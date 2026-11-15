### K1 — Wydarzenia światowe (§25) — mnożniki

Uzasadnienie: Przy bazie ropa 250 + produkty ~336 PLN/bbl (marża 86), kryzys z mnożnikiem produktów 0,29 daje koszyk ~97,4 PLN/bbl. Aby marża spadła do ~25 PLN/bbl, ropa musi zejść do ~72 PLN/bbl → mnożnik 0,29 (spójne z §21: "tnie ceny ropy o ~70%"). Boom z ropą ×1,25 i produktami ×1,45 daje marżę ~145 PLN/bbl (środek widełek 120–150).

```yaml
world_events:
  boom:
    oil_multiplier: 1.25
    npc_demand_multiplier: 1.50
    product_multiplier: 1.45
    duration_h: [48, 96]
    announce_h: 24
    weight: 8
  pandemia:
    oil_multiplier: 0.60
    npc_demand_multiplier: 0.40
    product_multiplier: 0.70
    duration_h: [72, 168]
    announce_h: 48
    weight: 10
  embargo:
    oil_multiplier: 0.70           # regionalny
    npc_demand_multiplier: 0.30
    product_multiplier: 0.80
    duration_h: [96, 240]
    announce_h: 24
    weight: 6
  wojna_naftowa:
    oil_multiplier: 0.50
    npc_demand_multiplier: 2.00    # panika
    product_multiplier: 1.30
    duration_h: [48, 120]
    announce_h: 12
    weight: 5
  kryzys_ekonomiczny:
    oil_multiplier: 0.29           # §21: ~70% cięcia
    npc_demand_multiplier: 0.50
    product_multiplier: 0.29       # kotwica specyfikacji
    duration_h: [96, 240]
    announce_h: 48
    weight: 12
  frontier_open:
    oil_multiplier: 1.00
    npc_demand_multiplier: 1.20    # nowe rynki zbytu
    product_multiplier: 1.05
    duration_h: [168, 720]         # długotrwałe
    announce_h: 72
    weight: 3
  stack_policy: fifo
  max_active_global: 2
  regional_unlimited: true
```

### K2 — Czarny rynek ropy (§12.4) — premia i score

Uzasadnienie: Premia +18% nad spot kompensuje ryzyko: przy transakcji 1000 bbl × 250 PLN = 250 000 PLN, kara kontroli to 10% niezadeklarowanych + blokada 50% banku + −12 wiarygodności — gracz realnie ryzykuje ~75 000 PLN potencjalnych strat. Score +2/transakcję (nowe, nie legacy 3–8) daje przewidywalne tempo: próg kontroli 30 osiągany po 15 transakcjach (15×24h decay = balans przy 1 transakcji/dobę), próg 30 to ~miesiąc codziennego handlu. Decay −1/24h (nowe) zamiast legacy ~−6/24h (0,5/tick×12/h) — wolniejszy decay nagradza ostrożnych, ale z 50 pkt do zera schodzi 50 dni, więc plama zostaje na długo. Bez limitu transakcji/dobę — score + decay same ograniczają.

```yaml
black_market:
  price_premium_pct: 18            # nad spot
  score_per_trade: 2               # nowe +2 (legacy 3-8 odrzucone)
  score_decay_per_24h: 1           # nowe -1/24h (legacy szybszy odrzucony)
  control_threshold: 30            # score > 30 → ryzyko kontroli
  control_chance_pct_per_h: 0.02   # × (score - 30)
  control_penalty:
    bank_freeze_pct: 50
    freeze_duration_h: [24, 72]
    fine_pct_undeclared: 10
    credibility_delta: -12
  daily_trade_limit: null          # brak — score samoregulujący
```

### K3 — Reputacja B2B — delta za settled

Uzasadnienie: Start z `contract_reputation = 50`. Aby osiągnąć próg kontraktów premium (75) w 12 transzach: (75−50)/12 = ~+2,08/transzę → zaokrąglone +2. Skalowanie rozmiarem: +1 za transzę <500 bbl, +2 za 500–2000, +3 za >2000 — średnia z mieszanki daje ~2,1/transzę. Próg premium 75 odblokowuje kontrakty z kaucją 5% (zamiast 10%) i `market_multiplier ≥ 1.05`. Poniżej 25: tylko spot, bez kontraktów.

```yaml
contract_reputation:
  start_value: 50
  deltas:
    settled:
      base: 1
      tier_volume:
        - { min_bbl: 0,    max_bbl: 499,  delta: 1 }
        - { min_bbl: 500,  max_bbl: 1999, delta: 2 }
        - { min_bbl: 2000, max_bbl: null, delta: 3 }
    missed: -6
    refunded_player_fault: -8
    cartel_sanction: -15
  thresholds:
    premium_contracts: 75          # kaucja 5%, multiplier ≥1.05
    standard_only: 25              # poniżej — tylko spot
    blacklist: 10                  # zero kontraktów przez 14 dni
```

### K4 — `credit_score` (0–115) — wzór

Uzasadnienie: Start: credibility=70, bez historii spłat, dług=0, staż=0 → 70×0,5 + 50×0,25 + 100×0,2 + 0×0,05 = 35+12,5+20+0 = 67,5. To mieści się w Tier 40–59 (max 2 mln) z pakietem startowym 4 mln — bank daje max 50% kapitału własnego, bezpiecznie. Credibility 90 + 100% spłat + staż 180 dni → 90×0,5+100×0,25+100×0,2+100×0,05 = 45+25+20+5 = 95 → Tier 80–99. Historia bankructwa → cap 40 (Tier 0–39, brak zdolności po wyjściu z postępowania).

```yaml
credit_score:
  range: [0, 115]
  formula:
    company_credibility:     { weight: 0.50, clamp: [0, 100] }
    repayment_history_pct:   { weight: 0.25, clamp: [0, 100], window_days: 360 }
    debt_to_assets_ratio:    { weight: 0.20, clamp: [0, 100], invert: true }
    account_age_days:        { weight: 0.05, clamp: [0, 100], cap_days: 365 }
  caps:
    bankruptcy_history: 40          # po restrukturyzacji
    active_bailiff: 0               # w trakcie egzekucji
```

### K5 — BHP (§11.1) — koszty i czasy poziomów 1–5

Uzasadnienie: Koszty skalowane logarytmicznie między 0,8 mln (wiercenie shallow) a 12 mln (offshore). Poziom 1 to bazowa certyfikacja (jak wiercenie shallow), poziom 5 to full HSE jak offshore. Czas rośnie od 24h (L1 — audyt papierowy) do 168h (L5 — pełna modernizacja infrastruktury bezpieczeństwa). Wymóg: dyrektor techniczny od L3. Po katastrofie `catastrophe`: spadek o 1 poziom + 50% kosztu odtworzenia.

```yaml
bhp_levels:
  1:
    upgrade_cost_pln: 800_000
    upgrade_duration_h: 24
    requirement: null               # każdy
    risk_reduction: 0.12
  2:
    upgrade_cost_pln: 2_000_000
    upgrade_duration_h: 48
    requirement: null
    risk_reduction: 0.24
  3:
    upgrade_cost_pln: 5_000_000
    upgrade_duration_h: 72
    requirement: director_technical
    risk_reduction: 0.36
  4:
    upgrade_cost_pln: 8_500_000
    upgrade_duration_h: 120
    requirement: director_technical
    risk_reduction: 0.48
  5:
    upgrade_cost_pln: 12_000_000
    upgrade_duration_h: 168
    requirement: director_technical + bhp_inspector
    risk_reduction: 0.60
  catastrophe_penalty:
    level_drop: -1
    restore_cost_pct: 50            # % kosztu utraconego poziomu
```

### K6 — `procedure_integrity` (§11.2) — pełna mechanika

Uzasadnienie: Start od 100% (świeże procedury). Zużycie 2%/dobę przy budżecie high (dyscyplina), 5% przy standard, 8% przy low (rozluźnienie). Przy turbo na hubach: +50% tempa zużycia (stres organizacyjny). Próg ostrzeżenia 40% — pomarańczowy alert; poniżej 20% — czerwony, mnożnik ryzyka §17.4 rośnie do 1,8. Audyt kosztuje 50 000 PLN, trwa 48h, przywraca do 100%. Cooldown 72h zapobiega spamowaniu. Koszt ~10% rocznego budżetu BHP high per audyt — balansuje.

```yaml
procedure_integrity:
  start_value: 100
  max_value: 100
  decay_per_24h:
    budget_high: 2.0
    budget_standard: 5.0
    budget_low: 8.0
  turbo_multiplier: 1.5             # huby na turbo
  thresholds:
    warning: 40                     # alert pomarańczowy
    critical: 20                    # alert czerwony, risk ×1.8 bazowo
  audit:
    cost_pln: 50_000
    duration_h: 48
    restore_to: 100
    cooldown_h: 72
    requirement: director_technical + bhp_inspector
```

### K7 — Zadania TTS (§11.3) — katalog

Uzasadnienie: Czasy i koszty skalowane tak, by zewnętrzna firma (×2,5 kosztu, ×1,4 czasu) była odczuwalną karą. Inspekcja rurociągu tania i szybka (profilaktyka), naprawa głowicy droga (część krytyczna). Koszty materiałów ~15–25% wartości nowego odcinka. Przy zużyciu rur 0,035–0,070%/h, średni odcinek (50k PLN) generuje ~17,5–35 PLN/h kosztów wymiany → rocznie ~150–300 tys. PLN na odcinek. 6 zadań pokrywa główne aktywa gry.

```yaml
tts_tasks:
  pipeline_inspection:
    duration_h: 2
    material_cost_pln: 1_200
    requirement: engineer_pipeline
    target: pipeline_segment
  wellhead_repair:
    duration_h: 8
    material_cost_pln: 18_000
    requirement: engineer_pipeline
    target: well_after_failure
  hub_buffer_maintenance:
    duration_h: 4
    material_cost_pln: 4_500
    requirement: hub_operator
    target: hub_storage_buffer
  tanker_inspection:
    duration_h: 3
    material_cost_pln: 2_800
    requirement: logistician
    target: tanker_unit
  bhp_calibration:
    duration_h: 6
    material_cost_pln: 8_000
    requirement: bhp_inspector
    target: bhp_system
    effect: "procedure_integrity audit (§11.2)"
  pipe_segment_replacement:
    duration_h: 12
    material_cost_pln: 35_000       # ~70% nowego odcinka
    requirement: engineer_pipeline
    target: worn_pipe_segment
  external_contractor:
    cost_multiplier: 2.5
    time_multiplier: 1.4
```

### K8 — Budżety HR i BHP (§10.2) — efekty i koszt

Uzasadnienie: BHP low oszczędza ~30% kosztów ale podnosi ryzyko ×1,5. Koszt BHP per pracownik: ~15% mediany pensji technicznej (~12 000 PLN) = 1800 PLN/miesiąc standard → 60 PLN/dzień. HR low tnie koszty rekrutacji ale czas ×2,0 — przy średnim koszcie rekrutacji 15 000 PLN (ogłoszenia + czas dyrektora), oszczędność 3000–4500 PLN kosztem opóźnienia obsady o 48h (utrata ~250 tys. PLN przychodu z nieobsadzonego odwiertu) — bilans nieopłacalny, zgodnie z intencją kary za cięcia. Cooldown 24h na zmianę budżetu zapobiega exploitom dziennym.

```yaml
department_budgets:
  bhp:
    low:
      risk_multiplier: 1.5
      cost_per_employee_per_day_pln: 30
      procedure_integrity_decay: 8.0
    standard:
      risk_multiplier: 1.0
      cost_per_employee_per_day_pln: 60
      procedure_integrity_decay: 5.0
    high:
      risk_multiplier: 0.7
      cost_per_employee_per_day_pln: 120
      procedure_integrity_decay: 2.0
  hr:
    low:
      recruitment_time_multiplier: 2.0
      recruitment_cost_multiplier: 0.7
      morale_penalty_on_cut: -6
      salary_satisfaction_floor: 60
    standard:
      recruitment_time_multiplier: 1.0
      recruitment_cost_multiplier: 1.0
      morale_penalty_on_cut: -3
      salary_satisfaction_floor: 70
    high:
      recruitment_time_multiplier: 0.6
      recruitment_cost_multiplier: 1.4
      morale_penalty_on_cut: 0
      salary_satisfaction_floor: 85
  budget_change_cooldown_h: 24
```

### K9 — `min_market_salary` per stanowisko (PLN/miesiąc)

Uzasadnienie: Przy 12 pracownikach i średniej pensji ~14 000 PLN, miesięczny koszt ~168 000 PLN → ~233 PLN/h. Marża przy 20 bbl/h × 86 PLN = 1720 PLN/h — pensje to ~13,5% marży, akceptowalne. Operatorzy (10–12k) to realia PL 2025 dla techników wydobycia (+30% za offshore). Dyrektorzy 20–32k — stawki managerskie w sektorze oil&gas. `expected_salary` = `min_market × random(1.0, 1.6)` — kandydat z high ambition żąda 60% powyżej minimum.

```yaml
min_market_salary_pln_monthly:
  director_finance:      28_000
  director_technical:    30_000
  director_hr:           24_000
  director_logistics:    26_000
  director_commercial:   32_000
  well_operator:         11_000
  well_operator_offshore: 16_000
  hub_operator:          10_000
  pipeline_engineer:     13_000
  refinery_operator:     14_000
  chemical_engineer:     15_000
  bhp_inspector:         12_000
  logistician:           10_000
  dispatcher:             9_000
  analyst:               11_000
expected_salary_multiplier: [1.0, 1.6]  # losowo per kandydat
```

### T1 — Globalny porządek blokad (§23.5)

Uzasadnienie: Kolejność rosnąca po stałych `lock_order` (integer) zapobiega deadlockom między żądaniami HTTP a sweeperem — oba stosują ten sam porządek `SELECT ... FOR UPDATE`. Zasoby zależne (escrow → contract, market_offer → storage) występują później. Timeout 10s + 3 retry z exponential backoff (100/200/400ms) pokrywają 99,9% kolizji przy 1000 graczy.

```php
// §23.5 Globalny porządek blokad — kanoniczna kolejność rosnąca
return [
    'company'           => 10,   // company_credibility, settings
    'player_accounts'   => 20,   // PLN_CASH, PLN_BANK (SELECT FOR UPDATE)
    'wells'             => 30,   // oil_wells: condition, production
    'pipelines'         => 40,   // pipeline_segments
    'hubs'              => 50,   // storage_hubs: buffers
    'refineries'        => 60,   // refineries: mode, stock
    'storages'          => 70,   // main_storage: available_bbl
    'escrow_tx'         => 80,   // locked_in_escrow_bbl
    'market_offers'     => 90,   // reserved_bbl, limit_price
    'contracts'         => 100,  // active B2B contracts
    'trips'             => 110,  // active transport trips
    'loans'             => 120,  // loan repayments
    'bailiff'           => 130,  // bailiff_proceedings
    'fts_log'           => 140,  // financial_transaction_service (INSERT only, no lock)
];
// Reguły: zawsze rosnąco per lock_order; timeout 10s; max 3 retry z backoff 100/200/400ms.
// Deadlock detection: MySQL ER_LOCK_DEADLOCK → rollback + retry z jitterem.
```

### T2 — Tabela kluczy idempotencji dla FTS partycjonowanego (§29.4)

Uzasadnienie: Partycjonowanie RANGE po `created_at` wyklucza globalny UNIQUE na `idempotency_key` (MySQL wymaga kolumny partycjonującej w każdym UNIQUE). Osobna, niepartycjonowana tabela `fts_idempotency_keys` rozwiązuje to przez atomowe INSERT przed właściwą transakcją. Przy 1000 graczy i 50 transakcjach/dzień/gracza: 50 000 wpisów/dzień × 365 = 18,25 mln/rok — retencja 90 dni (4,5 mln wierszy, ~300 MB) jest akceptowalna. Czyszczenie cronem co 24h usuwa przeterminowane.

```sql
-- §29.4 Tabela kluczy idempotencji (niepartycjonowana)
CREATE TABLE fts_idempotency_keys (
    idempotency_key CHAR(36) NOT NULL,       -- UUID v4
    fts_id BIGINT UNSIGNED NULL,             -- powiązanie po sukcesie
    created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    status ENUM('pending','committed','rolled_back') NOT NULL DEFAULT 'pending',
    PRIMARY KEY (idempotency_key),
    INDEX idx_created_at (created_at)         -- dla czyszczenia
) ENGINE=InnoDB;

-- Kolejność w transakcji FTS:
-- 1. INSERT INTO fts_idempotency_keys (key, status='pending') — UNIQUE gwarantuje CAS
-- 2. Jeśli DUPLICATE KEY → SELECT status; committed=zwróć istniejący fts_id, pending=czekaj/powtórz
-- 3. INSERT INTO financial_transaction_service (...) (partycjonowana)
-- 4. UPDATE fts_idempotency_keys SET status='committed', fts_id=<new_id>

-- Czyszczenie (cron co 24h):
-- DELETE FROM fts_idempotency_keys WHERE created_at < NOW() - INTERVAL 90 DAY;
```

### T3 — Generator kontraktów systemowych NPC (§13.1)

Uzasadnienie: Sweeper w fazie 4 (po rozliczeniu rynku) generuje oferty zależne od popytu NPC — gdy `npc_demand > supply`, rafinerie szukają dostaw. 3–5 ofert na region (6 regionów × 4 oferty = 24 oferty max) przy 1000 graczach daje ~2,4% szansy na kontrakt na gracza — rywalizacja, nie gwarancja. Widełki `market_multiplier` 0,92–1,08 dają premię za stabilność dostaw (fixed) lub elastyczność (multiplier). Próg reputacji 75 dla `market_plus_bonus` spójny z K3. RARS (+20% ceny, ≥90 jakości, §31.5) jako podtyp z `contract_subtype: rars`.

```yaml
contract_generator:
  trigger: sweeper_faza_4
  frequency: co_sweep                 # co ~15 min przy 4 sweepach/h
  offers_per_region: [3, 5]
  offer_lifetime_h: 48                # po tym czasie wygasa bez chętnych
  volume_bbl_range: [500, 50_000]
  tiers:
    standard:                         # contract_reputation ≥ 25
      modes: [fixed, market_multiplier]
      fixed_addon_pct: [-5, 5]       # ±5% od spot
      market_multiplier_range: [0.92, 1.08]
      security_deposit_pct: 10
    premium:                          # contract_reputation ≥ 75
      modes: [fixed, market_multiplier, market_plus_bonus]
      fixed_addon_pct: [2, 12]
      market_multiplier_range: [0.98, 1.15]
      market_plus_bonus_pln: [5, 25]  # PLN/bbl ponad spot
      security_deposit_pct: 5
  subtypes:
    rars:                             # §31.5 RARS
      price_bonus_pct: 20
      quality_min: 90
      requires: refinery_jet_mode
    aviation:                         # §31.5 kontrakty lotnicze
      product: jet
      quality_min: 95
  no_bidders_behavior: expire_and_relist  # nowa oferta w następnym sweepie
  delivery_mechanism: from_hub_or_main_storage  # §11A fizyka transportu
  fts_codes:
    contract_security_deposit: { debit: PLN_BANK, credit: CONTRACT_ESCROW }
    contract_transfer: { debit: CONTRACT_ESCROW, credit: PLN_BANK }
    contract_penalty: { debit: PLN_BANK, credit: SYS_TREASURY }
    delivery_settlement: { debit: NPC_COUNTERPARTY, credit: PLN_BANK }
```
