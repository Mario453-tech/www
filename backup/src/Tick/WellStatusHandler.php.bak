<?php

/**
 * WellStatusHandler - kontrola statusu odwiertu, personelu i mnoznikow.
 * WellStatusHandler - well status checks, staff resolution and multiplier calculation.
 *
 * Odpowiada za: / Responsible for:
 *   - montaz sprzetu (equipment_swap) / equipment installation (equipment_swap)
 *   - zmiane warstwy geologicznej / geological layer switch
 *   - kontrole personelu (staffCheck) / staff requirement check (staffCheck)
 *   - ustalanie operatora i technika / operator and technician resolution
 *   - obliczanie mnoznikow (warstwa, sprzet, spirala, perki) / multiplier calculation (layer, equipment, spiral, perks)
 *   - walidacje pracownika przez cache / staff validation via cache
 */
class WellStatusHandler
{
    private WellProductionSection $ctx;

    public function __construct(WellProductionSection $ctx)
    {
        $this->ctx = $ctx;
    }

    /**
     * Obsluguje montaz sprzetu (equipment_swap status).
     * Handles equipment swap (equipment_swap status).
     * Zwraca zaktualizowany $well lub null jesli tick ma byc pominiety.
     * Returns updated $well or null if the tick should be skipped.
     *
     * @param  array<string, mixed> $well
     * @return array<string, mixed>|null
     */
    public function handleEquipmentSwap(array $well, int $wellId, ?object $tsvc): ?array
    {
        $swapUntil = $well['equipment_swap_until'] ?? null;
        if ($swapUntil && strtotime($swapUntil) <= time()) {
            $prevStatus = !empty($well['equipment_swap_prev_status']) ? $well['equipment_swap_prev_status'] : 'active';
            if (!in_array($prevStatus, ['active','contaminated'])) $prevStatus = 'active';
            $this->ctx->db->prepare("
                UPDATE wells
                SET status = ?, equipment_swap_until = NULL, equipment_swap_prev_status = NULL
                WHERE id = ?
            ")->execute([$prevStatus, $wellId]);
            $well['status'] = $prevStatus;
            GameLog::info('tick', 'equipment_swap completed - well resumed', ['well_id' => $wellId, 'new_status' => $prevStatus]);
            $tsvc?->notify('task', $wellId, t('tick.notify.equipment_swap_done', ['id' => $wellId]));
            return $well;
        }
        return null; // jeszcze trwa / still in progress
    }

    /**
     * Obsluguje zakonczenie wiercenia warstwy geologicznej.
     * Handles geological layer switch completion.
     *
     * @param  array<string, mixed> $well
     * @return array<string, mixed>
     */
    public function handleGeoLayerSwitch(array $well, int $wellId): array
    {
        try {
            $this->ctx->geoSvc->processSwitchCompletion($wellId);
            $freshStatus = $this->ctx->db->prepare("SELECT status FROM wells WHERE id = ? LIMIT 1");
            $freshStatus->execute([$wellId]);
            $well['status'] = $freshStatus->fetchColumn() ?: $well['status'];
        } catch (Throwable $e) {
            GameLog::error('tick', 'geological layer drilling finish FAILED', $e, ['well_id' => $wellId]);
        }
        return $well;
    }

    /**
     * Kontrola personelu - pauzuje lub wznawia odwiert.
     * Staff check - pauses or resumes a well.
     *
     * @param  array<string, mixed> $well
     * @param  array<string, mixed> $staffCheck
     * @return array<string, mixed>
     */
    public function handleStaffCheck(array $well, int $wellId, int $playerId, array $staffCheck, ?object $tsvc): array
    {
        if (!$staffCheck['meets_minimum']) {
            if (in_array($well['status'], ['active','contaminated'])) {
                $reason = implode(',', $staffCheck['missing']);
                $this->ctx->db->prepare("UPDATE wells SET status = 'paused_staff', paused_staff_reason = ? WHERE id = ?")->execute([$reason, $wellId]);
                $well['status'] = 'paused_staff';
                GameLog::info('tick', 'well paused_staff (no staff)', ['well_id' => $wellId, 'player_id' => $playerId, 'missing' => $reason]);
                $tsvc?->notify('task', $wellId, t('tick.notify.well_paused_staff', ['id' => $wellId, 'missing' => implode(', ', $staffCheck['missing_labels'])]));
            }
        } elseif ($well['status'] === 'paused_staff') {
            $this->ctx->db->prepare("UPDATE wells SET status = 'active', paused_staff_reason = NULL WHERE id = ?")->execute([$wellId]);
            $well['status'] = 'active';
            GameLog::info('tick', 'well resumed (staff assigned)', ['well_id' => $wellId, 'player_id' => $playerId]);
            $tsvc?->notify('task', $wellId, t('tick.notify.well_resumed_staff', ['id' => $wellId]));
        }
        return $well;
    }

    /**
     * Ustala operator/technika i aktualizuje status odwiertu.
     * Resolves operator/technician and updates well status.
     * Zwraca: [operatorId, technicianId, opRow, techRow, opPerk, techPerk, opSkill].
     * Returns: [operatorId, technicianId, opRow, techRow, opPerk, techPerk, opSkill].
     *
     * @param  array<string, mixed>  $well
     * @return array<int, mixed>
     */
    public function resolveStaff(array &$well, int $wellId, int $playerId): array
    {
        $operatorId   = !empty($well['operator_id'])   ? (int)$well['operator_id']   : null;
        $technicianId = !empty($well['technician_id']) ? (int)$well['technician_id'] : null;

        $operatorId   = $this->validateStaff($operatorId,   $wellId, 'operator_id');
        $technicianId = $this->validateStaff($technicianId, $wellId, 'technician_id');

        if (!$operatorId && in_array($well['status'], ['active','contaminated','no_technician'])) {
            $this->ctx->db->prepare("UPDATE wells SET status = 'no_operator' WHERE id = ?")->execute([$wellId]);
            $well['status'] = 'no_operator';
            GameLog::info('tick', 'well - no operator', ['well_id' => $wellId]);
        } elseif ($operatorId && !$technicianId && in_array($well['status'], ['active','contaminated'])) {
            $this->ctx->db->prepare("UPDATE wells SET status = 'no_technician' WHERE id = ?")->execute([$wellId]);
            $well['status'] = 'no_technician';
            GameLog::info('tick', 'well - no technician', ['well_id' => $wellId]);
        } elseif ($operatorId && $technicianId && in_array($well['status'], ['no_operator','no_technician'])) {
            $this->ctx->db->prepare("UPDATE wells SET status = 'active' WHERE id = ?")->execute([$wellId]);
            $well['status'] = 'active';
            GameLog::info('tick', 'well restored to active (operator+technician)', ['well_id' => $wellId]);
        }

        $opSkill = 5; $opPerk = null; $techPerk = null;
        $opRow = null; $techRow = null;

        if ($operatorId) {
            // Uzyj preloadowanego cache ze staff_specializations / Use preloaded cache from staff_specializations
            $opRow = $this->ctx->staffCache[$operatorId] ?? null;
            if ($opRow === null) {
                // Fallback SELECT jesli brak w cache (nowo zatrudniony?) / Fallback SELECT if not in cache (hired this tick?)
                $opStmt = $this->ctx->db->prepare("SELECT ts.skill_level, ts.specialization, ss.prod_bonus, ss.wear_reduction, ss.incident_reduction, ss.spiral_reduction, ss.only_deep_layers FROM technical_staff ts LEFT JOIN staff_specializations ss ON ss.code = ts.specialization WHERE ts.id = ? LIMIT 1");
                $opStmt->execute([$operatorId]);
                $opRow = $opStmt->fetch() ?: null;
                if ($opRow) $this->ctx->staffCache[$operatorId] = $opRow;
            }
            if ($opRow) {
                $opSkill = (int)($opRow['skill_level'] ?? 5);
                if (!empty($opRow['specialization'])) $opPerk = $opRow;
            }
        }
        if ($technicianId) {
            $techRow = $this->ctx->staffCache[$technicianId] ?? null;
            if ($techRow === null) {
                $techStmt = $this->ctx->db->prepare("SELECT ts.skill_level, ts.specialization, ss.wear_reduction, ss.incident_reduction, ss.spiral_reduction, ss.repair_speed, ss.incident_return_reduction, ss.catastrophe_reduction FROM technical_staff ts LEFT JOIN staff_specializations ss ON ss.code = ts.specialization WHERE ts.id = ? LIMIT 1");
                $techStmt->execute([$technicianId]);
                $techRow = $techStmt->fetch() ?: null;
                if ($techRow) $this->ctx->staffCache[$technicianId] = $techRow;
            }
            if ($techRow && !empty($techRow['specialization'])) $techPerk = $techRow;
        }

        return [$operatorId, $technicianId, $opRow, $techRow, $opPerk, $techPerk, $opSkill];
    }

    /**
     * Oblicza wszystkie mnozniki (warstwa, sprzet, spirala, perki operatora/technika).
     * Calculates all multipliers (layer, equipment, spiral, operator/technician perks).
     * Zwraca tablice klucz->wartosc. / Returns key->value array.
     *
     * @param  array<string, mixed>      $well
     * @param  array<string, mixed>|null $opRow
     * @param  array<string, mixed>|null $techRow
     * @param  array<string, mixed>|null $opPerk
     * @param  array<string, mixed>|null $techPerk
     * @return array<string, mixed>
     */
    public function calcMultipliers(
        array  &$well,
        int    $wellId,
        ?int   $operatorId,
        ?int   $technicianId,
        ?array $opRow,
        ?array $techRow,
        ?array $opPerk,
        ?array $techPerk,
        int    $opSkill
    ): array {
        // Mnozniki warstwy geologicznej (raz per odwiert, z cache serwisu) / Geological layer multipliers (once per well, from service cache)
        $layerRichnessMult = 1.0;
        $layerWearMult     = 1.0;
        if ($this->ctx->geoSvc !== null) {
            try {
                $eqTierForLayer    = $well['equipment_tier'] ?? 'standard';
                $lMults            = $this->ctx->geoSvc->getLayerMultipliers($wellId, $eqTierForLayer);
                $layerRichnessMult = (float)($lMults['richness_mult'] ?? 1.0);
                $layerWearMult     = (float)($lMults['wear_mult']     ?? 1.0);
                // Zapisz active_layer_code do well (uzywane przez opProdPerkMult) / Store active_layer_code on well (used by opProdPerkMult)
                $well['active_layer_code'] = $lMults['code'] ?? ($well['active_layer_code'] ?? 'shallow');
            } catch (Throwable $e) {
                GameLog::error('tick', 'getLayerMultipliers FAILED', $e, ['well_id' => $wellId]);
            }
        }

        // Mnozniki sprzetu i spirali / Equipment and spiral multipliers
        $wearLevel      = (float)($well['wear_level'] ?? 0.0);
        $wearDegMult    = 1.0 + ($wearLevel * 0.5 / 100.0);
        $spiralBoost    = (float)($well['post_incident_risk_boost'] ?? 0.0);
        $spiralWearMult = 1.0 + ($spiralBoost / 150.0);
        $eqMults        = WellService::getEquipmentMultipliers($well['equipment_tier'] ?? 'standard', (int)($well['equipment_upgrade_level'] ?? 0));
        $spiralWearMult *= $eqMults['spiral'];

        $techDegradefMult = $technicianId ? 1.0 : 1.5;

        // Perki operatora i technika / Operator and technician perks
        $opEfficiencyMult = $operatorId ? (0.80 + ($opSkill - 1) * (0.30 / 9)) : 1.0;
        $opProdPerkMult   = 1.0;
        if ($opPerk && ($opPerk['specialization'] ?? '') === 'drilling_specialist') {
            $activeLayerCode = $well['active_layer_code'] ?? 'shallow';
            if (in_array($activeLayerCode, ['deep','ultra'])) $opProdPerkMult = 1.0 + (float)($opPerk['prod_bonus'] ?? 0.075);
        }

        $perkWearMult = 1.0;
        if ($opPerk   && (float)($opPerk['wear_reduction']   ?? 0) > 0) $perkWearMult *= (1.0 - (float)$opPerk['wear_reduction']);
        if ($techPerk && (float)($techPerk['wear_reduction'] ?? 0) > 0) $perkWearMult *= (1.0 - (float)$techPerk['wear_reduction']);

        $perkSpiralReduction = 0.0;
        if ($opPerk   && (float)($opPerk['spiral_reduction']   ?? 0) > 0) $perkSpiralReduction += (float)$opPerk['spiral_reduction'];
        if ($techPerk && (float)($techPerk['spiral_reduction'] ?? 0) > 0) $perkSpiralReduction += (float)$techPerk['spiral_reduction'];
        $spiralBoostEffective = $spiralBoost * max(0.0, 1.0 - $perkSpiralReduction);
        $spiralMultEffective  = 1.0 + ($spiralBoostEffective / 100.0);

        $perkCatastropheReduction = 0.0;
        if ($techPerk && (float)($techPerk['catastrophe_reduction'] ?? 0) > 0) $perkCatastropheReduction += (float)$techPerk['catastrophe_reduction'];
        $techSpecCatMult = max(0.3, 1.0 - $perkCatastropheReduction);

        return compact(
            'layerRichnessMult','layerWearMult',
            'wearDegMult','spiralWearMult','spiralMultEffective',
            'eqMults','techDegradefMult',
            'opEfficiencyMult','opProdPerkMult',
            'perkWearMult','techSpecCatMult'
        );
    }

    /**
     * Waliduje pracownika przez cache; usuwa martwe przypisanie jesli zwolniony.
     * Validates staff member via cache; removes dead assignment if fired.
     */
    public function validateStaff(?int $staffId, int $wellId, string $column): ?int
    {
        if (!$staffId) return null;
        try {
            if (isset($this->ctx->staffCache[$staffId])) {
                $chk = $this->ctx->staffCache[$staffId];
            } else {
                // Fallback - staff nie byl w cache (nowo zatrudniony w tym ticku?) / Fallback - not in cache (hired this tick?)
                $chkStmt = $this->ctx->db->prepare("SELECT status FROM technical_staff WHERE id = ? LIMIT 1");
                $chkStmt->execute([$staffId]);
                $chk = $chkStmt->fetch();
                if ($chk) $this->ctx->staffCache[$staffId] = $chk;
            }
            if (!$chk || $chk['status'] === 'fired') {
                $this->ctx->db->prepare("UPDATE wells SET {$column} = NULL WHERE id = ?")->execute([$wellId]);
                return null;
            }
        } catch (Throwable $e) {}
        return $staffId;
    }
}
