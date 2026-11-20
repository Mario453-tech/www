# OilEmpire — kontrakt implementacyjny Kadr HR-CONTRACT01

Status: specyfikacja wykonawcza wariantu A dla P07–P09 i zależności P16, 18.11.2026. Dokument opisuje docelowy nowy silnik Laravel, a nie polecenie modyfikacji starego PHP ani gotowe migracje. Rozszerzenia B korzystają z tych samych granic Staff. Źródła reguł: [Kadry §19](OILEMPIRE_KADRY_ZATRUDNIENIE_ROZSZERZENIE.md), [płace HR-PAY-01](OILEMPIRE_KADRY_UMOWY_PLACE_ODPRAWY.md), [karty P07–P09](OILEMPIRE_KARTY_IMPLEMENTACJI_P06_P13.md), [API](OILEMPIRE_API_SPECYFIKACJA_TECHNICZNA.md), [bezpieczeństwo](OILEMPIRE_BEZPIECZENSTWO_SILNIKA.md), [panel](OILEMPIRE_PANEL_ADMINA_SPECYFIKACJA.md).

Ten dokument jest **deltą do starych kart P07–P09**: zastępuje ich uproszczone pola `employees.salary_monthly_minor`, `employee_availability.active_job_*` i `candidate_offers.traits_json` jako pełne źródła prawdy. Pozostałe niezmienniki kart i wspólny standard nadal obowiązują. Przy sprzeczności liczb ze specyfikacją domeny wymagane jest rozstrzygnięcie w dokumentacji; nie wybiera się cichszego wariantu w kodzie.

## 1. Granice kodu i właściciele zapisu

Jedna aplikacja Laravel, jeden moduł Staff z częściami `Recruitment`, `Employment`, `Scheduling`, `Absences`, `Payroll`, `Training`, `Assessments`. Każda ma własne Actions/Queries/Policies, a wspólne Value Objects tylko dla identyfikatorów, czasu, pieniędzy i wersji. Kontroler API, kontroler web/Inertia i Filament Action wywołują tę samą Action aplikacyjną. Nie ma domenowego `StaffService` obejmującego cały moduł.

| Część | Zapisuje | Odczytuje przez kontrakt |
|---|---|---|
| Recruitment | kampanie, kandydatury, oferty | Staff person/role policy, Finance quote |
| Employment | osoby, okresy zatrudnienia, umowy, zdarzenia zakończenia | Recruitment offer, Scheduling availability, Payroll dues |
| Scheduling | grafiki, rezerwacje czasu, przydziały | Employment terms, właściciel obiektu |
| Absences | urlop, chorobę, zastępstwo | Scheduling plan, Employment terms |
| Payroll | należności i komponenty płacowe | Employment terms, Absences facts, Scheduling worked time |
| Training | kurs, egzamin, certyfikat | Scheduling reservation, Employment status |
| Assessments | obserwacje, podstawy, oceny, zlecenia analiz | publiczny fakt domenowy i kontekst HR, nigdy hidden trait |

Finance/Ledger księguje pieniądze; Staff tworzy source_key i zleca zapis. Moduł źródłowy, np. Logistics, publikuje obserwowalny fakt; Assessments nie czyta jego prywatnych tabel. Zdarzenia wychodzą z outbox w tej samej transakcji co zmiana domenowa.

## 2. Typy wspólne i niezmienniki

- `company_id` wskazuje firmę gospodarczą; `person_id` trwałą osobę; `employment_id` jeden okres pracy tej osoby w firmie. Publiczne ID to ULID. `employee_id` ze starszych kart interpretować jako `employment_id`, chyba że karta mówi o profilu trwałym. Nie wydawać dwóch zatrudnień z jednej oferty.
- Każdy agregat firmowy ma `company_id` albo obowiązkowy FK do rodzica z `company_id`; zapytanie i mutacja sprawdzają zakres firmy w bazie i polityce. W tabelach wielofirmowych oraz indeksach częstych odczytów utrwalamy `company_id` jawnie, z kontrolą zgodności z rodzicem. ID nie jest uprawnieniem. Obserwacje HR firmy A nie przechodzą do B.
- Czas: UTC millisecond, przedziały [from,to), `logical_at` i `recorded_at` odrębne. Aktywny czas firmy i pauzy z Simulation; bez własnego zegara Staff.
- Kwoty: BIGINT w groszach, JSON string `*_minor`; GBBL osobna jednostka. Brak float. Wersje polityk/umów i ceny quote utrwalone.
- `state_version` BIGINT dla CAS. Mutacje gospodarcze z `Idempotency-Key`, `If-Match` lub `expected_state_version` według API §5. Jedna intencja = jedna odpowiedź replay. Brak ponownego losowania przy GET/retry.
- Ukryty profil jest serwerowy; DTO, filtry, eksport, logi, WebSocket i cache gracza nie zawierają jego wartości. GM osobny capability i audyt.
- Typy enumerowane w kodzie i bazie przez ograniczone słowniki; nie dopuszczać dowolnego strategy_code/JSON wywołującego kod.
- Brak fizycznego usuwania osoby, umowy, dowodu, wypłaty lub certyfikatu przez zwykłą akcję. Korekta append-only.

## 3. Docelowy model danych — tabele fizyczne

Dla nowego silnika Staff A wybieramy wewnętrzne `BIGINT UNSIGNED` (zgodne z `$table->id()` szkieletu) i osobne publiczne ULID `CHAR(26) ascii_bin`. Tabele fundamentu `users` oraz `companies` muszą mieć `id BIGINT UNSIGNED` przed uruchomieniem schematu. [Pełny kontrakt SQL dla MySQL 8.4](OILEMPIRE_KADRY_DDL_MYSQL84.sql) podaje fizyczne kolumny, FK, indeksy i CHECK; ten rozdział streszcza ich role. Dla `from/to` nazwy fizyczne to `from_at/to_at`. InnoDB, utf8mb4, UTC; ograniczenia przekrojowe, w tym overlap i uprawnienia, Action sprawdza pod blokadą. SQL jest schematem greenfield, nie poleceniem migracji danych produkcyjnych.

| Tabela / właściciel | Pola kluczowe | Relacje, unikalność, indeks |
|---|---|---|
| `staff_persons` / Employment | public_ulid, generation_version, creation_key, status | UNIQUE(public_ulid), UNIQUE(creation_key); globalna blokada person; nie zawiera firmy ani oceny |
| `staff_person_traits` / Employment | person_id, trait_code, signed_value, generation_version | FK person RESTRICT, UNIQUE(person_id,trait_code); serwerowa allowlista; brak powielania starych loyalty/ambition |
| `staff_person_skills` / Employment/Training przez publiczną akcję | person_id, skill_code, level, version | FK person RESTRICT, UNIQUE(person_id,skill_code); Training nie robi prywatnego UPDATE |
| `staff_legacy_refs` / Employment Migration | person_id, legacy_type, legacy_id, evidence_code, migration_batch | FK person RESTRICT, UNIQUE(legacy_type,legacy_id); cross-company konflikt=unresolved |
| `staff_employments` / Employment | person_id, company_id, role_code, department_code, started_at, ended_at nullable, status, source_offer_id nullable, generated active_person_id | FK person/company/offer RESTRICT; UNIQUE(active_person_id) daje jeden otwarty okres pracy osoby we wszystkich firmach, UNIQUE(source_offer_id), indeksy company/status/role i person/start |
| `staff_contract_versions` / Employment | employment_id, version_no, form_code, term_code, wage_basis, actual_amount_minor, fte_num/den, effective_from/to, accepted_at, policy_version | FK employment RESTRICT, UNIQUE(employment_id,version_no), INDEX(employment_id,effective_from); niezmienne po akceptacji |
| `staff_termination_quotes` / Employment | employment_id, company_id, request_hash, contract_version_no, amount_minor, components_json, policy_version, expires_at, consumed_at, source_key | FK employment+company RESTRICT, UNIQUE(source_key), INDEX(employment_id,expires_at); trwały snapshot zacytowanego kosztu |
| `staff_employment_events` / Employment | employment_id, event_type, logical_at, source_event_key, payload allowlist | FK employment RESTRICT, UNIQUE(employment_id,event_type,source_event_key); append-only |
| `staff_recruitment_campaigns` / Recruitment | company_id, role_code, channel_code, reach_code, tier_code, price_minor, currency, balance_version, due_at, status, rng_slot | FK company RESTRICT, UNIQUE(company_id,rng_slot), INDEX(company_id,status,due_at) |
| `staff_candidate_offers` / Recruitment | campaign_id, person_id, offer_slot, terms_snapshot, expires_at, status, consumed_at | FK campaign/person RESTRICT, UNIQUE(campaign_id,offer_slot), INDEX(person_id,status); snapshot nie ujawnia hidden |
| `staff_roster_versions` / Scheduling | company_id, period_from/to, policy_version, status, published_at | INDEX(company_id,period_from,status); opublikowana wersja niezmienna |
| `staff_roster_intervals` / Scheduling | roster_version_id, employment_id, from/to, kind, planned_seconds | FK roster/employment RESTRICT, INDEX(employment_id,from,to), UNIQUE(roster_version_id,employment_id,from,kind) |
| `staff_time_reservations` / Scheduling | employment_id, from/to, purpose, owner_ref, status, source_key | UNIQUE(source_key), INDEX(employment_id,status,from,to); overlap pod blokadą employment/availability |
| `staff_assignments` / Scheduling | employment_id, target_type/id, allocation_bps, assigned_at, released_at, source_key | UNIQUE(source_key), INDEX(employment_id,released_at), INDEX(target_type,target_id,released_at); target ownership waliduje właściciel |
| `staff_leave_accounts` / Absences | employment_id, leave_type, accrual_year, balance_seconds, reserved_seconds | UNIQUE(employment_id,leave_type,accrual_year), CHECK balance/reserved≥0; projekcja księgi, roczniki i typy odrębne |
| `staff_leave_ledger` / Absences | account_id, entry_type, seconds, source_key, effective_at, recorded_at | FK account RESTRICT, UNIQUE(source_key), INDEX(account_id,effective_at); acquire/reserve/use/release/correct |
| `staff_absences` / Absences | company_id, person_id, employment_id, type, from/to, status, episode_key, policy_version | UNIQUE(episode_key), INDEX(employment_id,from,to), INDEX(company_id,status,from); cooldown choroby liczony dla person_id |
| `staff_replacement_orders` / Absences | company_id, absence_id, quote_snapshot, cost_minor, provider_ref nullable, arrival_at, status, source_key | UNIQUE(source_key), INDEX(company_id,status,arrival_at); unikalną dostępność provider+interval rezerwuje dostawca; brak oferty nie tworzy pracownika |
| `staff_pay_entitlements` / Payroll | employment_id, period_from/to, component_code, exact_accumulator, posted_minor, paid_minor, policy_version, entitlement_key, status | UNIQUE(entitlement_key), INDEX(employment_id,period_from,component_code); przyrosty mają osobne effect keys |
| `staff_trainings` / Training | employment_id, program_version_id, started_at, due_at, status, paid_minor, rng_slot | UNIQUE(employment_id,rng_slot), INDEX(employment_id,status,due_at) |
| `staff_training_program_versions` / Training | program_code, version_no, skill_code, level, duration_seconds, price_minor, pass_threshold, policy_version | UNIQUE(program_code,version_no); aktywne wersje niezmienne |
| `staff_certificates` / Training | person_id, employment_id, training_id, type, certificate_code, issued_at, expires_at nullable, source_key | UNIQUE(source_key), FK person/training RESTRICT; certyfikat ukończenia vs uprawnienie jako type |
| `staff_observations` / Assessments | company_id, person_id, employment_id, source_key, revision, domain, logical_at, independence_key, payload allowlist, quality | UNIQUE(company_id,source_key,revision), INDEX(company_id,person_id,domain,logical_at); append-only |
| `staff_assessment_runs` / Assessments | company_id, person_id, domain, as_of, method_version, input_hash, status | UNIQUE(company_id,person_id,domain,method_version,input_hash); technical run ≠ czas analityka |
| `staff_assessment_snapshots` / Assessments | run_id, stage, direction, confidence_band, freshness, basis_summary, created_at | UNIQUE(run_id), FK run RESTRICT; immutable |
| `staff_assessment_current` / Assessments | company_id, person_id, domain, snapshot_id, state_version | PK(company_id,person_id,domain), FK snapshot RESTRICT; CAS chroni nowszy wynik |
| `staff_assessment_evidence` / Assessments | snapshot_id, observation_id, revision, weight, reason_code | UNIQUE(snapshot_id,observation_id,revision); FK RESTRICT |
| `staff_analysis_work` / Assessments | company_id, person_id, domain, slot, priority, ready_at, required_seconds, worked_seconds, analyst_employment_id, status, dirty, state_version | UNIQUE(company_id,person_id,domain,slot), INDEX(company_id,status,priority,ready_at) |
| `staff_policy_versions` / Staff config | policy_group, version_no, effective_from, hash, status, approved_by, approved_at | UNIQUE(policy_group,version_no), INDEX(policy_group,status,effective_from); immutable active |
| `staff_policy_items` / Staff config | version_id, code, strategy_code, typed_value, unit, min/max, scope_key | UNIQUE(version_id,code,scope_key), FK version RESTRICT; walidowane strategie |
| `staff_policy_current` / Staff config | policy_group, version_id, state_version | PK(policy_group), FK version RESTRICT; jedna aktywna wersja |
| `staff_recruitment_quotes` / Recruitment | company_id, public_id, request_hash, amount_minor, currency, policy_version, expires_at, consumed_at, source_key | UNIQUE(public_id), UNIQUE(source_key), INDEX(company_id,expires_at) |
| `staff_leave_requests` / Absences | employment_id, leave_type, from/to, status, requested_seconds, policy_version, source_key, state_version | UNIQUE(source_key), INDEX(employment_id,status,from) |

Pola szczegółowego kontekstu, kalkulacji i pełny zestaw indeksów trzeba dopisać w finalnych plikach migracji/Schema; tabela powyżej jest minimalnym kontraktem, nie fikcyjnym pełnym CREATE TABLE. Nie używać CASCADE dla historycznych faktów. `staff_analysis_work` ma generated `active_slot` i UNIQUE(company_id,person_id,domain,active_slot), co chroni najwyżej jedno niezakończone zlecenie; sam UNIQUE(slot) tego nie zapewnia. `staff_employments.active_person_id` jest analogicznym fizycznym guardem zatrudnienia.

## 4. Zgodność ze starym schematem i przeniesienie kont

Eksport struktury 180 tabel i mapa znajdują się w Kadrach §18.18–18.20. Nowy schemat jest docelowy, nie żąda edycji starego kodu. Przy migracji danych:
1. `board_members`, `technical_staff`, `employee_source_links` → `staff_persons` + `staff_legacy_refs`; lustrzana para tworzy jedną osobę tylko po sprawdzeniu obu końców i company/player mapy.
2. Stare aktywne zatrudnienie → `staff_employments`; brak udokumentowanego końca/ponownego startu nie tworzy fikcyjnych epizodów. `employee_contracts.contract_type` 6m/1y/2y → `term_code`, nigdy `form_code`. Historyczne nieznane formy oznaczyć `legacy_unspecified` i ograniczyć nowe akcje wymagające formy do wyjaśnienia/migracji.
3. `employee_state` → bieżący stan/projekcja, nie historia ocen. `employee_assignments` i `well_staff_assignments` → jedna projekcja z linkiem źródłowym; niespójność unresolved, bez drugiej obsady.
4. `staff_trainings`, `technical_staff_skills`, `training_certificates`, `employee_certificates` → zachować źródłowe ID, kwalifikacje i osiągnięcia, nie scalać certyfikatu uprawnienia z potwierdzeniem kursu po nazwie.
5. `candidate_reviews` nie importować jako gotowe oceny supported; brak pełnej podstawy. Nie losować wstecz ukrytych cech historycznych osób przy GET.
6. Kwoty ze starych INT/DECIMAL przeliczać po jawnej mapie jednostek i porównaniu sum; niezidentyfikowane daty zerowe/kolizje do raportu unresolved.

Migracje expand, snapshot metadanych, backfill checkpointowany, porównanie liczności/sum, cutover i rollback opisane w §18.20. Testy wykonujemy na danych syntetycznych lub bezpiecznej kopii. Import nie jest warunkiem projektowania nowego silnika; jeśli gra startuje bez historycznych kont, adapter migracji pozostaje osobnym etapem.

## 5. Akcje, przejścia i zdarzenia

| Akcja / część | Wejście i wynik | Atomowy zapis + zdarzenie |
|---|---|---|
| `QuoteCampaign` / Recruitment | role, channel, reach, tier, terms → cena/ważność | read-only; bez losowania |
| `StartCampaign` | quote_id/version, Idempotency-Key → campaign/due_at | limit2, Ledger debit, campaign, due-event, outbox CampaignStarted |
| `GenerateOffers` | campaign_id/due_slot →0..N ofert | trwały RNG, person/offers, outbox OffersReady; retry no-op |
| `HireEmployee` / Employment | offer, accepted terms → employment/contract version | consume offer, person+employment+contract, outbox EmployeeHired; bez reroll |
| `AmendContract` | employment/version, new terms, effective_from → version | settle old segment, next version, outbox ContractAmended |
| `EndEmployment` | employment/version, reason, quote → notice/end dues | decyzja zapisuje notice i due-event; dopiero logiczny koniec finalizuje Payroll, zwalnia przyszłe rezerwacje i emituje EmploymentEnded; historia zostaje |
| `PublishRoster` / Scheduling | period, shifts, expected version → roster version | limity czasu/odpoczynku i coverage, outbox RosterPublished |
| `ReserveTime` | employment, interval, purpose → reservation | jeden kalendarz; overlap odmowa, outbox TimeReserved |
| `RequestLeave/ApproveLeave` / Absences | interval, policy/version → status/ledger | konto+coverage pod blokadą, outbox LeaveApproved/Rejected |
| `RecordIllness` | person+logical_slot → episode | utrwalony RNG, brak overlap/cooldown, outbox AbsenceStarted |
| `RequestReplacement` | quote/absence/mandate → order | oferta nie gwarantowana; rezerwacja konkretnej dostępności i Ledger raz |
| `AccruePay/FinalizePay` / Payroll | employment/period/segment → należność | accumulator+source key, Finance FTS raz, outbox PayAccrued/Finalized |
| `EnrollTraining/CompleteExam` / Training | program/version/employment → training/result | czas/fee raz, jeden RNG egzaminu, skill/certificate raz, outbox TrainingCompleted |
| `RecordObservation` / Assessments | trusted producer fact → observation | source+revision dedupe, outbox ObservationRecorded |
| `Request/Compute/PublishAssessment` | person/domain/slot → snapshot | work queue, immutable input hash, CAS current, outbox AssessmentUpdated |

`EndEmployment` jest procesem dwuetapowym, gdy okres wypowiedzenia trwa: decyzja i due-event zakończenia; nie wykonuje Payroll finalization przy samej decyzji. Remis egzamin/koniec według §19.3. Zdarzenia w outbox niosą ID, company, logical_at i wersję, nie ukryte cechy ani pełny payload. Gdy Ledger/Staff wymagają jednej transakcji, zapis odbywa się na jednym połączeniu MySQL i wspólnym LockPlan; handler asynchroniczny nie udaje atomowej płatności.

## 6. HTTP API v1 — kontrakt delta

Wszystkie ścieżki zaczynają się od `/api/v1`; web i mobile używają tych samych Action. JSON UTC ISO 8601 ms, pieniądze string `*_minor`. Mutacje mają `Idempotency-Key`; wymagany `expected_state_version` albo `If-Match` przy edycji istniejącego agregatu. Odpowiedź `{data,meta}`, błąd `{error:{code,message,details,request_id}}` wg głównego API. Cudzy rekord zwraca404 po polityce; brak prawa do klasy akcji403.

| Metoda i ścieżka | Action / odpowiedź | Kody domenowe |
|---|---|---|
| GET `/hr/recruitment/catalog` | ceny, legalne kanały, wersja | — |
| POST `/hr/recruitments/quotes` | QuoteCampaign → quote_id, price_minor, expires_at, version | INVALID_CHANNEL, INVALID_ROLE |
| POST `/hr/recruitments` | StartCampaign → 202 campaign_id,due_at | QUOTE_STALE, RECRUITMENT_LIMIT, INSUFFICIENT_FUNDS |
| GET `/hr/recruitments/{id}` | status/oferty publiczne | — |
| GET `/hr/candidates` | własne oferty, paginacja | — |
| POST `/hr/candidates/{id}/hire` | HireEmployee →201 employment_id, contract_version | OFFER_EXPIRED, OFFER_CONSUMED, TERMS_CHANGED |
| GET `/hr/employees`, GET `/hr/employees/{id}` | dane własnej firmy, aktualny stan i assessment DTO | — |
| POST `/hr/employees/{id}/contract-versions` | AmendContract →201 version | STATE_CHANGED, INVALID_TERMS |
| POST `/hr/employees/{id}/termination-quotes` | quote kosztu → quote_id,expires_at,components | — |
| POST `/hr/employees/{id}/terminations` | decyzja/koniec →202 operation_id | QUOTE_STALE, INVALID_REASON, COVERAGE_RISK |
| GET `/hr/rosters`, POST `/hr/rosters` | podgląd/publikacja wersji | REST_VIOLATION, COVERAGE_GAP |
| PUT `/hr/employees/{id}/assignments` | pełny zestaw przydziałów | CAPACITY_EXCEEDED, EMPLOYEE_UNAVAILABLE |
| GET/POST `/hr/leave-requests` | lista/wniosek urlopowy | LEAVE_BALANCE, OVERLAP |
| POST `/hr/leave-requests/{id}/approve` | ApproveLeave | COVERAGE_GAP, STATE_CHANGED |
| GET `/hr/absences` | własne fakty nieobecności | — |
| GET `/hr/training-programs`, POST `/hr/trainings` | katalog/zapis | INELIGIBLE, TIME_CONFLICT, INSUFFICIENT_FUNDS |
| GET `/hr/trainings/{id}` | stan i wynik własny | — |
| GET `/hr/employees/{id}/assessments` | publiczne oceny, aktualność | — |
| GET `/hr/assessments/{id}/basis` | paginowane, redagowane dowody | — |
| POST `/hr/employees/{id}/assessment-requests` | żądanie analizy→202/replay | NO_HR_ANALYST, ALREADY_QUEUED, RATE_LIMITED |

Dla `ALREADY_QUEUED` zwrócić istniejące `operation_id` jako202, gdy payload/intencja zgodne;409 tylko dla konfliktu wersji lub innego żądania. Brak analityka nie usuwa dowodów: zwracamy202 queued z kodem stanu `waiting_for_analyst` i bez ETA. `NO_HR_ANALYST` pozostaje kodem diagnostycznym, nie błędem HTTP dla przyjętego zlecenia. Brak baseline zwraca `unassessed` w GET, a POST oznacza waiting_evidence zamiast fikcyjnego wyniku. Rate limit według głównego API §10.1, bez osobnego nieuzgodnionego limitu HR.

Przykład startu kampanii:
```json
{"quote_id":"01J00000000000000000000001","expected_quote_version":1}
```
Odpowiedź:
```json
{"data":{"id":"01J00000000000000000000002","status":"searching","due_at":"2026-11-18T12:05:00.000Z","price_minor":"1200000","currency":"PLN"},"meta":{"balance_version":"hr-a-1"}}
```
Przykład błędu:
```json
{"error":{"code":"QUOTE_STALE","message":"Oferta zmieniła się. Sprawdź nową wycenę.","details":{"quote_id":"01J00000000000000000000003"},"request_id":"01J00000000000000000000004"}}
```
[Pełny kontrakt OpenAPI 3.1 Staff A](OILEMPIRE_KADRY_OPENAPI_V1.yaml) obejmuje 36 operacji (rekrutacja, umowy, zarząd, grafiki, urlopy, płace, spory, szkolenia i oceny). Tabela powyżej jest skrótem. [DOM03/DOM04](OILEMPIRE_DOM_DECYZJE_WLASCICIELA_2026_11_18.md) mają decyzję właściciela do programowania. Do publikacji wersjonowanej polityki zależny endpoint nadal zwraca `POLICY_NOT_APPROVED`; [DOM03-A](OILEMPIRE_DOM03_NEGOCJACJE_WARIANT_A.md) definiuje wejściowy wynik żądania grupowego; odrębny wynik rundy ugody zachowuje bramkę P08-F04.

## 7. Uprawnienia i widoczność

| Capability | Gracz uprawniony w firmie | Moderator/GM | Owner admin |
|---|---|---|---|
| staff.view | własna firma | zgodnie z zakresem sprawy | tak |
| staff.board.manage | mandat zarządu w firmie | tylko wyraźna akcja GM | tak |
| staff.recruitment.manage | mandat HR/rekrutacji | tylko wyraźna akcja GM | tak |
| staff.employment.manage | mandat zatrudniania | tylko wyraźna akcja GM | tak |
| staff.roster.manage | mandat obsady | tylko wyraźna akcja GM | tak |
| staff.leave.manage | mandat urlopowy | tylko wyraźna akcja GM | tak |
| staff.training.manage | mandat szkoleń | tylko wyraźna akcja GM | tak |
| staff.assessments.view | oceny swojej firmy | scope sprawy | tak |
| staff.assessment_policy.activate | nie | nie, chyba jawnie przyznane | tak |
| staff.hidden_traits.view | nie | nie domyślnie; osobny przydział i audyt | osobna capability, audyt |

Moderator nie otrzymuje ukrytego profilu przez zwykły podgląd pracownika. Admin/owner używają osobnych akcji, automatyczny audit actor/target/version/time; owner bez obowiązkowego komentarza. Filament ukrywa niedozwolone przyciski, ale polityka Action rozstrzyga po stronie serwera. API, export, cache i websocket bazują na tym samym safe DTO. Pole `traits_json` ze starych kart nie trafia do DTO ani analityki.

## 8. Transakcje i blokady

Obowiązuje dokładnie globalny porządek §23.5a: operation_idempotency rank1 → operations lease rank5, gdy dotyczy → companies rank10 → company_settlement_state rank11 → due_events rank12 → Core rank13 → Staff rank14 → pozostałe domeny zgodnie z tabelą → Ledger rank20 → append-only rank140. W rank14 obowiązuje kompletna lista 29 tabel i numery `table_order` w [architekturze §23.5a.1](OILEMPIRE_ARCHITEKTURA_SYSTEMOWA.md); jej identyczną kopię maszynową przechowuje `x-staff-lock-order` w [kontrakcie P06–P13](OILEMPIRE_KONTRAKTY_P06_P13.yaml). Nowe nazwy tabel nie mogą być samowolnie dopisane do akcji bez zmiany obu rejestrów. Dotychczasowe nazwy `employee_availability`, `employee_assignments` i `training_enrollments` są aliasami historycznymi; A używa odpowiednio `staff_time_reservations`, `staff_assignments` i `staff_trainings`. LockPlan deklaruje wiersze przed pierwszą blokadą, sortuje według (rank, table_order, PK); po rank20 nie wracamy do rank14.

Długie obliczenie HR/symulacji poza transakcją na immutable snapshot. Krótka publikacja sprawdza input_hash, version, ownership i CAS. Równoległe ReserveTime dla tej samej osoby serializuje się przez rekord employment/availability; sam odczyt overlap bez blokady nie wystarcza. Limit2 kampanii także pod blokadą companies rank10; nie tworzymy dla tego osobnego rank14 „firmy”. Finansowe source key, due-event i outbox zapisujemy atomowo. Job i sweeper wybudzają tę samą procedurę; nie wykonują drugiej logiki.

## 9. Parametry panelu i wariant B

Jedna grupa polityk Staff z podgrupami recruitment, employment/payroll, roster, absences, training, assessments. Katalog zawiera ceny/GBBL, zasięgi, czasy, progi, mapy skutków i tłumaczenia; edytor ma jednostki, zakresy, zależności, preview, hash i status draft/submitted/active/superseded. Aktywna wersja immutable. Wartości startowe A są w Kadrach §19, a zatwierdzone historyczne w YAML v1. Nie tworzymy osobnych panelowych tabel biznesowych ani wykonywalnych formuł. Symulator 0 FAIL + zatwierdzenie §29.5/§29.7 przed aktywacją; właściciel nie wpisuje komentarza.

Wariant B dodaje nowe wartości zarejestrowanego `strategy_code` i katalogowe wiersze dla regionów, krzywej częściowej obsady, profilu warunków, szkoleń wg czasu zaplanowanego oraz pełnego baseline ról. Publiczne akcje i właściciele tabel zostają. Zmiana semantyki publicznego pola wymaga API v2. Nie projektujemy B jako drugiego modułu Staff.

## 10. Plan kodowania, odbiór i otwarte bramki

1. P01–P05: fundament, wspólne ID/clock, Ledger, LockPlan, outbox, balance versions.
2. Staff core: osoby, zatrudnienia, wersje umów, polityki, API safe DTO i migracje bez starych danych.
3. Recruitment + Employment: quote, kampania, oferty, hire, aneksy, termination quote/notice/end.
4. Scheduling + Absences: wspólny kalendarz, grafiki, urlopy, choroba, obsada i zastępstwa.
5. Payroll + Training: naliczenie/FTS raz, kurs, egzamin, certyfikaty.
6. Assessments: dowody z domen, kolejka pracy HR, baseline, snapshot, UI.
7. Opcjonalna migracja starych kont po mapie §4, kontrolach liczności i rollbacku.
8. Test MySQL + 90dni gospodarki + web/mobile/Filament + Security + release.

Minimalne testy kontraktowe: hire/retry/offer consumed; rehire zachowuje person; mirror legacy bez podwójnej osoby i pensji; aneks i odcinek miesiąca bez podwójnej kwoty; urlop/choroba/szkolenie/naprawa bez overlap; dwóch równoległych kampanii przy jednym slocie; dwóch workerów egzaminu; assessment bez hidden profile; obca firma 404; admin bez uprawnienia 403; outbox/job/sweeper raz; rollback/restore migracji na MySQL.

Otwarte przed aktywacją produkcyjną: kalibracja cennika i marży startera, legalny safe-stop transportu, dokładne parametry i baseline ról, brak podwójnego OPEX rafinerii, kontrakty producentów obserwacji, wykonanie SQL na MySQL 8.4, konwersja kontraktu na migracje Laravel oraz testy API/DB i gospodarki 90 dni. Opis kontraktów nie potwierdza kodu, migracji ani PASS. Wycofanie tej dokumentacji przez odwrócenie zmian w GitHub; nie wpływa na dane gry.


## 11. Korekty po audycie spójności kontraktu

Przegląd 18.11.2026 objął cały niniejszy dokument oraz odpowiadające sekcje Kadr, HR-PAY-01, kart P07–P09, API i kanonicznego §23.5a. To przegląd statyczny dokumentacji, bez uruchomienia kodu. Poniższe reguły są normatywne i usuwają błędne skróty poprzedniej wersji.

### 11.1. Model osoby i kandydata

`GenerateOffers` może utworzyć `staff_persons`, ale wykonuje to przez publiczną akcję `CreatePersonFromCandidate` właściciela Employment w **tej samej transakcji**. Recruitment posiada kampanię i ofertę, nie zapisuje prywatnie cech ani umiejętności osoby. `HireEmployee` zużywa istniejącą ofertę i powiązaną osobę; nie tworzy jej ponownie. Klucz tworzenia osoby: jednoznaczny `campaign_id+offer_slot` utrwalony w `creation_key`. W przypadku ponownego zatrudnienia z nowej oferty należy jawnie powiązać ją z istniejącą osobą; dopasowanie nazwiska nie wystarcza.

Losowanie profilu jest oddzielne od publikacji oferty. `staff_person_traits` ma tylko szczególne osie §16.1; istniejące loyalty/ambition/corruption/stress mają jedno kanoniczne źródło w profilu osoby po migracji, bez podwójnych kolumn efektu. Słownik i konwersja skali1–10 wymagają osobnej karty; nie wolno przepisać surowych wartości do signed_value−3..3.

Jedna oferta może mieć co najwyżej jedno zatrudnienie przez UNIQUE(source_offer_id) w `staff_employments`, przy jednoczesnym CAS statusu oferty i sprawdzeniu firmy. Dodatkowe UNIQUE(person_id,campaign_id,offer_slot) niczego ponad UNIQUE(campaign_id,offer_slot) nie zabezpiecza — usunięto.

### 11.2. Kalendarz, choroba i urlop

Blokada samego `employment_id` nie chroni przed równoległymi rezerwacjami tej samej osoby w dwóch okresach zatrudnienia. `ReserveTime` blokuje najpierw person albo stały slot dostępności osoby, potem jej aktywne employment i przedziały. Nie wolno pozwolić osobie pracować równocześnie w dwóch firmach bez osobnego jawnego kontraktu; wariant A odrzuca równoległe aktywne zatrudnienia. Constraint „co najwyżej jedno aktywne zatrudnienie na osobę” wymaga guard-row lub generated active_person_key UNIQUE, bo zwykły UNIQUE(person_id,status) zablokowałby wiele historycznych zakończonych okresów.

Urlop ma osobny typ i rocznik. `staff_leave_accounts` jest projekcją księgi, a `staff_leave_ledger` źródłem prawdy. Dla istniejącej puli przy zmianie policy_version nie tworzymy nowego salda: wersję stosowanej polityki zapisujemy na zdarzeniu nabycia i wniosku, a konto utrzymuje ten sam employment+leave_type+accrual_year. Zatem fizyczny UNIQUE konta musi być `(employment_id,leave_type,accrual_year)`, a `policy_version` jest informacją o ostatnim nabyciu, nie częścią tożsamości konta. Przeniesienie rocznika to księgowe przeniesienie godzin między kontami z tym samym source_key pary, bez podwójnego grantu. Wniosek i jego rezerwację trzeba utrwalić oddzielnie od epizodu faktycznej nieobecności; `staff_absences` nie zastępuje `staff_leave_requests`. Potrzebna tabela `staff_leave_requests`: employment_id, leave_type, from/to, status, requested_seconds, policy_version, source_key, state_version; UNIQUE(source_key), INDEX(employment_id,status,from). Zatwierdzenie rezerwuje zaplanowane godziny, późniejsza edycja grafiku ich nie zmienia.

Cooldown chorobowy i cap liczymy po `person_id`, również przez rehire; wiersz epizodu ma równocześnie `employment_id` i `company_id`. Wariant A nie aktywuje generatora bez dokładnej mapy ekspozycji i slotów czasu z §14/§16.1. Odrębna nieobecność urlopowa nie jest dowodem choroby.

### 11.3. Płace i szkolenia

`staff_pay_entitlements.exact_accumulator` wymaga typu DECIMAL o określonej precyzji i jednostki „grosz ułamkowy” albo pary licznik/mianownik BIGINT. Nie wolno użyć float ani zaokrąglać przy GET. `posted_minor` oznacza kwotę naliczoną do księgi zobowiązań; `paid_minor` jest oddzielną projekcją płatności Finance. Naliczanie, zobowiązanie, płatność i prognoza są różnymi stanami. Jedno `source_key` na okres+składnik nie wystarcza do wielokrotnych przyrostów: klucz naliczenia zawiera także stabilny `segment_end`/slot; istniejący akumulator i `state_version` aktualizujemy pod blokadą. Zamknięcie okresu round_half_up według HR-PAY-01. Narzuty mają oddzielne komponenty, bez podwójnego kosztu własnego analityka HR.

`staff_training_program_versions` ma wersję katalogu; `staff_trainings` utrwala jej ID, cenę, termin, zajętość i RNG. Certyfikat wymaga kolumny `type` (ukończenie/uprawnienie) i polityki ważności. UNIQUE(source_key) zapobiega powtórnemu nadaniu. Zapis skilla przez publiczną akcję właściciela profilu musi być w tej samej transakcji co wynik egzaminu; handler outbox nie podnosi skilla drugi raz. Remis egzamin/koniec zatrudnienia rozstrzyga §19.3 Kadr; harmonogram jobów nie jest źródłem kolejności.

### 11.4. Konfiguracja i HTTP

`QuoteCampaign` jest read-only gospodarczo, ale zwrócony `quote_id` musi wskazywać utrwalony, krótko żyjący snapshot wyceny lub podpisany serwerowo payload z wersją i terminem; nie wolno przyjąć kwoty z klienta. Proponowana `staff_recruitment_quotes`: company_id, public_id, request_hash, amount_minor, currency, policy_version, expires_at, consumed_at, source_key; UNIQUE(public_id), UNIQUE(source_key). Akcja `StartCampaign` sprawdza quote/wersję i zapisuje `consumed_at` atomowo z kampanią i płatnością. Idempotentny replay tej samej intencji zwraca tę samą kampanię.

Zasada „jedna aktywna wersja polityki” potrzebuje `staff_policy_current(policy_group PK, version_id FK, state_version)` oraz transakcji aktywacji zgodnej z konfiguracją §29.7. Samo `status=active` na wielu wierszach nie daje unikalności. Publiczne umowy i kampanie zachowują snapshot wersji; aktywacja nie przelicza ich wstecz.

Ścieżki `POST /hr/employees/{id}/termination-quotes` i `POST /hr/recruitments/quotes` nie mutują gospodarki, ale tworzą snapshot quote; wymagają autoryzacji i limitu oraz mogą przyjąć `Idempotency-Key` do bezpiecznego replay. Zaakceptowana odpowiedź `202` dla braku analityka ma `status=waiting_for_analyst`, bez `NO_HR_ANALYST` jako błędu. `ALREADY_QUEUED` z tym samym input_hash zwraca istniejącą operację; zmiana wejścia ustawia dirty. W publicznym DTO brak surowego `basis_score`, hidden trait, seed i prywatnych obserwacji poprzedniej firmy.

### 11.5. Rejestr ustaleń i pozostałych zależności

| ID | Problem wykryty w poprzedniej wersji | Status po korekcie |
|---|---|---|
| C01 | Wewnętrzna kolejność Staff i pominięcie rank1–13 sprzeczne z §23.5a | Poprawiono; pełna kolejność 29 tabel A jest w architekturze §23.5a.1 i kontrakcie maszynowym |
| C02 | INDEX kolejki używał niepodanych priority/ready_at | Poprawiono pola |
| C03 | Umowa braku FK company_id była sprzeczna z częścią fizycznego modelu | Doprecyzowano owner przez jawne FK do rodzica; pełne FK w migracjach |
| C04 | UNIQUE urlopu z policy_version tworzył nową pulę po zmianie wersji | Skorygowano tożsamość konta i księgę |
| C05 | Brak tabeli wniosków urlopowych | Dodano minimalny kontrakt |
| C06 | Rezerwacja tylko employment nie chroniła osoby przez rehire | Wymagana blokada person/guard |
| C07 | Quote i wskazanie quote_id bez trwałego nośnika | Dodano minimalny kontrakt snapshotu |
| C08 | Jeden source_key nie identyfikował przyrostów płac | Rozdzielono akumulator i slot naliczenia |
| C09 | Certyfikat odwoływał się do nieistniejącej kolumny type | Dodano wymagane pole |
| C10 | Status active nie gwarantował jednej polityki | Dodano singleton current |
| C11 | EndEmployment sugerował finalizację płac w chwili decyzji | Rozdzielono decyzję i logiczny koniec |
| C12 | B zapowiadał dodanie strategy_code, choć pole istnieje już w A | Poprawiono: B dodaje wartości strategii |

Przed wygenerowaniem migracji Laravel należy wykonać kontrakt SQL na MySQL 8.4, porównać faktyczny schemat fundamentu P01/P06, potwierdzić fizyczne FK/guardy oraz przeprowadzić testy transakcyjne i statystyczny harness. SQL i OpenAPI są gotowymi artefaktami projektowymi, nie dowodem ich wykonania ani zgodności produkcyjnej. Nie oznaczać tego przeglądu jako testu MySQL lub potwierdzenia pełnej gotowości kodu.
