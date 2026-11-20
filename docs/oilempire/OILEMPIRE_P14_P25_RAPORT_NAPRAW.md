# OilEmpire — zamknięcie poprawek dokumentacji P14–P25

Data: 2026-11-18. Zakres: wyłącznie dokumentacja na gałęzi main repozytorium `Mario453-tech/www`. Bez zmian kodu gry, produkcyjnej bazy i aktywnego balansu.

## Wynik

13 ustaleń [niezależnego audytu](OILEMPIRE_P14_P25_AUDYT_KOMPLETNOSCI.md) ma poprawkę w kartach, [kontraktach akcji](OILEMPIRE_KONTRAKTY_AKCJI_P14_P25.md), [manifeście](OILEMPIRE_KONTRAKTY_P14_P25.yaml), [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql), [API gracza](OILEMPIRE_OPENAPI_P14_P25_V1.yaml) lub [API dostawców](OILEMPIRE_WEBHOOK_OPENAPI_P20_V1.yaml). To zamyka rozbieżności projektowe w dokumentach; nie potwierdza jeszcze wykonania migracji ani działania silnika.

| Audyt | Naprawa projektowa |
|---|---|
| 1 DOM dla całych pakietów | Manifest wskazuje zależność DOM tylko przy P21-F10 ApplyCartelSanction; pozostałe akcje nie są blokowane cudzą decyzją. Propozycje DOM pozostają do zatwierdzenia. |
| 2 arbitraż escrow/refund | [Scenariusz S01](OILEMPIRE_SCENARIUSZE_WSPOLBIEZNOSCI_P14_P25.md) zachowuje pierwszeństwo systemowego delivery_receipt według logical_at pod wspólną blokadą; opisano obie kolejności uruchomienia. |
| 3 FK i integralność | DDL dodaje brakujące FK/UNIQUE/CHECK dla escrow, B2B, trades, refinery, receipts i powiązań kontraktowych; P12 Logistics jest właścicielem delivery_receipts. |
| 4 brakujące dane | Dodano tabele, właścicieli i akcje dla prób łapówki, promocji, receipt sklepu, tokenów urządzeń, nawigacji i pozostałych obiecanych funkcji. |
| 5 zakupy/refundy | Rozdzielono web/store, weryfikację server-to-server, receipt, grant, refund, chargeback i kompensację; dodano unikalności transakcji operatora i scenariusz S07. |
| 6 model odczytu/API | Dodano GET snapshot, listy i szczegóły z polityką dostępu; webhooki wydzielono do specyfikacji dostawców. |
| 7 prywatność/DTO | Self-service działa na użytkowniku, purpose i wersja zgody są jawne, newsletter ma PL/EN/DE. |
| 8 LockPlan | Nagroda: Ledger20 → Monetization121 → Onboarding123; uzupełniono rank15 PvP i test deadlock/retry. |
| 9 receptura | [Kontrakt rekomendacji](OILEMPIRE_RECEPTURA_POPYT_KONTRAKTY.md) liczy popyt i zobowiązania na serwerze; klient podaje recipe_code i expected_state_version. |
| 10 szczegóły kart | 89 kart ma osobne kontrakty wykonawcze, stany, dane, błędy, blokady, outbox, UI, telemetrię, migrację i test; P25 ma własne warunki wydania. |
| 11 owner/event | Nazwy właścicieli są spójne; eventy bez spacji; odczyty nie emitują zdarzeń ani mutacyjnej idempotencji. |
| 12 pominięte funkcje | Dodano jednoznaczne akcje dla restrukturyzacji, bankructwa, łapówek, promocji, refundów, karteli, dyplomacji, wątku i nawigacji. Reset hasła należy do P02 Identity. |
| 13 DOM05 | [Propozycje](OILEMPIRE_PROPOZYCJE_DOM_P14_P25.md) zachowują próg 3 flag/7 dni, −15 cred/B2B i 30 dni zawieszenia zwolnienia; tylko nieustalony mechanizm deduplikacji pozostaje propozycją. |

## Kontrola po zapisie na GitHubie

Pliki odczytano ponownie z `main` i porównano z treścią zmian. Wszystkie 16 głównych artefaktów kontrolnych były zgodne bajtowo z przygotowaną treścią. Statyczny walidator wykazał:

- 89 unikalnych ID w czterech plikach kart, manifeście i kontraktach akcji; bez brakujących ID.
- 81 tabel z jednym właścicielem i 81 odpowiadających `CREATE TABLE`; 137 deklaracji FK, bez brakującej kolumny dziecka/rodzica, nieznanego celu, zduplikowanej nazwy i nazwy >64 znaków. Identyfikatory zewnętrznych operatorów, eventów i polimorficzne tank_id nie mają lokalnego FK celowo.
- 56 operacji API gracza i 2 operacje API dostawców; wszystkie player route/ID są w manifeście, lokalne `$ref` są rozwiązywalne, parametry ścieżki i wymagane pola istnieją.
- Brak eventu i mutacyjnej idempotencji dla odczytów; jedyna bramka DOM w nowych akcjach dotyczy P21-F10.
- Symulator ekonomiczny uruchomiony na lokalnej kopii obecnego YAML v1: **73 PASS, 0 FAIL, 0 INFO**. YAML nie zmienił się w tej rundzie. Próba nie obejmuje propozycji DOM ani wykonania nowego DDL.

Kontrola DDL jest statyczna. Projekt zawiera deklaracje wymagające preflight typów i indeksów z P01–P13. Nie uruchomiono MySQL 8.4, testów równoległości S01–S08, testów przeglądarkowych/UI, Laravela ani przebiegu 90 dni. Brak tych testów wynika z zakresu dokumentacyjnego i braku działającej implementacji P14–P25; nie wolno oznaczać wydania jako gotowego.

## Bramka wdrożenia

[Plan migracji](OILEMPIRE_MIGRACJE_P14_P25.md) i [runbook](OILEMPIRE_RUNBOOK_P25.md) wyznaczają kolejność. [DOM03–DOM15](OILEMPIRE_DOM_DECYZJE_WLASCICIELA_2026_11_18.md) otrzymały wybór wariantu do programowania. [DOM03-A](OILEMPIRE_DOM03_NEGOCJACJE_WARIANT_A.md) ma dokładny wzór; DOM06-A i DOM15-A pozostają nieaktywne do wykonania bramek wydania. Opublikowanie wersjonowanego balansu i symulatora nadal warunkuje aktywację zależnych akcji. Przed aktywacją wersji obowiązują four-eyes, 0 FAIL, hash YAML oraz testy MySQL/Laravel i 90 dni na docelowym środowisku.
