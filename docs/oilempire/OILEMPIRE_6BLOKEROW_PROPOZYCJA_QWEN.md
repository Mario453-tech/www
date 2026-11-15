### ZAD.1 — Ropa w magazynach musi wejść do bazy podatku, ale z małym dyskontem i pełnym liczeniem do progów. Dyskonto 10% jest mniejsze niż oszczędność podatkowa, którą by dawało (0,08%/dzień × 10% = 0,008%/dzień), więc ucieczka gotówka→ropa się nie opłaca, a jednocześnie uwzględnia ryzyko cenowe zapasu. Podwójnego opodatkowania nie ma, bo podatek majątkowy jest jedynym podatkiem od ropy (brak royalty od wydobycia). Progi tarczy i Tieru liczą zapasy w 100%, żeby dyskont nie był drugą furtką.

```markdown
### 19.1.2 Baza podatku majątkowego: zapasy rzeczowe i wierzytelności

- **Cena referencyjna:** VWAP spot z ostatnich 24h (liczony w fazie 4 sweepa) per rodzaj surowca (ropa bazowa 250 PLN/bbl, produkty wg tabel §31), parametr `wealth_tax_price_source` w `game_balance`.
- **Wycena:** 90% ceny referencyjnej × mnożnik jakości (produkty). Opodatkowane: magazyny, bufory hubów/rurociągów (powyżej minimum operacyjnego) oraz ropa w tranzycie (cysterny, tankowce, rurociągi — wg właściciela w `oil_movements` na moment snapshotu).
- **Minimum operacyjne wolne od podatku:** line-pack rurociągów i do 10% pojemności bufora huba (fizycznie niezbędne do przepływu); limity w `game_balance`.
- **Brak podwójnego opodatkowania:** podatek majątkowy jest JEDYNYM opodatkowaniem ropy — nie ma royalty od wydobycia; każdy bbl płaci raz dziennie jako stan w snapshotcie.
- **B2B:** towary do momentu `delivery_receipt` = zapas sprzedawcy (90%). Po wydaniu: wierzytelność wchodzi do bazy sprzedawcy (90% nominału), a środki kupującego w `PLN_ESCROW` przypisane do tej płatności są WYŁĄCZONE z jego bazy — jeden składnik, jeden podatnik.
- **Progi tarczy (§19.2) i Tieru (§22.6):** zapasy liczone w 100% ceny referencyjnej — dyskont podatkowy nie działa przy progach; parkowanie kapitału w ropie nie opóźnia utraty tarczy ani nie zaniża Tieru.
- **Bilans ucieczki:** konwersja gotówki w ropę obniża bazę o 10%, co przy stawce krańcowej 0,08%/dzień daje oszczędność 0,008%/dzień — mniej niż dobowa zmienność spot; ucieczka nieopłacalna.
- **FTS:** `reason_code=WEALTH_TAX`, `from_account` gracza → `to_account=SYS_TAX`, `idempotency_key=wealth...D}`, `sweep_no`, `balance_version`; kwoty BIGINT w groszach; baza = snapshot 00:00 UTC (§22.6.1).
```

### ZAD.2 — Morale jako suma ważona sześciu składowych (wagi sumują się do 1,00) minus karne zdarzenia, wszystkie wejścia już istnieją w grze (salary_satisfaction z §4.1, tryb turbo hubów, rejestr incydentów, budżet §10, hr_bonus). Asymetryczne wygładzanie (−12/+6 na dobę) oddaje psychologię: morale spada szybko, odrasta powoli. Zdarzenia z §4.2 działają natychmiast na morale, a wzór wyznacza tylko trend — próg sporu <35 zostaje bez zmian, spójny z §5.

```markdown
### 4.3 Model liczbowy morale pracownika (0–100)

**Morfale docelowe** liczone raz na dobę (faza 6 sweepa) jedną funkcją `moraleTarget(Employee, Dept, Company)`:

$$\text{morale\_target} = \operatorname{clamp}\Bigl(0,100,\ 0{,}45\cdot\min(s,100) + 0{,}15\cdot W + 0{,}10\cdot R + 0{,}10\cdot B + 0{,}10\cdot H + 0{,}10\cdot U - P\Bigr)$$

- `s` = salary_satisfaction (§4.1, obcięte do 100);
- `W` (przeciążenie) = 100 − 40 × turbo_ratio (godziny hubów działu na turbo / doba, cap 1,0);
- `R` (świeżość podwyżki) = 100 jeśli ≤90 dni; liniowo do 30 przy 365 dniach; 0 powyżej;
- `B` (bezpieczeństwo) = max(0, 100 − 15 × incydenty_30d_działu − 40 × wypadek_śmiertelny_180d);
- `H` (budżet HR/BHP §10) = low 40 / standard 70 / high 100;
- `U` (uznanie) = min(100, 40×hr_bonus_90d + 30×szkolenie_180d + 30×udana_mediacja);
- `P` (kary zdarzeń): odrzucone żądanie −20 (odradza +4/dzień), porażka kontroferty −15 (+3/dzień), zwolnienie w dziale −8/os. przez 14 dni (cap −24), aktywny spór −10, cięcie budżetu działu −6 przez 14 dni.

**Wygładzanie dobowe:** `morale ← clamp(0, 100, morale + clip(morale_target − morale, −12, +6))`. Zdarzenia §4.2 (±20, +8) działają NATYCHMIAST na `morale`; wzór dzienny tylko kotwiczy trend.

**Dział i firma:** `morale_dept = round(0,7 × śr(morale pracowników działu) + 0,3 × śr(morale firmy))`; próg sporu i strajku: `morale_dept < 35` — bez zmian, spójne z §5.

Wagi, capy i tempa zaniku w `game_balance` (klucz `morale_model`, wersjonowane); deterministyczne — bez RNG.
```

### ZAD.3 — Ceny kotwiczone do ekonomii ataku z §7.1: eskorta (2% kursu, min 2 500) musi być wyraźnie tańsza niż oczekiwana strata z zasadzki (atak 25 000 na kurs ~125 000 PLN), inaczej nikt jej nie kupi. Konwój 5% i subskrypcje 35–60 tys./7 dni sprawiają, że przy ryzyku wpadki podbitym do 30–40% (drony +15 p.p.) sabotaż przestaje się agresorowi opłacać — ochrona działa odstraszająco, nie tylko blokująco. Wszystko jest ujściem kapitału do SYS_TREASURY, więc moduł domyka bilans faucets/sinks.

```markdown
### 7.2 Cennik i mechanika modułów ochrony (ProtectionService)

**Ścieżka zakupu:** panel Działu Prawnego → „Ochrona aktywów". Płatność z `PLN_CASH`, przy braku środków automatyczne dopełnienie z `PLN_BANK` (§24.5); konta gracza nigdy nie schodzą poniżej zera — przy niedoborze zakup odrzucony. Wartość referencyjna kursu = bbl ładunku × referencyjny spot 24h.

| Moduł | Cena | Czas trwania | Aktywacja i warunki |
|---|---|---|---|
| `basic_escort` | 2% wartości kursu, min. 2 500 PLN | jeden kurs | natychmiast przy zleceniu kursu; w pętli cystern flaga auto-odnowienia (cena per kurs) |
| `armed_convoy` | 5% wartości kursu, min. 8 000 PLN | jeden kurs | dyr. prawny ≥3 AKTYWNY przy zakupie i w trakcie kursu; montaż konwoju 60 min (zakup po starcie kursu działa od +60 min); odejście dyrektora = degradacja do poziomu eskorty, bez zwrotu |
| `drone_patrol` | 35 000 PLN / aktywum | 7 dni | korytarz trasy LUB odcinek rurociągu, max 10 aktywów; od następnego sweepa; +15 p.p. do ryzyka wpadki agresora (kanon) |
| `hub_security` | 60 000 PLN / hub | 7 dni | od następnego sweepa; blokuje `hub_valve_sabotage` (kanon) |

**FTS:** `reason_code=PROTECT_{ESCORT,CONVOY,DRONE,HUB}`, `from_account=PLN_CASH` (lub `PLN_BANK` dla dopełnienia) → `to_account=SYS_TREASURY` (ujście kapitału), `idempotency_key=protect:{module}:{asset_id}:{start_ts}`, `sweep_no`, `balance_version`; kwoty BIGINT w groszach.

**Interakcja z budżetem BHP (§10):** budżet `high` → −10% ceny wszystkich modułów; budżet `low` → +15% ceny. BHP wpływa na incydenty pracownicze, moduły na sabotaże — domeny się nie nakładają.

**Zwroty:** moduł kursowy zwracany w 100%, gdy kurs anulowany przed wyjazdem; subskrypcje bezzwrotne. Zwroty po stronie agresora za operacje `cancelled` (§7.1) bez zmian. Firma pod Tarczą Startową nie kupuje ochrony — tarcza i tak blokuje ataki.
```

### ZAD.4 — Pozwolenia wycenione proporcjonalnie do skali przedsięwzięć: P1 150 tys. przy odwiercie za ~1,2–2 mln to ~10% inwestycji, P2 500 tys. przy rafinerii 18–240 mln jest symboliczne — oba realnie bolą tylko przy niskiej wiarygodności, bo szansę zatwierdzenia steruje głównie ona. Łapówki są tańsze od legalnej ścieżki tylko w krótkim terminie: ryzyko wpadki rośnie z historią, a kara (−18 wiarygodności + 250 tys. + blokada 30 dni) przewyższa korzyść — szara strefa pozostaje świadomym wyborem ryzyka, jak przy EPC. Katalog „czego kupić nie można" dziedziczy §22.6, żeby łapówki nie stały się obejściem zakazów P2W.

```markdown
### 6A. Pozwolenia P1/P2 i łapówki (Czarny Rynek)

**Pozwolenia** (wniosek przez Dział Prawny; opłata `PLN_CASH` → `SYS_TREASURY`, `reason_code=PERMIT_FEE_{P1,P2}`):

| Pozwolenie | Opłata | Czas rozpatrzenia | Szansa zatwierdzenia |
|---|---|---|---|
| P1 (wydobycie/eksploatacja) | 150 000 PLN | 72h | clamp(20%, 98%, 70% + 0,5×(wiarygodność−70) − 3×flaga_szarej_strefy − 2×incydent_środowiskowy_180d) |
| P2 (rafineria/instalacje niebezpieczne) | 500 000 PLN | 7 dni | clamp(10%, 95%, 55% + 0,6×(wiarygodność−70) − 4×flaga_szarej_strefy − 5×incydent_środowiskowy_180d) |

Odmowa: 50% opłaty przepada (koszt rozpatrzenia), ponowny wniosek po 72h. **Odwołanie:** 25% opłaty, 48h, +2 p.p. za punkt `skill_law` dyr. prawnego (cap +10 p.p.); druga odmowa jest ostateczna, nowy wniosek po 7 dniach. RNG: seed HMAC `permit:{application_id}:verdict`.

**Łapówki** (panel Czarnego Rynku; płatność `PLN_CASH` → `SYS_MARKET_MAKER`, `reason_code=BRIBE_PAYMENT`; każda łapówka = +2 `black_market_score`):

| Łapówka | Koszt | Efekt | Ryzyko wpadki bazowe |
|---|---|---|---|
| `permit_accelerate` | 40% opłaty pozwolenia | pozostały czas rozpatrzenia × 0,5 | 22% |
| `penalty_mitigation` | 30% mandatu, min. 20 000 PLN | mandat administracyjny × 0,5 (raz na mandat) | 18% |
| `inspection_warning` | 25 000 PLN | alert 24h przed planowaną kontrolą | 12% |

**Czego kupić NIE można (kanon §22.6):** zajęcia i odblokowania odwiertu przez komornika (§17.2), wiarygodności, kasowania FTS/logów, zaliczenia egzaminu, bezkarności PvP.

**Ryzyko wpadki** = clamp(3%, 90%, baza − (skill_ethics−4) p.p. [od ethics 5, cap −6] − 0,5 p.p. × black_market_score [cap −8] + 10 p.p. [wpadka w 180 dni] + 15 p.p. [status „pod lupą"]). „Pod lupą": 3 wpadki w 180 dni → modyfikator przez kolejne 90 dni. Seed HMAC `bribe:{bribe_id}`.

**Skutek wpadki:** wiarygodność −18 (kanon) + mandat 250 000 PLN → `SYS_TREASURY` (`reason_code=BRIBE_PENALTY`) + proces sądowy z trwałym wpisem w Centrum Nadzoru (§18.1) + blokada całego menu łapówek na 30 dni; przyspieszone pozwolenie wraca na standardowy bieg. **Limity:** 1 łapówka / 24h na firmę, ten sam typ / 72h.
```

### ZAD.5 — Wybieram wariant (a): żadna zniżka nie skróci wiercenia 8–48h do 30 minut, więc tylko gotowy odwiert szkoleniowy spełnia obietnicę FTUE. Budżet się spina z zapasem: własny łańcuch 2 240 000 + OPEX pierwszego tygodnia ~205 000 + druga nitka ~1 400 000 = 3 845 000 z 4 000 000, a ropa ze szkoleniowego odwiertu (7 000 bbl × 250 = 1 750 000 w ~7 dni) samodzielnie finansuje drugą nitkę — gracz uczy się pełnej pętli i płynnie przechodzi na własne wydobycie. Odwiert wyczerpuje się po ~7,3 dnia (7 000/40 bbl/h = 175h), jest niezbywalny (anty-farmienie nowych kont) i wyłączony z baz Tieru/tarczy jako część pakietu — spójnie z §22.6.

```markdown
### 27.1 (poprawka) Odwiert szkoleniowy w pakiecie startowym

**Decyzja projektowa (wiążąca):** wariant (a) — pakiet startowy zawiera prezbudowany **odwiert szkoleniowy** (`starter_well`). Zniżki na zakupy nie spełniłyby obietnicy „pierwsza sprzedaż w 30 minut", bo wiercenie trwa 8–48h (§9.4) — fizyki nie da się przecenić.

**Parametry odwiertu szkoleniowego** (wyjątek z tabeli §9.5, flaga `starter_well`):
- status `active` od utworzenia konta; płytka warstwa; `base_rate` 40 bbl/h (kanoniczne minimum);
- zasobność 7 000 bbl → wyczerpanie po ~175h (~7,3 doby) bez krzywej deplecji (§9.2 nie stosuje się — próg 70% nieosiągalny przed końcem zasobu); OPEX standardowy;
- w pakiecie: rurociąg dopływowy polowy najtańszego tieru (klasa 250 000) oraz bufor huba wstępnie wypełniony 800 bbl — pierwszy kurs cysterną (500 bbl) startuje w pierwszej minucie gry, sprzedaż spot osiągalna w ~30 minut;
- po wyczerpaniu: status `depleted`; odwiert **niezbywalny** (zakaz sprzedaży B2B i aukcji — blokuje farmienie pakietów startowych), kasacja zwraca 10% kosztu wiercenia;
- wyłączony z podstawy Tieru (§22.6) i z progów tarczy (§19.2) jako część pakietu startowego; od 8. doby aktywo trwałe podlega podatkowi majątkowemu na zasadach ogólnych.

**Budżet pakietu (2 mln gotówka + 2 mln bank) — sprawdzony łańcuch własny:**

| Pozycja | Koszt (PLN) |
|---|---|
| Odwiert szkoleniowy + rurociąg polowy | 0 (w pakiecie) |
| Koncesja L1 na pierwszy region docelowy | 400 000 |
| Badanie geologiczne (48h) | 150 000 |
| Wiercenie (płytka warstwa, 8–16h) | 1 200 000 |
| Rurociąg dopływowy (min. z widełek §9.4) | 250 000 |
| 2 cysterny używane (2 × 120 000 — do kalibracji) | 240 000 |
| **Razem łańcuch własny** | **2 240 000** |
| OPEX tydzień 1: wydobycie 6 720 bbl × 12 + stały 300/h × 168h + pensje ~45 000 + paliwo 6 720 × 4 | ~205 000 |
| Druga nitka (koncesja 400 000 + badanie 150 000 + wiercenie 600 000 + rurociąg 250 000) | ~1 400 000 |
| **Suma zobowiązań pierwszego tygodnia** | **~3 845 000 ≤ 4 000 000** |

Bufor gotówkowy ~155 000 + przychód z odwiertu szkoleniowego (7 000 bbl × 250 = **1 750 000 PLN** w ~7 dni) pokrywa drugą nitkę i ewentualny suchy odwiert bez utraty płynności.

**FTUE (6 kroków, zaktualizowane):** 1. przegląd odwiertu szkoleniowego i KPI → 2. pierwszy kurs cysterną i sprzedaż spot → 3. wybór ścieżki rurociągu pod WŁASNĄ koncesję (nowy/używany/czarny rynek §11A.3) → 4. koncesja + badanie → 5. wiercenie i podłączenie → 6. zatrudnienie technika i pulpit. Nagrody krokowe ~100 000 PLN (`reason_code=tutorial`) bez zmian. Tryb nauki 48h bez zmian.
```

### ZAD.6 — Potwierdzam snapshot dzienny 00:00 UTC i rozszerzam go na jeden wspólny stół dla podatku, tarczy i Tieru — trzy konsumenci, jedno źródło, zero rozjazdów. Wyłączenie Kroplówki realizuję jako sumę wpłat DRIP_IN z 30 dni odejmowaną od majątku (zwykły indeks na FTS, bez znaczników na każdym groszu): to technicznie proste i zabija śnieżną kulę „kup GBBL dziś → Tier V jutro → większy limit → więcej GBBL", bo kapitał z Kroplówki podnosi Tier dopiero po 30 dniach. Spadek majątku w ciągu doby działa od następnego snapshotu (kanon), z wyjątkiem egzekucji/recovery, gdzie blokada jest natychmiastowa — zgodnie z zasadą sprawdzania stanów w momencie decyzji, nie w sweepie.

```markdown
### 22.6.1 Dzienny snapshot majątku i wyznaczanie Tieru Kroplówki

- **Tabela `net_worth_snapshots`:** `company_id`, `snapshot_date` (data UTC), `net_worth_grosze`, `starter_value_grosze`, `drip_30d_grosze`, `tier_base_grosze`, `tier`, `sweep_no`, `balance_version`; UNIQUE(`company_id`, `snapshot_date`). Jeden snapshot na dobę (00:00 UTC, faza 5 sweepa) zasila TRZECH konsumentów: podatek majątkowy (§19.1), tarczę (§19.2), Tier Kroplówki (§22.6) — brak rozjazdów między progami.
- **Podstawa Tieru:** `tier_base = net_worth − wartość pakietu startowego (kanon) − drip_30d`, gdzie `drip_30d` = suma wpłat PLN z konwersji GBBL→PLN z ostatnich 30 dni (suma wejść FTS `reason_code=DRIP_IN` na kontach firmy; indeks na `(to_account, reason_code, created_at)`). Kapitał pokonwersyjny starszy niż 30 dni liczy się normalnie — nie ma osobnego pola salda ani znaczników pochodzenia na każdym groszu.
- **Cel:** zakup GBBL i wymiana tego samego dnia NIE podnosi jutrzejszego limitu; anty-snieżna-kula (Kroplówka → majątek → wyższy Tier → większa Kroplówka) jest结构性owo zamknięta 30-dniowym oknem.
- **Potwierdzenie:** Tier obowiązuje z ostatniego snapshotu 00:00 UTC. Spadek majątku w ciągu doby: obcina limit **od następnego dnia** (kanon „zmiana tieru w dół obcina limit od następnego dnia" — bez zmian); wyjątek: wejście w `recovery_mode` lub Fazę 2 egzekucji (§17.2) blokuje wymianę NATYCHMIAST — każdy time-gate sprawdza stan w momencie decyzji, sweeper tylko materializuje powiadomienia (§23).
- **Równoległość:** max **1 żądanie oczekujące** na firmę; UNIQUE(`company_id`, `conversion_date_utc`) w księdze konwersji + `idempotency_key=drip:{...DD}` — równoległe kliknięcia dają dokładnie jedną wymianę na dobę (kanon 1/24h); duplikat = no-op z odpowiedzią „już wymieniono".
- **Wzrost Tieru w ciągu doby** (np. duży kontrakt): widoczny dopiero w następnym symfonicznym snapshotcie — symetrycznie ze spadkiem; limit przy przyjmowaniu żądania = zawsze ten z najnowszego snapshotu.
- Parametry (okno 30 dni, progi Tierów, limity GBBL) w `game_balance` (wersjonowane); zmiana parametrów wpływa na snapshoty od następnej doby, nigdy wstecz.
```
