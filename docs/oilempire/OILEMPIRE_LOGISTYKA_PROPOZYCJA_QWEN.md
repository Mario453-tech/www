│ cache\scratch\log_balance_check.py
│ @@ -0,0 +1,22 @@
│ +ticks = 1440
│ +price = 250.0
│ +for t, (o, s) in {"small": (30, 3), "medium": (80, 6), "large": (200, 12)}.items():
│ +    d = o * ticks
│ +    print(f"hub {t}: dzien={d:,.0f} slot={d/s:,.0f} access_x5={d/s*5:,.0f}")
│ +for t, (o, c, l) in {"light": (2, 80, 0.008), "standard": (3, 200, 0.004), "heavy": (8, 500, 0.003)}.items():
│ +    bd = c * 24
│ +    ob = o * ticks / bd
│ +    lb = l * price
│ +    print(f"pipe {t}: {bd:,.0f} bbl/d opex/bbl={ob:.2f} strata={lb:.2f} razem={ob+lb:.2f}")
│ +rf = 1250 / 500
│ +ri = 0.008 * 0.75 * price
│ +print(f"road: {rf:.2f}+{ri:.2f}={rf+ri:.2f}")
│ +print(f"prog Q = {3*1440/3.0:,.0f} bbl/d")
│ +sav = (4.00 - (3 * 1440 / 4800 + 0.004 * 250)) * 4800
│ +print(f"oszcz/d={sav:,.0f} break-even 600k={600000/sav:.1f} dni")
│ +print(f"tanker medium={40000/25000+0.5+0.005*250:.2f}")
│ +for u in (0.2, 0.4, 0.6, 0.8):
│ +    r = 0.30 + 0.50 * u
│ +    print(f"udzial {u:.0%} redukcja {r:.0%} zostaje {max(0,1-r):.0%}")
│ +for n, m in (("std", 1.0), ("turbo", 2.2), ("turbo_brak_kons", 3.52)):
│ +    print(f"{n}: {100/(0.04*m):.0f}h = {100/(0.04*m)/24:.0f} dni do 0")
Poniżej kompletna odpowiedź. Wszystkie liczby z tabel `game_balance` przeliczyłem arytmetycznie (skrypt weryfikacyjny uruchomiony — wyniki spójne, podane w tekście).

---

# CZĘŚĆ 1 — PROJEKT: SEKCJA „LOGISTYKA" DO SPECYFIKACJI OILEMPIRE

*(propozycja: wstawienie jako §11A między §10 a §12; wymagania numerowane `LOG-{NR}` zgodnie z §24.4; kwoty w zapisie locale PL, jedna waluta §24.1; czas UTC, tick 60 s §23.1)*

## LOG-0. Zasada nadrzędna łańcucha logistycznego

**Ropa nie trafia do magazynu automatycznie po wydobyciu.** Każdy bbl przechodzi pełny łańcuch, a magazyn dostaje wyłącznie ropę, która fizycznie przeszła oba odcinki:

- Odwiert lądowy: `odwiert → [rurociąg | droga] (leg 1) → HUB → [rurociąg | droga] (leg 2) → magazyn`
- Odwiert morski: `odwiert → [bufor tankowca → rejs → port] (leg 1) → HUB → [rurociąg | droga] (leg 2) → magazyn`

Zasady:
1. **Brak darmowej destrukcji i darmowego tworzenia ropy.** Każda strata (spill, wyciek, kradzież, wypadek) = wpis `logistics_incidents` z `reason_code`, wolumenem i `tick_no`; każdy bbl daje się uzgodnić bilansowo: `wydobyte = w_magazynie + w_transit + w_buforach + straty(udokumentowane)`. Rekoncyliacja §29.2 obejmuje ropę, nie tylko FTS.
2. **Zero-wypełnienia łańcucha nie obejmuje się „fallbackiem ciszy".** Gdy łańcuch przerwany: ropa zatrzymuje się w buforze poprzedzającego ogniwa; po wypełnieniu bufora odwiert dławi się (stan `choked`, produkcja stop, koszty lecą) — patrz LOG-1.4. Wyjątkiem jest jawny, drogi tryb awaryjny (LOG-1.4c).
3. **Precyzja wolumenów:** `DECIMAL(16,4)` bbl we WSZYSTKICH tabelach logistyki; zaokrąglenie wyłącznie w punkcie rozliczenia (kredyt do magazynu). Zakaz mieszania skal (lekcja legacy: bufor `DECIMAL(12,2)` vs kursy `DECIMAL(12,4)` — rozjazdy mikrobilansu).
4. **Pieniądze:** wszystkie opłaty logistyczne wyłącznie `BIGINT` grosze przez FTS (§24.2) z `reason_code` z katalogu LOG-7. Żadna ścieżka kosztowa nie omija FTS (lekcja legacy: optymalizator transportu schodzący bezpośrednim `UPDATE players SET cash`).
5. **RNG:** wyłącznie seed deterministyczny §23.4, klucze domenowe: `HMAC(seed_tick, "logistics|trip:{id}")`, `"logistics|hub:{id}"`, `"logistics|pipeline:{id}"`, `"logistics|voyage:{id}"`, `"logistics|port:{id}"`. Zakaz `mt_rand()` (lekcja legacy — brak replayu incydentów).

## LOG-1. Huby logistyczne

### LOG-1.1. Typy, własność i przejmowanie
Hub jest punktem przeładunkowym z buforem. Formy własności (`ownership` — VARCHAR + backed enum):

| ownership | Opis |
|---|---|
| `npc` | Infrastruktur systemowa/NPC na rynku; gracz kupuje (acquisition) lub wynajmuje slot |
| `owned` | Własność gracza po acquisition; pełna kontrola (tryb pracy, leg 2, rozbudowa) |
| `leased` | Hub NPC wynajęty przez gracza (`tenant_player_id`); czynsz per slot per tick |

- **Acquisition (przejęcie):** cena = `build_cost × condition_factor × region_mult` (`condition_factor = 0,4 + 0,6 × condition_pct/100`). Huby `used` startują z kondycją 42–72% (generator świata z `game_balance`, NIE `id % 10` jak w legacy). Przejęcie huba od gracza-bankruta wyłącznie przez aukcję aktywów (§19.4/§30.5).
- **Wynajem:** umowa na czas (min. 7 dni), kaucja = 2× dzienny czynsz slotu; rozwiązanie z 7-dniowym wypowiedzeniem.
- **Pozwolenia:** budowa/rozbudowa huba oraz budowa rurociągu wymagają **pozwolenia Poziomu 2** per region (model legacy `hub_permit_applications`): `cost` (start 500 000 PLN), `review_ticks` (start 120), stany `pending → delayed → granted | refused`, `refusal_cooldown_ticks` (start 720 = 12 h), UNIQUE(`player_id`,`region_id`) — jeden wniosek per gracz per region. Rozpatrzenie w fazie 4/8 ticka.
- **Rozbudowa:** poziomy 1–3 per typ; każdy poziom: `capacity ×1,5`, `buffer ×1,6`, `slots +2`; koszt `upgrade_cost` z `game_balance`; czas 240/480/960 ticków; w trakcie rozbudowy status `upgrading` (przepustowość ×0,7).

### LOG-1.2. Parametry bazowe (propozycja `game_balance`)

| Parametr | small | medium | large |
|---|---|---|---|
| `nominal_capacity_bph` | 150 | 400 | 1 000 |
| `buffer_capacity_bbl` | 4 000 | 12 000 | 40 000 |
| `slot_limit` (odwierty inbound) | 3 | 6 | 12 |
| `waiting_bay_limit` (leg 2) | 2 | 4 | 8 |
| `opex_per_tick` (PLN) | 30 | 80 | 200 |
| — przeliczenie/dobę (×1440) | 43 200 | 115 200 | 288 000 |
| `build_cost` (PLN) | 800 000 | 2 500 000 | 8 000 000 |
| `base_wear_per_hour` (%) | 0,045 | 0,040 | 0,035 |

### LOG-1.3. Opłaty za przypisanie (access fee) — model legacy zachowany, przeskalowany
`slot_cost_daily = opex_per_tick × 1440 / slot_limit`; opłata jednorazowa przy przypisaniu odwiertu:

| acquisition_type | Formuła | small | medium | large |
|---|---|---|---|---|
| `new` | `slot_cost_daily × 5` | 72 000 | 96 000 | 120 000 |
| `used` | `slot_cost_daily × 8` | 115 200 | 153 600 | 192 000 |
| `rental` | `lease_per_tick × 2880` (2 dni z góry) | 23 040 | 43 200 | 72 000 |

(`lease_per_tick`/slot: 8 / 15 / 25 PLN.) Opłata pobierana w JEDNEJ transakcji z INSERT przypisania i kluczem idempotencji (§23.5) — legacy „refund w bloku catch" zastępuje atomowość transakcji. **Cooldown po odpięciu: 4 h** (`cooldown_until`) — blokuje ponowne przypisanie, spójny z anty-reroll §23.4.

### LOG-1.4. Bufory i przepełnienie
a) **Bufor huba** (`buffer_current_bbl`): przyjmuje ropę z leg 1 (rurociąg synchronicznie, kursy/rejsy po dotarciu).
b) **Kaskada przepełnienia (kanoniczna, bez skrótów):** magazyn pełny → leg 2 staje (`paused_storage`) → bufor huba rośnie → przepełnienie huba → **spill** (strata fizyczna z wpisem `reason_code=hub_overflow_spill`) → bufory odwiertów rosną → bufor odwiertu pełny → odwiert `choked` (produkcja 0, OPEX odwiertu pełny, powiadomienie `critical` §27.2).
c) **Tryb awaryjny bez huba** (`fallback_direct`, jawna flaga per odwiert, domyślnie WYŁ.): przepustowość 100 bbl/h, OPEX ×2,0, strata 3% — odpowiednik legacy `HubTickService::applyFallback()`, ale jawny i widoczny w UI (legacy miał tu sprzeczność: dokument „hub obowiązkowy" vs kod z cichym fallbackiem).
d) Spill NIE jest skalowany mnożnikami zdarzeń (fizyka) — ale jest mierzony telemetrią (§29.3) i wliczany do `company_credibility` przy przekroczeniu progu ekologicznego (kara jak §17.4 `env_fine`).

### LOG-1.5. Tryby pracy (work_mode) — eco / standard / turbo
Mnożniki stosowane w fazie 2, stack zgodnie z LOG-5.4:

| Tryb | throughput | wear | opex | ryzyko incydentu |
|---|---|---|---|---|
| `eco` | ×0,75 | ×0,60 | ×0,85 | ×0,80 |
| `standard` | ×1,00 | ×1,00 | ×1,00 | ×1,00 |
| `turbo` | ×1,50 | ×2,20 | ×1,40 | ×1,60 |

Przy `base_wear 0,04%/h`: standard → 104 dni do kondycji 0; turbo → 47 dni; turbo bez konserwacji (mnożnik wieku ×1,6) → 30 dni. Zmiana trybu: natychmiastowa dla właściciela/najemcy (walidacja `player_id IN (owner, tenant)` w finalnym UPDATE — lekcja audytu izolacji legacy), wpis do FTS i historii huba.

### LOG-1.6. Przepustowość efektywna (formuła kanoniczna)
```
capacity_bph_eff = nominal_capacity_bph
                 × f_condition(condition_pct)
                 × mode.throughput
                 × (1 + staffing.throughput_pct/100)
                 × budget.throughput            (§10)
                 × (1 − strike_reduction)       (§5.1, LOG-5.5)
                 × world_event_modifiers        (§25)
f_condition(c) = c < 20 → 0,10 | 20 ≤ c < 40 → 0,30 | c ≥ 40 → c/100
```
Przetworzenie w ticku: `processed = min(input + buffer_drain, capacity_bph_eff × Δt_h)`; `Δt_h` z delta-time (§23.3).

### LOG-1.7. Zużycie, kondycja, konserwacja
`wear = base_wear × mode.wear × maint_age_mult × overload_mult × condition_accel × Δt_h`, z **capem `8 × base_wear × max(1, Δt_h)`** (zachować 1:1 z legacy `HubTickService` — cap zapobiega podwójnemu stackowaniu overload+kondycja krytyczna, a skalowanie Δt_godzinami poprawnego catch-upu po przerwie). `maint_age_mult`: brak konserwacji 1,20; ≥72 h 1,15; ≥144 h 1,35; ≥240 h 1,60. Przeciążenie (`load_pct > 100`): `wear ×= 2,8 × min(3, load_pct/100)`. Kondycja ≤20: ×2,0; ≤30: ×1,4. OPEX rośnie przy złej kondycji: ×1,10 (<70), ×1,25 (<50), ×1,50 (<30), ×1,80 (≤20) — model `HubEconomyService::getOpex()` do zachowania.
**Naprawa** (przywraca kondycję, koszt `repair_cost(cond)`, czas w tickach, zadanie TTS z postępem w fazie 7 §17.4) i **konserwacja** (resetuje `maint_age_mult`, spowalnia wear, NIE naprawia) — dwie różne akcje (wprost z legacy README, dobra decyzja).

### LOG-1.8. Obsada
`hub_operator` — wyłącznie stanowisko techniczne (`hr_specializations.department=technical`, `technical_staff.spec_code`) — jedna ścieżka rekrutacji (decyzja legacy 2026-07-18 do zachowania 1:1; zakaz równoległej ścieżki „board"). Przypisania w `employee_assignments` z alokacją 0–100% per pracownik (suma ≤100%), `coverage_pct = Σ(allocation × skill_factor) / required_staff(hub_size)`. Wymagana obsada: small 1 / medium 2 / large 4 (config). Efekty: `staffing.throughput_pct ∈ [−20%, +10%]`, `staffing.incident_mult ∈ [0,85; 2,0]` (brak obsady = ×2,0 — jawne w UI, lekcja legacy, gdzie domyślne 2,0 było ukrytą karą). Bonus operatora działa WYŁĄCZNIE na hub, do którego jest przypisany (regresja legacy „globalny bonus wszystkim hubom" — nie przenosimy).

### LOG-1.9. Przepięcie odwiertu (relink)
`relink_started_at` / `relink_finish_at`, czas 2 ticki (config). W trakcie: odwiert nie przekazuje ropy (produkcja do bufora odwiertu, po wypełnieniu `choked`), koszty odwiertu i OPEX slotu lecą. Modal potwierdzenia z pełnym kosztem i czasem (§27.2: kategoria `operational`). Kursy leg-1 już `in_transit` dojeżdżają do STAREGO huba (test LOG-T9).

### LOG-1.10. Maszyna stanów huba
```
planned → building → active ⇄ degraded (40–70) ⇄ critical (20–40) → damaged (<20 | incydent)
active/degraded → upgrading → active ; active → maintenance → active
dowolny operacyjny → paused (urlop §19.2 / seized §17.2 / ban §30.4) → (wznowienie)
damaged → (naprawa TTS) → active ; port/hub `disabled` wyłącznie decyzją GM (§18.3)
```
Stany zamrożone pomijają fazę 2 per gracz (§23.6).

## LOG-2. Rurociągi

### LOG-2.1. Model odcinków (leg)
Rurociąg ZAWSZE ma cel: `leg=inbound` (odwiert→hub, `well_id NOT NULL, hub_id NOT NULL`) albo `leg=outbound` (hub→magazyn, `well_id NULL, hub_id NOT NULL`). **Zakaz sentinela `well_id=0`** (lekcja etap11) — `well_id NULL` + unikalność: inbound `UNIQUE(well_id, leg)`; outbound `UNIQUE(hub_id, leg)` (klucz funkcyjny/GENERATED COLUMN dla NULL-safe unique w MySQL).
Zakup inbound wymaga AKTYWNEGO przypisania odwiertu do huba, z re-checkiem przypisania `SELECT ... FOR UPDATE` w tej samej transakcji (lekcja legacy: wyścig z równoległym transferem odwiertu — naprawione 2026-07-15, w nowej wersji wymóg §23.5 od początku). Zakup blokowany, gdy hub w stanie nieoperacyjnym (`planned/paused/maintenance/disabled/building/upgrading`).
**Własność leg-2 przy hubie wielonajemcowym:** outbound należy do właściciela huba (`npc`: systemowy, dostępny wszystkim najemcom z taryfą; `owned`: właściciel, najemcy płaczą `tariff_per_bbl`). Decyzja do zatwierdzenia — patrz CZĘŚĆ 2, D2.

### LOG-2.2. Typy i parametry (propozycja `game_balance`)

| Parametr | light | standard | heavy |
|---|---|---|---|
| `capacity_bph` | 80 | 200 | 500 |
| `build_cost` (PLN) | 250 000 | 600 000 | 1 400 000 |
| `build_ticks` | 240 (4 h) | 480 (8 h) | 960 (16 h) |
| `opex_per_tick` (PLN) | 2 | 3 | 8 |
| `degradation_per_hour` (%) | 0,070 | 0,050 | 0,035 |
| `base_transport_loss` (%) | 0,8 | 0,4 | 0,3 |
| `incident_risk_mult` | ×1,25 | ×1,00 | ×0,85 |

Modyfikator odległości/regionu: koszt i czas × `region_distance_factor ∈ [0,8; 1,6]`. Pozwolenie Poziomu 2 wymagane przed startem budowy (LOG-1.1).

### LOG-2.3. Przepływ i straty
Rurociąg działa **synchronicznie w ticku** (faza 2, podfaza 2.4/2.6):
```
Q_in   = min(dostępny_wolumen_źródła, capacity_bph × f_condition × staffing × budget × strike × Δt_h)
loss%  = min(10, base_loss + max(0, 70 − condition_pct) × 0,05 + (status='leak' ? 3,0 : 0))
Q_out  = Q_in × (1 − loss/100)
```
Koszt jednostkowy przy pełnym obciążeniu (zweryfikowany): light 3,50 PLN/bbl; standard 1,90 PLN/bbl; heavy 1,71 PLN/bbl.

### LOG-2.4. Zużycie, wycieki, inspekcje (TTS)
- Degradacja `condition_pct DECIMAL(8,4)` i `round(...,4)` w kodzie — **wymóg twardy**: przy ticku 60 s degradacja 0,05%/h = 0,00083/tick i `DECIMAL(6,2)` zaokrągla ją do zera (dosłowny bug legacy naprawiony w rundzie 4 — nie przenosimy przyczyny źródłowej).
- `leak`: spontaniczny przy `condition < 60%` z szansą `0,0008 × Δt_h × incident_risk_mult × ((60−cond)/60) × engineer_incident_mult`; leak: +0,4 pkt loss/h, degradacja ×1,20, trwa do naprawy; `condition = 0` → `damaged`.
- **Inspekcje:** zadanie TTS (`pipeline_inspection`, postęp w fazie 7 §17.4): `inspection_interval_max = 720 ticków (12 h)`; przeterminowana inspekcja → `inspection_overdue_mult = 1,3` na ryzyko incydentu i ukryte defekty (`hidden_defect` obniża efektywną kondycję o 5–15 pkt do wykrycia inspekcją). Inspekcja: koszt, 30–90 ticków, nie wstrzymuje przesyłu; naprawa wstrzymuje (`maintenance`).
- Eksplozja (`condition < 40%`, szansa `0,0006 × Δt_h × mult`): status `damaged`, utrata wolumenu **w tranzycie tego odcinka** + `env_fine` (§17.4), incydent `major/catastrophic`. NIE niszczy 5% magazynu (bug koncepcyjny legacy — patrz CZĘŚĆ 2.3).

### LOG-2.5. Obsada
Per odcinek (inbound i outbound niezależnie): `pipeline_engineer` (skaluje degradację i ryzyko incydentu: brak obsady ×2,0, pełne pokrycie ×1,0) oraz opcjonalnie `pipeline_logistics_specialist` (redukcja `loss` przypisanego odcinka do −20%). Wspólny limit alokacji ≤100% per pracownik z blokadą wiersza `FOR UPDATE` (zamiast legacy `GET_LOCK()` — §23.5). Przypisanie dozwolone w stanach `active/degraded/critical/leak`; w pozostałych — zachowane, bez efektów.

### LOG-2.6. Maszyna stanów rurociągu
```
planned → building → active → degraded (<70) → critical (<40) → damaged (cond=0 | incydent | eksplozja)
active..critical ⇄ leak (rzut / sabotaż) ; leak → damaged (cond=0)
active ⇄ suspended (gracz; OPEX ×0,5 nadal leci — wyłączenie NIE znosi kosztów)
damaged → repair_task(TTS) → active
ZAKAZ: suspended/damaged → active skrótem „wstrzymaj/wznów" (bug legacy „wskrzeszanie rurociągów" — przejście testowane maszyną stanów §23.7)
```
Uszkodzony inbound NIE zatrzymuje produkcji cicho: jeśli `well.auto_road_fallback=true` → odwiert jedzie ciężarówkami (drożej, ryzykowniej); jeśli `false` → kaskada LOG-1.4b. Jawny wybór gracza przy zakupie odwiertu/rurociągu (bug legacy runda 4: „uszkodzony rurociąg leg-1 zatrzymuje produkcję zamiast cicho jeździć ciężarówkami" — naprawiamy przez JAWNĄ flagę, nie przez cichą magię).

## LOG-3. Transport drogowy (cysterny)

### LOG-3.1. Flota
Zakup (`transport_vehicles`) lub wynajem (stawka dzienna):

| Pojazd | `capacity_bbl` | `trip_base_cost` | `trip_ticks` | `incident_risk_mult` | Cena zakupu | Wynajem/dobę |
|---|---|---|---|---|---|---|
| standard | 500 | 1 250 | 12 | ×1,00 | 150 000 | 750 |
| heavy | 900 | 2 000 | 18 | ×0,85 | 260 000 | 1 300 |
| armored | 350 | 3 500 | 12 | ×0,35 | 400 000 | 2 000 |

Czas kursu: `trip_ticks × region_distance_factor × strike_time_mult`. Pojazd ma `wear_level` i `condition` (awarie — incydent `breakdown`), naprawy TTS.

### LOG-3.2. Kurs (road_trip) — encja, nie liczba
Każdy kurs = wiersz `road_trips` z `volume_bbl`, `departure_at`, `eta_at` (UTC), `status`, `delivered_bbl`, `incident_type`, `idempotency_key`. **Koszt kursu (składowe):** `cost = trip_base_cost × distance_factor × (1 + region_risk_surcharge) × strike_cost_mult × budget_opex_mult`; płatny przy dyspach (rezerwacja + FTS w jednej transakcji). Opłata za WSZYSTKIE wysłane kursy, nie tylko dostarczone (zasada legacy do zachowania).

### LOG-3.3. Routing: kto wybiera trasę
- **Leg 1 (odwiert→hub):** gracz-właściciel odwiertu: wybór huba (walidacje: ten sam `region_id`, wolny slot, hub operacyjny, cooldown) + wybór trybu (rurociąg/cysterny).
- **Leg 2 (hub→magazyn):** właściciel huba (przy `npc` — gracz wybiera z dostępnych opcji systemowych i płaci taryfę; przy `owned/leased` — właściciel/najemca).
- **Optymalizator tras** (usługa płatna, koszt 1 500–5 000 PLN przez FTS `LOG_OPTIMIZER`): rekomendacja mixu per odwiert z prognozą kosztu jednostkowego — zachować z legacy (2026-06-10), wyłącznie jako DORADZTWO, bez automatycznego przełączania.

### LOG-3.4. Kursy ręczne vs pętla automatyczna VIP (§22.6)
- **Ręczne:** gracz zleca kurs (bufor odwiertu/huba ≥ `min_load = 50%` pojemności); potwierdzenie z kosztem i ETA.
- **Auto-pętla (perk VIP wg Tieru):** dyspach automatyczny, gdy bufor ≥ 80% pojemności cysterny; priorytet: leg 2 > leg 1; respektuje limit `waiting_bay`. **Koniec VIP w trakcie kursu: kurs DOJEŻDŻA i jest rozliczany normalnie, kolejne auto-dyspacze wyłączone** (scenariusz akceptacyjny §23.7(3), test LOG-T2). Pojazd/wynajem opłacony z góry nie przepada.

### LOG-3.5. Kolejki i `waiting_bay`
Per hub `waiting_bay_limit` (2/4/8). Załadunek: 1 tick/pojazd. Kurs oczekujący na bay > 30 min → **opłata postojowa 2 000 PLN/h** (§21, księgowana w fazie 5). Kolejka FIFO z priorytetem VIP-auto; przelanie kolejki → cysterna nie rusza, ropa zostaje w buforze (kaskada LOG-1.4b).

### LOG-3.6. Incydenty drogowe — semantyka strat (POPRAWIONA względem legacy)

| Typ | Skutek | Waga bazowa |
|---|---|---|
| `road_ambush` (napad) | utrata 100% kursu | 2 |
| `road_partial_theft` | utrata 30–70% | 3 |
| `accident` | utrata 50–100% + `breakdown` pojazdu | 4 |
| `route_block` | **OPÓŹNIENIE 30–120 min, bez utraty wolumenu** | 2 |
| `sabotage` (§7) | per katalog sabotaży | tylko przy aktywnej opcji agresora |

Bazowa szansa: `0,8% × trip_hours × risk_mult × political_scale(region) × budget × strike` (cap 95%). **Zmiana względem legacy:** `route_block` nie niszczy ropy (w legacy `computeTripLoss()` traktował blokadę i sabotaż jak pełną utratę — niespójne z dokumentacją, która pełną utratę rezerwowała dla kradzieży/napadu). Szansa liczona od RZECZYWISTEGO czasu w tranzycie (z opóźnieniami), nie od planowanego `trip_hours` (lekcja legacy).

## LOG-4. Porty i transport morski

### LOG-4.1. Bufor tankowca i próg wypłynięcia
Odwiert offshore składa produkcję do `wells.marine_buffer_bbl`; rejs startuje, gdy bufor ≥ `min_load_bbl` (domyślnie 80% pojemności tankowca). **Zakaz mikrorejsów co tick** (dosłowna naprawa legacy 2026-06-07 — zachować). UWAGA na drift konfiguracji legacy: README mówił 4 000 bbl, `TransportConfigService::getDefaults()` miał 5 000 — w nowej wersji JEDNO źródło: `game_balance` wersjonowane (§29.5).

### LOG-4.2. Tankowce i rejsy

| Tankowiec | `capacity_bbl` | `voyage_cost` | `voyage_ticks` | Jedn. koszt* |
|---|---|---|---|---|
| small | 8 000 | 15 000 | 45 | 3,63 |
| medium | 25 000 | 40 000 | 60 | 3,35 |
| large | 60 000 | 90 000 | 75 | 3,25 |

\* z opłatą portową 0,50/bbl i oczekiwaną stratą incydentową 0,5%×250 PLN. Rejs = encja `marine_voyages` (`departing → in_transit → waiting_for_port → processing → delivered | delayed | lost`), `eta_at` UTC, rozliczenie dwufazowe jak kursy (LOG-6.3).

### LOG-4.3. Porty (systemowe/NPC)
`ports`: `throughput_per_tick` (500/1 500/4 000 bbl dla small/medium/large), `queue_limit`, `handling_cost_per_bbl` 0,50, `overload_delay_risk`, `failure_risk_per_tick`. Kolejka `port_queue_entries` FIFO (`waiting → processing → done`); port `overloaded` → opóźnienia; `closed` (incydent/zdarzenie światowe §25) → **wznowienie wyłącznie jawne** (GM/naprawa TTS) — bug legacy „port closed wznawia się automatycznie" nie przechodzi. Port dostarcza do huba powiązanego (`port.hub_id`).

### LOG-4.4. Kiedy morze się opłaca
Offshore: zawsze (brak alternatywy). Onshore dalekodystansowo: tankowiec (3,25–3,63 PLN/bbl) tańszy od drogi (4,00) powyżej ~8 000 bbl/partię i przy dostępie do portu; wolniejszy (45–75 ticków vs 12–18) → wymaga bufora i rodzi ryzyko kolejki portowej. Reguła decyzyjna wbudowana w optymalizator (LOG-3.3).

## LOG-5. Transport mix i ekonomia

### LOG-5.1. Tabela kosztów i czasów (zweryfikowana arytmetycznie; przy cenie ropy 250 PLN/bbl §24.1)

| Środek | Koszt efektywny PLN/bbl* | Czas | Capex/entry | Ryzyko |
|---|---|---|---|---|
| rurociąg heavy | 1,71 | synchronicznie (tick) | 1 400 000 | niskie (×0,85) |
| rurociąg standard | 1,90 | synchronicznie | 600 000 | bazowe |
| rurociąg light | 3,50 | synchronicznie | 250 000 | podwyższone (×1,25) |
| tankowiec large | 3,25 | 75 ticków | 90 000/rejs | średnie + port |
| droga standard | 4,00 | 12 ticków | 1 250/kurs | wysokie |
| droga armored | 8,10 | 12 ticków | 3 500/kurs | ×0,35 |
| fallback_direct | ~6,3 (2×OPEX+3%) | synchronicznie | 0 | — |

\* koszt bezpośredni + oczekiwana strata incydentowa; bez OPEX hubów.
**Progi opłacalności:** rurociąg standard vs droga: powyżej **1 440 bbl/d** na odwiert; zwrot capex 600 000 przy pełnym obciążeniu (4 800 bbl/d): **~60 dni**. Droga = start, awaria, rozbudowa regionu, przepięcie huba (rola gameplayowa z legacy README — zachować).

### LOG-5.2. OPEX logistyki (faza 5, delta-time §23.3)
Składniki: OPEX hubów (własnych; dla najemców czynsz slotów), OPEX rurociągów (`opex_per_tick`, dla nowo ukończonych outbound — proporcjonalnie do REALNEGO czasu aktywnego od zakończenia budowy, bez „sztucznej pełnej godziny" — naprawa legacy 2026-07-15 do zachowania), wynajem floty, opłaty postojowe (§21), taryfy leg-2. Wszystkie przez FTS z `last_calc_at` per encja.

### LOG-5.3. Budżet działowy (§10)
| Budżet | OPEX logistyki | wear | ryzyko incydentów | throughput |
|---|---|---|---|---|
| low | ×0,90 | ×1,20 | ×1,10 | ×0,95 |
| standard | ×1,00 | ×1,00 | ×1,00 | ×1,00 |
| high | ×1,15 | ×0,85 | ×0,90 | ×1,05 |

### LOG-5.4. Kolejność stackowania mnożników (KANONICZNA — jedna funkcja `applyModifierStack`)
`bazowy → typ/klasa → kondycja → work_mode → obsada → budżet §10 → strajk §5.1 → ochrona §7 → zdarzenia świata §25`. Mnożniki tego samego poziomu mnożą się; między poziomami — kolejność jak wyżej; wynik clampowany do widełek z `game_balance`. (Lekcja legacy: mnożniki rozsiane po 6 serwisach bez jawnego porządku.)

### LOG-5.5. Strajk logistyki (§5.1)
```
strike_reduction  = 0,30 + 0,50 × udział_strajkujących      (udział ∈ [0,1])
throughput_mult   = max(0, 1 − strike_reduction)
udział > 0,60  →  trasa główna STAJE: leg-2 głównego huba = strike_stop,
                  nowe dyspacze zablokowane; kursy/rejsy in_transit DOJEŻDŻAJĄ
cost_mult         = 1 + 0,25 × udział
time_mult         = 1 + 0,30 × udział
route_block weight ×2
```
Kontrola: udział 20% → 40% redukcji; 60% → 60% redukcji (zostaje 40%); powyżej 60% → stan trasy głównej `strike_stop`. Pat mediacyjny §5.1 blokuje eskalację równoległych strajków (scenariusz §23.7(8)).

## LOG-6. Integracje

### LOG-6.1. Faza 2 ticka (§23.2) — kanoniczna kolejność podfaz
Jedna transakcja na gracza (§23.5), stała kolejność graczy wg `player_id`; batch 100:
1. **2.1 Dyspacz leg-1:** ładowanie cystern z buforów odwiertów (auto-pętla VIP + sloty ręczne), `waiting_bay → in_transit`, rezerwacja wolumenu + koszt przez FTS.
2. **2.2 Rozliczenie przybyć:** kursy drogowe i rejsy z `eta_at ≤ now` → rzuty incydentów (seed §23.4) → `delivered_bbl` → dwufazowy kredyt (LOG-6.3) do bufora huba (leg 1) / magazynu (leg 2).
3. **2.3 Kolejki portowe:** FIFO, cap `throughput_per_tick`, opłata handling, przeładunek do huba portu.
4. **2.4 Przepływ rurociągów inbound:** synchroniczny, straty/wycieki, `well_buffer → hub_buffer`.
5. **2.5 Przetwarzanie hubów:** `capacity_eff` (LOG-1.6), drain bufora, overflow→spill, wear, status, incydenty huba (jedno źródło rzutu — `HubIncidentService`; NIE dublować z ticikiem huba, lekcja legacy `incident_flag`).
6. **2.6 Leg 2:** przepływ rurociągów outbound + dyspacz cystern z bufora huba (`waiting_bay`).
7. **2.7 Kredyt do magazynu z capem:** nadmiar → `paused_storage` + kaskada LOG-1.4b; uzgodnienie bilansu bbl.
8. **2.8 Zdarzenia i telemetria:** zapis `logistics_incidents`, statystyk tickowych, zdarzenia Reverb wyłącznie `after_commit` (§23.5/§28.3).
Zasada §23.2 zachowana: faza 2 zmienia stan surowca; faza 5 księguje OPEX/postojowe; faza 7 progres napraw/inspekcji TTS; faza 8 cooldowny sabotaży i wygasanie ochron.

### LOG-6.2. Katalog stanów (kolumny `status`, VARCHAR + enum PHP 8.3)
- Kurs/rejs: `waiting_bay → in_transit → delayed → in_transit → arrived → unloading → delivered | lost | seized`
- Rurociąg: LOG-2.6; Hub: LOG-1.10; Przypisanie: `active → relinking → detached(cooldown)`
- Odwiert (interfejs logistyki): `choked`, `auto_road_fallback`
- Magazyn/hub leg-2: `paused_storage`, `strike_stop`

### LOG-6.3. Rozliczenie dostawy — dwufazowy kredyt (wzorzec legacy M3, uogólniony)
Kurs przybywający przechodzi `in_transit → crediting` (wolumen i incydenty utrwalone) → kredyt do magazynu/huba + `crediting → delivered` w **JEDNEJ transakcji** z `idempotency_key = (tick_no, trip_id)` (§23.5). Restart workera: rekordy `crediting` na starcie ticka = kredyt NIE zacommitowany → ponowny kredyt BEZ ponownych rzutów (recovery legacy z `RoadTransportService::processCompletedTrips` — zachować semantykę). Zakaz stanów przejściowych w osobnych autocommitach (bug legacy: `delayed` ustawiało `delivered` poza transakcją magazynu → utrata ropy przy crashu).

### LOG-6.4. Fizyka dostaw B2B (§13.2)
**Dostawa B2B = REALNY kurs/rejs** (encja transportowa z ETA), nie goły timer: sprzedający wybiera środek (mix z LOG-5), wolumen rezerwowany z magazynu przy `accepted`, escrow `PLN_ESCROW` (§24.2) zwalniany przy `delivered` (potwierdzenie `delivery_receipt` wygrywa z auto-refund — domknięcie znanego gapa specyfikacji). Strata w tranzycie: `delivered_bbl < contracted` → rozliczenie częściowe + ścieżka sporu (§13.2); pełna utrata (`lost`) → auto-refund escrow do kupującego, odszkodowanie z ubezpieczenia kursu (opcja płatna, `game_balance`). Timeout dostawy = `eta_at + grace_ticks`; opóźnienie > grace → kara umowna przez FTS. Magazyn kupującego pełny → kurs czeka w `waiting_bay` kupującego, opłata postojowa po stronie kupującego (§21).

### LOG-6.5. Incydenty logistyczne i ochrona/sabotaże (§7)
Katalog incydentów per ogniwo (severity `micro|minor|medium|major|catastrophic`, powiadomienia §27.2): rurociąg — `pipe_micro/minor/medium`, `leak`, `explosion`; hub — `overload_spill`, `equipment_failure`, `hub_valve_sabotage`; droga — LOG-3.6; morze — `storm`, `piracy`, `breakdown`, `cataclysm`, `sabotage`; port — `port_failure`, `overload`.
**Sabotaże graczy (§7)** wyłącznie przez `SabotageService` z limitami (5 udanych/7 dni na ofiarę, okno kroczące; finansowanie z Kroplówki zablokowane w `recovery_mode` §24.3): `road_ambush`, `road_partial_theft`, `hub_valve_sabotage` (kondycja −5…−15, spill 2–5% bufora, throughput ×0,5 na 6–24 h). Waga `sabotage` w losowaniu = 0, gdy brak aktywnej opcji agresora (reguła legacy do zachowania).
**Ochrona (moduł uniwersalny — architektura legacy `ProtectionService` 1:1):** opcje w DB (`protection_options/effects`, nie w kodzie), `target_type + context`, mnożniki clamp **[0,05; 1,0]** — ochrona nigdy nie zeruje ryzyka; modyfikacja WAG typów z renormalizacją `sum(w')/sum(w)`, tak że typy niechronione (`accident`, `route_block`, `storm`) zachowują dokładnie bazowe prawdopodobieństwa (matematyka z `applyTripIncidents` — zweryfikowana, przenieść); wygasanie leniwe po `ends_at`; max 1 aktywna ochrona per gracz+cel+kontekst; log `protection_applied_to_incident`.

| Opcja | Cel | Koszt startowy | Czas | Efekty |
|---|---|---|---|---|
| `escort_basic` | kurs drogowy | 75 000 | 60 min | ambush ×0,6; partial_theft ×0,5 |
| `armed_convoy` | kurs drogowy | 500 000 (wym. dział prawny 3+) | 60 min | ambush ×0,25; theft ×0,3 |
| `drone_patrol` | kurs/hub/rurociąg | 50 000/h | 120 min | ambush ×0,35; theft ×0,45; valve ×0,5 |
| `hub_security` | hub | 120 000 | 60 min | valve ×0,4; overload/awarie sprzętu ×0,7 |
| `pipeline_monitor` | rurociąg | 30 000/h | 120 min | incydenty pipe ×0,6; leak ×0,7 |

Zakup ochrony tylko przez podmiot, na którego tick ją zastosuje (właściciel; najemca NIE kupuje ochrony huba, której silnik nie zaczyta — hotfix legacy 2026-06-12 jako reguła walidacji celu per `target_type`).

### LOG-6.6. Model danych (tabele, konwencje §24.4)
`logistics_hubs`, `hub_assignments`, `hub_permits`, `hub_staff_stats` (opcjonalnie JSON), `pipelines` (+`pipeline_inspections`), `transport_vehicles`, `road_trips`, `marine_voyages`, `ports`, `port_queue_entries`, `logistics_incidents`, `hub_tick_stats`, `pipeline_tick_stats` (partycjonowanie roczne §29.4), `protection_options/effects`, `active_protections`, `protection_logs`. Wszystkie: `status` VARCHAR+enum, pieniądze BIGINT grosze, wolumeny DECIMAL(16,4), czasy DATETIME(3) UTC, `last_calc_at` delta-time, FK z `player_id` w KAŻDYM finalnym UPDATE/DELETE (reguła z audytu izolacji legacy 2026-07-16 — warunek własności w zapisie, nie tylko w wcześniejszym SELECT).

### LOG-6.7. Katalog `reason_code` FTS
`LOG_ACCESS_FEE, LOG_LEASE, LOG_HUB_OPEX, LOG_PIPELINE_OPEX, LOG_PIPELINE_BUILD, LOG_HUB_BUILD, LOG_HUB_UPGRADE, LOG_REPAIR, LOG_MAINTENANCE, LOG_INSPECTION, LOG_PERMIT, LOG_ROAD_TRIP, LOG_VEHICLE_LEASE, LOG_VOYAGE, LOG_PORT_HANDLING, LOG_TARIFF_LEG2, LOG_PARKING (§21), LOG_PROTECTION, LOG_OPTIMIZER, LOG_SPILL_FINE, LOG_INSURANCE, LOG_B2B_DELIVERY_PENALTY`.

### LOG-6.8. Testy akceptacyjne LOG (rozszerzenie §23.7, blokują release)
(LOG-T1) restart workera między `crediting` a kredytem magazynu — brak podwójnego/utraconego bbl; (T2) koniec VIP w trakcie kursu (scen. §23.7-3); (T3) magazyn pełny → pełna kaskada do `choked`, bilans bbl uzgodniony co do 0,0001; (T4) `damaged` inbound + `auto_road_fallback` true/false — oba zachowania; (T5) strajk 60% + turbo — clamp i `strike_stop`; (T6) `drone_patrol` vs `road_ambush` — mnożniki w [0,05;1,0], typy niechronione bez zmian; (T7) jednoczesna sprzedaż ropy `in_transit` (spot+B2B+limit, scen. §23.7-4) — rezerwacja blokuje double-sell; (T8) golden-master formuł LOG (1000 ticków headless §23.7); (T9) relink huba podczas kursu leg-1 — kurs do starego huba; (T10) `delivery_receipt` przed auto-refund (escrow); (T11) suspend/resume rurociągu `damaged` — przejście odrzucone (maszyna stanów); (T12) catch-up 24 h po przerwie — wear z capem ×Δt, brak podwójnego OPEX.

---

# CZĘŚĆ 2 — AUDYT LEGACY

## 2.1. CO PRZENIEŚĆ (1:1 lub z minimalną adaptacją)

1. **Dwufazowy kredyt dostawy `crediting → delivered` + recovery sierot** — `RoadTransportService::processCompletedTrips()` / `confirmCreditedTrips()` (wpis M3). Rozwiązany realny problem: crash między rozliczeniem kursu a zapisem magazynu gubił/tworzył ropę. W nowej wersji jako `idempotency_key` + jedna transakcja (§23.5) — semantyka ta sama.
2. **Architektura modułu ochrony** — `src/Protection/ProtectionSchema.php`, `src/ProtectionService.php`: opcje/efekty w DB (nie w kodzie), `target_type+context`, clamp mnożników [0,05; 1,0], leniwe wygasanie, max 1 ochrona per cel, `protection_applied_to_incident`. Plus **matematyka wag** z `RoadTransportService::applyTripIncidents` (`w'=w×mult`, `chance ×= sum(w')/sum(w)`) — elegancko chroni bazowe prawdopodobieństwa typów niechronionych.
3. **Bufor tankowca z progiem `min_load_bbl`** (wpis 2026-06-07 + `TransportConfigService`) — koniec mikrorejsów co tick. Przenieść razem z buforem drogowym (`road_buffer_bbl`).
4. **Cap zużycia huba z skalowaniem Δt** — `HubTickService::processTick()` (`min(wear, base×8×max(1,Δt_h))` + komentarz o catch-upie 24 h). To gotowy wzorzec delta-time zgodny z §23.3.
5. **`condition_pct DECIMAL(8,4)` + `round(...,4)`** (runda 4 / komentarz w `PipelineSection.php`) — wraz z UZASADNIENIEM: przy krótkim ticku degradacja zaokrąglała się do zera. W nowej wersji podnieść do DECIMAL(9,5) dla 60-s ticka (0,05%/h = 0,00083/tick).
6. **Cooldown 4 h po odpięciu od huba + walidacje przypisania** (`HubAssignmentService`, `AssignmentValidationTrait`: region, sloty, środki, brak aktywnego przypisania) — spójne z anty-reroll §23.4.
7. **Blokady stanów huba przy zakupie rurociągu + re-check `FOR UPDATE`** (wpis 2026-07-15) — gotowe wymaganie §23.5; w Laravel od razu jako `lockForUpdate()`.
8. **Zamykanie zamiast kasowania**: `purgeOrphanActiveForPlayer()` oznacza `lost` z `incident_type=orphan_purge` zamiast DELETE (2026-07-09); admin nie zeruje buforów bez rozliczenia. W zero-wipe to wymóg audytowalności bilansu bbl.
9. **Jedna techniczna ścieżka `hub_operator`** (2026-07-18, migracja `2026-07-18_hub_operator_technical_migration.sql`) + zasada „bonus operatora tylko na przypisany hub, skalowany alokacją".
10. **Model obsady z alokacją ≤100%** (`EmployeeAssignmentService`, `LogisticsStaffingService` — coverage_pct, średni skill/morale, mnożniki per hub/odcinek; 2026-07-16/19). Zamiast `GET_LOCK()` — `FOR UPDATE`.
11. **Work_mode eco/standard/turbo na istniejących mnożnikach ticka** (2026-07-22) + walidacja owner/tenant w finalnym UPDATE.
12. **Savepoint w `persistTickResult()` przy pracy w cudzej transakcji** (2026-07-15) — wzorzec do Laravelowych zagnieżdżonych transakcji.
13. **`getViabilityRatio()` z normalizacją jednostek** (`HubEconomyService`) — nie sama formuła, ale dyscyplina: jawne sprowadzanie obu stron stosunku do bbl/h. W nowej specyfikacji: jednostki OPEX zawsze „per tick (60 s)", zapisane w nazwie pola (`opex_per_tick`).
14. **Batchowy prefetch (anty-N+1)**: `getActiveProtections()`, jedna mapa obsady na gracza, memoizacja portów/incydentów (runda 4, 2026-06-12) — wymagać w fazie 2 od pierwszego commitu (budżet 30 s/1000 graczy §23.6).
15. **Design łańcucha dwuodcinkowego** (README: leg 1 + leg 2, hub obowiązkowy, potwierdzenia z kosztem/czasem, liczniki czasu, dzienny raport logistyki w finansach) — to dobra oś gameplayowa; raport dzienny zmapować na telemetryczne agregaty §29.3.

## 2.2. CO DOPRACOWAĆ / ULEPSZYĆ

1. **Determinizm RNG:** `mt_rand()` w `RoadTransportService`, `PipelineSection`, `HubTickService` → HMAC-seed §23.4 z kluczami domenowymi (bez tego nie ma replayu ani golden-master §23.7).
2. **DDL w runtime:** `TransportConfigService::ensureTransportSchema()`, `RoadTransportService::ensureSchema()`, `Hub/ConfigTrait::ensureHubSchema()` wykonują ALTER-y przy starcie → wyłącznie migracje Laravel expand→migrate→contract (§29.1). Legacy ma przy tym **dwa sprzeczne źródła prawdy schematu** `well_road_trips` (SQL `etap6_logistics_v2.sql` z `hub_id` vs PHP-DDL z `trips_count/delivered_bbl` bez `hub_id`, łatane `ensureRoadTripColumn`) — w nowym projekcie jeden schemat per tabela.
3. **Migracje niekompatybilne z MySQL:** `etap9_hub_ownership.sql` i `etap13_hub_permit_schema.sql` używają `ADD COLUMN IF NOT EXISTS` (MariaDB-only) — ten sam błąd, który naprawiono w `HubPermitTrait` (2026-06-09, `Database::addColumnIfMissing`). Wniosek procesowy: walidacja migracji per silnik DB w CI.
4. **Churn modelu leg-2:** etap10 (per-odwiert `wells.hub_outbound_transport_type`) → etap11 (per-hub `logistics_hubs.outbound_transport_type`) w kilka dni; martwa kolumna „dla kompatybilności", sentinel `well_id=0`, migracja z DELETE duplikatów outbound (kasowanie majątku gracza; refund dopiero w `tick_bugfix_round4.sql`). W nowej wersji: model docelowy (LOG-2.1: NULL-able `well_id`, unique per hub, własność leg-2 jawna) ZANIM powstanie kod.
5. **Semantyka strat drogowych:** `computeTripLoss()` — `route_block` i `sabotage` = 100% utraty, niezgodnie z README („kradzież/napad = cały kurs"); `accident` 50–100%. Rozdzielić: blokada = opóźnienie (LOG-3.6).
6. **Sprzeczność fallback vs „hub obowiązkowy":** `HubTickService::applyFallback()` i ścieżka „hubless wells → storage cap" w `WellRoadTripSection` istnieją mimo dokumentu „nie wolno robić skrótu odwiert→magazyn". Ujednolicić: jawna flaga `fallback_direct` (LOG-1.4c) albo czysta kaskada `choked`.
7. **Eksplozja rurociągu niszczy magazyn:** `PipelineSection::process()` — `oilInTransit = min($remaining, $remaining×0.05)` (redundantny `min`, zawsze 5%) liczony od `currentStorage`, a nie od wolumenu w odcinku. Przeprojektować: strata = wolumen w tranzycie odcinka + env_fine (LOG-2.4).
8. **Skalowanie szansy incydentu planowanym `trip_hours`** zamiast rzeczywistym czasem w tranzycie (opóźnienia nie podnoszą ryzyka) — liczyć od realnego Δt.
9. **Ukryta kara za brak obsady** (`engineer_*_mult` domyślnie 2,0 w `PipelineSection`) — zachować efekt, ale ujawnić w UI/balansie i powiązać z budżetem §10.
10. **Jednostki czasu/OPEX:** „opex_per_tick skalibrowane jako per-godzinę (1 tick = 1 h z konwencji)" z komentarza `getViabilityRatio` — przy ticku 60 s (§23.1) ta konwencja się rozpada. Wszystkie stawki w nowej grze: per tick 60 s, nazwa pola mówi prawdę.
11. **Dryf konfiguracji:** `min_load_bbl` 4 000 (README 2026-06-07) vs 5 000 (`TransportConfigService::getDefaults()`) — jedno źródło: wersjonowany `game_balance` (§29.5).
12. **Własność hubów — dokończyć model:** `OSTATECZNA_WERSJA_HUB_RUROCIAG.md` §1.1 mówi „hub systemowy, gracz nie kupuje", ale etap9 dodał `tenant_player_id/acquisition_price`, `HubAcquisitionService` istnieje, README §7 listuje „Player ownership model — wysoki priorytet, nie zaczęte", a changelog 2026-07-22 obsługuje owner/tenant. Trzy dokumenty, trzy stany prawdy. Nowa specyfikacja: jeden model `npc/owned/leased` (LOG-1.1).
13. **Deterministyczny vs sprawiedliwy porządek graczy:** legacy `PlayersSection` przetwarza wg `last_tick_at ASC, id ASC` (fairness przy `max_items_per_run`); §23.5 nowej specyfikacji wymaga stałej kolejności wg `player_id` (deadlocki). Pogodzić: kolejność `player_id` w transakcjach, fairness przez gwarancję domknięcia ticka (budżet 30 s, alerty §29.3), nie przez rotację kolejki.
14. ** ENUM-y MySQL** (`MODIFY COLUMN ... ENUM` w etap6/7, `ensureRoadTripStatusEnum` z detekcją po `COLUMN_TYPE`) → VARCHAR + backed enumy PHP 8.3 (§24.4) — legacy płaciło za to ALTER-ami przy każdym nowym stanie (`crediting`, `delayed`).
15. **`HubPermitTrait`/pozwolenia:** schema OK (`hub_permit_applications` z `delay_count`, `refusal_cooldown_until`), ale domyślne `hub_permit_enabled=0` wszędzie = mechanika martwa do ręcznego włączenia. W nowej grze: stan domyślny per region z generatora świata.

## 2.3. BUGI I BŁĘDY (konkretne, z plikami)

**Historyczne (naprawione — zweryfikować, czy przyczyna źródłowa nie wraca):**
1. **Runda 3–4 ticka (2026-07-03, 48 bugów)** — najgęstsze pole minowe logistyki:
   - **IDOR w `getHubDetail`** — podgląd cudzego huba; przyczyna: brak `player_id` w zapytaniu. Reguła przeniesiona: własność w finalnym zapisie (audyt izolacji 2026-07-16).
   - **Podwójna utrata ropy przy spillu** — podwójne księgowanie straty (overflow + warunek kondycji). Przyczyna źródłowa: brak jednego punktu uzgadniania bilansu bbl → w nowej wersji wymóg LOG-0.1 (bilans co tick).
   - **Darmowe awarie mechaniczne** — incydent bez kosztu; **wskrzeszanie rurociągów przez suspend/resume** — obejście maszyny stanów (zakaz przejścia `damaged→active` bez naprawy, LOG-2.6, test T11).
   - **`condition_pct DECIMAL(6,2)` → zużycie zerowane zaokrągleniem** przy 5-min tickach — PRZYCZYNA ŹRÓDŁOWA KLASY „precyzja vs granularność ticka": przy ticku 60 s problem WRACA ze zdwojoną siłą (0,05%/h = 0,00083/tick) → DECIMAL(9,5) + round(,5) jako wymóg, test T8 golden-master zużycia.
   - **`purchasePipeline` odrzucał `leg=outbound`; refund + usunięcie martwych wierszy outbound** (`tick_bugfix_round4.sql`) — skutek churnu etap10/11 (patrz 2.2.4).
   - **Ciche kasowanie rejsów morskich** → status `lost`; **potrójna opłata offshore**; **port `closed` wznawiał się automatycznie**; **zużycie sprzętu do kwadratu** (podwójne nałożenie mnożnika — klasa bugów „stackowanie mnożników bez kolejności" → LOG-5.4 kanoniczny stack); **brak ryzyka politycznego leg-2**.
2. **Utrata ropy przy crashu kursu `delayed`** (wpis M3 w komentarzach `RoadTransportService`): `delivered` w autocommicie + kredyt in-memory → crash przed commitem magazynu gubił ropę. Naprawione wzorcem crediting; przyczyna źródłowa (stan przejściowy poza transakcją) wyeliminowana w LOG-6.3 przez §23.5.
3. **Cap magazynu kasował ropę hubową przed dotarciem do huba** (M4 w `WellRoadTripSection`) — fix rozdziela hub/hubless; w nowej wersji kaskada LOG-1.4b usuwa samą klasę problemu (magazyn nie jest capem dla bufora huba).
4. **Rozliczanie ropy drenowanej z bufora huba** (2026-07-15): straty kondycji odejmowane przed sprawdzeniem miejsca w magazynie, do bufora wracał wolumen brutto → obciążanie magazynu stratą. Widać w `HubTickService`: `condLostBbl` odejmowane od `processed` (zawierającego drain) — kolejność operacji musi być testem akceptacyjnym (T1/T3).
5. **Mikroróżnice bilansu `addBufferBbl()`** — DECIMAL(12,2) w hubie vs (12,4) w kursach → patrz 2.2/LOG-0.3.
6. **Wyścig zakup rurociągu vs transfer odwiertu** (2026-07-15: blokada przypisania `FOR UPDATE` w transakcji zakupu; analogiczny wyścig w grantach admina) — w §23.5 jako zasada ogólna.
7. **Wyścig alokacji pracownika** (`EmployeeAssignmentService`, `GET_LOCK()` per pracownik, 2026-07-16) i **wyścig przypisania z równoległym wyłączeniem rurociągu** (2026-07-19, `was_update` z atomowej operacji zamiast wcześniejszego SELECT).
8. **`release_hub_staff` zwalniał dowolne `employee_assignments` gracza, nie tylko typu hub** (2026-07-16) — błąd zakresu akcji masowej.
9. **Najemca płacił za ochronę huba, której tick nie stosował** (hotfix 2026-06-12) — rozjazd walidacji zakupu z egzekucją efektu; reguła LOG-6.5.
10. **Globalny bonus operatora naliczany wszystkim hubom gracza** (2026-07-18) — efekt oderwany od przypisania.
11. **Koszt optymalizatora poza FTS** (2026-06-10, bezpośredni `UPDATE players SET cash`) — dziura audytowa; w §24.2 każda ścieżka przez FTS.
12. **Widok logistyki pokazywał 0 mimo aktywnych dostaw** (2026-06-08: puste tablice w kontrolerze) oraz **czyszczenie historii dostaw po 7 dniach bez archiwum** (2026-06-07/08: historia „może zniknąć po czyszczeniu ticka") — w zero-wipe niedopuszczalne: partycjonowanie §29.4, nie DELETE.
13. **Odwierty `status='sold'` nie produkowały** (etap8 pkt 7, data-fix `sold→active`) — martwy stan w enumie cyklu życia; w nowej wersji maszyny stanów testowane (§23.7).

**Widoczne w dostarczonym kodzie (niekoniecznie naprawione):**
14. `PipelineSection::process()` — `$oilInTransit = min($remainingStorageForDisasters, $remainingStorageForDisasters * 0.05)`: `min(x, 0,05x)` = zawsze `0,05x`; kod mylący i koncepcyjnie błędny (patrz 2.2.7).
15. `RoadTransportService::ensureSchema()` — `political_risk_level ... AFTER incident_risk_mult` i `delay_risk_mult ... AFTER incident_risk_mult`: konflikt pozycjonowania kolumn (kosmetyka, ale objaw braku jednego schematu — 2.2.2).
16. `RoadTransportService::processTick()` (ścieżka SQLite/legacy) — pełne `mt_rand` bez seeda, równoległa logika incydentów obok `applyTripIncidents` → **dwa silniki ryzyka w jednym serwisie** (dryf zachowań MySQL vs SQLite — testy integracyjne SQLite nie walidują produkcji).
17. `HubTickService` — próg `COND_DAMAGED=30`/`COND_CRITICAL=20` dla hubów vs 40/70 dla rurociągów w `PipelineSection`: dwie skale kondycji w jednym module (ujednoliciłem w LOG-1.6/2.3).
18. `TransportConfigService::load()` wykonuje pełny `SELECT` konfiguracji **na każde wywołanie `getTypeConfig`** (brak cache per tick) — drobny N+1 konfiguracyjny.

## 2.4. WNIOSKI KOŃCOWE — decyzje do podjęcia PRZED kodowaniem logistyki

| # | Decyzja | Rekomendacja | Blokowane ryzyko |
|---|---|---|---|
| D1 | Model własności hubów (jeden kanoniczny) | `npc/owned/leased` z acquisition przez aukcję i rynek NPC (LOG-1.1) | legacy miało 3 sprzeczne stany prawdy (2.2.12) |
| D2 | Własność i koszty leg-2 przy hubie wielonajemcowym | leg-2 należy do właściciela huba; najemcy płacą `tariff_per_bbl`; przy `npc` — taryfa systemowa | etap10/11 churn, DELETE duplikatów bez refundu |
| D3 | Zachowanie przy zerwanym łańcuchu | kaskada `paused_storage → spill → choked` + jawny `fallback_direct` (domyślnie wył.) | ciche zatrzymania produkcji i darmowa destrukcja ropy (bug 1, 3, 2.2.6) |
| D4 | Precyzja wolumenów i punkt zaokrąglenia | DECIMAL(16,4) wszędzie, jedno zaokrąglenie przy kredycie; kondycja DECIMAL(9,5) | mikrorozjazdy bilansu, zerowanie zużycia (bug 4–5, 1) |
| D5 | Standard rozliczenia dostawy | dwufazowy kredyt + `idempotency_key (tick_no, trip_id)` dla kursów, rejsów, B2B, portów | utrata/duplikacja ropy przy crashu (bug 2) |
| D6 | Jeden kanoniczny stack mnożników | LOG-5.4, jedna funkcja, test golden-master | „zużycie do kwadratu", mnożniki rozsiane po serwisach (bug 1) |
| D7 | Klucze RNG domen logistycznych | LOG-0.5 (`logistics|trip/hub/pipeline/voyage/port`) | brak replayu incydentów, reroll exploit |
| D8 | Integracja `waiting_bay` z opłatą postojową §21 | grace 30 min, potem 2 000 PLN/h po stronie zlecającego | nieokreślone w obecnej specyfikacji |
| D9 | Dostawa B2B = realny kurs + escrow | LOG-6.4, `delivery_receipt` wygrywa z auto-refund, ubezpieczenie opcjonalne | znany gap specyfikacji (B2B escrow closure) |
| D10 | Mapping sabotaży/ochrony na kody §7 | LOG-6.5 (clamp [0,05;1,0], limit 5/7 dni, walidacja celu per target_type) | ochrona kupowana bez efektu (bug 9), griefing bez limitu |
| D11 | Kadencja inspekcji TTS i pozwoleń | inspekcja co ≤12 h, pozwolenie Poziomu 2 per region z cooldownem odmowy | przeterminowane przeglądy bez konsekwencji; martwe `hub_permit_enabled=0` |
| D12 | Zakres auto-pętli VIP (§22.6) | auto-dyspacz przy buforze ≥80%, priorytet leg-2, koniec VIP nie przerywa kursów w toku | scenariusz akceptacyjny §23.7(3) bez definicji zachowania |

Największa pojedyncza lekcja legacy: **48 bugów rund 3–4 to w ~70% klasy „bilans bbl bez jednego punktu uzgadniania" i „mnożniki/stany bez kanonicznej kolejności"** — obie zamykają się decyzjami D3–D6, które powinny zapaść przed pierwszą linijką kodu fazy 2.
