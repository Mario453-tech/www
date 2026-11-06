# Audyt szczegółowy 102 — partia F: panel rynku

Data: 2026-10-30. Gałąź `feature/oilcorp-laravel-audit-baseline`. Przeczytano całe `admin/market.php` (SHA `513a42fea1211012143cf1fb4c93247c45d18c15`, 218 linii) oraz `templates/views/admin/market/main.php` (SHA `a69bd4d99e6fea6d515a8d7c7d87f20497da9966`, 264 linie). To dwie pozycje z wcześniej oznaczonych 102; ich status w rejestrze pozostaje `ADMIN_CONTROLLER_STATIC_SCAN` i `ADMIN_VIEW_STATIC_SCAN`, ponieważ test runtime, JS, CSS oraz wszystkie serwisy zależne nie zostały jeszcze domknięte.

## Wejście, uprawnienia i dane

`admin/market.php:5-17`: bootstrap admina, `AdminAuth::requireLogin()`, kontrola CSRF dla POST. Widok ma 6 formularzy POST (cena, zmienność, trend aktywny, tick, dodanie/edycja trendu, usunięcie trendu w każdym wierszu) i 1 formularz GET do filtrowania. Wszystkie POST używają `CSRF::field()`. Strona ładuje `/assets/css/admin_market.css` (kontroler 200–203); trzy przyciski używają `confirmSubmit(...)` inline (widok 58–60, 104–106, 234–236 dla usunięcia), którego definicję i zachowanie przeglądarkowe trzeba osobno sprawdzić.

| Akcja | Linie kontrolera | Tabela/kolumny | Efekt w silniku |
| --- | --- | --- | --- |
| `set_price` | 22–31 | `market_state.current_price` | `MarketTick::updatePrices` używa aktualnej ceny w kolejnym obliczeniu; ręczna cena może być nadpisana następnym tickiem |
| `set_multiplier` | 33–38 | `market_state.volatility` | `MarketTick.php:57,98` rzutuje na int i losuje szum; wartość ułamkowa traci część po przecinku |
| `set_trend` | 40–55 | `market_trends.active, activated_at, id, trend_name` | `MarketTrend::getActiveTrend` i `MarketTick` odczytują aktywny trend |
| `market_tick` | 57–63 | `market_state`, historia cen/podaży, oferty przez `MarketTick` | Ręczne przeliczenie poza `TickCoordinator` |
| `add_trend` | 65–79 | `market_trends.trend_name, category, price_modifier, duration_hours, message_template` | Trend dostępny do aktywacji |
| `edit_trend` | 81–96 | te same kolumny | Zmienia parametry istniejącego trendu |
| `delete_trend` | 98–108 | rekord `market_trends.id` | Może usuwać aktywny trend |

GET (111–179) pobiera singleton, trend aktywny, listę filtrowaną/paginowaną oraz rekord do edycji. Widok (1–264) pokazuje stan, formularze, listę i paginację. Testy działania akcji, klient JS oraz stan schematu produkcyjnego: niezweryfikowane.

## Ustalenia

**P1 — ręczny tick rynku omija blokadę całego silnika.** `admin/market.php:57-63` wywołuje bezpośrednio `MarketTick::updatePrices()`. Normalna ścieżka `cron/tick.php:27-28` idzie przez `TickCoordinator::run()`, który używa `GET_LOCK('oilcorp_tick', 0)` (`src/Tick/TickCoordinator.php:136-166`). Równoległy ręczny i planowy tick mogą obliczać oraz zapisywać `market_state` i historię bez wspólnej serializacji. Test na izolowanej MySQL: dwa połączenia, wymuszenie kolizji czasowej, kontrola liczby wpisów historii, ceny i kolejności ofert.

**P1 — aktywacja nieistniejącego trendu najpierw wyłącza wszystkie trendy.** `admin/market.php:40-50` bez transakcji wykonuje `UPDATE market_trends SET active=FALSE`, następnie `UPDATE ... WHERE id=:id` i pokazuje sukces bez sprawdzenia `rowCount()` lub istnienia ID. Przy obcym lub usuniętym ID aktywny trend znika. Standardowy `MarketTrend::activateTrend` sprawdza istnienie (linie 104–111), lecz panel go omija. Test: POST z nieistniejącym ID i awaria drugiego UPDATE, oczekiwany rollback i brak fałszywego sukcesu.

**P2 — limity ceny w formularzu nie są walidowane na serwerze.** Widok 56 ma `min=30 max=150`, a `admin/market.php:22-30` akceptuje każdą wartość >0. `MarketTick.php:19-20,100-102` ogranicza dopiero następny wynik do 30–150, więc do tego momentu panel i odczyty API mogą widzieć wartość poza zakresem. Test POST 1 i 100000 z poprawnym CSRF; sprawdzić zapis i odpowiedź.

**P2 — ułamkowa zmienność w panelu nie ma takiego samego znaczenia w silniku.** `admin/market.php:33-38` dopuszcza 0.1–5.0, widok 72 ustawia krok 0.01, a `src/MarketTick.php:57,98` rzutuje `volatility` na int przed `rand(-v,v)`. Wartości 0.1–0.99 dają szum 0, 1.99 działa jak 1. Należy ustalić zamierzoną jednostkę i przetestować wynik z kontrolowanym RNG.

**P2 — strona może komunikować sukces mimo braku zapisu singletonu.** `admin/market.php:25-27,35-37` nie sprawdza liczby zmienionych wierszy; przy braku `market_state.id=1` pokazuje sukces. `Market::ensureState` istnieje, ale nie jest wywołane w tym kontrolerze. To scenariusz do testu na sztucznej bazie, nie dowód wystąpienia na produkcji.

## Dodatkowe obserwacje

`admin/market.php:153-167` przygotowuje dwa identyczne zapytania paginacji; pierwsze nie jest wykonywane, a wiązania 154–155 są martwym kodem. Nie stwierdzono tu zmiany danych, więc P3. Widok 260 przy pustej liście i więcej niż jednej stronie nie występuje; zachowanie tekstu paginacji przy zero rekordów warto sprawdzić po zmianach. Transakcje dla `set_trend` i wersjonowanie zmian administracyjnych pozostają do zaprojektowania.

## Testy i dalsze dowody

W tej partii wykonano pełny odczyt dwóch plików i sprawdzenie wskazanych fragmentów `MarketTrend`, `MarketTick`, `TickCoordinator`, `Market`. Nie uruchomiono testów PHP, integracyjnych, E2E ani wizualnych. Do domknięcia: pełny audyt `src/MarketTrend.php`, `assets/css/admin_market.css`, definicja i test `confirmSubmit`, testy akcji panelu na anonimowej MySQL, porównanie UI oraz rzeczywisty schemat `market_state` i `market_trends`.