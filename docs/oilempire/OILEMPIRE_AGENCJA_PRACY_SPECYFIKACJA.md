# OilEmpire — Agencja pracy (moduł opcjonalny)

[Indeks specyfikacji](README.md) · [Kadry](OILEMPIRE_KADRY_ZATRUDNIENIE_ROZSZERZENIE.md) · [Umowy i koszty](OILEMPIRE_KADRY_UMOWY_PLACE_ODPRAWY.md) · [Standard modułu](OILEMPIRE_STANDARD_MODULU_WDROZENIE.md)

Status: zatwierdzony kierunek przyszłego rozszerzenia; projekt funkcjonalny i techniczny do rozwinięcia w karty wykonawcze. Bez kodu, migracji, aktywacji lub zmiany YAML v1.
Nazwa techniczna proponowana: WorkforceAgency, module_key workforce_agency. Oddzielny moduł automatyzacji zależny od Staff; Staff nie wymaga Agencji. Powiązania z P07–P09/P16/P24/P25; nie dodajemy teraz P26 ani nowego aktywnego korzenia balansu.
Usługa działa wewnątrz gry; nie integruje zewnętrznej rzeczywistej agencji i nie jest jeszcze decyzją o płatności realnymi pieniędzmi.

## 1. Cel i granice

Gracz może samodzielnie wykonywać wszystkie podstawowe czynności kadrowe. Agencja sprzedaje organizację i automatyzację, nie wyłączne uprawnienia, idealnych kandydatów czy odporność na chorobowe.
Istniejący kanał „agencja rekrutacyjna” pozostaje źródłem kandydatów Staff. Ten moduł jest zleceniobiorcą zarządzającym procesami. Nie pobieramy dwa razy opłaty za tę samą usługę pod dwiema nazwami.
Pracownicy zatrudnieni dla firmy są jej załogą również po zakończeniu usługi. Agencja nie jest nowym właścicielem employees, umów, pensji, morale, certyfikatów ani grafiku.
Pierwsza wersja to systemowa agencja NPC. Agencje prowadzone przez graczy i transfer personelu między firmami pozostają poza zakresem.

## 2. Trzy zakresy usługi

| Zakres | Co obejmuje | Rezultat |
|---|---|---|
| Kompletowanie załogi | Analiza instalacji, grafików, kompetencji i wakatów; rekrutacja w zatwierdzonym planie | Plan zatrudnienia i obsadzenie możliwych wakatów |
| Utrzymanie obsady | Powyższe oraz zastępstwa, końce umów, wygasające uprawnienia, odejścia | Obsada monitorowana według polityki |
| Obsługa kadrowa | Powyższe oraz grafiki, wnioski urlopowe, szkolenia, podwyżki i kontroferty w granicach mandatu | Bieżąca obsługa z raportem wyjątków |

Pakiet usług nie jest poziomem jakości kandydatów. Terminy, brak ofert, strajk HR i limity rekrutacji obowiązują jak przy działaniach ręcznych. Nie gwarantujemy pełnej obsady ani natychmiastowego zastępcy.

## 3. Uruchomienie i plan obsady

1. Gracz wybiera pakiet, instalacje/działy, horyzont planowania i priorytet.
2. Agencja pobiera przez kontrakty wymagania obsady, grafik, kwalifikacje, nieobecności i bieżące zobowiązania.
3. Pokazuje brakujące role i godziny; rozróżnia wakat trwały, zastępstwo oraz brak uprawnień. Nie mnoży liczby pracowników przez liczbę urządzeń, jeśli jeden operator może legalnie obsługiwać kilka.
4. Plan przedstawia: stan obecny/docelowy, liczby etatów/kontraktów, przedział kosztów, jednorazowe opłaty, prognozę cykliczną, ryzyka i decyzje wymagające zgody.
5. Gracz zatwierdza wersję planu i mandat, albo poprawia parametry. Sam podgląd niczego nie kupuje.
6. Agencja realizuje plan etapami, rewalidując stan przed każdą mutacją.

Priorytety: niższy koszt, stabilna obsada, rozwój załogi. Wszystkie muszą zachować twarde wymogi bezpieczeństwa, kwalifikacji, odpoczynku i budżetu. Żaden priorytet nie usprawiedliwia pracy bez uprawnień.
Plan może być częściowo wykonalny. Pozycje blokowane mają powód i alternatywę; brak odpowiednich kandydatów nie powoduje utworzenia losowego pracownika.

## 4. Mandat i decyzje gracza

Mandat określa dozwolone akcje, działy/instalacje, formy umów, minima kompetencji, widełki pensji, wymiar pracy, limity godzin, datę ważności i budżety.
Domyślnie tryb rekomendacji: agencja przygotowuje decyzje, gracz zatwierdza. Automatyczne wykonanie wymaga osobnego włączenia konkretnych akcji.

| Akcja | Domyślne zachowanie |
|---|---|
| Analiza i prognoza | Automatycznie, bez kosztu zobowiązania |
| Kampania rekrutacji | Po zatwierdzeniu planu lub w ramach jawnego mandatu i limitu |
| Zatrudnienie/zastępstwo | Automatycznie tylko dla zatwierdzonego zakresu i kosztów |
| Zamiana grafiku/urlop | W mandacie, jeśli pozostaje legalna obsada |
| Szkolenie/odnowienie kwalifikacji | W limicie opłaty, czasu i utraty obsady |
| Podwyżka/awans/kontroferta | W zatwierdzonym limicie nowego stałego kosztu |
| Zwolnienie, odprawa, skrócenie umowy | Zawsze osobne zatwierdzenie gracza w pierwszej wersji |
| Zmiana własnego mandatu, zwiększenie limitu | Wyłącznie gracz; agencja nie zatwierdza sama siebie |
| Kredyt, realna płatność, zakup GBBL | Poza mandatem Agencji |

Upoważnienie musi być ponownie sprawdzone po stronie serwera w chwili wykonania, nie tylko przy planowaniu lub w UI. Pracownik z ukrytą cechą nie może być wybierany/odrzucany przez agencję na podstawie nieujawnionego profilu.
Agencja widzi te same legalnie dostępne oceny HR co firma. Korzysta z rozmów/testów za normalnym kosztem i w normalnym czasie. Nie ujawnia rzeczywistej podatności na niedyspozycję przez ranking/rekomendację.

## 5. Budżet i koszt

Rozliczenie w walucie gry PLN proponowane jako wariant podstawowy. Nie oznacza zatwierdzenia GBBL, VIP ani opłaty realnymi pieniędzmi.
Oddzielne pozycje: abonament za obsługę, opłata za skuteczne zatrudnienie, koszty kampanii, pensje i narzuty, zastępstwa, szkolenia, odprawy. UI pokazuje pełną sumę i właściciela naliczenia.
Katalog taryf musi jednoznacznie wskazać, czy abonament/prowizja zawiera koszt danego kanału. Nie doliczać zawartej pozycji ponownie.
Success fee powstaje dopiero po skutecznym utworzeniu umowy przez Staff, raz dla zatrudnienia. Odmowa oferty i retry nie generują prowizji. Przy przedłużeniu umowy nie ma automatycznie prowizji za „nowe zatrudnienie”.

Mandat zawiera:
- limit jednorazowej akcji i limit wydatków Agencji w miesiącu UTC;
- limit docelowego miesięcznego kosztu całej załogi;
- limit nowego zobowiązania wynikającego z podpisywanej umowy, w tym minimalnego okresu/wypowiedzenia;
- rezerwę dla zastępstw i dopuszczalną liczbę kampanii.

Niską opłatą jednorazową nie można obejść limitu pensji cyklicznej. Brak ceny lub formuły zobowiązania blokuje daną ofertę, nie ustawia jej kosztu na zero.
Ledger rezerwuje gotówkę potrzebną do konkretnej opłaty; osobny rejestr mandatu rezerwuje pojemność budżetu. To dwa różne ograniczenia.
Dostępny limit miesiąca = limit − wykonane obciążenia − aktywne rezerwacje limitu. Bieżący koszt załogi i rezerwy zatrudnień muszą być liczone atomowo, aby dwie równoległe rekrutacje nie przekroczyły limitu.
Rezerwacja limitu przechodzi w wykorzystanie albo jest zwalniana raz; rollback zwalnia obie rezerwacje. Zmiana miesiąca nie zwalnia rezerwacji przyszłych umów ani nie resetuje ich trwałych zobowiązań.
Quote określa okres taryfy, kwoty, zawarte usługi, ważność i wersję danych. Zmiana taryfy nie zmienia wstecz zaakceptowanych cen.

Abonament za okres usługi [start,end), naliczany proporcjonalnie do aktywnego czasu świadczenia z resztami jak w płacach; cena miesiąca i szczegóły pauzy firmy wymagają AG-CFG01.
Przy zawieszeniu usługi z powodu długu nie powstają nowe opłaty abonamentowe za czas, w którym Agencja nie świadczy usługi. Istniejące długi i pensje zachowuje Finance/Staff. Brak automatycznego kredytu.

## 6. Wykonywanie i współpraca z graczem

Każda potrzeba obsady ma trwały demand_id z zakresem/okresem/rolą. Jedna aktywna intencja na popyt i rodzaj działania; nie prowadzić kolejnych kampanii dlatego, że job uruchomił się ponownie.
Stany działania: proposed → awaiting_approval albo queued → executing → succeeded; alternatywnie blocked, cancelled lub failed.
Potwierdzenie gracza odnosi się do plan_version, quote_hash, action_id i oczekiwanego stanu. Zmiana ceny/warunków po zatwierdzeniu wymaga nowej zgody albo nadal mieszczącego się jawnego mandatu.
Ręczne zatrudnienie/przydział może usunąć wakat. Przed hire ponownie sprawdzamy demand; nie zatrudniamy nadmiarowej osoby według starego planu.
Ręczna korekta grafiku lub decyzji może przypiąć pozycję jako wyłączoną z automatyzacji. Agencja pokazuje skutki i nie cofa jej w następnej pętli.
Zwolnienie gracza lub zmiana budżetu nie uruchamia „wojny” zatrudnij/zwolnij: trwałe wersje potrzeb, kontrola blokad oraz cooldown ponownego planowania.
Po końcu zgody/innej wersji limitów worker porzuca niezatwierdzone rozpoczęcie, nie cały już legalnie uruchomiony proces Staff.

## 7. Stany usługi i rezygnacja

Stany umowy obsługi: draft, active, paused_by_player, suspended_for_debt, cancelling, ended. To osobne stany od runtime modułu installed/ready/active/draining/disabled/faulted.
Pauza gracza zatrzymuje nowe automatyczne zobowiązania. Rezygnacja natychmiast unieważnia mandat dla przyszłych akcji, a kończy rozliczenie już należnych usług.
Akcja już zatwierdzona i wykonywana w transakcji konkuruje z revokacją pod wspólną blokadą/version check. Możliwe wyniki: wykonała się przed revokacją albo została zablokowana; brak częściowego zatrudnienia.
Podpisane umowy, pensje, grafiki i pracownicy pozostają w Staff. Rozpoczęte kampanie i kursy przechodzą do obsługi ręcznej według dotychczasowych zasad; anulowanie ma normalne koszty Staff. Brak domyślnego zwrotu za usługę już wykonaną.
Agencja zamyka własne rezerwacje/rozliczenia i przekazuje listę spraw. Nie zwalnia pracowników za rezygnację z abonamentu.
Ponowna aktywacja wymaga aktualnego planu i nowej zgody; nie przywraca starego mandatu automatycznie.
Draining modułu blokuje nowych klientów i nowe działania, domyka własne należności oraz przekazuje procesy Staff. Disabled dopiero bez aktywnych własnych procesów/rezerwacji wymagających handlera. Nie blokuje ręcznych Kadr.
Urlop firmy, dormant i recovery korzystają z jawnej polityki Core. Agencja nie wznawia produkcji i nie obchodzi pauzy samodzielnie.

## 8. Architektura i dane

Katalog Modules/WorkforceAgency zgodny ze standardem. Proponowane publiczne kontrakty agency.plans v1, agency.mandates v1, agency.actions v1; nazwy i manifest wymagają walidacji przed wdrożeniem.
Zależności jednokierunkowe: Agencja → Staff (umowy, grafiki, rekrutacja, szkolenia), Core (firma/dostęp), Ledger/Finance (koszty), Simulation (due-events). Wymagania obsady przez publiczne DTO właścicieli instalacji, bez zapisu prywatnych tabel. Powiadomienia przez outbox.
Staff udostępnia neutralny kontekst delegowanego aktora z capability, company i mandate_ref. Nie importuje klas Agencji. Granica egzekwowania mandatu musi działać również przy retry i CLI.
Agencja posiada tylko:
- agency_subscriptions: firma, pakiet, stan, taryfa, okresy rozliczeń, state_version;
- agency_mandates i niezmienne mandate_versions: scope, limity, akcje, terminy, status revokacji;
- agency_plans/plan_items: snapshot wersji wejściowych, wymagania, propozycje, koszt, blokery;
- agency_actions: demand_ref, action_type, status, approval_ref, effect_key UNIQUE, Staff operation_ref;
- agency_budget_reservations: mandate/version/period, kwota/miara, status, unikatowa intencja;
- append-only agency_decision_events: aktor, powód strukturalny, wersje, wynik, koszt i referencje.

Wymagane unikalności: jedna bieżąca usługa firmy, jedna aktywna intencja danego demand/action, pojedyncza prowizja za zatrudnienie. Implementacja aktywnych slotów bez polegania na nullable UNIQUE dla całej historii.
Pieniądze BIGINT w najmniejszej jednostce, API string; czasy UTC; FK i zachowanie kasowania muszą chronić historię. Brak CASCADE usuwającego rozliczenia po zakończeniu abonamentu.
Plan jest wyliczany poza długą transakcją, ale wersje i uprawnienia rewalidowane pod blokadą przed skutkami. Plan nie blokuje pracowników na czas oglądania ekranu.
Hire + pobranie należnej prowizji + zużycie limitu + zapis wyniku/outbox w jednej krótkiej transakcji wspólnych kontraktów. Jeżeli kontrakt jest asynchroniczny, wymaga udokumentowanej rezerwacji i recovery — bez prowizji przed potwierdzonym wynikiem.
Globalny LockPlan musi rozszerzyć rejestr o nowe rekordy przed kodowaniem. Nie deklarujemy nowego lokalnego rank, który narusza Staff14 → konta20/21 → append140.
Długie planowanie stosuje istniejące lease/fencing; krótka operacja nie przejmuje widocznego pending. Deduplikacja event_type+entity+durable_slot, bez zależności od HTTP/job/sweeper.
Żaden błąd powiadomienia nie cofa zatrudnienia; brak odpowiedzi HTTP nie upoważnia do drugiego hire.

## 9. Kontrakty HTTP do uszczegółowienia

Projekt zasobów /api/v1/workforce-agency:
| Operacja | Cel |
|---|---|
| GET /catalog | Pakiety, ceny i dostępność; bez ukrytych statystyk kandydatów |
| POST /plans | Utworzenie wersjonowanej analizy z podanego zakresu |
| GET /plans/{id} | Plan, prognoza, blocked reasons i stan wykonania |
| POST /plans/{id}/approve | Zatwierdzenie wersji i quote; nie dowolnego przyszłego kosztu |
| POST /subscriptions | Zawarcie usługi po zatwierdzonym quote |
| PUT /mandates/{id} | Nowa wersja upoważnienia z CAS |
| POST /actions/{id}/approve | Jednorazowa decyzja wymagająca gracza |
| POST /subscriptions/{id}/pause | Blokada nowych działań |
| POST /subscriptions/{id}/cancel | Revokacja, rozliczenie i przekazanie |
| GET /actions oraz /history | Paginowana historia własnej firmy |

Wszystkie mutacje: autoryzacja obiektu, Idempotency-Key, expected_state_version; ceny wyłącznie serwerowe. Błędy m.in. MANDATE_REVOKED, BUDGET_EXCEEDED, PLAN_STALE, QUOTE_EXPIRED, STAFF_UNAVAILABLE, APPROVAL_REQUIRED.
Tabela nie jest pełnym OpenAPI. Request/response, statusy HTTP, limity i schematy wymagają AG-CONTRACT01 przed tworzeniem klientów.

## 10. Wygląd i panel

W Kadrach dodatkowa zakładka „Agencja pracy”, jeśli moduł jest dostępny. Nie zastępuje podstawowych ekranów.
Widok: aktywna usługa, zakres upoważnienia, wykorzystanie limitów, plan obsady, sprawy do zatwierdzenia, wykonane działania oraz blokery.
Uruchomienie: wybór pakietu → zakres firmy → priorytet i budżet → plan/koszty → zgoda. Każdy etap pozwala wrócić bez przypadkowej opłaty.
Plan pokazuje osobno „już należne”, „zaakceptowane zobowiązanie” i „prognoza”. Status „brak kandydatów” nie wygląda jak ukończone zadanie.
Zatrzymanie automatyzacji widoczne i łatwo dostępne; przed rezygnacją lista procesów do przejęcia i końcowych kosztów.
Vue3 stosuje istniejący plan wizualizacji Kadr: grafit/złoto, SVG, własne modale, PL/EN/DE, mobile320px, reduced-motion. Nie powstaje osobny styl lub nowy plan animacji.
Filament: wersjonowane pakiety/taryfy, katalog dozwolonych akcji, kontrola zawieszonych procesów i audyt. GM nie może po cichu zmienić podpisanej umowy ani stworzyć idealnego kandydata.
Główny administrator nie musi wpisywać komentarza; nadal obowiązuje audyt i zatwierdzanie balansu. Uzasadnienie decyzji automatu to strukturalny reason_code, nie obowiązkowy komentarz gracza.

## 11. Etapy i otwarte bramki

A: rekomendacje i plan obsady, bez samodzielnych mutacji.
B: kontrolowane rekrutacje/zastępstwa w mandacie i budżecie.
C: grafiki, urlopy, szkolenia i negocjacje po stabilizacji Staff.
Nie uruchamiać C przed wdrożeniem i przetestowaniem wariantu A Kadr §19 oraz HR-CONTRACT01, zamknięciem pozostałych bramek HR-CFG03/05 i osobną kalibracją automatyzacji Agencji.

| Bramka | Do przygotowania |
|---|---|
| AG-CFG01 | Konkretne taryfy, success fee, zawarte koszty, zasady pauzy i rozliczenia abonamentu |
| AG-CFG02 | Horyzont prognozy, priorytety wyboru, retry/cooldown, zasady częściowej realizacji |
| AG-CONTRACT01 | DDL, indeksy/unikaty, pełne OpenAPI, delegated actor, wspólny lock order i karty akcji |
| AG-ECON01 | Symulacja kosztów, skali firm, niewypłacalności i wpływu automatyzacji na gospodarkę |
| AG-RELEASE01 | Manifest, kandydat balansu, aktualizacja walidatora korzeni/planów, draining i testy aktywacji |

Brak zatwierdzonych cen nie oznacza darmowej agencji. Nie dopisujemy cen do aktywnego game_balance v1. To przyszły moduł, nie wymóg ukończenia bazowych Kadr.

## 12. Scenariusze odbioru

1. Wyłączona Agencja: pełne ręczne Kadry nadal dostępne.
2. Stary plan po ręcznym zatrudnieniu: brak drugiego zbędnego hire.
3. Dwie równoległe akcje: nie przekraczają budżetu ani limitu kosztów cyklicznych.
4. Retry i utracona odpowiedź: jeden hire, jedna prowizja, jeden wynik.
5. Cofnięcie mandatu równolegle z workerem: atomowa legalna granica bez częściowego skutku.
6. Zmiana taryfy/quote: brak pobrania niezatwierdzonej ceny.
7. Ukryte cechy: brak przewagi informacyjnej, wycieku przez ranking lub powiadomienie.
8. Brak ofert lub kwalifikacji: blocked, bez generowania sztucznej załogi.
9. Wypowiedzenie: wymagane osobne zatwierdzenie i zwykły koszt odpraw.
10. Pauza/rezygnacja/dług: pracownicy, pensje, kursy i historia pozostają; nowe działania zablokowane.
11. Przełom miesiąca: rezerwacje i zobowiązania nie znikają ani nie liczą się dwa razy.
12. Obca firma, wygasła sesja i nieuprawniony GM: odmowa również na poziomie kontraktu.
13. Strajk/urlop firmy/dormant: brak obejścia ograniczeń przez automat.
14. Draining i restart workera: domknięcie, przekazanie i odzyskanie bez ponownego skutku.
15.90dni: koszt, wakaty, rotacja i rekrutacja przy małej/dużej firmie oraz kryzysie.
16. Web/mobile/Filament: SVG, języki, focus, reduced-motion i czytelne zgody.

Są to testy do implementacji, nie wykonane wyniki. Ta runda zmienia tylko dokumentację; brak wpływu na dane i uprawnienia runtime. Wycofanie przez odwrócenie zmian tych dokumentów bez cofania obcych commitów.
