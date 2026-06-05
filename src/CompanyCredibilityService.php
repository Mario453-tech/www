<?php

declare(strict_types=1);

/**
 * CompanyCredibilityService — Wiarygodnosc firmy: fundament systemu reputacji.
 * CompanyCredibilityService — Company credibility: foundation of the reputation system.
 *
 * Ogolny wskaznik reputacji firmy wobec swiata gry (skala 0-100, start 50).
 * General company-reputation indicator in the game world (0-100 scale, starts at 50).
 *
 * NIE zastepuje istniejacych wskaznikow (zasada nadrzedna briefu, sekcja 11):
 * Does NOT replace existing indicators (brief rule, section 11):
 *  - credit_score        — ocena kredytowa banku / bank credit rating
 *  - bank_trust_scores   — ukryte zaufanie banku / hidden bank trust
 *  - black_market_score  — slad po czarnym rynku / black market footprint
 *
 * Zasada: zaden inny kod nie zmienia players.company_credibility bezposrednio —
 * wszystkie zmiany przechodza przez ten serwis (sekcja 4 briefu).
 * Rule: no other code mutates players.company_credibility directly — every change
 * goes through this service (brief section 4).
 */
class CompanyCredibilityService
{
    /** Zakres wyniku / Score bounds (sekcja 1.1). */
    public const MIN_SCORE     = 0;
    public const MAX_SCORE     = 100;
    public const DEFAULT_SCORE = 50;

    /**
     * Mapa zdarzenie -> delta punktow (sekcja 6 briefu).
     * Event -> point-delta map (brief section 6).
     *
     * admin_manual_adjustment ma delte dynamiczna — podawana wprost do changeScore().
     * admin_manual_adjustment has a dynamic delta — passed directly to changeScore().
     */
    public const EVENT_DELTAS = [
        // Zdarzenia negatywne / Negative events (6.1)
        'black_market_detected'         => -12,
        'bailiff_activated'             => -20,
        'bankruptcy_entered'            => -25,
        'recovery_plan_broken'          => -10,
        'major_payment_delay'           => -6,
        // Zdarzenia pozytywne / Positive events (6.2)
        'loan_installment_paid_on_time' => 2,
        'loan_fully_repaid'             => 8,
        'loan_repaid_early'             => 6,
        'clean_operation_period'        => 3,
    ];

    /** Prog generowania powiadomienia gracza (sekcja 9): |delta| >= 5. */
    public const NOTIFY_THRESHOLD = 5;

    /** Okres bez naruszen do bonusu +3 / Clean period for the +3 bonus. */
    public const CLEAN_OPERATION_DAYS = 7;

    private PDO $db;

    /** @var array<int,bool> cache zapewnionego schematu per polaczenie / schema-ensured cache per connection */
    private static array $schemaEnsured = [];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->ensureSchema();
    }

    /**
     * Zwraca sterownik PDO (mysql/sqlite). Na blad zaklada mysql (produkcja).
     * Returns the PDO driver name (mysql/sqlite). On error assumes mysql (production).
     */
    private function driver(): string
    {
        try {
            return (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable) {
            return 'mysql';
        }
    }

    // ----------------------------------------------------------------- Schema

    /**
     * Tworzy kolumne i tabele historii (idempotentnie). DDL MySQL — na SQLite
     * (testy) jest no-op, bo testy buduja wlasny schemat.
     * Creates the column and history table (idempotent). MySQL DDL — no-op on
     * SQLite (tests build their own schema).
     */
    public function ensureSchema(): void
    {
        $connId = spl_object_id($this->db);
        if (isset(self::$schemaEnsured[$connId])) {
            return;
        }

        if ($this->driver() !== 'mysql') {
            self::$schemaEnsured[$connId] = true;
            return;
        }

        // DDL (ALTER/CREATE) robi w MySQL niejawny commit — nie wolno go odpalac
        // wewnatrz transakcji (np. splata kredytu, czarny rynek). Odraczamy do
        // pierwszej konstrukcji poza transakcja (dashboard / panel admina).
        // DDL (ALTER/CREATE) triggers an implicit commit in MySQL — never run it
        // inside a transaction (e.g. loan repayment, black market). Defer until the
        // first construction outside a transaction (dashboard / admin panel).
        try {
            if ($this->db->inTransaction()) {
                return;
            }
        } catch (Throwable) {
            // Brak wsparcia inTransaction — kontynuuj / inTransaction unsupported — continue
        }

        self::$schemaEnsured[$connId] = true;

        try {
            // Nowe pole wiarygodnosci w graczach / New credibility field on players (1.1)
            Database::addColumnIfMissing(
                'players',
                'company_credibility',
                'INT UNSIGNED NOT NULL DEFAULT ' . self::DEFAULT_SCORE
            );

            // Historia zmian — obowiazkowa (sekcja 3) / Change history — mandatory
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS company_credibility_log (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    player_id INT UNSIGNED NOT NULL,
                    event_key VARCHAR(64) NOT NULL,
                    delta INT NOT NULL,
                    score_before INT UNSIGNED NOT NULL,
                    score_after INT UNSIGNED NOT NULL,
                    note VARCHAR(255) NULL DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_player_created (player_id, created_at),
                    KEY idx_event (event_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            );

            // Powiadomienia typu 'credibility' (sekcja 9): kolumna director_notifications.type
            // to ENUM, ktory domyslnie NIE zawiera 'credibility'. Bez tego INSERT w notify()
            // sie wywala (MySQL strict 1265) i powiadomienie ginie. Rozszerzamy ENUM idempotentnie.
            // 'credibility' notifications (section 9): director_notifications.type is an ENUM that
            // by default does NOT include 'credibility'. Without this the notify() INSERT fails
            // (MySQL strict, 1265) and the notification is lost. Extend the ENUM idempotently.
            $this->ensureNotificationType();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('CompanyCredibilityService', 'ensureSchema FAILED', $e);
            }
        }
    }

    /**
     * Dodaje wartosc 'credibility' do ENUM director_notifications.type, jesli jej brak.
     * Idempotentne: czyta aktualny typ kolumny i robi ALTER tylko gdy trzeba.
     * Adds 'credibility' to the director_notifications.type ENUM when missing.
     * Idempotent: reads the current column type and ALTERs only if needed.
     */
    private function ensureNotificationType(): void
    {
        try {
            // Tabela moze nie istniec w niektorych srodowiskach — wtedy nic nie robimy.
            // The table may not exist in some environments — then do nothing.
            $stmt = $this->db->query(
                "SELECT COLUMN_TYPE
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'director_notifications'
                    AND COLUMN_NAME = 'type'
                  LIMIT 1"
            );
            $columnType = $stmt ? (string)$stmt->fetchColumn() : '';
            if ($columnType === '' || str_contains($columnType, "'credibility'")) {
                return; // brak tabeli lub wartosc juz jest / no table or value already present
            }

            // Wstawiamy 'credibility' do listy wartosci ENUM, zachowujac reszte definicji.
            // Inject 'credibility' into the ENUM value list, preserving the rest of the definition.
            $newType = preg_replace('/^enum\((.*)\)$/i', "enum($1,'credibility')", $columnType);
            if (!is_string($newType) || $newType === $columnType) {
                return;
            }

            $this->db->exec(
                "ALTER TABLE director_notifications
                 MODIFY COLUMN type {$newType} COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info'"
            );
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('CompanyCredibilityService', 'ensureNotificationType FAILED', $e);
            }
        }
    }

    // ------------------------------------------------------------- Odczyt / Read

    /**
     * Zwraca aktualny wynik gracza (przyciety do 0-100). Brak gracza → wartosc startowa.
     * Returns the player's current score (clamped 0-100). Missing player → default.
     */
    public function getScore(int $playerId): int
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT company_credibility FROM players WHERE id = ? LIMIT 1"
            );
            $stmt->execute([$playerId]);
            $val = $stmt->fetchColumn();
            if ($val === false || $val === null) {
                return self::DEFAULT_SCORE;
            }
            return $this->clamp((int)$val);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('CompanyCredibilityService', 'getScore FAILED', $e, ['player_id' => $playerId]);
            }
            return self::DEFAULT_SCORE;
        }
    }

    /**
     * Zwraca poziom opisowy (klucz wewnetrzny) dla wyniku (sekcja 2).
     * Returns the descriptive level (internal key) for a score (brief section 2).
     *
     * 0-19 critical · 20-39 low · 40-59 shaky · 60-79 stable · 80-100 high
     */
    public function getLevel(int $score): string
    {
        $score = $this->clamp($score);
        return match (true) {
            $score <= 19 => 'critical',
            $score <= 39 => 'low',
            $score <= 59 => 'shaky',
            $score <= 79 => 'stable',
            default      => 'high',
        };
    }

    /**
     * Pobiera historie zmian gracza (najnowsze pierwsze).
     * Fetches the player's change history (newest first).
     *
     * @return array<int,array<string,mixed>>
     */
    public function getHistory(int $playerId, int $limit = 100): array
    {
        try {
            $limit = max(1, min(1000, $limit));
            $stmt  = $this->db->prepare(
                "SELECT id, player_id, event_key, delta, score_before, score_after, note, created_at
                   FROM company_credibility_log
                  WHERE player_id = ?
                  ORDER BY id DESC
                  LIMIT {$limit}"
            );
            $stmt->execute([$playerId]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('CompanyCredibilityService', 'getHistory FAILED', $e, ['player_id' => $playerId]);
            }
            return [];
        }
    }

    /**
     * Przyznaje bonus za okres bez negatywnych zdarzen wiarygodnosci.
     * Grants the clean-operation bonus when no negative credibility events occurred.
     */
    public function applyCleanOperationBonus(int $playerId, int $days = self::CLEAN_OPERATION_DAYS, ?DateTimeInterface $now = null): bool
    {
        $days = max(1, min(365, $days));
        $nowDt = $now ? DateTime::createFromInterface($now) : new DateTime();
        $cutoff = (clone $nowDt)->modify("-{$days} days")->format('Y-m-d H:i:s');

        try {
            $negativeEvents = [];
            foreach (self::EVENT_DELTAS as $eventKey => $delta) {
                if ($delta < 0) {
                    $negativeEvents[] = $eventKey;
                }
            }

            if ($this->countEventsSince($playerId, $negativeEvents, $cutoff) > 0) {
                return false;
            }
            if ($this->countEventsSince($playerId, ['clean_operation_period'], $cutoff) > 0) {
                return false;
            }
            $note = function_exists('tPlain')
                ? tPlain('credibility.note_clean_operation_period', ['days' => $days])
                : "Clean operation period: {$days} days";
            $before = $this->getScore($playerId);
            $after = $this->changeScore($playerId, self::EVENT_DELTAS['clean_operation_period'], 'clean_operation_period', $note);

            return $after > $before;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('CompanyCredibilityService', 'applyCleanOperationBonus FAILED', $e, [
                    'player_id' => $playerId,
                    'days'      => $days,
                ]);
            }
            return false;
        }
    }

    /**
     * Liczy wskazane zdarzenia od podanej daty / Counts selected events since cutoff.
     * @param string[] $eventKeys
     */
    private function countEventsSince(int $playerId, array $eventKeys, string $cutoff): int
    {
        if (empty($eventKeys)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($eventKeys), '?'));
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
               FROM company_credibility_log
              WHERE player_id = ?
                AND event_key IN ({$placeholders})
                AND created_at >= ?"
        );
        $stmt->execute(array_merge([$playerId], $eventKeys, [$cutoff]));

        return (int)$stmt->fetchColumn();
    }

    // ----------------------------------------------------------- Zmiana / Mutate

    /**
     * Przycina wynik do dozwolonego zakresu 0-100 (sekcja 1.1).
     * Clamps a score into the allowed 0-100 range (brief section 1.1).
     */
    private function clamp(int $score): int
    {
        return max(self::MIN_SCORE, min(self::MAX_SCORE, $score));
    }

    /**
     * Stosuje zdarzenie ze stalej mapy delt (sekcja 6). Wygodne dla podpiec w grze.
     * Applies an event from the fixed delta map (brief section 6). Convenience for game hooks.
     *
     * W pelni guarded — nigdy nie przerywa operacji nadrzednej (splata, komornik itd.).
     * Fully guarded — never breaks the parent operation (repayment, bailiff, etc.).
     */
    public function applyEvent(int $playerId, string $eventKey, ?string $note = null): void
    {
        if (!isset(self::EVENT_DELTAS[$eventKey])) {
            if (class_exists('GameLog', false)) {
                GameLog::warn('CompanyCredibilityService', 'applyEvent: nieznane zdarzenie / unknown event', ['event_key' => $eventKey]);
            }
            return;
        }
        $delta = self::EVENT_DELTAS[$eventKey];
        if ($delta === 0) {
            return;
        }
        try {
            $this->changeScore($playerId, $delta, $eventKey, $note);
        } catch (Throwable $e) {
            // changeScore jest juz guarded, ale chronimy tez samo wywolanie z ticku/serwisu.
            // changeScore is already guarded, but we also protect this call from tick/service.
            if (class_exists('GameLog', false)) {
                GameLog::error('CompanyCredibilityService', 'applyEvent FAILED', $e, [
                    'player_id' => $playerId, 'event_key' => $eventKey,
                ]);
            }
        }
    }

    /**
     * Centralny punkt zmiany wyniku: przycina, zapisuje log, ewentualnie powiadamia.
     * Single mutation point: clamps, writes the log, optionally notifies.
     *
     * @return int wynik po zmianie / score after the change
     */
    public function changeScore(int $playerId, int $delta, string $eventKey, ?string $note = null): int
    {
        try {
            $before         = $this->getScore($playerId);
            $after          = $this->clamp($before + $delta);
            $effectiveDelta = $after - $before;

            // Aktualizacja pola gracza / Update the player field
            $this->db->prepare(
                "UPDATE players SET company_credibility = ? WHERE id = ?"
            )->execute([$after, $playerId]);

            // Log zawsze (sekcja 3) — nawet przy delcie efektywnej 0 (np. sufit/podloga),
            // zeby historia tlumaczyla dlaczego wynik sie nie zmienil.
            // Always log (section 3) — even when effective delta is 0 (ceiling/floor),
            // so history explains why the score did not move.
            $this->logChange($playerId, $eventKey, $effectiveDelta, $before, $after, $note);

            // Powiadomienie tylko przy wiekszych zmianach (sekcja 9): |delta| >= 5
            // Notify only on larger changes (section 9): |delta| >= 5
            if (abs($effectiveDelta) >= self::NOTIFY_THRESHOLD) {
                $this->notify($playerId, $eventKey, $effectiveDelta, $after);
            }

            if (class_exists('GameLog', false)) {
                GameLog::info('CompanyCredibilityService', 'changeScore', [
                    'player_id' => $playerId,
                    'event_key' => $eventKey,
                    'delta'     => $effectiveDelta,
                    'before'    => $before,
                    'after'     => $after,
                ]);
            }
            return $after;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('CompanyCredibilityService', 'changeScore FAILED', $e, [
                    'player_id' => $playerId, 'event_key' => $eventKey, 'delta' => $delta,
                ]);
            }
            return $this->getScore($playerId);
        }
    }

    /**
     * Zapisuje wpis historii zmiany (sekcja 3). Guarded — log nigdy nie wywraca zmiany.
     * Writes a change-history entry (section 3). Guarded — logging never breaks the change.
     */
    public function logChange(
        int     $playerId,
        string  $eventKey,
        int     $delta,
        int     $before,
        int     $after,
        ?string $note = null
    ): void {
        try {
            $this->db->prepare(
                "INSERT INTO company_credibility_log
                    (player_id, event_key, delta, score_before, score_after, note)
                 VALUES (?, ?, ?, ?, ?, ?)"
            )->execute([$playerId, $eventKey, $delta, $before, $after, $note]);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('CompanyCredibilityService', 'logChange FAILED', $e, [
                    'player_id' => $playerId, 'event_key' => $eventKey,
                ]);
            }
        }
    }

    // ------------------------------------------------------ Powiadomienia / Notify

    /**
     * Wstawia powiadomienie dyrektora (typ 'credibility'). W pelni guarded — brak
     * tabeli director_notifications nie przerywa zmiany wyniku (sekcja 9).
     * Inserts a director notification (type 'credibility'). Fully guarded — a missing
     * director_notifications table never breaks the score change (brief section 9).
     */
    private function notify(int $playerId, string $eventKey, int $delta, int $after): void
    {
        try {
            $level   = $this->getLevel($after);
            $dir     = $delta < 0 ? 'down' : 'up';
            $params  = [
                'score' => $after,
                'level' => tPlain('credibility.level_' . $level),
            ];

            // Komunikat zalezny od zdarzenia; brak klucza → komunikat ogolny wg kierunku.
            // Event-specific message; missing key → generic message by direction.
            $msgKey  = 'credibility.notif.msg_' . $eventKey;
            $message = tPlain($msgKey, $params);
            if ($message === $msgKey) {
                $message = tPlain('credibility.notif.msg_generic_' . $dir, $params);
            }

            $title    = tPlain('credibility.notif.title_' . $dir);
            $priority = $delta <= -15 ? 'high' : 'low';
            $expires  = (new DateTime())->modify('+72 hours')->format('Y-m-d H:i:s');

            $this->db->prepare(
                "INSERT INTO director_notifications
                    (player_id, type, priority, title, message, icon,
                     requires_action, action_url, action_label, expires_at)
                 VALUES (?, 'credibility', ?, ?, ?, '', 0, NULL, NULL, ?)"
            )->execute([$playerId, $priority, $title, $message, $expires]);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('CompanyCredibilityService', 'notify FAILED', $e, [
                    'player_id' => $playerId, 'event_key' => $eventKey,
                ]);
            }
        }
    }
}
