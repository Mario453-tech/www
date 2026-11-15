# OilEmpire — LOGISTYKA: synteza 3 niezależnych opracowań (GPT / DeepSeek / Qwen)

Data: 09–10.11.2026. Każdy model dostał ten sam pakiet z legacy repo git (`www` @ 09b07a97): dokumentacja projektowa logistyki (LOGISTICS_HUBS_README_UJEDNOLICONE_FINAL, MODUL_LOGISTYKI_DLA_GRACZA, OSTATECZNA_WERSJA_HUB_RUROCIAG, README_rurociag, BRIEF_TESTERZY_LOGISTYKA), historia bugfixów z GAME_README (~35 KB), 11 migracji SQL (schematy) oraz 6 kluczowych serwisów PHP (HubTickService, HubEconomyService, TransportConfigService, RoadTransportService, WellRoadTripSection, PipelineSection) + konwencje nowej specyfikacji (§23/§24).

Pełne opracowania:
- `OILEMPIRE_LOGISTYKA_PROPOZYCJA_GPT.md` (gpt-6-sol, 33 KB) — sekcja [LOG-01–16] + audyt z tabelą bugów „potwierdzone w kodzie"
- `OILEMPIRE_LOGISTYKA_PROPOZYCJA_DEEPSEEK.md` (deepseek-v4-pro high, 44 KB) — sekcja §15.1–15.8 + 13 bugów + 12 decyzji P0–P3
- `OILEMPIRE_LOGISTYKA_PROPOZYCJA_QWEN.md` (qwen3.8-max high, 48 KB) — sekcja LOG-0–LOG-6 + 15×przenieść + 15×dopracować + 18 bugów + 12 decyzji D1–D12; **jako jedyny uruchomił skrypt weryfikujący arytmetykę bilansu** (`log_balance_check.py`)

---

## 1. KONSENSUS — wszyscy trzej niezależnie potwierdzili

### Architektura do przeniesienia z legacy (dobra, sprawdzona)
1. **Dwuodcinkowy łańcuch fizyczny:** `odwiert → leg1 (rurociąg|cysterna) → HUB → leg2 (rurociąg|cysterna) → magazyn`; hub obowiązkowy; brak automatycznego transferu odwiert→magazyn. Ropa w magazynie = tylko ta, która fizycznie przeszła oba odcinki.
2. **Dwufazowy kredyt dostawy** `crediting → delivered` (wzorzec M3 z `RoadTransportService`) — w nowej wersji jako jedna transakcja z `idempotency_key (tick_no, trip_id)`; restart workera nie gubi i nie duplikuje ropy.
3. **`ProtectionService` 1:1** — opcje/efekty ochrony w DB (nie w kodzie), `target_type+context`, mnożniki clamp [0,05; 1,0] (ochrona nigdy nie zeruje ryzyka), renormalizacja wag tak, że typy niechronione zachowują bazowe prawdopodobieństwa, max 1 ochrona per cel, log zastosowań.
4. **Bufory z progiem `min_load_bbl`** (koniec mikrorejsów co tick), **cooldown 4h po odpięciu od huba**, **tryby pracy huba eco/standard/turbo**, **rozdzielenie naprawy (TTS, przywraca kondycję) od konserwacji (resetuje mnożnik wieku, nie naprawia)**, **historia transportów zamykana (`lost` z przyczyną), nigdy DELETE** — w zero-wipe to wymóg bilansu.
5. **Jawna walidacja własności w FINALNYM zapisie** (`WHERE player_id=?` w UPDATE, nie tylko w SELECT) — zasada z audytu izolacji legacy.

### Bugi legacy — te same znalazły wszystkie trzy modele (nie przenosić przyczyn źródłowych!)
| Bug (legacy) | Klasa problemu | Zabezpieczenie w nowej wersji |
|---|---|---|
| IDOR w `getHubDetail` | brak `player_id` w zapytaniu | własność w każdym zapytaniu i zapisie |
| Podwójna utrata ropy przy spillu | dwa punkty księgowania straty | JEDEN niezmienny rejestr `oil_movements` + bilans bbl co tick |
| Wskrzeszanie rurociągów suspend/resume (`damaged→active` bez naprawy) | brak maszyny stanów | jawne przejścia + test odrzucenia skrótu |
| `condition_pct DECIMAL(5,2)` zerowało zużycie przy krótkim ticku | precyzja vs granularność | DECIMAL(9,5) dla ticka 60s (0,05%/h = 0,00083/tick!) |
| Wybuch rurociągu niszczy 5% **magazynu** zamiast ropy w tranzycie (`min($x, $x*0.05)` — zawsze 5%) | strata liczona od złej bazy | strata = wyłącznie `pipeline_inflight` danego odcinka |
| `route_block` = 100% utrata ładunku (sprzeczne z README) | semantyka incydentu | blokada = OPÓŹNIENIE 30–120 min, bez utraty |
| `mt_rand()` w incydentach (Road/Pipeline/Hub) | brak replayu | HMAC-seed §23.4 z kluczami `logistics|trip/hub/pipeline/voyage/port` |
| DDL w runtime (`ensureSchema()`, `ensureTransportSchema()`), dwa sprzeczne schematy `well_road_trips` | brak jednego źródła prawdy | wyłącznie migracje Laravel expand→contract, jeden schemat per tabela |
| `HubTickService` zużycie od `time()` zamiast czasu ticka | niedeterminizm catch-up | `tick_started_at` + Δt + cap `8×base×max(1,Δt_h)` |
| `opex_per_tick` traktowane jako PLN/h (`×24`) | chaos jednostek | stawki per tick(60s), nazwa pola mówi prawdę |
| Potrójna opłata offshore / port auto-reopen / ciche DELETE rejsów / overflow przed hubem / osierocone `crediting` / wyścig przypisania operatora / N+1 w ticku / koszt optymalizatora poza FTS (`UPDATE players SET cash`) / globalny bonus operatora wszystkim hubom / `release_hub_staff` zwalniał nie te przypisania / migracja `ADD COLUMN IF NOT EXISTS` (MariaDB-only) | każdy osobno udokumentowany w 3 recenzjach | patrz tabele decyzji i testy LOG-T1–T12 |

### Luki projektowe legacy — do domknięcia w nowej sekcji (wszyscy trzej)
1. **Własność hubów: 3 sprzeczne stany prawdy** (dokumenty „hub zawsze systemowy" vs `etap9` `tenant_player_id`/acquisition vs README „player ownership — nie zaczęte") → jeden model **`npc / owned / leased`**.
2. **Brak modelu floty** — legacy „nieskończona flota" (kurs = liczba, nie pojazd) → `transport_vehicles` jako skończony zasób (zakup/najem, pojemność, stan, kierowca, 1 pojazd ≠ 2 kursy równolegle).
3. **Leg-2 (hub→magazyn) niedokończony** + churn etap10/etap11 (wybór per odwiert vs per hub, migracja z DELETE duplikatów bez refundu) → własność leg-2 jawna: należy do właściciela huba, najemcy płacą `tariff_per_bbl`.
4. **Sabotaż rurociągów nieistniejący** w legacy mimo §7 nowej specyfikacji → `hub_valve_sabotage` + `pipeline_monitor` jako opcja ochrony.
5. **`fallback_direct` vs „hub obowiązkowy"** — legacy ma cichy fallback w kodzie mimo zakazu w dokumentach → albo czysta kaskada `paused_storage→spill→choked`, albo JAWNA flaga (droga: OPEX ×2, strata 3%) — decyzja produktowa.
6. **Martwy `pipeline_logistics_specialist`** i nieaktywny `min_load_bbl` dla drogi — w nowej wersji mają działać.
7. **B2B = realny transport, nie timer** (Qwen LOG-6.4 domyka znany gap §13.2): `delivery_receipt` wygrywa z auto-refund, strata częściowa → rozliczenie częściowe + spór, pełna → refund + opcjonalne ubezpieczenie kursu.

## 2. ROZBIEŻNOŚCI między modelami (do rozstrzygnięcia)

| Kwestia | GPT | DeepSeek | Qwen |
|---|---|---|---|
| Numeracja sekcji | „Logistyka [LOG-01–16]" | „§15" — **kolizja z istniejącym §15 CMS!** | §11A (między Technicznym a Rynkiem), ID `LOG-{NR}` wg §24.4 |
| Huby (mały/średni/duży) | 120/400/1200 bbl/h; bufor 500/2k/8k; budowa 120k/450k/1,4M | (własna tabela §15.8) | 150/400/1000 bbl/h; bufor 4k/12k/40k; budowa 800k/2,5M/8M |
| Cysterny | 25/50/20 bbl (!), 35k/65k/100k PLN | — | 500/900/350 bbl, 150k/260k/400k PLN |
| Tankowce | 1k/5k/20k bbl | — | 8k/25k/60k bbl |
| Kalibracja liczb | „wartości startowe do testów" | do kalibracji | **zweryfikowane arytmetycznie skryptem** przy cenie 250 PLN/bbl (progi opłacalności: rurociąg standard > 1440 bbl/d, zwrot CAPEX ~60 dni) |
| Model partii ropy | pełny łańcuch stanów partii `at_well→…→consumed` + `oil_movements` | bilans bbl | bilans bbl + DECIMAL(16,4) wszędzie, jedno zaokrąglenie przy kredycie |
| Fallback bez huba | odrzucić `applyFallback` | decyzja P0 | jawna flaga `fallback_direct` (domyślnie WYŁ) |

Rozbieżności liczbowe są naturalne (wszyscy zastrzegają „do kalibracji w `game_balance`") — ale rząd wielkości cystern (25 vs 500 bbl) MUSI być rozstrzygnięty, bo zmienia sens całego transportu. Rekomendacja: skala Qwena (zweryfikowana liczeniowo wobec ceny 250 PLN/bbl i produkcji 40–160 bbl/h z §9.5), bo GPT-owe 25 bbl przy odwiercie 40 bbl/h oznaczałoby ~2 kursy/h na odwiert (niegrywalna mikromanagementem).

## 3. REKOMENDACJA SYNTEZY

**Baza: propozycja QWEN** (najpełniejsza: LOG-0 zasada bilansu, kanoniczny stack mnożników LOG-5.4, fazy 2.1–2.8 ticka, katalog `reason_code` FTS, 12 testów LOG-T, liczby po weryfikacji skryptem) **+ z GPT:** niezmienny rejestr `oil_movements` i stany partii ropy (`at_well→reserved_inbound→at_hub→reserved_outbound→at_storage→reserved_sale|reserved_b2b→consumed`), tabela indeksów DB i kryteriów odbioru, separacja P(sukces sabotażu)/P(wpadka) **+ z DeepSeek:** decyzje P0 (ETA jako realny czas vs tick, delta-time dla każdej stawki, FTS dla KAŻDEJ opłaty logistycznej bez wyjątku) i lista 6 niedoróbek legacy jako testy regresji.

Numeracja w dokumencie: wstawić jako **nową sekcję po §11** (Qwen „§11A") LUB dołączyć jako §31 z twardymi cross-refami — do decyzji przy wklejaniu (nie §15 — kolizja z CMS).

## 4. DECYZJE DO PODJĘCIA PRZED KODOWANIEM LOGISTYKI (scalone D1–D12 Qwen + 8 GPT + P0–P3 DS)

1. **D1 Model własności hubów:** `npc/owned/leased` (rekomendowane) — usuwa 3 sprzeczne stany prawdy legacy.
2. **D2 Leg-2 przy hubu wielonajemcowym:** własność właściciela huba, najemcy płaczą `tariff_per_bbl`; wybór trasy per gracz (nie per hub — błąd etap11).
3. **D3 Przerwany łańcuch:** kaskada `paused_storage → spill (udokumentowany) → choked` + jawny `fallback_direct` domyślnie WYŁ.
4. **D4 Precyzja:** wolumeny DECIMAL(16,4), kondycja DECIMAL(9,5), jedno zaokrąglenie przy kredycie do magazynu; bilans `wydobyte = magazyn + transit + bufory + udokumentowane straty` uzgadniany CO TICK.
5. **D5 Rozliczenie dostawy:** dwufazowy kredyt + `idempotency_key (tick_no, trip_id)` dla kursów/rejsów/B2B/portów.
6. **D6 Jeden kanoniczny stack mnożników** (jedna funkcja): bazowy → typ → kondycja → tryb → obsada → budżet §10 → strajk §5.1 → ochrona §7 → wydarzenia §25; golden-master test.
7. **D7 Klucze RNG domen logistycznych** (`logistics|trip:{id}` itd.) — replayowalność incydentów.
8. **D8 `waiting_bay`:** grace 30 min → 2 000 PLN/h po stronie zlecającego (§21), limit zatok per hub.
9. **D9 B2B = realny kurs** z ETA; `delivery_receipt` > auto-refund; ubezpieczenie ładunku opcjonalne.
10. **D10 Ochrona/sabotaże:** clamp [0,05;1,0], walidacja celu per `target_type` przy ZAKUPIE (nie po), limit 5/7dni okno kroczące, `route_block` = opóźnienie nie strata.
11. **D11 Inspekcje/pozwolenia:** inspekcja rurociągu ≤12h (przeterminowana = ×1,3 ryzyka + ukryte defekty); pozwolenie P2 per region (UNIQUE player+region, cooldown odmowy 12h); domyślna dostępność z generatora świata (nie `hub_permit_enabled=0`).
12. **D12 VIP auto-pętla:** dyspacz przy buforze ≥80% pojemności, priorytet leg-2, koniec VIP nie przerywa kursów w toku (§22.6/§23.7-3).
13. **Skala floty** (rozbieżność §2): przyjąć rząd wielkości Qwena (500/900/350 bbl) do kalibracji symulatorem.
14. **Miejsce sekcji w dokumencie:** §11A vs §31.

**Największa pojedyncza lekcja z legacy (Qwen, potwierdzona historią 48 bugów rund 3–4):** ~70% bugów to dwie klasy — „bilans bbl bez jednego punktu uzgadniania" i „mnożniki/stany bez kanonicznej kolejności". Obie zamykają decyzje D3–D6 PRZED pierwszą linijką kodu fazy 2.
