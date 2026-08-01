<?php
declare(strict_types=1);

final class EmployeeActionReceiptService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array{id:int,replayed:bool,response:?array<string,mixed>} */
    public function claim(int $playerId, string $actionKey, string $token, array $request): array
    {
        $token = trim($token);
        if ($playerId <= 0
            || !preg_match('/^[A-Za-z0-9._:-]{16,128}$/', $token)
            || !preg_match('/^[a-z0-9._:-]{3,80}$/', $actionKey)) {
            throw new InvalidArgumentException('Invalid HR action idempotency data.');
        }
        $hash = hash('sha256', json_encode($this->canonicalize($request), JSON_THROW_ON_ERROR));
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'INSERT OR IGNORE INTO employee_action_receipts
                (player_id, action_key, idempotency_token, request_hash) VALUES (?, ?, ?, ?)'
            : 'INSERT IGNORE INTO employee_action_receipts
                (player_id, action_key, idempotency_token, request_hash) VALUES (?, ?, ?, ?)';
        $insert = $this->db->prepare($sql);
        $insert->execute([$playerId, $actionKey, $token, $hash]);
        if ($insert->rowCount() === 1) {
            return ['id'=>(int)$this->db->lastInsertId(), 'replayed'=>false, 'response'=>null];
        }

        $suffix = $driver === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare(
            "SELECT id, request_hash, response_json, completed_at
               FROM employee_action_receipts
              WHERE player_id=? AND action_key=? AND idempotency_token=? LIMIT 1{$suffix}"
        );
        $stmt->execute([$playerId, $actionKey, $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !hash_equals((string)$row['request_hash'], $hash)) {
            throw new RuntimeException('HR action token was reused with different data.');
        }
        if ($row['completed_at'] === null || $row['response_json'] === null) {
            throw new RuntimeException('HR action is already being processed.');
        }
        $response = json_decode((string)$row['response_json'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($response)) {
            throw new RuntimeException('Stored HR action response is invalid.');
        }
        return ['id'=>(int)$row['id'], 'replayed'=>true, 'response'=>$response];
    }

    /** @param array<string,mixed> $response */
    public function complete(int $receiptId, int $playerId, array $response): void
    {
        $stmt = $this->db->prepare(
            'UPDATE employee_action_receipts
                SET response_json=?, completed_at=CURRENT_TIMESTAMP
              WHERE id=? AND player_id=? AND completed_at IS NULL'
        );
        $stmt->execute([
            json_encode($response, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
            $receiptId,
            $playerId,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('HR action receipt could not be completed.');
        }
    }

    /**
     * Sorts payload maps recursively while preserving list order.
     * Sortuje mapy payloadu rekurencyjnie, zachowujac kolejnosc list.
     */
    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn(mixed $item): mixed => $this->canonicalize($item), $value);
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }
        return $value;
    }
}
