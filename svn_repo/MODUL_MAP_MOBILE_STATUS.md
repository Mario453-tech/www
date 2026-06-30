# Moduł Map — warstwa mobilna + endpointy API v1

> Status wdrożenia: **kompletny MVP** (PR #54, gałąź `claude/restart-basch-process-omr7r7`)
> Data: 2026-06-30 · CI: zielone (oba check-runy `success`)

---

## 1. Co zostało wdrożone

### 1.1 Nowe endpointy PHP (`api/v1/`)

| Plik | Metoda | Opis |
|------|--------|------|
| `api/v1/maps/index.php` | GET | Mapa świata — regiony z pełnym statusem zezwolenia + lokalizacje z flagami zajętości |
| `api/v1/permits/apply.php` | POST | Złożenie wniosku P1 o zezwolenie na wiercenie w regionie |

**`GET /api/v1/maps/`** — response:
```json
{
  "regions": [{
    "id": 1, "code": "PL", "name": "Polska",
    "political_risk": 20, "entry_cost": 150000.00,
    "tax_rate": 0.19, "opex_mult": 1.0, "color_hex": "#c8a84b",
    "permit": {
      "status": "none|pending|delayed|no_decision|granted|refused|transitional",
      "has_active": false,
      "minutes_left": null,
      "cooldown_minutes": null,
      "application_cost": 100000.00,
      "required_capital": null,
      "required_legal_level": null
    }
  }],
  "locations": [{
    "id": 1, "region_id": 1, "name": "Gdańsk Offshore",
    "latitude": 54.35, "longitude": 18.65,
    "oil_richness": 1.4, "well_type": "offshore", "tier": "medium",
    "effective_entry_cost": 150000.00, "effective_tax_rate": 0.19,
    "occupied_by_me": false, "occupied_by_anyone": false,
    "my_well_id": null, "my_well_status": null
  }],
  "well_count": 2
}
```

**`POST /api/v1/permits/apply`** — body `{"region_id": 1}`:
- 200: `{success:true, code, message, cost, review_minutes}`
- 422: `{success:false, code, message}` (np. `insufficient_funds`, `already_pending`, `legal_level_insufficient`, `credibility_too_low`)

**Require chain endpointów:**
```
api/v1/_bootstrap.php
  → src/i18n.php
  → src/LegalService.php          ← ciągnie cały łańcuch:
      src/CompanyCredibilityService.php
      src/PlayerPaymentService.php
        src/FinancialTransactionService.php
          src/BankAccountService.php
      src/Legal/HubPermitTrait.php
      src/Legal/BriberyTrait.php
  → src/WorldMap.php              ← (tylko maps/index.php)
      src/PlayerPaymentService.php (no-op, już załadowany)
```

`$_API_ROOT` jest ustawiany przez `_bootstrap.php` jako `dirname(__DIR__, 2)` = `/home/user/www`.
Endpointy używają `require_once $_API_ROOT . '/src/...'` — **nie** `dirname(__DIR__, 2)` bezpośrednio.

### 1.2 Rozszerzenia CI

**`tests/api_smoke.php`** — dodano endpoint do listy:
```php
'/api/v1/maps/',   // GET, zwraca 200 nawet dla pustych world_regions
```
Smoke test sprawdza brak klucza `error` w response — OK, bo `getMapData()` na pustych tabelach
zwraca `{regions:[], locations:[], well_count:0}` (valid JSON bez błędów).

**`tests/MySqlIntegration/MySqlApiSchemaContractTest.php`** — rozszerzono:
- W `setUp()`: `new LegalService()` (wywołuje `ensureSchema()` + `autoSeedIfEmpty()` w konstruktorze),
  wymaga wcześniej: `require_once .../src/i18n.php` i `require_once .../src/LegalService.php`
- W `CONTRACT`:

```php
'world_regions' => ['id','code','name','political_risk','entry_cost',
                    'production_bonus','tax_rate','opex_mult','color_hex'],
'world_locations' => ['id','region_id','name','latitude','longitude',
                      'oil_richness','well_type','tier','available',
                      'entry_cost_override','tax_rate_override'],
'legal_region_config' => ['region_id','enabled','risk_level','application_cost',
                          'base_review_minutes','required_capital','required_legal_level'],
'drilling_permit_applications' => ['id','player_id','region_id','status','cost',
                                   'submitted_at','decision_due_at','decided_at',
                                   'refusal_cooldown_until','delay_count'],
```

**`api/v1/healthcheck.php`** — dodano sekcję `3d. Zapytania endpointu /api/v1/maps`:
- sprawdzenie istnienia tabel: `world_regions`, `world_locations`, `legal_region_config`, `drilling_permit_applications`
- próbne zapytania na kolumnach używanych przez API

### 1.3 Nowy moduł Flutter (`mobile/lib/modules/maps/`)

| Plik | Opis |
|------|------|
| `mobile/lib/modules/maps/maps_module.dart` | `AppModule`: id=`maps`, order=30, icon=`Icons.map_outlined` |
| `mobile/lib/modules/maps/maps_screen.dart` | Główny ekran: lista regionów z kartami, pull-to-refresh, dialog potwierdzenia |
| `mobile/lib/modules/maps/i18n/maps_pl.dart` | Tłumaczenia PL (26 kluczy) |
| `mobile/lib/modules/maps/i18n/maps_en.dart` | Tłumaczenia EN (26 kluczy) |

Modele danych:
| Plik | Klasy |
|------|-------|
| `mobile/lib/models/map_data.dart` | `PermitInfo`, `MapRegion`, `MapLocation`, `MapData` |

Usługi — nowe metody w `mobile/lib/services/api_service.dart`:
```dart
static Future<MapData> getMapData(String token)
static Future<Map<String,dynamic>> applyPermit(String token, int regionId)
```

Integracja z resztą aplikacji:
- `mobile/lib/app/modules.dart` — `MapsModule()` dodany do `buildAppModules()`
- `mobile/lib/i18n/strings/core_pl.dart` — `'nav.maps': 'Mapa'`
- `mobile/lib/i18n/strings/core_en.dart` — `'nav.maps': 'Map'`

### 1.4 Zachowanie `MapsScreen`

- **Ładowanie**: `addPostFrameCallback` → `_load()` → `ApiService.getMapData(token)`
- **Pull-to-refresh**: `RefreshIndicator` → `_load()`
- **Lista regionów**: `_RegionCard` per region — expandable, pokazuje lokalizacje po rozwinięciu
- **Chip statusu**: switch na `permit.status` → kolor i etykieta (active=zielony, pending/delayed=pomarańczowy, refused/no_decision=czerwony, none=szary)
- **Wiersz akcji** (gdy `!permit.hasActive`):
  - `isPending` → tekst z minutami do decyzji
  - `refused` z cooldownem > 0 → tekst cooldown
  - `no_decision` → tekst informacyjny
  - `canApply` (none lub refused bez cooldownu) → przycisk z kosztem
- **Złożenie wniosku**: dialog potwierdzenia → `ApiService.applyPermit()` → SnackBar sukcesu/błędu → `_load()`
- **Lista lokalizacji**: po rozwinięciu karty — ikona + nazwa + etykieta (twój odwiert / zajęta / dostępna)

Gettery `PermitInfo`:
```dart
bool get canApply =>
    status == 'none' ||
    (status == 'refused' && (cooldownMinutes == null || cooldownMinutes == 0));

bool get isPending => status == 'pending' || status == 'delayed';
```

---

## 2. Co zostało naprawione (code review po wdrożeniu)

### 2.1 Niebezpieczne rzutowanie `as int?` (crash risk)

**Plik:** `mobile/lib/modules/maps/maps_screen.dart:77`

**Problem:** `result['review_minutes'] as int?` — w Dart `jsonDecode` może zwrócić `num` zamiast `int`
(np. gdy serwer odda `30.0`). Rzutowanie `as int?` na wartość `double` rzuca `_CastError`.

**Przed:**
```dart
final minutes = result['review_minutes'] as int? ?? 0;
```

**Po:**
```dart
final minutes = (result['review_minutes'] as num?)?.toInt() ?? 0;
```

### 2.2 Błędna nazwa pola błędu w `_serverErrorMessage`

**Plik:** `mobile/lib/services/api_service.dart:190`

**Problem:** `_serverErrorMessage()` czytało `body['error']`, ale `permits/apply.php` zwraca
422 przez `apiJson({success:false, code, message}, 422)` — klucz to `message`, nie `error`.
Standardowe `apiError()` używa klucza `error`, ale `permits/apply.php` używa własnego formatu.
W efekcie konkretny kod błędu (`legal_level_insufficient` itp.) był cicho gubiony i zastępowany
przez `api.error.server_http|422`.

**Przed:**
```dart
final raw = body['error'] as String?;
```

**Po:**
```dart
final raw = (body['error'] ?? body['message']) as String?;
```

---

## 3. Tabele bazy danych (używane przez ten moduł)

Wszystkie tabele są w `tests/ci-schema.sql` (pusta produkcja — dane są seedowane przez `LegalService`).

| Tabela | Kto wypełnia | Uwagi |
|--------|-------------|-------|
| `world_regions` | Seed admina | Musi mieć dane by mapa cokolwiek pokazała |
| `world_locations` | Seed admina | Lokalizacje per region |
| `legal_region_config` | `LegalService::autoSeedIfEmpty()` | Auto-seed przy pierwszym `new LegalService()` |
| `drilling_permit_applications` | `LegalService::submitApplication()` | Tworzone dynamicznie |

`LegalService::ensureSchema()` (wywoływane w konstruktorze) dodaje brakujące kolumny:
`bribe_locked_until`, `upgrade_pending`, `upgrade_decision_due_at` przez `Database::addColumnIfMissing()`.

---

## 4. Kluczowe zależności i pułapki

### 4.1 Klucze i5 URL endpointów

| Endpoint | URL w aplikacji Flutter | Plik na serwerze |
|----------|------------------------|-----------------|
| Mapa | `${baseUrl}/maps/` | `api/v1/maps/index.php` |
| Wniosek | `${baseUrl}/permits/apply.php` | `api/v1/permits/apply.php` |

`getMapData` nie przekazuje nagłówka `Accept-Language` — tłumaczenia po stronie serwera są domyślne (PL).
`applyPermit` analogicznie. Wiadomości `message` z serwera są przez Flutter ignorowane
(pokazywany jest zawsze `maps.apply_error` z i18n lokalnego).

### 4.2 Błąd wyświetlany użytkownikowi vs kod błędu serwera

W `_applyPermit()` blok `catch (_)` wyłapuje WSZYSTKIE wyjątki i pokazuje `maps.apply_error`.
Oznacza to, że gracz widzi zawsze ogólny komunikat błędu — niezależnie od tego czy serwer
zwrócił `insufficient_funds`, `already_pending` czy `credibility_too_low`.
Jeśli chcesz pokazać konkretny powód odmowy, zmień `catch (_)` na `catch (ApiException e)`
i pokaż `e.message` (po naprawie 2.2 będzie to zawartość pola `message` z serwera, jeśli pasuje
do wzorca klucza tłumaczeniowego).

### 4.3 Duplikacja `_fmt`

Metoda `_fmt(double v)` jest zduplikowana w `_MapsScreenState` (linia 142) i `_PermitActionRow`
(linia 365) — to świadomy kompromis, bo obie klasy są `Widget`ami bez wspólnego ancestry.
Przy refaktorze można wyciągnąć do pliku `maps_helpers.dart`.

### 4.4 Status `'transitional'`

Gdy `permit.status == 'transitional'` i `permit.hasActive == false`:
- `_PermitChip` pokazuje szary chip z `maps.permit.none` (domyślny case)
- `_PermitActionRow` zwraca `SizedBox.shrink()` (żaden warunek nie pasuje)
- Wyświetlana jest pusta sekcja akcji z separatorem

To zachowanie akceptowalne dla MVP — w stanie `transitional` gracz nie może nic zrobić.

---

## 5. Klucze i18n modułu map

Wszystkie klucze zdefiniowane w `maps_pl.dart` / `maps_en.dart`:

```
maps.title               maps.loading              maps.error
maps.regions.empty
maps.permit.none         maps.permit.pending        maps.permit.delayed
maps.permit.active       maps.permit.refused        maps.permit.no_decision
maps.permit.minutes_left maps.permit.cooldown
maps.apply_permit        maps.apply_permit_cost     maps.applying (nieu żywane MVP)
maps.apply_confirm_title maps.apply_confirm_body    maps.apply_confirm_ok
maps.apply_success       maps.apply_error
maps.location.available  maps.location.occupied_me  maps.location.occupied_other
maps.location.richness   maps.location.cost         (nieużywane MVP)
maps.locations.count     (nieużywane MVP)
maps.locations.header
```

Klucze z `core_pl/en.dart` używane przez ekran map:
```
common.cancel    common.retry
nav.maps
```

---

## 6. TODO i zakres poza MVP

- [ ] **Zakup odwiertu z poziomu mapy** — `POST /api/v1/maps/buy_well` — **poza zakresem MVP**.
  Gracz może kupić odwiert przez webappkę po uzyskaniu zezwolenia.
- [ ] **Locale w nagłówkach** — `getMapData` i `applyPermit` nie wysyłają `Accept-Language`.
  Komunikaty serwera są po polsku niezależnie od ustawień aplikacji.
- [ ] **Konkretne komunikaty błędu dla gracza** — zmienić `catch (_)` na `catch (ApiException e)`,
  wyświetlać `e.message` (po upewnieniu się, że serwer zwraca klucze i18n w polu `message`).
- [ ] **`maps.applying` / loading state** — klucz zdefiniowany, ale button nie ma stanu `_applying`.
  Przy dłuższym POST (wolne łącze) gracz nie ma feedbacku że coś się dzieje.
- [ ] **Widok mapy geograficznej** — aktualnie lista; flutter_map lub podobna biblioteka to P2.
- [ ] **Odświeżenie po powrocie** — jeśli gracz idzie do innej zakładki i wraca, dane mogą być stare;
  brak lifecycle-aware refresh (tylko pull-to-refresh).

---

## 7. Powiązane dokumenty

- `svn_repo/DZIAL_PRAWNY_P1_STATUS.md` — pełny status backendu P1 (LegalService, tick, panel admina)
- `svn_repo/MOBILE_ARCH.md` — architektura modułowa aplikacji Flutter
- `svn_repo/GAME_README.md` — changelog gry
- `AGENTS.md` (root repozytorium) — wytyczne dla agenta AI

---

## 8. Stan testów CI po wdrożeniu

```
PHPUnit + training migration on MySQL 8 → success (2× check-run, PR #54)
  - MySqlApiSchemaContractTest: world_regions, world_locations,
    legal_region_config, drilling_permit_applications — wszystkie kolumny OK
  - API smoke: /api/v1/maps/ → 200 (puste tabele = valid JSON bez klucza error)
  - Pozostałe testy regresyjne: zielone
```
