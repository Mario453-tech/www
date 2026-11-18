# OilEmpire — karty implementacyjne P21–P24

Status: projekt wykonawczy do programowania, nie gotowy kod. Czytać z [planem](OILEMPIRE_PLAN_PROGRAMOWANIA.md), [kontraktami maszynowymi](OILEMPIRE_KONTRAKTY_P14_P25.yaml), [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql), [OpenAPI](OILEMPIRE_OPENAPI_P14_P25_V1.yaml) i [rejestrem propozycji DOM](OILEMPIRE_PROPOZYCJE_DOM_P14_P25.md). Zatwierdzone OPEN01–OPEN12 obowiązują; nierozstrzygnięte DOM nie mają wartości produkcyjnej. Każda mutacja działa po Settlement, z pełnym LockPlan, Ledger i outbox według architektury §23.5a; GET ma aktualny stan bez nieautoryzowanego zapisu. UI Vue3/Flutter/Filament korzystają z tych samych Actions, nie z własnej ekonomii.

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
- **Transakcja:** firma10 → kontrakty110 gdy potrzebne → stan Social rank122 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `social.send_message.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** cudzy kanał/ban/rate limit → odmowa. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** dwa urządzenia, replay, XSS; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P21-F02 — JoinCartel
- **Aktor i kontrakt:** aktor firmy z capability `social.participate`. cartel_id, expected_version → membership. `POST /api/v1/social/cartels/{id}/members` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `cartels`, `cartel_memberships`. eligible→active; max5 członków i limit produkcji40%, relacja B2B od effective_at. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → kontrakty110 gdy potrzebne → stan Social rank122 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `social.join_cartel.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak prawa/pełny kartel → 409. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** równoległe zaproszenia i limit40%; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P21-F03 — LeaveCartel
- **Aktor i kontrakt:** aktor firmy z capability `social.participate`. membership_id → ended_at. `DELETE /api/v1/social/cartels/{id}/members/me` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `cartel_memberships`. active→left; przyszłe prowizje wg snapshot, stare rozliczenia bez zmian. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → kontrakty110 gdy potrzebne → stan Social rank122 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `social.leave_cartel.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** retry → ten sam wynik. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** B2B w locie, fee collar; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P21-F04 — ModerateMessage
- **Aktor i kontrakt:** Filament Action z jawną capability GM. message_id, decision, reason_code → moderation_record. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `chat_messages`, `moderation_reports`. visible→hidden/redacted z trwałą historią, osobny audit. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → kontrakty110 gdy potrzebne → stan Social rank122 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `social.moderate_message.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** moderator bez scope →403, brak cichego UPDATE treści. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** appeal, data retention, race; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P21-F05 — AuthorizeChannel
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. actor, channel, connection_epoch → grant/deny. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `channel_grants`. scope po logowaniu i przy każdej rewokacji; WS bez payload prywatnych danych po zmianie ról. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → kontrakty110 gdy potrzebne → stan Social rank122 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `social.authorize_channel.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** stara sesja/ban → disconnect. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** otwarty WebSocket po revokacji; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.



## P22. Notifications/Onboarding
Źródło: §27, §28. Właściciel zapisu: **Onboarding**; korzeń balansu: `onboarding`; poprzedniki: P02/P04/P06/P11/P12/P14.
Dane: tutorial_progress, achievement_grants, notifications, notification_deliveries, notification_preferences, device_tokens. Właściciele cudzych tabel są odpytywani przez publiczny kontrakt. Mutowalne ID wewnętrzne BIGINT, publiczne ULID; kwoty BIGINT grosze/API string, ropa DECIMAL(16,4), czas UTC DATETIME(3).
LockPlan: firma10 → źródłowy owner według globalnego rank → stan Onboarding rank123 → append140; wysyłka po commit. Zasoby obu firm deklaruje się przed pierwszym lockiem; żaden handler nie wraca do wcześniejszej rangi.
Reguły: Jedna trwała notyfikacja na zdarzenie/odbiorcę; push/e-mail są transportem, nie źródłem prawdy. FTUE i osiągnięcie mają unikalny slot nagrody; brak push nie cofa zdarzenia.
UI/GM: Filament: katalog szablonów, centrum dostaw i próby; bez wysyłania ukrytych cech. Web Vue3 i aplikacja mobile korzystają z tej samej Action; PL/EN/DE, lokalne SVG, dostępny modal zamiast okna systemowego.
Odbiór pakietu: każda karta ma test pozytywny, odmowy, obcej firmy, retry, równoległości MySQL i awarii workera. Parametry tylko z zatwierdzonej balance_version; propozycje DOM nie aktywują funkcji.


### P22-F01 — AdvanceTutorial
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. company_id, step_code, source_event → progress/reward. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `tutorial_progress`. pending→complete raz po domenowym dowodzie; etap szkoleniowego odwiertu i logistyki według §27.1a. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → źródłowy owner według globalnego rank → stan Onboarding rank123 → append140; wysyłka po commit. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `onboarding.advance_tutorial.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** pusty event/obca firma → odmowa. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** dwa wejścia i ponowny start firmy; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P22-F02 — GrantAchievement
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. company, achievement_code, evidence_id → grant. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `achievement_grants`. not_granted→granted; reward przez Ledger/GBBL raz. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → źródłowy owner według globalnego rank → stan Onboarding rank123 → append140; wysyłka po commit. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `onboarding.grant_achievement.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** stary event replay → no-op. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** exactly once reward; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P22-F03 — DeliverNotification
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. event_id, recipient_id, channel → delivery result. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `notifications`, `notification_deliveries`. outbox→pending→sent/failed/retry; centrum in-app trwałe, push/email adaptery. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → źródłowy owner według globalnego rank → stan Onboarding rank123 → append140; wysyłka po commit. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `onboarding.deliver_notification.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** wyłączenie push nie usuwa wpisu in-app. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** awaria FCM, duplicate job, offline; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P22-F04 — UpdatePreferences
- **Aktor i kontrakt:** aktor firmy z capability `notifications.manage`. user_id, categories/channels, expected_version → preferences. `PUT /api/v1/notifications/preferences` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `notification_preferences`. CAS; krytyczne systemowe nie zależą od opt-in marketingu. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → źródłowy owner według globalnego rank → stan Onboarding rank123 → append140; wysyłka po commit. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `onboarding.update_preferences.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak zgody newsletter → nie wysyłaj. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** zmiana zgody w trakcie kolejki; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.



## P23. CMS/Privacy
Źródło: §15–§16, §30. Właściciel zapisu: **Core Privacy**; korzeń balansu: `core`; poprzedniki: P02/P05/P06/P21/P22.
Dane: static_pages, page_versions, navigation_items, privacy_policies, consent_records, privacy_requests, newsletter_campaigns, newsletter_deliveries. Właściciele cudzych tabel są odpytywani przez publiczny kontrakt. Mutowalne ID wewnętrzne BIGINT, publiczne ULID; kwoty BIGINT grosze/API string, ropa DECIMAL(16,4), czas UTC DATETIME(3).
LockPlan: firma10 → właściciele danych według globalnego rank → Privacy/CMS rank124 → append140; publikacja config osobno. Zasoby obu firm deklaruje się przed pierwszym lockiem; żaden handler nie wraca do wcześniejszej rangi.
Reguły: TinyMCE tylko zatwierdzony HTML po sanitizacji; strona i regulamin wersjonowane. Eksport po autoryzacji; anonimizacja nie usuwa FTS. Newsletter wymaga ważnej zgody i audytu odwołania.
UI/GM: Filament: strony, menu, polityki, zgody, eksport/anonimizacja i newsletter z podglądem PL/EN/DE. Web Vue3 i aplikacja mobile korzystają z tej samej Action; PL/EN/DE, lokalne SVG, dostępny modal zamiast okna systemowego.
Odbiór pakietu: każda karta ma test pozytywny, odmowy, obcej firmy, retry, równoległości MySQL i awarii workera. Parametry tylko z zatwierdzonej balance_version; propozycje DOM nie aktywują funkcji.


### P23-F01 — PublishPage
- **Aktor i kontrakt:** Filament Action z jawną capability GM. draft_id, locale, expected_hash → page_version. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `static_pages`, `page_versions`. draft→published immutable; sanitizacja HTML i bezpieczny preview. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → właściciele danych według globalnego rank → Privacy/CMS rank124 → append140; publikacja config osobno. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `core_privacy.publish_page.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** zabroniony element/konflikt hash → odmowa. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** XSS, dwie publikacje; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P23-F02 — PublishPolicy
- **Aktor i kontrakt:** Filament Action z jawną capability GM. policy_version, effective_at → active. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `privacy_policies`. draft→active; snapshot treści i hash, nowa zgoda przy wymaganej zmianie. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → właściciele danych według globalnego rank → Privacy/CMS rank124 → append140; publikacja config osobno. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `core_privacy.publish_policy.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak tłumaczenia/akceptacji → odmowa. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** wersja historyczna i odwołanie; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P23-F03 — RecordConsent
- **Aktor i kontrakt:** aktor firmy z capability `privacy.manage`. user_id, policy_version, purpose, action → consent_event. `POST /api/v1/privacy/consents` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `consent_records`. grant/withdraw append-only; preferencje wynikają z ostatniego zdarzenia. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → właściciele danych według globalnego rank → Privacy/CMS rank124 → append140; publikacja config osobno. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `core_privacy.record_consent.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** obcy użytkownik →403/404. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** równoległa zgoda i withdrawal; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P23-F04 — ExportPersonalData
- **Aktor i kontrakt:** aktor firmy z capability `privacy.manage`. verified_actor, request_id → private archive/ref. `POST /api/v1/privacy/exports` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `privacy_requests`. requested→prepared→downloaded; chwilowy link, audyt pobrania. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → właściciele danych według globalnego rank → Privacy/CMS rank124 → append140; publikacja config osobno. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `core_privacy.export_personal_data.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak identity step-up → odmowa. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** cudze dane, wygaśnięcie linku; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P23-F05 — AnonymizeUser
- **Aktor i kontrakt:** aktor firmy z capability `privacy.manage`. approved_request, scope → anonymization_report. `POST /api/v1/privacy/anonymization-requests` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `privacy_requests`. pending→completed; PII ukryte/usunięte wg retencji, FTS i historia gospodarcza zachowane. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → właściciele danych według globalnego rank → Privacy/CMS rank124 → append140; publikacja config osobno. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `core_privacy.anonymize_user.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** aktywny obowiązek/hold → kontrolowany stan oczekiwania. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** rekoncyliacja po anonimizacji; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P23-F06 — SendNewsletter
- **Aktor i kontrakt:** Filament Action z jawną capability GM. campaign_id, audience_snapshot → delivery batch. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `newsletter_campaigns`, `newsletter_deliveries`. draft→approved→sending→completed; TinyMCE sanitized, zgoda sprawdzona przy wysyłce. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → właściciele danych według globalnego rank → Privacy/CMS rank124 → append140; publikacja config osobno. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `core_privacy.send_newsletter.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** withdrawn consent → skip. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** duplikat job, unsubscribe i bounce; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.



## P24. Web/Mobile delivery
Źródło: API v1, panel całość, §1/§28. Właściciel zapisu: **Client Delivery**; korzeń balansu: `—`; poprzedniki: P05–P23.
Dane: ui_preferences, client_version_support (bez ekonomii). Właściciele cudzych tabel są odpytywani przez publiczny kontrakt. Mutowalne ID wewnętrzne BIGINT, publiczne ULID; kwoty BIGINT grosze/API string, ropa DECIMAL(16,4), czas UTC DATETIME(3).
LockPlan: klient nie blokuje tabel domenowych; mutacje przez API/Actions właściciela. Zasoby obu firm deklaruje się przed pierwszym lockiem; żaden handler nie wraca do wcześniejszej rangi.
Reguły: Vue3/Inertia web oraz Flutter mobile używają jednego API v1 i DTO, bez drugich formuł ekonomii. Liczniki interpolowane tylko do estymaty, wynik serwera autorytatywny. Offline kolejkuje intencję z Idempotency-Key i ponownie waliduje wersję po połączeniu.
UI/GM: Filament zachowuje te same Actions i dostępność mobilną; komunikaty SVG i komponent modalny wspólne. Web Vue3 i aplikacja mobile korzystają z tej samej Action; PL/EN/DE, lokalne SVG, dostępny modal zamiast okna systemowego.
Odbiór pakietu: każda karta ma test pozytywny, odmowy, obcej firmy, retry, równoległości MySQL i awarii workera. Parametry tylko z zatwierdzonej balance_version; propozycje DOM nie aktywują funkcji.


### P24-F01 — SnapshotResource
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. resource_type/id, as_of → server snapshot/version. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `client_version_support`. GET rozlicza przez Settlement, maskuje prywatne pola, ETag/state_version. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** klient nie blokuje tabel domenowych; mutacje przez API/Actions właściciela. Odczyt autoryzowany, bez side effectów.
- **Czas i zdarzenie:** Brak księgowania i losowania w GET/quote. Brak zdarzenia domenowego z samego odczytu.
- **Odmowa/awaria:** cudzy zasób →404. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** web/mobile ten sam wynik; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P24-F02 — ApiClientContract
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. OpenAPI package and auth state → generated client binding. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `client_version_support`. Bearer 15min/refresh rotation, web cookie+CSRF; brak tokenu w localStorage; retry tylko przy idempotencji. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** klient nie blokuje tabel domenowych; mutacje przez API/Actions właściciela. Odczyt autoryzowany, bez side effectów.
- **Czas i zdarzenie:** Brak księgowania i losowania w GET/quote. Brak zdarzenia domenowego z samego odczytu.
- **Odmowa/awaria:** expired token → refresh raz; conflict→sync UI. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** stara wersja klienta, logout/revocation; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P24-F03 — DeepLinkRouting
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. app link, auth state, locale → safe screen. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `ui_preferences`. allowlista domen i tras; route to active resource after auth. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** klient nie blokuje tabel domenowych; mutacje przez API/Actions właściciela. Odczyt autoryzowany, bez side effectów.
- **Czas i zdarzenie:** Brak księgowania i losowania w GET/quote. Brak zdarzenia domenowego z samego odczytu.
- **Odmowa/awaria:** brak dostępu → safe 404, bez ujawniania istnienia. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** Android/iOS, offline, 320–1440 px; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


