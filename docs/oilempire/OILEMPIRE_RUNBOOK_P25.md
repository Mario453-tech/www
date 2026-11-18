# OilEmpire — runbook i bramka wydania P25

Źródła: architektura §29, bezpieczeństwo, [karty P25](OILEMPIRE_KARTY_IMPLEMENTACJI_P25.md), [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql). To kontrakt do wdrożenia; nie dowód uruchomienia produkcyjnego.

## Health i monitoring

GET /up: odpowiedź live bez nazw hostów, wyjątków, sekretów ani stanu graczy. GET /internal/ready: prywatna autoryzacja Operations; osobne probe MySQL, kolejka/worker, cache, prywatny magazyn plików, schema_version, contract_version, aktywny balance hash, due lag, outbox lag i dead-letter. Każdy probe ma timeout oraz stany healthy/degraded/down. Mierz p50/p95/p99 Action, lock wait/deadlock, CAS conflict, retry, due lag, outbox lag, escrow age, różnicę FTS/GBBL/bbl, błędy providera, nieudane push. Alarm deduplikowany per kod/okno, z escalation i bez payloadów PII. P25-F05 jest odczytem, nie uruchamia Settlement.

## BuildReleaseReport

Wejście: commit/release_version, schema_version, contract_version, balance_version i SHA-256 YAML, artefakty CI/Pint/PHPStan/MySQL/API/UI/mobile/security, wynik symulatora kandydata, raport 90 dni oraz test restore. Wynik: immutable report JSON + hash, podpis decyzji GM/Operations, status verified/blocked. Brak dowodu lub FAIL oznacza blocked; formularz GM nie może ręcznie wpisać PASS. Audyt zawiera kto/kiedy/co, bez wymogu komentarza od głównego administratora.

## RunReconciliation

Snapshot spójnego logical_at i zakresu świata. Porównaj: suma debetów/kredytów FTS=0; konta/obligations; produkcja+zakupy−sprzedaż−straty−flara=stan ropy/produktów; każda rezerwacja odnosi się do wolumenu dostępnego; held=released+refunded+remaining escrow; suma grantów−debetów GBBL=saldo/recovery; due_events i outbox bez osieroconych/zdublowanych effect_key. Różnica tworzy reconciliation_runs/alert, nigdy cichego UPDATE; korekta osobną autoryzowaną Action.

## Backup, restore, RPO/RTO

Backup MySQL zgodny z transakcjami oraz prywatnych plików, konfiguracji, kluczy i wersji aplikacji (sekrety poza raportem). Zapisz ID, czas, checksumy, punkt binlog/PITR, balance/contract/schema versions. RestoreWorld działa na odizolowanym środowisku: odtwórz bazę i pliki, zastosuj log do punktu, uruchom migracje kompatybilne, kolejki wstrzymane, sprawdź reconciliation, autoryzację, probe i smoke test; dopiero potem kontrolowane odblokowanie. RPO≤5 min i RTO≤60 min mierzyć na docelowym serwerze; test regularny oraz przed ryzykownym wydaniem.

## VerifyRelease / rollback

Bramka: 0 FAIL symulatora kandydata, four-eyes balance, zielone testy MySQL/kontrakty/bezpieczeństwo, 90 dni scenariuszy stała populacja/napływ/kryzys z inflacją koszyka <5% miesięcznie, measured restore, API mobile stara wersja, migracja expand i rollback plan. Weryfikować wdrożony SHA/release_version, nie tylko CI. Canary i health muszą odczytać nowy kontrakt przed pełnym ruchem. Przy błędzie: zatrzymać nowe Actions, drain z fencing, przełączyć kompatybilną wersję aplikacji, nie usuwać tabel ani historii; po awarii uruchomić reconciliation i zapis incydentu. Rollback balansu to nowa zatwierdzona wersja, bez edycji starego snapshotu.
