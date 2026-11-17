# Audyt plikowy E — ścieżka ticka rynku i ceny ropy

Data: 2026-10-30. Gałąź `feature/oilcorp-laravel-audit-baseline`. Odczytano w całości: `cron/tick.php` (SHA `49b23cdfac913ad55b8f92e5cf0e5dd9ab2634cd`), `src/Tick/Modules/MarketModule.php` (`370667a32d959de39cd968f236ac37ff73bf997b`), `src/Tick/MarketSection.php` (`e62a59524737603f71febbbc10f97a0daf8ce835`), `src/MarketTick.php` (`06a806029f9659f6142781dedd649385e3d50bc5`). W `src/Tick/TickCoordinator.php` (`5e0429aee80464143d4de0a7093adb8cfc788d27`) i `src/Tick/TickEngine.php` (`d6bbc2f54898eadf8ee8b3e61987840a77e4c64a`) sprawdzono fragmenty połączeń; ich pełny audyt pozostaje otwarty. Brak testów runtime w tej partii.

## Graf wywołań i danych

`cron/tick.php:4-28` → `TickCoordinator::run` → `TickEngine::runAll` → `MarketModule::run` (klucz `market`, kolejność 10, polityka STOP) → `MarketSection::run` → `MarketTrend`, `MarketTick::updatePrices`, `MarketOffer::purgeOldSaleHistory`.

`MarketTick` czyta `market_state.id, base_price, current_price, volatility, world_production, demand_index` i produkcję `wells.base_production_per_hour, transport_capacity_pct, technical_condition, status` wraz z `players.status`; zapisuje `market_state.current_price, supply_index, world_production, last_supply_tick, last_market_tick_at`, `market_supply_demand_log` i `price_history`, wywołuje `MarketOffer::processOffers`. `MarketModule` przekazuje cenę do `TickContext`, skąd korzystają następne moduły. Ustawienia administracyjne `market_state` i ich kontrolery, widoki, JS oraz konkretne testy porównawcze: do sprawdzenia.

## Kartoteka

### E01 — `cron/tick.php`

Linie 4–25: inicjalizacja; HTTP wymaga `well_config.cron_secret_key` w parametrze lub nagłówku, CLI omija tę kontrolę. Linie 27–40 uruchamiają koordynator i po sukcesie/częściowym sukcesie czyszczą historię sabotażu. Linie 42–51 zapisują wynik w globalach, ustawiają HTTP 500 dla niektórych porażek i wypisują podsumowanie. Decyzja migracyjna: zachować pojedynczą blokadę i semantykę źródeł, nie uruchamiać starego CRON i Laravel Scheduler równolegle. Weryfikacja uprawnień i konfiguracji HTTP/CLI pozostaje otwarta.

### E02 — `src/Tick/Modules/MarketModule.php`

Linie 14–16: klucz, kolejność i STOP. Linie 18–36: `MarketSection::run`, cena awaryjna gdy wynik <=0 oraz ustawienie `TickContext`. Linie 44–57: fallback `well_config.last_tick_oil_price` lub 70. Decyzja: kontrakt modułu musi rozróżniać poprawnie policzoną cenę od awaryjnej, aby scheduler i raport ticka nie oznaczały ich identycznie.

### E03 — `src/Tick/MarketSection.php`

Linie 14–19: kolejno trendy, cena, czyszczenie historii. Linie 21–54: wyjątki trendów i ceny są logowane, ale nie przekazywane wyżej. Linie 57–65: to samo dla czyszczenia historii. Decyzja: przed migracją określić, które błędy są krytyczne dla ekonomii i propagować je do wyniku modułu; testy dla awarii każdej sekcji.

### E04 — `src/MarketTick.php`

Linie 40–155 obliczają cenę według podaży, popytu, trendu, losowego szumu i limitów 30–150, zapisują stan i historię, przetwarzają oferty; przy wyjątku zwracają ostatnią cenę lub 70. Linie 159–177 wyliczają podaż z aktywnych odwiertów. Linie 179–239 aktualizują produkcję świata i historie/oferty. Linie 243–304 udostępniają historię i snapshot rynku. Decyzja: utrwalić algorytm i dane wejściowe w testach porównawczych ze sterowanym źródłem losowości; zachować stany i historię.

## Ustalenia

**P1 — snapshot rynku zawsze wpada w obsługę wyjątku po znalezieniu wiersza.** `src/MarketTick.php:259-285`: `getMarketSnapshot()` wywołuje `$this->fetchPlayerSupply()` w linii 265. W całej klasie zdefiniowano jedynie `fetchPlayerSupplyFlow()` (159–177); klasa nie dziedziczy po innej. PHP zgłosi błąd wywołania nieistniejącej metody, przechwycony przez `catch (Throwable)`, a funkcja zwróci `[]`. Warunek: `market_state.id=1` istnieje. Nie potwierdzono w tej partii, który ekran wywołuje tę metodę; należy sprawdzić użycie i test widoku.

**P1 — awaria aktualizacji ceny może zostać oznaczona jako udany moduł ticka.** `src/MarketTick.php:144-154` przechwytuje wyjątek i zwraca ostatnią cenę/70. `src/Tick/MarketSection.php:45-54` również przechwytuje wyjątek i pozostawia `newPrice=0`. `src/Tick/Modules/MarketModule.php:23-35` zamienia <=0 na fallback bez zgłoszenia błędu. `src/Tick/TickEngine.php:162-168` oznacza moduł jako sukces, jeżeli `run()` nie rzuci wyjątku. Dlatego nawet przy nieudanym zapisie `market_state` późniejsze moduły mogą otrzymać cenę awaryjną, a wynik nie ujawni porażki rynku. Test: sztucznie wymusić błąd `UPDATE market_state` w izolowanej bazie, sprawdzić status rynku, końcowy status ticka, brak niezamierzonych zapisów i raport administracyjny.

**P2 — awaria pobrania podaży jest liczona jako zerowa podaż.** `src/MarketTick.php:159-176` przechwytuje każdy błąd zapytania i zwraca `0.0`; `updatePrices` używa tej wartości w linii 64 i zapisuje wynik. Przy niezgodnym schemacie lub przejściowym błędzie DB cena może zostać przeliczona bez podaży graczy. Test: brak jednej z używanych kolumn, sprawdzenie, czy tick przerywa zapis ceny.

## Testy i otwarte zależności

W rejestrze są `tests/Unit/TickEngineTest.php` oraz `tests/MySqlIntegration/MySqlTickCoordinatorLockTest.php`; nie uruchomiono ich ani nie przypisano im pokrycia powyższych scenariuszy. Do zbadania: pełny `TickCoordinator`, `TickContext`, `MarketTrend`, `MarketOffer`, administracyjny edytor ustawień rynku, widoki, JS i testy wizualne. Nie uruchamiano CRON ani nie używano produkcyjnej bazy. Cztery pliki tej partii otrzymują status `CRITICAL_STATIC_REVIEW`; nie oznacza to potwierdzenia działania.