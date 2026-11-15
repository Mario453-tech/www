# §31. Fabryka i rafineria — trwała gałąź gospodarki paliwowej

Wszystkie kwoty w tej sekcji zapisano jako PLN zgodnie z polską wersją gry. W innych językach zmienia się wyłącznie wyświetlana nazwa tej samej waluty (§24). Wszystkie liczby poniżej są wartościami startowymi `game_balance` do kalibracji w symulatorze; nie są obietnicą stałego zysku.

## 31.1. Rola w rozgrywce

Rafineria pozwala graczowi zdecydować, czy sprzedać baryłkę ropy, czy kupić lub zachować ją jako wsad, ponieść koszty przerobu i sprzedać kilka produktów na różnych rynkach. Wprowadza nowe decyzje: dobór instalacji, obsady i receptury, rezerwację ropy dla kontraktów, zarządzanie zbiornikami oraz wybór między szybką sprzedażą a dostawą na termin.

Nie zastępuje wydobycia. Odwiert bez rurociągu dopływowego nadal nie produkuje (§9.4), a własna ropa musi przejść przez łańcuch odwiert → hub → magazyn (§11A). Rafineria pobiera ją dopiero z magazynu głównego. Może też przerabiać ropę kupioną od innych graczy lub na rynku. Dzięki temu wyczerpanie własnych złóż nie zamyka tej gałęzi gry: dojrzała firma może stać się nabywcą surowca i producentem paliw. Nie ma przy tym gwarantowanego dochodu bez końca — ograniczają go koszt wsadu, dostępna ropa, chłonność odbiorców i koszty utrzymania.

Moduł jest inwestycją środkowej i późnej fazy gry, nie częścią pierwszych 30 minut. Mała instalacja daje możliwość poznania przerobu; duży kompleks wymaga znacznego kapitału, załogi, logistyki i stałych odbiorców. Rywalizacja obejmuje dostęp do ropy, jakość i terminowość dostaw, a nie wyścig o jeden nieodnawialny „slot rafinerii”.

## 31.2. Budowa, pozwolenia i klasy obiektów

Gracz wybiera region z dostępną działką przemysłową. Działka nie przenosi ropy: wymagany jest magazyn główny w tym regionie albo opłacony, fizyczny dowóz z innego regionu. Przed rozpoczęciem budowy potrzebne są: projekt technologiczny, pozwolenie infrastrukturalne drugiego stopnia (§11A.2) oraz nowe pozwolenie środowiskowe dla wskazanej klasy i mocy. Wniosek rozpatruje dział prawny; strajk tego działu wstrzymuje procedowanie (§5.1). Kredyt pochodzi ze zwykłego systemu §17.1: obowiązują scoring, limit i Dyrektor Finansowy. Rafineria nie tworzy osobnej, łatwiejszej linii kredytowej.

| Klucz `game_balance` | Mała rozdzielnia | Średnia rafineria | Duży kompleks |
|---|---:|---:|---:|
| `refinery.capacity_bbl_h` | 120 | 400 | 1 000 |
| `refinery.capex` | 18 mln | 75 mln | 240 mln |
| `refinery.build_hours` | 72 h | 168 h | 336 h |
| `refinery.fixed_opex_per_h` | 1 800 | 6 000 | 16 000 |
| `refinery.crude_buffer_bbl` | 3 000 | 10 000 | 25 000 |
| `refinery.product_tank_bbl_each` | 2 000 | 7 000 | 18 000 |
| `refinery.minimum_credibility` | 60 | 70 | 80 |

Pojemność zbiornika produktowego dotyczy każdego z czterech produktów osobno; zbiornik wsadu jest dodatkowy. Pojemności nie łączą się z limitem magazynu ropy ani z premiową rozbudową bazy (§22.6).

| Etap | Udział w nakładzie | Udział w czasie | Wynik |
|---|---:|---:|---|
| Projekt i ocena wpływu | 5% | 10% | dokumentacja do wniosków |
| Roboty i zbiorniki | 45% | 45% | obiekt w budowie |
| Montaż instalacji | 40% | 35% | obiekt gotowy do prób |
| Rozruch i odbiór | 10% | 10% | możliwość przejścia do pracy |

Opłata za pozwolenie drugiego stopnia pozostaje zgodna z §11A.2 — startowo 500 000 PLN, z tamtejszym czasem rozpatrzenia. Pozwolenie środowiskowe: startowo 250 000 / 1 mln / 3 mln PLN; rozpatrzenie 24 / 48 / 72 h. Są to opłaty dodatkowe wobec `capex`. Roboty można zacząć dopiero po obu decyzjach.

Każdy etap ma `started_at`, `ends_at` i wersję balansu. Jego koszt jest pobierany przed startem, atomowo przez FTS. Brak pieniędzy między etapami daje stan `building_paused_funds`: wykonane prace zostają, termin kolejnego etapu nie biegnie, a opłata za zabezpieczenie placu wynosi startowo 5% zwykłego kosztu stałego na godzinę. Rezygnacja nie zwraca opłat za pozwolenia i ukończone etapy; niedokończone aktywo może zostać sprzedane na aukcji. Brak obsady lub wsadu po odbiorze nie cofa budowy — obiekt pozostaje gotowy, lecz nie produkuje.

Rozbudowa małej instalacji do średniej i średniej do dużej wymaga nowej decyzji środowiskowej, dopłaty różnicy nakładów i 60% czasu budowy wyższej klasy. W trakcie prac istniejąca instalacja pracuje z wydajnością 70%; gracz może zamiast tego ją wyłączyć. Zbiorniki można rozbudować osobno: startowo +50% pojemności danego produktu za 1 / 3 / 8 mln PLN i 24 / 48 / 72 h. Żadna rozbudowa nie omija ograniczeń regionu.

## 31.3. Instalacje, receptury i bilans baryłek

Jednostką wejścia i wyjścia jest bbl. Na pierwszym etapie gry wszystkie gatunki ropy mają wspólny model wsadu; późniejsze zróżnicowanie jakości ropy wymaga osobnej zmiany bilansu, a nie ukrytej zmiany receptur.

| Receptura — z 100 bbl ropy | Benzyna | Olej napędowy | Nafta lotnicza | Mazut | Gaz/straty procesowe | Koszt zmienny |
|---|---:|---:|---:|---:|---:|---:|
| Destylacja atmosferyczna | 32 bbl | 35 bbl | 15 bbl | 14 bbl | 4 bbl | 42 PLN/bbl wsadu |
| Destylacja + kraking | 40 bbl | 36 bbl | 10 bbl | 10 bbl | 4 bbl | 55 PLN/bbl |
| Destylacja + hydrooczyszczanie | 30 bbl | 34 bbl | 22 bbl | 10 bbl | 4 bbl | 60 PLN/bbl |

Każdy wiersz sumuje się do 100 bbl. Gaz i straty procesowe są jawnym ubytkiem w rejestrze materiałowym, bez przychodu w tej wersji; nie mogą po cichu trafić do magazynu. Kraking i hydrooczyszczanie są alternatywnymi recepturami dla danej porcji wsadu, a nie kolejnymi premiami procentowymi nakładanymi na ten sam wsad. Rozliczenie równoległych linii wymaga przypisania konkretnego wolumenu każdej z nich.

| Jednostka | Nakład mała / średnia / duża | Montaż | Skutek |
|---|---:|---:|---|
| Destylacja atmosferyczna | w cenie obiektu | w czasie budowy | obowiązkowa; receptura podstawowa |
| Kraking | 6 / 20 / 60 mln PLN | 48 / 96 / 168 h | otwiera recepturę benzynową |
| Hydrooczyszczanie | 8 / 25 / 70 mln PLN | 48 / 96 / 168 h | otwiera recepturę z większą ilością paliwa lotniczego |
| Filtry i wychwyt | 2 / 7 / 20 mln PLN | 24 / 48 / 96 h | emisje ×0,6; ryzyko kary za przekroczenie normy ×0,7 |
| Laboratorium | 1 / 3 / 8 mln PLN | 24 / 48 / 72 h | dopuszcza certyfikację jakości premium |

Podane koszty zmienne obejmują energię, katalizatory i materiały; ich rozbicie księgowe startowo wynosi odpowiednio 60% / 25% / 15%. Nie ma osobnego zużycia „magicznych” katalizatorów bez zapisanej dostawy lub kosztu. Koszt stały, pensje, transport, podatek i opłata środowiskowa są liczone dodatkowo.

Jakość ma trzy proste klasy: `standard`, `premium`, `off_spec` (poza normą). Benzyna, olej i paliwo lotnicze uzyskują `standard` przy sprawnej instalacji i badaniu partii; `premium` wymaga laboratorium, właściwej jednostki i zdanej kontroli jakości, a daje startowo premię ceny NPC do 5%, wyłącznie w wydzielonym popycie na taką klasę. Nie symulujemy pozornie precyzyjnej oktanowości i liczby cetanowej, dopóki nie mają wpływu na decyzje gracza. Partii `off_spec` nie wolno sprzedać jako paliwa lotniczego ani dostarczyć do kontraktu wymagającego normy. Może zostać przerobiona ponownie za opłatą albo sprzedana jako produkt obniżonej jakości, jeśli istnieje na niego jawny odbiorca. Mazut ma klasę zwykłą lub skażoną.

Wsad pobiera się wyłącznie z wolnej ropy w magazynie: nie z `reserved_bbl`, `locked_in_escrow_bbl` ani z kursu w drodze. Zakupiona ropa podlega temu samemu fizycznemu przyjęciu. Przekazanie do zbiornika wsadu i przerób są ruchami materiałowymi; nic nie znika wskutek samego odjęcia liczby od pola magazynu.

## 31.4. Ruch, zbiorniki i utrzymanie

Gracz ustawia recepturę i docelową moc od 25% do 100%. Produkcja w stałym segmencie czasu:

`wsad = min(wolna_ropa_w_zbiorniku_wsadu, moc_bbl_h × sprawność × obsada × mnożnik_strajku × Δt_h, miejsce_w_zbiornikach_wynikające_z_receptury)`

`produkt_i = wsad × udział_i`; `koszt_zmienny = wsad × stawka_receptury`.

Sprawność startowo wynosi `max(0,20; condition/100)` przy kondycji co najmniej 20%; poniżej 20% jednostka zostaje bezpiecznie zatrzymana. Niewystarczające miejsce choćby dla jednego produktu ogranicza cały wsad do ostatniej porcji, którą można pomieścić. Nadwyżki nie są automatycznie sprzedawane ani rozlewane. Rozliczenie dzieli czas w chwili zapełnienia zbiornika, zmiany receptury, obsady, ceny zdarzenia światowego, strajku, odbioru transportu i zakończenia przeglądu (§23.4).

Zużycie podczas pracy: startowo 0,035 / 0,045 / 0,055 punktu kondycji na godzinę dla destylacji / krakingu / hydrooczyszczania przy pełnej mocy; przy 25% mocy połowa tej stawki. Budżet techniczny i umiejętności konserwacji działają według jednej funkcji mnożników, zgodnej z §11A.6. Obowiązkowy przegląd co 72 h pracy danej jednostki: 4 h postoju, koszt 0,5% jej nakładu. Przegląd zeruje licznik zaległości, ale nie przywraca kondycji. Naprawa przez TTS lub firmę zewnętrzną przywraca kondycję i wymaga osobnego zadania. Zaległy przegląd zwiększa ryzyko incydentu ×1,5, jawnie na ekranie.

Paliwa w szczelnych zbiornikach nie „psują się” tylko dlatego, że gracz jest offline lub załoga strajkuje. Mogą natomiast zostać skażone przez konkretny incydent, z zapisanym wolumenem i przyczyną.

## 31.5. Incydenty, środowisko i bezpieczeństwo

Zezwolenie środowiskowe określa moc, emisję na bbl i maksymalną emisję dobową. Startowo opłata emisyjna to 8 PLN/bbl wsadu, płatna do `SYS_TAX`; przekroczenie limitu nie jest tańszą metodą produkcji: automatycznie ogranicza moc do dopuszczalnej. Rozbudowa mocy wymaga zmiany pozwolenia. Filtry zmniejszają emisję, ale nie znoszą opłaty całkowicie: opłata po filtracji ma podłogę 4 PLN/bbl. Kontrole środowiskowe startowo co 30 dni aktywnej pracy oraz po incydencie `major` lub `catastrophe`; rozstrzygają udokumentowany stan obiektu, nie karzą losowo za samą własność rafinerii.

| Zdarzenie | Bazowe ryzyko na godzinę pracy | Skutek startowy |
|---|---:|---|
| Awaria jednostki `minor` | 0,020% | postój 2–8 h, zadanie naprawy |
| Skażenie partii `minor` | 0,010% | wyłącznie wskazana partia `off_spec`, badanie i przerób |
| Pożar `major` | 0,003% | postój 12–36 h, kondycja −10 do −25 pkt, koszt gaszenia |
| Eksplozja lub duży wyciek `catastrophe` | 0,0005% | postój, udokumentowana strata z dotkniętych zbiorników, koszt usunięcia szkód i kontrola |

Stawki są do kalibracji statystycznej, nie należy ich bezpośrednio nazywać „szansą na tick”. Dla odcinka Δt stosuje się agregację z §23.6; najwyżej jedno rozstrzygnięcie danego rodzaju na okno ryzyka, z osobnym losowaniem dotkliwości. Bazę mnoży BHP z §17.4: poziom procedur, ich integralność, `skill_safety`, budżet BHP i strajk techniczny. Kondycja poniżej 40% daje dodatkowo ×1,5, zaległy przegląd ×1,5; iloczyn jest ograniczony startowo do ×8. Ochrona nie redukuje ryzyka do zera. Każde zdarzenie ma `source_entity_id`, ziarno, wersję balansu i zapis strat. Wyjątek §27.1: przez pierwsze 48 h nowego gracza wynik nie przekracza `micro`.

Kara za potwierdzone przekroczenie normy: startowo 50 000 PLN + 20 PLN za bbl przekroczenia, maksymalnie 5% wartości obiektu na jedną kontrolę. Katastrofa ekologiczna stosuje istniejące −15 `company_credibility` (§2), z obowiązkowym wpisem w jej dzienniku i pośrednią propagacją 50% delty do reputacji B2B; nie nakłada się drugi raz tej samej kary za to samo zdarzenie. Obowiązek usunięcia szkód jest oddzielnym kosztem. Taki incydent jest powiadomieniem `critical` (§27).

Nie wprowadzam „lewej produkcji” w MVP. Surowiec kupiony na czarnym rynku może wejść do legalnej instalacji tylko jako normalnie zarejestrowana partia ropy; sam zakup nadal zwiększa `black_market_score` zgodnie z §2. Przerób nie „pierze” pochodzenia partii. Ewentualna późniejsza nielegalna linia wymaga osobnego projektu, jawnego ryzyka wykrycia i zakazu dostaw do legalnych kontraktów — nie może być domyślnym przełącznikiem zwiększającym zysk.

## 31.6. Kadry i strajki

| Klasa obiektu | Operatorzy na zmianie | Inżynier chemik | Laborant | Technik utrzymania |
|---|---:|---:|---:|---:|
| Mała | 2 | 1 współdzielony do 1 obiektu | 1 | 1 współdzielony do 2 obiektów |
| Średnia | 5 | 1 | 2 | 1 |
| Duża | 12 | 2 | 4 | 3 |

Tabela oznacza minimalną aktywną obsadę symulacyjną, nie rzeczywisty grafik zmianowy. Pracownik `in_training`, w TTS albo przypisany już na 100% do innego obiektu nie liczy się ponownie (§6, §11A). Brak operatorów lub chemika zatrzymuje produkcję; brak laboranta blokuje zwolnienie nowych partii paliw jakościowych, ale nie usuwa istniejących zapasów; brak technika wstrzymuje własne przeglądy i naprawy, pozostawiając droższą usługę zewnętrzną.

Nowe subumiejętności 0–10: `skill_refining` operatora (startowo +1% skutecznej mocy za poziom, maks. +10%, bez tworzenia wsadu), `skill_process_control` chemika (−1% kosztu zmiennego za poziom, maks. −10%), `skill_product_quality` laboranta (+2 punkty procentowe szansy poprawnego badania za poziom, maks. +20). `skill_safety` i `skill_maintenance` pozostają wspólnymi umiejętnościami §6. Kursy I–III kosztują startowo 20 000 / 50 000 / 100 000 PLN, trwają 24 / 48 / 72 h i używają istniejącego egzaminu, limitu subumiejętności i wyłącznego przydziału (§6).

Personel produkcyjny należy do nowego działu „Rafineria” w mechanice morale §4–§5. Aktywny strajk operatorów zatrzymuje nowy przerób, lecz nie kasuje partii, nie zatrzymuje kursów już w drodze i nie zwalnia automatycznie z kontraktów. Strajk logistyki ogranicza dowóz i wywóz według §11A.6; techniczny blokuje własne naprawy oraz stosuje mnożnik ryzyka §17.4. Zasady mediatora i ochrony offline pozostają z §19.2. Nie dodaje się obowiązkowego szóstego fotela zarządu: nadzór technologiczny sprawuje Dyrektor Techniczny, a umowy i budżety podpisuje Dyrektor Finansowy. Osobny dyrektor produkcji może być późniejszym rozszerzeniem, nie warunkiem działania istniejących firm.

## 31.7. Cztery rynki produktów i transport

| Produkt | Odbiorca NPC | Cena odniesienia przy ropie 250 PLN/bbl | Chłonność bazowa regionu |
|---|---|---:|---:|
| Benzyna | sieci stacji | 430 PLN/bbl | 12 000 bbl/dobę |
| Olej napędowy | przewoźnicy | 420 PLN/bbl | 12 000 bbl/dobę |
| Nafta lotnicza | lotniska | 440 PLN/bbl | 4 000 bbl/dobę |
| Mazut | przemysł | 210 PLN/bbl | 6 000 bbl/dobę |

Są to wspólne pule regionu dla wszystkich sprzedających, nie limity przyznawane każdej fabryce osobno. Popyt odnawia się liniowo w czasie, z ewidencją w kroczącym oknie 24 h. Niewykorzystana chłonność nie gromadzi się bez końca. Sprzedaż natychmiastowa pobiera wolną część puli; ponad nią gracz musi poczekać, dostarczyć towar gdzie indziej lub sprzedać innemu graczowi. Minimalny skup NPC w tej gałęzi nie jest gwarantowany — gwarancja płynności rynku ropy z §19.3 nie oznacza gwarancji zbytu każdej ilości paliwa.

Cena odniesienia każdego produktu jest aktualizowana z ceną ropy i oddzielnym składnikiem popytu:

`cena_odniesienia_i = cena_ropy × współczynnik_i + stała_i + korekta_popytu_i + wydarzenia_i`

Współczynniki i stałe są tak dobrane, by przy ropie 250 PLN/bbl dawać wartości z tabeli; startowe współczynniki benzyny / oleju / nafty / mazutu: 0,90 / 0,85 / 0,95 / 0,70. Korekta popytu ograniczona jest do ±15% ceny odniesienia, a wydarzenie z §25 może ją zmienić w swoich jawnych granicach. Średni składnik ponad koszt ropy nie jest gwarantowaną marżą: spadek popytu lub wzrost kosztów może uczynić przerób nieopłacalnym. Sezonowość to zapowiadane zdarzenia lub przewidywalne krzywe popytu, nie ukryte rzuty przy sprzedaży. Przykłady: święta zwiększają popyt na paliwo lotnicze; kryzys ogranicza benzynę; embargo zmienia dostępność wsadu.

Poślizg ceny liczy się od łącznej sprzedaży danego produktu w regionie w kroczącym oknie 60 min, tak jak przy ropie (§12, §19.3): podział partii i kilka firm w kartelu nie resetują licznika. Zlecenie limit rezerwuje określony produkt, klasę i wolumen w zbiorniku; nie wykonuje się poniżej limitu ani ponad fizyczny popyt. Gracz bez Dyrektora Finansowego zachowuje sprzedaż spot tylko w awaryjnym limicie 20 000 PLN/h z §10, wspólnym dla ropy i paliw — nie osobnym na każdy rynek. Własne automatyczne oferty i zakup od własnych kont powiązanych podlegają kontroli §13.

Kontrakty produktowe wykorzystują §13, a nie równoległy system kar. Dla NPC: cena `fixed`, `market_multiplier` lub `market_plus_bonus`, kaucja 10% wartości, kara 5% wartości niedostarczonej części transzy, −6 `contract_reputation`, przepadek kaucji dopiero po trzech kolejnych niewykonanych transzach. Umowa podaje produkt, klasę, wolumen, miejsce, terminy transz i dopuszczalny margines jakości. Przed podpisaniem interfejs pokazuje prognozę pokrycia wsadem, mocą i zapasem; prognoza nie zastępuje dostawy. Strajk nie znosi kar bez klauzuli siły wyższej (§21).

Sprzedaż między graczami rozszerza §13.2: przy `accepted` blokuje się 100% zapłaty kupującego w `PLN_ESCROW` oraz konkretną partię produktu u sprzedającego; po fizycznym rozładunku powstaje `delivery_receipt`, a odpowiednia transza depozytu jest wypłacana. Termin, spór, częściowa strata, pełny magazyn kupującego i pierwszeństwo protokołu rozładunku nad automatycznym zwrotem działają tak samo jak w §11A.8. Kartel nie płaci prowizji depozytowej zgodnie z §26, lecz nadal podlega blokadzie towaru, dowodowi dostawy i kontroli kont powiązanych.

Produkt nie płynie rurociągiem ropy i nie jest wożony cysterną surowcową bez przystosowania. Startowo transport odbywa się osobnymi cysternami produktowymi: pojemność 400 bbl, zakup 180 000 PLN, koszt kursu 1 500 PLN, bazowe 18 min, rozładunek 400 bbl/min; pojazd ma przypisany dopuszczalny produkt i nie wykonuje dwóch kursów naraz. Zmiana produktu wymaga czyszczenia: 2 h i 5 000 PLN; bez czyszczenia kurs jest blokowany, nie „losowo skażony”. W późniejszej wersji można dodać dedykowany rurociąg produktowy oraz terminal morski z odrębnymi zbiornikami, miejscami rozładunku, ETA i pozwoleniem — istniejący rurociąg od odwiertu do huba nie zmienia funkcji.

## 31.8. Ekonomia trwałego świata

Przy recepturze podstawowej, cenach z §31.7 i ropie po 250 PLN/bbl przychód ze sprzedaży produktów przypadających na 1 bbl wsadu wynosi 380 PLN. Po odjęciu 250 PLN za ropę, 42 PLN kosztu zmiennego i 8 PLN opłaty emisyjnej zostaje 80 PLN na pokrycie kosztów stałych, pensji, transportu, podatków, ryzyka i zysku. Własna ropa także ma koszt alternatywny 250 PLN — porównanie z „darmową” ropą zawyżyłoby rentowność.

Przykład do strojenia: mała rafineria przy 85% wykorzystania mocy 120 bbl/h przerabia 2 448 bbl/dobę. Po kosztach stałych 43 200 PLN/dobę, przykładowych pensjach 45 000 PLN/dobę i założonej rezerwie 5 PLN na transport oraz naprawy na bbl pozostaje około 95 400 PLN/dobę przed podatkiem majątkowym, finansowaniem i większymi incydentami. Nakład 18 mln PLN zwracałby się w tym uproszczeniu po około 189 dniach. To cel kalibracji, nie wynik zapewniony graczowi: trzeba sprawdzić chłonność rynku, nakład na pozwolenia i zbiorniki, cenę zakupu ropy, kredyt, przestoje oraz konkurencję. Proponowany przedział zwrotu dla sprawnie prowadzonej małej instalacji to 180–300 dni; duży kompleks nie powinien uzyskiwać krótszego zwrotu wyłącznie przez mnożenie skali.

Źródłem nowych pieniędzy jest wyłącznie zakup przez NPC (`SYS_MARKET_MAKER`). Handel między graczami przenosi istniejące pieniądze. Ujścia to nakłady, OPEX, opłaty, kary, podatki i zużycie aktywów; każda płatność ma konto przeciwstawne FTS. Produktów nie uwzględnia się w podatku majątkowym jako „gotówki”, ale zapas i rafinerię uwzględnia się w majątku netto według ostrożnej wyceny. Dla podstawy podatku §19.1 rafineria jest aktywem trwałym liczonym w 70% wyceny, tak jak inne trwałe obiekty; nie tworzy zwolnienia podatkowego przez kupienie budynku.

Wycena rafinerii: `koszt_odtworzenia ukończonej klasy i jednostek × (0,25 + 0,75 × średnia ważona kondycja/100) × mnożnik_regionu`, ograniczona do 100% bieżącego kosztu odtworzenia. Budowa w toku: wyłącznie opłacone i ukończone etapy ×0,5. Zapasy ropy i produktów liczy się oddzielnie po ostrożnej cenie możliwej sprzedaży, z uwzględnieniem dostępnego popytu; dług i zobowiązania odejmuje się raz. Depozyt B2B pozostaje własnymi środkami w odpowiednim zakresie §19.1. Ta sama funkcja wyceny obsługuje scoring, progi firmy, tarczę, licytację i rankingi; nie wolno każdemu modułowi liczyć majątku inaczej.

Nie nakładam sztywnego limitu liczby rafinerii na firmę: premiowałby pierwszych graczy w świecie bez resetu. Ograniczeniami są regionalne działki i pozwolenia przydzielane także przy nowych zagłębiach (§19.4), wspólny popyt NPC, koszt utrzymania pustej mocy, dostęp do pracowników i wsadu oraz malejąca opłacalność dominacji. Nowe regiony powinny mieć otwartą pulę działek dla młodszych firm na zasadach zbliżonych do 70% pierwszeństwa koncesji we frontierach. Dla karteli liczy się łączny udział członków w sprzedaży paliw regionu; startowo po przekroczeniu 40% przez 7 dni urząd publikuje ostrzeżenie i blokuje nowe wyłączne kontrakty NPC w tym regionie, nie konfiskuje istniejących obiektów. Reguła wymaga symulacji, aby nie karać jedynego działającego dostawcy.

Rafineria firmy `dormant` nie produkuje; jej zobowiązania i aukcja po 90 dniach podlegają zasadom §19.2 i §19.4. Urlop zamraża procesy zgodnie z §19.2, ale nie jest dostępny przy aktywnej katastrofie, strajku lub dostawie B2B. Zajęcie rafinerii w egzekucji wymaga rozszerzenia zasad aukcyjnych §17.2: istniejące partie i kursy zachowują właściciela, umowy nie przechodzą automatycznie na nabywcę, a załoga trafia do rezerwy poprzedniej firmy. Nie wolno sprzedać zbiornika wraz z cudzym towarem powierzonym do transportu.

## 31.9. Granica złotych baryłek

Za GBBL można kupić wystrój zakładu, nazwę i emblemat, wykresy oraz alerty o stanie instalacji, zapis układu pulpitów i wygodne szablony ręcznych decyzji. Automatyczne powiadomienie nie może wyprzedzać danych dostępnych darmowym graczom. Funkcje sterowania wsadem i transportem muszą mieć darmowy, używalny odpowiednik; płatna wygoda nie zwiększa liczby cykli produkcji ani pierwszeństwa skupu NPC.

Nie można kupić mocy, wydajności receptury, większego udziału w popycie NPC, pierwszeństwa rozładunku paliwa, filtrów, pozwolenia, jakości partii, gwarancji egzaminu lub ochrony przed karą za GBBL. Już istniejąca Kroplówka z §24.3 pozostaje ograniczonym źródłem gotówki, więc może pośrednio współfinansować dowolny zakup w grze; nie tworzy się dla rafinerii dodatkowego przelicznika ani zwolnienia z limitów tieru. Prywatna Straż z §22.3 dotyczy opisanego tam aktywnego pożaru głowicy i blowoutu — nie rozszerza się jej milcząco na rafinerię.

## 31.10. Architektura i kolejność rozliczenia

Nowy moduł `modules/Refining` posiada własną logikę obiektów, receptur, partii i stanu technicznego. Korzysta z istniejących usług `Logistics`, `Market`, `Staff`, `Legal`, `Incidents` i Core/FTS; nie prowadzi własnego salda pieniędzy ani drugiego silnika czasu.

Maszyny stanów:

| Obiekt | Stany i dozwolone przejścia |
|---|---|
| Rafineria | `planned → permitting → building → commissioning → active ⇄ paused_input / paused_storage / paused_staff / paused_funds / maintenance → active`; `active → damaged → repairing → active`; `building → building_paused_funds → building`; `active → seized / dormant`. `damaged` nie przechodzi do `active` bez naprawy i odbioru. |
| Jednostka | `planned → installing → ready → running ⇄ idle / maintenance`; `running → failed → repairing → ready`; zmiana receptury tylko po zakończeniu bieżącej porcji. |
| Partia produktu | `producing → quarantined → certified_standard / certified_premium / off_spec → stored → reserved_sale / reserved_b2b / in_transit → delivered / sold`; osobne zakończenia `reprocessed`, `lost`. Nie ma powrotu ze `sold` do zbiornika. |
| Kontrakt produktowy | stany NPC z §13 dla transz; stany B2B `draft → published → accepted → in_delivery → delivered → settled`, z `disputed → resolved / refunded` i wyjątkami §13. Każda transza ma własny dowód i rozliczenie. |

Nowa podfaza fizyczna to `2.9 Refining`, po kredycie ropy do magazynu w §11A.8. Zajmuje się pobraniem wsadu, przerobem, stanami partii i napełnieniem zbiorników. W fazie 3 globalny sweeper wyznacza ceny i dopasowuje zlecenia czterech rynków; w fazie 4 obsługiwane są transze kontraktów i depozyty. Faza 5 księguje zmienny i stały OPEX, pensje, emisje, raty oraz podatki; faza 6 aktualizuje kadry, faza 7 rozstrzyga incydenty rafineryjne, a faza 8 kontrole, wygasania i inne zdarzenia zapadłe. Incydent w fazie 7 wpływa na następny segment rozliczenia, nie kasuje z mocą wsteczną produktu sprzedanego w fazie 3. Granice czasu zdarzeń tną długie Δt; cen globalnych i dopasowania ofert nie uruchamia żądanie pojedynczego gracza (§23.3, §23.7).

Stosuje się wspólne `SettlementService`, blokady wierszy, CAS `last_calc_at`, `due_slot` i klucze idempotencji z §23. W razie równoległego pobrania ropy przez sprzedaż spot i rafinerię zwycięża tylko jedna transakcja; druga ponawia sprawdzenie wolnego stanu. Obliczenia objętości mają `DECIMAL(16,4)`, pieniądze są `BIGINT` w groszach. Zaokrąglenie objętości następuje raz przy zapisaniu partii; różnica zaokrągleń trafia do jawnej pozycji bilansującej, a nie znika. Narzut kosztów za bbl gromadzi resztę grosza do kolejnego rozliczenia, aby częste żądania nie obniżały OPEX.

Rzuty mają klucz `HMAC(SERVER_SECRET, "refinery:{refinery_id}:{unit_id}:{event_type}:{due_slot}")`, gdzie slot jest zaokrąglony do pięciu minut; zapisuje się `secret_id`, `balance_version`, wynik i zakres godzin agregacji. Osobny typ zdarzenia zapobiega użyciu tego samego rzutu do pożaru i skażenia. Zmiana trybu pracy w środku slotu nie daje nowego losowania tego samego zagrożenia.

Minimalne tabele i pola:

| Tabela | Kluczowe pola |
|---|---|
| `refineries` | `id`, `player_id`, `region_id`, `class`, `status`, `condition`, `capacity_bbl_h`, `environmental_permit_id`, `last_calc_at`, `balance_version` |
| `refinery_build_stages` | `refinery_id`, `stage`, `status`, `cost_grosze`, `started_at`, `ends_at`, `idempotency_key` |
| `refinery_units` | `refinery_id`, `type`, `status`, `condition`, `wear_level`, `last_inspection_at`, `last_calc_at` |
| `refinery_tanks` | `refinery_id`, `product_type` lub `crude`, `capacity_bbl`, `used_bbl`, `reserved_bbl`, `locked_in_escrow_bbl`; unikalność obiekt + typ |
| `refinery_recipes` | `recipe_code`, pięć udziałów sumujących się do 100%, `variable_cost_grosze_per_bbl`, `balance_version` |
| `refinery_batches` | `refinery_id`, `unit_id`, `recipe_code`, `input_bbl`, wolumeny wynikowe, `quality`, `status`, `produced_at`, `balance_version` |
| `material_movements` | `owner_id`, `batch_id`, `source_type/id`, `target_type/id`, `product_type`, `volume_bbl`, `reason_code`, `sweep_no`, `idempotency_key` |
| `product_market_offers` | `player_id`, `product_type`, `quality`, `reserved_bbl`, `limit_price_grosze`, `status` |
| `product_contract_tranches` | `contract_id`, `product_type`, `quality`, `due_at`, `quantity_bbl`, `delivered_bbl`, `delivery_receipt_id`, `status` |
| `environmental_permits`, `refinery_inspections` | region, klasa, moc, limit emisji, termin, status, dowody, wynik |
| `product_transport_runs` | pojazd, partia, właściciel, źródło, cel, wolumen, ETA, stan, protokół dostawy |
| `refinery_incidents` | obiekt, rodzaj, klasa, dotknięte partie/zbiorniki, seed, `due_slot`, straty, stan naprawy |

`material_movements` rozszerza zasadę `oil_movements` z §11A: źródłowy ruch ropy do wsadu musi mieć powiązany ruch przerobu, a suma wejść musi odpowiadać produktom, gazowi i udokumentowanym stratom. Rekoncyliacja §29.2 sprawdza osobno każdą kategorię; produkt nie staje się ponownie ropą. Do najważniejszych kodów FTS należą `REFINERY_BUILD`, `REFINERY_UNIT_INSTALL`, `REFINERY_OPEX`, `REFINERY_EMISSION_FEE`, `REFINERY_INSPECTION_FINE`, `PRODUCT_SPOT_SALE`, `PRODUCT_CONTRACT_PAYOUT`, `PRODUCT_B2B_ESCROW_LOCK`, `PRODUCT_B2B_SETTLEMENT` i `PRODUCT_TRANSPORT`; straty materiałowe zapisuje się w `material_movements`, nie jako drugi przelew pieniężny. Wszystkie zapisy wskazują `actor_id`, `sweep_no` i klucz idempotencji.

## 31.11. Panel administratora i kontrola

W Filament należy dodać zasób „Rafinerie” z listą obiektów, etapów budowy, jednostek, obsady, kondycji, zbiorników, przepływu baryłek, kosztów i wersji balansu. „Rynek” pokazuje ceny i wykorzystanie popytu każdej z czterech kategorii w regionach. „Incydenty” pokazują ziarno losowania, zakres czasu, dotknięte partie, szkody i decyzję środowiskową. Widok 360° gracza otrzymuje zakładkę produkcji paliw.

`gm_readonly`, `gm_support` i `admin` mogą czytać te dane. `gm_support` i `admin` mogą wywołać incydent testowy lub rozstrzygnąć spór; na produkcji taki incydent musi być wyraźnie oznaczony jako działanie GM i podlegać zwykłym zapisom szkód. Korekta ilości produktu, zmiana stanu obiektu, emisji albo pieniędzy jest dostępna tylko dla `admin` poprzez ruch `GM_ADJUST` lub FTS — nigdy przez bezpośrednią edycję licznika. Edycja cen, receptur, pojemności i popytu w `game_balance` należy tylko do `admin`, wymaga drugiego administratora i nowej wersji (§18.3, §29.5). Praca już rozpoczęta używa wersji przypisanej do partii lub etapu; nowe działania nowej wersji. Każda akcja trafia do nieusuwalnego `gm_action_log` ze stanem przed i po. `gm_support` musi podać powód; administrator główny może go podać, ale nie musi (§18.3).

## 31.12. Testy odbiorcze blokujące wydanie

1. Przerób 100 bbl każdą recepturą daje dokładnie jej tabelę wyników, łącznie z gazem/stratą; bilans do 0,0001 bbl.
2. Ropa bez ukończonego łańcucha odwiert → hub → magazyn nie może stać się wsadem, nawet gdy gracz ma rafinerię.
3. Równoległy przerób, sprzedaż ropy spot, zlecenie limit i depozyt B2B nie zużywają tej samej baryłki dwukrotnie.
4. Zapełnienie jednego zbiornika zatrzymuje wsad na obliczonym plateau; 12 h offline nie nadpisuje pojemności.
5. Leniwe rozliczenie i zamiatacz tej samej jednostki dają jeden przerób, jeden koszt i jeden rzut incydentu; restart po zapisie partii nie powiela produktu.
6. Strajk rafinerii zatrzymuje nowy przerób, zachowuje istniejące partie i nie anuluje kursu w trasie; strajk logistyki stosuje granicę `u=0,60` z §11A.6.
7. Skażona partia nie spełnia kontraktu paliwa lotniczego; dowód rozładunku ma pierwszeństwo przed zwrotem depozytu, a częściowa dostawa rozlicza wyłącznie dostarczoną ilość.
8. Brak środków podczas budowy zatrzymuje wyłącznie następny etap; ukończone etapy i zapisy FTS pozostają. Uszkodzona jednostka nie daje się „wznowić” bez naprawy.
9. Ponowienie kary, opłaty emisyjnej i rozliczenia kontraktu jest bezskuteczne; salda FTS i bilans materiałowy uzgadniają się po odtworzeniu kopii.
10. Δt ponad 7 dni przechodzi w istniejący `dormant` (§23.5); urlop, ban i aukcja po 90 dniach zachowują właściciela oraz historię partii.
11. Wydarzenie światowe, zmiana balansu i zmiana receptury w środku okresu dzielą rozliczenie we właściwym czasie, bez naliczania nowej ceny wstecz.
12. Test obciążeniowy z rafineriami mieści całe rozliczenie 1 000 graczy w budżecie 60 s (§23.10); osobno mierzy czas nowej podfazy 2.9.
13. Symulacje wielu lat sprawdzają inflację, chłonność regionalną, zwrot nakładów, udział karteli, tempo aukcji i możliwość wejścia młodej firmy w nowych regionach — bez zerowania świata.

## 31.13. Etapy wdrożenia i ryzyka

MVP: jedna mała klasa rafinerii, destylacja podstawowa, cztery zbiorniki i rynki NPC, fizyczny pobór ropy z magazynu, obsada, pozwolenia, OPEX, podstawowy przegląd, bilans materiałowy, FTS oraz podfaza 2.9. Do wydania MVP niezbędne są już ograniczona chłonność popytu i testy równoległości; odłożenie ich stworzyłoby źródło niekontrolowanego pieniądza.

Wersja 2: średnia i duża klasa, kraking, hydrooczyszczanie, laboratorium, jakość, pełny katalog incydentów i kontroli, zlecenia limit oraz kontrakty NPC. Wersja 3: cysterny produktowe, handel B2B z depozytem i fizyczną dostawą, nowe zdarzenia światowe, ochrona przed dominacją regionalną. Wersja 4, dopiero po pomiarach: gatunki ropy, rurociągi produktowe, terminal morski i ewentualna nielegalna instalacja.

Największe ryzyka wykonawcze to przeciążenie zamiatacza przez liczbę partii, podwójne rozliczenie ropy i produktu, niejawne tworzenie baryłek przy zmianie receptury, zbyt łatwy zakup NPC oraz używanie różnych wycen tej samej fabryki w banku i podatkach. Przed rozbudową trzeba wdrożyć jedną księgę ruchów materiałowych, wspólną usługę rozliczeń, pomiar czasu podfaz i symulator balansu.

## Decyzje do podjęcia przez właściciela gry

1. Czy zatwierdzić docelowy przedział zwrotu małej rafinerii 180–300 dni, czy moduł ma być dostępny ekonomicznie wcześniej? Od tego zależą ceny zakładów i chłonność rynków.
2. Czy region ma mieć ograniczoną liczbę działek przemysłowych, a jeśli tak — jak zagwarantować miejsce firmom wchodzącym do trwałego świata po latach?
3. Czy w MVP produkty mogą być sprzedawane NPC bez osobnego kursu cysterny, jako uproszczony odbiór przy bramie rafinerii? Projekt zakłada tak wyłącznie dla NPC; B2B zawsze wymaga fizycznej dostawy.
4. Czy wprowadzić później gatunki ropy i gaz procesowy jako piąty produkt? Teraz gaz jest jawnym ubytkiem bez przychodu.
5. Czy udostępniać nielegalną produkcję w ogóle? Moja rekomendacja: nie w MVP; jej potencjalny zysk i kary wymagają osobnej symulacji.
6. Czy zaakceptować blokadę nowych wyłącznych kontraktów NPC dla kartelu dominującego w regionie, zamiast sztywnego limitu posiadania rafinerii?
7. Jak dokładnie potraktować początkowy „magazyn 20 000 bbl” z §1: jako samą pojemność czy pojemność wraz z ropą? To rozstrzygnięcie jest potrzebne do jednej kanonicznej wyceny majątku netto, tarczy i tieru firmy.
