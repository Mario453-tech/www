<?php
// Deploy sync after skipped CI gate. / Ponowny upload po pominietej bramce CI.
declare(strict_types=1);

final class AdminPlayerListQuery
{
    /**
     * @param array<string, mixed> $input
     * @return array{filter: string, login_from: string, login_to: string, registered_from: string, registered_to: string, sort: string}
     */
    public static function filters(array $input): array
    {
        $result = [];
        foreach (['filter', 'login_from', 'login_to', 'registered_from', 'registered_to', 'sort'] as $key) {
            if (isset($input[$key]) && !is_string($input[$key])) {
                throw new InvalidArgumentException('Invalid filter value');
            }
            $result[$key] = trim($input[$key] ?? '');
        }
        if (!in_array($result['filter'], ['', 'active', 'bankrupt', 'financial_risk', 'under_bailiff'], true)) {
            $result['filter'] = '';
        }
        if (!in_array($result['sort'], ['id', 'login_desc', 'login_asc'], true)) {
            $result['sort'] = 'login_desc';
        }
        foreach (['login_from', 'login_to', 'registered_from', 'registered_to'] as $key) {
            $value = $result[$key];
            if ($value === '') {
                continue;
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value || $value < '1000-01-01' || $value > '9999-12-30') {
                throw new InvalidArgumentException('Invalid date');
            }
        }
        if ($result['login_from'] !== '' && $result['login_to'] !== '' && $result['login_from'] > $result['login_to']) {
            throw new InvalidArgumentException('Reversed login date range');
        }
        if ($result['registered_from'] !== '' && $result['registered_to'] !== '' && $result['registered_from'] > $result['registered_to']) {
            throw new InvalidArgumentException('Reversed registration date range');
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $input
     * @return list<array<string, mixed>>
     */
    public static function fetch(PDO $db, array $input): array
    {
        $filters = self::filters($input);
        $conditions = [];
        $params = [];
        if ($filters['filter'] !== '') {
            $conditions[] = 'p.status = ?';
            $params[] = $filters['filter'];
        }
        if ($filters['login_from'] !== '') {
            $conditions[] = 'p.last_login_at >= ?';
            $params[] = $filters['login_from'] . ' 00:00:00';
        }
        if ($filters['login_to'] !== '') {
            $conditions[] = 'p.last_login_at < ?';
            $params[] = (new DateTimeImmutable($filters['login_to']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
        }
        if ($filters['registered_from'] !== '') {
            $conditions[] = 'p.created_at >= ?';
            $params[] = $filters['registered_from'] . ' 00:00:00';
        }
        if ($filters['registered_to'] !== '') {
            $conditions[] = 'p.created_at < ?';
            $params[] = (new DateTimeImmutable($filters['registered_to']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $order = match ($filters['sort']) {
            'login_asc' => 'p.last_login_at IS NULL ASC, p.last_login_at ASC, p.id ASC',
            'login_desc' => 'p.last_login_at IS NULL ASC, p.last_login_at DESC, p.id ASC',
            default => 'p.id ASC',
        };
        $stmt = $db->prepare("SELECT p.id, p.email, p.cash, p.status, p.created_at, p.last_login_at,
            s.used AS storage_used, s.capacity AS storage_capacity,
            (SELECT COUNT(*) FROM wells w WHERE w.player_id = p.id) AS well_count
            FROM players p LEFT JOIN storage s ON p.id = s.player_id
            {$where} ORDER BY {$order}");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
