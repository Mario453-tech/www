Poniższe reguły dotyczą wyłącznie handlu ropą między graczami. Nie zmieniają kaucji ani kar za kontrakty z odbiorcami systemowymi z §13.1. Wartości oznaczone jako „proponowane” są początkową wersją `game_balance`; wskazane niżej niezmienniki własności i księgowania nie są parametrami balansu.

### 1. MASZYNA STANÓW OFERTY B2B

**Jednostką rozliczenia jest transza, nie cała oferta.** Oferta spot ma jedną transzę. Kontrakt ratalny ma harmonogram transz, ale każda transza otrzymuje własne `accepted_at`, `hard_deadline_at`, `PLN_ESCROW`, rezerwację partii ropy i historię stanów. `settled` kontraktu oznacza, że wszystkie transze są zamknięte; wcześniejsze wypłaty częściowe pozostają ostateczne.

Stan własności w każdym momencie:

- Do zatwierdzenia protokołu rozładunku ropa, także w cysternie, zatoce i podczas sporu, należy do sprzedawcy. Po protokole wyłącznie przyjęta ilość należy do kupującego. Utracona ilość jest stratą sprzedawcy; nigdy nie jest „ropą w escrow”.
- Pieniądze wpłacone do `PLN_ESCROW` pozostają roszczeniem kupującego aż do rozliczenia przyjętej ilości albo rozstrzygnięcia sporu. Nie są saldem sprzedawcy.
- `locked_in_escrow_bbl` jest blokadą dyspozycji, nie zmianą właściciela ani fizycznym miejscem. Dla nierozładowanej ropy blokada podąża za partią przez transport; przy rozładunku odpowiednia część znika. Własność i lokalizacja wynikają z `oil_movements` oraz partii, nie z samego stanu oferty.
- Cena jednostkowa i całkowita wartość każdej transzy są ustalane przy jej `accepted` i zapisywane w groszach. Nie przelicza się ich późniejszą ceną spot. Wartość częściowej dostawy liczy się narastająco: `floor(cena_transzy_grosze × przyjęte_jednostki_0,0001_bbl / zamówione_jednostki_0,0001_bbl)`. W ostatnim rozliczeniu transzy pozostała różnica groszowa trafia kupującemu, jeżeli nie ma już dalszej dostawy.

| Przejście | Wyzwalacz i decydent | Ropa: właściciel, miejsce, blokada | Pieniądze i zapisy | Termin |
|---|---|---|---|---|
| `draft → published` | Sprzedawca publikuje ofertę z ceną, ilością, partiami, trasą i harmonogramem | Sprzedawca; magazyn sprzedawcy. Bez blokady — dostępność jest sprawdzana ponownie przy przyjęciu | Bez FTS; bez `oil_movements` | `published_expires_at ≤ published_at + 7 dni` |
| `published → cancelled` | Sprzedawca wycofuje nieprzyjętą ofertę | Bez zmiany; brak blokad | Bez FTS i ruchu ropy | Przed przyjęciem |
| `published → expired` | Automat; nikt jej nie przyjął | Bez zmiany; brak blokad | Bez FTS i ruchu ropy | Dokładnie w `published_expires_at` |
| `published → accepted` | Kupujący przyjmuje ofertę spot albo konkretną transzę; automat zatwierdza po kontroli stron, salda, partii, trasy i ryzyka powiązanych kont | Sprzedawca; ropa nadal w jego magazynie. Zakłada się blokadę dokładnie zakontraktowanej ilości | `PLN_BANK kupującego → PLN_ESCROW transzy`, 100% ceny, `B2B_ESCROW_LOCK`; bez ruchu ropy. `accepted_at` i wersja balansu są utrwalone | Transakcja atomowa; `hard_deadline_at = accepted_at + 7 dni` |
| `accepted → in_delivery` | Sprzedawca uruchamia opłacony kurs; może wysłać kilka kursów dla jednej transzy | Sprzedawca; z magazynu do oznaczonego pojazdu/odcinka. Blokada pozostaje na wysłanej i niewysłanej części | Koszt przewozu z konta sprzedawcy według §11A; `oil_movements: B2B_LOAD`. Bez wypłaty z escrow | Wysłanie tylko, jeżeli pozostały czas pozwala na ETA i rozładunek przed twardym limitem |
| `in_delivery → waiting_bay` | Przyjazd, brak wolnej zatoki lub miejsca w magazynie kupującego; automat | Sprzedawca; ładunek pozostaje w pojeździe/zatoce, nie w magazynie kupującego. Blokada trwa | Brak wypłaty; opłatę postoju kupującego nalicza §21 | Opłata po 2 h; systemowy holownik po 48 h zgodnie z §21, lecz nie przedłuża escrow |
| `in_delivery/waiting_bay → partial_unload` | System potwierdza faktycznie przyjętą ilość | Przyjęta część: kupujący, jego magazyn; reszta: sprzedawca, pojazd/zatoka. Blokada zwolniona tylko dla przyjętej części | `oil_movements: B2B_UNLOAD_ACCEPTED`. Za przyjętą część tworzy się należność do wypłaty; pozostała wartość zostaje w escrow | Protokół przy każdym rzeczywistym rozładunku |
| `partial_unload → in_delivery/waiting_bay` | Pozostała część jedzie dalej albo czeka na miejsce | Własność obu części bez zmian; blokada tylko na nieprzyjętej ropie | Bez ponownego pobrania escrow | Przed twardym limitem |
| `in_delivery/partial_unload/waiting_bay → delivered` | Suma protokołów obejmuje całą zachowaną, dostarczalną ilość, a brak dalszego kursu | Kupujący jest właścicielem przyjętej ropy w swoim magazynie; udokumentowane straty nie mają właściciela | Zamknięcie pozostałej należności i zwrot ceny niedostarczonej ropy; osobne FTS dla każdego kierunku | Automat w tym samym przebiegu co ostatni protokół |
| `partial_unload/delivered → settled` | Automat rozlicza potwierdzoną część, jeżeli nie dotyczy jej uzasadniony spór | Ropa już przyjęta pozostaje kupującego. Blokady zwolnione dla tej części | Escrow → `PLN_BANK` sprzedawcy, prowizja osobnym wpisem, ewentualny zwrot niewykorzystanej kwoty kupującemu | Nie później niż 5 min od protokołu lub końca okna reklamacji, zależnie od rodzaju sporu |
| `accepted/in_delivery/waiting_bay/partial_unload → disputed` | Strona zgłasza konkretną wadę z dowodami albo automat stwierdza przekroczenie terminu | Przyjęta ropa pozostaje kupującego; pozostała — sprzedawcy w zapisanym miejscu, także w pojeździe. Blokada tylko na pozostałej ropie | Sporna część escrow pozostaje zablokowana; bez cofania wcześniej prawidłowo wypłaconych części | Spór nie przesuwa `hard_deadline_at` |
| `disputed → resolved` | GM orzeka na podstawie protokołów, `oil_movements`, przebiegu kursu, ETA, incydentów, ochrony i FTS; automat stosuje domyślne rozstrzygnięcie, gdy GM nie zdąży | Każda ilość przypisana do właściciela i miejsca w orzeczeniu; fizyczny zwrot to osobny kurs | Wypłata, zwrot, kara lub korekta wyłącznie odrębnymi wpisami FTS; zakaz edycji starego wpisu | Najpóźniej do twardego limitu |
| `resolved → settled/refunded` | Automat wykonuje orzeczenie | Blokada niewysłanej ropy zniesiona; ropa w trasie zostaje u sprzedawcy do fizycznego powrotu lub dalszej dyspozycji | Całe pozostałe escrow opróżnione do odpowiednich odbiorców | W tej samej transakcji co orzeczenie |
| `accepted/in_delivery/waiting_bay/partial_unload/disputed → refunded` | Całkowita utrata niewykonanej części, skuteczne odstąpienie, rozstrzygnięcie sporu lub twardy limit; automat/GM | Niedostarczona ropa, jeśli istnieje, nadal należy do sprzedawcy; brak „teleportacji” z pojazdu. Zwolnienie blokady z zachowaniem lokalizacji | Niewykorzystane escrow → `PLN_BANK` kupującego; osobno ewentualna kara. Wypłaty za wcześniej przyjętą ropę nie są cofane | Bezwzględnie do `accepted_at + 7 dni` |

**Incydent i wina.** Strata w transporcie przed protokołem obciąża sprzedawcę, bo nadal jest właścicielem ropy i wybiera przewóz. `oil_movements` zapisuje utracony wolumen z rzeczywistą przyczyną (`road_ambush`, awaria, sabotaż itd.). Za utracone baryłki kupujący dostaje zwrot ceny; sprzedawca może dostać odszkodowanie z wykupionej ochrony/ubezpieczenia, nigdy drugi raz cenę z escrow. Po protokole ryzyko przyjętej części przechodzi na kupującego.

**Niedostępność miejsca nie jest winą sprzedawcy.** Jeżeli przewóz dotarł na czas, lecz kupujący nie zapewnił miejsca, w terminie rozlicza się przyjętą część, a pozostałą cenę zwraca kupującemu; sprzedawca zachowuje pozostałą ropę i nie płaci kary za niedostarczenie. Koszty postoju i holowania obciążają kupującego zgodnie z §21. Nie wolno przeksięgować ceny niewyładowanej ropy na sprzedawcę.

**Bankructwo nie cofa transakcji.** Ropa w transporcie jest aktywem sprzedawcy, lecz nadal obciążonym obowiązkiem dostawy. Przyjęta ropa jest aktywem kupującego, nawet jeśli zbankrutował przed wypłatą ceny. Wcześniej zasilone escrow pozostaje wydzielone; nowe transze bankruta nie są przyjmowane. Postępowanie egzekucyjne nie licytuje zablokowanej ropy, ale po zwolnieniu blokady może ją zająć.

### 2. PARAMETRY LICZBOWE

| Parametr proponowany | Reguła i uzasadnienie |
|---|---|
| Prowizja zwykła | **1% wartości faktycznie przyjętej ropy**, ponosi sprzedawca, potrącana z jego wypłaty do `SYS_TREASURY`. Przy 250 PLN/bbl to 2,50 PLN/bbl; przy dostawie 20 000 bbl wartej 5 mln PLN jest to 50 000 PLN. Nie ma prowizji od zwróconej ceny. |
| Oferta wewnątrz kartelu | **0% tylko dla pierwszych 800 bbl na kartel w kroczącym oknie 24 h; potem zwykłe 1%**. Przy cenie bazowej próg jest wart 200 000 PLN: połowę dziennego limitu Tieru V (400 000 PLN). Zniżka jest wspólną pulą kartelu, nie pulą każdego członka. |
| Kara za dobrowolne zerwanie po przyjęciu | **`max(15% wartości niewykonanej części, dodatnia korzyść z ruchu ceny + 1% tej wartości)`**, płatna niewinnej stronie; brak prawa do jednostronnego „anulowania bez zgody” — niewypłacalność tworzy dług i windykację, nie umarza kary. Dla spadku ceny o 8%: korzyść kupującego z ponownego zakupu wynosi 8%, kara 15%, więc zerwanie kosztuje go o 7 punktów procentowych więcej. Przy spadku 20% kara wynosi 21%, a nie 15%: ruch ceny nie otwiera arbitrażu. |
| Pomiar ruchu ceny do kary | Porównanie ceny zakontraktowanej z wykonalną ceną referencyjną spot dla pozostałej ilości i regionu w chwili zerwania; używa się już obowiązującego modelu slippage z §12/§19.3. Ujemna korzyść = zero. Bez dostępnego notowania automat nie uznaje jednostronnego zerwania; kieruje je do sporu. |
| Twardy limit escrow | **7 × 24 h od `accepted_at` każdej transzy**, łącznie z `disputed`, urlopem, dormant i czasem pracy GM. Taka sama liczba dni jak maksymalna publikacja, lecz jest to osobny zegar. Spór i nowe częściowe protokoły go nie odnawiają. |
| Termin przewozu | ETA trasy plus margines do maksymalnego terminu dostawy **24–72 h** z konfiguracji §13; nie wolno przyjąć ETA wykraczającego poza twardy limit. Po terminie automat otwiera spór; brak reakcji sprzedawcy przez **24 h** uruchamia domyślne rozstrzygnięcie, ale nie później niż limit escrow. |
| Odbiór | Protokół powstaje przy fizycznym rozładunku, bez przycisku „akceptuj” kupującego. Kupujący ma **24 h od protokołu** na reklamację udokumentowanej jakości/ilości; samo milczenie nie blokuje wypłaty za bezsporną ilość. |
| Rozliczenie | Bezsporna ilość: **do 5 min od protokołu** przez ten sam mechanizm leniwy/zamiatacz. Zgłoszenie jakości w ciągu 24 h może dotyczyć już wypłaconej kwoty: powstaje osobne roszczenie, nie odwrócenie FTS. Aby spór mógł zatrzymać wypłatę, musi być zgłoszony przed jej zaksięgowaniem. |
| Decyzja GM | **Do 24 h od otwarcia sporu**, nie później niż `hard_deadline_at`. Gdy GM nie zdecyduje, automat wypłaca tylko cenę ilości potwierdzonej protokołem i zwraca cenę pozostałej; spór jakości po wypłacie pozostaje roszczeniem poza escrow. |

Granica „pralni” jest ważna: **sam próg prowizji nie zapobiega przekazaniu kapitału**. Gracz Tieru V może wymienić 400 GBBL na 400 000 PLN dziennie, co przy 250 PLN/bbl odpowiada 1 600 bbl. Pierwsze 800 bbl wewnątrz kartelu może przejść bez prowizji, drugie 800 bbl obciąży 1% = 2 000 PLN. To tylko koszt 0,5% wymienionych 400 000 PLN, więc proceder nadal może być opłacalny. W ciągu 30 dni limit Tieru V daje 12 mln PLN, czyli 48 000 bbl przy cenie bazowej. Dlatego właściwą zaporą są **śledzenie pochodzenia środków i zakaz odsprzedaży finansowanej Kroplówką do innego gracza przez 30 dni**, opisane w §5; prowizja jest opłatą handlową, nie zabezpieczeniem przeciw obchodzeniu Tieru.

### 3. TABELA KODÓW FTS

Wszystkie wartości w tabeli są całkowitymi groszami (`BIGINT`), mimo że interfejs pokazuje PLN. Przy każdym wpisie obowiązują pola FTS z §24.2, w tym `balance_version`; każdy kierunek ma własny jednoznaczny klucz idempotencji. Zamiast „ujemnego przelewu” powstaje nowy wpis w kierunku przeciwnym.

| Kod | Opis | `from_account → to_account` | Jednostka | Brak środków |
|---|---|---|---|---|
| `B2B_ESCROW_LOCK` | Pełna cena przyjętej transzy | `PLN_BANK kupującego → PLN_ESCROW transzy` | grosze | Odrzuć `accepted`; bez rezerwacji ropy |
| `B2B_SELLER_PAYOUT` | Cena ilości przyjętej pomniejszona o prowizję | `PLN_ESCROW → PLN_BANK sprzedawcy` | grosze | Błąd spójności; alarm, bez debetu |
| `B2B_COMMISSION` | Prowizja od przyjętej ilości, osobno | `PLN_ESCROW → SYS_TREASURY` | grosze | Błąd spójności; alarm |
| `B2B_ESCROW_REFUND` | Cena niedostarczonej części lub zwrot po sporze | `PLN_ESCROW → PLN_BANK kupującego` | grosze | Błąd spójności; alarm |
| `B2B_CANCEL_PENALTY` | Kara za zawinione zerwanie | `PLN_BANK`, następnie `PLN_CASH` winnego → `PLN_BANK` niewinnego | grosze | Pobierz dostępne saldo; resztę zapisz jako zobowiązanie 72 h, następnie §17.1–17.2 |
| `B2B_LOSS_REFUND` | Zwrot ceny ropy utraconej przed odbiorem | `PLN_ESCROW → PLN_BANK kupującego` | grosze | Błąd spójności; alarm; koszt utraconej ropy nie jest drugim przelewem |
| `B2B_GM_AWARD` | Zasądzona dopłata po wyczerpaniu escrow, z podanym `dispute_id` | `PLN_BANK`, następnie `PLN_CASH` strony przegrywającej → `PLN_BANK` strony uprawnionej | grosze | Zapłata częściowa i zobowiązanie 72 h; GM nie tworzy pieniędzy |
| `B2B_GM_ESCROW_RELEASE` | Korekta podziału niewypłaconego escrow zgodnie z orzeczeniem | `PLN_ESCROW → PLN_BANK` wskazanej strony | grosze | Błąd spójności; alarm |
| `B2B_INSURANCE_PAYOUT` | Odszkodowanie za stratę przed odbiorem, jeśli wykupiono polisę obejmującą kurs | `SYS_INSURANCE_POOL → PLN_BANK sprzedawcy` | grosze | Stosuj limity i finansowanie puli z §24.2; nie wypłacaj z escrow |
| `CASH_BANK_DEPOSIT` | Wpłata własnej gotówki | `PLN_CASH → PLN_BANK` tego samego gracza | grosze | Odrzuć |
| `BANK_CASH_WITHDRAW` | Wypłata własnej gotówki | `PLN_BANK → PLN_CASH` tego samego gracza | grosze | Odrzuć |
| `LOAN_INSTALLMENT_CASH` | Gotówkowa część automatycznej raty | `PLN_CASH → SYS_TREASURY` | grosze | Zapłać dostępne saldo; brakująca część przechodzi w `late` |
| `BAILIFF_INFLOW_SEIZURE` | Zajęcie połowy kwalifikowanego wpływu | Konto odbiorcy `PLN_BANK`/`PLN_CASH` → `SYS_TREASURY`, z identyfikatorem długu | grosze | Nie występuje: zajęcie wykonuje się atomowo z wpływem |

`B2B_LOSS_REFUND` i `B2B_ESCROW_REFUND` są rozłącznymi klasyfikacjami tego samego zwrotu — nigdy dwoma przelewami za tę samą baryłkę. Wypłatę za przyjętą ilość dzieli się na `B2B_SELLER_PAYOUT` i `B2B_COMMISSION`, których suma równa się cenie tej ilości. Zajęcie komornicze jest następnie osobnym wpisem dotyczącym wypłaty netto.

### 4. TRANSFERY `PLN_CASH ↔ PLN_BANK`

Wpłata i wypłata są **natychmiastowe, bezpłatne**, dostępne bez limitu dobowego, tylko między własnymi kontami. Uzasadnienie: to jedna waluta i jeden właściciel; opłata lub zwłoka tworzyłaby sztuczną pułapkę dla raty kredytu. Limit operacyjny stanowi wyłącznie dostępne dodatnie saldo. Oba kierunki zapisują wskazany wyżej wpis FTS; nie tworzą przychodu, nie zwiększają majątku netto ani limitu Kroplówki.

Egzekucja Fazy 1 stosuje 50% **raz na wpływ zewnętrzny**, przed udostępnieniem jego pozostałości do transferu. Przeniesienie środków między własnymi kontami ma `origin_entry_id` i `internal_transfer=true`, więc nie jest ponownym „wpływem” do zajęcia; nie usuwa jednak zajęcia już należnego. Nie wolno wpłacić środków z nowej sprzedaży i wycofać ich z banku między wpływem a zajęciem: oba zapisy są jedną transakcją DB z blokadą rachunku i postępowania. Zwrot własnych środków z escrow po nieudanym zakupie też nie jest nowym zarobkiem; jest oznaczony pochodzeniem, aby nie pobrać 50% drugi raz. Wypłata sprzedawcy z B2B **jest nowym wpływem** i podlega zajęciu.

Rata kredytu: automat obciąża najpierw `PLN_BANK`, następnie brakującą część pobiera bezpośrednio z `PLN_CASH` jako `LOAN_INSTALLMENT_CASH`; nie wymaga ręcznej wpłaty. Jeżeli suma obu sald nie pokrywa raty, pobiera dostępną kwotę, zapisuje resztę jako zaległą i uruchamia dotychczasowe 72 h do Fazy 1. W trakcie egzekucji najpierw rozlicza się zajęcie nowych wpływów, potem ratę z dostępnego salda; ta sama złotówka nie spłaca długu dwukrotnie.

### 5. POPRAWKI KARTELOWE (§26.3)

Tekst zastępujący obecną obietnicę:

> Członkowie kartelu korzystają ze zwolnienia z prowizji B2B wyłącznie dla łącznie pierwszych 800 bbl faktycznie odebranych wewnątrz tego samego kartelu w kroczącym oknie 24 h. Nadwyżka podlega zwykłej prowizji 1%, płaconej przez sprzedawcę. Zwolnienie nie obejmuje sprzedaży do podmiotu spoza kartelu, nie znosi escrow, transportu, zajęcia komorniczego ani kontroli kont powiązanych. Ilość liczy się w chwili protokołu, wspólnie dla całego kartelu; podział na transze, zmiana członkostwa i rozwiązanie oraz ponowne utworzenie kartelu nie zerują historii okna. Przyjęta transza zachowuje zasady prowizji z chwili przyjęcia, ale zużycie wspólnego progu liczy się w chwili odbioru.

Dodatkowe zabezpieczenie Kroplówki: każda wymiana GBBL otrzymuje `conversion_id`, a środki z niej oraz ropa kupiona za te środki otrzymują znacznik pochodzenia. Przez **30 dni** od wymiany nie wolno sprzedawać takiej ropy innemu graczowi B2B ani finansować nią zakupu B2B dla odsprzedaży; dozwolona pozostaje sprzedaż na rynku systemowym i użycie we własnej firmie. Przy zakupie za środki mieszane znacznik obciąża proporcjonalną część partii, z zaokrągleniem do 0,0001 bbl w górę. Nie jest to zakaz legalnego zarobku, tylko zakaz przekazania innemu graczowi kapitału świeżo wyemitowanego z jego cudzego limitu Tieru.

GM widzi graf: `conversion_id → wpływ PLN → zakup partii → oil_movements → oferta/transza → odbiorca`, wolumen 24 h/30 dni, ceny względem spot, powiązania urządzenia/metody płatności/IP, powtarzalne transakcje wzajemne i historię członkostwa. Próba obejścia blokady jest odrzucana przed `accepted`; potwierdzony handel przez podstawione konto skutkuje blokadą nowych ofert obu stron do ręcznej kontroli GM. Już prawidłowo odebranej ropy i zapisów FTS nie kasuje się; ewentualne sankcje pieniężne wymagają odrębnej decyzji i wpisu.

### 6. INTERAKCJE MIĘDZYMODUŁOWE

- **Escrow i komornik:** `B2B_ESCROW_LOCK` nie jest wpływem sprzedawcy ani aktywem do zajęcia. Przy wypłacie cena przyjętej ropy pomniejszona o prowizję trafia wyłącznie na `PLN_BANK` sprzedawcy. Jeśli sprzedawca jest w Fazie 1, atomowo zajmuje się 50% tej wypłaty netto do wysokości pozostałego długu. Zwrot escrow kupującemu jest odzyskaniem jego własnych środków, a nie nowym wpływem; wcześniejsze zajęcie przy ich uzyskaniu pozostaje w historii. Kara i zasądzone dopłaty otrzymane od innego gracza są nowym wpływem i podlegają zajęciu.
- **Raty i bankructwo:** przyjęte transze biegną dalej mimo postępowania obu stron. Nie przyjmuje się nowych transz strony w `bankruptcy`/`recovery_mode`; wypłata po dostawie sprzedawcy-bankruta zasila jego bank i podlega zajęciu. Ropa sprzedawcy-bankruta w trasie pozostaje jego własnością do protokołu; nie może być równolegle licytowana.
- **Urlop i dormant:** nie można rozpocząć urlopu z aktywną dostawą, jak stanowi §21. Urlop rozpoczęty przed przyjęciem oferty uniemożliwia jej przyjęcie. Dormant nie zatrzymuje ETA, protokołów, reklamacji, egzekucji ani 7-dniowego limitu escrow; automat rozlicza zapisane zdarzenia według ich rzeczywistych terminów, bez symulowania ponad Δt-cap 7 dni.
- **VIP:** VIP może ułatwiać wystawianie i obsługę ofert, lecz nie zmienia prowizji, limitu kartelowego, wymogu escrow, liczby pojazdów ani twardego terminu. Po wygaśnięciu VIP już przyjęte transze są wykonywane; nowe automatyczne wysyłki nie startują, jeśli zależały od VIP. Gracz musi zapewnić dostawę ręcznie albo ponosi skutki niedostarczenia. Nie ustanawia się osobnego limitu liczby ofert B2B zależnego od VIP.
- **Ochrona i ubezpieczenie:** ochronę kursu kupuje sprzedawca, którego rozliczenie ryzyka ją stosuje (§11A.7). Przed odbiorem tylko sprzedawca może otrzymać odszkodowanie za utraconą ropę; kupujący otrzymuje zwrot ceny z escrow. Po protokole szkoda przyjętej ropy należy do kupującego i wymaga jego własnej ochrony/polisy obejmującej późniejsze miejsce i czas.
- **Jakość i czarny rynek:** `black_market_score` opisuje zachowanie gracza, nie chemiczną jakość każdej baryłki. Samo pochodzenie z czarnego rynku nie obniża automatycznie jakości ani ceny. Sprzedawca deklaruje mierzalną klasę/jakość partii przed publikacją; reklamacja wymaga zapisanego pomiaru albo rozbieżności partii/protokołu. Za niezgodność odpowiada sprzedawca, niezależnie od pochodzenia ropy; potwierdzone oszustwo może dodatkowo uruchomić istniejące konsekwencje §2, ale bez domniemywania winy z samego wskaźnika.

### 7. TESTY ODBIORCZE BLOKUJĄCE WYDANIE

1. Równoczesne `accepted` tej samej partii przez dwóch kupujących oraz sprzedaż spot: dokładnie jedna rezerwacja może wygrać; bilans ropy i FTS się zgadza.
2. Rozliczenie leniwe i 5-minutowy zamiatacz tej samej transzy: jeden protokół, jedna wypłata, jedna prowizja i jedna zmiana własności przyjętej ilości.
3. Spór otwarty w ostatniej godzinie 7-dniowego limitu: brak przesunięcia terminu; w chwili limitu całe pozostałe escrow jest wypłacone lub zwrócone według reguły domyślnej.
4. Przyjazd do pełnego magazynu: ropa pozostaje u sprzedawcy w pojeździe/zatoce; brak wypłaty; postój obciąża kupującego; nie ma kary za opóźnienie sprzedawcy.
5. Częściowy rozładunek, następnie utrata reszty kursu: kupujący posiada tylko przyjętą część; sprzedawca ponosi utratę reszty; cena reszty wraca dokładnie raz.
6. Bankructwo kupującego po protokole, przed wypłatą: przyjęta ropa jest w jego masie; należna cena trafia sprzedawcy; brak cofnięcia dostawy.
7. Egzekucja sprzedawcy: prowizja 1% od dostawy 20 000 bbl po 250 PLN/bbl wynosi 50 000 PLN; połowa wypłaty netto 4 950 000 PLN jest zajmowana, z ograniczeniem do niespłaconego długu.
8. Zwrot escrow kupującemu w Fazie 1 oraz przelew `CASH → BANK → CASH`: brak powtórnego zajęcia zwrotu i brak obejścia zajęcia pierwotnego zarobku.
9. Spadek ceny spot o 8% po `accepted`: próba zerwania przy transzy 5 mln PLN rodzi karę co najmniej 750 000 PLN, gdy potencjalna oszczędność wynosi 400 000 PLN.
10. Spadek ceny o 20%: kara dynamiczna przewyższa oszczędność o 1% wartości niewykonanej części; brak stałego „sufitu” 15%.
11. Kartel: 30 kolejnych dni konwersji Tieru V i prób przekazania ropy B2B — 12 mln PLN świeżo wymienionych środków nie przechodzi do drugiego gracza przed upływem 30 dni od każdej konwersji; podział partii i transz nie pomaga.
12. Przekroczenie 800 bbl przez dwa równoczesne protokoły kartelu: zwolnienie dotyczy łącznie tylko 800 bbl, a reszta jest obciążona 1%.
13. Rata przy pustym banku i pełnym portfelu: zapłacona terminowo z `PLN_CASH`, bez statusu `late`; przy niedoborze powstaje tylko rzeczywista zaległość.
14. Restart między kredytowaniem kursu a protokołem: wynik incydentu nie jest losowany ponownie; własność, ilość i cena rozliczają się raz.
15. GM zasądza kwotę po wcześniejszej wypłacie: stary FTS pozostaje niezmieniony; nowa należność jest pobrana albo staje się zobowiązaniem 72 h; escrow nie ma ujemnego salda.

### 8. GOTOWA TREŚĆ DO WKLEJENIA DO SPECYFIKACJI

#### 13.2. Giełda kontraktów B2B między graczami

**Zakres i model.** Oferta spot zawiera jedną transzę; kontrakt ratalny zawiera harmonogram niezależnie przyjmowanych i rozliczanych transz. Każda transza posiada `accepted_at`, `hard_deadline_at`, `price_grosze`, `quantity_bbl DECIMAL(16,4)`, własne `PLN_ESCROW`, wskazane partie ropy, wersję `game_balance`, trasę, ETA i historię protokołów. Cena jest zamrożona przy przyjęciu transzy. Oferta `published` wygasa najpóźniej po 7 dniach; nie blokuje wtedy ani pieniędzy, ani ropy.

| Stan transzy | Dozwolone następstwa | Własność, miejsce, escrow |
|---|---|---|
| `published` | `accepted`, `cancelled`, `expired` | Ropa u sprzedawcy w magazynie; brak blokad |
| `accepted` | `in_delivery`, `disputed`, `refunded` | Ropa sprzedawcy zablokowana; pełna cena kupującego w escrow |
| `in_delivery` | `waiting_bay`, `partial_unload`, `delivered`, `disputed`, `refunded` | Nierozładowana ropa sprzedawcy w oznaczonym kursie; escrow nienaruszone |
| `waiting_bay` | `partial_unload`, `delivered`, `disputed`, `refunded` | Ropa sprzedawcy w pojeździe/zatoce; koszty postoju kupującego (§21) |
| `partial_unload` | `in_delivery`, `waiting_bay`, `delivered`, `settled`, `disputed`, `refunded` | Przyjęta ropa kupującego w jego magazynie; pozostała sprzedawcy w kursie; wypłata wyłącznie za przyjętą ilość |
| `delivered` | `settled`, `disputed` | Przyjęta ropa kupującego; końcowy podział escrow |
| `disputed` | `resolved` | Własność według protokołów; sporna kwota pozostaje w escrow tylko do twardego limitu |
| `resolved` | `settled`, `refunded` | Wykonanie orzeczenia lub reguły domyślnej i opróżnienie escrow |
| `settled`, `refunded`, `cancelled`, `expired` | brak | Stany końcowe; brak aktywnej blokady escrow |

**Przyjęcie i transport.** Przy `accepted` jedna transakcja DB blokuje 100% ceny z `PLN_BANK` kupującego w `PLN_ESCROW` transzy oraz dokładny wolumen partii sprzedawcy jako `locked_in_escrow_bbl`. Brak któregokolwiek zasobu odrzuca całość. Sprzedawca organizuje i opłaca rzeczywisty transport (§11A); załadunek, strata i rozładunek tworzą `oil_movements`. Ropa należy do niego aż do systemowego `delivery_receipt` z fizycznego rozładunku. Dopiero przyjęta część zmienia właściciela; pełny magazyn kupującego zatrzymuje pozostałą część w pojeździe/zatoce bez fikcyjnego protokołu. Opłaty postoju ponosi kupujący; terminowe przybycie zwalnia sprzedawcę z odpowiedzialności za zwłokę spowodowaną brakiem miejsca.

**Cena, prowizja i wypłata.** Cenę częściowej dostawy wylicza się narastająco z całkowitych groszy i jednostek 0,0001 bbl, przez dzielenie całkowitoliczbowe; pozostałość groszowa nierozliczonej ilości wraca kupującemu przy zamknięciu transzy. Od wartości faktycznie przyjętej ropy sprzedawca płaci 1% prowizji (`B2B_COMMISSION → SYS_TREASURY`); jego wypłata netto trafia wyłącznie do `PLN_BANK`. Bezsporna wypłata następuje najpóźniej w następnym 5-minutowym przebiegu po protokole. Kupujący może zgłosić udokumentowaną reklamację jakości do 24 h od protokołu; po wypłacie jest ona odrębnym roszczeniem, nie wstecznym cofnięciem księgi. Prowizję za bezsporną część wewnątrz kartelu określa §26.3.

**Timeout i spór.** Dostawa ma ETA oraz termin 24–72 h zgodny z trasą i `game_balance`. Przekroczenie terminu otwiera `disputed`; brak odpowiedzi sprzedawcy przez 24 h uruchamia rozstrzygnięcie automatyczne. GM może rozstrzygnąć spór w ciągu 24 h od otwarcia na podstawie `delivery_receipts`, `oil_movements`, zapisów ETA, incydentów, ochrony i FTS. Niezależnie od sporu escrow musi zostać całkowicie opróżnione do `accepted_at + 7 × 24 h`: cena ilości potwierdzonej protokołem przypada sprzedawcy po prowizji, cena ilości niepotwierdzonej wraca kupującemu. Roszczenia jakościowe nierozstrzygnięte przed tym terminem pozostają poza escrow; GM rozlicza je nowymi wpisami FTS i ewentualnym zobowiązaniem, bez edycji historii. Protokół zatwierdzony przed terminem ma pierwszeństwo przed automatycznym zwrotem za brak dostawy. Przy równej chwili zdarzeń najpierw zapisuje się fizycznie ukończony rozładunek, następnie wykonuje limit escrow.

**Straty, zerwanie i niewypłacalność.** Utrata ropy przed protokołem obciąża sprzedawcę; kupujący odzyskuje jej cenę z escrow, a ewentualne ubezpieczenie wypłaca odszkodowanie sprzedawcy (§11A, §24.2). Po przyjęciu części ryzyko tej części obciąża kupującego. Jednostronne zawinione zerwanie po `accepted` rodzi karę równą większej z: 15% wartości niewykonanej części albo dodatniej korzyści strony z aktualnego ruchu wykonalnej ceny spot wraz ze slippage plus 1% tej wartości. Kara trafia do niewinnej strony; brak środków tworzy zobowiązanie 72 h (§17.1), nie pozwala anulować długu. Bankructwo i egzekucja nie cofają protokołów ani nie zajmują escrow lub zablokowanej ropy; po wypłacie sprzedawcy Faza 1 zajmuje 50% jego nowego wpływu netto do wysokości długu (§17.2). Urlop nie może rozpocząć się z aktywną dostawą, a dormant nie zatrzymuje terminów (§19.2).

**Spójność.** Wszystkie przejścia wykonywać atomowo z blokadą właściwych wierszy, CAS i kluczem idempotencji `(transza, zdarzenie, numer_protokołu)`; zamiatacz i żądanie gracza stosują tę samą funkcję. Jedna baryłka nie może należeć do dwóch stron ani mieć dwóch rezerwacji; każde przemieszczenie zapisuje `oil_movements`, każdą zmianę salda FTS. Powiązane konta są wstrzymywane do przeglądu GM przed `accepted` (§22.6). Nowa wersja `game_balance` nie zmienia zaakceptowanej transzy (§29.5).

#### 24.5. Transfery `PLN_CASH ↔ PLN_BANK`

Wpłata `PLN_CASH → PLN_BANK` i wypłata `PLN_BANK → PLN_CASH` są natychmiastowe, bezpłatne, bez limitu dobowego, wyłącznie między rachunkami tego samego gracza i do wysokości dostępnego salda. Każda operacja tworzy wpis FTS `CASH_BANK_DEPOSIT` albo `BANK_CASH_WITHDRAW` z identyfikatorem źródłowego wpływu; nie jest przychodem ani ponownym zdarzeniem do zajęcia. W Fazie 1 komornika 50% kwalifikowanego wpływu zewnętrznego zajmuje się atomowo przy jego powstaniu, przed udostępnieniem pozostałej kwoty do transferu. Zwrot własnej wpłaty z escrow nie jest nowym zarobkiem; wypłata ceny sprzedaży B2B, kary albo zasądzonej dopłaty jest nowym wpływem podlegającym zajęciu.

Rata kredytu obciąża najpierw `PLN_BANK`, następnie do pozostałej należnej wysokości bezpośrednio `PLN_CASH` (`LOAN_INSTALLMENT_CASH`). Przy niedoborze pobiera się dostępne środki, a jedynie niespłacona reszta otrzymuje status `late` i dotychczasowy termin 72 h (§17.1). Transfer nie może ominąć ani odroczyć już należnego zajęcia.

#### 26.3. Kartele naftowe — handel B2B

Kartel nadal obejmuje 2–5 firm, jawne członkostwo, wspólny kanał i pakt o nieagresji; założenie kosztuje 50 000 PLN za zgodą członków, a rozwiązanie podlega 7-dniowej karencji. Zamiast nieograniczonego zwolnienia z prowizji członkowie otrzymują wspólną pulę **800 bbl przyjętej ropy bez prowizji B2B w kroczącym oknie 24 h na kartel**. Powyżej progu sprzedawca płaci 1% wartości przyjętej ropy, jak poza kartelem (§13.2). Zużycie puli rejestruje się przy protokole rozładunku, atomowo dla równoległych transz. Podział dostaw, członkostwa lub utworzenie następcy kartelu nie zerują historii okna.

Zwolnienie nie znosi escrow, rzeczywistego przewozu, odpowiedzialności za stratę, kontroli kont powiązanych ani egzekucji wpływów. Ropa kupiona w części sfinansowanej środkami ze świeżej Kroplówki zachowuje `conversion_id` i nie może zostać sprzedana innemu graczowi B2B przez 30 dni od wymiany GBBL; można ją wykorzystać we własnej firmie albo sprzedać systemowi. System śledzi proporcjonalny udział takich środków w partii do 0,0001 bbl. GM ma wgląd w łańcuch konwersji, zakupów, partii, protokołów, beneficjentów i powiązań kont; próba obejścia blokuje przyjęcie oferty i kieruje sprawę do ręcznej kontroli. Pozostałe zasady anty-zmowy i limit 40% produkcji serwera pozostają bez zmian.

### 9. DECYZJE DLA WŁAŚCICIELA GRY

1. Zatwierdzić prowizję 1% i wspólne zwolnienie kartelu na 800 bbl/24 h — daje kartelowi korzyść, lecz nie robi z niego darmowego kanału nieograniczonych transferów.
2. Zatwierdzić karę `max(15%, korzyść z ceny + 1%)` — stała kara 5% nie chroni przed zerwaniem po spadku ceny.
3. Zatwierdzić bezwzględne 7 dni escrow na transzę — nawet spór i nieobecność GM nie mogą parkować pieniędzy.
4. Zatwierdzić 30-dniowy znacznik ropy finansowanej Kroplówką — sama prowizja 1% nie powstrzymuje przekazania innemu graczowi dziennego limitu Tieru.
5. Zatwierdzić bezpłatne transfery własne i gotówkową dopłatę raty — rozwiązuje `late` przy pełnym portfelu bez tworzenia drogi ucieczki przed zajęciem.
6. Zatwierdzić wypłatę B2B wyłącznie do banku i zajęcie 50% wypłaty netto — utrzymuje jedną, sprawdzalną kolejność prowizji, wpływu i egzekucji.
