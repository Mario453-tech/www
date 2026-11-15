# Projekt reguł węzła pieniężnego B2B — OilEmpire v1

---

## 1. MASZYNA STANÓW OFERTY B2B — kompletna tabela

### 1A. Stany oferty (transakcja pojedyncza, nie ratalna)

| # | Stan | Kto decyduje | Właściciel ropy | Gdzie fizycznie jest ropa | Właściciel pieniędzy | Rezerwacje aktywne | Czas trwania |
|---|---|---|---|---|---|---|---|
| 1 | `draft` | Sprzedający tworzy | Sprzedający | Magazyn sprzedającego | Kupujący (jeszcze niezaangażowany) | Brak | Bez limitu |
| 2 | `published` | Sprzedający publikuje | Sprzedający | Magazyn sprzedającego | — | `reserved_b2b` = wolumen oferty (nie blokada escrow — dopiero przy accepted) | Max 7 dni → `expired` |
| 3 | `accepted` | Kupujący klika „Akceptuj" | Sprzedający (jeszcze) | Magazyn sprzedającego | Kupujący (zablokowane w escrow) | `reserved_b2b` → `locked_in_escrow_bbl` + utworzenie `PLN_ESCROW` z kwotą 100% wartości | 0 min — natychmiast przechodzi w `escrow_locked` |
| 4 | `escrow_locked` | Automat po accepted | Sprzedający | Magazyn sprzedającego → załadunek | Kupujący (w escrow) | `locked_in_escrow_bbl` + `PLN_ESCROW` | Do momentu wysyłki (max 24h na załadunek) |
| 5 | `in_transit` | Automat po wysyłce | Sprzedający (ryzyko transportowe — patrz 1D) | W pojeździe/rurociągu/tankowcu | Kupujący (w escrow) | `locked_in_escrow_bbl` (wolumen w drodze) + `PLN_ESCROW` | ETA trasy + margines 6h (§11A.8) |
| 6 | `partial_unload` | Automat przy częściowym rozładunku | Sprzedający (pozostała część w pojeździe) | Część u kupującego, reszta w pojeździe [patrz 1B] | Kupujący (w escrow) | `locked_in_escrow_bbl` pomniejszone o rozładowaną część | Do pełnego rozładunku lub timeoutu |
| 7 | `delivered` | Automat — potwierdzenie rozładunku (`delivery_receipt`) | Kupujący | Magazyn kupującego | Kupujący (w escrow → zaraz uwolnione) | Tylko `PLN_ESCROW` (ropa już nie blokowana) | 1h na automatyczne `settled` |
| 8 | `settled` | Automat | Kupujący | Magazyn kupującego | Sprzedający (na `PLN_BANK`) | Brak — wszystkie rezerwacje zwolnione | Stan końcowy |
| 9 | `disputed` | Gracz (dowolna strona) lub automat po timeout | Zależnie od sporu (ropa w miejscu zatrzymania) | W miejscu zatrzymania (magazyn/pojazd/hub) | Kupujący (w escrow) | `locked_in_escrow_bbl` + `PLN_ESCROW` (zamrożone) | Do rozstrzygnięcia, max 7 dni od `accepted` |
| 10 | `resolved` | GM lub automat po timeoutach sporu | Zgodnie z wyrokiem | Zgodnie z wyrokiem | Zgodnie z wyrokiem | Zwalniane zgodnie z wyrokiem | Stan końcowy |
| 11 | `refunded` | Automat (timeout sporu bez reakcji sprzedawcy lub wyrok GM) | Sprzedający (zwrot) | Magazyn sprzedającego (zwrot fizyczny) | Kupujący (zwrot escrow) | Wszystkie zwolnione | Stan końcowy |
| 12 | `cancelled` | Kupujący (tylko z `published`) | Sprzedający | Magazyn sprzedającego | — | `reserved_b2b` zwolnione | Tylko ze stanu `published` |
| 13 | `expired` | Automat po 7 dniach w `published` | Sprzedający | Magazyn sprzedającego | — | `reserved_b2b` zwolnione | 7 dni od `published` |
| 14 | `terminated` | Automat — kara za anulowanie po `accepted` | Sprzedający (ropa wraca) | Magazyn sprzedającego | Sprzedający (kara) + Kupujący (reszta escrow) | Wszystkie zwolnione po potrąceniu kary | Stan końcowy |

### 1B. Szczegółowe przejścia stanów — tabela atomowa

Każde przejście = jedna transakcja DB (SELECT FOR UPDATE na ofercie, kontach, rezerwacjach ropy).

| Przejście | Wyzwalacz | Akcje (ropa) | Akcje (pieniądze) | Wpisy FTS | Wpisy oil_movements | Timeout |
|---|---|---|---|---|---|---|
| `draft → published` | Sprzedający klika „Opublikuj" | `reserved_b2b` w magazynie sprzedającego = wolumen oferty | Brak | Brak (rezerwacja to nie ruch pieniędzy) | `reason_code=RESERVE_B2B`, wolumen ujemny z dostępnego | — |
| `published → accepted` | Kupujący klika „Akceptuj" | `reserved_b2b` → `locked_in_escrow_bbl` (ta sama ropa, mocniejsza blokada) | 100% wartości kupującego → `PLN_ESCROW` (z `PLN_BANK` kupującego) | `B2B_ESCROW_LOCK`: `PLN_BANK`(kup.) → `PLN_ESCROW` | Brak (ropa jeszcze nie rusza się fizycznie) | — |
| `accepted → escrow_locked` | Automat natychmiast po accepted | Stan przejściowy — załadunek w toku | Bez zmian | Bez zmian | Bez zmian | 24h na wysyłkę |
| `escrow_locked → in_transit` | Sprzedający wysyła transport | Rezerwacja `locked_in_escrow_bbl` → fizyczny załadunek na pojazd/rurociąg/tankowiec | Bez zmian | Bez zmian (koszt transportu = osobny wpis `LOG_TRANSPORT_COST`) | `reason_code=LOAD_B2B_SHIPMENT`: magazyn sprzedającego → pojazd/rurociąg | — |
| `in_transit → partial_unload` | Przybycie do kupującego, magazyn pełny — rozładowano X bbl, Y bbl czeka | Rozładowane X bbl → magazyn kupującego; Y bbl zostaje w pojeździe (opłata postojowa §21) | `PLN_ESCROW` bez zmian (całość nadal zamrożona) | `B2B_PARTIAL_DELIVERY`: informacyjny, bez ruchu pieniędzy | `reason_code=UNLOAD_B2B_PARTIAL`: pojazd → magazyn kupującego (tylko X bbl) | Opłata postojowa po 2h (§21) |
| `partial_unload → delivered` | Ostatnia część rozładowana, `delivery_receipt` | Reszta ropy → magazyn kupującego | `PLN_ESCROW` → `PLN_BANK` sprzedającego (100% wartości) | `B2B_SETTLE` | `reason_code=UNLOAD_B2B_FINAL`: pojazd → magazyn kupującego | — |
| `in_transit → delivered` | Przybycie + pełny rozładunek + `delivery_receipt` | Ropa → magazyn kupującego | `PLN_ESCROW` → `PLN_BANK` sprzedającego pomniejszone o prowizję B2B (2%) → `SYS_TREASURY` | `B2B_SETTLE` + `B2B_COMMISSION` | `reason_code=UNLOAD_B2B`: pojazd → magazyn kupującego | — |
| `delivered → settled` | Automat po 1h (okno na reklamację ilości) | Bez zmian | Prowizja B2B (2%) z kwoty escrow → `SYS_TREASURY`; reszta → `PLN_BANK` sprzedającego | `B2B_SETTLE` + `B2B_COMMISSION` | — | 1h |
| `in_transit → disputed` (timeout) | ETA + margines 6h przekroczony | Ropa zatrzymana w miejscu, gdzie jest (pojazd/hub/magazyn) | Escrow zamrożony | `B2B_DISPUTE_OPEN` (informacyjny) | `reason_code=DISPUTE_HOLD`: lokalizacja bez zmian, blokada administracyjna | 24h na reakcję sprzedawcy |
| `disputed → refunded` (auto) | 24h bez reakcji sprzedawcy w `disputed` | Ropa fizycznie wraca do magazynu sprzedającego (koszt transportu zwrotnego = sprzedający) | 100% escrow → `PLN_BANK` kupującego | `B2B_REFUND_AUTO` | `reason_code=RETURN_B2B_DISPUTED`: bieżąca lokalizacja → magazyn sprzedającego | 24h od `disputed` |
| `disputed → resolved` | GM wydaje wyrok | Zgodnie z wyrokiem (częściowy zwrot, częściowa dostawa, odszkodowanie) | Zgodnie z wyrokiem | `B2B_RESOLVE_GM` + opcjonalnie `B2B_PENALTY`, `B2B_DAMAGES` | Zgodnie z wyrokiem | Do 7 dni od `accepted` (twardy limit) |
| `* → terminated` (kara) | Kupujący anuluje po `accepted` (kara 12% → sprzedający) | `locked_in_escrow_bbl` zwolnione → z powrotem do magazynu sprzedającego | 12% escrow → `PLN_BANK` sprzedającego; 88% → `PLN_BANK` kupującego | `B2B_CANCEL_PENALTY` | `reason_code=RELEASE_B2B_ESCROW`: powrót blokady do dostępnych | — |
| `published → cancelled` | Kupujący wycofuje ofertę | `reserved_b2b` zwolnione | Brak | Brak (brak kary dla kupującego w stanie `published`) | `reason_code=RELEASE_RESERVE` | — |
| `published → expired` | Automat po 7 dniach | `reserved_b2b` zwolnione | Brak | Brak | `reason_code=RELEASE_RESERVE` | 7 dni |
| `disputed → expired` (twardy limit) | 7 dni od `accepted`, spór nierozstrzygnięty przez GM | Automatyczny zwrot: ropa → sprzedający, jeśli transport nie dotarł; jeśli dotarł → kupujący | Escrow → kupujący (ropa nie dostarczona) LUB → sprzedający (ropa dostarczona, spór o jakość) | `B2B_EXPIRED_ESCROW` | Według reguły wyżej | 7 dni od `accepted` |

### 1C. Oferta ratalna (transze) — rozszerzenie maszyny stanów

Oferta ratalna ma nadrzędny kontrakt (`contract_header`) z własnymi stanami i podrzędne transze (`contract_installments`), każda z własną maszyną stanów jak wyżej.

| Stan kontraktu | Znaczenie |
|---|---|
| `draft` | Szkic — transze jeszcze nieutworzone |
| `published` | Oczekuje na akceptację |
| `accepted` | Kupujący zaakceptował cały kontrakt; tworzone są transze |
| `active` | Transze w realizacji (niektóre `in_transit`/`delivered`, inne oczekujące) |
| `completed` | Wszystkie transze `settled` |
| `partially_settled` | Część transzy `settled`, część `refunded`/`terminated` |
| `disputed` | Spór na poziomie kontraktu (co najmniej jedna transza w `disputed`) |
| `terminated` | Przedterminowe rozwiązanie z karą |

**Rezerwacja przy kontrakcie ratalnym:** w momencie `accepted` system rezerwuje cały wolumen kontraktu (`locked_in_escrow_bbl` = suma wszystkich transz) ORAZ blokuje w escrow tylko kwotę PIERWSZEJ transzy. Kolejne transze blokują środki kupującego na 24h przed planowaną wysyłką (zabezpiecza to przed zamrażaniem kapitału kupującego na cały okres kontraktu).

### 1D. Utrata ładunku w transporcie — własność i odpowiedzialność

Reguła: **właścicielem ropy w transporcie jest sprzedający aż do `delivered`**. Ryzyko transportowe ponosi sprzedający — to on wybiera środek transportu (§11A.8).

| Zdarzenie | Stan ropy | Kto ponosi stratę | Co z escrow | Wpis FTS |
|---|---|---|---|---|
| Sabotaż (kradzież 100%) | `lost` | Sprzedający | 100% zwrot do kupującego | `B2B_LOSS_THEFT` + escrow → kupujący |
| Sabotaż (częściowy 50%) | 50% `lost`, 50% dociera | Sprzedający traci 50% wartości; dostarcza 50% | 50% escrow → sprzedający za dostarczoną część; 50% → kupujący | `B2B_LOSS_PARTIAL` + `B2B_SETTLE` (częściowe) |
| Wypadek/eksplozja | `lost` | Sprzedający | 100% zwrot do kupującego | `B2B_LOSS_ACCIDENT` |
| Wyciek rurociągu (straty 0,8% — §11A.3) | `lost` (śladowe) | Sprzedający (w granicach normy — patrz niżej) | Escrow bez zmian (dostarczono pozostałe 99,2%) | `B2B_LOSS_PIPELINE` tylko na utracony wolumen |
| Ubezpieczenie kursu (§11A.8) | — | Ubezpieczyciel wypłaca odszkodowanie SPRZEDAJĄCEMU (on jest właścicielem) | Escrow bez zmian (kupujący dostaje swoją ropę albo zwrot) | Odszkodowanie: `SYS_INSURANCE_POOL` → `PLN_BANK` sprzedającego |

**Próg strat normalnych:** straty ≤ strata_bazowa środka transportu (§11A.3) nie są podstawą do sporu — to ryzyko wkalkulowane w koszt transportu. Straty > strata_bazowa (wyciek, sabotaż) otwierają ścieżkę sporu.

### 1E. Bankructwo/egzekucja strony w trakcie transakcji

**Bankructwo sprzedającego (ropa w transporcie):**
- Ropa w transporcie NALEŻY do sprzedającego → wchodzi do masy upadłościowej
- Escrow kupującego jest NIETYKALNY (§21: „Środki w depozycie Escrow są prawnie wydzielone — komornik nie może ich zająć")
- Automatyczne `refunded`: 100% escrow → kupujący
- Ropa wraca do magazynu sprzedającego (już pod zarządem komorniczym) lub — jeśli transport już dotarł do huba kupującego — fizyczny zwrot na koszt masy upadłościowej

**Bankructwo kupującego (escrow już wpłacone):**
- Escrow jest wydzielony → nietykalny dla komornika kupującego
- Transakcja toczy się normalnie: ropa dociera → `delivered` → `settled` → sprzedający dostaje pieniądze
- Jeśli kupujący nie może odebrać (magazyn pod egzekucją Fazy 2? — nie, Faza 2 to licytacja ropy dłużnika, nie blokada przyjęć): ropa czeka w zatoce (§21), opłata postojowa obciąża kupującego

**Egzekucja Fazy 1 (50% wpływów) a wypłata z escrow:**
- Pieniądze z escrow trafiają na `PLN_BANK` sprzedającego
- Faza 1 zajmuje 50% wpływów na `PLN_BANK` — dotyczy to RÓWNIEŻ wpływu z rozliczenia B2B
- Nie ma ucieczki przez „przelew na gotówkę przed rozliczeniem" — escrow → PLN_BANK to wpis niepodzielny, Faza 1 tnie go u źródła

---

## 2. PARAMETRY LICZBOWE — z uzasadnieniem arytmetycznym

### 2.1 Prowizja bazowa B2B

**Wartość: 2% od wartości transakcji, pobierana od sprzedającego przy `settled`.**

Uzasadnienie:
- Standard rynkowy platform escrow: 1–5% (eBay 13,25%, Allegro 3–11%, Flippa 5–10%)
- W gospodarce gry, gdzie transakcje opiewają na miliony PLN, 2% to istotny sink walutowy: przy obrocie 100 mln PLN/dzień na serwerze → 2 mln PLN/dzień ściągane z obiegu
- Prowizja trafia do `SYS_TREASURY` (nie do systemu bankowego NPC) — to trwałe usunięcie pieniędzy z gospodarki, konieczne do równoważenia emisji z Kroplówki (§24.3)
- Sprzedający płaci, bo to on korzysta z mechanizmu escrow (gwarancja zapłaty) i platformy B2B (dostęp do kupujących)

**Prowizja wewnątrz kartelu:**
- 0% dla pierwszych **50 000 bbl/miesiąc** na parę kartelową (suma transakcji między dwoma konkretnymi członkami)
- Powyżej progu: 1% (połowa stawki)
- Próg 50 000 bbl/miesiąc na parę: przy 2–5 firmach w kartelu daje max 10 par × 50 000 = 500 000 bbl/miesiąc łącznie — to ~40% limitu anty-zmowowego 40% produkcji serwera przy założeniu serwera ~100 graczy produkujących średnio ~12 500 bbl/miesiąc każdy

### 2.2 Kara za anulowanie — rozwiązanie arbitrażu

**Wartość: 12% wartości transakcji, płaci strona anulująca na rzecz drugiej strony.**

Obliczenie progu arbitrażu:

- Cena bazowa: 250 PLN/bbl
- Maksymalna realistyczna zmiana w oknie 60 min (slippage §19.3): ±15% (37,50 PLN) — to górna granica przy wydarzeniach globalnych (§25)
- Przy karze 5% i spadku ceny o 8%: kupujący anuluje → traci 5% kary → kupuje na spocie o 8% taniej → netto +3% = ARBITRAŻ
- Przy karze 10% i spadku o 8%: strata 10% > zysk 8% → NIE OPŁACA SIĘ
- Przy karze 12% i spadku o 12%: strata 12% = zysk 12% → próg rentowności arbitrażu = 12% spadku ceny
- Ruch 12% w 60 min to wydarzenie klasy `major` (§25) — występuje raz na kilkadziesiąt dni gry

**Dodatkowa reguła anty-arbitrażowa (kierunkowa):**
```
kara = max(12%, |zmiana_ceny_w_oknie| + 3%)
```
Gdzie `zmiana_ceny_w_oknie` to zmiana ceny spot od momentu `accepted` do momentu anulowania. Jeśli cena spadła o 15%, kara = max(12%, 15% + 3%) = 18% → zawsze większa niż zysk arbitrażowy. Jeśli cena nie drgnęła, kara = 12%.

To ZAWSZE zamyka okno arbitrażowe, bo kara ≥ zmiana ceny + 3 punkty procentowe.

### 2.3 Twardy limit blokady escrow

**Wartość: 7 dni kalendarzowych od `accepted`.**

Uzasadnienie:
- Czas dostawy: 24–72h (§13.2) + margines 6h (§11A.8) = max 78h (~3,25 dnia)
- Okno na spór: 24h (§13.2)
- Czas na rozstrzygnięcie GM: pozostałe ~2,75 dnia
- Limit 7 dni uniemożliwia parkowanie majątku w escrow (§21) — po 7 dniach następuje automatyczne rozstrzygnięcie (ropa wraca do sprzedającego + escrow do kupującego, chyba że GM rozstrzygnął wcześniej)

### 2.4 Timeouty szczegółowe

| Zdarzenie | Czas | Uzasadnienie |
|---|---|---|
| `published → expired` | 7 dni | Oferta nie może wisieć wiecznie; tydzień to wystarczający czas na znalezienie kupca |
| `accepted → wysyłka` | 24h | Sprzedający musi zorganizować transport — 24h to komfortowy margines |
| `ETA → disputed (timeout)` | ETA + 6h | Margines na opóźnienia logistyczne, potem domniemanie problemu |
| `disputed → auto refund (brak reakcji sprzedawcy)` | 24h | Ochrona kupującego — sprzedawca ma dobę na ustosunkowanie się do sporu |
| `disputed → auto resolve (brak reakcji obu stron)` | 72h od otwarcia sporu | Jeśli obie strony milczą, system rozstrzyga na korzyść kupującego |
| `delivered → settled` | 1h | Okno na zgłoszenie reklamacji ilościowej przez kupującego („dowód rozładunku mówi 10 000 bbl, ale system przyjął 9 800") |
| `twardy limit escrow` | 7 dni od `accepted` | Niezależnie od stanu — po 7 dniach automatyczne rozstrzygnięcie |

### 2.5 Limit wolumenu kartelowego — obliczenie anty-pralnicze

**Cel:** zablokować pranie GBBL przez kartel (Kroplówka → gotówka → ropa na spocie → B2B bez prowizji → wypłata drugiemu graczowi).

**Mechanizm pralni (ścieżka B3):**
1. Gracz A (Tier V, limit 400 GBBL/dobę = 400 000 PLN/dobę) używa Kroplówki
2. Kupuje ropę na spocie za gotówkę (PLN_CASH)
3. Sprzedaje tę ropę graczowi B przez B2B wewnątrz kartelu (0% prowizji)
4. Gracz B dostaje pieniądze na PLN_BANK — omija swój limit Tieru Kroplówki

**Blokada:** limit wolumenu wewnątrz-kartelowego = **równowartość limitu Kroplówki niższego z Tierów obu stron, × 1,0 (przelicznik 1:1)**

Przykład: Tier V (400 GBBL) + Tier II (50 GBBL) → limit = 50 000 PLN dziennie między tymi dwoma graczami = 200 bbl/dobę przy cenie 250 PLN/bbl.

**Dlaczego ×1,0, a nie mniej?** Bo przy ×1,0 pralnia daje DOKŁADNIE tyle samo, co legalna Kroplówka gracza B — nie ma zysku z prania. Przy ×0,8 pralnia jest mniej opłacalna niż legalna Kroplówka (po uwzględnieniu spreadu spot). Próg ×1,0 to „neutralność" — pralnia nie daje premii, ale też nie jest gorsza (uczciwy gracz nie traci na przypadkowym trafieniu w limit).

**Dodatkowe zabezpieczenie:** limit jest liczony jako **krocząca suma 30-dniowa**, nie dzienna — uniemożliwia kumulację („przez tydzień nic, potem 350 000 naraz").

---

## 3. TABELA KODÓW FTS — nowe reason_code dla B2B

| Kod | Opis | from_account | to_account | Jednostka | Skutek braku środków |
|---|---|---|---|---|---|
| `B2B_ESCROW_LOCK` | Blokada środków kupującego w escrow przy `accepted` | `PLN_BANK` (kupujący) | `PLN_ESCROW` (transakcja) | BIGINT grosze | Transakcja odrzucona — nie przechodzi w `accepted` |
| `B2B_ESCROW_RELEASE` | Zwrot escrow do kupującego (refund/expiry) | `PLN_ESCROW` | `PLN_BANK` (kupujący) | BIGINT grosze | Niemożliwe — escrow ma zawsze środki zablokowane |
| `B2B_SETTLE` | Rozliczenie — escrow do sprzedającego | `PLN_ESCROW` | `PLN_BANK` (sprzedający) | BIGINT grosze | Niemożliwe |
| `B2B_COMMISSION` | Prowizja B2B (2%) do Skarbu | `PLN_ESCROW` (przy settle) | `SYS_TREASURY` | BIGINT grosze | Niemożliwe — pobierane przed wypłatą dla sprzedającego |
| `B2B_CANCEL_PENALTY` | Kara za anulowanie (12%) → strona poszkodowana | `PLN_ESCROW` | `PLN_BANK` (strona poszkodowana — sprzedający przy anulowaniu przez kupującego) | BIGINT grosze | Niemożliwe |
| `B2B_CANCEL_PENALTY_RETURN` | Reszta escrow po karze → strona anulująca | `PLN_ESCROW` | `PLN_BANK` (strona anulująca) | BIGINT grosze | Niemożliwe |
| `B2B_LOSS_THEFT` | Zwrot escrow przy kradzieży 100% ładunku | `PLN_ESCROW` | `PLN_BANK` (kupujący) | BIGINT grosze | Niemożliwe |
| `B2B_LOSS_PARTIAL` | Zwrot części escrow przy częściowej utracie | `PLN_ESCROW` | `PLN_BANK` (kupujący) — za utraconą część | BIGINT grosze | Niemożliwe |
| `B2B_LOSS_ACCIDENT` | Zwrot escrow przy wypadku/eksplozji | `PLN_ESCROW` | `PLN_BANK` (kupujący) | BIGINT grosze | Niemożliwe |
| `B2B_LOSS_PIPELINE` | Zwrot escrow za straty rurociągu > norma | `PLN_ESCROW` | `PLN_BANK` (kupujący) — tylko nadwyżka strat | BIGINT grosze | Niemożliwe |
| `B2B_REFUND_AUTO` | Automatyczny zwrot escrow po timeout sporu | `PLN_ESCROW` | `PLN_BANK` (kupujący) | BIGINT grosze | Niemożliwe |
| `B2B_RESOLVE_GM` | Rozliczenie sporu przez GM — kwota główna | `PLN_ESCROW` | zgodnie z wyrokiem | BIGINT grosze | Niemożliwe |
| `B2B_PENALTY` | Kara umowna z wyroku GM | `PLN_BANK` (strona przegrywająca) | `PLN_BANK` (strona wygrywająca) | BIGINT grosze | Jeśli brak → zobowiązanie 72h (§17.1) |
| `B2B_DAMAGES` | Odszkodowanie z wyroku GM | `PLN_BANK` (strona przegrywająca) | `PLN_BANK` (strona wygrywająca) | BIGINT grosze | Jak wyżej |
| `B2B_EXPIRED_ESCROW` | Automatyczny zwrot po 7-dniowym limicie escrow | `PLN_ESCROW` | `PLN_BANK` (kupujący) lub `PLN_BANK` (sprzedający) — zależnie od stanu dostawy | BIGINT grosze | Niemożliwe |
| `CASH_DEPOSIT` | Wpłata gotówki na konto bankowe | `PLN_CASH` | `PLN_BANK` | BIGINT grosze | Jeśli brak na CASH → odrzucone |
| `CASH_WITHDRAWAL` | Wypłata z konta bankowego do gotówki | `PLN_BANK` | `PLN_CASH` | BIGINT grosze | Jeśli brak na BANK → odrzucone |
| `CASH_WITHDRAWAL_FEE` | Opłata za wypłatę gotówki (1%) | `PLN_BANK` → `SYS_TREASURY` | (potrącane z kwoty wypłaty) | BIGINT grosze | — |

---

## 4. TRANSFERY PLN_CASH ↔ PLN_BANK

### 4.1 Wpłata (PLN_CASH → PLN_BANK)

| Parametr | Wartość | Uzasadnienie |
|---|---|---|
| Koszt | **0% (bezpłatna)** | Wpłata nie tworzy ryzyka prania/ucieczki przed egzekucją (pieniądze stają się BARDZIEJ widoczne dla systemu) |
| Czas | **Natychmiast** | Atomowa transakcja DB — nie ma powodu do opóźnienia |
| Limit | **Brak** | Gotówka i tak podlega egzekucji Fazy 1 (§17.2) |
| Wpis FTS | `CASH_DEPOSIT` | `PLN_CASH` → `PLN_BANK` |

### 4.2 Wypłata (PLN_BANK → PLN_CASH)

| Parametr | Wartość | Uzasadnienie |
|---|---|---|
| Koszt | **1% wartości** (trafia do `SYS_TREASURY`) | Opłata zniechęca do częstych wypłat; tworzy sink walutowy |
| Czas | **Natychmiast** wykonanie, ale z **24h oknem cofnięcia** przez system przy wejściu w egzekucję (patrz 4.3) | Natychmiastowa dostępność dla gracza, ale system może cofnąć przy nadużyciu |
| Limit dobowy | **500 000 PLN** (500 mln groszy) | Limit Tieru V to 400 000 PLN/dobę z Kroplówki — limit wypłaty jest nieco wyższy, żeby legalny gracz nie blokował się przy dużej sprzedaży spot |
| Wpis FTS | `CASH_WITHDRAWAL` + `CASH_WITHDRAWAL_FEE` | `PLN_BANK` → `PLN_CASH` + `PLN_BANK` → `SYS_TREASURY` |

### 4.3 Interakcja z egzekucją Fazy 1 — zabezpieczenie przed ucieczką

Problem: gracz w Fazie 1 egzekucji masowo wypłaca pieniądze z PLN_BANK na PLN_CASH, żeby uniknąć zajęcia 50% wpływów. Ale Faza 1 zajmuje RÓWNIEŻ PLN_CASH (§17.2) — więc to nie działa.

**Jednak:** przelew BANK → CASH wykonywany po wejściu w Fazę 1 podlega **24-godzinnemu oknu cofnięcia**. Jeśli w ciągu 24h od wypłaty system wykryje, że wypłata służyła uniknięciu egzekucji (wskaźnik: wypłata >50% salda BANK w ciągu 1h od wejścia w Fazę 1), wypłata jest cofana, a gracz dostaje ostrzeżenie.

Dodatkowo: **w Fazie 1 każda wypłata BANK → CASH jest automatycznie obciążana 50% zajęciem** (tak jak wpływ na BANK). To zamyka ścieżkę: „wypłacę wszystko na gotówkę i schowam".

### 4.4 Fallback raty kredytu z PLN_CASH

Problem A7: gracz ma pusty PLN_BANK, pełny PLN_CASH, rata kredytu nie ma z czego zejść → `late`.

**Kolejność pobierania raty kredytu:**
1. `PLN_BANK` — podstawowe źródło
2. Jeśli BANK < rata: automatyczny przelew brakującej kwoty z `PLN_CASH` → `PLN_BANK` → rata
3. Opłata za automatyczny przelew ratowy: **0%** (nie karzemy gracza za brak manualnej operacji)
4. Jeśli CASH też za mało: rata niezapłacona → `late`

Wpis FTS: `LOAN_PAYMENT` (jak dotychczas) + opcjonalnie `CASH_DEPOSIT` (automatyczny przy braku w BANK).

---

## 5. POPRAWKI KARTELOWE (§26.3)

### Nowy tekst zastępujący obietnicę „zwolnienia z prowizji B2B":

> **3. Kartele naftowe (sojusze):** dobrowolne bloki 2–5 firm — wspólny kanał, widoczność statusów sojuszników (online/awarie), **pakt o nieagresji** (sabotaż wewnątrz kartelu = automatyczne wydalenie agresora + kara −10 `company_credibility`).
>
> **Przywileje handlowe wewnątrz kartelu:**
> - Transakcje B2B między członkami kartelu są zwolnione z prowizji escrow (0% zamiast 2%) **do limitu 50 000 bbl na parę kartelową na miesiąc kalendarzowy** (suma wolumenu transakcji między dwoma konkretnymi członkami kartelu w ciągu 30 dni kroczących).
> - Powyżej limitu: prowizja 1% (połowa stawki rynkowej).
> - **Dodatkowy limit anty-pralniczy:** dzienna wartość transakcji B2B między dwoma członkami kartelu nie może przekroczyć **równowartości limitu Kroplówki niższego z Tierów obu stron** (§22.6). Przekroczenie: transakcja jest odrzucana z komunikatem „Limit dzienny przekroczony — podejrzenie obrotu pozornego".
> - Założenie kartelu: 50 000 PLN + zgoda wszystkich członków; rozwiązanie z 7-dniową karencją.
>
> **Anty-zmowa:** rejestr karteli jest publiczny; limit wielkości — max **40% łącznej produkcji serwera** w jednym kartelu (powyżej: blokada rekrutacji); zmowa cenowa na spocie wykrywana telemetrią (§29.3) i rozstrzygana przez GM.
>
> **Monitoring kartelowy w panelu GM:**
> - Wskaźnik `cartel_volume_ratio`: stosunek wolumenu B2B wewnątrz kartelu do całkowitego wydobycia członków — alarm przy >60%
> - Wskaźnik `cartel_cash_flow_asymmetry`: przepływ gotówki netto między parą członków w oknie 30-dniowym — alarm przy >500 000 PLN bez odpowiadającego przepływu ropy
> - Historia transakcji B2B wewnątrz kartelu z podświetleniem transakcji odrzuconych limitem anty-pralniczym

### Sankcje za obejście limitów kartelowych:

1. **Pierwsze wykrycie:** ostrzeżenie GM + blokada przywilejów kartelowych dla pary graczy na 7 dni
2. **Drugie wykrycie:** kara −5 `company_credibility` dla obu stron + blokada na 30 dni
3. **Trzecie wykrycie:** rozwiązanie kartelu + −15 `company_credibility` dla wszystkich członków + 30-dniowy zakaz tworzenia/dołączania do karteli

---

## 6. INTERAKCJE MIĘDZYMODUŁOWE

### 6.1 Egzekucja komornicza a escrow/transza

| Sytuacja | Reguła |
|---|---|
| Sprzedający wchodzi w Fazę 1 w trakcie `escrow_locked` | Escrow NIETYKALNY (§21). Po `settled` pieniądze trafiają na `PLN_BANK` sprzedającego → Faza 1 tnie 50% wpływu. |
| Sprzedający w Fazie 2 (licytacja ropy) — ropa `locked_in_escrow_bbl` | Wyłączona z licytacji (§17.2: „wyłączone: `locked_in_escrow_bbl` i `reserved_bbl`"). |
| Sprzedający w Fazie 3 (licytacja odwiertów) — odwiert produkuje ropę na aktywną transzę B2B | Jeśli odwiert ma `locked_in_escrow_bbl` — trafia do komornika z tym obciążeniem. Kupujący odwiertu na aukcji przejmuje zobowiązanie dostawy (lub może je zerwać z karą 12% — kara idzie do masy upadłościowej). |
| Kupujący wchodzi w Fazę 2 — ropa w drodze do niego (B2B) | Transakcja kontynuowana. Ropa dociera → `delivered` → magazyn kupującego. Faza 2 NIE blokuje przyjęcia ropy — licytuje tylko ropę już będącą w magazynie. |
| Kupujący w Fazie 3 (licytacja aktywów) — ropa B2B w drodze | Jak wyżej — transakcja kontynuowana, ropa trafia do kupującego. |

### 6.2 Dormant/urlop strony w trakcie transakcji

| Sytuacja | Reguła |
|---|---|
| Sprzedający wchodzi w `dormant` (48h bez logowania) z aktywną ofertą `published` | Oferta przechodzi w `expired` po standardowym 7-dniowym limicie (lub wcześniej, jeśli 7 dni minęło przed dormant). Nie ma przyspieszenia — gracz mógł celowo zostawić ofertę. |
| Sprzedający w `dormant`, oferta w `in_transit` | Transport leci normalnie (systemowy). Po `delivered` → `settled` automatycznie. |
| Sprzedający w `dormant`, oferta w `disputed` | Timeouty sporu liczą się normalnie — brak reakcji 24h → `refunded` automatycznie. |
| Kupujący w `dormant`, transport w drodze | Transport dociera → `delivered`. Jeśli magazyn pełny → `partial_unload` + opłata postojowa naliczana normalnie (kupujący dostaje zobowiązanie). |
| Urlop (§19.2) | Ochrona offline: timeouty sporu są ZAWIESZANE na czas urlopu (do 7 dni). Po powrocie timeouty wznawiają się od miejsca zatrzymania. |

### 6.3 VIP (§22.6) a limity ofert

| Przywilej | Limit standardowy | Limit VIP |
|---|---|---|
| Aktywne oferty B2B (`published`) | 3 | 10 |
| Aktywne kontrakty ratalne | 1 | 3 |

Wygaśnięcie VIP: nadmiarowe oferty (>3/>1) NIE są anulowane — pozostają aktywne do naturalnego `expired`/`settled`/`cancelled`. Nowe oferty można tworzyć tylko w limicie standardowym. To spójne z zasadą „brak cliffów w środku operacji" (§22.6).

### 6.4 Ochrona ładunku a utrata w transporcie — kto dostaje odszkodowanie

Właścicielem ropy do `delivered` jest SPRZEDAJĄCY → odszkodowanie z tytułu ochrony ładunku (`escort_basic`, `armed_convoy`, `drone_patrol`) trafia do SPRZEDAJĄCEGO.

**Problem:** kupujący płaci pełną kwotę w escrow, ropa ginie, kupujący dostaje zwrot escrow — ale „stracił okazję" (cena mogła wzrosnąć). Czy kupujący powinien dostać odszkodowanie?

**Reguła:** odszkodowanie wypłaca `SYS_INSURANCE_POOL` tylko za UBEZPIECZONE kursy (§11A.8). Jeśli sprzedający NIE wykupił ubezpieczenia — kupujący dostaje tylko zwrot escrow (100% kwoty). Jeśli sprzedający wykupił ubezpieczenie — odszkodowanie trafia do sprzedającego (pokrywa jego stratę ropy), a kupujący dostaje zwrot escrow + **5% wartości transakcji** z puli ubezpieczeniowej jako rekompensatę utraconej okazji (chyba że cena spot spadła — wtedy 0%).

### 6.5 Reklamacja jakości a black_market_score

Ropa z czarnego rynku (kradziona/przepuszczona przez nielegalne wcięcia — §11A.3 rodzaj 6) ma parametr `black_market_origin = true` w rejestrze partii.

**Reguła:** jeśli kupujący przez B2B otrzymał ropę z czarnorynkowego źródła, a sprzedający nie ujawnił tego w ofercie ( checkbox „ropa z legalnego źródła"), kupujący może otworzyć spór z tytułu wady ukrytej. Konsekwencje dla sprzedającego:
- Kara 5% wartości transakcji → kupujący
- `b2b_reputation_score` −15 pkt
- Jeśli ropa została już zmieszana w magazynie kupującego z legalną → `black_market_score` magazynu = ważony udział (nie ma fizycznego rozdziału — to sankcja ekonomiczna: +2 do wskaźnika czarnorynkowego firmy kupującego, §2)
- Sprzedający ponosi koszt kontroli skarbowej kupującego, jeśli taka nastąpi w ciągu 30 dni

---

## 7. TESTY ODBIORCZE — 14 scenariuszy blokujących wydanie

**(T-B2B-1) Podwójne rozliczenie leniwe + zamiatacz tej samej transzy**
- Transza w stanie `delivered`, gracz klika „Rozlicz" (leniwe) RÓWNOCZEŚNIE z 5-minutowym zamiataczem
- Oczekiwane: dokładnie jedno `settled`, drugie żądanie dostaje `idempotency_key` konflikt → no-op
- Weryfikacja: `SELECT COUNT(*) FROM fts_entries WHERE idempotency_key = ?` = 1

**(T-B2B-2) Spór otwarty w ostatniej godzinie 7-dniowego limitu escrow**
- `accepted` 6 dni 23h temu, kupujący otwiera spór
- Oczekiwane: 1h później (7 dni od `accepted`) → jeśli GM nie rozstrzygnął → automatyczne `expired` z refundem do kupującego
- Weryfikacja: stan = `refunded`, escrow = 0, ropa u sprzedającego

**(T-B2B-3) Częściowy rozładunek + utrata reszty w transporcie**
- Transport: 10 000 bbl. Kupujący ma miejsce tylko na 4 000 bbl. Rozładunek 4 000 → `partial_unload`. Pozostałe 6 000 bbl wraca do huba/oczekuje. W drodze powrotnej — sabotaż, 100% utrata.
- Oczekiwane: 4 000 bbl → `settled` (pieniądze za nie do sprzedającego), 6 000 bbl → zwrot escrow do kupującego
- Weryfikacja: dwa wpisy FTS — `B2B_SETTLE` (4 000 bbl) i `B2B_LOSS_THEFT` (6 000 bbl)

**(T-B2B-4) Bankructwo kupującego po dostawie, przed rozliczeniem**
- `delivered`, kupujący wchodzi w `bankruptcy` zanim system przeszedł w `settled`
- Oczekiwane: `settled` normalnie — escrow jest wydzielony (§21), sprzedający dostaje pieniądze
- Weryfikacja: stan = `settled`, `PLN_BANK` sprzedającego zwiększony

**(T-B2B-5) Arbitraż anulowania przy spadku ceny 8%**
- `accepted` przy cenie 250 PLN/bbl. W ciągu 60 min cena spada do 230 PLN/bbl (−8%). Kupujący klika „Anuluj".
- Oczekiwane: kara = max(12%, |−8%| + 3%) = max(12%, 11%) = 12%. Kupujący traci 12% wartości.
- Weryfikacja: `PLN_BANK` kupującego pomniejszony o 12%, sprzedającego powiększony o 12%

**(T-B2B-6) Arbitraż anulowania przy spadku ceny 15% (wydarzenie globalne)**
- `accepted` przy 250, spadek o 15% do 212,50 (wydarzenie §25). Kupujący anuluje.
- Oczekiwane: kara = max(12%, 15% + 3%) = 18%. Kupujący traci 18%, zysk ze spadku 15% → netto −3%. Arbitraż zamknięty.
- Weryfikacja: kara 18% pobrana

**(T-B2B-7) Pralnia kartelowa — symulacja 30 dni**
- Gracz A (Tier V, limit 400 GBBL/dobę). Gracz B (Tier II, limit 50 GBBL/dobę).
- Dzień 1–10: A używa Kroplówki (400 000 PLN/dzień) → kupuje ropę na spocie → sprzedaje B przez B2B (0% prowizji, limit 50 000 bbl/miesiąc na parę = 1 666 bbl/dzień = 416 500 PLN/dzień przy 250 PLN/bbl).
- Limit dzienny anty-pralniczy: min(400 000, 50 000) = 50 000 PLN/dzień (równowartość limitu Kroplówki Tier II)
- Oczekiwane: transakcje powyżej 50 000 PLN/dzień są odrzucane. Po przekroczeniu 50 000 bbl w 30 dni → prowizja 1% zamiast 0%.
- Weryfikacja: licznik `cartel_pair_volume_30d` ≤ 50 000 bbl; odrzucone transakcje > limit dzienny; prowizja 1% naliczona powyżej progu

**(T-B2B-8) Magazyn kupującego pełny przy dojeździe → opłata postojowa → timeout**
- Transport 5 000 bbl. Magazyn kupującego ma 200 bbl wolne. Rozładunek 200 → `partial_unload`. Pozostałe 4 800 bbl czeka. 2h → opłata postojowa 2 000 PLN/h (§21) obciąża kupującego. 48h → holownik.
- Oczekiwane: opłata naliczona kupującemu; brak kary dla sprzedającego; po 48h automatyczny zwrot ropy do sprzedającego (holownik) + częściowe rozliczenie 200 bbl
- Weryfikacja: wpis `B2B_PARTIAL_DELIVERY` + opłata postojowa w FTS kupującego

**(T-B2B-9) Równoległa sprzedaż tej samej ropy: spot + B2B**
- Gracz wystawia ofertę B2B (`published`, `reserved_b2b` = 10 000 bbl) i RÓWNOCZEŚNIE próbuje sprzedać 10 000 bbl na spocie
- Oczekiwane: sprzedaż spot odrzucona — `reserved_b2b` blokuje wolumen (§11A.8 T7)
- Weryfikacja: błąd „Niewystarczający dostępny wolumen — 10 000 bbl zarezerwowane pod ofertę B2B #1234"

**(T-B2B-10) Dormant sprzedającego, timeout sporu**
- `in_transit` → `disputed` (timeout). Sprzedający offline przez 48h (dormant). 24h timeout sporu.
- Oczekiwane: `refunded` automatycznie po 24h — dormant nie zawiesza timeoutu sporu (ochrona offline = urlop, nie dormant)
- Weryfikacja: stan = `refunded`, `b2b_reputation_score` sprzedającego −8 pkt

**(T-B2B-11) Urlop sprzedającego, timeout sporu**
- Jak wyżej, ale sprzedający na aktywnym urlopie (§19.2).
- Oczekiwane: timeout sporu ZAWIESZONY na czas urlopu (max 7 dni). Po powrocie — 24h od nowa.
- Weryfikacja: stan = nadal `disputed`, licznik timeoutu zatrzymany

**(T-B2B-12) Eksplozja rurociągu — tylko tranzyt tracony, magazyn nietknięty**
- Rurociąg z `locked_in_escrow_bbl` (ropa B2B w tranzycie). Eksplozja (§11A.3).
- Oczekiwane: utrata ropy w tranzycie TEGO odcinka (B2B), NIE magazynu. Escrow zwrócony do kupującego za utraconą część.
- Weryfikacja: `B2B_LOSS_ACCIDENT` tylko na wolumen w rurociągu; stan magazynu bez zmian

**(T-B2B-13) Wycofanie oferty `published` przez sprzedającego — czy może?**
- Obecna specyfikacja (§13.2): „kupujący wycofuje ofertę tylko w stanie `published`". Czy sprzedający może?
- Decyzja projektowa: TAK — sprzedający może wycofać własną ofertę w stanie `published` (nikt jeszcze nie zaakceptował). `reserved_b2b` zwalniane.
- Weryfikacja: stan = `cancelled`, ropa dostępna na spocie

**(T-B2B-14) Kontrakt ratalny — bankructwo sprzedającego po 2 z 5 transz**
- Kontrakt: 5 transz po 10 000 bbl. 2 transze `settled`. Sprzedający bankrutuje.
- Oczekiwane: pozostałe 3 transze → `terminated`. `locked_in_escrow_bbl` dla niezrealizowanych transz zwolnione. Kaucja (10% wartości kontraktu, §13.1) przepada na rzecz kupującego. Kupujący NIE płaci kary za zerwanie — to bankructwo sprzedającego przerywa kontrakt.
- Weryfikacja: 3 transze w stanie `terminated`, kaucja → `PLN_BANK` kupującego

---

## 8. GOTOWA TREŚĆ DO WKLEJENIA DO SPECYFIKACJI

### 8.1 Poprawiony §13.2 (Giełda Kontraktów B2B)

```markdown
## 13.2. Giełda Kontraktów B2B (Handel Między Graczami) — Maszyna Stanów

### 13.2.1. Stany oferty pojedynczej
`draft → published → accepted → escrow_locked → in_transit → delivered → settled`
Ścieżki wyjątków: `cancelled` (z `draft` lub `published`, przez wystawcę), `expired` (z `published` po 7 dniach), `terminated` (kara 12% po `accepted`), `partial_unload` (magazyn kupującego pełny), `disputed → resolved | refunded`.

### 13.2.2. Atomowe przejścia — co się dzieje

**`draft → published`:**
- Sprzedający klika „Opublikuj".
- Rezerwacja: `reserved_b2b` = wolumen oferty w magazynie sprzedającego (ropa nie może być sprzedana na spocie ani w innej ofercie).
- Czas życia: 7 dni kalendarzowych do `expired`.

**`published → accepted`:**
- Kupujący klika „Akceptuj". Atomowo (SELECT FOR UPDATE na ofercie, magazynie, PLN_BANK kupującego):
  - `reserved_b2b` → `locked_in_escrow_bbl` (mocniejsza blokada).
  - 100% wartości oferty przechodzi z `PLN_BANK` kupującego na `PLN_ESCROW` (dedykowany rekord escrow per transakcja).
  - Wpis FTS: `B2B_ESCROW_LOCK` (`PLN_BANK` kupującego → `PLN_ESCROW`).
  - Sprzedający ma 24h na wysyłkę transportu.

**`accepted → escrow_locked → in_transit`:**
- Sprzedający wybiera środek transportu (§11A.6), płaci koszt kursu (osobny wpis FTS `LOG_TRANSPORT_COST`).
- Ropa przechodzi z magazynu sprzedającego na pojazd/rurociąg/tankowiec.
- Wpis `oil_movements`: `reason_code=LOAD_B2B_SHIPMENT`, źródło = magazyn sprzedającego, cel = pojazd/rurociąg.
- Stan `in_transit` z ETA.

**`in_transit → delivered`:**
- Transport dociera do magazynu/huba kupującego. `delivery_receipt` = potwierdzenie rozładunku.
- Ropa przechodzi z pojazdu/rurociągu do magazynu kupującego.
- Wpis `oil_movements`: `reason_code=UNLOAD_B2B`, źródło = pojazd/rurociąg, cel = magazyn kupującego.
- `locked_in_escrow_bbl` zwolnione (ropa już u kupującego).

**`delivered → settled` (automat po 1h):**
- 1h to okno na reklamację ilości kupującego. Jeśli brak reklamacji:
  - Prowizja B2B (2%) z `PLN_ESCROW` → `SYS_TREASURY` (wpis `B2B_COMMISSION`).
  - Pozostała kwota z `PLN_ESCROW` → `PLN_BANK` sprzedającego (wpis `B2B_SETTLE`).
  - `b2b_reputation_score` sprzedającego: +3 pkt (terminowa dostawa). Kupującego: +1 pkt.

### 13.2.3. Dostawy ratalne (transze)
- Kontrakt ratalny ma encję nadrzędną (`contract_header`) z polami: `total_volume_bbl`, `installment_count`, `schedule_days`, stan kontraktu.
- Każda transza (`contract_installment`) ma własną maszynę stanów 13.2.2.
- Rezerwacja w `accepted`: cały wolumen kontraktu → `locked_in_escrow_bbl`. Escrow pieniężny: tylko PIERWSZA transza. Kolejne transze blokują środki kupującego na 24h przed planowaną wysyłką.
- Kaucja kontraktowa (10% wartości, §13.1): osobne pole `security_deposit_grosze`, zwracana po `settled` wszystkich transz.

### 13.2.4. Timeouty i ścieżki wyjątków

| Zdarzenie | Czas | Akcja |
|---|---|---|
| `published` bez akceptacji | 7 dni | `expired`, `reserved_b2b` zwolnione |
| `accepted` bez wysyłki | 24h | `terminated` — kara 12% na rzecz kupującego |
| Transport nie dociera w ETA | ETA + 6h | `disputed` — zamrożenie escrow i ropy |
| `disputed`, brak reakcji sprzedawcy | 24h | `refunded` — 100% escrow → kupujący, ropa → sprzedający, `b2b_reputation` sprzedającego −8 pkt |
| `disputed`, spór nierozstrzygnięty | 7 dni od `accepted` | Automatyczne rozstrzygnięcie: escrow → kupujący (ropa nie dotarła) lub → sprzedający (ropa dotarła, spór o jakość) |
| `delivered` bez reklamacji | 1h | `settled` automatycznie |

### 13.2.5. Anulowanie, kara i arbitraż
- Kupujący może wycofać ofertę (`cancelled`) tylko w stanie `published` — bez kary.
- Sprzedający może wycofać WŁASNĄ ofertę w stanie `published` — bez kary, `reserved_b2b` zwalniane.
- Po `accepted` anulowanie przez DOWOLNĄ stronę = kara:
  ```
  kara = max(12%, |zmiana_ceny_spot_od_accepted| + 3%)
  ```
  gdzie `zmiana_ceny_spot_od_accepted` to procentowa zmiana ceny spot od momentu `accepted` do momentu anulowania. Kara liczona od wartości transakcji, płacona stronie przeciwnej z `PLN_ESCROW`. Reszta escrow wraca do strony anulującej (wpis `B2B_CANCEL_PENALTY` + `B2B_CANCEL_PENALTY_RETURN`). Formuła gwarantuje, że zysk arbitrażowy nigdy nie przewyższy kary.

### 13.2.6. Prowizja B2B
- 2% od wartości transakcji, pobierana od sprzedającego w momencie `settled`.
- Trafia do `SYS_TREASURY` (sink walutowy).
- Członkowie kartelu (§26.3): 0% dla pierwszych 50 000 bbl/miesiąc na parę kartelową, 1% powyżej.
- Prowizja NIE jest pobierana przy `refunded`/`expired`/`terminated` — tylko przy faktycznej dostawie.

### 13.2.7. Utrata ładunku w transporcie
- Do momentu `delivered` właścicielem ropy jest SPRZEDAJĄCY — on ponosi ryzyko transportowe on wybiera środek transportu.
- Kradzież 100%: ropa `lost`, escrow w 100% → kupujący (`B2B_LOSS_THEFT`).
- Kradzież częściowa X%: X% escrow → kupujący, (100−X)% → `settled` normalnie.
- Wypadek/eksplozja: escrow → kupujący (`B2B_LOSS_ACCIDENT`).
- Straty ≤ strata_bazowa środka transportu (§11A.3): tolerowane — nie są podstawą sporu. Straty > norma: kupujący może otworzyć spór.
- Ubezpieczenie kursu (§11A.8): odszkodowanie z `SYS_INSURANCE_POOL` → sprzedający (właściciel ropy). Kupujący dostaje 5% wartości transakcji jako rekompensatę utraconej okazji, tylko przy ubezpieczonym kursie i wzroście ceny spot od `accepted`.

### 13.2.8. Magazyn kupującego pełny
- Transport dociera, magazyn pełny: `partial_unload` — tyle, ile miejsca. Reszta czeka w pojeździe (opłata postojowa 2 000 PLN/h po 2h, płaci KUPUJĄCY).
- Sprzedający NIE ponosi kary za opóźnienie — to wina kupującego (§21).
- Po 48h: holownik zwraca pozostałą ropę sprzedającemu (koszt 10 000 PLN → kupujący). Rozliczeniu podlega tylko rozładowana część.

### 13.2.9. Egzekucja komornicza a transakcja B2B
- Środki w `PLN_ESCROW` są prawnie wydzielone — komornik NIE może ich zająć do rozliczenia transakcji (§21).
- `locked_in_escrow_bbl` wyłączone z licytacji ropy (Faza 2, §17.2).
- Po `settled` pieniądze trafiają na `PLN_BANK` sprzedającego — Faza 1 egzekucji tnie 50% tego wpływu.
- Bankructwo sprzedającego po `delivered`, przed `settled`: `settled` normalnie — escrow wydzielony.
- Bankructwo kupującego: escrow nietknięty, transakcja kontynuowana.

### 13.2.10. Spór (`disputed`)
- Otwiera dowolna strona z powodem + dowodami (screeny, logi systemowe).
- Rozstrzyga GM na podstawie rejestrów: `oil_movements`, `delivery_receipt`, FTS, ochrona, sabotaże (§11A.9 pkt 3).
- Ropa i escrow zamrożone do rozstrzygnięcia.
- Timeouty: 24h na reakcję drugiej strony → `refunded`; 72h bez reakcji obu → `refunded`.
- Sprzedający z przegranym sporem: `b2b_reputation_score` −8 pkt; kupujący z przegranym: −4 pkt.
- Twardy limit: 7 dni od `accepted` — po tym automatyczne rozstrzygnięcie na korzyść kupującego (ropa nie dotarła) lub sprzedającego (ropa dotarła).

### 13.2.11. Reklamacja jakości a czarny rynek
- Sprzedający deklaruje w ofercie checkbox „ropa z legalnego źródła".
- Dostarczenie ropy o `black_market_origin = true` bez ujawnienia = wada ukryta.
- Kupujący może otworzyć spór → kara 5% wartości → kupujący, `b2b_reputation_score` −15 pkt sprzedającego.
- `black_market_score` magazynu kupującego = ważony udział „brudnej" ropy — ryzyko kontroli skarbowej (§2).

### 13.2.12. Kontrola kont powiązanych
- Transakcje B2B między kontami o wspólnym odcisku (urządzenie, metoda płatności, IP) są blokowane do ręcznego przeglądu GM.
- Dodatkowo: transakcje B2B wewnątrz kartelu powyżej limitu dziennego anty-pralniczego (§26.3) są automatycznie odrzucane.
```

### 8.2. Nowa §24.5 (Transfery gotówka ↔ bank)

```markdown
## 24.5. Transfery PLN_CASH ↔ PLN_BANK

Gracz posiada dwa konta waluty gry: `PLN_CASH` (portfel gotówkowy — wpływy czarnorynkowe, podlega egzekucji) i `PLN_BANK` (rachunek bankowy — wypłaty spot, raty, podatki). Transfer między nimi jest jawny i księgowany.

### 24.5.1. Wpłata (PLN_CASH → PLN_BANK)
- Koszt: **bezpłatny** (0%).
- Czas: **natychmiastowy** (atomowa transakcja DB).
- Limit dobowy: **brak**.
- Wpis FTS: `CASH_DEPOSIT` (`PLN_CASH` → `PLN_BANK`, `actor_id` = gracz).
- Cel: umożliwienie spłaty rat kredytu, opłat systemowych z gotówki.

### 24.5.2. Wypłata (PLN_BANK → PLN_CASH)
- Koszt: **1% wartości** (pobierane z `PLN_BANK`, trafia do `SYS_TREASURY`).
- Czas: **natychmiastowy** dostęp do gotówki, z **24h oknem cofnięcia** przez system (tylko przy wejściu w egzekucję w ciągu 24h od wypłaty — system cofa wypłatę, jeśli stanowiła >50% salda BANK i nastąpiła w ciągu 1h od wejścia w Fazę 1).
- Limit dobowy: **500 000 PLN** (500 mln groszy).
- Wpisy FTS: `CASH_WITHDRAWAL` (`PLN_BANK` → `PLN_CASH`) + `CASH_WITHDRAWAL_FEE` (`PLN_BANK` → `SYS_TREASURY`).
- Dodatkowa reguła: w Fazie 1 egzekucji (§17.2) każda wypłata BANK → CASH jest automatycznie obciążana 50% zajęciem komorniczym (tak jak wpływ na BANK).

### 24.5.3. Fallback raty kredytu z PLN_CASH
Kolejność pobierania raty kredytu (§17.1) co 24h:
1. `PLN_BANK` — podstawowe źródło.
2. Jeśli BANK < rata: automatyczny przelew brakującej kwoty `PLN_CASH` → `PLN_BANK` → rata. Koszt automatycznego przelewu: 0%.
3. Jeśli CASH też < rata: rata niezapłacona → `late`, odsetki karne +150% (§17.1).

Wpis FTS: `LOAN_PAYMENT` (standardowy) + opcjonalne `CASH_DEPOSIT` (automatyczne, z `reason_code=LOAN_FALLBACK`).

### 24.5.4. Zabezpieczenia
- Transfery NIE mogą służyć do ukrywania majątku przed egzekucją — PLN_CASH RÓWNIEŻ podlega egzekucji Fazy 1 (§17.2) i Fazy 2/3 (poprzez zajęcie ropy i aktywów).
- Okno cofnięcia 24h i automatyczne zajęcie 50% w Fazie 1 zamykają ścieżkę „wypłata wszystkiego na gotówkę w momencie wejścia komornika".
- Monitoring w panelu GM: wskaźnik `cash_withdrawal_surge` — alarm przy wypłacie >200% średniej dziennej z ostatnich 7 dni.
```

### 8.3. Poprawiona §26.3 (Kartele)

```markdown
## 26.3. Kartele Naftowe (Sojusze)

### 26.3.1. Struktura
- 2–5 firm w kartelu. Założenie: 50 000 PLN + zgoda wszystkich członków (każdy klika „Akceptuj").
- Rozwiązanie: 7-dniowa karencja (w tym czasie przywileje kartelowe nadal obowiązują). Po karencji: kartel rozwiązany, przywileje wygasają, kanał czatu archiwalny.

### 26.3.2. Przywileje
- Wspólny kanał czatu `cartel_{id}`.
- Widoczność statusów sojuszników: online/offline, awarie krytyczne.
- **Pakt o nieagresji:** sabotaż (§7) wewnątrz kartelu = automatyczne wydalenie agresora + kara −10 `company_credibility`.
- **Preferencje handlowe B2B:**
  - Prowizja B2B: 0% (zamiast 2%) dla transakcji między dwoma konkretnymi członkami kartelu, do limitu **50 000 bbl na parę kartelową na 30 dni kroczących**.
  - Powyżej limitu: prowizja 1% (połowa stawki rynkowej).
  - **Limit anty-pralniczy:** dzienna wartość transakcji B2B między dwoma członkami kartelu nie może przekroczyć **równowartości limitu Kroplówki niższego z Tierów obu stron** (§22.6). Przeliczenie po cenie spot z momentu `accepted`. Przekroczenie: transakcja odrzucona z komunikatem „Limit dzienny przekroczony — podejrzenie obrotu pozornego".

  Przykład: Tier V (400 GBBL = 400 000 PLN/dzień) + Tier II (50 GBBL = 50 000 PLN/dzień). Limit dzienny = 50 000 PLN = 200 bbl/dzień przy cenie 250 PLN/bbl. Transakcja na 300 bbl zostanie odrzucona.

### 26.3.3. Limity globalne i anty-zmowa
- Rejestr karteli jest publiczny (członkowie, data założenia, łączna produkcja).
- Max 40% łącznej produkcji serwera w jednym kartelu — powyżej: blokada rekrutacji.
- Zmowa cenowa na spocie wykrywana telemetrią (§29.3): skorelowane wystawianie ofert spot przez członków kartelu w oknie 10 min → alert GM.

### 26.3.4. Monitoring w panelu GM
- `cartel_volume_ratio`: stosunek wolumenu B2B wewnątrz kartelu do całkowitego wydobycia członków. Alarm: >60%.
- `cartel_cash_flow_asymmetry`: przepływ gotówki netto między parą członków w oknie 30-dniowym. Alarm: >500 000 PLN bez odpowiadającego przepływu ropy.
- `cartel_daily_limit_rejections`: liczba transakcji odrzuconych limitem anty-pralnicznym. Alarm: >3/dzień dla tej samej pary.
- Historia transakcji B2B wewnątrz kartelu z podświetleniem transakcji odrzuconych.

### 26.3.5. Sankcje za obejście limitów
- Pierwsze wykrycie: ostrzeżenie GM + blokada przywilejów kartelowych dla pary graczy na 7 dni.
- Drugie wykrycie: kara −5 `company_credibility` dla obu stron + blokada na 30 dni.
- Trzecie wykrycie: rozwiązanie całego kartelu + −15 `company_credibility` dla wszystkich członków + 30-dniowy zakaz tworzenia i dołączania do karteli.
```

---

## 9. LISTA DECYZJI DLA WŁAŚCICIELA GRY — max 6 pozycji

| # | Decyzja | Rekomendacja | Uzasadnienie |
|---|---|---|---|
| **1** | Czy kara za anulowanie B2B ma być stała (12%) czy kierunkowa (max(12%, zmiana_ceny+3%))? | **Kierunkowa** | Przy stałej 12% arbitraż nadal istnieje przy spadku >12%. Formuła kierunkowa zamyka arbitraż matematycznie — kara zawsze > zysk. Koszt implementacji: 1 dodatkowe zapytanie o cenę spot. |
| **2** | Czy limit escrow 7 dni ma być twardy (automatyczne rozstrzygnięcie), czy miękki (eskalacja do GM)? | **Twardy** | Przy zerowym kasowaniu świata nie może istnieć mechanizm wiecznego zamrożenia aktywów. 7 dni to wystarczający czas na spór. Eskalacja do GM przy miękkim limicie to iluzja — GM może nie zdążyć, a eskaluje się w nieskończoność. |
| **3** | Czy transfery PLN_CASH ↔ PLN_BANK mają mieć opłatę i opóźnienie? | **Wpłata: 0% / natychmiast. Wypłata: 1% / natychmiast z oknem cofnięcia 24h.** | Opłata 0% za wpłatę nie zniechęca do legalnego transferu. 1% za wypłatę to sink walutowy. Okno cofnięcia 24h + automatyczne 50% zajęcie w Fazie 1 to wystarczające zabezpieczenie bez irytującego opóźnienia dla gracza. |
| **4** | Czy fallback raty kredytu z PLN_CASH ma być automatyczny? | **Tak — automatyczny i bezpłatny.** | Gracz z pełnym portfelem gotówkowym nie może dostać `late` tylko dlatego, że nie kliknął „wpłać". Automatyczny fallback eliminuje frustrację i tickety supportu. |
| **5** | Limit anty-pralniczy kartelowy: równowartość limitu Kroplówki niższego Tieru × 1,0 — czy to właściwy mnożnik? | **Tak — ×1,0 to neutralność.** | Przy ×1,0 pralnia daje dokładnie tyle co legalna Kroplówka gracza B — nie premiuje prania. Przy ×0,8 uczciwy gracz mógłby trafić w limit przy normalnym handlu. ×1,0 to „nie karzemy za legalny handel, nie premiujemy prania". |
| **6** | Czy ubezpieczenie kursu B2B ma wypłacać odszkodowanie również kupującemu (utracona okazja)? | **Tak — 5% wartości transakcji przy wzroście ceny.** | Kupujący zamroził kapitał w escrow i stracił okazję zakupu po starej cenie. 5% to symboliczna rekompensata (mniej niż kara za anulowanie 12%, więcej niż nic). Wypłacane tylko z ubezpieczonego kursu — to daje realną wartość ubezpieczeniu jako produktowi. |
