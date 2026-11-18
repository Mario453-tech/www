# OilEmpire — karty implementacyjne P19–P20

Status: projekt wykonawczy do programowania, nie gotowy kod. Czytać z [planem](OILEMPIRE_PLAN_PROGRAMOWANIA.md), [kontraktami maszynowymi](OILEMPIRE_KONTRAKTY_P14_P25.yaml), [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql), [OpenAPI](OILEMPIRE_OPENAPI_P14_P25_V1.yaml) i [rejestrem propozycji DOM](OILEMPIRE_PROPOZYCJE_DOM_P14_P25.md). Zatwierdzone OPEN01–OPEN12 obowiązują; nierozstrzygnięte DOM nie mają wartości produkcyjnej. Każda mutacja działa po Settlement, z pełnym LockPlan, Ledger i outbox według architektury §23.5a; GET ma aktualny stan bez nieautoryzowanego zapisu. UI Vue3/Flutter/Filament korzystają z tych samych Actions, nie z własnej ekonomii.

## P19. Refining/EPC/Insurance
Źródło: §31.1–31.10, §21. Właściciel zapisu: **Refining**; korzeń balansu: `refining`; poprzedniki: P08/P10/P12/P13/P14/P15/P16/P17.
Dane: refinery_plots, refineries, epc_contracts, epc_stages, refinery_recipe_versions, refinery_runs, product_movements, insurance_policies, insurance_claims; product_storages przez kontrakt Logistics. Właściciele cudzych tabel są odpytywani przez publiczny kontrakt. Mutowalne ID wewnętrzne BIGINT, publiczne ULID; kwoty BIGINT grosze/API string, ropa DECIMAL(16,4), czas UTC DATETIME(3).
LockPlan: firma10 → settlement11/due12 → Staff14/Legal15/Incidents17 → ledger20 → stock70 → refineries120 → append140. Zasoby obu firm deklaruje się przed pierwszym lockiem; żaden handler nie wraca do wcześniejszej rangi.
Reguły: EPC max2 równoległe budowy, raty i etapy utrwalone. Recepturę wybiera się wg popytu/kontraktów, ale zatwierdza gracz; przerób w fazie2.9, księgowanie w fazie5. Flara max2h, potem idle_standby; bez drugiego naliczania pensji w OPEX. Ubezpieczenia: najpierw deductible, potem cap50M/dobę i pula.
UI/GM: Filament: działki, EPC, receptury, szkody, polisy, parametry i audyt korekt. Web Vue3 i aplikacja mobile korzystają z tej samej Action; PL/EN/DE, lokalne SVG, dostępny modal zamiast okna systemowego.
Odbiór pakietu: każda karta ma test pozytywny, odmowy, obcej firmy, retry, równoległości MySQL i awarii workera. Parametry tylko z zatwierdzonej balance_version; propozycje DOM nie aktywują funkcji.


### P19-F01 — StartEpcBuild
- **Aktor i kontrakt:** aktor firmy z capability `refining.manage`. plot_id, archetype, quote_version, expected_version → epc_id/stage plan. `POST /api/v1/refining/epc-contracts` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `refinery_plots`, `epc_contracts`, `epc_stages`. plot owned/permit valid → signed/building; rata przez Ledger raz, snapshot archetypu i due events. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11/due12 → Staff14/Legal15/Incidents17 → ledger20 → stock70 → refineries120 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `refining.start_epc_build.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** limit2 budów, brak środków/pozwolenia → rollback. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** równoległy start, gray F flag, takeover; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P19-F02 — AdvanceBuild
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. epc_id, due_stage, state_version → stage/result. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `epc_contracts`, `epc_stages`, `refineries`. signed/building/paused_funds → next/completed; exact due_at, cap kosztu, brak reroll. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11/due12 → Staff14/Legal15/Incidents17 → ledger20 → stock70 → refineries120 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `refining.advance_build.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak raty → paused_funds; worker retry no-op. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** awaria job, aukcja w budowie, drugi worker; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P19-F03 — SelectRecipe
- **Aktor i kontrakt:** aktor firmy z capability `refining.manage`. refinery_id, recipe_code, effective_from, demand_snapshot, expected_version → selected recipe. `PUT /api/v1/refining/refineries/{id}/recipe` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `refinery_recipe_versions`, `refineries`. jawny wybór A/B/C na kolejny segment; podgląd popytu i kontraktów, ale nie automatyczna zmiana bez akcji. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11/due12 → Staff14/Legal15/Incidents17 → ledger20 → stock70 → refineries120 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `refining.select_recipe.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak wsadu, nielegalna receptura/umowa → odmowa. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** zmiana w połowie segmentu, kontrakt jet/RARS; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P19-F04 — SettleRefinery
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. refinery_id, from,to,balance_version → oil/product movements, OPEX/FTS refs. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `refinery_runs`, `product_movements`, `refineries`. faza2.9 przerób i flara max2h, faza5 FTS; produkty tworzone tylko z realnego wsadu, pełny zbiornik → standby. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11/due12 → Staff14/Legal15/Incidents17 → ledger20 → stock70 → refineries120 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `refining.settle_refinery.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** strike/awaria/zmiana właściciela segmentuje czas. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** długi Δt, brak double OPEX, receptury i jakość; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P19-F05 — SubmitInsuranceClaim
- **Aktor i kontrakt:** aktor firmy z capability `refining.manage`. incident_id, policy_id, evidence_refs → claim/status. `POST /api/v1/refining/claims` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `insurance_claims`, `insurance_policies`. reported→validated→queued/paid; deductible przed cap50M/dobę i saldem pool, malus raz. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11/due12 → Staff14/Legal15/Incidents17 → ledger20 → stock70 → refineries120 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `refining.submit_insurance_claim.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** duplikat szkody albo brak polisy → odmowa. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** wiele szkód tego dnia, wypłata po retry; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.



## P20. Monetization
Źródło: §22.1–22.6, §24.3, §21. Właściciel zapisu: **Monetization**; korzeń balansu: `monetization`; poprzedniki: P02/P03/P05/P06/P17.
Dane: payment_orders, payment_transactions, payment_events, gbbl_accounts, gbbl_ledger, gbbl_conversions, promo_codes, premium_entitlements, platform_purchase_receipts. Właściciele cudzych tabel są odpytywani przez publiczny kontrakt. Mutowalne ID wewnętrzne BIGINT, publiczne ULID; kwoty BIGINT grosze/API string, ropa DECIMAL(16,4), czas UTC DATETIME(3).
LockPlan: firma10 → settlement11/due12 → ledger20 → stan Monetization rank121 → append140. Zasoby obu firm deklaruje się przed pierwszym lockiem; żaden handler nie wraca do wcześniejszej rangi.
Reguły: Web operator adapter; Android Play Billing i iOS Apple IAP z serwerową weryfikacją. Webhook podpisany i idempotentny. GBBL osobna księga, konwersja blokowana w recovery/egzekucji Faza2+, refund/chargeback tworzy dług GBBL bez kasowania historii FTS.
UI/GM: Filament: katalog, zamówienia, refundy, promocje i audyt; brak dostępu do danych kart. Web Vue3 i aplikacja mobile korzystają z tej samej Action; PL/EN/DE, lokalne SVG, dostępny modal zamiast okna systemowego.
Odbiór pakietu: każda karta ma test pozytywny, odmowy, obcej firmy, retry, równoległości MySQL i awarii workera. Parametry tylko z zatwierdzonej balance_version; propozycje DOM nie aktywują funkcji.


### P20-F01 — CreateOrder
- **Aktor i kontrakt:** aktor firmy z capability `monetization.purchase`. product_code, channel, client_key → order_id/payment_intent. `POST /api/v1/monetization/orders` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `payment_orders`. draft→pending; cena i benefit z zatwierdzonej wersji katalogu. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11/due12 → ledger20 → stan Monetization rank121 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `monetization.create_order.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak aktywnego produktu/nielegalny kanał → odmowa. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** dwa kliknięcia jedna intencja; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P20-F02 — VerifyWebhook
- **Aktor i kontrakt:** podpisany adapter operatora. provider_event_id, signature, payload_hash → accepted/no-op. `POST /api/v1/payments/webhooks/{provider}` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `payment_events`, `payment_transactions`. pending→paid/failed/refunded według adaptera; zapisz surowy id i zredagowany audit raz. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11/due12 → ledger20 → stan Monetization rank121 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `monetization.verify_webhook.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** błędny podpis/kwota/waluta → reject bez benefitów. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** out-of-order i duplikat eventu; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P20-F03 — GrantGbbl
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. paid_order_id, receipt_ref → grant_id/balance. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `gbbl_ledger`, `premium_entitlements`. paid→granted; gbbl_ledger i benefit raz, outbox po commit. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11/due12 → ledger20 → stan Monetization rank121 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `monetization.grant_gbbl.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** niepotwierdzona płatność → no-op/odmowa. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** webhook kontra retry/reversal; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P20-F04 — ConvertGbbl
- **Aktor i kontrakt:** aktor firmy z capability `monetization.purchase`. company_id, amount, expected_version → conversion_id/cash_minor. `POST /api/v1/monetization/gbbl/conversions` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `gbbl_conversions`, `gbbl_accounts`. GBBL debit→PLN game FTS, drip30d/tier_base; blokada recovery/faza2 egzekucji. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11/due12 → ledger20 → stan Monetization rank121 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `monetization.convert_gbbl.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak GBBL/nieaktywny balans → odmowa. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** równoległe konwersje, tier północ; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P20-F05 — ApplyChargeback
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. payment_event_id, order_id → chargeback/debt. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `payment_events`, `gbbl_accounts`. granted→charged_back; historia immutable, dług GBBL i blokada zakupów/kroplówki. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11/due12 → ledger20 → stan Monetization rank121 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `monetization.apply_chargeback.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** duplikat eventu bez drugiego długu. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** zużyty benefit, refund po grant, retry; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


