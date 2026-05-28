# OilCorp — README logistyki
## Wersja robocza do wdrożenia przez AI

## 1. Cel dokumentu

Ten dokument opisuje dokładnie, jak ma działać logistyka w grze.

Dokument ma być podstawą do programowania systemu przez AI i przez człowieka.
Dlatego wszystko jest opisane prostym językiem, bez skrótów myślowych i bez nazw, które są jasne tylko dla autora projektu.

Ten README ma odpowiedzieć na pytania:
- co już działa,
- co dokładamy dalej,
- jak ma działać logika odwiertów lądowych,
- jak ma działać logika odwiertów morskich,
- jak mają działać porty,
- jak ma działać przyszły hub przeładunkowy,
- co z czym trzeba połączyć,
- w jakiej kolejności to wdrażać.

---

## 2. Zasady opisu systemu i komunikatów

### 2.1. Zasada ogólna

Wszystkie teksty w README, w panelu gracza, w panelu admina i w alertach muszą być napisane prostym, jednoznacznym językiem.

Nie używamy nazw, które nic nie mówią zwykłemu graczowi lub AI programującemu system.

### 2.2. Nie używamy takich nazw

Nie używamy w dokumentacji i w UI nazw typu:
- MVP offshore
- fallback layer
- transport stage
- systemic hub
- marine flow
- congestion queue handler

### 2.3. Zamiast tego używamy prostych nazw

Dobre przykłady:
- podstawowa logistyka odwiertów morskich
- zastępczy transport drogowy
- ilość ropy, która naprawdę dotarła
- port jest przeciążony
- dostawa morska jest opóźniona
- rurociąg nie jest w stanie przesłać całej wydobytej ropy

### 2.4. Każdy moduł musi odpowiadać na pytania

Każda sekcja README ma jasno opisywać:
- co to jest,
- po co to istnieje,
- kiedy działa,
- jakie ma dane,
- co liczy tick,
- co może pójść źle,
- co widzi gracz,
- co widzi admin,
- jakie są kolejne etapy wdrożenia.

---

## 3. Stan obecny — co już działa

### 3.1. Huby logistyczne

Na dziś działa już system hubów logistycznych.

Działa:
- tworzenie hubów,
- przypisywanie odwiertów do hubów,
- limity slotów,
- przypisanie do regionu,
- przypisanie do strefy,
- cooldown po odpięciu i transferze,
- tick hubów,
- przepustowość,
- przeciążenie,
- straty,
- zużycie,
- stan techniczny,
- statusy,
- incydenty hubów,
- zapis statystyk ticka,
- zapis eventów.

### 3.2. Integracje już działające

Huby są już połączone z:
- Finansami,
- Technicznym,
- BHP,
- panelem gracza,
- panelem admina,
- testami backendowymi.

### 3.3. Co jest jeszcze niepełne

Jeszcze nie jest domknięte:
- pełne wejście gracza w modele `new / used / rental`,
- pełna własność huba jako aktywa firmy,
- rurociąg przypisany do konkretnego odwiertu,
- nowy model dostarczonej ropy przed magazynem,
- podstawowa logistyka odwiertów morskich,
- porty,
- terminale jako część portów,
- kolejki portowe,
- przyszły hub przeładunkowy jako kolejna warstwa rozwoju.

---

## 4. Główna filozofia logistyki

### 4.1. Stary uproszczony model

Do tej pory logika była uproszczona:

`odwiert -> magazyn -> sprzedaż`

### 4.2. Nowy model

Docelowo ropa ma przechodzić przez kilka warstw.

Nie każda wydobyta ropa trafia od razu do magazynu.
Ropa trafia do magazynu dopiero wtedy, gdy została realnie dostarczona.

### 4.3. Zasada podstawowa

Magazyn i sprzedaż mają działać wyłącznie na ropie, która naprawdę dotarła po przejściu przez transport i inne ograniczenia logistyczne.

To oznacza, że:
- wydobycie nie jest jeszcze dostawą,
- dostawa nie jest jeszcze sprzedażą,
- awarie, kradzieże, przeciążenia, opóźnienia i warunki pogodowe wpływają na ostateczny wynik ekonomiczny.

### 4.4. Główne nazwy wolumenów w systemie

System powinien rozróżniać co najmniej:

`raw_production_bbl`
- ilość ropy wydobytej przez odwiert

`after_local_transport_bbl`
- ilość ropy po lokalnym transporcie z odwiertu

`after_hub_bbl`
- ilość ropy po przejściu przez hub

`after_marine_transport_bbl`
- ilość ropy po transporcie morskim

`to_storage_bbl`
- ilość ropy, która naprawdę trafiła do magazynu

`sold_from_storage_bbl`
- ilość ropy sprzedanej z magazynu

---

## 5. Logistyka odwiertów lądowych

### 5.1. Co to jest

To cały proces doprowadzania ropy z odwiertu lądowego do dalszej logistyki.

Ropa z odwiertu lądowego nie może pojawiać się od razu w magazynie.
Najpierw musi zostać odebrana z odwiertu i przewieziona dalej.

### 5.2. Dwie podstawowe drogi

Dla odwiertu lądowego mają istnieć dwie drogi przesyłu ropy:

#### A. Rurociąg
To podstawowa i lepsza forma przesyłu.
Jest stabilniejsza, tańsza w długim czasie i lepiej nadaje się do dużych odwiertów.

#### B. Transport drogowy
To droga zastępcza lub awaryjna.
Jest droższa, bardziej ryzykowna i działa wolniej, bo każdy przewóz trwa określony czas.

### 5.3. Zasada podstawowa

Jeśli odwiert lądowy ma rurociąg, ropa idzie rurociągiem.

Jeśli odwiert lądowy nie ma rurociągu, ropa jest przewożona transportem drogowym.

Jeśli odwiert nie ma ani sprawnego rurociągu, ani aktywnego transportu drogowego, ropa nie trafia dalej.

### 5.4. Najważniejsza zasada czasu

Rurociąg działa jak stały przesył.

Transport drogowy nie działa natychmiast.
Każdy przewóz drogowy trwa określony czas.

To oznacza, że:
- rurociąg może stale przesyłać ropę,
- transport drogowy wysyła kurs,
- kurs jedzie przez pewien czas,
- ropa dociera dopiero po zakończeniu kursu.

### 5.5. Co ma widzieć gracz

Gracz ma widzieć:
- czy odwiert ma rurociąg,
- czy działa transport drogowy,
- ile ropy udało się naprawdę odebrać z odwiertu,
- ile ropy nie dotarło,
- jaki jest koszt przesyłu,
- czy wystąpiły problemy lub opóźnienia.

---

## 6. Rurociąg przypisany do odwiertu

### 6.1. Co to jest

Rurociąg jest połączeniem przypisanym do konkretnego odwiertu lądowego.

Nie jest wspólny dla całego regionu.
Nie jest tylko liczbą w panelu.
Ma być prawdziwą częścią logistyki.

### 6.2. Po co istnieje

Rurociąg ma być głównym sposobem odbierania ropy z odwiertu lądowego.

Ma dawać:
- stabilniejszy przesył,
- mniejsze ryzyko problemów niż transport drogowy,
- niższy koszt długoterminowy,
- lepszą obsługę dużych odwiertów.

### 6.3. Najważniejsze zasady

- jeden odwiert lądowy może mieć maksymalnie jeden aktywny rurociąg,
- rurociąg trzeba kupić,
- rurociąg należy do gracza,
- rurociąg działa przed magazynem i przed późniejszym hubem przeładunkowym,
- do dalszej logistyki trafia tylko ta ropa, którą udało się naprawdę przesłać.

### 6.4. Rodzaje rurociągu

Na start wystarczą trzy rodzaje:

#### Lekki
- tani,
- ma mniejszą przepustowość,
- szybciej się zużywa,
- dobry dla małych odwiertów.

#### Standardowy
- średni koszt,
- średnia przepustowość,
- średnia trwałość,
- podstawowy wybór dla większości odwiertów.

#### Mocny
- drogi,
- ma dużą przepustowość,
- wolniej się zużywa,
- dobry dla dużych odwiertów.

### 6.5. Co wpływa na działanie rurociągu

Na działanie rurociągu wpływa:
- jego rodzaj,
- jego stan techniczny,
- ilość ropy, która przez niego płynie,
- brak konserwacji,
- naprawy,
- awarie,
- wycieki,
- sabotaż,
- wpływ Technicznego,
- wpływ BHP.

### 6.6. Stany rurociągu

Rurociąg może mieć stany:
- planowany,
- w budowie,
- aktywny,
- uszkodzony,
- z wyciekiem,
- wstrzymany,
- wyłączony.

### 6.7. Co się dzieje, gdy rurociąg jest w złym stanie

Im gorszy stan rurociągu:
- tym mniej ropy przepuści,
- tym więcej ropy może się stracić,
- tym większe ryzyko awarii,
- tym większe ryzyko wycieku,
- tym bardziej opłaca się go naprawić albo wymienić.

Rurociąg ma mieć realny wpływ na wynik gry.
Jeśli jest zaniedbany, gracz ma naprawdę tracić ropę i pieniądze.

### 6.8. Konserwacja i naprawa

#### Konserwacja
Konserwacja ma spowalniać dalsze zużycie rurociągu.

#### Naprawa
Naprawa ma poprawiać stan techniczny rurociągu po uszkodzeniach i awariach.

To są dwa różne działania.
Nie powinny robić dokładnie tego samego.

### 6.9. Problemy na rurociągu

Na rurociągu mogą wystąpić:
- mały wyciek,
- duży wyciek,
- awaria,
- spadek przepływu,
- uszkodzenie,
- sabotaż.

Skutki:
- mniej ropy dociera dalej,
- rosną koszty,
- może pojawić się przestój,
- trzeba naprawiać,
- rośnie ryzyko kolejnych problemów.

### 6.10. Co liczy system dla rurociągu

System ma liczyć:
- ile odwiert wydobył,
- ile rurociąg jest w stanie odebrać,
- ile ropy naprawdę przesłał,
- ile ropy utracono,
- jaki jest nowy stan rurociągu,
- czy wystąpił problem.

Najważniejsze:
magazyn i dalsza logistyka mają dostać tylko to, co naprawdę przeszło przez rurociąg.

### 6.11. Co ma widzieć gracz

Gracz ma widzieć:
- czy odwiert ma rurociąg,
- jaki to rodzaj rurociągu,
- jaki jest stan techniczny,
- ile ropy przez niego przechodzi,
- ile ropy się na nim traci,
- ile kosztuje utrzymanie,
- czy jest ryzyko awarii,
- czy wymaga naprawy.

### 6.12. Co ma być widoczne w panelu zarządzania

Panel powinien pokazywać:
- najgorsze rurociągi,
- rurociągi z największymi stratami,
- rurociągi w krytycznym stanie,
- odwierty bez rurociągu,
- gdzie gracz traci najwięcej pieniędzy przez zły przesył ropy.

---

## 7. Transport drogowy dla odwiertów lądowych

### 7.1. Co to jest

Transport drogowy jest zastępczym lub awaryjnym sposobem odbierania ropy z odwiertu lądowego.

### 7.2. Po co istnieje

Pozwala działać odwiertowi nawet wtedy, gdy gracz nie kupił jeszcze rurociągu.

Jest łatwiejszy do uruchomienia, ale:
- kosztuje więcej,
- jest bardziej ryzykowny,
- działa wolniej,
- jest bardziej podatny na straty.

### 7.3. Najważniejsza zasada

Transport drogowy nie może działać natychmiast.

Każdy przewóz drogowy trwa określony czas.
Ropa nie może pojawić się dalej w tym samym momencie, w którym została odebrana z odwiertu.

### 7.4. Jak działa transport drogowy

Transport drogowy ma działać jako kursy.

Każdy kurs:
- odbiera określoną ilość ropy,
- ma czas wyjazdu,
- ma czas dotarcia,
- może się opóźnić,
- może zostać utracony.

### 7.5. Co może pójść źle

Na drodze mogą wystąpić:
- kradzież kursu,
- napad,
- sabotaż,
- wypadek,
- blokada trasy.

Najważniejsza zasada:
kradzież lub napad oznaczają utratę całego kursu, a nie tylko części ładunku.

### 7.6. Co ma widzieć gracz

Gracz ma widzieć:
- czy odwiert używa transportu drogowego,
- ile kursów jest w drodze,
- kiedy kurs dotrze,
- czy kurs się opóźnił,
- czy kurs został utracony,
- ile ropy naprawdę dotarło,
- ile kosztował przewóz drogowy.

---

## 8. Podstawowa logistyka odwiertów morskich


### 8.1. Co to jest

To pierwszy etap logistyki dla odwiertów morskich.

### 8.2. Po co istnieje

Odwiert morski nie może działać tą samą logiką co odwiert lądowy.

### 8.3. Zasada podstawowa

Na pierwszym etapie odwiert morski korzysta z transportu morskiego.

Nie wdrażamy jeszcze oddzielnego podmorskiego rurociągu jako wariantu podstawowego.
To będzie przyszłe rozszerzenie premium.

### 8.4. Parametry transportu morskiego

Transport morski powinien mieć:
- pojemność,
- koszt,
- czas podróży,
- ryzyko opóźnienia,
- ryzyko awarii,
- ryzyko sztormu,
- ryzyko utraty ładunku.

### 8.5. Najważniejsza zasada

Ropa z odwiertu morskiego nie może pojawiać się w porcie lub magazynie natychmiast.
Transport morski trwa.

Czyli:
- ropa wypływa,
- jest w drodze,
- dopiero po czasie trafia do kolejnego etapu.

### 8.6. Przyszłe rozszerzenie premium

W przyszłości odwierty morskie mogą dostać droższy wariant premium infrastruktury.

### TODO — wariant premium dla odwiertów morskich

W przyszłości dodać premium dla odwiertów morskich, np. lepszą infrastrukturę przesyłową lub bardziej zaawansowany system odbioru ropy, który będzie stabilniejszy i mniej zależny od podstawowego transportu morskiego.

---

## 9. Dostawy morskie w czasie

### 9.1. Co to jest

Dostawa morska to osobny obiekt systemowy opisujący transport ropy z odwiertu morskiego do portu.

### 9.2. Po co to istnieje

Bez tego ropa teleportowałaby się do portu lub magazynu.
To jest nielogiczne.

### 9.3. Każda dostawa morska powinna mieć

- gracza,
- odwiert źródłowy,
- ilość ropy,
- region startowy,
- port docelowy,
- czas rozpoczęcia,
- przewidywany czas dotarcia,
- status.

### 9.4. Statusy dostawy morskiej

- `departing` — wypływa,
- `in_transit` — jest w drodze,
- `waiting_for_port` — czeka na przyjęcie w porcie,
- `processing` — jest obsługiwana przez port,
- `delivered` — została przyjęta,
- `delayed` — jest opóźniona,
- `lost` — została utracona.

### 9.5. Co może spowodować opóźnienie lub utratę

Dostawa morska może zostać opóźniona lub utracona przez:
- warunki atmosferyczne,
- sztorm,
- piratów,
- sabotaż,
- awarię środka transportu,
- kataklizmy,
- przeciążenie portu,
- blokadę trasy.

### 9.6. Tick dostaw morskich

Tick ma:
- sprawdzać przewidywany czas dotarcia,
- zmieniać status dostaw,
- dodawać opóźnienia,
- oznaczać część dostaw jako utracone,
- przekazywać dotarte dostawy do kolejki portowej.

---

## 10. Porty i terminal przeładunkowy

### 10.1. Zasada podstawowa

Porty są systemowe.
Nie należą do gracza.
Gracz korzysta z portów, ale ich nie posiada.

### 10.2. Terminal przeładunkowy

Terminal przeładunkowy nie jest osobnym niezależnym obiektem.
Terminal przeładunkowy jest częścią portu.

To oznacza, że port ma wewnątrz własną warstwę przyjęcia, przeładunku i obsługi dostaw.

### 10.3. Co to jest port

Port to systemowy punkt odbioru ropy z transportu morskiego.

Port:
- przyjmuje dostawy,
- tworzy kolejkę,
- ma ograniczoną przepustowość,
- ma własne opłaty,
- może być przeciążony,
- może mieć własne problemy i opóźnienia.

### 10.4. Co port powinien mieć

Każdy port powinien mieć:
- region,
- nazwę,
- typ,
- przepustowość na tick,
- kolejkę oczekujących dostaw,
- koszt obsługi,
- czas przeładunku,
- ryzyko przeciążenia,
- ryzyko awarii,
- status.

### 10.5. Różne porty

Porty mają być różne.
Nie każdy port ma działać tak samo.

Porty mogą różnić się:
- przepustowością,
- kosztem,
- czasem obsługi,
- ryzykiem przeciążenia,
- stabilnością,
- częstotliwością opóźnień.

---

## 11. Kolejki portowe

### 11.1. Po co istnieją

Port nie obsługuje tylko jednego gracza.
Dlatego port musi mieć kolejkę.

### 11.2. Zasada działania

Dostawa, która dopłynęła do portu, nie musi być od razu rozładowana.

Najpierw trafia do kolejki portowej.
Dopiero potem port przyjmuje tyle, ile pozwala jego przepustowość i aktualne obciążenie.

### 11.3. Co wpływa na kolejkę

Na kolejkę wpływa:
- liczba dostaw,
- przepustowość portu,
- przeciążenie portu,
- awarie portu,
- warunki pogodowe,
- kataklizmy,
- losowe zdarzenia,
- blokady.

### 11.4. Losowość kolejki

Kolejka może mieć element losowy, ale nie może być czystym przypadkiem.

Opóźnienia mają wynikać głównie z:
- przepustowości,
- liczby dostaw,
- stanu portu,
- awarii,
- warunków pogodowych,
- a dopiero dodatkowo z losowych utrudnień.

### 11.5. Tick portu

Tick portu ma:
- przyjmować nowe dostawy do kolejki,
- przetwarzać tylko część kolejki,
- zostawiać resztę na kolejne ticki,
- naliczać opóźnienia,
- naliczać koszty,
- przenosić obsłużoną ropę dalej.

---

## 12. Hub logistyczny i przyszły hub przeładunkowy

### 12.1. Stan obecny

Hub logistyczny już działa jako moduł w grze.

### 12.2. Rola obecna

Obecny hub działa jako regionalny obiekt logistyczny z własnym tickiem, kosztami, stanem technicznym, incydentami i integracją z Finansami, Technicznym i BHP.

### 12.3. Rola przyszła

W przyszłości system ma zostać rozbudowany o pełniejszy hub przeładunkowy.

### 12.4. Zasada rozwoju

Hub przeładunkowy nie jest pierwszym obowiązkowym etapem logistyki.

Najpierw wdrażamy:
- rurociąg per odwiert,
- transport drogowy jako kursy,
- podstawową logistykę morską,
- dostawy morskie w czasie,
- porty z kolejką,
- terminal przeładunkowy jako część portu.

Dopiero później wdrażamy pełny hub przeładunkowy jako dodatkową warstwę systemu.

### 12.5. Zakres przyszły

Hub przeładunkowy ma w przyszłości działać nie tylko dla infrastruktury lądowej.
Docelowo ma być rozwinięty tak, aby dało się go połączyć również z infrastrukturą morską.

### TODO — hub przeładunkowy jako późniejsza rozbudowa

W późniejszym etapie rozbudować system tak, aby hub przeładunkowy działał jako dodatkowy etap dla całej logistyki, w tym także dla infrastruktury morskiej.

---

## 13. Co z czym połączyć

### 13.1. Tick

Tick ma zostać połączony z:
- produkcją odwiertu,
- rurociągiem,
- transportem drogowym,
- dostawami morskimi,
- kolejką portową,
- hubami,
- magazynem,
- sprzedażą.

### 13.2. Finanse

Finanse mają liczyć:
- zakup rurociągu,
- koszt transportu drogowego,
- koszt transportu morskiego,
- opłaty portowe,
- koszty utrzymania,
- koszty napraw,
- koszty konserwacji,
- wartość utraconej ropy,
- skutki incydentów.

### 13.3. Techniczny

Techniczny ma obsługiwać:
- konserwację hubów,
- naprawę hubów,
- konserwację rurociągów,
- naprawę rurociągów,
- priorytety serwisowe infrastruktury.

### 13.4. BHP

BHP ma wpływać na:
- ryzyko incydentów hubów,
- ryzyko incydentów rurociągów,
- ryzyko transportu drogowego,
- ryzyko transportu morskiego,
- skutki zdarzeń krytycznych.

### 13.5. Panel gracza

Panel gracza ma pokazywać:
- gdzie ropa się gubi,
- gdzie logistyka zjada wynik,
- które odwierty nie mają rurociągu,
- które używają drogówki,
- które dostawy morskie są opóźnione,
- który port jest przeciążony,
- który hub jest przeciążony lub uszkodzony.

### 13.6. Panel admina

Panel admina ma umożliwiać:
- konfigurację hubów,
- konfigurację rurociągów,
- konfigurację transportu drogowego,
- konfigurację transportu morskiego,
- konfigurację portów,
- podgląd kolejek portowych,
- podgląd incydentów,
- wymuszanie awarii,
- balans kosztów i ryzyk.

---

## 14. Kolejność wdrożenia

### Etap 1
Wdrożyć zasadę:
magazyn dostaje tylko ropę, która naprawdę została dostarczona.

### Etap 2
Wdrożyć rurociąg przypisany do odwiertu lądowego.

### Etap 3
Wdrożyć transport drogowy jako kursy z incydentami.

### Etap 4
Wdrożyć podstawową logistykę odwiertów morskich.

### Etap 5
Wdrożyć dostawy morskie w czasie.

### Etap 6
Wdrożyć porty systemowe i terminal przeładunkowy jako część portu.

### Etap 7
Dopiąć różne typy portów, obciążenie portów, opóźnienia, utraty dostaw i kolejki.

### Etap 8
Rozbudować obecne huby o przyszły pełny hub przeładunkowy jako dodatkową warstwę logistyki dla lądu i później także dla infrastruktury morskiej.

---

## 15. Otwarte decyzje projektowe

Do osobnego dopięcia później:
- czy gracz może wpływać na wybór portu,
- czy część portów ma być lepsza od innych pod określone regiony,
- czy premium offshore ma być bardzo drogi, ale prawie bezpieczny,
- czy przyszły hub przeładunkowy ma być wspólny dla całego regionu,
- czy w przyszłości infrastruktura morska ma mieć własne specjalne incydenty ponad obecny zakres,
- czy port może zostać całkowicie zamknięty na jakiś czas przez wydarzenie globalne.
