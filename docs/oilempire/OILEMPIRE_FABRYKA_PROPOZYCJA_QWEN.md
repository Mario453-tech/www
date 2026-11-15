│ cache\scratch\refinery_calc.py
│ @@ -0,0 +1,42 @@
│ +crude = 250.0  # PLN/bbl cena bazowa (§24.1)
│ +
│ +# Mnożniki cen produktów względem ceny ropy (marża crackowa stabilna przy szokach §25)
│ +mult = {"benzyna": 1.68, "diesel": 1.60, "jet": 1.92, "mazut": 0.60, "lpg": 0.80}
│ +price = {k: crude * v for k, v in mult.items()}
│ +
│ +# Wariant A: tylko destylacja atmosferyczna (baza), nafta niesertyfikowana = 65% ceny
│ +yA = {"benzyna": 28, "diesel": 26, "jet": 6, "mazut": 32, "lpg": 4, "straty": 4}
│ +opexA = 22.0  # PLN/bbl wsadu
│ +revA = sum(yA[k] * (price[k] * (0.65 if k == "jet" else 1.0)) for k in yA if k != "straty")
│ +marzaA = revA - crude - opexA
│ +print("A destylacja: przychod/100bbl=%.0f, marza netto/100bbl=%.0f (%.2f PLN/bbl)" % (revA, marzaA, marzaA / 100))
│ +
│ +# Wariant B: pełny łańcuch (kraking + hydrooczyszczanie + reforming)
│ +yB = {"benzyna": 42, "diesel": 30, "jet": 12, "mazut": 6, "lpg": 5, "straty": 5}
│ +opexB = 55.0
│ +revB = sum(yB[k] * price[k] for k in yB if k != "straty")
│ +marzaB = revB - crude - opexB
│ +print("B pelny: przychod/100bbl=%.0f, marza netto/100bbl=%.0f (%.2f PLN/bbl)" % (revB, marzaB, marzaB / 100))
│ +
│ +# Klasy rafinerii i zwrot inwestycji
│ +for nazwa, cap, koszt, m in [
│ +    ("mala rozdzielnia", 150, 5_000_000, marzaA / 100),
│ +    ("srednia rafineria", 400, 35_000_000, (marzaA / 100 * 0.35 + marzaB / 100 * 0.65)),
│ +    ("duzy kompleks", 1000, 120_000_000, marzaB / 100),
│ +]:
│ +    h = cap * m
│ +    dzien = h * 24
│ +    print("%s: %d bbl/h, zysk/h=%.0f, zysk/dobe=%.0f, zwrot=%.0f dni" % (nazwa, cap, h, dzien, koszt / dzien))
│ +
│ +# Chłonność rynku NPC — region średni, bbl/dobę
│ +chlon = {"benzyna": 8000, "diesel": 12000, "jet": 4000, "mazut": 6000}
│ +max_przychod = sum(chlon[k] * price[k] for k in chlon)
│ +print("Maks. dzienny przychod NPC (region sredni): %.0f PLN" % max_przychod)
│ +print("Duzy kompleks 1000 bbl/h = %d bbl/dobe vs chlonnosc %d bbl/dobe -> %.0f%%" % (24000, sum(chlon.values()), 24000 / sum(chlon.values()) * 100))
│ +
│ +# Koszt przerobu vs spread: sprawdzenie progu opłacalności przy kryzysie -70% ceny ropy
│ +crude_low = crude * 0.3
│ +price_low = {k: crude_low * v for k, v in mult.items()}
│ +revB_low = sum(yB[k] * price_low[k] for k in yB if k != "straty")
│ +marzaB_low = revB_low - crude_low - opexB
│ +print("Kryzys -70%%: marza B/100bbl=%.0f (%.2f PLN/bbl) -> %s" % (marzaB_low, marzaB_low / 100, "dodatnia" if marzaB_low > 0 else "UJEMNA"))
│ cache\scratch\refinery_calc2.py
│ @@ -0,0 +1,42 @@
│ +crude_ref = 250.0
│ +mult = {"benzyna": 1.68, "diesel": 1.60, "jet": 1.92, "mazut": 0.60, "lpg": 0.80}
│ +base_price = {k: crude_ref * v for k, v in mult.items()}
│ +
│ +def price_factor(crude):
│ +    return max(0.4, min(2.0, 0.5 + 0.5 * crude / crude_ref))
│ +
│ +def margin(yields, opex_pbbl, crude, jet_ok=True):
│ +    f = price_factor(crude)
│ +    rev = 0.0
│ +    for k, v in yields.items():
│ +        if k == "straty":
│ +            continue
│ +        p = base_price[k] * f
│ +        if k == "jet" and not jet_ok:
│ +            p *= 0.65
│ +        rev += v * p
│ +    return rev - 100 * crude - 100 * opex_pbbl
│ +
│ +yA = {"benzyna": 28, "diesel": 26, "jet": 6, "mazut": 32, "lpg": 4, "straty": 4}
│ +yB = {"benzyna": 42, "diesel": 30, "jet": 12, "mazut": 6, "lpg": 5, "straty": 5}
│ +
│ +mA = margin(yA, 22, 250, jet_ok=False)
│ +mB = margin(yB, 55, 250)
│ +print("A destylacja (jet niesertyf.): marza/100bbl=%.0f = %.2f PLN/bbl" % (mA, mA / 100))
│ +print("B pelny lancuch:              marza/100bbl=%.0f = %.2f PLN/bbl" % (mB, mB / 100))
│ +print("B przy kryzysie -70%% (ropa 75): marza/100bbl=%.0f = %.2f PLN/bbl" % (margin(yB, 55, 75), margin(yB, 55, 75) / 100))
│ +print("B przy boomie +100%% (ropa 500): marza/100bbl=%.0f = %.2f PLN/bbl" % (margin(yB, 55, 500), margin(yB, 55, 500) / 100))
│ +
│ +m_mid = mA / 100 * 0.35 + mB / 100 * 0.65
│ +for nazwa, cap, koszt, m in [
│ +    ("mala rozdzielnia", 150, 5_000_000, mA / 100),
│ +    ("srednia rafineria", 400, 35_000_000, m_mid),
│ +    ("duzy kompleks", 1000, 120_000_000, mB / 100),
│ +]:
│ +    h = cap * m
│ +    dzien = h * 24
│ +    print("%s: %d bbl/h, zysk/h=%.0f, zysk/dobe=%.0f, zwrot=%.0f dni" % (nazwa, cap, h, dzien, koszt / dzien))
│ +
│ +chlon = {"benzyna": 8000, "diesel": 12000, "jet": 4000, "mazut": 6000}
│ +print("Chlonnosc region sredni: %d bbl produktow/dobe" % sum(chlon.values()))
│ +print("Duzy kompleks produkuje 24000 bbl/dobe -> %.0f%% chlonnosci jednego regionu" % (24000 / sum(chlon.values()) * 100))
Poniżej gotowy tekst nowej sekcji specyfikacji. Wszystkie liczby zweryfikowałem obliczeniowo (marże, zwroty z inwestycji, zachowanie przy kryzysie −70% i boomie +100%) — spójne z progami zwrotu ~60 dni z §11A.6.

---

# §31. Przerób ropy — Rafinerie, Produkty i Rynki Paliwowe (moduł `Refining`)

Nowy moduł domenowy: `modules/Refining/` (rejestracja zasobów Filament przez własny ServiceProvider, §18.4). Trasy gracza: `/refinery` (dodane do menu `OPERACJE` przez §18.2 — bez zmiany kodu nawigacji) oraz `/market?tab=products`. Wszystkie wartości liczbowe to **wartości startowe `game_balance` do kalibracji** symulatorem headless (§23.10), wersjonowane (§29.5).

## 31.1. Wizja i rola w grze

**Czym jest fabryka w cyklu życia gracza.** Dziś gracz sprzedaje ropę taką, jaką wydobył — po cenie surowca, z poślizgiem cenowym (§19.3) i podatkiem od chomikowania. Rafineria to **druga noga gospodarki gracza**: zamiast sprzedawać ropę za 250 PLN/bbl, przerabia ją i sprzedaje benzynę za 420, olej napędowy za 400, naftę lotniczą za 480 PLN/bbl. To naturalny cel dla gracza średniego i późnego etapu: pierwsze 2–4 tygodnie to odwierty i logistyka, potem rafineria, potem kontrakty paliwowe i pozycja potentata.

**Dlaczego gracz miałby ją zbudować:**
1. **Marża crackowa:** przerób daje 24–68 PLN/bbl marży netto ponad wartość rynkową wsadu (tabele 31.5, 31.6) — przy cenie ropy 250 PLN/bbl to +10% do +27% wartości każdej baryłki.
2. **Ubezpieczenie na wypadek kryzysu (wbudowany mechanizm antycykliczny):** ceny produktów spadają wolniej niż ropa (wzór 31.6.1). Przy kryzysie −70% (§25) marża przerobu **rośnie** do ~112 PLN/bbl, przy boomie +100% **spada** do ~4,5 PLN/bbl. Rafineria amortyzuje oba skrajne stany świata — gracz wydobywczy bez rafinerii jest całkowicie zdany na cykl cen ropy.
3. **Nowe, stabilniejsze źródło przychodu:** kontrakty paliwowe (lotniska, sieci stacji) płacą przewidywalnie, w przeciwieństwie do spotu.

**Co zmienia w rywalizacji.** Pojawia się walka o **sloty środowiskowe w regionach** (31.4.2) i o **chłonność rynków produktowych** (31.6.4) — zasoby ograniczone i dzielone między graczy. Kontrakty paliwowe z karami tworzą realne łańcuchy zależności: gracz A dostarcza rafinerii gracza B ropę (kontrakt §13), B dostarcza paliwo lotnisku — zerwanie u A kaskaduje karami u B (klauzula siły wyższej §21 zyskuje sens). Sabotaże (§7) zyskują nowy, atrakcyjny cel: pożar w rafinerii rywala boli bardziej niż awaria odwiertu.

**Trwały świat i wyczerpywanie złóż.** Złoża się kończą (§9.2, §19.1), a frontier co 90 dni otwiera nowe regiony (§19.4) — koszt przenosin wydobycia rośnie. Rafineria jest **aktywem osiadłym, którego nie trzeba przenosić**: kiedy okoliczne złoża gracza chudną, rafineria może kupować ropę od innych graczy (B2B §13.2) i z frontierów. To prawdziwy endgame: gracz przestaje być kopalnią, staje się **przetwórcą i sprzedawcą paliw** — jego przychód uniezależnia się od stanu własnych złóż. Fabryka nigdy nie jest kasowana: zużywa się, degraduje, ale trwa w świecie jak każdy inny aktyw (zero-wipe).

## 31.2. Budowa fabryki

### 31.2.1. Wymagania wstępne (lejek budowy)
1. **Teren:** region z wolnym **slotem środowiskowym** (31.4.2) i wymaganą minimalną infrastrukturą: działka musi sąsiadować z hubem logistycznym gracza lub systemowym (uzasadnienie fabularne i praktyczne: dojazd cystern, media). Terenów pod rafinerię w regionie jest tyle, ile slotów środowiskowych.
2. **Pozwolenia (oba PRZED rozpoczęciem budowy):**
   - **pozwolenie drugiego stopnia na region** (§11A.2 — istniejące: 500 000 PLN, ~12 h rozpatrzenia, stany `oczekujące → opóźnione → przyznane | odmowa`);
   - **pozwolenie środowiskowe** (nowy rodzaj, 31.4.1): koszt wg klasy fabryki, rozpatrzenie 24 h, wymaga oceny emisji planowanych jednostek.
3. **Projekt:** gracz wybiera klasę fabryki (31.2.2) i startowy zestaw jednostek (31.3.2); projekt zatwierdzany w jednym oknie z pełnym kosztorysem (zakaz ukrytych kosztów — lekcja legacy).
4. **Finansowanie:** środki własne (`PLN_BANK`/`PLN_CASH`) LUB **kredyt budowlany** (§17.1 — zwykły scoring; wypłata w transzach zgodnie z etapami). Wniosek wymaga Dyrektora Finansowego (§10).

### 31.2.2. Klasy fabryk

| Parametr | mała rozdzielnia | średnia rafineria | duży kompleks petrochemiczny |
|---|---|---|---|
| Przepustowość przerobu (bbl wsadu/h) | 150 | 400 | 1 000 |
| Koszt budowy (PLN) | 5 000 000 | 35 000 000 | 120 000 000 |
| Czas budowy (dni) | 7 | 21 | 45 |
| Sloty na jednostki technologiczne | 1 | 3 | 5 |
| Zbiorniki produktowe (4 × bbl) | 4 × 2 000 | 4 × 8 000 | 4 × 25 000 |
| Zbiornik wsadowy (ropa, bbl) | 5 000 | 15 000 | 40 000 |
| Wymagana obsada minimalna (osoby/zmianę) | 2 | 5 | 11 |
| Zużycie bazowe (%/h) | 0,050 | 0,040 | 0,035 |
| Utrzymanie stałe (PLN/h, niezależne od przerobu) | 1 200 | 3 500 | 9 000 |
| Emisja bazowa (pkt/tys. bbl przerobu) | 6 | 10 | 14 |

Czas budowy skracany o `5% × skill_organization` Dyrektora Technicznego (max −25%). Koszt i czas × `mnożnik_odległości_regionu` (§11A.3).

### 31.2.3. Etapy budowy i finansowanie transzowe
Budowa dzieli się na 4 etapy (przygotowanie terenu → konstrukcja → montaż jednostek → rozruch). Płatność w transzach **25/25/25/25%** — każda transza w momencie startu etapu, wpis FTS `LOG_REFINERY_BUILD`. Po zakończeniu budowy fabryka przechodzi **rozruch** (48 h): przepustowość ×0,7, ryzyko incydentu ×1,5 (rozruch to realnie najgroźniejszy moment — tak jest w prawdziwych rafineriach).

**Przerwanie / brak środków:** nieopłacona transza → stan `budowa_wstrzymana`; postęp stoi, koszty stałe terenu 500 PLN/dobę, inflacja materiałowa +10% do nieopłaconej transzy przy wznowieniu. Brak wznowienia przez 60 dni → pozwolenie budowlane wygasa (od nowa: pozwolenie drugiego stopnia), wpłacone transze przepadają w 50% (zwrot z `SYS_TREASURY`, wpis FTS). Budowa finansowana kredytem: brak transzy = `late` jak przy zwykłej racie (§17.1).

### 31.2.4. Rozbudowa
- **Nowy slot jednostki** (średnia +1, duży +2 ponad bazę): 20% kosztu budowy klasy, 7 dni; w trakcie rozbudowy przepustowość ×0,7 (spójne z rozbudową huba §11A.2).
- **Rozbudowa zbiorników:** +50% pojemności wybranego produktu, 8% kosztu budowy, 3 dni.
- **Awans klasy** (mała→średnia→duży): dopłata różnicy kosztów × 1,2, czas = 60% czasu budowy nowej klasy, w tym czasie fabryka nieczynna (stan `remont`). Decyzja architektoniczna: awans to przebudowa, nie magia — bez „dokupienia przepustowości" w locie.

## 31.3. Produkcja

### 31.3.1. Wsad (ropa)
- Źródło: **magazyn główny gracza** (pobór w fazie 2.9 rozliczeń, 31.10.4) lub **zakup na rynku spot** z dostawą do zbiornika wsadowego (§12 — standardowa ścieżka przez hub).
- Ropa w zbiorniku wsadowym to partia w stanie `at_refinery` — objęta bilansem baryłki (§11A.1 pkt 2): `wydobyte = magazyn + transport + bufory + zbiorniki rafinerii + produkty + udokumentowane straty`.
- Ropa kupiona od innych graczy (B2B) trafia do magazynu i dalej do przerobu — rafineria endgame'owa może nie mieć własnych odwiertów.

### 31.3.2. Jednostki technologiczne (ulepszenia)

| Jednostka | Wymagana min. klasa | Koszt (PLN) | Czas instalacji | Efekt |
|---|---|---|---|---|
| Destylacja atmosferyczna | (wbudowana) | — | — | baza wydajności (31.3.3) |
| Kraking katalityczny | średnia | 14 000 000 | 5 dni | mazut −26 pp → benzyna +14, diesel +4, jet +6, LPG +1; OPEX +18 PLN/bbl |
| Hydrooczyszczanie | mała | 9 000 000 | 4 dni | **certyfikacja nafty lotniczej** (bez niej tylko sprzedaż po 65% ceny, brak kontraktów lotniskowych); liczba cetanowa diesla +5; emisje −40%; OPEX +8 PLN/bbl |
| Reforming katalityczny | średnia | 11 000 000 | 4 dni | benzyna klasy premium (RON ≥95, cena +12%); OPEX +7 PLN/bbl |
| Instalacja LPG *(planowana, wz. rurociągów 5–6)* | mała | 5 000 000 | 3 dni | LPG +3 pp, straty −1 pp |

Jednostka to encja z własną kondycją i zużyciem (zużycie = 1,3 × zużycia fabryki — jednostki psują się szybciej niż mury). Awaria jednostki (31.3.8) wyłącza jej efekt, nie całą fabrykę. **Katalizatory:** kraking i reforming zużywają katalizator — licznik `bbl_przetworzone_od_regeneracji`; co 200 000 bbl obowiązkowa regeneracja (zadanie TTS, 48 h postoju jednostki, koszt 400 000 PLN, wpis `REF_CATALYST`). Przeciąganie regeneracji: jednostka ×0,8 wydajności, ryzyko awarii ×1,5 (jawne w interfejsie).

### 31.3.3. Wydajności (frakcje z 100 bbl wsadu)

| Frakcja | sama destylacja | + kraking | + kraking + hydro + reforming |
|---|---|---|---|
| Benzyna | 28 | 42 | 42 (premium) |
| Olej napędowy | 26 | 30 | 30 |
| Nafta lotnicza | 6 (niesertyfikowana) | 12 | 12 (certyfikowana) |
| Mazut | 32 | 6 | 6 |
| LPG / gazy | 4 | 5 | 5 |
| Stratyi | 4 | 5 | 5 |

*Straty: wypalenie technologiczne, ślady w zbiornikach — udokumentowany wpis `product_movements` z `reason_code=PROCESS_LOSS` (bilans musi się uzgadniać co do 0,0001 bbl).

### 31.3.4. Jakość produktów (model prosty)
Trzy cechy, zero chemii:
- **Benzyna:** liczba oktanowa 91 (baza) → 95 (reforming) → do 98 przy `skill_refining` laboranta ≥7. Kontrakty stacji paliw wymagają ≥95.
- **Olej napędowy:** liczba cetanowa 45 → 50 (hydrooczyszczanie).
- **Nafta lotnicza:** `niesertyfikowana` → `certyfikowana` (hydrooczyszczanie + laborant). Niesertyfikowana: sprzedaż NPC po 65% ceny, zakaz kontraktów lotniskowych.
Klasa jakości `standard | premium` per partia (batch) — premium wymaga jednostki + sprawnego laboranta; premia cenowa +10–12%.

### 31.3.5. Czas i stawki przerobu (delta-time)
Przerób jest **ciągły** (jak przepływ rurociągu), rozliczany z Δt w fazie 2.9:

```
przerob_bbl = min(ropa_w_zbiorniku_wsadowym,
                  wolne_miejsce_zbiornikow_produktowych_na_najwęższej_frakcji,
                  przepustowosc × f_kondycji × f_obsady × f_budzetu × (1 − redukcja_strajkowa)
                  × f_wydarzenia × Δt_h)
produkt_i   = przerob_bbl × wydajnosc_i(jednostki, jakość) / 100
```

`f_kondycji` identyczna z hubem (§11A.2): <20% → 0,10; 20–40% → 0,30; ≥40% → kondycja/100. Kolejność mnożników wyłącznie przez `applyModifierStack` (§11A.6) — zakaz rozsiewania mnożników po serwisach.

**Kaskada przepełnienia (analogiczna §11A.1 pkt 4, bez cichej magii):** zbiornik produktu pełny → fabryka przechodzi w `throttled` (przerób ograniczony do tempa zbywalności najwęższej frakcji) → jeśli wąska frakcja jest nieunikniona (np. mazut bez odbioru) → `paused_storage`: przerób staje, ropa zostaje w zbiorniku wsadowym, koszty stałe biegną, powiadomienie `operational`. **Zawór bezpieczeństwa — flara:** gracz może włączyć spalanie nadwyżek (`flaring`): wybrana frakcja jest niszczona (wpis `FLARE_BURN` w `product_movements`), koszt 200 PLN/h, emisje +50%, ale fabryka pracuje. Flara to świadoma, kosztowna decyzja — nie automat.

### 31.3.6. Koszty przerobu (OPEX)

| Składnik | Wartość | Uwagi |
|---|---|---|
| OPEX bazowy destylacji | 22 PLN/bbl wsadu | energia, robocizna operacyjna |
| + kraking | +18 PLN/bbl | tylko gdy jednostka aktywna |
| + hydrooczyszczanie | +8 PLN/bbl | wodór, para |
| + reforming | +7 PLN/bbl | |
| Razem pełny łańcuch | 55 PLN/bbl | |
| Utrzymanie stałe | tabela 31.2.2 | biegnie także przy postoju (×0,2 przy `paused`, wzór jałowy §23.4) |
| Katalizatory | amortyzowane w OPEX + regeneracja 400 000 PLN/200 tys. bbl | zadanie TTS |

OPEX księgowany w fazie 5 (Δt), wpis `REF_OPEX`. Brak środków: jak §11A.6 — przerób staje (zaległość), produkty w zbiornikach i partie w toku NIE przepadają.

### 31.3.7. Zużycie i przeglądy
`wear_gain_per_h = base_wear(klasa) × tryb × wiek_konserwacji × (1 − 0,04 × skill_maintenance) × przyspieszenie_kondycji × Δt_h` z ogranicznikiem `8 × base × max(1, Δt_h)` (wzorzec §11A.2 przeniesiony 1:1). **Przegląd obowiązkowy co 240 h** pracy (duża jednostka przemysłowa, rzadziej niż rurociąg): zadanie TTS, nie wstrzymuje przerobu; spóźniony przegląd → ryzyko incydentu ×1,3 + ukryte wady (−5…−15 pkt skutecznej kondycji do wykrycia). Tryby pracy jak hub: oszczędny ×0,75 / standardowy ×1,0 / **brak trybu turbo dla rafinerii** (decyzja świadoma: przeciążanie instalacji chemicznych to proszenie się o eksplozję — zamiast turbo jest flara i rozbudowa).

### 31.3.8. Incydenty rafineryjne
Ryzyko na sweep — kanoniczny stos §17.4 rozszerzony:

```
risk = base_risk(klasa, jednostki, wear) × (1 − 0,12 × bhp_level) × (2 − procedure_integrity/100)
       × (1 − 0,05 × skill_safety) × (1 − 0,03 × skill_refining_operatora) × budget_bhp_multiplier
       × strike_multiplier × flaring_multiplier × rozruch_multiplier
```

`base_risk`: mała 0,3% / średnia 0,5% / duży 0,8% na godzinę pracy (do kalibracji); `flaring_multiplier` 1,2; `rozruch_multiplier` 1,5 (48 h po budowie/remoncie). Losowanie wyłącznie ziarnem `HMAC(SERVER_SECRET, "refinery:{id}:{due_slot}")` (§23.6), przy długim Δt rzut zagregowany dwumianowy (§23.6).

| Incydent | Klasa (§17.4) | Skutki (startowe, do kalibracji) |
|---|---|---|
| Awaria czujnika / pompy | micro | kondycja −2…−5, bez postoju |
| Awaria jednostki | minor | jednostka wyłączona 6–24 h, jej efekt wydajności znika, przerób na pozostałych jednostkach |
| Skażenie partii | minor | losowa partia w zbiorniku → `zanieczyszczona`: wartość 30% (sprzedaż tylko jako blend mazutowy) lub utylizacja (koszt 50 PLN/bbl) |
| Pożar instalacji | major | jednostka `awaria` + naprawa TTS, utrata 10–30% zawartości jednego zbiornika (udokumentowana `FIRE_LOSS`), kara środowiskowa 500 000 PLN, wiarygodność −4 |
| Eksplozja jednostki | catastrophe | jednostka `uszkodzona` (naprawa TTS lub zewnętrzna 2,5×), fabryka `uszkodzona` do naprawy, utrata do 50% produktów w zbiornikach, skażenie: kara 2–10 mln PLN (`SYS_TREASURY`), wiarygodność −15 (§2), powiadomienie `critical` + e-mail |

Naprawy wyłącznie TTS (§11) lub firma zewnętrzna ×2,5; przy strajku technicznym czas ×1,4. **Zakaz legacy:** eksplozja niszczy wyłącznie produkty w zbiornikach fabryki i jednostki — nigdy magazynu głównego gracza ani cudzych aktywów.

## 31.4. Środowisko i zezwolenia

### 31.4.1. Pozwolenie środowiskowe
Nowy rodzaj pozwolenia (tabela `permits`, typ `environmental`): wymagane przed budową i odnawiane co 180 dni (jak koncesje §19.4 — spójny rytm opłat). Koszt: mała 200 000 / średnia 500 000 / duży 1 200 000 PLN; rozpatrzenie 24 h; odmowa przy przekroczeniu limitu emisji regionu (patrz sloty) — po odmowie 12 h przerwy, jeden wniosek na gracza na region (zasady §11A.2 przeniesione). Brak ważnego pozwolenia = fabryka `zakazana` (przerób 0, koszty stałe biegną).

### 31.4.2. Sloty środowiskowe (anty-monopol, patrz też 31.7.5)
Każdy region ma ograniczoną **pojemność środowiskową** wyrażoną w slotach: region duży 2 sloty / średni 4 / mały-frontier 6 (z generatora świata, §11A.2 — nie globalny wyłącznik). Jeden slot = jedna fabryka dowolnej klasy; limit gracza: **1 fabryka na region**. Slot zajmuje się przy pozwoleniu budowlanym, zwalnia przy rozbiórce (gracz może rozebrać starą fabrykę: odzysk 20% wartości, 14 dni).

### 31.4.3. Emisje i filtry
Fabryka ma `emission_score` (0–100): przyrost `emisja_bazowa × przerob / 1000 × Δt` minus redukcja filtrów, spadek naturalny −2 pkt/dobę przy postoju. Filtry (inwestycje jednorazowe + utrzymanie):

| Filtr | Koszt | Utrzymanie | Redukcja emisji |
|---|---|---|---|
| Elektrofiltr | 2 000 000 | 300 PLN/h | −20% |
| Skruber katalityczny | 5 000 000 | 700 PLN/h | −35% |
| Odzysk gazów flarowych | 3 000 000 | 400 PLN/h | −15% (flara ×0,5 emisji) |

Mnożniki łączą się multiplikatywnie, podłoga ×0,30 (filtry nigdy nie zerują emisji — wzorzec ochrony §11A.7).

### 31.4.4. Inspekcje środowiskowe
Ryzyko inspekcji na dobę: `2% + max(0, (emission_score − limit_regionu) / limit_regionu × 10%)`; `limit_regionu` = 40. Losowanie ziarnem `HMAC(SERVER_SECRET, "inspection:{refinery_id}:{due_slot}")`. Wynik:
- emisja ≤ limit: czysto, +1 wiarygodności raz na 30 dni (nagroda za inwestycje w filtry);
- przekroczenie do 50%: mandat 250 000–1 000 000 PLN (`REF_ECO_FINE` → `SYS_TREASURY`), nakaz redukcji: przerób ×0,7 do zejścia pod limit;
- przekroczenie >50%: mandat 2–5 mln, wiarygodność −3, blokada rozbudowy na 30 dni.
Katastrofa ekologiczna z incydentu (31.3.8) = osobna kara, wiarygodność −15 (§2) — inspekcja jej nie dubluje.

### 31.4.5. Czarny rynek i „lewa produkcja"
- **Blendowanie (oszustwo jakościowe):** sprzedaż benzyny standard jako premium (+30% ceny). Każda taka sprzedaż: `black_market_score` +2, kontrola jakości losowana przy transakcji (ziarno `blend_check:{sale_id}`) — wykrycie: kara 3× wartość partii, wiarygodność −12, blokada kontraktów premium 14 dni (§2 — mechanika śladu czarnorynkowego bez zmian).
- **Bimbarnia (nielegalna mini-instalacja):** 300 000 PLN, 10 bbl/h, bez pozwoleń i slotu; wydajność 60% bazowej, jakość wyłącznie standard, `black_market_score` +1/dobę działania; przy kontroli skarbowej (§2): konfiskata instalacji i produktów, kara 1 mln, wiarygodność −12. Sprzedaż wyłącznie na czarnym rynku (gotówka do `PLN_CASH`, podlega egzekucji §17.2 — schronem nie jest).

## 31.5. Kadry

### 31.5.1. Nowe stanowiska (personel techniczny §3 — jedna ścieżka rekrutacji, spójnie z §11A.2)

| Stanowisko | Wymagane | Rola |
|---|---|---|
| Operator rafinerii | 2 / 4 / 8 (mała/średnia/duży) | przepustowość i ryzyko; `skill_refining` działa z tego stanowiska |
| Inżynier chemik | 1 na każde 2 zaawansowane jednostki (kraking/hydro/reforming) | brak → jednostki zaawansowane wyłączone (efekt nie działa, fabryka pracuje na bazie) |
| Laborant jakości | 1 na fabrykę | klasa premium, certyfikacja nafty, +2 RON na pkt `skill_refining` ≥7 |
| Mechanik utrzymania | współdzielony z pulą TTS (§11) | przeglądy, regeneracja katalizatora |

Brak obsady: przepustowość ×0,70 i ryzyko ×2 — **jawne w interfejsie** (zasada §11A.2). Przypisania z alokacją 0–100%, suma ≤100%, blokada wiersza przy zapisie; pracownik `in_training` nie może być obsadą (§6).

### 31.5.2. Nowy sub-skill i szkolenia
`skill_refining` (Przerób ropy) — szósty sub-skill techniczny obok §6.1, cap 10. Kursy „Przerób ropy I/II/III": koszt 20 000 / 50 000 / 100 000 PLN, czas 24/48/72 h, `pass_rate` 70% (+bonus powtórkowy §6), certyfikat unikalny per poziom. Efekty: +1% przepustowości i −2% ryzyka incydentów za punkt (u operatorów); u laboranta próg premium (31.3.4). Egzamin losowy — Błyskawiczna Certyfikacja (15 GBBL) skraca wyłącznie czas (§22.6).

### 31.5.3. Strajk w rafinerii
Załoga fabryki należy do **działu technicznego** — strajk techniczny (§5.1) zatrzymuje przerób: jednostki przechodzą w bezpieczny `postój` (automatycznie, bez rzutów incydentów w trakcie strajku — strajkujący przestrzegają procedur zatrzymania; incydenty mogłyby eskalować poza limit ofiary §7.1). Skutki:
- produkty w zbiornikach **nie psują się** (benzyna, diesel, mazut są trwałe; psucie = tylko incydent `skażenie partii` — prosta zasada, bez drugiego silnika strat);
- kontrakty paliwowe niedowiezione → kary jak §13.1, chyba że klauzula siły wyższej (§21);
- **rozruch po strajku:** jednorazowy rzut `restart_incident` (micro/minor) przy wznowieniu, jak §23.4 — zimny restart instalacji jest ryzykowny;
- koszty stałe, pensje i utrzymanie filtrów biegną.

### 31.5.4. Kierownictwo
**MVP: bez nowego fotela zarządu.** Nadzór techniczny (przeglądy, incydenty, TTS) — Dyrektor Techniczny; sprzedaż produktów i kontrakty — Dyrektor Finansowy (podpisywanie, §10). „Dyrektor ds. Przerobu" jako opcjonalny szósty fotel — na liście decyzji właściciela (31.12), nie w MVP: nowy fotel to nowa macierz `BoardAccess`, nowe dialogi strajkowe i nowy wymóg obsady zarządu — kosztowne, a wartość gameplayowa pokrywa się z Technicznym.

## 31.6. Sprzedaż i rynki produktowe

### 31.6.1. Ceny produktów (marża crackowa)
Cena produktu = `cena_bazowa_produktu × czynnik_ropy × modyfikatory_wydarzeń × (1 − slippage)`:

```
cena_bazowa: benzyna 420, diesel 400, jet 480, mazut 150, LPG 200 PLN/bbl   (przy ropie 250)
czynnik_ropy = clamp(0,4; 2,0; 0,5 + 0,5 × cena_ropy / 250)
```

**Dlaczego tak (kluczowa decyzja balansu):** czynnik `0,5 + 0,5×ropa/250` sprawia, że ceny produktów podążają za ropą **w połowie**. Efekt zweryfikowany liczbami: kryzys −70% → marża przerobu rośnie do ~112 PLN/bbl (rafinerie w realu tak działają: tania ropa = gruba marża), boom +100% → marża spada do ~4,5 PLN/bbl. Rafineria jest **stabilizatorem antycyklicznym** całej gospodarki gracza i naturalnym przeciwieństwem spekulacji na ropie. Marża nigdy nie bierze się z niczego: pochodzi z popytu NPC na paliwa (31.6.4), a jej sufit wyznacza chłonność — nie wzór.

### 31.6.2. Kupcy NPC i popyt
Cztery rynki produktowe z kupcami NPC (głębokość księgi jak §19.3):
- **Lotniska i linie lotnicze** — nafta lotnicza (wyłącznie certyfikowana do kontraktów), popyt wysoki latem;
- **Sieci stacji paliw** — benzyna (premium wymaga RON ≥95);
- **Firmy transportowe i rolnictwo** — olej napędowy;
- **Przemysł, ciepłownie, żegluga** — mazut i LPG.

Sezonowość przez wydarzenia światowe (§25, nowe typy): `sezon_urlopowy` (jet +30% popytu, 6 tygodni), `zima` (mazut i diesel +20%), `program_infrastrukturalny` (diesel +25%, regionalne), `strajk_lotnisk` (jet −50%), `fala_upałów` (benzyna +15%). Modyfikatory w `payload` wydarzenia, start z `announced_at` ≥24 h (§25.3).

### 31.6.3. Sprzedaż: natychmiastowa i zlecenia limit
Mechanika **1:1 jak rynek ropy** (§12): sprzedaż natychmiastowa ze zbiornika po bieżącej cenie (wypłata na `PLN_BANK`, wpis `REF_PRODUCT_SALE`) oraz zlecenia limit per produkt (`product_market_offers`: 3 aktywne, VIP 15 — spójne z §22.6, wygasanie VIP anulowanie od najmłodszych ze zwrotem rezerwacji). Rezerwacja `reserved_product_bbl` wyłącza wolumen ze wszystkich innych ścieżek — jedna transakcja z `SELECT ... FOR UPDATE` (§12.1). Podwójna sprzedaż tej samej partii technicznie wykluczona.

### 31.6.4. Chłonność rynku (główny bezpiecznik antyinflacyjny)
Każdy region ma **dzienny sufit zbytu NPC** per produkt (odnawiany o 00:00 UTC):

| Produkt | region mały/frontier | region średni | region duży/metropolia |
|---|---|---|---|
| Benzyna | 3 000 | 8 000 | 20 000 |
| Olej napędowy | 4 500 | 12 000 | 28 000 |
| Nafta lotnicza | 1 500 | 4 000 | 10 000 |
| Mazut + LPG | 3 000 | 6 000 + 2 000 | 15 000 + 5 000 |
| **Razem bbl/dobę** | **12 000** | **30 000** | **78 000** |

Sprzedaż w oknie 60-minutowym (skumulowany wolumen WSZYSTKICH graczy regionu, wzór §19.3 przeniesiony): po przekroczeniu 70% dobowej chłonności produktu w regionie — **dyskonto cenowe 3–12%** rosnące z wolumenem, regeneracja liniowo 10% pierwotnego spadku co 10 minut. Powyżej 100% chłonności: sprzedaż możliwa wyłącznie z dyskontem skrajnym (−40%) — rynek „dławi się" nadpodażą. Duży kompleks (24 000 bbl produktów/dobę) nasyca 80% regionu średniego — **nie da się wydrukować pieniędzy jedną fabryką**; druga fabryka w tym samym regionie to już walka o resztki popytu.

### 31.6.5. Kontrakty paliwowe (rozszerzenie §13.1)
Nowy rodzaj kontraktu systemowego: `fuel_contracts` — odbiorca NPC, produkt, wolumen transzy, harmonogram, wymagana jakość:
- lotnisko: nafta certyfikowana, transze tygodniowe, premia +8% do ceny;
- sieć stacji: benzyna RON ≥95, premia +6%;
- firma transportowa: diesel, premia +4%;
- ciepłownia: mazut, premia +3%, kara za dostawę zimą ×1,5 (sezonowość).

Zabezpieczenie i kary **dokładnie jak §13.1**: kaucja 10% wartości (`REF_FUEL_DEPOSIT`), kara 5% niedostarczonej transzy + `contract_reputation` −6, trzy niewykonane transze → przepadek kaucji + blokada przetargów paliwowych 7 dni. Dostęp do lukratywnych kontraktów bramowany `contract_reputation` (progi w `game_balance`). Realizacja transzy = fizyczny odbiór: NPC odbiera z fabryki (MVP) lub dostawa cysterną produktową (v3, 31.6.7).

### 31.6.6. Handel produktami między graczami (v3)
Giełda B2B (§13.2) zyskuje pole `cargo_type` (`crude | benzyna | diesel | jet | mazut | lpg`): ta sama maszyna stanów (`draft → published → accepted → in_delivery → delivered → settled`), ten sam depozyt dwustronny (środki kupującego + produkt sprzedawcy w `locked_in_escrow`), te same timeouty i spory. **Depozyt produktowy działa identycznie jak przy ropie** — escrow nie jest schronem (wygasanie 7 dni). Zniesienie §13.2 o `delivery_receipt` wygrywającym z automatycznym zwrotem przeniesione 1:1. Rafineria gracza A może kupować ropę od gracza B i sprzedawać diesel graczowi C z flotą — pełny łańcuch B2B.

### 31.6.7. Transport produktów (v3; MVP — odbiór u bramy)
- **Cysterny produktowe:** istniejące typy pojazdów (§11A.4) z flagą `cargo_type`; jedna cysterna = jeden produkt (zalanie innego produktu = incydent `skażenie partii` na rozładunku, wartość partii 30%). Pojemności, koszty i ryzyka bez zmian — **nowy typ pojazdu to wiersz w konfiguracji** (§11A.10): cysterna chłodzona LPG (350 bbl, ×1,3 kosztu) jako pierwszy dodatek.
- **Rurociąg produktowy** (nowy rodzaj 7, §11A.3): fabryka → hub/port, 400 bbl/h, budowa 900 000 PLN, 10 h, straty 0,3%, wymaga pozwolenia drugiego stopnia.
- **Terminal morski** (tylko duży kompleks + port w regionie): eksport produktów — chłonność ×2 ceny światowej, ale cena ×0,85 i czas rejsu 4–8 h. Wentyl dla nadprodukcji potentatów, z naturalnym kosztem.
Kursy produktowe rozliczane fazą 2 (ETA, kredyt dwufazowy, `waiting_bay` u kupującego z opłatą postojową po stronie kupującego — §21 przeniesione).

## 31.7. Ekonomia trwałego świata (zero-wipe)

### 31.7.1. Skąd marża i dlaczego jest stabilna
Marża = `(Σ produkt_i × cena_i) − wsad × cena_ropy − OPEX`. Jest stabilna, bo ceny produktów są **zakotwiczone do ceny ropy** czynnikiem 31.6.1 (spread crackowy, nie absolutne ceny) — szok cenowy §25 przesuwa obie strony razem. Przy ropie 250: destylacja daje **24,3 PLN/bbl**, pełny łańcuch **68 PLN/bbl** marży netto (zweryfikowane obliczeniowo).

### 31.7.2. Ujścia pieniądza (sinks — rozszerzenie §19.1)
Budowa i rozbudowa (5–120 mln), jednostki (5–14 mln), filtry (2–5 mln), OPEX przerobu (22–55 PLN/bbl), regeneracja katalizatorów, pozwolenie środowiskowe co 180 dni, mandaty ekologiczne, kaucje kontraktów paliwowych, pensje specjalistów (inżynier chemik to najdroższe stanowisko techniczne w grze — widełki +40% nad operatorem huba), podatek majątkowy od wartości fabryki (31.7.4). Rafineria jest **największym sinkiem endgame'u** — to jej główna rola makroekonomiczna: konsumuje kapitał zgromadzony z wydobycia.

### 31.7.3. Zwrot z inwestycji (przy pełnym zbycie, ropa 250, bez zdarzeń)

| Klasa | Marża | Zysk/dobę | Zwrot nakładu |
|---|---|---|---|
| Mała rozdzielnia (destylacja + hydro) | 24,3 PLN/bbl | ~88 000 PLN | **~57 dni** |
| Średnia rafineria (destylacja + kraking + hydro + reforming) | ~53 PLN/bbl | ~506 000 PLN | **~69 dni** |
| Duży kompleks (pełny łańcuch) | 68 PLN/bbl | ~1 632 000 PLN | **~74 dni** |

Spójne z progiem ~60 dni z logistyki (§11A.6) — inwestycje infrastrukturalne zwracają się w tym samym rytmie. **Zastrzeżenie kalibracyjne:** tabela zakłada sprzedaż po cenie bazowej; przy nasyceniu chłonności dyskonta (31.6.4) realny zwrot dużej fabryki to ~90–110 dni — i tak jest to celowo najdłuższa inwestycja w grze.

### 31.7.4. Wycena do majątku netto
Do wzorca wyceny aktywów (§17.3, §19.1, §22.6) dochodzą składniki:
```
wartosc_fabryki = koszt_budowy × (0,4 + 0,6 × kondycja%) × mnoznik_regionu
                + Σ jednostki: koszt_jednostki × (0,4 + 0,6 × kondycja_jednostki%) × 0,8
                + filtry × 0,5
zapasy_produktow = Σ zbiornik_i × cena_produktu_i × 0,7
```
(wzorzec przejęcia jak hub §11A.2). Podatek majątkowy: fabryka i jednostki wchodzą do podstawy jako aktywa trwałe (70% wyceny, §19.1); zapasy produktów — jak ropa w magazynie. Egzekucja komornicza: **Faza 2 sprzedaje także produkty ze zbiorników po 70% ceny** (wyłączone `locked_in_escrow` i `reserved`); **Faza 3 licytuje fabryki** obok odwiertów (cena wywoławcza 60% wyceny; załoga → `unassigned`; aktywne kontrakty paliwowe przechodzą na kupującego z kaucją rozliczoną na rzecz sprzedającego-dłużnika — brak osieroconych zobowiązań, zasada §18.4).

### 31.7.5. Ryzyko monopolu — cztery niezależne hamulce
1. **Sloty środowiskowe regionów** (31.4.2): fizyczny limit liczby fabryk; 1 fabryka gracza na region.
2. **Limit koncentracji:** łączna przepustowość przerobu jednego gracza (i jednego kartelu — rozszerzenie limitu 40% z §26) ≤ **30% dobowego wydobycia serwera**; powyżej: nowe pozwolenia środowiskowe odrzucane z jawnym powodem.
3. **Chłonność + slippage** (31.6.4): nawet bez limitów prawnych nadpodaż dławi cenę — monopolista sam sobie psuje marżę.
4. **Frontiery** (§19.4): nowe regiony startują z chłonnością i slotami, ale bez fabryk — przewaga pierwszeństwa jest, ale nie jest nieodwracalna; przenośne aktywa (cysterny, kontrakty, kadra) pozwalają nowym wejść w łańcuch dostaw potentata.

## 31.8. Granica monetyzacji (§22.6)

**MOŻNA za GBBL (kosmetyka i wygoda):**
- skórki i iluminacja fabryki, złoty herb na kominie, nazwa własna fabryki z rezerwacją (kosmetyka);
- „Radar Przerobu PRO": zaawansowane wykresy spreadu crackowego, marży per jednostka, alerty e-mail o ruchach cen produktów — **wyłącznie analityka** (rozszerzenie VIP, spójne z Radarem Analitycznym §22.3);
- VIP: 15 slotów zleceń limit na produkty (zamiast 3) i automatyczna pętla cystern produktowych (v3) — z tą samą walidacją stanów fizycznych co §22.6;
- Błyskawiczna Certyfikacja kursów przerobu (skraca czas, egzamin losowy — zasada bez zmian).

**NIE MOŻNA (przewagi produkcyjne — zakaz wiążący):**
- wydajności, przepustowości, czasu budowy ani instalacji jednostek (żadnego „przyspiesz rozruch za 40 GBBL");
- gwarancji jakości/certyfikacji nafty (certyfikat zdobywa się instalacją i laborantem);
- odporności na incydenty i inspekcje ( Prywatna Straż §22.3 działa przy pożarze rafinerii na tych samych zasadach co przy blowoucie — gasi AKTYWNY incydent, nie cofa skutków);
- anulowania mandatów środowiskowych, kupowania chłonności rynku, kupowania slotów środowiskowych;
- Kroplówka w `recovery_mode` i od Fazy 2 egzekucji — bez zmian (§24.3).

## 31.9. Panel administratora (§18, uprawnienia §18.3)

Nowa grupa nawigacji **„8. PRZERÓB"** (auto-discovery z `modules/Refining/src/Filament/`):
1. **Rafinerie (podgląd 360°):** lista per gracz — klasa, kondycja, jednostki z kondycjami i licznikami katalizatora, zbiorniki (per produkt: wolumen, rezerwacje, escrow), przerób z 24 h, emisje, filtry, obsada; zakładka w `PlayerResource` (rozszerzenie §18.1).
2. **Rynki produktowe:** ceny per produkt/region, wykorzystanie chłonności dobowej, okna slippage, wolumeny sprzedaży — telemetria monopolowa (flaga przy >30% koncentracji, 31.7.5).
3. **Kontrakty paliwowe:** transze, kaucje, kary, reputacja kontraktowa.
4. **Inspekcje środowiskowe:** rejestr inspekcji, mandaty, nakazy redukcji.
5. **Akcje GM** (każda w `gm_action_log`: kto, co, stan przed/po, `sweep_no`):
   - wymuszenie incydentu testowego na fabryce/jednostce — `admin` + `gm_support`;
   - **korekta partii produktów wyłącznie wpisem `GM_ADJUST` przez `product_movements`** z konta systemowego — `admin` (bilans bbl musi się uzgadniać; zakaz cichej zmiany liczb, zasada §11A.9);
   - wyłączenie fabryki (np. przy braku pozwolenia) — `admin`, jawne, bez podawania powodu graczom (§18.3 — wsparcie powód podaje);
   - edycja `game_balance` przerobu (wydajności, mnożniki cen, chłonność, OPEX, kary) — wyłącznie `admin`, **zasada czterech oczu**, wersjonowanie §29.5 (przeroby w toku na starej wersji, nowe na nowej).
6. **Telemetria (§29.3):** faucets/sinks modułu (przychód ze sprzedaży produktów vs OPEX+podatki+kary), czas fazy 2.9, wykorzystanie chłonności per region, wykrywalność blendowania.

## 31.10. Technika

### 31.10.1. Maszyny stanów

**Fabryka (`refineries`):**
```
planowana → pozwolona → budowa (4 transze) → rozruch (48h) → aktywna
aktywna ⇄ zdegradowana (40–70) ⇄ krytyczna (20–40) → uszkodzona (0 | incydent | eksplozja)
aktywna ⇄ throttled (zbiornik pełny) ⇄ paused_storage ; aktywna → postój (gracz; OPEX stały ×0,2)
aktywna → rozbudowa/remont/awans klasy → rozruch → aktywna
aktywna..uszkodzona → zakazana (brak pozwolenia środowiskowego / decyzja admina) — wznowienie wyłącznie po usunięciu przyczyny
dowolna robocza → wstrzymana (urlop §19.2 / zajęcie §17.2 / blokada §30.4 / dormant §19.2) → rozruch → aktywna
uszkodzona → naprawa (TTS | zewnętrzna 2,5×) → rozruch → aktywna
ZAKAZ: skrótu wstrzymana/uszkodzona/zakazana → aktywna (testowane, wzór §11A.3)
```

**Jednostka (`refinery_units`):** `zamówiona → instalacja → aktywna ⇄ zużyta → awaria → naprawa → rozruch_jednostki (12h) → aktywna`; `aktywna → postój` (gracz); katalizator: licznik + `wymaga_regeneracji` (blokuje efekt, nie przepustowość całej fabryki).

**Partia produktu (`product_batches`):** `wyprodukowana → w_zbiorniku → zarezerwowana (spot | limit | kontrakt | b2b) → w_transporcie (v3) → dostarczona | sprzedana | utracona (incydent, flara, utylizacja) | zanieczyszczona | skonfiskowana (czarny rynek)`. Partie zachowują właściciela i jakość przez całą drogę.

**Kontrakt paliwowy (`fuel_contracts`):** `draft → published → accepted → in_delivery → delivered → settled` + `cancelled / expired / disputed → resolved | refunded` — maszyna §13.2 z transzami (każda transza: własny protokół, własne rozliczenie kaucji, częściowe `settled`).

### 31.10.2. Wzory delta-time (podsumowanie)
- przerób i wydajności: 31.3.5 (plateau §23.4 — punkty nasycenia: zbiornik wsadowy pusty, zbiornik produktowy pełny, chłonność dobowa);
- OPEX: `stawka_pln_na_bbl × przerob_bbl + utrzymanie_stałe × Δt_h` (faza 5);
- zużycie: 31.3.7 (ogranicznik 8× zachowany);
- emisje: 31.4.3; przyrost `emission_score` z Δt, spadek przy postoju;
- cap DR 7 dni i `dormant` (§23.5, §19.2): fabryka w `dormant` — przerób ustał, jednostki w `postój`, filtry wyłączone (emisje stoją), zobowiązania narastają jako dług;
- urlop (§19.2): twardy warunek rozszerzony — **brak aktywnej fabryki w stanie `uszkodzona` z incydentem major+** (analogia do blowoutu); podczas urlopu fabryka w `wstrzymana`, bez incydentów.

### 31.10.3. Klucze ziarna losowego (§23.6)
```
incydent fabryki:   HMAC(SERVER_SECRET, "refinery:{refinery_id}:{due_slot}")
awaria jednostki:   HMAC(SERVER_SECRET, "unit:{unit_id}:{due_slot}")
inspekcja:          HMAC(SERVER_SECRET, "inspection:{refinery_id}:{due_slot_doba}")
skażenie partii:    HMAC(SERVER_SECRET, "batch:{batch_id}:{due_slot}")
kontrola blendu:    HMAC(SERVER_SECRET, "blend_check:{sale_id}")
restart po strajku: HMAC(SERVER_SECRET, "refinery_restart:{refinery_id}:{due_slot}")
```
`due_slot` = zaokrąglenie w dół do 5 min (inspekcja: do doby); anty-reroll: slot rozliczony nie losuje ponownie; agregacja długiego Δt — rzut dwumianowy `p_agg = 1 − (1 − p_per_h)^n` z adnotacją `aggregated(n)`.

### 31.10.4. Integracja z fazami rozliczeń (§23.3)
Kolejność kanoniczna bez zmian; przerób wchodzi jako **nowa podfaza 2.9** (po 2.7 kredytu do magazynu, przed 2.8 zapisu incydentów):
- **2.9** pobór ropy ze zbiornika wsadowego/magazynu → przerób jednostek → kredyt produktów do zbiorników (dwufazowy, klucz `(sweep_no, refinery_id)` — wzór §11A.8) → kaskada przepełnienia (31.3.5);
- **3** (sweeper, globalnie): rynki produktowe — ceny 31.6.1, matching limitów, slippage i chłonność per region;
- **4**: kontrakty paliwowe (transze zapadłe, kary, kaucje);
- **5**: OPEX przerobu, utrzymanie filtrów, opłaty środowiskowe;
- **7**: rzuty incydentów rafineryjnych (JEDNO źródło rzutu na encję — nie dublować z 2.9);
- **8**: decay emisji przy postoju, liczniki katalizatorów, materializacja inspekcji, wygasanie pozwoleń.
Zasada §23.3 zachowana: 2.9 zmienia stan fizyczny, 5 księguje, 7–8 modyfikują wejścia kolejnych sweepów.

### 31.10.5. Wpisy FTS — nowe kody przyczyn (§24.2)
`LOG_REFINERY_BUILD` (transze budowy), `LOG_REFINERY_UNIT` (zakup/instalacja), `LOG_REFINERY_UPGRADE` (rozbudowa/awans), `REF_OPEX` (przerób + utrzymanie + filtry), `REF_CATALYST` (regeneracja), `REF_ENV_PERMIT` (pozwolenie środowiskowe), `REF_ECO_FINE` (mandaty → `SYS_TREASURY`), `REF_PRODUCT_SALE` (spot NPC), `REF_PRODUCT_LIMIT` (realizacja zlecenia limit), `REF_FUEL_DEPOSIT` / `REF_FUEL_PAYOUT` / `REF_FUEL_PENALTY` (kontrakty paliwowe), `REF_PRODUCT_B2B` (escrow produktowy), `REF_FLARE` (koszt flary), `REF_BLACK_SALE` (bimbarnia/blend → `PLN_CASH`), `GM_ADJUST` (korekty wyłącznie przez `product_movements`). Każdy wpis: double-entry, `idempotency_key`, `sweep_no`, `balance_version` (§29.5).

### 31.10.6. Tabele danych (nazwy, kluczowe pola)

| Tabela | Kluczowe pola |
|---|---|
| `refineries` | `id`, `player_id`, `region`, `class` (small/medium/large), `status` (maszyna 31.10.1), `condition DECIMAL(9,5)`, `wear_level`, `capacity_bbl_h`, `env_slot_id`, `last_calc_at`, `balance_version` |
| `refinery_constructions` | `refinery_id`, `stage` (1–4), `tranche_paid_grosze`, `eta`, `status` |
| `refinery_units` | `refinery_id`, `type` (cracking/hydro/reforming/lpg), `status`, `condition`, `catalyst_bbl_counter`, `installed_at` |
| `product_tanks` | `refinery_id`, `product`, `capacity_bbl DECIMAL(16,4)`, `used_bbl`, `reserved_bbl`, `locked_in_escrow_bbl` |
| `product_batches` | `tank_id`, `product`, `quality_class`, `ron/cetan`, `certified` (jet), `volume DECIMAL(16,4)`, `status` (maszyna 31.10.1), `owner_player_id` |
| `product_movements` | odpowiednik `oil_movements`: `źródło, cel, cargo_type, wolumen, reason_code, sweep_no, idempotency_key` — bilans baryłki rozszerzony na produkty |
| `product_market_offers` | jak `market_offers` + `product`, `region` |
| `product_price_history` | `product`, `region`, `cena`, `slippage`, `chlonnosc_used`, ts |
| `fuel_contracts` / `fuel_contract_tranches` | odbiorca NPC, `product`, `quality_req`, wolumen, harmonogram, kaucja, status; transza: `due_at`, `delivered_bbl`, `penalty` |
| `env_permits` / `env_inspections` / `emission_filters` | pozwolenie: region, slot, `expires_at`; inspekcja: wynik, mandat; filtry: typ, `active` |
| `refinery_config` (`game_balance`) | wydajności per zestaw jednostek, mnożniki cen, chłonności, OPEX, ryzyka, kary — wszystko do kalibracji |

Precyzja: wolumeny `DECIMAL(16,4)`, kondycja `DECIMAL(9,5)`, pieniądze `BIGINT` grosze — konwencje §11A.1 pkt 5 i §24.4 bez odstępstw.

### 31.10.7. Testy odbiorcze (rozszerzenie §11A.11, blokują wydanie)
(R1) bilans przerobu: `wsad = Σ produkty + straty + flara` co do 0,0001 bbl przez 1000 sweepów; (R2) restart między stanem `kredytowany` a kredytem zbiornika — brak podwójnych/utraconych produktów; (R3) zbiornik pełny → `throttled` → `paused_storage` → kaskada do wstrzymania poboru z magazynu, ropa w magazynie nienaruszona; (R4) strajk techniczny: postój bez rzutów incydentów, rozruch po strajku — dokładnie jeden rzut `restart_incident`; (R5) niesertyfikowany jet odrzucony przez kontrakt lotniskowy (walidacja jakości przy `accepted`); (R6) potrójna sprzedaż tej samej partii (spot + limit + kontrakt) — rezerwacja blokuje; (R7) agregacja ryzyka Δt=12 h i Δt>7 dni (cap + `dormant`, fabryka bez incydentów); (R8) slippage produktowy: skumulowane okno 60 min, dzielenie zleceń nie omija dyskonta; chłonność odnawia się o 00:00 UTC; (R9) awaria jednostki w trakcie sweepa — wydajność liczona segmentowo (przed/po awarii), nie średnio; (R10) kryzys −70%: marża dodatnia (~112 PLN/bbl), boom +100%: ~4,5 PLN/bbl — golden-master wzoru 31.6.1; (R11) podatek majątkowy obejmuje fabrykę i zapasy produktów (podstawa §19.1); (R12) egzekucja Faza 3: licytacja fabryki z aktywnym kontraktem paliwowym — kontrakt przechodzi, kaucja rozliczona, brak osieroconych stanów; (R13) inspekcja przy emisji ≤ limit i >50% limitu — oba wyniki, mandaty w FTS; (R14) równoległe rozliczenie leniwe + sweeper tej samej fabryki — dokładnie jedno rozliczenie (CAS §23.2).

## 31.11. Etapowanie

**MVP (v1 — „pierwsza ropa przerobiona"):** średnia rafineria (jedna klasa), destylacja + kraking, 4 produkty + LPG jako strata skonsolidowana z mazutem, sprzedaż NPC spot z chłonnością i slippage, zlecenia limit (3/15 VIP), pozwolenie środowiskowe + inspekcje + filtry, stanowiska (operator, inżynier chemik) + `skill_refining` + kursy I–III, incydenty (awaria jednostki, pożar, eksplozja, skażenie), budowa transzowa z rozruchem, faza 2.9, kody FTS, panel admina (podgląd + wymuszenie incydentu + balans), testy R1–R4, R6–R8, R10, R14. **Kryterium akceptacji MVP:** symulator headless — 90 dni świata, 20 graczy z rafineriami: cena ropy stabilna ±10%, inflacja sumy `PLN_BANK` graczy < +5%/miesiąc po uwzględnieniu sinków.

**v2 (jakość i kontrakty):** hydrooczyszczanie + reforming, klasy jakości i certyfikacja nafty, kontrakty paliwowe z kaucjami, mała i duża klasa fabryki, rozbudowa i awans klasy, katalizatory, laborant, sezonowość §25 (5 nowych typów wydarzeń), flara, blendowanie czarnorynkowe, testy R5, R9, R11, R13.

**v3 (rynek między graczami i logistyka produktów):** B2B produktowe (`cargo_type` w escrow §13.2), cysterny produktowe, rurociąg produktowy (rodzaj 7), terminal morski i eksport, bimbarnia, egzekucja i aukcje fabryk (Faza 3 rozszerzona), testy R12 + transportowe (odpowiedniki T9–T11 dla produktów).

**Ryzyka implementacji:**
1. **Inflacja z faucetu produktowego** — największe ryzyko modułu; mitygacja: chłonność + slippage + czynnik antycykliczny + sinki (31.7.2); obowiązkowa kalibracja symulatorem PRZED uruchomieniem na świecie produkcyjnym.
2. **Złożoność plateau z wieloma zbiornikami** (5 frakcji × nasycenie niezależne) — segmentowanie §23.4 musi uwzględniać najwęższą frakcję; rekomendacja: najpierw golden-master na papierze, potem kod.
3. **Rozjazd bilansu baryłki** po dodaniu produktów — `product_movements` od pierwszego dnia, rekoncyliacja §29.2 rozszerzona o produkty (alarm przy rozjeździe).
4. **Kolizja mnożników z legacy** — wyłącznie `applyModifierStack` (§11A.6), code review pod kątem „zużycia do kwadratu".
5. **Ukryte kary obsadowe** — wszystkie mnożniki jawne w interfejsie (reguła §11A.2).

## 31.12. Lista decyzji do podjęcia przez właściciela gry

1. **Numeracja i nazwa modułu:** przyjąć §31 i moduł `Refining` z trasą `/refinery`? (alternatywa: wcielenie do Market/Logistics — odradzam, to pełna domena).
2. **Nowy fotel zarządu „Dyrektor ds. Przerobu"** w v2/v3 — czy pozostawić nadzór przy Dyrektorze Technicznym (rekomendacja MVP: bez nowego fotela)?
3. **Chłonność dobowa regionów** (tabela 31.6.4) — czy region duży ma mieścić sprzedaż jednego dużego kompleksu (obecnie: tak, 78 000 vs 24 000 bbl/dobę — zapas na 3 kompleksy w regionie)?
4. **Czynnik antycykliczny cen produktów** `0,5 + 0,5 × ropa/250` — akceptacja modelu, w którym rafineria zarabia NAJLEPIEJ w kryzysie ropy (rekomendacja: tak — to bezpiecznik gospodarki), czy wariant neutralny (produkty w 100% podążają za ropą, marża stała PLN/bbl)?
5. **Limit koncentracji 30%** dobowego wydobycia serwera na gracza/kartel — wartość i czy limit ma być jawny dla graczy (rekomendacja: jawny, w interfejsie pozwolenia).
6. **Flara** jako mechanika — czy dopuszczamy niszczenie produktów (realistyczne, uczy zarządzania frakcjami), czy wymuszamy wyłącznie sprzedaż z dyskontem?
7. **Produkty niepsujące się** (poza incydentem skażenia) — akceptacja uproszczenia, czy wprowadzić powolną degradację benzyny (większy realizm, drugi silnik strat — odradzam w MVP)?
8. **Egzekucja i bankructwo:** czy Faza 3 komornika licytuje fabryki od razu w v1 (rekomendacja: v3, do tego czasu fabryka bankruta w `dormant` i na aukcję §19.4 po 90 dniach)?
9. **Bimbarnia (nielegalna mini-instalacja)** — w v3 czy wcale? (ryzyko: trywializacja czarnego rynku vs ciekawa mechanika dla graczy bez slotu środowiskowego).
10. **Wartości startowe `game_balance`** (wszystkie tabele oznaczone „do kalibracji") — zatwierdzenie zakresów przed sesją kalibracyjną symulatorem; w szczególności OPEX 22–55 PLN/bbl i zwroty 57–74 dni.
11. **Czy kontrakty paliwowe NPC konkurują o ten sam wolumen chłonności co spot** (rekomendacja: NIE — kontrakty to wydzielona pula 30% chłonności regionu, nagroda za reputację kontraktową), czy tak (wtedy kontrakt wypiera sprzedaż spot)?
12. **Awans klasy fabryki** (mała→średnia→duży) — utrzymać w v2 (rekomendacja: tak, z 60% czasu budowy i przestojem), czy wymuszać budowę nowej fabryki w nowym slocie?

---

Uwaga końcowa: wszystkie kwoty, wydajności i czasy oznaczone jako startowe przeszły weryfikację arytmetyczną (marże 24,3/68/112/4,5 PLN/bbl oraz zwroty 57/69/74 dni liczone dla ropy 250 PLN/bbl i pełnego zbytu po cenach bazowych) — ale przed wdrożeniem wymagają kalibracji symulatorem headless (§23.10), zwłaszcza tabela chłonności, bo to ona decyduje o realnej, a nie teoretycznej stopie zwrotu.
