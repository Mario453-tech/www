## Changelog

### 2026-07-10 - Kontrakty B2B: poprawki stabilności dostaw częściowych

- `src/B2BContractService.php` - finalna dostawa częściowa wypłaca teraz cały pozostały depozyt sprzedającemu i zeruje `remaining_escrow_amount`, dzięki czemu nie zostają groszowe resztki escrow po zaokrągleniach.
- `src/B2BContractService.php` - konfiguracje `partial_delivery_enabled`, `allow_multiple_deliveries` i `auto_finalize_after_deadline` mają teraz efekt runtime: blokują niepełną pierwszą dostawę, wymuszają finalną kolejną dostawę albo wyłączają automatyczne rozliczenie po terminie.
- `src/B2BContractService.php` - `sellerAbandonOffer()` nie cofa już `delivery_deadline_at` poza transakcją; wymuszone rozliczenie idzie przez `finalizeAcceptedOffer(..., force=true)`.
- `src/B2BContracts/B2BContractSchema.php` - dodano snapshot `allow_multiple_deliveries` na ofercie B2B, żeby zaakceptowany kontrakt zachowywał reguły z chwili wystawienia.
- `src/B2BContractService.php` - statystyki pulpitu B2B używają prepared statements zamiast interpolowania daty.
- `tests/Integration/B2BContractServiceTest.php` - dodano regresje dla wyłączonych dostaw częściowych, trybu jednej kolejnej dostawy, resztek escrow, wyłączonego auto-finalize i porzucenia oferty bez backdate deadline.

Walidacja: `B2BContractServiceTest` 39/39, `MySqlB2BContractServiceTest` 2/2, `tools/check_encoding.php`.

### 2026-07-09 - Logistyka: zamykanie transportu bez kasowania historii

- `MarineDeliveryService::purgeOrphanActiveForPlayer()` nie usuwa już osieroconych dostaw morskich przez `DELETE`; oznacza je jako `lost` z `incident_type = orphan_purge`.
- Panel admina transportu nie kasuje już aktywnych `well_road_trips` i `marine_deliveries` w trybie awaryjnym; zamyka je jako `lost`, zachowując historię.
- Czyszczenie transportu nie zeruje już buforów `road_buffer_bbl` i `marine_buffer_bbl`, żeby nie usuwać ropy bez rozliczenia.
- Zaktualizowano teksty PL/EN panelu admina, aby mówiły o zamykaniu rekordów, a nie o usuwaniu tabel.
- Dodano regresję MySQL potwierdzającą, że osierocona dostawa morska pozostaje w historii jako `lost`.

### 2026-07-08 - Kontrakty B2B: reputacja w UI gracza

- `/contracts` pokazuje teraz reputację B2B gracza jako procent oraz pasek postępu nad zakładkami B2B.
- `B2BContractService::getPlayerReputationScore()` udostępnia bezpieczny odczyt wyniku pojedynczego gracza i tworzy domyślny wpis reputacji, jeśli gracz jeszcze go nie miał.
- Widok korzysta z istniejącej tabeli `b2b_reputation_scores`; wynik jest przycinany do zakresu 0-100%.
- Dodano tłumaczenia PL/EN dla etykiety, odznaki i opisu reputacji B2B.

### 2026-07-08 - Najnowsze zmiany: kontrakty B2B

Ta sekcja zbiera ostatnie zmiany B2B w jednym miejscu, żeby były widoczne od razu na początku pliku.

1. Dodano fundament kontraktów B2B w istniejącym module `/contracts`, bez osobnej pozycji menu gracza.
2. Dodano schemat tabel B2B: `b2b_contract_offers`, `b2b_contract_terms`, `b2b_contract_logs`, `b2b_contract_config`.
3. Dodano `B2BContractService` do tworzenia, anulowania, realizacji, wygaszania, flagowania i anulowania ofert przez admina.
4. Podpięto finanse B2B przez `FinancialTransactionService`: escrow, zwroty, kary anulowania i przychód ze sprzedaży.
5. Dodano UI gracza w `/contracts`: `Systemowe`, `Rynek B2B`, `Moje B2B`, `Historia`, `Logi`.
6. Dodano zakładkę B2B w panelu admina: pulpit, ustawienia, oferty, flagowanie/anulowanie i logi.
7. Dodano filtry admina, paginację ofert/logów oraz wyszukiwanie po statusie, fladze i graczu/firmie/ID.
8. Dodano reputację B2B: tabele `b2b_reputation_scores`, `b2b_reputation_logs` oraz automatyczne zmiany reputacji po akcjach ofert.

Walidacja po wdrożeniu: `Unit + Integration`, `MySqlIntegration` oraz `tools/check_encoding.php`.

### 2026-07-07 - Dział techniczny: opłata przez FTS + pełna atomowość zlecenia

**Utwardzenie `startTask`: opłata za zadanie idzie przez FTS, a nieudane utworzenie zadania zwraca pieniądze.**

- `src/TTS/TasksTrait.php` — `startTask` pobiera koszt zadania przez `FinancialTransactionService::debit(TYPE_TTS_FEE)` zamiast surowego `UPDATE players SET cash` + `logTransaction` (reguła #10: każda mutacja gotówki przez FTS, z pełnym wpisem audytu w `bank_transactions`). Brak środków → `insufficient_funds` → komunikat „brak gotówki"; inny błąd FTS → wyjątek → rollback. Wywołanie zagnieżdżone w transakcji `startTask` jest bezpieczne dzięki izolacji SAVEPOINT w FTS.
- `src/TTS/TasksTrait.php` — blok `try` w `startTask` łapie teraz `Throwable` (było `Exception`), więc `Error`/`TypeError` w środku transakcji też wyzwala rollback; dodany strażnik `inTransaction()` przed `rollBack()`.
- `tests/Integration/TechnicalHubTasksTest.php` — test atomowości: gdy INSERT zadania zawiedzie, opłata wraca (gotówka bez zmian, brak osieroconego wpisu audytu, brak zadania).

### 2026-07-07 - Dział techniczny: zlecone zadanie kończyło się natychmiast (reguła #14)

**Naprawiono bug: zlecenie zadania technicznego pobierało pieniądze, ale zadanie nie pojawiało się jako „w toku" — kończyło się natychmiast.**

- Objaw: gracz zlecał zadanie działowi technicznemu, gotówka schodziła, ale zadanie nigdy nie było widoczne jako wykonywane. `TechnicalTeamService::startTask` zapisywał `start_time`/`end_time` zegarem PHP (`date()`), podczas gdy `processTick` domyka zadania porównując `end_time <= NOW()` zegarem MySQL (`getTasks` liczy `seconds_remaining` przez `TIMESTAMPDIFF(NOW(), end_time)`). Przy różnicy stref PHP vs MySQL `end_time` bywał już `<= NOW()` w chwili utworzenia, więc najbliższy tick — uruchamiany także przy odsłonie strony technicznej — natychmiast zamykał świeże zadanie. Naruszenie reguły #14.
- `src/TTS/TasksTrait.php` — `start_time`/`end_time` zapisywane teraz zegarem BAZY: `NOW()` oraz `DATE_ADD(NOW(), INTERVAL ? HOUR)` (MySQL) / `datetime(NOW(), ?)` (SQLite). Spójne z porównaniami `end_time <= NOW()`.
- `tests/Integration/TechnicalHubTasksTest.php` — test regresji: symuluje MySQL wyprzedzający PHP o 10 h i sprawdza, że zlecone zadanie zostaje `in_progress` po ticku.

### 2026-07-07 - FTS: izolacja SAVEPOINT dla wywołań zagnieżdżonych (atomowość ruchu środków)

**Naprawiono ograniczenie wzorca `$ownTx`: zagnieżdżone wywołanie FTS, które zawiodło w połowie zapisu, nie potrafiło wycofać częściowego ruchu środków.**

- Problem: PDO/MySQL nie ma prawdziwych transakcji zagnieżdżonych. Gdy `credit`/`debit`/`debitCombined` były wołane wewnątrz transakcji innego serwisu i zapis padał po zmianie salda a przed logiem (`INSERT bank_transactions`), wzorzec `$ownTx` nie robił `rollBack()` (bo cofnąłby całą transakcję wołającego) — częściowa mutacja salda mogła zostać zatwierdzona bez wpisu audytu. Dotyczyło wszystkich ścieżek FTS.
- `src/FinancialTransactionService.php` — nowe helpery `beginUnit()`/`commitUnit()`/`rollbackUnit()`. Wywołanie top-level nadal otwiera własną transakcję; wywołanie zagnieżdżone zakłada `SAVEPOINT` i przy błędzie robi `ROLLBACK TO SAVEPOINT` — cofa tylko własne zmiany, transakcja wołającego żyje dalej. `moveFunds()` (credit/debit/transfer) i `debitCombined()` przepisane na te helpery; logika biznesowa bez zmian. Działa w MySQL i SQLite.
- `tests/Integration/FinancialTransactionSavepointTest.php` (NOWY) — 4 testy izolacji SAVEPOINT.

### 2026-07-07 - Kontrakty długoterminowe: zerwanie kontraktu

**Rozbudowano anulowanie kontraktu do pełnego zerwania z konsekwencjami.**

- `src/Contracts/ContractSchema.php` — dodano pola `cancel_penalty`, `cancelled_reason` w `player_contracts` oraz `contract_blocked_until` w `contract_reputation`; seed kontraktów dostał termy `allow_cancel`, `cancel_penalty_pct`, `cancel_penalty_fixed`, `cancel_reputation_loss`, `cancel_forfeit_deposit`, `cancel_blocks_new_contracts_minutes`.
- `src/ContractService.php` — zerwanie kontraktu sprawdza `allow_cancel`, nalicza karę od niewykonanego wolumenu, pobiera środki przez `FinancialTransactionService::debitCombined(TYPE_CONTRACT_PENALTY)`, rozlicza przepadek/zwrot kaucji i zapisuje powód oraz karę w snapshotcie kontraktu.
- `src/ContractReputationService.php` — zerwanie korzysta z `cancel_reputation_loss` i może blokować podpisywanie nowych kontraktów do `contract_blocked_until`.
- `templates/views/contracts/main.php` + `lang/pl/contracts.php` + `lang/en/contracts.php` — UI gracza mówi teraz o zerwaniu kontraktu, a nie zwykłym anulowaniu; dodano komunikaty blokady, braku środków i braku prawa do zerwania.
- `tests/Integration/ContractServiceTest.php` — dodano regresje dla kary zerwania, przepadku kaucji, blokady nowych kontraktów i `allow_cancel = 0`.

### 2026-07-07 - Kontrakty długoterminowe: Etap 3 — kaucja kontraktowa

**Dodano zabezpieczenie finansowe przy podpisywaniu większych kontraktów.**

- `src/Contracts/ContractSchema.php` — dodano kolumny `security_deposit`, `security_deposit_status`, `security_deposit_refunded` w `player_contracts` oraz domyślne termy kaucji (`security_deposit_pct`, `security_deposit_fixed`, reguły zwrotu/przepadku i rabatu reputacyjnego).
- `src/ContractService.php` — podpisanie kontraktu liczy kaucję po aktualnej cenie ropy po stronie serwera, stosuje modyfikator reputacji kontraktowej, pobiera środki przez `FinancialTransactionService::debitCombined()` i zapisuje snapshot w kontrakcie. Ukończenie/anulowanie/porażka rozliczają zwrot, częściowy zwrot albo przepadek kaucji w tej samej transakcji.
- `src/FinancialTransactionService.php` + `src/WalletConfig.php` + `lang/*/bank.php` — dodano typy `contract_deposit` i `contract_deposit_refund`, księgowane w puli bankowej.
- `templates/views/contracts/main.php` + `templates/views/admin/contracts/main.php` — gracz i admin widzą kwotę oraz status kaucji.
- `tests/Integration/ContractServiceTest.php` — dodano regresje dla pobrania kaucji, blokady przy braku środków, savepointu przy zewnętrznej transakcji, pełnego zwrotu i częściowego zwrotu.

### 2026-07-07 - Kontrakty długoterminowe: Etap 2 reputacji w panelu admina

**Domknięto adminowy podgląd i korektę reputacji kontraktowej.**

- `admin/contracts.php` — dodano zakładkę `reputation`, ładowanie listy reputacji graczy, historii `contract_reputation_log` oraz akcję ręcznej korekty przez `ContractReputationService::adminAdjustScore()`. Każda korekta jest zapisywana w `AdminLog`.
- `src/ContractReputationService.php` — dodano metody adminowe `listScores()`, `recentLogs()` i `adminAdjustScore()`, bez obchodzenia istniejącego dziennika reputacji.
- `templates/views/admin/contracts/main.php` + `assets/css/admin.css` — dodano widok bez tabel HTML: lista graczy, statystyki wykonania, filtr, historia zmian i formularz korekty z modalnym potwierdzeniem.
- `lang/pl/admin/contracts.php` + `lang/en/admin/contracts.php` — dodano komplet kluczy dla zakładki reputacji.
- `tests/Integration/ContractReputationServiceTest.php` — dodano regresję dla listy admina, wyszukiwania, ręcznej korekty i filtrowania historii.

### 2026-07-07 - Kontrakty długoterminowe: poprawki po code review reputacji

**Naprawiono błędy znalezione po wdrożeniu reputacji kontraktowej.**

- `src/ContractService.php` — kara kontraktowa nie blokuje już przejścia kontraktu w `missed/failed`, gdy gracz nie ma środków. System zapisuje dostawę, status i reputację, a w logu/metadanych zapisuje pełną karę oraz realnie pobraną kwotę.
- `src/ContractService.php` — `processOneDueContract()` obsługuje teraz zewnętrzną transakcję przez savepoint; nie robi już bezwarunkowego `beginTransaction()` i nie zamyka transakcji wywołującego.
- `src/ContractReputationService.php` — reputacja korzysta z podpisanego snapshotu `player_contracts.terms_json`, a nie z aktualnie edytowanych `contract_terms`; fallback do bieżących warunków został tylko dla starych kontraktów bez snapshotu.
- `tests/Integration/ContractReputationServiceTest.php` — dodano regresję dla `partial` oraz snapshotu po zmianie warunku przez admina.
- `tests/Integration/ContractTickTest.php` — dodano regresję dla nieściągalnej kary i wywołania ticka wewnątrz zewnętrznej transakcji.

### 2026-07-06 - Kontrakty długoterminowe: Etap 1 rozszerzenia — reputacja kontraktowa

**Dodano osobny wskaźnik reputacji kontraktowej 0-100, niezależny od `company_credibility`.**

- `src/Contracts/ContractSchema.php` — dodano tabele `contract_reputation` i `contract_reputation_log` dla MySQL oraz SQLite; seed domyślnych kontraktów dostał warunki reputacyjne (`min_contract_reputation`, zyski za dostawy i ukończenie, straty za braki/anulowanie/niepowodzenie).
- `src/ContractReputationService.php` — nowy serwis reputacji: `getScore`, `ensureRow`, `changeScore`, zdarzenia dostawy, ukończenia, porażki i anulowania. Wynik jest przycinany do zakresu 0-100, a każda zmiana trafia do logu.
- `src/ContractService.php` — podpisanie kontraktu sprawdza teraz `min_contract_reputation`; tick kontraktów aktualizuje reputację przy dostawie, częściowej/pominiętej dostawie, ukończeniu i porażce; anulowanie kontraktu obniża reputację w tej samej transakcji.
- `templates/views/contracts/main.php` + `lang/pl/contracts.php` + `lang/en/contracts.php` — dodano komunikat blokady dla zbyt niskiej reputacji kontraktowej.
- `tests/Integration/ContractReputationServiceTest.php` — 6 testów regresyjnych: domyślny wynik, clamp i logi, wymaganie reputacji przy podpisaniu, sukces/perfect, miss/failure i anulowanie.

Walidacja: lint PHP dla nowych/zmienionych plików, `ContractReputationServiceTest`, `ContractServiceTest`, `ContractTickTest`, encoding check dla 8 plików zmiany. Znany szum lokalny: PHP 8.5 pokazuje deprecation `PDO::sqliteCreateFunction()` w `tests/Integration/SqliteIntegrationTestCase.php`; nie jest związane z tym etapem.

### 2026-07-06 - Kontrakty długoterminowe P1: Etap 5 (UI gracza) + Etap 6 (panel admina)

**Kontrakty dostały pełny interfejs gracza i panel administracyjny — moduł jest teraz obsługiwalny end-to-end.**

Etap 5 — UI gracza:
- `public/contracts.php` — endpoint strony: `Auth::requireLogin`, `RateLimiter::check('action')`, `CSRF::validateToken`; akcje `accept_contract` i `cancel_contract` (przez `ContractService::acceptContract`/`cancelContract`), komunikaty z `message_key` przez `tPlain`.
- `templates/views/contracts/main.php` — cztery sekcje: dostępne kontrakty (z warunkami, szac. przychodem i blokadami wiarygodność/dział prawny), aktywne kontrakty (pasek postępu `--bar-w`, anulowanie), historia dostaw, logi. Bez tabel HTML, bez inline style (poza dynamicznym `--bar-w`), potwierdzenia przez `data-confirm` z `modal.js`.
- `assets/css/contracts.css` — style widoku (grid kart, listy, pasek postępu), zmienne motywu z fallbackami, responsywność ≤640px.
- `assets/js/contracts.js` — tylko toast po akcji (flash); potwierdzenia podpisania/anulowania obsługuje globalny handler `data-confirm` z `modal.js`.
- `lang/pl/contracts.php` + `lang/en/contracts.php` — komplet kluczy UI gracza (sekcje, pola warunków, statusy, tryby ceny, zdarzenia logów, jednostki).

Etap 6 — panel admina:
- `admin/contracts.php` — panel z zakładkami `options / terms / active / deliveries / logs / help`; `AdminAuth::requireLogin`, `CSRF` na każdej akcji, `AdminLog::log` na każdej mutacji. Akcje: przełącz moduł, dodaj/edytuj opcję, włącz/wyłącz opcję (**is_active = 0, bez fizycznego DELETE**), dodaj/edytuj/usuń warunek (upsert po `contract_option_id, term_key`).
- `templates/views/admin/contracts/main.php` — listy i formularze opcji/warunków, podgląd aktywnych kontraktów, dostaw i logów, zakładka pomocy. Operacje destrukcyjne (wyłączenie modułu/opcji, usunięcie warunku) przez `data-confirm`.
- `assets/css/admin.css` — dodano blok `.contracts-admin-grid`/`.contracts-admin-row--{options,terms,active,deliveries,logs}` (wzorzec jak `protection-admin-*`).
- `lang/pl/admin/contracts.php` + `lang/en/admin/contracts.php` — komplet kluczy panelu (auto-ładowane przez `lang/pl/admin.php` glob).
- `tests/Integration/ContractsModuleTest.php` bez zmian; szablony zweryfikowane render-testem (wszystkie zakładki + tryb edycji, zero warningów PHP).

Routing: strona gracza dostępna pod czystym adresem `/contracts` (`RewriteRule ^contracts$ /public/contracts.php` w `.htaccess` + wpis `contracts => /contracts` w `ROUTES` w `src/init.php`); `url('contracts')` zwraca `/contracts`. Backup przed edycją: `backups/2026-07-06_15-45-52_htaccess.bak` i `..._init.php.bak`.

Zasady spełnione: pieniądze wyłącznie przez `FinancialTransactionService`, MVP tylko `storage` + `storage_oil_delivery`, ropa pobierana dopiero przy dostawie, komentarze dwujęzyczne bez polskich znaków. UWAGA: kafelek nawigacyjny w pulpicie gracza (GameShell action grid) celowo poza zakresem tych etapów — do dodania osobno; strona jest już osiągalna pod `/contracts`.

### 2026-07-06 - Kontrakty długoterminowe P1: Etap 4 — ContractsModule (TickModule + wiring)

**Kontrakty wpięte w cykl tikowy gry: automatyczne rozliczanie dostaw co ~5 minut.**

- `src/Tick/Modules/ContractsModule.php` — nowy TickModule (key=`contracts`, order=45); wywołuje `ContractService::processDueContracts($ctx->newPrice)` i loguje wynik przez `GameLog::info`.
- `cron/tick.php` — sekcja 10 (po szkoleniach): `ContractsModule` pobierany przez `TickRegistry::find('contracts')`; `$tickCtx` przeniesiony przed try-blok sekcji 7, żeby oba moduły (Credibility, Contracts) dzieliły ten sam kontekst.
- Moduł jest domyślnie wyciszony gdy `contracts_module_enabled = 0` (`ContractService::processDueContracts` wraca od razu z pustymi statystykami).
- Statystyki modułu (`processed/completed/failed/revenue/penalties`) mergowane do `$tickCtx` i dostępne przez `TickContext::collectStats()`.

### 2026-07-06 - Kontrakty długoterminowe P1: poprawki po code review Etapu 3 (HIGH + MEDIUM + LOW)

**Naprawiono trzy bugi znalezione przez code review implementacji processDueContracts.**

- **HIGH** — `src/ContractService.php`: `FTS::credit()` i `FTS::debitCombined()` zwracają `['success'=>bool]`, a nie rzucają wyjątkiem — teraz sprawdzamy wynik i rzucamy `RuntimeException` przy niepowodzeniu, żeby zewnętrzna transakcja wycofała się. Bez tej poprawki magazyn traciłby ropę i `delivered_bbl` rosło bez zaksięgowania wpłaty.
- **MEDIUM** — `src/ContractService.php`: `processDueContracts` przyjmował `\DateTime $now` (PHP-clock) zamiast używać MySQL-clock (`nowString()`). Usunięto parametr `$now`; `processOneDueContract` przyjmuje teraz `string $nowStr`. Eliminuje skew między PHP a MySQL przy porównaniu z `next_delivery_at` zapisanym przez `NOW()` (reguła #14).
- **LOW** — `src/ContractService.php`: `$processed++` było inkrementowane dla kontraktów ze statusem `skipped` (concurrent-lock guard) — wynik `processDueContracts` raportował nieprawdziwe liczby przy nakładających się tickach. Dodano guard `if ($r['new_status'] !== 'skipped')`.
- `tests/Integration/ContractTickTest.php` — sygnatura `processDueContracts(100.0)` (bez `$now`); `ends_at` testów niebędących testem niepowodzenia ustawione na `2099-12-31`; `next_delivery_at` testu braku due ustawione na `2099-12-31` (was: `2025-06-01 18:00:00` — byłby due z zegarem real-time); `testNextDeliveryAtAdvancesAfterTick` przepisany na `time()`-relative check (±5 s tolerance).

### 2026-07-06 - Kontrakty długoterminowe P1: poprawki po code review (MEDIUM + 2×LOW)

**Naprawiono trzy bugi znalezione przez code review fundament P1 kontraktów.**

- **MEDIUM** — `src/Contracts/ContractQueryTrait.php`: `nowString()` teraz pobiera `NOW()` z MySQL zamiast PHP `date()` — eliminuje skew stref PHP vs MySQL przy zapisach `starts_at`/`next_delivery_at`/`ends_at`/`created_at` (reguła #14). SQLite (testy) bez zmian — zegar PHP jest tam spójny.
- **LOW** — `src/ContractService.php`: `max_active_per_player = 0` traktowane jako „bez limitu" (`$maxActive > 0 && count >= $maxActive`) zamiast efektywnego minimum 1 przez `max(1, ...)`. Admin może teraz wyłączyć limit przez ustawienie 0.
- **LOW** — `src/Contracts/ContractSchema.php`: `ensure()` wywołany wewnątrz otwartej transakcji zamiast rzucać `RuntimeException` cicho wraca (`return`) — DDL i tak nie może działać w transakcji MySQL, a `ContractService::__construct` jest bezpieczniej wywoływać z dowolnego miejsca.

### 2026-07-06 - Kontrakty długoterminowe P1: Etap 3 — rozliczanie dostaw (processDueContracts)

**Silnik tikowy kontraktów: pobieranie ropy z magazynu, finansowe rozliczenie, historia dostaw.**

- `src/ContractService.php` — `processDueContracts(\DateTime $now, float $marketPrice)`: iteruje po aktywnych kontraktach z `next_delivery_at <= now`, przetwarza każdy w `processOneDueContract` i zwraca zagregowane statystyki (`processed/completed/failed/revenue/penalties`).
- `processOneDueContract`: pojedyncza transakcja z blokadą `FOR UPDATE` na graczu i magazynie; dedukcja ropy (`GREATEST(0, used - bbl)`); FTS `credit(contract_sale)` i `debitCombined(contract_penalty)` gdy niezerowe; INSERT do `contract_deliveries` (due_at = stary termin, status: `delivered/partial/missed`); UPDATE `player_contracts`: `delivered_bbl`, `missed_bbl`, `next_delivery_at` + `datePlusMinutes(now, interval)`, status (`active`→`completed`/`failed`).
- `src/Contracts/ContractQueryTrait.php` — dodano `calculatePrice(terms, marketPrice)`: obsługuje tryby `fixed`, `market_multiplier` i `market_plus_bonus` na podstawie snapshottu `terms_json`.
- `tests/Integration/ContractTickTest.php` — 10 testów integracyjnych: pelna/czesciowa/pominięta dostawa, zakończenie i niepowodzenie kontraktu, wpis `contract_deliveries`, log zdarzenia, przesunięcie `next_delivery_at`.
- Blokady `FOR UPDATE` pominięte dla SQLite (driver guard); SQLite otrzymuje `GREATEST`/`NOW()` przez custom functions.

### 2026-07-06 - Kontrakty długoterminowe P1: Etap 2 — finanse kontraktów (FTS + WalletConfig + lang)

**Trzy nowe typy FTS, routing do puli bankowej i klucze językowe niezbędne do rozliczania dostaw.**

- `src/FinancialTransactionService.php` — dodano stałe `TYPE_CONTRACT_SALE`, `TYPE_CONTRACT_PENALTY`, `TYPE_CONTRACT_BONUS`; wpisane do `ALLOWED_TYPES`.
- `src/WalletConfig.php` — wszystkie trzy typy kontraktowe zmapowane na `POOL_BANK`; przychód i kara trafiają na `bank_balance`, nie na gotówkę.
- `lang/pl/bank.php` + `lang/en/bank.php` — klucze `bank.account.type.contract_*` (etykiety historii) i `bank.tx_contract_*` (opisy transakcji z placeholderem `#:id`).
- `tests/Integration/ContractFinancesTest.php` — 11 testów: stałe FTS, routing pul, `credit` → `bank_balance`, `debitCombined` bank-first, polskie klucze lang.

### 2026-07-06 - Tick runda 5: etap L (L1-L8) — drobne bugi brzegowe silnika ticku

**Ostatni, odłożony etap analizy rundy 5: 8 drobnych bugów (niski priorytet — nieoptymalne albo błędne w rzadkich/brzegowych sytuacjach, głównie po przerwie crona). Klasy L3/L4/L7 to dokładnie reguły #13 (deltaHours) i #14 (timezone) skodyfikowane w CLAUDE.md.**

- **L1** — `src/Tick/WellProductionHandler.php`: reset bufora drogowego po wysyłce ciężarówek zmieniony z `SET road_buffer_bbl = 0` na atomowy `GREATEST(0, road_buffer_bbl - :wyslane)` — nakładający się tick (ADMIN_FORCE_TICK) nie kasuje ropy dodanej w międzyczasie (wzorzec jak dla bufora morskiego).
- **L2** — `src/Tick/MarineDeliverySection.php`: licznik kolejki portu przy przyjmowaniu dostawy liczony **per-gracz** (`pq.player_id = ?`), nie wspólnie dla całego portu. Gracz z pełnym magazynem trzymał wpisy `waiting`, które zajmowały wspólny `queue_limit` i wpychały dostawy innych graczy w `delayed` — jeden gracz głodził port całemu regionowi. (Drenowanie bez zmian: `refreshPortStatuses` nigdy nie ustawia `closed`, a `overloaded` nie blokuje.)
- **L3** — `src/MarineDeliveryService.php`: `departure_at`/`eta_at`/`created_at` zapisywane na zegarze MySQL (`NOW()`/`DATE_ADD`), nie z PHP `time()` — `purgeStale` i filtry porównują z `NOW()`, więc zapis z PHP przesuwał okna czyszczenia przy różnicy stref (reguła #14).
- **L4** — `src/BlackMarketService.php` + `cron/tick.php`: płaski decay `black_market_score` skalowany systemowym `deltaHours` (1 tick = 5 min); po przerwie crona catch-up tick odejmuje równowartość wielu godzin, nie jeden krok (reguła #13). Normalny tick 5-min bez zmiany zachowania.
- **L5** — `src/LoanDecisionService.php`: `processApplication` atomowo „zaklepuje" wniosek (warunkowy `UPDATE ... decision_at = +1h WHERE status='pending' AND decision_at <= NOW()` + `rowCount`) — tick (`BankSection`) i osobny cron (`cron/process_loan_decisions.php`) nie przetwarzają już tego samego wniosku podwójnie. Bez zmiany schematu (enum statusu nie ma stanu `processing`).
- **L6** — `src/TTS/TasksTrait.php`: `pipeline_maintenance`/`pipeline_inspection`/`pipeline_repair` ustawiają sukces i komunikat „strata zmniejszona" tylko po `rowCount > 0`. Gdy rurociąg zniknął (sprzedany) zadanie kończy się komunikatem `pipeline_gone` (nowe klucze i18n PL+EN) zamiast fałszywego sukcesu.
- **L7** — `src/RegionalEvent/EventsTrait.php`: `tickChance` clampowany do `min(1.0, ...)` — długi catch-up tick (`deltaHours > 24`) nie gwarantuje już zdarzenia w każdym regionie (reguła #13).
- **L8** — `src/Tick/WellHubSection.php`: incydent huba liczy `extra_loss` od faktycznie dostarczonej ropy — leg wylotowy uruchamiany PRZED incydentem, a baza straty pomniejszona o nadmiar wracający do bufora (throttling przepustowości). Wcześniej lekko przeszacowywał stratę, gdy incydent i throttling wypadły w tym samym ticku.

Testy: pełny zestaw Unit+Integration (SQLite) zielony; ścieżki MySQL (L2/L3/L5) weryfikowane przez CI `php-tests.yml` na żywej bazie MySQL 8 przy pushu.

### 2026-07-06 - Kontrakty długoterminowe P1: fundament danych i serwisu

**Dodano bezpieczny fundament kontraktów długoterminowych bez podpinania do ticka, UI, finansów ani logistyki. Ten etap przygotowuje moduł pod późniejsze rozliczanie dostaw ropy z magazynu.**

- `src/Contracts/ContractSchema.php` - idempotentny schemat 5 tabel: `contract_options`, `contract_terms`, `player_contracts`, `contract_deliveries`, `contract_logs`.
- `src/ContractService.php` - serwis P1 do włączania modułu, pobierania ofert, podpisywania i anulowania kontraktów; bez efektów finansowych i bez zmian w produkcji.
- `src/Contracts/ContractQueryTrait.php` - listy kontraktów/dostaw/logów oraz prywatne helpery serwisu; główny plik `ContractService.php` zostaje krótki i mieści się w standardzie podziału plików.
- Seed P1 dodaje 3 domyślne kontrakty magazynowe: lokalna rafineria, sieć paliwowa i koncern przemysłowy.
- Moduł jest domyślnie wyłączony przez `well_config.contracts_module_enabled`; można go włączyć bez zmiany kodu.
- Walidacja P1 obejmuje: aktywność oferty, kontekst `storage_oil_delivery`, minimalną wiarygodność firmy, poziom działu prawnego, wymagane parametry kontraktu i limit aktywnych kontraktów gracza.
- Po code review dodano ochronę przed równoległym podpisaniem kontraktów: serwis blokuje wiersz gracza i sprawdza limit aktywnych kontraktów w tej samej transakcji.
- `acceptContract()` i `cancelContract()` respektują zewnętrzne transakcje - nie commitują ani nie rollbackują transakcji otwartej przez caller.
- Walidacja odrzuca nieistniejącego gracza, nie zwraca cache'owanych ofert i używa spójnego czasu PHP do sprawdzania `expires_at`.
- Seed dużego kontraktu poprawiono tak, żeby harmonogram dostaw domykał pełny wolumen bez resztówki.
- `lang/pl/contracts.php`, `lang/en/contracts.php`, `lang/pl.php`, `lang/en.php` - dodano tłumaczenia `contracts.*`, żeby przyszłe UI/API nie pokazywało surowych kluczy.
- `tests/Integration/ContractServiceTest.php` - testy schematu, seeda, flagi modułu, listy ofert, podpisania, brakującego gracza, blokad wiarygodności/prawnego, limitu, transakcji zewnętrznych i anulowania.

### 2026-07-06 - Tick engine: podpięcie CredibilityModule przez registry

**Pierwszy realny krok migracji ticka na moduły: tylko sekcja wiarygodności firmy działa teraz przez `TickRegistry`, bez zmiany kolejności pozostałych sekcji.**

- `cron/tick.php` - sekcja `WIARYGODNOSC FIRMY` pobiera `CredibilityModule` przez `TickRegistry::find('credibility')`.
- `CredibilityModule` nadal używa istniejącego `CredibilitySection`, więc logika biznesowa wiarygodności nie została przepisana.
- Przed zmianą wykonano backup: `backups/2026-07-06_02-12-45_tick.php.bak`.

### 2026-07-06 - Tick engine: fundament modularizacji bez zmiany działania

**Dodano warstwę przygotowującą tick do przyszłych modułów, bez podpinania jej do `cron/tick.php`. Obecna kolejność ticka i zachowanie gry pozostają bez zmian.**

- `src/Tick/TickModule.php` - wspólny kontrakt modułu ticka (`key`, `order`, `run`, `stats`).
- `src/Tick/TickContext.php` - wspólny kontekst ticka: połączenie DB, czas, źródło, cena ropy, trend, mnożniki i statystyki modułów.
- `src/Tick/TickRegistry.php` - loader modułów z `src/Tick/Modules/`, z deterministycznym sortowaniem po `order()` i `key()`.
- `src/Tick/Modules/CredibilityModule.php` - cienki adapter na istniejący `CredibilitySection`; logika biznesowa nie została przeniesiona ani skopiowana.
- `tests/Unit/TickRegistryTest.php` - testy odkrywania modułów, sortowania i filtrowania po kluczach.

### 2026-07-05 - Tick runda 5: analiza i naprawa bugów silnika ticku (12 bugów, każdy z testem)

**Głęboka analiza całego systemu ticku (6 równoległych finderów + weryfikacja każdego znaleziska w kodzie) i naprawa potwierdzonych bugów w 4 etapach. Każdy fix z testem regresyjnym; bez zmian schematu produkcyjnego DB. Testy: 490 zielonych.**

Etap 1 — krytyczne pieniądze/ropa:
- **C1 (krytyczny)** — `src/Tick/WellRiskHandler.php`: koszt incydentu produkcyjnego (linia ~199) i katastrofy przemysłowej (~122) trafia teraz do `loopCtx->totalCosts`, a nie tylko do `finIncident`/`playerCash`. Realny zapis salda to `saveCashAndTick`: `cash = GREATEST(0, cash - totalCosts)` — bez tego katastrofy (blowout, pożar, kary środowiskowe) i awarie były **finansowo darmowe** (regresja po fixie C3 z rundy 4).
- **H1 (wysoki)** — `src/BailiffService.php`: komornik oznacza pożyczkę `paid_off` gdy zajęcie gotówki/ropy pokryje dług (`markLoanPaidIfCleared`). Wcześniej status zostawał `late` na zawsze (`processInstallments` obsługuje tylko `active`) → eskalacja do zajęcia odwiertów i bankructwa mimo zerowego salda.
- **M1 (średni)** — `src/BailiffService.php`: etap 2 zajęcia z `GREATEST(0, remaining_amount - ?)` (jak etap 3) — brak ujemnego salda.
- **H2 (wysoki)** — `src/Tick/WellProductionHandler.php`: zdarzenie transportowe `leak` skaluje się do bieżącej wysyłki (`$actual`), nie do całego magazynu — wcześniej pojedynczy wyciek niszczył 10-20% CAŁEGO zbiornika.

Etap 2 — M-bugi:
- **M2** — `src/BlackMarketService.php`: wygasanie ofert liczone po stronie MySQL (`DATE_ADD(NOW(), INTERVAL ...)`) zamiast z PHP `time()` — koniec rozjazdu stref czasowych.
- **M5** — `src/Tick/MarineDeliverySection.php`: ETA sprawdzane PRZED rzutem incydentu; rejs po ETA nie traci ładunku na catch-up ticku po przerwie crona.
- **M6** — `src/RoadTransportService.php`: kurs `delayed` finalizowany przez atomowy stan `crediting` (potwierdzany z zapisem magazynu), nie bezpośrednio `delivered` — crash nie gubi opłaconej ropy.
- **M8** — `src/Incident/TickTrait.php`: licznik `ticks_since_incident` (odporność + presja) skaluje się deltaHours (1 tick = 5 min), nie stałe +1/uruchomienie crona — koniec „darmowej" odporności po awarii crona.

Decyzje GM (balans):
- **M7** — `src/Incident/RepairDataTrait.php`: incydenty auto_repair (micro/minor) działają tylko w ticku wystąpienia — `getOngoingProdDrop`/`getOngoingProdDropForPlayer` filtrują `AND auto_repair = 0`. Trwający spadek zostaje dla medium/major.
- **H3** — `src/OutboundLegService.php`: uszkodzony/wyłączony rurociąg leg-2 (hub→magazyn) zatrzymuje przepływ — ropa czeka w buforze huba (kind `blocked`, `excess_bbl`=całość), zamiast lecieć za darmo/bezstratnie/bez limitu. Spójnie z leg-1.
- **M3** — `src/Tick/WellHubSection.php`: ropa wydrenowana z bufora huba, która nie mieści się w pełnym magazynie, wraca do bufora zamiast być niszczona jako strata (jak ścieżka outbound). Tylko nadmiar ponad pojemność bufora = strata.
- **M4 część A** — `src/Tick/WellRoadTripSection.php` + `src/Tick/PlayersSection.php`: ropa dostarczana ciężarówkami dla odwiertu przypisanego do huba nie jest capowana magazynem — idzie pełna do bufora huba (jak produkcja rurociągowa). Wcześniej przy pełnym magazynie ginęła jako „overflow" zanim dotarła do huba. Odwierty bez huba nadal capowane.

Testy (nowe pliki): `tests/Integration/TickBugfixRound5Test.php` (C1, H2), `tests/MySqlIntegration/MySqlBailiffSeizureClearsLoanTest.php` (H1, M1), `MySqlIncidentImmunityDeltaHoursTest.php` (M8), `MySqlIncidentOngoingDropAutoRepairTest.php` (M7), `MySqlHubStorageBlockedRebufferTest.php` (M3, bilans baryłek), `MySqlRoadHubDeliveryTest.php` (M4-A, bilans baryłek) + rozszerzenia `MySqlRoadTransportServiceTest`, `MySqlMarineDeliverySectionTest`, `OutboundLegServiceTest`, `BugfixRound4Test`.

### 2026-07-03 - GM: pełny reset gry (czystka wszystkich kont, danych i logów)

**Nowa funkcja „PEŁNY RESET GRY" w panelu Game Mastera — kasuje całkowicie wszystkie konta graczy i wszystkie powiązane dane (łącznie ze wszystkimi logami), żeby uruchomić grę od zera.**

- `admin/gm_tools.php` — akcja `full_wipe`: enumeruje realne tabele przez `information_schema.TABLES`, odejmuje allowlistę 34 tabel konfiguracyjnych / kont adminów, resztę czyści przez `TRUNCATE` (ID startują od 1) z fallbackiem na `DELETE` dla tabel z FK; `FOREIGN_KEY_CHECKS` wyłączane na czas operacji.
- Wyczyszczone: `players` + wszystkie dane per-gracz (odwierty, rurociągi, huby, finanse, pożyczki, staff, rynek, transport) oraz wszystkie logi/zdarzenia (`admin_logs`, `finance_logs`, `failure_log`, `well_events`, `*_incident_logs`, `tick_stats`, itd.).
- Zachowane: konta i uwierzytelnianie adminów, konfiguracja gry, regiony/lokacje, warstwy geologiczne, słowniki HR, porty, katalog `wells_for_sale`, `market_state`/`market_trends`, strony pomocy.
- Zabezpieczenia: wymagana ręcznie wpisana fraza `KASUJ WSZYSTKO` (walidacja serwer + klient), podwójny confirm (`confirmAction`), CSRF, wpis audytu przez `AdminLog::log` po czyszczeniu.
- `templates/views/admin/gm_tools/main.php` — panel danger; `lang/pl/admin/gm.php`, `lang/en/admin/gm.php` — klucze i18n `admin.gm.wipe_*`.

### 2026-07-03 - Tick rundy 3-4: code-review i naprawa bugów odwiertów/rurociągów/hubów/transportu

**Dwie rundy code-review (findery × weryfikatory) newralgicznych ścieżek: produkcja odwiertów, rurociągi, huby, transport morski/drogowy, incydenty. Łącznie 48 potwierdzonych bugów naprawionych + 6 optymalizacji wydajności. Migracje `migrations/tick_bugfix_round3.sql` i `tick_bugfix_round4.sql` (idempotentne).**

- Runda 3 (19 bugów): m.in. IDOR w `getHubDetail`, bugi duplikacji/utraty gotówki i ropy, mechaniki rdzenia, martwy kod UI.
- Runda 4 (29 bugów + perf): podwójna utrata ropy przy spillu, darmowe awarie mechaniczne, wskrzeszanie rurociągów przez suspend/resume, precyzja `condition_pct` (DECIMAL 8,4), uszkodzony rurociąg leg-1 zatrzymuje produkcję zamiast cicho jeździć ciężarówkami, `purchasePipeline` odrzuca `leg=outbound`, ciche kasowanie rejsów morskich → status `lost`, atomowy dispatch morski, potrójna opłata offshore, port `closed` wznawia się automatycznie, ręczna naprawa incydentów, egzekwowanie czasu trwania incydentu, zużycie sprzętu do kwadratu, ryzyko polityczne leg-2. Perf: eliminacja N+1 (ongoingDropCache, memoizacja portów/incydentów hubów).
- Migracja `tick_bugfix_round4.sql`: `well_pipelines.condition_pct` → DECIMAL(8,4); `wells.ticks_since_incident` DEFAULT 999 → 0 + data-fix; refund + usunięcie martwych wierszy `leg='outbound'`; przeliczenie placeholderów `real_capacity_bph`.

### 2026-06-14 - Lang: ekstrakcja hardkodowanych tekstów (incydenty, bank, finanse)

**Wszystkie hardkodowane polskie ciągi w panelu admina przeszły przez system lang** — nowe powiadomienie = jeden nowy klucz w `lang/pl/admin/*.php`, zero zmiany w logice.

- `admin/incidents.php` — 8 miejsc z hardkodowanym tekstem zastąpiono `tPlain()`:
  - etykiety konfiguracji DB (rurociągi, retencja, odporność, ciśnienie) → klucze `cfg_pipe_db_label`, `cfg_retention_db_label`, `cfg_immunity_db_label`, `cfg_pressure_growth_db_label`, `cfg_pressure_cap_db_label`
  - komunikaty wyzwalanych incydentów odwiertu i rurociągu → `log_well_trigger`, `log_pipe_trigger` (z parametrami `:level`, `:well`/`:pipeline`, `:drop`/`:loss`)
  - wyjątki „brak odwiertu/rurociągu" → `err_well_missing`, `err_pipe_missing`
  - błąd pobierania historii → `err_history`
- `admin/incidents.php` (SQL) — kolumna `message` dla wierszy morskich zmieniona z `CONCAT('Dostawa morska …')` (hardkod SQL) na `CAST(NULL AS CHAR) AS message`; tytuł wiersza budowany w PHP z `tPlain('admin.incidents.marine_history_msg')`.
- `admin/bank_status.php` — 3 komunikaty błędów JSON (`err_no_pid`, `err_old_schema`, `err_no_table`) → `tPlain()`.
- `admin/partials/finance_admin_actions.php` — `adminFinanceMultiplierLabels()` i etykiety `save_config` przez `tPlain()` zamiast hardkodowanych stringów.
- `lang/pl/admin/incidents.php` — 11 nowych kluczy.
- `lang/pl/admin/loans.php` — 3 nowe klucze (`admin.bank_status.*`).
- `lang/pl/admin/finance.php` — 18 nowych kluczy (`admin.finance.mult_sp_*`) dla etykiet mnożników planu oszczędności.

### 2026-06-14 - Bank: konfigurowalny limit przelewu + tryb bez limitu

**Limit przelewu portfel ↔ konto stał się konfigurowalny z panelu admina i obsługuje tryb „bez limitu" (wartość 0).**

- `src/WalletConfig.php` — `getTransferMax(PDO)` rozróżnia: brak klucza w DB → fallback 500 000 PLN; klucz = 0 → brak limitu (zwraca `0.0`); klucz > 0 → limit kwotowy.
- `src/CashTransferService.php` — `validateAmount()` pomija sprawdzenie maksimum gdy `$maxAmount === 0.0` (tryb bez limitu).
- `admin/loans.php` — handler `update_transfer_limit` (POST), zapis do `well_config` przez prepared statement; przeniesiony z `admin/bank.php`.
- `admin/bank.php` — usunięto handler i zmienne transfer limitu (były martwe po przeniesieniu do loans).
- `templates/views/admin/loans/main.php` — nowa sekcja „Limit przelewu portfel ↔ konto" z polem `min=0 step=1` (zero = brak limitu), modal potwierdzenia przez `confirmSubmit()`.
- `templates/views/admin/bank/main.php` — usunięto sekcję limitu (przeniesiona).

### 2026-06-12 - Ochrona P2: huby i rurociągi

**Hotfix po wdrożeniu P2** — domknięto błędy ochrony hubów/rurociągów:
- `public/protection.php` — ochrona huba może być wykupiona tylko przez właściciela huba. Najemca nie zapłaci już za ochronę, której tick nie zastosuje.
- `src/ProtectionService.php` — dodano `getActiveProtections()` do batchowego pobierania aktywnych ochron wraz z efektami.
- `public/logistics.php` — lista ochron transportu drogowego, hubów i rurociągów używa batchowych odczytów zamiast zapytania per element.
- `src/Tick/PipelineSection.php`, `src/HubIncidentService.php`, `src/Tick/WellHubSection.php` — tick prefetchuje ochrony hubów i rurociągów oraz loguje `protection_applied_to_incident` z prawidłowym `protection_option_id`.
- `assets/js/protection.js` — brak poprawnego celu w modalu pokazuje błąd zamiast cicho ignorować kliknięcie.
- Dodatkowy przegląd kodu: sekcja ochrony hubów pokazuje tylko huby własne, zgodnie z walidacją backendu; `WellPipelineService::getPlayerPipelines()` zwraca także rurociągi wylotowe `hub -> magazyn` (`well_id=0`) i oznacza je jako operacyjne, gdy hub działa.
- Testy: Unit 28/28, Integration 200/200, MySQL 118/118.

**Rozszerzenie uniwersalnego modułu ochrony na huby logistyczne i rurociągi** — ten sam silnik (`ProtectionService`), nowe cele. Gracz wykupuje ochronę huba lub rurociągu w panelu logistyki; aktywna ochrona zmniejsza ryzyko incydentów odpowiedniego typu. Architektura bez zmian — tylko nowe `target_type` + `context` i wpięcie efektów w istniejące silniki incydentów.

- `src/Protection/ProtectionSchema.php` — seed 2 nowych opcji: Ochrona huba (`hub_security`, 120 000 PLN/60 min, `target_type='hub'`, `context='hub_guard'`, mnożniki uszkodzenia sprzętu/wycieku/przeciążenia) i Monitoring rurociągu (`pipeline_monitor`, 30 000 PLN/h, 120 min, `target_type='pipeline'`, `context='pipeline_guard'`, mnożnik awarii). Seed uogólniony — `target_type`/`context` per opcja.
- `src/HubIncidentService.php` — `processTick()` przyjmuje `?ProtectionService`; mapa `PROTECTION_EFFECT_TO_TYPE` przekłada efekty na mnożniki per typ incydentu (`<typ>_risk_mult`), nakładane na szansę każdego typu osobno (silnik niezależnych szans — bez renormalizacji). Incydent pod ochroną → `protection_applied_to_incident`.
- `src/Tick/PipelineSection.php` — `rollPipelineIncident()` mnoży szansę wszystkich poziomów przez `pipeline_incident_risk_mult` aktywnej ochrony danego odcinka (`well_pipelines.id`).
- `src/Tick/WellHubSection.php` + `WellLoopSection.php` — przekazanie `ProtectionService` do finalizacji hubów (`finalizeHubTicks`).
- `src/Tick/PlayersSection.php` — jedna instancja `ProtectionService` na gracza, współdzielona przez rurociągi/huby/transport drogowy (wygasanie raz na gracza).
- `public/protection.php` — endpoint uogólniony: parametr `target` (road/hub/pipeline) + `target_id` z walidacją własności per typ (odwiert ciężarówkowy / hub właściciel-lub-najemca / rurociąg gracza).
- `public/logistics.php` + `templates/views/logistics/main.php` — sekcje „Ochrona hubów" i „Ochrona rurociągów" (reużywalna closure renderująca tabelę + modal dla dowolnego typu celu); generyczny formatter opisów efektów.
- `assets/js/protection.js` — generyczna obsługa 3 modali i typów celu (`data-target` + `data-target-id`).
- `lang/pl/protection.php` — klucze sekcji/kolumn/ryzyk per typ; disclaimer i błąd celu uogólnione.
- `tests/Integration/ProtectionServiceTest.php` — 2 nowe testy (seed hubów/rurociągów + aktywacja niezależnych celów). Integration 199/199, Unit 28/28.

### 2026-06-12 - Ochrona: domknięcie kontekstu, duplikatów i czasu DB

- `src/ProtectionService.php` — aktywacja może wymagać konkretnego `context` (P1: `road_transport_guard`), używa czasu bazy danych dla startu/końca/logów i zwraca czytelny błąd przy kolizji aktywnej ochrony.
- `src/Protection/ProtectionSchema.php` — dodano twardą blokadę jednej aktywnej ochrony na `player + target_type + target_id + context`; MySQL używa generowanej kolumny `active_guard`, SQLite częściowego unikalnego indeksu.
- `public/protection.php` — endpoint gracza wymusza kontekst `road_transport_guard`, więc POST-em nie da się uruchomić innego kontekstu dla transportu drogowego.
- `src/RoadTransportService.php` — historia `protection_applied_to_incident` zapisuje lekki agregat incydentów zamiast pełnej listy kursów.
- `admin/protection.php`, `templates/views/admin/protection/main.php` — panel jasno pokazuje, że P1 ochrony jest zawsze płacone gotówką; zapis wymusza `cash`.
- `tests/Integration/ProtectionServiceTest.php` — dodano testy blokady złego kontekstu i duplikatu aktywnej ochrony na poziomie schematu.

### 2026-06-12 - Ochrona: uniwersalny moduł + podpięcie transportu drogowego + panel admina

**Nowy, konfigurowalny moduł ochrony aktywów (`ProtectionService`) — opcje ochrony i ich efekty są definiowane w bazie i panelu admina, nie w kodzie.** Gracz wykupuje ochronę na cel (P1: odwiert z transportem ciężarówkami) na określony czas; aktywna ochrona zmniejsza ryzyko kradzieży, napadu i sabotażu kursów drogowych. Architektura gotowa pod kolejne cele (odwierty, huby, rurociągi) — moduł podaje tylko `target_type` + `context` i pyta o efekty. Brief: `BRIEF_UNIWERSALNY_MODUL_OCHRONY.md`.

- `src/Protection/ProtectionSchema.php` (NOWY) — 4 tabele (`protection_options`, `protection_effects`, `active_protections`, `protection_logs`) MySQL+SQLite, idempotentny seed 3 opcji P1: Eskorta podstawowa (75 000 PLN/60 min), Konwój uzbrojony (500 000 PLN/60 min, wymaga działu prawnego 3+), Patrol dronami (50 000 PLN/h, 120 min).
- `src/ProtectionService.php` (NOWY) — silnik: `getAvailableOptions()` (z powodem blokady: wiarygodność/poziom prawny i flagą „stać/nie stać"), `quote()`, `activate()` (FTS debit + zapis + log + powiadomienie, w transakcji), `getActiveProtection()`/`getActiveEffects()` (mnożniki przycinane do [0.05, 1.0] — ochrona nigdy nie zeruje ryzyka), `applyEffects()`, `cancel()`. Wygasanie leniwe po `ends_at` (bez crona). Max 1 aktywna ochrona per gracz+cel+kontekst.
- `src/RoadTransportService.php` — efekty ochrony nakładane przy rozliczaniu kursów: mnożniki na wagach typów incydentów (`w'=w*mult`) + korekta łącznej szansy (`sum(w')/sum(w)`), więc typy niechronione (awaria, blokada trasy) zachowują dokładnie bazowe prawdopodobieństwa. Incydent pod ochroną → wpis `protection_applied_to_incident`.
- `src/Tick/PlayersSection.php` + `WellRoadTripSection.php` — przekazanie `ProtectionService` do rozliczania kursów (guarded).
- `src/FinancialTransactionService.php` — nowy typ `TYPE_PROTECTION = 'protection'`; `src/WalletConfig.php` — `protection` → `POOL_CASH` (P1 tylko gotówka).
- `public/protection.php` (NOWY) — endpoint AJAX aktywacji (CSRF, rate limit, walidacja własności odwiertu i transportu `ciezarowki`).
- `public/logistics.php` + `templates/views/logistics/main.php` — sekcja „Ochrona transportu drogowego": lista odwiertów ciężarówkowych z aktywną ochroną (do kiedy) lub przyciskiem „Dodaj ochronę"; modal wyboru z kosztem, czasem i opisowymi efektami (bez mnożników).
- `assets/js/protection.js`, `assets/css/protection.css` (NOWE) — modal + potwierdzenie `confirmAction` + toast.
- `admin/protection.php` + `templates/views/admin/protection/main.php` (NOWE) — 4 zakładki: Opcje ochrony (CRUD bez usuwania — tylko wyłączenie), Efekty (upsert/usuwanie per opcja), Aktywne ochrony (anulowanie bez zwrotu), Historia. Link w nav „Transport". `assets/js/admin_protection.js` — potwierdzenia przez `confirmSubmit`.
- `lang/pl/protection.php`, `lang/pl/admin/protection.php` (NOWE) + loader `lang/pl.php`, `lang/pl/admin/nav.php`.
- `tests/Integration/ProtectionServiceTest.php` (NOWY, 11 testów) + 2 testy matematyki wag w `RoadTransportServiceTest.php`. Integration 195/195.

### 2026-06-11 - Łapówki: uniwersalny moduł + wtyczka w dziale prawnym + panel admina

**Nowy, przenośny moduł łapówek (`BriberyService`) — „gniazdko", które łatwo podpiąć pod dowolny inny moduł gry.** Łapówka pozwala graczowi zapłacić gotówką, żeby załatwić coś po cichu — z ryzykiem wpadki i kosztem dla wiarygodności firmy. Silnik liczy cenę i ryzyko z reputacji firmy, pobiera gotówkę, losuje wynik (sukces/wpadka), księguje kary reputacji i wysyła powiadomienie — wszystko w jednej transakcji. Moduł, który chce łapówek, podaje tylko: koszt odniesienia, „co przy sukcesie" i (opcjonalnie) „co przy wpadce".

- `src/BriberyService.php` (NOWY) — silnik: `quote()` (wycena: koszt + ryzyko, bez ruchu środków) i `attempt()` (próba: pobranie gotówki, losowanie, kary, powiadomienie, transakcja).
- `src/Bribery/BriberyConfig.php` (NOWY) — konfiguracja w tabeli `bribery_config` (klucz-wartość, idempotentny seed), edytowalna z panelu: szanse wpadki i mnożniki ceny per poziom reputacji (critical/low/shaky/stable/high), bazowy % kosztu, kary reputacji (sukces/wpadka), dodatkowa blokada po wpadce.
- `src/Legal/BriberyTrait.php` (NOWY) — pierwsza wtyczka: `LegalService::bribePermit()` i `bribeQuote()`. Łapówka dostępna dla wniosków `pending`/`delayed` (przyspieszenie) oraz `refused` (ominięcie cooldownu). Sukces → nadaje zezwolenie; wpadka → odmowa + dłuższy cooldown (urząd się zawziął) + alert dyrektora + incydent w historii reputacji.
- `src/FinancialTransactionService.php` — nowy typ `TYPE_BRIBE = 'bribe'` (+ `ALLOWED_TYPES`).
- `src/WalletConfig.php` — `bribe` → `POOL_CASH` (łapówka jest zawsze gotówkowa; domyka „gotówkową fazę 3").
- `public/legal.php` + `templates/views/legal/main.php` + `_bribe_button.php` (NOWY partial) — przycisk „Załatw po cichu" przy odmowie i w trakcie, z kosztem i ryzykiem; obsługa akcji `bribe_permit`.
- `assets/js/legal.js` + `assets/css/legal.css` — modal potwierdzenia łapówki (typ `danger`, ostrzeżenie o reputacji).
- `admin/bribery.php` + `templates/views/admin/bribery/main.php` (NOWE) — pełna edycja parametrów z panelu + podgląd ostatnich prób łapówek. Link w nawigacji admina (sekcja „Dział prawny").
- `lang/pl/bribery.php`, `lang/pl/admin/bribery.php` (NOWE) + wpisy w `lang/pl/legal.php`, `lang/pl/bank.php`, `lang/pl/admin/nav.php`.
- `tests/Integration/BriberyServiceTest.php` (NOWY) — 5 testów: wycena (% + mnożnik), sukces (pobranie gotówki, lekka kara), wpadka (mocna kara + alert + incydent), brak środków, moduł wyłączony.
- `AGENTS.md` §26 — instrukcja „jak podpiąć łapówkę do dowolnego modułu w 3 krokach".

### 2026-06-10 - Dział prawny: emoji w powiadomieniach ticku zamienione na ikony SVG

**`src/Tick/LegalSection.php` — usunięto emoji Unicode z powiadomień o decyzjach (łamały zasadę „ZERO emoji").** Metoda `notifyHub()` wstawiała do `director_notifications.icon` surowe emoji (`✅❌⏳⚠️`), które trafiały do bazy i nie pasowały do mapy `dirNotifIconSvg()`. Wersja dla wierceń (`buildNotification`) używała pustych ikon (fallback na domyślny okrąg).

- Oba tory powiadomień (wiercenia + huby) używają teraz spójnych identyfikatorów SVG: `granted → check`, `refused → cross`, `delayed → alert`, `no_decision → warning`. Identyfikatory są renderowane przez `dirNotifIconSvg()` w `templates/components/director_notifications.php`.
- Aktualizacja AGENTS.md §24: zaznaczono, że rozpatrywanie wniosków przez tick (`LegalSection`) i blokada zakupu bez zezwolenia (`WorldMap::regionPurchaseBlock`) **są już wdrożone** — wcześniejszy wpis „Co NIE jest w P1" był nieaktualny.

### 2026-06-10 - Bankructwo: opcje ratunkowe przez centralne API finansowe (uzupełnienie Fazy 2)

**Trzy metody w `src/Bankruptcy/OptionsTrait.php` przepięte z bezpośredniego `UPDATE players SET cash` + `logTransaction()` na `FinancialTransactionService::credit()`.** Dotychczas `logTransaction()` dodawał tylko wpis w historii bez faktycznego ruchu przez FTS — routing do właściwej puli, walidacja i atomowość były omijane.

Pula docelowa dla tych operacji — zgodnie z `WalletConfig::TYPE_TO_POOL` — to **konto bankowe** (`bank_balance`):
- `TYPE_LOAN → POOL_BANK` (kredyt ratunkowy)
- `TYPE_BANKRUPTCY_EVENT → POOL_BANK` (cięcia kosztów, inwestor ratunkowy)

Zmiany:
- `applyEmergencyLoan()` — gotówka z kredytu ratunkowego ląduje na koncie bankowym. Pola game-state (`credit_score`, `recovery_mode`, `bankruptcy_status`) zostają w osobnym UPDATE (bez zmian semantyki).
- `applyCostCuts()` — ulga gotówkowa z cięcia kosztów ląduje na koncie bankowym. Oddzielony UPDATE game-state.
- `applyRescueInvestor()` — zastrzyk gotówki od inwestora ratunkowego ląduje na koncie bankowym. Oddzielony UPDATE game-state.
- We wszystkich: FTS budowany przed `beginTransaction()`; przy niepowodzeniu `credit()` — rollback + komunikat błędu.
- Konkurencja (`competitor_buyout` w `EventsTrait`) — **pozostawiona bez zmian** (clamping `GREATEST(0, cash - ?)` musi być zachowany).

### 2026-06-10 - TTS: koszty przez centralne API finansowe (uzupełnienie Fazy 2)

**Moduł techniczny (TTS) przepięty z bezpośredniego `UPDATE players SET cash` na `FinancialTransactionService::debit()`.** Wcześniej koszty TTS schodziły bezpośrednim UPDATE, a `logTransaction()` dorzucał tylko wpis w historii bez faktycznego ruchu przez FTS (routing i walidacja salda omijane). Pula się nie zmienia — `hr_fee` i `tts_fee` w `WalletConfig::TYPE_TO_POOL` → gotówka, tak jak dotychczas. Zysk: jeden spójny tor ruchu środków (walidacja salda + atomowy wpis w `bank_transactions`).

- `src/TTS/StaffTrait.php` — pierwsza pensja przy zatrudnieniu (`hireEngineer`) przez `debit(TYPE_HR_FEE)`. Przy braku środków: rollback + komunikat `no_funds`.
- `src/TTS/ProceduresTrait.php` — ulepszenie procedur BHP (`upgradeProcedures`) oraz przegląd/naprawa integralności (`repairProcedureIntegrity`) przez `debit(TYPE_TTS_FEE)`. Wcześniej `logTransaction` był wołany PO `commit()` (poza transakcją) — teraz ruch i wpis są w jednej transakcji.
- `src/TTS/TasksTrait.php` — koszt zadania technicznego (`startTask`) przez `debit(TYPE_TTS_FEE)`.
- We wszystkich: FTS budowany przed `beginTransaction()`, aby setup schematu nie był pominięty w otwartej transakcji.

### 2026-06-10 - Logistyka: koszt optymalizatora przez FTS + audit trail (uzupełnienie Fazy 2)

**Routing Fazy 2 (`WalletConfig::TYPE_TO_POOL`) jest już aktywny w `FinancialTransactionService::moveFunds()` od commita „Faza 2: aktywacja routingu pul portfela".** To uzupełnienie domyka jedyną ścieżkę kosztową, która omijała centralne API finansowe.

- `src/LogisticsService.php` — koszt uruchomienia optymalizatora transportu (1500–5000 PLN) schodził wcześniej bezpośrednim `UPDATE players SET cash`, bez wpisu w historii operacji. Teraz pobierany przez `FinancialTransactionService::debit(..., TYPE_LOGISTICS_FEE)`: gotówka schodzi tak samo, ale powstaje wpis w `bank_transactions`, więc koszt jest widoczny w historii. FTS budowany przed `beginTransaction()`, aby setup schematu nie został pominięty w otwartej transakcji; przy braku środków zwracany jest dotychczasowy komunikat błędu i rollback.
- `src/FinancialTransactionService.php` — nowy typ `TYPE_LOGISTICS_FEE = 'logistics_fee'` (dodany też do `ALLOWED_TYPES`).
- `src/WalletConfig.php` — `logistics_fee` → `POOL_CASH` w `TYPE_TO_POOL`; zaktualizowano nieaktualny komentarz nagłówka (routing Fazy 2 jest aktywny, nie „do przeniesienia").
- `lang/pl/bank.php` — etykieta `bank.account.type.logistics_fee` = „Optymalizacja transportu" (wyświetlana w historii operacji).
- `lang/pl/logistics.php` — opis transakcji `logistics.tx_optimize` = „Optymalizacja transportu (tryb: :mode)".
- `tests/Integration/FinancialTransactionServiceTest.php` — nowy test potwierdzający, że `logistics_fee` schodzi z gotówki, a konto bankowe pozostaje bez zmian.

### 2026-06-10 - Portfel: rozdzielenie gotówki i salda konta bankowego (Faza 1 — struktura + UI)

**Nowa architektura portfela gracza:**
- `src/WalletConfig.php` (NOWY) — centralny rejestr konfiguracji: nazwy pul (`POOL_CASH='cash'`, `POOL_BANK='bank_balance'`), limity transferu (min 100 PLN, max 500 000 PLN), prowizja (0,5%, min 10 PLN), podział startowy (50/50), mapa routingu fazy 2 (`TYPE_TO_POOL`). Jedyne miejsce do edycji wszystkich parametrów portfela.
- `src/WalletService.php` (NOWY) — surowe operacje DB: `getBalances()`, `transferBetweenPools()`, `initNewPlayer()`. Migracja schematu w `ensureSchema()`: dodaje kolumny `bank_balance` + `wallet_initialized`, jednorazowo dzieli `cash` 50/50 dla istniejących graczy.
- `src/CashTransferService.php` (NOWY) — logika biznesowa transferu gracza: walidacja kwoty, obliczenie prowizji, atomowy UPDATE (kwota+prowizja z puli źródłowej, kwota do puli docelowej), audit trail w `bank_transactions`. Metody: `cashToBank()`, `bankToCash()`, `calcFee()`.
- `public/wallet_transfer.php` (NOWY) — AJAX endpoint POST `/wallet-transfer`: autoryzacja + CSRF + wywołanie `CashTransferService`; zwraca JSON `{success, message, new_cash, new_bank, fee}`.
- `assets/js/wallet.js` (NOWY) — logika UI: podgląd prowizji przy wpisywaniu kwoty, potwierdzenie przez `confirmAction`, AJAX submit, aktualizacja sald w DOM bez przeładowania strony.
- `assets/css/wallet.css` (NOWY) — style sekcji portfela w banku: kafelki sald (gotówka/konto), formularze transferu, strzałki kierunkowe.

**Zmiany w istniejących plikach:**
- `src/FinancialTransactionService.php` — nowy typ `TYPE_POOL_TRANSFER = 'pool_transfer'` (audit trail transferów portfelowych).
- `src/GameShell.php` — naprawiono etykietę `$ USD` → `PLN`; dodano 5. KPI `index.bank_balance` z `bank_balance`; grid przechodzi przez `new WalletService()` aby zapewnić schemat.
- `assets/css/style.css` — `.status-grid--redesign`: `repeat(4, 1fr)` → `repeat(auto-fit, minmax(170px, 1fr))` — grid obsługuje teraz dowolną liczbę KPI.
- `src/Bank/DataLoader.php` — `loadAccountData()` używa teraz `WalletService::getBalances()`: `accountBalance` = `bank_balance` (saldo konta), `cashBalance` = `cash` (gotówka).
- `templates/views/bank/main.php` — nowa sekcja „Portfel" z kafelkami obu sald i formularzami transferu; konfiguracja `window.WALLET_API/CSRF/FEE_*/LANG` dla `wallet.js`.
- `public/bank.php` — ładuje `wallet.css` i `wallet.js`.
- `public/register.php` — nowi gracze: po starcie `WalletService::initNewPlayer()` dzieli 10 000 000 PLN 50/50 (5M gotówka, 5M konto).
- `lang/pl/bank.php` — klucze `wallet.*` (sekcja, przyciski, błędy, komunikaty).
- `lang/pl/director.php` — klucz `index.bank_balance` dla HUD.
- `src/init.php` — trasa `wallet-transfer` w ROUTES.
- `.htaccess` — reguła `^wallet-transfer$ → /public/wallet_transfer.php`.

**Faza 2 (routing) — AKTYWNA: `FinancialTransactionService::moveFunds()` czyta `WalletConfig::TYPE_TO_POOL` i kieruje przychody na konto bankowe, koszty na gotówkę. Faza 3 (5 zastosowań gotówki) — nadal zaplanowana w `CASH_ONLY_TYPES`, nieaktywna.**

### 2026-06-10 - Bank: negocjacje, restrukturyzacja i HR przez centralne API finansowe
- `src/FinancialTransactionService.php` - nowy typ `bank_fee` (opłaty bankowe, np. za negocjacje).
- `src/BankNegotiation/ProcessorTrait.php` - opłata dodatkowa za negocjacje z bankiem przechodzi przez `debit()` (typ `bank_fee`); rollback + komunikat gdy brak środków.
- `src/Bankruptcy/OptionsTrait.php` - wypłaty za sprzedaż odwiertu i magazynu w restrukturyzacji idą przez `credit()` (typ `bankruptcy_event`) zamiast `UPDATE` + osobny `logTransaction`; rollback gdy księgowanie się nie powiedzie.
- `src/HR/HiringTrait.php` - pierwsza pensja przy zatrudnieniu pracownika technicznego przez `debit()` (typ `hr_fee`); rollback przy braku środków.
- `src/HeadhunterService.php` - opłata za wyszukiwanie oraz premia za zatrudnienie przez headhuntera przez `debit()` (typ `hr_fee`); rollback przy braku środków.
- `lang/pl/bank.php` - etykieta typu `bank_fee` oraz opisy operacji: negocjacje, sprzedaż odwiertu/magazynu w restrukturyzacji, zatrudnienie, headhunter (wyszukiwanie i premia).

### 2026-06-10 - Tick: naprawa podwójnego pobrania gotówki za katastrofy
- `src/Well/DisastersTrait.php` - usunięto bezpośrednie `UPDATE players SET cash = cash - X` z czterech katastrof (`triggerPipelineExplosion`, `triggerSurfaceSpill`, `triggerBlowout`, `triggerReservoirContamination`). Eksplozja rurociągu i wyciek były pobierane DWUKROTNIE: raz przez bezpośredni `UPDATE`, drugi raz przez tick (`cashDelta` + różnicowy `saveCashAndTick`) - gracz tracił podwójną kwotę kary (np. 40 mln zamiast 20 mln).
- `src/Tick/WellRiskHandler.php` - blowout i skażenie rezerwuaru doliczają teraz koszt+karę do `finIncident` i `playerCash` w ticku (wcześniej polegały na bezpośrednim `UPDATE`, który właśnie usunięto). Dzięki temu wszystkie cztery katastrofy są pobierane dokładnie raz, przez tick jako jedynego płatnika, i trafiają do audytu bankowego (`tick_incident`) oraz wykrywania kryzysu.

### 2026-06-10 - Bank: komornik, sprzedaż odwiertu i czarny rynek przez centralne API finansowe
- `src/FinancialTransactionService.php` - nowe typy operacji: `well_sale` (sprzedaż odwiertu) i `black_market_sale` (czarny rynek, przychód i kara).
- `src/BailiffService.php` - zajęcie 30% gotówki przez komornika przechodzi przez `FinancialTransactionService::debit()` (ruch gotówki + wpis w historii bankowej zamiast osobnego `UPDATE` + `logTransaction`); fallback do bezpośredniego `UPDATE` gdy FTS niedostępny.
- `src/Well/SellTrait.php` - sprzedaż odwiertu księguje wpływ przez `credit()` (typ `well_sale`, referencja do odwiertu) wewnątrz istniejącej transakcji; rollback + komunikat błędu gdy księgowanie się nie powiedzie.
- `src/BlackMarketService.php` - przychód i kara za handel na czarnym rynku idą przez `credit()`/`debit()` (typ `black_market_sale`); aktualizacja `black_market_score`/`credit_score` została oddzielona od ruchu gotówki, bez podwójnego pobrania.
- `lang/pl/bank.php`, `lang/pl/components.php` - etykiety typów i opisy operacji `well_sale`, `black_market_sale`, kary czarnorynkowej oraz komunikat błędu sprzedaży odwiertu.

### 2026-06-09 - Tick: audyt bezpieczenstwa i naprawa bledow
- `src/Tick/PlayersSection.php` - naprawiono blokujacy blad nowych graczy: `last_tick_at = NULL` powodowal `TypeError` w `new DateTime()` i gracz nigdy nie dostawal pierwszego ticka; query uzywa teraz `COALESCE(last_tick_at, '2000-01-01 00:00:00')`.
- `src/Tick/PlayersSection.php` - wykrywanie kryzysu finansowego (`FinancialStateSection::process`) uwzglednia teraz pelny koszt incydentow (odwierty + katastrofy rurociagow + kary za wyciek). Wczesniej eksplozja rurociagu mogla wyzerowac gotowke bez wyzwolenia kryzysu.
- `src/Tick/PlayersSection.php` - odliczenia gotowki za rurociagi i wyciek dostaly floor `max(0.0, ...)` jak pozostale koszty, zeby ujemne saldo nie psulo logiki kryzysu.
- `src/Tick/FinancialStateSection.php` - licznik godzin kryzysu uzywa wstrzyknietego `$this->now` zamiast `time()`/`date()` (spojnosc z reszta ticka, testowalnosc).
- `cron/tick.php` - dodano lock wykonania (`flock`) zapobiegajacy nakladaniu sie tickow gdy poprzedni przebieg trwa dluzej niz interwal crona (ochrona przed podwojona produkcja/kosztami). Klucz crona porownywany teraz przez `hash_equals` (odpornosc na timing attack).

### 2026-06-09 - Bank: koszty tickowe i sprzedaż ropy w historii bankowej
- `src/MarketOffer.php` - automatyczna sprzedaż ropy (oferty rynkowe wykonywane w ticku) przeszła ze starego `UPDATE players SET cash +` na `FinancialTransactionService::credit()` z opisem i referencją do oferty (`market_offer`).
- `src/FinancialTransactionService.php` - nowe typy operacji tickowych: `tick_opex`, `tick_salary`, `tick_transport`, `tick_incident`, `hub_usage`; nowa stała `TICK_AUDIT_TYPES` (razem z `tax`) oraz metoda `purgeTickAudit()` usuwająca stare wpisy tickowe (przelewy, kredyty i zakupy zostają na zawsze).
- `src/Tick/PlayersSection.php` - nowa metoda `logTickBankAudit()`: po zapisie gotówki tick dopisuje do `bank_transactions` zbiorcze koszty gracza per kategoria (podatek regionalny, OPEX odwiertów, opłaty hubowe, pensje, transport, incydenty + katastrofy rurociągów + kary środowiskowe); wpis tylko gdy kwota > 0; sam audit trail przez `logTransaction()` - gotówka schodzi różnicowo w `saveCashAndTick`, bez podwójnego pobrania; OPEX pomniejszony o opłaty hubowe (te wpadają do obu akumulatorów w `WellHubSection`).
- `cron/tick.php` - sekcja cleanup wywołuje `purgeTickAudit()` z tą samą retencją co `incident_retention_days` (domyślnie 30 dni).
- `lang/pl/bank.php` - opisy operacji `bank.tx_tick_*`, `bank.tx_market_sale` oraz etykiety typów `bank.account.type.tick_*` i `hub_usage` dla pilli w historii konta.
- `tests/Integration/FinancialTransactionServiceTest.php` - 2 nowe testy: akceptacja typów tickowych przez `logTransaction()` oraz `purgeTickAudit()` usuwający wyłącznie stare wpisy tickowe. Testy zielone: 176/176 SQLite.

### 2026-06-09 - Bank: zakupy i oplaty gracza przez centralne API finansowe
- `src/PlayerPaymentService.php` - dodano czytelna klase posrednia dla oplat gracza (`charge()` / `refund()`), oparta o `FinancialTransactionService`.
- `src/FinancialTransactionService.php`, `src/LegalService.php`, `src/Legal/HubPermitTrait.php`, `src/WorldMap.php`, `public/upgrade_storage.php` - schemat bankowy jest przygotowywany przed recznie otwierana transakcja, zeby pierwsze uzycie na bazie bez nowych tabel nie wywolywalo DDL w srodku transakcji MySQL.
- `src/HubAcquisitionService.php` - zakup, wynajem, rozbudowa oraz zwroty oplat za huby przechodza przez `PlayerPaymentService` i zapis bankowy typu `hub_purchase`.
- `src/WellPipelineService.php` - budowa rurociagow, naprawy i konserwacje ksiegowane sa przez `PlayerPaymentService` jako `pipeline_purchase`, `pipeline_repair` i `pipeline_maintenance`, bez osobnego recznego `UPDATE players SET cash`.
- `src/LegalService.php`, `src/Legal/HubPermitTrait.php` - oplaty za wnioski prawne dla odwiertow i hubow trafiaja do historii bankowej jako `legal_fee`.
- `src/WorldMap.php`, `src/GeologicalLayerService.php`, `public/upgrade_storage.php` - zakup lokalizacji, zmiana warstwy geologicznej i rozbudowa magazynu korzystaja z centralnego pobrania srodkow przez klase oplat gracza.
- `lang/pl/bank.php` - dodano czytelne opisy operacji bankowych dla powyzszych zakupow i oplat.

### 2026-06-09 - Bank: kredyty przez centralne API finansowe
- `src/Bank/ApplicationTrait.php` - akceptacja oferty kredytowej ksieguje wyplate kredytu przez `FinancialTransactionService::credit()` i zapisuje wpis `loan` w `bank_transactions`.
- `src/Bank/RepaymentTrait.php` - reczna splata rat, kilku rat albo calego kredytu przechodzi przez `FinancialTransactionService::debit()` i zapisuje typ `loan_payment` w historii bankowej.
- `src/LoanRepository.php` - automatyczne raty obslugiwane w ticku sa teraz atomowe: pobranie gotowki, wpis w `bank_transactions`, aktualizacja kredytu i wpis w `loan_payments` ida w jednej transakcji.
- `lang/pl/bank.php` - dodano opisy operacji bankowych dla wyplat kredytow, splat recznych i rat automatycznych.

### 2026-06-09 - Dzial prawny admin: czytelniejsza konfiguracja regionow
- `templates/views/admin/legal/main.php`, `lang/pl/admin/legal.php`, `assets/css/admin.css` - sekcja konfiguracji regionow dostala prosty opis dla laika, grupowane naglowki tabeli, lepsze wyróznienie pierwszej i ostatniej kolumny oraz czytelniejsze formularze dla parametrow odwiertow i hubow; dodatkowo formularze akcji w zakladce wnioskow hubow korzystaja juz ze wspolnej klasy `js-confirm-form` bez inline `style`.

### 2026-06-09 - Dzial prawny: poprawka bootstrapu hub permits
- `src/Legal/HubPermitTrait.php` - usunieto niekompatybilne dla aktualnego MySQL `ALTER TABLE ... ADD COLUMN IF NOT EXISTS ...`; schema hub permits korzysta teraz z `Database::addColumnIfMissing()`, dzieki czemu poprawnie tworza sie kolumny `hub_permit_*` oraz tabela `hub_permit_applications`.

### 2026-06-08 - Admin legal: naprawa potwierdzen akcji
- `admin/legal.php`, `templates/views/admin/legal/main.php` - dzial prawny admina korzysta juz tylko z jednego globalnego handlera `modal.js` dla formularzy `data-confirm`; usunieto dodatkowe podpiete `admin_legal.js`, ktore dublowalo przechwycenie submitu i blokowalo akcje `Seeduj regiony` oraz `Uruchom migracje`.

### 2026-06-08 - Logistyka: cleanup starych aktywnych dostaw morskich
- `src/MarineDeliveryService.php`, `public/logistics.php` - dodano bezpieczne czyszczenie osieroconych aktywnych dostaw morskich bez wpisu w `port_queue`, z ETA starszym niz 12 godzin; usuwa to stare mikro-kursy po poprzednim modelu i zostawia aktualne, prawidlowe rejsy w logistyce.

### 2026-06-08 - Czyste zakladki technical i black market
- `assets/js/ajax_pagination.js`, `templates/views/market/main.php`, `lang/pl/logistics.php` - wspolny mechanizm czysci teraz takze adresy zakladek `technical` oraz `market/black_market`; w dostawach morskich etykiete `ETA` zmieniono na bardziej czytelne `Planowane dotarcie`.

### 2026-06-08 - Globalna paginacja bez przeladowania
- `assets/js/ajax_pagination.js`, `templates/footer.php`, `admin/partials/footer.php`, `templates/views/market/main.php`, `assets/js/logistics.js` - dodano jeden wspolny mechanizm czesciowej paginacji i zakladek modulow dla stron gry oraz admina: market, technical, logistyka i pozostale kontenery paginacji podmieniaja glowna tresc strony, zostawiaja czysty adres bez query stringa i przewijaja do tej samej sekcji/paginacji.

### 2026-06-08 - Logistyka: czysty adres przy paginacji
- `assets/js/logistics.js` - paginacja AJAX w logistyce zapisuje techniczny adres z parametrami tylko w `history.state`, a w pasku przegladarki zostawia czysty adres `/logistics` bez query stringa i hashy sekcji.

### 2026-06-08 - Logistyka: paginacja bez przeskoku strony
- `assets/js/logistics.js` - linki paginacji w module logistyki dzialaja teraz jako czesciowe odswiezenie `.logistics-page`: klikniecie pobiera nowy HTML w tle, podmienia tylko modul logistyki, aktualizuje URL i przewija do aktualnej sekcji zamiast ladowac strone od gory.

### 2026-06-08 - Logistyka: paginacja historii i incydentow
- `logistics.php`, `templates/views/logistics/main.php`, `src/MarineDeliveryService.php` - historia dostaw morskich i incydenty logistyczne hubow sa stronicowane po 5 pozycji; historia dostaw morskich w ticku jest czyszczona po 7 dniach dla statusow `delivered` i `lost`.

### 2026-06-08 - Logistyka: paginacja transportu morskiego i drogowego
- `logistics.php`, `templates/views/logistics/main.php`, `src/MarineDeliveryService.php` - aktywne kursy drogowe i dostawy morskie sa stronicowane po 5 pozycji; panel morski pokazuje laczna liczbe aktywnych dostaw w KPI oraz nawigacje poprzednia/nastepna dla `marine_page`.

### 2026-06-08 - Logistyka: aktywne dostawy morskie w glownym kontrolerze
- `logistics.php` - glowny kontroler routingu `/logistics` laduje teraz `MarineDeliveryService`, bufory tankowcow, aktywne rejsy, historie i fallback panelu; wczesniej dane byly ustawiane na puste tablice, wiec widok pokazywal `0` mimo aktywnych dostaw w adminie.

### 2026-06-07 - Logistyka: priorytet aktywnych rejsow morskich
- `src/MarineDeliveryService.php` - aktywne dostawy morskie w panelu logistyki sortuja teraz realne rejsy (`departing`, `in_transit`, `delayed`) przed kolejka portowa, a liczniki nie uwzgledniaja starych opoznionych rekordow spoza 2-dniowego okna; dzieki temu rejs widoczny w adminie jako `in_transit` nie znika pod zaleglymi wpisami `waiting_for_port`.

### 2026-06-07 - Logistyka: widocznosc dostaw morskich
- `public/logistics.php`, `src/MarineDeliveryService.php` - panel logistyki jawnie laduje serwisy portow i dostaw morskich oraz ma awaryjne pobieranie danych z `marine_deliveries`, `ports` i `wells.marine_buffer_bbl`, zeby aktywne rejsy, historia i bufor tankowca nie zerowaly sie przy bledzie serwisu.

### 2026-06-07 - Logistyka: korekta bufora hubow w ticku
- `src/Tick/WellProductionHandler.php`, `src/Tick/PlayersSection.php`, `src/Tick/WellLoopSection.php`, `src/Tick/WellHubSection.php`, `src/HubTickService.php`, `src/Hub/TickPersistTrait.php`, `src/Hub/TickCalculationsTrait.php` - transport czasowy (`ciezarowki`, `tankowiec`) nie dopisuje juz produkcji do huba przed realna dostawa, a dostawy po dotarciu przechodza przez finalizacje huba; tick rozroznia rope przetworzona, pozostawiona w buforze i spuszczona z bufora, z korekta magazynu oraz finansow.

### 2026-06-07 - Logistyka: poprawka rozbudowy wlasnych hubow
- `assets/js/logistics_hubs.js`, `templates/views/logistics/main.php`, `lang/pl/logistics.php` - akcje `Napraw` i `Rozbuduj` w sekcji `Twoje huby` pobieraja teraz dane z karty wlasnego huba, a nie z rynku hubow; usunieto efekt `NaN PLN`, poprawiono stary tekst `Uaktualnij` na `Rozbuduj` i zachowano blokade rozbudowy hubow wynajmowanych/systemowych.

### 2026-06-07 - Logistyka: rozbudowa wlasnych hubow
- `src/Hub/ViewHubsTrait.php`, `templates/views/logistics/main.php`, `assets/js/logistics_hubs.js`, `lang/pl/logistics.php` - w sekcji `Twoje huby` podlaczono przycisk `Rozbuduj` dla hubow nalezacych do gracza; widok korzysta z istniejacego backendu `HubApi.php` / `HubAcquisitionService.php`, ktory pobiera koszt, respektuje maksymalny poziom 3 i odpala modal potwierdzenia.

### 2026-06-07 - Logistyka: paginacja kursow drogowych
- `public/logistics.php`, `templates/views/logistics/main.php`, `assets/css/logistics.css`, `lang/pl/logistics.php` - sekcja `Kursy drogowe w tranzycie` pokazuje teraz kursy po 10 na strone, z licznikiem wszystkich aktywnych kursow i nawigacja poprzednia/nastepna.

### 2026-06-07 - Transport morski: historia rejsow w logistyce
- `src/MarineDeliveryService.php`, `templates/views/logistics/main.php`, `assets/css/logistics.css`, `lang/pl/logistics.php` - pod sekcja `Dostawy morskie` dodano widoczny blok krotkiej historii rejsow tankowca; historia korzysta z biezacych rekordow `marine_deliveries`, sortuje po dacie zakonczenia i moze znikac po czyszczeniu ticka, bez stalego archiwum.

### 2026-06-07 - Transport morski: bufor tankowca
- `src/Tick/WellProductionHandler.php`, `src/TransportConfigService.php`, `admin/transport.php`, `templates/views/admin/transport/main.php`, `lang/pl/admin/transport.php` - transport morski nie wysyla juz mikrorejsow co tick; ropa z odwiertu tankowcowego trafia najpierw do bufora `wells.marine_buffer_bbl`, a tankowiec wyrusza dopiero po osiagnieciu progu `min_load_bbl`.
- `admin/transport.php` - prog startu tankowca jest edytowalny w panelu admina dla typu `tankowiec` jako `Minimalna ladownosc tankowca (bbl)`; aktualny balans produkcyjny: `4000 bbl`, a wartosc `0` oznacza stary model wysylki natychmiastowej.
- `src/MarineDeliveryService.php`, `public/logistics.php`, `templates/views/logistics/main.php`, `assets/css/logistics.css`, `lang/pl/logistics.php` - panel logistyki gracza pokazuje teraz bufory tankowcow per odwiert: aktualne bbl, prog wyplyniecia, brakujacy wolumen i pasek postepu.

### 2026-06-05 - Wiarygodnosc firmy: tick i bramka dzialu prawnego
- `src/CompanyCredibilityService.php` - dodano przyznawanie eventu `clean_operation_period` raz na 7 dni, jesli gracz nie mial w tym okresie negatywnych zdarzen wiarygodnosci.
- `src/Tick/CredibilitySection.php`, `cron/tick.php` - dodano sekcje ticku przyznajaca bonus +3 za czysty okres dzialania; przed zmiana ticka wykonano backup `backups/2026-06-05_19-51-17_tick.php.bak`.
- `src/LegalService.php`, `public/legal.php`, `templates/views/legal/main.php` - regiony `high/critical` wymagaja wiarygodnosci firmy min. 40/100 do skladania wnioskow, a widok pokazuje osobna grupe blokady.
- `src/WorldMap.php`, `templates/views/map/main.php`, `assets/js/world_map.js`, `assets/css/map.css` - mapa rozpoznaje status `credibility_locked` i pokazuje wymagany oraz aktualny wynik wiarygodnosci.
- `lang/pl/legal.php`, `lang/pl/map.php`, `lang/pl/credibility.php`, `assets/css/legal.css` - dodano teksty i style dla blokady wiarygodnoscia oraz notatke historii czystego okresu.
- `tests/Integration/CompanyCredibilityServiceTest.php`, `tests/Integration/LegalServiceTest.php`, `tests/Integration/LegalMapPermitDataTest.php` - dodano testy bonusu czystego okresu i blokady wnioskow przez niska wiarygodnosc.
- `DZIAL_PRAWNY_P1_STATUS.md` - zaktualizowano status dzialu prawnego po wdrozeniu punktu `18.6`, dodano TODO i rekomendowana kolejnosc dalszych prac.
- `DZIAL_PRAWNY_P1_STATUS.md` - doprecyzowano, ze `18.6` jest wdrozone jako zamkniety fundament, a pozostale pozycje to przyszle rozszerzenia balansu i kolejnych dzialow.
- `src/Tick/PlayersSection.php` - utracone dostawy morskie z `MarineDeliverySection` (`piracy` / `catastrophe`) sa teraz ksiegowane jako straty transportowe w `finance_logs`; backup przed zmiana ticka: `backups/2026-06-05_22-03-17_PlayersSection.php.bak`.
- `DZIAL_PRAWNY_P1_STATUS.md` - zweryfikowano zdarzenia transportowe: drogowe, rurociagowe i utracone dostawy morskie trafiaja do `finance_logs`; morski `storm` pozostaje opoznieniem bez utraty ropy.
- `admin/incidents.php`, `templates/views/admin/incidents/main.php`, `assets/js/admin_incidents.js`, `lang/pl/admin/incidents.php` - dodano osobny toolbar admina do recznego wywolywania incydentow morskich (`piracy`, `catastrophe`, `storm`, `breakdown`) oraz zrodlo `marine` w historii incydentow.
- `templates/views/admin/incidents/main.php`, `assets/js/admin_incidents.js`, `lang/pl/admin/incidents.php` - toolbar incydentow morskich przeniesiono do widocznej zakladki `Morskie`, zamiast chowac go w zakladce wywolywania incydentow odwiertow.
- `admin/incidents.php`, `assets/js/admin_incidents.js`, `templates/views/admin/incidents/main.php`, `lang/pl/admin/incidents.php` - lista dostaw morskich w toolbarze admina jest limitowana do 15 aktywnych dostaw wybranego gracza i odrzuca transporty, ktorych odwiert nie nalezy do tego gracza.

### 2026-06-05 - Strona glowna: status uszkodzony przy 1% stanu
- `src/WellGridData.php` - aktywny odwiert ze stanem technicznym `<= 1%` jest na stronie glownej prezentowany jako `broken`, zamiast jako aktywny.
- `templates/components/well_grid.php` - podsumowanie regionu i laczne wydobycie licza status wyswietlany (`_status/_isActive`), wiec odwiert krytyczny nie zawyza aktywnych KPI.

### 2026-06-05 - Karty odwiertow: stale KPI przy wstrzymaniu
- `templates/components/well_grid.php` - karty odwiertow pokazuja teraz wydobycie, stan, tryb i zloze takze dla statusow wstrzymanych; wydobycie aktywne pokazuje wartosc normalnie, a wstrzymane pokazuje `0 bbl/h` z bazowym potencjalem.
- `lang/pl/components.php` - dodano etykiety `wg.stat_paused` i `wg.stat_base` dla informacji pod KPI wydobycia.

### 2026-06-05 - Aktualnosci spolki: tytul z TinyMCE
- `src/AdminNewsHtml.php` - dodano wspolny sanitizer HTML dla tytulu i tresci aktualnosci admina.
- `admin/news.php`, `templates/views/admin/news/main.php`, `assets/js/admin_news_editor.js` - tytul aktualnosci jest edytowany przez TinyMCE, zapisywany w `admin_news.title_html`, a `admin_news.title` zostaje tekstowym fallbackiem.
- `src/AdminNewsApi.php`, `assets/js/chat.js`, `assets/css/chat.css` - API zwraca bezpieczne `title_html`, a panel aktualnosci renderuje formatowany tytul.
- `assets/css/admin.css` - dodano style podgladu formatowanego tytulu w liscie aktualnosci admina.

### 2026-06-05 - Aktualnosci spolki: render HTML z TinyMCE
- `src/AdminNewsApi.php` - dodano bezpieczne czyszczenie HTML aktualnosci i pole `content_html`, aby tresc z TinyMCE zachowala naglowki, linki i kolory tekstu.
- `assets/js/chat.js` - panel aktualnosci renderuje teraz HTML zwrocony przez API zamiast wyswietlac tresc jako zwykly tekst.
- `assets/js/chat.js` - poprawiono scope helpera renderowania HTML i tekst komunikatu ladowania, aby panel nie pokazywal `Bd adowania.` przy poprawnej odpowiedzi API.
- `assets/css/chat.css` - dodano style dla akapitow, list, naglowkow, cytatow i linkow w panelu aktualnosci spolki.

### 2026-06-04 — Dział prawny: domknięcie P1 i start P2
- `src/LegalService.php` — podpięto `required_legal_level` do walidacji wniosku i danych mapy; poziom działu prawnego liczony jest z aktywnego dyrektora roli `legal`.
- `public/legal.php`, `templates/views/legal/main.php`, `assets/js/legal.js`, `assets/css/legal.css` — dodano grupę regionów blokowanych poziomem prawnym i przeniesiono komunikaty flash do JS modułu.
- `admin/legal.php`, `templates/views/admin/legal/main.php`, `assets/js/admin_legal.js`, `admin/partials/footer.php`, `assets/css/admin.css` — admin może ustawiać wymagany poziom prawny regionu; potwierdzenia przeniesiono z inline JS.
- `src/WorldMap.php`, `assets/js/world_map.js`, `assets/css/map.css`, `lang/pl/map.php` — mapa rozróżnia status `legal_locked` i pokazuje osobny komunikat blokady prawnej.
- `src/Tick/LegalSection.php` — usunięto emoji z ikon powiadomień działu prawnego.
- `DZIAL_PRAWNY_P1_STATUS.md` — zaktualizowano status wdrożenia P1/P2 po audycie kodu.
- `tests/Integration/LegalServiceTest.php`, `tests/Integration/LegalMapPermitDataTest.php` — dodano testy blokady wymaganym poziomem działu prawnego.

### 2026-06-03 — Logowanie zapamiętywane na 30 dni
- `public/login.php` — podpięto istniejący mechanizm remember-me pod aktywny ekran `/login`, dodano checkbox i auto-logowanie z cookie.
- `login.php` — ujednolicono rootową kopię formularza logowania z aktywnym ekranem `/login`.
- `lang/pl/auth.php` — dodano tekst checkboxa logowania.
- `assets/css/auth.css` — dopasowano odstęp checkboxa na ekranie logowania.
# 2026-06-13 — Uniwersalny system sabotaży P1

Wdrożono fundament modułu sabotaży zgodny z briefem `BRIEF DLA AI — Uniwersalny system sabotaży.pdf`.

- `src/Sabotage/SabotageSchema.php` — idempotentny schemat 4 tabel: `sabotage_options`, `sabotage_effects`, `sabotage_attempts`, `sabotage_logs`.
- `src/SabotageService.php` — uniwersalny serwis sabotaży: pobiera aktywne typy, efekty, zapisuje próby i logi oraz obsługuje ręczne akcje PvP na celu `player_company`.
- Seed P1/P2: `road_ambush` oraz `road_partial_theft` dla `target_type=road_transport`, `context=road_transport_sabotage`, a także `player_budget_hit` i `player_dirty_report` dla `target_type=player_company`, `context=player_company_sabotage`.
- `src/RoadTransportService.php` — incydent drogowy typu `sabotage` wybiera aktywny typ sabotażu z konfiguracji i stosuje efekt `transport_loss_pct`.
- `src/Tick/PlayersSection.php`, `src/Tick/WellRoadTripSection.php` — tick tworzy jedną instancję `SabotageService` per gracz i przekazuje ją do rozliczania ukończonych kursów drogowych.
- `public/sabotage.php`, `templates/views/sabotage/main.php`, `assets/css/sabotage.css` — strona gracza do ręcznego sabotażu PvP; koszt idzie przez `FinancialTransactionService::TYPE_SABOTAGE`, a akcje respektują cooldown, wymóg czarnego rynku i globalny przełącznik modułu.
- `public/legal.php`, `templates/views/legal/main.php`, `src/GameShell.php`, `src/BoardAccess.php`, `src/init.php` — wejście do modułu sabotażu jest dostępne z działu prawnego, z osobnym route `/sabotage` i ikoną w siatce akcji.
- `admin/sabotage.php`, `templates/views/admin/sabotage/main.php` — panel Admin → Sabotaże: typy, efekty, próby, logi, pomoc.
- `lang/pl/sabotage.php`, `lang/pl/admin/sabotage.php`, `lang/pl/admin/nav.php` — tłumaczenia i link w menu admina.
- `well_config.sabotage_module_enabled` — globalny przełącznik modułu. Gdy jest wyłączony, ryzyko `sabotage` nie jest losowane dla transportu drogowego.

Zakres P1: tylko sabotaż systemowy transportu drogowego. Sabotaż gracz kontra gracz, sądy, odwety, porty/terminale i pełna wojna korporacyjna zostają jako TODO.

## 2026-06-29 - Mobile hardening P1

Wdrożono pierwszy etap hardeningu aplikacji Flutter/Android. Mobilny token sesji został przeniesiony ze `SharedPreferences` do `flutter_secure_storage`, a WebView nie dostaje już Bearer tokena przez JavaScript ani `localStorage`.

- `mobile/lib/services/session_storage.dart` - wspólna warstwa sesji z migracją starych danych z `SharedPreferences`.
- `api/v1/auth/webview-bridge.php`, `public/mobile_bridge_login.php`, `src/MobileWebBridge.php`, `src/Auth.php` - jednorazowy backend bridge mobile -> web session z TTL 60 sekund i hashowaniem tokena w DB.
- `mobile/lib/modules/game/game_module.dart`, `mobile/lib/screens/webview_screen.dart` - WebView otwiera tylko `bridge_url`, blokuje `http://` i obce hosty.
- Android: wyłączone backupy, blokada cleartext, `network_security_config.xml`, release signing przeniesiony do `key.properties`/CI secrets, `minifyEnabled` i `shrinkResources` w release, `FLAG_SECURE` w release.
- Dodano testy: `mobile/test/session_storage_test.dart`, `mobile/test/webview_navigation_policy_test.dart`, `tests/Integration/MobileWebBridgeTest.php`.

iOS zostaje etapem 2: bez katalogu `ios/` w tym wdrożeniu. Szczegóły architektury i TODO są w `svn_repo/MOBILE_ARCH.md`, sekcja 17.

## 2026-07-08 - Kontrakty B2B P1

Wdrozono fundament kontraktow B2B w istniejacym module `/contracts`, bez osobnej pozycji menu gracza.

- `src/B2BContracts/B2BContractSchema.php` - idempotentny schemat tabel: `b2b_contract_offers`, `b2b_contract_terms`, `b2b_contract_logs`, `b2b_contract_config`.
- `src/B2BContractService.php` - serwis ofert kupna B2B: tworzenie, anulowanie, realizacja pelnej natychmiastowej dostawy, wygaszanie, flagowanie i anulowanie admina.
- `src/FinancialTransactionService.php`, `src/WalletConfig.php` - dodano typy FTS: `b2b_escrow_lock`, `b2b_escrow_refund`, `b2b_cancel_penalty`, `b2b_trade_revenue`; routing idzie na konto bankowe.
- `public/contracts.php`, `templates/views/contracts/main.php`, `templates/views/contracts/b2b.php` - gracz ma zakladki: Systemowe, Rynek B2B, Moje B2B, Historia, Logi. Akcje ida przez POST + CSRF + PRG.
- `admin/contracts.php`, `templates/views/admin/contracts/main.php` - panel admina ma zakladke B2B: pulpit, ustawienia, oferty, flagowanie/anulowanie, logi.
- `src/Tick/Modules/B2BContractsModule.php`, `cron/tick.php` - tick wygasza oferty B2B i zwraca 100% escrow przy wygasnieciu.
- Testy: `tests/Integration/B2BContractServiceTest.php`, `tests/MySqlIntegration/MySqlB2BContractServiceTest.php`, aktualizacja `tests/Unit/TickRegistryTest.php` i `tests/Integration/ContractFinancesTest.php`.

Zakres MVP: tylko pelna natychmiastowa dostawa z magazynu sprzedajacego. Odlozone: dostawy czesciowe, aukcje, podkontrakty, reputacja B2B i rozbudowane klauzule.

### 2026-07-08 - Kontrakty B2B: admin filtry i reputacja

- `src/B2BContracts/B2BContractSchema.php` - dodano fundament reputacji B2B: `b2b_reputation_scores` i `b2b_reputation_logs`.
- `src/B2BContractService.php` - reputacja B2B aktualizuje sie przy realizacji, anulowaniu kupujacego, wygasnieciu, flagowaniu i anulowaniu admina.
- `admin/contracts.php`, `templates/views/admin/contracts/main.php` - zakladka B2B ma filtry ofert po statusie, fladze i graczu/firmie/ID, paginacje ofert i logow oraz sekcje reputacji B2B z paginacja.
- Testy rozszerzono o reputacje, filtry admina i MySQL cleanup nowych tabel.
