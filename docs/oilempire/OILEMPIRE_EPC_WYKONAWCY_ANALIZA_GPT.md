A. Werdykt ogólny

Pomysł wyboru wykonawcy warto zachować, ale przedstawiony tekst nie nadaje się do bezpośredniego wpięcia jako §31.2.3. Najmocniejsza część to czytelny wybór między ceną, terminem i jakością oraz próba powiązania budowy z późniejszą eksploatacją. Blokują go jednak niezamknięte przepływy pieniędzy, nieistniejąca polisa budowy, niewymierne szkody, zmienne parametry kontraktu bez wersjonowania i zdarzenia zależne w praktyce od chwili uruchomienia sweepera. Co ważniejsze, przy podanych cenach nawet po uwzględnieniu proponowanego ryzyka szara strefa pozostaje zdecydowanie najtańszym wyborem; nie należy udawać, że sama „duża szansa awarii” równoważy rabat 35%. Rekomenduję wdrożenie dopiero po zastąpieniu propozycji spójną sekcją G i przetestowaniu ekonomii na danych z gry.

B. Błędy krytyczne i poprawki

1. Nazwy i zakresy przeczą zasadom projektu. „Krupp-Petro” zawiera nazwę rzeczywistej firmy; „Sino-Hydrocarbon” i część pozostałych nazw są niepotrzebnie bliskie rzeczywistym markom lub instytucjom. Ponadto F ma integralność 72% i ryzyko wady 45%, choć wcześniej zadeklarowano odpowiednio 75–100% i 2–35%; mnożnik 0,65 jest poniżej zadeklarowanego minimum 0,75. Poprawka: użyć wyłącznie fikcyjnych nazw i opublikować jeden obowiązujący zestaw zakresów. Przykładowy komplet nazw, po trzy na archetyp, znajduje się w G. Przed publikacją trzeba jeszcze sprawdzić kolizje nazw z rzeczywistymi podmiotami; same wymyślone brzmienia nie stanowią takiej weryfikacji.

2. Nie ma zamkniętego rachunku finansowego. Zastąpienie odbiorcy `SYS_TREASURY` przez firmę NPC wymagałoby rzeczywistych kont wykonawców, zasad ich finansowania i niewypłacalności. Propozycja ich nie definiuje. Poprawka dla wersji pierwszej: firmy NPC są ofertami systemowymi, a istniejące `REF_BUILD_STAGE` nadal prowadzi z `PLN_BANK` do `SYS_TREASURY`; identyfikator wykonawcy trafia do metadanych operacji. Część nielegalna ma osobny ruch z `PLN_CASH`. Kara za opóźnienie wychodzi z `SYS_TREASURY`, a nie z nieistniejącego salda NPC. Pełny wykaz ruchów jest w G. Gwarancyjna naprawa bez opłaty gracza nie jest przelewem na zero groszy: księguje się tylko rzeczywisty koszt naprawy po stronie systemu, a czas postoju pozostaje kosztem gracza.

3. Zmiana wiersza `refinery_contractors` nie może wstecznie zmieniać podpisanych umów. Poprawka: słownik przechowuje tożsamość i nazwę firmy; liczby są w wersjonowanym `game_balance`. Przy podpisie zapisuje się niezmienną migawkę parametrów, `balance_version` i sumę kontrolną oferty. Zmiana balansu wymaga zatwierdzenia przez drugiego administratora i działa wyłącznie na nowe umowy. Samo dodanie kolumny `balance_version` do mutowalnego wiersza firmy nie wystarcza.

4. „Polisa placu budowy” nie istnieje w §31.6. Nie wolno obciążyć istniejącej puli eksploatacyjnej bez ustalenia składki, zakresu i limitów. Rekomenduję na początek świadomie zrezygnować z takiej polisy. Koszt katastrofy określa z góry kontrakt: w wersji z G gracz płaci naprawę 4% bazowego CAPEX i traci czas; wykonawca odpowiada tylko za wyraźnie opisane zdarzenia gwarancyjne. Późniejsza polisa wymaga osobnej decyzji ekonomicznej, nie dopisku „jeśli wykupiono”.

5. Ryzyko wady jest niewycenione. Proponuję jeden rzut na pierwsze 14 dni po rozruchu: uszkodzenie jednej jednostki, naprawa za 4% bazowego CAPEX i 24 godziny bez produkcji. Daje to odpowiednio 720 tys., 3 mln i 9,6 mln PLN kosztu naprawy dla klas I–III. Dla klasy II postój kosztuje w modelu bazowym najwyżej 24 × 18 300 = 439 200 PLN utraconego wyniku; jest to założenie pełnej sprzedaży i braku równoległego, oszczędzonego kosztu postoju. Łączna ekspozycja wynosi 3 439 200 PLN. Przy jednostajnym rozłożeniu chwili wady w ciągu 14 dni i gwarancji pokrywającej wyłącznie naprawę — nie utraconą produkcję — wartość oczekiwana kosztu gracza wynosi:
   A: 30% × (3 000 000 + 439 200) = 1 031 760 PLN;
   B: 10% × 439 200 = 43 920 PLN;
   C: 6% × 439 200 = 26 352 PLN;
   D: 2% × 439 200 = 8 784 PLN;
   E: 18% × [½ × 439 200 + ½ × (3 000 000 + 439 200)] = 349 056 PLN;
   F: 45% × (3 000 000 + 439 200) = 1 547 640 PLN.
   To zmienia istotny szczegół: gwarancja nie powinna magicznie zwracać utraconego przychodu. Nawet z tym kosztem rabat F względem B wynosi 26,25 mln PLN CAPEX, więc F pozostaje dużo tańszy. D nie jest ekonomiczną dominantą: jego premia 22,5 mln PLN względem B jest znacznie większa od wycenionych tu korzyści z gwarancji i mniejszego ryzyka.

C. Luki i brakujące reguły

• Upadłość NPC/rezygnacja: w prostym modelu NPC nie bankrutuje losowo, bo byłby to dodatkowy rzut i sposób utraty opłaconych transz. Gracz może rozwiązać umowę tylko między etapami. Ukończone etapy zostają; niewykorzystana, opłacona z góry część bieżącego etapu nie podlega automatycznemu zwrotowi. Nowy wykonawca otrzymuje ofertę na pozostałe etapy, bez powtórnego pobierania opłaty za ukończone prace. Wyjątkowe administracyjne wyłączenie firmy nie zrywa podpisanych umów. Ewentualna przyszła „upadłość NPC” wymaga osobnej reguły zwrotu i finansowania.
• Wiarygodność sprawdza się przy podpisie, wraz z progiem klasy rafinerii; późniejszy spadek nie rozwiązuje umowy ani nie zatrzymuje opłaconego etapu. Ponowny wybór firmy wymaga ponownego sprawdzenia.
• `building_paused_funds` zatrzymuje czas prac. Gwarancja zaczyna biec dopiero przy `operational`, nie podczas budowy. Za postój nadal nalicza się kanoniczny dozór 5% godzinowego OPEX.
• Strajk własnej załogi technicznej zatrzymuje eksploatację według §31.7, ale samodzielna budowa przez NPC trwa. Wyjątkiem jest wyraźnie wymagane działanie gracza przy odbiorze lub rozruchu; wtedy etap czeka na tę czynność, bez ukrytego naliczania postępu.
• Zmiany wiarygodności konkurenta nie mogą wynikać z samego zgłoszenia, ataku czy zlecenia kontroli. Dopiero rozstrzygnięte zdarzenie przypisane temu graczowi zmienia jego wynik; wykryte nadużycia powiązanych kont wymagają audytu.
• Limit regionalnych działek nie ogranicza majątku jednego gracza. Na start rekomenduję najwyżej dwie równoległe budowy na korporację, licząc również `building_paused_funds`; nie ogranicza to liczby wcześniej ukończonych rafinerii. Limit powinien być w `game_balance`.

D. „Duży wybór firm”

Zamiast sześciu pozycji proponuję 24 stałe firmy: po cztery w każdym archetypie. Każda ma trwały `contractor_id`, fikcyjną nazwę, archetyp i publiczny wskaźnik rzetelności 0–100. Parametry oferty powstają deterministycznie z `HMAC(SERVER_SECRET, "epc_offer:{contractor_id}:{balance_version}")`: koszt i czas dostają niezależną zmianę od −5% do +5% wartości archetypu, integralność od −2 do +2 punktów procentowych, ryzyko wady od −2 do +2 punktów, z ograniczeniami zatwierdzonymi w `game_balance`. Jedna firma nie losuje parametrów na nowo przy odświeżeniu strony; aktualizacja wersji balansu tworzy nową ofertę, nie zmieniając starych umów. Rzetelność jest obliczana z historii ukończonych budów i incydentów, z podaną liczbą obserwacji; przy braku danych panel wyświetla „brak historii”, a nie pozornie dokładną ocenę.

Panel pokazuje pełny koszt czterech etapów, termin bez zdarzeń, integralność, ryzyko wady, długość gwarancji, wymagany próg wiarygodności, rzetelność i liczbę ukończonych budów. Filtry: dostępne dla gracza, archetyp, zakres ceny i czasu, gwarancja oraz legalność. Sortowanie po cenie, terminie, ryzyku i rzetelności. Przed podpisem panel pokazuje również możliwy dodatkowy koszt i postój, a nie tylko „ocenę gwiazdkową”.

E. Bilans liczbowy

Podstawa dla klasy II: 400 × (86 − 19) − 8 500 = 18 300 PLN/h, czyli 439 200 PLN/dobę. To odpowiada około 170,77 dnia zwrotu bazowych 75 mln PLN od chwili uruchomienia, nie dokładnie 170. Poniżej czas budowy to 168 h × mnożnik; zwrot nie obejmuje pensji, podatków, zdarzeń ani czasu oczekiwania na surowiec i sprzedaż.

Archetyp | CAPEX II | Budowa | Zwrot po uruchomieniu | Od rozpoczęcia budowy
A | 60,00 mln PLN | 9,45 dnia | 136,61 dnia | 146,06 dnia
B | 75,00 mln PLN | 7,00 dni | 170,77 dnia | 177,77 dnia
C | 82,50 mln PLN | 5,95 dnia | 187,84 dnia | 193,79 dnia
D | 97,50 mln PLN | 5,25 dnia | 221,99 dnia | 227,24 dnia
E | 67,50 mln PLN | 4,55 dnia | 153,69 dnia | 158,24 dnia
F | 48,75 mln PLN | 7,70 dnia | 111,00 dni | 118,70 dni

Przy ekspozycji na wadę opisanej w B, dodatkowej naprawie katastrofy 3 mln PLN za każdy trafiony etap i przykładowym ryzyku katastrofy na etap A/B/C/D/E/F = 3%/1%/0,5%/0,5%/2%/8%, oczekiwany koszt katastrof to odpowiednio 360/120/60/60/240/960 tys. PLN (liniowo: cztery rzuty × prawdopodobieństwo × 3 mln). Po dodaniu kosztów wad, lecz bez ceny dodatkowych przestojów i mandatów, F nadal ma najniższy oczekiwany wydatek: 48,75 + 1,548 + 0,960 ≈ 51,26 mln PLN. To dowód, że obecny rabat jest zbyt silny, jeśli firmy mają stanowić zbliżone ekonomicznie wybory. Nie jest to pełny rachunek opłacalności — nie wyceniono np. gorszej integralności, kar za niedostawy i czasu napraw katastrof.

Zakres 0,65–1,45 kosztu rozsadza kotwicę 106–170 dni: dla klasy II daje od 111,00 do 247,61 dnia zwrotu po uruchomieniu; dla klasy I, przy przyjętych ~7 100 PLN/h, od 68,66 do 153,17 dnia; dla klasy III, przy ~59 400 PLN/h, od 109,43 do 244,11 dnia. Kotwica 106–170 opisuje bazowe klasy, nie wszystkie warianty EPC — trzeba to jawnie rozdzielić. Mnożnik czasu 0,65–1,40 oznacza dla II 4,55–9,80 dnia budowy; nie naprawia różnicy kilkudziesięciu milionów w CAPEX.

Gwarancja 30 dni i wada ujawniana tylko przez pierwsze 14 dni nie są sprzeczne logicznie, ale dodatkowe 16 dni nie chroni przed tym konkretnym zdarzeniem. W G gwarancja obejmuje przypisane wykonawcy awarie przez cały swój okres, a osobny, jednorazowy rzut na wadę ukrytą następuje tylko w pierwszych 14 dniach.

Cztery niezależne rzuty po 8% dają 1 − 0,92⁴ = 28,36% szansy na co najmniej jedną katastrofę, nie 32%. Oczekiwana liczba katastrof wynosi 4 × 8% = 0,32; te dwie wielkości nie są zamienne. Tak częste zdarzenie może być dotkliwe w odbiorze gracza, lecz przy rabacie 26,25 mln PLN względem B wciąż nie równoważy ceny. Zalecam ograniczyć do jednej katastrofy na budowę i dopiero potem skalibrować rabat, zamiast ukrywać nierównowagę za losowością.

F. Spójność ze specyfikacją — wykryte konflikty

• Propozycja używa rzeczywistego członu „Krupp” oraz nazw brandopodobnych mimo zakazu. `country_code` może opisywać fikcyjne pochodzenie firmy, ale nie uprawnia do używania marki.
• Deklarowane zakresy kosztu, integralności i ryzyka wady nie obejmują własnego archetypu F.
• Parametry w mutowalnej tabeli firm nie mają `balance_version`; podpisany kontrakt nie ma migawki warunków.
• Płatność do wykonawcy, gotówka F i kara od NPC nie mają kompletnej drogi FTS. Kod mandatu w propozycji i konwencjach to `REF_TAX_PENALTY`; nie należy tworzyć obok niego drugiego kodu tylko dla nalotu.
• „Natychmiastowa” naprawa gwarancyjna omija czas naprawy i TTS; musi być określona jako osobna usługa wykonawcy z czasem, bez personelu gracza, a nie zeroczasowe skasowanie awarii.
• Polisa budowy została przywołana bez definicji, chociaż §31.6 dotyczy działającej instalacji.
• „Sweeper sprawdza incydenty” może uzależniać wynik od aktywności gracza. Rozliczenie leniwe i sweeper muszą odtwarzać te same z góry określone okna zdarzeń.
• Rzut `build_incident:{refinery_id}:{stage}` nie jest równy wymaganej postaci `HMAC(SERVER_SECRET, "{encja}:{due_slot}")`; potrzebne są jednoznaczne `encja` i `due_slot`.
• Samo ustawianie `stage_ends_at` bez przesuwania go o pauzę nie realizuje zatrzymania budowy przy braku środków.
• `ALTER TABLE ... ADD contractor_id NOT NULL` nie określa migracji istniejących rafinerii i uniemożliwia stan `planning` przed wyborem firmy.
• W propozycji SQL nie ma typu `ENUM`. `BOOLEAN` w MySQL jest aliasem liczbowym, nie typem `ENUM`; można go użyć, choć w zapisie projektu czytelniejszy byłby `TINYINT(1)`. `ON DELETE RESTRICT` dla wykonawcy jest zgodne z trwałością kontraktu.
• W samym wyciągu §31 występuje już rozbieżność nazw `building_paused_funds` wobec komentarza schematu `paused_funds`. Przed implementacją trzeba ustalić jedną wartość statusu; poniżej używam kanonicznej nazwy z reguły budowy, bez wprowadzania kolejnego statusu.

G. Proponowana treść §31.2.3 do wklejenia

### 31.2.3. Generalny Wykonawca — kontrakt EPC

Przed opłaceniem etapu 1 gracz wybiera jednego wykonawcę NPC. Wykonawcy są ofertami systemowymi, nie graczami i nie mają własnych kont FTS. Kontrakt określa koszt i czas każdego z czterech etapów, integralność przy odbiorze, ryzyko wady ukrytej oraz gwarancję. Wymóg wiarygodności klasy rafinerii z §31.2 obowiązuje niezależnie od wymogu wykonawcy. Progi sprawdza się przy podpisie; późniejszy spadek wiarygodności nie unieważnia kontraktu.

| Archetyp | Przykładowe fikcyjne nazwy | CAPEX × | Czas × | Integralność | Wada w 14 dni | Gwarancja | Wiarygodność |
|---|---|---:|---:|---:|---:|---:|---:|
| A — lokalny tani | Brzegon Konstrukcje; Żelwita Projekty; Torvian Budowa; Luntor Inżynieria | 0,80 | 1,35 | 80% | 30% | 0 dni | 40 |
| B — stabilny publiczny | Vistara Instalacje; Nordela Infrastruktura; Sorevia Przemysł; Kalden Zakłady | 1,00 | 1,00 | 92% | 10% | 14 dni | 60 |
| C — prywatny lider | Arvexis Proces; Meriton EPC; Veltrix Montaż; Orventa Technologie | 1,10 | 0,85 | 95% | 6% | 21 dni | 70 |
| D — konsorcjum zachodnie | Eisenora Anlagen; Halenwerk Projekt; Brennwald Technik; Veldenbau Industrie | 1,30 | 0,75 | 99% | 2% | 30 dni | 80 |
| E — konsorcjum azjatyckie | Harukai Proces; Tensora Heavyworks; Miryuen Anlagen; Yandori Engineering | 0,90 | 0,65 | 88% | 18% | 7 dni | 65 |
| F — szara strefa | Cichy Fundament; Mrokant Roboty; Betonowy Szlak; Nocny Montaż | 0,65 | 1,10 | 72% | 45% | 0 dni | 0 |

Nazwy są robocze i wymagają sprawdzenia kolizji przed publikacją. Podpisanie F zwiększa `black_market_score` o 5 punktów. W tej klasie 25% każdej transzy płaci się z `PLN_CASH`, a 75% z `PLN_BANK`; brak którejkolwiek części wstrzymuje rozpoczęcie etapu bez pobrania drugiej. Pozostałe firmy pobierają całą transzę z `PLN_BANK`. Wybór F nie znosi wymaganego progu wiarygodności samej klasy rafinerii.

Katalog liczy 24 stałe firmy. Liczby w tabeli są wartościami archetypów, a konkretna firma ma trwale wyznaczone odchylenia: koszt i czas ±5% względnie, integralność ±2 punkty procentowe, ryzyko wady ±2 punkty. Wyniki muszą mieścić się w jawnych przedziałach zatwierdzonych w `game_balance`; w szczególności zakresy całego katalogu obejmują F: koszt co najmniej 0,60×, integralność co najmniej 70%, ryzyko wady najwyżej 50%. Odchylenia wyznacza się z HMAC dla `encja = epc_offer:{contractor_id}`, `due_slot = balance_version`; osobne oznaczone bajty wyniku służą osobnym parametrom. Oferta pokazuje wszystkie rzeczywiste liczby przed podpisem. Historia budów daje publiczną rzetelność 0–100 i liczbę obserwacji; nie wpływa ukrycie na rzut.

Koszt etapu = bazowy CAPEX klasy × udział etapu 5%/45%/40%/10% × mnożnik konkretnej oferty. Czas etapu = bazowe godziny klasy × udział czasu 10%/45%/35%/10% × mnożnik czasu. Kwoty oblicza się w groszach i zaokrągla deterministycznie; czwarty etap otrzymuje różnicę do sumy kontraktu. Każdy etap jest opłacany z góry atomowo. Brak środków oznacza `building_paused_funds`; prace nie postępują, a dozór wynosi 5% godzinowego OPEX klasy. Opłata za dozór podlega dotychczasowej regule braku środków i zobowiązania 72 h. Po uzupełnieniu środków nowy koniec etapu wynika z pozostałego czasu pracy, nie z dawnego `stage_ends_at`.

| `reason_code` | `from_account` → `to_account` | Kiedy i skutek braku środków |
|---|---|---|
| `REF_BUILD_STAGE` — istniejący kod | `PLN_BANK` gracza → `SYS_TREASURY` | Legalna transza lub bankowe 75% transzy F. Brak: etap nie startuje; `building_paused_funds`. |
| `REF_EPC_CASH_STAGE` | `PLN_CASH` gracza → `SYS_TREASURY` | Gotówkowe 25% transzy F. Oba ruchy transzy zatwierdza jedna transakcja; brak gotówki: żaden nie zachodzi. |
| `REF_EPC_DELAY_COMP` | `SYS_TREASURY` → `PLN_BANK` gracza | Kara za kwalifikowane opóźnienie: 5% ceny opóźnionego etapu, najwyżej raz na etap. Finansuje ją system; brak środków gracza nie dotyczy. |
| `REF_TAX_PENALTY` — istniejący kod | `PLN_BANK` gracza, a gdy reguła kary tak stanowi `PLN_CASH` → `SYS_TREASURY` | Mandat za wykryte naruszenie F. Kwota i źródło są zapisane przy zdarzeniu; brak środków: zobowiązanie 72 h, potem §17. |
| `REF_EPC_WARRANTY_REPAIR` | `SYS_TREASURY` → systemowe konto kosztów usług naprawczych, utworzone i zdefiniowane przed wdrożeniem | Wyłącznie rzeczywisty koszt naprawy objętej gwarancją, bez przelewu przez konto gracza. Jeśli gra nie prowadzi takiego konta, użyć rejestru kosztu systemowego zamiast fikcyjnego przelewu FTS; wówczas tego `reason_code` nie tworzy się w FTS. |
| `REF_EPC_DEFECT_REPAIR` | `PLN_BANK` gracza → `SYS_TREASURY` | Naprawa wady nieobjętej gwarancją. Brak: jednostka pozostaje uszkodzona; należność jest zobowiązaniem 72 h tylko wtedy, gdy naprawę już wykonano na kredyt systemowy — domyślnie naprawa nie rozpoczyna się. |
| `REF_EPC_SITE_REPAIR` | `PLN_BANK` gracza → `SYS_TREASURY` | Naprawa po katastrofie budowlanej, 4% bazowego CAPEX. Brak: budowa pozostaje wstrzymana; płatność poprzedza naprawę. |

Nie wprowadza się przelewu na kwotę zero. Każdy rzeczywisty ruch ma pełny rekord FTS: `from_account`, `to_account`, `amount_grosze` jako `BIGINT`, `reason_code`, `actor_id`, `sweep_no`, `idempotency_key`, `balance_version`. Korekty i zwroty administracyjne wymagają osobnego uzasadnienia oraz odpowiedniego istniejącego kodu korekty, nie ujemnej kwoty transzy. Szczegółowy sposób księgowania gwarancyjnych kosztów systemowych trzeba wybrać przed migracją: rejestr kosztów bez FTS jest wariantem zalecanym, ponieważ żadna gotówka nie zmienia właściciela.

Zdarzenia są przypisane do terminów logicznych, a nie do uruchomienia sweepera. Dla każdego opłaconego etapu istnieje jeden slot `stage:{stage_no}:incident`, a dla F również `stage:{stage_no}:inspection`. Seed każdego rzutu ma dokładnie postać `HMAC(SERVER_SECRET, "{encja}:{due_slot}")`, gdzie `encja = refinery_build:{refinery_id}:{contract_id}`, a `due_slot` to powyższy slot. Oddzielne sloty rozróżniają rodzaje zdarzeń. Wynik, wersję balansu i zastosowany skutek zapisuje się raz pod unikatowym kluczem; rozliczenie przy wejściu gracza oraz sweeper odczytują ten sam wynik. Slot staje się należny po połowie efektywnego czasu pracy etapu, z wyłączeniem pauz. Zdarzenie nie występuje ponownie po wznowieniu ani zmianie wykonawcy.

Przykładowe prawdopodobieństwa katastrofy na etap A/B/C/D/E/F to 3%/1%/0,5%/0,5%/2%/8%, przy czym najwyżej jedna katastrofa może wystąpić w całej budowie. Zdarzenie blokuje postęp do naprawy, wydłuża pozostały czas prac o 25% czasu danego etapu i kosztuje gracza 4% bazowego CAPEX klasy; nie istnieje polisa placu budowy. Opóźnienie dostawy, jeśli zostanie włączone w `game_balance`, dodaje 12 lub 24 godziny według z góry określonego wyniku; kara 5% ceny etapu należy się wyłącznie przy wskazanej w kontrakcie winie wykonawcy. Strajk podwykonawcy A/F to odrębny, jednorazowy wynik na etap i postój 48 godzin; nie ma nieopisanej opcji „dosypania gotówki”. F ma jeden rzut kontroli na etap z prawdopodobieństwem 15%; wykrycie oznacza 48 godzin postoju, `REF_TAX_PENALTY` w wysokości 5% ceny tego etapu i spadek wiarygodności o 12 punktów. Brak wykrycia nie obniża wiarygodności. Wszystkie te stawki, włącznie z limitem jednej katastrofy i priorytetem zdarzeń, należą do wersjonowanego `game_balance`.

Przy przejściu w `operational` integralność przyjmuje wartość z podpisanej oferty, rozpoczyna się gwarancja i ustala `warranty_ends_at`. W pierwszych 14 dobach pracy odbywa się jeden deterministyczny rzut na wadę ukrytą (`due_slot = operational:{contract_id}:latent_defect`), z przypisaną z góry chwilą ujawnienia. Wada uszkadza jedną jednostkę. Naprawa kosztuje 4% bazowego CAPEX i trwa 24 godziny; podczas niej ta jednostka nie produkuje. Jeżeli wada ujawnia się przed końcem gwarancji i jest przypisana wykonawcy, gracz nie płaci za naprawę, a wykonawca świadczy ją bez udziału techników gracza, ale nadal przez 24 godziny. Gwarancja obejmuje również inne awarie jednoznacznie przypisane wykonawcy do końca okresu, nie zużycie, zaniedbanie, sabotaż ani utracony przychód. Zgłoszenie gracza tylko odsłania i uruchamia już ustalony wynik; nie losuje ponownie. Pozostałe naprawy wykonuje własny personel według TTS. Polisa eksploatacyjna §31.6 nie pokrywa placu budowy i nie zastępuje gwarancji EPC.

W modelu danych `refinery_contractors` zawiera `id VARCHAR(32)`, fikcyjną `name VARCHAR(128)`, `archetype_code VARCHAR(32)`, `is_active TINYINT(1)`, czasy `DATETIME(3)` UTC oraz metadane pochodzenia; nie przechowuje bieżących mnożników jako źródła prawdy. Parametry archetypu, odchylenia i stawki zdarzeń są wersjonowane w `game_balance`. Osobna tabela `refinery_epc_contracts` zawiera `id BIGINT UNSIGNED`, `refinery_id BIGINT UNSIGNED`, `contractor_id VARCHAR(32)`, `balance_version`, podpisane mnożniki i ryzyka w `DECIMAL`, kwoty czterech transz w `BIGINT` groszy, godziny etapów w jednostce o ustalonej precyzji, `signed_at`, `operational_at`, `warranty_ends_at`, `contract_status VARCHAR(32)` i sumę kontrolną podpisanej oferty. Klucze obce do rafinerii i wykonawcy mają `ON DELETE RESTRICT`. W `refineries` bieżący `epc_contract_id` jest początkowo `NULL` dla `planning`; migracja starych obiektów nie wymaga więc wymyślonego wykonawcy. Historia zdarzeń ma unikatowy klucz `(contract_id, due_slot)`, wynik rzutu, termin logiczny, skutek i klucz idempotencji. Zmiana kontraktu między etapami tworzy nowy rekord; stary zostaje dla audytu. Wszystkie aktualizacje etapu i FTS odbywają się pod blokadą wiersza i CAS na `last_calc_at`, tak aby żądanie gracza i sweeper nie rozliczyły go dwukrotnie.

H. Decyzje dla właściciela gry

1. Zatwierdzić wybór wykonawcy jako mechanikę, ale odrzucić obecny tekst jako implementację; przyjąć wersjonowane oferty i niezmienne kontrakty.
2. Zdecydować, czy F ma być świadomie najtańszą, ryzykowną ścieżką. Rekomendacja: tak na etapie prób, lecz bez twierdzenia, że obecne ryzyko wyrównuje rabat; po pomiarach skorygować cenę lub konkretne koszty szkód.
3. Nie uruchamiać na razie ubezpieczenia placu budowy. Katastrofę rozliczać jawnie według kontraktu; osobną polisę projektować dopiero z bilansem składek i wypłat.
4. Przyjąć 24 trwałe, fikcyjne firmy i pokazywać pełne liczby ofert, nie losować ich przy każdym otwarciu panelu.
5. Ustalić, że gwarancja pokrywa koszt i wykonanie naprawy, ale nie utracony przychód ani zerowy czas naprawy.
6. Przed programowaniem ujednolicić nazwę statusu pauzy oraz wybrać sposób ewidencji kosztu napraw gwarancyjnych; rekomenduję rejestr kosztu systemowego bez pozornego przelewu FTS.
