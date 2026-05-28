# Brief dla testerów — logistyka ropy

## Cel testów

Trzeba sprawdzić, czy logistyka ropy działa dla gracza jasno, logicznie i bez błędów.

Najważniejsza zasada:

**Ropa nie może trafiać do magazynu automatycznie tylko dlatego, że została wydobyta.**

Ropa ma przechodzić przez prawdziwe etapy transportu i dopiero wtedy trafiać do magazynu.

---

## Główne ścieżki, które trzeba testować

### Odwiert lądowy

Ropa ma iść tak:

**odwiert → rurociąg albo transport drogowy → hub → rurociąg albo transport drogowy → magazyn**

### Odwiert morski

Ropa ma iść tak:

**odwiert → transport morski → hub → rurociąg albo transport drogowy → magazyn**

Tester ma sprawdzić, czy gra naprawdę działa według tego schematu.

---

## Co tester ma sprawdzić przy odwiertach lądowych

### 1. Sam zakup odwiertu nie wystarcza

Po zakupie odwiertu i przypisaniu ludzi odwiert nie powinien jeszcze działać w pełni, jeśli nie ma pełnej logistyki.

Trzeba sprawdzić, czy gracz musi dodatkowo:
- wybrać hub,
- wybrać transport do huba,
- wybrać transport z huba do magazynu.

### 2. Rurociąg

Trzeba sprawdzić, czy:
- rurociąg nie jest darmowy,
- trzeba go kupić,
- jego budowa trwa określony czas,
- nie działa od razu po kliknięciu,
- gracz widzi, ile zostało czasu do końca budowy,
- po zakończeniu budowy zaczyna działać poprawnie.

### 3. Transport drogowy

Trzeba sprawdzić, czy:
- transport drogowy działa bez rurociągu,
- nie działa natychmiast,
- każdy przewóz trwa określony czas,
- gracz widzi przewidywany czas transportu,
- ropa dociera dopiero po czasie,
- transport drogowy ma sens jako rozwiązanie tymczasowe albo awaryjne.

### 4. Incydenty drogowe

Trzeba sprawdzić, czy mogą występować:
- kradzież,
- napad,
- sabotaż,
- wypadek,
- blokada trasy.

Najważniejsze:
jeśli dochodzi do kradzieży albo napadu, cały kurs powinien przepaść.

---

## Co tester ma sprawdzić przy odwiertach morskich

### 1. Transport morski

Trzeba sprawdzić, czy:
- odwiert morski nie używa zwykłego lądowego rurociągu,
- ropa płynie transportem morskim do huba,
- transport morski trwa określony czas,
- ropa nie pojawia się od razu przy hubie,
- gracz widzi przewidywany czas dostawy.

### 2. Incydenty morskie

Trzeba sprawdzić, czy mogą występować:
- zła pogoda,
- sztorm,
- piraci,
- kataklizm,
- awaria jednostki,
- sabotaż.

Trzeba sprawdzić, czy dostawa może:
- dotrzeć normalnie,
- dotrzeć z opóźnieniem,
- dotrzeć tylko częściowo,
- przepaść całkowicie.

---

## Co tester ma sprawdzić przy hubach

### 1. Hub jest obowiązkowy

Trzeba sprawdzić, czy ropa przechodzi przez hub przed magazynem.

### 2. Hub nie działa „magicznie”

Trzeba sprawdzić, czy hub przyjmuje tylko tę ropę, która naprawdę do niego dotarła.

### 3. Ograniczenia lokalizacji

Trzeba sprawdzić, czy odwiert można przypiąć tylko do odpowiedniego huba:
- lokalnego,
- albo granicznego.

Nie może być tak, że odwiert z jednego kraju można bez sensu przypiąć do bardzo odległego huba.

### 4. Przepięcie odwiertu do innego huba

Trzeba sprawdzić, czy:
- przepięcie nie dzieje się od razu,
- wymaga potwierdzenia,
- trwa określony czas,
- w tym czasie odwiert nie przekazuje ropy dalej,
- koszty odwiertu nadal są naliczane.

To ma boleć finansowo.

---

## Co tester ma sprawdzić przy transporcie z huba do magazynu

Po dotarciu ropy do huba ropa ma jeszcze przejść z huba do magazynu.

Trzeba sprawdzić, czy:
- ten etap naprawdę istnieje,
- gracz może wybrać rurociąg albo transport drogowy,
- ten etap też trwa i może mieć problemy,
- magazyn dostaje tylko tę ropę, która naprawdę przeszła także ten ostatni odcinek.

---

## Co tester ma sprawdzić w magazynie

Magazyn nie może dostawać całej wydobytej ropy automatycznie.

Tester ma sprawdzić, czy magazyn dostaje tylko tę ropę, która:
- została wydobyta,
- została dowieziona do huba,
- została przyjęta przez hub,
- została dowieziona z huba do magazynu.

Jeśli któryś etap nie działa, ropa nie powinna pojawiać się w magazynie.

---

## Co tester ma sprawdzić w powiadomieniach

Powiadomienia mają być:
- czytelne,
- po polsku,
- proste,
- zrozumiałe.

Tester ma sprawdzić, czy gracz dostaje komunikaty o:
- rozpoczęciu budowy rurociągu,
- zakończeniu budowy rurociągu,
- wysłaniu kursu drogowego,
- opóźnieniu kursu,
- utracie kursu,
- wysłaniu dostawy morskiej,
- opóźnieniu dostawy morskiej,
- stracie części ładunku,
- stracie całej dostawy,
- przeciążeniu huba,
- rozpoczęciu przepięcia do innego huba,
- zakończeniu przepięcia,
- dotarciu ropy do magazynu.

---

## Co tester ma sprawdzić w oknach potwierdzenia

Ważne działania nie mogą dziać się bez ostrzeżenia.

Tester ma sprawdzić, czy okno potwierdzenia pojawia się przy:
- budowie rurociągu,
- przepięciu odwiertu do innego huba,
- zmianie transportu,
- wyłączeniu aktywnego rurociągu,
- anulowaniu ważnej operacji.

Okno ma jasno mówić:
- co gracz robi,
- ile to kosztuje,
- ile to potrwa,
- czy odwiert się zatrzyma,
- czy koszty będą dalej naliczane.

---

## Co tester ma sprawdzić przy czasie rzeczywistym

Jeśli coś trwa, gracz ma widzieć dokładny pozostały czas.

Tester ma sprawdzić, czy gra pokazuje:
- ile zostało do końca budowy rurociągu,
- ile zostało do końca kursu drogowego,
- ile zostało do końca dostawy morskiej,
- ile zostało do końca przepięcia odwiertu do innego huba,
- ile zostało do końca naprawy albo konserwacji, jeśli blokują działanie.

---

## Co tester ma sprawdzić w finansach

W finansach ma być codzienne podsumowanie logistyki.

Tester ma sprawdzić, czy gracz widzi:
- ile zarobił,
- ile stracił,
- jakie były koszty logistyki,
- czy były opóźnienia,
- jaki był powód strat i opóźnień.

Dobrze, jeśli widać też:
- co najbardziej obniżyło wynik tego dnia.

Na przykład:
- strata przez okradziony kurs,
- strata przez sztorm,
- strata przez przeciążony hub,
- wysoki koszt transportu drogowego.

---

## Co tester ma sprawdzić w panelu logistyki

Tester ma sprawdzić, czy panel logistyki pokazuje jasno:
- odwierty bez rurociągu,
- odwierty z rurociągiem w budowie,
- najgorsze rurociągi,
- największe straty drogowe,
- największe straty morskie,
- przeciążone huby,
- aktywny transport z huba do magazynu,
- czytelne rekomendacje, co warto zrobić.

---

## Co tester ma sprawdzić w panelu admina

Tester po stronie administracyjnej ma sprawdzić, czy admin widzi i kontroluje:
- rurociągi,
- transport drogowy,
- transport morski,
- huby,
- transport z huba do magazynu,
- straty,
- opóźnienia,
- incydenty.

Admin ma mieć możliwość:
- zmiany czasu budowy rurociągu,
- zmiany kosztów,
- wymuszania awarii,
- wymuszania opóźnień,
- wymuszania utraty kursów i dostaw,
- naprawiania elementów logistyki,
- zatrzymywania i wznawiania działania.

---

## Najważniejsze pytania dla testera

Tester ma umieć odpowiedzieć na koniec testów:

- Czy ropa naprawdę przechodzi przez wszystkie etapy logistyki?
- Czy magazyn dostaje tylko to, co naprawdę dotarło?
- Czy transport drogowy i morski naprawdę trwają w czasie?
- Czy rurociąg trzeba naprawdę kupić i zbudować?
- Czy przepięcie do nowego huba naprawdę zatrzymuje odwiert?
- Czy gracz dostaje jasne komunikaty?
- Czy gracz widzi czasy i koszty?
- Czy finanse pokazują, gdzie i dlaczego były straty?
- Czy system jest dla gracza zrozumiały?

---

## Najkrótsza wersja dla testerów

Tester ma sprawdzić, czy ropa nie trafia automatycznie do magazynu po wydobyciu, tylko przechodzi przez prawdziwe etapy logistyki. Dla odwiertu lądowego ma to być rurociąg albo droga do huba, a potem kolejny transport do magazynu. Dla odwiertu morskiego ma to być transport morski do huba, a potem transport do magazynu. Każdy etap ma trwać określony czas, może mieć incydenty i może powodować straty. Gracz ma widzieć powiadomienia, czasy, koszty, potwierdzenia ważnych działań i podsumowanie dnia w finansach.
