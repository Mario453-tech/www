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


## 14. Chorobowe i zastępstwa — zapis ustaleń

Zatwierdzona korekta właściciela: pracownik tymczasowy kosztuje **250% standardowego pełnego kosztu godzinowego stanowiska**, czyli mnożnik2,5, a nie bazę plus250%. Mnożnik edytowalny w panelu. Nie ma gwarancji znalezienia pracownika ani natychmiastowego rozpoczęcia pracy. Ta reguła zastępuje wcześniejszą propozycję150%.
Baza godzinowa pochodzi z polityki stanowiska/regionu i nominalnego kalendarza, obejmuje normalne obciążenia firmy. Przykład: pełny koszt100PLN/h → usługa250PLN/h; nie doliczamy drugi raz standardowych składek. Dojazd/minimum zamówienia/anulowanie wyłącznie jako osobne ujawnione pozycje quote.
Dostępność oferty, czas przyjazdu, kwalifikacje i długość zastępstwa muszą być znane przed potwierdzeniem. Cena zablokowana w ważnym quote, rezerwacja konkretnej dostępności atomowo; brak ofert daje unavailable, nie sztucznego pracownika. Do rozpoczęcia usługi normalne skutki braku obsady.

Kandydat generatora chorobowego:10% bazowo / do20% przy podatności na30 aktywnych dni przed wpływem warunków; długość1–3dni; cooldown7dni od końca; max2 rozpoczęcia krótkiego epizodu w ruchomych30dniach. Parametry wymagają kalibracji. Wypadki odrębne.
Ekspozycja i cooldown według wspólnego zegara aktywnego czasu firmy; przed produkcją wymagane dokładne sloty i mapa modyfikatorów. Nie losować przy requestach. Stały wynik epizodu, jedno source_event_id i idempotencja na granicach.
Morale oddzielone od podatności; przeciążenie/odpoczynek/warunki są wejściami ryzyka. Pojedyncza choroba nie dowodzi cechy ani symulowania.
Proces: zapis epizodu → niedostępność Staff → luki grafiku → propozycja zastępstwa → domenowe skutki obsady → powrót do najbliższej własnej zmiany. Powrót nie dodaje pracy w dniu wolnym.
Długość epizodu w pierwszej wersji losowana raz; brak codziennego niejawnego przedłużania.
Kandydat płatności employment:80% podstawy w okresie choroby zamiast normalnej pensji, nie dodatkowo. Zlecenie wykonane godziny; B2B/management według jawnej polityki umowy. Profil obciążeń świadczenia wymaga konfiguracji, nie automatycznego zastosowania narzutu do wszystkiego.
Pensja15000PLN, miesiąc30dni,3dni choroby:13500+1200=14700PLN brutto; zastępca osobno.

Kolejność zastępstw: wolny uprawniony pracownik → legalna zamiana zmian → zatwierdzone nadgodziny → oferta tymczasowa → ograniczenie pracy właściwe dla instalacji. Przesunięcie nie może ukryć nowej krytycznej luki.
Automatyczne płatne zastępstwo wymaga mandatu, limitu pojedynczej operacji i miesiąca oraz środków; wszystkie sprawdzane atomowo. Własny pracownik w nominalnym czasie nie otrzymuje drugiej pensji.
Wcześniejszy powrót chorego nie kasuje automatycznie opłaconego zastępstwa. UI pokazuje warunki anulowania. Agencja zarządzająca nie jest wymagana do kupienia pojedynczej usługi zastępstwa.
Kolizje: choroba/urlop wg §4.2; choroba/kurs wg §17 poniżej; koniec zatrudnienia zachowuje należności; kierowca w tranzycie wymaga safe-stop/handover Logistics. Generator nie może być aktywowany bez legalnej ścieżki obsługi tego przypadku.
Panel: ryzyko, modyfikatory, długość/cooldown/cap, płatność, oceny HR, dostępność i taryfy zastępstw, limity, kolizje i komunikaty. Wersje nie przeliczają wstecz rozpoczętego epizodu/usługi.
Odbiór: wiele odczytów ten sam epizod; brak nakładania; granice cooldown/cap; brak podwójnej pensji; dwie rezerwacje nie kupują tej samej osoby; brak budżetu; powrót przed końcem zastępstwa; brak odpowiedniego kandydata; brak ukrytych cech w DTO.

## 15. Rekrutacja — przebieg i parametry do implementacji

Rozwija §2 i DOM16. Źródło ogłoszenia, zasięg, region, stanowisko i warunki zatrudnienia to oddzielne parametry. Kanał „agencja” nie jest przyszłym modułem zarządzania firmą.

| Kanał | Docelowa specjalizacja | Ograniczenie |
|---|---|---|
| Gazeta lokalna | Stanowiska lokalne i operacyjne | Mały zasięg, mniej rzadkich kwalifikacji |
| Radio | Większy nabór regionalny | Więcej niedopasowanych zgłoszeń |
| Telewizja | Masowy nabór na wiele stanowisk | Wysoki koszt, nie gwarantuje wysokich kompetencji |
| Portal ogłoszeniowy | Ogólny rynek pracy | Konkurencja warunkami oferty |
| Portal branżowy | Specjaliści techniczni/logistyczni | Wyższe oczekiwania płacowe |
| Uczelnia/szkoła | Początkujący z potencjałem | Niskie doświadczenie |
| Polecenie | Mała pula dopasowanych kontaktów | Cooldown i limit źródła |
| Agencja rekrutacyjna | Selekcja pod wymagania | Jawna opłata za selekcję |
| Headhunter | Rzadkie specjalizacje/zarząd | Zachowuje istniejące25GBBL/raz dziennie do jawnej zmiany balansu |

Formularz kampanii: rola, liczba wakatów, lokalizacja, kanał/zasięg, forma i okres umowy, pensja z jednostką, wymiar/grafik, wymagane certyfikaty, budżet i termin. Nie pozwala ustalać hidden_traits.
Przed startem quote: opłata początkowa, koszt selekcji, koszt sukcesu jeśli występuje, limit całkowity, prognozowany przedział zgłoszeń i czas. Zero gwarancji znalezienia kandydata.
Start atomowo rezerwuje limit kampanii, pobiera należną opłatę i zapisuje parametry/wersję/RNG. Bazowe max2procesy i czasy2–4/3–5min pozostają kanonem do zatwierdzenia mapy nowych kanałów. Nie wolno równolegle stosować dwóch różnych cenników.
Kampania tworzy trwałe oferty. Zmiana pensji/źródła po starcie jest nową wersją przyszłych działań, nie przerzutem wyników już wygenerowanych.
Cennik ma fee_basis=fixed/per_vacancy/budget oraz jednoznaczne jednostki. Krzywa budżetu monotoniczna, ograniczona i z malejącym przyrostem; nie liniowy zakup nieskończonej liczby kandydatów.
Atrakcyjność uwzględnia pensję na zgodnej podstawie etatu, umowę/grafik, reputację i bezpieczeństwo. Nie używać nieujawnionych cech osoby do rekomendacji.
Rozdzielić: liczbę zgłoszeń, rozkład kompetencji, dopasowanie wymagań i prawdopodobieństwo przyjęcia oferty. Wyższy budżet nie zwiększa automatycznie każdej z tych wielkości.

Przebieg kandydata: dostępny → oceniany → oferta złożona → zaakceptowana/odrzucona/wygasła → zatrudniony. Każda oferta ma expires_at, snapshot warunków i stan. Wygaśnięcie nie kasuje historii.
Wymagania twarde filtrowane jawnie; „brak certyfikatu” nie jest ukrytą cechą. Rozmowa/test zawodowy dostarcza oceny kompetencji z ustaloną dokładnością HR, nie podnosi skilla.
Accept/hire sprawdza własność, ważność, wersję, budżet i brak zużycia. Jedna oferta nie tworzy dwóch zatrudnień. Cena i warunki nie mogą zmienić się po cichu między akceptacją a hire.
Relokacja: potrzeba, kwota, płatnik i czas do rozpoczęcia zapisane w ofercie. Koszt i brak dostępności przed przyjazdem jawne; brak teleportacji do pracy. Mapowanie regionów wymaga katalogu.
Anulowanie kampanii nie odzyskuje automatycznie wykonanej usługi; zasady zwrotu/rezerwacji określa taryfa. Brak kandydatów nie oznacza nowego darmowego losowania.

Do ustalenia tabelami HR-CFG01: PLN dla kanałów, czasy kanał×zasięg, liczba/wagi kandydatów, ważność oferty, refundy, relokacja i efekt reputacji. Nie dopisujemy arbitralnego cennika jako zaakceptowanego.
Panel: edytor katalogu, schemat kombinacji, preview kosztów i rozkładów, kampanie/oferty, ślad losowania bez sekretu, statystyki skuteczności. Testy: koszt raz, brak re-roll, wygasanie, konkurencja hire, zgodność pensji/etatu, brak ofert i brak wycieku.

## 16. Ukryte cechy — model oceny i poznawania

Zatwierdzony pierwszy zakres: podatność na niedyspozycję, dokładność, współpraca i inicjatywa. Pozostałe cechy z §2.1 są późniejszym rozszerzeniem. Rozkład60%/30%/10% dotyczy0/1/2cech szczególnych i pozostaje kandydatem kalibracji.
Jedna rzeczywista wartość w katalogu Staff oraz osobna ocena HR. Nie tworzyć drugiej lojalności/ambicji/stresu ani dwóch niezależnych osi dokładność/niedbałość.
Każda cecha ma trait_code, dopuszczalną skalę, neutralny efekt, wersję generatora, właściciela skutku i allowlistę dowodów. Tablica mapowania intensywności→efekt jawna dla konfiguracji, prywatna dla gracza.
Dobór bez powtórzeń, zakazane kombinacje walidowane. Brak cechy szczególnej oznacza bazowy profil, nie zerowe ryzyko absencji lub zerową zdolność współpracy.
Profil trwały dla osoby; zatrudnienie, ponowne zatrudnienie i retry nie losują ponownie. Zmiana balansu nie przerzuca cech istniejących osób.

Poznawanie:
1. Nieocenione: brak ujawnionej wartości.
2. Obserwacja wstępna: fakty i ostrożna ocena.
3. Ocena poparta obserwacjami: przedział/poziom z datą i pewnością.
Żaden stan nie wymaga100% pewności. Kryteria: minimalny okres aktywnej obserwacji, liczba niezależnych epizodów/zadań, jakość dowodu i kompetencja HR. Progi konfigurowalne; nie definiujemy pewności jako samej liczby odczytów.
Przykłady dowodów: absencja=1epizod, dokładność=wyniki zakończonych porównywalnych zadań, współpraca=zdarzenia zespołowe, inicjatywa=potwierdzona obserwacja problemu. Dni jednego epizodu nie są wieloma dowodami.
Oddzielać wpływ pracownika od awarii, złego sprzętu i przeciążenia. Wskaźnik błędów bez liczby okazji/zadań nie wystarcza do oceny.
Chorobowej podatności nie ujawnia rozmowa rekrutacyjna. Dokładność lub współpracę może częściowo ocenić właściwy test/referencja z allowlisty.
Ocena wiąże osobę z obserwującą firmą; inna firma nie dostaje cudzych prywatnych obserwacji. Powrót do tej samej firmy zachowuje historię z datą, nie udaje świeżej stuprocentowej oceny.

Wpływ domenowy: dokładność przez kontrakt zadania/Incidents, współpraca przez istniejący model zespołu, inicjatywa przez powiadomienie/propozycję, podatność przez generator nieobecności. Jeden skutek i jedno source_event_id; nie dublujemy modyfikatora.
Inicjatywa nie wykonuje napraw i nie wydaje pieniędzy bez zgody. Ukryta cecha nie jest filtrem dozwolonym Agencji.
Panel konfiguracji: rozkład, wagi, intensywność, konflikty, mapy efektów, typy dowodów, progi oceny, retencja historii i teksty obserwacji. Rzeczywiste wartości GM tylko z capability/audytem.
Przed implementacją HR-CFG05 musi zawierać liczbowe mapy i testy statystyczne. Testy izolacji: payload, sortowanie, eksport, WebSocket, logi; brak wycieku przez wycenę ocen opartą na prywatnej cesze.

## 17. Szkolenia, mentoring i awanse

Training pozostaje wewnątrz Staff. Wykorzystuje istniejące skills, certyfikaty i dostępność, nie osobny model pracownika.
Katalog kursu: rola/skill, poziom, wymagany poprzedni poziom, minimalne kompetencje, kwalifikowalne umowy, cena, czas, tryb czasu, zajętość, próg zdania, bonusy prób, limity, zasady przerwania, certyfikat i ewentualna ważność.
Zachowujemy kanoniczne kursy/kwoty/capy: Refining20k/50k/100k,24/48/72h, próg70, skill cap10. Brakujące ceny kursów bazowych nadal DOM06; nie zamieniamy przedziału10–100k na arbitralny cennik.

Tryby czasu:
- elapsed_time: upływ według dotychczasowego zegara kursu, bez samowolnego przeliczania24h na3dni robocze;
- scheduled_training_time: postęp tylko w zaplanowanych godzinach nauki, jako przyszły wariant wymagający osobnej kalibracji.
Czas kalendarzowy i zajętość osoby to osobne pola katalogu. Płatne szkolenie w czasie pracy wchodzi do limitów grafiku. Nie publikować trybu24hciągłej pracy sprzecznego z odpoczynkiem.
Dla istniejącego elapsed_time wymagane jawne rozstrzygnięcie zajętości vs grafik w HR-CFG03 przed aktywacją nowego modelu. Rozpoczęte kursy zachowują swoją wersję.

Zapis: wycena, wymagania, dostępność, wpływ na obsadę, opłata raz i rezerwacja Staff. Nie odpinamy pracownika od krytycznego stanowiska bez akceptacji skutku.
Choroba: dla nowego kandydata polityki proponujemy paused_absence; zachowane postępy/opłata, brak egzaminu w czasie choroby, wznowienie bez ponownego pobrania. Aktualizacja terminu i due-event z wersją; stary job no-op.
To rozszerzenie wymaga jawnego dopięcia do istniejącej polityki kursu; nie stosować wstecz. Strajk nadal nie zatrzymuje kursu według kanonu. Urlop nie może automatycznie oznaczać udziału w szkoleniu; katalog określa pauzę/odmowę kolizji.
Koniec zatrudnienia: istniejące cancelled_employee_left, bez certyfikatu i zwrotu według kanonu. Remis logical exam/end wymaga DOM08, nie kolejności workerów.
Egzamin: jeden zapisany wynik, jedno nadanie certyfikatu i jeden przyrost skilla. Retry lub odebranie powiadomienia nie losuje ponownie. Reset porażek/bonusu po blokadzie pozostaje DOM07.

Mentoring: relacja mentor–podopieczny, cel, zakres czasu, wymagana kwalifikacja, limit podopiecznych i koszt utraconej dostępności. Nauka ma efekty tylko przy rzeczywistym pokryciu obu kalendarzy.
Mentor nie daje jednocześnie100% czasu naprawie i100% podopiecznemu. Bonus nie tworzy certyfikatu bez egzaminu. Limity/bonusy/talent mentorski do HR-CFG05, nie domyślna nieskończona premia.
Wdrożenie nowego pracownika: jawny okres i krzywa osiągania docelowej samodzielności w katalogu roli; nie obniża trwale zapisanych skills. Krytyczne uprawnienie nadal wymagane od początku.

Awans: propozycja roli → walidacja kompetencji/certyfikatów → quote nowych warunków → akceptacja → aneks od effective_from → przeliczenie przydziału/grafiku.
Awans nie resetuje stażu, osoby, morale, chorobowego lub historii. Nie nadaje automatycznie skilla. Płatność i oczekiwania aktualizowane przez wspólne kontrakty płac, a nie bezpośredni UPDATE.
Rekrutacja wewnętrzna pokazuje lukę pozostawioną na poprzednim stanowisku. Zmiana mandatu zarządu używa istniejącego BoardAccess.
Certyfikat ukończenia i uprawnienie do pracy są różne. Certyfikat historyczny nie znika; wygasające uprawnienie może wymagać odnowienia. Nie wszystkie kursy otrzymują arbitralny termin ważności.
Wygasanie blokuje nowe zadania wymagające uprawnienia; trwające mają zatwierdzony safe-stop/handover. Odnowienie ma osobny quote i ślad, nie drugi ten sam certyfikat poziomu.

Panel: katalog i wersje, zapisy/pauzy/terminy, próby, certyfikaty/uprawnienia, mentoring, awanse, wpływ na obsadę i koszty. Nie ma cichego „zalicz” bez właściwej akcji GM/audytu.
Testy: kwalifikowalność i drugi zapis, kurs vs praca, choroba/pauza/wznowienie bez drugiej opłaty, stary job, egzamin retry, koniec umowy, współdzielony czas mentora, awans bez resetu, wygaśnięcie uprawnienia, izolacja firmy.
Bramki: DOM06/07/08, HR-CFG03/05, HR-CONTRACT01 i testy gospodarki90dni. Rozdziały14–17 są specyfikacją, nie gotowym kodem ani wynikiem PASS.


## 18. Oceny i obserwacje HR — specyfikacja wykonawcza HR-ASSESS-01

Data:2026-11-18. Rozwinięcie §2.1/§16, właściciel Staff, obszar wewnętrzny bez odrębnego modułu/runtime. Nie dotyczy całego domknięcia Kadr.
Status: projekt algorytmów, danych i kontraktów. Progi liczbowe poniżej są kandydatem do kalibracji; aktywny YAML pozostaje bez zmian. Brak gotowego kodu i testów runtime.

### 18.1. Gwarancje i granice

1. Rzeczywisty profil wpływa na symulację; evaluator HR nie ma dostępu do jego wartości.
2. Ocena wynika wyłącznie z dowodów dostępnych obserwującej firmie.
3. Wysoka pewność opisuje stabilność zaobserwowanego zachowania, nie procent trafności wykrycia ukrytej cechy.
4. GET, refresh, ponowne logowanie, restart i manualny przycisk analizy nie tworzą dowodów.
5. Profil osoby pozostaje po rehire; historia firmy i aktualność oceny są odrębne.
6. Dostępność nie jest morale, chorobowe nie dowodzi nielojalności/symulacji choroby.
7. Zdarzenie korzystne/niekorzystne nie powoduje automatycznej zmiany pensji, zwolnienia lub kary. Decyzje kadrowe to odrębne akcje.
8. Agencja i zwykłe UI nie otrzymują więcej informacji niż właściciel firmy.
9. Ocena historyczna nigdy nie jest nadpisana po zmianie metody.
10. Pozytywne wyniki, brak danych i brak okazji do działania to różne sytuacje.

### 18.2. Trzy warstwy danych

Profil rzeczywisty: istniejące Staff traits/skills, trwała tożsamość osoby, generator i wersja. Nie kopiować ukrytych wartości do tabel ocen.
Dowód: immutable źródłowe zdarzenie, czas i kontekst, jakość, rodzaj okazji, obserwator. Korekta przez odrębny wpis wskazujący zastępowany dowód.
Ocena: snapshot okna dowodów, metoda, etap, kierunek wniosku, jakość podstaw, aktualność i opublikowana treść przez klucz tłumaczenia.
Zależność wyłącznie profile→symulacja→zdarzenia→evidence→assessment. Zabroniony skrót profile→assessment.
Nie zapisujemy prawdziwych danych medycznych: dotyczy fikcyjnych pracowników i zdarzeń gry, bez diagnoz w UI.

### 18.3. Słownik stanów

Etap: unassessed, preliminary, supported.
Kierunek: insufficient, mixed, typical, favourable, concern. Nie każdy kierunek ma sens dla każdej domeny; allowlista w polityce.
Pewność prezentacyjna: unavailable, low, medium, high.
Aktualność: current, stale; stan techniczny osobno: queued, computing, completed, failed.
Unassessed nie pokazuje ukrytej liczby0 ani oceny „zły”. Preliminary może mieć niską/średnią pewność, supported średnią/wysoką; nigdy supported tylko dlatego, że minęło dużo dni.
Stale nie usuwa ostatniej oceny: pokazujemy datę, jej historyczny zakres i powód potrzeby nowych danych.
Przejścia mogą działać wstecz przy wygaśnięciu okna, korekcie dowodu lub zmianie roli. Historyczny snapshot pozostaje.
Brak oceniającego personelu HR: dowody zbierane dalej, poprzednia ocena widoczna z datą; nie korzystamy z maksymalnego skilla zastępczego.

### 18.4. Kandydat progów wejścia

Wymagania w wierszu są łączne. „Dzień” oznacza dzień rzeczywistej ekspozycji/obserwacji według UTC, nie samo istnienie konta.

| Domena | Preliminary | Supported | Okno |
|---|---|---|---|
| Dostępność | 30aktywnych dni zatrudnienia i80zaplanowanych godzin | 90dni i240h | 180aktywnych dni |
| Dokładność | 10kwalifikujących zadań na5różnych dniach pracy | 40zadań na20dniach | 90aktywnych dni |
| Współpraca | 8wspólnych zadań na4zmianach | 25zadań na12zmianach i co najmniej3różne osoby współpracujące | 90aktywnych dni |
| Inicjatywa | 10kwalifikujących okazji na5zmianach | 30okazji na15zmianach | 90aktywnych dni |

Absencja jest składnikiem wyniku dostępności, nie koniecznym warunkiem zebrania historii. Nieobecność0 nie oznacza pewnego braku ukrytej podatności.
Dla kierunku concern w dostępności dodatkowo minimum2niezależne epizody; supported concern wymaga minimum3 oraz progów supported i kontroli warunków. Są to proponowane zabezpieczenia, nie gwarancja kierunku wniosku.
Częściowy etat nie daje automatycznie gorszej oceny; gromadzi mniej okazji/godzin i może potrzebować dłuższego czasu.
Awans/zmiana charakteru pracy nie usuwa dowodów, lecz zmienia ich kwalifikowalność. Ocena nowej roli może być nieoceniona mimo dobrze poznanej starej roli.

### 18.5. Kontrakt dowodu

Producer dostarcza: source_module, source_event_id, source_revision, event_type, person_id, employment_period_id, observer_company_id, logical_at, interval_from/to, opportunity_id, role/task_category, outcome_code, exposure_units, public_context i schema_version.
public_context zawiera tylko dozwolone informacje: poziom trudności, stan sprzętu, obciążenie, kwalifikacje znane firmie, rodzaj zmiany, zdarzenia zewnętrzne. Żadnych hidden_trait_value lub secret_rng.
Adapter domeny wyznacza eligibility i quality wraz z reason_code; nie przyjmuje dowolnej jakości z klienta.
Unikalność źródła: (source_module, source_event_id, observer_company_id, assessment_domain, source_revision). Jedna najnowsza skuteczna rewizja danego dowodu; rewizje nie są nowymi niezależnymi obserwacjami.
Independence key:
- dostępność: absence_episode_id, a nie dzień absencji;
- dokładność: trwałe task_id lub z góry zdefiniowane okno produkcji, nie każdy baryłkowy ruch;
- współpraca: wspólne task/shift+team_ref, nie każde powiadomienie;
- inicjatywa: opportunity_id, zarówno wykorzystana jak i niewykorzystana okazja.
Wagi wspólnego zdarzenia nie mogą rosnąć przez rozbicie na callbacki. Udział obserwacji z jednego dnia/zadania ma cap w polityce.
Nie wolno interpretować braku dostarczonego eventu jako braku inicjatywy; tylko jawne zamknięcie rzeczywistej okazji daje taką obserwację.

### 18.6. Normalizacja wyników

Dostępność: osobno liczba epizodów/ekspozycja, utracone planowane godziny/planowane godziny, długość epizodów. Zatwierdzony urlop nie jest chorobowym; osobne kody przyczyn.
Dokładność: wyniki porównywalnych zadań wobec baseline rola×trudność×sprzęt×obciążenie. Brak wiarygodnego baseline → dowód opisowy, bez oceny liczbowej.
Współpraca: wyłącznie potwierdzone wyniki zespołowe i kontekst. Jedna osoba współpracująca nie daje supported oceny reprezentatywnej dla całej firmy.
Inicjatywa: wykorzystane okazje / kwalifikujące okazje, z jakością potwierdzonego skutku.0okazji=brak oceny, nie0kompetencji.
Dla każdej domeny adapter emituje signed residual w [-1,1], quality w[0,1] i attribution reason. Znaki znaczą favourable/concern zgodnie ze słownikiem domeny, nie ogólną ocenę wartości pracownika.
Wzór residual, wartości baseline i margines typical muszą być w zatwierdzonej DomainAssessmentPolicy. Nie wpisujemy arbitralnych progów błędów dla wszystkich stanowisk.
Gdy dominującym czynnikiem jest awaria/warunki, zmniejszamy quality lub wyłączamy dowód z oceny. Historia zdarzenia nadal dostępna.
Nie porównywać pracownika z jego prywatnym „oczekiwanym wynikiem” wyliczonym z ukrytej cechy — byłby to przeciek przez normalizację.

### 18.7. Proponowany algorytm jakości podstaw

Oddzielamy kierunek oceny od jakości podstaw. Kandydat do symulacji:
w_i = eligibility_i × quality_i × freshness_i × independence_cap_i.
Score = sum(w_i×residual_i)/sum(w_i); gdy mianownik0 → unassessed.
Freshness_i=max(0,1−age_i/window) w aktywnym czasie obserwacji; dla innego modelu zaniku konieczna nowa wersja polityki.
N_eff=(sum(w_i))²/sum(w_i²), przy zerowym mianowniku0. Chroni przed traktowaniem wielu znikomych lub jednej dominującej obserwacji jako pełnej próby.
Q = min(1,N_eff/target_count); D = znormalizowane pokrycie dni/zmian/współpracowników dla domeny; A = średnia ważona aktualność; R = jakość atrybucji kontekstu. Dokładne liczenie każdej miary zapisane w polityce; capy przed agregacją.
Proponowany wskaźnik podstaw C=100×(0,35Q+0,25D+0,20A+0,20R). Nie jest skalibrowanym prawdopodobieństwem statystycznym.
Proponowane przedziały: low<50, medium50–79, high≥80. Etapy nadal wymagają bramek §18.4, niezależnie od C.
Brak minimalnej jakości, niejednorodna rola albo silnie sprzeczne wyniki może dać mixed, nawet przy dużej próbce. Nie ukrywamy sprzeczności przez prostą średnią.
HR nie mnoży wprost C przez skill. Kompetencje mogą poprawić jakość rozpoznania kontekstu i zmniejszyć zakres niepewności tylko przy odpowiednich dowodach.
Konkretne baseline/residual, independence cap, target_count i strategia sprzeczności pozostają ASSESS-CFG01. Bez nich evaluator nie jest produkcyjnie gotowy. Ten wzór jest kandydatem, nie pozornie kompletną statystyką wykrywania cech.

### 18.8. Rola działu HR i kolejka analiz

Wykorzystujemy istniejące umiejętności analizy/organizacji przez publiczny Staff context. Mapowanie ról na uprawnionego analityka wymaga katalogu; brak nowego duplikatu skilla.
Lepszy analityk lepiej oddziela kontekst od pracownika i obsługuje większą kolejkę; nie tworzy dowodów i nie zagląda do profilu.
Ocenę obliczamy najwyżej raz na dobę aktywnego czasu firmy dla person+domain, po nowych dowodach/zmianie metody/wygaśnięciu okna. Pilne fakty widoczne od razu, ale nie wymuszają nowej oceny.
Manualny Reanalyse: ten sam input_hash+policy_version zwraca istniejący wynik. Brak płatnego „rzucania do skutku”.
input_hash obejmuje kanonicznie uporządkowane evidence revisions, role_context, method_version, as_of i HR context. Slot dobowy trwały; samo nowe request_id nie zmienia as_of.
Przerwanie pracy HR nie zatrzymuje logowania dowodów. Powrót przelicza stan docelowy zamiast emitować setki starych powiadomień.
Zamrożenie firmy korzysta z istniejącego zegara; po długiej przerwie kalendarzowej UI pokazuje datę ostatniej oceny, nawet jeżeli aktywny czas się nie przesunął.

### 18.9. Schemat logiczny

staff_observation_events: id, source refs/revision, person/company/employment, domain, opportunity/independence key, logical_at, exposure, context_schema_version, payload allowlist, correction_of, recorded_at. Append-only.
staff_observation_eligibility: evidence_id, policy_version, eligible, quality, reason_code, context_hash; UNIQUE(evidence_id,policy_version).
staff_assessment_runs: id, company/person/domain, as_of, method_version, input_hash, HR_context_ref, status, result_ref, error_code; UNIQUE(company,person,domain,input_hash,method_version).
staff_assessment_snapshots: run_id UNIQUE, stage, direction, confidence_band, basis_score, freshness_state, window_from/to, independent_count, exposure, message_key, safe_message_params, created_at. Immutable.
staff_assessment_current: company/person/domain PK, snapshot_id, state_version. CAS nie pozwala starszemu wynikowi zastąpić nowszego.
staff_assessment_evidence_links: snapshot_id,evidence_id,evidence_revision,weight,eligibility_reason, UNIQUE(snapshot_id,evidence_id,evidence_revision).
Wszystkie ownership FK i indeksy company+person+domain+logical_at, źródło+revision, status+due wymagają migracji w HR-CONTRACT01. JSON tylko dla ograniczonego kontekstu, nie całego modelu.
Retencja nie może usuwać podstawy snapshotu bez jawnej polityki archiwizacji i informacji „szczegóły zarchiwizowane”. Nie przeliczać starej oceny z niepełnej historii.

### 18.10. Akcje, zdarzenia i transakcje

RecordObservation: zaufany producer, walidacja schema/ownership, idempotentny zapis, outbox ObservationRecorded. Client nie ma publicznej mutacji tworzącej dowody.
CorrectObservation: wskazanie pierwotnego źródła, nowa rewizja, uprawnienia, audyt i unieważnienie zależnych aktualnych ocen do przeliczenia. Bez usuwania źródła.
ScheduleAssessment: scalanie żądań do jednego slotu, snapshot wejść. ComputeAssessment poza długą transakcją na niezmiennych danych.
PublishAssessment: sprawdzenie input/version i aktualnej revokacji/scope, CAS bieżącego snapshotu, historia/outbox atomowo. Zmiana wejść w trakcie obliczeń → stale/requeue, nie podmiana nowszego wyniku.
Odrębne lease+fencing dla długiego zadania; dla krótkiego zapisu standardowa idempotencja. Nowe tabele wymagają globalnej kolejności Staff14, nie własnych blokad po append140.
AssessmentUpdated payload minimalny: snapshot_id/company/person/domain/version; nie hidden profile ani cały evidence payload.
Powiadomienie tylko przy istotnej zmianie etapu/kierunku/powrocie aktualności według polityki. Nie wysyłać alertu dla każdej zmiany C o ułamek punktu.

### 18.11. Kontrakt HTTP i DTO

Proponowany odczyt: GET /api/v1/hr/employees/{id}/assessments; GET /api/v1/hr/assessments/{snapshot}/basis (paginowane).
Proponowane żądanie analizy: POST /api/v1/hr/employees/{id}/assessment-requests z domain, expected_state_version i Idempotency-Key. Bez pól wynik/cecha/pewność.
DTO: domain,stage,direction,confidence_band,freshness,as_of,window,independent_count,exposure_summary,message_key,safe_params,method_version,pending_refresh. Bez basis_score i surowego residual dla zwykłego gracza w pierwszej wersji.
Brak oceny zwraca jawne unassessed z brakującymi typami przesłanek, nie404 osoby ani ukrytą wartość.
Błędy:401/403/404wg wspólnej polityki,409stale/already_queued,422nieznana domena,429limit,503moduł niedostępny. Dokładne schematy i zgodność semantyki already_queued wymagają karty API; zalecany replay istniejącej operacji zamiast nowej.
Filtry/sortowanie/eksport/cache/WebSocket bazują na publicznym DTO i ownership. Cache key obejmuje firmę, uprawnienia i wersję oceny. Brak ukrytej wartości w HTML,data-*,telemetrii i komunikatach błędów.
GM odczyt rzeczywistego profilu oddzielnym kontraktem/capability z audytem, nigdy dodatkowym polem zwykłego DTO.

### 18.12. Widok gracza

Profil → Obserwacje HR, cztery obszary oceniane oddzielnie.
Każdy: nazwa, etap, bezpieczny wniosek, pewność, aktualność, okres/liczba niezależnych obserwacji, Zobacz podstawę.
Podstawa: lista faktów z datą, rodzajem zadania i opisem ograniczenia; widok bez sekretów, skalibrowanych ukrytych wag i danych obcej firmy.
Przykład preliminary availability: „Historia jest zbyt krótka, aby określić trwałą tendencję. Zaobserwowano dwa krótkie epizody nieobecności.” Nie „ma podatność”.
Przykład supported accuracy: „W porównywalnych zadaniach popełnia mniej błędów niż poziom odniesienia.” Gdy brak baseline, nie pokazujemy takiego wniosku.
Rekrutacja: ocena zawodowa z rozmowy/testu tylko tam, gdzie dopuszcza katalog; dostępność/morale przyszłej pracy nadal nieocenione.
Animacje zgodnie z §12.1; nie pokazują pseudo-losowania cechy. SVG, trzy języki, focus/klawiatura, mobile320px. Ładowanie/ocena w toku nie zastępują poprzedniego wyniku pustą kartą.

### 18.13. Panel i wersjonowanie

Kadry → Oceny i obserwacje HR:
- katalog domen/cech i allowlista dowodów;
- progi czasu/liczby/ekspozycji/różnorodności;
- polityki baseline, residual, jakości, zaniku i przedziałów pewności;
- mapowanie analityka/kompetencji i limitów kolejki;
- komunikaty PL/EN/DE i reguły powiadomień;
- lista snapshotów, dowodów, korekt, błędów i zaległych analiz;
- preview nowej metody na syntetycznych danych i porównanie wersji.

Aktywna metoda niezmienna, zmiana jako kandydat z hash i datą. Wdrożenie nie zmienia ukrytej cechy. Przeliczenie aktualnych ocen tworzy nowe snapshoty z nową wersją; nie nadpisuje historycznych.
GM może skorygować błędny fakt przez uprawnioną akcję, nie wpisać dowolnej pewności w computed snapshot. Główny administrator bez obowiązkowego komentarza; automatyczny audyt i reason operacji pozostają.
Granice praw: hr.assessments.view, hr.assessment_policy.draft/activate, hr.observations.correct, hr.assessments.recompute, hr.hidden_traits.view. Nazwy do rejestracji, nie przyznane uprawnienia.

### 18.14. Testy i kryteria odbioru

A01 epizod3dni=1dowód. A02 100GET=0nowych dowodów. A03 duplicate/revision nie zwiększa niezależnej liczby.
A04 0okazji inicjatywy→unassessed. A05 sprzęt/awaria obniża jakość atrybucji. A06 brak baseline blokuje liczbowy wniosek.
A07 wszystkie progi wejścia wymagane; progi dokładnie na granicy. A08 wysokie C bez ekspozycji nie daje supported.
A09 niedostępność nie staje się karą morale. A10 silny HR bez danych nie zna cechy.
A11 rehire zachowuje profil; obca firma nie dziedziczy oceny. A12 wygasłe okno/nowa rola obniża aktualność/etap bez kasowania historii.
A13 metoda zmienia ocenę, nie źródłowe fakty/profil. A14 korekta dowodu wymaga nowego snapshotu.
A15 brak wycieku przez DTO, filtry, cache, eksport, logi, WebSocket i Agencję.
A16 worker/job/HTTP/retry publikują jeden wynik; stary worker nie zastępuje nowego. A17 odłączenie firmy/uprawnień przed publikacją.
A18 brak personelu HR zachowuje dowody i ostatni wynik. A19 symulacja różnorodnych ról/etatów/warunków; pomiar mylnych wniosków.
A20 preview bez skutków; admin bez komentarza; odmowa nieuprawnionej korekty.
A21 PL/EN/DE/mobile/keyboard/reduced-motion. A22 retencja zachowuje odtwarzalność podstawy lub jawne archiwum.

Testy statystyczne na syntetycznych profilach: odsetek favourable/concern/mixed i etapów po30/90/180dniach, fałszywe alarmy przy małej próbie, różnice ekspozycji pełny/pół etatu, przeciążenie vs neutralne warunki. Porównanie z rzeczywistą cechą dozwolone WYŁĄCZNIE w offline harness, nie evaluatorze runtime.
Bramki: ASSESS-CFG01 baseline/wagi/progi, ASSESS-CONTRACT01 DDL/OpenAPI/lock order, ASSESS-QA01 kalibracja statystyczna i negatywne testy wycieków.
Etapy programowania:1zbieranie/deduplikacja →2read-only historii →3evaluator i wersje →4snapshoty/worker →5UI/panel →6kalibracja/aktywacja.
Ta runda opisuje kontrakty i testy do wykonania. Nie deklaruje testów Laravel/MySQL ani pełnego zamknięcia HR-CFG05.
