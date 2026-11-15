│ cache\scratch\b2b_math.py
│ @@ -0,0 +1,12 @@
│ +val = 10000 * 250
│ +print('prowizja 1.5% z 250/bbl:', 0.015 * 250)
│ +print('kartel max/dobe 1500bbl*250*1.2 =', 1500 * 250 * 1.2)
│ +print('Kroplowka Tier V 400*1000 =', 400 * 1000)
│ +print('zysk zerwania -8%:', val * 0.08, '| kara 10%:', val * 0.10, '| netto:', val * 0.08 - val * 0.10)
│ +print('os czasu 24+72+72 =', 24 + 72 + 72, 'h')
│ +print('oplata B2C 0.5% z 10M =', 0.005 * 10_000_000)
│ +print('marza ubez 0.8% - 0.5%*0.85 =', 0.008 - 0.005 * 0.85)
│ +print('escrow transzy 5000*250*1.05 =', 5000 * 250 * 1.05)
│ +print('0.5% z 2.5M =', 0.005 * 2.5e6)
│ +print('5% z 2.5M =', 0.05 * 2.5e6)
│ +print('transport 1500 bbl * 4.00 =', 1500 * 4.0)
Zweryfikowałem arytmetykę wszystkich poniższych liczb (wyniki obliczeń: 3,75 zł/bbl prowizji; 450 000 vs 400 000 zł/dobę pralnia vs Tier V; 24+72+72=168 h; −50 000 zł netto arbitrażu przy −8%; marża ubezpieczyciela 0,375%). Projekt jest spójny z kanonem: franszyza ubezpieczeniowa 15% (§31.6), jakość deterministyczna 0–100 z progami 70/85/92, konta `from_account`/`to_account`, brak debetu, `sweep_no` + `idempotency_key` + CAS.

---

# WĘZEŁ PIENIĘŻNY B2B — PROJEKT KOMPLETNY

## 1. MASZYNA STANÓW OFERTY B2B

### 1.1. Zasady nadrzędne (wiążące dla każdej tabeli poniżej)

1. **Własność ropy przenosi się WYŁĄCZNE w momencie `delivery_receipt`** (fizyczny rozładunek u kupującego, §11A.8). Do tej chwili: właściciel = sprzedający, ryzyko utraty = sprzedający, lokalizacja = wpis w `oil_movements`/`road_trips`/`marine_voyages`. Konsekwencja: odszkodowanie ubezpieczeniowe za ładunek zawsze dla sprzedającego (§6.4), a egzekucja wobec sprzedającego NIE dotyka ładunku w tranzycie B2B (odpowiednik `locked_in_escrow_bbl` w §17.2 — rozszerzenie na stan `cargo_in_b2b_transit`).
2. **Pieniądze kupującego są wydzielone prawnie od `accepted`** (PLN_ESCROW per transakcja; §21 — komornik ich nie zajmuje do rozliczenia/zwrotu).
3. **Twardy limit blokady escrow: 168 h od `accepted` DANEJ TRANZY** (nie od kontraktu), obejmujący `disputed`. Uzasadnienie arytmetyczne: 24 h (termin wysyłki) + 72 h (max transport §13) + 72 h (max `waiting_bay` u kupującego) = 168 h — najdłuższa legalna ścieżka fizyczna mieści się w limicie dokładnie, bez luzu na parkowanie escrow. Po 168 h automat rozstrzyga wg macierzy dowodowej (§1.4) — escrow NIGDY nie jest schronem (§21).
4. Każde przejście: jedna transakcja DB (§23.5), CAS na `state_version`, `idempotency_key = (offer_id, tranche_no, target_state, sweep_no)` — leniwe rozliczenie i sweeper dają dokładnie jedno przejście (wzór T14 §11A.11).
5. Wypłaty rozliczeniowe trafiają na **PLN_BANK** (B2B = biała gospodarka; PLN_CASH jest wyłącznie dla wpływów czarnorynkowych §24.2). Zwrot escrow również na PLN_BANK kupującego.

### 1.2. Oferta spot (jednorazowa) — tabela przejść

Stany: `draft → published → accepted → in_transit → delivered → settled`; wyjątki: `cancelled`, `expired`, `disputed → resolved_settled | resolved_refunded | resolved_split`, `refunded`.

| # | Przejście | Wyzwalacz / decydent | Ropa: właściciel, lokalizacja, stan | Pieniądze | Rezerwacje | Wpisy FTS / oil_movements | Timeout |
|---|---|---|---|---|---|---|---|
| 1 | draft→published | publikujący (gracz); walidacja: wolumen ≤ wolne `at_storage`, cena ≥ 1 grosz/bbl | sprzedający, magazyn główny, `at_storage→reserved_b2b` | brak ruchu | `locked_in_escrow_bbl += vol` (blokada spot/limit/inny kontrakt §12) | oil_movements `B2B_RESERVE` | publikacja żyje max 7 dni (§13.2) |
| 2 | published→expired | automat (sweeper) | sprzedający, magazyn, `reserved_b2b→at_storage` | brak | zwolnienie `locked_in_escrow_bbl` | oil_movements `B2B_UNRESERVE` | 168 h od published |
| 3 | published→cancelled | wycofanie przez publikującego | j.w. | brak (escrow jeszcze nie istnieje) | zwolnienie | j.w. | — |
| 4 | published→accepted | kontrahent klika „przyjmij"; automat waliduje atomowo: (a) środki kupującego ≥ wartość, (b) odcisk konta niespokrewniony (§13.2), (c) u sprzedającego wolumen nadal `reserved_b2b` | sprzedający, magazyn, `reserved_b2b` | **100% wartości** kupującego → PLN_ESCROW_tx; źródło: PLN_BANK, brak → dopełnienie z PLN_CASH (kolejność sztywna) | `b2b_escrow_lock` (pieniądze) + `locked_in_escrow_bbl` (ropa) — oba już od published/accepted | FTS `B2B_ESCROW_LOCK` (1–2 linie: BANK, CASH) | od tej chwili zegar 168 h |
| 5 | accepted→cancelled (wysyłka nie nastąpiła) | kupujący po 24 h bez `road_trips`/`marine_voyages` powiązanej z ofertą; decydent: kupujący (przycisk) lub automat po skardze | sprzedający, magazyn, zwolnienie | zwrot 100% escrow kupującemu + **5% kary od sprzedającego** na PLN_BANK kupującego | zwolnienie obu rezerwacji | FTS `B2B_ESCROW_REFUND` + `B2B_DISPATCH_PENALTY` | termin wysyłki: 24 h od accepted |
| 6 | accepted→cancelled (dowolna strona, dobrowolnie) | strona anulująca; automat liczy karę (§2.2) | sprzedający, magazyn lub bufor, zwolnienie | kara = `min(40%, max(10%, |spot_teraz−cena_kontraktu|/cena_kontraktu)) × wartość escrow` z escrow anulującego → PLN_BANK drugiej strony; reszta escrow → zwrot | zwolnienie obu | FTS `B2B_CANCEL_PENALTY` + `B2B_ESCROW_REFUND` | do momentu wysyłki; po wysyłce anulowanie niedostępne (tylko spór) |
| 7 | accepted→in_transit | sprzedający tworzy kurs/rejs (§11A.4/11A.5), opłata transportowa przy wysyłce; automat spina `offer_id` z `trip_id` | **sprzedający**, w trasie, `reserved_b2b→cargo_in_b2b_transit`; ETA zapisane | brak ruchu escrow; koszt transportu z PLN_BANK sprzedającego (istniejące kody §11A) | ropa nadal `locked_in_escrow_bbl` | oil_movements `B2B_SHIP` (źródło: magazyn sprzedającego, cel: kurs) | termin dostawy = ETA + 24 h margines (§11A.8) |
| 8 | in_transit→delivered | fizyczny rozładunek w zatoce kupującego; **`delivery_receipt` generuje SYSTEM, nie gracz** (usuwa wyścig „potwierdzenie vs autozwrot" — T10 §11A.11) | **kupujący** (przeniesienie własności), magazyn kupującego, `at_storage` | brak ruchu (jeszcze) | zwolnienie `locked_in_escrow_bbl` sprzedającego | oil_movements `B2B_UNLOAD` (z `delivery_receipt_id`) | okno reklamacyjne 12 h od receipt |
| 8a | delivered→settled | automat po 12 h bez reklamacji jakości | kupujący, magazyn | escrow → netto sprzedawcy: `wartość − prowizja 1,5%` → PLN_BANK; prowizja → SYS_TREASURY; **jeśli sprzedawca w Fazie 1: netto dzielone 50/50 PLN_BANK/SYS_TREASURY w TEJ SAMEJ transakcji** | brak | FTS `B2B_SETTLE_NET` + `B2B_FEE` (+ `B2B_SETTLE_GARNISH` przy Fazie 1) | T+12 h od receipt |
| 9 | in_transit→partial_unload→delivered | magazyn kupującego ma miejsce tylko na część: rozładunek części → receipt częściowy → **częściowe settled** (proporcjonalnie: `escrow × bbl_rozładowane/bbl_transzy`, prowizja od kwoty zwolnionej); reszta czeka w `waiting_bay` | rozładowana część: kupujący; reszta: sprzedający, cysterna w zatoce | częściowe jak 8a | częściowe zwolnienie | FTS `B2B_SETTLE_NET`(część) + `B2B_FEE`(część); oil_movements `B2B_UNLOAD`(część) | reszta: patrz 9a |
| 9a | waiting_bay → zwrot reszty | magazyn kupującego pełny: po 2 h opłata postojowa 2 000 PLN/h **po stronie kupującego** (§21), sprzedający bez kary za opóźnienie; po 72 h od przyjazdu automat: reszta wraca do sprzedającego | reszta: sprzedający (własność nie przeszła), powrót do jego magazynu/huba | reszta escrow → kupujący POMNIEJSZONA o naliczone postojowe i koszt frachtu powrotnego (potrącenie z kwoty zwrotu, capped at wartość reszty) | zwolnienie reszty | FTS `B2B_ESCROW_REFUND`(część) + `B2B_PARKING_FEE` + `B2B_RETURN_FREIGHT`; oil_movements `B2B_RETURN` | 2 h / 72 h |
| 10 | in_transit→lost→refunded | utrata całkowita (napad/wypadek/sabotaż §11A.4): stan kursu `utracony`, rzut ziarnem deterministycznym | **nikt** — partia `lost` z reason_code; bilans §11A.1 pkt 2 się uzgadnia | 100% escrow → kupujący (zwrot, nie przychód — bez splitu Fazy 1); sprzedający traci aktywum; jeśli miał ubezpieczenie: 85% wartości ładunku z SYS_INSURANCE_POOL → PLN_BANK sprzedającego | zwolnienie obu | FTS `B2B_ESCROW_REFUND` (+ `B2B_CARGO_INSURANCE_CLAIM`); oil_movements `B2B_LOSS`; reputacja: `b2b_reputation −8` sprzedającemu TYLKO jeśli utrata z jego winy (brak ochrony na trasie o ryzyku ≥ progu — config); utrata losowa mimo ochrony: 0 pkt | automat po utrwaleniu `utracony` |
| 10a | utrata częściowa | `road_partial_theft`/`częściowo_stracony`: rozliczenie częściowe za bbl dostarczone (ścieżka 9) + zwrot za bbl utracone (ścieżka 10) + ubezpieczenie 85% części utraconej | j.w. | j.w. | j.w. | j.w., jedna transakcja | j.w. |
| 11 | dowolny stan po accepted→disputed | otwiera strona (powód + dowody) LUB automat: (a) ETA+24 h bez receipt, (b) reklamacja jakości w 12 h, (c) sabotaż na trasie z flagą agresora | własność wg stanu w chwili otwarcia — **zamrożona**; ropa fizycznie tam gdzie jest (transit/bay/magazyn) | escrow i ropa ZABLOKOWANE do rozstrzygnięcia (§13.2) | bez zmian | wpis `b2b_disputes` (powód, dowody, `registry_snapshot_id`) | brak reakcji sprzedawcy 24 h (timeout dostawy) = auto `refunded` na korzyść kupującego (§13.2, −8 pkt); SLA GM 72 h; twardo: rozstrzygnięcie najpóźniej w 168 h od accepted |
| 12 | disputed→resolved_* | GM w panelu B2B (dowody: `oil_movements`, trasa, ETA, `delivery_receipt`, logi ochrony §11A.7, zapisy sabotaży §7) LUB automat po SLA/limicie wg macierzy §1.4 | wg rozstrzygnięcia | `resolved_settled`: jak 8a; `resolved_refunded`: jak 10; `resolved_split`: potrącenie jakościowe 0–20% wartości → kupujący, reszta → sprzedający | zwolnienie wszystkich | FTS `B2B_GM_RESOLVE` (GM: powód obowiązkowy, `gm_action_log`; dla kwot >1M PLN druga para oczu §18.3) | patrz 168 h |
| 13 | accepted→(bankructwo kupującego) | `bankruptcy` §17.3 kupującego przed wysyłką | sprzedający, zwolnienie | zwrot escrow do masy (PLN_BANK kupującego); **zwrot = nie przychód → bez splitu 50%**; ale saldo wchodzi do płynności/majątku netto masy | zwolnienie | FTS `B2B_ESCROW_REFUND` + znacznik `insolvency_no_fault` (bez kary, bez −pkt: bankructwo już dało −25 wiarygodności) | automat po wpisie bankruptcy |
| 14 | in_transit/delivered + bankructwo kupującego | j.w. po wysyłce | dostawa DOKAŃCZA się (ładunek poza masą — kupujący zapłacił przez escrow wydzielony §21) | settled normalnie do sprzedającego | — | jak 8/8a | bez zmian |
| 15 | bankructwo sprzedającego w trakcie | `bankruptcy`/Faza 2 sprzedającego | ładunek w tranzycie **wyłączony z licytacji Fazy 2** (`cargo_in_b2b_transit` obok `locked_in_escrow_bbl`); kurs dojeżdża do kupującego (analogia §21 „aukcja odwiertu a cysterny w trasie") | rozliczenie biegnie normalnie; wpływ sprzedającego do PLN_BANK podlega splitowi 50% Fazy 1 w tej samej transakcji | — | `B2B_SETTLE_NET` + `B2B_SETTLE_GARNISH` | bez zmian |

### 1.3. Kontrakt ratalny (transze)

Stany rodzica: `draft → published → active → completed`; wyjątki `cancelled`, `expired` (7 dni od published bez `active`), `terminated` (3 niewykonane transze z rzędu — kara i blokada przetargów 7 dni wg §13.1). **Każda transza = samodzielna oferta spot z tabeli §1.2** z własnym escrow, własnym zegarem 168 h (start: `accepted` transzy) i własnym `delivery_receipt` (§13.2 już to obiecuje — tu staje się egzekwowalne).

Dodatkowe reguły transz:
- **Harmonogram**: transza i ma `scheduled_dispatch_at`; aktywacja transzy (publikacja wewnętrzna + `accepted` automatyczne, jeśli kontrakt tak stanowi) następuje 24 h przed planowaną wysyłką.
- **Tryby cenowe** (§13.1): `fixed` — escrow = bbl × cena stała. `market_multiplier` / `market_plus_bonus` — escrow = bbl × (cena referencyjna spot z okna 60 min przed aktywacją × mnożnik + bonus); kupujący ma **24 h okno dofinansowania** (FTS `B2B_ESCROW_TOPUP`); brak środków po 24 h = transza `missed`: kara 5% wartości transzy + `b2b_reputation −6` (lustrzanie §13.1), escrow częściowy zwrócony.
- **Niedostarczenie transzy** ( accepted → 24 h bez wysyłki, albo ETA+24 h bez receipt z winy sprzedającego) = kara 5% + −6 pkt; **trzy z rzędu** → `terminated` + przepadek kaucji kontraktu (10% wartości kontraktu, `security_deposit` jak §13.1, trzymane na PLN_ESCROW kontraktu przez cały okres `active`) + blokada przetargów 7 dni.
- Kaucja 10% wraca sprzedającemu przy `completed` (FTS `B2B_DEPOSIT_RELEASE`).

### 1.4. Macierz autorozstrzygnięcia w 168 h (dowód = rejestr, nie zeznania)

Automat czyta `oil_movements` + `delivery_receipts` + flagi `waiting_bay` i wykonuje PIERWSZĄ pasującą regułę:

| Stan dowodów w rejestrze | Rozstrzygnięcie |
|---|---|
| receipt na 100% wolumenu | `resolved_settled` (pełne) |
| receipt częściowy | settled za część rozładowaną + refund reszty; ropa reszty → magazyn sprzedającego (`B2B_RETURN`, fracht z refundu kupującego jeśli winę ponosi kupujący — magazyn pełny — inaczej ze sprzedającego) |
| partia `lost` (utracona całkowita) | `resolved_refunded`; ubezpieczenie rozlicza się osobno |
| ładunek w zatoce kupującego, opłata postojowa naliczana (wina kupującego) | `resolved_settled` za wolumen przybyły + postojowe od kupującego; reszta → zwrot wg reguły 9a |
| ładunek w trasie, opóźnienie po stronie sprzedającego (ETA sprzedawcy przekroczone) | `resolved_refunded`, kurs przekierowany do magazynu sprzedającego (fracht sprzedającego), `b2b_reputation −8` |
| ropa wciąż w magazynie sprzedającego (nie wysłana) | `resolved_refunded` + kara 5% `B2B_DISPATCH_PENALTY` |
| dowody sprzeczne/rejestr niekompletny (błąd systemu — alarm §29.2) | freeze do GM, ale **nie dłużej niż +72 h**; po 72 h decyzja GM z `gm_action_log`, domyślnie refund |

---

## 2. PARAMETRY LICZBOWE (wszystko w `game_balance`, wersjonowane §29.5)

| Parametr | Wartość | Uzasadnienie |
|---|---|---|
| Prowizja bazowa B2B (`b2b_fee_pct`) | **1,5% wartości rozliczenia, płatna przez sprzedającego** (potrącana z escrow przy settled) | 1,5% × 250 PLN/bbl = **3,75 PLN/bbl** — między kosztem rurociągu standardowego (1,90) a cysterny (4,00) z §11A.6. Prowizja niższa niż oszczędność slippage'u market-makera (§19.3) przy wolumenach >8 000 bbl, więc handel B2B pozostaje opłacalny; wyższa niż 0 — §26.3 ma z czego zwalniać (naprawa usterki „prowizja nigdzie niezdefiniowana"). Pobór od sprzedającego (nie 50/50), bo sprzedający jest beneficjentem ceny — kupujący widzi czystą cenę ofertową. |
| Kara za anulowanie po `accepted` (`b2b_cancel_penalty`) | `min(40%, max(10%, |spot_now − cena| / cena)) × wartość escrow`, na rzecz **drugiej strony** | Likwidacja arbitrażu: przy spadku ceny o 8% zysk z zerwania i odkupienia na spocie = 8%, kara = max(10%, 8%) = 10% → **netto −2%** (dla kontraktu 10 000 bbl × 250 = 2,5M: zysk 200 000, kara 250 000, netto −50 000) plus `b2b_reputation −6`. Przy ruchu 15%: kara = 15% = zysk → arbitraż zerowy przed reputacją = nieopłacalny. Kierunkowa formuła „cover" (różnica odkupienia) sprawia, że kara ROŚNIE z ruchem ceny. **Cap 40%**: przy wydarzeniu −70% (§21/§25) pełna kara zniszczyłaby stronę — rezydualny arbitraż w ekstremum jest świadomym ryzykiem, mitygowany tym, że wydarzenia mają jawny `starts_at` (§25), a anulowania w oknie wydarzenia trafiają do telemetrii GM (§29.3). |
| Kara za niewysłanie w 24 h | 5% + zwrot 100% escrow | Spójne z §13.1 (kara umowna 5% wartości transzy) — jedna liczba w całej domenie kontraktów. |
| Twardy limit escrow (`b2b_escrow_max_lock_h`) | **168 h od accepted transzy**, obejmuje `disputed` | 24+72+72 = 168 (wysyłka + max trasa §13 + max kolejka u kupującego §1.2/9a); symetrycznie z expiry 7 dni ofert `published` (§13.2) i postulatem recenzentów. |
| Termin wysyłki po accepted | 24 h | Najkrótsza sensowna reakcja w grze real-time; 1/7 budżetu escrow, zostawia 144 h na transport+spory. |
| Termin dostawy | ETA trasy (24–72 h §13) + 24 h margines | §11A.8 już tak stanowi; margines = bufor na `route_block` (30–120 min) i kolejki portowe. |
| Okno reklamacyjne jakości | 12 h od `delivery_receipt` | Krótsze niż dobowy cykl gracza (wieczór EU obejmuje dostawy dzienne), dłuższe niż 6 h cykli sweepera; po oknie `settled` nieodwracalne (immutable FTS §22.5). |
| Settlement po delivered | T+12 h (automat) | = koniec okna reklamacyjnego; zero ręcznych klików po stronie kupującego. |
| SLA sporu GM | 72 h, po nim autorozstrzygnięcie §1.4 | 72 h < 168−96 (spór otwarty najpóźniej przy ETA+24 h wciąż mieści się w twardym limicie). |
| Ubezpieczenie ładunku B2B | premia 0,8% wartości kursu (przy wysyłce); wypłata 85% (franszyza 15% — kanon §31.6); limit puli 50M/24h | Marża poola: 0,8% − (oczekiwana strata incydentowa 0,5% × 85%) = **+0,375%** na kurs — dodatnia przy średnim ryzyku §11A.5, a gracz i tak kupuje, bo strata 15% × 250 PLN/bbl × 8 000 bbl = 300 000 PLN boli bardziej niż premia 16 000 PLN. |
| Limit ofert `published` równocześnie | 5 (free) / 15 (VIP); kontrakty ratalne jako sprzedający: 2 / 5 | Wzorzec §22.6 (zlecenia limit: 3 free) — VIP mnoży ×3, cliff tylko dla NOWYCH, w toku dokańczają. |
| **Limit wewnątrz-kartelowy** | **bezprowizyjnie ≤ 1 500 bbl/dobę (00:00 UTC) na uporządkowaną parę członków** + **kołnierz cenowy [0,8×; 1,2×] spot referencyjnego z chwili publikacji**; powyżej któregokolwiek progu — pełne 1,5% | Rachunek pralni (B3): maksymalny legalny zastrzyk gotówki to Kroplówka Tier V = 400 GBBL × 1 000 = **400 000 PLN/dobę**. Kanał kartelowy: 1 500 bbl × 250 × 1,2 = **450 000 PLN/dobę** = 1,125× najwyższego legalnego tieru — pralnia nie daje już PRZEWAGI nad uczciwą Kroplówką, a kosztuje: fizyczny transport 1 500 bbl (4,00 PLN/bbl drogą = 6 000 PLN/dobę) + ryzyko incydentów + ślad w rejestrze. Nadfakturowanie ponad kołnierz (sprzedaż 100 bbl po 1 000 PLN) blokuje kołnierz: poza [0,8; 1,2]× nie ma zwolnienia I transakcja idzie do telemetrii. Poniżej progów zwolnienie realnie nagradza sojusz (§26.3 dotrzymane). |
| Sankcja za wzorzec pralniczy | 3 oflagowane transakcje w 7 dni → zawieszenie zwolnienia na 30 dni + `company_credibility −15` (obu stronom) + przegląd GM | −15 ≈ połowa kary za wejście komornika (−20, §17.2) — dotkliwe, ale nie kasuje firmy; 30 dni > 7-dniowa karencja rozwiązania kartelu (§26.3), więc „rotacja kartelem" nie resetuje kary szybciej niż ona trwa. |
| Transfery gotówka↔bank (§4) | C2B: 0%, natychmiast, bez limitu; B2C: 0,5%, natychmiast, max 10M PLN/24h | Patrz §4 z wyprowadzeniem. |

---

## 3. TABELA KODÓW FTS (nowe; wszystkie kwoty BIGINT grosze, double-entry, suma zerowa)

| reason_code | Opis | from_account → to_account | Jednostka | Skutek braku środków |
|---|---|---|---|---|
| `B2B_ESCROW_LOCK` | Blokada 100% wartości przy `accepted` (1 linia z PLN_BANK + opcjonalnie 2. z PLN_CASH) | PLN_BANK / PLN_CASH kupującego → PLN_ESCROW_tx | PLN | odrzucenie `accepted` (transakcja atomowa, oferta wraca do `published`) |
| `B2B_ESCROW_TOPUP` | Dofinansowanie escrow transzy rynkowej (okno 24 h) | PLN_BANK/PLN_CASH kupującego → PLN_ESCROW_tx | PLN | po 24 h transza `missed` (kara 5%, −6 pkt) |
| `B2B_ESCROW_REFUND` | Zwrot escrow (anulowanie, refund, bankructwo-kupujący) — **nie przychód: bez splitu Fazy 1** | PLN_ESCROW_tx → PLN_BANK kupującego | PLN | n/d (środki wydzielone) |
| `B2B_SETTLE_NET` | Rozliczenie (część) transzy po receipt | PLN_ESCROW_tx → PLN_BANK sprzedającego | PLN | n/d |
| `B2B_SETTLE_GARNISH` | Split Fazy 1: 50% rozliczenia sprzedającego-dłużnika; **w tej samej transakcji co SETTLE_NET** | PLN_ESCROW_tx → SYS_TREASURY | PLN | n/d |
| `B2B_FEE` | Prowizja giełdy 1,5% (także od transz ponad limit kartelowy — patrz `B2B_FEE_CARTEL`) | PLN_ESCROW_tx → SYS_TREASURY | PLN | n/d (potrącana z rozliczenia) |
| `B2B_FEE_CARTEL` | Prowizja od wolumenu/ceny ponad progi kartelowe (audyt: widać, że kartelowa) | PLN_ESCROW_tx → SYS_TREASURY | PLN | n/d |
| `B2B_CANCEL_PENALTY` | Kara kierunkowa (§2) — dla odbiorcy **przychód → split 50% przy jego Fazie 1** | PLN_ESCROW_tx anulującego → PLN_BANK drugiej strony | PLN | n/d (z escrow, zawsze pokryta) |
| `B2B_DISPATCH_PENALTY` | Kara 5% za niewysłanie w 24 h / nieuruchomienie transzy | PLN_BANK/PLN_CASH sprzedającego (kolejność jak rata §4) → PLN_BANK kupującego; brak pokrycia → **zobowiązanie 72 h → §17.1** | PLN | zobowiązanie 72h → windykacja (konwencja 3 — konta gracza nigdy < 0) |
| `B2B_QUALITY_DEDUCT` | Potrącenie jakościowe 0–20% (resolved_split) | PLN_ESCROW_tx → PLN_BANK kupującego | PLN | n/d |
| `B2B_CARGO_INS_PREMIUM` | Premia ubezpieczenia kursu 0,8% (przy wysyłce) | PLN_BANK/PLN_CASH sprzedającego → SYS_INSURANCE_POOL | PLN | ubezpieczenie NIE aktywnne, kurs rusza bez polisy (jawna flaga w UI) |
| `B2B_CARGO_INS_CLAIM` | Wypłata 85% wartości utraconego ładunku (franszyza 15%); limit 50M/24h (§31.6) | SYS_INSURANCE_POOL → PLN_BANK sprzedającego | PLN | n/d (backstop Skarbu <20M rezerw, §31.6) |
| `B2B_PARKING_FEE` | Postojowe 2 000/h u kupującego (§21) | PLN_BANK kupującego → SYS_TREASURY | PLN | narasta jako zobowiązanie (§21), po 48 h holowanie 10 000 |
| `B2B_RETURN_FREIGHT` | Fracht powrotny reszty (§1.2/9a) — potrącany z refundu kupującego przy jego winie | PLN_ESCROW_tx → SYS_TREASURY | PLN | capped at wartość refundowanej reszty; nadwyżka = zobowiązanie |
| `B2B_GM_RESOLVE` | Rozstrzygnięcie GM (dowolny podział escrow + korekty); powód obowiązkowy, >1M PLN druga para oczu | PLN_ESCROW_tx → PLN_BANK stron / SYS_TREASURY | PLN | n/d |
| `B2B_DEPOSIT_LOCK` / `B2B_DEPOSIT_RELEASE` / `B2B_DEPOSIT_FORFEIT` | Kaucja kontraktu ratalnego 10% (§13.1): blokada / zwrot po `completed` / przepadek po 3 `missed` | PLN_BANK sprzedającego → PLN_ESCROW_kontraktu → PLN_BANK sprzedającego lub SYS_TREASURY | PLN | brak kaucji = brak `active` (kontrakt nie startuje) |
| `XFER_C2B` | Wpłata gotówki do banku (§4) | PLN_CASH → PLN_BANK gracza | PLN | n/d (free) |
| `XFER_B2C` | Wypłata z banku do gotówki (§4) | PLN_BANK → PLN_CASH gracza | PLN | odrzucenie przelewu (brak debetu — konwencja 3) |
| `XFER_B2C_FEE` | Opłata za wypłatę 0,5% | PLN_BANK → SYS_TREASURY | PLN | j.w. (odrzucone łącznie) |
| `LOAN_INSTALLMENT_CASH` | Fallback raty z gotówki (§4.3) | PLN_CASH → SYS_TREASURY (bank NPC) | PLN | dopiero po wyczerpaniu obu kont → `late` §17.1 |

`oil_movements` (reason_code): `B2B_RESERVE`, `B2B_UNRESERVE`, `B2B_SHIP`, `B2B_UNLOAD`, `B2B_RETURN`, `B2B_LOSS` — każdy z `offer_id`, `tranche_no`, `sweep_no`, `idempotency_key`; bilans 0,0001 bbl (§11A.1).

---

## 4. TRANSFERY PLN_CASH ↔ PLN_BANK (naprawa A7)

### 4.1. Wpłata C2B (gotówka → bank)
- **Koszt 0%, czas natychmiast, bez limitu.** Uzasadnienie: gotówka jest kanałem czarnorynkowym i PODLEGA egzekucji (§24.2/§17.2), więc wpłata nie ukrywa niczego; opodatkowanie wpłat zachęcałoby do trzymania gotówki „pod materacem" — odwrotność celu. Każdy wpływ zewnętrzny na OBYDWA konta jest już objęty Fazą 1 (§17.2 „gotówka nie jest schronem").
- FTS: `XFER_C2B`. Przeliczanie Δt: brak (operacja dyskretna gracza).

### 4.2. Wypłata B2C (bank → gotówka)
- **Koszt 0,5%, natychmiast, limit 10 000 000 PLN/24 h (doba 00:00 UTC, jak Kroplówka §24.3).** Kotwice: 5× pakiet startowy gotówki (2M, §1); 10M = 40 000 bbl czarnorynkowych przy 250 PLN/bbl — więcej niż realny dzienny obrót czarnego rynku jednej firmy; opłata max 50 000 PLN/dobę = 200 bbl wartości — odczuwalna, nie zaporowa.
- FTS: `XFER_B2C` + `XFER_B2C_FEE` (jedna transakcja, 2 linie, suma zerowa).

### 4.3. Interakcja z egzekucją — dlaczego transfer NIE jest ucieczką
1. Faza 1 zajmuje 50% **każdego wpływu zewnętrznego na PLN_BANK i PLN_CASH** (§17.2). Zajęcie następuje RAZ, przy wejściu pieniądza do systemu gracza, wg `reason_code ∈ INCOME_SET` = {sprzedaż spot, `B2B_SETTLE_NET`, `B2B_CANCEL_PENALTY`, kontrakty systemowe, czarny rynek, Kroplówka}. 
2. `XFER_C2B`/`XFER_B2C` **nie należą do INCOME_SET** — przelew własny nie jest wpływem; bez tego doszłoby do podwójnego zajęcia (50% przy wpływie na CASH i drugie 50% przy przelewie do BANK). Regułę egzekwuje jedna funkcja `isGarnishableIncome(reason_code)` (odpowiednik `applyModifierStack` — jedno miejsce, zakaz rozsiewania).
3. Ucieczka jest niemożliwa konstrukcyjnie: oba konta zajęte w punkcie wejścia, przelewy między nimi nic nie zmieniają, a `B2B_ESCROW_REFUND` (zwrot własnych środków) też nie jest przychodem — komornik dosięga salda dopiero Fazą 2/3 (majątek), nie splitem.
4. Anty-pranie międzymodułowe: transfer C2B/B2C **nie resetuje** flagi pochodzenia czarnorynkowego partii ropy (lineage w `oil_movements`) ani `black_market_score` — pranie pieniędzy to co innego niż pranie ropy (§6.5).
5. Audit: telemetria §29.3 — dzienny wolumen B2C per gracz, alert przy 3 kolejnych dniach ≥80% limitu w trakcie aktywnej Fazy 1 (wzorzec wyprowadzania pod zajęcia).

### 4.4. Fallback raty kredytu (koniec usterki „bogaty w gotówce, a `late`")
Kolejność źródeł płatności raty (jedna transakcja, do 3 linii FTS):
1. `PLN_BANK` (stan obecny §17.1);
2. niedobór → **automatycznie `PLN_CASH`** (`LOAN_INSTALLMENT_CASH`);
3. nadal niedobór → `late` (§17.1: +150% stopy, −5 trust/tydzień, 72 h → Faza 1).
Ta sama kolejność (BANK→CASH) obowiązuje dla: `B2B_ESCROW_LOCK`, `B2B_ESCROW_TOPUP`, `B2B_DISPATCH_PENALTY`, `B2B_CARGO_INS_PREMIUM`. Gracz widzi kolejność w UI przed potwierdzeniem (konwencja „zero cichej magii" §11A.1).

---

## 5. POPRAWKI KARTELOWE — tekst zastępujący §26.3 (skrót; pełny w §8)

„Kontrakty B2B wewnątrz kartelu bez prowizji escrow" → **„Zwolnienie z prowizji B2B do 1 500 bbl/dobę na parę członków, wyłącznie w kołnierzu cenowym [0,8×; 1,2×] spot; powyżej — pełna prowizja 1,5%; trzy oflagowane nadużycia w 7 dni = zawieszenie zwolnienia na 30 dni + wiarygodność −15"**. Do tego monitoring GM: dzienny agregat per kartel (wolumen wewnątrz, średnia cena vs spot, koncentracja na parze >60% obrotu pary = flaga) w telemetrii §29.3; rejestr zwolnień z `balance_version` (zmiana progów four-eyes, działa na nowe oferty — konwencja 5).

---

## 6. INTERAKCJE MIĘDZYMODUŁOWE

1. **Komornik vs escrow/transze (kolejność zajęć):** (a) PLN_ESCROW_tx i `locked_in_escrow_bbl` i `cargo_in_b2b_transit` — poza zasięgiem Fazy 1–3 do rozliczenia/zwrotu (§21, rozszerzenie §17.2); (b) przy `settled`: split 50% w tej samej transakcji (`B2B_SETTLE_GARNISH`); (c) `refunded`: bez splitu (nie przychód), ale saldo zasila płynność/majątek → Faza 2/3 widzi je normalnie; (d) kaucja kontraktu ratalnego (`B2B_DEPOSIT_LOCK`) — wydzielona jak escrow, przepadek na rzecz SYS_TREASURY nie jest „wpływem dłużnika".
2. **Dormant/urlop strony (§19.2):** urlopu NIE można aktywować w stanach `accepted…delivered` (§21 — już obowiązuje). Strona dormant w trakcie transakcji: zegary BIEGNĄ (real-time, zero-wipe), a automat zastępuje nieaktywnego — receipt generuje system, timeouty działają, spory rozstrzyga macierz §1.4. 48 h ultimatum §19.2 wysyła powiadomienia `critical` obu stronom przy każdej transzy w toku. Δt-cap 7 dni = dokładnie twardy limit escrow — po powrocie z dormant gracz zastaje stan rozstrzygnięty przez rejestr, nie zamrożony.
3. **VIP (§22.6):** limity z §2 (5/15 ofert, 2/5 kontraktów). Koniec VIP w trakcie: oferty `published` ponad 5 anulowane **od najmłodszych** ze zwrotem rezerwacji (wzorzec zlecień limit), transakcje `accepted+` dokańczają się bez zmian — brak cliffów w środku operacji.
4. **Ochrona ładunku (§7/§11A.7) a utrata:** polisę i eskortę kupuje **sprzedający** (właściciel ładunku do receipt) i tylko on dostaje wypłatę (`B2B_CARGO_INS_CLAIM`, 85%). Kupujący jest chroniony strukturalnie: 100% refund z escrow niezależnie od ubezpieczenia. Sabotaż §7 na kurs B2B: jeśli agresor = kupujący (dowód z logów operacji §7.1 + telemetryki), spór rozstrzygany `resolved_settled` (sprzedający dostaje zapłatę) + sankcje §7 wobec kupującego; jeśli agresor = osoba trzecia: standardowa ścieżka 10 (refund + ubezpieczenie sprzedającego). Eskorty `escort_basic`/`armed_convoy` działają na kurs B2B dokładnie jak na zwykły (mnożniki [0,05;1,0], §11A.7).
5. **Reklamacja jakości vs `black_market_score`:** jakość partii jest deterministyczna 0–100 z progami 70/85/92 (kanon §31) i **jawna w ofercie** (wraz z flagą pochodzenia `black_market_origin` z lineage `oil_movements` — rura/wywóz z czarnego rynku znaczy partię trwale). Reklamacja „ropa brudna" na partię z WIDOCZNĄ flagą lub widoczną jakością <70 = odrzucana automatycznie (kupujący zaakceptował specyfikację). Reklamacja zasadna wyłącznie przy rozjeździe receipt-vs-specyfikacja (zmieszanie w transporcie, sabotaż zaworów §11A.7): `resolved_split` 0–20%. Sprzedaż partii <70 progiem jakości dozwolona tylko z ujawnieniem — inaczej +1 do `black_market_score` sprzedającego i −8 `b2b_reputation` przy uznanej reklamacji (czarny rynek jest legalną mechaniką, OSZUSTWO jakościowe nie).

---

## 7. TESTY ODBIORCZE (blokujące wydanie; rozszerzenie T1–T14 §11A.11)

- **B2B-T1** Podwójne rozliczenie tej samej transzy: leniwe `SettlePlayer` + sweeper 5-min jednocześnie → dokładnie jedno `settled`, jeden `B2B_SETTLE_NET` (CAS + idempotency_key; odpowiednik T14).
- **B2B-T2** Spór otwarty w 167. godzinie limitu escrow → w 168 h autorozstrzygnięcie §1.4, brak jakiegokolwiek przedłużenia blokady; FTS suma zerowa; rezerwacje zwolnione.
- **B2B-T3** Częściowy rozładunek (magazyn kupującego na 60%) + kradzież częściowa (30–70%) reszty w powrocie → częściowe settled + częściowy refund + `B2B_LOSS`; bilans bbl co do 0,0001: `transza = rozładowane + utracone + zwrócone`.
- **B2B-T4** Bankructwo kupującego po `delivered`, przed `settled` → rozliczenie wykonuje się normalnie z escrow wydzielonego; masa upadłościowa nie pomniejsza zapłaty sprzedającego; split Fazy 1 po stronie sprzedającego (jeśli ma) niezależny.
- **B2B-T5** Arbitraż anulowania: cena spot −8% w 12 h po accepted, kupujący anuluje i odkupuje na spocie → kara 10% > zysk 8%, saldo netto −2% − reputacja; powtórzyć dla −15% (kara = cover, netto ≤0) i −70% (cap 40%, zdarzenie §25, flaga telemetrii).
- **B2B-T6** Symulacja pralni kartelowej 30 dni: Tier V, Kroplówka 400 GBBL/dobę → spot → sprzedaż wewnątrzkartelowa. Assert: przepływ bez prowizji ≤450 000 PLN/dobę/parę; każda transakcja poza kołnierzem [0,8;1,2]× → `B2B_FEE_CARTEL` + flaga; 3 flagi/7 dni → zawieszenie 30 dni + (−15) obu stronom; łączny „legalizowany" wolumen ≤ 1,125× legalnej Kroplówki.
- **B2B-T7** Sprzedający w Fazie 1 w chwili settled → JEDNA transakcja: `B2B_SETTLE_NET` 50% + `B2B_SETTLE_GARNISH` 50%; następnie `XFER_B2C` pozostałości → brak drugiego zajęcia (test `isGarnishableIncome`).
- **B2B-T8** Rata kredytu: PLN_BANK = 0, PLN_CASH = pełna rata → `LOAN_INSTALLMENT_CASH`, brak statusu `late`, brak −5 trust (naprawa A7).
- **B2B-T9** Magazyn kupującego pełny w chwili dojazdu: `waiting_bay`, po 2 h postojowe obciąża KUPUJĄCEGO, sprzedający bez −pkt; po 72 h auto-zwrot z potrąceniem postojowego i frachtu z refundu; bilans bbl uzgodniony.
- **B2B-T10** Utrata całkowita z polisą: refund 100% kupującemu + claim 85% sprzedającemu + franszyza 15% po stronie sprzedającego; bez polisy: refund 100%, strata w 100% sprzedającego; limit 50M/24h puli nieprzekroczony.
- **B2B-T11** Ta sama partia: `accepted` B2B + próba zlecenia limit na spocie + próba drugiego B2B → obie odrzucone (`locked_in_escrow_bbl`; odpowiednik T7 §11A.11).
- **B2B-T12** `resolved_split` przez GM: powód obowiązkowy w `gm_action_log`, kwota >1M PLN bez drugiego admina → odrzucona (four-eyes §18.3); replay salda escrow z FTS = stan przed sporem.
- **B2B-T13** Limity B2C w trakcie Fazy 1: przelew 10M → opłata 50 000; próba 10M+1 grosz → odrzut; C2B bez limitu i bez zajęcia; telemetria alert po 3 dniach ≥80% limitu.
- **B2B-T14** Restart procesu między `delivered` a `settled` (12 h okno) → brak podwójnego rozliczenia, brak utraty escrow (wzorzec kredytu dwufazowego §11A.8).
- **B2B-T15** Kontrakt ratalny `market_multiplier`: aktywacja transzy przy spocie +30% → escrow po cenie z okna, `B2B_ESCROW_TOPUP`; brak dofinansowania w 24 h → `missed`, kara 5%, −6; trzeci `missed` → `terminated` + przepadek kaucji + blokada 7 dni.

---

## 8. GOTOWA TREŚĆ DO WKLEJENIA

### 8.1. §13.2 (zastępuje obecny punkt 2 sekcji §13)

```markdown
### 13.2. Giełda Kontraktów B2B (Handel Między Graczami) — Maszyna Stanów

Moduł `/contracts/b2b`. Wszystkie kwoty BIGINT grosze przez FTS (§24.2), wolumeny
DECIMAL(16,4) bbl przez `oil_movements` (§11A.1); parametry w `game_balance` (§29.5).

**Zasady nadrzędne:**
1. Własność ropy przechodzi ze sprzedającego na kupującego WYŁĄCZNE w chwili
   systemowego `delivery_receipt` (fizyczny rozładunek, §11A.8). Do tej chwili ryzyko
   utraty ładunku ponosi sprzedający; ładunek w tranzycie B2B (`cargo_in_b2b_transit`)
   jest wyłączony z egzekucji wobec sprzedającego (§17.2, §21).
2. Wypłaty z escrow (rozliczenie sprzedającego, zwrot kupującego) trafiają na PLN_BANK.
   Rozliczenie sprzedającego jest przychodem: przy aktywnej Fazie 1 egzekucji 50%
   przechodzi na SYS_TREASURY w TEJ SAMEJ transakcji (`B2B_SETTLE_GARNISH`). Zwrot
   escrow nie jest przychodem i nie podlega splitowi.
3. Twardy limit blokady escrow: **168 h od `accepted` danej transzy** (24 h wysyłka +
   72 h max trasa + 72 h max kolejka u kupującego), obejmujący stan `disputed`.
   Po limicie autorozstrzygnięcie wg macierzy dowodowej z rejestrów — escrow nie jest
   schronem majątkowym (§21).
4. Każde przejście stanów: jedna transakcja DB (§23.5), CAS na `state_version`,
   `idempotency_key = (offer_id, tranche_no, target_state, sweep_no)` — leniwe
   rozliczenie i sweeper dają łącznie dokładnie jedno przejście (§23.2).

**Stany oferty spot:** `draft → published → accepted → in_transit → delivered → settled`;
ścieżki wyjątków: `cancelled`, `expired`, `disputed → resolved_settled | resolved_split |
resolved_refunded`, `refunded`. `delivered` obejmuje wariant `partial_unload`.

**Rezerwacja dwustronna:** `published` zakłada `locked_in_escrow_bbl` na ropie
sprzedającego (blokada spot/limit/inny kontrakt, §12); `accepted` atomowo blokuje
dodatkowo 100% wartości po stronie kupującego (`b2b_escrow_lock`, PLN_ESCROW per
transakcja; źródło: PLN_BANK, niedobór z PLN_CASH). Brak środków = odrzucenie
`accepted` w tej samej transakcji.

**Prowizja giełdy:** 1,5% wartości rozliczenia, potrącana z escrow przy `settled`
(`B2B_FEE` → SYS_TREASURY); = 3,75 PLN/bbl przy cenie bazowej 250 — poniżej kosztu
cysterny (4,00, §11A.6), powyżej zera potrzebnego §26.3. Zwolnienie kartelowe: §26.3.

**Terminy i timeouty (`game_balance`):**
| Zdarzenie | Termin | Skutek |
|---|---|---|
| Wysyłka po `accepted` | 24 h | kupujący może anulować: refund 100% + kara 5% od sprzedającego (`B2B_DISPATCH_PENALTY`) |
| Dostawa | ETA trasy (24–72 h) + 24 h margines | przekroczenie → `disputed`; brak reakcji sprzedawcy 24 h → auto `refunded` (−8 `b2b_reputation`); `delivery_receipt` WYGRYWA z autozwrotem (§11A.8) |
| Reklamacja jakości | 12 h od receipt | zasadna → `disputed`/`resolved_split` (potrącenie 0–20%); po oknie `settled` ostateczne |
| Settlement | receipt + 12 h | automat; receipt generuje SYSTEM przy rozładunku |
| Rozstrzygnięcie sporu | SLA GM 72 h, twardo 168 h od accepted | po terminie autorozstrzygnięcie z rejestrów |
| Magazyn kupującego pełny | postojowe 2 000/h po 2 h (KUPUJĄCY, §21); auto-zwrot reszty po 72 h | refund reszty − postojowe − fracht powrotny (`B2B_RETURN_FREIGHT`) |

**Anulowanie po `accepted` (kara kierunkowa — anty-arbitraż):**
`kara = min(40%, max(10%, |spot_now − cena_kontraktu| / cena_kontraktu)) × wartość_escrow`,
płatna drugiej stronie (`B2B_CANCEL_PENALTY`), `b2b_reputation −6`. Przy spadku ceny 8%
kara 10% > zysk 8% (netto −2%); przy 15% kara = cover (netto 0 przed reputacją); cap 40%
chroni przed ruiną przy wydarzeniach −70% (§25), a anulowania w oknie wydarzenia trafiają
do telemetrii GM (§29.3). Wycofanie bez kary — tylko w `published` (przez publikującego).

**Utrata ładunku w transporcie:** całkowita → `refunded` (100% kupującemu) + strata
aktywa po stronie sprzedającego (właściciel do receipt); częściowa → częściowe `settled`
za bbl dostarczone + refund za utracone. Opcjonalne ubezpieczenie kursu kupowane przez
sprzedającego: premia 0,8% (`B2B_CARGO_INS_PREMIUM`), wypłata 85% wartości straty
(franszyza 15%, §31.6) z SYS_INSURANCE_POOL, limit 50M/24h. Sabotaż (§7) z dowodem
na kupującego → `resolved_settled` + sankcje §7. Eskorty §11A.7 działają bez zmian.

**Kontrakty ratalne (transze):** rodzic `draft → published → active → completed`
(+ `cancelled`, `expired` po 7 dniach, `terminated`). Każda transza = oferta z tabeli
powyżej z WŁASNYM escrow i własnym zegarem 168 h. Kaucja kontraktu 10% (`security_deposit`,
§13.1) na PLN_ESCROW_kontraktu przez cały `active`. Tryby cenowe §13.1; dla
`market_multiplier`/`market_plus_bonus` escrow transzy = bbl × cena referencyjna z okna
60 min przed aktywacją (×mnożnik +bonus), okno dofinansowania kupującego 24 h
(`B2B_ESCROW_TOPUP`); brak → transza `missed`: kara 5% + −6 pkt; trzy `missed` z rzędu →
`terminated` + przepadek kaucji + blokada przetargów 7 dni (§13.1).

**Macierz autorozstrzygnięcia (168 h / po SLA GM)** — pierwsza pasująca reguła, dowód
wyłącznie z `oil_movements`/`delivery_receipts`/flag zatoki:
receipt 100% → settled; receipt częściowy → settled częściowy + refund reszty; partia
`lost` → refunded; ładunek w zatoce z winy kupującego (postojowe naliczane) → settled za
wolumen przybyły; opóźnienie sprzedającego → refunded + fracht powrotny po sprzedającym
+ −8 pkt; ropa niewysłana → refunded + kara 5%; dowody sprzeczne → freeze ≤72 h, potem
decyzja GM (domyślnie refund), alarm spójności §29.2.

**Bankructwo/dormant strony (§17.3/§19.2):** bankructwo kupującego przed wysyłką →
refund do masy (bez kary, bez −pkt, `insolvency_no_fault`); po wysyłce → dostawa
dokańcza się, settled normalnie. Bankructwo sprzedającego → ładunek poza masą
(§21), kurs dojeżdża, wpływ podlega splitowi Fazy 1. Dormant/urlop: urlopu nie można
aktywować w `accepted…delivered` (§21); zegary biegną, automat zastępuje nieaktywną
stronę (receipt systemowy, timeouty, macierz).

**Kontrola kont powiązanych:** transakcje między kontami o wspólnym odcisku
(urządzenie, metoda płatności, IP) blokowane do przeglądu GM (§13.2 dotychczas, §22.6).

**Limity (VIP §22.6):** oferty `published` równocześnie: 5 free / 15 VIP; kontrakty
ratalne jako sprzedający: 2 / 5. Koniec VIP: nadmiarowe `published` anulowane od
NAJMŁODSZYCH ze zwrotem rezerwacji; transakcje w toku dokańczają się (brak cliffów).

**Reputacja:** `b2b_reputation_score` 0–100: + przy `settled`, −8 przy `refunded`
z winy sprzedającego, −6 przy `missed`/karalnym anulowaniu, −15 przy sankcji kartelowej
(§26.3); delty w `game_balance`. Jakość partii deterministyczna 0–100 (progi 70/85/92)
i JAWNA w ofercie wraz z flagą `black_market_origin` (lineage `oil_movements`);
reklamacja na jawną wadę/flage = odrzucana automatycznie; sprzedaż <70 bez ujawnienia
= +1 `black_market_score` i −8 B2B przy uznanej reklamacji.

**Kody FTS modułu:** `B2B_ESCROW_LOCK`, `B2B_ESCROW_TOPUP`, `B2B_ESCROW_REFUND`,
`B2B_SETTLE_NET`, `B2B_SETTLE_GARNISH`, `B2B_FEE`, `B2B_FEE_CARTEL`,
`B2B_CANCEL_PENALTY`, `B2B_DISPATCH_PENALTY`, `B2B_QUALITY_DEDUCT`,
`B2B_CARGO_INS_PREMIUM`, `B2B_CARGO_INS_CLAIM`, `B2B_PARKING_FEE`, `B2B_RETURN_FREIGHT`,
`B2B_GM_RESOLVE`, `B2B_DEPOSIT_{LOCK,RELEASE,FORFEIT}` (plan kont: §24.2/§24.5).
```

### 8.2. Nowa §24.5

```markdown
### 24.5. Transfery PLN_CASH ↔ PLN_BANK

1. **Wpłata (C2B):** natychmiast, 0%, bez limitu; FTS `XFER_C2B`. Gotówka podlega
   egzekucji (§17.2), więc wpłata nic nie ukrywa; brak opłaty zniechęca do trzymania
   majątku poza bankiem.
2. **Wypłata (B2C):** natychmiast, opłata 0,5% (`XFER_B2C` + `XFER_B2C_FEE` →
   SYS_TREASURY, jedna transakcja), limit **10 000 000 PLN / 24 h** (doba 00:00 UTC,
   §24.3). Kotwice: 5× gotówkowy pakiet startowy (§1); 40 000 bbl czarnorynkowych
   przy 250 PLN/bbl; koszt max 50 000 PLN/dobę.
3. **Tylko konta własne.** Przelewów między graczami NIE MA — przeniesienie wartości
   wyłącznie przez handel (B2B §13.2, spot §12) z należnymi prowizjami.
4. **Egzekucja:** Faza 1 (§17.2) zajmuje 50% każdego wpływu ZEWNĘTRZNEGO na oba konta.
   `XFER_*` nie są wpływem zewnętrznym (`isGarnishableIncome(reason_code)` — jedna
   funkcja, INCOME_SET = sprzedaż spot, `B2B_SETTLE_NET`, `B2B_CANCEL_PENALTY`,
   kontrakty systemowe, czarny rynek, Kroplówka) — każde pieniądze są zajmowane raz,
   w punkcie wejścia; przelew własny nie tworzy drugiego zajęcia ani ucieczki.
   `B2B_ESCROW_REFUND` (zwrot własnych środków) również poza INCOME_SET.
5. **Kolejność źródeł płatności (BANK → CASH)** obowiązuje dla: raty kredytu (§17.1,
   kod `LOAN_INSTALLMENT_CASH` — gracz z pełnym portfelem gotówkowym NIGDY nie dostaje
   `late` mimo środków), `B2B_ESCROW_LOCK/TOPUP`, kar i premii ubezpieczeniowych.
   Niedobór na obu kontach = zobowiązanie 72 h → §17.1 (brak debetu, konwencja §24.2).
6. **Telemetria (§29.3):** dzienny wolumen B2C per gracz; alert: 3 kolejne dni ≥80%
   limitu podczas aktywnej Fazy 1.
```

### 8.3. Poprawiona §26.3 (zdanie o prowizji — zastępuje „kontrakty B2B wewnątrz kartelu bez prowizji escrow")

```markdown
3. **Kartele naftowe (sojusze):** dobrowolne bloki 2–5 firm — wspólny kanał, widoczność
   statusów sojuszników (online/awarie), **pakt o nieagresji** (sabotaż wewnątrz kartelu =
   automatyczne wydalenie agresora + kara −10 `company_credibility`). Założenie:
   50 000 PLN + zgoda wszystkich członków; rozwiązanie z 7-dniową karencją.
   **Przywilej handlowy (uszczelniony anty-pralnia, §13.2):** kontrakty B2B między
   członkami są zwolnione z prowizji giełdy (1,5%) do **1 500 bbl/dobę (00:00 UTC) na
   uporządkowaną parę członków** i wyłącznie przy cenie w kołnierzu **[0,8×; 1,2×]**
   spot referencyjnego z chwili publikacji. Próg uzasadniony: 1 500 × 250 × 1,2 =
   450 000 PLN/dobę ≈ 1,125× maksymalnej legalnej Kroplówki Tier V (400 GBBL × 1 000 =
   400 000 PLN/dobę, §22.6) — kanał kartelowy nie pozwala wstrzyknąć więcej kapitału
   niż najwyższy legalny tier, a wymaga fizycznego transportu (~4 PLN/bbl) i pełnego
   śladu w `oil_movements`. Transakcje powyżej progu wolumenu LUB poza kołnierzem:
   pełna prowizja (`B2B_FEE_CARTEL`) + flaga telemetrii. **Sankcje:** 3 oflagowane
   transakcje pary w 7 dni = zawieszenie zwolnienia pary na 30 dni + −15
   `company_credibility` obu stronom + przegląd GM (rejestr publiczny, §26.4).
   **Monitoring GM (§29.3):** dzienny agregat per kartel — wolumen wewnątrz-kartelowy,
   średnia cena vs spot, koncentracja (para >60% swojego obrotu B2B wewnątrz kartelu =
   flaga), korelacja z wymianami Kroplówki.
```

---

## 9. LISTA DECYZJI DLA WŁAŚCICIELA GRY

1. **Prowizja 1,5% wyłącznie od sprzedającego** (rekomendacja: tak) — kupujący widzi czystą cenę ofertową, a sprzedający i tak płaci mniej niż straciłby na slippage'u spot przy dużych wolumenach.
2. **Cap kary anulowania 40%** (rekomendacja: tak) — przy wydarzeniu −70% zostaje rezydualny arbitraż 30%, ale kara bez capu bankrutowałaby stronę w środku kryzysu; alternatywę (cap 100% w oknie §25) zostawiam jako łatkę po pomiarach telemetrii.
3. **Autorozstrzyganie sporów z rejestrów bez GM po 72 h/168 h** (rekomendacja: tak) — twardy limit escrow jest świętszy niż ręczna sprawiedliwość; GM pozostaje ścieżką odwoławczą przez `B2B_GM_RESOLVE`.
4. **Próg kartelowy 1 500 bbl/dobę/parę + kołnierz ±20%** (rekomendacja: tak) — liczby wynikają wprost z Tieru V Kroplówki; alternatywa „prowizja kartelowa zawsze, tylko niższa (0,5%)" jest prostsza, ale łamie obietnicę §26.3 dosłownie.
5. **Wypłata z escrow na PLN_BANK ze splitem Fazy 1 w tej samej transakcji** (rekomendacja: tak) — domyka pytanie „BANK czy CASH" i uniemożliwia okno między rozliczeniem a zajęciem; PLN_CASH pozostaje wyłącznie czarnorynkowy (§24.2).
6. **Limit wypłat B2C 10M/dobę z opłatą 0,5%** (rekomendacja: tak) — obie egzekucje obejmują oba konta, więc limit to wyłącznie higiena audytu i hamulec prania; jeśli wolisz czarny rynek bez tarcia, alternatywa: limit 50M, opłata 1%.

Chcesz, żebym zapisał całość jako plik w Documents (np. `OILEMPIRE_B2B_WEZEL_PIENIEZNY_PROJEKT.md`) obok pozostałych opracowań?
