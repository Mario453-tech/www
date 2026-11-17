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


### 2.1. Ukryte cechy i obserwacje HR

Zatwierdzony zakres: kandydat ma trwały profil, którego szczególne cechy są początkowo ukryte. Podatność na niedyspozycję jest oddzielna od morale. Chorobowe nie jest dowodem niezadowolenia, nielojalności lub symulowania choroby.
Przy rekrutacji nie pokazujemy dokładnego przyszłego morale w firmie: pole publiczne ma stan „Nieocenione”. Nie maskujemy liczby wyłącznie CSS — ukryta wartość nie opuszcza serwera.

| Cecha | Efekt domenowy | Etap |
|---|---|---|
| Podatność na niedyspozycję | Modyfikator prawdopodobieństwa krótkiej absencji, bez gwarancji zdarzenia | Pierwszy |
| Dokładność | Mniej błędów/strat; ewentualny koszt czasu wyłącznie wg zatwierdzonej polityki zadania | Pierwszy |
| Współpraca | Lepsza współpraca zespołu i wdrażanie nowych osób | Pierwszy |
| Inicjatywa | Szansa wykrycia problemu lub zaproponowania usprawnienia | Pierwszy |
| Niedbałość | Więcej błędów przy przeciążeniu; wariant tej samej osi co dokładność, nie drugi nakładający się modyfikator | Kolejny |
| Konfliktowość | Napięcia w zespole, bez automatycznego strajku | Kolejny |
| Talent mentorski | Wpływ na wdrożenie podopiecznego przy rzeczywiście zarezerwowanym czasie mentora | Kolejny |
| Stosunek do ryzyka | Ostrożność/brawura wpływa na czas i bezpieczeństwo właściwych zadań | Kolejny |

Lojalność, ambicja i odporność na stres korzystają z istniejących atrybutów. Nie tworzymy ich drugiej wersji. Nowe kody cech rozszerzają istniejący słownik Staff; parametry neutralne i mapowanie skali1–10 wymagają katalogu.
Profil nie musi zawierać szczególnej cechy. Rozkład startowy do symulacji:60% bez dodatkowej cechy,30% z jedną,10% z dwiema. To liczba szczególnych cech, nie brak podstawowych skills/traits. Nowe parametry są kandydatem do kalibracji, nie zmianą aktywnego YAML.
Cechy korzystne, niekorzystne i mieszane; katalog wyklucza sprzeczne kombinacje. Wagi poszczególnych cech, intensywność i zależność od źródła rekrutacji wymagają HR-CFG01. Droższy kanał nie gwarantuje odkrycia cech ani ich braku.

**Ujawnianie:** rozmowa/referencje/test mogą dostarczyć dowodów dla cech zawodowych z jawnej allowlisty. Podatność na niedyspozycję pozostaje nieoceniona w rekrutacji; jest obserwowana dopiero podczas zatrudnienia. Jedna absencja nie ujawnia cechy.
Obserwacja HR ma employee/person ref, observer_company_id, trait_code, evidence_refs, okres obserwacji, poziom pewności i assessment_version. To ocena, nie druga rzeczywista cecha.
Stany publiczne: nieocenione → obserwacja wstępna → ocena poparta obserwacjami. Liczba i rodzaj niezależnych dowodów, czas minimalny i progi pewności są wersjonowaną polityką; nie przyznajemy100% pewności przez sam upływ czasu.
Kolejny GET nie dodaje dowodu. Ta sama nieobecność ma jedno źródłowe zdarzenie, niezależnie od liczby dni/workera. Ocena może być korygowana nowymi obserwacjami; historia pozostaje.
Powrót tej samej osoby do tej samej firmy zachowuje jej profil i historię ocen. Inna firma nie dziedziczy prywatnych obserwacji poprzedniej bez jawnego kontraktu referencji. Stan rozpoznania nie jest globalnym publicznym polem osoby.

**Nieobecności:** także osoba bez szczególnej podatności może zachorować. Prawdopodobieństwo zależy od bazowej polityki, profilu, odpoczynku, zmęczenia i warunków pracy; niskie morale nie zastępuje modelu choroby.
Częstość, długość, cooldown, limit w oknie czasu, podstawa ekspozycji oraz zachowanie podczas urlopu/szkolenia wymagają HR-CFG03. Brak tych parametrów blokuje aktywację generatora, nie tworzy domyślnych chorób.
RNG musi być niezależny od częstości wejść do gry: stabilne sloty logicznego czasu/ekspozycji, utrwalone losowanie i idempotentny klucz person+event_type+slot. Odczyt leniwy, job i sweeper odtwarzają te same sloty oraz tę samą kolejność. Nie losować raz na request.
Limit kontrolowany pod blokadą; jedna osoba nie otrzymuje nakładających się epizodów. Obsługa granic aktywnej absencji i pauzy firmy musi być wspólna z Settlement. Reset zatrudnienia lub zmiana grafiku nie resetuje cooldownu osoby.
Absencja rezerwuje niedostępność przez Staff. Grafik/zastępstwo, produkcja i płace reagują przez istniejące kontrakty; bez dodatkowej kary morale za sam fakt choroby.
Dokładność/inicjatywa nie tworzą drugiego generatora awarii obok Incidents. Staff przekazuje kontekst do właściciela zadania; skutek ma jedno źródło i deduplikację. Współpraca/konfliktowość korzystają z istniejącego modelu morale, bez drugiej równoległej średniej.
Inicjatywa nie naprawia obiektu i nie wydaje pieniędzy bez uprawnionej akcji. Wykrycie problemu tworzy obserwację/powiadomienie, nie darmową naprawę.

**UI i API:** osobne sekcje „Morale”, „Dostępność”, „Obserwacje HR”. Przy kandydacie brak odgadniętych liczb i ikon zdradzających rzeczywistą cechę. DTO, sortowanie, filtry, eksport, WebSocket i logi dostępne graczowi używają wyłącznie ujawnionej oceny. Brak v-html, ukrytych pól payloadu i surowego profilu w analityce.
GM widzi rzeczywiste cechy tylko z osobną capability i audytem. Kandydat ani klient Vue nie wybierają wyniku RNG.
Wyświetlenie obserwacji to komponent z lokalnym SVG, PL/EN/DE. Powiadomienie opisuje fakt („kolejna nieobecność”), nie diagnozę ani pewną etykietę na podstawie pojedynczego zdarzenia.

**Testy do wykonania:** neutralny kandydat nadal może mieć absencję; pojedynczy epizod nie ujawnia cechy;1 vs100 odczytów ten sam wynik; retry i równoległe triggery bez drugiego epizodu; cooldown i cap; przerwy/pauzy; profil po rehire; oceny izolowane między firmami; brak wycieku przez DTO/filtr; jeden modyfikator dokładności; koszty zastępstw i brak obsady w symulacji90dni.
Status: decyzja funkcjonalna zapisana; wagi/progi i generator nie są zaimplementowane ani przetestowane. Powiązania: HR-CFG01/03/05, HR-CONTRACT01, P07-F01/F02/F03, P08-F03, P09, P13.

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


### 4.1. Grafiki i obsada — szczegółowy projekt HR-ROSTER-01

Status: specyfikacja kandydujących zasad gry. Podane normy są propozycją do symulacji, nie odwzorowaniem przepisów prawa pracy. Nie aktywuje nowego balansu ani nie zmienia liczebności obsady zapisanej w architekturze.
Cel: gracz wybiera tryb pracy i priorytety instalacji, Staff układa legalny grafik, a właściciel instalacji rozlicza skutki faktycznie dostępnej obsady. Agencja nie jest wymagana do zwykłego automatycznego układania grafiku.

#### 4.1.1. Trzy odrębne pojęcia

- Umowa: ile czasu osoba może/ma pracować i za jakie wynagrodzenie.
- Grafik: konkretne przedziały pracy, odpoczynku, szkolenia i nieobecności.
- Przydział: zadanie/obiekt i udział dostępnej osoby w danym przedziale.

Pełny etat nie oznacza dostępności24/7. Pół etatu nie obniża umiejętności. Suma alokacji dotyczy czasu, w którym osoba rzeczywiście jest dostępna;100% alokacji na pół etatu nie tworzy pełnego etatu.
Zapisany grafik nie jest dowodem wykonania pracy. Staff wyznacza effective availability z umowy, grafiku, kwalifikacji, absencji, strajku i zajętości.
B2B ma kalendarz uzgodnionej dostępności, a nie etat. Zatrudnienie zewnętrzne nie daje tej samej osobie nieograniczonego czasu.

#### 4.1.2. Zegar i zakres planowania

Czasy UTC, przedziały półotwarte [start,end). Prezentacja może używać lokalnej strefy, lecz ta sama zmiana nie przesuwa się wskutek zmiany języka lub strefy klienta.
Kandydat: siatka edycji15min, podstawowy horyzont14dni, podgląd7dni. Terminy choroby/awarii i Settlement zachowują dokładność silnika, nie są zaokrąglane do siatki.
Tydzień normy umownej: poniedziałek00:00UTC → kolejny poniedziałek. Limity bezpieczeństwa sprawdzamy również w ruchomym oknie168h, żeby przełom tygodnia nie pozwalał obejść odpoczynku.
Publikacja obejmuje konkretny przedział i wersję wejściową. Szablon służy generowaniu przyszłego grafiku, nie oznacza nieskończonej, bezwarunkowej dostępności.
Dla włączonego powtarzania generujemy następny tydzień z wyprzedzeniem7dni; job i sweeper mogą wznowić tę samą operację idempotentnie. Niepowodzenie publikuje blokery, nie fałszywą obsadę.
Jeśli brak opublikowanego grafiku na dany okres, osoba nie daje operacyjnej obsady. Pensja miesięczna nie znika. Jedna informacja o luce na obiekt/rolę/okres, nie powiadomienie przy każdym GET.
Pauza firmy jest obsługiwana przez istniejący company_time_pauses. Podczas pauzy brak wykonanej pracy; po powrocie zachowujemy historię i przeliczamy przyszły grafik. Nie przesuwamy istniejących przydziałów w niekontrolowane nakładanie. URLop pracownika to odrębna nieobecność.

#### 4.1.3. Tryby pracy instalacji

| Tryb | Domyślny szablon kandydujący | Zastosowanie |
|---|---|---|
| Jedna zmiana | 06:00–14:00 | Praca dzienna |
| Dwie zmiany | 06:00–14:00 i14:00–22:00 | Wydłużona praca |
| Całodobowy | 06:00–14:00,14:00–22:00,22:00–06:00 | Ciągła obsługa |
| Zadaniowy | Przedziały właściwego zadania | Kierowca, naprawa, inspekcja |

Dni pracy osobno: np. pon.–pt. lub7dni. Praca na3zmiany przez5dni nie jest24/7.
Użytkownik może zmienić godziny w zakresie katalogu. Zmiana trybu nie przełącza od razu urządzenia: najpierw plan, koszt, sprawdzenie procesu i zatwierdzenie przyszłej daty.
Każdy zasób ma operating calendar oraz personnel requirements. Instalacja może działać poza grafikiem własnej załogi tylko wtedy, gdy jej jawny model automatyzacji/usługi zewnętrznej na to pozwala.
Przerwa między zmianami daje rzeczywistą lukę. Przedziały06–14 i14–22 stykają się bez luki i nie liczą minuty14:00 dwukrotnie.

#### 4.1.4. Kandydat norm czasu

| Parametr gry | Wartość startowa do kalibracji |
|---|---:|
| Zwykła zmiana | 8h |
| Maksimum ciągłego bloku pracy z nadgodzinami | 12h |
| Nominał pełnego etatu | 40h/tydzień |
| Limit całej pracy w ruchomych168h | 48h/osobę |
| Minimalny odpoczynek pomiędzy blokami | 12h |
| Minimalny nieprzerwany odpoczynek w każdym ruchomym oknie168h | 36h |
| Noc | 22:00–06:00UTC |
| Dodatek za godzinę ponad wymiar/nominał w modelu gry | +50% podstawy godzinowej |
| Dodatek nocny | +20% podstawy godzinowej |

Przylegające zmiany tej samej osoby łączymy w jeden blok przed kontrolą limitu12h. Nie wolno nazwać16h pracy dwiema zmianami i ominąć odpoczynku.
Przydziały do różnych działów, mentorowanie, zadania i płatne szkolenia sumują czas tej samej osoby. Nie sumować dwukrotnie nakładających się reprezentacji tego samego zadania; odrębne nakładające się zadania wymagające pełnej uwagi są kolizją.
Niepełny etat: nominał tygodnia40h×wymiar, np.20h dla1/2. Godziny ponad umowny nominał wymagają jawnego limitu extra_hours i zgody na automatyczne nadgodziny. Domyślnie extra_hours=0, nie automatyczna praca do48h.
Proponowane uproszczenie: +50% dla godzin ponad umowny wymiar, również niepełnego etatu. To celowa reguła gry, nie utożsamienie wszystkich godzin ponadwymiarowych z ustawowymi nadgodzinami.
Godzina spełniająca dwa warunki nadgodzin jest liczona raz; noc i nadgodziny mogą dać łącznie+70% tej samej podstawy. Nie mnożymy1,5×1,2.
W pierwszej wersji twardych limitów nie da się obejść ręcznym grafikiem, automatyzacją HR ani Agencją. Nie wprowadzamy oddzielnego trybu łamania norm.
Walidacja odpoczynku uwzględnia co najmniej168h przed i po zmienianym okresie; nieznane przyszłe dane nie mogą posłużyć do deklaracji, że opublikowano grafik zgodny z limitem. Końcowy odcinek planu pozostawia wymagany odpoczynek.

#### 4.1.5. Przykłady etatu i koszt dodatków

1etat:5×8h. Pół etatu:5×4h albo2×8h+4h; wybór zależy od stanowiska i realnej potrzeby zmianowej. Krótszy dyżur nie daje obsady przez resztę dnia.
Cztery osoby po40h dają160h; do jednego stanowiska24/7 potrzeba168h.4,2etatu to matematyczne minimum godzin, nie dowód ułożenia legalnego grafiku. Pięć etatów daje200h, czyli32h rezerwy przed absencjami, szkoleniem i ograniczeniami odpoczynku.
Dla2 stanowisk24/7 zapotrzebowanie336h. Nie wolno rozdzielić jednego pracownika pełniącego dwa wyłączne stanowiska jako dwóch osób.
Dla wspólnego technika trzech odwiertów używamy ograniczenia domenowego „max3”, a nie zaokrąglonego33,33%, które dawałoby99,99%. W danych pojemność całkowita/licznik slotów albo dokładny ułamek. Ponad3 obsługiwane odwierty odrzucone.

Podstawa dodatków: actual_monthly_minor / nominal_contract_hours_in_month. Nominał wyznacza wersjonowany kalendarz umowy, nie opublikowany grafik ani godziny po odjęciu choroby. Dla bazowego kalendarza gry pon.–pt.8h dziennie×etat; święta regionalne nie występują bez jawnego katalogu.
Przykład miesiąca20dni roboczych:15000PLN/160h=93,75PLN/h.8 zwykłych godzin nocnych: dodatek150PLN;4 dodatkowe godziny nocne: podstawa za pracę375PLN +50%187,50PLN +20%75PLN =637,50PLN przed narzutami.
Pensja miesięczna pokrywa nominalne godziny; dodatkowa praca otrzymuje podstawę plus dodatek, nie sam dodatek. Zmiana położenia nominalnych godzin na noc daje tylko nocny dodatek. Zlecenie godzinowe płaci podstawę raz za wykonane godziny i właściwe umowne dodatki.
Dodatki idą przez HR-PAY-01 jako osobne składniki z resztami, nie drugi silnik płac. Sposób naliczania pierwszego/ostatniego niepełnego tygodnia i wpływ absencji na próg nadgodzin wymagają HR-ROSTER-CFG02 przed aktywacją kosztów.

#### 4.1.6. Mapa obsady — zachowanie istniejącej domeny

| Obszar | Kanon istniejący | Wymaganie dla grafiku |
|---|---|---|
| Odwiert | 1operator + technik współdzielony maks.3odwierty; brak=paused_staff | Operator i pojemność technika dostępne w tym samym przedziale produkcji |
| Hub mały/średni/duży | Obsada1/2/4; brak:70% przepustowości i ryzyko×2 | Zachować minimum i ten tryb degradacji; krzywa częściowej obsady wymaga doprecyzowania, nie wymyślać proporcji |
| Rurociąg | Inżynier; brak zwiększa zużycie/ryzyko×2; specjalista logistyki opcjonalny | Nie zatrzymywać całego przesyłu tylko z powodu końca zmiany; limity współdzielenia odcinków wymagają katalogu |
| Cysterna | Konkretny kierowca i skończony pojazd | Wyłączna rezerwacja na czas zadania, bez równoległej trasy/szkolenia |
| Rafineria | Minimalna załoga4+1/10+3/24+8; zewnętrzna obsługa/automatyzacja w OPEX | Rozdzielić własną załogę specjalistyczną od obsługi zewnętrznej; nie mnożyć istniejącej tabeli przez liczbę zmian bez decyzji |
| Naprawa/inspekcja | Uprawnienia i rezerwacja TTS | Zadanie musi zmieścić się w dostępności albo mieć jawny bezpieczny handover |
| Centrala i zarząd | Mandat/uprawnienie dyrektora osobno od zadania operacyjnego | Koniec zmiany nie kasuje mandatu ani awaryjnych uprawnień gracza |

Niezdefiniowane reguły dzielenia operatora między odległe obiekty nie oznaczają dowolnego współdzielenia. Jeden operator odwiertu obsługuje jedną pozycję w danym czasie. Wyjątki wymagają jawnej automatyzacji w domenie.
Przemieszczenie osoby między obiektami potrzebuje wersjonowanego transit_time. Brak polityki odległości blokuje automatyczne przenoszenie między lokalizacjami, nie dodaje teleportacji.
Suma czasu+przemieszczenia+przekazania mieści się w dostępności; handover personelu nie jest drugim wykonaniem tego samego zadania.

#### 4.1.7. Algorytm układania grafiku

Wejście: wersje umów, kompetencji, kalendarzy operacyjnych, potrzeb obsady, absencji, szkoleń, zajętości i limitów budżetu.
1. Zbuduj demand slots role+asset/group+interval z krytycznością, kwalifikacjami, liczbą stanowisk i allow_share.
2. Zachowaj ręcznie przypięte przyszłe pozycje, jeśli nadal legalne. Nieprawidłową oznacz konfliktem, nie wykonuj jej tylko dlatego, że przypięta.
3. Odfiltruj osoby bez umowy/kwalifikacji/dostępności lub z kolizją. Sprawdź całkowitą pracę osoby, nie osobno każdego działu.
4. Obsadzaj najpierw stanowiska krytyczne i te, do których jest najmniej uprawnionych osób.
5. Spośród legalnych rozwiązań preferuj już przypisany zespół, mniejszy koszt dodatkowy, mniej zmian grafiku i równy rozkład nocy. Remis rozstrzyga stabilne person_id.
6. Jeśli brakuje czasu nominalnego, proponuj nadgodziny tylko w mandacie i limicie. Nie uruchamiaj automatycznego płatnego zastępstwa, zanim nie istnieje jego zatwierdzona polityka.
7. Zwróć plan, koszt nominalny/dodatkowy, rezerwę godzin i luki z przyczynami. Nie udawaj optymalnego rozwiązania, jeśli użyta heurystyka nie znalazła obsady; status „nie znaleziono planu” różni się od dowodu niewykonalności.
8. Gracz zatwierdza wersję; publikacja rewaliduje dane i koszty pod blokadą. Konflikt zwraca PLAN_STALE zamiast nadpisania zmian.

Tryby: rekomendacje, autopublikacja w granicach mandatu, ręczny grafik. Pierwszy domyślnie pokazuje plan do zatwierdzenia.
Zwykły autoplan nie zatrudnia/zwalnia ludzi i nie podnosi pensji. Może proponować te kroki; szersza delegacja należy do osobnego mandatu, ewentualnie Agencji.
Koszt porównujemy z limitem nadgodzin; nie rezerwujemy drugi raz podstawowych pensji już wynikających z umów.

#### 4.1.8. Życie planu, konflikty i ręczne zmiany

Stany wersji: draft → validated → published → superseded/closed. Historia niezmienna. Osobno derived coverage: covered/partial/uncovered/conflict.
Publikacja stosuje future effective_from; przeszłego grafiku nie edytujemy. Rozliczenie poprzedniego stanu do granicy następuje przed nowym wpływem.
Jedna aktywna wersja dla firmy/zakresu/czasu. Równoległe publikacje CAS dają jedną wygraną. Szerokie zakresy nie mogą przykrywać części planu bez kontroli przecięć.
Nowa choroba, strajk, koniec umowy i wygaśnięcie uprawnienia natychmiast zmieniają effective availability, nawet jeśli ekran pokazuje wcześniejszy plan.
Zadania nieprzerywalne: start wymaga pokrycia przewidywanego czasu z rezerwą albo zdefiniowanej zmiany wykonawcy. Nie odcinać kierowcy na środku trasy arbitralnym końcem grafiku.
Nieprzewidziane przekroczenie czasu trasy/naprawy wymaga polityki safe-stop/handover w Logistics/Incidents. Do jej zdefiniowania nie dopuszczać wariantu bez legalnej ścieżki końca; automatyzacja nie obiecuje „kontynuacji bez limitu”.
Krytyczne luki mają trwały incident_ref do powiadomienia, deduplikację i zamknięcie po odzyskaniu obsady. Nie karzemy kilkakrotnie za tę samą godzinę absencji.

#### 4.1.9. Settlement i współbieżność

Przedział rozliczenia dzielimy na granicach zmian, przydziałów, nieobecności, kwalifikacji, kontraktów, zadań i pauz. Obliczenia fizyczne w fazie1 korzystają z obsady właściwej dla podprzedziału, nie wyłącznie obecnego statusu osoby.
Przykład: brak operatora10:00–11:00 w odczycie09:00–12:00 daje produkcję tylko09–10 i11–12; późniejsza faza HR nie odkręca całego okresu.
Właściciel obiektu łączy coverage z istniejącymi mnożnikami strajku i budżetu. Ten sam powód utraty dostępności nie tworzy dodatkowej kopii kary. Strajk mający szczególny skutek domenowy nadal stosuje ten kanon.
Job granicy zmiany jest przyspieszeniem, nie jedynym mechanizmem. Lazy settlement i sweeper5min odtwarzają identyczne granice, także po długim offline. Brak workerów nie daje darmowej produkcji.
Opublikowanie nowego przyszłego grafiku nie nadpisuje historycznej wersji użytej przez rozliczenie; źródłowe wersje i logical_at są utrwalone.
LockPlan: standard idempotency/company/settlement → Staff14 → dalsze obiekty/konta → append140. Dokładną kolejność nowych tabel dopisujemy wspólnie do kanonu w HR-CONTRACT01 przed implementacją. Nie lockować obiektu, a dopiero później pracownika.
Wynik publikacji, rezerwacje, zdarzenie i outbox atomowo; retry bez drugiej wersji lub podwójnej opłaty.

#### 4.1.10. Dane, kontrakty i ekran

Docelowe encje Staff: roster_templates, roster_versions, roster_entries, staffing_demands, coverage_findings. Nie zastępują employment_contracts, employee_assignments, employee_availability ani training_enrollments.
roster_entry: osoba/okres zatrudnienia, wersja, start/endUTC, typ pracy, rola, asset/group_ref, alokacja/sloty, status przypięcia i źródłowe wersje. Dla braku obsady demand bez person_id, nie sztuczny pracownik.
Unikalność intencji i aktywnej wersji; indeksy firma+przedział i osoba+przedział. MySQL nie rozwiązuje nakładania czasu zwykłym UNIQUE: blokada trwałego rodzica osoby oraz zapytanie o przecięcie [start,end) w transakcji. Plan wielu osób blokuje ID w stałej kolejności.
Kontrakty: BuildRoster, ValidateRoster, QuoteRoster, PublishRoster, PinRosterEntry, ResolveAvailability, GetCoverageForecast. Aktor+firma, Idempotency-Key i expected_state_version; DTO nie ujawnia ukrytych traits.
Ekran Grafik: tydzień/dzień, filtr dział/obiekt, osoby i godziny, luki, koszt dodatków, przyciski „Ułóż grafik”, „Sprawdź”, „Opublikuj”. Przeciąganie opcjonalne; każda akcja dostępna także formularzem/klawiaturą.
Mobile: agenda zmian i formularz, bez wymuszania szerokiej siatki. Status opisany tekstem+SVG; wspólne animacje §12.1, PL/EN/DE.
Podsumowanie: wymagane godziny, pokryte godziny, luki krytyczne, nadgodziny, rezerwa i prognoza kosztu. Nie utożsamiać pokrytych godzin z liczbą zatrudnionych osób.

#### 4.1.11. Starter i bramki aktywacji

Starter musi dać pierwszy legalny kurs i działający odwiert na warunkach istniejącej domeny. Obecny pakiet wymienia kierowcę, ale nie rozstrzyga pełnej zmianowej obsady operatora/technika. To luka do decyzji, nie powód dopisania darmowej załogi w kodzie.
Proponowany kierunek do osobnej kalibracji: jawny ograniczony pakiet obsługi szkoleniowej albo startowy grafik z zatrudnieniem. Oba muszą pokazać datę końca i przyszły koszt; żadnej niewidocznej nieograniczonej pracy NPC.
Rafineria: najpierw rozstrzygnąć, które wymagania tabeli są jednoczesnymi stanowiskami zmianowymi, a które przypisanym zespołem specjalistycznym. Płacenie za własną i już zawartą w OPEX tę samą obsługę jest błędem.
| Bramka | Co nadal wymaga decyzji/testu |
|---|---|
| HR-ROSTER-CFG01 | Ostateczne normy8/12/40/48h, odpoczynek12/36h, horyzont i siatka; symulacja legalnego planu |
| HR-ROSTER-CFG02 | Częściowy tydzień/miesiąc, absencje a nadgodziny, nominalny kalendarz i dodatki |
| HR-ROSTER-CFG03 | Rafinerie/starter, częściowa obsada hubów, liczba odcinków na inżyniera, transit/handover |
| HR-ROSTER-CFG04 | Opóźnione trasy i zadania nieprzerywalne: safe-stop, zastępstwo i koszty |
| HR-ROSTER-CONTRACT01 | DDL, pełne API, snapshot obsady, globalny lock order i karty delta |
| HR-ROSTER-ECON01 | Test90dni: ciągła praca, jedno/dwie zmiany, kryzys, absencje i marża startera |

Kolejny podtemat: chorobowe, urlopy i zastępstwa. Ten rozdział definiuje ich punkt integracji, nie losuje ani nie ustala jeszcze kompletnych zasad nieobecności.

#### 4.1.12. Testy do implementacji

R01:4 osoby×40h nie pokrywają168h. R02:5×40h to200h podaży, lecz planner nadal sprawdza odpoczynek.
R03:pół etatu5×4h i2×8h+4h mają20h, normalny skill, brak drugiego×0,5.
R04:przylegające8h+8h tworzą16h i są odrzucone. R05:przełom tygodnia nie omija48h/168h ani odpoczynku.
R06:zmiana22–06 poprawnie przecina dobę i miesiąc. R07:styki14:00 bez luki/podwójnego naliczenia.
R08:technik obsługuje maks.3odwierty; czwarte zadanie nie przechodzi równolegle.
R09:hub bez obsady zachowuje70%/×2, odwiert paused_staff; brak globalnej jednej kary.
R10:choroba po publikacji unieważnia obsadę tylko od logical_at. R11:kurs i naprawa nie zużywają tej samej dostępności.
R12:publikacje równoległe i ręcznie przypięte wpisy; brak nadpisania/cyklu automatycznych zmian.
R13:1odczyt vs100 i brak workera dają ten sam wolumen, czas pracy i koszt.
R14:delegacja Agencji nie omija norm; odczyt cudzej firmy/ukrytych cech odrzucony.
R15:niezmienna historia po aneksie i zmianie grafiku; korekty tylko jawne.
R16:pauza firmy, długi offline, brak przyszłego planu, restart i częściowo wykonane zadania.
R17:noc+nadgodziny sumują dodatki, bez podwójnej podstawy. R18:prognoza niczego nie księguje.
R19:pełna obsługa mobile/klawiaturą bez drag; PL/EN/DE. R20:starter i rafinerie po zatwierdzeniu mapy obsady.
To scenariusze odbioru, nie wyniki testów działającego silnika.


### 4.2. Urlopy — polityki w pełni konfigurowalne z panelu

Status: zatwierdzone wymaganie funkcjonalne. Właściciel Staff. Panel Filament: Kadry → Polityki urlopowe. Wszystkie reguły biznesowe i wartości urlopów są edytowalne; nie oznacza możliwości wyłączenia integralności księgowania, audytu lub autoryzacji. Brak implementacji.

| Obszar konfiguracji | Parametry |
|---|---|
| Rodzaje urlopów | Kod, nazwa PL/EN/DE, aktywność, kwalifikowalność, płatność, oddzielna pula; np. wypoczynkowy, bezpłatny, okolicznościowy |
| Pula i naliczanie | Godziny bazowe, zależność od etatu/stażu/umowy/regionu, okres, moment nabycia, tempo, precyzja i zaokrąglenia |
| Dostępność | Okres oczekiwania, urlop zaliczkowy, limit zaliczki, reguła rozliczenia zaliczki przy odejściu |
| Wnioski | Wyprzedzenie, minimalna/maksymalna długość, liczba otwartych wniosków, dopuszczalne terminy |
| Zatwierdzanie | Ręczne/automatyczne, wymagane pokrycie obsady, limit równoczesnych nieobecności i koszt zastępstwa |
| Płatność | Procent podstawy, metoda wyceny, profil składników i obciążeń firmy; według HR-PAY-01 |
| Przenoszenie | Limit przeniesienia, termin ważności, kolejność zużycia roczników, ostrzeżenia o wygaśnięciu |
| Zmiana/anulowanie | Uprawnione role, terminy, wymagana zgoda, zwrot rezerwacji i skutki finansowe |
| Kolizje | Choroba, inne urlopy, szkolenie, wypowiedzenie, pauza firmy; jawna macierz priorytetów |
| Ekwiwalent | Kiedy należny, podstawa godzinowa, limity, termin płatności i profil obciążeń |
| Morale | Skutki odmowy/przesunięcia/odwołania oraz braku wypoczynku; zdarzenia do istniejącego modelu, bez podwójnych kar |
| Powiadomienia | Progi, terminy, odbiorcy i szablony PL/EN/DE, ikony z lokalnej allowlisty SVG |

Konfiguracja używa walidowanych pól, słowników i zatwierdzonych strategii obliczeń, nie dowolnego PHP/JavaScript lub wykonywalnej formuły administratora. Nowy algorytm poza dostępnymi strategiami wymaga wydania kodu; jego parametry pozostają w panelu.

**Kandydat domyślny do kalibracji:** urlop wypoczynkowy160h/rok pełnego etatu (40/80/120/160h dla1/4,1/2,3/4,1); proporcjonalne nabywanie,100% podstawy; niewykorzystane godziny przenoszone bez automatycznego kasowania. To wartości startowe, nie stałe w kodzie ani deklaracja rzeczywistych przepisów.
Zmiana etatu wpływa na przyszłe nabywanie, bez cichego przeliczenia historycznej puli. Urlop zużywa zaplanowane godziny pracy, nie24h każdej doby.
Wniosek: submitted → approved/rejected/alternative_proposed → used; cancelled jako osobne zakończenie zgodne z polityką. Zmiana terminu alternatywnego wymaga akceptacji i ponownego sprawdzenia dostępności.
Zatwierdzenie rezerwuje godziny i utrwala objęte zmiany. Późniejsza edycja grafiku nie zwiększa lub zmniejsza samowolnie zużycia zaakceptowanego urlopu.
Pula jest księgą zdarzeń: nabycie, przyznanie, rezerwacja, zwolnienie, wykorzystanie, przeniesienie, wygaśnięcie, korekta. Dostępne=nabyte+przyznane+korekty−wykorzystane−wygasłe−aktywne rezerwacje, z osobnym jawnym limitem zaliczki. Przeniesienie rocznika nie przyznaje godzin drugi raz.
Anulowanie zwalnia rezerwację raz; wykorzystanie zamienia ją w zużycie, nie odejmuje drugi raz dostępności. Na styku choroby i urlopu zwracamy wyłącznie objęte kolizją godziny według wybranej polityki.
Płatny urlop zastępuje należny składnik czasu nieobecności, nie dodaje drugiej pensji. Zastępstwo ma własny koszt. Ekwiwalent końcowy przechodzi przez Finance i idempotencję świadczenia.

**Wersjonowanie i przejście:** draft → validated → approved → scheduled → active, zgodnie z istniejącą bramką balansu. Polityka ma version/hash, zakres i effective_from. Wersja aktywna jest niezmienna; edycja tworzy kandydata.
Panel pozwala wybrać nowe zatrudnienia albo objęcie wskazanych obecnych pracowników. Dla obecnych wymagany preview: zmiana przyszłego nabywania, nabytych praw, rezerwacji, płatności, ekwiwalentu i konfliktów.
Domyślnie historyczne naliczenia i zatwierdzone wnioski zachowują starą politykę. Ich zmiana wymaga jawnego planu przejścia z listą skutków, komunikatem i rewalidacją wersji przed wykonaniem. Nie działa wstecz przez zwykły zapis formularza.
Reguła doboru polityki ma jednoznaczną kolejność zakresów; dwie równie szczegółowe nakładające się polityki blokują publikację. Brak pasującej polityki blokuje nowy wniosek zamiast zgadywania uprawnienia; wcześniej należne świadczenia nadal rozliczane.

**Korekty indywidualne:** uprawniony administrator może przyznać/skorygować pulę, zatwierdzić wniosek, zmienić termin lub anulować zgodnie z prawami administracyjnymi. Powstaje odrębne zdarzenie z before/after, aktorem, czasem, wersją i powiązaniem z operacją. Nie nadpisujemy salda ani historii.
Główny administrator NIE musi podawać komentarza ani uzasadnienia tekstowego. Rodzaj operacji wynika z akcji; audyt pozostaje automatyczny. Polityka komentarzy pozostałych ról zgodnie z uprawnieniami panelu.
Masowa korekta wymaga podglądu i identyfikowalnych wyników per osoba; ponowienie nie powtarza przyznania. Nie wolno odebrać już zarezerwowanych godzin bez jawnego rozwiązania konfliktu.
Uprawnienia osobno: odczyt polityk, edycja kandydata, aktywacja, korekta puli, decyzja o wniosku, masowa operacja. Każda akcja autoryzowana na serwerze, nie tylko ukryciem przycisku.

**Dane/kontrakty do kart:** leave_policy_versions, leave_requests, leave_entitlements/leave_movements, leave_reservations, leave_transition_plans; jeden właściciel Staff. Kwoty/godziny z dokładną precyzją i resztami, UTC, wersje stanu, FK i trwałe klucze efektów.
Publikacja konfiguracji korzysta z istniejącej kolejności blokad konfiguracyjnych. Migracja indywidualnych praw jest osobną operacją gospodarczą; nie łączyć obu kolejności w jednej transakcji. Pełne DDL/OpenAPI i wpis nowych tabel do LockPlan wymagają HR-CONTRACT01.
Widok pracownika: nabyte, zarezerwowane, wykorzystane i dostępne godziny, roczniki/terminy ważności, wnioski i historia. Widok GM: edytor, porównanie wersji, preview skutków, korekty i audyt. Własne modale/SVG, PL/EN/DE, mobile i reduced-motion.

**Odbiór do implementacji:** proporcja etatów; aneks w okresie; reszty nabywania; dwa równoległe wnioski bez nadrezerwacji; retry anulowania; częściowa kolizja choroby; zmiana grafiku po zgodzie; przełom roku bez podwójnego grantu; koniec umowy/ekwiwalent raz; wygasanie bez naruszenia zarezerwowanych praw; masowa korekta retry; odrzucenie obcej firmy; główny administrator bez komentarza; stara wersja zachowana; preview nie księguje.
Następny krok: domknąć chorobowe i zastępstwa, w tym ich macierz kolizji z urlopem. Zasady liczbowe chorobowego omawiane w rozmowie pozostają kandydatem i nie stają się aktywne przez tę zmianę.

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


## 12. Ekran Kadr gracza — koncepcja pod Vue 3

Umowy, wynagrodzenia i odprawy z [HR-PAY-01](OILEMPIRE_KADRY_UMOWY_PLACE_ODPRAWY.md) są częścią Kadr, nie osobnym modułem ekonomicznym.
Koncepcja interfejsu gracza pod Vue 3; nie zastępuje panelu administratora Filament i nie deklaruje migracji całego frontendu.

Nawigacja Kadr: Przegląd, Pracownicy, Rekrutacja, Umowy i płace, Grafik, Szkolenia, Historia.
Domyślny ekran Pracownicy: wyszukiwarka, filtr działu/statusu/umowy, lista osób oraz panel szczegółów wybranej osoby. Podsumowanie pokazuje liczbę zatrudnionych, sumę etatów, prognozowany koszt miesiąca i luki obsady. Każda wartość ma jednostkę; koszt prognozowany nie jest zaksięgowaną należnością.
Wiersz osoby: imię i nazwisko, stanowisko, forma/wymiar umowy, morale, najważniejszy skill, stan dostępności i koszt firmy. Rozdzielić status umowy od statusu bieżącej pracy.
Panel osoby: Profil, Umowa, Rozwój, Historia. W Umowie widoczne brutto/kwota faktury, narzuty, łączny koszt, okres, etat, najbliższa zmiana i działania aneks/wycena zakończenia.
Odprawa wyświetlana jako prognoza z datą i wersją; nie jako pieniądze już należne. Zakończenie współpracy otwiera własny dostępny modal z ikoną SVG, rozbiciem kosztów, terminami i przyciskiem potwierdzającym aktualny quote.
Rekrutacja: źródło → zasięg → warunki → wycena kampanii → lista trwałych kandydatów. Kandydat nieoceniony pokazuje przedział lub brak oceny.
Grafik: oś czasu i obsada; mobilnie agenda dni. Szkolenie, odpoczynek i nieobecność czytelne tekstowo, nie tylko kolorem.

Kierunek wizualny: kompaktowy grafitowy interfejs, złoty akcent akcji/wyboru, jasna typografia, cienkie separatory, SVG o spójnym obrysie. Zielony dostępność, bursztyn wymagające uwagi, czerwony krytyczne braki — zawsze z etykietą.
Na komputerze lista + szczegóły obok; na telefonie lista kart i osobny ekran szczegółów z powrotem. Brak poziomego scrolla całej strony przy320px; cele dotykowe44px, klawiatura/focus i reduced-motion.
Nazwy komponentów proponowane: StaffPage, StaffSummary, StaffFilters, EmployeeList, EmployeeDetails, ContractBreakdown, TerminationQuoteDialog, RecruitmentWizard, StaffRoster.
Stan API/autoryzacja i wszystkie obliczenia finansowe pozostają w Laravel. Vue prezentuje odpowiedzi i obsługuje formularze; nie wylicza autorytatywnej odprawy. Kwoty API jako string w najmniejszej jednostce, formatowane z locale.
CSS modułu w osobnym pliku; SVG w lokalnych plikach; teksty PL/EN/DE. Obsłużyć loading/empty/error/403/409/offline i ponowne pobranie wygasłego quote. Nie używać v-html do treści użytkownika.
Wygenerowana makieta pozostaje propozycją wyglądu. Osobny lokalny prototyp Vue opisano w §12.2; nie jest wdrożeniem modułu gry.


### 12.1. Animacje i przejścia — plan do implementacji

Uzgodnione rozszerzenie istniejącej wizualizacji, bez nowego dokumentu. Status: zaplanowane; nie oznacza wykonania animacji w prototypie.
Ruch ma pomagać śledzić zmianę stanu, bez ciągłego migania, pulsowania i dekoracyjnych pętli.

| Element | Zachowanie | Docelowy czas |
|---|---|---|
| Zakładki i szczegóły osoby | Krótkie przenikanie treści i przesunięcie do8px; brak nakładania aktywnych formularzy | 160–220ms |
| Filtrowanie i kolejność listy | Vue TransitionGroup z trwałym employee_id; płynne wejście, wyjście i przesunięcie wierszy | 180–240ms |
| Morale | Zmiana długości paska od poprzedniej wartości do nowej, bez startu od zera przy każdym odczycie | 250–350ms |
| Koszt i podsumowania | Delikatne wyróżnienie zmiany; licznik może interpolować wyłącznie warstwę wizualną | 250–350ms |
| Szczegóły na telefonie | Wysunięcie panelu, przy powrocie przywrócenie miejsca na liście i fokusu | 200–260ms |
| Modal i powiadomienie | Opacity oraz krótkie przesunięcie; wspólne zachowanie w grze i panelu administracyjnym | 160–220ms |

Vue3: Transition/TransitionGroup; style i tokeny czasu w osobnym CSS modułu. Preferować transform/opacity; brak animowania całego layoutu strony. Ikony pozostają lokalnymi SVG.
Nowa odpowiedź API podczas animacji zastępuje poprzedni cel; nie odtwarzamy kolejki nieaktualnych wartości. Zmiana sortowania/filtra nie zmienia wybranego employee_id na innego pracownika.
Kwoty dokładne z serwera są natychmiast dostępne dla formularza, potwierdzenia, kopiowania i technologii asystujących. Interpolowany licznik jest aria-hidden i nie jest źródłem wyceny ani stanu finansowego. Nie konwertować kwot BIGINT do niedokładnego Number w logice finansowej.
Focus trap i aria-modal obowiązują od otwarcia; zamknięcie i powrót fokusu nie zależą od zakończenia animacji. Ukryte/wychodzące widoki nie mogą przechwytywać klawiatury. Toast nie odczytuje każdej klatki przez aria-live.
prefers-reduced-motion: wyłączyć przesunięcia, liczniki i animowane paski; pokazywać od razu końcowy stan. Brak opóźnienia operacji lub potwierdzenia ze względu na efekt wizualny.
Odbiór: szybkie przełączanie zakładek, filtrowanie podczas wyboru osoby, kolejne odpowiedzi API, Escape i focus, reduced-motion, brak overflow320px oraz pełna ścieżka mobilna. To scenariusze do wykonania po implementacji, nie obecny wynik PASS.

### 12.2. Stan prototypu

Powstał lokalny interaktywny prototyp Vue3: lista i filtr, szczegóły, formularz pensji, symulacja wypowiedzenia i szkic kampanii. Dane demonstracyjne w pamięci, interfejs PL, bez API i trwałego zapisu. Istnieją jedynie podstawowe efekty przycisków i wejścia modalu; zakres12.1 nadal czeka na realizację.
Nie jest to ukończony moduł gry. Docelowe PL/EN/DE, autoryzacja, serwerowa wycena, zapis oraz testy integracyjne pozostają w zakresie implementacji.


## 13. Opcjonalna Agencja pracy — przyszły moduł

[Specyfikacja Agencji pracy](OILEMPIRE_AGENCJA_PRACY_SPECYFIKACJA.md) opisuje kompletowanie załogi, utrzymanie obsady i obsługę kadrową. Podstawowe Kadry działają bez tej usługi. Agencja korzysta z tych samych publicznych akcji Staff, kandydatów, ocen HR i ograniczeń; nie ma dostępu do ukrytego profilu. Zarządza wyłącznie w granicach mandatu i limitów gracza. Rezygnacja zachowuje pracowników/umowy i przekazuje procesy do ręcznej obsługi. Nie należy utożsamiać tego modułu z kanałem rekrutacji „agencja rekrutacyjna”. Pierwszy zakres: NPC, bez agencji graczy. Ceny i wdrożenie pozostają przyszłe.
