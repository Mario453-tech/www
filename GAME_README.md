## Changelog

### 2026-06-12 - Ochrona P2: huby i rurociągi

**Hotfix po wdrożeniu P2** — domknięto błędy ochrony hubów/rurociągów:
- `public/protection.php` — ochrona huba może być wykupiona tylko przez właściciela huba. Najemca nie zapłaci już za ochronę, której tick nie zastosuje.
- `src/ProtectionService.php` — dodano `getActiveProtections()` do batchowego pobierania aktywnych ochron wraz z efektami.
- `public/logistics.php` — lista ochron transportu drogowego, hubów i rurociągów używa batchowych odczytów zamiast zapytania per element.
- `src/Tick/PipelineSection.php`, `src/HubIncidentService.php`, `src/Tick/WellHubSection.php` — tick prefetchuje ochrony hubów i rurociągów oraz loguje `protection_applied_to_incident` z prawidłowym `protection_option_id`.
- `assets/js/protection.js` — brak poprawnego celu w modalu pokazuje błąd zamiast cicho ignorować kliknięcie.
- Dodatkowy przegląd kodu: sekcja ochrony hubów pokazuje tylko huby własne, zgodnie z walidacją backendu; `WellPipelineService::getPlayerPipelines()` zwraca także rurociągi wylotowe `hub -> magazyn` (`well_id=0`) i oznacza je jako operacyjne, gdy hub działa.
- Testy: Unit 28/28, Integration 200/200, MySQL 118/118.

**Rozszerzenie uniwersalnego modułu ochrony na huby logistyczne i rurociągi** — ten sam silnik (`ProtectionService`), nowe cele. Gracz wykupuje ochronę huba lub rurociągu w panelu logistyki; aktywna ochrona zmniejsza ryzyko incydentów odpowiedniego typu. Architektura bez zmian — tylko nowe `target_type` + `context` i wpięcie efektów w istniejące silniki incydentów.

- `src/Protection/ProtectionSchema.php` — seed 2 nowych opcji: Ochrona huba (`hub_security`, 120 000 PLN/60 min, `target_type='hub'`, `context='hub_guard'`, mnożniki uszkodzenia sprzętu/wycieku/przeciążenia) i Monitoring rurociągu (`pipeline_monitor`, 30 000 PLN/h, 120 min, `target_type='pipeline'`, `context='pipeline_guard'`, mnożnik awarii). Seed uogólniony — `target_type`/`context` per opcja.
- `src/HubIncidentService.php` — `processTick()` przyjmuje `?ProtectionService`; mapa `PROTECTION_EFFECT_TO_TYPE` przekłada efekty na mnożniki per typ incydentu (`<typ>_risk_mult`), nakładane na szansę każdego typu osobno (silnik niezależnych szans — bez renormalizacji). Incydent pod ochroną → `protection_applied_to_incident`.
- `src/Tick/PipelineSection.php` — `rollPipelineIncident()` mnoży szansę wszystkich poziomów przez `pipeline_incident_risk_mult` aktywnej ochrony danego odcinka (`well_pipelines.id`).
- `src/Tick/WellHubSection.php` + `WellLoopSection.php` — przekazanie `ProtectionService` do finalizacji hubów (`finalizeHubTicks`).
- `src/Tick/PlayersSection.php` — jedna instancja `ProtectionService` na gracza, współdzielona przez rurociągi/huby/transport drogowy (wygasanie raz na gracza).
- `public/protection.php` — endpoint uogólniony: parametr `target` (road/hub/pipeline) + `target_id` z walidacją własności per typ (odwiert ciężarówkowy / hub właściciel-lub-najemca / rurociąg gracza).
- `public/logistics.php` + `templates/views/logistics/main.php` — sekcje „Ochrona hubów" i „Ochrona rurociągów" (reużywalna closure renderująca tabelę + modal dla dowolnego typu celu); generyczny formatter opisów efektów.
- `assets/js/protection.js` — generyczna obsługa 3 modali i typów celu (`data-target` + `data-target-id`).
- `lang/pl/protection.php` — klucze sekcji/kolumn/ryzyk per typ; disclaimer i błąd celu uogólnione.
- `tests/Integration/ProtectionServiceTest.php` — 2 nowe testy (seed hubów/rurociągów + aktywacja niezależnych celów). Integration 199/199, Unit 28/28.

### 2026-06-12 - Ochrona: domknięcie kontekstu, duplikatów i czasu DB

- `src/ProtectionService.php` — aktywacja może wymagać konkretnego `context` (P1: `road_transport_guard`), używa czasu bazy danych dla startu/końca/logów i zwraca czytelny błąd przy kolizji aktywnej ochrony.
- `src/Protection/ProtectionSchema.php` — dodano twardą blokadę jednej aktywnej ochrony na `player + target_type + target_id + context`; MySQL używa generowanej kolumny `active_guard`, SQLite częściowego unikalnego indeksu.
- `public/protection.php` — endpoint gracza wymusza kontekst `road_transport_guard`, więc POST-em nie da się uruchomić innego kontekstu dla transportu drogowego.
- `src/RoadTransportService.php` — historia `protection_applied_to_incident` zapisuje lekki agregat incydentów zamiast pełnej listy kursów.
- `admin/protection.php`, `templates/views/admin/protection/main.php` — panel jasno pokazuje, że P1 ochrony jest zawsze płacone gotówką; zapis wymusza `cash`.
- `tests/Integration/ProtectionServiceTest.php` — dodano testy blokady złego kontekstu i duplikatu aktywnej ochrony na poziomie schematu.

### 2026-06-12 - Ochrona: uniwersalny moduł + podpięcie transportu drogowego + panel admina

**Nowy, konfigurowalny moduł ochrony aktywów (`ProtectionService`) — opcje ochrony i ich efekty są definiowane w bazie i panelu admina, nie w kodzie.** Gracz wykupuje ochronę na cel (P1: odwiert z transportem ciężarówkami) na określony czas; aktywna ochrona zmniejsza ryzyko kradzieży, napadu i sabotażu kursów drogowych. Architektura gotowa pod kolejne cele (odwierty, huby, rurociągi) — moduł podaje tylko `target_type` + `context` i pyta o efekty. Brief: `BRIEF_UNIWERSALNY_MODUL_OCHRONY.md`.

- `src/Protection/ProtectionSchema.php` (NOWY) — 4 tabele (`protection_options`, `protection_effects`, `active_protections`, `protection_logs`) MySQL+SQLite, idempotentny seed 3 opcji P1: Eskorta podstawowa (75 000 PLN/60 min), Konwój uzbrojony (500 000 PLN/60 min, wymaga działu prawnego 3+), Patrol dronami (50 000 PLN/h, 120 min).
- `src/ProtectionService.php` (NOWY) — silnik: `getAvailableOptions()` (z powodem blokady: wiarygodność/poziom prawny i flagą „stać/nie stać"), `quote()`, `activate()` (FTS debit + zapis + log + powiadomienie, w transakcji), `getActiveProtection()`/`getActiveEffects()` (mnożniki przycinane do [0.05, 1.0] — ochrona nigdy nie zeruje ryzyka), `applyEffects()`, `cancel()`. Wygasanie leniwe po `ends_at` (bez crona). Max 1 aktywna ochrona per gracz+cel+kontekst.
- `src/RoadTransportService.php` — efekty ochrony nakładane przy rozliczaniu kursów: mnożniki na wagach typów incydentów (`w'=w*mult`) + korekta łącznej szansy (`sum(w')/sum(w)`), więc typy niechronione (awaria, blokada trasy) zachowują dokładnie bazowe prawdopodobieństwa. Incydent pod ochroną → wpis `protection_applied_to_incident`.
- `src/Tick/PlayersSection.php` + `WellRoadTripSection.php` — przekazanie `ProtectionService` do rozliczania kursów (guarded).
- `src/FinancialTransactionService.php` — nowy typ `TYPE_PROTECTION = 'protection'`; `src/WalletConfig.php` — `protection` → `POOL_CASH` (P1 tylko gotówka).
- `public/protection.php` (NOWY) — endpoint AJAX aktywacji (CSRF, rate limit, walidacja własności odwiertu i transportu `ciezarowki`).
- `public/logistics.php` + `templates/views/logistics/main.php` — sekcja „Ochrona transportu drogowego": lista odwiertów ciężarówkowych z aktywną ochroną (do kiedy) lub przyciskiem „Dodaj ochronę"; modal wyboru z kosztem, czasem i opisowymi efektami (bez mnożników).
- `assets/js/protection.js`, `assets/css/protection.css` (NOWE) — modal + potwierdzenie `confirmAction` + toast.
- `admin/protection.php` + `templates/views/admin/protection/main.php` (NOWE) — 4 zakładki: Opcje ochrony (CRUD bez usuwania — tylko wyłączenie), Efekty (upsert/usuwanie per opcja), Aktywne ochrony (anulowanie bez zwrotu), Historia. Link w nav „Transport". `assets/js/admin_protection.js` — potwierdzenia przez `confirmSubmit`.
- `lang/pl/protection.php`, `lang/pl/admin/protection.php` (NOWE) + loader `lang/pl.php`, `lang/pl/admin/nav.php`.
- `tests/Integration/ProtectionServiceTest.php` (NOWY, 11 testów) + 2 testy matematyki wag w `RoadTransportServiceTest.php`. Integration 195/195.

### 2026-06-11 - Łapówki: uniwersalny moduł + wtyczka w dziale prawnym + panel admina

**Nowy, przenośny moduł łapówek (`BriberyService`) — „gniazdko", które łatwo podpiąć pod dowolny inny moduł gry.** Łapówka pozwala graczowi zapłacić gotówką, żeby załatwić coś po cichu — z ryzykiem wpadki i kosztem dla wiarygodności firmy. Silnik liczy cenę i ryzyko z reputacji firmy, pobiera gotówkę, losuje wynik (sukces/wpadka), księguje kary reputacji i wysyła powiadomienie — wszystko w jednej transakcji. Moduł, który chce łapówek, podaje tylko: koszt odniesienia, „co przy sukcesie" i (opcjonalnie) „co przy wpadce".

- `src/BriberyService.php` (NOWY) — silnik: `quote()` (wycena: koszt + ryzyko, bez ruchu środków) i `attempt()` (próba: pobranie gotówki, losowanie, kary, powiadomienie, transakcja).
- `src/Bribery/BriberyConfig.php` (NOWY) — konfiguracja w tabeli `bribery_config` (klucz-wartość, idempotentny seed), edytowalna z panelu: szanse wpadki i mnożniki ceny per poziom reputacji (critical/low/shaky/stable/high), bazowy % kosztu, kary reputacji (sukces/wpadka), dodatkowa blokada po wpadce.
- `src/Legal/BriberyTrait.php` (NOWY) — pierwsza wtyczka: `LegalService::bribePermit()` i `bribeQuote()`. Łapówka dostępna dla wniosków `pending`/`delayed` (przyspieszenie) oraz `refused` (ominięcie cooldownu). Sukces → nadaje zezwolenie; wpadka → odmowa + dłuższy cooldown (urząd się zawziął) + alert dyrektora + incydent w historii reputacji.
- `src/FinancialTransactionService.php` — nowy typ `TYPE_BRIBE = 'bribe'` (+ `ALLOWED_TYPES`).
- `src/WalletConfig.php` — `bribe` → `POOL_CASH` (łapówka jest zawsze gotówkowa; domyka „gotówkową fazę 3").
- `public/legal.php` + `templates/views/legal/main.php` + `_bribe_button.php` (NOWY partial) — przycisk „Załatw po cichu" przy odmowie i w trakcie, z kosztem i ryzykiem; obsługa akcji `bribe_permit`.
- `assets/js/legal.js` + `assets/css/legal.css` — modal potwierdzenia łapówki (typ `danger`, ostrzeżenie o reputacji).
- `admin/bribery.php` + `templates/views/admin/bribery/main.php` (NOWE) — pełna edycja parametrów z panelu + podgląd ostatnich prób łapówek. Link w nawigacji admina (sekcja „Dział prawny").
- `lang/pl/bribery.php`, `lang/pl/admin/bribery.php` (NOWE) + wpisy w `lang/pl/legal.php`, `lang/pl/bank.php`, `lang/pl/admin/nav.php`.
- `tests/Integration/BriberyServiceTest.php` (NOWY) — 5 testów: wycena (% + mnożnik), sukces (pobranie gotówki, lekka kara), wpadka (mocna kara + alert + incydent), brak środków, moduł wyłączony.
- `AGENTS.md` §26 — instrukcja „jak podpiąć łapówkę do dowolnego modułu w 3 krokach".

### 2026-06-10 - Dział prawny: emoji w powiadomieniach ticku zamienione na ikony SVG

**`src/Tick/LegalSection.php` — usunięto emoji Unicode z powiadomień o decyzjach (łamały zasadę „ZERO emoji").** Metoda `notifyHub()` wstawiała do `director_notifications.icon` surowe emoji (`✅❌⏳⚠️`), które trafiały do bazy i nie pasowały do mapy `dirNotifIconSvg()`. Wersja dla wierceń (`buildNotification`) używała pustych ikon (fallback na domyślny okrąg).

- Oba tory powiadomień (wiercenia + huby) używają teraz spójnych identyfikatorów SVG: `granted → check`, `refused → cross`, `delayed → alert`, `no_decision → warning`. Identyfikatory są renderowane przez `dirNotifIconSvg()` w `templates/components/director_notifications.php`.
- Aktualizacja AGENTS.md §24: zaznaczono, że rozpatrywanie wniosków przez tick (`LegalSection`) i blokada zakupu bez zezwolenia (`WorldMap::regionPurchaseBlock`) **są już wdrożone** — wcześniejszy wpis „Co NIE jest w P1" był nieaktualny.

### 2026-06-10 - Bankructwo: opcje ratunkowe przez centralne API finansowe (uzupełnienie Fazy 2)

**Trzy metody w `src/Bankruptcy/OptionsTrait.php` przepięte z bezpośredniego `UPDATE players SET cash` + `logTransaction()` na `FinancialTransactionService::credit()`.** Dotychczas `logTransaction()` dodawał tylko wpis w historii bez faktycznego ruchu przez FTS — routing do właściwej puli, walidacja i atomowość były omijane.

Pula docelowa dla tych operacji — zgodnie z `WalletConfig::TYPE_TO_POOL` — to **konto bankowe** (`bank_balance`):
- `TYPE_LOAN → POOL_BANK` (kredyt ratunkowy)
- `TYPE_BANKRUPTCY_EVENT → POOL_BANK` (cięcia kosztów, inwestor ratunkowy)

Zmiany:
- `applyEmergencyLoan()` — gotówka z kredytu ratunkowego ląduje na koncie bankowym. Pola game-state (`credit_score`, `recovery_mode`, `bankruptcy_status`) zostają w osobnym UPDATE (bez zmian semantyki).
- `applyCostCuts()` — ulga gotówkowa z cięcia kosztów ląduje na koncie bankowym. Oddzielony UPDATE game-state.
- `applyRescueInvestor()` — zastrzyk gotówki od inwestora ratunkowego ląduje na koncie bankowym. Oddzielony UPDATE game-state.
- We wszystkich: FTS budowany przed `beginTransaction()`; przy niepowodzeniu `credit()` — rollback + komunikat błędu.
- Konkurencja (`competitor_buyout` w `EventsTrait`) — **pozostawiona bez zmian** (clamping `GREATEST(0, cash - ?)` musi być zachowany).

### 2026-06-10 - TTS: koszty przez centralne API finansowe (uzupełnienie Fazy 2)

**Moduł techniczny (TTS) przepięty z bezpośredniego `UPDATE players SET cash` na `FinancialTransactionService::debit()`.** Wcześniej koszty TTS schodziły bezpośrednim UPDATE, a `logTransaction()` dorzucał tylko wpis w historii bez faktycznego ruchu przez FTS (routing i walidacja salda omijane). Pula się nie zmienia — `hr_fee` i `tts_fee` w `WalletConfig::TYPE_TO_POOL` → gotówka, tak jak dotychczas. Zysk: jeden spójny tor ruchu środków (walidacja salda + atomowy wpis w `bank_transactions`).

- `src/TTS/StaffTrait.php` — pierwsza pensja przy zatrudnieniu (`hireEngineer`) przez `debit(TYPE_HR_FEE)`. Przy braku środków: rollback + komunikat `no_funds`.
- `src/TTS/ProceduresTrait.php` — ulepszenie procedur BHP (`upgradeProcedures`) oraz przegląd/naprawa integralności (`repairProcedureIntegrity`) przez `debit(TYPE_TTS_FEE)`. Wcześniej `logTransaction` był wołany PO `commit()` (poza transakcją) — teraz ruch i wpis są w jednej transakcji.
- `src/TTS/TasksTrait.php` — koszt zadania technicznego (`startTask`) przez `debit(TYPE_TTS_FEE)`.
- We wszystkich: FTS budowany przed `beginTransaction()`, aby setup schematu nie był pominięty w otwartej transakcji.

### 2026-06-10 - Logistyka: koszt optymalizatora przez FTS + audit trail (uzupełnienie Fazy 2)

**Routing Fazy 2 (`WalletConfig::TYPE_TO_POOL`) jest już aktywny w `FinancialTransactionService::moveFunds()` od commita „Faza 2: aktywacja routingu pul portfela".** To uzupełnienie domyka jedyną ścieżkę kosztową, która omijała centralne API finansowe.

- `src/LogisticsService.php` — koszt uruchomienia optymalizatora transportu (1500–5000 PLN) schodził wcześniej bezpośrednim `UPDATE players SET cash`, bez wpisu w historii operacji. Teraz pobierany przez `FinancialTransactionService::debit(..., TYPE_LOGISTICS_FEE)`: gotówka schodzi tak samo, ale powstaje wpis w `bank_transactions`, więc koszt jest widoczny w historii. FTS budowany przed `beginTransaction()`, aby setup schematu nie został pominięty w otwartej transakcji; przy braku środków zwracany jest dotychczasowy komunikat błędu i rollback.
- `src/FinancialTransactionService.php` — nowy typ `TYPE_LOGISTICS_FEE = 'logistics_fee'` (dodany też do `ALLOWED_TYPES`).
- `src/WalletConfig.php` — `logistics_fee` → `POOL_CASH` w `TYPE_TO_POOL`; zaktualizowano nieaktualny komentarz nagłówka (routing Fazy 2 jest aktywny, nie „do przeniesienia").
- `lang/pl/bank.php` — etykieta `bank.account.type.logistics_fee` = „Optymalizacja transportu" (wyświetlana w historii operacji).
- `lang/pl/logistics.php` — opis transakcji `logistics.tx_optimize` = „Optymalizacja transportu (tryb: :mode)".
- `tests/Integration/FinancialTransactionServiceTest.php` — nowy test potwierdzający, że `logistics_fee` schodzi z gotówki, a konto bankowe pozostaje bez zmian.

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

**Faza 2 (routing) — AKTYWNA: `FinancialTransactionService::moveFunds()` czyta `WalletConfig::TYPE_TO_POOL` i kieruje przychody na konto bankowe, koszty na gotówkę. Faza 3 (5 zastosowań gotówki) — nadal zaplanowana w `CASH_ONLY_TYPES`, nieaktywna.**

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
