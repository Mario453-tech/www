# OilEmpire — rozszerzenie kadr i zatrudnienia

[HR-PAY-01](OILEMPIRE_KADRY_UMOWY_PLACE_ODPRAWY.md) opisuje płace i odprawy, a §19 wybiera wariant A dla DOM01/DOM02/DOM16. Wcześniejsze wzmianki o tych decyzjach jako otwartych są historycznym stanem rozdziałów 1–18; przed aktywacją nadal wymagane są kalibracja, finalne migracje/OpenAPI i testy. Aktywny YAML v1 pozostaje bez zmian.


Kontrakt do programowania wariantu A: [HR-CONTRACT01 — tabele, akcje, API, uprawnienia i migracja](OILEMPIRE_KADRY_KONTRAKT_IMPLEMENTACYJNY.md), [DDL MySQL 8.4](OILEMPIRE_KADRY_DDL_MYSQL84.sql) i [OpenAPI 3.1](OILEMPIRE_KADRY_OPENAPI_V1.yaml). Ten rozdział pozostaje źródłem mechaniki i wartości; kontrakt wykonawczy uszczegóławia jej zapis oraz transport.

Status: zatwierdzony zakres funkcjonalny z rozmowy właściciela, specyfikacja do programowania; brak implementacji. Wartości nowych stawek i końcowe kontrakty wykonawcze pozostają bramką gotowości.
Właściciel: Staff. Powiązania: [architektura](OILEMPIRE_ARCHITEKTURA_SYSTEMOWA.md) §3–§6, §19, §23; [karty](OILEMPIRE_KARTY_IMPLEMENTACJI_P06_P13.md) P07–P09; P06 reputacja, P11–P13 obsada, P16 finanse, P24 interfejs.
Ten rozdział jest źródłem wymagań dla rozszerzenia HR, nie zmianą aktywnego YAML v1. DOM01, DOM02 i DOM16 otrzymują szerszy zakres; pozostałe propozycje DOM nie zostają automatycznie zatwierdzone.

> **DOM03/DOM04:** [decyzje właściciela](OILEMPIRE_DOM_DECYZJE_WLASCICIELA_2026_11_18.md) przyjęte do programowania. [DOM06](OILEMPIRE_DOM06_KATALOG_SZKOLEN_KANDYDAT.md) ma przyjęte ceny wariantu A, lecz wymaga testu ekonomii przed aktywacją. [DOM03-A](OILEMPIRE_DOM03_NEGOCJACJE_WARIANT_A.md) podaje wzór żądania grupowego.

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
LockPlan: standard idempotency/company/settlement → Staff14 → dalsze obiekty/konta → append140. Dokładną kolejność 30 tabel Staff A podaje architektura §23.5a.1; kontrakt maszynowy `x-staff-lock-order` zawiera tę samą listę. Nie lockować obiektu, a dopiero później pracownika.
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
Publikacja konfiguracji korzysta z istniejącej kolejności blokad konfiguracyjnych. Migracja indywidualnych praw jest osobną operacją gospodarczą; nie łączyć obu kolejności w jednej transakcji. Pełne DDL/OpenAPI pozostają do wykonania według HR-CONTRACT01; lista nowych tabel LockPlan jest już w architekturze §23.5a.1.
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
Wariant A dzielnika miesiąca i profilu kosztów opisują HR-PAY-01 oraz §19.3. W kodzie nie tworzymy innej podstawy ani procentu składek; wymagane są testy naliczenia i finalne migracje.

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
Dla wariantu A rozstrzygnięcie kar i populacji sporu znajduje się w §19.3; ten wcześniejszy punkt oznaczał brak decyzji przed wyborem A. Aktywację blokują jeszcze testy i kalibracja, nie wybór pomiędzy −10 i −8.

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
LockPlan zachowuje rank14 Staff przed kontami20/21 i append140. Pełna kolejność 30 tabel A jest w globalnym rejestrze architektury §23.5a.1 i w kontrakcie maszynowym; migracje muszą użyć tych nazw lub najpierw zaktualizować oba rejestry.
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

Wariant A dla kanałów, cen, czasów, liczby ofert, refundów i relokacji określa §19.2. Szczegółowe modyfikatory reputacji i rozszerzenia B pozostają do kalibracji; nie dopisujemy ich jako aktywnych wartości.
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


### 16.1. Katalog i generator profilu — HR-TRAITS-01

Status: szczegółowy kandydat do programowania i kalibracji; nie zmienia aktywnego game_balance v1. Doprecyzowuje §2.1, §14 i §16. Oceny z §18 obserwują skutki, nigdy nie odczytują poniższych wartości. Liczby oznaczają mechanikę fikcyjnych pracowników gry, nie model medyczny.

#### A. Rozdzielenie profilu, umiejętności i stanu

- Profil szczególny: trwałe osie podatności, dokładności, współpracy, inicjatywy.
- Umiejętności: istniejące kwalifikacje i poziomy zawodowe; wpływ na bazowy wynik określa właściciel zadania.
- Stan: morale, zmęczenie, stres, odpoczynek, dostępność. Zmienia się w czasie i nie przepisuje profilu.
- Ocena: wniosek konkretnej firmy z obserwacji, według §18. Nie jest kopią profilu.

Neutralny profil nadal korzysta z umiejętności, może popełnić błąd, zachorować, współpracować i zauważyć problem. Szczególna dokładność nie daje kwalifikacji, inicjatywa nie daje uprawnienia do wydania pieniędzy. Podatność nie oznacza niskiej lojalności ani niskiego morale.

Kody poniżej są proponowanymi kodami katalogu Staff. Przed migracją wymagane sprawdzenie istniejącego słownika employee_traits: istniejącą równoważną oś mapujemy, nie tworzymy drugiej. Nie przeskalowujemy w ciemno obecnych skills1–10.

#### B. Katalog wartości i neutralność

| Oś / proponowany kod | Wartości szczególne | Neutralna | Właściciel zastosowania |
|---|---|---|---|
| Podatność / absence_susceptibility | 1 łagodna, 2 umiarkowana, 3 silna | 0 | Staff, generator krótkiej absencji |
| Dokładność / accuracy | −3,−2,−1 oraz +1,+2,+3 | 0 | Właściciel zadania przez pojedynczy kontrakt wyniku/Incidents |
| Współpraca / cooperation | −3,−2,−1 oraz +1,+2,+3 | 0 | Właściciel zadania zespołowego |
| Inicjatywa / initiative | −3,−2,−1 oraz +1,+2,+3 | 0 | Właściciel kwalifikującej okazji |

Skala to odrębna jednostka intensywności, nie ocena pracownicza1–10. Brak wpisu szczególnego daje wartość0. Zapis nie może jednocześnie zawierać dwóch wartości tej samej osi. Ujemna dokładność realizuje niedbałość: nie dokładamy dodatkowej cechy z drugim mnożnikiem. Konfliktowość, talent mentorski i stosunek do ryzyka pozostają poza pierwszym generatorem.

Nie dodajemy szczególnej „odporności na choroby” jako przeciwieństwa podatności w tej wersji. Brak podatności nie gwarantuje zdrowia.

#### C. Losowanie liczby, osi, znaku i intensywności

1. Przy pierwszym utworzeniu osoby losujemy K: 0 z wagą6000, 1 z3000, 2 z1000; suma10000.
2. Wybieramy K różnych osi spośród czterech. Wagi osi: każda100. Losowanie ważone bez zwracania, stała kolejność kodów przy budowie przedziałów.
3. Podatność zawsze dodatnia. Dla pozostałych osi znak dodatni/ujemny ma wagę50/50.
4. Intensywność bezwzględna1/2/3: wagi60/30/10.
5. Walidujemy kompletny profil i zapisujemy atomowo z generation_version oraz niezmiennym identyfikatorem losowania.

Przedziały losowania całkowite i półotwarte; przykład K: U z0..9999, [0,6000)→0, [6000,9000)→1, [9000,10000)→2. Próbkowanie liczb całkowitych bez błędu modulo; nie używać round(random×limit). Algorytm RNG i wersja należą do wspólnego kontraktu silnika; brak zatwierdzonego adaptera blokuje aktywację.

Idempotentne wejście to niezmienny person_creation_key ze źródła kandydatury. Zapis RNG/profile musi przetrwać retry; nowy request_id, odświeżenie, odrzucenie oferty i ponowne zatrudnienie nie tworzą nowego profilu tej samej osoby. Klient nie podaje seed ani cechy. Sekret generatora nie trafia do DTO/logów; narzędzia odtwarzania dostępne tylko wewnętrznie.

W pierwszym kandydacie kanał rekrutacji, cena kampanii, region, płeć, wiek, forma umowy i wymiar pracy NIE modyfikują rozkładu ukrytych osi. Kanał nadal może zmieniać liczbę kandydatów, dopasowanie kwalifikacji i dowody zawodowe według §15. To jawne rozstrzygnięcie wcześniejszego otwarcia zależności cech od źródła; przyszła zmiana wymaga osobnej wersji i kalibracji.

Wynik oczekiwany na10000 osób:6000 neutralnych,3000 zjedną,1000 zdwiema; łącznie5000 szczególnych osi. Każda oś występuje średnio u1250 osób (12,5%), nie u25% całej populacji. Podatność intensywności1/2/3: oczekiwane750/375/125 osób. To wartości oczekiwane, nie kwoty wymuszane w pojedynczej kampanii.

#### D. Kombinacje i walidator

Dozwolone wszystkie6 różnych par czterech osi, wszystkie dopuszczalne znaki i intensywności. Dokładny pracownik może być podatny na nieobecności; współpracujący może mieć niską inicjatywę. Nie wymagamy sztucznego równoważenia zalety wadą.

Zakazane:
- powtórzona oś, np. accuracy+2 i accuracy−1;
- accuracy oraz osobna aktywna niedbałość;
- więcej niż2 szczególne osie w tej wersji;
- wartość spoza skali, nieznany kod lub cecha przyszłego etapu;
- zerowa intensywność zajmująca miejsce jednej zK cech;
- nadpisanie istniejącej lojalności/ambicji/odporności na stres przez nowy los.

Walidator wersji sprawdza, czy dla każdego K o dodatniej wadze istnieje legalny wybór. Przy przyszłych wykluczeniach katalog enumeruje legalne uporządkowane wybory i ich wagi; nie używać nieograniczonej pętli „losuj aż pasuje” ani cichego zmniejszania K. Dla bieżącego katalogu każda para jest legalna.

#### E. Podatność — mapa ryzyka

| Intensywność | Nominalne prawdopodobieństwo rozpoczęcia krótkiej absencji w30 aktywnych dniach |
|---:|---:|
| 0 | 10% |
| 1 | 13% |
| 2 | 16% |
| 3 | 20% |

Są to prawdopodobieństwa dla bazowej ekspozycji bez blokad, przed wpływem warunków. Nie interpretować20% jako20% dni na chorobowym ani2× ryzyko codziennie. Cooldown, limit epizodów i okresy niekwalifikujące zmniejszają zrealizowaną częstość.

Kandydat przeliczenia na pełny aktywny dzień: p_day=1−(1−p30)^(1/30). Przykład p30=10% daje około0,3505857%/dzień; p30=20% około0,7410524%/dzień. Dla części dnia ekspozycji e w0..1: p(e)=1−(1−p_day)^e. Ryzyko składa się po ekspozycji, nie sumuje liniowo.

Wersjonowany modyfikator warunków m działa na hazard: p(e,m)=1−(1−p30)^(e×m/30). Kandydat ogranicza wynik m do0,75..1,50; neutralny m=1. Morale nie jest wejściem m. Brak mapy odpoczynku/zmęczenia/warunków nie oznacza automatycznego uruchomienia neutralnej konfiguracji na produkcji — pozostaje bramką HR-CFG03.

Długość1/2/3 aktywne dni z równymi wagami1/1/1, losowana raz przy początku epizodu. Intensywność wpływa tylko na częstość, nie ponownie na długość, płatność, cooldown lub cap. Zachowane:7dni cooldown od końca, max2 rozpoczęcia w ruchomych30dniach, brak nakładania. Wypadki nie korzystają z tej cechy.

Przed aktywacją konieczne zamknięcie podstawy ekspozycji (grafik, dni wolne, urlopy, szkolenia), slotów oraz przenoszenia zegara osoby między firmami. Pauza i rehire nie mogą usuwać istniejącego cooldownu/licznika. Sama mapa p30 nie zamyka generatora chorobowego.

#### F. Dokładność — jeden mnożnik ryzyka błędu

| Wartość | −3 | −2 | −1 | 0 | +1 | +2 | +3 |
|---|---:|---:|---:|---:|---:|---:|---:|
| Mnożnik | 1,30 | 1,20 | 1,10 | 1,00 | 0,90 | 0,80 | 0,70 |

p_base to ryzyko błędu przypisywalnego wykonawcy, obliczone przez właściciela zadania z kwalifikacji, trudności, sprzętu i obciążenia, bez tej osi. p_final=clamp(p_base×m_accuracy,0,1). To zmiana względna:10%×0,70=7%, nie minus30 punktów procentowych.

Nie zmienia błędu czysto maszynowego ani pewnego skutku reguły. Brak kwalifikacji nadal blokuje zadanie; wysokie ryzyko nie zastępuje zakazu. Błąd, jego dotkliwość i księgowanie pozostają jednym wynikiem właściciela. Dokładność nie zmniejsza równocześnie prawdopodobieństwa, wielkości straty i kosztu naprawy. W tej wersji nie daje bonusu szybkości ani kary czasu.

Dla zadań ciągłych właściciel określa stałą okazję do błędu lub hazard niezależny od liczby wywołań Settlement. Podział jednego zadania na segmenty nie tworzy nowych prób. Bez takiego kontraktu mapa pozostaje nieaktywna w danej kategorii.

#### G. Współpraca — wynik wspólnego zadania

Ta sama tabela mnożników co wF stosowana jest do ryzyka nieudanego współdziałania, nie do całej produkcji. Dodatnia współpraca zmniejsza ryzyko; ujemna zwiększa. Neutralna osoba nadal współpracuje.

Dla zespołu: x_team=sum(x_i×h_i)/sum(h_i), gdzie h_i oznacza rzeczywiście wykonane, kwalifikujące godziny udziału. Nieobecny, niezatrudniony lub tylko wpisany do planu ma wagę0. Przy sum(h_i)=0 brak okazji/wyniku; nie losujemy udanego współdziałania. M_team=1−0,10×x_team, zakres0,70..1,30. Wartości pośrednie zachowujemy bez zaokrąglenia osi.

p_failure=clamp(p_failure_base×M_team,0,1). Dla osób+3 i−1 z równym czasem: x_team=1, M=0,90; bazowe20% niepowodzenia daje18%. Zwiększenie liczby identycznych pracowników nie mnoży bonusu.

W pierwszym zakresie brak bezpośredniej zmiany morale, strajku, wydajności i czasu szkolenia. Faktyczne powodzenie/konflikt może wejść do istniejącego modelu morale, jeśli jego katalog tak stanowi, z jednym source_event_id. Mentorowanie i osobny talent mentorski wymagają przyszłej mapy; nie przyznajemy darmowego przyspieszenia.

Nie przypisywać wszystkim osobom identycznego sukcesu jako bezpośredniego dowodu indywidualnej cechy. Właściciel zadania przekazuje udział i jakość atrybucji; §18 może uznać wynik za niejednoznaczny lub niekwalifikujący.

#### H. Inicjatywa — okazja, wykrycie i propozycja

| Wartość | −3 | −2 | −1 | 0 | +1 | +2 | +3 |
|---|---:|---:|---:|---:|---:|---:|---:|
| Mnożnik szansy wykorzystania okazji | 0,70 | 0,80 | 0,90 | 1,00 | 1,10 | 1,20 | 1,30 |

p_final=clamp(p_opportunity_base×M_initiative,0,1). Bazowe30% i+3 daje39%. p_base=0 nadal daje0; cecha nie tworzy problemów ani okazji z niczego.

Właściciel tworzy stabilny opportunity_id z przyczyną, oknem dostępności, wymaganą rolą i kategorią (wykrycie problemu/propozycja usprawnienia). Próba najwyżej raz na person+opportunity. Pierwszy poprawny sukces zamyka wspólną okazję atomowo; kolejne wyniki nie duplikują naprawy, nagrody ani powiadomienia. Współbieżne próby mają deterministyczną kolejność według kontraktu domeny, nie szybkości workerów.

Brak uprawnionej obecności oznacza brak próby, nie porażkę. Porażka oznacza niewykorzystaną okazję, nie awarię. Wykrycie tworzy fakt i propozycję wymagającą normalnej decyzji/budżetu. Nie ma bezpośredniej nagrody pieniężnej, przyrostu skill ani automatycznej naprawy za samo posiadanie inicjatywy. Wewnętrzna nieudana próba jest dostępna do HR wyłącznie, gdy istnieje obserwowalny, uprawniony dowód; nie ujawniamy graczowi ukrytych okazji.

#### I. Łączenie efektów i maksymalny wpływ

Kolejność: dostępność i uprawnienia → bazowy wynik domeny → dokładnie jeden właściwy modyfikator danej metryki → ograniczenie zakresu → jedno losowanie → zapis wyniku → obserwacja HR.

Współpraca dotyczy współdziałania, dokładność błędu wykonania. Jeśli kategoria zadania nie rozróżnia tych wyników, kontrakt wybiera jedną oś; mnożenie obu przez ten sam wskaźnik jest zabronione. Jeśli rozróżnia, definiuje dwa niezależnie identyfikowalne fakty i jedną regułę końcowego skutku, aby nie naliczyć podwójnej straty.

Limity kandydata: na każdą ztrzech metryk zawodowych mnożnik0,70..1,30; podatność nominalna10..20%/30dni przed warunkami. Nie istnieje dodatkowy globalny mnożnik „jakości pracownika”. Brak załogi, brak pieniędzy, nielegalny grafik i brak kwalifikacji mają pierwszeństwo przed bonusami. Dwa korzystne atrybuty mogą pomagać w różnych zdarzeniach, lecz nie dają mnożnika1,30² do produkcji.

Pieniądze nadal rozliczane w jednostkach całkowitych przez właściciela finansów. Prawdopodobieństwa/mnożniki wymagają wspólnej deterministycznej reprezentacji i reguł zaokrąglenia. Wektory zgodności PHP/harness obowiązkowe, szczególnie dla potęg wE.

#### J. Trwałość, wersje i panel

Profil utrwala person_id, kod osi, wartość, generation_version, created_at oraz chroniony identyfikator próby. Cały profil powstaje raz atomowo; nie zapisujemy połowy dwóch cech. Historia korekt append-only, z osobną uprawnioną akcją techniczną, bez zwykłego przycisku „losuj ponownie”.

Zmiana wag generatora działa tylko na nowe osoby. Istniejąca osoba zachowuje wartości. Mapa skutków ma odrębną wersję obowiązującą od daty aktywacji; zmiana mapy nie przelicza wyników przeszłych. Zadanie/okazja/epizod zapisuje wersję użytych reguł. Ciągły proces dzielimy na granicy aktywacji, nie według chwili uruchomienia workera. Migracja dawnych profili wymaga mapy i preview, bez automatycznego losowania braków przy GET.

Panel Kadry → Cechy i generator:
- rozkład K, wagi osi/znaków/intensywności, skale i legalne kombinacje;
- mapy skutków z jednostką, zakresem i właścicielem metryki;
- preview rozkładu i kosztów, porównanie wersji, próbka syntetyczna;
- walidacja osiągalności, neutralności, monotoniczności, duplikatów i limitów;
- historia wersji, aktywacja przyszła zgodnie z bramką balansu, wycofanie przez kolejną wersję.

Parametry ekonomiczne są edytowalne wersjonowanym kandydatem. Nie można wyłączyć izolacji firm, ochrony ukrytych wartości, idempotencji ani nadać klientowi prawa losowania. Główny administrator zatwierdza bez obowiązkowego komentarza; actor, czas, zakres, hash i wynik walidacji zapisują się automatycznie.

Zwykły panel gracza/API nie otrzymuje K, osi, znaku, intensywności, seed ani mnożnika. Nie używamy ich w cenie kandydata, filtrach, kolejności ofert lub opisach zdradzających profil. Oceny zawodowe z rekrutacji pozostają osobnymi dowodami. Rzeczywisty profil dostępny GM wyłącznie przez osobną capability i audyt.

#### K. Scenariusze odbioru i bramki

T01 granice losowania K5999/6000/8999/9000. T02 brak powtórzeń osi i zachowanie K. T03 nielegalny katalog blokuje aktywację. T04 retry/concurrent creation daje jeden profil. T05 rehire/odrzucona oferta nie zmienia wartości.
T06 neutralna osoba może zachorować/popełnić błąd/wykazać inicjatywę. T07 morale nie zmienia podatności. T08 intensywność nie wydłuża choroby ani nie obchodzi cap/cooldown.
T09 dokładność nie zmienia czysto maszynowej awarii. T10 jedna metryka nie przyjmuje dwóch modyfikatorów. T11 brak kwalifikacji nie jest losową próbą.
T12 zespół uwzględnia faktyczny udział, nieobecny ma0. T13 duplikat członka/czasu nie zwiększa bonusu. T14 brak okazji nie daje porażki inicjatywy.
T15 jedna okazja ma jeden końcowy skutek także przy wyścigu workerów. T16 podział Settlement/100GET nie zmienia rozkładu zdarzeń.
T17 nowe wagi nie przerzucają starych profili. T18 aktywacja w połowie procesu zachowuje granicę i wersje. T19 API/cache/export/log/WebSocket/Agencja nie ujawniają profilu.
T20 HR odczytuje tylko dowody; test zawodowy nie ujawnia podatności. T21 błędna migracja nie tworzy drugiej osi. T22 korekta GM audytowana i nie nadpisuje historii.

Harness statystyczny: wiele stałych seedów, porównanie liczności0/1/2 i osi z rozkładem oczekiwanym, monotoniczność map, przebiegi30/90/180dni, podział na etaty/role/warunki, koszty zastępstw i straty. Nie wymagać dokładnie60/30/10 w małej próbie. Liczebność prób, tolerancje i akceptowalne fałszywe oceny HR muszą być zapisane przed uruchomieniem bramki; nie dobierać ich po zobaczeniu wyników.

Status: katalog, kombinacje i siła efektów opisane. Otwarte: HR-TRAITS-CONTRACT (mapa istniejących osi, RNG, kontrakty okazji i wyniku), HR-CFG03 (ekspozycja/warunki/clock absencji), ASSESS-CFG01 (baseline stanowisk), HR-TRAITS-QA (kalibracja i pełne testy). Testy opisane powyżej są wymaganiami, nie deklaracją uruchomienia silnika.


## 17. Szkolenia, mentoring i awanse

Training pozostaje wewnątrz Staff. Wykorzystuje istniejące skills, certyfikaty i dostępność, nie osobny model pracownika.
Katalog kursu: rola/skill, poziom, wymagany poprzedni poziom, minimalne kompetencje, kwalifikowalne umowy, cena, czas, tryb czasu, zajętość, próg zdania, bonusy prób, limity, zasady przerwania, certyfikat i ewentualna ważność.
Zachowujemy kanoniczne kursy/kwoty/capy: Refining20k/50k/100k,24/48/72h, próg70, skill cap10. Ceny czterech kursów bazowych są przyjęte w [DOM06-A](OILEMPIRE_DOM06_KATALOG_SZKOLEN_KANDYDAT.md): 10k/30k/60k PLN; aktywacja czeka na wersjonowany balans i testy.

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


### 18.15. Tabele parametrów kandydata HR-ASSESS-01a

Status: propozycja liczbowa do harnessu, nie aktywny YAML. Doprecyzowuje algorytm §18.7; w tej wersji waga independence_cap zastąpiona jest deterministycznym grupowaniem obserwacji opisanym poniżej. Nie stosować obu mechanizmów jednocześnie.

#### A. Warunki wejścia i jakość próby

| Domena | Preliminary: minimalna próba / ekspozycja | Supported: minimalna próba / ekspozycja | target_count dla Q | Okno aktywnych dni |
|---|---|---|---:|---:|
| Dostępność | 30dni zatrudnienia i80planowanych godzin | 90dni i240h | 30bloków ekspozycji | 180 |
| Dokładność | 10zadań na5dniach | 40zadań na20dniach | 40 | 90 |
| Współpraca | 8zadań na4zmianach | 25zadań na12zmianach i3współpracowników | 25 | 90 |
| Inicjatywa | 10okazji na5zmianach | 30okazji na15zmianach | 30 | 90 |

Dla dostępności brak choroby jest obserwowalnym wynikiem ekspozycji, a nie dowodem ukrytego zdrowia. Q liczymy z bloków ekspozycji, nie z liczby chorób. Jeden blok=UTC dzień z dodatnimi zaplanowanymi godzinami; liczba epizodów pozostaje osobnym ograniczeniem kierunku concern.
Planowane godziny dostępności wyłączają zatwierdzone urlopy/pauzy i okresy poza umową, ale obejmują godziny utracone przez chorobę. Nie wolno usunąć choroby z mianownika.
Liczba epizodów: preliminary concern wymaga2; supported concern3. Spełnienie licznika nie wystarcza bez reszty progów, baseline i kierunku Score.
Progi liczbowe zadania/zmiany odnoszą się do niezależnych źródeł po deduplikacji, przed ułamkowymi wagami. Kwalifikowalność wymaga q≥0,25. Dowód q=0 pozostaje wyłącznie w historii.

#### B. Jakość kontekstu i udział analityka

| Poziom dowodu | q bazowe | Warunek |
|---|---:|---|
| Bezpośredni, zweryfikowany | 1,00 | Wynik/ekspozycja i pełny kontekst, bez istotnego zakłócenia |
| Kontekst kontrolowany | 0,75 | Znany wpływ sprzętu/obciążenia uwzględniony przez zatwierdzony baseline |
| Istotna niejednoznaczność | 0,50 | Część atrybucji niepewna, nadal znany mierzalny wynik |
| Słaby dowód pomocniczy | 0,25 | Referencja lub ocena częściowa z allowlisty; nie chorobowa podatność |
| Niekwalifikujący | 0,00 | Brak baseline, nieznana przyczyna dominująca, uszkodzony lub nieuprawniony dowód |

Jakość1,00 bezpośredniego dowodu nie zależy od skilla HR. HR może poprawić interpretację zakłócenia tylko wtedy, gdy istnieje oznaczony resolvable_context i niezbędne dane.
Kandydat: q_final=min(1,q_base+0,025×analysis_skill), wyłącznie dla resolvable_context i q_base≥0,50. Dla q0/0,25 brak bonusu. Skill zakres0–10; brak uprawnionego analityka nie uruchamia analizy, zamiast przyjmować10.
Przykład q0,50, skill6 i kontekst rozstrzygalny →0,65. Ten sam dowód bez kontekstu →0,50. Brak baseline pozostaje0 nawet przy skill10.
Organizacja wpływa tylko na kolejkę/przepustowość według osobnej polityki; nie podwyższa automatycznie wiarygodności. Koszt i przepustowość wymagają mapy obsady HR przed aktywacją.

#### C. Normalizacja i poziomy odniesienia

Każdy baseline ma: domain,role_code,task_category,difficulty_band,equipment_band,workload_band,metric_code,unit,direction,expected_value,tolerance,normalization_span,valid_from,version,status.
Dopuszczalne difficulty: routine/complex/critical; sprzęt: normal/degraded; obciążenie: nominal/elevated. Granice przedziałów dostarcza właściciel domeny, a nie HR.
Wybór: dokładne dopasowanie klucza. Nie stosować podobnej roli jako cichego fallbacku. Jawna wildcard polityka możliwa tylko po zatwierdzeniu i z oznaczoną niższą jakością.
Baseline powstaje z syntetycznych danych referencyjnych i jest publikowany jako niezmienna tabela. Nie wyliczać go na bieżąco z zachowania graczy, co pozwalałoby zatruć punkt odniesienia.
Evaluator nie zna hidden_trait_value nawet przy doborze baseline.

Dla metryki „mniej znaczy lepiej”:
delta=expected−observed.
Dla „więcej znaczy lepiej”: delta=observed−expected.
residual=0 gdy abs(delta)≤tolerance; poza marginesem residual=sign(delta)×min(1,(abs(delta)−tolerance)/normalization_span).
normalization_span musi być>0. Jednostki observed/expected/tolerance/span identyczne. Przedziały percent zapisujemy jako0..1, nie mieszamy z punktami procentowymi.

Katalog metryk:
- dostępność: utracone godziny chorobowe / planowane godziny ekspozycji, mniej lepiej; liczbę epizodów pokazujemy osobno;
- dokładność: błędy przypisywalne / okazje do błędu, mniej lepiej; wagę ciężkości błędów definiuje właściciel zadania;
- współpraca: potwierdzone udane współdziałania / kwalifikujące interakcje, więcej lepiej;
- inicjatywa: potwierdzone wykorzystane okazje / kwalifikujące okazje, więcej lepiej.
Mianownik0 → brak wyniku. Nie dopisujemy zerowej obserwacji do średniej.

Fixture WYŁĄCZNIE do testów rachunku (nie tabela ekonomii stanowisk):
| Metryka | expected | tolerance | span |
|---|---:|---:|---:|
| Utracone godziny/planowane | 0,02 | 0,01 | 0,10 |
| Błędy/okazje | 0,10 | 0,02 | 0,20 |
| Udana współpraca/interakcje | 0,70 | 0,05 | 0,30 |
| Inicjatywy/okazje | 0,30 | 0,05 | 0,30 |

Nie aktywować fixture jako uniwersalnego baseline. Rzeczywiste tabele dla każdej roli/zadania wymagają danych z kalibracji; brak tabeli daje „brak porównywalnych danych”, nie zgadywany wynik.

#### D. Grupowanie i ochrona przed mnożeniem dowodów

Najpierw scalamy revisions do najnowszej skutecznej wersji źródła i independence_key. Następnie:
- dokładność: jeden klaster person+UTCday+task_category;
- współpraca: person+shift+team_ref;
- inicjatywa: person+shift+opportunity_category;
- dostępność: person+UTCday; godziny są ważoną ekspozycją, epizody deduplikowane niezależnie.
W klastrze średnia residual ważona q i ekspozycją; q klastra=średnia q ważona ekspozycją. Łączna waga klastra≤1. Nie odrzucać arbitralnie pierwszych lub ostatnich zdarzeń.
Q i N_eff liczymy na klastrach. Liczniki wymaganych niezależnych zadań/okazji zachowujemy oddzielnie.40zadań jednego dnia nie daje20dni obserwacji ani wysokiej różnorodności.
Wzór N_eff z §18.7 pozostaje. Jeżeli te capy utrudniają osiągnięcie supported w danych realnego rytmu zadań, kalibracja zmienia wersję target_count, nie omija ograniczenia.

#### E. Różnorodność, aktualność i atrybucja

Q=min(1,N_eff/target_count).
D:
- dostępność=min(1,distinct_exposure_days/90);
- dokładność=min(1,distinct_task_days/20);
- współpraca=min(min(1,distinct_shifts/12),min(1,distinct_partners/3));
- inicjatywa=min(1,distinct_opportunity_shifts/15).
R=średnia q klastra ważona ekspozycją przed zanikiem.
freshness_i=max(0,1−active_age_days/window_days).
A=średnia freshness ważona q przed zanikiem. Nie używać już zanikłych wag do wyliczenia A, co ukrywałoby starzenie próby.
Score=średnia residual z wagami q×freshness, po grupowaniu. Q używa tych samych końcowych wag; D wyłącznie kwalifikujące obserwacje w oknie.
Jeśli suma wag0: etap unassessed, pewność unavailable, direction insufficient.

C=100×(0,35Q+0,25D+0,20A+0,20R).
Porównania na dokładnej wartości; zaokrąglenie wyłącznie do prezentacji GM. Wagi muszą sumować się do1.
Przykład Q0,8,D0,75,A0,9,R0,8 → C80,75. Wysoki wynik C nie uchyla bramek etapu.

Aktualność current wymaga: A≥0,40, zgodnej roli oraz ostatniej kwalifikującej obserwacji nie starszej niż30aktywnych dni dla dostępności albo14 dla pozostałych domen. W innym przypadku stale; ostatni snapshot z datą pozostaje. Próg nie wymusza dopisywania nowego dowodu.

#### F. Kierunek i publikacja oceny

Kandydat:
| Warunek Score | Kierunek |
|---|---|
| Score>0,20 | favourable |
| −0,20≤Score≤0,20 | typical |
| Score<−0,20 | concern |

Sprzeczność ma pierwszeństwo: jeśli≥30% kwalifikującej końcowej wagi ma residual>0,20 i≥30% ma residual<−0,20 → mixed.
Brak baseline/jakości → insufficient. Dla dostępności kierunek concern bez wymaganego licznika epizodów staje się insufficient z opisem faktów, nie typical.
Publiczny favorable dostępności oznacza „dotychczasowa dostępność stabilna”, nie „brak podatności”. Podwyższona nieobecność może pozostać opisowym faktem bez wniosku o cesze.
Etap:
- niespełnione preliminary → unassessed/unavailable;
- spełnione preliminary, ale nie wszystkie supported albo C<50 → preliminary; confidence low przy C<50, medium przy C≥50;
- supported gates spełnione i C≥50 → supported; medium dla C<80, high dla C≥80.
mixed ogranicza confidence do medium. stale ogranicza confidence do low i wyłącza automatyczne rekomendacje oparte na aktualnej ocenie, ale nie usuwa historii.
Etap nie zmienia się w połowie wyliczenia: jeden immutable wynik publikowany atomowo wraz z wersją metody i opisem podstaw.

#### G. Wersjonowanie i panel

Wersjonowane tabele: stage_thresholds, evidence_quality_rules, hr_context_bonus, baseline_catalog, grouping_rules, confidence_weights, direction_thresholds, freshness_rules, translation_templates.
Edytor wymaga jednostek i zakresów: min_count≥1, exposure≥0, q/weights0..1, low_threshold<high_threshold, positive_span>0, sum(weights)=1. Progi supported nie mogą być słabsze niż preliminary w tej samej miarze.
Nie wolno konfigurować odczytu hidden_traits jako źródła ani wyłączać ownership/deduplikacji. Przyciski: Klonuj wersję, Waliduj, Porównaj, Symuluj na fixture, Preview skutków, Zatwierdź zgodnie z bramką balansu.
Preview pokazuje rozkład etapów/kierunków, liczby brakujących baseline, zmianę względem poprzedniej metody i przypadki graniczne. Nie nadpisuje ocen i nie zmienia osoby.
Główny administrator bez obowiązkowego komentarza. Audit: actor/version/hash/scope/time i wynik walidacji automatycznie.
Plan wdrożenia: wypełnić baseline rzeczywistych kategorii, uruchomić harness, ustalić limity mylnych alarmów, dopiero aktywować. Sam poprawny wzór nie potwierdza jakości predykcji.

#### H. Przykłady i kontrola

Fixture accuracy: observed0,04, expected0,10, tolerance0,02, span0,20 → residual+0,20; observed0,18 →−0,30; observed0,11 →0.
Fixture availability:8utraconych godzin/160planowanych=0,05 → residual−0,20. Jeden epizod nie daje concern niezależnie od wyniku.
Wagi klastrów[1,1,0,5] → N_eff=6,25/2,25=2,777…; nie3pełne obserwacje.
Przy residual[+0,6,−0,6] i równych wagach → mixed, nie typical mimo średniej0.
Przy C80,75 i niepełnym supported gate → preliminary/medium, nie supported/high.
Przy q0 i skill10 → nadal q0. Przy braku okazji → insufficient. Granice Score±0,20 należą do typical.

Status ASSESS-CFG01: algorytm i kandydat progów szczegółowo opisane, produkcyjne baseline ról oraz kalibracja nadal otwarte. ASSESS-CONTRACT01 i ASSESS-QA01 pozostają otwarte. Niniejsza tabela nie jest zmianą aktywnego game_balance.


### 18.16. Normy obserwacji stanowisk — HR-ROLE-BASELINES-01

Status: katalog wykonawczy metryk i warunków porównania, kandydat do kalibracji. Nie ustala jeszcze produkcyjnych expected_value/tolerance/normalization_span. Wartości fixture z §18.15C pozostają przykładami rachunku. Normy nie dodają nowych etatów ani nowych obowiązkowych zadań do gospodarki.

Powiązania: §16.1 (rzeczywiste skutki cech), §18.5–18.15 (dowody i oceny), architektura §3, §6, §9, §11, §11A i §31. Nazwy stanowisk poniżej muszą zostać zmapowane na istniejące role_code przed implementacją; identyfikatory kategorii są propozycją kontraktu.

#### A. Co oznacza norma

Norma opisuje oczekiwany obserwowalny wynik porównywalnej pracy osoby z neutralnymi nowymi osiami szczególnymi, przy określonych kwalifikacjach, sprzęcie, obciążeniu i trudności. Nie jest normą produkcji na etat ani automatycznym wymogiem zatrudnienia.

Rozdzielamy:
1. Wynik operacyjny: np. transport dotarł, naprawa zakończona, raport zatwierdzony.
2. Możliwy do przypisania wynik pracownika: np. potwierdzony błąd wykonania.
3. Kontekst: np. awaria maszyny, polecenie właściciela, pogoda lub brak surowca.
4. Wniosek HR: porównanie kwalifikującego wyniku z właściwą normą.

Mniejszy wolumen produkcji, niższy zysk, brak wygranej w przetargu czy sama awaria nie są dowodem złej dokładności. Nieobecność wyłącza okazję zawodową; nie zapisujemy wtedy zera jako nieudanego zadania. Dostępność oceniana jest oddzielnie według §18.15.

#### B. Wspólny format metryk

| Domena | Licznik | Mianownik | Kierunek |
|---|---|---|---|
| Dokładność | Zamknięte kwalifikujące jednostki z co najmniej jednym potwierdzonym błędem przypisywalnym wykonawcy | Wszystkie zamknięte porównywalne jednostki, także bez błędu | Mniej lepiej |
| Współpraca | Potwierdzone udane współdziałania | Zamknięte, obserwowalne interakcje z jawnym kryterium sukcesu | Więcej lepiej |
| Inicjatywa | Wykorzystane, obserwowalne okazje | Wszystkie kwalifikujące i obserwowalne okazje, także niewykorzystane | Więcej lepiej |
| Dostępność | Planowane godziny utracone przez chorobę | Planowane godziny ekspozycji według §18.15A | Mniej lepiej |

Pierwsza wersja dokładności używa binarnego błędu jednostki: trzy usterki tej samej czynności to1 jednostka z błędem, nie3 niezależne zadania. Kwota straty i ciężkość incydentu są oddzielnym faktem operacyjnym. Ważone ciężkością wskaźniki wymagają odrębnego metric_code/baseline; nie mieszamy ich z odsetkiem0..1.

Nie zbieramy tylko porażek ani tylko sukcesów: bez pełnego mianownika wynik nie kwalifikuje się do porównania. Korekta faktu zmienia rewizję źródła, nie liczbę prób. Licznik nigdy nie przekracza mianownika.

#### C. Katalog stanowisk technicznych i operacyjnych

| Stanowisko / źródło normy | Kategoria jednostki dokładności | Dowód współpracy | Okazja inicjatywy | Wyłączenia i ograniczenia |
|---|---|---|---|---|
| Operator odwiertu — Extraction | well_operation: zamknięty blok obsługi z kontrolą czynności; potwierdzony błąd obsługi | Przekazanie zmiany lub współpraca przy interwencji technika | Udokumentowany sygnał wymagający zgłoszenia w czasie obsady | Wyczerpanie złoża, cena ropy, brak odbioru i czysto maszynowa awaria nie są błędem operatora |
| Technik utrzymania — Technical | maintenance_acceptance: zadanie serwisowe i wynik odbioru | Wspólne przygotowanie/zabezpieczenie obiektu i przekazanie do ruchu | Wykryta podczas uprawnionej inspekcji potrzeba dodatkowego serwisu | Brak części/środków, wada dostarczonej części i obce uszkodzenie nie są automatyczną winą technika |
| Inżynier rurociągów — Technical/Logistics, zgodnie z właścicielem zadania | pipeline_task: odbiór inspekcji/naprawy konkretnego odcinka | Potwierdzone uzgodnienie odcięcia/przekazania odcinka | Obserwowalny symptom nieszczelności lub potrzeby przeglądu | Nie przypisywać całej straty sieci jednej osobie; sabotaż i zużycie wymagają własnej atrybucji |
| Operator huba — Logistics | hub_operation: zamknięta operacja przyjęcia/przekazania partii albo blok nadzoru, osobne baseline | Przekazanie partii kierowcy/operatorowi kolejnej zmiany | Rozpoznawalna niezgodność lub ryzyko przeciążenia w obsługiwanym hubie | Brak miejsca, złe polecenie routingu i brak dostaw nie oznaczają błędu; bonus tylko dla przypisanego huba |
| Kierowca — Logistics | road_delivery: zakończony kurs z potwierdzeniem ładunku i rozładunku | Potwierdzone przejęcie/oddanie ładunku lub zmiana kierowcy | Zaobserwowany sygnał nieprawidłowości pojazdu/ładunku wymagający zgłoszenia | Samo opóźnienie, pogoda, zamknięta droga, awaria i sabotaż nie przesądzają winy; nie premiować przekraczania norm jazdy |
| Operator rafinerii — Refining | refinery_operation: blok obsługi konkretnej instalacji i zatwierdzonej receptury | Przekazanie zmiany lub wykonanie uzgodnionej procedury z chemikiem | Dostępny operatorowi sygnał odchylenia procesu | Cena produktów, zamówiona receptura, brak wsadu i zapełnienie zbiornika z decyzji gracza nie są automatyczną winą |
| Inżynier chemik procesowy — Refining | process_review: zamknięta analiza/procedura z udokumentowanym wynikiem kontroli | Uzgodnienie i przekazanie zaleceń operatorom | Potwierdzona możliwość usprawnienia dopuszczona przez katalog procesu | Nie oceniać na podstawie późniejszej zmiany rynku; brak zgody na propozycję nie oznacza braku inicjatywy |
| Inspektor BHP — właściciel procesu HSE | hse_inspection: odbiór kompletnej inspekcji według checklisty | Potwierdzone przekazanie zaleceń i warunków bezpiecznej pracy | Obserwowalne zagrożenie wymagające eskalacji | Zero wypadków nie dowodzi poprawnej inspekcji; ujawnione zagrożenie nie jest porażką inspektora |
| Geolog wykonujący badanie — Extraction | geological_survey: zamknięte badanie z uprawnioną niezależną weryfikacją | Przekazanie danych zespołowi wierceń/eksploatacji | Obserwowalna przesłanka dodatkowego badania | Brak odkrycia opłacalnego złoża nie jest błędem; niedostępna prawda o złożu nie może wyciec przez ocenę HR |

Bloki obsługi są proponowaną reprezentacją obserwacji, nie nowymi płatnymi zadaniami. Jeśli właściciel domeny nie generuje kontroli wykonania i atrybucji, dany wiersz pozostaje nieaktywny: sam upływ czasu nie daje „bezbłędnego zadania”.

#### D. Specjaliści i zarząd

| Rola | Jednostka dokładności / właściciel faktu | Współpraca i inicjatywa | Czego nie oceniamy jako cechy |
|---|---|---|---|
| Dyspozytor/logistyk | dispatch_plan: zatwierdzony plan i weryfikacja ograniczeń przez Logistics | Uzgodnione przekazanie planu; wskazanie wykonalnej alternatywy | Losowa blokada trasy; odmowa gracza; tańsza trasa nie jest zawsze lepsza |
| Analityk | analysis_report: raport z zamkniętym okresem, wersją danych i kontrolą poprawności przez moduł zlecający | Przekazanie analizy; wykrycie sprawdzalnej rozbieżności | Trafność prognozy rynku bez osobnej skalibrowanej metryki; sama rentowność firmy |
| Dyrektor techniczny | technical_decision: uprawniona decyzja/procedura z kontrolą zgodności | Koordynacja interwencji; udokumentowana propozycja planu serwisu | Wszystkie awarie firmy niezależnie od przyczyny |
| Dyrektor finansowy | finance_decision: kontrola wyliczeń i zgodności mandatu przez Finance | Uzgodnienie budżetu; wskazanie rozpoznawalnej luki płynności | Wahania cen, brak gotówki po decyzji właściciela, spekulacyjny zysk/strata |
| Dyrektor prawny | legal_case_review: zamknięta kontrola kompletności i terminów przez Legal | Przekazanie dokumentów; wykrycie obserwowalnego braku | Przegrana sprawa/odmowa pozwolenia jako samodzielny dowód niekompetencji |
| Dyrektor logistyki | logistics_decision: zamknięty plan zasobów z kontrolą wykonalności | Uzgodnienia między zespołami; propozycja usunięcia rozpoznawalnego zatoru | Każde opóźnienie podległych kierowców |
| Dyrektor HR / uprawniony analityk HR | hr_procedure: kontrola zgodności procesu z polityką przez Staff | Przekazanie rekrutacji/grafiku; wykrycie jawnej luki obsady | Zgodność oceny pracownika z jego sekretnym profilem; „zatrudnił podatną osobę” |

Nie tworzymy osobnego stanowiska analityka HR bez mapowania ról z §18.8. To funkcja wymagająca kwalifikacji i mandatu. Nie generujemy samooceny z własnego snapshotu HR. Kontrola procesu HR jest niezależnym faktem deterministycznej walidacji/audytu, bez dostępu do ukrytych cech; nie uruchamia rekurencyjnej oceny ocen.

Każda aktywna rola musi mieć jawne pokrycie: obsługiwane kategorie albo not_supported z przyczyną. Nowy role_code bez normy nie dziedziczy automatycznie normy „podobnego” pracownika.

#### E. Jednostki, czas i dowody niezależne od odczytów

Zadanie skończone: jedna jednostka source_task_id+acceptance_revision, liczona po ostatecznym wyniku. Nie liczymy rozpoczęcia i zakończenia jako dwóch sukcesów. Powtórna kontrola tej samej pracy koryguje źródło, chyba że domena potwierdziła rzeczywiście nowe zadanie z nowym zakresem.

Proces ciągły: kandydat bloku obserwacyjnego to stałe8h UTC [00,08),[08,16),[16,24), oddzielnie od godzin grafiku. To siatka pomiaru, nie zmiana zatwierdzonych zmian06–14/14–22/22–06. Kontrakt klucza: person+asset+task_category+block_start. Segmenty Settlement łączymy w jeden blok, zachowując faktycznie wykonaną ekspozycję. Zmiana grafiku lub request nie tworzy nowego bloku.

W bloku częściowym nie zakładamy pełnych8h; zapisujemy work_seconds. Normę dobieramy do zatwierdzonego zakresu ekspozycji. Mniej niż zatwierdzone minimum daje insufficient_exposure, nie porażkę. Minimum i przedziały ekspozycji wymagają kalibracji kategorii. Zmiana roli, metody lub istotnego kontekstu wewnątrz bloku zapisuje segmenty pod jednym kluczem niezależności; brak zatwierdzonego agregatora daje niekwalifikujący blok, bez uśredniania różnych norm.

Liczba jednostek zadaniowych i klastrów pozostają oddzielne (§18.15D). Podział czynności przez gracza nie może mnożyć dowodów: owner nadaje stabilny independence_key dla zakresu zlecenia. Dwa assety nie oznaczają dwóch dowodów tego samego przekazania.

Współpraca wymaga co najmniej dwóch rzeczywistych uczestników; automatyczne potwierdzenie przez system nie udaje drugiego pracownika. Zdarzenie zespołowe wspólne, ale atrybucja osoby i udział osobne. Brak indywidualnej atrybucji nie daje q1.

#### F. Dobór porównywalnego baseline

Rozszerzenie klucza z §18.15C:
domain + role_code + task_category + metric_code + difficulty_band + equipment_band + workload_band + qualification_band + exposure_band + measurement_method_version.

qualification_band wyznacza właściciel na podstawie jawnych umiejętności i certyfikatów istotnych dla zadania. To konieczne, aby niższy skill nie udawał ukrytej niedbałości. Nie korzystamy z ukrytej accuracy/cooperation/initiative, podatności, lojalności ani prywatnej oceny innej firmy. Zmiana umiejętności daje nowy kontekst przyszłej obserwacji; nie przepisuje starego faktu.

Dla współpracy baseline uwzględnia jawny kontekst zespołu (liczba osób, kwalifikacje, rodzaj współdziałania) przez wersjonowany team_context_band. Bez tego nie porównujemy dwuosobowego przekazania z dużą akcją remontową. Inicjatywa wymaga opportunity_category oraz wykrywalności na podstawie informacji dostępnych w danym momencie.

Nie tworzymy iloczynu wszystkich kombinacji „na zapas”. Właściciel deklaruje legalne konfiguracje i coverage manifest. Każda rzeczywiście obsługiwana kombinacja musi mieć dokładnie jedną aktywną normę albo jawny brak. Nieznany band, brak wpisu i dwa pasujące wpisy blokują ocenę tej kategorii, nie operacje gospodarcze.

Definicje bandów pochodzą z katalogu właściciela i muszą zawierać jednostkę, granice i zachowanie na granicy. routine/complex/critical nie są dowolnym tekstem nadawanym przez HR. Wskaźnik sprzętu/obciążenia z chwili pracy, nie z aktualnego stanu przy otwarciu profilu. Nie nadawać difficulty po zobaczeniu sukcesu/porażki.

#### G. Inicjatywa bez ujawniania ukrytych okazji

Generator domeny i HR mają inne uprawnienia. To, że silnik zna niewykorzystaną okazję, nie oznacza, że HR może ją zaliczyć do mianownika.

Do oceny trafia tylko okazja z niezależnym dowodem, że odpowiedni sygnał był dostępny osobie, oraz późniejszym legalnym potwierdzeniem wyniku. Jeżeli rejestrowane są wyłącznie zgłoszone sukcesy, cała taka kategoria nie nadaje się do oceny proporcji. Nie prezentować100% inicjatywy z trzech sukcesów i nieznanej liczby okazji.

Zamknięcie wspólnej okazji przez inną osobę nie tworzy porażki tych, którzy nie zdążyli wykonać swojej kwalifikującej próby. Kontrola musi odróżniać attempted, not_exposed, closed_by_other i unobservable. Tylko pierwsze z obserwowalnym wynikiem wchodzi do mianownika; bias kolejności prób uwzględnia kalibracja.

Dla geologa, HSE i podobnych ról niedostępna graczowi prawda nie trafia do message_params, licznika ocen ani filtrów. Odkrycie/weryfikacja może później dostarczyć dowód z zachowaniem daty pracy i daty poznania. Ocenę liczymy według czasu pracy; spóźniona rewizja nie odmładza dowodu.

#### H. Pozyskanie liczb i walidacja norm

Dla każdej legalnej komórki katalogu:
1. Właściciel domeny dostarcza syntetyczny scenariusz, neutralne nowe osie, kwalifikacje i kontekst.
2. Harness wykonuje próbę z wieloma zapisanymi seedami, wersją modelu i pełnym mianownikiem.
3. Osobna próba walidacyjna używa innych seedów/scenariuszy, również cech±1..3, zakłóceń i zmian obsady.
4. Raport zawiera sample_size, ekspozycję, częstość metryki, niepewność estymacji, rozkład residual i odsetek ocen HR.
5. Dopiero zatwierdzony rekord otrzymuje expected_value, tolerance, normalization_span, dataset_hash, method_version, valid_from i status active.

Nie ustawiamy wspólnego „10% błędów” dla kierowcy, chemika i dyrektora. Tolerancja nie może być dobrana po wyniku tak, aby wszystkie fixture przechodziły. Kryteria akceptacji/statystyczne liczebności ustalamy przed kalibracją. Mała liczba rzadkich błędów nie uzasadnia dokładnej normy; taki rekord pozostaje draft/insufficient_sample.

Normy po publikacji niezmienne. Nowa wersja domeny, która zmienia wynik, wymaga sprawdzenia ważności baseline. Zmiana cen rynkowych sama w sobie nie przelicza jakości osoby. Brak aktualnej normy daje komunikat o ograniczeniu porównania, nie ocenę negatywną.

#### I. Przykłady interpretacji i porównywania

- Kierowca:12 zamkniętych kursów,1 potwierdzony błąd wykonania →1/12≈8,33%. Dwa opóźnienia pogodowe nie zwiększają licznika. Bez normy dla tego rodzaju kursów brak wniosku favourable/concern; nadal obowiązują dni/próba z §18.15.
- Technik:5 odrębnych odbiorów, jeden zawiera3 usterki przypisywalne wykonaniu →1/5=20%, nie3/5. Pięć ponownych odczytów protokołu nadal daje5 jednostek.
- Operator: spadek wydobycia przez wyczerpanie złoża → brak dowodu błędu. Prawidłowo obsłużony blok daje wynik tylko przy istniejącym kontrakcie kontroli wykonania.
- Inicjatywa:3 zgłoszenia i brak rejestru niewykorzystanych, obserwowalnych okazji → brak metryki proporcji. Przy10 pełnych kwalifikujących okazjach i3 sukcesach →30%.
- Współpraca:6 udanych z8 obserwowalnych interakcji →75%. Sam fakt wspólnego przydziału do zmiany nie jest interakcją.
- Ta sama osoba po kursie: obserwacje sprzed kursu korzystają z dawnego qualification_band, po kursie z nowego. Nie poprawiamy historycznej dokładności przez aktualizację skilla.

Przykłady sprawdzają interpretację liczników, nie zatwierdzają norm ekonomicznych.

#### J. Panel, widok gracza i kontrakt odbioru

Panel Kadry → Normy stanowisk: macierz rola/kategoria/domena, właściciel źródła, jednostka, kryteria kwalifikacji, klucz baseline, kompletność pokrycia, wersja i wynik kalibracji. Widoki: brakujące normy, konflikty dopasowania, zbyt mała próba, nieaktywny producer, zmieniona wersja domeny.

Edycja liczb i bandów w kandydacie; preview na zapisanych syntetycznych scenariuszach; aktywacja zgodna z bramką balansu. Główny administrator bez obowiązkowego komentarza; audyt automatyczny. Formularz nie przyjmuje dowolnego SQL/PHP ani formuły wykonywanej jako kod. Nowy typ metryki wymaga zarejestrowanego adaptera.

Gracz widzi ocenę danej domeny, okres, liczbę obserwacji i opis ograniczeń, np. „Brak wystarczających porównywalnych odbiorów napraw”. Brak jednej kategorii nie zeruje ocen pozostałych; zbiorczy widok oznacza zakres częściowy i nie twierdzi, że opisuje całość pracy. Nie zestawiamy surowych odsetków różnych ról w rankingu pracowników. SVG, PL/EN/DE, bez okien systemowych, zgodnie z §12 i §18.12.

Wymagany payload właściciela: source/revision, person/company, role/task/metric, independence_key, work_period, observed_at, exposure, numerator/denominator, context bands/version, attribution_reason, observability status i metoda kontroli. Szczegóły wewnętrzne mogą być chronione; publiczny DTO pozostaje allowlistą §18.11. HR nie odpytuje prywatnych tabel innych modułów.

#### K. Testy i pozostałe bramki

B01 pełny mianownik sukcesów i porażek. B02 zero okazji→insufficient. B03 trzy usterki jednego zadania→jedna jednostka. B04 pogoda/wyczerpanie/ceny nie stają się błędem pracownika.
B05 baseline uwzględnia kwalifikacje, bez hidden profile. B06 nowy skill nie zmienia starego kontekstu. B07 brak i wieloznaczność baseline blokują tylko ocenę danej kategorii.
B08 GET/job/sweeper i segmentacja czasu dają te same jednostki. B09 częściowa obsada nie daje pełnej ekspozycji. B10 brak kontroli wykonania nie generuje sztucznych sukcesów.
B11 brak partnera nie tworzy współpracy. B12 sukces zespołu nie daje każdemu bezpośredniej atrybucji. B13 zamknięcie okazji przez inną osobę nie tworzy fikcyjnej porażki.
B14 same sukcesy bez mianownika blokują metrykę. B15 ukryte złoże/okazja nie wycieka przez oceny. B16 późne odkrycie nie odmładza dowodu.
B17 korekta źródła i nowa wersja normy zachowują historię. B18 brak rekurencyjnej samooceny HR. B19 nieznany role_code nie dziedziczy normy.
B20 panel waliduje legalne komórki, zakres0..1, dodatni span, hash i kompletność. B21 preview nie zmienia gry. B22 komunikaty PL/EN/DE i brak wycieku w eksporcie/cache/filtrach.

Domknięte dokumentacyjnie: katalog obserwacji, jednostki, zasady przypisania, dobór porównywalnych norm i procedura kalibracji. Otwarte: mapowanie role_code/producerów i DDL/OpenAPI, liczbowe bandy/ekspozycja oraz rzeczywiste expected/tolerance/span z harnessu. Nie oznaczać ASSESS-CFG01 ani ASSESS-QA01 jako zakończonych na podstawie samego opisu. Żaden z testów B01–B22 nie jest tu deklarowany jako uruchomiony test Laravel.



### 18.17. Organizacja działu HR — HR-OPS-01

Status: kandydat reguł gry do kalibracji; dokumentacja, bez aktywacji balansu i implementacji. Rozwija §18.8; nie zmienia progów poznawania pracownika z §18.15 ani ochrony ukrytego profilu.

#### A. Obsada i mandat

Analizę może wykonywać dyrektor HR lub pracownik z istniejącej rodziny analityków, któremu nadano funkcję analityka HR. Nie tworzymy nowego duplikatu umiejętności: używamy skill_analysis i skill_organization. Mapa role_code → dopuszczalna funkcja musi zostać zatwierdzona przed aktywacją; nie każdy analityk ma automatyczny dostęp do kadr.

Warunki: aktywna umowa, uprawniona rola, przydział do HR, dodatnia dostępność, brak kolizji z urlopem, chorobą, strajkiem, szkoleniem i inną pracą. Dyrektor HR nie jest obowiązkowy do działania podstawowych kadr. Właściciel firmy zarządza personelem przez UI, ale jego konto nie jest darmowym pracownikiem wykonującym analizy.

Gracz ustala udział czasu na analizy0–100% i priorytet względem pozostałych obowiązków. Rezerwacje obejmują rzeczywisty grafik. Kandydat domyślny przy włączeniu funkcji:25%, wymagający zatwierdzenia planu. Przydział nie jest dodatkowym etatem. Nie można wykorzystać tej samej godziny na analizę, rekrutację i szkolenie.

#### B. Jednostka pracy i czas

Jedno zlecenie dotyczy jednej osoby, jednej domeny oceny i jednego slotu analizy. Profil czterech domen może wymagać czterech zleceń; zbiorcze kliknięcie nie daje darmowych analiz.

| Parametr kandydata | Wartość |
|---|---:|
| Pierwsza analiza domeny | 30min pracy przy organizacji0 |
| Aktualizacja istniejącej oceny | 15min pracy przy organizacji0 |
| Mnożnik czasu | 1−0,03×skill_organization |
| Zakres organizacji | 0–10 |
| Minimum wynikające z tabeli | 21min pierwsza /10,5min aktualizacja |
| Równoległe aktywne analizy jednej osoby HR | 1 |
| Maksimum regularnych publikacji person+domain | 1 na aktywny slot dobowy |

Czas jest nakładem pracy w grze, nie czasem wykonywania kodu PHP. Przechowujemy wymagane sekundy i wykonane sekundy; nie zaokrąglamy10,5min do11min. Organizacja nie zwiększa pewności oceny. Analiza wpływa na jakość kontekstu wyłącznie zgodnie z §18.15B, bez nowych bonusów.

Skill i wersję polityki czasu utrwalamy przy rozpoczęciu pracy. Po kursie nowe zlecenia korzystają z nowej wartości. Brak baseline lub wymaganych dowodów wykrywany bezpłatnym preflightem: zlecenie oczekuje na dane, nie zajmuje analityka.

Przykład:8h zmiany,25% czasu na analizy =120min. Przy organizacji0 wystarczą na4 pierwsze analizy albo8 aktualizacji. Przy organizacji10 pierwsza analiza trwa21min:5 zakończonych i15min postępu szóstej. Pół etatu z4h pracy i25% przydziału daje60min. To pojemność teoretyczna przed absencjami i innymi rezerwacjami, nie gwarancja terminów.

#### C. Koszt i budżet

Własny analityk w nominalnym czasie jest opłacany przez zwykłe płace. Zlecenie nie nalicza drugiej pensji ani opłaty za odczyt. HR może pokazać informacyjny koszt przypisany do czasu: pełny koszt godzinowy z wersjonowanej polityki płac × rzeczywiste godziny analizy. Ten koszt nie jest nowym zapisem obciążenia.

Nadgodziny tylko po zgodzie, przy legalnym grafiku i budżecie zgodnie z §4.1. Umowa godzinowa rozlicza faktycznie wykonany czas raz. Brak przydziału do analiz nie znosi pensji miesięcznej.

Przykład informacyjny: pełny koszt100 jednostek waluty/h, analiza30min →50; aktualizacja15min →25. Nie zmienia to istniejących stawek stanowisk. Koszt analiz zewnętrznych pozostaje w przyszłym module Agencji; HR-OPS-01 nie zamawia automatycznie płatnej usługi.

#### D. Kolejka i priorytety

Kolejka biznesowa jest w bazie Staff. Kolejka Laravel jedynie wybudza wspólną procedurę; nie jest źródłem czasu pracy, dostępności ani kolejności. Job, odczyt leniwy i sweeper muszą odtworzyć identyczny wynik.

Kandydat priorytetów:0 korekta nieważnej podstawy;1 pierwsza kwalifikująca analiza;2 aktualizacja nieaktualnej oceny;3 zwykłe nowe dowody. Ręczne żądanie trafia do odpowiedniej klasy, nie omija limitu dobowego. Gracz może ustawić kolejność osób w tej samej klasie.

Ochrona przed zagłodzeniem: effective_priority=max(0,base_priority−floor(ready_wait_active_days/7)). Czas oczekiwania liczymy tylko w ready/queued, nie waiting_evidence. Kolejność: effective_priority, ready_at, ręczny ranking dla jednakowego ready_at, stabilny id. Priorytet nie przerywa już wykonywanej analizy; służy wyborowi następnej.

Najwyżej jedno niezakończone zlecenie person+domain w firmie. Kolejne dowody ustawiają dirty/latest_requested_version, zamiast dodawać duplikat. Limit aktywnych prac = liczba rzeczywiście dostępnych uprawnionych analityków. Nadmiar oczekuje bez opłat; backlog nie usuwa zleceń.

#### E. Stany i przebieg

Proponowane stany: waiting_evidence, queued, reserved, working, paused, ready_to_compute, computing, published, cancelled, failed. Techniczny retry nie jest nową pracą biznesową.

1. Trigger sprawdza ownership, zakres, slot i istnienie zmian; bez zmian zwraca poprzedni wynik.
2. Preflight sprawdza kompletność norm/dowodów. Brak danych daje przyczynę waiting_evidence.
3. Planista wybiera dostępnego analityka i rezerwuje rzeczywisty czas.
4. Settlement rozlicza nakład wyłącznie w legalnych, dostępnych przedziałach.
5. Po wykonaniu nakładu utrwalamy snapshot wejść i HR context, zlecając obliczenie.
6. Publikacja zgodna z §18.10: sprawdzenie aktualności, CAS, outbox i historia atomowo.

Pauza firmy/koniec zmiany/absencja zatrzymują postęp; wznowienie zachowuje wykonany czas. Nie tworzymy automatycznych nadgodzin. Zmiana analityka przed ukończeniem: zachowujemy wykonany nakład, a wymagany całkowity czas przeliczamy według nowego analityka i tej samej wersji polityki, nigdy poniżej już wykonanej pracy. HR context jakości pochodzi od analityka kończącego i jest jawny w audycie. Takie przekazanie wymaga realnej rezerwacji co najmniej5min pracy nowej osoby; koszt czasu przekazania osobny od nakładu, bez dodatkowego bonusu pewności.

Zwolnienie ocenianego pracownika anuluje nieukończone zlecenia aktualnej oceny, zachowując fakty, postęp historyczny i należne płace. Rehire nie wznawia automatycznie starej rezerwacji. Utrata uprawnień analityka blokuje publikację i wymaga przekazania uprawnionej osobie.

#### F. Sloty, nowe dane i retry

Sloty używają istniejącego aktywnego zegara firmy i trwałej definicji doby z §18.8; klient nie wyznacza as_of. Analiza ukończona później korzysta z najnowszego osiągalnego slotu na moment utrwalenia wejść. Nie odtwarzamy wszystkich zaległych ocen dzień po dniu.

Zmiana wejść w computing: wynik nie zastępuje nowszego; jedna scalona aktualizacja czeka na przeliczenie. Sam techniczny retry/correction input race nie nalicza ponownie nakładu pracy. Nowe dowody po skutecznej publikacji wymagają zwykłej aktualizacji w kolejnym dozwolonym slocie.

Korekta źródła może od razu oznaczyć ocenę jako nieaktualną/wycofaną z rekomendacji. Nie musi czekać na analityka. Nie publikuje jednak drugiej regularnej oceny tego samego dnia. Wygaśnięcie ważności również działa bez analityka jako deterministyczna bramka świeżości, nie jako nowa analiza.

Zadanie techniczne ma retry z backoff i limit prób według wspólnej polityki kolejki. Po wyczerpaniu failed z alertem operatora i możliwością wznowienia tego samego zlecenia. Awaria infrastruktury nie obciąża ponownie budżetu gracza i nie produkuje fikcyjnych godzin pracy.

#### G. Firma bez HR

Nadal działają: zatrudnianie, umowy, płace, grafiki, urlopy, absencje, zakończenie współpracy, rejestrowanie zdarzeń i podstawowe powiadomienia. Automatyczne układanie legalnego grafiku nie wymaga Agencji ani analityka.

Gracz widzi fakty i ostatnie oceny z datą/aktualnością. Nowe oceny czekają; komunikat „Brak dostępnego analityka HR”. Nigdy nie podstawiamy skilla10, nie ujawniamy profilu i nie kasujemy dowodów. Sam brak HR nie obniża morale ani produktywności. Po przydzieleniu analityka kolejka analizuje bieżące dane i publikuje scalone powiadomienia.

#### H. Panel i interfejs

Gracz: obsada HR, przydział czasu, lista analiz, przyczyna oczekiwania, wykonany/wymagany nakład, przewidywane okno ukończenia, ostatnia ocena i jej aktualność. ETA jest prognozą opartą na grafiku, nie obietnicą; brak grafiku daje „termin nieustalony”. Akcje: zleć, ustaw kolejność, zmień przydział, anuluj niewykonaną część. Wszystko autoryzowane serwerowo.

Admin: mapowanie ról, czas pierwszej/aktualizacji, wpływ organizacji, limit publikacji, priorytety i starzenie kolejki, minimalny czas przekazania, katalog przyczyn i powiadomień. Wersjonowane kandydaty, preview kosztu/przepustowości i bramka balansu. Główny administrator bez obowiązkowego komentarza; automatyczny audyt pozostaje.

Niezmienniki niewyłączalne: brak podwójnego czasu/pensji, ownership, deduplikacja, ochrona profilu, legalna dostępność i niezmienna historia. Komunikaty SVG, PL/EN/DE, własne komponenty zamiast okien systemowych.

#### I. Dane, testy i gotowość

Zlecenie przechowuje company/person/domain, slot, status, priority, ready_at, policy_version, required/worked_seconds, analyst_ref, reservation_ref, input_hash, dirty flag, publication_ref i state_version. Rezerwacje/czas pracy korzystają ze wspólnego kalendarza Staff. Oddzielić rekord pracy biznesowej od staff_assessment_runs. Indeksy kolejki i unikalność niezakończonego zlecenia wymagają projektu MySQL, bez zakładania częściowych indeksów PostgreSQL.

O01 brak HR nie blokuje płac/grafiku. O02 realny przydział ogranicza pojemność. O03 brak podwójnego przydziału. O04 koniec zmiany/choroba zatrzymują nakład.
O05 retry nie nalicza ponownie czasu/pieniędzy. O06 brak baseline nie zużywa analityka. O07 priorytety i starzenie deterministyczne.
O08 nowe dowody scalają się. O09 max jedna publikacja na slot. O10 spóźniony worker nie nadpisuje aktualnego snapshotu.
O11 korekta/utrata świeżości blokują rekomendację bez HR. O12 brak drugiej pensji. O13 brak samowolnych nadgodzin.
O14 rehire nie wznawia starej rezerwacji. O15 przekazanie wymaga czasu i nowego HR context. O16 nieuprawniony analityk nie publikuje.
O17 długi offline daje jeden stan docelowy. O18 techniczna awaria nie tworzy godzin pracy. O19 izolacja firm i brak profilu w DTO. O20 panel/ETA/tłumaczenia/mobile.

Do wykonania: DDL/OpenAPI i mapa ról, dokładna integracja rezerwacji z Settlement, kalibracja nakładu dla firm różnej wielkości oraz testy O01–O20. Liczby w tabeli są kandydatem, nie wynikiem testów wydajności. Zmiana dotyczy dokumentacji; nie uruchamia płac, kolejek ani produkcyjnych analiz.



### 18.18. Wykorzystanie istniejącej bazy — HR-DB-REUSE-01

Decyzja właściciela: najpierw wykorzystujemy i rozszerzamy istniejące struktury. Nazwy staff_* z wcześniejszych rozdziałów opisują odpowiedzialności logiczne, nie obowiązek utworzenia równoległego kompletu tabel.

#### A. Zakres dowodów i ograniczenia

Przegląd statyczny 18.11.2026, lokalny checkout OIL, HEAD bbe6a761c9d387162e077d93e61058a372cb42e9, z istniejącymi niezwiązanymi zmianami użytkownika. Nie zmieniano kodu ani bazy. To nie audyt aktualnego schematu produkcyjnego.

Odczytano EmployeeSystemSchema.php (VERSION8) i EmployeeRef.php w całości, właściwe definicje MySQL EmployeeSystemBootstrap.php, fragment TrainingBootstrap.php obejmujący cztery definicje tabel oraz EmployeeRelationLifecycleService.php. Wyodrębniono wyłącznie definicje CREATE TABLE dotyczące HR ze starego pliku SQL i tests/ci-schema.sql; nie kopiowano rekordów graczy. Skan src/migrations dotyczył definicji i nazw tabel, nie pełnego odczytu wszystkich plików.
Źródła repozytorium: src/Employee/EmployeeSystemSchema.php, src/EmployeeSystemBootstrap.php, src/Employee/EmployeeRef.php, src/TrainingBootstrap.php, tests/ci-schema.sql. Stary dump nie jest pełnym ani aktualnym kontraktem: w dostępnym pliku znaleziono definicje tylko do części alfabetu. Wniosku „brak tabeli na produkcji” nie wolno wyciągać z braku w tym pliku.

#### B. Mapa ponownego wykorzystania

| Istniejąca tabela / rodzina | Decyzja | Potrzebne rozszerzenie lub warunek |
|---|---|---|
| board_members | Zachować dane, rozszerzyć/mapować | Zawiera dyrektorów i staff, skills, traits, salary, daty; dodać trwałe powiązanie osoby i epizodu zatrudnienia. Nie tworzyć drugiego zarządu |
| technical_staff | Zachować dane, rozszerzyć/mapować | Istnieją role, salary, status, traits; status on_leave nie zastępuje rejestru urlopu. Zachować różnicę technik/zarząd bez dublowania osoby |
| employee_source_links | Zachować jako dowód mapowania | Łączy board_member z technical_staff jako legacy_headhunter_mirror, oba końce UNIQUE; nie jest ogólnym rejestrem osób. Wykorzystać przy deduplikacji |
| employee_state | Rozszerzyć | Istnieją morale, workload, expected_salary, relation_status i version. Unikalność source_type+source_id nie przechowuje kolejnych zatrudnień; stan bieżący oddzielić od historii |
| board_roles | Rozszerzyć katalog | Stabilny code i mapowanie funkcji HR; tłumaczenia PL/EN/DE i SVG. Nie zastępować istniejących kodów bez mapy |
| employee_role_effects | Rozszerzyć wersjonowaniem | Istnieją specialization_code, effect_key, scope, value i skill_weights_json. Efekt roli nie jest indywidualną ukrytą cechą |
| recruitment_requests | Rozszerzyć | Dodać kanał oddzielony od zasięgu local/international, budżet/quote, politykę i trwały wynik RNG; zachować historię request_id |
| candidates | Rozszerzyć | Powiązanie trwałej osoby, wersja generatora, warunki oferty; nie losować nowych cech przy przeniesieniu do zatrudnienia |
| headhunter_searches / headhunter_candidates | Mapować do wspólnego procesu rekrutacji | Zachować źródłowe ID, cenę/ofertę i status; nie utrzymywać dwóch aktywnych zapisów tej samej kandydatury |
| candidate_reviews | Zachować jako historyczne oceny rekrutacyjne | score/recommendation/comment nie zawierają podstaw i wersji nowych ocen HR. Możliwy uprawniony dowód pomocniczy; nie importować jako supported |
| employee_contracts | Rozszerzyć zamiast drugiego katalogu umów | Obecny contract_type=6m/1y/2y oznacza długość, NIE employment/mandate/B2B. Dodać formę, basis, wymiar, walutę, wersję warunków, identyfikację zatrudnienia i okres obowiązywania |
| employment_history | Zachować/rozszerzyć | action hired/fired/resigned/suspended i daty zachować; dodać firmę, employment_ref, źródło i idempotencję. Sama lista zdarzeń nie zastępuje przedziałów zatrudnienia |
| employee_assignments | Rozszerzyć | Istnieją cel, allocation_pct, assigned_at/released_at. Dodać plan/wersję i przedziały; nie utożsamiać przydziału z faktycznie przepracowanym czasem |
| technical_staff_skills | Zachować | UNIQUE staff_id+skill_code; nie zakładać nowego równoległego słownika umiejętności. Mapowanie tożsamości oraz polityka historii przy usunięciu |
| training_programs | Rozszerzyć | Wersje programów, DE, rozdzielenie długości kursu i zajętości pracownika |
| staff_trainings | Rozszerzyć | Istnieją koszt, wynik, retry, cooldown, active_guard; dodać pauzy/progress i snapshot programu. Nie tworzyć drugiej kolejki szkoleń |
| training_certificates / employee_certificates | Zachować i uzgodnić semantykę | Pierwsza przechowuje wynik kursu dla obu typów personelu; druga member_id i expires_at. Oddzielić osiągnięcie od uprawnienia, nie scalać po nazwie |
| employee_raise_requests | Zachować/rozszerzyć | Kwoty, status, terminy, request_no już są; wynik wiązać z nową wersją warunków umowy |
| employee_strikes / employee_strike_members | Zachować | Zachować członkostwo/historyczne końce; mapować person/employment i scope firmy |
| employee_strike_negotiations / employee_strike_negotiation_rounds | Zachować | Próby, rundy, zapis RNG i idempotencja już istnieją; nie tworzyć nowego równoległego negocjatora |
| employee_dialogue_templates | Rozszerzyć | PL/EN już istnieją; DE, wersje i allowlista parametrów |
| employee_events | Zachować jako komunikaty/historyczne fakty UI | is_read/notified_at i meta_json wskazują inną odpowiedzialność niż niezmienne obserwacje z rewizjami. Nie wkładać sekretnego profilu w meta_json |
| employee_action_receipts | Wykorzystać albo migrować do wspólnej idempotencji | Jest request_hash, token, odpowiedź i unikalność player+action+token; nie utrzymywać dwóch konkurujących rejestrów tej samej akcji |
| employee_system_config | Migrować wartości do wersjonowanej konfiguracji | Obecny klucz/wartość bez historii nie spełnia publikacji immutable balance_version |
| employee_module_cycles / employee_cycle_department_claims / employee_schema_versions | Zachować historycznie, zastąpić rolę wykonawczą adapterem | Cykle legacy nie są slotami nowego Settlement. Nie kopiować numeru ticka jako czasu pracy |
| staff_morale_logs / staff_strikes | Uzgodnić starszą i nowszą generację | Definicje znalezione w HR/MoraleBootstrap.php; nie uruchamiać obok employee_state/employee_strikes jako dwóch źródeł tego samego skutku |

„Zachować” oznacza ponowne wykorzystanie danych i odpowiedzialności, a nie gwarancję bezpośredniego podłączenia starych tabel do nowego kodu bez migracji.

#### C. Rzeczywiste luki i minimalne nowe struktury

W sprawdzonych źródłach nie potwierdzono kompletnego odpowiednika:
- trwałej osoby niezależnej od firmy i typu rekordu oraz kolejnych epizodów zatrudnienia;
- wersjonowanych warunków umowy (mogą być wierszami rozszerzonego employee_contracts, niekoniecznie osobną tabelą);
- planów zmian, rezerwacji czasu i faktycznego czasu pracy;
- wniosków/epizodów nieobecności oraz księgi nabycia/rezerwacji/zużycia urlopu;
- niezmiennych obserwacji HR, rewizji, ocen/snapshotów i powiązań dowodów;
- biznesowego nakładu pracy analityka i jego kolejki.

To kandydaci do nowych struktur dopiero po potwierdzeniu metadanych bieżącej bazy. Nazwy employee_availability i employee_traits z dokumentacji nie zostały potwierdzone jako tabele w przeszukanych src/migrations. Nie traktować ich jako istniejącego DDL. Cechy lojalność/ambicja/korupcja rzeczywiście są kolumnami board_members i technical_staff.

Model §18.9 można uprościć fizycznie: assessment_current jako kontrolowany wskaźnik, eligibility jako wersjonowana część powiązania dowodu, a przebiegi techniczne według wspólnej infrastruktury. Warunek: zachować historię, CAS, ownership i odtwarzalność. Nie redukować liczby tabel przez upychanie wszystkich procesów w jednym JSON.

#### D. Krytyczne pułapki migracji

1. source_id jest unikalny tylko wraz z source_type; staff_type w szkoleniach używa technical/board, a EmployeeRef technical_staff/board_member. Wymagana jawna mapa.
2. player_id nie jest automatycznie company_id. Mapowanie świata/firmy musi być jednoznaczne; prywatne obserwacje zostają u dawnego pracodawcy.
3. Rekordy lustrzane łączymy wyłącznie na podstawie istniejącego linku lub udokumentowanego dowodu. Imię i nazwisko nie wystarczają.
4. technical_staff.salary jest INT, board_members.salary i employee_contracts.salary DECIMAL. Przed konwersją ustalić jednostkę i źródło prawdy; nie traktować starego INT jako groszy i nie mnożyć etatu dwukrotnie.
5. W źródłach występują zerowe daty oraz expires_at z ON UPDATE. Konwersja do UTC/NULL wymaga raportu wyjątków; edycja oferty nie może przedłużać ważności przypadkowo.
6. staff_trainings.active_guard chroni jedną aktywną naukę, ale nie kolizję z jazdą lub pracą HR. Wspólny kalendarz nadal potrzebny.
7. technical_staff_skills i training_certificates mają źródłowe ON DELETE CASCADE. Przeniesienie historii do nowego silnika musi zapobiec utracie osiągnięć po zwolnieniu; nie wykonywać usunięć w tej rundzie.
8. Legacy employee_state ma jeden bieżący stan per źródło, nie pełną historię firmy/osoby. Nie aktualizować owner w sposób udostępniający nowej firmie stare prywatne oceny.
9. Nie tworzyć wstecz fikcyjnych obserwacji ani ukrytych cech z liczby starych komunikatów. Brak danych = nieznane, nie zero ani nowe losowanie przy GET.

#### E. Plan wdrożenia i odbioru

Etap1: eksport SAMYCH metadanych SHOW CREATE TABLE, indeksów i FK z uzgodnionego środowiska oraz wersji migracji. Bez uruchamiania init.php/ensure() — bootstrapy wykonują DDL. Bez kopiowania danych produkcyjnych do lokalnego środowiska.

Etap2: raport różnic source→target, typów/signedness/collation, niezgodnych dat, braków powiązań i źródeł kwot. Uzupełnić pełny katalog technicznych specjalizacji, przydziałów obiektowych i księgowania płac; obecny przegląd nie potwierdza kompletności tych obszarów.

Etap3: migracje addytywne i idempotentna mapa legacy_type+legacy_id→person/employment/target_id. Dla braków zachować status unresolved; nie scalać ani nie wymyślać historii. Testy na syntetycznej bazie MySQL.

Etap4: adaptery odczytu, backfill i porównania liczności osób po deduplikacji, aktywnych umów, sum płac, certyfikatów, przydziałów i otwartych procesów. Jeden właściciel zapisów; dual-write tylko po osobnym projekcie transakcyjnym.

Etap5: kontrolowane przełączenie, backup i punkt odcięcia. Rollback wymaga uzgodnienia nowych zapisów, nie samego cofnięcia kodu. Usunięcie legacy struktur dopiero osobnym zatwierdzonym etapem.

Testy wymagane: kolizje ID między źródłami, mirror bez podwójnej pensji, rehire tej samej osoby, przejście między firmami bez ujawnienia ocen, mapowanie obu enumów źródła, precyzja kwot, zerowe daty, rollback/backfill retry, aktywne szkolenie/strajk/negocjacje zachowane, brak kaskadowej utraty certyfikatów.

Wynik tej rundy: mapa oparta na statycznych definicjach i fragmentach kodu. Nie wykonano SHOW CREATE TABLE na działającej bazie, migracji, testów Laravel/MySQL ani wdrożenia. Testy runtime/build nie dotyczą zmiany dokumentacyjnej. Wycofanie: odwrócenie wyłącznie commitu dokumentacji; dane pozostają nietknięte.


### 18.19. Weryfikacja pełnego eksportu schematu — HR-DB-REUSE-02

Źródło: lokalny eksport `01240275_oil (1).sql` z 18.11.2026, 222590 bajtów, SHA-256 `7b74ad35a5a316b37e3279fe85c435487380c5569c978398de9f091d96878c1c`. Plik kończy się COMMIT i zawiera 180 definicji CREATE TABLE oraz 368 instrukcji ALTER TABLE. Poprzedni plik `01240275_oil.sql` miał 188811394 bajtów, ale kończył się w instrukcji INSERT po 79 tabelach; nie używamy go do dowodzenia braku tabel. Eksport jest schematem, nie potwierdzeniem bieżącego stanu serwera ani zawartości rekordów.

Potwierdzono 34 tabele związane z kadrami według nazw. W szczególności istnieją również: `technical_staff`, `staff_trainings`, `technical_staff_skills`, `training_programs`, `training_certificates`, `staff_specializations`, `headhunter_searches`, `headhunter_candidates`, `recruitment_requests` i `well_staff_assignments`. Uzupełnia to statyczny przegląd §18.18; tamtejsze stwierdzenie o niepełnym dumpie dotyczy poprzedniego pliku, nie tego eksportu.

| Obszar | Potwierdzone kolumny/indeksy | Wniosek dla migracji |
|---|---|---|
| Tożsamość | `board_members`, `technical_staff`, `employee_source_links` z UNIQUE dla obu lustrzanych ID | Zachować i mapować; osobę wspólną tworzyć tylko tam, gdzie mapowanie jest pewne |
| Stan | `employee_state` z UNIQUE(source_type,source_id), morale, workload i version | Zachować stan bieżący; historię zatrudnień oraz oceny odseparować |
| Umowy | `employee_contracts` z FK do `board_members`, contract_type długości6m/1y/2y | Rozszerzyć i uwzględnić, że obecny FK nie obejmuje `technical_staff` |
| Historia | `employment_history` z FK do `board_members` | Zachować dane; nowe zdarzenia techników wymagają wspólnego odniesienia osoby |
| Przydziały | `employee_assignments` i `well_staff_assignments` | Uzgodnić oba zapisy jako jeden stan operacyjny; nie liczyć obsady dwa razy |
| Szkolenia | `staff_trainings` z UNIQUE(staff_type,staff_id,active_guard), `technical_staff_skills`, `training_programs`, `training_certificates` | Ponownie wykorzystać; kalendarz i kolizje czasu nadal wymagają rozszerzenia |
| Certyfikaty | `employee_certificates` dla board member oraz `training_certificates` dla board/technical | Nie scalać bez mapy osiągnięcie/uprawnienie; sprawdzić retencję przy FK |
| Rekrutacja | `recruitment_requests`, `candidates`, `candidate_reviews`, `headhunter_searches/candidates` | Rozszerzyć o kanał/budżet/wersję i mapować ścieżkę headhuntera |
| Role | `board_roles`, `staff_specializations`, `employee_role_effects` | Zachować kody, ustalić mapowanie funkcji analityka HR |
| Idempotencja | `employee_action_receipts`, UNIQUE(player_id,action_key,idempotency_token) | Wykorzystać lub jawnie zintegrować ze wspólnym rejestrem akcji |

W tym kompletnym eksporcie NIE znaleziono definicji tabel `employee_traits`, `employee_availability`, rejestru urlopów, grafiku zmian ani `staff_assessment_*`. Nazwy z §18.9 pozostają logiczne. Trwałe ukryte cechy można zapisać w rozszerzeniu osoby lub osobnym wąskim rejestrze; decyzja wynika z mapowania tożsamości, a nie założenia o istniejącej tabeli.

Potwierdzone ograniczenia schematu: `technical_staff` ma salary jako INT, `board_members` i `employee_contracts` jako DECIMAL; `employee_contracts.member_id` oraz `employment_history.member_id` wskazują board_members. `training_certificates` przechowuje staff_type/staff_id, a `employee_certificates` member_id. Indeksy i FK są w sekcjach ALTER eksportu, więc same CREATE TABLE nie wystarczają do odtworzenia schematu.

Następne zadanie implementacyjne: katalog mapowania fizycznych PK/FK i jednostek, syntetyczny test deduplikacji lustrzanych pracowników, projekt addytywnych migracji i adapterów. Nie importowano dumpa, nie wykonywano DDL ani testów Laravel/MySQL. Historia i kwoty pozostają nietknięte.


### 18.20. Klucze i migracja addytywna — HR-DB-MAP-01

Status: projekt programistyczny oparty na pełnym eksporcie §18.19; nie jest wykonaną migracją. W pierwszym wydaniu nie usuwamy istniejących tabel ani FK. Nowe nazwy poniżej są propozycjami fizycznymi do migracji Laravel, po sprawdzeniu aktualnego schematu na środowisku wdrożenia.

#### A. Faktyczne relacje z eksportu

| Źródło | Relacja/ograniczenie | Wpływ |
|---|---|---|
| employee_contracts.member_id | FK → board_members.id, ON DELETE CASCADE | Nie może reprezentować technika; usunięcie członka może usunąć umowy |
| employment_history.member_id | FK → board_members.id, ON DELETE SET NULL | Historia może stracić powiązanie z osobą |
| employee_certificates.member_id | FK → board_members.id, ON DELETE CASCADE | Usunięcie członka usuwa certyfikat |
| training_certificates.training_id | FK → staff_trainings.id, ON DELETE CASCADE | Usunięcie szkolenia usuwa certyfikat |
| staff_trainings.program_id | FK → training_programs.id | Szkolenie ma program; staff_type+staff_id nie ma FK do osoby |
| employee_source_links | UNIQUE(board_member_id), UNIQUE(technical_staff_id), brak FK | Jednoznaczny link lustrzany, ale trzeba walidować oba końce i właściciela |
| employee_state | UNIQUE(source_type,source_id), brak FK do pracownika | Jeden bieżący stan źródła; kontrola player_id w kodzie |
| employee_assignments, well_staff_assignments | Brak FK do osoby; dwa sposoby wskazania obsady | Wymaga zgodności, by nie policzyć tego samego slotu dwukrotnie |

W eksporcie board_members.role_id ma FK do board_roles.id z ON DELETE CASCADE; candidates.role_id taki sam. Nowy panel nie może przez usunięcie roli kasować historii pracowników/kandydatów. Wyłączenie roli i późniejsze ograniczenie kaskady wymagają osobnej bezpiecznej migracji po audycie danych.

#### B. Jeden identyfikator osoby i zatrudnienia

Proponowany rejestr staff_persons: id (wewnętrzny bigint), public_ulid, generation_version, created_at; bez firmowej oceny. Rejestr staff_person_legacy_refs: person_id, legacy_type, legacy_id, evidence_code; UNIQUE(legacy_type,legacy_id). Typy jawne: board_member, technical_staff, candidate, headhunter_candidate. Indeks/unikalność person_id+legacy_type zależy od dopuszczenia wielu kandydatur tej samej osoby.

Dla pary w employee_source_links tworzymy jedno person_id dopiero po weryfikacji istnienia obu rekordów i zgodności player_id. Brak linku oznacza osobne osoby lub unresolved; nie scalać po nazwisku, dacie urodzenia ani podobieństwie cech. Kandydatura przechodząca w zatrudnienie wymaga zapisanego źródłowego związku w akcji zatrudnienia; istniejący eksport nie dowodzi takiego związku dla wszystkich historycznych rekordów. Nie przypisujemy historycznym pracownikom nowych ukrytych cech przez GET.

Proponowany staff_employments: id, person_id, company_id, source_ref, started_at, ended_at, status, created_at, version. Jeden wiersz na ciągły okres u jednego pracodawcy; nowe zatrudnienie tej samej osoby to nowy id. Koniec umowy/aneks nie musi kończyć zatrudnienia. Ocena HR wiąże person_id i company_id, a istotne dowody także employment_id; inna firma nie dziedziczy prywatnej oceny.

Idempotencja backfill: UNIQUE(legacy_type,legacy_id) oraz stabilny migration_batch/source_hash. Ponowienie nie tworzy nowej osoby ani zatrudnienia. Sprzeczne mapy oznaczamy unresolved w raporcie; nie wybieramy arbitralnie.

#### C. Umowy, płace i historia

Istniejąca employee_contracts pozostaje źródłem historycznych umów zarządu. Addytywnie: employment_id, effective_from/to, form_code, term_code, basis_code, actual_amount_minor, currency_code, fte_numerator/denominator lub uzgodnione godziny, policy_version, supersedes_id. Stare contract_type mapujemy wyłącznie do term_code; nie utożsamiamy 1y z employment. Dopóki kwoty nie zostaną uzgodnione, salary/bonus i pola nowe funkcjonują z flagą migration_state; brak automatycznej podwójnej wypłaty.

Dla techników nie ustawiamy fikcyjnego member_id. Opcje: rozszerzenie employee_contracts przez nullable member_id i kontrolę employment_id z nową polityką FK, albo osobna wąska tabela wersji umów wspólna dla wszystkich. Wybór po analizie zapisów płac i zachowania starych FK; nie tworzyć dwóch aktywnych źródeł pensji.

Historia zatrudnienia: stare employment_history pozostaje immutable. Nowe zdarzenia odnoszą się do employment_id i reason_code; opcjonalny legacy_history_id wiąże zdarzenie importowane. Nie nadpisujemy starego member_id technikiem. Stare eventy bez daty rozpoczęcia/końca nie uzasadniają odtwarzania fikcyjnych okresów.

Płaca rozliczana raz per employment_id+period+component+policy_version przez wspólny kontrakt finansowy. W fazie porównawczej obliczenia nowego modelu są tylko shadow/read-only; nie księgują drugiego obciążenia.

#### D. Przydziały i czas

employee_assignments pozostaje historią ogólnego przydziału; well_staff_assignments historią konkretnej obsady odwiertu. Adapter Staff zwraca jeden kanoniczny widok przydziału person/employment/asset/interval/source. Jeśli oba stare źródła opisują tę samą pracę, oznacza się mirror_of i liczy raz; jeśli się różnią, konflikt do ręcznej analizy. Nie kasujemy żadnego źródłowego wiersza podczas backfill.

Nowy grafik wymaga wersjonowanych przedziałów zmian i rezerwacji czasu. Sam allocation_pct nie jest dowodem dostępności (§4.1). Zakaz overlap sprawdzamy transakcyjnie dla jednego employment_id i przedziału; przy MySQL brak prostego exclusion constraint, więc indeks czasu plus blokada właściciela czasu. Rzeczywiście przepracowane sekundy i planowane godziny chorobowe mają osobne źródła.

#### E. Kolejność migracji i bramki

M1: snapshot metadanych z docelowego środowiska, typy PK/FK/signedness, indeksy, collation, tryb SQL; tylko odczyt. Porównanie z SHA eksportu §18.19.
M2: addytywne staff_persons, staff_person_legacy_refs i staff_employments oraz indeksy; bez zmian starego payroll/obsady.
M3: deterministyczny backfill z checkpointem, walidacją mirror links, raportem unresolved i sumami kontrolnymi. Zero automatycznego scalania po podobieństwie.
M4: adapter odczytu osoby i rozliczenia czasu w nowym silniku; przy przenoszeniu istniejących kont porównanie wyników z danymi źródłowymi, bez uruchamiania starego kodu jako warunku projektowania.
M5: wersjonowanie umów, grafiki, absencje i oceny HR z odrębnymi migracjami. Aktywacja funkcji dopiero po testach MySQL i symulacji.
M6: przełączenie pojedynczego właściciela zapisu. Legacy zapis wyłączamy tylko w kontrolowanym cutover; żadnego swobodnego dual-write. Usunięcie starych struktur to odrębny przyszły projekt.

Warunki wejścia do M4: kompletna mapa wszystkich aktywnych rekordów, zero niewyjaśnionych podwójnych pensji, zgodność liczby unikalnych osób po lustrzanych linkach, zgodność sald/rozliczeń shadow, pełna izolacja firm. Warunki wejścia do M6: backup, test restore, rollback code+data dla nowych zapisów, próba na kopii bez danych osobowych, monitorowanie rozbieżności.

Testy: para board+technical→jedna osoba; dwa takie same ID z różnych tabel→dwie osoby bez linku; dangling/cross-company link→unresolved; retry backfill→ten sam wynik; rehire→nowe employment bez zmiany person; umowa technika bez board_member; status/kwota/FK zachowane; dwa źródła przydziału jednego odwiertu→jedna obsada; nieznany związek→brak sztucznego scalenia; stara firma nie widzi nowej oceny i odwrotnie; rollback nie usuwa starej historii.

Nowy silnik Laravel projektujemy na podstawie zatwierdzonych zasad gry i docelowego schematu. Pełny przegląd starych ścieżek zapisu salary, umów, przydziałów, zatrudniania i zwalniania nie jest bramką projektowania ani przygotowania migracji. Stary kod sprawdzamy punktowo wyłącznie wtedy, gdy eksport schematu nie wyjaśnia znaczenia pola lub gdy przygotowujemy przeniesienie istniejących kont i historii. Dla przeniesienia danych wymagane są mapy źródło→cel, walidacja liczności i kwot oraz testy na kopii; nie wymaga to odtwarzania dawnych mechanizmów zapisu w Laravelu. Nie wykonano migracji ani testów MySQL w tej rundzie.


## 19. Decyzja wdrożeniowa: wariant A z rozbudową B — HR-MVP-01

Właściciel wybrał wariant A jako pierwszy zakres nowego silnika. Wariant B pozostaje zaplanowanym rozszerzeniem tego samego modułu Staff. Cennik poniżej jest **proponowaną przeze mnie wartością startową** do wersjonowanego panelu i symulatora; nie zmienia aktywnego game_balance v1. Nowe liczby można zatwierdzić/skalibrować jako kandydat wersji balansu. Już ustalone ceny, limity i wzory mają pierwszeństwo.

### 19.1. Jedna prosta droga programowania

Każdy proces ma jedną akcję domenową, jedno źródło stanu i parametry katalogu: RecruitmentCampaign, Employment, Roster, Absence, Training, Assessment. Frontend, Filament, API, kolejka i sweeper wywołują te same akcje, nie implementują własnych reguł. Tylko właściciel domeny zapisuje swoje dane. Skutki gospodarcze trafiają do Finance/Ledger, czas do Staff/Simulation.

Silnik reguł **nie** interpretuje dowolnych wyrażeń administratora ani kodu z bazy. Tabela polityki zawiera code, strategy_code, parametry z typami i zakresem, version, effective_from, hash. Kod obsługuje mały zamknięty zestaw strategii; dodanie strategii B wymaga wydania kodu, ale nie wymiany tabel ani endpointów. Przykłady: fixed_fee, fixed_band, calendar_proration, role_minimum, evidence_threshold. Nie tworzymy własnego frameworka reguł.

Dla każdego wariantu B używamy rozszerzalnych pól katalogu i wersjonowanego strategy_code. Nie dodajemy teraz tabel dla hipotetycznej agencji, wszystkich regionalnych przepisów lub nieskończonych kombinacji. Osobne wiersze/tabele tylko dla rzeczywistych danych powtarzalnych: umowa i jej wersje, przedziały grafiku, absencje, dowody, snapshoty. Jawne sloty i idempotencja pozostają obowiązkowe.

### 19.2. Rekrutacja A — cennik i wynik

Jednostka: PLN gry za **jedną kampanię na jedno stanowisko i jeden wakat**. Kampania z większą liczbą wakatów to osobne pozycje quote; nie liczymy ceny raz dla dowolnej liczby osób. Cena zawiera publikację i podstawową selekcję. Nie pobieramy drugiej prowizji za samo zatrudnienie. Maksimum2 równoległe kampanie zostaje. Headhunter: istniejące25GBBL i limit1/dobę, nie przeliczamy arbitralnie na PLN.

| Kanał | Zasięg A | Cena/kampania | Liczba ofert do wylosowania | Typowy profil |
|---|---|---:|---:|---|
| Uczelnia/szkoła | lokalny | 4 000 | 0–2 | początkujący |
| Polecenie pracownika | lokalny | 6 000 | 0–1 | mała pula |
| Gazeta lokalna | lokalny | 8 000 | 0–2 | operacyjny |
| Portal ogłoszeniowy | krajowy | 12 000 | 0–3 | ogólny |
| Radio regionalne | krajowy | 20 000 | 0–3 | większy nabór |
| Portal branżowy | krajowy | 30 000 | 0–3 | techniczny/specjalistyczny |
| Agencja rekrutacyjna (kanał) | krajowy | 45 000 | 0–3 | selekcja wg wymagań |
| Telewizja | krajowy | 90 000 | 0–4 | duży zasięg, bez bonusu jakości |
| Headhunter | według istniejącego katalogu | 25 GBBL | według istniejącej polityki | rzadkie role |

Zasięg A wynika z wiersza kanału; UI nie oferuje nieobsługiwanych kombinacji. Rekrutacja międzynarodowa A: portal branżowy lub agencja, cena bazowa ×1,5 za kampanię, mnożenie raz na quote. Bez niezależnej premii „lokalizacja”. Wariant B może dodać inne dozwolone kombinacje jako nowe wiersze, bez zmiany akcji.

Budżet A: podstawowy1,0× ceny, rozszerzony1,5×, szeroki2,0×. Liczba ofert pozostaje w zakresie wiersza; budżet zwiększa wagę losowania wyższej liczby ofert, ale nie tworzy gwarancji. Aby uniknąć nieokreślonej krzywej, bazowe wagi liczby ofert dla zakresu0–N: dla N=1 [30,70], N=2 [20,50,30], N=3 [15,40,30,15], N=4 [10,30,30,20,10] w procentach. W rozszerzonym przesuwamy5 punktów procentowych z wyniku0 na wynik min(1,N); w szerokim10 punktów. Pozostałe wagi bez zmian. Jedno utrwalone losowanie per kampania; droższy wariant może nadal dać0. Nie zmienia prawdopodobieństwa ukrytych cech ani bazowych umiejętności.

Czas A: lokalny stałe3min, krajowy4min, międzynarodowy5min aktywnego czasu firmy; mieści się w istniejących przedziałach2–4/3–5min. Dla headhuntera zachować jego własny termin z wcześniejszego katalogu. Oferta ważna24h aktywnego czasu od udostępnienia. Kampania opłacona na starcie; anulowanie przed końcem nie zwraca opłaty za publikację, po otrzymaniu ofert również brak zwrotu. Niewystarczająca gotówka blokuje start; quote ważny5min i utrwala całkowitą kwotę.

Relokacja A tylko gdy oferta jest międzynarodowa: stałe20 000 PLN jednorazowo po zaakceptowaniu oferty przez pracownika, przed rozpoczęciem zatrudnienia; start po24h aktywnego czasu. Anulowanie przed akceptacją nie obciąża relokacją. Brak środków pozostawia ofertę niezaakceptowaną, nie tworzy długu poza zgodą gracza. Wariant B doda stawki region/rola i czas podróży jako katalog.

Do symulacji: kampania nie może ekonomicznie zastąpić każdej trudnej decyzji finansowej; telewizja przy jednym wakacie jest celowo droga. Porównać koszt zatrudnienia/utrzymania operatora, technika i inżyniera w90dni z przychodem startera.

### 19.3. Płace, umowy i zwolnienia A

Zachować HR-PAY-01: formy employment/mandate/business_service/management, rzeczywisty wymiar etatu, stawki bazowe z architektury, profil20,48%, miesięczne naliczanie i kontrolowane zaokrąglenia, staż, progi7/14/30dni wypowiedzenia oraz odprawy0,5/1/2 podstawy. Nie wymyślać drugiej stawki pensji ani ponownie mnożyć salary przez etat. Panel pokazuje cenę całkowitą i źródło składowych.

A stosuje jeden STANDARD_GAME profil kosztów, jeden kalendarz UTC oraz jeden zestaw reguł dodatków §4.1: +50% czasu ponad wymiar, +20% noc, sumowanie gdy oba. Płatne urlopy według §4.2, choroba employment80% podstawy według §14; rozliczenie niepełnego okresu metodą HR-PAY-01. B może dodać profile region/umowa, lecz ich brak nie tworzy pola „0%” w A.

A domyka DOM02 bez podwójnej kary: natychmiastowe zakończenie przez firmę ma **jednorazowy** efekt morale zespołu −10 punktów; zwykłe wypowiedzenie z przepracowaniem/odpłatnym odsunięciem −8 punktów na osobę, ograniczone do−24 punktów w ruchomych14dniach dla jednego działu. Nie stosować obu do tego samego końca zatrudnienia. Efekt uruchamia się przy komunikowanej decyzji, nie po raz drugi na employment_ended_at. Populacja sporu jest snapshotem aktywnej obsady z logical_at decyzji, nie „ostatnie24h” po zakończeniu. Progi i cap edytowalne w panelu. Wyjątki (porozumienie, naturalny koniec, odejście pracownika) mają efekt0 w tej polityce. To kandydat gry, wymagający testu morale i90dni.

DOM08 w Staff A: przy identycznym logical_at najpierw kończymy uprawnioną pracę/egzamin, której faktyczny warunek ukończenia zaszedł przed końcem zatrudnienia; jeśli due_at egzaminu **równa się** employment_ended_at, najpierw koniec zatrudnienia, potem egzamin jest cancelled_employee_left, bez certyfikatu. Wydarzenia innych domen, np. repair/escalation, wymagają ich własnej jawnej kolejności w Simulation i nie wynikają z tej reguły Staff.

### 19.4. Grafiki, obsada i nieobecności A

A oferuje szablony1/2/3 zmian, dni pracy i wymagania obsady z §4.1. Gracz ustawia tryb obiektu i priorytet; planista układa grafik i zgłasza brak pokrycia. Ręczna edycja dopuszczona w granicach twardych norm, bez osobnej strategii łamania czasu pracy. Każda osoba ma jeden wspólny kalendarz i limit czasu; to obowiązuje również B.

Obsada startera A: od razu jawna, ograniczona czasowo obsługa szkoleniowa NPC operatora i technika dla odwiertu szkoleniowego na pierwsze48h aktywnego czasu firmy, bez pensji i bez przenoszenia na inne obiekty; UI pokazuje termin końca i konieczne zatrudnienie. **To kandydat do kalibracji FTUE**, bo obecny kanon nie zapewnia zmianowej obsady startowego odwiertu. Nie aktywować jednocześnie z alternatywnym darmowym przydziałem.

Hub A: pełna wymagana obsada1/2/4 daje100% nominalnej przepustowości; każdy brak choćby jednej wymaganej osoby daje istniejące70% i ryzyko×2. Nie interpolujemy do0–100% według liczby osób. B może dodać krzywą częściowej obsady jako strategię katalogu, po testach gospodarki.

Rafineria A: liczby4+1 /10+3 /24+8 są **liczbą wymaganych jednocześnie obecnych operatorów/chemików** w aktywnym przedziale pracy instalacji. W trybie zmianowym każdy przedział wymaga takiej obsady, a nie tych samych osób24/7. Raport kosztu pokazuje liczbę etatów potrzebną do pokrycia zadanych godzin; zewnętrzny OPEX techniczny nie jest dodatkową pensją za te osoby. Jeśli istniejący OPEX zawiera ten sam koszt, przed aktywacją trzeba wyłączyć odpowiednią składową, nie księgować dwukrotnie. Bez testu marży nie aktywować trybu rafinerii.

Rurociąg A: inżynier może nadzorować jeden aktywny odcinek na zmianę; dla kolejnego potrzebna osobna dostępność. Bez obsady zachowany dotychczasowy skutek wear/risk×2, nie zatrzymanie przesyłu. B może dodać współdzieloną pojemność zależną od długości/stanu odcinków.

Zadanie nieprzerywalne A nie startuje bez zaplanowanego pokrycia przewidywanego czasu i rezerwy. Jeśli rzeczywisty czas przekroczy plan: Logistics/Technical wykonuje zaprojektowany safe-stop albo przekazanie uprawnionej osobie. Bez kontraktu bezpiecznego zakończenia dana kategoria zadania pozostaje nieaktywna; nie przedłuża się zmiany bez zgody.

Urlopy A: wszystkie parametry biznesowe nadal edytowalne w panelu, zgodnie z §4.2; wersjonowanie uprawnień, ledger godzin i audyt są niezmiennikami. Chorobowe według §14/§16.1: nominalne10–20%/30 aktywnych dni, długość1–3dni, cooldown7, max2 krótkie epizody/30dni; pojedynczy epizod nie zdradza ukrytej cechy. Warunki pracy A: jeden neutralny m=1 dla pierwszej kalibracji, a w UI nie obiecujemy wpływu zmęczenia, dopóki nie ma zatwierdzonej mapy. B może dodać wersjonowaną mapę warunków i odpoczynku. Zastępstwo: kolejność §14, pracownik tymczasowy2,5× pełny koszt godzinowy, bez gwarancji dostępności.

### 19.5. Szkolenia i HR A

Zachować wszystkie zatwierdzone parametry Refining:20k/50k/100k,24/48/72h, próg70 i skill cap10. Dla czterech bazowych kursów technicznych wariant DOM06-A: poziom I 10 000 PLN/24h, II 30 000/48h, III 60 000/72h; próg70, cap10. Cennik Przerobu Ropy 20/50/100 tys. PLN pozostaje bez zmiany. To wybór do nowej wersji parametrów, nie retroaktywna zmiana rozpoczętych szkoleń. Katalog roli/skill dopuszcza poziom I–III; nie wolno otworzyć kursu bez jawnego wpisu. Koszt raz przy zapisie, egzamin idempotentny, pauza chorobowa według §17. W pierwszym wydaniu tylko elapsed_time z rezerwacją **8h pracy w każdym dniu trwania kursu**, o ile istnieje zaplanowany dyżur; bez dyżuru kurs trwa kalendarzowo, ale pracownik nie jest obsadą obiektu. Rezerwacja nie łamie limitów §4.1. Wariant scheduled_training_time to B.

Po7 aktywnych dniach blokady egzaminów A: licznik kolejnych niepowodzeń resetuje się do0, ale skill, wcześniejsze próby i historia wyników pozostają. Nowa próba płatna normalnie; nie przyznajemy automatycznego bonusu zdania za sam reset. To decyzja DOM07 dla wariantu A; przed aktywacją wymagany jest test czwartej próby.

Cztery cechy §16.1 i oceny §18 zostają w A. Aktywujemy ocenę domeny stanowiska tylko wtedy, gdy ma zatwierdzony baseline i producenta dowodów; pozostałe pokazują „za mało porównywalnych danych”. Nie wpisujemy uniwersalnych liczb błędów dla różnych ról. Priorytet norm na start: operator odwiertu, technik, kierowca i operator huba; dalej pozostałe role. Zwykły gracz nie widzi profilu ukrytego.

Organizacja HR A: jedna kolejka i czasy §18.17, bez darmowej analizy przy braku analityka. Mentoring A tylko jako jawne zadanie z rezerwacją czasu, bez premii do skilla/egzaminu dopóki jego mapa nie przejdzie symulacji; B doda skuteczność i wielu podopiecznych. Automatyzacja A: układanie legalnego grafiku, alarmy luk i scalanie zleceń analizy; bez automatycznego zwalniania lub wydawania pieniędzy.

### 19.6. Droga do B bez przebudowy

| Funkcja A | Rozszerzenie B | Stały kontrakt |
|---|---|---|
| Stały katalog kanałów, cen i zasięgów | region, konkurencja, oferta, budżet | RecruitmentQuote/Start/Result, wersja katalogu |
| STANDARD_GAME koszt umowy | profil region/forma | EmploymentContractVersion + PayrollComponent |
| Szablony grafików i jeden kalendarz | warianty planisty, prognozy | AvailabilityQuery + ReserveTime |
| Minimum obsady i fixed_band | krzywa częściowej obsady | StaffingSnapshot + OccupancyStrategy |
| Neutralne warunki choroby | modyfikatory odpoczynku/obciążenia | AbsenceExposure + HazardStrategy |
| elapsed_time i stały kurs | scheduled_training_time, mentoring | TrainingEnrollment + TimeReservation |
| Podstawy i stopniowa aktywacja ocen | normy wszystkich ról | Observation + AssessmentSnapshot |

Wersja publicznego DTO i zdarzeń nie zależy od strategy_code; nowa strategia zmienia tylko wynik i własny params_version. Gdy B wymaga nowych pól publicznych, wydajemy nową wersję kontraktu z okresem zgodności. Tabele wersjonowane i pola rozszerzeń nie mogą być jednym nieograniczonym JSON. Nie projektować B przez funkcje bez użycia; dodać jego strategie dopiero w kartach konkretnych funkcji.

### 19.7. Panel, bramki i testy

Panel Kadry → Polityki A grupuje: rekrutację i cennik, płace/umowy, wypowiedzenia, grafiki/obsadę, urlopy/choroby, szkolenia, oceny HR. Każde pole ma jednostkę, zakres, opis, podgląd skutków i wersję. Owner admin zatwierdza bez komentarza; audyt actor/hash/time/zakres i bramka symulatora zgodnie z §29.5/§29.7. Nie można edytować historycznych wersji. Ceny PLN trzymamy w groszach; GBBL oddzielna jednostka. Nie mieszamy panelowego cennika z prywatną logiką Filament Resource.

Przed aktywacją A: (1) test 90 dni z co najmniej starterem, zmianami, chorobami, zwolnieniami i szkoleniami; (2) testy MySQL idempotencji i wspólnego kalendarza; (3) brak podwójnego OPEX rafinerii i pensji; (4) sprawdzenie możliwości obsadzenia FTUE; (5) symulator balansu na kandydacie YAML 0 FAIL i archiwizacja hash; (6) testy API/Filament uprawnień, braku wycieku cech, PL/EN/DE i mobile. Ten dokument jest decyzją zakresu oraz propozycją cen, nie dowodem wykonania tych testów.

Status po tej decyzji: otwarte pozostają **kalibracja** cen/ryzyka/marży, rzeczywiste baseline HR, wykonanie [SQL Staff A](OILEMPIRE_KADRY_DDL_MYSQL84.sql) na MySQL 8.4, testy [OpenAPI Staff A](OILEMPIRE_KADRY_OPENAPI_V1.yaml) i integracja safe-stop z domenami. Nie wpisujemy „zamknięte” w historycznym rejestrze DOM przed propagacją zmian do kart i testów.



### 19.8. Podział kodu Staff — obowiązkowa granica implementacji

Staff jest modułem odpowiedzialnym za kadry, **nie jedną klasą ani jednym serwisem**. Docelowy podział wewnętrzny:

| Część | Właściciel zapisu | Przykładowe akcje |
|---|---|---|
| Recruitment | kampanie, kandydatury, oferty | QuoteCampaign, StartCampaign, MakeOffer |
| Employment | zatrudnienia, wersje umów, aneksy, zakończenia | HireEmployee, AmendContract, EndEmployment |
| Scheduling | grafiki, rezerwacje czasu, przydziały | PublishRoster, ReserveTime, ReleaseAssignment |
| Absences | wnioski urlopowe, epizody chorobowe, zastępstwa | ApproveLeave, RecordIllness, RequestReplacement |
| Payroll | należności pracownicze i składniki naliczeń | AccruePay, FinalizePay; księgowanie przez Finance/Ledger |
| Training | zapisy, postęp, egzaminy, uprawnienia | EnrollTraining, CompleteExam |
| Assessments | dowody i oceny HR, kolejka analiz | RecordObservation, RequestAssessment, PublishAssessment |

Każda akcja ma własną odpowiedzialność, walidację wejścia, autoryzację, idempotencję i testy. Nie tworzymy `StaffService` prowadzącego wszystkie procesy ani kontrolera/Resource Filament z logiką gry. Wspólne małe kontrakty, np. EmployeeRef, AvailabilityQuery, TimeReservation i EmploymentTerms, łączą części Staff; nie dają im prawa do bezpośredniej zmiany cudzych tabel.

Jedna czynność domenowa ma jeden tor wykonania: web Vue, mobilne API, panel Filament, job i sweeper wywołują właściwą akcję lub usługę aplikacyjną. Transport odpowiada za uwierzytelnienie, walidację i odpowiedź, a reguły i zapis wykonuje właściciel. Akcje nie wywołują wzajemnie prywatnych repozytoriów; przepływ między częściami odbywa się przez publiczny kontrakt/zdarzenie z jednoznacznym właścicielem transakcji.

Staff nie księguje gotówki samodzielnie. Payroll wystawia należność i komponent z unikalnym source key, Finance/Ledger wykonuje zapis finansowy raz. Scheduling jest źródłem dostępności; Recruitment i Assessments korzystają z jego zapytań, nie prowadzą własnego kalendarza. Assessment nigdy nie otrzymuje ukrytego profilu jako wejścia.

Wariant B rozwija wybraną część przez nową wersję katalogu lub zarejestrowaną strategię. Nie wymaga kopiowania modułu, równoległego endpointu z drugą logiką ani przepisania Payroll po zmianie Recruitment. Migracje i publiczne kontrakty wersjonujemy tylko tam, gdzie faktycznie zmieniają pola lub semantykę.

Przed kodowaniem każdej funkcji karta implementacyjna wskazuje: część Staff, akcję, właściciela tabel, publiczne kontrakty, zdarzenia, transakcję/lock order, DTO i testy. Code review odrzuca duplikację tej samej reguły w web/API/Filament oraz bezpośredni zapis danych części przez inną część. Ta zasada obowiązuje również nowe funkcje wariantu B.
