# Logistics P1 — Implementacja (P1.1 + P1.2)

Data: 2026-05-24

## Kontekst

Plik opisuje co zostalo faktycznie wdrozone w ramach sesji P1 (na podstawie luki
miedzy `LOGISTICS_HUBS_README_ulepszony.md` a kodem).

Zrodlo referencyjne: `LOGISTICS_HUBS_README_ulepszony.md`

---

## P1.1 — Timer budowy rurociagu

### Problem
Rurociag pojawial sie jako `active` natychmiast po `purchasePipeline()`.
Brak mechanizmu odliczania czasu budowy.

### Schemat DB (kolumny dodane do `well_pipelines`)

```sql
build_started_at  DATETIME DEFAULT NULL
build_finish_at   DATETIME DEFAULT NULL
```

Dodane przez `ensureColumn()` w `WellPipelineService::ensureSchema()`.

### `WellPipelineService.php` — nowe metody

| Metoda | Opis |
|--------|------|
| `purchasePipeline(int $playerId, int $wellId, string $type)` | Odejmuje koszt atomicznie (`cash >= cost`), tworzy pipeline `status='building'`, ustawia `build_finish_at = NOW() + INTERVAL N HOUR`. Zwraca `[success, pipeline_type, build_cost, build_hours, build_finish_at]`. |
| `completeBuildingPipelines(int $playerId)` | Szuka `status='building' AND build_finish_at <= NOW()`, przelacza na `'active'`, zapisuje zdarzenie, zwraca liste ukonczonych. |
| `getBuildingForPlayer(int $playerId)` | Zwraca rurociagi `status='building'` z `seconds_remaining` (MySQL `TIMESTAMPDIFF`). |

### Dodanie `build_hours` do `PIPELINE_DEFAULTS`

```php
'light'    => [..., 'build_hours' => 4],
'standard' => [..., 'build_hours' => 8],
'heavy'    => [..., 'build_hours' => 16],
```

### Bugfix: `getProfile()` nie zwracalo `build_hours`

`purchasePipeline()` crashowal z `DateMalformedStringException` bo `getProfile()`
nie mial `build_hours` w tablicy return. Naprawiono.

### `Tick/PipelineSection.php` — wywolanie completeBuildingPipelines

Na poczatku `process()` wywolywane jest `completeBuildingPipelines($playerId)`.
Degradacja wyklucza `status='building'` przez `status IN ('active','degraded','critical')`.

### `PipelineApi.php` (nowy plik)

AJAX endpoint: `/pipeline-api.php`

| Action | Method | Opis |
|--------|--------|------|
| `buy_pipeline` | POST | Wywoluje `purchasePipeline()` |
| `building_pipelines` | GET | Wywoluje `getBuildingForPlayer()` |
| `pipeline_status` | GET | Wywoluje `getByPlayerAndWellIds()` |
| `pipeline_profiles` | GET | Zwraca profile 3 typow (koszt/godziny/etykieta) |

### `logistics.php` — dane widoku

- `$pipeline['seconds_remaining']` obliczane per rurociag `status='building'`
- `buildingPipelines` — przefiltrowana lista dla frontendu

### `templates/views/logistics/main.php`

- Karta budujacego sie rurociagu: pasek postepu + countdown (`pipeline-countdown`)
- Modal zakupu: `#pipeline-buy-modal` z wyborem typu (light/standard/heavy)
- JS: `openPipelineBuyModal()`, `confirmPipelinePurchase()`, countdown z reload

### `lang/pl/logistics.php` — nowe klucze (P1.1)

```
logistics.pipeline.status_building
logistics.pipeline.building_label_cost
logistics.pipeline.building_label_finish
logistics.pipeline.building_label_remaining
logistics.pipeline.buy_modal_title
logistics.pipeline.buy_confirm_btn
logistics.pipeline.buy_label_hours
pipeline.err_insufficient_funds
pipeline.err_already_exists
pipeline.err_offshore
pipeline.ok_build_started
pipeline.event_build_started
pipeline.event_build_complete
tick.notify.pipeline_build_complete
```

### Testy MySQL (`MySqlWellPipelineServiceTest.php`) — 7 nowych

- `testPurchasePipelineCreatesBuildingPipelineAndDeductsCash`
- `testPurchasePipelineFailsWhenInsufficientFunds`
- `testPurchasePipelineFailsWhenPipelineAlreadyExists`
- `testCompleteBuildingPipelinesFlipsStatusToActiveWhenTimeElapsed`
- `testCompleteBuildingPipelinesDoesNotFlipWhenTimeNotElapsed`
- `testGetBuildingForPlayerReturnsBuildingPipelinesWithSecondsRemaining`
- `testGetBuildingForPlayerIgnoresActivePipelines`

---

## P1.2 — Kursy drogowe jako encje czasowe

### Problem
`ciezarowki` uzywalo bezstanowego `processTick()` — ropa pojawia sie w magazynie
natychmiastowo. Brak reprezentacji czasu dowozu.

### Schemat DB — nowa tabela `well_road_trips`

```sql
CREATE TABLE well_road_trips (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    player_id            INT NOT NULL,
    well_id              INT NOT NULL,
    volume_bbl           DECIMAL(12,4) NOT NULL,
    delivered_bbl        DECIMAL(12,4) NOT NULL DEFAULT 0,
    truck_type           ENUM('standard','heavy','armored') DEFAULT 'standard',
    trips_count          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    trip_hours           TINYINT UNSIGNED NOT NULL DEFAULT 2,
    cost                 DECIMAL(10,2) NOT NULL DEFAULT 0,
    incident_risk_mult   DECIMAL(6,3) NOT NULL DEFAULT 1.000,
    political_risk_level TINYINT UNSIGNED NOT NULL DEFAULT 1,
    status               ENUM('in_transit','delivered','lost') DEFAULT 'in_transit',
    departure_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    eta_at               DATETIME NOT NULL,
    arrived_at           DATETIME NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)
```

Migracja brakujacych kolumn (istniejace tabele): przez `ensureRoadTripColumn()`.

### `TRUCK_DEFAULTS` — nowe pole `trip_hours`

```php
'standard' => [..., 'trip_hours' => 2],
'heavy'    => [..., 'trip_hours' => 3],
'armored'  => [..., 'trip_hours' => 2],
```

### `RoadTransportService.php` — nowe metody publiczne

| Metoda | Opis |
|--------|------|
| `dispatchTrips($playerId, $wellId, $volumeBbl, $config, $politicalRisk)` | MySQL only. Tworzy rekord w `well_road_trips`. ETA obliczone przez `DATE_ADD(NOW(), INTERVAL N HOUR)` (unika rozbieznosci stref czasowych PHP vs MySQL). Zwraca `[trips_count, volume_bbl, cost, truck_type, eta_at]`. |
| `processCompletedTrips($playerId, $hseBonus)` | Pobiera kursy `status='in_transit' AND eta_at <= NOW()`, stosuje incydenty per-kurs przez `applyTripIncidents()`, aktualizuje `status='delivered'` i `delivered_bbl`. Zwraca `[delivered_bbl, lost_bbl, completed_count]`. |
| `getActiveTripsForPlayer($playerId)` | Lista kursow `in_transit` z JOIN wells i `TIMESTAMPDIFF` na `seconds_remaining`. |

### Metody prywatne

| Metoda | Opis |
|--------|------|
| `applyTripIncidents(...)` | Stosuje incydenty per-kurs (ta sama logika co `processTick()`, ale uzywajac `trip_hours` zamiast `deltaHours`). |
| `ensureRoadTripColumn($column, $definition)` | Migracja — sprawdza `information_schema.COLUMNS`, dodaje kolumne jesli brak. |

### `Tick/WellProductionHandler.php` — rozgalezienie ciezarowki

```
MySQL → dispatchTrips() + return early  (ropa w tranzycie)
SQLite → processTick() jak dotychczas  (fallback bezstanowy, testy jednostkowe)
```

Warunek: `$this->ctx->db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite'`

Przy dyspozycji:
- `finTransport += $dispatch['cost']` (koszt naliczany przy wysylce)
- `roadInTransitBbl += $actual`
- `return` — ropa NIE trafia do magazynu w tej chwili

### `Tick/WellLoopSection.php`

Dodano pole `public float $roadInTransitBbl = 0.0` (analogicznie do `marineInTransitBbl`).

### `Tick/WellRoadTripSection.php` (nowy plik)

Sekcja tick wywoływana per gracz, po `MarineDeliverySection`, przed `PortSection`.

- Wywoluje `RoadTransportService::processCompletedTrips()`
- Kredytuje `delivered_bbl` do `$currentStorage` (z ograniczeniem do wolnego miejsca)
- Eksponuje: `deliveredBbl`, `lostBbl`, `completedCount`

### `Tick/PlayersSection.php` — sekcja 3c

```php
// 3c. KURSY DROGOWE - ukonczone dostawy ciezarowkami (P1.2)
$roadSvc        = new RoadTransportService($db);
$roadTripSec    = new WellRoadTripSection($db, $now);
$currentStorage = $roadTripSec->process(...);
// aktualizuje wellLoop->finBbl, finRevenue, transportEventLossBbl, finLossBbl
```

### `logistics.php`

Laduje `$activeRoadTrips = $roadSvc->getActiveTripsForPlayer($playerId)` i przekazuje do widoku.

### `templates/views/logistics/main.php`

Sekcja `#logistics-road-trips-heading` — tabela z kursami w tranzycie:
- kolumny: odwiert / wolumen / kursow / typ / ETA / pozostalo
- `road-trip-countdown` z atrybutem `data-seconds`, aktualizacja co 30s, reload po dotarciu

### `lang/pl/logistics.php` — nowe klucze (P1.2)

```
logistics.road_trips.section_title
logistics.road_trips.empty
logistics.road_trips.col_well
logistics.road_trips.col_volume
logistics.road_trips.col_trips
logistics.road_trips.col_truck
logistics.road_trips.col_eta
logistics.road_trips.col_remaining
logistics.road_trips.truck_standard
logistics.road_trips.truck_heavy
logistics.road_trips.truck_armored
```

### Testy MySQL (`MySqlRoadTransportServiceTest.php`) — 9 nowych

- `testDispatchTripsCreatesInTransitRecord`
- `testDispatchTripsUsesHeavyConfigAndTripHours`
- `testDispatchTripsWithZeroVolumeCreatesNoRecord`
- `testProcessCompletedTripsDeliversOilWhenEtaPassed`
- `testProcessCompletedTripsIgnoresFutureEta`
- `testProcessCompletedTripsAggregatesMultipleWells`
- `testGetActiveTripsForPlayerReturnsInTransitWithSecondsRemaining`
- `testGetActiveTripsForPlayerIgnoresDeliveredTrips`
- `testEnsureSchemaCreatesWellRoadTripsTable`

### `MySqlIntegrationTestCase.php`

Cleanup `well_road_trips` dodany do `cleanupTrackedIds()`.

---

## Wyniki testow po wdrozeniu P1.1 + P1.2

| Suite | Testy | Status |
|-------|-------|--------|
| MySQL integration | 95/95 | OK |
| Unit + SQLite | 54/56 | OK (2 pre-existing failures w `TechnicalHubTasksTest`, nie nasze) |

---

## Pozostale do wdrozenia (z README)

### P1.3 — Timer relinkowania huba
- `HubAssignmentService::transferWell()` → `status='relinking'`, timer
- Tick: zakonczenie relinkowania
- Template: countdown w karcie huba

### P2.1 — `hub_id` w dostawach morskich
- `marine_deliveries.hub_id` jako cel dostawy

### P2.2 — UI zakupu rurociagu
- Modal juz istnieje (P1.1), endpoint juz istnieje
- Finalizacja flow (walidacja odwiertu offshore, refresh listy)

### P2.3 — Hub → storage jako wymagany etap
- Bufor hubowy jako obowiazkowy krok przed magazynem
- `hub_road_trips` (drugie polaczenie) lub rozszerzenie `well_road_trips`

### P3 — Powiadomienia, dzienne podsumowanie finansowe, dialogi potwierdzen
