# OilCorp → Laravel: audyt kodu, stan częściowy

**Stan:** 4 listopada 2026 · repozytorium `Mario453-tech/www` · gałąź `feature/oilcorp-laravel-audit-baseline` · HEAD `09b07a97195836698e600215bb19013559aabf9a`.

> **To jeden roboczy dokument ustaleń, nie raport końcowy ani zgoda na migrację.** Nie testowano produkcji, nie wdrażano zmian, nie wykonywano `full_wipe`, CRON-u ani migracji SQL na bazie graczy. Znaleziska statyczne, hipotezy i test izolowany są rozdzielone. Szczegółowe wcześniejsze dowody pozostają w dokumentach `03_CRITICAL_FILES_REVIEW_A.md` oraz `07_EVIDENCE_ADDENDUM_B.md`–`15_HANDOFF_FOR_NEXT_AI.md`; ten plik zbiera późniejsze ustalenia i 874 karty z bieżącej kontynuacji w aneksie na końcu. **Poprzednią partię 20 przerwał limit modelu przy 10/20; trzy jej brakujące pliki odczytano później, ale pozostałych siedmiu nie zaliczono. Kolejna partia GPT objęła 20/20 arkuszy CSS; następnie bezpośrednio odczytano dalszych 13/13 CSS. Trzy dokumenty historyczne przeczytano w całości osobno przez delegowany model GPT; ich karty zweryfikowano względem HEAD.**

## 1. Zakres, metodologia i stan dowodów

| Miara | Potwierdzony stan | Czego nie oznacza |
|---|---:|---|
| Śledzone ścieżki na HEAD | 5153: 435 backend, 518 frontend/mobile, 4200 pozostałych | Pełnego audytu wszystkich plików |
| Przypisanie w roboczym ledgerze | 5153/5153 bez luk | Zakończenia przeglądu |
| Karty `SOURCE_READ_DEPENDENCIES_OPEN` | 1605: backend 435, frontend/mobile 511, pozostałe 659 | Zamkniętych zależności, uprawnień i testów runtime |
| Nowe, zweryfikowane karty tej kontynuacji | 874 unikalne (861 poprzednio oraz 13 ponownie sprawdzonych teraz) | Potwierdzonego działania w aplikacji |
| Zaległa partia 118 szablonów | 118 kart odczytu | Testu renderowania/E2E |
| Zaległa partia 383 „pozostałych” | 340 odczytów, 43 klasyfikacje, 0 bez właściwej karty w tej podgrupie | Że skany regexowe są pełnym odczytem semantycznym |

W bieżących 874 kartach jest 269 z 271 śledzonych ścieżek `tests/`; jedyne niewpisane to duży `tests/ci-schema.sql` i `tests/error_log` (logu nie kopiować do raportu). Dlatego partia 37 objęła 20 plików JS bez nowych plików testowych; odczyt testu lub mapa strukturalna nie oznaczają jego uruchomienia. Dla 77 testów i 47 CSS istnieją **automatyczne mapy strukturalne**, nie ocena skuteczności testów ani wyglądu. Karty binariów, cache, logów i artefaktów PHPStan są klasyfikacją metadanych, nie odczytem treści źródła; historyczny inwentarz `02_ALL_TRACKED_FILES.tsv` został osobno zamknięty po walidacji całej struktury (§6.16). W pierwotnym ledgerze backendu jest 7 wierszy odrzuconych przez walidator; poprawne późniejsze karty obejmują te ścieżki, ale błędne wpisy należy wyjaśnić. Wyraźne statusy i kryteria: `06_FILE_BY_FILE_AUDIT_PROTOCOL.md`.

**Porównanie baz:** stary `02_ALL_TRACKED_FILES.tsv` ma 5133 ścieżki z `main` @ `7521a97a...`; obecny HEAD ma 20 dodatkowych **śledzonych** plików, wszystkie w `docs/laravel-migration/00…15`. Nie ma usuniętych ścieżek ani zmienionych blob SHA w 5133 wspólnych plikach. Ten dokument roboczy nie jest częścią tego licznika. Statusy starego indeksu: 5031 `NOT_REVIEWED`, 33 `CRITICAL_STATIC_REVIEW`, 34 `ADMIN_CONTROLLER_STATIC_SCAN`, 35 `ADMIN_VIEW_STATIC_SCAN`; nie wolno przenosić ich na `RUNTIME_TEST_PASS`.

**Interpretacja:** „potwierdzone kodem” znaczy statyczny ślad źródłowy, „odtworzone izolowanie” znaczy test w odseparowanym Node z mockami, a „hipoteza” wymaga wskazanego testu. Priorytet P0/P1 nie jest dowodem wykorzystania podatności. Wszystkie SHA w aneksie odnoszą się do powyższego HEAD.

## 2. Pilne bezpieczeństwo i ochrona danych

| Priorytet / stan | Dowód | Skutek i działanie |
|---|---|---|
| **P1, warunkowy redirect poza domenę** | `public/language.php:25–29` | Kontrola `redirect` wyklucza `//`, ale nie `/\host`. Parser WHATWG (sprawdzony w Node) interpretuje taki `Location` jako inny host. Zmiana języka wymaga POST i ważnego CSRF; nie wykonano żądania HTTP ani testu w przeglądarce. W migracji akceptować wyłącznie bezpieczne ścieżki lokalne po kanonikalizacji. |
| **P0, poświadczenia śledzone w Git** | `winscp_sync.txt`, blob `8a95e0bb58a8736bf4bd95db59fc0f7f768c3f46` | Plik zawierał dane uwierzytelniające **[REDACTED]**. Właściciel powinien prywatnie zbadać ważność i historię Git, zmienić/unieważnić dostęp i zbadać użycie. Nie sprawdzono, czy poświadczenia nadal działają; `.gitignore` nie usuwa historii. |
| **P0, publikacja logów** | `.github/workflows/sync-logs.yml:18–44`, wcześniejsze raporty 03/07/15 | Workflow według kodu okresowo pobiera log produkcyjny i commit-uje go; odpowiedź błędnego HTTP może trafić do logu CI. Wstrzymać publikację i zbadać zawartość prywatnie. Nie sprawdzono aktualnego stanu Actions ani treści logów. |
| **P0/P1, log w katalogu publicznym** | `public/error_log`, blob `e1a074b3d88c9f62720533732e822401b3e02239`; `.htaccess` | Plik jest śledzony, brak reguły blokującej dokładnie tę nazwę w analizowanym `.htaccess`. Dostępność przez HTTP na produkcji **niepotwierdzona**. Odizolować plik i zbadać ekspozycję bez publikowania treści. |
| **P0, reset GM** | `admin/gm_tools.php:361–444`, raport 15 | `full_wipe` czyści wszystkie tabele poza allowlistą, więc nowe tabele Laravel mogą być objęte resetem; `TRUNCATE` nie jest zwykłym atomowym rollbackiem. Nie wykonywać na produkcji. |
| **P1, diagnostyczny GET** | `tests/check_trends.php:74–85`, blob `19ef84315fa7d848542b597b896862274feeee77` | `?activate=1` wywołuje `activateTrend()` bez widocznego loginu/CSRF w tym pliku. HTTP reachability niepotwierdzone. Odizolować diagnostykę albo użyć admin POST+CSRF; nie testować na produkcji. |
| **P0 warunkowo, niebezpieczna konfiguracja testów DB** | `tests/MySqlIntegration/MySqlIntegrationTestCase.php:11–25,49–76`; testy `MySqlIncidentOngoingDropAutoRepairTest`, `MySqlIncidentImmunityDeltaHoursTest`, `MySqlWellShopTest`, `MySqlHubIncidentServiceTest`, `MySqlPipelineHseIntegrationTest` | Wspólne `setUp()` łączy się przez `config/database.php` i **przed testem wykonuje DELETE po losowym ID**; nie sprawdza, czy to dedykowana baza. Przy wskazaniu produkcyjnej DB istnieje ryzyko usunięcia danych. Odmienny `TransactionRaceFixtures.php:16–28` chroni tylko testy go używające. Nie uruchamiać całego PHPUnit bez fizycznie odizolowanego DSN i globalnej bramki nazwy/hosta DB. |
| **P0 warunkowo, skutki globalne testów** | `MySqlEmployeeRoleEffectServiceTest.php:25,84`, `MySqlEmployeeStateServiceTest.php:22,56,87`, `MySqlBankNegotiationOwnershipTest.php:108–132`; `src/EmployeeSystemBootstrap.php:158–446` | Po niechronionym połączeniu do DB testy `EmployeeSystemBootstrap::ensure()` mogą wykonać `CREATE TABLE`, `ALTER TABLE` i `UPDATE` danych konfiguracyjnych. Test negocjacji przełącza globalne `feature_strike_effects`, przywraca w `finally`, ale twarde przerwanie może zostawić zmianę. Nie uruchamiać na bazie produkcyjnej; ten zakres nie jest ograniczony do losowych ID testowych. |
| **P0 warunkowo, dodatkowe globalne skutki testów MySQL** | `tests/MySqlIntegration/MySqlMarineDeliverySectionTest.php:180–212`, `MySqlRecruitmentFlowTest.php:322–370`, `MySqlRoadTransportServiceTest.php:263–296`, `MySqlEmployeeHrConcurrencyTest.php:16–39`, `MySqlPortSectionTest.php:83–99`; `src/Tick/PortSection.php:248–279` | Test „brak portu” robi `DELETE FROM ports WHERE region_id=997` bez zakresu testowych ID. Rekrutacja, transport drogowy i testy wyścigów HR przełączają globalną konfigurację; przywrócenie w `finally`/`tearDown` nie chroni po twardym przerwaniu. Nawet test pojedynczego gracza wywołuje `refreshPortStatuses()` z globalnym `UPDATE ports`, który może zmienić porty innych regionów. **Statyczny zakres SQL; skutków runtime nie sprawdzono.** Izolacja bazy jest warunkiem koniecznym przed uruchomieniem. |
| **P1, różne definicje tabeli limitera czatu** | `src/ChatBootstrap.php:186–188`; `src/ChatLimiterMigrationService.php:15–25` | Pierwszy bootstrap tworzy `chat_request_limits` bez indeksu `started_at`, a późniejsze `CREATE TABLE IF NOT EXISTS` z tym indeksem nie modyfikuje już istniejącej tabeli. `apply()` nie weryfikuje tego indeksu. Sprawdzić schemat na izolowanej DB i wprowadzić idempotentne DDL; planów zapytań nie mierzono. |
| **P1, polityka finansowa: zapis nieatomowy** | `src/FinancePolicyService.php:211–278,285–369` | Upsert ustawień poprzedza wpisy decyzji bez jawnej transakcji; przy błędzie audytu ustawienia mogą zostać zapisane mimo zwróconego błędu lub pozornie nowego wyniku. Sprawdzenie cooldownu nie blokuje równoległych żądań. **Scenariusz statyczny**, wymaga testu izolowanego MySQL i transakcji/lockingu. |
| **P1, konstruktor finansów modyfikuje historię** | `src/FinanceService.php:8–45`; `tests/MySqlIntegration/MySqlFinanceDashboardReadTest.php:15–35` | Domyślny konstruktor uruchamia DDL i globalne UPDATE finance_logs, traktując wszystkie delivered_bbl=0 przy bbl_produced>0 jako brak wartości. Test read-only zaczyna liczyć write counters dopiero po konstrukcji domyślnej. Strony public/admin finance używają false; sprawdzić inne ścieżki i prawdziwe znaczenie zerowych dostaw. Bez migracji danych na produkcji. |
| **P1, zajęcie komornicze może przekroczyć dług** | `src/BailiffService.php:172–229,233–281`; `tests/MySqlIntegration/MySqlBailiffSeizureClearsLoanTest.php:79–110` | Etap 2 debituje 30% gotówki, etap 3 zabiera 50% magazynu bez cap na remaining_amount; redukcja długu używa GREATEST(0,...), co może ukryć nadpłatę. Test sprawdza status i nieujemny dług, nie wysokość zajęcia. Brak jednej transakcji dla wszystkich operacji. **Statyczne ryzyko, bez uruchomienia MySQL**. |
| **P1, harmonogram rat może pobrać za dużo lub ponownie** | `src/LoanRepository.php:25–130`; `src/Bank/RepaymentTrait.php:32–183` | Lista wymagalnych loans powstaje przed transakcją; przy pobraniu nie weryfikuje się ponownie statusu/terminu i debituje pełną installment_amount, nawet gdy remaining_amount jest mniejszy. Przy równoległym ticku/spłacie ręcznej możliwy podwójny debet, zaś GREATEST(0,...) maskuje nadpłatę w długu. **Wymaga izolowanego testu współbieżnego**. |
| **P1, wyścigi oferty kredytowej** | `src/Bank/ApplicationTrait.php:15–181,370–530`; `tests/MySqlIntegration/MySqlLoanDecisionClaimTest.php:46–81` | Dwa równoległe POST-y mogą minąć kontrolę istniejącego wniosku; rejectLoanOffer nie warunkuje UPDATE aktualnym statusem, więc może nadpisać accepted. W ścieżce expired status jest ustawiany, ale throw wywołuje rollback. Test claim obejmuje jedynie sekwencyjne ponowienie, nie te scenariusze. |
| **P1, konstruktor repozytorium statystyk może usuwać historię** | `src/Tick/TickStatsRepository.php:14–101`; `src/Tick/TickCoordinator.php:281–314,450–459` | ensureSchema na MySQL przed UNIQUE usuwa starsze duplikaty tick_stats przez DELETE JOIN i może wykonać DDL przy konstrukcji także na ścieżce odczytu/retencji. Flaga schemaEnsured ustawiana przed sprawdzeniem inTransaction, więc nieudana/pominięta próba nie jest retry w tym procesie. **Nie uruchamiano SQL**; wymagany osobny plan migracji/backup/inspekcja indeksów. |
| **P1, wynik ticku nie gwarantuje zapisu metadanych** | `src/Tick/TickEngine.php:137–146`; `src/Tick/TickModuleConfigRepository.php:99–125`; `src/Tick/TickStatsRepository.php:110–170` | Po sukcesie modułu błąd markFinished jest tylko logowany; status TickRunResult nadal success. Config UPDATE i log INSERT nie są wspólną transakcją; stats używają INSERT IGNORE bez kontroli rowCount. To statyczna luka obserwowalności/ponowienia, nie wynik uruchomionego ticku. |
| **P2, retencja incydentów niezależna od config** | `src/Tick/TickCoordinator.php:321–472` | Pomimo odczytu incident_retention_days, well_incidents, failure_log i industrial_disasters czyszczone są stałym oknem 3 dni; tick_stats i logi modułów mają okno 2 dni. Przy migracji trzeba określić oczekiwaną politykę retencji przed dopuszczeniem cleanup. |
| **P1, krytyczny moduł HR ma politykę CONTINUE** | `src/Tick/TickModuleCatalog.php:8–18`; `src/Tick/Modules/EmployeesModule.php:13–66`; `src/Tick/TickRunResult.php:46–65` | Katalog wymusza uruchamianie employees nawet gdy disabled, ale wyjątek samego modułu daje wynik partial i pozwala kontynuować tick/wyczyścić flagę in-progress. Test TickHrWiring sprawdza tylko isCritical i interwał, nie ten kontrakt. **Statyczna rozbieżność polityk**, do testu na izolowanym ticku. |
| **P2, limity modułów i błędy per-item nie są egzekwowane jednolicie** | `src/Tick/Modules/BlackMarketModule.php:38–56`; `src/Tick/Modules/ContractsModule.php:37–48`; `src/Tick/Modules/B2BContractsModule.php:36–52`; `src/Tick/Modules/EmployeesModule.php:37–59` | BlackMarket pobiera pełną listę uprawnionych graczy bez ctx.moduleLimit, Contracts nie przekazuje limitu do serwisu; B2B rozdziela limit. Liczniki failed/errors w stats nie zmieniają sukcesu modułu. Przebadać budżet czasu, semantykę częściowych porażek i asercje migracyjne. |
| **P1, błędny stan formularza resetu hasła** | `public/reset_password.php:12–39`; `src/Auth.php:693–718,860–887`; `templates/views/public/reset_password/main.php:7–29` | verifyResetToken zwraca null dla nieważnego tokenu, ale kontroler sprawdza `!== false`, więc formularz jest pokazywany. resetPassword zwraca tablicę z success=false, a kontroler sprawdza tylko prawdziwość tablicy — wyświetla sukces mimo niezmienionego hasła. **Jednoznaczne rozgałęzienia w kodzie; bez testu HTTP/PHP**. |
| **P1, reset hasła nie unieważnia tokenu atomowo** | `src/Auth.php:693–718,860–887` | Walidacja used=0 i aktualizacja password_hash/used=1 są oddzielnymi operacjami bez claim/lock i bez transakcji; równoległe żądania tym samym tokenem mogą przejść. Kod nie unieważnia istniejących sesji ani player_remember_tokens po zmianie hasła. Wymaga decyzji bezpieczeństwa i testu izolowanego, bez produkcyjnego resetu. |
| **P2, sesja i proxy — granice zaufania** | `src/Auth.php:18–71,442–460`; `src/Security.php:10–39`; `src/CSRF.php:5–20` | login ustawia sesję przed UPDATE last_login_at, który może rzucić i zwrócić false mimo zalogowania; session_regenerate_id(false) nie jest kontrolowane. HTTPS ufa X-Forwarded-Proto bez weryfikacji proxy w tej klasie; CSPRNG failure przechodzi na słabszy fallback CSRF. Część ryzyk zależy od konfiguracji hostingu i wyjątków, których nie symulowano. |
| **P1, aktualizacja wynagrodzenia wymaga transakcji callera** | `src/HR/EmployeeCompensationService.php:16–44,120–143`; `tests/Integration/EmployeeCompensationAtomicityTest.php:8–39` | setSalary zapisuje kilka źródeł/satysfakcję/kontrakt bez własnej transakcji; test rollback jawnie rozpoczyna ją w callerze i nie wymusza awarii pomiędzy UPDATE. Bez transakcji otaczającej błąd może pozostawić częściowo zsynchronizowane wynagrodzenia. **Warunkowy scenariusz statyczny, bez testu MySQL**. |
| **P1, purge wynajmującego może skasować dane cudzego huba** | `src/AdminPlayerDeletionService.php:90–129,140–151` | purgeOneInsideTransaction łączy ownedHubIds i rentedHubIds w hubIds, a potem usuwa po hub_id rurociągi, kursy, zadania, zdarzenia i inne rekordy. Gdy gracz był wyłącznie najemcą huba innego właściciela, te rekordy mogą należeć do tego właściciela. **Statyczny scenariusz warunkowy, usuwania nie wykonywano**. |
| **P1, pliki załączników usuwane przed COMMIT** | `src/AdminPlayerDeletionService.php:46–73,112,155–171` | Załączniki są unlinkowane wewnątrz transakcji SQL; przy późniejszym rollback rekordy wrócą, a pliki nie. Wymaga procedury kompensacyjnej/outbox i testu wyłącznie w izolacji. |
| **P2, wyścig retencji logu** | `src/AdminLogs/GameLogReader.php:99–146` | pruneOlderThan zwalnia flock starego pliku przed rename(temp,path), co tworzy okno utraty wpisu dopisanego do starego logu. fwrite sprawdza jedynie false, nie niepełny zapis. **Scenariusz statyczny, brak testu równoległego**. |
| **P2, właściciel/najemca huba — niespójny warunek** | `src/Hub/QueryTrait.php:49–65`; `src/Hub/AssignmentValidationTrait.php:29–42` | getHubForPlayer dopuszcza dostęp dla owner **lub** tenant, lecz validateAssignment odrzuca tenant, gdy player_id huba jest niezerowe i różne od jego ID. Może uniemożliwiać przypisanie odwiertu do huba widocznego jako wynajęty. **Warunkowe dla takiej relacji, bez MySQL/HTTP**. |
| **P2, konstruktor HubService modyfikuje schemat/konfigurację** | `src/HubService.php:35–41`; `src/Hub/ConfigTrait.php` | Konstruktor wywołuje ensureHubSchema i ensureAcquisitionConfigDefaults, więc utworzenie serwisu w odczytowej trasie może mieć efekty DDL/konfiguracyjne. Przed migracją trzeba rozdzielić migracje schematu od odczytu. Zakres i faktyczne SQL wymagają osobnej karty ConfigTrait. |
| **P1, sprzedaż natychmiastowa może cofnąć transakcję callera** | `src/MarketSaleService.php:21–59` | beginTransaction jest wewnątrz try; jeśli na PDO już trwa transakcja callera, wyjątek rozpoczynania trafia do catch, po czym finally rollBackuje aktywną transakcję zewnętrzną. **Warunkowy scenariusz statyczny, bez PHP/MySQL**. |
| **P2, legacy `/sell` nie respektuje ilości z formularza** | `public/form_sell.php:18–24`; `.htaccess:91`; `public/sell.php:24–45`; `src/Storage.php:83–105` | Stary formularz wysyła `amount` na `/sell`, ale ten endpoint nie czyta ilości i używa `sellAll`, które zeruje magazyn oraz wylicza przychód od całego stanu. **Dotyczy starej ścieżki, nie aktualnego formularza `/market`**: ten wywołuje `MarketSaleService::sellInstant($amount)` i odejmuje wybraną ilość (`templates/views/market/main.php:51–73`, `public/market.php:25–44`, `src/MarketSaleService.php:16–53`). Brak testu HTTP i potwierdzenia użycia starego formularza na produkcji; nie migrować ślepo starego kontraktu. |
| **P2, osobna strona ofert pokazuje nieaktualny magazyn po anulowaniu** | `public/market_offers.php:53–64,92–105`; `src/MarketOffer.php:261–310` | Serwis aktualizuje magazyn i zwraca sukces oraz komunikat, ale nie zwraca `returned_amount`; kontroler dodaje do lokalnego `storageData.used` zero, więc formularz renderowany w tym samym POST może mieć stary limit. **To nie jest utrata ropy w DB**, tylko niesynchronizowany stan widoku; `/market` po anulowaniu ponownie czyta Storage (`public/market.php:69–76`). Bez HTTP/MySQL. |
| **P2, niekanoniczny link z pilnego alertu ofert** | `templates/components/urgent_offer_alert.php:79–82`; `.htaccess:99,119–123`; `templates/views/index/main.php:51`; `public/market_offers.php:83` | Przycisk „zarządzaj” kieruje do względnego `market_offers.php`, co z `/`, `/market` i `/market-offers` rozwiązuje się na `/market_offers.php`. W śledzonym repozytorium brak takiego pliku i reguły; właściwy adres to `/market-offers`. Statyczny wniosek o błędnym celu w czystej kopii; historyczny plik na FTP/konfiguracja produkcji niezweryfikowane. |
| **P2, paginacja nieprzypisanych odwiertów zrywa pozostałe filtry logistyki** | `templates/views/logistics/sections/unassigned_wells_section.php:47–61`; `src/LogisticsPage/ViewData/OverviewAndProtectionData.php:69–83,151–162` | Linki generują od zera `?tab=logistics&unassigned_page=N`, zamiast przenosić istniejące `$_GET`. Przykładowe `incident_q`, `incident_severity`, `hub_market_q`, `road_page` zostają zgubione; inne paginatory tej strony używają `http_build_query($_GET)` z escapowaniem. Dotyczy bieżącego widoku; bez HTTP/renderu. |
| **P3, niemiecki język logistyki z polskim formatowaniem liczb** | `templates/views/logistics/partials/bootstrap.php:2–6`; `partials/client_config.php:6–10,40–62`; `assets/js/logistics_config.js:21–33`; `src/i18n.php:8–13` | `de` jest obsługiwanym locale i ustawia `EUR`, lecz `$currencyLocale` przechodzi w `pl-PL`; wartości z tego pola trafiają do konfiguracji formatowania JS. Statyczny błąd prezentacji, bez weryfikacji wyglądu w przeglądarce. |
| **P2, edytor stron nie synchronizuje routingu** | `admin/pages_editor.php:30–61,72–116`; `.htaccess:115–122` | `pagesRebuildHtaccess()` nie znajduje ani bloku do usunięcia (wzorzec wymaga dwóch spacji po `#`, aktualny ma jedną), ani kotwicy wstawienia (szuka nieobecnego komentarza „Wszystko inne public/”). Porównanie na bajtach bieżącego HEAD: zero dopasowań, więc przy tym pliku `.htaccess` zapis odtwarza te same reguły. Nowo dodana strona nie uzyskuje trasy; usunięta/dezaktywowana zachowuje starą regułę, ale kontroler odmawia treści nieaktywnej/nieobecnej. Nie wykonywano POST/SQL/FTP; konfiguracja hostingu niezweryfikowana. |
| **P2, token resetu OPcache w URL wdrożenia** | `public/opcache-reset.php:15–17,43–59`; `.github/workflows/deploy-ftp.yml:350–354` | Endpoint akceptuje token w query string, a workflow przekazuje go w URL. `hash_equals` i odmowa przy braku tokena są prawidłowe, ale URL może trafić do logów serwera/proxy/diagnostyki; brak dowodu, że logi produkcyjne rzeczywiście go zawierają. Przy migracji preferować nagłówek (już obsługiwany) i nie umieszczać tokenu w adresie. |
| **P2, reset hasła admina bez limitu próśb w aplikacji** | `admin/forgot_password.php:8–28`; `src/AdminAuth.php:415–455`; `src/Mailer.php:15–59` | Anonimowy GET wydaje token CSRF; poprawny POST wywołuje reset, który dla istniejącego konta usuwa poprzedni token, wstawia nowy i wysyła email bez widocznego cooldownu/licznika. Powtarzane żądania mogą unieważniać starsze linki i obciążać skrzynkę. Nie przeprowadzono żądań ani nie sprawdzono zewnętrznego limitera/WAF; generyczne przekierowanie nie ujawnia istnienia konta. |
| **P1 warunkowe, reset hasła admina po emailu może zmienić kilka kont** | `src/AdminAuth.php:415–420,485–508`; `tests/ci-schema.sql:30–42,143–150,2690–2693,2731–2734` | `sendPasswordReset` wybiera jedno aktywne konto (`LIMIT 1`), ale nie zapisuje jego `id`; `resetPassword` wykonuje `UPDATE admins ... WHERE email = ?` bez `is_active` i bez `rowCount`. Schemat HEAD gwarantuje unikalność `username`, **nie `email`**. Jeśli dwa konta adminów współdzielą email, jeden token może zmienić hasła obu (także nieaktywnego); jeśli email zmieniono/usunięto, możliwy sukces bez zmiany któregokolwiek. Nie stwierdzono takich kont na produkcji ani możliwości ich utworzenia przez atakującego. Sprawdzić rzeczywistą bazę w bezpieczny sposób i migrować do trwałej tożsamości `admin_id`/unikalnego emaila. §6.20. |
| **P1, reset hasła admina nie cofa sesji i zaufanych urządzeń** | `admin/reset_password.php:8–31`; `src/AdminAuth.php:469–516,331–353,367–376,650–667,699–740`; `src/SessionCookie.php:7–28` | Pomyślny reset aktualizuje hash hasła i zużywa token w transakcji, lecz nie kasuje innych sesji ani tokenów `admin_trusted_devices`. Istniejąca aktywna sesja przechodzi `isLoggedIn()`, a `requireLogin()` odnawia jej czas aktywności; ważny cookie urządzenia może utworzyć nową sesję przez `tryTrustedDevice()` bez podawania nowego hasła, jeśli konto pozostaje aktywne i nie jest zablokowane. To **statyczne ustalenie o ścieżce kodu**, nie wykonany test przejęcia. Wymaga decyzji o unieważnieniu sesji/tokenów i testu na izolowanym środowisku. |
| **P2, śledzony cache PHPUnit nie jest wykluczony przez regułę deployu** | `phpunit.mysql.xml.dist:7`; `.github/workflows/deploy-ftp.yml:203–210,251–260,289–295`; `.htaccess:71–74` | Śledzony `.phpunit.cache/result-mysql` (23559 B) jest wskazany jako cache testów MySQL, lecz obie reguły wdrożenia wykluczają `.phpunit_cache/` (podkreślenie), nie rzeczywistą ścieżkę z kropką. W trybie partial zmieniony plik przejdzie `is_excluded`; w pełnym mirrorze reguła `-X` również nie pasuje, choć zachowania lftp wobec ukrytych plików nie sprawdzono. `.htaccess` przekierowuje żądania do katalogu, jeśli rewrite działa. **Nie potwierdzono uploadu ani publicznego odczytu**; poprawić wykluczenie i zweryfikować artefakty stage/serwera bez publikowania cache. |
| **P2, nieatomowy reset polityki finansowej w panelu** | `admin/finance.php:30–39`; `admin/partials/finance_admin_actions.php:116–134,136–185` | Akcja `reset_player_policy` wykonuje dwa oddzielne DELETE bez transakcji; błąd drugiego może pozostawić pierwszy zapisany, choć widok dostanie komunikat błędu. Również `save_config` i `save_multipliers` zapisują wiele kluczy osobno. Dostęp ogranicza admin login+CSRF. **Warunkowy scenariusz statyczny, bez SQL/fault injection.** |
| **P2/P3, audyt admina best-effort** | `admin/AdminLog.php:15–43` (bajtowo identyczny z aktywnym `src/AdminLog.php`); `admin/init.php:13–14`; `admin/player_menu_save.php:20–27` | Logger przechwytuje błąd INSERT i nie zwraca statusu; zapis menu może odpowiedzieć `ok=true` bez wpisu w `admin_logs` (pozostaje próba logowania błędu). Nie twierdzić, że logi są faktycznie tracone na produkcji; sprawdzić wymóg trwałego audytu przy migracji. |
| **P2, warunkowe rozminięcie pollingu sali zarządu ze stanem rekrutacji** | `public/boardroom_status.php:17–25,50–55`; `assets/js/boardroom-dynamic.js:724–732`; `src/Tick/BankSection.php:178–190`; `public/boardroom.php:25–49,76–81` | Endpoint zgłasza `reload` wyłącznie dla przeterminowanej rekrutacji `pending`. Jeśli tick zdąży przełączyć ją na `ready` przed pierwszym odpytywaniem, nowi kandydaci nie wywołają przeładowania już otwartej strony (w JS są dane z renderu początkowego). Jeśli `pending` utrzymuje się między odpytywaniem, strona może przeładowywać się co około 15 s do czasu przetworzenia przez tick; dotyczy to także żądań innych niż widoczne tu rekrutacje dyrektorskie, bo endpoint nie stosuje filtrów widoku. Tick globalnie przetwarza `pending`, więc nie twierdzić, że pętla zawsze trwa bez końca. Brak HTTP/DB/testu przeglądarki. |
| **P1, możliwość przywrócenia sprzedanego odwiertu przez zmianę tieru** | `src/Well/SellTrait.php:156–164,220`; `src/Well/QueryTrait.php:5–17`; `src/Well/ActionsTrait.php:14–51`; `src/Tick/WellStatusHandler.php:33–45`; `src/Tick/PlayerTickProcessingTrait.php:28–45` | Sprzedaż zostawia wiersz `sold` z tym samym `player_id`. `set_tier` sprawdza własność, lecz nie status, i po zapłacie ustawia `equipment_swap`; po terminie tick zmienia zapamiętany `sold` na `active`. UI blokuje akcję, ale endpoint przyjmuje własny POST. **Łańcuch statyczny**, nie wykonano POST/ticka; wymaga izolowanego testu i blokady statusu w usłudze, także przy ponownej zmianie tieru w czasie montażu. |
| **P1, odczyt recovery może zmienić aktywa** | `public/recovery.php:9–15,41,44`; `src/Bankruptcy/StateTrait.php:10–27,31–48`; `src/Bankruptcy/EventsTrait.php:12–16,18–68,82–116` | Sam GET wywołuje getState: może ustawić `recovery_mode`, przetworzyć przeterminowane zdarzenia (włącznie z `status='seized'` odwiertu), zamknąć zdarzenie i wygenerować następne; getRecoveryOptions ponownie wywołuje getState. Skutki są warunkowe od stanu zdarzeń i bazy. Bez uruchomienia GET/SQL, nie przypisywać produkcji. |
| **P1/P2, fałszywy sukces wysyłki maila rejestracyjnego** | `public/register.php:35–51`; `src/Auth.php:78–131,219–251`; `src/Auth.php:45–53` | Konto niezweryfikowane zapisuje się przed próbą wysyłki. `sendVerificationEmail()` zwraca `false` przy błędzie, lecz kontroler ignoruje wynik i pokazuje „wysłano”; logowanie odrzuca `email_verified=0`. Token może istnieć albo nie — zależnie od momentu błędu — nie twierdzić, że zawsze go brak. Wymaga testu mocka poczty i ścieżki ponowienia; brak stwierdzonej awarii produkcyjnej. |
| **P1/P2, opłata za magazyn bez powiększenia przy niepowodzeniu usługi** | `public/upgrade_storage.php:54–87`; `src/Storage.php:117–139`; `src/Player.php:153–167`; `src/FinancialTransactionService.php:220–249` | Kontroler nie sprawdza `false` z `Storage::upgrade()` i robi commit po pomyślnym debicie. Przy błędzie samego UPDATE, który `Storage::upgrade()` przechwytuje i zamienia na `false`, możliwa opłata bez przyrostu pojemności. Samo bankructwo nie jest takim dowodem: wcześniejsze `Player::canAfford()` je blokuje; stan mógłby zmienić się pomiędzy kontrolami. Bez izolowanego testu awarii SQL. |
| **P1/P2, endpoint poziomu odwiertu niezgodny z transakcją usługi** | `public/upgrade_well.php:80–96`; `src/Well.php:74–86,121–162`; `src/Database.php:67–78`; `src/FinancialTransactionService.php:201–234` | Endpoint otwiera transakcję i wykonuje debit przez FTS na tym samym singletonie PDO, następnie `Well::upgrade()` bezwarunkowo wywołuje drugie `beginTransaction()`. Zwykły PDO nie ma tu własnej obsługi zagnieżdżenia (w przeciwieństwie do savepointów FTS); oczekiwany błąd kończy się `false` i rollbackiem zewnętrznym, nie pewną podwójną opłatą. Wymaga izolowanego testu PHP/MySQL przed stwierdzeniem objawu runtime. |
| **P2, osobna strona ofert bez PRG** | `public/market_offers.php:23–66,77–147`; `public/market.php:95–100` | `/market-offers` renderuje odpowiedź na POST bez przekierowania; po potwierdzonym ponowieniu formularza przez przeglądarkę może dojść do kolejnej próby operacji. **Nie każdy ponowiony POST podwaja skutek**: cancel/edit weryfikują status/warunki, create może się udać ponownie, jeśli nadal są zasoby i przejdą limity. `/market` używa redirectu po POST. Bez testu HTTP. |
| **P1, rynek: snapshot zawsze wpada w fallback** | `src/MarketTick.php:159–177,259–285` | getMarketSnapshot przy istniejącym market_state wywołuje niezdefiniowane `fetchPlayerSupply()`, podczas gdy w klasie istnieje `fetchPlayerSupplyFlow()`. Błąd jest złapany i metoda zwraca [], więc dane snapshotu nie powstają tą ścieżką. **Spójność nazw potwierdzona pełnym odczytem pliku; bez PHP**. |
| **P2, łapówka: konkurencyjny grant i obciążenie** | `src/Legal/BriberyTrait.php:43–95`; `src/BriberyService.php:98–161` | Eligibility permit jest sprawdzane przed transakcją, a callback aktualizuje status bez warunku wcześniejszego stanu/rowCount. Dwie jednoczesne próby mogą obie pobrać koszt, nawet gdy pierwszy grant już zakończył wniosek. Wymaga izolowanego testu współbieżności. |
| **P2, endpoint rynku ujawnia diagnostykę wyjątku** | `src/BlackMarketApi.php:44–58` | Odpowiedź GET offers przy błędzie tabeli zawiera surowe `$e->getMessage()` oraz `debug=true` dla zalogowanego gracza. Nie ujawniono żadnego realnego komunikatu; zakres ekspozycji zależy od konfiguracji błędów SQL. |
| **P1, bramka roli w LogisticsApi może być pominięta** | `src/LogisticsApi.php:34–41`; `src/init.php:123–143`; `api/internal/LogisticsApi.php` | Warunek autoryzacji używa `class_exists('BoardAccess', false)`: nie inicjuje autoloadera i gdy klasa nie została wcześniej załadowana, blok `BoardAccess::has` jest pominięty dla zalogowanego użytkownika. Adapter i init nie wymagają BoardAccess jawnie. **Warunkowy wniosek statyczny; sprawdzić przez izolowane żądanie HTTP i model uprawnień**. |
| **P1, testy tick history uruchamiają repozytorium przed shadow temp** | `tests/MySqlIntegration/MySqlTickHistoryQueryTest.php:16–21`; `tests/MySqlIntegration/MySqlTickHistoryRetentionTest.php:16–24`; `src/Tick/TickStatsRepository.php:14–101` | Oba standalone testy łączą się z config/database.php i tworzą TickStatsRepository przed założeniem TEMPORARY TABLE, więc jego ensureSchema/DELETE duplikatów może dotknąć stałych tabel. Nie uruchamiać bez odrębnej izolowanej instancji DB. |
| **P1, bootstrap morale nie ponawia błędu** | `src/HR/MoraleBootstrap.php:8–20,23–63` | `static $done=true` ustawione przed sprawdzeniem klasy Database i CREATE/ALTER; po braku klasy lub błędzie kolejne wywołanie w request nie podejmie ponownej próby. **Statycznie, bez DDL.** |
| **P2, klucz plikowego cache bez walidacji** | `src/Cache.php:12–53` | Key konkatenowany w ścieżce PHP, get używa `include`; nie znaleziono callerów Cache:: w `src`. Ryzyko traversal zależy od dostępu do klucza z niezaufanego wejścia — **niepotwierdzone**. |
| **P1, bootstrap bankructwa i bramka POST mogą fail-open** | `src/BankruptcyBootstrap.php:11–47,50–116` | Flaga done ustawiona przed DDL i aktualizacją graczy; przy błędzie bazy `isPlayerInBankruptcyMode()` zwraca `false`, więc warunkowo nie zatrzymuje ryzykownych POST. **Wymaga testu izolowanej DB.** |
| **P1, odsetki bez własnej atomowości** | `src/InterestEngine.php:23–40,46–119` | Aktualizacja kredytu i INSERT historii nie mają własnej transakcji/blokady; po wyjątku metoda milczy, więc przy autonomicznym wywołaniu możliwa niespójność lub dubel. **Zależne od callera.** |
| **P2, rozjazd efektów zdarzeń regionalnych** | `src/RegionalEventService.php:38–84`, `src/RegionalEvent/EventsTrait.php:73–81`, `src/RegionalEvent/ModifiersTrait.php:47–89` | Definicje stop_pct/tax_extra/bonus_pct nie są zapisywane w zdarzeniu; bieżące modyfikatory wynikają z severity. **Statycznie, bez ticka.** |
| **P2, bootstrap offline pomija retry** | `src/Tick/OfflineSection.php:32–50` | Flaga per identyfikator PDO powstaje przed kontrolą aktywnej transakcji i próbą DDL; kolejny konstruktor może ominąć przygotowanie schematu. **Warunkowe, bez DB.** |
| **P1, migracja portfela po błędzie nie jest ponawiana** | `src/WalletService.php:189–245` | Błąd masowego UPDATE 50/50 jest połykany, a `schemaReady` ustawiane na `true`; nieprzemigrowane wiersze mogą pozostać do końca życia danego PDO. **Warunkowo, bez DB.** |
| **P1, limit logowania tylko w sesji** | `src/RateLimiter.php:29–60,68–99` | Stan prób jest zapisany w `$_SESSION`, nie we współdzielonym magazynie. Zmiana sesji może ominąć ten konkretny limit; **nie potwierdzono przez HTTP.** |
| **P1, niespójny ledger aktualizacji gotówki** | `src/Player.php:47–108` | Błąd pobocznego wpisu FTS jest przechwycony, a aktualizacja cash może zostać zatwierdzona. **Warunkowo, bez DB.** |
| **P2, błędne potwierdzenie przyrostu bufora huba** | `src/Hub/TickPersistTrait.php:137–175` | Odczyt wolnej przestrzeni przed UPDATE LEAST nie jest blokowany; zwracana wstępna ilość może przekroczyć rzeczywisty przyrost przy współbieżności. **Hipoteza do testu dwóch połączeń.** |
| **P2, logi z nieocenzurowanymi parametrami SQL** | `src/GameLog.php:39–73,111–140,157–170` | Helpery zapisują parametry i identyfikator sesji, a fallback używa `*.fallback.log`; root `.htaccess:126–128` blokuje `*.log`, lecz retencja i ekspozycja HTTP nie były sprawdzone. **Nie kopiować logów.** |
| **P2, renegocjacja bez walidacji liczbowych** | `src/ContractService.php:669–762` | Allowlista chroni nazwy kluczy, nie wartości: ujemne `delivery_bbl` może naruszyć invariant rozliczenia (492–520). Nie znaleziono callera tej metody w `src/public/admin`; **osiągalność HTTP niepotwierdzona; seedowane opcje nie włączają renegocjacji**. |
| **P1, odczyt strony technicznej mutuje stan** | `src/TechnicalPage/ViewDataTrait.php:29–37` | Budowanie widoku wywołuje `processTick()`. Zachować tę semantykę GET świadomie lub wydzielić proces; **bez wykonania HTTP**. |
| **P2, wyścig zdarzeń negocjacji** | `src/BankNegotiation/RandomEventsTrait.php:28–56,130–181` | Kontrola cooldown i zapis zdarzenia nie mają własnej transakcji; możliwy wyścig przy równoległych wywołaniach. **Nie testowano dwóch połączeń.** |
| **P2, nazwa metody nie oznacza kontroli dostępu** | `src/Hub/PlayerQueryTrait.php:166–180` | `getRegionHubs` filtruje region/status, lecz nie owner/tenant. Caller powinien egzekwować uprawnienia; **nie dowiedziono ekspozycji danych przez HTTP**. |
| **P1, błąd migracji transportu blokuje ponowienie** | `src/TransportConfigService.php:65–217` | `schemaEnsured=true` jest ustawiane przed DDL, a wyjątki logowane bez ponowienia w tym procesie. **Nie testowano MySQL.** |
| **P1, transfer bez savepointu przy aktywnej transakcji** | `src/CashTransferService.php:118–212` | Po UPDATE kont gracza awaria wpisu FTS zwraca fail; metoda nie cofa zmian, jeśli transakcja należy do callera. **Warunkowo, bez DB.** |
| **P1, bootstrapping szkoleń może utkwić przy ≥3 duplikatach** | `src/TrainingBootstrap.php:11–25,134–160` | Migracja usuwa tylko jeden aktywny rekord z grupy, a potem dodaje UNIQUE; błąd jest połykany po ustawieniu `done=true`. **Warunkowo; nie wykonywano DDL.** |
| **P2, odczyt bankructwa z efektem ubocznym i fallbackiem** | `src/Bankruptcy/StateTrait.php:10–29` | `getState` wywołuje tick flow, a błąd zwraca `is_bankrupt=false`; UI może widzieć fałszywy stan, **nie dowodzi obejścia autoryzacji**. |
| **P1, minimalna reputacja sprzedawcy B2B nieegzekwowana** | `src/B2BContractService.php:192–210,377–541` | Parametr jest przechowywany w ofercie, ale `acceptOffer` nie sprawdza reputacji. Brak asercji dla tej reguły; **statyczne ustalenie, bez DB/HTTP**. |
| **P2, wyścig limitu otwartych ofert B2B** | `src/B2BContractService.php:175–189` | COUNT jest poza transakcją, więc dwa równoległe żądania mogą przekroczyć limit. **Hipoteza do testu MySQL.** |
| **P1, potwierdzenie dostawy morskiej przed zapisem magazynu** | `src/Tick/PortSection.php:154–241`, `src/Tick/PlayerTickProcessingTrait.php:320–359` | Bez transakcji nadrzędnej wpis kolejki i marine_delivery zostaje zatwierdzony przed osobnym UPDATE storage. Awaria w przerwie może utracić kredyt ropy. **Statycznie, bez testu crash.** |
| **P1, odczyt techniczny uzgadnia statusy w DB** | `src/TechnicalPage/DataTrait.php:105–155,256–324` | Oprócz ticka strona techniczna wykonuje UPDATE wells i industrial_disasters podczas budowy widoku; **statycznie, bez HTTP**. |
| **P1, konstruktor HubService modyfikuje schemat i rynek** | `src/Hub/ConfigTrait.php:12–130`, `src/HubService.php:35–41` | Nowa instancja może wykonać DDL i masowy UPDATE hubów systemowych nawet przy odczycie WWW. **Bez wykonania DB/HTTP.** |
| **P1, odczyt stanu bankructwa wyzwala egzekucję** | `src/Bankruptcy/EventsTrait.php:12–80`, `src/Bankruptcy/StateTrait.php:10–24` | `getState` może spowodować zajęcie odwiertu, karę lub zmianę credit_score po terminie. **Statycznie, bez tick/HTTP.** |
| **P1 warunkowo, straż bankructwa przy naprawie incydentu API** | `api/v1/technical/repair_incident.php:18–37`; `api/v1/_bootstrap.php:18–42`; `src/IncidentService.php:3–6`; `src/Incident/RepairDataTrait.php:18–28,30–53` | Endpoint bierze gracza z Bearer, ale metoda sprawdza bankructwo tylko gdy `class_exists('Player', false)`; w widocznym standardowym łańcuchu require brak jawnego `Player.php`, a `false` nie uruchamia autoloadera. Po kontroli `i18n.php`, pozostałych traitów `Incident` i `composer.json`: w standardowym żądaniu na gałęzi API brak jawnego załadowania `Player`; nawet opcjonalny classmap autoloadera nie uruchomi się przez `false`. Gracz `status=active`, `recovery_mode=1` przechodzi `ApiAuth.php:46`, choć `Player::isBankrupt():137–139` zablokowałby go po załadowaniu. Własność incydentu nadal jest sprawdzana przez `player_id` w SQL. Potwierdzić w świeżym izolowanym procesie PHP/DB; produkcji nie testowano. |
| **P2, powiadomienia: aktualizacja bez wspólnej transakcji i rozjazd licznika** | `api/notifications/mark-read.php:23–35`; `src/DirectorNotificationService.php:341–347,373–422,442–454`; `templates/components/activity_center.php:27–40`; `assets/js/director_notifications.js:67–79` | Pojedynczy odczyt robi UPDATE i osobny INSERT historii; błąd historii może zostawić zmianę odczytu mimo 500. Masowy odczyt obejmuje wygasłe, których getUnread nie pokazuje. Widok renderuje najwyżej 5, ale pokazuje licznik wszystkich; JS po kliknięciu liczy tylko pozostałe elementy DOM. Skutki DB/UI nie były odtwarzane. |
| **P1, rola spoza allowlisty odpina technika i rozjeżdża stan obsady** | `api/internal/WellStaffApi.php:1–6`, `src/WellStaffApi.php:56–69,95–101`, `src/WellStaffService.php:170–196,209–255,358–374` | Aktywny adapter AJAX po sesji i CSRF przekazuje każdą niepustą rolę do serwisu. Dla roli różnej od `operator`, serwis próbuje zamknąć przypisanie tej roli, ale osobno zeruje `wells.technician_id` i zwraca sukces; dla nieznanej roli może to rozjechać cache i tabelę przypisań. Dodatkowo prawidłowe odpięcie personelu przy `status=servicing` może nadpisać ten status na `no_operator`/`no_technician`, a zadanie techniczne odblokowuje go tylko gdy nadal jest `servicing`. **Ślad statyczny; bez MySQL/HTTP** (§6.19). |
| **P1, płatność za zmianę warstwy przed niezweryfikowanym UPDATE** | `src/GeologicalLayerService.php:245–345` | Po kontroli statusu poza transakcją płatność następuje przed UPDATE, który nie sprawdza statusu/rowCount; możliwa opłata przy równoległym zajęciu odwiertu. **Warunkowo, bez testu race.** |
| **P1, warunkowy podwójny dispatch kursu drogowego** | `src/Tick/WellProductionHandler.php:475–509`, `src/RoadTransportService.php:295–354` | Przy nakładających się tickach road buffer z nieblokowanego snapshotu może być wysłany dwa razy; dekrement `GREATEST(0, ...)` nie sprawdza dostępnego wolumenu. **Statycznie, bez testu race.** |
| **P1, ponowna opłata za przegląd BHP przy równoległości** | `src/TTS/ProceduresTrait.php:210–320` | Nowa integralność obliczona przed transakcją; UPDATE nie kontroluje wartości starej, więc dwa równoległe przeglądy mogą pobrać opłatę dwukrotnie. **Warunkowo, bez testu DB.** |
| **P1, połknięta awaria efektów incydentu w transakcji callera** | `src/Incident/RepairDataTrait.php:300–403` | Po błędzie applyEffects loguje i wraca bez rollbacku cudzej transakcji; możliwy częściowy commit. **Warunkowo, bez fault injection.** |
| **P1, brak historii po commicie oceny wiarygodności** | `src/CompanyCredibilityService.php:374–465` | Zmiana wyniku jest commitowana przed logChange; wyjątek zapisu historii jest połykany. **Statycznie, bez testu DB.** |
| **P1, limit rekrutacji nie jest chroniony blokadą** | `src/TTS/RecruitmentTrait.php:341–399` | Odczyt pojemności w transakcji bez blokady pozwala warunkowo na równoległe przekroczenie limitu. **Bez testu race.** |
| **P2, akcje administracyjne hubów niedostępne** | `src/HubApi.php:78–89`; `src/Auth.php` | Kontrola admina wywołuje nieistniejące w Auth metody; build/repair/toggle/rename odrzucane. **Statycznie, bez HTTP.** |
| **P1, zakup huba może wyczyścić nowy najem** | `src/HubAcquisitionService.php:129–180,205–269` | buyUsed odczytuje najem przed płatnością, ale UPDATE nie sprawdza tenant_player_id; współbieżny rent może zostać nadpisany. **Warunkowo, bez testu race.** |
| **P1, zezwolenie huba fail-open przy błędzie kolumny** | `src/HubAssignmentService.php:471–517` | SQLSTATE 42S22 / unknown column jest traktowane jak brak modułu zezwoleń, a przypisanie dopuszczane. **Statycznie, bez testu DB; warunkowe przy drift schematu.** |
| **P1, inicjalizacja ochrony zmienia historię aktywnych ochron** | `src/Protection/ProtectionSchema.php:209–264` | ensureMysqlActiveGuard anuluje starsze duplikaty active przed próbą ALTER/indeksu; wyjątki są połykane. **Statycznie, bez wykonania DDL.** |
| **P2, UI sugeruje opłatę za przypisanie huba bez naliczenia** | `src/Hub/ViewHubsTrait.php:205–233`; `assets/js/logistics_hub_wells.js:72–88`; `src/HubAssignmentService.php:80–110` | acq_access_fee w potwierdzeniu może być dodatnie, podczas gdy serwis zapisuje 0. **Statyczny przepływ, bez E2E.** |
| **P2, optymalizator transportu nie realizuje deklarowanego kryterium** | `src/LogisticsService.php:251–295` | min_cost używa łącznego kosztu, nie jednostkowego, a wybór nie porównuje bieżącej oceny mimo komentarza never downgrade. **Statycznie, bez DB.** |
| **P1, zdany egzamin może trwale pozostać bez certyfikatu** | `src/Training/TrainingService.php:145–168,249–283` | Certyfikat jest zapisywany po commicie wyniku, a ponowna selekcja bierze tylko in_progress. **Statycznie, bez fault injection.** |
| **P0, benchmark seed usuwa całą zawartość tabel** | `tests/bench_seed.php:10–42,69–101` | Skrypt łączy się z bazą z `config/database.php`, wykonuje `DELETE FROM ... WHERE 1=1` dla listy tabel, nadpisuje globalny `well_config`, potem seeduje testowe rekordy; brak wymuszenia izolacji. **Nie uruchamiać na istniejącej bazie. Nie uruchomiono.** |
| **P1, wspólny setUp MySQL usuwa rekordy bez izolacji bazy** | `tests/MySqlIntegration/MySqlIntegrationTestCase.php:11–76,272–289` | Przed każdym testem i po nim kasuje w wielu tabelach rekordy wyznaczone z losowych ID, a następnie uruchamia konstruktor usługi; nazwa testowa ani zakres ID nie potwierdzają izolacji. **Nie uruchomiono.** |
| **P2, settlement może przekroczyć totalCosts** | `src/FinancialTransaction/TickSettlementTrait.php:15–25,36–55` | Przy `sum(costs)>totalCosts` kod dopisuje do OPEX zero, lecz zachowuje większą sumę typów; na odpowiednim saldzie pobranie może przekroczyć przekazany total. **Warunkowe, bez wykonania DB.** |
| **P1, smoke API: kasowanie stałego ID przed testem** | `tests/api_smoke.php:60–89,100–140` | Skrypt kasuje rekordy gracza o stałym ID przed seedem, może tworzyć tabelę i nie ma `finally` gwarantującego cleanup. **Nie uruchamiać na nieizolowanej bazie**. |
| **P1, standalone szkolenia: globalne ustawienia programu** | `tests/test_training_engine.php:22–51,97–99,113–115,141–143,190–199` | Test modyfikuje globalne `training_programs.base_pass_rate`, a przy końcu odtwarza wartości sztywne, nie wcześniejsze. Kasuje też dane stałych ID; przerwanie może zostawić stan zmieniony. **Nie uruchamiano**. |
| **P2, most mobilny: cache bootstrapu przed DDL** | `src/MobileWebBridge.php:17–60` | `WeakMap` oznacza połączenie jako gotowe przed `CREATE TABLE`; przy błędzie DDL następne wywołanie na tym samym PDO pomija bootstrap. **Warunkowo; bez wykonania**. |
| **P2, powiadomienia TTS: deduplikacja SELECT/INSERT** | `src/TTS/NotificationsTrait.php:39–64` | Dwa równoległe wywołania mogą oba minąć sprawdzenie 10-minutowe i dodać identyczne powiadomienia. **Warunkowo, bez wyścigu runtime**. |
| **P1, odwierty: rollback cudzej transakcji w akcjach** | `src/Well/ActionsTrait.php:85–111,165–183` | W gałęziach braku odwiertu, zablokowanego statusu, limitu poziomu i braku konserwacji wywoływany jest bezwarunkowy `rollBack()`, mimo że metoda może dołączyć do aktywnej transakcji callera (`$ownTx=false`). **Warunkowo przy współdzielonym PDO; bez wykonania**. |
| **P1, skrypt diagnostyki trendów: stan przez GET bez lokalnej autoryzacji** | `tests/check_trends.php:3–29,73–85` | Plik włącza `display_errors`, wypisuje dane DB bez escapingu i na `?activate` aktywuje trend bez lokalnej bramki auth/CSRF. **Ekspozycja HTTP ścieżki nie została sprawdzona; nie wykonywano GET**. |
| **P1, ręczne skrypty ceny modyfikują rynek** | `tests/price_test.php:20–54`; `tests/test_price_detailed.php:20–54`; `tests/debug_calculation.php:9–10,52–55`; `tests/exact_debug.php:9–10,65–68` | Każdy może ustawić `market_state.current_price` i uruchomić tick ceny; to nieizolowane diagnostyki, nie bezpieczna suita testowa. **Nie uruchamiano**. |
| **P2, WellService bootstrap schematu w konstruktorze** | `src/WellService.php:38–44,72–185` | Inicjalizacja może wykonywać ALTER TABLE/UPDATE; cache oznacza połączenie jako obsłużone przed dalszymi krokami, a wczesny return dla typu status nie-ENUM pomija część kontroli kolumn. **Statycznie; bez DB**. |
| **P1, seeder lokalizacji: zbyt wczesne zwolnienie blokady** | `src/WorldLocationCatalogSeeder.php:158–222` | Gdy seeder działa w transakcji wywołującego, nazwany lock MySQL jest zwalniany w `finally` przed COMMIT tej transakcji. Drugi seeder może nie widzieć markera ani wstawionych wierszy, wywołując konflikt lub duplikację. **Warunkowo przy współbieżności; bez wykonania**. |
| **P1, endpoint wyceny odwiertu wykonuje bootstrap DDL** | `src/WellSellApi.php:9–30` | Przed rozgałęzieniem GET/POST wywołuje `addColumnIfMissing` dla `sold_at`, a błąd ignoruje; pierwszy odczyt wyceny może uruchomić zmianę schematu. **Warunkowo gdy brak kolumny; bez HTTP/DB**. |
| **P2, test tick_stats: szeroki DELETE w tearDown** | `tests/MySqlIntegration/MySqlTickStatsRepositoryTest.php:28–37` | Test usuwa wszystkie wiersze z `ran_at LIKE 2099-12-31%`, nie tylko własne rekordy, i może uruchomić DDL w konstruktorze repozytorium. Zakaz uruchamiania na nieizolowanej DB pozostaje. |
| **P2, konfiguracja odwiertów: nieodświeżony cache** | `src/Well/ConfigTrait.php:15–26,56–81` | `updateConfig()` zapisuje DB, ale nie aktualizuje `$this->config`; `cfg()` w tym samym obiekcie może nadal zwracać poprzednią wartość. **Statycznie; bez PHP/DB**. |
| **P1, definicje cookies: fałszywy sukces po błędzie zapisu** | `src/Privacy/Features/Cookies/CookieService.php:23–71`; `src/Privacy/Features/Cookies/CookieRepository.php:53–128` | Serwis nie sprawdza `create() === 0` ani `false` z `update`, `setActive`, `delete`; loguje i zgłasza sukces nawet bez potwierdzonego zapisu. **Statycznie; bez PHP/DB**. |
| **P1, aktywacja polityki a transakcja wywołującego** | `src/Privacy/PrivacyPolicyService.php:54–75` | `beginTransaction()` wewnątrz istniejącej transakcji może rzucić wyjątek; `catch` widząc `inTransaction()` może cofnąć transakcję wywołującego. **Warunkowe dla współdzielonego PDO; bez wykonania**. |
| **P2, ustawienia prywatności: cichy błąd zapisu** | `src/Privacy/PrivacySettingsService.php:34–51`; `src/Privacy/Features/Banner/BannerSettingsService.php` | `set()` przechwytuje błąd i zwraca `void`, a caller nie może potwierdzić trwałego zapisu; efekt dotyczy także komunikatu sukcesu admina. **Statycznie; bez DB**. |
| **P1, CSV zgód: potencjalne formuły i ciche ucięcie** | `src/Privacy/Features/Consents/ConsentService.php:14–44`; `src/Privacy/Features/Consents/ConsentsFeature.php:26–41` | Eksportuje username i inne dane do cytowanych komórek bez neutralizacji prefiksów formuł `=,+,-,@`; aplikacja arkuszowa może je interpretować. Pobiera wyłącznie pierwsze 10000 wierszy, lecz nie komunikuje obcięcia. **Warunkowe dla danych i klienta CSV, bez uruchomienia Excela/LibreOffice**. |
| **P2, audit prywatności best effort** | `src/Privacy/PrivacyAuditLogger.php:19–37`; `src/Privacy/Features/Banner/BannerSettingsFeature.php:27–31` | AuditLogger łapie wszystkie wyjątki i nie zwraca statusu; funkcja zapisująca ustawienia może zgłosić sukces mimo braku wpisu audytu. Nie oceniano prawnych obowiązków, tylko niezawodność śladu w kodzie. |
| **P1, przesyłanie obrazów na czacie — statyczne ryzyko** | `src/ChatApi.php:186–252,255–315,401–409`; `src/ChatService.php:493–546` | `last_index` jest nadpisany bieżącym indeksem przed sprawdzeniem kolejności, więc to sprawdzenie nie chroni uploadu. Limit 3 MiB weryfikowany dopiero przy finalizacji, a przeniesienie pliku następuje przed odmową wysyłki przez serwis (np. nieaktywny odbiorca), bez cleanup w tej ścieżce. Możliwe niepełne/porzucone pliki i obciążenie pamięci/dysku. **Nie przeprowadzono exploita ani testu HTTP**; zabezpieczyć kolejność, limit na każdym chunku, finalizację i sprzątanie na izolowanym środowisku. |

`logs/game_debug.txt` jest również śledzony (blob `653ce6fa3c35e539eac7aa8c5358a77c47f5608a`, 648981 bajtów). Oceniono **tylko metadane**, bez odczytania treści. Istniejące testy `tests/MySqlIntegration/*` wykonują operacje zapisu/usuwania i mogą korzystać z `config/database.php`; wymagają fizycznie odizolowanego DSN/testowej bazy przed uruchomieniem.

## 3. Logika domenowa i migracja danych

1. **P1, własność hubów — sprzeczność dokumentacji z kodem.** `svn_repo/LOGISTICS_HUBS_README_V2.md`, `OSTATECZNA_WERSJA_HUB_RUROCIAG.md`, `LOGISTICS_HUBS_README_UJEDNOLICONE_FINAL.md` i `LOGISTICS_HUBS_README_ulepszony.md` nazywają huby wyłącznie systemowymi i zakazują zakupu przez gracza. Obecny `src/HubAcquisitionService.php:6–13,36–112` tworzy `logistics_hubs` z `player_id` nabywcy; `src/HubApi.php:286–296` obsługuje `buy_new_hub`, a `assets/js/logistics_hub_market.js:82–85` go wywołuje. `src/Hub/AssignmentValidationTrait.php:29–42` rozróżnia właściciela, najemcę i hub nienabyty. **Ślad kodowy potwierdzony; sukces transakcji i dane produkcyjne niebadane.** Przed schematem Laravel potrzebna decyzja produktu i analiza istniejących własności.
2. **P1, czas transferu między hubami.** `LOGISTICS_HUBS_README_UJEDNOLICONE_FINAL.md:189–229` wymaga około dwóch ticków przerwy i dalszego naliczania kosztów. `src/HubAssignmentService.php:306–350` w jednej transakcji odłącza stare i od razu tworzy nowe `active`; `src/Hub/AssignmentValidationTrait.php:49–59` pomija kontrolę starego cooldownu przy `skipCurrentCheck=true`. To potwierdza natychmiastową zmianę statusu, **nie** wynik finansowy ticka. Wymaganie wymaga decyzji i testu izolowanej bazy.
3. **P1, egzamin i certyfikat — warunkowa niespójność.** `src/Training/TrainingService.php:220–260` commit-uje wynik egzaminu i poziom skilla, a potem wystawia certyfikat (`:269–282`). Błąd INSERT certyfikatu jest logowany po commicie; ponowny zwykły przebieg nie wybierze statusu innego niż `in_progress`. Test `tests/test_training_engine.php:166–187` nie wymusza awarii INSERT. Możliwy brak certyfikatu po zaliczeniu — **bez testu awarii i bez stwierdzenia jej częstości**. Potrzebna próba fault injection na sztucznej DB i decyzja transakcja/outbox/retry.
4. **P1, rekrutacja międzynarodowa.** `templates/views/technical/tabs/candidates.php` ustawia ukryte `recruitment_type=local`, zmieniając na `international` przez `onclick`. `src/TechnicalPage/ActionsTrait.php:159–183` i `src/TTS/RecruitmentTrait.php:329–339,378–389` przekazują i walidują region oraz typ osobno, dobierając okres 360–720 s lub 1440–2880 s. POST z niespójnymi polami może uzyskać krótszy czas. Droga statyczna potwierdzona, transakcja DB **nie**; wyprowadzić typ z regionu na serwerze i testować negatywny przypadek.
5. **P1, etykiety walut bez wykazanej konwersji.** `public/finance.php:101–111` (`fmtPLN`) etykietuje te same kwoty USD/PLN zależnie od locale; `templates/views/technical/tabs/{infra,safety}.php` stosują USD/EUR/PLN, a `templates/views/wells/helpers.php` zawsze PLN. Ustalić walutę bazową i regułę konwersji domenowej przed zmianą Blade, nie zmieniać wyłącznie napisu.
6. **P1/P2, rynek/tick.** Wcześniejsze raporty 10/11/15 wskazują niezdefiniowane `fetchPlayerSupply()` w `MarketTick::getMarketSnapshot()`, możliwy fallback ceny i pozorny sukces ticka, ręczny update cen bez blokady koordynatora oraz dezaktywację trendów przed walidacją `trend_id`. Wszystko wymaga ponownego przejścia zależności i testu na izolowanym MySQL; szczegóły i bloby we wskazanych raportach.
7. **P1, GM/konta/API mobile.** `admin/gm_tools.php:223–226` zapisuje `players.password`, gdy `src/Auth.php` używa `password_hash`; usuwanie gracza może przemilczeć błędy zależnych tabel. `src/ApiAuth.php` zapisuje surowe tokeny bearer, a zmiana/reset hasła w `src/Auth.php` nie wywołuje widocznego `revokeAllForPlayer`. Zaplanować zgodność istniejących kont/haszy i kontraktu mobilnego oraz test dwóch sesji; szczegóły 07–09/15.
8. **P1, hipoteza ponownego zwrotu w SQL.** `migrations/tick_bugfix_round4.sql:69–79` najpierw zwraca `build_cost` do `players.cash`, potem usuwa martwe `well_pipelines`. Przerwanie po UPDATE przed DELETE i ponowne uruchomienie **może** zwrócić kwotę dwa razy bez wspólnej atomowości. Nie wykonywano SQL; potrzebny test awarii na testowej bazie.
9. **P1, kolejność kontraktów w historycznym briefie jest sprzeczna z celem.** `svn_repo/kontrakty-dlugoterminowe-brief-rozszerzony.md:22–30` wymaga rozliczania kontraktów **po** produkcji (`players`), lecz proponuje `ContractsModule::order()=35`. Obecny `PlayersModule::order()=40`, `TickRegistry.php:120` sortuje rosnąco, a aktualny `ContractsModule::order()=90`, czego dodatkowo oczekuje `tests/Integration/ContractsModuleTest.php:38–41`. Przeniesienie liczby 35 z briefu wykonałoby kontrakty **przed** produkcją. Test modułu nie wykonuje pełnego ticku ani nie dowodzi, że produkcja faktycznie poprzedziła dostawę. Nie jest to błąd bieżącego kodu; w migracji zachować relację zależności, nie literalną liczbę. Dokument jest planem rozbudowy: reputacja, kaucja, renegocjacja i inne elementy mają już ścieżki w `ContractService.php`/`src/Contracts/ContractSchema.php`; pozostałe funkcje wymagają osobnego mapowania i decyzji.
10. **P1/P2, zgubione powiadomienie prawne po opłaconym wniosku.** `tests/Integration/LegalNotificationsTest.php:66–78` oczekuje sukcesu i pobrania opłaty nawet przy braku tabeli powiadomień. `src/LegalService.php:687–705` wywołuje `notifyDirector()` po commicie, a `:725–750` przechwytuje błąd bez mechanizmu ponownego doręczenia w tej ścieżce. **Statycznie potwierdzony kontrakt best-effort**, nie zmierzona częstość awarii. Ustalić, czy potrzebny outbox/retry, i przetestować naprawę tabeli na sztucznej DB.
11. **P1, test C3 nie jest testem współbieżności.** `tests/Integration/FinancialStateSectionTest.php:139–152` wstawia już powiększone saldo do SQLite i wykonuje jedno synchroniczne potrącenie. Aktualny `FinancialStateSection::saveCashAndTick()` deleguje do `FinancialTransactionService::settleTickCosts()` (`src/FinancialTransaction/TickSettlementTrait.php:13–59`): historia obejmuje tylko kwotę faktycznie pobraną, a MySQL czyta saldo z `FOR UPDATE` (`src/FinancialTransactionService.php:652–677`). Komentarz testu o bezpośrednim `UPDATE ... GREATEST` jest nieaktualny względem tej ścieżki. Przed migracją wymagany test rzeczywistych dwóch połączeń, blokady i zgodności historii z saldem — bez uruchamiania na bieżącej DB.
12. **P1, kwota kary kontraktowej ≠ kwota pobrana.** `tests/Integration/ContractTickTest.php:218–240` oczekuje naliczonej kary 500, mimo braku środków, zerowej transakcji bankowej i statusu `failed`. `src/ContractService.php:537–561,582–592,660–661` osobno trzyma `penalty_collected`, a statystyka zwraca `penalty` naliczone. W Laravel nie księgować tych statystyk jako wpływu gotówkowego; potrzebne testy niedoboru, retry, depozytu i księgi po obu stronach.
13. **P1/P2, port i magazyn — granica dowodu.** `tests/MySqlIntegration/MySqlPortSectionTest.php:83–99` nazwą sugeruje zapis do magazynu, ale asercja dotyczy liczby zwróconej przez `PortSection::process()`, nie trwałego `storage.used`. Przy magazynie 90/100 test potwierdza przyjęcie 10 z 50 bbl, nie sprawdza losu pozostałych 40 (`:143–158`). W migracji osobno chronić persist stanu magazynu, kolejki i bilans baryłek przy ponowionym ticku; bez izolowanej DB nie przeprowadzono testu end-to-end.

Historyczny `svn_repo/SYSTEM_SZKOLEN_DESIGN.md:389–395` podaje **błędny szkic** `passMin=100-passChance` przy `score` od 1 do 100 i `>=`: dla deklarowanych 5% daje 6 wyników zdających ze 100. Aktualny `src/Training/TrainingService.php:196–204` stosuje prawidłowe `101-passChance`. Ten sam szkic planuje `training_certificates`, lecz przykładowy kod zapisuje technika do `employee_certificates` wbrew opisowi ograniczenia FK; nie kopiować przykładów planu do migracji. Historyczne pliki logistyki podają część transportu hub→magazyn i zakup pipeline jako „przyszłe”, podczas gdy dzisiejszy kod odnosi się do `well_pipelines.leg='outbound'` i `hub_road_trips`; **istnienie ścieżek nie dowodzi poprawnego bilansu wolumenu**.

**Kolejna partia auth/chat/admin (20 plików, 10 testów + 10 aplikacyjnych):** potwierdzono statycznie niespójne definicje tabeli limitera oraz warunkowe pominięcie własnej wysłanej wiadomości przez UI `dm.js` (`lastId` ustawiany przed pollingiem). Testy MySQL używają tymczasowych tabel, lecz DSN nie ma globalnego sprawdzenia izolacji hosta; nie uruchomiono ich. `node --check` obu nowych JS zakończył się kodem 0, co potwierdza tylko składnię. Pełne ograniczenia i zależności w kartach poniżej.

**Partia finance/bank (20 plików: 10 testów + 10 źródeł):** analiza statyczna wykazała możliwe częściowe zapisy ustawień i audytu finansowego, globalny backfill w domyślnym konstruktorze FinanceService oraz ryzyko błędnej interpretacji no-op UPDATE jako brak klucza w BankSettings. SQLite testuje osobne kontrakty finansowe; MySQL testy dziedziczą niebezpieczny setUp i nie były uruchamiane. `node --check assets/js/bank.js` kod 0; nie jest testem UI. Szczegóły w kartach.

**Partia bank/tick (20 plików: 10 testów + 10 źródeł):** statycznie prześledzono egzekucję komorniczą, decyzję/akceptację wniosku, ręczne i automatyczne raty, loader widoku oraz reprezentatywne testy SQLite i MySQL. Najpoważniejsze granice asercji: MySQL test egzekucji nie porównuje zajętej kwoty z długiem, test claim nie uruchamia konkurencyjnych workerów. Niczego nie wykonywano na bazie ani w PHP. Szczegóły i zakres odczytu w kartach.

**Partia orkiestratora ticku (20 plików: 10 testów + 10 źródeł):** odczytano pełny kod modułów harmonogramu, registry, coordinatora i statystyk oraz kontrakty PHPUnit/SQLite. Analiza ujawniła DDL i usuwanie duplikatów w konstruktorze repozytorium statystyk, nieatomowy zapis statusu/logu i granice asercji testów tworzonych dynamicznie w katalogu temp. Nie uruchamiano CRON, PHP ani SQL. Szczegóły w kartach.

**Partia adapterów modułów ticku (20 plików: 10 testów + 10 źródeł):** wszystkie moduły odczytano wraz z testami huba, HR, retencji i transportu. Statycznie porównano limity i failurePolicy: employees jest krytyczny dla harmonogramu, lecz CONTINUE dla błędów wykonania; BlackMarket/Contracts nie stosują limitu jak B2B. Żadnego ticku, PHP ani SQL nie uruchamiano. Szczegóły w kartach.

**Partia auth/security (20 plików: 10 testów + 10 źródeł):** odczytano pełny Auth, strony logowania/resetu, helpery bezpieczeństwa i testy kontraktowe. Statycznie potwierdzono błędne rozgałęzienia formularza resetu (null vs false, tablica vs success), a także nieatomowe zużycie tokenu i brak unieważnienia istniejących sesji. Plik `.htaccess` ma CRLF w checkout, równoważny blob HEAD po normalizacji końców linii. PHP/HTTP/Apache nie uruchomiono. Szczegóły w kartach.

**Partia usług HR/admin (20 plików: 10 testów + 10 źródeł):** przeanalizowano allowlist ustawień, zapytania admina, receipt idempotencji, bonus, kompensację, dashboard, retry deadlocków i efekty strajku. SQLite test wynagrodzeń obejmuje rollback tylko przy transakcji utworzonej przez test. Kontrakty UI i języków są głównie asercjami tekstowymi. PHP/MySQL/HTTP nie uruchomiono; szczegóły w kartach.

**Partia admin/dashboard/logi (20 plików: 10 testów + 10 źródeł):** pełny odczyt objął testy MySQL z konfiguracji, filtry listy graczy, dashboard, logi i nieodwracalny purge. Testy MySQL nie były uruchamiane nawet gdy deklarują TEMPORARY TABLE: używają realnego DSN, a poboczne ensureSchema nie są wykluczone. Wskazano statycznie możliwość usunięcia danych huba właściciela wraz z tenantem i utraty załączników po rollback. Szczegóły w kartach.

**Partia hubów (20 plików: 10 testów + 10 źródeł):** odczytano testy bilansu baryłek, współbieżnych przydziałów, BHP i incydentów oraz logikę query/validation/incident/economy. Testy MySQL opierają się na wspólnym destrukcyjnym setup i nie były uruchamiane. Statycznie wskazano niespójność owner/tenant oraz side effects konstruktora HubService; testy SQLite/MySQL to ustalony zakres asercji, nie wynik wykonania.

**Partia rynek/prawo/kontrakty UI (20 plików: 10 testów + 10 źródeł):** odczytano testy schematu API, black-market decay, własności ofert, prawa oraz UI, a także Bribery, Market i historię permit. Kontrakt MySQL API wywołuje ensureSchema w setUp, więc nie jest read-only. Zidentyfikowano brak metody snapshotu i warunkowy rollback cudzej transakcji sprzedaży; bez wykonywania PHP, MySQL lub ticka. Szczegóły w kartach.

**Partia logistyka/tick (20 plików: 10 testów + 10 źródeł):** pełny odczyt objął feed incydentów, marine history, granicę transakcji ticka i testy MySQL. Dwa testy historii tworzą repozytorium statystyk przed shadow tabel tymczasowych; nie uruchomiono ich. Statycznie rozpoznano warunkowe ominięcie BoardAccess w LogisticsApi. Ustalenia i granice dowodu zapisano w kartach.

**Partia prywatność/UI (20 plików: 10 testów + 10 źródeł):** statycznie odczytano testy nawigacji, lokalizacji, bootstrapu i historii odwiertów oraz moduły administracji prywatnością. Eksport CSV zgód ma limit 10000 bez oznaczenia i nie neutralizuje prefiksów formuł; audyt operacji jest best effort. Nie oceniano zgodności prawnej ani nie uruchamiano PHP, arkusza czy DB. Szczegóły w kartach.

**Partia prywatność/świat (20 plików: 10 testów + 10 źródeł):** pełny odczyt testów SQLite, MySQL i asercji widoków oraz usług cookie/zgód/polityk i dwóch schematów. Testy MySQL są wyłącznie przeanalizowane, nie uruchomione; zawierają destrukcyjny setup i dodatkowe połączenia/procesy. Statycznie serwis definicji cookie ignoruje błędy repozytorium, a aktywacja polityki może wycofać transakcję callera. Szczegóły w kartach.

**Partia odwierty/świat (20 plików: 10 testów + 10 źródeł):** przeanalizowano testy MySQL/SQLite i moduły nazewnictwa, historii, sprzedaży oraz kalkulacji odwiertów. Test nazwany współbieżnym wykonuje wywołania sekwencyjne; test katastrof sprawdza stan po wywołaniu, nie rollback atomowości. Seeder w transakcji callera uwalnia lock przed COMMIT; endpoint GET wyceny może inicjować DDL, a test tick_stats ma szeroki DELETE. Nie uruchamiano PHP/MySQL. Szczegóły w kartach.

**Partia odwierty/skrypty testowe (20 plików: 10 testowych narzędzi/workerów + 10 źródeł):** analizowano statycznie skrypty diagnostyczne, helpery TTS oraz moduły WellService, zakup, sprzedaż, akcje i tick. Część plików `tests/` to nie PHPUnit, lecz ręczne skrypty zmieniające rynek lub globalny cache; nie uruchamiano ich. W akcjach odwiertów jest bezwarunkowy rollback także dla transakcji callera; źródło skalowania kursów nie używa już `updated_at` wbrew staremu komentarzowi testu. Szczegóły w kartach.

**Partia UI/nawigacja i testy (20 plików: 10 testów/fixtures/skryptów + 10 źródeł):** pełny odczyt statyczny; jednostkowe asercje profilu to głównie kontrakty tekstowe, a fixtures renderują atrapy danych lub łączą się z realnym DB. Smoke API kasuje stałe ID; standalone test szkoleń zmienia globalne progi. `tests/index.php` linkuje mutujący skrypt ceny, jednak root `.htaccess` ma redirect `/tests`, więc ekspozycji HTTP nie potwierdzono. Nie uruchamiano PHP/DB/HTTP. Szczegóły w kartach.

**Partia współbieżność/rozliczenie/tick (20 plików: 10 testów, fixtures i skryptów + 10 źródeł):** pełny odczyt statyczny; wspólny MySQL setUp kasuje dane po losowym ID, a `tests/bench_seed.php` bez guardu usuwa zawartość wielu tabel i modyfikuje globalny config. Worker z exit(0) po wyjątku wymaga asercji JSON w callerze. Usługi HR/TTS używają transakcji lub savepointów, zaś wyliczenia strat bazują na losowaniu. PHP/MySQL/HTTP nie uruchamiano. Szczegóły w kartach.

**Partia frontend Node i małe serwisy (20 plików: 3 testy JS + 17 źródeł):** pełny odczyt; wykonano trzy testy Node z makietami DOM/fetch: 24/24 zaliczone (3 + 20 + 1). To nie jest HTTP, PHP ani przeglądarka. W kodzie statycznie: bootstrap morale oznacza done przed DDL, plikowy Cache przyjmuje surowy key i ładuje PHP, a fasady HR/Bankruptcy delegują do traitów. PHP/MySQL/SMTP/HTTP nie uruchamiano. Szczegóły w kartach.

**Partia regresji SQLite i usług incydentów/finansów (20 plików: 1 test + 19 źródeł):** odczytano w całości asercje `BugfixRound4Test.php` i źródła. Nie uruchamiano PHPUnit/DB. Statycznie: konstruktor offline oznacza połączenie jako gotowe przed próbą schematu, regionalny modyfikator używa severity zamiast procentów definicji, InterestEngine zapisuje stan i historię bez własnej granicy transakcji, a bootstrap bankructwa ma warunkową ścieżkę fail-open. Szczegóły i zależności w kartach.

**Partia reputacja kontraktowa i fundamenty (20 plików: 1 test SQLite + 19 źródeł):** pełny odczyt testu, serwisu reputacji, portfela, limitera, logowania i pozostałych usług. PHPUnit/PHP/DB/HTTP nie uruchamiano. Statycznie: migracja portfela może zostać oznaczona jako gotowa mimo błędu UPDATE, RateLimiter jest per sesja, Player może zatwierdzić gotówkę mimo błędu audytu, a HubTickPersist może raportować więcej dodanego bufora niż faktyczny przyrost przy wyścigu. Test reputacji obejmuje SQLite i wybrane snapshoty, nie MySQL.

**Partia kontraktów i usług pomocniczych (20 plików: 1 test + 19 źródeł):** odczytano `tests/Integration/ContractServiceTest.php` w całości; Partia zakończona po pełnym odczycie, weryfikacji blobów i ledgeru. PHPUnit/PHP/MySQL nie uruchamiano; nie mylić zakresu asercji z wynikiem.

**Partia B2B — zamknięta (1 test + 19 źródeł z 20):** pełny odczyt `B2BContractServiceTest.php`, bez wykonania. Kolejne źródła pozostają do zbadania.

## 4. Interfejs, prywatność i zgodność

- **Potwierdzony izolowany błąd P2:** `assets/js/finance.js:109–115` traci znak minus w tooltipie. Test Node dla `net_profit=-123` zwrócił `Net profit: 123 PLN` i `negativeShown=false`; nie jest to E2E strony.
- **Sinki HTML/JS, bez dowodu exploita:** `assets/js/well_staff.js:28–31,63–80` składa `innerHTML` z danych API; `templates/views/technical/tabs/well_staff.php` buduje inline `onclick` z nazwą po HTML escaping, co nie chroni kontekstu JS po dekodowaniu encji. `templates/views/technical/main.php` serializuje komunikaty do inline script bez `JSON_HEX_TAG`. Zbadać możliwość kontroli danych i zastąpić bezpiecznym DOM API; nie twierdzić, że XSS jest już wykorzystywalny.
- **P2, zgody/baner:** `assets/js/privacy_banner.js:100–124` zapisuje `privacy_consent` przed potwierdzeniem API i chowa baner również przy błędzie. Polityki cookies PL/EN/DE opisują `csrf_token` jako oddzielne cookie, gdy `src/CSRF.php` trzyma token w sesji i polach. Historyczny brief wymaga globalnego banera, a anonimowa `public/index.php:7–11` zwraca samodzielny widok bez wczytania skryptu banera. Ustalić aktualne wymaganie i zatwierdzić treści prawnie; to nie jest opinia prawna.
- **P2, mobile maps:** `mobile/lib/services/api_service.dart:134–151` nie przekazuje opcjonalnego locale do żądań mapy i zezwolenia, chociaż `_headers:45–49` obsługuje `Accept-Language`. `maps_screen.dart:83–88` łapie wszystkie wyjątki, pokazując ogólny błąd. Odczyt kodu, bez testu Flutter/API.
- **P2, obsługa błędów i walidacja UI:** `assets/js/technical.js` może pozostawić przycisk `disabled` po odrzuceniu `fetch`; `recruitment.js` interpretuje `ready_at` bez jawnej strefy; `form.submit()` po modalach omija interaktywną walidację HTML5. Reguły biznesowe muszą być wymuszone na serwerze.
- **P2, wydajność:** `templates/views/technical/tabs/team.php:110–124` robi N+1 SELECT dla zajętych pracowników; wells/technical mają pełne listy i zagnieżdżone pętle, a historia sabotażu może tworzyć wiele linków paginacji. Sprawdzić duże dane i test przeglądarkowy.
- **P2/P3, CSS/wygląd:** karty wcześniej wskazały możliwe błędy selektorów w `admin.css`, `mobile.css`, `admin_finance.css`, `admin_sabotage.css`; wymagana weryfikacja parserem i renderem. Mapa strukturalna 47 CSS policzyła 35 `url(...)` bez brakujących lokalnych referencji w tej podgrupie, z Google Fonts w `style.css`; nie jest to parytet wizualny. `VISUAL_GUIDELINES.md` wymienia PL+EN, choć web ma także `lang/de.php`.
- **Nowe odczyty JS, hipotezy regresji UI:** `assets/js/hr_raises.js:36–45` zachowuje token po zmianie kwoty przy ponowieniu (serwis wymaga tokenu spójnego z payloadem), a `hr.js:132–149` sygnalizuje odrzuconą ofertę toastem typu `success`. `legal.js:62–90` i `legal_design.js:181–215` pokazują literalne `PLN` przy locale, w którym PHP przekazuje inną walutę. `protection.js:69–98` nie zgłasza toastu dla błędu sieci przy zakupie. `logistics_hub_wells.js:198–236` renderuje pierwszą stronę listy `assignable_hubs` bez sterowania kolejną; przy rzeczywistym `condition=0` ścieżka `Number(...||100)` może pominąć ostrzeżenie o stanie. To **wnioski statyczne**, bez potwierdzenia zachowania E2E. `boardroom-dynamic.js` kieruje wartości do `innerHTML` — prześledzić pochodzenie i escaping przed uznaniem za wykorzystaną lukę XSS; ścieżka tła względna wymaga testu HTTP. `admin-template-upload.js` wysyła surowe chunki POST z CSRF, ale bez sprawdzonego backendu/wyścigów uploadu; wczesny `return` przy braku upload-form nie stanowi wykazanej usterki na bieżącym widoku `template_editor`, który zawiera także kontrolki stopki.
- **Kolejny odczyt kodu UI/CSS:** `assets/js/well_grid.js:519–535` wywołuje `card.remove()` przed `card.closest('.wg-grid')`, więc po sprzedaży odwiertu nie znajdzie jego dawnego rodzica i nie ukryje pustej grupy; wynik po stronie DOM wymaga E2E. `hr_strikes.js:51–64` zachowuje token idempotencji przy błędzie, nawet gdy użytkownik zmieni kwotę oferty przed ponowieniem — analogiczny kontrakt payload/token jak przy podwyżkach wymaga weryfikacji serwera. `assets/css/hr.css:753–764` definiuje `.trait-fill` bez użycia `--bar-w` ustawionego w `templates/views/hr/main.php:443–445`, dlatego pasek nie koduje proporcji cechy; `boardroom.css:249–253` jest nadpisany przy ≤600px przez późniejszy `mobile.css:169–176` z `!important` (`4/3` → `3/2`). To analiza statyczna kaskady, **bez pomiaru renderu**. `assets/js/boardroom.js` rysuje statyczne miejsca, ale aktualny `templates/views/boardroom/main.php:51` ładuje `boardroom-dynamic.js` — nie przypisywać starej implementacji do bieżącego ekranu. `profile.css:245` ustawia focus również na przyciskach poza `.profile-shell`; zamierzony zasięg wymaga decyzji projektowej.
- **Częściowa partia CSS i briefów (10/20):** `assets/css/finance.css:184–199` ma selektor potomka `.fin-well-header .fin-well-row` zamiast osobnych reguł dla rodzeństwa w widoku finansów — zamierzony `display:grid` nie dopasowuje zwykłych wierszy; `bank.css:335–338` i `home.css:1027–1028` mają analogiczne brakujące przecinki łączące warianty hover/odznaki w niezamierzone selektory potomków. To potwierdzone dopasowanie składniowe, a nie pomiar wyglądu. Historyczny `svn_repo/kontrakty-dlugoterminowe-brief.md` podaje sprzeczne `order=35/45/90` i opis kolejności; bieżące moduły mają `players=40`, `black_market=50`, `contracts=90`. `svn_repo/b2b.md` także ma sprzeczne szkice `35/36/100`; bieżący B2B ma `100` i rozszerza MVP o dostawy częściowe. Traktować rozbieżność jako decyzję domenową, nie automatyczną poprawkę. Oba dokumenty **nie są dowodem przejścia ich historycznych testów**.
- **Nowa partia GPT: 20/20 CSS (odczyt statyczny):** `help.css:53–63,97–99` oraz `training.css:286–290` mają brakujące przecinki między selektorami; przez to reguły potomków nie trafiają w zwykłą tabelę `.help-content table` w `public/help.php` ani w sąsiednie siatki programów/certyfikatów w `trainings.php`. W `technical.css:1497` bardziej swoisty `.t-wrap .trn-program-grid` nadpisuje nominalne kolumny `training.css`; rzeczywista liczba kolumn zależy od dostępnej szerokości. `admin_protection.css:1,3` zawiera dwie osierocone deklaracje `gap` poza blokami. Nie znaleziono literalnych odwołań do nazw arkuszy `admin_protection.css`, `help_editor.css`, `template_editor.css`, `player_menu_editor.css` w przeszukanych PHP/JS — wpięcie wymaga dalszej kontroli. To obserwacje kodu i kaskady, bez renderu i bez uznania któregokolwiek CSS za test funkcji serwera.
- **Partia 20 plików: 10 testów i 10 źródeł aplikacji (auth/chat):** statycznie wykryto błędną kontrolę kolejności chunków i brak limitu całego pliku przed finalizacją w `ChatApi.php`; `ChatMessageHtml::forApi()` tworzy `message_html` admina, ale `chat.js` renderuje tylko plain text, więc efekt formatowania na ekranie wymaga testu DOM. `ApiAuth.php` przechowuje bearer tokeny jawnie w tabeli i ustawia done przed ryzykownym DDL; testy MySQL ograniczają DSN regexem nazwy `test|ci`, co nie gwarantuje izolacji hosta. Szczegóły i warunki w kartach. **Wykonano izolowany Node `tests/js/chat_keyboard.test.cjs` (1/1), a w kolejnej partii trzy pliki testowe Node (24/24); PHP/MySQL/Playwright nadal 0.**
- **Dalsze 13 CSS, pełny odczyt statyczny:** `contracts.css:156–157` ma brakujący przecinek przed wariantem krytycznym i nie stylizuje samodzielnego badge `--high` według zamierzonej reguły; `dashboard.css:17–25` nadpisuje globalne `--gold2` na wartość półprzezroczystą; `well-grid.css:1521–1539` ma regułę 4 kolumn `!important` o wyższej specyficzności niż późniejsze 3/2-kolumnowe media, ale arkusz jest odnajdywany tylko w dawnych kopiach entrypointu, więc nie wykazano tego jako awarii żywego UI. `logistics.bak.css` to kopia bez wpięcia w `public/logistics.php`; aktualne `logistics.css` jest dłuższe o 617 linii. `chat.css` zawiera dwie warstwy stylów i końcowe nadpisanie wysokości powłoki na małych ekranach. **Bez testu renderu, computed styles i wykonania kodu PHP.**
- **Inne zależności:** dwa formularze finansów mają POST+CSRF, ale reguły polityki finansowej pozostają otwarte; sprzedaż odwiertu ma wycenę GET i musi przeliczyć ją serwerowo przy POST. `assets/js/sabotage.js` używa `textContent` dla danych gracza, lecz nieobsłużony `JSON.parse` może przerwać UI. W widoku home istnieją 23 literalne assety na dysku — to nie test HTTP ani wyglądu. Regulaminy muszą odróżnić legalny sabotaż w grze od exploitu.

## 5. Narzędzia statyczne, testy i bramki migracyjne

**Nowe wykonanie izolowane:** `python tests/Unit/test_technical_ui_contract.py` — **11/11, exit 0**. To kontrakty stringów źródłowych i obecności plików, bez renderu, przeglądarki, PHP ani bazy. `tests/.htaccess` wyłącza rewrite i nagłówki bezpieczeństwa lokalnie; zewnętrzna dostępność HTTP nie została potwierdzona.

`phpstan-baseline.neon` ma 268 reguł ignorowania (suma `count` 269) w 46 ścieżkach. `phpstan.neon` analizuje `src` na poziomie 6, wyłącza `src/PHPMailer` i ma `reportUnmatchedIgnoredErrors:false`; historyczny `phpstan_errors.txt` z `C:\xampp\htdocs` ma około 421 linii diagnostyk policzonych regexem. **Nie uruchomiono aktualnego PHPStan.** `tools/check_encoding.php` wcześniej zgłosił sukces przy 0 sprawdzonych plików; nie jest to zielona bramka.

**Nowy przegląd 20 testów/fixtures:** przeglądarkowe `profile_browser.cjs` i `sabotage_browser.cjs` mają twardo zapisane ścieżki Chrome/PHP w Windows; testy dashboardu i home używają fixture, mocków API oraz PL/EN bez DE, więc nie potwierdzają prawdziwego serwera ani danych gracza. Trzy testy SQLite (`HRContractRenewalTest`, `HubPlayerQueryTraitTest`, `SabotageScreenTest`) badają wycinki serwisów na sztucznych tabelach, nie zgodność produkcyjnego MySQL. `TechnicalDisasterStatusTest` statycznie pokazuje istotny kontrakt: pobranie aktywnych katastrof może **zmienić status** starego wpisu na `resolved`, więc zamiana kontrolera na „czysty” odczyt Blade wymaga decyzji i testu. Żaden z tych testów nie został uruchomiony jako PHP/Playwright w tej sesji.

**Kolejnych 10 testów/fixtures:** `MySqlHubAssignmentServiceTest.php:22–50` faktycznie wywołuje transfer i **oczekuje natychmiastowego nowego przypisania `active`**, wzmacniając wykrytą sprzeczność z dokumentem ~2 ticków, ale nadal nie stanowi wykonanego tutaj testu ticku. Jego test o nazwie `testAssignWellDeductsAccessFeeFromPlayerCash` w rzeczywistości **oczekuje opłaty 0** i niezmienionego salda, bo koszt jest przeniesiony na akwizycję. `MySqlWellActionsTraitTest.php:51–103,135–164` kopiuje wzór i literalny SQL zamiast wywołać produkcyjne metody, zatem przejście testu nie wykryje regresji tych metod. `well_workspace_browser.cjs:1` wymaga `PLAYWRIGHT_MODULE` bez fallback, a jego mock zwraca puste assety przy brakującym pliku. Testów nadal nie uruchomiono.

**Kolejnych 10 odczytanych testów SQLite/Unit:** `ContractsModuleTest` statycznie oczekuje `order=90`, lecz nie testuje pełnego ticku; `FinancialStateSectionTest` seeduje już powiększone saldo zamiast wywołać równoległy przelew, a jego komentarz o `GREATEST` odbiega od bieżącej implementacji blokady i audytowania faktycznie pobranej kwoty. `WellHubReconciliationTest` podstawia `HubTickService::processTick` i `persistTickResult`; sprawdza bilans w pamięci, nie rollback prawdziwego SQL. `LegalNotificationsTest` jawnie dopuszcza zgubione powiadomienie po commicie. `RoadTransportServiceTest` ustawia globalne `mt_srand` bez przywrócenia; offshore i road mają testy statystyczne, nie wszystkie deterministyczne. `EmployeeDashboardQueryServiceTest` seeduje obcego pracownika, ale nie obce zdarzenie do testu mark/read; `EmployeeRepositoryTest`, `HubStaffingManagementServiceTest` i `TechnicalStrikeEffectsTest` sprawdzają wycinki domeny na uproszczonym SQLite. Szczegóły i granice per plik: karty aneksu. **Nie uruchomiono** tych testów.

**Dalszych 20 plików testowych przeczytanych równolegle:** 10 SQLite/Integration, 9 MySQL i 1 Playwright. `ContractTickTest` obejmuje niedobór, dostawę częściową i naliczoną karę, lecz nie pełny tick ani współbieżność; `FinancialTransactionServiceTest` nazywa przelew atomowym bez wymuszenia awarii między zapisami. `HubAssignmentServiceTest` sprawdza rollback savepointu z triggerem SQLite, ale HubService jest mockiem; `MobileWebBridgeTest` uruchamiałby lokalne podprocesy PHP i emuluje endpointy, nie rzeczywiste HTTP ani wyścig dwóch połączeń. `EmployeeStateServiceTest` ma rzeczywistą iniekcję błędu INSERT przez trigger SQLite, ale brak konfliktu legacy/kanoniczny; `LegalMapPermitDataTest` ma uproszczony schemat bez produkcyjnego UNIQUE(player_id,region_id) — brak testu duplikatów **nie dowodzi** błędu obecnego schematu. `LegalSectionTest`, `OfflineSectionTest` i `ProtectionServiceTest` nie wymuszają awarii notyfikacji/powrotu po commit. `MySqlB2BContractServiceTest` i `MySqlEmployeeAssignmentServiceTest` zawierają prawdziwe workery na osobnych połączeniach, ale nie były wykonane tutaj; inne metody o nazwie concurrency wykonują dwie sekwencyjne operacje. `MySqlPortServiceTest::testReturnsNullWhenNoActivePort` akceptuje null **albo dowolną tablicę**, więc nie dowodzi fallbacku; `MySqlOffshoreTransportServiceTest` nie porównuje `result1` z `result4` w scenariuszu ryzyka politycznego. `legal_design_browser.cjs` używa fixture PL/EN, przechwytuje submit bez POST, a jedyna nowa kontrola to `node --check` (składnia). Każda z 20 nowych kart aneksu podaje konkretne asercje i ograniczenia; testów PHP/MySQL/Playwright nie wykonano.

**Partia mieszana: 10 testów PHP + 10 źródłowych JS, pełny odczyt statyczny.** `EmployeeRaiseRequestServiceTest.php:125–146` może przechwycić własne `self::fail()` w `catch (Throwable)` i tym samym zamaskować brak oczekiwanego błędu. `MySqlEmployeeHrConcurrencyTest` ma realne równoległe workery na oddzielnych połączeniach, ale dziedziczy ryzykowny MySQL `setUp()` i modyfikuje współdzieloną flagę. `MySqlWellLifecycleSimulationTest` symuluje zakup szóstego odwiertu przez SQL — nie wywołuje odmowy w `WellShop`. `MySqlRoadTransportServiceTest` zmienia globalny feature flag i ma probabilistyczne asercje incydentów. Pozostałe testy i wszystkie asercje/fixture/ograniczenia opisano per plik w aneksie; **PHP/MySQL ani E2E nie wykonano**. `node --check` 10 JS potwierdził wyłącznie składnię.

**Kolejna partia 20 (7 testów, 7 JS, 6 CSS):** `EmployeeAssignmentServiceTest` na syntetycznym SQLite sprawdza alokacje i efekty ról, lecz scenariusz fired jedynie pokazuje zajęty przydział, nie usuwa go; `TechnicalHubTasksTest` rzeczywiście wymusza rollback przez trigger SQLite przy INSERT, ale używa podstawionej premii kierownika i niespójnych zegarów PHP/SQL. `MySqlMarineDeliveryServiceTest` dla regionu bez portu asercjuje samą **obecność klucza** `port_id`, nie jego wartość; cleanup portu nie ma pełnej gwarancji po twardym przerwaniu. `MySqlWellPipelineServiceTest` sprawdza istnienie trzech tabel, nie strukturę indeksów; przy powtórnym zakupie nie asercjuje salda. `MySqlMoraleServiceTest` zmienia wspólną konfigurację i uruchamia eskalacje na realnym połączeniu. Dwa scenariusze `.cjs` używają fixture i mocków, a screenshoty są opcjonalne, nie testem parytetu. Szczegóły wszystkich asercji i ograniczeń: aneks. **Nie uruchomiono PHP, DB ani Playwright; `node --check` wybranych CJS/JS to tylko składnia.**

**Partia auth/chat (10 testów + 10 aplikacyjnych):** testy limitera API mają realne workery SQLite/MySQL i kontrolę przeplotu, ale nie były tutaj uruchomione; MySQL wymaga fizycznie bezpiecznego DSN przed konstrukcją PDO/DDL. `ChatHistoryRegressionTest` pomija konstruktor ChatService przez Reflection; `ChatRequestLimiterTest` i `ChatRetentionServiceTest` używają mocków, więc nie badają produkcyjnego SQL ani konkurencji. `ChatMessageHtmlTest` nie renderuje UI; `chat_controls_browser.cjs` wycina handler regexem ze źródła i uruchamiałby sztuczny DOM, nie aplikację. Izolowane `node --test tests/js/chat_keyboard.test.cjs` zakończyło się 1 pass, 0 fail; `node --check` dla chat.js i testu browser potwierdził wyłącznie składnię.


W tej kontynuacji wykonano izolowany test Node tooltipu, `node --check` ośmiu odczytanych testów `.cjs` oraz walidatory kart/pokrycia. Wcześniejsze etapy deklarowały 20/20 testów JS z mockami, 272 kontrole składni PHP i 93 kontrole Node; **nie uruchamiano ich ponownie na potrzeby tej migawki** i nie zastępują testu aplikacji. Wcześniejsze procesy delegowane GPT i jawna próba DeepSeek kończyły się HTTP 402 (brak środków); późniejszy przegląd 20+13 CSS wykonano bezpośrednio w tej sesji na GPT. Trzy dokumenty odczytał delegowany agent GPT; jego karty sprawdzono centralnie pod kątem HEAD, zakresów odczytu i zależności. **Nie wykonano** żywego PHP/MySQL, testów izolowanej DB, E2E admin/gracz/mobile, wizualnego porównania ikon, rzeczywistego testu zgód/rekrutacji, produkcyjnego sprawdzenia HTTP ani Fluttera. Historyczne wyniki CI zapisane w `svn_repo/*` nie są wynikami bieżącego HEAD. Istnienie testu, przejście mocka i syntaktyczna poprawność pliku nie dowodzą poprawnego zapisu, autoryzacji i wyniku gracza.

**Historyczna partia 20 plików pozostała nieukończona (10/20):** grupy C/D zapisały 10 zweryfikowanych kart, grupy A/B nie pozostawiły kart po `HTTP 402: Insufficient Balance`. Jawna próba DeepSeek również zwróciła `HTTP 402` bez odpowiedzi. Po późniejszym poleceniu użytkownika wznowiono bezpośrednią analizę na GPT: osobno 20/20, a następnie 13/13 CSS. Liczby nie oznaczają, że brakujące ścieżki partii 8 zostały automatycznie zaliczone; trzy dokumenty zostały później odczytane w całości i zweryfikowane jako odrębne karty.

**Przed zamknięciem audytu:** w tej podgrupie nie ma już brakujących kart; pozostają zależności wszystkich kart odczytu; sprawdzić 7 wadliwych wierszy pierwotnego ledgera; osobno zweryfikować kontroler → serwis → SQL → tick → widok/JS → test; przygotować sztuczną/zanonimizowaną DB, testy awarii/transakcji/współbieżności i porównania wizualne. Zachować `players.id`, wszystkie `player_id`, hashe i konta adminów/2FA, salda, historię gry, kontrakt mobile oraz obecny wygląd. Podczas cutover może pisać **jeden** silnik ticka, z backupem i planem odtworzenia nowych zapisów. Nie oznaczać raportu jako pełny ani nie zmieniać statusów starego rejestru na `FULL_SOURCE_REVIEW`/`RUNTIME_TEST_PASS` bez wymaganych dowodów.

## 6. Mapa testów i pozostałych plików — na co zwrócić uwagę

**Status tej mapy:** wszystkie 77 plików testowych/fixtures z tej podgrupy zostało przeczytanych w całości i ma karty w aneksie; to **nie oznacza wykonania ani zaliczenia testów**. Wszystkie **13 CSS** z ostatniej mapy mają teraz pełne karty statycznego odczytu; **3 dokumenty** odczytano w całości i zweryfikowano ich karty; poniższe punkty są planem weryfikacji, nie ustaleniami o ich zachowaniu. Testów PHP/MySQL/Playwright nie uruchomiono. Nadal otwarte są zależności już przeczytanych plików, testy runtime i 7 wadliwych wierszy starszego ledgera.

### 6.1. Przed uruchomieniem czegokolwiek

1. **MySQL:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php` czyta `config/database.php` i czyści rekordy już w `setUp()`, bez globalnej bramki izolowanej bazy. Nie uruchamiać całego PHPUnit, CRON ani testów integracyjnych w bieżącej konfiguracji. Najpierw fizycznie odrębna baza/host z danymi sztucznymi, kontrola *rzeczywistego* `SELECT DATABASE()` i hosta **przed pierwszym DELETE/DDL**, osobny użytkownik bez praw do produkcji. `TransactionRaceFixtures.php` ma własną bramkę, ale nie chroni pozostałych klas. `EmployeeSystemBootstrap::ensure()` może tworzyć/zmieniać tabele, a test negocjacji przełącza globalny feature flag; rollback i `finally` nie zabezpieczają przed twardym przerwaniem.
2. **SQLite:** testy pod `tests/Integration` często używają `sqlite::memory:` i sztucznego schematu. Sprawdzić, czy rzeczywiście wywołują produkcyjny serwis, a nie skopiowany SQL; oddzielnie potwierdzić dialekt, blokady i FK na izolowanym MySQL.
3. **Przeglądarka:** testy `.cjs` często korzystają z fixture PHP, mockowanych endpointów i Edge/Chrome pod konkretną ścieżką lub portem. Pojedyncza zielona asercja nie pokrywa loginu, CSRF, zapisu do bazy, ticku ani produkcyjnego HTTP. Porównać PL/EN/DE, błędy sieci i render bez mocków. `node --check` oznacza jedynie składnię.
4. **Dowód:** dla każdego testu zapisać dokładny DSN i izolację, wersję PHP/MySQL/Playwright, rzeczywiście wykonane przypadki i ich wynik; dla wizualnych — szerokość, locale, screenshot/baseline i brak overflow. Nie utożsamiać liczby `test*` z liczbą uruchomionych testów.

### 6.2. Już przeczytane: co one faktycznie obejmują

- **Fixture widoków (7):** `tests/fixtures/sabotage-view.php`, `legal_design_render.php`, `home_dashboard_render.php`, `admin_dashboard_render.php`, `hub_incidents_render.php`, `logistics_design_render.php` podają ręcznie zbudowane dane do realnych szablonów; kontrolować zgodność kluczy, locale, escaped output i linków z rzeczywistym kontrolerem. `tests/fixtures/TransactionRaceFixtures.php` pilnuje nazwy testowej DB tylko u swoich użytkowników, a cleanup zależy od zakończenia procesu. Dwa fixture bez straży CLI wymagają sprawdzenia document root; sama ścieżka `tests/` nie dowodzi ekspozycji HTTP.
- **Przeglądarka (7):** `tests/profile_browser.cjs`, `navigation_activity_browser.cjs`, `home_dashboard_browser.cjs`, `public_home_browser.cjs`, `sabotage_browser.cjs`, `hub_incidents_browser.cjs`, `well_workspace_browser.cjs` sprawdzają głównie render, responsywność, taby, klawiaturę i modal na fixtures; news, historia i statystyki bywają mockowane. Uwaga na twarde ścieżki PHP/Chrome, port 8768, brak DE w części scenariuszy i `well_workspace_browser.cjs` wymagający `PLAYWRIGHT_MODULE` bez fallback; jego mock zwraca pusty asset zamiast błędu.
- **MySQL (11):** `tests/MySqlIntegration/MySqlIncidentOngoingDropAutoRepairTest.php`, `MySqlIncidentImmunityDeltaHoursTest.php`, `MySqlWellShopTest.php`, `MySqlChatServiceLimitTest.php`, `MySqlHubIncidentServiceTest.php`, `MySqlPipelineHseIntegrationTest.php`, `MySqlEmployeeRoleEffectServiceTest.php`, `MySqlBankNegotiationOwnershipTest.php`, `MySqlWellActionsTraitTest.php`, `MySqlHubAssignmentServiceTest.php`, `MySqlEmployeeStateServiceTest.php`. Szczególnie: `MySqlWellActionsTraitTest` kopiuje SQL i wzór, nie wywołuje metod; `MySqlHubAssignmentServiceTest` oczekuje transferu od razu do `active` i zerowej opłaty przy przypisaniu; `MySqlChatServiceLimitTest` używa tymczasowych tabel i pomija konstruktor serwisu. Część testów wywołuje bootstrap DDL albo zapisuje globalną konfigurację. Szczegóły per plik w kartach aneksu.
- **SQLite (5):** `tests/Integration/HRContractRenewalTest.php`, `TechnicalDisasterStatusTest.php`, `HubPlayerQueryTraitTest.php`, `SabotageScreenTest.php`, `MoraleFormulaTest.php` badają kontrakt pracowniczy, status katastrofy, cooldown przypisania, wybór celu sabotażu i wzory morale. `TechnicalDisasterStatusTest` świadomie oczekuje zapisu `resolved` podczas odczytu widoku; nie zgubić tej decyzji przy rozdzieleniu GET/komendy w Laravel. Żaden z tych pięciu nie dowodzi działania MySQL.
- **SQLite (kolejnych 9):** `tests/Integration/ContractsModuleTest.php`, `EmployeeRepositoryTest.php`, `FinancialStateSectionTest.php`, `HubStaffingManagementServiceTest.php`, `LegalNotificationsTest.php`, `OffshoreTransportServiceTest.php`, `RoadTransportServiceTest.php`, `TechnicalStrikeEffectsTest.php`, `WellHubReconciliationTest.php`. Asercje obejmują m.in. `order=90`, normalizację dwóch źródeł pracowników, potrącenie kosztów z bieżącego salda, załogę huba najemcy, komunikaty prawne, bilans transportu, pauzę zadania podczas strajku i bilans hub–odwiert. Luki: brak pełnego ticku, prawdziwej współbieżności i MySQL; `WellHubReconciliationTest` mockuje obliczenie i persist, a testy transportowe używają RNG. Pełne granice każdego w aneksie.
- **„Unit” z SQLite (1):** `tests/Unit/EmployeeDashboardQueryServiceTest.php` sprawdza realny serwis na `sqlite::memory:` (dashboard, paginacja, read/notified). Nie próbuje oznaczyć cudzego zdarzenia jako przeczytanego — sam obcy pracownik w seedzie nie dowodzi izolacji zapisu. Nie jest testem bez DB ani testem HTTP.
- **SQLite/Integration (kolejnych 10):** `tests/Integration/ContractTickTest.php`, `FinancialTransactionServiceTest.php`, `HubAssignmentServiceTest.php`, `MobileWebBridgeTest.php`, `WellProductionHandlerTotalCostsTest.php`, `EmployeeStateServiceTest.php`, `LegalMapPermitDataTest.php`, `LegalSectionTest.php`, `OfflineSectionTest.php`, `ProtectionServiceTest.php`. Asercje dotyczą dostaw i naliczonej kary, księgi, statusów hubów, sesji z tokenu, kosztów OPEX, migracji pracowników, permitów i ochrony. **Rozróżnienie:** trigger SQLite rzeczywiście wymusza rollback w wybranych testach; samo seedowanie salda i sekwencyjny transfer nie są testami dwóch połączeń. MobileWebBridge uruchamiałby podproces PHP z emulowanym żądaniem, nie serwer WWW. `LegalMapPermitDataTest` pomija produkcyjny indeks UNIQUE, więc jego fixture nie jest schematem referencyjnym.
- **MySQL (kolejnych 9):** `tests/MySqlIntegration/MySqlB2BContractServiceTest.php`, `MySqlEmployeeAssignmentServiceTest.php`, `MySqlHubTickServiceTest.php`, `MySqlPortSectionTest.php`, `MySqlPortServiceTest.php`, `MySqlMarineDeliverySectionTest.php`, `MySqlOffshoreTransportServiceTest.php`, `MySqlRecruitmentFlowTest.php`, `MySqlTechnicalPipelineTasksTest.php`. Dwa pierwsze zawierają workery na osobnych połączeniach, lecz nie wykonano ich w audycie. Reszta obejmuje huba, port, rejs, rekrutację i naprawę pipeline; niektóre metody o nazwie „concurrent” wywołują operacje sekwencyjnie. **Szczególne ryzyko wykonania:** globalny `DELETE ports WHERE region_id=997`, globalny `UPDATE ports`, DDL w konstruktorach i feature flag zmieniany dla całej DB.
- **Przeglądarka (kolejny 1):** `tests/legal_design_browser.cjs` podaje PL/EN z CLI fixture na sześciu szerokościach, sprawdza widok mapy i przechwytuje submit. Nie wysyła realnego POST, nie dowodzi autoryzacji ani CSRF; wykonano jedynie `node --check`.
- **Ostatnie 10 testów PHP (5 Integration, 4 MySQL, 1 Unit):** `tests/Integration/EmployeeNegotiationServiceTest.php`, `EmployeeRaiseRequestServiceTest.php`, `EmployeeRoleEffectServiceTest.php`, `LegalPermitSimulationTest.php`, `LegalServiceTest.php`, `tests/MySqlIntegration/MySqlEmployeeHrConcurrencyTest.php`, `MySqlPipelineSectionTest.php`, `MySqlRoadTransportServiceTest.php`, `MySqlWellLifecycleSimulationTest.php`, `tests/Unit/BoardroomUploadServiceTest.php`. Zakres faktycznych asercji, fixture, side effects i braków jest w każdej karcie aneksu. Szczególnie: równoległość HR jest realna, test szóstego zakupu odwiertu nie wywołuje odmowy `WellShop`, test uploadu działa na mockowanej/tymczasowej przestrzeni, testy SQLite nie odtwarzają transakcji MySQL. Żadnego nie wykonano.
- **Ostatnie 7 testów (2 Integration, 3 MySQL, 2 browser):** `tests/Integration/EmployeeAssignmentServiceTest.php`, `TechnicalHubTasksTest.php`, `tests/MySqlIntegration/MySqlMarineDeliveryServiceTest.php`, `MySqlMoraleServiceTest.php`, `MySqlWellPipelineServiceTest.php`, `tests/admin_dashboard_browser.cjs`, `tests/logistics_design_browser.cjs`. Pierwsze dwa wywołują produkcyjne serwisy na uproszczonym SQLite (w zadaniach technicznych jest trigger błędu i sprawdzenie rollbacku). Trzy MySQL dziedziczą niebezpieczne `setUp()`; testy portu i pipeline nie dowodzą wszystkich deklarowanych warunków. Dwa browser mają lokalne fixtures/mocki i opcjonalne screenshoty, nie potwierdzają działającego serwera. Karty podają asercje i ograniczenia per plik; żaden z siedmiu testów nie został uruchomiony.

### 6.3. Testy: odczyt ukończony, wykonanie nadal zablokowane

Zestaw 77 plików tej wcześniejszej podgrupy oraz 10 dodatkowych testów z nowej partii ma karty źródłowe, ale mapa asercji, sztuczne schematy i `node --check` nie są wynikami PHPUnit/Playwright. Przed bezpiecznym uruchomieniem MySQL wymagana jest izolacja połączenia **przed** `setUp()`; przed porównaniem wyglądu — rzeczywisty render PL/EN/DE i baselines. Priorytetem pozostaje sprawdzenie luk asercji i zależności wskazanych w kartach, nie ponowny odczyt tych samych testów.

### 6.4. Ostatnie 13 plików CSS — odczyt ukończony

Każdy z 13 arkuszy ma pełną kartę źródłową w aneksie. To nadal nie jest wynik renderu. Zweryfikować parser, selektory i kaskadę, lokalne zasoby, 320–1920 px, PL/EN/DE, klawiaturę, kontrast i screenshoty. Wszystkie 77 testów/fixtures oraz 7 JS tej podgrupy mają już karty źródłowe, ale nie zostały tu uruchomione.

- **Podstawa i sesja:** `assets/css/chat.css`, `dashboard.css`, `privacy.css` — zbadać cache, focus, zgodę i prywatność danych.
- **Finanse/prawo/rynek:** `assets/css/black_market.css`, `contracts.css`, `legal.css` — kwoty, stany błędów i kaskada.
- **Zarząd/admin:** `assets/css/boardroom-scene.css`, `director.css` — uprawnienia, podglądy i fokus.
- **HR/techniczny/odwierty:** `assets/css/hr_morale.css`, `technical.css`, `well-grid.css` — zweryfikować wpływ na już przeczytane arkusze (szczególnie nadpisanie training.css), długie listy i media queries.
- **Logistyka/mapa:** `assets/css/logistics.bak.css`, `map.css` — pierwszą ścieżkę ocenić jako możliwą kopię historyczną, nie z góry jako ładowaną produkcyjnie.

### 6.5. Trzy dokumenty historyczne — odczyt ukończony

Odczytano wszystkie trzy źródła w całości i porównano kluczowe twierdzenia z HEAD. Dokonał tego osobno delegowany agent **GPT**; karty sprawdzono centralnie według blob SHA, fizycznej liczby linii i istnienia zależności. Nie uruchomiono testów przywołanych w dokumentach ani kodu aplikacji.

- `svn_repo/FINANCE_README.md`: historyczne 100 testów/259 asercji opisuje pliki nieobecne w HEAD; bieżący odczyt panelu finansów pomija automatyczne ensureSchema, a globalne mnożniki bilansu należą do osobnego panelu. Zachowano rozróżnienie między deklaracją w dokumencie a stanem kodu.
- `svn_repo/GAME_README.md`: późniejsze wpisy changeloga korygują wcześniejsze twierdzenia o ticku i czacie; HEAD ma moduł pracowników i wersję schematu 8, lecz dawne liczniki testów i deklaracje wdrożenia nie są dowodem obecnego wyniku.
- `svn_repo/brief-ai-pracownicy-role-logistyka-morale-strajki.md`: późniejsza korekta umieszcza `hub_operator` w technice; HEAD ma `EmployeesModule` order 35 i katalogowy interval 1 (zamiast historycznego 2). Strajki/efekty mają ścieżki kodu, ale domyślne flagi są wyłączone; nie potwierdzono stanu produkcyjnej bazy.

**Bramka:** mapa braków tej podgrupy jest pusta, ale status `SOURCE_READ_DEPENDENCIES_OPEN` nie oznacza zamknięcia zależności ani `RUNTIME_TEST_PASS`. Karty dokumentów opisują deklaracje historyczne, a nie wyniki CI lub działanie bieżącej aplikacji.

### 6.6. Próba nadzorowanego audytu Qwen — trzy pliki

Zdalny `alibaba-token-plan:qwen3.7-plus` otrzymał pełną treść `public/sell.php`, `public/form_sell.php` i `templates/components/form_sell.php` z tego HEAD, bez narzędzi i bez prawa modyfikacji. Odpowiedział; następnie niezależnie przeczytano wszystkie trzy pliki i sprawdzono zależności (`.htaccess`, `src/Storage.php`, `public/market.php`, `src/MarketSaleService.php`, `templates/views/market/main.php`, `src/FinancialTransactionService.php`). **Nie uruchamiano PHP, MySQL ani HTTP.**

- **Potwierdzone po weryfikacji:** stary `/sell` ignoruje `amount`; `Storage::sellAll` naprawdę liczy przychód od całego `used` i ustawia `used = 0`. Po błędzie w wewnętrznej transakcji `public/sell.php` loguje i przekierowuje na `/` bez komunikatu dla gracza. To nie jest dowód zachowania domyślnego formularza rynku.
- **Korekta wniosku Qwena:** stwierdzenie, że wpisanie ilości w zwykłym formularzu gracza sprzedaje cały magazyn, było zbyt szerokie. Aktualnie podłączony widok `templates/views/market/main.php:51–73` używa komponentu `templates/components/form_sell.php` i POST na bieżącą stronę; `public/market.php:25–44` przekazuje `amount` do `MarketSaleService::sellInstant()`, które kontroluje zakres i odejmuje podaną liczbę. Przeszukanie źródeł PHP nie wykazało `include/require` starego `public/form_sell.php`; jego osiągalność i użycie produkcyjne nie zostały sprawdzone przez HTTP. Trasa `/sell` istnieje w `.htaccess:91`.
- **Korekta precyzji:** Qwen podawał błędne numery wierszy `public/sell.php` (wywołanie `sellAll` jest w wierszu 30, nie 23). Ograniczenie o nieznanej implementacji `sellAll` rozstrzygnięto odczytem `src/Storage.php:83–105`; nie przypisywać zwykłemu PDO wsparcia dla zagnieżdżonych transakcji bez analizy użycia savepointów. Zaproponowany przez Qwena test POST nie został wykonany i odnosi się tylko do starej trasy.

### 6.7. Druga nadzorowana kontrola Qwen — rynek i oferty

Qwen otrzymał pełne, ponumerowane treści trzech nowych plików: `public/market.php`, `public/market_offers.php`, `templates/views/market/main.php`, oraz zależności `src/MarketOffer.php`, `templates/components/form_sell.php`, `assets/js/market.js` i reguły tras z `.htaccess`. Odpowiedział bez uruchamiania aplikacji; wszystkie nowe pliki odczytano niezależnie, a istotne zależności i schemat `tests/ci-schema.sql` sprawdzono punktowo. **Nie wykonywano PHP/MySQL/HTTP ani testu przeglądarkowego.**

- **Potwierdzone:** `MarketOffer::cancelOffer` zwraca sukces bez klucza `returned_amount`; `public/market_offers.php` używa brakującego klucza do lokalnej aktualizacji magazynu. Serwis wcześniej zapisuje zwrot ropy w `storage` i commit (`src/MarketOffer.php:282–310`), więc nazwanie tego przez Qwena „krytyczną utratą ropy” jest **fałszywe**. To problem nieaktualnego widoku w starszej, osobnej stronie; `/market` odczytuje stan ponownie. Brak PRG w `/market-offers` jest faktem kodowym, ale powtórzenie i jego skutek zależą od działania przeglądarki, tokenu, limitera i dostępnego stanu.
- **Potwierdzona rozbieżność kontraktu, bez dowodu podatności:** formularze ograniczają `limit_price` do 50–500 (`templates/components/form_sell.php:32`, `public/market_offers.php:110`), natomiast `MarketOffer::createOffer` odrzuca tylko wartości `<30` i nie widać tam górnej granicy (`src/MarketOffer.php:87–92`). Wymaga decyzji o regule biznesowej oraz testu granic w izolacji, nie etykiety „exploit”.
- **Hipoteza Qwena zweryfikowana negatywnie w dostępnym schemacie CI:** `createOffer` nie wpisuje `editable`, ale `tests/ci-schema.sql:1386–1400` deklaruje `editable DEFAULT '1'`. Nie twierdzić, że edycja nowych ofert jest zablokowana; stan schematu produkcyjnego i runtime niepotwierdzone.
- **Słaby wniosek Qwena o XSS odrzucony:** `t()` escapuje HTML (`src/i18n.php:40–50`), `tPlain()` nie (`60–70`), a `templates/components/alert.php:8–20` wypisuje komunikat bez escapingu. W rozpatrywanych gałęziach użyto stałych kluczy tłumaczeń, nie wykazano wejścia gracza trafiającego do tego sinka. To temat przeglądu zasad escapingu, **nie potwierdzona podatność XSS**.

### 6.8. Trzecia nadzorowana kontrola — `alibaba-token-plan:qwen3.8-max`

Zweryfikowano identyfikator `qwen3.8-max` w dokumentacji Alibaba Token Plan oraz krótką odpowiedzią rzeczywistego endpointu planu w regionie Singapore; konfigurację modelu domyślnego Hermes zmieniono z `qwen3.7-plus` na `qwen3.8-max` bez przywracania lokalnego GGUF. Pierwsza duża próba na trzech pełnych plikach i fragmentach zależności **nie dała odpowiedzi przed limitem 240 s** — nie liczono jej jako kontroli modelu. Druga, krótsza próba z pełnymi `public/boardroom_status.php` i `templates/views/boardroom/main.php` oraz fragmentem `public/boardroom.php` dała wynik. Nadzorca niezależnie odczytał komplet trzech nowych plików i sprawdził zależności w JS, ticku i HR. To próba statyczna, **bez uruchamiania aplikacji, PHP, MySQL i przeglądarki**.

- **Potwierdzone warunkowo:** `boardroom_status.php` uznaje za powód przeładowania tylko `pending` z minionym `ready_at` (17–25, 50–55). JS pobiera tę flagę co 15 s i przeładowuje stronę (724–732). Gdy tick (`src/Tick/BankSection.php:178–190`, `src/HR/RecruitmentTrait.php:227–255`) zakończy przetwarzanie przed pierwszym odczytem statusu, `ready` już nie wyzwoli przeładowania, a dane sali pozostaną z początkowego renderu; przy długo utrzymującym się `pending` może dojść do kolejnych przeładowań. Nie ma dowodu na występowanie tych harmonogramów w produkcji. `assets/js/recruitment.js:189–213` ma osobny polling **po rozpoczęciu rekrutacji z jego modalu**, więc nie wolno twierdzić, że każdy otwarty modal nie może pokazać nowych kandydatów.
- **Doprecyzowanie odpowiedzi modelu:** Qwen napisał, że w dostarczonym wycinku nie widać wywołania batcha. W pełnym kodzie jest ono w `BankSection::runHR` (183). Status endpointu nie filtruje `initiated_by`/`spec_code`, choć widok sali filtruje rekrutacje dyrektorskie; może to wywoływać przeładowanie sali z powodu innej rekrutacji. Endpointowe `candidate_count` ma szerszy filtr niż widok, lecz w `boardroom-dynamic.js` konsumowane jest tylko `d.reload` — **nie przypisano rozbieżności tego licznika widocznemu błędowi UI**.

### 6.9. Porównanie A/B — ten sam prompt dla `qwen3.7-plus` i `qwen3.8-max`

Jedno identyczne zadanie statyczne o pollingu sali zarządu, z tymi samymi wycinkami plików i instrukcją odpowiedzi do 250 słów, przekazano obu modelom przez `alibaba-token-plan`, `--ignore-user-config`, `--max-turns 1`, bez narzędzi i bez zmian w repozytorium. Tekst wiadomości użytkownika w obu zapisanych sesjach jest bajtowo identyczny po UTF-8 (SHA-256 `1cecdfb399a870b843bcfeb771dc15b6f17ca10730714a9eb15e6e014aaff90f`); lista narzędzi i prompt systemowy były takie same poza wierszem nazwy modelu. To pojedynczy przypadek, nie powtarzany benchmark jakości ani szybkości.

| Kryterium | `qwen3.7-plus` | `qwen3.8-max` |
|---|---|---|
| `pending` po `ready_at` może wywoływać kolejne reloady do czasu zmiany stanu | Trafnie, warunkowo | Trafnie, warunkowo |
| Rekord `ready` sam nie ustawia `reload=true` | Trafnie | Trafnie |
| Wyraźnie nazwana konsekwencja: tick przed pierwszym pollingiem może pozostawić otwartą salę z nieaktualnymi oznaczeniami | Nie wskazał skutku dla UI | Nie wskazał skutku dla UI |
| Dodatkowe twierdzenie | Uznał reload przed wygenerowaniem kandydatów za „błąd”; kod potwierdza kolejność, nie wykazuje samodzielnego defektu | Wskazał szerszy `candidate_count`; różnica filtra może wystąpić, ale to pole nie jest używane przez JS sali, więc nie dowodzi błędu widocznego dla gracza |
| Czas pomiędzy zapisem pytania i odpowiedzi w sesji | ok. 59,4 s | ok. 59,6 s |

**Werdykt:** porównanie nie wykazało przewagi 3.8 w trafności ani czasie na tym zadaniu. Oba modele poprawnie odczytały główny warunek, ale wymagały nadzoru; wcześniejsza duża próba 3.8 przekroczyła 240 s i nie stanowi pary porównawczej. Czas sesji zawiera narzut platformy i nie jest pomiarem samej inferencji. Nie wykonano testów aplikacji; w chwili porównania liczba kart wynosiła 774.

### 6.10. Nadzorowane kontrole `qwen3.8-max` — ulepszenia, mapa, rejestracja i recovery

Siedem krótkich zapytań do zdalnego Maxa zakończyło się odpowiedzią (`exit 0`): dwa o ulepszeniach/sprzęcie, dwa o mapie/widoku, jedno o rejestracji, jedno o recovery/magazynie i punktowy follow-up o transakcji mapy. Model dostał **pełną treść 11 nowych plików** z numerami linii i blobami; `templates/views/recovery/main.php` przeczytał w całości tylko nadzorca (Max dostał wycinki). Zależności `src/WorldMap.php`, `src/Well.php`, `src/Well/ActionsTrait.php`, `src/Bankruptcy/*`, `src/Auth.php`, `src/FinancialTransactionService.php` i tick sprawdzano punktowo; ich odczyt nie jest nową kartą. Bez uruchomienia PHP, MySQL, HTTP, aplikacji ani testów; limit planu zdalnego nie został potwierdzony jako wyczerpany.

- **Ulepszenia:** `public/upgrade_storage.php:73–74` ignoruje `false` z `Storage::upgrade()`. Możliwy commit opłaty bez przyrostu przy przechwyconym błędzie UPDATE, ale **nie wynika to z samego bankructwa**, bo `Player::canAfford()` blokuje je wcześniej. `public/upgrade_well.php:82–93` rozpoczyna transakcję i wywołuje `Well::upgrade()`, które na tym samym PDO robi drugie bezwarunkowe `beginTransaction()`; kod FTS używa savepointu tylko we własnej jednostce. Max błędnie ogłosił pewną podwójną opłatę i błędną ścieżkę `common.app_error`: w tej ścieżce drugie `beginTransaction()` rzuca przed wewnętrznym `try`, `Well::upgrade()` zwraca `false`, a endpoint wycofuje debit i raportuje `err_failed`. Rozstrzygnięcie objawu wymaga izolowanego PHP/MySQL.
- **Sprzęt i status:** po sprzedaży wiersz `sold` pozostaje z tym samym właścicielem. `set_tier` nie ma walidacji statusu i może zmienić go w `equipment_swap`; tick po terminie zamienia zapamiętane `sold` na `active` (`src/Tick/WellStatusHandler.php:33–45`). Ten warunkowy łańcuch, pominięty w odpowiedzi Maxa z niepełnym fragmentem ticka, wymaga testu integracyjnego. `upgrade_level` ma inną, serwerową listę blokad; sama blokada przycisku w widoku nie zabezpiecza `set_tier`. Przy wywołaniu usługi wewnątrz transakcji innego callera `upgrade_level` ma także bezwarunkowe rollbacki w trzech gałęziach — nie przypisywać tego publicznemu endpointowi bez dodatkowego callera.
- **Mapa:** aktualny POST `/map` używa `location_id`; osobny `well_shop_grid.php` wysyła `well_id`, pokazuje limit 5 i nie znaleziono jego dołączenia w bieżącym widoku. Serwis mapy egzekwuje 10 oraz sprawdza zezwolenie (`WorldMap.php:225–239`); komponentu nie traktować jako aktywnego kontraktu. **Odrzucona hipoteza transakcyjna:** punktowy follow-up Maxa wyliczył osiem wczesnych `return` po `beginTransaction` i nie widział rollbacku, ponieważ dostał tylko wiersze 178–276. Pełna metoda ma `finally` z rollbackiem przy aktywnej transakcji w wierszach 376–378; nie wpisano niezamkniętej transakcji jako ustalenia. Również nie wykazano XSS ani awarii zakupu w widoku.
- **Rejestracja i recovery:** kontroler rejestracji ignoruje `false` z wysyłki maila i pokazuje sukces mimo możliwego braku dostarczenia; Max zbyt szeroko uznał, że niepowodzenie zawsze oznacza brak tokenu. Sukces `registerPendingVerification()` zawsze zwraca identyfikator i nazwę — hipoteza Maxa o ich cichym braku nie jest wykazana. Dla recovery potwierdzono niezależnie, że GET może wykonywać UPDATE i przetwarzać przeterminowane zdarzenia, nawet z zajęciem odwiertu; nie utożsamiać disabled w HTML z autoryzacją, ale `applyEmergencyLoan()` naprawdę ponawia kontrolę credit_score, a `applySellStorageAsset()` ponawia limit pojemności. Magazyn w karcie odwiertu jest wspólny dla gracza: `well_id` w `upgrade_storage.php` służy tylko do logu.

**Granice:** Nowe karty to 12 odczytanych w całości plików; przegląd kilkunastu zależności nie podnosi licznika. Odrzucone tezy Maxa pozostają w tej sekcji, nie w tabeli potwierdzonych problemów. Brak potwierdzenia awarii poczty, utraty środków, reaktywacji w bazie produkcyjnej czy konkretnego zachowania przeglądarki.

### 6.11. Kontrola komponentów ofert i korekta poprzedniego komunikatu

W poprzedniej odpowiedzi czatu błędnie podano **792** karty i ogłoszono niedziałający przycisk `buy` oraz brak warunku `status` w UPDATE. W tamtym momencie raport i identyczna kopia miały tylko **786** kart. Pełny `public/market_offers.php:23–65,87–118` nie renderuje przycisku `buy`, a `src/MarketOffer.php:196–238` blokuje ofertę w `lockOffer()` (`30–36`, na MySQL `FOR UPDATE`) i aktualizuje tylko `status='pending' AND editable=TRUE` (214–223). `createOffer()` ma ponowne `UPDATE storage ... used >= :amount` i kontrolę `rowCount` (`100–115`). Te wcześniejsze twierdzenia **odrzucono**, bez przypisywania im kart czy testu runtime. Delegowany agent nie ukończył pracy po powtarzających się identycznych wywołaniach; nie zaliczono jego niezweryfikowanego wyniku ani nie uznano tego za limit planu Qwen.

Dwie nowe, krótkie kontrole `alibaba-token-plan:qwen3.8-max` zakończyły się `exit 0` i objęły w całości sześć wcześniej nieskartowanych komponentów: `my_offers_table.php`, `offers_table.php`, `urgent_offer_alert.php`, `alert.php`, `status_grid.php`, `form_input.php`. `assets/js/urgent_offer_alert.js` również przeczytano i przekazano Maxowi w całości, lecz miał już kartę, dlatego nie podnosi licznika. Kontrolery, `MarketOffer`, `GameShell`, `modal.js`, routing i widoki były punktowymi zależnościami. Statyczny odczyt uzupełniono izolowaną próbą konstrukcji HTML w przeglądarce i `node --check` skryptu alertu; **nie uruchomiono PHP/MySQL/SMTP/HTTP aplikacji ani produkcji**.

- `my_offers_table.php:65–81` pobiera dowolny tekst z `promptInput` (`assets/js/modal.js:328–354`), a `parseFloat(newPrice)>=50` dopuszcza np. tekst zaczynający się od liczby z dalszym HTML. Następnie surowy tekst wpada do `form.innerHTML` jako wartość atrybutu (74). W izolowanej przeglądarce sprawdzono, że taki ciąg może utworzyć element `<img>` i uruchomić jego `onerror`. **To lokalne wejście użytkownika (self-XSS/niebezpieczny sink), nie dowód zdalnego XSS przeciw innemu graczowi**; kontrolery konwertują cenę do `(int)`, co nie naprawia wcześniejszego wykonania w DOM. Przy migracji użyć elementów DOM i `.value` zamiast konkatenacji HTML.
- `urgent_offer_alert.php:79–82` ma względny cel `market_offers.php`; zgodnie z rozwiązywaniem URL daje `/market_offers.php`, którego nie ma w śledzonym katalogu głównym ani regułach `.htaccess`. Osobno `/market-offers` mapuje na `public/market_offers.php` (`.htaccess:99`). Nie potwierdzono 404 na produkcji, gdzie mogą istnieć stare pliki FTP. `offers_table.php` jest prezentacyjny i nie znaleziono literalnego podłączenia w bieżących PHP; nie twierdzić, że każdy historyczny/dynamiczny caller nie istnieje.
- `alert.php` wypisuje surowe komunikaty, `status_grid.php` miesza escapowane atrybuty i surowy kontrolowany HTML ikon/etykiet/wartości, a `form_input.php` escapuje `$inputValue`, lecz nie wszystkie pozostałe atrybuty. W sprawdzonych callerach rynku/rejestracji i `GameShell::statusItems` nie znaleziono nieufnego źródła trafiającego do tych sinków (`t()` escapuje w `src/i18n.php:40–50`). To kontrakty do zachowania/uszczelnienia, **nie potwierdzone XSS**. Max błędnie uznał `register.php` za bezpośredni caller `alert.php`; w rzeczywistości ten kontroler dołącza własny widok rejestracji.

**Stan po tej partii: 792 karty dopiero po poniższym dodaniu sześciu nowych kart i walidacji.** Nie potwierdzono wyczerpania limitu Maxa; obie próby odpowiedziały.

### 6.12. Kolejne komponenty i kontrola rzeczywistego zakresu testów

Zlecenie Maxowi sześciu pełnych plików przekroczyło 240 s (`exit 124`, bez odpowiedzi), dlatego **tego wyniku nie zaliczono**. Dwa krótsze wywołania o `game_shell.php` oraz trzech małych komponentach zwróciły odpowiedź (`exit 0`). Nadzorca sam przeczytał pełne cztery komponenty i trzy testy (`MySqlMarketOfferOwnershipTest.php`, `MarketNotificationRouteTest.php`, `DashboardNewsWidgetTest.php`), sprawdził śledzonych callerów, `GameShell`, `MarketOffer::executeOffer()` i skrypt wiadomości. Testy miały już karty; nowe są wyłącznie **cztery** komponenty. Ani timeout, ani odpowiedź Maxa nie dowodzi wyczerpania planu.

- `game_shell.php:20` używa `require $gameShellView` i `:15` może emitować surowy `$gameShellTitleHtml`, ale znalezione kontrolery (`public/bank.php`, `boardroom.php`, `chat.php`, `contracts.php`, `dashboard.php`, `finance.php`, `hr.php`, `legal.php`, `logistics.php`, `map.php`, `market.php`, `sabotage.php`, `technical.php`) przypisują ścieżki widoków literalnie z `__DIR__`; pokazany tytuł HTML pochodzi z `tPlain('bank.title')`. **Nie stwierdzono LFI/XSS** z samego sinka. Max wskazał właściwą zależność do sprawdzenia, nie gotowy exploit.
- `card.php` i `limit_alert.php` wypisują surowe zmienne, lecz nie znaleziono ich literalnych callerów w bieżących PHP; nie przedstawiać ich jako czynnych luk. `news_panel.php` również nie ma literalnego dołączenia, ale **wiadomości dashboardu nie są przez to nieaktywne**: `templates/views/index/main.php:120` dołącza `activity_center.php`, który ma `id="newsList"` (`:55`); `assets/js/dashboard_news.js:8–9` wyszukuje ten identyfikator. Max nie znał tej zależności. `t()` escapuje, `tPlain()` w atrybutach panelu jest escapowane lokalnie.
- `MySqlMarketOfferOwnershipTest.php:27–67` wywołuje dwukrotnie `executeOffer` na tej samej starej migawce oferty i oczekuje jednej wpłaty/historii/transakcji; **nie symuluje dwóch równoległych połączeń**. Metoda usługi ponownie pobiera `pending` z `FOR UPDATE` na MySQL (`src/MarketOffer.php:364–392`) i sprawdza `rowCount()` aktualizacji z warunkiem statusu (`414–434`); `catch` wycofuje aktywną transakcję (`458–465`). `MarketNotificationRouteTest.php` oraz `DashboardNewsWidgetTest.php` robią wyłącznie asercje tekstowe; ten drugi nie sprawdza, czy `news_panel.php` jest dołączany, choć inny aktywny komponent zapewnia ten sam element. **Nie uruchamiano tych testów**: baza MySQL w `MySqlIntegrationTestCase::setUp()` podlega DELETE, a izolowanego połączenia nie przygotowano.

Stan po tej partii: **796** unikalnych kart, bez potwierdzonego limitu zdalnego Maxa.

### 6.13. Transfer, reset OPcache, wypis z newslettera i trasy stron

Trzy krótkie zapytania do `alibaba-token-plan:qwen3.8-max` odpowiedziały (`exit 0`): dwie pary pełnych plików `public/` oraz pełny `admin/pages_editor.php` z wycinkiem `.htaccess`. Nadzorca niezależnie odczytał pięć nowych plików w całości, sprawdził trasowanie, pełne `CashTransferService::doTransfer()` wraz z `catch`, ograniczenia `WalletConfig`, `Database`, `Auth`, `GameLog`, `getLocale`, `templates/header.php`, istniejące testy i fragment workflow wdrożenia. Wzorzec regex i kotwicę edytora porównano z bieżącym `.htaccess` w pamięci poza aplikacją: zero dopasowań, zero zmian; **bez POST, PHP, MySQL, FTP ani HTTP produkcji**.

- **Edytor stron:** `admin/pages_editor.php:36–40` oczekuje bloku `#  BEGIN static_pages `, lecz `.htaccess:115–117` ma `# BEGIN static_pages`; wstawienie z `:54–58` szuka nieistniejącego `#  Wszystko inne  public/`. Reguły nie są synchronizowane mimo zapisu stron w DB. Max zasugerował, że usunięta strona nadal odpowiada; doprecyzowanie: stara **reguła** zostaje, lecz `public/page.php:45–61` odczytuje tylko aktywny rekord i bez niego odpowiada 404. Bez sprawdzenia serwera nie twierdzić, że nowa strona jest niedostępna każdym sposobem (bezpośredni `public/page.php?slug=...` nadal może działać).
- **Transfer portfela:** Max zauważył rzutowanie surowego tekstu do `(float)` w kontrolerze (`wallet_transfer.php:43–48`), lecz nie znał `CashTransferService`. Serwis zaokrągla i waliduje minimum/maksimum (`85–96,239–253`), ponawia sprawdzenie salda po `FOR UPDATE` (`125–154`), w tej samej transakcji zapisuje audit trail lub wycofuje własną transakcję (`156–211`). Odrzucono tezę, że sam input `abc`, kwota ujemna lub nieskończona dowodzi ujemnego transferu/wycieku środków. Pozostaje kontrakt parsowania: ciąg zaczynający się od poprawnej kwoty, lecz zawierający dalsze znaki, nie jest jawnie odrzucany w kontrolerze; możliwa rozbieżność z intencją gracza, bez testu PHP/UI.
- **OPcache:** `opcache-reset.php:43–59` odmawia przy braku tokena i porównuje przez `hash_equals`, ale przyjmuje token z URL, a `.github/workflows/deploy-ftp.yml:350–354` używa właśnie query string. To **ryzyko ujawnienia przez logowanie adresów**, nie stwierdzone ujawnienie ani aktywny sekret w raporcie. Kanał nagłówka już istnieje; nie kopiowano wartości tokena ani `.env`.
- **Newsletter:** `newsletter_unsubscribe.php:13–40` aktualizuje tylko `newsletter_subscribed` dla pasującego tokena. `Database.php:39–42` zapewnia `FETCH_ASSOC`, `Auth.php:217` i `admin/newsletter.php:59` generują 16 losowych bajtów jako 32 znaki hex, `GameLog::error` obsługuje trzeci argument `Throwable` (`GameLog.php:87–108`). Odrzucono hipotezy Maxa o niezgodności tych trzech kontraktów; GET z jednorazowym skutkiem to obecny projekt wypisu, bez dowodu exploitu.
- **Strona statyczna:** `public/page.php:27–30` łączy ścieżkę szablonu wyłącznie z `getLocale()`, które stosuje allowlistę `pl/en/de` (`src/i18n.php:8–13`), więc hipoteza manipulacji ścieżką została odrzucona. Surowe `$page['content']` (`:78`) jest świadomym kontraktem HTML zapisanym przez uwierzytelnionego administratora (`admin/pages_editor.php:5–6,65–82`); nie wykazano źródła nieufnego gracza. Za to `$pageTitle` w `:64` jest escapowane przed dołączeniem `templates/header.php:84`, który escapuje je ponownie — tytuł z `&` będzie wyświetlany z encją. `LegalPagesLocalizationTest.php` sprawdza locale/klucze i pliki, nie render tytułu; nie uruchomiono testu.

**Stan po tej partii: 801 unikalnych kart, pięć nowych poniżej.** Limit planu Maxa nadal nie jest potwierdzony jako wyczerpany; trzy próby odpowiedziały.

### 6.14. Jedna partia 20 plików widoku logistyki

Zamrożono **20 wcześniej nieskartowanych plików** `templates/views/logistics/` z HEAD, pogrupowano bez nakładania w cztery porcje po 4/6/5/5 o zbliżonym rozmiarze. Max otrzymał pełną treść każdego pliku z numerami linii i blobem; cztery wywołania `alibaba-token-plan:qwen3.8-max` odpowiedziały (`exit 0`). Nadzorca odczytał wszystkie 20 plików w całości i niezależnie sprawdził aktywną ścieżkę `public/logistics.php:16–22,49–52` → `LogisticsPageController::buildViewData()` → `extract($viewData)` → `game_shell.php` → `templates/views/logistics/main.php:1–67`. Odczyt `LogisticsPageViewContractTest.php` i fixture/testów incydentów jest kontrolą zakresu testów, **nie uruchomieniem PHP lub przeglądarki**.

- Max wysunął liczne warunkowe ostrzeżenia o niezdefiniowanych `$wells`, `$hubCards`, `$activeWellCount`, `$storageBbl`, `$marineMinLoadBbl`, `$currencyLabel`, ale `src/LogisticsPage/ViewData/OverviewAndProtectionData.php:11–121`, `PipelineAndInsightsData.php:384–482` oraz `main.php:1` inicjalizują wymagany kontrakt przed renderem. `LogisticsPageViewDataTrait.php:28–36` sprawdza komplet kluczy, a `public/logistics.php:50` je rozpakowuje. Nie przypisano zwykłej stronie produkcyjnej błędu z samodzielnego include fragmentu. `CSRF::generateToken()` nie rotuje tokena przy kolejnych wywołaniach (`src/CSRF.php:5–12`), a `t()` escapuje (`src/i18n.php:40–50`). Klucze CTA rekomendacji są stałe w `PipelineAndInsightsData.php:342–381`, więc `decisions_section.php:9` nie stanowi wykazanego `javascript:`-sink z danymi gracza.
- `hub_incidents_section.php:38–43,69–71` zakłada ciągłe indeksy listy; aktualny `LogisticsIncidentFeed::page():15–28` daje `fetchAll(PDO::FETCH_ASSOC)`, bez `array_filter` czy zachowania dziur, więc hipotezy Maxa o niezamkniętym `<details>` **nie potwierdzono w aktywnym przepływie**. SQL wybiera `message` i `created_at` (`:39–54`). Użyty tutaj `visibleHubIds` ogranicza link do obiektów widocznych w kartach; brak dowodu ujawnienia cudzego huba. Testy fixture/syntetycznej bazy nie są produkcją.
- **Potwierdzony błąd kontraktu linków:** `unassigned_wells_section.php:54,59` tworzy paginację od nowego `?tab=logistics&unassigned_page=N`, usuwając pozostałe parametry z tej samej strony (`incident_q`, `incident_severity`, `hub_market_q`, `road_page`). W sąsiednich `road_trips_section.php:32–45` i `hub_incidents_section.php:73–88` linki dziedziczą `$_GET` przez `http_build_query()` i escapują atrybut. Izolowane rozwiązanie URL potwierdziło zniknięcie przykładowych parametrów; brak testu HTTP.
- **Błąd prezentacji dla `de`:** `partials/bootstrap.php:2–6` obsługuje `EUR`, ale przypisuje `$currencyLocale='pl-PL'` dla każdego języka poza `en`; `getLocale()` dopuszcza `de` (`src/i18n.php:8–13`). Wartość przechodzi przez `client_config.php:9,61` i `assets/js/logistics_config.js:21–33` do formatowania w `logistics_optimizer.js:6,28–35` oraz `logistics_hubs.js:198`. Nie jest to błąd kursu ani płatności, tylko lokalizacja wyświetlania; brak oględzin UI.
- Pozostałe części to szkielet/modale, policzone KPI i czytanie przygotowanych danych. `transport_table.php` dostaje `id/capacity_pct/loss/cost` z `LogisticsService::getCurrentSummary():334–379`; `unassigned_wells_section.php` dostaje `region_id/base_production_per_hour` z `Hub/PlayerQueryTrait.php:188–213`; `road_trips_section.php` dostaje `well_id/eta_at` z `RoadTransportService.php:627–653`. `LogisticsPageViewContractTest.php:70–123` testuje tekstową listę include i kontroler, nie zachowanie pod wpływem GET; `tests/logistics_design_browser.cjs` i fixture wymagają PHP/Playwright, nie były wykonane. Żadnej hipotezy o brakujących kluczach nie awansowano do P1 bez realnego przepływu.

**Stan po tej partii: 821 unikalnych kart po walidacji poniższych 20 wpisów.** Max odpowiedział na wszystkie cztery porcje; wyczerpania limitu nie potwierdzono.

### 6.15. Jedna partia 40 małych plików PHP: panel admina, HR, prywatność i komponenty

Z nieobjętych kartami plików PHP wybrano **40 unikalnych ścieżek** o najmniejszym rozmiarze spośród `admin/`, `public/`, `templates/` i `src/` (253–2886 B; 70 625 B łącznie). Manifest utrwala HEAD, blob, fizyczne linie i osiem rozłącznych grup po pięć. Max dostał pełne, numerowane treści w ośmiu krótkich promptach; wszystkie osiem wywołań `alibaba-token-plan:qwen3.8-max` zwróciło `exit 0`. Nadzorca przeczytał pełne 40 źródeł i osiem odpowiedzi, sprawdził wskazane callery, funkcje tłumaczeń/CSRF, bramkę admina i pełne granice POST/catch/redirect. **Nie zaliczał fragmentów zależności jako kolejnych kart.**

- **Reset hasła admina:** `admin/forgot_password.php:8–28` wystawia formularz anonimowemu użytkownikowi i waliduje CSRF, który sam generuje przy GET. `AdminAuth::sendPasswordReset():415–455` nie limituje próśb: dla istniejącego adresu najpierw usuwa wcześniejszy reset, potem zapisuje nowy token i wysyła wiadomość przez `Mailer::send():15–59`, również bez limitera w tym wrapperze. **P2 warunkowo**: wielokrotne ważne POST-y mogą unieważniać linki i obciążać skrzynkę; logowanie i WAF poza kodem niebadane. Surowe `$_POST['email']` wraca do widoku po `htmlspecialchars` (`templates/views/admin/forgot_password/main.php:26–31`), więc sugerowanego przez Maxa XSS z tego pola nie potwierdzono; odpowiedź na reset jest generyczna.
- **Finanse admina:** `admin/finance.php:8,30–39` ogranicza dostęp i wymaga CSRF, a handler ma zamkniętą listę trzech akcji. `admin/partials/finance_admin_actions.php:121–132` wykonuje dwa DELETE resetu polityki osobno, bez beginTransaction/rollback: jeśli drugi rzuci, pierwszy może zostać utrwalony mimo błędu. `:136–185` podobnie zapisuje wiele wartości konfiguracji osobno. **P2, fault injection nie wykonano.** Nie twierdzić, że jest to otwarta droga publiczna ani że baza już uległa rozjazdowi.
- **Ślad admina:** wybrany `admin/AdminLog.php` ma treść bajtowo identyczną z faktycznie ładowanym przez `admin/init.php:13–14` plikiem `src/AdminLog.php`. `log():15–43` łapie błąd INSERT i go nie propaguje, zaś `admin/player_menu_save.php:20–27` może zwrócić `ok=true` po zapisie mimo nieudanego wpisu do `admin_logs` (ma dodatkowy best-effort GameLog). To ryzyko kompletności audytu operacji, nie stwierdzona utrata wpisów w produkcji. `AdminAuth::setSession():115–122` zapisuje zarówno `admin_id`, jak i `admin_user`; hipotezę Maxa o trwałym fallbacku nazwy `admin` z powodu rozbieżnych kluczy sesji odrzucono.
- **Hipotezy Maxa odrzucone po call-chain:** `templates/views/admin/hr/main.php:59` ma dynamiczne `require`, lecz `admin/hr.php:16–31` ogranicza GET do listy zakładek, a klasyczne POST-y kończą się przekierowaniem `:536–544`; `specializations`/`candidates` są aliasowane ponownie przy GET. Nie ma potwierdzonego path traversal ani próby bezpośredniego include nieistniejącego pliku po POST. `templates/views/admin/privacy/main.php:32–36` bierze ścieżkę ze statycznych klas registry, a kontroler allowlistuje tab dla GET/POST (`admin/privacy.php:26–52`). `admin/bank_status.php:9` wywołuje prawdziwe admin `requireLogin()` (`src/AdminAuth.php:331–355`); nie dowiedziono IDOR zwykłego gracza. `game_navigation.php` dostaje ikony z `PlayerNavIcon::resolve()/url()` z walidacją nazw i plików, a linki z `PlayerMenuEditor::href()`; nie awansowano nieescapowanego `src` do XSS. `action_grid.php` używa stałej listy linków i `t()` z `public/index.php:309–340`, a `$actions` pochodzi z aktywnego kontraktu widoku; sam sink surowego HTML nie oznacza danych od użytkownika.
- **Granice utrzymania:** `templates/views/admin/change_password/main.php` jest osieroconym szablonem — aktualny `admin/change_password.php:43–77` renderuje formularz inline, a odwołań do pliku widoku nie znaleziono w kodzie PHP. `templates/views/privacy/banner.php:22` escapuje atrybut URL, ale nie sprawdza schematu; `BannerSettingsService.php:23–39` pozwala zalogowanemu adminowi skonfigurować dowolny ciąg. To ryzyko **niewłaściwej konfiguracji przez admina**, nie wykazany XSS od gracza; walidację względnej ścieżki/HTTPS przenieść do serwisu. `public/cookies-policy.php:14–25,39–42` renderuje celowy HTML z pliku lub aktywnej polityki admina; `getLocale()` allowlistuje `pl/en/de` (`src/i18n.php:8–13`). Treści polityki zapisuje tylko chroniony panel (`PolicyFeature.php:26–78`); nie przypisywać raw echo do niezaufanego użytkownika bez innej ścieżki edycji. Prawdziwości deklaracji prawnych nie oceniano.

**Wynik partii: 40/40 kart po walidacji HEAD/bloba/linii; 861 unikalnych kart łącznie.** Brak PHP w tym środowisku; nie wykonano PHP, MySQL, SMTP, HTTP, suity ani renderu. Nie wykazano wyczerpania limitu Maxa.

### 6.16. Domykanie audytu od początku — pierwszy przebieg ponownej weryfikacji

**Baza zamknięcia:** przeliczono `git ls-files -s -z` dla HEAD `09b07a97195836698e600215bb19013559aabf9a`: **5153 śledzone ścieżki**. Z 874 unikalnych kart tego raportu **874 mają blob zgodny z HEAD**; dla **4279** nie ma indywidualnej karty *w tym raporcie* (część ma dowody we wcześniejszych dokumentach i ledgerze, nie należy ich nazywać automatycznie nieprzeczytanymi). Osobny maszynowy rejestr w lokalnym katalogu roboczym przypisuje każdej ścieżce blob, obecność karty i stan zamknięcia (`OPEN` albo `CLOSED_DOCUMENT_ARCHIVE`); w tym przebiegu **1 dokument historyczny został zamknięty, a żaden plik aplikacyjny nie uzyskał certyfikatu zamknięcia** według kryterium z `06_FILE_BY_FILE_AUDIT_PROTOCOL.md:50–51`. To stan weryfikacji, a nie cofnięcie wcześniejszych odczytów. Karty z testem Node lub Python nie oznaczają jeszcze zgodności migracji.

- **Przepływ resetu admina — rozbudowany ślad statyczny, nadal otwarty.** `admin/forgot_password.php:8–28` → `AdminAuth::sendPasswordReset():415–455` → tabela resetów i `EmailTemplate::build():32–65`/`Mailer::send()` → `admin/reset_password.php:8–39,56–85` → `AdminAuth::verifyResetToken():457–467`/`resetPassword():469–516` → `admins.password_hash`. POST resetu ma CSRF i sprawdzenie potwierdzenia hasła; zużycie tokenu i zapis hasła mają jedną transakcję oraz rollback w `catch`. **Nowe ustalenie P1:** reset nie usuwa sesji ani wierszy zaufanych urządzeń; `isLoggedIn():367–376`, `requireLogin():331–353` i `tryTrustedDevice():703–740` umożliwiają dalszą autoryzację w warunkach opisanych w §2. Otwarta bramka: próba na izolowanej DB i dwóch sesjach + ważnym cookie, negatywne przypadki tokenu, odmowa po unieważnieniu, decyzja o sesjach/tokenach; konfiguracja hostingu/WAF oraz SMTP niezweryfikowane.
- **Przepływ finansów admina — rozbudowany ślad statyczny, nadal otwarty.** `admin/finance.php:7–39` wymaga loginu i CSRF przed `adminFinanceHandlePost():116–188`; `reset_player_policy` robi dwa `DELETE` bez wspólnej transakcji (`:121–132`), a zapisy konfiguracji są wieloetapowe (`:136–185`). PDO jest współdzielone przez `Database::getConnection():67–78`; `src/AdminLog.php:15–43` przechwytuje własny błąd INSERT. Otwarta bramka: izolowany MySQL, dodatni i ujemny POST, fault injection drugiego `DELETE` i zapisu logu, porównanie komunikatu z rzeczywistym stanem; nie uruchamiać istniejącej suity MySQL na bieżącym DSN.

- **Śledzony cache testów — pozostaje otwarty.** Pełny plik `.phpunit.cache/result-mysql` ma 23559 B i poprawny JSON z kluczami `version/defects/times`; `phpunit.mysql.xml.dist:7` wskazuje go jako plik cache. `.gitignore:1` nie usuwa wcześniej śledzonego pliku. `deploy-ftp.yml:203–210` myli `.phpunit_cache/` z `.phpunit.cache/`, więc wykluczenie nie pasuje; `.htaccess:71–74` przekierowuje tę ścieżkę, jeśli reguły serwera działają. Nie stwierdzono faktycznej publikacji ani odczytu HTTP; potrzeba kontroli artefaktów deployu i decyzji o usunięciu cache z dystrybucji.

**Reguła dalszego zamykania:** każdy plik autorski musi mieć potwierdzony pełny odczyt, callery i skutki DB/plików, decyzję migracyjną i wynik wymaganych testów; assety, vendor, dokumenty i testy mają osobne kryteria z protokołu. `OPEN` zmieniać dopiero po zapisaniu dowodu; nie mnożyć liczby kart jako liczby ukończonych audytów. Na tej maszynie nie znaleziono `php`, `mysql` ani `docker` w PATH; nie uruchomiono aplikacji ani testów na bazie. Konieczne są odizolowane środowisko i bezpieczny DSN, bez użycia produkcyjnych danych.

### 6.17. Weryfikacja pozostałego zachowanego materiału Maxa

Porównano lokalnie zachowane prompty/odpowiedzi z kartami i HEAD. **13 wcześniej przeanalizowanych przez Maxa endpointów API** miało osobny ledger z hipotezami i korektami, lecz nie miało indywidualnych kart w niniejszym raporcie. Nadzorca ponownie odczytał pełne źródła tych 13 plików (21 317 B łącznie), sprawdził ich bloby, fizyczne linie i treść roboczą względem HEAD, a następnie zweryfikował aktywne bramki, serwisy, schemat i klienta we wskazanych przepływach. Dopisano 13 kart poniżej; **874 unikalne karty, 13 nowych w tej kontroli**. Nie przenoszono bezkrytycznie odpowiedzi Maxa: `data.php` dostaje mapowane pola z `WellStaffService`, a `!empty($r['well_name'])` na brakującym kluczu nie emituje ostrzeżenia; wcześniejsza prywatna notatka sugerująca warning została skorygowana. Potwierdzono statycznie warunkową bramkę `class_exists('Player', false)` przy naprawie incydentu, pozbawiony allowlisty drugi caller `WellStaffApi::unassign`, rozdział UPDATE/INSERT historii powiadomienia i rozbieżność licznika UI; bez wykonania PHP/DB/HTTP.

W innych zachowanych promptach `qwen38-*` znaleziono **29 unikalnych plików oznaczonych pełnym źródłem**; wszystkie mają już kartę z blobem zgodnym z HEAD. Jest to **kontrola pokrycia kart**, nie ponowny pełny audyt semantyczny wszystkich 29 w tej turze. Część promptów nie ma zachowanej odpowiedzi, a próba `qwen38-next-components` zwróciła tylko krótki błąd zamiast analizy sześciu plików; nie zaliczać jej jako przeglądu Maxa. Zachowane partie 20 logistyki i ostatnie 40 PHP miały osobne kontrole opisane w §6.14–6.15. Nie da się z tych artefaktów dowieść, że **każdy** dawny prompt Maxa i wszystkie jego zależności zostały wyczerpująco przebadane; statusy plików aplikacyjnych pozostają otwarte do izolowanych testów i decyzji migracyjnych.

### 6.18. Nadzorowany głęboki przegląd Maxa: mobilna naprawa incydentu (nadal otwarty)

Max otrzymał siedem **pełnych plików** (`api/v1/technical/repair_incident.php`, `api/v1/_bootstrap.php`, `src/IncidentService.php`, `src/Incident/RepairDataTrait.php`, `src/ApiAuth.php`, `src/Market.php`, `src/Player.php`) oraz pięć jawnie oznaczonych wycinków zależności, wszystkie zgodne z migawką kodu `09b07a97`. Odpowiedź Maxa zachowano w lokalnym `oilcorp-max-repair-deep.out` (`exit 0`), a każdą poniższą tezę skonfrontowano z pełnymi metodami i dodatkowymi plikami (`i18n.php`, `MessagesTrait.php`, `CooldownTrait.php`, `ApiErrorHandler.php`, rozliczenie ticku, schemat). Nie jest to uruchomienie aplikacji.

- **Wejście i uprawnienie.** `_bootstrap.php:18–42`, `IncidentService.php:3–6` i dodatkowo sprawdzone pozostałe traity/i18n nie ładują `Player.php`; opcjonalna classmap z `composer.json:14–16` nie jest wywołana przez `class_exists('Player', false)` (`RepairDataTrait.php:23`). `ApiAuth.php:40–48` wpuszcza aktywnego gracza niezależnie od `recovery_mode`/`bankruptcy_status`, które `Player::isBankrupt():110–145` traktuje jako blokadę. **Statyczny warunkowy błąd bramki P1:** aktywny gracz w restrukturyzacji może przejść tę gałąź, choć status `bankrupt` nie przechodzi tokenowego auth. Incydent i wszystkie UPDATE-y są filtrowane po `player_id` (`RepairDataTrait.php:30–35,66–87`), więc nie wynika z tego dostęp do cudzych incydentów. Rzeczywisty wynik 422/200 wymaga świeżego izolowanego procesu PHP i bazy.
- **Wyścig naprawy P1, bez podwójnej opłaty z endpointu.** SELECT `repaired_at` jest przed `beginTransaction`, a UPDATE `well_incidents` nie ma `AND repaired_at IS NULL` ani kontroli `rowCount()` (`RepairDataTrait.php:30–45,61–70`). Dwa jednoczesne żądania po odczycie starego stanu mogą oba zwrócić sukces; przy `medium` oba wykonałyby `post_incident_risk_boost = ... * 0.5` (`:82–87`), czyli 20 → 10 → 5 zamiast 10. Również usunięcie incydentu między SELECT a UPDATE dałoby sukces przy zerze zmienionych wierszy. Wewnętrzny `catch` wycofuje własną transakcję; jeśli serwis wejdzie do cudzej transakcji, nie wycofuje jej sam (`:61–63,91–108`). Nie przeprowadzono dwóch połączeń DB ani fault injection.
- **Koszt, status i odpowiedź.** Docblock endpointu (`repair_incident.php:7–11`) błędnie obiecuje pobranie kosztu przy naprawie. `RepairDataTrait.php:48–53` tego nie robi; incydent zwiększa `finIncident` i `totalCosts` w `WellRiskHandler.php:203–217`, a `PlayerTickProcessingTrait.php:362–372` przekazuje je do `TickSettlementTrait.php:13–63`. **Korekta Maxa:** nie wolno twierdzić, że pełna kwota jest zawsze pobrana „raz” — `TickSettlementTrait.php:32–55` ogranicza pobranie do dostępnego salda i rozdziela budżet według kolejności kategorii. `major` przywraca tylko odwiert o statusie `broken`, `medium` połowi spiralę; przy zerowym `rowCount` aktualizacji odwiertu endpoint nadal może zwrócić sukces. `apiError()` daje `error` przy 400/405, a gałąź 422 daje `success/message` (`_bootstrap.php:48–63`, endpoint `:22–43`).
- **Bootstrap i korekty wypowiedzi Maxa.** Dla każdego żądania **poza OPTIONS** `_bootstrap.php:13–16,38–42` próbuje `CREATE TABLE IF NOT EXISTS api_tokens` i `INSERT IGNORE market_state` przed kontrolą metody i Bearer; w szczególności nieautoryzowany GET może mieć te efekty. Max błędnie zaliczył tu OPTIONS, które wychodzi już w linii 15. `GameLog::setEnabled(false)` wyłącza zwykłe wpisy `repairIncident_OK/FAILED` (`_bootstrap.php:31`, `GameLog.php:39–42`), ale nie wyłącza całej diagnostyki API: `ApiErrorHandler.php:25–41,58–85` może logować nieobsłużone błędy. To nie potwierdza zachowania produkcyjnego ani dostępności endpointu w hostingu.

**Decyzja migracyjna na tym etapie: naprawić/przeprojektować bramkę bankructwa i atomowy claim przed przeniesieniem zachowania; skorygować dokumentację opłaty. Status `OPEN`.** Brakuje izolowanych testów: aktywnego gracza w restrukturyzacji, cudzej własności, niepoprawnego ID/typu i metody, `auto_repair`/już naprawionego incydentu, dwóch równoczesnych POST, usunięcia wiersza między SELECT a UPDATE, błędu drugiego UPDATE z rollbackiem, wyników przy niedoborze środków oraz odpowiedzi HTTP. Nie uruchamiano suity MySQL na bieżącym DSN (jej setUp usuwa dane); brak `php`, `mysql`, `docker` w PATH podczas tej kontroli.

### 6.19. Nadzorowany przegląd Maxa: obsada odwiertu, web i mobile (nadal otwarty)

Max otrzymał sześć pełnych plików (`src/WellStaffApi.php`, `src/WellStaffService.php`, `api/internal/WellStaffApi.php`, `api/v1/technical/well_staff_unassign.php`, `assets/js/well_staff.js`, `.htaccess`) i wycinki `init.php`, widoku oraz schematu; odpowiedź `oilcorp-max-wellstaff-deep.out` (`exit 0`) zweryfikowano dodatkowo względem `CSRF.php`, szablonu obsady, `src/TTS/TasksTrait.php` i czytnika statusu ticku. **Karty tych plików już istniały; nie dodano nowych ani nie zamknięto audytu.**

- **Rzeczywisty caller.** `templates/views/technical/main.php:163–164` kieruje `well_staff.js:109–135` na `/api/internal/WellStaffApi.php` z POST i `_token`; adapter `api/internal/WellStaffApi.php:1–6` dołącza implementację. `src/WellStaffApi.php:56–67` wymaga sesji, a `:60–64` sprawdza CSRF (`CSRF.php:22–25` używa `hash_equals`). Bezpośrednie HTTP `/src/` przekierowuje widoczny `.htaccess:71–74`, ale to nie blokuje **adaptera**. `GET ?action=unassign` nie bierze `well_id`/`role` z query, lecz z `$_POST`, i nie jest alternatywną ścieżką usunięcia (`WellStaffApi.php:95–101`); nie potwierdzono routingu ani konfiguracji serwera produkcyjnego. Max spekulował o nginx/hostingu — nie przyjmujemy tego bez dowodu.
- **Rozjazd obsady P1.** W webowym POST `role` musi być tylko niepusta (`src/WellStaffApi.php:95–101`); `WellStaffService::unassign():170–196` nie sprawdza allowlisty. `unassignRole():358–365` filtruje assignment po przekazanej roli, ale operator/technician cache wybiera już ternary `$role === 'operator' ? 'operator_id' : 'technician_id'` (`:181–183`). Dla innej roli, **jeśli** zapytanie na `well_staff_assignments` nie dopasuje wiersza, a baza nie zgłosi błędu, prawidłowy odwiert traci cache technika bez zamknięcia aktywnego assignmentu; metoda zwraca sukces. Max założył szczegółową semantykę porównania MySQL `ENUM` dla `'foo'`/`'Operator'` — bez izolowanego MySQL pozostaje to hipotezą, nie wykonanym testem. Mobilny endpoint `api/v1/technical/well_staff_unassign.php:25–34` **ma** ścisłą allowlistę. UI zwykle podaje tylko poprawne role (`templates/views/technical/tabs/well_staff.php:41–46,70–75`), lecz klient nie zastępuje serwerowej walidacji; przy desynchronizacji brak przycisku dla technika przy pustym cache, mimo że assignment może nadal blokować ponowny przydział (`WellStaffService.php:68–88,330–341`, `well_staff.js:47–61`). Nie jest to dowód trwałej nieodwracalności: naprawę można rozważyć osobnym, poprawnym żądaniem/rekonsyliacją.
- **Niezależny aktywny scenariusz statusu P1.** Właściwa rola również może uszkodzić workflow: `TTS/TasksTrait.php:324–332` ustawia `wells.status='servicing'` oraz `service_prev_status`. `getWellsStaffStatus()` pokazuje taki odwiert (`WellStaffService.php:263–279`), a szablon ma przycisk odpięcia; `unassign()` nie blokuje `servicing` (`:170–185`). `refreshWellStatus():220–248` może zamienić ten stan na `no_operator`/`no_technician`, bo `servicing` nie znajduje się ani w liście wczesnego return, ani w SQL guardzie. Późniejsze odblokowanie `TTS/TasksTrait.php:590–603` zachodzi **tylko przy `status='servicing'`**, więc może pominąć przywrócenie statusu i czyszczenie `service_prev_status`. Nie wykazano w izolowanym ticku, jaki końcowy status zostanie po wszystkich efektach zadania. Dodatkowo Max wskazał warunkowy wyścig: `refreshWellStatus` odczytuje `active`, a inny writer zmienia go na `paused_cash`; SQL guard nie wyklucza `paused_cash` (`:243–248`).

**Decyzja migracyjna: naprawić walidację roli w serwisie i scalić reguły statusów przed odwzorowaniem web/mobile; dodać testy izolowane. Status `OPEN`.** Testy wymagane: `'foo'` i wariant wielkości liter przy MySQL z rzeczywistą kolacją, poprawne role, obcy `well_id`, brak przypisania, spójność `well_staff_assignments`/`wells`, odpięcie podczas aktywnego serwisu i zakończenie zadania, dwa równoległe writers dla `paused_cash`, render/kliknięcie UI oraz parytet adaptera i mobile. Nie uruchomiono destrukcyjnej suity ani produkcyjnego HTTP.

### 6.20. Nadzorowana kontrola Maxa: reset hasła administratora i współbieżność (nadal otwarty)

Pełniejsza próba Maxa z ośmioma plikami przekroczyła limit czasu i **nie dała odpowiedzi**, więc nie została zaliczona. Powtórzono węższe zadanie: pełne kontrolery `admin/forgot_password.php`, `admin/reset_password.php`, `admin/login.php` oraz jawnie oznaczone wycinki `src/AdminAuth.php` i `tests/ci-schema.sql`; `oilcorp-max-admin-reset-focused.out` zakończył się `exit 0`. Nadzorca samodzielnie sprawdził dodatkowo pełny `AdminAuth.php`, `src/Database.php`, `admin/logout.php`, `src/SessionCookie.php` oraz **pominięty przez Maxa indeks `admins` w schemacie** (`tests/ci-schema.sql:2690–2693`). To pogłębienie istniejących kart, nie test HTTP/DB ani zamknięcie ich audytu.

- **Nowa warunkowa luka tożsamości P1 (ważna korekta zakresu Maxa).** `sendPasswordReset():418–420` wybiera `id, username` jednego *aktywnego* admina po emailu, lecz do `admin_password_resets` zapisuje tylko email i hash tokenu (`:429–435`). `resetPassword():485–507` pobiera email i aktualizuje `admins.password_hash WHERE email = ?`, bez powiązania z pierwotnym `id`, bez filtra `is_active` i bez kontroli liczby wierszy. `tests/ci-schema.sql:30–42,2690–2693` ma unikalne `username`, **nie ma unikalnego `admins.email`**. Przy dwóch kontach o tym samym emailu jeden reset może zmienić oba hasła (również konta nieaktywnego); po usunięciu konta lub zmianie emaila może oznaczyć token jako zużyty, zatwierdzić transakcję i zwrócić sukces bez zmiany hasła. Max słusznie zaznaczył drugi wariant, ale nie miał indeksu `admins`; niezależnie sprawdziliśmy ten warunek. To **nie** dowód, że w produkcji występują duplikaty ani że atakujący potrafi je założyć.
- **Jednorazowość tokenu jest częściowo poprawna.** `resetPassword():485–505` sprawdza token przed transakcją, potem `UPDATE ... SET used=1 WHERE token_hash=? AND used=0` oraz `rowCount()`. Dla dwóch równoczesnych prób tego samego *wiersza* tylko jedna powinna go zużyć, a błąd w transakcji wykonuje rollback (`:509–512`); nie wpisywać tu bez dowodu „dowolna wielokrotna zmiana hasła”. `expires_at > NOW()` jest w SELECT (`:485`), ale **nie** w UPDATE (`:498`), więc token ważny przy odczycie może zostać zużyty po wygaśnięciu przy odpowiednim opóźnieniu. To hipoteza interleavingu do odtworzenia, nie wykonany test. Nie formułować z tego twierdzenia, że dowolny już wygasły link działa: pierwszy SELECT go odrzuca.
- **Dwa równoległe żądania wydania tokenu.** `sendPasswordReset():433–435` robi `DELETE` i `INSERT` bez transakcji; indeksy `admin_password_resets` to PRIMARY(id), zwykłe `idx_token` i `idx_email` (`ci-schema.sql:2731–2734`). Przeplot dwóch połączeń `DELETE A → DELETE B → INSERT A → INSERT B` *może* pozostawić dwa ważne tokeny, jeśli oba DELETE dojdą do skutku w tej kolejności; blokady InnoDB, izolacja i deadlock mogą zmienić wynik. To nie znosi poprawnej atomowości zużycia **pojedynczego** wiersza. Max zbyt pewnie opisał zachowanie przy `READ COMMITTED`/`REPEATABLE READ`; nie zaobserwowano żadnego przeplotu DB.
- **Weryfikacja wcześniejszego P1, nie nowe odkrycie.** Reset nie unieważnia istniejących sesji ani `admin_trusted_devices` (`AdminAuth.php:469–516`). `login.php:15–19` i `requireLogin():331–353` rzeczywiście wywołują `tryTrustedDevice():703–740`; poprawny, niewygasły cookie przy aktywnym, niezablokowanym koncie może przywrócić pełną sesję bez nowego hasła. `logout():379–390,650–667` cofa tylko cookie bieżącego klienta przy jawnym wylogowaniu; zwykły timeout `isLoggedIn():372–374` woła `logout(false,false)`. Nie stwierdzono przejęcia konta w działającej instalacji.

**Decyzja migracyjna: status `OPEN`.** Wiązać token z jednoznacznym identyfikatorem admina; ustalić regułę unikalności emaila na prawdziwych danych **bez wykonywania tu SQL na produkcji**; sprawdzać liczbę zmienionych kont i ważność tokenu przy atomowym zużyciu; zdefiniować centralne unieważnianie sesji i wszystkich zaufanych urządzeń po resecie; ograniczyć ponawianie maili. W izolowanej bazie i z atrapą poczty potrzebne są testy dwóch kont ze wspólnym adresem (w tym nieaktywnego), usunięcia/zmiany emaila przed POST, dwóch jednoczesnych resetów, dwóch jednoczesnych wydań tokenu przy różnych izolacjach, wygaśnięcia między SELECT/UPDATE oraz drugiej sesji i cookie po resecie. Nie uruchomiono obecnej suity MySQL ani prawdziwych maili.

## 7. Karty dowodowe tej kontynuacji

Poniżej szczegóły **874 kart statycznych/klasyfikacyjnych** (bez poświadczenia zamknięcia audytu plików aplikacyjnych). `SOURCE_READ_DEPENDENCIES_OPEN` oznacza pełny odczyt pliku z otwartymi zależnościami; status `CLASSIFIED_*` oznacza klasyfikację, nie czytanie treści. Notatki są dowodem statycznym, a nie automatyczną rekomendacją wdrożenia. Pominięto zawartość sekretów, logów i dumpów. Każda karta wskazuje blob Git dla migawki; zależności literalne są zweryfikowane co do istnienia pliku, nie co do zachowania w runtime.

### `.phpunit.cache/result-mysql`

**Blob:** `d273626d93d8a31ef3825a119a5bc1d5a6e97c15` · **Status:** `CLASSIFIED_GENERATED_CACHE`.

- **Granica dowodu:** Nieprzeczytane szczegóły testów/cache
- Klasyfikacja/metadata: bez deklaracji przeglądu zawartości ani wyniku działania.

### `assets/css/admin_protection.css`

**Blob:** `9cfe801316c13062145ae5356db5f6c762284363` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 43 linii.

- **Ustalenie:** Wiersze 1 i 3: deklaracje gap stoją poza blokiem reguły, więc nie nadają odstępu; zakres od wiersza 5 stylizuje przewijaną tabelę opcji ochrony z gridem. Nie znaleziono literalnego odwołania do nazwy arkusza w przeszukanych PHP/JS; trzeba ustalić faktyczne wpięcie, nie uznawać go za działający styl.
- **Zależności do sprawdzenia:** `templates/views/admin/protection/main.php`.
- **Granica dowodu:** Pełny odczyt arkusza i statyczne sprawdzenie wskazanych zależności na HEAD; nie uruchomiono renderu, PHP/MySQL ani Playwright.

### `assets/css/auth.css`

**Blob:** `300cd4342922f74cdf68b06966aeef5c6f5a9dff` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 354 linii.

- **Ustalenie:** auth.css:1–22 styluje body.auth-page i auth-bg z absolutnym URL /assets/images/oilempire_bg.png; plik obrazu jest śledzony. templates/header.php:92–99 ładuje auth.css po variables.css/style.css, gdy authPage=true; public/login.php:46, register.php:58, forgot_password.php:41, reset_password.php:45 ustawiają ten przełącznik.
- **Ustalenie:** auth.css:24–145 dostarcza językowy fixed switcher, kontenery login/register, brand i szklistą kartę; :146–274 ma .form-group, .form-label, .form-input, password-wrap, checkbox, przyciski i alerty. Globalne style.css:559–599 ustanawia wcześniej bazowe reguły formularzy; późniejszy auth.css wygrywa przy tej samej specyficzności, ale np. style.css input:focus i bardziej specyficzne reguły mogą pozostać aktywne zależnie od DOM.
- **Ustalenie:** auth.css:276–334 obsługuje starter-pack, auth-card weryfikacji, linki i animację; :335–354 <=480 przesuwa układ na scrollowalny przepływ, tło na scroll, margin-block register na zero, zmniejsza typografię. :12–13 używa 100vh i potem 100svh jako progresywnego fallbacku.
- **Zależności do sprawdzenia:** `templates/header.php`, `assets/css/style.css`, `assets/css/variables.css`, `assets/images/oilempire_bg.png`, `public/login.php`, `public/register.php`, `public/forgot_password.php`, `public/reset_password.php`.
- **Granica dowodu:** Pełen odczyt statyczny 1–354; nie renderowano formularzy ani nie sprawdzono wczytania tła, zgodności przeglądarek czy computed styles.
- **Granica dowodu:** Przyjęto tylko statycznie widoczną kolejność arkuszy; nie weryfikowano wszystkich extraHead/extraCss dla poszczególnych stron.

### `assets/css/bank.css`

**Blob:** `9461e97d0b2232653cdcf206c99459a494ae2ddb` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 1350 linii.

- **Ustalenie:** bank.css:1–241,242–589 styluje banery, kartę kredytu, negocjacje, spłatę i modal; public/bank.php:100 deklaruje extraCss bank.css, potem wallet.css, a templates/header.php:92–99 ładuje je po variables.css/style.css. Zmienne --green/--gold/--border są zależne od globalnych tokenów.
- **Ustalenie:** bank.css:335–339 ma brak przecinka między .offer-item--warning:hover i .offer-item--note:hover: parser interpretuje to jako selektor potomka, a nie dwie równorzędne reguły hover; .offer-item--warning:hover nie należy przez to do listy wyłączającej standardowe tło hover.
- **Ustalenie:** bank.css:590–838 redefiniuje wcześniejsze .loan-card, .repay-bar-fill, .bank-flash--success itd. licznymi !important; :813–817 kasuje border-left dla .card--bank-approved i szerokiego section.card:has(> h2). :843–983 pod <=768/480 nadaje !important wszystkim .card i .btn na stronie banku, co może przełamać globalne i komponentowe reguły także poza bankiem; wymaga DOM/computed styles, by ocenić konkretny wpływ.
- **Ustalenie:** bank.css:985–1350 dodaje konto, historię i transfer-modal; historia ma siatkę pięciu kolumn :1114–1121, a przy <=920 przechodzi na dwie z ukrytym nagłówkiem :1290–1317. Ikony banku i historii używają wbudowanych data:image/svg+xml (:1005–1013,1095–1103), bez dodatkowej ścieżki assetu; modal ma z-index 9999 (:1179–1209).
- **Zależności do sprawdzenia:** `public/bank.php`, `templates/header.php`, `assets/css/style.css`, `assets/css/variables.css`, `assets/css/wallet.css`.
- **Granica dowodu:** Statyczny odczyt 1–1350; brak renderu, pomiarów szerokości, interakcji i sprawdzenia z wallet.css, późniejszym modal.css czy realnym DOM.
- **Granica dowodu:** Nie uruchomiono bankowego PHP ani operacji finansowych; sam CSS nie dowodzi działania spłaty/transferu.

### `assets/css/boardroom.css`

**Blob:** `aa3bdd04febcc9c6bf90645cf8d50809367a0885` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 272 linii.

- **Ustalenie:** Potwierdzony konflikt breakpointów: boardroom.css:249–253 ustala .scene aspect-ratio:4/3 przy ≤600px, lecz templates/header.php:92–99,126 ładuje mobile.css po extraCss, a mobile.css:169–176 daje .scene aspect-ratio:3/2 !important przy ≤768px. Na /boardroom (public/boardroom.php:169–179; templates/views/boardroom/main.php:11–34) wartości 4/3 w przedziale ≤600px nie da się uzyskać w kaskadzie; zwycięża 3/2. Nie rozstrzyga to bez renderu, która proporcja jest właściwa projektowo.
- **Ustalenie:** boardroom.css:31–40 ustawia .scene-bg background-size:cover, position i opacity:.35, ale templates/views/boardroom/main.php:11–13 emituje pusty element, a assets/js/boardroom-dynamic.js:85–95 wstawia inline backgroundImage i opacity:.85, następnie :677–721 wybiera pliki przez Image() z fallbackiem. Sprawdzono na dysku /assets/images/boardroom_bg.png, boardroom_bg_hr.png i boardroom_bg_hr_tech.png; brak statycznego url() w boardroom.css nie oznacza braku tła. Pozostałe kombinacje generowane dynamicznie wymagają obserwacji fallbacku, nie wolno twierdzić, że każda istnieje.
- **Ustalenie:** boardroom.css:87–105 ujawnia .slot-tooltip wyłącznie na .slot:hover; brak tam odpowiednika :focus-within. Wpływ na dostępność klawiaturą zależy od struktury slotu tworzonej przez JS i zachowania fokusu; to ryzyko do testu, nie stwierdzony wynik przeglądarkowy. Selektory .scene/.slot/.legend podobne do boardroom-scene.css, ale public/boardroom.php:170–173 nie ładuje tego drugiego pliku; nie wolno przypisywać mu kaskady na tej stronie.
- **Ustalenie:** boardroom.css nie ma @import ani url(); zmienne --gold/--green/--red pochodzą z variables.css/style.css ładowanych przed extraCss (templates/header.php:92–99).
- **Zależności do sprawdzenia:** `public/boardroom.php`, `templates/header.php`, `templates/views/boardroom/main.php`, `assets/css/mobile.css`, `assets/css/variables.css`, `assets/css/style.css`, `assets/js/boardroom-dynamic.js`, `assets/images/boardroom_bg.png`, `assets/images/boardroom_bg_hr.png`, `assets/images/boardroom_bg_hr_tech.png`.
- **Granica dowodu:** Pełny odczyt 1–272, zależności odczytane w zakresach potrzebnych do potwierdzenia kaskady/URL.
- **Granica dowodu:** Bez renderowania ani weryfikacji wszystkich wygenerowanych wariantów tła; wymiary klikalnych slotów i obsługa tooltipów klawiaturą niepotwierdzone.

### `assets/css/credibility.css`

**Blob:** `3767b3ad852e161eb11bd7f605c99178f771c33e` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 100 linii.

- **Ustalenie:** Wiersze 8–100: style karty wiarygodności obejmują pięć poziomów statusu i kolory paska; komponent PHP emituje credibility-level-- i credibility-badge--. Sama karta CSS nie ustala algorytmu poziomów; nazwa arkusza występuje w public/legal.php.
- **Zależności do sprawdzenia:** `templates/components/company_credibility.php`, `public/legal.php`.
- **Granica dowodu:** Pełny odczyt arkusza i statyczne sprawdzenie wskazanych zależności na HEAD; nie uruchomiono renderu, PHP/MySQL ani Playwright.

### `assets/css/finance.css`

**Blob:** `b14c7281d016d99eca2e6517d3b9cf7d3b4d599c` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 1177 linii.

- **Ustalenie:** assets/css/finance.css:5–179,212–731,732–1177: karty .g-card, alerty, KPI, wykres, per-well, ustawienia budżetu/polityki, statusy rezerwy i historia; arkusz ładowany w public/finance.php:124, klasy fin-well-header/fin-well-row występują w templates/views/public/finance/main.php:331–391. Zmienne --radius/--gold/--text3 itd. są zależnością od bazowego stylu, a nie lokalnymi definicjami; brak @import/url().
- **Ustalenie:** assets/css/finance.css:184–199: brak przecinka między .fin-well-header i .fin-well-row tworzy selektor potomka .fin-well-header .fin-well-row, nie grupę równorzędnych elementów. Widok finance/main.php:335,372–389 emituje rodzeństwo w .fin-well-list; deklaracje grid/padding dla obu wierszy w tym bloku nie pasują do tego układu. Osobna reguła .fin-well-header:200–207 daje tylko typografię/obramowanie. Ten wniosek jest statyczny o dopasowaniu selektora, nie pomiarem wizualnego efektu.
- **Ustalenie:** assets/css/finance.css:69–71,122–124,194–199,225–240,597–607,629–638,688–692,793–795,848–850,1054–1059: wiele progów responsywnych, ale błąd złożonego selektora dotyczy też reguły 900px. Nie da się wnioskować o ostatecznym układzie mobilnym bez kaskady i renderu.
- **Zależności do sprawdzenia:** `public/finance.php`, `templates/views/public/finance/main.php`, `assets/css/style.css`.
- **Granica dowodu:** Pełny odczyt 1177 linii, kontrola punktowa renderującego PHP; nie wykonywano PHP ani przeglądarki, więc rozmiary, overflow, kolory i dostępność pozostają niezweryfikowane.
- **Granica dowodu:** Współdzielone .g-card i zmienne globalne mogą podlegać dalszym override; nie potwierdzono pełnej kaskady w realnym DOM.

### `assets/css/game_nav.css`

**Blob:** `c09be08a1b6509cfacb076268e6310b93620a955` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 78 linii.

- **Ustalenie:** Wiersze 1–78: nawigacja desktopowa przewijana poziomo, w <=600px przesuwa menu z right:-310px na right:0 po body.nav-open, stosując !important; header ładuje arkusz po mobile.css. Układ, fokus i otwarcie wymagają renderu/klawiatury.
- **Zależności do sprawdzenia:** `templates/header.php`, `assets/css/mobile.css`.
- **Granica dowodu:** Pełny odczyt arkusza i statyczne sprawdzenie wskazanych zależności na HEAD; nie uruchomiono renderu, PHP/MySQL ani Playwright.

### `assets/css/help.css`

**Blob:** `541ab3708a81c0228df2422ba24db0a5648065cb` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 108 linii.

- **Ustalenie:** Wiersze 53–63 i 97–99: brak przecinków między .help-table a .help-content table tworzy selektory potomków (.help-table .help-content table), nie niezależne alternatywy. public/help.php renderuje .help-content z sanitizowaną treścią, bez wrappera .help-table, więc reguły tabeli i min-width:480px nie obejmują zwykłego table w tej treści. Hipotezę wizualnego skutku potwierdzić w przeglądarce.
- **Zależności do sprawdzenia:** `public/help.php`.
- **Granica dowodu:** Pełny odczyt arkusza i statyczne sprawdzenie wskazanych zależności na HEAD; nie uruchomiono renderu, PHP/MySQL ani Playwright.

### `assets/css/help_editor.css`

**Blob:** `64733c7cfdee8226ded28cf10b32fcbc7849af3a` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 60 linii.

- **Ustalenie:** Wiersze 15–60: panel edytora pomocy ma dwukolumnowy układ 220px/1fr, cztery kolumny pól i media <=700px; podgląd treści jest stylowany, ale styl nie jest walidacją ani sanitizacją HTML. Brak literalnego odwołania do nazwy arkusza w przeszukanych PHP/JS.
- **Zależności do sprawdzenia:** `templates/views/admin/help_editor/main.php`.
- **Granica dowodu:** Pełny odczyt arkusza i statyczne sprawdzenie wskazanych zależności na HEAD; nie uruchomiono renderu, PHP/MySQL ani Playwright.

### `assets/css/home.css`

**Blob:** `925e6450ef8a2d83db5b456a1bb472e3d5f99779` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 1059 linii.

- **Ustalenie:** assets/css/home.css:1–337,339–944,953–1059: style dashboardu dla trend-alert, bailiff, urgent offer overlay, redesign, action grid, well grid i technical notifications; public/index.php:360 i public/market_offers.php:73 ładują arkusz. Nie mylić z osobnym public_home.css ładowanym przez publiczną stronę startową.
- **Ustalenie:** assets/css/home.css:210–229,350–389,888–951,955–1059: overlay i animacje, breakpointy 700/768/480/600 px; reguły .status-grid--redesign:946–950 współdziałają z assets/css/style.css:320,465–468,1348 i komponentem templates/components/status_grid.php:8. Brak @import/url(); zmienne kolorów i promieni pochodzą z zewnętrznych stylów bazowych.
- **Ustalenie:** assets/css/home.css:1027–1029: brak przecinka między .tech-notif--disaster .tech-notif-type-badge a .tech-notif--critical .tech-notif-type-badge składa je w jeden zagnieżdżony selektor zamiast dwóch alternatywnych statusów. templates/components/tech_notifications.php:38–41 wybiera odrębne klasy severity, więc reguła tego bloku nie dopasowuje zwykłego badge w pojedynczej notyfikacji disaster/critical; warning/default mają odrębne reguły 1029–1030. To błąd składni semantycznej selektora statycznie, nie dowód wyglądu ekranu.
- **Zależności do sprawdzenia:** `public/index.php`, `public/market_offers.php`, `templates/components/tech_notifications.php`, `templates/components/status_grid.php`, `assets/css/style.css`.
- **Granica dowodu:** Pełny odczyt 1059 linii i punktowa kontrola komponentów; nie renderowano dashboardu ani nie weryfikowano wszystkich warunków generowania alertów.
- **Granica dowodu:** Nie można ocenić efektu CSS w pikselach, dostępności ani pełnej kaskady bez załadowania widoku i bazowego style.css.

### `assets/css/home_dashboard.css`

**Blob:** `52b312980337913c01694abfccb70a1a642b012d` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 98 linii.

- **Ustalenie:** Wiersze 1–98: dashboard ma metryki, wykres SVG, chat i ukrywanie [hidden] !important; przy <=700px karty przechodzą do dwóch kolumn, <=390px do jednej. Skrypt/test fixture i kaskadę sprawdzić oddzielnie, nie wykonano renderu.
- **Zależności do sprawdzenia:** `public/index.php`, `tests/home_dashboard_browser.cjs`.
- **Granica dowodu:** Pełny odczyt arkusza i statyczne sprawdzenie wskazanych zależności na HEAD; nie uruchomiono renderu, PHP/MySQL ani Playwright.

### `assets/css/hr.css`

**Blob:** `8c562f0e3723929c37e59610385c7b1b90e28177` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 1246 linii.

- **Ustalenie:** Potwierdzony konflikt zmiennych globalnych: hr.css:7–20 redeklaruje w :root --gold2 jako rgba(200,168,75,.15), --bg2 i --border; templates/header.php:92–99 ładuje variables.css/style.css przed recruitment.css i hr.css zgodnie z public/hr.php:268–276. recruitment.css:183–188 używa --gold2 w gradiencie .skill-fill, a :255–256 w tle i obramowaniu .btn-recruitment.primary:hover: na stronie HR otrzymują przez kaskadę półprzezroczysty wariant zamiast #e8cc7a z variables.css:11/style.css:11. Wspólny .card z style.css:238–244 też dziedziczy nowe --bg2/--border (game_shell.php:23–25). Efekt kolorystyczny/kontrast wymaga renderu; to nie jest konflikt ładowania między stroną HR a logistyką, bo są osobnymi dokumentami.
- **Ustalenie:** Potwierdzona niespójność selektora/wartości: hr.css:753–765 definiuje .trait-bar i .trait-fill bez width zależnego od --bar-w, podczas gdy templates/views/hr/main.php:443–445 nadaje .trait-fill inline --bar-w zależny od cechy. W pięciu źródłach CSS i przeszukanych pozostałych arkuszach nie ma innej reguły .trait-fill; blokowe wypełnienie zajmie całą szerokość rodzica zamiast kodowanego odsetka. Dla porównania .avail-fill hr.css:611–616 rzeczywiście czyta --bar-w.
- **Ustalenie:** Powiązanie widoku: public/hr.php:271–275 ładuje hr.css i hr_raises.css, templates/views/hr/main.php:165–167 ma .hr-container/.hr-tab-content; końcowa reguła hr.css:1240–1245 nadpisuje .cand-skills z :1141–1144 przy ≤600px (2 zamiast 3 kolumn), co jest przewidywalną kaskadą, a nie automatycznie defektem. Reguła .hr-body z :22–32 nie pasuje do body z templates/header.php:129 (brak klasy hr-body), lecz :root pozostaje aktywne.
- **Ustalenie:** Brak @import i url() w samym hr.css. Nie znaleziono podstaw, by twierdzić, że boardroom.css jest ładowany na produkcyjnym /hr: komentarz hr.css:1089 nie stanowi zależności, public/hr.php:268–276 nie zawiera boardroom.css.
- **Zależności do sprawdzenia:** `public/hr.php`, `templates/header.php`, `templates/components/game_shell.php`, `templates/views/hr/main.php`, `assets/css/variables.css`, `assets/css/style.css`, `assets/css/recruitment.css`, `assets/css/hr_raises.css`, `assets/css/mobile.css`.
- **Granica dowodu:** Pełny odczyt 1–1246; zależne pliki sprawdzone w przytoczonych fragmentach, nie wszystkie w całości.
- **Granica dowodu:** Bez renderowania, obliczonych stylów, testu przeglądarki i wykonania PHP; oceniono kaskadę statycznie.

### `assets/css/hr_employees.css`

**Blob:** `c94305ad9be3b90af8334c0d7072ec62db5a4333` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 271 linii.

- **Ustalenie:** Wiersze 1–271: karty pracowników i panele HR definiują kolumny umiejętności, moralu, szkoleń i zdarzeń; przy <=640px układają wiersze pionowo. Arkusz nadpisuje klasy wspólne (.trait-item, .emp-header); porządek CSS/render wymaga osobnej kontroli.
- **Zależności do sprawdzenia:** `public/hr.php`.
- **Granica dowodu:** Pełny odczyt arkusza i statyczne sprawdzenie wskazanych zależności na HEAD; nie uruchomiono renderu, PHP/MySQL ani Playwright.

### `assets/css/hr_raises.css`

**Blob:** `fd1cc8193c6d68ec398545f3e2dc91f01a1110d0` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 165 linii.

- **Ustalenie:** public/hr.php:268–276 ładuje hr_raises.css po hr.css/hr_employees.css/hr_morale.css/hr_strikes.css; templates/views/hr/main.php:248–258 oraz :297–349 zawiera rzeczywiste .hr-raise-center, .hr-raise-list, .hr-raise-card, .hr-raise-actions, .hr-raise-offer-form i .btn. Zawężone selektory hr_raises.css:2–145 nie kolidują statycznie z ogólnym .hr-section-header z hr.css:45–59; :8 nadpisuje wyłącznie margin-bottom nagłówka, w kaskadzie celowy wariant.
- **Ustalenie:** hr_raises.css:98–115 ustala 3 kolumny akcji i formularz span 3, przy ≤700px :147–164 ustala jedną kolumnę i grid-column:auto. To jest świadomy breakpoint; ilość zawijania i dostępność formularza przy różnych długościach tekstów nie zostały udowodnione bez renderu. Karta :16–22 korzysta z var(--bg-card,#17191e) wewnątrz color-mix, a input :131–145 z var(--bg-input,#101217), var(--text-muted,#a8abb3), więc istnieją fallbacks; brak @import i url() w tym pliku.
- **Ustalenie:** Nie stwierdzono realnego konfliktu importu/URL ani potwierdzonej kolizji selektorów tej karty; globalne przedefiniowanie --gold2 w hr.css jej nie dotyczy wprost. Kod jest zależny od wspólnych .btn z style.css i klas/akcji szablonu, nie dowodzi zachowania po kliknięciu.
- **Zależności do sprawdzenia:** `public/hr.php`, `templates/header.php`, `templates/views/hr/main.php`, `assets/css/hr.css`, `assets/css/style.css`, `assets/js/hr_raises.js`.
- **Granica dowodu:** Pełny odczyt 1–165; zależności czytane punktowo, interakcje i łamanie layoutu niezweryfikowane w przeglądarce.
- **Granica dowodu:** Bez PHP/DB/E2E; reguły CSS nie rozstrzygają, czy dane wniosków będą obecne ani czy zewnętrzne API odpowie.

### `assets/css/hr_strikes.css`

**Blob:** `df88398f92ef0949c02c1faa6a6988e7369ba82b` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 186 linii.

- **Ustalenie:** Wiersze 1–186: panel sporów obsługuje statusy, formularz oferty i responsywność <=600px; .morale-fill używa var(--morale-color) oraz width:var(--bar-w), bez fallback. Wpięty przez public/hr.php; nie wykonano scenariuszy negocjacji.
- **Zależności do sprawdzenia:** `public/hr.php`, `templates/views/hr/main.php`.
- **Granica dowodu:** Pełny odczyt arkusza i statyczne sprawdzenie wskazanych zależności na HEAD; nie uruchomiono renderu, PHP/MySQL ani Playwright.

### `assets/css/legal_design.css`

**Blob:** `1bcadf246b9f00a6510b820af2701a710e2cde38` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 162 linii.

- **Ustalenie:** Wiersze 5–162: arkusz ukrywa bezpośrednie section.card wewnątrz .legal-redesign i definiuje odrębny root zestawienia, dialog, historię oraz siatki <=880/620px. Wpięty przez public/legal.php; ukrywanie kart trzeba zestawić z fallbackiem i JS, nie jest dowodem działania dialogu.
- **Zależności do sprawdzenia:** `public/legal.php`, `templates/views/legal/main.php`.
- **Granica dowodu:** Pełny odczyt arkusza i statyczne sprawdzenie wskazanych zależności na HEAD; nie uruchomiono renderu, PHP/MySQL ani Playwright.

### `assets/css/logistics.css`

**Blob:** `3b362298243980e3559c2959df48d8c24c7b1c24` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 2836 linii.

- **Ustalenie:** Potwierdzony konflikt kaskady z arkuszem doładowanym później: logistics.css:1829–1842 przy ≤600px ukrywa każde span od czwartego w .logistics-table-row/head (display:none) i nadaje #hub-assign-modal .logistics-table-row min-width:760px. assets/js/logistics_hub_wells.js:177–192 buduje tabelę z czterema span, czwarty zawiera przycisk przypisania odwiertu; public/logistics.php:23–29 ładuje później logistics_design.css, a jego :321 ma silniejsze .logistics-design .logistics-table-row span:nth-child(n) {display:block}. Modal #hub-assign-modal jest w templates/views/logistics/main.php:61 pod .logistics-design, więc w obecnym widoku późniejsza reguła przywraca przycisk; nie zgłaszać ukrycia jako potwierdzonego błędu produkcyjnego. Przy wyjęciu modala poza .logistics-design lub bez logistics_design.css ukrycie stanie się realnym ryzykiem.
- **Ustalenie:** Potwierdzona nadmiarowa kaskada: logistics.css:1791–1797 ustawia .logistics-hub-stats na jedną kolumnę przy ≤640px, a :1825–1827 wraca do dwóch przy ≤600px; logistics.css:2025–2035 ustawia najpierw display:none i potem padding w .logistics-region-body, a :2029–2031 jawnie pokazuje .is-open. templates/views/logistics/sections/available_hubs_market.php:49–64 naprawdę emituje .is-open i .logistics-region-body. Są to reguły z nakładającymi się mediami/stylistyką, nie same w sobie awarie.
- **Ustalenie:** Niespójność zakresu: logistics.css:2797–2799 deklaruje globalne .c-good/.c-warn/.c-bad bez prefiksu, a hr.css:928–930 deklaruje te same kolory. Ponieważ public/logistics.php:23–29 i public/hr.php:268–276 są osobnymi dokumentami, nie dochodzi między nimi do kolizji w tych widokach; same identyczne barwy nie uzasadniają błędu. logistics.css nie zawiera @import ani url(); URL ikon widoczne w późniejszym logistics_design.css:52–57,138–152 nie są importami logistics.css.
- **Ustalenie:** Źródło 1–2836 objęto trzema pełnymi przedziałami odczytu 1–1400, 1401–2120 i 2121–2836; sprawdzono layout, modal, huby, media queries, przepływ oraz rzeczywiste selektory widoku. templates/views/logistics/sections/flow_section.php:9–40 emituje .logistics-flow-stages zamiast starszego .logistics-flow-track z logistics.css:2659–2668; aktualne logistics_design.css:138–152,311–312 odpowiada nowym etapom, więc nie należy wywodzić braku stylu z nieużywanej gałęzi.
- **Ustalenie:** W logistics.css:488–492 jest jawne [hidden]{display:none} dla overlay/sekcji, a późniejszy logistics_design.css:13 wymusza [hidden] {display:none !important} pod .logistics-design; nie uznano tego za konflikt widoczności bez konkretnego stanu DOM. Kolory var(--gold2,#e8cc7a) z :39–44,941–945 nie są na tej stronie poddane override hr.css, bo tego arkusza tutaj nie ładuje public/logistics.php.
- **Zależności do sprawdzenia:** `public/logistics.php`, `templates/header.php`, `templates/views/logistics/main.php`, `templates/views/logistics/sections/available_hubs_market.php`, `templates/views/logistics/sections/flow_section.php`, `templates/views/logistics/sections/transport_mix_section.php`, `assets/css/logistics_design.css`, `assets/css/mobile.css`, `assets/css/style.css`, `assets/js/logistics_hub_wells.js`.
- **Granica dowodu:** Pełny odczyt logistics.css 1–2836, zależne widoki/arkusze/JS czytane celowo w punktach konfliktu, nie pełny audyt każdego pliku zależnego.
- **Granica dowodu:** Statyczna kaskada nie potwierdza responsywnego wyglądu, funkcjonowania modali ani rozmiarów przy konkretnych danych; brak renderu/E2E/PHP/DB.

### `assets/css/logistics_design.css`

**Blob:** `05043d6526582cc2a6ced940ae22f396b97a7942` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 374 linii.

- **Ustalenie:** Stylesheet jest w $extraCss po logistics.css, logistics_incidents.css i logistics_pipeline_staffing.css (public/logistics.php:23–29); nie ma @import. Selektory .logistics-design ustalają tokeny kolorów, [hidden] wymusza display:none, nawigacja sticky z poziomym scroll, sekcje scroll-margin-top:58px, media responsive 1100/700/380 i reduced-motion (3–71,297–332). Działanie sticky, kontrast i kaskada z pozostałymi arkuszami wymagają renderu/przeglądarki, nie są potwierdzone samym CSS.
- **Ustalenie:** Kontrakt ikon: --ld-icon url() i CSS mask dla sześciu linków nav, flow stages/flow icons, trip/tanker, źródła incydentu i trybu pracy (41–57,137–153,181–183,261–263,275). Wszystkie 21 odwołań url() prowadzi do ścieżek /assets/icons/logistics/icon-{dashboard,route,hub,market,pipeline,warning,well,truck,storage,ship,status}.svg, które występują w git ls-files assets/icons/logistics/*.svg; HTTP/cache/CSP i rzeczywiste rysowanie maski nie zostały sprawdzone.
- **Ustalenie:** Warstwy widoku: tokenizowane panele/KPI/flow, poziomo przewijane tabele z min-width:720px, szczegóły transportu, karty hubów i pipeline, marketplace i filtry incydentów (85–196,200–260,271–296). .logistics-entity-browser/list/row + .logistics-entity-detail i [hidden] wspierają przyciski tworzone przez logistics_design.js:45–101, a .is-changing animuje zmianę szczegółu 220ms (209–235,267–270); .is-summary i .logistics-detail-toggle są deklarowane w CSS (227–236), lecz nie znaleziono ich jako klas/akcji w sprawdzonych szablonach i JS, więc nie przypisywać im działającego mechanizmu bez dalszej weryfikacji.
- **Ustalenie:** Modale hub-wells/staffing mają ograniczenie rozmiaru i przewijany body, przy <=600px pełnoekranowe 100dvh; body.logistics-dialog-open blokuje overflow (334–374). Źródła: templates/views/logistics/modals/hub_modals.php:4–17,60–73 oraz assets/js/logistics_hubs.js:129–151. Zwarta lista odwiertów jest generowana przez logistics_hub_wells.js:109–114, staffing-notice przez logistics_staffing.js:93. Kolory i układ nie zostały wizualnie potwierdzone.
- **Ustalenie:** Potencjalny problem kontraktu: CSS ustawia .logistics-design [data-progress-width] transition width 650ms (295), a JS w trybie reduced-motion/bez IO ustawia szerokość natychmiast lub przez observer (logistics_design.js:122–137); CSS reduced-motion wyłącza transition (330–332), ale bez JS paski z bazowym width:0 mogą pozostać puste. CSS wyłącza animacje krytycznych hub badge (240,354) i zaznacza nowy incydent is-new (269–270); dostępność i wynik zależą od DOM/pozostałych stylów.
- **Zależności do sprawdzenia:** `public/logistics.php`, `assets/css/logistics.css`, `assets/css/logistics_incidents.css`, `assets/css/logistics_pipeline_staffing.css`, `assets/js/logistics_design.js`, `assets/js/logistics_hubs.js`, `assets/js/logistics_hub_wells.js`, `assets/js/logistics_staffing.js`, `templates/views/logistics/main.php`, `templates/views/logistics/sections/owned_hubs_section.php`, `templates/views/logistics/sections/pipelines_section.php`, `templates/views/logistics/modals/hub_modals.php`, `assets/icons/logistics/`.
- **Granica dowodu:** Pełny odczyt 374 linii, przegląd źródeł selektorów/url/import punktowo; bez renderowania przeglądarkowego, obliczonej kaskady, sieci ani testu dostępności.
- **Granica dowodu:** Nie wykonywano PHP/DB/SQL/CRON/E2E; deklaracja reguły CSS nie dowodzi wyglądu ani aktywności klasy.

### `assets/css/logistics_incidents.css`

**Blob:** `968d97fb7410b4d6a82410a65611646b723347c7` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 45 linii.

- **Ustalenie:** Wiersze 1–45: styl panelu incydentów logistyki opiera się na details[open], wierszach i paginacji; przy 800/480 px zmienia siatkę. Nie świadczy o pobieraniu incydentów ani o widoku mobilnym bez renderu.
- **Zależności do sprawdzenia:** `public/logistics.php`.
- **Granica dowodu:** Pełny odczyt arkusza i statyczne sprawdzenie wskazanych zależności na HEAD; nie uruchomiono renderu, PHP/MySQL ani Playwright.

### `assets/css/logistics_pipeline_staffing.css`

**Blob:** `63a8c09089c2e12130878ec713e7e3267aa89f49` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 147 linii.

- **Ustalenie:** Wiersze 1–147: modal obsady rurociągu ma dwukolumnowe pokrycie, listy kandydatów, pola i układ <=720px; element body ma overflow-y:auto, a box max-height. Wpięty przez public/logistics.php, treść modalu dostarcza osobny szablon/JS.
- **Zależności do sprawdzenia:** `public/logistics.php`, `templates/views/logistics/modals/pipeline_staffing_modal.php`, `assets/js/logistics_pipeline_staffing.js`.
- **Granica dowodu:** Pełny odczyt arkusza i statyczne sprawdzenie wskazanych zależności na HEAD; nie uruchomiono renderu, PHP/MySQL ani Playwright.

### `assets/css/market.css`

**Blob:** `dcc9bd25fbe3c51316d3708b2081a28cdf2844e8` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 367 linii.

- **Ustalenie:** assets/css/market.css:1–56,153–267,276–367: taby rynku, wykres, formularz sprzedaży, listy ofert, historię i paginację; public/market.php:156–157 ładuje market.css obok black_market.css, public/market_offers.php:73–74 ładuje home.css i market.css. Arkusz nie zawiera @import/url(); odwołuje się do globalnych zmiennych --gold, --text, --border itd.
- **Ustalenie:** assets/css/market.css:43–56 odpowiada aktywnym .market-tab/.market-tab-content w templates/views/market/main.php:31–42,149,194; inline switchMarketTab:3–16 rzeczywiście zmienia klasę active. Osobna zakładka B2B na rynku występuje warunkowo przy b2bModuleEnabled (main.php:36–39,188–208), ale styl tutaj dotyczy ogólnego przełącznika, nie reguł gospodarczych B2B.
- **Ustalenie:** assets/css/market.css:280–348 i templates/views/market/main.php:82–145: sale-history-table, kontener przewijania i klasy paginacji są użyte w widoku. Breakpointy 700/480/600 px definiują przewijanie/węższe fonty; brak obserwacji czy faktycznie nie dochodzi do overflow lub nakładania.
- **Zależności do sprawdzenia:** `public/market.php`, `public/market_offers.php`, `templates/views/market/main.php`, `assets/css/black_market.css`, `assets/css/style.css`.
- **Granica dowodu:** Pełny odczyt 367 linii i punktowa kontrola widoku/loaderów; nie uruchamiano rynku, JavaScript ani renderu, więc nie potwierdzono zachowania po kliknięciu, dostępności zakładek ani wynikowego layoutu.
- **Granica dowodu:** Nie analizowano wyczerpująco globalnej kaskady style.css, black_market.css i contracts.css, ani warunków backendowych zakładki B2B.

### `assets/css/player_menu_editor.css`

**Blob:** `fdde875b923036bba44adeb2c39b2058fc4bd6eb` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 85 linii.

- **Ustalenie:** Wiersze 1–85: edytor menu ma podgląd telefonu, drzewo, pola PL/EN/DE, stany [hidden], :has(input[type=checkbox]) i siatkę w <=1100/700/360px. Brak literalnej referencji do nazwy arkusza w przeszukanych PHP/JS; samo istnienie widoku nie dowodzi wpięcia.
- **Zależności do sprawdzenia:** `templates/views/admin/template_editor/player_menu.php`.
- **Granica dowodu:** Pełny odczyt arkusza i statyczne sprawdzenie wskazanych zależności na HEAD; nie uruchomiono renderu, PHP/MySQL ani Playwright.

### `assets/css/profile.css`

**Blob:** `f31d9affe7059ec297b095b4565dcc08f0d51b9c` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 273 linii.

- **Ustalenie:** Potwierdzona nieszczelność deklarowanego scope: komentarz profile.css:8 mówi o .profile-shell, lecz :245 zawiera globalne button:focus-visible,input:focus-visible; /profile ładuje arkusz przez public/profile.php:314–319, a templates/header.php:129,277 zawiera elementy poza .profile-shell, w tym przycisk wylogowania. Reguła obejmie ich focus outline 2px #f0cf66 zamiast tylko formularzy profilu; efekt końcowy po wszystkich regułach/specyficznościach i oczekiwania projektowe wymagają obliczonych stylów. .profile-photo-button:focus-visible w tej samej regule jest redundantny względem button:focus-visible.
- **Ustalenie:** profile.css:10–22 definiuje zmienne --pf-* lokalnie na .profile-shell; templates/views/profile/main.php:30,64–87 emituje odpowiedni kontener i strukturę. Media :247–273 zmieniają dwie kolumny na jedną i display:contents dla .profile-rail/.profile-content przy ≤690px; porządek kart zależy od :259–264, lecz faktyczny odczyt/porządek klawiatury i łamanie przy długich danych nie zostały sprawdzone w DOM przeglądarki.
- **Ustalenie:** W profile.css nie ma @import ani url(). Widok templates/views/profile/main.php:26–27,69–77,90–194 wstawia URL ikon przez asset(), a na dysku istnieją wszystkie nazwane w nim SVG w assets/icons/profile (m.in. eye.svg, eye-off.svg, camera.svg, stats.svg, lock.svg). Dynamiczny $avatarUrl jest danymi serwera, a nie statycznym URL do weryfikacji z CSS.
- **Ustalenie:** Nie wykazano kolizji z hr.css/logistics.css/boardroom.css w rzeczywistym dokumencie /profile: public/profile.php:315 ładuje tylko profile.css jako extraCss, obok wspólnych arkuszy z templates/header.php:92–99,126–127.
- **Zależności do sprawdzenia:** `public/profile.php`, `templates/header.php`, `templates/views/profile/main.php`, `assets/css/variables.css`, `assets/css/style.css`, `assets/css/mobile.css`, `assets/icons/profile/eye.svg`, `assets/icons/profile/eye-off.svg`, `assets/icons/profile/camera.svg`, `assets/icons/profile/stats.svg`, `assets/icons/profile/lock.svg`.
- **Granica dowodu:** Pełny odczyt 1–273; widok i arkusze współdzielone sprawdzone w relewantnych fragmentach, nie był to pełny audyt zależności.
- **Granica dowodu:** Bez przeglądarki, renderu, obliczonych stylów i sprawdzenia dynamicznego $avatarUrl; nieszczelność selektora nie jest dowodem regresji widocznego focus ring.

### `assets/css/protection.css`

**Blob:** `1fb118c0079574f8855a88df7e88322b92dfc44d` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 78 linii.

- **Ustalenie:** Wiersze 7–78: style listy opcji ochrony, stanu locked, ukrytej nakładki i czterokolumnowego wariantu tabeli logistyki; komentarz wskazuje zależność od klas bazowego modalu. Arkusz jest wskazany w public/logistics.php.
- **Zależności do sprawdzenia:** `public/logistics.php`.
- **Granica dowodu:** Pełny odczyt arkusza i statyczne sprawdzenie wskazanych zależności na HEAD; nie uruchomiono renderu, PHP/MySQL ani Playwright.

### `assets/css/public_home.css`

**Blob:** `a2b64dc96f00e694c51fb6e8a2953be571dc22df` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 1526 linii.

- **Ustalenie:** assets/css/public_home.css:6–97,241–253,673–717,1328–1526: samodzielne zmienne --home-*, zakres body.public-home, nagłówek/hero, zakładki aria-selected i [hidden], progi 1100/959/680 px i prefers-reduced-motion; templates/views/public/home/main.php:23–24 ładuje arkusz i assets/js/public_home.js, main.php:227,340,464 dostarcza panele, a JS:40–74 przełącza hidden/aria-selected. Nie należy oceniać geometrii z samych deklaracji.
- **Ustalenie:** assets/css/public_home.css:102–110,236,1354–1393: menu mobilne jest domyślnie ukryte i otwiera się przez .home-header.is-open; templates/views/public/home/main.php:36 oraz assets/js/public_home.js:4–33 dostarczają przycisk i stan, więc reguły mobilnego menu nie są martwe.
- **Ustalenie:** assets/css/public_home.css:245–249,1142–1149: dwa URL absolutne /assets/images/home/hero-oilfield.png i footer-oilfield.png odpowiadają tracked assets; brak @import i innych url() w czterech analizowanych arkuszach. templates/views/public/home/main.php:64–78,614–624 mają odpowiadające sekcje; ścieżka HTTP i poprawny rendering obrazów nie zostały sprawdzone.
- **Ustalenie:** assets/css/public_home.css:1272–1323 oraz templates/views/public/home/main.php:657–661 i assets/js/public_home.js:76–101: styl dialogu preview ma powiązanie z realnym dialogiem i obsługą open/close/focus.
- **Zależności do sprawdzenia:** `templates/views/public/home/main.php`, `assets/js/public_home.js`, `assets/images/home/hero-oilfield.png`, `assets/images/home/footer-oilfield.png`.
- **Granica dowodu:** Pełny odczyt 1526 linii i punktowe dopasowanie widoku/JS/zasobów; bez renderu desktop/mobile, computed styles, asset HTTP i testu dostępności nie da się potwierdzić finalnego wyglądu, kontrastu ani zachowania przeglądarki.
- **Granica dowodu:** Nie przeprowadzono wyczerpującej analizy kaskady innych arkuszy ani wszystkich warunkowych gałęzi szablonu.

### `assets/css/recruitment.css`

**Blob:** `82dfb56464f50cdd1df9132e6df3e2200c6c21d3` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 630 linii.

- **Ustalenie:** recruitment.css:5–79 definiuje ukryty domyślnie fullscreen modal (display:none) i .active display:flex, panel max-width 1200px/max-height 90vh oraz scrollującą .recruitment-content; widoczność zależy od klasy w rzeczywistym DOM/JS, nie potwierdzono jej renderem.
- **Ustalenie:** recruitment.css:81–303 opisuje karty kandydatów selected/recommended, skill-fill width z --skill-width, akcje i animowany stan oczekiwania; :304–416 empty/badge/scrollbar, statystyki i panel-link, :418–613 region grid, pracowników, toast i map-load-error. Kolory korzystają z --gold/--gold2/--green/--red z arkusza bazowego; font Cormorant Garamond jest zadeklarowany w public/hr.php:268–276 jako osobny Google Fonts URL obok recruitment.css.
- **Ustalenie:** public/hr.php:268–276 ładuje recruitment.css przed hr.css i następnymi HR arkuszami w extraCss, po style.css według templates/header.php:92–99. Wspólne/ogólne selektory .panel-link, .candidate-card i .skill-fill mogą wchodzić w kaskadę z późniejszymi CSS; bez DOM i computed styles nie można przypisać ostatecznej prezentacji.
- **Ustalenie:** recruitment.css:617–630 zmniejsza panel-stats z trzech do dwóch kolumn przy <=768 i jednej przy <=480; employee-stats pozostaje trzykolumnowy (:556–562), zatem jego responsywność zależy od innych arkuszy lub DOM.
- **Zależności do sprawdzenia:** `public/hr.php`, `templates/header.php`, `assets/css/style.css`, `assets/css/hr.css`, `assets/css/variables.css`.
- **Granica dowodu:** Pełen odczyt statyczny 1–630; nie weryfikowano rzeczywistego renderu HR, modalu, toastu ani interakcji JS.
- **Granica dowodu:** Nie przeanalizowano w pełni hr.css i pozostałych późniejszych arkuszy HR ani dostępności fontów; kaskada i efekt końcowy pozostają otwarte.

### `assets/css/sabotage.css`

**Blob:** `d356a0357dcfce84ff1551a6c0d00e79a068f157` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 95 linii.

- **Ustalenie:** Wiersze 1–95: gra sabotażu używa aria-pressed do zaznaczenia opcji/celu, wariantów ryzyka, historii i modalu; media <=1120/800/640/440/350px oraz reduced-motion. CSS nie stanowi dowodu serwerowego naliczenia kosztu lub ochrony.
- **Zależności do sprawdzenia:** `public/sabotage.php`.
- **Granica dowodu:** Pełny odczyt arkusza i statyczne sprawdzenie wskazanych zależności na HEAD; nie uruchomiono renderu, PHP/MySQL ani Playwright.

### `assets/css/static_page.css`

**Blob:** `2621dc7adf1986a86937d5ee1fb5b84b392684b8` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 114 linii.

- **Ustalenie:** Wiersze 5–114: style statycznej strony regulaminu/polityki obejmują nagłówek, WYSIWYG, tabele, cytaty i link powrotu; wpięte przez public/page.php. Brak dowodu sanitizacji danych lub renderu.
- **Zależności do sprawdzenia:** `public/page.php`.
- **Granica dowodu:** Pełny odczyt arkusza i statyczne sprawdzenie wskazanych zależności na HEAD; nie uruchomiono renderu, PHP/MySQL ani Playwright.

### `assets/css/style.css`

**Blob:** `a2447f8a69fb32a8268a1626420f162a712f274d` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 1358 linii.

- **Ustalenie:** style.css:1–57 definiuje import Montserrat, tokeny kolorów w :root, reset globalny i flex body; templates/header.php:92–99 ładuje variables.css przed style.css, następnie auth.css albo extraCss, więc późniejsze definicje o tej samej specyficzności mogą nadpisywać style. Zależność od fontów zewnętrznych i fallbacków; brak testu sieciowego.
- **Ustalenie:** style.css:71–278,280–469,482–599 dostarcza globalny container/header/card/status-grid, KPI, przyciski i formularze. Selektory input[type], select i textarea bez kontekstu strony (:575–590) obejmują także formularze banku/auth; auth.css:146–175 z późniejszego arkusza precyzuje form-input, a bank.css:607–630 definiuje bardziej specyficzne .form-group input i select. Wynik kaskady zależy od DOM i kolejności zasobów.
- **Ustalenie:** style.css:601–759 definiuje wspólne module-tabs i action-grid; :761–997 footer, bankructwo/offline, utility .hidden. style.css:1002–1320 redefiniuje header jako dwurzędowy, nav mobilny jako wysuwany przy body.nav-open i backdrop; :1325–1358 reguły nth-child kolorują KPI według pozycji, a media <=900 i <=480 wymuszają dwie kolumny !important, nadpisując wcześniejszą jedną kolumnę <=480 (:467–469).
- **Ustalenie:** style.css:1245 ukrywa h1 tylko pod .header--redesign, :1265–1314 przenosi .user-nav--bar poza ekran poniżej 600 px, widoczność wymaga body.nav-open; samo CSS nie dowodzi działania przełącznika JS ani renderu strony.
- **Zależności do sprawdzenia:** `templates/header.php`, `assets/css/variables.css`, `assets/css/auth.css`, `assets/css/bank.css`.
- **Granica dowodu:** Pełen odczyt statyczny 1–1358; nie uruchomiono przeglądarki ani nie zweryfikowano wyglądu rzeczywistych widoków, ładowania fontu czy interakcji nav.
- **Granica dowodu:** Kaskada może być zmieniona przez późniejsze arkusze extraCss, modal.css, style inline i rzeczywistą strukturę DOM; nie wyprowadzano ostatecznych computed styles.

### `assets/css/technical_incidents.css`

**Blob:** `57c94b08c4cde9957ecbe09a2c90e1b56f25ebd9` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 58 linii.

- **Ustalenie:** Wiersze 1–58: panel incydentów technicznych używa klas statusu/ciężkości, details[open], paginacji i układu przy 800/480 px; dubluje regułę resolved badge w wierszach 20 i 25, druga wymusza kolor !important.
- **Zależności do sprawdzenia:** `public/technical.php`.
- **Granica dowodu:** Pełny odczyt arkusza i statyczne sprawdzenie wskazanych zależności na HEAD; nie uruchomiono renderu, PHP/MySQL ani Playwright.

### `assets/css/template_editor.css`

**Blob:** `b9f106d1bd93516dcebef955f8f16fe01288a6f7` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 69 linii.

- **Ustalenie:** Wiersze 5–69: zakładki i podglądy edytora menu, formularz 220px/1fr i przejście na jedną kolumnę <=700px; nie znaleziono literalnego wpięcia nazwy arkusza w przeszukanych PHP/JS.
- **Zależności do sprawdzenia:** `templates/views/admin/template_editor/main.php`.
- **Granica dowodu:** Pełny odczyt arkusza i statyczne sprawdzenie wskazanych zależności na HEAD; nie uruchomiono renderu, PHP/MySQL ani Playwright.

### `assets/css/training.css`

**Blob:** `a90bf0ee4ca89dadce6bd6ed6aaa50f421b09dde` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 292 linii.

- **Ustalenie:** Wiersze 286–290: brak przecinka między .trn-program-grid i .trn-cert-grid daje selektor potomka zamiast osobnych siatek, więc reguła jednej kolumny nie trafia w niezależne rodzeństwo w trainings.php. Ponadto technical.css:1497 z bardziej swoistym .t-wrap .trn-program-grid ustawia auto-fit min235px przed training.css; w rzeczywistym wrapperze .t-wrap nadpisuje nominalne 4/3/2 kolumny z training.css. Bez pomiaru renderu.
- **Zależności do sprawdzenia:** `public/technical.php`, `assets/css/technical.css`, `templates/views/technical/main.php`, `templates/views/technical/tabs/trainings.php`.
- **Granica dowodu:** Pełny odczyt arkusza i statyczne sprawdzenie wskazanych zależności na HEAD; nie uruchomiono renderu, PHP/MySQL ani Playwright.

### `assets/css/wallet.css`

**Blob:** `56000f581b45ce56b18366dfba23fd3dbbb7567d` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 103 linii.

- **Ustalenie:** Wiersze 8–103: siatki sald gotówki/banku i formularzy transferu z media <=600px; public/bank.php wskazuje arkusz. Podgląd opłaty i kolor paska są prezentacją, bez testu księgowań/limitów.
- **Zależności do sprawdzenia:** `public/bank.php`.
- **Granica dowodu:** Pełny odczyt arkusza i statyczne sprawdzenie wskazanych zależności na HEAD; nie uruchomiono renderu, PHP/MySQL ani Playwright.

### `assets/css/well-workspace.css`

**Blob:** `f6d257666222eff5ecdb4b99b9b0d00a431ed024` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 22 linii.

- **Ustalenie:** Wiersze 1–22: zminifikowany arkusz obszaru .ww definiuje selektor odwiertu, KPI, zakładki, wyposażenie, transport, historię/paginację i modal; przełomy 1180/1000/680/389 px oraz prefers-reduced-motion. Sześć lokalnych ikon maski wskazanych przez url(...) istnieje na dysku.
- **Zależności do sprawdzenia:** `public/index.php`, `templates/views/wells/main.php`.
- **Granica dowodu:** Pełny odczyt arkusza i statyczne sprawdzenie wskazanych zależności na HEAD; nie uruchomiono renderu, PHP/MySQL ani Playwright.

### `assets/js/admin-template-upload.js`

**Blob:** `239231b874d18efed02e6fba3623d3a570be9d44` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 150 linii.

- **Ustalenie:** Linie 4–35: skrypt wymaga formularza, parsuje data-upload-messages i buduje nazwę w stałym porządku ról hr/tech/finance/legal/logistics z opcjonalnym M/F; select wyłącza przy odznaczonym checkboxie, preview używa textContent.
- **Ustalenie:** Linie 37–53: preventDefault, blokada busy, wymagany plik/nazwa/CSRF, zakres 1..20 MiB, max 32 × 1 MiB oraz whitelist MIME deklarowanego przez przeglądarkę; autorytatywne dekodowanie/finfo i wymiary po stronie serwisu (BoardroomUploadService:174–205).
- **Ustalenie:** Linie 55–112: losowy upload_id z crypto, kolejne POST raw octet-stream jako file.slice, metadane i csrf_token w query URL /admin/template_editor.php?ajax_upload=1, same-origin cookies, timeout 60 s na chunk; waliduje HTTP, JSON ok/done/chunk; po finalnym success ustawia komunikat i reload.
- **Ustalenie:** Linie 86–121: wszystkie błędy serwera/transportu trafiają przez status.textContent (bez HTML), w razie niepowodzenia przywraca poprzedni stan kontrolek; nie ma automatycznych retry ani anulowania/wznowienia uploadu, częściowe pliki mogą zostać do serwerowego sweep.
- **Ustalenie:** Linie 4–5,125–149: obsługa dodawania/usuwania linków stopki jest w tym samym IIFE po warunku wczesnego return przy braku formularza uploadu. Bieżący templates/views/admin/template_editor/main.php:245–256,306–309,367 zawiera zarówno formularz, jak i kontrolki stopki oraz ładuje ten skrypt, więc w tej konkretnej stronie warunek jest spełniony; nie przypisywać temu potwierdzonej usterki. Przy wydzieleniu stopki do innej strony zależność stanie się istotna.
- **Zależności do sprawdzenia:** `templates/views/admin/template_editor/main.php`, `admin/template_editor.php`, `src/BoardroomUploadService.php`.
- **Granica dowodu:** Pełny odczyt 150 linii; node --check przeszedł wyłącznie jako kontrola składni. Bez realnego DOM/przeglądarki, requestów i serwera nie potwierdzono uploadu ani działania linków stopki.

### `assets/js/boardroom-dynamic.js`

**Blob:** `376b16cb6f6a500c88f5f3ed0176584e44a379c7` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 737 linii.

- **Ustalenie:** Linie 81–194 i 734–737: generuje sloty z phpData.membersByRole/roleIdByCode, badge kandydatów lub rekrutacji i modale; render() używa innerHTML dla tooltipów i nazw, dane przychodzą z widoku PHP (templates/views/boardroom/main.php:39–49).
- **Ustalenie:** Linie 198–427, 460–535, 560–665: liczne szablony openModal()/panel.innerHTML wstawiają bez HTML-escape pola member/candidate oraz teksty brl; htmlJsString zabezpiecza tylko interpolowane nazwisko w inline onclick przy zwalnianiu (253,281,309,337,365,587), nie pozostałe konteksty HTML. Ryzyko DOM XSS jeśli atakujący kontroluje źródłowe nazwy/komunikaty; kontrola wejścia i ścieżka do tych danych niepotwierdzone.
- **Ustalenie:** Linie 435–457, 537–557, 594–619: rekrutacja/hiring/fire wysyła POST FormData do /api/internal/HRApi.php z _token CSRF, odpowiedź JSON i toast/reload; brak jawnej obsługi HTTP status w hiring/fire, a promisy w confirm/prompt callback mogą odrzucać bez lokalnego catch. W submitRecruitment catch wyświetla e.message.
- **Ustalenie:** Linie 85–95 i 677–722: początkowe tło ustawia tylko hr/technical, później updateBackground próbuje prefiksy zajętych ról od najpełniejszego do default, z płcią lub bez; ładuje przez Image.onload i url względny assets/images/ (w odróżnieniu od /assets/images/ na początku). Potencjalnie błędne rozwiązywanie przy ścieżce /boardroom lub /boardroom/; sprawdzić w przeglądarce.
- **Ustalenie:** Linie 460–469: GET listy kandydatów bez try/catch; przy pustej liście deleguje recruitment.showCandidatesForRole mimo że recruitment.js jest załadowany dopiero po boardroom-dynamic.js (main.php:51–52), lecz wywołanie następuje dopiero na późniejsze kliknięcie; brak testu browser.
- **Ustalenie:** Linie 725–732: co 15 s GET /boardroom-status i reload tylko przy d.reload; błędy polling są ignorowane; brak anulowania requestów przy ukrytej karcie.
- **Zależności do sprawdzenia:** `templates/views/boardroom/main.php`, `assets/js/recruitment.js`, `api/internal/HRApi.php`, `assets/js/boardroom.js`.
- **Granica dowodu:** Pełny odczyt 737 linii; node --check przeszedł wyłącznie jako kontrola składni. Nie weryfikowano kontroli wejść DB/API ani uruchomienia w przeglądarce; DOM XSS i względny URL są hipotezami wymagającymi przepływu danych/testu.

### `assets/js/boardroom.js`

**Blob:** `174f8b4021a9172a86f252e77fcf1dab07b7d289` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 161 linii.

- **Ustalenie:** Linie 11–40, 95–155: statyczna lista 10 foteli (dyrektor id=0, HR id=99, osiem pustych 1–8) jest rysowana jako absolutne procentowe sloty względem sceny; używa SVG avatarów, roli, etykiety, tooltipa i skali. W/H obliczone z offsetWidth/Height nie wpływają na pozycję.
- **Ustalenie:** Linie 67–90, 158–161: statyczne occupiedPositions=['director','hr'] wybiera boardroom_bg_hr.png, zapisuje względny URL jako backgroundImage .scene-bg, renderuje raz i przy resize. Nie pobiera obsady z API ani nie reaguje na zmianę obsady po inicjalizacji.
- **Ustalenie:** Linie 136–152: kliknięcie dowolnego pustego stanowiska wywołuje ten sam recruitment.startRecruitment(1, 'Dzia Kadr') tylko jeśli global jest dostępny; kliknięcie obsadzonego HR wyłącznie loguje do konsoli, bez ekranu opcji pracownika; brak asercji testowych w pliku.
- **Ustalenie:** templates/views/boardroom/main.php:11–13 ma docelową scenę i #slots, lecz :51 ładuje boardroom-dynamic.js (oraz :52 recruitment.js), nie boardroom.js; nie należy utożsamiać zachowania tego skryptu z aktualnie załadowanym UI bez dalszego sprawdzenia pozostałych entrypointów.
- **Zależności do sprawdzenia:** `templates/views/boardroom/main.php`, `assets/js/boardroom-dynamic.js`, `assets/js/recruitment.js`, `assets/images/boardroom_bg_hr.png`, `assets/css/boardroom-scene.css`.
- **Granica dowodu:** Pełny odczyt 161 linii i kontrola składni node --check; nie wykonano kodu w przeglądarce. Nie potwierdzono, czy jakikolwiek inny aktualny entrypoint ładuje ten plik; zależności nie są w pełni przeczytane.

### `assets/js/finance.js`

**Blob:** `26cef152f782f83c10f853575469346eeb54c530` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 283 linii.

- **Ustalenie:** P2: tooltip dla ujemnych wartości używa (v < 0 ? '' : '') i Math.abs(v), więc straty / ujemny wynik są wyświetlane jako dodatnie, bez znaku minus (109-115). Potwierdzone izolowanym uruchomieniem Node: -123 -> Net profit: 123 PLN.
- **Ustalenie:** Chart.js 4.4.1 jest pobierany z CDN przy danych historycznych; brak onerror / fallback dla niedostępnego zasobu (17-21).
- **Ustalenie:** Po końcu cooldown UI zmienia tylko HTML, a zapisy pozostają kontrolowane przez serwer; confirmAction używa form.submit(), co pomija eventy walidacji HTML5 po potwierdzeniu (263-274) — test zachowania formularza.
- **Zależności do sprawdzenia:** `src/FinancePolicyService.php`, `templates/views/public/finance/main.php`.
- **Granica dowodu:** Bez testu Chart.js i formularza w przeglądarce

### `assets/js/hr.js`

**Blob:** `62df45dd2cef576900f18e5fa4235be4c8566547` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 526 linii.

- **Ustalenie:** Linie 1–27, 455–526: skrypt po DOMContentLoaded aktywuje serwerowy HR_ACTIVE_TAB, synchronizuje aria-selected/hidden i query tab, obsługuje klawiaturę zakładek, delegowane akcje kart/elementów, formularz headhuntera i przewinięcie do rekordu; źródłem atrybutów jest templates/views/hr/main.php.
- **Ustalenie:** Linie 29–87: HR_LANG i HR_API/CSRF_TOKEN wstrzyknięte przez szablon (main.php:1092–1157), hrl podstawia parametry, hrApi POST FormData z _token i X-Requested-With, wymaga JSON i propaguje błąd HTTP. src/HRApi.php:53–108 potwierdza Auth, HR access i CSRF; nie jest to klient ogólnego publicznego API.
- **Ustalenie:** Linie 89–132: listy ID zdarzeń pochodzą z HR_EVENT_IDS/HR_UNREAD_EVENT_IDS; po starcie wysyła mark_events_notified, po wejściu w history mark_events_read, zmienia klasy/badge. Obie porażki są nieme; przy braku odpowiedzi UI zachowa stan i ponawia read przy następnym wejściu, notified tylko przy kolejnym załadowaniu strony.
- **Ustalenie:** Linie 134–289, 431–453: akcje hire/reject/fire/fire_technical/renew/bonus potwierdzane przez confirmAction/promptInput; aktualizują kartę lub przeładowują stronę, toastami obsługują API/network. Token mapowany przez scope:id jest zachowywany po wyjątku sieciowym, ale usuwany po każdej odpowiedzi 2xx przed oceną result.success (renew/bonus); do weryfikacji wpływ na powtórkę niejednoznacznego wyniku.
- **Ustalenie:** Linie 304–395: headhunter wymaga spec, salary jest parseFloat i walidowane tylko truthiness; ujemna wartość przechodzi front, lecz src/HRApi.php:507–520 odrzuca salary<=0/bonus<0 i nie-finite. Po odpowiedzi 2xx token oferty jest czyszczony przed rozgałęzieniem accept/negotiate/reject; counteroffer wstawia salary/bonus do pól; brak klientowej walidacji bonusu i górnego limitu.
- **Ustalenie:** Linie 397–429: toast preferuje window.showGameToast, w przeciwnym razie alertInfo; countdown używa sekund data-end→ms, setInterval na element, pokazuje 00:00 po terminie bez anulowania interwału. Test tests/Unit/HRUiLanguageContractTest.php:40–55 sprawdza tylko statyczne fragmenty DOM/JS, nie zachowanie przeglądarki.
- **Ustalenie:** Weryfikacja node --check przeszła: tylko składnia; nie uruchomiono aplikacji, fetch, DOM ani PHP.
- **Zależności do sprawdzenia:** `templates/views/hr/main.php`, `src/HRApi.php`, `src/HR/EmployeeBonusService.php`, `tests/Integration/EmployeeNegotiationServiceTest.php`, `tests/Unit/HRUiLanguageContractTest.php`.
- **Granica dowodu:** Zależności globalne confirmAction, promptInput, alertInfo, showGameToast oraz backend innych akcji wymagają przeglądarkowego/integracyjnego sprawdzenia; node --check nie jest testem funkcjonalnym.

### `assets/js/hr_raises.js`

**Blob:** `57d0f8399a5b55382b069fcb561241759a53638c` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 134 linii.

- **Ustalenie:** Linie 1–33: IIFE mapuje accept/reject/postpone na akcje HTTP, generuje token UUID lub fallback i przechowuje go per element/action w dataset do ponowień; błędna akcja/ID kończy się bez żądania.
- **Ustalenie:** Linie 35–73, 120–134: decyzja używa data-raise-action/request-id/employee-name z templates/views/hr/main.php:325–349, confirmAction, setBusy/aria-busy, hrApi z hr.js; po dowolnej odpowiedzi 2xx czyści token i pokazuje success/reload 5,2 s; przy błędzie pokazuje toast i pozwala ponowić ten sam token.
- **Ustalenie:** Linie 75–118: formularz sprawdza reportValidity oraz Number.isFinite(salary) i salary>0, pokazuje potwierdzenie formatowane HR_LOCALE; min/max/step/disabled ustala szablon (main.php:264–284,329–340), nie sam JS. Wysyła offered_salary/request_id/token, backend src/HRApi.php:408–448 dispatchuje do EmployeeRaiseRequestService::negotiate i maskuje błędy 422/409.
- **Ustalenie:** Linie 80–105: token negocjacji jest przypięty do formularza, nie do wartości salary; po niejednoznacznym błędzie sieciowym zmiana kwoty przed ponowieniem wyśle ten sam token z nową kwotą. Test tests/Integration/EmployeeRaiseRequestServiceTest.php:127–135 potwierdza, że serwis odrzuca zmieniony payload; wymaga testu przeglądarkowego/izolowanego API dla pełnego scenariusza.
- **Ustalenie:** Linie 35–38, 57–66, 99–111: każdy wynik HTTP 2xx trafia do complete() jako success; backend ustawia success=true także przy negocjacji z result=rejected_offer i statusem open (src/HRApi.php:423–436; test:137–151), więc UI używa toast typu success i przeładowuje stronę także po odrzuceniu oferty. Nie jest to dowód błędnego zapisu, lecz myląca semantyka komunikatu.
- **Ustalenie:** Weryfikacja node --check przeszła: tylko składnia; brak uruchomienia przeglądarki/serwera i PHP.
- **Zależności do sprawdzenia:** `assets/js/hr.js`, `templates/views/hr/main.php`, `src/HRApi.php`, `src/HR/EmployeeRaiseRequestService.php`, `tests/Integration/EmployeeRaiseRequestServiceTest.php`.
- **Granica dowodu:** Brak testu DOM/HTTP; walidacja HTML i ograniczenia serwera poza punktowo śledzoną ścieżką nie zostały wykonane.

### `assets/js/hr_strikes.js`

**Blob:** `24ee9a22db7c596361ae9bcfb142d92896a97d61` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 99 linii.

- **Ustalenie:** assets/js/hr_strikes.js:4–29,86–99: click na [data-open-strike-negotiation] parsuje strike ID, oznacza przycisk disabled/aria-busy, wywołuje hrApi('open_strike_negotiation',{strike_id}), pokazuje toast i planuje reload po 5200 ms; przy błędzie pokazuje toast error i odblokowuje przycisk.
- **Ustalenie:** assets/js/hr_strikes.js:32–50,86–97: submit formularza jest preventDefault; reportValidity i walidacja strikeId/expectedRound/finite raisePct oraz bonusPerMember i co najmniej jednej dodatniej wartości odbywa się przed confirmAction. Kod nie narzuca w JS górnej granicy ani nie sprawdza, czy druga wartość jest nieujemna.
- **Ustalenie:** assets/js/hr_strikes.js:51–83: po potwierdzeniu generuje lub odzyskuje token idempotencji (crypto.randomUUID, fallback Date.now/Math.random), wysyła strike_id, raise_pct, bonus_per_member, expected_round, idempotency_token do hrApi('submit_strike_offer'); sukces usuwa token, umieszcza dialog przez textContent, pokazuje success/warning i odświeża po 5200 ms; błąd zachowuje token dla próby ponownej i odblokowuje przycisk. Token przechowywany na formularzu zostaje ten sam nawet przy zmianie wartości po błędzie.
- **Ustalenie:** assets/js/hr_strikes.js:23–28,46–82 korzysta z zewnętrznych hrApi, hrl, showToast, confirmAction i window.HR_LOCALE; nie zawiera własnego endpointu ani implementacji tych funkcji.
- **Zależności do sprawdzenia:** `src/HRApi.php`.
- **Granica dowodu:** Przegląd statyczny; brak uruchomienia w DOM/HTTP i brak potwierdzenia walidacji serwerowej czy skutków DB.

### `assets/js/legal.js`

**Blob:** `e56e56ef81c01df25e93024a8b66233c4eb625c4` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 154 linii.

- **Ustalenie:** Skrypt nie wykonuje fetch: przechwytuje submit formularzy legal-submit-form i legal-bribe-form w capture, preventDefault i otwiera confirmAction; po potwierdzeniu ustawia dataset.confirmed, wywołuje requestSubmit/form.submit i resetuje flagę; bez confirmAction wysyła formularz natywnie (47–100,103–153). Formularze zawierają CSRF::field i action/region_id (templates/views/legal/main.php:164–174; templates/views/legal/_bribe_button.php:32–41), a endpoint weryfikuje login/BoardAccess, rate limit i CSRF (public/legal.php:8–69); ten JS sam nie waliduje żądania.
- **Ustalenie:** escHtml koduje region, czas i koszt z data-* przed wstawieniem w bodyHtml (33–39,65–84,120–137), ale etykiety/ostrzeżenia z window.LEGAL_LANG są konkatenowane bez escape (69–84,124–137), a JSON tłumaczeń jest osadzony przez PHP (templates/views/legal/main.php:705–719); bez dowodu sterowania tłumaczeniami przez użytkownika to ryzyko wymagające weryfikacji, nie potwierdzony XSS.
- **Ustalenie:** W obu oknach waluta ma na sztywno 'PLN' (82,133), podczas gdy szablon ustala USD dla en, EUR dla de i PLN dla pl (templates/views/legal/main.php:24–25; templates/views/legal/_bribe_button.php:15–16). Modal może więc podać inną walutę niż formularz/strona; po POST komunikat flash jest pobierany z data-error/data-success i usuwany, z fallbackiem toast (10–31).
- **Ustalenie:** Brak osobnych testów JS w tych pięciu plikach; node --check przeszedł, ale nie sprawdza DOM, potwierdzenia ani serwera.
- **Zależności do sprawdzenia:** `templates/views/legal/main.php`, `templates/views/legal/_bribe_button.php`, `public/legal.php`, `assets/js/legal_design.js`.
- **Granica dowodu:** Pełny odczyt JS; zależności obejrzane punktowo, bez testu przeglądarkowego i bez wykonania POST.
- **Granica dowodu:** node --check potwierdza tylko składnię, nie E2E; nie uruchamiano PHP/SQL/CRON.

### `assets/js/legal_design.js`

**Blob:** `c345288c53aa8fcfba63c722dfb5a406d9e1df7d` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 375 linii.

- **Ustalenie:** Skrypt czyta regiony z kart serwerowych i historię/statusy z JSON w data-* (23–51), sortuje regiony według uwagi i nazwy, liczy aktywne i wymagające uwagi (52–103), a następnie buduje dialog, priorytety, filtry i listę z textContent (105–375). Źródła data-history-data/statuses i daty due-epoch występują w templates/views/legal/main.php:43–50,115–129; nie wykonuje fetch ani POST, tylko klonuje istniejące formularze z hidden CSRF (248–267).
- **Ustalenie:** Datę wyświetla przez proste rozcięcie YYYY-MM-DD HH:mm do DD.MM.YYYY, HH:mm, bez walidacji/konwersji strefy (15–21); overdue liczy data-legal-due-epoch * 1000 < Date.now() (59–64), więc dokładna równość nie jest overdue. Historyczne kwoty używają Number(cost).toLocaleString(L.locale) i L.currency (192–205), bez sprawdzenia błędnego locale/NaN. PHP zapisuje epoch przez strtotime i locale w data-* (templates/views/legal/main.php:24–25,43–50,120–128).
- **Ustalenie:** Obie wartości JSON.parse są bez try/catch (30–32); błędne dane zatrzymają init przed shell.classList.add i root.hidden=false (366–367), więc nowy widok nie powstanie, pozostanie serwerowy. Dla no_decision zmienia tekst klonowanego przycisku na retry, ale nie podmienia jego action ani CSRF (248–250); native submit przechwytuje assets/js/legal.js:47–100. Dialog jest zamykany po preventDefault formularza (113–117), co oddaje modalowi potwierdzenia top layer.
- **Ustalenie:** node() używa textContent dla danych regionu i historii (8–13,159–205,324–338), co ogranicza HTML injection w renderowanych tekstach; URL skrótu przejmuje href istniejącego linku strony (71–85), nie pochodzi bezpośrednio z JSON. Brak testu DOM/daty/locale w tych pięciu plikach.
- **Zależności do sprawdzenia:** `templates/views/legal/main.php`, `public/legal.php`, `assets/js/legal.js`, `src/Legal/ApplicationHistory.php`.
- **Granica dowodu:** Pełny odczyt JS; tylko punktowy wgląd w szablon/endpoint/historię.
- **Granica dowodu:** node --check przeszedł, ale nie jest testem DOM ani E2E; brak wykonania PHP/SQL/CRON.

### `assets/js/logistics_design.js`

**Blob:** `a15f866843696b2b7e46a1b2b6bc63d73ccf7b1a` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 150 linii.

- **Ustalenie:** IIFE kończy się, gdy brak .logistics-design; przy obecności ustawia globalnie documentElement.style.scrollBehavior='auto', czyta nav linki # i sekcje. Klik linku do istniejącej sekcji robi preventDefault, aria-current='location', scrollIntoView(auto) i history.replaceState; scroll (passive, rAF) wybiera ostatnią sekcję na dole lub ostatnią z top<=140; początkowy aria-current jest w szablonie na overview i nie jest jawnie synchronizowany z hash do pierwszego scrolla (8–43; templates/views/logistics/main.php 2–12).
- **Ustalenie:** makeSelectable dla istniejących kart hubów i rurociągów buduje przyciski z nazwą (textContent), klonuje facts/badge, nadaje card.id według indeksu i button aria-controls, przenosi grid do browser; ukrywa wszystkie oprócz pierwszej krytycznej lub pierwszej, aria-current='true' na wybranym przycisku. Klik button wybiera i animuje wyłącznie bez reduced motion; linki data-logistics-object/data-object-id wybierają kartę po numerycznym ID, focus({preventScroll:true}), scrollIntoView i podmieniają hash na sekcję (45–101,112–120). Źródła kart/ID/facts: owned_hubs_section.php 29–30,69–98 i pipelines_section.php 76–90,150–160; linki: hub_incidents_section.php 48–58 i owned_hubs_section.php 15–21.
- **Ustalenie:** Hash przy załadowaniu: scrollRestoration='manual', decodeURIComponent(hash), setTimeout scrollIntoView na start i pageshow jednorazowo po 50 ms (102–111). Błędne procentowe kodowanie hash może rzucić URIError w callbacku; brak obsługi, a nadpisanie historii scrollRestoration i inline scrollBehavior nie jest odwracane. Link do brakującej karty przechwytuje kliknięcie, ale select nie robi nic, mimo ustawienia hash sekcji (90–120). Nie jest to potwierdzona awaria przeglądarki.
- **Ustalenie:** Progress: data-progress-width jest clamp do 0..100 przez Number, ustawiane natychmiast przy reduced motion lub bez IntersectionObserver, w przeciwnym razie animowane po wejściu i unobserve (122–137); dane w tanker_buffer_card.php:26, owned_hubs_section.php:178,211 i pipelines_section.php:136. Incydenty porównuje leksykograficznie data-event-time do poprzedniej wartości sessionStorage per player, dodaje is-new tylko przy poprzedniej wartości i gdy brak reduced motion; wyjątki storage są tłumione (138–149; hub_incidents_section.php:53). Kolejność tekstowych dat musi być chronologicznie sortowalna, czego JS sam nie weryfikuje.
- **Ustalenie:** node --check assets/js/logistics_design.js przeszedł (składnia), ale nie bada DOM, aria, historii, IntersectionObserver, storage ani renderu. JS nie wykonuje fetch/POST; zmienia jedynie DOM i stan lokalny.
- **Zależności do sprawdzenia:** `public/logistics.php`, `templates/views/logistics/main.php`, `templates/views/logistics/sections/owned_hubs_section.php`, `templates/views/logistics/sections/pipelines_section.php`, `templates/views/logistics/sections/hub_incidents_section.php`, `templates/views/logistics/sections/tanker_buffer_card.php`, `assets/css/logistics_design.css`, `assets/js/logistics_hubs.js`.
- **Granica dowodu:** Pełny odczyt 150 linii, punktowe odczyty szablonów i sąsiednich skryptów; bez przeglądarki, interakcji i E2E.
- **Granica dowodu:** node --check oznacza wyłącznie poprawność składni; nie wykonywano PHP/DB/SQL/CRON.

### `assets/js/logistics_hub_wells.js`

**Blob:** `5548fed55fd841bc285da86d4ee9d83d0f582501` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 290 linii.

- **Ustalenie:** Linie 91–125, 144–228, 253–272: GET hub_wells, unassigned_wells i assignable_hubs; API src/HubApi.php:381–416 sprawdza własność huba przy hub_wells, a src/Hub/ViewHubsTrait.php:163–236 filtruje huba po właścicielu/najemcy. Odpowiedzi renderowane przez innerHTML po Hub.esc tekstów, z Number dla ID; status hub.status (l. 58) jest surową wartością zamiast tłumaczenia.
- **Ustalenie:** Linie 230–251 i 274–289: POST assign_well/transfer_well, detach_well w l. 127–142; obsługa success/error i przeładowanie po sukcesie. no_hub_permit ma osobny modal tylko przy przypisaniu (l. 241); pozostałe błędy to ogólny komunikat. Backend jest źródłem walidacji i kosztów.
- **Ustalenie:** Linie 253–268: modal przeniesienia pobiera assignable_hubs bez page, odfiltrowuje obecny hub i nie renderuje paginacji; API src/HubApi.php:396–411 zwraca tylko 5 hubów na stronę. Przy >5 hubach alternatywy na kolejnych stronach są niedostępne; jeśli bieżący zajmuje miejsce na stronie, może pojawić się fałszywe transfer_none.
- **Ustalenie:** Linie 49 i 231: condition_pct = 0 zostaje zastąpione 100 przez `|| 100`, dlatego ostrzeżenie o krytycznym stanie może zostać pominięte; backend nadal rozstrzyga możliwość przypisania. Linie 144–191: tryb przypisywania z karty wpisuje data-fee=0 i nie ma data-acq-access-fee ani data-condition; costConfirmation w l. 72–88 nie pokazuje opłaty dostępu/ostrzeżenia o stanie tej ścieżki, mimo że src/Hub/ViewHubsTrait.php:205–233 oblicza accessFee dla ścieżki assignable_hubs.
- **Ustalenie:** Linie 91–125 i 198–228: asynchroniczne GET mogą nadpisać modal wynikami wcześniejszego żądania po zmianie wybranego huba/odwiertu; brak tokenu żądania/AbortController. To hipoteza o zachowaniu przy szybkich kliknięciach, bez testu UI.
- **Zależności do sprawdzenia:** `assets/js/logistics_hubs.js`, `assets/js/logistics_hub_browser.js`, `src/HubApi.php`, `src/Hub/ViewHubsTrait.php`, `templates/views/logistics/modals/hub_modals.php`, `templates/views/logistics/sections/owned_hubs_section.php`.
- **Granica dowodu:** node --check potwierdza tylko składnię; brak testu renderu, HTTP, uprawnień i kosztu na izolowanej instancji.

### `assets/js/logistics_hubs.js`

**Blob:** `e41a98f223a32baf5972cf120af18cd10532651f` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 297 linii.

- **Ustalenie:** Linie 8–11, 166–179: HUB_API/HUB_CSRF z assets/js/logistics_config.js; hubPost wysyła action i _token, hubGet parametry w query. API src/HubApi.php:65–89 egzekwuje logowanie, CSRF POST, BoardAccess i adminOnlyActions; autoryzacja nie polega wyłącznie na UI. fetch nie sprawdza response.ok ani Content-Type, a response.json może zgłosić wyjątek obsługiwany jako ogólny błąd.
- **Ustalenie:** Linie 37–90: brak pozwolenia obsługiwany modalem/POST do legal z csrf_token, action, region_id; kontekst pochodzi z API. Linie 93–164: confirmAction oraz dialogi z focus/Tab/Escape, ale bez confirmAction Promise kończy się false; brak aktywnego trapu przy braku fokusowalnych elementów.
- **Ustalenie:** Linie 222–251: hubBuildSubmit celuje w #hub-build-form i action build_hub, ale szablon templates/views/logistics/modals/hub_modals.php:78–139 ma #hub-buy-new-form, a src/HubApi.php:86–89 blokuje build_hub zwykłym graczom; ścieżka jest w obecnym widoku martwa, nie należy traktować jej jako dostępnej dla gracza.
- **Ustalenie:** Linie 254–296: repair_hub i toggle_pause wymagają admin w API, upgrade_hub/set_mode działają dla uprawnionego właściciela/najemcy; po sukcesie reload. W szablonie templates/views/logistics/sections/owned_hubs_section.php:103–105 naprawa prowadzi do /technical zamiast hubRepair. Odpowiedź new_status z toggle_pause nie jest używana, lecz przeładowanie odświeża stan.
- **Ustalenie:** Linie 185–195: pobranie karty przez interpolację hubId do selektora i esc dla &, <, >, cudzysłowu; wywołania ze strony używają identyfikatorów numerycznych, lecz brak lokalnej walidacji typu argumentu; uprawnienia weryfikuje backend.
- **Zależności do sprawdzenia:** `assets/js/logistics_config.js`, `assets/js/logistics_hub_browser.js`, `src/HubApi.php`, `api/internal/HubApi.php`, `templates/views/logistics/modals/hub_modals.php`, `templates/views/logistics/sections/owned_hubs_section.php`, `public/logistics.php`.
- **Granica dowodu:** node --check potwierdza wyłącznie składnię; brak wykonania w przeglądarce i bez żądań HTTP.

### `assets/js/logistics_optimizer.js`

**Blob:** `39287bc5975767d55a01637cbe5c9e43dffa87fa` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 314 linii.

- **Ustalenie:** Linie 4–63: LOGISTICS_API/CSRF z assets/js/logistics_config.js; GET summary/cooldown i POST optimize z _token oraz X-Requested-With. src/LogisticsApi.php:22–56 weryfikuje login, CSRF dla POST, BoardAccess technical lub logistics i metodę POST dla optimize; serwer src/LogisticsService.php:68–96 sprawdza mode, cooldown i saldo.
- **Ustalenie:** Linie 8 i 94–109: koszty trybów zakodowane w JS pokrywają aktualne src/LogisticsService.php:32–37, ale mogą się rozejść po zmianie serwera; właściwa opłata pochodzi z result.mode_cost (l. 174–179).
- **Ustalenie:** Linie 129–171, 174–203: summary/renderComparison aktualizują DOM, większość tekstów ucieka przez escapeHtml, wartości numeryczne formatują Number. Gdy GET cooldown rzuci wyjątek po poprawnym GET summary (l. 164–170), catch zastępuje cały poprawny widok sumaryczny błędem; brak odrębnej obsługi błędu cooldown.
- **Ustalenie:** Linie 205–244, 246–314: potwierdzenie w footer, blokada przy wysyłaniu, render wyniku i odświeżenie summary; po błędzie HTTP/JSON resetFooter przywraca przycisk bez pobrania cooldown, więc UI może zaproponować ponowienie mimo odrzucenia po stronie serwera. startCooldown (l. 112–126) bazuje na lokalnym timerze, a resetFooter wymienia jego przycisk; serwer utrzymuje cooldown w sesji.
- **Ustalenie:** Linie 273–313: delegacja kliknięć i wybór trybu dla #logistics-modal, ale bez obsługi Escape/focus trap/aria-modal; szablon templates/views/logistics/modals/optimizer_modal.php:1–49 ma tylko hidden overlay. Wymaga sprawdzenia dostępności w przeglądarce.
- **Zależności do sprawdzenia:** `assets/js/logistics_config.js`, `api/internal/LogisticsApi.php`, `src/LogisticsApi.php`, `src/LogisticsService.php`, `templates/views/logistics/modals/optimizer_modal.php`, `public/logistics.php`.
- **Granica dowodu:** node --check potwierdza wyłącznie składnię; brak testów przeglądarkowych i żądań API/DB.

### `assets/js/logistics_staffing.js`

**Blob:** `40e1b38cff1267fba965bb3f6ad9f9e73036cf04` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 285 linii.

- **Ustalenie:** assets/js/logistics_staffing.js:1–18,38–94: bez HUB_STAFFING_CONFIG skrypt kończy działanie; wybiera en-US/pl-PL, esc koduje &<>"' przed HTML, generuje badge runtime, etykiety i podsumowanie oparte na runtime_effects.hub_throughput_pct i missing_roles.
- **Ustalenie:** assets/js/logistics_staffing.js:96–175: modal renderuje chipy aktywnych przypisań i kandydatów; zablokowany lub niekwalifikowany kandydat ma powód, link do HR i (jeśli ma current_assignment_id) formularz release; inni dostają formularz POST do config.post_url z csrf_token, action=assign_hub_staff, hub_id, source_type/id, allocation_pct (min/step 0.01, max z free/current), opcjonalnie osobny release_hub_staff z assignment_id. Przyciski są type=button, więc przepływ przez confirmAndSubmit.
- **Ustalenie:** assets/js/logistics_staffing.js:177–206: pobiera dane config.hubs[hubId], dzieli kandydatów na dostępnych i busy, renderuje summary/sekcje, pokazuje link rekrutacji przy pustej liście, zamienia body.innerHTML i otwiera przez HubUI albo hidden=false; window.hubStaffingModal eksportuje wywołanie (linie 226–228).
- **Ustalenie:** assets/js/logistics_staffing.js:208–224,267–285: klik delegowany na data-staffing-submit/release pyta przez window.confirmAction lub od razu form.submit() przy braku helpera; nie woła reportValidity/checkValidity, a programowe submit omija natywną walidację formularza. Flash usuwa węzeł, wyświetla error/info/toast zależnie od dostępnych globalnych helperów (linie 230–265).
- **Granica dowodu:** Brak uruchomienia DOM/HTTP/PHP; ustawienia config i odpowiedź serwera nie zostały zweryfikowane. HTML min/max i JavaScriptowe filtry nie zastępują walidacji serwerowej.
- **Granica dowodu:** Dla tej karty nie wskazano ścieżki pojedynczej zależności plikowej, bo skrypt czyta globalny config i formularzowy config.post_url zamiast literalnego endpointu (assets/js/logistics_staffing.js:4,112,160–170).

### `assets/js/player_menu_editor.js`

**Blob:** `17437fb53ea138eb6bff40988590916ea0e102e4` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 173 linii.

- **Ustalenie:** Linie 1–41: IIFE wymaga #player-menu-editor i parsuje JSON #pm-data; tworzy roboczą kopię grup, elementów i wersji, zaznaczenie oraz stan dirty/saving. Ikony i etykiety są odczytywane z config; node() używa textContent dla podpisów.
- **Ustalenie:** Linie 42–121: drzewo z wyszukiwaniem, grupami/details i pozycjami, podgląd z filtrem roli i hidden, formularz pól PL/EN/DE/tras/ikon, aria-expanded/aria-pressed dla widoku telefonu i zwijania. Filtr roli działa dla protectedRoutes i ukrywa nieaktywne elementy/grupy, lecz role 'all' i brak wymagań pokazują pozycję; to symulacja menu, nie autoryzacja serwera.
- **Ustalenie:** Linie 122–139: zmiany formularza, przenoszenie, kolejność, dodawanie draftów, upload ikon do 1 MiB PNG/JPEG/WebP jako data URL, usuwanie po confirmAction (grupy z dziećmi blokowane), undo; payload rozdziela istniejące/nowe/usunięte rekordy oraz version. Kontrola formatu ikony jest wyłącznie po stronie UI.
- **Ustalenie:** Linie 140–170: save blokuje interakcje, POSTuje csrf_token i payload JSON na config.endpoint; niepowodzenie/conflict zachowuje draft i dodaje reload, sukces rejestruje ikony custom-* z odpowiedzi, zastępuje original/state danymi serwera, czyści dirty, renderuje; przed opuszczeniem brudnej strony pojawia się ostrzeżenie. Nie ma asercji testowych, a zapewnienie trwałości wymaga odczytu API/serwera.
- **Ustalenie:** templates/views/admin/template_editor/player_menu.php:29–31 definiuje protectedRoutes, CSRF i endpoint /admin/player_menu_save.php, :78 osadza #pm-data; admin/template_editor.php:690 deklaruje ładowanie player_menu_editor.js. Asercje live zachowania edytora nie wynikają z samego skryptu.
- **Zależności do sprawdzenia:** `templates/views/admin/template_editor/player_menu.php`, `admin/template_editor.php`, `admin/player_menu_save.php`, `assets/css/player_menu_editor.css`.
- **Granica dowodu:** Pełny odczyt 173 linii; node --check tylko składnia, bez testów E2E/PHP/DB, bez potwierdzenia transakcji, walidacji tras/uprawnień i synchronizacji zapisu po stronie serwera. Zależności wymagają pełnego audytu.

### `assets/js/privacy_banner.js`

**Blob:** `b57807aa6473fdd661c08d12b355ab90ad47f4ef` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 216 linii.

- **Ustalenie:** P2: saveConsent zapisuje cookie privacy_consent zanim fetch API potwierdzi sukces; API błędy są ignorowane, UI chowa banner (100-124). Przeglądarka może deklarować zgodę bez śladu po stronie serwera.
- **Ustalenie:** Cookie 365 dni, SameSite=Lax i path=/; brak Secure, choć dokumentacja deklaruje HTTPS; politykę należy ustalić i przetestować. Lokalna konfiguracja bannerEnabled/versions pochodzi z PHP.
- **Ustalenie:** Konfiguracja i teksty modalne są wybierane z DOM, zdarzenia bindują po DOMContentLoaded; stan zgód wymaga testu reconsent wersji.
- **Zależności do sprawdzenia:** `api/privacy/consent.php`, `src/Privacy/PrivacyConsentService.php`, `templates/views/privacy/banner.php`.
- **Granica dowodu:** Ścieżkę komponentu sprawdzić, brak testu przeglądarkowego i opinii prawnej

### `assets/js/profile.js`

**Blob:** `9bfde886b6660afa7d6fd139f53bc206285d2aec` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 285 linii.

- **Ustalenie:** Linie 14–77: skrypt kończy pracę bez #profileConfig; z data-attributes pobiera teksty, pokazuje komunikaty przez alertError/alertInfo/showGameToast albo lokalny flash, konsumuje flash serwera i aktualizuje podgląd nazwy firmy przez textContent.
- **Ustalenie:** Linie 79–135: toggles zmieniają typ pola hasła, ikonę i aria-label; wskaźnik hasła liczy maksymalnie 5 punktów za długości 8/12, dużą literę, cyfrę i znak specjalny; dla niepustego hasła o wyniku 0 wyświetla pustą etykietę; potwierdzenie porównuje wartości przy input confirm (nie przelicza przy późniejszej zmianie nowego hasła). To wskaźniki klienta, nie walidacja serwera.
- **Ustalenie:** Linie 137–216: avatar dopuszcza JPEG/PNG/WebP do 2 MiB po MIME/rozmiarze, robi lokalny blob URL i feedback; FileReader/Image/canvas zmniejszają do najwyżej 128 px i próbują sześciu jakości JPEG aż do 6000 B, ale ostateczna jakość może nadal dać większy blob; funkcja tworzy też data URL podglądu, którego wywołujący nie używa.
- **Ustalenie:** Linie 218–285: POST surowych bajtów JPEG na bieżący pathname+search z X-CSRF-Token i X-Upload-Action; dopiero odpowiedź JSON data.ok daje sukces i ewentualne avatar_url w profilu/topbarze; błąd JSON/API/kompresji wyświetla komunikat, lecz lokalny podgląd wybranego pliku zostaje do nadpisania; pagehide zwalnia blob URL. public/profile.php:314–318 deklaruje profile.js, a :23–36 i :131–137 wskazują osobną akcję upload_avatar i JSON, bez weryfikacji pełnej ścieżki serwera w tej karcie.
- **Zależności do sprawdzenia:** `public/profile.php`, `templates/views/profile/main.php`, `assets/css/profile.css`, `tests/profile_browser.cjs`.
- **Granica dowodu:** Pełny odczyt 285 linii, node --check tylko składnia; nie uruchomiono UI/uploadu ani testów, nie potwierdzono ochrony CSRF, autoryzacji, MIME i zapisu po stronie serwera. Brak asercji testowych w samym pliku.

### `assets/js/protection.js`

**Blob:** `0d65c35d4e47d1bbec8193a279678abc9116ee42` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 111 linii.

- **Ustalenie:** Obsługuje otwieranie/zamykanie modalów road/hub/pipeline, parsuje data-target-id przez parseInt || 0 (26–59), a przy zakupie sprawdza tylko niezerowe selected.id (93–109). POST URLSearchParams przesyła csrf_token, option_code, target i target_id do PROTECTION_API (61–72). Łańcuch konfiguracji istnieje: templates/views/logistics/partials/protection_client_config.php:2–11 → assets/js/logistics_config.js:42–45 → skrypt ładowany po konfiguracji na public/logistics.php:30–43; endpoint waliduje metodę, auth, rate limit, CSRF, typ i własność celu (public/protection.php:21–83). Walidacja klienta nie jest zabezpieczeniem serwera.
- **Ustalenie:** Odpowiedź jest zawsze parsowana resp.json bez sprawdzenia resp.ok; po data.success wywołuje closeAll i po 1200 ms location.replace z pathname+search (68–85). Błąd sieci/nie-JSON trafia do catch, ale pokazuje go tylko gdy istnieje showGameToast (86–90); showError przy nieprawidłowym celu ma fallback alertError (18–24,95–97). Przy braku toast błąd sieci jest więc cichy. Brak finally/blokady przycisku umożliwia kolejne kliknięcia i żądania przed odpowiedzią (61–109).
- **Ustalenie:** Tekst potwierdzenia pochodzi z LANG.confirm_question z jednorazową zamianą :name i :cost na data-option-*; jest przekazany jako zwykły tekst do confirmAction, nie jako bodyHtml (99–105). Domyślne 'Error' i 'Invalid protection target.' są po angielsku (76,88,96), gdy konfiguracja tłumaczeń nie jest dostępna. Nie sprawdzono runtime renderowania ani nie wykonano requestów.
- **Zależności do sprawdzenia:** `assets/js/logistics_config.js`, `templates/views/logistics/partials/protection_client_config.php`, `templates/views/logistics/partials/client_config.php`, `templates/views/logistics/sections/protection_sections.php`, `public/logistics.php`, `public/protection.php`, `src/ProtectionService.php`.
- **Granica dowodu:** Pełny odczyt JS; punktowo obejrzano konfigurację, formularze i endpoint, nie cały serwis.
- **Granica dowodu:** node --check potwierdza składnię, nie POST/CSRF w przeglądarce; nie uruchamiano PHP/SQL/CRON.

### `assets/js/recruitment.js`

**Blob:** `d422bcc6378b7bf154e6f026b8abfe97338f46f6` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 453 linii.

- **Ustalenie:** Dynamiczne imię/nazwisko kandydata, nationality i id HTML-escapowane w renderCandidateCard; numeryczne pola skill/age przetwarzane niespójnie, zależne od schematu API.
- **Ustalenie:** P2: ready_at jest przekształcany na lokalny Date bez offsetu (165), więc klient w innej strefie może widzieć błędny countdown względem serwera.
- **Ustalenie:** Cykliczny check_status co 5 sekund do czasu otwarcia listy; po błędzie API kontynuuje, testować taryfę i idempotencję.
- **Zależności do sprawdzenia:** `api/internal/RecruitmentAPI.php`, `assets/js/modal.js`, `src/HRApi.php`.
- **Granica dowodu:** Wymagany test w przeglądarce z API i timezone

### `assets/js/sabotage.js`

**Blob:** `e196625856caa55649700ddf9bbb83c36ef86276` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 142 linii.

- **Ustalenie:** Komunikaty, nazwy i sugestie używają textContent; brak innerHTML dla danych gracza w tym pliku.
- **Ustalenie:** JSON.parse z data-quotes/cooldowns bez try/catch; nieprawidłowy atrybut może przerwać UI. Koszt/cooldown/środki są tylko wskazówką po stronie klienta; autorytatywne reguły w SabotageService.
- **Ustalenie:** Submit zabezpieczony lokalnym submitted i tokenem jednorazowym po stronie kontrolera; konieczny test dwóch kart/sesji.
- **Zależności do sprawdzenia:** `public/sabotage.php`, `src/SabotageService.php`, `templates/views/sabotage/main.php`.
- **Granica dowodu:** Brak przeglądarki i DB

### `assets/js/technical.js`

**Blob:** `8b302de909fa38611498f8da034d517ddbfe4c23` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 495 linii.

- **Ustalenie:** P2: dismissAllNotifs i dismissNotif mają try/finally bez catch; odrzucony fetch wywoła nieobsłużoną obietnicę i pierwszy przycisk może pozostać disabled / tekst '...' (45-89,91-130).
- **Ustalenie:** W techTaskConfirm callback zapisuje koszty i wysyła POST AJAX; _pendingFetches chroni auto-refresh podczas żądania. Deep-link dopuszcza tylko wymienione task_type i usuwa parametry po odczycie (394-495).
- **Ustalenie:** Potwierdzenie oceny kandydata wywołuje form.submit() bez interaktywnej walidacji constraintów po modalu; wymagany E2E.
- **Zależności do sprawdzenia:** `api/internal/TechNotifApi.php`, `assets/js/modal.js`, `src/TechnicalPage/ActionsTrait.php`, `templates/views/technical/main.php`.
- **Granica dowodu:** Brak testu przeglądarki i wymuszonej awarii sieci

### `assets/js/well-workspace.js`

**Blob:** `a8a2ada0aacd265e0d16ac0a33fa7c2988a155ab` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 348 linii.

- **Ustalenie:** W historii dane API są wstawiane przez textContent i allowlistę icon; paginacja ogranicza przyciski do 5 i opóźniony request odrzuca stary wynik.
- **Ustalenie:** Akcje POST do endpoint z data-endpoint doklejają csrf_token, _token i csrf dla kompatybilności; walidacja działa po stronie endpointów, UI blokuje tylko duplikat wysyłki.
- **Ustalenie:** Sprzedaż odwiertu najpierw pobiera wycenę GET, potem POST z tokenem; brak ochrony przed zmianą ceny między GET i POST na poziomie klienta, serwer musi przeliczyć cenę.
- **Ustalenie:** CSS.escape i sessionStorage wymagają kompatybilności przeglądarki; błąd w history render może blokować UI, E2E otwarte.
- **Zależności do sprawdzenia:** `api/internal/WellSellApi.php`, `api/internal/WellStaffApi.php`, `public/well_history.php`, `templates/views/wells/main.php`.
- **Granica dowodu:** Bez E2E i izolowanego testu współbieżności cen

### `assets/js/well_grid.js`

**Blob:** `a7bdef21ed3d6788d56030b86677196cedac7e72` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 795 linii.

- **Ustalenie:** assets/js/well_grid.js:6–27,37–69: czyta wgConfig (lang JSON z fallback {}, csrf, pipelineApi), tłumaczy klucze z podmianą :param; zegary swap liczą lokalny czas do dataset.until, aktualizują co sekundę i po końcu planują reload; wgToggleGroup zmienia widoczność i strzałkę.
- **Ustalenie:** assets/js/well_grid.js:71–172,785–795: wgToggle zamyka pozostałe detale/sekcje i otwiera kartę; wgFocusWell rozszerza grupę, otwiera szczegół, po timeout przewija/focus; hash #wg-card-N uruchamia focus po starcie i hashchange. Osobno są toggles magazynu, sprzętu, warstwy, transportu.
- **Ustalenie:** assets/js/well_grid.js:178–279: modal rozbudowy magazynu renderuje pojemność i koszt, POST FormData well_id/csrf_token do /public/upgrade_storage.php; obsługa sukcesu pokazuje komunikat i reload po 3 s, błędów odblokowuje button. wgCloseModal animuje zamknięcie magazynu i equipment modal.
- **Ustalenie:** assets/js/well_grid.js:290–409: sprzęt używa confirmAction i POST /public/equipment_well.php z action=set_tier albo upgrade_level, tier, well_id, csrf_token; warstwa POST /public/layer_well.php z action=switch, layer_id i polem csrf (nie csrf_token). Oba przepływy przy sukcesie odświeżają stronę, przy błędzie przywracają button; wgSetTier/wgUpgradeEquipment/wgSwitchLayer pobierają globalne event.currentTarget bez przekazanego event.
- **Ustalenie:** assets/js/well_grid.js:425–552: GET /api/internal/WellSellApi.php?well_id wyświetla breakdown oraz cenę w confirmAction; POST JSON well_id/csrf_token sprzedaje, przy sukcesie animuje i usuwa kartę oraz pokazuje toast lub reload gdy nie ma karty. W callbacku card.remove() poprzedza card.closest('.wg-grid') (linie 519–535), więc próba ukrycia pustej grupy po usunięciu opiera się na odłączonym elemencie i nie znajdzie dotychczasowego rodzica.
- **Ustalenie:** assets/js/well_grid.js:570–715: wybór transportu na nieustawiony/własny/inny wyświetla potwierdzenie, a brak kupionego rurociągu otwiera wybór light/standard/heavy; GET pipeline_profiles może się nie udać i wtedy karty pokazują brak kosztów. POST FormData action=set_transport, well_id, transport_type, _token i opcjonalnie pipeline_type do /api/internal/WellStaffApi.php; success toast/reload, no_hub_permit wywołuje hubPermitModal, inne błędy alertError.
- **Ustalenie:** assets/js/well_grid.js:717–783: drugi odcinek hub→magazyn potwierdza rurociąg (odrębny komunikat dla już kupionego), ciężarówki lub bezpośredni przewóz i wysyła action=set_outbound_transport do tego samego WellStaffApi; obsługuje success, no_hub_permit i błędy analogicznie. HTML modali jest składany konkatenacją wartości z tłumaczeń/odpowiedzi; nie prześledzono pochodzenia i sanitizacji tych danych (linie 189–211,442–495,649–665).
- **Zależności do sprawdzenia:** `public/upgrade_storage.php`, `public/equipment_well.php`, `public/layer_well.php`, `api/internal/WellSellApi.php`, `api/internal/WellStaffApi.php`, `api/internal/PipelineApi.php`.
- **Granica dowodu:** Wyłącznie statyczne odczytanie JS; brak przeglądarki, żądań HTTP, walidacji endpointów i dowodu, że odpowiedź serwera jest pod kontrolą użytkownika.
- **Granica dowodu:** Zależność globalnego event oraz rodzic po card.remove() są obserwacjami kodu, nie wynikami E2E.

### `assets/js/well_staff.js`

**Blob:** `5a6209f921c459e98c2936de3d3850564f5d9d75` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 178 linii.

- **Ustalenie:** P2: dane z API staff.first_name / last_name / spec_name (63-76) i błędy API (28-31) trafiają do innerHTML bez DOM-safe encoding; źródło danych należy zbadać pod kątem możliwości zapisania HTML przez gracza. Zastąpić textContent i addEventListener.
- **Ustalenie:** Inline wsAssign(${s.id}) w HTML template także wymaga numerycznego parsowania ID; endpoint GET get_available jest uwierzytelniony, POST ma CSRF (src/WellStaffApi.php:56-83).
- **Ustalenie:** Obsługa błędów wyświetla e.message via innerHTML (78-80), potencjalne źródło niekontrolowane; modal potwierdzenia także buduje innerHTML z tłumaczeń.
- **Zależności do sprawdzenia:** `api/internal/WellStaffApi.php`, `src/WellStaffApi.php`, `templates/views/technical/tabs/well_staff.php`.
- **Granica dowodu:** Brak potwierdzonego sterowania wartościami API przez gracza i testu E2E

### `docs/laravel-migration/02_ALL_TRACKED_FILES.tsv`

**Blob:** `889180078c6b1feec1e64306f7b82b22826c176d` · **Status:** `CLOSED_DOCUMENT_ARCHIVE`.

- **Dowód pełnej kontroli inwentarza:** przetworzono wszystkie 5133 wiersze danych (5136 linii wraz z komentarzami/nagłówkiem), sprawdzając unikalność i zgodność każdej ścieżki, bloba oraz rozmiaru z drzewem Git `main` @ `7521a97aab762c1ff4dd27b249b78778922cb5ed` przez `git ls-tree` i zbiorcze `git cat-file --batch-check`. Obecny HEAD ma tych samych 5133 ścieżek z identycznymi blobami i 20 dodatkowych śledzonych dokumentów `docs/laravel-migration/00…15`. Bieżący nieśledzony raport 16 nie jest częścią licznika.
- **Statusy historyczne:** 5031 `NOT_REVIEWED`, 33 `CRITICAL_STATIC_REVIEW`, 34 `ADMIN_CONTROLLER_STATIC_SCAN`, 35 `ADMIN_VIEW_STATIC_SCAN`. Nie zastępują obecnego rejestru zamknięcia ani testów runtime.
- **Decyzja migracyjna:** archiwizować jako migawkę dowodową, **nie przenosić jako konfiguracji działającej aplikacji**; bieżącą listę generować z właściwego HEAD. Test PHP/DB nie dotyczy dokumentu inwentarzowego. Zamknięcie tej karty nie oznacza audytu zawartości 5133 wymienionych plików ani wdrożenia. Zależność dokumentacyjna: `06_FILE_BY_FILE_AUDIT_PROTOCOL.md`.

### `logs/game_debug.txt`

**Blob:** `653ce6fa3c35e539eac7aa8c5358a77c47f5608a` · **Status:** `CLASSIFIED_SENSITIVE_LOG`.

- Tylko metadane. Treść logu celowo pominięta; wymagany prywatny przegląd bezpieczeństwa.

### `mobile/android/app/src/main/res/mipmap-hdpi/ic_launcher.png`

**Blob:** `89b9cd0a48f6d4871a0a522cd25cc2a7b4e54c14` · **Status:** `CLASSIFIED_BINARY_ASSET`.

- **Granica dowodu:** Bez oceny wizualnej
- Klasyfikacja/metadata: bez deklaracji przeglądu zawartości ani wyniku działania.

### `mobile/android/app/src/main/res/mipmap-mdpi/ic_launcher.png`

**Blob:** `a3fc2459616d0390663016dac25fa6b584ed2f57` · **Status:** `CLASSIFIED_BINARY_ASSET`.

- **Granica dowodu:** Bez oceny wizualnej
- Klasyfikacja/metadata: bez deklaracji przeglądu zawartości ani wyniku działania.

### `mobile/android/app/src/main/res/mipmap-xhdpi/ic_launcher.png`

**Blob:** `f26dca5b0a99abd07ba1fb456507146cbb26e7e7` · **Status:** `CLASSIFIED_BINARY_ASSET`.

- **Granica dowodu:** Bez oceny wizualnej
- Klasyfikacja/metadata: bez deklaracji przeglądu zawartości ani wyniku działania.

### `mobile/android/app/src/main/res/mipmap-xxhdpi/ic_launcher.png`

**Blob:** `59b944b7fce606799420114252448e1a0f91c17b` · **Status:** `CLASSIFIED_BINARY_ASSET`.

- **Granica dowodu:** Bez oceny wizualnej
- Klasyfikacja/metadata: bez deklaracji przeglądu zawartości ani wyniku działania.

### `mobile/android/app/src/main/res/mipmap-xxxhdpi/ic_launcher.png`

**Blob:** `ccacebb3151f4514c4a06f12dfc858cdd94e0e41` · **Status:** `CLASSIFIED_BINARY_ASSET`.

- **Granica dowodu:** Bez oceny wizualnej
- Klasyfikacja/metadata: bez deklaracji przeglądu zawartości ani wyniku działania.

### `mobile/android/gradle/wrapper/gradle-wrapper.jar`

**Blob:** `13372aef5e24af05341d49695ee84e5f9b594659` · **Status:** `CLASSIFIED_VENDOR_BINARY`.

- **Granica dowodu:** Nie zweryfikowano pochodzenia i sygnatury dostawcy
- Klasyfikacja/metadata: bez deklaracji przeglądu zawartości ani wyniku działania.

### `phpstan-baseline.neon`

**Blob:** `e0ddcdfbd7a108f32646df05703e55602349bb46` · **Status:** `CLASSIFIED_GENERATED_DIAGNOSTIC`.

- **Ustalenie:** Wersjonowany baseline PHPStan obejmuje 268 wzorców ignorowanych błędów w 46 plikach src; suma count=269. Automatyczna mapa struktury, nie ponowne uruchomienie PHPStan ani przegląd 268 komunikatów.
- **Ustalenie:** phpstan.neon ustawia level:6, paths:src, wyklucza src/PHPMailer i reportUnmatchedIgnoredErrors:false; nieaktualne suppressions mogą być ukryte. Wymaga uruchomienia na bieżącym HEAD w izolowanym środowisku.
- **Zależności do sprawdzenia:** `phpstan.neon`, `composer.json`.
- **Granica dowodu:** Nie uruchomiono PHPStan; nie rozstrzygnięto, które ignorowane błędy odpowiadają obecnemu kodowi

### `phpstan_errors.txt`

**Blob:** `d569deda9101efa494f49d85c72eb5a457564b0b` · **Status:** `CLASSIFIED_GENERATED_DIAGNOSTIC`.

- **Ustalenie:** Historyczny wynik konsoli PHPStan zawiera około 421 linii diagnostycznych z prefiksem :numer linii, głównie brak typów elementów tablic. Jest zapisany z konfiguracji C:\xampp\htdocs, więc nie jest wynikiem uruchomienia na obecnym HEAD ani liczbą obecnych błędów.
- **Ustalenie:** Skrypt PowerShell Out-File i artefakt tekstowy zawierają ślady nieprawidłowo dekodowanych znaków. Przed migracją uruchomić phpstan z bieżącym baseline i zweryfikować niezgodności typów na aktualnym kodzie.
- **Zależności do sprawdzenia:** `phpstan.neon`, `phpstan-baseline.neon`.
- **Granica dowodu:** Nie uruchomiono narzędzia; liczba diagnostyk jest przybliżeniem regexowym, nie liczbą błędów PHPStan

### `public/error_log`

**Blob:** `e1a074b3d88c9f62720533732e822401b3e02239` · **Status:** `CLASSIFIED_SENSITIVE_LOG`.

- Tylko metadane. Treść logu celowo pominięta; wymagany prywatny przegląd bezpieczeństwa.

### `svn_repo/LOGISTICS_HUBS_README_UJEDNOLICONE_FINAL.md`

**Blob:** `eb7855f7dde06fd80c5b764164388a179ed10838` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 859 linii.

- **Ustalenie:** P1 rozbieżność wymagań z kodem: dokument (23-52,826-846) zakazuje własności hubów przez gracza, ale bieżący HubAcquisitionService.php:6-13,36-112 pozwala kupić hub z player_id>0; HubApi.php:286-296 i logistics_hub_market.js:82-85 pokazują drogę UI/API. Ustalić decyzję produktową i dane istniejących hubów przed migracją.
- **Ustalenie:** P1 rozbieżność czasu: dokument (189-229) wymaga transferu odwiertu przez ~2 ticki z zatrzymaniem produkcji i trwającymi kosztami. src/HubAssignmentService.php:306-350 zamyka starą asignację i tworzy nową active w jednej transakcji; src/Hub/AssignmentValidationTrait.php:49-59 pomija sprawdzenie starego cooldownu przy skipCurrentCheck=true. To statyczne potwierdzenie natychmiastowej zmiany przypisania, nie test efektu ekonomicznego ticka. Wymaga decyzji biznesowej i testu na izolowanej DB.
- **Ustalenie:** Dwustopniowy transport i bilans wolumenu (73-128,463-529) są wymaganiem; bieżące źródło ma leg outbound i hub_road_trips, ale nie sprawdzono end-to-end ilości w magazynie. Warunki braku skrótu oraz utraty całego kursu po kradzieży (389-405) wymagają testu.
- **Ustalenie:** Opis UI admin/pipelines.php (715-730,848-858) różni się od starszych opisów adminskiej listy/napraw; nie traktować słowa FINAL ani daty pliku jako gwarancji aktualności. Wymagania powiadomień, odliczania, kosztów i podsumowania finansów (533-705) pozostają checklistą do weryfikacji.
- **Zależności do sprawdzenia:** `src/HubAcquisitionService.php`, `src/HubApi.php`, `src/HubAssignmentService.php`, `src/Hub/AssignmentValidationTrait.php`, `assets/js/logistics_hub_market.js`.
- **Granica dowodu:** Brak pełnego audytu ścieżki backend-tick-DB oraz testu produkcji i E2E; rozbieżności kod/dokument nie dowodzą same przez się błędu wykonania

### `svn_repo/LOGISTICS_HUBS_README_V2.md`

**Blob:** `c4d8a9c0a1345123c6c272538b32de0b34d1c020` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 323 linii.

- **Ustalenie:** Dokument (5-12,46-52) ogłasza się kanonicznym dla hubów, ale jest historycznym stanem względem obecnego HEAD; opisuje realny backend/tick/porty/marine i odróżnia je od planu własności huba przez gracza (209-246). Nie traktować stwierdzeń o implementacji jako wyniku tego audytu.
- **Ustalenie:** Rozgraniczenie uprawnień z dokumentu (224-231): build/repair/upgrade/toggle/rename admin, assign/detach/transfer well gracz; przed migracją porównać z aktualnym API i autoryzacją serwera.
- **Ustalenie:** Istotny kontrakt danych (149-159): transport lądowy domyślnie nieustawiony, starszy autopreset pipeline bez rekordu well_pipelines wymaga ponownego wyboru, fallback na drogę tylko dla pipeline istniejącego, lecz nieoperacyjnego. Test istnieje wg dokumentu, nie uruchomiono go tutaj.
- **Ustalenie:** Dokument deklaruje wynik historycznych pakietów MySQL 60 testów/1769 asercji i aplikacyjnych 41 testów/2 błędy tekstów (283-295). Nie jest to aktualne CI ani runtime w tej sesji.
- **Ustalenie:** Konfiguracja admina pipeline_types w well_config (314-322) obejmuje price_pct/capacity_pct/durability_pct; porównać zapis admina, odczyt przy zakupie i efekt w ticku przed translacją do Laravela.
- **Zależności do sprawdzenia:** `src/HubService.php`, `src/WellPipelineService.php`, `public/logistics.php`, `tests/MySqlIntegration/MySqlLogisticsOwnershipFlowTest.php`.
- **Granica dowodu:** Nie uruchomiono MySQL ani nie zweryfikowano wszystkich twierdzeń dokumentu w aktualnym kodzie

### `svn_repo/LOGISTICS_HUBS_README_ulepszony.md`

**Blob:** `f0137e38eb3882a70b37d7b8490af9d484d1d999` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 900 linii.

- **Ustalenie:** Roboczy dokument (114-125,885-900) zakazuje kupowania hubów przez gracza i uznaje je za systemowe. Aktualny src/HubAcquisitionService.php:36-112 i HubApi.php:286-296 implementują zakup, więc nie jest źródłem prawdy dla obecnego modelu własności; wymaga decyzji biznesowej.
- **Ustalenie:** Wymaga opóźnionego transferu odwiertu z przerwą w dostawach i naliczaniem kosztów (162-167,603-632); bieżący HubAssignmentService.php:306-350 tworzy aktywne przypisanie w tej samej transakcji. Skutek w ticku niepotwierdzony.
- **Ustalenie:** Dokument rozróżnia wymaganie dwóch odcinków transportu i bilansu wolumenu (129-150,560-599) od opisów rzekomo wdrożonych usług (18-110). Nie uznawać tych deklaracji za przetestowaną zgodność na aktualnym HEAD.
- **Ustalenie:** Wskazuje moduły powiadomień, finansów, panel admina i modalnego potwierdzenia jako docelowy zakres (636-900); każdą funkcję trzeba powiązać z bieżącym UI/API/serwisem, nie przepisywać całego briefu jako backlogu.
- **Zależności do sprawdzenia:** `src/HubAcquisitionService.php`, `src/HubApi.php`, `src/HubAssignmentService.php`.
- **Granica dowodu:** Brak testu runtime/DB, porównanie części źródeł z historycznym dokumentem

### `svn_repo/LOGISTICS_P1_IMPLEMENTATION.md`

**Blob:** `1aa4ae0cce31782e2cfb114364faae8b949a7f31` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 269 linii.

- **Ustalenie:** Historyczna notatka P1.1/P1.2 opisuje timery budowy pipeline i transport drogowy z rozdziałem MySQL/SQLite; istotne dla migracji parytetu logistyki i testów czasowych.
- **Ustalenie:** Tabela z wynikiem MySQL 95/95 i Unit 54/56 jest deklaracją historyczną (linie 242-248), nie wynikiem uruchomienia w tej sesji.
- **Ustalenie:** Dokument zaznacza niedokończone P1.3 i P2 (252-267); stan aktualnego kodu trzeba sprawdzić osobno.
- **Zależności do sprawdzenia:** `src/RoadTransportService.php`, `src/Tick/WellRoadTripSection.php`, `src/WellPipelineService.php`.
- **Granica dowodu:** Nie uruchomiono MySQL, status funkcji wg aktualnego HEAD niepotwierdzony

### `svn_repo/MODUL_LOGISTYKI_DLA_GRACZA.md`

**Blob:** `756d7b8fc11aec324fcb4a690626d2be98541afa` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 215 linii.

- **Ustalenie:** Opis przepływu ropa: odwiert → transport → hub → magazyn oraz listę funkcji klienta (45-200); to założenia funkcjonalne, nie dowód implementacji.
- **Ustalenie:** Dokument opisuje podsumowanie dnia w finansach i powiadomienia o transportach, wymagające porównania z kodem ticka i UI przed migracją.
- **Zależności do sprawdzenia:** `src/Tick/PlayersSection.php`, `templates/views/logistics/main.php`, `templates/views/public/finance/main.php`.
- **Granica dowodu:** Historyczny dokument, bez testu parytetu z HEAD

### `svn_repo/MODUL_MAP_MOBILE_STATUS.md`

**Blob:** `132654159a665935e99232058a75fcf4602412b5` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 305 linii.

- **Ustalenie:** Historyczny status MVP map i PR #54 (1-5,297-305) oraz deklaracja zielonych testów CI nie są wynikiem tego audytu; kontrakt API v1 należy sprawdzić na aktualnym HEAD.
- **Ustalenie:** Kod mobile/lib/services/api_service.dart:134-151 wywołuje getMapData/applyPermit przez _headers(token) bez locale, mimo obsługi opcjonalnego Accept-Language w _headers:45-49. Dokument (215-217,276-277) trafnie wskazuje brak locale w tych żądaniach; wymaga weryfikacji efektu serwera.
- **Ustalenie:** Kod mobile/lib/modules/maps/maps_screen.dart:83-88 łapie wszystkie wyjątki przy applyPermit i pokazuje maps.apply_error, gubiąc szczegółowy kod błędu; dokument (219-225,278-281) opisuje tę różnicę. Sukces przelicza review_minutes jako num (77), co odpowiada historycznej poprawce.
- **Ustalenie:** Dokument wymienia jako nieukończone kupno odwiertu z mapy, loading state submit i lifecycle-aware refresh (272-284); to historyczny zakres MVP, nie dowód obecnego braku we wszystkich ścieżkach.
- **Zależności do sprawdzenia:** `mobile/lib/services/api_service.dart`, `mobile/lib/modules/maps/maps_screen.dart`, `api/v1/maps/index.php`, `api/v1/permits/apply.php`.
- **Granica dowodu:** Nie uruchomiono aplikacji Flutter ani API/MySQL; tylko odczyt dokumentu i wskazanych fragmentów źródła

### `svn_repo/OSTATECZNA_WERSJA_HUB_RUROCIAG.md`

**Blob:** `c223e646f4ee7fdad0c604d969dc6550101cbc30` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 415 linii.

- **Ustalenie:** P1 rozbieżność migracyjna: dokument (3-6,20-26,379-387) nazywa huby wyłącznie systemowymi (player_id=0) i zakup/wynajem przez gracza niezaimplementowanym. Aktualny kod src/HubAcquisitionService.php:6-13,36-112 ma buyNew z INSERT player_id gracza; src/HubApi.php:286-296 udostępnia buy_new_hub, assets/js/logistics_hub_market.js:82-85 go wywołuje. Nie przenosić założeń dokumentu jako aktualnego modelu własności. Przebieg runtime/DB nieprzetestowany.
- **Ustalenie:** Dokument opisuje dwustopniowy transport i osobne statusy/kolumny, opłaty za przypisanie, cooldown 4h, budowę pipeline i finalizację w ticku (63-151,165-300). To historyczne kontrakty do porównania z obecnym schematem i testem izolowanej DB, nie certyfikat aktualnego działania.
- **Ustalenie:** Wyniki testów 65/65 SQLite i 109/111 MySQL (337-375) pochodzą z historycznej notatki; nie są wynikami obecnego HEAD. W opisie P2.3 hub_road_trips jest nie zaczęte (379-385), podczas gdy bieżący kod referuje tę tabelę; stan produktu zweryfikować po ścieżkach ticka.
- **Zależności do sprawdzenia:** `src/HubAcquisitionService.php`, `src/HubApi.php`, `assets/js/logistics_hub_market.js`, `src/HubAssignmentService.php`.
- **Granica dowodu:** Nie uruchomiono API, ticka ani testów MySQL, a wymienione źródła sprawdzono fragmentami w celu porównania wymagania

### `svn_repo/README_rurociag.md`

**Blob:** `5f6dc93e7d31fcba5d526895ad7754a8c24d937c` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 325 linii.

- **Ustalenie:** Historyczny opis kontraktu lądowego i offshore: wydobycie -> etap 1 transport -> hub -> etap 2 transport -> magazyn, z kosztami, stratami i ryzykiem per etap (31-58). Wymaganie zachowania bilansu wolumenu, nie dowód bieżącego ticka.
- **Ustalenie:** Wyłączenie pipeline według briefu nie ma usuwać kosztu: fallback na droższy transport drogowy (125-134). Sprawdzić realny backend przy statusach suspended/disabled i brak podwójnych kosztów.
- **Ustalenie:** Stan implementacji datowany 2026-05-28 (225-324) mówi, że zakup pipeline i wybór transportu drogowego nie były kompletne, lecz nowszy LOGISTICS_HUBS_README_V2.md opisuje już selekcję/własność i test. Ta lista TODO jest potencjalnie nieaktualna; nie przenosić jej jako obecnego backlogu bez kodu.
- **Ustalenie:** Opis osobnych zdarzeń well_pipeline_events, pól stanu/przepustowości i adminskich napraw/incydentów jest planem mapowania danych przy migracji, wymaga porównania schematu aktualnej bazy i testu ticka.
- **Zależności do sprawdzenia:** `src/WellPipelineService.php`, `public/logistics.php`, `svn_repo/LOGISTICS_HUBS_README_V2.md`.
- **Granica dowodu:** Nie wykonano live testu transportu, zakupu ani testu na izolowanej bazie

### `svn_repo/SYSTEM_SZKOLEN_DESIGN.md`

**Blob:** `715a1fb0d44b692dcd2a70a5d3ba54f7ddfe0e17` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 640 linii.

- **Ustalenie:** Dokument oznaczono jako PROJEKT z 2026-06-22, lecz obecnie istnieją src/Training/TrainingService.php i templates/views/technical/tabs/trainings.php. Ścieżki training.php/TrainingTickProcessor.php oraz szkic klas Programs nie istnieją w opisanych miejscach; nie kopiować planu jako bieżącej architektury.
- **Ustalenie:** Błąd matematyczny w szkicu (389-395): przy score=rand(1,100) i passMin=100-passChance oraz >= szansa zdania wynosi passChance+1 punkt procentowy; dla 5% daje 6 wyników (95..100). Bieżący TrainingService.php:196-204 ma prawidłowe 101-passChance. Nie regresować podczas migracji.
- **Ustalenie:** P1 warunkowe ryzyko spójności w bieżącym kodzie: TrainingService.php:220-255 zatwierdza wynik egzaminu i podniesienie skilla przed issueCertificate; błąd INSERT certyfikatu jest logowany po commicie, bez ponownego przetworzenia już zakończonego egzaminu. Przy awarii DB certyfikat może być trwale nieobecny. tests/test_training_engine.php:166-187 sprawdza typowy powtórny przebieg, ale nie wymusza awarii wystawienia certyfikatu. Wymagany test fault injection na izolowanej bazie i decyzja o transakcji/outbox/retry.
- **Ustalenie:** Plan proponuje tabelę training_certificates (139-167), ale przykład klasy (353-370) nadal INSERT do employee_certificates, co sam dokument oznacza jako niezgodne dla techników (141-147). Aktualny serwis zapisuje training_certificates:273-282. Nie używać przykładu SQL jako implementacji.
- **Zależności do sprawdzenia:** `src/Training/TrainingService.php`, `templates/views/technical/tabs/trainings.php`, `tests/test_training_engine.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL, testów współbieżności ani awarii certyfikatu; źródła serwisu sprawdzono fragmentami

### `svn_repo/UI_COMMUNICATION_SYSTEM_README.md`

**Blob:** `42e019fe7e3f9453a5505f941230968c6c7e9b82` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 328 linii.

- **Ustalenie:** Historyczny opis wspólnego systemu modal.js/modal.css oraz MODAL_LANG i data-confirm; checkboxy wdrożenia i pozostających wyjątków (183-253) są deklaracją dokumentacji, a nie testem wszystkich obecnych widoków.
- **Ustalenie:** Dokument preferuje confirmAction/promptInput i unikanie natywnych confirm/alert; stan rzeczywisty wymaga porównania z assetami i inline eventami. W innych kartach stwierdzono form.submit() po modalu, co omija walidację interaktywną HTML5.
- **Ustalenie:** Ścieżki C:\xampp1\htdocs to historyczne ścieżki autora, nie kontrakt deploymentu Laravel; zachować semantykę komponentu, nie kopiować ścieżek.
- **Zależności do sprawdzenia:** `assets/js/modal.js`, `assets/css/modal.css`, `templates/header.php`, `admin/partials/footer.php`.
- **Granica dowodu:** Brak testu przeglądarkowego i weryfikacji wszystkich checkboxów dokumentu

### `svn_repo/VISUAL_GUIDELINES.md`

**Blob:** `c50b76ba5626bbeb84009598fb8f194b4c5c98a1` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 457 linii.

- **Ustalenie:** Historyczny opis wzorca ciemnego UI, palety tokenów variables.css, Montserrat, modularnych CSS i SVG (7-188); potraktować jako wymaganie parytetu wizualnego, a nie zweryfikowany stan każdej strony.
- **Ustalenie:** Zasady globalnych modalów, tłumaczeń i unikania inline JS (253-274,423-442) są instrukcją projektową; stwierdzone w innych kartach istniejące inline onclick, onsubmit i CSS raw wymagają inwentaryzacji wyjątków zamiast deklaracji pełnej zgodności.
- **Ustalenie:** Dokument z 2026-07-06 wymienia PL+EN (11,423-442), podczas gdy bieżące repo ma także lang/de.php; zakres językowy opisany w nim jest nieaktualny dla web i trzeba uwzględnić DE w testach migracyjnych.
- **Ustalenie:** Parytet responsywności (355-378), ikon GameShell (317-333), routingu (.htaccess, src/init.php, public) (336-351) i oddzielnego admin.css (276-313) wymaga testów w przeglądarce i po migracji.
- **Zależności do sprawdzenia:** `assets/css/variables.css`, `assets/css/style.css`, `assets/js/modal.js`, `src/GameShell.php`, `lang/de.php`, `.htaccess`.
- **Granica dowodu:** Brak porównania wizualnego w przeglądarce i całościowego porównania tokenów CSS

### `svn_repo/b2b.md`

**Blob:** `96c77c32b8382cb2136fba375caa71dd840657ac` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 1677 linii.

- **Ustalenie:** svn_repo/b2b.md:27–57,192–348,1391–1450: historyczny kontrakt MVP: kupujący blokuje 100% wartości, sprzedawca dostarcza pełną ilość przy akceptacji, otrzymuje wypłatę, anulowanie przez kupującego oznacza domyślnie 10% kary/90% zwrotu, wygaśnięcie otwartej oferty zwraca 100%; cena 70–130% rynku, limit 5 ofert i 100–50000 bbl.
- **Ustalenie:** svn_repo/b2b.md:112–188 i 1657–1669: brief jest wewnętrznie niespójny: deklaruje order()=100, w tabeli kolejności 35/36/40, a w szkielecie 36; późniejszy status wdrożenia podaje 100. Obecnie B2BContractsModule.php:21–29 ma key=b2b_contracts/order=100, ContractsModule.php:27–30 order=90, BlackMarketModule.php:21–24 order=50; TickRegistry.php:120 sortuje rosnąco. Faktyczna kolejność: black_market, contracts, B2B, a nie opisana słowami po contracts/przed black_market. To konflikt historycznej specyfikacji z implementacją wymagający decyzji, nie automatyczna zmiana runtime.
- **Ustalenie:** svn_repo/b2b.md:1133–1281,1285–1387,1454–1522: projekt czterech tabel, polskich etykiet, odrębnej paginacji i typów finansowych; żąda FOR UPDATE dla oferty i magazynu oraz testu dwóch requestów z jednokrotnym pobraniem ropy/wypłatą. Współczesny B2BContractService.php:65–84 ma dodatkowo częściowe dostawy, pierwszy próg 25%, deadline i karę sprzedawcy; createBuyOffer:189–242 blokuje środki przez FinancialTransactionService w transakcji, acceptOffer:389–452 dopuszcza częściową pierwszą dostawę, acceptAndDeliver:361–372 zachowuje pełną ścieżkę kompatybilności. B2BContractsModule.php:36–52 poza wygaszaniem finalizuje zaakceptowane po terminie z limitem TickContext; to rozszerzenie ponad historyczne MVP, nie dowód regresji.
- **Ustalenie:** svn_repo/b2b.md:1441–1450,1488–1522 kontra tests/MySqlIntegration/MySqlB2BContractServiceTest.php:38–121,275–361 i tests/fixtures/b2b_concurrent_worker.php:23–43: dwa realne procesy na osobnych PDO próbują akceptacji tej samej oferty (30 bbl) i dostawy pozostałych 70 bbl, rodzic trzyma FOR UPDATE i zwalnia blokadę, testy asercyjnie sprawdzają jednego zwycięzcę, jedną wypłatę/dostawę na etap, salda i stan magazynu; metoda testConcurrentDeliverPartialOnlyOneSucceedsMysql:127–164 jest jedynie sekwencyjna. Worker bierze połączenie z config/database.php, więc izolację DB trzeba potwierdzić przed uruchomieniem; testów nie wykonano.
- **Ustalenie:** svn_repo/b2b.md:388–639,986–1129,1657–1677: historyczne zakładki kontraktów i panel admina, a dopisek wdrożeniowy oznacza odłożone aukcje, podkontrakty i rozbudowane klauzule; nie należy przenosić briefu jako bezwarunkowej definicji obecnego zachowania. Kontrola templates/views/market/main.php:31–42,188–208 potwierdza także B2B na rynku przy włączonym module, niezależnie od historycznego opisu UI.
- **Zależności do sprawdzenia:** `src/Tick/Modules/B2BContractsModule.php`, `src/Tick/Modules/ContractsModule.php`, `src/Tick/Modules/BlackMarketModule.php`, `src/Tick/TickRegistry.php`, `src/Tick/TickContext.php`, `src/B2BContractService.php`, `tests/MySqlIntegration/MySqlB2BContractServiceTest.php`, `tests/fixtures/b2b_concurrent_worker.php`, `templates/views/market/main.php`.
- **Granica dowodu:** Pełny statyczny odczyt 1677 linii; porównanie zależności punktowe, bez uruchamiania PHP, DB, CRON ani testów, bez weryfikacji stanu wdrożenia i kompletności wszystkich widoków/admina.
- **Granica dowodu:** Historyczny brief i jego dopisek statusu nie są bieżącym kontraktem runtime; decyzja o wymaganej kolejności ticków należy do produktu.

### `svn_repo/brief_rodo_cookies_oilcorp.md`

**Blob:** `ff818d18925a0c55945240e4dadfa15a44f4b8ce` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 701 linii.

- **Ustalenie:** Historyczny brief architektury prywatności wymaga globalnego banera również na stronie głównej (linie 562-571), lecz public/index.php:7-11 dla anonimowego użytkownika ładuje samodzielny templates/views/public/home/main.php i kończy żądanie; szablon nie wczytuje banner.php ani privacy_banner.js. Rozbieżność wymaga decyzji właściciela i testu, nie automatycznej opinii prawnej.
- **Ustalenie:** Wymaganie historii zgód oraz przejrzystej re-konsentyzacji nie jest spełnione samym cookie: JS zapisuje cookie przed odpowiedzią API i ignoruje błąd.
- **Ustalenie:** Brief explicite odkłada automatyczną retencję danych na późniejszy etap (104-110), a tekst polityki opisuje usuwanie nieaktywnych kont po 90 dniach jako możliwość; potwierdzić proces.
- **Zależności do sprawdzenia:** `assets/js/privacy_banner.js`, `public/index.php`, `templates/views/privacy/banner.php`, `templates/views/public/home/main.php`.
- **Granica dowodu:** Historyczny brief nie dowodzi aktualnych wymagań prawnych ani biznesowych

### `svn_repo/kontrakty-dlugoterminowe-brief-rozszerzony.md`

**Blob:** `bf28464e0926b055f3bbde00a6087a84acea3e69` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 1426 linii.

- **Ustalenie:** Rozszerzony brief opisuje 12 etapowych funkcji kontraktów: reputację, bonus, kaucję, region, renegocjację, anulowanie, dynamikę rynku, kontrakty specjalne, klauzule, P2P, aukcje i ubezpieczenie (35–61,1348–1381). To plan produktu, a nie lista tego, co jest wdrożone. W aktualnych źródłach są co najmniej ContractReputationService, obsługa kaucji/renegocjacji/cancel w ContractService oraz odpowiednie tabele w src/Contracts/ContractSchema.php; nie kopiować briefu w całości jako brakującego zakresu.
- **Ustalenie:** Wewnętrzna sprzeczność kolejności ticka: brief wymaga wykonania contracts PO players (22–30), lecz podaje order()=35; obecny PlayersModule::order()=40, a TickRegistry sortuje rosnąco, więc 35 wykonałoby kontrakty PRZED produkcją/dopisaniem magazynu. Bieżący ContractsModule::order()=90 jest po players i nie wolno go regresować do 35 w migracji.
- **Ustalenie:** Brief sugeruje kontrakty regionalne z walidacją posiadania licencji/odwiertu, lecz wprost dopuszcza brak dowodu pochodzenia konkretnej baryłki bez storage_batches (561–583). Wymagania o pochodzeniu ropy i księgowaniu bonusu/kaucji/escrow wymagają osobnej decyzji i testów atomowości, nie prostego przeniesienia przykładowych fragmentów SQL.
- **Ustalenie:** W src nie znaleziono dosłownych nazw contract_spawn_rules, contract_auctions, contract_clauses ani player_contract_offers; możliwe odrębne odpowiedniki (np. B2B) wymagają osobnego mapowania. Brak dokładnego tokenu nie dowodzi braku funkcji.
- **Zależności do sprawdzenia:** `src/Contracts/ContractSchema.php`, `src/ContractReputationService.php`, `src/ContractService.php`, `src/Tick/Modules/ContractsModule.php`, `src/Tick/Modules/PlayersModule.php`, `src/Tick/TickRegistry.php`.
- **Granica dowodu:** Pełny odczyt briefu; odczyt kodu powiązanego częściowy, bez SQL/tick runtime, wymagana decyzja produktowa

### `svn_repo/kontrakty-dlugoterminowe-brief.md`

**Blob:** `0c7eaa316aec8df1c5944a0c2cc896cf35fb048e` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 1953 linii.

- **Ustalenie:** Brief:49–88,594–646,1540–1583 projektuje ContractsModule key=contracts, order=35 i po players/przed black_market; dopisek 1588–1623 i 1780–1819 jednocześnie mówi order=90, nadal deklaruje przed black_market, a linia 1816 nawet order=45. W HEAD PlayersModule:26–28 ma 40, BlackMarketModule:16–24 ma 50, ContractsModule:22–35 ma 90; TickRegistry:120 sortuje rosnąco, TickRegistryTest:27–45 oczekuje 40→50→60→70→80→90→100. Wymaganie 'przed black_market' nie jest realizowane przez porządek 90; samo order=35 byłoby przed players. To sprzeczność biznesowa do decyzji, a nie dowód awarii ticka.
- **Ustalenie:** Brief:552–645,979–990,1790–1802 używa processDueContracts(DateTime $now,float $marketPrice) i mergeStats w module; HEAD ContractsModule:37–55 woła processDueContracts($ctx->newPrice) bez $ctx->now, a ContractService:370–388 bierze nowString z własnego zegara. Stats modułu:58–69 mają krótkie klucze processed/revenue itd.; mergeStats wykonuje TickEngine:162–168, a ContractsModuleTest:82–105 wywołuje mergeStats ręcznie po run, więc nie dowodzi, że sam moduł merguje statystyki. Kontrakt testowy jest dostosowany do order=90 (ContractsModuleTest:38–41).
- **Ustalenie:** Brief:941–970,1520–1534,1941–1953 wymaga podpisania bez pobierania ropy i pieniędzy; obecny ContractServiceTest:311–423 sprawdza depozyt przy podpisaniu, refundację i transakcję zewnętrzną. Odróżnić brak rezerwacji ropy od pieniężnego depozytu: reguła 'nie księgować pieniędzy przy podpisaniu' z historycznego etapu nie opisuje już całego HEAD.
- **Ustalenie:** Brief:974–1049,1740–1775 wymaga lock kontraktu i storage, transakcji, historię każdej dostawy i niezatrzymywanie pozostałych kontraktów; HEAD ContractService:370–425 pobiera max 200 due i łapie wyjątek per kontrakt, :444–515 stosuje FOR UPDATE poza SQLite i savepoint wewnątrz istniejącej transakcji, :563–665 zapisuje dostawę/log i finalizuje lub rollback. Następny termin liczy od nowStr (:595–606), nie od poprzedniego due_at, choć brief:1044 wymaga dodania interwału do next_delivery_at. Test ContractTickTest:292–315 asercją z zegarem testuje obecne zachowanie, nie planowaną formułę.
- **Ustalenie:** Brief:1035–1039,1711–1717 sugeruje pobranie całej kary przez debitCombined; HEAD ContractService:537–561 ogranicza pobór do dostępnego salda, :582–593 rejestruje penalty i penalty_collected oddzielnie; ContractTickTest:218–239 potwierdza postęp przy nieściągalnej karze. To różnica semantyczna: naliczona kara ≠ ściągnięta kwota.
- **Ustalenie:** Brief:1407–1434,1766–1775,1928–1937 to lista postulowanych testów, nie wynik CI bieżącego HEAD. W bieżącym repo istnieją SQLite ContractServiceTest/ContractTickTest/ContractsModuleTest i jednostkowy TickRegistryTest, ale wymienione w briefie MySqlContractServiceTest/MySqlContractConcurrencyTest nie są śledzone pod tymi ścieżkami; obecność testów nie potwierdza dwóch równoległych połączeń ani ich przejścia.
- **Zależności do sprawdzenia:** `src/Tick/Modules/ContractsModule.php`, `src/Tick/Modules/PlayersModule.php`, `src/Tick/Modules/BlackMarketModule.php`, `src/Tick/TickRegistry.php`, `src/Tick/TickEngine.php`, `src/ContractService.php`, `tests/Unit/TickRegistryTest.php`, `tests/Integration/ContractsModuleTest.php`, `tests/Integration/ContractTickTest.php`, `tests/Integration/ContractServiceTest.php`.
- **Granica dowodu:** Historyczny dokument zawiera wewnętrznie sprzeczne wartości order; nie traktować go jako aktualnego kontraktu bez decyzji właściciela produktu.
- **Granica dowodu:** Statyczna analiza HEAD 09b07a97195836698e600215bb19013559aabf9a; nie uruchamiano PHP, SQL, ticka, testów ani CI. Nie przypisano dawnych wyników CI obecnemu HEAD.
- **Granica dowodu:** Przegląd zależności ukierunkowany na twierdzenia briefu; nie wykonano pełnego audytu wszystkich plików serwisu, schematu, UI i testów MySQL.

### `templates/views/public/finance/main.php`

**Blob:** `4ab8681fcb6684607f757c2c833b735594c43918` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 905 linii.

- **Ustalenie:** [P1; linie public/finance.php:101-111] fmtPLN etykietuje te same liczby jako USD dla locale=en i PLN w innych; sprawdzić czy to realna konwersja waluty czy myląca etykieta (brak konwersji w tej funkcji).
- **Ustalenie:** [P2; linie templates/views/public/finance/main.php:401-446,693-818; public/finance.php:32-64] Dwa formularze POST z CSRF dla save_finance_settings/save_finance_policy; walidacja i cooldown w FinancePolicyService pozostają otwarte.
- **Ustalenie:** [P2; linie templates/views/public/finance/main.php:111-115,211-213] HTML ikon alertów jest wstawiany bez escaping; obecny FinanceService zwraca stałe encje, a historia w JS jest serializowana bez JSON_HEX_TAG; obecny getHistory pobiera liczby i timestamp, więc brak dowodu eksploatowalnego XSS. Przy zmianie źródła danych ponownie zweryfikować.
- **Ustalenie:** [P3; linie templates/views/public/finance/main.php:843-855] Czas względny ma gałąź EN i wspólną PL dla pozostałych locale, więc DE wyświetla polski tekst.
- **Zależności do sprawdzenia:** `assets/css/finance.css`, `assets/js/finance.js`, `lang/de/finance.php`, `lang/en/finance.php`, `lang/pl/finance.php`, `public/finance.php`, `src/FinancePolicyService.php`, `src/FinanceService.php`, `templates/components/game_shell.php`.
- **Granica dowodu:** Brak testu renderowania i zapisu na izolowanej DB
- **Granica dowodu:** Nieprzeczytane w całości FinancePolicyService i finance.js w tej karcie
- **Granica dowodu:** Brak weryfikacji currency contract i skutku ustawień w ticku

### `templates/views/public/home/main.php`

**Blob:** `80811fdcfe649647de5e758e77b05e26f8d0942e` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 667 linii.

- **Ustalenie:** Strona publiczna ma osobny layout i nawigację, CSRF w formularzu języka. public/language.php waliduje locale i dopuszcza lokalną ścieżkę przekierowania (odrzuca //).
- **Ustalenie:** 23 unikalne ścieżki asset(...) w szablonie istnieją na dysku; nie jest to test wyglądu, dostępności HTTP ani kompletności renderowania.
- **Ustalenie:** Podgląd gry wykorzystuje statyczne wartości i datę 2025-04-12, nie dane konta; przy migracji oznaczyć jako makietę, nie żywy stan.
- **Ustalenie:** W stopce linki do regulaminu i privacy-policy.php, treści mają osobne szablony PL/EN/DE; brak własnej inicjalizacji banera prywatności w tym pliku.
- **Ustalenie:** t() HTML-escapuje tekst i placeholdery, a stopka home.footer_energy dodatkowo escapuje wynik (możliwe podwójne encje).
- **Zależności do sprawdzenia:** `assets/css/public_home.css`, `assets/js/public_home.js`, `lang/de.php`, `lang/en.php`, `lang/pl.php`, `public/index.php`, `public/language.php`.
- **Granica dowodu:** Brak testu HTTP, przeglądarki i a11y; konfiguracja privacy banner wymaga osobnej analizy

### `templates/views/public/pages/cookies_de.php`

**Blob:** `3775a8e3d8f27adf2a188efed623baa77fb41b16` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 38 linii.

- **Ustalenie:** Sekcja 3 opisuje csrf_token jako cookie; src/CSRF.php:8-10 przechowuje go w sesji i generuje ukryte pola, bez osobnego setcookie. Wskazanie locale/lang wymaga korekty wobec public/language.php ustawiającego locale.
- **Ustalenie:** Dokument podaje czas privacy_consent 365 dni, zgodny z assets/js/privacy_banner.js:8-9,22-29,107; prawne stwierdzenia wymagają osobnej akceptacji eksperta.
- **Zależności do sprawdzenia:** `api/privacy/consent.php`, `assets/js/privacy_banner.js`, `public/language.php`, `src/CSRF.php`.
- **Granica dowodu:** Bez opinii prawnej, zachowanie produkcyjne niezweryfikowane

### `templates/views/public/pages/cookies_en.php`

**Blob:** `aac381790527aadfa17c6b747be4d3b5ade448c1` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 38 linii.

- **Ustalenie:** Jak cookies_de.php: csrf_token jest kluczem sesji i polem formularza, nie osobnym cookie według src/CSRF.php:8-10; locale/lang opisuje dwa aliasy, podczas gdy public/language.php ustawia locale.
- **Ustalenie:** Cookie privacy_consent zapisuje JS na 365 dni bez czekania na API; potrzebny test rozbieżności lokalnego i serwerowego stanu zgód.
- **Zależności do sprawdzenia:** `api/privacy/consent.php`, `assets/js/privacy_banner.js`, `public/language.php`, `src/CSRF.php`.
- **Granica dowodu:** Bez opinii prawnej, brak testu w przeglądarce

### `templates/views/public/pages/cookies_pl.php`

**Blob:** `1397651d985077fe9008a9dbd1197265bd2a7958` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 38 linii.

- **Ustalenie:** Jak cookies_de.php: lista cookies deklaruje csrf_token, chociaż implementacja src/CSRF.php używa $_SESSION; locale/lang nie odpowiada jednemu cookie locale ustawianemu przez public/language.php.
- **Ustalenie:** privacy_consent (365 dni) jest cookie klienta w assets/js/privacy_banner.js; zapis API nie blokuje UI, błąd jest przemilczany.
- **Zależności do sprawdzenia:** `api/privacy/consent.php`, `assets/js/privacy_banner.js`, `public/language.php`, `src/CSRF.php`.
- **Granica dowodu:** Bez opinii prawnej, brak testu prod

### `templates/views/public/pages/privacy_de.php`

**Blob:** `6d3a72308651de96d238d6b26a013c6b35719b9e` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 36 linii.

- **Ustalenie:** Dane operatora opisano jedynie jako 'Plattformbetreiber'; przed migracją treść trzeba zachować i przekazać do zatwierdzenia właścicielowi/prawnikowi.
- **Ustalenie:** Tekst wymienia okresy retencji i zakres kategorii; weryfikacja zgodności z faktycznymi zadaniami kasowania/logowania poza tą kartą.
- **Zależności do sprawdzenia:** `public/privacy-policy.php`, `src/Privacy/PrivacyPolicyService.php`.
- **Granica dowodu:** Brak opinii prawnej i weryfikacji operacyjnej retencji

### `templates/views/public/pages/privacy_pl.php`

**Blob:** `e856b439b806d8f3d029d0d82a9b078f205b578b` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 35 linii.

- **Ustalenie:** Administrator opisany ogólnie jako 'operator serwisu'; deklaracja możliwości usunięcia nieaktywnych kont po 90 dniach i czasu logów wymaga powiązania z zadaniami retencji.
- **Ustalenie:** Tekst nie może być uznany za prawnie zatwierdzony tylko na podstawie audytu kodu.
- **Zależności do sprawdzenia:** `public/privacy-policy.php`, `src/Privacy/PrivacyPolicyService.php`.
- **Granica dowodu:** Bez opinii prawnej i testu operacyjnej retencji

### `templates/views/public/pages/regulamin_de.php`

**Blob:** `3ff4caeffeadda1512ff1d3b82bce293b52887a8` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 62 linii.

- **Ustalenie:** Sekcja 3 zakazuje celowej sabotażu mechaniki gry szkodzącego innym graczom, natomiast templates/views/sabotage/main.php udostępnia graczom system sabotażu; zakres dozwolonego gameplayu wymaga doprecyzowania redakcyjnego.
- **Ustalenie:** Deklaracje usuwania kont, odwołań i sankcji wymagają potwierdzenia w systemie i przez właściciela.
- **Zależności do sprawdzenia:** `public/page.php`, `src/SabotageService.php`, `templates/views/sabotage/main.php`.
- **Granica dowodu:** Bez opinii prawnej i testu procedur

### `templates/views/public/pages/regulamin_pl.php`

**Blob:** `cc27fb24196a40e45d9e4567cadd4ec5db7361bd` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 62 linii.

- **Ustalenie:** Sekcja 3 zabrania celowego sabotowania mechanizmów gry, lecz templates/views/sabotage/main.php zawiera funkcję rozgrywki sabotującą innych graczy; rozgraniczyć legalną akcję w grze od exploitu.
- **Ustalenie:** Treść odwołania 7 dni / 14 dni roboczych i retencja 90 dni wymagają sprawdzenia procedur; samo odczytanie szablonu nie weryfikuje ich.
- **Zależności do sprawdzenia:** `public/page.php`, `src/SabotageService.php`, `templates/views/sabotage/main.php`.
- **Granica dowodu:** Bez opinii prawnej i testu procedur

### `templates/views/sabotage/main.php`

**Blob:** `bf234bd9fbce50f50eccf21617d9246d4c1dac8b` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 116 linii.

- **Ustalenie:** [P2; linie 103-107] Paginacja historii renderuje każdy numer strony; backend limituje hpage do 100000, ale nie limituje liczby generowanych linków; test na dużej historii przed migracją.
- **Ustalenie:** [P2; linie 81-87; public/sabotage.php:32-49] Akcja używa POST+CSRF+jednorazowego tokenu sesyjnego, którego kontroler zużywa przed wykonaniem; serwerowe walidacje kosztu, cooldownu i dostępu pozostają zależnością SabotageService.
- **Ustalenie:** [P3; linie 35,61-62,99] Nazwy/opisy celu i opcji są HTML-escapowane, dane JSON w atrybutach także; brak statycznego dowodu XSS w tych polach.
- **Zależności do sprawdzenia:** `assets/css/sabotage.css`, `assets/js/sabotage.js`, `lang/de/sabotage.php`, `lang/en/sabotage.php`, `lang/pl/sabotage.php`, `public/sabotage.php`, `src/SabotageService.php`.
- **Granica dowodu:** Brak izolowanej bazy i testu dwóch sesji, duża historia nietestowana
- **Granica dowodu:** Nie wykonano przeglądarkowego E2E

### `templates/views/technical/main.php`

**Blob:** `4d3b78cba4804da60dc0f0b0018edfcf6a8304ac` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 188 linii.

- **Ustalenie:** [P2; linie 7-27,131-159,165-186] Dane msg/msgType i teksty locale serializowane do inline script bez JSON_HEX_TAG; sprawdzić źródło komunikatów i czy ich zawartość może zawierać sekwencję końca skryptu; brak udowodnionego XSS.
- **Ustalenie:** [P2; linie 126-127; src/TechnicalPage/ViewDataTrait.php:48] Include zakładki opiera się na sanitized activeTab; kontroler ogranicza activeTab do allowlisty; ścieżka wejścia i dostęp gracza wymagają zachowania przy migracji.
- **Ustalenie:** [P2; linie 161-188] WELL_STAFF_API używa /api/internal/WellStaffApi.php; sprawdzić routing .htaccess i działanie z CSRF dla żądań AJAX.
- **Zależności do sprawdzenia:** `api/internal/WellStaffApi.php`, `assets/js/technical.js`, `assets/js/well_staff.js`, `public/technical.php`, `src/TechnicalPage/ViewDataTrait.php`, `templates/views/technical/tabs/candidates.php`, `templates/views/technical/tabs/incidents.php`, `templates/views/technical/tabs/infra.php`, `templates/views/technical/tabs/prod.php`, `templates/views/technical/tabs/report.php`, `templates/views/technical/tabs/safety.php`, `templates/views/technical/tabs/tasks.php`, `templates/views/technical/tabs/team.php`, `templates/views/technical/tabs/trainings.php`, `templates/views/technical/tabs/well_staff.php`, `templates/views/technical/tabs/wells.php`.
- **Granica dowodu:** Zakładki nie są objęte tą kartą
- **Granica dowodu:** Brak testu renderu i API na izolowanym środowisku

### `templates/views/technical/tabs/candidates.php`

**Blob:** `b6363fcb3f7f7f5ba4bd72ecaca60b506460fe39` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 227 linii.

- **Ustalenie:** P1: recruitment_type jest hidden=local i ustawiany na international tylko w onclick przycisku. Submit Enter lub bezpośredni POST region_code=US_CA,recruitment_type=local omija klasyfikację; ActionsTrait:159-183 przekazuje oba pola, TTS/RecruitmentTrait.php:329-339 waliduje je osobno i :378-389 losuje krótszy czas 360-720 s zamiast 1440-2880 s. Wymusić spójność region/type na serwerze, dopiero potem testy.
- **Ustalenie:** Komunikaty tłumaczeń HTML-escapowane wewnątrz onclick nie są escapowane dla kontekstu JS po dekodowaniu encji; bezpieczeństwo zależy od zaufania do tłumaczeń i sanitacji danych.
- **Zależności do sprawdzenia:** `src/TTS/RecruitmentTrait.php`, `src/TechnicalPage/ActionsTrait.php`, `src/TechnicalPage/ViewDataTrait.php`.
- **Granica dowodu:** Brak testu runtime na izolowanej bazie

### `templates/views/technical/tabs/incidents.php`

**Blob:** `f94ac9bc422cd068309fff68366507ab297c6ae0` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 142 linii.

- **Ustalenie:** Wiadomości i nazwy escapowane, a lista incydentów odwiertów paginowana; hubIncidents wyświetlane częściowo i odsyłają do logistyki. Sprawdzić zakres filtrów po player_id i historię z serwisu.
- **Ustalenie:** Daty formatuje date/strtotime bez explicit timezone; migracja wymaga ustalenia strefy.
- **Zależności do sprawdzenia:** `src/IncidentService.php`, `src/TechnicalPage/ViewDataTrait.php`, `templates/components/incident_icons.php`.
- **Granica dowodu:** Ścieżkę serwisu i E2E filtrów zweryfikować

### `templates/views/technical/tabs/infra.php`

**Blob:** `57141568ff4e572a2c695bc474675b0a2ce24464` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 106 linii.

- **Ustalenie:** P1: etykieta kosztu zależy od locale: USD (en), EUR (de), PLN (pl), bez przeliczenia wartości opex; obok wwMoney zawsze etykietuje PLN. Potwierdzić walutę bazową i naprawić niespójność przed migracją.
- **Ustalenie:** Dla każdego pipeline filtruje całą listę activeTasks, potencjalnie O(pipelines*tasks); status/nazwy escapowane lub zaufane tłumaczenia.
- **Zależności do sprawdzenia:** `src/TechnicalPage/ViewDataTrait.php`, `src/WellPipelineService.php`, `templates/views/wells/helpers.php`.
- **Granica dowodu:** Brak testu waluty z DB i profilu wydajności

### `templates/views/technical/tabs/prod.php`

**Blob:** `9920a377028b8e11d78f698b3f785cc2b74a272f` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 50 linii.

- **Ustalenie:** Wiersze nazw i statusów odwiertów escapowane; wydajność renderowania rośnie wraz z liczbą odwiertów; obliczenia i widok zależą od ViewDataTrait.
- **Ustalenie:** number_format w widoku używa niezależnych separatorów od locale.
- **Zależności do sprawdzenia:** `src/TechnicalPage/ViewDataTrait.php`, `templates/views/technical/main.php`.
- **Granica dowodu:** Brak testu dużego portfela odwiertów

### `templates/views/technical/tabs/report.php`

**Blob:** `328c617d53d11be63aaf60bcfc60c17dcf750f44` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 50 linii.

- **Ustalenie:** Nazwy odwiertów, zadania i pracownicy escapowane; raport pokazuje najwyżej 5 wpisów każdej listy, filtrowanie anomalii w pamięci nad pełną listą.
- **Ustalenie:** Przy migracji zachować regułę status<60 i przekierowania do kart wells/tasks.
- **Zależności do sprawdzenia:** `src/TechnicalPage/ViewDataTrait.php`, `templates/views/technical/main.php`.
- **Granica dowodu:** Brak testu skali i aktualności danych

### `templates/views/technical/tabs/safety.php`

**Blob:** `af09385f6dc44f58227483585434d02fb84aefd6` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 421 linii.

- **Ustalenie:** P1: etykieta kosztów katastrof, napraw i grzywien jest wybierana wg locale (USD/EUR/PLN) bez przeliczenia, podobnie jak infra.php; sprawdzić rzeczywistą jednostkę kwot.
- **Ustalenie:** Widok grupuje awarie, liczy aktywne zadania na katastrofę poprzez iterację po wszystkich activeTasks i renderuje wszystkie wpisy historyczne z $failures; możliwy problem skalowania.
- **Ustalenie:** Formularze upgrade/repair mają CSRF, ale reguły poziomu, obsady i potrącenia gotówki muszą być potwierdzone po stronie serwera.
- **Ustalenie:** W jednym widoku wyliczane są mnożniki redukcji oraz koszt naprawy 500000*level; migracja powinna przenieść regułę do wspólnego modelu domenowego.
- **Zależności do sprawdzenia:** `src/TechnicalPage/ActionsTrait.php`, `src/TechnicalPage/ViewDataTrait.php`, `src/TechnicalTeamService.php`.
- **Granica dowodu:** Brak izolowanego testu ekonomii i wydajności

### `templates/views/technical/tabs/tasks.php`

**Blob:** `0a10ffdf5d84ec3fdcf483a53f46d3f43a6abcb6` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 142 linii.

- **Ustalenie:** Formularze cancel_task i cancel_queue_item zawierają token CSRF i numeryczne ID; autoryzację właściciela zadania/kolejki musi potwierdzić serwis.
- **Ustalenie:** Historia zadań ograniczona w HTML do 20 pozostałych i 10 anulowanych; zdarzenia daty przeliczane w widoku.
- **Ustalenie:** Tekst potwierdzenia w inline onsubmit wymaga bezpiecznego kodowania dla kontekstu JS, samo HTML escaping nie wystarcza.
- **Zależności do sprawdzenia:** `src/TechnicalPage/ActionsTrait.php`, `src/TechnicalPage/ViewDataTrait.php`, `src/TechnicalTeamService.php`.
- **Granica dowodu:** Bez próby IDOR i E2E

### `templates/views/technical/tabs/team.php`

**Blob:** `a3aef85a31293347d0519e9903a25e84b43e0088` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 283 linii.

- **Ustalenie:** P2: dla każdego zajętego pracownika render wykonuje SELECT technical_tasks WHERE id=? AND player_id=? (N+1 zapytań; linie 110-124). Przed migracją załadować daty w jednym zapytaniu albo z view data.
- **Ustalenie:** P2: pełne listy wells, hubs, pipelines w select dla każdego dostępnego pracownika powodują znaczny rozmiar HTML przy dużej obsadzie.
- **Ustalenie:** Formularz fire_engineer pokazuje się niezależnie od manager, lecz StaffTrait::fireEngineer sprawdza player_id i status zajęcia w transakcji; nie potwierdzono wymogu manager dla zwalniania.
- **Ustalenie:** Inline onsubmit confirm dla zwolnienia scala nazwę i tłumaczenie; osobno testować escaping HTML i kontekst JS.
- **Zależności do sprawdzenia:** `src/TTS/StaffTrait.php`, `src/TechnicalPage/ActionsTrait.php`, `src/TechnicalPage/ViewDataTrait.php`.
- **Granica dowodu:** Brak profilu DB i testu dostępu bez manager

### `templates/views/technical/tabs/trainings.php`

**Blob:** `7928123b86335cf7df8aec0d91ca852d788259f2` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 178 linii.

- **Ustalenie:** Form start_training posiada CSRF, program_id i staff_id; serwer musi wymusić przynależność pracownika i program/warunki, UI tego nie dowodzi.
- **Ustalenie:** Dla każdego programu renderowana jest pełna lista personelu: koszt HTML O(programs*staff). Nazwy programów są escapowane, język DE ma odrębną ścieżkę tłumaczenia.
- **Zależności do sprawdzenia:** `src/TechnicalPage/ActionsTrait.php`, `src/TechnicalPage/ViewDataTrait.php`, `src/Training/TrainingService.php`.
- **Granica dowodu:** Ścieżkę serwisu zweryfikować, brak testu wydajności

### `templates/views/technical/tabs/well_staff.php`

**Blob:** `ec6d92ecbd57eb6c826b82d81219f92f0c990f5e` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 108 linii.

- **Ustalenie:** P2: well_name trafia do łańcucha JS w atrybucie onclick po htmlspecialchars(ENT_QUOTES). Parser HTML dekoduje encje przed kompilacją handlera JS; to nie jest zabezpieczenie kontekstu JS. Jeśli nazwa może zawierać apostrof, możliwy XSS; obecnie WellNamingMigrationService automatycznie przypisuje nazwy, brak dowodu na sterowanie przez gracza. Zastąpić inline onclick delegacją i data attributes.
- **Ustalenie:** Wiele kart personelu renderowane z pełnego wellsStaffStatus bez paginacji.
- **Zależności do sprawdzenia:** `api/internal/WellStaffApi.php`, `assets/js/well_staff.js`, `src/WellNamingMigrationService.php`.
- **Granica dowodu:** Nie udowodniono wektora wstrzyknięcia do well_name ani nie wykonano E2E

### `templates/views/technical/tabs/wells.php`

**Blob:** `4177ff1d001ce0cb64d1190dfcda6e85a550226a` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 237 linii.

- **Ustalenie:** Dane zadań grupowane wg well_id w pamięci, nazwy odwiertów i zadań escapowane; ekran renderuje wszystkie odwierty bez paginacji.
- **Ustalenie:** P2: awarie, transport i produkcję opisano pochodnymi obliczanymi w szablonie (WellService::getEffectivePressure), należy zachować zgodność z modelem domeny po migracji.
- **Ustalenie:** Tekst no_wells przekazuje URL jako parametr t(); sprawdzić escapowanie interpolacji względem źródła URL.
- **Zależności do sprawdzenia:** `src/TechnicalPage/ViewDataTrait.php`, `src/WellService.php`, `templates/views/technical/main.php`.
- **Granica dowodu:** Brak E2E przy wszystkich statusach odwiertu

### `templates/views/wells/geology.php`

**Blob:** `5942cc6d8d6ddda6f29d2f6598757b939014e5e9` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 13 linii.

- **Ustalenie:** Nazwy warstw escapowane; akcja switch korzysta z wwAction i /public/layer_well.php, wymaga testu serwerowego owner/cost/cooldown/CSRF.
- **Ustalenie:** Przy wielu warstwach renderuje wszystkie opcje bez paginacji.
- **Zależności do sprawdzenia:** `public/layer_well.php`, `src/WellWorkspaceData.php`, `templates/views/wells/helpers.php`.
- **Granica dowodu:** Brak testu bazy i współbieżności

### `templates/views/wells/helpers.php`

**Blob:** `83536996e8c0d50ba443bbbb030b811703539abb` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 28 linii.

- **Ustalenie:** wwEscape używa ENT_QUOTES UTF-8; wwAction escapuje data-payload i data-summary, ale etykiety i powód wyłączenia wypisuje raw (obecnie przekazywane tłumaczenia).
- **Ustalenie:** wwIcon bezpośrednio osadza SVG z repo, nazwa ograniczona do [a-z-]; zmiany ikon wymagają kontroli zaufania i wynikowego HTML.
- **Ustalenie:** wwMoney etykietuje PLN niezależnie od locale, sprzecznie z etykietami w technical/tabs/infra.php.
- **Zależności do sprawdzenia:** `assets/img/icons/wells/automation.svg`, `assets/img/icons/wells/black-market.svg`, `assets/img/icons/wells/condition.svg`, `assets/img/icons/wells/flow.svg`, `assets/img/icons/wells/geology.svg`, `assets/img/icons/wells/history.svg`, `assets/img/icons/wells/hub.svg`, `assets/img/icons/wells/kondycja.svg`, `assets/img/icons/wells/odwiert-mini.svg`, `assets/img/icons/wells/odwierty.svg`, `assets/img/icons/wells/overview.svg`, `assets/img/icons/wells/pipeline.svg`, `assets/img/icons/wells/premium.svg`, `assets/img/icons/wells/produkcja.svg`, `assets/img/icons/wells/przychod.svg`, `assets/img/icons/wells/repair.svg`, `assets/img/icons/wells/resources.svg`, `assets/img/icons/wells/sell.svg`, `assets/img/icons/wells/standard.svg`, `assets/img/icons/wells/status-dobry.svg`, `assets/img/icons/wells/status-krytyczny.svg`, `assets/img/icons/wells/status-uwaga.svg`, `assets/img/icons/wells/storage.svg`, `assets/img/icons/wells/tanker.svg`, `assets/img/icons/wells/truck.svg`, `assets/img/icons/wells/upgrade.svg`, `assets/img/icons/wells/wydajnosc.svg`, `assets/js/well-workspace.js`, `src/i18n.php`.
- **Granica dowodu:** Bez testu renderu dla wszystkich locale

### `templates/views/wells/history.php`

**Blob:** `cdf8d4e85f75d8b5aed9cf827cfb28165148ffe1` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 13 linii.

- **Ustalenie:** Historia ładowana asynchronicznie do data-well-history; trzeba zweryfikować endpoint, paginację i sposób escapowania JS, nie da się ocenić samym PHP.
- **Zależności do sprawdzenia:** `assets/js/well-workspace.js`, `public/well_history.php`.
- **Granica dowodu:** Ścieżka endpointu do potwierdzenia, brak E2E

### `templates/views/wells/main.php`

**Blob:** `1e6f1da8083f33c76a5598bd9aa997928189444f` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 58 linii.

- **Ustalenie:** [P2; linie 25-47] Każda zakładka jest renderowana dla każdego odwiertu; przy dużej liczbie odwiertów rozmiar HTML rośnie z liczbą odwiertów i 6 sekcji; wymagany test wydajności.
- **Ustalenie:** [P2; linie 49-52] Przycisk sprzedaży wyłącza UI dla nieaktywnego lub przypisanego do hubu odwiertu; walidacja serwerowa w endpoint sprzedaży i efekt finansowy niepotwierdzone tutaj.
- **Ustalenie:** [P3; linie 7-10,27-31] Token CSRF w atrybucie root, nazwy odwiertów escapowane wwEscape; ścieżki deep link repair_well/task_type wymagają testu.
- **Zależności do sprawdzenia:** `assets/js/well-workspace.js`, `public/index.php`, `src/WellWorkspaceData.php`, `templates/views/wells/equipment.php`, `templates/views/wells/geology.php`, `templates/views/wells/helpers.php`, `templates/views/wells/history.php`, `templates/views/wells/overview.php`, `templates/views/wells/storage.php`, `templates/views/wells/transport.php`.
- **Granica dowodu:** Brak izolowanego E2E sprzedaży i widoków 1440/390/320px
- **Granica dowodu:** Część dependency path wymaga weryfikacji

### `templates/views/wells/overview.php`

**Blob:** `d6c3c806c14ea869b30f8c3bd53c00b42d4a3b45` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 44 linii.

- **Ustalenie:** Wartości nazw warstw/hubów przechodzą przez wwEscape, ale część statusów i wartości pochodnych jest emitowana raw; kontrakt serwisów musi zapewnić allowlistę.
- **Ustalenie:** Linki do naprawy/deep-link w technical zależą od uprawnień serwerowych; sam widok ich nie dowodzi.
- **Zależności do sprawdzenia:** `public/technical.php`, `src/WellWorkspaceData.php`, `templates/views/wells/helpers.php`.
- **Granica dowodu:** Brak E2E dla statusów i zadań

### `tests/Integration/ContractTickTest.php`

**Blob:** `de474b9c2af7970ed338e1c030d4ce7d9c50d124` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 443 linii.

- **Ustalenie:** Linie 19–26, 386–442: każdy przypadek tworzy osobną bazę SQLite :memory:, minimalne tabele players/storage/well_config/board*/bank_transactions; ContractService w konstruktorze wywołuje ContractSchema::ensure (src/ContractService.php:55–61), więc tabele kontraktów nie są tworzone ręcznie w teście.
- **Ustalenie:** Linie 30–55: wyłączony moduł zwraca processed=0, revenue=0, penalties=0; przyszły termin daje tylko processed=0 (nie sprawdza pozostałych statystyk ani stanu kontraktu).
- **Ustalenie:** Linie 59–133: dla pełnej dostawy wymaga processed=1, revenue=5000, penalties=0, storage=50 i bank=5000; dla częściowej revenue=2000, penalties=300, storage=0, bank=1700, cash=1000; przy zerowym zapasie penalty=500, bank spada z 200 do 0 i cash z 1000 do 700. Nazwa testu nie dowodzi realnego przelewu poza stanem lokalnej bazy.
- **Ustalenie:** Linie 138–164, 168–215: kontroluje jeden wpis contract_deliveries (status, baryłki, cenę, przychód, due_at) oraz przejście statusu kontraktu na completed/failed; nie porównuje kompletu wpisów finansowych, sumy delivered_bbl/missed_bbl ani reputacji/depozytu.
- **Ustalenie:** Linie 218–240: brak środków na karę 500 nadal daje processed=1, failed=1, penalties=500, wpis dostawy i zero bank_transactions z referencją kontraktu; penalties jest kwotą naliczoną, nie pobraną (src/ContractService.php:537–561, 563–590, 660–661).
- **Ustalenie:** Linie 242–265: otwiera transakcję na tej samej konekcji, sprawdza że pozostaje otwarta i rollback przywraca status active oraz usuwa dostawę; nie jest to test dwóch połączeń ani izolacji konkurencyjnej.
- **Ustalenie:** Linie 269–315: sprawdza obecność contract_delivery w logach i następny termin jako 120 minut od time() z tolerancją 5 s; 2025-06-30 w teście terminu jest już przeszłą datą, a implementacja oznacza kontrakt failed, lecz test nie sprawdza statusu (src/ContractService.php:595–607).
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `tests/Unit/BaseTestCase.php`, `src/ContractService.php`, `src/Contracts/ContractSchema.php`, `src/FinancialTransactionService.php`, `src/WalletConfig.php`, `src/init.php`.
- **Granica dowodu:** Nie uruchomiono testów ani bazy; wynik wykonania nieznany.
- **Granica dowodu:** SQLite in-memory na jednej konekcji nie potwierdza MySQL FOR UPDATE, wyścigów ani produkcyjnej atomowości.
- **Granica dowodu:** Brak testu błędu credit/INSERT po odjęciu storage, retry/idempotencji i limitu 200; zależności reputacji, depozytu i ścieżek tick pozostają otwarte.

### `tests/Integration/ContractsModuleTest.php`

**Blob:** `11be7b55be649c3f310c4d9d293fc417d12b1086` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 245 linii.

- **Ustalenie:** SQLite sprawdza key=contracts, order=90, zerowe statystyki, jedną wymagalną dostawę 50 bbl po 100 i mergeStats do TickContext; wyłączony moduł raportuje zero (linie 33–133).
- **Ustalenie:** Nie testuje rzeczywistego sortowania TickRegistry ani produkcji przed kontraktem, kar, współbieżności, idempotencji ticku i MySQL. Fixture tworzy uproszczony schemat.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `src/Tick/Modules/ContractsModule.php`, `src/Tick/TickContext.php`, `src/ContractService.php`.
- **Granica dowodu:** Pełny odczyt pliku; PHP/SQLite/MySQL/CRON nie uruchamiano; zależności i zachowanie runtime pozostają otwarte

### `tests/Integration/EmployeeAssignmentServiceTest.php`

**Blob:** `46c6c0166858afaba3761892b74e29a942a8d69c` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 558 linii.

- **Ustalenie:** tests/Integration/EmployeeAssignmentServiceTest.php:20–27,444–547: setUp tworzy osobną SQLite :memory:, ograniczone tabele board_roles/hr_specializations/board_members/technical_staff/logistics_hubs/well_pipelines/well_config, syntetyczne aktywne i fired osoby, busy specjalistę pipeline, własne/obce/wynajęte huby i własne/obce/building rurociągi; usługi uruchamiają bootstrap stanu/przydziałów. tests/Integration/SqliteIntegrationTestCase.php:8–25 definiuje połączenie oraz NOW/LEAST/GREATEST, tests/Unit/BaseTestCase.php:8–23 resetuje sesję.
- **Ustalenie:** tests/Integration/EmployeeAssignmentServiceTest.php:29–107,109–166: asercje success, allocation_pct i wierszy listForHub/listForPipeline; błędy obcego hubu/rurociągu, building i niedozwolonej specjalizacji; listForPipelines grupuje dwa rurociągi, obcy gracz nie zwalnia przypisania. Statusy active/degraded/critical/leak są akceptowane, building/disabled/suspended/servicing/damaged odrzucane; suma 70% hub + 40% pipeline przekracza limit 100%.
- **Ustalenie:** tests/Integration/EmployeeAssignmentServiceTest.php:168–221: dwa przypisania pipeline dają coverage 50% engineer/logistics, degradation multiplier 1.5, ujemny pipeline_loss_pct; inny pipeline ma zera; status suspended zeruje efekty, zachowuje 2 przypisania; ustawienie relation_status=on_strike zeruje engineer coverage i daje multiplier 2.0.
- **Ustalenie:** tests/Integration/EmployeeAssignmentServiceTest.php:223–305: management odrzuca release typu hub i złą specjalizację, busy technical może obsłużyć pipeline lecz nie hub; ponowne przypisanie raportuje was_update i klient otrzymuje 2 kandydatów bez per-pipeline candidates oraz 50% aktualnej alokacji. roleCode board_member z pipeline_engineer daje pusty kod; fired po przydziale nadal figuruje jako active_assignment i zablokowany kandydat z current_assignment_id — sam test nie wywołuje release po fired.
- **Ustalenie:** tests/Integration/EmployeeAssignmentServiceTest.php:307–382: najemca może przypisać pracownika do wynajętego huba; obcy gracz nie zwalnia przypisania, własne release usuwa je z listy; fired board member, alokacja 70%+40% i on_strike blokują nowe przypisania z oczekiwanymi wyjątkami.
- **Ustalenie:** tests/Integration/EmployeeAssignmentServiceTest.php:384–442: hub bez personelu ma 0% coverage, throughput<1, incident risk>1 i missing hub_operator; 50% operator daje 25% coverage/bonus tylko na hubie 100, drugi operator 100% podnosi coverage do 75% i poprawia mnożniki; konfiguracja required_medium=4 daje required_count=4 i 25% coverage, flaga '1.00' włącza runtime.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `tests/Unit/BaseTestCase.php`, `src/Employee/EmployeeRef.php`, `src/Employee/EmployeeRepository.php`, `src/Employee/EmployeeStateService.php`, `src/Employee/EmployeeAssignmentService.php`, `src/Employee/LogisticsStaffingService.php`, `src/Employee/EmployeeRoleEffectService.php`, `src/Employee/PipelineStaffingService.php`, `src/Employee/PipelineStaffingManagementService.php`, `src/EmployeeSystemBootstrap.php`.
- **Granica dowodu:** Bez uruchomienia PHP/DB i bez produkcyjnego schematu MySQL, HTTP/DOM, współbieżnych połączeń ani fault-injection; asercje opisują tylko syntetyczny fixture SQLite.
- **Granica dowodu:** Zachowanie zablokowanego fired przypisania sprawdza widok, nie późniejsze skuteczne zwolnienie; test nazwy nie stanowi dowodu tej operacji (tests/Integration/EmployeeAssignmentServiceTest.php:282–305).

### `tests/Integration/EmployeeNegotiationServiceTest.php`

**Blob:** `a934629b03109b79f6891e49698b34d7584bbacf` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 965 linii.

- **Ustalenie:** Linie 18–24, 872–923: osobna SQLite :memory: z syntetycznymi tabelami players, HR, kontraktów i zadań; testy pośrednio uruchamiają bootstrap schematu, nie produkcyjny MySQL/CRON.
- **Ustalenie:** Linie 26–85: sprawdza część wymaganych tabel wersji 3/8, domyślne 13 konfiguracji i odrzucenie max_postponements=11. Uwaga: catch (Throwable) przy częściowym schemacie łapie też PHPUnit fail() z linii 43, więc przy pozostawieniu wersji 3 może przepuścić brak wyjątku ensure().
- **Ustalenie:** Linie 87–143, 548–583, 836–870: proces eskalacji tworzy wniosek 15000→16500, nie duplikuje postponed, wygasza go w terminie, nie tworzy dla fired; morale cycle raportuje wygaśnięcie i współdzielony budżet pracy. Testy sprawdzają stany lokalnej bazy, nie scheduler.
- **Ustalenie:** Linie 144–198: seed minimum 80 dwujęzycznych dialogów i brak pustych wartości/znaku ?, blokada nieznanego placeholdera, brak powtórki użytego szablonu, typy finansowe HR i routing do cash; brak niezależnej kontroli jakości tłumaczeń.
- **Ustalenie:** Linie 200–346, 348–415, 659–697: submitOffer na strajku sprawdza settlement, salary 19500, cash 190000, event/round i idempotencję; odrzuca zmieniony payload/round, zerową ofertę i stale round; testuje efekty słabej oferty przez ReflectionMethod, expiry/cooldown i ponowne otwarcie. Brak wymuszonej awarii w połowie settlementu, brak drugiego połączenia i kontrolowanego losowania wszystkich progów.
- **Ustalenie:** Linie 417–491, 493–545, 585–657, 698–834: odejście po 3 cyklach i notice period zamyka oba źródła/lustrzane relacje, assignment/task/strike; testuje postęp batch morale, efekty aktywnych strajków, izolację zespołu HR, dashboard player scope, bonus i brak bonusu obcemu/fired/striking, zamykanie strajku przy dezaktywacji oraz invalidację cache.
- **Ustalenie:** Nie należy mylić EmployeeNegotiationServiceTest (negocjacje strajkowe submitOffer) z formularzem podwyżki assets/js/hr_raises.js, który trafia do EmployeeRaiseRequestService przez src/HRApi.php:408–431; assets/js/hr.js:431–450 grant_bonus odpowiada natomiast ścieżce src/HRApi.php:450–467 i testowi bonusu w liniach 698–767.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `tests/Unit/BaseTestCase.php`, `src/EmployeeSystemBootstrap.php`, `src/Employee/EmployeeSystemConfigService.php`, `src/HR/EmployeeDialogueTemplateService.php`, `src/HR/EmployeeNegotiationService.php`, `src/HR/EmployeeBonusService.php`, `src/HR/EmployeeRelationLifecycleService.php`, `src/HR/EmployeeStrikeService.php`, `src/Tick/EmployeeMoraleSection.php`, `src/HRApi.php`, `assets/js/hr.js`.
- **Granica dowodu:** Bez wykonania PHP, bazy i CRON; wybrane zależności serwisowe tylko śledzone punktowo, nie pełny audyt runtime.

### `tests/Integration/EmployeeRaiseRequestServiceTest.php`

**Blob:** `56d8413c5350d6aee5e71ae428dfc18658a65e1f` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 460 linii.

- **Ustalenie:** Linie 11–18, 392–459: setUp tworzy osobną SQLite :memory:, syntetyczne board_members/technical_staff/employee_contracts i EmployeeSystemBootstrap; klasa bazowa resetuje $_SESSION. Fixtures nie odwzorowują pełnego produkcyjnego schematu MySQL ani wielopołączeniowej konkurencji.
- **Ustalenie:** Linie 20–60, 85–106, 252–295: asercje obejmują player scope i salary live w liście, pełną akceptację/idempotencję, morale/satisfaction/leave risk/loyalty/event, synchronizację technicznego lustra z kontraktem zarządu, nieobniżanie płacy po zmianie oraz filtrowanie aktywnych wniosków i limit.
- **Ustalenie:** Linie 62–83, 127–151: negocjacja z kontrolowanym roll 1/100 sprawdza płacę, kontrakt, wpis negotiated_salary, publiczny wynik bez roll/formula oraz metadane eventu; odrzucona oferta zostawia request open, obniża morale raz i powtórny token jest idempotentny; zmiana kwoty przy tym tokenie jest odrzucana.
- **Ustalenie:** Linie 108–125, 153–250, 269–281: odmowa rozstrzygnięcia przy leaving/on_leave/fired, izolacja obcego gracza przy reject, dispute po odmowie, limit dwóch odroczeń, status expired i event po terminie, walidacja tokenu oraz odczyt historycznego wyniku. Nie ma symulacji współbieżnych połączeń ani niezależnej kontroli HTTP/DOM.
- **Ustalenie:** Linie 297–323: trigger SQLite wymusza błąd drugiej aktualizacji konfiguracji; test sprawdza rollback wartości w bazie i pamięci, ale nie sprawdza transakcji decyzji o podwyżce przy błędzie po zapisie płacy.
- **Ustalenie:** Powiązanie potwierdzone: assets/js/hr_raises.js:57–60,99–103 wysyła akcje i request_id/token/offered_salary; src/HRApi.php:408–448 kieruje je do EmployeeRaiseRequestService; test wywołuje ten serwis bezpośrednio, nie testuje API.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `tests/Unit/BaseTestCase.php`, `src/EmployeeSystemBootstrap.php`, `src/HR/EmployeeRaiseRequestService.php`, `src/HR/EmployeeRaiseRequest/DecisionTrait.php`, `src/HR/EmployeeRaiseRequest/NegotiationTrait.php`, `src/HR/EmployeeRaiseRequest/PersistenceTrait.php`, `src/HRApi.php`, `assets/js/hr_raises.js`.
- **Granica dowodu:** Nie uruchomiono PHP/DB; asercje wywnioskowane z pełnego źródła, delegaty serwisu nie są tutaj w całości zweryfikowane.

### `tests/Integration/EmployeeRepositoryTest.php`

**Blob:** `355d52dd6e732addb442cc6cc8bd718a06f16ea0` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 182 linii.

- **Ustalenie:** SQLite bada normalizację board_members i technical_staff, filtrowanie aktywnych/departamentów, ownership oraz canonicalRef po potwierdzonym mirror manager_id (24–99). Nie scala odmiennych osób bez linku.
- **Ustalenie:** SetUp tworzy uproszczony schemat i wywołuje EmployeeSystemBootstrap::ensure; brak rzeczywistego MySQL, równoległego backfillu oraz testu niejednoznacznych/obcych linków.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `src/Employee/EmployeeRepository.php`, `src/Employee/EmployeeRef.php`, `src/EmployeeSystemBootstrap.php`.
- **Granica dowodu:** Pełny odczyt pliku; PHP/SQLite/MySQL/CRON nie uruchamiano; zależności i zachowanie runtime pozostają otwarte

### `tests/Integration/EmployeeRoleEffectServiceTest.php`

**Blob:** `b4bf7f136fb2a6d400f6e35c4e93a6c33faf1181` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 548 linii.

- **Ustalenie:** Linie 18–27, 391–464: osobna SQLite :memory:, ręcznie tworzone ograniczone tabele board_roles/hr_specializations/board_members/technical_staff; bootstrap tworzy role effects i state. Nie jest to schemat produkcyjnego MySQL.
- **Ustalenie:** Linie 30–103: seed co najmniej 6 specjalizacji/efektów, rachunek współczynników skill=1.31, morale=1.05 i final=6.8775, wybór opisu en/pl przez $_SESSION, fallback technicznego role code, agregacja efektów hub/pipeline i dwa nowe employee_state.
- **Ustalenie:** Linie 105–188: przed reconcile mirror board→technical pozostaje odrębny stan, po reconcile kopiuje strike i wyłącza efekty; fired canonical mirror nie daje efektu, global nie włącza efektu działowego, brakujący lub błędny link wraca do board. Fixture linku na nieistniejące ID (169) nie dowodzi zachowania przy produkcyjnym FK.
- **Ustalenie:** Linie 190–307: save/update/delete wraz z odczytem DB, lokalizowany description_key/fallback tekst, preferencja scope hub nad department oraz kontrolowane wyjątki dla brakującego id i zduplikowanej krotki; w osobnym legacy fixture jest unikalny indeks na (specialization_code,effect_key,target_scope) w linii 546.
- **Ustalenie:** Linie 309–389, 483–547: manager bonus przy director, pomijanie on_leave/on_strike, migracja starszego SQLite z zachowaniem custom spec/effect i dodaniem opisu. Brak testów HTTP/UI, realnego MySQL i wielu współbieżnych zapisów; nie znaleziono potwierdzonego połączenia tego serwisu z dwoma badanymi skryptami JS.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `tests/Unit/BaseTestCase.php`, `src/Employee/EmployeeRef.php`, `src/Employee/EmployeeRepository.php`, `src/EmployeeSystemBootstrap.php`, `src/Employee/EmployeeStateService.php`, `src/Employee/EmployeeRoleEffectService.php`.
- **Granica dowodu:** Bez wykonania PHP/DB; nie przypisano powiązania do JS bez śladu wywołań.

### `tests/Integration/EmployeeStateServiceTest.php`

**Blob:** `e7c125017ec3b38093d4f1be99235e45951e93f1` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 308 linii.

- **Ustalenie:** Wywołuje rzeczywisty EmployeeStateService i migrator: idempotentne ensure, płaca/satysfakcja, dry-run/apply, własność gracza, kanonizacja pól stanu, filtr ryzyka i wznowienie strajku (27–180); asercje płac są tylko nierównościami, nie dokładnym wzorem (36–47).
- **Ustalenie:** Test rollbacku wymusza błąd SQLite triggerem na drugim INSERT i sprawdza zero wierszy po wyjątku (221–235); to realny test atomowości w pamięci SQLite, nie dowód atomowości MySQL ani wszystkich etapów migracji. Konstruktor migratora bez DDL sprawdzony tylko przez licznik tabel (182–191).
- **Ustalenie:** Przypadek „mixed canonical state” tworzy tylko już kanoniczny stan i sprawdza morale=41 (204–219); nie tworzy równocześnie legacy i canonical, więc nie pokrywa konfliktowej gałęzi scalania reconcileCanonicalState (src/Employee/EmployeeStateService.php:317–335).
- **Ustalenie:** Źródłowe tabele i rekordy pracowników są ręcznie wymyślonym fixture SQLite (238–307), a docelowy schemat powstaje przez bootstrap serwisu (17–24); brak weryfikacji historycznej migracji SQL, danych rzeczywistych i zachowania wielopołączeniowego.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `tests/Unit/BaseTestCase.php`, `src/Employee/EmployeeRef.php`, `src/Employee/EmployeeRepository.php`, `src/EmployeeSystemBootstrap.php`, `src/Employee/EmployeeStateService.php`, `src/HR/EmployeeLegacyMigrationService.php`.
- **Granica dowodu:** Pełny odczyt testu; zależności obejrzano punktowo, nie stanowią pełnego audytu.
- **Granica dowodu:** Nie uruchamiano PHP, SQLite, MySQL, suity ani CRON; runtime i zgodność produkcyjnego schematu pozostają niezweryfikowane.

### `tests/Integration/FinancialStateSectionTest.php`

**Blob:** `a232c04977a23b459dc0661feb2316cde7bee46d` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 213 linii.

- **Ustalenie:** SQLite wywołuje prawdziwe saveCashAndTick: 0 kosztów, potrącenie, podłogę 0, izolację gracza i last_tick_at (68–212). Scenariusz nazwany concurrency tylko seeduje już powiększone saldo 200, a następnie synchronicznie odejmuje 150 — nie ma dwóch połączeń ani interleavingu (139–152).
- **Ustalenie:** Komentarz testu i FinancialStateSection.php opisuje SQL GREATEST, lecz aktualna metoda deleguje do FinancialTransactionService::settleTickCosts; trait blokuje odczyt, zapisuje faktycznie pobraną kwotę do historii i aktualizuje cash (TickSettlementTrait.php:13–59). Migracja wymaga ochrony bieżącego kontraktu ledger/lock, a nie przepisania komentarza jako SQL.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `src/Tick/FinancialStateSection.php`, `src/FinancialTransactionService.php`, `src/FinancialTransaction/TickSettlementTrait.php`.
- **Granica dowodu:** Pełny odczyt pliku; PHP/SQLite/MySQL/CRON nie uruchamiano; zależności i zachowanie runtime pozostają otwarte

### `tests/Integration/FinancialTransactionServiceTest.php`

**Blob:** `e20f1a852835318efad25eb2db9de1d1467fbfe5` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 355 linii.

- **Ustalenie:** Linie 19–25, 299–354: każdy przypadek tworzy SQLite :memory: z dwiema uproszczonymi tabelami; brak rzeczywistego MySQL, constraints/triggerów i zewnętrznych kont.
- **Ustalenie:** Linie 29–82: credit(TYPE_LOAN,250) utrzymuje cash=1000, ustawia bank=250 i sprawdza pojedynczy wpis from=NULL/to=1/amount/type/description; kwota zero i minus oraz nieznany typ zwracają odpowiednie błędy, lecz tylko zero i nieznany gracz kontrolują brak wpisów.
- **Ustalenie:** Linie 87–142: debit(TYPE_TAX,300) zmniejsza cash 1000→700 i sprawdza kierunek wpisu; logistics_fee zdejmuje 2500 z cash i zostawia bank 8000; niedobór zwraca insufficient_funds bez wpisu; pełne saldo dopuszcza cash=0.
- **Ustalenie:** Linie 146–220: transfer 200 zmienia bank 1000→800 i 500→700 oraz zapisuje typ/opis; self_transfer, niedobór, nieznany odbiorca i zero zwracają błędy; 100.4567 zaokrągla do 100.46. Test nazwany Atomically sprawdza tylko końcowe salda i ostatni wpis, bez wymuszonej awarii między zapisami lub drugiej konekcji.
- **Ustalenie:** Linie 175–185: test niedoboru seeduje jedynie gotówkę 50 i 500, podczas gdy player_transfer korzysta z banku (por. 146–150 i 189–193); pokazuje odrzucenie przy bank=0, ale nie sprawdza granicy rzeczywistego salda bankowego 50.
- **Ustalenie:** Linie 224–265: logTransaction zwraca dodatnie ID, typ/referencję i nie zmienia cash obu graczy; nie sprawdza bank obu graczy; zero i niedozwolony typ zwracają null; typy TICK_AUDIT_TYPES są testowane przez iterowanie tej samej produkcyjnej stałej, bez niezależnej listy oczekiwanych typów.
- **Ustalenie:** Linie 268–295: purgeTickAudit(30) usuwa jeden stary tick_opex, pozostawia świeży tick_opex i stary player_transfer; porównanie typów nie obejmuje dokładnych dat granicznych, innych typów audytu i innych graczy.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `tests/Unit/BaseTestCase.php`, `src/FinancialTransactionService.php`, `src/WalletConfig.php`.
- **Granica dowodu:** Nie uruchomiono testów ani bazy; wynik wykonania nieznany.
- **Granica dowodu:** Brak dwóch połączeń, wymuszonej awarii logowania po zmianie salda i potwierdzenia rollbacku atomowego przelewu.
- **Granica dowodu:** Uproszczony schemat SQLite i samoreferencyjna lista typów nie zastępują kontroli produkcyjnych ograniczeń oraz kompletności kontraktu audytowego.

### `tests/Integration/HRContractRenewalTest.php`

**Blob:** `59ad79b9cd3e79b7c45d47f85508540c797bf790` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 124 linii.

- **Ustalenie:** Trzy testy SQLite in-memory: odrzucenie odchodzącego pracownika, odnowienie przy poprawnym kanonicznym powiązaniu board_member–technical_staff, odrzucenie linked pracownika w stanie leaving. Walidują contract_end, nie cały harmonogram wynagrodzeń.
- **Ustalenie:** Korzysta z Reflection bez konstruktorowego workflow HRService i sztucznego schematu SQLite, który nie dowodzi zgodności MySQL FK/migracji.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `src/HRService.php`.
- **Granica dowodu:** Pełny odczyt, bez wykonania PHP/SQLite

### `tests/Integration/HubAssignmentServiceTest.php`

**Blob:** `59d73db1446ded239767fae1ae87fc946713e5f0` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 355 linii.

- **Ustalenie:** Linie 12–48, 50–68: baza SQLite :memory: ma uproszczone players/wells/logistics_hub_assignments; HubService jest mockiem ze statycznym hubem (owner=1, 4 sloty, region=7), nie czyta prawdziwej tabeli hubów.
- **Ustalenie:** Linie 71–85: transferAvailability kontroluje true przy innym hubie, false dla innego właściciela odwiertu i bieżącego huba oraz transfer_no_permit po włączeniu wymogu pozwolenia; pomimo nazwy nie testuje zapełnionych slotów, bo assigned_count=0/slot_limit=4.
- **Ustalenie:** Linie 88–129: assignWell tworzy aktywny wpis i oczekuje jednego createEvent; brak opłaty przy przypięciu potwierdza access_fee=0, niezmienioną gotówkę i access_fee_paid=0, ale nie sprawdza trwałości zdarzenia ani tabel finansowych.
- **Ustalenie:** Linie 131–205: odmowa dla niezajętego huba (hub_not_acquired), huba należącego lub wynajętego przez innego gracza (hub_not_yours), a dla condition_pct=35 zwraca condition_low; HubService jest mockowany.
- **Ustalenie:** Linie 208–250: przyszły cooldown oddaje cooldown_active i dodatnie cooldown_remaining_s; detach zmienia status na detached i ustawia niepuste cooldown_until, bez asercji długości 4h, powtórzeń lub ograniczeń granicznych.
- **Ustalenie:** Linie 252–315: transfer sprawdza stary status detached i nowy aktywny wiersz, odmowa no_hub_permit zachowuje stary status; getWellAssignment i getHub są mockowane, więc nie wykrywa rozjazdu mocka z prawdziwą bazą.
- **Ustalenie:** Linie 318–354: SQLite trigger RAISE(ABORT) na INSERT nowego przypięcia wymusza db_error w istniejącej transakcji; test stwierdza inTransaction=true i aktywny stary wpis, po czym commit. To realna awaria SQL i kontrola savepointu na jednej konekcji (src/HubAssignmentService.php:268–328, 350–377), ale bez sprawdzenia braku nowego wpisu, ochrony wcześniejszych zmian caller-a lub współbieżności.
- **Ustalenie:** Linie 96, 142, 162, 182, 199, 241, 273: createEvent jest liczony mockiem; implementacja wywołuje go po commit i połyka jego wyjątek (src/HubAssignmentService.php:86–110, 325–350), a test nie symuluje błędu zdarzenia.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `tests/Unit/BaseTestCase.php`, `src/HubService.php`, `src/HubAssignmentService.php`, `src/Hub/AssignmentValidationTrait.php`.
- **Granica dowodu:** Nie uruchomiono testów ani bazy; wynik wykonania nieznany.
- **Granica dowodu:** Brak prawdziwej implementacji HubService w przepływie testowym, dwóch połączeń, wyścigu o ostatni slot i kontroli unikalności aktywnych przypięć.
- **Granica dowodu:** Nieprzetestowane skutki nieudanego zdarzenia po commit oraz błędu podczas detach/assign w istniejącej transakcji; komentarz serwisu o system-owned hubach nie zgadza się z aktualną walidacją owner/tenant, więc nie przyjmować go jako potwierdzonej reguły biznesowej.

### `tests/Integration/HubPlayerQueryTraitTest.php`

**Blob:** `779bc420a861092cef52f07e89d9d102da500259` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 135 linii.

- **Ustalenie:** SQLite in-memory sprawdza listę nieprzypisanych odwiertów (w tym no_operator, bez sold), cooldown_until aktywny/wygasły, najpóźniejszy cooldown po wielu odłączeniach i wykluczenie aktywnie przypisanego.
- **Ustalenie:** Sprawdza tylko SELECT/trait, nie wywołuje transferWell(), ticku ani opłaty; nie rozstrzyga historycznej rozbieżności co do dwutickowego transferu.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `src/Hub/PlayerQueryTrait.php`.
- **Granica dowodu:** Pełny odczyt, brak PHP/SQLite oraz MySQL runtime

### `tests/Integration/HubStaffingManagementServiceTest.php`

**Blob:** `d0bcee56c0056e459dc10f91cfef13f9c9aa2a0c` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 237 linii.

- **Ustalenie:** SQLite sprawdza widok kandydatów, pracowników najemcy huba, cap throughput 0.70 przy strajku, zmianę istniejącej alokacji, zwolnienie i odrzucenie nieoperatora (27–142).
- **Ustalenie:** SetUp korzysta z własnych tabel i seeda; nie dowodzi autoryzacji kontrolera, całego ticku, braku podwójnej kary przy wielu efektach ani zachowania MySQL pod współbieżnym przydziałem.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `src/Employee/HubStaffingManagementService.php`, `src/Employee/EmployeeAssignmentService.php`.
- **Granica dowodu:** Pełny odczyt pliku; PHP/SQLite/MySQL/CRON nie uruchamiano; zależności i zachowanie runtime pozostają otwarte

### `tests/Integration/LegalMapPermitDataTest.php`

**Blob:** `e366ba799f27fc247f188a8f0e5562b2f458f14a` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 365 linii.

- **Ustalenie:** Bezpośrednio sprawdza getMapPermitData na pustym zbiorze, statusach active/pending/delayed/no_decision/refused/none/locked oraz czasach, cooldownie, poziomie prawnym, wiarygodności i izolacji gracza (32–247); zegar jest ustalony na 2026-06-04 12:00:00 (23–30), progi kapitału/wiarygodności mają przypadki równości (154–162, 202–210).
- **Ustalenie:** Fixture ręcznie tworzy tylko wycinek players, legal_region_config, drilling_permit_applications i opcjonalne role (252–365), nie odtwarza produkcyjnego UNIQUE(player_id, region_id) z src/LegalService.php:185–203. Dlatego brak przypadku wielu aplikacji nie jest sam w sobie luką normalnego schematu; SELECT bez ORDER BY w getMapPermitData (965–973) i nadpisywanie statusu (1024–1066) może być problemem jedynie przy rozjechanym/legacy schemacie dopuszczającym duplikaty. Wymaga osobnej kontroli migracji danych i indeksu, nie twierdzenia o błędzie aktualnej bazy.
- **Ustalenie:** Komentarz deklaruje wszystkie warianty, lecz legal_locked i credibility_locked dochodzą poza wymienioną listą (7–14,165–210); testuje wynik metody, nie wyświetlenie mapy ani walidację złożenia wniosku. Brak konfiguracji wyłączonego regionu, uszkodzonych danych/zapytań i wariantu cooldown dokładnie w chwili końca.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `tests/Unit/BaseTestCase.php`, `src/LegalService.php`, `src/CompanyCredibilityService.php`.
- **Granica dowodu:** Pełny odczyt testu; zależności obejrzano punktowo, nie stanowią pełnego audytu.
- **Granica dowodu:** Nie uruchamiano PHP, SQLite, MySQL, suity ani CRON; runtime i zgodność produkcyjnego schematu pozostają niezweryfikowane.

### `tests/Integration/LegalNotificationsTest.php`

**Blob:** `6ffc3b0cf80bbf6c24fb12e660d4bf98dc723293` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 251 linii.

- **Ustalenie:** SQLite sprawdza jeden komunikat po submit, zero po braku środków, osobny komunikat dla każdego regionu oraz brak duplikatu przy ponowionej migracji (32–123). Test 66–78 jawnie oczekuje sukcesu wniosku i pobrania opłaty, gdy tabela powiadomień nie istnieje.
- **Ustalenie:** LegalService::submitApplication wywołuje notifyDirector po commit (687–705), a notifyDirector po błędzie tylko loguje (725–750). Potwierdzona statycznie możliwość opłaconego wniosku bez komunikatu; brak testu odzyskania/ponownego doręczenia po naprawie tabeli. W Laravel wymagana decyzja o outboxie lub świadomie best-effort.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `src/LegalService.php`.
- **Granica dowodu:** Pełny odczyt pliku; PHP/SQLite/MySQL/CRON nie uruchamiano; zależności i zachowanie runtime pozostają otwarte

### `tests/Integration/LegalPermitSimulationTest.php`

**Blob:** `3e5f3164b333f735008006b7cae1f895f7602fc2` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 466 linii.

- **Ustalenie:** Test uruchamia rzeczywiste submitApplication, LegalSection::run i WorldMap::regionPurchaseBlock na prywatnym SQLite in-memory: opłata → pending → tick → granted → odblokowanie mapy, izolacja graczy (45–82,211–235), opóźnienie i drugi tick po zmianie ryzyka (88–125), odmowa/cooldown/retry (131–174), no_decision/retry (180–205), brak decyzji przed terminem (241–255), migracja transitional (261–277) i blokada ponownego wniosku granted (283–301). Nie jest to test HTTP, przeglądarki ani produkcyjnego CRON.
- **Ustalenie:** Fixture tworzy ręcznie players, wells, regiony, konfigurację, applications i notifications; applications ma UNIQUE(player_id,region_id) (342–420), odmiennie od schematu siostrzanego LegalServiceTest. Ryzyka 0/100 i stałe czasy 2026-06-01..03 dają nominalnie deterministyczne gałęzie, ale brak asercji dokładnej chwili decision_due_at oraz równości cooldown; sprawdza 10 min przed terminem i 31 min po 30 min (45–69,131–159,241–255). Produkcyjny tick wybiera due <= now i próg mt_rand(1,100) (src/Tick/LegalSection.php:43–59,423–446).
- **Ustalenie:** W teście opóźnienia drugi termin jest ręcznie cofany UPDATE, a ryzyko 100 zmieniane na 0 (111–123); dowodzi ponownego rozpatrzenia pod zmienioną konfiguracją, nie samoczynnego dojścia nowego terminu. W retry po odmowie także ręcznie cofa się termin (163–172). Komentarz scenariusza 8 mówi o opóźnieniu, lecz asercje obejmują tylko granted i odrzucenie retry (279–301).
- **Ustalenie:** setUp rejestruje atrapę DATE_ADD zwracającą pusty tekst (31–39), a schemat nie odtwarza wszystkich kluczy/typów MySQL; nie należy z niego wnioskować o zgodności czasu/DDL w produkcji. Asercje notified=1/2 sprawdzają licznik, a nie treść i dostarczenie powiadomień (71–72,226–227).
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `tests/Unit/BaseTestCase.php`, `src/LegalService.php`, `src/Tick/LegalSection.php`, `src/WorldMap.php`.
- **Granica dowodu:** Pełny odczyt testu; zależności obejrzane punktowo, nie pełny audyt.
- **Granica dowodu:** Nie uruchamiano PHP/SQL/CRON ani suity; zachowanie runtime i MySQL niezweryfikowane.

### `tests/Integration/LegalSectionTest.php`

**Blob:** `c5c64b257962362bcc328cb2c1a593a6d4541443` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 376 linii.

- **Ustalenie:** Wywołuje LegalSection::run i pojedynczy moduł TickEngine: sprawdza 4 deterministyczne wyniki upgradu transitional, decyzje pending/delayed, przyszłe/terminalne pomijanie, opóźnienia i licznik, odmowę/cooldown, no_decision, liczniki i powiadomienia oraz przetwarzanie huba mimo błędu zapytania drilling (29–255). Wyniki wymusza ryzykiem 0/100, nie weryfikuje losowej dystrybucji ani progów pośrednich.
- **Ustalenie:** Powiadomienie sprawdza typ i fragment tytułu, ale nie dokładnie jeden wpis na każdą decyzję ani awarię INSERT powiadomienia (196–226); produkcja najpierw aktualizuje status/licznik, potem osobno próbuje powiadomić i po błędzie go nie ponawia (src/Tick/LegalSection.php:472–483,554–583). Możliwa trwała decyzja bez notyfikacji wymaga osobnej próby błędu.
- **Ustalenie:** Test strajku wywołuje metodę prywatną przez ReflectionMethod (106–121), bez sprawdzenia wyniku decyzji przy strajku. Hub-failure jest wymuszony przez DROP tabeli na sztucznym SQLite (244–254); nie dowodzi izolacji błędów transakcyjnych MySQL. Fixture ma tylko trzy uproszczone tabele (276–330), a pozostałe helpery korzystają z rzeczywistego czasu, nie stałego zegara (257–260,352–375).
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `tests/Unit/BaseTestCase.php`, `src/LegalService.php`, `src/Tick/LegalSection.php`, `src/Tick/TickEngine.php`, `src/Employee/EmployeeSystemConfigService.php`, `src/HR/StrikeEffectService.php`.
- **Granica dowodu:** Pełny odczyt testu; zależności obejrzano punktowo, nie stanowią pełnego audytu.
- **Granica dowodu:** Nie uruchamiano PHP, SQLite, MySQL, suity ani CRON; runtime i zgodność produkcyjnego schematu pozostają niezweryfikowane.

### `tests/Integration/LegalServiceTest.php`

**Blob:** `e03c98419df88bd3fe898d5028fcc840012178cf` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 704 linii.

- **Ustalenie:** Test wywołuje rzeczywisty LegalService: seed ryzyka i idempotencja konfiguracji (101–109,162–205), statusy (207–241), opłata i termin (381–398), strajk wydłużający termin (400–429), blokady aktywnego/w toku, kapitału, wiarygodności i progu równości 40, poziomu działu, środków i cooldownu (431–603), a także migracja przejściowych zezwoleń (607–694). Asercje dotyczą wyniku serwisu i SQLite, nie POST ani UI.
- **Ustalenie:** Historia: test opóźnionego wpisu opłaty wymusza 10 sekund przesunięcia i sprawdza jedno archiwum z ID (25–42); test legacy pilnuje starszej opłaty obok archiwum (44–60); retry po no_decision i rollback przy triggerze INSERT sprawdzają historię, salda i status (111–160). Trigger to rzeczywisty błąd SQLite, ale nie dowód transakcji MySQL; ensureSchema wewnątrz transakcji sprawdzono tylko przez inTransaction (93–99).
- **Ustalenie:** Własny schemat drilling_permit_applications nie ma UNIQUE(player_id,region_id) ani FK (245–301), podczas gdy definicja MySQL serwisu ma UNIQUE (src/LegalService.php:185–203). Test idempotencji migracji sprawdza liczbę wierszy bez wymuszenia unikalności (622–636); nie potwierdza zgodności produkcyjnego DDL ani wyścigów. Cooldown testowany tylko 'now' ±1 godzina (558–593), nie dokładnie w chwili wygaśnięcia; serwis porównuje > (src/LegalService.php:507–516).
- **Ustalenie:** Testy wczesnych odmów sprawdzają kod i/lub saldo, ale nie stan połączenia inTransaction (431–603); w serwisie ścieżki wczesnego return po beginTransaction są zabezpieczone przez finally z rollBack (src/LegalService.php:479–539,669–685). BaseTestCase zeruje sesję w setUp/tearDown, createSqlitePdo używa sqlite::memory: (tests/Unit/BaseTestCase.php:8–23; tests/Integration/SqliteIntegrationTestCase.php:8–25).
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `tests/Unit/BaseTestCase.php`, `src/LegalService.php`, `src/Legal/ApplicationHistory.php`, `src/Tick/LegalSection.php`.
- **Granica dowodu:** Pełny odczyt testu; zależności obejrzane punktowo, nie pełny audyt.
- **Granica dowodu:** Nie uruchamiano PHP/SQL/CRON ani suity; wykonanie, MySQL i współbieżność niezweryfikowane.

### `tests/Integration/MobileWebBridgeTest.php`

**Blob:** `449e178fa717f89384fb709231d1d4068c1a67c8` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 383 linii.

- **Ustalenie:** Linie 13–38, 159–177: lokalne SQLite :memory: jest wstrzykiwane refleksją do globalnego singletonu Database; tearDown zeruje singleton, BaseTestCase zeruje $_SESSION. Nie sprawdza pozostałego stanu globalnego (np. $_SERVER, funkcji, obsługi błędów).
- **Ustalenie:** Linie 40–75: createForPlayer/consume zwraca użytkownika za pierwszym razem, null za drugim; ręcznie przeterminowany token, konto banned i email_verified=0 nie dają kontekstu. Sprawdza API funkcji bez realnej przeglądarki.
- **Ustalenie:** Linie 77–88: przesuwa expires_at na za 1 sekundę, śpi 2 sekundy i otrzymuje null; nie kontroluje interleavingu między SELECT a warunkowym UPDATE ani konkurencyjnego consume na dwóch połączeniach, zatem nazwa AtomicUpdate opisuje implementację (src/MobileWebBridge.php:98–123), nie dowód wyścigu.
- **Ustalenie:** Linie 90–150: Auth::loginByPlayerId tworzy user_id/username w $_SESSION; osobny proces PHP uruchamia endpointy dla braku i błędnego Bearer w pl/en (401, komunikat, brak user_id/URL/tokenu), GET (405), POST z podmienionym HTTP_HOST (200, TTL=60, URL z oilempire.pl), publiczne logowanie ważnym i nieważnym tokenem (sesja obecna/nieobecna).
- **Ustalenie:** Linie 183–235, 237–379: runner jest tymczasowym plikiem PHP z serializowanymi parametrami, proces dostaje plik SQLite, deterministyczny fikcyjny token API i emuluje $_SERVER; endpointy są naprawdę dołączane, ale nie ma HTTP, serwera WWW, nagłówków z sieci ani cookie przeglądarki. Assercja 405 nie sprawdza metody w body poza treścią błędu.
- **Ustalenie:** Linie 192–210, 249–265: skrypt i plik bazy są sprzątane przy normalnej ścieżce; przedwczesne przerwanie/nieudane asercje mogą zostawić runner w systemowym katalogu tymczasowym; test uruchamia subprocess i zapisuje plik poza pamięcią.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `tests/Unit/BaseTestCase.php`, `src/Database.php`, `src/Auth.php`, `src/MobileWebBridge.php`, `api/v1/auth/webview-bridge.php`, `public/mobile_bridge_login.php`.
- **Granica dowodu:** Nie uruchomiono testów, subprocessów ani bazy; wynik wykonania nieznany.
- **Granica dowodu:** Brak dwu-koneksyjnej konkurencji, testu rzeczywistych odpowiedzi HTTP/cookie oraz negatywnych przypadków formatów tokenów, CSRF i przekierowań open-redirect.
- **Granica dowodu:** Test narusza globalny singleton refleksją i emuluje środowisko procesu; nie dowodzi konfiguracji produkcyjnej ani zachowania proxy/serwera WWW.

### `tests/Integration/MoraleFormulaTest.php`

**Blob:** `0595592d6b53aa8c29b1297eaf58a7ffba674c42` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 162 linii.

- **Ustalenie:** Pięć testów SQLite in-memory: dolny clamp oczekiwanej płacy 40%, górny 90%, workload wg statusu, modyfikator lojalności obniżający ryzyko oraz synchronizacja morale do kanonicznego stanu przez legacy mirror.
- **Ustalenie:** Sprawdza wybrane wartości brzegowe i sztuczne tabele; nie dowodzi MySQL ani pełnej migracji historii pracownika.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `src/EmployeeSystemBootstrap.php`, `src/Employee/EmployeeSystemConfigService.php`, `src/HR/MoraleServiceV2.php`.
- **Granica dowodu:** Pełny odczyt, brak uruchomienia PHP/SQLite

### `tests/Integration/OfflineSectionTest.php`

**Blob:** `611d784d43b0315f3575a5a2ab856ff10a7473c5` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 330 linii.

- **Ustalenie:** Wywołuje OfflineSection::process na syntetycznym SQLite: 5/45 minut bezczynności, 2/25 godzin ochrony, mnożniki, freeze przy cash=0, zapis offline_frozen, przejście offline, raport i reset po powrocie (99–329). Asercje bitu, raportu i flag odczytują faktyczny stan DB (180–192,224–242,248–329).
- **Ustalenie:** Fixture sam tworzy kolumnę offline_frozen i offline_reports (23–52), więc nie testuje zapewnienia schematu przez konstruktor: w produkcji ensureSchema działa tylko dla MySQL poza transakcją i połyka wyjątek (src/Tick/OfflineSection.php:32–51). Nie pokrywa awarii zapisu, braku offline_since, granicy 30 minut ani dokładnie 24 h.
- **Ustalenie:** Powrót online w implementacji wykonuje INSERT raportu i osobny UPDATE flag bez transakcji, po czym połyka błąd (src/Tick/OfflineSection.php:104–130); testy badają tylko ścieżkę sukcesu, nie ewentualny duplikat raportu po częściowej awarii i ponowieniu.
- **Ustalenie:** Testy ustawiają $now na datę stałą, lecz helpers minutesAgo/hoursAgo używają strtotime/date w domyślnej strefie (23–27,73–80); brak sprawdzenia strefy i DST. BaseTestCase zeruje globalną sesję w setUp/tearDown (tests/Unit/BaseTestCase.php:8–23).
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `tests/Unit/BaseTestCase.php`, `src/Tick/OfflineSection.php`.
- **Granica dowodu:** Pełny odczyt testu; zależności obejrzano punktowo, nie stanowią pełnego audytu.
- **Granica dowodu:** Nie uruchamiano PHP, SQLite, MySQL, suity ani CRON; runtime i zgodność produkcyjnego schematu pozostają niezweryfikowane.

### `tests/Integration/OffshoreTransportServiceTest.php`

**Blob:** `d619a7cde4c7ec63c3e6cac6fc525c7e70ee3cd5` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 232 linii.

- **Ustalenie:** SQLite bada auto-schemat, idempotentny config tylko tankowca, zerowy input/czas/ryzyko, liczbę kursów, koszt i bilans delivered+lost=input; bada też losowe incydenty i przez refleksję prywatny zakres strat (29–231).
- **Ustalenie:** Max-risk i default używają RNG bez kontrolowanego ziarna, więc nie jest to deterministyczny test ticku. Brak portu, rzeczywistych decyzji zezwolenia i MySQL; nie interpretować samego bilansu jako dowodu dostarczenia do magazynu.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `src/OffshoreTransportService.php`.
- **Granica dowodu:** Pełny odczyt pliku; PHP/SQLite/MySQL/CRON nie uruchamiano; zależności i zachowanie runtime pozostają otwarte

### `tests/Integration/ProtectionServiceTest.php`

**Blob:** `becbfb1b2e23ccf0275fd598d7160f525be89ce9` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 394 linii.

- **Ustalenie:** Wywołuje rzeczywiste quote/activate/getActiveEffects/applyEffects/cancel/getAvailableOptions i batched getActiveProtections: sprawdza koszty fixed/per_hour/percent, debet cash, transakcję bankową, ochronę, log i powiadomienie, odmowę bez środków, blokady wymagań, efekty, expiry oraz hub/pipeline (22–325). Nie ma testu kosztu per_bbl, ujemnej referencji, opcji disabled ani ochrony innego gracza.
- **Ustalenie:** Test unikalności wymusza drugi INSERT w tej samej pojedynczej bazie SQLite (119–137), a test „already_active” wykonuje kolejno dwie aktywacje na jednej instancji (87–102); nie sprawdzają konfliktu dwóch połączeń ani ścieżki MySQL po błędzie UNIQUE.
- **Ustalenie:** Stan schematu ochrony powstaje przez ProtectionSchema::ensure w konstruktorze serwisu, a players/ledger/notyfikacje są małym ręcznym fixture SQLite (342–393; src/ProtectionService.php:46–53); nie dowodzi zgodności migracji MySQL ani księgowania na rzeczywistym schemacie.
- **Ustalenie:** Powiadomienie aktywacji jest wysyłane po commit i błąd zostaje połknięty (src/ProtectionService.php:217–247,626–645); test sprawdza tylko sukcesowy wpis (46–70), bez awarii powiadomienia i retry. logEvent również połyka błąd, choć wykonywany przed commit (src/ProtectionService.php:359–376); brak iniekcji tych awarii i testu atomowości debetu.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `tests/Unit/BaseTestCase.php`, `src/ProtectionService.php`, `src/Protection/ProtectionSchema.php`, `src/FinancialTransactionService.php`, `src/CompanyCredibilityService.php`, `src/LegalService.php`.
- **Granica dowodu:** Pełny odczyt testu; zależności obejrzano punktowo, nie stanowią pełnego audytu.
- **Granica dowodu:** Nie uruchamiano PHP, SQLite, MySQL, suity ani CRON; runtime i zgodność produkcyjnego schematu pozostają niezweryfikowane.

### `tests/Integration/RoadTransportServiceTest.php`

**Blob:** `29ac19a1d232208b142a60a3240d4fa2b0c6cd3a` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 263 linii.

- **Ustalenie:** SQLite bada config tylko ciężarówek, zero input/czas/ryzyko, liczbę kursów, koszt, bilans bbl, typy incydentów i ochronę ich wag (29–262). W teście protection użyto refleksji prywatnej metody.
- **Ustalenie:** Test statystyczny przy 2000 kursów używa mt_srand(4242) bez przywrócenia globalnego RNG (238–260), a część scenariuszy pozostaje losowa. Brak rzeczywistego ruchu przez tick, transportu do storage i parytetu MySQL.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `src/RoadTransportService.php`.
- **Granica dowodu:** Pełny odczyt pliku; PHP/SQLite/MySQL/CRON nie uruchamiano; zależności i zachowanie runtime pozostają otwarte

### `tests/Integration/SabotageScreenTest.php`

**Blob:** `eac8f061e3bc364d724c4686a756c7b468a88e33` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 93 linii.

- **Ustalenie:** SQLite in-memory bada wyszukiwanie kwalifikowanych celów, odmowę ataku na inactive bez obciążenia portfela, cytowanie kosztu, próg z konfiguracji, paginację i retencję historii przy trwającym cooldown.
- **Ustalenie:** Test nie wykonuje rzeczywistego HTTP/CSRF/operation token ani testów współbieżności obciążenia; wnioski dotyczą jedynie ścieżek serwisu przy sztucznych tabelach.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `src/SabotageService.php`.
- **Granica dowodu:** Pełny odczyt, bez wykonania PHP/SQLite

### `tests/Integration/TechnicalDisasterStatusTest.php`

**Blob:** `9c12f84230c9b95dd0db66194df50a97e22a1c84` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 148 linii.

- **Ustalenie:** SQLite in-memory testuje automatyczne oznaczenie nieaktualnej katastrofy resolved przy ładowaniu widoku, pozostawienie aktualnej i bycie w naprawie, izolację graczy oraz nieujawnianie nazwy cudzego odwiertu w historii awarii.
- **Ustalenie:** loadActiveDisasters jest obserwowalnie operacją z efektem zapisu nawet na odczycie strony (asercja statusu po load). Przy migracji kontrolera Blade trzeba zachować lub świadomie zmienić ten kontrakt; test nie sprawdza współbieżności i rzeczywistego schematu MySQL.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `src/TechnicalPage/DataTrait.php`.
- **Granica dowodu:** Pełny odczyt testu i bazy SQLite helper, bez uruchomienia

### `tests/Integration/TechnicalHubTasksTest.php`

**Blob:** `5332f244f5b346efb450c6a5431de1d92d6b9920` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 371 linii.

- **Ustalenie:** tests/Integration/TechnicalHubTasksTest.php:11–16,243–274,350–370: każda metoda dostaje SQLite :memory: z uproszczonymi tabelami players, technical_staff/tasks/queue, hubs/assignments, wells, disasters itd.; fixtures seedują gracza 1 z cash 100000000 i aktywnego maintenance_engineer, opcjonalnie hub z aktywnie przypisanym odwiertem; anonimowa podklasa TechnicalTeamService neutralizuje manager bonus i ReflectionProperty ustawia db/playerId=1. tests/Integration/SqliteIntegrationTestCase.php:8–25 dostarcza PDO i funkcje SQLite; tests/Unit/BaseTestCase.php:8–23 resetuje sesję.
- **Ustalenie:** tests/Integration/TechnicalHubTasksTest.php:18–76: anulowanie paused_strike oznacza zadanie cancelled, pracownik nie jest active, powtórne anulowanie jest nieskuteczne; obcego queue item nie usuwa; nie promuje kolejki strajkującego; obcego task i busy status pozostawia bez zmian; przy well_repair przywraca poprzedni status tylko własnego odwiertu.
- **Ustalenie:** tests/Integration/TechnicalHubTasksTest.php:78–95: fixture tworzy dwa uszkodzone rurociągi i dwie aktywne katastrofy z pipeline_id, completeTask pipeline_repair dla pipeline 10 rozwiązuje tylko katastrofę 1, druga pozostaje active.
- **Ustalenie:** tests/Integration/TechnicalHubTasksTest.php:97–134: assignTask hub_maintenance bez hub_id i na obcym/nieużywanym hubie zwraca success=false z fragmentami komunikatów; zajęty pracownik dostaje hub_repair do kolejki z task_type i hub_id=10, success=true i queued=true.
- **Ustalenie:** tests/Integration/TechnicalHubTasksTest.php:136–227: completeTask hub_maintenance zmienia condition 40→58, repair_cost_estimate 500000→50000, zapisuje last_maintenance_at, oznacza task completed i staff active; hub_repair zmienia 15→92, 750000→0, damaged→active oraz timestamp; obcy task pozostaje in_progress i obcy hub utrzymuje condition=20.
- **Ustalenie:** tests/Integration/TechnicalHubTasksTest.php:229–240: cleanupTaskHistoryOlderThanDays(2) usuwa dokładnie 3 stare zamknięte zadania gracza 1, pozostawia niedawno completed, stare in_progress i zadanie gracza 2 (ID [4,5,6]).
- **Ustalenie:** tests/Integration/TechnicalHubTasksTest.php:284–318: funkcja SQLite NOW jest nadpisana na czas PHP+10 h; assignTask hub_maintenance sprawdza in_progress/end_time>NOW, processTick nie kończy zadania, cashAfter<cashBefore. tests/Integration/TechnicalHubTasksTest.php:326–348: trigger BEFORE INSERT wymusza PDOException, po błędzie cash wraca do wartości sprzed zlecenia, liczba zadań i bank_transactions wynosi zero.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `tests/Unit/BaseTestCase.php`, `src/TechnicalTeamService.php`.
- **Granica dowodu:** Nie uruchomiono PHP/DB/CRON; syntetyczny SQLite i podmieniony manager nie weryfikują produkcyjnego MySQL, prawdziwego schedulera ani wielu połączeń.
- **Granica dowodu:** Scenariusz zegarów używa PHP date/time() i nadpisania tylko SQL NOW (tests/Integration/TechnicalHubTasksTest.php:284–288), a test katastrofy woła completeTask z ręcznie skonstruowanym payloadem (tests/Integration/TechnicalHubTasksTest.php:78–95).

### `tests/Integration/TechnicalStrikeEffectsTest.php`

**Blob:** `a97df7935f28de289ebd6f1a1388712a68a7fc1a` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 198 linii.

- **Ustalenie:** SQLite sprawdza blokadę nowego zadania dla on_strike/leaving/inactive tylko właściwego gracza, pauzę i wznowienie zadania z zachowaniem ok. 2h, snapshot mnożnika czasu/kosztu awarii i niezmienność zwykłego zadania (19–135).
- **Ustalenie:** Test ustawia $_SESSION=[] i tworzy subclass TechnicalTeamService pomijający konstruktor, managera i bonusy; czas bazuje na datetime(now), a nie wstrzykiwanym zegarze. Nie dowodzi pełnej ścieżki HTTP, ticku ani MySQL.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `src/TechnicalTeamService.php`.
- **Granica dowodu:** Pełny odczyt pliku; PHP/SQLite/MySQL/CRON nie uruchamiano; zależności i zachowanie runtime pozostają otwarte

### `tests/Integration/WellHubReconciliationTest.php`

**Blob:** `c20bbfa1646d1705d107d74540d80d4c7584dfb0` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 286 linii.

- **Ustalenie:** Sprawdza bilans 300 bbl przy mnożniku strat 0.9, zatrzymanie finalize po odmowie persystencji, brak obciążenia starego magazynu przy drenażu bufora oraz skalowanie strat incydentu (91–285).
- **Ustalenie:** HubTickService::processTick, persistTickResult i addBufferBbl są zastąpione klasą testową; test awarii sprawdza stan w pamięci, nie rollback SQL ani trwały bilans między well/hub/storage.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `src/Tick/WellHubSection.php`, `src/HubTickService.php`, `src/HubService.php`.
- **Granica dowodu:** Pełny odczyt pliku; PHP/SQLite/MySQL/CRON nie uruchamiano; zależności i zachowanie runtime pozostają otwarte

### `tests/Integration/WellProductionHandlerTotalCostsTest.php`

**Blob:** `9881d6f55e5f7391b2536bdaf9e6b341cd03ebe4` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 332 linii.

- **Ustalenie:** Linie 28–50, 52–161: SQLite :memory: zawiera wyłącznie wells; WellLoopSection, WellService i WellProductionSection są ręcznymi stubami z mnożnikami=1, ceną=70 oraz wyłączonymi usługami transportowymi; test wywołuje rzeczywisty WellProductionHandler, ale nie pełen tick.
- **Ustalenie:** Linie 176–192: processOpex przy cash=0 ma zwrócić false, totalCosts=1000 i finOpex=0; wymusza totalCosts>finOpex i sprawdza tylko akumulatory, nie zapis salda gracza.
- **Ustalenie:** Linie 201–218: przy cash=400 wymaga totalCosts=1000, finOpex=400 i nierówności; nie sprawdza zwróconej wartości processOpex ani statusu paused_cash w DB.
- **Ustalenie:** Linie 231–269: po krótkim OPEX processProduction dla rurociągu o opex_per_tick=800 wymaga totalCosts≈800+opexCost z tolerancją 1 oraz mniej ścisłych nierówności dla finTransport; nie mierzy dokładnie 499 ani salda końcowego.
- **Ustalenie:** Linie 280–304: przy wysokiej gotówce i podatku regionalnym 30% kontroluje dodatni przyrost totalCosts i równość z finTax w tolerancji 0.01; nie sprawdza niezależnie oczekiwanej kwoty podatku, więc wspólny błąd wyliczenia może przejść.
- **Ustalenie:** Linie 317–330: przypadek nazwany AlwaysGeAllFinAccumulators wykonuje tylko jeden niedoborowy OPEX cash=50; w PHPUnit assertGreaterThanOrEqual($sumFin,$loop->totalCosts) sprawdza totalCosts>=sumFin, a druga nierówność jedynie ten wariant niedoboru, nie uniwersalne zawsze.
- **Ustalenie:** Linie 10–22: komentarz odwołuje się do atomowego zapisu cash=GREATEST(0,cash-totalCosts), ale test w ogóle nie wywołuje FinancialStateSection::saveCashAndTick; metoda deleguje do FinancialTransactionService::settleTickCosts (src/Tick/FinancialStateSection.php:137–144), więc ten przypadek nie dowodzi wyścigu kredytu i ticka.
- **Zależności do sprawdzenia:** `tests/Integration/SqliteIntegrationTestCase.php`, `tests/Unit/BaseTestCase.php`, `src/Tick/WellProductionHandler.php`, `src/Tick/WellLoopSection.php`, `src/Tick/WellProductionSection.php`, `src/Tick/FinancialStateSection.php`, `src/FinancialTransactionService.php`, `src/WellService.php`.
- **Granica dowodu:** Nie uruchomiono testów ani bazy; wynik wykonania nieznany.
- **Granica dowodu:** Brak dwóch połączeń, pośredniego zwiększenia gotówki, wywołania settleTickCosts/saveCashAndTick oraz asercji rzeczywistego cash w players.
- **Granica dowodu:** Stubbowane usługi, neutralne mnożniki i jeden przypadek na kategorię nie obejmują alternatywnych ścieżek transportu ani wszystkich kombinacji kosztów.

### `tests/MySqlIntegration/MySqlB2BContractServiceTest.php`

**Blob:** `591ab2209c69dea5f8265398eaa20d36f7a3191b` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 363 linii.

- **Ustalenie:** Linie 38-77 i 79-121: dwa rzeczywiste wyścigi oddzielnych procesów/połączeń MySQL, rodzic blokuje ofertę FOR UPDATE (275-281), zwalnia po gotowości workerów; asercje dokładnie 1 sukces/1 porażka, status accepted/not_open lub completed/not_accepted, wolumen/escrow, dostawy, jedna/dwie transakcje, saldo sprzedawcy i stan storage. Worker łączy PDO osobno i dopiero wtedy publikuje ready (tests/fixtures/b2b_concurrent_worker.php:23-42); serwis blokuje ofertę w transakcji i kredytuje sprzedawcę (src/B2BContractService.php:389-399,445-527,561-570,609-676,1435-1522,1586-1592). Gotowość nie dowodzi jednoczesnego wejścia do krytycznej sekcji; kod testu nie asertuje exit code workerów (341-360).
- **Ustalenie:** Linie 123-164: nazwa/comment o współbieżności myli — dwa wywołania deliverPartial idą kolejno na tej samej instancji PDO; sprawdza zakończenie, odmowę drugiego wywołania i liczbę rekordów, nie wyścig FOR UPDATE.
- **Ustalenie:** Linie 166-186: sekwencyjne acceptAndDeliver dwukrotnie; sprawdza brak ponownej akceptacji, jedną transakcję, saldo i reputację. Wrapper deleguje do acceptOffer (src/B2BContractService.php:365-371); nie pokrywa refundu, anulowania, terminów ani wielokrotnych dostaw niekończących.
- **Ustalenie:** Linie 14-35,189-227: dodatkowe DELETE dla buyer/seller (seed+80/+81) i ręczne seedy graczy/storage; catch(Throwable) w tearDown połyka błąd sprzątania. Konstruktor B2BContractService wywołuje B2BContractSchema::ensure (src/B2BContractService.php:23-33), więc możliwe DDL; moduł/limity/cena zależą od istniejącej b2b_contract_config (src/B2BContractService.php:63-103,159-180). Baza: tests/MySqlIntegration/MySqlIntegrationTestCase.php:11-35 łączy PDO z config/database.php:3-8 bez bramki izolacji, losuje ID, wykonuje DELETE jeszcze w setUp, czyści $_SESSION i wyłącza GameLog; konstruktor WellPipelineService uruchamia DDL/migracje (src/WellPipelineService.php:40-55,57-133). tearDown:37-76 ponawia DELETE. Nie uruchomiono PHP, SQL ani testów.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `config/database.php`, `src/WellPipelineService.php`, `src/init.php`, `src/B2BContractService.php`, `src/B2BContracts/B2BContractSchema.php`, `tests/fixtures/b2b_concurrent_worker.php`.
- **Granica dowodu:** Statyczny odczyt; brak wykonania testów/DB; nie potwierdzono skuteczności blokad na serwerze ani czystości danych konfiguracyjnych.

### `tests/MySqlIntegration/MySqlBankNegotiationOwnershipTest.php`

**Blob:** `75356ef916b780b05584c3d4ce35e2c827dee52a` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 158 linii.

- **Ustalenie:** Testy MySQL weryfikują, że cudzy dyrektor nie trafia do kontekstu negocjacji oraz że strajk finansów wyłącza bonus CFO; wywołują prywatne buildContext przez Reflection, nie pełny workflow oferty kredytowej.
- **Ustalenie:** P0 warunkowo: poza niechronioną bazą test zmienia globalne feature_strike_effects przez EmployeeSystemConfigService::save (108–132) i zapisuje active employee_strikes; finally próbuje przywrócić wartość, ale twarde przerwanie procesu lub awaria sprzątania może zostawić zmianę. Wymagana wyłącznie dedykowana DB.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `src/BankNegotiationService.php`, `src/Employee/EmployeeSystemConfigService.php`, `config/database.php`.
- **Granica dowodu:** Pełny odczyt; nie uruchomiono PHP/MySQL ani testu awarii cleanup

### `tests/MySqlIntegration/MySqlChatServiceLimitTest.php`

**Blob:** `437306af8a3534799272f8108ced0f410a7e47cb` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 104 linii.

- **Ustalenie:** Test tworzy TEMPORARY TABLE dla graczy, pokojów i wiadomości przy emulacji prepared statements, weryfikując LIMIT dla listy pokojowej, direct i aktywnych graczy; brak API_TEST_MYSQL_DSN oznacza skip.
- **Ustalenie:** Podaje API_TEST_MYSQL_DSN bez sprawdzenia nazwy bazy, ale operacje opisane w tym teście dotyczą tabel tymczasowych i połączenia; dla zgodności i bezpieczeństwa nadal ustawić dedykowaną DB. Reflection omija konstruktor ChatService i nie testuje pełnej inicjalizacji/usług sieciowych.
- **Zależności do sprawdzenia:** `src/ChatService.php`.
- **Granica dowodu:** Pełny odczyt, bez uruchomienia MySQL; test sprawdza tylko wycinek metod

### `tests/MySqlIntegration/MySqlEmployeeAssignmentServiceTest.php`

**Blob:** `2d90c5246a3e6d4c5424a433b3cbde0e7177d6b5` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 293 linii.

- **Ustalenie:** Linie 19-55: przypisanie 75% do huba i odmowa kolejnych 30% (oczekiwany RuntimeException) oraz zwolnienie jedynego aktywnego przypisania; nazwa „all active rows” nie dowodzi wielu wierszy. Serwis liczy sumę alokacji i blokuje >100% (src/Employee/EmployeeAssignmentService.php:470-493).
- **Ustalenie:** Linie 57-125: owned pipeline, przypisanie i release; busy technical_staff dopuszczony dla pipeline z active/degraded/critical/leak, odrzucony dla building/disabled/suspended/servicing/damaged i dla huba. Polityka w src/Employee/EmployeeAssignmentService.php:8-9,380-395,432-450,461-468; brak testu obcego właściciela, innych ról i nieaktywnej relacji.
- **Ustalenie:** Linie 128-215: dwa procesy na osobnych połączeniach, file gate; po 60% na dwa huby, dokładnie jeden sukces i suma aktywnych alokacji 60. Worker tests/fixtures/employee_assignment_concurrent_worker.php:25-55 czeka na gate; serwis GET_LOCK i FOR UPDATE + transakcja (src/Employee/EmployeeAssignmentService.php:47-69,549-600). Gate nie gwarantuje równoczesnego wejścia do blokady; test nie sprawdza komunikatu/rodzaju porażki ani exit code.
- **Ustalenie:** Linie 217-292: rodzic blokuje pipeline, worker startuje, rodzic przełącza status na disabled i commit; asercja odmowy oraz braku aktywnego przypisania. Serwis czyta status FOR UPDATE (src/Employee/EmployeeAssignmentService.php:432-450). Kontrola gotowości workera dotyczy chwili przed wywołaniem serwisu (tests/fixtures/pipeline_assignment_concurrent_worker.php:25-47); brak twardej bariery dowodzącej, że worker już czeka na rekord.
- **Ustalenie:** Linie 9-16: osobny teardown usuwa employee_assignments/state/source_links dla player_id. Każda metoda wywołuje EmployeeSystemBootstrap::ensure (21,43,59,88,130,219); bootstrap może globalnie tworzyć/migrować tabele i seedować role/efekty (src/EmployeeSystemBootstrap.php:158-190,192-272), a baza w setUp także uruchamia DDL. Baza: tests/MySqlIntegration/MySqlIntegrationTestCase.php:11-35 łączy PDO z config/database.php:3-8 bez bramki izolacji, losuje ID, wykonuje DELETE jeszcze w setUp, czyści $_SESSION i wyłącza GameLog; konstruktor WellPipelineService uruchamia DDL/migracje (src/WellPipelineService.php:40-55,57-133). tearDown:37-76 ponawia DELETE. Nie uruchomiono PHP, SQL ani testów.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `config/database.php`, `src/init.php`, `src/WellPipelineService.php`, `src/EmployeeSystemBootstrap.php`, `src/Employee/EmployeeAssignmentService.php`, `tests/fixtures/employee_assignment_concurrent_worker.php`, `tests/fixtures/pipeline_assignment_concurrent_worker.php`.
- **Granica dowodu:** Statyczny odczyt; bez wykonania PHP/SQL/testów; semantyka izolacji, harmonogram procesów i ostateczny stan globalnych seedów niezweryfikowane.

### `tests/MySqlIntegration/MySqlEmployeeHrConcurrencyTest.php`

**Blob:** `8294a13c1939f672c0fac28fd55bde48d4d5b787` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 703 linii.

- **Ustalenie:** Linie 16–39: parent::setUp łączy się z konfigu i usuwa śledzone ID bez bramki izolacji (MySqlIntegrationTestCase:11–35,49–77); EmployeeSystemBootstrap::ensure i zapis globalnych feature_negotiations/negotiation_rounds/negotiation_offer_weight, odtworzenie w tearDown tylko po poprawnym setUp; nie uruchamiać na współdzielonej bazie.
- **Ustalenie:** Linie 42–303: 12 scenariuszy ×20 iteracji; dwa osobne procesy PHP są naprawdę uwalniane wspólną bramką (548–621, worker:25–42), więc to testy race, nie sekwencja; asercje finalnego stanu sprawdzają rundy, salary/event, lock ticka, jednorazowe opłaty/receipt, rolę/rekrutację i odejście.
- **Ustalenie:** Linie 74–129: wyścigi offer/deadline i raise/deadline sprawdzają spójność stanu DB; w offer/deadline nie sprawdzają obu ok ani konkretnego zwycięzcy, jedynie dopuszczają 0/1 rundę skorelowaną ze statusem.
- **Ustalenie:** Linie 149–220, 250–275: bonus/renewal wymagają obu sukcesów i jednej opłaty/receipt; hiring/recruitment jednego sukcesu i jednego aktywnego rekordu; bonus+settlement dopuszcza 0/1 bonus receipt, ale oczekuje pojedynczego kosztu settlement.
- **Ustalenie:** Linie 358–386, 403–445, 667–696: fixture wstawia players, technical_staff, employee_state, kontrakt i kandydatów; cleanupPlayer usuwa wiele tabel według player_id i bank_transactions po stronach transakcji; uruchomienie cyklu odejścia (worker:76–81) skanuje po last_morale_cycle_id, nie jest jawnie zawężone do player_id (EmployeeDepartureService:22–48).
- **Ustalenie:** Linie 554–620: readiness/gate i dwa procesy, lecz odczyt stdout przed stderr jest sekwencyjny i może się zablokować przy pełnym stderr; finally usuwa pliki bramki, ale nie zamyka/terminuje procesów i potoków na wcześniejszym wyjątku.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `tests/fixtures/employee_hr_concurrent_worker.php`, `src/init.php`, `src/EmployeeSystemBootstrap.php`, `src/Employee/EmployeeSystemConfigService.php`, `src/HR/EmployeeDepartureService.php`, `src/HR/EmployeeNegotiationService.php`, `src/HRService.php`, `config/database.php`.
- **Granica dowodu:** Pełny odczyt 703 linii testu, klasy bazowej i workera; nie uruchamiano PHP/MySQL/CRON z uwagi na destrukcyjny setUp. Rzeczywiste wyniki, schema/FK, odporność na deadlock oraz pełne ścieżki delegowanych usług wymagają izolowanego środowiska.

### `tests/MySqlIntegration/MySqlEmployeeRoleEffectServiceTest.php`

**Blob:** `1bae2353399263e8ddd04bcefe685bf5fdb52f5f` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 142 linii.

- **Ustalenie:** Dwa testy MySQL badają role i bonus managera logistyki oraz tworzenie brakującego employee_state przy batch calculation, lecz przed seedem gracza wywołują EmployeeSystemBootstrap::ensure($db) (linie 25,84).
- **Ustalenie:** P0 warunkowo: test dziedziczy niechroniony MySqlIntegrationTestCase; EmployeeSystemBootstrap::ensure wykonuje CREATE TABLE oraz potencjalny ALTER TABLE i UPDATE seeda schematu. Przy błędnym DSN skutki wykraczają poza usuwane ID; nie uruchamiać bez fizycznej izolacji DB i twardej bramki przed bootstrapem.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `src/EmployeeSystemBootstrap.php`, `src/Employee/EmployeeRoleEffectService.php`, `config/database.php`.
- **Granica dowodu:** Pełny odczyt testu i wskazanie operacji bootstrapu statycznie; bez wykonania MySQL

### `tests/MySqlIntegration/MySqlEmployeeStateServiceTest.php`

**Blob:** `7332cfbefb6709f7c0c3be808fb803e3edf16ebf` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 170 linii.

- **Ustalenie:** Trzy testy MySQL badają idempotentny employee_state, kanonizację board→technical i odrzucenie błędnego linku; za każdym razem wywołują EmployeeSystemBootstrap::ensure($db) przed seedingiem.
- **Ustalenie:** P0 warunkowo: bootstrap może wykonywać CREATE/ALTER/UPDATE schematu, a wspólna baza testowa z config/database.php nie ma globalnego zabezpieczenia przed produkcją; nie uruchamiać bez twardej izolacji.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `src/EmployeeSystemBootstrap.php`, `src/Employee/EmployeeStateService.php`, `config/database.php`.
- **Granica dowodu:** Pełny odczyt, bez uruchomienia PHP/MySQL

### `tests/MySqlIntegration/MySqlHubAssignmentServiceTest.php`

**Blob:** `1a22dc7ee621c39633f7df6dc9999ef99f8a6499` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 154 linii.

- **Ustalenie:** Test rzeczywiście wywołuje HubAssignmentService::transferWell() i po transferze oczekuje natychmiastowego statusu active dla nowego huba (22–50). Wspiera statyczny wniosek o rozbieżności z historycznym wymaganiem ~2 ticków, lecz nie uruchomiono go teraz i nie sprawdza efektu tick/finanse.
- **Ustalenie:** Test opłaty przypisania oczekuje access_fee=0 i brak zmiany cash, bo opłatę przeniesiono na akwizycję (63–92). Nazwa testAssignWellDeductsAccessFeeFromPlayerCash jest myląca; nie przyjmować nazwy jako reguły biznesowej. Dziedziczy niechroniony MySqlIntegrationTestCase.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `src/HubService.php`, `src/HubAssignmentService.php`, `config/database.php`.
- **Granica dowodu:** Pełny odczyt, bez wykonania testu MySQL i ticku

### `tests/MySqlIntegration/MySqlHubIncidentServiceTest.php`

**Blob:** `7e9c43d1d94dd6c5796e41c92e61e7136865d445` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 112 linii.

- **Ustalenie:** Trzy testy MySQL: HSE obniża mnożnik ryzyka przez wywołanie prywatnej metody Reflection, hub paused nie generuje incydentu, lista zdarzeń filtruje event_type i paginuje.
- **Ustalenie:** Dziedziczy niechroniony MySqlIntegrationTestCase, który przed testem używa config/database.php i wykonuje DELETE; nie uruchamiać bez izolowanego DSN. Test ryzyka nie losuje faktycznych incydentów ani nie weryfikuje rozliczenia finansowego.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `src/HubService.php`, `src/HubIncidentService.php`, `config/database.php`.
- **Granica dowodu:** Pełny odczyt, bez wykonania MySQL

### `tests/MySqlIntegration/MySqlHubTickServiceTest.php`

**Blob:** `6ef976c1abe242d284ab587b30f3d1d2df876b34` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 173 linii.

- **Ustalenie:** Linie 10-64: processTick dla pojedynczego huba, wynik (brak overload/incydentu, dodatni processing/wear, zerowa strata, spadek kondycji), ręczny persistTickResult z datą stałą i SELECT stanu huba oraz najnowszych stats; nie uruchamia pełnego globalnego ticka ani generatora incydentów. src/HubTickService.php:70-216 wylicza wynik, a src/Hub/TickPersistTrait.php:15-130 zapisuje hub i telemetrię.
- **Ustalenie:** Linie 66-84: porównuje wear i nową kondycję dla huba new vs used; wynik processTick pozostaje bez zapisu.
- **Ustalenie:** Linie 86-114: ręcznie podany efekt hub_throughput_pct=20 i porównanie real_capacity_bph po zapisie; nie integruje systemu pracowników ani realnych przypisań, mimo „Employee” w nazwie. src/HubTickService.php:82-88 oraz src/Hub/TickPersistTrait.php:62-67.
- **Ustalenie:** Linie 116-155: wcześniej wstawiony duplikat telemetryczny, transakcja zewnętrzna, oczekiwane false i niezmienione pola huba po commit; to realny test ścieżki savepoint/rollback, ale nie wstrzyknięta awaria DB. src/Hub/TickPersistTrait.php:35-44,78-86,117-130.
- **Ustalenie:** Linie 157-172: 0.004 zaokrągla się do zera, 0.006 do 0.01 w buforze; asercje DB i zwrotu. Baza: tests/MySqlIntegration/MySqlIntegrationTestCase.php:11-35 łączy PDO z config/database.php:3-8 bez bramki izolacji, losuje ID, wykonuje DELETE jeszcze w setUp, czyści $_SESSION i wyłącza GameLog; konstruktor WellPipelineService uruchamia DDL/migracje (src/WellPipelineService.php:40-55,57-133). tearDown:37-76 ponawia DELETE. Nie uruchomiono PHP, SQL ani testów.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `config/database.php`, `src/WellPipelineService.php`, `src/HubTickService.php`, `src/Hub/TickPersistTrait.php`, `src/Hub/TickCalculationsTrait.php`, `src/HubService.php`.
- **Granica dowodu:** Brak wykonania PHP/SQL/testów; aktualny schemat, zaokrąglenia sterownika i warunki incydentów niezweryfikowane runtime.

### `tests/MySqlIntegration/MySqlIncidentImmunityDeltaHoursTest.php`

**Blob:** `7bb6664cd4432927c2a875180446cac2ca07098d` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 85 linii.

- **Ustalenie:** Dwa testy DB porównują 4h catch-up (10+48 ticków) z normalnym krokiem 5 min (+1), przy transport_incident_mult=0 wyłączając losowy incydent; nie badają dodatnich mnożników ani faktycznej pracy CRON.
- **Ustalenie:** Dziedziczenie po MySqlIntegrationTestCase oznacza destrukcyjne DELETE w setUp/tearDown na bazie wskazanej przez config/database.php, bez globalnej bramki nazwy DB; zachować test wyłącznie dla izolowanej bazy.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `src/IncidentService.php`, `config/database.php`.
- **Granica dowodu:** Pełny odczyt, nie wykonano PHP/MySQL; nie jest dowodem na aktualne wyniki runtime

### `tests/MySqlIntegration/MySqlIncidentOngoingDropAutoRepairTest.php`

**Blob:** `b88a39c064a6c242e59e0e975c884cede4cdea9f` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 87 linii.

- **Ustalenie:** Dwa testy DB sprawdzają brak trwałego spadku produkcji dla micro/minor auto_repair=1 i 50% trwały spadek dla medium auto_repair=0, przez getOngoingProdDrop i mapę gracza.
- **Ustalenie:** Dziedziczy MySqlIntegrationTestCase, którego setUp czyta config/database.php i wykonuje DELETE dla losowego ID przed weryfikacją odizolowania DB (linie 15–25,49–76); nie wolno uruchamiać przy konfiguracji produkcyjnej. Lokalny cleanupIncidents łapie każde Throwable, maskując błąd sprzątania.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `src/IncidentService.php`, `config/database.php`.
- **Granica dowodu:** Pełny odczyt testu i bazy pomocniczej, bez uruchomienia MySQL/ticku

### `tests/MySqlIntegration/MySqlMarineDeliverySectionTest.php`

**Blob:** `394534452c53e7a2c0902e95a7bfeddda24f35d2` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 329 linii.

- **Ustalenie:** Test na prawdziwym PDO/MySQL: setUp tworzy MarineDeliveryService, fixture INSERT portu i dostawy, tearDown usuwa port_queue/marine_deliveries dla gracza i port po ID (linie 19–75); baza wspólna z MySqlIntegrationTestCase łączy przez config/database.php i kasuje rekordy już w setUp (linie 11–24, 49–77 klasy bazowej).
- **Ustalenie:** Asercje sprawdzają przejście departing→in_transit, po ETA waiting_for_port, licznik i wpis kolejki ze statusem/objętością; parametr failure_reduction=0 wyłącza losowe incydenty w tych scenariuszach (linie 81–137). Pętla 25 przybyłych rejsów z deltaHours=24 i failure_reduction=1 oczekuje każdorazowo waiting_for_port, usuwając kolejkę i dostawę w każdej iteracji (linie 140–174).
- **Ustalenie:** Przy braku portu w regionie 997 test wykonuje globalne DELETE FROM ports WHERE region_id = 997, a następnie dopuszcza zarówno delayed, jak i waiting_for_port przy fallbacku; nie dowodzi ściśle braku portu/fallbacku i może usuwać cudze rekordy (linie 180–212).
- **Ustalenie:** Test pełnej kolejki wstawia rekord z delivery_id seed+99 bez tworzenia odpowiadającej dostawy, wymaga delayed i delay_ticks≥1; test izolacji per gracz wstawia wpis obcego playerId bez seedPlayer, wymaga waiting_for_port i jednego wpisu gracza, a obcy wpis kasuje dopiero na końcu testu (linie 218–252, 287–328). Obydwa inserty zależą od ograniczeń FK/schema; wyjątek przed sprzątaniem obcego wpisu może zostawić dane.
- **Ustalenie:** Dwa testy liczników sprawdzają zero po konstrukcji i po przetworzeniu gracza bez dostaw, nie sprawdzają wszystkich liczników po realnych incydentach/stratach (linie 258–280). Port jest UPSERT-owany po losowym ID i usuwany w tearDown; brak globalnej bramki testowej, więc bez izolowanej bazy ryzyko kolizji/destrukcji (linie 40–49, 26–34).
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `config/database.php`, `src/PortService.php`, `src/MarineDeliveryService.php`, `src/Tick/MarineDeliverySection.php`.
- **Granica dowodu:** Pełny odczyt 329 linii testu i klasy bazowej; nie uruchamiano PHP, MySQL, SQL ani CRON; zachowanie serwisów, schema/FK, zegar DB i faktyczne wyniki pozostają niezweryfikowane.

### `tests/MySqlIntegration/MySqlMarineDeliveryServiceTest.php`

**Blob:** `1aa18abcfc6a18e1f2278cda9f4ec54efbf22bb5` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 312 linii.

- **Ustalenie:** Fixture: setUp wywołuje bazowy setUp, tworzy MarineDeliveryService i ustawia portId=seed+10; seedPort robi INSERT/ON DUPLICATE KEY UPDATE do ports dla regionu 77 (19–53). Scenariusze tworzą gracza i odwiert tankowcowy przez bazowe seedPlayer/seedWell, przekazują do serwisu tablicę region_id i pustą konfigurację (59–159). Własny tearDown usuwa kolejkę i dostawy gracza oraz port seed+10, łapie wszystkie wyjątki bez sygnału, potem woła cleanup bazy (26–35).
- **Ustalenie:** Asercje tworzenia: ID >0 (59–69); rekord w marine_deliveries z player_id, well_id, status='departing' i objętością 60±0.001 (71–89); przy seedowanym porcie zgodność port_id (91–106); dla regionu 998 sprawdza wyłącznie obecność klucza port_id, nie NULL ani brak portu (108–125). ETA > departure, departure > czas rozpoczęcia minus 10 sekund (127–146).
- **Ustalenie:** Asercje odczytu: dwie aktywne dostawy o statusie departing i właściwym graczu (152–169); po ręcznym UPDATE statusu delivered brak aktywnych (171–185); obcy identyfikator ma pustą listę (188–201); historia po ręcznym oznaczeniu jednej dostawy liczy jeden wpis delivered, a limit=3 zwraca trzy z pięciu (207–243), bez kontroli kolejności/kompletności innych pól.
- **Ustalenie:** Purge orphan: test ręcznie ustawia delayed, NULL port_id i ETA 13 godzin wstecz, potem rzeczywista metoda ma zwrócić 1, zachować wiersz ze statusem lost, incident_type=orphan_purge i niepustym delivered_at (245–269). Suma inTransit: 40+35=75±0.01, delivered daje 0±0.001, nieistniejący ID daje 0±0.001 (275–310); brak testu CRON/ticku i realnego dotarcia dostawy.
- **Ustalenie:** Ryzyko uruchomienia: MySqlIntegrationTestCase 11–35 łączy się z bazą z config/database.php, już w setUp kasuje zestaw ID, zeruje sesję/wyłącza GameLog i tworzy WellPipelineService (możliwy DDL); 37–77 ponownie usuwa dane, bez transakcyjnej izolacji. Port seed+10 nie jest w trackedIds (82–94); własny cleanup wyjątków nie zgłasza. Nie wykonywano suite ani DB.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `src/MarineDeliveryService.php`, `src/PortService.php`, `src/WellPipelineService.php`, `config/database.php`.
- **Granica dowodu:** Pełny odczyt 312 linii; serwis i schemat niezweryfikowane w pełni, zachowanie runtime oraz czystość bazy niepotwierdzone.
- **Granica dowodu:** Nie wykonywano PHP, SQL, MySQL, CRON ani E2E; asercje są opisem kodu testu, nie wynikiem testu.

### `tests/MySqlIntegration/MySqlMoraleServiceTest.php`

**Blob:** `f2bf56a1123df57ecc3ebb8bb13a3d5f5512fa4b` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 290 linii.

- **Ustalenie:** Fixture setUp: bazowy setup MySQL, seedPlayer, seedTechnicalWorker z menedżerem/rolą, ensureState(EmployeeRef), ustawienie canonical morale=50 przez UPDATE employee_state i asercję rowCount=1 (13–21,250–263); brak własnego tearDown. Base 11–35 łączy z config/database.php, kasuje trackedIds jeszcze przed testem, resetuje sesję, wyłącza log i konstruuje WellPipelineService; cleanup w tearDown 37–77 usuwa wskazane ID, lecz nie obejmuje literalnie employee_state, employee_raise_requests, employee_strikes ani bank_transactions, więc zależy od FK/serwisów/schema i może pozostawić ślady. Bazowy seedTechnicalWorker 180–194 i 292–334 może utworzyć globalną rolę technical.
- **Ustalenie:** MoraleService::modifyMorale: z 50 +10 oczekuje 60 oraz jednej historii change_amount=10/reason='bonus.granted' (23–33); z 95 +20 oczekuje 100 i log +5 (35–46); z 10 -15 oczekuje 0 i log -10 (48–59). Helper canonicalMorale czyta employee_state, a nie jedynie zwrot serwisu (281–289).
- **Ustalenie:** Bonus: grantTechnicalBonus(100,5) ma success=true, morale 55, suma cash+bank_balance o 100 mniejsza oraz dokładnie jedna bank_transactions typu hr_bonus/reference technical_staff dla pracownika (61–83); nie sprawdza pełnego audytu finansowego ani równoległego wywołania.
- **Ustalenie:** Strajk: zapisuje cztery klucze globalnej konfiguracji i przywraca je w finally (84–126), ustawia employee_state morale=30/dispute/support=70/workload=80, wywołuje processEscalations o 10:00 i 10:05; asercje threats_started>=1/status threat, strikes_started>=1, jedna active z jednym członkiem, canonical on_strike; po closeByAgreement 10.0 brak aktywnych, relation normal, morale 40. ForceActiveForTesting przy bazowych dobrych parametrach zwraca jednego członka, jeden aktywny strajk, poprawne ID, on_strike i morale 30 (129–156). To są agregaty >=1, nie dowód izolacji od innych graczy.
- **Ustalenie:** Wniosek o podwyżkę: ustawia raise_requested i NULL datę, processEscalations tworzy jeden request, ręcznie ustawia postponed/deadline, drugi run ma 0 nowych; asercje utrwalonego current_salary, requested_salary=round(1.1*salary,2), negotiated_salary=NULL, reason_code=low_morale, postponed_count=0, status=postponed (158–194). Acceptance: tworzy wniosek w chwili '+1 day', podwyższa live salary ponad requested o 1000, acceptFull ma zostawić wyższe live salary i trait_loyalty, zwrócić to samo salary; ponowne wywołanie tym samym tokenem ma idempotent=true i loyalty_modifier>0 (195–249). Test zależy od względnego zegara '+1 day', nie sprawdza wszystkich skutków/rollbacku.
- **Ustalenie:** Ryzyko: processEscalations działa na bazie bez filtra gracza widocznego w teście (107,111,172,177,208), a globalny config->save modyfikuje konfigurację wspólną (89–94;125), więc nie uruchamiać na współdzielonej bazie. Wyjątek przed wejściem do try po save mógłby ominąć przywrócenie; wyjątki cleanup bazowego również ryzykiem. Żadnych testów nie wykonano.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `src/init.php`, `src/HR/EmployeeStrikeService.php`, `src/HR/EmployeeBonusService.php`, `src/HR/EmployeeRaiseRequestService.php`, `src/Employee/EmployeeStateService.php`, `src/Employee/EmployeeSystemConfigService.php`, `src/HR/MoraleService.php`, `config/database.php`.
- **Granica dowodu:** Pełny odczyt 290 linii oraz bazowego TestCase; zależności usług, DDL/FK i rzeczywiste wyniki MySQL niepotwierdzone.
- **Granica dowodu:** Nie uruchamiano PHP/DB/SQL/CRON/E2E; ryzyko pozostałości danych wymaga weryfikacji schematu i izolacji.

### `tests/MySqlIntegration/MySqlOffshoreTransportServiceTest.php`

**Blob:** `95a629b0653474f19ed0453ddc45c4e6f19ee112` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 241 linii.

- **Ustalenie:** Rzeczywisty OffshoreTransportService przez PDO: seed dwóch odwiertów tankowiec/rurociag, podwójne ensureConfigs i asercja dokładnie jednego configu small; odczyt getConfigsByWellIds testuje klucz po wellId, wartości decimal jako stringi i brak nieznanego wellId (linie 13–57).
- **Ustalenie:** processTick z zerowym incident_risk_mult sprawdza trzy rejsy, bilans 90 bbl, koszt 2400, brak incydentów i wpisów logu; przy 200 rejsach i bardzo wysokim ryzyku sprawdza jedynie dodatnią stratę, niepustą listę z typem/indeksem/stratą oraz dodatnie sumy w tabeli logów (linie 64–141).
- **Ustalenie:** Dalsze przypadki sprawdzają bilans delivered+lost=60 i koszt zależny od liczby rejsów, pusty input oraz bilans i 100 rejsów przy politicalRisk=4 (linie 147–197, 203–240). Wynik result1 dla politicalRisk=1 jest wyliczany, ale nigdzie nie porównywany z result4: nazwa testu sugeruje wzrost incydentów, którego asercje nie dowodzą (linie 218–239).
- **Ustalenie:** Scenariusz ryzyka maksymalnego jest probabilistyczny bez kontroli/odtworzenia RNG, chociaż próba 200 rejsów redukuje szansę fałszywego braku incydentów; scenariusz polityczny również losowy. Test usuwa wszystkie logi danego well_id między wywołaniami (linia 224), potencjalny wpływ na współdzieloną bazę. Cleanup klasy bazowej kasuje logi i configi po well_id (linie 49–66 klasy bazowej).
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `config/database.php`, `src/OffshoreTransportService.php`.
- **Granica dowodu:** Pełny odczyt 241 linii testu i klasy bazowej; PHP/MySQL/SQL/CRON nie uruchamiano, nie potwierdzono schematu ani implementacji serwisu; brak izolowanej bazy.

### `tests/MySqlIntegration/MySqlPipelineHseIntegrationTest.php`

**Blob:** `3f21ac1374c2e3bb003c38817448063f338544a8` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 123 linii.

- **Ustalenie:** Dwa testy MySQL sprawdzają liczbę nadzorowanych jednostek przed/po założeniu pipeline i sekwencyjnie porównują zużycie/loss pipeline z HSE oraz bez bonusu; resetują dane między porównaniami.
- **Ustalenie:** Dziedziczy MySqlIntegrationTestCase z niechronionym połączeniem do config/database.php i DELETE w setUp; PipelineSection::process może zapisywać dane. Porównanie jednego ziarna/ścieżki nie jest testem pełnego bilansu transportu ani konkurencji ticków.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `src/init.php`, `src/WellPipelineService.php`, `src/Tick/PipelineSection.php`, `config/database.php`.
- **Granica dowodu:** Pełny odczyt testu, bez uruchomienia PHP/MySQL

### `tests/MySqlIntegration/MySqlPipelineSectionTest.php`

**Blob:** `26d6e91d91f32cf3260d238afc8471c8c24959f4` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 331 linii.

- **Ustalenie:** Linie 11–43, 45–79 i 312–329: realne wywołania WellPipelineService i asercje listy outbound, blokady zakupów przy paused/maintenance (saldo i brak rekordu) oraz dwóch odrębnych outbound; testy nie zastępują metody kopiowaną formułą.
- **Ustalenie:** Linie 81–141 i 143–214: PipelineSection::process sprawdza degradację, stratę, status, statystyki ticka oraz brak zdarzenia zmiany statusu przy obsadzie; tablica staffing w l. 172–187 jest wstrzyknięta ręcznie, a sam rekord technical_staff nie dowodzi pobrania przydziału z produkcyjnego przepływu obsady (src/Tick/PipelineSection.php:167–176).
- **Ustalenie:** Linie 216–263: wymuszone ryzyko eksplozji i asercje oilLostBbl, oilLostByHubBbl, unavailablePipelineIds i industrial_disasters; nie sprawdza odjęcia od rzeczywistego magazynu ani integracji całego ticka. Linie 265–310: zakończenie budowy i czas aktywny sprawdzone przez ReflectionProperty na prywatnym polu oraz wynik zużycia, nie przez pełny tick.
- **Ustalenie:** Blokada wykonania: tests/MySqlIntegration/MySqlIntegrationTestCase.php:11–35 otwiera PDO z config/database.php i uruchamia cleanupTrackedIds przed testem, a l. 37–76 usuwa rekordy ponownie po teście; brak weryfikacji izolowanej nazwy DB przed DELETE. Losowe ID 900000000–900999900 (l. 23, 82–93) mogą kolidować; sam test zapisuje UPDATE i INSERT przez fixture/service. Nie uruchomiono SQL ani CRON.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `config/database.php`, `src/init.php`, `src/WellPipelineService.php`, `src/Tick/PipelineSection.php`, `src/WellService.php`.
- **Granica dowodu:** Odczyt statyczny; nie uruchamiano MySQL/PHPUnit/ticka; DDL i schemat produkcyjny niezweryfikowane na izolowanej bazie.

### `tests/MySqlIntegration/MySqlPortSectionTest.php`

**Blob:** `751993fa0bc345d835cd4ae794fc7e6e4ea813e1` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 251 linii.

- **Ustalenie:** Linie 17-32,39-76: setUp po bazowym destrukcyjnym cleanup konstruuje MarineDeliveryService, który konstruuje PortService i odpala CREATE TABLE / możliwe globalne ALTER wells (src/MarineDeliveryService.php:27-31,53-116; src/PortService.php:20-50); port seed+10, dostawy i kolejka w żywej DB, lokalny teardown DELETE po playerId/portId z połkniętymi błędami.
- **Ustalenie:** Linie 83-137: ręcznie zasiane waiting_for_port/waiting, process() zwraca 80 bbl, liczniki i koszt; osobne testy SELECT status delivered i queue done. src/Tick/PortSection.php:47-123,191-241 rzeczywiście modyfikuje statusy i sumuje wyniki, ale nie zapisuje tabeli storage ani cash; nazwa „ToStorage” oznacza wartość zwracaną dla dalszego kodu, nie potwierdzony zapis magazynu/potrącenie opłat.
- **Ustalenie:** Linie 143-175: pełny magazyn bez dostawy, a przy 90/100 częściowo kredytuje 10 z 50; asercja nie sprawdza pozostałych 40 bbl w kolejce ani kosztu częściowego (implementacja src/Tick/PortSection.php:147-190).
- **Ustalenie:** Linie 182-249: pusta kolejka, koszt 60×2, liczniki początkowe, port closed bez dostawy; brak testu przepustowości per port, 10-entry LIMIT, idempotencji/konkurencji, deliveredByWell i degradacji/awarii. src/Tick/PortSection.php:55-115,228-230.
- **Ustalenie:** Ważny skutek uboczny: process() po obsłudze wykonuje globalne UPDATE ports bez player/port filter (src/Tick/PortSection.php:114-115,248-279), może zmienić status również obcych portów i ponownie otworzyć closed z pustą kolejką; test closed nie asertuje statusu portu po process. Baza: tests/MySqlIntegration/MySqlIntegrationTestCase.php:11-35 łączy PDO z config/database.php:3-8 bez bramki izolacji, losuje ID, wykonuje DELETE jeszcze w setUp, czyści $_SESSION i wyłącza GameLog; konstruktor WellPipelineService uruchamia DDL/migracje (src/WellPipelineService.php:40-55,57-133). tearDown:37-76 ponawia DELETE. Nie uruchomiono PHP, SQL ani testów.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `config/database.php`, `src/WellPipelineService.php`, `src/MarineDeliveryService.php`, `src/PortService.php`, `src/Tick/PortSection.php`.
- **Granica dowodu:** Statyczna analiza, bez testów/DB; skutki globalnego UPDATE wynikają z SQL, nie z obserwacji serwera.

### `tests/MySqlIntegration/MySqlPortServiceTest.php`

**Blob:** `bd49d2f22b21434cc0e738b1de15103839287452` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 252 linii.

- **Ustalenie:** Linie 19-33,40-59: PortService konstruktor już wywołuje ensureSchema (src/PortService.php:20-50), dwie systemowe sztuczne porty seed+10/+11 ON DUPLICATE KEY UPDATE mogą zmienić kolidujący istniejący rekord; teardown usuwa queue i ports po portId (połyka błędy), poza zakresem trackedIds bazy. Baza: tests/MySqlIntegration/MySqlIntegrationTestCase.php:11-35 łączy PDO z config/database.php:3-8 bez bramki izolacji, losuje ID, wykonuje DELETE jeszcze w setUp, czyści $_SESSION i wyłącza GameLog; konstruktor WellPipelineService uruchamia DDL/migracje (src/WellPipelineService.php:40-55,57-133). tearDown:37-76 ponawia DELETE. Nie uruchomiono PHP, SQL ani testów.
- **Ustalenie:** Linie 65-70: testEnsureSchemaPasses tylko sprawdza brak wyjątku i assertTrue(true), nie strukturę DDL; konstruktor już wcześniej wykonał ensureSchema.
- **Ustalenie:** Linie 76-117: aktywny port regionu i preferencja active nad overloaded; „ReturnsNullWhenNoActivePort” asertuje jedynie null LUB dowolną tablicę, więc nie weryfikuje braku portu/fallbacku. Implementacja src/PortService.php:98-118 ma fallback losowego globalnego active (ORDER BY RAND).
- **Ustalenie:** Linie 123-175: getById istniejący/nieznany i getQueueSize empty, waiting+processing vs done. Kolejki mają fikcyjne delivery_id seed+20.. bez rekordu delivery; schemat port_queue nie definiuje FK (src/MarineDeliveryService.php:100-115), więc nie jest to pełny przepływ morski.
- **Ustalenie:** Linie 181-227: refreshStatuses przy 4/5 przełącza active→overloaded, pusta kolejka overloaded→active, closed z pełną kolejką pozostaje closed; brak testu progu poniżej i ponownego otwarcia closed poniżej połowy. Metoda wykonuje globalne UPDATE wszystkich ports, także niezwiązanych z ID testu (src/PortService.php:148-177).
- **Ustalenie:** Linie 234-251: getAllWithQueueStats odnajduje seedowany port i jedną waiting; nie izoluje całego wyniku od innych portów ani braków regionu. src/PortService.php:230-253.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `config/database.php`, `src/WellPipelineService.php`, `src/PortService.php`, `src/MarineDeliveryService.php`.
- **Granica dowodu:** Brak wykonania PHP/SQL/testów; zależność od globalnych portów/regionów i potencjalne kolizje ID nie zweryfikowane na bazie.

### `tests/MySqlIntegration/MySqlRecruitmentFlowTest.php`

**Blob:** `c61f8db4a2ab435e385c47ca3e075ddf8612a8c4` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 560 linii.

- **Ustalenie:** Klasa polega na bootstrap tests/bootstrap.php, który ładuje bazowy MySqlIntegrationTestCase; własne setUp/tearDown wywołują cleanupRecruitmentFixtures, dodatkowo baza sprząta losowe ID przez config/database.php (linie 12–46, 487–516; bootstrap 6–9; baza 11–24). Fixture ręcznie wstawiają role, dyrektorów, specjalizacje, kandydatów i headhunter_searches/candidates, a serwisy HR i Headhunter są tworzone bez konstruktora przez Reflection i ręczny PDO/playerId; prywatne doHire wywoływane przez Closure::bind w ręcznej transakcji (linie 372–485).
- **Ustalenie:** Asercje hiring: specjalista działu zatrudniony jako board staff mimo obsadzonego dyrektora, dyrektor nadal blokowany, ujemna pensja odrzucona bez częściowego board member; kandydat techniczny HR tworzy technical_staff bez nowego board/employee_contracts i z jedną transakcją hr_fee (linie 48–152).
- **Ustalenie:** Headhunter: bezpośrednie prywatne doHire sprawdza wyłącznie technical_staff albo board staff i accepted (linie 154–204), więc omija publiczny tok oferty, losowanie/warunki wywołującego. Osobne publiczne makeOffer sprawdza akceptację zapisanej kontroferty, identyczną odpowiedź po ponownym tokenie i jeden receipt, a zmieniony payload pod tym samym tokenem ma conflict (linie 206–290); nie testuje dwóch równoległych połączeń.
- **Ustalenie:** cleanupExpired(2), potem cleanupExpired(1) porównuje GLOBALNY COUNT(*) candidates przed/po, z trzema wygasłymi fixture i rollbackiem w finally (linie 292–320); nie asertuje tożsamości usuniętych kandydatów ani nienaruszenia innych rekordów, jest podatne na równoległe zmiany i interakcję z innymi wygasłymi kandydatami.
- **Ustalenie:** Test strajku zapisuje GLOBALNĄ konfigurację feature_strike_effects/multiplier i potem odtwarza snapshot w finally, wstawia employee_strikes, oczekuje wydłużenia recruitment duration i terminu headhunter (linie 322–370). Zmiana widoczna dla innych połączeń; przy awarii procesu before finally lub równoległym zapisie ryzyko trwałego nadpisania ustawień; granica czasu opiera się na time() i tolerancji 2 s.
- **Ustalenie:** cleanupRecruitmentFixtures usuwa m.in. bank_transactions po from/to playerId, receipts, recruitment_requests i role-related records, lecz role znalezione jako już istniejące są współdzielone; losowe ID roli generowane random_int i nie dodane do własnego cleanup roli (linie 418–433, 487–516). Brak gwarancji izolacji, rollbacku całej klasy lub testowej bramki DB.
- **Zależności do sprawdzenia:** `tests/bootstrap.php`, `phpunit.xml.dist`, `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `config/database.php`, `src/FinancePolicyService.php`, `src/CandidateGenerator.php`, `src/BankAccountService.php`, `src/WalletConfig.php`, `src/FinancialTransactionService.php`, `src/HRService.php`, `src/HeadhunterService.php`, `src/Employee/EmployeeSystemConfigService.php`.
- **Granica dowodu:** Pełny odczyt 560 linii testu i klasy bazowej; PHP/SQL/MySQL/CRON nie uruchamiano. Implementacje usług, pełny schemat, zachowanie transakcji/fault-injection i skutki uboczne remain otwarte; nie przypisywać wynikom statusu testów wykonanych.

### `tests/MySqlIntegration/MySqlRoadTransportServiceTest.php`

**Blob:** `425badcf1122c3db9e760f91c22a35b694f2ed00` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 556 linii.

- **Ustalenie:** Linie 13–58: sprawdza idempotencję konfiguracji tylko dla ciężarówek i odczyt po well_id, w tym wartości DECIMAL jako string. Linie 64–202: processTick sprawdza brak strat przy zerowym ryzyku, bilans wolumenu i kursów, koszt oraz pusty input.
- **Ustalenie:** Linie 101–143: przypadek max risk losuje 200 prób przy limicie 95% (src/RoadTransportService.php:706–720), asercje >0 incydentów i logów są probabilistyczne, nie w 100% deterministyczne; nie ustawia/przywraca RNG. Nie dowodzi dokładnej zgodności wolumenu logów z wynikiem, tylko dodatniość.
- **Ustalenie:** Linie 208–310: dispatchTrips sprawdza snapshots czasu/kosztu/ryzyka i brak rekordu przy zerowym wolumenie. Linie 263–296 zapisują globalną flagę feature_strike_effects przez EmployeeSystemConfigService, przywracaną w finally; awaria procesu lub kolizja równoległa może pozostawić/zmienić stan współdzielonej konfiguracji; test wstawia i usuwa employee_strikes po player_id.
- **Ustalenie:** Linie 316–450 i 518–555: asercje przejścia in_transit/delayed → crediting → delivered, recovery osieroconego crediting, ETA i agregacji. confirmCreditedTrips wywołane bez transakcji zapisu magazynu (l. 348, 399, 550), więc komentarze o atomowości z magazynem nie są tu dowodem integracyjnym.
- **Ustalenie:** Linie 456–505: aktywne/ukończone kursy i informacyjna asercja istnienia tabeli; testEnsureSchemaCreatesWellRoadTripsTable nie dowodzi CREATE na pustej bazie, bo konstruktor src/RoadTransportService.php:58–176 wykonuje CREATE TABLE IF NOT EXISTS i ewentualne ALTER TABLE przed sprawdzeniem information_schema.
- **Ustalenie:** Blokada wykonania: tests/MySqlIntegration/MySqlIntegrationTestCase.php:11–35 używa config/database.php i kasuje śledzone ID w setUp bez sprawdzenia bazy; tearDown l. 37–76 kasuje ponownie. src/RoadTransportService.php:58–176 może wykonać DDL; nie uruchomiono DB/SQL/CRON.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `config/database.php`, `src/RoadTransportService.php`, `src/Employee/EmployeeSystemConfigService.php`, `src/Tick/PlayerTickProcessingTrait.php`.
- **Granica dowodu:** Odczyt statyczny; nie uruchomiono MySQL/PHPUnit, transakcji magazynowej ani DDL.

### `tests/MySqlIntegration/MySqlTechnicalPipelineTasksTest.php`

**Blob:** `3779143c05221de8f5c6e03dbda47a1a3646aba4` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 221 linii.

- **Ustalenie:** Trzy scenariusze na realnym PDO/MySQL seedują gracza, pipeline_engineer, odwiert rurociągowy, hub i assignment, tworzą well_pipeline serwisem, zmieniają stan SQL, wywołują TechnicalTeamService::assignTask, cofają end_time w bazie i wywołują processTick (linie 9–40, 81–111, 158–203). Wspólne setUp/tearDown bazowe bez bramki izolacji wykonują DELETE (baza 11–24, 37–77).
- **Ustalenie:** Maintenance wymaga sukcesu bez klucza queued, dodatniego kosztu, statusu in_progress, a po ticku condition_pct>64, transport_loss<3.5, active, last_inspected_at i completed (linie 38–79); repair wymaga sukcesu, kosztu>0 oraz active, condition_pct≥80, mniejszej straty, wyczyszczonego damaged_at, inspection i completed (linie 110–150). Brak asercji salda/debetu lub powiadomień w tych dwóch scenariuszach.
- **Ustalenie:** Trzeci przypadek usuwa well_pipelines już po przydzieleniu maintenance, kończy tick, a następnie wymaga statusu completed i ostatniego technical_notifications.message równego tłumaczeniu pipeline_gone (linie 158–220). Nie asertuje braku pozostałych skutków ubocznych ani zachowania przy rzeczywistej sprzedaży odwiertu; usuwa pipeline bezpośrednio SQL (linia 199).
- **Ustalenie:** Fixture zależą od WellPipelineService oraz ładowania src/init.php, którego require inicjuje wiele globalnych komponentów (linie 4–5 testu, 1–70 init); TechnicalTeamService pobiera PDO przez własny konstruktor, a nie jawnie przekazane $this->db (linie 38, 110, 184). Czy trafia w to samo połączenie/konfigurację i czy cleanup obejmuje wszystkie efekty wymaga osobnej weryfikacji bez wykonywania na współdzielonej bazie.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `config/database.php`, `src/init.php`, `src/WellPipelineService.php`, `src/TechnicalTeamService.php`, `src/i18n.php`.
- **Granica dowodu:** Pełny odczyt 221 linii testu i klasy bazowej; PHP/MySQL/SQL/CRON nie uruchamiano; implementacja serwisów, schema i efekt runtime pozostają otwarte.

### `tests/MySqlIntegration/MySqlWellActionsTraitTest.php`

**Blob:** `83238debcdcf44eaac0fb853aabb118549def391` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 166 linii.

- **Ustalenie:** Tytuł sugeruje test upgradeEquipment/blowout, lecz oba testy kopiują wzór max(0, level), listę kosztów i literalne UPDATE SQL bez wywołania metod WellActionsTrait/WellTickTrait (51–103,135–164). Może przejść mimo regresji produkcyjnej implementacji; nie nazywać go integracyjną walidacją tych metod.
- **Ustalenie:** Mimo ograniczonego pokrycia dziedziczy niechroniony MySqlIntegrationTestCase i wykonuje INSERT/UPDATE/DELETE na bazie z config/database.php.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `src/init.php`, `config/database.php`.
- **Granica dowodu:** Pełny odczyt, nie uruchomiono MySQL; produkcyjne metody pozostają otwartą zależnością

### `tests/MySqlIntegration/MySqlWellLifecycleSimulationTest.php`

**Blob:** `f4cf34621c69b4bf16917205d308666bc7fcd91a` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 581 linii.

- **Ustalenie:** Linie 36–65: parent::setUp uruchamia niezabezpieczone cleanupTrackedIds (baza:11–35); fixture wells_for_sale z ON DUPLICATE KEY UPDATE, ręczne usunięcie saleWellId w tearDown (42–50), tylko po udanym setUp.
- **Ustalenie:** Linie 68–85, 103–125, 251–294: zakup odwiertu jest ręcznie symulowany UPDATE cash/INSERT wells, a limit 5 odwiertów jest sprawdzony samym COUNT pięciu ręcznie dodanych wierszy — brak wywołania WellShop::buyWell lub odmowy szóstego zakupu; nie dowodzi reguły limitu ani transakcji sklepu.
- **Ustalenie:** Linie 127–245: sekwencyjny flow usług HubAssignmentService/WellPipelineService/HubTickService: przypisanie bez access fee, koszt i budowa standard pipeline, ręczne przesunięcie finish_at, utrwalenie ticka, detach/cooldown i widoczność w getUnassignedWells. To integracja z MySQL, nie współbieżność ani realny harmonogram CRON.
- **Ustalenie:** Linie 296–419, 421–476, 478–579: testy osobnych ścieżek: niski stan gotówki bez opłaty za assignment, wymóg huba, brak środków, duplikat pipeline, cooldown także przy innym hubie, transfer dwóch wierszy, koszty heavy/light, persist tick i access_fee_paid=0.
- **Ustalenie:** Linie 22–28 i 127–140, 528–552: pozostawione komentarze/stała ACCESS_FEE_NEW_HUB=125 sugerują historyczną opłatę, lecz faktyczne asercje oczekują 0; migracja powinna opierać się na bieżących asercjach i serwisie, nie tych komentarzach.
- **Ustalenie:** Linie 271–294: pięć odwiertów wellId..wellId+4, a base cleanup usuwa jawnie tylko wellId i auxWellId, następnie wells po player_id (baza:69–76); skuteczność sprzątania i FK należy potwierdzić na izolowanej schemie.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `src/HubService.php`, `src/HubAssignmentService.php`, `src/WellPipelineService.php`, `src/HubTickService.php`, `config/database.php`.
- **Granica dowodu:** Pełny odczyt 581 linii testu i klasy bazowej; nie uruchamiano PHP/SQL/CRON z powodu destrukcyjnego setUp. Nie zweryfikowano żywej schemy ani działania WellShop, usług w bazie, ticka runtime.

### `tests/MySqlIntegration/MySqlWellPipelineServiceTest.php`

**Blob:** `7c67fe718610dc3c3ed5c2ef59e365dc24eb1d34` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 361 linii.

- **Ustalenie:** Fixture: każda metoda używa MySqlIntegrationTestCase (11–35) z żywym PDO MySQL, cleanupTrackedIds przed i po, seed losowy, reset sesji i konstrukcja WellPipelineService w bazowym setUp; bazowe seedPlayer ma 50 mln, seedWell/seedHub/seedAssignment tworzą odpowiednie rekordy, seedLegacyPipeline osobną tabelę pipelines (MySqlIntegrationTestCase 82–178,261–270). Brak lokalnego tearDown; bazowy cleanup obejmuje well_pipelines po player_id/well_id i wells/hubs/players (49–77), lecz brak gwarancji pełnego sprzątania eventów/tick_stats bez obejrzenia FK/schema.
- **Ustalenie:** createPipelineForWell: przy reinforced oczekuje normalizacji do heavy, well_id, nominal_capacity_bph=55*capacity_pct z getProfile('heavy')/100 sformatowane do 2 miejsc, koszt z tego profilu, po dwóch wywołaniach dokładnie jeden wiersz (10–39). Dla obcego gracza oczekuje [] i zero wierszy dla well_id (41–57). ensurePipelinesForPlayerWells dwukrotnie wywołane z dwoma odwiertami i legacy pipeline mają pozostawić zero well_pipelines dla gracza (59–90); nie asercjuje stanu legacy pipeline.
- **Ustalenie:** getPlayerPipelines: po seedWell, hub i assignment oraz create sprawdza jeden rekord z source_well_id, well_name/location_name, transport_type=rurociag, identyczne aliasy real_capacity_bph/capacity_bbl_h i transport_loss/transport_loss_pct, pipeline_type=standard (92–117). Test ensureSchema tylko tworzy instancję i pyta information_schema o trzy tabele well_pipelines/events/tick_stats (119–134); nie dowodzi poprawnych kolumn, indeksów ani migracji ze starszej wersji.
- **Ustalenie:** purchasePipeline: standard z hubem i przypisaniem ma success=true, typ standard, koszt 18000, 8 godzin i klucz build_finish_at; DB ma building i nie-NULL daty, cash maleje z 50 mln o 18000±0.01 (138–172). Przy cash=1 heavy zwraca insufficient_funds i brak well_pipelines (174–195); przy drugiej próbie po udanej light zwraca pipeline_already_exists (197–214), bez asercji, że drugi zakup nie pobrał pieniędzy. Outbound dla well po udanym inbound zwraca invalid_leg, cash bez zmiany i wyłącznie ['inbound'] w DB (216–248).
- **Ustalenie:** completeBuildingPipelines: ręcznie wstawiony building z finish=NOW()-1 SECOND przechodzi do active, liczba completed=1; finish=NOW()+1 HOUR pozostaje building, completed=0 (252–310). getBuildingForPlayer: ręczny building +2h ma jeden wpis/status building/seconds_remaining w (0,7200] i nazwę odwiertu; createPipelineForWell active nie pojawia się w building (314–359). Brak dowodu wywołania tick/CRON oraz testu dokładnego momentu granicznego.
- **Ustalenie:** Ryzyko uruchomienia: bazowy setUp wykonuje cleanup i konstruuje usługę z możliwym DDL przed jakimkolwiek per-test guardem (MySqlIntegrationTestCase 11–35); jest to integracja z realną bazą, nie bezpieczna makieta. Zapis seeded ID oraz cash/DDL może dotknąć współdzieloną bazę; nie wykonywano suite.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `src/WellPipelineService.php`, `src/init.php`, `config/database.php`.
- **Granica dowodu:** Pełny odczyt 361 linii i bazowego TestCase; implementacja usługi, schema/FK i rzeczywiste wyniki MySQL pozostają do odrębnej weryfikacji.
- **Granica dowodu:** Nie uruchomiono PHP, DB, SQL, CRON ani E2E; testy są opisane według asercji, a nie raportowane jako zaliczone.

### `tests/MySqlIntegration/MySqlWellShopTest.php`

**Blob:** `a21597243c0ad8794f669b227a376e2b95eb9e0d` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 112 linii.

- **Ustalenie:** Test DB weryfikuje wykluczenie odwiertu sold z limitu zakupu oraz dwa sekwencyjne upgrade z kosztami 10000 i 20000 PLN i zmianą poziomu 1→2→3; nie symuluje współbieżności dwóch połączeń, mimo opisu blokady w komentarzu.
- **Ustalenie:** Dziedziczy MySqlIntegrationTestCase (config/database.php i DELETE przed ochroną DB); identyfikatory w lokalnych interpolowanych SELECT są wyliczonymi int, nie wejściem HTTP.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `src/init.php`, `src/WellShop.php`, `src/Well.php`, `config/database.php`.
- **Granica dowodu:** Pełny odczyt, brak uruchomienia DB; literalne zależności muszą zostać zweryfikowane

### `tests/Unit/BoardroomUploadServiceTest.php`

**Blob:** `60b83dc86037d98635bbbd4ef8c6bf1954310e75` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 247 linii.

- **Ustalenie:** Linie 13–27: osobny losowy katalog /public i /private na test, rekurencyjne usuwanie w tearDown; brak bazy. Fixture obrazu 3×2 tworzy GD (34–40).
- **Ustalenie:** Linie 52–69, 139–145, 208–224: rzeczywisty BoardroomUploadService::receive, finfo/getimagesize/dekoder PNG; odrzucenie fałszywego MIME, uciętego obrazu i wadliwego base64; JPEG/WebP transkodowane do PNG.
- **Ustalenie:** Linie 71–107, 175–184, 226–233: odrzucenie metadanych, rozmiaru chunków i sumy, nadmiarowych wymiarów/pikseli oraz sukces 32 chunków; sprawdzenie 20 istniejących partów po odmowie kolejnego.
- **Ustalenie:** Linie 109–172, 196–205: odseparowanie owner/session, identyczny retry, konflikt retry, brak środkowego fragmentu, zachowanie poprzedniej publikacji i ponowienie po błędzie rename; to sekwencyjne wywołania, nie współbieżny test wyścigu flock/rename.
- **Ustalenie:** Linie 186–194 i 235–246: test obecności tłumaczeń oraz tekstowe asercje fragmentu kontrolera przed $_codexGuardStart; nie wykonuje HTTP/auth/CSRF ani nie dowodzi faktycznej kolejności bootstrapu bazy w admin/init.php.
- **Zależności do sprawdzenia:** `src/BoardroomUploadService.php`, `admin/template_editor.php`, `admin/init.php`, `lang/en/admin/template_editor.php`, `lang/pl/admin/template_editor.php`.
- **Granica dowodu:** Pełny odczyt 247 linii testu oraz powiązanego serwisu; nie uruchamiano PHP. Wymaga GD/finfo i izolowanej inspekcji działania HTTP; asercje kontrolera są tekstowe, nie E2E.

### `tests/Unit/EmployeeDashboardQueryServiceTest.php`

**Blob:** `eac27350350a2fd37fc0d9b05f24d85d9ec82ad1` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 154 linii.

- **Ustalenie:** Test nazwany Unit używa SQLite :memory: i realnego EmployeeDashboardQueryService: kanoniczny dashboard dla gracza 7, morale, 1 event, notified/read oraz paginację 25 zdarzeń na stronę 2 (19–90).
- **Ustalenie:** Seeduje obcego pracownika, ale nie obce eventy ani próbę markEventsRead/Notified z cudzym ID. Brak dowodu izolacji zapisów cudzych zdarzeń, kontroli HTTP i parytetu MySQL; etykieta Unit nie oznacza braku zależności DB.
- **Zależności do sprawdzenia:** `src/HR/EmployeeDashboardQueryService.php`.
- **Granica dowodu:** Pełny odczyt pliku; PHP/SQLite/MySQL/CRON nie uruchamiano; zależności i zachowanie runtime pozostają otwarte

### `tests/admin_dashboard_browser.cjs`

**Blob:** `bbc0d53bb05c75bc18301b43370640c2daa06d8f` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 118 linii.

- **Ustalenie:** Linie 1–27: Playwright uruchamia headless Edge, ale HTML każdego z bank/help_editor/news/tech dla pl/en generuje osobny skrypt PHP fixtures/admin_dashboard_render.php; fixture.test oraz lokalne assety i TinyMCE są przechwytywane. To render fixture, nie żywy panel/admin HTTP.
- **Ustalenie:** Linie 28–45: dla 7 szerokości (320–1536) asercja braku poziomego overflow; dla news także dokładnie 9 kart, 5 widocznych, border-top 1px i przestrzenne rozdzielenie pierwszych kart. Screenshoty są opcjonalne, nie są asercjami.
- **Ustalenie:** Linie 47–89: sztucznie dodany sidebar sprawdzany tylko pod kątem overflow; link edycji ma focus i href edit=9; wyszukiwanie, filtr pinned, zakładka, paginacja i sortowanie są sprawdzane liczbą widocznych kart i pierwszymi ID #9/#2. Usunięcie sprawdza otwarcie/zamknięcie modala, a formularz add jest ponownie wyrenderowany i przechwycony jako fixture.
- **Ustalenie:** Linie 90–109: bank sprawdza otwarcie/zamknięcie modala kredytu; tech podmienia API TechNotifApi.php na odpowiedzi HTTP 500, success:false, wadliwy JSON i 200 success:true; asercje obejmują zachowanie 2 powiadomień przy błędzie oraz 1 i licznik '1' przy sukcesie, bez realnego zapisu serwera.
- **Ustalenie:** Linie 13–14, 110–118: zbiera pageerror i oczekuje pustej listy na koniec; komunikaty PASS są tylko logiem scenariusza, nie dowodem uruchomienia w tym audycie.
- **Zależności do sprawdzenia:** `tests/fixtures/admin_dashboard_render.php`, `assets/js/modal.js`, `assets/css/admin.css`, `assets/css/admin_news.css`, `admin/news.php`, `api/internal/TechNotifApi.php`.
- **Granica dowodu:** Pełny odczyt 118 linii; node --check potwierdził wyłącznie składnię. Nie uruchomiono Playwright/PHP/E2E ani nie otwarto pełnych zależności; brak dowodu działania żywego panelu, API lub produkcyjnej bazy.

### `tests/fixtures/TransactionRaceFixtures.php`

**Blob:** `7ee490cac8d92d881e599a22ea3e51cade987163` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 81 linii.

- **Ustalenie:** Fixture testów wyścigu odrzuca połączenie, jeśli DB_NAME nie pasuje do allowlisty nazw testowych, sterownik nie jest MySQL lub SELECT DATABASE() nie jest identyczny (linie 16–28); insert dopuszcza wyliczone tabele i nazwy kolumn, rejestruje ID dopiero po udanym INSERT.
- **Ustalenie:** cleanup() sprawdza bazę ponownie, cofa otwartą transakcję i usuwa zarejestrowane rekordy według ID; ta bramka dotyczy tego fixture, NIE całego zestawu MySqlIntegrationTestCase. W razie przerwania procesu przed cleanup lub nieobjętych tabel pozostają zależności; uruchamiać tylko na odizolowanej DB.
- **Granica dowodu:** Pełny odczyt; brak wykonania SQL lub próby awarii cleanup

### `tests/fixtures/admin_dashboard_render.php`

**Blob:** `3cf8cc0693744e7a286d6f42ef223d08ba5217eb` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 48 linii.

- **Ustalenie:** CLI-only fixture (PHP_SAPI guard) renderuje syntetyczne powierzchnie bank, help_editor, news i tech_notifications z wyliczonej listy switch; nie ma rzeczywistej sesji admina, DB ani kontroli uprawnień.
- **Ustalenie:** Zmiennej surface z argv nie interpoluje bezpośrednio dowolnie: nieznana wartość kończy się wyjątkiem. Test wyglądu nie jest testem realnej autoryzacji lub finansów.
- **Zależności do sprawdzenia:** `src/i18n.php`, `templates/views/admin/bank/main.php`, `templates/views/admin/help_editor/main.php`, `templates/views/admin/news/main.php`, `templates/components/tech_notifications.php`.
- **Granica dowodu:** Pełny odczyt, brak uruchomienia PHP/przeglądarki

### `tests/fixtures/home_dashboard_render.php`

**Blob:** `e4ca3a0c6765ad16dff478ccac8f62413c436897` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 51 linii.

- **Ustalenie:** Fixture ustawia syntetyczne dane dashboardu, chatu, powiadomień oraz nawigacji i renderuje prawdziwe komponenty z trzema lokalnymi skryptami; używana przez przeglądarkowy navigation_activity_browser.cjs.
- **Ustalenie:** Wybiera tylko PL/EN z query locale (linia 5), więc ten test nie obejmuje DE; fikcyjna historia i powiadomienia nie dowodzą integracji API/DB.
- **Zależności do sprawdzenia:** `src/i18n.php`, `src/CSRF.php`, `src/GameNavigation.php`, `templates/components/game_navigation.php`, `templates/components/company_statistics.php`, `templates/components/activity_center.php`, `assets/js/home_dashboard.js`, `assets/js/dashboard_news.js`, `assets/js/game_nav.js`.
- **Granica dowodu:** Pełny odczyt fixture, bez testu renderowania/HTTP; nie ma zabezpieczenia CLI, bo test korzysta z lokalnego serwera

### `tests/fixtures/hub_incidents_render.php`

**Blob:** `7d50c1a18773b331a6dad04c0ec90fe36b2ba18c` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 132 linii.

- **Ustalenie:** CLI-only fixture z makietą incydentów, hubów, filtrów, staffing i panelu admina; wybór surface jest ograniczony do incidents/logistics/overview/list/config. Przekazuje syntetyczny stan do rzeczywistych szablonów PHP.
- **Ustalenie:** $db=null i liczne dane hardcoded oznaczają brak autoryzacji, połączenia DB i semantyki transportu; wynik renderowania nie dowodzi polityki własności hubów.
- **Zależności do sprawdzenia:** `src/i18n.php`, `src/AdminHub/ConfigFieldTrait.php`, `templates/views/technical/tabs/incidents.php`, `templates/views/logistics/sections/hub_incidents_section.php`, `templates/views/admin/logistics/main.php`.
- **Granica dowodu:** Pełny odczyt, PHP/DOM nie uruchomiono

### `tests/fixtures/legal_design_render.php`

**Blob:** `71cfd927e6ea10f99bc9778766df3a52e3f96a49` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 40 linii.

- **Ustalenie:** Renderuje rzeczywisty legal/main.php na syntetycznych permitach, aktywnych/pending/dostępnych regionach, historii i saldzie; przyjmuje locale z argv, nie pobiera danych z produkcyjnej DB.
- **Ustalenie:** Jest zabezpieczony warunkiem PHP_SAPI===cli (linia 4); zakres fixture obejmuje tylko rozgałęzienia widoku, nie prawdziwą decyzję permit, opłatę ani lokalizację backendu.
- **Zależności do sprawdzenia:** `src/i18n.php`, `src/LegalService.php`, `templates/views/legal/main.php`.
- **Granica dowodu:** Pełny odczyt, brak uruchomienia PHP lub E2E

### `tests/fixtures/logistics_design_render.php`

**Blob:** `3360f1f2f35e123b177183365ea9c6b4d2b6822f` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 115 linii.

- **Ustalenie:** CLI-only fixture przekazuje syntetyczne transporty, ofertę huba typu new/used, załogę, pipeline i historię morską do logistics/main.php; dobór locale i filtrów pochodzi z argv, bez DB.
- **Ustalenie:** Mock oferty zakupu stanowi dowód oczekiwań widoku, nie sukcesu akwizycji; mapa przykładowych wartości nie potwierdza bilansu produkcja→magazyn ani cen na backendzie.
- **Zależności do sprawdzenia:** `src/i18n.php`, `templates/views/logistics/main.php`.
- **Granica dowodu:** Pełny odczyt, brak uruchomienia PHP/Playwright

### `tests/fixtures/sabotage-view.php`

**Blob:** `4c6004a41e2e3c32e7edd3d7dfc50ed9b9c69a77` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 29 linii.

- **Ustalenie:** Fixture podstawia t/url/CSRF i syntetyczne cele, ofertę, cooldown oraz historię do rzeczywistego widoku sabotage/main.php; sprawdza kontrakt renderowania, nie autoryzację, koszty ani zapis sabotażu.
- **Ustalenie:** Brak jawnego ograniczenia PHP_SAPI do CLI w odróżnieniu od legal_design_render.php; dostępność HTTP zależy od konfiguracji document root, nie została zbadana.
- **Zależności do sprawdzenia:** `lang/pl/sabotage.php`, `templates/views/sabotage/main.php`.
- **Granica dowodu:** Pełny odczyt fixture, brak uruchomienia PHP i przeglądarki

### `tests/home_dashboard_browser.cjs`

**Blob:** `70cc824b9a190f30e0b26a30f6eb17a79f9f4ec5` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 62 linii.

- **Ustalenie:** Playwright testuje PL/EN, sześć szerokości, taby, klawiaturę, widoczność chatu i dashboardu; obie odpowiedzi HTTP news/stats są mockowane, a strona pochodzi z fixture na porcie 8768.
- **Ustalenie:** Brak DE i realnych danych/backendu; wynik nie dowodzi autoryzacji, zapisów, zachowania ticku ani parytetu produkcji. Wymaga msedge i lokalnego serwera PHP.
- **Zależności do sprawdzenia:** `tests/fixtures/home_dashboard_render.php`.
- **Granica dowodu:** node --check przeszedł; Playwright/PHP nie uruchomiono

### `tests/hub_incidents_browser.cjs`

**Blob:** `923ed6329ab231fdb603babf8e01c6d2d5ab4c27` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 87 linii.

- **Ustalenie:** Playwright sprawdza 5 powierzchni hub/incydent, PL/EN i 7 szerokości; render z CLI fixture, assets z lokalnych plików pod sztucznym hostem; asercje obejmują overflow, panele, szczegóły i paginację.
- **Ustalenie:** Nie bada DE ani prawdziwej sesji, API, ticku lub zapisu incydentu; msedge i PHP_BINARY/serwer są wymaganiami środowiska, opcjonalne screenshoty nie były wykonane.
- **Zależności do sprawdzenia:** `tests/fixtures/hub_incidents_render.php`.
- **Granica dowodu:** node --check przeszedł; Playwright/PHP nie uruchomiono

### `tests/legal_design_browser.cjs`

**Blob:** `e71c102dd3a885c2fd7c9b6e9cc7dd1ad97be6af` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 98 linii.

- **Ustalenie:** Skrypt Playwright uruchamia headless Edge (msedge), wywołuje PHP fixture legal_design_render.php przez execFileSync dla pl/en, wstrzykuje HTML do page.setContent oraz lokalne arkusze i skrypty; łącznie 2 locale × 6 szerokości 320–1440 (linie 3–22). Fixture ręcznie buduje viewData, statusy, historię, daty i atrapę tokenu CSRF, renderując szablon bez serwera/produkcyjnej bazy (fixture linie 4–40).
- **Ustalenie:** Asercje obejmują statystyki 1/6 i 1/3, brak undefined, stany dialogów dla nowego regionu, Bliskiego Wschodu, Rosji i upgrade, konkretne daty/historię, centrowanie/overflow/font; opcjonalne screenshoty PL przy zadanych szerokościach (linie 23–73). Tekstowe selektory polskie są używane także przy locale=en (linie 28, 33, 56, 62, 67, 74), więc test wymaga takich nazw fixture nawet w wersji angielskiej.
- **Ustalenie:** Klik potwierdzenia formularza Afryki przechwytuje submit przez listener i event.preventDefault, asertuje pola fixturePost csrf_token='fixture', region_id='2', action='submit_application', brak poziomego overflow i pageerror (linie 74–93). Nie wysyła POST ani nie dowodzi serwerowej walidacji CSRF, zapisu, autoryzacji czy realnego flow gracza. Test uruchomiony normalnie wymaga PHP, Playwright i msedge; w ramach audytu wykonano wyłącznie node --check (składnia poprawna).
- **Zależności do sprawdzenia:** `tests/fixtures/legal_design_render.php`, `templates/views/legal/main.php`, `src/i18n.php`, `src/LegalService.php`, `assets/css/style.css`, `assets/css/legal.css`, `assets/css/modal.css`, `assets/css/legal_design.css`, `assets/js/modal.js`, `assets/js/legal.js`, `assets/js/legal_design.js`.
- **Granica dowodu:** Pełny odczyt 98 linii testu i 40 linii fixture; tylko node --check bez uruchamiania Playwright/PHP/przeglądarki; brak wyników wizualnych i dowodu działania aplikacji live.

### `tests/logistics_design_browser.cjs`

**Blob:** `70600a2f5a773c4819b27b7547b5f0b544fd937d` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 171 linii.

- **Ustalenie:** Linie 1–32: headless Edge/Playwright renderuje pl/en przez tests/fixtures/logistics_design_render.php i synthetic fixture.test; CSS/JS z lokalnych assetów, HubApi.php?** zwraca stały mock huba z uszkodzoną studnią #57. Nie jest to live UI/API.
- **Ustalenie:** Linie 33–58: asercje 5 wierszy historii dostaw i Dostawa 6 po paginacji; modal staffing nie ma pól liczbowych, ma 2 linki record= i komunikat -40%, zamyka się Escape; modal wells ma deep link /#wg-card-57, nie ma transfer-modal i nie przelewa się w 320/390/1440 px.
- **Ustalenie:** Linie 59–75: asercje liczebności nawigacji i sekcji (po 6), hubów i rurociągów (po 2), kart rynku (10), jednostek (4), wierszy mix (3), KPI transportu (3), innych kart/wierszy; aktywny transport ma tekst PL/EN waiting for port, jest jedna kontrolka optymalizacji, brak alertów i 'Nie ustawiono'.
- **Ustalenie:** Linie 76–99: asercja braku poziomego overflow dla 320/360/390/768/1024/1440 (diagnostyka elementów tylko przy błędzie); desktop sprawdza, że detal huba jest pod listą, a wskaźniki rurociągów mają tę samą pozycję Y. Screenshoty opcjonalne i nieporównywane.
- **Ustalenie:** Linie 100–151: kliknięcia myszy i Enter zmieniają aria-current oraz pokazują pojedynczy detal huba/rurociągu; link incydentu wraca do huba, nawigacja aktywuje incydenty; rynek ma 12 pozycji, druga strona 2 karty, filtr Beta jedną, kotwica zostaje po submit; Space zwija/rozwija kartę i sekcja jest w viewport; pageerror puste.
- **Ustalenie:** Linie 152–166: przy reduced motion sprawdza inline width pierwszego progress bara; oddzielny, ręcznie wstrzyknięty HTML /technical testuje preselect hub_repair/hub_id=2/details open przez technical.js, a sztuczny /well-fixture testuje otwarcie #wg-detail-57 przez well_grid.js; oba są mockowanymi deep linkami, nie ekranami działającej aplikacji.
- **Zależności do sprawdzenia:** `tests/fixtures/logistics_design_render.php`, `assets/js/logistics_design.js`, `assets/js/logistics_hubs.js`, `assets/js/logistics_staffing.js`, `assets/js/logistics_hub_wells.js`, `assets/js/logistics_hub_browser.js`, `assets/js/technical.js`, `assets/js/well_grid.js`, `api/internal/HubApi.php`.
- **Granica dowodu:** Pełny odczyt 171 linii; node --check potwierdził tylko składnię. Nie uruchomiono Edge/Playwright/PHP/E2E, realnego API ani bazy; wartości i widoki pochodzą z fixture i mocków, a zależności pozostają do pełnej analizy.

### `tests/navigation_activity_browser.cjs`

**Blob:** `a9325d1a9b7addbcdb924c246b2594810fac1279` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 53 linii.

- **Ustalenie:** Playwright w sześciu szerokościach sprawdza otwieranie nawigacji, ikony, aria-expanded, klawiaturę, powiadomienia i overflow. Wywołania API news są mockowane; fixture na http://127.0.0.1:8768/tests/fixtures/home_dashboard_render.php musi być wcześniej serwowany.
- **Ustalenie:** Chromium launch channel msedge i sztywny port 8768 wymagają przygotowanego środowiska; wynik testu nie obejmuje prawdziwej sesji, danych ani backendu. Opcjonalne zrzuty tylko dla 390/1440 przez UI_SCREENSHOT_DIR.
- **Zależności do sprawdzenia:** `tests/fixtures/home_dashboard_render.php`.
- **Granica dowodu:** node --check przeszedł; nie uruchomiono Playwright ani serwera PHP

### `tests/profile_browser.cjs`

**Blob:** `9566c69777e0f1f2813bc1722ad4fdbe2b7d2023` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 43 linii.

- **Ustalenie:** Test uruchamia PHP fixture, dodaje lokalny CSS/JS i w sześciu szerokościach sprawdza brak overflow, wielkość kontrolek oraz kolejność kart; sprawdza także podgląd nazwy firmy i przełącznik hasła.
- **Ustalenie:** Domyślne ścieżki PHP i Chrome są twardo zakodowane pod Windows (linie 9–10), więc test nie jest przenośną bramką CI bez override PHP_BIN i instalacji Chrome w tej samej lokalizacji. setContent() nie testuje sieci/API ani zapisu profilu.
- **Zależności do sprawdzenia:** `tests/fixtures/profile-view.php`, `assets/css/profile.css`, `assets/js/profile.js`.
- **Granica dowodu:** node --check przeszedł; testu Playwright nie uruchomiono, brak PHP/Chrome potwierdzonych w tym środowisku

### `tests/public_home_browser.cjs`

**Blob:** `97b6be251298aae67c2f6c2a7572bd0e7a22ea9b` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 98 linii.

- **Ustalenie:** Playwright renderuje PHP fixture PL/EN pod fikcyjnym https://fixture.test, mockuje zasoby lokalne i bada taby, nawigację, dialog, focus, sześć szerokości oraz błędy konsoli. Opcjonalne screenshoty dotyczą PL 390/1440.
- **Ustalenie:** Nie obejmuje DE, prawdziwego API ani serwera HTTP; sztuczny route/asset nie dowodzi odpowiedzi produkcyjnego hosta. Wymaga PHP_BINARY i Edge.
- **Zależności do sprawdzenia:** `tests/fixtures/public_home_render.php`, `favicon.png`.
- **Granica dowodu:** node --check przeszedł; test przeglądarkowy nie został uruchomiony

### `tests/sabotage_browser.cjs`

**Blob:** `53c62aefa8674bff1aed5a492cbb748294e4cdc1` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 66 linii.

- **Ustalenie:** Pierwszy test Playwright używa całkowicie syntetycznego HTML i mockuje podpowiedzi serwera, sprawdzając wybór akcji, ofertę, dialog i focus; drugi renderuje PHP fixture i CSS przy 320–1440px, lecz nie wykonuje serwerowego POST.
- **Ustalenie:** Domyślne PHP i Chrome są twardo przypięte do ścieżek Windows; test nie zastępuje auth/CSRF, testu ceny na serwerze i realnego kosztu. Skrypt JS w drugim teście nie jest dodawany, więc kontroluje głównie statyczny widok.
- **Zależności do sprawdzenia:** `tests/fixtures/sabotage-view.php`, `assets/js/sabotage.js`, `assets/css/sabotage.css`.
- **Granica dowodu:** node --check przeszedł; nie uruchomiono Playwright/PHP

### `tests/well_workspace_browser.cjs`

**Blob:** `80206c7aa56e513134ae782da44641ca2b118f30` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 96 linii.

- **Ustalenie:** Playwright dla PL/EN/DE sprawdza sześć tabów odwiertu, selektor, modal, paginację historii, odporność na opóźnioną odpowiedź i 7 szerokości; historie oraz assety są mockowane, formularz działania nie jest wykonywany.
- **Ustalenie:** W odróżnieniu od innych testów importuje require(process.env.PLAYWRIGHT_MODULE) bez fallback (linia 1); bez zmiennej nie startuje. Mock assetów w razie braku pliku zwraca pustą treść zamiast błędu (14–16), co osłabia dowód kompletności zasobów.
- **Zależności do sprawdzenia:** `tests/fixtures/well_workspace_render.php`.
- **Granica dowodu:** node --check przeszedł; testu Playwright/PHP nie uruchomiono


### `assets/css/black_market.css`

**Blob:** `1c656b73d1745166b3593ec9c01fe74080132a5b` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 420 linii.

- **Ustalenie:** Wiersze 49–179 definiują osobne siedmio-/pięcio-/dziesięcio-/dziewięciokolumnowe listy ofert, historii i admina; <=900px dodają poziome przewijanie i min-width, <=600px układ filtrów. Arkusz jest wpinany w public/market.php oraz admin/black_market.php.
- **Ustalenie:** Klasy bm-risk/status/penalty i toast (182–395) opisują wygląd, nie dowodzą wykrycia, kary ani zaksięgowania czarnorynkowej transakcji.
- **Zależności do sprawdzenia:** `public/market.php`, `admin/black_market.php`.
- **Granica dowodu:** Pełny odczyt CSS i statyczna kontrola wskazanych zależności na zamrożonym HEAD; nie uruchamiano PHP, MySQL, Playwright, HTTP ani testu renderu.


### `assets/css/boardroom-scene.css`

**Blob:** `b81bb5bc669adaf4852e98f652951aac9863833f` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 343 linii.

- **Ustalenie:** Wiersze 6–10 definiują globalnie --gold/--gold2/--red/--green; 175–211 stylizują scenę z tooltipem ujawnianym wyłącznie przez :hover, bez osobnej reguły :focus-visible dla tooltipu. Przy <=600px scena otrzymuje aspect-ratio:4/3 (333–343).
- **Ustalenie:** Nie znaleziono literalnego odwołania do boardroom-scene.css w przeszukanych źródłach PHP/JS ani klasy br-body poza CSS; nie przypisywać tego arkusza aktualnej stronie boardroom bez weryfikacji ładowania.
- **Zależności do sprawdzenia:** `public/boardroom.php`, `assets/css/boardroom.css`.
- **Granica dowodu:** Pełny odczyt CSS i statyczna kontrola wskazanych zależności na zamrożonym HEAD; nie uruchamiano PHP, MySQL, Playwright, HTTP ani testu renderu.


### `assets/css/chat.css`

**Blob:** `24e232629510a430e4e05c6943b05690a60c5cc4` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 1875 linii.

- **Ustalenie:** Arkusz jest ładowany przez public/chat.php i public/index.php. Zawiera stare style news/chat oraz późniejszy kompletny system rozmów (l. ok. 974+), wspólny dla chat-container i wariantu --dashboard; deklaracje :root --chat-* mogą wpłynąć na oba ekrany.
- **Ustalenie:** W @media <=768px .chat-shell ma najpierw height:auto, lecz późniejsza reguła ustawia height:min(700px,85dvh) i min-height:440px; finalna kaskada różni się od wcześniejszej deklaracji. Historyczne ukrywane na hover .chat-msg-opts nie zostały znalezione w aktywnych szablonach PHP/JS, nie przypisywać im bieżącego defektu dostępności.
- **Zależności do sprawdzenia:** `public/chat.php`, `public/index.php`, `templates/views/chat/main.php`.
- **Granica dowodu:** Pełny odczyt CSS i statyczna kontrola wskazanych zależności na zamrożonym HEAD; nie uruchamiano PHP, MySQL, Playwright, HTTP ani testu renderu.


### `assets/css/contracts.css`

**Blob:** `fbfb8ae1d079ac676b08eb7415e5fe5d6e10e286` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 476 linii.

- **Ustalenie:** Wiersze 156–157: brak przecinka między .contracts-badge--high a .contracts-badge--critical zmienia regułę na selektor potomka, a szablon contracts/main.php:173 może emitować samodzielny contracts-badge--high. Wariant high nie otrzymuje zamierzonych czerwonych deklaracji z tej reguły; skutki wizualne zależą od kaskady.
- **Ustalenie:** Wiersze 226–234 szerokość paska bierze z --bar-w z szablonu; przy <=640px wiersze tabel przechodzą w karty z etykietą data-label. Nie sprawdzano rozliczeń ani czasu dostaw.
- **Zależności do sprawdzenia:** `public/contracts.php`, `templates/views/contracts/main.php`, `templates/views/contracts/b2b.php`.
- **Granica dowodu:** Pełny odczyt CSS i statyczna kontrola wskazanych zależności na zamrożonym HEAD; nie uruchamiano PHP, MySQL, Playwright, HTTP ani testu renderu.


### `assets/css/dashboard.css`

**Blob:** `8a0e2fcdfa505576e90d5aa09783ec5ba6c003d6` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 443 linii.

- **Ustalenie:** Wiersze 17–25: :root globalnie nadpisuje --gold2 z variables.css/style.css (jasny kolor #e8cc7a) na półprzezroczysty rgba(200,168,75,.15) i --muted na inną wartość. public/dashboard.php ładuje arkusz, więc inne komponenty tej strony konsumujące var(--gold2) mogą zmienić wygląd; faktyczne efekty wymagają computed styles.
- **Ustalenie:** Wiersze 113–443: dashboard ma statystyki, kandydatów, listy i formularz rekrutacji; responsive <=480px. CSS nie weryfikuje opłat, rekrutacji ani przepływu danych.
- **Zależności do sprawdzenia:** `public/dashboard.php`, `templates/views/dashboard/main.php`, `assets/css/variables.css`, `assets/css/style.css`.
- **Granica dowodu:** Pełny odczyt CSS i statyczna kontrola wskazanych zależności na zamrożonym HEAD; nie uruchamiano PHP, MySQL, Playwright, HTTP ani testu renderu.


### `assets/css/director.css`

**Blob:** `38b8d8d04a2ffa4b72f1b43900d708762bc80691` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 436 linii.

- **Ustalenie:** Wiersze 7–441 stylizują panel komunikatów, priorytety, przyciski oznaczania odczytu i alerty techniczne; .notification-action-btn.btn-primary (268–286) wymusza kolory !important. public/index.php ładuje ten arkusz.
- **Ustalenie:** W <600px zmienia odstępy i układ; bez renderu i serwera nie potwierdza widoczności ani skuteczności akcji powiadomień.
- **Zależności do sprawdzenia:** `public/index.php`, `templates/components/director_notifications.php`.
- **Granica dowodu:** Pełny odczyt CSS i statyczna kontrola wskazanych zależności na zamrożonym HEAD; nie uruchamiano PHP, MySQL, Playwright, HTTP ani testu renderu.


### `assets/css/hr_morale.css`

**Blob:** `1a70b169fd28ab19479ac3b385d9e63309f2e061` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 343 linii.

- **Ustalenie:** Wiersze 147–153: .hr-morale-progress__bar używa width:var(--bar-w), a templates/views/hr/main.php:727–749 przekazuje procent inline dla czterech KPI; nie mylić z wcześniej ustalonym brakiem width dla innego .trait-fill.
- **Ustalenie:** Wiersze 301–343 zmieniają układ morale przy 900 i 640px; public/hr.php ładuje arkusz po hr.css.
- **Zależności do sprawdzenia:** `public/hr.php`, `templates/views/hr/main.php`, `assets/css/hr.css`.
- **Granica dowodu:** Pełny odczyt CSS i statyczna kontrola wskazanych zależności na zamrożonym HEAD; nie uruchamiano PHP, MySQL, Playwright, HTTP ani testu renderu.


### `assets/css/legal.css`

**Blob:** `9d7c63bab97a2e762b3ff88923d39bd9ff1f4495` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 358 linii.

- **Ustalenie:** Wiersze 7–30 ustawiają .card h3 globalnie dla wszystkich kart na stronach, które ładują arkusz, 296–301 także .card > p:first-child; trzeba sprawdzić rzeczywistą kaskadę względem legal_design.css i inne karty na public/legal.php.
- **Ustalenie:** Warianty karty regionu i badges obejmują pending/locked/credibility (38–181), modal potwierdzenia i łapówkę (304–350); samo CSS nie określa logiki pozwoleń.
- **Zależności do sprawdzenia:** `public/legal.php`, `assets/css/legal_design.css`, `templates/views/legal/main.php`.
- **Granica dowodu:** Pełny odczyt CSS i statyczna kontrola wskazanych zależności na zamrożonym HEAD; nie uruchamiano PHP, MySQL, Playwright, HTTP ani testu renderu.


### `assets/css/logistics.bak.css`

**Blob:** `76130d37083651030552af3192af305ddee68091` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 2219 linii.

- **Ustalenie:** Plik .bak.css jest archiwalną kopią stylów logistyki: siatki KPI, huby, pipeline, modale, responsive. Bieżące public/logistics.php ładuje logistics.css, nie logistics.bak.css; porównanie git diff --no-index --stat wykazało 617 dodatkowych linii w aktualnym pliku (bez usunięć). Nie traktować kopii jako jedynej specyfikacji aktualnego UI.
- **Ustalenie:** Kopia zawiera reguły mobile chowające kolumny tabeli >3 i inne dla assign modal; skutki na żywym ekranie zależą od aktualnego logistics.css, którego sama kopia nie zastępuje.
- **Zależności do sprawdzenia:** `public/logistics.php`, `assets/css/logistics.css`.
- **Granica dowodu:** Pełny odczyt CSS i statyczna kontrola wskazanych zależności na zamrożonym HEAD; nie uruchamiano PHP, MySQL, Playwright, HTTP ani testu renderu.


### `assets/css/map.css`

**Blob:** `834179ac5b61787216cd9e321473ea94a01997f6` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 844 linii.

- **Ustalenie:** Wiersze 50–119: desktop 1fr + 320px z mapą/globusem i panelem wysokości 560px, <=900px jedna kolumna i 380px; dane regionów, permitów i ceny nie są walidowane przez CSS.
- **Ustalenie:** Wiersze 18–32 używają ogólnych .legend-item/.legend-dot, mogą oddziaływać na inne legendy na stronie mapy; zasięg i nadpisania weryfikować renderem.
- **Zależności do sprawdzenia:** `public/map.php`, `templates/views/map/main.php`.
- **Granica dowodu:** Pełny odczyt CSS i statyczna kontrola wskazanych zależności na zamrożonym HEAD; nie uruchamiano PHP, MySQL, Playwright, HTTP ani testu renderu.


### `assets/css/privacy.css`

**Blob:** `c810096b65f3e17c4edc74cc1f59f2d85d6066ed` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 481 linii.

- **Ustalenie:** Wiersze 5–132: baner startuje poza ekranem (translateY(100%)), klasa --visible go pokazuje; modal startuje z opacity:0 i pointer-events:none i jest otwierany klasą --open. To tylko style — dostępność klawiaturą, focus trap i trwałość zgody wymagają oddzielnego testu.
- **Ustalenie:** Wiersze 235–283 stylizują toggle input z szerokością i wysokością 0 i ukryty wizualnie; strony publiczne/admin i mobile (do 481) mają odrębne reguły. Szablon header.php ładuje arkusz warunkowo przy banerze.
- **Zależności do sprawdzenia:** `templates/header.php`, `public/privacy-settings.php`, `assets/js/privacy_banner.js`.
- **Granica dowodu:** Pełny odczyt CSS i statyczna kontrola wskazanych zależności na zamrożonym HEAD; nie uruchamiano PHP, MySQL, Playwright, HTTP ani testu renderu.


### `assets/css/technical.css`

**Blob:** `0eeae9ea2f19d27fee95d69a928d31d7699b9110` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 1564 linii.

- **Ustalenie:** Arkusz ładowany w public/technical.php stylizuje powiadomienia, kadrę i kandydatów, zadania, bezpieczeństwo/HSE, procedury, odwierty, produkcję, pracowników i układ department; kaskada późniejszych sekcji i responsywność muszą być odtworzone w migracji.
- **Ustalenie:** Kolory/statusy i wygląd guzików nie dowodzą uprawnień, wykonania napraw ani zmian danych. Nie było renderu ani uruchomienia testów; zweryfikować widoki technical przy migracji.
- **Zależności do sprawdzenia:** `public/technical.php`, `templates/views/technical/tabs/safety.php`.
- **Granica dowodu:** Pełny odczyt CSS i statyczna kontrola wskazanych zależności na zamrożonym HEAD; nie uruchamiano PHP, MySQL, Playwright, HTTP ani testu renderu.


### `assets/css/well-grid.css`

**Blob:** `2f287aad1b389cabf53da5cb8e419abf5a8371e5` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 1540 linii.

- **Ustalenie:** Pełny arkusz ma kolejne warstwy override dla siatki odwiertów, KPI, modali, responsywności. W l.1521–1522 .wg-region-group .wg-kpi-grid ma 4 kolumny !important, a późniejsze reguły @media dla samej .wg-kpi-grid (l.1535/1539) są mniej szczegółowe i nie zmienią grupowego KPI na 3/2 kolumny. To statyczny konflikt selektorów, jeśli arkusz i dawny komponent są ładowane.
- **Ustalenie:** W bieżących aktywnych PHP/JS nie znaleziono literalnego odwołania do well-grid.css; odwołania istnieją w backupach index.php, zaś współczesne templates/views/wells używają odrębnych klas ww-*. Nie przedstawiać hipotetycznej regresji starego CSS jako błędu działającego UI.
- **Zależności do sprawdzenia:** `templates/components/well_grid.php`, `templates/views/wells/overview.php`, `backup/index.php.bak_mass_20260521_152448`.
- **Granica dowodu:** Pełny odczyt CSS i statyczna kontrola wskazanych zależności na zamrożonym HEAD; nie uruchamiano PHP, MySQL, Playwright, HTTP ani testu renderu.


### `svn_repo/FINANCE_README.md`

**Blob:** `0f535ef5031cf58aa2721785a4c13d7e35660442` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 2702 linii.

- **Ustalenie:** L3–5, 1217–1262, 1876–1997: oznaczenie etapów 1–5 jako ukończonych dotyczy stanu z maja 2026; HEAD ma FinanceService/FinancePolicyService i panel admina, lecz §29 (L1960–2015) podaje 100 testów/259 asercji w trzech plikach tests/Integration/FinancePolicyServiceTest.php, FinanceServiceTest.php, AdminFinancePanelTest.php, których nie ma w indeksie HEAD. Nie jest to wynik testów HEAD.
- **Ustalenie:** L1888–1900, 1934–1944: siedem pól hubowych i zapis ticka są obecne w src/FinanceService.php:27–39, 47–89 oraz src/Tick/PlayersSection.php; HEAD dodaje też produced_bbl, delivered_bbl, pre_storage_loss_bbl i osobne straty transportowe (FinanceService.php:27–32, 70–89). Test MySqlFinanceDeliveredVolumeTest.php:6–59 obejmuje zapis i agregację dodatkowych metryk, ale nie wykonano go tutaj.
- **Ustalenie:** L2673–2702: twierdzenie, że admin/finance.php zapisuje global_tax_modifier/global_cost_modifier/global_loss_modifier i przepina je na tick, jest nieaktualne: tests/Unit/AdminBalanceConfigTest.php:9–27 wymaga ich braku w polach finansów; admin/balance.php:15–22 udostępnia global_*_multiplier, a src/Tick/TickContext.php:109–112 odczytuje runtime mnożniki. Zachowane pliki admin/partials/finance_admin_actions.php.bak_mass_* nie są aktywnym kontrolerem.
- **Ustalenie:** L85–89 GAME_README i L1950–1958 FINANCE_README: w HEAD admin/finance.php:19–20 tworzy FinanceService(false) i FinancePolicyService($db, false), zaś tests/Unit/AdminFinanceReadPathTest.php:8–20 sprawdza to jako asercję tekstową (nie wykonuje pełnego żądania ani nie mierzy produkcji).
- **Ustalenie:** L391–496, 1237–1242: polityka oszczędności/rezerwy to funkcje rzeczywistego FinancePolicyService.php (m.in. savePolicySettings:285, getSavingsPlanStatus:476); admin/partials/finance_admin_actions.php:64,158–161 ogranicza cooldown DB do 1–168 h. Wartość 6 h jest fallbackiem, a nie uniwersalnym stałym cooldownem HEAD.
- **Ustalenie:** L1431–1439, 1483–1511, 1607–1611: stwierdzenia „brak pełnego modelu morale/strajków/kompetencji logistycznych” opisują wcześniejszy etap i nie są stanem HEAD: istnieją src/Tick/Modules/EmployeesModule.php:8–59, src/HR/StrikeEffectService.php:29–81 i src/Employee/EmployeeRoleEffectService.php; feature_strike_effects jest flagą i domyślnie false w EmployeeSystemConfigService.php:52–54.
- **Zależności do sprawdzenia:** `src/FinanceService.php`, `src/FinancePolicyService.php`, `src/Tick/PlayersSection.php`, `src/Tick/TickContext.php`, `admin/finance.php`, `admin/balance.php`, `admin/partials/finance_admin_actions.php`, `tests/Unit/AdminBalanceConfigTest.php`, `tests/Unit/AdminFinanceReadPathTest.php`, `tests/MySqlIntegration/MySqlFinanceDeliveredVolumeTest.php`, `src/Tick/Modules/EmployeesModule.php`, `src/HR/StrikeEffectService.php`, `src/Employee/EmployeeRoleEffectService.php`, `src/Employee/EmployeeSystemConfigService.php`.
- **Granica dowodu:** Odczyt statyczny pełnych 2702 wierszy dokumentu w pięciu partiach; bez uruchamiania PHPUnit, PHPStan, PHP/MySQL, ticka i przeglądarki. Liczby i sukcesy testów z 2026-05-13/16 pozostają deklaracjami historycznymi, nie walidacją HEAD.
- **Granica dowodu:** Nie weryfikowano wdrożenia produkcyjnego, danych bazy, rzeczywistego balansu ekonomicznego ani działania UI na sesji.


### `svn_repo/GAME_README.md`

**Blob:** `3dd3b86ed834b333bffc3e66a32a1ec1da7c9147` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 1277 linii.

- **Ustalenie:** L3–8, 53–58: późny changelog deklaruje nową nawigację i regionalne nazwy; HEAD zawiera src/GameNavigation.php:33–92 (grupy filtrowane po linkach) i assets/img/icons/game-nav/*.svg. Bez uruchomienia fixture/Playwright nie potwierdzono wyglądu, overflow ani stanu produkcji; 935 testów w L8 to wynik historyczny.
- **Ustalenie:** L29–33: opis naprawy tick_run_sequence ma odpowiednik w HEAD: src/Tick/TickCoordinator.php:178–204 inicjalizuje klucz tylko gdy brak i inkrementuje atomowo, ręczny run tylko odczytuje. tests/MySqlIntegration/MySqlTickSequenceTest.php:46–52 asercjami sprawdza następny numer; testu tutaj nie wykonano.
- **Ustalenie:** L91–95 koryguje wcześniejsze L322–328: HEAD src/Tick/TickCoordinator.php:433–471 trzyma 2 dni i sprawdza cleanup co godzinę, również po złej/przyszłej dacie. tests/MySqlIntegration/MySqlTickHistoryRetentionTest.php:8–55 sprawdza to na tabelach tymczasowych, nie na produkcyjnej retencji.
- **Ustalenie:** L40–45 i L23–27: istnieją src/ChatRetentionService.php:13–20 (brak tabeli 1146 nie blokuje innej retencji), src/ChatRequestPolicy.php:33 i src/ChatApi.php:24 (Retry-After 60), sql/manual/chat_request_limits.sql; brak dowodu, że migracja została wykonana na hostingu lub że serwer WWW zwraca rzeczywisty 503 w sesji.
- **Ustalenie:** L69–78 (3-kolumnowy czat z boxem admina) jest historyczne i wewnętrznie zastąpione L16–21 (usunięcie boxu); tych opisów nie należy traktować jako równoczesnej specyfikacji obecnego UI. Tests/Unit/ChatRedesignTest.php i ChatMessageHtmlTest.php są w HEAD, ale ich dawne liczniki nie potwierdzają bieżącego przebiegu.
- **Ustalenie:** L1041–1054, 351–368: src/Tick/TickRegistry.php:120 sortuje moduły rosnąco, tests/Unit/TickRegistryTest.php:27–45 sprawdza kolejność market 10, marine 20, bank 30, employees 35, players 40 itd. HISTORYCZNE etapowe zdania „cron jeszcze nie przepięty” (L361–369) nie odnoszą się do HEAD.
- **Ustalenie:** L1149–1159 (EmployeesModule nie dodano) i L1082–1092 to zapisy z 15–16 lipca wyparte przez późniejsze L138–147; HEAD ma src/Tick/Modules/EmployeesModule.php:8–59 oraz test kolejności w tests/Unit/TickRegistryTest.php:27–45. Nie wolno przenosić starych TODO do stanu obecnego.
- **Ustalenie:** L120–147 i 1182–1188 opisują HR v7 i bramki CI w ówczesnym stanie; HEAD src/Employee/EmployeeSystemSchema.php:6 ma VERSION=8, więc deklaracja wersji 7 nie jest aktualnym kontraktem schematu. Tests/MySqlIntegration/MySqlEmployeeHrConcurrencyTest.php:9,42–100 zawiera pętle 20 prób, ale istnienie testu ≠ wykonanie lub wynik HEAD.
- **Zależności do sprawdzenia:** `src/GameNavigation.php`, `src/PlayerNavIcon.php`, `src/Tick/TickCoordinator.php`, `src/Tick/TickRegistry.php`, `src/Tick/Modules/EmployeesModule.php`, `src/Employee/EmployeeSystemSchema.php`, `src/ChatApi.php`, `src/ChatRequestPolicy.php`, `src/ChatRetentionService.php`, `sql/manual/chat_request_limits.sql`, `tests/Unit/TickRegistryTest.php`, `tests/MySqlIntegration/MySqlTickSequenceTest.php`, `tests/MySqlIntegration/MySqlTickHistoryRetentionTest.php`, `tests/Unit/ChatRedesignTest.php`, `tests/Unit/ChatMessageHtmlTest.php`, `tests/MySqlIntegration/MySqlEmployeeHrConcurrencyTest.php`.
- **Granica dowodu:** Odczyt statyczny pełnych 1277 wierszy w dwóch partiach; bez wykonania testów, PHP, MySQL, HTTP, Playwright czy produkcyjnego smoke. Chronologia changeloga zawiera sprzeczne i nadpisane etapy; interpretacja oparta na najpóźniejszym kodzie HEAD.
- **Granica dowodu:** Nie potwierdzono migracji DB, serwera WWW, produkcyjnego wdrożenia ani wyników CI; deklaracje 935/664/256 i pozostałe nie są wynikami tego audytu.


### `svn_repo/brief-ai-pracownicy-role-logistyka-morale-strajki.md`

**Blob:** `80a3ca04cc00cf576130eb85b4fc7b34b63d2069` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 2701 linii.

- **Ustalenie:** L108–125, 235–299 i 2321–2343: reguła zatrudnienia ma odpowiednik w src/HR/HiringTrait.php:44–72: brak specjalizacji→director, techniczny→technical_staff, inny→board_staff, a blokada zajętej roli dotyczy tylko dyrektora. Sam statyczny kod nie dowodzi poprawności transakcji na realnej bazie.
- **Ustalenie:** L520–547 chce hub_operator jako logistyczny zawód, ale późniejsze L2597–2599 i 2610–2612 zmieniają to na techniczny; HEAD src/EmployeeSystemBootstrap.php:71–77 seeduje hub_operator z department=technical, a L17–68 seeduje inne role logistyki. Nie przenosić pierwotnej listy bez późniejszej korekty.
- **Ustalenie:** L1546–1585, 2564–2571 żąda order=35, interval=2, limit=200; HEAD src/Tick/Modules/EmployeesModule.php:13–35 ma order=35 i limit fallback=200, lecz src/Tick/TickModuleCatalog.php:11 ustawia zalecany interval=1 (nie 2) i critical=true. src/Tick/TickModuleConfigRepository.php:21–40 używa katalogu przy seedzie; istniejącej konfiguracji DB INSERT IGNORE nie nadpisuje. Polityka wykonania modułu w EmployeesModule.php:23–26 to CONTINUE; znaczenia pola critical w katalogu nie utożsamiać bez sprawdzenia schedulera.
- **Ustalenie:** L2391–2446 (częściowo wdrożone / brak strajków i EmployeesModule) to stan sprzed L2644–2653; HEAD src/Tick/Modules/EmployeesModule.php:28–59 wywołuje EmployeeMoraleSection i raportuje liczniki, src/HR/StrikeEffectService.php:29–81 liczy efekty aktywnych strajków. Funkcje feature_threats/feature_strikes/feature_strike_effects są domyślnie wyłączone (src/Employee/EmployeeSystemConfigService.php:52–54), więc dostępność kodu ≠ aktywacja mechaniki u gracza.
- **Ustalenie:** L2651 i starsze L131–145 GAME_README wspominają schemat v6/v7; HEAD src/Employee/EmployeeSystemSchema.php:6 ma VERSION=8. Wersjonowanie i migrację danych trzeba rozróżnić od historycznego statusu dokumentu.
- **Ustalenie:** L1770–1809 planuje konkretne nazwy ActionsTrait/DataTrait/StaffingTrait i tabs/*, ale implementacja HEAD używa src/LogisticsPageController.php oraz src/LogisticsPage/ActionsTrait.php, ViewDataTrait.php i ViewData/*; tests/Unit/LogisticsPageViewContractTest.php:9–67 pilnuje kontraktu kluczy, L70–108 kolejności sekcji przez statyczne odczyty. Nazwy z planu nie stanowią brakujących plików wymaganych aktualną implementacją.
- **Ustalenie:** L2619–2654 i 2694–2701 podają dawne liczniki suite; tests/MySqlIntegration/MySqlEmployeeHrConcurrencyTest.php:9,42–100 zawiera 20 powtórzeń i osobne workerowe wyścigi, ale nie zostały wykonane tutaj. Test kontraktu logistyki bazuje na statycznych asercjach; nie jest dowodem pełnego live UI ani produkcyjnego rolloutu.
- **Ustalenie:** L941–951 mnożniki morale są przykładowym briefem; istnieje src/Employee/EmployeeRoleEffectService.php i jego test integracyjny, lecz bez uruchomienia i sprawdzenia wszystkich konsumentów nie zakładano, że każda przykładowa rola/efekt działa w każdym module gry.
- **Zależności do sprawdzenia:** `src/HR/HiringTrait.php`, `src/EmployeeSystemBootstrap.php`, `src/Employee/EmployeeSystemSchema.php`, `src/Employee/EmployeeSystemConfigService.php`, `src/Employee/EmployeeRoleEffectService.php`, `src/Tick/Modules/EmployeesModule.php`, `src/Tick/TickModuleCatalog.php`, `src/Tick/TickModuleConfigRepository.php`, `src/Tick/EmployeeMoraleSection.php`, `src/HR/StrikeEffectService.php`, `src/LogisticsPageController.php`, `src/LogisticsPage/ActionsTrait.php`, `src/LogisticsPage/ViewDataTrait.php`, `tests/Unit/LogisticsPageViewContractTest.php`, `tests/MySqlIntegration/MySqlEmployeeHrConcurrencyTest.php`, `tests/Integration/EmployeeRoleEffectServiceTest.php`.
- **Granica dowodu:** Odczyt statyczny pełnych 2701 wierszy dokumentu w pięciu partiach. Nie uruchomiono PHP, MySQL, Playwright, testów, migratora ani ticka; historyczne sukcesy z lipca nie są wynikiem HEAD.
- **Granica dowodu:** Nie weryfikowano produkcyjnego stanu flag, schematu bazy, transakcji na żywych danych ani kompletnego end-to-end wszystkich 18 kryteriów akceptacji.


### `api/internal/ChatApi.php`

**Blob:** `866c0ff3aabbdd2c2273a7877c25dff96a85752c` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 6 linii.

- **Ustalenie:** L1–6 wyłącznie adapter require do src/ChatApi.php; uwierzytelnienie, CSRF i logika rozmów są w implementacji, nie w tym pliku. Dostępność HTTP zależy od konfiguracji serwera/rewrite, tu nie testowana.
- **Zależności do sprawdzenia:** `src/ChatApi.php`.
- **Granica dowodu:** Pełny odczyt źródła na HEAD; zależności sprawdzono punktowo. Nie uruchomiono PHP/MySQL/Playwright; jedyny wykonany test to izolowany Node wskazany w odpowiedniej karcie.


### `assets/js/chat.js`

**Blob:** `ad99114fb20f89dea5c317495b52ad00abf03801` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 857 linii.

- **Ustalenie:** L45–724 obsługuje ekran czatu: bezpiecznie escapeuje nazwę/tekst, ładuje 50 wiadomości i starszą historię, polluje co 9 s, heartbeat co 30 s, drawer mobilny. L727–857 to osobny fallback widgetu dashboardu; oba źródła public/chat.php i public/index.php ładują ten sam plik.
- **Ustalenie:** L632–639 handler Enter/Shift+Enter/IME wyodrębniony przez test Node: 1 przypadek zaliczony lokalnie, bez DOM/HTTP. L119–160 i 759–770 renderują tylko escaped message, ignorują message_html z ChatMessageHtml::forApi, więc admin rich-text może zniknąć w obu wariantach UI; potwierdzić renderem.
- **Ustalenie:** L254–299 i 377–406 używają epoch dla wiadomości, ale błędy polling są ciche; L547–575 ignoruje błędy list. Nie oznacza to utraty danych serwerowych bez testu sesji i sieci.
- **Zależności do sprawdzenia:** `public/chat.php`, `public/index.php`, `src/ChatApi.php`, `tests/js/chat_keyboard.test.cjs`.
- **Granica dowodu:** Pełny odczyt źródła na HEAD; zależności sprawdzono punktowo. Nie uruchomiono PHP/MySQL/Playwright; jedyny wykonany test to izolowany Node wskazany w odpowiedniej karcie.


### `src/ApiAuth.php`

**Blob:** `993cfe5a94ff90aea5fbfd226502724fbace38c0` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 161 linii.

- **Ustalenie:** L23–62: bearer o formacie 64 hex jest porównywany z surowym tokenem w api_tokens; aktywne konto i expiry filtrują odczyt, a last_used_at jest best-effort. Brak haszowania tokenu w tabeli oznacza, że wyciek DB ujawnia aktywne tokeny; szczegóły uprawnień endpointów wymagają osobnego audytu.
- **Ustalenie:** L69–103 generuje 32 losowe bajty i daje 90 dni, revokeToken/revokeAllForPlayer istnieją. L109–144 ustawia statyczne done=true przed próbą CREATE TABLE i po błędzie tylko loguje; w tym procesie kolejna próba ensureSchema nie będzie podjęta. Wywołań revokeAll przy resetowaniu hasła nie znaleziono we wskazanych src/admin/public/api.
- **Zależności do sprawdzenia:** `src/ApiAuthRateLimiter.php`, `src/Auth.php`.
- **Granica dowodu:** Pełny odczyt źródła na HEAD; zależności sprawdzono punktowo. Nie uruchomiono PHP/MySQL/Playwright; jedyny wykonany test to izolowany Node wskazany w odpowiedniej karcie.


### `src/ApiAuthRateLimitBootstrap.php`

**Blob:** `9df6d6c38ae37a6fde5335aeb5f979bdc7efcb28` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 43 linii.

- **Ustalenie:** L10–43 konstruktor limitera wywołuje CREATE TABLE IF NOT EXISTS oraz kontrolę metadanych/ALTER/CREATE INDEX przed transakcją; jawnie odrzuca czynny transakcyjny PDO. To DDL przy żądaniu, nie dowód uruchomionej migracji produkcyjnej.
- **Ustalenie:** Race przy CREATE INDEX/ALTER łapie PDOException tylko gdy inny proces już utworzył obiekt; inne błędy propaguje. Testy sprawdzają wyłącznie wybrane warianty schematu.
- **Zależności do sprawdzenia:** `src/ApiAuthRateLimiter.php`, `tests/Integration/ApiAuthSecurityTest.php`.
- **Granica dowodu:** Pełny odczyt źródła na HEAD; zależności sprawdzono punktowo. Nie uruchomiono PHP/MySQL/Playwright; jedyny wykonany test to izolowany Node wskazany w odpowiedniej karcie.


### `src/ApiAuthRateLimiter.php`

**Blob:** `db3e80db57e66052c2014dec1d41c548dc065e9d` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 137 linii.

- **Ustalenie:** L15–43 normalizuje identyfikator i IPv4-mapped IPv6; L76–129 liczy dwa haszowane kubełki (IP 30/900s, konto+IP 5/900s), blokuje IP przed kontem i używa SELECT FOR UPDATE na MySQL.
- **Ustalenie:** L45–74 usuwa maks. 64 wygasłe rekordy na wywołanie, porównując snapshots attempts/expires_at; L83–85 odrzuca połączenie w transakcji. Test przeplotu i workerów dotyczy tych kontraktów, ale MySQL nie uruchamiano i nazwa test/ci w DSN nie gwarantuje izolacji.
- **Zależności do sprawdzenia:** `src/ApiAuthRateLimitBootstrap.php`, `tests/Integration/ApiAuthRetentionRaceTest.php`, `tests/MySqlIntegration/MySqlApiAuthRateLimiterTest.php`.
- **Granica dowodu:** Pełny odczyt źródła na HEAD; zależności sprawdzono punktowo. Nie uruchomiono PHP/MySQL/Playwright; jedyny wykonany test to izolowany Node wskazany w odpowiedniej karcie.


### `src/ChatApi.php`

**Blob:** `88290f0adc59b0b66ef5f18a30db76ba547ea2ff` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 896 linii.

- **Ustalenie:** L32–77 wymaga sesji Auth i waliduje CSRF dla wszystkich POST przez ChatRequestPolicy, następnie limituje według IP/akcji; adapter publiczny jest w api/internal/ChatApi.php. Ładuje pełne php://input przed sprawdzeniem limitu długości chunku, zatem duży payload może zużyć pamięć przed odmową.
- **Ustalenie:** L186–252: meta last_index ustawiana na bieżący index, potem array_merge($existing,$meta) nadpisuje poprzedni last_index; warunek L229 porównuje index z nim samym, więc nie weryfikuje kolejności chunków. Limit 15 KiB na request jest sprawdzany, lecz limit całego tymczasowego pliku 3 MiB dopiero przy finalizacji (L274–276), bez potwierdzonego sprzątania niedokończonych uploadów w tym handlerze. Ryzyko nadużycia przestrzeni/niepełnego uploadu wymaga testu izolowanego HTTP i polityki retencji.
- **Ustalenie:** L255–315 sprawdza MIME przez finfo, a dopiero potem przenosi plik do assets/uploads/chat; L401–409 najpierw finalizuje plik, potem wywołuje sendDirectAttachment. Przy odmowie partnera/bana/limitu w ChatService.php:493–546 brak tu cleanup przeniesionego pliku, więc możliwa osierocona kopia.
- **Ustalenie:** L79–89 sanitizer forApi dopisuje message_html dla admina, lecz współczesny chat.js:159,769 renderuje escaped message i ignoruje message_html; test jednostkowy serializacji nie sprawdza wyglądu admin broadcast. Legacy GET ma osobne zapytania/polling; nie uruchomiono rzeczywistego serwera ani DB.
- **Zależności do sprawdzenia:** `api/internal/ChatApi.php`, `src/ChatService.php`, `src/ChatRequestPolicy.php`, `src/ChatRequestLimiter.php`, `src/ChatMessageHtml.php`, `assets/js/dm.js`, `assets/js/chat.js`.
- **Granica dowodu:** Pełny odczyt źródła na HEAD; zależności sprawdzono punktowo. Nie uruchomiono PHP/MySQL/Playwright; jedyny wykonany test to izolowany Node wskazany w odpowiedniej karcie.


### `src/ChatMessageHtml.php`

**Blob:** `1971b35b7566ebc258d277f60c038b9e802d533c` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 72 linii.

- **Ustalenie:** L7–49 parsuje HTML przez DOMDocument z LIBXML_NONET, przepuszcza tylko p/strong/b/em/i/u/s/ul/ol/li/br, tekst escapeuje i odrzuca skrypty/atrybuty; bez DOMDocument fallback escapeuje cały HTML.
- **Ustalenie:** L51–70 zamienia admin broadcast na plain-text message i bezpieczne message_html, a dla gracza usuwa pole HTML. Testy sanitizacji nie sprawdzają wyświetlenia message_html w chat.js; aktualny renderer go nie konsumuje.
- **Zależności do sprawdzenia:** `tests/Unit/ChatMessageHtmlTest.php`, `assets/js/chat.js`.
- **Granica dowodu:** Pełny odczyt źródła na HEAD; zależności sprawdzono punktowo. Nie uruchomiono PHP/MySQL/Playwright; jedyny wykonany test to izolowany Node wskazany w odpowiedniej karcie.


### `src/ChatRequestLimiter.php`

**Blob:** `df7cf329058e7768d6d1122800fb7eecac01bae2` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 33 linii.

- **Ustalenie:** L8–31 haszuje klucz, rozpoczyna transakcję i na MySQL blokuje kubełek FOR UPDATE, zwracając Retry-After przy limicie; brak tabeli propaguje PDOException po rollback, mapowane do 503 przez politykę.
- **Ustalenie:** Nie ma tu ochrony, gdy caller już posiada transakcję; jednostkowy test pokrywa jedynie exception/mock PDO, nie prawdziwą równoległość lub wpływ limitera na mutację czatu.
- **Zależności do sprawdzenia:** `src/ChatRequestPolicy.php`, `tests/Unit/ChatRequestLimiterTest.php`.
- **Granica dowodu:** Pełny odczyt źródła na HEAD; zależności sprawdzono punktowo. Nie uruchomiono PHP/MySQL/Playwright; jedyny wykonany test to izolowany Node wskazany w odpowiedniej karcie.


### `src/ChatRequestPolicy.php`

**Blob:** `e474bb26da1326dc80ec2e65c96ec282fb3cc409` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 47 linii.

- **Ustalenie:** L12–25 dopuszcza konkretne GET i POST, odrzuca inne metody, wymaga CSRF dla każdego POST po uwierzytelnieniu; L27–46 mapuje tylko allowlistę błędów, a brak tabeli MySQL 1146 na 503.
- **Ustalenie:** Unit test wywołuje politykę w izolacji, bez wykazania przebiegu PHP przez ChatApi i efektywnego serwerowego Auth/CSRF.
- **Zależności do sprawdzenia:** `src/ChatApi.php`, `tests/Unit/ChatRequestPolicyTest.php`.
- **Granica dowodu:** Pełny odczyt źródła na HEAD; zależności sprawdzono punktowo. Nie uruchomiono PHP/MySQL/Playwright; jedyny wykonany test to izolowany Node wskazany w odpowiedniej karcie.


### `src/ChatRetentionService.php`

**Blob:** `ec64fb603d454fdb89c749f0d1f7786dfe255f8a` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 36 linii.

- **Ustalenie:** L8–35 usuwa maks. 1000 starych kubełków limitera, toleruje wyłącznie brak tabeli 1146, potem według konfiguracji oznacza stare wiadomości global/room is_deleted=1 (bez prywatnych i przypiętych) i aktualizuje chat_auto_clear_last_at.
- **Ustalenie:** UPDATE wiadomości i zapis znacznika konfiguracji nie są objęte tu wspólną transakcją; awaria drugiego może pozostawić wykonane pierwsze. Test używa mock PDO, nie bada trwałości ani pracy CRON.
- **Zależności do sprawdzenia:** `tests/Unit/ChatRetentionServiceTest.php`.
- **Granica dowodu:** Pełny odczyt źródła na HEAD; zależności sprawdzono punktowo. Nie uruchomiono PHP/MySQL/Playwright; jedyny wykonany test to izolowany Node wskazany w odpowiedniej karcie.


### `tests/Integration/ApiAuthRetentionRaceTest.php`

**Blob:** `1d689dbebdd83132cfd50b311f81690e6c55d2cd` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 90 linii.

- **Ustalenie:** L25–78: cztery warianty SQLite/MySQL oraz schemat legacy symulują odnowienie kubełka między SELECT a DELETE; asercje porównują próby [2000], expiry 2900 i limit 5/900 s. To przeplot kontrolowany hookiem PDO, nie losowy stres łączeń.
- **Ustalenie:** L37–49,81–87: MySQL uruchamia się tylko przy API_TEST_MYSQL_DSN i sprawdza regex nazwy dbname zawierającej test/ci; nie weryfikuje hosta ani fizycznej izolacji przed DDL/zapisem. Nie uruchamiać przy niepotwierdzonym DSN.
- **Zależności do sprawdzenia:** `src/ApiAuthRateLimiter.php`, `src/ApiAuthRateLimitBootstrap.php`.
- **Granica dowodu:** Pełny odczyt źródła na HEAD; zależności sprawdzono punktowo. Nie uruchomiono PHP/MySQL/Playwright; jedyny wykonany test to izolowany Node wskazany w odpowiedniej karcie.


### `tests/Integration/ApiAuthSecurityTest.php`

**Blob:** `7f943d9bda5425cfde45310493362aed9d8521b4` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 277 linii.

- **Ustalenie:** L26–124: SQLite sprawdza limity wspólnych aliasów/IP, normalizację, bounded retention 64, blokadę DDL/retencji przy transakcji. Asercja obecności hashy nie bada kradzieży surowych bearer tokenów.
- **Ustalenie:** L126–161 uruchamiałby 12 workerów SQLite i oczekuje 5 dopuszczonych; L163–214 bada odpowiedzi błędów przez podprocesy PHP oraz brak wybranych sekretów w logach; L216–267 startuje lokalny serwer PHP na fixture i kontroluje 400/401/429/500. Nie jest to rzeczywisty hosting ani test produkcyjnego MySQL.
- **Zależności do sprawdzenia:** `src/ApiAuthRateLimiter.php`, `tests/fixtures/api_auth_security_worker.php`, `tests/fixtures/api_auth_security_router.php`, `tests/fixtures/tech_notif_error_worker.php`.
- **Granica dowodu:** Pełny odczyt źródła na HEAD; zależności sprawdzono punktowo. Nie uruchomiono PHP/MySQL/Playwright; jedyny wykonany test to izolowany Node wskazany w odpowiedniej karcie.


### `tests/Integration/ChatHistoryRegressionTest.php`

**Blob:** `3b97736c979ae5b4a627ba5b33b5731910b202e1` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 66 linii.

- **Ustalenie:** L13–35: syntetyczny SQLite seeduje 121 wiadomości pokoju i prywatnych; ChatService jest tworzony bez konstruktora przez Reflection i ręcznie otrzymuje PDO. Nie bada migracji schematu ani bootstrapa.
- **Ustalenie:** L37–65: asercje ciągłości after_id/before_id (po 50), chronologii i braku dostępu do archiwalnego pokoju; markAsRead ma odrzucić cudzy kursor. Nie wykonuje HTTP, CSRF ani rzeczywistego MySQL.
- **Zależności do sprawdzenia:** `src/ChatService.php`.
- **Granica dowodu:** Pełny odczyt źródła na HEAD; zależności sprawdzono punktowo. Nie uruchomiono PHP/MySQL/Playwright; jedyny wykonany test to izolowany Node wskazany w odpowiedniej karcie.


### `tests/MySqlIntegration/MySqlApiAuthRateLimiterTest.php`

**Blob:** `36eec6d9fb03aaf7e19e8d0a4c15e76dc691a6de` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 89 linii.

- **Ustalenie:** L10–79: 12 i 40 osobnych workerów PHP na MySQL mają wykazać limity 5 i 25, zachowanie aktywnego kubełka oraz skasowanie wygasłych; sprawdza też odrzucenie ensureSchema w otwartej transakcji. To test źródłowo rzeczywistej współbieżności, ale nie został wykonany.
- **Ustalenie:** L12–21: jedyna bramka DSN wymaga podciągu test/ci w nazwie DB, bez sprawdzenia hosta/uprawnień; konstruktor limitera może wykonać DDL i test zapisuje/usuwa tabelę. Fizyczna izolacja musi być potwierdzona przed uruchomieniem.
- **Zależności do sprawdzenia:** `src/ApiAuthRateLimiter.php`, `tests/fixtures/api_auth_security_worker.php`.
- **Granica dowodu:** Pełny odczyt źródła na HEAD; zależności sprawdzono punktowo. Nie uruchomiono PHP/MySQL/Playwright; jedyny wykonany test to izolowany Node wskazany w odpowiedniej karcie.


### `tests/Unit/ChatMessageHtmlTest.php`

**Blob:** `2aa9fcb35107d566d152e59e6c14fa103f3ac2a6` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 50 linii.

- **Ustalenie:** L9–39: testuje whitelist tagów, usuwanie atrybutów i payloady script/svg/math oraz idempotencję sanitizacji; L41–49 wymusza zwykły tekst dla API admina i usunięcie message_html gracza.
- **Ustalenie:** Test nie renderuje współczesnego assets/js/chat.js, które wyświetla escHtml(m.message) i nie używa m.message_html; utrata formatowania admina w tym UI jest rozbieżnością statyczną do sprawdzenia w przeglądarce.
- **Zależności do sprawdzenia:** `src/ChatMessageHtml.php`, `src/ChatApi.php`, `assets/js/chat.js`.
- **Granica dowodu:** Pełny odczyt źródła na HEAD; zależności sprawdzono punktowo. Nie uruchomiono PHP/MySQL/Playwright; jedyny wykonany test to izolowany Node wskazany w odpowiedniej karcie.


### `tests/Unit/ChatRequestLimiterTest.php`

**Blob:** `ea9bf6f8ef8de084d20191ced411f110b881a09e` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 31 linii.

- **Ustalenie:** L11–30 mockuje PDO tak, by prepare rzuciło brak tabeli 1146; sprawdza rollback, brak commit/exec i mapę 503 w ChatRequestPolicy. Nie bada limitów 30/600/120 ani blokady FOR UPDATE pod równoległym ruchem.
- **Zależności do sprawdzenia:** `src/ChatRequestLimiter.php`, `src/ChatRequestPolicy.php`.
- **Granica dowodu:** Pełny odczyt źródła na HEAD; zależności sprawdzono punktowo. Nie uruchomiono PHP/MySQL/Playwright; jedyny wykonany test to izolowany Node wskazany w odpowiedniej karcie.


### `tests/Unit/ChatRequestPolicyTest.php`

**Blob:** `bfbda87d837ce2bb4e6c528ab4de5086f1a4b3eb` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 45 linii.

- **Ustalenie:** L9–19 sprawdza wymóg CSRF dla listy akcji POST, w tym aliasów i chunk upload, 405 dla niewłaściwych metod oraz odczyt presence bez CSRF; L21–44 mapowanie błędów/capabilities na 403/429/500/503.
- **Ustalenie:** Wywołuje wyłącznie statyczną politykę, a nie prawdziwą ścieżkę Auth/CSRF/ChatApi; nie dowodzi, że wszystkie efekty uboczne w handlerze są osłonięte.
- **Zależności do sprawdzenia:** `src/ChatRequestPolicy.php`, `src/ChatApi.php`.
- **Granica dowodu:** Pełny odczyt źródła na HEAD; zależności sprawdzono punktowo. Nie uruchomiono PHP/MySQL/Playwright; jedyny wykonany test to izolowany Node wskazany w odpowiedniej karcie.


### `tests/Unit/ChatRetentionServiceTest.php`

**Blob:** `1edee5e65d478e31e3622c988191f462116ac144` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 66 linii.

- **Ustalenie:** L45–64 mock RetentionFailurePDO rzuca 1146 dla brakującej tabeli limitera i pozwala kontynuować dwa zapisy; 1142 ma przerwać przed zapisami. Nie wykonuje rzeczywistego SQL, warunku daty, paginacji ani transakcji.
- **Zależności do sprawdzenia:** `src/ChatRetentionService.php`.
- **Granica dowodu:** Pełny odczyt źródła na HEAD; zależności sprawdzono punktowo. Nie uruchomiono PHP/MySQL/Playwright; jedyny wykonany test to izolowany Node wskazany w odpowiedniej karcie.


### `tests/chat_controls_browser.cjs`

**Blob:** `93135a1d81f0d1e66849ac089ff502183e0e9919` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 38 linii.

- **Ustalenie:** L7–34 headless Edge używa sztucznego DOM na sześciu szerokościach i wycina regexem handler keydown z chat.js do new Function; sprawdza Shift+Enter, Enter, lokalne ikony SVG i poziomy overflow fixture.
- **Ustalenie:** Test nie uruchamia pełnego chat.js, ChatApi ani sesji; zależy od msedge i Playwright, nie został tutaj wykonany.
- **Zależności do sprawdzenia:** `assets/js/chat.js`, `assets/img/icons/game-nav/home.svg`.
- **Granica dowodu:** Pełny odczyt źródła na HEAD; zależności sprawdzono punktowo. Nie uruchomiono PHP/MySQL/Playwright; jedyny wykonany test to izolowany Node wskazany w odpowiedniej karcie.


### `tests/js/chat_keyboard.test.cjs`

**Blob:** `27092b724963d737b73bbe0b390233cb85ffdd21` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 30 linii.

- **Ustalenie:** L6–30 wycina regexem handler klawiatury z chat.js i sprawdza Enter, Shift+Enter, IME, hidden/disabled. Izolowany node --test w tej sesji: 1 test zaliczony; nie jest to test DOM/serwera.
- **Zależności do sprawdzenia:** `assets/js/chat.js`.
- **Granica dowodu:** Pełny odczyt źródła na HEAD; zależności sprawdzono punktowo. Nie uruchomiono PHP/MySQL/Playwright; jedyny wykonany test to izolowany Node wskazany w odpowiedniej karcie.


### `admin/chat.php`

**Blob:** `effd32c933e48a8ba273c78e68447c726e1b6b3d` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 488 linii.

- **Ustalenie:** Po requireLogin i walidacji POST CSRF wybiera action z allowlisty, sprawdza ChatRequestPolicy, zaczyna transakcję dla moderacji; oddzielna akcja migrate_request_limits wykonuje DDL przed transakcją (5–54).
- **Ustalenie:** Moderacja obejmuje globalne soft-delete, ban/unban, broadcast, pin, zgłoszenia, słowa i pokoje; błędy service w akcjach pokojów skutkują rollback przez $err (56–287).
- **Ustalenie:** W pin_msg zbędna pierwsza kwerenda SELECT z castem bool execute() na int jest ignorowana, potem wykonywane jest poprawne SELECT (135–145). AdminLog i log moderacji to odrębne ścieżki, których atomowości nie dowodzi odczyt tej strony.
- **Zależności do sprawdzenia:** `src/ChatService.php`, `src/ChatRequestPolicy.php`, `src/ChatLimiterMigrationService.php`, `src/AdminAuth.php`, `templates/views/admin/chat/main.php`.
- **Granica dowodu:** Nie wykonano POST/DDL/MySQL; zakres uprawnień zależy od AdminAuth/ChatRequestPolicy i środowiska.


### `assets/js/admin_chat_editor.js`

**Blob:** `1e61e4be95a2ee7046869fd3837b9047bd291ff9` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 78 linii.

- **Ustalenie:** Inicjuje TinyMCE jeśli form/field/error i biblioteka są obecne; na submit zapisuje edytor, parsuje HTML, odrzuca 0 lub >500 znaków tekstu; reszta obsługuje przełączniki i dynamiczne wiersze tłumaczeń (1–78).
- **Ustalenie:** Klientowe sprawdzenie długości nie jest granicą bezpieczeństwa; serwer używa AdminChatBroadcast. Dynamiczne dane pochodzą z serwerowego szablonu i liczbowego nextIndex.
- **Zależności do sprawdzenia:** `admin/chat.php`, `src/AdminChatBroadcast.php`, `templates/views/admin/chat/main.php`.
- **Granica dowodu:** node --check kod 0; brak TinyMCE i testu browser DOM.


### `assets/js/dm.js`

**Blob:** `b3180ab0e916cbd7f0d55b8665a3569bf1574f2b` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 550 linii.

- **Ustalenie:** UI DM pobiera wątki, historię i polluje, EscHtml filtruje tekst/atrybuty, renderuje załączniki, ma własne ustawienia i wysyła upload w chunkach 12288 B (1–550).
- **Ustalenie:** Po pomyślnej wysyłce ustawia lastId na ID zwrócone przez send, po czym wywołuje pollMessages(since=lastId), które dodaje tylko wiadomości o większym ID (324–355, 226–237). Statycznie wysłana wiadomość może nie pojawić się w otwartym wątku bez reloadu, jeśli nie trafiła w równoległym pollu.
- **Ustalenie:** Walidacja MIME i rozmiaru 3 MiB po stronie JS nie zastępuje serwera; upload pozostaje podatny na problem serwerowy z wcześniej zbadanej karty ChatApi. RenderConversation interpoluje partner_id bez HTML escape, jednak ChatService::getDirectThreads rzutuje tę wartość na int (318–405); nie przypisywać jej XSS bez innej ścieżki danych.
- **Zależności do sprawdzenia:** `src/ChatService.php`, `src/ChatApi.php`, `api/internal/ChatApi.php`, `public/dm.php`, `assets/js/emoji.js`.
- **Granica dowodu:** node --check kod 0; brak przeglądarki, serwera HTTP oraz reprodukcji UI.


### `public/chat.php`

**Blob:** `3f2f0914f69918b8ad6b0109942686652e17614d` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 101 linii.

- **Ustalenie:** Kontroler /chat wymaga Auth, obsługuje fallback POST z CSRF, limitem IP i PRG 303, a GET wybiera pokój/wątek, preładuje historię i osadza wspólny game_shell (7–101).
- **Ustalenie:** Wymóg bezpieczeństwa wiadomości egzekwuje ChatService; test fallback nie wykonuje prawdziwego HTTP. GET ?with identyfikuje partnera bez wymogu dodatniego ID w tym kontrolerze; zapytania serwisu filtrują parę graczy.
- **Zależności do sprawdzenia:** `src/ChatService.php`, `src/ChatRequestLimiter.php`, `templates/views/chat/main.php`, `templates/components/game_shell.php`, `tests/Unit/ChatViewFallbackTest.php`.
- **Granica dowodu:** Nie wykonano żądań ani bazy; koszt zapytań niezmierzony.


### `src/ChatBootstrap.php`

**Blob:** `43876a00e49b29d99cec59b34d555203f2ae8782` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 198 linii.

- **Ustalenie:** ensureChatSchema odmawia poza CLI, potem na podanym PDO modyfikuje chat_messages, tworzy/uzupełnia tabele czatu i read-ledgery, wykonuje backfill globalnych wiadomości oraz ALTER ENUM i indeks (6–197).
- **Ustalenie:** DDL jest niespójne z wariantem ChatLimiterMigrationService: tu chat_request_limits powstaje bez indeksu started_at (186–188), a późniejsze CREATE TABLE IF NOT EXISTS z indeksem w admin-migracji nie dołoży go już istniejącej tabeli. Kontrola apply sprawdza wyłącznie PRIMARY.
- **Ustalenie:** MySqlChatComplianceTest wywołuje migrację na connection-local fixture, jednak surowy CLI na rzeczywistym PDO może wykonać zmiany globalne; nie uruchamiać bez backupu/izolacji.
- **Zależności do sprawdzenia:** `src/ChatLimiterMigrationService.php`, `tests/MySqlIntegration/MySqlChatComplianceTest.php`, `src/ChatService.php`.
- **Granica dowodu:** Odczyt statyczny; schematu/DDL nie wykonano.


### `src/ChatFilter.php`

**Blob:** `a4047b9fd22cc6b0c018c8c91d49767aa11b799c` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 62 linii.

- **Ustalenie:** Lazy-load cache słów z chat_blocked_words; contains robi substring case-insensitive, filter regex preg_replace case-insensitive, clearCache resetuje cache (9–62).
- **Ustalenie:** W odróżnieniu od contains, filter interpretuje replacement jako składnię zastępowania PCRE; zachowanie dla $1/backslash w konfiguracji wymaga testu, jeśli oczekiwany jest dosłowny tekst. Błąd SELECT ukrywa jako pustą listę słów.
- **Zależności do sprawdzenia:** `admin/chat.php`, `src/ChatService.php`, `src/ChatApi.php`.
- **Granica dowodu:** Nie uruchomiono PHP ani przypadków PCRE; wpływ zależy od rzeczywistych wywołań filter.


### `src/ChatLimiterMigrationService.php`

**Blob:** `f018d2c2b2b077449b5dde347110c9ae9f3248cd` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 28 linii.

- **Ustalenie:** apply odrzuca aktywną transakcję, CREATE TABLE IF NOT EXISTS chat_request_limits z indeksem started_at, a potem sprawdza kolumny przez SELECT i dokładnie jeden PRIMARY bucket_key (8–27).
- **Ustalenie:** Jeśli Bootstrap utworzył już tę tabelę bez indeksu started_at, CREATE IF NOT EXISTS jej nie zmieni i apply nadal może przejść; zgodność drugiego indeksu/typów nie jest tu w pełni weryfikowana.
- **Zależności do sprawdzenia:** `src/ChatBootstrap.php`, `admin/chat.php`, `src/ChatRequestLimiter.php`.
- **Granica dowodu:** Odczyt statyczny; brak wykonania DDL i oceny planów SQL.


### `src/ChatService.php`

**Blob:** `d9769392949e6458ba56c90a3682106719c0ce83` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 1170 linii.

- **Ustalenie:** ChatService obsługuje pokoje, DM, odczyt, obecność, tłumaczenia i moderację; nadawanie blokuje rekord nadawcy w transakcji i ogranicza 5 wiadomości/10 s (1–1170).
- **Ustalenie:** getDirectThreads używa wielokrotnie :player_id w jednej kwerendzie (316–376); bieżący Database ustawia ATTR_EMULATE_PREPARES=true, więc migracja do native prepares wymaga odrębnych placeholderów/testów.
- **Ustalenie:** sendDirectAttachment najpierw wstawia wiadomość, potem aktualizuje ścieżkę i zwraca strukturę sprzed update (480–489); dla samego UI istotne jest późniejsze odświeżenie. markAsRead aktualizuje nowy i legacy ledger w jednej transakcji (672–727).
- **Zależności do sprawdzenia:** `src/Database.php`, `src/ChatBootstrap.php`, `src/ChatApi.php`, `tests/MySqlIntegration/MySqlChatComplianceTest.php`.
- **Granica dowodu:** Pełen odczyt statyczny; brak wykonania SQL/MySQL i profilu konkurencji.


### `templates/views/admin/chat/main.php`

**Blob:** `d197722c438e3a2b53e313a259ef7ab93c0ccc50` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 617 linii.

- **Ustalenie:** Widok administracji renderuje formularze CSRF dla moderacji, tłumaczeń i manualnej migracji oraz log; zwykłe wiadomości escape, adminowy HTML przechodzi ChatMessageHtml::sanitize (1–617).
- **Ustalenie:** Asercja AdminChatFormTest ogranicza się do wygenerowanego DOM: ban ma data-confirm, broadcast nie; nie dowodzi wykonania frontendu ani serwera.
- **Zależności do sprawdzenia:** `admin/chat.php`, `assets/js/admin_chat_editor.js`, `src/ChatMessageHtml.php`, `tests/Unit/AdminChatFormTest.php`.
- **Granica dowodu:** Nie renderowano widoku przez PHP/DOM; brak testu działania przy rzeczywistych danych.


### `templates/views/chat/main.php`

**Blob:** `771c37c3d17b98db497d906a3c14f2a5891c4dcd` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 275 linii.

- **Ustalenie:** Wspólny widok room/DM renderuje serwerową historię escaped HTML i fallback POST, CSRF, paginację noscript, dialog mobilny i config JSON w atrybucie przez htmlspecialchars (1–275).
- **Ustalenie:** Wyświetlane na stronie wiadomości są plain-text nawet gdy is_admin; różni się to od sanitizowanego formatowania w panelu admina i pola message_html API, co wymaga świadomej decyzji migracyjnej.
- **Ustalenie:** t() w src/i18n.php sam wykonuje htmlspecialchars ENT_QUOTES; podstawienia partnera do placeholder/aria są tu już zakodowane, nie przypisywać im XSS tylko na podstawie braku dodatkowego htmlspecialchars.
- **Zależności do sprawdzenia:** `public/chat.php`, `src/i18n.php`, `assets/js/chat.js`, `tests/Unit/ChatViewFallbackTest.php`, `templates/components/chat.php`.
- **Granica dowodu:** Pełen odczyt statyczny; PHP view/DOM i browser nieuruchomione.


### `tests/MySqlIntegration/AdminChatSessionMySqlTest.php`

**Blob:** `95b3981ce408b049fa99a6a0af87efe024b51946` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 89 linii.

- **Ustalenie:** setUp tworzy connection-local TEMPORARY admins, admin_trusted_devices, chat_messages i chat_rooms; testuje sanitizację nadawania admina (w tym granicę 500 znaków), zaufane urządzenie oraz odrzucenie nieaktywnego/zablokowanego admina (41–88).
- **Ustalenie:** DSN wybierany z OIL_ADMIN_TEST_DSN albo API_TEST_MYSQL_DSN bez sprawdzenia hosta i bazy (19–30); nie uruchamiać na niepotwierdzonym środowisku.
- **Zależności do sprawdzenia:** `src/AdminAuth.php`, `src/AdminChatBroadcast.php`, `src/ChatMessageHtml.php`.
- **Granica dowodu:** Statyczny odczyt; testu MySQL nie uruchomiono, brak dowodu izolacji z samej nazwy zmiennej.


### `tests/MySqlIntegration/MySqlChatComplianceTest.php`

**Blob:** `8ede2f3b2c1d213aac5e85dd0a57fd33e5111c7a` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 229 linii.

- **Ustalenie:** Fixture ChatTemporaryPDO zastępuje CREATE TABLE IF NOT EXISTS → CREATE TEMPORARY TABLE IF NOT EXISTS; setUp tworzy tymczasowe players/chat_messages/chat_bans/nav_items i wykonuje migrację ChatBootstrap na tym połączeniu (10–42).
- **Ustalenie:** Asercje obejmują strict SQL, idempotencję migracji, obecność, oba read-ledgery i rollback, limity, retencję/pin, dziennik moderacji oraz wielojęzyczne pokoje (45–229).
- **Ustalenie:** API_TEST_MYSQL_DSN nie jest walidowany względem hosta/bazy; DDL ALTER/CREATE INDEX jest wykonywany. Wymaga osobnej inspekcji środowiska i rzeczywistej izolacji przed uruchomieniem.
- **Zależności do sprawdzenia:** `src/ChatBootstrap.php`, `src/ChatService.php`, `src/ChatRetentionService.php`, `src/ChatRequestLimiter.php`, `src/ChatLimiterMigrationService.php`.
- **Granica dowodu:** Pełny odczyt statyczny; suite MySQL nie została uruchomiona.


### `tests/Unit/AdminChatFormTest.php`

**Blob:** `e612813fa89cad5fdd5e42df2495501e0cfd9fe8` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 33 linii.

- **Ustalenie:** Renderuje admin view do DOMDocument i sprawdza różnicę między formularzem broadcastu (bez data-confirm) a banem (z data-confirm), brak inline handlerów oraz szablon tłumaczeń (8–32).
- **Ustalenie:** Nie klika formularzy, nie sprawdza CSRF/routing/moderacji w bazie ani JS edytora.
- **Zależności do sprawdzenia:** `templates/views/admin/chat/main.php`, `src/CSRF.php`.
- **Granica dowodu:** PHPUnit/DOM nie uruchomiono; asercje odnoszą się do HTML widoku.


### `tests/Unit/AdminSessionIsolationTest.php`

**Blob:** `564c5834d7e3e27af10542dde80fe94a48b5287e` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 46 linii.

- **Ustalenie:** W oddzielnych procesach sprawdza czasowy logout admina z zachowaniem sesji gracza/CSRF/locale oraz isLoggedIn przy aktywnej sesji; logout POST+CSRF i ekran loginu sprawdza wyłącznie podciągami w źródłach (8–45).
- **Zależności do sprawdzenia:** `src/AdminAuth.php`, `admin/logout.php`, `admin/login.php`.
- **Granica dowodu:** PHPUnit nieuruchomiony; nie dowodzi faktycznych HTTP 403/405 ani pełnego SSO.


### `tests/Unit/AuthRememberMeSecurityTest.php`

**Blob:** `6186eab5e0531e66e4c9ed0102e90aaae37728ef` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 22 linii.

- **Ustalenie:** Wycina fragment Auth::tryRememberMe z pliku i szuka literalnych warunków aktywnego oraz zweryfikowanego gracza (8–21).
- **Ustalenie:** Nie wykonuje sesji/cookie/SQL; literalne dopasowanie nie dowodzi skuteczności filtrowania i łatwo traci pokrycie po refaktorze.
- **Zależności do sprawdzenia:** `src/Auth.php`.
- **Granica dowodu:** PHPUnit nieuruchomiony; statyczna asercja tekstu.


### `tests/Unit/ChatRedesignTest.php`

**Blob:** `48fc146038a395818544914a089db3335d90703c` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 238 linii.

- **Ustalenie:** Asercje statyczne sprawdzają klucze lokalizacji, napisy w dwóch widokach, część SQL przez szukanie podciągów, regexy routingu oraz skan linii widoków pod kątem polskich znaków (15–237).
- **Ustalenie:** Test sprawdzający SQL nie wykonuje zapytań; liczy literalne substringi i może nie uchwycić zmian semantycznych. Skan diakrytyków usuwa komentarze pojedynczą regexową heurystyką.
- **Zależności do sprawdzenia:** `src/ChatService.php`, `src/ChatBootstrap.php`, `templates/views/chat/main.php`, `templates/views/admin/chat/main.php`, `src/init.php`.
- **Granica dowodu:** PHPUnit nie uruchomiono; testy statycznych kontraktów, nie end-to-end.


### `tests/Unit/ChatViewFallbackTest.php`

**Blob:** `7cb13a17a419efbfbabc45c1c0bb58584e611c04` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 31 linii.

- **Ustalenie:** Włącza główny widok czatu z fixture history zawierającą <script>, parsuje HTML i sprawdza escapowanie, formularz CSRF, textarea maxlength 1000, dialog oraz dane konfiguracji (9–30).
- **Ustalenie:** Nie wykonuje JS, żądań HTTP, bazy ani parsowania HTML w docelowych przeglądarkach; DOMDocument może różnić się od browser DOM.
- **Zależności do sprawdzenia:** `templates/views/chat/main.php`, `src/CSRF.php`.
- **Granica dowodu:** PHPUnit/DOM nie uruchomiono.


### `tests/Unit/ChatWidgetResilienceTest.php`

**Blob:** `654601c712017e4f56a47e2201ed4e7408cffcdf` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 23 linii.

- **Ustalenie:** Sprawdza tekstowo, że widget dashboardu osadza wspólny widok, istnieją flagi dashboardEmbed i dane w public/index.php oraz odpowiednie klasy CSS; nie renderuje dashboardu (8–22).
- **Zależności do sprawdzenia:** `templates/components/chat.php`, `public/index.php`, `templates/views/chat/main.php`, `assets/css/chat.css`.
- **Granica dowodu:** PHPUnit i przeglądarka nieuruchomione; wyłącznie kontrakty tekstowe.


### `tests/fixtures/api_auth_security_router.php`

**Blob:** `2ae89c6ac13b7add1aa2b8bea84a5edba89265b0` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 13 linii.

- **Ustalenie:** Fixture HTTP uruchamia SQLite z API_TEST_SQLITE, podmienia singleton Database przez Reflection i ładuje api/v1/auth/login.php (4–13).
- **Ustalenie:** Brak guardu ograniczającego ten plik do cli-server; ekspozycja w deployu zależy od webroot/routingu.
- **Zależności do sprawdzenia:** `src/Database.php`, `api/v1/auth/login.php`, `tests/Integration/ApiAuthSecurityTest.php`.
- **Granica dowodu:** Fixture tylko odczytany; brak uruchomienia serwera i potwierdzenia ekspozycji w produkcji.


### `tests/fixtures/api_auth_security_worker.php`

**Blob:** `7521f5f15b13134653f0fdfcac36b1faa826ca1d` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 44 linii.

- **Ustalenie:** Fixture consume łączy się z DSN podanym w argv i czeka na plik startowy, po czym zużywa bucket limitera; inne gałęzie instalują handler błędów lub eval prefiksu init.php, emitując kontrolowane ostrzeżenie/wyjątek (8–44).
- **Ustalenie:** Brak walidacji argv[2] jako bezpiecznej bazy; jawne fałszywe sekrety służą testowi redakcji. Nie uruchamiać consume na arbitralnym DSN.
- **Zależności do sprawdzenia:** `src/ApiAuthRateLimiter.php`, `src/ApiErrorHandler.php`, `src/init.php`, `tests/Integration/ApiAuthSecurityTest.php`.
- **Granica dowodu:** Fixture odczytany statycznie; podprocesu PHP nie uruchomiono.


### `assets/js/bank.js`

**Blob:** `13b17db3c66c1b65eca8a88829fab5c267598724` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 78 linii.

- **Ustalenie:** JS banku pokazuje modal szacowanej spłaty i po potwierdzeniu submit formularza; odpala także podgląd sumy wielu rat i zamykanie Escape (6–78).
- **Ustalenie:** form.submit() omija natywne zdarzenia submit po potwierdzeniu; nie zakładać, że wszystkie listener-y/validatory strony zostaną wykonane. Serwer musi niezależnie weryfikować kwotę i CSRF.
- **Zależności do sprawdzenia:** `public/bank.php`, `templates/views/bank/main.php`, `src/Bank/RepaymentTrait.php`.
- **Granica dowodu:** node --check kod 0; brak browser DOM i HTTP, nie oceniano rzeczywistej kwoty serwera.


### `src/AdminBankAdjustment.php`

**Blob:** `a46501ece360ae0ea080bff1cdd990130fd2b60e` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 63 linii.

- **Ustalenie:** Waliduje kwotę do 2 miejsc i limit 0.01–9,999,999,999.99, działanie admin_credit/debit, notatkę i aktora; rozpoczyna własną transakcję, korzysta z FTS, wstawia strict admin_logs i commit/rollback (12–63).
- **Ustalenie:** Korekta odrzuca zewnętrzną transakcję, co wymaga zachowania w nowej architekturze; spójność audytu jest silniejsza niż przy AdminLog::log tłumiącym błędy.
- **Zależności do sprawdzenia:** `src/FinancialTransactionService.php`, `tests/Integration/AdminBankAdjustmentTest.php`.
- **Granica dowodu:** PHPUnit/MySQL nieuruchomione; sprawdzono kod i asercje SQLite.


### `src/BankAccountService.php`

**Blob:** `795b276a801c76d994302c3e2fff46a9cadaf838` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 390 linii.

- **Ustalenie:** Konstruktor BankAccountService wywołuje ensureSchema; dla MySQL dodaje kolumnę players, UNIQUE i tabelę bank_transactions. Flagę per spl_object_id ustawia przed DDL, a wyjątek DDL/indeksu loguje i tłumi (34–159).
- **Ustalenie:** Database::addColumnIfMissing używa globalnego singletonu zamiast wstrzykniętego PDO; przy innej instancji MySQL schemat może być zmieniony na innym połączeniu niż odczyt/transakcja (90–95; Database.php:110–130).
- **Ustalenie:** ensureAccount wybiera MAX z obecnego roku i robi UPDATE warunkowy z 8 retry po kolizji UNIQUE. Testy SQLite nie pokrywają współbieżności ani ponowienia na snapshot REPEATABLE READ (211–273,360–389). migrateExisting materializuje pełną tabelę players i bez transakcji iteruje po wszystkich graczach (309–345).
- **Zależności do sprawdzenia:** `src/Database.php`, `src/FinancialTransactionService.php`, `tests/Integration/BankAccountServiceTest.php`.
- **Granica dowodu:** Brak MySQL i testów równoległych; ryzyko snapshot/retry zależy od konfiguracji izolacji transakcji.


### `src/BankHelpers.php`

**Blob:** `faf648ce83c6593940ac1b7d7f4e32d845a0c77f` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 47 linii.

- **Ustalenie:** Cztery funkcje pomocnicze mapują statusy/typy na badge HTML, etykiety i encje ikon; domyślne obce statusy są htmlspecialchars (9–47).
- **Ustalenie:** t() samo koduje HTML; zwrócony string zawiera zaufane znaczniki badge, więc konsument musi wiedzieć, czy go escape czy renderować jako już bezpieczny HTML.
- **Zależności do sprawdzenia:** `src/i18n.php`, `templates/views/bank/main.php`.
- **Granica dowodu:** Nie renderowano w przeglądarce ani PHP.


### `src/BankService.php`

**Blob:** `83770bc03afb1ce078cbd4e84a66ec43cb21d533` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 38 linii.

- **Ustalenie:** Cienka fasada bankowa ładuje trzy traity kalkulacji, aplikacji i spłat; konstruktor pobiera globalne PDO i loguje, bez oddzielnego DB inject (1–38).
- **Ustalenie:** Sama klasa nie zawiera logiki obliczeń/spłat; pełne zachowanie zależy od niekartowanych traitów.
- **Zależności do sprawdzenia:** `src/Bank/CalculationTrait.php`, `src/Bank/ApplicationTrait.php`, `src/Bank/RepaymentTrait.php`, `src/Database.php`.
- **Granica dowodu:** Pełny odczyt fasady, nie traitów; tick/bank nieuruchomiony.


### `src/BankSettings.php`

**Blob:** `f6a74a34de64cb720ccbf34490eb77408808fbba` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 168 linii.

- **Ustalenie:** BankSettings pobiera i cache’uje globalne parametry banku, set aktualizuje jeden klucz i czyści cache po niezerowym rowCount; status systemu pochodzi z progów APR/ryzyka/kwoty (24–167).
- **Ustalenie:** W domyślnym PDO MySQL bez FOUND_ROWS, UPDATE do tej samej wartości zwraca rowCount=0, więc set może zgłosić 'key not found' mimo istniejącego klucza (52–68). Wymaga testu MySQL i rozróżnienia no-op od braku rekordu.
- **Zależności do sprawdzenia:** `src/Database.php`, `admin/bank.php`, `src/Bank/CalculationTrait.php`.
- **Granica dowodu:** MySQL i PHP nieuruchomione; nie mierzono zachowania rowCount na tym serwerze.


### `src/FinancePolicyService.php`

**Blob:** `3b5414ba6674210dea267ae560da63618fc78551` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 665 linii.

- **Ustalenie:** Konstruktor domyślnie uruchamia DDL player_finance_settings/decisions, oznacza połączenie jako schemaEnsured przed próbą i po błędzie tylko loguje; false oznacza ścieżkę read-only (112–170).
- **Ustalenie:** saveSettings i savePolicySettings zapisują settings przed wpisami decisions bez jawnej transakcji; po błędzie logu pierwszy zwraca $new, drugi może zwrócić ok=false, mimo że wcześniejszy upsert mógł pozostać (211–369).
- **Ustalenie:** Cooldown savings planu sprawdzany na uprzednio pobranym row bez FOR UPDATE/warunkowego CAS; dwa jednoczesne żądania mogą odczytać can_change przed zmianą (285–304,476–499).
- **Zależności do sprawdzenia:** `src/Database.php`, `src/FinanceService.php`, `public/finance.php`, `admin/finance.php`.
- **Granica dowodu:** Statyczne scenariusze race/partial write, nie potwierdzono ich w izolowanym SQL; PHP/MySQL nieuruchomione.


### `src/FinanceService.php`

**Blob:** `a9949d9592935559dba8260de9707eb8ddac9182` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 1207 linii.

- **Ustalenie:** Domyślny konstruktor FinanceService uruchamia ALTER przez Database::addColumnIfMissing oraz globalne UPDATE historycznych finance_logs (28–44); flaga schemaEnsured jest ustawiana przed operacjami i błędy są tłumione.
- **Ustalenie:** UPDATE delivered_bbl=bbl_produced dla wszystkich zer przy bbl_produced>0 nie odróżnia historycznego braku wartości od rzeczywistego zerowego dostarczenia. Read-only public/admin finance podają false, ale inne wywołania domyślne wykonują maintenance (8–45).
- **Ustalenie:** saveTick zapisuje produced/delivered i koszty; getSummary cofa się do bbl_produced, jeśli delivered_bbl=0 (47–192). Panele/agregaty i estymacje ryzyka bazują na danych finance_logs; brak gwarancji, że dane historyczne można bezstratnie odtworzyć.
- **Zależności do sprawdzenia:** `src/Database.php`, `src/Tick/PlayerTickProcessingTrait.php`, `public/finance.php`, `admin/finance.php`, `tests/MySqlIntegration/MySqlFinanceDashboardReadTest.php`.
- **Granica dowodu:** Pełen odczyt 1207 linii; bez PHP/MySQL/porównania danych produkcyjnych.


### `src/Tick/BankSection.php`

**Blob:** `55104b08442e731813746d887db3f0a8cde34f61` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 266 linii.

- **Ustalenie:** BankSection uruchamia kolejno odsetki, raty, komornika, decyzje, wygaszenie, negocjacje, HR i upadłość; każdy etap lub gracz ma catch logujący i kontynuujący (36–266).
- **Ustalenie:** Parametr limit ogranicza tylko HR cleanup/ready/headhunter; decyzje kredytowe, negocjacje i lista bankrutów są SELECT ... fetchAll bez LIMIT (78–96,135–148,223–230). Zachowanie pod obciążeniem i atomowość całego ticku wymagają testu izolowanego.
- **Zależności do sprawdzenia:** `src/Tick/Modules/BankModule.php`, `src/LoanRepository.php`, `src/InterestEngine.php`, `src/BankNegotiationService.php`.
- **Granica dowodu:** Tick nieuruchomiony; nie mierzono wydajności ani skutków połowicznego błędu.


### `src/Tick/Modules/BankModule.php`

**Blob:** `3c4b8e1f8a30f8cd5d47d38e7314b9692cf857f1` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 58 linii.

- **Ustalenie:** BankModule deleguje run do BankSection, zbiera metryki i deklaruje failurePolicy STOP (8–58).
- **Ustalenie:** BankSection chwyta większość wyjątków wewnętrznie, więc STOP może nie zobaczyć błędów poszczególnych operacji; stats() zwraca interest_processed/installments_processed jako stałe 0 bez liczników z sekcji (43–55).
- **Zależności do sprawdzenia:** `src/Tick/BankSection.php`, `src/Tick/TickModule.php`, `src/Tick/TickContext.php`.
- **Granica dowodu:** Nie uruchomiono TickContext ani orkiestratora; warunkowe zachowanie polityki awarii.


### `tests/Integration/AdminBankAdjustmentTest.php`

**Blob:** `295166491cdff9385bcaf53a9992a75b26976a03` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 62 linii.

- **Ustalenie:** SQLite in-memory testuje dwie korekty salda z logiem aktora i id transakcji, rollback na błędzie INSERT admin_logs oraz brak logu przy niewystarczających środkach (23–61).
- **Zależności do sprawdzenia:** `src/AdminBankAdjustment.php`, `src/FinancialTransactionService.php`, `tests/Integration/SqliteIntegrationTestCase.php`.
- **Granica dowodu:** PHPUnit nieuruchomiony; nie bada konkurencji ani MySQL/DDL.


### `tests/Integration/BankAccountServiceTest.php`

**Blob:** `836190838244a6f9934b7ecc13ca11c318864b79` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 214 linii.

- **Ustalenie:** Na SQLite in-memory testuje format OC-6 cyfr-rok, wzrost sekwencji, niezmienność, UNIQUE, lookup i idempotentny backfill do 25 graczy (35–193).
- **Ustalenie:** Nie wymusza prawdziwego równoległego generowania ani MySQL; schema service na SQLite jest celowo no-op, więc nie testuje produkcyjnego DDL (203–213).
- **Zależności do sprawdzenia:** `src/BankAccountService.php`, `tests/Integration/SqliteIntegrationTestCase.php`.
- **Granica dowodu:** PHPUnit nieuruchomiony; brak obciążenia współbieżnego i testu błędów DDL.


### `tests/Integration/CompanyCredibilityServiceTest.php`

**Blob:** `bfd24f954475e12f97651cedd9e2f345dc38ebb3` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 267 linii.

- **Ustalenie:** Testy SQLite sprawdzają clamp wyniku wiarygodności, skuteczną deltę w logu, eventy, progi 0–100, brak powiadomień, jednorazowość bonusu w okresie, nieznany event i brak tabeli powiadomień (31–185).
- **Ustalenie:** Komentarz wstępny wspomina próg powiadomienia, lecz asercje oczekują zera powiadomień nawet przy dużej zmianie; migracja powinna zachować asercje, nie traktować opisu jako obecnego wymagania.
- **Zależności do sprawdzenia:** `src/CompanyCredibilityService.php`, `tests/Integration/SqliteIntegrationTestCase.php`.
- **Granica dowodu:** PHPUnit nieuruchomiony; fixture schematu SQLite nie dowodzi prod MySQL.


### `tests/Integration/ContractFinancesTest.php`

**Blob:** `15ba5ec2d16f26a3f91cdf741eebc4e3b89d4c31` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 237 linii.

- **Ustalenie:** Sprawdza stałe typów kontraktów i routing WalletConfig do banku, credit wpływów oraz debitCombined bank-first na SQLite, plus jeden test istnienia tłumaczenia (27–194).
- **Ustalenie:** Nie testuje pełnego cyklu kontraktu ani punktów zewnętrznych; część asercji tylko na stałych i mapach.
- **Zależności do sprawdzenia:** `src/FinancialTransactionService.php`, `src/WalletConfig.php`, `src/init.php`.
- **Granica dowodu:** PHPUnit nieuruchomiony; efekt funkcji w SQLite ≠ MySQL.


### `tests/Integration/DisasterTransactionTest.php`

**Blob:** `704b004b7f8a8734645bd7d3fac2a4200c4fdfb7` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 72 linii.

- **Ustalenie:** SQLite z wstrzykniętym WellService przez Reflection i deterministycznym mt_srand sprawdza brak rollbacku zewnętrznej transakcji po pominiętej katastrofie oraz propagację błędu DB przez service/handler (18–70).
- **Ustalenie:** Nie wykonuje produkcyjnego ticku ani probabilistycznego stress testu; reset mt_srand nie przywraca poprzedniego stanu RNG.
- **Zależności do sprawdzenia:** `src/WellService.php`, `src/Tick/WellProductionSection.php`, `src/Tick/WellRiskHandler.php`.
- **Granica dowodu:** PHPUnit nieuruchomiony; seed kontroluje gałąź testu.


### `tests/Integration/FinancialTransactionSavepointTest.php`

**Blob:** `5612f6bb1d00797922b4af6e22c3786b9c94901c` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 164 linii.

- **Ustalenie:** Na SQLite trigger RAISE(ABORT) w INSERT bank_transactions wymusza błąd po zmianie salda; cztery testy sprawdzają rollback savepointu, żywotność transakcji wołającego, późniejszy sukces i niezależny rollback debitCombined (22–117).
- **Zależności do sprawdzenia:** `src/FinancialTransactionService.php`, `tests/Integration/SqliteIntegrationTestCase.php`.
- **Granica dowodu:** PHPUnit nieuruchomiony; nie dowodzi zachowania MySQL pod DDL/lock wait.


### `tests/Integration/TickSettlementTest.php`

**Blob:** `cc88b6774f501bcdaaac29d8d1b995df5054df84` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 51 linii.

- **Ustalenie:** Trzy testy SQLite sprawdzają ograniczenie opłat do 10.01, brak zmiany gracza 2, rollback salda/ticku na błędzie audytu oraz rollback przez zewnętrzną transakcję (21–50).
- **Zależności do sprawdzenia:** `src/FinancialTransactionService.php`, `tests/Integration/SqliteIntegrationTestCase.php`.
- **Granica dowodu:** PHPUnit nieuruchomiony; brak wielograczowego równoległego settlement.


### `tests/MySqlIntegration/MySqlFinanceDashboardReadTest.php`

**Blob:** `ab279027a70bb186e3d9300e5a57815ae831166f` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 61 linii.

- **Ustalenie:** Dziedziczy niebezpieczny MySqlIntegrationTestCase: setUp wykonuje DELETE według IDs z config/database.php zanim zacznie test. Sam test najpierw wywołuje domyślne konstruktory usług i INSERT, potem liczy session status counters wokół dashboardu w ONLY_FULL_GROUP_BY (10–60).
- **Ustalenie:** Asercja 'bez writes' obejmuje tylko fragment po zainicjowaniu FinanceService/FinancePolicyService, nie cały request lub setUp; nie wykryje wcześniejszego DDL/backfill w konstruktorach (15–35).
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `src/FinanceService.php`, `src/FinancePolicyService.php`, `admin/partials/finance_admin_metrics.php`.
- **Granica dowodu:** MySQL nieuruchomiony; nie ufać nazwie testu jako izolacji bazy.


### `tests/MySqlIntegration/MySqlFinanceDeliveredVolumeTest.php`

**Blob:** `cafc82e8f582ee5194bf1c74b347aa7f211a1e3a` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 61 linii.

- **Ustalenie:** Po wspólnym setUp MySQL zapisuje tick z produkcją 100, dostawą 77, stratami i sprawdza getLastTick/getSummary na wybranych polach (6–60).
- **Ustalenie:** Nie testuje wartości 0 przy dodatnim bbl_produced, historycznego backfill ani przyczynowej poprawności rozliczenia; odziedziczony setUp może czyścić realną DB.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`, `src/FinanceService.php`.
- **Granica dowodu:** MySQL/PHPUnit nieuruchomiony; brak bezpiecznego izolowanego DSN.


### `tests/Unit/AdminFinanceReadPathTest.php`

**Blob:** `a6a8b8be603116cfd0d6b88a3dd3f98b5fa84797` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 21 linii.

- **Ustalenie:** Dwie asercje tekstowe wymagają FinanceService(false)/FinancePolicyService(false) w admin finance oraz przekazania policySvc do funkcji metryk (8–20).
- **Ustalenie:** Nie uruchamia kontrolera, zapytań ani nie bada pozostałych ścieżek konstruktorów domyślnych.
- **Zależności do sprawdzenia:** `admin/finance.php`, `admin/partials/finance_admin_metrics.php`, `src/FinanceService.php`.
- **Granica dowodu:** PHPUnit nieuruchomiony; kontrakt literalnych substringów.


### `src/BailiffService.php`

**Blob:** `47a79ea771dc61a5d47ab53d639cc8cf3d5713f1` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 453 linii.

- **Ustalenie:** Etap 2 egzekucji oblicza 30% cash, debituje tę kwotę i zmniejsza remaining_amount z GREATEST(0,...); nie ogranicza zajęcia do aktualnego długu (172–229). Etap 3 zajmuje 50% magazynu, też bez ograniczenia do długu (233–281).
- **Ustalenie:** Fazy nie są jedną transakcją: debit FTS może commitować przed aktualizacją loans, zaś magazyn odejmowany jest przed redukcją długu. Przy błędzie po części zapisów lub małym długu pozostają niespójności/nadpłata — scenariusze statyczne do izolowanego testu.
- **Zależności do sprawdzenia:** `src/FinancialTransactionService.php`, `src/Bank/RepaymentTrait.php`, `tests/MySqlIntegration/MySqlBailiffSeizureClearsLoanTest.php`.
- **Granica dowodu:** MySQL nieuruchomiony; brak pomiaru faktycznej wielkości egzekucji.


### `src/Bank/ActionsHandler.php`

**Blob:** `c47a11d9b554aeb52c2c7df89570509e158e733f` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 508 linii.

- **Ustalenie:** Obsługuje formularze banku (wnioski, oferty, spłaty, negocjacje, przelewy i przesunięcia portfela), z kontrolą CSRF w centralnym dispatch i mapowaniem akcji na serwisy (16–508).
- **Ustalenie:** Kwoty transferu/wypłaty/wpłaty rzutowane do float przed wywołaniem FTS; nie ma tu ścisłego parsera formatu tekstowego ani walidacji kwoty względem oferty, więc granica danych wejściowych zależy od dalszej warstwy.
- **Zależności do sprawdzenia:** `src/BankService.php`, `src/FinancialTransactionService.php`, `src/WalletService.php`, `src/BankNegotiationService.php`.
- **Granica dowodu:** Nie wykonano formularzy w HTTP ani PHP; FTS waliduje dodatniość po zaokrągleniu.


### `src/Bank/ApplicationTrait.php`

**Blob:** `c104e7da2108ae6db0d4434c0d89413254a092ac` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 540 linii.

- **Ustalenie:** Wniosek sprawdza wiek, kryzys, komornika, inne wnioski/kredyty, cooldown i odwierty, a następnie INSERT pending z godziną do decyzji (15–181). Brak transakcji/blokady między SELECT stanu a INSERT pozwala statycznie na duplikaty przy współbieżnych POST-ach.
- **Ustalenie:** Akceptacja oferty blokuje jeden approved rekord i w transakcji tworzy loan + credit FTS + status accepted (370–489). W ścieżce wygasłej oferty ustawia expired, lecz potem throw i rollback cofają również status (397–407,480–483).
- **Ustalenie:** Odrzucenie odczytuje approved, potem UPDATE po id/player bez warunku status; równoległa akceptacja może zmienić accepted w rejected po commicie (497–530).
- **Zależności do sprawdzenia:** `src/BankService.php`, `src/FinancialTransactionService.php`, `src/Database.php`, `tests/MySqlIntegration/MySqlLoanDecisionClaimTest.php`.
- **Granica dowodu:** Race to scenariusz statyczny, nie przeprowadzono współbieżnego MySQL; funkcja auto-ALTER przed transakcją nie była testowana.


### `src/Bank/CalculationTrait.php`

**Blob:** `6a8e9be1a82c3cda6dc69e9853dfa9138d91a640` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 137 linii.

- **Ustalenie:** Formuła raty annuitetowej zwraca zaokrągloną kwotę; limit kredytu wycenia odwierty, produkcję, gotówkę i magazyn, stosuje karę płynności, cap oraz redukcję po recovered (28–131).
- **Ustalenie:** Przy dowolnym wyjątku kalkulacji limitu zwraca awaryjne 5,000,000 zamiast odmówić wniosku (132–135); awaria odczytu market_state/storage może więc pozwolić na kwotę bez kompletnego scoringu.
- **Zależności do sprawdzenia:** `src/Bank/ApplicationTrait.php`, `src/BankService.php`, `src/BankSettings.php`.
- **Granica dowodu:** Statyczny fallback; nie testowano awarii zależności w MySQL ani wartości granicznych APR.


### `src/Bank/DataLoader.php`

**Blob:** `d014cfc8c4f16130c69e8fccd6424791b48aeb83` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 587 linii.

- **Ustalenie:** Loader widoku banku scala stan wniosku, kredyty, negocjacje, limity, salda i paginowaną historię transakcji (18–86). loadAccountData wywołuje ensureAccount — odczyt strony może wygenerować numer konta i uruchomić ensureSchema/DDL (95–172).
- **Ustalenie:** Część błędów SQL zwraca pusty/zero stan, np. hasEverHadLoan false, canApply bez powodu blokady albo historia pusta; widok nie odróżnia braku danych od awarii (207–243,452–524).
- **Zależności do sprawdzenia:** `src/BankAccountService.php`, `src/WalletService.php`, `src/BankService.php`, `public/bank.php`.
- **Granica dowodu:** Nie uruchomiono GET ani zmierzonego planu SQL; pełny odczyt loadera.


### `src/Bank/RepaymentTrait.php`

**Blob:** `ee68751b91e2cae842106e58d11d3c153a953e70` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 248 linii.

- **Ustalenie:** Spłata ręczna w transakcji blokuje gracza i kredyt, debituje przez FTS/savepoint, aktualizuje loan i anuluje negocjacje po pełnej spłacie (32–183).
- **Ustalenie:** Nawet spłata częściowa ustawia aktywne bailiff_proceedings na completed (153–159), choć remaining_amount może pozostawać dodatni; sprawdzić intencję biznesową. Early-repay credibility hook działa dopiero po commit i błędy są tłumione (185–193).
- **Zależności do sprawdzenia:** `src/Bank/ApplicationTrait.php`, `src/FinancialTransactionService.php`, `src/BailiffService.php`.
- **Granica dowodu:** PHP/MySQL nieuruchomione; nie badano deadlocków przy równoległym ticku.


### `src/BankNegotiation/MessagesTrait.php`

**Blob:** `197422a23faaa68ab9127d508373af4268cbd684` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 165 linii.

- **Ustalenie:** Prywatne generatory komunikatów otwarcia, komentarzy CFO, decyzji i powodów odrzucenia losują warianty tłumaczeń, a powód publiczny pomija dokładną wewnętrzną wartość trust (13–164).
- **Ustalenie:** Ref BNK/rok/4-cyfry pochodzi z rand i nie jest tu unikalnym identyfikatorem; traktować jako ozdobny tekst, nie identyfikator transakcji.
- **Zależności do sprawdzenia:** `src/BankNegotiationService.php`, `src/BankNegotiation/ContextTrait.php`, `src/i18n.php`.
- **Granica dowodu:** Brak testów renderowania/kolizji referencji, kodu PHP nie uruchomiono.


### `src/BankNegotiationService.php`

**Blob:** `41c990c8b9aabecfb05723628e33238bc3120134` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 119 linii.

- **Ustalenie:** Klasa łączy pięć traitów negocjacyjnych, stałe opłat, okresu ważności i zmian trust; konstruktor przyjmuje opcjonalny PDO, a bonus CFO czyta StrikeEffectService (1–119).
- **Zależności do sprawdzenia:** `src/BankNegotiation/ContextTrait.php`, `src/BankNegotiation/RequestsTrait.php`, `src/BankNegotiation/ProcessorTrait.php`, `src/BankNegotiation/RandomEventsTrait.php`.
- **Granica dowodu:** Zachowanie żądań/akceptacji mieści się w osobnych niekartowanych traitach; nie uruchomiono PHP.


### `src/LoanDecisionService.php`

**Blob:** `af95f10fbacbc855cc7596b40c22ef6f9d46d613` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 268 linii.

- **Ustalenie:** Warunkowy UPDATE claim przesuwa decision_at o godzinę i wymaga pending+due, po czym zapisuje decyzję tylko przy status pending i rowCount=1 (35–123,219–257). Test MySQL bada tylko sekwencyjną ponowną próbę.
- **Ustalenie:** makeDecision używa progów/APR BankSettings, scoringu i sentymentu rynku; w kryzysie redukuje kwotę, w boom zwiększa ją o 10%; lease może wygasnąć przy długim obliczaniu (131–210).
- **Zależności do sprawdzenia:** `src/RiskScoreEngine.php`, `src/BankSettings.php`, `tests/MySqlIntegration/MySqlLoanDecisionClaimTest.php`.
- **Granica dowodu:** Brak równoległego testu ani analizy maksymalnego czasu obliczeń.


### `src/LoanRepository.php`

**Blob:** `eeaf810aa24fc5e56e79b22ffb1e167f0047d4b0` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 193 linii.

- **Ustalenie:** Pobiera wszystkie wymagalne active loans przed transakcjami, potem dla każdego debituje pełną installment_amount i aktualizuje remaining_amount do nie mniej niż zero (25–130). Nie weryfikuje statusu ani next_installment_at ponownie po pobraniu wiersza, nie ogranicza debetu do pozostałej kwoty.
- **Ustalenie:** Współbieżny tick lub spłata ręczna może więc spowodować podwójne/za wysokie pobranie, podczas gdy GREATEST ukryje nadpłatę w długu; wymaga testu transakcyjnego i blokady/warunku przed debetem. Brak środków zmienia status late; wiarygodność po commit (133–174).
- **Zależności do sprawdzenia:** `src/Player.php`, `src/FinancialTransactionService.php`, `src/Bank/RepaymentTrait.php`, `src/Tick/BankSection.php`.
- **Granica dowodu:** Statyczne ryzyko wyścigu/ostatniej raty; MySQL/tick nieuruchomione.


### `tests/Integration/BugfixWellsHubsPipelinesTest.php`

**Blob:** `0f2513a90792b15e19dd3cb426a1286c63da6188` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 96 linii.

- **Ustalenie:** Testuje, że obcy gracz nie otrzymuje hub detail, zdarzenia odwiertu zwracają trzy wpisy, a limit 0/99999 nie wyrzuca wyjątku (18–96).
- **Ustalenie:** Nie asertuje dokładnej wartości ograniczonego limitu, brak testu HTTP/403 — getHubDetail zwraca null.
- **Zależności do sprawdzenia:** `src/HubViewService.php`, `src/WellService.php`, `tests/Integration/SqliteIntegrationTestCase.php`.
- **Granica dowodu:** SQLite/PHPUnit nieuruchomione.


### `tests/Integration/EmployeeActionReceiptServiceTest.php`

**Blob:** `0988812513061b6057c45d3199eb8293b0ce0d2d` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 35 linii.

- **Ustalenie:** Test sprawdza, że receipt o tym samym payloadzie mimo innej kolejności kluczy jest replayed; zmieniony payload dla tego samego klucza rzuca RuntimeException (10–35).
- **Zależności do sprawdzenia:** `src/HR/EmployeeActionReceiptService.php`, `tests/Integration/SqliteIntegrationTestCase.php`.
- **Granica dowodu:** Nie testuje równoległych prób idempotencji; PHPUnit nieuruchomiony.


### `tests/Integration/EnvironmentSmokeTest.php`

**Blob:** `35cfcca17785c1982343a1282049ebc6854829ad` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 15 linii.

- **Ustalenie:** Smoke test weryfikuje APP_ENV=testing oraz autoload Validator, TransportConfigService i CSRF (8–14).
- **Zależności do sprawdzenia:** `tests/bootstrap.php`, `phpunit.xml.dist`.
- **Granica dowodu:** PHPUnit nieuruchomiony; class_exists nie dowodzi działania serwisów.


### `tests/Integration/PlayerTickTransactionTest.php`

**Blob:** `455845d09c65c16a05c8ccf93e7aaf14235cd653` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 58 linii.

- **Ustalenie:** SQLite scenariusz wymusza awarię zapisu historii po efektach; sprawdza rollback magazynu, gotówki, efektów i bank_transactions, a następnie pojedynczy skuteczny retry (25–46).
- **Zależności do sprawdzenia:** `src/Tick/PlayerTickProcessingTrait.php`, `tests/Integration/SqliteIntegrationTestCase.php`.
- **Granica dowodu:** Testu nie wykonano; nie dowodzi rollbacku DDL ani rzeczywistej transakcji MySQL.


### `tests/Integration/TechnicalIncidentFilterTest.php`

**Blob:** `b66c958321b0f35e77a902b0860af3a1bde159ef` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 34 linii.

- **Ustalenie:** SQLite sprawdza statusowe filtrowanie, liczniki oraz strony incydentów tylko wskazanego gracza, z konkretną kolejnością identyfikatorów (9–34).
- **Zależności do sprawdzenia:** `src/IncidentService.php`, `tests/Integration/SqliteIntegrationTestCase.php`.
- **Granica dowodu:** Nie wykonano PHPUnit ani HTTP/uprawnień widoku.


### `tests/Integration/TickBugfixRound5Test.php`

**Blob:** `0e08e85d63d84ae7d66f7d31fd0d645a7c64294f` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 198 linii.

- **Ustalenie:** Cztery testy SQLite pokrywają zaksięgowanie kosztu incydentu i katastrofy do totalCosts/finIncident/playerCash, brak kosztu gdy katastrofa nie zachodzi, oraz ograniczenie wycieku w transporcie (70–197).
- **Zależności do sprawdzenia:** `src/Tick/PlayerTickProcessingTrait.php`, `src/Tick/WellProductionSection.php`.
- **Granica dowodu:** Scenariusze używają kontrolowanych danych/losowości, nie wykonano PHP/MySQL ani pełnego ticku produkcyjnego.


### `tests/Integration/TickHistoryQueryTest.php`

**Blob:** `1ec4f8910a524bcc6c2330b1d76f0c9a50133d23` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 67 linii.

- **Ustalenie:** SQLite wypełnia 105 wpisów i sprawdza paginację, clamp ostatniej strony, filtr force, usunięcie payloadu diagnostycznego z listy oraz sumaryczne dane 24h (30–66).
- **Ustalenie:** Asercja dla tekstu podobnego do SQL injection oczekuje 105, tak jak brak filtra; dowodzi jedynie braku zmiany wyniku w tych danych, nie pełnej ochrony wszystkich wejść.
- **Zależności do sprawdzenia:** `src/AdminLogs/TickHistoryQuery.php`, `tests/Integration/SqliteIntegrationTestCase.php`.
- **Granica dowodu:** PHPUnit nieuruchomiony; bez obciążenia i bez MySQL.


### `tests/Integration/WorldMapPermitGateTest.php`

**Blob:** `73b52e9fca01a3e6db677e5522ac1555b555099d` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 100 linii.

- **Ustalenie:** SQLite gate zakupu regionu blokuje brak/pending pozwolenia, dopuszcza granted/transitional oraz izoluje pozwolenie po player_id i region_id (27–66).
- **Zależności do sprawdzenia:** `src/WorldMap.php`, `tests/Integration/SqliteIntegrationTestCase.php`.
- **Granica dowodu:** Testuje metodę gate, nie atomowość zakupu i realne współbieżne pozwolenia; PHP nieuruchomione.


### `tests/MySqlIntegration/MySqlBailiffSeizureClearsLoanTest.php`

**Blob:** `1bfa279a7f3efcbc16152dca563d542546d689a7` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 112 linii.

- **Ustalenie:** Dwa testy MySQL zakładają aktywne postępowanie i wywołują komorniczą fazę 2: mały dług przechodzi do paid_off/completed, duży pozostaje late/active z nieujemnym saldem (79–110).
- **Ustalenie:** Asercje nie sprawdzają, czy faktycznie zabrana gotówka jest ograniczona do pozostającego długu ani czy wpis historii odpowiada redukcji długu; nie łapią potencjalnej nadmiernej egzekucji.
- **Zależności do sprawdzenia:** `src/BailiffService.php`, `tests/MySqlIntegration/MySqlIntegrationTestCase.php`.
- **Granica dowodu:** MySQL nieuruchomiony; dziedziczony setup może usuwać dane.


### `tests/MySqlIntegration/MySqlLoanDecisionClaimTest.php`

**Blob:** `7ee88d149c0f9e4412411b7cbe1d7d1694f193f1` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 82 linii.

- **Ustalenie:** Testy MySQL sprawdzają, że przyszły decision_at nie jest claimowany, wymagalny zostaje przejęty i ponowne przetwarzanie zwraca false (46–81).
- **Ustalenie:** Odczyt/ponowienie są sekwencyjne na jednym połączeniu; nie ma równoległego uruchomienia ticka z cronem ani testu odnowienia po godzinie.
- **Zależności do sprawdzenia:** `src/LoanDecisionService.php`, `tests/MySqlIntegration/MySqlIntegrationTestCase.php`.
- **Granica dowodu:** Nie uruchomiono MySQL; dane testu wymagają bezpiecznej izolacji.


### `src/Tick/TickContext.php`

**Blob:** `03907139ae8da4e506e23426654f17cdb54aa2da` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 184 linii.

- **Ustalenie:** Kontext przechowuje PDO, zegar, cenę, metryki i limity modułów; loadBalanceMults pobiera 8 kluczy, clampuje 0.1–10 i cache’uje wynik per instancja (8–184).
- **Ustalenie:** Flagę loaded ustawia przed SELECT; błąd odczytu pozostawia wartości domyślne i blokuje retry w tym kontekście (97–134).
- **Zależności do sprawdzenia:** `src/Tick/TickEngine.php`, `src/Tick/TickModuleScheduler.php`.
- **Granica dowodu:** Testuje tylko wybrane mnożniki SQLite; runtime ticka nieuruchomiony.


### `src/Tick/TickCoordinator.php`

**Blob:** `5e0429aee80464143d4de0a7093adb8cfc788d27` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 592 linii.

- **Ustalenie:** Koordynator serializuje tick MySQL GET_LOCK, zapisuje flagę in-progress, sequence, uruchamia engine i na sukcesie/partial zapisuje statystyki oraz uruchamia retencję (47–125,245–319). GET_LOCK failure powoduje fail-closed.
- **Ustalenie:** Retencja well_incidents/failure_log/industrial_disasters ma stałe 3 dni, mimo pobrania incident_retention_days dla innych kategorii (356–395); historia tick_stats i modułów utrzymywana tylko 2 dni (433–472). Te okna trzeba zachować lub świadomie zmienić w migracji.
- **Ustalenie:** Flagę in-progress czyści przed zapisem statystyk i cleanup; błędy zapisu statystyk są tłumione, więc success ticku nie dowodzi pełnej historii (268–318).
- **Zależności do sprawdzenia:** `src/Tick/TickEngine.php`, `src/Tick/TickStatsRepository.php`, `src/Tick/TickModuleConfigRepository.php`.
- **Granica dowodu:** Nie uruchomiono CRON/MySQL ani destrukcyjnej retencji; statyczny odczyt.


### `src/Tick/TickEngine.php`

**Blob:** `d6bbc2f54898eadf8ee8b3e61987840a77e4c64a` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 244 linii.

- **Ustalenie:** Orkiestruje discover, schedule, run modułów oraz STOP/CONTINUE. Przy wyjątku próbuje rollback aktywnej transakcji i zapisuje status failed (15–205).
- **Ustalenie:** Po udanym module markFinished może rzucić wyjątek zapisu statusu/logu; jest tylko logowany, a TickRunResult pozostaje success, przez co metadane harmonogramu mogą się rozjechać z wykonaną pracą (137–146).
- **Zależności do sprawdzenia:** `src/Tick/TickModuleScheduler.php`, `src/Tick/TickRunResult.php`, `tests/Unit/TickEngineTest.php`.
- **Granica dowodu:** Scenariusz błędu zapisu markFinished jest statyczny, PHP/MySQL nieuruchomione.


### `src/Tick/TickFailurePolicy.php`

**Blob:** `edd776fb4098c736159304695882567df581d21e` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 8 linii.

- **Ustalenie:** Enum rozróżnia STOP i CONTINUE i jest używane przez silnik do przerwania albo częściowego wyniku (4–8).
- **Zależności do sprawdzenia:** `src/Tick/TickEngine.php`, `src/Tick/TickRunResult.php`.
- **Granica dowodu:** Brak samodzielnej logiki runtime; PHP nieuruchomione.


### `src/Tick/TickModuleCatalog.php`

**Blob:** `9078c00121ff9d95b84ebfb0cd859063b7082751` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 37 linii.

- **Ustalenie:** Katalog ma domyślne interwały, limity i flagi critical dla 13 kluczy; brak klucza daje interval=1, limit=200, critical=false (7–36).
- **Zależności do sprawdzenia:** `src/Tick/TickModuleScheduler.php`, `src/Tick/TickRegistry.php`.
- **Granica dowodu:** Dwa klucze (cleanup/daily_stats) są w katalogu bez modułów registry; to konfiguracja, nie dowód ich wykonania.


### `src/Tick/TickModuleConfigRepository.php`

**Blob:** `f82174e158f70b064428d63c1573de086b6a1bce` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 285 linii.

- **Ustalenie:** Konstruktor wykonuje CREATE TABLE IF NOT EXISTS obu tabel przy każdym nowym repozytorium. update clampuje parametry, markFinished osobno UPDATE config i INSERT log, bez jawnej transakcji (15–125,227–285).
- **Ustalenie:** Jeżeli INSERT logu zawiedzie, ostatni tick i status mogą już być zapisane, a engine tylko loguje wyjątek. Retencja i odczyt logów działają na wspólnym PDO (128–225).
- **Zależności do sprawdzenia:** `src/Tick/TickEngine.php`, `src/Tick/TickModuleScheduler.php`, `src/Tick/TickCoordinator.php`.
- **Granica dowodu:** MySQL nieuruchomiony; konstruktor potencjalnie DDL na istniejącej bazie.


### `src/Tick/TickModuleScheduler.php`

**Blob:** `d4e1fabbfffd05e147214e7308a9f386171237e3` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 75 linii.

- **Ustalenie:** decision uruchamia krytyczne moduły niezależnie od enabled/interwału; optional respect disabled i interwał, forced omija tylko interwał. Błąd zostawia last_run_tick bez zmiany (19–75).
- **Ustalenie:** Brak DB claim/lock w samym schedulerze; serializacja zależy od wyższego TickCoordinator::GET_LOCK, którego testy modułowe nie symulują.
- **Zależności do sprawdzenia:** `src/Tick/TickModuleConfigRepository.php`, `src/Tick/TickCoordinator.php`, `tests/Unit/TickModuleSchedulerTest.php`.
- **Granica dowodu:** Nie badano sesji MySQL ani dwóch workerów.


### `src/Tick/TickRegistry.php`

**Blob:** `087a6ca7c0b7fb4a81880017c681aeaeb9b4c801` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 164 linii.

- **Ustalenie:** Registry globuje pliki modułów, require_once, wymaga jednej zgodnej klasy implementującej TickModule, klucza regexowego, dodatniego i unikalnego order; cache’uje klasy, ale tworzy świeże instancje (21–164).
- **Ustalenie:** Odkrywanie uruchamia kod top-level w plikach PHP przez require_once; zachowanie zależy od zaufania do katalogu modułów. Testy sprawdzają wykrywanie, nie skutki ładowania dowolnej zawartości.
- **Zależności do sprawdzenia:** `src/Tick/TickModule.php`, `src/Tick/TickEngine.php`, `tests/Unit/TickRegistryTest.php`.
- **Granica dowodu:** Nie załadowano modułów przez PHP na żywo; pełny odczyt registry.


### `src/Tick/TickRunResult.php`

**Blob:** `67cbfe62ba9cff51a822936ae81adb0b38764bc6` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 133 linii.

- **Ustalenie:** Agreguje sukcesy, błędy, status partial/failed i wyniki per moduł, refresh pobiera z kontekstu cenę oraz stats players (4–133).
- **Ustalenie:** addFailure dla policy STOP ustawia failed, dla CONTINUE partial; assertCanContinue rzuca tylko przy failed. Metryki są snapshotem po finish, nie potwierdzeniem persystencji.
- **Zależności do sprawdzenia:** `src/Tick/TickContext.php`, `src/Tick/TickEngine.php`.
- **Granica dowodu:** PHPUnit nieuruchomiony.


### `src/Tick/TickStatsRepository.php`

**Blob:** `b44e42766c86ba2c47541a6e129df14a084156f4` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 334 linii.

- **Ustalenie:** Konstruktor ensureSchema na MySQL próbuje migracji kolumn i UNIQUE tick_stats, a przed dodaniem indeksu usuwa starsze duplikaty przez DELETE JOIN (14–101). Flaga schemaEnsured jest ustawiona przed testem inTransaction/DDL; jeśli połączenie jest już w transakcji, kolejne próby w tym procesie pomija.
- **Ustalenie:** save używa INSERT IGNORE oraz nie sprawdza rowCount: duplikat ran_at/tick_sequence lub inne ignorowane ograniczenia mogą zniknąć z historii bez błędu (110–170). Cleanup usuwa starsze wpisy (203–229).
- **Zależności do sprawdzenia:** `src/Database.php`, `src/Tick/TickCoordinator.php`, `tests/Unit/TickModuleAdminServiceTest.php`.
- **Granica dowodu:** Nie uruchomiono MySQL ani DELETE duplikatów; nie sprawdzono rzeczywistego indeksu.


### `tests/Integration/EmployeeCompensationAtomicityTest.php`

**Blob:** `8a3d44e21feacdcc0bd8b0136e1db4b77ee6fd48` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 40 linii.

- **Ustalenie:** Trzy przypadki SQLite sprawdzają, że setSalary aktualizuje salary_satisfaction i employee_contracts, ale nie morale/cycle; rollback zewnętrznej transakcji przywraca satysfakcję (8–39).
- **Zależności do sprawdzenia:** `src/HR/EmployeeCompensationService.php`, `src/Employee/EmployeeRef.php`.
- **Granica dowodu:** Brak symulacji wyjątku w środku zapisu i równoległości; PHPUnit nieuruchomiony.


### `tests/Integration/HRApiEndpointTest.php`

**Blob:** `bf082483cd34e76a40890ca7d30b0b68f1d22cc3` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 40 linii.

- **Ustalenie:** Test uruchamia osobny proces PHP z GET src/HRApi.php i bez sesji, wymaga JSON success=false i statusu 401 zapisanych do stderr w shutdown hook (8–39).
- **Zależności do sprawdzenia:** `src/HRApi.php`, `tests/bootstrap.php`.
- **Granica dowodu:** Wymaga PHP_BINARY/proc_open; nieuruchomiony. Sprawdza CLI emulację żądania, nie rzeczywisty serwer HTTP.


### `tests/Integration/HREventDeliveryStateTest.php`

**Blob:** `f3b3e5f456bd5e420bc7ba7938aed573cf9923db` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 65 linii.

- **Ustalenie:** SQLite sprawdza, że markEventsNotified i markEventsRead dotyczą tylko zdarzeń jednego gracza; odczyt oznacza również notified_at, delivery nie oznacza is_read (34–54).
- **Zależności do sprawdzenia:** `src/HR/EmployeeDashboardQueryService.php`.
- **Granica dowodu:** Nie dowodzi uwierzytelnienia endpointu ani granic batcha; PHP nieuruchomione.


### `tests/Unit/TickAdapterMetadataTest.php`

**Blob:** `2e652c6e47225927520625dd1ed487efdb33c38c` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 30 linii.

- **Ustalenie:** Data provider porównuje order/failurePolicy dla siedmiu modułów; nie uruchamia run ani nie sprawdza side effects (9–30).
- **Zależności do sprawdzenia:** `src/Tick/TickRegistry.php`, `src/Tick/TickModule.php`.
- **Granica dowodu:** PHPUnit nieuruchomiony; metadata nie dowodzą poprawności ticku.


### `tests/Unit/TickContextTest.php`

**Blob:** `54ebbca63cb02480213a04feeefb23e458d21efa` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 32 linii.

- **Ustalenie:** SQLite sprawdza clamp 0.01→0.1 i 25→10 oraz cache: po zmianie well_config ponowny loadBalanceMults utrzymuje poprzednią wartość (9–31).
- **Zależności do sprawdzenia:** `src/Tick/TickContext.php`.
- **Granica dowodu:** Brak testu błędnego/missing config i MySQL; nieuruchomione.


### `tests/Unit/TickEngineTest.php`

**Blob:** `949a50f6a740783b97edf29bc24492f606cf17a0` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 153 linii.

- **Ustalenie:** Generowane moduły testują kolejność i stats, CONTINUE po wyjątku, STOP przed następnym modułem, brak modułów, runOne missing oraz rollback niezamkniętej transakcji (26–112).
- **Ustalenie:** Test dynamicznie tworzy katalogi PHP w systemowym temp bez cleanup w tearDown; testowanie nie jest izolowane od zapisu poza repo. Nie bada błędu zapisu markFinished.
- **Zależności do sprawdzenia:** `src/Tick/TickEngine.php`, `src/Tick/TickRegistry.php`.
- **Granica dowodu:** PHPUnit nieuruchomiony; mock modułów nie odtwarza transakcji produkcyjnych.


### `tests/Unit/TickHrWiringTest.php`

**Blob:** `3a962273128620c157316abd356a0de83f8b2c6a` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 63 linii.

- **Ustalenie:** Test kontraktowy czyta źródła jako stringi: zabrania określonych wywołań rekrutacji na GET, wymaga napisów o limitach w ticku, krytyczności employees i oczekiwanego nagłówka JSON (9–63).
- **Zależności do sprawdzenia:** `src/Tick/TickModuleCatalog.php`, `src/Tick/BankSection.php`, `src/RecruitmentAPI.php`.
- **Granica dowodu:** Asercje tekstowe nie dowodzą braku efektów przez pośrednie wywołania ani działania API.


### `tests/Unit/TickModuleAdminServiceTest.php`

**Blob:** `047941eaaf3976f3484364483fe60e71b149e6c2` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 188 linii.

- **Ustalenie:** SQLite sprawdza ochronę konfiguracji krytycznego market, opcjonalne legal, zapis/odczyt logów i profili graczy, paginację i retencję historyczną (27–187).
- **Zależności do sprawdzenia:** `src/TickModuleAdminService.php`, `src/Tick/TickModuleConfigRepository.php`, `src/Tick/TickStatsRepository.php`.
- **Granica dowodu:** Brak MySQL, HTTP i sprawdzenia atomowości markFinished; PHP nieuruchomione.


### `tests/Unit/TickModuleSchedulerTest.php`

**Blob:** `e898a8dfa9be93977a49426c6f3f7148f8582272` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 266 linii.

- **Ustalenie:** Testy SQLite pokrywają interwały, disable, force, krytyczny moduł, retry po failure, ustawienia domyślne, dodatni sequence runAll i izolację runOne od innych ustawień/logów (31–235).
- **Ustalenie:** Generują pliki PHP w temp bez sprzątania; brak testu dwóch konkurencyjnych schedulerów i błędu zapisu logu po udanym wykonaniu.
- **Zależności do sprawdzenia:** `src/Tick/TickModuleScheduler.php`, `src/Tick/TickModuleConfigRepository.php`, `src/Tick/TickEngine.php`.
- **Granica dowodu:** PHPUnit nieuruchomiony; nie ekstrapolować SQLite na MySQL.


### `tests/Unit/TickRegistryTest.php`

**Blob:** `695c8156b9c6a320f94565ae7e1e2ec088de835c` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 141 linii.

- **Ustalenie:** Weryfikuje dokładną kolejność 11 modułów, selektywne odkrywanie, świeże instancje, odrzucenie innej klasy, dodatkowej klasy, zdublowanego klucza/order (15–121).
- **Zależności do sprawdzenia:** `src/Tick/TickRegistry.php`, `src/Tick/TickModule.php`.
- **Granica dowodu:** Test tworzy katalogi w temp bez cleanup; nie testuje inicjalizacji modułu z zależnościami produkcyjnymi.


### `src/Tick/Modules/B2BContractsModule.php`

**Blob:** `1589154cfc9ad750601774d5193ed5a4d710f96d` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 78 linii.

- **Ustalenie:** B2BContractsModule (CONTINUE) dzieli limit modułu między expireOpenOffers i finalizeExpiredAcceptedOffers, zgłasza liczniki refunded/finalized/failed/penalties (21–78).
- **Zależności do sprawdzenia:** `src/B2BContractService.php`, `src/Tick/TickEngine.php`.
- **Granica dowodu:** Wynik failed jest metryką, a nie wyjątkiem; nie dowodzi STATUS_PARTIAL ticka.


### `src/Tick/Modules/BlackMarketModule.php`

**Blob:** `9713a3047d300d2369e27a33c8fc48eed9884437` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 120 linii.

- **Ustalenie:** BlackMarketModule (CONTINUE) wygasza oferty, liczy decay z elapsed od last_system_tick_at, inkrementuje bm_tick_counter i co N ticków generuje oferty wszystkim kwalifikującym się graczom (31–120).
- **Ustalenie:** SELECT eligible player IDs używa fetchAll i nie stosuje ctx.moduleLimit; deklarowany limit katalogu nie ogranicza tej fazy, co jest statycznym ryzykiem czasu/pamięci.
- **Zależności do sprawdzenia:** `src/BlackMarketService.php`, `src/Tick/TickModuleCatalog.php`, `src/Tick/TickCoordinator.php`.
- **Granica dowodu:** Nie mierzono czasu/ilości graczy ani MySQL.


### `src/Tick/Modules/ContractsModule.php`

**Blob:** `7fe3634b7223e17aadcc7da55082d8cea244e11c` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 71 linii.

- **Ustalenie:** ContractsModule (CONTINUE) procesuje wymagalne kontrakty, usuwa historię starszą niż 2 dni i raportuje processed/completed/failed/revenue/penalties (37–71).
- **Ustalenie:** Moduł nie przekazuje limitu harmonogramu do processDueContracts; failed jako licznik nie zmienia statusu modułu na partial. Zachowanie serwisu pozostaje oddzielną zależnością.
- **Zależności do sprawdzenia:** `src/ContractService.php`, `src/Tick/TickModuleCatalog.php`.
- **Granica dowodu:** Nie uruchomiono ticku ani nie analizowano pełnego serwisu kontraktów w tej karcie.


### `src/Tick/Modules/CredibilityModule.php`

**Blob:** `62ae22dc47b63277c146985102bb15f78694e2c3` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 54 linii.

- **Ustalenie:** Adapter CredibilitySection (CONTINUE) wywołuje run, zapisuje playersChecked i cleanBonuses (18–54).
- **Zależności do sprawdzenia:** `src/Tick/CredibilitySection.php`, `src/CompanyCredibilityService.php`.
- **Granica dowodu:** Nie wykonano bonusów ani pełnego testu sekcji.


### `src/Tick/Modules/EmployeesModule.php`

**Blob:** `d18c8be0f3b8254362b427768daf16af19a7a530` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 66 linii.

- **Ustalenie:** EmployeesModule używa runSequence i moduleLimit, zbiera metryki cyklu, błędów i terminów rekrutacji (13–66). Katalog oznacza employees jako critical, lecz sam moduł deklaruje failurePolicy CONTINUE (23–26).
- **Ustalenie:** Jeżeli EmployeeMoraleSection rzuci wyjątek, TickRunResult oznaczy tick jako partial, a koordynator będzie kontynuował i czyścił in-progress; test TickHrWiring sprawdza isCritical, nie failurePolicy. Błędy per-item zapisane tylko w stats nie zmieniają statusu modułu.
- **Zależności do sprawdzenia:** `src/Tick/EmployeeMoraleSection.php`, `src/Tick/TickModuleCatalog.php`, `src/Tick/TickRunResult.php`, `tests/Unit/TickHrWiringTest.php`.
- **Granica dowodu:** Scenariusz awarii statyczny; bez CRON/PHP/MySQL.


### `src/Tick/Modules/LegalModule.php`

**Blob:** `ed83b826c710276ac689b754d62e577917a6235c` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 50 linii.

- **Ustalenie:** LegalModule (CONTINUE) uruchamia LegalSection z mutableNow, zbiera decyzje i powiadomienia graczy/hubów (15–50).
- **Zależności do sprawdzenia:** `src/Tick/LegalSection.php`, `src/Tick/TickContext.php`.
- **Granica dowodu:** Brak egzekucji logiki prawnej; metryki nie gwarantują dostarczenia.


### `src/Tick/Modules/MarinePurgeModule.php`

**Blob:** `011814a7f9bf090dd66de665c9f3c3ca98f58b4f` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 40 linii.

- **Ustalenie:** MarinePurgeModule (CONTINUE) wywołuje purgeStale na PDO i raportuje terminal/stuck (15–40).
- **Zależności do sprawdzenia:** `src/Tick/MarineDeliverySection.php`.
- **Granica dowodu:** Nie uruchomiono purge; brak potwierdzenia retencji/stanu danych.


### `src/Tick/Modules/MarketModule.php`

**Blob:** `370667a32d959de39cd968f236ac37ff73bf997b` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 58 linii.

- **Ustalenie:** MarketModule (STOP) uruchamia MarketSection i ustawia stan ceny/trendu; gdy cena jest niedodatnia, próbuje last_tick_oil_price, potem 70 (14–58).
- **Ustalenie:** Awaria ceny może więc zostać przełożona na domyślną 70 i moduł nadal zakończy się success, co nie daje sygnału STOP. To świadomy fallback w kodzie, do testu biznesowego.
- **Zależności do sprawdzenia:** `src/Tick/MarketSection.php`, `src/Tick/TickContext.php`, `src/Tick/TickRunResult.php`.
- **Granica dowodu:** Nie uruchomiono rynku; nie oceniano realnej ceny.


### `src/Tick/Modules/PlayersModule.php`

**Blob:** `37d3e1b47b4bb5648e4b97edcb068b29fc4a5e6e` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 86 linii.

- **Ustalenie:** PlayersModule (STOP) ładuje mnożniki i uruchamia PlayersSection z limitem, raportuje przetworzonych/pominiętych, brak magazynu, produkcję, przychody, OPEX i timingi sekcji (26–86).
- **Zależności do sprawdzenia:** `src/Tick/PlayersSection.php`, `src/Tick/TickContext.php`.
- **Granica dowodu:** Zależność od sekcji i błędów pojedynczych graczy; nie uruchomiono produkcyjnego ticku.


### `src/Tick/Modules/TrainingModule.php`

**Blob:** `5357aa01a0809aa3bb3226e4bdf822f9a3c268bf` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 39 linii.

- **Ustalenie:** TrainingModule (CONTINUE) uruchamia TrainingSection i raportuje examined (13–39).
- **Zależności do sprawdzenia:** `src/Tick/TrainingSection.php`.
- **Granica dowodu:** Nie testuje wysyłek, wyjątków ani limitu przebiegu; PHP nieuruchomione.


### `tests/Integration/HubWorkModeTest.php`

**Blob:** `66d69237cebdd1200b91984c9ee716c4465f884c` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 84 linii.

- **Ustalenie:** SQLite sprawdza zmianę trybu pracy huba przez właściciela i najemcę, odmowę obcemu graczowi oraz jawny admin override (45–76).
- **Zależności do sprawdzenia:** `src/HubService.php`, `tests/Integration/SqliteIntegrationTestCase.php`.
- **Granica dowodu:** Bypass konstruktora Reflection; nie testuje uwierzytelnienia admina ani HTTP.


### `tests/Integration/LogisticsIncidentRetentionServiceTest.php`

**Blob:** `7fb09877e92869e1e5156625993fefcc3898d4e3` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 48 linii.

- **Ustalenie:** SQLite weryfikuje usunięcie 505 starych incydentów w backlogu i selekcję typów eventów huba/pipeline przy 48h cutoff, pozostawiając nowsze i inne typy (9–47).
- **Zależności do sprawdzenia:** `src/LogisticsIncidentRetentionService.php`.
- **Granica dowodu:** Brak sprawdzenia indeksów/przepustowości MySQL; test nieuruchomiony.


### `tests/Unit/AdminHRControllerContractTest.php`

**Blob:** `e2ca460641f72f4d4247e345cda9005fde0f4738` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 33 linii.

- **Ustalenie:** Testy czytają admin/hr.php i pagination.php jako string, wymagając sekwencji beginTransaction/forceActiveForTesting/commit i niezależnych parametrów candidate_page/round_page (8–33).
- **Zależności do sprawdzenia:** `admin/hr.php`, `templates/views/admin/hr/_pagination.php`.
- **Granica dowodu:** Regex i wyszukiwanie napisów nie dowodzą wykonania transakcji, odrzucenia CSRF lub paginacji HTTP.


### `tests/Unit/HeadhunterOfferValidationTest.php`

**Blob:** `0f8c605ff37120059f5717306e5af8b82996fc61` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 23 linii.

- **Ustalenie:** Dwa testy oczekują InvalidArgumentException dla ujemnej pensji i ujemnego bonusu przed dostępem do bazy (8–23).
- **Zależności do sprawdzenia:** `src/HeadhunterService.php`.
- **Granica dowodu:** Nie testuje zera, NaN/INF, maksymalnych wartości ani dodatniej oferty; PHP nieuruchomione.


### `tests/Unit/HubTickServiceTest.php`

**Blob:** `d332e4f3d5a7fef5897d9c564924c5d4d71a1a80` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 262 linii.

- **Ustalenie:** Sześć testów z mock HubService sprawdza zużycie używanego huba, przepływ awaryjny disabled/planned, bonus throughput pracownika, clamp ujemnego mnożnika i straty z bufora (17–262).
- **Zależności do sprawdzenia:** `src/HubTickService.php`, `src/HubService.php`.
- **Granica dowodu:** Nie wykonano; testuje kalkulację in-memory, nie zapis stanu ticka do bazy.


### `tests/Unit/LogRetentionServiceTest.php`

**Blob:** `2a5a5a16d8a6c5b71be6101da9085ba32dc438d8` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 102 linii.

- **Ustalenie:** SQLite i plik tymczasowy testują retencję admin_logs i game log, 0 dni jako disable, interwał godzinny, nonblocking lock oraz cleanup po zakończeniu odpowiedzi/sesji (10–101).
- **Zależności do sprawdzenia:** `src/AdminLogs/LogRetentionService.php`, `src/AdminLogs/GameLogReader.php`.
- **Granica dowodu:** Test korzysta z temp i callbacku, nie dowodzi zachowania PHP-FPM ani retencji produkcyjnego logu; nieuruchomiony.


### `tests/Unit/MarketNotificationRouteTest.php`

**Blob:** `5113e76b9a75485a4cc15777b93a7762a4f0ac2e` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 23 linii.

- **Ustalenie:** Asercje sprawdzają action_url=/market w trzech szablonach powiadomień i obecność reguł /market.php→/market oraz /market→public/market.php w .htaccess (10–22).
- **Zależności do sprawdzenia:** `src/DirectorNotificationService.php`, `.htaccess`.
- **Granica dowodu:** Brak rzeczywistego Apache/HTTP, test tekstu reguł nie dowodzi routingu.


### `tests/Unit/TransportConfigServiceTest.php`

**Blob:** `a29cd572cbaf404ca062060ab2e48d0763b5f967` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 67 linii.

- **Ustalenie:** SQLite pokrywa domyślne parametry trzech środków transportu, brak tabeli, merge override i zero dla nieznanego typu (18–67).
- **Zależności do sprawdzenia:** `src/TransportConfigService.php`.
- **Granica dowodu:** Nie testuje błędnej/ujemnej wartości konfiguracji ani wpływu na faktyczny transport; nieuruchomione.


### `tests/Unit/WellEventRetentionServiceTest.php`

**Blob:** `1d119664d9b0cd969845233a47611c549725e412` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 41 linii.

- **Ustalenie:** SQLite sprawdza granicę cutoff well_events i usunięcie całego zaległego zbioru 2001 zdarzeń przy zachowaniu wpisu na granicy (8–40).
- **Zależności do sprawdzenia:** `src/WellEventRetentionService.php`.
- **Granica dowodu:** PHPUnit nieuruchomiony; brak konkurencyjnego INSERT i MySQL.


### `tests/Unit/WellLoopPipelineStateTest.php`

**Blob:** `38bd547f314b2b84edf5d991a960e24140ca5f79` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 47 linii.

- **Ustalenie:** Dwa testy na sztucznym WellLoopSection sprawdzają invalidację cache uszkodzonego rurociągu oraz proporcjonalne zmniejszenie input/delivered/finBbl/finRevenue przy stracie huba (8–47).
- **Zależności do sprawdzenia:** `src/Tick/WellLoopSection.php`.
- **Granica dowodu:** Bez PDO, HTTP i pełnego ticku; PHPUnit nieuruchomiony.


### `.htaccess`

**Blob:** `72ccea37675814332bbfb46dcdcd6d16500fe890` · **Status:** `pełny odczyt statyczny; zgodny z HEAD po normalizacji CRLF` · **Odczyt:** 166 linii.

- **Ustalenie:** Apache rewrite mapuje czyste URL, blokuje katalogi implementacji oraz w FilesMatch część wrażliwych rozszerzeń; /cron/tick.php ma jawny wyjątek i musi mieć własną ochronę (8–166).
- **Ustalenie:** Reguła HTTPS bierze X-Forwarded-Proto z headera; nie oceniono konfiguracji trusted proxy. Ścieżki /v2 i /api/v1 omijają główne przepisy rewrite, opierając się na kontrolach w endpointach (12–16,41–47).
- **Zależności do sprawdzenia:** `src/Security.php`, `src/.htaccess`, `cron/tick.php`, `tests/Unit/WebDirectoryAccessTest.php`.
- **Granica dowodu:** Nie uruchomiono Apache, nie sprawdzono aktywnych mod_rewrite/mod_headers.


### `public/forgot_password.php`

**Blob:** `ccc8a989bb42105657923a80a650d2de9d98dde4` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 102 linii.

- **Ustalenie:** POST forgot-password sprawdza CSRF i progressive RateLimiter, błędny email rejestruje niepowodzenie, a dla poprawnego ignoruje wynik sendPasswordReset i zawsze pokazuje neutralny komunikat (15–37).
- **Zależności do sprawdzenia:** `src/Auth.php`, `src/RateLimiter.php`, `src/EmailTemplate.php`.
- **Granica dowodu:** Neutralność ogranicza enumerację w odpowiedzi; nie potwierdza rzeczywistego wysłania maila, HTTP nieuruchomione.


### `public/login.php`

**Blob:** `3eb53cb144112ca6f3c046b5e43a4a78748a2c5b` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 137 linii.

- **Ustalenie:** POST login używa RateLimiter, CSRF, Validator i Auth::login; remember-me ustawiane dopiero po sukcesie, potem redirect. Widok pokazuje błąd i pole email escape (20–123).
- **Ustalenie:** Warstwa Auth może ustawić sesję, a następnie zwrócić false po awarii last_login_at; UI pokaże błąd mimo zalogowania. Test prezentacyjny nie sprawdza tego przepływu.
- **Zależności do sprawdzenia:** `src/Auth.php`, `src/RateLimiter.php`, `src/CSRF.php`, `tests/Unit/AuthLoginPresentationTest.php`.
- **Granica dowodu:** Nie wykonano żądania ani symulacji błędu DB.


### `public/reset_password.php`

**Blob:** `5a0b2485d74b088905cb5f0eeb73c679f2d7df2d` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 69 linii.

- **Ustalenie:** verifyResetToken zwraca null przy nieważnym tokenie, ale tokenValid porównuje wynik do false: null !== false jest prawdziwe, więc nieważny token pokazuje formularz zamiast błędu (12–25; widok reset_password/main.php:7–29).
- **Ustalenie:** Auth::resetPassword zwraca niepustą tablicę także przy success=false; if ($result) ustawia success=true, więc POST z błędnym tokenem może pokazać fałszywe potwierdzenie zmiany hasła (34–39).
- **Zależności do sprawdzenia:** `src/Auth.php`, `templates/views/public/reset_password/main.php`, `src/CSRF.php`.
- **Granica dowodu:** Wniosek z kodu PHP i kontraktu zwrotu, bez uruchomienia HTTP/PHP.


### `src/Auth.php`

**Blob:** `bf0a4ca60bab67e626136536890bf7f45cd198db` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 889 linii.

- **Ustalenie:** Auth obsługuje login/rejestrację, mail verification, reset hasła i remember-me. Tokeny resetu oraz remember-me są generowane random_bytes i przechowywane jako SHA-256 hash (18–889).
- **Ustalenie:** login ustawia sesję przed UPDATE last_login_at; błąd zapisu zwróci false przy istniejącej sesji. setSession ignoruje false z session_regenerate_id i tłumi wyjątki, więc wynik true nie dowodzi bezpiecznej rotacji ID (18–71,442–460).
- **Ustalenie:** resetPassword weryfikuje used=0, lecz UPDATE password oraz used=1 nie są transakcją ani atomowym claimem; dwa równoległe żądania mogą zużyć token równocześnie. Po zmianie hasła kod nie unieważnia sesji ani player_remember_tokens (693–718,860–887).
- **Zależności do sprawdzenia:** `src/Database.php`, `src/EmailTemplate.php`, `src/Security.php`, `public/reset_password.php`.
- **Granica dowodu:** Statyczne scenariusze bezpieczeństwa; nie uruchomiono PHP/MySQL/HTTP ani testu równoległego.


### `src/CSRF.php`

**Blob:** `828b52f8149401d5c96ca88458c65a17ed52de93` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 45 linii.

- **Ustalenie:** CSRF generuje 32 losowe bajty kodowane hex i porównuje hash_equals z tokenem sesji; field wystawia hidden input (5–45).
- **Ustalenie:** Jeśli random_bytes rzuci wyjątek, catch tworzy token z uniqid+mt_rand zamiast fail-closed; test obejmuje tylko ścieżkę losową. Token utrzymuje się w sesji, nie jest per formularz.
- **Zależności do sprawdzenia:** `src/init.php`, `tests/Unit/CSRFTest.php`.
- **Granica dowodu:** Nie wywoływano awarii RNG ani sesji w PHP.


### `src/EmailTemplate.php`

**Blob:** `c11d941dfad35a710b4e7b44575c062ac0efb3a4` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 122 linii.

- **Ustalenie:** HTML email koduje title/URL/label/subtitle, ale greeting, bodyHtml i footer interpoluje bez dodatkowej sanitizacji — API zakłada poprawny escaping u wywołujących (32–121).
- **Zależności do sprawdzenia:** `src/Auth.php`, `src/Mailer.php`, `tests/Unit/PasswordResetEmailTest.php`.
- **Granica dowodu:** Nie wysłano maila ani nie zweryfikowano wszystkich callerów; sam test koduje tylko nazwę.


### `src/Security.php`

**Blob:** `5f5a323aaeb6694a24d8249d88e3fe1abb20d0a1` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 58 linii.

- **Ustalenie:** isHttpsRequest ufa HTTPS, portowi 443 lub pierwszej wartości X-Forwarded-Proto; setHeaders ustawia nagłówki ochronne i HSTS gdy wykrywa HTTPS (10–45).
- **Ustalenie:** Nie ma tu listy zaufanych proxy; nagłówek Forwarded jest traktowany jako autorytatywny także przy bezpośrednim HTTP. Przy migracji zależność od konfiguracji reverse proxy/cookie Secure wymaga jawnej weryfikacji (22–23,37–39).
- **Zależności do sprawdzenia:** `src/init.php`, `src/Auth.php`, `tests/Unit/SecurityHttpsRequestTest.php`.
- **Granica dowodu:** Nie oceniano realnej sieci/proxy; scenariusz zależy od konfiguracji hostingu.


### `src/SessionCookie.php`

**Blob:** `12862e511d80f3024ad68e41730c34c07081eefd` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 30 linii.

- **Ustalenie:** SessionCookie::refresh odnawia cookie PHP dla aktywnego gracza lub admina z terminem 7200 s, zachowuje cookie params i wyłącza odnowienie po headers_sent (7–30).
- **Zależności do sprawdzenia:** `src/init.php`, `src/Auth.php`, `src/AdminAuth.php`.
- **Granica dowodu:** Brak uruchomienia sesji i HTTP; nie weryfikuje rzeczywistego lifetime storage.


### `src/Validator.php`

**Blob:** `141f4af3144be4275ea401c82fe2dda2cf89fd0b` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 54 linii.

- **Ustalenie:** Validator koduje HTML w sanitize, a email/int/float waliduje filtrami PHP i granicami min/max (5–54).
- **Zależności do sprawdzenia:** `tests/Unit/ValidatorTest.php`, `public/login.php`.
- **Granica dowodu:** Nie zastępuje walidacji domenowej ani parametryzacji SQL; PHP nieuruchomione.


### `tests/Unit/AuthLoginPresentationTest.php`

**Blob:** `b37138ea24599247c5d9b7c179c8c1019817a2e8` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 30 linii.

- **Ustalenie:** Sprawdza branding OilEmpire w tłumaczeniach login PL/EN i pliku CSS, plus istnienie tła (8–30).
- **Zależności do sprawdzenia:** `lang/pl/auth.php`, `lang/en/auth.php`, `assets/css/auth.css`.
- **Granica dowodu:** Nie renderuje ani nie sprawdza logowania.


### `tests/Unit/CSRFTest.php`

**Blob:** `58b8c252dc3b46e5149a2f91422eca61794864f2` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 39 linii.

- **Ustalenie:** Sprawdza długość 64 i zapis tokenu CSRF w sesji, matching/wrong token oraz hidden input (9–39).
- **Zależności do sprawdzenia:** `src/CSRF.php`, `tests/Unit/BaseTestCase.php`.
- **Granica dowodu:** Nie testuje wyjątku random_bytes i słabszego fallbacku, braku aktywnej sesji ani replay; PHP nieuruchomione.


### `tests/Unit/DeploymentBaselineTest.php`

**Blob:** `bd4a46a2884328d528831d1db2826b3ccbb39e72` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 17 linii.

- **Ustalenie:** Kontrakt deploymentu szuka w YAML znaczników ostatniego udanego wdrożenia, fallback do full mirror i diff filtrowany ACMRT (8–17).
- **Zależności do sprawdzenia:** `.github/workflows/deploy-ftp.yml`.
- **Granica dowodu:** Nie uruchomiono deploy, nie porównano zachowania skryptu w GitHub Actions.


### `tests/Unit/FrontendInlineConfigTest.php`

**Blob:** `523b1b1c49f4b5da7664ae6a26528f90ff1aea51` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 31 linii.

- **Ustalenie:** Wymaga braku literalnego <script> w wybranych szablonach, danych JSON w data attributes i konkretnego parsera w JS (8–31).
- **Zależności do sprawdzenia:** `templates/header.php`, `templates/footer.php`, `templates/components/well_grid.php`, `assets/js/well_grid.js`.
- **Granica dowodu:** Wyszukiwanie fragmentów nie dowodzi CSP/XSS ani braku inline script w pozostałych miejscach.


### `tests/Unit/HelpHtmlSanitizerTest.php`

**Blob:** `0cd3f1203725e7c37ebd51a14ddefee48ff00a65` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 65 linii.

- **Ustalenie:** Testy AdminNewsHtml sprawdzają tabele PL/EN, idempotencję sanitizacji, usunięcie event handlerów, javascript: i niebezpiecznych zagnieżdżeń; asercje tekstowe wymagają sanitizacji zapisu/odczytu pomocy (9–65).
- **Zależności do sprawdzenia:** `src/AdminNewsHtml.php`, `admin/help_editor.php`, `public/help.php`.
- **Granica dowodu:** DOMDocument i PHP nieuruchomione; syntaktyczna kontrola wywołań nie dowodzi każdej ścieżki HTML.


### `tests/Unit/PasswordResetEmailTest.php`

**Blob:** `6ec409c7cf0953160291817ee906f46948c36ee7` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 39 linii.

- **Ustalenie:** Test buduje email resetu z wcześniej zakodowaną nazwą i sprawdza escape oraz temat tłumaczeń pl/en/de (10–39).
- **Zależności do sprawdzenia:** `src/EmailTemplate.php`, `src/Auth.php`, `lang/pl/auth.php`, `lang/en/auth.php`, `lang/de/auth.php`.
- **Granica dowodu:** Używa przykładowego tokenu; nie wysyła email, nie sprawdza bezpieczeństwa wszystkich fragmentów HTML.


### `tests/Unit/SecurityHttpsRequestTest.php`

**Blob:** `72be0d0e67f505111fde0d7f4c2df7e2be99c06e` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 28 linii.

- **Ustalenie:** Cztery przypadki testują HTTPS native, port 443 i pierwszy X-Forwarded-Proto=https, w tym listę 'https, http' (9–28).
- **Zależności do sprawdzenia:** `src/Security.php`, `src/init.php`.
- **Granica dowodu:** Nie sprawdza czy proxy jest zaufane/sanitizuje header, ani rzeczywistego TLS; PHP nieuruchomione.


### `tests/Unit/SessionConfigurationContractTest.php`

**Blob:** `2a7b8bac755121475cc56396298a4276fb9d1d1f` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 20 linii.

- **Ustalenie:** Asercje tekstowe wymagają w src/init.php TTL 7200, aligned gc, strict_mode, cookie Secure zależne od Security i SameSite Lax (8–20).
- **Zależności do sprawdzenia:** `src/init.php`, `src/Security.php`, `src/SessionCookie.php`.
- **Granica dowodu:** Nie uruchamia session_start ani nie sprawdza nagłówków przez HTTP.


### `tests/Unit/ValidatorTest.php`

**Blob:** `b9f3bf75922ae3acd93784e62d179deeaf12216f` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 39 linii.

- **Ustalenie:** Testuje trim+HTML escape, poprawny/błędny email oraz progi int/float w kilku przykładach (9–39).
- **Zależności do sprawdzenia:** `src/Validator.php`.
- **Granica dowodu:** Nie pokrywa encodings/typów tablicowych/NaN; PHPUnit nieuruchomiony.


### `tests/Unit/WebDirectoryAccessTest.php`

**Blob:** `0c6d586fd00eef0702e92be1676b1d10574257e3` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 78 linii.

- **Ustalenie:** Czyta .htaccess i workflow: wymaga blokady katalogów implementacji, przekierowania nawigacji GET z api/internal, brak dawnych diagnostyk, adaptery AJAX oraz pliki healthcheck/tool (8–77).
- **Zależności do sprawdzenia:** `.htaccess`, `src/.htaccess`, `.github/workflows/deploy-ftp.yml`.
- **Granica dowodu:** Asercje tekstowe i glob nie dowodzą konfiguracji Apache/FTP, aktywnych modułów rewrite ani fizycznego stanu serwera.


### `src/HR/AdminHRConfigService.php`

**Blob:** `915d6d50710ca1a67d68464295662e4efd107707` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 152 linii.

- **Ustalenie:** AdminHRConfigService udostępnia pięć allowlist grup, odrzuca obce klucze, deleguje walidację do EmployeeSystemConfigService; operacje dialogowe owijane w transakcję tylko gdy brak zewnętrznej (10–152).
- **Zależności do sprawdzenia:** `src/Employee/EmployeeSystemConfigService.php`, `src/HR/EmployeeDialogueTemplateService.php`.
- **Granica dowodu:** Nie odczytano pełnej implementacji save config; nie przyjmować atomowości wielokluczowej bez zależności.


### `src/HR/AdminHRQueryService.php`

**Blob:** `243da16aafaa77ff371c64c4bc0ead5001441ca1` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 252 linii.

- **Ustalenie:** AdminHRQueryService parametryzuje filtry, paginuje pracowników, strajki, dialogi i eventy, a rundy negocjacji sortuje po attempt_no i round_no (11–252).
- **Ustalenie:** count wykonuje SELECT COUNT(*) z całej podkwerendy zawierającej ORDER BY, a employee_name bazuje na per-row correlated subqueries; koszt na dużej bazie wymaga EXPLAIN, nie pomiaru statycznego.
- **Zależności do sprawdzenia:** `src/EmployeeSystemBootstrap.php`, `tests/Integration/AdminHRQueryServiceTest.php`.
- **Granica dowodu:** Bez MySQL/EXPLAIN; brak pełnego audytu callerów filtrów.


### `src/HR/EmployeeActionReceiptService.php`

**Blob:** `181d6c58dc83ec0f2c22397e1b9e58a69da753d7` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 92 linii.

- **Ustalenie:** Receipt waliduje token/action, hashuje kanoniczny payload, INSERT IGNORE claimuje lub odczytuje istniejący z FOR UPDATE na MySQL; replay tylko po completed_at i identycznym hash, complete wymaga rowCount=1 (14–92).
- **Zależności do sprawdzenia:** `src/HR/EmployeeBonusService.php`, `tests/Integration/EmployeeActionReceiptServiceTest.php`.
- **Granica dowodu:** Bez równoległych workerów MySQL; bezpieczeństwo atomowe zależy od unikalnego indeksu i transakcji callera.


### `src/HR/EmployeeBonusService.php`

**Blob:** `9b80a012901e4220d388de0ec64945aac43cee86` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 124 linii.

- **Ustalenie:** Bonus techniczny ma top-level transaction, retry deadlocków, claim idempotency, walidację właściciela, debitCombined i zmianę morale, zapisuje także negatywny wynik płatności w receipt (18–124).
- **Ustalenie:** Konstruktor przed transakcją wywołuje EmployeeSystemBootstrap::ensure($db), potencjalny schema side effect na ścieżce żądania; przy zewnętrznej transakcji odpowiedzialność za rollback spoczywa na callerze.
- **Zależności do sprawdzenia:** `src/EmployeeSystemBootstrap.php`, `src/HR/EmployeeActionReceiptService.php`, `src/FinancialTransactionService.php`, `src/HR/EmployeeDeadlockRetry.php`.
- **Granica dowodu:** Nie uruchomiono MySQL ani bonusu; trzeba zbadać ensure i unikalne indeksy.


### `src/HR/EmployeeCompensationService.php`

**Blob:** `e0bd7225a739c1309d063b7a6f1bdb6d55785ce5` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 144 linii.

- **Ustalenie:** setSalary waliduje kwotę, aktualizuje połączone board/technical source, satysfakcję i aktywny kontrakt; applyRaise blokuje źródło i wywołuje setSalary (16–143).
- **Ustalenie:** setSalary nie otwiera własnej transakcji: częściowa aktualizacja między źródłami/kontraktem może zostać utrwalona przez caller bez transakcji. Test atomicity rozpoczyna transakcję jawnie i bada rollback tylko takiej ścieżki.
- **Zależności do sprawdzenia:** `src/Employee/EmployeeRef.php`, `tests/Integration/EmployeeCompensationAtomicityTest.php`.
- **Granica dowodu:** Brak symulacji awarii po pierwszym UPDATE; ryzyko warunkowe dla callerów bez transakcji.


### `src/HR/EmployeeDashboardQueryService.php`

**Blob:** `023c8dfac333d31b35be2df830e3b067316d1339` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 288 linii.

- **Ustalenie:** forPlayer agreguje pracowników, stan, zadania, szkolenia i paginowane eventy z filtrami player_id; markEventsRead/Notified aktualizuje maksymalnie 50 dodatnich unikalnych ID tego gracza (33–252).
- **Ustalenie:** Błędy treningu są logowane i zamieniane w pustą listę, co nie odróżnia braku danych od awarii. Repeat update zwraca DB rowCount, nie liczbę żądanych ID.
- **Zależności do sprawdzenia:** `src/Employee/EmployeeRepository.php`, `src/Training/TrainingService.php`, `tests/Integration/HREventDeliveryStateTest.php`.
- **Granica dowodu:** Nie uruchomiono HTTP ani MySQL; brak pomiaru skali listy pracowników.


### `src/HR/EmployeeDeadlockRetry.php`

**Blob:** `fc6489b7ae197617899c28c5e02ab80cf4641f76` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 35 linii.

- **Ustalenie:** Helper ponawia do 3 (max5) razy tylko PDOException SQLSTATE 40001/driver 1205,1213, gdy PDO nie jest już w transakcji; liniowy usleep (14–35).
- **Zależności do sprawdzenia:** `src/HR/EmployeeBonusService.php`.
- **Granica dowodu:** Nie testowano deadlocku MySQL; nie ponawia RuntimeException ani operacji w transakcji callerów.


### `src/HR/EmployeeNegotiationEffectivenessService.php`

**Blob:** `0e5ef9568df05c2ed428cc07a235924aa1ee5fac` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 43 linii.

- **Ustalenie:** Wylicza skuteczność aktywnego HR board z negocjacji, organizacji i opcjonalnie morale, filtruje nieaktywne/strajkujące role i nakłada strike multiplier, clampując 0–100 (15–43).
- **Zależności do sprawdzenia:** `src/HR/StrikeEffectService.php`, `src/Employee/EmployeeSystemConfigService.php`.
- **Granica dowodu:** Bez testu danych rzeczywistych; fallback 0 lub 50 zależy od includeMorale.


### `src/HR/EmployeeRelationLifecycleService.php`

**Blob:** `8ae75f06960c336ebd6245ea246cf26066ec7dfb` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 89 linii.

- **Ustalenie:** deactivate może rozpocząć transakcję, wypisuje pracownika z otwartych strajków, zamyka strajk bez aktywnych członków i negocjacje, oznacza employee_state inactive; unieważnia cache strike (13–89).
- **Zależności do sprawdzenia:** `src/HR/StrikeEffectService.php`, `src/Employee/EmployeeRef.php`.
- **Granica dowodu:** Wewnętrzny UPDATE employee_state nie sprawdza rowCount; bez testu współbieżności i MySQL.


### `src/HR/StrikeEffectService.php`

**Blob:** `81081b87131f45173190c5d38bc9de11b065afdd` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 146 linii.

- **Ustalenie:** Batchowe efekty strajków dla aktywnych/negocjujących grup po player_id; cache instancji z generacją request do unieważnienia po zmianie, mapuje finance na role_bonus_active=false (29–146).
- **Ustalenie:** forPlayers ustawia loaded przed fetchAll; gdy odczyt rzuci po execute, instancja może zapamiętać pusty wynik i nie ponowić SELECT bez invalidacji. Scenariusz statyczny.
- **Zależności do sprawdzenia:** `src/Employee/EmployeeSystemConfigService.php`, `src/HR/EmployeeRelationLifecycleService.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL ani awarii fetchAll.


### `tests/Infrastructure/HRLanguageContractTest.php`

**Blob:** `f380d361e490c5c97285d5cab6a687d55f9742b5` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 154 linii.

- **Ustalenie:** Test porównuje klucze i placeholdery pl/en dla HR gracza i admina oraz wymaga listy wybranych dynamicznych etykiet (11–154).
- **Zależności do sprawdzenia:** `lang/pl/hr.php`, `lang/en/hr.php`, `lang/pl/admin/hr.php`, `lang/en/admin/hr.php`.
- **Granica dowodu:** Nie obejmuje niemieckiego tłumaczenia ani znaczenia tekstów/renderingu; PHP nieuruchomione.


### `tests/Integration/AdminHRConfigServiceTest.php`

**Blob:** `af951088d087b090c3ca003f14d5c2d7b3b26b3b` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 86 linii.

- **Ustalenie:** SQLite sprawdza allowlist grup i kluczy ustawień, odrzucenie NAN/nieprawidłowych typów oraz CRUD szablonów dialogów (21–85).
- **Zależności do sprawdzenia:** `src/HR/AdminHRConfigService.php`, `src/Employee/EmployeeSystemConfigService.php`, `tests/Integration/SqliteIntegrationTestCase.php`.
- **Granica dowodu:** Nie testuje wielokluczowego rollback ani MySQL/HTTP.


### `tests/Integration/AdminHRQueryServiceTest.php`

**Blob:** `e3346acd8113f8b67bd7b8baf67a8b1128fef35e` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 83 linii.

- **Ustalenie:** SQLite sprawdza izolację player_id w pracownikach, paginację 65 szablonów w SQL oraz kolejność prób negocjacji (10–82).
- **Zależności do sprawdzenia:** `src/HR/AdminHRQueryService.php`, `src/EmployeeSystemBootstrap.php`.
- **Granica dowodu:** Brak dużej tabeli/planów MySQL i uprawnień kontrolera; nieuruchomione.


### `tests/Unit/AdminBalanceConfigTest.php`

**Blob:** `c588dd9f29882f18b8dfba4f78dd7bceac5c7b33` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 38 linii.

- **Ustalenie:** Sprawdza, że dawne globalne modyfikatory bilansu nie są w formularzu finansów i etykiety PL mają oczekiwane wartości (9–38).
- **Zależności do sprawdzenia:** `admin/partials/finance_admin_actions.php`, `lang/pl/admin/balance.php`.
- **Granica dowodu:** Nie dowodzi braku zapisu globalnych wartości przez inne endpointy ani rzeczywistego renderingu.


### `tests/Unit/AdminDashboardRegressionTest.php`

**Blob:** `c94a9adcc679b749ca38e97dd1de8d30cf7f26cd` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 104 linii.

- **Ustalenie:** Testuje walidator progów alertów, sanitizer pomocy, lokalizację powiadomień, parsing kwoty korekty bankowej i brak inline handlerów w wybranych widokach (18–103).
- **Zależności do sprawdzenia:** `src/AdminAlertThresholds.php`, `src/AdminNewsHtml.php`, `src/DirectorNotificationService.php`, `src/AdminBankAdjustment.php`.
- **Granica dowodu:** PHPUnit nieuruchomiony; to wybrane jednostki/kontrakty statyczne, nie E2E admina.


### `tests/Unit/AdminHRViewContractTest.php`

**Blob:** `4386816894b155d782dfe6658817394db8094d09` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 20 linii.

- **Ustalenie:** Test tekstowy wymaga attempt_no, hpage, pageQueryKey i braku inline style w wybranych szablonach HR admina (8–20).
- **Zależności do sprawdzenia:** `templates/views/admin/hr/strikes.php`, `templates/views/admin/hr/logs.php`, `templates/views/admin/hr/_pagination.php`.
- **Granica dowodu:** Nie renderuje HTML ani nie testuje rzeczywistej paginacji.


### `tests/Unit/EmployeeRefTest.php`

**Blob:** `3f6f9fcc913e9e792f1e05956a6cc59f3f2266de` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 32 linii.

- **Ustalenie:** Test stabilnego klucza board_member:17 i odrzucenia błędnego source type, zerowego source ID i ujemnego player ID (9–32).
- **Zależności do sprawdzenia:** `src/Employee/EmployeeRef.php`.
- **Granica dowodu:** Nie sprawdza rzeczywistego istnienia pracownika; PHPUnit nieuruchomiony.


### `tests/Unit/HRHttpContractTest.php`

**Blob:** `7e82ff9cb2c3c71584a87ae0c6c215ba06a82866` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 49 linii.

- **Ustalenie:** Kontrakty tekstowe sprawdzają JSON boundary/statusy API HR, akcje mark_events, brak określonych wywołań mutujących na GET i brak DDL w BoardAccess (8–49).
- **Zależności do sprawdzenia:** `src/HRApi.php`, `public/hr.php`, `src/HR/EmployeeDashboardQueryService.php`, `src/BoardAccess.php`.
- **Granica dowodu:** Wyszukiwanie literalnych ciągów nie dowodzi braku pośrednich side effects/DDL; nieuruchomione.


### `tests/Unit/HRPlayerViewContractTest.php`

**Blob:** `1f32e3a72cfd10a9ed7994de976eb6efa299ef53` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 41 linii.

- **Ustalenie:** Sprawdza znaczniki siedmiu zakładek, ARIA, deep_link, brak inline handlerów i tokeny kolorów w stylu HR (8–41).
- **Zależności do sprawdzenia:** `templates/views/hr/main.php`, `assets/css/hr_morale.css`.
- **Granica dowodu:** Nie sprawdza działania przeglądarki ani kontrastu.


### `tests/Unit/HRUiLanguageContractTest.php`

**Blob:** `8083b7865c6cf70593c311041e06b0c7f7c403b3` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 56 linii.

- **Ustalenie:** Kontrola tekstowa JS rekrutacji, parytetu kluczy PL/EN oraz znaczników i akcji klienta HR (8–56).
- **Zależności do sprawdzenia:** `assets/js/recruitment.js`, `assets/js/hr.js`, `lang/pl/hr.php`, `lang/en/hr.php`.
- **Granica dowodu:** Nie wykonano JS ani HTTP; parytet nie dowodzi poprawności tłumaczeń.


### `src/AdminAlertThresholds.php`

**Blob:** `6fae03480d9016e504dd17856f405949a4718193` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 43 linii.

- **Ustalenie:** AdminAlertThresholds waliduje wszystkie dziewięć progów jako skończone liczby i narzuca relacje warn<critical, price_low<high i roi_min<max (6–42).
- **Zależności do sprawdzenia:** `tests/Unit/AdminDashboardRegressionTest.php`.
- **Granica dowodu:** Wymaga pełnego zestawu wejścia; nie uruchomiono PHP.


### `src/AdminLog.php`

**Blob:** `b74de1c6973437315bcc91463c11f98c513c439f` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 45 linii.

- **Ustalenie:** AdminLog::log tworzy wpis audytu przez Database singleton, ale łapie każdy Throwable, loguje błąd i zawsze zwraca void (8–44).
- **Ustalenie:** Caller nie może rozpoznać braku wpisu audytowego po wyniku metody; nie jest to gwarancja atomowego audytu operacji biznesowej.
- **Zależności do sprawdzenia:** `src/Database.php`, `src/GameLog.php`.
- **Granica dowodu:** Nie badano każdego callera; wniosek tylko z kontraktu metody.


### `src/AdminLogs/GameLogReader.php`

**Blob:** `9ee9f88f801af11b23f26f8bffc4cd92b2ff21ed` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 158 linii.

- **Ustalenie:** GameLogReader paginuje od końca chunkami 64 KiB, omija puste linie; pruneOlderThan filtruje datowane wpisy do pliku temp i atomowo zastępuje log, pomijając zajęty flock (10–158).
- **Ustalenie:** prune zwalnia flock oryginału przed rename(temp,path). Writer może dopisać do starego pliku w tej luce, a podmiana utracić wpis; fwrite(target,line) sprawdza false, ale nie krótszy zapis.
- **Zależności do sprawdzenia:** `src/GameLog.php`, `tests/Unit/GameLogReaderTest.php`.
- **Granica dowodu:** Warunkowy wyścig i niepełny zapis wynikają z kodu; brak testu równoległego/PHP.


### `src/AdminLogs/LogRetentionService.php`

**Blob:** `115e9e34bdb0e104d0a7fccf1d9d80b0c6d88e7a` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 98 linii.

- **Ustalenie:** LogRetentionService odsyła odpowiedź przed cleanup, używa nieblokującego flock markera .retention.log i zapisuje próbę przed SQL DELETE admin_logs oraz pruningiem game log (20–97).
- **Zależności do sprawdzenia:** `src/AdminLogs/GameLogReader.php`, `tests/Unit/LogRetentionServiceTest.php`.
- **Granica dowodu:** Awarie po markerze mogą opóźnić retry do godziny; nie uruchamiano retencji ani SQL.


### `src/AdminLogs/TickHistoryQuery.php`

**Blob:** `a0318689a2d39324c32d7dce5e3c523e6efe8d5c` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 69 linii.

- **Ustalenie:** TickHistoryQuery wyłącznie czyta tick_stats, waliduje source, ogranicza page size do 100, pobiera podsumowania 24h z osobnym wariantem SQLite (13–69).
- **Zależności do sprawdzenia:** `src/Tick/TickStatsRepository.php`, `tests/Integration/TickHistoryQueryTest.php`.
- **Granica dowodu:** Nie potwierdzono indeksów i EXPLAIN produkcyjnej bazy.


### `src/AdminNewsApi.php`

**Blob:** `2041a2ac35435de480819d8ddaf42505c697bb2a` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 140 linii.

- **Ustalenie:** Publiczny endpoint AdminNewsApi ładuje init, wykrywa title_html przez SHOW COLUMNS, pobiera 20 aktywnych newsów i sanituje tytuł/treść przed JSON; na błędzie zwraca pustą listę (4–140).
- **Zależności do sprawdzenia:** `src/AdminNewsHtml.php`, `assets/js/dashboard_news.js`, `tests/Unit/DashboardNewsWidgetTest.php`.
- **Granica dowodu:** Nie uruchomiono HTTP ani DOMDocument, test sprawdza jedynie tekst.


### `src/AdminNewsHtml.php`

**Blob:** `3a1dacddef30c4b909acc5885fa34095e4400afe` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 255 linii.

- **Ustalenie:** AdminNewsHtml sanituje title/content przez DOMDocument allowlist; zrzuca script/style/iframe i atrybuty on*, ogranicza href i style; fallback bez DOM stripping attrs regexem (13–255).
- **Zależności do sprawdzenia:** `src/AdminNewsApi.php`, `tests/Unit/HelpHtmlSanitizerTest.php`.
- **Granica dowodu:** Brak fuzzingu i browser DOM dla skrajnych HTML/URL; część zasad zależy od dostępności DOMDocument.


### `src/AdminPlayerDeletionService.php`

**Blob:** `e8b18352394fbbf7f56258e2ed46981948a3cddf` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 301 linii.

- **Ustalenie:** purgeMany zbiera powiązane IDs i usuwa w transakcji dane gracza, wyłącza FK checks na MySQL, usuwa pliki załączników, a tabele dynamiczne znajduje przez information_schema (28–301).
- **Ustalenie:** hubIds łączy własne i tylko wynajmowane huby, potem kasuje po hub_id także rurociągi, przejazdy, zadania i zdarzenia; może usunąć dane huba właściciela, gdy usuwany gracz jest tenantem (90–129). Pliki są unlinkowane przed commit i nie podlegają rollback (112,155–171).
- **Zależności do sprawdzenia:** `src/Database.php`, `src/GameLog.php`, `src/ChatService.php`.
- **Granica dowodu:** Nie wykonano purge ani SQL; rozmiar szkody zależy od schematu i relacji, niezalecane uruchamianie na danych graczy.


### `src/AdminPlayerListQuery.php`

**Blob:** `d92959e3ab1b8804a5c6e1c304ad01e62ec4ca0d` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 88 linii.

- **Ustalenie:** AdminPlayerListQuery waliduje typy i daty YYYY-MM-DD, używa bind dla filtrów, allowlist sortu i half-open przedziałów dat; pobiera wszystkich graczy z correlated well count (11–87).
- **Zależności do sprawdzenia:** `tests/Unit/AdminPlayerListQueryTest.php`, `tests/MySqlIntegration/AdminPlayerListQueryMySqlTest.php`.
- **Granica dowodu:** Brak paginacji w fetch; skalowanie wymaga pomiaru; MySQL nieuruchomiony.


### `src/HomeDashboardQuery.php`

**Blob:** `f75dcd0f8c758baae3fa99a764e79dbc4cce199a` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 208 linii.

- **Ustalenie:** HomeDashboardQuery pobiera player-scoped aggregate, serię 7d/30d/1y i statystyki dostaw, rozróżnia empty/error; normalizuje nieznany okres (20–208).
- **Zależności do sprawdzenia:** `tests/Unit/HomeDashboardQueryTest.php`, `src/GameLog.php`.
- **Granica dowodu:** Rate zależy od observedHours(first,last,+300 s), nie rzeczywistego czasu bez ticków; bez danych produkcyjnych.


### `tests/MySqlIntegration/AdminDashboardMySqlTest.php`

**Blob:** `c828312fd9ecffdc273a812883d8ac6b20e564d5` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 76 linii.

- **Ustalenie:** Test standalone z config/database.php i tymczasowymi tabelami sprawdza transakcyjność korekt banku przy awarii audytu, potem locale-neutralne powiadomienie oraz autoryzację markAsRead (10–75).
- **Zależności do sprawdzenia:** `src/AdminBankAdjustment.php`, `src/DirectorNotificationService.php`, `config/database.php`.
- **Granica dowodu:** Używa faktycznego połączenia z konfiguracji; temp tables nie gwarantują braku efektów ensureSchema, więc nie uruchomiono.


### `tests/MySqlIntegration/AdminPlayerListQueryMySqlTest.php`

**Blob:** `3d64e99fb8962c5198b41ee320f4e63d2d274bd4` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 46 linii.

- **Ustalenie:** Test standalone łączy się z config/database.php, tworzy TEMPORARY tables i sprawdza daty login/rejestracji, kolejność, filtr oraz złośliwy sort/filter (10–44).
- **Zależności do sprawdzenia:** `src/AdminPlayerListQuery.php`, `config/database.php`.
- **Granica dowodu:** Sama tymczasowa tabela nie stanowi izolacji całego połączenia; MySQL nieuruchomiony.


### `tests/Unit/AdminPlayerListQueryTest.php`

**Blob:** `8780503997350b28272a6df40294a77c7c252e6f` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 42 linii.

- **Ustalenie:** Unit odrzuca 10 błędnych zakresów i formatów dat oraz wymaga kilkunastu kluczy etykiet PL/EN (10–41).
- **Zależności do sprawdzenia:** `src/AdminPlayerListQuery.php`, `lang/pl/admin/players.php`, `lang/en/admin/players.php`.
- **Granica dowodu:** Nie testuje SQL/MySQL ani wyświetlenia etykiet.


### `tests/Unit/AdminPlayersDateButtonsTest.php`

**Blob:** `c35daa4bc789a6f8dc21070b99689ffc72da7e1b` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 55 linii.

- **Ustalenie:** Renderuje widok bez rekordów i sprawdza link rozwijania filtra, zachowanie parametrów i ukryte inputy (11–54).
- **Zależności do sprawdzenia:** `templates/views/admin/players/main.php`, `src/AdminPlayerListQuery.php`.
- **Granica dowodu:** Nie wykonuje HTTP ani filtrowania bazy.


### `tests/Unit/AdminPlayersLayoutTest.php`

**Blob:** `d2ca5048d38bced9b11630866de1370e7e7c6fac` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 13 linii.

- **Ustalenie:** Asercja regex sprawdza wspólną, sztywną 10-kolumnową siatkę CSS nagłówka i wierszy (8–12).
- **Zależności do sprawdzenia:** `assets/css/admin_players.css`.
- **Granica dowodu:** Nie ocenia responsywności wizualnie.


### `tests/Unit/DashboardNewsWidgetTest.php`

**Blob:** `c942154615d021f51c2859149a032471cafebb9c` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 23 linii.

- **Ustalenie:** Kontrakt tekstowy wymaga dedykowanego dashboard_news.js, data-empty/error, fetch API i SHOW COLUMNS bez addColumnIfMissing (8–22).
- **Zależności do sprawdzenia:** `src/AdminNewsApi.php`, `assets/js/dashboard_news.js`, `templates/components/news_panel.php`.
- **Granica dowodu:** Nie wywołuje API ani renderowania JS.


### `tests/Unit/GameLogContentionTest.php`

**Blob:** `a1b620480a724011f3290fd160bc592dd2597b78` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 57 linii.

- **Ustalenie:** Test tworzy temp log, w drugim procesie PHP sprawdza fallback przy flock i limit czasu, po czym usuwa temp/fallback (8–56).
- **Zależności do sprawdzenia:** `src/GameLog.php`, `tests/Unit/BaseTestCase.php`.
- **Granica dowodu:** Wymaga PHP_BINARY/proc_open i locks; nie uruchomiono.


### `tests/Unit/GameLogReaderTest.php`

**Blob:** `74fc10b9245abd3aeff1ea26488730df27d9e2bd` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 112 linii.

- **Ustalenie:** Test na pliku tymczasowym sprawdza paging wstecz przez 250 linii, przekroczenie chunku, pruning starych wpisów i zachowanie pliku przy flock (11–111).
- **Zależności do sprawdzenia:** `src/AdminLogs/GameLogReader.php`, `tests/Unit/BaseTestCase.php`.
- **Granica dowodu:** Nie symuluje writerów podczas rename ani częściowego fwrite; PHPUnit nieuruchomiony.


### `tests/Unit/HomeDashboardContractTest.php`

**Blob:** `4c31ae6c4793d70dcea7550fe11dc64dee9be83c` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 34 linii.

- **Ustalenie:** Tekstowo wymaga komponentów statystyk i activity center, tablist, braku inline style/onclick, oraz równości kluczy home_dashboard PL/EN (8–33).
- **Zależności do sprawdzenia:** `templates/views/index/main.php`, `templates/components/activity_center.php`, `lang/pl/home.php`, `lang/en/home.php`.
- **Granica dowodu:** Nie renderuje dashboardu; brak kontroli języka DE.


### `tests/Unit/HomeDashboardQueryTest.php`

**Blob:** `c17990c0e3bbe5046055fe194d018e1300f9d3a0` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 88 linii.

- **Ustalenie:** SQLite memory sprawdza okres allowlist, player scope historii, wyliczone rates, empty != zero i monthly bucket roku (12–87).
- **Zależności do sprawdzenia:** `src/HomeDashboardQuery.php`.
- **Granica dowodu:** Nie mierzy MySQL ani błędów produkcyjnych; PHPUnit nieuruchomiony.


### `src/Hub/AssignmentQueryTrait.php`

**Blob:** `8910824c489e5ea9828e1abe66d5917ee83e148b` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 76 linii.

- **Ustalenie:** getWellAssignment opcjonalnie filtruje własność well, getHubWells bierze wszystkie przypisania huba, getHubWellsForPlayer dodaje warunek player_id (13–75).
- **Zależności do sprawdzenia:** `src/HubService.php`, `src/HubAssignmentService.php`.
- **Granica dowodu:** Wywołujący muszą rozróżniać metodę ogólną i per-player; bez testu HTTP.


### `src/Hub/AssignmentValidationTrait.php`

**Blob:** `5fe93bffa223a7e24a270fbe25816b1f71059c49` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 128 linii.

- **Ustalenie:** validateAssignment kontroluje status, region, slots, cooldown oraz ownership huba i odwiertu; getCooldownAssignment filtruje detached z future cooldown (14–128).
- **Ustalenie:** Jeśli hub ma niezerowego właściciela innego niż gracz, ale tenant_player_id wskazuje gracza, validateAssignment odrzuca go już na hubOwner check (33–38), podczas gdy getHubForPlayer dopuszcza owner OR tenant (QueryTrait:58–60). Rozbieżność warunkowa dla takiego rekordu.
- **Zależności do sprawdzenia:** `src/HubAssignmentService.php`, `src/Hub/QueryTrait.php`, `tests/MySqlIntegration/MySqlHubAssignmentConcurrencyTest.php`.
- **Granica dowodu:** Nie potwierdzono występowania takich rekordów ani działania MySQL.


### `src/Hub/EventsTrait.php`

**Blob:** `f100e3dd6a6887f191233b579321703dd985db63` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 43 linii.

- **Ustalenie:** createEvent zapisuje zdarzenie i tłumi błędy po GameLog::error; getRepairCost oblicza koszt z condition_pct i config hub_type (12–43).
- **Zależności do sprawdzenia:** `src/HubService.php`, `src/GameLog.php`.
- **Granica dowodu:** Metoda void nie daje callerowi gwarancji audytu zdarzenia; SQL nieuruchomiony.


### `src/Hub/IncidentEffectsTrait.php`

**Blob:** `5f28c75449a9fcf3e0e86b83fc467f70f2dfe2c7` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 141 linii.

- **Ustalenie:** IncidentEffects generuje stratę na processed_bbl, uszkadza hub z owner/tenant filter, loguje zdarzenie i powiadomienie z oddzielnymi catches (18–140).
- **Zależności do sprawdzenia:** `src/HubIncidentService.php`, `tests/MySqlIntegration/MySqlHubIncidentEffectsTraitTest.php`.
- **Granica dowodu:** Błąd eventu/notification jest tłumiony, więc zwrócony incydent nie dowodzi zapisu powiadomienia.


### `src/Hub/IncidentRiskTrait.php`

**Blob:** `f059c6e7c903db4e68a1cc54fa1319c3b48e54de` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 90 linii.

- **Ustalenie:** IncidentRisk definiuje sześć typów i mnożników condition/load/mode/acquisition/HSE; HSE ma dolny limit redukcji (10–89).
- **Zależności do sprawdzenia:** `src/HubIncidentService.php`, `tests/Unit/HubIncidentServiceTest.php`.
- **Granica dowodu:** Bez empirycznej symulacji rozkładu ryzyka; test sprawdza wybrane wartości.


### `src/Hub/QueryTrait.php`

**Blob:** `a9ae3d146e0bf95c015f0f638f41867b59991986` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 66 linii.

- **Ustalenie:** HubQueryTrait::getHub jest lookupiem bez ownership dla kodu wewnętrznego; getHubForPlayer filtruje owner OR tenant i zwraca assigned_count (26–65).
- **Zależności do sprawdzenia:** `src/HubService.php`, `src/HubApi.php`.
- **Granica dowodu:** Bez inspekcji wszystkich callerów nie przypisywać zabezpieczenia getHub publicznym trasom.


### `src/HubEconomyService.php`

**Blob:** `a7361c02ef27cf2914597d635e80c6819e15ac35` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 165 linii.

- **Ustalenie:** HubEconomyService oblicza build cost z regionem, OPEX condition/work mode/acquisition/lease, oraz viability na średnim wolumenie przeliczonym na bbl/h (22–164).
- **Zależności do sprawdzenia:** `src/HubService.php`, `tests/Unit/HubEconomyServiceTest.php`.
- **Granica dowodu:** Viability = 99 przy niezerowej historii i opex<=0, niezależnie od przychodu; to konwencja, nie gwarancja zysku.


### `src/HubIncidentService.php`

**Blob:** `50503a22cc3e5602f2b872724f5c2b90737c71a8` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 318 linii.

- **Ustalenie:** HubIncidentService dopuszcza tylko aktywne statusy, wymaga aktywności, losuje jeden incydent w kolejności severity, nakłada HSE/protection owner i udostępnia listy per-player oraz admin forceIncident (71–310).
- **Ustalenie:** forceIncident ma domyślny playerId=0; generateIncident pomija wtedy condition damage, a event/notification dla player_id=0 mogą się nie zapisać. Jedyny znaleziony caller admin przekazuje playerId jawnie, więc to granica publicznej metody, nie dowiedziony błąd ścieżki admin.
- **Zależności do sprawdzenia:** `src/AdminHub/PostActionsTrait.php`, `src/Hub/IncidentEffectsTrait.php`, `tests/Unit/HubIncidentServiceTest.php`.
- **Granica dowodu:** Nie uruchomiono ticka/forceIncident; skuteczność ochrony zależy od danych.


### `src/HubService.php`

**Blob:** `bed496de9efc44378abd855a70bbbe40e15598ab` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 42 linii.

- **Ustalenie:** HubService składa siedem traitów; już konstruktor wykonuje ensureHubSchema, loadHubConfig i ensureAcquisitionConfigDefaults (3–41).
- **Zależności do sprawdzenia:** `src/Hub/ConfigTrait.php`, `src/Hub/QueryTrait.php`, `src/Hub/AdminActionsTrait.php`.
- **Granica dowodu:** To nie jest czysty odczyt: wymagają kontroli DDL/side effects w ConfigTrait przy migracji.


### `src/HubViewService.php`

**Blob:** `d42b52cdaf534c330b1222ce7c12ff24bc61ec5a` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 29 linii.

- **Ustalenie:** HubViewService to cienka fasada dwóch traitów widoku z wstrzykniętymi DB, HubService i HubEconomyService; konstruktor sam niczego nie zapisuje (3–29).
- **Zależności do sprawdzenia:** `src/Hub/ViewSummaryTrait.php`, `src/Hub/ViewHubsTrait.php`.
- **Granica dowodu:** Logika widokowa leży w traitach, które nie należą do tej karty.


### `tests/Integration/HubMarketPaginationTest.php`

**Blob:** `9c768ac46ec57c29e1d8cc7d5f4838f91a65a3ea` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 89 linii.

- **Ustalenie:** SQLite in-memory sprawdza, że oferty huba paginują 14 dostępnych bez duplikatów, filtr/szukaj działa przed count, wildcard % jest literalny, brak aktywnego regionu daje zero (11–89).
- **Zależności do sprawdzenia:** `src/Hub/PlayerQueryTrait.php`, `tests/Integration/SqliteIntegrationTestCase.php`.
- **Granica dowodu:** Nie testuje MySQL pod obciążeniem ani konkurencyjnej zmiany ofert.


### `tests/MySqlIntegration/MySqlHubAssignmentConcurrencyTest.php`

**Blob:** `e2a78ca1d6665ac570cc32d0d492d2583d1a5ce7` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 81 linii.

- **Ustalenie:** MySQL test seeduje dwóch workerów PHP przez plik gate na jedno ostatnie miejsce w hubie; oczekuje jednego sukcesu i jednej aktywnej relacji (9–80).
- **Zależności do sprawdzenia:** `src/HubAssignmentService.php`, `tests/fixtures/hub_assignment_concurrent_worker.php`, `tests/MySqlIntegration/MySqlIntegrationTestCase.php`.
- **Granica dowodu:** Nie uruchomiono: setup może czyścić realną bazę; test wymaga proc_open i izolacji DB.


### `tests/MySqlIntegration/MySqlHubHseIntegrationTest.php`

**Blob:** `72ce848751da944e339b86faa8b2ea193fd52037` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 79 linii.

- **Ustalenie:** MySQL sprawdza obciążenie BHP od nadzorowanego huba, następnie wpływ realnego bonusu na wear, condition i ryzyko incydentów (12–78).
- **Zależności do sprawdzenia:** `src/HubTickService.php`, `src/HubIncidentService.php`, `tests/MySqlIntegration/MySqlIntegrationTestCase.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL; sam odczyt nie potwierdza wyliczeń.


### `tests/MySqlIntegration/MySqlHubIncidentEffectsTraitTest.php`

**Blob:** `be224d8ef8d4e10e302c4f367ab0d64917f98bfb` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 102 linii.

- **Ustalenie:** MySQL reflection wywołuje applyConditionDamage z obcym i własnym player_id, oraz generateIncident dla tenant huba player_id=0 (21–101).
- **Zależności do sprawdzenia:** `src/Hub/IncidentEffectsTrait.php`, `tests/MySqlIntegration/MySqlIntegrationTestCase.php`.
- **Granica dowodu:** Nie sprawdza tenant z innym niezerowym właścicielem ani całego ticka; nieuruchomione.


### `tests/MySqlIntegration/MySqlHubOutboundPipelineBalanceTest.php`

**Blob:** `b3036ca81cc99e2236865afccbb993571a317e40` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 231 linii.

- **Ustalenie:** MySQL bada bilans baryłek przy uszkodzonym outbound, 10 tickach aktywnego pipeline i stracie incydentu po utracie outbound (59–230).
- **Zależności do sprawdzenia:** `src/Tick/WellHubSection.php`, `src/HubTickService.php`, `tests/MySqlIntegration/MySqlIntegrationTestCase.php`.
- **Granica dowodu:** Wymaga izolowanego MySQL; fixture ręcznie wypełnia kontekst, nie bada każdej produkcyjnej ścieżki.


### `tests/MySqlIntegration/MySqlHubStorageBlockedRebufferTest.php`

**Blob:** `18a11691e527460bd746f9ace905632743951460` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 194 linii.

- **Ustalenie:** MySQL sprawdza rebuffer przy pełnym magazynie, zwykłą dostawę przy wolnym i nowy input pipeline przy pełnym; porównuje bilans z tolerancją (57–193).
- **Zależności do sprawdzenia:** `src/Tick/WellHubSection.php`, `tests/MySqlIntegration/MySqlIntegrationTestCase.php`.
- **Granica dowodu:** Nie uruchomiono; brak konkluzji o wszystkich kombinacjach transportu.


### `tests/MySqlIntegration/MySqlRoadHubDeliveryTest.php`

**Blob:** `b71f618ce0c5fbf1c0585e4c92ccbcbe02e84c25` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 118 linii.

- **Ustalenie:** MySQL testuje dwóch trucków (hub/non-hub) przy pełnym magazynie i wolnym, bilans delivered+lost i DB delivered_bbl; hubowy input jest optymistycznie kredytowany przed finalizacją (34–117).
- **Zależności do sprawdzenia:** `src/Tick/WellRoadTripSection.php`, `tests/MySqlIntegration/MySqlIntegrationTestCase.php`.
- **Granica dowodu:** Nie uruchomiono; nie dowodzi finalnej bilansowej zgodności gdy późniejszy finalize zawiedzie.


### `tests/MySqlIntegration/MySqlTechnicalHubTasksTest.php`

**Blob:** `e3a9e3821d6bcd4451508d23cd6fc4ba2988e3b6` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 75 linii.

- **Ustalenie:** MySQL seeduje pracownika i huby, przydziela/kończy konserwację przez processTick oraz sprawdza kolejkę drugiego zadania (9–74).
- **Zależności do sprawdzenia:** `src/TechnicalTeamService.php`, `tests/MySqlIntegration/MySqlIntegrationTestCase.php`.
- **Granica dowodu:** Test realnej bazy nie został uruchomiony.


### `tests/Unit/HubEconomyServiceTest.php`

**Blob:** `bc65f5b30b1a26b5c135e332738421edf0ca1995` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 92 linii.

- **Ustalenie:** Mock HubService testuje regionalny build cost, OPEX rental z kondycją i viability 3 historycznych ticków (8–91).
- **Zależności do sprawdzenia:** `src/HubEconomyService.php`, `src/HubService.php`.
- **Granica dowodu:** Nie dotyka prawdziwej konfiguracji, DB ani waluty gry; PHP nieuruchomione.


### `tests/Unit/HubIncidentServiceTest.php`

**Blob:** `df2b98b2d13ec32060f28777447bd4663926da57` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 146 linii.

- **Ustalenie:** SQLite z funkcjami NOW/GREATEST testuje kalkulację ryzyka, brak incydentu paused, możliwość incydentu z drenażu oraz runtime incident_mult=0 (10–145).
- **Zależności do sprawdzenia:** `src/HubIncidentService.php`, `src/Hub/IncidentRiskTrait.php`.
- **Granica dowodu:** Nie wykonano PHP; mt_srand scenariusza nie weryfikuje rozkładu ryzyka.


### `src/BlackMarketApi.php`

**Blob:** `bba0d2fdee58747507f329510c8501447b667eb7` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 125 linii.

- **Ustalenie:** Endpoint black market wymaga login, CSRF i RateLimiter na POST; GET oferty/historia, POST sell z offerId>0 (16–125).
- **Ustalenie:** W GET offers przy błędzie tabeli JSON dla zalogowanego gracza zawiera e->getMessage() i debug=true, co może ujawnić szczegóły SQL/hostingu (44–58).
- **Zależności do sprawdzenia:** `src/BlackMarketService.php`, `src/CSRF.php`, `src/RateLimiter.php`.
- **Granica dowodu:** Nie uruchomiono HTTP, ekspozycja zależy od treści wyjątku.


### `src/Bribery/BriberyConfig.php`

**Blob:** `7fdfcd0a313a88d140fef128932507d24b7fc3ab` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 221 linii.

- **Ustalenie:** BriberyConfig::ensureSchema tworzy tabelę i seeduje defaults w konstruktorze poza transakcją; gettery ograniczają procenty/ujemne, save zapisuje znane klucze jeden po drugim (64–220).
- **Ustalenie:** schemaEnsured jest oznaczone przed DDL i insertami, a wyjątek jest połykany; niepowodzenie na tym PDO nie zostanie ponowione. save nie otwiera transakcji: awaria może zostawić część zmian.
- **Zależności do sprawdzenia:** `src/BriberyService.php`, `tests/Integration/BriberyServiceTest.php`.
- **Granica dowodu:** Bez PHP/MySQL; skutki zależą od awarii i callera.


### `src/BriberyService.php`

**Blob:** `33db3cbb0ec1c67938308fe1536abec1ee9200fc` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 219 linii.

- **Ustalenie:** BriberyService wycenia ryzyko/koszt z reputacji, pobiera gotówkę, losuje caught, wykonuje callback, księguje reputację i commit; opcjonalny alert caught jest best effort (62–218).
- **Ustalenie:** beginTransaction jest przed try, więc wywołanie z aktywnej transakcji rzuci przed catch; callback może mieć własne nie-DB side effects nieobjęte rollback. Brak idempotency tokenu dla równoległych/retry prób.
- **Zależności do sprawdzenia:** `src/Bribery/BriberyConfig.php`, `src/FinancialTransactionService.php`, `src/Legal/BriberyTrait.php`.
- **Granica dowodu:** Nie testowano równoległych transakcji ani callbacków spoza DB.


### `src/Legal/ApplicationHistory.php`

**Blob:** `169706cdf6f2ced2c805e4650040b071943f5bf4` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 171 linii.

- **Ustalenie:** LegalApplicationHistory.ensureSchema ma wariant MySQL/SQLite i uzupełnia fee_transaction_id; archive wymaga transakcji/własności, forPlayer agreguje archiwum i dopasowane wcześniejsze fee (12–170).
- **Zależności do sprawdzenia:** `src/FinancialTransactionService.php`, `tests/MySqlIntegration/MySqlLegalServiceTest.php`.
- **Granica dowodu:** DDL może wykonać się poza transakcją; nie badano wszystkich migracji/schematu.


### `src/Legal/BriberyTrait.php`

**Blob:** `aa1d06c7b5af666236b7b213c97c1247b0cb7fc5` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 133 linii.

- **Ustalenie:** LegalBriberyTrait sprawdza status wniosku i bribe_locked_until przed BriberyService::attempt; callback sukcesu nadaje grant, wpadki ustawia cooldown/transitional upgrade (43–131).
- **Ustalenie:** Sprawdzenie eligibility jest przed transakcją i nie ma claim ani warunkowego UPDATE statusu w callbacku; dwie równoległe próby mogą być obie opłacone, mimo że pierwsza już nadała grant. Scenariusz statyczny.
- **Zależności do sprawdzenia:** `src/BriberyService.php`, `src/LegalService.php`, `tests/Integration/BriberyServiceTest.php`.
- **Granica dowodu:** Nie uruchomiono testu równoległego; zależne od współbieżności/locków.


### `src/Market.php`

**Blob:** `5fe8c2d92a956d464d087084757a7fe8260a8382` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 89 linii.

- **Ustalenie:** Market::ensureState próbuje INSERT IGNORE singleton id=1, ale ustawia lokalne done przed próbą DB, więc przejściowy błąd nie daje retry w procesie; getCurrentPrice zwraca 0 przy braku stanu (21–87).
- **Zależności do sprawdzenia:** `src/Database.php`, `src/MarketTick.php`.
- **Granica dowodu:** Nie uruchomiono DB; efekt zależy od bootstrap i procesu.


### `src/MarketSaleService.php`

**Blob:** `cd5679cc7aeae186e4496963d7f2be420bd13d99` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 61 linii.

- **Ustalenie:** sellInstant blokuje player/storage, debituje storage warunkowo, kredytuje przez FinancialTransactionService i commit; finally rollbackuje pozostałą transakcję (16–60).
- **Ustalenie:** Jeśli caller ma już transakcję na tym PDO, beginTransaction rzuci, a finally może wykonać rollBack transakcji callera. Wymaga jawnego kontraktu transakcyjnego. Zwrócenie no-stock po beginTransaction jest domykane przez finally.
- **Zależności do sprawdzenia:** `src/FinancialTransactionService.php`, `src/Market.php`.
- **Granica dowodu:** Warunkowo przy zewnętrznej transakcji; PHP/MySQL nieuruchomione.


### `src/MarketTick.php`

**Blob:** `06a806029f9659f6142781dedd649385e3d50bc5` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 305 linii.

- **Ustalenie:** updatePrices wylicza price z supply/world demand/trend/noise, zapisuje market_state, historie i oferty, lecz wyjątki pomocnicze są łapane; fallback odczytuje cenę (40–305).
- **Ustalenie:** getMarketSnapshot wywołuje nieistniejące fetchPlayerSupply() (265), gdy w klasie zdefiniowano fetchPlayerSupplyFlow() (159); Error jest schwytany i metoda zwraca [], więc ścieżka snapshotu nigdy nie daje danych przy istniejącym state.
- **Zależności do sprawdzenia:** `src/MarketOffer.php`, `src/Tick/MarketSection.php`, `src/GameLog.php`.
- **Granica dowodu:** Bez PHP/MySQL; jednoznaczne niedopasowanie nazwy metody w pełnym pliku.


### `src/MarketTrend.php`

**Blob:** `5c61954c0ec489fcd3f867ad2e6cccbf9342f5f0` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 268 linii.

- **Ustalenie:** MarketTrend czyta aktywny trend, wybiera losowy po cooldown 30 min, aktywuje/dezaktywuje oraz tłumi błędy SQL w swoich metodach (21–268).
- **Ustalenie:** activateTrend robi globalny UPDATE active=FALSE, potem osobny UPDATE id=TRUE bez własnej transakcji. Błąd drugiej operacji może zostawić rynek bez trendu, jeśli caller nie zapewnia transakcji.
- **Zależności do sprawdzenia:** `src/Tick/MarketSection.php`, `src/GameLog.php`.
- **Granica dowodu:** Nie testowano SQL/callera; ryzyko warunkowe.


### `src/Tick/MarketSection.php`

**Blob:** `e62a59524737603f71febbbc10f97a0daf8ce835` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 66 linii.

- **Ustalenie:** MarketSection uruchamia trendy, cenę i purge sale history, osobno łapiąc błędy; newPrice pozostaje domyślnym 0, gdy runPrice rzuci przed przypisaniem (14–66).
- **Zależności do sprawdzenia:** `src/MarketTrend.php`, `src/MarketTick.php`, `src/MarketOffer.php`.
- **Granica dowodu:** Nie uruchomiono ticka, a obsługę newPrice przez wywołującego trzeba prześledzić.


### `tests/MySqlIntegration/MySqlApiSchemaContractTest.php`

**Blob:** `e4808f13375594b2a69e5c457267ccddbdf05f10` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 153 linii.

- **Ustalenie:** Kontrakt MySQL sprawdza kolumny tabel API v1 w information_schema, ale setUp najpierw wywołuje ApiAuth::ensureSchema, konstruktor LegalService i TechnicalTeamService, które mogą modyfikować schemat (93–153).
- **Zależności do sprawdzenia:** `src/ApiAuth.php`, `src/LegalService.php`, `src/TechnicalTeamService.php`, `config/database.php`.
- **Granica dowodu:** To nie jest test read-only; nie uruchomiono na bieżącej bazie.


### `tests/MySqlIntegration/MySqlBlackMarketDecayScalingTest.php`

**Blob:** `72ca110e5ed81dad7eae1b2d61f75c6442c7a002` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 97 linii.

- **Ustalenie:** MySQL sprawdza flat decay score dla 5-min i 2h catch-up, clamp 0 oraz stats modułu BlackMarketModule (17–96).
- **Zależności do sprawdzenia:** `src/BlackMarketService.php`, `src/Tick/Modules/BlackMarketModule.php`, `tests/MySqlIntegration/MySqlIntegrationTestCase.php`.
- **Granica dowodu:** setDecayConfig upsertuje globalny well_config, test wymaga izolowanej bazy; nieuruchomiony.


### `tests/MySqlIntegration/MySqlLegalServiceTest.php`

**Blob:** `fec390d2a7658c0322e670456888fa3fa5c1344d` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 223 linii.

- **Ustalenie:** MySQL testuje ensureSchema prawny, seed region, status permit, unique, opłatę, historyczne retry/upgrades oraz lokalny hub permit (20–222).
- **Zależności do sprawdzenia:** `src/LegalService.php`, `src/Legal/ApplicationHistory.php`, `tests/MySqlIntegration/MySqlIntegrationTestCase.php`.
- **Granica dowodu:** setUp i tearDown usuwają rekordy, w tym po region_id; nie uruchamiać bez izolacji.


### `tests/MySqlIntegration/MySqlMarketOfferOwnershipTest.php`

**Blob:** `60ab49ec705b927dcc6c879f3bdd12deef08e899` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 75 linii.

- **Ustalenie:** MySQL twice wywołuje executeOffer na stale kopii, oczekuje jednego credit, jednego sale_history i transakcji bankowej; tearDown usuwa te rekordy (11–75).
- **Zależności do sprawdzenia:** `src/MarketOffer.php`, `tests/MySqlIntegration/MySqlIntegrationTestCase.php`.
- **Granica dowodu:** Bez testu dwóch rzeczywiście równoległych workerów; MySQL nieuruchomiony.


### `tests/Unit/LegalPagesLocalizationTest.php`

**Blob:** `32a8d1c2a0ae51472e777c68ab650cdff04dcf3c` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 106 linii.

- **Ustalenie:** Sprawdza locale fallback pl, klucze PL/EN/DE, podstawienie daty oraz obecność OilEmpire w dziewięciu templates prawnych (16–105).
- **Zależności do sprawdzenia:** `src/i18n.php`, `templates/views/public/pages/regulamin_pl.php`.
- **Granica dowodu:** Nie ocenia zgodności prawnej tekstów, tylko dostępność i markę; PHP nieuruchomione.


### `tests/Unit/LogisticsPageViewContractTest.php`

**Blob:** `998d572f3ef6d78e26fa4acf89972d74b6bddd4b` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 124 linii.

- **Ustalenie:** Asercje kontraktu porównują 60+ kluczy danych widoku logistics, kolejność 22 include i 6 anchorów, delegację wejścia POST/buildViewData (9–123).
- **Zależności do sprawdzenia:** `src/LogisticsPageController.php`, `public/logistics.php`, `templates/views/logistics/main.php`.
- **Granica dowodu:** Nie dowodzi wartości pól ani działania UI.


### `tests/Unit/PlayerNavEditorContractTest.php`

**Blob:** `25947866a9b50e5d976442b1775c21678a4ad60b` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 60 linii.

- **Ustalenie:** Tekstowo sprawdza editor ikon/nawigacji, live preview i wybrane niemieckie etykiety różne od PL/EN (8–59).
- **Zależności do sprawdzenia:** `admin/template_editor.php`, `templates/header.php`, `assets/js/player_menu_editor.js`.
- **Granica dowodu:** Nie sprawdza upload bezpieczeństwa ani renderingu.


### `tests/Unit/PublicHomepageContractTest.php`

**Blob:** `0a24a4e11d0629bad1d264c710c7483a843c980b` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 90 linii.

- **Ustalenie:** Kontrakt strony publicznej wymaga gałęzi landing przed login guard, assets, trzy tabs/tabpanel, fallback no-JS i toggle w template editor (8–89).
- **Zależności do sprawdzenia:** `public/index.php`, `templates/views/public/home/main.php`, `assets/js/public_home.js`.
- **Granica dowodu:** Odczyt tekstowy nie zastępuje żądania anonimowego ani testu dostępności.


### `tests/Unit/SabotagePageContractTest.php`

**Blob:** `4b90b63c6e8bc24d841b85247107568887c99af0` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 52 linii.

- **Ustalenie:** Asercje tekstowe sabotage controller sprawdzają login/board/CSRF/rate token i historie, retencję ticka, klucze PL/EN/DE oraz pola dialogu (10–51).
- **Zależności do sprawdzenia:** `public/sabotage.php`, `cron/tick.php`, `templates/views/sabotage/main.php`.
- **Granica dowodu:** Nie wykonuje sabotażu ani nie potwierdza izolacji targetów.


### `tests/Unit/TechnicalOperationsViewContractTest.php`

**Blob:** `56e12c73b305ce10555cbd62518e82191c4864f5` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 283 linii.

- **Ustalenie:** Liczne kontrakty tekstowe dotyczą stanu pracowników, odświeżenia danych po processTick, skutecznej produkcji, filtrowania incydentów, linków naprawy i tłumaczeń DE (13–282).
- **Zależności do sprawdzenia:** `src/TechnicalPage/ViewDataTrait.php`, `templates/views/technical/tabs/infra.php`, `assets/js/technical.js`.
- **Granica dowodu:** Bez uruchomienia PHP/JS nie weryfikują faktycznego targetu lub uprawnień akcji.


### `src/LogisticsApi.php`

**Blob:** `92f51d4041d69bd767fe7694adc12a73a40563b1` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 74 linii.

- **Ustalenie:** LogisticsApi publicznym adapterem prowadzi GET summary/cooldown i POST optimize, sprawdza login/CSRF, ale ogranicza BoardAccess wyłącznie gdy klasa już załadowana (10–74).
- **Ustalenie:** src/init.php i adapter api/internal/LogisticsApi.php nie ładują BoardAccess jawnie; class_exists('BoardAccess', false) nie wywoła autoload. Gdy klasa nie jest zainicjalizowana pobocznie, bramka ról jest pominięta dla zalogowanego użytkownika. Krytyczny warunek autoryzacji do potwierdzenia w HTTP.
- **Zależności do sprawdzenia:** `api/internal/LogisticsApi.php`, `src/init.php`, `src/BoardAccess.php`, `src/LogisticsService.php`.
- **Granica dowodu:** Nie uruchomiono HTTP; możliwe pośrednie wcześniejsze załadowanie klasy zależne od bootstrap.


### `src/LogisticsIncidentFeed.php`

**Blob:** `1abc843b0e4ffea916a7a6ba3152e6db5906a68b` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 75 linii.

- **Ustalenie:** LogisticsIncidentFeed łączy zdarzenia hub/pipeline po player_id, filtruje severity i substring, sortuje deterministycznie i paginuje (15–75).
- **Zależności do sprawdzenia:** `tests/Integration/LogisticsIncidentFeedTest.php`, `src/HubIncidentService.php`.
- **Granica dowodu:** LIKE escape zależy od trybu SQL i collation; brak testów z wildcardem.


### `src/LogisticsIncidentRetentionService.php`

**Blob:** `dad10fe9b0e91992a144bf8c30273f23a280cb4d` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 57 linii.

- **Ustalenie:** Retention usuwa tylko hub_incident_*/pipeline incident starsze niż cutoff w maksymalnie 4 partiach po 500; przed DELETE ponawia filtr daty/typu (18–57).
- **Zależności do sprawdzenia:** `src/LogisticsIncidentFeed.php`, `tests/Integration/LogisticsIncidentRetentionServiceTest.php`.
- **Granica dowodu:** Nie uruchomiono SQL; brak planu/indeksów i wyczerpującego backlog testu.


### `src/MarineHistoryService.php`

**Blob:** `8ec82169c387bd0d1282d346131b328835416e57` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 39 linii.

- **Ustalenie:** MarineHistoryService paginuje po 5 completed deliveries gracza, stable order, usuwa status delivered/lost starsze od cutoff przez COALESCE dat (13–38).
- **Zależności do sprawdzenia:** `tests/Integration/MarineHistoryServiceTest.php`.
- **Granica dowodu:** Retention globalna, nie player scoped; to zamierzone zadanie maintenance, nie bezpieczne do wykonania na bieżącej DB.


### `src/Tick/CredibilitySection.php`

**Blob:** `702ac5c05de51159bbb875898681e908c7bc4120` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 57 linii.

- **Ustalenie:** CredibilitySection przechodzi graczy innych niż bankrupt, uruchamia clean bonus per gracz, loguje błędy jednostkowo i liczy sprawdzonych/nagrodzonych (23–57).
- **Zależności do sprawdzenia:** `src/CompanyCredibilityService.php`.
- **Granica dowodu:** Nie uruchomiono ticka; side effects i zasady bonusu zależą od serwisu.


### `src/Tick/MoraleSection.php`

**Blob:** `f73ee4fb1341fb844d8fc5820430f0134f8b675a` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 78 linii.

- **Ustalenie:** MoraleSection modyfikuje legacy current_morale technical_staff i losuje strajki w staff_strikes (17–78).
- **Ustalenie:** Nie znaleziono aktywnego new MoraleSection w śledzonych PHP; traktować jako potencjalny legacy kod, nie potwierdzony element obecnego ticka.
- **Zależności do sprawdzenia:** `src/HR/StrikeService.php`, `src/Tick/Modules/EmployeesModule.php`.
- **Granica dowodu:** Brak uruchomienia ticka i pełnego dynamicznego call graph; wniosek o nieużyciu tylko statyczny.


### `src/Tick/PlayerTickBoundaryTrait.php`

**Blob:** `b35631336c06b44654c7185c924fcc74eb211432` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 46 linii.

- **Ustalenie:** PlayerTickBoundaryTrait buduje usługi, wykonuje addColumnIfMissing i schema check przed transakcją per player, po błędzie przywraca liczniki pamięci procesu (10–46).
- **Zależności do sprawdzenia:** `src/Tick/PlayerTickTransaction.php`, `src/Tick/PlayerTickSchemaCheck.php`, `tests/MySqlIntegration/MySqlPlayerTickAtomicityTest.php`.
- **Granica dowodu:** Konstrukcje nadal mogą mieć efekty uboczne poza rollback gracza; nie uruchomiono ticka.


### `src/Tick/PlayerTickSchemaCheck.php`

**Blob:** `beacabad66ec9e1113bb419729ea13cf12cc6860` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 31 linii.

- **Ustalenie:** PlayerTickSchemaCheck sprawdza engine<>InnoDB na liście 20 kluczowych tabel MySQL i memoizuje wynik w WeakMap PDO (9–31).
- **Zależności do sprawdzenia:** `src/Tick/PlayerTickBoundaryTrait.php`.
- **Granica dowodu:** Nie wykrywa tabel nieistniejących ani nowych pominiętych w allowliście; brak testu realnego schematu.


### `src/Tick/PlayerTickTransaction.php`

**Blob:** `51047685db4184eb79c2682a66feb7187795b3bc` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 53 linii.

- **Ustalenie:** PlayerTickTransaction przy braku transakcji otwiera własną, przy istniejącej tworzy savepoint; blokuje player/storage, sprawdza niezamknięcie i rollbackuje przy wyjątku (14–53).
- **Zależności do sprawdzenia:** `src/Tick/PlayerTickBoundaryTrait.php`.
- **Granica dowodu:** Wewnętrzny savepoint ma stałą nazwę, bez testu rekursji; nie uruchomiono MySQL.


### `src/Tick/TickModule.php`

**Blob:** `edc3001f2e1c563c97e306e4d505239c178ecbc8` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 26 linii.

- **Ustalenie:** TickModule wymaga key/order/failurePolicy/run/stats, kontrakt bez implementacji (10–26).
- **Zależności do sprawdzenia:** `src/Tick/TickFailurePolicy.php`, `src/Tick/TickEngine.php`.
- **Granica dowodu:** Nie mówi nic o polityce konkretnego modułu; testów nie wykonywano.


### `tests/Integration/LogisticsIncidentFeedTest.php`

**Blob:** `ebec9cad80e40fcd170394eaa2bfba9766801555` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 37 linii.

- **Ustalenie:** SQLite memory sprawdza UNION hub/pipeline event, izolację gracza, severity map, search i paging (10–36).
- **Zależności do sprawdzenia:** `src/LogisticsIncidentFeed.php`.
- **Granica dowodu:** Nie wykonano PHP; nie bada wildcard %/escape ani produkcyjnego MySQL.


### `tests/Integration/MarineHistoryServiceTest.php`

**Blob:** `b360bdba04f9c3013d5d243d0e1706fd0b8e0d79` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 32 linii.

- **Ustalenie:** SQLite memory sprawdza stabilny paging 12 dostaw i retention tylko terminalnych delivered/lost starszych niż cutoff (7–31).
- **Zależności do sprawdzenia:** `src/MarineHistoryService.php`, `tests/Integration/SqliteIntegrationTestCase.php`.
- **Granica dowodu:** Nie potwierdza retencji w prawdziwym harmonogramie; PHP nieuruchomione.


### `tests/Integration/SqliteIntegrationTestCase.php`

**Blob:** `146fea99742152239bc481381a8ccf3eb574a8b4` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 34 linii.

- **Ustalenie:** Baza testowa SQLite powstaje w pamięci, ma mock funkcji NOW/LEAST/GREATEST i helper ReflectionProperty (6–34).
- **Zależności do sprawdzenia:** `tests/Unit/BaseTestCase.php`.
- **Granica dowodu:** Nie zapewnia zgodności MySQL; pozostałe testy korzystające z tego case nadal wymagają odczytu własnych setup.


### `tests/MySqlIntegration/MySqlLogisticsIncidentFeedTest.php`

**Blob:** `b87296e4bced25dfb36d15eb2692374c36ecd79d` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 39 linii.

- **Ustalenie:** MySQL sprawdza player scope i paging feedu hub/pipeline, severity i wyszukiwanie (9–38).
- **Zależności do sprawdzenia:** `src/LogisticsIncidentFeed.php`, `tests/MySqlIntegration/MySqlIntegrationTestCase.php`.
- **Granica dowodu:** SetUp i manualny DELETE w finally; bez izolacji nie uruchomiono.


### `tests/MySqlIntegration/MySqlLogisticsOwnershipFlowTest.php`

**Blob:** `4dca0ae821db4ee4ff2aeffe793d408d6ccddc21` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 67 linii.

- **Ustalenie:** MySQL testuje legacy transport rurociąg bez fizycznej rury, fallback optimizer na truck oraz aktywną rurę właściciela (11–66).
- **Zależności do sprawdzenia:** `src/LogisticsService.php`, `src/WellPipelineService.php`, `tests/MySqlIntegration/MySqlIntegrationTestCase.php`.
- **Granica dowodu:** Nie uruchomiono; nie dowodzi bezpieczeństwa przestawienia współbieżnie.


### `tests/MySqlIntegration/MySqlPlayerTickAtomicityTest.php`

**Blob:** `08d9af4abe81765d0a5e65a7ac57ee6d69cced47` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 98 linii.

- **Ustalenie:** TickFaultPdo wstrzykuje pięć awarii persist (storage/log/payroll/road/financial), sprawdza rollback okresu i retry bez powtórzenia; wyłapuje exec DDL w transakcji (7–97).
- **Zależności do sprawdzenia:** `src/Tick/PlayersSection.php`, `src/Tick/PlayerTickTransaction.php`, `tests/MySqlIntegration/MySqlIntegrationTestCase.php`.
- **Granica dowodu:** test łączy z config/database.php i usuwa rekordy w tearDown; nie uruchomiono na nieizolowanym DSN.


### `tests/MySqlIntegration/MySqlTickCoordinatorLockTest.php`

**Blob:** `8de5eab67f4596f142a6b2507ee1a52052b64b16` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 96 linii.

- **Ustalenie:** MySQL advisory lock oilcorp_tick blokuje coordinator; single disabled legal module nie nadpisuje pełnej historii ani last_system_tick_at (25–95).
- **Zależności do sprawdzenia:** `src/Tick/TickCoordinator.php`, `tests/MySqlIntegration/MySqlIntegrationTestCase.php`.
- **Granica dowodu:** Test używa drugiego realnego połączenia i zapisuje well_config; nie uruchomiono.


### `tests/MySqlIntegration/MySqlTickHistoryQueryTest.php`

**Blob:** `27aee19da1339881bca8b86ad37594a9c23c39af` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 46 linii.

- **Ustalenie:** Standalone MySQL test shadowuje tick_stats dopiero po skonstruowaniu TickStatsRepository, potem sprawdza native prepared paging i brak ciężkich JSON w summary (9–45).
- **Zależności do sprawdzenia:** `src/Tick/TickStatsRepository.php`, `src/AdminLogs/TickHistoryQuery.php`, `config/database.php`.
- **Granica dowodu:** Konstruktor repozytorium przed TEMPORARY TABLE może wykonywać schema cleanup na stałej tabeli; test nie jest bezpieczny na bieżącej bazie.


### `tests/MySqlIntegration/MySqlTickHistoryRetentionTest.php`

**Blob:** `fb32630b063105b051083d045ca9fb13947a809a` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 56 linii.

- **Ustalenie:** Standalone MySQL sprawdza retencję 48h i ograniczenie częstotliwości dla daty starej/nowej/przyszłej/invalid na connection-local tables (9–55).
- **Zależności do sprawdzenia:** `src/Tick/TickStatsRepository.php`, `src/Tick/TickCoordinator.php`, `config/database.php`.
- **Granica dowodu:** Repozytorium i config tworzone przed shadow tabel, więc side effects na stałych tabelach możliwe; nie uruchomiono.


### `tests/MySqlIntegration/MySqlTickSequenceTest.php`

**Blob:** `48043a13fb75962cdcd89ef91f71a4c0d164bb68` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 75 linii.

- **Ustalenie:** TickSequenceTemporaryPDO podmienia CREATE TABLE na TEMPORARY, test sprawdza monotoniczny tick_run_sequence, restart coordinator i due interval=1 (8–75).
- **Zależności do sprawdzenia:** `src/Tick/TickCoordinator.php`, `src/Tick/TickModuleScheduler.php`.
- **Granica dowodu:** Sam env API_TEST_MYSQL_DSN nie dowodzi izolacji hosta; żadnego testu DB nie uruchomiono.


### `src/Privacy/AbstractPrivacyFeature.php`

**Blob:** `b1c4f6bbc758de8c28cc633a5ac3545f0728aeec` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 35 linii.

- **Ustalenie:** AbstractPrivacyFeature::isEnabled czyta privacy_features; gdy brak wiersza lub dowolny wyjątek, zwraca true, pokazując zakładkę (15–29).
- **Zależności do sprawdzenia:** `src/Privacy/PrivacyFeatureRegistry.php`, `src/Privacy/PrivacySettingsService.php`.
- **Granica dowodu:** Fail-open dotyczy widoczności modułu admina, nie dowodzi dostępu użytkownika bez uprawnienia; HTTP nieuruchomione.


### `src/Privacy/Features/Banner/BannerSettingsFeature.php`

**Blob:** `5dc581db6b5e990d35516a7617d7fb214ca1d5cf` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 47 linii.

- **Ustalenie:** BannerSettingsFeature obsługuje save_banner_settings, zapisuje ustawienia i dopiero potem loguje audit, zwracając success (22–46).
- **Zależności do sprawdzenia:** `src/Privacy/Features/Banner/BannerSettingsService.php`, `src/Privacy/PrivacyAuditLogger.php`.
- **Granica dowodu:** Audyt best effort nie oznacza atomowego śladu operacji.


### `src/Privacy/Features/Banner/BannerSettingsService.php`

**Blob:** `eb8a6dcdd29845fc124c52068f5da2df5f1dcd26` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 46 linii.

- **Ustalenie:** BannerSettingsService zapisuje wybrane klucze string i wszystkie flagi bool w pętli; brak własnej transakcji i limitów długości w tej klasie (21–45).
- **Zależności do sprawdzenia:** `src/Privacy/PrivacySettingsService.php`.
- **Granica dowodu:** Walidację/limity dalszej warstwy trzeba odczytać osobno; nie uruchomiono DB.


### `src/Privacy/Features/Consents/ConsentRepository.php`

**Blob:** `8b66d40e07d131b5a9fc4e1d4d04e61993c86012` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 97 linii.

- **Ustalenie:** ConsentRepository filtruje date/version/source/player_id z bind, pobiera count i listę z JOIN users, na dowolnym wyjątku zwraca pusty wynik (16–97).
- **Ustalenie:** page/perPage nie są clampowane w repo; ujemne lub zerowe parametry mogą skutkować błędem SQL lub dzieleniem przez zero po fetch, który catch zamieni w pusty wynik.
- **Zależności do sprawdzenia:** `src/Privacy/Features/Consents/ConsentService.php`, `src/Privacy/Features/Consents/ConsentsFeature.php`.
- **Granica dowodu:** Rzeczywiste zakresy zależą od callerów; nie wykonywano PHP/SQL.


### `src/Privacy/Features/Consents/ConsentService.php`

**Blob:** `d1b4ecf5a22b0510cdf2a5dc8f9a6857dc5957ad` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 44 linii.

- **Ustalenie:** ConsentService::exportCsv pobiera tylko pierwszych 10000 rekordów i zapisuje wszystkie pola w cytowanych CSV komórkach z escape cudzysłowów (14–44).
- **Ustalenie:** Nie neutralizuje komórek rozpoczynających się od formuł (=,+,-,@), więc przy otwarciu w arkuszu pola pochodzące z danych mogą zostać zinterpretowane jako formuły; eksport >10000 rekordów jest obcięty bez sygnału.
- **Zależności do sprawdzenia:** `src/Privacy/Features/Consents/ConsentRepository.php`, `src/Privacy/Features/Consents/ConsentsFeature.php`.
- **Granica dowodu:** Ryzyko zależy od wartości pól i programu arkusza; nie otwierano pliku w Excel/LibreOffice.


### `src/Privacy/Features/Consents/ConsentsFeature.php`

**Blob:** `7a6f9395727f80428f0d9652acfc1341794bec12` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 75 linii.

- **Ustalenie:** ConsentsFeature obsługuje POST export_csv z datami/version/source, loguje audit i wysyła attachment, a GET pozwala filtrować listę i detal po id (26–75).
- **Zależności do sprawdzenia:** `src/Privacy/Features/Consents/ConsentService.php`, `src/Privacy/PrivacyAuditLogger.php`.
- **Granica dowodu:** Autoryzacja i CSRF są poza tą klasą; wymagają kontroli kontrolera.


### `src/Privacy/Features/Cookies/CookiesFeature.php`

**Blob:** `03bd7e9e38f078cfcedf9fa75d75360a856910f2` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 65 linii.

- **Ustalenie:** CookiesFeature mapuje pięć akcji CRUD/activate na CookieService, view data opiera się na CookieRepository, zwraca dozwolone kategorie i typy (26–65).
- **Zależności do sprawdzenia:** `src/Privacy/Features/Cookies/CookieService.php`, `src/Privacy/Features/Cookies/CookieRepository.php`.
- **Granica dowodu:** Bez pełnego odczytu serwisu nie wnioskować o walidacji POST ani autoryzacji.


### `src/Privacy/Features/Policy/PolicyRepository.php`

**Blob:** `63507f83bbb974dbab3f407eb7f74de8d4ab7f03` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 30 linii.

- **Ustalenie:** PolicyRepository pobiera wszystkie wersje/ID, maskuje błędy jako pustą listę/null (8–30).
- **Zależności do sprawdzenia:** `src/Privacy/Features/Policy/PolicyFeature.php`.
- **Granica dowodu:** Nie potwierdza publikacji/zgodności treści polityki.


### `src/Privacy/PrivacyAuditLogger.php`

**Blob:** `ab1fcc34fbeaaacd8a44d2ccc5aea4a48b951912` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 38 linii.

- **Ustalenie:** PrivacyAuditLogger zapisuje stare/nowe JSON do privacy_audit_logs, ale łapie wyjątki i zwraca void (9–38).
- **Ustalenie:** Wywołujący może zgłosić sukces przy braku rekordu audytu; wymaga decyzji o atomowości lub obowiązkowym error path.
- **Zależności do sprawdzenia:** `src/Privacy/Features/Banner/BannerSettingsFeature.php`, `src/GameLog.php`.
- **Granica dowodu:** Nie uruchomiono SQL ani nie oceniano obowiązków prawnych.


### `src/Privacy/PrivacyFeatureRegistry.php`

**Blob:** `bbd7eab87ddbf24d500e598d2b62276bb12d25be` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 79 linii.

- **Ustalenie:** PrivacyFeatureRegistry tworzy cztery moduły cookies/consents/policy/banner, getEnabled filtruje przez isEnabled; build tworzy współdzielone Settings/Audit (23–79).
- **Zależności do sprawdzenia:** `src/Privacy/AbstractPrivacyFeature.php`, `src/Privacy/PrivacyFeatureInterface.php`.
- **Granica dowodu:** Włączenie modułu nie jest samo w sobie kontrolą uprawnień HTTP.


### `tests/Unit/GameNavIconsTest.php`

**Blob:** `5f54d445f78f0ddfb4c529d14c6f23829536758a` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 37 linii.

- **Ustalenie:** Test tekstowy weryfikuje referencję SVG w nagłówku, 14 osobnych ikon XML i dwa elementy czatu (8–36).
- **Zależności do sprawdzenia:** `templates/header.php`, `templates/components/game_navigation.php`, `templates/views/chat/main.php`.
- **Granica dowodu:** Nie renderuje ikon, XMLReader DOMDocument wymaga PHP extension.


### `tests/Unit/GameNavigationTest.php`

**Blob:** `1f511e313460b2803425a680f60a19a9d6846480` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 30 linii.

- **Ustalenie:** Unit przekazuje uprzednio filtrowane elementy do GameNavigation::build; sprawdza grupy, active, polskie etykiety i icon keys (10–29).
- **Zależności do sprawdzenia:** `src/GameNavigation.php`.
- **Granica dowodu:** Nie testuje samej autoryzacji; caller ma przefiltrować listę.


### `tests/Unit/GameShellCompanyStatusTest.php`

**Blob:** `e78df380a312d5beae0b0074bb10bcd32b04f7a7` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 24 linii.

- **Ustalenie:** Pięć asercji mapuje bankrupt/financial_risk/well_failure/idle/active przy liczbach odwiertów (8–23).
- **Zależności do sprawdzenia:** `src/GameShell.php`.
- **Granica dowodu:** Nie renderuje widoku, PHP nieuruchomione.


### `tests/Unit/GameShellHeadingsTest.php`

**Blob:** `77042fd2edf67c57c6e45e3912a03d1f0c3786dd` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 30 linii.

- **Ustalenie:** Test wymaga kluczy tytułów ośmiu działów PL/EN bez OilCorp/HTML/entities (8–29).
- **Zależności do sprawdzenia:** `lang/pl/finance.php`, `lang/en/finance.php`.
- **Granica dowodu:** Nie obejmuje DE i poprawności tytułów semantycznie.


### `tests/Unit/GermanLocaleCompleteTest.php`

**Blob:** `b53f47b4e54c47f09c9e930ad394fbc6f7eebb44` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 82 linii.

- **Ustalenie:** Sprawdza wszystkie moduły lang/de/*.php z PL: identyczną kolejność kluczy/placeholdery i brak znaków PLN/USD/zł, oraz EUR locale w loaderze (10–81).
- **Zależności do sprawdzenia:** `lang/pl.php`, `lang/de.php`.
- **Granica dowodu:** Brak semantycznej kontroli DE, admin podkatalog nieobjęty globem.


### `tests/Unit/GermanLocaleStageOneTest.php`

**Blob:** `57d3951b3a35f7e19afb93ae7181e0628beafb14` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 50 linii.

- **Ustalenie:** Wcześniejsza wersja kontraktu sprawdza auth/common/nav/notifications/profile DE względem PL i EUR (8–49).
- **Zależności do sprawdzenia:** `lang/pl.php`, `lang/de.php`.
- **Granica dowodu:** Częściowy podzbiór testu ogólnego; PHP nieuruchomione.


### `tests/Unit/PublicHomepageSettingsTest.php`

**Blob:** `4dbfad9c815e5c3bb4a82762ce067981d7054581` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 44 linii.

- **Ustalenie:** SQLite memory testuje domyślnie publiczną stronę, możliwość wyłączenia/włączenia i fallback true gdy brak tabeli (10–43).
- **Zależności do sprawdzenia:** `src/PublicHomepageSettings.php`.
- **Granica dowodu:** Brak żądania anonimowego ani admin authorization.


### `tests/Unit/TechnicalTaskCatalogTest.php`

**Blob:** `ad1beb9af9989ff65ee4d927f226f9eccc62b635` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 18 linii.

- **Ustalenie:** Reflection listy TASKS sprawdza dodatnie minima i max>=min dla każdego task technical (9–17).
- **Zależności do sprawdzenia:** `src/TechnicalTeamService.php`.
- **Granica dowodu:** Nie waliduje cena vs saldo ani efektów zadania.


### `tests/Unit/WellConfigWebBootstrapTest.php`

**Blob:** `dba4ffee6e198cb3884df65bf4230aece4d9fdf5` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 38 linii.

- **Ustalenie:** Dwa testy regex wymagają by wskazane migracje well_config/bankruptcy/transport/training w init były pod PHP_SAPI=cli (8–37).
- **Zależności do sprawdzenia:** `src/init.php`.
- **Granica dowodu:** Tylko tekst; inne konstruktory i endpointy mogą nadal wykonywać DDL.


### `tests/Unit/WellHistoryTest.php`

**Blob:** `e031001e5a91c0065cc61c72165b790af88359d8` · **Status:** `pełny odczyt statyczny; plik zgodny z HEAD` · **Odczyt:** 69 linii.

- **Ustalenie:** SQLite memory testuje owner scope, paginację 25 eventów i 48h cutoff również przy zmianie timezone PHP (6–68).
- **Zależności do sprawdzenia:** `src/WellHistoryService.php`.
- **Granica dowodu:** Nie mierzy wydajności MySQL i retencji zapisów, PHP nieuruchomione.


### `src/Privacy/Features/Cookies/CookieRepository.php`

**Blob:** `2a3d5b86b3189a468105ebd5d3510682f151ee30` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 130 linii.

- **Ustalenie:** Repozytorium definicji cookies parametryzuje filtry, ale odczyty zwracają []/null po dowolnym wyjątku, create 0, update/delete bool.
- **Zależności do sprawdzenia:** `src/Privacy/Features/Cookies/CookieService.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; asercje testów to analiza kodu, nie wyniki wykonania.


### `src/Privacy/Features/Cookies/CookieService.php`

**Blob:** `bf38054d50a77b647c8a04818b08adfb7583f605` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 107 linii.

- **Ustalenie:** P1 statyczne: create ignoruje ID=0 i zgłasza sukces; update/toggle/delete ignorują false z repo, audytują operację i zgłaszają sukces bez potwierdzonego zapisu.
- **Zależności do sprawdzenia:** `src/Privacy/Features/Cookies/CookieRepository.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; asercje testów to analiza kodu, nie wyniki wykonania.


### `src/Privacy/Features/Policy/PolicyFeature.php`

**Blob:** `9b571dd9ec7e192e3b813a7f244b24d6e38e9bc4` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 101 linii.

- **Ustalenie:** Dispatch create/update/activate polityk waliduje część pól i respektuje wynik serwisu; audyt nie jest warunkiem odpowiedzi sukcesu.
- **Zależności do sprawdzenia:** `src/Privacy/PrivacyPolicyService.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; asercje testów to analiza kodu, nie wyniki wykonania.


### `src/Privacy/PrivacyBannerService.php`

**Blob:** `70ada68756c0952bccd3f4b7985896199bd7a69e` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 101 linii.

- **Ustalenie:** Treść banera: dla locale nie-pl i wybranych starych tekstów PL zastępuje ustawienia tłumaczeniem; kategorie wynikają z aktywnych definicji cookies, błąd SQL daje pustą listę.
- **Zależności do sprawdzenia:** `src/Privacy/PrivacySettingsService.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; asercje testów to analiza kodu, nie wyniki wykonania.


### `src/Privacy/PrivacyConsentService.php`

**Blob:** `1f8f3d292d53e1c8ebfb3b7a544075cc15809296` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 173 linii.

- **Ustalenie:** Zgoda: pobiera najnowszą aktywną po graczu/tokenie; błąd SQL ukrywa jako null, co ponownie pokazuje baner i odmawia niekoniecznych kategorii. save/withdraw łapią błędy bez propagacji; getOrCreate token wymaga sesji.
- **Zależności do sprawdzenia:** `src/Privacy/PrivacySettingsService.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; asercje testów to analiza kodu, nie wyniki wykonania.


### `src/Privacy/PrivacyFeatureInterface.php`

**Blob:** `c698032e47243dab2993aa8fe6be4382e3e6b1ea` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 41 linii.

- **Ustalenie:** Interfejs feature definiuje tab, aktywność, POST, dane i ścieżkę widoku; sam nie narzuca auth ani transakcji.
- **Zależności do sprawdzenia:** `src/Privacy/AbstractPrivacyFeature.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; asercje testów to analiza kodu, nie wyniki wykonania.


### `src/Privacy/PrivacyPolicyService.php`

**Blob:** `efb97d7266518aa97cbb42ef975ceeeb26f42923` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 111 linii.

- **Ustalenie:** P1 warunkowe: activate zawsze beginTransaction po SELECT, a catch rollbackuje aktywną transakcję PDO także przy błędzie rozpoczęcia przez callerową transakcję. Brak weryfikacji zapisem runtime.
- **Zależności do sprawdzenia:** `src/Privacy/Features/Policy/PolicyFeature.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; asercje testów to analiza kodu, nie wyniki wykonania.


### `src/Privacy/PrivacySettingsService.php`

**Blob:** `11c94a9fd65899333e398d3093b9b5983d74a135` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 95 linii.

- **Ustalenie:** set() ukrywa błąd zapisu, zwraca void i unieważnia cache; caller może ogłosić sukces bez trwałego zapisu. get() cachuje istniejące wartości, ale nie brakujące; brak odświeżania przy zewnętrznej zmianie.
- **Zależności do sprawdzenia:** `src/Privacy/Features/Banner/BannerSettingsService.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; asercje testów to analiza kodu, nie wyniki wykonania.


### `src/WellNamingSchema.php`

**Blob:** `324e29b64791f5ab41005d3a62bc8175c3f192c0` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 23 linii.

- **Ustalenie:** Tworzy well_name_counters przez CREATE TABLE IF NOT EXISTS dla MySQL/SQLite; wywołanie w aktywnej transakcji MySQL może niejawnie zatwierdzić ją przez DDL.
- **Zależności do sprawdzenia:** `src/WellNamingMigrationService.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; asercje testów to analiza kodu, nie wyniki wykonania.


### `src/WorldMapSchema.php`

**Blob:** `db0ee271cc18152da48a4fd79507ac3948f89f85` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 35 linii.

- **Ustalenie:** Bootstrap indeksu MySQL ma guard transakcji i sprawdza nazwy kolumn indeksu; DDL ALTER TABLE to efekt uboczny ensure, SQLite no-op.
- **Zależności do sprawdzenia:** `src/WorldMap.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; asercje testów to analiza kodu, nie wyniki wykonania.


### `tests/Integration/WorldLocationCatalogSeederTest.php`

**Blob:** `a7b363af7572333e4f93a7dbfc463b3d80d4f7e8` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 70 linii.

- **Ustalenie:** Test SQLite: 140 lokalizacji, idempotencja, zachowanie wyłączonej i rollback po brakującym regionie.
- **Zależności do sprawdzenia:** `src/WorldLocationCatalogSeeder.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; asercje testów to analiza kodu, nie wyniki wykonania.


### `tests/MySqlIntegration/MySqlAuthRegistrationFlowTest.php`

**Blob:** `953b2456f2aac501cbcac0dbd2315c4299f91750` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 103 linii.

- **Ustalenie:** Test MySQL rejestracji legacy i oczekującej: sesja, saldo 5 mln, bank 5 mln i magazyn; tearDown kasuje rekordy po e-mailu. Nie uruchamiano.
- **Zależności do sprawdzenia:** `src/Auth.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; asercje testów to analiza kodu, nie wyniki wykonania.


### `tests/MySqlIntegration/MySqlSabotageServiceLockTest.php`

**Blob:** `ab6fe8171944036bd066fd5e4524d9495300b8e0` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 130 linii.

- **Ustalenie:** Test dwóch połączeń MySQL wymusza GET_LOCK i asercje braku opłaty/logu po busy; wykonuje DELETE w cleanup. Nie uruchamiano.
- **Zależności do sprawdzenia:** `src/SabotageService.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; asercje testów to analiza kodu, nie wyniki wykonania.


### `tests/MySqlIntegration/MySqlTtsAtomicityTest.php`

**Blob:** `120240e119bd7e698e936c122af865b1533a6271` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 142 linii.

- **Ustalenie:** Test MySQL izolacji blokad, starszego snapshotu i dwóch procesów TTS; jawnie COMMIT/ROLLBACK, DELETE i proc_open, bez wykonania.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/tts_start_worker.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; asercje testów to analiza kodu, nie wyniki wykonania.


### `tests/MySqlIntegration/WorldLocationCatalogSeederMySqlTest.php`

**Blob:** `29cc655383b5bc26e8e1d009518f0c1391c9dd75` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 38 linii.

- **Ustalenie:** Test MySQL uruchamia seeder w transakcji i sprawdza limit +140/idempotencję, po czym rollback; wspólny setup nadal ma efekty uboczne.
- **Zależności do sprawdzenia:** `src/WorldLocationCatalogSeeder.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; asercje testów to analiza kodu, nie wyniki wykonania.


### `tests/Unit/BaseTestCase.php`

**Blob:** `817c16c959cb4c90a825061cdaff3be62871c6f1` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 24 linii.

- **Ustalenie:** Baza testów jednostkowych uruchamia sesję i czyści $_SESSION przed/po; nie resetuje innych stanów globalnych.
- **Zależności do sprawdzenia:** `tests/bootstrap.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; asercje testów to analiza kodu, nie wyniki wykonania.


### `tests/Unit/PlayersSectionLimitTest.php`

**Blob:** `e29fec168e24562dbcde80dea63c66bab20f92f9` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 140 linii.

- **Ustalenie:** Test SQLite oczekuje sortowania najstarszego ticka, limitu min. 1 i pominięcia graczy bez storage bez aktualizacji czasu.
- **Zależności do sprawdzenia:** `src/Tick/PlayersSection.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; asercje testów to analiza kodu, nie wyniki wykonania.


### `tests/Unit/WellNamingTest.php`

**Blob:** `3b798fa778c28563fae2f3a1531578ca451d1e53` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 101 linii.

- **Ustalenie:** Test SQLite numeracji odwiertów i migracji; oddzielny test sprawdza tekst kontrolera/widoku/locale, nie autoryzację HTTP.
- **Zależności do sprawdzenia:** `src/WellNamingMigrationService.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; asercje testów to analiza kodu, nie wyniki wykonania.


### `tests/Unit/WellWorkspaceTest.php`

**Blob:** `a912c0239beeaa7fb7130cbcad2150a8397bc051` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 148 linii.

- **Ustalenie:** Test UI w większości asercji substringów PHP/JS/CSS/SVG; sprawdza też katalog cen oraz mapowanie problemów; nie uruchamia przeglądarki.
- **Zależności do sprawdzenia:** `src/WellWorkspaceData.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; asercje testów to analiza kodu, nie wyniki wykonania.


### `tests/bootstrap.php`

**Blob:** `c8420e47613cc799990b6e277fdead8c558c5589` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 17 linii.

- **Ustalenie:** Bootstrap ładuje vendor, log/i18n i klasę MySqlIntegrationTestCase, wyłącza GameLog, otwiera sesję; APP_ENV ustawiany tylko gdy brak. Import klasy nie oznacza połączenia z DB.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlIntegrationTestCase.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; asercje testów to analiza kodu, nie wyniki wykonania.


### `src/Well/ConfigTrait.php`

**Blob:** `fe7ddb82ab1cbf25651655c4ddcb3b8b393cfe59` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 83 linii.

- **Ustalenie:** Katalog cen/mnożników; updateConfig zapisuje DB i zwraca bool, ale nie aktualizuje lokalnej tablicy config, więc cfg() w tym samym obiekcie może zwrócić starą wartość.
- **Zależności do sprawdzenia:** `src/WellService.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; testy i ryzyka ocenione statycznie.


### `src/Well/CostsTrait.php`

**Blob:** `8378ce5341d5ab49f782ea9045a6ebeeb277439b` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 168 linii.

- **Ustalenie:** Koszty i efektywna produkcja stosują mnożniki regionu, wyczerpania, sprzętu i trybu; getEffectiveProduction łapie Throwable i zwraca 0 przy błędzie.
- **Zależności do sprawdzenia:** `src/Well/ConfigTrait.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; testy i ryzyka ocenione statycznie.


### `src/Well/QueryTrait.php`

**Blob:** `2384ee222b049522cd11107ca5787bea44b97e3f` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 101 linii.

- **Ustalenie:** Zapytania odwiertów filtrują player_id, zdarzenia limit 1..200; wyjątek SQL ukrywa jako null/[], a getInstalledUpgrades przy playerId=0 omija kontrolę właściciela (metoda private).
- **Zależności do sprawdzenia:** `src/WellService.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; testy i ryzyka ocenione statycznie.


### `src/WellEventRetentionService.php`

**Blob:** `fb34bf5a3c1616ce67c3cc9340c95e055bab7545` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 34 linii.

- **Ustalenie:** Retencja kasuje wiersze well_events po 500 do wyczerpania; nie używa transakcji całego przebiegu, postęp częściowy przy błędzie. Nie uruchamiano kasowania.
- **Zależności do sprawdzenia:** `src/WellHistoryService.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; testy i ryzyka ocenione statycznie.


### `src/WellHistoryService.php`

**Blob:** `0709d350d0d3e0910f0c1496256c7730c41b4f34` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 74 linii.

- **Ustalenie:** Paginacja po własności wells i e.player_id, filtr 48h, 20 na stronę; osobny COUNT i SELECT mogą dać różne wyniki przy równoległym zapisie.
- **Zależności do sprawdzenia:** `src/WellEventRetentionService.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; testy i ryzyka ocenione statycznie.


### `src/WellNaming.php`

**Blob:** `9dcadb0953105994e2c65cf88b4033c52daa314b` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 48 linii.

- **Ustalenie:** Nazwy prefiks+licznik per gracz/region; alokacja wymaga transakcji, SELECT FOR UPDATE tylko gdy rekord już istnieje; pierwszy INSERT może ścigać się z innym i zderzyć o PK, jeśli caller nie blokuje gracza.
- **Zależności do sprawdzenia:** `src/WellNamingSchema.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; testy i ryzyka ocenione statycznie.


### `src/WellNamingMigrationService.php`

**Blob:** `6945694a9a560252c9cd5874c6e141055843cdec` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 122 linii.

- **Ustalenie:** Migracja preview bez zapisu, apply blokuje players/wells MySQL, renumeruje i aktualizuje counter; beginTransaction bez guardu przy aktywnej transakcji; deploy uruchamia DDL przed preview.
- **Zależności do sprawdzenia:** `src/WellNamingSchema.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; testy i ryzyka ocenione statycznie.


### `src/WellSellApi.php`

**Blob:** `5e837ec08b41795d0d620e83806cac2be459b429` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 74 linii.

- **Ustalenie:** Endpoint JSON GET wycena, POST sprzedaż z CSRF i login; także GET wywołuje ciche addColumnIfMissing sold_at, potencjalny DDL w żądaniu odczytu.
- **Zależności do sprawdzenia:** `src/WellService.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; testy i ryzyka ocenione statycznie.


### `src/WellWorkspaceData.php`

**Blob:** `ffbfb75b6530fbfddce12b07d847496975a26d39` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 120 linii.

- **Ustalenie:** Dane widoku agregują odwierty i mapują status/stan na działanie z katalogu TechnicalTeamService; prepare ładuje konfigurację transportu i kalkuluje procent zapełnienia.
- **Zależności do sprawdzenia:** `tests/Unit/WellWorkspaceTest.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; testy i ryzyka ocenione statycznie.


### `src/WorldLocationCatalogSeeder.php`

**Blob:** `bac7ef7300dc4207f9b83f2cca6e7c8a6b9413f7` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 223 linii.

- **Ustalenie:** Seeder 140 katalogowych lokalizacji z markerem, transakcją i named lock MySQL; przy transakcji callera zwalnia named lock przed jego COMMIT, co warunkowo otwiera wyścig drugiego seedera po nieutrwalonym markerze.
- **Zależności do sprawdzenia:** `tests/Integration/WorldLocationCatalogSeederTest.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; testy i ryzyka ocenione statycznie.


### `tests/Integration/BriberyServiceTest.php`

**Blob:** `af293f3412ef260175ed073a1827def897840dc4` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 233 linii.

- **Ustalenie:** Test SQLite kosztu, sukcesu/wpadki, braku środków i wyłączonego modułu łapówek; callbacks i zapisy sprawdzane na lokalnym schemacie.
- **Zależności do sprawdzenia:** `src/BriberyService.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; testy i ryzyka ocenione statycznie.


### `tests/MySqlIntegration/IncidentCooldownMySqlTest.php`

**Blob:** `a1fd8cd2679025c30b57b3729db016d3ee2a6f2a` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 193 linii.

- **Ustalenie:** Własny DSN API_TEST_MYSQL_DSN, temporary tables i testy cooldownu, zegara, izolacji, błędu historii i DDL; wymaga odrębnej izolowanej bazy, nie uruchamiano.
- **Zależności do sprawdzenia:** `src/IncidentService.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; testy i ryzyka ocenione statycznie.


### `tests/MySqlIntegration/MySqlFinancialStateCashSaveTest.php`

**Blob:** `d2cb82104bbb1eb704ca076102d5cad49d23b85d` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 212 linii.

- **Ustalenie:** Koszty pobierane ze stanu gotówki w DB i aktualizowany last_tick_at; „concurrent” symuluje kolejny UPDATE na tym samym PDO przed zapisem, nie faktyczny wyścig.
- **Zależności do sprawdzenia:** `src/Tick/FinancialStateSection.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; testy i ryzyka ocenione statycznie.


### `tests/MySqlIntegration/MySqlOfflineSectionTest.php`

**Blob:** `45acf9a4d301f8295e22858022357f84b1e3261f` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 226 linii.

- **Ustalenie:** Test offline sprawdza offline_frozen, raport was_frozen i reset po powrocie; konstruktor może wykonywać DDL ALTER TABLE na bazie.
- **Zależności do sprawdzenia:** `src/Tick/OfflineSection.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; testy i ryzyka ocenione statycznie.


### `tests/MySqlIntegration/MySqlTechnicalConcurrentTickTest.php`

**Blob:** `f430c552b2e8f92444ddb3670738a5223db249a1` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 155 linii.

- **Ustalenie:** Dwie kolejne completeTask na tym samym zadaniu i wariant już completed; mimo nazwy test nie uruchamia dwóch równoległych wykonawców.
- **Zależności do sprawdzenia:** `src/TechnicalTeamService.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; testy i ryzyka ocenione statycznie.


### `tests/MySqlIntegration/MySqlTickStatsRepositoryTest.php`

**Blob:** `d7cc6817a5351518f26b3e943dfcf3c51ff88717` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 298 linii.

- **Ustalenie:** MySQL testuje indeks UNIQUE i deduplikację pary ran_at, tick_sequence; tearDown kasuje wszystkie rekordy tick_stats z datą 2099-12-31 bez ograniczenia do własnych ID. Nie uruchamiano.
- **Zależności do sprawdzenia:** `src/Tick/TickStatsRepository.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; testy i ryzyka ocenione statycznie.


### `tests/MySqlIntegration/MySqlWellDisastersTraitTest.php`

**Blob:** `b9f166ee558806a3ab8063b6315089c55c415e7a` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 250 linii.

- **Ustalenie:** Test katastrof sprawdza końcowe status i boost, cap, logi; nie wymusza rollback po błędzie ani nie obserwuje stanu przed COMMIT, więc sam nie dowodzi atomowości.
- **Zależności do sprawdzenia:** `src/Well/DisastersTrait.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; testy i ryzyka ocenione statycznie.


### `tests/MySqlIntegration/MySqlWellRoadTripSectionTest.php`

**Blob:** `cda0ac1bbadb69f94955233ee763203cefb10dfb` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 279 linii.

- **Ustalenie:** Test przewozów opisuje nieistniejącą kolumnę updated_at i cicho złapany wyjątek skalowania; asercja zachowania delivered_bbl może przejść dzięki pominięciu operacji, nie dzięki prawidłowemu scopingowi.
- **Zależności do sprawdzenia:** `src/Tick/WellRoadTripSection.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; testy i ryzyka ocenione statycznie.


### `tests/MySqlIntegration/MySqlWellSellTraitTest.php`

**Blob:** `39ec7431bd69f1e592c524d1e99a6f1907a68d38` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 216 linii.

- **Ustalenie:** Test sprzedaży używa współdzielonej konfiguracji DB i sprawdza kalkulację, active/sold, broken/blowout i własność; teardown DELETE.
- **Zależności do sprawdzenia:** `src/Well/SellTrait.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; testy i ryzyka ocenione statycznie.


### `tests/Unit/OutboundLegServiceTest.php`

**Blob:** `095706cb2b60b8caa2102b0200dfac78febeb6c9` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 154 linii.

- **Ustalenie:** Test czystej kalkulacji transportu obejmuje brak transportu, blokadę rurociągu, OPEX, straty drogowe i zero baryłek.
- **Zależności do sprawdzenia:** `src/OutboundLegService.php`.
- **Granica dowodu:** Nie wykonano PHP/PHPUnit/MySQL/HTTP; testy i ryzyka ocenione statycznie.


### `src/Tick/WellProductionSection.php`

**Blob:** `bdfd5800d95001b0bd81e6c99c385cf9ded55783` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 221 linii.

- **Ustalenie:** Orkiestrator status→degradacja/ryzyko→katastrofa/incydent→OPEX→produkcja; servicing/sold-like statusy pomijane zgodnie z rozgałęzieniem.
- **Zależności do sprawdzenia:** `src/Tick/WellStatusHandler.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; skrypty diagnostyczne i testy są opisane statycznie.


### `src/Tick/WellRiskHandler.php`

**Blob:** `f1f9e9cf69c15bd5cca008b133dcb53b3ea954b9` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 243 linii.

- **Ustalenie:** Naliczanie kosztów awarii/katastrof/incydentów do totalCosts i playerCash w pamięci ticka; persystencja salda następuje w dalszym etapie, nie w tej metodzie.
- **Zależności do sprawdzenia:** `src/Tick/WellProductionSection.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; skrypty diagnostyczne i testy są opisane statycznie.


### `src/Tick/WellRoadTripSection.php`

**Blob:** `a377b14eb5a4c91f5862e1c7d2e2b0057bd486d4` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 189 linii.

- **Ustalenie:** Dostawy hubowe bez capa magazynu, bez-hubowe ze skalowaniem SELECT crediting z wykluczeniem orphan IDs; starszy komentarz testu o updated_at jest nieaktualny: źródło nie używa tej kolumny.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlWellRoadTripSectionTest.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; skrypty diagnostyczne i testy są opisane statycznie.


### `src/Tick/WellStatusHandler.php`

**Blob:** `b8356c3456d8a1e32c48a031196521cc996eaac4` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 331 linii.

- **Ustalenie:** Obsługa końca equipment_swap, pause/resume personelu i perki; wyjątek DB w validateStaff loguje i zachowuje staffId zamiast przerwać tick.
- **Zależności do sprawdzenia:** `src/Tick/WellProductionSection.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; skrypty diagnostyczne i testy są opisane statycznie.


### `src/Well.php`

**Blob:** `0714ec69c14277059e8e612b5c59b86fd2c248b0` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 184 linii.

- **Ustalenie:** Legacy Well.upgrade blokuje sold/seized i max level, używa transakcji i Player.updateCash; na wspólnym PDO beginTransaction może wejść w konflikt z transakcją callera.
- **Zależności do sprawdzenia:** `src/Player.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; skrypty diagnostyczne i testy są opisane statycznie.


### `src/Well/ActionsTrait.php`

**Blob:** `86d0a7c12a841131c13d251889610ea924cb7c86` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 249 linii.

- **Ustalenie:** P1 warunkowe: upgrade_level i performMaintenance wywołują rollback bez sprawdzenia ownTx przy brakującym odwiercie/nieprawidłowym statusie, mogą cofnąć transakcję wywołującego; set_tier stosuje guard.
- **Zależności do sprawdzenia:** `src/WellService.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; skrypty diagnostyczne i testy są opisane statycznie.


### `src/Well/SellTrait.php`

**Blob:** `4e9ec123b8769e4dfe3fe6d21268b88c47e77a36` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 242 linii.

- **Ustalenie:** Wycena przed transakcją sprawdza status, przypisania i cooldown; sellWell w transakcji blokuje odwiert i status active, ale nie ponawia kontroli przypisań, więc równoległy przydział może unieważnić warunek sprzedaży.
- **Zależności do sprawdzenia:** `src/WellService.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; skrypty diagnostyczne i testy są opisane statycznie.


### `src/WellGridData.php`

**Blob:** `b6a414605015332a4f775f20fb8f28e10a7570fe` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 361 linii.

- **Ustalenie:** Przygotowanie widoku preładuje część relacji, ale prepareWell per odwiert tworzy WellPipelineService i odpytuje profile, a także GeologicalLayerService.getActiveLayer; potencjalne N+1 i koszty inicjalizacji.
- **Zależności do sprawdzenia:** `src/WellPipelineService.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; skrypty diagnostyczne i testy są opisane statycznie.


### `src/WellService.php`

**Blob:** `1ec9ca1a93dbee76f1a56c6ba79b88c96ada1fbb` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 187 linii.

- **Ustalenie:** Konstruktor WellService ładuje config i wykonuje ensureSchema z możliwymi ALTER TABLE oraz UPDATE; cache per PDO ustawiony przed dalszą walidacją, a wcześniejsze return dla statusu nie-ENUM pomija późniejsze kolumny.
- **Zależności do sprawdzenia:** `src/IncidentService.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; skrypty diagnostyczne i testy są opisane statycznie.


### `src/WellShop.php`

**Blob:** `b3e59d5388c2f7872a0e975b0193e0141f7ec9e2` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 188 linii.

- **Ustalenie:** Zakup z blokadą gracza i sprawdzeniem limitu w transakcji, alokuje nazwę i odejmuje saldo; getWell przed transakcją sprawdza ofertę, ale brak drugiego sprawdzenia available po locku.
- **Zależności do sprawdzenia:** `src/WellNaming.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; skrypty diagnostyczne i testy są opisane statycznie.


### `tests/MySqlIntegration/tts_start_worker.php`

**Blob:** `c65db95009fac3983abaa7b292111855a68732ef` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 27 linii.

- **Ustalenie:** Worker testowy otwiera DB z config/database.php, czeka READY/GO, w transakcji przypisuje zadanie i commit; nie uruchamiać bez izolacji.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlTtsAtomicityTest.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; skrypty diagnostyczne i testy są opisane statycznie.


### `tests/MySqlIntegration/tts_test_service.php`

**Blob:** `e4af91897f880c35fd28258dc19b63d9c453f60f` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 14 linii.

- **Ustalenie:** Pomocnik testu tworzy anonimowy subclass TTS i wstrzykuje PDO/playerId przez reflection, omijając konstruktor i menedżera. Nie jest samodzielnym testem.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlTtsAtomicityTest.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; skrypty diagnostyczne i testy są opisane statycznie.


### `tests/check_trends.php`

**Blob:** `19ef84315fa7d848542b597b896862274feeee77` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 89 linii.

- **Ustalenie:** Strona diagnostyczna z display_errors, nieescapowanymi wartościami trendów i GET ?activate uruchamiającym zmianę trendu bez auth/CSRF w tym pliku; ekspozycja HTTP niepotwierdzona.
- **Zależności do sprawdzenia:** `src/MarketTrend.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; skrypty diagnostyczne i testy są opisane statycznie.


### `tests/debug_calculation.php`

**Blob:** `4026c6b759dcb453357e88abcce11965a4e4e872` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 63 linii.

- **Ustalenie:** Ręczny debug rynku: UPDATE market_state i updatePrices, porównanie losowego obliczenia z kolejnym losowym rzutem nie jest deterministyczną asercją. Nie uruchamiano.
- **Zależności do sprawdzenia:** `src/MarketTick.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; skrypty diagnostyczne i testy są opisane statycznie.


### `tests/exact_debug.php`

**Blob:** `7de08e3cefd4789c6b84d3f519e08ef9d29c4a1e` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 73 linii.

- **Ustalenie:** Ręczny debug ceny zmienia stan rynku i wywołuje tick, używa rand osobno względem realnego ticka; różnica nie dowodzi błędu. Nie uruchamiano.
- **Zależności do sprawdzenia:** `src/MarketTick.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; skrypty diagnostyczne i testy są opisane statycznie.


### `tests/price_test.php`

**Blob:** `44d06dcb33194cf7eb8228f3ad0049da1ea1e432` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 61 linii.

- **Ustalenie:** Ręczny skrypt diagnostyczny bez asercji: UPDATE market_state id=1 na 54, dwukrotny updatePrices/tick; bez resetu wartości początkowej i izolacji. Nie uruchamiać.
- **Zależności do sprawdzenia:** `src/MarketTick.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; skrypty diagnostyczne i testy są opisane statycznie.


### `tests/test_cache.php`

**Blob:** `7130070060348b3842ce88a1144c9f100ea76854` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 42 linii.

- **Ustalenie:** Skrypt ręczny Cache set/get/delete/TTL/flush; nie zwraca niezerowego kodu przy fail, flush może dotknąć współdzielonego cache. Nie uruchamiano.
- **Zależności do sprawdzenia:** `src/Cache.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; skrypty diagnostyczne i testy są opisane statycznie.


### `tests/test_html_standards.php`

**Blob:** `7ee6aa8c3d69d3ed825e100393d76bbc4fd8a6ae` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 58 linii.

- **Ustalenie:** Skrypt standardów HTML obejmuje tylko HR/admin HR, heurystycznie wykrywa style i zwraca kod 1 przy błędzie; nie jest walidatorem HTML.
- **Zależności do sprawdzenia:** `templates/views/hr/main.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; skrypty diagnostyczne i testy są opisane statycznie.


### `tests/test_price_detailed.php`

**Blob:** `fe60dff930371086764729791d3cdd0a7706723c` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 61 linii.

- **Ustalenie:** Duplikat skryptu ceny z inną ścieżką require; zmienia market_state i uruchamia tick, nie test autonomiczny. Nie uruchamiano.
- **Zależności do sprawdzenia:** `src/MarketTick.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; skrypty diagnostyczne i testy są opisane statycznie.


### `tests/test_separation.php`

**Blob:** `bf77e4bfe520d08ba066cd2a72c8698f38a514bf` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 50 linii.

- **Ustalenie:** Skrypt grep-heurystyka separacji widoków z glob **/*.php i sprawdzaniem wzorców, ale przy błędach tylko echo, bez exit(1); nie dowodzi sukcesu testów.
- **Zależności do sprawdzenia:** `templates/views/index/main.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; skrypty diagnostyczne i testy są opisane statycznie.


### `src/ApiErrorHandler.php`

**Blob:** `38946d7802fcc7c6e3a315593e5c8feeee06b849` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 86 linii.

- **Ustalenie:** Handler API lokalizuje komunikaty, loguje typ/lokalizację wyjątku bez treści, instaluje globalne exception/error/shutdown handlers; log() przyjmuje arbitralny context od callera.
- **Zależności do sprawdzenia:** `src/TechNotifApi.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP/przeglądarki; opis testów to nie rezultat ich uruchomienia.


### `src/GameNavigation.php`

**Blob:** `69b9c1c1f04db59b01eb58de288e021ad1f8b6b7` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 94 linii.

- **Ustalenie:** Nawigacja filtruje po grupach i deleguje href do PlayerMenuEditor; aktywne linki wylicza dla lokalnych ścieżek, zewnętrzne mogą otworzyć nową kartę.
- **Zależności do sprawdzenia:** `src/PlayerNavigationSettings.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP/przeglądarki; opis testów to nie rezultat ich uruchomienia.


### `src/MobileWebBridge.php`

**Blob:** `304faa93a0e85d327b4ac04bb2f7924762a380ba` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 144 linii.

- **Ustalenie:** Bridge mobilny: 60s token z hashem SHA-256 i warunkowy UPDATE used_at po SELECT; ensureSchema oznacza PDO jako wykonane przed CREATE, więc błąd bootstrapu może zablokować retry w tym procesie.
- **Zależności do sprawdzenia:** `src/Database.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP/przeglądarki; opis testów to nie rezultat ich uruchomienia.


### `src/PlayerNavIcon.php`

**Blob:** `34f8f3eb6ab5bac9764d305fec1d70cf47824c62` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 101 linii.

- **Ustalenie:** Ikony: walidacja nazwy/pliku, whitelist MIME i rzeczywistego obrazu, GD re-encode do 40x40 PNG; fallback do dashboard.
- **Zależności do sprawdzenia:** `tests/Unit/PlayerNavIconTest.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP/przeglądarki; opis testów to nie rezultat ich uruchomienia.


### `src/PlayerNavigationSettings.php`

**Blob:** `5215d299050fa60305d56118a80525b8386be640` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 146 linii.

- **Ustalenie:** Destinations parsuje .htaccess i istniejące pliki, ensureSchema może wykonywać CREATE/ALTER w runtime oraz seeding grup/specjalnych pozycji; migracja powinna oddzielić DDL od requestu.
- **Zależności do sprawdzenia:** `src/GameNavigation.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP/przeglądarki; opis testów to nie rezultat ich uruchomienia.


### `src/PublicHomepageSettings.php`

**Blob:** `2ca215e6dd7c29e2cc859bd83068dfac4e2780a4` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 34 linii.

- **Ustalenie:** Flaga publicznej strony startowej domyślnie true przy braku rekordu lub awarii odczytu, normalizuje 0/false/off/no do false.
- **Zależności do sprawdzenia:** `tests/Unit/PublicHomepageSettingsTest.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP/przeglądarki; opis testów to nie rezultat ich uruchomienia.


### `src/TTS/NotificationsTrait.php`

**Blob:** `67d6c7e0d527302b60fb13a4d677a5e6c2099da9` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 65 linii.

- **Ustalenie:** Powiadomienia TTS deduplikowane przez SELECT type/well_id/10 min przed INSERT; bez unikalnego indeksu/locku dwa równoległe wywołania mogą wstawić duplikaty.
- **Zależności do sprawdzenia:** `src/TechNotifApi.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP/przeglądarki; opis testów to nie rezultat ich uruchomienia.


### `src/TechNotifApi.php`

**Blob:** `1c6742eda74411e6f329413c5846e10b9d5431de` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 46 linii.

- **Ustalenie:** Endpoint mark_read / mark_all_read: login, POST/CSRF i WHERE player_id; nie weryfikuje rowCount, więc ID nieistniejącego powiadomienia zwróci success=true.
- **Zależności do sprawdzenia:** `src/TTS/NotificationsTrait.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP/przeglądarki; opis testów to nie rezultat ich uruchomienia.


### `src/TechnicalPage/RecruitmentViewTrait.php`

**Blob:** `f2cf8f3e8369726a0dcd28226325730ddbc5e3fc` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 68 linii.

- **Ustalenie:** UI rekrutacji liczy zapotrzebowanie ze stałych ratio per spec i osobno wywołuje getRecruitmentCapacityForSpec dla każdego katalogowego specjalisty.
- **Zależności do sprawdzenia:** `src/TechnicalPageController.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP/przeglądarki; opis testów to nie rezultat ich uruchomienia.


### `src/TechnicalPageController.php`

**Blob:** `1cab2e21f5b3ed44a969d2a20e3cc23cfc3eea68` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 60 linii.

- **Ustalenie:** Konstruktor strony technicznej inicjalizuje TTS i WellService (możliwy bootstrap DB), IncidentService/HubIncidentService są opcjonalne po błędach.
- **Zależności do sprawdzenia:** `src/WellService.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP/przeglądarki; opis testów to nie rezultat ich uruchomienia.


### `tests/Unit/PlayerMenuEditorTest.php`

**Blob:** `cb8d5f4c62b8781f79f3d704431618bc9fe357f4` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 182 linii.

- **Ustalenie:** Test SQLite menu: walidacja URL, wersjonowanie, atomowość batch, warianty języków i obrazów; kontrola bramek kontrolera jest substringiem, nie żądaniem HTTP.
- **Zależności do sprawdzenia:** `src/PlayerMenuEditor.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP/przeglądarki; opis testów to nie rezultat ich uruchomienia.


### `tests/Unit/PlayerNavIconTest.php`

**Blob:** `4a826aca2037729b1d81caa8570dd3bcc0aebdb6` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 187 linii.

- **Ustalenie:** Test ikon/nawigacji, częściowo SQLite i pliki GD; ensureUrlHelper może załadować src/init.php, więc to niekoniecznie odizolowany test jednostkowy.
- **Zależności do sprawdzenia:** `src/PlayerNavIcon.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP/przeglądarki; opis testów to nie rezultat ich uruchomienia.


### `tests/Unit/ProfileRedesignTest.php`

**Blob:** `d6b4a92195fee7f0fc55acdc010eab123d8625e9` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 259 linii.

- **Ustalenie:** Test kontraktów profilu w większości grep na źródłach CSS/JS/PHP, nie rzeczywisty upload/CSRF; opcjonalne porównanie SVG z absolutną ścieżką na maszynie autora jest środowiskowe.
- **Zależności do sprawdzenia:** `public/profile.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP/przeglądarki; opis testów to nie rezultat ich uruchomienia.


### `tests/api_smoke.php`

**Blob:** `cf79f048abc03625a7d78f12ab1f0f9de4b3bb8b` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 140 linii.

- **Ustalenie:** Smoke API seeduje gracza o stałym ID, przed testem usuwa jego wiersze, ensureSchema tworzy tabelę; brak finally dla cleanup przy błędzie. Nie uruchamiać na nieizolowanej bazie.
- **Zależności do sprawdzenia:** `src/ApiAuth.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP/przeglądarki; opis testów to nie rezultat ich uruchomienia.


### `tests/fixtures/profile-view.php`

**Blob:** `a68082b0397180159467779ad7b1fc5d2314bee4` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 21 linii.

- **Ustalenie:** Fixture widoku profilu z danymi przykładowymi i helperami tłumaczenia; nie weryfikuje kontrolera ani DB, brak strażnika CLI.
- **Zależności do sprawdzenia:** `templates/views/profile/main.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP/przeglądarki; opis testów to nie rezultat ich uruchomienia.


### `tests/fixtures/public_home_render.php`

**Blob:** `e2f146c44a0b5d12d224895fc0877e7de0e19b2d` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 50 linii.

- **Ustalenie:** Fixture renderu publicznego home dla pl/en z lokalnymi tłumaczeniami i atrapą CSRF; nie ćwiczy prawdziwego tokenu ani kontrolera.
- **Zależności do sprawdzenia:** `templates/views/public/home/main.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP/przeglądarki; opis testów to nie rezultat ich uruchomienia.


### `tests/fixtures/well_workspace_render.php`

**Blob:** `e357e5728c234ca8e13f228183794a23a53f3e27` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 12 linii.

- **Ustalenie:** Fixture CLI renderuje prawdziwe dane gracza/dom. ID=2 przez init.php i WellService; mimo nazwy może wykonać bootstrap schematu przy połączeniu, więc nie uruchamiano.
- **Zależności do sprawdzenia:** `src/WellService.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP/przeglądarki; opis testów to nie rezultat ich uruchomienia.


### `tests/index.php`

**Blob:** `82ac827c78bfb48ec7da92cae2c3f4d67a6682ce` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 33 linii.

- **Ustalenie:** Index tests linkuje price_test.php i ostrzega o modyfikacjach DB; root .htaccess próbuje przekierować /tests, a tests/.htaccess ma RewriteEngine Off i Allow from all — skuteczność zależy od Apache.
- **Zależności do sprawdzenia:** `tests/.htaccess`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP/przeglądarki; opis testów to nie rezultat ich uruchomienia.


### `tests/test_notifications.php`

**Blob:** `b70b7f6473cf121f20155f533017b12650f11935` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 96 linii.

- **Ustalenie:** Ręczny skrypt wpisuje osiem powiadomień dla gracza ID=1, oznacza jedno przeczytanym; bez cleanup i exit(1) na wyjątku. Nie uruchamiać na nieizolowanej bazie.
- **Zależności do sprawdzenia:** `src/DirectorNotificationService.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP/przeglądarki; opis testów to nie rezultat ich uruchomienia.


### `tests/test_training_engine.php`

**Blob:** `56660b863d59c7d5541ee7b18e365e835f705ee1` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 199 linii.

- **Ustalenie:** Standalone test szkoleń na żywej DB kasuje stałe wysokie ID i globalnie zmienia training_programs.base_pass_rate, na końcu przywraca sztywne 85/80 zamiast poprzednich wartości; bez izolacji nie uruchamiać.
- **Zależności do sprawdzenia:** `src/Training/TrainingService.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP/przeglądarki; opis testów to nie rezultat ich uruchomienia.


### `src/FinancialTransaction/TickSettlementTrait.php`

**Blob:** `0288a92b11ecf9639f2170147593f69e82461af8` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 65 linii.

- **Ustalenie:** settleTickCosts pobiera saldo z blokadą, rozdziela budżet według kolejności typów i loguje wyłącznie opłacone kwoty; nadwyżkę total przypisuje do OPEX, aktualizuje cash/last_tick_at (13–63). Gdy suma typów przekracza total, nie odejmuje nadwyżki i może pobrać więcej niż total; warunkowe, zależy od callera.
- **Zależności do sprawdzenia:** `src/FinancialTransactionService.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL/HTTP; opis to statyczny kontrakt kodu, nie wynik testu.


### `src/HR/EmployeeRaiseRequest/DecisionTrait.php`

**Blob:** `79f7be20308a9bc535eecfd6fe8752790e6a0b94` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 129 linii.

- **Ustalenie:** Akceptacja podwyżki aktualizuje salary, loyalty, state i request; negocjacja przy losowaniu może zaakceptować ofertę albo obniżyć morale przy pozostawieniu statusu open; postponement ustawia deadline (10–127). Atomowość zależy od transakcji callera.
- **Zależności do sprawdzenia:** `src/HR/EmployeeRaiseRequest/PersistenceTrait.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL/HTTP; opis to statyczny kontrakt kodu, nie wynik testu.


### `src/HR/EmployeeRaiseRequest/NegotiationTrait.php`

**Blob:** `57e3fb8abfa003e77f7ba1b9511b76378c3d856b` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 84 linii.

- **Ustalenie:** Szansa negocjacji zależy od jakości oferty, HR, morale, lojalności i losowania, obcięta do 5–95%; obecność salary_negotiator potwierdza JOIN employee_state (19–82). Wyniku probabilistycznego nie ustalono przez wykonanie.
- **Zależności do sprawdzenia:** `src/HR/EmployeeRaiseRequest/DecisionTrait.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL/HTTP; opis to statyczny kontrakt kodu, nie wynik testu.


### `src/HR/EmployeeRaiseRequest/PersistenceTrait.php`

**Blob:** `1244a4b47508523ff9f704d0703c60244dcbe5e2` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 110 linii.

- **Ustalenie:** updateState obcina morale/support/risk, przelicza salary_satisfaction, wymaga dokładnie jednego zmienionego rekordu; resolveRequest wymaga open/postponed. updateSalary deleguje do CompensationService; brak własnej granicy transakcyjnej (6–109).
- **Zależności do sprawdzenia:** `src/HR/EmployeeRaiseRequest/DecisionTrait.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL/HTTP; opis to statyczny kontrakt kodu, nie wynik testu.


### `src/Hub/TickCalculationsTrait.php`

**Blob:** `dcd4edcf9e01c9f0fac240386892d9ef7addd966` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 113 linii.

- **Ustalenie:** Wyliczenie throughput progi kondycji i losowych strat mt_rand; status disabled/building/paused zachowany, inactive result stosuje fallback i zerowy bufor (13–112). Sam trait bez DB, testowanie wymaga kontrolowania RNG i kontraktu hubSvc.
- **Zależności do sprawdzenia:** `src/HubTickService.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL/HTTP; opis to statyczny kontrakt kodu, nie wynik testu.


### `src/TTS/CatalogTrait.php`

**Blob:** `ece157d5120b92cc72cc36ee6527e9300f3ee28e` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 77 linii.

- **Ustalenie:** Katalog stanowisk bazuje na hr_specializations department technical; brak wierszy daje pustą listę, a wyłącznie błąd zapytania wraca do stałych SPECS (10–65). Zachować odrębność pustej konfiguracji od awarii.
- **Zależności do sprawdzenia:** `src/TechnicalTeamService.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL/HTTP; opis to statyczny kontrakt kodu, nie wynik testu.


### `src/TTS/TaskTransactionTrait.php`

**Blob:** `937c56d6240d3435b9e1c0dcf084a7ae92b5c859` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 76 linii.

- **Ustalenie:** taskTransaction inicjuje zależność przed własną transakcją, a wewnątrz cudzej używa losowego SAVEPOINT i rollback do savepointu; blokady właściciela i staff mają kolejność players→technical_staff (10–75).
- **Zależności do sprawdzenia:** `src/FinancialTransactionService.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL/HTTP; opis to statyczny kontrakt kodu, nie wynik testu.


### `src/TTS/TaskValidationTrait.php`

**Blob:** `2f268345d217f53da81c72a312518754899b5a50` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 93 linii.

- **Ustalenie:** Walidacja celu TTS sprawdza specjalizację, własność pipeline/well i tenant lub właściciela huba poprzez przypisany odwiert; blokuje m.in. statusy sold/seized i dopuszcza blowout_control jako wyjątek (9–91).
- **Zależności do sprawdzenia:** `src/TTS/TaskTransactionTrait.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL/HTTP; opis to statyczny kontrakt kodu, nie wynik testu.


### `src/Tick/SpillSection.php`

**Blob:** `42544cfc4c14f4da83c22d0fcae3819900088f51` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 116 linii.

- **Ustalenie:** Spill przy zapełnieniu >=95% zlicza ticki w player_meta; po 2 losuje wyciek, aktualizuje liczniki/finanse i powiadomienie (29–80). Błędy odczytu/zapisu/resetu liczników są ignorowane (83–115), więc stan może zaniżyć/zgubić serię.
- **Zależności do sprawdzenia:** `src/WellService.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL/HTTP; opis to statyczny kontrakt kodu, nie wynik testu.


### `src/Tick/TrainingSection.php`

**Blob:** `7c0c5c65c0893111bde207692e9515f8a0a356c6` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 57 linii.

- **Ustalenie:** TrainingSection wyszukuje ukończone szkolenia i uruchamia processFinishedExams per gracz; błąd pobrania przerywa całość, błąd gracza jest logowany i kolejne ID są przetwarzane (26–56). Nie jest to gwarancja atomowego ticka.
- **Zależności do sprawdzenia:** `src/Training/TrainingService.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL/HTTP; opis to statyczny kontrakt kodu, nie wynik testu.


### `tests/MySqlIntegration/MySqlIntegrationTestCase.php`

**Blob:** `e7d8b0979ed0318fc14c1520ca2e6458f31c4900` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 334 linii.

- **Ustalenie:** Wspólne setUp: losuje ID 900M, przed seedem wykonuje DELETE w wielu tabelach, bez sprawdzenia dedykowanej bazy; tearDown powtarza cleanup. Konstruktor WellPipelineService może dotknąć schematu (11–76,272–289). To nie jest izolacja testów.
- **Zależności do sprawdzenia:** `src/WellPipelineService.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL/HTTP; opis to statyczny kontrakt kodu, nie wynik testu.


### `tests/bench_seed.php`

**Blob:** `f57a7b085da0a6253418f9e5a8c2d2ac3510b3e2` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 107 linii.

- **Ustalenie:** KRYTYCZNE: benchmark seed bez guardu izolacji łączy się z config/database.php, DELETE FROM 16 tabel WHERE 1=1, modyfikuje globalny well_config i seeduje ID 1M (10–42,69–101). Nawet na domyślnych parametrach bezwzględnie destrukcyjny wobec wskazanej bazy; nie uruchamiać.
- **Zależności do sprawdzenia:** `config/database.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL/HTTP; opis to statyczny kontrakt kodu, nie wynik testu.


### `tests/fixtures/b2b_concurrent_worker.php`

**Blob:** `62d4ab1fb54933f4a998ad1e709feecfd95a99b4` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 48 linii.

- **Ustalenie:** Worker B2B realizuje accept/deliver po bramce plikowej; wynik JSON, wyjątek kończy kodem 1. Nie stanowi samodzielnej asercji ani gwarancji izolacji DB (25–47).
- **Zależności do sprawdzenia:** `src/B2BContractService.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL/HTTP; opis to statyczny kontrakt kodu, nie wynik testu.


### `tests/fixtures/employee_assignment_concurrent_worker.php`

**Blob:** `2b05976d30820aa57e8ca79668240cc0fff11a78` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 56 linii.

- **Ustalenie:** Worker przydziału pracownika do huba czeka na gate, wywołuje assignToHub i zwraca JSON; wyjątek zwraca success=false, ale exit(0), więc status procesu sam nie oznacza sukcesu (25–55).
- **Zależności do sprawdzenia:** `src/Employee/EmployeeAssignmentService.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL/HTTP; opis to statyczny kontrakt kodu, nie wynik testu.


### `tests/fixtures/employee_hr_concurrent_worker.php`

**Blob:** `adaa2373b9b2fe1049b92647ad91e3bd34dcd89f` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 151 linii.

- **Ustalenie:** Worker współbieżności HR zawiera gałęzie ofert, podwyżek, premii, zatrudnienia i cyklu odejść; odczytuje DB z config, emituje JSON, a wyjątek kończy exit(0) (25–95). Wymaga izolowanej DB i asercji po stronie parenta.
- **Zależności do sprawdzenia:** `src/HRService.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL/HTTP; opis to statyczny kontrakt kodu, nie wynik testu.


### `tests/fixtures/hub_assignment_concurrent_worker.php`

**Blob:** `86e6a4170c5218361845027a1e9dc44bfb6840a3` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 45 linii.

- **Ustalenie:** Worker assignWell do huba czeka na gate, wywołuje HubAssignmentService i w razie wyjątku exit(1); sam nie weryfikuje końcowego stanu bazy (22–45).
- **Zależności do sprawdzenia:** `src/HubAssignmentService.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL/HTTP; opis to statyczny kontrakt kodu, nie wynik testu.


### `tests/fixtures/pipeline_assignment_concurrent_worker.php`

**Blob:** `96388eeac93318ac4ad75d680c143eb1e40a5bd8` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 56 linii.

- **Ustalenie:** Worker assignToPipeline czeka na gate, zwraca JSON; wyjątek daje success=false oraz exit(0), więc caller musi zbadać payload (25–56).
- **Zależności do sprawdzenia:** `src/Employee/EmployeeAssignmentService.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL/HTTP; opis to statyczny kontrakt kodu, nie wynik testu.


### `tests/fixtures/tech_notif_error_worker.php`

**Blob:** `cdf6639fc87c5e8c0776c3f6f5005a4acd740692` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 36 linii.

- **Ustalenie:** Fixture izoluje endpoint przez eval kodu źródłowego po usunięciu require init, podstawia mock Auth/CSRF i SQLite; testuje wyłącznie tę ścieżkę, nie faktyczny request HTTP/bootstrapping (9–36).
- **Zależności do sprawdzenia:** `src/TechNotifApi.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL/HTTP; opis to statyczny kontrakt kodu, nie wynik testu.


### `tests/fixtures/transaction_race_worker.php`

**Blob:** `6ad17f047a875ecd3c12ceee1c500f00ddf07545` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 55 linii.

- **Ustalenie:** Worker transakcyjnego wyścigu wymaga assertDedicatedDatabase przed działaniami; inicjuje serwisy/schemat i wywołuje sale/cancel/repay/legal/map, wyjątek kończy exit(1). Jego bezpieczeństwo zależy od implementacji guardu i wskazanego DB (12–55).
- **Zależności do sprawdzenia:** `tests/fixtures/TransactionRaceFixtures.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL/HTTP; opis to statyczny kontrakt kodu, nie wynik testu.


### `tests/trace_real_tick.php`

**Blob:** `24a076df5e369a8498ea7da6492888ee8e0f5d91` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 89 linii.

- **Ustalenie:** Skrypt diagnostyczny najpierw UPDATE market_state id=1, następnie losowy debug i realny MarketTick::updatePrices; brak asercji, rollbacku oraz izolacji. Porównanie debug vs real zależy od dwóch losowań (9–80).
- **Zależności do sprawdzenia:** `src/MarketTick.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL/HTTP; opis to statyczny kontrakt kodu, nie wynik testu.


### `src/AdminChatBroadcast.php`

**Blob:** `aaeff3584894f989d0aa2f5163f48f6e3a744f23` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 22 linii.

- **Ustalenie:** Broadcast sanitizuje HTML i zapisuje do aktywnego pokoju polski, limit 16000 bajtów wejścia i 500 znaków HTML; sukces przy rowCount=1 (7–20). Autoryzacja należy do callera.
- **Zależności do sprawdzenia:** `src/ChatMessageHtml.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; wnioski statyczne.


### `src/BankruptcyService.php`

**Blob:** `17ff3122b56695fa06a548e88c6c70f7fd6bcdb2` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 39 linii.

- **Ustalenie:** Fasada składa trzy traity Bankruptcy; konstruktor pobiera singleton DB i ID gracza, loguje błąd (3–39). Logika w traitach.
- **Zależności do sprawdzenia:** `src/Bankruptcy/StateTrait.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; wnioski statyczne.


### `src/Cache.php`

**Blob:** `ffbef044cc89f932bed58c5c237a8b4dff65df5d` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 54 linii.

- **Ustalenie:** Cache konkatenacją niezweryfikowanego key tworzy ścieżkę pliku PHP, get wykonuje include, flush usuwa wszystkie *.php w katalogu (12–53). Ryzyko traversal zależne od callera; nie znaleziono wywołań Cache:: w src.
- **Zależności do sprawdzenia:** `src/Cache.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; wnioski statyczne.


### `src/DmApi.php`

**Blob:** `5d19ab5e2f86f73565f13d6df87acc87f81809ce` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 10 linii.

- **Ustalenie:** Adapter buforuje i czyści output init, ustawia JSON, dopisuje GET with=null i ładuje ChatApi (3–10); skutki zależne od bootstrapu i endpointu.
- **Zależności do sprawdzenia:** `src/ChatApi.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; wnioski statyczne.


### `src/Employee/EmployeeRef.php`

**Blob:** `6203b2492cdaf4a4ffe31657a8c95fe85cfa4af1` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 37 linii.

- **Ustalenie:** EmployeeRef dopuszcza dwa źródła i dodatnie identyfikatory; key() zwraca sourceType:sourceId bez playerId (13–36). Globalne użycie klucza wymaga osobnego scope.
- **Zależności do sprawdzenia:** `src/Employee/EmployeeAssignmentService.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; wnioski statyczne.


### `src/Employee/TechnicalStaffProfile.php`

**Blob:** `c8e6a0ce621afa82513aaa22b7e51f9218bac7c4` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 34 linii.

- **Ustalenie:** Profil pracownika bierze cechy kandydata albo deterministyczny seed CRC32 z ID/nazwy/specjalizacji; obcina wartości do 1–10 (10–33).
- **Zależności do sprawdzenia:** `src/HRService.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; wnioski statyczne.


### `src/HR/MoraleBootstrap.php`

**Blob:** `37eb075fad7a352d0dcd11087cda35cc15918daa` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 64 linii.

- **Ustalenie:** ensureMoraleSchema ustawia static done przed sprawdzeniem Database i przed DDL; nieudana próba w danym request blokuje retry (8–63). Nie uruchomiono DDL.
- **Zależności do sprawdzenia:** `src/Database.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; wnioski statyczne.


### `src/HRService.php`

**Blob:** `1e34bf383781ab2a65df467bd1372d68e7f8d83d` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 70 linii.

- **Ustalenie:** HRService składa cztery traity; konstruktor tworzy CandidateGenerator, StrikeEffectService i pobiera singleton DB (8–69). Sam plik nie opisuje całego HR.
- **Zależności do sprawdzenia:** `src/HR/RecruitmentTrait.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; wnioski statyczne.


### `src/Mailer.php`

**Blob:** `1afef89a78e4754ded40f9455b8c9c72d376918e` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 60 linii.

- **Ustalenie:** Mailer wysyła SMTP HTML i AltBody; błędy oraz adres/temat są logowane, error_log dostaje treść wyjątku (15–58). Nie badano realnej poczty ani jej sekretów.
- **Zależności do sprawdzenia:** `src/PHPMailer/PHPMailer.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; wnioski statyczne.


### `src/PlayerPaymentService.php`

**Blob:** `3ff8a2a05219ff3397df7ced908a11191b940de0` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 55 linii.

- **Ustalenie:** Adapter charge/refund przekazuje do FinancialTransactionService debit/credit; konstruktor tworzy serwis, tu brak własnej transakcji (17–54).
- **Zależności do sprawdzenia:** `src/FinancialTransactionService.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; wnioski statyczne.


### `src/Training/AbstractTrainingSkill.php`

**Blob:** `3a262df2bbd53a3c2e7b2239d6a005bd74a774c3` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 44 linii.

- **Ustalenie:** Baza umiejętności: max 10, neutralny passRateModifier, ambicja 2 punkty procentowe na punkt od 5, retry +10 do +30 (13–43).
- **Zależności do sprawdzenia:** `src/Training/TrainingSkillInterface.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; wnioski statyczne.


### `src/Training/Skills/BoardColumnSkill.php`

**Blob:** `27729b5bb692d3c36f9853ad02111cbbc0409756` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 51 linii.

- **Ustalenie:** BoardColumnSkill waliduje kolumnę ścisłą białą listą, filtruje read/write po playerId i podnosi maksymalnie do 10 (14–50).
- **Zależności do sprawdzenia:** `src/Training/AbstractTrainingSkill.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; wnioski statyczne.


### `src/Training/Skills/TechnicalSubSkill.php`

**Blob:** `ddc3bb8cdc277bb0b3522336750b85a2005a66e0` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 54 linii.

- **Ustalenie:** TechnicalSubSkill odczytuje 1 przy braku wiersza umiejętności u istniejącego pracownika, 0 przy braku pracownika; insert-select filtruje playerId, duplikat zwiększa do 10 (19–53).
- **Zależności do sprawdzenia:** `src/Training/AbstractTrainingSkill.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; wnioski statyczne.


### `src/Training/TrainingSkillInterface.php`

**Blob:** `82d44da355e439e55e2f456aaf1fec3ff383326a` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 43 linii.

- **Ustalenie:** Interfejs wymaga getCurrentLevel i applyIncrement z playerId; izolacja gracza w komentarzu nie jest egzekwowana przez sam interfejs (11–43).
- **Zależności do sprawdzenia:** `src/Training/TrainingSkillRegistry.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; wnioski statyczne.


### `src/Training/TrainingSkillRegistry.php`

**Blob:** `8501077c5193193a65fb6836aad0cadc8a18f1fc` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 62 linii.

- **Ustalenie:** Rejestr tworzy 4 umiejętności technical i 5 board; klucz department:code, register może nadpisać wpis o tym samym kluczu (19–61).
- **Zależności do sprawdzenia:** `src/Training/Skills/TechnicalSubSkill.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; wnioski statyczne.


### `src/admin_wells.php`

**Blob:** `fff6b5d32568620e9d40644d5ba9bcd4f05ee692` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 11 linii.

- **Ustalenie:** Legacy entrypoint do admin/wells.php: loguje, w razie wyjątku status 500 (4–11); nie wprowadza własnej autoryzacji. Routing niezweryfikowany.
- **Zależności do sprawdzenia:** `admin/wells.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; wnioski statyczne.


### `src/i18n.php`

**Blob:** `7a790a8e089b69fbb34c42c361a8a2d303e1056d` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 71 linii.

- **Ustalenie:** getLocale ogranicza pl/en/de, t() HTML-escapes tekst po interpolacji, tPlain() pozostaje nieescapowany do DB/logów/maili (8–71).
- **Zależności do sprawdzenia:** `lang/pl.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; wnioski statyczne.


### `tests/Unit/AdminGmPaginationFrontend.test.cjs`

**Blob:** `b0dd83e784e5257303ceaeef84222b48843962e8` · **Status:** `pełny odczyt statyczny; wykonany test Node` · **Odczyt:** 65 linii.

- **Ustalenie:** Node 3/3 zaliczone: selekcja strony, przeniesienie ID między stronami i potwierdzenie wysłania (33–65). To mock DOM, nie HTTP ani PHP.
- **Zależności do sprawdzenia:** `assets/js/admin_gm.js`.
- **Granica dowodu:** Node: mock DOM/fetch bez HTTP, PHP lub DB.


### `tests/Unit/BoardroomUploadFrontend.test.cjs`

**Blob:** `a262cf5162bfa7a3cad4b5ebbc31eacf61cc65f4` · **Status:** `pełny odczyt statyczny; wykonany test Node` · **Odczyt:** 154 linii.

- **Ustalenie:** Node 20/20 zaliczone: chunking uploadu, CSRF, błędy sieci i odpowiedzi, retry, limit rozmiaru, dostępność szablonu (59–154). Mock fetch bez backendu.
- **Zależności do sprawdzenia:** `assets/js/admin-template-upload.js`.
- **Granica dowodu:** Node: mock DOM/fetch bez HTTP, PHP lub DB.


### `tests/Unit/technical_ui_behavior.test.js`

**Blob:** `41b6fcb78d2b197d7663728d92928ef7377baf13` · **Status:** `pełny odczyt statyczny; wykonany test Node` · **Odczyt:** 108 linii.

- **Ustalenie:** Node 1/1 zaliczony: filtry personelu/kandydatów/szkoleń, accordion i reload w makiecie DOM (70–108); bez przeglądarki.
- **Zależności do sprawdzenia:** `assets/js/technical.js`.
- **Granica dowodu:** Node: mock DOM/fetch bez HTTP, PHP lub DB.


### `src/AdminHub/ConfigFieldTrait.php`

**Blob:** `a5d2f234a820e8884b39468a92deeb72c6e49f0b` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 59 linii.

- **Ustalenie:** Renderowanie pól konfiguracji huba: escapuje wartości, group/key i CSRF w HTML, wysyła POST save_config na panel administracyjny (23–58). Walidacja serwerowa poza tym plikiem.
- **Zależności do sprawdzenia:** `admin/logistics_hubs.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP; scenariusze warunkowe wymagają izolowanego testu.


### `src/BankruptcyBootstrap.php`

**Blob:** `1922e45272b42701569874cfd97bf5cc88770559` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 117 linii.

- **Ustalenie:** Bootstrap bankructwa ustawia done przed DDL oraz masowym UPDATE graczy; błąd jest logowany i retry w request pomijane (11–47). POST guard blokuje pięć nazw skryptów, ale przy błędzie DB zwraca false i fail-open (50–116).
- **Zależności do sprawdzenia:** `src/init.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP; scenariusze warunkowe wymagają izolowanego testu.


### `src/HR/MoraleService.php`

**Blob:** `020bf847aad37275ad28539d1e2def7106de7a4f` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 79 linii.

- **Ustalenie:** LegacyMoraleService otwiera własną transakcję, blokuje staff FOR UPDATE, obcina morale 0–100 i zapisuje log (9–57). W istniejącej transakcji PDO beginTransaction może rzucić; brak obsługi savepoint.
- **Zależności do sprawdzenia:** `src/HR/MoraleBootstrap.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP; scenariusze warunkowe wymagają izolowanego testu.


### `src/HR/StrikeService.php`

**Blob:** `3891d75a35f1a4894ba13ad36185f280ea0765da` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 75 linii.

- **Ustalenie:** Legacy StrikeService tworzy strajk przez INSERT SELECT WHERE NOT EXISTS, odczytuje i zamyka po staffId; brak playerId dla trzech operacji (10–44). Bez izolacji współbieżności lub kontroli callera możliwy wyścig/zakres.
- **Zależności do sprawdzenia:** `src/HR/MoraleService.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP; scenariusze warunkowe wymagają izolowanego testu.


### `src/Hub/AdminQueryTrait.php`

**Blob:** `d58a44276abe25ac794d3182e5baca9ff1f3ef12` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 91 linii.

- **Ustalenie:** Admin Query pobiera wszystkie huby i liczbę przydziałów z opcjonalnym statusem/regionem, statystyki i nieprzeczytane eventy (13–90); uprawnienie admina egzekwuje caller.
- **Zależności do sprawdzenia:** `src/HubService.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP; scenariusze warunkowe wymagają izolowanego testu.


### `src/Hub/ViewSummaryTrait.php`

**Blob:** `1f11b6a2e5e74d32d4169bd6234dd536c2ee8bb6` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 160 linii.

- **Ustalenie:** Widok hubów grupuje tylko regiony z hubami lub odwiertami; sumuje pojemność i bufor, stan z ostatniego ticka, alerty oraz odwierty bez huba (22–122); wiele zapytań per hub.
- **Zależności do sprawdzenia:** `src/HubViewService.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP; scenariusze warunkowe wymagają izolowanego testu.


### `src/Incident/CooldownTrait.php`

**Blob:** `5cc73caac17cb3a58c9edae67230bc4aea7b2723` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 84 linii.

- **Ustalenie:** Cooldown dodaje kolumnę MySQL poza aktywną transakcją, preload odczytuje i utrwala start per player; pamięć cache po playerId (13–82). Potencjalna implikacja DDL wymaga izolowanego testu.
- **Zależności do sprawdzenia:** `src/IncidentService.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP; scenariusze warunkowe wymagają izolowanego testu.


### `src/Incident/MessagesTrait.php`

**Blob:** `cacf21b265e976f06b611297f6de0c9b5c6e3c62` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 117 linii.

- **Ustalenie:** Incydent losuje przyczynę, spadek, czas, uszkodzenia i koszt; aktywne HSE zmniejsza medium/major, komunikat używa tPlain z fallbackiem (25–104). Nie testowano RNG.
- **Zależności do sprawdzenia:** `src/IncidentService.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP; scenariusze warunkowe wymagają izolowanego testu.


### `src/IncidentService.php`

**Blob:** `09237e698b63fe12fa961bfc77aa11e951857fe9` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 135 linii.

- **Ustalenie:** Fasada Incidents składa cztery traity i w konstruktorze czyta well_config; override poziomów i prawdopodobieństwa z DB, brak odczytu zostawia domyślne (26–135). Komentarz o rozkładzie nie jest testem.
- **Zależności do sprawdzenia:** `src/Incident/TickTrait.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP; scenariusze warunkowe wymagają izolowanego testu.


### `src/InterestEngine.php`

**Blob:** `9a49798eb79bfdd33688501ea4a3136fba48f36a` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 121 linii.

- **Ustalenie:** InterestEngine iteruje wszystkie aktywne/spóźnione kredyty; po godzinie zwiększa saldo i zapisuje loan_payments bez własnej transakcji/blokady, wyjątki per kredyt połyka (23–120). Warunkowe niespójności/duplikacja przy współbieżności.
- **Zależności do sprawdzenia:** `src/BankService.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP; scenariusze warunkowe wymagają izolowanego testu.


### `src/LogisticsPage/ViewDataTrait.php`

**Blob:** `1c7224d0c5db6123129b4f0d4299befc8eed1b1a` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 77 linii.

- **Ustalenie:** Widok logistyki scala pliki danych i sprawdza zestaw wymaganych kluczy; loadLockedRegionSet przy dowolnym błędzie zwraca pusty set, czyli brak blokad w UI (12–76). Uprawnienia serwera wymagają osobnej kontroli.
- **Zależności do sprawdzenia:** `src/LogisticsPage/ViewData/OverviewAndProtectionData.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP; scenariusze warunkowe wymagają izolowanego testu.


### `src/OutboundLegService.php`

**Blob:** `1c03f20a94933f6026cfd27143a74ce88f897028` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 193 linii.

- **Ustalenie:** Pure leg-2: pipeline niedostępny/zero capacity blokuje przepływ, nadmiar wraca do bufora; droga liczy OPEX z globalnym mnożnikiem i losowe straty (41–191). Test SQLite obejmuje wybrane granice, nie pełny tick.
- **Zależności do sprawdzenia:** `tests/Integration/BugfixRound4Test.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP; scenariusze warunkowe wymagają izolowanego testu.


### `src/PHPMailer/Exception.php`

**Blob:** `09c1a2cfefaa986c846b41743e856c945beff3cd` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 40 linii.

- **Ustalenie:** Bundled PHPMailer Exception dziedziczy Exception; errorMessage() HTML-escapuje tekst wyjątku (22–40). To komponent vendor, nie dowód bezpieczeństwa mailera.
- **Zależności do sprawdzenia:** `src/Mailer.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP; scenariusze warunkowe wymagają izolowanego testu.


### `src/PipelineApi.php`

**Blob:** `1ea30bc4f59b02ecb93fd37e1622fe494d5f2904` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 191 linii.

- **Ustalenie:** Endpoint PipelineApi: wymaga loginu, CSRF dla POST, buy/repair/maintenance/toggle i odczyty GET; konstruktor WellPipelineService jest wykonywany przed akcją (14–190). Nie testowano routingu/HTTP.
- **Zależności do sprawdzenia:** `src/WellPipelineService.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP; scenariusze warunkowe wymagają izolowanego testu.


### `src/RegionalEvent/ModifiersTrait.php`

**Blob:** `118c3e26e4f610878b9fd4146e26b34d3f8ab040` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 91 linii.

- **Ustalenie:** Aktywne zdarzenia regionalne filtruje playerId, getWellModifiers wylicza procenty z severity, nie z deklarowanych stop_pct/tax_extra w EVENT definicjach (14–89). Wartości definicji nie są zapisywane przez triggerEvent (EventsTrait:73–81).
- **Zależności do sprawdzenia:** `src/RegionalEvent/EventsTrait.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP; scenariusze warunkowe wymagają izolowanego testu.


### `src/RegionalEventService.php`

**Blob:** `0d80960b6ce414fd1949ac35008d698327950372` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 95 linii.

- **Ustalenie:** RegionalEventService trzyma definicje regionów i stałe prawdopodobieństwa; wartości stop_pct/tax_extra/bonus_pct nie trafiają do INSERT w EventsTrait:73–81, a ModifiersTrait stosuje severity (27–84).
- **Zależności do sprawdzenia:** `src/RegionalEvent/EventsTrait.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP; scenariusze warunkowe wymagają izolowanego testu.


### `src/TaskConfigService.php`

**Blob:** `3848459209e26f984babed9d0514e1dd2b6afa2e` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 98 linii.

- **Ustalenie:** TaskConfigService oznacza static done przed CREATE TABLE, nie ponawia DDL po błędzie; save zapisuje cztery klucze w osobnych UPSERT, brak lokalnej transakcji (17–34,67–85).
- **Zależności do sprawdzenia:** `src/TTS/TaskValidationTrait.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP; scenariusze warunkowe wymagają izolowanego testu.


### `src/Tick/OfflineSection.php`

**Blob:** `c20ef88f26cc2b42d1258957fde8a6e25565a627` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 162 linii.

- **Ustalenie:** Konstruktor OfflineSection ustawia schemaEnsured po spl_object_id zanim sprawdzi transakcję lub wykona DDL (32–50); jeśli pierwsze wywołanie jest w transakcji/błąd DDL, ponowienie na tym ID jest pomijane. Ochrona offline 24h i freeze dla gotówki <=0 (61–160).
- **Zależności do sprawdzenia:** `src/Database.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP; scenariusze warunkowe wymagają izolowanego testu.


### `src/TickModuleAdminService.php`

**Blob:** `2ae89400bf1e7c5564b1e4604fa732b768275b7c` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 177 linii.

- **Ustalenie:** Admin modułów ticka: modules() synchronizuje wpisy konfiguracji przez INSERT IGNORE, więc nominalny odczyt zapisuje DB; krytyczny moduł updateSettings wymusza enabled=true; cleanup usuwa historię (19–124).
- **Zależności do sprawdzenia:** `src/Tick/TickModuleConfigRepository.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP; scenariusze warunkowe wymagają izolowanego testu.


### `tests/Integration/BugfixRound4Test.php`

**Blob:** `693ec28af9f601c986ecf77af54d37731f1f8288` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 382 linii.

- **Ustalenie:** SQLite regresje: scope processWear, wyciek bez bezpośredniej zmiany storage, toggle pipeline, limit bufora, okno incydentów, leg-2 zero capacity i koszt drogi (23–381). Refleksja omija konstruktor usług; nie uruchomiono PHPUnit ani MySQL.
- **Zależności do sprawdzenia:** `src/WellService.php`.
- **Granica dowodu:** Nie uruchomiono PHPUnit/PHP/MySQL/HTTP; opis asercji to odczyt testu.


### `src/AdminHub/DataFetchTrait.php`

**Blob:** `b57c9563fe8f06211ed80d97d1efe2c2b5bff846` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 210 linii.

- **Ustalenie:** AdminHubDataFetchTrait daje globalne mapy i diagnostykę ticka; total_opex_charged jest opisany jako przybliżenie z parametrów hubów, nie suma faktycznych obciążeń (110–209).
- **Zależności do sprawdzenia:** `src/HubService.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP i bez testu współbieżności.


### `src/AdminHub/StaffingTrait.php`

**Blob:** `c41205f1eaecdce6d81ff2649fcf747d2984966b` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 209 linii.

- **Ustalenie:** AdminHubStaffingTrait czyta/zapisuje cztery klucze well_config, save robi osobne UPSERT bez własnej transakcji; diagnostyka filtruje i paginuje assignments (10–209).
- **Zależności do sprawdzenia:** `admin/logistics_hubs.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP i bez testu współbieżności.


### `src/BoardAccess.php`

**Blob:** `7e0be255ddd92209d79b68b86f555f43ab8b50ea` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 151 linii.

- **Ustalenie:** BoardAccess po błędzie DB odmawia ról; cache per playerId, filtr menu dotyczy tylko znanych url_key; has/require wykonują kontrolę, sam filterNav nie zabezpiecza endpointu (49–150).
- **Zależności do sprawdzenia:** `src/GameNavigation.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP i bez testu współbieżności.


### `src/ContractReputationService.php`

**Blob:** `0a96472f76958ec5f9026086915b27ee2e52c23b` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 458 linii.

- **Ustalenie:** ContractReputationService czyta snapshot terms_json po playerId i prowadzi ledger 0–100; changeScore używa transakcji własnej lub transakcji callera, bez własnego savepointu w cudzej (18–458). Testy SQLite pokrywają wybrane ścieżki.
- **Zależności do sprawdzenia:** `src/Contracts/ContractSchema.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP i bez testu współbieżności.


### `src/Contracts/ContractQueryTrait.php`

**Blob:** `e17e11d0c2ea246d2a2486b06d840816e1813356` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 337 linii.

- **Ustalenie:** ContractQueryTrait czyta opcje i terminy, sprawdza reputację, legal, limit kontraktów oraz scope; cleanup kasuje historię po 1–30 dniach, fetchList przy błędzie zwraca [] (10–337).
- **Zależności do sprawdzenia:** `src/ContractService.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP i bez testu współbieżności.


### `src/Database.php`

**Blob:** `d313e4c65e441c99bc12a29238c5f2b0fc2eb71a` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 132 linii.

- **Ustalenie:** Database łączy MySQL z config/database.php, włącza PDO emulated prepares, addColumnIfMissing sprawdza INFORMATION_SCHEMA i robi ALTER (8–131). Kod nie potwierdza izolacji testowej ani bezpieczeństwa wszystkich zapytań.
- **Zależności do sprawdzenia:** `config/database.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP i bez testu współbieżności.


### `src/DisasterMessages.php`

**Blob:** `94b4eafda5c334746432b1965ac48830153ef21a` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 206 linii.

- **Ustalenie:** DisasterMessages cache per typ i HSE, fallback przy braku DB/wierszy, interpolacja placeholderów z kontekstem bez HTML-escaping (63–205); sposób renderowania i kontrola danych wejściowych pozostają niezweryfikowane.
- **Zależności do sprawdzenia:** `src/i18n.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP i bez testu współbieżności.


### `src/GameLog.php`

**Blob:** `f3288dbd003dde719c57d10488e5b99db13f8faa` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 255 linii.

- **Ustalenie:** GameLog zapisuje linię do pliku, przy blokadzie używa *.fallback.log; db/dbError logują parametry SQL bez redakcji, pageStart loguje query i session ID (39–170). Root .htaccess odmawia *.log, ale nie badano HTTP ani retencji.
- **Zależności do sprawdzenia:** `.htaccess`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP i bez testu współbieżności.


### `src/Hub/TickPersistTrait.php`

**Blob:** `4010bbd95ac44d7a86b138096d2d663944821cf7` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 207 linii.

- **Ustalenie:** HubTickPersistTrait zapisuje hub i telemetrię we własnej transakcji lub savepoincie; addBufferBbl odczytuje wolne miejsce, potem UPDATE LEAST ale zwraca wstępnie obliczoną ilość (15–175). Przy współbieżności zwrot może różnić się od realnego przyrostu.
- **Zależności do sprawdzenia:** `src/HubTickService.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP i bez testu współbieżności.


### `src/LogisticsPage/ActionsTrait.php`

**Blob:** `61369ec8de69b193de40203ed8543ea58edbfd9c` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 143 linii.

- **Ustalenie:** LogisticsPageActionsTrait dopuszcza cztery akcje obsady po CSRF i PRG; tworzy flash, tłumaczy błędy domenowe i deleguje playerId do serwisów (9–142).
- **Zależności do sprawdzenia:** `src/Employee/PipelineStaffingManagementService.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP i bez testu współbieżności.


### `src/LogisticsPageController.php`

**Blob:** `61a15e0f11f68bf5eedeef4f3f115a2a099a1be2` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 107 linii.

- **Ustalenie:** LogisticsPageController tworzy serwisy i w konstruktorze wywołuje purgeOrphanActiveForPlayer, więc nawet utworzenie kontrolera może mutować DB (90–106).
- **Zależności do sprawdzenia:** `src/MarineDeliveryService.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP i bez testu współbieżności.


### `src/Player.php`

**Blob:** `83a2db5abd92241765a498bec987aca53e773adc` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 191 linii.

- **Ustalenie:** Player.updateCash warunkowo odejmuje saldo i próbuje audytowej transakcji FTS, ale błąd audytu połyka i może zatwierdzić zmianę cash bez logu (47–108). isBankrupt przy błędzie fail-closed (110–145).
- **Zależności do sprawdzenia:** `src/FinancialTransactionService.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP i bez testu współbieżności.


### `src/RateLimiter.php`

**Blob:** `aa936e4a27aa96a6bda4d6f9cd78e638717199a7` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 165 linii.

- **Ustalenie:** RateLimiter przechowuje liczniki w $_SESSION z identyfikatorem w nazwie klucza, nie w współdzielonym storage; oddzielne sesje mogą omijać globalny limit (29–60). Progressive forgot_password utrzymuje failures/cooldown w sesji (68–165).
- **Zależności do sprawdzenia:** `src/Auth.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP i bez testu współbieżności.


### `src/RegionalEvent/EventsTrait.php`

**Blob:** `7be0aec7fae2ea3315e7f6e07c82cf8a1c6a82c0` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 130 linii.

- **Ustalenie:** RegionalEventsTrait losuje wydarzenie, zapisuje typ/severity/daty oraz powiadomienie best effort; definicje procentów nie są persystowane (12–111), co współgra z osobnym obliczeniem z severity.
- **Zależności do sprawdzenia:** `src/RegionalEventService.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP i bez testu współbieżności.


### `src/Storage.php`

**Blob:** `b1661caf5e67283bd52907e90225a91608221430` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 157 linii.

- **Ustalenie:** Storage::getData() przy braku wiersza próbuje INSERT IGNORE, a przy błędzie inserta nadal zwraca domyślną pojemność; sellAll() zwraca zarobek mimo braku kontroli $ok execute (22–115). Warunkowe skutki zależą od callera.
- **Zależności do sprawdzenia:** `src/Player.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP i bez testu współbieżności.


### `src/Tick/FinancialStateSection.php`

**Blob:** `02e793cfeb8b200795416c735599dfae69283621` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 162 linii.

- **Ustalenie:** FinancialStateSection wylicza kryzys i limit godzin na podstawie wyniku ticka, aktualizuje stan, settleTickCosts otrzymuje całość jako OPEX; wywołanie bankruptcy jest w logice kryzysu (25–160).
- **Zależności do sprawdzenia:** `src/FinancialTransactionService.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP i bez testu współbieżności.


### `src/Totp.php`

**Blob:** `1dc8c35aa6c44b33396a740fe0e8a401add2b343` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 150 linii.

- **Ustalenie:** Totp generuje sekret random_bytes i HOTP SHA1; verifyWithCounter zwraca licznik do ochrony replay po stronie callera, samo verify zwraca bool bez pamięci użytych kodów (26–63). Bez wykonania PHP.
- **Zależności do sprawdzenia:** `src/Auth.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP i bez testu współbieżności.


### `src/WalletConfig.php`

**Blob:** `6640b9b9aaa4dce354c45c6c81c2f0591d3c71f1` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 143 linii.

- **Ustalenie:** WalletConfig ustala routing cash/bank dla typów, opłatę i limit transferu z well_config; zero oznacza bez limitu, błąd odczytu wraca do stałej (21–143).
- **Zależności do sprawdzenia:** `src/FinancialTransactionService.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP i bez testu współbieżności.


### `src/WalletService.php`

**Blob:** `b07c08b84b95cde773befb61cd812bd19f0d7f59` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 274 linii.

- **Ustalenie:** WalletService transfer blokuje saldo FOR UPDATE i aktualizuje dwie pule; w ensureSchema migracja 50/50 jest w try/catch, ale po jej błędzie schemaReady ustawiane na true, więc nie ponawia migracji dla tego ID PDO (189–245).
- **Zależności do sprawdzenia:** `src/Database.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP i bez testu współbieżności.


### `tests/Integration/ContractReputationServiceTest.php`

**Blob:** `47a4dca66bf1fa49b2b117adaf8d8c98b0078bc9` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 410 linii.

- **Ustalenie:** SQLite: domyślna reputacja 50, limity 0–100 i ledger, wymóg podpisania, tick success/miss/partial/failure, cancel z snapshotem warunków, scope gracza, panel admin i blokada przed skróceniem (21–410). Nie wykonano PHPUnit.
- **Zależności do sprawdzenia:** `src/ContractReputationService.php`.
- **Granica dowodu:** Nie wykonano PHPUnit/PHP/MySQL; asercje opisano wyłącznie z kodu.


### `tests/Integration/ContractServiceTest.php`

**Blob:** `34e50f05ba24c7b50c53683e2c54e2489f69c31a` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 636 linii.

- **Ustalenie:** SQLite asercje schematu, domyślnego wyłączenia modułu, opcji/wymagań, akceptacji, kaucji i zwrotu, zakończenia/anulowania, strony historii oraz otwartej transakcji callera (25–636). Nie są to wyniki uruchomienia; test tworzy własne tabele SQLite i używa prawdziwego ContractService.
- **Zależności do sprawdzenia:** `src/ContractService.php`.
- **Granica dowodu:** PHPUnit/PHP/MySQL nie uruchomiono; pozostałe ścieżki biznesowe wymagają izolowanego testu.


### `src/ContractService.php`

**Blob:** `6aecb0ca84537b73ae5fbd6d735d9ef2b0909001` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 926 linii.

- **Ustalenie:** accept/cancel/due stosują transakcję własną albo SAVEPOINT i snapshot terms_json, kontrolują scope i księgowania; per-due przetwarza maksymalnie 200 kontraktów (151–667). renegotiateContract dopuszcza pięć kluczy, lecz nie waliduje wartości liczbowych, m.in. ujemnego delivery_bbl; przy odpowiedniej aktywacji kontraktu może naruszać invariant dostaw (669–762). W src/public/admin nie znaleziono wywołania tej metody — ekspozycji HTTP nie potwierdzono.
- **Zależności do sprawdzenia:** `src/Contracts/ContractQueryTrait.php`, `src/Contracts/ContractSchema.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL; ryzyko renegocjacji jest warunkowe i wymaga ustalenia osiągalności oraz testu izolowanego.


### `src/Contracts/ContractSchema.php`

**Blob:** `4a12995a7dd780d1424b15a14e5c040fff4677c1` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 511 linii.

- **Ustalenie:** ensure kończy bez migracji w otwartej transakcji i łapie błędy DDL; MySQL migracja wewnętrznie połyka wyjątki (13–46,212–254). Kolumny renegotiations_used i last_renegotiated_at są dodawane migracją, nie CREATE TABLE. SQLite tworzy analogiczne tabele testowe, ale nie weryfikuje przejścia istniejącego schematu MySQL. Seedowane trzy opcje nie definiują allow_renegotiation (417–467); ujemne terminy z renegocjacji są w domyślnej konfiguracji zablokowane.
- **Zależności do sprawdzenia:** `src/ContractService.php`, `src/Database.php`.
- **Granica dowodu:** Nie wykonano DDL ani migracji; stan istniejącej bazy i warianty obsługi błędów są niepotwierdzone.


### `src/BankNegotiation/RandomEventsTrait.php`

**Blob:** `53a712ab1d1ed3abc65c3bf1934013f5ad0ab627` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 193 linii.

- **Ustalenie:** 25% losowania przy wywołaniu, 3h cooldown od ostatniego zdarzenia, limit due +48h (28–56); odczyt cooldown, UPDATE due i INSERT eventu nie są objęte własną transakcją, więc dwa równoległe wywołania mogą przejść kontrolę i zapisać zdarzenia. Hipoteza współbieżności bez wykonania.
- **Zależności do sprawdzenia:** `src/BankNegotiationService.php`.
- **Granica dowodu:** Nie wykonano PHP/DB/HTTP ani testu współbieżności; ryzyka warunkowe.


### `src/TechnicalPage/ViewDataTrait.php`

**Blob:** `d99080fb3d7dc88244d7ed57e90aa424313db1c0` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 203 linii.

- **Ustalenie:** buildViewData wywołuje processTick przed renderem danych (29–37), więc GET strony może mutować stan; błąd jest logowany i render trwa dalej. Tab jest na allowliście, część danych ma safeLoad fallback, lecz normalizeWellStatuses/getEffectiveProduction i inne wywołania są poza nim (45–135).
- **Zależności do sprawdzenia:** `src/TechnicalPageController.php`.
- **Granica dowodu:** Nie wykonano PHP/DB/HTTP ani testu współbieżności; ryzyka warunkowe.


### `src/Hub/PlayerQueryTrait.php`

**Blob:** `445d75051651b4bc97c7d6437de6801d70c46e48` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 245 linii.

- **Ustalenie:** getMarketHubsPageForPlayer ogranicza regiony do odwiertów gracza, bindowanie i escape LIKE (101–159); getRegionHubs(regionId) nie sprawdza ownership/tenant i zwraca wszystkie aktywne huby regionu (166–180), więc nie jest samodzielną kontrolą uprawnienia, mimo komentarza o dostępności.
- **Zależności do sprawdzenia:** `src/HubService.php`.
- **Granica dowodu:** Nie wykonano PHP/DB/HTTP ani testu współbieżności; ryzyka warunkowe.


### `src/PortService.php`

**Blob:** `495fd33808b12add865947b7b77fda7d008d7d0a` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 254 linii.

- **Ustalenie:** hasActivePortForRegion twardo ogranicza aktywny/przeciążony port do regionu i memoizuje per instancja (66–88), natomiast findForRegion ma fallback losowego portu z innego regionu (98–118): migracja musi zachować odrębne semantyki bramki produkcji i routingu. Konstruktor wykonuje DDL ensureSchema bez guard dla otwartej transakcji (20–51).
- **Zależności do sprawdzenia:** `src/Tick/PortSection.php`.
- **Granica dowodu:** Nie wykonano PHP/DB/HTTP ani testu współbieżności; ryzyka warunkowe.


### `src/HR/EventsTrait.php`

**Blob:** `7b191caba0216c0c92fe1780d462ef6978a4d620` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 251 linii.

- **Ustalenie:** fireEmployee/fireTechnicalStaff weryfikują player/status, zmieniają status i odpinają przypisania w transakcji własnej (14–153); brak współpracy z transakcją callera — beginTransaction() może zawieść jeśli aktywna. markEventsRead obejmuje dwie tabele transakcją własną tylko jeśli brak transakcji callera (178–198).
- **Zależności do sprawdzenia:** `src/HRService.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP ani testów plikowych; wskazane ryzyka warunkowe.


### `src/Employee/PipelineStaffingService.php`

**Blob:** `0d3f0b6d8483634ae91723f354658602be7b3946` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 252 linii.

- **Ustalenie:** Stan obsady jest liczony z przypisań, roli technicznej, stanu relacji i efektów; brak inżyniera podwaja mnożniki degradacji/incydentu, logistic coverage wpływa na weighted loss (34–111). Odczyt przypisań w jednym zapytaniu jest ograniczony player_id i id rurociągu.
- **Zależności do sprawdzenia:** `src/Employee/PipelineStaffingManagementService.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP ani testów plikowych; wskazane ryzyka warunkowe.


### `src/BoardroomUploadService.php`

**Blob:** `38db8e6c886dad1b053447a4c1ec1af2e641776b` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 206 linii.

- **Ustalenie:** Waliduje metadane i rozmiary, blokuje katalog uploadu flock, składa i dekoduje części, weryfikuje obraz mime/dimensions, ponownie koduje PNG i publikuje przez rename (20–205). Autoryzacja ownera i CSRF są odpowiedzialnością callera, nie tej klasy; bez uruchomienia PHP/filesystem.
- **Zależności do sprawdzenia:** `tests/Unit/BoardroomUploadServiceTest.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP ani testów plikowych; wskazane ryzyka warunkowe.


### `src/TransportConfigService.php`

**Blob:** `a687a2d5d6791a1cc7d3ec1873f06e1d29a97977` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 218 linii.

- **Ustalenie:** load korzysta z wartości domyślnych i nadpisuje tylko rozpoznane klucze; przy błędzie tabeli lub SELECT wraca fallback (18–58). ensureTransportSchema ustawia statyczne schemaEnsured przed próbą migracji i połyka błędy etapów DDL (65–217), więc częściowy błąd nie jest ponawiany w tym procesie.
- **Zależności do sprawdzenia:** `src/Database.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP ani testów plikowych; wskazane ryzyka warunkowe.


### `src/Employee/PipelineStaffingManagementService.php`

**Blob:** `8e3437beda1c29d978a3a9ddbd9aa4153b6dd28d` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 234 linii.

- **Ustalenie:** Widok obsady buduje per-rura kandydatów z wolnym procentem alokacji i deduplikuje payload (33–143); zapis deleguje do EmployeeAssignmentService::assignToPipeline i wybiera tylko role inżyniera/logistyka (145–166). UI can_assign nie jest kontrolą serwerową; docelowe walidacje należy potwierdzić w serwisie przypisań.
- **Zależności do sprawdzenia:** `src/Employee/EmployeeAssignmentService.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; scenariusze transakcyjne są warunkowe.


### `src/CashTransferService.php`

**Blob:** `720c00bcf3876a04ede349f7ab11808cdc462f1c` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 266 linii.

- **Ustalenie:** cashToBank/bankToCash liczą prowizję, blokują saldo FOR UPDATE, aktualizują obie pule i wymagają wpisu FTS (85–233). Przy aktywnej transakcji callera nie tworzą savepointu: jeśli po UPDATE wystąpi błąd logu, metoda zwraca fail bez cofnięcia zmian w transakcji wywołującego. Warunkowy scenariusz awarii; nie uruchomiono DB.
- **Zależności do sprawdzenia:** `src/WalletService.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; scenariusze transakcyjne są warunkowe.


### `src/HubTickService.php`

**Blob:** `a2a61c75516c5f0d10e149e7a7ab016b3814d229` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 239 linii.

- **Ustalenie:** proces hubu wylicza przepustowość, bufor/overflow, drain, zużycie, straty kondycji i status, zwracając podsumowanie bez zapisu DB (70–217); fallback bez przypisania ogranicza throughput i podwyższa opex (225–238). Persist osobny w Hub/TickPersistTrait, już ujęty w audycie.
- **Zależności do sprawdzenia:** `src/Hub/TickPersistTrait.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; scenariusze transakcyjne są warunkowe.


### `src/TTS/StaffTrait.php`

**Blob:** `287fa3f28e8b4b02ad6869884dff886395f18efd` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 230 linii.

- **Ustalenie:** hireEngineer używa własnej transakcji lub SAVEPOINT, pobiera pensję przez FTS i tworzy profil (96–178); na odmowie debit w gałęzi transakcji callera tylko RELEASE SAVEPOINT bez ROLLBACK TO (125–129). Oczekiwanie braku pobocznych zmian zależy od gwarancji FTS. fireEngineer blokuje wiersz i sprawdza zadania aktywne (180–229).
- **Zależności do sprawdzenia:** `src/FinancialTransactionService.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; scenariusze transakcyjne są warunkowe.


### `src/Bankruptcy/StateTrait.php`

**Blob:** `22a4819a4381ec49dbdbffe30b3ff34cacbaed43` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 222 linii.

- **Ustalenie:** getState wykonuje ensureRecoveryMode i tickBankruptcyFlow podczas odczytu, a wyjątek zwraca domyślnie is_bankrupt=false (10–29); przy awarii DB UI może widzieć stan niebankrutujący, nie jest to dowód pominięcia autoryzacji. tryRecover po progu cash/debt/wells zmienia stan i usuwa zajęte odwierty w transakcji (112–161); sam odczyt progów poprzedza beginTransaction.
- **Zależności do sprawdzenia:** `src/BankruptcyService.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP, bez migracji i ticka na żywej bazie.


### `src/BankNegotiation/RequestsTrait.php`

**Blob:** `21c91627157c2f135514d750e80b5b8d0812f3ec` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 289 linii.

- **Ustalenie:** Trzy rodzaje wniosków walidują loan player/status i brak pending/approved przed beginTransaction, potem zapisują pending (10–289). Przy równoległych wywołaniach kontrola hasPendingOrApproved poza transakcją może przepuścić dwa żądania, chyba że ogranicza je inna blokada/unikalność — bez testu i pełnego schematu.
- **Zależności do sprawdzenia:** `src/BankNegotiationService.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP, bez migracji i ticka na żywej bazie.


### `src/TrainingBootstrap.php`

**Blob:** `00af97aefdc8af277ff8d32a6025e36d8be7dc0e` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 188 linii.

- **Ustalenie:** ensureTrainingSchema oznacza done przed DDL, po błędzie nie ponawia w procesie (11–25,184–186). Dla starej tabeli bez indeksu dedup anuluje wyłącznie MIN(id) na grupę duplikatów: przy co najmniej trzech aktywnych wpisach pozostają dwa, więc dodanie UNIQUE może nie przejść (134–160). Migracja modyfikuje dane; nie uruchamiać na obecnej bazie.
- **Zależności do sprawdzenia:** `src/Training/TrainingService.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP, bez migracji i ticka na żywej bazie.


### `src/Incident/TickTrait.php`

**Blob:** `df0becf18ad88714668c84d6e812f26de78dd4d5` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 269 linii.

- **Ustalenie:** processTick wylicza elapsed z cooldownAgeSeconds, dla catch-up używa tylko exposedHours po immunitecie, losuje major→micro, a micro omija immunitet (49–67,134–243). Zapisuje ticks_since_incident, incydent i efekty; wynik zależy od mt_rand oraz danych geologii, HSE i staff. Bez wykonania ticka.
- **Zależności do sprawdzenia:** `src/IncidentService.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP, bez migracji i ticka na żywej bazie.


### `src/HR/EmployeeLegacyMigrationService.php`

**Blob:** `eaf451fcfe1453a225f8369171da3df9b5115ed4` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 263 linii.

- **Ustalenie:** run(false) robi preflight/backfill dry-run; run(true) wykonuje backfill, migrację morale i strajków w transakcji, sprawdza idempotencję stanu (15–65). Zapis wybiera legacy morale tylko przy pristine canon lub nowszym logu, a strajki upsertuje po open_key (68–215). Nie uruchomiono apply ani dry-run na bieżącej bazie.
- **Zależności do sprawdzenia:** `src/Employee/EmployeeStateService.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP, bez migracji i ticka na żywej bazie.


### `tests/Integration/B2BContractServiceTest.php`

**Blob:** `7ced28c183e695262ba28d30637530f39721fbfb` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 871 linii.

- **Ustalenie:** SQLite asercje depozytu escrow, limitu i widełek oferty, zakazu self-deal, dostawy pełnej/częściowej, prowizji kary, anulowania, wygaśnięcia i finalizacji przez moduł tick, paginacji i logów B2B (26–780). Test nie potwierdza równoległych transakcji MySQL, ochrony route admin ani rzeczywistego cron.
- **Zależności do sprawdzenia:** `src/B2BContractService.php`.
- **Granica dowodu:** Nie uruchomiono PHPUnit/PHP/DB; opis dotyczy treści asercji, nie wyniku.


### `src/B2BContractService.php`

**Blob:** `de3449da575d95414d4f9d900ff88162a85d97a1` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 1939 linii.

- **Ustalenie:** Escrow i oil przepływają w transakcjach z blokadą oferty oraz FTS, a częściowe dostawy rozliczają rounding i residual (152–939,1435–1523). Parametr min_seller_reputation jest zapisany w createBuyOffer, lecz acceptOffer nie sprawdza go (192–210,377–541); wyszukanie w src/public nie wykazało innej kontroli. Open-offer limit jest sprawdzany przed transakcją (175–189), zatem limit może być przekroczony przy wyścigu. listAdminDeliveries/countAdminDeliveries ignorują parametr filters (1328–1352).
- **Zależności do sprawdzenia:** `src/B2BContracts/B2BContractSchema.php`, `tests/Integration/B2BContractServiceTest.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL; brak asercji testu dla min_seller_reputation i równoległości.


### `src/B2BContracts/B2BContractSchema.php`

**Blob:** `24013763899473837c10914237c8b41e23ea935b` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 396 linii.

- **Ustalenie:** DDL MySQL i SQLite tworzy oferty, escrow, dostawy, logi, reputację i config; brak UNIQUE dla otwartych ofert per buyer, więc COUNT przed transakcją w serwisie nie jest wsparty ograniczeniem schematu (44–179,233–355). ensure pomija otwartą transakcję i łapie błędy, przy migracji starej bazy addColumnIfMissing dla B2B w trybie DDL (13–41,181–231). Seed nie nadpisuje istniejących kluczy config.
- **Zależności do sprawdzenia:** `src/B2BContractService.php`.
- **Granica dowodu:** Nie uruchomiono MySQL DDL ani migracji istniejącej bazy; ustalenie unikalności dotyczy kodu schematu.


### `src/RecruitmentAPI.php`

**Blob:** `9dc92c4856b01690d0c0bc1361cbd4847e6cfafd` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 337 linii.

- **Ustalenie:** POST handler wymaga Auth::isLoggedIn i CSRF, przekazuje Auth::getUserId do akcji; odczyty filtrują player_id, a hire/fire delegują do HRService (249–337). fireEmployee przy złapanym wyjątku wywołuje rollBack dla dowolnej aktywnej transakcji na PDO, choć nie jest jej właścicielem (184–205); scenariusz tylko gdy HR rzuci wyjątek.
- **Zależności do sprawdzenia:** `src/HRService.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP/cron; zależności sprawdzono punktowo.


### `src/Tick/PortSection.php`

**Blob:** `73bd66ab1b8549083e60800005b9a77ed66f62db` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 281 linii.

- **Ustalenie:** Kolejka dostaw max 10 wpisów, per-port throughput skalowany max(1,deltaHours), partial zostawia remainder, done potwierdza marine_deliveries (47–241). W braku transakcji callera metoda commituje stan kolejki przed odrębnym zapisem magazynu w PlayerTickProcessingTrait:320–359, więc przerwanie między nimi może zgubić kredyt dostawy. Bez testu awarii.
- **Zależności do sprawdzenia:** `src/Tick/PlayerTickProcessingTrait.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP/cron; zależności sprawdzono punktowo.


### `src/init.php`

**Blob:** `2cefc92ad87fb96ed736ecf2b47eeb8c2e660737` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 310 linii.

- **Ustalenie:** Ładuje usługi, autoloader, sesję z cookie HttpOnly/SameSite Lax/secure zależnym od HTTPS i nagłówki (37–178); trzy schematy bootstrap oraz ALTER well_config są zastrzeżone dla CLI (180–238), choć konstruktory innych usług mogą nadal wykonywać własne DDL. BankNegotiation ma guard brakujących traitów z opcache_invalidate.
- **Zależności do sprawdzenia:** `src/BankruptcyBootstrap.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP/cron; zależności sprawdzono punktowo.


### `src/AdminHub/PostActionsTrait.php`

**Blob:** `956a2e39ab0b3669c67cec58cb94c5ee807ebc3c` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 278 linii.

- **Ustalenie:** POST panelu admina obsługuje tworzenie i seed hubów, zmianę stanu, incydent, globalne parametry i staffing (18–276); seed_region count obcina tylko z góry do 50 i tworzy trzy typy per liczba, bez transakcji. Zakres Auth/CSRF leży w admin/logistics_hubs.php, nie w trait; akcji nie uruchamiano.
- **Zależności do sprawdzenia:** `admin/logistics_hubs.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP/cron; zależności sprawdzono punktowo.


### `src/Tick/PlayersSection.php`

**Blob:** `121da492d418b73dd7896d3367d06f5acf1282fb` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 316 linii.

- **Ustalenie:** run pobiera najwyżej maxPlayersPerRun najstarszych graczy mających storage, pomija bankrutów, łapie per-player wyjątki i wycofuje otwartą transakcję (56–108,116–173). applyOutboundLeg odejmuje straty i koszty od akumulatorów, a queueHubDeliveredInputs rozdziela wolumeny hub/direct (205–315); szczegóły commitu w PlayerTickProcessingTrait.
- **Zależności do sprawdzenia:** `src/Tick/PlayerTickProcessingTrait.php`.
- **Granica dowodu:** Bez wykonania PHP/MySQL/HTTP/cron; zależności sprawdzono punktowo.


### `src/LogisticsPage/ViewData/OverviewAndProtectionData.php`

**Blob:** `4c19d44af1213b5559efe2c6a4fb6a71eacb75d7` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 325 linii.

- **Ustalenie:** Widok logistyki oblicza miks i efektywność, pobiera huby owned/rented, rynek z regionów gracza, incydenty, kursy i ochronę; dla road trips pobiera całą listę getActiveTripsForPlayer i dopiero kroi stronę w PHP (198–207). Dane ochrony hubu pokazują tylko owned, nie rented (284–321).
- **Zależności do sprawdzenia:** `src/LogisticsPageController.php`.
- **Granica dowodu:** Statycznie; bez wykonania PHP/DB/HTTP i bez E2E.


### `src/TechnicalPage/DataTrait.php`

**Blob:** `cd2a8ba75c76ee4095c0b59b5834d4dd17deb0d2` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 348 linii.

- **Ustalenie:** safeLoad zwraca fallback przy błędach, ale normalizeWellStatuses podczas budowy widoku aktualizuje statusy odwiertów, a loadActiveDisasters zamyka wpisy skażenia/blowout przed SELECT (105–155,256–324). GET strony technicznej ma więc skutki uboczne niezależnie od processTick; zakres transakcji nie jest tu wspólny.
- **Zależności do sprawdzenia:** `src/TechnicalPageController.php`.
- **Granica dowodu:** Statycznie; bez wykonania PHP/DB/HTTP i bez E2E.


### `src/Employee/HubStaffingManagementService.php`

**Blob:** `492e426d4f0771c642a86e5a4dce9c3b5fe62b16` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 311 linii.

- **Ustalenie:** Buduje obsadę i kandydatów hubu z alokacją i statusem relacji; assignToHub wymaga roli hub_operator, resztę walidacji i scope deleguje do EmployeeAssignmentService (34–176). Widok bierze hubCards od callera; sam nie dowodzi własności huba. Fallback summary określa throughput 0.6 i ryzyko 1.75 (285–310).
- **Zależności do sprawdzenia:** `src/Employee/EmployeeAssignmentService.php`.
- **Granica dowodu:** Statycznie; bez wykonania PHP/DB/HTTP i bez E2E.


### `src/TechnicalPage/ActionsTrait.php`

**Blob:** `bff0866de180279b7ae1c2de7dc3e35cf46a1dcf` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 328 linii.

- **Ustalenie:** POST strony technicznej wymaga CSRF, dispatchuje zadania, rekrutację, szkolenie i powiadomienia; AJAX wybiera odpowiedź JSON na X-Requested-With (5–84). Rekrutacja wielokrotna wykonuje do trzech osobnych requestRecruitment i może zakończyć się częściowym sukcesem bez atomowej grupy (154–225).
- **Zależności do sprawdzenia:** `src/TechnicalPageController.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP/cron, bez uruchamiania resetu.


### `src/Bankruptcy/EventsTrait.php`

**Blob:** `bdba3b1e1ed2a6c6d3ca244e62d8e964e3cca6de` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 235 linii.

- **Ustalenie:** tickBankruptcyFlow podczas getState uruchamia przeterminowane krytyczne zdarzenia: może zająć odwiert, pobrać karę lub obniżyć credit_score (12–80). Po 8h może wylosować nowe zdarzenie. Likwidacyjny reset jest odrębną transakcją i usuwa zajęte odwierty (124–195); metod nie uruchamiano.
- **Zależności do sprawdzenia:** `src/Bankruptcy/StateTrait.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP/cron, bez uruchamiania resetu.


### `src/Hub/ConfigTrait.php`

**Blob:** `fcc4e4f597d95741d1285c11f75cbe776d6e4cca` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 350 linii.

- **Ustalenie:** HubService::__construct wywołuje ensureHubSchema, które wykonuje addColumnIfMissing i przy systemowych hubach typu new/lease=0 masowy UPDATE acquisition_type, condition i lease (12–130; src/HubService.php:35–41). Konstruktor może więc wykonać DDL i modyfikację rynku podczas zwykłego odczytu WWW; brak guard transakcji w trait.
- **Zależności do sprawdzenia:** `src/HubService.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP/cron, bez uruchamiania resetu.


### `src/RiskScoreEngine.php`

**Blob:** `c38dbeb6f1790925d5d2d95fbf2d55741e16818c` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 439 linii.

- **Ustalenie:** calculateRiskScore sumuje osiem składowych i ogranicza 0–115; market używa globalnego aktywnego trendu, historia kredytu punktuje paid/defaulted, a awaria odczytu credit_score daje 5 punktów zamiast przerwać (35–62,279–438). Wymaga MySQL składni SUM(status=...) i tłumaczeń.
- **Zależności do sprawdzenia:** `src/MarketTrend.php`.
- **Granica dowodu:** Statycznie; bez uruchomienia PHP/MySQL/HTTP.


### `src/GameShell.php`

**Blob:** `8f8983b42407995a23e050558c1a56f9da2032b8` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 320 linii.

- **Ustalenie:** statusItems inicjalizuje WalletService przed Player::getData, więc pasek stanu może uruchomić ensureSchema/DDL przy odczycie (6–31). Dalej pobiera rynek i liczniki odwiertów z fallbackami; menu filtrowane przez BoardAccess, ale puste przy awarii DB (141–231).
- **Zależności do sprawdzenia:** `src/WalletService.php`.
- **Granica dowodu:** Statycznie; bez uruchomienia PHP/MySQL/HTTP.


### `src/Hub/AdminActionsTrait.php`

**Blob:** `6abde9a5bde3d63a84d42dfde746f7285264cf3c` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 373 linii.

- **Ustalenie:** Admin operations build/repair/upgrade/pause/rename/status/condition i shared setWorkMode. Dla gracza setWorkMode sprawdza owner/tenant i ponownie filtruje UPDATE, dla adminOverride pomija filtr (201–260). Uprawnienie admina musi zapewnić caller; sam trait go nie weryfikuje.
- **Zależności do sprawdzenia:** `src/HubService.php`.
- **Granica dowodu:** Statycznie; bez uruchomienia PHP/MySQL/HTTP.


### `src/GeologicalLayerService.php`

**Blob:** `8464b69e9667e28d3e0e33748113d073408a075b` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 414 linii.

- **Ustalenie:** switchLayer kontroluje ownership, status i koszt, stosuje własną transakcję tylko przy braku nadrzędnej; pobiera opłatę przed UPDATE wells, ale UPDATE nie filtruje statusu seized ani nie sprawdza rowCount (245–345). Przy zmianie statusu do seized między SELECT a UPDATE może zostać opłata bez poprawnej zmiany warstwy; warunkowe, bez testu race.
- **Zależności do sprawdzenia:** `src/PlayerPaymentService.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP, bez testu współbieżności.


### `src/HR/RecruitmentTrait.php`

**Blob:** `210bfbe660271b3fa9deffc4c08cb0e779554242` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 358 linii.

- **Ustalenie:** startRecruitment blokuje wiersz player FOR UPDATE, limituje pending i zapisuje nowy request w transakcji (68–178). processReadyRecruitmentBatch wybiera pending, a następnie atomowo claimuje każdy w osobnej transakcji; przy zero kandydatów ustawia cancelled, inaczej generuje i powiadamia (227–356). Nie uruchamiano ticka.
- **Zależności do sprawdzenia:** `src/CandidateGenerator.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP, bez testu współbieżności.


### `src/WellStaffService.php`

**Blob:** `89c0538fee991648e490e28001aa45460a8a4a27` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 386 linii.

- **Ustalenie:** unassign nie sprawdza role: każda wartość inna niż operator zeruje technician_id, choć unassignRole filtruje według przekazanej wartości (170–195). WellStaffApi.php:95–101 przekazuje niezweryfikowaną wartość z POST po Auth i CSRF; dlatego uwierzytelniony gracz może rozłączyć technika błędną rolą. Statycznie, bez HTTP.
- **Zależności do sprawdzenia:** `src/WellStaffApi.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP, bez testu współbieżności.


### `tests/MySqlIntegration/MySqlTransactionRacesTest.php`

**Blob:** `d8f6767508ceed83c3833a71fea504b92b689cb9` · **Status:** `pełny odczyt statyczny; nie uruchomiono` · **Odczyt:** 633 linii.

- **Ustalenie:** Asercje MySQL dla wyścigów sprzedaży/ofert/spłat/pozwoleń/zakupu odwiertu, rollbacków po triggerze i izolacji fixture (73–475). setUp wymaga guard DB, ale tworzy wiersze, schematy i zmienia SESSION sql_mode; withFailureTrigger robi CREATE/DROP TRIGGER (477–502), para procesów uruchamia PHP_BINARY (564–621). Nie uruchamiać na bieżącym DB.
- **Zależności do sprawdzenia:** `tests/fixtures/TransactionRaceFixtures.php`.
- **Granica dowodu:** Nie wykonano PHPUnit/MySQL ani DDL; wymagana fizycznie osobna baza i zgoda przed uruchomieniem.


### `src/WorldMap.php`

**Blob:** `1d95e5a938086fcb4c532918e23c303c16946484` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 490 linii.

- **Ustalenie:** buyWellAtLocation uruchamia WorldMapSchema::ensure przed transakcją, blokuje player i location, sprawdza zajęcie, limit 10 i permit; charge i INSERT wells są w transakcji z finally rollback na wczesnym return (178–379). getLocations wywołuje seeder podczas odczytu, getMapData sprawdza occupancy w osobnym zapytaniu dla każdej lokalizacji (89–115,452–463). Test MySQL race odczytano, nie wykonano.
- **Zależności do sprawdzenia:** `src/WorldMapSchema.php`.
- **Granica dowodu:** Bez PHP/MySQL/HTTP i testu race; wyniki testu nie są potwierdzone wykonaniem.


### `src/MarketOffer.php`

**Blob:** `85c106f9c12c86ab8422361cf0c5f2578de6caf1` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 520 linii.

- **Ustalenie:** Konstruktor ensureSchema tworzy market_sale_history, oznacza schemaEnsured przed DDL i połyka błąd (13–63). createOffer blokuje gracza i odejmuje oil warunkowym UPDATE; cancel i execute rewalidują status w transakcji, a execute loguje sprzedaż (65–155,242–466). Test MySQL race odczytano, nie wykonano.
- **Zależności do sprawdzenia:** `src/FinancialTransactionService.php`.
- **Granica dowodu:** Bez PHP/MySQL/HTTP i testu race; wyniki testu nie są potwierdzone wykonaniem.


### `tests/Integration/WellProductionHandlerTest.php`

**Blob:** `95ceff63d82a63bf652a6d0c44de2323e49dad4e` · **Status:** `pełny odczyt statyczny; testu nie uruchomiono` · **Odczyt:** 939 linii.

- **Ustalenie:** Asercje SQLite dla owner-filter w processOpex/Production, kumulatorów kosztów, klucza region_tax_rate, strat rurociągu i storage przy leak (199–939). Część testów owner izolacji (road/marine/reservoir/accident) wykonuje ręcznie napisany SQL, nie ścieżkę WellProductionHandler; leak test 50 losowych wywołań nie wymusza leak, tylko brak bezpośredniego UPDATE storage niezależnie od wyniku.
- **Zależności do sprawdzenia:** `src/Tick/WellProductionHandler.php`.
- **Granica dowodu:** Nie wykonano PHPUnit/SQLite; mocki omijają Database singleton, nie dowodzą pełnego ticka ani MySQL.


### `src/Tick/WellProductionHandler.php`

**Blob:** `81430e0cc7a72e145ee1f009af7dad0e98ae1872` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 943 linii.

- **Ustalenie:** OPEX, port/rurociąg gate, produkcja, koszt i strata transportowa oraz zdarzenia mają rozdzielone ścieżki MySQL road/marine i fallback SQLite (126–858). Road dispatch przy min_load bierze bufferBbl z nieblokowanego snapshotu well (475–509), a dispatchTrips tylko INSERT bez blokady (src/RoadTransportService.php:295–354); dwa nakładające się ticki mogą wysłać ten sam wolumen i obciąć bufor przez GREATEST. Marine path czyta świeży bufor FOR UPDATE (650–679). Warunkowe bez testu race.
- **Zależności do sprawdzenia:** `src/RoadTransportService.php`.
- **Granica dowodu:** Bez uruchomienia PHP/MySQL/cron i równoległego ticka; testy SQLite pomijają MySQL road dispatch.


### `src/WellStaffApi.php`

**Blob:** `a5f23fa8540e9efd43e658b22c33f047fa4e8858` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 375 linii.

- **Ustalenie:** Endpoint sprawdza Auth i CSRF dla POST, assign/unassign deleguje do WellStaffService; unassign przyjmuje dowolny niepusty role z POST i jest osiągalną ścieżką błędnego wyzerowania technician_id (56–101). Zmiana transportu blokuje owned well i aktualizuje po zakupie/bind pipeline w transakcji, outbound filtruje hub owner/tenant; konstruktory pipeline przed beginTransaction mogą wykonywać DDL (103–367).
- **Zależności do sprawdzenia:** `src/WellStaffService.php`.
- **Granica dowodu:** Statycznie; nie wykonano HTTP, PHP ani MySQL.


### `src/HR/MoraleServiceV2.php`

**Blob:** `7da39c136bf2757399f9c3fdf39692032ca044d5` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 299 linii.

- **Ustalenie:** MoraleService konstruktor odpala EmployeeSystemBootstrap::ensure, changeMorale blokuje state i aktualizuje version + event w transakcji, a w cudzej transakcji nie zarządza rollbackiem (17–24,80–149). calculateMetrics wylicza salary satisfaction, morale, leave/strike risk; persistCycleMetrics używa version i cycle guard (208–299).
- **Zależności do sprawdzenia:** `src/EmployeeSystemBootstrap.php`.
- **Granica dowodu:** Bez PHP/MySQL/HTTP; nie dowodzi integracji widoku ani transakcji pliku z DB.


### `src/PlayerMenuEditor.php`

**Blob:** `7f378f86ef4af3641ee81119dd6fc4eaae55be4e` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 262 linii.

- **Ustalenie:** save porównuje hash wersji z bieżącym menu pod blokadą player_nav_settings, waliduje grupy/elementy, edytuje w transakcji, przy wyjątku usuwa nowo zapisane ikonki (47–201). externalUrl dopuszcza wyłącznie HTTP(S), bez userinfo/port/fragment/IP/localhost; upload obrazu waliduje base64, rozmiar i deleguje MIME do PlayerNavIcon (12–27,211–236).
- **Zależności do sprawdzenia:** `src/PlayerNavIcon.php`.
- **Granica dowodu:** Bez PHP/MySQL/HTTP; nie dowodzi integracji widoku ani transakcji pliku z DB.


### `src/OffshoreTransportService.php`

**Blob:** `6c4cb556cc0fe3bb2c41a13ffa52fb283cc622c4` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 399 linii.

- **Ustalenie:** Konstruktor uruchamia ensureSchema, a znacznik per PDO ustawiany jest przed dwoma CREATE TABLE; awaria drugiego DDL pozostawi przy kolejnym wywołaniu na tym samym obiekcie pominięty bootstrap (42–123). processTick jest bezpośrednim modelem rejsów fallback, losuje straty per rejs i nalicza koszt wszystkich rejsów, log incydentów połyka błędy (219–382). Nie dowodzi modelu MarineDelivery.
- **Zależności do sprawdzenia:** `src/Tick/WellProductionHandler.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP ani uruchomienia ticka.


### `src/TTS/ManagerTrait.php`

**Blob:** `e3a6bc8f921d3394f489824bf26d666445268102` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 354 linii.

- **Ustalenie:** getManager filtruje player_id; getHSEBonus liczy obciążenie z odwiertów, przypisanych hubów i rurociągów, pojemność safety_officer/engineer, bonus audytu z 72 h i procedur BHP (12–354). Przy błędzie SQL zwraca fallback, potencjalnie po częściowo uzupełnionej tablicy, bez oznaczenia niekompletności dla callera.
- **Zależności do sprawdzenia:** `src/TTS/StaffTrait.php`.
- **Granica dowodu:** Statycznie; bez PHP/MySQL/HTTP ani uruchomienia ticka.


### `tests/Integration/TTSSecurityTest.php`

**Blob:** `4395097db2967c052cc8972714b42f96d9234e52` · **Status:** `pełny odczyt statyczny; testu nie uruchomiono` · **Odczyt:** 756 linii.

- **Ustalenie:** Test SQLite obejmuje cancelTask izolujące kolejkę, upgradeProcedures, hireEngineer, incident ownership, fault-injection ukończenia zadania i wiele wariantów utraty celu przy promocji queue (277–756). „Race” upgradeProcedures w l. 597–623 to ręcznie zapisany UPDATE po wcześniejszej zmianie poziomu, nie dwie równoległe transakcje ani wywołanie metody; osobny test metody sprawdza saldo/poziom.
- **Zależności do sprawdzenia:** `src/TechnicalTeamService.php`.
- **Granica dowodu:** Nie uruchomiono PHPUnit/SQLite ani MySQL; syntetyczny schemat i mocki serwisów nie dowodzą pełnego ticka.


### `src/TTS/ProceduresTrait.php`

**Blob:** `0552f8c86f5a2ce8030c7a4b6ea2a43b111e4a70` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 435 linii.

- **Ustalenie:** upgradeProcedures waliduje HSE i audyt, po blokadzie gracza pobiera opłatę i zmienia poziom z guardem; własna transakcja/SAVEPOINT (46–204). repairProcedureIntegrity oblicza newIntegrity przed transakcją i pobraniem opłaty, a UPDATE nie ma warunku starej integralności (210–320): dwa równoległe żądania mogą pobrać opłatę dwukrotnie za ten sam wzrost; statyczne, bez testu race. processProcedureDecay cofa integralność według deltaHours (327–380).
- **Zależności do sprawdzenia:** `src/TechnicalTeamService.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; współbieżność i rollback są hipotezami na podstawie kodu.


### `src/Incident/RepairDataTrait.php`

**Blob:** `f423a4fd1a8526662c1078c43333f4ba11358d53` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 418 linii.

- **Ustalenie:** repairIncident sprawdza właściciela, potem oznacza incydent repaired i zmienia odwiert w transakcji, lecz UPDATE nie ma repaired_at IS NULL; równoległe naprawy mogą ponowić halving boost medium (18–109). applyEffects w cudzej transakcji nie robi rollback po błędzie i połyka wyjątek (300–403), więc caller może zatwierdzić częściowy efekt; statyczne, bez fault injection. getRecentIncidents filtruje player_id.
- **Zależności do sprawdzenia:** `src/IncidentService.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; współbieżność i rollback są hipotezami na podstawie kodu.


### `src/TechnicalTeamService.php`

**Blob:** `fff38eaea110cf07bd43715a6b5c2ce7638e5cf6` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 491 linii.

- **Ustalenie:** Definiuje katalog specjalizacji/zadań, pozwala na override kosztów i godzin z TaskConfigService; getTasksCatalog cache statyczny per proces (51–323). Konstruktor wywołuje EmployeeSystemBootstrap i ensureHubTaskSchema, który dodaje kolumny i może ALTER enum na MySQL; flagę hubTaskSchemaEnsured ustawia przed DDL, a błędy enum łapie (372–491). Odczyt/instancjowanie serwisu może więc mutować schemat.
- **Zależności do sprawdzenia:** `src/TTS/TasksTrait.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; współbieżność i rollback są hipotezami na podstawie kodu.


### `src/HubApi.php`

**Blob:** `1661891d10613cd67b58782d4ac3f348addbde62` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 436 linii.

- **Ustalenie:** Adapter /api/internal/HubApi.php ładuje ten endpoint; POST wymaga CSRF i zalogowania, GET nawigacyjny .htaccess przekierowuje (65–99). Admin-check używa function_exists("Auth::isAdmin") i method_exists("Auth","hasRole"), których Auth.php nie definiuje; adminOnlyActions zawsze 403 w tej wersji (79–89). Każde żądanie, także odczytowe, uruchamia migrateExistingAssignmentsToTenancy (93–100); rzeczywisty efekt migracji wymaga analizy serwisu.
- **Zależności do sprawdzenia:** `api/internal/HubApi.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; zachowanie na żywo i ryzyka równoległości pozostają niepotwierdzone.


### `api/internal/HubApi.php`

**Blob:** `1afc979f6ba0c63dfbd6e7d158a04347c4ec656a` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 6 linii.

- **Ustalenie:** Sześcioliniowy adapter bez własnej logiki, require src/HubApi.php; żądania XHR nie są odrzucane ogólną regułą /src, ponieważ adapter leży pod /api/internal; nawigacyjne GET do /api/internal są przekierowane przez .htaccess (58–64).
- **Zależności do sprawdzenia:** `src/HubApi.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; zachowanie na żywo i ryzyka równoległości pozostają niepotwierdzone.


### `api/internal/WellStaffApi.php`

**Blob:** `0e360e029fdfe936a7aff446c8fda882241830ef` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 6 linii.

- **Ustalenie:** Sześcioliniowy adapter bez własnej autoryzacji, require src/WellStaffApi.php; punkt wejścia /api/internal z GET nawigacyjnym przekierowanym przez .htaccess, XHR pozostawione do obsługi wewnętrznej.
- **Zależności do sprawdzenia:** `src/WellStaffApi.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; zachowanie na żywo i ryzyka równoległości pozostają niepotwierdzone.


### `src/HR/EmployeeDepartureService.php`

**Blob:** `c16f30827ebc5b4dedb13bbef59e5f81c17a0365` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 386 linii.

- **Ustalenie:** processCycle wybiera nieukończone claims i pod blokadą gracza oraz employee_state nalicza streak lub ustawia leaving, kończy claim (22–185). processDue finalizuje pracownika po notice, zwalnia zadania, role i przypisania, a event deduplikuje (75–94, 188–375). W cudzej transakcji nie wywołuje commit/rollback; wywołujący odpowiada za rollback.
- **Zależności do sprawdzenia:** `src/HR/EmployeeDeadlockRetry.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; zachowanie na żywo i ryzyka równoległości pozostają niepotwierdzone.


### `src/TTS/RecruitmentTrait.php`

**Blob:** `6694e84c54a51c82c17f85fabc99221915527a91` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 417 linii.

- **Ustalenie:** Kandydaci filtrowani po player_id, dziale i terminie, review zapisuje UPSERT (34–147); requestRecruitment sprawdza limity 2/spec i 6/łącznie, po czym w transakcji wstawia żądanie bez blokady gracza lub strażnika limitu (228–415). Dwie równoległe transakcje MySQL mogą odczytać tę samą pojemność i przekroczyć limit — ryzyko warunkowe, nie przetestowano race.
- **Zależności do sprawdzenia:** `src/TechnicalTeamService.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; zachowanie na żywo i ryzyka równoległości pozostają niepotwierdzone.


### `src/CompanyCredibilityService.php`

**Blob:** `2c88be6e621a1a9f546cc32d7680f0b1e0c1da38` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 466 linii.

- **Ustalenie:** Konstruktor zapewnia schemat poza transakcją MySQL, lecz ustawia schemaEnsured przed DDL i połyka błąd (62–149). changeScore blokuje rekord MySQL, ale COMMIT następuje przed logChange, który połyka wyjątki (374–465): aktualizacja może pozostać bez obowiązkowej historii; applyCleanOperationBonus sprawdza istnienie bonusu przed blokadą (268–304), więc równoległe uruchomienia mogą przyznać go dwa razy. Statycznie, bez DB.
- **Zależności do sprawdzenia:** `src/FinancialTransactionService.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL/HTTP; zachowanie na żywo i ryzyka równoległości pozostają niepotwierdzone.


### `src/HubAcquisitionService.php`

**Blob:** `58257c0729ad9f9581543c3e8483cbb2b70fa632` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 501 linii.

- **Ustalenie:** buyNew/buyUsed/rent/upgradeOwned pobierają opłatę przed mutacją huba, refundują przy błędzie (36–355). buyUsed odczytuje tenant_player_id przed pobraniem opłaty, ale UPDATE kontroluje tylko player_id=0 (129–180): w wyścigu z rent może skasować najem innego gracza po jego transakcji. migrateExistingAssignmentsToTenancy jest wywoływane na każdym żądaniu HubApi i modyfikuje huby, wybierając gracza z największą liczbą przypisanych odwiertów (365–414).
- **Zależności do sprawdzenia:** `src/HubApi.php`.
- **Granica dowodu:** Nie wykonano MySQL/HTTP ani race; skutek dla depozytu/wynajmu wymaga reprodukcji w izolowanej bazie.


### `tests/.htaccess`

**Blob:** `3b1d5b99087b57bcd2ac6b0acd321dcbd3bd97ff` · **Status:** `pełny odczyt statyczny; bez testu Apache` · **Odczyt:** 14 linii.

- **Ustalenie:** Lokalnie wyłącza rewrite, dopuszcza pliki PHP przez Allow from all i usuwa nagłówki X-Content-Type-Options/X-Frame-Options/X-XSS-Protection dla PHP/HTML (1–14). Główna .htaccess przekierowuje bezpośrednie /tests; rzeczywiste dziedziczenie reguł Apache i ekspozycja HTTP wymagają weryfikacji na serwerze.
- **Zależności do sprawdzenia:** `.htaccess`.
- **Granica dowodu:** Brak testu przeglądarkowego, HTTP/PHP i MySQL; test Python jest statycznym kontraktem tekstowym.


### `tests/Unit/test_technical_ui_contract.py`

**Blob:** `6b581e22bba78d7bcbeb201fbf89a26023e58af2` · **Status:** `pełny odczyt i uruchomiony test unittest` · **Odczyt:** 121 linii.

- **Ustalenie:** 11 testów unittest czyta pliki źródłowe i sprawdza dokładne stringi CSS/PHP/JS, istnienie ikon SVG, klucze pl/en/de oraz statyczny scope akordeonów; nie renderuje strony, nie oblicza CSS ani nie uruchamia PHP. Uruchomiono izolowanie: 11/11, exit 0.
- **Zależności do sprawdzenia:** `templates/views/technical/main.php`.
- **Granica dowodu:** Brak testu przeglądarkowego, HTTP/PHP i MySQL; test Python jest statycznym kontraktem tekstowym.


### `src/HubAssignmentService.php`

**Blob:** `5d1a45b0c050212770467ab6e26d9b403b5acc03` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 519 linii.

- **Ustalenie:** assignWell i transferWell blokują MySQL wiersze wells/hubs/assignment i ponawiają walidację w transakcji; transfer używa SAVEPOINT w transakcji callera, dostęp do huba i permit po locku (43–121,233–381,435–465). Assign nie pobiera access_fee (0). hasLocalPermitOrNotRequired uznaje brak tabeli LUB kolumny (42S22) za brak systemu zezwoleń i dopuszcza przypisanie (471–517); przy drift schematu może niezamierzenie fail-open.
- **Zależności do sprawdzenia:** `src/Hub/AssignmentValidationTrait.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL ani testu wyścigów; brak kolumny i polityka fail-open wymagają potwierdzenia na izolowanym schemacie.


### `src/CandidateGenerator.php`

**Blob:** `e89ee336e6446b67477aa2cb518d35dd4126ac51` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 527 linii.

- **Ustalenie:** generateForRequest dobiera liczbę losowo według rzadkości i regionu; dla technical wymusza 1 kandydata, chyba że licznik już wynosi >=2/spec lub >=6/łącznie, liczony bez blokady (79–199). cleanupExpired usuwa globalnie do limitu najstarszych wygasłych kandydatów, bez player_id/request_id (507–525); test MySqlRecruitmentFlowTest wykonuje ten cleanup w transakcji, ale suite bazowa i inne testy nadal nie są odizolowane od wspólnej bazy.
- **Zależności do sprawdzenia:** `tests/MySqlIntegration/MySqlRecruitmentFlowTest.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL; losowość i realne zakresy danych niepotwierdzone. Nie uruchamiać cleanupExpired na bazie graczy.


### `src/Employee/EmployeeSystemConfigService.php`

**Blob:** `03120fe04169a3cd4ff3cd76d04cc761fa4a4458` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 286 linii.

- **Ustalenie:** Konstruktor uruchamia EmployeeSystemBootstrap::ensure oraz seedDefaults dla całego katalogu kluczy, więc pozorny odczyt może zapisywać globalną konfigurację i uruchamiać bootstrap (85–90,222–232). save waliduje typ/zakres i relacje, ale porównuje old z per-obiektowym cache i wykonuje UPDATE tylko po config_key bez wersji/locku (129–177): współbieżni administratorzy mogą nadpisać zmianę i zwrócić mylący old. Test MySqlRecruitmentFlowTest tym serwisem modyfikuje globalne klucze.
- **Zależności do sprawdzenia:** `src/EmployeeSystemBootstrap.php`.
- **Granica dowodu:** Nie uruchomiono PHP/MySQL; wystąpienie race i wpływ DDL wymagają izolowanego testu.


### `src/Sabotage/SabotageSchema.php`

**Blob:** `c242657259cf07324bde4c940c8b339196f684d2` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 325 linii.

- **Ustalenie:** ensure nie działa wewnątrz transakcji, poza nią tworzy tabele/indeks i seeduje cztery opcje sabotażu; błędy są logowane i połykane, WeakMap ustawiany dopiero po pełnym sukcesie (12–41). CREATE TABLE MySQL definiuje UNIQUE dla code i efektu, ale nie deklaruje FK prób/logów do opcji/prób ani graczy (43–128); SQLite ma prostszy schemat bez tych indeksów i enumów (151–224). Migracja Laravel wymaga ustalenia polityki orphanów i różnic test/prod.
- **Zależności do sprawdzenia:** `src/SabotageService.php`.
- **Granica dowodu:** Nie uruchomiono MySQL/SQLite; brak FK jest obserwacją DDL tego serwisu, a nie dowodem braku osobnych migracji.


### `src/Protection/ProtectionSchema.php`

**Blob:** `2982a4995283df15171c204c98ac9c8436944ff8` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 358 linii.

- **Ustalenie:** ensure poza transakcją tworzy cztery tabele, aktywny guard UNIQUE i seed pięciu ochron (27–55,57–207,272–357). ensureMysqlActiveGuard w ramach inicjalizacji automatycznie zmienia status starszych duplikatów active na cancelled (zostawia MAX(id)), po czym próbuje ALTER kolumny/indeksu; wyjątki łapie i nie propaguje, a ensure może mimo to oznaczyć cache jako gotowy (209–264,46–49). Laravel musi wydzielić tę mutację historycznych danych do jawnej migracji i ustalić politykę depozytu/zwrotu.
- **Zależności do sprawdzenia:** `src/ProtectionService.php`.
- **Granica dowodu:** Nie uruchomiono DDL na MySQL ani testu duplikatów; nie potwierdzono obecności historycznych duplikatów.


### `src/HR/EmployeeDialogueTemplateService.php`

**Blob:** `01e838d8f729dd295ab8df21cca4f60a57741dd6` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 365 linii.

- **Ustalenie:** choose zapisuje domyślne szablony przy każdym odczycie, losuje z wagami i stara się unikać poprzednio użytych ID (113–135,281–364). render wybiera text_en tylko dla en, każdemu innemu locale (w tym de) daje polski; podmiana placeholderów nie escapuje wartości (140–150), więc bezpieczeństwo zależy od miejsca wyświetlenia. restoreSeededDefaults usuwa wszystkie rekordy z seed_key i odtwarza je, kasując edycje admina tych rekordów (152–156).
- **Zależności do sprawdzenia:** `src/HR/EmployeeNegotiationService.php`.
- **Granica dowodu:** Nie wykonano PHP/DB/renderu; nie stwierdzono XSS bez kontroli źródła placeholderów i sinku HTML.


### `src/Employee/LogisticsStaffingService.php`

**Blob:** `b6725e43eeeb3866f2e59906ab31f6bd98256109` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 394 linii.

- **Ustalenie:** Konstruktor uruchamia EmployeeSystemBootstrap i config, ładuje flagę employee_hub_staffing_enabled oraz wymagania per typ (26–45,287–351). hubStaffingForHubs grupuje po właścicielu/najemcy, liczy jedynie aktywnych operatorów poza strike/leaving/inactive, a multiplier ogranicza cap strajku (74–215). Sama metoda nie sprawdza runtimeEnabled; to obowiązek wywołującego. Brak obsady daje throughput 0.60, ryzyko incydentu 1.75 i maintenance 1.25, choć feature flag domyślnie false (365–380).
- **Zależności do sprawdzenia:** `src/Tick/WellHubSection.php`.
- **Granica dowodu:** Nie uruchomiono PHP/DB/ticku; zakres użycia flagi trzeba potwierdzić u wywołujących.


### `src/Tick/PlayerTickProcessingTrait.php`

**Blob:** `5947f27727436bc26b153a2a7eee12d3365101a7` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 437 linii.

- **Ustalenie:** processLockedPlayer działa w callbacku PlayerTickTransaction (PlayerTickBoundaryTrait), ogranicza deltaSeconds do 86400, obsługuje TTS/regional/wells/pipelines/marine/road/port/hub/spill w tej kolejności (8–320). Magazyn zapisuje różnicowo i potwierdza crediting trips w tej samej zewnętrznej transakcji MySQL; po tym rozlicza koszty i zapisuje finanse (320–427). Brak storage lub offline freeze kończy callback bez zwykłego przetwarzania; migracja nie może rozbić atomowej granicy ani pomylić delivery z producedBbl.
- **Zależności do sprawdzenia:** `src/Tick/PlayerTickBoundaryTrait.php`.
- **Granica dowodu:** Nie uruchomiono ticku, DB ani harmonogramu; same komentarze o odzyskiwaniu crediting nie stanowią testu awarii.


### `src/Hub/ViewHubsTrait.php`

**Blob:** `17fcdf60fa7aace8d046715ef729248794e2ca6f` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 441 linii.

- **Ustalenie:** getHubDetail izoluje właściciela/najemcę, getAssignableHubs filtruje huby po owner/tenant i wylicza usage_fee oraz acq_access_fee (130–236). Pole acq_access_fee jest wyświetlane przez logistics_hub_wells.js:72–88 jako jednorazowa opłata przy przypisaniu, lecz HubAssignmentService::assignWell:80–110 zapisuje access_fee_paid=0 i zwraca access_fee=0; statyczna sprzeczność komunikatu z naliczeniem, nie dowód dodatkowej opłaty. Gate permit traktuje unknown column jako brak modułu (375–410), analogicznie do serwisu przypisania.
- **Zależności do sprawdzenia:** `assets/js/logistics_hub_wells.js`.
- **Granica dowodu:** Nie renderowano UI ani nie wykonano HTTP/DB; faktyczny tekst potwierdzenia zależy od danych huba.


### `src/LogisticsService.php`

**Blob:** `07ae0a3dd649a77cf19c6e509f40b9a0f953f9e4` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 461 linii.

- **Ustalenie:** optimize wylicza rekomendacje dla odwiertów, pobiera stałą opłatę przez FTS w transakcji i aktualizuje transport, nawet gdy lista changes pusta; cooldown 5 min trzyma wyłącznie w sesji po commicie (68–247). Komentarz deklaruje never downgrade i min_cost=koszt jednostkowy, lecz chooseBest nie porównuje wyniku z obecnym typem, a score(min_cost) minimalizuje koszt całkowity, nie jednostkowy (251–295). Wiele równoczesnych sesji może niezależnie przejść cooldown; brak blokady wells przed wyliczeniem planu, UPDATE sprawdza tylko id/player_id.
- **Zależności do sprawdzenia:** `src/FinancialTransactionService.php`.
- **Granica dowodu:** Nie wykonano PHP/MySQL ani symulacji wielosesyjnej; wpływ odmiennych kosztów i konkurencyjności wymaga danych testowych.


### `api/internal/HRApi.php`

**Blob:** `b355ef8bb09e13fc105203f90c69e871d4086bd2` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 6 linii.

- **Ustalenie:** Adapter 6 linii wymaga src/HRApi.php; nie implementuje własnej autoryzacji/CSRF. Główna .htaccess przepuszcza AJAX do /api/internal (GET navigate przekierowuje), więc kontrola dostępu musi być w źródłowym endpointcie.
- **Zależności do sprawdzenia:** `src/HRApi.php`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; kontrola node --check sprawdza tylko składnię wybranych plików JS.


### `api/internal/LogisticsApi.php`

**Blob:** `33347b70f02a7c7eb52f08483f6b9a190744011a` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 6 linii.

- **Ustalenie:** Adapter 6 linii wymaga src/LogisticsApi.php; nie implementuje własnej autoryzacji/CSRF. URL jest domyślny w logistics_config.js, a .htaccess pozwala XHR /api/internal.
- **Zależności do sprawdzenia:** `src/LogisticsApi.php`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; kontrola node --check sprawdza tylko składnię wybranych plików JS.


### `assets/js/logistics_config.js`

**Blob:** `928ae51ad0f19942c9e5aa34ab40bca7275b9e08` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 46 linii.

- **Ustalenie:** Czyta JSON z logistics-client-config i ustawia globalne URL/CSRF/locale dla optymalizatora, hubów, rurociągów i ochrony. Bez węzła lub przy błędnym JSON skrypt kończy się bez inicjalizacji; ścieżki API fallback są pod /api/internal lub /public. node --check exit 0.
- **Zależności do sprawdzenia:** `templates/views/logistics/main.php`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; kontrola node --check sprawdza tylko składnię wybranych plików JS.


### `assets/js/admin_credibility.js`

**Blob:** `2e6c9738ffd6fb65393d428f5c6e10e641d4cb8a` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 70 linii.

- **Ustalenie:** Modal korekty wiarygodności wpisuje playerId do pola hidden i nazwę przez textContent, obsługuje anulowanie/tło/Escape. Walidacja identyfikatora i uprawnień musi być po stronie formularza backendu; brak sinku innerHTML w tym pliku. node --check exit 0.
- **Zależności do sprawdzenia:** `src/CompanyCredibilityService.php`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; kontrola node --check sprawdza tylko składnię wybranych plików JS.


### `assets/js/logistics_hub_market.js`

**Blob:** `5b7bf2f7574bd7499a45711d2b1c5527efbfeb66` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 99 linii.

- **Ustalenie:** Obsługuje zakup/wynajem/nową budowę przez Hub.hubPost, pokazuje potwierdzenie cen z dataset, błędy no_hub_permit oraz reload po sukcesie. buyUsed/rent nie blokują przycisku podczas POST; buyNew blokuje go dopiero po asynchronicznym potwierdzeniu, więc wielokrotne żądania wymagają ochrony idempotencji po stronie serwera. node --check exit 0.
- **Zależności do sprawdzenia:** `src/HubAcquisitionService.php`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; kontrola node --check sprawdza tylko składnię wybranych plików JS.


### `assets/js/logistics_pipeline_staffing.js`

**Blob:** `858fa8ad09d3c2bffa615141c3f386cead669031` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 177 linii.

- **Ustalenie:** Składa modal obsady z escape HTML dla nazw i atrybutów, liczy wolną alokację z config.candidates i active_assignments oraz wysyła formularze assign/release z CSRF. Po potwierdzeniu wywołuje form.submit(), co omija interaktywną walidację input number; backend musi walidować allocation_pct i własność. node --check exit 0.
- **Zależności do sprawdzenia:** `src/Employee/EmployeeAssignmentService.php`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; kontrola node --check sprawdza tylko składnię wybranych plików JS.


### `assets/js/logistics_hub_browser.js`

**Blob:** `1be6a271efedcdfe4bd2d2b706186c8c6b3766f6` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 100 linii.

- **Ustalenie:** Deleguje kliknięcia dla zakupu/najmu/przypisania/transferu, przełącza regiony i odświeża stronę po wygaśnięciu cooldown (1–100). Licznik tworzy Date z data-cooldown-until (74–78), a szablon unassigned_wells_section.php:17–35 podaje surowy DATETIME bez offsetu; przy różnych strefach klienta i serwera odliczanie może się rozjechać mimo poprawnego porównania PHP. node --check exit 0.
- **Zależności do sprawdzenia:** `templates/views/logistics/sections/unassigned_wells_section.php`.
- **Granica dowodu:** Nie uruchomiono przeglądarki ani sprawdzenia strefy serwera; rozjazd jest warunkowy.


### `src/HR/EmployeeRaiseRequestService.php`

**Blob:** `568e393bf110987c0cc9f4eb21eb59137e900810` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 472 linii.

- **Ustalenie:** accept/negotiate/reject/postpone używają tokenu 16–128, klucza hash SHA-256, ponawiają kontrolę idempotencji po locku player/request i zapisują public_result w employee_events w jednej własnej transakcji (87–287,365–450). Jeśli deadline minął, wywołują expireRaiseRequest, COMMIT, a następnie zgłaszają wyjątek — odmowa akcji świadomie utrwala zmianę statusu (203–209). Przy wejściu w transakcję callera serwis nie robi savepoint/rollback po wyjątku; caller musi wycofać zmiany.
- **Zależności do sprawdzenia:** `src/HR/EmployeeRaiseRequest/DecisionTrait.php`.
- **Granica dowodu:** Nie wykonano PHP/DB; test idempotencji i rollbacku na dwóch połączeniach pozostaje otwarty.


### `src/Training/TrainingService.php`

**Blob:** `18d0d0633c58ea872c0057fb16c87ef0f7a7a9b6` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 477 linii.

- **Ustalenie:** enroll waliduje gracza/program/cooldown, obciąża przez FTS i zapisuje szkolenie w transakcji (42–139). runExam warunkowo przejmuje in_progress, zapisuje wynik i skill w transakcji; dopiero po COMMIT wystawia certyfikat i wysyła powiadomienie, połykając wyjątki (176–316). processFinishedExams wybiera wyłącznie in_progress, więc awaria certyfikatu po commit może pozostać bez retry i historii certyfikatów mimo passed (145–168). Próg 101-passChance przy losowaniu 1–100 jest spójny z podaną szansą.
- **Zależności do sprawdzenia:** `src/Tick/TrainingSection.php`.
- **Granica dowodu:** Nie wykonano PHP/DB ani fault injection; brak retry certyfikatu to statyczny wniosek z selekcji statusów.


### `assets/js/urgent_offer_alert.js`

**Blob:** `e174adf0e3006db569888b1bea8f9675a3258813` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 13 linii.

- **Ustalenie:** closeUrgentAlert ustawia animację i usuwa alert po 300 ms; automatyczne zamknięcie po 15 s. Bez tokenów/HTTP.
- **Zależności do sprawdzenia:** `.htaccess`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; node --check potwierdza składnię JS, nie zachowanie strony.


### `assets/js/admin_dashboard_flash.js`

**Blob:** `ad114ee849a3d61feb48b4dd5eb9c3deda7d799e` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 15 linii.

- **Ustalenie:** DOMContentLoaded przetwarza alerty w toasty, jeśli showGameToast istnieje; pagehide usuwa wszystkie pasujące alerty nawet gdy funkcja toast nie była dostępna. Kryterium type łączy kilka klas.
- **Zależności do sprawdzenia:** `.htaccess`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; node --check potwierdza składnię JS, nie zachowanie strony.


### `assets/js/language_switcher.js`

**Blob:** `4b1632b67028aed541a38976fa45d9665598ace7` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 17 linii.

- **Ustalenie:** Po zmianie select[data-language-switcher] wykonuje form.submit(); wymaga poprawnego action/metody/CSRF w szablonie, a sama logika nie waliduje locale.
- **Zależności do sprawdzenia:** `.htaccess`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; node --check potwierdza składnię JS, nie zachowanie strony.


### `assets/js/admin_protection.js`

**Blob:** `b78fe30533b336e43a64a2fdea3a078645c8cddf` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 23 linii.

- **Ustalenie:** Przechwytuje submit form[data-confirm] i używa confirmSubmit, a gdy go brak, wywołuje form.submit() bez potwierdzenia oraz omija interaktywną walidację HTML. Backend musi mieć autoryzację i CSRF.
- **Zależności do sprawdzenia:** `.htaccess`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; node --check potwierdza składnię JS, nie zachowanie strony.


### `assets/js/logistics_ui.js`

**Blob:** `9a21ef3326dd5837c8aaca634bc4069a53f24915` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 23 linii.

- **Ustalenie:** Ustawia width dla data-progress-width z Number ograniczonym do [0,100]; inicjalizuje przy DOMContentLoaded lub od razu i po ajax-pagination:updated. NaN pozostaje NaN przy Math.max/min, więc niepoprawna wartość dataset da style.width="NaN%".
- **Zależności do sprawdzenia:** `assets/js/logistics_config.js`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; node --check potwierdza składnię JS, nie zachowanie strony.


### `assets/js/admin_logistics_hubs.js`

**Blob:** `96a7ff26997396867392254c2ab25e514066ac43` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 27 linii.

- **Ustalenie:** Przycisk seed-region-submit otwiera confirmAction i wysyła form.submit po akceptacji; używa nieguardowanego globalnego confirmAction, więc brak modal.js zablokuje seeding. Nie dowodzi wykonywania na stronie bez kontroli jej include.
- **Zależności do sprawdzenia:** `assets/js/logistics_config.js`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; node --check potwierdza składnię JS, nie zachowanie strony.


### `assets/js/template_editor_nav.js`

**Blob:** `ae39b4e949afc565e7b7e774c5a6db00da8c5843` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 27 linii.

- **Ustalenie:** Przegląda picker ikon, tworzy blob URL dla wybranego pliku i odwołuje go przy zmianie/pagehide; preview.src z select.selectedOptions[0] wymaga faktycznie wybranej opcji i węzła preview. Bez serwerowego uploadu.
- **Zależności do sprawdzenia:** `.htaccess`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; node --check potwierdza składnię JS, nie zachowanie strony.


### `assets/js/map_locations.js`

**Blob:** `9971efd1f8e49a71798afbb57a64cc9238b8c30d` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 29 linii.

- **Ustalenie:** Globalne openEdit ustawia pola formularza z obiektu loc przez value (bez innerHTML), pokazuje modal; closeEdit i klik w tło ukrywają go. Autoryzacja i walidacja współrzędnych poza skryptem.
- **Zależności do sprawdzenia:** `.htaccess`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; node --check potwierdza składnię JS, nie zachowanie strony.


### `assets/js/dashboard_controls.js`

**Blob:** `62002e8df827bdbada6b81fd4a97f6dbe97c3173` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 33 linii.

- **Ustalenie:** Wybór karty roli i regionu zapisuje dataset do ukrytych pól i klas is-selected; gdy brakuje regionInput/regionGrid, obsługa roli nadal działa. Backend musi walidować ID i region.
- **Zależności do sprawdzenia:** `.htaccess`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; node --check potwierdza składnię JS, nie zachowanie strony.


### `assets/js/admin_legal.js`

**Blob:** `96191556fdffd35db7c13d6e31f03f3d6f21e059` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 36 linii.

- **Ustalenie:** Przechwytuje submit w capture, po confirmAction używa requestSubmit, a dopiero przy jego braku form.submit; gdy confirmAction brak, zatrzymuje wysyłkę. Tokeny/zgody wymagają walidacji backendowej.
- **Zależności do sprawdzenia:** `.htaccess`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; node --check potwierdza składnię JS, nie zachowanie strony.


### `assets/js/admin_help.js`

**Blob:** `178c92deb740481dabc677f9fdd812d347c253ec` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 37 linii.

- **Ustalenie:** Inicjalizuje TinyMCE w #ah-tinymce-content z language pl i ciemnym style; po init na submit #ahEditForm wywołuje triggerSave. Wymaga globalnego tinymce i uwzględnienia innych locale w migracji.
- **Zależności do sprawdzenia:** `.htaccess`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; node --check potwierdza składnię JS, nie zachowanie strony.


### `api/internal/AdminNewsApi.php`

**Blob:** `b5c72e5bd7d8ed6342d9418ea0e37f6baf6bb0cc` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 6 linii.

- **Ustalenie:** Sześcioliniowy publiczny adapter bez własnej walidacji; require src/AdminNewsApi.php. Dla XHR /api/internal root .htaccess nie stosuje bezpośredniego redirectu /src. Ochronę CSRF/rolę trzeba prześledzić w implementacji.
- **Zależności do sprawdzenia:** `src/AdminNewsApi.php`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; node --check potwierdza składnię JS, nie zachowanie strony.


### `api/internal/BlackMarketApi.php`

**Blob:** `6375239278e38c384e48695023c8cce31db741b0` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 6 linii.

- **Ustalenie:** Sześcioliniowy publiczny adapter bez własnej walidacji; require src/BlackMarketApi.php. Dla XHR /api/internal root .htaccess nie stosuje bezpośredniego redirectu /src. Ochronę CSRF/rolę trzeba prześledzić w implementacji.
- **Zależności do sprawdzenia:** `src/BlackMarketApi.php`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; node --check potwierdza składnię JS, nie zachowanie strony.


### `api/internal/DmApi.php`

**Blob:** `651c8ac51db234d249bdf528bac083c9b251c0c3` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 6 linii.

- **Ustalenie:** Sześcioliniowy publiczny adapter bez własnej walidacji; require src/DmApi.php. Dla XHR /api/internal root .htaccess nie stosuje bezpośredniego redirectu /src. Ochronę CSRF/rolę trzeba prześledzić w implementacji.
- **Zależności do sprawdzenia:** `src/DmApi.php`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; node --check potwierdza składnię JS, nie zachowanie strony.


### `api/internal/PipelineApi.php`

**Blob:** `bbdb21470d686606e8182a77072e1f352e0b7151` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 6 linii.

- **Ustalenie:** Sześcioliniowy publiczny adapter bez własnej walidacji; require src/PipelineApi.php. Dla XHR /api/internal root .htaccess nie stosuje bezpośredniego redirectu /src. Ochronę CSRF/rolę trzeba prześledzić w implementacji.
- **Zależności do sprawdzenia:** `src/PipelineApi.php`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; node --check potwierdza składnię JS, nie zachowanie strony.


### `api/internal/RecruitmentAPI.php`

**Blob:** `43241e7d25af2cf4083cbeaeed5a1097bde78777` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 6 linii.

- **Ustalenie:** Sześcioliniowy publiczny adapter bez własnej walidacji; require src/RecruitmentAPI.php. Dla XHR /api/internal root .htaccess nie stosuje bezpośredniego redirectu /src. Ochronę CSRF/rolę trzeba prześledzić w implementacji.
- **Zależności do sprawdzenia:** `src/RecruitmentAPI.php`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; node --check potwierdza składnię JS, nie zachowanie strony.


### `api/internal/TechNotifApi.php`

**Blob:** `4683609db59cad29e59f699ffdd5b3fb698a53fd` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 6 linii.

- **Ustalenie:** Sześcioliniowy publiczny adapter bez własnej walidacji; require src/TechNotifApi.php. Dla XHR /api/internal root .htaccess nie stosuje bezpośredniego redirectu /src. Ochronę CSRF/rolę trzeba prześledzić w implementacji.
- **Zależności do sprawdzenia:** `src/TechNotifApi.php`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; node --check potwierdza składnię JS, nie zachowanie strony.


### `api/internal/WellSellApi.php`

**Blob:** `72c4594ee1d58bb42b1b7a370d12a0fef5b2e3cb` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 6 linii.

- **Ustalenie:** Sześcioliniowy publiczny adapter bez własnej walidacji; require src/WellSellApi.php. Dla XHR /api/internal root .htaccess nie stosuje bezpośredniego redirectu /src. Ochronę CSRF/rolę trzeba prześledzić w implementacji.
- **Zależności do sprawdzenia:** `src/WellSellApi.php`.
- **Granica dowodu:** Nie wykonano HTTP/PHP/E2E; node --check potwierdza składnię JS, nie zachowanie strony.


### `assets/js/admin_hr.js`

**Blob:** `3caf05f476a610c07b4e8fd0f6b24da103e24fc2` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 37 linii.

- **Ustalenie:** Confirm dla formularzy i przycisków używa nieguardowanego confirmAction, a callback form.submit() omija submit handlers i interaktywną walidację; flash znika po 5 s.
- **Zależności do sprawdzenia:** `assets/js/modal.js`.
- **Granica dowodu:** Brak uruchomienia PHP/HTTP, przeglądarki lub E2E; node --check potwierdza wyłącznie składnię.


### `assets/js/contracts.js`

**Blob:** `62325a74757c90cf9f71521fdf9cecd739469cc6` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 43 linii.

- **Ustalenie:** Komunikat flash jest usuwany przed sprawdzeniem dostępności alertError/alertInfo/showGameToast, więc bez tych funkcji komunikat ginie; sam skrypt nie wykonuje akcji kontraktu.
- **Zależności do sprawdzenia:** `assets/js/modal.js`.
- **Granica dowodu:** Brak uruchomienia PHP/HTTP, przeglądarki lub E2E; node --check potwierdza wyłącznie składnię.


### `assets/js/help_editor.js`

**Blob:** `cbd1e930fb795d17c99cb682d7a0a15cf845bed3` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 44 linii.

- **Ustalenie:** Inicjalizacja TinyMCE obejmuje dwa pola PL/EN, ale language jest pl; callback init przypina triggerSave do #editForm bez kontroli istnienia formularza.
- **Zależności do sprawdzenia:** `assets/js/admin_help.js`.
- **Granica dowodu:** Brak uruchomienia PHP/HTTP, przeglądarki lub E2E; node --check potwierdza wyłącznie składnię.


### `assets/js/game_nav.js`

**Blob:** `93a0a5901edd68d08ba476d004f37dac1abacb3c` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 46 linii.

- **Ustalenie:** Grupowa nawigacja zmienia aria-expanded i hidden; Escape zamyka grupy, a klik linku także nav-open, lecz nie synchronizuje aria-label burgera jak header.js.
- **Zależności do sprawdzenia:** `assets/js/header.js`.
- **Granica dowodu:** Brak uruchomienia PHP/HTTP, przeglądarki lub E2E; node --check potwierdza wyłącznie składnię.


### `assets/js/auth.js`

**Blob:** `a2cd075ff98ba70458823b744dcbd2aaa684d57b` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 49 linii.

- **Ustalenie:** Sprawdzenie siły i zgodności hasła działa tylko w przeglądarce: wynik z pięciu cech, przycisk aktywny przy zgodności i min. ośmiu znakach; backend musi walidować politykę niezależnie.
- **Zależności do sprawdzenia:** `src/Auth.php`.
- **Granica dowodu:** Brak uruchomienia PHP/HTTP, przeglądarki lub E2E; node --check potwierdza wyłącznie składnię.


### `assets/js/pages_editor.js`

**Blob:** `63236dde7a93cfc4ff754e8cfaa53fc00940e7e5` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 49 linii.

- **Ustalenie:** TinyMCE #tinymce-content synchronizuje treść na submit #editForm; globalne tinymce jest konieczne, a brak formularza jest obsłużony.
- **Zależności do sprawdzenia:** `assets/js/help_editor.js`.
- **Granica dowodu:** Brak uruchomienia PHP/HTTP, przeglądarki lub E2E; node --check potwierdza wyłącznie składnię.


### `assets/js/emoji.js`

**Blob:** `3617603b59ebf1c7610020c1f898c5a05412a5b2` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 50 linii.

- **Ustalenie:** EmojiLib.parseEmojis podmienia mapę emotikonów regexami w surowym stringu; wynik wymaga właściwego escapingu kontekstu przy wyświetleniu HTML.
- **Zależności do sprawdzenia:** `assets/js/game.js`.
- **Granica dowodu:** Brak uruchomienia PHP/HTTP, przeglądarki lub E2E; node --check potwierdza wyłącznie składnię.


### `assets/js/tech_notifications.js`

**Blob:** `0b522fe096c930278d9079e915441ba837f53074` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 53 linii.

- **Ustalenie:** POST do TechNotifApi z _token z panelu; pending blokuje równoległe kliknięcia i przy błędzie pokazuje tekst z dataset; aktualizuje licznik DOM po sukcesie.
- **Zależności do sprawdzenia:** `api/internal/TechNotifApi.php`.
- **Granica dowodu:** Brak uruchomienia PHP/HTTP, przeglądarki lub E2E; node --check potwierdza wyłącznie składnię.


### `assets/js/dashboard_news.js`

**Blob:** `f721b829bda808d4a02351d004e418bcf2a14c71` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 55 linii.

- **Ustalenie:** Co 60 s pobiera do 3 newsów z AdminNewsApi; title_html i content_html wstawia przez innerHTML bez lokalnego sanitizer; źródło API sanitizuje je przez AdminNewsHtml. Migracja musi zachować tę granicę zaufania.
- **Zależności do sprawdzenia:** `src/AdminNewsApi.php`.
- **Granica dowodu:** Brak uruchomienia PHP/HTTP, przeglądarki lub E2E; node --check potwierdza wyłącznie składnię.


### `assets/js/admin_bank_panel.js`

**Blob:** `5ce21e60191e193c4e3e668669cf2762df93c8df` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 59 linii.

- **Ustalenie:** Modal kredytu/debetu bierze player ID z dataset, ustawia ukryte action/id, zmienia etykietę i submit; nie waliduje operacji finansowej w JS.
- **Zależności do sprawdzenia:** `admin/bank.php`.
- **Granica dowodu:** Brak uruchomienia PHP/HTTP, przeglądarki lub E2E; node --check potwierdza wyłącznie składnię.


### `assets/js/market.js`

**Blob:** `b817cda71597b35c95a6cbe6009f63e591be6119` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 59 linii.

- **Ustalenie:** Confirm sprzedaży lub oferty liczy sumę z window.MARKET_PRICE i parseInt(amount); bez confirmAction formularz wysyła się bez potwierdzenia, a callback form.submit omija submit handlers.
- **Zależności do sprawdzenia:** `assets/js/modal.js`.
- **Granica dowodu:** Brak uruchomienia PHP/HTTP, przeglądarki lub E2E; node --check potwierdza wyłącznie składnię.


### `assets/js/admin_wells.js`

**Blob:** `93cf78f93a06bf8b40d756b72814d1d7702810cd` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 60 linii.

- **Ustalenie:** Zakładki i panele GM są lokalnym stanem DOM; aktywna zakładka w hash/parametrze jest ograniczona do pięciu znanych wartości, zapis hash przez history.replaceState.
- **Zależności do sprawdzenia:** `assets/js/game_nav.js`.
- **Granica dowodu:** Brak uruchomienia PHP/HTTP, przeglądarki lub E2E; node --check potwierdza wyłącznie składnię.


### `assets/js/header.js`

**Blob:** `ada7cfffc7efff90ae89dfe7570e352c36c5cdf1` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 60 linii.

- **Ustalenie:** Obsługuje burger nawigacji i timer trendu; wyświetla HH:MM z sekund, a po końcu 00:00; nie synchronizuje stanu nav z game_nav po kliknięciu linku.
- **Zależności do sprawdzenia:** `assets/js/game_nav.js`.
- **Granica dowodu:** Brak uruchomienia PHP/HTTP, przeglądarki lub E2E; node --check potwierdza wyłącznie składnię.


### `assets/js/admin_gm.js`

**Blob:** `e3cb04fc9979bb599fb5a699d4725f5e898b2b13` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 61 linii.

- **Ustalenie:** W paginacji przenosi selected[] w URL, a carried jest stały w obrębie strony. Widok PHP wkłada do carried wyłącznie ID spoza bieżącej strony, więc odznaczenie widocznego ID usuwa je z URL; test Node obejmuje to dla prostego fixture, ale nie pełną nawigację HTTP.
- **Zależności do sprawdzenia:** `tests/Unit/AdminGmPaginationFrontend.test.cjs`.
- **Granica dowodu:** Brak uruchomienia PHP/HTTP, przeglądarki lub E2E; node --check potwierdza wyłącznie składnię.


### `assets/js/dashboard.js`

**Blob:** `e2baba3e860f24489a656872487020a2cd4b54da` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 62 linii.

- **Ustalenie:** Hire/fire wysyła FormData z _token przez API_URL i używa confirmAction/promptInput; auto-odświeża stronę co 60 s przy widocznej karcie. Stałe są globals dostarczanymi przez widok.
- **Zależności do sprawdzenia:** `assets/js/modal.js`.
- **Granica dowodu:** Brak uruchomienia PHP/HTTP, przeglądarki lub E2E; node --check potwierdza wyłącznie składnię.


### `assets/js/admin_players.js`

**Blob:** `501a78d8baa4d3f2c81583ddf07185578688d125` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 81 linii.

- **Ustalenie:** Masowe zaznaczanie dotyczy tylko widocznych rowChecks; submit wymaga confirmSubmit, a gdy jej brak, preventDefault blokuje operację. Walidacja IDs i autoryzacja należą do serwera.
- **Zależności do sprawdzenia:** `assets/js/modal.js`.
- **Granica dowodu:** Brak uruchomienia PHP/HTTP, przeglądarki lub E2E; node --check potwierdza wyłącznie składnię.


### `assets/js/director_notifications.js`

**Blob:** `57775a32e077cd8a64eed86420184502d0141923` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 94 linii.

- **Ustalenie:** POST JSON do dwóch endpointów z csrf_token z data-csrf; usuwa po sukcesie element/lub przeładowuje stronę; wymaga globalnych confirmAction, alertError i MODAL_LANG.
- **Zależności do sprawdzenia:** `api/notifications/mark-read.php`.
- **Granica dowodu:** Brak uruchomienia PHP/HTTP, przeglądarki lub E2E; node --check potwierdza wyłącznie składnię.


### `assets/js/newsletter_editor.js`

**Blob:** `8ec392b9ab90639ff786e10182d61311c14038eb` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 94 linii.

- **Ustalenie:** Wywołuje tinymce.init na poziomie modułu; deklarowany fallback po DOMContentLoaded nie chroni przed brakiem globalnego tinymce (ReferenceError przed rejestracją callback). Wysyłka używa form.submit po confirmAction.
- **Zależności do sprawdzenia:** `assets/js/help_editor.js`.
- **Granica dowodu:** Brak uruchomienia PHP/HTTP, przeglądarki lub E2E; node --check potwierdza wyłącznie składnię.


### `assets/js/game.js`

**Blob:** `cbabc0a3363aa6b31c3d252cd11cf05c415bc12d` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 96 linii.

- **Ustalenie:** BFCache pageshow wymusza reload, timer co 5 min odświeża stronę; polling dm_status co 15 s ustawia badge i opcjonalny dźwięk. Sprzedaż wymaga globalnego confirmAction.
- **Zależności do sprawdzenia:** `api/internal/ChatApi.php`.
- **Granica dowodu:** Brak uruchomienia PHP/HTTP, przeglądarki lub E2E; node --check potwierdza wyłącznie składnię.


### `assets/js/admin_news_editor.js`

**Blob:** `f1b5b76898a3c4e877b2bed826c4f7ff032fb1ea` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 99 linii.

- **Ustalenie:** TinyMCE synchronizuje title/content, blokuje submit dla pustego tekstu; używa alertError bez guard, a backend wciąż musi walidować i sanitizować HTML.
- **Zależności do sprawdzenia:** `src/AdminNewsApi.php`.
- **Granica dowodu:** Brak uruchomienia PHP/HTTP, przeglądarki lub E2E; node --check potwierdza wyłącznie składnię.


### `assets/js/public_home.js`

**Blob:** `d51b6c691d67f092cf3f658207791610975bcf34` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 103 linii.

- **Ustalenie:** Menu i tabs aktualizują atrybuty ARIA/hidden, strzałki i Home/End obsługują klawiaturę; dialog screenshot przy zamknięciu usuwa src i przywraca fokus. Select języka używa requestSubmit.
- **Zależności do sprawdzenia:** `public/language.php`.
- **Granica dowodu:** Nie uruchomiono PHP/HTTP ani prawdziwej przeglądarki; kontrole Node/URL nie zastępują testu end-to-end.


### `assets/js/logistics_countdowns.js`

**Blob:** `8401a99178dc580cb5197a1a4d61a81f5d70e0bc` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 106 linii.

- **Ustalenie:** Pipeline parsuje DATETIME po podmianie spacji na T jako czas lokalny klienta; road liczy od chwili renderu; po zerze przeładowuje URL. Potencjalne odchylenie strefy przy terminie z serwera.
- **Zależności do sprawdzenia:** `public/logistics.php`.
- **Granica dowodu:** Nie uruchomiono PHP/HTTP ani prawdziwej przeglądarki; kontrole Node/URL nie zastępują testu end-to-end.


### `assets/js/admin_incidents.js`

**Blob:** `a1b1416d47b8a6863d34965fbfa2a34fbc2a949d` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 118 linii.

- **Ustalenie:** Przełącza zakładki w sessionStorage i na ajax-pagination:updated; selektory incydentów tworzą option przez textContent, bez wstrzyknięcia HTML. IDs pochodzą z danych widoku i wymagają walidacji serwera.
- **Zależności do sprawdzenia:** `assets/js/ajax_pagination.js`.
- **Granica dowodu:** Nie uruchomiono PHP/HTTP ani prawdziwej przeglądarki; kontrole Node/URL nie zastępują testu end-to-end.


### `assets/js/admin_news_list.js`

**Blob:** `de731cf8aed8af462ceba1cbf87c9de748c84d0f` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 125 linii.

- **Ustalenie:** Paginacja 5 kart jest po stronie klienta; sortuje pinned i datę ze stringów, filtruje normalizując diakrytyki. Wymaga wszystkich newsów w DOM i pól toolbar/search/sort.
- **Zależności do sprawdzenia:** `assets/js/dashboard_news.js`.
- **Granica dowodu:** Nie uruchomiono PHP/HTTP ani prawdziwej przeglądarki; kontrole Node/URL nie zastępują testu end-to-end.


### `assets/js/home_dashboard.js`

**Blob:** `7e1d4b7c6ba9b0f730e5cc6c1d980add940ccc7f` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 149 linii.

- **Ustalenie:** Wyniki statystyk fetch są wpisywane przez textContent, ranking budowany jako DOM; wykres pobiera chart_points z API. Po błędzie pokazuje komunikat, bez sprawdzenia semantyki wartości finansowych.
- **Zależności do sprawdzenia:** `assets/js/dashboard.js`.
- **Granica dowodu:** Nie uruchomiono PHP/HTTP ani prawdziwej przeglądarki; kontrole Node/URL nie zastępują testu end-to-end.


### `assets/js/ajax_pagination.js`

**Blob:** `056ecbeb52948b46369c1ff18b5b123fce9c3dea` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 163 linii.

- **Ustalenie:** Dla linków do tej samej ścieżki pobiera HTML XHR, podmienia korzeń widoku, publikuje ajax-pagination:updated i pushState. Skrypty związane z usuniętym DOM wymagają ponownej inicjalizacji po zdarzeniu.
- **Zależności do sprawdzenia:** `assets/js/logistics_countdowns.js`.
- **Granica dowodu:** Nie uruchomiono PHP/HTTP ani prawdziwej przeglądarki; kontrole Node/URL nie zastępują testu end-to-end.


### `assets/js/map_audio.js`

**Blob:** `01afaca706439ab6ab68bca50088ea90e6444228` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 164 linii.

- **Ustalenie:** Pobiera listę tracków z /assets/audio/list.php, tworzy Audio z URL, losuje kolejny utwór i zapisuje głośność w localStorage; brak widgetu w przypadku wyłączenia JS. Polityka autoplay i media runtime niezweryfikowane.
- **Zależności do sprawdzenia:** `assets/audio/list.php`.
- **Granica dowodu:** Nie uruchomiono PHP/HTTP ani prawdziwej przeglądarki; kontrole Node/URL nie zastępują testu end-to-end.


### `assets/js/wallet.js`

**Blob:** `f43e26982e67baf002b494d6ce9be25b5c4aa27e` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 197 linii.

- **Ustalenie:** Klient parsuje kwotę przez parseFloat (akceptuje prefiks numeryczny niepoprawnego ciągu), szacuje prowizję i wysyła FormData z csrf_token; serwer musi niezależnie walidować kwotę, prowizję i saldo. Brak confirmAction nie blokuje POST.
- **Zależności do sprawdzenia:** `public/wallet_transfer.php`.
- **Granica dowodu:** Nie uruchomiono PHP/HTTP ani prawdziwej przeglądarki; kontrole Node/URL nie zastępują testu end-to-end.


### `assets/js/black_market.js`

**Blob:** `197ca9e0776663e07b50733bd0c2561a82ce2d5c` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 199 linii.

- **Ustalenie:** Oferty/historia są pobierane co 30 s/na żądanie, sprzedaż POST z csrf_token. HTML karty osadza o.id i o.bbl w inline onclick bez escapingu JS; bezpieczne tylko przy gwarantowanym typie numerycznym danych API, do sprawdzenia na granicy.
- **Zależności do sprawdzenia:** `src/BlackMarketApi.php`.
- **Granica dowodu:** Nie uruchomiono PHP/HTTP ani prawdziwej przeglądarki; kontrole Node/URL nie zastępują testu end-to-end.


### `assets/js/admin_visits.js`

**Blob:** `8556dd38a640f6a9f82b31335bb17968c5940ce2` · **Status:** `pełny odczyt statyczny; node --check OK` · **Odczyt:** 276 linii.

- **Ustalenie:** Canvas wykresu PV/UV i tooltip; formularz czyszczenia po submit uruchamia cleanupForm.submit również przy braku confirmAction, więc potwierdzenie jest opcjonalne. Dane tooltip składane przez innerHTML z VISITS_DATA.
- **Zależności do sprawdzenia:** `admin/visits.php`.
- **Granica dowodu:** Nie uruchomiono PHP/HTTP ani prawdziwej przeglądarki; kontrole Node/URL nie zastępują testu end-to-end.


### `public/dm.php`

**Blob:** `35f79d659da54c25dd2a00891fcb30b3ac360a2a` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 14 linii.

- **Ustalenie:** Po requireLogin przekierowanie 301 do /chat, opcjonalnie z dodatnim integer with; stare adresy i status redirect zachować w trasach migracji.
- **Zależności do sprawdzenia:** `src/Auth.php`.
- **Granica dowodu:** Nie uruchomiono PHP/HTTP ani prawdziwej przeglądarki; kontrole Node/URL nie zastępują testu end-to-end.


### `public/logout.php`

**Blob:** `7208e49263af0447565a43d974f243a4ed382f52` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 22 linii.

- **Ustalenie:** Wywołuje Auth::logout w try/catch z GameLog; GET/route nie wymusza lokalnie POST ani tokenu CSRF. Ocenić politykę logout w Laravel, bez przypisywania incydentu.
- **Zależności do sprawdzenia:** `src/Auth.php`.
- **Granica dowodu:** Nie uruchomiono PHP/HTTP ani prawdziwej przeglądarki; kontrole Node/URL nie zastępują testu end-to-end.


### `public/mobile_bridge_login.php`

**Blob:** `a39abb3c6bb0b44acc5e711a57c5e92bf57910a8` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 24 linii.

- **Ustalenie:** GET token do 128 znaków przekazywany do MobileWebBridge::consume, potem Auth::loginByPlayerId; consume sprawdza token_hash, used_at i expires_at oraz atomowo oznacza użycie. Token URL może trafić do historii/logów; wymagane testy integracyjne przepływu.
- **Zależności do sprawdzenia:** `src/MobileWebBridge.php`.
- **Granica dowodu:** Nie uruchomiono PHP/HTTP ani prawdziwej przeglądarki; kontrole Node/URL nie zastępują testu end-to-end.


### `public/language.php`

**Blob:** `6478cdca0ba564816f52abadc8eb4cee94dd2362` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 30 linii.

- **Ustalenie:** POST + CSRF zmienia locale w sesji i cookie; redirect wymaga tylko prefiksu / i wyklucza //, ale nie /\host. Parser URL WHATWG rozpoznaje /\host jako host zewnętrzny (sprawdzenie Node); potencjalny open redirect przy kontrolowanym redirect i ważnym tokenie, bez testu HTTP.
- **Zależności do sprawdzenia:** `assets/js/language_switcher.js`.
- **Granica dowodu:** Nie uruchomiono PHP/HTTP ani prawdziwej przeglądarki; kontrole Node/URL nie zastępują testu end-to-end.


### `public/technical.php`

**Blob:** `dcc5eeff13f3f610d5983f607ded7c9f177f7e98` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 37 linii.

- **Ustalenie:** Wymaga logowania i uprawnienia BoardAccess; handlePost i render delegated do TechnicalPageController. Wyjątek w konstruktorze jest wypisywany przez die($e->getMessage()), co może warunkowo ujawnić treść błędu serwera.
- **Zależności do sprawdzenia:** `src/TechnicalPageController.php`.
- **Granica dowodu:** Nie uruchomiono PHP/HTTP ani prawdziwej przeglądarki; kontrole Node/URL nie zastępują testu end-to-end.


### `public/layer_well.php`

**Blob:** `7f8fe3b2b53d216d5e10bcf2777b5000daec31af` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 45 linii.

- **Ustalenie:** POST z CSRF i ID > 0 kieruje switchLayer do GeologicalLayerService z ID zalogowanego gracza; odpowiedź JSON 200/400/419/500. Autoryzacja własności musi pozostać w usłudze.
- **Zależności do sprawdzenia:** `src/GeologicalLayerService.php`.
- **Granica dowodu:** Nie uruchomiono PHP/HTTP ani prawdziwej przeglądarki; kontrole Node/URL nie zastępują testu end-to-end.


### `public/well_history.php`

**Blob:** `4931100f9e307252d89cfdd5190e2b049e3dfacb` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 51 linii.

- **Ustalenie:** GET z auth, no-store, stronicowanie WellHistoryService::page; null daje 404, mapuje znane kategorie i tłumaczy opis, bez zwracania surowych stack trace. Backend service odpowiada za zakres gracza.
- **Zależności do sprawdzenia:** `src/WellHistoryService.php`.
- **Granica dowodu:** Nie uruchomiono PHP/HTTP ani prawdziwej przeglądarki; kontrole Node/URL nie zastępują testu end-to-end.


### `public/privacy-policy.php`

**Blob:** `9bb9bad68a780ca99099cf382470b8663ddefbcf` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 52 linii.

- **Ustalenie:** Dla locale innego niż pl preferuje lokalny szablon, a dla pl aktywny content z bazy; policyContent jest emitowany jako HTML bez escapingu w widoku, co wymaga zaufanej sanitizacji przy zapisie.
- **Zależności do sprawdzenia:** `src/Privacy/PrivacyPolicyService.php`.
- **Granica dowodu:** Nie uruchomiono PHP/HTTP ani prawdziwej przeglądarki; kontrole Node/URL nie zastępują testu end-to-end.


### `public/verify_email.php`

**Blob:** `26223e9059a13a456f7e249186404377077d7198` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 52 linii.

- **Ustalenie:** Dla anonimowego użytkownika bierze token z GET po Validator::sanitize i wywołuje Auth::verifyEmail; zalogowany użytkownik jest przekierowany bez sprawdzenia tokenu. Treść błędu ucieka przez htmlspecialchars.
- **Zależności do sprawdzenia:** `src/Auth.php`.
- **Granica dowodu:** Nie uruchomiono PHP/HTTP ani prawdziwej przeglądarki; kontrole Node/URL nie zastępują testu end-to-end.


### `public/logistics.php`

**Blob:** `f50a6105d053074033497a690c3336ea295e9d98` · **Status:** `pełny odczyt statyczny` · **Odczyt:** 54 linii.

- **Ustalenie:** Wymaga login i BoardAccess logistykę, consume flash z sesji, deleguje POST do LogisticsPageController; ładuje zbiór CSS/JS i meta CSRF. W migracji zachować kolejność obsługi POST, flash, widoku i zależności JS.
- **Zależności do sprawdzenia:** `src/LogisticsPageController.php`.
- **Granica dowodu:** Nie uruchomiono PHP/HTTP ani prawdziwej przeglądarki; kontrole Node/URL nie zastępują testu end-to-end.

### `public/sell.php`

**Blob:** `82277025f39933e1e1cb13f88e3278e3e7b9e3b7` · **Status:** `pełny odczyt statyczny; opinia Qwen zweryfikowana przez GPT` · **Odczyt:** 60 linii.

- **Ustalenie:** Wiersze 8–16 sprawdzają login, rate limit i CSRF, lecz plik nie odczytuje `amount`. Wiersz 30 wywołuje `Storage::sellAll($currentPrice)`, wiersze 31–35 próbują zapisać uzyskany przychód, a 37 zatwierdza transakcję. Po wyjątku 38–43 następuje rollback i log, ale 45–46 przekierowują na `/` bez komunikatu o błędzie. `src/Storage.php:83–105` potwierdza wyzerowanie całego `used` w starej trasie; `.htaccess:91` mapuje `/sell` do tego skryptu. **Nie utożsamiać z aktualnym POST rynku przez `public/market.php`.**
- **Zależności sprawdzone punktowo:** `src/Storage.php`, `src/FinancialTransactionService.php`, `.htaccess`, `public/market.php`, `src/MarketSaleService.php`.
- **Granica dowodu:** Bez PHP/MySQL/HTTP; powodzenie `execute()` i transakcyjność w realnej bazie oraz osiągalność starej trasy na produkcji nie były testowane.

### `public/form_sell.php`

**Blob:** `448af59ade1d036df46cfd746b73676ee8058352` · **Status:** `pełny odczyt statyczny; opinia Qwen zweryfikowana przez GPT` · **Odczyt:** 66 linii.

- **Ustalenie:** Widoczny przy dodatnim `storageData.used` dawny formularz 18–31 wysyła `name="amount"` na `url('sell')`, podczas gdy formularz oferty 37–56 wysyła `action=create_offer` i `amount` na `url('market-offers')`. Pierwsza trasa ignoruje `amount` (`public/sell.php:24–30`). Przeszukanie PHP nie wskazało `include/require` tego pliku; aktualnie podłączony rynek korzysta z innego szablonu. Brak referencji w przeszukanym kodzie nie dowodzi braku każdej historycznej lub zewnętrznej ścieżki użycia.
- **Zależności sprawdzone punktowo:** `src/init.php:250–253`, `.htaccess:91,99`, `templates/views/market/main.php:51–73`, `public/sell.php`.
- **Granica dowodu:** Bez renderu i HTTP; aktywność starego fragmentu na serwerze produkcyjnym niepotwierdzona.

### `templates/components/form_sell.php`

**Blob:** `9bbff1f8eabe46978698f4bbccd131f99381af4d` · **Status:** `pełny odczyt statyczny; opinia Qwen zweryfikowana przez GPT` · **Odczyt:** 38 linii.

- **Ustalenie:** Komponent 16–37 renderuje jeden formularz POST bez atrybutu `action`, z ukrytą akcją w wierszu 19 oraz polem ilości 22–25. Widok `templates/views/market/main.php:51–73` osadza go dwukrotnie (`sell_instant`, `create_offer`); w pierwszym przypadku `public/market.php:33–44` przekazuje `amount` do `MarketSaleService::sellInstant`, które odejmuje tylko podaną ilość (`src/MarketSaleService.php:16–53`). Drugi formularz obsługuje `MarketOffer::createOffer`, którego zachowania nie weryfikowano w tej próbie.
- **Zależności sprawdzone punktowo:** `templates/views/market/main.php`, `public/market.php`, `src/MarketSaleService.php`; `MarketOffer::createOffer` zbadano w późniejszej kontroli opisanej w 6.7.
- **Granica dowodu:** Wniosek o routingu jest statyczny; nie wykonano renderu, POST, testu przeglądarkowego ani bazodanowego.

### `public/market.php`

**Blob:** `2434dd9071e5c9d1bcbbd14f2ea8c6083e414022` · **Status:** `pełny odczyt statyczny; opinia Qwen zweryfikowana przez GPT` · **Odczyt:** 166 linii.

- **Ustalenie:** Kontroler po auth, RateLimiter i CSRF rozróżnia `sell_instant`, `create_offer`, `edit_offer`, `cancel_offer` i B2B (25–93). Dla tworzenia oferty przekazuje `amount`/`limit_price` do `MarketOffer::createOffer` (46–56); po anulowaniu ponownie pobiera Storage (69–76). Wszystkie POST kończy flash i przekierowanie do `market.php` (95–100), więc ta ścieżka ma PRG. Widok `templates/views/market/main.php` korzysta z komponentu `form_sell.php` i jest renderowany przez `game_shell` (147–166); nie utożsamiać go z osobną `/market-offers`.
- **Zależności sprawdzone punktowo:** `src/MarketOffer.php`, `templates/views/market/main.php`, `templates/components/form_sell.php`, `.htaccess`, `assets/js/market.js`.
- **Granica dowodu:** Bez PHP/HTTP/MySQL; skutku zapisu oferty, przekierowania i flash w działającej aplikacji nie sprawdzono.

### `public/market_offers.php`

**Blob:** `a42b14c06196c85ca3aed217dd1962d411f4367f` · **Status:** `pełny odczyt statyczny; opinia Qwen zweryfikowana przez GPT` · **Odczyt:** 162 linie.

- **Ustalenie:** Osobny kontroler `market-offers` przyjmuje create/edit/cancel po RateLimiter+CSRF (23–66), ale po POST renderuje stronę bez redirectu. Po udanym anulowaniu linia 60 używa nieobecnego `returned_amount` z wyniku `MarketOffer::cancelOffer`, więc lokalny `storageData.used` nie uwzględnia już zwróconej ropy; formularz w wierszach 92–105 może pokazać nieaktualny limit. Serwis zapisuje zwrot ropy i commit; brak klucza wyniku nie dowodzi utraty danych. Formularz wystawienia oferty ma w HTML zakres ceny 50–500 (109–112), którego backend nie egzekwuje w identycznym zakresie.
- **Zależności sprawdzone punktowo:** `src/MarketOffer.php`, `templates/components/alert.php`, `src/i18n.php`, `.htaccess`, `tests/ci-schema.sql` (tylko definicja market_offers).
- **Granica dowodu:** Brak POST/HTTP/MySQL i testu odświeżenia; ponowienie żądania jest możliwe po potwierdzeniu resubmisji, nie automatycznie gwarantowane.

### `templates/views/market/main.php`

**Blob:** `980f37a8f81183d352164aa236bcf4c63869e4d2` · **Status:** `pełny odczyt statyczny; opinia Qwen zweryfikowana przez GPT` · **Odczyt:** 293 linie.

- **Ustalenie:** Aktywny widok rynku dołącza formularz `templates/components/form_sell.php` dwa razy — `sell_instant` (51–60) i `create_offer` (63–74). Formularze bez jawnego atrybutu `action` POST-ują do bieżącej strony, a `public/market.php:25–93` interpretuje akcje i wykonuje PRG; historia sprzedaży i B2B mają odrębne sekcje, a ceny/ilości w HTML nie zastępują walidacji backendu. `assets/js/market.js:12–45` dodaje potwierdzenie tylko przy dostępnej funkcji `confirmAction`.
- **Zależności sprawdzone punktowo:** `public/market.php`, `templates/components/form_sell.php`, `assets/js/market.js`, `src/MarketOffer.php`.
- **Granica dowodu:** Bez renderu HTML/JS w przeglądarce, POST i bazy; dostępność modułu B2B i końcowy wygląd widoku niepotwierdzone.

### `public/boardroom.php`

**Blob:** `4c8fb92a352b9ad993348ea3f02a07055768099a` · **Status:** `pełny odczyt statyczny przez nadzorcę; Qwen analizował wycinek` · **Odczyt:** 192 linie.

- **Ustalenie:** GET po `Auth::requireLogin()` pobiera aktywnych członków gracza (12–23), osobno jego rekrutacje `initiated_by='director'`, bez specjalizacji (25–49), i liczy kandydatów gracza (51–68); dla kandydatów z `player_id IS NULL` dodatkowo ogranicza powiązany wniosek do director/no spec (56–61), lecz dla bezpośrednio przypisanych kandydatów tych filtrów nie stosuje. Tablice `$boardMembers`, `$candidateCounts` i `$readyRecruitments` powstają jednorazowo przy renderowaniu (70–82,149–166); nie są aktualizowane przez sam polling `/boardroom-status`. Endpoint statusu ma szerszy filtr rekrutacji niż widok sali, więc jego `reload` nie zawsze oznacza nowe CV w sali.
- **Zależności sprawdzone punktowo:** `public/boardroom_status.php`, `assets/js/boardroom-dynamic.js:118–162,724–737`, `src/Tick/BankSection.php:178–190`, `src/HR/RecruitmentTrait.php:227–266`, `.htaccess:79–80`, `assets/js/recruitment.js:189–213`.
- **Granica dowodu:** Nie uruchomiono strony/SQL/HTTP; bez oceny rzeczywistej częstotliwości ticków i zachowania przeglądarki.

### `public/boardroom_status.php`

**Blob:** `aae80dd4ee2444c01631c7235e8272074c97c105` · **Status:** `pełny odczyt statyczny; Qwen sprawdzony przez nadzorcę` · **Odczyt:** 58 linii.

- **Ustalenie:** Uwierzytelniony JSON GET liczy `pending` z `ready_at <= NOW()` (16–25), aktywnych członków (27–34) i kandydatów (36–48); `reload` zależy wyłącznie od pierwszego licznika (50–55). W stanie `ready` i przy nowych kandydatach, ale bez `pending`, `reload=false`; przy utrzymującym się `pending` flaga pozostaje true. Filtry `initiated_by`/`spec_code` z kontrolera sali nie występują w zapytaniu statusu. Pozostałe pola JSON nie są używane przez polling tej strony (`boardroom-dynamic.js:728–730`).
- **Zależności sprawdzone punktowo:** `public/boardroom.php:25–82`, `assets/js/boardroom-dynamic.js:724–737`, `src/Tick/BankSection.php:178–190`, `src/HR/RecruitmentTrait.php:227–266`, `.htaccess:79–80`.
- **Granica dowodu:** Wniosek o możliwym pominięciu zmiany lub ponawianym reloadzie wynika z kodu i kolejności zdarzeń; bez zapytań HTTP/MySQL i pomiaru harmonogramu ticka.

### `templates/views/boardroom/main.php`

**Blob:** `3e70497ed23d4357c551b8bf4abdb5841d504482` · **Status:** `pełny odczyt statyczny; Qwen sprawdzony przez nadzorcę` · **Odczyt:** 52 linie.

- **Ustalenie:** Widok renderuje podsumowanie miejsc i gotowych rekrutacji (1–9), dane o składzie i rekrutacjach w początkowym `phpData` (36–49), po czym dołącza `boardroom-dynamic.js` i `recruitment.js` (51–52). Drugi skrypt ma polling własnego modalu zainicjowanej rekrutacji, lecz nie zastępuje odświeżenia początkowego `phpData` wykorzystywanego do oznaczeń miejsc w sali (`boardroom-dynamic.js:145–162`).
- **Zależności sprawdzone punktowo:** `public/boardroom.php:149–180`, `assets/js/boardroom-dynamic.js:118–162,724–737`, `assets/js/recruitment.js:189–213`.
- **Granica dowodu:** Bez wykonania renderu/JS; zewnętrzne assety i zdarzenia przeglądarki nie były testowane.

### `public/upgrade_storage.php`

**Blob:** `3a9a53c60421d964e362d582ae5f0737b0096ae8` · **Status:** `pełny odczyt statyczny; Qwen skorygowany przez nadzorcę` · **Odczyt:** 88 linii.

- **Ustalenie:** Kontroler wymaga loginu, rate limitu i CSRF, oblicza koszt magazynu oraz sprawdza `Player::canAfford` (6–52). Wspólny magazyn identyfikuje `playerId`; `well_id` jest użyty jedynie w logu (36,76–77,85). Po debicie 60–71 wywołuje `Storage::upgrade()` bez sprawdzenia `bool`, a następnie `commit()` i sukces (73–80). Jeżeli UPDATE w usłudze rzuci i zostanie zamieniony na `false`, istnieje warunkowa opłata bez powiększenia. Bankructwo jest blokowane już przez `canAfford`; `!$upgradeCost` w wierszu 42 myli także koszt zero z brakiem magazynu, nie dowodzi obciążenia.
- **Zależności sprawdzone punktowo:** `src/Storage.php:117–155`, `src/Player.php:110–167`, `src/PlayerPaymentService.php:28–36`, `src/FinancialTransactionService.php:220–274`.
- **Granica dowodu:** Bez PHP/SQL/HTTP i symulacji awarii; nie wykazano rzeczywistej utraty środków.

### `public/upgrade_well.php`

**Blob:** `3f74fdec34a352cf62cca40c871aeeb5365abda9` · **Status:** `pełny odczyt statyczny; Qwen skorygowany przez nadzorcę` · **Odczyt:** 134 linie.

- **Ustalenie:** JSON POST sprawdza auth, limiter i CSRF (25–40), odczytuje odwiert należący do gracza (42–66), po czym otwiera transakcję, debituje FTS i woła `Well::upgrade()` (80–96). Oba obiekty używają `Database::getInstance()->getConnection()`, a `Well::upgrade()` bezwarunkowo rozpoczyna kolejną transakcję (86). Przy zwykłym PDO drugie `beginTransaction()` jest niezgodne z aktywną transakcją; wyjątek łapie **zewnętrzny** catch usługi (Well:155–162), zwraca `false`; endpoint wycofuje własną transakcję w 91–93. Odrzucono stwierdzenie Maxa o pewnej podwójnej opłacie i nieosiągalności `err_failed`. Osobno, po usunięciu tej kolizji trzeba zapobiec podwójnemu debitowi w dwóch warstwach.
- **Zależności sprawdzone punktowo:** `src/Well.php:74–173`, `src/FinancialTransactionService.php:201–249,310–319`, `src/Database.php:67–78`, `src/Player.php:153–167`.
- **Granica dowodu:** Brak PHP/MySQL/HTTP; objaw runtime i zachowanie przy innym sterowniku wymagają izolowanego testu.

### `public/equipment_well.php`

**Blob:** `207e383ca5c22f698f8dbcc04d0484f96224a929` · **Status:** `pełny odczyt statyczny; Qwen skorygowany przez nadzorcę` · **Odczyt:** 81 linii.

- **Ustalenie:** POST JSON sprawdza login, limiter, CSRF, dodatni ID i akcję `set_tier`/`upgrade_level` (22–50). Własność jest kontrolowana w `WellService::getWell`, ale `set_tier` nie odrzuca statusu `sold` w `Well/ActionsTrait.php:14–51`; wiersz sprzedanego odwiertu pozostaje w DB i u pierwotnego gracza (`Well/SellTrait.php:157,220`), więc własny POST może ustawić `equipment_swap`. Przy ukończeniu tick normalizuje zapamiętane `sold` do `active` (`WellStatusHandler.php:33–45`). To odrębne od `upgrade_level`, które blokuje status `sold`.
- **Zależności sprawdzone punktowo:** `src/Well/QueryTrait.php:5–17`, `src/Well/ActionsTrait.php:12–153`, `src/Well/SellTrait.php:133–232`, `src/Tick/PlayerTickProcessingTrait.php:28–45`, `src/Tick/WellProductionSection.php:135–145`, `src/Tick/WellStatusHandler.php:33–45`.
- **Granica dowodu:** Statyczna ścieżka warunkowa od opłacenia zmiany i ticka; nie uruchomiono żądania ani bazy, bez potwierdzonego exploitu produkcyjnego.

### `templates/views/wells/equipment.php`

**Blob:** `ab964449f28c26cc26acad056f986b0588dc6b62` · **Status:** `pełny odczyt statyczny; Qwen sprawdzony przez nadzorcę` · **Odczyt:** 19 linii.

- **Ustalenie:** Widok pokazuje opcje tieru i poziomu (4–15), a `wwAction()` wysyła dane do `/public/equipment_well.php`. Powód `_blocked`/brak gotówki blokuje przycisk (12,14), ale nie jest walidacją samego endpointu. `wwAction` koduje payload w atrybucie HTML (`helpers.php:24–27`), a JS dodaje `csrf_token` do POST (`assets/js/well-workspace.js:85–93`). Należy zachować kontrakt i osobne warunki serwerowe przy migracji.
- **Zależności sprawdzone punktowo:** `templates/views/wells/helpers.php`, `assets/js/well-workspace.js`, `src/Well/ActionsTrait.php`, `public/equipment_well.php`.
- **Granica dowodu:** Bez renderu/przeglądarki/POST; disabled nie dowodzi, że arbitralny POST wystąpił.

### `public/map.php`

**Blob:** `4f1a35e3e82e6ff0bcab8b5ca1db9f9e4b69fc09` · **Status:** `pełny odczyt statyczny; Qwen skorygowany przez nadzorcę` · **Odczyt:** 91 linii.

- **Ustalenie:** GET ładuje dane mapy; POST `buy_well` po limiterze i CSRF przekazuje `location_id` do `WorldMap::buyWellAtLocation` i dla XHR zwraca JSON (23–65). Sukces ponownie pobiera mapę i dane gracza (43–44). Osobny komponent `well_shop_grid.php` przekazuje `well_id`, więc nie jest kontraktem tego kontrolera. Zależność `WorldMap.php:184–378` ma **finally** z rollbackiem otwartej transakcji (376–378): błędna teza z punktowego follow-up Maxa o pozostawionych transakcjach została odrzucona.
- **Zależności sprawdzone punktowo:** `src/WorldMap.php:178–378`, `.htaccess:97`, `templates/views/map/main.php`, `assets/js/world_map.js:655–715`, `templates/components/well_shop_grid.php`.
- **Granica dowodu:** Bez HTTP/SQL/renderu; nie potwierdzono skutku w bazie ani wykorzystania starego komponentu.

### `templates/components/well_shop_grid.php`

**Blob:** `4f93597c0689ee18b5ac681b1feb58855896906b` · **Status:** `pełny odczyt statyczny; Qwen sprawdzony przez nadzorcę` · **Odczyt:** 69 linii.

- **Ustalenie:** Formularz 47–63 ogranicza UI do pięciu odwiertów i wysyła `well_id` z akcją `buy_well` oraz CSRF bez atrybutu action. Aktualny `public/map.php:38–40` czyta `location_id`, a `WorldMap::buyWellAtLocation` egzekwuje limit dziesięciu i zezwolenie (225–239). Nie znaleziono statycznego include tego komponentu w bieżącej mapie — nie ogłaszać błędu aktywnej strony, lecz nie migrować fragmentu jako aktualnego formularza.
- **Zależności sprawdzone punktowo:** `public/map.php`, `templates/views/map/main.php`, `src/WorldMap.php:178–252`.
- **Granica dowodu:** Brak renderu i pełnego dowodu niedostępności na produkcji; nie zakładać użycia komponentu.

### `templates/views/map/main.php`

**Blob:** `362b9407cad2816be5733337897ef3c16c06aa0c` · **Status:** `pełny odczyt statyczny; Qwen sprawdzony przez nadzorcę` · **Odczyt:** 193 linie.

- **Ustalenie:** Widok emituje ukryte `csrf-token`, gotówkę i mapę JSON w escapowanym atrybucie (102–104), tłumaczenia MAP_LANG przez `json_encode` (110–190), po czym ładuje `world_map.js` (192). JS tworzy zakup przez fetch z `action=buy_well`, `location_id` i CSRF (655–715), zgodny z `public/map.php:23–65`. W dostarczonym przepływie nie wykazano źródła kontrolowanego wejścia prowadzącego do XSS; sam `json_encode` lub zewnętrzny asset nie są dowodem takiej podatności.
- **Zależności sprawdzone punktowo:** `public/map.php`, `assets/js/world_map.js:655–715`, `src/WorldMap.php`, `.htaccess:97`.
- **Granica dowodu:** Nie uruchomiono przeglądarki, assetów CDN ani żądania; analiza nie obejmuje wszystkich sinków pozostałego JS.

### `public/register.php`

**Blob:** `7241efa2787a9124fb61278f1d0835d3555bcfe1` · **Status:** `pełny odczyt statyczny; Qwen skorygowany przez nadzorcę` · **Odczyt:** 70 linii.

- **Ustalenie:** Po walidacji i sukcesie `Auth::registerPendingVerification()` konto niezweryfikowane jest już zapisane. Kontroler woła `Auth::sendVerificationEmail` (43), ignoruje wynik bool i ustawia `msg_verify_sent` (51), a logowanie odrzuca niezweryfikowane konto (`Auth.php:45–53`). Przy błędzie wysyłki może więc pokazać mylący sukces. `Auth::sendVerificationEmail` tworzy token przed wysyłką, dlatego twierdzenie Maxa „zawsze brak tokenu” jest błędne; sukces rejestracji z Auth zawiera `player_id` i `username` (245–251), więc ich hipotetycznego braku również nie wykazano.
- **Zależności sprawdzone punktowo:** `src/Auth.php:18–65,78–131,192–257`, `templates/views/public/register/main.php`, `templates/components/form_input.php`.
- **Granica dowodu:** Bez PHP/SMTP/HTTP, testu fałszywego wyniku mailera ani potwierdzenia niedoręczeń.

### `templates/views/public/register/main.php`

**Blob:** `374cc77d5ee8dbf5f3047d1bc5d3f64898d19a96` · **Status:** `pełny odczyt statyczny; Qwen sprawdzony przez nadzorcę` · **Odczyt:** 114 linii.

- **Ustalenie:** Po ustawieniu `$success` widok wyświetla komunikat weryfikacji i ukrywa formularz (18–25); ustawienie sukcesu następuje w kontrolerze niezależnie od `false` z wysyłki maila. Dane pól idą przez `templates/components/form_input.php:10` z `htmlspecialchars` wartości wejściowej; ostrzeżenie Maxa o braku escapingu etykiet/limitów nie identyfikuje kontrolowanego przez gracza źródła w tej ścieżce.
- **Zależności sprawdzone punktowo:** `public/register.php:35–51`, `src/Auth.php:78–131,245–251`, `templates/components/form_input.php`.
- **Granica dowodu:** Bez renderu i wysyłki maila; zachowanie przeglądarki i dostępność alternatywnej weryfikacji nie zostały sprawdzone.

### `public/recovery.php`

**Blob:** `f53a54f6c491387fad1aa3db614ba51d8d8ac799` · **Status:** `pełny odczyt statyczny; Qwen skorygowany przez nadzorcę` · **Odczyt:** 83 linie.

- **Ustalenie:** Po auth GET wywołuje `BankruptcyService::getState` (9–15), a potem `getRecoveryOptions` (44), które wywołuje getState ponownie. `getState` może ustawić recovery_mode i uruchomić tick zdarzeń (`StateTrait.php:10–27,31–48`), w tym zajęcie odwiertu przy terminie (`EventsTrait.php:18–68`). POST wymaga CSRF i ponownie odczytuje stan po akcji (20–44). To warunkowy zapis przy GET, nie dowód, że każda odsłona zajmuje odwiert.
- **Zależności sprawdzone punktowo:** `src/BankruptcyService.php`, `src/Bankruptcy/StateTrait.php:10–105`, `src/Bankruptcy/EventsTrait.php:12–116`, `src/Bankruptcy/OptionsTrait.php:13–42`, `templates/views/recovery/main.php`.
- **Granica dowodu:** Nie wywołano GET/POST ani ticka na bazie graczy; skutków i kolejności w runtime nie potwierdzono.

### `templates/views/wells/storage.php`

**Blob:** `0c74b7e06f2170b267f0aa1924b7ddd2d09af405` · **Status:** `pełny odczyt statyczny; Qwen sprawdzony przez nadzorcę` · **Odczyt:** 16 linii.

- **Ustalenie:** Karta odwiertu opisuje magazyn jako wspólny (6) i przy rozbudowie wysyła `well_id` do `upgrade_storage.php` (14). Serwer używa tego ID wyłącznie do logów, a `Storage::upgrade()` modyfikuje pojemność po `player_id` (117–133). Powód `_blocked` lub brak środków wyłącza przycisk w UI, lecz nie stanowi pełnej walidacji serwerowej; sprawdzenia funduszy i bankructwa istnieją osobno w `Player::canAfford`.
- **Zależności sprawdzone punktowo:** `templates/views/wells/helpers.php:24–27`, `public/upgrade_storage.php:36–80`, `src/Storage.php:117–139`, `src/Player.php:153–167`.
- **Granica dowodu:** Bez renderu/POST/DB; nie twierdzić, że arbitralny POST faktycznie zmienił magazyn.

### `templates/views/recovery/main.php`

**Blob:** `e1a147c45ea6d238bc6d1fb1c5d265f2dbd5da48` · **Status:** `pełny odczyt statyczny przez nadzorcę; Qwen analizował wycinki` · **Odczyt:** 247 linii.

- **Ustalenie:** Widok tworzy sześć form recovery z `CSRF::field()` i `action` (`sell_asset`, `bank_takeover`, `emergency_loan`, `cost_cuts`, `rescue_investor`, `new_start`), a przyciski są disabled wg `$options` (89–239). Teksty/zdarzenia z DB są escapowane w istotnych wyjściach (np. 40–47,63–77). Disabled jest podpowiedzią: `BankruptcyOptionsTrait::applyOption` sprawdza bankructwo, a metody szczegółowe powtarzają część warunków (np. credit_score w applyEmergencyLoan:220–223, minimalna pojemność w applySellStorageAsset:117–127). Nie ogłaszać ogólnego obejścia wszystkich akcji bez analizy każdej metody.
- **Zależności sprawdzone punktowo:** `public/recovery.php`, `src/Bankruptcy/OptionsTrait.php:13–42,105–160,218–267`, `src/Bankruptcy/StateTrait.php:55–105`.
- **Granica dowodu:** Bez renderu/POST/SQL; ocena wszystkich opcji bankructwa i testy reguł serwerowych pozostają otwarte.

### `templates/components/my_offers_table.php`

**Blob:** `7058f978354b40ea3b12f7a87d806c96b7d24d66` · **Status:** `pełny odczyt statyczny; Qwen zweryfikowany przez nadzorcę` · **Odczyt:** 96 linii.

- **Ustalenie:** Komponent jest dołączany w `public/market_offers.php:89` i bieżącym widoku `templates/views/market/main.php:79`. Dla własnych oczekujących ofert renderuje akcje edycji i anulowania (21–50), budujące dynamiczne formularze POST. `promptInput()` zwraca tekst wpisany przez bieżącego użytkownika (`assets/js/modal.js:328–354`), `parseFloat(newPrice)>=50` akceptuje prefiks liczbowy, a `editOffer` wkłada surowy `newPrice` do `form.innerHTML` w atrybucie value (65–77). W izolowanej przeglądarce tekst z prefiksem 50, końcem atrybutu i znacznikiem `img onerror` utworzył aktywny element; to **self-XSS/niebezpieczny sink**, nie dowód zdalnego wejścia innego gracza. Kontrolery rzutują cenę do `(int)`, ale dopiero po stronie serwera.
- **Zależności sprawdzone punktowo:** `assets/js/modal.js:328–354`, `templates/header.php:122,140`, `public/market.php:25–78`, `public/market_offers.php:23–65`, `src/MarketOffer.php:196–238`, `templates/views/market/main.php:75–81`.
- **Granica dowodu:** Próbę DOM wykonano na izolowanym HTML, nie na stronie produkcyjnej; nie wykazano zdalnego XSS ani wykonania PHP/HTTP aplikacji.

### `templates/components/offers_table.php`

**Blob:** `c7994302b5a7ecbca6459320768222bd96a43562` · **Status:** `pełny odczyt statyczny; Qwen sprawdzony przez nadzorcę` · **Odczyt:** 26 linii.

- **Ustalenie:** Komponent prezentuje datę, ilość i cenę ofert z `$offers` (11–26), bez formularza lub przycisku kupna. Przeszukanie aktualnych PHP/JS pod nazwą tego pliku nie wykazało literalnego dołączenia; obecny widok rynku dołącza `my_offers_table.php` zamiast niego. Nie przypisywać mu awarii akcji `buy` ani widoczności na produkcji bez osobnego dowodu.
- **Zależności sprawdzone punktowo:** `templates/views/market/main.php:75–81`, `public/market.php:106–107`, `public/market_offers.php:87–90`.
- **Granica dowodu:** Bez renderowania; dynamicznego lub historycznego użycia komponentu nie wykluczono.

### `templates/components/urgent_offer_alert.php`

**Blob:** `f3aef254bed1fca2a90ccc35b44000b06d988dc2` · **Status:** `pełny odczyt statyczny; Qwen uzupełniony przez nadzorcę` · **Odczyt:** 91 linii.

- **Ustalenie:** Dołączany na stronie głównej i osobnej stronie ofert, po braku `$myOffers`/`$marketData` wychodzi bez renderu (8–13); filtruje oczekujące oferty w zakresie ±5 od aktualnej ceny (15–28) i ładuje skrypt zamknięcia tylko przy niepustym alercie (41–90). Przycisk zarządzania ustawia względny `window.location.href='market_offers.php'` (80), z czystych tras `/` i `/market-offers` rozwiązuje się to do `/market_offers.php`; śledzony root nie ma pliku ani takiej reguły, podczas gdy `.htaccess:99` mapuje `/market-offers`. Wymaga sprawdzenia hostingu przed stwierdzeniem rzeczywistego 404.
- **Zależności sprawdzone punktowo:** `public/index.php:169,226`, `templates/views/index/main.php:51`, `public/market_offers.php:83`, `.htaccess:24–40,76–123`, `assets/js/urgent_offer_alert.js:5–13`.
- **Granica dowodu:** Bez HTTP/produkcji i bez potwierdzenia obecności starych root PHP na FTP; JS sprawdzono tylko `node --check`.

### `templates/components/alert.php`

**Blob:** `bf1f52bb6c24decc159e2cd3b675bc7c809f18ac` · **Status:** `pełny odczyt statyczny; Qwen skorygowany przez nadzorcę` · **Odczyt:** 21 linii.

- **Ustalenie:** Wiersze 9,16,19 emitują `$error` i `$success` jako HTML bez własnego escapingu. Podłączony w `public/market_offers.php:82` i widoku rynku `templates/views/market/main.php:46`; w analizowanych gałęziach komunikaty pochodzą ze stałych tłumaczeń lub wyników usług, bez wykazanego przepływu nieufnego tekstu gracza do sinka. `register.php` nie dołącza tego komponentu — Max błędnie wskazał je jako caller. W migracji ustalić jeden kontrakt tekst/bezpieczny HTML, zamiast zakładać globalną podatność XSS.
- **Zależności sprawdzone punktowo:** `public/market_offers.php:20–65,82`, `public/market.php:25–104`, `templates/views/market/main.php:46`, `src/i18n.php:40–70`.
- **Granica dowodu:** Bez renderu/HTTP i bez audytu wszystkich przyszłych callerów; XSS nieudowodnione.

### `templates/components/status_grid.php`

**Blob:** `66bd11297a09e59b3bb4d2554c229a05ea270769` · **Status:** `pełny odczyt statyczny; Qwen skorygowany przez nadzorcę` · **Odczyt:** 43 linie.

- **Ustalenie:** Wiersze 19,23,33 escapują niektóre atrybuty i tekst, natomiast `icon_html`, `label`, `class`, `value` są wypisywane surowo (24,27–30). W sprawdzonym `GameShell::statusItems` etykiety pochodzą z escapującego `t()`, wartości z formatowania liczb lub ustalonych prezentacji, ikony ze stałych metod (85–131); nie znaleziono nieufnego źródła. Jeśli caller nie ustawi `$statusItems`, wstępne sprawdzenie tylko loguje, a `foreach` na linii 9 nadal próbuje iterować niezdefiniowaną zmienną (ostrzeżenia, nie potwierdzony TypeError).
- **Zależności sprawdzone punktowo:** `src/GameShell.php:6–131`, `public/index.php:216–226`, `public/market_offers.php:70–84`, `src/i18n.php:40–50`.
- **Granica dowodu:** Bez PHP/renderu; nie przeanalizowano wszystkich niestandardowych callerów i nie wykazano XSS.

### `templates/components/form_input.php`

**Blob:** `34a5c9923e0699a12861d98016c17c3eb9ac462d` · **Status:** `pełny odczyt statyczny; Qwen sprawdzony przez nadzorcę` · **Odczyt:** 14 linii.

- **Ustalenie:** Komponent wypisuje `inputValue` przez `htmlspecialchars` (10), a pozostałe parametry id/name/type/placeholder/min/max i etykietę bez lokalnego escapingu (2–12). Znaleziony bieżący caller `templates/views/public/register/main.php:28–60` przypisuje stałe pola i wyniki `t()`, które escapuje (`src/i18n.php:40–50`); nie wykazano kontrolowanego przez gracza wstrzyknięcia do tych surowych pól. Przy migracji utrzymać kontekstowe escapowanie dla każdego parametru.
- **Zależności sprawdzone punktowo:** `templates/views/public/register/main.php:28–60`, `src/i18n.php:40–50`, `public/register.php:16–51`.
- **Granica dowodu:** Bez renderu/przeglądarki strony rejestracji, testu PHP i wszystkich ewentualnych dynamicznych callerów.

### `templates/components/card.php`

**Blob:** `f52ffa6cc0029abac43c7e5ed828be442558227a` · **Status:** `pełny odczyt statyczny; odpowiedź Maxa zweryfikowana przez nadzorcę` · **Odczyt:** 4 linie.

- **Ustalenie:** Wypisuje `$cardTitle` w `<h2>` i `$cardContent` w `<div>` bez własnego escapingu (1–4). Nie znaleziono literalnego dołączenia ani przypisania tych zmiennych w sprawdzonych śledzonych PHP, więc nie potwierdzono aktywnej ścieżki nieufnego źródła do wyjścia. Przy migracji jawnie określić kontrakt tekstu/HTML.
- **Zależności sprawdzone punktowo:** przeszukanie PHP pod `card.php` i `$cardTitle`.
- **Granica dowodu:** Brak renderu i runtime; nie wykluczono dynamicznych callerów, nie stwierdzono XSS.

### `templates/components/limit_alert.php`

**Blob:** `2bf4bf0f1d4e4540bf605795de91c476159091c4` · **Status:** `pełny odczyt statyczny; odpowiedź Maxa zweryfikowana przez nadzorcę` · **Odczyt:** 12 linii.

- **Ustalenie:** Jeżeli `GameLog` jest załadowany, brak `$showLimitAlert` loguje ostrzeżenie (2–6); warunkowe wyświetlenie `$limitMessage` nie escapuje wartości (8–11). Nie znaleziono literalnego dołączenia ani przypisania `$limitMessage` w aktualnych śledzonych PHP. To kontrakt komponentu, nie potwierdzony exploit.
- **Zależności sprawdzone punktowo:** przeszukanie PHP pod `limit_alert.php` i `$limitMessage`.
- **Granica dowodu:** Bez aktywnego callera, renderu i dowodu źródła nieufnego; XSS nieudowodnione.

### `templates/components/news_panel.php`

**Blob:** `1e30d3ccf8b74fd95037885ab543e4d7f9b5346b` · **Status:** `pełny odczyt statyczny; odpowiedź Maxa uzupełniona przez nadzorcę` · **Odczyt:** 11 linii.

- **Ustalenie:** Renderuje szkielet `newsList` oraz escapuje tekst atrybutów `data-empty` i `data-error` poprzez `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` (5–10); `t()` również escapuje w `src/i18n.php:40–50`. Jedyny literalny odczyt tego pliku w PHP to asercja tekstowa `tests/Unit/DashboardNewsWidgetTest.php:12,17–18`, nie render. **Nie wywodzić braku wiadomości na dashboardzie**: aktywny `templates/components/activity_center.php:55` zawiera alternatywny `newsList`, dołączany w `templates/views/index/main.php:120`, a `assets/js/dashboard_news.js:8–9` używa identyfikatora, nie nazwy pliku.
- **Zależności sprawdzone punktowo:** `src/i18n.php:40–50`, `tests/Unit/DashboardNewsWidgetTest.php:8–22`, `templates/views/index/main.php:120`, `templates/components/activity_center.php:55`, `assets/js/dashboard_news.js:8–9`.
- **Granica dowodu:** Bez testu renderu/HTTP; sam statyczny test PHPUnit nie sprawdza widoczności panelu.

### `templates/components/game_shell.php`

**Blob:** `ead9e4246024e5bb54a3f009f042e7e6b74192f1` · **Status:** `pełny odczyt statyczny; odpowiedź Maxa zweryfikowana przez nadzorcę` · **Odczyt:** 27 linii.

- **Ustalenie:** Komponent inicjalizuje brakujące `statusItems`/`actions` do pustych tablic (2–8), dołącza `status_grid.php` i `action_grid.php`, a środkowy widok przez `require $gameShellView` (20). `$gameShellTitleHtml` może być surowym HTML (15); alternatywny `$gameShellTitle` jest escapowany. Znalezione kontrolery przypisują `$gameShellView` do literalnych ścieżek opartych na `__DIR__`; `public/bank.php:107` ustawia jawny tytuł HTML z `tPlain('bank.title')`. Nie wykazano nieufnego wejścia do `require` ani HTML, zatem nie stwierdzono LFI/XSS.
- **Zależności sprawdzone punktowo:** przypisania i wywołania w `public/bank.php`, `boardroom.php`, `chat.php`, `contracts.php`, `dashboard.php`, `finance.php`, `hr.php`, `legal.php`, `logistics.php`, `map.php`, `market.php`, `sabotage.php`, `technical.php`; `src/i18n.php:60–70`.
- **Granica dowodu:** Bez uruchomienia PHP i bez oceny niejawnych/niestandardowych callerów; konieczne utrzymanie zamkniętej mapy ścieżek przy migracji.

### `public/wallet_transfer.php`

**Blob:** `fc2f4fe180e301854394449a86849ad1def62cf3` · **Status:** `pełny odczyt statyczny; Max skorygowany przez nadzorcę` · **Odczyt:** 73 linie.

- **Ustalenie:** Endpoint `/wallet-transfer` jest POST-only, wymaga logowania i CSRF (18–38), dopuszcza dwie akcje (50–54) i deleguje zmianę sald do `CashTransferService` (56–72). Normalizacja tekstu i bezpośrednie `(float)` (43–48) nie egzekwują ścisłego formatu liczbowego. Jednak pełny serwis `CashTransferService.php:85–253` waliduje zakres po zaokrągleniu, ponownie sprawdza saldo po `FOR UPDATE`, zapisuje transfer i wpis audytu w jednej własnej transakcji lub zwraca błąd; nie wynika z tego ujemny transfer ani podwójne pobranie. Ryzyko niejednoznacznego parsowania wejścia wymaga testu PHP/kontraktu UI; brak dowodu nadużycia środków.
- **Zależności sprawdzone punktowo:** `.htaccess:90`, `src/CashTransferService.php:85–253` łącznie z `catch`, `src/WalletConfig.php:28–62`, `assets/js/wallet.js:15,107`.
- **Granica dowodu:** Bez PHP, HTTP, DB i testu danych granicznych; nie uznano hipotez Maxa o utracie pieniędzy za potwierdzone.

### `public/opcache-reset.php`

**Blob:** `d8b6edc8861aa0f8664b6c03683a5252c2b167fd` · **Status:** `pełny odczyt statyczny; ustalenie Maxa zweryfikowane przez nadzorcę` · **Odczyt:** 68 linii.

- **Ustalenie:** Endpoint ładuje `OPCACHE_RESET_TOKEN` z `.env` bez uruchamiania aplikacji (23–41), akceptuje `$_GET['token']` przed nagłówkiem `X-Reset-Token` (43), odmawia przy braku konfiguracji i błędnej wartości przez `hash_equals` (51–60); przy sukcesie wywołuje `opcache_reset()` (62–68). Workflow wdrożenia wysyła token w query string (`.github/workflows/deploy-ftp.yml:350–354`). To ryzyko utrwalenia sekretu w logach URL, nie dowód istniejącego przecieku. Nie oglądano ani nie kopiowano wartości z `.env`.
- **Zależności sprawdzone punktowo:** `.github/workflows/deploy-ftp.yml:161–171,350–354`, `.htaccess:53–56,125–132`.
- **Granica dowodu:** Bez resetu, HTTP ani odczytu logów produkcyjnych; czas ważności i rotacja sekretu niezweryfikowane.

### `public/newsletter_unsubscribe.php`

**Blob:** `68131e656d9547a55ca2be7cd69d6b8f2bb23a9e` · **Status:** `pełny odczyt statyczny; hipotezy Maxa rozstrzygnięte przez nadzorcę` · **Odczyt:** 74 linie.

- **Ustalenie:** GET z tokenem 32-znakowym (13–16) wyszukuje rekord przez prepared statement i przy subskrypcji ustawia tylko `newsletter_subscribed=0` (19–40); komunikaty z tłumaczeń przechodzą przez `t()` (43–74). `Database.php:39–42` ustawia asocjacyjny tryb fetch, `Auth.php:217` i `admin/newsletter.php:59` generują tokeny z `random_bytes(16)`, a `GameLog::error()` akceptuje wyjątek (87–108): trzy obawy Maxa nie są tutaj potwierdzonymi błędami. Ryzyko automatycznego GET przez prefetch to kwestia projektowa, nie potwierdzony incydent.
- **Zależności sprawdzone punktowo:** `.htaccess:105`, `src/Database.php:38–53`, `src/Auth.php:192–228`, `admin/newsletter.php:52–70`, `src/GameLog.php:81–108`, `src/i18n.php:40–50`.
- **Granica dowodu:** Bez wysyłki maila, GET, SQL ani testu produkcji; nie badano praktycznej obsługi linku przez klienty pocztowe.

### `public/page.php`

**Blob:** `5c023d1f6fb920de2a7c7c0f52691bf58f3f43d6` · **Status:** `pełny odczyt statyczny; Max skorygowany przez nadzorcę` · **Odczyt:** 85 linii.

- **Ustalenie:** Slug jest ograniczany do małych liter/cyfr/myślnika (4), aktywne strony pobierane z `static_pages`; dla `regulamin` treść może pochodzić z lokalnego szablonu `regulamin_<locale>.php` (17–53). `getLocale()` stosuje allowlistę `pl/en/de` (`src/i18n.php:8–13`), więc nie potwierdzono manipulacji ścieżką. Pole `content` jest celowo emitowane jako HTML (78); jedyny znaleziony zapis treści idzie przez panel wymagający administratora i CSRF (`admin/pages_editor.php:5–6,65–82`), bez wykazanego nieufnego źródła gracza. `$pageTitle` jest escapowane w 64, potem ponownie w `templates/header.php:84`; encje w tytule będą widoczne w zakładce. `LegalPagesLocalizationTest.php` nie testuje renderu tego tytułu.
- **Zależności sprawdzone punktowo:** `admin/pages_editor.php:5–116`, `src/i18n.php:8–13`, `templates/header.php:77–85`, `tests/Unit/LegalPagesLocalizationTest.php:33–105`, `.htaccess:115–122`.
- **Granica dowodu:** Bez PHP/DB/renderu i bez audytu innych niejawnych zapisów do tabeli; nie deklarować stored XSS tylko na podstawie surowego wyjścia.

### `admin/pages_editor.php`

**Blob:** `4e3d792819b5033ddbe18d06279e21333402f62c` · **Status:** `pełny odczyt statyczny; Max zweryfikowany i doprecyzowany przez nadzorcę` · **Odczyt:** 153 linie.

- **Ustalenie:** Po sprawdzeniu `AdminAuth::requireLogin()` i CSRF (5–6,65–68) zapis/dodanie/usunięcie strony aktualizuje DB, potem wywołuje `pagesRebuildHtaccess()` (72–119). Funkcja próbuje usunąć blok regexem wymagającym `#  BEGIN static_pages ` (35–40) i wstawić reguły po literalnym komentarzu `#  Wszystko inne  public/ ` (53–58). Oba wzorce mają **zero dopasowań** w śledzonej `.htaccess:115–123` (tam jedna spacja po `#`, angielski komentarz i CRLF); izolowane porównanie tekstu pokazało brak zmiany przy bieżącym HEAD. Zapis do pliku może się wykonać, ale nie synchronizuje reguł z DB. Stara reguła po usunięciu/dezaktywacji pozostaje, lecz `public/page.php:45–61` odpowiada 404 bez aktywnego rekordu; nowo dodana strona nie uzyskuje czystej trasy na podstawie obecnych reguł.
- **Zależności sprawdzone punktowo:** `.htaccess:76–123` (pełny plik odczytany wcześniej), `public/page.php:4–61`, `src/i18n.php:8–13`; brak testu tej funkcji w sprawdzonych `tests/`.
- **Granica dowodu:** Bez wywołania POST, SQL, zapisu `.htaccess` w aplikacji i testu hostingu; inne reguły serwera/pozostałości FTP mogą zmienić objaw produkcyjny.

### `templates/views/logistics/sections/optimizer_cta.php`

**Blob:** `2557421242e0aeff896a7c0b3e685ace7a509dc6` · **Status:** `pełny odczyt statyczny; Max sprawdzony przez nadzorcę` · **Odczyt:** 3 linii.

- **Ustalenie:** Przycisk `data-logistics-open`/`btn-logistics-open` (1–3) otwiera modal optymalizatora; `main.php:29` dołącza go raz. Nie ma tu formularza POST ani opłaty.
- **Zależności sprawdzone punktowo:** main.php:26–32; optimizer_modal.php:1–49.
- **Granica dowodu:** Bez PHP/MySQL/HTTP/produkcyjnego renderu; odczyt testu lub fixture nie jest wykonaniem aplikacji.

### `templates/views/logistics/sections/alerts.php`

**Blob:** `11b682dad224713557af6edfe2927a5d3c1f5664` · **Status:** `pełny odczyt statyczny; Max sprawdzony przez nadzorcę` · **Odczyt:** 7 linii.

- **Ustalenie:** Iteruje `$alerts`, escapuje typ w klasie CSS i treść (2–6). Aktualny model inicjalizuje `$alerts=[]`; bez danych nie renderuje alertu. Brak dowodu wstrzyknięcia HTML.
- **Zależności sprawdzone punktowo:** src/LogisticsPage/ViewData/OverviewAndProtectionData.php:60; main.php:1–67 (brak literalnego include tego fragmentu).
- **Granica dowodu:** Bez PHP/MySQL/HTTP/produkcyjnego renderu; odczyt testu lub fixture nie jest wykonaniem aplikacji.

### `templates/views/logistics/partials/bootstrap.php`

**Blob:** `a82167e21c4988c72fdd3151d26116cd105052fb` · **Status:** `pełny odczyt statyczny; Max sprawdzony przez nadzorcę` · **Odczyt:** 6 linii.

- **Ustalenie:** Pobiera locale z sesji/cookie i wyprowadza walutę/separatory (2–6). Dla obsługiwanego `de` ustawia `EUR`, ale `$currencyLocale=pl-PL`; ta wartość trafia do aktywnej konfiguracji JS, więc formatowanie EUR ma polską lokalizację. Nie jest to przeliczenie kursu.
- **Zależności sprawdzone punktowo:** main.php:1; src/i18n.php:8–13; partials/client_config.php:6–10,40–62; assets/js/logistics_config.js:21–33.
- **Granica dowodu:** Bez PHP/MySQL/HTTP/produkcyjnego renderu; odczyt testu lub fixture nie jest wykonaniem aplikacji.

### `templates/views/logistics/partials/protection_client_config.php`

**Blob:** `5626b440a745925afe91d8f08ccf1e346c600481` · **Status:** `pełny odczyt statyczny; Max sprawdzony przez nadzorcę` · **Odczyt:** 11 linii.

- **Ustalenie:** Dokleja do konfiguracji klienta API `/public/protection.php`, token CSRF i teksty `tPlain` (2–11). Jest dołączany przez `client_config.php:4`, a wynik przechodzi przez `json_encode` z flagami HEX; surowe `tPlain` samo nie dowodzi XSS.
- **Zależności sprawdzone punktowo:** partials/client_config.php:1–5,93–98; src/CSRF.php:5–12.
- **Granica dowodu:** Bez PHP/MySQL/HTTP/produkcyjnego renderu; odczyt testu lub fixture nie jest wykonaniem aplikacji.

### `templates/views/logistics/sections/decisions_section.php`

**Blob:** `48d8a0e1cb94ffcb9153765e68088bcd50e5f789` · **Status:** `pełny odczyt statyczny; Max sprawdzony przez nadzorcę` · **Odczyt:** 13 linii.

- **Ustalenie:** Pokazuje maksymalnie dwie rekomendacje, escapując ton, tytuł, opis, adres CTA i etykietę (6–10). W aktualnym builderze każda rekomendacja ma te pola, a `cta_href` jest stałą kotwicą; hipotezy Maxa o brakujących kluczach i `javascript:` z wejścia gracza odrzucono.
- **Zależności sprawdzone punktowo:** src/LogisticsPage/ViewData/PipelineAndInsightsData.php:342–381; main.php:16–18.
- **Granica dowodu:** Bez PHP/MySQL/HTTP/produkcyjnego renderu; odczyt testu lub fixture nie jest wykonaniem aplikacji.

### `templates/views/logistics/modals/pipeline_staffing_modal.php`

**Blob:** `24de839740fd3f09465da81273b9d1ee460fe6e2` · **Status:** `pełny odczyt statyczny; Max sprawdzony przez nadzorcę` · **Odczyt:** 18 linii.

- **Ustalenie:** Szkielet modala obsady rurociągu z zamknięciem i pustym kontenerem dla JS (3–18); samo otwarcie nie wysyła żądania. Teksty `t()` są escapowane przez funkcję tłumaczeń, a atrybuty close escapowane dodatkowo.
- **Zależności sprawdzone punktowo:** main.php:64–67; src/i18n.php:40–50; assets/js/logistics_pipeline_staffing.js.
- **Granica dowodu:** Bez PHP/MySQL/HTTP/produkcyjnego renderu; odczyt testu lub fixture nie jest wykonaniem aplikacji.

### `templates/views/logistics/sections/hub_summary.php`

**Blob:** `8aafe6187b62c8f63ee84cb056f560799c62e122` · **Status:** `pełny odczyt statyczny; Max sprawdzony przez nadzorcę` · **Odczyt:** 20 linii.

- **Ustalenie:** Zlicza huby krytyczne, wymagające obserwacji oraz nieprzypisane odwierty z przygotowanych tablic (4–19); progi 20/60% kondycji i 80% obciążenia są regułami wyświetlania. Builder inicjalizuje `$hubCards` i `$unassignedTotal`, więc brak tych zmiennych w samodzielnym include nie jest awarią aktywnej strony.
- **Zależności sprawdzone punktowo:** src/LogisticsPage/ViewData/OverviewAndProtectionData.php:62–88; main.php:40–44.
- **Granica dowodu:** Bez PHP/MySQL/HTTP/produkcyjnego renderu; odczyt testu lub fixture nie jest wykonaniem aplikacji.

### `templates/views/logistics/sections/transport_kpi.php`

**Blob:** `b14a6b6387a9d0aa3cf96a909baa682f3cdeef48` · **Status:** `pełny odczyt statyczny; Max sprawdzony przez nadzorcę` · **Odczyt:** 35 linii.

- **Ustalenie:** Liczy aktywne odwierty z ustawionym transportem, największą stratę typu i dostawy morskie (2–35). `$wells`/`$transportMix` są inicjalizowane przed aktywnym includem; przy braku danych „no losses” jest zamierzonym fallbackiem, nie dowodem utraty danych.
- **Zależności sprawdzone punktowo:** src/LogisticsPage/ViewData/OverviewAndProtectionData.php:11–36; main.php:26–35.
- **Granica dowodu:** Bez PHP/MySQL/HTTP/produkcyjnego renderu; odczyt testu lub fixture nie jest wykonaniem aplikacji.

### `templates/views/logistics/modals/pipeline_modals.php`

**Blob:** `954aabc1d4572209a0b0dd14752e54419e7161e2` · **Status:** `pełny odczyt statyczny; Max sprawdzony przez nadzorcę` · **Odczyt:** 40 linii.

- **Ustalenie:** Definiuje dwa ukryte modale: zakup rurociągu i potwierdzenie akcji (2–40), z kontenerami treści wypełnianymi po stronie JS. Teksty są ze stałych `t()`; przyciski zamknięcia bez własnego aria-label to obserwacja dostępności, nie luka bezpieczeństwa.
- **Zależności sprawdzone punktowo:** main.php:64–66; src/i18n.php:40–50; assets/js/logistics_pipeline.js.
- **Granica dowodu:** Bez PHP/MySQL/HTTP/produkcyjnego renderu; odczyt testu lub fixture nie jest wykonaniem aplikacji.

### `templates/views/logistics/sections/transport_table.php`

**Blob:** `cd6b8d8db5f8379ce283461061a7329f153e0829` · **Status:** `pełny odczyt statyczny; Max sprawdzony przez nadzorcę` · **Odczyt:** 35 linii.

- **Ustalenie:** Tabela transportu pokazuje pusty stan lub wiersze odwiertów z escapowaną nazwą i liczbowymi danymi (6–35). `LogisticsService::getCurrentSummary()` buduje `id`, `capacity_pct`, `loss`, `cost` dla każdego wiersza, więc ostrzeżenia Maxa o brakujących kluczach nie są wykazane w tej ścieżce.
- **Zależności sprawdzone punktowo:** src/LogisticsService.php:334–379; main.php:30–33.
- **Granica dowodu:** Bez PHP/MySQL/HTTP/produkcyjnego renderu; odczyt testu lub fixture nie jest wykonaniem aplikacji.

### `templates/views/logistics/sections/tanker_buffer_card.php`

**Blob:** `6774762693cf67afbb81dbd085aa0e7aa4763d8b` · **Status:** `pełny odczyt statyczny; Max sprawdzony przez nadzorcę` · **Odczyt:** 32 linii.

- **Ustalenie:** Renderuje bufor tankowców, próg załadunku i pasek procentowy z liczbowymi ograniczeniami 0–100 (1–32). `$marineMinLoadBbl` jest zainicjalizowane nawet przed próbą pobrania usług (builder 388–394), więc hipoteza Maxa o undefined variable nie dotyczy aktualnego callera.
- **Zależności sprawdzone punktowo:** src/LogisticsPage/ViewData/PipelineAndInsightsData.php:384–413,475–476; main.php:33–37.
- **Granica dowodu:** Bez PHP/MySQL/HTTP/produkcyjnego renderu; odczyt testu lub fixture nie jest wykonaniem aplikacji.

### `templates/views/logistics/sections/transport_mix_section.php`

**Blob:** `0fab75c267bd1a0556d8bbde5de6dddf8884b6e7` · **Status:** `pełny odczyt statyczny; Max sprawdzony przez nadzorcę` · **Odczyt:** 29 linii.

- **Ustalenie:** Wyświetla trzy znane rodzaje transportu, liczby odwiertów, przepływ, straty i koszty; brak typu ma zerowy fallback (14–29). Polskie klucze są takie same jak inicjalizowane w builderze (21–25); `$currencyLabel` pochodzi z `bootstrap.php`, więc nie potwierdzono błędu niezdefiniowanej waluty w aktywnym widoku.
- **Zależności sprawdzone punktowo:** src/LogisticsPage/ViewData/OverviewAndProtectionData.php:21–36; main.php:1,19; partials/bootstrap.php:2–6.
- **Granica dowodu:** Bez PHP/MySQL/HTTP/produkcyjnego renderu; odczyt testu lub fixture nie jest wykonaniem aplikacji.

### `templates/views/logistics/sections/flow_section.php`

**Blob:** `6e0865b8bfda0dec388d45d066659a9bc22faa6d` · **Status:** `pełny odczyt statyczny; Max sprawdzony przez nadzorcę` · **Odczyt:** 41 linii.

- **Ustalenie:** Zlicza aktywne huby, rurociągi i trasy z kontraktu strony, następnie wyświetla pięć etapów przepływu (4–41). Builder inicjalizuje `$hubCards`, `$pipelines`, `$transportMix`, `$activeWellCount` i `$storageBbl`; ostrzeżenia Maxa o niezdefiniowanych zmiennych nie są potwierdzone dla bieżącej trasy.
- **Zależności sprawdzone punktowo:** src/LogisticsPage/ViewData/OverviewAndProtectionData.php:11–121; main.php:1,16.
- **Granica dowodu:** Bez PHP/MySQL/HTTP/produkcyjnego renderu; odczyt testu lub fixture nie jest wykonaniem aplikacji.

### `templates/views/logistics/modals/optimizer_modal.php`

**Blob:** `4e74f6d481e938dfed39db088118bc06c7882241` · **Status:** `pełny odczyt statyczny; Max sprawdzony przez nadzorcę` · **Odczyt:** 49 linii.

- **Ustalenie:** Modal z wyborem jednego z trzech trybów `balans/max_prod/min_cost`, kontenerami wyników i przyciskiem `data-logistics-run` (1–49). `t()` już escapuje HTML, zatem brak dodatkowego `htmlspecialchars` nie jest XSS; koszt i serwerowa walidacja leżą poza tym komponentem.
- **Zależności sprawdzone punktowo:** main.php:64; src/i18n.php:40–50; src/LogisticsService.php:68–75.
- **Granica dowodu:** Bez PHP/MySQL/HTTP/produkcyjnego renderu; odczyt testu lub fixture nie jest wykonaniem aplikacji.

### `templates/views/logistics/sections/flash_kpi.php`

**Blob:** `1b8ee7dd22133e6cb7f4f5c676a2fb85970da565` · **Status:** `pełny odczyt statyczny; Max sprawdzony przez nadzorcę` · **Odczyt:** 48 linii.

- **Ustalenie:** Pokazuje flash obsady oraz KPI wydajności, strat, kosztu i uwagi (1–48). Dane `$wells`, `$efficiency`, `$lossPct`, `$totals`, `$activeWellCount` inicjalizuje builder, także przy pustych tablicach; samodzielny include nie dowodzi fatala w aktualnej trasie.
- **Zależności sprawdzone punktowo:** src/LogisticsPage/ViewData/OverviewAndProtectionData.php:11–47; main.php:12–19.
- **Granica dowodu:** Bez PHP/MySQL/HTTP/produkcyjnego renderu; odczyt testu lub fixture nie jest wykonaniem aplikacji.

### `templates/views/logistics/sections/road_trips_section.php`

**Blob:** `2993e8b64c7b1dbc56bebffd96348fc1d8b84e97` · **Status:** `pełny odczyt statyczny; Max sprawdzony przez nadzorcę` · **Odczyt:** 50 linii.

- **Ustalenie:** Karty kursów i countdown pobierają `well_id`, `eta_at`, wolumen z `RoadTransportService` (13–29,627–646); paginacja kopiuje `$_GET` i escapuje `http_build_query` (32–49). Brak kluczy z zapytania SQL nie jest wykazany; pełny runtime żądań niebadany.
- **Zależności sprawdzone punktowo:** src/RoadTransportService.php:627–653; src/LogisticsPage/ViewData/OverviewAndProtectionData.php:200–207; main.php:33–35.
- **Granica dowodu:** Bez PHP/MySQL/HTTP/produkcyjnego renderu; odczyt testu lub fixture nie jest wykonaniem aplikacji.

### `templates/views/logistics/sections/unassigned_wells_section.php`

**Blob:** `867f043cef76129672187b792f9193bb40270709` · **Status:** `pełny odczyt statyczny; Max sprawdzony przez nadzorcę` · **Odczyt:** 67 linii.

- **Ustalenie:** Renderuje nieprzypisane odwierty, cooldown i akcję przypisania; `region_id` i `base_production_per_hour` są pobierane w `Hub/PlayerQueryTrait.php:188–213`. Paginacja (54,59) tworzy nowy query string zamiast zachować istniejące filtry/incydenty/strony innych sekcji — potwierdzony kontraktowy błąd linków, bez HTTP.
- **Zależności sprawdzone punktowo:** src/Hub/PlayerQueryTrait.php:188–213; src/LogisticsPage/ViewData/OverviewAndProtectionData.php:69–83,156–162; main.php:40–45.
- **Granica dowodu:** Bez PHP/MySQL/HTTP/produkcyjnego renderu; odczyt testu lub fixture nie jest wykonaniem aplikacji.

### `templates/views/logistics/main.php`

**Blob:** `f5741e0128d3f5a645ca467e0c9368c022678b40` · **Status:** `pełny odczyt statyczny; Max sprawdzony przez nadzorcę` · **Odczyt:** 67 linii.

- **Ustalenie:** Aktywny widok wywołany przez `public/logistics.php` wciąga bootstrap, sekcje KPI/transport/huby/rynek/rurociągi/incydenty, modale i konfigurację klienta w stałej kolejności (1–67). Sam nie realizuje operacji DB; test kontraktu sprawdza listę literalnych `require`, nie render aplikacji.
- **Zależności sprawdzone punktowo:** public/logistics.php:16–22,49–52; src/LogisticsPage/ViewDataTrait.php:28–36; tests/Unit/LogisticsPageViewContractTest.php:70–123.
- **Granica dowodu:** Bez PHP/MySQL/HTTP/produkcyjnego renderu; odczyt testu lub fixture nie jest wykonaniem aplikacji.

### `templates/views/logistics/partials/client_config.php`

**Blob:** `07606b1890eeadb95b0dcf152ed51085cbddd465` · **Status:** `pełny odczyt statyczny; Max sprawdzony przez nadzorcę` · **Odczyt:** 98 linii.

- **Ustalenie:** Składa konfigurację optimizer/pipeline/staffing oraz dołączone hub/protection (1–91), emituje JSON w `<script type=application/json>` z `JSON_HEX_*` (93–98). `CSRF::generateToken()` utrzymuje istniejący token sesyjny (CSRF.php:5–12), więc wielokrotne wywołania nie unieważniają poprzedniego tokena; `bootstrap.php` zainicjalizowano na `main.php:1`.
- **Zależności sprawdzone punktowo:** main.php:1,67; src/CSRF.php:5–12; assets/js/logistics_config.js:6–25.
- **Granica dowodu:** Bez PHP/MySQL/HTTP/produkcyjnego renderu; odczyt testu lub fixture nie jest wykonaniem aplikacji.

### `templates/views/logistics/sections/hub_incidents_section.php`

**Blob:** `22f157dc3d5901a8642c3976b6b2f321c55a3a30` · **Status:** `pełny odczyt statyczny; Max sprawdzony przez nadzorcę` · **Odczyt:** 93 linii.

- **Ustalenie:** Filtry GET, lista incydentów z rozwinięciem po trzecim elemencie i paginacja (19–93). W bieżącej trasie `LogisticsIncidentFeed::page()` zwraca numerowaną listę z SQL zawierającego `message` i `created_at`, więc Maxowa hipoteza o dziurawych kluczach i osieroconym `</details>` nie jest potwierdzona; paginacja zachowuje parametry przez escapowany `http_build_query`.
- **Zależności sprawdzone punktowo:** src/LogisticsIncidentFeed.php:15–28,39–54; src/LogisticsPage/ViewData/OverviewAndProtectionData.php:65–70,149–154; main.php:58–59.
- **Granica dowodu:** Bez PHP/MySQL/HTTP/produkcyjnego renderu; odczyt testu lub fixture nie jest wykonaniem aplikacji.

### `admin/player.php`

**Blob:** `d6962287866b616bc153949e62bd589c8a5b52b5` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 8 linii.

- **Ustalenie:** Redirect kompatybilnościowy rzutuje `id` na int i kończy żądanie (3–8); nie ujawnia danych gracza. Cele `player_clean.php` i `players.php` same wywołują `AdminAuth::requireLogin()`, więc brak lokalnego loginu nie daje dostępu do treści.
- **Zależności sprawdzone punktowo:** admin/player_clean.php:3; admin/players.php:7.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/components/chat.php`

**Blob:** `e3bbb518550a27c8b6460dfc578f2addc5d27b66` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 17 linii.

- **Ustalenie:** Guard `is_array($dashboardChatViewData ?? null)` emituje fallback, a prawidłowy payload przekazuje do `views/chat/main.php` w statycznej funkcji (6–17). Bezpieczeństwo tekstu czatu zależy od docelowego widoku, nie od tego wrappera.
- **Zależności sprawdzone punktowo:** templates/views/index/main.php; templates/views/chat/main.php; tests/Unit/ChatWidgetResilienceTest.php.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/components/well_grid/danger_zone.php`

**Blob:** `c1f3eda001b46a5013d772fe173df343fb3ba6b3` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 11 linii.

- **Ustalenie:** Przycisk podglądu sprzedaży widoczny poza statusami `seized/blowout/sold`; `$wid` w inline JS ma rzutowanie `(int)$w['id']` w callerze, więc nie ma tu dowodu wstrzyknięcia z ID. Formularz/UI nie jest serwerową bramką statusu.
- **Zależności sprawdzone punktowo:** templates/components/well_grid.php:99,320; templates/views/wells/main.php:26.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/views/admin/hr/_pagination.php`

**Blob:** `60e4e6dfb0eac608582be3882bbccf3a912e2937` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 20 linii.

- **Ustalenie:** Linki zachowują `$_GET` i nadpisują allowlistowany klucz strony przez `http_build_query` oraz `$esc` (1–20). Kontrakt `$tab/$esc/$pagination` zapewnia `hr/main.php` oraz `admin/hr.php`; wiele linków przy dużej liczbie stron pozostaje osobnym ryzykiem wydajności UI.
- **Zależności sprawdzone punktowo:** templates/views/admin/hr/main.php:2–7,51–59; admin/hr.php:715–826.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `admin/init.php`

**Blob:** `504369630032108f5396c872db67b5c92b97ce92` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 28 linii.

- **Ustalenie:** Bootstrap administracji ładuje `src/init.php`, AdminAuth i aktywny `src/AdminLog.php` (8–18), propaguje wyjątek po logu (19–23). Nie ładuje `admin/AdminLog.php`; te dwie kopie są bajtowo identyczne na HEAD.
- **Zależności sprawdzone punktowo:** src/init.php; src/AdminAuth.php; src/AdminLog.php; admin/AdminLog.php.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/views/admin/training/main.php`

**Blob:** `dfcbbbfaf92d086a67cb213e0a4d91645606701d` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 36 linii.

- **Ustalenie:** Widok dopuszcza jedynie dwie stałe gałęzie include dla `programs/monitor` (29–34); GET tab jest allowlistowany w kontrolerze. Komunikaty błędu/sukcesu są escapowane, a `$viewData` z `msg/err/tab` jest przygotowane.
- **Zależności sprawdzone punktowo:** admin/training.php:17–24,170–176.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `admin/logout.php`

**Blob:** `0a46efccea664b4c6ec1653b90f3c0ca20a01fb9` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 39 linii.

- **Ustalenie:** POST+CSRF są wymagane (7–16); po opcjonalnym audycie `AdminAuth::logout()` usuwa admin sesję i token zaufanego urządzenia, lecz zachowuje niezależną sesję gracza (18–25). Catch ma generyczny 500; brak dowodu bypassu.
- **Zależności sprawdzone punktowo:** src/AdminAuth.php:379–396; src/CSRF.php:22–31.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/components/incident_icons.php`

**Blob:** `2edd4e99446e5b92bbc82e29f1def95c65fe4c0a` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 29 linii.

- **Ustalenie:** Ikony SVG czytane tylko z literalnej allowlisty nazw (10–25); `$class` jest escapowane przed dodaniem do `<svg>` (26–27). Modyfikacja zaufanego pliku SVG jest innym modelem zagrożenia, nie potwierdzonym wejściem użytkownika.
- **Zależności sprawdzone punktowo:** assets/img/icons/incidents/rig.svg; assets/img/icons/nav/logistics.svg.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/views/admin/hr/effects.php`

**Blob:** `3707dd0b6d6ebd68fc8e4c48a6b9265de87628b8` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 23 linii.

- **Ustalenie:** Tabela efektów ról używa `$esc` dla pól z DB i numerycznego `number_format` (12–18). `$esc/$label` są zdefiniowane w `hr/main.php`; brak ich w samodzielnym include nie jest błędem aktywnej trasy.
- **Zależności sprawdzone punktowo:** templates/views/admin/hr/main.php:2–7,59; admin/hr.php:730,744–745.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `admin/forgot_password.php`

**Blob:** `d036c064ee8f74a5a5dc5a7ea9854c33fd59a936` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 42 linii.

- **Ustalenie:** Anonimowy formularz CSRF na GET (8–28) po ważnym POST wywołuje `AdminAuth::sendPasswordReset()` i generycznie przekierowuje. Serwis nie limituje ponowień, usuwa stary token i wysyła nowy mail; to statyczne ryzyko mail flood/unieważnienia linków, bez wykonania HTTP/SMTP.
- **Zależności sprawdzone punktowo:** src/AdminAuth.php:415–455; src/Mailer.php:15–59; templates/views/admin/forgot_password/main.php:26–31.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/views/admin/change_password/main.php`

**Blob:** `f514fd74d1df73a9fd3141715512fc98d69d8f60` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 32 linii.

- **Ustalenie:** Nieużyty obecnie szablon formularza ze zdefiniowanym CSRF (1–32). Aktywny `admin/change_password.php:43–77` renderuje inny formularz inline i nie ma referencji do tej ścieżki w PHP; ostrzeżenia o brakującym `$viewData` nie dotyczą aktywnej trasy.
- **Zależności sprawdzone punktowo:** admin/change_password.php:12–40,43–77; src/AdminAuth.php:594–601.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/views/admin/privacy/main.php`

**Blob:** `56bc69af2c8c2dc4548a06ea3999085e6e219922` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 45 linii.

- **Ustalenie:** Widok wybiera `getViewPath()` z obiektu w rejestrze (30–42), nie buduje pliku z surowego GET. `admin/privacy.php:33–52` ogranicza GET/POST do kluczy registry, a klasy cech zwracają stałe lokalne ścieżki; hipoteza o path traversal odrzucona.
- **Zależności sprawdzone punktowo:** admin/privacy.php:26–52; src/Privacy/PrivacyFeatureRegistry.php:62–72; src/Privacy/Features/Policy/PolicyFeature.php:97–100.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/views/admin/hr/_filters.php`

**Blob:** `e9532001837763086103beabdd27d4f3c4018ec0` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 31 linii.

- **Ustalenie:** Formularz filtrów GET odczytuje `filters.player_id/department/status` z ustalonej struktury `admin/hr.php:715–722`; wartości atrybutów przechodzą przez `$esc` (1–31). Link „wyczyść” celowo zeruje inne parametry, w przeciwieństwie do paginacji.
- **Zależności sprawdzone punktowo:** admin/hr.php:715–723; templates/views/admin/hr/main.php:2–7.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `admin/AdminLog.php`

**Blob:** `b74de1c6973437315bcc91463c11f98c513c439f` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 45 linii.

- **Ustalenie:** Zduplikowana bajtowo z aktywnym `src/AdminLog.php`; `log()` po błędzie INSERT loguje, lecz nie rzuca dalej (15–43). Poprawny zapis biznesowy może więc nie mieć trwałego wpisu admin_logs; sesja admina dostarcza normalnie `admin_user`.
- **Zależności sprawdzone punktowo:** admin/init.php:13–14; src/AdminLog.php:15–43; src/AdminAuth.php:115–122.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/views/admin/hr/assignments.php`

**Blob:** `f095d6c3e5c17127ce7cb4c8256237a3786268ac` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 25 linii.

- **Ustalenie:** Wiersze przypisań używają `$esc`/`$label` dla nazw/statusu oraz rzutowania dla ID i `number_format` dla alokacji (13–20). `$assignmentList` jest inicjalizowane w kontrolerze i dostarczane przez query service przy tab `assignments`.
- **Zależności sprawdzone punktowo:** admin/hr.php:726,746–747,815–816; templates/views/admin/hr/main.php:2–7.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `admin/bank_status.php`

**Blob:** `3f0b42fcbdfe75c362435a496ca7539c5952557a` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 54 linii.

- **Ustalenie:** JSON stanu kredytów, postępowań i gotówki dla podanego int `pid` (13–54); `requireLogin` jest admin bramką, a zapytania parametryzowane. Brak tu dowodu IDOR zwykłego gracza; SELECT * ujawnia pola uprawnionemu adminowi, co można ograniczyć przy migracji.
- **Zależności sprawdzone punktowo:** src/AdminAuth.php:331–355; admin/player_clean.php:3.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/components/company_credibility.php`

**Blob:** `bee93c607241b3e15d760deadf330996f4a2742c` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 41 linii.

- **Ustalenie:** Poziom wiarygodności jest ograniczony allowlistą pięciu wartości przed użyciem jako CSS/tłumaczenie (17–39). Skala `score` jest clampowana, ale nie wyświetlana (celowe ograniczenie UI); brak domniemanego XSS w klasie.
- **Zależności sprawdzone punktowo:** templates/views/legal/main.php:83; src/i18n.php:40–50.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `admin/player_menu_save.php`

**Blob:** `3e6cd471e6dabcee7377d5ae36ecb4a5c7bb19eb` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 34 linii.

- **Ustalenie:** JSON endpoint wymaga pełnej admin sesji, POST+CSRF i tablicy JSON (7–21); szczegółowa walidacja/wersjonowanie oraz rollback są w `PlayerMenuEditor::save()`. Błąd zapisu audytu nie cofa zmiany, a odpowiedź może być `ok=true` (22–27); nie ma masowego assignment bez walidacji serwisu.
- **Zależności sprawdzone punktowo:** src/PlayerMenuEditor.php:47–82,130–199; src/AdminLog.php:15–43.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/views/admin/hr/employees.php`

**Blob:** `9a2c0419bc33255d7d5219a18e60b50369f834c3` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 26 linii.

- **Ustalenie:** Tabela pracowników escapuje nazwę, email i kody, rzutuje ID/procenty (13–21); przekazuje filtry i paginację do wspólnych partiali. `employeeList` zainicjalizowano w kontrolerze, a query service wybiera `employee_name` i `player_email`.
- **Zależności sprawdzone punktowo:** admin/hr.php:725,742–743; src/HR/AdminHRQueryService.php:27–38.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/views/admin/forgot_password/main.php`

**Blob:** `ede009a90bf17806ab376dc694ce8072df747239` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 38 linii.

- **Ustalenie:** Formularz resetu wyświetla surową wartość POST e-mail dopiero po `htmlspecialchars` w value (26–31); token i komunikat także escapowane. Nie potwierdza się hipoteza o XSS od tego POST; ryzyko limitu wysyłek leży w kontrolerze/serwisie.
- **Zależności sprawdzone punktowo:** admin/forgot_password.php:10–28; src/AdminAuth.php:415–455.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/views/admin/hr/morale.php`

**Blob:** `4ce4e579c30f2f559e8f73299a77ef6cceecf5e9` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 28 linii.

- **Ustalenie:** Siatka morale i ryzyka pracownika escapuje nazwy/kody i formatem liczbowym pokazuje procenty (14–23). `employeeList` dostaje `SELECT es.*` oraz `employee_name` z query service, więc sugestia o braku kluczy nie jest poparta aktualnym SQL.
- **Zależności sprawdzone punktowo:** admin/hr.php:725,742–743; src/HR/AdminHRQueryService.php:27–38.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/components/price_chart.php`

**Blob:** `33fb002ba45025cc791a7e37a3d1e65bc39eedc7` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 46 linii.

- **Ustalenie:** Wykres skaluje historyczne ceny, a try/catch w razie błędu skali kończy tylko komponent (10–46). `MarketTick::savePriceHistory(int $price)` zapisuje cenę liczbową, `getPriceHistory` wybiera `price,created_at` dla aktywnego callera; surowe atrybuty z tego źródła nie są dowodem XSS.
- **Zależności sprawdzone punktowo:** public/market.php:109; src/MarketTick.php:215–229,243–257; templates/views/market/main.php:47.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `admin/index.php`

**Blob:** `685e985875352a97673be1ffa42398b2d8f3f27d` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 58 linii.

- **Ustalenie:** Panel admina po `requireLogin()` wykonuje statyczne odczyty liczników, rynku i ostatniego ticku, po czym renderuje widok (5–44). Dane flash są kasowane z sesji dopiero po odczycie (29–31); catch oddaje ogólny błąd/500, nie szczegóły SQL.
- **Zależności sprawdzone punktowo:** src/AdminAuth.php:331–355; templates/views/admin/index/main.php.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/views/admin/hr/raises.php`

**Blob:** `8e0c0fc0445eeb711bb63f890a19fb168d688698` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 32 linii.

- **Ustalenie:** Lista próśb o podwyżkę pokazuje kwoty jako float oraz powód/status przez `$esc`/`$label`, dołącza filtry i paginację (1–32). `raiseList` ma zainicjalizowany kontrakt i jest wypełniany przez query service tylko dla kanonicznego tab `raises`.
- **Zależności sprawdzone punktowo:** admin/hr.php:727,748–749,816; templates/views/admin/hr/main.php:2–7.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/views/legal/_bribe_button.php`

**Blob:** `7a58147271f16ad098b4ac7aae5ddd585a5a66a7` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 47 linii.

- **Ustalenie:** Przycisk łapówki renderuje formularz POST z CSRF wyłącznie przy włączonym mechanizmie i dostępnej wycenie; region_id jest rzutowany, nazwa regionu escapowana w `data-*` (12–41). Wyświetlony stan `$cash` nie zastępuje walidacji kosztu po stronie serwera.
- **Zależności sprawdzone punktowo:** templates/views/legal/main.php; src/CSRF.php:34–44; src/Legal/BriberyTrait.php.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `admin/partials/footer.php`

**Blob:** `30c08dc001d78a222ae628d302e339918fe05f65` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 65 linii.

- **Ustalenie:** Zamyka layout, serializuje stałe tłumaczenia do JS, ładuje skrypty i obsługuje mobilne menu (8–65). `t()` escapuje HTML, a bieżące klucze `MODAL_LANG` są statyczne; hipoteza o XSS z samego `json_encode` bez HEX nie ma tu potwierdzonego źródła niezaufanej treści.
- **Zależności sprawdzone punktowo:** src/i18n.php:40–50; lang/pl/common.php:72–79; admin/hr.php:828–832.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/views/privacy/banner.php`

**Blob:** `46a734d2bdb5873ac1592f0053c7c6c61bdb1683` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 52 linii.

- **Ustalenie:** Teksty banera są HTML-escapowane, JSON config ma `JSON_HEX_*`, modal dołączany jednorazowo (9–52). `policy_url` pochodzi z ustawienia admina, dla którego writer nie waliduje schematu; to ryzyko błędnej konfiguracji `href`, nie wykazany atak użytkownika gry.
- **Zależności sprawdzone punktowo:** src/Privacy/PrivacyBannerService.php:44–53; src/Privacy/Features/Banner/BannerSettingsService.php:23–39; templates/header.php:58.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `public/cookies-policy.php`

**Blob:** `602c5b755bc311c4b53498a4e2543bf54f68295e` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 55 linii.

- **Ustalenie:** Aktywna treść polityki PL pochodzi z bazy zarządzanej przez admina, wariant en/de z plików repozytorium; HTML celowo renderowany bez escaping (13–42). `getLocale()` ogranicza interpolację ścieżki do `pl/en/de`; sam raw output nie dowodzi XSS gracza. Metadane wersji/daty mogą pochodzić z DB niezależnie od statycznego tłumaczenia.
- **Zależności sprawdzone punktowo:** src/i18n.php:8–13; src/Privacy/PrivacyPolicyService.php:16–29; src/Privacy/Features/Policy/PolicyFeature.php:26–78.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/views/admin/hr/main.php`

**Blob:** `241190ae4375fe6415a4c579c78aebe2f0c2e9e5` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 60 linii.

- **Ustalenie:** Stały szkielet jedenastu zakładek, helpery `$esc/$label` oraz dołączenie widoku tab (2–59). `admin/hr.php:16–31` allowlistuje GET, a po klasycznych POST robi redirect (536–544), więc aliasy są przetwarzane ponownie; nie potwierdzono path traversal ani brakującego include.
- **Zależności sprawdzone punktowo:** admin/hr.php:16–35,536–553,789–832.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/components/action_grid.php`

**Blob:** `444f574cf718f4352aaf8db2481d64eae4d989d8` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 53 linii.

- **Ustalenie:** Układ akcji zawiera surowe `icon_html/label/class` i niescapowaną akcję formularza (18–47), ale w aktywnym dashboardzie `$actions` to stałe linki i escapowane tłumaczenia z kontrolera. Formularz ma CSRF; nie ma tutaj dowodu niezaufanego sinka ani błędu `foreach(null)` w aktywnym przepływie.
- **Zależności sprawdzone punktowo:** public/index.php:309–340; templates/views/index/main.php:124–127; src/i18n.php:40–50.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `admin/finance.php`

**Blob:** `a386a7ec39f9f3b344c27e5d9c7f2f707290fb34` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 67 linii.

- **Ustalenie:** Po admin login+CSRF dopuszcza `hours` 24/168/720, deleguje POST do trzech akcji i renderuje statystyki (8–57). W handlerze reset polityki usuwa dwa zbiory bez transakcji; przy błędzie drugiego po pierwszym możliwy częściowy zapis mimo komunikatu błędu.
- **Zależności sprawdzone punktowo:** admin/partials/finance_admin_actions.php:116–189; admin/partials/finance_admin_metrics.php:14–42.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/views/public/pages/privacy_en.php`

**Blob:** `60e4b642281bb83c5cc6904a355a450e067a8079` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 36 linii.

- **Ustalenie:** Statyczny angielski tekst polityki prywatności, bez kodu PHP i wejścia żądania (1–36). Deklaracje o retencji, HTTPS i przetwarzaniu wymagają oceny przez właściciela/eksperta; audyt kodu nie potwierdza ich prawdziwości.
- **Zależności sprawdzone punktowo:** public/privacy-policy.php; src/Privacy/PrivacyPolicyService.php.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/views/admin/hr/settings.php`

**Blob:** `0fb9bbfdfe697aacfcc9f7313ecdb2d397624b19` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 38 linii.

- **Ustalenie:** Formularze ustawień HR (1–38) używają CSRF i `$esc` w atrybutach; grupy/definicje dostarcza `AdminHRConfigService` w kontrolerze. `t()` już escapuje HTML, więc brak dodatkowego `htmlspecialchars` na etykietach nie jest XSS.
- **Zależności sprawdzone punktowo:** admin/hr.php:764–765,787,818–819; src/i18n.php:40–50.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/views/privacy/settings_modal.php`

**Blob:** `5a74c4b55b9cd017cadf6d9b2c04b85f1d8dea7f` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 54 linii.

- **Ustalenie:** Modal iteruje kategorie z serwisu banera i escapuje ich pola (25–42); konieczne `required` oznacza przełącznik jako checked/disabled w UI. Kategorie buduje `getActiveCategories()` według stałej listy czterech typów; brak dowodu brakujących pól na aktywnej trasie.
- **Zależności sprawdzone punktowo:** templates/views/privacy/banner.php:43–47; src/Privacy/PrivacyBannerService.php:63–100.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/components/trend_alert.php`

**Blob:** `d78da7019beaa9b487eaaa1d874d8e60f683ec84` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 42 linii.

- **Ustalenie:** Bieżący dashboard inicjalizuje `trendClass`, `trendMessage`, `remainingTime` przed include (public/index.php:169–204), więc hipoteza o niezdefiniowanych zmiennych z samodzielnego include nie dotyczy tej trasy. Komponent pokazuje aktywne zdarzenie, czas i procent przy escapie danych tekstowych (7–41).
- **Zależności sprawdzone punktowo:** public/index.php:169–205; templates/views/index/main.php:22.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/views/admin/hr/dialogue_form.php`

**Blob:** `cae42e14099d3d5d015538047a8149cd471c9f9a` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 15 linii.

- **Ustalenie:** Formularz dialogu HR ma CSRF, rzutowany ID, ograniczone w UI liczby, escapowane PL/EN textarea i selecty (1–15). `dialogueContexts` i `dialogueTones` są zawsze ustawione w `admin/hr.php`; serwerową walidację pozostawia handlerowi, nie ograniczeniom HTML.
- **Zależności sprawdzone punktowo:** admin/hr.php:734–735,789–826; templates/views/admin/hr/dialogues.php.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/views/admin/hr/logs.php`

**Blob:** `a6a293a8fc7bb6096ea253b6fe1dd219dfbde3a4` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 50 linii.

- **Ustalenie:** Dwie sekcje zdarzeń i historii używają `$esc/$label`, a drugiej paginacji przekazują allowlistowany klucz `hpage` (1–50). Kontroler inicjalizuje `eventList` i historyczne liczniki przed renderem; brak dowodu błędu nieistniejącego klucza strony w aktualnym flow.
- **Zależności sprawdzone punktowo:** admin/hr.php:611–633,729,774–775; templates/views/admin/hr/_pagination.php:3–16.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/views/admin/login/main.php`

**Blob:** `97b927f1b23c35af98f791962515be9516fb4de6` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 64 linii.

- **Ustalenie:** Formularz logowania admina escapuje wartości `login/csrf/error/success` i drukuje hasło tylko w polu input, bez value (1–64). `t()` jest HTML-escapowany w `src/i18n.php`, a POST obsługuje `AdminAuth::login` przed etapem 2FA; surowy POST login w viewData nie daje XSS.
- **Zależności sprawdzone punktowo:** admin/login.php:41–69; src/i18n.php:40–50; src/AdminAuth.php:15–59.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `admin/login.php`

**Blob:** `69cac3244b5e238c328dcf5e106f507c50ae2c97` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 83 linii.

- **Ustalenie:** Kontroler obsługuje pełną sesję, zaufane urządzenie, pending 2FA i SSO (10–34); zwykły POST wymaga CSRF, blokady IP, a poprawne hasło kieruje do `/admin/2fa.php` zamiast tworzyć pełną sesję (41–69). `trySSO` wiąże gracza z emailem admina i ustawia tylko pending; nie wykazano obejścia 2FA w tym pliku.
- **Zależności sprawdzone punktowo:** src/AdminAuth.php:15–59,64–103,331–355,699–741; templates/views/admin/login/main.php:21–36.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `templates/components/game_navigation.php`

**Blob:** `020b7e1d417a5c008302a265b2aa325d2e91b246` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 27 linii.

- **Ustalenie:** Pasek grupowy bierze `href` z `PlayerMenuEditor::href` i ikony z `PlayerNavIcon::resolve/url`, które walidują destynację i nazwę pliku; teksty i sub-linki są escapowane (1–27). Nie potwierdzono XSS przez `src` w bieżącym przepływie.
- **Zależności sprawdzone punktowo:** templates/header.php:200–230,289–292; src/GameNavigation.php:33–88; src/PlayerNavIcon.php:34–53; src/PlayerMenuEditor.php:12–37.
- **Granica dowodu:** Pełny odczyt pliku na HEAD i statyczna kontrola wybranych zależności; bez PHP/MySQL/SMTP/HTTP ani renderu aplikacji.

### `api/v1/technical/fire.php`

**Blob:** `76e43015cc8b0c2ff62997e2bbcb01d152568f84` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 39 linii.

- **Ustalenie po ponownej kontroli nadzorcy:** POST wymaga Bearer i dodatniego staff_id (18–33). StaffTrait::getStaffMember filtruje po player_id (54–63), więc odrzucono tezę o wycieku nazw cudzego pracownika. fireEngineer (180–229) rozpoczyna transakcję przed try; przy błędzie w try łapie Throwable i mapuje na staff_missing/422 także błąd DB. W zwykłym endpointcie nie ma transakcji callera; test współbieżności i runtime otwarte.
- **Granica dowodu:** Pełny odczyt pliku, wybrane zależności i korekta tez Maxa; statycznie na HEAD, bez PHP/MySQL/HTTP i bez certyfikatu zamknięcia.

### `api/v1/technical/assign_task.php`

**Blob:** `65eebf11d34f868644f71ec11dad652e5049a2e1` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 51 linii.

- **Ustalenie po ponownej kontroli nadzorcy:** POST wymaga Bearer (22–39). TasksTrait::assignTaskLocked (108–143) może zwrócić queued=true dla zajętego pracownika, lecz odpowiedź endpointu (41–48) pomija tę flagę. getTaskDefinition (TechnicalTeamService:319–323) zwraca tablicę lub null, a TaskValidationTrait (9–12) odrzuca nieznany typ: hipoteza o pewnym wyjątku po zapisie odrzucona. Brak testu mobilnego E2E.
- **Granica dowodu:** Pełny odczyt pliku, wybrane zależności i korekta tez Maxa; statycznie na HEAD, bez PHP/MySQL/HTTP i bez certyfikatu zamknięcia.

### `api/v1/technical/hire_candidate.php`

**Blob:** `c03a89f5d09d475c67193cf850161eef48c8b119` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 39 linii.

- **Ustalenie po ponownej kontroli nadzorcy:** POST pobiera playerId z Bearer (18–33). HR/HiringTrait::getCandidateForHire (325–343) filtruje własność po player_id lub własnym request_id, a techniczna gałąź kontroluje saldo i debit w transakcji (110–189). Domniemany brak klucza success i nieautoryzowane zatrudnienie odrzucono. Nie testowano DB/HTTP ani pozostałych wariantów rekrutacji.
- **Granica dowodu:** Pełny odczyt pliku, wybrane zależności i korekta tez Maxa; statycznie na HEAD, bez PHP/MySQL/HTTP i bez certyfikatu zamknięcia.

### `api/v1/technical/repair_incident.php`

**Blob:** `1b192a8ea7602127c88a41bc43e03baf2feec23f` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 43 linii.

- **Ustalenie po ponownej kontroli nadzorcy:** POST wymaga Bearer i dodatniego incident_id (22–37). RepairDataTrait (18–28) sprawdza bankructwo tylko przy class_exists(Player,false); standardowy _bootstrap.php/IncidentService.php nie wymaga jawnie Player.php, więc warunkowo blok może zostać pominięty. Naprawa (48–53) nie pobiera drugiej opłaty, mimo przeciwnego docblocku endpointu; koszty naliczane przy incydencie. Wewnętrzny catch (91–94) cofa własną transakcję, zewnętrzny (105–108) mapuje błąd na false. Bez wykonania procesu PHP/DB.
- **Granica dowodu:** Pełny odczyt pliku, wybrane zależności i korekta tez Maxa; statycznie na HEAD, bez PHP/MySQL/HTTP i bez certyfikatu zamknięcia.

### `api/v1/technical/well_staff_assign.php`

**Blob:** `04a74859652ff4da4244d6e9e97cc1a66460e49c` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 41 linii.

- **Ustalenie po ponownej kontroli nadzorcy:** POST weryfikuje Bearer, dodatnie identyfikatory i allowlistę ról (18–35). WellStaffService::assign (29–165) filtruje własność, transakcyjnie odpina starą i zapisuje nową relację, lecz lockStaff nie sprawdza wyniku fetch (92–99); możliwy wyścig wymaga izolowanych dwóch połączeń. Tezy o pewnym double-assign lub błędzie GameLog po commicie w tym endpointcie nie dowiedziono: _bootstrap.php:31 wyłącza GameLog.
- **Granica dowodu:** Pełny odczyt pliku, wybrane zależności i korekta tez Maxa; statycznie na HEAD, bez PHP/MySQL/HTTP i bez certyfikatu zamknięcia.

### `api/v1/technical/well_staff_unassign.php`

**Blob:** `e72c83cadc09e5ec12bcf63f9eac1b60009245a9` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 40 linii.

- **Ustalenie po ponownej kontroli nadzorcy:** POST ogranicza rolę do operator/technician (18–34). Serwis unassign (170–196) nie ma własnej allowlisty: jego drugi caller src/WellStaffApi.php:95–101 przepuszcza dowolną niepustą rolę po auth/CSRF, a ternary wybiera technician_id; to aktywna ścieżka do wyzerowania pola technika. Serwis zwraca success także przy braku aktywnego przypisania. Dostępność bezpośredniej trasy /src na hostingu i skutek DB niepotwierdzone.
- **Granica dowodu:** Pełny odczyt pliku, wybrane zależności i korekta tez Maxa; statycznie na HEAD, bez PHP/MySQL/HTTP i bez certyfikatu zamknięcia.

### `api/v1/technical/well_staff_available.php`

**Blob:** `56308c6eb7e5a42ed85a7f2bfd987b85cbbfb0be` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 46 linii.

- **Ustalenie po ponownej kontroli nadzorcy:** GET wymaga Bearer i allowlisty roli (16–29). getAvailableStaff (312–354) wybiera również busy i już przypisanych mimo nazwy available; MAX(wsa.well_id) i MAX(w.location_name) agreguje niezależnie, więc para może być niespójna przy wielu aktywnych przypisaniach. Endpoint emituje nazwę odwiertu, nie jego ID (31–43). Warunkowe dla danych/klienta, bez DB/UI.
- **Granica dowodu:** Pełny odczyt pliku, wybrane zależności i korekta tez Maxa; statycznie na HEAD, bez PHP/MySQL/HTTP i bez certyfikatu zamknięcia.

### `api/v1/technical/available_tasks.php`

**Blob:** `f43289414ce2e6f5ccd58a7a92078b9bf6efe0a5` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 87 linii.

- **Ustalenie po ponownej kontroli nadzorcy:** GET filtruje staff przez właściciela (25–38) i zwraca tylko zadania bez pickera hub/pipeline (44–60). SQL wells (65–74) wyklucza sold/abandoned; w ci-schema.sql:2134 abandoned nie ma, a seized/blowout mogą być w tej liście, inaczej niż w getWellsStaffStatus (WellStaffService:265–279). Kolumny location_name i well_name istnieją w schemacie (2152,2159), więc hipotezę o brakujących kolumnach odrzucono. Nie jest to dowód, że assignTask przyjmie taki odwiert.
- **Granica dowodu:** Pełny odczyt pliku, wybrane zależności i korekta tez Maxa; statycznie na HEAD, bez PHP/MySQL/HTTP i bez certyfikatu zamknięcia.

### `api/v1/technical/data.php`

**Blob:** `5bdd50bb3cc06e4d2e4ecf34bd62e98cc54dbad4` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 135 linii.

- **Ustalenie po ponownej kontroli nadzorcy:** GET mapuje staff i stan odwiertów (22–31,89–135). getWellsStaffStatus (WellStaffService:283–304) zwraca well_id/well_name/has_operator, więc hipoteza Maxa o zawsze pustych polach jest fałszywa. SELECT nie pobiera well_name, ale !empty($r[well_name]) nie ostrzega o brakującym kluczu w PHP; fallback na location_name działa (267,287) — prostujemy błędną sugestię wcześniejszej notatki. Statusy odwiertów różnią się od available_tasks; brak testu endpointu.
- **Granica dowodu:** Pełny odczyt pliku, wybrane zależności i korekta tez Maxa; statycznie na HEAD, bez PHP/MySQL/HTTP i bez certyfikatu zamknięcia.

### `api/notifications/mark-all-read.php`

**Blob:** `29ced6e26d45cd8e004b945d02609b82302af3ca` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 30 linii.

- **Ustalenie po ponownej kontroli nadzorcy:** Endpoint wymaga sesji i CSRF (2–19), nie sprawdza jawnie metody. DirectorNotificationService::markAllAsRead (397–406) aktualizuje wszystkie nieprzeczytane gracza, także wygasłe, podczas gdy getUnread/countUnread je filtrują (341–347,412–418); masowy zapis nie dodaje historii jak pojedynczy markAsRead. Warunkowa rozbieżność licznika, bez SQL/HTTP.
- **Granica dowodu:** Pełny odczyt pliku, wybrane zależności i korekta tez Maxa; statycznie na HEAD, bez PHP/MySQL/HTTP i bez certyfikatu zamknięcia.

### `api/notifications/mark-read.php`

**Blob:** `c30d29209cd818eb57ccbd4949b97287e58afc1a` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 35 linii.

- **Ustalenie po ponownej kontroli nadzorcy:** Endpoint wymaga sesji i CSRF (2–25); markAsRead (DirectorNotificationService:373–391) UPDATE po id+player_id, a potem osobny INSERT historii (442–454) bez transakcji. Gdy INSERT rzuci, odczyt może pozostać zapisany mimo odpowiedzi 500; rowCount=0 zwraca false z HTTP 200. Aktywny widok activity_center.php:30–39 pokazuje maks. pięć pozycji przy liczniku wszystkich; JS director_notifications.js:67–71 po kliknięciu liczy tylko pozostałe w DOM. Bez uruchomienia DB/UI.
- **Granica dowodu:** Pełny odczyt pliku, wybrane zależności i korekta tez Maxa; statycznie na HEAD, bez PHP/MySQL/HTTP i bez certyfikatu zamknięcia.

### `api/v1/auth/logout.php`

**Blob:** `2db8ac747bb0b3e9bda191410fd2ce21452e7143` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 24 linii.

- **Ustalenie po ponownej kontroli nadzorcy:** POST/DELETE po poprawnym Bearer (13–24); apiRequireAuth (_bootstrap.php:72–79) kończy 401 przy braku tokenu, więc gałąź null po getRawToken jest nieosiągalna w standardowej ścieżce. ApiAuth::revokeToken (87–92) usuwa token bez lokalnego catch; globalny ApiErrorHandler może odpowiedzieć JSON 500 przy błędzie DB. Surowy token jest przechowywany w api_tokens (ApiAuth:69–78,124–137): to osobne ryzyko bezpieczeństwa, nie dowód exploitu. Brak HTTP.
- **Granica dowodu:** Pełny odczyt pliku, wybrane zależności i korekta tez Maxa; statycznie na HEAD, bez PHP/MySQL/HTTP i bez certyfikatu zamknięcia.

### `api/v1/auth/webview-bridge.php`

**Blob:** `01a6ca5868659f85dfdc18d913463f64492bbf41` · **Status:** `SOURCE_READ_DEPENDENCIES_OPEN` · **Odczyt:** 27 linii.

- **Ustalenie po ponownej kontroli nadzorcy:** POST po Bearer (13–22) tworzy URL z tokenem (MobileWebBridge:63–83), gdzie hasz jest zapisany w DB, a link zawiera sekret w query string. consume (87–129) warunkowym UPDATE used_at IS NULL i rowCount=1 chroni przed zwykłym replay; schema ci-schema.sql:1692 ustawia email_verified NOT NULL DEFAULT 0, więc hipoteza o aktywacji NULL w aktualnym schemacie odrzucona. Domena bazowa pochodzi z config/mail.php lub fallbacku; brak testu staging, logów i wyścigu dwóch połączeń.
- **Granica dowodu:** Pełny odczyt pliku, wybrane zależności i korekta tez Maxa; statycznie na HEAD, bez PHP/MySQL/HTTP i bez certyfikatu zamknięcia.
