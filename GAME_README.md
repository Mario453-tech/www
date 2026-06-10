## Changelog

### 2026-06-10 - Portfel: rozdzielenie gotówki i salda konta bankowego (Faza 1 — struktura + UI)

**Nowa architektura portfela gracza:**
- `src/WalletConfig.php` (NOWY) — centralny rejestr konfiguracji: nazwy pul (`POOL_CASH='cash'`, `POOL_BANK='bank_balance'`), limity transferu (min 100 PLN, max 500 000 PLN), prowizja (0,5%, min 10 PLN), podział startowy (50/50), mapa routingu fazy 2 (`TYPE_TO_POOL`). Jedyne miejsce do edycji wszystkich parametrów portfela.
- `src/WalletService.php` (NOWY) — surowe operacje DB: `getBalances()`, `transferBetweenPools()`, `initNewPlayer()`. Migracja schematu w `ensureSchema()`: dodaje kolumny `bank_balance` + `wallet_initialized`, jednorazowo dzieli `cash` 50/50 dla istniejących graczy.
- `src/CashTransferService.php` (NOWY) — logika biznesowa transferu gracza: walidacja kwoty, obliczenie prowizji, atomowy UPDATE (kwota+prowizja z puli źródłowej, kwota do puli docelowej), audit trail w `bank_transactions`. Metody: `cashToBank()`, `bankToCash()`, `calcFee()`.
- `public/wallet_transfer.php` (NOWY) — AJAX endpoint POST `/wallet-transfer`: autoryzacja + CSRF + wywołanie `CashTransferService`; zwraca JSON `{success, message, new_cash, new_bank, fee}`.
- `assets/js/wallet.js` (NOWY) — logika UI: podgląd prowizji przy wpisywaniu kwoty, potwierdzenie przez `confirmAction`, AJAX submit, aktualizacja sald w DOM bez przeładowania strony.
- `assets/css/wallet.css` (NOWY) — style sekcji portfela w banku: kafelki sald (gotówka/konto), formularze transferu, strzałki kierunkowe.

**Zmiany w istniejących plikach:**
- `src/FinancialTransactionService.php` — nowy typ `TYPE_POOL_TRANSFER = 'pool_transfer'` (audit trail transferów portfelowych).
- `src/GameShell.php` — naprawiono etykietę `$ USD` → `PLN`; dodano 5. KPI `index.bank_balance` z `bank_balance`; grid przechodzi przez `new WalletService()` aby zapewnić schemat.
- `assets/css/style.css` — `.status-grid--redesign`: `repeat(4, 1fr)` → `repeat(auto-fit, minmax(170px, 1fr))` — grid obsługuje teraz dowolną liczbę KPI.
- `src/Bank/DataLoader.php` — `loadAccountData()` używa teraz `WalletService::getBalances()`: `accountBalance` = `bank_balance` (saldo konta), `cashBalance` = `cash` (gotówka).
- `templates/views/bank/main.php` — nowa sekcja „Portfel" z kafelkami obu sald i formularzami transferu; konfiguracja `window.WALLET_API/CSRF/FEE_*/LANG` dla `wallet.js`.
- `public/bank.php` — ładuje `wallet.css` i `wallet.js`.
- `public/register.php` — nowi gracze: po starcie `WalletService::initNewPlayer()` dzieli 10 000 000 PLN 50/50 (5M gotówka, 5M konto).
- `lang/pl/bank.php` — klucze `wallet.*` (sekcja, przyciski, błędy, komunikaty).
- `lang/pl/director.php` — klucz `index.bank_balance` dla HUD.
- `src/init.php` — trasa `wallet-transfer` w ROUTES.
- `.htaccess` — reguła `^wallet-transfer$ → /public/wallet_transfer.php`.

**Faza 2 (routing) i Faza 3 (5 zastosowań gotówki) — zaplanowane w `WalletConfig::TYPE_TO_POOL` i `CASH_ONLY_TYPES`, nieaktywne.**

### 2026-06-10 - Bank: negocjacje, restrukturyzacja i HR przez centralne API finansowe
- `src/FinancialTransactionService.php` - nowy typ `bank_fee` (opłaty bankowe, np. za negocjacje).
- `src/BankNegotiation/ProcessorTrait.php` - opłata dodatkowa za negocjacje z bankiem przechodzi przez `debit()` (typ `bank_fee`); rollback + komunikat gdy brak środków.
- `src/Bankruptcy/OptionsTrait.php` - wypłaty za sprzedaż odwiertu i magazynu w restrukturyzacji idą przez `credit()` (typ `bankruptcy_event`) zamiast `UPDATE` + osobny `logTransaction`; rollback gdy księgowanie się nie powiedzie.
- `src/HR/HiringTrait.php` - pierwsza pensja przy zatrudnieniu pracownika technicznego przez `debit()` (typ `hr_fee`); rollback przy braku środków.
- `src/HeadhunterService.php` - opłata za wyszukiwanie oraz premia za zatrudnienie przez headhuntera przez `debit()` (typ `hr_fee`); rollback przy braku środków.
- `lang/pl/bank.php` - etykieta typu `bank_fee` oraz opisy operacji: negocjacje, sprzedaż odwiertu/magazynu w restrukturyzacji, zatrudnienie, headhunter (wyszukiwanie i premia).

### 2026-06-10 - Tick: naprawa podwójnego pobrania gotówki za katastrofy
- `src/Well/DisastersTrait.php` - usunięto bezpośrednie `UPDATE players SET cash = cash - X` z czterech katastrof (`triggerPipelineExplosion`, `triggerSurfaceSpill`, `triggerBlowout`, `triggerReservoirContamination`). Eksplozja rurociągu i wyciek były pobierane DWUKROTNIE: raz przez bezpośredni `UPDATE`, drugi raz przez tick (`cashDelta` + różnicowy `saveCashAndTick`) - gracz tracił podwójną kwotę kary (np. 40 mln zamiast 20 mln).
- `src/Tick/WellRiskHandler.php` - blowout i skażenie rezerwuaru doliczają teraz koszt+karę do `finIncident` i `playerCash` w ticku (wcześniej polegały na bezpośrednim `UPDATE`, który właśnie usunięto). Dzięki temu wszystkie cztery katastrofy są pobierane dokładnie raz, przez tick jako jedynego płatnika, i trafiają do audytu bankowego (`tick_incident`) oraz wykrywania kryzysu.

### 2026-06-10 - Bank: komornik, sprzedaż odwiertu i czarny rynek przez centralne API finansowe
- `src/FinancialTransactionService.php` - nowe typy operacji: `well_sale` (sprzedaż odwiertu) i `black_market_sale` (czarny rynek, przychód i kara).
- `src/BailiffService.php` - zajęcie 30% gotówki przez komornika przechodzi przez `FinancialTransactionService::debit()` (ruch gotówki + wpis w historii bankowej zamiast osobnego `UPDATE` + `logTransaction`); fallback do bezpośredniego `UPDATE` gdy FTS niedostępny.
- `src/Well/SellTrait.php` - sprzedaż odwiertu księguje wpływ przez `credit()` (typ `well_sale`, referencja do odwiertu) wewnątrz istniejącej transakcji; rollback + komunikat błędu gdy księgowanie się nie powiedzie.
- `src/BlackMarketService.php` - przychód i kara za handel na czarnym rynku idą przez `credit()`/`debit()` (typ `black_market_sale`); aktualizacja `black_market_score`/`credit_score` została oddzielona od ruchu gotówki, bez podwójnego pobrania.
- `lang/pl/bank.php`, `lang/pl/components.php` - etykiety typów i opisy operacji `well_sale`, `black_market_sale`, kary czarnorynkowej oraz komunikat błędu sprzedaży odwiertu.

### 2026-06-09 - Tick: audyt bezpieczenstwa i naprawa bledow
- `src/Tick/PlayersSection.php` - naprawiono blokujacy blad nowych graczy: `last_tick_at = NULL` powodowal `TypeError` w `new DateTime()` i gracz nigdy nie dostawal pierwszego ticka; query uzywa teraz `COALESCE(last_tick_at, '2000-01-01 00:00:00')`.
- `src/Tick/PlayersSection.php` - wykrywanie kryzysu finansowego (`FinancialStateSection::process`) uwzglednia teraz pelny koszt incydentow (odwierty + katastrofy rurociagow + kary za wyciek). Wczesniej eksplozja rurociagu mogla wyzerowac gotowke bez wyzwolenia kryzysu.
- `src/Tick/PlayersSection.php` - odliczenia gotowki za rurociagi i wyciek dostaly floor `max(0.0, ...)` jak pozostale koszty, zeby ujemne saldo nie psulo logiki kryzysu.
- `src/Tick/FinancialStateSection.php` - licznik godzin kryzysu uzywa wstrzyknietego `$this->now` zamiast `time()`/`date()` (spojnosc z reszta ticka, testowalnosc).
- `cron/tick.php` - dodano lock wykonania (`flock`) zapobiegajacy nakladaniu sie tickow gdy poprzedni przebieg trwa dluzej niz interwal crona (ochrona przed podwojona produkcja/kosztami). Klucz crona porownywany teraz przez `hash_equals` (odpornosc na timing attack).

### 2026-06-09 - Bank: koszty tickowe i sprzedaż ropy w historii bankowej
- `src/MarketOffer.php` - automatyczna sprzedaż ropy (oferty rynkowe wykonywane w ticku) przeszła ze starego `UPDATE players SET cash +` na `FinancialTransactionService::credit()` z opisem i referencją do oferty (`market_offer`).
- `src/FinancialTransactionService.php` - nowe typy operacji tickowych: `tick_opex`, `tick_salary`, `tick_transport`, `tick_incident`, `hub_usage`; nowa stała `TICK_AUDIT_TYPES` (razem z `tax`) oraz metoda `purgeTickAudit()` usuwająca stare wpisy tickowe (przelewy, kredyty i zakupy zostają na zawsze).
- `src/Tick/PlayersSection.php` - nowa metoda `logTickBankAudit()`: po zapisie gotówki tick dopisuje do `bank_transactions` zbiorcze koszty gracza per kategoria (podatek regionalny, OPEX odwiertów, opłaty hubowe, pensje, transport, incydenty + katastrofy rurociągów + kary środowiskowe); wpis tylko gdy kwota > 0; sam audit trail przez `logTransaction()` - gotówka schodzi różnicowo w `saveCashAndTick`, bez podwójnego pobrania; OPEX pomniejszony o opłaty hubowe (te wpadają do obu akumulatorów w `WellHubSection`).
- `cron/tick.php` - sekcja cleanup wywołuje `purgeTickAudit()` z tą samą retencją co `incident_retention_days` (domyślnie 30 dni).
- `lang/pl/bank.php` - opisy operacji `bank.tx_tick_*`, `bank.tx_market_sale` oraz etykiety typów `bank.account.type.tick_*` i `hub_usage` dla pilli w historii konta.
- `tests/Integration/FinancialTransactionServiceTest.php` - 2 nowe testy: akceptacja typów tickowych przez `logTransaction()` oraz `purgeTickAudit()` usuwający wyłącznie stare wpisy tickowe. Testy zielone: 176/176 SQLite.

### 2026-06-09 - Bank: zakupy i oplaty gracza przez centralne API finansowe
- `src/PlayerPaymentService.php` - dodano czytelna klase posrednia dla oplat gracza (`charge()` / `refund()`), oparta o `FinancialTransactionService`.
- `src/FinancialTransactionService.php`, `src/LegalService.php`, `src/Legal/HubPermitTrait.php`, `src/WorldMap.php`, `public/upgrade_storage.php` - schemat bankowy jest przygotowywany przed recznie otwierana transakcja, zeby pierwsze uzycie na bazie bez nowych tabel nie wywolywalo DDL w srodku transakcji MySQL.
- `src/HubAcquisitionService.php` - zakup, wynajem, rozbudowa oraz zwroty oplat za huby przechodza przez `PlayerPaymentService` i zapis bankowy typu `hub_purchase`.
- `src/WellPipelineService.php` - budowa rurociagow, naprawy i konserwacje ksiegowane sa przez `PlayerPaymentService` jako `pipeline_purchase`, `pipeline_repair` i `pipeline_maintenance`, bez osobnego recznego `UPDATE players SET cash`.
- `src/LegalService.php`, `src/Legal/HubPermitTrait.php` - oplaty za wnioski prawne dla odwiertow i hubow trafiaja do historii bankowej jako `legal_fee`.
- `src/WorldMap.php`, `src/GeologicalLayerService.php`, `public/upgrade_storage.php` - zakup lokalizacji, zmiana warstwy geologicznej i rozbudowa magazynu korzystaja z centralnego pobrania srodkow przez klase oplat gracza.
- `lang/pl/bank.php` - dodano czytelne opisy operacji bankowych dla powyzszych zakupow i oplat.

### 2026-06-09 - Bank: kredyty przez centralne API finansowe
- `src/Bank/ApplicationTrait.php` - akceptacja oferty kredytowej ksieguje wyplate kredytu przez `FinancialTransactionService::credit()` i zapisuje wpis `loan` w `bank_transactions`.
- `src/Bank/RepaymentTrait.php` - reczna splata rat, kilku rat albo calego kredytu przechodzi przez `FinancialTransactionService::debit()` i zapisuje typ `loan_payment` w historii bankowej.
- `src/LoanRepository.php` - automatyczne raty obslugiwane w ticku sa teraz atomowe: pobranie gotowki, wpis w `bank_transactions`, aktualizacja kredytu i wpis w `loan_payments` ida w jednej transakcji.
- `lang/pl/bank.php` - dodano opisy operacji bankowych dla wyplat kredytow, splat recznych i rat automatycznych.

### 2026-06-09 - Dzial prawny admin: czytelniejsza konfiguracja regionow
- `templates/views/admin/legal/main.php`, `lang/pl/admin/legal.php`, `assets/css/admin.css` - sekcja konfiguracji regionow dostala prosty opis dla laika, grupowane naglowki tabeli, lepsze wyróznienie pierwszej i ostatniej kolumny oraz czytelniejsze formularze dla parametrow odwiertow i hubow; dodatkowo formularze akcji w zakladce wnioskow hubow korzystaja juz ze wspolnej klasy `js-confirm-form` bez inline `style`.

### 2026-06-09 - Dzial prawny: poprawka bootstrapu hub permits
- `src/Legal/HubPermitTrait.php` - usunieto niekompatybilne dla aktualnego MySQL `ALTER TABLE ... ADD COLUMN IF NOT EXISTS ...`; schema hub permits korzysta teraz z `Database::addColumnIfMissing()`, dzieki czemu poprawnie tworza sie kolumny `hub_permit_*` oraz tabela `hub_permit_applications`.

### 2026-06-08 - Admin legal: naprawa potwierdzen akcji
- `admin/legal.php`, `templates/views/admin/legal/main.php` - dzial prawny admina korzysta juz tylko z jednego globalnego handlera `modal.js` dla formularzy `data-confirm`; usunieto dodatkowe podpiete `admin_legal.js`, ktore dublowalo przechwycenie submitu i blokowalo akcje `Seeduj regiony` oraz `Uruchom migracje`.

### 2026-06-08 - Logistyka: cleanup starych aktywnych dostaw morskich
- `src/MarineDeliveryService.php`, `public/logistics.php` - dodano bezpieczne czyszczenie osieroconych aktywnych dostaw morskich bez wpisu w `port_queue`, z ETA starszym niz 12 godzin; usuwa to stare mikro-kursy po poprzednim modelu i zostawia aktualne, prawidlowe rejsy w logistyce.

### 2026-06-08 - Czyste zakladki technical i black market
- `assets/js/ajax_pagination.js`, `templates/views/market/main.php`, `lang/pl/logistics.php` - wspolny mechanizm czysci teraz takze adresy zakladek `technical` oraz `market/black_market`; w dostawach morskich etykiete `ETA` zmieniono na bardziej czytelne `Planowane dotarcie`.

### 2026-06-08 - Globalna paginacja bez przeladowania
- `assets/js/ajax_pagination.js`, `templates/footer.php`, `admin/partials/footer.php`, `templates/views/market/main.php`, `assets/js/logistics.js` - dodano jeden wspolny mechanizm czesciowej paginacji i zakladek modulow dla stron gry oraz admina: market, technical, logistyka i pozostale kontenery paginacji podmieniaja glowna tresc strony, zostawiaja czysty adres bez query stringa i przewijaja do tej samej sekcji/paginacji.

### 2026-06-08 - Logistyka: czysty adres przy paginacji
- `assets/js/logistics.js` - paginacja AJAX w logistyce zapisuje techniczny adres z parametrami tylko w `history.state`, a w pasku przegladarki zostawia czysty adres `/logistics` bez query stringa i hashy sekcji.

### 2026-06-08 - Logistyka: paginacja bez przeskoku strony
- `assets/js/logistics.js` - linki paginacji w module logistyki dzialaja teraz jako czesciowe odswiezenie `.logistics-page`: klikniecie pobiera nowy HTML w tle, podmienia tylko modul logistyki, aktualizuje URL i przewija do aktualnej sekcji zamiast ladowac strone od gory.

### 2026-06-08 - Logistyka: paginacja historii i incydentow
- `logistics.php`, `templates/views/logistics/main.php`, `src/MarineDeliveryService.php` - historia dostaw morskich i incydenty logistyczne hubow sa stronicowane po 5 pozycji; historia dostaw morskich w ticku jest czyszczona po 7 dniach dla statusow `delivered` i `lost`.

### 2026-06-08 - Logistyka: paginacja transportu morskiego i drogowego
- `logistics.php`, `templates/views/logistics/main.php`, `src/MarineDeliveryService.php` - aktywne kursy drogowe i dostawy morskie sa stronicowane po 5 pozycji; panel morski pokazuje laczna liczbe aktywnych dostaw w KPI oraz nawigacje poprzednia/nastepna dla `marine_page`.

### 2026-06-08 - Logistyka: aktywne dostawy morskie w glownym kontrolerze
- `logistics.php` - glowny kontroler routingu `/logistics` laduje teraz `MarineDeliveryService`, bufory tankowcow, aktywne rejsy, historie i fallback panelu; wczesniej dane byly ustawiane na puste tablice, wiec widok pokazywal `0` mimo aktywnych dostaw w adminie.

### 2026-06-07 - Logistyka: priorytet aktywnych rejsow morskich
- `src/MarineDeliveryService.php` - aktywne dostawy morskie w panelu logistyki sortuja teraz realne rejsy (`departing`, `in_transit`, `delayed`) przed kolejka portowa, a liczniki nie uwzgledniaja starych opoznionych rekordow spoza 2-dniowego okna; dzieki temu rejs widoczny w adminie jako `in_transit` nie znika pod zaleglymi wpisami `waiting_for_port`.

### 2026-06-07 - Logistyka: widocznosc dostaw morskich
- `public/logistics.php`, `src/MarineDeliveryService.php` - panel logistyki jawnie laduje serwisy portow i dostaw morskich oraz ma awaryjne pobieranie danych z `marine_deliveries`, `ports` i `wells.marine_buffer_bbl`, zeby aktywne rejsy, historia i bufor tankowca nie zerowaly sie przy bledzie serwisu.

### 2026-06-07 - Logistyka: korekta bufora hubow w ticku
- `src/Tick/WellProductionHandler.php`, `src/Tick/PlayersSection.php`, `src/Tick/WellLoopSection.php`, `src/Tick/WellHubSection.php`, `src/HubTickService.php`, `src/Hub/TickPersistTrait.php`, `src/Hub/TickCalculationsTrait.php` - transport czasowy (`ciezarowki`, `tankowiec`) nie dopisuje juz produkcji do huba przed realna dostawa, a dostawy po dotarciu przechodza przez finalizacje huba; tick rozroznia rope przetworzona, pozostawiona w buforze i spuszczona z bufora, z korekta magazynu oraz finansow.

### 2026-06-07 - Logistyka: poprawka rozbudowy wlasnych hubow
- `assets/js/logistics_hubs.js`, `templates/views/logistics/main.php`, `lang/pl/logistics.php` - akcje `Napraw` i `Rozbuduj` w sekcji `Twoje huby` pobieraja teraz dane z karty wlasnego huba, a nie z rynku hubow; usunieto efekt `NaN PLN`, poprawiono stary tekst `Uaktualnij` na `Rozbuduj` i zachowano blokade rozbudowy hubow wynajmowanych/systemowych.

### 2026-06-07 - Logistyka: rozbudowa wlasnych hubow
- `src/Hub/ViewHubsTrait.php`, `templates/views/logistics/main.php`, `assets/js/logistics_hubs.js`, `lang/pl/logistics.php` - w sekcji `Twoje huby` podlaczono przycisk `Rozbuduj` dla hubow nalezacych do gracza; widok korzysta z istniejacego backendu `HubApi.php` / `HubAcquisitionService.php`, ktory pobiera koszt, respektuje maksymalny poziom 3 i odpala modal potwierdzenia.

### 2026-06-07 - Logistyka: paginacja kursow drogowych
- `public/logistics.php`, `templates/views/logistics/main.php`, `assets/css/logistics.css`, `lang/pl/logistics.php` - sekcja `Kursy drogowe w tranzycie` pokazuje teraz kursy po 10 na strone, z licznikiem wszystkich aktywnych kursow i nawigacja poprzednia/nastepna.

### 2026-06-07 - Transport morski: historia rejsow w logistyce
- `src/MarineDeliveryService.php`, `templates/views/logistics/main.php`, `assets/css/logistics.css`, `lang/pl/logistics.php` - pod sekcja `Dostawy morskie` dodano widoczny blok krotkiej historii rejsow tankowca; historia korzysta z biezacych rekordow `marine_deliveries`, sortuje po dacie zakonczenia i moze znikac po czyszczeniu ticka, bez stalego archiwum.

### 2026-06-07 - Transport morski: bufor tankowca
- `src/Tick/WellProductionHandler.php`, `src/TransportConfigService.php`, `admin/transport.php`, `templates/views/admin/transport/main.php`, `lang/pl/admin/transport.php` - transport morski nie wysyla juz mikrorejsow co tick; ropa z odwiertu tankowcowego trafia najpierw do bufora `wells.marine_buffer_bbl`, a tankowiec wyrusza dopiero po osiagnieciu progu `min_load_bbl`.
- `admin/transport.php` - prog startu tankowca jest edytowalny w panelu admina dla typu `tankowiec` jako `Minimalna ladownosc tankowca (bbl)`; aktualny balans produkcyjny: `4000 bbl`, a wartosc `0` oznacza stary model wysylki natychmiastowej.
- `src/MarineDeliveryService.php`, `public/logistics.php`, `templates/views/logistics/main.php`, `assets/css/logistics.css`, `lang/pl/logistics.php` - panel logistyki gracza pokazuje teraz bufory tankowcow per odwiert: aktualne bbl, prog wyplyniecia, brakujacy wolumen i pasek postepu.

### 2026-06-05 - Wiarygodnosc firmy: tick i bramka dzialu prawnego
- `src/CompanyCredibilityService.php` - dodano przyznawanie eventu `clean_operation_period` raz na 7 dni, jesli gracz nie mial w tym okresie negatywnych zdarzen wiarygodnosci.
- `src/Tick/CredibilitySection.php`, `cron/tick.php` - dodano sekcje ticku przyznajaca bonus +3 za czysty okres dzialania; przed zmiana ticka wykonano backup `backups/2026-06-05_19-51-17_tick.php.bak`.
- `src/LegalService.php`, `public/legal.php`, `templates/views/legal/main.php` - regiony `high/critical` wymagaja wiarygodnosci firmy min. 40/100 do skladania wnioskow, a widok pokazuje osobna grupe blokady.
- `src/WorldMap.php`, `templates/views/map/main.php`, `assets/js/world_map.js`, `assets/css/map.css` - mapa rozpoznaje status `credibility_locked` i pokazuje wymagany oraz aktualny wynik wiarygodnosci.
- `lang/pl/legal.php`, `lang/pl/map.php`, `lang/pl/credibility.php`, `assets/css/legal.css` - dodano teksty i style dla blokady wiarygodnoscia oraz notatke historii czystego okresu.
- `tests/Integration/CompanyCredibilityServiceTest.php`, `tests/Integration/LegalServiceTest.php`, `tests/Integration/LegalMapPermitDataTest.php` - dodano testy bonusu czystego okresu i blokady wnioskow przez niska wiarygodnosc.
- `DZIAL_PRAWNY_P1_STATUS.md` - zaktualizowano status dzialu prawnego po wdrozeniu punktu `18.6`, dodano TODO i rekomendowana kolejnosc dalszych prac.
- `DZIAL_PRAWNY_P1_STATUS.md` - doprecyzowano, ze `18.6` jest wdrozone jako zamkniety fundament, a pozostale pozycje to przyszle rozszerzenia balansu i kolejnych dzialow.
- `src/Tick/PlayersSection.php` - utracone dostawy morskie z `MarineDeliverySection` (`piracy` / `catastrophe`) sa teraz ksiegowane jako straty transportowe w `finance_logs`; backup przed zmiana ticka: `backups/2026-06-05_22-03-17_PlayersSection.php.bak`.
- `DZIAL_PRAWNY_P1_STATUS.md` - zweryfikowano zdarzenia transportowe: drogowe, rurociagowe i utracone dostawy morskie trafiaja do `finance_logs`; morski `storm` pozostaje opoznieniem bez utraty ropy.
- `admin/incidents.php`, `templates/views/admin/incidents/main.php`, `assets/js/admin_incidents.js`, `lang/pl/admin/incidents.php` - dodano osobny toolbar admina do recznego wywolywania incydentow morskich (`piracy`, `catastrophe`, `storm`, `breakdown`) oraz zrodlo `marine` w historii incydentow.
- `templates/views/admin/incidents/main.php`, `assets/js/admin_incidents.js`, `lang/pl/admin/incidents.php` - toolbar incydentow morskich przeniesiono do widocznej zakladki `Morskie`, zamiast chowac go w zakladce wywolywania incydentow odwiertow.
- `admin/incidents.php`, `assets/js/admin_incidents.js`, `templates/views/admin/incidents/main.php`, `lang/pl/admin/incidents.php` - lista dostaw morskich w toolbarze admina jest limitowana do 15 aktywnych dostaw wybranego gracza i odrzuca transporty, ktorych odwiert nie nalezy do tego gracza.

### 2026-06-05 - Strona glowna: status uszkodzony przy 1% stanu
- `src/WellGridData.php` - aktywny odwiert ze stanem technicznym `<= 1%` jest na stronie glownej prezentowany jako `broken`, zamiast jako aktywny.
- `templates/components/well_grid.php` - podsumowanie regionu i laczne wydobycie licza status wyswietlany (`_status/_isActive`), wiec odwiert krytyczny nie zawyza aktywnych KPI.

### 2026-06-05 - Karty odwiertow: stale KPI przy wstrzymaniu
- `templates/components/well_grid.php` - karty odwiertow pokazuja teraz wydobycie, stan, tryb i zloze takze dla statusow wstrzymanych; wydobycie aktywne pokazuje wartosc normalnie, a wstrzymane pokazuje `0 bbl/h` z bazowym potencjalem.
- `lang/pl/components.php` - dodano etykiety `wg.stat_paused` i `wg.stat_base` dla informacji pod KPI wydobycia.

### 2026-06-05 - Aktualnosci spolki: tytul z TinyMCE
- `src/AdminNewsHtml.php` - dodano wspolny sanitizer HTML dla tytulu i tresci aktualnosci admina.
- `admin/news.php`, `templates/views/admin/news/main.php`, `assets/js/admin_news_editor.js` - tytul aktualnosci jest edytowany przez TinyMCE, zapisywany w `admin_news.title_html`, a `admin_news.title` zostaje tekstowym fallbackiem.
- `src/AdminNewsApi.php`, `assets/js/chat.js`, `assets/css/chat.css` - API zwraca bezpieczne `title_html`, a panel aktualnosci renderuje formatowany tytul.
- `assets/css/admin.css` - dodano style podgladu formatowanego tytulu w liscie aktualnosci admina.

### 2026-06-05 - Aktualnosci spolki: render HTML z TinyMCE
- `src/AdminNewsApi.php` - dodano bezpieczne czyszczenie HTML aktualnosci i pole `content_html`, aby tresc z TinyMCE zachowala naglowki, linki i kolory tekstu.
- `assets/js/chat.js` - panel aktualnosci renderuje teraz HTML zwrocony przez API zamiast wyswietlac tresc jako zwykly tekst.
- `assets/js/chat.js` - poprawiono scope helpera renderowania HTML i tekst komunikatu ladowania, aby panel nie pokazywal `Bd adowania.` przy poprawnej odpowiedzi API.
- `assets/css/chat.css` - dodano style dla akapitow, list, naglowkow, cytatow i linkow w panelu aktualnosci spolki.

### 2026-06-04 — Dział prawny: domknięcie P1 i start P2
- `src/LegalService.php` — podpięto `required_legal_level` do walidacji wniosku i danych mapy; poziom działu prawnego liczony jest z aktywnego dyrektora roli `legal`.
- `public/legal.php`, `templates/views/legal/main.php`, `assets/js/legal.js`, `assets/css/legal.css` — dodano grupę regionów blokowanych poziomem prawnym i przeniesiono komunikaty flash do JS modułu.
- `admin/legal.php`, `templates/views/admin/legal/main.php`, `assets/js/admin_legal.js`, `admin/partials/footer.php`, `assets/css/admin.css` — admin może ustawiać wymagany poziom prawny regionu; potwierdzenia przeniesiono z inline JS.
- `src/WorldMap.php`, `assets/js/world_map.js`, `assets/css/map.css`, `lang/pl/map.php` — mapa rozróżnia status `legal_locked` i pokazuje osobny komunikat blokady prawnej.
- `src/Tick/LegalSection.php` — usunięto emoji z ikon powiadomień działu prawnego.
- `DZIAL_PRAWNY_P1_STATUS.md` — zaktualizowano status wdrożenia P1/P2 po audycie kodu.
- `tests/Integration/LegalServiceTest.php`, `tests/Integration/LegalMapPermitDataTest.php` — dodano testy blokady wymaganym poziomem działu prawnego.

### 2026-06-03 — Logowanie zapamiętywane na 30 dni
- `public/login.php` — podpięto istniejący mechanizm remember-me pod aktywny ekran `/login`, dodano checkbox i auto-logowanie z cookie.
- `login.php` — ujednolicono rootową kopię formularza logowania z aktywnym ekranem `/login`.
- `lang/pl/auth.php` — dodano tekst checkboxa logowania.
- `assets/css/auth.css` — dopasowano odstęp checkboxa na ekranie logowania.
