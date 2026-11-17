# OilCorp 2.0 — raport plikowy, partia A (kod i integracje krytyczne)

Wersja źródłowa: `7521a97aab762c1ff4dd27b249b78778922cb5ed`. Analiza statyczna kodu i zależności. Żaden wymieniony mechanizm nie został tutaj przetestowany na uruchomionej aplikacji ani na kopii produkcyjnej bazy. Używaj statusu **POTWIERDZONE W KODZIE**, a nie **DZIAŁA**.

## Pilne ryzyka architektoniczne i bezpieczeństwa

**P0 / ekspozycja artefaktów:** repozytorium jest publiczne i zawiera historyczne eksporty SQL, logi oraz nazwany plik konfiguracji tokenu. `sync-logs.yml` cyklicznie pobiera logi z produkcji i commit-uje je do repo. Nie odczytywano, nie kopiowano i nie publikowano żadnych wartości sekretów. Sprawdzić prywatnie zawartość oraz ekspozycję danych osobowych, rotować ujawnione sekrety, ograniczyć publikację logów i dopiero potem porządkować historię Git. `.gitignore` nie usuwa plików już śledzonych.

**P0 / destrukcyjne GM:** pełny reset w `admin/gm_tools.php` buduje listę tabel do wyczyszczenia jako wszystkie tabele minus lista zachowywanych. Przy dodaniu np. technicznej tabeli Laravel może ona zostać wyczyszczona automatycznie. `TRUNCATE` w MySQL jest operacją DDL z niejawnym commit; resetu nie można traktować jako atomowej transakcji. Przed podłączeniem Laravel zastąpić ten wzorzec ściśle kontrolowaną, jawną listą celów dla środowiska testowego lub usunąć reset z produkcji. Nie uruchamiać `full_wipe` na bazie produkcyjnej.

**P1 / DDL w runtime:** klasy finansowe i uwierzytelnianie wykonują `CREATE TABLE` / `ALTER TABLE` podczas żądań, co grozi rozjazdami schematu oraz niejawnymi commitami. W Laravel przejść na jawne migracje, przy zachowaniu kluczy i istniejących danych.

**P1 / API mobilne:** `ApiAuth` przechowuje surową wartość tokenu bearer w DB (w przeciwieństwie do bezpieczniejszej reprezentacji hash), token jest ważny maksymalnie 90 dni w standardowym wydaniu. Migracja wymaga kompatybilnego okresu przejściowego i zabezpieczenia przechowywania tokenów.

## Kartoteka krytycznych plików

### A01 — [src/Auth.php](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/src/Auth.php#L15-L65)
**Rola:** logowanie gracza według e-maila lub nazwy, `password_verify`, status weryfikacji e-maila, sesja; rejestracja tworzy rekord `players`, `storage` i portfel w transakcji (zob. `src/Auth.php` linie 290–401). **Zależności:** `Database`, `WalletService`, `WalletConfig`, `GameLog`. **Migrować:** istniejące `players.id`, `password_hash`, nazwy pól, powiązania i sesje. **Test:** stare hashe, konta o różnym statusie, istniejący e-mail i nazwa, transakcja przy awarii bootstrapu. **Status:** POTWIERDZONE W KODZIE; RUNTIME NIEBADANY.

### A02 — [src/AdminAuth.php](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/src/AdminAuth.php#L65-L151)
**Rola:** oddzielne konto `admins`, pending 2FA, TOTP, SSO przez zweryfikowany kontekst gracza, zaufane urządzenia i reset hasła. `requireLogin()` sprawdza sesję oraz wariant zaufanego urządzenia; nie wolno utożsamiać `trySSO()` z pełnym zalogowaniem. **Migrować:** osobno konto admina i jego polityki, sekrety TOTP i klucz szyfrowania. **Test:** reset, SSO, timeout, blokada, TOTP, zaufane urządzenie oraz uprawnienia. **Status:** POTWIERDZONE W KODZIE; RUNTIME NIEBADANY.

### A03 — [src/ApiAuth.php](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/src/ApiAuth.php#L23-L80)
**Rola:** mobilny bearer, łączenie `api_tokens` do `players`, zapis `last_used_at`, token urządzenia. **Ryzyko:** porównanie surowej wartości tokenu w DB oraz możliwość automatycznego DDL `ensureSchema` (linie 109–137). **Test:** wygaśnięcie, odwołanie, weryfikacja po migracji, brak możliwości odczytu cudzych danych, zgodność mobilna. **Status:** POTWIERDZONE W KODZIE.

### A04 — [api/v1/auth/login.php](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/api/v1/auth/login.php#L16-L77)
**Rola:** POST z limitem prób, kontrolą weryfikacji adresu i statusu, generowanie tokenu. **Test:** limit po nazwie i e-mailu, stan zablokowany, błędny format JSON, zgodność kontraktu odpowiedzi i brak regresji klienta Flutter. **Status:** POTWIERDZONE W KODZIE.

### A05 — [src/Database.php](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/src/Database.php#L8-L53)
**Rola:** jeden PDO/MySQL, konfiguracja przez środowisko; emulowane przygotowane zapytania. **Ryzyko migracyjne:** inny sposób bindowania typów w Laravel; istnieje helper `addColumnIfMissing` (linie 99+). **Test:** limity paginacji, DECIMAL, strefy czasu, kodowanie, transakcje i DDL. **Status:** POTWIERDZONE W KODZIE.

### A06 — [src/init.php](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/src/init.php#L13-L145)
**Rola:** bootstrap wielu serwisów oraz autoloader; posiada obejście problemów OPcache po FTP. **Ryzyko:** zależności ładowane globalnie ukryją się podczas zamiany na kontener usług Laravela; potrzeba grafu klas i bootstrappingu. **Test:** HTTP, cron, import klas, konfiguracja, kolejność inicjalizacji. **Status:** POTWIERDZONE W KODZIE.

### A07 — [src/BankAccountService.php](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/src/BankAccountService.php#L53-L131)
**Rola:** numer bankowy gracza i historia `bank_transactions`; w konstruktorze `ensureSchema()` wykonujące DDL, z kontrolą `inTransaction()`. **Migrować:** bez utraty numerów, historii i indeksów; zastąpić tworzenie schematu migracjami. **Test:** wielokrotne uruchomienie, kolizje numerów, brak automatycznego commita. **Status:** POTWIERDZONE W KODZIE.

### A08 — [src/WalletService.php](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/src/WalletService.php#L5-L29)
**Rola:** `cash` i `bank_balance`, transfery między pulami, schemat; sam serwis nie zapisuje pełnego audytu. **Ryzyko:** zapis audytowy musi pozostać odpowiedzialnością wywołującego. **Test:** suma środków przed/po transferze, wyścigi, rollback, wartości graniczne. **Status:** POTWIERDZONE W KODZIE.

### A09 — [src/FinancialTransactionService.php](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/src/FinancialTransactionService.php#L9-L42)
**Rola:** centralne operacje `credit`, `debit`, `transfer`, `logTransaction` oraz typy wpisów finansowych. **Migrować:** nienaruszalny ledger `bank_transactions`, dokładne znaczenie transakcji, idempotencję i blokady. **Test:** wszystkie typy, współbieżność, brak podwójnych zapisów, niezmienniki ekonomii. **Status:** POTWIERDZONE W KODZIE.

### A10 — [src/Tick/TickCoordinator.php](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/src/Tick/TickCoordinator.php#L57-L124)
**Rola:** globalny koordynator ticków, blokada, sekwencja uruchomienia, scheduler modułów, obsługa nieukończonego przebiegu. **Migrować:** nie zamienić mechanicznie na scheduler Laravel bez testu wyłączności i sekwencji; w żadnej chwili nie mogą pisać dwa niezależne silniki. **Test:** podwójne wywołanie, crash i wznowienie, porównanie wyników ekonomicznych. **Status:** POTWIERDZONE W KODZIE.

### A11 — [src/Tick/PlayerTickTransaction.php](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/src/Tick/PlayerTickTransaction.php#L13-L50)
**Rola:** transakcyjny okres pojedynczego gracza; blokuje `players` i `storage`, korzysta z savepointów przy transakcji nadrzędnej. **Test:** porównanie nested transactions Laravel, wyjątek po obciążeniu, gracz bankrupt i brak magazynu. **Status:** POTWIERDZONE W KODZIE.

### A12 — [admin/force_tick.php](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/admin/force_tick.php#L4-L32)
**Rola:** wymuszenie ticka w panelu; autoryzacja, CSRF, sesyjny cooldown, zapis administracyjny. **Ryzyko:** operacja zmienia gospodarkę globalną; wymaga wydzielonego uprawnienia i izolacji testowej. **Test:** równoległe wywołania, cooldown w wielu sesjach, globalna blokada. **Status:** POTWIERDZONE W KODZIE.

### A13 — [admin/gm_tools.php](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/admin/gm_tools.php#L361-L444)
**Rola:** destrukcyjne działania na danych graczy i narzędzia GM. **Ryzyko P0:** domyślne czyszczenie każdej nowej tabeli poza allowlistą, `TRUNCATE`, brak atomowego rollbacku pełnego resetu. **Test:** wyłącznie na odizolowanej bazie; ochrona przed uruchomieniem na produkcji, kontrola ról, dodatkowe potwierdzenie i backup. **Status:** POTWIERDZONE W KODZIE.

### A14 — [admin/template_editor.php](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/admin/template_editor.php#L3-L51)
**Rola:** konfiguracja strony i menu, tła, upload grafiki. Oddzielny endpoint chunków wymaga admina oraz CSRF, następnie zapisuje wynik uploadu i audit log. **Migrować:** ścieżki do plików, obrazy na serwerze, dane konfiguracji z DB; nie kopiować jedynie Git assets. **Test:** duże pliki, anulowanie, powtórzenie chunku, zapis w DB i render w panelu gracza. **Status:** POTWIERDZONE W KODZIE.

### A15 — [src/BoardroomUploadService.php](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/src/BoardroomUploadService.php#L20-L78)
**Rola:** upload dzielony na chunki, limity rozmiarów i blokada `flock`. **Migrować:** zachować walidację i katalogi zasobów. **Test:** wyścigi, niekompletne części, nieprawidłowy PNG, ścieżki i uprawnienia. **Status:** POTWIERDZONE W KODZIE.

### A16 — [src/PlayerMenuEditor.php](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/src/PlayerMenuEditor.php#L47-L95)
**Rola:** atomowy zapis menu z porównaniem wersji oraz `FOR UPDATE`. **Migrować:** strukturę `player_nav_groups`, `nav_items`, wielojęzyczne etykiety i kolejność. **Test:** jednoczesna edycja w dwóch kartach, cofnięcie zmian, złe URL, zachowanie kluczowych pozycji. **Status:** POTWIERDZONE W KODZIE.

### A17 — [templates/header.php](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/templates/header.php#L1-L55)
**Rola:** wygląd i nawigacja gracza, dynamiczna konfiguracja strony z `site_config`, style i skrypty. **Migrować:** klasy CSS, JS i dane konfiguracji do Blade bez redesignu. **Test:** responsywność i porównanie pikselowe w przeglądarce. **Status:** POTWIERDZONE W KODZIE.

### A18 — [admin/partials/header.php](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/admin/partials/header.php#L16-L72)
**Rola:** nawigacja admina — 36 wpisów w menu. **Test:** wszystkie adresy, prawo dostępu, aktywna sekcja, urządzenia mobilne. **Status:** POTWIERDZONE W KODZIE.

### A19 — [admin/2fa.php](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/admin/2fa.php#L17-L105)
**Rola:** ekran kodu TOTP i pierwszej konfiguracji. **Test:** ponowne użycie kodu, limity, brak klucza, zaufane urządzenie, logout. **Status:** POTWIERDZONE W KODZIE.

### A20 — [.github/workflows/sync-logs.yml](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/.github/workflows/sync-logs.yml#L18-L45)
**Rola:** pobieranie logów produkcyjnych i commit do repozytorium. **Ryzyko P0:** możliwe niezamierzone ujawnianie zdarzeń, tokenów lub danych użytkowników. Nie oceniać zawartości logów w publicznym PR. **Test:** wyłączyć publikowanie logów w publicznym repo, zaplanować prywatny kanał diagnostyczny. **Status:** POTWIERDZONE W KODZIE.

### A21 — [.gitignore](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/.gitignore#L1-L19)
**Rola:** wyklucza nowe `*.log`, `*.bak`, `.env.*`; nie usuwa z Git wcześniej śledzonych artefaktów. **Test:** kontrola `git ls-files` i czyszczenie historii po zabezpieczeniu sekretów. **Status:** POTWIERDZONE W KODZIE.

### A22 — [tests/admin_dashboard_browser.cjs](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/tests/admin_dashboard_browser.cjs#L1-L27)
**Rola:** Playwright sprawdza UI pod `fixture.test` z lokalnym HTML / mockami. **Ryzyko pokrycia:** nie dowodzi współpracy panelu z prawdziwą bazą ani API. **Test:** prawdziwe E2E na staging. **Status:** POTWIERDZONE W KODZIE.

### A23 — [tests/MySqlIntegration/MySqlTransactionRacesTest.php](https://github.com/Mario453-tech/www/blob/7521a97aab762c1ff4dd27b249b78778922cb5ed/tests/MySqlIntegration/MySqlTransactionRacesTest.php#L148-L190)
**Rola:** istniejąca baza scenariuszy współbieżności, anulowania i wycofania transakcji. **Migrować:** zachować jako testy regresyjne oraz uzupełnić o Laravel. **Status:** POTWIERDZONE W KODZIE; uruchomienie w tej partii NIE POTWIERDZONE.

## Wnioski wspólne

1. Dopóki nie mamy aktualnego schematu produkcji, nie wolno traktować `tests/ci-schema.sql` jako kompletnego wzorca.
2. W bazie istnieje rozdział kont `players` i `admins`, a ścieżki API mobilnego wymagają kompatybilności.
3. Laravel będzie modularnym monolitem, nie pretekstem do wymiany grafiki ani wszystkich tabel.
4. Wymagana druga partia: analiza wszystkich kontrolerów → usług domenowych → SQL → frontend (w tym elementy inline) → testów i faktycznych wyników.

## Status audytu

Partia A: przegląd kodu źródłowego i identyfikacja ryzyk. Zapis rejestru wszystkich 5133 śledzonych plików: `02_ALL_TRACKED_FILES.tsv`. Oddzielnie: 36 kontrolerów i 35 widoków panelu poddano automatycznej kontroli statycznej (`04_*`, `05_*`). **Nie przeprowadzono pełnego audytu semantycznego wszystkich 5133 plików, zrzutu produkcyjnego, E2E ani migracji danych.**
