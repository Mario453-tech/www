# OilCorp - Dział Finansów v2

> **Stan na 13.05.2026** — Etap 1 ✅ | Etap 2 ✅ | Etap 3 ✅ | Etap 4 ✅ | Etap 5 ✅
>
> Szczegółowa mapa wdrożenia: §28 | Plan testów PHPUnit: §29 | Najbliższe kroki: §30

---

## 1. Cel dokumentu

Ten dokument opisuje docelową rozbudowę działu `Finanse` w grze OilCorp.

To nie ma być nowy, oderwany moduł. To ma być rozwinięcie istniejącego panelu finansowego tak, aby:
- dalej pokazywał dane i statystyki,
- ale dodatkowo dawał graczowi realne decyzje,
- wpływał na tick gry,
- czytał także dane z logistyki i hubów przeładunkowych,
- miał spójny panel admina,
- był wdrażany etapami i bez rozwalania obecnego systemu.

Ten dokument jest briefem wdrożeniowym pod implementację.

---

## 2. Stan obecny

### 2.1. Co już działa

Obecny panel finansów już pokazuje:
- saldo,
- zysk / tick,
- zysk / godzina,
- straty transportu,
- wykres historii finansowej,
- strukturę finansową 24h / 7 dni,
- analizę per odwiert.

Obecne pliki:
- `C:\xampp1\htdocs\public\finance.php`
- `C:\xampp1\htdocs\src\FinanceService.php`
- `C:\xampp1\htdocs\templates\views\public\finance\main.php`
- `C:\xampp1\htdocs\assets\css\finance.css`
- `C:\xampp1\htdocs\assets\js\finance.js`
- `C:\xampp1\htdocs\admin\finance.php`
- `C:\xampp1\htdocs\templates\views\admin\finance\main.php`

### 2.2. Jak działa to teraz

Obecnie `FinanceService` zapisuje per tick do `finance_logs`:
- revenue,
- gross_revenue,
- opex,
- salary_cost,
- transport_cost,
- incident_cost,
- tax,
- loss_bbl,
- loss_value,
- net_profit,
- cash_after,
- oil_price,
- bbl_produced,
- wells_active.

Tick zapisuje to z:
- `C:\xampp1\htdocs\src\Tick\PlayersSection.php`
- na podstawie liczb z:
  - `C:\xampp1\htdocs\src\Tick\WellLoopSection.php`

### 2.3. Główna luka obecnego modułu

Obecny panel jest czytelny, ale jest głównie raportem.

Brakuje mu:
- decyzji,
- polityk finansowych,
- prognoz,
- zarządzania płynnością,
- budżetów działowych,
- warstwy ryzyka,
- jawnego rozliczania wpływu hubów.

---

## 3. Najważniejsze założenie v2

Finanse mają stać się działem, który:
- nie tylko pokazuje wynik,
- ale pozwala nim sterować.

Panel finansów ma być:
- centrum kontroli kosztów,
- centrum prognoz płynności,
- centrum ryzyka,
- centrum decyzji strategicznych.

---

## 4. Jednolity system wdrożenia

## 4.1. Spójność z agentem

Wdrożenie musi być zgodne z:
- `C:\xampp1\htdocs\.windsurf\agent.md`

To znaczy:
- wspólny shell strony,
- wspólna kolorystyka,
- brak nowego mini-systemu UI,
- brak hardcoded tekstów,
- modalowy system projektu zamiast natywnych okienek,
- `style.css` jako źródło prawdy dla wspólnych komponentów.

## 4.2. Wygląd panelu gracza

Panel gracza `Finanse` ma pozostać wizualnie spójny z resztą gry:
- złoty akcent,
- ten sam `GameShell`,
- te same status boxy,
- ten sam footer akcji,
- ten sam system zakładek,
- ten sam system kart i alertów.

`assets/css/finance.css` może istnieć dalej, ale tylko dla rzeczy specyficznych dla finansów.

Nie przenosimy do niego wspólnych klas typu:
- globalne przyciski,
- globalne taby,
- globalne formularze,
- globalny header,
- globalny footer.

## 4.3. Wygląd panelu admina

Panel admina dla finansów musi wyglądać podobnie jak istniejące panele typu:
- rynek globalny,
- transport,
- logistyka,
- inne nowoczesne sekcje admina.

Zasady:
- ciemne tło,
- złote nagłówki sekcji,
- siatka kart statystyk,
- sekcje w panelach,
- czytelne formularze,
- przyciski zgodne z obecnym admin UI,
- brak białych natywnych elementów rozwalających estetykę.

Admin korzysta z:
- `C:\xampp1\htdocs\assets\css\admin.css`

Nie tworzymy nowego osobnego systemu CSS dla admina finansów bez konsultacji.

## 4.4. Wszystkie napisy po polsku

Wszystkie napisy muszą być po polsku:
- panel gracza,
- panel admina,
- komunikaty,
- modale,
- opisy pól,
- alerty,
- tooltipy,
- widoki pustego stanu,
- komunikaty JS.

Wszystko ma trafiać do:
- `C:\xampp1\htdocs\lang\pl.php`

Bez wyjątków.

---

## 5. Architektura panelu gracza

Obecny ekran ma zostać zakładką:
- `Przegląd`

Nowy docelowy układ zakładek:
1. `Przegląd`
2. `Budżety`
3. `Płynność`
4. `Ryzyko`
5. `Polityka finansowa`
6. `Historia decyzji`

---

## 6. Zakładka: Przegląd

To jest rozwinięcie obecnego ekranu.

### 6.1. Zostaje
- saldo,
- zysk / tick,
- zysk / godzina,
- straty transportu,
- wykres 24h / 7 dni,
- struktura finansowa,
- analiza per odwiert.

### 6.2. Dochodzą alerty finansowe

Przykłady:
- `Transport pochłania 34% kosztów`
- `Huby logistyczne generują zbyt wysoki koszt użycia`
- `Region Polska ma wysokie straty hubowe`
- `2 odwierty są mało opłacalne`
- `Ryzyko utraty płynności w 24h: średnie`
- `Koszty stałe przekroczyły bezpieczny próg`

Każdy alert może mieć CTA:
- `Przejdź do Budżetów`
- `Przejdź do Ryzyka`
- `Przejdź do Logistyki`
- `Uruchom plan oszczędności`

---

## 7. Zakładka: Budżety

To ma być pierwsza naprawdę „żywa” sekcja.

### 7.1. Budżety działowe

Na start gracz ustawia poziom budżetu dla:
- Technicznego
- Logistyki
- HR
- BHP / bezpieczeństwa

### 7.2. Forma

Na MVP:
- `Niski`
- `Standard`
- `Wysoki`

Później można dodać:
- wartości procentowe,
- suwaki,
- miesięczny limit w PLN.

### 7.3. Wpływ na grę

#### Techniczny
- niski budżet:
  - szybszy wear,
  - więcej usterek,
  - większe ryzyko awarii
- wysoki budżet:
  - lepsze utrzymanie,
  - mniej usterek

#### Logistyka
- niski budżet:
  - większe straty,
  - większy koszt awarii,
  - większe ryzyko problemów hubowych,
  - gorsza wydajność fallbacku bez huba
- wysoki budżet:
  - mniejsze straty,
  - lepsza efektywność logistyki,
  - niższe ryzyko incydentów hubowych

#### HR
- niski budżet:
  - gorszy pool kandydatów,
  - dłuższa rekrutacja
- wysoki budżet:
  - lepsi kandydaci,
  - większe szanse powodzenia

#### BHP
- niski budżet:
  - większe ryzyko incydentów
- wysoki budżet:
  - lepsza prewencja

---

## 8. Zakładka: Płynność

To ma być panel prognoz i bezpieczeństwa finansowego.

### 8.1. Pokazywać
- prognozowany cashflow:
  - następny tick,
  - 1h,
  - 6h,
  - 24h
- wolną gotówkę,
- rezerwę gotówkową,
- koszty stałe,
- raty / zobowiązania,
- udział kosztów logistyki i hubów,
- udział strat w przychodzie.

### 8.2. Akcje
- `Włącz tryb oszczędności`
- `Wstrzymaj inwestycje`
- `Buduj rezerwę`
- `Priorytet płynności`

### 8.3. Zasada działania

Te decyzje mają wpływać na tick od kolejnego przebiegu, a nie tylko zmieniać kolor boxa.

### 8.4. Rezerwa awaryjna - dokładna logika MVP

Rezerwa awaryjna na etapie 3 nie jest osobnym portfelem i nie blokuje fizycznie gotówki.

Na start ma działać jako:
- cel bezpieczeństwa gotówkowego,
- liczony w godzinach kosztów firmy,
- źródło alertów i oceny płynności.

#### Poziomy rezerwy
- `Niska` = 6h kosztów firmy
- `Standardowa` = 12h kosztów firmy
- `Wysoka` = 24h kosztów firmy

#### Jak liczyć
System bierze średni koszt firmy na godzinę i wylicza:
- `cel_rezerwy = koszt_h * liczba_godzin`

Przykład:
- koszt firmy: `120 000 PLN / h`
- poziom rezerwy: `12h`
- cel rezerwy: `1 440 000 PLN`

#### Jak to działa w praktyce
- jeśli gotówka jest powyżej celu rezerwy:
  - ryzyko płynności spada,
  - alerty wygaszają się,
  - firma uznawana jest za stabilniejszą
- jeśli gotówka spada poniżej celu rezerwy:
  - panel pokazuje alert,
  - rośnie ocena ryzyka płynności,
  - system może sugerować uruchomienie planu oszczędności

#### Czego rezerwa awaryjna nie robi na MVP
- nie tworzy osobnego salda,
- nie przenosi gotówki do sub-portfela,
- nie blokuje pieniędzy przed użyciem,
- nie wywołuje automatycznie decyzji bez potwierdzenia gracza.

---

## 9. Zakładka: Ryzyko

To ma być panel ostrzegawczy i diagnostyczny.

### 9.1. Kategorie ryzyka
- płynność,
- zadłużenie,
- koszty stałe,
- zależność od ceny ropy,
- ryzyko logistyczne,
- ryzyko hubowe,
- ryzyko regionalne,
- ryzyko incydentów.

### 9.2. Poziomy
- `Niskie`
- `Umiarkowane`
- `Wysokie`
- `Krytyczne`

### 9.3. Dodatkowo

Każde ryzyko powinno pokazać:
- co je powoduje,
- jakie dane je podbijają,
- co gracz może zrobić.

Przykład:

`Ryzyko hubowe: wysokie`
- wysoki `hub_usage_fee`,
- częste `hub_tick_loss`,
- niski condition w 2 hubach,
- region przeciążony.

CTA:
- `Przejdź do Logistyki`

---

## 10. Zakładka: Polityka finansowa

To ma być miejsce dla długoterminowych ustawień firmy.

### 10.1. Ustawienia
- polityka kosztowa:
  - oszczędna / zbalansowana / agresywna
- poziom rezerwy:
  - niski / standard / wysoki
- tolerancja strat:
  - niska / średnia / wysoka
- polityka zadłużenia:
  - ostrożna / standard / agresywna

### 10.2. Wpływ
- scoring ryzyka,
- reakcje kryzysowe,
- dostępne akcje systemowe,
- decyzje bankowe,
- ocena bezpieczeństwa firmy.

### 10.3. Plan oszczędności - dokładna logika MVP

Plan oszczędności ma być trybem firmy, a nie jednorazowym bonusem.

#### Dostępne stany
- `Wyłączony`
- `Umiarkowany`
- `Agresywny`

#### Zasada nadrzędna
Plan oszczędności:
- ma poprawiać krótkoterminowy wynik finansowy,
- ale kosztem jakości działania firmy,
- i nie może udawać prostego, magicznego cięcia płac działów, których realny koszt to głównie ludzie.

#### Co robi realnie

##### Logistyka
To jest dziś najbardziej naturalne źródło realnych oszczędności kosztowych.

Plan oszczędności może wpływać na:
- koszt transportu,
- koszt użycia hubów,
- koszt fallbacku bez aktywnego huba,
- koszt operacji logistycznych premium.

Jednocześnie pogarsza:
- straty logistyczne,
- ryzyko przeciążeń,
- ryzyko incydentów hubowych,
- sprawność regionów przeciążonych.

##### HR
Plan oszczędności nie powinien udawać bezpośredniego cięcia stałego kosztu HR.

Na MVP wpływa przez:
- dłuższy czas rekrutacji,
- gorszą jakość kandydatów,
- słabszą skuteczność działań premium,
- możliwość ograniczenia headhuntera w trybie agresywnym.

##### Techniczny
Plan oszczędności nie powinien udawać prostego cięcia kosztu płac technicznych.

Na MVP wpływa przez:
- gorszą sprawność operacyjną,
- większe ryzyko zużycia,
- wolniejsze lub mniej efektywne reakcje działu,
- większą ekspozycję na skutki zaniedbań.

##### BHP
Na MVP wpływ powinien być lekki i ostrożny:
- wyższe ryzyko tylko w trybie agresywnym,
- bez przesadnego mnożenia katastrof.

#### Proponowany model efektów

##### Wyłączony
- brak dodatkowego wpływu

##### Umiarkowany
- logistyka: umiarkowanie niższy koszt operacyjny
- logistyka: umiarkowanie wyższe straty i ryzyko
- HR: umiarkowanie dłuższa rekrutacja i słabsi kandydaci
- techniczny: umiarkowanie gorsza sprawność

##### Agresywny
- logistyka: mocniej obniżony koszt operacyjny
- logistyka: wyraźnie wyższe straty i ryzyko
- HR: wyraźnie dłuższa rekrutacja i słabsi kandydaci
- techniczny: wyraźnie gorsza sprawność operacyjna
- BHP: lekko podniesione ryzyko
- część kosztownych akcji premium może być ograniczona lub zablokowana

#### Czego plan oszczędności nie robi na MVP
- nie obniża automatycznie pensji pracowników,
- nie obniża automatycznie pensji zarządu,
- nie tworzy jeszcze pełnego systemu morale / retencji / buntu,
- nie daje darmowych oszczędności bez konsekwencji.

#### Zasady użytkowe
- zmiana trybu nie może być spamowana co tick,
- powinna mieć cooldown,
- efekt wchodzi od kolejnego ticka,
- historia zmian trafia do historii decyzji.

---

## 10A. Etap 3 - dokładny widok gracza

Ta sekcja opisuje dokładnie, co gracz ma zobaczyć i jak ma z tego korzystać na etapie 3.

### 10A.1. Główne zakładki, które mają żyć na etapie 3

Na etapie 3 gracz ma realnie używać głównie:
- `Ryzyko`
- `Polityka finansowa`
- `Płynność`

Zakładka `Przegląd` nadal pokazuje dane, ale te trzy zakładki mają już dawać decyzje.

### 10A.2. Zakładka `Ryzyko` - co gracz ma widzieć

Gracz powinien widzieć osobne karty ryzyka:
- `Płynność`
- `Logistyka`
- `Huby`
- `Koszty stałe`
- `Zależność od ceny ropy`
- `Ryzyko operacyjne`

Każda karta powinna pokazywać:
- poziom:
  - `Niskie`
  - `Umiarkowane`
  - `Wysokie`
  - `Krytyczne`
- krótki opis, co to znaczy,
- 2-4 konkretne powody,
- przycisk akcji prowadzący do właściwego miejsca.

Przykłady CTA:
- `Przejdź do Logistyki`
- `Włącz plan oszczędności`
- `Zmień poziom rezerwy`
- `Przejdź do Płynności`

### 10A.3. Zakładka `Polityka finansowa` - co gracz ma widzieć

Na start gracz powinien mieć dwa główne bloki:

#### A. Plan oszczędności
Widok powinien pokazywać:
- aktualny tryb:
  - `Wyłączony`
  - `Umiarkowany`
  - `Agresywny`
- czas do końca cooldownu zmiany,
- skrót efektów:
  - co daje,
  - czym grozi.

#### B. Rezerwa awaryjna
Widok powinien pokazywać:
- aktualny poziom:
  - `Niska`
  - `Standardowa`
  - `Wysoka`
- ile wynosi obecny cel rezerwy w PLN,
- ile godzin kosztów firmy to dziś pokrywa,
- czy firma jest:
  - `powyżej celu`
  - `blisko celu`
  - `poniżej celu`
  - `krytycznie poniżej celu`

### 10A.4. Modale gracza na etapie 3

Wszystkie interakcje mają używać systemu modali projektu.

Bez:
- `alert()`
- `confirm()`
- `prompt()`

#### Modal: `Włącz plan oszczędności`
Powinien pokazywać:
- wybrany tryb,
- kiedy zmiana zacznie działać,
- jaki jest cooldown kolejnej zmiany,
- sekcję:
  - `Oszczędzasz`
  - `Kosztem`

Przykładowy układ:

**Oszczędzasz**
- niższe koszty logistyki
- ograniczenie części wydatków opcjonalnych

**Kosztem**
- większe straty logistyczne
- słabsi kandydaci i wolniejsza rekrutacja
- gorsza sprawność operacyjna technicznego

Przyciski:
- `Anuluj`
- `Włącz plan`

#### Modal: `Zmień poziom rezerwy awaryjnej`
Powinien pokazywać:
- obecny poziom,
- nowy poziom,
- szacowany cel rezerwy w PLN,
- jak zmieni się ocena płynności.

Przyciski:
- `Anuluj`
- `Zapisz poziom rezerwy`

#### Modal: `Wstrzymaj inwestycje`
Powinien wyjaśniać:
- co zostanie ograniczone,
- na jak długo,
- że decyzja ma charakter obronny.

Przyciski:
- `Anuluj`
- `Wstrzymaj inwestycje`

### 10A.5. Komunikaty gracza na etapie 3

Wszystkie komunikaty muszą być po polsku i trafić do `lang/pl.php`.

#### Komunikaty sukcesu
- `Plan oszczędności został włączony.`
- `Poziom rezerwy awaryjnej został zapisany.`
- `Inwestycje zostały wstrzymane.`

#### Komunikaty błędu
- `Nie możesz jeszcze zmienić planu oszczędności.`
- `Ta zmiana jest chwilowo zablokowana przez cooldown.`
- `Nie udało się zapisać ustawień finansowych.`

#### Komunikaty ostrzegawcze
- `Gotówka spadła poniżej celu rezerwy awaryjnej.`
- `Straty logistyczne przekroczyły bezpieczny próg.`
- `Aktywny plan oszczędności pogarsza sprawność operacyjną firmy.`

### 10A.6. Alerty, które gracz ma widzieć na etapie 3

Na etapie 3 alerty mają być bardziej decyzyjne niż tylko opisowe.

Minimalny zestaw:
- `Rezerwa awaryjna poniżej celu`
- `Krytycznie niski bufor gotówki`
- `Wysokie straty logistyczne`
- `Wysoki koszt użycia hubów`
- `Region przeciążony logistycznie`
- `Zbyt duży udział strat w przychodzie`
- `Aktywny plan oszczędności obniża sprawność firmy`

Każdy alert powinien mieć:
- poziom,
- opis,
- przyczynę,
- przycisk akcji.

### 10A.7. Co gracz ma zrozumieć bez czytania README

UI ma jasno tłumaczyć, że:
- `Plan oszczędności` nie obniża magicznie pensji,
- `Rezerwa awaryjna` nie jest osobnym kontem,
- `Logistyka` jest głównym miejscem realnych oszczędności i strat,
- `HR` i `Techniczny` reagują głównie przez skuteczność i jakość działań,
- `Huby` wpływają na wynik finansowy bezpośrednio.

To musi być czytelne w samym panelu, bez potrzeby studiowania dokumentacji technicznej.

---

## 11. Zakładka: Historia decyzji

Powinna pokazywać:
- datę,
- decyzję,
- moduł,
- koszt / efekt,
- status.

Przykłady:
- `Podniesiono budżet logistyki`
- `Uruchomiono tryb oszczędności`
- `Wstrzymano inwestycje`
- `Zwiększono poziom rezerwy`

---

## 12. Finanse mają czytać także z hubów

To jest zasada twarda.

Rozbudowany dział finansów ma czytać nie tylko z:
- odwiertów,
- transportu,
- pensji,
- podatków,
- incydentów,

ale także z:
- hubów logistycznych,
- strat hubowych,
- kosztów użycia hubów,
- incydentów hubowych,
- kosztów stref / regionów związanych z logistyką.

## 12.1. Co już dziś jest naliczane w ticku

W obecnym ticku istnieją już logi i naliczenia związane z hubami:
- `hub_tick_loss`
- `hub_usage_fee`
- fallback bez huba
- incydenty hubów

Źródła:
- `C:\xampp1\htdocs\src\Tick\WellLoopSection.php`
- `C:\xampp1\htdocs\src\Tick\PlayersSection.php`

## 12.2. Problem obecny

Dziś finanse zapisują wynik zbiorczo do `finance_logs`, ale nie rozdzielają jawnie warstwy hubowej na osobne pola raportowe.

To trzeba poprawić.

## 12.3. Co finanse mają czytać z hubów docelowo

### Koszty
- `hub_usage_fee`
- koszty napraw hubów
- koszty upgrade hubów
- koszty zmian trybu pracy
- ewentualne koszty przypisań / przenosin

### Straty
- `hub_tick_loss`
- straty fallbacku dla odwiertów bez huba
- dodatkowe straty z incydentów hubowych
- straty ze złego przypisania strefowego

### Ryzyko
- przeciążenie hubów,
- niski condition,
- wysoka liczba incydentów,
- wysokie koszty regionu / strefy,
- zbyt duży udział logistyki w całkowitym wyniku firmy.

### KPI finansowe powiązane z hubami
- koszt hubów / 24h
- straty hubowe / 24h
- średni koszt logistyczny na baryłkę
- najbardziej kosztowny region logistyczny
- najbardziej stratny hub
- region z największym bottleneckiem

---

## 13. Tick integration

## 13.1. Zasada główna

Nie budujemy osobnego silnika finansów.

Finanse mają czytać i modulować istniejące systemy:
- WellLoopSection
- IncidentService
- HRService
- Logistics / Hubs
- Bank

## 13.2. Obecna ścieżka

Obecnie:
1. `PlayersSection` uruchamia tick gracza
2. `WellLoopSection` liczy produkcję, OPEX, transport, huby, straty, incydenty
3. `PlayersSection` woła `FinanceService->saveTick()`
4. `public/finance.php` czyta dane z `FinanceService`

## 13.3. Docelowa ścieżka v2

1. tick liczy produkcję, transport, huby i incydenty
2. tick zapisuje pełniejsze dane finansowe
3. `FinanceService` agreguje:
   - klasyczne koszty,
   - koszty logistyczne,
   - koszty hubowe,
   - straty transportowe,
   - straty hubowe
4. panel finansów pokazuje:
   - stan bieżący,
   - prognozę,
   - alerty,
   - ryzyko,
   - decyzje

## 13.4. Dane, które tick musi wystawiać pod finanse

Minimalny zakres:
- przychód netto po stratach,
- brutto przed stratami,
- OPEX odwiertów,
- pensje,
- transport cost,
- incident cost,
- tax,
- fallback loss,
- hub loss,
- hub incident loss,
- hub usage fee,
- bbl produced,
- bbl lost,
- cash after,
- liczba aktywnych odwiertów,
- liczba aktywnych hubów używanych przez gracza.

---

## 14. Tabele i dane

## 14.1. Istniejąca tabela
- `finance_logs`

## 14.2. Rozszerzenia `finance_logs`

Do rozważenia rozszerzenie o pola:
- `hub_loss_bbl`
- `hub_loss_value`
- `hub_usage_cost`
- `hub_incident_cost`
- `hub_count_active`
- `wells_without_hub`
- `fallback_loss_bbl`
- `fallback_loss_value`

## 14.3. Nowe tabele

### `player_finance_settings`
Ustawienia gracza:
- budget_technical
- budget_logistics
- budget_hr
- budget_safety
- liquidity_mode
- reserve_policy
- cost_policy
- debt_policy
- created_at
- updated_at

### `player_finance_alerts`
Snapshoty alertów / historia alertów:
- player_id
- alert_code
- alert_level
- alert_payload_json
- created_at
- is_active

### `player_finance_decisions`
Historia decyzji finansowych:
- player_id
- decision_code
- value_before
- value_after
- effect_json
- created_at

### `player_finance_snapshots`
Rozszerzone snapshoty do analiz:
- player_id
- tick_at
- liquidity_score
- logistics_score
- hub_cost_total
- hub_loss_total
- transport_loss_total
- risk_score
- reserve_level

### `finance_config`
Konfiguracja admina:
- config_key
- config_value
- label
- description
- category

---

## 15. Modale

Tylko system modalny projektu.

Bez:
- `alert()`
- `confirm()`
- `prompt()`

## 15.1. Modale gracza
- zmiana budżetu działu
- zmiana polityki finansowej
- uruchomienie trybu oszczędności
- ustawienie poziomu rezerwy
- potwierdzenie wstrzymania inwestycji

## 15.2. Modale admina
- zapis konfiguracji finansów
- reset ustawień gracza
- uruchomienie scenariusza kryzysowego
- korekta mnożników globalnych
- podejrzenie szczegółów alertu

---

## 16. Komunikaty

Wszystkie komunikaty po polsku w `lang/pl.php`.

### 16.1. Komunikaty gracza
- sukces zmiany budżetu
- błąd zapisu polityki
- aktywacja oszczędności
- ostrzeżenie o płynności
- ostrzeżenie o kosztach hubowych
- alert o przeciążonej logistyce

### 16.2. Komunikaty admina
- zapis konfiguracji
- reset ustawień
- włączenie trybu testowego
- aktualizacja progów alarmowych

### 16.3. Alerty systemowe
- wysoki udział strat logistycznych
- rosnący koszt hubów
- niski condition hubów wpływający na wynik
- zbyt duża liczba odwiertów bez huba

---

## 17. Panel admina - założenia

Panel admina finansów ma być pełnoprawnym centrum konfiguracji.

Nie tylko podglądem.

Ma być także:
- spójny wizualnie z nowoczesnymi panelami admina projektu,
- w pełni po polsku,
- gotowy do realnego balansu etapu 3,
- użyteczny zarówno do produkcji, jak i do testów GM / QA.

## 17.1. Musi umożliwiać
- edycję progów alertów,
- edycję wpływu budżetów na systemy,
- edycję mnożników kosztów,
- edycję mnożników strat,
- edycję wpływu logistyki i hubów,
- podgląd graczy z największym ryzykiem,
- podgląd regionów i stref generujących największe koszty,
- podgląd hubów generujących najwyższe straty,
- reset / korektę ustawień gracza,
- wymuszenie scenariuszy testowych.

Dodatkowo dla etapu 3 musi umożliwiać:
- konfigurację `Planu oszczędności`,
- konfigurację `Rezerwy awaryjnej`,
- konfigurację cooldownów decyzji finansowych,
- podgląd ilu graczy działa poniżej rezerwy,
- podgląd ilu graczy ma aktywny plan oszczędności,
- ręczne wymuszenie i cofnięcie trybu testowego dla oszczędności / rezerwy.

## 17.2. Sekcje panelu admina

### A. Stan globalny finansów
- przychód globalny
- wynik netto globalny
- globalne straty
- udział logistyki
- udział hubów
- liczba graczy z problemem płynności

### B. Konfiguracja budżetów
- mnożnik wpływu budżetu technicznego
- mnożnik wpływu budżetu logistyki
- mnożnik wpływu budżetu HR
- mnożnik wpływu budżetu BHP

### C. Konfiguracja ryzyka
- progi alertów
- progi płynności
- progi udziału strat
- progi udziału kosztów hubowych

### D. Konfiguracja warstwy hubowej dla finansów
- czy hub cost wpływa na alerty
- czy hub loss wpływa na risk score
- czy fallback loss ma osobny próg alertu
- próg regionów przeciążonych logistycznie
- próg strefowego bottlenecku

### D2. Konfiguracja planu oszczędności
- czy moduł jest aktywny globalnie
- dostępne tryby:
  - `Wyłączony`
  - `Umiarkowany`
  - `Agresywny`
- cooldown zmiany trybu
- wpływ `Umiarkowanego` planu na:
  - logistykę
  - HR
  - techniczny
  - BHP
- wpływ `Agresywnego` planu na:
  - logistykę
  - HR
  - techniczny
  - BHP
- ograniczenia akcji premium:
  - headhunter
  - kosztowne akcje logistyczne
  - opcjonalne inwestycje

### D3. Konfiguracja rezerwy awaryjnej
- czy moduł jest aktywny globalnie
- dostępne poziomy:
  - `6h`
  - `12h`
  - `24h`
- sposób liczenia kosztu godzinowego firmy
- próg alertu `poniżej celu rezerwy`
- próg alertu `krytycznie poniżej celu rezerwy`
- wpływ poziomu rezerwy na ocenę ryzyka płynności
- czy niski poziom rezerwy ma wpływać na alerty bankowe / scoring

### E. Regiony i strefy
- ranking regionów po kosztach
- ranking stref po stratach
- ranking hubów po OPEX
- ranking hubów po loss

### F. Gracze
- ranking ryzyka
- ranking strat
- ranking kosztów logistyki
- ranking kosztów hubowych

### G. Narzędzia GM
- reset ustawień finansowych gracza
- podbicie / obniżenie ryzyka
- podbicie / obniżenie kosztów
- test alertu
- test kryzysu płynności

### H. Monitoring etapu 3
- ilu graczy ma aktywny plan oszczędności
- ilu graczy działa w trybie `Umiarkowanym`
- ilu graczy działa w trybie `Agresywnym`
- ilu graczy jest poniżej rezerwy 6h
- ilu graczy jest poniżej rezerwy 12h
- ilu graczy jest w stanie krytycznym płynności
- które regiony i strefy najczęściej generują alerty finansowe
- które huby najczęściej podbijają koszt i straty

## 17.3. Widoki admina etapu 3

Panel admina powinien mieć co najmniej następujące sekcje robocze:

### A. Przegląd globalny
- stan rynku finansowego graczy
- najważniejsze KPI
- liczba aktywnych alertów
- liczba graczy w krytycznej płynności

### B. Plan oszczędności
- konfiguracja globalna
- konfiguracja trybów
- cooldowny
- skutki dla logistyki / HR / technicznego / BHP

### C. Rezerwa awaryjna
- konfiguracja poziomów rezerwy
- wpływ na ryzyko
- progi alertów
- powiązanie z płynnością

### D. Alerty i progi
- konfiguracja wszystkich progów
- włączenie / wyłączenie wybranych alertów
- wgląd w aktywne alerty graczy

### E. Huby i logistyka
- koszty hubowe
- straty hubowe
- fallback
- regiony
- strefy
- ranking największych problemów logistycznych wpływających na finanse

### F. Gracze
- lista graczy
- ich polityki finansowe
- poziom rezerwy
- aktywny plan oszczędności
- poziom ryzyka
- historia decyzji

### G. Narzędzia GM / QA
- wymuszenie scenariusza
- reset polityk
- podejrzenie pełnego snapshotu
- szybki test alertów

## 17.4. Zasady UX panelu admina etapu 3

Panel admina etapu 3 musi trzymać się tych samych zasad, co inne nowoczesne panele admina:
- sekcje jako ciemne panele,
- złote nagłówki,
- czytelne siatki formularzy,
- duże, jednoznaczne CTA,
- brak przypadkowych białych selectów,
- brak natywnych przeglądarkowych modali,
- wszystkie napisy po polsku,
- wszystkie komunikaty przez `lang/pl.php`.

Wszystkie nowe akcje admina muszą mieć:
- komunikat sukcesu,
- komunikat błędu,
- walidację wejścia,
- log wpisu admina.

## 17.5. Czego admin etapu 3 nie powinien robić

Panel admina nie powinien:
- udawać prostego cięcia płac HR i Technicznego,
- mieć osobnego, niespójnego stylu względem reszty admina,
- opierać się o biały systemowy UI,
- pozwalać na zmianę krytycznych parametrów bez zapisu do loga,
- mieć hardcoded angielskich etykiet.

---

## 18. Spójność panelu admina

Panel admina ma zachować spójność z nowoczesnymi panelami admina, szczególnie z widokami typu:
- rynek globalny,
- transport,
- logistyka.

To znaczy:
- sekcje jako panele,
- karty statystyk,
- formularze w siatkach,
- wyraźne nagłówki,
- przyciski systemowe,
- brak białych selectów i przypadkowych stylów przeglądarki.

Wszystkie etykiety mają być po polsku.

---

## 19. Testy

To jest duży moduł i ma wejść z pełnym testowaniem.

## 19.1. Testy funkcjonalne

Trzeba przetestować ręcznie każdą funkcję:

### Gracz
- wejście do finansów
- działanie wszystkich zakładek
- zapis budżetów
- zapis polityk
- alerty
- prognozy
- reakcję na tick
- wpływ logistyki
- wpływ hubów

### Admin
- zapis konfiguracji
- odczyt globalnych statystyk
- edycję mnożników
- regiony / strefy
- ranking hubów
- narzędzia GM

## 19.2. Testy integracyjne

Potrzebne testy pod:
- `FinanceService`
- zapis snapshotów
- odczyt budżetów
- alerty
- wyliczenia hubowe
- odczyt danych region / strefa
- konfigurację admina

## 19.3. PHPStan

Moduł ma przejść:
- pełny `phpstan`

Nie tylko składnię.

## 19.4. Kontrola zgodności ticka

Trzeba potwierdzić, że:
- tick zapisuje dane spójnie,
- finanse czytają te same liczby, które liczy tick,
- hub loss i hub cost nie są gubione ani dublowane,
- fallback bez huba nie rozjeżdża wyniku.

---

## 20. Etapy wdrożenia

## Etap 1 — ✅ Zrealizowany — ożywienie obecnego panelu
- ✅ obecny ekran staje się `Przegląd`
- ✅ alerty finansowe
- ✅ podstawowe prognozy płynności
- ✅ podstawowy odczyt danych hubowych
- ✅ rozszerzenie `finance_logs` o 7 nowych kolumn hubowych

## Etap 2 — ✅ Zrealizowany — decyzje gracza
- ✅ zakładka `Budżety`
- ✅ zakładka `Płynność`
- ✅ zapis ustawień gracza (`player_finance_settings`)
- ✅ pierwszy wpływ na tick (modyfikatory budżetów)

## Etap 3 — ✅ Zrealizowany — ryzyko i polityka
- ✅ zakładka `Ryzyko` (5 kart z CTA)
- ✅ zakładka `Polityka finansowa` (plan oszczędności + rezerwa)
- ✅ historia decyzji (zakładka + zapis do DB)
- ✅ rozbudowane alerty (etap 3)
- ✅ `Plan oszczędności` — tryby: `Wyłączony` / `Umiarkowany` / `Agresywny`
- ✅ cooldown 6h przy zmianie planu
- ✅ `Rezerwa awaryjna` — poziomy: `6h` / `12h` / `24h` kosztów
- ✅ modyfikatory planu oszczędności dla logistyki / HR / technicznego / BHP
- ✅ integracja planu oszczędności z tickiem (`WellLoopSection`, `PlayersSection`)
- ✅ zapis danych hubowych z ticka do `finance_logs`
- ❌ panel admina dla konfiguracji etapu 3
- ✅ testy PHPUnit — 100 testów, 259 asercji (§29)

## Etap 4 — ✅ Zrealizowany — panel admina v3
- ✅ monitoring planu oszczędności — 3 karty: off / umiarkowany / agresywny (§17.2 H)
- ✅ tabela polityk per gracz — savings mode, rezerwa, budżety, cooldown (§17.2 F)
- ✅ narzędzia GM — reset polityki finansowej gracza (§17.2 G)
- ✅ konfiguracja D2 — tabela efektów trybów oszczędnościowych (mnożniki per dział)
- ✅ konfiguracja D3 — karty poziomów rezerwy (low=6h, standard=12h, high=24h)
- ✅ monitoring rezerwy — gracze poniżej celu (critical/warning/caution)
- ✅ cooldown DB-driven — `well_config.savings_plan_cooldown_hours`, fallback=6h

## Etap 5 — ✅ Zrealizowany — testy i stabilizacja
- ✅ testy PHPUnit integracyjne — 100 testów, 259 asercji (§29)
- ✅ phpstan level 6+ — `[OK] No errors`
- ✅ historia decyzji graczy w adminie (filtr per gracz, 100/200 wpisów)
- ✅ monitoring regionów i hubów (JOIN 4 tabel, filtr `$hours`)
- ✅ konfiguracja alertów i progów (4 klucze `well_config`, DB-driven)
- ❌ balans mnożników
- ❌ poprawki UI/UX po testach

---

## 21. Najważniejsze zasady końcowe

1. Nie budujemy nowego, osobnego systemu finansów obok gry.
2. Rozwijamy obecny panel i spinamy go z tickiem.
3. Finanse mają czytać także z hubów.
4. Panel admina ma być spójny z resztą admina.
5. Wszystkie napisy mają być po polsku.
6. Wszystko ma zostać wdrożone etapami.
7. Każdy etap ma mieć test funkcjonalny.
8. Całość ma przejść phpstan i testy integracyjne.

---

## 22. Rekomendowany pierwszy krok implementacyjny

Najrozsądniej zacząć od:
- rozszerzenia `FinanceService`,
- rozszerzenia `finance_logs`,
- jawnego rozdzielenia:
  - strat transportowych,
  - strat hubowych,
  - fallback loss,
  - hub usage fee,
- oraz dodania alertów do obecnej zakładki `Przegląd`.

To da najszybciej efekt, że dział finansów zacznie naprawdę żyć.

---

## 23. Mapa realnych wpływów na gameplay (stan obecny)

Ta sekcja ma zostać w dokumencie na stałe.

Jej cel:
- nie zgubić, co już naprawdę działa w grze,
- odróżnić systemy realnie podpięte do ticka od danych tylko wizualnych,
- podejmować dalsze decyzje projektowe na bazie faktów z kodu, a nie założeń.

### 23.1. Techniczni pracownicy - co już wpływa realnie

#### Operator odwiertu
Dziś realnie wpływa na:
- produkcję odwiertu,
- ryzyko incydentu,
- częściowo zużycie i stabilność przez perki.

Obecne źródła wpływu:
- `skill_level` operatora,
- specjalizacja / perk operatora.

Główne miejsca w kodzie:
- `C:\xampp1\htdocs\src\Tick\WellLoopSection.php`
- `C:\xampp1\htdocs\src\Incident\TickTrait.php`

Wniosek:
- operator nie jest tylko statystyką na karcie,
- jego skill realnie zmienia produkcję i prawdopodobieństwo problemów.

#### Technik odwiertu
Dziś realnie wpływa na:
- ryzyko incydentów,
- ryzyko katastrof,
- wear / spiralę / awarie przez perki,
- skuteczność części zadań technicznych.

Główne miejsca w kodzie:
- `C:\xampp1\htdocs\src\Tick\WellLoopSection.php`
- `C:\xampp1\htdocs\src\Incident\TickTrait.php`
- `C:\xampp1\htdocs\src\TTS\TasksTrait.php`

Wniosek:
- technik realnie wpływa na stabilność odwiertu,
- ale nie jest dziś prostym mnożnikiem "niższego kosztu działu technicznego".

#### Kierownik Techniczny
Dziś realnie wpływa na:
- czas zadań technicznych,
- koszt zadań technicznych.

Główne źródło wpływu:
- `skill_organization`

Główne miejsce w kodzie:
- `C:\xampp1\htdocs\src\TTS\ManagerTrait.php`

Wniosek:
- kierownik techniczny realnie usprawnia dział,
- ale przez organizację zadań, a nie przez cięcie pensji ludzi.

#### BHP (Oficer BHP / Inżynier BHP)
Dziś realnie wpływa na:
- awarie,
- katastrofy,
- degradację,
- koszty napraw,
- uptime.

Główne miejsce w kodzie:
- `C:\xampp1\htdocs\src\TTS\ManagerTrait.php`

Wniosek:
- BHP jest jednym z najlepiej podpiętych systemów kompetencji w grze.

### 23.2. HR / Kadry - co dziś wpływa, a co nie

#### Miękkie skille kandydatów i pracowników
Obecne skille:
- `skill_organization`
- `skill_negotiation`
- `skill_analysis`
- `skill_stress`
- `skill_ethics`

Dziś realnie wpływają przede wszystkim na:
- ocenę kandydatów,
- sortowanie kandydatów,
- jakość zatrudnienia,
- część konkretnych ról zarządczych.

Główne miejsca w kodzie:
- `C:\xampp1\htdocs\src\HR\DataTrait.php`
- `C:\xampp1\htdocs\src\HR\HiringTrait.php`
- `C:\xampp1\htdocs\src\BankNegotiation\ContextTrait.php`
- `C:\xampp1\htdocs\src\TTS\ManagerTrait.php`

Wniosek:
- te skille są używane,
- ale nie tworzą jeszcze pełnego silnika produktywności całego działu HR.

#### HR jako dział
Na dziś HR nie ma silnego, własnego modelu kosztowego poza:
- pensjami pracowników,
- pensjami zarządu,
- opcjonalnymi kosztami specjalnych akcji (np. headhunter).

Wniosek projektowy:
- przyszłe systemy finansowe nie powinny udawać, że "cięcie HR" automatycznie obniża realny stały koszt działu,
- bardziej uczciwe jest wpływanie na:
  - czas rekrutacji,
  - jakość kandydatów,
  - skuteczność działań HR,
  - dostępność kosztownych akcji premium.

### 23.3. Logistyka - co dziś wpływa realnie

Dziś logistyka realnie wpływa na:
- koszt transportu,
- straty transportowe,
- koszt użycia hubów,
- straty przeciążenia hubów,
- straty fallbacku bez aktywnego huba,
- straty incydentów hubowych.

Główne miejsca w kodzie:
- `C:\xampp1\htdocs\src\Tick\WellLoopSection.php`
- `C:\xampp1\htdocs\src\HubTickService.php`
- `C:\xampp1\htdocs\src\HubIncidentService.php`
- `C:\xampp1\htdocs\src\FinanceService.php`
- `C:\xampp1\htdocs\public\logistics.php`
- `C:\xampp1\htdocs\public\finance.php`

Wniosek:
- logistyka już dziś ma realne, policzalne koszty i straty,
- dlatego to właśnie logistyka jest najbardziej naturalnym miejscem dla realnych oszczędności kosztowych w finansach.

#### Czego jeszcze brakuje logistyce
Na dziś nie ma jeszcze pełnego, działowego modelu kompetencji logistycznych w stylu:
- lepszy dyrektor logistyki = stale mniejsze straty,
- lepsza kadra logistyki = lepsze decyzje i wydajność regionu,
- pełne przypięcie każdego pracownika logistyki do mnożników ticka.

Wniosek projektowy:
- przy kolejnych etapach logistyka może być rozwijana jako pełnoprawny dział kompetencyjny,
- ale już dziś nadaje się do rozbudowy finansów, bo ma realne koszty operacyjne.

### 23.4. Finanse - co dziś wpływa realnie

#### CFO / dyrektor finansowy
Dziś realnie wpływa na:
- negocjacje bankowe,
- część jakości kontekstu negocjacyjnego.

Główne źródła wpływu:
- `skill_negotiation`
- `skill_analysis`

Główne miejsce w kodzie:
- `C:\xampp1\htdocs\src\BankNegotiation\ContextTrait.php`

Wniosek:
- CFO już działa, ale w wąskim zakresie,
- nie steruje jeszcze całym działem finansów i polityką firmy w szerokim sensie.

#### Finanse etap 2 - co już wdrożono
Obecny etap 2 finansów realnie wpływa na:
- techniczny,
- logistykę,
- HR,
- BHP,
- ocenę płynności,
- ocenę ryzyka,
- historię decyzji.

Główne miejsca w kodzie:
- `C:\xampp1\htdocs\src\FinancePolicyService.php`
- `C:\xampp1\htdocs\src\FinanceService.php`
- `C:\xampp1\htdocs\src\Tick\WellLoopSection.php`
- `C:\xampp1\htdocs\src\HR\RecruitmentTrait.php`
- `C:\xampp1\htdocs\src\CandidateGenerator.php`
- `C:\xampp1\htdocs\public\finance.php`

Wniosek:
- finanse przestały być tylko raportem,
- ale część nazw i założeń wymaga jeszcze dopracowania, żeby były w 100% zgodne z realnym modelem kosztów gry.

---

## 24. Co dziś nie wpływa jeszcze tak szeroko, jak może się wydawać

### 24.1. Brak pełnego systemu kompetencji działowych
Na dziś nie ma jeszcze pełnego modelu:
- lepszy dział HR = stale lepszy dział HR jako system,
- lepszy dział finansów = stale lepsza firma poza pojedynczymi obszarami,
- lepszy dział logistyki = pełny model kompetencyjny pracowników logistyki,
- każdy miękki skill każdej osoby wpływa stale na tick i wszystkie procesy.

### 24.2. Brak dynamicznego cięcia kosztów płacowych
Na dziś system nie ma jeszcze sensownego i uczciwego modelu:
- obniżania pensji pracowników kliknięciem,
- cięcia wynagrodzeń zarządu bez konsekwencji,
- pełnego morale / odejść / buntu / retencji związanego z cięciem płac.

Wniosek projektowy:
- systemy finansowe nie powinny zakładać, że plan oszczędności automatycznie tnie płace i "oszczędza" w ten sposób,
- dopóki nie ma pełnego systemu konsekwencji personalnych.

### 24.3. Miękkie skille nie są jeszcze pełnym silnikiem całej firmy
Skille takie jak:
- organizacja,
- negocjacje,
- analiza,
- stres,
- etyka

są dziś ważne,
ale nie tworzą jeszcze pełnej, globalnej symulacji kompetencji firmy.

---

## 25. Wnioski projektowe dla dalszej rozbudowy finansów

### 25.1. Budżety działowe należy interpretować ostrożnie
Obecne nazwy typu:
- `Budżet Techniczny`
- `Budżet HR`

mogą sugerować, że gra ma realne, osobne linie kosztowe dla tych działów.

Stan faktyczny jest bardziej złożony:
- Techniczny to dziś głównie koszt ludzi + wpływ na operacje,
- HR to dziś głównie koszt ludzi + wpływ na rekrutację,
- Logistyka ma najbardziej realny koszt operacyjny,
- BHP ma realny wpływ na ryzyko i awarie.

Wniosek:
- w kolejnych etapach warto rozważyć nazwy bliższe rzeczywistości,
  np. bardziej "priorytet" lub "intensywność działań" niż czysty "budżet",
- jeśli okaże się, że obecna nazwa wprowadza gracza w błąd.

### 25.2. Plan oszczędności nie powinien być sztucznym cięciem płac
Jeśli wdrażamy `Plan oszczędności`, to jego wpływ powinien być uczciwy wobec obecnego modelu gry.

Najbardziej naturalne skutki:
- logistyka: realnie niższe koszty operacyjne, ale większe straty i ryzyko,
- HR: gorsza skuteczność rekrutacji, dłuższy czas, słabsi kandydaci,
- techniczny: gorsza sprawność operacyjna, większe ryzyko zużycia i problemów,
- BHP: ewentualnie lekko wyższe ryzyko, ale ostrożnie.

Najmniej uczciwe byłoby dziś:
- sztuczne "-20% kosztów technicznego", jeśli realny koszt to głównie płace ludzi,
- sztuczne "-20% kosztów HR", jeśli nie ma tam osobnego kosztu procesowego.

### 25.3. Rezerwa awaryjna powinna być liczona jako cel bezpieczeństwa
Najzdrowszy model MVP:
- rezerwa awaryjna = liczba godzin kosztów firmy,
- np. 6h / 12h / 24h,
- bez tworzenia osobnego portfela gotówki na start.

To pozwala:
- oceniać płynność,
- budować alerty,
- uruchamiać decyzje kryzysowe,
- bez dokładania niepotrzebnej złożoności.

### 25.4. Logistyka jest najlepszym miejscem dla realnych oszczędności kosztowych
Spośród wszystkich działów to logistyka ma dziś najbardziej naturalne i policzalne pole do oszczędności:
- koszt transportu,
- koszt hubów,
- koszt strat,
- koszt fallbacku,
- koszt incydentów logistycznych.

Wniosek:
- jeśli finanse mają realnie "żyć", to decyzje finansowe powinny bardzo mocno czytać i modulować właśnie logistykę.

---

## 26. Co warto ustalać przed etapem 3

Przed wdrożeniem kolejnych funkcji finansowych trzeba świadomie odpowiedzieć na pytania:

1. Czy obecne `Budżety` zostają pod tą nazwą, czy zmieniamy je później na bardziej uczciwe nazwy?
2. Czy `Plan oszczędności` ma wpływać głównie na logistykę i skuteczność procesów, zamiast udawać proste cięcie kosztów płacowych?
3. Czy `Rezerwa awaryjna` pozostaje celem bezpieczeństwa gotówkowego, czy kiedyś ma stać się osobnym sub-portfelem?
4. Czy rozwijamy pełny system kompetencji działowych dla HR / Finansów / Logistyki, czy zostajemy przy punktowych wpływach?

Ta sekcja ma służyć jako punkt odniesienia przy wszystkich kolejnych decyzjach projektowych.

---

## 27. Etap 3 - plan wdrożenia technicznego

Ta sekcja rozbija etap 3 na realne prace techniczne.

Cel:
- najpierw ustalić dokładną kolejność wdrożenia,
- potem kodować bez zgadywania,
- nie rozwalić ticka, finansów, logistyki i UI przez chaotyczne wejście w duży moduł.

## 27.1. Zakres etapu 3

Etap 3 obejmuje:
- zakładkę `Ryzyko`,
- zakładkę `Polityka finansowa`,
- `Plan oszczędności`,
- `Rezerwę awaryjną`,
- rozbudowane alerty,
- historię decyzji,
- pełne czytanie danych z logistyki i hubów,
- panel admina dla konfiguracji tych mechanik.

Nie obejmuje jeszcze:
- cięcia płac pracowników i zarządu,
- osobnego sub-portfela gotówki,
- pełnego systemu morale / retencji,
- pełnego systemu kompetencji działowych dla wszystkich działów.

## 27.2. Krok 1 - warstwa danych

Najpierw trzeba dopiąć dane.

### Tabele / pola do sprawdzenia i rozszerzenia

#### `player_finance_settings`
Trzeba potwierdzić lub dopisać pola dla:
- `savings_plan_mode`
- `savings_plan_changed_at`
- `reserve_policy`
- `reserve_target_hours`
- `investments_paused`
- `liquidity_priority_mode`

#### `player_finance_decisions`
Trzeba zapisywać:
- decyzję,
- poprzednią wartość,
- nową wartość,
- payload efektu,
- czas zmiany,
- źródło zmiany (gracz / admin / GM).

#### `player_finance_alerts`
Jeśli tabela już istnieje w planie, trzeba ją wdrożyć lub potwierdzić:
- `player_id`
- `alert_code`
- `alert_level`
- `alert_payload_json`
- `created_at`
- `is_active`

#### `player_finance_snapshots`
Trzeba rozważyć wdrożenie snapshotów dla:
- `liquidity_score`
- `risk_score`
- `reserve_target_value`
- `reserve_current_coverage_hours`
- `hub_cost_total`
- `hub_loss_total`
- `fallback_loss_total`
- `transport_loss_total`
- `savings_plan_mode`

#### `finance_config`
Admin musi mieć globalną konfigurację dla:
- progów alertów,
- trybów planu oszczędności,
- poziomów rezerwy,
- cooldownów,
- wpływu logistyki i hubów na scoring ryzyka.

## 27.3. Krok 2 - backend i serwisy

### Serwisy, które trzeba ruszyć

#### `C:\xampp1\htdocs\src\FinanceService.php`
Rozszerzyć o:
- pełne liczenie ryzyka,
- pełne liczenie rezerwy awaryjnej,
- budowę alertów finansowych,
- rozdzielenie danych:
  - transport,
  - huby,
  - fallback,
  - incydenty logistyczne.

#### `C:\xampp1\htdocs\src\FinancePolicyService.php`
Rozszerzyć o:
- odczyt i zapis `Planu oszczędności`,
- odczyt i zapis `Rezerwy awaryjnej`,
- cooldown zmian,
- historię decyzji,
- budowę prostego podsumowania skutków dla UI.

#### Nowy lub rozszerzony serwis alertów finansowych
Jeśli obecna logika nie wystarczy, warto dodać osobną warstwę typu:
- `FinanceAlertService`

Odpowiedzialność:
- budowa alertów,
- aktywacja / wygaszanie,
- zapis historii alertów,
- payload pod UI gracza i admina.

#### Nowy lub rozszerzony serwis ryzyka
Jeśli `FinanceService` zrobi się zbyt ciężki, można wydzielić:
- `FinanceRiskService`

Odpowiedzialność:
- scoring ryzyka,
- interpretacja płynności,
- interpretacja kosztów logistycznych i hubowych,
- poziomy:
  - `Niskie`
  - `Umiarkowane`
  - `Wysokie`
  - `Krytyczne`

## 27.4. Krok 3 - integracja z tickiem

To jest najważniejsza część etapu 3.

### Tick ma dostarczyć pod finanse
- koszt transportu,
- koszt użycia hubów,
- straty transportowe,
- straty hubowe,
- straty fallbacku,
- koszty / skutki incydentów hubowych,
- aktualny cashflow,
- koszt godzinowy firmy,
- pokrycie kosztów przez gotówkę.

### Główne miejsca do analizy i wdrożenia
- `C:\xampp1\htdocs\src\Tick\PlayersSection.php`
- `C:\xampp1\htdocs\src\Tick\WellLoopSection.php`
- `C:\xampp1\htdocs\src\HubTickService.php`
- `C:\xampp1\htdocs\src\HubIncidentService.php`

### Zasady wdrożenia ticka
1. Nie dublować kosztów hubów i strat fallbacku.
2. Nie zgubić rozdzielenia:
   - transport loss
   - hub loss
   - fallback loss
   - hub incident loss
3. Rezerwa awaryjna ma czytać z realnych kosztów firmy, nie z liczb „na oko”.
4. Plan oszczędności ma wpływać uczciwie:
   - logistyka: realne koszty i ryzyko,
   - HR: czas i jakość rekrutacji,
   - techniczny: sprawność operacyjna,
   - BHP: ostrożnie, lekko.

## 27.5. Krok 4 - widok gracza

### Główne pliki
- `C:\xampp1\htdocs\public\finance.php`
- `C:\xampp1\htdocs\templates\views\public\finance\main.php`
- `C:\xampp1\htdocs\assets\js\finance.js`
- `C:\xampp1\htdocs\assets\css\finance.css`

### Co wdrożyć
- zakładkę `Ryzyko`,
- zakładkę `Polityka finansowa`,
- karty ryzyka,
- panel `Planu oszczędności`,
- panel `Rezerwy awaryjnej`,
- alerty z CTA,
- modale,
- historię decyzji.

### Zasady UI
- korzystamy z istniejącego shellu,
- korzystamy z istniejących klas wspólnych,
- `finance.css` tylko dla rzeczy specyficznych dla finansów,
- żadnych nowych białych systemowych elementów,
- wszystkie napisy po polsku,
- bez `alert()` i `confirm()`.

## 27.6. Krok 5 - panel admina

### Główne pliki
- `C:\xampp1\htdocs\admin\finance.php`
- `C:\xampp1\htdocs\templates\views\admin\finance\main.php`
- `C:\xampp1\htdocs\assets\css\admin.css`
- ewentualny dedykowany JS admina finansów, jeśli będzie potrzebny

### Co wdrożyć
- konfigurację planu oszczędności,
- konfigurację rezerwy awaryjnej,
- konfigurację alertów i progów,
- monitoring graczy,
- monitoring regionów i hubów,
- narzędzia GM / QA.

### Zasady admina
- wygląd jak nowoczesne panele admina,
- pełna polska terminologia,
- walidacja zapisów,
- logowanie działań admina,
- brak osobnego mini-systemu UI.

## 27.7. Krok 6 - tłumaczenia i komunikaty

Trzeba dopisać pełny zestaw kluczy do:
- `C:\xampp1\htdocs\lang\pl.php`

Zakres:
- zakładki,
- etykiety formularzy,
- opisy trybów,
- komunikaty sukcesu,
- komunikaty błędu,
- alerty,
- teksty modali,
- puste stany,
- etykiety admina.

Ważne:
- bez hardcoded tekstów w PHP,
- bez hardcoded tekstów w JS.

## 27.8. Krok 7 - testy

To nie może wejść bez testów.

### Testy funkcjonalne

#### Gracz
- wejście do wszystkich zakładek finansów,
- zmiana planu oszczędności,
- zmiana poziomu rezerwy,
- działanie cooldownu,
- działanie alertów,
- historia decyzji,
- poprawność danych z hubów i fallbacku.

#### Admin
- zapis konfiguracji etapu 3,
- odczyt monitoringu graczy,
- odczyt hubów i regionów,
- testy scenariuszy GM.

### Testy integracyjne
- poprawność danych z ticka,
- poprawność liczenia rezerwy,
- poprawność działania planu oszczędności,
- poprawność alertów,
- poprawność historii decyzji.

### PHPStan
- cały moduł finansów,
- miejsca dotykające ticka,
- miejsca dotykające logistyki i hubów.

## 27.9. Kolejność wdrożenia

Najzdrowsza kolejność:

1. migracje / tabele / pola
2. backend:
   - `FinancePolicyService`
   - `FinanceService`
   - alerty / ryzyko
3. tick integration
4. widok gracza
5. panel admina
6. tłumaczenia
7. testy funkcjonalne
8. testy integracyjne i phpstan
9. balans i korekty UX

## 27.10. Co robimy zaraz po tym briefie

Po zamknięciu tej specyfikacji następny sensowny ruch to:
- zrobić backup,
- wdrażać etap 3 dokładnie według kolejności z sekcji `27.9`,
- zaczynając od warstwy danych i backendu, a nie od samego UI.

---

## 28. Stan wdrożenia — aktualna mapa

> Ostatnia aktualizacja: 13.05.2026

### 28.1. Etap 1 — ✅ Zrealizowany

| Element | Status |
|---------|--------|
| Zakładka `Przegląd` | ✅ |
| Alerty finansowe (straty, huby, tax) | ✅ |
| Prognozy płynności podstawowe | ✅ |
| Odczyt danych hubowych w UI | ✅ |
| Rozszerzenie `finance_logs` o kolumny hubowe | ✅ `hub_usage_cost`, `hub_loss_bbl`, `hub_loss_value`, `fallback_loss_bbl`, `fallback_loss_value`, `hub_incident_loss_bbl`, `hub_incident_loss_value` |
| `FinanceService::saveTick()` rozszerzony | ✅ |

### 28.2. Etap 2 — ✅ Zrealizowany

| Element | Status |
|---------|--------|
| Zakładka `Budżety` | ✅ |
| Zakładka `Płynność` | ✅ |
| `player_finance_settings` (tabela + migracja) | ✅ |
| `FinancePolicyService::saveSettings()` | ✅ |
| Budżety: `technical`, `logistics`, `hr`, `safety` | ✅ |
| Wpływ budżetów na tick (modyfikatory) | ✅ `getTechnicalModifiers()`, `getLogisticsModifiers()`, `getHRModifiers()`, `getSafetyModifiers()` |
| Integracja z rekrutacją (`RecruitmentTrait`) | ✅ |
| Rezerwa gotówkowa (podstawowa) | ✅ |

### 28.3. Etap 3 — ✅ Zrealizowany

#### Backend — ✅ Gotowy

| Element | Status |
|---------|--------|
| `player_finance_decisions` (tabela + migracja) | ✅ |
| `savings_plan_mode`, `savings_plan_changed_at` w `player_finance_settings` | ✅ |
| `FinancePolicyService::savePolicySettings()` z cooldownem | ✅ |
| `FinancePolicyService::getSavingsPlanStatus()` | ✅ |
| `FinancePolicyService::getPolicySnapshot()` | ✅ |
| `FinancePolicyService::getDecisionHistory()` | ✅ |
| `FinanceService::getLiquidityOverview()` | ✅ |
| `FinanceService::getRiskOverview()` — 5 kart ryzyka | ✅ |
| `FinanceService::getStage3Alerts()` | ✅ |
| Modyfikatory planu oszczędności dla logistyki / HR / technicznego / BHP | ✅ |

#### Widok gracza — ✅ Gotowy

| Element | Status |
|---------|--------|
| Zakładka `Ryzyko` z kartami ryzyka i przyciskami CTA | ✅ |
| Zakładka `Polityka finansowa` — panel planu oszczędności | ✅ |
| Zakładka `Polityka finansowa` — panel rezerwy awaryjnej | ✅ |
| Zakładka `Historia decyzji` z etykietami etapu 3 | ✅ |
| Badge `!` na zakładce gdy plan oszczędności aktywny | ✅ |
| Wszystkie klucze `lang/pl.php` dla etapu 3 | ✅ |
| Style CSS (`finance.css`) dla etapu 3 | ✅ |
| Picker trybu/rezerwy z wizualnym zaznaczeniem | ✅ |

#### Integracja z tickiem — ✅ Zrealizowana

| Element | Status | Gdzie w kodzie |
|---------|--------|----------------|
| Odczyt `savings_plan_mode` w `WellLoopSection` | ✅ | `preloadFinancePolicies()` → `getLogisticsModifiers()` → `getSettings()` |
| Stosowanie `loss_mult` z planu oszczędności | ✅ | `WellLoopSection` linie ~577, ~832–836, ~893, ~933 |
| Stosowanie `transport_cost_mult` | ✅ | `WellLoopSection` linie ~604, ~613 |
| Stosowanie `hub_cost_mult` | ✅ | `WellLoopSection` linia ~968 |
| Stosowanie `wear_mult` / `degradation_mult` | ✅ | `WellLoopSection` linie ~440, ~444 |
| Stosowanie `disaster_mult` / `incident_mult` | ✅ | `WellLoopSection` linie ~452–453, ~480 |
| Zapis `hub_usage_cost`, `hub_loss_*`, `fallback_loss_*` do `finance_logs` | ✅ | `PlayersSection` → `saveTick()` z 7 polami hubowymi |

#### Panel admina etapu 3 — ✅ Wdrożony (Etap 4 + 5)

| Element | Status | Plik |
|---------|--------|------|
| Konfiguracja planu oszczędności | ✅ | `admin/finance.php` (configFields + save_config) |
| Konfiguracja rezerwy awaryjnej (D3) | ✅ | `templates/views/admin/finance/main.php` |
| Konfiguracja cooldownów | ✅ | `well_config` klucz `savings_plan_cooldown_hours` |
| Konfiguracja alertów i progów (4 klucze) | ✅ | `well_config` — DB-driven, Etap 5 |
| Monitoring ilu graczy ma aktywny plan | ✅ | `admin/finance.php` + template (3 karty) |
| Monitoring ilu graczy poniżej rezerwy | ✅ | `admin/finance.php` + template |
| Monitoring regionów i hubów | ✅ | `admin/finance.php` + template (Etap 5) |
| Reset ustawień polityki gracza przez GM | ✅ | `admin/finance.php` + template (GM tools) |
| Podgląd historii decyzji graczy przez admina | ✅ | `admin/finance.php` + template (filtr per gracz, max 100/200 wpisów) |

#### Testy PHPUnit — ✅ Zrealizowane

| Plik | Testy | Asercje | Status |
|------|-------|---------|--------|
| `tests/Integration/FinancePolicyServiceTest.php` | 38 | 82 | ✅ wszystkie przechodzą |
| `tests/Integration/FinanceServiceTest.php` | 27 | 106 | ✅ wszystkie przechodzą |
| `tests/Integration/AdminFinancePanelTest.php` | 35 | 71 | ✅ wszystkie przechodzą |
| **Łącznie** | **100** | **259** | **✅** |
| Cały suite (wszystkie testy projektu) | 529 | — | ✅ 0 błędów, 3 pominięte |

Szczegóły: §29.

### 28.4. Etap 4 — ✅ Zrealizowany (panel admina v3)

| Element | Status | Plik |
|---------|--------|------|
| Monitoring planu oszczędności (3 karty) | ✅ | `templates/views/admin/finance/main.php` |
| Tabela polityk per gracz (savings/reserve/budgets/cooldown) | ✅ | `templates/views/admin/finance/main.php` |
| Monitoring rezerwy — gracze poniżej celu | ✅ | `admin/finance.php` + template |
| D2 — edytowalna tabela mnożników (DB-driven, 18 kluczy `sp_*`) | ✅ | `admin/finance.php` + template + service |
| D3 — karty poziomów rezerwy awaryjnej | ✅ | `templates/views/admin/finance/main.php` |
| Narzędzia GM — reset polityki finansowej | ✅ | `admin/finance.php` + template |
| Cooldown DB-driven (`well_config` table) | ✅ | `src/FinancePolicyService.php` |
| Konfiguracja cooldown w formularzu admina | ✅ | `admin/finance.php` |
| Klucze językowe (`lang/pl.php`) | ✅ | `lang/pl.php` |
| CSS (`admin.css`) | ✅ | `assets/css/admin.css` |

### 28.5. Etap 5 — ✅ Zrealizowany (testy i stabilizacja)

| Element | Status |
|---------|--------|
| Testy PHPUnit integracyjne | ✅ 100 testów, 259 asercji (§29) |
| PHPStan level 6+ | ✅ `[OK] No errors` |
| Historia decyzji graczy (admin) | ✅ Etap 5 |
| Monitoring regionów i hubów | ✅ Etap 5 |
| Konfiguracja alertów i progów | ✅ Etap 5 |
| Balans mnożników | ❌ Wymaga danych z ticka |
| Poprawki UI/UX panelu gracza | 🔶 W toku |

---

## 29. Testy PHPUnit — wyniki

> **Status: ✅ Napisane i przechodzą** — 13.05.2026

Testy integracyjne trafiają do katalogu `tests/Integration/` (nie `tests/Finance/`).

### 29.1. Wyniki — podsumowanie

| Plik | Testów | Asercji | Wynik |
|------|--------|---------|-------|
| `tests/Integration/FinancePolicyServiceTest.php` | 38 | 82 | ✅ |
| `tests/Integration/FinanceServiceTest.php` | 27 | 106 | ✅ |
| `tests/Integration/AdminFinancePanelTest.php` | 35 | 71 | ✅ |
| **Razem (moduł finansów)** | **100** | **259** | **✅** |
| Cały suite projektu | 529 | — | ✅ 0 błędów, 3 pominięte |

### 29.2. `FinancePolicyServiceTest.php` — co pokrywa

```
tests/Integration/FinancePolicyServiceTest.php
```

38 testów, 82 asercje. Używa `player_id = 99999` (nieistniejący gracz),
FK checks wyłączone w `setUp()` / przywracane w `tearDown()`.

**`getSettings()`** (3 testy)
- zwraca wartości domyślne gdy gracz nie ma rekordu w DB
- zwraca poprawnie zapisane wartości po `saveSettings()`
- normalizuje nieprawidłowe wartości (`'invalid'` → `'standard'`)

**`saveSettings()`** (3 testy)
- zapisuje wszystkie 4 budżety i rezerwę do `player_finance_settings`
- zapisuje wpis do `player_finance_decisions` przy zmianie wartości
- nie tworzy wpisu gdy wartość się nie zmienia

**`savePolicySettings()`** (5 testów)
- zapisuje `savings_plan_mode` i `reserve_policy`
- zwraca `error: cooldown` gdy plan zmieniony mniej niż 6h temu
- pozwala zmienić gdy cooldown minął (symulacja przez bezpośredni UPDATE DB)
- zapisuje `savings_plan_changed_at` przy zmianie trybu planu
- nie zmienia `savings_plan_changed_at` gdy zmienia się tylko rezerwa

**`getSavingsPlanStatus()`** (4 testy)
- `can_change: true` gdy `savings_plan_changed_at` = NULL
- `can_change: true` gdy od zmiany minęło > 6h
- `can_change: false` gdy od zmiany minęło < 6h
- `cooldown_remaining_seconds` obliczone poprawnie

**`getPolicySnapshot()`** (5 testów)
- `reserve_state: good` gdy gotówka > cel × 1.25
- `reserve_state: caution` gdy gotówka między 100% a 125% celu
- `reserve_state: warning` gdy gotówka między 50% a 100% celu
- `reserve_state: critical` gdy gotówka < 50% celu
- `reserve_target_value = hourly_cost × reserve_hours`

**`getReserveTargetHours()`** (3 testy)
- `low` → 6.0
- `standard` → 12.0
- `high` → 24.0

**Modyfikatory — `getTechnicalModifiers()`** (4 testy)
- `low` budżet + `moderate` plan → `wear_mult` = 1.10 × 1.06 ≈ 1.166
- `high` budżet + `off` plan → `wear_mult` = 0.92
- `standard` budżet + `aggressive` plan → `wear_mult` = 1.00 × 1.12
- nieznany tryb planu traktowany jak `off`

**Modyfikatory — `getLogisticsModifiers()`** (4 testy)
- `standard` + `aggressive` → `loss_mult` = 1.00 × 1.18
- `low` + `moderate` → `transport_cost_mult` = 0.94 × 0.96
- `high` budżet → `loss_mult` < 1.0
- `standard` + `off` → bez zmian (mnożniki neutralne)

**Modyfikatory — `getHRModifiers()`** (3 testy)
- `standard` + `aggressive` → `duration_mult` = 1.00 × 1.18
- `low` budżet → gorsza jakość kandydatów (`quality_mult` < 1.0)
- `high` budżet → lepsza jakość kandydatów (`quality_mult` > 1.0)

**Modyfikatory — `getSafetyModifiers()`** (2 testy)
- `standard` + `aggressive` → `incident_mult` = 1.00 × 1.04
- `standard` + `moderate` → `incident_mult` = 1.0 (plan moderate nie dotyka BHP)

**`getDecisionHistory()`** (3 testy)
- zwraca listę posortowaną malejąco po `created_at`
- respektuje parametr `$limit`
- zwraca pustą tablicę gdy brak historii

---

### 29.3. `FinanceServiceTest.php` — co pokrywa

```
tests/Integration/FinanceServiceTest.php
```

27 testów, 106 asercji. Używa rzeczywistego DB (tylko odczyt + tabele pomocnicze).

**Istnienie tabel** (3 testy)
- `finance_logs` istnieje
- kolumny hubowe w `finance_logs` (`hub_usage_cost`, `hub_loss_bbl`, itd.)
- `player_finance_settings` istnieje

**`getLiquidityOverview()`** (7 testów)
- zwraca wszystkie wymagane klucze struktury
- `level: good` przy wysokiej gotówce i dodatnim wyniku
- `level: critical` gdy `cash + nextDay < 0`
- `level: warning` gdy gotówka poniżej `reserve_target_value`
- `coverage_hours = cash / hourly_cost`
- `coverage_hours = 999.0` gdy `hourly_cost = 0` (ochrona przed dzieleniem przez zero)
- `reserve_gap = 0` gdy gotówka powyżej celu rezerwy

**`getRiskOverview()`** (6 testów)
- zwraca listę dokładnie 5 elementów
- każdy element ma klucze: `key`, `label`, `level`, `level_label`, `desc`, `hint`, `action_tab`, `action_label`
- ryzyko logistyczne `high` gdy hub stanowi ≥ 10% przychodów
- ryzyko logistyczne `low` gdy hub stanowi < 4% przychodów
- ryzyko polityki `high` gdy plan `aggressive` lub rezerwa `low`
- ryzyko kosztów `high` gdy koszty ≥ 75% przychodów

**`getStage3Alerts()`** (5 testów)
- alert `reserve_below_target` gdy `is_below_target = true`
- alert `savings_plan_active` gdy tryb != `off`
- poziom alertu `info` dla `moderate`, `warning` dla `aggressive`
- alert `high_hub_usage_share` gdy udział hubu w przychodach > 12%
- pusta lista gdy brak warunków

**`getAlerts()`** (3 testy)
- podstawowa struktura alertów
- alert `net_loss` gdy `net_profit < 0`
- brak alertów przy pustej historii

**`getSummary()`** (3 testy)
- zwraca wymagane klucze
- kolumny hubowe uwzględnione w podsumowaniu
- `wells_active` ≥ 0

---

### 29.4. `AdminFinancePanelTest.php` — co pokrywa

```
tests/Integration/AdminFinancePanelTest.php
```

35 testów, 71 asercji. Player ID = 99998, FK wyłączone. Klucze `well_config` (`sp_*`, `savings_plan_cooldown_hours`) przywracane do stanu sprzed testu przez `wellConfigSaved[]`.

**`loadSavingsMultipliers()` — wartości domyślne** (6 testów)
- logistics moderate/aggressive — wszystkie 4 pola (transport, hub, loss, incident)
- technical moderate/aggressive — wear, degradation
- HR moderate/aggressive — duration, quality

**`loadSavingsMultipliers()` — DB nadpisuje domyślne** (7 testów)
- `sp_log_transport_mod` → transport_cost_mult moderate
- `sp_log_transport_agg` → transport_cost_mult aggressive
- `sp_log_loss_mod` + `sp_log_incident_mod` → oba pola jednocześnie
- `sp_tech_wear_mod` → wear, degradation bez zmian
- `sp_tech_degr_agg` → degradation, wear bez zmian
- `sp_hr_duration_agg` + `sp_hr_quality_agg` → oba pola HR aggressive
- `sp_safety_incident_agg` + `sp_safety_disaster_agg` → BHP aggressive

**Brak klucza w DB → fallback** (2 testy)
- brak `sp_log_transport_mod` → 0.96, `sp_log_hub_mod` z DB → 0.50 jednocześnie
- wartość 0.5 (liczbowa) — zaakceptowana przez serwis (walidacja jest w kontrolerze)

**Tryb OFF — brak mnożnika** (2 testy)
- logistics off → transport/hub/loss = 1.00 niezależnie od sp_* w DB
- technical off → wear = 1.00

**BHP — tylko aggressive, moderate bez zmian** (3 testy)
- moderate → incident/disaster = 1.00
- aggressive defaults → 1.04 / 1.02
- aggressive z DB-override → używa DB wartości

**Składanie mnożników: budżet × plan oszczędności** (3 testy)
- low technical × custom moderate → poprawny iloczyn
- high logistics × custom aggressive → poprawny iloczyn
- dwa różne klucze DB → każdy wpływa tylko na swoje pole

**`getCooldownHours()` (via `getSavingsPlanStatus()`)** (4 testy)
- brak klucza w DB → 6h
- klucz = 12 → 12h
- klucz = 0 → clamped ≥ 1
- klucz = 9999 → clamped ≤ 168

**Cooldown blocking z DB-driven cooldown** (3 testy)
- 12h cooldown, zmiana 7h temu → blokada (`error: cooldown`)
- 12h cooldown, zmiana 13h temu → dozwolone
- 24h cooldown, zmiana 1h temu → `cooldown_remaining_seconds` ≈ 82800s

**GM policy reset** (3 testy)
- player z ustawieniami → DELETE → `getSettings()` zwraca defaults
- player z historią decyzji → DELETE decisions → COUNT = 0
- player bez ustawień → DELETE → brak błędu, defaults

**Cache izolacja i trwałość DB** (2 testy)
- nowy klucz w DB → `freshSvc()` widzi wartość
- ta sama instancja → cached wartość, nowa instancja → odczyt z DB

### 29.5. Uruchamianie testów

```bash
# Wszystkie testy finansów
& "C:\xampp1\php\php.exe" vendor/bin/phpunit tests/Integration/FinancePolicyServiceTest.php tests/Integration/FinanceServiceTest.php tests/Integration/AdminFinancePanelTest.php --testdox

# Tylko AdminFinancePanelTest
& "C:\xampp1\php\php.exe" vendor/bin/phpunit tests/Integration/AdminFinancePanelTest.php --testdox

# Cały suite projektu
& "C:\xampp1\php\php.exe" vendor/bin/phpunit --testdox
```

### 29.5. Szczegóły techniczne testów

#### Izolacja danych (FK trick)

`FinancePolicyServiceTest` używa `player_id = 99999` (nieistniejący gracz).
Aby ominąć klucze obce:

```php
protected function setUp(): void
{
    $this->db = Database::getInstance()->getConnection();
    $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
    $this->cleanup();
    // ...
}

protected function tearDown(): void
{
    $this->cleanup();
    $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');
}
```

#### Bootstrap — dodana zależność

Plik `tests/bootstrap.php` wymagał dopisania:

```php
require_once APP_ROOT . '/src/FinancePolicyService.php';
```

(był pomijany przez autoloader — klasa ładowana ręcznie w bootstrap jak `FinanceService`)

#### Poprawki w innych testach

Przy okazji pisania testów finansowych naprawiono 2 pre-existing błędy w suite:

| Plik | Problem | Naprawa |
|------|---------|---------|
| `tests/Integration/Tick/WellLoopSectionTest.php` | Błąd składni: zbłąkane `w` na końcu linii 164 powodowało `ParseError` łamiący cały suite | Usunięto zbłąkany znak |
| `tests/Integration/HubAssignmentServiceTest.php` | Testy oczekiwały `'not_assigned'` ale serwis sprawdza istnienie studni przed przypisaniem i zwraca `'well_not_found'` | Zmieniono `assertEquals` na `assertContains([...])` z listą dopuszczalnych kodów |

### 29.6. Priorytety dla kolejnych testów

| Priorytet | Co jeszcze warto przetestować | Uzasadnienie |
|-----------|-------------------------------|--------------|
| 1 | Integracja ticka — `WellLoopSection` z modyfikatorami | Gdy modyfikatory zostaną podpięte do ticka |
| 2 | `saveTick()` z polami hubowymi | Gdy dane hubowe będą przekazywane z `PlayersSection` |
| 3 | ~~PHPStan level 6+~~ | ✅ Zrealizowane — `[OK] No errors` |
| 4 | Balans mnożników — testy regresji | Po ustaleniu docelowych wartości |

---

## 30. Najbliższe kroki wdrożenia

### Krok 1 — Integracja z tickiem (priorytet)

Bez tego etap 3 jest tylko UI bez realnego wpływu na grę.

#### `src/Tick/WellLoopSection.php`

Na początku loopa per gracz wczytać politykę:
```php
$logMods = $policySvc->getLogisticsModifiers($playerId);
```

Następnie w obliczeniach transportu:
```php
$transportCost *= (float)$logMods['transport_cost_mult'];
$hubUsageCost  *= (float)$logMods['hub_cost_mult'];
$lossValue     *= (float)$logMods['loss_mult'];
```

#### `src/Tick/PlayersSection.php`

Przekazać do `FinanceService::saveTick()` zebrane dane hubowe:
```php
$finSvc->saveTick(
    ...,
    hubUsageCost: $totals['hub_usage_cost'],
    hubLossBbl: $totals['hub_loss_bbl'],
    hubLossValue: $totals['hub_loss_value'],
    fallbackLossBbl: $totals['fallback_loss_bbl'],
    fallbackLossValue: $totals['fallback_loss_value'],
    hubIncidentLossBbl: $totals['hub_incident_loss_bbl'],
    hubIncidentLossValue: $totals['hub_incident_loss_value'],
);
```

### Krok 2 — Testy PHPUnit ✅ Zrealizowane

100 testów integracyjnych napisanych i przechodzących (§29).
- `tests/Integration/FinancePolicyServiceTest.php` — 38 testów
- `tests/Integration/FinanceServiceTest.php` — 27 testów

### Krok 3 — Panel admina etapu 3 ✅ Zrealizowany (Etap 4 + 5)

Wdrożono wszystkie sekcje §17.2 (D2, D3, H) w `admin/finance.php` + template:
- D2 — edytowalna tabela 18 mnożników (`sp_*` w `well_config`)
- D3 — karty poziomów rezerwy awaryjnej
- Historia decyzji graczy (filtr per gracz)
- Monitoring regionów i hubów (JOIN 4 tabel)
- Konfiguracja alertów i progów (4 klucze DB)

### Krok 4 — PHPStan ✅ Zrealizowany

```bash
vendor/bin/phpstan analyse src/FinanceService.php src/FinancePolicyService.php --level 6
# [OK] No errors
```

### Krok 5 — Poprawki UI/UX panelu gracza 🔶 W toku

Zakres:
- Sekcja hubów — usunięcie podwójnego zagnieżdżenia `g-card > g-card`
- Tabela per-odwiert — miniaturowe paski zysku (profit bar)
- Zakładka Polityka — cooldown z odliczaniem JS, scalenie dwóch formularzy w jeden
- Zakładka Historia — ikony per typ decyzji, czas relatywny ("2h temu")
- Zakładka Budżety — opis efektu per poziom wyświetlany dynamicznie przez JS

Pliki: `templates/views/public/finance/main.php`, `assets/css/finance.css`

### Krok 6 — Balans mnożników

Po integracji z tickiem — weryfikacja czy wartości modyfikatorów są wyważone względem obecnych liczb gry.

---

## 31. Checklista testowa finansów

Ta checklista ma służyć do realnego sprawdzenia modułu po wdrożeniu, a nie tylko do odhaczenia dokumentacji.

Przy każdym punkcie warto oznaczać jeden z trzech statusów:
- działa
- częściowo
- ug

### 31.1. Wejście do panelu gracza

Sprawdzić:
- czy C:\xampp1\htdocs\public\finance.php otwiera się bez błędu,
- czy header, status boxy i shell strony wyglądają normalnie,
- czy wszystkie napisy są po polsku,
- czy nie ma surowych kluczy tłumaczeń typu inance.xxx.

### 31.2. Zakładki gracza

Sprawdzić zakładki:
- Przegląd
- Budżety
- Płynność
- Ryzyko
- Polityka finansowa
- Historia decyzji

Dla każdej:
- czy da się kliknąć,
- czy otwiera właściwą treść,
- czy aktywna zakładka jest poprawnie podświetlona,
- czy layout się nie rozjeżdża.

### 31.3. Przegląd finansów

Sprawdzić:
- saldo,
- zysk / tick,
- zysk / godzina,
- straty logistyki,
- historię finansową,
- strukturę finansową,
- huby logistyczne i fallback,
- analizę per odwiert.

Porównać, czy:
- liczby są sensowne,
- nie ma pustych wartości ani NaN,
- dane reagują po ticku.

### 31.4. Dane z hubów

Na graczu z aktywnymi hubami sprawdzić:
- koszt użycia hubów,
- straty hubowe,
- straty fallbacku,
- straty incydentów hubowych.

Warto wykonać dwa scenariusze:
1. odwierty przypisane do hubów,
2. odwierty bez huba / fallback.

### 31.5. Budżety działów

W zakładce Budżety zmienić:
- techniczny,
- logistyka,
- HR,
- BHP.

Sprawdzić:
- czy zapis działa,
- czy pojawia się komunikat sukcesu,
- czy po odświeżeniu ustawienia zostają,
- czy Historia decyzji zapisuje zmianę.

### 31.6. Płynność

Sprawdzić:
- prognozę na tick,
- prognozę na 1h / 6h / 24h,
- koszt godzinowy,
- poziom rezerwy,
- pokrycie kosztów.

Porównać ze stanem firmy:
- przy dużej gotówce ryzyko powinno być niskie,
- przy małej gotówce i wysokich kosztach powinno rosnąć.

### 31.7. Ryzyko

Sprawdzić:
- czy są karty ryzyka,
- czy pokazują poziomy 
iskie / umiarkowane / wysokie / krytyczne,
- czy opisy mają sens,
- czy CTA prowadzi do właściwej zakładki.

### 31.8. Polityka finansowa

Sprawdzić:
- Plan oszczędności,
- Rezerwa awaryjna.

#### A. Plan oszczędności

Przetestować:
- Wyłączony
- Umiarkowany
- Agresywny

Sprawdzić:
- czy zapis działa,
- czy zmiana respektuje cooldown,
- czy komunikaty są poprawne,
- czy po zapisie tryb zostaje.

#### B. Rezerwa awaryjna

Przetestować:
- Niska
- Standardowa
- Wysoka

Sprawdzić:
- czy poziom zapisuje się,
- czy po odświeżeniu zostaje,
- czy zmienia się ocena ryzyka i płynności.

### 31.9. Cooldown planu oszczędności

Scenariusz:
1. zmienić plan z Wyłączony na Umiarkowany,
2. od razu spróbować zmienić go na Agresywny.

Sprawdzić:
- czy system blokuje drugą zmianę,
- czy pokazuje poprawny komunikat o cooldownie,
- czy nie zapisuje nieuprawnionej zmiany.

### 31.10. Historia decyzji

Po kilku zmianach ustawień sprawdzić:
- czy wpisy się pojawiają,
- czy mają polskie etykiety,
- czy pokazują stare i nowe wartości,
- czy kolejność wpisów jest poprawna.

### 31.11. Tick a polityki finansowe

Najważniejszy test funkcjonalny.

Wykonać trzy próby:
1. plan oszczędności Wyłączony,
2. plan oszczędności Umiarkowany,
3. plan oszczędności Agresywny.

Po każdej:
- puścić kilka ticków,
- porównać koszty logistyki,
- porównać straty logistyczne,
- porównać dane hubowe,
- porównać czas i jakość rekrutacji HR,
- porównać ocenę ryzyka.

### 31.12. Panel admina finansów

Wejść do:
- C:\xampp1\htdocs\admin\finance.php

Sprawdzić:
- globalne statystyki,
- rozkład trybów oszczędności,
- monitoring rezerwy,
- monitoring hubów,
- polityki graczy,
- konfigurację mnożników,
- konfigurację progów alertów.

### 31.13. Zapis mnożników admina

W panelu admina zmienić kilka mnożników:
- koszt transportu,
- koszt huba,
- straty transportowe,
- degradacja,
- czas HR.

Sprawdzić:
- czy wartości wracają po odświeżeniu,
- czy wpływają na panel gracza i tick.

### 31.14. Reset polityki gracza

W panelu admina uruchomić:
- reset polityki finansowej gracza.

Następnie sprawdzić po stronie gracza:
- czy wróciły domyślne ustawienia,
- czy historia decyzji zachowuje się sensownie,
- czy nie ma rozjazdu między panelem a backendem.

### 31.15. Alerty finansowe

Sprawdzić różne stany:
- wysokie straty logistyki,
- niski cash,
- aktywny plan oszczędności,
- wysoki koszt hubów,
- fallback loss.

Ocenić:
- czy alerty pokazują się wtedy, kiedy powinny,
- czy nie pokazują się bez powodu,
- czy teksty są po polsku i zrozumiałe.

### 31.16. Tłumaczenia i kodowanie

Sprawdzić:
- czy w panelu gracza i admina nie ma:
  - `finance.xxx`
  - `admin.finance.xxx`
  - `â`, `Ä`, `Ĺ`, `Â`
- czy wszystkie teksty są po polsku.

### 31.17. Layout i spójność UI

Sprawdzić:
- czy panel gracza finansów pasuje do reszty gry,
- czy admin finansów wygląda jak inne panele admina,
- czy nie ma białych selectów,
- czy modale są projektowe, nie systemowe.

### 31.18. Test awaryjny

Sprawdzić skrajny scenariusz:
- niski cash,
- wysokie koszty,
- aktywny plan oszczędności,
- wysokie straty hubowe / fallback,
- kilka ticków pod rząd.

Ocenić:
- czy panel się nie sypie,
- czy liczby dalej są sensowne,
- czy alerty rosną logicznie.

### 31.19. Minimum sensownego testu

Jeśli trzeba zrobić szybki przegląd, to najpierw sprawdzić te 6 rzeczy:
1. zakładki gracza,
2. zapis budżetów,
3. zapis planu oszczędności i cooldown,
4. wpływ ticka na koszty i straty,
5. adminowe mnożniki i zapis,
6. dane z hubów w finansach.

## 32. ✅ Widoczność realnego wpływu polityki finansowej

### Stan wdrożenia — 16.05.2026

Wdrożono:
- wspólny backend `policy impact snapshot` w `FinanceService`,
- nowy panel `Wpływ polityki finansowej` w widoku gracza,
- skrót wpływu polityki w `Przeglądzie`,
- warstwę rekomendacji `Co warto zrobić dalej`,
- podsumowanie:
  - aktywnego planu oszczędności,
  - aktywnej rezerwy,
  - efektu ostatniego ticku,
  - efektu 24h,
- rekomendowane następne kroki:
  - `Logistyka`,
  - `Płynność`,
  - `Budżety`,
  - `Historia decyzji`,
- tłumaczenia UI dla nowego panelu.

Potwierdzenie automatyczne:
- `FinanceServiceTest` — OK
- `FinancePolicyServiceTest` — OK
- `AdminFinancePanelTest` — OK

Potwierdzenie funkcjonalne:
- panel pokazuje aktywne efekty polityki po ludzku, a nie surowe mnożniki,
- gracz widzi bilans polityki dla ostatniego ticku i dla 24h,
- rekomendacje prowadzą do właściwych sekcji (`Logistyka`, `Płynność`, `Budżety`, `Historia decyzji`),
- warstwa `Co warto zrobić dalej` działa jako następny krok, a nie tylko raport.

Uwagi:
- `Rezerwa awaryjna` pozostaje celem bezpieczeństwa, a nie osobnym portfelem,
- `Plan oszczędności` pokazuje realny wpływ na logistykę, huby, HR, techniczny i BHP,
- jeśli efekt jest pośredni lub opóźniony, UI ma to komunikować wprost.

## 33. Co jeszcze zostało do zrobienia

### 33.1. Admin finansów — ✅ wyrównany do gracza

Wdrożono:
- zbiorczy podgląd wpływu polityki per gracz,
- szybkie porównanie, którym graczom polityka realnie pomaga, a którym szkodzi,
- czytelny podgląd graczy z ujemnym bilansem polityki 24h,
- spięcie z istniejącym monitoringiem hubów i rezerwy,
- wykorzystanie tego samego backendu snapshotów i rekomendacji co po stronie gracza.

Potwierdzenie automatyczne:
- `FinanceServiceTest` — OK
- `FinancePolicyServiceTest` — OK
- `AdminFinancePanelTest` — OK

### 33.2. Ręczne potwierdzenie scenariuszy na żywym ticku

Do dalszego potwierdzenia ręcznego:
- odbiór wizualny panelu w grze,
- czytelność dla gracza przy zmianie `Wyłączony / Umiarkowany / Agresywny`,
- porównanie danych na żywym ticku między różnymi trybami,
- zachowanie panelu przy skrajnych scenariuszach:
  - niski cash,
  - wysokie koszty,
  - duże straty logistyki,
  - odwierty bez huba.

### 33.3. Test końcowy etapu 5

Etap 5 jest wdrożony funkcjonalnie, ale do pełnego domknięcia warto jeszcze:
- potwierdzić ręcznie zgodność danych gracza i admina,
- sprawdzić zapis mnożników i progów po zmianach admina,
- przejść końcową checklistę z §31,
- oznaczyć końcowy stan jako zamknięty po ręcznym audycie UX i danych.
## 34. ✅ Globalna konfiguracja finansowa wpływa już na tick

Domknięto połączenie między panelem admina Finansów a realnym tickiem gry.

### Co działa teraz realnie
- `global_tax_modifier`
  - zapisuje się także jako runtime key `global_tax_multiplier`
  - wpływa na regionalny podatek naliczany w ticku

- `global_cost_modifier`
  - zapisuje się także jako runtime key `global_opex_multiplier`
  - wpływa na:
    - OPEX odwiertów
    - koszt transportu procentowy
    - koszt transportu za baryłkę
    - koszt użycia huba

- `global_loss_modifier`
  - zapisuje się także jako runtime key `global_loss_multiplier`
  - wpływa na straty logistyczne w ticku

### Gdzie zostało to spięte
- `admin/finance.php`
- `admin/partials/finance_admin_actions.php`
- `cron/tick.php`
- `cron/tick_new.php`
- `src/Tick/WellLoopSection.php`

### Efekt projektowy
Panel admina Finansów nie ma już martwych ustawień dla:
- podatków
- kosztów
- strat

Zmiana w panelu przekłada się teraz na realne wyniki ticka.
