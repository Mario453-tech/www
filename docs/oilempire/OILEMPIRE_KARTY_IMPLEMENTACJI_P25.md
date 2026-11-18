# OilEmpire — karty implementacyjne P25

Status: kontrakt dokumentacyjny do programowania po audycie; wartości DOM pozostają do zatwierdzenia. Szczegóły wymagane przez plan §3 są powiązane przez [macierz DoR akcji](OILEMPIRE_KONTRAKTY_AKCJI_P14_P25.md). Czytać z [planem](OILEMPIRE_PLAN_PROGRAMOWANIA.md), [kontraktami maszynowymi](OILEMPIRE_KONTRAKTY_P14_P25.yaml), [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql), [OpenAPI](OILEMPIRE_OPENAPI_P14_P25_V1.yaml) i [rejestrem propozycji DOM](OILEMPIRE_PROPOZYCJE_DOM_P14_P25.md). Zatwierdzone OPEN01–OPEN12 obowiązują; nierozstrzygnięte DOM nie mają wartości produkcyjnej. Każda mutacja stosuje wymagany Settlement i LockPlan §23.5a; Ledger, ruch fizyczny i outbox tylko wtedy, gdy operacja ma taki skutek. GET jest odczytem bez skutków ubocznych. UI Vue3/Flutter/Filament korzystają z tych samych Actions, nie z własnej ekonomii.

## P25. Release/Operations
Źródło: §29, bezpieczeństwo §9–§11. Właściciel zapisu: **Operations**; korzeń balansu: `admin`; poprzedniki: P01–P24.
Dane: release_reports, restore_reports, reconciliation_runs, alert_events, runbook_versions. Właściciele cudzych tabel są odpytywani przez publiczny kontrakt. Mutowalne ID wewnętrzne BIGINT, publiczne ULID; kwoty BIGINT grosze/API string, ropa DECIMAL(16,4), czas UTC DATETIME(3).
LockPlan: operacyjne lease rank5; gospodarcza rekoncyliacja przez publiczne kontrakty, bez prywatnego UPDATE; publikacja config osobnym porządkiem. Zasoby obu firm deklaruje się przed pierwszym lockiem; żaden handler nie wraca do wcześniejszej rangi.
Reguły: Wydanie ma immutable release_version + contract versions + balance hash. RPO≤5min, RTO≤60min mierzone na docelowym serwerze. Dokumentacja i build nie oznaczają produkcyjnego wdrożenia. Rollback schematu wymaga expand/contract, bez kasowania historii.
UI/GM: Filament: health, kolejki, dead-letter, raport wydania/restore i przycisk kontrolowanego wycofania. Web Vue3 i aplikacja mobile korzystają z tej samej Action; PL/EN/DE, lokalne SVG, dostępny modal zamiast okna systemowego.
Odbiór pakietu: każda karta ma test pozytywny, odmowy, obcej firmy, retry, równoległości MySQL i awarii workera. Parametry tylko z zatwierdzonej balance_version; propozycje DOM nie aktywują funkcji.


### P25-F01 — BuildReleaseReport
- **Aktor i kontrakt:** GM/Operations lub kontrolowany job. commit, schema version, balance hash, test artifacts → signed report. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `release_reports`. draft→verified; wszystkie bramki i artefakty, bez ręcznego oznaczania PASS. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** operacyjne lease rank5; gospodarcza rekoncyliacja przez publiczne kontrakty, bez prywatnego UPDATE; publikacja config osobnym porządkiem. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `operations.build_release_report.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak dowodu → blocked. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** fałszywy green CI kontra real deploy; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P25-F02 — RunReconciliation
- **Aktor i kontrakt:** GM/Operations lub kontrolowany job. world snapshot, interval → discrepancy report. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `reconciliation_runs`. read-only źródła FTS, bbl, escrow, GBBL, due/outbox; korekta oddzielną autoryzowaną akcją. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** operacyjne lease rank5; gospodarcza rekoncyliacja przez publiczne kontrakty, bez prywatnego UPDATE; publikacja config osobnym porządkiem. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `operations.run_reconciliation.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** wykryta różnica → alert, nie cicha naprawa. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** double FTS, orphan due, stock divergence; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P25-F03 — RestoreWorld
- **Aktor i kontrakt:** GM/Operations lub kontrolowany job. backup_id, target_env, expected_checksum → restore report. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `restore_reports`. test w izolacji, RPO/RTO pomiar, worker fencing i replay idempotentny. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** operacyjne lease rank5; gospodarcza rekoncyliacja przez publiczne kontrakty, bez prywatnego UPDATE; publikacja config osobnym porządkiem. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `operations.restore_world.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** checksum mismatch → stop. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** przerwana kopia i odtworzenie; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P25-F04 — VerifyRelease
- **Aktor i kontrakt:** GM/Operations lub kontrolowany job. release_id, probes, rollback_ref → ready/blocked. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `release_reports`, `alert_events`. health HTTP/queue/DB, migracje, klient, 90dni i inflacja<5% stałego koszyka. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** operacyjne lease rank5; gospodarcza rekoncyliacja przez publiczne kontrakty, bez prywatnego UPDATE; publikacja config osobnym porządkiem. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `operations.verify_release.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** jakikolwiek wymagany FAIL → blocked. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** pełny gate P25, rollback drill; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.



### P25-F05 — ReadHealth
- **Aktor i DTO:** GET /up publiczny, minimalny live bez szczegółów; GET /internal/ready prywatny dla Operations/monitora. Wynik live/ready/degraded i status zależności bez sekretów/PII.
- **Warunki:** baza, kolejka, cache, prywatny storage, zaległość due/outbox, schema/contract/balance version. Każdy probe ma ograniczony timeout; zewnętrzny provider degraduje odpowiednią capability, nie ukrywa awarii.
- **Dane/transakcja:** odczyt bez Settlement, Ledger, FTS, outbox i bez mutacji; prywatny wynik wymaga autoryzacji.
- **Test/monitoring:** niedostępna baza/kolejka/cache/storage, różnica wersji migracji, brak PII w /up, alert z deduplikacją i PL/EN/DE + lokalne SVG w Filament.
