# OilEmpire — rozszerzenie kadr i zatrudnienia

Doprecyzowanie HR-CFG02/04: [Umowy, wynagrodzenia i odprawy — HR-PAY-01](OILEMPIRE_KADRY_UMOWY_PLACE_ODPRAWY.md). Zawiera kandydujące stawki, algorytmy i przykłady sprawdzone arytmetycznie; nie zmienia YAML v1. Pozostają kalibracja, grafiki/absencje, DOM02/DOM08 oraz kontrakty wykonawcze.


Status: zatwierdzony zakres funkcjonalny z rozmowy właściciela, specyfikacja do programowania; brak implementacji. Wartości nowych stawek i końcowe kontrakty wykonawcze pozostają bramką gotowości.
Właściciel: Staff. Powiązania: [architektura](OILEMPIRE_ARCHITEKTURA_SYSTEMOWA.md) §3–§6, §19, §23; [karty](OILEMPIRE_KARTY_IMPLEMENTACJI_P06_P13.md) P07–P09; P06 reputacja, P11–P13 obsada, P16 finanse, P24 interfejs.
Ten rozdział jest źródłem wymagań dla rozszerzenia HR, nie zmianą aktywnego YAML v1. DOM01, DOM02 i DOM16 otrzymują szerszy zakres; pozostałe propozycje DOM nie zostają automatycznie zatwierdzone.

## 1. Granica względem wcześniejszego modelu

| Obszar | Docelowa reguła |
|---|---|
| Rekrutacja local/international/headhunter | Oddzielne kanał, zasięg, region, budżet i warunki oferty. Stary katalog to baza do migracji, nie pełny nowy katalog. |
| Jedna pensja miesięczna | Warunki wersjonowane według formy współpracy i podstawy rozliczenia. |
| Natychmiastowe zwolnienie | Osobne daty decyzji, odsunięcia od pracy, końca umowy i wymagalności należności. |
| notice zawsze bez pracy | Wypowiedzenie może obejmować normalną pracę, przekazanie obowiązków lub płatne odsunięcie. |
| Stała dostępność przydzielonej osoby | Przydział działa tylko w dostępnym przedziale grafiku, z uprawnieniami i bez kolizji. |
| Urlop firmy | Pozostaje mechaniką zamrożenia §19; urlop pracownika nie zamraża firmy. |
| Odprawa | Osobne zobowiązanie według umowy i przyczyny zakończenia, bez automatycznej kwoty dla wszystkich. |

Historyczne wartości morale, kursów, limitów i kosztów zachowują status bazowy. Nie sumować nowych kar z istniejącymi za ten sam skutek. Aktywacja rozszerzenia wymaga scalenia reguł i ponownej kalibracji, nie interpretowania starszego przykładu jako wyjątku.

## 2. Rekrutacja i kandydat

Kanały: gazeta lokalna, radio regionalne, telewizja, portal ogłoszeniowy, portal branżowy, uczelnia/szkoła, polecenie pracownika, agencja i headhunter.
Zasięg: lokalny, krajowy, międzynarodowy; katalog określa legalne kombinacje kanału, regionu i stanowiska.
Telewizja zwiększa przede wszystkim zasięg; kanał branżowy dopasowanie. Żaden kanał nie gwarantuje najlepszego kandydata.

Kampania utrwala: stanowisko, region, kanał, zasięg, budżet, widełki wynagrodzenia wraz z jednostką i wymiarem pracy, akceptowane formy umowy, grafik, start/koniec, wersję polityki i RNG.
Proces: draft → quoted → searching → ready → closed; anulowanie według jawnej polityki kosztu, bez resetowania wyniku RNG.
Liczba i profil kandydatów zależą od katalogu i atrakcyjności oferty; dopuszczalny brak dopasowanych kandydatów. Budżet ma malejący przyrost skuteczności.
Stare max2 procesy, czasy lokalne/międzynarodowe i headhunter25GBBL/raz dziennie nie są samowolnie zmieniane. Mapowanie do nowego katalogu i efekty czas/budżet wymagają HR-CFG01.

Profil: tożsamość, doświadczenie, istniejące skills/traits, potencjał rozwoju, oczekiwania płacowe, preferowane umowy/grafiki/wymiar, początkowe morale i certyfikaty.
Nie tworzymy drugiej lojalności lub ambicji obok employee_traits. Nowe cechy wymagają słownika z zakresem i jednoznacznym efektem.
Zmęczenie/stres są stanami eksploatacyjnymi, nie losowym obciążeniem kandydata dla samej różnorodności.
Ocena HR ujawnia przedziały lub informacje oznaczone jako nieocenione; test nie zmienia rzeczywistych cech. Endpoint nie ujawnia ukrytych wartości.
Wyniki są utrwalone raz; odczyt, retry i rezygnacja z oferty nie przerzucają losowania. Zatrudnienie kopiuje ten sam profil. Ponowne zatrudnienie zachowuje tożsamość i historię.

## 3. Umowy i aneksy

Formy: employment (umowa o pracę), mandate (zlecenie), business_service (B2B), management (kontrakt menedżerski z jawną podstawą).
Czas trwania i forma to osobne pola: próbny/określony/bezterminowy/do zadania tylko w dozwolonych kombinacjach.
Dla etatu: 1/4, 1/2, 3/4, 1; dla pozostałych uzgodniona liczba godzin, usługa lub ryczałt. Nie nazywać B2B etatem.
Kandydat może odmówić nieakceptowanej formy, grafiku lub pensji; akceptacja i ewentualne RNG zostają zapisane.
Aneks tworzy nową wersję effective_from; nie nadpisuje historii. Przed zmianą rozliczyć poprzedni segment.
Płaca w umowie określa actual_amount oraz basis monthly/hourly/task/retainer i currency. Przy etacie jasno rozróżnić faktyczną pensję od ekwiwalentu pełnego etatu, aby nie mnożyć przez wymiar dwukrotnie.
Nie zakładamy, że zlecenie/B2B zawsze tańsze. Dopuszczalność formy zależy od charakteru roli i zatwierdzonego katalogu.

## 4. Czas, obsada i nieobecności

Wszystkie terminy w UTC według istniejącego zegara świata; lokalna strefa służy prezentacji. Przedziały [start,end), bez podwójnej granicy.
Grafik jest wersjonowany; obejmuje zmiany, odpoczynek, nieobecności i dostępność. Limity pracy, noc, nadgodziny i okres rozliczeniowy są parametrami zatwierdzonej polityki.
Pół etatu ogranicza czas, nie umiejętności. Alokacja≤100% obowiązuje wewnątrz dostępnego czasu, nie tworzy dodatkowych godzin.
Jedna osoba nie pracuje jednocześnie na dwóch pełnych zmianach, w szkoleniu i w naprawie.
employee_availability pozostaje wspólną bramką Staff; kalendarz ją rozszerza, nie zastępuje drugim niezależnym systemem blokad.
Każda instalacja deklaruje minimum obsady, kwalifikacje, rolę zastępcy i wpływ braków. Brak obsady krytycznej zatrzymuje odpowiednią operację; inne niedobory mają jawny efekt.
Nie mnożyć automatycznie całej produkcji przez liczbę etatów. Istniejące mnożniki strajku i obsady nie mogą liczyć tego samego braku dwukrotnie.
Szkolenie ma osobno czas kalendarzowy kursu i zajętość pracownika. Mapowanie kursów24/48/72h do grafików wymaga HR-CFG03; nie zmieniamy czasu kursu po cichu.
Wygasłe uprawnienia blokują nowe wymagające ich czynności. Dla trwającej pracy katalog określa bezpieczne przekazanie/zatrzymanie; nie porzucamy cysterny w tranzycie.
Urlop/choroba pracownika uruchamia zastępstwo, nie zatrzymuje zegara firmy. Urlop firmy zachowuje istniejące reguły zamrożenia; wyjątki podatkowe/fakturowe nowego modelu wymagają jawnego mapowania.

## 5. Płace i pełny koszt

Koszt firmy = wynagrodzenie + obciążenia firmy + dodatki + świadczenia + odrębne koszty organizacyjne.
Składniki mają kod, podstawę, stawkę, odbiorcę, okres, datę naliczenia, termin płatności, policy_version i unikatowy effect_key.
Rozdzielić wynagrodzenie brutto, potrącenia z niego i dodatkowy koszt firmy. Nie dodawać potrąceń drugi raz do kosztu.
B2B: kwota netto faktury, podatek i kwota do zapłaty są odrębne; nie utożsamiać przepływu pieniężnego z kosztem ekonomicznym.
Płatny urlop w pensji miesięcznej nie generuje drugiej pensji. Zastępca jest osobnym kosztem.
Kwoty BIGINT w najmniejszej jednostce, stawki/precyzyjne reszty bez float. Segmenty na granicach umowy, taryfy, grafiku i nieobecności. Rachunek całego okresu równy sumie rozliczeń przy wielu odczytach.
Naliczenie nie oznacza zapłaty. Finance prowadzi zobowiązanie i spłaty; saldo należne=naliczone−spłacone−jawne korekty. Niedobór środków nie usuwa długu.
Zaległość emituje jeden identyfikowalny sygnał do istniejącego modelu morale/sporu. Spłata długu nie nalicza drugi raz wynagrodzenia.
Nie dodajemy nowego dzielnika miesiąca ani procentu składek w kodzie: DOM01/HR-CFG02 wymagają tabel i przykładów obliczeniowych.

## 6. Zakończenie współpracy i odprawa

Proces: quote → notice/uzgodniony termin → employment_ended → final_settlement_recorded. Płatność może nastąpić później.
Quote zawiera podstawę zakończenia, daty, pozycje kosztu, wpływ na obsadę i morale, policy_version, expires_at i state_version.
Potwierdzenie ponownie sprawdza warunki; zmiana umowy/stażu/praw do świadczeń unieważnia quote.
work_release_at, employment_ended_at, dispute_membership_until oraz payment_due_at to różne pojęcia.
Samo złożenie wypowiedzenia nie anuluje kursu. Faktyczny koniec zatrudnienia stosuje regułę P09; remis z egzaminem pozostaje zależnością DOM08.
Rozliczenie końcowe: niewypłacona pensja, dodatki/premie należne, wynagrodzenie okresu wypowiedzenia, ekwiwalent niewykorzystanego urlopu oraz odprawa/rekompensata, jeżeli przysługuje.
Każdy składnik rozlicza tylko nieujęty wcześniej okres/kwotę. Odprawa nie zastępuje należnej pensji.
Macierz formy umowy × inicjatora × przyczyny × stażu określa prawo do odprawy, podstawę, mnożnik, limity, termin i wyjątki.
Redukcja przez firmę może rodzić odprawę; wygaśnięcie umowy i odejście pracownika nie oznaczają automatycznie odprawy. B2B/management mają warunki wcześniejszego rozwiązania.
To zasady modelu gry do parametryzacji, nie deklaracja obowiązku prawnego w każdej rzeczywistej sytuacji.
Odsunięcie od pracy może być natychmiastowe, ale należne koszty pozostają. Brak gotówki nie blokuje rejestracji wymaganego końca umowy; tworzy dług według Finance.
Historia i zobowiązania pozostają po odejściu. Nie kasujemy osoby, certyfikatów ani zdarzeń.
DOM02 nadal blokuje aktywację kar: trzeba uzgodnić −10 instant, −8/os14dni cap−24 oraz historyczne24h populacji sporu. Nie nazywać24h ustawowym okresem wypowiedzenia.

## 7. Rozwój i automatyzacja HR

Etap A: umowy/aneksy, grafiki, pełne koszty, nieobecności, uprawnienia, rozliczenie odejścia, zaległości, historia i prognoza.
Etap B: mentoring, przekazanie obowiązków, awans/rekrutacja wewnętrzna, zastępstwa tymczasowe, kontroferty i automatyzacje.
Okres próbny jest częścią A; rozbudowany efekt wdrożenia i mentora częścią B.
Mentor poświęca zarezerwowany czas; nie daje darmowej dodatkowej pracy. Awans wymaga kwalifikacji i nowej zaakceptowanej wersji warunków.
Kontroferta odejścia to osobny proces od istniejącej kontroferty płacowej P08, ale współdzieli warunki pensji i wyklucza równoległe sprzeczne aneksy.
Zastępca wymaga tych samych uprawnień. Delegowanie pracowników między graczami nie jest objęte tym etapem.
Reputacja pracodawcy: przez kontrakt Core, bez drugiej prywatnej kopii company_credibility. Mapowanie wag pozostaje HR-CFG05.
Automatyzacja ma jawne włączenie, zakres, limit kosztów, uprawnienia i audyt. Nie podpisuje dowolnej droższej umowy bez limitu.
Prognoza przedstawia koszty i braki obsady z horyzontem oraz wersją danych; odróżnia zobowiązania od szacunków i nie księguje niczego.

## 8. Model danych i kontrakty do rozwinięcia

Staff jest właścicielem kontraktów zatrudnienia, aneksów, grafików, nieobecności, kwalifikacji personelu, kampanii, oceny kandydatów i zakończeń.
Finance/Ledger są właścicielami pieniężnych zobowiązań i księgowań; Legal dostarcza politykę poprzez publiczny kontrakt, nie zapisuje tabel Staff.
Logical entities: employment_contracts, employment_contract_versions, schedule_versions, work_intervals, absence_requests, personnel_credentials, termination_cases, settlement_components, candidate_assessments.
To katalog logiczny do kart/DDL, nie gotowe migracje. employee_salary_terms i nowe wersje umowy muszą mieć jedno źródło prawdy, nie dwa zapisy pensji.
Istniejące employee_skills/traits/training_certificates i employee_availability są ponownie używane.
Kontrakty aplikacyjne: QuoteRecruitment, StartRecruitment, AssessCandidate, QuoteEmployment, Hire, AmendEmployment, PlanRoster, RequestAbsence, QuoteTermination, ConfirmTermination, FinalizeEmployment, AccrueEmploymentCosts, ForecastStaffing.
Komendy wymagają actor/ownership, Idempotency-Key, expected_state_version; wyceny są serwerowe. Statusy i granice kwot walidowane po stronie serwera.
Brak opublikowanych nowych endpointów: OpenAPI wymaga uzupełnienia request/response/errors przed kodowaniem klientów.
Due-event, odczyt leniwy i sweeper uruchamiają tę samą akcję. Trwały klucz zdarzenia obejmuje rodzaj, encję i slot; nie źródło wywołania.
LockPlan zachowuje rank14 Staff przed kontami20/21 i append140. Pełną kolejność nowych tabel trzeba dodać do rejestru przed migracjami; nie dopisujemy lokalnego order w jednym serwisie.
Outbox/audyt/skutek finansowy atomowo; push po commicie. Retry zwraca wcześniejszy rezultat.
Draining kończy istniejące umowy i naliczenia; wyłączenie modułu nie likwiduje zobowiązań.

### Uzupełnienia po przeglądzie agenta
- Trwała tożsamość osoby i kolejne okresy zatrudnienia są oddzielne. Klucz odprawy wskazuje okres zatrudnienia i rodzaj świadczenia; UNIQUE source_candidate_id sam nie obsługuje powrotu.
- Zadowolenie płacowe porównuje pensję, oczekiwania i minimum na tej samej podstawie czasu i etatu. Pół etatu nie oznacza automatycznie połowy zadowolenia.
- Istniejące stawki §4.1 są kalibracją PL2025×1,8. Nie nazywać ich ustawowymi kosztami; dodanie składek wymaga ponownej kalibracji całego obciążenia HR.
- Settlement dzieli produkcję na granicach grafików/nieobecności/uprawnień przed obliczeniem skutków fizycznych. Faza6 HR nie poprawia wstecz obsady użytej już przez fazę1. Zmiana grafiku wstecz jest odrzucana; korekty finansowe są oddzielne i audytowane.
- Mandat i prawo podpisu dyrektora są oddzielne od jego dostępności operacyjnej. HR-CFG03 musi opisać zastępstwo i istniejące ścieżki awaryjne, aby koniec zmiany nie blokował przypadkowo całej gry.
- Dodatkowe testy: proporcjonalne wynagrodzenie pełny/pół etatu daje to samo zadowolenie; powrót pracownika nie odtwarza starej odprawy; grafik wstecz odrzucony; prognoza nie księguje; mandat dyrektora nie znika na końcu zmiany.

## 9. Panel, API i bezpieczeństwo

Gracz: kampanie/kandydaci, porównanie ofert, umowa/aneksy, grafik, nieobecności, uprawnienia, koszty, prognoza, odejścia i historia.
GM: katalog kanałów, form umów, ról/uprawnień, wersje stawek i macierz odpraw, podgląd naliczeń i audyt korekt.
Zmiana konfiguracji to kandydat wersji i istniejąca bramka balansu; nie edycja historycznych umów lub księgowań.
Główny administrator zachowuje uzgodniony brak obowiązku komentarza; autoryzacja, audit i zasady zatwierdzania wersji nadal obowiązują.
Web/mobile/Filament wywołują te same akcje. Komunikaty w komponentach z lokalnymi SVG, PL/EN/DE, bez alert/confirm/prompt.
Ukryte cechy kandydata nie trafiają do DTO gracza/logów/analityki. GM podgląda je wyłącznie według capability.
Odmowa dostępu do cudzej osoby, umowy, kampanii i dokumentu działa na serwerze również dla odczytu, eksportu i potwierdzenia quote.

## 10. Bramki gotowości

| ID | Co pozostaje do zamknięcia | Powiązanie |
|---|---|---|
| HR-CFG01 | Cennik kanałów/zasięgów, krzywe skuteczności, liczba/ważność ofert, preferencje, relokacja i migracja dotychczasowych limitów | DOM16, P07-F01 |
| HR-CFG02 | Kalendarz płac, składki/podatki, dodatki, faktury, potrącenia i dokładne zaokrąglenia z przykładami | DOM01, P08-F01, P16 |
| HR-CFG03 | Normy czasu/odpoczynku, noc, urlopy/choroby, kurs vs grafik, minimum obsady każdej instalacji i startera | P07-F03, P09, P11–P13/P19 |
| HR-CFG04 | Macierz odpraw/wypowiedzeń/stażu i rozliczenia, kolejność równoczesnych zdarzeń, kary morale | DOM02/DOM08, P07-F02/P08-F03 |
| HR-CFG05 | Wagi reputacji, mentoring, kontroferty, automatyzacja i koszty zastępstw | P06/P07/P08 |
| HR-CONTRACT01 | Karty delta, DDL/FK/indeksy, pełne OpenAPI, migracja danych i wspólny lock order | P07–P09/P16 |

Wszystkie powyższe pozostają otwarte. Akceptacja zakresu nie oznacza zatwierdzenia niepodanych kwot ani gotowości implementacji całego modułu.
Stawki oparte na prawie wymagają kraju, źródła i daty obowiązywania; nie zakładamy wspólnych przepisów wszystkich regionów gry. Źródła wskazane w rozmowie są punktem wyjścia do osobnej kalibracji, nie zweryfikowanym cennikiem.
Nowe wartości trafiają do kandydata balance_version z testami, a nie do historycznego YAML v1 przez nadpisanie.

## 11. Scenariusze odbioru do zaprogramowania

| Test | Oczekiwany rezultat |
|---|---|
| HR-T01 odczyt/retry kampanii i zatrudnienia | Ci sami kandydaci, jedna opłata i jedno zatrudnienie |
| HR-T02 profil przed/po hire i ponowne zatrudnienie | Brak ponownego losowania, ciągłość tożsamości/historii |
| HR-T03 pół etatu, dwie zmiany i szkolenie | Pełny skill w dostępnych godzinach; odmowa kolizji |
| HR-T04 zmiana aneksu/stawki w środku okresu | Podział naliczeń; wiele odczytów = jedno rozliczenie |
| HR-T05 urlop, choroba i zastępca | Bez podwójnej pensji urlopowej, osobny koszt zastępcy |
| HR-T06 brak/wygaśnięcie uprawnień | Brak nowej nielegalnej pracy; bezpieczna obsługa rozpoczętej |
| HR-T07 quote zwolnienia vs nowy aneks | Stary quote odrzucony, nowa czytelna wycena |
| HR-T08 wypowiedzenie/odsunięcie/odprawa | Różne daty; każdy należny składnik naliczony raz |
| HR-T09 brak środków i częściowa spłata po odejściu | Dług pozostaje, spłata nie tworzy drugiego kosztu |
| HR-T10 job+sweeper+HTTP jednocześnie | Jeden końcowy efekt i jeden outbox na zdarzenie |
| HR-T11 urlop firmy vs pracownika, dormant/draining | Właściwa polityka czasu i zachowanie zobowiązań |
| HR-T12 egzamin/koniec umowy, granica zmiany | Wynik według logical_at i zatwierdzonego priorytetu |
| HR-T13 cudza firma/ukryte cechy/GM | Odmowa serwerowa, brak wycieku, audyt |
| HR-T14 obsada całodobowa i strajk | Brak darmowej pracy24/7 i podwójnego mnożnika |
| HR-T15 stabilność ekonomii90dni | Koszty HR/rekrutacji/odpraw w scenariuszach starter/rozwój/kryzys |
| HR-T16 UI PL/EN/DE i mobile | Pełna ścieżka, SVG, brak okien systemowych |

Tabela to plan testów, nie wyniki PASS. Dotychczasowy symulator nie sprawdza nowych odpraw, grafików ani tabel składek.
