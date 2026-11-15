# §31. Moduł Rafineryjny (Refining) — Przetwórstwo Ropy Naftowej

---

## 31.1. Wizja i Rola w Grze

Fabryka/rafineria to **endgame'owa gałąź gospodarki** — naturalny krok po fazie czystego wydobycia. Gracz zaczyna od pierwszych odwiertów, buduje łańcuch logistyczny, gromadzi kapitał. Gdy złoża zaczynają się wyczerpywać (spadek wydajności od 70% zasobu, §9.2), staje przed wyborem: kupować kolejne koncesje i wiercić dalej, czy wejść w **przetwórstwo** — gdzie zysk nie zależy już wyłącznie od szczęścia geologicznego, lecz od inwestycji technologicznych, zarządzania kadrą inżynierską i gry rynkowej na produktach.

**Co daje rafineria:**

- **Marżę rafineryjną:** różnicę między ceną produktów a ceną ropy + kosztem przerobu. Przy stabilnym rynku ropy (250 PLN/bbl) marża wynosi typowo 15–45% w zależności od technologii i produktu.
- **Dywersyfikację:** gracz nie jest już zakładnikiem ceny ropy — może kupować tanią ropę od innych graczy (kontrakty B2B §13), przetwarzać i sprzedawać produkty.
- **Niezależność od wyczerpania złóż:** rafineria pracuje na ropie z magazynu — własnej lub kupionej. Nie ma „złoża produktów" do wyczerpania.
- **Nowy wymiar rywalizacji:** gracze walczą o kontrakty produktowe z odbiorcami NPC (linie lotnicze, sieci stacji paliw, armatorzy), o wykwalifikowanych inżynierów chemików, o przepustowość rynku produktowego.
- **Prestiż:** rafineria to symbol przejścia od „kopacza" do „przemysłowca". Widoczna na mapie jako kompleks przemysłowy.

**Dlaczego nie od razu:** budowa rafinerii to inwestycja rzędu 5–50 mln PLN (zależnie od klasy), wymaga pozwoleń środowiskowych, wykwalifikowanej kadry i stałych dostaw ropy. Gracz bez rozwiniętego wydobycia i logistyki nie utrzyma rentowności — rafineria na kupowanej ropie to hazard cenowy.

**Wpisanie w trwały świat (zero-wipe):** rafineria to aktywo trwałe — podlega wycenie majątku netto (§19.1), podatkowi majątkowemu i dziedziczeniu/aukcji (§19.4). Technologie (ulepszenia jednostek) starzeją się — trzeba inwestować w modernizacje, co stanowi stałe ujście pieniądza (sink). Rynek produktowy ma ograniczoną chłonność — pięciu graczy z wielkimi rafineriami nasyci popyt NPC i marże spadną, co jest naturalnym regulatorem.

---

## 31.2. Budowa Rafinerii

### 31.2.1. Wymagania wstępne

| Wymóg | Szczegóły |
|---|---|
| **Region** | Rafineria wymaga działki w regionie z dostępem do infrastruktury: droga krajowa lub port. Regiony górskie i pustynne wykluczone — tylko `plains`, `coastal`, `industrial`. |
| **Pozwolenie środowiskowe (nowy typ)** | Pozwolenie III stopnia — „pozwolenie emisyjne". Koszt bazowy 1 500 000 PLN, rozpatrzenie 24h (moduł Legal). Wymaga `company_credibility ≥ 50`. Stan: `oczekujące → opóźnione → przyznane | odmowa`. Odmowa = 48h karencji przed ponownym wnioskiem. |
| **Kapitał własny / kredyt (§17.1)** | Gracz musi pokryć koszt budowy. Kredyt bankowy dostępny przy `credit_score ≥ 60`. Finansowanie mieszane dozwolone. |
| **Projekt techniczny** | Jednorazowa opłata 200 000 PLN za projekt (czas realizacji: 4h). Obejmuje wybór klasy rafinerii i układu jednostek. |

### 31.2.2. Klasy rafinerii

| Klasa | Nazwa | Przepustowość (bbl/h) | Koszt budowy (PLN) | Czas budowy (h) | Powierzchnia (ha) | Min. załoga |
|---|---|---|---|---|---|---|
| I | **Rozdzielnia atmosferyczna** | 200 | 5 000 000 | 48 | 2 | 4 operatorów |
| II | **Rafineria średnia** | 500 | 15 000 000 | 96 | 5 | 10 operatorów |
| III | **Kompleks petrochemiczny** | 1 200 | 40 000 000 | 168 | 12 | 24 operatorów |

Wartości startowe `game_balance` — do kalibracji symulatorem (§23.10). Koszt i czas × `mnożnik_regionu ∈ [0,85; 1,3]`.

### 31.2.3. Etapy budowy (maszyna stanów)

```
projekt → pozwolenie_środowiskowe → fundamenty → konstrukcja → instalacje → rozruch → aktywny
```

| Etap | Czas (klasa II) | Koszt (% budowy) | Przerwanie |
|---|---|---|---|
| **Fundamenty** | 24 h | 20% | Zwrot 50% niewykorzystanych materiałów |
| **Konstrukcja** | 36 h | 35% | Konstrukcja niszczeje: −2% kondycji/h powyżej 24h przerwy |
| **Instalacje technologiczne** | 24 h | 30% | Montaż wstrzymany — sprzęt czeka w kontenerach (ryzyko kradzieży 0,5%/h) |
| **Rozruch próbny** | 12 h | 15% | Rozruch przerywany = powtórka od zera (koszt rozruchu ×1,5 przy drugiej próbie) |

**Zasada delta-time (§23):** każdy etap to encja `refinery_build_stage` z `started_at`, `eta`, `status`. Leniwa delta + sweeper rozliczają postęp. Brak środków na koncie przy rozpoczęciu kolejnego etapu = budowa WSTRZYMANA (`paused_funding`), nie anulowana — gracz ma 72h na uzupełnienie, potem przepadek 50% wpłaconych środków i zwrot działki do puli.

### 31.2.4. Rozbudowa i jednostki technologiczne

Rafineria to nie monolit — składa się z **jednostek technologicznych** (instalacji), które gracz dokupuje jako ulepszenia:

| Jednostka | Wymagana klasa rafinerii | Koszt (PLN) | Czas montażu | Efekt |
|---|---|---|---|---|
| **Destylacja atmosferyczna (CDU)** | bazowa (wbudowana) | — | — | Rozdziela ropę na frakcje: benzyna surowa, nafta, diesel surowy, mazut. Wydajności bazowe (patrz 31.3.1). |
| **Hydrooczyszczanie (HDS)** | ≥ I | 3 000 000 | 24 h | Odsiarcza frakcje: +8% ceny sprzedaży produktów (normy środowiskowe), −15% ryzyka kary środowiskowej. |
| **Reforming katalityczny** | ≥ II | 8 000 000 | 36 h | Zwiększa udział benzyny wysokooktanowej: udział benzyny +10 p.p., kosztem mazutu −8 p.p. Wymaga HDS. |
| **Kraking katalityczny (FCC)** | ≥ II | 12 000 000 | 48 h | Rozbija ciężkie frakcje: udział mazutu −20 p.p., diesel +12 p.p., benzyna +8 p.p. Wymaga HDS. |
| **Hydrokraking** | III | 20 000 000 | 60 h | Najgłębsza konwersja: mazut −35 p.p., diesel +20 p.p., nafta lotnicza +15 p.p. Wymaga FCC. |
| **Instalacja alkilacji** | ≥ II | 5 000 000 | 24 h | Produkcja wysokooktanowego komponentu benzyny: +5 p.p. do udziału benzyny premium (osobna subfrakcja, +20% ceny). Wymaga FCC. |

**Zasady:**
- Jednostki montowane sekwencyjnie — kraking wymaga HDS, hydrokraking wymaga FCC.
- Każda jednostka ma własny stan, zużycie i ryzyko awarii.
- Montaż nowej jednostki NIE wstrzymuje pracy pozostałych — rafineria działa podczas rozbudowy na dotychczasowych jednostkach.
- Jednostki można wyłączać pojedynczo (np. na czas naprawy) — wtedy frakcje z jej udziałem przechodzą w mazut (najgorszy produkt).

### 31.2.5. Finansowanie budowy

| Źródło | Warunki |
|---|---|
| **Środki własne** | Z `PLN_BANK` lub `PLN_CASH`, standardowy wpis FTS (`reason_code=REFINERY_BUILD`). |
| **Kredyt inwestycyjny** | Odmiana kredytu (§17.1): oprocentowanie −1 p.p. względem standardowego, okres do 180 dni, zabezpieczenie na samej rafinerii (Faza 3 egzekucji obejmuje rafinerię przed odwiertami). |
| **Udziałowiec NPC** | Gracz pokrywa 50% kosztów, NPC 50%. NPC pobiera 40% zysku rafinerii do momentu spłaty jego udziału + 20% premii. Opcja ryzykowna — oddaje kontrolę nad częścią marży. |

---

## 31.3. Produkcja

### 31.3.1. Model przerobu — frakcje i wydajności

**Wsad:** ropa naftowa z magazynu głównego gracza (`PLN_STORAGE`, `used_bbl`) lub ropa kupiona na rynku i skierowana bezpośrednio do rafinerii.

Podstawowa jednostka — destylacja atmosferyczna (CDU) — rozdziela każdą baryłkę ropy na frakcje:

| Frakcja | Udział bazowy (z 1 bbl ropy) | Produkt |
|---|---|---|
| **Benzyna surowa** (nafta lekka) | 0,22 bbl | Po hydrooczyszczaniu → **benzyna silnikowa** |
| **Nafta** | 0,10 bbl | Po hydrooczyszczaniu → **nafta lotnicza (Jet A-1)** |
| **Diesel surowy** (olej napędowy surowy) | 0,28 bbl | Po hydrooczyszczaniu → **olej napędowy (ON)** |
| **Mazut** (pozostałość ciężka) | 0,35 bbl | Produkt ciężki — sprzedawany jako paliwo żeglugowe / przemysłowe |
| **Straty procesowe** | 0,05 bbl | Gazy, woda, zanieczyszczenia — bezpowrotnie tracone |

Suma: 0,22 + 0,10 + 0,28 + 0,35 + 0,05 = 1,00 bbl.

**Wpływ jednostek technologicznych na udziały (skumulowany, po CDU):**

| Konfiguracja | Benzyna | Nafta lotnicza | Diesel | Mazut | Straty |
|---|---|---|---|---|---|
| Tylko CDU | 22% | 10% | 28% | 35% | 5% |
| CDU + HDS | 22% | 10% | 28% | 35% | 5% |
| CDU + HDS + Reforming | **32%** | 10% | 28% | **25%** | 5% |
| CDU + HDS + FCC | 30% | 10% | **40%** | **15%** | 5% |
| CDU + HDS + FCC + Hydrokraking | 30% | **25%** | **40%** | **0%** | 5% |
| CDU + HDS + FCC + Alkilacja | 30%* | 10% | 40% | 15% | 5% |

\* Z czego 5 p.p. to benzyna premium (+20% ceny).

**Zasada 100%:** udziały zawsze sumują się do 100% (ze stratami). Jednostki PRZESUWAJĄ udziały, nie dodają nowej masy.

### 31.3.2. Czas przerobu (model delta-time)

Rafineria pracuje ciągle — rozliczenie metodą Δt (§23):

`przerobione_bbl = min(przepustowość_nominalna × f_kondycji × f_obłożenia × Δt_h, dostępna_ropa_w_magazynie)`

- `przepustowość_nominalna` = 200/500/1200 bbl/h (klasa I/II/III)
- `f_kondycji` = clamp(0.10, 1.0, kondycja_rafinerii / 100) — przy <20% kondycji spada do 0.10
- `f_obłożenia` = 1.0 (pełna obsada) / 0.70 (brak do 30% obsady) / 0.40 (brak >30% obsady) / 0.0 (brak 100% — rafineria stoi)

**Ograniczenie plateau (§23.4):** gdy magazyn ropy się wyczerpie, rafineria przechodzi w `idle` — OPEX jałowy = 40% stawki nominalnej, zużycie jałowe = 0,2 × bazowe. Ponowny rozruch przy `idle > 24h` = rzut `restart_incident` (mikro/mały).

### 31.3.3. Koszty przerobu (OPEX rafineryjny)

| Składnik | Stawka (PLN/bbl przerobionej ropy) — klasa II | Uwagi |
|---|---|---|
| **Energia i media** | 25 | Prąd, woda chłodząca, para technologiczna |
| **Katalizatory i chemikalia** | 15 | Zużycie katalizatorów HDS/FCC, rozpuszczalniki |
| **Utrzymanie ruchu** | 20 | Bieżące naprawy, smary, części zamienne |
| **Personel produkcyjny** | 18 | Płace operatorów i inżynierów (naliczane przez fazę 5 FTS, tu pokazane jako koszt jednostkowy) |
| **Razem OPEX** | **78 PLN/bbl** | Klasa I: ×0,75 (59 PLN/bbl); klasa III: ×1,15 (90 PLN/bbl) |

Koszty naliczane proporcjonalnie do faktycznie przerobionego wolumenu (faza 5 sweepa, §23.3). Przy rafinerii `idle`: OPEX jałowy = 40% × stawka nominalna × Δt_h (samo utrzymanie gotowości).

### 31.3.4. Jakość produktów

Zamiast skomplikowanych liczb oktanowych — system **klas jakości**:

| Klasa jakości | Warunek | Mnożnik ceny sprzedaży |
|---|---|---|
| **Surowa** (unprocessed) | Tylko CDU, brak HDS | ×0,75 (produkt wymaga domieszki u odbiorcy) |
| **Standardowa** | CDU + HDS | ×1,00 |
| **Premium** | CDU + HDS + Alkilacja (dla benzyny) / Hydrokraking (dla nafty i diesla) | Benzyna ×1,20; Nafta ×1,15; Diesel ×1,10 |

Jakość jest cechą **partii produktu** w magazynie produktowym — gracz może mieć jednocześnie benzynę standardową i premium jako osobne pozycje magazynowe.

### 31.3.5. Magazyny produktowe

Osobne zbiorniki na każdy produkt — NIE mieszają się z magazynem ropy (§9, magazyn główny `PLN_STORAGE`).

| Produkt | Pojemność bazowa (bbl) — klasa II | Rozbudowa (+bbl / +PLN) | Max poziom |
|---|---|---|---|
| **Benzyna** | 15 000 | +5 000 za 500 000 PLN | 3 |
| **Nafta lotnicza** | 10 000 | +3 000 za 400 000 PLN | 3 |
| **Olej napędowy** | 20 000 | +7 000 za 600 000 PLN | 3 |
| **Mazut** | 25 000 | +10 000 za 350 000 PLN | 3 |

Rozbudowa zbiornika: czas 12h, nie wstrzymuje pracy rafinerii. Zbiornik pełny = kaskada plateau (§23.4): produkcja danej frakcji przechodzi w `paused_storage`, mazut nie ma gdzie iść → rafineria redukuje przepustowość o udział mazutu (czyli o 35% przy CDU).

### 31.3.6. Zużycie instalacji i przeglądy

`zużycie_rafinerii = bazowe_zużycie × f_obciążenia × mnożnik_zaniechania_konserwacji × Δt_h`

| Parametr | Wartość (klasa II) |
|---|---|
| `bazowe_zużycie` | 0,03% / h pracy |
| `f_obciążenia` | 1.0 (standard) / 1.4 (powyżej 90% przepustowości przez >6h) |
| `mnożnik_zaniechania` | 1.0 (przegląd <72h temu) / 1.20 (72–144h) / 1.50 (>144h) |

**Przegląd planowy:** co 96h, koszt 150 000 PLN, czas 6h. Podczas przeglądu rafineria pracuje na 80% przepustowości (nie zatrzymuje się całkowicie — przegląd rotacyjny jednostek). Spóźnienie >24h = ryzyko awarii ×1,5.

**Naprawa:** przywraca kondycję; zadanie TTS (§11) — wymaga inżyniera chemika + operatora. Czas: 4–24h zależnie od ubytku kondycji. Koszt: 50 000 PLN + 5 000 PLN za każdy p.p. kondycji do odtworzenia.

### 31.3.7. Incydenty rafineryjne

Ryzyko naliczane per jednostka technologiczna, w fazie 7 sweepa (§23.3):

`ryzyko_jednostki = bazowe_ryzyko_jednostki × (2 − kondycja_rafinerii/100) × mnożnik_zaniechania × mnożnik_BHP × mnożnik_strajku`

| Klasa incydentu | Bazowe ryzyko/h (klasa II) | Skutek |
|---|---|---|
| **Mikro** — usterka czujnika | 0,15% | Spadek kondycji −2%, nie wstrzymuje pracy |
| **Mały** — wyciek chemiczny | 0,08% | Przepustowość −20% na 4h, kara środowiskowa 50 000 PLN |
| **Średni** — awaria jednostki | 0,04% | Jednostka wyłączona na 12h, koszt naprawy 200 000 PLN |
| **Duży** — pożar instalacji | 0,015% | Rafineria WSTRZYMANA na 24h, kondycja −15%, kara `company_credibility` −5, koszt gaszenia 500 000 PLN |
| **Katastrofa** — eksplozja / skażenie | 0,003% | Rafineria `uszkodzona` na 72h, kondycja −40%, kara `company_credibility` −15, kara środowiskowa 2 000 000 PLN, utrata 30% produktów w zbiornikach, ryzyko eskalacji na sąsiednie jednostki (20%/h do ugaszenia) |

**Mnożniki BHP:** inwestycje w filtr i procedury — analogicznie do §17.4:
- `bhp_rafineryjny` poziom 1–5: redukcja ryzyka o 10% na poziom
- `procedure_integrity` (0–100): `(2 − integrity/100)` — zużyte procedury ×2 ryzyko
- `budget_bhp_multiplier` z §10: low 1.5 / standard 1.0 / high 0.7

**Skutki środowiskowe:** incydenty `duży` i `katastrofa` generują wpis w rejestrze środowiskowym — inspekcja środowiskowa (§31.4) może nałożyć dodatkowe kary.

---

## 31.4. Środowisko i Zezwolenia

### 31.4.1. Pozwolenie emisyjne (III stopnia)

Od momentu uruchomienia rafinerii gracz ma obowiązek posiadania **pozwolenia emisyjnego**:

| Parametr | Wartość startowa |
|---|---|
| Koszt wydania | 1 500 000 PLN |
| Czas rozpatrzenia | 24 h |
| Ważność | 180 dni |
| Opłata odnowieniowa | 300 000 PLN |
| `company_credibility` min. | 50 |
| Dodatkowy warunek | Brak aktywnej kary środowiskowej powyżej 100 000 PLN |

Brak ważnego pozwolenia = rafineria przechodzi w stan `shutdown_permit` (produkcja 0, OPEX jałowy biegnie). Po 7 dniach bez pozwolenia = kara administracyjna 500 000 PLN + `company_credibility` −8 + obowiązkowy postój do uzyskania pozwolenia (dodatkowe 24h od wydania).

### 31.4.2. Emisje, filtry i kontrole

**Emisje** to ukryty wskaźnik `emission_score` (0–100) rosnący w trakcie pracy rafinerii:

`przyrost_emisji = bazowy_przyrost × (1 − filtr_poziom × 0,15) × Δt_h`

- `bazowy_przyrost` = 0,5 pkt/h (klasa II)
- `filtr_poziom` = 0–5 (inwestycje w filtry i odzysk siarki)

| Poziom filtra | Koszt instalacji (PLN) | Redukcja emisji |
|---|---|---|
| 0 (brak) | 0 | 0% |
| 1 — Podstawowy | 500 000 | 15% |
| 2 — Ulepszony | 1 200 000 | 30% |
| 3 — Zaawansowany | 2 500 000 | 45% |
| 4 — Wysokosprawny | 5 000 000 | 60% |
| 5 — Zamknięty obieg | 10 000 000 | 75% |

**Kontrola inspekcji środowiskowej:** szansa na kontrolę = `emission_score × 0,01%` na godzinę. Wynik kontroli:

| `emission_score` przy kontroli | Skutek |
|---|---|
| < 30 | Brak zastrzeżeń |
| 30–60 | Upomnienie — wymóg poprawy w 30 dni (emisja musi spaść poniżej 30). Niespełnienie = kara 200 000 PLN |
| 60–80 | Mandat 400 000 PLN + `company_credibility` −4 + przymusowa instalacja filtra poziomu ≥2 w 14 dni |
| > 80 | Mandat 1 000 000 PLN + `company_credibility` −8 + WSTRZYMANIE rafinerii do czasu instalacji filtra poziomu ≥3 |

**Decay emisji:** −2 pkt/24h przy rafinerii `idle` lub `shutdown`. Brak naturalnego spadku podczas pracy.

### 31.4.3. Czarny rynek a rafineria

„Lewa" produkcja — rafineria bez pozwolenia emisyjnego:

- Rafineria pracuje normalnie (produkcja, sprzedaż), ale z flagą `illegal_operation`.
- `black_market_score` +1 za każde 24h nielegalnej pracy (§2.3 — decay −1/24h, więc saldo ±0 przy stałej nielegalnej pracy, ale +2 za „wejście w tryb" przy pierwszym ticku).
- Ryzyko kontroli skarbowej rośnie (wg formuły §2.3, `score > 30`).
- Przy kontroli: mandat 2 000 000 PLN, rafineria opieczętowana na 72h (`shutdown_authority`), `company_credibility` −12.
- Gracz NIE może sprzedawać produktów z nielegalnej rafinerii przez oficjalny rynek — tylko czarny rynek produktowy (cena ×0,6, rozliczenie w `PLN_CASH`).

---

## 31.5. Kadry Rafineryjne

### 31.5.1. Nowe stanowiska

| Stanowisko | Rola | Min. obsada (klasa I/II/III) | Oczekiwana pensja (PLN/miesiąc) | Sub-skille |
|---|---|---|---|---|
| **Operator rafinerii** | Obsługa paneli sterowniczych, monitoring parametrów, reagowanie na alarmy | 3 / 8 / 18 | 18 000 | `skill_refining` |
| **Inżynier chemik** | Nadzór technologiczny, optymalizacja procesu, badania laboratoryjne, dozór katalizatorów | 1 / 2 / 4 | 28 000 | `skill_refining`, `skill_safety` |
| **Laborant jakości** | Badania próbek produktów, certyfikacja partii, kalibracja analizatorów | 0 / 1 / 2 | 15 000 | `skill_analysis` |
| **Dyrektor produkcji (Zarząd)** | Nowy fotel w zarządzie — zarządza rafinerią, negocjuje kontrakty produktowe, optymalizuje marżę | 0 / 1 / 1 | 40 000 | `skill_refining`, `skill_negotiation` |

### 31.5.2. Nowy sub-skill: `skill_refining`

| Poziom | Efekt |
|---|---|
| 1–3 | Przepustowość rafinerii +2% na poziom operatora (średnia zespołu) |
| 4–6 | Zużycie instalacji −3% na poziom inżyniera chemika |
| 7–9 | Straty procesowe −0,3 p.p. na poziom (max −0,9 p.p. — zejście do 4,1% strat) |
| 10 | Jakość produktów +1 klasa (surowa→standardowa, standardowa→premium) — wymaga `skill_refining = 10` u DYREKTORA PRODUKCJI |

Szkolenia: kursy I–III (jak §6), koszt 15 000 / 40 000 / 100 000 PLN. Czas: 24h / 48h / 72h. Pass rate: 70% / 60% / 50%.

### 31.5.3. Strajk w rafinerii (§5)

**Próg:** średnie morale działu rafineryjnego < 35 pkt → spór → groźba → strajk.

| Skutek strajku rafineryjnego | Wartość |
|---|---|
| Przepustowość rafinerii | Spadek o 40% + 40% × udział_strajkujących (zakres 40–80%) |
| Jakość produktów | Spadek o 1 klasę (premium→standardowa→surowa) przy udziale > 50% |
| Ryzyko incydentu | ×2,0 (spójne z §5.1) |
| Kontrakty produktowe | Kara za niedostarczenie naliczana normalnie (§13) — chyba że gracz ma klauzulę Siły Wyższej (§21) |
| Produkty w zbiornikach | NIE psują się — produkty ropopochodne są trwałe w perspektywie dni/tygodni |

**Negocjacje strajkowe:** jak §5.2 — baza dialogów rozszerzona o argumenty rafineryjne („Pracujemy w oparach benzenu", „Konkurencyjna rafineria płaci 30% więcej").

### 31.5.4. Dyrektor produkcji — nowy fotel zarządu

Fotel `board_refining`. Rekrutacja jak pozostali dyrektorzy (§3.1). Bez dyrektora produkcji:

- Rafineria pracuje na 85% przepustowości
- Nie można podpisywać kontraktów produktowych z odbiorcami NPC
- Sprzedaż spot produktów możliwa (limit 20 000 PLN/h — spójne z trybem awaryjnym §10)
- Nie można montować nowych jednostek technologicznych

---

## 31.6. Sprzedaż i Rynek Produktowy

### 31.6.1. Cztery rynki produktowe

Każdy produkt ma własny rynek spot z dynamiczną ceną:

| Produkt | Cena bazowa (PLN/bbl) | Źródło popytu NPC | Cena bazowa jako % ceny ropy |
|---|---|---|---|
| **Benzyna silnikowa** | 520 | Sieci stacji paliw, floty samochodowe | 208% |
| **Nafta lotnicza (Jet A-1)** | 580 | Linie lotnicze, lotniska wojskowe, cargo | 232% |
| **Olej napędowy (ON)** | 480 | Firmy transportowe, koleje, rolnictwo, przemysł | 192% |
| **Mazut** | 200 | Elektrownie, cementownie, żegluga morska (bunkier) | 80% |

Ceny bazowe `game_balance` — do kalibracji.

### 31.6.2. Kształtowanie cen produktowych

Cena każdego produktu = cena_bazowa × mnożnik_popytu × mnożnik_sezonowy × mnożnik_wydarzeń × slippage_produktowy.

| Czynnik | Mechanizm |
|---|---|
| **Mnożnik popytu NPC** | `1 + 0,003 × (popyt_bazowy − podaż_rynku_w_oknie) / popyt_bazowy` — ograniczony do [0,75; 1,40]. Okno = 24h kroczące. Podaż to łączny wolumen sprzedany przez wszystkich graczy. |
| **Sezonowość** | Zima: diesel +8%, mazut +5%. Lato: benzyna +10%, nafta lotnicza +12%. Przesilenia trwają 7 dni — ogłaszane jako wydarzenia (§25). |
| **Wydarzenia światowe (§25)** | Embargo na ropę → ceny produktów +15%. Kryzys gospodarczy → −10%. |
| **Slippage produktowy** | Jak §19.3: skumulowany wolumen sprzedaży w oknie 60 min obniża cenę o 2–8% proporcjonalnie do `Σwolumen / pojemność_okna`. Regeneracja: 10% spadku co 10 min. |

### 31.6.3. Sprzedaż produktów

Trzy tryby — identyczne jak dla ropy (§12):

1. **Sprzedaż spot (natychmiastowa):** produkt z magazynu produktowego → `PLN_BANK` po bieżącej cenie rynkowej. Limit: brak limitu transakcji, ograniczeniem tylko chłonność rynku (slippage). Wpis FTS: `reason_code=REF_PRODUCT_SPOT`, konto `SYS_MARKET_MAKER`.

2. **Zlecenia limit:** blokada partii produktu w magazynie produktowym, cena minimalna, automatyczna realizacja. Nowa tabela `refinery_market_offers` (osobna od `market_offers` dla ropy — produkty to inny rynek). Te same limity slotów: 3 standard / 15 VIP (§22.6).

3. **Kontrakty produktowe** — patrz 31.6.4.

### 31.6.4. Kontrakty produktowe (nowy typ kontraktów §13)

Rozszerzenie systemu kontraktów systemowych (§13.1) o dostawy produktów rafineryjnych:

| Parametr | Wartość |
|---|---|
| **Odbiorcy NPC** | Narodowy przewoźnik lotniczy, sieć stacji „Petro24", armator „BalticCargo", elektrownia „WattPol" — każdy ma własny popyt, okres i preferencje cenowe |
| **Tryby cenowe** | `fixed` (stała cena), `market_multiplier` (mnożnik ceny spot), `market_plus_bonus` (cena spot + bonus) |
| **Kaucja** | 15% wartości kontraktu (wyższa niż dla ropy — produkty to wyższa wartość dodana) |
| **Kara za niedostarczenie transzy** | 8% wartości transzy + `contract_reputation` −8 pkt |
| **Przepadek kaucji** | Po trzech niewykonanych transzach z rzędu (spójne z §13.1) |
| **Czas trwania** | 7 / 14 / 30 dni |

Nowa tabela: `refinery_system_contracts` z polami `product_type` (ENUM: `gasoline`, `jet_fuel`, `diesel`, `mazut`), `product_bbl_total`, `product_bbl_per_tranche`, `tranche_interval_h`, `price_mode`, `price_value`, `security_deposit_grosze`.

Kontrakty produktowe między graczami (B2B) — analogicznie do §13.2: stany `draft → published → accepted → in_delivery → delivered → settled`. Eskrowane w PLN (wartość produktów). Transport produktów — cysterny produktowe (§31.6.6) lub odbiór własny kupującego.

### 31.6.5. Limit chłonności rynku (anty-drukarnia)

Każdy rynek produktowy ma **twardą dzienną chłonność NPC** — maksymalny wolumen, jaki odbiorcy NPC kupią po cenie nie niższej niż 70% ceny bazowej:

| Produkt | Chłonność dzienna NPC (bbl) — łączna dla wszystkich graczy |
|---|---|
| Benzyna | 80 000 |
| Nafta lotnicza | 40 000 |
| Olej napędowy | 100 000 |
| Mazut | 60 000 |

Powyżej chłonności cena spada do podłogi 60% ceny bazowej (slippage maksymalny) — rynek nasycony. Gracze mogą sprzedawać między sobą (B2B) po dowolnej cenie — ograniczeniem jest tylko popyt innych graczy.

Chłonność podlega kalibracji symulatorem i może być modyfikowana przez wydarzenia światowe (§25) — np. „Zimowy kryzys energetyczny" podwaja chłonność mazutu na 14 dni.

Wartości startowe `game_balance` — przy 5 graczach z rafineriami klasy II to ok. 6 dni ciągłej produkcji, zanim rynek się nasyci.

### 31.6.6. Transport produktów

Produkty NIE płyną tymi samymi cysternami co ropa — to osobna flota:

| Pojazd | Pojemność (bbl) | Koszt kursu (PLN) | Czas bazowy | Zakup (PLN) | Uwagi |
|---|---|---|---|---|---|
| **Cysterna paliwowa mała** | 400 | 900 | 15 min | 120 000 | Produkty lekkie (benzyna, nafta) |
| **Cysterna paliwowa duża** | 800 | 1 600 | 20 min | 220 000 | Wszystkie produkty |
| **Cysterna chemiczna** | 300 | 1 200 | 18 min | 180 000 | Produkty ciężkie (diesel, mazut) — podgrzewana |
| **Wagon cysterna (kolej)** | 2 000 | 3 000 | 30 min | 500 000 | Wymaga bocznicy kolejowej przy rafinerii (inwestycja 1 500 000 PLN, czas 48h) |

**Trasy transportu produktowego:**
- Rafineria → magazyn produktowy (zawsze w ramach kompleksu rafineryjnego — zbiorniki są fizycznie przy rafinerii, więc transfer wewnętrzny: zero kosztu, przepustowość = rurociąg wewnętrzny 500 bbl/h)
- Magazyn produktowy → odbiorca NPC (sprzedaż spot / realizacja kontraktu): koszt transportu POKRYWA KUPUJĄCY NPC — cena produktu na rynku to cena loco rafineria. Gracz widzi „cenę netto" po odjęciu szacunkowego transportu.
- Magazyn produktowy → magazyn innego gracza (kontrakt B2B): transport organizuje i opłaca SPRZEDAJĄCY (chyba że strony ustalą inaczej w warunkach kontraktu). Wpis FTS: `reason_code=REF_PRODUCT_TRANSPORT`.

**Rurociągi produktowe:** w przeciwieństwie do ropy, produkty NIE są transportowane rurociągami dalekosiężnymi w MVP. Rurociąg wewnętrzny rafineria→zbiorniki jest automatyczny. W przyszłości (wersja 2.0): rurociągi produktowe jako osobny rodzaj infrastruktury.

**Terminal morski:** rafineria przy porcie może eksportować produkty tankowcami produktowymi — osobna flota, większe wolumeny, niższy koszt jednostkowy. Do rozważenia w wersji 2.0.

---

## 31.7. Ekonomia Rafineryjna w Zero-Wipe

### 31.7.1. Marża rafineryjna — źródło i stabilność

`marża_jednostkowa = średnia_cena_produktów − cena_ropy − OPEX_przerobu`

Przykład dla rafinerii klasy II z HDS (benzyna: 520 PLN, diesel: 480 PLN, nafta: 580 PLN, mazut: 200 PLN; średnia ważona = ~415 PLN/bbl produktów przy standardowych udziałach):

| Pozycja | PLN/bbl |
|---|---|
| Przychód ze sprzedaży produktów (ważony) | 415 |
| Koszt ropy (cena spot 250 PLN/bbl) | −250 |
| OPEX przerobu | −78 |
| **Marża brutto** | **87 PLN/bbl (34,8% od kosztu ropy)** |

Dlaczego marża jest stabilna:

1. **Sprzężenie ceny ropy z produktami:** ceny produktów bazują na cenie ropy + marża rafineryjna. Gdy ropa drożeje, produkty też — mnożniki względem ropy są stałe.
2. **Chłonność rynku:** gdy gracze masowo produkują benzynę, jej cena spada (slippage), marża się kurczy → mniej opłaca się produkować → produkcja spada → cena wraca. Samoregulacja.
3. **Koszty stałe:** OPEX, pensje, przeglądy — niezależne od ceny ropy. Przy niskich marżach rafineria może zejść poniżej progu rentowności.

### 31.7.2. Zwrot z inwestycji (ROI)

Dla rafinerii klasy II (koszt 15 000 000 PLN, przepustowość 500 bbl/h, marża 87 PLN/bbl):

- **Pełne obciążenie:** 500 bbl/h × 87 PLN = 43 500 PLN/h = 1 044 000 PLN/doba
- **Zwrot inwestycji:** 15 000 000 / 1 044 000 ≈ **14,4 dnia**

Przy typowym obciążeniu 70% (logistyka, awarie, postoje): ~**20 dni**.

To założenia do kalibracji — symulator (§23.10) zweryfikuje rzeczywisty ROI w warunkach rynkowych z wieloma graczami.

### 31.7.3. Rafineria w majątku netto

Wycena rafinerii do podatku majątkowego (§19.1) i tieru firmy (§22.6):

`wartość_rafinerii = koszt_budowy × (0,3 + 0,7 × kondycja%) + Σ(koszt_jednostek × (0,2 + 0,8 × kondycja_jednostki%)) + wartość_produktów_w_zbiornikach`

Produkty w zbiornikach wyceniane po 85% bieżącej ceny spot.

### 31.7.4. Mechanizmy antymonopolowe

Problem: jeden gracz buduje 3 rafinerie i zalewa rynek tanimi produktami → niszczy marże innym.

**Zabezpieczenia:**

1. **Limit rafinerii na gracza:** max **2 rafinerie** łącznie (niezależnie od klasy). Próba budowy trzeciej = odrzucona na etapie pozwolenia środowiskowego.
2. **Chłonność rynku:** nasycenie popytu NPC → ceny spadają → marża spada → duże rafinerie tracą rentowność szybciej niż małe (wyższy OPEX stały).
3. **Kadry:** ograniczona pula wykwalifikowanych inżynierów chemików na rynku pracy — trudno obsadzić wiele rafinerii jednocześnie.
4. **Regiony:** rafineria wymaga konkretnego typu regionu — pula działek jest ograniczona.
5. **Kartele (§26):** limit 40% produkcji serwera w jednym kartelu dotyczy też produkcji rafineryjnej (liczonej jako bbl produktów).

---

## 31.8. Granica Monetyzacji (§22.6)

### Co MOŻNA kupić za GBBL w kontekście rafinerii:

| Usługa | Koszt (GBBL) | Opis |
|---|---|---|
| **Przyspieszony projekt techniczny** | 15 | Skraca czas projektowania z 4h do 15 min |
| **Szybkie pozwolenie środowiskowe** | 30 | Rozpatrzenie w 2h zamiast 24h (NIE omija wymogów — tylko czas) |
| **Ekspresowy montaż jednostki** | 25 | Czas montażu −50% (np. kraking z 48h na 24h) |
| **Złota skórka rafinerii** | 40 | Wizualny prestiż — kompleks ze złotymi akcentami widoczny na mapie |
| **VIP — automatyczne zlecenia produktowe** | (w ramach VIP §22.6) | Automatyczne wystawianie zleceń limit na produkty przy zadanych progach cenowych |
| **Rozbudowa zbiornika „ekspres"** | 15 | Czas rozbudowy −50% |

### Czego NIE można kupić:

- **Przewag produkcyjnych:** przepustowości, wydajności frakcji, jakości produktów
- **Ominięcia katastrof:** Prywatna Straż (§22.3) działa tylko na AKTYWNY incydent, nie zapobiega
- **Ominięcia pozwoleń:** pozwolenia środowiskowe zawsze wymagane — GBBL nie zwalnia z prawa
- **Dodatkowych rafinerii ponad limit 2:** limit twardy, nie do obejścia

---

## 31.9. Panel Administratora

### 31.9.1. Zasoby w Filament v3

Nowa grupa w panelu: **„RAFINERIA"** z zasobami:

1. **Rafinerie (`RefineryResource`):**
   - Lista wszystkich rafinerii: właściciel, klasa, region, kondycja, przepustowość aktualna, stan, data budowy
   - Widok szczegółowy: wszystkie jednostki technologiczne z kondycją, zbiorniki produktowe z zapełnieniem, dziennik produkcji
   - Akcje GM: wymuszenie incydentu, korekta `emission_score`, wyłączenie/włączenie rafinerii

2. **Kontrakty produktowe (`RefineryContractResource`):**
   - Lista aktywnych kontraktów systemowych i B2B na produkty
   - Podgląd transz, statusów dostaw, kar

3. **Rynek produktowy (`ProductMarketResource`):**
   - Aktualne ceny 4 produktów, historia notowań
   - Wykresy popytu/podaży
   - Ręczna modyfikacja chłonności NPC i ceny bazowej (z zasadą czterech oczu §18.3)

4. **Pozwolenia emisyjne (`EmissionPermitResource`):**
   - Lista pozwoleń, statusy, terminy ważności
   - Historia kontroli środowiskowych

### 31.9.2. Uprawnienia wg macierzy §18.3

| Akcja | admin | gm_support | gm_readonly |
|---|---|---|---|
| Podgląd rafinerii, rynku, pozwoleń | ✅ | ✅ | ✅ |
| Wymuszenie incydentu rafineryjnego | ✅ | ✅ | ❌ |
| Modyfikacja `emission_score` | ✅ | ✅ | ❌ |
| Wyłączenie/włączenie rafinerii | ✅ | ✅ | ❌ |
| Ręczna zmiana cen produktowych i chłonności | ✅ (cztery oczy) | ❌ | ❌ |
| Rozstrzyganie sporów kontraktów produktowych | ✅ | ✅ | ❌ |
| Korekta sald zbiorników produktowych (`GM_ADJUST`) | ✅ | ❌ | ❌ |

### 31.9.3. Monitoring (telemetria §29.3)

- Średnia marża rafineryjna per gracz i globalnie
- Procent wykorzystania przepustowości
- Czas do nasycenia rynku produktowego (przy bieżącej produkcji)
- Najczęściej kupowane usługi GBBL w kontekście rafinerii

---

## 31.10. Technika

### 31.10.1. Maszyny stanów

**Rafineria:**

```
projekt → pozwolenie → fundamenty → konstrukcja → instalacje → rozruch → aktywny
                                                                           ├── idle (brak ropy)
                                                                           ├── degraded (kondycja <50%)
                                                                           ├── shutdown_permit (brak pozwolenia)
                                                                           ├── shutdown_authority (decyzja inspekcji)
                                                                           ├── shutdown_strike (strajk)
                                                                           ├── damaged (incydent duży/katastrofa)
                                                                           └── seized (egzekucja §17.2)
aktywny/degraded → konserwacja → aktywny
damaged → naprawa (TTS) → aktywny
dowolny → wstrzymany (urlop §19.2) → wznowienie
```

**Jednostka technologiczna:**

```
montowana → aktywna → wyłączona (przez gracza) → aktywna
                   → uszkodzona (awaria) → naprawa → aktywna
                   → zmodernizowana (upgrade) → aktywna
```

**Partia produktu w zbiorniku:**

```
produkowana (rafineria) → zmagazynowana → zarezerwowana (zlecenie limit / kontrakt) → sprzedana
                                        → utracona (incydent)
                                        → przeterminowana (NIE w MVP — produkty trwałe)
```

**Kontrakt produktowy:**

```
draft (systemowy / B2B) → published (B2B) → accepted → in_delivery → delivered → settled
                                                                                ├── partially_settled (transze)
                                                                                └── breached (kara)
draft/published → cancelled
published → expired (7 dni)
in_delivery → disputed → resolved | refunded
```

### 31.10.2. Wzory delta-time

**Przepustowość skuteczna (faza 2 sweepa — po logistyce, przed rynkiem):**

`Q_raf = min(przepustowość_nom × f_kond × f_obsady × f_strajku × f_budżetu, dostępna_ropa_w_magazynie / Δt_h)`

**Produkcja frakcji (w tej samej fazie):**

`produkcja_frakcji_X = Q_raf × udział_X × Δt_h`

gdzie `udział_X` pochodzi z macierzy jednostek (31.3.1), zsumowany do 100% ze stratami.

**Koszt przerobu (faza 5, księgowanie FTS):**

`koszt = Q_raf × stawka_OPEX × Δt_h`

**Zużycie instalacji (faza 7):**

`Δkondycja = −bazowe_zużycie × f_obciążenia × mnożnik_zaniechania × Δt_h`

**Ryzyko incydentów (faza 7, rzut zagregowany §23.6):**

`p_inc = 1 − (1 − bazowe_ryzyko_jednostki × mnożnik_kondycji × mnożnik_BHP × mnożnik_strajku)^(Δt_h)`

**Emisje (faza 7):**

`Δemission_score = bazowy_przyrost × (1 − filtr_poziom × 0,15) × Δt_h`

### 31.10.3. Klucze ziarna losowego (§23.6)

`seed = HMAC(SERVER_SECRET, "refinery:{refinery_id}:{due_slot}")`

Nie `entity_id` ogólnie, tylko `refinery_id` — osobna przestrzeń ziaren dla rafinerii.

### 31.10.4. Wpisy FTS (nowe kody przyczyn)

| Kod | Opis | Debet | Kredyt |
|---|---|---|---|
| `REFINERY_BUILD` | Opłata za etap budowy rafinerii | `PLN_BANK` gracza | `SYS_TREASURY` |
| `REFINERY_UNIT_INSTALL` | Montaż jednostki technologicznej | `PLN_BANK` gracza | `SYS_TREASURY` |
| `REFINERY_OPEX` | Koszty przerobu (energia, katalizatory) | `PLN_BANK` gracza | `SYS_TREASURY` |
| `REFINERY_MAINTENANCE` | Przegląd planowy rafinerii | `PLN_BANK` gracza | `SYS_TREASURY` |
| `REFINERY_REPAIR` | Naprawa rafinerii/jednostki | `PLN_BANK` gracza | `SYS_TREASURY` |
| `REF_PRODUCT_SPOT` | Sprzedaż spot produktu | `SYS_MARKET_MAKER` | `PLN_BANK` gracza |
| `REF_PRODUCT_CONTRACT` | Realizacja transzy kontraktu produktowego | `SYS_MARKET_MAKER` | `PLN_BANK` gracza |
| `REF_PRODUCT_B2B` | Realizacja kontraktu B2B na produkt | `PLN_ESCROW` kupującego | `PLN_BANK` sprzedającego |
| `REF_PRODUCT_TRANSPORT` | Opłata za transport produktu | `PLN_BANK` gracza | `SYS_TREASURY` |
| `REF_ENV_PERMIT` | Opłata za pozwolenie emisyjne | `PLN_BANK` gracza | `SYS_TREASURY` |
| `REF_ENV_FILTER` | Instalacja filtra środowiskowego | `PLN_BANK` gracza | `SYS_TREASURY` |
| `REF_ENV_FINE` | Kara środowiskowa | `PLN_BANK` gracza | `SYS_TREASURY` |
| `REF_STORAGE_EXPAND` | Rozbudowa zbiornika produktowego | `PLN_BANK` gracza | `SYS_TREASURY` |
| `REF_ILLEGAL_INCOME` | Przychód z nielegalnej sprzedaży produktów | `SYS_TREASURY` | `PLN_CASH` gracza |

### 31.10.5. Tabele danych (kluczowe)

```sql
-- Rafinerie
refineries: id, player_id, region_id, class (I/II/III), throughput_nominal, condition,
            status, emission_score, filter_level, bhp_level, procedure_integrity,
            project_completed_at, built_at, last_calc_at, created_at, updated_at

-- Jednostki technologiczne
refinery_units: id, refinery_id, unit_type (cdu/hds/reforming/fcc/hydrocracking/alkylation),
                condition, status, installed_at, last_maintenance_at, created_at

-- Etapy budowy
refinery_build_stages: id, refinery_id, stage, started_at, eta, status, cost_grosze,
                       created_at

-- Magazyny produktowe
refinery_storage_tanks: id, refinery_id, product_type, capacity_bbl, used_bbl,
                        upgrade_level, created_at

-- Partie produktów
refinery_product_batches: id, refinery_id, tank_id, product_type, quality_class,
                          volume_bbl, reserved_bbl, status, produced_at

-- Kontrakty produktowe systemowe
refinery_system_contracts: id, player_id, product_type, total_bbl, bbl_per_tranche,
                           tranche_interval_h, price_mode, price_value_grosze,
                           security_deposit_grosze, status, signed_at, expires_at

-- Kontrakty produktowe B2B
refinery_b2b_offers: id, seller_id, buyer_id, product_type, volume_bbl, quality_class,
                     price_per_bbl_grosze, delivery_deadline_at, security_deposit_grosze,
                     status, created_at

-- Kontrole środowiskowe
refinery_env_inspections: id, refinery_id, inspection_at, emission_score_at_inspection,
                          result, fine_grosze, required_action, deadline_at, created_at

-- Dzienne chłonności rynków produktowych
product_market_daily_capacity: product_type, date, base_demand_bbl, sold_bbl, current_price,
                                created_at, updated_at

-- Zlecenia limit na produkty
refinery_market_offers: id, player_id, product_type, volume_bbl, limit_price_grosze,
                        status, created_at, filled_at
```

### 31.10.6. Integracja z fazami rozliczeń (§23.3)

Rafineria rozliczana jest w **fazie 2.5a** — między przetwarzaniem hubów (2.5) a odcinkiem 2 (2.6). Kolejność:

1. Faza 1 — Wydobycie (bez zmian)
2. Faza 2.1–2.4 — Logistyka surowcowa (bez zmian)
3. **Faza 2.5a — Pobór ropy do rafinerii** (z magazynu głównego → rafineria; plateau: magazyn pusty → rafineria `idle`)
4. **Faza 2.5b — Przerób rafineryjny** (ropa → produkty wg udziałów, zużycie instalacji, emisje, rzut incydentów, produkty → zbiorniki produktowe; plateau: zbiornik pełny → `paused_storage` dla danej frakcji)
5. Faza 2.6–2.8 — Logistyka wyjściowa i magazyn (bez zmian)
6. Faza 3 — Rynek (rozszerzona o rynki produktowe: matching zleceń limit, sprzedaż spot, realizacja transz kontraktów — wykonywana WYŁĄCZNIE przez sweepera)
7. Faza 5 — Finanse (rozszerzona o OPEX rafineryjny, kary środowiskowe, przychody ze sprzedaży produktów)

**Uzasadnienie umiejscowienia:** rafineria pobiera ropę po tym, jak trafiła do magazynu (po fazie 2 logistyki), a przed sprzedażą produktów (faza 3). Produkty to nowe encje — muszą być dostępne dla rynku w tej samej fazie sweepa.

### 31.10.7. Testy odbiorcze (rozszerzenie listy §23.10)

Oznaczone jako `REF-01` do `REF-12`:

| ID | Test |
|---|---|
| REF-01 | Równoległe Lazy+Sweeper na rafinerii — jedno rozliczenie (CAS) |
| REF-02 | Plateau: magazyn ropy pusty → rafineria `idle`, OPEX jałowy 40% |
| REF-03 | Plateau: zbiornik benzyny pełny → frakcja `paused_storage`, mazut idzie dalej |
| REF-04 | Katastrofa rafineryjna: utrata 30% produktów w zbiornikach — bilans bbl co do 0,0001 |
| REF-05 | Kontrakt produktowy: 3 niewykonane transze → przepadek kaucji; 1 transza → tylko kara 8% |
| REF-06 | Agregacja ryzyka incydentów rafineryjnych n=168h vs golden-master |
| REF-07 | Chłonność rynku: sprzedaż powyżej `daily_capacity` → cena przy podłodze 60% |
| REF-08 | Wyłączenie jednostki FCC → udziały wracają do CDU+HDS, mazut +20 p.p. |
| REF-09 | Strajk rafinerii u=0,60: przepustowość −64%, jakość −1 klasa |
| REF-10 | Nielegalna praca: `black_market_score` +1/24h, sprzedaż tylko czarny rynek ×0,6 |
| REF-11 | Limit 2 rafinerii na gracza — próba budowy trzeciej odrzucona na etapie pozwolenia |
| REF-12 | Inspekcja środowiskowa przy `emission_score=75`: mandat + przymusowa instalacja filtra |

---

## 31.11. Etapowanie i Ryzyka

### 31.11.1. MVP (wersja 1.0)

| Element | Zakres |
|---|---|
| **Klasy rafinerii** | Tylko I (Rozdzielnia) i II (Rafineria średnia) |
| **Jednostki technologiczne** | CDU + HDS + FCC |
| **Produkty** | Benzyna, olej napędowy, mazut (bez nafty lotniczej) |
| **Sprzedaż** | Tylko spot i zlecenia limit |
| **Kontrakty produktowe** | Tylko systemowe (NPC), bez B2B produktowego |
| **Transport produktów** | Tylko cysterny małe i duże |
| **Środowisko** | Pozwolenie emisyjne + kontrole, bez filtrów (poziom 0) |
| **Kadry** | Operator + inżynier chemik, bez dyrektora produkcji i laboranta |
| **Incydenty** | Mikro, mały, duży (bez katastrofy) |

### 31.11.2. Kolejne wersje

| Wersja | Elementy |
|---|---|
| **1.1** | Nafta lotnicza jako produkt, katastrofy rafineryjne, filtry środowiskowe |
| **1.2** | Kontrakty B2B na produkty, dyrektor produkcji, laborant jakości |
| **1.3** | Klasa III (Kompleks petrochemiczny), Hydrokraking, Alkilacja |
| **2.0** | Terminal morski dla produktów, rurociągi produktowe, wagony cysterny |
| **2.1** | Czarny rynek produktowy („lewa" benzyna), sabotaż rafinerii między graczami |

### 31.11.3. Ryzyka implementacji

| Ryzyko | Prawdopodobieństwo | Wpływ | Środek zaradczy |
|---|---|---|---|
| **Niestabilna marża** — trudno zbalansować ceny produktów vs ropa | Wysokie | Duży | Headless symulator z 1000 przebiegów przed wydaniem; parametry w `game_balance` do szybkiej korekty |
| **Wyścig o chłonność rynku** — pierwsi gracze z rafineriami zgarniają cały popyt NPC | Średnie | Średni | Chłonność na start ustawiona wysoko (2× szacowanego zapotrzebowania); stopniowe dostrajanie |
| **Złożoność testów** — wiele nowych encji, stanów, plateau | Wysokie | Średni | Testy REF-01..REF-12 jako brama wyjścia; golden-master formuł |
| **Wydajność sweepa** — dodatkowe encje w fazie 2.5 i 3 | Średnie | Mały | Batch'owanie rafinerii per gracz; budżet sweepa powiększony o 15% |
| **Konflikt z istniejącą logistyką** — pobór ropy z magazynu vs sprzedaż spot ropy | Niskie | Duży | Blokada `SELECT ... FOR UPDATE` na magazynie: w jednej transakcji albo sprzedaż ropy spot, albo pobór do rafinerii — nie oba naraz |

### 31.11.4. Otwarte pytania do właściciela gry

1. **Czy nafta lotnicza w MVP?** To dodatkowy produkt, zbiornik, rynek i kontrakty — ok. 25% więcej pracy. Można dorzucić w 1.1.
2. **Czy gracz może mieć rafinerię przy porcie (region `coastal`)?** Jeśli tak, terminal morski produktów mógłby być w MVP jako prostsza alternatywa dla cystern — jedna trasa tankowcem zamiast wielu kursów cystern.
3. **Czy limit 2 rafinerii na gracza jest OK?** Przy mniejszej populacji serwera można dać 1. Przy większej — nawet 3. To prosty przełącznik w `game_balance`.
4. **Czy produkty mają być sprzedawane wyłącznie z rafinerii (loco), czy z dowolnego magazynu?** Obecny projekt zakłada loco rafineria — transport odbiorcy. Alternatywa: gracz dostarcza produkt do odbiorcy własnym transportem i dostaje wyższą cenę.
5. **Czy czarny rynek produktowy ma być w MVP?** Dodaje ryzyko i narrację, ale to kolejne 10–15% zakresu.
6. **Czy rafineria ma mieć własny „bufor ropy" (zbiornik surowca) przy rafinerii, czy ciągnie bezpośrednio z magazynu głównego?** Obecny projekt zakłada ciągnięcie z magazynu głównego przez wewnętrzny rurociąg — prostsze. Osobny bufor to dodatkowa encja i logistyka (trzeba dowozić ropę do rafinerii cysternami).
7. **Czy mazut może być spalany lokalnie jako paliwo dla rafinerii (obniża OPEX)?** Ciekawy mechanizm: zamiast sprzedawać mazut po niskiej cenie, gracz zużywa go na potrzeby własnej rafinerii, obniżając OPEX energii o 30–50%. Do rozważenia w wersji 1.2.
8. **Czy inspekcje środowiskowe mają być losowe, czy zapowiadane?** Obecny projekt: losowe z `emission_score × 0,01%/h`. Można dodać zapowiadane inspekcje co 30 dni (gracz ma czas na przygotowanie).

---

## Podsumowanie — decyzje do podjęcia przez właściciela

1. [ ] Zakres MVP: klasa III, nafta lotnicza, katastrofy, filtry — co wchodzi, co nie?
2. [ ] Limit rafinerii na gracza: 1, 2, czy więcej?
3. [ ] Model transportu produktów: loco rafineria czy dostawa do odbiorcy przez gracza?
4. [ ] Terminal morski i wagony cysterny w MVP czy później?
5. [ ] Bufor ropy przy rafinerii czy bezpośredni pobór z magazynu głównego?
6. [ ] Spalanie mazutu na własne potrzeby energetyczne (obniżka OPEX) — tak/nie?
7. [ ] Czarny rynek produktowy w MVP?
8. [ ] Zapowiadane inspekcje środowiskowe (oprócz losowych)?
9. [ ] Parametry startowe — pełna tabela `game_balance` do akceptacji przed implementacją
10. [ ] Czy dyrektor produkcji ma być w zarządzie (osobny fotel), czy jako kierownik średniego szczebla?
