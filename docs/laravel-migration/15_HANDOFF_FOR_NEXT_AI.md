# OilCorp/OilEmpire — przekazanie audytu migracji do Laravel

**Stan na 2026-10-30.** Repozytorium: `Mario453-tech/www`. Draft PR: [#68](https://github.com/Mario453-tech/www/pull/68), gałąź `feature/oilcorp-laravel-audit-baseline` do `main`. Ten dokument ma umożliwić innemu AI kontynuowanie pracy bez zgadywania, co zrobiono. Jest to audyt i dokumentacja; **nie scalaj PR, nie wdrażaj i nie zmieniaj danych produkcyjnych**. Przed każdą zmianą sprawdź aktualny head gałęzi, SHA plików i stan PR, bo poniższe wartości są migawką.

## Cel i nienaruszalne warunki

Przygotować dowodowy, plikowy audyt autorskiego PHP/JS/CSS, widoków, konfiguracji, SQL, API, CRON, paneli admina/gracza, tłumaczeń, assetów i integracji mobilnej przed przejściem PHP 8.2/MySQL do modularnego monolitu Laravel. Zachować `players.id` i wszystkie `player_id`, hashe, konta adminów i 2FA, aktywa, salda, historię gry, kontrakty API mobile oraz aktualną grafikę/UI. Vendor i backupy klasyfikować po pochodzeniu/ryzyku, bez przepisywania bibliotek linia po linii. Każdy problem: P0/P1/P2/P3, plik, linia, blob SHA, warunki wystąpienia, skutek, test i ograniczenia dowodu.

Nie dotykać produkcyjnej DB, nie uruchamiać `full_wipe`, destrukcyjnych migracji ani produkcyjnego CRON. Testy tylko na izolowanym środowisku i sztucznej lub zanonimizowanej bazie. Nie kopiować danych osobowych ani wartości sekretów do raportu, logu narzędzia czy PR.

## Stan dokumentacji i liczby

- `00_SAFETY_AND_ARCHITECTURE.md`: zasady zachowania danych, modularny monolit, etapy E0–E5, plan wyglądu i cutover.
- `01_ADMIN_COVERAGE.md`: macierz 36 sekcji menu admina.
- `02_ALL_TRACKED_FILES.tsv`: 5133 śledzone pliki, ścieżka/rozmiar/blob SHA/kategoria/status. **102/5133** mają co najmniej skan statyczny: 33 `CRITICAL_STATIC_REVIEW`, 34 `ADMIN_CONTROLLER_STATIC_SCAN`, 35 `ADMIN_VIEW_STATIC_SCAN`. Pozostałe 5031 nie mają indywidualnego przeglądu.
- `03_CRITICAL_FILES_REVIEW_A.md`: pierwsza partia 23 krytycznych plików; jej status „potwierdzone w kodzie”, nie runtime.
- `04_ADMIN_WIRING_PART_*.tsv` i `05_ADMIN_VIEWS_PART_*.tsv`: wstępne wyciągi kontrolerów i widoków. W 35 widokach wykryto 173 formularze, 218 przycisków i 84 inline event attributes. To liczniki tekstowe, nie test działania.
- `06_FILE_BY_FILE_AUDIT_PROTOCOL.md`: wymagane kryteria pełnej kontroli i statusy.
- `07_EVIDENCE_ADDENDUM_B.md` do `10_MARKET_TICK_FILE_REVIEW_E.md`: kolejne przeglądy krytycznych plików i ustalenia.
- `11_DEEP_REVIEW_MARKET_ADMIN_F.md`, `13_DEEP_REVIEW_TICK_MODULES_G.md`, `14_DEEP_REVIEW_FORCE_TICK_2FA_H.md`: szczegółowa ponowna kontrola wskazanych par panelowych.
- `12_102_DEEP_REVIEW_TRACKER.tsv`: **18/102** plików ma udokumentowany odczyt całego źródła z otwartymi zależnościami; **84/102** czeka na taki przegląd. `SOURCE_READ_DEPENDENCIES_OPEN` nie znaczy `FULL_SOURCE_REVIEW`, `RUNTIME_TEST_PASS` ani „działa”. Tracker jest osobny od ogólnego rejestru.

Źródłowy spis 5133 plików powstał dla `main` @ `7521a97aab762c1ff4dd27b249b78778922cb5ed`. Przed dalszym audytem porównaj aktualny `main` i PR, bo kod mógł się zmienić. Nie przypisuj starego SHA do nowej wersji.

## Potwierdzone ustalenia i ryzyka z dotychczasowych raportów

### P0 — publiczne artefakty i logi

`.github/workflows/sync-logs.yml`, blob `9e471cac674d7825830f5d303f468c7e3f6f99a6`, linie 18–44: workflow co godzinę pobiera log produkcyjny do `logs/game_debug.txt`, commit-uje i wypycha plik; przy HTTP innym niż 200 wypisuje odpowiedź serwera do logu CI (27–30). W repo wskazano historyczny eksport SQL około 91 MB i pliki mogące zawierać tokeny. **Nie sprawdzono prywatnej zawartości ani aktualności sekretów, nie kopiowano wartości.** Pilne działanie w osobnym bezpiecznym procesie: wstrzymać publiczną publikację, prywatnie zbadać historię Git i Actions, ustalić dane osobowe/sekrety, rotować potwierdzone tokeny, dopiero potem zaplanować oczyszczenie historii. `.gitignore` nie usuwa już śledzonych danych.

### P0/P1 — destrukcyjne GM i konta

`admin/gm_tools.php`, blob `4606dadf0a499b88405d232f9614a1e9e48c2d54`: `full_wipe` (361–444) czyści wszystkie tabele poza allowlistą; nowe tabele Laravel mogą zostać objęte resetem, a MySQL `TRUNCATE` nie jest atomowym rollbackiem. Nie wykonywać tej akcji.

`admin/gm_tools.php:223-226` zapisuje klonowi `players.password`, podczas gdy `src/Auth.php`, blob `bf0a4ca60bab67e626136536890bf7f45cd198db`, loguje przez `password_hash` (25–40), a rejestracja zapisuje `password_hash` (359–375). P1: klon może się nie zapisać albo nie zalogować; szczegół zależy od schematu. `admin/gm_tools.php:291-326` ignoruje błędy usuwania z tabel zależnych i może pokazać nieadekwatny rezultat. Test tylko na sztucznej DB.

### P1 — tokeny i konto

`src/ApiAuth.php`, blob `993cfe5a94ff90aea5fbfd226502724fbace38c0`, linie 45/69–91: token mobilny przechowywany surowo w `api_tokens.token`, zwykle 90 dni; potrzebny etap kompatybilności i przejście na hash. `src/Auth.php:609-628,860-888` zmienia/resetuje hasło bez odwołania `api_tokens`; `src/ApiAuth.php:98-103` ma `revokeAllForPlayer`, ale te ścieżki go nie używają. Cookie remember-me ma własny token do 30 dni (`src/Auth.php:763-794`). Skutek po zmianie hasła jest wnioskiem statycznym do testu w dwóch sesjach. `api/v1/_bootstrap.php` i `src/ApiAuth.php` tworzą tabelę tokenów podczas żądania; `src/ApiAuthRateLimiter.php` → `src/ApiAuthRateLimitBootstrap.php` wykonuje DDL/metadane podczas logowania. Przenieść schemat do wersjonowanych migracji po porównaniu DDL.

### P1/P2 — rynek i tick

`src/MarketTick.php`, blob `06a806029f9659f6142781dedd649385e3d50bc5`, `getMarketSnapshot():259-285` wywołuje niezdefiniowane `fetchPlayerSupply()` (istnieje tylko `fetchPlayerSupplyFlow():159-177`), po znalezieniu `market_state.id=1` kończy w `catch` i zwraca pustą tablicę. `MarketTick::updatePrices():144-154` i `src/Tick/MarketSection.php:45-54` przechwytują błędy, `MarketModule.php:23-35` daje fallback, a `TickEngine.php:162-168` oznacza moduł jako udany, jeśli nie ma wyjątku. Możliwy fałszywy sukces i obliczenie następnych modułów na cenie awaryjnej. `MarketTick.php:159-176` zamienia błąd zapytania podaży na 0 (P2). Wszystko wymaga testu izolowanego MySQL.

`admin/market.php`, blob `513a42fea1211012143cf1fb4c93247c45d18c15`, linie 57–63 wywołuje ręcznie `MarketTick::updatePrices` bez blokady `TickCoordinator::GET_LOCK` (P1). Linie 40–50 przy nieistniejącym `trend_id` najpierw wyłączają wszystkie trendy i pokazują sukces (P1). Walidacja serwerowa ceny dopuszcza >0, mimo formularza 30–150 (P2); zmienność 0.1–5 jest rzutowana do int przed `rand` (`MarketTick.php:57,98`, P2); brak sprawdzenia `rowCount()` singletonu może pokazać sukces bez zapisu (P2). Widok: `templates/views/admin/market/main.php`, blob `a69bd4d99e6fea6d515a8d7c7d87f20497da9966`.

`src/Market.php` (blob `5fe8c2d92a956d464d087084757a7fe8260a8382`) ustawia flagę inicjalizacji przed `INSERT IGNORE` `market_state.id=1`; po błędzie `getCurrentPrice` może zwrócić 0 (P2). `src/Tick/TickModuleConfigRepository.php` i `TickModuleAdminService` dopisują brakujące konfiguracje przy GET panelu ticków, co jest ryzykiem migracyjnym, jeśli w Laravel GET nie będzie miał zapisu. `admin/tick_modules.php` → serwis → `tick_module_config.enabled, interval_ticks, max_items_per_run` → scheduler → `TickContext` jest potwierdzone statycznie. Krytyczne moduły są wymuszone, opcjonalne używają przełącznika i interwału; każdy moduł wymaga kontroli stosowania limitu. Raport G ma dokładne linie.

### Admin: wymuszony tick i 2FA

`admin/force_tick.php`, blob `bcda59db6dbd4b78e89c47b63bb537ab1106a6ae`: POST wymaga loginu i CSRF, wywołuje koordynator z blokadą. Brak osobnej kontroli uprawnień do operacji globalnej (P1 do decyzji polityki), cooldown jest sesyjny, przekierowanie bierze `HTTP_REFERER`, komunikaty mogą zawierać tekst wyjątku (P2). `admin/2fa.php`, blob `a1dfcb917cbedb21c55a76f4829116cba090a702`: kod TOTP, setup, IP/session rate limit, zaufane urządzenie. Blokada ponownego użycia licznika TOTP jest tylko w `admin_pending` sesji (83–88); test dwóch sesji potrzebny. `AdminAuth::tryTrustedDevice` może pominąć hasło i 2FA na ważnym cookie; to polityka, której nie wolno zmieniać przypadkiem przy migracji. Raport H podaje warunki, nie uznaje tego automatycznie za podatność.

## Testy: stan faktyczny

W tej serii **nie uruchomiono nowych testów jednostkowych, integracyjnych, E2E ani wizualnych**. Brak izolowanej anonimowej kopii MySQL i działającego środowiska dla tej gałęzi; próba klonowania repo przez HTTPS w środowisku pracy zakończyła się błędem dostępu sieciowego. Odczyty i zapisy dokumentacji wykonano przez połączony GitHub. Nie uruchamiano CRON, panelu ani migracji. Istniejące testy `tests/Integration/ApiAuthSecurityTest.php`, `ApiAuthRetentionRaceTest.php`, `tests/MySqlIntegration/MySqlApiAuthRateLimiterTest.php` obejmują m.in. limity i wyścigi; ostatni wymaga `API_TEST_MYSQL_DSN` do dedykowanej bazy testowej i może być pominięty. Istnienie testu nie oznacza jego przejścia ani pokrycia konkretnych ustaleń. Potrzebny kontrakt API mobile, testy idempotencji/wyścigów ticków oraz porównania widoków 1440/390/320 px.

## Dokładna procedura kontynuacji

1. Sprawdź head PR, aktualne SHA i różnice do `main`; nie wpisuj ustaleń dla starego blobu bez weryfikacji. Pracuj wyłącznie na gałęzi audytowej i utrzymuj PR jako draft.
2. Weź kolejną pozycję `PENDING_DEEP_REVIEW` z `12_102_DEEP_REVIEW_TRACKER.tsv`. Czytaj **cały plik**. Dla dużych plików segmentami, ale nie awansuj statusu po fragmencie.
3. Zapisz osobną kartę pliku: rola/wejście, całość akcji, zależności, konkretne tabele/kolumny, uprawnienia/CSRF, transakcje/locki, widok/JS/CSS, testy z rozróżnieniem istnieje vs uruchomiono, ryzyka P0–P3 z linią/blob SHA, decyzja migracyjna i otwarte dowody.
4. Powiąż po obu stronach kontroler → serwis → tabela/kolumna → widok → JS → test. Dla ustawień admina potwierdź zapis, odczyt silnika i wynik gracza; nie wystarczy komunikat „zapisano”.
5. Po pełnym odczycie aktualizuj tracker na `SOURCE_READ_DEPENDENCIES_OPEN` z nazwą raportu. `02_ALL_TRACKED_FILES.tsv` podnoś do `FULL_SOURCE_REVIEW` dopiero po domknięciu zależności zgodnie z protokołem. Nie zwiększaj licznika 102 za ponowną kontrolę tych samych plików.
6. Przygotuj izolowaną bazę sztuczną lub zanonimizowany restore i środowisko PHP 8.2/MySQL bez danych produkcyjnych; dopiero wtedy uruchamiaj ukierunkowane testy, E2E i porównania wizualne. Każdy wynik wpisz z datą/komendą/środowiskiem i liczbą zaliczonych/pominiętych.
7. Przy migracji Laravel zachowaj stare nazwy tabel i kluczy przez adaptery. Nowe tabele tylko po uzasadnieniu. Dla cutover tylko jeden silnik zapisujący, zamrożenie starych ticków, snapshot i plan odtworzenia nowych zapisów; szczegóły w `00_SAFETY_AND_ARCHITECTURE.md`.

**Najbliższe sensowne pliki:** `src/AdminAuth.php` i `src/Auth.php` (duże, dotąd nie przeczytane w całości); `src/Tick/TickCoordinator.php`, `src/Tick/TickEngine.php`, `src/Tick/TickModuleConfigRepository.php`, `src/TickModuleAdminService.php`; `admin/gm_tools.php` i widok; następnie pozostałe kontrolery/widoki menu admina. Równolegle w prywatnym procesie sprawdzić ekspozycję SQL/logów i rotację potwierdzonych sekretów. Nie zamieszczać ich wartości w Git.

## Uwaga o stanie raportów

Wcześniejsze raporty `07–10` zawierają ważne znaleziska, ale ich nazwa lub status `CRITICAL_STATIC_REVIEW` nie gwarantuje pełnego audytu wszystkich zależności. Raporty `11–14` są szczegółowym ponownym odczytem wybranych plików spośród 102. Obecna granica to **18 odczytanych źródeł z otwartymi zależnościami i 84 oczekujące**, zero potwierdzonych testów runtime w tej serii. Każda przyszła odpowiedź powinna te trzy liczby i ograniczenie jasno podawać.