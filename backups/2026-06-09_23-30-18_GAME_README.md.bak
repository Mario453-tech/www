# OilCorp — Dokumentacja Techniczna Gry

> Strategiczna gra naftowa. Zarządzasz firmą wydobywczą: kupujesz lokalizacje, wiertujesz odwierty, zatrudniasz ludzi, handlujesz ropą.

---

## Spis treści

1. [Architektura](#1-architektura)
2. [Routing i .htaccess](#2-routing-i-htaccess)
3. [Autentykacja i sesje](#3-autentykacja-i-sesje)
4. [Odwierty (Wells)](#4-odwierty-wells)
4b. [System sprzętu (Equipment Tiers)](#4b-system-sprzętu-equipment-tiers)
4c. [System transportu odwiertów](#4c-system-transportu-odwiertów)
4d. [Sprzedaż odwiertów](#4d-sprzedaż-odwiertów)
5. [Warstwy geologiczne](#5-warstwy-geologiczne)
6. [System pracowników (HR)](#6-system-pracowników-hr)
7. [System degradacji](#7-system-degradacji)
8. [System awarii i incydentów](#8-system-awarii-i-incydentów)
9. [Wear & Tear (Zużycie)](#9-wear--tear-zużycie)
10. [Spirala katastrof](#10-spirala-katastrof)
11. [System rynku (Market)](#11-system-rynku-market)
12. [System bankowy](#12-system-bankowy)
13. [System bankructwa](#13-system-bankructwa)
14. [Komornik (Bailiff)](#14-komornik-bailiff)
15. [Mapa świata i lokalizacje](#15-mapa-świata-i-lokalizacje)
16. [System zadań technicznych](#16-system-zadań-technicznych)
17. [Cron Tick — serce gry](#17-cron-tick--serce-gry)
18. [Panel admina](#18-panel-admina)
18b. [Panel admina — sekcje transportu i balansu](#18b-panel-admina--sekcje-transportu-i-balansu)
18c. [Panel admina — HR (`admin/hr.php`)](#18c-panel-admina--hr-adminhrphp)
18d. [Panel admina — moderacja czatu](#18d-panel-admina--moderacja-czatu-adminchatphp)
19. [Profil gracza](#19-profil-gracza)
20. [Sala Zarządu (Boardroom)](#20-sala-zarządu-boardroom)
21. [Bezpieczeństwo](#21-bezpieczeństwo)
22. [System czatu graczy](#22-system-czatu-graczy)
23. [Dział Finansowy](#23-dział-finansowy)
24. [Czarny Rynek Ropy](#24-czarny-rynek-ropy)
25. [Separacja logiki od widoku](#25-separacja-logiki-od-widoku--faza-1)
26. [Panel admina — odwierty (admin/wells.php)](#26-panel-admina--odwierty-adminwellsphp--i18n--zakładki-konfiguracji)
27. [System Aktualności (Admin News)](#27-system-aktualności-admin-news)
28. [System Newsletter](#28-system-newsletter)
29. [System Hubów Logistycznych](#29-system-hubów-logistycznych)
- [Specjalizacje pracowników (perki)](#specjalizacje-pracowników-perki)
- [Changelog](#changelog)
- [Otwarte TODO](#otwarte-todo)
- [Jakość kodu — PHPStan](#jakość-kodu--phpstan)

---

## 1. Architektura

```
htdocs/
├── public/         - strony dostępne przez przeglądarkę (login, market, bank, sell...)
├── src/            - serwisy PHP (logika biznesowa)
├── cron/           - cron/tick.php (uruchamiany co ~5 min)
├── assets/         - CSS, JS, obrazy
├── templates/      - header.php (topbar nawigacji)
├── config/         - database.php (dane połączenia)
├── admin/          - panel admina
├── profile.php     - strona profilu gracza
├── hr.php          - panel HR (zarządzanie pracownikami)
├── dashboard.php   - główna strona gry
├── boardroom.php   - sala zarządu
├── technical.php   - panel techniczny (incydenty, odwierty)
└── htaccess        - plik do wgrania na serwer jako .htaccess
```

### Kluczowe serwisy (`src/`)
| Serwis | Opis |
|---|---|
| `WellService` | Produkcja, degradacja, wear, spirala |
| `IncidentService` | Generowanie i obsługa awarii |
| `MarketTick` | Obliczanie ceny ropy co tick |
| `MarketTrend` | Zarządzanie trendami rynkowymi |
| `HRService` | Rekrutacja, zatrudnianie, kontrakty |
| `GeologicalLayerService` | Warstwy geologiczne, zmiana warstwy |
| `BankService` / `LoanRepository` | Kredyty, raty, komornik |
| `BankruptcyService` | Bankructwo, restrukturyzacja |
| `RegionalEventService` | Zdarzenia regionalne |
| `TechnicalTeamService` | Zespół techniczny, BHP, zadania, powiadomienia |
| `RiskScoreEngine` | Ocena ryzyka kredytowego |
| `DirectorNotificationService` | Powiadomienia dla dyrektora (`director_notifications`) |
| `WellStaffService` | Przypisanie personelu do odwiertów, transport |

### Autoloading
`src/init.php` rejestruje `spl_autoload_register` — klasy ładowane automatycznie po nazwie pliku z `src/`.

---

## 2. Routing i .htaccess

### Clean URLs
Wszystkie strony mają "czyste" URLe bez `.php`:
```
/dashboard   → dashboard.php
/hr          → hr.php
/profile     → profile.php
/technical   → technical.php
/market      → public/market.php
/bank        → public/bank.php
/sell        → public/sell.php
/map         → public/map.php
/dm          → dm.php
...
```

### WAŻNE — plik `htaccess`
Plik w repozytorium nazywa się `htaccess` (bez kropki, bo Windows nie pozwala tworzyć plików zaczynających się od `.`).

**Na serwerze produkcyjnym musi być wgrany jako `.htaccess`** w katalogu głównym `public_html/`.

> **az.pl / PHP-FPM:** NIE używaj `php_value` ani `php_flag` w `.htaccess` — dyrektywy te działają wyłącznie z `mod_php`. Na serwerach z SAPI `fpm-fcgi` powodują HTTP 500 lub ignorowane są cicho, a ustawiony `upload_tmp_dir` prowadzi poza `open_basedir`. Konfigurację PHP-FPM zmienia się przez panel hostingowy lub `php.ini` per-vhost.

### Mapa routingu (`src/init.php` — stała `ROUTES`)
```php
const ROUTES = [
    'home'            => '/',
    'dashboard'       => '/dashboard',
    'profile'         => '/profile',
    'hr'              => '/hr',
    'technical'       => '/technical',
    'market'          => '/market',
    'bank'            => '/bank',
    'sell'            => '/sell',
    'map'             => '/map',
    'boardroom'       => '/boardroom',
    'loans'           => '/loans',
    ...
];
```
Użycie: `url('profile')` → `/profile`

### Zmiany (03.04.2026)
- Dodano regułę `RewriteRule ^profile$ /profile.php [L,PT]` — wcześniej `/profile` zwracało 404

### Zmiany (06–07.04.2026)
- Dodano regułę `RewriteRule ^dm$ /dm.php [L,PT]` — strona wiadomości prywatnych
- `src/.htaccess` usunięty — cała ochrona katalogu `src/` przeniesiona do głównego `.htaccess`
- Whitelist `/src/` rozszerzona o: `ChatApi.php`, `DmApi.php`, `TechNotifApi.php`

### Zmiany (22.04.2026)
- **Technical team** — status odwiertu w dropdown "Zlec zadanie" teraz wyświetlany z polskimi etykietami (zamiast surowego `paused_storage`, `paused_cash` etc.)
- **Mapa** — kliknięcie na własny odwiert na mapie teraz wyświetla szczegóły: status, produkcja, stan techniczny, poziom + link do panelu technicznego
- **Cache-Control** — dodanie `Cache-Control: no-store` do `Security::setHeaders()` — naprawia problem z nieodświeżającą się gotówką po zmianie przez admina (przeglądarka nie cachuje stron)
- **technical.php** — naprawa błędu `$db = null` — inicjalizacja `$db` przeniesiona przed pierwsze użycie (wcześniej była dopiero przy pobieraniu pipelines)

---

## 3. Autentykacja i sesje

- `Auth::requireLogin()` na każdej chronionej stronie
- Bcrypt dla haseł
- Reset hasła przez e-mail (token jednorazowy)
- CSRF token (`window.WG_CSRF` / `CSRF::validateToken()`) na każdym endpoincie AJAX

### Bugfix (06.04.2026)
- **`public/register.php` — Duplicate entry username** — INSERT nie podawał kolumny `username` (ma `DEFAULT ''` + `UNIQUE`); drugi gracz dostawał błąd; naprawiono przez auto-generowanie username z części emaila przed `@` (suffix liczby jeśli zajęte)

---

## 4. Odwierty (Wells)

### Statusy odwiertu (`wells.status`)
| Status | Opis |
|---|---|
| `active` | Produkuje normalnie |
| `contaminated` | Skażony, produkuje z karą |
| `no_operator` | Brak operatora |
| `no_technician` | Brak technika |
| `paused_staff` | Brak minimum kadrowego |
| `paused_cash` | Brak gotówki na OPEX |
| `paused_storage` | Magazyn pełny |
| `broken` | Zerowy stan techniczny — odwiert zatrzymany, nie nalicza OPEX, wymaga naprawy |
| `seized` | Zajęty przez komornika |
| `blowout` | Zniszczony (katastrofa) |
| `layer_switch` | W trakcie wiercenia do nowej warstwy |
| `sold` | Sprzedany — niewidoczny na liście gracza, lokalizacja ponownie wolna na mapie |

### Formuła produkcji (`WellService::getEffectiveProduction`)
```
produkcja = base_production
  × region_richness
  × equipment_mult
  × operator_skill_mult     (skill 1→70%, 5→100%, 10→130%)
  × layer_richness_mult     (shallow×0.70 … ultra×2.80)
  × technical_condition/100
  × incident_prod_drop      (jeśli aktywny incydent)
  × regional_event_mult
```

---

## 4b. System sprzętu (Equipment Tiers)

Każdy odwiert ma tier sprzętu i poziom upgrade:
- `equipment_tier`: `black_market` / `standard` / `premium`
- `equipment_upgrade_level`: 0–3 (każdy poziom: +5% prod, -10% awarii, -10% wear)

### Mnożniki tierów

| Tier | Produkcja | Awarie | Wear | Spirala |
|------|-----------|--------|------|---------|
| 🔴 Czarny rynek | -10% | +70% | +60% | +20% |
| 🟡 Standard | 0% | +40% | +20% | 0% |
| 🟢 Premium | +10% | -30% | -40% | -15% |

### Koszty
| Operacja | Koszt |
|----------|-------|
| Czarny rynek | 500 000 PLN |
| Standard | 2 000 000 PLN |
| Premium | 8 000 000 PLN |
| Upgrade lvl 1 | 30 000 000 PLN |
| Upgrade lvl 2 | 60 000 000 PLN |
| Upgrade lvl 3 | 100 000 000 PLN |

### Implementacja
- `WellService::getEquipmentMultipliers(tier, upgradeLevel)` — zwraca mnożniki
- `WellService::getEffectiveProduction()` — używa `prod` mult
- `WellService::processWear()` — używa `wear` mult
- `IncidentService::processTick()` — używa `incident` mult
- `WellService::addSpiralBoost()` — używa `spiral` mult
- `cron/tick.php` — pobiera `eqMults` i stosuje do produkcji, degradacji, wear i spirali
- UI: panel ⚙️ Sprzęt w `well_grid.php` (zmiana tieru, upgrade)

## 4c. System transportu odwiertów

Każdy odwiert ma przypisany typ transportu ropy do magazynu. Transport wpływa na przepustowość, OPEX i ryzyko awarii.

### Kolumny w tabeli `wells`
| Kolumna | Typ | Domyślnie | Opis |
|---------|-----|-----------|------|
| `transport_type` | enum('rurociag','ciezarowki','tankowiec') | 'rurociag' | Typ transportu |
| `transport_capacity_pct` | decimal(5,2) | 120.00 | Przepustowość jako % produkcji |
| `transport_opex_pct` | decimal(5,2) | 7.50 | OPEX transportu jako % wartości ropy |

### Parametry typów

| Typ | Ikona | Przepustowość | OPEX | Awarie |
|-----|-------|--------------|------|--------|
| Rurociąg | 🔵 | 120% produkcji | 7.5% wartości ropy | -20% |
| Ciężarówki | 🟡 | 70% produkcji | 20.0% wartości ropy | +30% |
| Tankowiec | 🟢 | 110% produkcji | 12.0% wartości ropy | 0% |

### Formuła w tick.php
```
sprzedaż = MIN(produkcja_tick, produkcja_tick × transport_capacity_pct / 100)
opex_transport = sprzedaż × oil_price × transport_opex_pct / 100
```

### Implementacja
- **Migracja SQL**: `transport_migration.sql` — dodaje 3 kolumny do `wells`
- **`cron/tick.php`** — pobiera transport per odwiert, ogranicza produkcję przez capacity, pobiera OPEX, przekazuje `transport_incident_mult` do IncidentService
- **`IncidentService::processTick()`** — mnoży szansę incydentu przez `transport_incident_mult` (rurociąg ×0.8, ciężarówki ×1.3, tankowiec ×1.0)
- **`WellStaffApi.php`** — endpoint `set_transport`: zmiana typu per odwiert
- **`well_grid.php`** — panel 🚛 Transport z aktualnym typem i przełączaniem
- **`well_grid.js`** — `wgToggleTransport()`, `wgSetTransport()` — fetch do `/src/WellStaffApi.php`
- **`style.css`** — klasy `.wg-transport-wrap`, `.wg-transport-toggle`, `.wg-transport-body`

### Zmiana transportu
Gracz zmienia transport klikając **🚛 Transport ▼** w karcie odwiertu. Zmiana jest natychmiastowa i bez kosztu. Po przełączeniu gra pokazuje komunikat potwierdzający.

---

## 4d. Sprzedaż odwiertów

Gracz może sprzedać dowolny odwiert (z wyjątkiem `seized`, `blowout`, `sold`) i otrzymać jednorazową wypłatę.

### Wycena (`WellService::calculateSellValue`)

Podstawa: `profit_per_hour × 24h × 1.2` (szacowany zwrot za dobę z premią 20%).

Modyfikatory:
| Czynnik | Zakres | Efekt |
|---------|--------|-------|
| Stan techniczny (`condition`) | 0–100% | -30% do +10% |
| Zużycie (`wear_level`) | 0–100 | 0% do -20% |
| Risk score | 0–100 | 0% do -25% |
| Tier sprzętu | black_market/standard/premium | -10% / 0% / +15% |
| Głębokość (`depth_m`) | 0–8000 m | 0% do +20% |
| Spirala katastrof | `post_incident_risk_boost` > 0 | do -15% |

### Cooldown
Nie można sprzedać odwiertu kupionego mniej niż **2 godziny temu** (`created_at`).

### Wykonanie sprzedaży (`WellService::sellWell`)
1. `UPDATE wells SET status='sold', sold_at=NOW()`
2. `UPDATE players SET cash = cash + sell_value`
3. Wpis do `bankruptcy_events` (typ `well_sold`)
4. Wpis do `admin_logs`

### Zachowanie po sprzedaży
- `wells.status` = `sold`, `sold_at` = NOW()
- Odwiert **znika z listy gracza** (zapytania filtrują `status != 'sold'`)
- Lokalizacja (`location_id`) staje się **ponownie wolna** — `WorldMap::isLocationAvailable()` zwraca `true`
- `WorldMap::getAvailableLocations()` i `WorldMap::getOccupiedLocations()` nie uwzględniają `sold`
- Karta odwiertu usuwa się z DOM animacją opacity+scale (bez reload strony); toast `wgShowSoldToast` pokazuje zarobioną kwotę

### Ponowny zakup tej samej lokalizacji
- Gracz (lub inny) może kupić lokalizację ponownie — powstaje **nowy rekord** `wells`
- Nowy odwiert **dziedziczy stan złoża** (`reservoir_extracted_bbl`) z poprzedniego rekordu tej lokalizacji (jeśli `locations.reservoir_bbl` jest skończone)
- Jeśli poprzedni odwiert wyeksploatował złoże (`reservoir_pct < 5%`) — zakup jest nadal możliwy, ale wycena sprzedaży będzie bardzo niska; `world_map.js` powinien ostrzegać gracza (TODO)

### Implementacja
- **`src/WellSellApi.php`** — endpoint REST:
  - `GET ...well_id=N` → wycena (bez zmiany stanu); zwraca `{ sell_value, breakdown, reservoir_pct }`
  - `POST {well_id, csrf_token}` → wykonanie sprzedaży (walidacja CSRF); zwraca `{ success, sell_value }`
- **`src/WellService.php`** — `calculateSellValue()`, `sellWell()`
- **`templates/components/well_grid.php`** — przycisk 💰 Sprzedaj odwiert na górze sekcji szczegółów karty (warunek: status nie w `seized`, `blowout`, `sold`)
- **`assets/js/well_grid.js`**:
  - `wgSellPreview(wellId)` — fetch GET → buduje `bodyHtml` z breakdown + pasek złoża (`wg-sell-reservoir`) + `wg-sell-note`; wywołuje `confirmAction()` z `modal.js`
  - `wgConfirmSell(wellId)` — fetch POST → animuje usunięcie karty (`#wg-card-{id}`), czyści pusty region, wywołuje `wgShowSoldToast()`
  - `wgShowSoldToast(earned)` — toast dolny z kwotą, auto-znika po 3.5s
- **`assets/css/style.css`** — `.wg-sell-wrap`, `.wg-btn-sell`, `.wg-sell-breakdown`, `.wg-sell-row`, `.wg-sell-minus`, `.wg-sell-plus`, `.wg-sell-total`, `.wg-sell-price`, `.wg-sell-reservoir`, `.wg-sell-res-bar`, `.wg-sell-res-val`, `.wg-sold-toast`, `.wg-sold-toast--show`

### UI — flow gracza
1. Kliknij **▼ szczegóły** na karcie odwiertu
2. Kliknij **💰 Sprzedaj odwiert**
3. Modal (`confirmAction` z `modal.js`) pokazuje breakdown wyceny, cenę końcową i (jeśli < 100%) pasek zasobów złoża
4. Kliknij **Potwierdź sprzedaż** → środki trafiają na konto natychmiast
5. Karta odwiertu znika animacją (opacity 0 + scale 0.95 → `card.remove()`); jeśli region pusty — region też znika
6. Toast w dolnej części ekranu: `✅ Odwiert sprzedany · +{kwota} PLN`

### TODO
- `world_map.js`: przed zakupem lokalizacji sprawdzić `reservoir_pct` poprzedniego odwiertu i pokazać ostrzeżenie jeśli złoże wyeksploatowane (< 10%)

---

## 5. Warstwy geologiczne

Każdy odwiert wierci w konkretnej warstwie geologicznej. Warstwa wpływa na produkcję, ryzyko, zużycie i spiralę.

| Warstwa | Maks. głębokość | Zasoby | Richness | Ryzyko | Zużycie | Spirala | Koszt | Przestój |
|---|---|---|---|---|---|---|---|---|
| Płytka | 300 m | 100k bbl | ×0.70 | ×5.0 | ×1.0 | ×1.0 | bezpłatna | 0h |
| Środkowa | 3 000 m | 400k bbl | ×1.30 | ×7.5 | ×1.3 | ×1.2 | 25 mln PLN | 2h |
| Głęboka | 5 000 m | 1 mln bbl | ×2.00 | ×11.0 | ×1.6 | ×1.4 | 120 mln PLN | 4h |
| Ultra-głęboka | 8 000 m | 2.5 mln bbl | ×2.80 | ×16.0 | ×2.0 | ×1.7 | 400 mln PLN | 6h |

### Zasady
- **Płytka jest domyślna** — każdy odwiert startuje na shallow (id=1)
- **Black market + deep/ultra** → +50% ryzyka awarii
- **Zmiana warstwy** → odwiert pauzowany na czas wiercenia (`layer_switch_until`)
- **Zasoby per warstwa** → śledzone w `layer_reservoir_used`

### Implementacja
- `GeologicalLayerService::getAllLayers()` — z `try/catch` (fallback gdy brak tabeli)
- `GeologicalLayerService::getActiveLayer(wellId)` — z `try/catch` (fallback gdy brak kolumny)
- `GeologicalLayerService::processSwitchCompletion()` — wywoływane w każdym ticku
- UI: panel w `well_grid.php`, endpoint AJAX `layer_well.php`
- JS: `wgToggleLayer()`, `wgSwitchLayer()`

---

### Aktualizacja (03.05.2026) - HR jako panel kadrowy, rekrutacja zarzadu w dashboardzie
- HR nie prowadzi juz rekrutacji kadry dyrektorskiej.
- Z `hr.php` usunieto widoczny flow: **Rekrutacja**, **Kandydaci** i stary formularz nowej rekrutacji.
- `dashboard.php` przejal:
  - start rekrutacji dyrektorow,
  - liste kandydatow zarzadu,
  - aktywne procesy rekrutacji dyrektorskiej,
  - decyzje zatrudnij / odrzuc.
- HRApi.php blokuje stary flow initiated_by='hr' komunikatem hr.recruitment_moved_to_dashboard.
- `src/HR/HiringTrait.php` i `src/HR/DataTrait.php` zostaly doszczelnione:
  - kandydaci i requesty sa scope'owane do player_id,
  - finalizacja zatrudnienia sprzata tylko wlasciwego kandydata / `request_id`,
  - nie ma juz ryzyka czyszczenia obcych kandydatow po samej roli.
- Mobilny i waski layout poprawiono:
  - pasek zakladek HR ma poziomy scroll i nie ucina juz `Headhunter`,
  - karta pracownika nie wychodzi poza ekran na telefonie,
  - dashboard dyrektora ma pelnoszerokie akcje i lepsze zawijanie formularza.

### Aktualizacja (02.05.2026) - rozdzielenie HR i zarzadu

- Dodano osobna zakladke **Zarzad** w HR (widok read-only).
- Zakladka **Pracownicy** pokazuje tylko personel operacyjny (bez czlonkow zarzadu).
- W `board_members` wprowadzono `member_type` (`director` / `staff`) i na tym oparto filtrowanie:
  - dostep do dzialow (`BoardAccess`) liczy wylacznie `director`,
  - dane HR rozdzielaja `staff` vs `director`.
- Rekrutacja HR wymaga `spec_code` (`HRApi.php` - walidacja).
- Skrocono czas rekrutacji do minut: `local` 120-240 s, `international` 180-300 s.
- Usunieto auto-refresh aktywnych rekrutacji; karta nowej rekrutacji dopina sie dynamicznie bez przeladowania.
- Poprawiono lokalny timer odliczania (bez bledu przesuniecia czasu o ~2h).
- Dodano akcje `fire_technical_staff` dla `technical_staff`.
- Uzupelniono i18n w `lang/pl.php` (`hr.tab_directors`, `hr.field_specialization`, `hr.recruitment_started`, `hr.err_missing_specialization`, rozszerzone `hr.spec.*`).


### Panel HR (`hr.php` + `assets/js/hr.js`)
Zakladki:
- **Pracownicy** - lista aktywnego personelu operacyjnego
- **Zarzad** - read-only podglad obsadzonej kadry dyrektorskiej
- **Kontrakty** - zarzadzanie umowami
- **Historia** - log zdarzen kadrowych
- **Rynek pracy** - regiony i modyfikatory rynku pracy
- **Headhunter** - zlecenia na technicznych ekspertow
### Panel dyrektora (`dashboard.php`)
Od 03.05.2026 dashboard jest jedynym miejscem dla rekrutacji zarzadu:
- **Rekrutacja kadry dyrektorskiej** - formularz wyboru stanowiska i regionu
- **CV oczekujace na Twoja decyzje** - kandydaci zarzadu
- **Aktywne rekrutacje** - procesy initiated_by='director'
- **Zarzad** - aktywnie zatrudnieni dyrektorzy
- **Ostatnie zdarzenia** - historia ostatnich decyzji
### Zatrudnianie kandydata (`hireCandidate`)
Po kliknięciu "Zatrudnij":
1. AJAX → `HRApi.php` action=`hire_candidate`
2. Toast z potwierdzeniem
3. Karta kandydata znika z animacją (fade + scale)
4. Licznik na zakładce "Kandydaci" maleje o 1
5. **Strona NIE jest przeładowywana** — gracz zostaje na zakładce Kandydaci

**Zmiana (03.04.2026):** Wcześniej `hireCandidate` wywoływało `location.reload()` co przenosiło gracza z powrotem na zakładkę Rekrutacja. Teraz karta jest usuwana z DOM bez reload.

### Pracownicy przypisani do odwiertów
- `operator_id` → wpływa na produkcję (skill_level)
- `technician_id` → wpływa na degradację (skill_level)
- Brak operatora → status `no_operator`, brak produkcji
- Brak technika → status `no_technician`, +50% degradacji

### Równoległa rekrutacja (02.05.2026)

Gracz może prowadzić **maksymalnie 2 rekrutacje jednocześnie**, w różnych działach.

#### Ograniczenia (walidacja po stronie serwera — `src/HRApi.php`)
- Limit 2 aktywnych procesów (status `pending` lub `ready`) → błąd `hr.err_max_recruitments`
- Nie można rekrutować dwukrotnie na tę samą rolę → błąd `hr.err_role_already_recruiting`

#### Formularz nowej rekrutacji (`templates/views/hr/main.php`)
Formularz `.new-recruit-card` wyświetlany pod listą aktywnych procesów w zakładce Rekrutacja:
- **Wskaźnik slotów** — badge kolorowy (zielony 0/2, złoty 1/2, czerwony 2/2): `"Aktywne rekrutacje: X / 2"`
- **Dropdown ról** — filtruje automatycznie role już obsadzone przez pracownika i będące w rekrutacji
- **Dropdown specjalizacji** — filtrowany przez `nrFilterSpecializations()` do działu wybranej roli
- **Siatka regionów** (`.nr-region-grid`) — klikalnie z pre-selekcją pierwszego regionu
- Gdy limit 2/2 osiągnięty → formularz zastępowany alertem `.hr-alert--warn`
- Gdy wszystkie role obsadzone → alert `.hr-alert--info`

#### JS (`assets/js/hr.js`)
| Funkcja | Opis |
|---------|------|
| `startNewRecruitment()` | Wysyła `action=start_recruitment`; po sukcesie dodaje kartę i aktualizuje UI |
| `addRecruitmentCard(rec)` | Tworzy kartę z timerem (obsługuje HH:MM:SS dla rekrutacji >60 min); usuwa rolę z dropdownu; wywołuje `_nrUpdateSlotsUI()` |
| `_nrUpdateSlotsUI()` | Aktualizuje badge slotów + chowa formularz gdy 2/2; używa `hrl()` dla i18n |
| `nrFilterSpecializations()` | Chowa opcje spec niezgodne z działem wybranej roli |
| `nrSelectRegion(el)` | Zaznacza kartę regionu i ustawia `#nr-region` hidden input |
| `switchTab(name)` *(fix)* | Teraz poprawnie re-aktywuje przycisk zakładki (wcześniej tylko panel treści) |

#### CSS (`assets/css/hr.css`)
| Klasa | Opis |
|-------|------|
| `.nrc-slots-badge` | Badge wskaźnika slotów |
| `.nrc-slots-free` | Zielony (0 aktywnych) |
| `.nrc-slots-partial` | Złoty (1 aktywna) |
| `.nrc-slots-full` | Czerwony (2 aktywne — limit) |
| `.hr-alert` | Ramka alertu wewnątrz formularza |
| `.hr-alert--warn` | Wersja złota (ostrzeżenie o limicie) |
| `.hr-alert--info` | Wersja niebieska (brak dostępnych ról) |

#### i18n (`lang/pl.php`)
| Klucz | Wartość |
|-------|---------|
| `hr.err_max_recruitments` | Maksymalnie 2 rekrutacje mogą trwać jednocześnie. |
| `hr.err_role_already_recruiting` | Rekrutacja na to stanowisko jest już w toku. |
| `hr.slots_indicator` | Aktywne rekrutacje: %d / 2 |
| `hr.max_slots_reached` | Osiągnięto limit 2 równoległych rekrutacji… |
| `hr.nr_role_label` | Dział / stanowisko |
| `hr.nr_region_label` | Region rekrutacji |
| `hr.nr_spec_label` | Specjalizacja |
| `hr.nr_no_roles_available` | Wszystkie stanowiska są już obsadzone lub w trakcie rekrutacji. |

### Bugfix (05.04.2026)
- **`hr.php` — `$db` undefined** — `$db` używane w auto-cleanup rekrutacji przed inicjalizacją; dodano `$db = Database::getInstance()->getConnection()` na początku pliku

### Bugfix (02.05.2026)
- **`src/HRApi.php` — `$db` undefined** — zmienna `$db` używana w walidacji `start_recruitment` (max 2 rekrutacje) ale nigdy zainicjalizowana; każde wywołanie kończyło się fatal error; naprawione przez dodanie `$db = Database::getInstance()->getConnection()` po inicjalizacji serwisów

---

## 7. System degradacji

### Formuła (`WellService::processDegradation`)
```
degradacja/tick = base_deg
  × political_risk_mult
  × wear_mult
  × brak_technika (+50% jeśli brak)
  × monitoring_mult
  × hse_mult
  × spirala_mult
```

### Stan techniczny (`technical_condition`)
- 100% = idealny
- <40% = wysokie ryzyko awarii
- 0% = odwiert zatrzymany

---

## 8. System awarii i incydentów

### Poziomy
| Poziom | Produkcja | Naprawa | Częstotliwość |
|---|---|---|---|
| `micro` | -5-10% | Automatyczna | Bardzo często |
| `minor` | -20% | Automatyczna | Często |
| `medium` | -50% | Ręczna | Umiarkowanie |
| `major` | Stop | Ręczna | Rzadko |

### Formuła szansy incydentu (`IncidentService::processTick`)
```
chance = max(
  BASE_CHANCE_PER_HOUR[level] × deltaHours × eq_mult × layer_risk_mult × wear_mult × spiral_mult,
  FLOOR_CHANCE_PER_TICK       (tylko micro)
)
```

### Skalibrowane stałe (02.04.2026)
```php
// Przy shallow(×5.0) + standard(×1.4) + tick 5min(0.0833h):
BASE_CHANCE_PER_HOUR = [
    'micro'  => 0.072,   // ~4.2%/tick → ~1 micro co 2h
    'minor'  => 0.029,   // ~1.7%/tick → ~1 minor co 5h
    'medium' => 0.0096,  // ~0.56%/tick → ~1 medium co 15h
    'major'  => 0.002,   // ~0.12%/tick → ~1 major co 70h
];
FLOOR_CHANCE_PER_TICK = 0.025; // min 2.5%/tick dla micro
```

**Zmiana (02.04.2026):** Przeliczono bazy z uwzględnieniem 5-minutowego ticku. Dodano `FLOOR_CHANCE_PER_TICK` jako stałą minimalną (nie skalowaną przez `deltaHours`).

### Implementacja
- `IncidentService::processTick` — główna logika
- `IncidentService::saveIncident` — zapis do `well_incidents`
- `IncidentService::applyEffects` — aktualizuje `technical_condition`, `post_incident_risk_boost`
- `technical.php` — wyświetla listę incydentów

---

## 9. Wear & Tear (Zużycie)

Zużycie odwiertu (`wear_level`) rośnie z produkcją. Wysokie zużycie zwiększa ryzyko awarii i degradację.

### Formuła (`WellService::processWear`)
```
wear_gain = base_production_per_hour
  × richness_mult
  × wear_depth_factor   (z warstwy geologicznej)
  × (1 + spiral_wear_mult)
```

---

## 10. Spirala katastrof

Po każdym incydencie (poza micro) odwiert wchodzi w "spiralę katastrof" — zwiększa ryzyko kolejnych incydentów.

### Implementacja
- `WellService::addSpiralBoost` — dodaje `post_incident_risk_boost` (mnożony przez `spiral_boost` warstwy)
- `WellService::processSpiralDecay` — redukuje boost z czasem

### Wyświetlanie w UI
Spirala widoczna w szczegółach odwiertu (`well_grid.php`) jako metryka **🌀 Spirala**:
- Pojawia się gdy `post_incident_risk_boost > 0.5%`
- Żółta: boost < 30%
- Czerwona: boost ≥ 30%
- Nie pojawia się przy pauzowanych odwiertach (brak incydentów = brak spirali)

### Uwaga
Odwierty z `no_operator`, `paused_staff` etc. nie generują incydentów w ticku — spirala nie rośnie gdy odwiert nie produkuje.

---

## 11. System rynku (Market)

### Formuła ceny (`MarketTick::updatePrices`)
```
ratio        = (player_supply + world_production) / effective_demand
sd_pressure  = (1 - ratio) × SENSITIVITY(0.40) × current_price
trend_target = clamp(base_price × price_modifier, MIN_PRICE, MAX_PRICE)
gravity      = (trend_target - current_price) × GRAVITY_RATE(0.03)
trend_shock  = (price_modifier - 1.0) × current_price × 0.05   [gdy |modifier-1| ≥ 0.25]
new_price    = clamp(current + sd_pressure + gravity + trend_shock + noise, 30, 300)
```

### Kluczowe stałe
| Stała | Wartość | Opis |
|---|---|---|
| `MIN_PRICE` | 30 | Minimalna cena ropy |
| `MAX_PRICE` | 300 | Maksymalna cena ropy |
| `SENSITIVITY` | 0.40 | Siła nacisku supply/demand |
| `GRAVITY_RATE` | 0.03 | Szybkość powrotu do celu trendu |

### Trendy rynkowe (`MarketTrend`)
Aktywny trend wpływa na cenę dwutorowo:
1. **Popyt**: `effective_demand = demand_index × price_modifier`
2. **Gravity target**: `trend_target = base_price × price_modifier`
3. **Szok cenowy**: przy silnym trendzie (`|modifier-1| ≥ 0.25`) — dodatkowy impuls co tick

Przykład — Pandemia (`price_modifier=0.60`):
- `trend_target = 100 × 0.60 = 60` → gravity ciągnie cenę do $60
- `effective_demand` spada o 40% → oversupply → dodatkowa presja w dół
- `trend_shock` = ujemny (~-3$/tick przy cenie $100)

**Zmiana (02.04.2026):** Wcześniej `gravity` ciągnęło do `base_price` ignorując trend. Teraz `gravity` ciągnie do `base_price × price_modifier`. Dodano `trendShock` dla natychmiastowej reakcji.

### Synchronizacja
`$newPrice` obliczony przez `MarketTick` na początku ticku jest używany bezpośrednio przy obliczeniu podatku regionalnego — bez dodatkowego `SELECT`.

---

## 12. System bankowy

### Kredyty (`BankService`, `LoanRepository`)
- Wymagania: konto >4 dni, brak bankructwa, odpowiednia ocena ryzyka
- Raty pobierane automatycznie w ticku
- Przekroczenie terminu → status `late` → komornik

### Ocena ryzyka kredytowego (`RiskScoreEngine`)
Skala 0–115 pkt: odwierty, produkcja, magazyn, gotówka, zachowanie, rynek, historia, credit score.

### Negocjacje (`BankNegotiationService`)
Restrukturyzacja, umorzenie odsetek, wydłużenie okresu — decyzja probabilistyczna.

---

## 13. System bankructwa

| Status | Opis |
|---|---|
| `none` | Normalny |
| `restructuring` | Plan naprawczy aktywny |
| `liquidation` | Odwierty zajęte |
| `recovered` | Wyszedł z bankructwa |

---

## 14. Komornik (Bailiff)

Gdy gracz nie spłaca rat: kredyt `late` → postępowanie egzekucyjne → odwierty `seized` → po spłacie zwolnione.

---

## 15. Mapa świata i lokalizacje

Regiony z parametrami: `oil_richness`, `political_risk`, `regional_tax_rate`, `region_opex_mult`, `region_production_bonus`, `region_stability_bonus`.

Zdarzenia regionalne (`RegionalEventService`) — kataklizmy, kryzysy polityczne, odkrycia złóż.

---

## 16. System zadań technicznych

`TechnicalTeamService` — naprawy awarii, konserwacja prewencyjna, wymiana sprzętu. Czas trwania zależy od poziomu uszkodzenia i dostępnego personelu.

---

## 17. Cron Tick — serce gry

`cron/tick.php` uruchamiany co **~5 minut**.

### Kolejność operacji

#### 1. Gospodarka i rynek
- Aktualizacja popytu (`EconomyService`, co 30 min)
- Deaktywacja wygasłych / aktywacja nowych trendów (`MarketTrend`)
- Obliczenie `$newPrice` (`MarketTick::updatePrices($activeTrend)`)

#### 2. System bankowy
- Odsetki, raty, komornik, decyzje kredytowe, negocjacje, plany naprawcze, `Headhunter`, bankruci

#### 2b. Odczyt globalnych mnożników balansu
```php
// Przed pętlą graczy — jednorazowo per tick
$gBalanceMults = ['incident'=>1.0, 'disaster'=>1.0, 'wear'=>1.0,
                  'degradation'=>1.0, 'loss'=>1.0, 'opex'=>1.0, 'production'=>1.0];
// Odczyt z well_config (klucze global_*_multiplier / global_*_mult)
// Fallback: 1.0 gdy tabela/klucz nie istnieje
```
Ustawiane przez `admin/balance.php`. Gdy wszystkie = 1.0 → tick zachowuje się identycznie.

#### 3. Każdy gracz (pomijani bankruci)

```
Przed pętlą odwiertów:
  - TechnicalTeamService: HSE bonus, staffCheck, processProcedureDecay
  - RegionalEventService: resolveExpired, processTick, getActiveEvents
  - Potrącenie pensji (zarząd + technicy, przeliczone na deltaHours)

Dla każdego odwiertu:
  a) Zakończenie wiercenia warstwy (processSwitchCompletion)
  b) Sprawdzenie minimum kadrowego → paused_staff / wznowienie
  c) Weryfikacja operatora i technika (czy nie zwolnieni)
  d) Degradacja stanu technicznego
  e) Aktualizacja risk_score
  f) Incydenty (IncidentService::processTick)
     - BASE_CHANCE_PER_HOUR × deltaHours × eq_mult × layer_risk_mult × wear_mult × spiral_mult
     - × transport_incident_mult × gBalanceMults['incident']
     - floor: min 2.5%/tick dla micro
  g) Wear & Tear (WellService::processWear)
     - × transportWearMult × gBalanceMults['wear']
  h) Decay spirali katastrof
  i) Disaster roll (blowout, pipeline explosion, surface spill)
     - × transportDisasterMult × gBalanceMults['disaster']
  j) OPEX → jeśli brak kasy: paused_cash
  k) Efektywna produkcja (WellService::getEffectiveProduction)
     - × gBalanceMults['production']
  l) Transport loss (rurociąg infrastrukturalny — `pipelines`)
     - lostOil × gBalanceMults['loss']
  l2) Transport per odwiert:
     - `sprzedaż = MIN(produkcja, produkcja × transport_capacity_pct%)`
     - OPEX transportu: `sprzedaż × oil_price × transport_opex_pct% × gBalanceMults['opex']`
     - `transport_incident_mult` przekazywany do IncidentService
  l3) Zdarzenia transportowe (theft/accident/storm/leak/pressure_drop):
     - Wywoływane **przed** zapisem do magazynu i liczeniem finansów
     - Modyfikują `$actual` przez referencję → faktycznie redukują przychód i produkcję
     - Różnica `$actualBefore - $actual` trafia do `finance_logs.loss_bbl` i `loss_value`
     - `finance_logs.gross_revenue` = produkcja przed zdarzeniem × cena
  m) Zapis do magazynu → paused_storage jeśli pełny
  n) Podatek regionalny: actual × $newPrice × tax_rate
```

### Częstotliwość efektywna
- Tick co 5 min = 12 ticków/godzinę
- `delta_hours` = czas od `last_tick_at` gracza (odporny na opóźnienia)
- Max `delta_hours` = 24h (zabezpieczenie)

### Bugfixy tick (22.04.2026)
- **`PipelineSection`** — `spec_code` → `specialization` w sprawdzeniu inżyniera rurociągów
- **`WellLoopSection`** — dodano `'broken'` do skip listy statusów odwiertów
- **`WellLoopSection`** — zdarzenia transportowe wywoływane przed zapisem finansów (theft/accident mają teraz realny efekt)
- **`WellLoopSection`** — `finGross/finLossBbl/finLossValue` teraz poprawnie wypełniane w `finance_logs`
- **`BankSection`** — `WHERE status != 'bankrupt'` → `status = 'bankrupt'` w selekcji graczy do BankruptcyService
- **`LoanDecisionService`** — `...... null`/`...... 0` przy dostępie do `$breakdown['market']`
- **`CostsTrait`** — `'turbo'` → `'boost'` w match trybu produkcji (×1.40 działa poprawnie)

---

## 18. Panel admina

Dostępny pod `/admin/`. Wymaga osobnego logowania (`AdminAuth`).

### Istniejące strony
| Plik | URL | Opis |
|---|---|---|
| `index.php` | `/admin` | Dashboard — statystyki, quick links |
| `players.php` | `/admin/players` | Zarządzanie graczami |
| `market.php` | `/admin/market` | Ręczna zmiana ceny, trendy rynkowe |
| `loans.php` | `/admin/loans` | Panel bankowy i kredyty |
| `wells.php` | `/admin/wells` | Zarządzanie odwiertami |
| `gm_tools.php` | `/admin/gm_tools` | GM Tools (reset, testy) |
| `logs.php` | `/admin/logs` | Logi gry (`game_debug.log`) |
| `chat.php` | `/admin/chat` | Moderacja czatu — historia, usuwanie, ban/mute, zgłoszenia |
| `finance.php` | `/admin/finance` | Dział finansowy — globalne statystyki, per-gracz, mnożniki |
| `financial-crisis.php` | `/admin/financial-crisis` | Kryzys finansowy — lista firm w warning/crisis, config, akcje |

### Konfiguracja gry (`well_config`)
Tabela klucz-wartość do przechowywania parametrów gry modyfikowanych bez deploy:
- Globalne mnożniki balansu (patrz §18b)
- Dowolne klucze konfiguracji używane przez serwisy

### Inicjalizacja
`admin/init.php` ładuje `src/init.php` + klasy admina: `AdminAuth`, `AdminLog`, `BankSettings`.

### Bezpieczeństwo
- Osobna sesja, osobne hasło admina
- CSRF protection (`CSRF::field()` / `CSRF::validateToken()`)
- Każda akcja logowana przez `AdminLog::log($action, $detail)`

### Bugfixy (06.04.2026)
- **`src/AdminLog.php` — TypeError** — `GameLog::error()` wywoływane z array zamiast `...Throwable` jako 3. argument; naprawiono
- **`admin/chat.php` — ENUM truncation** — username admina jako `target_type`; naprawiono: `'player'` + `$pid` dla akcji na graczu, pominięcie dla akcji globalnych

---

## 18b. Panel admina — sekcje transportu i balansu

Dodane w sesji 05.04.2026. Nawigacja admina podzielona na dwie grupy linków (separator `|`).

### Nowe strony
| Plik | URL | Opis | Priorytet |
|---|---|---|---|
| `transport.php` | `/admin/transport` | Konfiguracja mnożników per typ transportu | MUST |
| `transport_loss.php` | `/admin/transport_loss` | Monitoring strat transportowych (global, per gracz, per odwiert) | MUST |
| `market_debug.php` | `/admin/market_debug` | Debug rynku: cena, supply/demand, historia ticków | MUST |
| `pipelines.php` | `/admin/pipelines` | Panel rurociągów: stan, naprawa, wymuszanie awarii | SHOULD |
| `alerts.php` | `/admin/alerts` | Alerty systemowe z progami (cena, loss, warunki, gracze) | SHOULD |
| `balance.php` | `/admin/balance` | Quick Balance Panel — globalne mnożniki bez deploy | SHOULD |

### admin/transport.php
- Edytowalne mnożniki per typ (rurociąg / ciężarówki / tankowiec): `incident`, `disaster`, `wear`, `spiral`, `capacity_pct`, `opex_pct`
- Persystencja w tabeli `transport_config` (plik migracji: `sql/transport_config.sql`)
- Masowy UPDATE odwiertów danego typu (capacity/OPEX)
- Graceful fallback gdy tabela nie istnieje — wyświetla SQL do utworzenia

### admin/transport_loss.php
- Globalne pipeline loss (avg, max, krytyczne >15%)
- Loss per typ transportu z szacowanym kosztem OPEX/h
- Loss per warstwa geologiczna × typ
- Loss per gracz z transport mix
- Top 20 najgorszych odwiertów

### admin/market_debug.php
- Stan rynku: cena, base price, volatility, world_production, demand_index
- Globalna produkcja odwiertów per typ transportu
- Statystyki magazynów, szacowany pipeline loss
- Historia ceny z `price_history` (tabela, kolumny: `price`, `created_at`)
- Historia supply/demand z `market_supply_demand_log`
- Ekonomia per gracz (produkcja, magazyn, transport mix, status)
- Szacunkowy bilans supply/demand

### Historia ceny
`MarketTick::savePriceHistory` zapisuje każdy tick do tabeli `price_history` (kolumny: `price INT`, `created_at DATETIME`).
`MarketTick::getPriceHistory(hours)` zwraca historię z ostatnich N godzin (domyślnie 24h).

### Historia supply/demand
`MarketTick::saveSupplyDemandLog` zapisuje do `market_supply_demand_log` (kolumny: `supply`, `demand`, `ratio`, `price`, `created_at`). Rekordy starsze niż `LOG_KEEP_DAYS=7` dni są automatycznie usuwane.

### admin/pipelines.php
- Lista wszystkich `pipelines` z condition%, loss%, status, historia awarii
- Akcje: napraw, ustaw loss%, wymuś awarię
- Mass repair wszystkich krytycznych (condition < 30%)

> ⚠️ **Schemat `players`:** kolumna loginu to `username` (nie `login`). We wszystkich zapytaniach SQL w panelach admina należy używać `p.username` / `pl.username`. Alias `AS login` może być stosowany dla kompatybilności z kodem PHP.

### admin/alerts.php
Automatyczne alerty z progami:
| Typ alertu | Próg krytyczny | Próg ostrzegawczy |
|---|---|---|
| Cena ropy | > $140 lub < $40 | > $120 lub < $60 |
| Pipeline loss | > 15% | > 8% |
| Stan rurociągów | condition < 30% | condition < 50% |
| Odwierty | tech_condition < 20% | tech_condition < 40% |
| Blowout | > 0 aktywnych | — |
| Magazyny | > 95% pojemności | > 85% |
| Gracze ujemna kasa | > 0 | — |
| Cron zatrzymany | > 30 min bez ticku | > 15 min |

### admin/balance.php — Quick Balance Panel
7 globalnych mnożników zapisywanych w `well_config`, odczytywanych przez `cron/tick.php`:

| Klucz w `well_config` | Skrót w ticku | Zastosowanie |
|---|---|---|
| `global_incident_multiplier` | `incident` | `IncidentService::processTick` |
| `global_disaster_multiplier` | `disaster` | `WellService::processDisasterRoll` |
| `global_wear_multiplier` | `wear` | `WellService::processWear` (×2 wywołania) |
| `global_degradation_mult` | `degradation` | `WellService::processDegradation` |
| `global_loss_multiplier` | `loss` | transport loss ($lostOil) |
| `global_opex_multiplier` | `opex` | transport OPEX ($transportOpex) |
| `global_production_mult` | `production` | `WellService::getEffectiveProduction` |

Domyślnie wszystkie = `1.0` — tick zachowuje się identycznie jak przed wdrożeniem.
Zmiana widoczna od **następnego ticku** crona.

**Emergency Hotfix** — jednym kliknięciem ustawia grupę mnożników na podany współczynnik:
- 🔴 Incydenty + Katastrofy
- 💧 Loss + OPEX
- ⚠️ Wszystkie ryzyka
- ⬆️ Produkcja (buff)

---

## 18c. Panel admina — HR (`admin/hr.php`)

Dodano w sesji 12–13.04.2026. Wielozakładkowy panel zarządzania danymi HR z widokiem i edycją kandydatów, historii zatrudnienia, statystyk HR graczy oraz słowników specjalizacji.

### Pliki

| Plik | Rola |
|------|------|
| `admin/hr.php` | Kontroler — routing zakładek, obsługa POST, pobieranie danych |
| `templates/views/admin/hr/main.php` | Widok — HTML zakładek, formularze, siatki danych |
| `assets/css/admin.css` | Style HR panelu (`.spec-card`, `.spec-group`, `.add-spec-form`, `.btn-danger` itd.) |
| `lang/pl.php` | Klucze i18n dla całego panelu HR (`admin.hr.*`) |

### Zakładki

| Zakładka | URL parametr | Opis |
|----------|-------------|------|
| Kandydaci | `...tab=candidates` | Lista aktywnych kandydatów z filtrem |
| Historia | `...tab=history` | Historia zatrudnienia (per gracz, paginacja) |
| Statystyki HR | `...tab=stats` | Statystyki HR per gracz |
| Specjalizacje | `...tab=specializations` | Edycja `staff_specializations` i `hr_specializations` |

### Zakładka Specjalizacje — `staff_specializations`

Specjalizacje techniczne pogrupowane po polu `role` w osobnych sekcjach:

- **⛏ Operatorzy** — `role = operator`
- **🔧 Technicy** — `role = technician`
- **Pozostałe** — pozostałe wartości `role`

Każda specjalizacja wyświetlana jako `<details>` (`.spec-card`). Po rozwinięciu — formularz edycji z polami:

| Pole | Opis |
|------|------|
| `spec_name` | Polska nazwa (edytowalna, zapisywana w `staff_specializations.name`) |
| `prod_bonus` | Bonus produkcji (0.0–1.0) |
| `wear_reduction` | Redukcja zużycia (0.0–1.0) |
| `incident_reduction` | Redukcja incydentów (0.0–1.0) |
| `spiral_reduction` | Redukcja spirali (0.0–1.0) |
| `repair_speed` | Szybkość naprawy (0.0–1.0) |
| `incident_return_reduction` | Redukcja powrotu awarii (0.0–1.0) |
| `catastrophe_reduction` | Redukcja katastrof (0.0–1.0) |

**Dodawanie nowej specjalizacji technicznej** — formularz z polami: `code` (snake_case), `name` (PL), `role`, `rarity`. POST handler: `add_spec`.

**Usuwanie** — przycisk **Usuń** z modalem potwierdzenia (`confirmAction`, `type:'danger'`). POST handler: `delete_spec`.

### Zakładka Specjalizacje — `hr_specializations`

Specjalizacje kandydatów HR pogrupowane po polu `department` w osobnych sekcjach z nagłówkiem `📂 {dział}`.

Każdy wiersz edytowalny inline: `name`, `department`, `rarity` (select). Przyciski: **Zapisz** i **Usuń**.

**Dodawanie** — formularz z polami: `code`, `name` (PL), `department`, `rarity`. POST handler: `add_hr_spec`.

**Usuwanie** — modal potwierdzenia. POST handler: `delete_hr_spec`.

### POST handlery (`admin/hr.php`)

| `$_POST` key | Akcja |
|---|---|
| `add_spec` | INSERT do `staff_specializations` (walidacja: code/name wymagane, unique) |
| `delete_spec` | DELETE FROM `staff_specializations` WHERE code |
| `save_spec` | UPDATE `staff_specializations` — nazwa + wszystkie pola perków |
| `add_hr_spec` | INSERT do `hr_specializations` |
| `delete_hr_spec` | DELETE FROM `hr_specializations` WHERE id |
| `save_hr_spec` | UPDATE `hr_specializations` — name, department, rarity |

Każdy handler: CSRF (`CSRF::validateToken()`), `AdminLog::log()`, try/catch z komunikatem błędu.

### Konwencja pola `code`

- Snake_case, tylko `[a-z0-9_]`
- Musi być unikalny w obrębie tabeli
- Przykłady: `drilling_specialist`, `safety_bph`, `hr_legal_analyst`
- Używany jako klucz w `technical_staff.specialization` (łącznik między tabelami)

### CSS (nowe klasy w `assets/css/admin.css`)

| Klasa | Zastosowanie |
|-------|-------------|
| `.spec-card` | Karta `<details>` specjalizacji technicznej |
| `.spec-card-summary` | Nagłówek karty (flex: nazwa, kod, badge rzadkości) |
| `.spec-form` | Formularz edycji wewnątrz karty |
| `.spec-fields` | Grid pól formularza (auto-fill, min 200px) |
| `.spec-field--full` | Pole na pełną szerokość gridu (nazwa PL) |
| `.spec-group` | Kontener sekcji (np. Operatorzy) |
| `.spec-group-title` | Nagłówek sekcji — uppercase, border-bottom |
| `.spec-group-inline` | Kontener sekcji dla hr_specializations |
| `.spec-group-subtitle` | Nagłówek podsekecji działu |
| `.spec-delete-form` | Formularz usuwania (flex, justify-content: flex-end) |
| `.add-spec-form` | Formularz dodawania nowej specjalizacji |
| `.add-spec-fields` | Flex-wrap pola formularza dodawania |
| `.spec-field--action` | Kontener przycisku submit (padding-top wyrównuje z inputami) |
| `.btn-danger` | Czerwony przycisk usuwania |

### i18n — nowe klucze (`lang/pl.php`)

```
admin.hr.section_staff_specs       admin.hr.section_hr_specs
admin.hr.staff_specs_desc          admin.hr.hr_specs_desc
admin.hr.btn_add_spec              admin.hr.add_spec_hint
admin.hr.field_code                admin.hr.field_name_pl
admin.hr.field_role                admin.hr.field_prod_bonus
admin.hr.field_wear_reduction      admin.hr.field_incident_reduction
admin.hr.field_spiral_reduction    admin.hr.field_repair_speed
admin.hr.field_incident_return     admin.hr.field_catastrophe_reduction
admin.hr.col_spec_name             admin.hr.col_department
admin.hr.col_rarity                admin.hr.confirm_delete_spec
admin.hr.msg_spec_saved            admin.hr.msg_spec_deleted
admin.hr.msg_spec_added            admin.hr.msg_hrspec_added
admin.hr.msg_hrspec_deleted        admin.hr.err_spec_empty
admin.hr.err_spec_duplicate        common.delete
```

### Modal systemu potwierdzeń

Przyciski **Usuń** używają `confirmAction()` z `modal.js` (globalny system zastępujący `confirm()`):

```js
confirmAction('Czy na pewno usunąć......', function() {
    document.getElementById('del-spec-{code}').submit();
}, { type: 'danger', confirmLabel: 'Usuń' });
```

- `modal.js` ładowany przez `admin/partials/footer.php` (dodano `require_once footer.php` do `admin/hr.php`)
- Formularz delete ma unikalny `id` (`del-spec-{code}` / `del-hs-{id}`) — niezależny od `display:contents`

---

## 18d. Panel admina — moderacja czatu (`admin/chat.php`)

Dodane w sesji 06.04.2026.

### Funkcje
- Podgląd historii czatu z filtrem (gracz, data, paginacja)
- Soft delete wiadomości (`is_deleted=1`) — usunięte wiadomości niewidoczne dla graczy
- Ban/mute gracza na czas (1h/3h/12h/1d/3d/7d/14d/30d/90d) lub permanentnie
- Odblokowanie gracza
- Wysyłanie komunikatu jako `[ADMIN]` (`player_id=NULL`)
- Czyszczenie całego czatu globalnego (soft delete)
- Top nadawców, statystyki
- **Otwarte zgłoszenia** (`chat_reports`) z akcjami: Usuń wiadomość / Oznacz jako rozwiązane

---

## 19. Profil gracza

### Strona `/profile` (`profile.php`)
- Zmiana hasła, nazwy firmy, upload avatara
- Statystyki: liczba odwiertów, aktywne odwierty, historia sprzedaży

### Upload avatara — az.pl (PHP-FPM)

Serwer produkcyjny az.pl ma dwa ograniczenia dla uploadów:
1. **SAPI = `fpm-fcgi`** — dyrektywy `php_value` w `.htaccess` są ignorowane (działają tylko z `mod_php`). Użycie `php_value upload_tmp_dir "/tmp"` powoduje, że PHP ustawia temp dir poza `open_basedir` → `move_uploaded_file()` failuje.
2. **WAF (ModSecurity)** blokuje body żądań HTTP ≥ ~8 KB dla `application/octet-stream`.

**Rozwiązanie:**
- `.htaccess`: usunięte bloki `<IfModule mod_php.c>` i `<IfModule mod_php8.c>` z `php_value upload_tmp_dir`
- `templates/views/profile/main.php`: `compressAvatarBlob(file)` — kompresja po stronie klienta przez Canvas API:
  - Max rozmiar: 96×96px
  - Format: JPEG quality 0.82 → ~4 KB (bezpieczne poniżej progu WAF)
  - Wysyłka: `fetch()` z `Content-Type: image/jpeg` + nagłówek `X-CSRF-Token`
- `profile.php`: obsługa raw body (`file_get_contents('php://input')`) z wykrywaniem MIME przez `finfo_buffer()`

### Licznik aktywnych odwiertów
Liczy odwierty ze wszystkimi statusami operacyjnymi — wykluczone są tylko `seized` i `blowout`:
```sql
SELECT COUNT(*) FROM wells
WHERE player_id = ... AND status NOT IN ('seized', 'blowout')
```

**Zmiana (03.04.2026):** Wcześniej liczono tylko `status='active'` — odwierty z `no_operator`, `paused_cash` etc. były ignorowane, co dawało wynik 0 przy aktywnych odwiertach.

---

## 20. Sala Zarządu (Boardroom)

`boardroom.php` — sala zarządu z dynamicznym tłem zmieniającym się w zależności od zatrudnionych pracowników.

### System dynamicznych teł
Tło sali dobierane jest na podstawie obsadzonych ról **i płci** pracowników.

**Format nazwy pliku:**
```
boardroom_bg_[rola1]_[płeć1]_[rola2]_[płeć2].png
```

Przykłady:
```
brak pracowników          → boardroom_bg.png
Dyrektor + Kobieta HR     → boardroom_bg_hr_F.png
Dyrektor + Mężczyzna HR   → boardroom_bg_hr_M.png
Dyrektor + HR(K) + Tech(M)→ boardroom_bg_hr_F_tech_M.png
```

### Kolejność ról w nazwie (zawsze ta sama)
1. `hr`
2. `tech`
3. `finance`
4. `legal`
5. `logistics`

**Format płci:** `M` = mężczyzna, `F` = kobieta

### Sloty przy stole (kąty od góry)
| Slot | Kąt | Rola |
|---|---|---|
| #0 | 348° | Dyrektor (zawsze) |
| #1 | 9° | HR |
| #2 | 40° | Technical |
| #3 | 58° | Finance |
| #4 | 92° | Legal |
| #5 | 140° | Logistics |

### Priorytety generowania obrazów
| Priorytet | Plik | Opis |
|---|---|---|
| Konieczny | `boardroom_bg.png` | Sam dyrektor (start gry) |
| Konieczny | `boardroom_bg_hr_M/F.png` | Dyrektor + HR |
| Ważny | `boardroom_bg_hr_*_tech_*.png` | + Technical |
| Ważny | `boardroom_bg_hr_*_tech_*_finance_*.png` | + Finance |
| Opcjonalny | + `legal`, + `logistics` | Pełny zarząd |

### Fallback
Jeśli plik graficzny nie istnieje → system ładuje `boardroom_bg.png` (domyślny).

### Specyfikacja graficzna
- Rozdzielczość: **1920×1080px** lub wyższa
- Format: **PNG**
- Ta sama sala i oświetlenie na wszystkich obrazach — zmieniają się tylko osoby na krzesłach
- Puste krzesła powinny być widoczne

### Testowanie
1. Wgraj obrazy przez panel admina → Edytor szablonu → zakładka **Boardroom** → sekcja **Tła sceny zarządu**
2. Otwórz `boardroom.php`
3. Konsola przeglądarki (F12) pokazuje: `Background loaded: boardroom_bg_hr_F.png`

> Pełna dokumentacja dla grafika: `BOARDROOM_BACKGROUNDS_GUIDE.md`
> `IMAGES_GUIDE.md` jest przestarzały — zastąpiony przez powyższy przewodnik.

### Panel admina — manager teł sceny
Zakładka **Edytor szablonu → Boardroom → Tła sceny zarządu** (`admin/template_editor.php`):

- **Siatka istniejących plików** — miniaturki wgranych teł z możliwością usunięcia (przycisk ✕, handler `delete_boardroom_bg`)
- **Upload nowego tła** — checkboxy ról + select płci (M/F/—); nazwa pliku generowana automatycznie na podstawie wybranych ról w kolejności sceny
- Plik zapisywany bezpośrednio do `assets/images/boardroom_bg_{combo}.png`
- System JS (`boardroom-dynamic.js`) wybiera najbardziej pasujący istniejący plik — fallback do `boardroom_bg.png`

#### Mechanizm uploadu — chunked AJAX (az.pl)

Hosting az.pl ma dwa ograniczenia blokujące standardowy upload:
1. **Brak katalogu tymczasowego PHP** (`UPLOAD_ERR_NO_TMP_DIR`) — `$_FILES` nie działa
2. **WAF (ModSecurity) blokuje body ≥ 16 KB** — `$_POST` i `php://input` są puste dla żądań z body > 16 KB

Rozwiązanie: upload jako surowe bajty binarne w chunked AJAX:

- JS czyta plik przez `file.arrayBuffer()` (brak konwersji base64)
- Dane dzielone na chunki po **8 KB** (`application/octet-stream`)
- Metadane (`bg_name`, `bg_file_mime`, `chunk_index`, `total_chunks`, `csrf_token`, `upload_id`) wysyłane jako **parametry GET** — omijają WAF całkowicie
- PHP wykrywa żądanie przez `$_GET['ajax_upload'] === '1'` + nagłówek `X-Requested-With: XMLHttpRequest`
- Chunki zapisywane tymczasowo do `assets/images/boardroom/` jako `.ub_{uploadId}_{bgName}_{idx}`
- Po ostatnim chunku — łączenie pliku i zapis do `assets/images/boardroom_bg_{name}.png`
- `upload_id` generowany po stronie JS (`Math.random().toString(36)`) — unikalny identyfikator sesji uploadu

**Pliki kontrolera/widoku:**
- `admin/template_editor.php` — blok AJAX na samym początku pliku (przed `$_codexGuardStart`); handlery `save_boardroom_bg`, `delete_boardroom_bg`; `$brBgMatrix` z macierzą kombinacji
- `templates/views/admin/template_editor/main.php` — sekcja UI z podglądem generowanej nazwy pliku (live JS), chunked upload bez `<form enctype="multipart/form-data">`
- `assets/css/admin.css` — klasy `.br-bg-*`

---

## 21. Bezpieczeństwo

- Sesje PHP + `Auth::requireLogin()` na chronionych stronach
- Bcrypt dla haseł
- CSRF token na każdym żądaniu POST/AJAX
- PDO prepared statements wszędzie
- `src/`, `config/`, `cron/` zablokowane w `.htaccess`

---

## 22. System czatu graczy

### Zakres systemu

System komunikacji sklada sie z trzech warstw:
- czat globalny osadzony w glownej grze,
- wiadomosci prywatne pod `/dm`,
- panel moderacji admina pod `/admin/chat`.

Glowne pliki:
- `templates/components/chat.php` - komponent czatu globalnego,
- `assets/js/chat.js` - frontend czatu globalnego i paska przypietych wiadomosci,
- `dm.php` + `templates/views/dm/main.php` - strona DM,
- `assets/js/dm.js` - frontend prywatnych rozmow,
- `src/ChatApi.php` - wspolny backend dla globala i DM,
- `src/DmApi.php` - cienki wrapper delegujacy do `ChatApi.php`,
- `admin/chat.php` + `templates/views/admin/chat/main.php` - moderacja i raporty.

---

### Czat globalny

Funkcje dzialajace obecnie:
- polling co 4 sekundy (`GET /src/ChatApi.php?since=id`),
- pierwsze ladowanie pobiera ostatnie 50 wiadomosci,
- nazwa nadawcy to `company_name`, a gdy brak - `username`,
- limit 300 znakow,
- rate limit: 1 wiadomosc na 2 sekundy,
- flood protection: 5+ wiadomosci w 30 sekund skutkuje automatycznym mute na 5 minut,
- soft delete przez `is_deleted=1`,
- przypiete komunikaty admina (`is_admin=1`, `is_pinned=1`) wyswietlane sa w osobnym pasku nad strumieniem,
- awatary graczy sa renderowane z `players.avatar_path`, a fallbackiem jest inicjal,
- przy cudzej wiadomosci sa dostepne akcje `Zglos` i `DM`,
- zgloszenie zapisuje sie do `chat_reports` przez `action=report`.

Dodatkowe uwagi implementacyjne:
- `ChatApi.php` wykrywa runtime'owo, czy tabela ma kolumny `is_admin` i `is_pinned`; gdy ich nie ma, zwraca fallback `0 AS is_admin, 0 AS is_pinned`,
- pinned messages nie sa zwracane w zwyklym streamie `since`, tylko osobno przez `pinned_only=1`,
- `chat.js` usuwa ewentualne duplikaty przypietych wiadomosci z glownego strumienia po wyrenderowaniu paska pinned.

---

### Wiadomosci prywatne (DM)

Aktualny stan `/dm`:
- sidebar z lista rozmow i licznikami nieprzeczytanych wiadomosci,
- przycisk `+ Nowa` z lista graczy (limit 50),
- osobny widok rozmowy z pollingiem co 4 sekundy,
- ustawienie `Enter wysyla` zapisywane w `localStorage`,
- ustawienie `Powiadomienia dzwiekowe` zapisywane w `localStorage`,
- prosty sygnal audio dla nowej przychodzacej wiadomosci,
- pasek narzedzi kompozytora: zalacznik, emoji, menu ustawien,
- wysylka obrazow tylko w DM (JPG, PNG, GIF, WEBP),
- limit zalacznika: 3 MB,
- upload chunked przez `POST application/octet-stream` z naglowkami `X-Upload-*`,
- rozmiar chunka w `dm.js`: `12288` bajtow (12 KB),
- podglad obrazka przed wyslaniem,
- mozliwosc usuniecia obrazka z juz wyslanej prywatnej wiadomosci (`action=delete_attachment`),
- backend i frontend DM korzystaja z tego samego `src/ChatApi.php`; `src/DmApi.php` tylko przekazuje ruch dalej.

Wazne doprecyzowania wobec starszych opisow:
- DM nie korzysta juz z prostych globalnych zmiennych `DM_API`, `MY_ID`, `WITH_ID`; obecnie strona przekazuje konfiguracje przez `window.DM_CONFIG`,
- layout DM jest stylowany w `assets/css/chat.css`, nie w `assets/css/style.css`,
- upload chunkow nie idzie parametrami GET - idzie surowym body POST + naglowkami HTTP,
- attachmenty sa obecnie obslugiwane tylko dla wiadomosci prywatnych, nie dla czatu globalnego.

Rzeczy nadal niewdrozone:
- brak lightboxa/modala do powiekszania wyslanych obrazow,
- brak osobnego centrum powiadomien DM poza samym widokiem rozmow,
- brak zalacznikow w czacie globalnym.

---

### Panel admina (`admin/chat.php`)

Realnie dzialajace funkcje:
- historia czatu z filtrem po graczu i paginacja,
- soft delete pojedynczej wiadomosci,
- soft delete wszystkich wiadomosci wybranego gracza,
- wysylka komunikatu jako `[ADMIN]`,
- przypinanie i odpinanie wiadomosci admina,
- lista aktywnych banow / mute'ow,
- ban na czas lub permanentny,
- odblokowanie gracza,
- reczne usuwanie wygaslych wiadomosci globalnych,
- auto-czyszczenie czatu na podstawie `well_config`,
- podglad otwartych zgloszen z `chat_reports`,
- akcje dla zgloszen: usun wiadomosc albo oznacz jako rozwiazane,
- lista blokowanych slow (`chat_blocked_words`) z wlaczaniem, wylaczaniem i usuwaniem wpisow.

Doprecyzowania:
- panel ma filtr po graczu, ale nie ma jeszcze osobnego filtra po dacie,
- otwarte zgloszenia sa wyswietlane w panelu admina tylko wtedy, gdy tabela `chat_reports` istnieje i zawiera rekordy,
- akcja `delete_msg` na zgloszeniu usuwa wiadomosc, ale nie zamyka automatycznie raportu; do tego sluzy osobna akcja `resolve_report`.

Widok admina po aktualnym stanie kodu:
- historia wiadomosci jest renderowana przez klasy `chat-list`, `chat-list-head`, `chat-list-row`,
- lista banow i raportow korzysta z `data-list` / `list-row`,
- nadal sa tam fragmenty starszego markupu i drobne pozostalosci kodowania do przyszlego cleanupu, ale funkcje moderacyjne dzialaja.

---

### Routing i bezpieczenstwo

- `/dm` routuje do `dm.php`,
- `src/ChatApi.php` i `src/DmApi.php` sa dopuszczone przez glowny `.htaccess`,
- odpowiedzi API sa zwracane jako JSON (`Content-Type: application/json; charset=utf-8`),
- frontend uzywa `credentials: 'same-origin'`,
- render tekstu przechodzi przez `escHtml()` przed podstawieniem do DOM,
- przy uploadzie DM pliki tymczasowe trafiaja do `sessions/chat_uploads`, a finalne do `assets/uploads/chat`.

---

### Bugfixy i rozwoj

#### 06-07.04.2026
- dodano route `/dm`,
- usunieto blokade `src/.htaccess`,
- poprawiono FK / legacy problemy z wiadomosciami admina,
- naprawiono stare bledy zgodnosci po migracjach schematu czatu.

#### 15.04.2026
- logika DM zostala wyciagnieta do `assets/js/dm.js`,
- widok admina czatu zostal uporzadkowany po stronie HTML/CSS.

#### 02.05.2026
- przypinanie wiadomosci admina zostalo dopiete do panelu admina i frontu globalnego czatu,
- dodano obsluge usuwania wygaslych wiadomosci i brakujace klucze i18n.

#### 05.05.2026
- dodano awatary graczy w czacie globalnym i DM,
- `ChatApi.php` dostal fallback dla brakujacych kolumn `is_admin` / `is_pinned`,
- DM dostalo nowy layout, ustawienia Enter/dzwiek, upload obrazow przez chunki 12 KB, preview i kasowanie zalacznikow.

#### 07.05.2026
- **Weryfikacja e-mail** — nowi gracze muszą potwierdzić adres e-mail przed pierwszym logowaniem; token ważny 24h; istniejący gracze automatycznie oznaczeni jako zweryfikowani (`sql/email_verification.sql`).
- **Branded e-mail wrapper** — `src/EmailTemplate.php` generuje OilCorp HTML dla wszystkich e-maili (weryfikacja, reset hasła, newsletter).
- **Auth background** — strony logowania, rejestracji, resetowania hasła używają `assets/css/auth.css` + `$authPage = true` w headerze; tło z obrazu `assets/images/oilcorp_bg.jpg` (do umieszczenia ręcznie).
- **Newsletter panel admina** — `/admin/newsletter.php` z edytorem TinyMCE; wysyłka do wszystkich zweryfikowanych graczy; historia wysyłek w tabeli `newsletter_log`.
- **Poprawka emoji w DM** — `assets/js/emoji.js` jako wspólny moduł dla chat.js i dm.js (`window.EmojiLib`).
- **Poprawka zakładek modułowych** — usunięto `overflow-x: auto` z `.module-tabs`, dodano `flex-wrap: wrap`.
- **Tabela w pomocy** — `assets/css/help.css` rozszerzony o `.help-content table` (TinyMCE nie dodaje klas CSS do `<table>`).

---

### Roadmap / TODO dla chatu

- lightbox dla obrazow w DM,
- centralne powiadomienia o nowych prywatnych wiadomosciach poza samym widokiem `/dm`,
- ewentualne SSE/WebSocket zamiast pollingu,
- dalsze porzadki i18n / cleanup starszego markupu w `admin/chat.php` i `templates/components/chat.php`.

---
## 23. Dział Finansowy

Centralny system finansowy OilCorp. Agreguje przychody i koszty z wszystkich systemów gry per tick, zapisuje historię i udostępnia analizę.

### Architektura

| Plik | Rola |
|------|------|
| `src/FinanceService.php` | Serwis: zapis ticków, agregaty, alerty, admin stats |
| `public/finance.php` | Strona `/finance` — panel gracza |
| `admin/finance.php` | Panel admina `/admin/finance` |
| `assets/js/finance.js` | Wykres Chart.js (linia: przychód/koszty/zysk/straty) |
| `assets/css/finance.css` | Style panelu finansowego |
| `finance_migration.sql` | CREATE TABLE `finance_logs` |

### Baza danych — `finance_logs`

Zapisywana co tick (5 min) per gracz:

| Kolumna | Opis |
|---------|------|
| `revenue` | bbl × cena × (1-loss) |
| `gross_revenue` | bbl × cena przed stratami |
| `opex` | OPEX odwiertów |
| `salary_cost` | Pensje zarząd + technicy |
| `transport_cost` | Transport OPEX |
| `incident_cost` | Naprawy, kary |
| `tax` | Podatek regionalny |
| `loss_bbl` / `loss_value` | Straty transportu (bbl + PLN) |
| `net_profit` | revenue - wszystkie koszty |
| `cash_after` | Stan kasy po ticku |
| `oil_price` | Cena ropy w tym ticku |
| `bbl_produced` | Baryłki netto |
| `wells_active` | Liczba aktywnych odwiertów |

### Logika finansowa (tick)

```
gross_revenue = bbl × cena_ropy
revenue       = bbl_netto × cena  (po odjęciu lostOil)
loss_value    = lostOil × cena
net_profit    = revenue - opex - salary - transport_opex - incident_cost - tax
```

### Panel gracza (`/finance`)

- **4 karty top bar:** Saldo, Zysk/tick, Zysk/godzina (×12), Straty transport %
- **Wykres liniowy 24h/7dni:** przychód, koszty, zysk netto, straty
- **Breakdown struktury finansowej:** przychody / koszty (7 kategorii) / straty netto
- **Tabela per odwiert:** produkcja/h, OPEX/h, transport%, podatek%, szacowany zysk/h
- **Alerty automatyczne:** strata netto, loss >15%, brak produkcji, podatki >20%

### Rozszerzenie: Finanse a huby logistyczne (13.05.2026)

Etap 1 rozbudowy finansów został podpięty do systemu hubów logistycznych. Dział finansowy nie pokazuje już tylko klasycznych kosztów odwiertów i transportu, ale także osobne dane z infrastruktury logistycznej.

#### Nowe pola w `finance_logs`

| Kolumna | Opis |
|---------|------|
| `hub_usage_cost` | Opłaty za użycie hubów logistycznych |
| `hub_loss_bbl` / `hub_loss_value` | Straty przeciążenia hubów |
| `fallback_loss_bbl` / `fallback_loss_value` | Straty odwiertów obsługiwanych fallbackiem bez aktywnego huba |
| `hub_incident_loss_bbl` / `hub_incident_loss_value` | Straty wywołane incydentami hubów |

#### Integracja z tickiem

- `WellLoopSection` agreguje teraz osobno:
  - koszt użycia hubów,
  - straty przeciążenia hubów,
  - straty odwiertów bez huba,
  - straty incydentów hubów.
- `PlayersSection` przekazuje te dane do `FinanceService::saveTick()`.
- `FinanceService` zapisuje je w `finance_logs`, zwraca w podsumowaniach i używa do alertów.

#### Nowe sekcje panelu gracza (`/finance`)

- **Straty logistyki** w górnym KPI
- **Huby logistyczne i odwierty bez huba**:
  - koszt użycia hubów,
  - straty hubów,
  - straty odwiertów bez huba,
  - straty incydentów hubów
- **Breakdown kosztów i strat** został rozszerzony o osobne pozycje hubowe
- Alerty finansowe mogą teraz wskazywać:
  - wysokie straty hubów,
  - wysokie koszty użycia hubów,
  - straty fallbacku,
  - straty incydentów hubów

#### Panel admina (`/admin/finance`)

- globalne statystyki zostały rozszerzone o:
  - koszt hubów,
  - straty hubów,
  - straty fallbacku,
  - straty incydentów hubów
- tabela per gracz pokazuje dodatkowo:
  - koszt hubów,
  - łączną wartość strat hubowych

#### Migracja / pliki

- `sql/finance_hub_metrics_2026_05_13.sql`
- `src/FinanceService.php`
- `src/Tick/WellLoopSection.php`
- `src/Tick/PlayersSection.php`
- `public/finance.php`
- `templates/views/public/finance/main.php`
- `admin/finance.php`
- `templates/views/admin/finance/main.php`
- `lang/pl.php`

### Panel admina (`/admin/finance`)

- Globalne statystyki (przychód, wynik netto, straty, produkcja, gracze aktywni)
- Tabela per gracz: saldo, przychód, wynik netto, straty, avg/tick
- **Config panel:** 3 globalne mnożniki w `well_config` (tax, cost, loss) — efekt od następnego ticku

### Integracja z tick.php

Zmienne agregujące per pętla gracza:
`$finRevenue`, `$finGross`, `$finOpex`, `$finSalary`, `$finTransport`, `$finIncident`, `$finTax`, `$finLossBbl`, `$finLossValue`, `$finBbl`, `$finWellsActive`

`FinanceService::saveTick()` wywoływana przed `UPDATE players SET cash`.


## Specjalizacje pracowników (perki)

Każdy pracownik techniczny może mieć maksymalnie 1 specjalizację (perk), losowaną przy zatrudnieniu.

> **Zarządzanie słownikami** specjalizacji dostępne w panelu admina: `admin/hr.php...tab=specializations` — zob. §18d.

### Operator (`staff_specializations` WHERE role = 'operator')
| Kod | Polska nazwa | Efekt | Rarity |
|-----|-------------|-------|--------|
| `drilling_specialist` | Specjalista Wiercenia | +7.5% produkcji (tylko deep/ultra), -10% wear | rare |
| `pressure_control` | Kontrola Ciśnienia | -15% szansy awarii, -10% boost spirali | uncommon |

### Technik (`staff_specializations` WHERE role = 'technician')
| Kod | Polska nazwa | Efekt | Rarity |
|-----|-------------|-------|--------|
| `electronics_specialist` | Specjalista Elektroniki | -25% czasu naprawy, -10% powrotu awarii | uncommon |
| `mechanical_specialist` | Specjalista Mechaniczny | -20% wear, -15% awarii sprzętu | common |
| `safety_specialist` | Specjalista BHP | -12.5% katastrof, -10% boost spirali | rare |

### Specjalizacje kandydatów HR (`hr_specializations`)
Losowane przy generowaniu kandydata. Pogrupowane po polu `department` (np. `finance`, `legal`, `hr`, `logistics`).
Zarządzanie w `admin/hr.php...tab=specializations` — dodawanie, edycja nazwy/działu/rzadkości, usuwanie.

### Szansa losowania specjalizacji technicznej
- Bazowa: 5% + 1% za każdy skill ponad 5 (skill 10 = 10%)
- Wagi: common=60%, uncommon=30%, rare=10%

### Konwencja `code`
- Snake_case, tylko `[a-z0-9_]`, unikalny w tabeli
- Powiązanie: `technical_staff.specialization = staff_specializations.code`
- Nowe kody tworzone wyłącznie w panelu admina (§18d)

### Implementacja
- `staff_specializations` — tabela definicji perków (edytowalna przez admina)
- `hr_specializations` — tabela definicji specjalizacji kandydatów (edytowalna przez admina)
- `technical_staff.specialization` — przypisany perk pracownika
- `HRService::rollStaffSpecialization()` — losowanie przy zatrudnieniu
- `cron/tick.php` — zastosowanie efektów (produkcja, wear, incydenty, spirala)
- `IncidentService::processTick()` — redukcja szansy incydentów
- UI: badge specjalizacji w karcie pracownika (`technical.php`, `well_staff` modal)

---

## 27. System Aktualności (Admin News)

Panel aktualności zintegrowany z dashboardem gry. Admin tworzy newsy widoczne dla wszystkich graczy w bocznym panelu.

### Architektura

| Plik | Rola |
|------|------|
| `admin/news.php` | Kontroler admina — CRUD newsów (add/edit/delete/pin/unpin), `$viewData`, obsługa POST |
| `templates/views/admin/news/main.php` | Widok panelu admina — layout kart zamiast tabeli |
| `src/AdminNewsApi.php` | Publiczny endpoint AJAX (GET) — pobiera newsy dla graczy |
| `assets/js/chat.js` | Moduł `NEWS PANEL` — polling co 60s, renderowanie |
| `assets/css/admin.css` | Style kart newsów (`.news-card`, `.news-admin-layout` itp.) |
| `lang/pl.php` | Klucze `admin.news.*` + `admin.nav.news` |

### Baza danych — tabela `admin_news`

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | INT AUTO_INCREMENT | PK |
| `title` | VARCHAR(120) | Tytuł aktualności |
| `content` | TEXT | Treść |
| `is_pinned` | TINYINT(1) | 1 = przypięta (pokazywana na górze) |
| `pinned_at` | DATETIME NULL | Czas przypięcia |
| `active` | TINYINT(1) | 1 = widoczna dla graczy |
| `created_by` | VARCHAR(60) | Login admina |
| `created_at` | DATETIME | Data dodania |
| `updated_at` | DATETIME | Data edycji |

### Funkcje panelu admina (`admin/news.php`)

| Akcja POST | Opis |
|------------|------|
| `add` | INSERT do `admin_news` (title, content, created_by) |
| `edit` | UPDATE title + content WHERE id |
| `delete` | `SET active = 0` (soft delete) |
| `pin` | `SET is_pinned = 1, pinned_at = NOW()` (maks. 3 przypięte — walidacja) |
| `unpin` | `SET is_pinned = 0, pinned_at = NULL` |

Każda akcja zabezpieczona przez `CSRF::validateToken()`.

### API dla graczy (`src/AdminNewsApi.php`)

Endpoint: `GET /src/AdminNewsApi.php`

Odpowiedź JSON:
```json
{
  "news": [
    {
      "id": 5,
      "title": "Nowa wersja 1.4",
      "content": "Dodano obsługę warstw głębokich...",
      "is_pinned": 1,
      "created_by": "admin",
      "created_at": "2026-05-02 12:00:00",
      "date_fmt": "02.05.2026"
    }
  ]
}
```

Zapytanie SQL: `WHERE active = 1 ORDER BY is_pinned DESC, created_at DESC LIMIT 20`

### Renderowanie po stronie gracza (`assets/js/chat.js`)

Moduł IIFE `NEWS PANEL`:
- `loadNews()` — `fetch('/src/AdminNewsApi.php')` → `renderNews(data.news)`
- `renderNews(items)` — buduje HTML w `#newsList`; przypięte newsy otrzymują klasę `.news-item--pinned` i badge 📌
- `initNews()` — inicjuje przy `DOMContentLoaded`; polling co **60 sekund**
- `escHtml()` — lokalna kopia (moduł jest osobnym IIFE)

### Widok admina (`templates/views/admin/news/main.php`)

Dwukolumnowy layout (`.news-admin-layout`):
- **Lewa kolumna** — formularz dodaj/edytuj (`.news-form-panel`): pola `title` + `content`, dynamiczny nagłówek ✏️ Edytuj / ➕ Dodaj, przycisk Anuluj przy edycji
- **Prawa kolumna** — lista kart (`.news-card-list`): każda karta zawiera ID, badge (📌 Przypięta / ✓ Aktywna), datę + godzinę, tytuł, podgląd treści (120 znaków), autora, przyciski ✏️ Edytuj / 📌 Przypiń-Odepnij / 🗑 Usuń

### .htaccess — whitelist

`src/AdminNewsApi.php` dodane do **dwóch** miejsc w `.htaccess`:
1. Blok wyjątków (`RewriteCond %{REQUEST_URI} ^/src/AdminNewsApi\.php$`)
2. Negatywny lookahead reguły blokującej (`!^/src/(…|AdminNewsApi\.php)$`)

Bez obu wpisów endpoint zwracał 403 Forbidden.

### i18n (`lang/pl.php`)

| Klucz | Wartość |
|-------|---------|
| `admin.news.heading` | Aktualności |
| `admin.news.add_heading` | Dodaj aktualność |
| `admin.news.edit_heading` | Edytuj aktualność |
| `admin.news.title_label` | Tytuł |
| `admin.news.content_label` | Treść |
| `admin.news.submit_add` | Dodaj |
| `admin.news.submit_edit` | Zapisz zmiany |
| `admin.news.btn_cancel` | Anuluj |
| `admin.news.btn_edit` | Edytuj |
| `admin.news.btn_pin` | Przypiń |
| `admin.news.btn_unpin` | Odepnij |
| `admin.news.btn_delete` | Usuń |
| `admin.news.delete_confirm` | Na pewno usunąć tę aktualność... |
| `admin.news.status_pinned` | Przypięta |
| `admin.news.status_active` | Aktywna |
| `admin.news.list_empty` | Brak aktualności. |
| `admin.nav.news` | Aktualności |

---

## 28. System Newsletter

Wysyłka masowych e-maili do graczy z poziomu panelu admina. Admin komponuje wiadomość przez edytor TinyMCE i wysyła ją do wszystkich subskrybentów lub do jednego konkretnego gracza.

### Architektura

| Plik | Rola |
|------|------|
| `admin/newsletter.php` | Kontroler — compose, preview, send (all / single), logowanie do `newsletter_log` |
| `templates/views/admin/newsletter/main.php` | Widok panelu — formularz TinyMCE, stats bar, historia kampanii |
| `assets/js/newsletter_editor.js` | Inicjalizacja TinyMCE (selector `#nl-content`, ciemny motyw) |
| `src/Mailer.php` | Wysyłka przez SMTP (PHPMailer) |
| `src/EmailTemplate.php` | Szablon HTML e-maila z brandingiem OilCorp (złoty motyw) |
| `public/newsletter_unsubscribe.php` | Strona wypisania się — walidacja tokenu, `newsletter_subscribed = 0` |
| `sql/newsletter_subscription.sql` | Migracja MySQL — kolumny `newsletter_subscribed`, `newsletter_token` w tabeli `players` |

### Baza danych

#### Kolumny w tabeli `players`

| Kolumna | Typ | Domyślnie | Opis |
|---------|-----|-----------|------|
| `newsletter_subscribed` | TINYINT(1) | `1` | 0 = wypisany, 1 = subskrybuje |
| `newsletter_token` | VARCHAR(32) | NULL | Stały token do linku wypisania (`bin2hex(random_bytes(16))`) |

#### Tabela `newsletter_log`

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | INT AUTO_INCREMENT | PK |
| `subject` | VARCHAR(255) | Temat kampanii |
| `body_html` | MEDIUMTEXT | Treść HTML |
| `sent_to` | INT | Liczba skutecznie wysłanych wiadomości |
| `sent_by` | VARCHAR(64) | Login admina |
| `sent_at` | DATETIME | Czas wysyłki |
| `status` | ENUM `sent`/`failed`/`partial` | Wynik kampanii |
| `notes` | VARCHAR(512) NULL | Dodatkowe info (np. adres przy wysyłce do jednego gracza) |

### Przepływ wysyłki

```
Admin → formularz TinyMCE → POST action=send
  ├── send_target=all  → SELECT players WHERE newsletter_subscribed=1 AND email_verified=1 AND status!='suspended'
  │     └── foreach player → nlGetToken() → nlUnsubUrl() → EmailTemplate::build() → Mailer::send()
  └── send_target=single → SELECT players WHERE email=? → EmailTemplate::build() → Mailer::send()
        (pomija sprawdzanie newsletter_subscribed — do testów i wysyłek specjalnych)
```

### Funkcje pomocnicze (`admin/newsletter.php`)

| Funkcja | Opis |
|---------|------|
| `nlGetToken(PDO, int): string` | Pobiera lub generuje `newsletter_token` dla gracza |
| `nlUnsubUrl(string): string` | Buduje URL wypisania — zawsze używa `base_url` z `config/mail.php` (nigdy `$_SERVER['HTTP_HOST']`) |
| `nlFooterHtml(string): string` | Buduje stopkę e-maila jako raw HTML z encjami (nie przez `t()`, która escapuje HTML) |

### Wypisywanie się (`public/newsletter_unsubscribe.php`)

- Route: `/newsletter-unsubscribe?token=XXXX` (`.htaccess`)
- Walidacja: token musi być dokładnie 32 znakami `[a-f0-9]`
- `UPDATE players SET newsletter_subscribed = 0 WHERE newsletter_token = ?`
- Stany strony: `success` / `already` (już wypisany) / `invalid` (token nieznany)
- Strona używa `$authPage = true` (layout bez nawigacji gry)

### Subskrypcja przy rejestracji

Nowi gracze wybierają przy rejestracji:
- **Checkbox obowiązkowy** — akceptacja regulaminu (`terms_accepted`)
- **Checkbox opcjonalny** — subskrypcja newslettera (`newsletter_optin`, domyślnie niezaznaczony)

INSERT przy rejestracji ustawia `newsletter_subscribed = 0|1` i generuje `newsletter_token = bin2hex(random_bytes(16))`.

### Admin — zarządzanie subskrypcją gracza

W `admin/player.php` → `admin/player_clean.php`:
- Akcja `toggle_newsletter` przełącza `newsletter_subscribed` (0 ↔ 1)
- Widok pokazuje aktualny stan z kolorowym badge (zielony / czerwony)

### Linki e-mail — produkcja vs lokalnie

Wszystkie URLe w e-mailach (weryfikacja, resetowanie hasła, wypisanie z newslettera) używają `base_url` z `config/mail.php`:

```php
$cfg     = require __DIR__ . '/../config/mail.php';
$baseUrl = rtrim($cfg['base_url'] ?? 'https://oilempire.pl', '/');
```

Dzięki temu linki zawsze wskazują na produkcję, nawet gdy admin wysyła maile z lokalnego XAMPP.

### i18n (`lang/pl.php`)

Klucze w grupie `admin.newsletter.*`:
- `heading`, `desc`, `label_subject`, `label_body`, `label_recipients`
- `placeholder_subject`, `placeholder_single_email`
- `target_all`, `target_single`, `single_email_note`
- `btn_preview`, `btn_send`, `btn_send_single`
- `confirm_send`, `confirm_send_single`
- `preview_heading`, `preview_greeting`, `preview_footer`, `preview_note`
- `history_heading`, `col_date`, `col_subject`, `col_sent_to`, `col_by`, `col_status`
- `stats_total`, `stats_eligible`, `stats_unsubscribed`
- `err_subject_empty`, `err_body_empty`, `err_single_email_invalid`, `err_single_not_found`, `err_no_recipients`
- `msg_sent`, `msg_sent_single`

Klucze w grupie `newsletter_unsub.*`:
- `title`, `heading_success`, `desc_success`, `heading_already`, `desc_already`, `heading_invalid`, `desc_invalid`

### Uwagi implementacyjne

- **`t()` nie może zawierać HTML** — funkcja wywołuje `htmlspecialchars()`. Stopka i powitanie e-maila są budowane jako raw PHP string przez `nlFooterHtml()` z encjami HTML.
- **Migracja MySQL** — `sql/newsletter_subscription.sql` używa wzorca procedury z `INFORMATION_SCHEMA.COLUMNS` (nie `ADD COLUMN IF NOT EXISTS`, które jest tylko MariaDB).
- **TinyMCE** — hosted na CDN Tiny Cloud; klucz API w `newsletter_editor.js`; `tinymce.triggerSave()` wywoływane przed każdym submittem.

---

- [x] **Rekrutacja zarzadu przeniesiona z HR do dashboardu** (03.05.2026) - `hr.php` stal sie panelem czysto kadrowym; zniknely zakladki `Rekrutacja`, `Kandydaci` i `Specjalizacje`; `dashboard.php` przejal start rekrutacji dyrektorow, liste kandydatow zarzadu i aktywne procesy; `HRApi.php` blokuje stary flow `initiated_by='hr'`; `HiringTrait` / `DataTrait` zawezaja dane do `player_id`.
- [x] **Mobilne poprawki HR i dashboardu** (03.05.2026) - `assets/css/hr.css`: poziomy scroll zakladek, brak ucietego `Headhunter`, poprawione karty pracownikow i akcje na telefonie; `assets/css/dashboard.css`: pelnoszerokie akcje na mobile, lepsze zawijanie formularza rekrutacji i sekcji dyrektorskich.
- [x] **Równoległa rekrutacja HR — maks. 2 działy jednocześnie** (02.05.2026) — `src/HRApi.php`: walidacja max 2 aktywne rekrutacje + brak duplikatu roli (status `pending`/`ready`); formularz `.new-recruit-card` w zakładce Rekrutacja z dropdownem ról (filtruje zajęte/rekrutowane), dropdownem specjalizacji (filtrowany per dział), siatką regionów i wskaźnikiem slotów; `_nrUpdateSlotsUI()` aktualizuje badge i chowa formularz po osiągnięciu limitu; 8 nowych kluczy `hr.*` w `lang/pl.php`; nowe klasy `.nrc-slots-badge`, `.hr-alert--warn/info` w `hr.css`
- [x] **System Aktualności (§27)** (02.05.2026) — `admin/news.php` + `templates/views/admin/news/main.php` (CRUD z layoutem kart); `src/AdminNewsApi.php` (GET endpoint dla graczy); `assets/js/chat.js` — moduł NEWS PANEL (polling co 60s, `#newsList`); tabela `admin_news`; link 📰 Aktualności w nawigacji admina; `.htaccess` whitelist dla `AdminNewsApi.php`; i18n `admin.news.*` + `admin.nav.news`
- [x] **Pinowanie wiadomości admina w czacie** (02.05.2026) — `admin/chat.php`: akcje `pin_msg`/`unpin_msg`; przyciski 📌 w historii czatu (tylko dla `is_admin=1`); `src/ChatApi.php`: endpoint `...pinned_only=1` (polling co 15s z JS); filtry `AND is_pinned=0` w strumieniu wiadomości — przypiętych nigdy nie ma w scrollu czatu; `chat.js`: `renderPinned()` usuwa duplikaty z DOM; kolumny `is_admin`, `is_pinned`, `pinned_at` w `chat_messages`
- [x] **Bugfix czat — brakujące klucze i18n** (02.05.2026) — `admin.chat.delete_expired`, `admin.chat.delete_expired_confirm`, `admin.chat.msg_expired_deleted`, `admin.chat.pin_btn`, `admin.chat.pin_title`, `admin.chat.unpin_btn`, `admin.chat.unpin_title` — surowe klucze wyświetlały się w UI zamiast polskich etykiet
- [x] **Bugfix `src/HRApi.php` — `$db` undefined przy walidacji rekrutacji** (02.05.2026) — fatal error przy każdym `action=start_recruitment`; dodano `$db = Database::getInstance()->getConnection()` po inicjalizacji serwisów
- [x] **Bugfix `hr.js` — `switchTab()` nie reaktywował przycisku zakładki** (02.05.2026) — po kliknięciu wszystkie przyciski zakładek były wizualnie nieaktywne; naprawione przez `querySelector(".hr-tab[onclick=...]")....classList.add('active')`
- [x] **Bugfix `hr.css` — `.btn-hr-primary:hover` niewidoczny** (02.05.2026) — hover używał `--gold2` = `rgba(200,168,75,0.15)` (prawie przezroczysty); zamieniono na solidne `#d4b455`
- [x] **HR: rozdzielenie Pracownicy vs Zarzad + stabilizacja rekrutacji** (02.05.2026) - `member_type` (`director`/`staff`) w `board_members`; nowa zakladka `Zarzad` w HR; filtrowanie dostepow przez `member_type='director'`; HR rekrutuje ze `spec_code`; czasy rekrutacji skrocone do minut; brak auto-refresh listy rekrutacji; dynamiczne dodawanie karty bez reload; poprawiony countdown; dodane `fire_technical_staff`; dopisane klucze `hr.*` i `hr.spec.*` do `lang/pl.php`.


- [x] **Warstwy geologiczne** — zaimplementowane (`GeologicalLayerService`, UI w `well_grid.php`)
- [x] **System incydentów** — naprawione szanse (skalibrowane per 5-min tick), floor per-tick
- [x] **MarketTick** — gravity do celu trendu, trendShock, synchronizacja `$newPrice`
- [x] **Profil `/profile`** — 404 naprawione (reguła w `.htaccess`), licznik aktywnych odwiertów naprawiony
- [x] **HR zatrudnianie** — brak reload po `hireCandidate`, karta usuwana z DOM
- [x] Specjalizacje pracowników (perki) — drilling_specialist, pressure_control, electronics_specialist, mechanical_specialist, safety_specialist
- [x] **Panel admina — HR** (`admin/hr.php`) — wielozakładkowy panel: kandydaci, historia, statystyki HR, specjalizacje
- [x] **Panel admina — Zarządzanie `staff_specializations`** — dodawanie, edycja (nazwa PL + perki), usuwanie z potwierdzeniem modal; grupowanie po `role`
- [x] **Panel admina — Zarządzanie `hr_specializations`** — dodawanie, edycja inline (nazwa/dział/rzadkość), usuwanie; grupowanie po `department`
- [x] **i18n HR admin** — ~30 nowych kluczy `admin.hr.*` + `common.delete` w `lang/pl.php`
- [x] **CSS spec-card** — `.spec-card`, `.spec-group`, `.spec-group-title`, `.spec-delete-form`, `.btn-danger`, `.spec-field--full` w `assets/css/admin.css`
- [x] **Modal confirm dla akcji delete** — `confirmAction()` z `modal.js` zamiast natywnego `confirm()`; `admin/hr.php` dołącza `admin/partials/footer.php` (ładuje `modal.js`)
- [x] **Bugfix label a11y** — usunięte puste `<label>&nbsp;</label>`; `spec-field--action` wyrównany przez `padding-top` w CSS
- [x] **Bugfix confirmAction undefined** — dodano `require_once footer.php` do `admin/hr.php`
- [x] **System transportu per odwiert** — rurociąg/ciężarówki/tankowiec
- [x] **Panel admina — Transport Config** (`admin/transport.php`) — mnożniki per typ, capacity, OPEX
- [x] **Panel admina — Loss Monitoring** (`admin/transport_loss.php`) — straty global/per gracz/per odwiert
- [x] **Panel admina — Market Debug** (`admin/market_debug.php`) — supply/demand, historia ceny, ekonomia graczy
- [x] **Panel admina — Pipelines** (`admin/pipelines.php`) — stan, naprawa, wymuszanie awarii
- [x] **Panel admina — Alerty** (`admin/alerts.php`) — automatyczne progi krytyczne i ostrzegawcze
- [x] **Panel admina — Quick Balance** (`admin/balance.php`) — globalne mnożniki bez deploy kodu
- [x] **Globalne mnożniki balansu** — odczyt z `well_config` w `cron/tick.php`, 7 mnożników × 6 miejsc w pętli
- [x] **`sql/transport_config.sql`** — migracja tabeli `transport_config` z domyślnymi wartościami
- [x] **Bugfix `players.username`** — tabela `players` używa kolumny `username`, nie `login`; poprawione w `admin/pipelines.php`, `admin/transport_loss.php`, `admin/market_debug.php` (JOIN `pl.username AS player_login`)
- [x] **Bugfix `storage` FK** — klucz obcy wskazywał na `players_old` zamiast `players`; migracja `sql/fix_storage_fk_players.sql`
- [x] **Bugfix `admin/alerts.php` PDO** — stara wersja używała `$db->query(..., [array])` (błąd TypeError); zastąpione przez `prepare()`+`execute()`
- [x] **Strona pomocy `/help`** — `public/help.php` z dynamiczną treścią z DB, `assets/css/help.css`, routing `.htaccess` + `ROUTES`, link w nawigacji gracza
- [x] **Panel admina — Edytor instrukcji** (`admin/help_editor.php`) — WYSIWYG TinyMCE 6, tabela `game_help_pages`, zarządzanie sekcjami (dodaj/edytuj/usuń/kolejność/widoczność); style w `assets/css/help_editor.css`, JS w `assets/js/help_editor.js`
- [x] **Panel admina — Edytor szablonu** (`admin/template_editor.php`) — edycja nawigacji gracza (tabela `nav_items`), nazwy serwisu, tagline, tekstu stopki, pliku JS; tabela `site_config`; `templates/header.php` i `templates/footer.php` pobierają dane z DB z fallbackiem
- [x] **Tłumaczenia admina** — wszystkie angielskie fragmenty (`Force Tick`, `→ Logi`, `supply`, `pipeline loss`, `wear_level`, `ROI`) przetłumaczone na polski w `balance.php`, `alerts.php`, `force_tick.php`, `index.php`
- [x] **Edytowalne progi alertów** (`admin/alerts.php`) — progi zapisywane w `well_config` zamiast hardcode; formularz z 9 progami (straty rurociągów, cena ropy, ROI, stan techniczny, zużycie, magazyn); style `input-inline` + `alert-banner` w `admin.css`
- [x] **Bugfix TinyMCE** (`assets/js/help_editor.js`) — `setup` callback z `editor.on('init')` nasłuchuje `submit` na `#editForm` i wywołuje `tinymce.triggerSave()` przed wysłaniem; bez tego TinyMCE nie synchronizował treści z `textarea` i pole `content` było puste
- [x] **Edytowalne linki stopki** (`admin/template_editor.php` → zakładka „Linki stopki") — tabela `nav_items` rozszerzona o kolumnę `location ENUM('header','footer','actions')`; `templates/footer.php` renderuje dynamiczne linki z bazy; style `.footer-nav` / `.footer-link` w `assets/css/style.css`
- [x] **Edytowalne przyciski AKCJE** (`admin/template_editor.php` → zakładka „Przyciski AKCJE") — przyciski na dashboardzie gracza (Rynek ropy, Kup odwiert, Ulepsz, Zarząd/HR, Bank) pobierane z `nav_items WHERE location='actions'`; edycja etykiety, ikony, URL key, klasy CSS, kolejności, widoczności; fallback hardkodowany jeśli baza pusta; `public/index.php` zastąpiony dynamicznym zapytaniem
- [x] **Edytor stron statycznych** (`admin/pages_editor.php`) — WYSIWYG TinyMCE 6, tabela `static_pages` (slug, title, icon, content, active, sort_order); tworzenie/edycja/usuwanie podstron (Regulamin, Polityka, Kontakt itp.); routing `.htaccess` aktualizowany **automatycznie** po każdym zapisie/usunięciu — blok `# BEGIN static_pages … # END static_pages`; `public/page.php` wyświetla stronę po slugu z 404 fallbackiem; style w `assets/css/static_page.css`; link w menu admina sekcja „Treści"
- [x] **Bugfix `templates/header.php` — nav_items bez filtra lokalizacji** — zapytanie pobierało wszystkie aktywne wpisy z `nav_items` (header + footer + actions), przez co przyciski AKCJE i linki stopki trafiały do navbara gracza i powodowały wyświetlanie panelu admina na stronie logowania; naprawione przez dodanie `WHERE location='header'`
- [x] **Bugfix TinyMCE — białe litery na stronie publicznej** — `content_style` w `assets/js/help_editor.js` i `assets/js/pages_editor.js` używał `color: #e8e8f0` jako domyślny kolor body, przez co TinyMCE wstawiał inline `color: rgb(232,232,240)` do każdego akapitu; zmieniono na `color: #c8c8d4` (zbliżony do `--text2` na stronie gracza) — domyślny tekst nie ma inline koloru, celowo wybrane kolory (żółty, czerwony itp.) działają poprawnie
- [x] **Dział Finansowy** — `FinanceService`, `/finance`, `/admin/finance`, `finance_logs`, wykres Chart.js, breakdown, per-well analiza
- [x] **Finanse - etap 1 integracji z hubami logistycznymi** — `finance_logs` rozszerzone o `hub_usage_cost`, `hub_loss_*`, `fallback_loss_*`, `hub_incident_loss_*`; `WellLoopSection` i `PlayersSection` przekazują osobne dane logistyczne do `FinanceService`; `/finance` i `/admin/finance` pokazują już koszty i straty hubów osobno
- [x] **Finanse - etap 2 ożywienia modułu** — dodano zakładki `Przegląd`, `Budżety`, `Płynność`, `Ryzyko`, `Historia decyzji`; wdrożono `player_finance_settings` i `player_finance_decisions`; budżety finansowe wpływają już na tick, logistykę, HR i BHP; admin widzi rozkład polityk graczy
- [x] **Sprzedaż odwiertów** — `WellSellApi.php`, `WellService::calculateSellValue/sellWell`, UI w `well_grid.php` + `well_grid.js`, wycena z breakdownem w modalu, CSRF, cooldown 2h
- [x] **Podział `templates/views/technical/main.php` na taby** — plik 1405 linii rozbity na 10 osobnych plików w `templates/views/technical/tabs/` (`team`, `candidates`, `tasks`, `wells`, `well_staff`, `prod`, `infra`, `safety`, `incidents`, `report`); `main.php` skrócony do 81 linii — zawiera tylko topbar, nawigację i jeden dynamiczny `include` z walidacją ścieżki (`preg_replace('/[^a-z_]/', '', $activeTab)`)
- [x] **Internacjonalizacja `BankNegotiationService.php`** — wszystkie polskie stringi zastąpione wywołaniami `t()`; dodano ~80 kluczy `bank_neg.*` do `lang/pl.php`; objęte metody: `formatHours`, `formatHoursNom`, `calculateDecisionTime`, `buildBankOpeningMessage`, `buildCfoOpeningMessage`, `buildApprovalMessage`, `buildRejectionMessage`, `triggerRandomEvent`, `requestRecoveryPlan`, `resolveNegotiation`; logi wewnętrzne (`GameLog`/`AdminLog`) pozostawione w angielskim
- [x] **Podział `BankruptcyService.php` na traity** — plik 606 linii rozbity na 3 traity w `src/Bankruptcy/`: `StateTrait.php` (getState, ensureRecoveryMode, getRecoveryOptions, tryRecover, loadState, getEvents, countOpenCriticalEvents), `OptionsTrait.php` (applyOption, applySellAsset, applyBankTakeover, applyEmergencyLoan, applyCostCuts, applyRescueInvestor, applyNewStart), `EventsTrait.php` (tickBankruptcyFlow, spawnCriticalEventIfNeeded, applyLiquidationResetIfNeeded, logEvent, addNotification); główny plik skrócony do 31 linii
- [x] **Podział `BankNegotiationService.php` na traity** — plik 1530 linii rozbity na 5 traitów w `src/BankNegotiation/`: `ContextTrait.php` (buildContext, trust score, calculateDecisionTime, calculateDeferralFee), `MessagesTrait.php` (buildBankOpeningMessage, buildCfoOpeningMessage, buildApprovalMessage, buildRejectionMessage), `RandomEventsTrait.php` (triggerRandomEvent), `RequestsTrait.php` (requestDeferral, requestRestructure, requestRecoveryPlan), `ProcessorTrait.php` (processPendingNegotiations, applyNegotiation, canNegotiate, checkRecovery*, gettery); główny `BankNegotiationService.php` skrócony do 79 linii — tylko stałe, konstruktor i `use` traitów; `init.php`, `bank.php`, `tick.php` bez zmian
- [x] **System kryzysu finansowego** — `financial_state` (normal/warning/crisis) + `crisis_ticks` w `players`; tick.php detekuje warning/crisis i triggeruje bankructwo po N tickach (modyfikowane przez credit_score); crisis overlay + warning banner w dashboardzie gracza; blokada UI (brak budowy/upgrade w trybie crisis); `/admin/financial-crisis` — lista firm + config + akcje; CSS w style.css i admin.css
- [x] **Czarny Rynek Ropy (§24)** — zakładka w `/market`, oferty co 3 ticki, black_score, kary proporcjonalne do kasy, credit recovery, profil gracza, panel admina z konfiguracją
- [x] **Refaktor separacji logiki od widoku** — `change_password.php`, `loans.php`, `boardroom.php`, `admin/boardroom.php`, `admin/black_market.php` przeniesione do wzorca `$viewData` + `require templates/views/…/main.php`; wszystkie polskie stringi zastąpione wywołaniami `t()`; nowe pliki widoków: `templates/views/admin/change_password/main.php`, `templates/views/admin/loans/main.php`
- [x] **Manager teł sceny Boardroom** (`admin/template_editor.php` → zakładka Boardroom → Tła sceny zarządu) — upload PNG z automatycznym nazewnictwem `boardroom_bg_{role}_{gender}.png`, podgląd live nazwy pliku przez JS, siatka istniejących teł z miniaturkami i usuwaniem; handlery `save_boardroom_bg` / `delete_boardroom_bg`; `$brBgMatrix` — macierz kombinacji ról × płeć ze statusem istnienia pliku
- [x] **Bugfix `PipelineSection` — inżynier rurociągów nie był rozpoznawany** (22.04.2026) — zapytanie SQL używało `spec_code` zamiast `specialization`; rurociągi zawsze degradowały 2× za szybko; naprawione w `src/Tick/PipelineSection.php:102`
- [x] **Bugfix `WellLoopSection` — odwierty `broken` były przetwarzane przez tick** (22.04.2026) — `broken` brakowało na liście pomijanych statusów; odwierty z zerowym stanem technicznym naliczały OPEX; naprawione w `src/Tick/WellLoopSection.php`
- [x] **Bugfix `WellLoopSection` — zdarzenia transportowe bez efektu** (22.04.2026) — `processTransportEvent(&$actual)` wywoływane po `currentStorage += $actual` i `finBbl += $actual`; kradzieże i wypadki były logowane, ale olej i tak trafiał do magazynu; naprawione — transport events wywoływane **przed** zapisem finansów
- [x] **Bugfix `WellLoopSection` — `finGross/finLossBbl/finLossValue` zawsze = 0** (22.04.2026) — pola zadeklarowane ale nigdy przypisywane; `finance_logs.gross_revenue`, `loss_bbl`, `loss_value` były zerowe dla każdego ticka; naprawione — `$actualBeforeEvent` zapisywany przed eventem transportowym, różnica idzie do `finLoss*`
- [x] **Bugfix `BankSection` — BankruptcyService dla wszystkich graczy** (22.04.2026) — warunek `WHERE status != 'bankrupt' OR recovery_mode=1` wybierał wszystkich aktywnych graczy zamiast tylko bankrutów; naprawione na `status = 'bankrupt' OR recovery_mode=1`
- [x] **Bugfix `LoanDecisionService` — brak `...... null/0` przy dostępie do `$breakdown['market']`** (22.04.2026) — przy braku aktywnego trendu rynkowego linia 54 i 77 rzucały `Undefined array key`; naprawione przez dodanie `...... null` i `...... 0`
- [x] **Bugfix `CostsTrait` — tryb `boost` nie dawał efektu** (22.04.2026) — match używał `'turbo'` zamiast `'boost'`; UI i DB zapisują `'boost'`; odwierty w trybie boost produkowały jak normalne (×1.00 zamiast ×1.40); naprawione w `src/Well/CostsTrait.php:152`
- [x] **Poprawki danych produkcyjnych** (22.04.2026) — skrypt `sql/fixes_2026_04_22.sql`: well #19 status→`sold`, well #13 status→`broken`, `crisis_ticks_base` 6→48, `bm_max_bbl` 200000→2000, cena ropy reset do $70
- [x] **Bugfix `public/bank.php` — komunikat "Spłać zobowiązania" wyświetlany nowym graczom** (26.04.2026) — komunikat `bank.blocked_repay_hint` był widoczny dla każdego zablokowanego gracza niezależnie od powodu blokady; naprawione przez flagę `$blockHasActiveLoan` ustawianą tylko gdy status kredytu to `active` lub `late`; widok `templates/views/bank/main.php` warunkuje wyświetlanie na `<...php if ($blockHasActiveLoan): ...>`
- [x] **Manager teł Boardroom — chunked AJAX upload na az.pl** (26–27.04.2026) — standardowy upload `$_FILES` niemożliwy (brak tmp_dir); WAF ModSecurity blokuje body ≥ 16 KB; rozwiązanie: surowe bajty binarne (`application/octet-stream`) w 8 KB chunkach, metadane w GET params; CSRF token w URL; tymczasowe chunki w `assets/images/boardroom/`; PHP łączy i zapisuje finalny PNG
- [x] **Bugfix `admin/template_editor.php` — regex ucinał M/F z nazwy pliku tła** (27.04.2026) — `preg_replace('/[^a-z0-9_]/', '')` wyrzucał wielkie litery; `boardroom_bg_hr_M.png` zapisywało się jako `boardroom_bg_hr_.png`; naprawione przez zmianę na `[^a-zA-Z0-9_]` w handlerach `upload_bg_chunk` i `delete_boardroom_bg`
- [x] **Bugfix htaccess — whitelist dla ChatApi i DmApi** — `ChatApi.php` i `DmApi.php` nie były na whitelist reguły blokującej `/src/`; Apache zwracał 403; czat nie wyświetlał wiadomości ani nie zapisywał ich do DB; naprawione przez dodanie pełnej whitelist AJAX: `ChatApi.php`, `DmApi.php`, `TechNotifApi.php`, `WellSellApi.php`, `RecruitmentAPI.php`, `HRApi.php`, `WellStaffApi.php`, `BlackMarketApi.php`
- [x] **Integracja stron działów ze wspólnym layoutem gry** (28.04.2026) — `dashboard.php`, `boardroom.php`, `hr.php` i `technical.php` przestały renderować własne pełne dokumenty HTML/topbary; strony używają teraz `templates/header.php`, wspólnego `status_grid`, centralnego shellu `templates/components/game_shell.php`, właściwego widoku modułu oraz wspólnego `action_grid` na dole. Dodano `src/GameShell.php`, który zbiera metryki gracza i akcje (`nav_items WHERE location='actions'` z fallbackiem). Usunięto lokalne topbary z widoków modułów i ograniczono CSS, żeby nie nadpisywał globalnego `body`.
- [x] **Muzyka tła na mapie — automatyczna lista utworów** (29.04.2026) — `assets/js/map_audio.js` pobiera listę plików MP3/OGG/WAV/M4A z endpointu `assets/audio/list.php` (PHP skanuje katalog przez `DirectoryIterator`); lista tasowana losowo przy każdej sesji; brak konieczności edycji JS po dodaniu nowego pliku — wystarczy wrzucić plik do `/assets/audio/`; fade-in/out 1500 ms, widget głośności zapamiętywany w `localStorage`.
- [x] **Wizualny picker współrzędnych globusa w panelu admina** (29.04.2026) — `templates/views/admin/map_locations/main.php`: przycisk „🌍 Ustaw na globie" przy polach Lat/Lng modala edycji lokalizacji; pełnoekranowy modal z Three.js SphereGeometry (ta sama tekstura co mapa gracza); klik na globie → czerwony marker + wyświetlenie współrzędnych; „Zatwierdź" wypełnia pola formularza; `latLngToVec3` / `vec3ToLatLng` matematycznie spójne z `world_map.js`; Three.js + OrbitControls dołączone lokalnie przez CDN (nie są w globalnym headerze admina).
- [x] **Obfuskacja JS przed deplojem na az.pl** (29.04.2026) — `build/` folder: `package.json` (zależność `javascript-obfuscator ^4.1.1`), `obfuscate.js` (przetwarza wszystkie pliki z `assets/js/` → `dist/js/`; opcje: `stringArrayEncoding: base64`, `controlFlowFlattening`, `splitStrings`, `identifierNamesGenerator: hexadecimal`), `buduj.bat` (podwójne kliknięcie = instalacja + build); źródłowe JS pozostają czytelne lokalnie, na serwer wgrywana jest wyłącznie zawartość `dist/js/`.
- [x] **Responsywność mobilna gry** (29.04.2026) — audyt i poprawki we wszystkich plikach CSS gry (pominięto panel admina); zmiany:
  - `assets/css/hr.css` — `@media (max-width: 900px)`: `.candidates-grid`, `.employees-grid`, `.recruit-form-grid` → 1 kolumna; tabele kontraktów/specs/historii → `overflow-x: auto` + `min-width`; `@media (max-width: 600px)`: `.hr-tabs` mniejszy padding, `.region-info` → 1 kolumna, `.cand-skills` / `.emp-skills-grid` → 3 kolumny zamiast 5
  - `assets/css/black_market.css` — dodano cały blok `@media` (wcześniej zero media queries); listy 10/9/7/6-kolumnowe → scroll poziomy (`overflow-x: auto`); karty towarów → 1 kolumna na ≤600px
  - `assets/css/style.css` — rozszerzono `@media (max-width: 900px)`: globus mapy `560px → 380px`, filtry `.map-top-filters__group` → `flex-wrap: wrap`, widget audio mniejszy
  - `assets/css/recruitment.css` — `.panel-stats` 3 kolumny → 2 kol. (≤768px) → 1 kol. (≤480px)
  - `assets/css/modal.css` — `@media (max-width: 600px)`: przyciski modala `padding: 8px → 11px` (większy touch target), `flex-direction: column`, pełna szerokość
- [x] **Poprawki panelu HR** (29.04.2026):
  - Nowa rekrutacja z wyborem regionu bezpośrednio z `hr.php` (zakładka Rekrutacja) — gracz nie musi wchodzić do boardroom; wybór roli z dropdownu + siatka kart regionów; `hr.js`: `nrSelectRegion()` + `startNewRecruitment()`; backend `HRApi: start_recruitment`; `hr.php`: `$rolesForRecruitment` (filtrowanie zajętych/rekrutujących ról); CSS: `.new-recruit-card`, `.nr-region-card`, `.nr-region-grid` w `hr.css`
  - Staz pracy pracownika w boardroom — `boardroom.php`: dodano `DATEDIFF(CURDATE(), bm.hired_at) AS days_employed` do zapytania SQL; `boardroom-dynamic.js` wyświetla realną liczbę dni zamiast `undefined`
  - Timer aktywnej rekrutacji — usunięto PHP-generowane `~X min` (arc-status); pozostał tylko JS countdown (arc-timer); `hr.lang: status_pending` → `'W trakcie'`
- [x] **Panel admina — dane ostatniego logowania gracza** (29.04.2026) — w górnej siatce statystyk gracza (`admin/player/main.php`) zamieniono „Ostatni tick" na „Ostatnie logowanie" (`last_login_at`); dodano też wiersz w szczegółach gracza (zakładka Info); nowe klucze i18n: `admin.player.stat_last_login`, `admin.player.info_last_login` w `lang/pl.php`
- [x] **Optymalizator logistyki odwiertów** (29.04.2026) — przycisk „Optymalizuj logistykę" w zakładce Odwierty panelu technicznego; modal z 3 trybami optymalizacji (Balans, Max produkcja, Min koszt) i podglądem stanu obecnego; backend:
  - `src/LogisticsService.php` — algorytm per odwiert: offshore→tankowiec, onshore→rurociąg/ciężarówki; scoring `transported - cost×0.001` (tryb balans), `transported` (max_prod), `-cost` (min_cost); batch UPDATE w transakcji; cooldown 5 min (sesja); zwraca statystyki przed/po (straty, koszt, efektywność)
  - `src/LogisticsApi.php` — AJAX endpoint; akcje: `optimize`, `summary`, `cooldown`; dodano do whitelist w `htaccess`
  - `assets/js/logistics.js` — `openLogisticsModal()`, `loadLogisticsSummary()`, `runLogisticsOptimize()`, `startCooldownTimer()`; renderuje porównanie przed/po i listę zmian per odwiert
  - CSS — `.logistics-optimizer-bar`, `.logistics-modal-*`, `.logistics-modes`, `.logistics-compare`, `.logistics-changes-list` w `assets/css/style.css`; responsive ≤640px
  - W szczegółach każdego odwiertu (zakładka Odwierty) dodano wiersz „Transport" z aktualnym typem i % przepustowości
  - Nowe klucze i18n: `technical.logistics_*` (~30 kluczy), `technical.transport_*`, `technical.stat_transport` w `lang/pl.php`
- [x] **Bugfix `WellStaffService` — nieprawidłowe `spec_code` techników** (01.05.2026) — `assign()` i `getAvailableStaff()` używały `['well_technician', 'maintenance_engineer']` dla roli technika; `well_technician` nie istnieje w DB; techników nie można było przypisać; naprawione na `['maintenance_engineer', 'pipeline_engineer', 'safety_engineer', 'safety_officer']`
- [x] **Bugfix `WellSellTrait` — pracownicy "zajęci" po sprzedaży odwiertu** (01.05.2026) — `sellWell()` ustawiało status odwiertu na `sold` i zerował `operator_id`/`technician_id` w `wells`, ale nie aktualizowało `well_staff_assignments`; rekordy z `unassigned_at IS NULL` pozostawały → pracownicy wyglądali na zajętych i nie można ich było przypisać do nowego odwiertu; naprawione przez dodanie `UPDATE well_staff_assignments SET unassigned_at=NOW() WHERE well_id=... AND player_id=... AND unassigned_at IS NULL` w transakcji sprzedaży
- [x] **Bugfix `DisastersTrait` — pracownicy "zajęci" po blowout** (01.05.2026) — analogiczny problem jak w `sellWell`; `triggerBlowout()` nie czyściło `well_staff_assignments`; naprawione w `src/Well/DisastersTrait.php`
- [x] **SQL jednorazowy `fix_stale_well_staff_2026_05_01.sql`** — naprawia historyczne stale rekordy `well_staff_assignments` dla odwiertów `sold`/`blowout` istniejących przed poprawką; plik: `sql/fix_stale_well_staff_2026_05_01.sql`
- [x] **Bugfix `WellGridData` — klucze i18n nieistniejące** (01.05.2026) — `WellGridData::prepare()` wywoływało `t('wg.status_active')`, `t('wg.spec_safety_officer')` itp., które nie istniały w `lang/pl.php`; dashboard wyświetlał surowe klucze zamiast polskich nazw; naprawione przez zamianę na istniejące klucze: statusy → `technical.ws_*` / `well.status.*` / `map_js.well_status_broken`, specjalizacje → `hr.spec.*`; dodano jedyny brakujący klucz `wg.no_location` => `'Bez regionu'`
- [x] **Bugfix `boardroom.php` — brak tabeli `boardroom_config` i kolumny `sort_order`** (02.05.2026) — `board_roles` nie miała kolumny `sort_order`; tabela `boardroom_config` nie istniała; strona boardroom crashowała z PDOException; SQL fix: `sql/fix_boardroom_2026_05_02.sql`
- [x] **Bugfix `boardroom-dynamic.js` — rekrutacja zarządu zwracała "Brak specjalizacji"** (02.05.2026) — `submitRecruitment()` wysyłało `initiated_by: 'hr'` gdy HR był zatrudniony, ale nie wysyłało `spec_code`; `HRApi.php` rzucał wyjątek dla `initiator=hr` bez `spec_code`; naprawione przez zmianę na `initiated_by: 'director'` (role zarządu nie wymagają specjalizacji)
- [x] **Rozbudowa paneli pracowników w boardroom** (02.05.2026) — dodano `showLogisticsPanel()` z linkiem do `/logistics` i `showLegalPanel()` dla odpowiednich ról; wcześniej oba działy używały generycznego `showEmployeeModal` bez linku do działu; nowe klucze i18n: `br_js.logistics_panel_*`, `br_js.legal_panel_*` w `lang/pl.php` i `$brLang` w `boardroom.php`
- [x] **Bugfix `logistics.php` — "Transport działa optymalnie" przy braku aktywnych odwiertów** (02.05.2026) — gdy brak odwiertów lub `$totalOutput == 0`, żaden próg alertu nie był przekraczany → wyświetlało się błędnie "Transport działa optymalnie."; naprawione przez sprawdzenie `empty($wells) || $totalOutput == 0` przed logiką alertów — wtedy pokazywany jest czerwony alert `logistics.no_wells`
- [x] **Bugfix `admin/player_clean.php` — kolumna `event_type` nie istnieje w `bank_trust_log`** (02.05.2026) — `fetchTrustLog()` wykonywało `SELECT id, event_type AS event, ...` ale tabela ma kolumnę `event`, nie `event_type`; rzucało `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'event_type' in 'field list'`; naprawione przez usunięcie aliasu: `SELECT id, event, delta, note, created_at FROM bank_trust_log`
- [x] **Awatary graczy w czacie** (05.05.2026) — `src/ChatApi.php`: wszystkie zapytania GET (global + DM polling, pierwsze ładowanie, pinned) rozszerzone o `LEFT JOIN players p ON p.id = cm.sender_id` + `p.avatar_path`; `assets/js/chat.js`: nowa funkcja `renderAvatar(username, avatarPath)` zwraca `<img class="chat-msg-avatar">` dla gracza z avatarem lub `<span class="chat-msg-avatar chat-msg-avatar--initials">` dla reszty; `renderMsg(m)` osadza avatar w nagłówku bańki (`.chat-msg-header`); układ bańki odwrócony dla własnych wiadomości (`flex-direction: row-reverse`); nowe klasy CSS w `assets/css/chat.css`: `.chat-msg-header`, `.chat-msg-avatar`, `.chat-msg-avatar--initials`, `.chat-msg-bubble`, `.chat-msg-bubble--mine`, `.chat-msg-bubble--admin`
- [x] **Bugfix `src/ChatApi.php` — crash Unknown column 'is_admin'** (05.05.2026) — produkcyjna baza danych mogła nie mieć kolumn `is_admin`/`is_pinned` dodanych migracją; endpoint rzucał PDOException przy każdym pollu; naprawione przez runtime detection: `SHOW COLUMNS FROM chat_messages LIKE 'is_admin'` przy starcie API; zapytania SQL wybierają kolumny dynamicznie — gdy nie istnieją, używają `0 AS is_admin, 0 AS is_pinned`
- [x] **Naprawa uploadu avatara — az.pl (PHP-FPM + WAF)** (05.05.2026) — `move_uploaded_file()` failowało z komunikatem "Błąd przesyłania pliku." na serwerze produkcyjnym; przyczyny: (1) `php_value upload_tmp_dir "/tmp"` w `.htaccess` ustawia temp dir poza `open_basedir` gdy SAPI = `fpm-fcgi` (dyrektywa działa tylko z `mod_php`); (2) WAF (ModSecurity) na az.pl blokuje body żądania ≥ ~8 KB; rozwiązanie: usunięcie bloku `<IfModule mod_php.c>` i `<IfModule mod_php8.c>` z `.htaccess`; po stronie klienta: `compressAvatarBlob()` w `templates/views/profile/main.php` — canvas resize do max 96×96px JPEG quality 0.82 ≈ 4 KB, bezpieczne poniżej limitu WAF; wysyłka przez `fetch()` z `Content-Type: image/jpeg` zamiast `FormData`
- [x] **Diagnostyka az.pl — `admin/diag_upload.php`** (05.05.2026) — narzędzie diagnostyczne testujące próg WAF: wysyła `application/octet-stream` o rosnących rozmiarach (2/4/8/16/32 KB) i mierzy które przechodzą; potwierdziło próg ~8–16 KB; dostępne pod `/admin/diag_upload` (już w strefie chronionej admina — nie wymaga osobnych reguł htaccess)
- [x] **Bugfix `bank.php` — `compact(): Undefined variable $deferralOpts`** (05.05.2026) — zmienna `$deferralOpts` inicjalizowana dopiero w pętli `foreach ($activeLoans)`, ale `compact()` wywoływane przed pętlą; rzucało Notice i powodowało brakującą zmienną w `$viewData`; naprawione przez inicjalizację `$deferralOpts = [];` przed foreach
- [x] **Bugfix `TechnicalTeamService` — brakująca stała `PROCEDURE_UPGRADE_COSTS`** (05.05.2026) — kod referował `self::PROCEDURE_UPGRADE_COSTS` ale stała nie była zdefiniowana w klasie; rzucało `Undefined constant`; naprawione przez dodanie stałej z kosztami 1–5 poziomu procedur BHP (500 000 → 8 000 000 PLN)
- [x] **Bugfix `admin/boardroom.php` — kolumna `sort_order` nie istnieje** (05.05.2026) — `SELECT * FROM board_roles ORDER BY sort_order, id` failowało gdy kolumna nie dodana migracją; panel admina crashował; naprawione przez try/catch — fallback na `ORDER BY id`; właściwa migracja: `ALTER TABLE board_roles ADD COLUMN sort_order INT NOT NULL DEFAULT 0`
- [x] **SQL migracje schematu czatu** (05.05.2026) — dodane kolumny: `ALTER TABLE chat_messages ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0`, `ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0`, `ADD COLUMN pinned_at DATETIME NULL`; `ALTER TABLE board_roles ADD COLUMN sort_order INT NOT NULL DEFAULT 0`; `CREATE TABLE IF NOT EXISTS black_market_transactions (...)`
- [x] **DM — przeprojektowanie UI i system załączników** (05.05.2026) — `dm.php` i `templates/views/dm/main.php` gruntownie przepisane: nowy config `window.DM_CONFIG` (api, myId, withId, strings); pasek narzędzi kompozytora z przyciskami emoji/załącznik/ustawienia; popover ustawień (Enter wysyła / dźwięk); upload załączników obrazkowych przez chunked AJAX (15 KB chunki identycznie jak boardroom-bg) omijający WAF az.pl; `assets/js/dm.js` zawiera pełną logikę: polling, renderowanie bańki, emoji picker, `handleDmAttachment()`, `dmSend()`; nowe klasy CSS: `.dm-wrap`, `.dm-layout`, `.dm-sidebar`, `.dm-main`, `.dm-composer`, `.dm-toolbar`, `.dm-popover`, `.dm-setting` w `assets/css/chat.css`
- [x] **Etap 1 — metryka `delivered_volume` per odwiert** (17.05.2026) — nowa tabela `well_pipeline_deliveries` z kolumną `delivered_volume_bbl`; `WellLoopSection.processWell()` zapisuje faktycznie dostarczoną objętość (po wszystkich stratach transportowych); `FinanceService` raportuje delivery efficiency; testy zielone: 16/16 MySQL + 21/21 SQLite
- [x] **Etap 2 — rurociągi per odwiert (`WellPipelineService`)** (17.05.2026) — nowy serwis `src/WellPipelineService.php`; tabela `well_pipelines` (zużycie, kondycja, `loss_pct` per odwiert); `PipelineSection` zastąpiona modelem per odwiert z własnym cyklem degradacji i ryzykiem awarii; stary globalny model rurociągów gracza wygaszony; `ensureConfigsForPlayerWells()` tworzy brakujące rekordy idempotentnie; migracja: `migrations/etap2_well_pipelines.sql`; testy zielone: 16/16 MySQL + 21/21 SQLite
- [x] **Etap 3 — transport drogowy jako kursy (`RoadTransportService`)** (17.05.2026) — nowy serwis `src/RoadTransportService.php`; transport `ciezarowki` zamieniony z modelu procentowego na model kursów (trips); każdy kurs to niezależna jednostka ryzyka: incident_type ENUM(`theft`,`raid`,`accident`,`sabotage`,`route_block`); tabele `well_road_configs`, `well_road_incident_logs`; integracja z `WellLoopSection.processWell()`; `pending_migrations.sql` — zbiorczy plik SQL dla Etap 3+; testy zielone: 33/33 SQLite + 22/22 MySQL
- [x] **Etap 4 — transport morski MVP (`OffshoreTransportService`)** (17.05.2026) — nowy serwis `src/OffshoreTransportService.php`; transport `tankowiec` zamieniony na model rejsów per odwiert; typy tankowców: `small`/`medium`/`large`; incydenty per rejs: `storm` (20–60% straty), `breakdown` (100%), `delay` (10–30%), `piracy` (100%); skala ryzyka politycznego: ≥2→×1,3 / ≥3→×1,8 / ≥4→×2,5; tabele `well_offshore_configs`, `well_offshore_incident_logs`; `pending_migrations.sql` rozszerzony o Etap 4; testy zielone: **56/56 SQLite + 29/29 MySQL**
- [x] **Etap 5 — bufor huba logistycznego** (18.05.2026) — `HubTickService.processTick()` używa teraz rzeczywistego `buffer_capacity_bbl`/`buffer_current_bbl` (wcześniej obie wartości były zerowane w `WellLoopSection.finalizeHubTicks()` jako MVP-disable); logowanie uzupełnione o pola `buffered` i `new_buffer` w `hub_tick_loss`; nowy typ logu `hub_tick_buffered`; UI logistyki: pasek postępu bufora z kolorem progowym (zielony/pomarańczowy/czerwony przy ≥90%) i wartościami bbl w kartach hubów (`templates/views/logistics/main.php`); klasy CSS `.hub-buffer-bar`, `.hub-buffer-bar__fill--{low,mid,full}`, `.logistics-hub-stat--buffer` w `assets/css/logistics.css`; brak nowej migracji SQL — kolumny `buffer_*` istnieją w `logistics_hubs`; testy zielone: 56/56 SQLite + 29/29 MySQL
- [x] **Etap 6 — UI akwizycji hubów + potwierdzenia czynszów + fix szablonu** (19.05.2026) — kolorowe badge'e akwizycji (`nowy`=zielony, `używany`=pomarańczowy, `wynajem`=niebieski) w widoku tabeli hubów i modalu przypisania; breakdown ekonomiczny per typ w modalu (⚙ wear ×, ⚠ risk ×, 💰 opex ×, 🔧 start kondycja, 🔑 czynsz); potwierdzenia kosztu czynszu wynajmu we wszystkich 3 flowach: `hubDoAssign()`, `hubDoTransfer()`, `hubAssignWellToHubModal()`; `ViewHubsTrait.getAssignableHubs()` wzbogacony o pola `acq_type/wear_mult/risk_mult/opex_mult/start_min/start_max/lease_fee`; nowe klucze i18n w `lang/pl.php` (`confirm_rental`, `confirm_rental_transfer`, `ok_assign_with_lease`, `ok_transfer_with_lease`, `acq_*`); **bugfix szablonu** — redesigned `.logistics-hub-tbl-row` w `templates/views/logistics/main.php` uzupełniony o brakujące `data-hub-acq-type`, `data-hub-lease-fee`, `data-hub-name`, `data-hub-region-id`, `data-hub-zone-key` (bez tych atrybutów `hubAssignWellToHubModal()` nie czytało danych akwizycji); seeding bazy: 40 hubów `used` (kondycja 20–40%, ≥15 medium) + 60 hubów `rental` (kondycja 55–70%, czynsz 320 PLN/tick, różne wielkości); skrypt `migrations/seed_used_rental_hubs.sql` zachowany; testy zielone: 56/56 SQLite + 29/29 MySQL
- [x] **Etap 7 — browser hubów na `/logistics` + hardening sekcji morskiej** (24.05.2026) — sekcja „Dostępne huby systemowe” została przebudowana z gęstej listy w kartowy browser regionów: tylko pierwszy region jest otwarty, każdy region pokazuje domyślnie krótki preview kart z przyciskiem `Pokaż kolejne N`, a filtrowanie po nazwie/typie działa już w `assets/js/logistics_hubs.js` zamiast w inline `<script>`; dodane klasy layoutu w `assets/css/logistics.css` oraz nowe klucze i18n `logistics.hub.region_summary`, `logistics.hub.show_more`, `logistics.hub.show_less`; dodatkowo sekcja dostaw morskich w `templates/views/logistics/main.php` została utwardzona przez rzutowanie `marineDeliveries` i `marineHistory` do tablic, co naprawiło realny crash renderu (`Wystąpił błąd aplikacji.`) i przywróciło ładowanie `assets/js/logistics_hubs.js` na stronie.

---

## Otwarte TODO

- [ ] Cleanup starego kodu po migracjach i naprawach i18n:
  `dashboard.php`, `boardroom.php`, `assets/js/boardroom-dynamic.js`, `assets/js/recruitment.js`
  - wyczyszczenie starych komentarzy i pozostalosci po zlym kodowaniu,
  - przeglad twardych fallbackow tekstowych,
  - audyt martwych lub nieuzywanych kluczy w `lang/pl.php`

- [ ] Multiplayer — sabotaż, przejęcia

---

## 24. Czarny Rynek Ropy

Nielegalna sprzedaż ropy po zawyżonych cenach — zakładka "🏴 Czarny Rynek" w `/market`.

### Pliki

- `src/BlackMarketService.php` — logika ofert, transakcji, kar, decay, statystyk
- `src/BlackMarketApi.php` — endpoint AJAX (whitelist w htaccess)
- `assets/js/black_market.js` — polling ofert, sprzedaż, historia, toasty
- `assets/css/black_market.css` — style zakładek, score bar, risk colors
- `admin/black_market.php` — logika: POST handlers, zapytania DB, `$viewData`
- `templates/views/admin/black_market/main.php` — widok HTML panelu admina
- `sql/black_market_migration.sql` — migracja

### Tabele DB

- `black_market_offers` — (player_id, bbl, price_per_bbl, base_risk_pct, expires_at, status)
- `black_market_transactions` — (player_id, offer_id, bbl, revenue, detected, penalty, black_score_before/after, credit_score_change)
- `players.black_market_score` — DECIMAL(5,2), 0–100

### Mechanika

**Generowanie ofert:**
- Co N ticków (domyślnie 3, klucz `bm_offer_interval_ticks`) system generuje 1–3 losowych ofert per gracz
- Ilość: 200–2000 bbl (skalowane do 80% magazynu)
- Cena: 130–200% oficjalnej ceny ropy
- Ryzyko bazowe: 15–40%
- Czas ważności: 6–18 ticków
- Gracz musi mieć ≥ 50 bbl w magazynie i nie być w kryzysie

**Transakcja:**
1. Gracz klika „Sprzedaj” na ofercie → modal potwierdzenia
2. Ropa pobierana z magazynu, gotówka na konto
3. **Black score** rośnie: +3 do +8 za transakcję
4. Rzut na wykrycie: `szansa = base_risk + (black_score × 0.5)%` (max 95%)

**Gdy wykryty:**
- Kara = % aktualnej kasy gracza (nigdy na minus):
  - score < 30 → ~5–10% kasy
  - score 30–60 → ~10–20% kasy
  - score > 60 → ~20–35% kasy
- Credit score: -3 do -10 pkt
- Wpis do admin_logs

**Black Score:**
- Zakres 0–100, domyślny decay: -0.5/tick
- Widoczny w profilu gracza (progress bar, kolorowy)
- Edytowalny przez admina

**Credit Score Recovery:**
- Legalna sprzedaż na oficjalnym rynku → +0.1 pkt/transakcję (klucz `credit_score_legal_recovery_rate`)

### Konfiguracja (well_config)

| Klucz | Domyślnie | Opis |
|-------|-----------|------|
| `bm_offer_interval_ticks` | 3 | Co ile ticków nowe oferty |
| `bm_score_decay_per_tick` | 0.5 | Spadek score/tick |
| `bm_min_bbl` / `bm_max_bbl` | 200 / 2000 | Zakres bbl w ofercie |
| `bm_price_mult_min` / `bm_price_mult_max` | 1.3 / 2.0 | Mnożnik ceny |
| `bm_base_risk_min` / `bm_base_risk_max` | 15 / 40 | Ryzyko bazowe (%) |
| `bm_score_gain_min` / `bm_score_gain_max` | 3 / 8 | Przyrost score za tx |
| `bm_penalty_low/mid/high_pct` | 7.5 / 15 / 27.5 | % kary wg score |
| `bm_offer_ttl_ticks_min/max` | 6 / 18 | Czas życia oferty (ticki) |
| `credit_score_legal_recovery_rate` | 0.1 | Recovery per legalną tx |

### UI Gracza

- Zakładka w `/market...tab=black_market`
- Score bar (progress, kolor: zielony < 30, złoty < 60, czerwony ≥ 60)
- Tabela ofert z ryzykiem (kolor: zielony/żółty/czerwony)
- Historia transakcji (status: udana/wykryta, kara, zmiana score)
- Warning banner gdy score > 50
- Sekcja w profilu gracza (score, tx count, przychód, kary)

### Panel admina (`admin/black_market.php` + `templates/views/admin/black_market/main.php`)

- Logika oddzielona od widoku (wzorzec `$viewData` + `extract()`, jak `admin/hr.php`)
- Statystyki globalne (tx, przychód, kary, wykrycia, unikalni gracze)
- Lista graczy z edytowalnym black_score — CSS grid (`bm-list-head/row`), bez `<table>`
- Historia transakcji z filtrowaniem per gracz — CSS grid (`bm-tx-head/row`), bez `<table>`
- Konfiguracja 16 kluczy `bm_*` + `credit_score_legal_recovery_rate` — CSS grid (`config-grid`)
- Zero inline styles — wszystkie klasy w `assets/css/black_market.css`

### Integracja z tick.php

- Sekcja §6 w `cron/tick.php`
- Expire przeterminowanych ofert
- Decay black_score wszystkich graczy
- Generowanie ofert co N ticków dla aktywnych graczy

### i18n

~80 kluczy `black_market.*` w `lang/pl.php` (zakładki, oferty, historia, profil, admin, komunikaty).

---

## Jakość kodu — PHPStan

PHPStan level 5 na `src/` → **0 błędów** (sesja type-hinting, kwiecień 2026).

Dodano precyzyjne adnotacje PHPDoc generic dla wszystkich tablic iteracyjnych:
- `array<string, mixed>` — asocjacyjne tablice (wiersze DB, konteksty)
- `list<array<string, mixed>>` — listy wierszy DB (wyniki fetchAll)
- `array<int, array<string, mixed>>` — cache indeksowany po int ID
- `list<string>` — proste listy stringów

Pliki objęte: wszystkie klasy w `src/` (serwisy, traity, helpers) — łącznie ~35 plików i ~28 traitów.

---

## 25. Separacja logiki od widoku — Faza 1

Realizacja wzorca MVC-lite opisanego w `BRIEF_VIEW_SEPARATION.md`. Każdy plik PHP dzielony na:
- **Kontroler** (`public/X.php` / `admin/X.php`) — tylko PHP: query, walidacja, obliczenia, `$viewData`
- **Widok** (`templates/views/X/main.php`) — tylko HTML z `<...= $zmienna ...>`

### Zrealizowane w sesji 10 Kwiecień 2026

#### Infrastruktura

| Plik | Opis |
|------|------|
| `src/i18n.php` | Funkcja `t('modul.klucz', [':param' => val])` — tłumaczenia inline przy separacji |
| `src/Cache.php` | Prosty cache plikowy z TTL; metody: `get`, `set`, `delete`, `flush`; pliki w `cache/` |
| `src/BankHelpers.php` | Funkcje pomocnicze banku (`loanStatusBadge`, `negStatusBadge`, `negTypeLabel`, `negEventIcon`) wyodrębnione z `public/bank.php` |
| `lang/pl.php` | Plik tłumaczeń PL — klucze `bank.*`, `index.*`, `common.*` (format `modul.klucz`) |
| `cache/.htaccess` | `Deny from all` — zabezpieczenie katalogu cache przed dostępem HTTP |
| `src/init.php` | Dodano `require_once` dla `Cache.php` i `i18n.php` |
| `tests/test_separation.php` | Test weryfikujący separację (brak DB/logiki w widokach) |
| `tests/test_cache.php` | Test funkcjonalności `Cache.php` |
| `tests/test_html_standards.php` | Test standardów HTML (brak tabel layoutowych, brak statycznych inline styles) |

#### Rozdzielone pliki

| Kontroler | Widok | Uwagi |
|-----------|-------|-------|
| `public/bank.php` | `templates/views/bank/main.php` | 1073L → kontroler 476L + widok 305L; i18n inline |
| `public/index.php` | `templates/views/index/main.php` | 350L → kontroler 210L + widok; i18n inline |

#### Poprawki standardów CSS — `templates/components/well_grid.php`

- Usunięto statyczny `style="color:var(--red);margin-top:6px"` → klasa CSS `.wg-diag-note--danger`
- Dodano `.wg-diag-note--danger` i `.wg-hidden` do `assets/css/style.css`
- Pozostałe `style=` w `well_grid.php` są dozwolone (dynamiczne PHP: `color:<...= $color ...>`, `width:<...= $cond ...>%`, `display:none` dla JS accordion)

### Bugfixy

| Problem | Przyczyna | Fix |
|---------|-----------|-----|
| `lang/pl.php` — `strict_types declaration must be the very first statement` | `Set-Content` PowerShell zapisywał plik z UTF-8 BOM przed `<...php` | Zapis przez `[System.IO.File]::WriteAllText` z `UTF8Encoding($false)` (bez BOM) |
| `cron/tick.php` — `Unknown column 'last_crisis_tick_at'` | Kolumna używana w SELECT ale nie dodana migracją | `ALTER TABLE players ADD COLUMN IF NOT EXISTS last_crisis_tick_at DATETIME NULL` w bootstrap `ensureBankruptcyRecoverySchema()` w `src/init.php`; SELECT zabezpieczony przez `COALESCE` |
| `templates/views/index/main.php` — `Failed opening required status_bar.php` | Komponent nazywa się `status_grid.php`, nie `status_bar.php` | Poprawiono ścieżkę require w widoku |

### i18n — nowe klucze (`lang/pl.php`)

```
bank.*          — ~80 kluczy: tytuły, sekcje, kredyty, negocjacje, blokady, wnioski
index.*         — ~40 kluczy: dashboard, komornik, magazyn, akcje, alerty
common.pln / common.apr / common.days / common.months / common.dash
```

### Wzorzec kontrolera (standard)

```php
require_once __DIR__ . '/../src/init.php';
Auth::requireLogin();
// ... logika, serwisy, POST handling ...
$viewData = compact('zmienna1', 'zmienna2', ...);
$pageTitle = t('modul.title');
require_once __DIR__ . '/../templates/header.php';
require __DIR__ . '/../templates/views/modul/main.php';
require_once __DIR__ . '/../templates/footer.php';
```

### Wzorzec widoku (standard)

```php
<...php extract($viewData, EXTR_SKIP); ...>
<!-- tylko HTML + <...= $zmienna ...> + t('klucz') -->
```

### Reguły kodowania (obowiązkowe)

- **ZERO** `<table>` dla layoutu — tylko dla naprawdę tabelarycznych danych
- **ZERO** `style=""` statycznych — wyjątek: dynamiczne wartości PHP (`width:<...= $pct ...>%`)
- **ZERO** zapytań DB i logiki biznesowej w widokach
- CSS gracza → `assets/css/style.css`; CSS admina → `assets/css/admin.css`

### Priorytety — pozostałe pliki do separacji

| Priorytet | Plik | Status |
|-----------|------|--------|
| ✅ | `public/bank.php` | Zrealizowane |
| ✅ | `public/index.php` | Zrealizowane |
| 🔴 | `admin/chat.php` (485L) — TABLE×30, inline×42 | Do zrobienia |
| 🔴 | `admin/map_locations.php` — inline×37 | Do zrobienia |
| 🔴 | `admin/player_clean.php` (487L) — inline×27 | Do zrobienia |
| 🟡 | `templates/components/well_grid.php` | CSS częściowo naprawiony |
| 🟡 | `templates/components/my_offers_table.php` — 15× `<table>` | Do zrobienia |
| 🟡 | `templates/components/offers_table.php` — 11× `<table>`, 10× inline | Do zrobienia |

---

### Faza 2 — integracja stron działów ze wspólnym shellem gry (28.04.2026)

Cel: strony kodowane później (`dashboard`, `boardroom`, `hr`, `technical`) mają wyglądać jak część głównej gry, a nie osobne aplikacje. Zachowany jest globalny header/nav, pasek statusu gracza i dolny blok AKCJE; właściwa zawartość działu renderuje się pomiędzy tymi sekcjami.

#### Nowa infrastruktura

| Plik | Rola |
|------|------|
| `src/GameShell.php` | Centralny helper dla widoków gracza: buduje `statusItems` (gotówka, magazyn, cena ropy, status) i `actions` z `nav_items location='actions'` lub fallbacku |
| `templates/components/game_shell.php` | Wspólny wrapper: `status_grid` → nagłówek modułu → widok modułu → `action_grid` |
| `templates/header.php` | Obsługa opcjonalnego `$extraHead` dla stron wymagających dodatkowych meta tagów, np. CSRF w `technical.php` |

#### Zintegrowane strony

| Strona | Zmiana |
|--------|--------|
| `dashboard.php` | Przejście na `templates/header.php` / `templates/footer.php`; widok `templates/views/dashboard/main.php` nie renderuje już własnego topbara ani pełnego dokumentu HTML |
| `boardroom.php` | Sala zarządu działa wewnątrz shellu gry; widok sceny zachowany, ale bez własnego `<html>`, `<body>`, headera i footera |
| `hr.php` | Panel HR działa pod globalnym headerem i statusem gry; usunięto lokalny topbar z `templates/views/hr/main.php` |
| `technical.php` | Panel techniczny działa pod globalnym headerem; lokalny topbar usunięty, CSRF meta przekazywane przez `$extraHead` |

#### CSS

- `assets/css/style.css` — dodano klasy `.game-shell`, `.game-shell-heading`, `.game-shell-module`
- `assets/css/dashboard.css` — globalne reset/body ograniczone do `.db-container`, żeby nie psuć layoutu gry
- `assets/css/hr.css` — końcowy override `body, body.hr-body` zawężony do `body.hr-body`
- `assets/css/boardroom-scene.css` — dodano `.br-shell-summary` dla krótkiego statusu sali zarządu wewnątrz shellu

#### Weryfikacja

`php -l` bez błędów dla:
- `dashboard.php`, `boardroom.php`, `hr.php`, `technical.php`
- `src/GameShell.php`
- `templates/header.php`
- `templates/components/game_shell.php`
- widoków `templates/views/{dashboard,boardroom,hr,technical}/main.php`

`git diff --check` — brak błędów whitespace; tylko standardowe ostrzeżenia Git o LF/CRLF na Windows.

---

## 26. Panel admina — odwierty (admin/wells.php) — i18n + zakładki konfiguracji

Sesja 21–22 Kwiecień 2026. Rozbudowa `admin/wells.php` o polskie tłumaczenia, czytelne etykiety parametrów i logiczny podział konfiguracji na zakładki.

### Zakładki panelu (`admin/wells.php`)

| ID zakładki | Etykieta | Zawartość |
|-------------|----------|-----------|
| `config` | ⚙ Parametry | Główne parametry gry: drilling, opex, production, maintenance, repair, upgrade, market, incident, crisis, balance |
| `sell` | 💰 Wycena i sprzedaż | Parametry wyceny odwiertu (`sell`) + ustawienia systemowe (`system`) z separatorem |
| `wells` | 🛢 Odwierty | Lista odwiertów z edycją inline (pressure, reservoir) |
| `events` | 📋 Zdarzenia | Dziennik zdarzeń odwiertów (ostatnie 50) |
| `help` | ❓ Pomoc | Instrukcja systemu odwiertów (10 sekcji) |

> Parametry czarnego rynku (`bm_*`) są zarządzane wyłącznie w `admin/black_market.php` — nie duplikowane w panelu wells.

### Podział kategorii `well_config` na grupy

```php
$catMain   = ['drilling','opex','production','maintenance','repair','upgrade','market','incident','crisis','balance'];
$catSell   = ['sell'];        // → zakładka "Wycena i sprzedaż"
$catSystem = ['system'];      // → zakładka "Wycena i sprzedaż" (separator na dole)
// kategoria 'general' (bm_*) → admin/black_market.php (nie wyświetlana w wells)
```

Nieznane przyszłe kategorie fallbackują do `$groupMain`.

### Czytelne etykiety parametrów (`admin.wells.key.*`)

Widok używa `t('admin.wells.key.' . $r['key'])` z fallbackiem do `$r['label']` z DB:

```php
$lKey = 'admin.wells.key.' . $r['key'];
echo t($lKey) !== $lKey ... t($lKey) : htmlspecialchars($r['label']);
```

Dodano ~50 kluczy `admin.wells.key.*` w `lang/pl.php`, m.in.:

| Klucz DB | Polska etykieta |
|----------|----------------|
| `sell_base_days_mult` | Wycena bazowa — dni zysku × ten mnożnik |
| `sell_eq_black_market` | Mnożnik wyceny: sprzęt z czarnego rynku |
| `sell_risk_divisor` | Dzielnik ryzyka w wycenie (niższy = większa kara za ryzyko) |
| `sell_wear_divisor` | Dzielnik zużycia w wycenie (niższy = większa kara za zużycie) |
| `last_system_tick_at` | Czas ostatniego ticka systemu |
| `incident_retention_days` | Czas przechowywania historii incydentów (dni) |
| `bm_offer_interval_ticks` | Czarny rynek: co ile ticków pojawia się nowa oferta |
| `bm_penalty_high_pct` | Czarny rynek: kara za wysoki poziom ryzyka (%) |
| `bm_score_decay_per_tick` | Czarny rynek: utrata punktów reputacji co tick |
| `credit_score_legal_recovery_rate` | Credit score: tempo odbudowy po wyjściu z kryzysu (pkt/tick) |

### Tłumaczenia kategorii (`admin.wells.cat.*`)

| Klucz | Polska nazwa |
|-------|-------------|
| `admin.wells.cat.drilling` | 🔩 Budowa i zakup odwiertów |
| `admin.wells.cat.opex` | 💸 Koszty operacyjne (OPEX) |
| `admin.wells.cat.production` | 🛢 Produkcja |
| `admin.wells.cat.maintenance` | 🔧 Konserwacja |
| `admin.wells.cat.repair` | 🔨 Naprawa i wymiana |
| `admin.wells.cat.upgrade` | ⬆ Modernizacje |
| `admin.wells.cat.market` | 📊 Rynek |
| `admin.wells.cat.sell` | 💰 Wycena sprzedaży odwiertu |
| `admin.wells.cat.crisis` | 🚨 Kryzys finansowy i bankructwo |
| `admin.wells.cat.balance` | ⚖ Balans rozgrywki |
| `admin.wells.cat.general` | 🔩 Ustawienia ogólne |
| `admin.wells.cat.system` | 🖥 Parametry systemowe |
| `admin.wells.cat.incident` | ⚠️ Incydenty i awarie |

### Separatory grup (`admin.wells.group_*`)

W zakładce „Wycena i sprzedaż" między `sell` a `system` wyświetla się separator:

```html
<div class="config-group-separator config-group-separator--system">
    <span><...= t('admin.wells.group_system') ...></span>
</div>
```

Klasy CSS: `.config-group-separator` (linia + label), `.config-group-separator--system` (szary wariant).

### Zakładka Pomoc — 10 sekcji (`admin.wells.help.*`)

| Sekcja | Temat |
|--------|-------|
| s1 | Jak działa odwiert... (produkcja, OPEX, wear, wyczerpanie) |
| s2 | Statusy odwiertu (active, broken, paused_cash, paused_storage, staff, blowout) |
| s3 | Produkcja — jak jest liczona... (formuła, sprzęt, ciśnienie, warstwa, operator) |
| s4 | Efektywne ciśnienie i wyczerpanie złoża (formuła, zachowanie przy wyczerpaniu) |
| s5 | Zużycie i stan techniczny (technik, warstwa, wear, spirala) |
| s6 | Warstwy geologiczne (shallow/mid/deep/ultra — produkcja, ryzyko, koszty) |
| s7 | Koszty operacyjne OPEX (aktywny/paused_storage/paused_cash) |
| s8 | Kryzys finansowy (warning → crisis → bankructwo, grace period, credit score) |
| s9 | Sprzedaż odwiertu (wycena, modyfikatory, cooldown, flow gracza) |
| s10 | Parametry konfiguracyjne — co regulować... (kluczowe stałe systemu) |

Sekcja s11 (Czarny rynek sprzętu) przeniesiona do zakładki `admin/black_market.php`.

### Pliki zmodyfikowane

| Plik | Zmiany |
|------|--------|
| `lang/pl.php` | ~50 nowych kluczy `admin.wells.key.*`, `admin.wells.cat.*`, `admin.wells.group_*`, `admin.wells.tab_*`, `admin.wells.help.*` |
| `templates/views/admin/wells/main.php` | Podział na 5 zakładek; `wellsRenderSection()` jako nazwana funkcja PHP; grupowanie kategorii; separatory; zakładka Help z 10 sekcjami |
| `assets/js/admin_wells.js` | Tablica zakładek: `['config','sell','wells','events','help']` |
| `assets/css/admin.css` | `.config-group-separator`, `.config-group-separator--system`, `.config-save-bar`, `.bm-info`, `.help-section--dark` |

---

### Aktualizacja (03.05.2026) - technical i panel admina gracza

#### Technical
- Drobne etykiety pomocnicze w panelu technicznym zostaly uporzadkowane i przepiete pod spojniejszy system:
  - `templates/views/technical/tabs/candidates.php`
  - `templates/views/technical/tabs/tasks.php`
  - `templates/views/technical/tabs/team.php`
  - `templates/views/technical/tabs/wells.php`
  - `templates/views/technical/tabs/well_staff.php`
- Ograniczono twarde skróty i stare znaczniki w UI (`OK`, `X`, `V`, `TASK`, `STOP`, `CASH`, `MAG`, `AWR`, `SKAZ`) i przepieto je na klucze `technical.short_*` oraz bezpieczne encje HTML tam, gdzie to miało sens.
- `well_staff.php` zostal wyczyszczony z najbardziej widocznych resztek starego kodowania w badge'ach statusu i przyciskach akcji.

#### Admin - profil gracza
- `admin/player_clean.php` zostal uporzadkowany pod katem logow i komunikatow:
  - `AdminLog::log(...)` dla akcji na gotowce, statusach, ticku, trust score, `credit_score` i bankructwie korzysta teraz z kluczy `admin.player.log_*`
  - komunikat ustawiania `credit_score` korzysta z `admin.player.msg_credit_score_set`
  - bledy ustawiania `credit_score` korzystaja z `admin.player.err_credit_score_set`
  - mapowanie statusow gracza i odwiertow opiera sie na tlumaczeniach `player.status.*` i `well.status.*`
- `templates/views/admin/player/main.php` dostal czesc porzadkow i18n:
  - naglowek strony korzysta z `admin.player.title`
  - sekcja `Credit Score` jest podpieta pod `admin.player.credit_score_*`
  - dodano brakujace klucze pomocnicze dla paginacji i formularza trust/credit score

#### i18n
- `lang/pl.php` rozszerzono o:
  - `technical.skill_short`
  - `technical.manager_badge`
  - `technical.rec_ok_short`
  - `technical.short_*`
  - `admin.player.msg_cash_set`
  - `admin.player.msg_credit_score_set`
  - `admin.player.err_credit_score_set`
  - `admin.player.log_*`
  - `admin.player.credit_score_*`
  - `admin.player.pagination_wells`
  - `admin.player.trust_current`
  - `admin.player.trust_adjust_label`
  - `admin.player.trust_reason_placeholder`
  - `admin.player.trust_save_delta`

#### Weryfikacja
- `php -l` bez bledow dla:
  - `admin/player_clean.php`
  - `templates/views/admin/player/main.php`
  - `templates/views/technical/tabs/candidates.php`
  - `templates/views/technical/tabs/tasks.php`
  - `templates/views/technical/tabs/team.php`
  - `templates/views/technical/tabs/wells.php`
  - `templates/views/technical/tabs/well_staff.php`
  - `lang/pl.php`

---

## 29. System Hubów Logistycznych

Huby to systemowa infrastruktura przeładunkowa — gracze **nie są właścicielami** hubów, lecz przypisują do nich swoje odwierty. Hub obsługuje transport ropy z odwiertów do magazynu gracza.

### Tabele DB

| Tabela | Opis |
|--------|------|
| `logistics_hubs` | Huby systemowe (player_id=0): typ, pojemność, kondycja, status, tryb pracy |
| `logistics_hub_assignments` | Przypisania odwiert→hub (status active/detached, cooldown) |
| `logistics_hub_events` | Zdarzenia i incydenty per hub |
| `logistics_hub_tick_stats` | Statystyki ticka (input/processed/lost/wear) — 7 dni historii |
| `logistics_region_zones` | Strefy per region (kary dystansowe za cross-zone) |

### Serwisy (`src/`)

| Serwis | Odpowiedzialność |
|--------|-----------------|
| `HubService` | Konfiguracja typów, trybów pracy, koszty napraw/upgrade, helper `createEvent()` |
| `HubTickService` | Przetwarzanie ticka: przepustowość, bufor, wear, degradacja kondycji, straty z kondycji |
| `HubAssignmentService` | Przypisanie/odpięcie/transfer odwiertu; walidacja slotów, regionu, cooldownu; ostrzeżenia kondycji |
| `HubEconomyService` | OPEX per hub, fee od gracza za obsługę odwiertu |
| `HubViewService` | Dane widoku: karty hubów, statystyki, lista dostępnych, incydenty |
| `HubIncidentService` | Losowanie, generowanie i zapis incydentów logistycznych |
| `HubApi.php` | Endpoint AJAX dla wszystkich akcji gracza (GET + POST) |

### Typy hubów

| Typ | Pojemność | Sloty | Opis |
|-----|-----------|-------|------|
| `small` | niska | 3–5 | Lokalne, tanie w utrzymaniu |
| `medium` | średnia | 8–12 | Standardowe |
| `large` | wysoka | 20+ | Regionalne huby główne |

### Tryby pracy (`work_mode`)

| Tryb | Przepustowość | Wear | Ryzyko incydentów |
|------|--------------|------|------------------|
| `eco` | ×0.8 | ×0.6 | ×0.6 |
| `standard` | ×1.0 | ×1.0 | ×1.0 |
| `max` | ×1.2 | ×1.5 | ×1.5 |

### Przetwarzanie ticka (`HubTickService::processTick`)

**Kondycja a przepustowość** — progi, nie liniowe:

| Kondycja | Max przepustowość | Wear bonus | Status |
|----------|------------------|-----------|--------|
| > 30% | liniowo (cond/100) | ×1.0 | active |
| ≤ 30% | max 30% nominalnej | ×1.4 | damaged |
| ≤ 20% | max 10% nominalnej | ×2.0 | **critical** |

**Bezpośrednie straty z kondycji** (niezależnie od przepustowości):

| Kondycja | Straty z przetworzonego wolumenu |
|----------|----------------------------------|
| 50–70% | 1–3% |
| 30–50% | 3–8% |
| 20–30% | 8–15% |
| ≤ 20% | 15–25% |
| Overload (load > 100%) | powyższe ×1.8 |

**Wear** — dodatkowy mnożnik przeciążenia: `overWearMult × min(3.0, loadPct/100)`.

### Incydenty logistyczne (`HubIncidentService`)

6 typów incydentów, szanse per godzinę skalowane przez `deltaHours × riskMult`:

| Typ | Severity | Condition DMG | Extra loss |
|-----|----------|--------------|-----------|
| `transfer_failure` | medium | 1–3 pkt | 5–20% inputBbl |
| `equipment_damage` | high | 3–8 pkt | 10–25% |
| `local_leak` | medium | 2–5 pkt | 10–35% |
| `loading_error` | low | 0–1 pkt | 3–12% |
| `storage_jam` | low | 0–2 pkt | 5–15% |
| `critical_overload` | critical | 5–15 pkt | 20–60% (tylko load > 100%) |

**Mnożnik ryzyka** (`calcRiskMultiplier`):

| Czynnik | Wartość | Mnożnik |
|---------|---------|---------|
| Kondycja ≤ 20% | critical | ×6.0 |
| Kondycja < 30% | damaged | ×3.0 |
| Kondycja < 50% | | ×1.8 |
| Kondycja < 70% | | ×1.3 |
| Load > 120% | | ×3.0 |
| Load > 100% | | ×2.0 |
| Load > 80% | | ×1.4 |
| Tryb `max` | | ×1.5 |
| Tryb `eco` | | ×0.6 |

Incydenty zapisywane do `logistics_hub_events` i `technical_notifications`. Wyświetlane w zakładce Incydenty w `/technical`.

Komunikaty: `lang/pl.php`, klucze `logistics.hub.incident.{typ}.{0-3}` — przez `tPlain()`.

### Przypisanie odwiertu — walidacja i ostrzeżenia

`HubAssignmentService::validateAssignment()` sprawdza kolejno:
1. Hub istnieje
2. Status hub: nie `disabled`/`building`/`paused`
3. Odwiert należy do gracza
4. Brak aktywnego przypisania / cooldown po odpięciu (4h)
5. Region odwiertu = region huba
6. Wolny slot
7. **Ostrzeżenie kondycji** (nie blokuje):
   - kondycja ≤ 20% → `warning: 'condition_critical'`
   - kondycja ≤ 40% → `warning: 'condition_low'`

Ostrzeżenie przekazywane przez API do JS → wyświetlane w modalu dialogowym po potwierdzeniu przypisania.

### UI Gracza (`/logistics`)

- **Karty hubów** — kondycja, obciążenie, sloty, moje odwierty; badge ryzyka (`⚠ Ryzyko` / `🟠 Wysokie ryzyko` / `🔴 Ryzyko krytyczne`) obliczany z condition + load
- **Karty hubów** — bezpośrednia akcja `Przypisz odwiert`, która otwiera modal z odwiertami bez huba pasującymi do wybranego huba
- **Badge statusu** — active / overloaded / damaged / **critical** / paused / building / disabled
- **Modal: Moje odwierty** — lista z przyciskami Odepnij i Przenieś
- **Modal: Przypisz odwiert** — lista dostępnych hubów z etykietą złego stanu kondycji
- **Modal: Transfer** — zmiana huba bez detach/cooldown (osobna ścieżka)
- **Sekcja incydentów huba** — ostatnie zdarzenia w infrastrukturze
- **Odwierty bez huba** — lista z przyciskiem przypisania
- **Gdzie logistyka zjada wynik** — blok z największymi stratami, najdroższymi odwiertami, problematycznymi hubami i rekomendowanym kolejnym krokiem

### Integracja z tickiem (`WellLoopSection`)

Po `HubTickService::processTick()` i `persistTickResult()`:
1. `HubIncidentService::processTick()` — losowanie incydentu
2. Jeśli incydent: `extra_loss` odejmowane z `currentStorage`, `finBbl`, `finRevenue`
3. OPEX fee od gracza za obsługę odwiertu przez hub

### i18n

Wszystkie teksty w `lang/pl.php`:
- `logistics.hub.*` — UI, statusy, etykiety, tryby, ostrzeżenia kondycji, risk badge
- `logistics.hub.incident.{typ}.{0-3}` — 24 komunikaty incydentów
- `logistics.hub.incident.title.{typ}` — 6 tytułów typów

Komunikaty do bazy przez `tPlain()` (bez htmlspecialchars).

### Pliki

| Plik | Rola |
|------|------|
| `src/HubService.php` | Konfiguracja, eventy, fallback |
| `src/HubTickService.php` | Tick: przepustowość, wear, degradacja, straty, status |
| `src/HubAssignmentService.php` | Assign / detach / transfer + walidacja |
| `src/HubEconomyService.php` | OPEX fee |
| `src/HubViewService.php` | Dane widoku gracza |
| `src/HubIncidentService.php` | Incydenty logistyczne |
| `src/HubApi.php` | Endpoint AJAX |
| `logistics.php` | Strona gracza `/logistics` |
| `templates/views/logistics/main.php` | Widok strony |
| `assets/js/logistics_hubs.js` | Logika JS modułu (modale, fetch) |
| `admin/logistics_hubs.php` | Panel admina hubów |

---

### Aktualizacja (09.05.2026) — System Hubów Logistycznych + refaktory

#### Nowe funkcjonalności

- **System Hubów Logistycznych** (§29) — pełna implementacja: serwisy, tick, incydenty, UI, admin
- **Próg krytyczny kondycji 20%** — nowy status `critical`, przepustowość max 10% nominalnej, wear ×2.0, straty wolumenu 15–25%, mnożnik ryzyka incydentów ×6.0
- **Transfer odwiertu** między hubami bez cooldownu (modal `hub-transfer-modal`)
- **Risk badge** na kartach hubów — medium/high/critical, łączy condition + load
- **Ostrzeżenie kondycji** przy przypisaniu odwiertu — modal po sukcesie assign

#### Refaktory i18n

- **`tPlain(string $key, array $replace = []): string`** — nowa funkcja w `src/i18n.php`, bez `htmlspecialchars`; używana wszędzie gdzie tekst trafia do DB (incydenty, notyfikacje, logi)
- **`_langLoad(): array`** — wydzielona funkcja cache'owania tablicy tłumaczeń; współdzielona przez `t()` i `tPlain()`
- **`HubIncidentService`** — komunikaty przeniesione z `const MESSAGES` do `lang/pl.php` (klucze `logistics.hub.incident.*`), generator używa `tPlain()`
- **`IncidentService / MessagesTrait`** — hardcodowane polskie komunikaty awarii studni przeniesione z `static array $MESSAGES` do `lang/pl.php` (klucze `incident.{level}.{cause}.{index}`), generator używa `tPlain()`; `interpolate()` usunięty

#### System modalny — rozszerzenie reguły

- **`agent.md` §8** — zaktualizowany: wszystkie komunikaty, ostrzeżenia i powiadomienia w grze i panelu admina przechodzą przez system modalny (`modal.js`); zakazane toasty, inline bannery jako główny kanał, natywne `confirm()`/`alert()`/`prompt()`
- **`logistics_hubs.js`** — akcje logistyczne korzystają z globalnego systemu `confirmAction(...)`, `alertError(...)`, `alertWarning(...)` i `showGameToast(...)`; lokalny modal dialogowy nie jest już potrzebny

#### Pliki zmienione

| Plik | Zmiany |
|------|--------|
| `src/HubTickService.php` | Progi kondycji (20%/30%), `calcConditionFactor()`, `calcConditionLoss()`, status `critical`, wear mnożniki, pole `condition_lost_bbl` w wyniku |
| `src/HubIncidentService.php` | Status `critical` dopuszczony do incydentów, mnożnik ×6.0 przy cond ≤ 20%, komunikaty → `tPlain()` |
| `src/HubAssignmentService.php` | Ostrzeżenia kondycji (`condition_critical`/`condition_low`) w `validateAssignment()` i `assignWell()` |
| `src/HubApi.php` | Propagacja `warning` z assign do JSON response |
| `src/i18n.php` | `_langLoad()`, `tPlain()` |
| `src/Incident/MessagesTrait.php` | `$MESSAGES` → `MSG_COUNT`, `interpolate()` usunięty, `tPlain()` |
| `lang/pl.php` | +24 klucze `logistics.hub.incident.*`, +6 `logistics.hub.incident.title.*`, +22 klucze `incident.{level}.{cause}.{index}`, +klucze risk/warn/cond |
| `assets/js/logistics_hubs.js` | `hubDialog()`, `hubConfirm()`, usunięty `notifyHub()`/`confirm()` |
| `templates/views/logistics/main.php` | Risk badge, modal przypisania/przeniesienia oraz `HUB_LANG` rozszerzony o etykiety wspólnego systemu |
| `assets/css/style.css` | Status `critical`, `.hub-risk-badge`, `.hub-dialog-*` |
| `.windsurf/agent.md` | §8 rozszerzony (wszystkie komunikaty przez modal) |

---

### Aktualizacja (13.05.2026) — Finanse: etap 1 integracji z hubami logistycznymi

#### Co wdrożono

- `FinanceService` zapisuje i agreguje osobno:
  - koszt użycia hubów,
  - straty przeciążenia hubów,
  - straty odwiertów bez huba,
  - straty incydentów hubów
- `WellLoopSection` oraz `PlayersSection` przekazują te dane bezpośrednio z ticka do `finance_logs`
- panel gracza `/finance` pokazuje osobną sekcję:
  - **Huby logistyczne i odwierty bez huba**
- panel admina `/admin/finance` pokazuje osobno:
  - koszt hubów,
  - straty hubów,
  - straty fallbacku,
  - straty incydentów hubów
- rozszerzono alerty finansowe o logikę hubów
- dodano migrację:
  - `sql/finance_hub_metrics_2026_05_13.sql`

#### Pliki zmienione

| Plik | Zmiany |
|------|--------|
| `src/FinanceService.php` | nowe kolumny, zapis ticka, agregaty, alerty, statystyki admina |
| `src/Tick/WellLoopSection.php` | osobne liczniki kosztów i strat hubowych |
| `src/Tick/PlayersSection.php` | przekazanie danych hubowych do `FinanceService::saveTick()` |
| `public/finance.php` | przygotowanie danych nowego widoku finansów |
| `templates/views/public/finance/main.php` | sekcja hubów logistycznych, rozszerzony breakdown i KPI |
| `admin/finance.php` | globalne i per-player statystyki hubowe |
| `templates/views/admin/finance/main.php` | widok admina z blokiem kosztów i strat hubów |
| `lang/pl.php` | nowe polskie klucze dla finansów i admina |
| `sql/finance_hub_metrics_2026_05_13.sql` | migracja kolumn `finance_logs` |

#### Weryfikacja

- `php -l` bez błędów dla wszystkich ruszanych plików
- pliki zapisane jako UTF-8 bez BOM
- `phpstan analyse` bez błędów dla:
  - `src/FinanceService.php`
  - `src/Tick/WellLoopSection.php`
  - `src/Tick/PlayersSection.php`
  - `public/finance.php`
  - `admin/finance.php`

### Aktualizacja (13.05.2026) — Finanse: etap 2 (budżety, płynność, ryzyko)

#### Co wdrożono

- panel gracza `/finance` został rozbudowany o zakładki:
  - **Przegląd**
  - **Budżety**
  - **Płynność**
  - **Ryzyko**
  - **Historia decyzji**
- dodano nowy serwis:
  - `src/FinancePolicyService.php`
- wdrożono nowe tabele:
  - `player_finance_settings`
  - `player_finance_decisions`
- gracz może ustawiać poziom budżetu dla:
  - technicznego,
  - logistyki,
  - HR,
  - BHP,
  - oraz poziom rezerwy gotówkowej
- ustawienia finansowe realnie wpływają na działanie gry:
  - **Techniczny** — modyfikują OPEX, zużycie i degradację odwiertów
  - **Logistyka** — modyfikuje koszty transportu i hubów oraz poziom strat logistycznych
  - **HR** — modyfikuje czas rekrutacji i jakość kandydatów
  - **BHP** — modyfikuje ryzyko incydentów i katastrof
- dodano sekcję **Płynność**, która liczy:
  - prognozę na tick,
  - prognozę na godzinę,
  - prognozę na 6 godzin,
  - prognozę na 24h,
  - docelową rezerwę gotówkową,
  - pokrycie kosztów w godzinach
- dodano sekcję **Ryzyko**, która ocenia:
  - płynność,
  - logistykę,
  - koszty,
  - operacje,
  - politykę finansową
- dodano **Historię decyzji**, która zapisuje zmiany polityki finansowej gracza
- panel admina `/admin/finance` dostał nową sekcję:
  - **Rozkład polityk finansowych graczy**

#### Pliki zmienione

| Plik | Zmiany |
|------|--------|
| `src/FinancePolicyService.php` | nowy serwis ustawień finansowych gracza, schema, historia decyzji, mnożniki budżetów |
| `src/FinanceService.php` | logika płynności i oceny ryzyka |
| `public/finance.php` | obsługa zakładek, zapis ustawień, dane do widoku |
| `templates/views/public/finance/main.php` | nowy układ zakładek i sekcji żywego panelu finansowego |
| `assets/css/finance.css` | style sekcji budżetów, płynności, ryzyka i historii decyzji |
| `src/Tick/WellLoopSection.php` | wpływ budżetów finansowych na tick techniczny i logistyczny |
| `src/HR/RecruitmentTrait.php` | wpływ budżetu HR na czas rekrutacji |
| `src/CandidateGenerator.php` | wpływ budżetu HR na jakość kandydatów |
| `admin/finance.php` | dane o rozkładzie polityk finansowych graczy |
| `templates/views/admin/finance/main.php` | nowa sekcja admina z rozkładem polityk |
| `lang/pl.php` | nowe polskie klucze dla zakładek, budżetów, płynności, ryzyka i historii |
| `sql/finance_policy_stage2_2026_05_13.sql` | migracja tabel `player_finance_settings` i `player_finance_decisions` |

#### Weryfikacja

- `php -l` bez błędów dla wszystkich ruszanych plików
- wszystkie zmienione pliki zapisane jako UTF-8 bez BOM
- `phpstan analyse` bez błędów dla:
  - `src/FinancePolicyService.php`
  - `src/FinanceService.php`
  - `public/finance.php`
  - `admin/finance.php`
  - `src/Tick/WellLoopSection.php`
  - `src/HR/RecruitmentTrait.php`
  - `src/CandidateGenerator.php`

*Ostatnia aktualizacja: 13 Maj 2026 (Finanse: etap 2 — budżety, płynność, ryzyko, historia decyzji, wpływ na tick, HR i logistykę) | poprzednio: 13 Maj 2026 (Finanse: etap 1 integracji z hubami logistycznymi — osobne koszty i straty hubów w `finance_logs`, `/finance`, `/admin/finance`, alerty i migracja SQL)*

---

### Aktualizacja (15.05.2026) — UI/UX: system komunikatów, rynek, burger menu, globalne CSS

#### System komunikatów (UI Communication System)

- **`assets/js/finance.js`** — toast dla `$msg`/`$err` przez `showGameToast()`; confirmacje dla KAŻDEJ zmiany polityki finansowej (tryb agresywny/umiarkowany/off, rezerwa awaryjna — każda zmiana wymaga potwierdzenia w modalu); `window._FIN_MSG`, `_FIN_ERR`, `_FIN_CUR_MODE`, `_FIN_CUR_RESERVE`, `_FIN_CONFIRM` przekazywane z PHP
- **`templates/views/public/finance/main.php`** — `data-confirm` na formularzu budżetów działowych; `id="fin-msg-banner"` / `id="fin-err-banner"` na bannerach sukcesu/błędu (ukrywane przez JS na rzecz toastów)
- **`templates/views/admin/finance/main.php`** — `data-confirm` na formularzach zapisu mnożników (type=warning) i konfiguracji alertów (type=info); toast dla `$msg`/`$err`
- **`lang/pl.php`** — nowe klucze: `finance.confirm_aggressive`, `finance.confirm_moderate`, `finance.confirm_turnoff`, `finance.confirm_reserve`, `finance.confirm_budget`, `market.confirm_sell_instant`, `market.confirm_sell_btn`, `market.confirm_create_offer`, `market.confirm_offer_btn`, `black_market.loading`

#### Rynek i czarny rynek

- **`assets/js/market.js`** (nowy plik) — `confirmAction()` przed sprzedażą instant (`:bbl`, `:total`) i wystawieniem oferty limit (`:bbl`, `:price`); toast dla `MARKET_MSG`/`MARKET_ERR` przez `showGameToast()`; ładowany na `/market` i `/market-offers`
- **`public/market.php`** — bugfix: `create_offer` używał raw INSERT bez blokowania magazynu; zastąpione przez `MarketOffer::createOffer()` (transakcja, race-condition protection, `UPDATE storage SET used = used - amount WHERE used >= amount`)
- **`public/market_offers.php`** — dodano `market.js`; klasa `form-sell` i `data-action-type` na formularzu
- **`assets/js/black_market.js`** — loading state podczas sprzedaży: dezaktywacja wszystkich `.bm-sell-btn` + tekst "Przetwarzanie…"; przywrócenie przycisków przy błędzie; `loadOffers()` re-renderuje przyciski po sukcesie

#### Bugfix bank.js

- **`assets/js/bank.js`** — null reference crash na linii 44: `repayModalClose()` nullował `_repayPendingForm` przed wywołaniem `.submit()`; naprawione przez `var formToSubmit = _repayPendingForm` przed `repayModalClose()`

#### Burger menu — nawigacja mobilna

- **`templates/header.php`** — dodano `<button class="nav-burger" id="nav-burger">` (3 spany — linie burgera) przed `<nav class="user-nav">`; `<div class="nav-backdrop" id="nav-backdrop">` po `</header>`; inline JS IIFE: `openNav()`/`closeNav()` z `body.nav-open`, Escape key, auto-close po kliknięciu linku nav
- **`assets/css/mobile.css`** — sekcja burger menu `@media (max-width: 600px)`: `.nav-burger { display: flex }`, `.user-nav { position: fixed; right: -280px; transition: right .28s }` → `body.nav-open .user-nav { right: 0 }`, animacja X (span 1: `translateY(9px) rotate(45deg)`, span 2: `opacity:0`, span 3: `translateY(-9px) rotate(-45deg)`), backdrop `rgba(0,0,0,.55)` z blur; `100vh` fallback + `100dvh` dla iOS

#### Globalne CSS — poprawki nawigacji (wszystkie strony)

- **`assets/css/style.css`** — przeniesiono `.topbar-user-link`, `.topbar-avatar`, `.topbar-avatar-initials` z `well-grid.css` (ładowanego tylko na `index.php`) do `style.css` (globalny) — avatar miał nieograniczony rozmiar na stronach banku, logistyki, finansów, HR, technicznego itd.; przeniesiono `.user-nav .nav-active` (podświetlenie aktywnej zakładki) — działało tylko na `index.php`
- **`assets/css/style.css`** — `.header { flex-wrap: wrap }`, `.user-nav { flex: 0 0 100%; flex-wrap: wrap; justify-content: flex-end }` — nawigacja zawsze zajmuje pełny drugi rząd pod logo; eliminuje problem obcinania przycisków i "samotnego WYLOGUJ" na osobnej linii

#### Pliki zmienione

| Plik | Zmiany |
|------|--------|
| `assets/js/finance.js` | Toasty + confirmacje polityki finansowej |
| `assets/js/market.js` | Nowy plik — confirmacje i toasty dla rynku |
| `assets/js/black_market.js` | Loading state sprzedaży |
| `assets/js/bank.js` | Bugfix null reference przy sprzedaży |
| `public/market.php` | Bugfix create_offer — `MarketOffer::createOffer()` |
| `public/market_offers.php` | Integracja market.js |
| `templates/views/public/finance/main.php` | data-confirm budżety, id na bannerach |
| `templates/views/admin/finance/main.php` | data-confirm, toasty |
| `templates/header.php` | Burger button, backdrop, inline JS |
| `assets/css/style.css` | topbar-avatar, nav-active globalnie; layout headera |
| `assets/css/mobile.css` | Burger menu ≤600px; usunięto konflikty nav |
| `lang/pl.php` | +10 kluczy confirmacji (finanse, rynek, czarny rynek) |

---

### Aktualizacja (05.06.2026) — Wiarygodność firmy: fundament systemu

#### Cel

Ogólny wskaźnik reputacji firmy wobec świata gry (`company_credibility`, skala 0–100, start 50).
NIE zastępuje `credit_score`, `bank_trust_scores` ani `black_market_score` — to osobny, nadrzędny wskaźnik
pod przyszły dział prawny, trudniejsze regiony, kontrakty i przetargi.

#### Architektura

| Plik | Zmiany |
|------|--------|
| `src/CompanyCredibilityService.php` | Nowy serwis: `getScore`, `getLevel`, `changeScore`, `logChange`, `applyEvent`, `getHistory`, `ensureSchema` (guarded, DDL odraczane poza transakcję) |
| `migrations/etap12_company_credibility.sql` | Pole `players.company_credibility` + tabela `company_credibility_log` |
| `templates/components/company_credibility.php` | Karta na dashboardzie gracza (wynik, poziom, opis) |
| `assets/css/credibility.css` | Style karty gracza |
| `public/index.php` | Pobranie wyniku + przekazanie do widoku, podpięcie CSS |
| `templates/views/index/main.php` | Włączenie komponentu karty |
| `admin/credibility.php` | Panel admina: lista graczy, historia, ręczna korekta |
| `templates/views/admin/credibility/main.php` | Widok admina — CSS Grid (zero tabel HTML), modal korekty |
| `assets/js/admin_credibility.js` | Sterowanie modalem ręcznej korekty |
| `assets/css/admin.css` | Style admina: odznaki, grid listy, modal |
| `admin/partials/header.php` | Pozycja w nawigacji (sekcja Finanse) |
| `lang/pl/credibility.php` | Tłumaczenia gracza (`credibility.*`) |
| `lang/pl/admin/credibility.php` | Tłumaczenia admina (`admin.credibility.*`) |
| `lang/pl/admin/nav.php` | Klucz nawigacji |
| `lang/pl.php` | Rejestracja loadera `credibility.php` |
| `tests/Integration/CompanyCredibilityServiceTest.php` | 21 testów (zakres, log, poziomy, próg powiadomienia, guard) |

#### Poziomy (sekcja 2 briefu)

| Zakres | Poziom |
|--------|--------|
| 0–19 | krytyczna |
| 20–39 | niska |
| 40–59 | chwiejna |
| 60–79 | stabilna |
| 80–100 | wysoka |

#### Podpięte zdarzenia (sekcja 6)

| Zdarzenie | Delta | Punkt podpięcia |
|-----------|-------|-----------------|
| `black_market_detected` | −12 | `BlackMarketService::executeTransaction` (po commit) |
| `bailiff_activated` | −20 | `BailiffService::startNewProceedings` |
| `bankruptcy_entered` | −25 | `BailiffService::declareBankruptcy` |
| `recovery_plan_broken` | −10 | `BankNegotiation/ProcessorTrait::checkRecoveryPlanViolations` |
| `major_payment_delay` | −6 | `LoanRepository::processInstallment` (przejście w `late`) |
| `loan_installment_paid_on_time` | +2 | `LoanRepository::processInstallment` (rata) |
| `loan_fully_repaid` | +8 | `LoanRepository::processInstallment` (spłata) |
| `loan_repaid_early` | +6 | `Bank/RepaymentTrait::repay` (po commit) |

`clean_operation_period` (+3) i `admin_manual_adjustment` — pierwszy odłożony (brief 6.2),
drugi sterowany ręcznie z panelu admina.

#### Zasady

- Każda zmiana przechodzi wyłącznie przez `CompanyCredibilityService` (sekcja 4).
- Każda zmiana jest logowana w `company_credibility_log` (sekcja 3).
- Wynik twardo przycinany do 0–100 (sekcja 1.1).
- Powiadomienie dyrektora tylko przy `|delta| >= 5` (sekcja 9), typ `credibility`, guarded.
- Wszystkie podpięcia są try/catch — nigdy nie wywracają operacji nadrzędnej.

#### Co nie wchodzi w fundament (TODO, sekcja 14–15)

Wpływ na dział prawny, regiony, kontrakty, przetargi, łapówki, audyty, partnerów,
offshore oraz automatyczna odbudowa wyniku — odłożone na kolejne etapy.

---

### Aktualizacja (04.06.2026) — Dział prawny P1: zezwolenia na wiercenie

#### Cel

Gracz musi uzyskać **zezwolenie na wiercenie** w każdym regionie zanim kupi tam odwiert.
System działa per-region i obejmuje 7 statusów (none / pending / delayed / no_decision / granted / refused / transitional / locked).

#### Architektura

| Plik | Zmiany |
|------|--------|
| `src/LegalService.php` | Nowy serwis: `ensureSchema`, `seedRegionConfig`, `submitApplication`, `migrateTransitionalPermits`, `getMapPermitData`, `notifyDirector` (prywatna) |
| `public/legal.php` | Nowy kontroler gracza — klasyfikuje regiony do 4 kubełków: `$activePermits`, `$pendingApplications`, `$capitalLocked`, `$available` |
| `templates/views/legal/main.php` | Nowy widok gracza — lista regionów z badge'ami statusów i formularzem złożenia wniosku |
| `assets/css/legal.css` | Nowy plik CSS dla widoku gracza (karty regionów, badge'e, kapita-locked, notatki) |
| `src/WorldMap.php` | `getMapData()`: zastąpiono N+1 `hasActivePermit()` jednym batch-requestem `getMapPermitData()` |
| `assets/js/world_map.js` | Dodano `fmtMinutes()`, `permitBadge()`, `buildPermitHtml()` — badge'e i modale na mapie per status |
| `assets/css/map.css` | Nowe klasy `.loc-badge--permit-*`, `.sr-permit--active`, `.loc-permit-required--*` |
| `templates/views/map/main.php` | Klucze `MAP_LANG` rozszerzone o 15 kluczy `map_js.permit_*`; div `#sr-permit` w panelu regionu |
| `admin/legal.php` | Nowy panel admina: konfiguracja regionów, lista wniosków, 5 akcji manualnych (grant/transitional/no_decision/refuse/reset→pending), seed, migracja |
| `templates/views/admin/legal/main.php` | Widok admina — tabela konfiguracji regionów z edycją inline, tabela wniosków z akcjami |
| `lang/pl/legal.php` | Nowy plik tłumaczeń działu prawnego gracza (klucze `legal.*`) |
| `lang/pl/admin/legal.php` | Nowy plik tłumaczeń panelu admina (klucze `admin.legal.*`) |
| `lang/pl/map.php` | +15 kluczy `map_js.permit_*` i `map_js.political_risk` |
| `templates/views/index/main.php` | Podpięcie komponentu `director_notifications.php` (powiadomienia na dashboardzie) |
| `tests/Integration/LegalMapPermitDataTest.php` | Nowy — 15 testów SQLite in-memory dla `getMapPermitData()` |
| `tests/Integration/LegalNotificationsTest.php` | Nowy — 6 testów SQLite in-memory dla powiadomień §13 |

#### Tabele bazy danych

```sql
legal_region_config           -- parametry per region: koszt, czasy, ryzyko, required_capital
drilling_permit_applications  -- wnioski i zezwolenia: status, submitted_at, decision_due_at, refusal_cooldown_until
```

#### Statusy zezwoleń

| Status | Znaczenie | Aktywne zezwolenie? |
|--------|-----------|---------------------|
| `none` | Brak wniosku — można składać | nie |
| `pending` | Wniosek w toku — oczekiwanie na decyzję | nie |
| `delayed` | Decyzja opóźniona — nowy termin | nie |
| `no_decision` | Brak decyzji (bez cooldownu) | nie |
| `granted` | Zezwolenie aktywne | **tak** |
| `transitional` | Zezwolenie przejściowe (nadane przez migrację) | **tak** |
| `refused` | Odrzucony — cooldown przed ponownym wnioskiem | nie |
| `locked` | Wymagany kapitał > gotówka gracza (widok mapy, §7.3) | nie |

#### Kluczowe metody LegalService

```php
// Batch-odczyt statusów dla mapy: 2 SQL queries, brak N+1
getMapPermitData(int $playerId, array $regionIds, float $playerCash, ?DateTimeInterface $now): array

// Złożenie wniosku: walidacja, pobranie opłaty (transakcja), powiadomienie §13
submitApplication(int $playerId, int $regionId, ?DateTimeInterface $now): array
// → ['success' => bool, 'code' => string, 'application_id' => int, ...]

// Migracja: gracze z odwiertami bez zezwolenia → status transitional (idempotentna)
migrateTransitionalPermits(?DateTimeInterface $now): int  // → liczba nowych wpisów

// Seed konfiguracji regionów z world_regions (idempotentny)
seedRegionConfig(): int  // → liczba nowych wpisów
```

#### Powiadomienia dyrektora (§13)

`notifyDirector()` (prywatna, try/catch-guarded) wysyła powiadomienie do `director_notifications`:
- po `submitApplication()` — tytuł/treść z `legal.notif.submitted.*`, z nazwą regionu
- po `migrateTransitionalPermits()` — `legal.notif.transitional.*` per zmigrowany region
- brak tabeli `director_notifications` **nie przerywa** operacji nadrzędnej

#### Parametry domyślne per poziom ryzyka

| Poziom | Koszt (PLN) | Czas rozpatrzenia | Wymagany kapitał |
|--------|-------------|-------------------|-----------------|
| `low` | 100 000 | 30 min | 0 |
| `medium` | 250 000 | 60 min | 0 |
| `high` | 500 000 | 90 min | 5 000 000 |
| `critical` | 1 000 000 | 120 min | 25 000 000 |

Poziom ryzyka mapowany automatycznie z `political_risk` regionu przy seedzie.

#### Co nie jest w P1 (planowane P2+)

- Automatyczne przetwarzanie wniosków przez tick (losowanie wyniku, cooldowny)
- Twarda blokada zakupu odwiertu w `WorldMap` (backend — mapa pokazuje badge'e informacyjnie)
- Wygasanie aktywnych zezwoleń

---

### Aktualizacja (24.05.2026) - logistyka: jawny wybor transportu i hub-bound pipeline

- Odwiert ladowy nie startuje juz domyslnie z `rurociag`.
- Nowe odwierty ladowe dostaja stan `transport_type = 'nieustawiony'` i czekaja na decyzje gracza.
- `rurociag` mozna wybrac dopiero po aktywnym przypisaniu odwiertu do huba.
- Zakup / budowa pipeline nie jest juz auto-tworzona przez tick ani preload cache.
- `well_pipelines` dostaje `hub_id`, a aktywny pipeline jest liczony jako gotowy tylko wtedy, gdy ma przypiety aktywny hub i nie jest w statusie `building`.
- Gdy gracz nie wybierze transportu dla odwiertu ladowego, tick nie produkuje ropy "na niby" i loguje oczekiwanie na wybor.
- Dodana migracja: `migrations/etap7_transport_selection.sql`.
