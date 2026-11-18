# OilEmpire — karty implementacyjne P14–P18

Status: projekt wykonawczy do programowania, nie gotowy kod. Czytać z [planem](OILEMPIRE_PLAN_PROGRAMOWANIA.md), [kontraktami maszynowymi](OILEMPIRE_KONTRAKTY_P14_P25.yaml), [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql), [OpenAPI](OILEMPIRE_OPENAPI_P14_P25_V1.yaml) i [rejestrem propozycji DOM](OILEMPIRE_PROPOZYCJE_DOM_P14_P25.md). Zatwierdzone OPEN01–OPEN12 obowiązują; nierozstrzygnięte DOM nie mają wartości produkcyjnej. Każda mutacja działa po Settlement, z pełnym LockPlan, Ledger i outbox według architektury §23.5a; GET ma aktualny stan bez nieautoryzowanego zapisu. UI Vue3/Flutter/Filament korzystają z tych samych Actions, nie z własnej ekonomii.

## P14. Market
Źródło: §12, §19.3, §25, §23.3. Właściciel zapisu: **Market**; korzeń balansu: `market`; poprzedniki: P03/P04/P12.
Dane: market_offers, market_price_snapshots, market_trades, black_market_trades, world_events. Właściciele cudzych tabel są odpytywani przez publiczny kontrakt. Mutowalne ID wewnętrzne BIGINT, publiczne ULID; kwoty BIGINT grosze/API string, ropa DECIMAL(16,4), czas UTC DATETIME(3).
LockPlan: firma10 → settlement11 → ledger20 → stock70 → market_offers100 → globalna płynność/slippage130 → append140; cenę i matching publikuje tylko sweeper. Zasoby obu firm deklaruje się przed pierwszym lockiem; żaden handler nie wraca do wcześniejszej rangi.
Reguły: Spot wykonuje wspólna akcja HTTP; automatyczne dopasowanie limitów i publikacja globalnej ceny tylko w fazie rynku sweepera. Dostępny wolumen odejmuje rezerwacje limit/B2B. Slippage liczy sumę wolumenu z 60 min, a wydarzenie modyfikuje cenę dopiero od logical_at.
UI/GM: Ceny i wydarzenia w Filament; GM aktywuje zdarzenie przez zatwierdzoną politykę balansu. Web Vue3 i aplikacja mobile korzystają z tej samej Action; PL/EN/DE, lokalne SVG, dostępny modal zamiast okna systemowego.
Odbiór pakietu: każda karta ma test pozytywny, odmowy, obcej firmy, retry, równoległości MySQL i awarii workera. Parametry tylko z zatwierdzonej balance_version; propozycje DOM nie aktywują funkcji.


### P14-F01 — QuoteSale
- **Aktor i kontrakt:** aktor firmy z capability `market.trade`. company, source_storage, bbl, expected_state_version → cena/fee/ważność quote. `POST /api/v1/market/spot/quotes` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `market_price_snapshots`, `stock_reservations`. odczyt aktualnego zapasu i ceny; bez rezerwacji oraz FTS. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11 → ledger20 → stock70 → market_offers100 → globalna płynność/slippage130 → append140; cenę i matching publikuje tylko sweeper. Odczyt autoryzowany, bez side effectów.
- **Czas i zdarzenie:** Brak księgowania i losowania w GET/quote. Brak zdarzenia domenowego z samego odczytu.
- **Odmowa/awaria:** brak wolumenu/nieaktualna wersja → odmowa, bez ceny klienta. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** granica limit/B2B, równoczesna rezerwacja; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P14-F02 — ExecuteSpot
- **Aktor i kontrakt:** aktor firmy z capability `market.trade`. quote_id, bbl, idempotency_key → trade_id/FTS/ref. `POST /api/v1/market/spot/sales` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `market_trades`, `oil_movements`, `ledger_accounts`. available → sold, ruch bbl raz i wpływ PLN_BANK przez Ledger, zajęcie komornika w tej samej transakcji. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11 → ledger20 → stock70 → market_offers100 → globalna płynność/slippage130 → append140; cenę i matching publikuje tylko sweeper. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `market.execute_spot.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak zapasu, wygasła wycena lub limit awaryjny → rollback całości. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** 2 sprzedaże tej samej baryłki, retry, egzekucja; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P14-F03 — PlaceLimitOrder
- **Aktor i kontrakt:** aktor firmy z capability `market.trade`. storage, bbl, limit_price_minor, expected_version → offer_id. `POST /api/v1/market/limit-orders` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `market_offers`, `stock_reservations`. available → reserved_bbl, published; cena zmieniana tylko wersjonowaną akcją. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11 → ledger20 → stock70 → market_offers100 → globalna płynność/slippage130 → append140; cenę i matching publikuje tylko sweeper. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `market.place_limit_order.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak zapasu/limit niespełniony → brak oferty. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** spot kontra limit, anulowanie i zwrot rezerwacji; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P14-F04 — MatchOrders
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. due_slot, price_snapshot → list trade_id. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `market_offers`, `market_trades`, `market_price_snapshots`. published → filled/partial tylko przez fazę globalnego matchingu; stabilny porządek ofert i effect_key. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11 → ledger20 → stock70 → market_offers100 → globalna płynność/slippage130 → append140; cenę i matching publikuje tylko sweeper. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `market.match_orders.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** po nieudanym workerze replay bez drugiej sprzedaży. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** 2 workery, kryzys cenowy, partial; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P14-F05 — TradeBlackMarket
- **Aktor i kontrakt:** aktor firmy z capability `market.trade`. storage, bbl, quote_id → trade_id/cash_minor. `POST /api/v1/market/black-market/trades` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `black_market_trades`, `company_reputation_state`. available → sold; PLN_CASH, black_market_score +2; limit 5 transakcji i 1500 bbl/dobę UTC. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11 → ledger20 → stock70 → market_offers100 → globalna płynność/slippage130 → append140; cenę i matching publikuje tylko sweeper. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `market.trade_black_market.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** przekroczenie limitu, brak zapasu lub kontrola → jawny wynik. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** 2 równoległe transakcje na progu, score decay; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.



## P15. Contracts
Źródło: §13, §21, §23.6. Właściciel zapisu: **Contracts**; korzeń balansu: `market`; poprzedniki: P10/P12/P14.
Dane: system_contract_offers, b2b_offers, contracts, contract_tranches, escrow_accounts, escrow_movements, contract_disputes, delivery_receipts. Właściciele cudzych tabel są odpytywani przez publiczny kontrakt. Mutowalne ID wewnętrzne BIGINT, publiczne ULID; kwoty BIGINT grosze/API string, ropa DECIMAL(16,4), czas UTC DATETIME(3).
LockPlan: firmy10 (obie rosnąco) → due12 → Staff14/Legal15 jeśli bramka → ledger20/21 → stock70/trips80 → escrow90 → contracts110 → append140. Zasoby obu firm deklaruje się przed pierwszym lockiem; żaden handler nie wraca do wcześniejszej rangi.
Reguły: Własność ropy przechodzi dopiero na delivery_receipt. Escrow max 168 h od accepted, receipt ma pierwszeństwo przed refundem. Jedna transza ma jedno settlement; GM po limicie rozlicza nagrodę poza escrow.
UI/GM: Filament: spory, dowody, timeout i ręczna decyzja z audytem; bez cichej edycji historii. Web Vue3 i aplikacja mobile korzystają z tej samej Action; PL/EN/DE, lokalne SVG, dostępny modal zamiast okna systemowego.
Odbiór pakietu: każda karta ma test pozytywny, odmowy, obcej firmy, retry, równoległości MySQL i awarii workera. Parametry tylko z zatwierdzonej balance_version; propozycje DOM nie aktywują funkcji.


### P15-F01 — GenerateNpcOffers
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. region_id, due_slot, balance_version → oferta NPC. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `system_contract_offers`. HMAC seed; pula 3+floor(gracze/25), max8/region, odstęp 24h; oferta ważna 24–72h. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firmy10 (obie rosnąco) → due12 → Staff14/Legal15 jeśli bramka → ledger20/21 → stock70/trips80 → escrow90 → contracts110 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `contracts.generate_npc_offers.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak miejsca w puli → no-op. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** retry i dwa sweepy nie dublują oferty; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P15-F02 — AcceptContract
- **Aktor i kontrakt:** aktor firmy z capability `contracts.manage`. offer_id, company_id, quote_version → contract/tranche plan. `POST /api/v1/contracts/offers/{id}/accept` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `contracts`, `contract_tranches`, `escrow_accounts`. open → accepted; kaucja NPC 10% lub premium 5%; B2B osobny depozyt/zakres. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firmy10 (obie rosnąco) → due12 → Staff14/Legal15 jeśli bramka → ledger20/21 → stock70/trips80 → escrow90 → contracts110 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `contracts.accept_contract.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** wygasła oferta, reputacja lub brak środków → bez kontraktu. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** limit tier, kaucja raz, kontrakt po retry; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P15-F03 — FundEscrow
- **Aktor i kontrakt:** aktor firmy z capability `contracts.manage`. b2b_offer_id, buyer, expected_version → escrow_id. `POST /api/v1/contracts/b2b/offers/{id}/accept` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `b2b_offers`, `escrow_accounts`, `stock_reservations`. published → accepted; rezerwacja 100% wartości i ropy sprzedawcy w jednej transakcji. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firmy10 (obie rosnąco) → due12 → Staff14/Legal15 jeśli bramka → ledger20/21 → stock70/trips80 → escrow90 → contracts110 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `contracts.fund_escrow.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak środków albo wolumenu → rollback obu stron. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** dwie akceptacje, cross-company access, saldo; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P15-F04 — SettleReceipt
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. tranche_id, receipt_id, logical_at → settlement_id. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `delivery_receipts`, `contract_tranches`, `escrow_accounts`, `escrow_movements`. in_transit/waiting_bay → delivered/settled; ruch bbl, prowizja 1,5%, wypłata na PLN_BANK, garnishment raz. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firmy10 (obie rosnąco) → due12 → Staff14/Legal15 jeśli bramka → ledger20/21 → stock70/trips80 → escrow90 → contracts110 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `contracts.settle_receipt.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** receipt po refund → rozstrzygnięcie według logic_at i stanu, bez podwójnej wypłaty. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** receipt kontra timeout/refund i 168h; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P15-F05 — ResolveDispute
- **Aktor i kontrakt:** Filament Action z jawną capability GM. dispute_id, dowody, decision, expected_version → resolved outcome. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `contract_disputes`, `escrow_accounts`, `escrow_movements`. disputed → resolved_settled/split/refunded; max168h; późny GM award poza escrow. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firmy10 (obie rosnąco) → due12 → Staff14/Legal15 jeśli bramka → ledger20/21 → stock70/trips80 → escrow90 → contracts110 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `contracts.resolve_dispute.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak uprawnienia/konflikt dowodów → jawne wstrzymanie do twardego limitu. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** freeze 72h, cap 168h, replays; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.



## P16. Finance
Źródło: §10, §19.1, §24, §22.6.1. Właściciel zapisu: **Finance**; korzeń balansu: `economy`; poprzedniki: P03/P06/P08/P11/P12/P15.
Dane: department_budgets, player_finance_decisions, asset_valuations, net_worth_snapshots, wealth_tax_assessments. Właściciele cudzych tabel są odpytywani przez publiczny kontrakt. Mutowalne ID wewnętrzne BIGINT, publiczne ULID; kwoty BIGINT grosze/API string, ropa DECIMAL(16,4), czas UTC DATETIME(3).
LockPlan: firma10 → settlement11 → Staff14/Legal15 według odczytu snapshot → ledger20 → aktywa40–120 → append140. Zasoby obu firm deklaruje się przed pierwszym lockiem; żaden handler nie wraca do wcześniejszej rangi.
Reguły: Finance liczy kwoty i zleca Ledger, nie zapisuje FTS samodzielnie. Podatek majątkowy jest marginalny; tarcza/Tier używa pełnej wyceny, podatek nocnego snapshotu. Ropa w tranzycie liczona raz. Rafineria ma dyskonto już we własnej wycenie.
UI/GM: Filament: polityki budżetów/podatku i podgląd wyceny z uzasadnieniem składników; aktywacja balance_version przez bramkę. Web Vue3 i aplikacja mobile korzystają z tej samej Action; PL/EN/DE, lokalne SVG, dostępny modal zamiast okna systemowego.
Odbiór pakietu: każda karta ma test pozytywny, odmowy, obcej firmy, retry, równoległości MySQL i awarii workera. Parametry tylko z zatwierdzonej balance_version; propozycje DOM nie aktywują funkcji.


### P16-F01 — ChangeBudget
- **Aktor i kontrakt:** aktor firmy z capability `finance.manage`. department, low|standard|high, expected_version → effective_at. `PUT /api/v1/finance/budgets/{department}` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `department_budgets`, `player_finance_decisions`. 24h cooldown; obniżka emituje morale −6 raz, koszt od kolejnego segmentu czasu. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11 → Staff14/Legal15 według odczytu snapshot → ledger20 → aktywa40–120 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `finance.change_budget.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak dyrektora finansów poza jawnie dozwoloną ścieżką emergency → odmowa. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** zmiana na granicy 24h, strajk, retry; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P16-F02 — AccrueCosts
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. company, from,to, balance_version → obligations/FTS refs. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `department_budgets`, `obligations`. segmentowe OPEX/budżety/HR; subgroszowa reszta przenoszona; niedobór tworzy zobowiązanie 72h. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11 → Staff14/Legal15 według odczytu snapshot → ledger20 → aktywa40–120 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `finance.accrue_costs.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** pauza/vacation/dormant zgodnie z §19.2, bez ujemnego konta. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** długi Δt, zmiana stawki, dwa workery; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P16-F03 — ValueAsset
- **Aktor i kontrakt:** aktor firmy z capability `finance.manage`. asset_ref, as_of, valuation_purpose → breakdown_minor. `GET /api/v1/finance/assets/{id}/valuation` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `asset_valuations`. publiczne snapshoty właścicieli; CAPEX razy stan, depleted ×0,10; refinery według §31.7 bez drugiego ×0,70. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11 → Staff14/Legal15 według odczytu snapshot → ledger20 → aktywa40–120 → append140. Odczyt autoryzowany, bez side effectów.
- **Czas i zdarzenie:** Brak księgowania i losowania w GET/quote. Brak zdarzenia domenowego z samego odczytu.
- **Odmowa/awaria:** brak wycenialnego aktywa → typed unavailable, bez zera z domysłu. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** przeniesienie aktywa, brak podwójnego pozwolenia; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P16-F04 — SnapshotTaxBase
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. company, date_utc → immutable tax_base. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `net_worth_snapshots`. 00:00 UTC; kwoty rzeczowe i wierzytelności raz; zawiera aktywa i zapasy według OWNERSHIP. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11 → Staff14/Legal15 według odczytu snapshot → ledger20 → aktywa40–120 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `finance.snapshot_tax_base.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** ponowny snapshot zwraca ten sam hash, korekta append-only. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** granica północy, ropa w trasie, escrow; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P16-F05 — AssessWealthTax
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. snapshot_id, policy_version → assessment_minor. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `wealth_tax_assessments`. marginalnie od nadwyżki, post Ledger raz; tarcza/tier nie używa dyskonta podatkowego. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11 → Staff14/Legal15 według odczytu snapshot → ledger20 → aktywa40–120 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `finance.assess_wealth_tax.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** niezatwierdzona polityka → POLICY_NOT_APPROVED. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** 200M/400M/1,2B i retry; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.



## P17. Banking
Źródło: §17.1–17.3, §21. Właściciel zapisu: **Banking**; korzeń balansu: `banking`; poprzedniki: P15/P16.
Dane: loans, loan_installments, obligations, bank_trust_scores, credit_score_history, bailiff_proceedings, auctions, recovery_plans. Właściciele cudzych tabel są odpytywani przez publiczny kontrakt. Mutowalne ID wewnętrzne BIGINT, publiczne ULID; kwoty BIGINT grosze/API string, ropa DECIMAL(16,4), czas UTC DATETIME(3).
LockPlan: firma10 → due12 → ledger20 → obligations/loans30 → aktywa40–120 rosnąco według rejestru → append140. Zasoby obu firm deklaruje się przed pierwszym lockiem; żaden handler nie wraca do wcześniejszej rangi.
Reguły: Scoring 0–115 z kanonicznych składników. Rata co 24h; late 72h → Faza1, po7d bez planu Faza2, potem Faza3 aktywów. Escrow i B2B cargo w tranzycie wyłączone z zajęcia. Zero-wipe zachowuje historię.
UI/GM: Filament: scoring, zobowiązania, plany naprawcze, aukcje i ślad decyzji GM. Web Vue3 i aplikacja mobile korzystają z tej samej Action; PL/EN/DE, lokalne SVG, dostępny modal zamiast okna systemowego.
Odbiór pakietu: każda karta ma test pozytywny, odmowy, obcej firmy, retry, równoległości MySQL i awarii workera. Parametry tylko z zatwierdzonej balance_version; propozycje DOM nie aktywują funkcji.


### P17-F01 — EvaluateCreditScore
- **Aktor i kontrakt:** aktor firmy z capability `banking.manage`. company, as_of → score/tier/breakdown. `GET /api/v1/banking/credit-score` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `bank_trust_scores`, `credit_score_history`. credibility35%, history35%, solvency20%, tenure10%, bonus clean cap15; cap bankructwo/late/egzekucja. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → due12 → ledger20 → obligations/loans30 → aktywa40–120 rosnąco według rejestru → append140. Odczyt autoryzowany, bez side effectów.
- **Czas i zdarzenie:** Brak księgowania i losowania w GET/quote. Brak zdarzenia domenowego z samego odczytu.
- **Odmowa/awaria:** brak historii =40, bez arbitralnego neutral=100. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** start48,5, bonus i aktywne late; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P17-F02 — ApplyLoan
- **Aktor i kontrakt:** aktor firmy z capability `banking.manage`. company, amount_minor, term_days, expected_version → loan/payment plan. `POST /api/v1/banking/loans` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `loans`, `loan_installments`. eligible → approved/funded; tier cap i stawka z YAML, BoardAccess finance; Ledger raz. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → due12 → ledger20 → obligations/loans30 → aktywa40–120 rosnąco według rejestru → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `banking.apply_loan.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak zdolności, wakat dyrektora lub dług → odmowa. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** dwa wnioski pod limitem, retry funding; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P17-F03 — PayInstallment
- **Aktor i kontrakt:** aktor firmy z capability `banking.manage`. loan_id, due_slot, amount_minor → paid/late. `POST /api/v1/banking/loans/{id}/payments` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `loan_installments`, `loans`. PLN_BANK → wierzyciel raz; late nalicza +150% stopy bazowej i trust −5/tydzień. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → due12 → ledger20 → obligations/loans30 → aktywa40–120 rosnąco według rejestru → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `banking.pay_installment.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak środków → late bez ujemnego salda. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** deadline kontra wpłata, FTS raz; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P17-F04 — StartEnforcement
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. obligation, logical_at → proceeding/phase. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `bailiff_proceedings`, `obligations`. late72h→Faza1; wpływy 50%; po7d bez planu Faza2, potem Faza3 zgodnie z kanonem. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → due12 → ledger20 → obligations/loans30 → aktywa40–120 rosnąco według rejestru → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `banking.start_enforcement.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** escrow i zarezerwowana ropa wyłączone; brak długu → no-op. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** receipt kontra zajęcie, timeout i replay; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P17-F05 — AuctionAsset
- **Aktor i kontrakt:** uprawniony licytant. proceeding, asset_ref, bid → award/transfer. `POST /api/v1/banking/auctions/{id}/bids` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `auctions`. seized → auctioned/transferred; wells/refinery wg kolejności §17.2; procesy i historia zachowane. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → due12 → ledger20 → obligations/loans30 → aktywa40–120 rosnąco według rejestru → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `banking.auction_asset.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** niewłaściwy właściciel lub brak prawa kupującego → odmowa. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** aukcja kontra dostawa/strajk/EPC; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.



## P18. Legal PvP
Źródło: §7, §21, §30. Właściciel zapisu: **Legal**; korzeń balansu: `legal`; poprzedniki: P10/P13/P15/P17.
Dane: sabotage_operations, protection_policies, protection_activations, bribe_attempts, abuse_reports, victim_attack_windows. Właściciele cudzych tabel są odpytywani przez publiczny kontrakt. Mutowalne ID wewnętrzne BIGINT, publiczne ULID; kwoty BIGINT grosze/API string, ropa DECIMAL(16,4), czas UTC DATETIME(3).
LockPlan: obie firmy10 rosnąco → due12 → Staff14/Legal15 → ledger20 → assets40–80 → append140. Zasoby obu firm deklaruje się przed pierwszym lockiem; żaden handler nie wraca do wcześniejszej rangi.
Reguły: Tarcza i urlop ofiary blokują trafienie według logical_at. Max5 skutecznych ataków/ofiarę/7dni; cooldown A i para A→B. Koszt sabotażu i RNG trwałe; refund 100% przy tarczy/urlopie, 50% przy utracie aktywa.
UI/GM: Filament: nadzór sabotaży, raporty, limit ofiary, chronione dane i audyt decyzji. Web Vue3 i aplikacja mobile korzystają z tej samej Action; PL/EN/DE, lokalne SVG, dostępny modal zamiast okna systemowego.
Odbiór pakietu: każda karta ma test pozytywny, odmowy, obcej firmy, retry, równoległości MySQL i awarii workera. Parametry tylko z zatwierdzonej balance_version; propozycje DOM nie aktywują funkcji.


### P18-F01 — PlanSabotage
- **Aktor i kontrakt:** aktor firmy z capability `legal.pvp.manage`. attacker, victim, target_ref, operation_code, expected_version → op_id/due_at. `POST /api/v1/legal/sabotage-operations` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `sabotage_operations`, `victim_attack_windows`. planned opłaca koszt PLN_CASH raz; zapisuje seed i termin; sprawdza cooldown. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** obie firmy10 rosnąco → due12 → Staff14/Legal15 → ledger20 → assets40–80 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `legal.plan_sabotage.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** własna firma, osłona celu, limit ofiary → odmowa/refund wg fazy. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** dwa ataki na granicy5/7dni; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P18-F02 — ResolveSabotage
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. op_id, due_slot → succeeded/failed/cancelled. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `sabotage_operations`, `victim_attack_windows`. ponownie sprawdza target/tarcza, trwały RNG; sukces stosuje limit szkody, wpadka −15 cred+100k. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** obie firmy10 rosnąco → due12 → Staff14/Legal15 → ledger20 → assets40–80 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `legal.resolve_sabotage.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** replay job/sweeper nie losuje ani nie obciąża ponownie. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** sprzedaż celu, urlop i ochrona tuż przed due; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P18-F03 — BuyProtection
- **Aktor i kontrakt:** aktor firmy z capability `legal.pvp.manage`. module, asset_ref, quoted_cost_minor → policy/effective_at. `POST /api/v1/legal/protections` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `protection_policies`, `protection_activations`. basic/armed jeden kurs; drony/hub 7dni od następnego sweepa; Ledger i wersja polityki raz. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** obie firmy10 rosnąco → due12 → Staff14/Legal15 → ledger20 → assets40–80 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `legal.buy_protection.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** niewystarczające środki albo brak dyrektora dla konwoju → odmowa. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** ochrona kupiona po starcie ataku nie działa wstecz; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P18-F04 — InvestigateAbuse
- **Aktor i kontrakt:** Filament Action z jawną capability GM. report_id, evidence_refs, GM decision → audited outcome. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `abuse_reports`. raport → reviewing/resolved; decyzja zapisuje audit i ewentualną sankcję przez ownera. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** obie firmy10 rosnąco → due12 → Staff14/Legal15 → ledger20 → assets40–80 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, FTS/ruch fizyczny przez właściciela i outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. HTTP, job i sweeper wywołują tę samą Action. Outbox `legal.investigate_abuse.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** bez audytu/bez capability → odmowa. Brak środków, konflikt wersji i przekroczenie limitu nie pozostawiają półoperacji; retry czyta trwały wynik.
- **Test odbioru:** cross-company data, spam report, retry GM; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


