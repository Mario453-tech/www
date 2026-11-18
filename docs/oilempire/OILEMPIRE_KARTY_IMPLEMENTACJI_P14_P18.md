# OilEmpire — karty implementacyjne P14–P18

Status: kontrakt dokumentacyjny do programowania po audycie; wartości DOM pozostają do zatwierdzenia. Szczegóły wymagane przez plan §3 są powiązane przez [macierz DoR akcji](OILEMPIRE_KONTRAKTY_AKCJI_P14_P25.md). Czytać z [planem](OILEMPIRE_PLAN_PROGRAMOWANIA.md), [kontraktami maszynowymi](OILEMPIRE_KONTRAKTY_P14_P25.yaml), [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql), [OpenAPI](OILEMPIRE_OPENAPI_P14_P25_V1.yaml) i [rejestrem propozycji DOM](OILEMPIRE_PROPOZYCJE_DOM_P14_P25.md). Zatwierdzone OPEN01–OPEN12 obowiązują; nierozstrzygnięte DOM nie mają wartości produkcyjnej. Każda mutacja stosuje wymagany Settlement i LockPlan §23.5a; Ledger, ruch fizyczny i outbox tylko wtedy, gdy operacja ma taki skutek. GET jest odczytem bez skutków ubocznych. UI Vue3/Flutter/Filament korzystają z tych samych Actions, nie z własnej ekonomii.

## P14. Market
Źródło: §12, §19.3, §25, §23.3. Właściciel zapisu: **Market**; korzeń balansu: `market`; poprzedniki: P03/P04/P12.
Dane: market_offers, price_history, market_trades, black_market_trades, world_events. Właściciele cudzych tabel są odpytywani przez publiczny kontrakt. Mutowalne ID wewnętrzne BIGINT, publiczne ULID; kwoty BIGINT grosze/API string, ropa DECIMAL(16,4), czas UTC DATETIME(3).
LockPlan: firma10 → settlement11 → ledger20 → stock70 → market_offers100 → globalna płynność/slippage130 → append140; cenę i matching publikuje tylko sweeper. Zasoby obu firm deklaruje się przed pierwszym lockiem; żaden handler nie wraca do wcześniejszej rangi.
Reguły: Spot wykonuje wspólna akcja HTTP; automatyczne dopasowanie limitów i publikacja globalnej ceny tylko w fazie rynku sweepera. Dostępny wolumen odejmuje rezerwacje limit/B2B. Slippage liczy sumę wolumenu z 60 min, a wydarzenie modyfikuje cenę dopiero od logical_at.
UI/GM: Ceny i wydarzenia w Filament; GM aktywuje zdarzenie przez zatwierdzoną politykę balansu. Web Vue3 i aplikacja mobile korzystają z tej samej Action; PL/EN/DE, lokalne SVG, dostępny modal zamiast okna systemowego.
Odbiór pakietu: każda karta ma test pozytywny, odmowy, obcej firmy, retry, równoległości MySQL i awarii workera. Parametry tylko z zatwierdzonej balance_version; propozycje DOM nie aktywują funkcji.


### P14-F01 — QuoteSale
- **Aktor i kontrakt:** aktor firmy z capability `market.trade`. company, source_storage, bbl, expected_state_version → cena/fee/ważność quote. `POST /api/v1/market/spot/quotes` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `price_history`, `stock_reservations`. odczyt aktualnego zapasu i ceny; bez rezerwacji oraz FTS. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11 → ledger20 → stock70 → market_offers100 → globalna płynność/slippage130 → append140; cenę i matching publikuje tylko sweeper. Odczyt autoryzowany, bez side effectów.
- **Czas i zdarzenie:** Brak księgowania i losowania w GET/quote. Brak zdarzenia domenowego z samego odczytu.
- **Odmowa/awaria:** brak wolumenu/nieaktualna wersja → odmowa, bez ceny klienta. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** granica limit/B2B, równoczesna rezerwacja; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P14-F02 — ExecuteSpot
- **Aktor i kontrakt:** aktor firmy z capability `market.trade`. quote_id, bbl, idempotency_key → trade_id/FTS/ref. `POST /api/v1/market/spot/sales` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `market_trades`, `oil_movements`, `ledger_accounts`. available → sold, ruch bbl raz i wpływ PLN_BANK przez Ledger, zajęcie komornika w tej samej transakcji. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11 → ledger20 → stock70 → market_offers100 → globalna płynność/slippage130 → append140; cenę i matching publikuje tylko sweeper. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `market.execute_spot.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak zapasu, wygasła wycena lub limit awaryjny → rollback całości. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** 2 sprzedaże tej samej baryłki, retry, egzekucja; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P14-F03 — PlaceLimitOrder
- **Aktor i kontrakt:** aktor firmy z capability `market.trade`. storage, bbl, limit_price_minor, expected_version → offer_id. `POST /api/v1/market/limit-orders` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `market_offers`, `stock_reservations`. available → reserved_bbl, published; cena zmieniana tylko wersjonowaną akcją. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11 → ledger20 → stock70 → market_offers100 → globalna płynność/slippage130 → append140; cenę i matching publikuje tylko sweeper. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `market.place_limit_order.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak zapasu/limit niespełniony → brak oferty. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** spot kontra limit, anulowanie i zwrot rezerwacji; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P14-F04 — MatchOrders
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. due_slot, price_snapshot → list trade_id. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `market_offers`, `market_trades`, `price_history`. published → filled/partial tylko przez fazę globalnego matchingu; stabilny porządek ofert i effect_key. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11 → ledger20 → stock70 → market_offers100 → globalna płynność/slippage130 → append140; cenę i matching publikuje tylko sweeper. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `market.match_orders.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** po nieudanym workerze replay bez drugiej sprzedaży. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** 2 workery, kryzys cenowy, partial; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P14-F05 — TradeBlackMarket
- **Aktor i kontrakt:** aktor firmy z capability `market.trade`. storage, bbl, quote_id → trade_id/cash_minor. `POST /api/v1/market/black-market/trades` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `black_market_trades`, `company_reputation_state`. available → sold; PLN_CASH, black_market_score +2; limit 5 transakcji i 1500 bbl/dobę UTC. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11 → ledger20 → stock70 → market_offers100 → globalna płynność/slippage130 → append140; cenę i matching publikuje tylko sweeper. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `market.trade_black_market.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** przekroczenie limitu, brak zapasu lub kontrola → jawny wynik. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** 2 równoległe transakcje na progu, score decay; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.



## P15. Contracts
Źródło: §13, §21, §23.6. Właściciel zapisu: **Contracts**; korzeń balansu: `market`; poprzedniki: P10/P12/P14.
Dane: system_contract_offers, b2b_offers, contracts, contract_tranches, escrow_accounts, escrow_movements, contract_disputes, contract_receipt_links. Fizyczne delivery_receipts należą do P12 Logistics; Contracts czyta je przez publiczny kontrakt i zapisuje wyłącznie link rozliczenia. Mutowalne ID wewnętrzne BIGINT, publiczne ULID; kwoty BIGINT grosze/API string, ropa DECIMAL(16,4), czas UTC DATETIME(3).
LockPlan: firmy10 (obie rosnąco) → due12 → Staff14/Legal15 jeśli bramka → ledger20/21 → stock70/trips80 → escrow90 → contracts110 → append140. Zasoby obu firm deklaruje się przed pierwszym lockiem; żaden handler nie wraca do wcześniejszej rangi.
Reguły: Własność ropy przechodzi dopiero na delivery_receipt. Escrow max 168 h od accepted, receipt ma pierwszeństwo przed refundem. Jedna transza ma jedno settlement; GM po limicie rozlicza nagrodę poza escrow.
UI/GM: Filament: spory, dowody, timeout i ręczna decyzja z audytem; bez cichej edycji historii. Web Vue3 i aplikacja mobile korzystają z tej samej Action; PL/EN/DE, lokalne SVG, dostępny modal zamiast okna systemowego.
Odbiór pakietu: każda karta ma test pozytywny, odmowy, obcej firmy, retry, równoległości MySQL i awarii workera. Parametry tylko z zatwierdzonej balance_version; propozycje DOM nie aktywują funkcji.


### P15-F01 — GenerateNpcOffers
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. region_id, due_slot, balance_version → oferta NPC. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `system_contract_offers`. HMAC seed; pula 3+floor(gracze/25), max8/region, odstęp 24h; oferta ważna 24–72h. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firmy10 (obie rosnąco) → due12 → Staff14/Legal15 jeśli bramka → ledger20/21 → stock70/trips80 → escrow90 → contracts110 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `contracts.generate_npc_offers.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak miejsca w puli → no-op. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** retry i dwa sweepy nie dublują oferty; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P15-F02 — AcceptContract
- **Aktor i kontrakt:** aktor firmy z capability `contracts.manage`. offer_id, company_id, quote_version → contract/tranche plan. `POST /api/v1/contracts/offers/{id}/accept` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `contracts`, `contract_tranches`, `escrow_accounts`. open → accepted; kaucja NPC 10% lub premium 5%; B2B osobny depozyt/zakres. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firmy10 (obie rosnąco) → due12 → Staff14/Legal15 jeśli bramka → ledger20/21 → stock70/trips80 → escrow90 → contracts110 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `contracts.accept_contract.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** wygasła oferta, reputacja lub brak środków → bez kontraktu. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** limit tier, kaucja raz, kontrakt po retry; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P15-F03 — FundEscrow
- **Aktor i kontrakt:** aktor firmy z capability `contracts.manage`. b2b_offer_id, buyer, expected_version → escrow_id. `POST /api/v1/contracts/b2b/offers/{id}/accept` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `b2b_offers`, `escrow_accounts`, `stock_reservations`. published → accepted; rezerwacja 100% wartości i ropy sprzedawcy w jednej transakcji. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firmy10 (obie rosnąco) → due12 → Staff14/Legal15 jeśli bramka → ledger20/21 → stock70/trips80 → escrow90 → contracts110 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `contracts.fund_escrow.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak środków albo wolumenu → rollback obu stron. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** dwie akceptacje, cross-company access, saldo; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P15-F04 — SettleReceipt
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. tranche_id, receipt_id, logical_at → settlement_id. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `delivery_receipts` [P12 Logistics, read-only], `contract_receipt_links`, `contract_tranches`, `escrow_accounts`, `escrow_movements`. in_transit/waiting_bay → delivered/settled; ruch bbl, prowizja 1,5%, wypłata na PLN_BANK, garnishment raz. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firmy10 (obie rosnąco) → due12 → Staff14/Legal15 jeśli bramka → ledger20/21 → stock70/trips80 → escrow90 → contracts110 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `contracts.settle_receipt.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** receipt po refund → rozstrzygnięcie według logic_at i stanu, bez podwójnej wypłaty. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** receipt kontra timeout/refund i 168h; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P15-F05 — ResolveDispute
- **Aktor i kontrakt:** Filament Action z jawną capability GM. dispute_id, dowody, decision, expected_version → resolved outcome. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `contract_disputes`, `escrow_accounts`, `escrow_movements`. disputed → resolved_settled/split/refunded; max168h; późny GM award poza escrow. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firmy10 (obie rosnąco) → due12 → Staff14/Legal15 jeśli bramka → ledger20/21 → stock70/trips80 → escrow90 → contracts110 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `contracts.resolve_dispute.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak uprawnienia/konflikt dowodów → jawne wstrzymanie do twardego limitu. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** freeze 72h, cap 168h, replays; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.



## P16. Finance
Źródło: §10, §19.1, §24, §22.6.1. Właściciel zapisu: **Finance**; korzeń balansu: `economy`; poprzedniki: P03/P06/P08/P11/P12/P15.
Dane: department_budgets, player_finance_decisions, asset_valuations, net_worth_snapshots, wealth_tax_assessments. Właściciele cudzych tabel są odpytywani przez publiczny kontrakt. Mutowalne ID wewnętrzne BIGINT, publiczne ULID; kwoty BIGINT grosze/API string, ropa DECIMAL(16,4), czas UTC DATETIME(3).
LockPlan: firma10 → settlement11 → Staff14/Legal15 według odczytu snapshot → Finance19 → ledger20 → aktywa40–120 → append140. Zasoby obu firm deklaruje się przed pierwszym lockiem; żaden handler nie wraca do wcześniejszej rangi.
Reguły: Finance liczy kwoty i zleca Ledger, nie zapisuje FTS samodzielnie. Podatek majątkowy jest marginalny; tarcza/Tier używa pełnej wyceny, podatek nocnego snapshotu. Ropa w tranzycie liczona raz. Rafineria ma dyskonto już we własnej wycenie.
UI/GM: Filament: polityki budżetów/podatku i podgląd wyceny z uzasadnieniem składników; aktywacja balance_version przez bramkę. Web Vue3 i aplikacja mobile korzystają z tej samej Action; PL/EN/DE, lokalne SVG, dostępny modal zamiast okna systemowego.
Odbiór pakietu: każda karta ma test pozytywny, odmowy, obcej firmy, retry, równoległości MySQL i awarii workera. Parametry tylko z zatwierdzonej balance_version; propozycje DOM nie aktywują funkcji.


### P16-F01 — ChangeBudget
- **Aktor i kontrakt:** aktor firmy z capability `finance.manage`. department, low|standard|high, expected_version → effective_at. `PUT /api/v1/finance/budgets/{department}` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `department_budgets`, `player_finance_decisions`. 24h cooldown; obniżka emituje morale −6 raz, koszt od kolejnego segmentu czasu. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11 → Staff14/Legal15 według odczytu snapshot → Finance19 → ledger20 → aktywa40–120 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `finance.change_budget.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak dyrektora finansów poza jawnie dozwoloną ścieżką emergency → odmowa. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** zmiana na granicy 24h, strajk, retry; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P16-F02 — AccrueCosts
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. company, from,to, balance_version → obligations/FTS refs. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `department_budgets`, `obligations`. segmentowe OPEX/budżety/HR; subgroszowa reszta przenoszona; niedobór tworzy zobowiązanie 72h. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11 → Staff14/Legal15 według odczytu snapshot → Finance19 → ledger20 → aktywa40–120 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `finance.accrue_costs.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** pauza/vacation/dormant zgodnie z §19.2, bez ujemnego konta. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** długi Δt, zmiana stawki, dwa workery; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P16-F03 — ValueAsset
- **Aktor i kontrakt:** aktor firmy z capability `finance.manage`. asset_ref, as_of, valuation_purpose → breakdown_minor. `GET /api/v1/finance/assets/{id}/valuation` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `asset_valuations`. publiczne snapshoty właścicieli; CAPEX razy stan, depleted ×0,10; refinery według §31.7 bez drugiego ×0,70. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11 → Staff14/Legal15 według odczytu snapshot → Finance19 → ledger20 → aktywa40–120 → append140. Odczyt autoryzowany, bez side effectów.
- **Czas i zdarzenie:** Brak księgowania i losowania w GET/quote. Brak zdarzenia domenowego z samego odczytu.
- **Odmowa/awaria:** brak wycenialnego aktywa → typed unavailable, bez zera z domysłu. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** przeniesienie aktywa, brak podwójnego pozwolenia; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P16-F04 — SnapshotTaxBase
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. company, date_utc → immutable tax_base. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `net_worth_snapshots`. 00:00 UTC; kwoty rzeczowe i wierzytelności raz; zawiera aktywa i zapasy według OWNERSHIP. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11 → Staff14/Legal15 według odczytu snapshot → Finance19 → ledger20 → aktywa40–120 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `finance.snapshot_tax_base.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** ponowny snapshot zwraca ten sam hash, korekta append-only. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** granica północy, ropa w trasie, escrow; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P16-F05 — AssessWealthTax
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. snapshot_id, policy_version → assessment_minor. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `wealth_tax_assessments`. marginalnie od nadwyżki, post Ledger raz; tarcza/tier nie używa dyskonta podatkowego. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → settlement11 → Staff14/Legal15 według odczytu snapshot → Finance19 → ledger20 → aktywa40–120 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `finance.assess_wealth_tax.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** niezatwierdzona polityka → POLICY_NOT_APPROVED. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
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
- **Odmowa/awaria:** brak historii =40, bez arbitralnego neutral=100. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** start48,5, bonus i aktywne late; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P17-F02 — ApplyLoan
- **Aktor i kontrakt:** aktor firmy z capability `banking.manage`. company, amount_minor, term_days, expected_version → loan/payment plan. `POST /api/v1/banking/loans` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `loans`, `loan_installments`. eligible → approved/funded; tier cap i stawka z YAML, BoardAccess finance; Ledger raz. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → due12 → ledger20 → obligations/loans30 → aktywa40–120 rosnąco według rejestru → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `banking.apply_loan.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak zdolności, wakat dyrektora lub dług → odmowa. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** dwa wnioski pod limitem, retry funding; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P17-F03 — PayInstallment
- **Aktor i kontrakt:** aktor firmy z capability `banking.manage`. loan_id, due_slot, amount_minor → paid/late. `POST /api/v1/banking/loans/{id}/payments` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `loan_installments`, `loans`. PLN_BANK → wierzyciel raz; late nalicza +150% stopy bazowej i trust −5/tydzień. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → due12 → ledger20 → obligations/loans30 → aktywa40–120 rosnąco według rejestru → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `banking.pay_installment.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** brak środków → late bez ujemnego salda. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** deadline kontra wpłata, FTS raz; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P17-F04 — StartEnforcement
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. obligation, logical_at → proceeding/phase. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `bailiff_proceedings`, `obligations`. late72h→Faza1; wpływy 50%; po7d bez planu Faza2, potem Faza3 zgodnie z kanonem. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → due12 → ledger20 → obligations/loans30 → aktywa40–120 rosnąco według rejestru → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `banking.start_enforcement.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** escrow i zarezerwowana ropa wyłączone; brak długu → no-op. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** receipt kontra zajęcie, timeout i replay; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P17-F05 — AuctionAsset
- **Aktor i kontrakt:** uprawniony licytant. proceeding, asset_ref, bid → award/transfer. `POST /api/v1/banking/auctions/{id}/bids` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `auctions`. seized → auctioned/transferred; wells/refinery wg kolejności §17.2; procesy i historia zachowane. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** firma10 → due12 → ledger20 → obligations/loans30 → aktywa40–120 rosnąco według rejestru → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `banking.auction_asset.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** niewłaściwy właściciel lub brak prawa kupującego → odmowa. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
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
- **Transakcja:** obie firmy10 rosnąco → due12 → Staff14/Legal15 → ledger20 → assets40–80 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `legal.plan_sabotage.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** własna firma, osłona celu, limit ofiary → odmowa/refund wg fazy. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** dwa ataki na granicy5/7dni; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P18-F02 — ResolveSabotage
- **Aktor i kontrakt:** wewnętrzny handler/worker z trwałym due_slot. op_id, due_slot → succeeded/failed/cancelled. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `sabotage_operations`, `victim_attack_windows`. ponownie sprawdza target/tarcza, trwały RNG; sukces stosuje limit szkody, wpadka −15 cred+100k. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** obie firmy10 rosnąco → due12 → Staff14/Legal15 → ledger20 → assets40–80 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `legal.resolve_sabotage.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** replay job/sweeper nie losuje ani nie obciąża ponownie. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** sprzedaż celu, urlop i ochrona tuż przed due; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P18-F03 — BuyProtection
- **Aktor i kontrakt:** aktor firmy z capability `legal.pvp.manage`. module, asset_ref, quoted_cost_minor → policy/effective_at. `POST /api/v1/legal/protections` — request/response w OpenAPI; ten sam kontrakt Action dla web/mobile.
- **Dane i przejście:** `protection_policies`, `protection_activations`. basic/armed jeden kurs; drony/hub 7dni od następnego sweepa; Ledger i wersja polityki raz. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** obie firmy10 rosnąco → due12 → Staff14/Legal15 → ledger20 → assets40–80 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `legal.buy_protection.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** niewystarczające środki albo brak dyrektora dla konwoju → odmowa. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** ochrona kupiona po starcie ataku nie działa wstecz; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.


### P18-F04 — InvestigateAbuse
- **Aktor i kontrakt:** Filament Action z jawną capability GM. report_id, evidence_refs, GM decision → audited outcome. Bez publicznej trasy; kontrakt Action/DTO i wyzwalacz w kontrakcie maszynowym.
- **Dane i przejście:** `abuse_reports`. raport → reviewing/resolved; decyzja zapisuje audit i ewentualną sankcję przez ownera. Wymagane FK, indeksy i UNIQUE definiuje projekt DDL; bez bezpośredniego zapisu prywatnej tabeli innego modułu.
- **Transakcja:** obie firmy10 rosnąco → due12 → Staff14/Legal15 → ledger20 → assets40–80 → append140. Idempotency-Key aktora lub stabilny typ:encja:slot dla domeny; expected_state_version/CAS, skutki finansowe/fizyczne przez publiczne Actions ich właścicieli, jeśli występują, oraz wymagany outbox w jednej transakcji.
- **Czas i zdarzenie:** Clock UTC; proces utrwala logical_at, due_slot (gdy terminowy), balance_version oraz seed/wynik RNG jeśli używa losowania. Właściwy wyzwalacz wywołuje tę samą Action; HTTP, job i sweeper nie mają osobnych reguł. Outbox `legal.investigate_abuse.v1` z ID, company_id, logical_at i wersją; bez sekretów oraz prywatnych modeli.
- **Odmowa/awaria:** bez audytu/bez capability → odmowa. Każda odmowa domenowa i konflikt wersji wycofują całą transakcję; retry czyta trwały wynik.
- **Test odbioru:** cross-company data, spam report, retry GM; dodatkowo własność, 401/403/404/409/422/429 gdzie dotyczy, outbox raz, rollback oraz brak wycieku danych.



## Uzupełnione akcje i zapytania po audycie kompletności

Poniższe ID należą do odpowiednich pakietów i korzystają z tych samych kontraktów wersji, LockPlan, DDL i polityk. Pełna macierz DoR dla wszystkich akcji jest w [kontrakcie szczegółowym](OILEMPIRE_KONTRAKTY_AKCJI_P14_P25.md).

### P14-F06 — ListMarketOffers
- **Kanon i aktor:** pakiet P14; uwierzytelniony aktor z polityką zasobu. Właściciel tabel: Market.
- **Interface / DTO:** region, grade, cursor, limit → paged offers/price. GET /api/v1/market/offers OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** market_offers(storage_id,side,quantity_bbl,remaining_bbl,limit_price_minor,expires_at); price_history(region_code,price_minor,volume_60m_bbl,event_multiplier_ppm,slot_at). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Bez zmiany | autoryzacja/widoczność; foreign offer private fields hidden; stale cursor→409 | read-only snapshot, available volume excludes reservations | Brak skutku ubocznego |

- **Transakcja:** brak blokad zapisu, idempotencji mutacyjnej i outbox.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; ETag/as_of i polityka widoczności.
- **Zdarzenie:** brak.
- **UI/Filament:** Rynek; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** pagination, own/foreign, concurrent match; foreign offer private fields hidden; stale cursor→409; test własności i odmowy, paginacji/ETag, brak zapisu, licznik market.listmarketoffers.success/conflict/error bez PII.
- **Migracja/rollback:** odczyt istniejących tabel; indeksy i migracje właściciela wskazuje DDL, bez własnego backfill. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P15-F06 — ListContracts
- **Kanon i aktor:** pakiet P15; uwierzytelniony aktor z polityką zasobu. Właściciel tabel: Contracts.
- **Interface / DTO:** status, cursor, limit → own contracts and next due. GET /api/v1/contracts OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** contracts(buyer_company_id,seller_company_id,system_offer_id,b2b_offer_id,kind,quantity_bbl,…); contract_tranches(contract_id,sequence_no,quantity_bbl,due_at,received_bbl); escrow_accounts(contract_id,b2b_offer_id,held_minor,released_minor,refunded_minor,expires_at). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Bez zmiany | autoryzacja/widoczność; foreign contract→404; escrow details masked | read-only owner-filtered projection after Settlement | Brak skutku ubocznego |

- **Transakcja:** brak blokad zapisu, idempotencji mutacyjnej i outbox.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; ETag/as_of i polityka widoczności.
- **Zdarzenie:** brak.
- **UI/Filament:** Kontrakty; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** buyer/seller visibility, pagination, due boundary; foreign contract→404; escrow details masked; test własności i odmowy, paginacji/ETag, brak zapisu, licznik contracts.listcontracts.success/conflict/error bez PII.
- **Migracja/rollback:** odczyt istniejących tabel; indeksy i migracje właściciela wskazuje DDL, bez własnego backfill. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P16-F06 — GetFinanceSummary
- **Kanon i aktor:** pakiet P16; uwierzytelniony aktor z polityką zasobu. Właściciel tabel: Finance.
- **Interface / DTO:** company → cash/assets/liabilities/tax snapshot. GET /api/v1/finance/summary OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** net_worth_snapshots(cash_minor,asset_minor,liability_minor,taxable_minor); wealth_tax_assessments(snapshot_id,tax_minor,ledger_reference); asset_valuations(asset_type,asset_public_ulid,gross_minor,taxable_minor,formula_version,valued_at). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Bez zmiany | autoryzacja/widoczność; foreign company→404 | read-only as-of snapshot with current shield/Tier valuation | Brak skutku ubocznego |

- **Transakcja:** brak blokad zapisu, idempotencji mutacyjnej i outbox.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; ETag/as_of i polityka widoczności.
- **Zdarzenie:** brak.
- **UI/Filament:** Finanse; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** midnight, refinery discount once, transit oil once; foreign company→404; test własności i odmowy, paginacji/ETag, brak zapisu, licznik finance.getfinancesummary.success/conflict/error bez PII.
- **Migracja/rollback:** odczyt istniejących tabel; indeksy i migracje właściciela wskazuje DDL, bez własnego backfill. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P17-F06 — RestructureLoan
- **Kanon i aktor:** pakiet P17; aktor zasobu z polityką owner/capability. Właściciel tabel: Banking.
- **Interface / DTO:** loan, proposed schedule, expected_version → decision. POST /api/v1/banking/loans/{id}/restructuring OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** loans(principal_minor,outstanding_minor,apr_basis_points,term_days,accepted_at); loan_installments(loan_id,sequence_no,due_at,principal_minor,interest_minor,paid_minor). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Aktualny stan | autoryzacja, wersja, policy gate i ineligible phase/insufficient trust→409 | eligible→restructured with new immutable installment schedule | rollback przed commit; po skutku zewnętrznym oddzielna idempotentna korekta |

- **Transakcja:** company10→due12→Ledger20→Banking30→asset40–120→append140; Idempotency-Key lub stabilny klucz domenowy + CAS; effect key i outbox w tej samej transakcji.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; due_slot dla terminów, fencing dla workera, seed+wynik RNG tylko gdy losowanie; deadlock wycofuje całość i ponawia ten sam efekt.
- **Zdarzenie:** banking.restructure_loan.v1; payload: event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorca przez rejestr subskrypcji; retry z event_id, po limicie DLQ i alert.
- **UI/Filament:** Bank i egzekucja; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** two concurrent requests, sum principal/interest; ineligible phase/insufficient trust→409; test własności i odmowy, MySQL dwie sesje dla mutacji, retry po commit, licznik banking.restructureloan.success/conflict/error bez PII.
- **Migracja/rollback:** expand tworzy własne tabele/indeksy; greenfield bez backfill, import legacy tylko z osobnym mapowaniem. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P17-F07 — DeclareBankruptcy
- **Kanon i aktor:** pakiet P17; aktor zasobu z polityką owner/capability. Właściciel tabel: Banking.
- **Interface / DTO:** company, expected_version → proceeding/state. POST /api/v1/banking/bankruptcy OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** bailiff_proceedings(obligation_public_ulid,phase,started_at,next_due_at,recovered_minor); loans(principal_minor,outstanding_minor,apr_basis_points,term_days,accepted_at); auctions(proceeding_id,asset_type,asset_public_ulid,reserve_minor,winning_bid_minor,closes_at). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Aktualny stan | autoryzacja, wersja, policy gate i escrow/cargo not seized; active dispute→review | eligible→bankruptcy; Core company status via public Action | rollback przed commit; po skutku zewnętrznym oddzielna idempotentna korekta |

- **Transakcja:** company10→due12→Ledger20→Banking30→asset40–120→append140; Idempotency-Key lub stabilny klucz domenowy + CAS; effect key i outbox w tej samej transakcji.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; due_slot dla terminów, fencing dla workera, seed+wynik RNG tylko gdy losowanie; deadlock wycofuje całość i ponawia ten sam efekt.
- **Zdarzenie:** banking.declare_bankruptcy.v1; payload: event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorca przez rejestr subskrypcji; retry z event_id, po limicie DLQ i alert.
- **UI/Filament:** Bank i egzekucja; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** parallel transfer, asset snapshots, no history deletion; escrow/cargo not seized; active dispute→review; test własności i odmowy, MySQL dwie sesje dla mutacji, retry po commit, licznik banking.declarebankruptcy.success/conflict/error bez PII.
- **Migracja/rollback:** expand tworzy własne tabele/indeksy; greenfield bez backfill, import legacy tylko z osobnym mapowaniem. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P17-F08 — ListLoans
- **Kanon i aktor:** pakiet P17; uwierzytelniony aktor z polityką zasobu. Właściciel tabel: Banking.
- **Interface / DTO:** cursor, status → own loans/installments. GET /api/v1/banking/loans OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** loans(principal_minor,outstanding_minor,apr_basis_points,term_days,accepted_at); loan_installments(loan_id,sequence_no,due_at,principal_minor,interest_minor,paid_minor). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Bez zmiany | autoryzacja/widoczność; foreign loan→404 | read-only owner-filtered projection | Brak skutku ubocznego |

- **Transakcja:** brak blokad zapisu, idempotencji mutacyjnej i outbox.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; ETag/as_of i polityka widoczności.
- **Zdarzenie:** brak.
- **UI/Filament:** Bank i egzekucja; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** paging and overdue as-of; foreign loan→404; test własności i odmowy, paginacji/ETag, brak zapisu, licznik banking.listloans.success/conflict/error bez PII.
- **Migracja/rollback:** odczyt istniejących tabel; indeksy i migracje właściciela wskazuje DDL, bez własnego backfill. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P18-F05 — AttemptBribe
- **Kanon i aktor:** pakiet P18; aktor zasobu z polityką owner/capability. Właściciel tabel: Legal.
- **Interface / DTO:** target_case, amount_minor, expected_version → attempt/due. POST /api/v1/legal/bribe-attempts OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** bribe_attempts(target_case_public_ulid,amount_minor,resolve_at,rng_seed,result_code). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Aktualny stan | autoryzacja, wersja, policy gate i shield/forbidden target→409 | requested→resolved once using persisted seed | rollback przed commit; po skutku zewnętrznym oddzielna idempotentna korekta |

- **Transakcja:** companies10→due12→Legal15→Ledger20 gdy koszt→assets40–80→append140; Idempotency-Key lub stabilny klucz domenowy + CAS; effect key i outbox w tej samej transakcji.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; due_slot dla terminów, fencing dla workera, seed+wynik RNG tylko gdy losowanie; deadlock wycofuje całość i ponawia ten sam efekt.
- **Zdarzenie:** legal.attempt_bribe.v1; payload: event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorca przez rejestr subskrypcji; retry z event_id, po limicie DLQ i alert.
- **UI/Filament:** Prawo i ochrona; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** once-only cost, bribery detection, concurrent case; shield/forbidden target→409; test własności i odmowy, MySQL dwie sesje dla mutacji, retry po commit, licznik legal.attemptbribe.success/conflict/error bez PII.
- **Migracja/rollback:** expand tworzy własne tabele/indeksy; greenfield bez backfill, import legacy tylko z osobnym mapowaniem. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P18-F06 — GetProtectionStatus
- **Kanon i aktor:** pakiet P18; uwierzytelniony aktor z polityką zasobu. Właściciel tabel: Legal.
- **Interface / DTO:** asset, cursor → own protection/status. GET /api/v1/legal/protections OpenAPI/manifest definiują wymagane pola, błędy i wersje.
- **Dane:** protection_policies(target_type,target_public_ulid,tier_code,premium_minor,starts_at,ends_at); protection_activations(policy_id,attack_id,prevented,resolved_at). Typy, nullability, FK, UNIQUE i indeksy: [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql); obce zasoby tylko przez kontrakt właściciela.
- **Tabela przejścia:**

  | Stan wejściowy | Warunek | Wynik | Kompensacja |
  |---|---|---|---|
  | Bez zmiany | autoryzacja/widoczność; foreign asset→404 | read-only projection | Brak skutku ubocznego |

- **Transakcja:** brak blokad zapisu, idempotencji mutacyjnej i outbox.
- **Czas / awaria:** Clock UTC, logical_at i balance_version; ETag/as_of i polityka widoczności.
- **Zdarzenie:** brak.
- **UI/Filament:** Prawo i ochrona; PL/EN/DE; loading, empty, error, conflict i offline/retry; lokalne SVG, dostępny modal, brak systemowych okien. GM ma tylko jawne, audytowane Actions.
- **Test/telemetria:** expiry boundary and secret attack masking; foreign asset→404; test własności i odmowy, paginacji/ETag, brak zapisu, licznik legal.getprotectionstatus.success/conflict/error bez PII.
- **Migracja/rollback:** odczyt istniejących tabel; indeksy i migracje właściciela wskazuje DDL, bez własnego backfill. Wycofanie przez feature gate/draining i forward-fix, bez DROP historii.

### P14-F07 — AmendLimitOrder
- **Aktor i DTO:** właściciel oferty, market.trade; PUT /api/v1/market/limit-orders/{id}; new_limit_price_minor i expected_state_version → nowa wersja ceny. Kwoty BIGINT grosze/API string.
- **Stan:** published/partial → published z nową ceną, remaining_bbl i reservation_operation_id bez zmiany; filled/cancelled odmawia 409.
- **Transakcja:** company10→settlement11→storage70→market100→liquidity130→append140; CAS i Idempotency-Key. Matching sweepera widzi albo starą, albo nową cenę w całości.
- **Zdarzenie/test:** market.amend_limit_order.v1 po commit; wyścig matching/zmiana, retry, obca oferta 404, limit ceny, PL/EN/DE i SVG.

### P14-F08 — CancelLimitOrder
- **Aktor i DTO:** właściciel oferty; POST /api/v1/market/limit-orders/{id}/cancel; expected_state_version → cancelled/released_bbl.
- **Stan:** published/partial → cancelled; Logistics zwalnia dokładnie remaining_bbl według reservation_operation_id. Filled/foreign → 409/404.
- **Transakcja:** company10→settlement11→storage70→market100→append140; CAS, wspólny effect_key i Idempotency-Key. Brak podwójnego zwolnienia po retry.
- **Zdarzenie/test:** market.cancel_limit_order.v1; matching kontra anulowanie, 0/cały/częściowy remaining_bbl, crash po commit.

### P15-F07 — CreateB2BOffer
- **Aktor i DTO:** sprzedawca, contracts.manage; POST /api/v1/contracts/b2b/offers; storage/quantity/price/buyer/expiry → offer_id.
- **Stan:** dostępna ropa → reserved przez publiczny kontrakt Logistics i opublikowana oferta. Kołnierz kartelu i limit pary z kanonu §2/§13.
- **Transakcja:** obie firmy10 rosnąco, Ledger20 gdy kaucja, storage70→escrow90 gdy potrzeba→contract110→append140; Idempotency-Key, reservation_operation_id UNIQUE.
- **Zdarzenie/test:** contracts.create_b2b_offer.v1; spot kontra oferta, dwie oferty tej samej ropy, obcy odbiorca, expiry.

### P15-F08 — CancelB2BOffer
- **Aktor i DTO:** właściciel oferty; POST /api/v1/contracts/b2b/offers/{id}/cancel; expected_state_version → cancelled/released_bbl.
- **Stan:** published → cancelled i rezerwacja Logistics zwolniona raz; accepted/expired → 409. Nie zastępuje anulowania zaakceptowanego kontraktu.
- **Transakcja:** companies10→storage70→contract110→append140; CAS, effect_key i Idempotency-Key.
- **Zdarzenie/test:** contracts.cancel_b2b_offer.v1; równoległe przyjęcie/anulowanie, retry, brak utraty ropy.

### P15-F09 — CancelContract
- **Aktor i DTO:** strona umowy z contracts.manage; POST /api/v1/contracts/{id}/cancel; expected_state_version → penalty_minor/refund_minor/status.
- **Stan:** accepted → cancelled pod warunkami §13.2; kara kierunkowa min10%/max40% od różnicy spot. Receipt ma pierwszeństwo przed autozwrotem i anulowaniem w tym samym logical_at.
- **Transakcja:** obie firmy10→due12→Ledger20→trip80→escrow90→contract110→append140; jedna Action dzieli escrow, opłatę i wolumen, CAS/effect_key.
- **Zdarzenie/test:** contracts.cancel_contract.v1; receipt kontra anulowanie, floor/cap, dwa workery, brak podwójnego refundu.
