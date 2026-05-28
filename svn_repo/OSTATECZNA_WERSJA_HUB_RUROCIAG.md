# Ostateczna wersja — System Hubów i Rurociągów

Data aktualizacji: 2026-05-26

Plik jest kanonicznym opisem stanu wdrożenia po zakończeniu etapów P1.1–P1.3 oraz cyklu kosztowego (Etap 2–4).
Zastępuje i uaktualnia: `LOGISTICS_HUBS_README_V2.md`, `README_rurociag.md`, `LOGISTICS_P1_IMPLEMENTATION.md`.

---

## 1. Architektura ogólna

System logistyki opiera się na dwóch powiązanych podsystemach:

```
Odwiert (gracz)
    │
    ├── Rurociąg (well_pipelines)          ← odwiert lądowy, wymagany hub
    │       status: building → active
    │
    └── Hub logistyczny (logistics_hubs)   ← infrastruktura systemowa (player_id = 0)
            └── Przypisanie (logistics_hub_assignments)
                    status: active / detached
```

Huby są **infrastrukturą systemową** — gracz ich nie posiada, tylko przypisuje do nich swoje odwierty.
Model `new / used / rental` opisuje sposób nabycia huba przez system, nie przez gracza.

---

## 2. System hubów logistycznych

### 2.1 Model danych

| Tabela | Opis |
|--------|------|
| `logistics_hubs` | Huba (player_id = 0 = systemowy) |
| `logistics_hub_assignments` | Przypisania odwiertów do hubów |
| `logistics_hub_tick_stats` | Statystyki ticków huba |
| `logistics_hub_events` | Zdarzenia / logi zdarzeń huba |

Kluczowe kolumny `logistics_hubs`:

| Kolumna | Typ | Opis |
|---------|-----|------|
| `acquisition_type` | `new / used / rental` | Typ nabycia huba |
| `slot_limit` | int | Maks. liczba podpiętych odwiertów |
| `condition_pct` | decimal | Kondycja huba (0–100) |
| `opex_per_tick` | decimal | Koszt OPEX na tick |
| `lease_fee_per_tick` | decimal | Czynsz na slot na tick (tylko `rental`) |
| `status` | enum | `active / paused / damaged / disabled / building` |

Kluczowe kolumny `logistics_hub_assignments`:

| Kolumna | Typ | Opis |
|---------|-----|------|
| `status` | `active / detached` | Stan przypisania |
| `access_fee_paid` | decimal | Jednorazowa opłata dostępowa (zapisana przy przypisaniu) |
| `cooldown_until` | datetime | Czas końca cooldownu po odpieniu |
| `assigned_at` | datetime | Data przypisania |
| `detached_at` | datetime | Data odpiecia |

### 2.2 Typy akwizycji i opłaty

#### Jednorazowa opłata dostępowa (przy przypisaniu odwiertu)

```
slot_cost = opex_per_tick / slot_limit

new    → access_fee = slot_cost × 5
used   → access_fee = slot_cost × 8
rental → access_fee = lease_fee_per_tick × 3
```

Opłata jest odliczana od gotówki gracza przed zapisem do DB.
Jeśli INSERT się nie powiedzie — opłata jest natychmiast zwracana (refund w bloku catch).
Wartość jest zapisywana w kolumnie `access_fee_paid`.

#### Opłata cykliczna (per tick)

- **OPEX**: `opex_per_tick` — naliczany każdemu graczowi, który ma aktywne przypisanie do huba
- **Czynsz** (tylko `rental`): `lease_fee_per_tick × player_well_count` — naliczany w `Tick/WellHubSection.php`

### 2.3 Operacje przypisania

Wszystkie operacje w `HubAssignmentService`:

| Metoda | Opis |
|--------|------|
| `assignWell(playerId, hubId, wellId)` | Przypisuje odwiert do huba, pobiera opłatę dostępową |
| `detachWell(playerId, wellId)` | Odpina odwiert, ustawia `cooldown_until = NOW() + 4h` |
| `transferWell(playerId, wellId, newHubId)` | Odpina od starego, tworzy nowe przypisanie do nowego huba |

Walidacje przed przypisaniem (`HubAssignmentValidationTrait`):

- odwiert należy do gracza
- odwiert i hub mają ten sam `region_id`
- hub ma wolne sloty (`assigned_count < slot_limit`)
- hub jest aktywny
- odwiert nie jest już aktywnie przypisany
- brak aktywnego cooldownu na odwiercie
- gracz ma wystarczające środki na opłatę dostępową

### 2.4 Cooldown po odpieniu

Po każdym odpieniu lub transferze ustawiany jest `cooldown_until = NOW() + 4 godziny`.

Cooldown **blokuje ponowne przypisanie** — `assignWell()` zwraca:

```php
['success' => false, 'error' => 'cooldown_active', 'cooldown_remaining_s' => N]
```

`HubApi.php` zwraca do frontendu dokładny czas oczekiwania (np. "3h 42min").

#### Timer cooldownu w UI (P1.3)

Na liście odwiertów bez przypisania (`getUnassignedWells()`):

```sql
(SELECT a.cooldown_until
   FROM logistics_hub_assignments a
  WHERE a.well_id = w.id
    AND a.status  = 'detached'
    AND a.cooldown_until > NOW()
  ORDER BY a.cooldown_until DESC
  LIMIT 1
) AS cooldown_until
```

W widoku PHP obliczany jest czas pozostały i renderowany element:

```html
<span class="hub-cooldown-badge" data-cooldown-until="YYYY-MM-DD HH:MM:SS">
    ⏳ 3h 42min
</span>
```

JavaScript (`initCooldownTimers()`) odświeża badge co 60 sekund. Po wygaśnięciu cooldownu strona przeładowuje się automatycznie i przyciski przypisania stają się widoczne.

### 2.5 Tick huba

`HubTickService::processTick()` oblicza na podstawie kondycji i trybu pracy:

- `processed_bbl` — przetworzona ropa
- `buffered_bbl` — ropa w buforze
- `lost_bbl` — stracona ropa
- `wear_added` — przyrost zużycia
- `new_condition` — nowa kondycja
- `new_status` — nowy status huba

`persistTickResult()` zapisuje wyniki do `logistics_hubs` i `logistics_hub_tick_stats`.

### 2.6 Ostrzeżenie o kondycji

Jeśli `condition_pct < 40%` przy przypisaniu — `assignWell()` zwraca:

```php
['success' => true, 'warning' => 'condition_low', 'access_fee' => N]
```

Frontend wyświetla ostrzeżenie w modalu potwierdzenia.

---

## 3. System rurociągów

### 3.1 Model danych

| Tabela | Opis |
|--------|------|
| `well_pipelines` | Rurociąg przypisany do odwiertu (jeden per odwiert) |
| `well_pipeline_events` | Zdarzenia rurociągu |
| `well_pipeline_tick_stats` | Statystyki ticków rurociągu |

Kluczowe kolumny `well_pipelines`:

| Kolumna | Typ | Opis |
|---------|-----|------|
| `pipeline_type` | `light / standard / heavy` | Typ rurociągu |
| `status` | enum | `building / active / degraded / critical / damaged / disabled` |
| `condition_pct` | decimal | Kondycja rurociągu (0–100) |
| `transport_loss` | decimal | Procent strat transportowych |
| `hub_id` | int | Hub, przez który jest podpięty (z aktywnego przypisania) |
| `build_started_at` | datetime | Start budowy |
| `build_finish_at` | datetime | Planowane zakończenie budowy |
| `build_cost` | decimal | Koszt budowy |

### 3.2 Typy rurociągów

| Typ | Koszt budowy | Czas budowy | OPEX/tick | Degradacja/h | Ryzyko incydentu |
|-----|-------------|-------------|-----------|--------------|------------------|
| `light` | 11 000 | 4h | 95,00 | 0,070 | ×1,25 |
| `standard` | 18 000 | 8h | 140,00 | 0,050 | ×1,00 |
| `heavy` | 28 000 | 16h | 190,00 | 0,035 | ×0,85 |

### 3.3 Zakup rurociągu (`purchasePipeline`)

Warunki konieczne do zakupu:

1. Odwiert należy do gracza i nie ma statusu `sold` / `disabled`
2. Odwiert jest lądowy (`well_type = 'onshore'`)
3. Odwiert ma **aktywne przypisanie do huba** (`hub_required` w razie braku)
4. Rurociąg dla tego odwiertu jeszcze nie istnieje (`pipeline_already_exists` w razie duplikatu)
5. Gracz ma wystarczające środki (`insufficient_funds` w razie braku)

Atomiczne odjęcie gotówki:

```sql
UPDATE players SET cash = cash - ? WHERE id = ? AND cash >= ?
```

Rurociąg tworzony jest ze statusem `building` i ustawionym `build_finish_at = NOW() + N HOUR`.

Zwracany wynik:

```php
['success' => true, 'pipeline_type' => 'standard', 'build_cost' => 18000.0, 'build_hours' => 8, 'build_finish_at' => '...']
```

### 3.4 Proces budowy (P1.1)

Rurociąg po zakupie ma status `building`. Podczas budowy:

- nie przetwarza ropy
- tick degradacji jest pomijany
- `getBuildingForPlayer()` zwraca listę z `seconds_remaining`
- w UI wyświetlany jest pasek postępu i countdown

Zakończenie budowy:

```php
$completed = $pipelineSvc->completeBuildingPipelines($playerId);
```

Metoda szuka `status='building' AND build_finish_at <= NOW()` i przełącza na `status='active'`.

W ticku `PipelineSection::process()` wywołuje `completeBuildingPipelines()` na początku, przed degradacją.

### 3.5 Stany rurociągu

| Status | Opis |
|--------|------|
| `building` | W trakcie budowy — nie przetwarza ropy |
| `active` | Sprawny — pełna przepustowość |
| `degraded` | Kondycja 40–60% — zmniejszona przepustowość |
| `critical` | Kondycja < 40% — duże straty, ryzyko awarii |
| `damaged` | Po incydencie — wymaga naprawy |
| `disabled` | Wyłączony przez gracza |

### 3.6 Wymaganie huba przed zakupem rurociągu

Rurociąg lądowy można zakupić **tylko wtedy, gdy odwiert ma aktywne przypisanie do huba**.

Uzasadnienie: rurociąg łączy odwiert z hubem. Bez huba nie ma do czego podłączyć rurociągu.

---

## 4. Pełny cykl życia odwiertu

### Krok po kroku (happy path)

```
1. Zakup odwiertu (WellShop)
   └── transport_type = 'nieustawiony'
   └── cash -= base_cost

2. Przypisanie do huba (HubAssignmentService::assignWell)
   └── walidacja: region, sloty, brak cooldownu, środki
   └── cash -= access_fee
   └── INSERT logistics_hub_assignments (status='active', access_fee_paid=N)

3. Zakup rurociągu (WellPipelineService::purchasePipeline)
   └── wymagane: aktywne przypisanie do huba
   └── cash -= build_cost
   └── INSERT well_pipelines (status='building', hub_id=N, build_finish_at=NOW()+Nh)

4. Ukończenie budowy (completeBuildingPipelines)
   └── automatycznie w ticku lub ręcznie po czasie
   └── UPDATE well_pipelines SET status='active'

5. Tick huba (HubTickService::processTick + persistTickResult)
   └── przetwarzanie ropy, zużycie kondycji, stats
   └── OPEX i czynsz (rental) odliczane w WellHubSection

6. Odpięcie od huba (HubAssignmentService::detachWell)
   └── UPDATE assignments SET status='detached', cooldown_until=NOW()+4h

7. Cooldown (4 godziny)
   └── assignWell → 'cooldown_active' + cooldown_remaining_s
   └── UI: badge z odliczaniem, reload po wygaśnięciu
```

### Stany `transport_type` w `wells`

| Wartość | Znaczenie |
|---------|-----------|
| `nieustawiony` | Świeżo zakupiony odwiert — transport nie wybrany |
| `rurociag` | Transport przez rurociąg (wymaga wpisu w `well_pipelines`) |
| `ciezarowki` | Transport drogowy (kursy w `well_road_trips`) |
| `tankowiec` | Transport morski (offshore) |

---

## 5. Frontend i API

### Modalne potwierdzenia kosztów

Przed każdym przypisaniem JS wyświetla modal z dokładnym zestawieniem kosztów:

- **Opłata dostępowa** (jednorazowa) — wartość z `acq_access_fee` w danych huba
- **OPEX** (per tick) — opex_per_tick / slot_limit
- **Czynsz** (per tick, tylko rental) — lease_fee_per_tick

### Endpointy AJAX

| Endpoint | Action | Opis |
|----------|--------|------|
| `hub-api.php` | `assign_well` | Przypisanie odwiertu, pobranie opłaty |
| `hub-api.php` | `detach_well` | Odpięcie odwiertu |
| `hub-api.php` | `transfer_well` | Transfer do innego huba |
| `pipeline-api.php` | `buy_pipeline` | Zakup rurociągu |
| `pipeline-api.php` | `building_pipelines` | Lista rurociągów w budowie |
| `pipeline-api.php` | `pipeline_profiles` | Profile typów (koszt/godziny) |

### Odpowiedź `hub-api.php` przy cooldownie

```json
{
  "success": false,
  "error": "Cooldown aktywny — poczekaj jeszcze 3h 42min",
  "cooldown_remaining_s": 13320
}
```

---

## 6. Testy

### Aktualny wynik

| Zestaw | Plik konfiguracyjny | Wynik |
|--------|---------------------|-------|
| SQLite + unit | `phpunit.xml.dist` | **65 / 65** |
| MySQL integration | `phpunit.mysql.xml.dist` | **109 / 111** (2 pre-existing w TechnicalPipelineTasks) |

### Kluczowe pliki testów

| Plik | Zakres |
|------|--------|
| `tests/Integration/HubAssignmentServiceTest.php` | SQLite: assign / detach / transfer, opłaty, cooldown, insufficient_funds |
| `tests/Integration/HubPlayerQueryTraitTest.php` | SQLite: getUnassignedWells, cooldown_until w wynikach |
| `tests/MySqlIntegration/MySqlHubAssignmentServiceTest.php` | MySQL: pełny flow assign → transfer → detach |
| `tests/MySqlIntegration/MySqlWellPipelineServiceTest.php` | MySQL: purchasePipeline, building → active, getBuildingForPlayer |
| `tests/MySqlIntegration/MySqlHubTickServiceTest.php` | MySQL: processTick, persistTickResult, used vs new wear |
| `tests/MySqlIntegration/MySqlWellLifecycleSimulationTest.php` | MySQL: **end-to-end** zakup → hub → rurociąg → tick → detach → cooldown |

### Pokrycie testami symulacyjnymi (`MySqlWellLifecycleSimulationTest`)

13 testów, 90 asercji:

| Test | Co weryfikuje |
|------|---------------|
| `testFullWellLifecycleFromPurchaseToHubAndPipeline` | Pełny scenariusz end-to-end |
| `testWellPurchaseSimulationDeductsCashAndSetsNieustawiony` | Cash po zakupie, transport_type |
| `testMaxWellsLimitBlocksFifthPurchase` | Limit 5 odwiertów na gracza |
| `testHubAssignmentFailsWithInsufficientFunds` | insufficient_funds, zero zmian w DB |
| `testPipelinePurchaseRequiresActiveHubAssignment` | hub_required |
| `testPipelinePurchaseFailsWithInsufficientFunds` | Brak kasy, brak rurociągu w DB |
| `testPipelinePurchaseBlocksSecondPurchaseOnSameWell` | pipeline_already_exists |
| `testDetachCreatesActiveCooldownAndBlocksReassignment` | ~4h cooldown, cooldown_active |
| `testTransferWellCreatesDetachedAndNewActiveAssignment` | 2 wiersze w assignments |
| `testHeavyPipelineCostsMoreAndTakesLongerThanLight` | Porównanie typów light/heavy |
| `testHubTickOnRealMySqlAfterWellAssigned` | condition_pct, wear_level, tick_stats |
| `testAccessFeeStoredInAssignmentAndMatchesReturnValue` | access_fee_paid = access_fee |
| `testUnassignedWellsListShowsCooldownAfterDetach` | cooldown_until w getUnassignedWells() |

---

## 7. Co pozostaje do wdrożenia

| Element | Priorytet | Status |
|---------|-----------|--------|
| P2.1 — `hub_id` w `marine_deliveries` | średni | Nie zaczęte |
| P2.3 — Hub → storage jako osobny etap (`hub_road_trips`) | niski | Nie zaczęte |
| Player ownership model dla hubów (gracz kupuje hub) | wysoki | Nie zaczęte |
| Pełny flow zakupu / wynajmu huba przez gracza | wysoki | Nie zaczęte |
| Naprawienie 2 failujących testów `TechnicalPipelineTasks` | niski | Pre-existing issue |

---

## 8. Kluczowe pliki źródłowe

```
src/
├── HubService.php                    # getHub, getUnassignedWells, createEvent
├── HubAssignmentService.php          # assignWell, detachWell, transferWell
├── HubApi.php                        # AJAX endpoint dla operacji gracza
├── HubTickService.php                # processTick, persistTickResult
├── WellPipelineService.php           # purchasePipeline, completeBuildingPipelines
├── PipelineApi.php                   # AJAX endpoint dla rurociągów
├── Hub/
│   ├── AssignmentValidationTrait.php # validateAssignment, getCooldownAssignment
│   ├── PlayerQueryTrait.php          # getUnassignedWells (z cooldown_until subquery)
│   ├── ViewHubsTrait.php             # getAssignableHubs (z acq_access_fee)
│   └── ConfigTrait.php              # ensureHubSchema (migracje kolumn)
├── Tick/
│   ├── WellHubSection.php            # OPEX + czynsz rental per tick
│   └── PipelineSection.php           # degradacja + completeBuildingPipelines
assets/js/
└── logistics_hubs.js                 # hubDoAssign, hubDoTransfer, initCooldownTimers
templates/views/logistics/
└── main.php                          # hub-cooldown-badge, pipeline progress bar
lang/pl/
└── logistics.php                     # klucze i18n dla modali i błędów
```
