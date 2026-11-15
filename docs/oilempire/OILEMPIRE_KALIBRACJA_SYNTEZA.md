# Kalibracja 26 nulli + 3 technikalia — synteza trzech modeli i decyzje wdrożone

**Data:** 2026-11-14 · **Źródła:** `OILEMPIRE_KALIBRACJA_PROPOZYCJA_GPT.md` (301 ln), `..._DEEPSEEK.md` (366 ln), `..._QWEN.md` (207 ln) — przebiegi czyste, 12/12 zadań każdy. **Wdrożone:** 12 bloków w dokumencie + ~90 nowych/wypełnionych parametrów w `OILEMPIRE_GAME_BALANCE_V1.yaml` (z 26 nulli zostało 10 wskaźników celowych „kanon w §7.2" + 1 „brak górnego progu").

## Decyzje scalające (co wygrało i dlaczego)

| Zadanie | Baza | Odrzucone | Uzasadnienie |
|---|---|---|---|
| K1 Wydarzenia §25.1 | **Qwen** | DeepSeek (wojna/embargo OBNIŻAŁY cenę ropy — kierunek ekonomicznie błędny przy szoku podażowym); GPT częściowo | Qwen jako jedyny zweryfikował arytmetykę kotwic: kryzys 336×0,29−250×0,29 ≈ 25 ✓, boom ≈128,6 ∈ [120;150] ✓. Od GPT: kryzys **wyłączny** (nie stackuje) + clamp iloczynu [0,25; 2,20] |
| K2 Czarny rynek | Qwen + GPT | premia 12% (GPT) i 20% (Qwen) → **15% compromise**; legacy score 3–8 odrzucone jednogłośnie | Poniżej progu 30 handel jest bez ryzyka kontroli, więc premia musi być limitowana wolumenem (limit GPT: 5 transakcji / 1 500 bbl/dobę ≈ max 56 tys. PLN/dobę zysku) — wentyl płynności, nie faucet. +2/transakcję i decay −1/24h (utrzymane; z 50 pkt resocjalizacja 50 dni — spójne z zero-wipe) |
| K3 Reputacja B2B | **Qwen** (progi wartościowe transzy) | GPT (płaskie +2) | +1/+2/+3 wg wartości transzy (<500k / ≤5M / >5M PLN) → 50→75 w ~12–13 dostawach ✓ cel 10–15. Od GPT: spóźnione-but-valid +1, `resolved_split` +0. Od DeepSeek: nagroda premium = kaucja 5% zamiast 10%. Progi: 75 premium / 25 blokada / 10 blacklist 14 dni |
| K4 credit_score | **Qwen** | DeepSeek (start 67,5 = tier 60–79 → 10 mln kredytu na starcie przy pakiecie 4 mln — zbyt hojne, wbrew własnemu uzasadnieniu); GPT (poprawne, ale bez mechanizmu bonusa 100–115) | Tylko Qwen wyjaśnia zakres do 115: `clean_bonus` +1,25/mies. bez `late`, cap +15 — jedyne źródło nadwyżki nad 100 (§2). Start 48,5 = tier 40–59 (max 2 mln) ✓ spójne z pakietem startowym |
| K5 BHP poziomy | **Qwen** (suma 3,07 mln) | DeepSeek (28,5 mln — 228 dni marży Klasy I, za stromo); GPT (2,7 mln — zbliżone) | Qwen/GPT zbieżne ~3 mln ≈ 38% odwiertu deep; Qwen ma czasy + katastrofa: −1 poziom, odbudowa 50% (Qwen+DeepSeek 2:1 vs GPT „bez utraty poziomu") |
| K6 procedure_integrity | **Qwen** | DeepSeek (zużycie 5/dobę standard → 20 dni do zera, za agresywne); GPT (audyt +20 za 60k) | Qwen: 0,4/dobę × budżet (1,8/1,0/0,6) → 100→70 w ~75 dni (standard) / ~42 (low) — „cięcia widoczne w horyzoncie miesiąca, nie roku"; dwa stopnie naprawy (audyt 25k/+35, kalibracja 60k/do pełna); uderzenia incydentów −2/−5/−15 |
| K7 TTS | **Qwen** | GPT (materiały ~2× droższe, wymiana rury 180k = 72% nowej — za drogo); DeepSeek (materiały ~10× tańsze — ×2,5 firmy zewnętrznej traci sens) | Tylko Qwen podał EFEKTY zadań (wear −5, condition +25, road_loss −20%/30 dni) i zakotwiczył wymianę rury: 110k = 44% nowej 250k. Od GPT zasada: `bhp_calibration` = audyt PI — jeden koszt, bez podwójnego naliczania |
| K8 Budżety | **Qwen** + uzupełnienie moje | GPT (koszty 20–80 PLN/os/dobę — za małe wobec stawek §10.2); DeepSeek (tylko BHP) | Qwen zweryfikowany: 15 osób standard ≈ 275 PLN/h ≈ 5% marży Klasy I. **Moja decyzja:** dodałem brakujące u Qwena koszty budżetu Technicznego (100/220/400) i Logistyki (80/180/320 PLN/os/dobę) w jego skali — §10.2 obiecuje 4 budżety. Od GPT: brak środków → zobowiązanie 72h |
| K9 Płace | **Qwen** (realia PL 2025 ×1,8) | GPT (7–18k — za tanio wobec skali gry); DeepSeek (pośrednie) | Qwen z pełną kotwicą: obsada Klasy I ≈ 2,6% marży, pełna załoga ~8%; osobowości zawężające `expected_salary` (loyal 1,0–1,2 / ambitious 1,3–1,6) + seed HMAC. **Moja decyzja:** zarząd przemapowany na NASZYCH 5 dyrektorów (Qwen miał CEO — w grze nie istnieje): finanse 38k, techniczny 38k, prawny 36k (dodałem — brak u Qwena), logistyka 34k, HR 34k; offshore ×1,35 (od DeepSeek) |
| T1 Porządek blokad | **Qwen** (§23.5a) | DeepSeek (kontrakty na rank 30 — przed kontami, niespójne z przepływem escrow) | Qwen najpełniejszy: ranki 10–130, sweeper rosnąco po `companies.id`, `innodb_lock_wait_timeout` 10s, cap transakcji 30s, 3 retry z jitterem, `deadlock_log` + przegląd w Filament, bloker code review. Od GPT: klucz idempotencji PRZED kontami (grupa 20) i zakaz `SKIP LOCKED` w rozliczeniach |
| T2 Idempotencja FTS | **Qwen** (§29.4a) | DeepSeek (retencja 90 dni wszystkiego — łamie zero-wipe dla zdarzeń domenowych); GPT (bez przejmowania zawieszonych kluczy) | Qwen: BLAKE2b-128, przejęcie `pending` >60s warunkowym UPDATE (CAS na affected_rows), 409 przy zgodnym `committed`. **Moja decyzja (z GPT, ważne):** retencja rozszczepiona — klucze zdarzeń domenowych (sweepy, transze, raty) TRWALE (zero-wipe), jednorazowe HTTP: committed 30 dni / failed 24h |
| T3 Generator NPC | **Qwen** (§13.1a) | DeepSeek (generacja co ~15 min — nadprodukcja ofert; wolumeny 500–50 000 bbl — niedostępne dla startu) | Qwen spójny z kanonem: faza 4, katalog NPC bez kont (jak EPC), pula `3+floor(gracze/25)` max 8/region, TTL 24–72h, fizyka §11A (bez teleportu), kody FTS, podtypy aviation ≥92 ×1,20 / RARS ≥90 +20%. Od GPT: **tier startowy transz 240–960 bbl** — kontrakt osiągalny dla świeżej firmy z odwiertem 40 bbl/h (6h produkcji na transzę) |

## Gdzie wklejone w dokumencie

* §25.1 — tabela mnożników wydarzeń + stackowanie (K1)
* §12.4 — parametry skupu czarnorynkowego: premia 15%, limity dobowe, score/decay (K2)
* §13.1a — generator kontraktów NPC (T3) · §13.2 — delty reputacji B2B + progi (K3)
* §17.1 — wzór credit_score z clean_bonus i capami (K4)
* §11.1/11.2/11.3 — BHP koszty, procedure_integrity, katalog TTS z efektami (K5–K7)
* §10.2 — tabela kosztów i efektów 4 budżetów + cooldown (K8)
* §4.1 — widełki min_market_salary + expected_salary z osobowościami (K9)
* §23.5a — globalny porządek blokad (T1) · §29.4a — `fts_idempotency_keys` (T2)

## Rozbieżności między modelami warte odnotowania
* **Kierunek szoku:** DeepSeek jako jedyny obniżał cenę ropy przy embargu/wojnie (0,70/0,50) — odrzucone jako sprzeczne z ekonomią szoku podażowego i z §21 (kryzys = jedyny event taniejący).
* **Skala kosztów BHP:** rozstrzał 10× (Qwen 3,07 mln vs DeepSeek 28,5 mln) — rozstrzygnięty kotwicą „bezpieczeństwo tańsze od odwiertu deep, droższe od rury".
* **Tempo zużycia PI:** 0,4 (Qwen) vs 1,0 (GPT) vs 5,0 (DeepSeek) pkt/dobę — 12× rozstrzał; przyjęte najwolniejsze z uzasadnieniem horyzontu miesięcznego (audyt i tak jest tani, więc szybkie zużycie zamieniłoby grę w klikanie audytów).
