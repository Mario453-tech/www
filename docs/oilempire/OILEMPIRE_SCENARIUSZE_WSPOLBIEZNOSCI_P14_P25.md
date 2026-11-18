# OilEmpire — scenariusze współbieżności i awarii P14–P25

Status: przegląd projektowy kontraktów, nie wynik MySQL ani PHP. Implementacja odtwarza przypadki na MySQL 8.4 w dwóch transakcjach i po restarcie workera. Źródła: [LockPlan §23.5a](OILEMPIRE_ARCHITEKTURA_SYSTEMOWA.md), [karty](OILEMPIRE_PLAN_PROGRAMOWANIA.md#11-mapa-wykonawcza-p14p25), [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql), [manifest](OILEMPIRE_KONTRAKTY_P14_P25.yaml).

## Wspólny protokół

1. Ustalić wszystkie firmy, konta i zapas przed FOR UPDATE; blokować rank → table_order → PK. Ujawnienie nowej niższej rangi wymaga rollbacku całej Action i ponowienia z pełnym planem.
2. Zweryfikować aktora, capability, własność i policy gate. POLICY_NOT_APPROVED kończy komendę przed rezerwacją, FTS i outbox.
3. W jednej transakcji zapisać stan właściciela, FTS/ruch przez publiczny kontrakt, operation result, effect key i outbox. Dispatcher działa po commit ze stabilnym event_id.
4. Deadlock/timeout wycofuje cały skutek; retry używa tego samego klucza, logical_at i seed. Fencing blokuje starego workera.

## S01. Escrow kontra refund

Stan: B2B funded, 100 bbl w podróży, 1 mln gr w escrow, granica 168h. SettleReceipt i ResolveDispute/timeout startują razem. Obie Action deklarują firmy10, trip80, escrow90, contract110, append140. Pierwsza transakcja ustawia terminalny CAS; druga po locku zwraca trwały wynik albo STATE_CONFLICT. Niezmiennik: released_minor + refunded_minor ≤ held_minor; własność 100 bbl przenosi się raz. Testować obie kolejności commit, logical_at=expires_at, crash przed i po commit.

## S02. Matching kontra sprzedaż

100 bbl fizycznie, 80 zarezerwowane przez limit. MatchOrders bierze 80, ExecuteSpot chce 30. Rank company10→ledger20→storage70→offer100→liquidity130→append140. Sprzedaż 30 musi odmówić, bo dostępne jest 20; sprzedaż 20 i matching 80 mogą współistnieć. Quote nie rezerwuje. Cena globalna tylko z fazy sweepera. Crash po match przed dispatch odtwarza event, bez drugiego trade.

## S03. Egzekucja kontra transfer

Banking aukcjonuje aktywo, Contracts kończy transfer B2B. Obie Action deklarują obie firmy przed rank10. Banking rank30 sprawdza zajęcie, Logistics rank70/80 i escrow90/contract110 rozliczają własność. Zajęcie przed transferem blokuje przeniesienie zajętej części; transfer przed zajęciem wymaga ponownej wyceny dłużnika. Nie wolno wrócić z rank110 do rank30. Testować oba porządki, ten sam timestamp, aktywo w escrow/cargo wyłączone z aukcji, refund i retry.

## S04. Webhook kontra chargeback

Signed VerifyWebhook/GrantGbbl i signed ApplyChargeback startują razem. provider_event_id i transaction_key są UNIQUE; Ledger20 przed Monetization121, wpis GBBL append140. Podpis sprawdzać na surowych bajtach. Wynik może mieć grant i dług recovery albo odmowę grantu po chargeback; nie może mieć dodatniego spendable GBBL bez opłaconego zakupu. ConvertGbbl sprawdza recovery i egzekucję w tej samej transakcji. Testować duplikat, odwróconą kolejność, crash po commit, refund częściowy i zły podpis.

## S05. Retry powiadomienia

Dwa workery pobierają ten sam event_key i kanał; jeden dostaje lease/fencing. notification_deliveries ma UNIQUE(notification_id,channel_code,attempt_no). Provider key pochodzi z notification_id+channel+attempt. Crash przed odpowiedzią providera ponawia ten sam key. Gdy provider nie gwarantuje deduplikacji, oznaczyć unknown_delivery i uzgodnić stan, zamiast wysyłać ponownie. Preferencje i revocation sprawdzić przed wysyłką. Testować 429, timeout, permanent failure, wyłączenie kanału w kolejce i prywatność push.

## S06. Receptura, popyt i kontrakt

SelectRecipe zapisuje wybór gracza oraz snapshot popytu i zobowiązań. SettleRefinery liczy slot z wersją receptury. Zmiana popytu później nie przepisuje historii. Dwa handlery dla slotu tworzą jeden refinery_runs(refinery_id,slot_at) i jeden zestaw product movements. Suma input/output/flare zachowuje bilans masowy. Testować pełny magazyn i flarę do 2h, brak surowca, skrajny popyt, 2 EPC równolegle i insurance claim.

## Bramka odbioru

Każdy scenariusz wymaga testu MySQL w dwóch sesjach, wycofania transakcji w połowie, retry po commit, kontroli UNIQUE, sumy księgi zero, zachowania wolumenu, pojedynczego outbox i telemetrii deadlocków. RestoreWorld mierzy RPO≤5 min i RTO≤60 min na docelowym serwerze. 90 dni z ustalonym koszykiem i seedami to osobny test runtime.
