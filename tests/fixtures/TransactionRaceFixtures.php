<?php
declare(strict_types=1);

final class TransactionRaceFixtures
{
    /** @var array<string,list<int>> */
    private array $inserted = [
        'players' => [], 'world_regions' => [], 'legal_region_config' => [], 'world_locations' => [],
    ];

    public function __construct(private readonly PDO $db)
    {
        self::assertDedicatedDatabase($db);
    }

    public static function isDedicatedName(string $name): bool
    {
        return preg_match('/^(?:(?:oil_test|ci_test)(?:_[a-zA-Z0-9_]+)?|oil_review_[0-9]{8}_test)$/D', $name) === 1;
    }

    public static function assertDedicatedDatabase(PDO $db): void
    {
        $expected = (string)getenv('DB_NAME');
        if (!self::isDedicatedName($expected)
            || $db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
            || $db->query('SELECT DATABASE()')->fetchColumn() !== $expected) {
            throw new RuntimeException('Transaction race tests require the actual database to match an approved test DB_NAME.');
        }
    }

    /** @param array<string,int|float|string|null> $values */
    public function insert(string $table, array $values): void
    {
        if (!array_key_exists($table, $this->inserted)) {
            throw new InvalidArgumentException('Unsupported transaction race fixture table.');
        }
        if ($this->db->inTransaction()) {
            throw new LogicException('Fixture registration requires a committed insert.');
        }
        if ($table === 'players') {
            $values += ['created_at' => '2026-09-23 10:00:00', 'last_tick_at' => '2026-09-23 10:00:00'];
        }
        $key = $table === 'legal_region_config' ? 'region_id' : 'id';
        $id = (int)($values[$key] ?? 0);
        if ($id <= 0) throw new InvalidArgumentException('Fixture ID is required.');
        $columns = array_keys($values);
        foreach ($columns as $column) {
            if (preg_match('/^[a-z_]+$/D', $column) !== 1) {
                throw new InvalidArgumentException('Invalid fixture column.');
            }
        }
        $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $columns) . ') VALUES ('
            . implode(',', array_fill(0, count($columns), '?')) . ')';
        $this->db->prepare($sql)->execute(array_values($values));
        // Register only after INSERT succeeds. / Rejestruj dopiero po udanym INSERT.
        $this->inserted[$table][] = $id;
    }

    public function cleanup(): void
    {
        self::assertDedicatedDatabase($this->db);
        if ($this->db->inTransaction()) $this->db->rollBack();
        foreach ($this->inserted['players'] as $id) {
            foreach (['market_sale_history', 'market_offers', 'storage', 'bank_negotiations',
                'bailiff_proceedings', 'loans', 'drilling_permit_applications', 'hub_permit_applications',
                'director_notifications', 'company_credibility_log', 'wells'] as $table) {
                $this->db->prepare("DELETE FROM {$table} WHERE player_id = ?")->execute([$id]);
            }
            $this->db->prepare('DELETE FROM bank_transactions WHERE from_player_id = ? OR to_player_id = ?')->execute([$id, $id]);
            $this->db->prepare('DELETE FROM players WHERE id = ?')->execute([$id]);
        }
        $this->inserted['players'] = [];
        foreach (['world_locations', 'legal_region_config', 'world_regions'] as $table) {
            $key = $table === 'legal_region_config' ? 'region_id' : 'id';
            foreach ($this->inserted[$table] as $id) {
                $this->db->prepare("DELETE FROM {$table} WHERE {$key} = ?")->execute([$id]);
            }
            $this->inserted[$table] = [];
        }
    }
}
