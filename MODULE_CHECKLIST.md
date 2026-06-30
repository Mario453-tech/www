# Checklist nowego modułu — żeby wdrożenie nie było straszne

Krótka lista kontrolna dla każdego nowego modułu gry (web + API mobilne + cron).
Powstała po incydencie „Dane rynku niedostępne": endpoint `/api/v1/market` pytał o
nieistniejące kolumny `market_offers`, przeszedł przez całe CI i wyszedł dopiero w
aplikacji na telefonie. Cel listy: złapać takie rzeczy **w CI / smoke-teście**, zanim
dotkną produkcji albo telefonu.

Zasada nadrzędna: **jedno źródło prawdy dla schematu**. Kod, `tests/ci-schema.sql` i
produkcyjna baza muszą używać tych samych nazw kolumn. Jeśli kod zakłada kolumnę, ona
musi istnieć w `ci-schema.sql` (a `ci-schema.sql` musi nadążać za produkcją).

## 1. Schemat i bootstrap

- [ ] Tabele modułu dodane do `tests/ci-schema.sql` (z danymi seed, jeśli wymagany jest
      wiersz startowy — jak `market_state` id=1).
- [ ] Jeśli moduł wymaga tabel/wierszy, których nie ma w świeżej bazie: **idempotentny
      bootstrap** w stylu `ApiAuth::ensureSchema()` (`src/ApiAuth.php`) lub
      `Market::ensureState()` (`src/Market.php`) — `static $done`, pomija sqlite,
      `CREATE TABLE IF NOT EXISTS` / `INSERT IGNORE`, woła go `api/v1/_bootstrap.php`.
- [ ] Nazwy kolumn w kodzie **dokładnie** zgodne z `ci-schema.sql` i produkcją
      (np. `amount`/`limit_price`/`completed_at`, nie wymyślone `volume_bbl`).

## 2. Endpoint API (`api/v1/...`)

- [ ] Przechodzi przez `api/v1/_bootstrap.php` (CORS, auth, handler wyjątków, `apiJson`).
- [ ] Auth przez `apiRequireAuth()`; izolacja po `player_id` w każdym zapytaniu.
- [ ] Dodany do listy `$endpoints` w `tests/api_smoke.php` (E2E sprawdzi 200, nie 500).
- [ ] Kolumny czytane przez endpoint dopisane do mapy `CONTRACT` w
      `tests/MySqlIntegration/MySqlApiSchemaContractTest.php`.

## 3. Healthcheck

- [ ] Sekcja w `api/v1/healthcheck.php` (jak 3b/3c) uruchamiająca **dokładne** zapytania
      modułu — żeby smoke-test po deployu (`deploy-ftp.yml`) wykrył rozjazd na produkcji.

## 4. CI / smoke

- [ ] `php-tests.yml` zielone: `MySqlApiSchemaContractTest` + krok „API endpoint smoke".
- [ ] Sprawdzenie, że test naprawdę łapie błąd: tymczasowo zepsuj nazwę kolumny →
      E2E/kontrakt failuje → przywróć. (Dowód, że siatka działa.)

## 5. Aplikacja mobilna (jeśli dotyczy)

- [ ] Model `fromJson` z bezpiecznymi domyślnymi (`as num?`, `?? 0`) — brak pola nie wywala apki.
- [ ] Widoczny **stan błędu** gdy endpoint zawiedzie (wzorzec `_MarketErrorBanner` w
      `mobile/lib/modules/dashboard/dashboard_screen.dart`) — żeby błąd API był widoczny,
      a nie mylony z „brak danych".
- [ ] Klucze i18n PL + EN dla nowych etykiet.

## 6. Wdrożenie

- [ ] Merge do `main` → `deploy-ftp.yml` (PHP na FTP) i/lub `flutter-build.yml` (APK).
- [ ] Po deployu: smoke-test produkcji w logu joba „Upload to FTP" — sekcje healthchecku
      `[ OK ]`, endpoint odpowiada.
- [ ] `vendor/` NIE jest wgrywany na produkcję — nie używać `require vendor/autoload.php`
      jako twardej zależności (ładować klasy ręcznie, jak w `_bootstrap.php`).

---

### Znany otwarty action item (infrastruktura)

OPcache reset po deployu zwraca **403**. Po diagnostyce w `public/opcache-reset.php`
odpowiedź mówi którą przyczynę naprawić:
- `token_not_configured` — brak `OPCACHE_RESET_TOKEN` w produkcyjnym `.env` (pisanym z
  sekretu `ENV_FILE`).
- `token_mismatch` — token jest, ale różni się od sekretu `OPCACHE_RESET_TOKEN` użytego w curl.

Naprawa: wyrównać `OPCACHE_RESET_TOKEN` wewnątrz sekretu `ENV_FILE` z sekretem
`OPCACHE_RESET_TOKEN` w ustawieniach repo. Do tego czasu nowy kod PHP wchodzi z
opóźnieniem (auto-rewalidacja mtime), ale wchodzi.
