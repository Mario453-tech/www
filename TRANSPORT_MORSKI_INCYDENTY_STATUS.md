# Transport morski - incydenty w panelu admina

Data: 2026-06-05

## Co wdrożono

Dodano obsługę incydentów transportu morskiego w panelu administracyjnym incydentów:

- osobna zakładka `Morskie` w `admin/incidents.php`,
- toolbar do ręcznego wywoływania incydentu na aktywnej dostawie morskiej,
- typy incydentów: `piracy`, `catastrophe`, `storm`, `breakdown`,
- źródło `marine` w historii incydentów,
- księgowanie utraconych dostaw morskich jako strata transportowa w `finance_logs`,
- limit listy dostaw w selectcie do 15 aktywnych pozycji dla wybranego gracza.

## Zakres funkcjonalny

To dotyczy transportu morskiego/tankowców, czyli danych z tabeli `marine_deliveries`.

To nie dotyczy:

- hubów przeładunkowych,
- połączeń `odwiert -> hub`,
- transportu drogowego,
- rurociągów.

## Pliki zmienione

- `admin/incidents.php` - obsługa akcji `trigger_marine_incident`, pobieranie aktywnych dostaw morskich do formularza i źródło `marine` w historii.
- `templates/views/admin/incidents/main.php` - zakładka `Morskie` i formularz wywoływania incydentu morskiego.
- `assets/js/admin_incidents.js` - filtrowanie dostaw morskich po wybranym graczu i komunikat przy limicie listy.
- `lang/pl/admin/incidents.php` - tłumaczenia dla zakładki i formularza incydentów morskich.
- `src/Tick/PlayersSection.php` - utracone dostawy morskie z ticka trafiają do strat transportowych w `finance_logs`.
- `GAME_README.md` - changelog wdrożenia.
- `DZIAL_PRAWNY_P1_STATUS.md` - dopisany status weryfikacji zdarzeń transportowych.

## Commity

- `d2c4757 Add marine incident admin toolbar`
- `4d476ce Show marine incidents toolbar tab`
- `b128b5b Limit marine incident delivery options`

## Ważna uwaga projektowa

W kodzie istnieją dwa osobne obszary logistyki:

- `marine_deliveries` - transport morski/tankowiec/port,
- `logistics_hubs` i `logistics_hub_assignments` - huby przeładunkowe i połączenia odwiertów z hubami.

Wdrożony toolbar `Morskie` używa `marine_deliveries`. Jeśli kolejne zadanie ma dotyczyć połączenia `odwiert -> hub`, trzeba wdrożyć osobny toolbar hubowy, oparty o `HubIncidentService`, a nie o `marine_deliveries`.

## Testy wykonane

- `php -l admin/incidents.php` - OK,
- `php -l templates/views/admin/incidents/main.php` - OK,
- `php -l lang/pl/admin/incidents.php` - OK,
- `node --check assets/js/admin_incidents.js` - OK,
- `git diff --check` / `git diff --cached --check` - bez błędów treści.

Nie wykonano testu na lokalnej bazie MySQL, bo lokalne PDO zwróciło błąd połączenia `connection refused`.
