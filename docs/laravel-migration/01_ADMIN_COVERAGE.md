# OilCorp 2.0 — panel administracyjny: macierz pokrycia (audyt statyczny)

Data: 2026-10-29. Źródło: `Mario453-tech/www`, gałąź `main`.

## Legenda
- **S**: skontrolowano powiązanie w kodzie (nie oznacza działania w przeglądarce).
- **R**: test runtime ze środowiskiem i bazą TEST jest wymagany.
- **P**: wysoki priorytet — pieniądze, uprawnienia, dane, ticki, usuwanie.
- **U**: poziom rzeczywistej sprawności na ten moment niepotwierdzony.

Spis 36 sekcji jest oparty na nawigacji `admin/partials/header.php`. Wykryte powiązania nie są deklaracją pełnego pokrycia ani poprawności.

| Sekcja w menu | Kontroler | Obszar do sprawdzenia | Priorytet | Stan |
|---|---|---|---|---|
| Gracze | admin/players.php | lista, filtry, profile, edycja, usuwanie, ID, paginacja | P | S/R/U |
| Odwierty | admin/wells.php | ustawienia vs produkcja i tick | P | S/R/U |
| Kredyty | admin/loans.php | koszty, decyzje, spłaty, salda | P | S/R/U |
| Narzędzia GM | admin/gm_tools.php | szczególnie full_wipe, reset, klon i rozgłoszenie | P | S/R/U |
| HR | admin/hr.php | katalog zadań, role, pracownicy i tick | P | S/R/U |
| Zadania | admin/tasks.php | zapis i odczyt parametrów z konfiguracji | R | S/R/U |
| Zarząd | admin/boardroom.php | ustawienia i obraz | R | S/R/U |
| Szkolenia | admin/training.php | reguły i baza | R | S/R/U |
| Rynek | admin/market.php | ceny, trendy, sprzedaż i tick | P | S/R/U |
| Balans | admin/balance.php | parametry ekonomii i powiązania | P | S/R/U |
| Incydenty | admin/incidents.php | ustawienia, powiadomienia, rzeczywiste skutki | P | S/R/U |
| Czarny rynek | admin/black_market.php | transakcje i opłaty | P | S/R/U |
| Transport | admin/transport.php | ustawienia transportu | P | S/R/U |
| Straty transportu | admin/transport_loss.php | statystyki i raport | R | S/R/U |
| Rurociągi | admin/pipelines.php | przepustowość, koszty, tick | P | S/R/U |
| Huby logistyczne | admin/logistics_hubs.php | konfiguracja, przypisania, opłaty | P | S/R/U |
| Ochrona | admin/protection.php | wpływ na ryzyka i wyniki | R | S/R/U |
| Sabotaż | admin/sabotage.php | prawidłowość skutków, limity i blokady | P | S/R/U |
| Prawo | admin/legal.php | pozwolenia, przepisy, blokady | P | S/R/U |
| Łapówki | admin/bribery.php | wydatki, ryzyko i konsekwencje | P | S/R/U |
| Kontrakty | admin/contracts.php | ceny, obowiązki i rozliczenia | P | S/R/U |
| Finanse | admin/finance.php | odczyty oraz zapis konfiguracji | P | S/R/U |
| Kryzys finansowy | admin/financial-crisis.php | progi i przejścia statusów | P | S/R/U |
| Wiarygodność | admin/credibility.php | wskaźniki, zdarzenia i historia | R | S/R/U |
| Bank | admin/bank.php | korekty sald i historia audytu | P | S/R/U |
| Alerty | admin/alerts.php | progi i powiadomienia | R | S/R/U |
| Wizyty | admin/visits.php | statystyki i prywatność | R | S/R/U |
| Logi | admin/logs.php | uprawnienia, filtry, retencja, eksport | P | S/R/U |
| Moduły ticka | admin/tick_modules.php | scheduler, kolejność, konfig i logi | P | S/R/U |
| Czat | admin/chat.php | ustawienia, wiadomości, moderacja | R | S/R/U |
| Aktualności | admin/news.php | edycja i wyświetlanie | R | S/R/U |
| Newsletter | admin/newsletter.php | listy i wysyłka | R | S/R/U |
| Pomoc administratora | admin/admin_help.php | treści i uprawnienia | R | S/R/U |
| Edytor pomocy | admin/help_editor.php | zapis i render treści | R | S/R/U |
| Edytor wyglądu | admin/template_editor.php | menu, tła, upload, strony | P | S/R/U |
| Prywatność | admin/privacy.php | konfiguracja i zgodność zapisów | P | S/R/U |

## Dodatkowe strony i operacje niebędące wpisami głównego menu
`admin/index.php`, `admin/login.php`, `admin/2fa.php`, `admin/force_tick.php`, `admin/player.php`, `admin/player_clean.php`, `admin/pages_editor.php`, `admin/map_locations.php`, `admin/well_sales.php`, `admin/bank_status.php`, `admin/transport_loss.php` i API administracyjne.
Każde przekierowanie, formularz i endpoint musi być uwzględniony w pełnej automatycznej inwentaryzacji tras.

## Obecne potwierdzenia kodowe
- W 28 przykładowo sprawdzonych kontrolerach występuje jawne `AdminAuth::requireLogin()` i mapowanie do widoku lub inna kontrolowana odpowiedź; nie jest to jeszcze test autoryzacji runtime.
- `admin/tasks.php` renderuje formularze bezpośrednio w kontrolerze: kandydat do wydzielenia widoku Blade.
- `admin/player.php` jest przekierowaniem kompatybilności do `player_clean.php`.
- `tests/admin_dashboard_browser.cjs` korzysta z fixture i mocków; wymaga uzupełnienia o prawdziwe E2E.
- `tests/MySqlIntegration/MySqlTransactionRacesTest.php` ma scenariusze błędów transakcyjnych. Testów nie uruchomiono w tej fazie audytu.
- Zawartość `assets/images` i uploadów na serwerze może różnić się od Git — wymagane porównanie z produkcją.

## Protokół sprawdzenia każdej funkcji
1. Lokalizacja elementu na ekranie (widok i zrzut referencyjny).
2. Trasa HTTP (GET/POST/API) i uwierzytelnienie.
3. Kontroler/serwis oraz odczytywane i zmieniane tabele/kolumny.
4. Walidacja, błędne dane, CSRF, role, potwierdzenia.
5. Rzeczywisty efekt operacji na izolowanej bazie testowej i wpływ na tick.
6. Porównanie grafiki i reakcji JS na desktop/tablet/mobile.
7. Potwierdzony test automatyczny, data uruchomienia, wynik, ślad i regresja.
8. Status: działa / częściowo / nie działa / nieprzetestowane.

**Uwaga:** nie uruchamiać testów zapisujących dane na produkcji. Dotychczasowy audyt jest statyczny, więc statusu **działa** nie przypisano żadnej pozycji.
