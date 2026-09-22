<?php

trait TTSTaskTransactionTrait
{
    /**
     * @template T
     * @param callable():T $operation
     * @return T
     */
    private function taskTransaction(callable $operation): mixed
    {
        $owns = !$this->db->inTransaction();
        $savepoint = 'tts_' . bin2hex(random_bytes(8));
        if ($owns) {
            // Schema setup must precede BEGIN. / Przygotowanie schematu musi poprzedzac BEGIN.
            new FinancialTransactionService($this->db);
            $this->db->beginTransaction();
        } else {
            $this->db->exec('SAVEPOINT ' . $savepoint);
        }
        try {
            $result = $operation();
            if ($owns) {
                $this->db->commit();
            } else {
                $this->db->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            return $result;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                if ($owns) {
                    $this->db->rollBack();
                } else {
                    $this->db->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    $this->db->exec('RELEASE SAVEPOINT ' . $savepoint);
                }
            }
            throw $e;
        }
    }

    private function taskLockSuffix(): string
    {
        return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    }

    /** @return array<string,mixed>|null */
    private function getLockedTaskStaff(int $staffId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM technical_staff WHERE id=? AND player_id=?' . $this->taskLockSuffix());
        $stmt->execute([$staffId, $this->playerId]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$staff) return null;
        // Shared dictionary reads must not lock other players. / Odczyt slownika nie blokuje innych graczy.
        $spec = $this->db->prepare('SELECT repair_speed FROM staff_specializations WHERE code=?');
        $spec->execute([$staff['specialization']]);
        $speed = $spec->fetchColumn();
        $staff['repair_speed'] = $speed === false ? null : $speed;
        return $staff;
    }

    private function lockTaskOwner(?int $staffId = null): void
    {
        // Match the tick and HR lock order. / Zachowaj kolejnosc blokad ticka i HR.
        $stmt = $this->db->prepare('SELECT id FROM players WHERE id=?' . $this->taskLockSuffix());
        $stmt->execute([$this->playerId]);
        if ($stmt->fetchColumn() === false) {
            throw new RuntimeException('Technical task player does not exist.');
        }
        if ($staffId !== null) {
            $stmt = $this->db->prepare('SELECT id FROM technical_staff WHERE id=? AND player_id=?' . $this->taskLockSuffix());
            $stmt->execute([$staffId, $this->playerId]);
            $stmt->fetchColumn();
        }
    }
}
