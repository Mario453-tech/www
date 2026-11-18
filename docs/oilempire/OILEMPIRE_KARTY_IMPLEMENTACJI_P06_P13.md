# OilEmpire — karty implementacyjne P06–P13

> **Kadry — wariant A do implementacji:** [mechanika i parametry §19](OILEMPIRE_KADRY_ZATRUDNIENIE_ROZSZERZENIE.md) oraz [HR-CONTRACT01](OILEMPIRE_KADRY_KONTRAKT_IMPLEMENTACYJNY.md) ustalają kierunek P07–P09/P16. DOM01/DOM02/DOM16 mają decyzje A, ale wymagają propagacji do kontraktów maszynowych, kalibracji i testów przed aktywacją. Starsze karty opisują bazowy model historyczny; przy wdrażaniu A stosuje się wskazaną deltę. Aktywny game_balance v1 nie został zmieniony.


> Część jednej specyfikacji. [Indeks](README.md), [plan P01–P25](OILEMPIRE_PLAN_PROGRAMOWANIA.md), [fundament P01–P05](OILEMPIRE_KARTY_IMPLEMENTACJI_P01_P05.md), [architektura](OILEMPIRE_ARCHITEKTURA_SYSTEMOWA.md), [API](OILEMPIRE_API_SPECYFIKACJA_TECHNICZNA.md), [panel](OILEMPIRE_PANEL_ADMINA_SPECYFIKACJA.md), [bezpieczeństwo](OILEMPIRE_BEZPIECZENSTWO_SILNIKA.md), [standard modułu](OILEMPIRE_STANDARD_MODULU_WDROZENIE.md).

Status: projekt do implementacji, bez zmian w kodzie gry. Karty rozwijają P06–P13, nie tworzą nowego wariantu ekonomii. Parametry pobierane z zatwierdzonej balance_version, a procesy w toku utrwalają wersję startową. Nazwy, typy i kontrakty dziedziczą fundament; w razie wykrytej sprzeczności źródeł karta jawnie wskazuje blocker, zamiast dobierać własną liczbę.

## 1. Wspólna granica implementacji

- Właściciele: Core firma/reputacje/majątek, Staff rekrutacja/zarząd/morale/szkolenia, Legal uprawnienia/koncesje, Extraction odwierty/geologia, Logistics transport i magazyny, Incidents procedury techniczne/awarie/naprawy. Finance/Ledger używane wyłącznie przez publiczne kontrakty.
- Poniższe dane stanowią docelowy model logiczny. Przed migracją sprawdzamy faktyczny szkielet i mapujemy ID/FK na jeden przyjęty wariant. Nie uruchamiamy destrukcyjnych migracji i nie edytujemy wdrożonych.
- Każda gospodarcza mutacja: actor + zasób właściciela + Idempotency-Key + expected_state_version; polityka również wewnątrz wspólnej akcji. API ma pełny prefiks /api/v1; web i Filament używają tej samej akcji. Jeśli metoda GET wymaga aktualnej ekonomii, korzysta z Settlement, a nie niezależnych formuł.
- LockPlan obejmuje rozliczenie i komendę, wszystkie strony i encje przed pierwszym lockiem. Rangi dodatkowe są częścią §23.5a: 13 Core dzieci firmy, 14 Staff i Training, 15 Legal, 16 Technical/BHP, 17 Incidents, 18 TechnicalTasks. Konta20, rezerwacje21, aktywa40–120 i append140 pozostają. W grupie kolejność tabel jest jawnie ustalana w rejestrze kontraktów, potem ID; żaden kontrakt nie może samowolnie wrócić do wcześniejszego rank.
- Zasoby fizyczne mają własne rezerwacje ilościowe. fund_reservations przechowuje pieniądze, nie bbl, ludzi czy stanowiska. Transfer surowca nie jest zakupem tworzącym nową ropę; zapas w transporcie pozostaje we wskazanej własności aż do receipt.
- Due-event ma stabilny typ+encję+slot, logiczne due_at i wersję procesu. expected_state_version wskazuje potrzebę ponownej walidacji; zwykła niezwiązana zmiana firmy nie może skasować legalnego ukończenia szkolenia, odwiertu czy kursu. Tylko terminalny stan/odwołanie/przekazanie zgodne z domeną czyni zdarzenie no-opem.
- RNG: nazwana decyzja i utrwalony seed/wynik; ponowienie requestu/job/sweepera nie losuje drugi raz. Stawki zmian ciągłych segmentowane w czasie. Przy rozliczeniu subgroszowych części utrzymujemy precyzyjny accumulator/resztę; nie zaokrąglamy kosztu niezależnie przy każdym GET.
- Zdarzenie/audit/FTS/ruch/rezultat idempotencji w transakcji. Dopiero transport outbox po commicie. Rozłączenie telefonu i błąd push nie cofają operacji. Emitujemy minimalny payload, bez prywatnych modeli lub sekretów RNG.
- UI obejmuje dane, pusty stan, loading, odmowę, konflikt wersji, błąd, ograniczony Internet oraz odświeżenie po powrocie. Komunikaty/SVG lokalne, PL/EN/DE, dostępność i pełna ścieżka mobilna. Panel read-only nie może ukrywać braku kontroli serwera.
- Draining blokuje nowe zobowiązania, pozwala dokończyć istniejące i zachowuje koszty/terminy zgodnie z regułą domenową. Disabled dopiero po legalnym przekazaniu albo domknięciu zobowiązań i zależności.

## 2. Odbiór i gotowość

Każda karta ma kryterium testu, nie wynik PASS implementacji. Właściwe S01–S18, test własności, retry i konkurencja na MySQL są obowiązkowe; test Python dokumentacji sprawdza tylko jawnie objęte kontrakty. Polityka wymagająca nierozstrzygniętego współczynnika nie ma domyślnej wartości produkcyjnej: nowy proces zwraca POLICY_NOT_APPROVED lub MODULE_UNAVAILABLE, a nie losowy koszt. To warunek gotowości wydania, nie mechanizm do wyłączenia obsługi już istniejących zobowiązań.

Karty z blokadą oznaczają konkretną operację do domknięcia. Nie wstrzymują przygotowania schematu, testów, widoku read-only i pozostałych niezależnych funkcji. Rejestr blokad i ich dowody znajduje się w końcowej sekcji oraz audycie.

## P06–P08 — Firma, reputacja i Staff

Karty rozszerzają [plan P01–P25](OILEMPIRE_PLAN_PROGRAMOWANIA.md) i [fundament](OILEMPIRE_KARTY_IMPLEMENTACJI_P01_P05.md). Źródła liczb: architektura §1–§5/§10/§14/§19/§21/§22.6/§27.1a oraz YAML core/staff. Są specyfikacją, nie deklaracją działania kodu.

**Wspólne dane:** publiczne ID i FK według konwencji fundamentu, czasy DATETIME(3) UTC, pieniądze BIGINT grosze (API string), reputacje i morale DECIMAL(7,3) dla zachowania połówek, enum w PHP i VARCHAR w DB. Aktor i firma ustalane z sesji/polityki, nigdy z dowolnego company_id klienta. Mutacje mają Idempotency-Key, oczekiwaną state_version i ponowną walidację pod blokadą; błędy 401/403/404/409/422/429 wg API. Każdy response zawiera właściwe dane i meta state_version/server_time/balance_version.

Obowiązuje jedna tabela §23.5a architektury. Core13; Staff/Training14 (dla A pełna lista 30 tabel jest w §23.5a.1 i kontrakcie maszynowym); Legal15; Technical/BHP16; Incidents17; TechnicalTasks18; geologia39; aktywa40–80; append140. Wewnętrzna kolejność tabel to dokładna kolejność z §23.5a, nie lokalny wariant z przykładu.

### P06-F01 — Utworzenie firmy i jednorazowy starter

**Dane / właściciel Core:**
- companies: id, owner_user_id FK users NOT NULL UNIQUE, name VARCHAR(80), normalized_name VARCHAR(80) UNIQUE, status VARCHAR(32), dormant_reason nullable, founded_at, state_version BIGINT UNSIGNED, starter_grant_id nullable. Identyfikator usera nie jest identyfikatorem firmy.
- starter_identity_claims: identity_hash BINARY(32) PK (HMAC wersjonowanym sekretem), first_user_id FK, first_company_id nullable FK, claimed_at. Surowy fingerprint/metoda płatności nie trafiają do klucza ani logu; IP samodzielnie nie jest dowodem multikonta.
- company_starter_grants: id, company_id FK UNIQUE, claim_hash nullable, package_kind full/secondary, balance_version_id, status preparing/granted, granted_at nullable, money_value_minor, in_kind_value_minor, manifest_json (zamknięta lista składników), operation_id UNIQUE. Stan granted i wszystkie skutki zatwierdzane razem, bez widocznego półpakietu.
- starter_grant_items: id, grant_id FK, item_code, resource_ref nullable, value_minor, quantity nullable, effect_key UNIQUE; UNIQUE(grant_id,item_code). Służy wyłączeniu startera z progów, nie ponownemu transferowi pieniędzy.

**Kontrakt / API:** core.company v1 CreateCompany(actor, name, clientKey) → CompanyDto. POST /api/v1/company: name; odpowiedź 201 z company_id/status/created_at/starter_status. Brak osobnego publicznego endpointu „przyznaj starter”. COMPANY_ALREADY_EXISTS/NAME_TAKEN/STARTER_DEPENDENCY_UNAVAILABLE → 409. Niezdolność pełnego provisioningu nie może zostawić aktywnej firmy z samymi 4M. P06 można programować/testować na kontraktach wcześniej, ale pełny starter produkcyjny ma bramkę gotowości P09/P11/P12/P17/P22 dla wymaganych składników; jest to zależność aktywacji, nie cykl runtime providers ani powód kopiowania logiki.

**Przebieg:** autoryzacja zweryfikowanego konta; operacja idempotentna; rezerwacja tożsamości startera przed transferami, company/claim w jednolitym planie. Pełny pakiet pieniężny: 2M PLN cash +2M PLN bank, wiarygodność70, magazyn20k bbl pojemności, magazyn początkowo pusty. ProvisionStarterBundle wywołuje publiczne kontrakty Extraction/Logistics/Staff z §27.1a: odwiert szkoleniowy40 bbl/h/7000bbl, polowa rura, 7 dni miejsca NPC, używana cysterna500bbl z kierowcą i 800bbl w buforze huba. 800bbl nie znajduje się w pustym magazynie głównym; kredyt oil_movements raz. Te same terminy/wartości jak YAML onboarding, brak nowych kwot w kodzie.
Jeśli zależności P11/P12 nie są gotowe, provisioning produkcyjny jest niedostępny; fixture P06 testuje kontrakty, nie udaje pełnego FTUE. Wymagane rozszerzenie schematu company/claim nie daje prawa ominąć ścieżki secondary: ponowny odcisk nie dostaje startera; pożyczka500k z SYS_TREASURY przez Banking na warunkach §14.1/§17.1, bez ręcznego dopisania salda. Adaptor Banking niegotowy → nie kończymy secondary provisioningu.

**FTS / zdarzenia:** cash/bank z właściwego konta SYS, rejestr reason_code startera w manifeście; to samo operation_id i osobny effect_key dla każdej pary. Append CompanyCreated/StarterGranted po skutkach, dispatch po commicie. Powrót z dormant nie wywołuje tej akcji.
**UI / odbiór:** formularz nazwy i jawny status; 2 równoległe rejestracje tego samego usera/claim → jeden grant; rollback po przyznaniu cash cofa cały pakiet; retry po timeout odtwarza wynik; ten sam grant nigdy nie tworzy drugiego kierowcy/800bbl; brak sprzętu downstream blokuje aktywację, bez fikcyjnego PASS.

### P06-F02 — Zmiany reputacji i propagacja

**Dane:** company_reputation_state: company_id PK/FK, company_credibility DECIMAL(7,3), b2b_reputation_score DECIMAL(7,3), contract_reputation DECIMAL(7,3), black_market_score DECIMAL(7,3), twofa_bonus_granted BOOL, twofa_bonus_active BOOL, twofa_baseline DECIMAL nullable, last_black_trade_at nullable, state_version. Zakresy kredytowego credit_score/trust pozostają w Banking, nie duplikujemy jego logiki. company_reputation_events: id, company_id, source_event_id, metric, source_code, requested_delta, applied_delta, before_value, after_value, propagation_kind, balance_version_id, occurred_at; UNIQUE(source_event_id,metric,propagation_kind), INDEX(company_id,occurred_at). Append-only.

**Kontrakt:** core.reputation v1 ApplyReputationEvent(company, sourceEvent, changes[]) → ReputationResult. GET /api/v1/company/reputation oraz /history (kursor); mutacja wyłącznie nazwanymi akcjami domenowymi/GM capability, nie PATCH arbitralnego pola.
**Transakcja:** company/rank13, deduplikacja sourceEvent i pełny zapis przed/po. Credibility clamp0–100, B2B clamp0–100. Dla zwykłych zdarzeń 50% delty wiarygodności propaguje do B2B; zachowujemy ułamki bez zaokrąglania do całych punktów. contract_reputation zmienia wyłącznie zdarzenie kontraktowe, nigdy automatyczna propagacja.
**Wyjątki kanoniczne:** bonus/cofnięcie2FA nie propaguje B2B (§14.3/§21). Zdarzenie sankcji kartelu ma w §26.3 jawne −15 credibility i −15 B2B, a ogólna reguła przewiduje propagację 50%. To nieuzgodniona interakcja CARTEL-PROPAGATION-01 (P21): przed aktywacją tej konkretnej sankcji ustalić, czy −15 B2B jest efektem finalnym czy dodatkowym. Nie tworzymy nowego wyjątku ani trzeciej kary domyślnie. Bonus2FA raz na konto/firmę: ponowne włączanie nie przyznaje nowego +5; cofnięcie wg zapisanego baseline, bez obniżenia poniżej stanu sprzed bonusu.
**Zdarzenia:** ReputationChanged z referencją do immutable eventu; kredytowy scoring reaguje przez Banking, bez prywatnego modelu.
**Odbiór:** duplikat źródła neutralny; +3 daje +1,5 B2B; 2FA +5/−5 nie zmienia B2B; kartel nie karze dwa razy; contract_reputation niezależna. Przy credibility na cap zapis requested/applied pozwala audytować faktyczny efekt.

### P06-F03 — Bieżący majątek, Tier i tarcza

**Dane:** company_wealth_state: company_id PK/FK, state_version, as_of, pricing_version, balance_version_id, net_worth_minor, tax_base_minor, shield_base_minor, starter_value_minor, drip_30d_minor, tier_base_minor, tier VARCHAR(8), source_state_hash BINARY(32). Oddzielne append-only net_worth_snapshots UNIQUE(company_id,snapshot_date) zgodnie z P16; bieżący wiersz nie nadpisuje historycznego podatku. company_shields: company_id PK, granted_at, expires_at, permanently_removed_at nullable, source starter/recovery, reason_code, state_version.

**Kontrakt:** core.wealth v1 RefreshCurrentWorth(company,targetAt) → WealthDto; core.shield v1 EvaluateShield(company,decisionAt) → ShieldDecision. GET /api/v1/company/wealth i /api/v1/company/shield; odczyt wymaga aktualnego settlement. Źródła wartości przez publiczne AssetValuationProvider/InventoryOwnershipProvider/Ledger/Monetization, nie JOIN i UPDATE prywatnych tabel modułów.
**Reguły:** podatek historyczny00:00; tarcza/Tier bieżąco. Aktywa do progów100% systemowej wyceny, bez podatkowego0,70; zapasy100% referencji; rafineria swoim kanonem bez podwójnego dyskonta. Starter manifest odejmowany raz; drip30d tylko w tier_base wg §22.6.1. Przedziały Tierów i próg tarczy bierzemy z YAML. Tarcza7dni lub próg10M netto bez startera; wygasanie sprawdzane z zegara w decyzji. Pierwsza próba agresji/PvP lub handlu czarnorynkowego trwale zdejmuje tarczę zgodnie z §19.2; nie jest przywracana przez zmniejszenie majątku.
**Blokady:** rank10/11/13 i dalsze źródła wg przygotowanego LockPlan. Na stale source_state_hash wynik odrzucony/ponowiony, nie publikujemy pozornie atomowego majątku. Snapshot podatku w P16, nie w GET tarczy.
**Odbiór:** zmiana własności w południe zmienia Tier/tarczę od razu; podatek poprzedniego00:00 bez zmian; 1B dokładnie TierIV; zero ropy w magazynie nie jest20k zapasu; escrow/licencja/ropa w tranzycie tylko u jednego właściciela.

### P06-F04 — Urlop, dormant i bezpieczny powrót

**Dane:** company_status_periods: id, company_id FK, status, reason_code nullable, starts_at, ends_at nullable, resume_after nullable, balance_version_id, state_version; INDEX(company_id,starts_at). companies.status jest projekcją bieżącego okresu. company_time_pauses: id, company_id, started_at, ended_at nullable, cause, operation_id UNIQUE; pozwala przesunąć zamrożone terminy dokładnie raz. Due-events mają decyzję pause/resume przez kontrakt Simulation, bez przesuwania ręcznego w SQL.

**API:** POST /api/v1/company/vacation {duration_hours, expected_state_version}; POST /api/v1/company/vacation/end; POST /api/v1/company/recovery; GET /api/v1/company/status. Step-up wg §14.5. VACATION_BLOCKED/TOO_EARLY/RECOVERY_DEBT_PENDING →409 z bezpiecznym kodem przyczyny.
**Warunki:** urlop48h–30dni; brak major/catastrophe, strajku, B2B accepted/in_transit i egzekucjiFaza1+. LockPlan obejmuje predykaty tych aktywnych procesów, nie tylko UI. Urlop zamraża produkcję/pensje/OPEX/cooldowny/terminy, PvP obustronnie niedostępne. Powrót po min48h; kolejny urlop po7dniach. Kończenie/przesuwanie terminów atomowo z okresem pauzy; retry nie dodaje drugiej pauzy.
**Dormant:** stan i dormant_reason jawne; no_funds/inactivity/ban/anonymized/module_disabled mają osobne wejścia, uprawnienia i zasady. Brak gotówki/banku zatrzymuje produkcję i nowe incydenty, zobowiązania narastają jako dług; brak bankructwa wcześniej niż14dni dormant. Recovery po rozliczeniu przez Banking/Finance, nie reset kont. Ochrona powrotna7dni zgodnie z §19.4, dwukierunkowa.
**Zdarzenia/odbiór:** CompanyStatusChanged, VacationStarted/Ended, RecoveryRequested, outbox w transakcji. Wyścig start dostawy/urlop → legalny jeden wynik; żaden termin nie znika; ban nie jest urlopem; powrót nie przyznaje startera.

Dla P07–P09 obowiązuje [delta HR-CONTRACT01](OILEMPIRE_KADRY_KONTRAKT_IMPLEMENTACYJNY.md) i Kadry §19. Starsze nazwy tabel w kartach, które nie zostały poniżej przepisane, są mapą historyczną do weryfikacji przy migracji, nie poleceniem tworzenia drugiego modelu Staff.

### P07-F01 — Proces rekrutacji i oferty

**Rozszerzenie profilu:** [Kadry §2.1](OILEMPIRE_KADRY_ZATRUDNIENIE_ROZSZERZENIE.md) określa ukryte cechy i obserwacje HR. Rzeczywiste traits pozostają serwerowe; publiczny DTO zwraca tylko ocenione dane, a morale kandydata jest nieocenione. Rozkład60/30/10 jest kandydatem do kalibracji. Generator cech/absencji wymaga HR-CFG01/03/05; zatrudnienie i rehire nie losują osoby ponownie.

**Dane A:** `staff_recruitment_campaigns`, `staff_candidate_offers`, `staff_persons` i ukryte `staff_person_traits` według HR-CONTRACT01 §3. Oferta odnosi się do trwałej osoby; ponowne zatrudnienie nie losuje profilu. Jawny katalog kanał×zasięg×budżet, cennik i wagi 0–N określa Kadry §19.2; kampania może zwrócić 0 ofert. Headhunter pozostaje osobną opcją 25 GBBL/raz dziennie.
**API i transakcja A:** QuoteCampaign/StartCampaign/GenerateOffers z HR-CONTRACT01 §5–§6; limit dwóch kampanii, cena utrwalona w quote, Ledger po LockPlan, due-event/outbox raz. Publiczny DTO nie ujawnia cech ukrytych ani sekretu RNG. Starsze nazwy `recruitment_requests`/`candidate_offers.traits_json` nie są DDL wariantu A.
**Odbiór:** dwa równoległe starty przy jednym wolnym slocie dają jeden sukces; brak wycieku cudzej oferty; powrót po5min generuje raz; wersja procesu zachowana; RECRUITMENT_LIMIT/HR_STRIKE/INSUFFICIENT_FUNDS.

### P07-F02 — Zatrudnienie i rozwiązanie umowy

**Dane A:** `staff_persons` → `staff_employments` → `staff_contract_versions`, z osobnymi historiami, umiejętnościami i zdarzeniami HR-CONTRACT01 §3. Jedna oferta tworzy najwyżej jeden okres zatrudnienia; osoba może wrócić bez nowego losowania. Stare `employees.salary_monthly_minor` i `source_candidate_id UNIQUE` nie są docelowym schematem A.
**API i akcje A:** HireEmployee/EndEmployment według HR-CONTRACT01 §5–§7. Akceptowana wersja umowy zawiera formę, wymiar etatu i stawkę; Payroll rozlicza należności. Decyzja o wypowiedzeniu oraz logiczny koniec zatrudnienia to dwa etapy. Kadry §19.3 rozstrzygają DOM02: −10 jednorazowo przy natychmiastowym zakończeniu albo −8 przy zwykłym wypowiedzeniu z limitem −24/14 dni; jedna decyzja nie uruchamia obu kar. Populacja sporu to snapshot z logical_at decyzji. Zakończenie nie usuwa osoby, certyfikatów ani długu.
**Odbiór:** kandydat zatrudniony raz; błędna pensja/zmieniony quote odmowa; zakończenie zatrudnienia nie usuwa osoby, okresu zatrudnienia ani certyfikatów; test notice/kar według decyzji A §19.3 i kalibracji.

### P07-F03 — Przydziały i rezerwacja dostępności

**Dane A:** `staff_roster_versions`, `staff_roster_intervals`, `staff_time_reservations` i `staff_assignments` według HR-CONTRACT01 §3. Jeden kalendarz przedziałowy chroni czas pracy i konflikt kursu, zadania technicznego oraz obsady; `employee_availability.active_job_*` ze starszej karty nie wystarcza jako źródło prawdy. Dla `staff_assignments` suma aktywnych alokacji ≤100%; owner celu potwierdza prawo do obiektu.
**Kontrakt i transakcja A:** PublishRoster/ReserveTime oraz przydział przez Scheduling, z kontrolą overlap pod blokadą employment i idempotencją źródła. Staff nie zapisuje tabel celu; domeny korzystają z publicznego DTO dostępności. API i pełne kody odmowy określa HR-CONTRACT01 §6–§7; planista nie może nadpisać opublikowanego grafiku.
**Odbiór:** równoległe60%+60% → odmowa jednego; cudzy hub/niekwalifikowany operator →403/422; naprawa i kurs nie rezerwują tej samej dostępności; globalny bonus operatora nie działa poza targetem.

### P07-F04 — Zarząd i bramki kompetencyjne

**Dane:** board_positions: company_id FK, position_code, employee_id nullable FK, state_version, appointed_at nullable, vacated_at nullable; UNIQUE(company_id,position_code), UNIQUE(employee_id) dla obsadzonego dyrektora. Role code finance/technical/hr/legal/logistics z zamkniętego słownika. board_history: id, company_id, position_code, employee_id, effective_at, source_event_id UNIQUE; append-only.

**Kontrakt:** staff.board v1 RequireRole(company,position,action,decisionAt) → BoardAccessDecision. API GET /api/v1/board/positions; PUT /api/v1/board/positions/{position} {employee_id,expected_state_version}; DELETE zwalnia stanowisko bez usuwania employees.
**Reguły:** pracownik własny, dyrektorska kwalifikacja i aktywna relacja, brak równoczesnego obsadzenia drugiego stanowiska przez tę osobę. Ustawienie budżetu/podpis kontraktu/kredyt wymaga dyrektoraFinansów; odczyt finansów zawsze dostępny. Wakat zachowuje spot20kPLN/h, spłatę, pensje i mediatora; te akcje wymagają osobnego emergency reason_code/capability w logu, nie pełnego ominięcia bramek.
**Blokady:** company, Staff employees/board_positions rank14, dopiero potem konta i zasoby akcji. Zewnętrzna akcja zawsze ponownie sprawdza obsadę w swoim LockPlan; cache UI nie autoryzuje. Odejście dyrektoraLegal degraduje konwój zgodnie z P12/P18, nie usuwa kursu.
**Odbiór:** dwie nominacje do jednego slotu →jedna; zwolnienie/przeniesienie w trakcie kontraktu nie zmienia parametrów już podpisanych, ale blokuje nowe wymagające dyrektora; test jawnej ścieżki awaryjnej.

### P08-F01 — Naliczanie wynagrodzeń i ocena zadowolenia

**Dane A:** `staff_contract_versions` przechowuje zaakceptowane warunki, `staff_pay_entitlements` naliczenia z dokładną resztą i source keys. Nie tworzyć równoległego źródła `employee_salary_terms`/`salary_accrual_state` dla A. Wspólna akcja Payroll korzysta z jednego kalendarza UTC i polityki HR-PAY-01; zmiana umowy zamyka stary segment, a brak środków pozostawia należność. Kontrakt oceny zadowolenia nadal stosuje §4.1 i stawkę rynkową z YAML.
**DOM01 A:** podstawa miesięczna, dzielenie według rzeczywistych przedziałów miesiąca UTC i zachowanie reszt są określone w HR-PAY-01 oraz Kadry §19.3. Finalna migracja i test sumy miesiąca są bramką przed aktywacją, nie decyzją o wyborze dzielnika.
**Odbiór:** podwyżka w połowie segmentu używa dwóch stawek, retry nie tworzy drugiej pensji, zaległość nie znika po zwolnieniu, suma ułamków zgodna z polityką. Pensja rynkowa0 odrzucona, dzielenie przez0 niemożliwe.

### P08-F02 — Żądanie podwyżki i decyzja

**Dane:** employee_raise_requests: id, employee_id FK, company_id FK, open_slot TINYINT nullable (1 dla otwartego), requested_salary_minor, status pending/deferred/accepted/countered/refused/escalated/cancelled, due_at, deferral_count TINYINT, balance_version_id, state_version, decision_event_id nullable; UNIQUE(employee_id,open_slot), INDEX(company_id,status,due_at). Zamknięcie ustawia open_slot=NULL; tylko jedno otwarte żądanie dzięki UNIQUE, nie sam SELECT.
raise_decisions: id, request_id FK, action_code, offered_salary_minor nullable, outcome, rng_slot nullable, rng_result nullable, source_event_id UNIQUE, occurred_at; append-only.

**API:** GET /api/v1/hr/raise-requests; POST /api/v1/hr/raise-requests/{id}/decision {action:accept|counteroffer|refuse|defer, offered_salary_minor?, expected_state_version}. Wyjście status, salary_effective_from, due_at, deferral_count, state_version. 409 REQUEST_CLOSED/DEADLINE_PASSED/DEFERRAL_USED; 422 INVALID_OFFER. Nie ma endpointu gracza tworzącego sobie żądanie ani wybierającego wynik RNG.
**Reguły:** trigger satisfaction<60 w fazie6; deadline72h, odroczenie raz+48h. Akceptacja morale+20 i lojalność+5 według kanonu; wartości cech są ograniczane ich legalnym zakresem. Kontroferta używa zapisanej formuły5–95%, negocjatoraHR/personelu, lojalności/ambicji, jeden deterministic slot na decyzję; sukces+8 morale, odmowa−20. Kwota żądanej podwyżki musi pochodzić z wersjonowanej polityki żądań; brak wpisanego cennika oznacza błąd schematu, nie losową „rozsądną kwotę” programisty.
**Transakcja:** company/Staff employee/request pod blokadą, rozliczenie starej stawki do decisionAt przed nową wersją staff_contract_versions; morale zdarzenie i decyzja atomowo z outbox. Przy deadline≤now właściwa ścieżka due-events wygrywa, nie da się odroczyć po terminie. Zmiana pensji sama nie powoduje jednorazowej wypłaty całej miesięcznej kwoty.
**Odbiór:** jedna otwarta prośba; dwa kliknięcia accept nie podnoszą pensji drugi raz; drugi defer409; HTTP vs job na deadline jeden wynik; RNG po retry identyczne.

### P08-F03 — Dobowy model morale i populacja działu

**Rozdzielenie stanu:** ukryta podatność na niedyspozycję nie jest morale. Absencja nie uruchamia automatycznej kary morale ani nowego modelu strajku. Wpływ współpracy/konfliktowości integrujemy przez pojedyncze zdarzenia istniejącego modelu, zgodnie z Kadry §2.1.

**Dane:** employee_morale_events: id, employee_id FK, company_id FK, department_code, event_type, amount_pts DECIMAL, occurred_at, decays_until nullable, source_event_id, balance_version_id; UNIQUE(employee_id,source_event_id,event_type). employee_morale_daily_snapshots: employee_id,date_utc,morale_before,target,morale_after,inputs_hash,policy_version; UNIQUE(employee_id,date_utc). department_morale_snapshots: company_id,department_code,date_utc,population_count,employee_mean,lagged_company_mean,morale; UNIQUE(company_id,department_code,date_utc).

**Kontrakt:** staff.morale v1 ApplyMoraleEvent oraz EvaluateDailyMorale(company,date) → list zmian. GET /api/v1/hr/morale z bezpiecznym rozbiciem składników i widoczną populacją. Miesięczne pensje/minimum tylko do satisfaction; żadnego przeliczenia kosztu w GET.
**Algorytm:** target clamp0–100, wagi0.45/0.15/4×0.10, s cap100, pozostałe W/R/B/H/U/P zgodnie z YAML. Wartości penalties_P zapisane ujemnie w YAML są normalizowane do dodatniej wielkości kary przy odejmowaniu P; nie wolno odejmować ujemnej sumy i dawać nagrody za karę. Dobowy drift max−12/+6. Company lag-1, bez równania uwikłanego. Bieżące zdarzenie ±20/+8 zapisane raz; dzienny trend jest osobnym krokiem, bez ponownego odtwarzania instant eventu. Wyłączenie ze średniej: zarząd, in_training, unassigned i już strajkujący; populacja0 nie generuje sporu. Dla wariantu A populacja sporu jest snapshotem z logical_at decyzji o zakończeniu współpracy (§19.3); nie utrzymujemy fikcyjnego członkostwa przez 24h.
**Czas:** dla długiego Δt przetwarzamy logiczne granice dobowe ze snapshotem poprzedniej doby; nie stosujemy 7× zmiany raz z końcowym inputem. Operacja jest agregowana/sortowana i ma dzienny durable_slot, unikamy pętli per sekundę. Historyczne źródła overload/incident30d/fatal180d są jawnie wersjonowane.
**Odbiór:** suma wag1, satisfaction120 daje składnik100, lag snapshot nie zależy od kolejności pracowników, kary wygasają wg YAML, brak sporu w pustym dziale, replay doby bez zmiany.

### P08-F04 — Spór, strajk, negocjacje i mediator

**Dane:** department_strikes: id, company_id FK, department_code, open_slot TINYINT nullable, status dispute/threat/on_strike/mediated/resolved, created_at, ultimatum_due_at nullable, mediator_until nullable, last_round_no, balance_version_id, state_version; UNIQUE(company_id,department_code,open_slot), INDEX(status,ultimatum_due_at).
strike_rounds: id,strike_id FK,round_no,dialogue_template_id,offer_json (zamknięty schemat pieniędzy/warunków), outcome, rng_slot,source_event_id; UNIQUE(strike_id,round_no), UNIQUE(source_event_id).
employee_dialogue_templates: id,template_key UNIQUE,category,is_active; translation/template inputs z PL/EN/DE, bez kodu HTML/JS.
strike_members: strike_id,employee_id,joined_at,left_at nullable; UNIQUE(strike_id,employee_id).

**API:** GET /api/v1/hr/strikes/{id}; POST /api/v1/hr/strikes/{id}/negotiate {expected_round,offer_minor,expected_state_version}; POST /api/v1/hr/strikes/{id}/mediate {mode:government|legal_director,expected_state_version}. Return status/round/nextDueAt/bezpieczny dialog. UNKNOWN_DIALOGUE, ROUND_CHANGED, MEDIATOR_UNAVAILABLE, NO_NEGOTIATOR, INSUFFICIENT_FUNDS. Klient nie podaje chance/outcome.
**Stany:** średnia<35 tworzy dispute, potem threat z min48h ultimatum, brak ugody uruchamia on_strike. Dokładne wejście threat i formuła wyniku rund muszą być wersjonowanym modelem negocjacji; jeśli źródła opisują tylko zależność od oferty/skilla/zaufania bez współczynników, produkcyjna negocjacja jest zablokowana do karty polityki NEGOTIATION-01, nie zmyślamy procentów.
**Skutki operacyjne:** publiczny StaffStrikeEffectsDto z due/effectiveAt: logistyka spadek30%+50%udział, >60% stop trasy; +20%koszt cystern, +25%reakcja; technika brak własnych TTS, zewnętrzni×2.5 koszt/×1.4czas, zużycie×1.5, blowout×2; HR blokuje rekrutację i−1morale/24h pozaHR. Legal/Finanse/Rafineria wg §5.1; konsumenci nie zapisują prywatnych employees.
**Mediator:** zamówiony dla patuHR50kPLN lub Legal skill_negotiation≥3 z−10p.p., automatyczny po72h nieobecności podczas strajku koszt10%cash min25k, przy braku środków mediator_debt25k/10%rok. Nie łączymy cen mediacji manual/auto w jedną stawkę. Auto mediator trwa7dni i łagodzi skutki×0.5; bramka timestamp, jeden wpis zobowiązania przez Banking. Job/HTTP/sweeper przez ten sam due handler.
**FTS / UI / testy:** płatna oferta/mediacja ma rezerwację i FTS przy legalnej akceptacji, odpowiedź po commicie; upadła runda nie pobiera ponownie kwoty po retry. Widok HR wymaga minimum80 szablonówPL/EN/DE wg YAML. Test macierzy populacji, terminu48h, mediatora72h, patuHR, strajku logistyki i kończenia rozpoczętych procesów w draining.

## P09 — szkolenia jako obszar Staff

Źródło: architektura §6/§6.1, §21, §22.3, §23; YAML modules.staff.params.training i refining.params.hr_training. Training nie ma osobnego modułu runtime ani własnego korzenia balansu. Wspólne jednostki/ID/FK/PL–EN–DE i envelope z kart P01–P05 obowiązują. Ranga Staff **14**, po companies10/settlement11/due12 i przed Legal15/kontami20; wewnątrz Staff pełna kolejność tabel §23.5a, z person/employment/skills oraz kalendarzem rezerwacji przed assignments i rekordami treningu. Nie zaczynamy joba od blokowania kursu.

### P09-F01 — katalog, kwalifikowalność i niezmienna oferta kursu

**Właściciel:** Staff. Publiczny kontrakt staff.training.v1: ListPrograms(Actor, company_id, employment_id?) i QuoteEnrollment(employment_id, program_version_id) zwracają DTO, nie modele. Źródło kosztu/czasu/pass_rate to zatwierdzony balans oraz wersjonowany katalog, nie pola ręcznie podane przez klienta.

**Dane A:** `staff_training_program_versions` przechowuje jeden zatwierdzony wiersz programu/poziomu: program_code, skill_code, level, version_no, duration_seconds, price_minor, base_pass_rate_pct, pass_threshold, skill_delta, skill_cap, policy_version i params_hash. `QuoteEnrollment` jest odczytem tej niezmiennej wersji i stanu pracownika; nie tworzy drugiego katalogu `training_programs/training_program_levels/training_catalog_versions` ani tabeli `training_quotes`. Dla technicznych I/II/III wariant A wybiera 20k/50k/100k PLN, 24/48/72h, próg70 i cap10 (Kadry §19.5); inne role/skill są niedostępne bez jawnego wpisu.

**API:** GET `/api/v1/hr/training-programs`; GET `/api/v1/hr/training-programs/{id}/quote?employment_id={id}` według [OpenAPI Staff A](OILEMPIRE_KADRY_OPENAPI_V1.yaml). Cudzy pracownik →404; brak wpisu →409 PROGRAM_UNAVAILABLE. Cena jest stringiem groszy, bez ujawnienia seedu.

**Reguły:** subskill cap10, +1 po zaliczeniu; certificate UNIQUE(employee,course,level). Nie dopisujemy niewskazanej w katalogu zależności „ukończ I, aby zacząć II” ani ukrytego mnożnika. Kurs przy skill=10 odrzuca się jako TRAINING_SKILL_CAPPED zamiast pobierać pieniądze bez możliwości efektu. Nie tworzony jest drugi certyfikat dla już zaliczonego poziomu.

**UI/GM:** katalog i eligiblity w HR, przyczyny odmowy PL/EN/DE, SVG kursu/statusu. Filament widzi wersjonowany katalog i certyfikaty; koszt, duration i pass_rate edytuje przez Balans/P05, nie bezpośrednio w rekordzie działającego kursu.

**Odbiór:** trzy locale i zgodne kwoty string; brak kosztu w zatwierdzonym katalogu blokuje enrollment, a nie losuje ceny. Refining I/II/III korzysta z istniejących 20k/50k/100k PLN, 24h/48h/72h i progu70, bez dublowania innego kursu „refining”.

### P09-F02 — zapis pracownika na kurs i wyłączna zajętość

**Wejście:** EnrollEmployeeInCourse(ActorContext, company_id, employment_id, program_version_id, expected_state_version, client_key). POST /api/v1/hr/trainings z employment_id, program_version_id, expected_state_version; Idempotency-Key. Klient nie wysyła kwoty do obciążenia.

**Dane:**
- `staff_trainings`: company_id, employment_id, program_version_id, attempt_sequence, payment_operation_id, exam_slot_id, params_snapshot_json, started_at, due_at, completed_at/cancelled_at nullable, paid_minor, status, rng_slot, state_version; FK/UNIQUE/indeksy w [DDL Staff A](OILEMPIRE_KADRY_DDL_MYSQL84.sql).
- `training_progress`: employment_id + program_version_id UNIQUE, active_enrollment_id nullable FK do staff_trainings, last_passed_at, state_version. Wspólny kalendarz staff_time_reservations chroni zajętość całej osoby.
- `training_retry_state`: employment_id + program_version_id UNIQUE, consecutive_failures, accumulated_bonus_pp, next_attempt_at, last_training_id, state_version.

**Stany:** utworzenie od razu in_progress po opłaceniu; później passed | failed | cancelled_employee_left. Opłata i utworzenie to jedna transakcja — nie ma trwałego paid bez kursu ani kursu bez pobrania. Nie dodajemy stanu delayed, który mógłby opóźnić należny egzamin.

**Transakcja:** operation idempotency rank1; firmy10, settlement11/due12, person/employment/rezerwacje czasu/training rank14 według §23.5a.1; sprawdzenie własności, statusu zatrudnienia, wolnego pracownika, cap, nieposiadanego certyfikatu, next_attempt_at i wersji katalogu. Stan in_training wyklucza równoległe przypisanie do odwiertu/huba/TTS. Nie odpinamy za gracza obciążenia pracą bez jawnej akcji P07. Potem ledger_accounts20/reservations21 i PostTransfer opłaty z właściwego konta wg polityki wydatku Staff do SYS_TREASURY, reason code rejestrowany przez Staff (np. TRAINING_FEE); nie dopisujemy niewskazanego automatycznego dopełnienia kont.
Zapis staff_trainings, rezerwacji czasu, due_event exam i outbox training.enrollment.started atomowo. Publiczny kontrakt P07 oddaje zajętość w tej samej transakcji, nie prywatny UPDATE z innego modułu.

**Wynik:**202 data{enrollment_id,state=in_progress,due_at,paid_cost_minor,balance_version,state_version}; replay zwraca ten sam kurs i płatność. 409 EMPLOYEE_BUSY/COURSE_ALREADY_PASSED/TRAINING_COOLDOWN/STATE_CHANGED/CATALOG_CHANGED; 422 INVALID_LEVEL/SKILL_CAPPED; 409 INSUFFICIENT_AVAILABLE_FUNDS.

**Odbiór:** dwa różne klucze dla tego samego pracownika→jeden kurs/opłata; enrollment kontra TTS/przydział→jedna legalna zajętość; brak salda bez kursu; zmiana balansu po zapisie nie zmienia due_at/ceny rozpoczętej próby; rollback przed commitem usuwa wszystkie skutki.

### P09-F03 — egzamin, certyfikat, powtórki i dokończenie w draining

**Akcje:** CompleteCourse(event_id, logical_due_at), CancelForEmploymentEnd(employment_id,effective_at), AccelerateTrainingTime(training_id, purchase_receipt_ref) jako wewnętrzny kontrakt dla P20. GET /api/v1/hr/trainings/{id} odczytuje własny stan przez wspólny Settlement. Przyspieszenie nie jest publicznym endpointem pozwalającym ustawić passed.

**Dane A:** `staff_training_exam_results` jest niezmiennym wynikiem egzaminu z UNIQUE(training_id) i UNIQUE(exam_slot_id); przechowuje logical_at, bazową/efektywną szansę, bonus, roll, secret_id, outcome, wersję i hash. `staff_certificates` wskazuje person_id, employment_id i training_id oraz skill_before/after; certyfikat jest osobnym typowanym faktem, nie drugim wynikiem egzaminu. Szczegółowe FK w [DDL Staff A](OILEMPIRE_KADRY_DDL_MYSQL84.sql).

**Rozliczenie:** idempotency domeny training.exam:{enrollment_id}:{exam_slot_id}, operations5 gdy lease, firma10/settlement11/due12/Staff14/konta20 jeśli należny koszt; raz zapisany wynik i certyfikat nie są losowane ponownie. RNG według trwałej tożsamości egzaminu, z secret_id i balance_version. Szansa efektywna = clamp do zakresu prawdopodobieństwa0–100 bazowej szansy plus utrwalony bonus; nie mnożymy +10% jako ×1.10. Po zaliczeniu +1 do wskazanego skillu z cap10 i certyfikat; kosztu ponownie nie pobieramy. Staff publikuje training.exam.passed/failed oraz pracownik wraca do legalnej dostępności P07.

**Powtórki:** pierwsza/druga porażka blokuje ponowny zapis przez12h. Trzecia porażka uruchamia7dni (ten dłuższy termin zastępuje12h), bonus +10p.p. za porażkę, max30p.p. Wariant A po 7 aktywnych dniach blokady resetuje licznik kolejnych porażek do 0; historia, skill i wyniki zostają, a nowa próba nie dostaje automatycznego bonusu. Test czwartej próby jest bramką aktywacji (Kadry §19.5, DOM07). Kolejny kurs jest nową opłaconą próbą według warunków nowego zapisu.

**Zwolnienie/strajk:** opłata bezzwrotna przy zakończeniu zatrudnienia w trakcie kursu; cancelled_employee_left bez certyfikatu. W pojedynku egzaminu ze zwolnieniem porównujemy logiczne due_at i effective_at (nie czas workera); w wariancie A równość kończy zatrudnienie przed egzaminem: cancelled_employee_left bez certyfikatu (§19.3); inne remisy domenowe DOM08 pozostają osobne. Strajk nie zatrzymuje kursu. Draining Staff blokuje nowe zapisy, lecz kończy in_progress, egzamin, powiadomienie i legalny zwrot dostępności.

**Premium:** przyspieszenie15GBBL skraca pozostały czas do zera, nie zmienia wyniku/pass_rate i nie omija cap/certyfikatu. P20 musi dostarczyć zweryfikowany receipt i idempotencję zakupu; brak endpointu „kup zdanie egzaminu”. Przyspieszenie aktualizuje due_at/event przez CAS; stary opóźniony job jest neutralny.

**Odbiór:** trzy wyzwalacze jeden wynik; replay po przełączeniu secret_id; równoległy certyfikat tylko raz; bonus0/10/20/30 i granice12h/7dni; zwolnienie przed terminem bez certyfikatu; strajk i draining nie gubią kursu; przyspieszenie nadal może dać failed.

## P10 — pozwolenia i koncesje Legal

Źródło: §7.3/§9/§11A.2/§19.4 oraz YAML legal.params.permits_bribes.permits. Legal jest właścicielem pozwolenia/koncesji; Extraction/Logistics/Refining pytają publiczny kontrakt, nie prywatny model. Ranga **15**, po Staff14, przed kontami20 i aktywami40+. Wewnątrz rank15: region scope → application → grant/license → renewal/cost-allocation rows po stabilnych ID. Katalog regionów jako read-only kontrakt świata/Map; Legal nie tworzy nieograniczonych regionów.

### P10-F01 — złożenie wniosku P1/P2

**Dane:**
- permit_region_scopes: company_id FK, region_id FK rejestru regionów, active_application_id nullable, next_initial_application_at nullable, state_version; UNIQUE(company_id,region_id). Ten singleton serializuje jeden aktywny wniosek/odwołanie firmy na region, zgodnie z literalnym §11A.2; P1 i P2 można uzyskać kolejno, nie są wzajemnie zastępowalne.
- permit_applications: id/public_id, company_id FK, region_id FK, permit_type VARCHAR(8), kind initial/appeal, parent_application_id nullable FK RESTRICT, state pending/granted/refused, fee_minor BIGINT, payment_operation_id, balance_version_id FK, submitted_at, due_at, decided_at nullable, review_slot_id VARCHAR(128) UNIQUE, state_version; INDEX(company_id,region_id,created_at), INDEX(state,due_at); UNIQUE(parent_application_id) tylko dla appeal i przy NULL wielu initial. Ograniczenie CHECK(kind/parent) i walidacja akcji wykluczają appeal bez rodzica.
- permit_verdicts: application_id UNIQUE FK, review_slot_id UNIQUE, input_history_ref, logical_at, chance_pct DECIMAL, rng_secret_id, roll_value, outcome, reason_code; append-only.
- permit_grants: id, company_id, region_id, permit_type, issued_by_application_id UNIQUE FK, granted_at, revoked_at nullable, state_version; grant jest prawnym uprawnieniem, nie kolejnym pobraniem pieniędzy. Jedna aktywna grant na scope/type zabezpieczona rekordem permit_entitlement_heads(company_id,region_id,permit_type UNIQUE, grant_id nullable,state_version).

**Kontrakt:** ApplyForPermit(actor, company_id,region_id,type,expected_scope_version,client_key). POST /api/v1/legal/permits {region_id,permit_type,expected_scope_version}; Idempotency-Key. Koszt/czas z YAML: P1 150000PLN/72h, P2 500000PLN/7dni. P1 obejmuje koncesję i odwiert, P2 hub/rurociąg/rafinerię. Już posiadane aktywne uprawnienie typu w regionie→409 PERMIT_ALREADY_GRANTED; inny aktywny wniosek/appeal w tym regionie→409 REGION_APPLICATION_PENDING; cooldown→409 REAPPLICATION_LOCKED; niedozwolony region→404/409.

**Transakcja:** scope operacji1; firma10, settlement11/due12, potrzebne rekordy Staff14 i Legal15, konto PLN_CASH oraz SYS_TREASURY rank20. Płatność PERMIT_FEE_P1/P2 z available, zapis pending/due/outbox i active_application_id atomowo. Brak gotówki→odmowa bez wpisania pending; specyfikacja permit nie nadaje automatycznego kredytu ani dopełnienia z banku. Do danych wejściowych nie przyjmujemy chance, due_at, credibility czy wyboru werdyktu.

**Wynik:**202 {application_id,state=pending,due_at,fee_minor,balance_version,state_version}; GET /api/v1/legal/permits lista własna i /{id} wynik. UI PL/EN/DE, SVG P1/P2, koszt i termin przed potwierdzeniem; Filament ma odczyt/legalne akcje GM przez kontrakt.

**Odbiór:** dwie próby P1/P2 na scope→jedna należność i jeden pending; cudzy scope404; retry tego samego klucza identyczny application; deadline z zegara serwera.

### P10-F02 — werdykt, refund pierwszej odmowy i odwołanie

**Akcje:** DecidePermit(application_id, logical_due_at), AppealPermit(actor,parent_application_id,expected_scope_version,client_key). POST /api/v1/legal/permits/{id}/appeals {}; Idempotency-Key, expected_state_version.

**Dodatkowe dane:** permit_refunds: application_id UNIQUE FK, amount_minor, fts_operation_id, created_at — kontrola pojedynczego refundu. appeal_inputs snapshot {base_formula_version,law_skill,bonus_pp,credibility,grey_zone_flag,env_incident_count,as_of}; referencje do historycznych źródeł umożliwiają spóźniony werdykt wg logicznego due_at.

**Werdykt P1:** clamp20–98%, 70 +0.5×(credibility−70) −3×grey_zone_flag −2×env_incident_count.
**P2:** clamp10–95%,55 +0.6×(credibility−70) −4×grey_zone_flag −5×env_incident_count. Parametry i formuły wykonywalne z zatwierdzonej wersji, string wzoru z YAML nie jest eval dowolnego PHP.
Grey flag: BMS≥30 lub aktywny EPC F; env: major/catastrophe i surface_spill/blowout z180dni, bez potwierdzonego sabotażu z ustalonym agresorem. Dane odnoszą się do logicznego czasu decyzji; zmiana stanu między due_at a uruchomieniem workera nie może zmieniać wyniku. W razie braku historii as_of blokujemy decyzję do odzyskania danych, nie losujemy na bieżących przypadkowych wartościach.

RNG to permit:{application_id}:verdict; appeal ma nowy application_id z parent wskazującym pierwszy, więc nie powiela tego samego rzutu pod tym samym ID. Wynik i input snapshot zapisane raz. Decide przyjmuje wyłącznie event ID, nie wynik od klienta. Transakcja:1/5 gdy lease/10/11/12/14/15/20, legalny pending, uprawnienia i utrwalona balance_version; na grant zapis uprawnienia/heads i clear active scope, na refused jednorazowy refund50% pierwotnej opłaty SYS_TREASURY→PLN_CASH z kodem rejestrowanym Legal, zdarzenie i next_initial_application_at=decision_due_at+72h. Refund nie korzysta z niezabezpieczonego UPDATE salda.

Appeal dozwolony tylko raz dla pierwszego refused we własnym scope; nie wymaga upływu cooldownu nowego wniosku. Opłata25% bazowej opłaty rodzaju, czas48h, bonus2p.p.×skill_law, cap10p.p. od publicznego kontekstu dyrektora Legal/Staff. Próba bez dyrektora lub bez udokumentowanego skilla musi przestrzegać BoardAccess z P07; brak domyślnej maksymalnej premii. Appeal refused końcowe i nowy initial dopiero po7dniach od jego logicznego werdyktu; granted ustawia uprawnienie. Pierwszy refund nigdy nie wypłaca się drugi raz wskutek appeal. Źródło nie definiuje zwrotu opłaty appeal ani dokładnego miejsca bonusu względem clamp — luka T10-L01, bez wymyślonego nowego refundu.

**Błędy:**409 NOT_REFUSED/APPEAL_ALREADY_USED/REGION_APPLICATION_PENDING, 403 BOARD_ACCESS_REQUIRED, 409 STALE_STATE, 409 INSUFFICIENT_AVAILABLE_FUNDS. Klient nie może przepchnąć arbitralnej chance.

**Odbiór:** P1 przy credibility70 bez flag/incydentów70%, z grey flag67%; P2 analogicznie55/51%; refund50% raz; appeal25% bez ponownej pełnej opłaty; 72h/48h/7dni dokładnie na granicy; nowy application ID dla appeal; 3wyzwalacze jeden werdykt; sabotaż nie karze ofiary flagą środowiskową.

### P10-F03 — kontrakt uprawnień, koncesja i odnowienie

**Dane:** licenses: id/public_id, company_id FK, region_id FK, source_permit_grant_id FK, status active/renewal_due/returned/revoked, granted_at, renewal_due_at, payment_deadline_at, renewed_at nullable, state_version; aktywny rekord wskazuje license_heads(company_id,region_id UNIQUE,license_id,state_version), historia nie jest usuwana. license_renewals: id, license_id FK, period_slot_id UNIQUE, valuation_snapshot_id, assessed_value_minor BIGINT, fee_minor BIGINT, due_at, deadline_at, paid_at nullable, payment_operation_id nullable, state due/paid/returned; INDEX(state,deadline_at).

**Kontrakty:** LegalEntitlements::require(company_id,region_id,capability,as_of): PermitDecision{grant_id/license_id,valid,reason,version}; capability allowlist extraction.concession/drill, logistics.hub/pipeline, refining.build. Legal nie ma dowolnej „zgody admina na wszystko”. Extraction otrzymuje trwały license_id z publicznego DTO, nie tworzy konkurencyjnego własnego rekordu koncesji. Przydział zasobów regionu i slotów przez jawny kontrakt Map/World z polityką Frontier (§19.4), nie przez nieograniczony INSERT po opłacie.

**Odnowienie:** co180dni, opłata5% wyceny regionu, po30dniach od terminu bez wpłaty koncesja wraca do puli; terminy w UTC i trwały period_slot_id. POST /api/v1/legal/licenses/{id}/renewals/{renewal_id}/pay (empty body, Idempotency-Key) pobiera kwotę ustaloną serwerowo z opublikowanego snapshotu wyceny, nie z ceny klienta. GET /api/v1/legal/licenses i /{id} pokazują due/deadline/grant/scope. Nie dopisujemy automatycznego odnowienia/obciążenia karty. Spóźniony worker porównuje logical_due_at i deadline, nie „czy już ustawił flagę”.

**Transakcja:**1/10/11/12/15/20, przed nową budową publiczny require sprawdza ważność z timestampów, nie tylko bool. Po płatności opłata/renewal/license i outbox atomowo. Zwrot do puli uruchamia publiczne kontrakty istniejących odwiertów/logistyki, nie usuwa ich danych z Legal. Zachowanie pracujących aktywów w okresie30dni i po zwrocie oraz wzór wyceny regionu nie są rozpisane w kanonie — T10-L02; aktywacji mechanizmu wygasania nie wolno zamknąć samym testem wpisu statusu.

**UI/GM:** koncesje/regiony wydano/wygasa,5% ceny referencyjnej, historia; GM_PERMIT_EXTEND/REVOKE tylko legalne przejścia i audyt, bez kasowania faktu pierwotnej decyzji. Draining Legal nie może zakończyć się disabled, gdy zależne aktywa potrzebują jego decyzji lub trwa renewals/grants.

**Odbiór:** jedna koncesja/region/firma mimo dwóch grant callback;180dni i30dni; równoległy renew kontra revoke; stan grant nie wraca wskutek starego joba; brak dwa razy opłaty za ten sam period_slot_id; odwiert nie „znika” z bazy przy cofnięciu uprawnienia.

### P10-F04 — jednorazowy CAPEX pozwolenia i alokacje do odwiertów

**Właściciel:** Legal dla puli źródłowego kosztu pozwolenia; Finance/P16 dla publicznego serwisu wyceny, Ledger dla dowodu wydatku. Karta realizuje zatwierdzone OPEN03, bez kosztu odtworzenia i wyceny całej ropy pod ziemią.

**Dane:** permit_cost_pools: id, permit_application_id UNIQUE FK, company_id FK, region_id FK, source_fts_operation_id, total_cost_minor BIGINT, unallocated_minor BIGINT, state_version; UNIQUE(source_fts_operation_id,cost_kind) jeśli koszt jest typowany osobno, cost_kind VARCHAR(32) NOT NULL. capex_allocations: id, pool_id FK, asset_type VARCHAR(32), asset_id(public resource ref), allocated_minor BIGINT≥0, allocation_revision BIGINT, updated_at; UNIQUE(pool_id,asset_type,asset_id), INDEX(asset_type,asset_id). allocation_events: id,pool_id,revision,payload_delta_json,actor_type/id,request_id,occurred_at append-only; UNIQUE(pool_id,revision).

**Akcje:** RegisterPermitCost(receipt_ref) po legalnym opłaceniu i ReallocatePermitCost(pool_id,eligible_asset_refs,expected_revision). Brak endpointu gracza „zwiększ CAPEX”; publiczny kontrakt wewnętrzny wywołuje przyłączenie odwiertu P11 i transfer aktywa. Równe części tylko wśród odwiertów objętych tym samym pozwoleniem; ostatni po stabilnym ID otrzymuje resztę groszy, suma alokacji+unallocated=źródłowy koszt. Nieprzypisany koszt należy do koncesji, nie jednocześnie do odwiertu. Każda część podąża za przeniesionym aktywem; przeniesionej części nie dolicza się ponownie do puli poprzedniego właściciela.

**Transakcja:** company roots wszystkich stron w10, Legal15, ledger20 gdy operacja ma nowy transfer, wells40 znane przed pierwszą blokadą, zapis allocation revision i event/outbox w140. Żadne wywołanie Finance nie pobiera wcześniejszej rangi po wells40. Podstawa kosztu bierze wyłącznie faktyczny wydatek FTS, refund pierwszej odmowy nie udaje CAPEX pełnej nieuzyskanej koncesji. Ostateczna karta wyceny musi jasno określić kwalifikację kosztu odrzuconego wniosku i appeal, zamiast przyjąć każdą wpłatę jako wartość uzyskanego aktywa.

**Odbiór:**150000PLN/3odwierty to łącznie150000PLN, nie450000; niepodzielne grosze bez straty/dodatkowej emisji;4odwiert przelicza alokację bez nowego FTS; historyczny podatek00:00 nie jest nadpisywany; transfer nie duplikuje wartości CAPEX.

## P11–P13 — wspólny zakres produkcji, logistyki i bezpieczeństwa technicznego

Źródła: architektura §9, §11, §11A, §17.4, §21, §23 oraz YAML extraction/incidents/logistics/banking.params.incidents. Wartości, mnożniki i czasy pobierane z utrwalonej balance_version; nie wprowadzamy nowych kwot. Nazwy stare road_trips/marine_voyages/transport_runs mapujemy na kanoniczne trips z typem road/marine; dokument nie tworzy równoległych ksiąg ani rootów YAML.

Wspólne typy/FK/idempotencja/outbox jak P01–P05. Wszystkie mutacje ekonomiczne: ActorContext i capability, Idempotency-Key, expected_state_version, settlement i komenda w jednej transakcji. SQL nie ufa company_id klienta; właściciela/uczestnika sprawdza akcja przed i po blokadzie. Dostęp do pracownika, pozwolenia i księgi przez publiczne kontrakty właścicieli.

Obowiązuje jedna tabela §23.5a architektury. Core13; Staff/Training14 (dla A pełna lista nowych tabel musi wejść do globalnego LockPlan przed migracją, zgodnie z HR-CONTRACT01 §8); Legal15; Technical/BHP16; Incidents17; TechnicalTasks18; geologia39; aktywa40–80; append140. Wewnętrzna kolejność tabel to dokładna kolejność z §23.5a, nie lokalny wariant z przykładu.

expected_state_version utrwalone w due_event jest wskazowka do rewalidacji, nie bezwarunkowym anulowaniem po kazdej zmianie encji. Handler blokuje aktualny stan i sprawdza tozsamosc procesu/durable_slot, termin i legalne przejscie. Niezwiązana zmiana etykiety nie kasuje naleznej dostawy lub naprawy; anulowanie/przejecie celu daje jawny wynik domenowy, nie ciche zgubienie zdarzenia.

Kolejność implementacji P11 → P12 → P13 nie oznacza, że P11 wymyśla własną logistykę/ryzyko: w P11 aktywacja i ryzyko są kontraktami, testowanymi adapterem, a uruchomienie produkcyjne wymaga gotowego P12/P13. Ledger jest właścicielem niezmiennego rejestru ruchów; Logistics właścicielem stanów fizycznych magazynu, partii i ładunku, Extraction właścicielem zasobu pod ziemią i bufora odwiertu. Zmiany stanu przez kontrakty muszą uczestniczyć w tej samej transakcji i udostępniać kompletny LockPlan.

### P11-F01 — badanie geologiczne i prognoza złoża

**Dane Extraction:** strata: id, region_id (publiczna referencja regionu), code VARCHAR(32), stratum VARCHAR(16), generator_seed_ref VARCHAR(128), initial_reserves_bbl DECIMAL(16,4), truth_version BIGINT; UNIQUE(region_id,code), INDEX(region_id,stratum). Dane rzeczywiste są prywatne, API publikuje wyłącznie prognozę/odkryte wartości. geological_surveys: id, company_id, stratum_id, permit_ref, survey_no BIGINT, status pending/completed/cancelled, requested_at, due_at, completed_at nullable, analysis_skill_snapshot DECIMAL(9,5), balance_version_id, forecast_json, state_version. UNIQUE(company_id,stratum_id,survey_no), INDEX(status,due_at), FK do własnego strata RESTRICT. Jeden otwarty survey dla pary company/stratum wymusza osobny survey_open_slots(company_id,stratum_id PK,survey_id) pod tym samym rank39, bez nieskutecznego UNIQUE z nullable status.

**Kontrakty/HTTP:** legal.entitlements.require(company,region,P1,at); staff.assignments.requireGeologist(company,at); extraction.surveys.start/complete/get. POST /api/v1/extraction/surveys: stratum_id, expected_state_version; 202 survey_id/status/due_at/state_version/balance_version, bez prawdziwej zasobności. GET /api/v1/extraction/surveys/{id}: wyłącznie właściciel, po terminie ten sam due-handler. Błędy 404 RESOURCE_NOT_FOUND, 409 SURVEY_ALREADY_PENDING/PERMIT_INVALID/STAFF_UNAVAILABLE, 422 INVALID_STRATUM, 409 INSUFFICIENT_AVAILABLE_FUNDS.

**Proces:** sprawdzić koncesję i region przez Legal, ustalić zespół/LockPlan, settlement do czasu decyzji, rank14 personel, rank20 konta, rank39 strata/survey; zapisać opłatę przez Ledger, snapshot umiejętności i due_at zgodny z48h §9.4, zdarzenie due i outbox survey.started. Nie uruchamiać własnego losowania przy GET. CompleteSurvey używa trwałego domain key survey:id:completion; rezultat raz z deterministycznego ziarna i utrwalonej wersji, zamyka open_slot. Nieudana prognoza nie tworzy nowego złoża. Skrócenie20GBBL jest komendą premium z P20, nie niesprawdzonym flagiem w payloadzie.

**UI/odbiór:** mapa pokazuje etap i prognozę z dokładnością, nie ukryte liczby; Filament widok audytowy z capability na prawdziwe dane, PL/EN/DE i SVG. Testy: brakP1, cudza warstwa, dwa StartSurvey, 48h przy HTTP/job/sweeper i retry, zmiana geologa/balansu nie przelosowuje wyniku, brak środków nie zostawia opłaty ani slotu.

### P11-F02 — wiercenie, odbiór i ewidencja nakładów

**Dane:** drilling_projects: id, company_id, stratum_id, survey_id nullable, permit_ref, equipment_tier VARCHAR(32), stratum_code VARCHAR(16), status drilling/commissioned/dry/cancelled, started_at, due_at, drilling_skill_snapshot DECIMAL(9,5), quoted_cost_minor BIGINT, balance_version_id, well_id nullable, state_version. UNIQUE(well_id) gdy przypisany; INDEX(company_id,status), INDEX(status,due_at). Złoże ma drilling_open_slot: PK stratum_id, project_id, company_id — jeden projekt uprawniony do tego konkretnego zasobu. wells: id, company_id, stratum_id, code VARCHAR(32), region_id, permit_ref, equipment_tier, upgrade_level SMALLINT, status VARCHAR(32), condition_pct DECIMAL(9,5), wear_pct DECIMAL(9,5), initial_reserves_bbl/extracted_bbl/remaining_bbl DECIMAL(16,4), last_calc_at, state_version, capex_ref. UNIQUE(company_id,code), INDEX(company_id,status), INDEX(region_id,status), FK stratum RESTRICT. Nie wymuszamy produkcji i utraty zasobów samymi polami JSON.

**HTTP/DTO:** POST /api/v1/wells/drilling-projects: stratum_id, survey_id, equipment_tier, expected_state_version; odpowiedź202 project_id/due_at/server_cost_minor/balance_version/state_version. GET /api/v1/wells/drilling-projects/{id}; POST /api/v1/wells/{id}/upgrades z target_level i expected_state_version, wyłącznie legalny kolejny poziom0–3. Błędy PERMIT_INVALID, STRATUM_ALREADY_ALLOCATED, SURVEY_NOT_AVAILABLE, STAFF_UNAVAILABLE, INSUFFICIENT_AVAILABLE_FUNDS, ILLEGAL_STATE_TRANSITION.

**Transakcja:** rank14 geolog/obsługa przez Staff; Ledger koszt badania/wiercenia osobnymi efektami tylko gdy faktycznie należne; rank39 slot projektu, rank40 nowy odwiert i bufor. Koszt wiercenia i czas z §9.5 × skill_drilling (6/12/24/48h bazowo); opłata zapisuje CAPEX przez publiczny rejestr Finance/ekonomii. Wspólne P1 raz według capex_allocations, nie doliczane w pełni do każdego odwiertu. Due zakończenia daje awaiting_connection, nigdy automatycznie active. Suchy odwiert/niższa zasobność jest utrwalonym wynikiem generatora, bez przyznawania ropy. Zapis well.created i drilling.completed w outbox z jednego commitu.

**Odbiór:** równoczesne wiercenie tej samej warstwy, powtórzenie odbioru, brak opłaty i częściowej budowy po rollbacku; koszt pozwolenia sumuje się raz; equipment z black_market ma faktyczny CAPEX; nowy awaiting_connection ma produkcję0 i zachowaną historię.

### P11-F03 — obsada, połączenie i legalny rozruch

**Dane:** well_connection_state: well_id PK/FK, inbound_pipeline_ref nullable, offshore_contract_ref nullable, hub_ref nullable, effective_from nullable, connection_version BIGINT, auto_road_fallback BOOL, activated_at nullable, state_version. Logistics jest właścicielem połączenia/rury i publikuje stan przez logistics.connections v1; Extraction trzyma jawny odczyt referencji, nie prywatny model. well_buffers: well_id PK/FK, capacity_bbl DECIMAL(16,4), used_bbl DECIMAL(16,4), state_version; CHECK used≥0 i used≤capacity, pojemność500/2000/5000 wg tieru.

**Komendy:** POST /api/v1/wells/{id}/start; POST /api/v1/wells/{id}/operating-mode: mode oraz expected_state_version; GET /api/v1/wells/{id}; staff.assignments v1 przydziela operatora i technika. Rozruch wymaga jednego operatora i technika (max3 odwierty/technika), ważnego przypisania/huba, ukończonej właściwej rury; offshore dopuszcza ukończone połączenie morskie wg §9.4.

**Stany:** drilling→awaiting_connection→active; brak obsady active→paused_staff; brak miejsca→paused_storage; brak odbioru→choked zgodnie z segmentem nasycenia; broken wymaga TTS, seized kontroli Banking, depleted nie uruchamia się ponownie. Wyjątki nie mogą być realizowane dowolnym PATCH status. Flaga fallback dotyczy uruchomionego odwiertu po awarii rury, nie omija pierwszego wymogu podłączenia.

**Transakcja:** settlement, dostępność Staff rank14, well rank40 i planowane zasoby Logistics50–70; sprawdzenie connection_version i własności pod blokadą. Zmiana wydajności ma effective_from i outbox well.mode_changed z nową rate_version. Nie ma wpłaty produkcji do magazynu głównego ani ukrytego tworzenia cystern.

**Odbiór/UI:** powód zatrzymania i wymagane role/połączenie jawne w interfejsie; test bezrury, beztechnika, technik4odwierty, offshore alternatywa, awaria rury z/bez fallback, GET i UI nie mają przycisku obchodzącego policy.

### P11-F04 — produkcja segmentowa, wyczerpanie i zużycie

**Wejście kontraktu:** extraction.production.settle(company_id, segment_start/end, immutable ModifierContext, LockPlan, balance_version). Wynik: wyprodukowane bbl, zmiana reserves/condition, capex/state_version unchanged albo konkretne zmiany, zdarzenia progowe. base_rate w §9.2 jest bbl/h; wzór zapisany production_bbl interpretuje się jako stawkę, którą integrator mnoży przez godziny segmentu. Wytworzone bbl≤remaining i≤dostępne miejsce/zdolność przepływu, bez ujemnych rezerw.

**Dane:** production_effects: effect_key UNIQUE, well_id, start_at/end_at, produced_bbl, state_version_before/after, balance_version_id, sweep_ref; append-only rank140. Wyrobisko i bufor lock rank40; zmiany rejestrów fizycznych przez Logistics/Ledger w tej samej transakcji. Każdy wyprodukowany bbl ma udokumentowane źródło extraction w oil_movements, nie ręczny UPDATE obu stanów bez rejestru. Faza1 tworzy ilość, faza5 z P16 księguje OPEX należnego segmentu raz; produkcja nie pobiera drugi raz OPEX w swoim handlerze.

**Reguły:** depletion po70% rezerwy, spadek1.5p.p./kolejne10000bbl aż do0.10; remaining=0→depleted; szkoleniowy odwiert po wyczerpaniu nie nalicza OPEX zgodnie z FTUE §27.1a. Dla innych klas koszt nieczynnego aktywa wynika z właściwej polityki stanu, nie z nieudokumentowanego globalnego zwolnienia z kosztów. Wzór zużycia i budget_tech_factor z YAML; kondycja i wear w punktach procentowych, nie mieszane ze skalą0..1. Modyfikatory przez wspólny stos w ustalonej kolejności. RNG awarii jest własnością P13; P11 nie robi drugiego rzutu.

**Odbiór:** golden-master shallow40bbl/h przy pełnych mnożnikach, 1h/30min segment; przekroczenie70%, koniec zasobu, plateau12h/DR>7dni; bilans do0.0001bbl; retry segmentu no-op; zmiana budżetu w połowie nie działa wstecz. DPL03 ujednolicono w architekturze: brak odbioru przy wolnym buforze może produkować do bufora; po pełnym buforze paused_storage/produkcja0 i OPEX jałowy20% wg §23.4.

### P12-F01 — magazyny, huby, najem i przypisanie odwiertu

**Dane Logistics:** storages: id, owner_company_id, location_ref, product_code, capacity_bbl/used_bbl DECIMAL(16,4), state_version; UNIQUE(owner_company_id,location_ref,product_code). Starter tworzy magazyn capacity20000/used0 przez idempotentny kontrakt provisionStarterStorage, nie kopiuje logiki startera Core. hubs: id, owner_kind npc/owned, owner_company_id nullable, region_id, type_code, level, mode, condition_pct/wear_pct DECIMAL(9,5), status, last_maintenance_at, last_calc_at, state_version. hub_leases: id, hub_id FK, tenant_company_id, slots, deposit_account_ref, starts_at/ends_at/notice_at, status, version; INDEX(hub_id,status), INDEX(tenant_company_id,status). Najem nie zmienia właściciela infrastruktury. hub_well_assignments: id, well_ref, hub_id FK, company_id, slot_no, state, effective_from, reassign_after, state_version; UNIQUE(hub_id,slot_no) dla bieżącego rekordu; odrębna tabela historii jest append-only. well_assignment_current(well_ref PK, assignment_id) zapewnia jedno bieżące przypisanie. hub_buffers: hub_id, owner_company_id, product_code, used_bbl/capacity_share_bbl; UNIQUE(hub_id,owner_company_id,product_code). Bufor osób trzecich nie staje się własnością właściciela huba.

**Akcje/HTTP:** POST /api/v1/logistics/hubs: type_code, region_id; POST /api/v1/logistics/hubs/{id}/leases; POST /api/v1/logistics/hubs/{id}/well-assignments: well_id; POST /api/v1/wells/{id}/hub-reassignment: destination_hub_id. GET /api/v1/logistics/hubs/{id} publikuje uprawnioną część danych, nie cudze zapasy. Błędy P2_REQUIRED/HUB_NOT_OPERATIONAL/NO_FREE_SLOT/REASSIGNMENT_COOLDOWN/LEASE_CONFLICT/INSUFFICIENT_AVAILABLE_FUNDS.

**Proces:** budowa/rozbudowaP2 z Legal i koszty/czasy YAML; najem min7dni, kaucja2×dobowy czynsz miejsca, wypowiedzenie7dni; opłata przypisania zgodnie z nowy×5/używany×8/najem2dni z jednym FTS. Przepięcie trwa2przebiegi rozliczeniowe i przerwa po odpięciu4h wg kanonu; zapisać konkretne transition_events zamiast testowania liczby odświeżeń strony. Kursy w trasie zachowują stary destination_hub_id. To termin logiczny wymagający ujednolicenia DPL06, nie dwa requesty HTTP.

**Odbiór:** walka o ostatni slot, właściciel/najemca, ochrona cudzej ropy przy przejęciu, opłata raz, stare kursy do starego huba, zmiana trasy nie przestawia ładunku. Panel pokazuje właściciela i dzierżawców, kontrakty, sloty/bay, bilans.

### P12-F02 — rurociąg skierowany i segmenty

**Dane:** pipelines: id, owner_company_id nullable dla NPC, leg inbound/outbound, well_ref nullable, hub_id FK, destination_storage_id nullable, type_code, acquisition_kind new/used/black_market, condition_pct/wear_pct DECIMAL(9,5), status, build_started_at/build_due_at nullable, last_inspected_at, state_version, balance_version_id, capex_ref. pipeline_inbound_heads(well_ref PK,pipeline_id UNIQUE FK) wyłącznie dla aktywnego dopływu i pipeline_outbound_heads(hub_id PK,pipeline_id UNIQUE FK) wyłącznie dla wypływu; nie nakładamy UNIQUE(hub_id,leg) na wszystkie inboundy huba z CHECK prawidłowości endpointów; brak well_id=0. pipeline_segments: id, pipeline_id FK, sequence_no, length_km DECIMAL, leak_state, effective_condition_pct, hidden_defect_ref nullable; UNIQUE(pipeline_id,sequence_no). pipeline_flow_state: segment_id FK, owner_company_id, line_pack_bbl, in_transit_bbl, last_calc_at, state_version; PK(segment_id,owner_company_id), z sumaryczną kontrolą przepustowości pod lockiem segmentu.

**HTTP/kontrakty:** POST /api/v1/logistics/pipelines: leg, assignment_id, type_code, acquisition_kind; POST /api/v1/logistics/pipelines/{id}/pause; POST /api/v1/logistics/pipelines/{id}/resume; GET /api/v1/logistics/pipelines/{id}. Legal P2 i ważność przypisania, owner/najemca w ramach kontraktu. Błędy ASSIGNMENT_CHANGED/PIPELINE_EXISTS/P2_REQUIRED/ILLEGAL_PIPELINE_TRANSITION/HIDDEN_CONDITION_NOT_AVAILABLE.

**Stany:** planowany→pozwolony→budowa→aktywny→zdegradowany/krytyczny/uszkodzony; wyciek jako odrębna cecha, nie utrata wszystkich informacji o stanie. Resume nigdy nie leczy uszkodzenia; po naprawie zgoda właściwego kontraktu Technical. Wznowienie wraca do legalnego stanu zachowanej kondycji/wycieku; uszkodzony wymaga naprawy zgodnie z ujednoliconym §11A.3.

**Transakcja:** lock well40, rura/segmenty50, hub/assignment60 i magazyn70 w uprzednio ustalonym zbiorze; zakup vs reassign porównuje assignment_version pod blokadą. Jedna opłata/CAPEX i due budowy, wynik stanu utrwalony raz; tier używany/black_market losowany i zapisany przy zakupie/montażu zgodnie z YAML, nigdy ponownie przy odczycie. Koszty tranzytu/utrzymania mają jednego właściciela naliczania faza5, a straty materializuje handler fizyczny faza2.

**Odbiór:** równoległy zakup tej samej nogi, ponowienie budowy, brakP2, zero-well odrzucone; restart nie resetuje zużycia/wady; uszkodzony→resume odrzucony; wyciek dotyka wyłącznie przepływu/line-pack odcinka.

### P12-F03 — flota, kurs drogowy i rejs morski

**Dane:** transport_vehicles: id, owner_company_id nullable, tenant_company_id nullable, vehicle_type, mode road/marine, capacity_bbl, condition_pct, availability available/reserved/in_trip/repair, active_trip_id nullable, driver_assignment_ref nullable, state_version; INDEX(owner_company_id,availability), INDEX(tenant_company_id,availability), aktywne użycie zapisane w vehicle_use(vehicle_id PK,trip_id UNIQUE). trips: id, owner_company_id, carrier_company_id, vehicle_id FK, mode road/marine, leg inbound/outbound/b2b, source_ref/destination_ref, origin_hub_ref nullable, destination_hub_ref nullable, contract_ref nullable, state, loaded_bbl/delivered_bbl/lost_bbl DECIMAL(16,4), departed_at nullable, eta, arrived_at nullable, wait_started_at nullable, due_slot_id, balance_version_id, state_version; INDEX(owner_company_id,state), INDEX(state,eta). marine port-call jest własną encją, nie osobnym niespójnym rejestrem rejsu.

**stock_reservations:** id, source_type/id, owner_company_id, operation_id, purpose, quantity_bbl, state active/loaded/released, created_at, state_version; UNIQUE(operation_id,source_type,source_id,purpose), INDEX(owner_company_id,state). To rezerwacja ropy, nie fund_reservations. Stan fizyczny bez aktywnej rezerwacji jest available_bbl=used_bbl−active_reserved_bbl. Załadunek zużywa rezerwację i przenosi ilość do tranzytu atomowo, nie rezerwuje drugi raz w księdze pieniędzy.

**HTTP:** POST /api/v1/logistics/trips: vehicle_id, source_ref, destination_ref, quantity_bbl, route_option, expected_source_version; 202 trip_id/state/eta/cost_minor/version. POST /api/v1/logistics/trips/{id}/redirect dopuszcza tylko domenową procedurę nowej ETA/kosztu, nigdy PATCH destination. Błędy VEHICLE_BUSY/DRIVER_UNAVAILABLE/LOAD_THRESHOLD_NOT_MET/INSUFFICIENT_AVAILABLE_STOCK/ROUTE_CLOSED/NO_VALID_CONNECTION/PORT_UNAVAILABLE.

**Proces:** ręczny próg50%pojemności; autoVIP80%, sprawdzany przy NOWEJ wysyłce; koniecVIP nie zatrzymuje istniejącego kursu. Morze min_load80%domyślnie YAML. Rank14 Staff kierowca, konto20/rezerwacja21, wszystkie fizyczne źródła40–70, pojazd/kurs80; koszt wysyłki poniesiony także przy utracie. Jedna funkcja mnożników. OPEX/paliwo nie jest pobierane ponownie przy przybyciu. Strajk u>0.60 blokuje nowe kursy głównej trasy, istniejące dojeżdżają. Incydent drogi zleca publiczny Incidents resolver z ownerem RNG=logistics.trip, nie drugi WellRisk roll.

**Odbiór:** pojazd nie obsługuje2kursów; dwie sprzedaże i wysyłka konkurują o ten sam zasób; threshold50/80 i min_load, koniecVIP w trasie; blokada trasy30–120min nie kasuje ropy; koszt utraconego kursu nie zwraca się automatycznie.

### P12-F04 — przepływ, zatoki, rozładunek i delivery_receipt

**Dane:** oil_lots: id, owner_company_id, product_code, quantity_bbl, current_resource_type/id, state, origin_ref, state_version; INDEX(owner_company_id,current_resource_type,current_resource_id). Podział partii tworzy dzieci z parent_lot_id, suma=stan rodzica, historia Ledger. hub_bays: id, hub_id FK, bay_no, current_trip_id nullable, occupied_until nullable, state_version; UNIQUE(hub_id,bay_no), UNIQUE(current_trip_id). ports: id, hub_id FK, type_code, status open/closed, throughput_config_ref, state_version. port_calls: id, trip_id FK, port_id FK, call_no BIGINT UNSIGNED NOT NULL, queued_at, reserved_slot_at nullable, unload_due_at nullable, state_version; UNIQUE(trip_id,port_id,call_no). delivery_receipts: id, trip_id FK, unload_slot_id, owner_before_company_id, owner_after_company_id, quantity_bbl, delivered_at, effect_key UNIQUE; UNIQUE(trip_id,unload_slot_id). trip_stage_events: event_id, trip_id, stage, durable_slot_id, due_at, effect_key UNIQUE, payload; append-only.

**SettleLogistics:** phase2.1–2.8 wg §11A.8; min(dostępny wolumen, przepustowość×dt_h, miejsce), modyfikatory tylko raz. Normalne nasycenie zatrzymuje dopływ, nie tworzy incydentu rozlania. Pojemności wspólnego huba/segmentu/bay kontrolowane pod nadrzędnym lockiem, a nie niezależnie per tenant. Wyjście każdego kroku utrwala ruch bbl, właściciela i wersję. Ledger zapisuje stratę tylko z jawnego zdarzenia i uprawnionego typu; źródło strata nie jest dowolnym adresem klienta.

**CompleteArrival:** w_trasie→waiting_bay gdy brak miejsca; próba rozładunku po osiągnięciu ETA i dostępnej zatoce, czas z unload_bbl_per_min (droga) lub obowiązującego kontraktu portu (DPL05). Pełny rozładunek: przeniesienie tranzyt→bufor/magazyn, receipt, final state i outbox w JEDNEJ transakcji. Techniczny credited nie może być zapisany samodzielnie poza tą transakcją. Klucz skutku stabilny trip_id+unload_slot_id, NIE sweep_no; ponowienie następnego sweepera nie kredytuje po raz drugi.

**B2B:** receipt jest dowodem fizycznego odbioru, dopiero Contracts P15 rozlicza escrow we wspólnej transakcji/kontrakcie. Nie ma własnego refund w Logistics. Receipt/timeout synchronizują się na tym samym kontrakcie i kluczu; receipt wygrywa. Przed receipt właściciel nadawca. Magazyn kupującego pełny: waiting_bay, koszt adresowany do kupującego, brak kary za winę kupującego. Po48h odholowanie do zajezdni z bezpiecznym ładunkiem/10000PLN zgodnie §21; moment startu postojowego wymaga DPL01.

**UI/odbiór:** śledzenie partii „gdzie jest każda baryłka”, kolejki i przyczyny strat; panel read-only historia i audytowane działania przez serwisy. T1–T14 §11A.11, delivery przed timeout, restart między punktami, line-pack,12h/7dni, strata tylko tranzytu, port zamknięty nie otwiera się sam, pełna zatoka nie usuwa ładunku.

### P13-F01 — BHP, integralność procedur i audyty

**Dane Incidents/Technical:** company_technical_state: company_id PK, bhp_level SMALLINT, procedure_integrity DECIMAL(9,5), level_upgrade_job_id nullable, last_calc_at, state_version, last_audit_at nullable, last_full_calibration_at nullable. procedure_jobs: id, company_id FK, type bhp_upgrade/bhp_rebuild/pi_audit/pi_full_calibration, from_level nullable, target_level nullable, started_at, due_at, status queued/running/completed/cancelled, cost_minor BIGINT, balance_version_id, effect_key UNIQUE, state_version; INDEX(company_id,status), INDEX(status,due_at). Jeden aktywny upgrade przez company_technical_state.level_upgrade_job_id pod rank16; audyt/kalibracja mają oddzielne cooldowny z §11.2, bez współbieżnego dublowania tego samego efektu.

**HTTP:** GET /api/v1/technical/safety; POST /api/v1/technical/safety/upgrades: target_level; POST /api/v1/technical/procedure-jobs: type; odpowiedź202 job_id/due_at/quote_minor/state_version; błędy SAFETY_UPGRADE_ALREADY_RUNNING/TECH_DIRECTOR_REQUIRED/SAFETY_INSPECTOR_REQUIRED/PROCEDURE_COOLDOWN/INSUFFICIENT_AVAILABLE_FUNDS.

**Proces:** level1start; L2..L5 koszty/czasy wyłącznie §11.1/YAML; level3 dyrektor techniczny, level5 inspektor. Rank14 Staff,16 stan/procedure job,20 konta; FTS raz i due/outbox. AudytPI25000/6h/+35/cooldown24h oraz kalibracja60000/12h/do100/cooldown72h z §11.2. bhp_calibration z katalogu TTS oznacza ten sam audyt, nie drugi koszt/drugi przyrost. Procedura PI drift phase8 lub spójny handler czasu; nie może być naliczany równocześnie w Staff/Finance.

**Odbiór:** dwa upgrade jeden wynik; wymogi personelu;100→70 po75d standard, low≈42d; clampPI0..100; minor−2/major−5/catastrophe−15 raz; katastrofa obniżaBHP o1 i rebuild50% utraconego poziomu; audyt z dwóch UI nie przyznaje70PI.

### P13-F02 — TTS: zlecenie, obsada, pauza i koszt materiałów

**Dane:** technical_tasks: id, company_id, task_type, target_type/id, requested_by, assigned_staff_ref nullable, provider_kind internal/external/private_fire, status queued/waiting_funds/running/paused_strike/completed/cancelled, requested_at, started_at nullable, paused_at nullable, seconds_remaining BIGINT UNSIGNED, due_at nullable, materials_cost_minor BIGINT, materials_paid_at nullable, balance_version_id, state_version. INDEX(company_id,status), INDEX(status,due_at); employee occupancy wyłącznie przez Staff publiczny kontrakt, nie własny duplicate model. technical_task_effects: effect_key UNIQUE, task_id, target_ref, before/after, applied_at; append-only.

**HTTP:** POST /api/v1/technical/tasks: type,target_ref,provider_kind,staff_id(optional); POST /api/v1/technical/tasks/{id}/start; GET /api/v1/technical/tasks. task_type katalog YAML, bez dowolnego duration/cost/condition klienta. Błędy INELIGIBLE_STAFF/EMPLOYEE_BUSY/STRIKE_BLOCKS_INTERNAL_TASK/RESOURCE_NOT_OWNED/ILLEGAL_TASK_STATE/SAFETY_PRECONDITION.

**Proces:** utworzenie queued nie pobiera materiałów; StartTask sprawdza available Funds i rank14 harmonogram→18 task→20konto→40–80target, spłata raz materials_paid_at/effect key. Brak pieniędzy→waiting_funds bez debetu. Internal: jeden pracownik/aktywne zadanie, bieżące uprawnienia i przypisanie. Strajk techniczny zamraża własną kolejkę/seconds_remaining; wznowienie zachowuje opłacone materiały i przesuwa due, nie uruchamia drugi raz StartTask FTS. Zewnętrzna firma ×2.5koszt i×1.4czas względem wewnętrznej, nie mnożyć×1.4 ponownie od flagi strajku; prywatna straż35GBBL przez oddzielną komendę P20 i domenę blowout. Incydent+brakgotówki może stworzyć udokumentowane zobowiązanie zewnętrznej usługi zgodnie §21, nie darmową naprawę.

**Odbiór:** katalog6typów §11.3, start dwóch zadań tego samego pracownika, brakśrodków, retry paid task, strajk/pauza/wznowienie, ukończenie starego zadania po wygaśnięciuVIP; koszt i czas zewnętrzny nie potęgują się.

### P13-F03 — losowanie, skutki i rejestr incydentu

**Dane:** incidents: id, company_id, target_type/id, kind, severity micro/minor/major/catastrophe, status active/contained/resolved, logical_occurred_at, discovered_at, risk_owner VARCHAR(64), durable_slot_id, balance_version_id, rng_secret_id, rng_input_hash BINARY(32), sampled_result_json, aggregate_hours DECIMAL(16,4), state_version; UNIQUE(risk_owner,target_type,target_id,durable_slot_id), INDEX(company_id,status), INDEX(target_type,target_id,status). incident_effects: effect_key UNIQUE, incident_id, effect_type, quantity_bbl nullable, delta_condition nullable, amount_minor nullable, target_ref, occurred_at; append-only. RNG secret nigdy nie trafia do client payload ani zwykłego audytu.

**Kontrakt:** incidents.risk.resolve(RiskRequest: owner,target,logical_slot,exposure_interval,ModifierSnapshot,version)→IncidentOutcome. Właściciel przypadku jawny: well→Extraction exposure/P13risk, trip/pipeline/hub→Logistics exposure/P13risk; jeden owner rzutu. Staff i technika dostarczają niemutowalne dane przez kontrakty, nie osobne rolls. Legal sabotaż ma osobny typ/klucz i limitP18, nie spontaniczny road risk.

**Proces:** zamknięty LockPlan14–18+20–80, wyliczenie ryzyka raz wg §17.4 i §11A.6, wynik zapisany pod UNIQUE. Wielkość strat≤fizyczny wolumen atakowanego bufora/odcinka/ładunku. Koszt kary/naprawy Ledger ma jawny reason i przeciwkonto; strata ropy nie jest drugą opłatą. Zdarzenie incident.created po commicie, alert kategorii zgodnie z severity. Duże Δt używa kanonicznej agregacji §23.6, bez replay N godzin i bez ponownego losowania po retry. Czas bazowy słowa „tick” wymaga DPL07 przed implementacją hazardu.

**HTTP/UI:** GET /api/v1/technical/incidents i /{id}; endpoint wymuszenia incydentu tylko GM przez policy+audit, nigdy publiczny parametr severity. Klient widzi przyczynę/skutek/czas/akcję naprawy, nie seed. Test: HTTP+job+sweeper na tym samym target/slot dokładnie jeden incydent, jeden loss i jeden alert; ochrona zmienia tylko dozwolone wagi.

### P13-F04 — naprawa, blowout i uzgodnienie stanu zasobu

**Kontrakty:** incidents.repairs.start/complete oraz publiczne extraction.restoreOperationalState/logistics.repairAsset. Target module jest jedynym właścicielem statusu technicznego aktywa; P13 nie wykonuje prywatnego UPDATE wells/pipelines/hubs. Wymagana wspólna transakcja, ten sam connection, pełny LockPlan. status active wynika z legalnych warunków po naprawie (obsada, połączenie, pojemność), nie bezwarunkowe active.

**Dane:** incident_repair_links: incident_id, task_id, active BOOL, linked_at, resolved_at nullable; UNIQUE(incident_id,task_id); bieżący repair_current(incident_id PK,task_id) zapobiega dwóm konkurencyjnym naprawom tego samego problemu. blowout_escalation_state: incident_id PK, next_due_at, last_processed_slot_id, escalation_version, affected_targets_json (lista referencji, nie prywatne kopie stanów), state_version; due_events zawiera trwały slot eskalacji. Konkretne next_due_at według ustalonego modelu zagrożenia z DPL07; nie zależy od liczby odświeżeń strony.

**Proces:** StartRepair wybiera internal/external/private_fire z §17.4, wiąże task i koszty; CompleteTask stosuje efekt raz, zamyka incident/repair_current i przez kontrakt celu ustawia aktualny legalny stan. Blowout eskaluje do containment, nie kończy się od wylogowania/bana. Dla długiegoΔt jeden zagregowany wynik wg kanonu; naprawa i eskalacja tej samej chwili muszą być uporządkowane przez logiczny termin i transakcyjny stan (nie „kto pierwszy uruchomił request”); kolejność remisu wymaga karty zdarzenia zatwierdzonej przed wdrożeniem, bez cichego wyboru.

**Odbiór:** ukończenie naprawy nie usuwa cudzej części rurociągu/ładunku; po naprawie bez pracownika paused_staff, pełny bufor paused_storage, seized nadal zablokowany; blowout+strajk+brakgotówki ma płatną ścieżkę zewnętrzną/zobowiązanie, nie darmowy efekt; wyścig repair/escalation, zamknięty incydent nie wraca po retry; historia i downgradeBHP nie cofają się wraz z naprawą.


## 3. Rejestr gotowości i rozstrzygnięć ekonomicznych

Poniższe punkty są ujawnionymi brakami starych źródeł, nie błędami SQL działającej gry. Nie zmieniają zatwierdzonych OPEN01–OPEN12. Blokują wskazane operacje/podprzypadki, a nie całą dokumentację. Pełny model kolumn/FK i testy pozostają obowiązkową bramką implementacji.

| ID | Zakres karty | Alias źródłowy | Wymagane rozstrzygnięcie | Dowód |
|---|---|---|---|---|
| DOM01 | P08-F01 | POLICY-PAYROLL-01 | Wariant A: kalendarz UTC i reszty z HR-PAY-01/§19.3 wybrane; przed aktywacją wymagane migracja i test sumy miesiąca. | §4.1/§23; staff.params.morale.salary_satisfaction |
| DOM02 | P07-F02, P08-F03 | DISMISSAL-01 | Wariant A: osobne decyzja i koniec, populacja sporu snapshot z logical_at, rozłączne kary −10 lub −8 z cap −24/14 dni; testy i migracja przed aktywacją. | §4.2/§4.3/§5 |
| DOM03 | P08-F04 | NEGOTIATION-01 | Brak pełnej formuły rund zbiorowych i etapów eskalacji; ustalić współczynniki i koszt oferty przed produkcyjną negocjacją. | §5.2; staff params |
| DOM04 | P08-F02 | RAISE-AMOUNT-01 | Próg/deadline kontroferty istnieją, brak formuły żądanej kwoty podwyżki; potrzebna wersjonowana polityka generatora. | §4.2 |
| DOM05 | P06-F02 (wyłącznie event sankcji P21) | CARTEL-PROPAGATION-01 | Jawne −15cred/−15B2B vs propagacja50%; określić czy B2B finalne czy dodatkowe. Pozostałe reputacje i wyjątek2FA są rozpisane. | §2/§26.3 |
| DOM06 | P09-F01, P09-F02 | T09-L02 | Techniczny I/II/III ma decyzję A: 20k/50k/100k PLN i 24/48/72h, próg70, cap10; inne role/skill wymagają jawnych wpisów katalogu i testów. Refining bez zmian. | §6/§31; staff/refining training |
| DOM07 | P09-F03 (powrót po locku) | T09-L01 | Wariant A: po7 aktywnych dniach licznik kolejnych porażek=0, bez automatycznego bonusu nowej próby; historia zostaje. Test czwartej próby przed aktywacją. | §6; training.max_failures_before_lock |
| DOM08 | P09-F03, P13-F04 | T09-L03/DPL07-remis | Równość egzamin/koniec zatrudnienia oraz repair/escalation wymaga jawnego priorytetu zdarzeń przy identycznym logical_at. | §6/§17.4/§23.6 |
| DOM09 | P10-F02 (appeal) | T10-L01 | Zwrot opłaty appeal i kolejność bonusu2p.p.×skill vs clamp podstawowy; nie dopisywać refundu ani wyższego cap bez decyzji. | §7.3 |
| DOM10 | P10-F03 | T10-L02 | Wzór wyceny regionu dla5% odnowienia i zachowanie aktywów w30d grace/po utracie uprawnienia. | §19.4 |
| DOM11 | P10-F04 (koszty odmów/appeal) | CAPEX-COST-01 | Zakwalifikować koszt odrzuconego wniosku i appeal; ewidencja wpłaty nie oznacza automatycznie CAPEX uzyskanego pozwolenia. | §19.1/OPEN03 |
| DOM12 | P12-F04 (postojowe) | DPL01 | 30min w§11A.4 vs2h w§21/B2B: jawnie rozdzielić konteksty albo uzgodnić jedną wartość. Nie zmieniać2000PLN/h bez decyzji. | §11A.4/§13/§21 |
| DOM13 | P12-F04 (port) | DPL05 | Port500/1500/4000bbl na przebieg potrzebuje stałej jednostki ekspozycji/czasu, aby requesty nie zwiększały przepustowości. | §11A.5 |
| DOM14 | P12-F01 (przepięcie) | DPL06 | 2przebiegi przepięcia muszą dostać logiczne sloty/due_at niezależne od liczby wejść gracza. | §11A.2 |
| DOM15 | P13-F03, P13-F04 | DPL07-ekspozycja | Przypisać hazardom na tick ich kanoniczną bazę czasu; nie zamieniać ich po cichu na p_per_h. | §9.5/§17.4/§23.6 |
| DOM16 | P07-F01 (płatny katalog rekrutacji) | RECRUITMENT-CATALOG-01 | Wariant A: cennik, sloty, wagi i międzynarodowy mnożnik w Kadry §19.2; przed aktywacją kalibracja i test 90 dni. Nieobsługiwane kanały pozostają niedostępne. | §3.1; staff params |

Ujednolicone przy integracji bez zmiany liczb: wyjątek2FA bez propagacji do B2B; brak Cred→ContractRep; dwa widoki wyceny OPEN02; koncesja należy do Legal; domain key dostawy bez sweep_no; choked produkuje tylko do wolnego bufora, po full idleOPEX20%; pause/resume nie leczy uszkodzenia;0.05p.p./h to ok.0.00083p.p./min. Dla wariantu A wspólne staff_time_reservations i staff_person_skills rozwiązują styki Staff/Training/TTS; starsze nazwy są wyłącznie mapą migracyjną.

## 4. Kolejność realizacji i testy przekrojowe

P06 baza firmy i starter fixture → P07 dostępność/skills/zarząd → P08 nieblokowane morale i polityki → P09 katalog/flow egzaminu → P10 scope/uprawnienia → P11 złoże/odwiert → P12 fizyczne transfery → P13 naprawy/ryzyko. Pełny starter i aktywacja produkcji czekają na rzeczywistą gotowość kontraktów downstream, bez cyklu runtime rejestru i bez kopiowania prywatnego modelu.

Testy integracyjne: CreateCompany równolegle i rollback pełnego startera; dwóch najemców jednego huba; enroll vs TTS i skill cap; permit initial/appeal i0podwójnych refundów; geologia/odwiert/dostawa na HTTP+job+sweeper; receipt przed refund; payroll przed/po zmianie umowy; BHP+strajk+brak pieniędzy. Na końcu każdej próby sprawdzić pieniądze, rezerwacje, bbl, stan właściciela, wpisy outbox i utrwalony wynik RNG. Scenariusze czasowe wymagają FrozenClock i MySQL osobnych połączeń, nie sekwencyjnego klikania.

Maszynowy indeks i kontrakty wybranych komend: [OILEMPIRE_KONTRAKTY_P06_P13.yaml](OILEMPIRE_KONTRAKTY_P06_P13.yaml). To wycinek kontraktu i mapa gotowości, nie pełne OpenAPI wszystkich endpointów. Liczby balansu są w istniejącym YAML. Wyniki sprawdzenia i uwagi agentów: [audyt](OILEMPIRE_AUDYT_GOTOWOSCI_PROGRAMOWANIA.md).
