<?php
declare(strict_types=1);

trait EmployeeRaiseRequestDecisionTrait
{
    /**
     * @param array<string,mixed> $request
     * @return array{result:string,status:string,salary:float,morale:float,deadline_at:null}
     */
    private function applyAccepted(
        array $request,
        EmployeeRef $ref,
        float $salary,
        float $moraleGain,
        string $status,
        float $loyaltyGain = 0.0,
        float $leaveRiskDelta = 0.0
    ): array {
        $this->updateSalary($ref, $salary);
        if ($loyaltyGain > 0.0) {
            $this->updateLoyalty($ref, $loyaltyGain);
        }
        $morale = $this->updateState($ref, $moraleGain, 'normal', 0.0, $leaveRiskDelta);
        $negotiatedSalary = $status === 'negotiated' ? $salary : null;
        $this->resolveRequest((int)$request['id'], $ref->playerId, $status, null, true, false, $negotiatedSalary);
        return ['result' => $status, 'status' => $status, 'salary' => $salary, 'morale' => $morale, 'deadline_at' => null];
    }

    /**
     * @param array<string,mixed> $request
     * @param array<string,mixed> $employee
     * @param array<string,mixed> $state
     * @param array<string,float> $formula
     * @return array{result:string,status:string,salary:float,morale:float,deadline_at:null}
     */
    private function applyNegotiation(
        array $request,
        EmployeeRef $ref,
        array $employee,
        array $state,
        float $currentSalary,
        float $requestedSalary,
        float $offeredSalary,
        array &$formula
    ): array {
        if ($offeredSalary <= $currentSalary || $offeredSalary > $requestedSalary) {
            throw new InvalidArgumentException('Negotiated salary must be above current and no higher than requested salary.');
        }
        $formula = $this->negotiationFormula(
            $ref->playerId,
            $employee,
            $state,
            $currentSalary,
            $requestedSalary,
            $offeredSalary
        );
        if ((float)$formula['random_roll'] <= (float)$formula['chance']) {
            return $this->applyAccepted(
                $request,
                $ref,
                $offeredSalary,
                $this->config->getFloat('raise_negotiated_morale_gain'),
                'negotiated',
                0.0,
                -$this->config->getFloat('raise_accept_leave_risk_reduction')
            );
        }

        $morale = $this->updateState(
            $ref,
            -$this->config->getFloat('raise_negotiation_fail_morale_penalty'),
            'raise_requested',
            0.0
        );
        $this->resolveRequest((int)$request['id'], $ref->playerId, 'open', (string)$request['deadline_at'], false);
        return [
            'result' => 'rejected_offer',
            'status' => 'open',
            'salary' => $currentSalary,
            'morale' => $morale,
            'deadline_at' => null,
        ];
    }

    /**
     * @param array<string,mixed> $request
     * @return array{result:string,status:string,salary:float,morale:float,deadline_at:null}
     */
    private function applyRejected(array $request, EmployeeRef $ref): array
    {
        $salary = $this->currentSalary($ref);
        $morale = $this->updateState(
            $ref,
            -$this->config->getFloat('raise_reject_morale_penalty'),
            'dispute',
            $this->config->getFloat('raise_reject_support_gain'),
            $this->config->getFloat('raise_reject_leave_risk_gain')
        );
        $this->resolveRequest((int)$request['id'], $ref->playerId, 'rejected', null, true);
        return ['result' => 'rejected', 'status' => 'rejected', 'salary' => $salary, 'morale' => $morale, 'deadline_at' => null];
    }

    /**
     * @param array<string,mixed> $request
     * @return array{result:string,status:string,salary:float,morale:float,deadline_at:string}
     */
    private function applyPostponed(array $request, EmployeeRef $ref): array
    {
        if ((int)($request['postponed_count'] ?? 0) >= $this->postponeLimit()) {
            throw new RuntimeException('Raise request postpone limit has been reached.');
        }
        $deadline = date('Y-m-d H:i:s', time() + $this->config->getInt('raise_postpone_hours') * 3600);
        $morale = $this->updateState(
            $ref,
            -$this->config->getFloat('raise_postpone_morale_penalty'),
            'raise_requested',
            0.0,
            $this->config->getFloat('raise_postpone_leave_risk_gain')
        );
        $this->resolveRequest((int)$request['id'], $ref->playerId, 'postponed', $deadline, false, true);
        return [
            'result' => 'postponed',
            'status' => 'postponed',
            'salary' => $this->currentSalary($ref),
            'morale' => $morale,
            'deadline_at' => $deadline,
        ];
    }
}
