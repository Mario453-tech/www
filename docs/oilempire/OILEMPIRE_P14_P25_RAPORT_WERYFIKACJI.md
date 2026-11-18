# OilEmpire — raport kontroli dokumentacji P14–P25

> **Aktualizacja 2026-11-18:** [niezależny audyt kompletności](OILEMPIRE_P14_P25_AUDYT_KOMPLETNOSCI.md) wykazał błędy P0/P1. Wynik poniżej dotyczy kontroli strukturalnej i starego balansu; dokumentacja P14–P25 nie spełnia jeszcze Definition of Ready.

Data: 2026-11-18. Zakres: dokumentacja na main, nie wdrożenie gry.

## Wynik kontroli strukturalnej

- 56 unikalnych kart P14-F01–P25-F04 w 4 plikach; każda wskazuje właściciela, wejście/wynik, stan, LockPlan, idempotencję, zdarzenie, awarię i test.
- Manifest maszynowy: 56 wpisów, 71 tabel z jednym właścicielem, 27 publicznych operacji oraz 29 komend/kontraktów wewnętrznych. 27 tras odpowiada kartom i OpenAPI.
- Projekt DDL: 71 CREATE TABLE, 104 deklaracje FK; wszystkie tabele docelowe FK są w tym projekcie lub jawnie w P01–P13 (companies, users, storages). Brak zduplikowanej nazwy FK. Jest to kontrola statyczna, nie wykonanie SQL.
- OpenAPI 3.1 zapisane w składni JSON zgodnej z YAML 1.2: poprawne parsowanie JSON, 27 unikalnych operationId, rozwiązane lokalne $ref, parametry ścieżki i pola required. Odpowiedzi mają DTO specyficzne dla operacji, standard błędu i POLICY_NOT_APPROVED.
- 12 pozycji DOM: DOM03–05, DOM06, DOM08–15. DOM06 i DOM08 oznaczone jako częściowe; wszystkie nowe wartości mają status propozycji. P05 Admin/Balance pozostaje właścicielem zatwierdzeń.
- Przejście projektowe S01–S06: escrow/refund, matching/spot, egzekucja/transfer, webhook/chargeback, retry powiadomień, receptura/popyt/kontrakt. Zapisano prewarunki, kolejność i niezmienniki. Testy współbieżne pozostają do wykonania w Laravelu.

## Symulator balansu

Uruchomiono z kopii plików z main: OILEMPIRE_SYMULATOR_BALANSU.py (blob 7a73e8c9f0e0cfe18e22dc46ab67f753fc206953), architektura po aktualizacji LockPlan (blob dd09f0b7a37e96867956b8a0077be9ac7283dfad), panel (blob 190f3548e0c99a46a8852474b18b53a50c86faa0) i kandydująca kopia YAML identyczna bajtowo z obecną v1 (blob 944aac1d71867329e32c0452e21689a5e0273fbb; SHA-256 7e8915307252f8f08a49be9ea0d18058ef0df8087b94b33686525a69998f16fe). Wynik: **73 PASS, 0 FAIL, 0 INFO**. Pierwsze uruchomienie w domyślnym kodowaniu konsoli Windows przerwał błąd wyświetlania Unicode; ponowienie z PYTHONIOENCODING=utf-8 zakończyło się kodem 0. Propozycje DOM nie zostały dopisane do YAML i nie są objęte tym wynikiem.

## Bramka przed implementacją i aktywacją

1. Właściciel produktu zatwierdza lub odrzuca propozycje DOM. Zależne akcje do tego czasu zwracają POLICY_NOT_APPROVED. Zatwierdzenie wymaga wspólnej aktualizacji kanonu, kart, kontraktów, kandydującego YAML i symulatora; aktywacja balansu wymaga four-eyes i hash.
2. DDL wymaga preflight typów FK i nazw P01–P13, rozbicia na migracje expand/contract, wykonania na MySQL 8.4 oraz testów EXPLAIN, ograniczeń i współbieżności. Ten raport nie potwierdza poprawności wykonania DDL w MySQL.
3. Laravel/Filament/Vue/Flutter muszą przejść testy jednostkowe, API, UI, autoryzacji i mobile; P25 mierzy 90 dni, inflację <5%/miesiąc, RPO≤5 min i RTO≤60 min na docelowym serwerze. Tych testów nie uruchomiono, bo ta zmiana obejmuje dokumentację.
