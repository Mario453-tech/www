# Audyt plikowy D — limiter, stan rynku i błędy API

Data: 2026-10-30. Źródło: gałąź `feature/oilcorp-laravel-audit-baseline`. W całości odczytano cztery pliki źródłowe: `src/ApiAuthRateLimiter.php` (blob `db3e80db57e66052c2014dec1d41c548dc065e9d`), `src/ApiAuthRateLimitBootstrap.php` (`9df6d6c38ae37a6fde5335aeb5f979bdc7efcb28`), `src/Market.php` (`5fe8c2d92a956d464d087084757a7fe8260a8382`) i `src/ApiErrorHandler.php` (`38946d7802fcc7c6e3a315593e5c8feeee06b849`). Powiązanie wejścia potwierdzono w `api/v1/auth/login.php` i `api/v1/_bootstrap.php`. Testów w tej sesji nie uruchomiono.

## Graf zależności i kolumn

`POST /api/v1/auth/login` → `ApiAuthRateLimiter::normalizeIdentifier/consume` → `ensureApiAuthRateLimitSchema` → `api_auth_rate_limits.bucket_key, attempts, expires_at` → wynik `Retry-After` i JSON 429. Limiter dodatkowo czyta `players.id, email, username` przez kontroler dla wspólnego budżetu aliasów. Nie ma widoku/JS po stronie serwera; zachowanie klienta mobilnego pozostaje do sprawdzenia.

`api/v1/_bootstrap.php` → `ApiErrorHandler::install` → `lang/{pl,en,de}/api.php`, `GameLog` → odpowiedź JSON 500; oraz → `Market::ensureState` → `market_state.id, base_price, current_price, volatility, last_market_tick_at` → późniejszy odczyt `Market::getState/getCurrentPrice`. Powiązanie z tickiem i widokami wymaga dalszego audytu.

## Kartoteka

### D01 — `src/ApiAuthRateLimiter.php`

Linie 15–42 normalizują identyfikator i IP; linie 45–74 usuwają wygasłe koszyki porcjami po 64, porównując zapisane próby przy wyścigu. Linie 76–136 realizują dwa budżety: 30 prób z IP i 5 dla konto/IP w oknie 900 s, z transakcją i `FOR UPDATE` na MySQL. Konstruktor (10–13) każdorazowo wywołuje przygotowanie schematu. Decyzja migracyjna: zachować reguły i klucze koszyków, ale schemat utrzymywać migracjami. Testy na MySQL muszą weryfikować blokady i współbieżność, nie tylko SQLite.

### D02 — `src/ApiAuthRateLimitBootstrap.php`

Linie 10–42 przed logowaniem wykonują `CREATE TABLE IF NOT EXISTS`, sprawdzają metadane, ewentualnie `ALTER TABLE` i `CREATE INDEX`. Sprawdzenie `inTransaction()` (10–13) chroni transakcję wywołującego, lecz DDL nadal zachodzi w ścieżce żądania. Decyzja migracyjna: odtworzyć schemat dokładnie w wersjonowanych migracjach, po porównaniu produkcyjnego DDL i indeksu; klient runtime z uprawnieniami tylko DML po cutover.

### D03 — `src/Market.php`

Linie 21–49 wstawiają brakujący singleton `market_state.id=1` z ceną 70 przez `INSERT IGNORE` i ustawiają flagę `$done` przed próbą zapisu. Linie 64–88 czytają ten wiersz, a przy braku albo błędzie `getCurrentPrice()` zwraca 0. Decyzja migracyjna: stan początkowy zapewnić w kontrolowanym etapie migracji/restore, porównać z rzeczywistą ceną i silnikiem tick; nie resetować istniejącej ceny. Test: brak singletonu, brak uprawnień zapisu, równoległy bootstrap, zachowanie ceny po błędzie.

### D04 — `src/ApiErrorHandler.php`

Linie 7–22 wybierają tłumaczenie z trzech języków; 25–42 zapisują klasę i lokalizację wyjątku bez jego tekstu; 44–56 budują odpowiedź 500; 58–85 instalują obsługę wyjątków, błędów PHP i fatali. Decyzja migracyjna: zachować format JSON i języki przy przejściu na handler Laravel, sprawdzić brak wycieku danych do logów i odpowiedzi.

## Ustalenia

**P1 — tworzenie i modyfikacja schematu w ścieżce logowania.** `src/ApiAuthRateLimiter.php:10-13` → `src/ApiAuthRateLimitBootstrap.php:10-42`. Przy każdym nowym konstruktorze wykonywane są DDL/metadane. Nawet idempotentny `CREATE TABLE IF NOT EXISTS` nie jest odpowiednikiem migracji wersjonowanej. Koszt, blokady i skutki uprawnień wymagają testu MySQL. Wpis jest ryzykiem migracyjnym potwierdzonym kodem, nie wynikiem pomiaru wydajności.

**P2 — odczyt rynku może zwrócić 0 po nieudanej inicjalizacji.** `src/Market.php:23-48,64-86`. Ponieważ `$done` jest ustawiane przed próbą zapisu, błąd nie zostanie ponowiony w tym samym procesie; `getCurrentPrice()` ma wartość awaryjną 0, różną od 70 używanego przy inicjalizacji. Wpływ na tick i ekran gracza wymaga śledzenia wywołań oraz testu izolowanego.

## Pokrycie istniejącymi testami

Przejrzano definicje i zakres: `tests/Integration/ApiAuthSecurityTest.php` (blob `7f943d9bda5425cfde45310493362aed9d8521b4`) zawiera testy okna prób, aliasów, IP, retencji, wyścigów, bezpiecznych błędów i HTTP 400/401/429/500; test HTTP używa SQLite i lokalnego serwera PHP (linie 216–265). `tests/Integration/ApiAuthRetentionRaceTest.php` (`1d689dbebdd83132cfd50b311f81690e6c55d2cd`) testuje odnowienie koszyka podczas retencji, opcjonalnie na MySQL. `tests/MySqlIntegration/MySqlApiAuthRateLimiterTest.php` (`36eec6d9fb03aaf7e19e8d0a4c15e76dc691a6de`) testuje współbieżność, ale pomija test bez `API_TEST_MYSQL_DSN` wskazującego dedykowaną bazę testową (linie 10–19). To istniejące testy w repo, **nie wyniki uruchomienia w tej sesji**. Nie znaleziono w tej partii dowodu testu `Market::ensureState` z błędem zapisu.

## Granice

Nie badano produkcyjnego schematu, danych, uprawnień ani środowiska klienta mobilnego. Nie uruchamiano testów, CRON ani zapisu do bazy. Status czterech plików: `CRITICAL_STATIC_REVIEW`; pełne domknięcie zależności rynku, tłumaczeń, logowania i rzeczywistego klienta jest otwarte.