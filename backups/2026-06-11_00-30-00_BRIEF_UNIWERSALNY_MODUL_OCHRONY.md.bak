# BRIEF — Uniwersalny moduł ochrony z konfigurowalnymi opcjami i efektami

> Wersja zweryfikowana względem kodu (2026-06-11). Sekcja „0. Zmiany względem wersji
> pierwotnej" wyjaśnia, co i dlaczego poprawiono po analizie istniejącego silnika
> transportu drogowego (`RoadTransportService`), ticka i `FinancialTransactionService`.

---

## 0. Zmiany względem wersji pierwotnej — wynik weryfikacji z kodem

1. **Ochrona kupowana per ODWIERT, nie per kurs.** Kursy drogowe (`well_road_trips`)
   są tworzone **automatycznie przez tick** (model bufora: ropa zbiera się do
   `min_load_bbl`, potem dispatch w `WellProductionHandler`). Gracz nie tworzy kursu
   ręcznie, więc „wykup ochrony przy tworzeniu kursu" jest niewykonalny. Ochrona
   jest wykupywana na odwiert (`target_type = road_transport`, `target_id = well_id`)
   na czas `duration_minutes`, a kursy rozliczane w czasie jej trwania dostają efekty.
2. **Matematyka efektów dopasowana do silnika incydentów.** `RoadTransportService`
   ma JEDNĄ wspólną szansę incydentu na kurs i dopiero PO trafieniu losuje typ
   z wag (`INCIDENT_WEIGHTS`: theft 3, raid 2, accident 4, sabotage 1, route_block 2).
   Mnożnik per typ stosuje się więc na wagach (sekcja 8, `applyEffects`).
3. **Klucze efektów dopasowane do typów z kodu**: `raid_risk_mult` zamiast
   `attack_risk_mult` (w kodzie typ nazywa się `raid`). Typy `accident`
   i `route_block` celowo NIE mają mnożników — zgodnie z zasadą „ochrona nie działa
   na awarię/korki".
4. **Płatność przez FTS.** Każdy ruch pieniędzy idzie przez
   `FinancialTransactionService`. Nowy typ `TYPE_PROTECTION = 'protection'`
   → `POOL_CASH` w `WalletConfig::TYPE_TO_POOL`. P1 = tylko gotówka; płatność
   z konta bankowego (`bank`/`both`) to przyszłość — kolumna `cost_currency`
   zostaje, ale na starcie honorujemy tylko `cash`.
5. **Usunięto redundancję**: kolumny `requires_cash`/`requires_bank` skreślone —
   duplikowały `cost_currency`. Skreślono też `min_legal_level` (w grze nie ma
   numerycznego poziomu działu prawnego; dodamy kolumnę, gdy powstanie taki system).
6. **Doprecyzowano brakujące reguły**: stackowanie (max 1 aktywna ochrona na
   `player + target_type + target_id + context`), wygasanie (leniwe, po `ends_at`),
   moment naliczenia efektu (przy ROZLICZENIU kursu, nie przy wyjeździe).

---

## Cel

Stworzyć uniwersalny moduł ochrony, który pozwala łatwo dodawać nowe opcje ochrony
i nowe efekty bez przepisywania logiki w kodzie.

System ma działać dla różnych elementów gry:

- transport drogowy,
- odwiert,
- hub,
- rurociąg,
- magazyn,
- port,
- terminal.

Na start wdrażamy tylko transport drogowy, ale architektura ma być przygotowana
pod kolejne moduły.

---

## 1. Główna zasada

Nie kodować opcji ochrony na sztywno w PHP.

Opcje ochrony mają być definiowane w bazie danych i edytowane z panelu admina.

Admin ma móc dodać nową ochronę bez zmiany kodu, np.:

- Eskorta podstawowa,
- Konwój uzbrojony,
- Patrol dronami,
- Ochrona odwiertu,
- Monitoring huba,
- Patrol rurociągu.

---

## 2. Struktura systemu

- `ProtectionService` — główny silnik ochrony (`src/ProtectionService.php`
  + ewentualnie `src/Protection/ProtectionConfig.php` na wzór `src/Bribery/`),
- `protection_options` — definicje opcji ochrony,
- `protection_effects` — efekty przypisane do opcji ochrony,
- `active_protections` — aktywne ochrony wykupione przez graczy,
- `protection_logs` — historia ochrony.

Schemat tworzony idempotentnie w `ensureSchema()` (wzór: `BriberyConfig`),
z pominięciem DDL w otwartej transakcji. Serwis tworzony **przed**
`beginTransaction()` wołającego kodu.

---

## 3. ProtectionService

Serwis odpowiada za:

- pobranie dostępnych opcji ochrony,
- wyliczenie kosztu,
- aktywację ochrony (płatność przez FTS),
- sprawdzenie aktywnej ochrony dla celu,
- zastosowanie efektów ochrony na ryzykach,
- zapis historii (`protection_logs`),
- wysłanie powiadomień (`director_notifications`, w pełni guarded — wzór
  `BriberyService::notifyCaught`).

Żaden moduł gry nie liczy efektów ochrony samodzielnie — tylko `ProtectionService`.
GameLog w `__construct`, każdej metodzie publicznej i każdym `catch` (zasada projektu).

---

## 4. Tabela protection_options

```sql
id                       INT AUTO_INCREMENT PRIMARY KEY
code                     VARCHAR(64) NOT NULL UNIQUE      -- np. basic_escort, armed_convoy, drone_patrol
name                     VARCHAR(128) NOT NULL            -- nazwa widoczna dla gracza
description              VARCHAR(512) NOT NULL DEFAULT '' -- prosty opis dla gracza
target_type              VARCHAR(32) NOT NULL             -- road_transport / well / hub / pipeline / warehouse / port / terminal
context                  VARCHAR(64) NOT NULL             -- road_transport_guard / well_security / hub_security / ...
is_active                TINYINT(1) NOT NULL DEFAULT 1
cost_type                ENUM('fixed','percent_reference','per_hour','per_bbl') NOT NULL DEFAULT 'fixed'
cost_value               DECIMAL(12,2) NOT NULL DEFAULT 0.00
cost_currency            ENUM('cash','bank','both') NOT NULL DEFAULT 'cash'  -- P1: honorowane tylko 'cash'
duration_minutes         INT UNSIGNED NOT NULL DEFAULT 60
min_company_credibility  INT UNSIGNED NOT NULL DEFAULT 0  -- 0 = brak wymogu
sort_order               INT NOT NULL DEFAULT 0
created_at / updated_at  DATETIME
```

Znaczenie kosztów:

- `fixed` — `cost_value` to kwota,
- `percent_reference` — `cost_value`% z `referenceValue` przekazanego przez moduł
  (np. wartość ładunku),
- `per_hour` — `cost_value` × (`duration_minutes` / 60),
- `per_bbl` — `cost_value` × `referenceValue` (moduł podaje wolumen w bbl).

`min_company_credibility` porównywane ze `CompanyCredibilityService::getScore()`.

---

## 5. Tabela protection_effects

```sql
id                    INT AUTO_INCREMENT PRIMARY KEY
protection_option_id  INT NOT NULL                 -- FK do protection_options
effect_key            VARCHAR(64) NOT NULL
effect_type           ENUM('mult','delta') NOT NULL DEFAULT 'mult'
effect_value          DECIMAL(8,4) NOT NULL
created_at / updated_at DATETIME
UNIQUE KEY (protection_option_id, effect_key)
```

### Klucze efektów P1 (transport drogowy) — zgodne z typami incydentów w kodzie

```text
theft_risk_mult       -- kradzież  (typ 'theft' w INCIDENT_WEIGHTS)
raid_risk_mult        -- napad     (typ 'raid')
sabotage_risk_mult    -- sabotaż   (typ 'sabotage')
```

Typy `accident` i `route_block` świadomie BEZ mnożników — ochrona nie działa na
awarię pojazdu, pogodę, korki (sekcja 12).

### Klucze zarezerwowane na przyszłość (NIE wdrażać teraz, sekcja 15)

```text
attack_risk_mult, loss_mult, delay_risk_mult, incident_risk_mult, damage_mult,
detection_risk_mult, equipment_theft_risk_mult,
black_market_score_delta, company_credibility_delta
```

Nieznany `effect_key` jest ignorowany przez `applyEffects()` (silnik nie wybucha
po dodaniu nowego klucza w adminie, zanim moduł go obsłuży).

### Przykłady seedów P1

**Eskorta podstawowa** — `theft_risk_mult 0.80`, `raid_risk_mult 0.85`
**Konwój uzbrojony** — `theft_risk_mult 0.55`, `raid_risk_mult 0.60`, `sabotage_risk_mult 0.85`
**Patrol dronami** — `sabotage_risk_mult 0.70`, `theft_risk_mult 0.90`

---

## 6. Tabela active_protections

```sql
id                    INT AUTO_INCREMENT PRIMARY KEY
player_id             INT NOT NULL
protection_option_id  INT NOT NULL
target_type           VARCHAR(32) NOT NULL
target_id             INT NOT NULL                  -- P1: well_id (ochrona tras danego odwiertu)
context               VARCHAR(64) NOT NULL
paid_from             ENUM('cash','bank') NOT NULL DEFAULT 'cash'
cost                  DECIMAL(12,2) NOT NULL
starts_at             DATETIME NOT NULL
ends_at               DATETIME NOT NULL
status                ENUM('active','expired','cancelled','failed') NOT NULL DEFAULT 'active'
meta_json             TEXT NULL
created_at / updated_at DATETIME
KEY (player_id, target_type, target_id, context, status)
```

### Reguły (doprecyzowane)

- **Stackowanie:** maksymalnie JEDNA aktywna ochrona na
  `player + target_type + target_id + context`. Próba wykupu drugiej, gdy aktywna
  jeszcze trwa → błąd `already_active` z datą `ends_at`.
- **Wygasanie leniwe:** brak osobnego crona. `getActiveEffects()` filtruje po
  `status='active' AND ends_at > NOW()`; przy okazji odczytu (i w widoku admina)
  rekordy z `ends_at <= NOW()` są przełączane na `expired` jednym UPDATE.
- **Moment naliczenia:** efekty sprawdzane przy ROZLICZENIU kursu
  (`processCompletedTrips`, gdy `eta_at <= NOW()`), nie przy wyjeździe. Prościej
  (jeden punkt integracji) i korzystnie dla gracza — ochrona dokupiona w trakcie
  kursu jeszcze go obejmie.

---

## 7. Tabela protection_logs

```sql
id                    INT AUTO_INCREMENT PRIMARY KEY
player_id             INT NOT NULL
protection_option_id  INT NOT NULL
target_type           VARCHAR(32) NOT NULL
target_id             INT NOT NULL
context               VARCHAR(64) NOT NULL
event_key             VARCHAR(64) NOT NULL
amount                DECIMAL(12,2) NOT NULL DEFAULT 0.00
message               VARCHAR(512) NOT NULL DEFAULT ''
meta_json             TEXT NULL
created_at            DATETIME
```

`event_key`: `protection_activated`, `protection_expired`,
`protection_applied_to_incident`, `protection_failed`, `protection_cancelled`.

`protection_applied_to_incident` zapisywany, gdy kurs rozliczono pod aktywną
ochroną i doszło (lub dzięki redukcji nie doszło) do incydentu chronionego typu —
to daje adminowi odpowiedź „czy ochrona zadziałała".

---

## 8. Metody ProtectionService

```php
getAvailableOptions(int $playerId, string $targetType, string $context): array
```
Zwraca opcje: `is_active = 1`, pasujący `target_type` + `context`, spełnione
`min_company_credibility`. Każda opcja z wyliczonym kosztem (jeśli moduł poda
`referenceValue`) i flagą `affordable` (stać gracza / nie stać).

```php
quote(int $playerId, string $optionCode, float $referenceValue): array
```
Koszt + lista efektów (do UI) + `affordable`, bez ruchu środków.

```php
activate(int $playerId, string $optionCode, string $targetType, int $targetId,
         float $referenceValue, array $meta = []): array
```
1. waliduje opcję (aktywna, target/context, credibility, brak aktywnej ochrony
   na tym celu),
2. wylicza koszt wg `cost_type`,
3. `beginTransaction` → `FinancialTransactionService::debit($playerId, $cost,
   FinancialTransactionService::TYPE_PROTECTION, opis)` — przy `success=false`
   rollback i `outcome='no_funds'`,
4. INSERT `active_protections` (`starts_at = NOW()`,
   `ends_at = NOW() + duration_minutes`),
5. INSERT `protection_logs` (`protection_activated`),
6. commit, powiadomienie dyrektora (guarded).

Zwraca `['success'=>bool, 'outcome'=>'success|no_funds|already_active|requirements_not_met|disabled|error', 'cost'=>float, 'ends_at'=>?, 'message'=>string]`.

```php
getActiveEffects(int $playerId, string $targetType, int $targetId, string $context): array
```
Zwraca scaloną mapę `effect_key => effect_value` z aktywnej ochrony celu
(pusta tablica = brak ochrony). Po drodze leniwe wygaszanie (sekcja 6).

```php
applyEffects(array $baseRisks, array $effects): array
```
Uniwersalne nałożenie: dla `effect_type='mult'` mnoży, dla `'delta'` dodaje;
nieznane klucze ignoruje.

### Integracja z silnikiem jednej szansy + wag (transport drogowy)

`RoadTransportService` losuje najpierw CZY incydent (wspólna szansa), potem JAKI
(wagi). Mnożniki per typ nakładamy więc na wagi i korygujemy łączną szansę, żeby
nie zmieniać prawdopodobieństw typów niechronionych:

```text
w'_i      = w_i × mult_i          (mult_i = 1.0 dla typów bez efektu)
chance'   = chance × (Σ w'_i / Σ w_i)
losowanie typu                  = z wag w'_i
```

Przykład: konwój uzbrojony (theft 0.55, raid 0.60, sabotage 0.85) przy wagach
(3,2,4,1,2): Σw=12 → Σw'=1.65+1.2+4+0.85+2=9.7 → łączna szansa × 0.808,
a `accident`/`route_block` zachowują dokładnie swoje bazowe prawdopodobieństwa.

Punkt wpięcia: `RoadTransportService::applyTripIncidents()` dostaje (opcjonalny)
parametr z mapą mnożników; `processCompletedTrips()` pobiera ją raz per odwiert
z `ProtectionService::getActiveEffects()`.

---

## 9. Panel admina

**Admin → Ochrona** (`admin/protection.php` + `templates/views/admin/protection/main.php`
+ `assets/js/admin_protection.js` + `lang/pl/admin/protection.php`).

Zakładki:

### Opcje ochrony
Dodawanie/edycja: code, nazwa, opis, target_type, context, koszt (typ + wartość),
czas działania, źródło płatności, min. wiarygodność, włącz/wyłącz, kolejność.

### Efekty ochrony
Dodawanie/edycja/usuwanie par `effect_key` + `effect_value` per opcja.
Select z listą znanych kluczy P1 + pole wolne (przyszłe klucze).

### Aktywne ochrony
Gracz, opcja, cel, start, koniec, status. Przycisk anulowania (status `cancelled`,
bez zwrotu środków — albo ze zwrotem proporcjonalnym, do decyzji przy wdrożeniu).

### Historia ochrony
`protection_logs`: kto, co, ile zapłacił, na co działało, czy zadziałała przy
incydencie (`protection_applied_to_incident`).

Zasady projektu: CSS Grid (zero tabel HTML), zero inline JS/style, modale tylko
z `modal.js`, CSRF przez `CSRF::field()` / `CSRF::validateToken()`.

---

## 10. UI gracza

P1: panel logistyki / widok odwiertu z transportem drogowym (`ciezarowki`).
Przy odwiercie przycisk **Dodaj ochronę** → modal **Wybierz ochronę** z listą opcji:

- nazwa, opis,
- koszt (z `quote()`),
- czas działania,
- źródło płatności,
- prosty opis efektu (sekcja 11).

Przykład:

**Konwój uzbrojony**
Zmniejsza ryzyko kradzieży i napadu podczas kursów.
Koszt: 500 000 PLN gotówką
Czas działania: 60 minut

Przyciski: **Anuluj** / **Wykup ochronę**. Gdy ochrona aktywna — zamiast przycisku
plakietka z nazwą ochrony i czasem do końca (`ends_at`).

Pliki: `assets/js/protection.js`, `assets/css/protection.css`, `lang/pl/protection.php`,
endpoint POST (CSRF + RateLimiter) np. `public/protection.php` lub akcja w logistyce.

---

## 11. Prosty opis efektów dla gracza

Nie pokazywać mnożników typu `0.55`. Tekst generowany z progów:

```text
mult <= 0.60  → „Znacznie zmniejsza ryzyko …"
mult <= 0.85  → „Zmniejsza ryzyko …"
mult <  1.00  → „Lekko zmniejsza ryzyko …"
```

Plus stała linia: „Nie chroni przed awarią pojazdu, pogodą ani korkami."

---

## 12. Podpięcie P1 — transport drogowy

Ochrona wpływa na: **kradzież (theft), napad (raid), sabotaż (sabotage)**.

Nie wpływa na: awarię pojazdu (accident), blokady tras / korki (route_block),
pogodę, zwykłe opóźnienie, błędy techniczne.

Cel ochrony: `target_type = road_transport`, `target_id = well_id`,
`context = road_transport_guard`. Efekty stosowane przy rozliczaniu kursów
danego odwiertu w `processCompletedTrips()` (sekcja 8).

Płatność: `FinancialTransactionService::TYPE_PROTECTION = 'protection'`
→ `POOL_CASH` (wpis w `WalletConfig::TYPE_TO_POOL`; zastępuje zarezerwowany
komentarz `transport_guard` — jeden uniwersalny typ dla całego modułu).

---

## 13. Podpięcia późniejsze

### Ochrona odwiertu
Działa na: sabotaż, kradzież sprzętu, atak na ekipę, celowe uszkodzenie.
Nie działa na: naturalne zużycie, degradację, brak technika, awarie technologiczne.

### Ochrona huba
Działa na: kradzież z bufora, sabotaż, atak na infrastrukturę, celowe zatrzymanie
przepływu. Nie działa na: przeciążenie, degradację, zły stan techniczny.
(Punkt wpięcia: `HubIncidentService`.)

### Ochrona rurociągu
Działa na: sabotaż, kradzież ropy, celowe uszkodzenie.
Nie działa na: naturalne zużycie, brak konserwacji, awarie techniczne.

---

## 14. Balans

Ochrona nie może być gwarancją bezpieczeństwa — tylko zmniejsza ryzyko
(mnożniki > 0, nigdy 0).

- tania ochrona = mały efekt,
- średnia ochrona = dobry kompromis,
- droga ochrona = mocny efekt, opłacalna tylko przy dużych wolumenach.

Konwój uzbrojony nakłada się na ciężarówkę `armored` (`incident_risk_mult` 0.3
z `TRUCK_DEFAULTS`) — to świadome: gracz płacący za oba ma kursy niemal bezpieczne,
ale płaci podwójnie. Seedy cen ustawić tak, by ochrona była droższa niż oczekiwana
strata przy małych wolumenach.

---

## 15. Czego nie wdrażać teraz

- ochrony wszystkich aktywów (odwierty/huby/rurociągi/magazyny — tylko architektura),
- ochrony portów i terminali,
- prywatnej armii,
- kontraktów ochroniarskich na wiele dni,
- wpływu na czarny rynek (`black_market_score_delta`),
- wpływu na wiarygodność firmy (`company_credibility_delta`),
- płatności z konta bankowego (`cost_currency` = `bank`/`both`),
- śledztw po incydencie,
- zwrotu środków przy anulowaniu (chyba że decyzja przy wdrożeniu).

Na start: **silnik + admin + transport drogowy.**

---

## 16. Najkrótsza wersja dla AI

Stworzyć konfigurowalny moduł ochrony. Opcje i efekty definiowane w bazie
i panelu admina, nie w kodzie. `ProtectionService` pobiera dostępne opcje, wycenia
(`quote`), aktywuje (płatność przez `FinancialTransactionService::TYPE_PROTECTION`,
zapis `active_protections` + `protection_logs`, powiadomienie) i zwraca efekty dla
celu (`getActiveEffects` → `applyEffects`). P1: ochrona kupowana per odwiert
(`road_transport` / `road_transport_guard`), efekty `theft/raid/sabotage_risk_mult`
nakładane na wagi incydentów w `RoadTransportService::applyTripIncidents()`
z korektą łącznej szansy. Max 1 aktywna ochrona per cel+kontekst, wygasanie leniwe
po `ends_at`. System gotowy pod odwierty, huby i rurociągi.
