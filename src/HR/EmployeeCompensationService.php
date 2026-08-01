<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Employee/EmployeeRef.php';

/**
 * Keeps linked employee sources and the financial contract on one salary.
 * Utrzymuje powiazane zrodla pracownika i kontrakt finansowy na jednej pensji.
 */
final class EmployeeCompensationService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function setSalary(EmployeeRef $ref, float $salary): void
    {
        if (!is_finite($salary) || $salary <= 0.0 || $salary > 999999999999.99) {
            throw new InvalidArgumentException('Employee salary is outside the supported range.');
        }
        $salary = round($salary, 2);
        $sources = $this->linkedSources($ref);
        foreach ($sources as $source) {
            $this->updateSource($source, $salary);
        }

        foreach ($sources as $source) {
            if ($source->sourceType !== EmployeeRef::SOURCE_BOARD_MEMBER) {
                continue;
            }
            $contract = $this->db->prepare(
                "UPDATE employee_contracts
                    SET salary=?
                  WHERE member_id=? AND status='active'
                    AND EXISTS (
                        SELECT 1 FROM board_members bm
                         WHERE bm.id=employee_contracts.member_id
                           AND bm.player_id=? AND bm.status='active'
                    )"
            );
            $contract->execute([$salary, $source->sourceId, $source->playerId]);
        }
    }

    public function applyRaise(EmployeeRef $ref, float $raisePct): void
    {
        if (!is_finite($raisePct) || $raisePct <= 0.0 || $raisePct > 100.0) {
            throw new InvalidArgumentException('Employee raise percentage is outside the supported range.');
        }
        $source = $this->lockSource($ref);
        $this->setSalary($ref, round((float)$source['salary'] * (1.0 + $raisePct / 100.0), 2));
    }

    /** @return list<EmployeeRef> */
    public function linkedSources(EmployeeRef $ref): array
    {
        $suffix = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare(
            'SELECT board_member_id, technical_staff_id
               FROM employee_source_links
              WHERE player_id=?
                AND ((board_member_id=? AND ?=\'board_member\')
                  OR (technical_staff_id=? AND ?=\'technical_staff\'))
              LIMIT 1' . $suffix
        );
        $stmt->execute([
            $ref->playerId,
            $ref->sourceId,
            $ref->sourceType,
            $ref->sourceId,
            $ref->sourceType,
        ]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($link)) {
            return [$ref];
        }

        return [
            new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, (int)$link['board_member_id'], $ref->playerId),
            new EmployeeRef(EmployeeRef::SOURCE_TECHNICAL_STAFF, (int)$link['technical_staff_id'], $ref->playerId),
        ];
    }

    /** @return array{salary:float} */
    private function lockSource(EmployeeRef $ref): array
    {
        $table = $ref->sourceType === EmployeeRef::SOURCE_TECHNICAL_STAFF
            ? 'technical_staff'
            : 'board_members';
        $suffix = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare("SELECT salary FROM {$table} WHERE id=? AND player_id=? LIMIT 1{$suffix}");
        $stmt->execute([$ref->sourceId, $ref->playerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('Employee source does not exist for compensation update.');
        }
        return ['salary' => (float)$row['salary']];
    }

    private function updateSource(EmployeeRef $ref, float $salary): void
    {
        $table = $ref->sourceType === EmployeeRef::SOURCE_TECHNICAL_STAFF
            ? 'technical_staff'
            : 'board_members';
        $statusSql = $ref->sourceType === EmployeeRef::SOURCE_TECHNICAL_STAFF
            ? "status IN ('active','busy','on_leave')"
            : "status='active'";
        $stmt = $this->db->prepare(
            "UPDATE {$table} SET salary=? WHERE id=? AND player_id=? AND {$statusSql}"
        );
        $stmt->execute([$salary, $ref->sourceId, $ref->playerId]);
        if ($stmt->rowCount() === 1) {
            return;
        }
        $check = $this->db->prepare(
            "SELECT salary FROM {$table} WHERE id=? AND player_id=? AND {$statusSql}"
        );
        $check->execute([$ref->sourceId, $ref->playerId]);
        $current = $check->fetchColumn();
        if ($current === false || abs((float)$current - $salary) > 0.009) {
            throw new RuntimeException('Employee salary update did not affect the required source.');
        }
    }
}
