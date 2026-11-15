### ZAD.1 — Baza podatku majątkowego
Ropa to aktywo płynne, ale nie gotówka — nie można jej opodatkować jak PLN (sprzedaż dopiero przed nami). Dlatego wycena po 80% średniej ceny spot z ostatnich 24h (konserwatywny dyskont 20% za niepłynność i spread sprzedaży) eliminuje ucieczkę w zapasy, ale nie karze normalnego buforowania. Wierzytelności B2B: środki w ESCROW kupującego są już w JEGO podstawie (PLN_ESCROW wprost w §19.1) — sprzedawca nie płaci podwójnie; jego należność to wartość pozabilansowa do momentu zwolnienia escrow. Ropa w tranzycie (cysterna w kursie) wchodzi do podstawy z datą wysyłki — nie znika z ksiąg na czas transportu. Podwójnego opodatkowania nie ma: podatek majątkowy dotyczy STANU posiadania, nie transakcji; podatek dochodowy od sprzedaży to osobny mechanizm (§19 nie definiuje CIT — doliczany przy rozliczeniu miesięcznym).

```markdown
### 19.1-A. Wycena ropy i produktów w podstawie podatku majątkowego (uzupełnienie)

**Zasada:** każda baryłka ropy surowej, półproduktu i paliwa w posiadaniu firmy w momencie naliczenia podatku (faza 5 ticka, 00:00 UTC) jest składnikiem podstawy opodatkowania.

**Cena jednostkowa do wyceny:**
- `valuation_price = ROUND(AVG(spot_close_24h) * 0.80, 2)` — średnia arytmetyczna cen zamknięcia giełdy spot z 24 okien godzinnych poprzedzających snapshot, przemnożona przez 0,80 (dyskont za niepłynność).
- Dla produktów rafinowanych (benzyna, olej napędowy, LPG): `valuation_price * product_multiplier` z `game_balance.products`, domyślnie 1.15–1.40.
- Cena z okna snapshotu jest niezmienna dla danego cyklu podatkowego — zapisywana w `tax_run.valuation_price`.

**Lokalizacje objęte wyceną:**
| Lokalizacja | Wchodzi do podstawy |
|---|---|
| Magazyn firmowy (`storage_tank`) | TAK — pełny stan |
| Bufor huba (`hub_buffer`) | TAK — pełny stan |
| Bufor odwiertu (`well_buffer`) | TAK — pełny stan |
| Cysterna w kursie (`tanker_in_transit`) | TAK — stan z chwili wysyłki (zapisany w `shipment.snapshot_bbl`) |
| Rurociąg (ropa w tranzycie między hubem a magazynem) | TAK — `pipeline_inflight_bbl` z `pipeline_flow_log` |
| Ropa sprzedana, jeszcze nieodebrana (tytuł u kupca) | NIE — własność przeszła z chwilą `fulfillment_id` |
| Escrow kupującego (środki B2B) | NIE u sprzedawcy — to zobowiązanie kupca, nie aktywo sprzedawcy |

**Wzór rozszerzonej podstawy (§19.1):**
```
podstawa = PLN_BANK + PLN_CASH + PLN_ESCROW_własne
         + 0.70 * SUM(aktywa_trwałe)
         + SUM(ropa_w_magazynach_i_buforach * valuation_price)
         + SUM(ropa_w_tranzycie * valuation_price)
```
```

### ZAD.2 — Wzór morale (0–100)
Salary_satisfaction z §4.1 daje 0–120 — to naturalny rdzeń, ale nie może dominować (pensja to nie wszystko). Przeciążenie huba (tryb turbo), czas bez podwyżki i zwolnienia w dziale to czynniki kumulatywne degradujące — każdy z osobną wagą, bo działają niezależnie. Premie i szkolenia działają jako jednorazowe boosty (impulsy) z półokresem rozpadu ~7 dni, żeby nie dało się „zakupić" morale na stałe. Współczynnik bezpieczeństwa spinający incydenty z §10 daje spójność między BHP a nastrojami — wypadek obniża morale w całym dziale, nie tylko u poszkodowanego. Wzór daje się policzyć w jednym przebiegu na `employee` złączeniem z `department`, `facility`, `hr_budget` i `incident_log`.

```markdown
### 4.0. Formuła Morale Pracownika (uzupełnienie §4)

**Definicja:** `morale` ∈ [0, 100], aktualizowane w fazie 6 ticka (co 5 min) dla każdego pracownika.

```
morale = CLAMP(0, 100,
    + 0.40 * salary_satisfaction_adj   // salary_satisfaction przeskalowane 0→100 (÷1.2)
    + 0.15 * (100 - overload_penalty)  // przeciążenie obiektu (0=brak, 100=turbo non-stop)
    + 0.15 * time_since_raise_score    // czas od ostatniej podwyżki
    + 0.10 * layoff_trauma             // wpływ zwolnień w dziale
    + 0.10 * hr_budget_score           // poziom budżetu HR (low=30, standard=60, high=90)
    + 0.10 * safety_score              // z §10: brak incydentów=100, poważny=-30/dobę
    + recent_impulse                   // premie/szkolenia — rozpad wykładniczy
)
```

**Składniki szczegółowo:**

| Składnik | Źródło danych | Sposób obliczenia |
|---|---|---|
| `salary_satisfaction_adj` | `employee.salary_satisfaction` (0–120) | `salary_satisfaction / 1.2` |
| `overload_penalty` | `facility.turbo_hours_7d / facility.operating_hours_7d * 100` | udział godzin turbo w ostatnich 7 dobach |
| `time_since_raise_score` | `DATEDIFF(NOW(), employee.last_raise_date)` | `CLAMP(0, 100, 100 - dni_bez_podwyżki * 0.55)` (180 dni → 0) |
| `layoff_trauma` | `department.layoffs_30d` | `CLAMP(0, 100, 100 - liczba_zwolnień_30d * 12)` (≥9 zwolnień → 0) |
| `hr_budget_score` | `hr_budget.level` (low/standard/high) | `low=30, standard=60, high=90` — stała per dział na dany cykl budżetowy |
| `safety_score` | `incident_log` z §10 | `CLAMP(0, 100, 100 - SUM(incident_severity * days_since_incident^-0.3))` |
| `recent_impulse` | `employee_bonus_log`, `employee_training_log` | `SUM(impulse_value * EXP(-dni_od_impulsu / 7))` — premia=+8, szkolenie=+5 |

**Tempo zmian:** `morale` zmienia się płynnie — różnica między wartością wyliczoną a bieżącą jest aplikowana z krokiem max ±3 pkt na tick (faza 6), co daje max ~864 pkt/dobę w skrajnym przypadku (realnie ~15–30/dobę przy normalnych wahaniach).

**Próg sporu:** `morale < 35` → faza 6 ticka podnosi flagę `dispute_risk` dla działu (spójne z §5 — Strajk). Dział ze średnią `morale < 35` wchodzi w stan `pre_dispute`.

**Uśrednianie:** dla progów działowych (strajk §5) liczona jest średnia arytmetyczna `morale` wszystkich pracowników działu. Pracownicy na urlopie/zwolnieniu są wyłączani z uśredniania.
```

### ZAD.3 — Cennik i mechanika ochrony (§7)
Basic_escort to usługa per kurs — cena stała, bo eskortuje się konkretny przejazd cysterny, a nie „bezpieczeństwo w ogóle". Armed_convoy wymaga dyr. prawnego 3+ (spec już to mówi) i kosztuje % wartości ładunku, bo ochrona uzbrojona skaluje się z ryzykiem — im cenniejszy ładunek, tym droższa eskorta. Drony i hub_security to abonamenty 7-dniowe (monitorowanie ciągłe), stąd cena ryczałtowa. Wszystkie usługi przechodzą przez Dział Prawny (to jego kompetencja wg §7) i mają czas aktywacji — eskorta natychmiastowa (przypisanie do kursu), konwój 2h (mobilizacja uzbrojonego zespołu). Budżet BHP (§10) obniża ceny ochrony (inwestycja w bezpieczeństwo się zwraca), a FTS rejestruje każdą transakcję z `reason_code=protection`.

```markdown
### 7.2. Cennik, Czas Trwania i Ścieżka Zakupu Ochrony (uzupełnienie §7)

**Ścieżka zakupu:** wszystkie moduły dostępne przez Dział Prawny (`/legal/protection`). Zakup natychmiastowy po spełnieniu wymagań; środki pobierane z `PLN_BANK`.

| Moduł | Cena | Czas trwania | Czas aktywacji | Wymaganie |
|---|---|---|---|---|
| `basic_escort` | **3 000 PLN** (stała) | **1 kurs** (wiązany z `shipment_id`) | natychmiast (przypisanie do kursu) | brak |
| `armed_convoy` | **6% wartości ładunku** (PLN wg `valuation_price`) | **1 kurs** | **2h** (czas mobilizacji) | Dyrektor Prawny ≥3 |
| `drone_patrol` | **12 000 PLN** (ryczałt) | **7 dni** kalendarzowych | **1h** (rozmieszczenie dronów) | brak |
| `hub_security` | **18 000 PLN** (ryczałt) | **7 dni** kalendarzowych | **30 min** (instalacja systemów) | brak |

**Ceny po uwzględnieniu budżetu BHP (§10):**
- `budget_level = low → brak zniżki`
- `budget_level = standard → -15% ceny`
- `budget_level = high → -30% ceny`

**Kody FTS:**
| Operacja | `from_account` | `to_account` | `reason_code` |
|---|---|---|---|
| Zakup eskorty/konwoju/dronów/huba | `PLN_BANK` | `SYS_TREASURY` | `protection_purchase` |
| Zwrot przy anulowaniu (cel chroniony) | `SYS_TREASURY` | `PLN_BANK` | `protection_refund_cancelled` |
| Zwrot 50% (cel sprzedał aktywo) | `SYS_TREASURY` | `PLN_BANK` | `protection_refund_partial` |

**Zasięg ochrony:**
- `basic_escort` / `armed_convoy`: przypisane do konkretnego `shipment_id` — chronią TEN kurs. Jeśli cysterna zmieni trasę lub ładunek, ochrona wygasa.
- `drone_patrol`: chroni wszystkie rurociągi i trasy drogowe firmy przed `road_ambush` i `road_partial_theft` (+15 p.p. do ryzyka wpadki agresora). Nie chroni hubów ani zaworów.
- `hub_security`: chroni wszystkie huby firmy przed `hub_valve_sabotage`. Nie chroni tras ani cystern.

**Interakcja z sabotażem:**
- `basic_escort` zmniejsza `road_partial_theft` o 40% (ryzyko sukcesu agresora: bazowe_ryzyko * 0.60)
- `armed_convoy` blokuje `road_ambush` całkowicie; zmniejsza `road_partial_theft` o 75% (ryzyko sukcesu: bazowe * 0.25)
- `drone_patrol`: +15 p.p. do `bazowe_ryzyko_wpadki` każdej operacji fizycznej (ambush/theft) przeciw chronionej firmie
- `hub_security`: +25 p.p. do `bazowe_ryzyko_wpadki` przy `hub_valve_sabotage`

**Wygasanie i odnowienie:** ochrona kursowa wygasa z chwilą `shipment.status = delivered`. Ochrona 7-dniowa wygasa o 00:00 UTC po upływie okresu. Brak automatycznego odnowienia — gracz otrzymuje alert 24h przed wygaśnięciem.
```

### ZAD.4 — Łapówki i pozwolenia P1/P2
Pozwolenia to bramka administracyjna — muszą mieć zdefiniowany koszt, czas i ścieżkę odwołania, żeby nie były „czarną skrzynką" blokującą postęp bez wyjaśnienia. Odmowa zależy od wiarygodności (firma niebudząca zaufania dostaje odmowy) i `black_market_score` (podejrzane firmy są pod lupą). Łapówki działają na zasadzie „wszystko ma cenę i ryzyko" — od przyspieszenia pozwolenia po ostrzeżenie o kontroli, ale zawsze z mechanicznym ryzykiem wpadki, gdzie skill_ethics dyrektora prawnego działa jak tarcza (im wyższa etyka, tym trudniej firmę przyłapać — paradoksalnie, etyczny prawnik lepiej zaciera ślady). Wpadka kosztuje nie tylko wiarygodność (−18), ale też mandat i potencjalną blokadę odwiertu — państwo nie odbiera majątku, ale zamraża operacje do wyjaśnienia.

```markdown
### 13A. Pozwolenia P1/P2 i System Łapówek (nowy moduł)

#### Pozwolenia P1 (koncesja na odwiert) i P2 (pozwolenie na rurociąg)

| Parametr | P1 (Koncesja) | P2 (Rurociąg) |
|---|---|---|
| Koszt urzędowy | **80 000 PLN** (→ `SYS_TREASURY`) | **25 000 PLN** (→ `SYS_TREASURY`) |
| Czas oczekiwania | **6–12h** (losowo, `seed=HMAC(firma:permit_slot)`) | **2–4h** |
| Warunek wydania | `company_credibility ≥ 55` ORAZ `active_violations = 0` | `company_credibility ≥ 40` |
| Odmowa | `credibility < 55` LUB zaległe mandaty > 100k PLN → `denied` z podaniem przyczyny | `credibility < 40` → `denied` |
| Odwołanie | koszt **15 000 PLN**, czas **24h**, szansa = `CLAMP(5%, 90%, 50% + 3% * skill_legal dyrektora)` | koszt **8 000 PLN**, czas **12h** |
| Przyspieszenie legalne | **30 000 PLN** → czas / 2 (jednorazowo na wniosek) | **10 000 PLN** → czas / 2 |
| `reason_code` FTS | `permit_p1_fee` / `permit_p1_appeal` / `permit_p1_expedite` | `permit_p2_fee` / `permit_p2_appeal` / `permit_p2_expedite` |

**Ponowny wniosek po odmowie:** cooldown 48h. Każda odmowa obniża `company_credibility` o 3 („firma notorycznie składa niekompletne wnioski").

---

#### System Łapówek (`bribery_service`)

**Dostępne cele łapówki:**

| Cel | Koszt (PLN, gotówka) | Efekt | Ryzyko wpadki (bazowe) |
|---|---|---|---|
| Przyspieszenie P1/P2 | 40 000 | czas pozwolenia → 1h (z pominięciem kolejki) | 25% |
| Umorzenie mandatu | 150% kwoty mandatu | mandat znika z rejestru, brak wpływu do SYS_TREASURY | 30% |
| Ostrzeżenie o kontroli | 25 000 | alert 6h przed kontrolą BHP/środowiskową (§10) | 15% |
| Odblokowanie zajętego odwiertu | 60 000 | zniesienie `seized` → `inactive` (przed rozstrzygnięciem sprawy) | 35% |
| Czyszczenie `black_market_score` | 80 000 | −15 pkt z `black_market_score` (jednorazowo na 30 dni) | 40% |

**Mechanika ryzyka wpadki:**
```
ryzyko = CLAMP(3%, 85%, bazowe_ryzyko - modyfikatory)

Modyfikatory (odejmowane od ryzyka):
  - 2% * skill_ethics dyrektora prawnego (etyczny prawnik lepiej kamufluje)
  - 5% jeśli black_market_score ≥ 60 (doświadczenie w szarej strefie)
  + 5% jeśli active_investigations > 0 (firma pod lupą)
  + 10% jeśli ta sama łapówka użyta w ciągu 7 dni (cooldown ignorowany → ryzyko rośnie)
```

**Skutek wpadki (RNG niezaliczony):**
- `company_credibility` **−18** (kanon)
- Mandat karny: **150% kosztu łapówki** → `SYS_TREASURY` (`reason_code = bribery_fine`)
- Wpis w `violation_log` z `type = bribery`
- Blokada wszystkich pozwoleń na 7 dni
- Jeśli dotyczyło odwiertu: odwiert → `seized` na 72h (postępowanie wyjaśniające)
- Alert GM (do ręcznej weryfikacji przy powtarzających się wpadkach)

**Limity:**
- Max 3 łapówki na dobę na firmę
- Cooldown 2h między łapówkami
- Ten sam cel (np. przyspieszenie P1) — max 1 raz na 48h

**Kody FTS:**
| Operacja | `from_account` | `to_account` | `reason_code` |
|---|---|---|---|
| Opłacenie łapówki | `PLN_CASH` (wymóg — tylko gotówka) | `SYS_TREASURY` (przy wpadce przekierowanie z konta prowizji) | `bribery_payment` |
| Mandat za wpadkę | `PLN_BANK` | `SYS_TREASURY` | `bribery_fine` |

**Proces techniczny:**
1. Gracz wybiera cel → system sprawdza limity/cooldown → pobiera gotówkę → zapisuje w `bribery_log` z `status=pending_roll`.
2. Faza 8 ticka: RNG z seedem `HMAC(firma:cel:bribery_slot)` przeciw `ryzyko`. 
3. Sukces → `bribery_log.status = resolved`, efekt aplikowany natychmiast.
4. Porażka → `bribery_log.status = exposed`, skutki wpadki aplikowane, FTS z `reason_code=bribery_fine`.
```

### ZAD.5 — Starter kit / naprawa FTUE
Wybieram opcję (a): prezbudowany odwiert szkoleniowy. Tutorial obiecuje „pierwszy odwiert → cysterna → sprzedaż w 30 minut" — nie da się tego zrealizować z pełnym lejkiem §9.4 (badanie 48h, wiercenie godziny, rurociąg). Dociąganie samouczka do istniejących aktywów (opcja b) wymagałoby tylu wyjątków w kodzie (zniżka startowa, skrócone czasy, pominięcie etapów), że powstałby osobny tor logiki tylko dla tutorialu — kruchy i trudny w utrzymaniu. Odwiert szkoleniowy jest czysty: to normalny odwiert o obniżonych parametrach, już podłączony, który po ~7 dniach się wyczerpuje — gracz uczy się na prawdziwym obiekcie, a potem musi zbudować własny. Pakiet 4 mln PLN musi pokryć cysternę, pierwszy własny odwiert (już po tutorialu) i OPEX — poniższe wyliczenie pokazuje, że wystarcza z zapasem.

```markdown
### 27.1-A. Pakiet Startowy i Odwiert Szkoleniowy (uzupełnienie §27.1)

**Decyzja projektowa: prezbudowany odwiert szkoleniowy (opcja A).**

Odwiert szkoleniowy jest normalnym rekordem `oil_wells` z polem `is_training = true` — nie jest to osobny byt, tylko odwiert o parametrach startowych. Gracz wchodzi w posiadanie odwiertu w kroku 1 tutorialu (już połączony rurociągiem polowym).

**Parametry Odwiertu Szkoleniowego:**
| Parametr | Wartość | Uzasadnienie |
|---|---|---|
| `name` | "Odwiert Szkoleniowy P-0" | stała nazwa, nieedytowalna |
| `base_rate` | 35 bbl/h | niska wydajność (minimum zakresu 40–160, obniżone dla szkolenia) |
| `stratum` | 1 (płytka warstwa) | najniższy koszt wiercenia, szybkie zużycie |
| `reserves_initial` | 4000 bbl | wyczerpie się po ~114h ciągłej pracy (~5 dni non-stop, realnie 7–8 dni z przestojami) |
| `pipeline_id` | wbudowany rurociąg polowy (typ 1) | 200 m, przepustowość 50 bbl/h, `wear_level = 0` |
| `status` | `active` od razu | bez etapu `awaiting_connection` |
| `construction_cost` | 0 PLN (już opłacony) | nie wchodzi do podstawy podatku (wartość księgowa = 0) |
| `is_training` | `true` | nie można sprzedać, nie można rozbudować, nie podlega zajęciu komorniczemu |

**Bilans startowy (cel: tutorial w 30 min + zapas na pierwszy własny odwiert):**

```
Pakiet startowy:                    4 000 000 PLN
  gotówka (PLN_CASH):               2 000 000 PLN
  bank (PLN_BANK):                  2 000 000 PLN
  magazyn:                         20 000 bbl pojemności (pusty)
  odwiert szkoleniowy:              już podłączony, base_rate 35 bbl/h
  wiarygodność:                     70

Koszty tutorialu (kroki 1-3):
  cysterna (500 bbl, używana):       ~180 000 PLN  (używana, nie nowa — gracz uczy się rynku wtórnego)
  paliwo na pierwszy kurs (50 km):    ~2 000 PLN    (4 PLN/bbl * 500 bbl — nie, to kurs za transport. Korekta: paliwo ~200 PLN)
  --------------------------------
  Zostaje:                          ~3 818 000 PLN 

Koszty pierwszego własnego odwiertu (po tutorialu, w ciągu 7 dni):
  koncesja P1:                         80 000 PLN
  badanie geologiczne:                 25 000 PLN  (szacunek — tabela 9.5, stratum 2)
  wiercenie (stratum 2, tier 1):     350 000 PLN  (szacunek z tabeli 9.5)
  rurociąg polowy 500m (nowy):        400 000 PLN  (środek zakresu 250k–3M, krótki dystans)
  cysterna (druga, opcjonalnie):             0 PLN  (jedna wystarczy na początek)
  --------------------------------
  CAPEX łącznie:                     855 000 PLN

OPEX pierwszych 14 dni:
  pensje (5 pracowników startowych):  ~90 000 PLN  (średnia 3 000 PLN/mc * 5 os. * 14/30)
  paliwo, energia, pompy:             ~30 000 PLN
  --------------------------------
  OPEX łącznie:                      ~120 000 PLN

  CAPEX + OPEX:                      ~975 000 PLN
  Zostaje po 14 dniach:            ~2 843 000 PLN — KOMFORTOWY ZAPAS
```

**Modyfikacje tutorialu pod odwiert szkoleniowy:**

Kroki FTUE (30 min):
1. **Odwiert już działa** — gracz widzi pulpit odwiertu, `base_rate` 35 bbl/h, bufor 200 bbl. Tooltip: „To Twój odwiert szkoleniowy. Produkuje 35 baryłek na godzinę. Zobacz, jak ropa płynie do bufora."
2. **Rurociąg polowy** — podświetlony jako połączony. Tooltip wyjaśnia, że bez rurociągu odwiert nie produkuje. Gracz klika „Podgląd rurociągu" — widzi parametry.
3. **Zakup cysterny** — gracz kupuje używaną cysternę z listy. Wyjaśnienie rynku wtórnego i parametrów (pojemność, zużycie).
4. **Pierwszy kurs** — cysterna ładuje ropę z bufora odwiertu (max 200 bbl naraz przy 35 bbl/h — tutorial czeka ~3 min na pierwsze 100 bbl, z animacją napełniania).
5. **Sprzedaż spot** — gracz sprzedaje ropę na giełdzie. Nagroda krokowa +50 000 PLN (`reason_code=tutorial_step5`).
6. **Zatrudnienie technika i KPI** — gracz zatrudnia pierwszego pracownika, widzi pulpit. Nagroda końcowa +50 000 PLN. Odwiert szkoleniowy pracuje dalej — gracz ma ~7 dni na zbudowanie własnego pierwszego odwiertu.

**Wygaśnięcie odwiertu szkoleniowego:**
- Gdy `cumulative_production >= reserves_initial` (4000 bbl) → status `depleted`.
- Automatyczny alert 48h przed wyczerpaniem: „Odwiert szkoleniowy wyczerpie się za ~2 dni. Czas na własną koncesję!"
- Po wyczerpaniu: odwiert zostaje w księgach jako `depleted`, nie można go reaktywować. Nie blokuje slotu koncesyjnego (nie wymagał koncesji).
```

### ZAD.6 — Snapshot Tieru Kroplówki
Snapshot na 00:00 UTC to jedyna sensowna opcja — Tier nie może skakać w ciągu doby przy każdym przelewie, bo gracze graliby w „optymalizację okienka". Emisja GBBL→PLN (Kroplówka) NIE może powiększać podstawy Tieru — to byłaby rekurencja: wyższy Tier → większa kropla → jeszcze wyższy Tier. Technicznie najczystsze jest osobne pole `net_worth_ex_drip` wyliczane razem z snapshotem i przechowywane jawnie (nie „odejmowanie sumy 30 dni" które jest kruche przy zmianach Tieru). Spadek majątku w ciągu doby: Tier w dół dopiero od następnego snapshotu — to daje przewidywalność i zapobiega panicznemu wyprzedawaniu aktywów przed 23:59.

```markdown
### 22.6-A. Mechanika Snapshotu Tieru i Wyłączenia Kroplówki (uzupełnienie §22.6)

**Snapshot:**
- Tier firmy wyliczany jest **raz na dobę**, w fazie 5 ticka, na podstawie stanu ksiąg z **00:00:00 UTC** tego samego dnia kalendarzowego.
- Obliczona wartość Tieru (I–V) zapisywana jest w `company_tier_log` i obowiązuje przez **pełne 24h** — do następnego snapshotu.
- Zmiana majątku w trakcie doby (sprzedaż ropy, zakup odwiertu, otrzymanie przelewu) **nie zmienia Tieru** do kolejnego snapshotu.

**Podstawa wyliczenia majątku netto dla Tieru:**
```
net_worth = PLN_BANK + PLN_CASH + PLN_ESCROW_własne
          + 0.70 * SUM(book_value_aktywów_trwałych)
          + SUM(ropa * valuation_price)   -- wg §19.1-A
          - SUM(zobowiązania_wymagalne)   -- kredyty §17, mandaty, kary sądowe
          - net_worth_ex_drip              -- KOREKTA: wyłączenie emisji z Kroplówki
```

**`net_worth_ex_drip` — mechanizm wyłączenia:**
- `net_worth_ex_drip` to wartość przechowywana w `companies.drip_excluded_pln` (BIGINT, grosze), aktualizowana przy KAŻDEJ konwersji GBBL→PLN.
- Przy konwersji: `drip_excluded_pln += kwota_konwersji_w_PLN` (zanim PLN trafi na `PLN_BANK`).
- W momencie snapshotu Tieru (00:00 UTC): `net_worth` pomniejszane jest o bieżącą wartość `drip_excluded_pln`.
- **Starzenie się wyłączenia:** co 30 dni od konwersji, odpowiednia część `drip_excluded_pln` jest zerowana (PLN z Kroplówki „wtapia się" w majątek po miesiącu — nie można go wiecznie wyłączać): `drip_excluded_pln -= SUM(konwersji_sprzed_30_dni)`. Mechanizm realizowany w fazie 5 ticka, przed snapshotem.

**Uzasadnienie 30-dniowego okna:** Kroplówka to zastrzyk gotówki — po miesiącu pieniądze te zostały już wydane lub zainwestowane i nie ma sensu dłużej ich wyłączać. 30 dni to horyzont jednego cyklu rozliczeniowego VIP.

**Limit równoległych żądań konwersji:**
- Max **1 aktywne żądanie** konwersji GBBL→PLN na firmę w danym momencie (nie tylko 1 na dobę — tylko jedno może być „w toku").
- Żądanie jest atomowe: `check_limit → debit_GBBL → credit_PLN → update_drip_excluded` w jednej transakcji.
- `idempotency_key=*** company_id, ':', DATE_FORMAT(UTC_TIMESTAMP, '%Y%m%d'))` — klient może ponawiać bezpiecznie, drugie żądanie w tej samej dobie zwróci wynik pierwszego.

**Spadek majątku w ciągu doby:**
- Tier w dół dopiero od **następnego snapshotu** (00:00 UTC kolejnego dnia).
- Przykład: firma o majątku 210M PLN (Tier IV) traci odwiert wart 150M PLN o 14:00. Do 00:00 UTC wciąż obowiązuje Tier IV (limit Kroplówki 200 GBBL/dobę, sloty premium 2). O 00:00 UTC snapshot pokazuje 60M PLN → Tier III od tego momentu.
- Wyjątek: `recovery_mode` (§17.2) → Tier natychmiast obcinany do I, Kroplówka zablokowana (to nie podlega logice snapshotu).

**Zapis techniczny:**
```sql
-- Uproszczony widok snapshotu
INSERT INTO company_tier_log (company_id, snapshot_date, net_worth, net_worth_ex_drip, tier, calculated_at)
SELECT
    c.id,
    CURDATE(),
    -- majątek netto wg formuły
    (c.pln_bank + c.pln_cash + c.pln_escrow
     + 0.70 * COALESCE(av.book_value, 0)
     + COALESCE(oil.valuation, 0)
     - COALESCE(liab.total, 0)) AS net_worth,
    c.drip_excluded_pln AS net_worth_ex_drip,
    -- wyznacz Tier z net_worth - net_worth_ex_drip
    CASE
        WHEN (net_worth - c.drip_excluded_pln) < 1000000000 THEN 1   -- <10M
        WHEN (net_worth - c.drip_excluded_pln) < 5000000000 THEN 2   -- 10-50M
        WHEN (net_worth - c.drip_excluded_pln) < 20000000000 THEN 3  -- 50-200M
        WHEN (net_worth - c.drip_excluded_pln) < 100000000000 THEN 4 -- 200M-1B
        ELSE 5
    END AS tier,
    UTC_TIMESTAMP()
FROM companies c
LEFT JOIN (...) av ON ...
LEFT JOIN (...) oil ON ...
LEFT JOIN (...) liab ON ...
WHERE c.id = ?;
```
```
