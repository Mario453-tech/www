# OilEmpire — kolejność migracji i dane P14–P25

Status: projekt dla migracji Laravela; [DDL MySQL 8.4](OILEMPIRE_DDL_P14_P25_MYSQL84.sql) jest wzorcem schematu, a nie poleceniem produkcyjnym. Nie edytować opublikowanych migracji; każda poprawka ma nowy numer.

## Preflight P01–P13

Przed pierwszą migracją potwierdzić istniejące i zgodne typy PK users.id, companies.id, storages.id (BIGINT UNSIGNED), silnik InnoDB, utf8mb4, indeksy rodziców i nazwy publicznych kontraktów Ledger/Logistics/Simulation. Jeżeli starszy schemat ma inny typ/nazwę, adapter migracji mapuje dane w osobnym, testowanym etapie; nie usuwać FK ani nie wprowadzać drugiej księgi. Zmierzyć EXPLAIN dla due/slot/cursor i ustalić wolumen danych.

## Expand w kolejności zależności

1. P14–P18: Market, Contracts, Finance, Banking, Legal. Utworzyć tabele bez FK cyklicznych, następnie FK/UNIQUE/CHECK. Contracts ma jeden z system_offer_id lub b2b_offer_id; escrow ma jeden z contract_id lub b2b_offer_id i UNIQUE dla obu. Fizyczne delivery_receipts należą do P12 Logistics; Contracts tworzy tylko contract_receipt_links z UNIQUE(logistics_receipt_id) i effect_key, co umożliwia receipt-first bez drugiej ścieżki transferu.
2. P19–P20: Refining i Monetization. refineries.selected_recipe_id oraz refinery_recipe_versions.refinery_id tworzą cykl: obie tabele najpierw, FK później. Store receipt szyfrowany, receipt_hash/transaction_id unikalne; refund ma osobny append, GBBL fulfillment_key unikalny.
3. P21–P24: Social, Onboarding, CorePrivacy, ClientDelivery. static_pages.published_version_id i page_versions.page_id tworzą drugi cykl. Chat ma członkostwo wątku, kartel flagi/sankcje, push token szyfrowany/HMAC, nawigacja wersjonowana.
4. P25: Operations i raporty wydania/restore. Health/readiness zależą od kolejki, bazy, cache, obiektu i migracji; zewnętrzne serwisy nie są uznane za zdrowe tylko dlatego, że HTTP 200.

Dla każdej paczki: migracja expand → test FK/UNIQUE/CHECK na MySQL 8.4 → deploy kodu czytającego nowy kontrakt → backfill/import, jeżeli zatwierdzony → dual-read/porównanie → wyłączenie starego odczytu. Wyłączenie foreign_key_checks nie jest planem migracji. MySQL 8.4 [opisuje ograniczenia FK](https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html) oraz [CHECK, w tym ograniczenia przy akcjach FK](https://dev.mysql.com/doc/refman/8.4/en/create-table-check-constraints.html); DDL pomija jawne ON DELETE/UPDATE na kolumnach użytych w CHECK.

## Import danych historycznych

Nowy silnik jest greenfield. Stare tabele służą do mapowania pojęć i ewentualnego importu po osobnej decyzji właściciela; nie są uruchomione równolegle jako źródło prawdy. Import wymaga: zamrożonego snapshotu źródła, mapy legacy→kanoniczne ID, przeliczenia pieniędzy na grosze i ropy na DECIMAL(16,4), walidacji sum FTS/GBBL/escrow i wolumenu, logu odrzuconych rekordów, dry-run na anonimizowanej kopii oraz podpisanego raportu reconciliation. Bez importu etap data ma jawny wynik „nie dotyczy”.

## Rollback i wydanie

Nie cofać wykonanych księgowań przez DROP/UPDATE historii. Wyłączyć nową trasę/handler feature gate, przeprowadzić draining kolejki, zachować czytelność wcześniejszej wersji kontraktu, a dane naprawić nową idempotentną Action i wpisem kompensującym. Zmiany schematu prowadzić expand/contract z okresem kompatybilności dla web/mobile; rollback aplikacji wymaga weryfikacji zgodności starego kodu z nowymi kolumnami. P25 archiwizuje backup ID, schema_version, contract_version, balance hash, wynik testów MySQL i restore. Produkcyjna migracja wymaga backupu, planu odtworzenia i zatwierdzenia.
