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
- Każda tabela firmowa ma `company_id`; zapytanie i mutacja sprawdzają je w bazie i polityce. ID nie jest uprawnieniem. Obserwacje HR firmy A nie przechodzą do B.
- Czas: UTC millisecond, przedziały [from,to), `logical_at` i `recorded_at` odrębne. Aktywny czas firmy i pauzy z Simulation; bez własnego zegara Staff.
- Kwoty: BIGINT w groszach, JSON string `*_minor`; GBBL osobna jednostka. Brak float. Wersje polityk/umów i ceny quote utrwalone.
- `state_version` BIGINT dla CAS. Mutacje gospodarcze z `Idempotency-Key`, `If-Match` lub `expected_state_version` według API §5. Jedna intencja = jedna odpowiedź replay. Brak ponownego losowania przy GET/retry.
- Ukryty profil jest serwerowy; DTO, filtry, eksport, logi, WebSocket i cache gracza nie zawierają jego wartości. GM osobny capability i audyt.
- Typy enumerowane w kodzie i bazie przez ograniczone słowniki; nie dopuszczać dowolnego strategy_code/JSON wywołującego kod.
- Brak fizycznego usuwania osoby, umowy, dowodu, wypłaty lub certyfikatu przez zwykłą akcję. Korekta append-only.

## 3. Docelowy model danych — tabele fizyczne

Nazwy są propozycją migracji Laravel. Kolumny `id` BIGINT UNSIGNED lub ULID według wspólnego standardu P01; dla FK typ identyczny jak PK. Wszystkie mutable aggregate mają `state_version` i `created_at/updated_at`; poniżej wypisano pola domenowe i krytyczne ograniczenia. InnoDB, utf8mb4, UTC, CHECK tam gdzie MySQL go egzekwuje; ograniczenia przekrojowe sprawdza Action pod blokadą.

| Tabela / właściciel | Pola kluczowe | Relacje, unikalność, indeks |
|---|---|---|
| `staff_persons` / Employment | public_ulid, generation_version, creation_key, status | UNIQUE(public_ulid), UNIQUE(creation_key); nie zawiera firmy ani oceny |
| `staff_person_traits` / Employment | person_id, trait_code, signed_value, generation_version | FK person RESTRICT, UNIQUE(person_id,trait_code); serwerowa allowlista; brak powielania starych loyalty/ambition |
| `staff_person_skills` / Employment/Training przez publiczną akcję | person_id, skill_code, level, version | FK person RESTRICT, UNIQUE(person_id,skill_code); Training nie robi prywatnego UPDATE |
| `staff_legacy_refs` / Employment Migration | person_id, legacy_type, legacy_id, evidence_code, migration_batch | FK person RESTRICT, UNIQUE(legacy_type,legacy_id); cross-company konflikt=unresolved |
| `staff_employments` / Employment | person_id, company_id, role_code, department_code, started_at, ended_at nullable, status, source_offer_id nullable | FK person/company RESTRICT; UNIQUE(source_offer_id), INDEX(company_id,status,role_code), INDEX(person_id,started_at) |
| `staff_contract_versions` / Employment | employment_id, version_no, form_code, term_code, wage_basis, actual_amount_minor, fte_num/den, effective_from/to, accepted_at, policy_version | FK employment RESTRICT, UNIQUE(employment_id,version_no), INDEX(employment_id,effective_from); niezmienne po akceptacji |
| `staff_employment_events` / Employment | employment_id, event_type, logical_at, source_event_key, payload allowlist | FK employment RESTRICT, UNIQUE(employment_id,event_type,source_event_key); append-only |
| `staff_recruitment_campaigns` / Recruitment | company_id, role_code, channel_code, reach_code, tier_code, price_minor, currency, balance_version, due_at, status, rng_slot | FK company RESTRICT, UNIQUE(company_id,rng_slot), INDEX(company_id,status,due_at) |
| `staff_candidate_offers` / Recruitment | campaign_id, person_id, offer_slot, terms_snapshot, expires_at, status, consumed_at | FK campaign/person RESTRICT, UNIQUE(campaign_id,offer_slot), UNIQUE(person_id,campaign_id,offer_slot); snapshot nie ujawnia hidden |
| `staff_roster_versions` / Scheduling | company_id, period_from/to, policy_version, status, published_at | INDEX(company_id,period_from,status); opublikowana wersja niezmienna |
| `staff_roster_intervals` / Scheduling | roster_version_id, employment_id, from/to, kind, planned_seconds | FK roster/employment RESTRICT, INDEX(employment_id,from,to), UNIQUE(roster_version_id,employment_id,from,kind) |
| `staff_time_reservations` / Scheduling | employment_id, from/to, purpose, owner_ref, status, source_key | UNIQUE(source_key), INDEX(employment_id,status,from,to); overlap pod blokadą employment/availability |
| `staff_assignments` / Scheduling | employment_id, target_type/id, allocation_bps, assigned_at, released_at, source_key | UNIQUE(source_key), INDEX(employment_id,released_at), INDEX(target_type,target_id,released_at); target ownership waliduje właściciel |
| `staff_leave_accounts` / Absences | employment_id, policy_version, balance_seconds, reserved_seconds | UNIQUE(employment_id,policy_version), CHECK balance/reserved≥0 |
| `staff_leave_ledger` / Absences | account_id, entry_type, seconds, source_key, effective_at | FK account RESTRICT, UNIQUE(source_key), INDEX(account_id,effective_at); acquire/reserve/use/release/correct |
| `staff_absences` / Absences | employment_id, type, from/to, status, episode_key, policy_version | UNIQUE(episode_key), INDEX(employment_id,from,to), INDEX(company_id,status,from) |
| `staff_replacement_orders` / Absences | company_id, absence_id, quote_snapshot, cost_minor, provider_ref, arrival_at, status | UNIQUE(company_id,absence_id,provider_ref), INDEX(company_id,status,arrival_at); brak oferty nie tworzy pracownika |
| `staff_pay_entitlements` / Payroll | employment_id, period_from/to, component_code, exact_accumulator, posted_minor, policy_version, source_key, status | UNIQUE(source_key), INDEX(employment_id,period_from,component_code); settlement raz |
| `staff_trainings` / Training | employment_id, program_version_id, started_at, due_at, status, paid_minor, rng_slot | UNIQUE(employment_id,rng_slot), INDEX(employment_id,status,due_at) |
| `staff_training_program_versions` / Training | program_code, version_no, skill_code, level, duration_seconds, price_minor, pass_threshold, policy_version | UNIQUE(program_code,version_no); aktywne wersje niezmienne |
| `staff_certificates` / Training | person_id, employment_id, training_id, certificate_code, issued_at, expires_at nullable, source_key | UNIQUE(source_key), FK person/training RESTRICT; certyfikat ukończenia vs uprawnienie jako type |
| `staff_observations` / Assessments | company_id, person_id, employment_id, source_key, revision, domain, logical_at, independence_key, payload allowlist, quality | UNIQUE(company_id,source_key,revision), INDEX(company_id,person_id,domain,logical_at); append-only |
| `staff_assessment_runs` / Assessments | company_id, person_id, domain, as_of, method_version, input_hash, status | UNIQUE(company_id,person_id,domain,method_version,input_hash); technical run ≠ czas analityka |
| `staff_assessment_snapshots` / Assessments | run_id, stage, direction, confidence_band, freshness, basis_summary, created_at | UNIQUE(run_id), FK run RESTRICT; immutable |
| `staff_assessment_current` / Assessments | company_id, person_id, domain, snapshot_id, state_version | PK(company_id,person_id,domain), FK snapshot RESTRICT; CAS chroni nowszy wynik |
| `staff_assessment_evidence` / Assessments | snapshot_id, observation_id, revision, weight, reason_code | UNIQUE(snapshot_id,observation_id,revision); FK RESTRICT |
| `staff_analysis_work` / Assessments | company_id, person_id, domain, slot, required_seconds, worked_seconds, analyst_employment_id, status, dirty, state_version | UNIQUE(company_id,person_id,domain,slot), INDEX(company_id,status,priority,ready_at) |
| `staff_policy_versions` / Staff config | policy_group, version_no, effective_from, hash, status, approved_by, approved_at | UNIQUE(policy_group,version_no), INDEX(policy_group,status,effective_from); immutable active |
| `staff_policy_items` / Staff config | version_id, code, strategy_code, typed_value, unit, min/max, scope_key | UNIQUE(version_id,code,scope_key), FK version RESTRICT; walidowane strategie |

Pola `company_id`, `priority`, `ready_at`, `context_version`, `basis` i szczegóły indeksów trzeba dopisać w finalnych plikach migracji/Schema; tabela powyżej jest minimalnym kontraktem, nie fikcyjnym pełnym CREATE TABLE. Nie używać CASCADE dla historycznych faktów. `staff_analysis_work` może wymagać osobnego guard dla najwyżej jednego niezakończonego zlecenia niezależnie od slotu: MySQL generated active_key NULL/unikalny lub kontrolowany parent-row; sam UNIQUE(slot) tego nie zapewnia.

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
| `EndEmployment` | employment/version, reason, quote → notice/end dues | legalna przyczyna, payroll finalization, release przyszłych rezerwacji, outbox EmploymentEnded; stary historyczny fakt zostaje |
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

Dla `ALREADY_QUEUED` zwrócić istniejące `operation_id` jako202, gdy payload/intencja zgodne;409 tylko dla konfliktu wersji lub innego żądania. `NO_HR_ANALYST` nie usuwa dowodów: może zwrócić202 queued z informacją o braku ETA. Brak baseline zwraca `unassessed` w GET, a POST oznacza waiting_evidence zamiast fikcyjnego wyniku. Rate limit według głównego API §10.1, bez osobnego nieuzgodnionego limitu HR.

Przykład startu kampanii:
```json
{"quote_id":"550e8400-e29b-41d4-a716-446655440001","expected_quote_version":1}
```
Odpowiedź:
```json
{"data":{"id":"550e8400-e29b-41d4-a716-446655440002","status":"searching","due_at":"2026-11-18T12:05:00.000Z","price_minor":"1200000","currency":"PLN"},"meta":{"balance_version":"hr-a-1"}}
```
Przykład błędu:
```json
{"error":{"code":"QUOTE_STALE","message":"Oferta zmieniła się. Sprawdź nową wycenę.","details":{"quote_id":"550e8400-e29b-41d4-a716-446655440003"},"request_id":"550e8400-e29b-41d4-a716-446655440004"}}
```
Dokładne OpenAPI 3.1, typy pól każdej mutacji, odpowiedzi i schematy errors są artefaktem do wygenerowania z tej tabeli przed implementacją klienta. Powyższa tabela nie udaje kompletnego pliku OpenAPI.

## 7. Uprawnienia i widoczność

| Capability | Gracz uprawniony w firmie | Moderator/GM | Owner admin |
|---|---|---|---|
| staff.view | własna firma | zgodnie z zakresem sprawy | tak |
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

Globalny porządek z architektury §23.5a obowiązuje Staff. W rank14 blokujemy wyłącznie wymagane wiersze Staff w stabilnej kolejności: firma/limity → person → employment → kontrakt → kalendarz/rezerwacje → proces (campaign/absence/training/assessment), następnie konta Ledger rank20 i dalsze zasoby według planu globalnego. Przed transakcją składamy LockPlan; nie wracamy do rank14 po zajęciu rank20. Jeśli globalny dokument określa inną wewnętrzną kolejność dla operacji, ujednolicić przed kodowaniem.

Długie obliczenie HR/symulacji poza transakcją na immutable snapshot. Krótka publikacja sprawdza input_hash, version, ownership i CAS. Równoległe ReserveTime dla tej samej osoby serializuje się przez rekord employment/availability; sam odczyt overlap bez blokady nie wystarcza. Limit2 kampanii także pod blokadą firmy. Finansowe source key, due-event i outbox zapisujemy atomowo. Job i sweeper wybudzają tę samą procedurę; nie wykonują drugiej logiki.

## 9. Parametry panelu i wariant B

Jedna grupa polityk Staff z podgrupami recruitment, employment/payroll, roster, absences, training, assessments. Katalog zawiera ceny/GBBL, zasięgi, czasy, progi, mapy skutków i tłumaczenia; edytor ma jednostki, zakresy, zależności, preview, hash i status draft/submitted/active/superseded. Aktywna wersja immutable. Wartości startowe A są w Kadrach §19, a zatwierdzone historyczne w YAML v1. Nie tworzymy osobnych panelowych tabel biznesowych ani wykonywalnych formuł. Symulator 0 FAIL + zatwierdzenie §29.5/§29.7 przed aktywacją; właściciel nie wpisuje komentarza.

Wariant B dodaje `strategy_code` i nowe katalogowe wiersze dla regionów, krzywej częściowej obsady, profilu warunków, szkoleń wg czasu zaplanowanego oraz pełnego baseline ról. Publiczne akcje i właściciele tabel zostają. Zmiana semantyki publicznego pola wymaga API v2. Nie projektujemy B jako drugiego modułu Staff.

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

Otwarte przed aktywacją produkcyjną: kalibracja cennika i marży startera, legalny safe-stop transportu, dokładne parametry i baseline ról, brak podwójnego OPEX rafinerii, kontrakty producentów obserwacji, komplet OpenAPI 3.1, końcowe DDL FK/indeksy oraz wykonane testy. Opis kontraktów nie potwierdza kodu, migracji ani PASS. Wycofanie tej dokumentacji przez odwrócenie zmian w GitHub; nie wpływa na dane gry.
