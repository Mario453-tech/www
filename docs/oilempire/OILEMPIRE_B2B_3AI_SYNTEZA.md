# Węzeł pieniężny B2B — scalenie trzech propozycji (GPT / DeepSeek / Qwen)

**Data:** 2026-11-11 · **Materiały źródłowe:**
- `OILEMPIRE_B2B_PROPOZYCJA_GPT.md` (167 linii)
- `OILEMPIRE_B2B_PROPOZYCJA_DEEPSEEK.md` (597 linii)
- `OILEMPIRE_B2B_PROPOZYCJA_QWEN.md` (366 linii)

**Zakres:** domknięcie blokerów a4 (maszyna stanów B2B), a7 (transfery gotówka↔bank), B3 (pralnia GBBL przez kartel) z raportu `OILEMPIRE_WERYFIKACJA_3_MODELI.md`.

**Baza scalenia:** Qwen (najpełniejsza tabela przejść: 15 stanów z właścicielem ropy, lokalizacją, pieniędzmi, rezerwacjami i wpisami księgowymi + macierz autorozstrzygnięcia z rejestrów). Dodatki: GPT (`B2B_GM_AWARD` — orzeczenia GM ponad saldo escrow, zasada jednokrotnego zajęcia przy wpływie). DeepSeek — głównie potwierdzenie kierunków (też 7 dni escrow, też kierunkowa kara za anulowanie).

## Porównanie parametrów kluczowych

| Parametr | GPT | DeepSeek | Qwen | **Przyjęto** |
|---|---|---|---|---|
| Prowizja B2B | 1%, sprzedawca | 2%, sprzedawca | 1,5%, sprzedawca | **1,5%** (3,75 PLN/bbl — między kosztem rurociągu 1,90 a cysterny 4,00; niższa niż oszczędność slippage'u przy >8 000 bbl, więc B2B zostaje opłacalne) |
| Kara za anulowanie | max(15%, korzyść+1%), bez capu | max(12%, \|Δceny\|+3%), bez capu | min(40%, max(10%, \|Δceny\|)) | **Qwen z capem 40%** — przy wydarzeniu −70% kara bez capu (73%) bankrutowałaby stronę w środku kryzysu; arbitraż przy 8% spadku: kara 10% > zysk 8% ✓ |
| Twardy limit escrow | 7 dni od accepted transzy | 7 dni | 168 h (24+72+72) | **168 h od `accepted` transzy**, obejmuje `disputed`, urlop i czas GM (wszyscy zgodni) |
| Zwolnienie kartelowe | 800 bbl/24h na CAŁY kartel + 30-dniowy lineage środków z Kroplówki | 50 000 bbl/mies. na parę, potem 1% | 1 500 bbl/dobę na parę + kołnierz cenowy [0,8×;1,2×] spot + sankcje | **Qwen** — jedyny z kołnierzem cenowym (blokuje nad-/niedofakturowanie, nie tylko wolumen); próg 450 tys. PLN/dobę ≈ 1,125× legalnej Kroplówki Tier V → pralnia bez przewagi. Lineage GPT (30 dni) — zapisany jako wariant zapasowy, jeśli telemetria wyłapie obejścia |
| Wpłata gotówki C2B | free, natychmiast | free | free, bez limitu | **free, natychmiast, bez limitu** (wszyscy zgodni) |
| Wypłata B2C | free, bez limitu | 1% + limit 500 tys./dobę + 24h okno cofnięcia + 50% zajęcia | 0,5% + limit 10 mln/dobę | **Qwen** — limit DS (500 tys.) zablokowałby legalne płatności gotówkowe (transza EPC szarej strefy Klasy II = 12 mln); mechanizm DS „50% zajęcia przy wypłacie" odrzucony (podwójne zajęcie tych samych pieniędzy — przy wpływie i przy wypłacie) |
| Rata kredytu z gotówki | fallback automatyczny | fallback automatyczny, 0% | kolejność BANK→CASH, kod `LOAN_INSTALLMENT_CASH` | **jednomyślnie: fallback automatyczny** — koniec usterki „bogaty w gotówce, a `late`" |
| Rozliczenie po dostawie | ≤5 min za część bezsporną, reklamacja = osobne roszczenie | 1 h | receipt systemowy + 12 h okno reklamacji → settled ostateczne | **Qwen (T+12h)** — ostateczność rozliczenia zamiast „roszczeń po wypłacie" (prostsze, immutable FTS) |
| Utrata ładunku | ryzyko sprzedawcy do odbioru; ubezpieczenie z puli | właściciel = sprzedawca do receipt | premia 0,8%, wypłata 85% (franszyza 15%), limit 50M/24h | **Qwen** — spójne z §31.6 (ta sama pula, ta sama franszyza) |
| Wypłata z escrow | PLN_BANK | — | PLN_BANK + split Fazy 1 (50%) w TEJ SAMEJ transakcji | **PLN_BANK ze splitem w jednej transakcji** — zero okna między rozliczeniem a zajęciem; zwrot escrow ≠ przychód (bez splitu) |

## Decyzje podjęte za właściciela (z rekomendacji Qwen + korekt moich)

1. Prowizja 1,5% wyłącznie od sprzedającego (kupujący widzi czystą cenę ofertową).
2. Cap kary anulowania 40% — przy skrajnych wydarzeniach zostaje świadomy arbitraż rezydualny, trzymany w telemetrii GM; zaostrzenie dopiero po pomiarach.
3. Autorozstrzyganie sporów z rejestrów (`oil_movements`, `delivery_receipts`) po SLA 72 h / twardo 168 h; GM pozostaje ścieżką odwoławczą. Twardy limit escrow > ręczna sprawiedliwość.
4. Próg kartelowy 1 500 bbl/dobę/parę + kołnierz cenowy ±20%; sankcja: 3 oflagowane transakcje w 7 dni = zawieszenie zwolnienia pary 30 dni + wiarygodność −15 obu stronom.
5. Wypłata z escrow wyłącznie na `PLN_BANK` (B2B = biała gospodarka; `PLN_CASH` pozostaje kanałem czarnorynkowym).
6. Limit wypłat B2C 10 mln PLN/dobę z opłatą 0,5% (higiena audytu; egzekucja i tak obejmuje oba konta).
7. Ubezpieczenie ładunku B2B: premia 0,8% wartości kursu, wypłata 85%, z puli §31.6 (marża poola +0,375% na kurs — dodatnia).
8. Odrzucone: 24h okno cofnięcia wypłat i podwójne zajęcia DS (kolizja z zasadą jednokrotnego zajęcia); miesięczne limity kartelowe DS (za luźne: 10 par × 50 000 bbl); prowizja 2% DS (za wysoka przy milionowych obrotach).

## Co wklejono do specyfikacji

- **§13 przebudowana:** `### 13.1` (kontrakty systemowe — treść bez zmian, tylko awans na podsekcję; odwołania „§13.1" z §31 stały się formalnie prawdziwe) + **nowa `### 13.2`** — pełna maszyna stanów: 15 przejść (w tym częściowy rozładunek, pełny magazyn kupującego i zatoka postojowa 2 000 PLN/h, utrata całkowita/częściowa, bankructwo obu stron w każdej fazie, dormant), macierz autorozstrzygnięcia, kara kierunkowa, 20 kodów FTS, limity VIP, reputacja, jakość partii jawna w ofercie z flagą pochodzenia czarnorynkowego.
- **Nowa `### 24.5`:** transfery C2B/B2C, `INCOME_SET` i jedna funkcja `isGarnishableIncome(reason_code)` (jednokrotne zajęcie w punkcie wejścia), kolejność źródeł płatności BANK→CASH dla raty/escrow/kar/premii, telemetria wypłat.
- **Nowa `### 26.3`:** przywilej kartelowy z progami anty-pralniczymi, kołnierzem cenowym, sankcjami i monitoringiem GM.

## Status blokerów z finalnej weryfikacji

- **a4 (B2B)** — ZAMKNIĘTY
- **a7 (cash↔bank)** — ZAMKNIĘTY
- **B3 (pralnia kartelowa)** — ZAMKNIĘTY
- Powiązane: a11 (aukcje) — częściowo (aukcje działek §31.2 + komornicze §19.4 istnieją; generator kontraktów systemowych nadal otwarty), a12 (globalny porządek blokad) — częściowo (§23.5 + CAS per encja; pełna kolejność blokad między typami zasobów do spisania przy implementacji).
