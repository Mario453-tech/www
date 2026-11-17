# OilEmpire — karty implementacyjne fundamentu P01–P05

> Część jednej specyfikacji. [Indeks](README.md), [plan P01–P25](OILEMPIRE_PLAN_PROGRAMOWANIA.md), [standard modułu](OILEMPIRE_STANDARD_MODULU_WDROZENIE.md), [API](OILEMPIRE_API_SPECYFIKACJA_TECHNICZNA.md), [bezpieczeństwo S01–S18](OILEMPIRE_BEZPIECZENSTWO_SILNIKA.md).

Status: projekt wykonawczy do programowania. Nie potwierdza gotowego kodu. Zakres obejmuje fundament P01–P05; pozostałe pakiety zachowują własne karty do wykonania. Liczby domenowe i approval pozostają zgodne z główną architekturą i zaakceptowanymi OPEN01–OPEN12.

## 1. Konwencje wspólne

Właściciel karty = moduł odpowiedzialny za dane i akcję. ID publiczne: ULID CHAR(26), ASCII binary; referencje mają ten sam typ. Przy wewnętrznym BIGINT istniejącego szkieletu należy zachować osobny public_id UNIQUE i mapowanie; nie zmieniać istniejącej migracji. Poniższe ID oznacza identyfikator logiczny, a karta migracji wskazuje wybrany wariant dla faktycznego schematu.

Czas: DATETIME(3) UTC, jeden Clock wstrzyknięty w akcję; deadline według zegara serwera. Pieniądze: BIGINT SIGNED grosze, w API dziesiętny string. Wolumen: DECIMAL(16,4), API string. Statusy: VARCHAR(32) i BackedEnum, nie SQL ENUM. Każda tabela ma created_at; updated_at tylko gdy rekord jest mutowalny. Append-only nie ma mutacji domenowej UPDATE/DELETE. FK domyślnie RESTRICT; CASCADE tylko dla wyraźnie technicznych dzieci, nigdy księgi, audytu ani zdarzeń.

Każda karta dziedziczy: Form Request, allowlistę pól, politykę obiektu, kontrakt DTO, envelope API, PL/EN/DE, request_id, limity §10 API i właściwe S01–S18. Walidacja kontekstu ponawiana pod blokadą. Błędy domenowe nie zawierają sekretów ani stosu. Odpowiedź UI ma kod/klucz tłumaczenia, komponent dobiera lokalny SVG.

Kontrakty publiczne umieszczamy w Contracts/. Kontrolery/Filament/CLI/job wywołują tę samą akcję. Żądanie mutujące ekonomię ma actor_id, company_id, operation, client_key i request_hash. Snapshot danych wrażliwych w audycie jest redagowany. Migracje uruchamiane tylko w kontrolowanym procesie wydania.

### 1.1. Rejestr kontraktów fundamentu

| Kontrakt | Metoda / wynik |
|---|---|
| core.clock v1 | now(): InstantUtc; w testach FrozenClock |
| core.access v1 | authorize(ActorContext, capability, ResourceRef): void albo odmowa |
| core.transaction v1 | execute(OperationContext, command): OperationResult; jedna główna transakcja |
| core.ledger v1 | post(PostingCommand): PostingReceipt; reserve/release po jawnej komendzie |
| core.idempotency v1 | begin(scope, key, payloadHash); commitResult(resultRef); bez przejmowania niewidocznego pending |
| simulation.settlement v1 | settleWithinTransaction(companyId, targetAt, expectedState): SettlementResult |
| simulation.due v1 | processWithinTransaction(eventId, targetAt): DueResult |
| simulation.outbox v1 | append(EventEnvelope): eventId w transakcji właściciela |
| admin.balance v1 | propose/submit/review/activate z hashem i wersją bazową |
| platform.modules v1 | requireAvailable(moduleKey, operationKind, actor): AvailabilityDecision |

ActorContext: actor_type user/system, actor_id, authenticated_session_id nullable dla systemu, capabilities, request_id. System musi wskazać nazwany cel zadania; nie posiada automatycznie wszystkich capability. Stan uprawnień nie jest przyjmowany z payloadu klienta.

## 2. P01 — Platform i modularność

### P01-F01 — rejestr manifestów

**Dane:** niezmienny buildowy module_registry.json: release_sha, manifest_hash, schema_version, moduły i kontrakty. Nie jest źródłem enabled/premium/parametrów.

**Akcja:** CompileModuleRegistry(releasePath). Wejście wyłącznie z zaufanego artefaktu; parser YAML bez tagów obiektowych. Waliduje schemat, ścieżki w katalogu modułu, klasy i interfaces, unikalne nazwy tras/permission/reason_code oraz zgodność kontraktów. Graf requires jest sortowany topologicznie; cykl daje błąd z krawędziami. Wynik zapisany atomowo do cache wydania.

**Błędy:** INVALID_MANIFEST, DUPLICATE_REGISTRATION, MISSING_CONTRACT, CONTRACT_VERSION_MISMATCH, DEPENDENCY_CYCLE. CLI exit 1; brak częściowego rejestru.

**Odbiór:** poprawny Staff; brak providera; ../ w ścieżce; nieznany klucz; cykl A→B→A; odtworzenie identycznego rejestru z tego samego artefaktu. Runtime nie skanuje katalogów przy każdym request.

### P01-F02 — stan wykonania modułu

**Tabela module_runtime_states:** module_key VARCHAR(64) PK; state VARCHAR(32); state_version BIGINT UNSIGNED; desired_enabled BOOL; activation_balance_version_id nullable; prior_state nullable; fault_code nullable; changed_by nullable; changed_at. Indeks(state). FK do wersji balansu po P05, bez cyklu migracji.

**Tabela module_state_events:** id, module_key, from_state, to_state, expected_version, actor_type/id, request_id, occurred_at; indeks(module_key, occurred_at). Append-only.

**Akcje:** RequireAvailable (read/start_new_process/continue_existing/recovery), BeginDrain, FinalizeDisable, RecoverModule. Przejścia dokładnie wg standardu; UPDATE WHERE state_version=expected. BeginDrain jest realizacją zatwierdzonego enabled=false; nie omija P05. FinalizeDisable sprawdza zobowiązania, due-events, outbox i zależności pod stabilnym planem blokad. Draining nie blokuje dokończenia.

**UI:** Filament: stan, zależności, liczniki pozostałych procesów i blokery; brak bezpośredniej edycji enum. Nie ma publicznego endpointu gracza do zmiany stanu.

**Odbiór:** dwa konkurencyjne przejścia → jedno; dependent aktywny blokuje disable; istniejący kurs kończy się w draining; nowe zlecenie daje 503 MODULE_DRAINING.

### P01-F03 — szkielet wykonania i health

HTTP przez public/index.php, CLI przez artisan, wspólne bootstrap/app.php. Rejestracja providerów nie wykonuje ekonomii. Health publiczny ujawnia wyłącznie dostępność; gotowość DB/rejestru/kolejek dostępna operacyjnie, bez sekretów.

**Zakres:** RequestContext, Clock, ActorContext, ExceptionMapper, MoneyMinor/VolumeBbl, module registry, tłumaczenia błędów. CI: formatowanie, analiza statyczna, testy, kontrola importów Contracts/. Brak rozszerzenia PHP/komendy w środowisku daje jawny bloker.

**Odbiór:** web, stateless API i CLI uruchamiają się; sesja nie startuje w Bearer API/CLI; wadliwy manifest blokuje gotowość, nie pokazuje stosu graczowi.

## 3. P02 — Identity i sesje

### P02-F01 — model kont i logowanie

**Dane:** users: id/public_id, email_normalized UNIQUE, password nullable dla kont bez hasła, email_verified_at nullable, status, auth_epoch BIGINT UNSIGNED default 0, locale; personel przez jawny rejestr ról. Email nie jest publicznym ID.

**api_sessions:** id, user_id FK, device_label VARCHAR(100), created_at, last_used_at, idle_expires_at, absolute_expires_at, revoked_at nullable, auth_epoch_at_issue; INDEX(user_id, revoked_at). Web sesje obsługiwane driverem Laravela; mapa do user/session dla zdalnej revokacji.

**POST /api/v1/auth/login:** email, password, device_label; 200 z access_token, expires_in=900, refresh_token, session_id albo 202 z challenge_id, expires_at, next_step=totp. Neutralny 401 INVALID_CREDENTIALS. Zakaz wydania pełnego tokenu przed 2FA, jeśli wymagane. Hasła i tokeny wyłączone z logowania HTTP.

**Akcje:** AuthenticateCredentials, StartLoginChallenge, IssueSession. Po weryfikacji credentials ponowne sprawdzenie statusu/auth_epoch przed wydaniem; regeneracja sesji web. Hasła wg §14, limit per konto/IP; 2FA obowiązkowe dla wszystkich GM.

**Odbiór:** błędne hasło i nieznane konto mają ten sam komunikat; zablokowane konto bez sesji; brak TOTP GM blokuje dostęp; brak wycieku hasha.

### P02-F02 — TOTP i odzyskiwanie

**totp_credentials:** user_id UNIQUE FK, encrypted_secret TEXT, key_version VARCHAR(32), enabled_at nullable, last_accepted_step BIGINT nullable. Włączenie dopiero po poprawnym kodzie; sekret nigdy w API po zakończeniu konfiguracji.

**auth_challenges:** id, user_id, purpose, secret_hash BINARY(32), attempts UNSIGNED SMALLINT, expires_at, consumed_at nullable, context_hash; INDEX(expires_at).
**recovery_codes:** id, user_id, code_hash BINARY(32) UNIQUE, consumed_at nullable.

**POST /auth/2fa/verify:** challenge_id, challenge_secret, code; 200 sesja albo 401 INVALID_CHALLENGE/INVALID_CODE, 429 dla limitu. Wyzwanie 5 min, 5 prób; kody i wyzwanie zużywane atomowo. Kod TOTP nie jest ponownie akceptowany w tym samym kroku, last_accepted_step pod blokadą. Zegary synchronizowane; konkretna tolerancja kroków jako jawna konfiguracja bezpieczeństwa pod testem, bez zmiany czasów sesji.

**Reset hasła:** standardowy jednorazowy link 60 min, neutralna odpowiedź i limity §14.4. Sukces zwiększa auth_epoch, unieważnia web/API/refresh i prywatne WS. Zmiana 2FA/hasła/e-maila wymaga step-up z ostatnich 15 min. Ręczne odzyskanie 2FA ma odrębny audit i weryfikację tożsamości; personel nie wyłącza jej samą znajomością e-maila.

**Odbiór:** dwa użycia kodu odzyskiwania → jeden sukces; szósta próba odmowa; wyzwanie po deadline bez tokenu; reset odcina stare połączenie WS.

### P02-F03 — refresh z bezpiecznym retry

**refresh_credentials:** id, session_id FK, token_hash BINARY(32) UNIQUE, issued_at, consumed_at nullable, replaced_by_id nullable; indeks(session_id).
**refresh_replay_results:** session_id, request_key, request_hash BINARY(32), used_token_hash BINARY(32), encrypted_result TEXT, encryption_key_version, expires_at; UNIQUE(session_id, request_key), INDEX(expires_at).

Tokeny losowe o wysokiej entropii; trwały rejestr wyłącznie hashe. Wyjątek: wynik odpowiedzi szyfrowany na 2 minuty.

**POST /auth/refresh:** refresh_token i Idempotency-Key. Transakcja blokuje sesję i credential, sprawdza auth_epoch, revokację, 30 dni bezczynności/90 dni absolutne. Identyczny request z zużytym tokenem sprawdza replay result i zwraca ten sam wynik. Inny request zużytego tokenu revokuje rodzinę. Nowa rotacja zapisuje consumed/replaced_by, nowy token i zaszyfrowany wynik atomowo.

**Błędy:** 401 SESSION_EXPIRED/SESSION_REVOKED/REFRESH_REUSE; 409 IDEMPOTENCY_KEY_REUSED przy innym payloadzie tego samego request_key; 429 limiter. Po wygaśnięciu replay klient może wymagać login. Job usuwa wygasłe encrypted_result; nie usuwa wcześniej dowodu zużycia tokenu.

**Odbiór:** utrata odpowiedzi i retry; dwa równoległe refresh; purge po 2 min; rotacja klucza szyfrowania; brak jawnych tokenów w logach. Klient serializuje refresh, nie tworzy nowego request_key dla retry.

### P02-F04 — własne sesje, step-up i Google

GET /me zwraca public_id, locale, bezpieczne dane profilu/capabilities; GET /me/sessions zwraca wyłącznie własne urządzenia bez tokenów. DELETE /me/sessions/{id} revokuje własną sesję, 404 dla cudzej; POST /auth/logout revokuje bieżącą i jest idempotentny.

**step_up_grants:** id, session_id, method, authenticated_at, expires_at, purpose; brak przenoszenia między sesjami. Uprzywilejowana akcja ponownie sprawdza deadline i auth_epoch.

**external_identities:** id, user_id FK, provider, subject, verified_email_at_link, linked_at; UNIQUE(provider, subject).
**identity_link_requests:** id, existing_user_id, provider, subject, state, available_at, confirmed_at nullable, request_id; UNIQUE aktywnego powiązania realizowane kontrolą pod blokadą i ograniczeniem finalnego subject.

Google: weryfikacja state/nonce i tożsamości na serwerze; brak automatycznego łączenia po samym e-mailu. Hasło konta lokalnego albo ścieżka verified_email + 48h karencji i powiadomienie wg §14. Nowy user nie powoduje drugiego pakietu startowego; jego przyznanie należy do P06.

**Odbiór:** cudza sesja/subject odmowa; OAuth state replay odmowa; równoległe linkowanie jeden wynik. Integracja Google wymaga skonfigurowanego klienta i testowego adaptera w CI; mock nie jest dowodem produkcyjnego połączenia.

## 4. P03 — Ledger i idempotencja

### P03-F01 — konta, księgowanie i kontrakt Money

**ledger_accounts:** id, company_id nullable dla systemowych, code VARCHAR(64), currency_code VARCHAR(16), balance_minor BIGINT, state_version BIGINT UNSIGNED. Globalny account_key VARCHAR(128) UNIQUE wyliczany jednoznacznie z właściciela/kodu, aby NULL właściciela nie pozwalał duplikować kont SYS. Currency_code to jedna waluta gry; symbole PL/EN/DE wyłącznie prezentacyjne.

**financial_transaction_service:** logiczny niezmienny transfer: id, created_at, from_account_id, to_account_id, amount_minor BIGINT >0, reason_code, actor_type/id, company_id, operation_id, idem_key, sweep_no nullable, balance_version_id. INDEX obu kont po czasie, company/time, operation_id. Jeśli tabela partycjonowana: PK zawiera created_at; globalna deduplikacja w osobnej tabeli niepartycjonowanej. Ograniczenia FK/partycjonowania w docelowym MySQL trzeba sprawdzić przed DDL; nie deklarujemy niemożliwych FK do partycjonowanej księgi.

**PostTransfer:** from/to, amount, reason, actor, balance_version, operation context. Zakaz zero, ujemnej kwoty, from=to i niedozwolonego debetu. Blokady kont rosnąco wg account_id w rank 20. Zapis jednej pary transferu i dwóch aktualizacji salda w transakcji właściciela. BalanceState z CAS; żadnego publicznego endpointu „ustaw saldo”.

**Odbiór:** suma sald transferu zachowana; dozwolony wyjątek długu GBBL tylko w określonej kompensacji; dwa równoległe obciążenia całego salda → jedno. Kwota powyżej safe integer JS wraca jako string bez straty.

### P03-F02 — rezerwacje i idempotencja

**fund_reservations:** id, account_id FK, operation_id, amount_minor>0, state active/captured/released, expires_at nullable, state_version; UNIQUE(operation_id, account_id, reservation_kind); INDEX(account_id, state).
**operation_idempotency:** idem_key BINARY(32) PK (SHA-256 kanonicznego kodowania scope), scope_type, actor_id nullable, company_id nullable, operation, client_key nullable, entity_ref nullable, durable_slot_id nullable, payload_hash BINARY(32), status, result_ref, result_json nullable, created_at, committed_at nullable, expires_at nullable. Rejestr API/operacji może obsługiwać wiele wpisów FTS, nie zakładamy 1 request=1 transfer.
**fts_idempotency_keys:** trwały klucz pojedynczego skutku księgowego z operation_id i numerem/nazwą skutku; UNIQUE(effect_key). Rozdzielenie chroni wielotransferowe operacje i jest mapowane na logiczny kontrakt §29.4a.

HTTP scope: konto+firma+operacja+client_key; domena: event_type+entity_id+durable_slot_id. Scope kanonizowany z typem/długością pól, payload_hash osobno. Jeden klucz nie obejmuje dowolnych komend. HTTP wynik 30 dni; domenowe skutki trwale. DB pending i efekt w tej samej transakcji: brak przejęcia niewidocznego pending. Duplikat committed odtwarza wynik; inny payload 409. Timeout/deadlock cofa próbę i podlega limitowi §23.5a.

**Reserve/Capture/Release:** rezerwacja ma jawny model: pozostaje w saldzie konta, ale zmniejsza available=balance−active_reservations. Capture zamienia rezerwację na transfer i finalizuje ją atomowo; release nie tworzy nowego pieniądza. Nigdy jednocześnie rezerwacja i osobne obciążenie za ten sam koszt. Escrow po P15 to realny transfer do konta depozytu, nie druga rezerwacja tej samej kwoty.

**Odbiór:** release dwa razy no-op; konkurencja nie robi available<0; 1 request z kilkoma FTS odtwarza całą odpowiedź; retry po commicie nie powtarza efektów.

### P03-F03 — fizyczne zasoby i rekoncyliacja

oil_movements/product_movements: id, occurred_at, owner_company_id, from_resource_ref, to_resource_ref, product_code, quantity DECIMAL(16,4)>0, movement_kind, operation_id, effect_key UNIQUE, balance_version_id. Append-only. ResourceRef zawiera typ i publiczny ID; konkretny magazyn/odwiert tworzy moduł właściciel w P11/P12/P19.

Kontrakt RecordMovement sprawdza legalny właścicielski transfer, źródło, pojemność i kontrolowaną stratę/zużycie. Księga ruchu nie może sama z dowolnego JSON utworzyć ropy. Testy fundamentu korzystają z syntetycznego repozytorium zasobu, bez udawania gotowej logistyki.

ReconcileLedger odtwarza saldo i rezerwacje według punktu kontrolnego/historycznej księgi. Rozbieżność zapisuje incident z zakresem blokady, nie aktualizuje salda „dla wyrównania”. Raport read-only w panelu. S14: zasiana różnica wywołuje alarm, a korekta jest nową operacją.

## 5. P04 — Settlement, terminy, outbox

### P04-F01 — wykonanie transakcji i czasu

**company_settlement_state:** company_id PK/FK po P06, last_calc_at, state_version, last_balance_version_id. Wcześniejszy fixture ma wyraźne testowe ID, bez migracji produkcyjnej tworzącej sztuczne firmy.
**simulation_sweeps:** id, scope global/company, started_at, target_at, finished_at nullable, status, balance_version_id, secret_id, error_code nullable, metrics_json; INDEX(status, started_at), INDEX(company_id, target_at) gdy pole company_id użyte.

SettleCompanyRead otwiera transakcję i wywołuje settleWithinTransaction; mutacja ekonomiczna otwiera JEDNĄ transakcję i w niej rozlicza wymagany czas, a następnie wykonuje komendę. Middleware nie zatwierdza wcześniej osobnego rozliczenia dla mutacji. Granica ta zapobiega luce pomiędzy odczytem stanu a obciążeniem.

Plan zasobów obejmuje settlement i komendę; globalny porządek §23.5a, bez odkrywania wcześniejszego rank po późniejszym locku. Złożony zakres retry obejmuje całość. Delta dzielona na segmenty zmian modyfikatorów/nasycenia/terminów, chunk 1h i cap DR 7 dni zgodnie z §23.4–§23.5; CAS last_calc_at/state_version na końcu. Nie ma pętli po sekundach. Globalna cena/matching odseparowane od per-company.

**GET /game/snapshot:** nowy stan i server_time/synced_at/valid_until/rate_version/balance_version/state_version. Dla fundamentu syntetyczne stawki tylko w testach; produkcyjne handlery dołączają moduły. Jeśli transakcja nie dochodzi do commitu, nie zwracamy „świeżego” stanu.

**Odbiór:** ten sam targetAt równolegle dwa razy → jeden efekt; odczyt+mutacja+job nie powielają Δt; ujemny Δt odrzucony; test blokad osobnymi połączeniami MySQL.

### P04-F02 — due-events i długie próby

**due_events:** id, event_type, entity_type/id, owner_company_id, durable_slot_id, due_at, expected_state_version, balance_version_id, payload_version, status scheduled/processed/cancelled, processed_at nullable; UNIQUE(event_type, entity_type, entity_id, durable_slot_id), INDEX(status,due_at).
**operation_attempts:** id, operation_id, attempt_no, lease_until, fencing_token BIGINT UNSIGNED, status, last_error_code; UNIQUE(operation_id, attempt_no), INDEX(status, lease_until). Fencing token aktualizowany monotonicznie przez CAS na rekordzie operacji.

Utworzenie terminu w transakcji domeny + outbox; opóźniony job jest przyspieszeniem. Job niesie event_id, a nie nowe due_at/kwotę. Handler blokuje rekord/encje w zaplanowanym porządku, sprawdza termin, wersję stanu i utrwalony balans. Wczesny job bez skutku; spóźniony działa według logicznego due_at. HTTP/job/sweeper używają jednego klucza domenowego.

Długa próba ma lease i fencing; przejęcie nie zmienia operation_id/event key. Stary worker po przejęciu nie może zatwierdzić efektu. Sweeper co 5 min odnajduje zaległe terminy niezależnie od sukcesu dispatch. Błąd integracji nie cofa processed ekonomii.

**Odbiór:** h/i/j z §23.10, S06/S07; stary worker po wygaśnięciu lease; utracony dispatch; event odwołany po zaplanowaniu; brak ponownego RNG.

### P04-F03 — outbox i konsumenci

**outbox_events:** event_id PK, aggregate_type/id, aggregate_version, type, schema_version, payload_json, occurred_at, available_at, status, attempts, lease_until nullable, delivered_at nullable; INDEX(status,available_at), INDEX(aggregate_type,aggregate_id,aggregate_version).
**consumer_receipts:** consumer_key, event_id, processed_at; UNIQUE(consumer_key,event_id).

Append w transakcji właściciela, dispatcher po commicie z lease. Duplikaty dozwolone w transporcie; konsument atomowo zapisuje skutek i receipt. Dla sieci nie obiecujemy exactly-once: identyfikator dostawcy/rekoncyliacja; retry zachowuje event_id. Wersja agregatu zapobiega nadpisaniu nowszego stanu starą deltą.

Polityka retry to jawna konfiguracja konsumenta: liczba prób, backoff/jitter, limit czasu, dead-letter i alarm; karty e-mail/push/operatora ustalą różne wartości. Fundament dostarcza adapter testowy i trwały dead-letter, nie sam nieskończony retry.
Panel: oldest_age, lag, kod błędu, audytowany retry bez edycji payloadu.

**Odbiór:** awaria przed commitem brak eventu; po commicie bez dispatch odzyskanie; po wysłaniu bez delivered_at duplikat neutralny; receipt i skutek w jednej transakcji.

## 6. P05 — Admin, balans i aktywacja

### P05-F01 — role i audit

**staff_role_assignments:** user_id, role, granted_by, granted_at, revoked_at nullable. Jedyny owner_admin zabezpieczony osobnym singletonem ownership, nie tylko kontrolą formularza. Zmiana pod wspólną blokadą activation_gate używaną również przez ReviewBalance; nie można usunąć/degradować właściciela. Dzięki temu odebranie/nadanie roli nie ściga się z liczeniem aktywnych administratorów.
**gm_action_log:** id, actor_id, role_snapshot, action_code, target_ref, request_id, before_json/after_json redacted, reason nullable, approval_ref nullable, occurred_at. Append-only, INDEX(actor_id,occurred_at), INDEX(target_type,target_id,occurred_at).

Wszystkie role GM TOTP, step-up dla wrażliwych działań, polityki capability. owner_admin nie musi wpisywać wolnego komentarza/powodu; wymagane merytoryczne dane operacji pozostają. gm_support podaje powód każdej mutacji. Audit i skutek tej samej transakcji; niedozwolona akcja bez skutku.

**Odbiór:** drugi owner odrzucony przy równoległości; 403 po zabraniu roli; owner bez reason działa i ma audit; support bez reason 422.

### P05-F02 — kandydatura i testy balansu

**balance_candidates:** id, base_version_id, revision BIGINT, draft_json, proposed_by, state draft/submitted, submitted_at nullable; UPDATE tylko draft z CAS revision.
**balance_versions:** id, candidate_id UNIQUE, params_json, canonical_yaml, yaml_hash BINARY(32), INDEX(yaml_hash), schema_bundle_hash, release_sha, created_by, created_at. Snapshot niezmienny; identyczny hash treści jest dozwolony dla nowej wersji/rollbacku, lecz candidate_id pozostaje UNIQUE. Approval wiąże version_id, hash i release, nie sam hash.
**balance_test_runs:** id, version_id, yaml_hash, release_sha, harness_version, status, fail_count, report_json, finished_at; INDEX(version_id,status).
**balance_version_events:** id, version_id, event_type, actor_id, approval_mode nullable, reason nullable, request_id, occurred_at; append-only.
**balance_activation_state:** version_id UNIQUE, status, state_version, scheduled_at nullable, activated_at nullable, sweep_id nullable.

ProposeBalance zapisuje draft. SubmitBalance waliduje wszystkie schematy, materializuje pełny snapshot, oblicza hash kanonicznego YAML i blokuje edycję zgłoszonej treści. Zmiana po submit to nowy draft. RunBalanceChecks działa na dokładnym YAML/release/schema hash; raport starego hasha nie uprawnia do aktywacji. Bazowy symulator v1 nie jest kompletnym harness kandydujących wersji — przed produkcyjną edycją balansu trzeba wdrożyć testy niezmienników i wersjonowane cele wg §29.5.

**UI:** drzewo wszystkich liści params, jednostki i zakresy, diff, raport, historia. Bez terminala/edytora PHP w panelu. Nie ma publicznej trasy gracza do zmiany balansu.

**Odbiór:** zmiana payloadu po teście unieważnia approval; nieznany klucz schematu blokuje; brak raportu nie oznacza PASS; scenariusz celowo złą sumą receptury daje FAIL.

### P05-F03 — review, aktywacja i rollback

ReviewBalance wymaga uprawnionego admina i TOTP; liczba aktywnych uprawnionych administratorów sprawdzona atomowo z decyzją. Przy co najmniej dwóch: autor nie zatwierdza własnej. Jedyny owner_admin: zwykła wersja, 2FA, typed-confirm i 0 FAIL, approval_mode=single_owner. Emergency zawsze dwóch adminów i audit, zgodnie z §29.7; wyjątek single_owner nie działa. owner nie musi powodu.

Jedna blokada activation_gate serializuje decyzje dotyczące stanu globalnej aktywacji; jej miejsce w planie transakcji konfiguracji jest przed odczytem listy administratorów i runtime. Nie łączymy tego z długą transakcją gospodarczą. Aktywacja sprawdza base_version: nieaktualna kandydatura wymaga nowego przeliczenia/diff, nie cichego nadpisania cudzych zmian.

ActivateApprovedBalance: na granicy sweepa, jeden aktywny globalny snapshot, CAS aktywnej wersji i zapis zdarzenia; procesy w toku zachowują wersję startową. Instancje web/worker potwierdzają obsługę release/schema przed aktywacją. Emitowany outbox balance.activated po commicie.

RollbackBalance tworzy nową kandydaturę z treści wersji historycznej; ta sama bramka, brak UPDATE starego snapshotu. Rejected nie przechodzi do active. Draining modułu jest skutkiem zatwierdzonego enabled=false i korzysta z P01, bez wyłączenia handlerów istniejących zobowiązań.

**Błędy:** SELF_APPROVAL_FORBIDDEN 403, SECOND_ADMIN_REQUIRED 409, BALANCE_TEST_FAILED 422, STALE_BASE_VERSION 409, MANIFEST_INCOMPATIBLE 409.
**Odbiór:** dwa równoległe approval/activate, pojawienie się drugiego admina, emergency jedynego owner, stary raport, rollback i rozpoczęty kurs. Aktor i decyzja nie giną po timeout.

## 7. Kolejność commitów i odbiór fundamentu

1. P01-F01/F03: rejestr, Clock/Actor, konwencje błędów, test architektury.
2. P02-F01/F02: konta, sesje, TOTP i reset; P05-F01 minimalne role/audit potrzebne testom panelu.
3. P03-F01/F02: konta, transakcja, rezerwacje, idempotencja i test konkurencji.
4. P04-F01/F02/F03: Settlement, terminy, lease/outbox i test awarii.
5. P02-F03/F04: pełna rotacja/revokacja, sesje i identity linking.
6. P03-F03 oraz P05-F02/F03: księga ruchu, rekoncyliacja, balans i aktywacja.
7. Pierwsza ścieżka transferu dwóch syntetycznych kont → API/web → audit i outbox; potem P06 z prawdziwą firmą.

Pakiety nie czekają na koniec wszystkich warstw, aby napisać test. Każdy commit ma właściwy test i migrację expand. Fixtures są syntetyczne; nie ma kopiowania sekretów ani produkcyjnych sesji. Wydanie fundamentu nie aktywuje niezaimplementowanej gospodarki.

## 8. Macierz testów i definicja zakończenia

| Obszar | Testy wymagane |
|---|---|
| P01 | manifest/ścieżki/cykle/kontrakty/CAS/draining, bootstrap HTTP/CLI |
| P02 | login/TOTP/reset/step-up/Google, replay refresh 2 min, 30/90 dni, revokacja WS |
| P03 | transfer/rezerwacja/efekt wielotransferowy, precision, rollback, konkurencja MySQL |
| P04 | delta/plateau/CAS, due_at i trzy wyzwalacze, lease/fencing, outbox/receipt |
| P05 | role/owner/reason, hash testów, single_owner/emergency, aktywacja/rollback |

Dla każdej karty: pozytywny proces, odmowa cudzej operacji, walidacja, retry i właściwy scenariusz S01–S18. Widoki: PL/EN/DE, SVG, dostępność i rozmiary 320/360/390/768/1024/1440. Transakcje i blokady na MySQL, nie tylko SQLite. Zależności testów i warunki uruchomienia muszą być w CI.

Karty nie dostarczają jeszcze kompletnego OpenAPI wszystkich modułów ani wykonywalnych migracji. Programista przed migracją porównuje faktyczny schemat i zapisuje typy/FK/indeksy zgodne z konwencją §1; nie wolno nadpisać wdrożonej migracji. Gotowość karty do przyjęcia w commicie potwierdza implementacja, test i przegląd; dokument nie otrzymuje fikcyjnego statusu wdrożony.
