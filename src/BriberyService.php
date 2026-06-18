<?php

declare(strict_types=1);

require_once __DIR__ . '/Bribery/BriberyConfig.php';
require_once __DIR__ . '/FinancialTransactionService.php';
require_once __DIR__ . '/CompanyCredibilityService.php';

/**
 * BriberyService - uniwersalny silnik lapowek ("gniazdko").
 * BriberyService - universal bribery engine (the "socket").
 *
 * Modul, ktory chce lapowek, podaje tylko trzy rzeczy: koszt odniesienia, co
 * zrobic przy sukcesie i (opcjonalnie) dodatkowa kare przy wpadce. Silnik sam
 * liczy cene i ryzyko z reputacji firmy, pobiera gotowke, losuje wynik, ksieguje
 * kary reputacji, wysyla powiadomienie i robi wszystko w jednej transakcji.
 *
 * A module that wants bribery only provides three things: a reference cost, what
 * to do on success and (optionally) an extra penalty on getting caught. The engine
 * computes price and risk from company reputation, charges cash, rolls the outcome,
 * books reputation penalties, sends a notification and does it all in one transaction.
 *
 * Wzorzec wdrozenia w innym module / How to plug into another module:
 *   $bribery = new BriberyService($db);
 *   $bribery->attempt($playerId, 'context_key', $referenceCost,
 *       fn() => /* odblokuj cos przy sukcesie / unlock something on success * /,
 *       ['on_caught' => fn() => /* dodatkowa kara / extra penalty * /,
 *        'meta' => ['label' => 'Nazwa', 'action_url' => 'modul.php']]);
 */
class BriberyService
{
    private PDO $db;
    private BriberyConfig $config;
    private CompanyCredibilityService $credibility;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        // Config i serwis reputacji budowane w konstruktorze (poza transakcja),
        // by ich setup schematu nie byl pominiety w otwartej transakcji.
        // Config and reputation service built in the constructor (outside a tx),
        // so their schema setup is not skipped inside an open transaction.
        $this->config = new BriberyConfig($this->db);
        $this->credibility = new CompanyCredibilityService($this->db);

        if (class_exists('GameLog', false)) {
            GameLog::info('BriberyService', '__construct');
        }
    }

    public function config(): BriberyConfig
    {
        return $this->config;
    }

    /**
     * Wycena lapowki bez ruchu srodkow (dla UI: koszt + ryzyko wpadki).
     * Bribe quote without money movement (for UI: cost + catch chance).
     *
     * @return array{enabled:bool,cost:int,catch_pct:int,level:string}
     */
    public function quote(int $playerId, float $referenceCost): array
    {
        $level = $this->credibility->getLevel($this->credibility->getScore($playerId));
        return [
            'enabled'   => $this->config->isEnabled(),
            'cost'      => $this->costFor($referenceCost, $level),
            'catch_pct' => $this->config->catchChanceFor($level),
            'level'     => $level,
        ];
    }

    private function costFor(float $referenceCost, string $level): int
    {
        return (int)round(max(0.0, $referenceCost) * $this->config->baseCostFraction() * $this->config->priceMultFor($level));
    }

    /**
     * Probuje przekupic. Pobiera gotowke, losuje wynik i ksieguje skutki.
     * Attempts a bribe. Charges cash, rolls the outcome and books the effects.
     *
     * @param string   $contextKey  Identyfikator kontekstu (np. 'legal_permit') do logow i historii.
     * @param float    $referenceCost Koszt odniesienia modulu (silnik dolicza % bazowy i mnoznik reputacji).
     * @param callable $onSuccess   Wykonywane w transakcji TYLKO przy sukcesie (praca modulu).
     * @param array{on_caught?:callable,meta?:array<string,mixed>} $opts
     * @return array<string,mixed> ['success'=>bool,'outcome'=>string,'caught'=>bool,'cost'=>int,'message'=>string]
     */
    public function attempt(int $playerId, string $contextKey, float $referenceCost, callable $onSuccess, array $opts = []): array
    {
        if (!$this->config->isEnabled()) {
            return ['success' => false, 'outcome' => 'disabled', 'message' => tPlain('bribery.err_disabled')];
        }

        $onCaught = (isset($opts['on_caught']) && is_callable($opts['on_caught'])) ? $opts['on_caught'] : null;
        $meta = (array)($opts['meta'] ?? []);
        $label = (string)($meta['label'] ?? '');

        $level = $this->credibility->getLevel($this->credibility->getScore($playerId));
        $cost = $this->costFor($referenceCost, $level);
        $catchPct = $this->config->catchChanceFor($level);

        $fts = new FinancialTransactionService($this->db);
        $this->db->beginTransaction();
        try {
            // Lapowka to wydatek wylacznie gotowkowy (bribe -> POOL_CASH w WalletConfig).
            // A bribe is a cash-only expense (bribe -> POOL_CASH in WalletConfig).
            $charge = $fts->debit(
                $playerId,
                (float)$cost,
                FinancialTransactionService::TYPE_BRIBE,
                tPlain('bribery.tx_label', ['context' => $label !== '' ? $label : $contextKey])
            );
            if (empty($charge['success'])) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'outcome' => 'no_funds',
                    'cost'    => $cost,
                    'message' => tPlain('bribery.err_no_funds', ['cost' => number_format($cost, 0, '.', ' ')]),
                ];
            }

            $caught = mt_rand(1, 100) <= $catchPct;

            if ($caught) {
                // Wpadka: mocna kara reputacji (incydent w historii) + praca modulu + alert.
                // Caught: strong reputation penalty (history incident) + module penalty + alert.
                $this->credibility->changeScore(
                    $playerId,
                    -$this->config->penaltyCaught(),
                    'bribe_caught',
                    tPlain('bribery.note_caught', ['context' => $label !== '' ? $label : $contextKey])
                );
                if ($onCaught !== null) {
                    $onCaught();
                }
                $this->notifyCaught($playerId, $meta);
                $this->db->commit();

                if (class_exists('GameLog', false)) {
                    GameLog::info('BriberyService', 'attempt CAUGHT', ['player_id' => $playerId, 'context' => $contextKey, 'cost' => $cost]);
                }
                return [
                    'success' => false,
                    'outcome' => 'caught',
                    'caught'  => true,
                    'cost'    => $cost,
                    'message' => tPlain('bribery.msg_caught', ['cost' => number_format($cost, 0, '.', ' ')]),
                ];
            }

            // Sukces: praca modulu (odblokowanie) + lekka kara reputacji.
            // Success: module work (unlock) + small reputation penalty.
            $onSuccess();
            $this->credibility->changeScore(
                $playerId,
                -$this->config->penaltySuccess(),
                'bribe_paid',
                tPlain('bribery.note_success', ['context' => $label !== '' ? $label : $contextKey])
            );
            $this->db->commit();

            if (class_exists('GameLog', false)) {
                GameLog::info('BriberyService', 'attempt SUCCESS', ['player_id' => $playerId, 'context' => $contextKey, 'cost' => $cost]);
            }
            return [
                'success' => true,
                'outcome' => 'success',
                'caught'  => false,
                'cost'    => $cost,
                'message' => tPlain('bribery.msg_success', ['cost' => number_format($cost, 0, '.', ' ')]),
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if (class_exists('GameLog', false)) {
                GameLog::error('BriberyService', 'attempt FAILED', $e, ['player_id' => $playerId, 'context' => $contextKey]);
            }
            return ['success' => false, 'outcome' => 'error', 'message' => tPlain('bribery.err_generic')];
        }
    }

    /**
     * Wstawia powiadomienie dyrektora o wpadce (uniwersalny alert dla kazdego modulu).
     * W pelni guarded - brak tabeli nie przerywa operacji.
     * Inserts a director "caught" notification (universal alert for every module).
     * Fully guarded - a missing table never breaks the operation.
     *
     * @param array<string,mixed> $meta
     */
    private function notifyCaught(int $playerId, array $meta): void
    {
        try {
            $label       = (string)($meta['label'] ?? '');
            $type        = (string)($meta['notif_type'] ?? 'legal');
            $actionUrl   = isset($meta['action_url']) ? (string)$meta['action_url'] : null;
            $actionLabel = isset($meta['action_label']) ? (string)$meta['action_label'] : null;

            $title   = tPlain('bribery.notif.caught.title');
            $message = tPlain('bribery.notif.caught.message', ['context' => $label]);
            $expires = (new DateTime())->modify('+72 hours')->format('Y-m-d H:i:s');

            $this->db->prepare(
                "INSERT INTO director_notifications
                    (player_id, type, priority, title, message, icon,
                     requires_action, action_url, action_label, expires_at)
                 VALUES (?, ?, 'high', ?, ?, 'warning', ?, ?, ?, ?)"
            )->execute([
                $playerId, $type, $title, $message,
                $actionUrl !== null ? 1 : 0, $actionUrl, $actionLabel, $expires,
            ]);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('BriberyService', 'notifyCaught FAILED', $e, ['player_id' => $playerId]);
            }
        }
    }
}
