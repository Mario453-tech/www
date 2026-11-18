# OilEmpire — karty implementacyjne P19–P20

Status: kontrakt dokumentacyjny do programowania po audycie; wartości DOM pozostają do zatwierdzenia. Szczegóły wymagane przez plan §3 są powiązane przez [macierz DoR akcji](OILEMPIRE_KONTRAKTY_AKCJI_P14_P25.md). Czytać z [planem](OILEMPIRE_PLAN_PROGRAMOWANIA.md), [kontraktami maszynowymi](OILEMPIRE_KONTRAKTY_P14_P25.yaml), [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql), [OpenAPI](OILEMPIRE_OPENAPI_P14_P25_V1.yaml) i [rejestrem propozycji DOM](OILEMPIRE_PROPOZYCJE_DOM_P14_P25.md). Zatwierdzone OPEN01–OPEN12 obowiązują; nierozstrzygnięte DOM nie mają wartości produkcyjnej. Każda mutacja stosuje wymagany Settlement i LockPlan §23.5a; Ledger, ruch fizyczny i outbox tylko wtedy, gdy operacja ma taki skutek. GET jest odczytem bez skutków ubocznych. UI Vue3/Flutter/Filament korzystają z tych samych Actions, nie z własnej ekonomii.

## P19. Refining/EPC/Insurance
Źródło: §31.1–31.10, §21. Właściciel zapisu: **Refining**; korzeń balansu: `refining`; poprzedniki: P08/P10/P12/P13/P14/P15/P16/P17.
Dane: refinery_plots, refineries, epc_contracts, epc_stages, refinery_recipe_versions, refinery_runs, product_movements, insurance_policies, insurance_claims; product_storages przez kontrakt Logistics. Właściciele cudzych tabel są odpytywani przez publiczny kontrakt. Mutowalne ID wewnętrzne BIGINT, publiczne ULID; kwoty BIGINT grosze/API string, ropa DECIMAL(16,4), czas UTC DATETIME(3).
LockPlan: firma10 → settlement11/due12 → Staff14/Legal15/Incidents17 → ledger20 → fund_reservations21 gdy ubezpieczenie → stock70 → refineries120 → append140. Zasoby obu firm deklaruje się przed pierwszym lockiem; żaden handler nie wraca do wcześniejszej rangi.
Reguły: EPC max2 równoległe budowy, raty i etapy utrwalone. Recepturę wybiera się wg popytu/kontraktów, ale zatwierdza gracz; przerób w fazie2.9, księgowanie w fazie5. Flara max2h, potem idle_standby; bez drugiego naliczania pensji w OPEX. Ubezpieczenia: najpierw deductible, potem cap50M/dobę i pula.
UI/GM: Filament: działki, EPC, receptury, szkody, polisy, parametry i audyt korekt. Web Vue3 i aplikacja mobile korzystają z tej samej Action; PL/EN/DE, lokalne SVG, dostępny modal zamiast okna systemowego.
Odbiór pakietu: każda karta ma test pozytywny, odmowy, obcej firmy, retry, równoległości MySQL i awarii workera. Parametry tylko z zatwierdzonej balance_version; propozycje DOM nie aktywują funkcji.


### P19-F01 — StartEpcBuild
- **Aktor i kontrakt:** aktor firmy z capability `refining.manage`. plot_id, archetype, quote_version, expected_version → epc_id/stage plan. `POST /api/v1/refining/epc-contracts` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `refinery_plots`, `epc_contracts`, `epc_stages`. plot owned/permit valid → signed/building; rata przez Ledger raz, snapshot archetypu i due events. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11/due12 → Staff14/Legal15/Incidents17 → ledger20 → fund_reservations21 gdy ubezpieczenie → stock70 → refineries120 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `refining.start_epc_build.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** limit2 budów, brak środków/pozwolenia → rollback. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** równoległy start, gray F flag, takeover; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P19-F02 — AdvanceBuild
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. epc_id, due_stage, state_version → stage/result. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `epc_contracts`, `epc_stages`, `refineries`. signed/building/paused_funds → next/completed; exact due_at, cap kosztu, brak reroll. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11/due12 → Staff14/Legal15/Incidents17 → ledger20 → fund_reservations21 gdy ubezpieczenie → stock70 → refineries120 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `refining.advance_build.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak raty → paused_funds; worker retry no-op. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** awaria job, aukcja w budowie, drugi worker; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P19-F03 — SelectRecipe
- **Aktor i kontrakt:** aktor firmy z capability `refining.manage`. refinery_id, recipe_code, effective_from, expected_state_version → selected recipe oraz serwerowy snapshot popytu i kontraktów; klient nie podaje snapshotu. `PUT /api/v1/refining/refineries/{id}/recipe` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `refinery_recipe_versions`, `refineries`. jawny wybór A/B/C na kolejny segment; serwer oblicza rekomendację według [kontraktu receptury](OILEMPIRE_RECEPTURA_POPYT_KONTRAKTY.md), utrwala demand_snapshot_at, demand_snapshot_json, contract_snapshot_json i contract_snapshot_hash; gracz zatwierdza wybór. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11/due12 → Staff14/Legal15/Incidents17 → ledger20 → fund_reservations21 gdy ubezpieczenie → stock70 → refineries120 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `refining.select_recipe.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak wsadu, nielegalna receptura/umowa → odmowa. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** zmiana w połowie segmentu, kontrakt jet/RARS; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P19-F04 — SettleRefinery
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. refinery_id, from,to,balance_version → oil/product movements, OPEX/FTS refs. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `refinery_runs`, `product_movements`, `refineries`. faza2.9 przerób i flara max2h, faza5 FTS; produkty tworzone tylko z realnego wsadu, pełny zbiornik → standby. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11/due12 → Staff14/Legal15/Incidents17 → ledger20 → fund_reservations21 gdy ubezpieczenie → stock70 → refineries120 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `refining.settle_refinery.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** strike/awaria/zmiana właściciela segmentuje czas. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** długi Δt, brak double OPEX, receptury i jakość; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P19-F05 — SubmitInsuranceClaim
- **Aktor i kontrakt:** aktor firmy z capability `refining.manage`. incident_id, policy_id, evidence_refs → claim/status. `POST /api/v1/refining/claims` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `insurance_claims`, `insurance_policies`. reported→validated→queued/paid; deductible przed cap50M/dobę i saldem pool, malus raz. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11/due12 → Staff14/Legal15/Incidents17 → ledger20 → fund_reservations21 gdy ubezpieczenie → stock70 → refineries120 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `refining.submit_insurance_claim.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** duplikat szkody albo brak polisy → odmowa. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** wiele szkód tego dnia, wypłata po retry; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.



## P20. Monetization
Źródło: §22.1–22.6, §24.3, §21. Właściciel zapisu: **Monetization**; korzeń balansu: `monetization`; poprzedniki: P02/P03/P05/P06/P17.
Dane: payment_orders, payment_transactions, payment_events, gbbl_accounts, gbbl_ledger, gbbl_conversions, promo_codes, premium_entitlements, platform_purchase_receipts. Właściciele cudzych tabel są odpytywani przez publiczny kontrakt. Mutowalne ID wewnętrzne BIGINT, publiczne ULID; kwoty BIGINT grosze/API string, ropa DECIMAL(16,4), czas UTC DATETIME(3).
LockPlan: firma10 → settlement11/due12 → ledger20 → stan Monetization rank121 → append140. Zasoby obu firm deklaruje się przed pierwszym lockiem; żaden handler nie wraca do wcześniejszej rangi.
Reguły: Web operator adapter; Android Play Billing i iOS Apple IAP z serwerową weryfikacją. Webhook podpisany i idempotentny. GBBL osobna księga, konwersja blokowana w recovery/egzekucji Faza2+, refund/chargeback tworzy dług GBBL bez kasowania historii FTS.
UI/GM: Filament: katalog, zamówienia, refundy, promocje i audyt; brak dostępu do danych kart. Web Vue3 i aplikacja mobile korzystają z tej samej Action; PL/EN/DE, lokalne SVG, dostępny modal zamiast okna systemowego.
Odbiór pakietu: każda karta ma test pozytywny, odmowy, obcej firmy, retry, równoległości MySQL i awarii workera. Parametry tylko z zatwierdzonej balance_version; propozycje DOM nie aktywują funkcji.


### P20-F01 — CreateOrder
- **Aktor i kontrakt:** aktor firmy z capability `monetization.purchase`. product_code, Idempotency-Key → order_id/payment_intent; channel/platform i provider wynikają z uwierzytelnionego kanału serwera, nie z żądania klienta. `POST /api/v1/monetization/orders` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `payment_orders`. draft→pending; cena i benefit z zatwierdzonej wersji katalogu. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11/due12 → ledger20 → stan Monetization rank121 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `monetization.create_order.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak aktywnego produktu/nielegalny kanał → odmowa. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** dwa kliknięcia jedna intencja; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P20-F02 — VerifyWebhook
- **Aktor i kontrakt:** podpisany adapter operatora. provider_event_id, signature, payload_hash → accepted/no-op. `POST /api/v1/payments/webhooks/{provider}` — osobny kontrakt podpisanego providera; klient web/mobile nie wywołuje webhooka.
- **Dane i przejście:** `payment_events`, `payment_transactions`. pending→paid/failed/refunded według adaptera; zapisz surowy id i zredagowany audit raz. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11/due12 → ledger20 → stan Monetization rank121 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `monetization.verify_webhook.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** błędny podpis/kwota/waluta → reject bez benefitów. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** out-of-order i duplikat eventu; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P20-F03 — GrantGbbl
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. paid_order_id, receipt_ref → grant_id/balance. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `gbbl_ledger`, `premium_entitlements`. paid→granted; gbbl_ledger i benefit raz, outbox po commit. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11/due12 → ledger20 → stan Monetization rank121 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `monetization.grant_gbbl.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** niepotwierdzona płatność → no-op/odmowa. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** webhook kontra retry/reversal; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P20-F04 — ConvertGbbl
- **Aktor i kontrakt:** aktor firmy z capability `monetization.purchase`. company_id, amount, expected_version → conversion_id/cash_minor. `POST /api/v1/monetization/gbbl/conversions` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `gbbl_conversions`, `gbbl_accounts`. GBBL debit→PLN game FTS, drip30d/tier_base; blokada recovery/faza2 egzekucji. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11/due12 → ledger20 → stan Monetization rank121 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `monetization.convert_gbbl.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak GBBL/nieaktywny balans → odmowa. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** równoległe konwersje, tier północ; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P20-F05 — ApplyChargeback
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. payment_event_id, order_id → chargeback/debt. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `payment_events`, `gbbl_accounts`. granted→charged_back; historia immutable, dług GBBL i blokada zakupów/kroplówki. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11/due12 → ledger20 → stan Monetization rank121 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `monetization.apply_chargeback.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** duplikat eventu bez drugiego długu. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** zużyty benefit, refund po grant, retry; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.



## Uzupełnione akcje i zapytania po audycie kompletności

Poniższe ID należą do odpowiednich pakietów i korzystają z tych samych kontraktów wersji, LockPlan, DDL i polityk. Pełna macierz DoR dla wszystkich akcji jest w [kontrakcie szczegółowym](OILEMPIRE_KONTRAKTY_AKCJI_P14_P25.md).

### P19-F06 — ListRefineryProducts
- **Kanon i aktor:** pakiet P19; uwierzytelniony aktor z polityką zasobu. Właściciel tabel: Refining.
- **Interface / DTO:** refinery, cursor → product balances and demand recommendation. GET /api/v1/refining/products OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** refineries(plot_id,tier_code,capacity_bbl_day,commissioned_at,selected_recipe_id); refinery_runs(refinery_id,recipe_id,slot_at,input_bbl,output_bbl,flare_bbl); product_movements(refinery_run_id,product_code,quantity_bbl,destination_public_ulid). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Bez zmiany | autoryzacja/widoczność; foreign refinery→404 | read-only; recommendation server-side | Brak skutku ubocznego |

- **Transakcja:** brak blokad zapisu, idempotencji mutacyjnej i outbox.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; ETag/as_of i polityka widoczności.
- **Zdarzenie:** brak.
- **UI/Filament:** Rafineria; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** recipe snapshot, product storage ownership; foreign refinery→404; test własności i odmowy, paginacji/ETag, brak zapisu, licznik refining.listrefineryproducts.success/conflict/error bez PII.
- **Migracja/rollback:** odczyt istniejących tabel; indeksy i migracje właściciela wskazuje DDL, bez własnego backfill. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P20-F06 — VerifyPlatformReceipt
- **Kanon i aktor:** pakiet P20; użytkownik zakupujący na Android/iOS; serwer weryfikuje receipt u sklepu. Właściciel tabel: Monetization.
- **Interface / DTO:** order_id, platform receipt token → verified/pending. POST /api/v1/monetization/mobile/receipts OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** payment_orders(user_id,platform,provider,product_code,catalog_version,benefit_snapshot_hash,…); platform_purchase_receipts(user_id,order_id,platform,provider_transaction_id,receipt_hash,receipt_ciphertext,…); payment_transactions(order_id,event_id,provider,provider_transaction_id,amount_minor,settled_at). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Aktualny stan | autoryzacja, wersja, policy gate i wrong account/product/transaction replay→409 | pending→verified only after store server validation | rollback przed commit; po skutku zewnętrznym oddzielna idempotentna korekta |

- **Transakcja:** company10 gdy związane z firmą→Ledger20 gdy PLN→Monetization121→append140; Idempotency-Key lub stabilny klucz domenowy + CAS; effect key i outbox w tej samej transakcji.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; due_slot dla terminów, fencing dla workera, seed+wynik RNG tylko gdy losowanie; deadlock wycofuje całość i ponawia ten sam efekt.
- **Zdarzenie:** monetization.verify_platform_receipt.v1; payload: event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorca przez rejestr subskrypcji; retry z event_id, po limicie DLQ i alert.
- **UI/Filament:** Sklep; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** Play/IAP adapter replay, delayed store response; wrong account/product/transaction replay→409; test własności i odmowy, MySQL dwie sesje dla mutacji, retry po commit, licznik monetization.verifyplatformreceipt.success/conflict/error bez PII.
- **Migracja/rollback:** expand tworzy własne tabele/indeksy; greenfield bez backfill, import legacy tylko z osobnym mapowaniem. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P20-F07 — ApplyRefund
- **Kanon i aktor:** pakiet P20; podpisany provider lub GM z capability, 2FA i audytem. Właściciel tabel: Monetization.
- **Interface / DTO:** provider refund event, order, amount → refunded/recovery debt. Komenda wewnętrzna lub Filament Action bez publicznej trasy. OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** payment_refunds(order_id,provider,provider_refund_id,amount_minor,received_at,recovery_units); payment_orders(user_id,platform,provider,product_code,catalog_version,benefit_snapshot_hash,…); gbbl_accounts(user_id,available_units,recovery_debt_units); gbbl_ledger(user_id,transaction_key,fulfillment_key,delta_units,reason_code,order_id). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Aktualny stan | autoryzacja, wersja, policy gate i unsigned/unmatched/excess refund→reject | paid/granted→partial_or_full_refund, append compensation once | rollback przed commit; po skutku zewnętrznym oddzielna idempotentna korekta |

- **Transakcja:** company10 gdy związane z firmą→Ledger20 gdy PLN→Monetization121→append140; Idempotency-Key lub stabilny klucz domenowy + CAS; effect key i outbox w tej samej transakcji.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; due_slot dla terminów, fencing dla workera, seed+wynik RNG tylko gdy losowanie; deadlock wycofuje całość i ponawia ten sam efekt.
- **Zdarzenie:** monetization.apply_refund.v1; payload: event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorca przez rejestr subskrypcji; retry z event_id, po limicie DLQ i alert.
- **UI/Filament:** Sklep; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** refund after conversion/grant, two refunds, chargeback race; unsigned/unmatched/excess refund→reject; test własności i odmowy, MySQL dwie sesje dla mutacji, retry po commit, licznik monetization.applyrefund.success/conflict/error bez PII.
- **Migracja/rollback:** expand tworzy własne tabele/indeksy; greenfield bez backfill, import legacy tylko z osobnym mapowaniem. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P20-F08 — RedeemPromoCode
- **Kanon i aktor:** pakiet P20; aktor zasobu z polityką owner/capability. Właściciel tabel: Monetization.
- **Interface / DTO:** code, product, expected_version → discounted order quote. POST /api/v1/monetization/promo-redemptions OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** promo_codes(code_hmac,product_code,discount_basis_points,quota_total,quota_used,starts_at,…); payment_orders(user_id,platform,provider,product_code,catalog_version,benefit_snapshot_hash,…). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Aktualny stan | autoryzacja, wersja, policy gate i expired/owner mismatch/quota exhausted→409 | active→reserved→redeemed once with quota CAS | rollback przed commit; po skutku zewnętrznym oddzielna idempotentna korekta |

- **Transakcja:** company10 gdy związane z firmą→Ledger20 gdy PLN→Monetization121→append140; Idempotency-Key lub stabilny klucz domenowy + CAS; effect key i outbox w tej samej transakcji.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; due_slot dla terminów, fencing dla workera, seed+wynik RNG tylko gdy losowanie; deadlock wycofuje całość i ponawia ten sam efekt.
- **Zdarzenie:** monetization.redeem_promo_code.v1; payload: event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorca przez rejestr subskrypcji; retry z event_id, po limicie DLQ i alert.
- **UI/Filament:** Sklep; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** parallel final use, casefold, revoked code; expired/owner mismatch/quota exhausted→409; test własności i odmowy, MySQL dwie sesje dla mutacji, retry po commit, licznik monetization.redeempromocode.success/conflict/error bez PII.
- **Migracja/rollback:** expand tworzy własne tabele/indeksy; greenfield bez backfill, import legacy tylko z osobnym mapowaniem. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P20-F09 — GetOrder
- **Kanon i aktor:** pakiet P20; uwierzytelniony aktor z polityką zasobu. Właściciel tabel: Monetization.
- **Interface / DTO:** order_id → own status/grant/refund. GET /api/v1/monetization/orders/{id} OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** payment_orders(user_id,platform,provider,product_code,catalog_version,benefit_snapshot_hash,…); payment_transactions(order_id,event_id,provider,provider_transaction_id,amount_minor,settled_at); payment_refunds(order_id,provider,provider_refund_id,amount_minor,received_at,recovery_units). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Bez zmiany | autoryzacja/widoczność; foreign order→404 | read-only projection, no payment secrets | Brak skutku ubocznego |

- **Transakcja:** brak blokad zapisu, idempotencji mutacyjnej i outbox.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; ETag/as_of i polityka widoczności.
- **Zdarzenie:** brak.
- **UI/Filament:** Sklep; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** web/mobile parity, stale event; foreign order→404; test własności i odmowy, paginacji/ETag, brak zapisu, licznik monetization.getorder.success/conflict/error bez PII.
- **Migracja/rollback:** odczyt istniejących tabel; indeksy i migracje właściciela wskazuje DDL, bez własnego backfill. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P20-F10 — VerifyStoreNotification
- **Aktor:** powiadomienie Apple podpisane JWS albo Google RTDN po zweryfikowaniu tożsamości Pub/Sub; nigdy klient gry. POST /api/v1/payments/store-notifications/{platform} w osobnym OpenAPI providera.
- **DTO i przejście:** surowy podpisany payload → provider_event_id, transaction_id, product, order i typ; received→verified→normalized. Refund wywołuje ApplyRefund, chargeback ApplyChargeback; grant tylko po potwierdzeniu.
- **Dane:** payment_events, platform_purchase_receipts, payment_refunds; UNIQUE(provider,provider_event_id) i UNIQUE(platform,provider_transaction_id), pełna wersja w DDL.
- **LockPlan:** po weryfikacji podpisu firma10 gdy potrzebna → Ledger20 gdy PLN → Monetization121 → append140; retry identycznego event_id zwraca trwały wynik.
- **Awaria i test:** nieznany zakup/zły podpis nie daje świadczenia; replay, odwrócona kolejność refund/grant, timeout sklepu i dwa workery. Bez surowego tokenu w logach, DLQ po bounded retry.
