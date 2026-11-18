# OilEmpire — karty implementacyjne P21–P24

Status: kontrakt dokumentacyjny do programowania po audycie; wartości DOM pozostają do zatwierdzenia. Szczegóły wymagane przez plan §3 są powiązane przez [macierz DoR akcji](OILEMPIRE_KONTRAKTY_AKCJI_P14_P25.md). Czytać z [planem](OILEMPIRE_PLAN_PROGRAMOWANIA.md), [kontraktami maszynowymi](OILEMPIRE_KONTRAKTY_P14_P25.yaml), [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql), [OpenAPI](OILEMPIRE_OPENAPI_P14_P25_V1.yaml) i [rejestrem propozycji DOM](OILEMPIRE_PROPOZYCJE_DOM_P14_P25.md). Zatwierdzone OPEN01–OPEN12 obowiązują; nierozstrzygnięte DOM nie mają wartości produkcyjnej. Każda mutacja stosuje wymagany Settlement i LockPlan §23.5a; Ledger, ruch fizyczny i outbox tylko wtedy, gdy operacja ma taki skutek. GET jest odczytem bez skutków ubocznych. UI Vue3/Flutter/Filament korzystają z tych samych Actions, nie z własnej ekonomii.

## P21. Social/Realtime
Źródło: §26, §28, §30. Właściciel zapisu: **Social**; korzeń balansu: `social`; poprzedniki: P02/P04/P06/P18.
Dane: chat_threads, chat_messages, cartels, cartel_memberships, diplomacy_relations, moderation_reports, channel_grants. Właściciele cudzych tabel są odpytywani przez publiczny kontrakt. Mutowalne ID wewnętrzne BIGINT, publiczne ULID; kwoty BIGINT grosze/API string, ropa DECIMAL(16,4), czas UTC DATETIME(3).
LockPlan: firma10 → kontrakty110 gdy potrzebne → stan Social rank122 → append140. Zasoby obu firm deklaruje się przed pierwszym lockiem; żaden handler nie wraca do wcześniejszej rangi.
Reguły: Reverb publikuje wyłącznie autoryzowane zdarzenia po commit. Członkostwo i zwolnienie prowizji mają snapshot czasu; rewokacja odcina otwarte połączenie. Publiczny rejestr karteli nie odsłania prywatnych rozmów.
UI/GM: Filament: czat, zgłoszenia, kary, role moderatora, audyt bez cichej zmiany historii. Web Vue3 i aplikacja mobile korzystają z tej samej Action; PL/EN/DE, lokalne SVG, dostępny modal zamiast okna systemowego.
Odbiór pakietu: każda karta ma test pozytywny, odmowy, obcej firmy, retry, równoległości MySQL i awarii workera. Parametry tylko z zatwierdzonej balance_version; propozycje DOM nie aktywują funkcji.


### P21-F01 — SendMessage
- **Aktor i kontrakt:** aktor firmy z capability `social.participate`. thread_id, text, client_key → message_id. `POST /api/v1/social/threads/{id}/messages` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `chat_threads`, `chat_messages`. member→posted; sanitize i ograniczenie długości, outbox po commit. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → kontrakty110 gdy potrzebne → stan Social rank122 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `social.send_message.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** cudzy kanał/ban/rate limit → odmowa. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** dwa urządzenia, replay, XSS; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P21-F02 — JoinCartel
- **Aktor i kontrakt:** aktor firmy z capability `social.participate`. cartel_id, expected_version → membership. `POST /api/v1/social/cartels/{id}/members` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `cartels`, `cartel_memberships`. eligible→active; max5 członków i limit produkcji40%, relacja B2B od effective_at. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → kontrakty110 gdy potrzebne → stan Social rank122 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `social.join_cartel.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak prawa/pełny kartel → 409. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** równoległe zaproszenia i limit40%; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P21-F03 — LeaveCartel
- **Aktor i kontrakt:** aktor firmy z capability `social.participate`. membership_id → ended_at. `DELETE /api/v1/social/cartels/{id}/members/me` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `cartel_memberships`. active→left; przyszłe prowizje wg snapshot, stare rozliczenia bez zmian. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → kontrakty110 gdy potrzebne → stan Social rank122 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `social.leave_cartel.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** retry → ten sam wynik. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** B2B w locie, fee collar; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P21-F04 — ModerateMessage
- **Aktor i kontrakt:** Filament Action z jawną capability GM. message_id, decision, reason_code → moderation_record. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `chat_messages`, `moderation_reports`. visible→hidden/redacted z trwałą historią, osobny audit. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → kontrakty110 gdy potrzebne → stan Social rank122 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `social.moderate_message.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** moderator bez scope →403, brak cichego UPDATE treści. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** appeal, data retention, race; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P21-F05 — AuthorizeChannel
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. actor, channel, connection_epoch → grant/deny. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `channel_grants`. scope po logowaniu i przy każdej rewokacji; WS bez payload prywatnych danych po zmianie ról. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → kontrakty110 gdy potrzebne → stan Social rank122 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `social.authorize_channel.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** stara sesja/ban → disconnect. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** otwarty WebSocket po revokacji; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.



## P22. Notifications/Onboarding
Źródło: §27, §28. Właściciel zapisu: **Onboarding**; korzeń balansu: `onboarding`; poprzedniki: P02/P04/P06/P11/P12/P14.
Dane: tutorial_progress, achievement_grants, notifications, notification_deliveries, notification_preferences, device_tokens. Właściciele cudzych tabel są odpytywani przez publiczny kontrakt. Mutowalne ID wewnętrzne BIGINT, publiczne ULID; kwoty BIGINT grosze/API string, ropa DECIMAL(16,4), czas UTC DATETIME(3).
LockPlan: firma10 → Ledger20 oraz Monetization121, jeśli nagroda dotyka GBBL → Onboarding123 → append140; wysyłka po commit. Pozostałe zdarzenia Onboarding bez nagrody pomijają Ledger/Monetization. Zasoby obu firm deklaruje się przed pierwszym lockiem; żaden handler nie wraca do wcześniejszej rangi.
Reguły: Jedna trwała notyfikacja na zdarzenie/odbiorcę; push/e-mail są transportem, nie źródłem prawdy. FTUE i osiągnięcie mają unikalny slot nagrody; brak push nie cofa zdarzenia.
UI/GM: Filament: katalog szablonów, centrum dostaw i próby; bez wysyłania ukrytych cech. Web Vue3 i aplikacja mobile korzystają z tej samej Action; PL/EN/DE, lokalne SVG, dostępny modal zamiast okna systemowego.
Odbiór pakietu: każda karta ma test pozytywny, odmowy, obcej firmy, retry, równoległości MySQL i awarii workera. Parametry tylko z zatwierdzonej balance_version; propozycje DOM nie aktywują funkcji.


### P22-F01 — AdvanceTutorial
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. company_id, step_code, source_event → progress/reward. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `tutorial_progress`. pending→complete raz po domenowym dowodzie; etap szkoleniowego odwiertu i logistyki według §27.1a. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → Ledger20 oraz Monetization121, jeśli nagroda dotyka GBBL → Onboarding123 → append140; wysyłka po commit. Pozostałe zdarzenia Onboarding bez nagrody pomijają Ledger/Monetization. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `onboarding.advance_tutorial.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** pusty event/obca firma → odmowa. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** dwa wejścia i ponowny start firmy; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P22-F02 — GrantAchievement
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. company, achievement_code, evidence_id → grant. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `achievement_grants`. not_granted→granted; reward przez Ledger/GBBL raz. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → Ledger20 oraz Monetization121, jeśli nagroda dotyka GBBL → Onboarding123 → append140; wysyłka po commit. Pozostałe zdarzenia Onboarding bez nagrody pomijają Ledger/Monetization. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `onboarding.grant_achievement.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** stary event replay → no-op. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** exactly once reward; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P22-F03 — DeliverNotification
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. event_id, recipient_id, channel → delivery result. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `notifications`, `notification_deliveries`. outbox→pending→sent/failed/retry; centrum in-app trwałe, push/email adaptery. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → Ledger20 oraz Monetization121, jeśli nagroda dotyka GBBL → Onboarding123 → append140; wysyłka po commit. Pozostałe zdarzenia Onboarding bez nagrody pomijają Ledger/Monetization. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `onboarding.deliver_notification.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** wyłączenie push nie usuwa wpisu in-app. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** awaria FCM, duplicate job, offline; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P22-F04 — UpdatePreferences
- **Aktor i kontrakt:** aktor firmy z capability `notifications.manage`. user_id, categories/channels, expected_version → preferences. `PUT /api/v1/notifications/preferences` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `notification_preferences`. CAS; krytyczne systemowe nie zależą od opt-in marketingu. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → Ledger20 oraz Monetization121, jeśli nagroda dotyka GBBL → Onboarding123 → append140; wysyłka po commit. Pozostałe zdarzenia Onboarding bez nagrody pomijają Ledger/Monetization. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `onboarding.update_preferences.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak zgody newsletter → nie wysyłaj. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** zmiana zgody w trakcie kolejki; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.



## P23. CMS/Privacy
Źródło: §15–§16, §30. Właściciel zapisu: **CorePrivacy**; korzeń balansu: `core`; poprzedniki: P02/P05/P06/P21/P22.
Dane: static_pages, page_versions, navigation_items, privacy_policies, consent_records, privacy_requests, newsletter_campaigns, newsletter_deliveries. Właściciele cudzych tabel są odpytywani przez publiczny kontrakt. Mutowalne ID wewnętrzne BIGINT, publiczne ULID; kwoty BIGINT grosze/API string, ropa DECIMAL(16,4), czas UTC DATETIME(3).
LockPlan: firma10 → właściciele danych według globalnego rank → Privacy/CMS rank124 → append140; publikacja config osobno. Zasoby obu firm deklaruje się przed pierwszym lockiem; żaden handler nie wraca do wcześniejszej rangi.
Reguły: TinyMCE tylko zatwierdzony HTML po sanitizacji; strona i regulamin wersjonowane. Eksport po autoryzacji; anonimizacja nie usuwa FTS. Newsletter wymaga ważnej zgody i audytu odwołania.
UI/GM: Filament: strony, menu, polityki, zgody, eksport/anonimizacja i newsletter z podglądem PL/EN/DE. Web Vue3 i aplikacja mobile korzystają z tej samej Action; PL/EN/DE, lokalne SVG, dostępny modal zamiast okna systemowego.
Odbiór pakietu: każda karta ma test pozytywny, odmowy, obcej firmy, retry, równoległości MySQL i awarii workera. Parametry tylko z zatwierdzonej balance_version; propozycje DOM nie aktywują funkcji.


### P23-F01 — PublishPage
- **Aktor i kontrakt:** Filament Action z jawną capability GM. draft_id, locale, expected_hash → page_version. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `static_pages`, `page_versions`. draft→published immutable; sanitizacja HTML i bezpieczny preview. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → właściciele danych według globalnego rank → Privacy/CMS rank124 → append140; publikacja config osobno. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `core_privacy.publish_page.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** zabroniony element/konflikt hash → odmowa. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** XSS, dwie publikacje; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P23-F02 — PublishPolicy
- **Aktor i kontrakt:** Filament Action z jawną capability GM. policy_version, effective_at → active. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `privacy_policies`. draft→active; snapshot treści i hash, nowa zgoda przy wymaganej zmianie. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → właściciele danych według globalnego rank → Privacy/CMS rank124 → append140; publikacja config osobno. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `core_privacy.publish_policy.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak tłumaczenia/akceptacji → odmowa. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** wersja historyczna i odwołanie; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P23-F03 — RecordConsent
- **Aktor i kontrakt:** uwierzytelniony użytkownik dla własnego user_id (GM tylko w odrębnej, audytowanej procedurze). policy_version, purpose_code, granted, expected_state_version → consent_event; user_id pochodzi z sesji. `POST /api/v1/privacy/consents` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `consent_records`. grant/withdraw append-only per user_id + purpose_code + policy_id; preferencje wynikają z ostatniego zdarzenia, bez nadpisania historii. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → właściciele danych według globalnego rank → Privacy/CMS rank124 → append140; publikacja config osobno. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `core_privacy.record_consent.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** obcy użytkownik →403/404. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** równoległa zgoda i withdrawal; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P23-F04 — ExportPersonalData
- **Aktor i kontrakt:** właściciel konta po step-up; verified_actor wynika z sesji, request_id → prywatne archiwum i krótki adres pobrania. `POST /api/v1/privacy/exports` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `privacy_requests`. requested→prepared→downloaded; chwilowy link, audyt pobrania. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → właściciele danych według globalnego rank → Privacy/CMS rank124 → append140; publikacja config osobno. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `core_privacy.export_personal_data.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak identity step-up → odmowa. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** cudze dane, wygaśnięcie linku; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P23-F05 — AnonymizeUser
- **Aktor i kontrakt:** właściciel konta po step-up składa wniosek; GM rozpatruje go osobną audytowaną akcją. approved_request, scope → anonymization_report. `POST /api/v1/privacy/anonymization-requests` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `privacy_requests`. pending→completed; PII ukryte/usunięte wg retencji, FTS i historia gospodarcza zachowane. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → właściciele danych według globalnego rank → Privacy/CMS rank124 → append140; publikacja config osobno. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `core_privacy.anonymize_user.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** aktywny obowiązek/hold → kontrolowany stan oczekiwania. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** rekoncyliacja po anonimizacji; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P23-F06 — SendNewsletter
- **Aktor i kontrakt:** Filament Action z jawną capability GM. campaign_id, audience_snapshot → delivery batch. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `newsletter_campaigns`, `newsletter_deliveries`. draft→approved→sending→completed; TinyMCE sanitized, zgoda sprawdzona przy wysyłce. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → właściciele danych według globalnego rank → Privacy/CMS rank124 → append140; publikacja config osobno. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `core_privacy.send_newsletter.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** withdrawn consent → skip. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** duplikat job, unsubscribe i bounce; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.



## P24. Web/Mobile delivery
Źródło: API v1, panel całość, §1/§28. Właściciel zapisu: **ClientDelivery**; korzeń balansu: `—`; poprzedniki: P05–P23.
Dane: ui_preferences, client_version_support (bez ekonomii). Właściciele cudzych tabel są odpytywani przez publiczny kontrakt. Mutowalne ID wewnętrzne BIGINT, publiczne ULID; kwoty BIGINT grosze/API string, ropa DECIMAL(16,4), czas UTC DATETIME(3).
LockPlan: klient nie blokuje tabel domenowych; mutacje przez API/Actions właściciela. Zasoby obu firm deklaruje się przed pierwszym lockiem; żaden handler nie wraca do wcześniejszej rangi.
Reguły: Vue3/Inertia web oraz Flutter mobile używają jednego API v1 i DTO, bez drugich formuł ekonomii. Liczniki interpolowane tylko do estymaty, wynik serwera autorytatywny. Offline kolejkuje intencję z Idempotency-Key i ponownie waliduje wersję po połączeniu.
UI/GM: Filament zachowuje te same Actions i dostępność mobilną; komunikaty SVG i komponent modalny wspólne. Web Vue3 i aplikacja mobile korzystają z tej samej Action; PL/EN/DE, lokalne SVG, dostępny modal zamiast okna systemowego.
Odbiór pakietu: każda karta ma test pozytywny, odmowy, obcej firmy, retry, równoległości MySQL i awarii workera. Parametry tylko z zatwierdzonej balance_version; propozycje DOM nie aktywują funkcji.


### P24-F01 — SnapshotResource
- **Aktor i kontrakt:** uwierzytelniony użytkownik; GET /api/v1/game/snapshot. Server-side query składa aktualny stan autoryzowanych zasobów z modułów właścicieli; ETag, as_of UTC, contract_version, balance_version. Bez Idempotency-Key i bez zdarzenia.
- **Dane i przejście:** brak własnego zapisu ClientDelivery; nie zmienia last_calc_at ani FTS przy nieautoryzowanym odczycie. Settlement read path według P04, pola prywatne filtrowane przez polityki właścicieli.
- **Odmowa i test:** obcy zasób 404; test web/Flutter tego samego snapshotu, 304 ETag, równoległy tick, revokacja sesji, brak write/outbox.

### P24-F02 — ApiClientContract
- **Aktor i kontrakt:** GET /api/v1/client/contract zwraca wspieraną contract_version, min_app_version i capabilities. Generator SDK używa opublikowanego OpenAPI; web cookie+CSRF, mobile Bearer/refresh. Bez skutku ubocznego i bez zdarzenia.
- **Dane:** client_version_support tylko do odczytu. Stary klient dostaje jawny UPGRADE_REQUIRED; retry mutacji tylko z zachowanym Idempotency-Key.
- **Test:** zakres wersji, revokacja, brak tokenu w localStorage i brak stanu pochodzącego z klienta.

### P24-F03 — DeepLinkRouting
- **Aktor i kontrakt:** lokalny kontrakt klienta Vue/Flutter, bez serwerowej Action. Allowlista domen i ścieżek, stan auth, locale → ekran; każdy docelowy zasób jest pobierany z API i ponownie autoryzowany.
- **Dane i test:** bez zapisu i zdarzenia. Test wygasłego linku, obcego zasobu, offline, Android/iOS, 320–1440 px, PL/EN/DE i lokalnej ikony SVG.

## Uzupełnione akcje i zapytania po audycie kompletności

Poniższe ID należą do odpowiednich pakietów i korzystają z tych samych kontraktów wersji, LockPlan, DDL i polityk. Pełna macierz DoR dla wszystkich akcji jest w [kontrakcie szczegółowym](OILEMPIRE_KONTRAKTY_AKCJI_P14_P25.md).

### P21-F06 — CreateCartel
- **Kanon i aktor:** pakiet P21; aktor zasobu z polityką owner/capability. Właściciel tabel: Social.
- **Interface / DTO:** name, invite_policy → cartel_id. POST /api/v1/social/cartels OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** cartels(name,founder_company_id,invite_policy); cartel_memberships(cartel_id,member_company_id,joined_at,left_at). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Aktualny stan | autoryzacja, wersja, policy gate i name taken/limit→409 | new→active; founder membership once | rollback przed commit; po skutku zewnętrznym oddzielna idempotentna korekta |

- **Transakcja:** companies10→contract110 gdy kartel→Social122→append140; Idempotency-Key lub stabilny klucz domenowy + CAS; effect key i outbox w tej samej transakcji.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; due_slot dla terminów, fencing dla workera, seed+wynik RNG tylko gdy losowanie; deadlock wycofuje całość i ponawia ten sam efekt.
- **Zdarzenie:** social.create_cartel.v1; payload: event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorca przez rejestr subskrypcji; retry z event_id, po limicie DLQ i alert.
- **UI/Filament:** Czat i kartele; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** two founders, membership uniqueness; name taken/limit→409; test własności i odmowy, MySQL dwie sesje dla mutacji, retry po commit, licznik social.createcartel.success/conflict/error bez PII.
- **Migracja/rollback:** expand tworzy własne tabele/indeksy; greenfield bez backfill, import legacy tylko z osobnym mapowaniem. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P21-F07 — CreateThread
- **Kanon i aktor:** pakiet P21; aktor zasobu z polityką owner/capability. Właściciel tabel: Social.
- **Interface / DTO:** kind, participants, subject → thread_id. POST /api/v1/social/threads OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** chat_threads(kind,subject_public_ulid,created_by_user_id); channel_grants(user_id,channel_key,granted_at,revoked_at). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Aktualny stan | autoryzacja, wersja, policy gate i private member missing→403 | new→open after participant authorization | rollback przed commit; po skutku zewnętrznym oddzielna idempotentna korekta |

- **Transakcja:** companies10→contract110 gdy kartel→Social122→append140; Idempotency-Key lub stabilny klucz domenowy + CAS; effect key i outbox w tej samej transakcji.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; due_slot dla terminów, fencing dla workera, seed+wynik RNG tylko gdy losowanie; deadlock wycofuje całość i ponawia ten sam efekt.
- **Zdarzenie:** social.create_thread.v1; payload: event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorca przez rejestr subskrypcji; retry z event_id, po limicie DLQ i alert.
- **UI/Filament:** Czat i kartele; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** duplicate direct thread, hidden member; private member missing→403; test własności i odmowy, MySQL dwie sesje dla mutacji, retry po commit, licznik social.createthread.success/conflict/error bez PII.
- **Migracja/rollback:** expand tworzy własne tabele/indeksy; greenfield bez backfill, import legacy tylko z osobnym mapowaniem. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P21-F08 — SetDiplomacyRelation
- **Kanon i aktor:** pakiet P21; aktor zasobu z polityką owner/capability. Właściciel tabel: Social.
- **Interface / DTO:** target_company, relation_code, expected_version → relation. PUT /api/v1/social/relations/{id} OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** diplomacy_relations(other_company_id,relation_code,effective_at). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Aktualny stan | autoryzacja, wersja, policy gate i foreign company/self→409 | previous→active versioned relation | rollback przed commit; po skutku zewnętrznym oddzielna idempotentna korekta |

- **Transakcja:** companies10→contract110 gdy kartel→Social122→append140; Idempotency-Key lub stabilny klucz domenowy + CAS; effect key i outbox w tej samej transakcji.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; due_slot dla terminów, fencing dla workera, seed+wynik RNG tylko gdy losowanie; deadlock wycofuje całość i ponawia ten sam efekt.
- **Zdarzenie:** social.set_diplomacy_relation.v1; payload: event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorca przez rejestr subskrypcji; retry z event_id, po limicie DLQ i alert.
- **UI/Filament:** Czat i kartele; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** concurrent updates, historical snapshot; foreign company/self→409; test własności i odmowy, MySQL dwie sesje dla mutacji, retry po commit, licznik social.setdiplomacyrelation.success/conflict/error bez PII.
- **Migracja/rollback:** expand tworzy własne tabele/indeksy; greenfield bez backfill, import legacy tylko z osobnym mapowaniem. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P21-F09 — ListThreads
- **Kanon i aktor:** pakiet P21; uwierzytelniony aktor z polityką zasobu. Właściciel tabel: Social.
- **Interface / DTO:** cursor, limit → authorized thread list. GET /api/v1/social/threads OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** chat_threads(kind,subject_public_ulid,created_by_user_id); chat_messages(thread_id,sender_user_id,body_text,edited_at,redacted_at); channel_grants(user_id,channel_key,granted_at,revoked_at). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Bez zmiany | autoryzacja/widoczność; revoked member→404 | read-only, membership checked at query time | Brak skutku ubocznego |

- **Transakcja:** brak blokad zapisu, idempotencji mutacyjnej i outbox.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; ETag/as_of i polityka widoczności.
- **Zdarzenie:** brak.
- **UI/Filament:** Czat i kartele; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** revoke during open WS, no private preview; revoked member→404; test własności i odmowy, paginacji/ETag, brak zapisu, licznik social.listthreads.success/conflict/error bez PII.
- **Migracja/rollback:** odczyt istniejących tabel; indeksy i migracje właściciela wskazuje DDL, bez własnego backfill. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P21-F10 — ApplyCartelSanction
- **Kanon i aktor:** pakiet P21; wewnętrzny handler z due_slot i fencing. Właściciel tabel: Social.
- **Interface / DTO:** 3 oflagowane transakcje/7 dni z różnych source_event_id → sanction state + 30 dni zawieszenia. Komenda wewnętrzna lub Filament Action bez publicznej trasy. OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** cartels(name,founder_company_id,invite_policy); cartel_memberships(cartel_id,member_company_id,joined_at,left_at). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Aktualny stan | autoryzacja, wersja, policy gate i fewer than 3 flags→no-op/POLICY_NOT_APPROVED only unresolved propagation | threshold reached→30d fee exemption suspension; reputation deltas once | rollback przed commit; po skutku zewnętrznym oddzielna idempotentna korekta |

- **Transakcja:** companies10→contract110 gdy kartel→Social122→append140; Idempotency-Key lub stabilny klucz domenowy + CAS; effect key i outbox w tej samej transakcji.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; due_slot dla terminów, fencing dla workera, seed+wynik RNG tylko gdy losowanie; deadlock wycofuje całość i ponawia ten sam efekt.
- **Zdarzenie:** social.apply_cartel_sanction.v1; payload: event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorca przez rejestr subskrypcji; retry z event_id, po limicie DLQ i alert.
- **UI/Filament:** Czat i kartele; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** dedupe flags, 7d boundary, 30d expiry; fewer than 3 flags→no-op/POLICY_NOT_APPROVED only unresolved propagation; test własności i odmowy, MySQL dwie sesje dla mutacji, retry po commit, licznik social.applycartelsanction.success/conflict/error bez PII.
- **Migracja/rollback:** expand tworzy własne tabele/indeksy; greenfield bez backfill, import legacy tylko z osobnym mapowaniem. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P21-F11 — ListCartels
- **Kanon i aktor:** pakiet P21; publiczny odczyt wyłącznie opublikowanych danych. Właściciel tabel: Social.
- **Interface / DTO:** cursor, region → public cartel directory. GET /api/v1/social/cartels OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** cartels(name,founder_company_id,invite_policy); cartel_memberships(cartel_id,member_company_id,joined_at,left_at). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Bez zmiany | autoryzacja/widoczność; private membership hidden | read-only public-safe projection | Brak skutku ubocznego |

- **Transakcja:** brak blokad zapisu, idempotencji mutacyjnej i outbox.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; ETag/as_of i polityka widoczności.
- **Zdarzenie:** brak.
- **UI/Filament:** Czat i kartele; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** paging, directory privacy; private membership hidden; test własności i odmowy, paginacji/ETag, brak zapisu, licznik social.listcartels.success/conflict/error bez PII.
- **Migracja/rollback:** odczyt istniejących tabel; indeksy i migracje właściciela wskazuje DDL, bez własnego backfill. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P22-F05 — RegisterDeviceToken
- **Kanon i aktor:** pakiet P22; aktor zasobu z polityką owner/capability. Właściciel tabel: Onboarding.
- **Interface / DTO:** platform, token, device_id → token registration. POST /api/v1/notifications/device-tokens OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** device_tokens(user_id,platform,device_hmac,token_hmac,token_ciphertext,revoked_at). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Aktualny stan | autoryzacja, wersja, policy gate i token bound to another user→409 | absent/rotated→active; logout/revoke→revoked | rollback przed commit; po skutku zewnętrznym oddzielna idempotentna korekta |

- **Transakcja:** company10 gdy nagroda→Ledger20/Monetization121 gdy nagroda→Onboarding123→append140; Idempotency-Key lub stabilny klucz domenowy + CAS; effect key i outbox w tej samej transakcji.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; due_slot dla terminów, fencing dla workera, seed+wynik RNG tylko gdy losowanie; deadlock wycofuje całość i ponawia ten sam efekt.
- **Zdarzenie:** onboarding.register_device_token.v1; payload: event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorca przez rejestr subskrypcji; retry z event_id, po limicie DLQ i alert.
- **UI/Filament:** Centrum powiadomień i samouczek; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** rotate/revoke, push privacy, duplicate; token bound to another user→409; test własności i odmowy, MySQL dwie sesje dla mutacji, retry po commit, licznik onboarding.registerdevicetoken.success/conflict/error bez PII.
- **Migracja/rollback:** expand tworzy własne tabele/indeksy; greenfield bez backfill, import legacy tylko z osobnym mapowaniem. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P22-F06 — ListNotifications
- **Kanon i aktor:** pakiet P22; uwierzytelniony aktor z polityką zasobu. Właściciel tabel: Onboarding.
- **Interface / DTO:** cursor, limit → own notification center. GET /api/v1/notifications OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** notifications(user_id,template_key,locale,event_key,payload_json,created_at_event,…); notification_preferences(user_id,channel_code,category_code,enabled). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Bez zmiany | autoryzacja/widoczność; foreign notification→404 | read-only projection | Brak skutku ubocznego |

- **Transakcja:** brak blokad zapisu, idempotencji mutacyjnej i outbox.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; ETag/as_of i polityka widoczności.
- **Zdarzenie:** brak.
- **UI/Filament:** Centrum powiadomień i samouczek; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** paging, locale fallback, preference; foreign notification→404; test własności i odmowy, paginacji/ETag, brak zapisu, licznik onboarding.listnotifications.success/conflict/error bez PII.
- **Migracja/rollback:** odczyt istniejących tabel; indeksy i migracje właściciela wskazuje DDL, bez własnego backfill. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P22-F07 — MarkNotificationRead
- **Kanon i aktor:** pakiet P22; aktor zasobu z polityką owner/capability. Właściciel tabel: Onboarding.
- **Interface / DTO:** notification_id → read_at. POST /api/v1/notifications/{id}/read OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** notifications(user_id,template_key,locale,event_key,payload_json,created_at_event,…). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Aktualny stan | autoryzacja, wersja, policy gate i foreign notification→404 | unread→read once | rollback przed commit; po skutku zewnętrznym oddzielna idempotentna korekta |

- **Transakcja:** company10 gdy nagroda→Ledger20/Monetization121 gdy nagroda→Onboarding123→append140; Idempotency-Key lub stabilny klucz domenowy + CAS; effect key i outbox w tej samej transakcji.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; due_slot dla terminów, fencing dla workera, seed+wynik RNG tylko gdy losowanie; deadlock wycofuje całość i ponawia ten sam efekt.
- **Zdarzenie:** onboarding.mark_notification_read.v1; payload: event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorca przez rejestr subskrypcji; retry z event_id, po limicie DLQ i alert.
- **UI/Filament:** Centrum powiadomień i samouczek; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** retry, concurrent read; foreign notification→404; test własności i odmowy, MySQL dwie sesje dla mutacji, retry po commit, licznik onboarding.marknotificationread.success/conflict/error bez PII.
- **Migracja/rollback:** expand tworzy własne tabele/indeksy; greenfield bez backfill, import legacy tylko z osobnym mapowaniem. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P22-F08 — GetTutorialProgress
- **Kanon i aktor:** pakiet P22; uwierzytelniony aktor z polityką zasobu. Właściciel tabel: Onboarding.
- **Interface / DTO:** company → steps/rewards. GET /api/v1/onboarding/progress OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** tutorial_progress(user_id,step_code,completed_at); achievement_grants(user_id,achievement_code,granted_at,source_event_key). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Bez zmiany | autoryzacja/widoczność; foreign company→404 | read-only projection | Brak skutku ubocznego |

- **Transakcja:** brak blokad zapisu, idempotencji mutacyjnej i outbox.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; ETag/as_of i polityka widoczności.
- **Zdarzenie:** brak.
- **UI/Filament:** Centrum powiadomień i samouczek; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** FTUE state, reward once; foreign company→404; test własności i odmowy, paginacji/ETag, brak zapisu, licznik onboarding.gettutorialprogress.success/conflict/error bez PII.
- **Migracja/rollback:** odczyt istniejących tabel; indeksy i migracje właściciela wskazuje DDL, bez własnego backfill. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P23-F07 — PublishNavigation
- **Kanon i aktor:** pakiet P23; podpisany provider lub GM z capability, 2FA i audytem. Właściciel tabel: CorePrivacy.
- **Interface / DTO:** draft menu version/hash → published navigation. Komenda wewnętrzna lub Filament Action bez publicznej trasy. OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** navigation_items(menu_key,locale,version_no,position_no,label,route_name,…). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Aktualny stan | autoryzacja, wersja, policy gate i invalid target/locale/hash→409 | draft→published immutable version | rollback przed commit; po skutku zewnętrznym oddzielna idempotentna korekta |

- **Transakcja:** owner danych→CorePrivacy124→append140; Idempotency-Key lub stabilny klucz domenowy + CAS; effect key i outbox w tej samej transakcji.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; due_slot dla terminów, fencing dla workera, seed+wynik RNG tylko gdy losowanie; deadlock wycofuje całość i ponawia ten sam efekt.
- **Zdarzenie:** core_privacy.publish_navigation.v1; payload: event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorca przez rejestr subskrypcji; retry z event_id, po limicie DLQ i alert.
- **UI/Filament:** CMS i prywatność; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** broken link, PL/EN/DE, concurrent publish; invalid target/locale/hash→409; test własności i odmowy, MySQL dwie sesje dla mutacji, retry po commit, licznik coreprivacy.publishnavigation.success/conflict/error bez PII.
- **Migracja/rollback:** expand tworzy własne tabele/indeksy; greenfield bez backfill, import legacy tylko z osobnym mapowaniem. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P23-F08 — GetPublishedPage
- **Kanon i aktor:** pakiet P23; publiczny odczyt wyłącznie opublikowanych danych. Właściciel tabel: CorePrivacy.
- **Interface / DTO:** slug, locale → published safe HTML/version. GET /api/v1/content/pages/{slug} OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** static_pages(slug,locale,published_version_id); page_versions(page_id,version_no,safe_html,approved_by_user_id,published_at); navigation_items(menu_key,locale,version_no,position_no,label,route_name,…). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Bez zmiany | autoryzacja/widoczność; unpublished/foreign→404 | read-only, no draft leakage | Brak skutku ubocznego |

- **Transakcja:** brak blokad zapisu, idempotencji mutacyjnej i outbox.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; ETag/as_of i polityka widoczności.
- **Zdarzenie:** brak.
- **UI/Filament:** CMS i prywatność; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** XSS, locale fallback, ETag; unpublished/foreign→404; test własności i odmowy, paginacji/ETag, brak zapisu, licznik coreprivacy.getpublishedpage.success/conflict/error bez PII.
- **Migracja/rollback:** odczyt istniejących tabel; indeksy i migracje właściciela wskazuje DDL, bez własnego backfill. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P23-F09 — GetPolicy
- **Kanon i aktor:** pakiet P23; publiczny odczyt wyłącznie opublikowanych danych. Właściciel tabel: CorePrivacy.
- **Interface / DTO:** policy_id, locale → active policy/version. GET /api/v1/privacy/policies/{id} OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** privacy_policies(policy_code,locale,version_no,body_html,effective_at). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Bez zmiany | autoryzacja/widoczność; unknown version→404 | read-only immutable version | Brak skutku ubocznego |

- **Transakcja:** brak blokad zapisu, idempotencji mutacyjnej i outbox.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; ETag/as_of i polityka widoczności.
- **Zdarzenie:** brak.
- **UI/Filament:** CMS i prywatność; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** effective_at, locale, consent version; unknown version→404; test własności i odmowy, paginacji/ETag, brak zapisu, licznik coreprivacy.getpolicy.success/conflict/error bez PII.
- **Migracja/rollback:** odczyt istniejących tabel; indeksy i migracje właściciela wskazuje DDL, bez własnego backfill. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.
