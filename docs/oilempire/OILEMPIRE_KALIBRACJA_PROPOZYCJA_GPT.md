### K1

Przy koszyku produktów 336 PLN/bbl mnożniki kryzysowe 0,30 dla ropy i 0,297619 dla produktów dają marżę 25 PLN/bbl; mnożnik produktów 0,29 dałby tylko 22,44 PLN/bbl. Boom 1,14/1,25 daje 135 PLN/bbl. Zapis „mnożnik 0,29 na produkty” w §31.5 trzeba więc doprecyzować jako przybliżenie, nie dokładną wartość.

```yaml
# modules.market.params.world_events; wartości dla pojedynczego wydarzenia
draw_interval_h: 24
draw_chance_pct: 35
max_active_global: 2
global_queue: FIFO
regional_scope: tylko_własny_region
types: # ropa, popyt NPC, produkty, czas h, zapowiedź h, waga losowania
  boom:                {oil: 1.14, npc: 1.30, products: 1.25, duration_h: 48,  announce_h: 24, weight: 12, scope: global}
  pandemia:            {oil: 0.82, npc: 0.65, products: 0.86, duration_h: 72,  announce_h: 24, weight: 12, scope: global}
  embargo:             {oil: 1.10, npc: 0.90, products: 1.12, duration_h: 48,  announce_h: 24, weight: 14, scope: global, sabotage_risk: 1.20}
  wojna_naftowa:       {oil: 1.20, npc: 0.95, products: 1.08, duration_h: 48,  announce_h: 24, weight: 10, scope: global}
  kryzys_ekonomiczny:  {oil: 0.30, npc: 0.60, products: 0.297619, duration_h: 24, announce_h: 24, weight: 8, scope: global}
  frontier_open:       {oil: 1.02, npc: 1.15, products: 1.04, duration_h: 72,  announce_h: 24, weight: 44, scope: regional}
stacking:
  same_type_same_scope: false
  crisis_exclusive_with: [boom, pandemia, embargo, wojna_naftowa]
  global_global: "mnożniki mnożone; cena końcowa ropy i każdego produktu ograniczona do [0.30, 1.60] ceny bazowej"
  global_regional: "mnożniki mnożone; to samo ograniczenie ceny końcowej"
  overflow_global: "kolejka FIFO; nie usuwaj trwającego wydarzenia"
  gm: "wyjątek od zapowiedzi: 0 h; nadal obowiązują limity i wykluczenia"
```

### K2

Premia 12% to 30 PLN/bbl przy ropie po 250 PLN, więcej niż koszt cysterny 4 PLN/bbl, lecz pojedyncze wykrycie z karą 200% wartości sprzedaży może wymazać zysk z wielu transakcji. Przy +2 pkt próg flagi `≥30` osiąga się po 15 transakcjach, a kontrola `>30` zaczyna się po 16; zejście z 50 do zera trwa 50 dni bez transakcji.

```yaml
# modules.market.params.black_market + modules.core.params.black_market_score
oil_price_premium_pct: 12
premium_base: "faktyczna cena spot po poślizgu; bez premii do cen kontraktowych"
score_per_trade: 2
score_cap: 100
decay_pts_per_24h_since_last_trade: 1
decay_mode: "liniowo według rzeczywistego Δt; nowa transakcja zeruje tylko zegar od ostatniej transakcji, nie wynik"
gray_zone_flag_from_score: 30
tax_audit_above_score: 30
tax_audit_pct_per_h_per_excess_point: 0.02
trade_limit_per_company_utc_day: 10
volume_limit_bbl_per_company_utc_day: 1000
trade_control: "jeden klucz transakcji i blokada magazynu; limit liczony łącznie, nie na zlecenie"
detected_sale: "kara 200% wartości rynkowej wolumenu + credibility -12; niedobór -> zobowiązanie 72 h"
```

### K3

Premia +2 pkt za terminową dostawę podnosi reputację z 50 do 76 po 13 transzach; próg premium 75 wymaga zatem 13 sukcesów. Cztery zawinione zwroty po −8 sprowadzą wynik z 50 do 18, poniżej blokady rynku 25.

```yaml
# modules.market.params.b2b
reputation_start: 50
reputation_range: [0, 100]
reputation_delta_settled_on_time: 2
reputation_delta_settled_late_but_valid: 1
reputation_delta_settled_partial: 0
reputation_delta_refunded_seller_fault: -8
reputation_delta_missed_or_penal_cancel: -6
reputation_delta_cartel_sanction: -15
premium_contracts_min: 75
new_b2b_contracts_blocked_below: 25
reputation_credit: "raz na transzę, po ostatecznym settled; bez skalowania kwotą ani liczby ofert"
```

### K4

Wymagania „start około 55–65” i „start w progu kredytu 40–59” przecinają się tylko w 55–59; proponowane 58 daje limit 2 mln, nie 10 mln. Wiarygodność 90, pełna terminowość, niski dług i roczny staż dają 104 pkt; bankructwo ogranicza wynik do 40.

```yaml
# modules.banking.params.banking.credit_score_formula
range: [0, 115]
formula: "clamp(0,115,round_half_up(0.55*credibility + repayment_points + leverage_points + tenure_points))"
repayment_points:
  no_due_installments_in_12m: 12
  otherwise: "30 * on_time_installments_12m / max(1, due_installments_12m)"
leverage_points:
  formula: "12 * clamp(0,1,1 - debt_grosze/max(1,assets_grosze))"
  starter_without_debt: 8 # początkowa zdolność; nie naliczaj jako pełne 12
tenure_points: "12 * min(account_age_days,365)/365"
bankruptcy_history_cap: 40 # do 365 dni od zakończenia postępowania
delinquent_over_72h_cap: 39
recalculate_on: [loan_application, installment_settlement, bankruptcy_transition]
```

### K5

Poziom 1 daje czynnik ryzyka 0,88, a poziom 5 — 0,40, zgodnie z §17.4; nie należy dodawać osobnej redukcji za ten sam poziom. Suma ulepszeń do poziomu 5 wynosi 2,85 mln PLN — więcej niż płytki odwiert za 0,8 mln, lecz znacznie mniej niż offshore za 12 mln.

```yaml
# modules.incidents.params.technical_dept.bhp
starting_level: 1
risk_reduction_per_level_pct: 12 # wyłącznie istniejący czynnik (1 - 0.12*level)
upgrade_to:
  2: {cost_grosze: 15000000,  duration_h: 12}
  3: {cost_grosze: 35000000,  duration_h: 24}
  4: {cost_grosze: 80000000,  duration_h: 36}
  5: {cost_grosze: 140000000, duration_h: 48}
requirements: "obsadzony dyrektor techniczny; inspektor BHP z ukończonym kursem bezpieczeństwa; jeden upgrade równocześnie"
catastrophe_level_loss: 0
catastrophe_integrity_loss_pts: 25 # spada PI, nie zakupiony poziom
```

### K6

Przy standardowym zużyciu 1 punktu dziennie czynnik ryzyka `(2−PI/100)` rośnie ze startowego 1,00 do 1,20 po 20 dniach bez audytu. Kalibracja za 60 tys. PLN kosztuje mniej niż odwiert za 0,8 mln, ale wymaga powtarzania.

```yaml
# modules.incidents.params.technical_dept.procedure_integrity
start_pct: 100
range_pct: [0, 100]
wear_pts_per_24h: {bhp_low: 1.5, bhp_standard: 1.0, bhp_high: 0.7}
turbo_wear_multiplier: 1.5
warning_below_pct: 80
critical_below_pct: 50
risk_multiplier: "2 - procedure_integrity/100" # bez dodatkowej kary progowej
audit: {cost_grosze: 6000000, duration_h: 8, restore_pts: 20, cooldown_h: 72}
audit_start_condition: "obsadzony inspektor BHP; nie więcej niż jeden audyt naraz"
settlement: "zużycie proporcjonalne do Δt; wynik ograniczony do 0..100"
```

### K7

Wymiana rury za 180 tys. PLN to 22,5% kosztu płytkiego odwiertu; przy zużyciu 0,035–0,070%/h sama inspekcja musi być tańsza od wymiany. Firma zewnętrzna stosuje do każdej pozycji koszt ×2,5 i czas ×1,4, bez podwójnego doliczania mnożników.

```yaml
# modules.incidents.params.technical_dept.tts_task_queue
# sekundy są wartością zapisywaną w seconds_remaining
tasks:
  pipeline_inspection_segment: {seconds: 14400,  material_grosze: 1200000}
  wellhead_repair:             {seconds: 28800,  material_grosze: 9000000}
  hub_buffer_maintenance:      {seconds: 21600,  material_grosze: 3500000}
  tanker_service:              {seconds: 10800,  material_grosze: 1800000}
  bhp_calibration:              {seconds: 28800,  material_grosze: 6000000}
  pipeline_segment_replacement:{seconds: 43200,  material_grosze: 18000000}
external_contractor: {cost_multiplier: 2.5, time_multiplier: 1.4}
bhp_calibration_rule: "to samo zlecenie co audyt K6; jeden koszt i jeden przyrost PI, nie dwa"
pipeline_replacement_rule: "przywraca stan odcinka do 100%; osobny wpis materiałowy i stanowy"
queue_rule: "jeden inżynier na aktywne zadanie; koszt pobrany przy starcie, brak środków -> zadanie oczekuje"
```

### K8

Przy 10 pracownikach HR na poziomie standard kosztuje 400 PLN/dzień, a BHP 600 PLN/dzień; skala jest mała wobec utrzymania małego huba za 1800 PLN/h, ale wyższe finansowanie nadal jest stałym wydatkiem. Morale HR zachowuje zatwierdzone 40/70/100 pkt, zaś ryzyko BHP — istniejące ×1,5/1,0/0,7.

```yaml
# modules.finance.params.department_budgets
hr:
  low:      {grosze_per_employee_per_day: 2000, morale_pts: 40,  recruitment_time_multiplier: 1.50}
  standard: {grosze_per_employee_per_day: 4000, morale_pts: 70,  recruitment_time_multiplier: 1.00}
  high:     {grosze_per_employee_per_day: 8000, morale_pts: 100, recruitment_time_multiplier: 0.75}
bhp:
  low:      {grosze_per_technical_employee_per_day: 3000, risk_multiplier: 1.5}
  standard: {grosze_per_technical_employee_per_day: 6000, risk_multiplier: 1.0}
  high:     {grosze_per_technical_employee_per_day: 12000, risk_multiplier: 0.7}
change:
  cooldown_h: 24
  cut_morale_delta: -6 # istniejące zdarzenie §4.3, tylko raz za zmianę
  takes_effect_at: "chwila decyzji; koszty rozliczane proporcjonalnie do Δt"
  insufficient_funds: "należność jako zobowiązanie 72 h; nie tworzyć ujemnego salda"
```

### K9

Zespół 10 osób po średnio 8 tys. PLN/mies. kosztuje około 111 PLN/h, a 15 osób po 15 tys. — około 313 PLN/h: 2,1–6,0% wskazanej marży 5,2 tys. PLN/h. To zakłada miesiąc rozliczeniowy 720 h; pensje są kosztem odrębnym od tej marży, o ile §31.2 nie uwzględnia ich już w OPEX.

```yaml
# modules.hr.params.min_market_salary; miesięcznie, w groszach
directors:
  finance:   1800000
  technical: 1800000
  logistics: 1600000
  hr:        1500000
  legal:     1700000
staff:
  well_operator:         750000
  hub_operator:          700000
  pipeline_engineer:    1100000
  refinery_operator:     850000
  chemical_engineer:    1200000
  bhp_inspector:         950000
  logistician:           750000
  dispatcher:            700000
  analyst:               850000
candidate_expected_salary_multiplier: {min: 1.0, max: 1.6}
candidate_roll: "HMAC(candidate_id:offer_slot), raz zapisany; cechy kandydata wpływają na rozkład, nie przekraczają widełek"
pay_period_h: 720
salary_satisfaction: "clamp(0,120,current/max(expected,min_market_salary)*100)"
```

### T1

Jednolity porządek blokad jest ważniejszy niż kolejność opisana przez pojedyncze moduły: spot, sweeper i rozliczenie kontraktu muszą blokować te same wiersze w tej samej kolejności. Limit 5 prób pozwala ponowić konflikt, lecz nie zastępuje klucza idempotencji ani sprawdzenia CAS.

```markdown
§23.5 — Kanoniczny porządek blokad dla HTTP, kolejek i sweepa:

0. Blokada koordynacyjna regionu/rynku, JEŚLI operacja jej wymaga; nigdy
   nie trzymać jej podczas wywołań sieciowych. Nie blokować wszystkich graczy
   dla zwykłej sprzedaży.
1. companies (wszystkie uczestniczące company_id rosnąco).
2. fts_idempotency_keys (istniejące klucze rosnąco; nowe wstawiać w tej fazie).
3. accounts (company_id, account_type, account_id rosnąco; systemowe również).
4. wells (id rosnąco).
5. pipelines oraz pipeline_segments (typ, id rosnąco).
6. hubs oraz hub_buffers (typ, id rosnąco).
7. trips (id rosnąco).
8. storages i product_storages (typ, id rosnąco).
9. refineries (id rosnąco).
10. market_offers (id rosnąco).
11. contracts, contract_tranches, escrow_transactions (typ, id rosnąco).
12. obligations i loans (typ, id rosnąco).
13. zapisy append-only FTS/oil_movements/product_movements i zmiana CAS
    last_calc_at; nie pobierać już nowych blokad z wcześniejszych grup.

Najpierw ustal zbiór potrzebnych wierszy; nie odkrywaj kolejnego zasobu po
zablokowaniu późniejszej grupy. Brakujący klucz idempotencji wstaw przez
UNIQUE i obsłuż konflikt ponownym odczytem; nie stosuj SKIP LOCKED do
operacji wymagających dokładnego rozliczenia. Wewnątrz grupy zawsze sortuj
identyfikatory rosnąco. W Laravel: DB::transaction(...), maksymalnie 5 prób
dla MySQL 1213 (deadlock) i 1205 (lock wait timeout), opóźnienie
25/50/100/200/400 ms z losowym dodatkiem poza transakcją; limit oczekiwania
na blokadę 2 s, limit całej próby 5 s na poziomie aplikacji, po przekroczeniu
przerwij i zleć ponowienie. Po retry ponownie sprawdź saldo, wersję,
last_calc_at i status; zapis RNG HMAC(entity:due_slot) wykonuj raz.
```

### T2

MySQL nie zapewni globalnej unikalności `idempotency_key` w FTS partycjonowanej po `created_at`, dlatego klucz musi żyć poza partycjami. Przy 1000 graczy i przykładowo 2 zapisach dziennie na gracza powstaje około 730 tys. kluczy rocznie; jest to rejestr wymagający indeksowania i archiwizacji, nie automatycznego kasowania po roku.

```markdown
§29.4 — fts_idempotency_keys, tabela NIEPARTYCJONOWANA:

id BIGINT PK; namespace VARCHAR(64) NOT NULL;
idempotency_key VARBINARY(128) NOT NULL; request_hash BINARY(32) NOT NULL;
status VARCHAR(16) NOT NULL; fts_entry_id BIGINT NULL;
fts_created_at DATETIME(3) NULL; response_hash BINARY(32) NULL;
created_at DATETIME(3) NOT NULL; committed_at DATETIME(3) NULL;
UNIQUE(namespace, idempotency_key); INDEX(status, created_at).
Statusy: processing, committed. Zapis wyniku FTS i statusu committed
następuje w jednej transakcji; rollback usuwa również nowe processing.

W transakcji: (1) blokady companies; (2) INSERT klucza lub SELECT FOR UPDATE
po kolizji UNIQUE; (3) jeśli committed i request_hash równy — zwróć ten sam
wynik bez księgowania; jeśli hash różny — błąd konfliktu 409; (4) blokady
kont i zasobów według §23.5, sprawdzenie CAS stanu i sald; (5) FTS,
ruch towaru, CAS oraz oznaczenie klucza committed; COMMIT. Nie wykonuj
wcześniej nieodwracalnego działania poza bazą.

fts_entry_id wraz z fts_created_at identyfikuje wpis w partycji; bez
wymaganego klucza obcego do partycjonowanej FTS. Klucze zobowiązań,
kontraktów, rat, sweepów i innych zdarzeń możliwych do wznowienia zachowuj
trwale (zero-wipe). Klucze jednorazowych żądań HTTP wolno usunąć dopiero
po 24 miesiącach ORAZ po trwałym zapisie w archiwum z unikalnym indeksem
odtwarzania; odtworzenie starszego żądania najpierw sprawdza archiwum.
Bez takiego sprawdzenia nie kasuj kluczy. Dzienna kontrola porównuje
każdy committed z FTS/archiwum i alarmuje o brakującym wpisie.
```

### T3

Oferta dla płytkiego odwiertu 40 bbl/h powinna zaczynać się od dziennej transzy 240 bbl, a nie wymagać od nowej firmy pełnego huba o przepustowości 150 bbl/h. Przy 250 PLN/bbl taka transza w cenie 1,02×spot jest warta 61 200 PLN; kaucja całej umowy jest liczona od wszystkich jej transz, nie od jednej dostawy.

```yaml
# modules.market.params.contracts_system.npc_generator
phase: 4
generate_every_h: 24
offer_ttl_h: 48
active_offer_slots_per_region: {crude: 6, refinery_products: 4, retail_fuel: 3}
unclaimed_offer: "wygasa; kolejna generacja z nowym offer_slot, bez automatycznego zaniżania ceny"
seed: "HMAC(region_id:buyer_type:offer_slot:balance_version); wynik i snapshot ceny zapisane raz"
tiers:
  starter: {min_contract_reputation: 25, tranche_bbl: [240, 960],   tranches: [2, 4], interval_h: 24}
  standard:{min_contract_reputation: 50, tranche_bbl: [1000, 3000], tranches: [3, 7], interval_h: 24}
  premium: {min_contract_reputation: 75, tranche_bbl: [3000, 8000], tranches: [4, 8], interval_h: 24}
buyer_types: [refinery_crude, petrochemical_crude, retail_fuel]
pricing:
  reference: "regionalny spot odpowiedniego towaru; dla ropy bazowo 250 PLN/bbl"
  fixed: {spot_multiplier_range: [1.00, 1.04], snapshot_at: signing}
  market_multiplier: {multiplier_range: [1.00, 1.03], reference_at: tranche_due}
  market_plus_bonus: {bonus_pln_bbl_range: [2, 8], reference_at: tranche_due}
  rounding: "jedno zaokrąglenie wartości transzy do BIGINT groszy"
deposit_pct_of_signed_contract_value: 10
tranche_failure: {penalty_pct_of_missing_value: 5, contract_reputation_delta: -6}
three_consecutive_failures: {forfeit_deposit: true, tender_ban_days: 7}
physical_delivery: "NPC odbiera z magazynu głównego albo z huba po osiągnięciu dostępnego, niezarezerwowanego wolumenu; oil_movements/product_movements i receipt w jednej transakcji; bez teleportu z odwiertu"
payment: "SYS_MARKET_MAKER -> PLN_BANK; zajęcie 50% w Fazie 1 tylko raz przy wpływie"
reason_codes_to_register_in_market_manifest:
  - NPC_CONTRACT_DEPOSIT_LOCK
  - NPC_CONTRACT_DEPOSIT_RELEASE
  - NPC_CONTRACT_DEPOSIT_FORFEIT
  - NPC_CONTRACT_PAYOUT
  - NPC_CONTRACT_PENALTY
fuel_subtypes:
  aviation: "wyłącznie Jet A-1 quality>=92; kaucja/kary jak wyżej; użyj istniejących REF_FUEL_DEPOSIT/REF_FUEL_PAYOUT/REF_FUEL_PENALTY"
  rars: "produkty quality>=90; cena 1.20 * średnia spot produktu; istniejąca sankcja jakościowa 15% kaucji i -10 wiarygodności państwowej; bez dodatkowego mnożnika cenowego"
activation: "nowe kody FTS i INCOME_SET zarejestrować w manifeście oraz zatwierdzić nową balance_version w Filament przez dwie osoby"
```
