# Logistics Hubs README — wersja uporządkowana i rozszerzona

## Cel pliku

Ten plik jest roboczym, uporządkowanym opisem systemu logistyki ropy w projekcie.

Ma łączyć dwie rzeczy:

1. **stan faktyczny potwierdzony w kodzie i testach**,  
2. **obowiązujące decyzje projektowe i kierunek dalszego rozwoju**.

Ten dokument ma być czytelny dla człowieka i wystarczająco dokładny dla AI, które ma rozwijać system dalej.

Wszystkie opisy, komunikaty i nazwy w UI mają być pisane **prostym językiem po polsku**.

---

## 1. Stan faktyczny potwierdzony w kodzie

Na dziś system logistyki jest wdrożony szerzej, niż sugerowały starsze dokumenty.

### 1.1. Huby logistyczne
Wdrożone są:
- budowa, naprawa, ulepszanie, pauza i wznowienie,
- zmiana trybu pracy i nazwy,
- stany huba,
- poziomy, kondycja, zużycie, sprawność i limity slotów,
- przepustowość nominalna i realna,
- bufor ropy,
- koszty działania,
- incydenty,
- praca w ticku.

### 1.2. Przypisywanie odwiertów do hubów
Wdrożone są:
- przypisanie odwiertu do huba,
- odpięcie odwiertu,
- przeniesienie odwiertu między hubami,
- lista dostępnych hubów,
- szczegóły huba i przypiętych odwiertów.

Działają też reguły:
- zgodność regionu,
- limit slotów,
- cooldown,
- filtrowanie do odwiertów gracza,
- sprawdzanie aktywności i stanu huba.

### 1.3. Model `new / used / rental`
W danych i ekonomii działają:
- typ pozyskania,
- mnożniki kosztu budowy,
- początkowy zakres kondycji,
- czynsz za wynajem,
- prezentacja w UI gracza i admina,
- potwierdzenia dla wynajmu.

### 1.4. UI gracza dla hubów
Wdrożone są:
- ekran logistyki,
- lista hubów i ich statystyki,
- widoczność typu pozyskania,
- widoczność czynszu,
- podgląd bufora,
- modal przypisania odwiertu,
- modal transferu odwiertu,
- komunikaty ostrzegawcze i sukcesu.

### 1.5. UI admina dla hubów
Wdrożone są:
- budowa huba,
- wybór typu pozyskania przy budowie,
- podgląd stanu, kondycji, czynszu i bufora,
- pauza i wznowienie,
- naprawa,
- ulepszenie,
- zmiana nazwy,
- konfiguracja parametrów pozyskania.

### 1.6. Tick i ekonomia
Tick uwzględnia:
- stan i zużycie hubów,
- koszty działania,
- straty hubowe,
- zdarzenia,
- pracę z buforem,
- finalizację po pętli odwiertów.

### 1.7. Rurociągi per odwiert
To jest realnie wdrożony system.

W praktyce:
- odwiert lądowy może mieć własny wpis rurociągu,
- jeśli odwiert ma ustawiony transport „rurociąg”, ale gracz nie ma własnego rurociągu, efektywny transport spada do ciężarówek,
- system nie tworzy rurociągu sam z siebie bez warunków.

### 1.8. Transport drogowy
Transport drogowy jest wdrożony jako system kursów.

### 1.9. Transport morski
Transport morski dla odwiertów offshore jest wdrożony jako system rejsów.

### 1.10. Dostawy morskie
Dostawy morskie i ich statusy są wdrożone.

### 1.11. Porty
Porty są wdrożone jako osobny system.

### 1.12. Logika dostarczonego wolumenu
System rozdziela wolumen wydobyty od wolumenu dostarczonego w logice finansowej i tickowej.

---

## 2. Najważniejsze decyzje projektowe obowiązujące od teraz

### 2.1. Hub zostaje systemowy
Na tym etapie gracz **nie kupuje huba na własność**.

Hub jest infrastrukturą systemową, z której gracz korzysta.

Nie wdrażamy teraz pełnego modelu:
- zakupu huba przez gracza,
- posiadania huba przez gracza,
- przepisywania hubów na majątek firmy.

### 2.2. Hub jest obowiązkowym punktem pośrednim
Dla obecnego kierunku rozwoju przyjmujemy, że ropa ma przechodzić przez hub przed magazynem.

### 2.3. Główne ścieżki przepływu ropy

#### Dla odwiertu lądowego
**odwiert → rurociąg albo transport drogowy → hub → rurociąg albo transport drogowy → magazyn**

#### Dla odwiertu morskiego
**odwiert → transport morski → hub → rurociąg albo transport drogowy → magazyn**

Na każdym etapie mogą wystąpić:
- koszty,
- opóźnienia,
- straty,
- incydenty.

### 2.4. Sam zakup odwiertu nie wystarcza
Sam zakup odwiertu i przypisanie ludzi nie uruchamia jeszcze pełnej pracy odwiertu.

Aby odwiert działał poprawnie, musi mieć:
- ludzi,
- przypisany hub,
- aktywny transport do huba,
- aktywny transport z huba do magazynu.

### 2.5. Hub musi być lokalny albo graniczny
Nie można przypiąć odwiertu do dowolnego huba na świecie.

Hub musi być:
- lokalny,
- albo graniczny,
- albo dozwolony przez mapę połączeń.

Nie może być tak, że odwiert w Polsce trafia do huba w Afryce.

### 2.6. Przepięcie odwiertu do innego huba trwa
Przepięcie odwiertu:
- wymaga potwierdzenia,
- trwa określony czas,
- w tym czasie odwiert nie przekazuje ropy dalej,
- koszty odwiertu nadal są naliczane.

### 2.7. Rurociąg trzeba kupić
Rurociąg:
- nie jest darmowy,
- trzeba go kupić,
- jego położenie trwa określony czas,
- koszt i czas budowy są ustawiane w panelu admina.

### 2.8. Transport drogowy ma być potrzebny
Transport drogowy ma być:
- droższy w użyciu,
- ale szybszy do uruchomienia,
- przydatny na start,
- przydatny tymczasowo,
- przydatny awaryjnie,
- przydatny dla małych odwiertów.

Jeśli dziś się nie opłaca, trzeba poprawić jego balans.

### 2.9. Powiadomienia i potwierdzenia są obowiązkowe
Gracz ma dostawać czytelne powiadomienia i okna potwierdzenia przy ważnych działaniach.

### 2.10. Jeśli coś trwa, gracz ma widzieć czas
Jeśli trwa:
- budowa rurociągu,
- kurs drogowy,
- transport morski,
- przepięcie do nowego huba,
- naprawa,
- konserwacja,

to gracz ma widzieć dokładny pozostały czas.

### 2.11. Finanse mają pokazywać podsumowanie dnia
Dział finansowy ma codziennie pokazywać:
- zysk,
- straty,
- koszty logistyki,
- opóźnienia,
- powody opóźnień i strat.

---

## 3. Logika odwiertu lądowego

### 3.1. Główna zasada
Jeśli odwiert lądowy ma rurociąg, ropa idzie rurociągiem.

Jeśli odwiert lądowy nie ma rurociągu, ropa idzie transportem drogowym.

Jeśli odwiert nie ma ani sprawnego rurociągu, ani aktywnego transportu drogowego, ropa nie trafia dalej.

### 3.2. Uruchomienie odwiertu lądowego
Odwiert lądowy ma działać poprawnie dopiero wtedy, gdy:
- ma przypisanych ludzi,
- ma wybrany hub,
- ma aktywny transport do huba,
- ma aktywny transport z huba do magazynu.

### 3.3. Co ma widzieć gracz
Gracz ma widzieć:
- czy odwiert ma rurociąg,
- czy rurociąg jest w budowie,
- ile czasu zostało do końca budowy,
- czy odwiert jedzie drogą,
- ile ropy naprawdę dotarło do huba,
- ile ropy nie dotarło,
- ile kosztował transport,
- jakie były problemy.

---

## 4. Rurociąg

### 4.1. Czym jest
Rurociąg jest połączeniem przypisanym do konkretnego odwiertu lądowego.

Nie jest wspólny dla całego regionu.  
Nie jest darmowy.  
Nie jest tylko liczbą w panelu.

### 4.2. Co ma robić
Rurociąg ma być głównym i stabilnym sposobem odbioru ropy z odwiertu lądowego.

### 4.3. Jak gracz uruchamia rurociąg
Na karcie odwiertu w sekcji **Transport** gracz wybiera **Rurociąg**.

Jeśli odwiert jest lądowy i nie ma jeszcze rurociągu, system:
- rozpoczyna budowę rurociągu,
- nalicza koszt,
- przypisuje rurociąg do odwiertu,
- pokazuje, że rurociąg jest w budowie.

### 4.4. Czas budowy
Położenie rurociągu trwa określony czas.

Czas budowy:
- nie może być zerowy,
- ma być ustawiany przez admina,
- ma być widoczny dla gracza.

### 4.5. Co dzieje się w czasie budowy
Dopóki rurociąg nie jest gotowy:
- ropa nie idzie rurociągiem,
- gracz może używać transportu drogowego jako rozwiązania tymczasowego.

### 4.6. Rodzaje rurociągu
Na start wystarczą:
- lekki,
- standardowy,
- mocny.

### 4.7. Co wpływa na działanie rurociągu
Na rurociąg wpływają:
- jego rodzaj,
- stan techniczny,
- ilość ropy,
- zużycie,
- konserwacja,
- naprawy,
- awarie,
- wycieki,
- sabotaż,
- wpływ Technicznego,
- wpływ BHP.

### 4.8. Stany rurociągu
Rurociąg może być:
- planowany,
- w budowie,
- aktywny,
- uszkodzony,
- z wyciekiem,
- wstrzymany,
- wyłączony.

### 4.9. Problemy na rurociągu
Na rurociągu mogą wystąpić:
- mały wyciek,
- duży wyciek,
- awaria,
- spadek przepływu,
- uszkodzenie,
- sabotaż.

### 4.10. Co system ma liczyć
System ma liczyć:
- ile odwiert wydobył,
- ile rurociąg może przesłać,
- ile naprawdę przesłał,
- ile ropy utracono,
- jaki jest nowy stan rurociągu,
- czy wystąpił problem.

### 4.11. Co ma widzieć gracz
Gracz ma widzieć:
- rodzaj rurociągu,
- stan techniczny,
- czas do końca budowy,
- ile ropy przechodzi,
- ile ropy się traci,
- koszt utrzymania,
- ryzyko awarii,
- czy rurociąg wymaga naprawy.

### 4.12. Co ma widzieć admin
Admin ma widzieć:
- listę rurociągów,
- gracza,
- odwiert,
- stan techniczny,
- czy rurociąg jest w budowie,
- czas do końca budowy,
- przepustowość,
- straty,
- awarie.

Admin ma móc:
- zmieniać czas budowy,
- zmieniać koszt położenia,
- wymuszać awarie,
- naprawiać,
- zmieniać stan,
- zmieniać przepustowość,
- wyłączać i włączać.

---

## 5. Transport drogowy

### 5.1. Czym jest
Transport drogowy jest rozwiązaniem zastępczym, tymczasowym albo awaryjnym.

### 5.2. Po co istnieje
Ma być używany wtedy, gdy:
- gracz nie ma jeszcze rurociągu,
- rurociąg jest w budowie,
- rurociąg się zepsuł,
- gracz uruchamia nowy region,
- gracz przepina odwiert do nowego huba,
- mały odwiert nie uzasadnia jeszcze budowy rurociągu.

### 5.3. Główna zasada
Transport drogowy nie działa natychmiast.

Działa jako kursy.  
Każdy kurs trwa określony czas.

### 5.4. Jak działa kurs
Każdy kurs:
- odbiera określoną ilość ropy,
- ma moment wyjazdu,
- ma przewidywany czas dotarcia,
- może się opóźnić,
- może zostać utracony.

### 5.5. Incydenty drogowe
Na drodze mogą wystąpić:
- kradzież kursu,
- napad,
- sabotaż,
- wypadek,
- blokada trasy,
- awaria pojazdu.

Najważniejsza zasada:
**kradzież albo napad oznaczają utratę całego kursu**

### 5.6. Co ma widzieć gracz
Gracz ma widzieć:
- czy odwiert jedzie drogą,
- ile kursów jest w drodze,
- kiedy kurs dotrze,
- czy kurs się opóźnił,
- czy kurs został utracony,
- ile ropy naprawdę dotarło,
- ile kosztował transport drogowy.

### 5.7. Co ma widzieć admin
Admin ma widzieć:
- listę kursów,
- gracza,
- odwiert,
- ilość ropy,
- czas wyjazdu,
- czas dotarcia,
- opóźnienia,
- utracone kursy,
- powód problemu.

Admin ma móc:
- zmieniać koszt transportu,
- zmieniać czas przejazdu,
- wymuszać opóźnienia,
- wymuszać utratę kursu,
- usuwać testowe kursy.

---

## 6. Logika odwiertu morskiego

### 6.1. Główna zasada
Odwiert morski nie używa lądowego rurociągu.

Ropa z odwiertu morskiego ma iść transportem morskim do huba.

### 6.2. Warunki poprawnej pracy
Odwiert morski ma działać poprawnie dopiero wtedy, gdy:
- ma ludzi,
- ma wybrany hub w odpowiednim regionie lub strefie,
- ma aktywny transport morski do huba,
- ma aktywny transport z huba do magazynu.

### 6.3. Co ma widzieć gracz
Gracz ma widzieć:
- czy tankowiec płynie,
- ile czasu zostało do dotarcia,
- czy dostawa się opóźniła,
- czy część ładunku przepadła,
- czy cała dostawa przepadła,
- do którego huba płynie ropa.

---

## 7. Transport morski

### 7.1. Czym jest
To dostawy ropy z odwiertu morskiego do huba.

### 7.2. Jak działa
Każda dostawa:
- zabiera określoną ilość ropy,
- ma moment wypłynięcia,
- ma czas dotarcia,
- ma hub docelowy,
- ma status,
- może się opóźnić,
- może zostać częściowo utracona,
- może zostać utracona całkowicie.

### 7.3. Czas
Transport morski trwa.  
Ropa nie pojawia się od razu przy hubie.

### 7.4. Incydenty morskie
Na morzu mogą wystąpić:
- zła pogoda,
- sztorm,
- piraci,
- kataklizm,
- awaria jednostki,
- sabotaż.

### 7.5. Co ma widzieć gracz
Gracz ma widzieć:
- aktywne dostawy morskie,
- czas dotarcia,
- opóźnienia,
- straty,
- powody problemów,
- docelowy hub.

### 7.6. Co ma widzieć admin
Admin ma widzieć:
- wszystkie dostawy morskie,
- gracza,
- odwiert,
- hub docelowy,
- czas wypłynięcia,
- czas dotarcia,
- status,
- straty,
- powód problemu.

Admin ma móc:
- zmieniać czas podróży,
- zmieniać koszt,
- wymuszać opóźnienie,
- wymuszać częściową stratę,
- wymuszać całkowitą stratę,
- oznaczać jako dostarczone.

---

## 8. Hub jako punkt przeładunku

### 8.1. Rola huba
Hub przyjmuje ropę dopiero po dostarczeniu jej do niego.

Nie przyjmuje ropy „z powietrza”.

### 8.2. Co hub robi
Hub:
- przyjmuje ropę,
- buforuje ją,
- przekazuje dalej,
- może być przeciążony,
- może tracić ropę przez stan lub przeciążenie,
- może mieć incydenty.

### 8.3. Najważniejsza zasada
Jeśli ropa nie dotarła do huba, hub nie ma czego przyjąć.

### 8.4. Co ma widzieć gracz
Gracz ma widzieć:
- ile ropy dotarło do huba,
- ile ropy przeszło dalej,
- czy hub jest przeciążony,
- jaki transport z huba do magazynu jest aktywny,
- ile ropy dotarło do magazynu.

### 8.5. Co ma widzieć admin
Admin ma widzieć:
- stan hubów,
- obciążenie,
- bufor,
- straty,
- przeciążenie,
- napływ ropy,
- transport z huba do magazynu.

Admin ma móc:
- zmieniać parametry huba,
- naprawiać,
- wstrzymywać,
- wznawiać,
- zmieniać przepustowość,
- zmieniać koszty,
- wymuszać problemy testowe.

---

## 9. Transport z huba do magazynu

### 9.1. Główna zasada
Po przyjęciu ropy przez hub gracz ma wybrać transport z huba do magazynu.

Do wyboru:
- rurociąg,
- transport drogowy.

### 9.2. Zasada działania
Ten etap też trwa i też może mieć incydenty.

Czyli także między hubem a magazynem mogą wystąpić:
- awarie rurociągu,
- wycieki,
- kradzieże kursów,
- napady,
- wypadki,
- opóźnienia.

### 9.3. Co ma widzieć gracz
Gracz ma widzieć:
- jaki transport z huba do magazynu jest aktywny,
- ile ropy wyszło z huba,
- ile ropy dotarło do magazynu,
- jakie były problemy.

---

## 10. Magazyn

Magazyn nie dostaje całej wydobytej ropy.

Magazyn dostaje tylko tę ropę, która:
- została wydobyta,
- została dostarczona do huba,
- została przyjęta przez hub,
- została dostarczona z huba do magazynu.

To jest jedna z najważniejszych zasad całego systemu.

---

## 11. Przepięcie odwiertu do innego huba

### 11.1. Główna zasada
Gracz może przenieść odwiert do innego huba, ale:
- nie natychmiast,
- nie bez potwierdzenia,
- nie do dowolnego huba na świecie.

### 11.2. Co musi sprawdzić system
System ma sprawdzić:
- czy nowy hub jest lokalny albo graniczny,
- czy ma wolne miejsce,
- czy jest aktywny,
- czy jest dozwolony dla tego odwiertu.

### 11.3. Co się dzieje w czasie przepięcia
W czasie przepięcia:
- odwiert nie przekazuje ropy dalej,
- odwiert nie zarabia normalnie,
- koszty odwiertu nadal są naliczane.

To ma być odczuwalne finansowo.

### 11.4. Potwierdzenie
Przed przepięciem gracz ma zobaczyć:
- do jakiego huba przepina odwiert,
- czy hub jest dozwolony,
- ile potrwa przepięcie,
- że odwiert się zatrzyma,
- że koszty nadal będą naliczane.

---

## 12. Powiadomienia

System ma dawać graczowi czytelne powiadomienia.

### Powiadomienia mają dotyczyć:
- rozpoczęcia budowy rurociągu,
- zakończenia budowy rurociągu,
- wysłania kursu drogowego,
- opóźnienia kursu drogowego,
- utraty kursu drogowego,
- wysłania dostawy morskiej,
- opóźnienia dostawy morskiej,
- utraty części ładunku,
- utraty całej dostawy,
- przeciążenia huba,
- braku możliwości przyjęcia ropy przez hub,
- rozpoczęcia przepięcia odwiertu,
- zakończenia przepięcia,
- braku aktywnego łańcucha logistycznego,
- dotarcia ropy do magazynu.

### Przykładowe komunikaty:
- „Rozpoczęto budowę rurociągu.”
- „Budowa rurociągu zakończy się za 20 minut.”
- „Kurs z ropą jest w drodze.”
- „Kurs opóźnił się.”
- „Kurs został okradziony. Ropa nie dotarła.”
- „Tankowiec płynie do huba.”
- „Dostawa morska opóźniła się przez sztorm.”
- „Hub nie przyjął całej ropy.”
- „Przepięcie odwiertu trwa.”
- „Przepięcie zakończone.”
- „Ropa trafiła do magazynu.”

---

## 13. Potwierdzenia w oknie

Ważne działania mają wymagać potwierdzenia.

### Potwierdzenie ma być wymagane przy:
- wyborze nowego huba,
- przepięciu odwiertu,
- rozpoczęciu budowy rurociągu,
- przełączeniu z rurociągu na drogę,
- przełączeniu z drogi na rurociąg,
- wyłączeniu aktywnego rurociągu,
- anulowaniu budowy rurociągu.

### Okno potwierdzenia ma mówić:
- co gracz robi,
- ile to kosztuje,
- ile to potrwa,
- czy odwiert się zatrzyma,
- czy ropa nie będzie trafiała dalej,
- czy koszty nadal będą naliczane.

---

## 14. Czas rzeczywisty

Jeśli coś trwa, gracz ma widzieć dokładny pozostały czas.

Dotyczy to:
- budowy rurociągu,
- kursu drogowego,
- transportu morskiego,
- przepięcia do nowego huba,
- napraw,
- konserwacji, jeśli blokują działanie.

Przykłady:
- „Do końca budowy zostało 20 minut.”
- „Kurs dotrze za 12 minut.”
- „Tankowiec dotrze za 35 minut.”
- „Przepięcie zakończy się za 10 minut.”

---

## 15. Podsumowanie dnia w finansach

W dziale finansowym gracz ma dostawać codzienne podsumowanie logistyki.

### Ma widzieć:
- ile zyskał,
- ile stracił,
- jakie były koszty logistyki,
- czy wystąpiły opóźnienia,
- z jakiego powodu były straty.

### Podsumowanie ma zawierać:
- ilość ropy dostarczonej do magazynu,
- ilość ropy sprzedanej,
- przychód,
- straty na rurociągach,
- straty na drodze,
- straty na morzu,
- straty na hubach,
- koszty budowy rurociągów,
- koszty transportu drogowego,
- koszty transportu morskiego,
- koszty utrzymania,
- koszty napraw i konserwacji,
- liczbę opóźnień,
- powód opóźnień.

### Dobrze dodać też:
„Co najbardziej obniżyło wynik tego dnia”

Na przykład:
- „Największą stratę spowodował okradziony kurs.”
- „Największy koszt wygenerował transport drogowy.”
- „Największe opóźnienie wywołał sztorm.”
- „Największy problem wystąpił na rurociągu odwiertu X.”

---

## 16. Co ma widzieć gracz

### Na karcie odwiertu lądowego
- czy ma rurociąg,
- czy rurociąg jest w budowie,
- ile zostało czasu do końca budowy,
- czy działa drogowo,
- jaki jest przewidywany czas transportu,
- ile ropy dotarło do huba,
- jakie były incydenty.

### Na karcie odwiertu morskiego
- czy tankowiec płynie,
- ile czasu zostało do dotarcia,
- czy dostawa się opóźniła,
- czy część ładunku przepadła,
- czy cała dostawa przepadła,
- do którego huba płynie ropa.

### W logistyce
- odwierty bez rurociągu,
- odwierty z rurociągiem w budowie,
- najgorsze rurociągi,
- największe straty drogowe,
- największe straty morskie,
- huby przeciążone,
- aktywny transport z huba do magazynu,
- rekomendacje, co zrobić.

---

## 17. Co ma widzieć admin

Admin ma mieć pełny podgląd i kontrolę nad całym łańcuchem:

**odwiert → transport do huba → hub → transport do magazynu**

### Admin dla rurociągów
Ma widzieć:
- listę rurociągów,
- gracza,
- odwiert,
- stan techniczny,
- czy jest w budowie,
- czas do zakończenia budowy,
- przepustowość,
- straty,
- awarie.

Ma móc:
- zmieniać czas budowy,
- zmieniać koszt położenia,
- wymuszać awarie,
- naprawiać,
- zmieniać stan,
- zmieniać przepustowość,
- wyłączać i włączać.

### Admin dla transportu drogowego
Ma widzieć:
- listę kursów,
- gracza,
- odwiert,
- ilość ropy,
- czas wyjazdu,
- czas dotarcia,
- opóźnienia,
- utracone kursy,
- powód problemu.

Ma móc:
- zmieniać koszt transportu,
- zmieniać czas przejazdu,
- wymuszać opóźnienia,
- wymuszać utratę kursu,
- usuwać testowe kursy.

### Admin dla transportu morskiego
Ma widzieć:
- wszystkie dostawy morskie,
- gracza,
- odwiert,
- hub docelowy,
- czas wypłynięcia,
- czas dotarcia,
- status,
- straty,
- powód problemu.

Ma móc:
- zmieniać czas podróży,
- zmieniać koszt,
- wymuszać opóźnienia,
- wymuszać częściową stratę,
- wymuszać całkowitą stratę,
- oznaczać jako dostarczone.

### Admin dla hubów
Ma widzieć:
- stan hubów,
- obciążenie,
- bufor,
- straty,
- przeciążenie,
- napływ ropy,
- transport z huba do magazynu.

Ma móc:
- zmieniać parametry huba,
- naprawiać,
- wstrzymywać,
- wznawiać,
- zmieniać przepustowość,
- zmieniać koszty,
- wymuszać problemy testowe.

---

## 18. Co robić teraz

1. Ujednolicić cały przepływ ropy.  
2. Dodać obowiązek pełnego łańcucha logistycznego.  
3. Dopić rurociąg jako kupowaną i budowaną infrastrukturę.  
4. Dopić transport drogowy jako kursy w czasie.  
5. Dopić transport morski jako dostawy w czasie.  
6. Dopić przepięcie odwiertów między hubami.  
7. Dopić powiadomienia i okna potwierdzenia.  
8. Dopić codzienne podsumowanie dnia w finansach.  
9. Dopić czytelny panel gracza i admina.

---

## 19. Czego teraz nie robić

Nie robić teraz zakupu hubów przez gracza.  
Nie przebudowywać hubów na własność firmy.  
Nie przepisywać całej architektury od zera.

Na teraz:
- hub zostaje systemowy,
- gracz inwestuje w odwiert, rurociąg i transport,
- hub jest wspólnym punktem przeładunku przed magazynem.

---

## 20. Najkrótsza wersja końcowa

Gracz kupuje odwiert. Sam zakup odwiertu i przypisanie ludzi nie uruchamia jeszcze pełnej pracy odwiertu. Dla odwiertu lądowego gracz musi wybrać hub i sposób dostarczenia ropy do huba: rurociąg albo transport drogowy. Rurociąg trzeba kupić i zbudować, a budowa trwa określony czas. Jeśli rurociągu jeszcze nie ma, ropa może jechać transportem drogowym, który też kosztuje, trwa i może mieć incydenty. Dla odwiertu morskiego ropa płynie tankowcem do wybranego huba i również może się opóźnić albo zostać utracona. Po dotarciu do huba ropa musi jeszcze zostać przesłana z huba do magazynu — rurociągiem albo transportem drogowym. Hub jest systemowy i służy jako punkt przeładunku przed magazynem. Magazyn dostaje tylko tę ropę, która naprawdę dotarła do huba i została przez hub przyjęta. Gracz ma dostawać czytelne powiadomienia, widzieć czasy w czasie rzeczywistym i mieć w finansach podsumowanie dnia: zyski, straty, koszty i powody opóźnień.
