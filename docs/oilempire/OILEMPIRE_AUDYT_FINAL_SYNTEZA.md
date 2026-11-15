# Audyt finalny specyfikacji — synteza trzech modeli + lista wdrożonych poprawek

**Data:** 2026-11-13 · **Zakres:** cały dokument `OILEMPIRE_ARCHITEKTURA_SYSTEMOWA.md` (~2 100 linii)
**Źródła:** `OILEMPIRE_AUDYT_FINAL_GPT.md` (41 znalezisk), `OILEMPIRE_AUDYT_FINAL_DEEPSEEK.md` (33), `OILEMPIRE_AUDYT_FINAL_QWEN.md` (48). Przebiegi czyste (bez awaryjnego przekierowania modeli). Tryb szybki: prompt 223 KB, rozumowanie medium, limit 40 znalezisk na model.

## Werdykt ogólny

Dokument jest **spójny w zrębie i gotowy do implementacji** po tej rundzie poprawek. Wszystkie trzy modele potwierdziły brak błędów w: bilansach masowych receptur (A: 28+26+6+32+4+4=100 ✓, B: 42+30+14+6+4+4=100 ✓), limicie B2C (10M × 0,5% = 50 000 ✓), kotwicy kartelowej (1 500 × 250 × 1,2 = 450 000 ≈ 1,125 × 400 000 ✓), progach jakości i widełkach EPC (F: 0,65×0,95=0,6175 ≥ 0,60 ✓). Znaleziska dotyczyły: rozjazdów między nowymi sekcjami a starszymi, braków w maszynach stanów, trzech błędów arytmetycznych i kilku niedefiniowanych pojęć użytych we wzorach.

## Wdrożone poprawki (57 łat w dokumencie + 21 w `game_balance`)

### Błędy krytyczne (naprawione)
1. **Pozwolenie P2 — dwa różne terminy i zakresy** (§11A.2: ~12 h vs §7.3: 7 dni) → kanon §7.3; dodana mapa zakresów: P1 = koncesja+odwiert, P2 = hub/rurociąg/rafineria.
2. **Brak stanu `idle_standby` i kolumny `epc_contract_id` w schemacie `refineries`** (§31.8) mimo wymogu §31.2.3/§31.3 → dodane; pełna maszyna stanów rafinerii opisana w §31.2.
3. **RARS ≥90 vs klasa lotnicza 92–100 „tylko do RARS"** → rozdzielone: kontrakty lotnicze ≥92, RARS ≥90 dla wszystkich produktów; partie 90–91 nie są lotnicze.
4. **Kolizja zegarów B2B** (ETA+24h vs reklamacja 12h vs settlement T+12h vs freeze 72h vs limit 168h) → nowa zasada #5 w §13.2: o 168h autorozstrzygnięcie przerywa WSZYSTKIE zegary; freeze nigdy nie przekracza limitu; rozliczenia GM po terminie przez `B2B_GM_AWARD` poza escrow; kolejność przy T+12h: najpierw reklamacje złożone do terminu włącznie, potem settlement.
5. **Kara anulowania bez źródła przy pustych kontach** → BANK→CASH płatnika, niedobór = zobowiązanie 72h; escrow drugiej strony zwracany niezależnie.
6. **Podwójny dyskont 70% przy wycenie rafinerii** (§31.7 już zawiera ×0,70; §19.1 mnoży ×0,70 ponownie) → jawny zakaz podwójnego dyskonta; rafinerie dopisane do listy aktywów §19.1.
7. **Faza 3 egzekucji nie obejmowała rafinerii** → dodana (po odwiertach; 60% wyceny §31.7; działka/zapasy/polisa/kontrakty przechodzą na nabywcę).
8. **`dormant` bez rozróżnienia powodów** (brak gotówki vs ban vs anonimizacja vs moduł) → `dormant_reason` + osobne reguły per powód.
9. **Przepełnienie huba = rozlanie vs polityka offline „ropa nie wycieka"** → precedens: zwykłe nasycenie zatrzymuje dopływ kaskadą bez strat; rozlanie wyłącznie incydent/sabotaż.
10. **Sprzeczne skutki sabotaży** (§7.1 vs §11A.4/§11A.7): napad = przejęcie do 30% ładunku (nie 100%); sabotaż zaworów = jeden kanon §11A.7.
11. **Fałszywa arytmetyka progu opłacalności rurociągu** („1 440 bbl/dobę, zwrot 60 dni") → poprawnie: break-even ≈ 2 060 bbl/dobę; zwrot 600k w ~60 dni wymaga ≈ 4 760 bbl/dobę (≈99% mocy rury standardowej).
12. **Bankructwo: 48h (§17.3) vs 14 dni dormant (§23.5)** → 48h uruchamia `recovery_mode`; `bankruptcy` przy majątku <0 lub 14 dniach `dormant`.
13. **`drip_30d` z FTS vs zakaz pojedynczego wpisu konwersji (§24.3)** → źródło: księga `gbbl_conversions`, nie FTS.
14. **INCOME_SET bez przychodów rafinerii i `B2B_GM_AWARD`** → Faza 1 egzekucji obejmuje teraz wszystkie wpływy; dopisane wyjątki CASH-first (ochrona §7.2, EPC-F 25%) i OPEX rafinerii w kolejności BANK→CASH.
15. **`REF_EPC_WARRANTY_REPAIR` — kod, który „istnieje i nie istnieje"** → usunięty; 4 kody EPC scalone do głównej tabeli §31.4; naprawy gwarancyjne = rejestr kosztów systemowych bez przelewu.
16. **Klasa I mogła montować kraking** (złamanie progresji: marża 20→86+) → restrykcja: Klasa I tylko `distillation_column`; kraking/hydroodsiarczanie od Klasy II.
17. **Zmiana wykonawcy EPC bez reguł** → tylko przed opłaceniem kolejnego etapu; opłacone etapy zamrożone; zmiana klasy = nowy kontrakt; cap katalogu 1,40×; zdarzenia deterministycznie ustalone wersją balansu przy podpisie.
18. **FTUE fizycznie niewykonalny i z niekanonicznymi cenami** (koncesja 400k vs P1 150k; wiercenie 1,2M/8–16h vs §9.5 800k/6h; OPEX 12 vs 30 PLN/bbl; „stały 300/h" bez źródła; brak huba i cysterny w pakiecie) → pakiet zawiera teraz: 7 dni miejsca w hubie NPC + używaną cysternę 500 bbl z kierowcą + 800 bbl w buforze (kredyt w `oil_movements`); budżet przeliczony kanonicznie: łańcuch 1 590 000 + OPEX tyg. ~394 000 + druga nitka (frontier 0,75×) ~1 150 000 = **~3 134 000 ≤ 4 000 000** (bufor ~866 000).
19. **Błędna marża Wariantu A** (nafta 65 pkt liczona ×0,92 zamiast ×0,55) → kosz hurtowy ≈261 PLN/bbl, marża hurtowa ≈11; detal z mnożnikiem jakości → ~85,5 PLN/bbl; Klasa I netto ~5,2 tys./h, zwrot ~144 dni; Klasa III netto ~59,0 tys./h, zwrot ~170 dni (poprawione też w `game_balance`).
20. **Koszt cysterny opancerzonej 8,10 < koszt samego kursu 10,00** → 10,50 (3 500÷350 + 0,2%×250); wyprowadzenie wzoru kosztu efektywnego w tabeli; tryb awaryjny ~15,5 (2×4,00 + 3%×250) zamiast ~6,3.
21. **Zapętlenie wzoru morale działu** (średnia firmy liczy średnią działów, która liczy średnią firmy) → średnia firmy ze snapshotu poprzedniej doby (lag-1).
22. **Rozjazdy nazw stanów**: `in_delivery` → `in_transit` (2 miejsca), `choked` dopisany do enum odwiertu (§9.1, §18.1), łańcuch stanów kursu §11A.8 (`załadowany → w_trasie → waiting_bay → kredytowany → dostarczony` + `odholowany`), `paused_funds` → `building_paused_funds`.

### Błędy istotne (naprawione)
- Zdefiniowane pojęcia użyte we wzorach bez definicji: `flaga_szarej_strefy` (BMS ≥30 lub aktywny EPC archetypu F — dopisane przez audyt do §7.3 nie było, wzór pozostawiono z nazwami opisowymi — PATRZ „do decyzji"), `hr_bonus_90d`/`training_180d`/`successful_mediation` = flagi 0/1, zanikanie kar P = `max(0, K0 − regen×dni)`.
- §31.3: `processed_bbl_since_regen` BIGINT → DECIMAL(16,4); flara spala frakcje proporcjonalnie do receptury (LPG → gazy → mazut) z zapisem per frakcja.
- §31.6: wzór składki i sumy ubezpieczenia, kolejność limitów wypłaty `min(85%×szkoda, suma, limit 50M/24h, saldo puli)`, stany polisy.
- §31.8: `reformer`/`coker` oznaczone jako v2 (brak receptur w v1); `policy_status` z komentarzem stanów; komentarz statusów rafinerii zgodny z §31.2.
- §31.9: faza 2.9 tylko nalicza fizykę; FTS w fazie 5 (kanon §23.3).
- §31.10: dopisane testy R7–R12 (CAS EPC, atomowość transzy F, podwójna wypłata z puli, flara offline, snapshot 3w1, kołnierz kartelowy) — nagłówek „R1–R12" teraz prawdziwy.
- §24.5: mechanizm zobowiązań operacyjnych `obligations` (rejestr, 72h, windykacja jak `late`); telemetria przenumerowana.
- §22.6.1: przedziały Tierów półotwarte (dokładnie 1 mld = Tier IV); pola snapshotu `tax_base_grosze`, `shield_base_grosze`, `inventory_valuation_version`; limit „1 wymiana na dobę UTC" (nie „na 24h").
- §19.2: wzór majątku netto + magazyn startowy = pojemność (0 bbl); §19.1.1: „0,008 p.p." zamiast „0,008%".
- §13.2: `resolved_split` z niezmiennikiem `paid+refunded+lost=wolumen`; `quality_score=NULL` dla ropy surowej; odwołanie kont powiązanych → §14.1/§22.6; sankcja kartelowa = −15 credibility ORAZ −15 b2b_reputation (dwie skale).
- §26.3: agregacja limitu dobowego (transze `settled` + `in_transit` w oknie UTC).
- §2: lista wzrostów wiarygodności += 2FA (+5) i wyjście z recovery (+8); wyjątek propagacji 2FA→B2B; §17.4: eskalacja blowout jednym rzutem zagregowanym; §25: wydarzenia w fazie 3 (nie 8); §29: heartbeats→sweeps; §29.6: ref D1→§31.1.
- §14: pożyczka rozruchowa 500k na warunkach §17.1; 2FA obowiązkowe dla WSZYSTKICH ról GM; blokada IP wyłącznie panel Filament; §3.1 headhunter 25 GBBL/dobę; §3 inspektor BHP (HSE) w strukturze.
- §11A.2: jawny wzór `koszt_dzienny_miejsca = utrzymanie zł/h × 24 ÷ miejsca`; §9.4 krok 4a (przypisanie do huba przed rurą).

## Weryfikacja domknięcia (14.11.2026)

Systematyczny przegląd wszystkich **38 znalezisk krytycznych** (GPT 15, DeepSeek 8, Qwen 15) testami tokenowymi po dokumencie: **38/38 ZAMKNIĘTYCH** (w tym: freeze ≤72h nigdy ponad limit 168h — zasada #5 §13.2; pełna blokada IP niestosowana wobec graczy + 2FA obowiązkowe dla wszystkich ról GM §14.3; RARS ≥90 vs lotnicze ≥92 rozdzielone decyzją, nie błędem). Dodatkowo domknięty ostatni brak systemowy: **§29.7 Panel „Balans Gry"** — pełny CRUD parametrów z four-eyes, diff, rollbackiem, trybem awaryjnym i bramką symulatora (wielokrotny wymóg użytkownika: edycja parametrów z panelu jak w starej grze).

## Do decyzji / na etap implementacji (nie naprawiane automatycznie)
1. **Definicje `flaga_szarej_strefy` i `incydent_środowiskowy_180d`** (użyte we wzorach §7.3): propozycja DeepSeek — flaga=1 gdy `black_market_score ≥30` lub aktywny kontrakt EPC archetypu F; incydent = `class IN (major, catastrophe) AND type IN (surface_spill, blowout)` w 180 dni. Do zatwierdzenia i wklejenia.
2. **26 parametrów null** z `game_balance` v1 (m.in. mnożniki wydarzeń §25, `procedure_integrity`, czasy TTS, premia czarnego rynku ropy, wagi `credit_score`, delta reputacji B2B za `settled`) — sesja kalibracyjna.
3. Globalny porządek blokad między typami zasobów (§23.5) i osobna tabela kluczy idempotencji przy partycjonowaniu (§29.4) — do spisania przy implementacji.
4. Generator kontraktów systemowych NPC (§13.1) — kto tworzy oferty rafinerii NPC.
5. Rename pliku `OILCORP_…` → `OILEMPIRE_…` (P3-8) — decyzja właściciela.
6. Koszty szkoleń §6 (10k–100k) vs kursy rafineryjne §31.7 (20k/50k/100k) — spójne, ale pełna tabela cen kursów nierafineryjnych do kalibracji.
