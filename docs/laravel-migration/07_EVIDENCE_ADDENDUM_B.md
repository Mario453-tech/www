# Audyt uzupełniający B — konta GM i publikacja logów

Data: 2026-10-30. Źródło: gałąź `feature/oilcorp-laravel-audit-baseline`, odczyt przez GitHub API. To przegląd statyczny wskazanych plików, bez dostępu do produkcyjnej bazy, bez uruchomienia aplikacji i bez testów E2E. Stan schematu produkcyjnego pozostaje nieznany. Poprzedni rejestr 5133 plików i jego licznik 92 wstępnie przejrzanych pozycji nie oznaczają ukończenia audytu.

## Ustalenia z dowodem

### P1 — klon konta GM zapisuje hasło do innej kolumny niż logowanie

- `admin/gm_tools.php:222-226`, blob SHA `4606dadf0a499b88405d232f9614a1e9e48c2d54`: akcja `clone_test` wykonuje `INSERT INTO players (email, password, cash, status, last_tick_at, created_at)`.
- `src/Auth.php:25-40`, blob SHA `bf0a4ca60bab67e626136536890bf7f45cd198db`: logowanie wybiera `password_hash` i wywołuje `password_verify`.
- `src/Auth.php:359-375`, ten sam SHA: standardowa rejestracja wpisuje `password_hash` oraz `username`.
- Wniosek statyczny: w schemacie bez kolumny `password` klonowanie kończy się błędem SQL i rollbackiem. Jeżeli kolumna `password` istnieje, nowy hash i tak nie trafia do pola odczytywanego przez logowanie. Należy sprawdzić rzeczywisty schemat oraz wymagania `username`; bez tego nie określamy jednego konkretnego komunikatu błędu runtime.
- Macierz: `admin/gm_tools.php::clone_test` → `players.email/password/cash/status/last_tick_at/created_at`, `storage.player_id/capacity/used`, `wells.player_id/...`, `pipelines.player_id/name` → `src/Auth.php::login` → `players.password_hash`. Widok formularza, JS i test wywołujący tę akcję: **niezweryfikowane**.
- Test do wykonania wyłącznie na izolowanej sztucznej DB: uruchomić klon z konta źródłowego, potwierdzić commit, obecność `password_hash`, możliwość logowania nowym hasłem, poprawność `username`, salda, magazynu, odwiertów i rollback przy błędzie. Testu nie uruchomiono.

### P1 — usuwanie graczy może przemilczeć błędy zależnych tabel

- `admin/gm_tools.php:291-316`, ten sam SHA: pętla wykonuje `DELETE ... WHERE player_id IN (...)` dla listy tabel i ignoruje każdy `Throwable`. Osobny blok również ignoruje błędy czyszczenia `candidates`.
- `admin/gm_tools.php:318-326`: po tych błędach kod nadal próbuje usunąć `players`, commitować i pokazuje liczbę równą liczbie żądanych identyfikatorów.
- Wniosek: przy schemacie z FK błąd usunięcia rekordu zależnego może zatrzymać końcowe `DELETE players`; przy brakujących FK możliwe pozostawienie osieroconych danych. Konkretna ścieżka zależy od produkcyjnego DDL. Test izolowany powinien wymusić błąd jednego `DELETE` i sprawdzić rollback, wpis audytowy oraz zgodność komunikatu z faktyczną liczbą usuniętych rekordów.

### P0 — workflow kopiuje log z produkcji do publicznego repo

- `.github/workflows/sync-logs.yml:18-26`, blob SHA `9e471cac674d7825830f5d303f468c7e3f6f99a6`: cykliczne pobranie do `logs/game_debug.txt` z produkcyjnego `logs_export.php`.
- Linie 27-30: przy odpowiedzi innej niż 200 workflow wypisuje zawartość pobranej odpowiedzi do logu CI. Linie 33-44: zmieniony plik jest commitowany i wypychany.
- Jest to potwierdzony mechanizm publikacji, nie potwierdzenie obecności konkretnych danych w logach. Nie kopiowano i nie wyświetlano wartości sekretów. Działanie pilne poza publicznym raportem: wstrzymać workflow na gałęzi domyślnej, sprawdzić prywatnie historię Git i Actions, ocenić ekspozycję dumpu SQL oraz plików tokenowych, ograniczyć dostęp, rotować potwierdzone sekrety i ustalić obowiązki dotyczące danych osobowych. Samo `.gitignore` nie usuwa historii.

## Decyzje migracyjne

1. `players.id`, `player_id`, `password_hash`, salda i historia są kontraktem danych. Dla istniejących tabel najpierw adapter Laravel; migracje schematu dopiero po porównaniu rzeczywistego DDL, z testem rollbacku i sumami kontrolnymi.
2. Akcje GM wymagają jawnej mapy tabel i kolumn oraz testów awarii w izolowanej bazie. Nie przenosić `full_wipe` do nowego panelu bez osobnego projektu zabezpieczeń.
3. Przed cutover zachować API mobile, 2FA, uploady i obecne assety. Porównania ekranów oraz testy API/ekonomii pozostają **niewykonane**.
4. Rejestr `02_ALL_TRACKED_FILES.tsv` należy zaktualizować dopiero po pełnym odczycie i kontroli obu stron zależności dla każdej nowej pozycji; ten raport nie podnosi liczby `FULL_SOURCE_REVIEW`.

## Weryfikacja i granice

Wykonano: odczyt źródeł i wzajemne porównanie instrukcji SQL oraz workflow na wskazanych SHA. Nie wykonano: testów jednostkowych, integracyjnych, E2E, wizualnych, badania prywatnych artefaktów ani schematu produkcyjnego. Brak bezpiecznego, odizolowanego środowiska i zatwierdzonej anonimowej kopii bazy w tej sesji. Nie uruchamiano CRON, resetów, migracji ani zapisów do DB.