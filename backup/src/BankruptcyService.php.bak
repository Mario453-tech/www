<?php

require_once __DIR__ . '/Bankruptcy/StateTrait.php';
require_once __DIR__ . '/Bankruptcy/OptionsTrait.php';
require_once __DIR__ . '/Bankruptcy/EventsTrait.php';

/**
 * Service for bankruptcy mode and restructuring options.
 * PL: Serwis trybu bankructwa i opcji restrukturyzacji.
 *
 * Logic is split into traits in src/Bankruptcy/.
 * PL: Logika jest podzielona na traity w src/Bankruptcy/.
 *   - StateTrait.php   - state loading, recovery mode, events and counters
 *      PL: odczyt stanu, recovery mode, eventy i liczniki
 *   - OptionsTrait.php - applying bankruptcy options
 *      PL: stosowanie opcji bankructwa
 *   - EventsTrait.php  - ticking bankruptcy flow and critical events
 *      PL: tick bankructwa i obsluga krytycznych eventow
 */
class BankruptcyService
{
    use BankruptcyStateTrait;
    use BankruptcyOptionsTrait;
    use BankruptcyEventsTrait;

    private PDO $db;
    private int $playerId;

    public function __construct(int $playerId)
    {
        try {
            $this->db = Database::getInstance()->getConnection();
            $this->playerId = $playerId;
        } catch (Throwable $e) {
            GameLog::error('BankruptcyService', '__construct failed', $e, ['player_id' => $playerId]);
            throw $e;
        }
    }
}
