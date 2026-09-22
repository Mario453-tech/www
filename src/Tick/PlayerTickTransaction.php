<?php
declare(strict_types=1);

/** Atomic player period; schema preparation must happen before this boundary.
 * Atomowy okres gracza; przygotowanie schematu musi nastapic przed ta granica.
 */
final class PlayerTickTransaction
{
    public function __construct(private PDO $db)
    {
    }

    /** @param callable(array<string, mixed>): void $process */
    public function run(int $playerId, callable $process): void
    {
        $own = !$this->db->inTransaction();
        if ($own) {
            $this->db->beginTransaction();
        } else {
            $this->db->exec('SAVEPOINT player_tick');
        }
        try {
            $lock = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $stmt = $this->db->prepare('SELECT * FROM players WHERE id = ?' . $lock);
            $stmt->execute([$playerId]);
            $player = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($player && ($player['status'] ?? '') !== 'bankrupt') {
                $storage = $this->db->prepare('SELECT player_id FROM storage WHERE player_id = ?' . $lock);
                $storage->execute([$playerId]);
                $storage->fetchAll();
                $process($player);
            }
            if (!$this->db->inTransaction()) {
                throw new RuntimeException('Player tick transaction was unexpectedly closed');
            }
            if ($own) {
                $this->db->commit();
            } else {
                $this->db->exec('RELEASE SAVEPOINT player_tick');
            }
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                if ($own) {
                    $this->db->rollBack();
                } else {
                    $this->db->exec('ROLLBACK TO SAVEPOINT player_tick');
                    $this->db->exec('RELEASE SAVEPOINT player_tick');
                }
            }
            throw $e;
        }
    }
}
