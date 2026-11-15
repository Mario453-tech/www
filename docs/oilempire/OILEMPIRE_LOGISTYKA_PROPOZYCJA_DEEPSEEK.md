# OIL EMPIRE — PEŁNA SEKCJA LOGISTYKI + AUDYT LEGACY

Data: 2026-11-09 | Autor: Hermes Agent (architekt + audytor)
Język: PL | Konwencje: OilEmpire §23 Tick Engine + §24 Waluta/FTS

---

# CZĘŚĆ 1 — SEKCJA "LOGISTYKA" DO SPECYFIKACJI OILEMPIRE

## §15. Logistyka

### 15.1. Architektura Łańcucha Dostaw

Logistyka w OilEmpire jest dwustopniowa i obowiązkowa – ropa nie trafia do magazynu automatycznie po wydobyciu. Każda baryłka przechodzi przez dwa odcinki transportu rozdzielone hubem logistycznym.

#### 15.1.1. Schemat kanoniczny

**Odwiert lądowy (onshore):**
```
odwiert → [rurociąg LUB transport drogowy] → hub → [rurociąg LUB transport drogowy] → magazyn
```

**Odwiert morski (offshore):**
```
odwiert → [transport morski] → hub → [rurociąg LUB transport drogowy] → magazyn
```

**Zasada:** magazyn otrzymuje tylko ropę, która przeszła oba odcinki. Skrót `odwiert → magazyn` jest NIEDOZWOLONY.

#### 15.1.2. Faza ticka

Logistyka wykonuje się w **Fazie 2** ticka (§23.2), po wydobyciu (Faza 1), przed rynkiem (Faza 3). Kolejność wewnętrzna Fazy 2:

1. Ukończenie budów rurociągów (`building → active`)
2. Dopływ z odwiertów do buforów transportowych (akumulacja `road_buffer_bbl`, `marine_buffer_bbl`)
3. Transport drogowy: wysyłka kursów z buforów, rozliczenie kursów przeterminowanych (ETA ≤ NOW)
4. Transport morski: wysyłka tankowców z buforów, rozliczenie rejsów przeterminowanych
5. Dopływ do hubów (kolejka `waiting_bay`): sumowanie dostarczonej ropy per hub
6. Przetwarzanie hubów: `HubTickService::processTick()` — przepustowość, bufor huba, straty
7. Transport z huba do magazynu (odcinek 2): rurociągi outbound + kursy `hub_road_trips`
8. Zapis do magazynu gracza (atomowy z `confirmCreditedTrips`)

#### 15.1.3. Stany transportowe

| Stan | Znaczenie | Dotyczy |
|------|----------|--------|
| `waiting_bay` | Ropa czeka w buforze odwiertu na wysyłkę | road_buffer, marine_buffer |
| `in_transit` | Kurs/rejs w drodze | well_road_trips, marine_deliveries |
| `crediting` | Kurs rozliczony, czeka na commit magazynu (M3 recovery) | well_road_trips |
| `delayed` | Kurs opóźniony (sabotaż, pogoda) — nowe ETA | well_road_trips, marine_deliveries |
| `delivered` | Dostarczone i zaksięgowane w magazynie | well_road_trips, marine_deliveries |
| `lost` | Utracone całkowicie | well_road_trips, marine_deliveries |
| `paused_storage` | Magazyn pełny — ropa czeka w buforze huba | logistics_hubs.buffer_current_bbl |

---

### 15.2. Huby Logistyczne

#### 15.2.1. Model własności

Huby są **infrastrukturą hybrydową** – istnieją huby systemowe (NPC, `player_id=0`) oraz huby graczy (zbudowane/kupione, `player_id>0`).

| Typ własności | Opis | player_id |
|---------------|------|-----------|
| **Systemowy (NPC)** | Hub postawiony przez świat gry. Gracz przypina do niego odwiert i płaci opłaty. | 0 |
| **Własny** | Gracz zbudował/kupił hub. Pełna kontrola, OPEX, rozbudowa. | >0 |
| **Wynajęty** | Gracz wynajmuje hub systemowy. `tenant_player_id>0` przy `player_id=0`. Płaci czynsz + OPEX. | 0 (tenant_player_id>0) |

**Przejmowanie hubów (acquisition):** gracz może kupić hub systemowy na własność (one-time fee wg `HubAcquisitionService`) lub wynająć go. Hub przejęty/wynajęty zmienia `player_id`/`tenant_player_id`.

#### 15.2.2. Tabela `logistics_hubs` — kluczowe kolumny

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | BIGINT UNSIGNED | PK |
| `player_id` | BIGINT UNSIGNED | 0=systemowy, >0=własność gracza |
| `tenant_player_id` | BIGINT UNSIGNED | Najemca (tylko gdy player_id=0) |
| `region_id` | INT | Region na mapie |
| `hub_type` | VARCHAR(16) | `small` / `medium` / `large` |
| `level` | TINYINT UNSIGNED | Poziom rozbudowy (1-3) |
| `acquisition_type` | VARCHAR(16) | `new` / `used` / `rental` (dla systemowych: seed balansu) |
| `status` | VARCHAR(16) | `planned` / `building` / `active` / `paused` / `damaged` / `maintenance` / `disabled` |
| `work_mode` | VARCHAR(16) | `eco` / `standard` / `turbo` |
| `condition_pct` | DECIMAL(8,4) | Kondycja techniczna 0–100 |
| `nominal_capacity_bph` | DECIMAL(12,2) | Nominalna przepustowość (bbl/h) |
| `buffer_capacity_bbl` | DECIMAL(12,2) | Pojemność bufora huba |
| `buffer_current_bbl` | DECIMAL(12,2) | Aktualny stan bufora |
| `slot_limit` | INT | Maks. liczba podpiętych odwiertów |
| `opex_per_tick` | DECIMAL(12,2) | Bazowy OPEX na tick |
| `lease_fee_per_tick` | DECIMAL(12,2) | Czynsz najmu na tick (tylko rental) |
| `outbound_transport_type` | VARCHAR(16) | `nieustawiony` / `rurociag` / `ciezarowki` |
| `last_maintenance_at` | DATETIME(3) | Ostatni przegląd techniczny |

#### 15.2.3. Tryby pracy (work_mode)

| Tryb | Mnożnik przepustowości | Mnożnik OPEX | Mnożnik zużycia (wear) | Efektywność | Ryzyko incydentu |
|------|------------------------|--------------|------------------------|-------------|------------------|
| `eco` | ×0.70 | ×0.60 | ×0.80 | -5% | ×0.90 |
| `standard` | ×1.00 | ×1.00 | ×1.00 | 0% | ×1.00 |
| `turbo` | ×1.40 | ×1.80 | ×2.20 | +5% | ×1.50 |

**Reguła:** zmiana trybu możliwa raz na 12 ticków (12 min real-time). Tryb `turbo` przy kondycji <40% podwaja ryzyko awarii (`wear ×2.0` dodatkowo).

#### 15.2.4. Bufory i przepełnienie

Hub przyjmuje ropę do `buffer_current_bbl`. Gdy `buffer_current_bbl + input > buffer_capacity_bbl`:
- Nadmiar jest **tracony fizycznie** (`overflow_lost_bbl`), chyba że hub ma aktywny outbound transport.
- Przy outbound `rurociag`: nadmiar czeka w buforze odwiertu (backpressure).
- Przy outbound `ciezarowki`: nadmiar czeka w buforze huba do następnego kursu.

**Kondycja a straty:** `condition_lost_bbl = processed × max(0, (50 - condition_pct) / 200)`. Przy 20% kondycji hub traci ~15% przepływającej ropy (nieszczelności, parowanie).

#### 15.2.5. Przepustowość

Efektywna przepustowość ticka:
```
real_capacity = nominal_capacity_bph × condition_factor × work_mode_throughput_mult × delta_hours
```
gdzie `condition_factor`:
- `condition_pct < 20%` → 0.10 (krytyczny)
- `condition_pct < 30%` → 0.30 (uszkodzony)
- `condition_pct ≥ 30%` → `condition_pct / 100` (liniowo)

#### 15.2.6. Obsada huba

| Stanowisko | Specjalizacja (hr_specializations.code) | Efekt |
|------------|------------------------------------------|-------|
| **Hub operator** | `hub_operator` (dział techniczny) | Skaluje przepustowość i redukuje ryzyko incydentu |
| **Technik** | `technician` | Skaluje szybkość napraw i konserwacji |

Model: `LogisticsStaffingService` liczy `coverage_pct` (0–100%) per hub. `coverage_pct < 50%` → mnożnik przepustowości ×0.7, ryzyko incydentu ×1.5.

#### 15.2.7. Rozbudowa

Hub można rozbudować do poziomu 3 (`level`). Koszt: `HubEconomyService::getUpgradeCost()`.
- Poziom 1→2: +40% przepustowości, +50% slotów, +30% OPEX
- Poziom 2→3: +30% przepustowości, +30% slotów, +25% OPEX
- Czas rozbudowy: 24h (Poziom 2), 48h (Poziom 3) — tick czasu rzeczywistego.
- Podczas rozbudowy hub działa w trybie `maintenance` (przepustowość ×0.50).

#### 15.2.8. Przypisanie odwiertu do huba

Tabela `logistics_hub_assignments`:

| Kolumna | Opis |
|---------|------|
| `well_id` | FK wells.id |
| `hub_id` | FK logistics_hubs.id |
| `status` | `active` / `detached` / `relinking` |
| `access_fee_paid` | Jednorazowa opłata dostępowa |
| `cooldown_until` | Blokada ponownego przypisania (4h po odpnięciu) |
| `relink_started_at` / `relink_finish_at` | Timer przepięcia |

**Opłata dostępowa (jednorazowa):**
```
slot_cost = opex_per_tick / slot_limit
new    → access_fee = slot_cost × 5
used   → access_fee = slot_cost × 8
rental → access_fee = lease_fee_per_tick × 3
```

**Przepięcie (relink):** zatrzymuje pracę odwiertu na czas przepięcia (konfigurowalny, domyślnie 2 ticki = 120s). OPEX odwiertu naliczany normalnie.

#### 15.2.9. Pozwolenia (hub permits)

W regionach z `hub_permit_enabled=1` (tabela `legal_region_config`) gracz potrzebuje zezwolenia na prace lokalne przed budową/kupnem huba.

Tabela `hub_permit_applications`:
- `status`: `pending` → `granted` / `refused` / `delayed` / `no_decision`
- `decision_due_at`: termin decyzji (domyślnie +120 min od `submitted_at`)
- `cost`: opłata za wniosek (domyślnie 500 000 PLN)
- `refusal_cooldown_until`: blokada ponownego wniosku po odmowie
- `delay_count`: liczba przedłużeń (biurokracja)

---

### 15.3. Rurociągi

#### 15.3.1. Model danych — `well_pipelines`

Rurociąg jest **infrastrukturą gracza** kupowaną per odcinek. Jeden odwiert może mieć jeden rurociąg `inbound` i jeden `outbound` (ten ostatni przez hub: `well_id=0`).

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | INT PK | |
| `player_id` | INT | Właściciel |
| `well_id` | INT | 0 dla outbound (hub→magazyn) |
| `hub_id` | INT | Hub powiązany |
| `leg` | ENUM(`inbound`, `outbound`) | Odcinek |
| `pipeline_type` | VARCHAR(16) | `light` / `standard` / `heavy` |
| `status` | VARCHAR(16) | `building` / `active` / `degraded` / `critical` / `leak` / `damaged` / `suspended` / `disabled` |
| `condition_pct` | DECIMAL(8,4) | Stan techniczny |
| `transport_loss` | DECIMAL(6,2) | Procent strat na przesyle |
| `nominal_capacity_bph` | DECIMAL(12,2) | Przepustowość nominalna | 
| `real_capacity_bph` | DECIMAL(12,2) | Przepustowość rzeczywista |
| `opex_per_tick` | DECIMAL(12,2) | OPEX na tick |
| `build_cost` | DECIMAL(12,2) | Koszt budowy |
| `build_started_at` | DATETIME(3) | Start budowy |
| `build_finish_at` | DATETIME(3) | Planowane zakończenie |

#### 15.3.2. Typy rurociągów

| Typ | Koszt budowy (PLN) | Czas budowy | Przepustowość (bbl/h) | OPEX/tick | Degradacja/h | Ryzyko incydentu |
|-----|---------------------|-------------|----------------------|-----------|--------------|-------------------|
| `light` | 11 000 | 4h | 60 | 95.00 | 0.070 | ×1.25 |
| `standard` | 18 000 | 8h | 120 | 140.00 | 0.050 | ×1.00 |
| `heavy` | 28 000 | 16h | 200 | 190.00 | 0.035 | ×0.85 |

Wartości startowe w `game_balance.pipeline_types`. Admin może edytować procentowo przez panel (`price_pct`, `capacity_pct`, `durability_pct` względem bazowych).

#### 15.3.3. Budowa

1. Gracz musi mieć **aktywne przypisanie odwiertu do huba** (inbound) lub hub z `outbound_transport_type=rurociag` (outbound).
2. Wymagane **pozwolenie Poziomu 2** (hub permit `granted` przez `legal_region_config`).
3. Środki: `build_cost` z gotówki gracza (FTS `TYPE_PIPELINE_BUILD`).
4. Rurociąg tworzony ze statusem `building`, `build_finish_at = NOW() + build_hours`.
5. Tick (Początek Fazy 2) automatycznie przełącza `building → active` przez `completeBuildingPipelines()`.

**W trakcie budowy:** rurociąg nie przesyła ropy. Gracz może używać transportu drogowego jako tymczasowego.

#### 15.3.4. Zużycie i stany

| Status | Kondycja | Efekt |
|--------|----------|-------|
| `active` | >70% | Pełna przepustowość |
| `degraded` | 40–70% | Przepustowość ×0.70 |
| `critical` | 20–40% | Przepustowość ×0.40, ryzyko awarii ×2 |
| `leak` | — | Wyciek: +0.4 pkt transport_loss/tick, degradacja +20% |
| `damaged` | ≤0% | Wymaga naprawy, nie przesyła ropy |
| `suspended` | — | Wyłączony przez gracza (odwiert wraca na ciężarówki) |
| `disabled` | — | Wyłączony administracyjnie |

Degradacja: `condition_pct -= degradation_rate_per_hour × delta_hours × engineer_mult × hse_mult`.

#### 15.3.5. Straty i inspekcje

`transport_loss` (0–10%): procent ropy tracony na przesyle. Rośnie z degradacją i wyciekami.

**Inspekcje (TTS):** zadanie techniczne `pipeline_inspection` — przywraca `last_inspected_at`, resetuje ukryty licznik ryzyka wycieku. Koszt inspekcji: stały + zmienny od długości/delta od ostatniej.

#### 15.3.6. Obsada rurociągu

| Stanowisko | Kod | Efekt |
|------------|-----|-------|
| **Pipeline engineer** | `pipeline_engineer` | Skaluje degradację (`engineer_degradation_mult`) i ryzyko incydentu (`engineer_incident_mult`) |
| **Pipeline logistics specialist** | `pipeline_logistics_specialist` | Redukuje `transport_loss` tylko przypisanego odcinka |

Pokrycie <50%: `engineer_degradation_mult = 2.0`, `engineer_incident_mult = 2.0`.

#### 15.3.7. Incydenty rurociągowe

| Poziom | Szansa bazowa/h | loss_add (pkt) | cond_drop (pkt) |
|--------|-----------------|----------------|-----------------|
| `pipe_micro` | 0.015 | 0.1–0.5 | 0.5–2.0 |
| `pipe_minor` | 0.005 | 0.5–1.5 | 2.0–5.0 |
| `pipe_medium` | 0.001 | 1.5–3.0 | 5.0–12.0 |

Szansa skalowana: `base_chance × delta_hours × incident_risk_mult × cond_factor × engineer_incident_mult × hse_mult × protection_mult`.

**Sabotaż rurociągu** (§7): typ `hub_valve_sabotage` — jednorazowe uderzenie: `condition_pct −20`, `transport_loss +5`, `status→leak`.

---

### 15.4. Transport Drogowy (Cysterny)

#### 15.4.1. Model danych

| Tabela | Opis |
|--------|------|
| `well_road_configs` | Konfiguracja per odwiert: typ ciężarówki, pojemność kursu |
| `well_road_trips` | Kursy drogowe w czasie (odwiert→hub) |
| `hub_road_trips` | Kursy drogowe hub→magazyn |
| `well_road_incident_logs` | Logi incydentów drogowych |

#### 15.4.2. Typy ciężarówek

| Typ | Pojemność (bbl) | Koszt/kurs (PLN) | Czas kursu (h) | Ryzyko incydentu | Koszt zakupu floty (PLN) |
|-----|-----------------|-------------------|----------------|------------------|---------------------------|
| `standard` | 25 | 500 | 2 | ×1.00 | 0 (startowe) |
| `heavy` | 50 | 900 | 3 | ×0.80 | 25 000 |
| `armored` | 20 | 1 500 | 2 | ×0.30 | 80 000 |

#### 15.4.3. Mechanika kursów

1. **Bufor drogowy** (`wells.road_buffer_bbl`): ropa z odwiertu akumuluje się w buforze. Po osiągnięciu `min_load_bbl` (konfigurowalne, domyślnie równe `trip_capacity_bbl`) system wysyła kurs.
2. **Wysłanie kursu:** `dispatchTrips()` tworzy rekord(y) w `well_road_trips` z `departure_at = NOW()`, `eta_at = NOW() + trip_hours`. Koszt odejmowany atomowo z gotówki (FTS `TYPE_ROAD_TRIP`).
3. **Rozliczenie:** w ticku (Faza 2) `processCompletedTrips()` dla kursów z `eta_at ≤ NOW()`. Losowanie incydentów per kurs.
4. **Dostarczenie:** rola trafia do bufora huba (odwierty z przypisaniem do huba) LUB bezpośrednio do magazynu (odwierty bez huba — fallback).

#### 15.4.4. Incydenty drogowe

| Typ | Waga | Efekt | Odporność (armored) |
|-----|------|-------|---------------------|
| `theft` | 3 | Cały kurs stracony | ×0.20 |
| `raid` | 2 | Cały kurs stracony | ×0.15 |
| `accident` | 4 | 50–100% wolumenu stracone | ×1.00 (brak odporności) |
| `sabotage` | 1 | Wg SabotageService | ×0.30 |
| `route_block` | 2 | Cały kurs stracony | ×1.00 (brak odporności) |

Bazowa szansa incydentu na kurs na godzinę: **1.5%** (`BASE_INCIDENT_CHANCE_PER_HOUR = 0.015`).

#### 15.4.5. Routing i pętla automatyczna

- **Ręczny:** gracz wybiera `transport_type = 'ciezarowki'` per odwiert. System automatycznie wysyła kursy z bufora.
- **Pętla VIP** (§22.6): automatyczne odnawianie kursów bez interwencji gracza. Wymaga aktywnego VIP Tier III+. VIP Tier V: priorytet w kolejce `waiting_bay`.

#### 15.4.6. Kolejka `waiting_bay`

Kursy oczekujące na wolną przepustowość huba. Sortowane: VIP Tier V > VIP > standard > czas oczekiwania. Jeśli `waiting_bay` > 3× `buffer_capacity_bbl` huba, hub przechodzi w stan `overloaded`.

#### 15.4.7. Koszt kursu (składowe)

```
koszt_kursu = trips_count × cost_per_trip
gdzie trips_count = ceil(buffer_volume / trip_capacity_bbl)
```
Modyfikatory: strajk logistyki (§5.1: `transport_cost_mult`), budżet działowy (§10: low ×1.3, high ×0.8).

---

### 15.5. Porty i Transport Morski

#### 15.5.1. Model danych

| Tabela | Opis |
|--------|------|
| `ports` | Porty systemowe |
| `marine_deliveries` | Rejsy tankowców |
| `port_queue` | Kolejka w porcie |

#### 15.5.2. Porty — tabela `ports`

| Kolumna | Typ | Opis |
|---------|-----|------|
| `region_id` | INT | Region |
| `port_type` | VARCHAR(16) | `small` / `medium` / `large` |
| `throughput_per_tick` | DECIMAL(12,2) | Przepustowość (bbl/tick) |
| `queue_limit` | INT | Maks. dostaw w kolejce |
| `handling_cost_per_bbl` | DECIMAL(8,4) | Opłata portowa za bbl |
| `base_transit_hours` | DECIMAL(6,2) | Bazowy czas rejsu |
| `overload_risk_pct` | DECIMAL(5,2) | Szansa opóźnienia przy przeciążeniu |
| `failure_risk_per_tick` | DECIMAL(8,6) | Szansa awarii portu |
| `status` | VARCHAR(16) | `active` / `overloaded` / `damaged` / `closed` |

#### 15.5.3. Tankowce — typy

| Typ | Pojemność (bbl) | Koszt/rejs (PLN) | Czas (h) | Ryzyko |
|-----|-----------------|-------------------|----------|--------|
| `small` | 2 000 | 8 000 | 3 | ×1.10 |
| `medium` | 5 000 | 15 000 | 5 | ×1.00 |
| `large` | 12 000 | 30 000 | 8 | ×0.85 |

#### 15.5.4. Mechanika rejsów

1. **Bufor morski** (`wells.marine_buffer_bbl`): ropa akumuluje się przy odwiercie.
2. **Wysłanie:** gdy `marine_buffer_bbl ≥ min_load_bbl` (domyślnie 4000 bbl), tworzony rejs w `marine_deliveries`.
3. **Przydział portu:** automatyczny do najbliższego portu z wolną kolejką.
4. **Rejs:** `status = 'in_transit'`, `eta_at = NOW() + transit_hours`.
5. **Dotarcie:** statek wchodzi do `port_queue`. Port przetwarza wg przepustowości.
6. **Rozładunek do huba:** ropa trafia do bufora huba docelowego.

#### 15.5.5. Incydenty morskie

| Typ | Efekt |
|-----|-------|
| `storm` | Opóźnienie +30–120 min, możliwa utrata 5–20% ładunku |
| `piracy` | Utrata 30–100% ładunku |
| `breakdown` | Opóźnienie +60–240 min |
| `delay` | Opóźnienie +15–60 min |
| `cataclysm` | Utrata całego rejsu |
| `sabotage` | Wg SabotageService |

#### 15.5.6. Kiedy opłacalny transport morski vs drogowy/rurociąg

Transport morski jest opłacalny gdy:
- Odległość od huba >8h drogą lądową
- Wolumen >4000 bbl (próg `min_load_bbl`)
- Odwiert offshore (jedyna opcja na odcinku 1)
- Ryzyko polityczne regionu przybrzeżnego <3

**Nieopłacalny:** małe odwierty (<2000 bbl/dzień), krótkie dystanse, wysokie ryzyko pirackie.

---

### 15.6. Transport Mix i Ekonomia

#### 15.6.1. Tabela porównawcza (wartości startowe do `game_balance`)

| Środek transportu | Koszt/bbl (PLN) | Czas (h) | Strata (%) | Ryzyko incydentu | CAPEX (PLN) | OPEX/bbl (PLN) |
|-------------------|-----------------|----------|------------|-------------------|-------------|----------------|
| Rurociąg light | 0.50 | real-time flow | 0–5 | ×1.25 | 11 000 | 0.50 |
| Rurociąg standard | 0.50 | real-time flow | 0–3 | ×1.00 | 18 000 | 0.50 |
| Rurociąg heavy | 0.50 | real-time flow | 0–2 | ×0.85 | 28 000 | 0.50 |
| Cysterna standard | 20.00 | 2 | 0–100 | ×1.30 | 0 | 20.00 |
| Cysterna heavy | 18.00 | 3 | 0–100 | ×1.04 | 25 000 | 18.00 |
| Cysterna armored | 75.00 | 2 | 0–100 | ×0.39 | 80 000 | 75.00 |
| Tankowiec small | 4.00 | 3 | 5–20 | ×1.10 | 0 | 4.00 |
| Tankowiec medium | 3.00 | 5 | 5–15 | ×1.00 | 0 | 3.00 |
| Tankowiec large | 2.50 | 8 | 5–10 | ×0.85 | 0 | 2.50 |

**Uwaga:** koszt/bbl dla rurociągu to `cost_per_bbl` z `transport_config`. Koszt/bbl dla cystern = `cost_per_trip / trip_capacity_bbl`. Dla tankowców = `cost_per_shipment / shipment_capacity_bbl + handling_cost_per_bbl`.

#### 15.6.2. OPEX logistyki — kalkulacja w ticku

Dla każdego gracza w Fazie 2:
```
logistics_opex = SUM(opex_per_tick_hubów) + SUM(opex_per_tick_rurociągów) + SUM(koszt_kursów_drogowych) + SUM(koszt_rejsów_morskich)
```
Potrącany atomowo z gotówki przez FTS (`TYPE_LOGISTICS_OPEX`).

#### 15.6.3. Wpływ budżetu działowego (§10)

| Poziom budżetu | Mnożnik OPEX | Mnożnik przepustowości | Mnożnik awarii | Mnożnik czasu kursu |
|----------------|--------------|------------------------|----------------|---------------------|
| `low` (oszczędny) | ×0.70 | ×0.80 | ×1.40 | ×1.30 |
| `standard` | ×1.00 | ×1.00 | ×1.00 | ×1.00 |
| `high` (premium) | ×1.40 | ×1.20 | ×0.70 | ×0.85 |

#### 15.6.4. Wpływ strajku logistyki (§5.1)

Gdy dział logistyki strajkuje:
- Przepustowość spada o `30% + 50% × udział_strajkujących`
- Jeśli udział >60%: **trasa główna staje** (huby przestają przyjmować nową ropę, tranzyt wstrzymany)
- Koszty kursów: `transport_cost_mult` (StrikeEffectService)
- Opóźnienia: `delay_risk_mult` rośnie

---

### 15.7. Integracje

#### 15.7.1. Faza 2 ticka — kolejność operacji

```
1. completeBuildingPipelines(playerId)          // building → active
2. WellProductionHandler dopisuje do buforów    // road_buffer_bbl, marine_buffer_bbl
3. dispatchTrips z buforów drogowych            // road_buffer → well_road_trips
4. dispatchMarineDeliveries z buforów morskich  // marine_buffer → marine_deliveries
5. processCompletedTrips (kursy z eta_at≤NOW)   // → delivered_bbl per well
6. queueHubDeliveredInputs                      // agregacja dostaw per hub
7. HubTickService::processTick per hub          // przepustowość, bufor huba, straty
8. WellHubSection::finalizeHubTicks             // OPEX hubów + zapis do magazynu
9. PipelineSection::process (degradacja inbound+outbound)
10. Hub → storage: outbound rurociągi + hub_road_trips
11. Zapis magazynu + confirmCreditedTrips (atomowe)
```

#### 15.7.2. Fizyka dostaw B2B (§13.2)

Dostawa B2B **NIE JEST realnym kursem** — to timer wypełnienia kontraktu z magazynu sprzedającego. Logistyka B2B polega na tym, że:
- Sprzedający musi mieć ropę w magazynie (przeszła oba odcinki logistyki).
- Po akceptacji kontraktu ropa jest rezerwowana w `PLN_ESCROW`.
- Czas dostawy to stały timer (niezależny od fizycznej logistyki).
- Jeśli w trakcie timera magazyn sprzedającego spadnie poniżej zobowiązania → naruszenie kontraktu + kara.

**Wyjątek:** kontrakty wewnątrz kartelu (§26.3) mogą używać fizycznego transportu (opcjonalnie, szybsze rozliczenie).

#### 15.7.3. Incydenty logistyczne

| Kategoria | Typy | Wywołanie | Efekt |
|-----------|------|-----------|-------|
| Wyciek huba | `hub_leak` | Losowe (condition-based) | `condition_lost_bbl` z bufora |
| Awaria huba | `hub_breakdown` | Losowe + sabotaż | Hub `status→damaged`, przepustowość ×0 |
| Wyciek rurociągu | `pipe_micro/minor/medium` | Losowe per tick | loss_add, cond_drop |
| Eksplozja rurociągu | `pipeline_explosion` | Losowe (cond<40%) | Utrata ropy, kara środowiskowa, `status→damaged` |
| Napad drogowy | `raid`, `theft` | Losowe per kurs | Cały kurs stracony |
| Sabotaż cysterny | `road_ambush` | SabotageService | Wg opcji sabotażu (strata 20–100%, opóźnienie) |
| Częściowa kradzież | `road_partial_theft` | SabotageService | Strata 30–60% kursu |
| Sabotaż zaworu | `hub_valve_sabotage` | SabotageService | `condition_pct −20`, `transport_loss +5`, `status→leak` |
| Patrol dronami | `drone_patrol` | ProtectionService | Redukuje `theft/raid/sabotage` ×0.50 na cysternach |

#### 15.7.4. Ochrona logistyki (§7)

Moduł `ProtectionService` — opcje ochrony:

| Cel | Opcja | Koszt (PLN) | Czas | Efekt |
|-----|-------|-------------|------|-------|
| Transport drogowy | Eskorta podstawowa | 75 000 / 60min | 60 min | theft×0.7, raid×0.7, sabotage×0.7 |
| Transport drogowy | Konwój uzbrojony | 500 000 / 60min | 60 min | theft×0.3, raid×0.3, sabotage×0.3 |
| Transport drogowy | Patrol dronami | 50 000 / h | 120 min | theft×0.5, raid×0.5, sabotage×0.5 |
| Hub | Ochrona huba | 120 000 / 60min | 60 min | damage×0.5, leak×0.5, overload×0.5 |
| Rurociąg | Monitoring rurociągu | 30 000 / h | 120 min | pipeline_incident_risk ×0.6 |

---

### 15.8. Parametry `game_balance` — logistyka

```php
'logistics' => [
    // Huby
    'hub_types' => [
        'small'  => ['build_cost' => 250000, 'nominal_bph' => 80,  'buffer_bbl' => 2000,  'slot_limit' => 3, 'opex_per_tick' => 120.00,  'wear_per_tick' => 0.03, 'upgrade_cost' => 150000, 'max_level' => 3],
        'medium' => ['build_cost' => 600000, 'nominal_bph' => 200, 'buffer_bbl' => 5000,  'slot_limit' => 6, 'opex_per_tick' => 220.00,  'wear_per_tick' => 0.04, 'upgrade_cost' => 350000, 'max_level' => 3],
        'large'  => ['build_cost' => 1200000,'nominal_bph' => 500, 'buffer_bbl' => 12000, 'slot_limit' => 12,'opex_per_tick' => 380.00,  'wear_per_tick' => 0.05, 'upgrade_cost' => 700000, 'max_level' => 3],
    ],
    'acquisition' => [
        'new'    => ['wear_mult' => 1.0, 'opex_mult' => 1.0],
        'used'   => ['wear_mult' => 1.3, 'opex_mult' => 1.15],
        'rental' => ['wear_mult' => 1.0, 'opex_mult' => 0.9, 'lease_fee_per_tick' => ['small' => 120, 'medium' => 220, 'large' => 380]],
    ],
    'work_modes' => [
        'eco'      => ['throughput_mult' => 0.70, 'opex_mult' => 0.60, 'wear_mult' => 0.80, 'efficiency_mod' => -5.0],
        'standard' => ['throughput_mult' => 1.00, 'opex_mult' => 1.00, 'wear_mult' => 1.00, 'efficiency_mod' =>  0.0],
        'turbo'    => ['throughput_mult' => 1.40, 'opex_mult' => 1.80, 'wear_mult' => 2.20, 'efficiency_mod' =>  5.0],
    ],
    'cooldown_hours' => 4,  // po odpnięciu odwiertu

    // Rurociągi
    'pipeline_types' => [
        'light'    => ['build_cost' => 11000, 'build_hours' => 4,  'nominal_bph' => 60,  'opex_per_tick' => 95.00,  'degradation_rate_per_hour' => 0.070, 'incident_risk_mult' => 1.25],
        'standard' => ['build_cost' => 18000, 'build_hours' => 8,  'nominal_bph' => 120, 'opex_per_tick' => 140.00, 'degradation_rate_per_hour' => 0.050, 'incident_risk_mult' => 1.00],
        'heavy'    => ['build_cost' => 28000, 'build_hours' => 16, 'nominal_bph' => 200, 'opex_per_tick' => 190.00, 'degradation_rate_per_hour' => 0.035, 'incident_risk_mult' => 0.85],
    ],
    'pipeline_incidents' => [
        'pipe_micro'  => ['base_chance' => 0.015, 'loss_add_min' => 0.1, 'loss_add_max' => 0.5, 'cond_drop_min' => 0.5, 'cond_drop_max' => 2.0],
        'pipe_minor'  => ['base_chance' => 0.005, 'loss_add_min' => 0.5, 'loss_add_max' => 1.5, 'cond_drop_min' => 2.0, 'cond_drop_max' => 5.0],
        'pipe_medium' => ['base_chance' => 0.001, 'loss_add_min' => 1.5, 'loss_add_max' => 3.0, 'cond_drop_min' => 5.0, 'cond_drop_max' => 12.0],
    ],
    'explosion_base_chance' => 0.0006,  // per hour when condition < 40%

    // Transport drogowy
    'truck_types' => [
        'standard' => ['trip_capacity_bbl' => 25.0, 'cost_per_trip' => 500.0,  'incident_risk_mult' => 1.000, 'trip_hours' => 2],
        'heavy'    => ['trip_capacity_bbl' => 50.0, 'cost_per_trip' => 900.0,  'incident_risk_mult' => 0.800, 'trip_hours' => 3],
        'armored'  => ['trip_capacity_bbl' => 20.0, 'cost_per_trip' => 1500.0, 'incident_risk_mult' => 0.300, 'trip_hours' => 2],
    ],
    'road_incident_chance_per_hour' => 0.015,
    'road_incident_weights' => ['theft' => 3, 'raid' => 2, 'accident' => 4, 'sabotage' => 1, 'route_block' => 2],

    // Transport morski
    'tanker_types' => [
        'small'  => ['shipment_capacity_bbl' => 2000,  'cost_per_shipment' => 8000,  'incident_risk_mult' => 1.10, 'transit_hours' => 3],
        'medium' => ['shipment_capacity_bbl' => 5000,  'cost_per_shipment' => 15000, 'incident_risk_mult' => 1.00, 'transit_hours' => 5],
        'large'  => ['shipment_capacity_bbl' => 12000, 'cost_per_shipment' => 30000, 'incident_risk_mult' => 0.85, 'transit_hours' => 8],
    ],
    'min_load_bbl_tanker' => 4000,
    'marine_incident_chance_per_hour' => 0.012,
    'port_defaults' => [
        'small'  => ['throughput_per_tick' => 500,  'handling_cost_per_bbl' => 0.50, 'base_transit_hours' => 3.0, 'queue_limit' => 10],
        'medium' => ['throughput_per_tick' => 1500, 'handling_cost_per_bbl' => 0.35, 'base_transit_hours' => 5.0, 'queue_limit' => 20],
        'large'  => ['throughput_per_tick' => 4000, 'handling_cost_per_bbl' => 0.20, 'base_transit_hours' => 8.0, 'queue_limit' => 40],
    ],

    // Ogólne
    'fallback_throughput_bph' => 100,  // dla odwiertów bez huba
    'fallback_opex_mult' => 2.0,
    'staffing_threshold_warning' => 50,  // coverage_pct < 50% → efekty
    'repair_cost_per_condition_pt' => 150,  // PLN za 1 pkt condition
    'maintenance_duration_ticks' => 4,
],
```

---

# CZĘŚĆ 2 — AUDYT LEGACY OILCORP

## 2.1. CO PRZENIEŚĆ 1:1 (mechaniki sprawdzone i dobre)

### 2.1.1. Dwustopniowy model transportu (leg inbound + outbound)
**Źródło:** `LOGISTICS_HUBS_README_UJEDNOLICONE_FINAL.md §2`, `OSTATECZNA_WERSJA_HUB_RUROCIAG.md §4`, `etap10_transport_leg2.sql`, `etap11_hub_outbound_pipeline.sql`
**Decyzja:** model odwiert→hub→magazyn jest fundamentalnie poprawny i przetestowany. Przenosimy 1:1 z ulepszeniem: outbound zapisany per hub (nie per odwiert — to zostało już poprawione w ETAP 11).

### 2.1.2. System buforów transportowych (road_buffer, marine_buffer)
**Źródło:** `TransportConfigService.php` + notka "Transport morski: bufor tankowca" (2026-06-07)
**Decyzja:** mechanika akumulacji ropy w buforze do progu `min_load_bbl` przed wysyłką sprawdziła się. Eliminuje mikro-kursy co tick. Przenosimy 1:1.

### 2.1.3. Mechanika kursów drogowych jako bytów w czasie
**Źródło:** `RoadTransportService.php` — `dispatchTrips()`, `processCompletedTrips()`, `well_road_trips`
**Decyzja:** model z `departure_at`/`eta_at`/`status`/`incident_type` jest dojrzały. Przenosimy 1:1 z przeniesieniem na Laravel (Eloquent + Job/Queue zamiast bezpośredniego PDO).

### 2.1.4. Ochrona jako uniwersalny moduł (`ProtectionService`)
**Źródło:** `ProtectionService.php`, notki "Ochrona: uniwersalny moduł" + "Ochrona P2: huby i rurociągi" (2026-06-12)
**Decyzja:** architektura `target_type` + `context` + `effects` jako klucz-wartość jest czysta i rozszerzalna. Przenosimy 1:1.

### 2.1.5. Mechanizm `crediting` + `confirmCreditedTrips` (M3 recovery)
**Źródło:** `RoadTransportService.php` — `confirmCreditedTrips()`, `WellRoadTripSection.php`
**Decyzja:** dwufazowe potwierdzenie kursów (crediting → delivered w transakcji z zapisem magazynu) rozwiązuje problem crashu ticka. Przenosimy 1:1.

### 2.1.6. Cooldown po odpnięciu odwiertu od huba (4h)
**Źródło:** `HubAssignmentService.php`, `OSTATECZNA_WERSJA_HUB_RUROCIAG.md §2.4`
**Decyzja:** prosty i skuteczny mechanizm blokujący ping-pong hubów. Przenosimy 1:1.

### 2.1.7. Konfiguracja transportu per typ w `transport_config`
**Źródło:** `TransportConfigService.php`, tabela `transport_config`
**Decyzja:** model `transport_type → config_key → config_value` jest czytelny i łatwy do edycji z panelu admina. Przenosimy 1:1 do `game_balance`.

### 2.1.8. Tryby pracy huba (eco/standard/turbo)
**Źródło:** `HubService::getWorkModeMultipliers()`, notka "Logistyka: zmiana trybu pracy huba" (2026-07-22)
**Decyzja:** mechanika z mnożnikami `throughput_mult`, `opex_mult`, `wear_mult`, `efficiency_mod` jest czysta. Przenosimy 1:1.

---

## 2.2. CO DOPRACOWAĆ/ULEPSZYĆ

### 2.2.1. Własność hubów — niedokończona
**Źródło:** `OSTATECZNA_WERSJA_HUB_RUROCIAG.md §7`: "Player ownership model dla hubów (gracz kupuje hub) — wysoki priorytet, nie zaczęte"
**Problem:** legacy ma `player_id` w `logistics_hubs` i `tenant_player_id`, ale flow zakupu/wynajmu przez gracza nie został dokończony. Są kolumny (`acquisition_price`, `acquired_at`) i serwis `HubAcquisitionService`, ale panel gracza nie pokazuje dostępnych hubów do kupna.
**Poprawa w OilEmpire:** zaimplementować pełny flow: lista hubów systemowych na sprzedaż/wynajem → zakup przez FTS → przejście `player_id: 0→gracz`. Dodać `HubMarketService` z listą dostępnych hubów.

### 2.2.2. Transport z huba do magazynu — odcinek 2 niedokończony
**Źródło:** `OSTATECZNA_WERSJA_HUB_RUROCIAG.md §7`: "Hub → storage jako osobny etap (hub_road_trips) — niski priorytet, nie zaczęte"
**Problem:** tabela `hub_road_trips` istnieje w migracji `etap6_logistics_v2.sql`, ale jej użycie w ticku nie jest w pełni podpięte. Outbound pipeline działa przez `well_pipelines` z `leg='outbound'` i `well_id=0`, ale drogowy outbound jest tylko szkieletem.
**Poprawa w OilEmpire:** zaimplementować `HubRoadTripService` analogicznie do `RoadTransportService`. Hub z `outbound_transport_type='ciezarowki'` wysyła kursy z bufora do magazynu.

### 2.2.3. Brak modelu floty — ciężarówki jako "nieskończona flota"
**Źródło:** `RoadTransportService.php` — brak limitu równoległych kursów
**Problem:** gracz może wysłać nieograniczoną liczbę kursów jednocześnie. Floty jako bytu nie ma — `well_road_configs` definiuje tylko typ ciężarówki, nie liczbę pojazdów.
**Poprawa w OilEmpire:** dodać tabelę `fleets` z liczbą posiadanych ciężarówek per typ. `max_concurrent_trips = fleet_size`. Zakup/wynajem floty przez FTS.

### 2.2.4. Sabotaż rurociągów — nie zaimplementowany
**Źródło:** `README_rurociag.md` (koniec): "Mechanizm sabotażu rurociągu — celowe zniszczenie przez gracza — brak w obecnej implementacji, wymaga osobnego projektowania"
**Problem:** `SabotageService` ma target `ROAD_TRANSPORT` i `HUB`, ale nie ma targetu `PIPELINE`. Kod w `PipelineSection.php` ma tylko losowe incydenty, nie ma podpięcia sabotażu.
**Poprawa w OilEmpire:** dodać `SabotageService::TARGET_PIPELINE` + `CONTEXT_PIPELINE_VALVE`. Efekt: `condition_pct −20`, `transport_loss +5`, `status→leak`.

### 2.2.5. Stanowisko `pipeline_logistics_specialist` — martwy kod?
**Źródło:** notka "Obsada rurociągów" (2026-07-19), `PipelineSection.php`
**Problem:** kod mówi o `pipeline_logistics_specialist` redukującym `transport_loss`, ale w `PipelineSection::process()` nie ma odczytu tego efektu z `pipelineStaffingByPipeline`. Jest tylko `engineer_degradation_mult` i `engineer_incident_mult`.
**Poprawa w OilEmpire:** dodać `logistics_specialist_loss_mult` do `LogisticsStaffingService` i zastosować go w ticku rurociągu.

### 2.2.6. Próg `min_load_bbl` dla transportu drogowego — nieaktywny
**Źródło:** `TransportConfigService.php`: defaults dla `ciezarowki` mają `min_load_bbl => 0.0`
**Problem:** bufor drogowy (`road_buffer_bbl`) istnieje, ale `min_load_bbl=0` oznacza wysyłkę natychmiastową (stary model). Nie ma konfiguracji progu dla ciężarówek.
**Poprawa w OilEmpire:** ustawić `min_load_bbl` dla `ciezarowki` na `trip_capacity_bbl` (domyślnie 25 bbl). Dodać do panelu admina.

---

## 2.3. BUGI I BŁĘDY (znalezione w kodzie i historii)

### 2.3.1. IDOR w `getHubDetail` (Runda 3, 2026-07-03)
**Źródło:** notka "Runda 3 (19 bugów): m.in. IDOR w getHubDetail"
**Problem:** brak sprawdzenia, czy gracz ma prawo widzieć szczegóły huba (np. cudzy hub prywatny).
**Wniosek dla OilEmpire:** każdy endpoint odczytu huba musi sprawdzać `player_id` LUB `tenant_player_id` LUB `player_id=0` (systemowy).

### 2.3.2. Wskrzeszanie rurociągów przez suspend/resume (Runda 4, 2026-07-03)
**Źródło:** notka "wskrzeszanie rurociągów przez suspend/resume"
**Problem:** suspend→resume na uszkodzonym rurociągu (`status=damaged`) przywracało go do `active` z pełną kondycją.
**Poprawka w legacy:** `togglePipeline` sprawdza status przed wznowieniem.
**Wniosek dla OilEmpire:** maszyna stanów rurociągu musi mieć jawne przejścia. `suspended → active` tylko gdy kondycja >0. `damaged` NIE MOŻE przejść w `suspended`.

### 2.3.3. Podwójna utrata ropy przy spillu (Runda 4)
**Źródło:** notka "podwójna utrata ropy przy spillu"
**Problem:** wyciek huba odejmował ropę zarówno z bufora huba, jak i z magazynu gracza (podwójne księgowanie).
**Wniosek dla OilEmpire:** `HubTickService::calcConditionLoss()` musi operować wyłącznie na wolumenie `processed` w danym ticku, nie na magazynie gracza.

### 2.3.4. Darmowe awarie mechaniczne (Runda 4)
**Źródło:** notka "darmowe awarie mechaniczne"
**Problem:** incydenty rurociągów nie naliczały OPEX od awarii — `status=damaged` przerywał pętlę kosztów.
**Wniosek dla OilEmpire:** OPEX rurociągu naliczany zawsze, nawet przy `status=damaged` (koszty stałe). Tylko `disabled` całkowicie wyłącza OPEX.

### 2.3.5. Precyzja `condition_pct` — DECIMAL(5,2) → DECIMAL(8,4) (Runda 4)
**Źródło:** `tick_bugfix_round4.sql`: `well_pipelines.condition_pct → DECIMAL(8,4)`, `PipelineSection.php` (komentarz: "przy round(,1)/DECIMAL(5,2) rurociągi nigdy się nie zużywały")
**Problem:** przy ticku 5-minutowym degradacja 0.0017–0.0042/tick zaokrąglała się do 0 przy DECIMAL(5,2). Rurociągi były nieśmiertelne.
**Wniosek dla OilEmpire:** wszystkie kolumny kumulatywne (condition, wear, buffer) muszą mieć precyzję co najmniej 4 miejsc po przecinku. Weryfikacja: test 1000 ticków na light pipeline — condition MUSI spaść.

### 2.3.6. Uszkodzony rurociąg leg-1 nie przełącza na ciężarówki (Runda 4)
**Źródło:** notka "uszkodzony rurociąg leg-1 zatrzymuje produkcję zamiast cicho jeździć ciężarówkami"
**Problem:** gdy inbound pipeline miał `status=damaged`, odwiert nie produkował (ropę blokował uszkodzony pipeline), zamiast automatycznie przełączyć się na `transport_type=ciezarowki`.
**Wniosek dla OilEmpire:** fallback transportowy musi być automatyczny. Gdy inbound pipeline nie działa (damaged/disabled), odwiert przełącza się na ciężarówki. Gracz dostaje powiadomienie `critical`.

### 2.3.7. Ciche kasowanie rejsów morskich → status `lost` (Runda 4)
**Źródło:** notki "ciche kasowanie rejsów morskich → status lost" + "zamykanie transportu bez kasowania historii" (2026-07-09)
**Problem:** `MarineDeliveryService::purgeOrphanActiveForPlayer()` robił `DELETE` zamiast `UPDATE status='lost'`. Dane znikały bez śladu.
**Wniosek dla OilEmpire:** NIGDY nie kasuj rekordów transportowych. Wszystkie zakończenia: `delivered`, `lost`, `abandoned` przez UPDATE statusu.

### 2.3.8. Potrójna opłata offshore (Runda 4)
**Źródło:** notka "potrójna opłata offshore"
**Problem:** koszt transportu morskiego był naliczany 3 razy: przy wysyłce, przy dotarciu do portu, przy rozładunku.
**Wniosek dla OilEmpire:** każdy koszt transportu naliczany RAZ w momencie wysyłki (FTS `TYPE_MARINE_SHIPMENT`). Handling fee portu przy rozładunku to osobna transakcja.

### 2.3.9. Port `closed` wznawia się automatycznie (Runda 4)
**Źródło:** notka "port closed wznawia się automatycznie"
**Problem:** port zamknięty przez admina (`status='closed'`) samoczynnie wracał do `active` w następnym ticku.
**Wniosek dla OilEmpire:** zmiana statusu portu przez admina musi być trwała. Tick NIE zmienia `closed → active`. Tylko admin może ponownie otworzyć port.

### 2.3.10. Road trip storage overflow przed dostawą do huba (M4 fix)
**Źródło:** `WellRoadTripSection.php` — komentarz "M4: split delivered oil into hub-assigned wells and hubless wells"
**Problem:** cap magazynu był aplikowany do wszystkich kursów, także tych zmierzających do huba. Ropa przeznaczona dla huba była "overflow" zanim w ogóle dotarła do huba (hub ma własny bufor!).
**Poprawka:** `WellRoadTripSection::process()` — kursy do huba omijają cap magazynu. Tylko kursy bez huba (fallback) są capowane.
**Wniosek dla OilEmpire:** implementować od razu z tym rozróżnieniem.

### 2.3.11. Osierocone kursy `crediting` po crashu ticka (M3 recovery)
**Źródło:** `RoadTransportService.php` — `processCompletedTrips()` recovery pętla
**Problem:** jeśli tick crashnął po `UPDATE status='crediting'` ale przed `confirmCreditedTrips()`, kursy zostawały w `crediting` na zawsze. Ropa ani nie trafiała do magazynu, ani nie była ponownie liczona.
**Poprawka:** recovery na początku `processCompletedTrips()` wczytuje osierocone `crediting` i dolicza je ponownie.
**Wniosek dla OilEmpire:** implementować z recovery od razu.

### 2.3.12. Wyścig: równoległe przypisanie operatora + wyłączenie rurociągu
**Źródło:** notka "regresje dla polityki busy, statusów rurociągu, kompaktowego payloadu oraz wyścigu przypisania z równoległym wyłączeniem rurociągu" (2026-07-19)
**Problem:** `EmployeeAssignmentService` mógł przypisać pracownika do rurociągu, który w międzyczasie został wyłączony przez inny request.
**Wniosek dla OilEmpire:** każda operacja przypisania musi blokować cel (`SELECT ... FOR UPDATE` na `well_pipelines`) i ponownie sprawdzać status po blokadzie.

### 2.3.13. N+1 w ticku: wielokrotne zapytania o ochronę per gracz
**Źródło:** notka "eliminacja N+1 — ongoingDropCache, memoizacja portów/incydentów hubów" (Runda 4)
**Problem:** `ProtectionService::getActiveProtection()` wywoływane per rurociąg, per kurs — generowało setki zapytań na gracza.
**Poprawka:** prefetch `getActiveProtections()` z batchowym `WHERE target_id IN (...)`.
**Wniosek dla OilEmpire:** wszystkie odczyty w ticku muszą być batchowe. Jeden `SELECT ... WHERE player_id=?` na encję, nie N zapytań.

---

## 2.4. WNIOSKI KOŃCOWE — PRIORYTETOWE DECYZJE PRZED KODOWANIEM

### P0 — Architektura (blokuje start)

1. **Tick 60s vs real-time trips:** OilEmpire ma tick 60s, a nie real-time. Kursy drogowe w legacy miały ETA w minutach/godzinach. **Decyzja:** zachować real-time ETA dla kursów (timer niezależny od ticka), ale rozliczać tylko w Fazie 2 ticka przez `eta_at <= NOW()`. Czas podróży skalować przez tick: `trip_ticks = ceil(trip_hours * 60 / tick_interval_seconds)`.

2. **Delta-time dla transportu:** przy ticku 60s `deltaHours = 1/60` dla wzorów per-godzinnych. Wszystkie formuły degradacji/zużycia muszą być przemnożone przez `deltaHours`. **Decyzja:** używać `deltaHours = (now - last_calc_at) / 3600` jak w §23.3.

3. **FTS double-entry dla każdej operacji logistycznej:** legacy używało bezpośrednich `UPDATE players SET cash = cash - ?`. **Decyzja:** każdy koszt (OPEX huba, kurs drogowy, budowa rurociągu) przez `FinancialTransactionService` z `reason_code` i `idempotency_key`.

### P1 — Projektowe (przed implementacją)

4. **Flota ciężarówek jako limit concurrent trips:** bez limitu floty transport drogowy jest nieskończenie skalowalny. **Decyzja:** dodać tabelę `fleets` (player_id, truck_type, count). `max_concurrent_trips = SUM(count)` per player. Zakup floty: FTS + `reason_code=fleet_purchase`.

5. **Hub outbound jako osobny byt w ticku:** obecnie outbound pipeline działa per hub (`well_id=0`), ale `hub_road_trips` nie ma serwisu. **Decyzja:** stworzyć `HubOutboundTransportService` obsługujący oba typy outbound: rurociąg (przez `PipelineSection`) i drogę (przez nowy `HubRoadTripService`).

6. **Sabotaż rurociągów:** dodać do `SabotageService`: `TARGET_PIPELINE` + opcje `valve_sabotage` (zniszczenie zaworu), `pressure_spike` (przeciążenie). Powiązać z §7 specyfikacji.

### P2 — Balansowe (do kalibracji)

7. **Kalibracja `BASE_INCIDENT_CHANCE_PER_HOUR`:** legacy używa 0.015 (1.5% na kurs na godzinę). Przy ticku 60s i `trip_hours=2` daje to ~3% szansy na incydent na kurs. Dla 10 kursów/tick: ~26% szansy że przynajmniej jeden ma incydent. **Decyzja:** zachować 0.015 jako start, monitorować telemetrię (§29.3).

8. **Koszty ochrony vs straty:** legacy: eskorta 75k PLN/60min, a kurs standardowy 500 PLN. Ochrona opłaca się tylko przy bardzo dużym wolumenie lub w niebezpiecznych regionach. **Decyzja:** zachować proporcje. Dodać kalkulator opłacalności w UI ("Ochrona zwróci się po X kursach").

9. **Próg `min_load_bbl` dla ciężarówek:** obecnie 0 = stary model. **Decyzja:** ustawić domyślnie `trip_capacity_bbl` (25 bbl dla standard). Gracz może zmienić per odwiert.

### P3 — Jakościowe (przed produkcyjnym wdrożeniem)

10. **Testy akceptacyjne logistyki** (uzupełnienie §23.7):
    - Kurs dociera → ropa w magazynie (happy path)
    - Kurs okradziony → 0 w magazynie, log incydentu
    - Hub przeciążony → overflow_lost_bbl
    - Rurociąg w budowie → ciężarówki jako fallback
    - Crash ticka po `crediting` → recovery nie gubi ropy
    - Sabotaż cysterny → opóźnienie + częściowa strata
    - Ochrona huba → zredukowane ryzyko incydentu
    - Przepięcie odwiertu → zatrzymanie produkcji na 2 ticki, OPEX naliczony

11. **Obserwowalność logistyki** (§29.3): dodać metryki: `logistics_opex_total`, `logistics_lost_bbl`, `hub_overload_count`, `trip_completed_count`, `pipeline_condition_avg` — per tick, per gracz.

12. **Migracja z legacy:** NIE przenosić tabel 1:1. Zaprojektować nowy schemat w konwencji Laravel (migrations, snake_case, `BIGINT` dla pieniędzy w groszach, `DATETIME(3)`). Dane testowe przez seeder, nie przez SQL dump.