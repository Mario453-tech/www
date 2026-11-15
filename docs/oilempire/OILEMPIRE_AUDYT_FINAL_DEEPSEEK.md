[KRYT] §27.1a vs §9.5 — OPEX 12 vs 30 PLN/bbl. 6 720 × (30−12) = +120 960 PLN niezdefiniowanego kosztu → budżet tygodnia przekroczony o ~121k. Poprawka: użyć 30 PLN/bbl z §9.5, przeliczyć tabelę: 6 720×30=201 600, suma zobowiązań ~3 845 000 + (201 600−80 640) = ~3 966 000 < 4 000 000 (bufor ~34k).

[KRYT] §27.1a vs §7.3 — „Koncesja L1 400 000 PLN" nie istnieje. §7.3 definiuje P1=150 000 i P2=500 000. 400 000 nie pasuje do żadnego. Poprawka: P1=150 000 PLN; budżet zyskuje 250k → łańcuch własny = 1 990 000 zamiast 2 240 000.

[KRYT] §27.1a — „stały 300/h" bez definicji. Utrzymanie rury polowej=120/h (§11A.3), hub mały=1 800/h (§11A.2). 300 nie jest sumą ani składową żadnej znanej wartości. Poprawka: zastąpić sumą: (rura 120 + hub 1 800) × 168h = 322 560 PLN za tydzień.

[KRYT] §27.1a — „bufor huba wstępnie wypełniony 800 bbl" — nie wskazano huba (gracz nie ma własnego). Jeśli NPC, brak opłaty za miejsce w budżecie. Pierwszy kurs (500 bbl) wymaga 500 w buforze + cysterna 500 = hub musi mieć min. 500 bbl i wolną zatokę. Poprawka: wskazać hub systemowy, dodać opłatę za miejsce (11A.2).

[KRYT] §4.3 — zapętlenie: morale_dept = 0.7×śr(dept) + 0.3×śr(firma), a śr(firma) = średnia(morale_dept wszystkich działów). Równanie uwikłane — nie da się policzyć w jednym przebiegu bez iteracji. Poprawka: śr(firma) = średnia z poprzedniej doby (lag-1, snapshot przed sweepem).

[KRYT] §22.6.1 vs §24.3 — sprzeczność źródła drip_30d: §22.6.1 każe sumować FTS z `reason_code=DRIP_IN`, ale §24.3 mówi „rozliczenie wyłącznie przez księgę konwersji GBBL — NIE pojedynczym wpisem". Wpis FTS i księga konwersji to dwa różne byty — nie można sumować z obu. Poprawka: zdefiniować DRIP_IN jako wpis w księdze konwersji GBBL, nie w FTS; indeks na `gbbl_conversions(to_account, conversion_date_utc)`.

[KRYT] §13.2 vs §17.2 — cel zajęcia 50%: §13.2 mówi „50% przechodzi na SYS_TREASURY", §17.2 mówi „na poczet długu". Dług może być wobec banku (kredyt) lub skarbu (zobowiązanie podatkowe) — nie zawsze SYS_TREASURY. Poprawka: `B2B_SETTLE_GARNISH` → `PLN_ESCROW` wierzyciela (bank/SYS_TAX), nie SYS_TREASURY; kierunek zależy od typu długu.

[KRYT] §31.2.3 — REF_EPC_WARRANTY_REPAIR: tabela FTS mówi „jeśli gra nie prowadzi takiego konta... nie tworzy się tego reason_code". Kod jednocześnie istnieje w specyfikacji i ma nie istnieć. Poprawka: usunąć REF_EPC_WARRANTY_REPAIR z listy FTS; używać systemowego rejestru kosztów (bez przelewu).

[IST] §7.3 — `flaga_szarej_strefy`: użyta w clamp() szans P1 i P2, nigdzie niezdefiniowana (0/1? próg? powiązanie z black_market_score?). Poprawka: `flaga = 1` gdy `black_market_score ≥ 30` LUB aktywny kontrakt EPC archetyp F; w przeciwnym razie 0.

[IST] §7.3 — `incydent_środowiskowy_180d`: użyty w szansach P1/P2, brak definicji (tylko surface_spill? blowout też? wycieki rur?). Poprawka: zdefiniować jako `COUNT(incidents WHERE class IN ('major','catastrophe') AND type IN ('surface_spill','blowout') AND created_at ≥ NOW() − 180 dni)`.

[IST] §19.2 — jawna luka: „pełny algorytm wyceny majątku netto do doprecyzowania przed implementacją". Programista nie wie, jak wycenić odwierty, huby, rurociągi, zapasy na potrzeby tarczy 10M. Poprawka: podać wzór — `net_worth = PLN_CASH + PLN_BANK + własne PLN_ESCROW + 0.7×Σ(CAPEX_aktywa×(1−wear/100)) + 0.9×VWAP×Σropa`.

[IST] §27.1a — zasób odwiertu 7 000 bbl przy 40 bbl/h = 175h (~7.3 doby). W pierwszym tygodniu (168h) wydobycie = 6 720 bbl — odwiert NIE wyczerpuje się. Ale §27.1a mówi „wyczerpanie po ~175h (~7,3 doby)", a jednocześnie liczy OPEX za 168h (6 720 bbl). Brak scenariusza na dobę 8. Poprawka: dodać uwagę, że od 8. doby OPEX=0 (status depleted), i że 7 000 / 40 = dokładnie 175h.

[IST] §31.5 — Klasa I a Wariant B: nigdzie nie zabroniono Klasy I montażu jednostek katalitycznych (catalytic_cracker, hydrotreater). Jeśli może, marża rośnie z ~101 do >200 PLN/bbl, zwrot CAPEX z 106 do ~50 dni — przełamanie progresji. Poprawka: dodać regułę: Klasa I może mieć tylko `distillation_column`; Klasy II/III pełny zestaw jednostek.

[IST] §13.2 — jakość partii B2B: „Jakość partii deterministyczna 0–100 (progi 70/85/92) i JAWNA w ofercie". Dotyczy to produktów rafinerii (benzyna, diesel, Jet A-1), ale B2B obejmuje też surową ropę, która nie ma „jakości". Poprawka: dodać pole `quality_score NULL` w B2B; dla ropy surowej = NULL i reklamacja jakościowa nie ma zastosowania.

[IST] §31.2 — dozór budowy „5% godzinowego OPEX": nie wskazano którego (stały 2 400/h, zmienny 22/bbl/h, suma?). Przy braku produkcji OPEX zmienny=0, więc 5%×2 400=120/h, ale to domysł. Poprawka: „5% stałego OPEX (fixed_opex_per_h)".

[IST] §7.2 — „referencyjny spot 24h" do wyceny kursu dla eskorty: brak definicji. Czy VWAP z §19.1.1? Prosta średnia z price_history? Ostatni kurs? Poprawka: jawnie wskazać VWAP z ostatnich 24h (ten sam co w §19.1.1).

[IST] §31.2.3 — „zobowiązanie 72 h" przy braku środków na dozór: odwołuje się do reguły zobowiązań, ale §17.1 dotyczy tylko kredytów, nie zobowiązań operacyjnych. §24.5 pkt 5 wzmiankuje zobowiązanie 72h → §17.1, ale tam nie ma mechanizmu zobowiązań pozakredytowych. Poprawka: dodać ogólny mechanizm `obligations` (dług operacyjny, 72h → windykacja §17.2).

[IST] §4.3 — jednostka `hr_bonus_90d`: „40×hr_bonus_90d" — czy to liczba premii, suma kwot, flaga? Niezdefiniowane. Poprawka: `hr_bonus_90d` = liczba przyznanych premii uznaniowych w ostatnich 90 dniach (INT, 0..N).

[IST] §4.3 — reguła odradzania kar: „odrzucone żądanie −20 (odradza +4/dzień)". Czy +4/dzień dodaje się do morale? Czy zmniejsza karę (czyli po 5 dniach kara 0)? Niezdefiniowany mechanizm. Poprawka: `kara_aktywna(t) = max(0, 20 − 4×dni_od_zdarzenia)`, odejmowana od morale_target.

[IST] §11A.7 vs §7.2 — wpływ budżetu BHP: §7.2 mówi „budżet high → −10% ceny modułów, low → +15%", ale §11A.7 (cennik ochrony) nie wspomina o tym mnożniku. Programista nie wie, gdzie stosować. Poprawka: w §11A.7 dodać kolumnę „mnożnik BHP" lub referencję do §7.2.

[IST] §23.6 vs §31.2.3 — różne nazwy entity w seed RNG: §23.6 używa `{entity}`, §31.2.3 używa `{encja}`. Ten sam koncept, dwie nazwy — generator seed musi wiedzieć którą. Poprawka: ujednolicić do `{entity}` w całym dokumencie.

[IST] §31.2.3 — „Opóźnienie dostawy, jeśli zostanie włączone w game_balance" — część parametrów EPC jest opcjonalna (włączona/wyłączona w game_balance), ale kontrakt jest podpisywany z konkretnymi warunkami. Gracz musi wiedzieć przed podpisem, czy opóźnienia działają. Poprawka: wszystkie zdarzenia EPC (katastrofa, opóźnienie, kontrola F, strajk podwykonawcy) muszą być deterministycznie włączone dla danej wersji balansu; stan „jeśli włączone" jest niedopuszczalny przy podpisie.

[IST] §19.2 — Tarcza Startowa: „do momentu osiągnięcia majątku netto 10 000 000 PLN liczonego Z WYŁĄCZENIEM wartości pakietu startowego". Ale §1 nie precyzuje czy „magazyn 20 000 bbl" to pojemność czy ropa. Jeśli ropa: 20 000 × 250 = 5 000 000 PLN — to 50% progu tarczy. Poprawka: magazyn startowy = pojemność 20 000 bbl (stan 0 bbl); wartość 0 w majątku netto.

[DROB] §31.5 — kolumna „Koszty (zm. + stałe)" dla Klasy I: 2.6 + 2.4 = 5.0, ale 120 × 22 / 1000 = 2.64. Zaokrąglono 2.64→2.6 bez zaznaczenia. Poprawka: użyć 2.64 dla spójności arytmetycznej.

[DROB] §31.5 — „Bilans ucieczki: oszczędność 0,008%/dzień". 0.08% × 10% = 0.008 punktu procentowego stawki, nie 0.008% (0.008% z 0.08% to 0.0000064%). Poprawka: „oszczędność 0.008 p.p. stawki dziennej".

[DROB] §31.5 — Kryzys: „spread ~25 PLN/bbl". Nie podano mechanizmu — czy to mnożnik 25/86=0.29 na ceny produktów, czy spadek VWAP ropy, czy wzrost OPEX. Poprawka: dodać klucz `crisis_spread_multiplier = 0.29` w game_balance, działający na ceny produktów.

[DROB] §11A.2 — `koszt_dzienny_miejsca` zdefiniowany jako `utrzymanie_dobowe / liczba_miejsc`, ale `utrzymanie_dobowe` nie jest jawnie podane (jest `utrzymanie zł/h`). Dla małego: 1 800 × 24 / 3 = 14 400. Poprawka: jawnie zapisać: `koszt_dzienny_miejsca = (utrzymanie_h × 24) / liczba_miejsc`.

[DROB] §31.6 — franszyza 15% vs limit 50M: przy szkodzie 100M wypłata = min(100M×0.85, 50M) = 50M. Czy franszyza odejmowana przed limitem? Tekst nie precyzuje kolejności. Poprawka: „wypłata = min(szkoda × 0.85, 50M PLN)".

[DROB] §7.2 — „Zakaz działania wstecz" obejmuje cały §7.2, ale basic_escort działa „natychmiast". Akapit mówi jednocześnie „aktywacja od następnego sweepa" (dla subskrypcji) i „natychmiast" (dla eskorty). Poprawka: przenieść „Zakaz działania wstecz" jako klauzulę dotyczącą wyłącznie drone_patrol i hub_security.

[DROB] §31.3 — Wariant A: suma=28+26+6+32+4+4=100 ✓. Wariant B: 42+30+14+6+4+4=100 ✓. Ale Wariant A produkuje LPG=4 bbl, a Wariant B też LPG=4 bbl — ten sam uzysk LPG niezależnie od technologii. Jeśli to celowe, warto skomentować.

[DROB] §24.5 — Kotwica limitu B2C: „5× gotówkowy pakiet startowy" = 5×2M = 10M. Pakiet startowy to 2M gotówki + 2M banku (§1), więc 5×2M=10M ✓. Ale tekst §24.5 pkt 2 mówi też „koszt max 50 000 PLN/dobę" — 10M × 0.5% = 50 000. ✓.

[DROB] §31.2 — „capacity_bbl_h" dla Klasy I = 120. §31.5: Klasa I przychód marżowy = 120 × 101 = 12 120 PLN/h. ✓. Ale we wzorze netto: 12.1 − 2.6 − 2.4 = 7.1, a 12.1 − (2.64+2.4) = 7.06. Zaokrąglenie 2.64→2.6 ukrywa 40 PLN/h różnicy (960 PLN/dobę). Pomijalne, ale nieudokumentowane.

[DROB] §31.2.3 — „czwarty etap otrzymuje różnicę do sumy kontraktu". Przy zaokrągleniach groszowych 3 pierwszych etapów, etap 4 = CAPEX_całkowity − Σ(etapy 1–3). Jeśli CAPEX_całkowity jest zaokrąglony, suma etapów może nie sumować się idealnie. Poprawka: etap 4 = CAPEX_całkowity − Σ(etapy 1–3); różnicę ≤3 grosze absorbuje etap 4.

[DROB] §31.5 — sezonowość: „Wzrost zapotrzebowania na naftę lotniczą Jet A-1 (+30%)". Czy to +30% ceny czy +30% wolumenu popytu? Tekst mówi „zapotrzebowania" (popyt), nie „ceny". Dalej: „Ceny spot rosną o 15–25%". Dwa różne efekty — dobrze, ale łatwo przeoczyć. Poprawka: rozdzielić jawnie: mnożnik popytu NPC i mnożnik ceny.

[DROB] §27.1a — „OPEX tydzień 1: ... paliwo 6 720 × 4". 4 PLN/bbl to koszt cysterny standard z §11A.6. ✓. Ale 6 720 bbl wydobycia ≠ 6 720 bbl transportu — część zostaje w buforze, część to straty. Poprawka: transport ~6 500 bbl (po stratach rurociągu polowego 0.8% §11A.3).

[DROB] §2 — „7 dni czystej działalności bez incydentów (+3)" — nie jest jasne, czy +3 jest jednorazowe po 7 dniach, czy co 7 dni, czy za każde 7-dniowe okno. Poprawka: „co 7 kolejnych dni bez incydentu: +3 credibility (faza 6 sweepa)".

[DROB] §31.2.3 — „Strajk podwykonawcy A/F to odrębny, jednorazowy wynik na etap i postój 48 godzin; nie ma nieopisanej opcji 'dosypania gotówki'". Zdanie „nie ma nieopisanej opcji" to meta-komentarz, nie specyfikacja — do usunięcia z dokumentu implementacyjnego.

[DROB] §11A.6 — „tryb awaryjny bez huba ~6,3" w tabeli kosztów. 6.3 nie ma rozbicia na składowe — czy to koszt cystern ×2 + strata 3% × 250? 4×2 + 0.03×250 = 8 + 7.5 = 15.5, nie 6.3. Poprawka: podać wzór: koszt = (4.00 × 2 + 0.03 × 250) × 100/100? Albo skorygować liczbę.
