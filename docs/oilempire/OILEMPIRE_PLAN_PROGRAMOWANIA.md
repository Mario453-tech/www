# OilEmpire — plan programowania i wdrożenia całego silnika

Status: plan wykonawczy v1 do implementacji. Zakres obejmuje 14 węzłów game_balance i wszystkie sekcje funkcjonalne głównej specyfikacji, panel, API, mobile i eksploatację. Ten dokument nie potwierdza gotowej implementacji. Dokumenty źródłowe: architektura gry, panel, API, bezpieczeństwo i standard modułu. Liczby bierze się z kanonu i YAML; propozycje historyczne nie nadpisują ich.

## 1. Zasady wykonania od początku

Każdy pakiet P01–P25 jest zakresem prac z własnym odbiorem. To nie jest lista 25 nowych modułów PHP: np. Training pozostaje częścią Staff, a Contracts ma parametry w market. Nie tworzymy dodatkowego korzenia game_balance dla każdej klasy lub podmodułu.

Etapy realizujemy kolejno, ale panel, API, tłumaczenia i testy rozwijamy razem z pierwszą funkcją modułu. P24 jest odbiorem zintegrowanych klientów, nie terminem rozpoczęcia UI/API. Istniejący szkielet Laravel i migracje wymagają przeglądu przed użyciem; nie są dowodem zgodności ze specyfikacją.

Przed programowaniem pakietu powstaje karta implementacyjna według §3. Brak konkretnego schematu danych/kontraktu lub nierozstrzygnięte P0 blokuje ten pakiet. Nie dopuszcza się zgadywania reguły ekonomicznej w kodzie.

## 2. Etapy i bramki

| Etap | Pakiety | Wynik i bramka |
|---|---|---|
| E0: porządkowanie | rejestr błędów specyfikacji | identyfikatory, źródła prawdy, kontrakty i nierozstrzygnięte decyzje oznaczone |
| E1: fundament | P01–P05 | moduł ładuje się, dostęp jest kontrolowany, Ledger i Settlement mają testy MySQL oraz outbox |
| E2: firma i produkcja | P06–P13 | jeden pełny łańcuch: konto → pracownik → pozwolenie → odwiert → transport → naprawa |
| E3: gospodarka | P14–P18 | spot/B2B/podatki/kredyty/egzekucja/PvP spójne pod współbieżnością |
| E4: gospodarka zaawansowana | P19–P20 | rafineria i premium z księgą, rollbackiem i kontrolami integracji |
| E5: pełny produkt | P21–P24 | komunikacja, onboarding, CMS/RODO, kompletny web i mobile |
| E6: produkcja | P25 | restore, wydajność, długa symulacja, bezpieczeństwo, raport gotowości |

To kolejność budowy i testów; nie zmienia faz rozliczeń §23.3. Wspólne kontrakty są dostępne wcześniej niż pełne moduły. Testowy adapter innego modułu jest dozwolony w testach, ale nie może udawać gotowej integracji produkcyjnej.

## 3. Obowiązkowa karta implementacyjna funkcji

Dla każdej akcji i query w pakiecie programista zapisuje przed kodowaniem:
1. ID wymagania i odnośnik do paragrafu; aktor i uprawnienie; właściciel danych.
2. Tabele/kolumny, typy, jednostki, nullability, FK, indeksy i UNIQUE; migracja expand oraz plan danych.
3. Publiczny interface/DTO, input/output, błędy domenowe, kontrakt OpenAPI i mapowanie web/Filament.
4. Tabela przejść: stan źródłowy, warunek czasowy, aktor, wynik, kompensacja; przykłady odmów.
5. Transakcja: zestaw zasobów, kolejność blokad, rezerwacja, FTS/ruch bbl, idempotency key i CAS.
6. Czas: Clock UTC, due_at, faza, wersja balansu procesu, zasady opóźnienia/ponowienia i RNG.
7. Zdarzenie/outbox: schemat, odbiorcy, deduplikacja, timeout i obsługa dead-letter.
8. UI: ekran, PL/EN/DE, SVG, stany loading/empty/error/conflict/offline i dostępność.
9. Telemetria, zakres logów bez sekretów, draining i odzyskanie po awarii.
10. Testy jednostkowe, funkcjonalne, odmowy, MySQL, kontrakty i właściwe S01–S18; rollout i rollback.

Karta jest Definition of Ready. Implementacja z testami i raportem jest Definition of Done. Mapa poniżej wyznacza zakres każdej karty; nie zastępuje schematu OpenAPI ani DDL.

## 4. Macierz pokrycia pakietów

| ID | Właściciel/obszar | Węzeł balansu | Kanon | Poprzedniki implementacyjne |
|---|---|---|---|---|
| P01 | Platform | core | §1, §8, §29 | — |
| P02 | Identity (Core) | core | §14, §16 | P01 |
| P03 | Ledger (Core) | core | §23.8, §24 | P01, P02 |
| P04 | Simulation | economy | §23, §25, §29 | P01, P03 |
| P05 | Admin i Balance | admin | §18, §29.5–29.7; panel całość | P02, P03, P04 |
| P06 | Company i Reputation (Core) | core | §1–§2, §19, §22.6 | P02, P03, P04 |
| P07 | Staff — rekrutacja i zarząd | staff | §3, §10 | P03, P04, P06 |
| P08 | Staff — morale, płace, strajki | staff | §4–§5 | P07 |
| P09 | Staff/Training | staff | §6; standard modułu §9 | P07, P08 |
| P10 | Legal — koncesje i pozwolenia | legal | §7.3, §9, §11A | P03, P04, P06, P07 |
| P11 | Extraction | extraction | §9, §19.1 | P03, P04, P06, P10 |
| P12 | Logistics | logistics | §11A, §13, §21 | P03, P04, P10, P11 |
| P13 | Incidents/Technical | incidents | §11, §17.4, §21 | P07, P08, P11, P12 |
| P14 | Market | market | §12, §19.3, §25 | P03, P04, P12 |
| P15 | Contracts | market | §13, §21 | P10, P12, P14 |
| P16 | Finance | economy | §10, §19.1, §24 | P03, P06, P08, P11, P12, P15 |
| P17 | Banking | banking | §17.1–§17.3, §21 | P15, P16 |
| P18 | Legal — PvP i ochrona | legal | §7, §21, §30 | P10, P13, P15, P17 |
| P19 | Refining/EPC/Insurance | refining | §31, §21 | P08, P10, P12, P13, P14, P15, P16, P17 |
| P20 | Monetization | monetization | §22, §24.3 | P02, P03, P05, P06, P17 |
| P21 | Social i Realtime | social | §26, §28, §30 | P02, P04, P06, P18 |
| P22 | Notifications i Onboarding | onboarding | §27, §28 | P02, P04, P06, P11, P12, P14 |
| P23 | Cms/Privacy | core | §15–§16, §30 | P02, P05, P06, P21, P22 |
| P24 | Web/Mobile delivery | — | §1, API całość; panel całość | P05–P23 |
| P25 | Release/Operations | admin | §29; bezpieczeństwo §9–§11 | P01–P24 |

Nazwy tabel w kartach są słownikiem roboczym: przed migracją obowiązuje rejestr nazw §6 i sprawdzenie istniejącego schematu. Poprzedniki są kolejnością implementacji, nie listą runtime requires. Zależność runtime deklarujemy tylko, jeśli moduł rzeczywiście wymaga kontraktu przy wykonaniu; zdarzenie nie uzasadnia cyklu providers.

## 5. Zakres programowania pakiet po pakiecie

### P01. Platform

- Kanon i parametry: §1, §8, §29; węzeł core.
- Odpowiedzialność: rejestr modułów, konfiguracja, kontrakty Clock/Actor/Transaction, rejestr migracji.
- Akcje/Queries do rozpisania i wdrożenia: ModuleRegistry; ValidateManifest; ModuleAvailability.
- Dane i rejestry do zaprojektowania: manifesty, module_runtime_states, historia aktywacji.
- Kryterium specyficzne: niezgodny manifest/kontrakt/cykl blokuje start; draining nie gubi procesów.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P02. Identity (Core)

- Kanon i parametry: §14, §16; węzeł core.
- Odpowiedzialność: użytkownik, sesje, TOTP, Google linking, reset i odzyskiwanie konta.
- Akcje/Queries do rozpisania i wdrożenia: Login; VerifyTotp; RotateSession; RevokeSession; LinkIdentity.
- Dane i rejestry do zaprojektowania: users, sesje web/mobile, wyzwania, kody odzyskiwania, trusted_devices.
- Kryterium specyficzne: reset odcina HTTP i WS; refresh retry nie traktuje utraty odpowiedzi jako ataku; brak enumeracji.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P03. Ledger (Core)

- Kanon i parametry: §23.8, §24; węzeł core.
- Odpowiedzialność: konta, niezmienny FTS, rezerwacje, idempotencja i bilans surowców.
- Akcje/Queries do rozpisania i wdrożenia: PostTransfer; ReserveFunds; RecordOilMovement; ReleaseReservation.
- Dane i rejestry do zaprojektowania: konta, FTS, idempotency_records, rezerwacje, oil_movements/product_movements.
- Kryterium specyficzne: 20 konkurencyjnych sprzedaży nie tworzy pieniędzy ani ropy; rollback nie zostawia rezerwacji.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P04. Simulation

- Kanon i parametry: §23, §25, §29; węzeł economy.
- Odpowiedzialność: Clock UTC, segmenty delta-time, handler phases, due-events, CAS, outbox.
- Akcje/Queries do rozpisania i wdrożenia: SettleCompany; ProcessDueEvent; RunGlobalSweep; PublishOutbox.
- Dane i rejestry do zaprojektowania: simulation_sweeps, due_events, outbox, consumer_receipts, last_calc_at.
- Kryterium specyficzne: HTTP/job/sweeper daje jeden wynik; nasycenie/7 dni/deadlock zgodne z §23.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P05. Admin i Balance

- Kanon i parametry: §18, §29.5–29.7; panel całość; węzeł admin.
- Odpowiedzialność: role, panel shell, parametry, approval, runtime modułów, audit i alerty.
- Akcje/Queries do rozpisania i wdrożenia: ProposeBalance; ReviewBalance; ActivateBalance; BeginDrain; ExecuteGmAction.
- Dane i rejestry do zaprojektowania: balance_versions, schematy parametrów, gm_action_log, runtime i preferencje panelu.
- Kryterium specyficzne: owner bez komentarza zachowuje audit; single_owner i emergency dokładnie wg §29.7.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P06. Company i Reputation (Core)

- Kanon i parametry: §1–§2, §19, §22.6; węzeł core.
- Odpowiedzialność: firma, pakiet startowy raz, reputacje, snapshoty, urlop i dormant.
- Akcje/Queries do rozpisania i wdrożenia: CreateCompany; GrantStarter; ChangeCredibility; ActivateVacation; SnapshotWorth.
- Dane i rejestry do zaprojektowania: companies, rejestr startera, company_credibility_log, snapshoty i zmiany reputacji.
- Kryterium specyficzne: starter raz; 50% propagacji bez podwójnego naliczenia; urlop blokują procesy krytyczne.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P07. Staff — rekrutacja i zarząd

- Kanon i parametry: §3, §10; węzeł staff.
- Odpowiedzialność: kandydaci, cechy, rekrutacja, przydziały i dyrektorzy.
- Akcje/Queries do rozpisania i wdrożenia: GenerateCandidates; HireEmployee; AssignEmployee; AppointDirector.
- Dane i rejestry do zaprojektowania: employees, candidate_offers, przydziały, obsada zarządu.
- Kryterium specyficzne: suma przydziałów ≤100%; brak dyrektora zachowuje akcje awaryjne.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P08. Staff — morale, płace, strajki

- Kanon i parametry: §4–§5; węzeł staff.
- Odpowiedzialność: pensje, morale, żądania, negocjacje i deterministyczna eskalacja.
- Akcje/Queries do rozpisania i wdrożenia: EvaluateMorale; RequestRaise; NegotiateRaise; ResolveStrike.
- Dane i rejestry do zaprojektowania: employee_raise_requests, strikes, dialogi, historia wynagrodzeń.
- Kryterium specyficzne: jedno otwarte żądanie; odroczenie raz; zaległy termin nie generuje ponownych rzutów.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P09. Staff/Training

- Kanon i parametry: §6; standard modułu §9; węzeł staff.
- Odpowiedzialność: kursy, certyfikaty, egzaminy, subskille.
- Akcje/Queries do rozpisania i wdrożenia: EnrollEmployeeInCourse; CompleteCourse; AwardCertificate.
- Dane i rejestry do zaprojektowania: training_programs, course_enrollments, training_certificates.
- Kryterium specyficzne: kurs nie zatrzymuje się od strajku; zwolnienie nie przyznaje certyfikatu; draining kończy kursy.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P10. Legal — koncesje i pozwolenia

- Kanon i parametry: §7.3, §9, §11A; węzeł legal.
- Odpowiedzialność: regiony uprawnień, wnioski P1/P2, decyzje i odwołania.
- Akcje/Queries do rozpisania i wdrożenia: ApplyForPermit; DecidePermit; AppealPermit; GrantConcession.
- Dane i rejestry do zaprojektowania: permits, appeals, licenses i przypisania regionów.
- Kryterium specyficzne: P1/P2 zgodne z zakresem budowy; decyzja raz według due_at; opłata/zwrot przez FTS.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P11. Extraction

- Kanon i parametry: §9, §19.1; węzeł extraction.
- Odpowiedzialność: geologia, warstwy, wiercenie, produkcja, zużycie, depletion.
- Akcje/Queries do rozpisania i wdrożenia: SurveyStratum; DrillWell; ChangeWellMode; CalculateProduction.
- Dane i rejestry do zaprojektowania: wells, well_buffers, strata, wyniki badań, rejestr CAPEX.
- Kryterium specyficzne: plateau nie produkuje poza bufor; depleted i 70% zasobu zgodne z kanonem; CAPEX przypisany raz.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P12. Logistics

- Kanon i parametry: §11A, §13, §21; węzeł logistics.
- Odpowiedzialność: magazyny, huby, rury, flota, porty, line-pack, dostawy.
- Akcje/Queries do rozpisania i wdrożenia: BuildHub; ConnectPipeline; DispatchTrip; CreditDelivery; TowWaitingTrip.
- Dane i rejestry do zaprojektowania: hubs/hub_buffers, pipelines/segments, trucks, trips, storages, receipts.
- Kryterium specyficzne: pełny magazyn/strata/48h zatoka/receipt vs refund; jeden bilans w dwóch odcinkach.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P13. Incidents/Technical

- Kanon i parametry: §11, §17.4, §21; węzeł incidents.
- Odpowiedzialność: BHP, awarie, blowout, ekipy, naprawy i eskalacja.
- Akcje/Queries do rozpisania i wdrożenia: ScheduleMaintenance; StartRepair; EscalateIncident; CompleteRepair.
- Dane i rejestry do zaprojektowania: incidents, incident_repairs, repair_teams, safety_upgrades.
- Kryterium specyficzne: brak ekipy i strajk prowadzą do właściwej ścieżki zewnętrznej; losowanie raz.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P14. Market

- Kanon i parametry: §12, §19.3, §25; węzeł market.
- Odpowiedzialność: spot, orderbook limit, VWAP, slippage, czarny rynek i globalne wydarzenia.
- Akcje/Queries do rozpisania i wdrożenia: QuoteSale; ExecuteSpot; PlaceLimitOrder; MatchOrders; TradeBlackMarket.
- Dane i rejestry do zaprojektowania: market_offers, price_history, black_market_trades, world_events.
- Kryterium specyficzne: globalny matching tylko w sweeperze; quote nie ufa cenie klienta; jeden snapshot referencyjny.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P15. Contracts

- Kanon i parametry: §13, §21; węzeł market.
- Odpowiedzialność: NPC oferty, kontrakty okresowe, B2B, escrow, transze i spory.
- Akcje/Queries do rozpisania i wdrożenia: GenerateNpcOffers; AcceptContract; FundEscrow; SettleReceipt; ResolveDispute.
- Dane i rejestry do zaprojektowania: system_contracts, b2b_offers, contracts/tranches, escrow i spory.
- Kryterium specyficzne: receipt wygrywa z refund; brak luki anulowania i podwójnego przypisania escrow.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P16. Finance

- Kanon i parametry: §10, §19.1, §24; węzeł economy.
- Odpowiedzialność: budżety, OPEX, podatki, CAPEX, wycena i wspólny net worth.
- Akcje/Queries do rozpisania i wdrożenia: ChangeBudget; AccrueCosts; ValueAsset; SnapshotTaxBase; AssessWealthTax.
- Dane i rejestry do zaprojektowania: department_budgets, player_finance_decisions, asset_valuations, net_worth_snapshots.
- Kryterium specyficzne: marginalny podatek; ropa w tranzycie raz; brak dyskonta rafinerii drugi raz.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P17. Banking

- Kanon i parametry: §17.1–§17.3, §21; węzeł banking.
- Odpowiedzialność: scoring, kredyty, raty, restrukturyzacja, komornik, bankructwo i aukcje.
- Akcje/Queries do rozpisania i wdrożenia: ApplyLoan; PayInstallment; EvaluateCreditScore; StartEnforcement; AuctionAsset.
- Dane i rejestry do zaprojektowania: loans, obligations, bank_trust_scores, bailiff_proceedings, auctions.
- Kryterium specyficzne: escrow wyłączone w wymaganym momencie; przejęcie aktywów zachowuje procesy i historię.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P18. Legal — PvP i ochrona

- Kanon i parametry: §7, §21, §30; węzeł legal.
- Odpowiedzialność: sabotaż, ochrona, łapówki, tarcze, raporty i antynękanie.
- Akcje/Queries do rozpisania i wdrożenia: PlanSabotage; ResolveSabotage; BuyProtection; InvestigateAbuse.
- Dane i rejestry do zaprojektowania: sabotage_operations, protections, bribes, raporty i limity ofiary.
- Kryterium specyficzne: równoległe ataki respektują 5/7 dni; tarcza z timestampu; bez farmienia bonusu.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P19. Refining/EPC/Insurance

- Kanon i parametry: §31, §21; węzeł refining.
- Odpowiedzialność: działki, EPC, etapy budowy, receptury A/B/C, produkty, flara, ubezpieczenia.
- Akcje/Queries do rozpisania i wdrożenia: StartEpcBuild; AdvanceBuild; SelectRecipe; SettleRefinery; SubmitInsuranceClaim.
- Dane i rejestry do zaprojektowania: refineries, epc_contracts/stages, product_storages, policies, claims.
- Kryterium specyficzne: przerób faza 2.9/FTS faza 5; flara max 2h; limity puli, podatku i budów.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P20. Monetization

- Kanon i parametry: §22, §24.3; węzeł monetization.
- Odpowiedzialność: zamówienia, adapter operatora, premium, VIP, drip, promocje, refund.
- Akcje/Queries do rozpisania i wdrożenia: CreateOrder; VerifyWebhook; GrantGbbl; ConvertGbbl; ApplyChargeback.
- Dane i rejestry do zaprojektowania: payment_orders/transactions/events, gbbl_ledger/conversions, promo_codes.
- Kryterium specyficzne: podpis/kwota/duplikat/chargeback; brak sprzedaży reputacji; zakupy mobile wymagają polityki sklepów.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P21. Social i Realtime

- Kanon i parametry: §26, §28, §30; węzeł social.
- Odpowiedzialność: czat, kartele, dyplomacja, członkostwo, moderacja i kanały.
- Akcje/Queries do rozpisania i wdrożenia: SendMessage; JoinCartel; LeaveCartel; ModerateMessage; AuthorizeChannel.
- Dane i rejestry do zaprojektowania: chat_threads/messages, cartels/memberships, diplomacy, moderation_reports.
- Kryterium specyficzne: revokacja WS działa na otwartym połączeniu; brak cudzych rozmów; limit prowizji kartelu.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P22. Notifications i Onboarding

- Kanon i parametry: §27, §28; węzeł onboarding.
- Odpowiedzialność: FTUE, osiągnięcia, tutorial, centrum powiadomień, e-mail, preferencje i adapter push.
- Akcje/Queries do rozpisania i wdrożenia: AdvanceTutorial; GrantAchievement; DeliverNotification; UpdatePreferences.
- Dane i rejestry do zaprojektowania: tutorial_progress, achievement_grants, notifications/preferences/device_tokens.
- Kryterium specyficzne: nagroda raz; brak push nie cofa skutku; powiadomienia krytyczne i preferencje zgodne z §27.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P23. Cms/Privacy

- Kanon i parametry: §15–§16, §30; węzeł core.
- Odpowiedzialność: CMS, nawigacja, regulaminy, zgody, eksport/anonimizacja, reset/newsletter TinyMCE.
- Akcje/Queries do rozpisania i wdrożenia: PublishPage; PublishPolicy; RecordConsent; ExportPersonalData; AnonymizeUser; SendNewsletter.
- Dane i rejestry do zaprojektowania: static_pages, navigation_items, privacy_policies, consents, privacy_requests, campaigns.
- Kryterium specyficzne: sanityzacja, odwołanie zgód, anonimizacja bez kasowania ksiąg; PL/EN/DE i neutralne błędy.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P24. Web/Mobile delivery

- Kanon i parametry: §1, API całość; panel całość; węzeł —.
- Odpowiedzialność: pulpit Inertia/Vue, Flutter, API v1, dostępność, konfiguracja klienta i wydania.
- Akcje/Queries do rozpisania i wdrożenia: SnapshotResource; endpointy modułów; klient OpenAPI; deep link routing.
- Dane i rejestry do zaprojektowania: preferencje UI i wersje klienta; brak własnego stanu ekonomicznego.
- Kryterium specyficzne: 320–1440 px, PL/EN/DE, kontrakty starego klienta, offline/retry i prywatność push.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

### P25. Release/Operations

- Kanon i parametry: §29; bezpieczeństwo §9–§11; węzeł admin.
- Odpowiedzialność: health, metryki, backup/restore, wydanie, rekoncyliacja i gotowość produkcyjna.
- Akcje/Queries do rozpisania i wdrożenia: BuildReleaseReport; RunReconciliation; RestoreWorld; VerifyRelease.
- Dane i rejestry do zaprojektowania: release_reports, restore_reports, runbooks, alerty i checksumy.
- Kryterium specyficzne: RPO≤5 min, zmierzone RTO, 90 dni symulacji i limit inflacji, rollback zgodny ze schematem.
- Dostarczenie: karta §3 dla każdej operacji, kontrakty, polityki, migracje, API i panel właściwe dla zakresu, PL/EN/DE, telemetria i testy. Zakończenie wymaga dowodu wykonania, a nie zaznaczenia pola w planie.

## 6. Rejestr nazw i właścicieli

- Core: identity, company, reputacje i Ledger są wydzielonymi odpowiedzialnościami jednego właściciela. Nie tworzymy niezależnego hr/identity/finance w YAML tylko dlatego, że przykładowy manifest ich wymienił.
- Staff: rekrutacja, płace, zarząd i Training; publiczne kontrakty staff.employees.v1 i staff.training.v1. Trasy /hr pozostają nazwą UI.
- Finance jest właścicielem budżetów i naliczania podatków/OPEX; Ledger w Core jest właścicielem zapisu księgi. Banking nie zapisuje FTS bez publicznego kontraktu Ledger.
- Contracts odpowiada za umowy/escrow; Market za oferty i globalną cenę. Obie odpowiedzialności używają węzła market, bez powielania parametrów.
- Simulation planuje rozliczenia, ale nie przejmuje tabel domenowych. Moduły udostępniają handlery faz i jawne kontrakty; handler nie sięga do prywatnych modeli innego modułu.
- Rozbieżne nazwy transport_runs/trips i b2b_escrow_lock/escrow_transactions wymagają jednego rejestru DDL przed P12/P15. Nazwy w starszych tabelach i diagramach nie są automatycznie istniejącymi migracjami.
- Kanoniczna ścieżka to Modules/<Name>/manifest.yaml; pozostałe przykłady katalogów są historyczne i podlegają aktualizacji przed wygenerowaniem kodu.

## 7. Pierwszy pionowy zakres wykonawczy

Po P01–P05: utworzyć firmę z pakietem raz, odczytać snapshot, zasiać syntetyczny zasób produkcyjny, wykonać jedną autoryzowaną akcję transferu/sprzedaży i pokazać wynik w web/API/panelu. Fixture nie zastępuje skończonej geologii i logistyki.

Na tej ścieżce należy odtworzyć: cudzy zasób, równoległe żądania, utratę odpowiedzi, awarię przed/po commicie i utracony dispatch. Dopiero po zaliczeniu fundamentu rozszerza się zakres ekonomii. Testy równoległości wykorzystują osobne połączenia MySQL i kontrolowane bariery, nie sekwencyjne wywołanie 20 razy.

## 8. Plan weryfikacji dokumentacji

Poziom D1: parsowanie YAML z wykrywaniem duplikatów, odnośniki do plików, komplet 14 węzłów i 25 pakietów, identyfikatory, brak cykli poprzedników.

Poziom D2: ręczne przejście kontraktów i stanów, porównanie liczb z YAML, scenariusze §21 i S01–S18. Scenariusz opisuje preconditions → równoległe zdarzenia → oczekiwany bilans/stan; brak odpowiedzi jest luką dokumentacji, nie wolnym wyborem programisty.

Poziom D3: istniejący symulator Python — dowód wyłącznie dla asercji, które faktycznie wykonuje. Nie dowodzi poprawności transakcji MySQL, API, panelu ani 90-dniowej stabilności.

Poziom D4 po implementacji: golden-master PHP na prawdziwym SettlementService, współbieżność MySQL, awarie workerów i outbox, testy kontraktu starej aplikacji, E2E web/mobile/admin, restore oraz 90 dni symulacji. Próg inflacji <5%/miesiąc wymaga jawnej definicji wskaźnika i scenariusza populacji przed zatwierdzeniem testu.

Wynik audytu trafia do OILEMPIRE_AUDYT_GOTOWOSCI_PROGRAMOWANIA.md z identyfikatorem problemu, wagą, dowodem i statusem. „Nie znaleziono błędu” nie jest gwarancją, że dokument nie zawiera błędów.

## 9. Bramka gotowości

Gotowość dokumentacji do rozpoczęcia fundamentu: plan, kontrakty bezpieczeństwa i jawne blokery. Gotowość konkretnego pakietu do kodowania: karta §3 bez otwartego P0/P1 dla jego zakresu. Gotowość do produkcji: wszystkie wymagane pakiety odebrane, implementacyjne testy zaliczone, blokery ekonomii/integracji zamknięte i podpisany raport wydania.

Nie obiecujemy dat na podstawie liczby dokumentów. Po pierwszym pionowym zakresie szacujemy kolejne pakiety na podstawie rzeczywistego czasu, liczby migracji i ryzyk.
