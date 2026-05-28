# Rurociąg

## Do czego służy rurociąg

Rurociąg służy do przesyłania ropy z konkretnego odwiertu lądowego dalej do logistyki.

Najważniejsze zasady:
- jeden odwiert lądowy powinien mieć swój własny rurociąg
- jeśli go nie ma, ropa musi jechać transportem drogowym
- transport drogowy jest droższy i bardziej ryzykowny

---

## Czym jest rurociąg w grze

Rurociąg:
- należy do gracza
- jest kupowany osobno
- jest przypisany do konkretnego odwiertu
- ma swój stan techniczny
- zużywa się w czasie
- może się psuć
- trzeba go naprawiać
- trzeba go konserwować

Rurociąg nie jest wspólny dla całego regionu i nie jest tylko liczbą w panelu.  
Ma być realną częścią logistyki.

---

## Jak ma działać

To, że odwiert wydobył ropę, nie oznacza jeszcze, że ropa dotarła dalej.

Ropa zawsze przechodzi przez dwa etapy transportu — zawsze przez hub.

### Schemat: ląd

```
odwiert → [rurociąg LUB droga] → hub → [rurociąg LUB droga] → magazyn
```

### Schemat: morze

```
odwiert → [transport morski] → hub → [rurociąg LUB droga] → magazyn
```

Każdy etap jest osobnym wyborem transportu z własnymi kosztami, stratami i ryzykiem incydentów.  
Gracz konfiguruje etap 1 (odwiert → hub) i etap 2 (hub → magazyn) niezależnie.

Schemat działania w tiku:
1. odwiert wydobywa ropę
2. etap 1: obliczamy ile ropy dojechało do hubu (straty, opex, ryzyko incydentu)
3. etap 2: obliczamy ile ropy dojechało z hubu do magazynu (j.w.)
4. do magazynu trafia tylko to, co przeszło oba etapy

Nie cała produkcja ma trafiać automatycznie do magazynu.

---

## Co wpływa na działanie rurociągu

Na działanie rurociągu wpływa:
- jego typ
- jego stan techniczny
- to, ile ropy przez niego płynie
- czy był konserwowany
- czy był naprawiany
- czy wydarzyła się awaria albo wyciek

---

## Rodzaje rurociągu

Na start wystarczą trzy rodzaje:

### Lekki
- tani
- ma mniejszą przepustowość
- szybciej się zużywa
- dobry dla małych odwiertów

### Standardowy
- średni koszt
- średnia przepustowość
- średnia trwałość
- podstawowy wybór dla większości odwiertów

### Mocny
- drogi
- ma dużą przepustowość
- wolniej się zużywa
- dobry dla dużych odwiertów

---

## Stany rurociągu

Rurociąg może mieć następujące stany:
- planowany
- w budowie
- aktywny
- uszkodzony
- z wyciekiem
- wstrzymany
- wyłączony

---

## Co się dzieje, gdy rurociąg jest w złym stanie

Im gorszy stan rurociągu:
- tym mniej ropy przepuści
- tym więcej ropy może się stracić
- tym większe ryzyko awarii
- tym większe ryzyko wycieku
- tym bardziej opłaca się go naprawić albo wymienić

Rurociąg nie może być tylko ozdobą.  
Jeśli jest zaniedbany, gracz ma naprawdę tracić ropę i pieniądze.

---

## Wyłączenie rurociągu — zasada kosztów

Wyłączenie rurociągu **nie znosi kosztów transportu**.

Gdy rurociąg jest wyłączony (`suspended` / `disabled`), odwiert automatycznie przełącza się na transport drogowy.  
Transport drogowy ma wyższy OPEX i wyższe straty niż dobrze utrzymany rurociąg.

Gracz nie może uniknąć kosztów przez wyłączenie rurociągu — traci na tym więcej ropy i pieniędzy, nie mniej.

Może się to opłacić tylko wtedy, gdy stan rurociągu jest tak zły, że jego straty przekraczają straty transportu drogowego — ale wtedy gracz i tak traci, tylko inaczej.

---

## Konserwacja i naprawa

### Konserwacja
Konserwacja ma spowalniać dalsze zużycie rurociągu.

### Naprawa
Naprawa ma poprawiać stan techniczny rurociągu po uszkodzeniach i awariach.

To są dwa różne działania i nie powinny robić dokładnie tego samego.

---

## Jakie problemy mogą wystąpić

Na rurociągu mogą wystąpić:
- mały wyciek
- duży wyciek
- awaria
- spadek przepływu
- uszkodzenie
- sabotaż

Skutki:
- mniej ropy dociera dalej
- rosną koszty
- trzeba naprawiać
- może pojawić się przestój

---

## Co gracz ma móc zrobić

Gracz powinien móc:
- kupić rurociąg
- zobaczyć jego stan
- naprawić go
- konserwować go
- ulepszyć go
- wyłączyć go
- uruchomić go ponownie
- przełączyć odwiert na transport drogowy, jeśli nie chce używać rurociągu

---

## Co gracz ma widzieć

Gracz ma widzieć:
- czy odwiert ma rurociąg
- jaki to typ
- jaki ma stan
- ile ropy przez niego przechodzi
- ile ropy się na nim traci
- ile kosztuje utrzymanie
- czy jest ryzyko awarii
- czy wymaga naprawy

---

## Co ma być widoczne w panelu zarządzania

Panel powinien pokazywać:
- najgorsze rurociągi
- rurociągi z największymi stratami
- rurociągi w krytycznym stanie
- odwierty bez rurociągu
- które odwierty jadą drogą zamiast rurociągiem
- gdzie gracz traci najwięcej pieniędzy przez zły przesył ropy

---

## Najważniejsza zasada całego systemu

Rurociąg ma być prawdziwą częścią logistyki, a nie tylko liczbą w panelu.

Jeśli rurociąg jest w złym stanie, gracz ma naprawdę tracić ropę i pieniądze.  
Jeśli dba o rurociąg, ma mieć stabilniejszy przesył i większy zysk.

---

## Krótka wersja

Rurociąg jest kupowanym przez gracza połączeniem przypisanym do konkretnego odwiertu lądowego. To on odpowiada za przesył ropy z odwiertu do dalszej logistyki. Rurociąg ma własny stan techniczny, zużywa się w czasie, może ulegać awariom i wymaga konserwacji oraz napraw. Jeśli odwiert nie ma rurociągu, może korzystać z droższego i bardziej ryzykownego transportu drogowego. Do dalszej logistyki trafia tylko ta ropa, którą udało się naprawdę przesłać.

---

---

## Stan implementacji

> Ostatnia aktualizacja: 2026-05-28

### ✅ Zaimplementowane

#### Baza danych i backend

- **Tabela `well_pipelines`** — pełna struktura: typ, status (enum 9 stanów), `condition_pct`, `transport_loss`, `nominal/real_capacity_bph`, `degradation_rate_per_hour`, `incident_risk_mult`, `opex_per_tick`, `opex_per_bbl`, `build_cost`, daty budowy i konserwacji
- **Tabela `well_pipeline_events`** — historia zdarzeń: `event_type`, `severity`, `level`, `message`, `pipeline_id`, `well_id`, `player_id`, `created_at`
- **`WellPipelineService`** — serwis backendowy: degradacja stanu technicznego w tiku, obliczanie `transport_loss`, losowanie incydentów pipe_micro/minor/medium, zapis do `well_pipeline_events`
- **Integracja z tikiem** (`cron/tick.php`) — degradacja rurociągów, straty transportu i incydenty obliczane przy każdym tiku

#### Panel gracza (`logistics.php` + `templates/views/logistics/main.php`)

- Lista rurociągów gracza: typ, status z badge (active / degraded / critical / damaged / leak / building / suspended / disabled), stan techniczny, transport_loss, OPEX
- CSS badge `.logistics-pipeline-badge--leak` — osobny styl dla statusu wyciek
- Karta insights **"Najgorsze rurociągi"** — top 3 według najniższego `condition_pct`, z kolorem stanu i informacją o stratach
- Siatka kart insights rozszerzona do 4 kolumn (was 3)
- **Akcje gracza na kartach rurociągów** — przyciski Napraw / Konserwacja / Wstrzymaj / Wznów z modalem potwierdzenia (styled, nie natywny `confirm()`)
- **`WellPipelineService`** — metody `repairPipeline`, `maintenancePipeline`, `togglePipeline` z atomicznym potrąceniem gotówki
- **`PipelineApi.php`** — endpointy `repair_pipeline`, `maintenance_pipeline`, `toggle_pipeline`
- **CSS** `.logistics-pipeline-actions`, `.logistics-modal-body`, `.logistics-modal-box--sm` w `assets/css/logistics.css`

#### Panel admina — incydenty (`admin/incidents.php`)

- Zakładki konfiguracji **pipe_micro / pipe_minor / pipe_medium** — konfiguracja zakresów `loss_add`, `cond_drop`, `base_chance`; zapis do `well_config`; reset do domyślnych
- **Historia incydentów UNION ALL** — łączy `well_incidents` (odwierty) z `well_pipeline_events` (rurociągi), filtr źródła (Wszystkie / Odwiert / Rurociąg), collation fix dla MySQL 8.4
- **Wywołanie incydentu rurociągu** w zakładce "Wywołaj incydent" — wybór gracza → dynamiczna lista rurociągów → poziom (pipe_micro/minor/medium) → efekty natychmiastowe: `UPDATE well_pipelines` (loss + cond), `INSERT well_pipeline_events`, wpis do AdminLog

#### Panel admina — rurociągi (`admin/pipelines.php`)

- Lista wszystkich rurociągów z filtrowaniem
- Akcja masowa: naprawa wszystkich krytycznych rurociągów (condition < 30% → 80%, loss → 5%)
- Ręczna naprawa i wymuszenie awarii (condition=15%, loss=25%) pojedynczego rurociągu
- Historia awarii (ostatnie 20 zdarzeń z `well_pipeline_events`)
- Statystyki: łącznie, aktywne, krytyczne, ostrzeżenia, avg loss, avg condition

#### Panel admina — monitoring strat (`admin/transport_loss.php`)

- Globalne statystyki strat transportu (avg/max pipeline loss, krytyczne rurociągi)
- Straty per typ transportu (pipeline/truck/tanker)
- Straty per warstwa geologiczna × typ transportu
- Straty per gracz
- Top 20 odwiertów z najwyższymi stratami (OPEX + utrata przez cap)

#### Panel admina — alerty (`admin/alerts.php`)

- Alerty dla rurociągów w krytycznym stanie (condition < 30%)
- Alert dla podwyższonych i krytycznych strat transportu (avg loss > progi)

#### Tłumaczenia (`lang/pl/`)

- `lang/pl/logistics.php` — wszystkie klucze widoku gracza (rurociągi, badges, insights)
- `lang/pl/admin.php` — klucze dla `admin/pipelines`, `admin/transport_loss`, `admin/incidents` (pipeline)

---

### ⬜ Do zrobienia

#### Akcje gracza

- ~~**Naprawa rurociągu**~~ ✅ zaimplementowane
- ~~**Konserwacja rurociągu**~~ ✅ zaimplementowane
- ~~**Wyłącz / wznów rurociąg**~~ ✅ zaimplementowane (`active ↔ suspended`, odwiert wraca na transport drogowy)
- **Ulepszenie rurociągu** — zmiana typu (lekki → standardowy → mocny); koszt, czas budowy
- **Przełączenie odwiertu na transport drogowy** — gracz może zrezygnować z rurociągu bez jego usuwania

#### Zakup rurociągu (brak pełnego flow)

- Formularz zakupu: wybór odwiertu, wybór typu rurociągu, potwierdzenie kosztu
- Start budowy: INSERT do `well_pipelines` ze statusem `building`, ustawienie `build_finish_at`
- Automatyczne przejście `building → active` po ukończeniu budowy (w tiku lub cron)
- Blokada zakupu gdy odwiert już ma aktywny rurociąg

#### Panel gracza — widok rozszerzony

- Wyraźne oznaczenie odwiertów **bez rurociągu** na liście logistyki
- Zestawienie strat: ile ropy gracz traci przez zły stan rurociągów (zł/h)
- Ostrzeżenie o rurociągu wymagającym natychmiastowej naprawy (condition < 20%)
- Historia zdarzeń na konkretnym rurociągu (ostatnie N wpisów z `well_pipeline_events`)

#### Panel admina — braki

- **Ręczna naprawa/konserwacja** z poziomu admina (teraz jest tylko wymuszenie awarii i zbiorcza naprawa krytycznych)
- **Zmiana statusu** rurociągu przez admina (np. wymuś `suspended`, `disabled`)
- **Edycja parametrów** rurociągu (nadpisanie `condition_pct`, `transport_loss`, `degradation_rate_per_hour`)
- Filtrowanie historii incydentów po `pipeline_id` (teraz tylko per gracz/poziom/źródło)

#### Sabotaż

- Mechanizm sabotażu rurociągu — celowe zniszczenie przez gracza (gracza-złodzieja?) lub zdarzenie systemowe
- Brak w obecnej implementacji, wymaga osobnego projektowania

#### Balans i testy

- Przetestowanie progów degradacji dla wszystkich typów rurociągów przy różnych poziomach produkcji
- Weryfikacja proporcji incydentów pipe_micro/minor/medium w tiku (analogicznie do calibracji well incidents)
- Sprawdzenie czy `transport_loss` poprawnie wpływa na faktyczny przychód gracza w kalkulacji tiku

---
