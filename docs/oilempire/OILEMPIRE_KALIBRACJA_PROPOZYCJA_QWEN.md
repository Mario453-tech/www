### K1
Kryzys: produkty ×0,29 i ropa ×0,29 daje spread koszyka 336×0,29 − 250×0,29 = 97,4 − 72,5 ≈ 24,9 ≈ 25 ✓, a cięcie −71% mieści się w zaokrągleniu §21 (−70%). Boom: 336×1,35 − 250×1,30 = 453,6 − 325 = 128,6 ∈ [120;150] ✓. Wojna celowo przekracza boom (171) — jest krótka (48–120 h) i rzadka (waga 4 vs 12).

```yaml
world_events:            # game_balance → modules.world_events.params
  price_basket_base: 336   # efektywny koszyk produktów (marża 86 = 336−250)
  combined_multiplier_clamp: [0.25, 2.20]   # iloczyn mnożników aktywnych wydarzeń
  types:
    boom:              {oil_mult: 1.30, npc_demand_mult: 1.25, product_mult: 1.35, duration_h: [96,168],  announce_h: 48, weight: 12, stack: global}
    pandemia:          {oil_mult: 0.75, npc_demand_mult: 0.55, product_mult: 0.70, duration_h: [120,240], announce_h: 24, weight: 8,  stack: global}
    embargo:           {oil_mult: 1.60, npc_demand_mult: 0.95, product_mult: 1.50, duration_h: [72,168],  announce_h: 24, weight: 6,  stack: global, extra: {sabotage_risk_mult: 1.20}}
    wojna_naftowa:     {oil_mult: 1.80, npc_demand_mult: 1.10, product_mult: 1.85, duration_h: [48,120],  announce_h: 24, weight: 4,  stack: global}
    kryzys_ekonomiczny:{oil_mult: 0.29, npc_demand_mult: 0.60, product_mult: 0.29, duration_h: [96,240],  announce_h: 24, weight: 8,  stack: global}
    frontier_open:     {oil_mult: 1.00, npc_demand_mult: 1.15, product_mult: 1.02, duration_h: [168,336], announce_h: 48, weight: 10, stack: global, extra: {unlock_region: true}}
  draw: {phase: 3, interval_h: 6, min_gap_between_starts_h: 24, max_global_active: 2, queue: FIFO}
```

### K2
Premia +20% (250→300 PLN/bbl; partia 1 000 bbl = +50 000 PLN) przy oczekiwanej karze rzędu 10% przychodu gotówkowego × ~2%/dobę szansy kontroli przy score 35 — kusi, ale nie dominuje. +2/transakcję: 15 transakcji do progu 30 (kontrola §2.3 = flaga szarej strefy — settled) vs legacy 3–8 (4–10 transakcji — za szybko przy decayu ~0). Decay −1/24 h z 50 pkt = 50 dni gry (1 200 h) — powolna resocjalizacja zgodna z zero-wipe; limit 5 transakcji/dobę zapobiega sprintowi +10/dobę.

```yaml
black_market:            # modules.market.params.black_market
  oil_premium_over_spot: 0.20        # sprzedaż: spot × 1,20, wypłata do PLN_CASH
  score_per_transaction: 2           # NOWE (odrzucone legacy 3–8)
  score_decay_per_24h: -1            # utrzymane §2.3; z 50 pkt → 0 w 50 dni
  score_max: 100
  audit_threshold: 30                # = próg flagi szarej strefy (settled)
  audit_chance_per_h_per_point: 0.0002
  daily_transaction_limit: 5         # max +10 score/dobę
  equipment_price_of_new: [0.30, 0.40]  # istniejące §12.4
```

### K3
+2 za transzę standardową: 50→75 (próg premium) w 12–13 dostaw ✓ (cel 10–15; duże transze +3 skracają do 9). Spadek symetryczny z istniejącymi kar: 4 niewykonane transze (−6) z 50 → 26, piąta → 20 < 25 = blokada rynku.

```yaml
b2b_reputation:          # modules.b2b.params.reputation
  settled_delta_by_tranche_value:    # wartość transzy brutto
    - {max_pln: 500_000,   delta: 1}
    - {max_pln: 5_000_000, delta: 2}
    - {max_pln: null,      delta: 3}
  refunded_seller_fault: -8          # istniejące
  missed_or_penalty_cancel: -6       # istniejące
  cartel_sanction: -15               # istniejące
  premium_contracts_threshold: 75
  market_lock_threshold: 25          # poniżej: brak nowych ofert systemowych i B2B
  cap: [0, 100]
```

### K4
Start: 0,35×70 + 0,35×40 + 0,20×50 (kredyt startowy 2 mln / aktywa 4 mln) + 0 = 48,5 → tier 40–59 (max 2 mln) ✓ nawet bez kredytu (solvency 100) daje 58,5 < 60. Czysta historia 12 mies. + credibility 90: 31,5 + 35 + 18 + 10 + bonus 15 = 109,5 → tier 100–115 ✓. Bankructwo = twardy cap 40 (poniżej progu 40 = brak zdolności nawet przy odbudowanej wiarygodności).

```yaml
credit_score:            # modules.finance.params.credit_score
  formula: clamp(0, 115, round(0.35*credibility + 0.35*history + 0.20*solvency + 0.10*tenure + clean_bonus))
  components:
    credibility: company_credibility (0–100)
    history: pct_rat terminowych 12m × 100; brak historii kredytowej = 40
    solvency: clamp(0, 100, (1 - zobowiazania_laczne/aktywa) × 100)
    tenure: clamp(0, 100, wiek_konta_dni / 365 × 100)
    clean_bonus: +1.25/miesiąc bez `late`, cap +15 (nadwyżka >100 = bonus §17.1)
  hard_caps:
    bankruptcy_in_history: 40
    late_active: -10 do wyniku (przed clamp)
  tiers: istniejąca tabela §17.1 (40/60/80/100)
```

### K5
Pełna ścieżka L1→L5 = 3,07 mln ≈ 38% kosztu odwiertu deep (5 mln) i 26% offshore (12 mln) — bezpieczeństwo droższe od rury, tańsze od odwiertu; L5 daje mnożnik ×(1−0,6)=0,4 w §17.4. Spadek o 1 poziom po katastrofie (blowout) z odbudową za 50% ceny — katastrofa musi boleć dłużej niż sama naprawa (czas naprawy ×1,4 przy strajku już istnieje).

```yaml
bhp_levels:              # modules.technical.params.bhp
  start_level: 1
  upgrades:
    - {to: 2, cost_pln: 120_000,   time_h: 8}
    - {to: 3, cost_pln: 350_000,   time_h: 24}
    - {to: 4, cost_pln: 800_000,   time_h: 48}
    - {to: 5, cost_pln: 1_800_000, time_h: 72}
  requirement: obsadzony Dyrektor Techniczny (BoardAccess::require('technical'))
  risk_reduction_per_level: 0.12     # istniejące §17.4
  catastrophe_downgrade: {classes: [catastrophe], levels_lost: 1, rebuild_cost_factor: 0.5}
  fts_code: TECH_BHP_UPGRADE
```

### K6
Zużycie 0,4%/dobę (standard) → 100→70 (ostrzeżenie, mnożnik ryzyka 1,30) w 75 dni; budżet low ×1,8 skraca to do ~42 dni — cięcia BHP muszą być widoczne w horyzoncie miesiąca, nie roku. Audyt 25 tys./+35 PI z cooldownem 24 h: z 40 (krytyczne, ×1,60) do 100 potrzeba 2 audytów (50 tys., ~2 dni) lub 1 kalibracji pełnej (60 tys.) — tanio vs kara katastrofy (−15 wiarygodności + naprawy).

```yaml
procedure_integrity:     # modules.technical.params.procedure_integrity
  start: 100
  decay_pct_per_day:
    base: 0.4
    budget_multiplier: {low: 1.8, standard: 1.0, high: 0.6}
    per_overloaded_asset_per_day: 0.15      # turbo hubów (§4.1 przeciążenie)
  incident_hit: {minor: -2, major: -5, catastrophe: -15}   # natychmiast
  thresholds: {warning: 70, critical: 40}   # UI + alert §27.2 przy 40
  audit:      {cost_pln: 25_000, time_h: 6,  restore: 35,  cooldown_h: 24}
  calibration:{cost_pln: 60_000, time_h: 12, restore: 100, cooldown_h: 72}   # do pełna
  risk_multiplier: "(2 - PI/100)"           # istniejące §17.4
  fts_code: TECH_PROCEDURE_AUDIT
```

### K8
Koszty budżetów: 15 pracowników na standard = (260+180)×15 = 6 600 PLN/dobę ≈ 275 PLN/h ≈ 5% marży klasy I (5,2 tys. PLN/h) — z pensjami (K9) ~13% łącznie; przy kryzysie (spread 25 → netto ujemne) stają się głównym obciążeniem stałym. Zmiana budżetu z cooldownem 24 h, bo §4.3 i tak daje asymetryczny drift morale (−12/+6 pkt/dobę) — brak cooldownu pozwalałby „migotać" budżetem pod strike.

```yaml
dept_budgets:            # modules.finance.params.budgets (per pracownik/dobę, PLN)
  bhp:  {low: 120, standard: 260, high: 460}   # ryzyko ×1.5/×1.0/×0.7 (istniejące)
  hr:   {low: 80,  standard: 180, high: 320}   # morale-component 40/70/100 pkt (§4.3)
  hr_recruitment_time_mult: {low: 1.5, standard: 1.0, high: 0.75}
  technical_wear_mult:      {low: 1.6, standard: 1.0, high: 0.7}   # §10.2 „drastycznie przyspiesza zużycie"
  logistics_delay_chance:   {low: 0.10, standard: 0.02, high: 0.005}  # per transport/dobę
  change_cooldown_h: 24
  cut_morale_penalty: -6   # istniejące §4.3; podwyższenie budżetu: bez kary
  fts_codes: [BUDGET_BHP_DAILY, BUDGET_HR_DAILY, BUDGET_TECH_DAILY, BUDGET_LOG_DAILY]
```

### K7
Kotwice: serwis huba 1 800 PLN/h → 6 h pracy ≈ 10,8 tys., więc materiały 15 tys. są tego samego rzędu; rura zużywa się 0,035–0,070%/h → żywotność odcinka 1 400–2 900 h, a wymiana 110 tys. = 44% nowej rury (250 tys. z FTUE). Firma zewnętrzna 2,5×/1,4× pozostaje realną alternatywą przy strajku (naprawy ×1,4 czasu już w §17.4).

```yaml
tts_tasks:               # modules.technical.params.tts (koszt/czas WEWNĘTRZNY; zewnętrzna ×2.5/×1.4)
  - {key: pipeline_inspection,  scope: odcinek ≤5 km, time_h: 4,  materials_pln: 8_000,   effect: wear_level −5}
  - {key: wellhead_repair,      scope: odwiert,       time_h: 8,  materials_pln: 35_000,  effect: condition +25 po awarii}
  - {key: hub_buffer_service,   scope: hub,           time_h: 6,  materials_pln: 15_000,  effect: przywraca bufor, −przeciążenie}
  - {key: tanker_overhaul,      scope: cysterna,      time_h: 3,  materials_pln: 6_000,   effect: road_loss −20% na 30 dni}
  - {key: bhp_calibration,      scope: firma,         time_h: 6,  materials_pln: 25_000,  effect: = audyt PI (K6)}
  - {key: pipe_section_replace, scope: odcinek,       time_h: 12, materials_pln: 110_000, effect: wear_level → 0, condition 100}
  fts_code: TECH_TTS_MATERIALS
```

### K9
Obsada klasy I (5 osób: 2×operator 15 + hub 13 + inżynier 19 + BHP 18 = 80 tys./mies.) ≈ 137 PLN/h ≈ 2,6% marży 5,2 tys. PLN/h; pełne 10–15 osób ≈ 280 tys./mies. ≈ 8% z budżetami działowymi — sensowne, nie zabójcze, a przy kryzysie (produkt ×0,29) pensje stają się gwoździem do §17.3. Skala ≈ realia PL 2025 ×1,8 (operator realnie ~8,5 tys.) — spójna z gospodarką gry, gdzie odwiert 0,8 mln zwraca się w ~6 dni.

```yaml
min_market_salary:       # modules.hr.params.salaries (PLN/miesiąc)
  zarzad: {ceo: 42_000, cfo: 38_000, cto: 38_000, hr_director: 34_000, logistics_director: 34_000}
  operator_odwiertu: 15_000
  operator_huba: 13_000
  inzynier_rurociagow: 19_000
  operator_instalacji_rafineryjnej: 17_000
  inzynier_chemik: 26_000
  inspektor_bhp: 18_000
  dyspozytor_logistyk: 14_000
  analityk: 20_000
expected_salary:
  formula: min_market_salary × rand(1.0, 1.6)   # per kandydat
  rng_seed: HMAC(candidate_id:balance_version)  # wynik zapisany raz, replayowalny
  personality: {loyal: [1.0,1.2], standard: [1.1,1.4], ambitious: [1.3,1.6]}
```

### T1
Jedna kanoniczna kolejność eliminuje zakleszczenia, bo HTTP i sweeper blokują wiersze w tej samej sekwencji rosnącej; retry z backoffem jest bezpieczny dzięki kluczom idempotencji z T2 (ponowienie nie księguje dwa razy).

```markdown
#### §23.5a Globalny porządek blokad (kanoniczny)
Kolejność `SELECT ... FOR UPDATE` — ZAWSZE rosnąco wg (type_rank, entity_id):
10 company → 20 accounts (portfele PLN_CASH/PLN_BANK/GBBL/SYS_*, wg account_id) →
30 obligations+loans → 40 wells → 50 pipelines → 60 hubs → 70 storages →
80 trips → 90 escrow_tx (B2B) → 100 market_offers → 110 contracts → 120 refineries →
130 global market rows (tylko sweeper faza 3).
Reguły:
- Żądanie HTTP i sweeper stosują identyczną kolejność; operacje wsadowe sortują encje PRZED pierwszą blokadą.
- Sweeper przetwarza graczy rosnąco wg companies.id.
- `innodb_lock_wait_timeout` = 10 s; twardy cap czasu transakcji 30 s w SettlementService.
- MySQL 1213/1205: max 3 ponowienia, backoff 50 ms × 2^n + jitter 0–50 ms; ponowienie = cała transakcja od nowa, idempotencja z `fts_idempotency_keys` (§29.4a).
- Każdy 1213 → wpis `deadlock_log` (obie strony, zapytania) + przegląd tygodniowy (Filament).
- Naruszenie kolejności w code review = bloker PR.
```

### T2
Partycjonowana `financial_transaction_service` nie może mieć globalnego UNIQUE z `created_at` w kluczu, więc deduplikacja żyje w osobnej małej tabeli niepartycjonowanej z punktowym lookupem po UNIQUE; 1 000 graczy × ~50 zapisów/dobę = 1,5 mln wierszy/30 dni ≈ 250 MB — bez wpływu na partycje FTS.

```markdown
#### §29.4a Tabela idempotencji FTS (`fts_idempotency_keys`)
Niepartycjonowana, InnoDB:
- `id` BIGINT UNSIGNED AI PK
- `idem_key` VARBINARY(16) — BLAKE2b-128 z `reason_code:entity_id:due_slot:request_uuid`
- `company_id` BIGINT UNSIGNED, `fts_id` BIGINT UNSIGNED NULL
- `status` VARCHAR(16): pending|committed|failed (BackedEnum PHP 8.3)
- `created_at` DATETIME(3), `expires_at` DATETIME(3); UNIQUE(`idem_key`), INDEX(`expires_at`)
Kolejność w transakcji (CAS):
1. INSERT status='pending', expires_at=NOW()+24h — PRZED księgowaniem FTS.
2. Duplikat 1062 → SELECT: 'committed' → zwróć zapisany `fts_id` (odpowiedź idempotentna, brak ponownego księgowania); 'pending' <60 s → HTTP 409 retry-later; 'pending' >60 s lub 'failed' → przejęcie: `UPDATE ... SET status='pending', created_at=NOW(3) WHERE idem_key=? AND (status='failed' OR created_at < NOW()-INTERVAL 60 SECOND)` — tylko gdy affected_rows=1.
3. INSERT FTS + `UPDATE ... SET status='committed', fts_id=?` w TEJ SAMEJ transakcji.
Kolizja równoległa: przegrywa strona bez affected_rows — jej transakcja wycofuje się i ponawia (T1 backoff).
Czyszczenie: dzienny sweeper `DELETE WHERE expires_at<NOW() AND status='committed'` (retencja 30 dni; 'failed' — 24 h). Klucze sweepera (seed HMAC(entity:due_slot)) naturalnie deduplikują rozliczenie po awarii.
```

### T3
Generator w fazie 4 sweepa z deterministycznym seedem domyka obieg: oferta systemowa (NPC-katalog, bez kont NPC — spójne z EPC), a odbiór transzy przez fizykę §11A sprawia, że kontrakt nie jest darmowym pieniądzem — wymaga łańcucha logistycznego. Podtypy aviation/RARS dziedziczą tryby cenowe z mnożnikiem ×1,20 i wymogiem jakości ≥90 (+20% wolumenu premium), bez osobnej mechaniki.

```markdown
#### §13.1a Generator kontraktów systemowych NPC
- Tworzy: sweeper faza 4 (Kontrakty); RNG seed HMAC(`contract_offer:{region_id}:{due_slot}`), wynik zapisany raz.
- Częstotliwość: każde sweep uzupełnia pulę do celu; cel = 3 + floor(aktywni_gracze_regionu/25), max 8/region; odstęp ≥24 h między ofertami tej samej firmy NPC.
- NPC-odbiorcy: katalog fikcyjnych firm (6–10/region: rafinerie, petrochemia, sieci stacji) — oferty systemowe, bez kont/bankructw NPC.
- Czas życia oferty: 24–72 h (fixed); wygaśnięcie bez podpisu → pula, losowanie parametrów od nowa (brak kumulacji).
- Tiery wg `contract_reputation`: T1 ≥0; T2 ≥40; T3 ≥65; premium ≥75 (spójne z K3).
- Widełki per tryb cenowy:
  fixed: transza 500–5 000 bbl, 3–10 transz, cena = baza × 1.02–1.10;
  market_multiplier: transza 2 000–20 000 bbl, mnożnik 0.94–1.06 × spot;
  market_plus_bonus: transza 1 000–10 000 bbl, bonus +4–15 PLN/bbl ponad spot.
- Kaucja 10% wartości (istniejące): FTS `CONTRACT_DEPOSIT`; zwrot po ostatniej transzy `CONTRACT_DEPOSIT_RETURN`; przepadek po 3 missed z rzędu `CONTRACT_DEPOSIT_FORFEIT`.
- Rozliczenie transzy: okno 24 h; NPC odbiera z magazynu głównego/huba — dostępność fizyczna wg §11A (gracz zapewnia połączenie lub cysterny); brak wolumenu = missed (kara 5% + `contract_reputation` −6, istniejące). Wpływ transzy: FTS `CONTRACT_TRANCHE_INCOME` (PLN_BANK, podlega Fazie 1 §17.2).
- Podtypy: `aviation` (§31.5): tylko jet, jakość ≥90, cena trybu ×1.20, próg reputacji +1 tier; `rars`: jakość ≥90, premia wolumenowa +20%, próg premium 75.
```
