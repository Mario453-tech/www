[KRYT] §19.2 vs §17.3 — sprzeczny próg bankructwa: §17.3 „brak płynności 48h → bankruptcy", §19.2 „najwcześniej po 14 dniach dormant" — ujednolicić: `bankruptcy` dopiero po 14 dniach `dormant`; 48h to tylko start `recovery_mode`.
[KRYT] §7.1 vs §11A.4 — `road_ambush`: §7.1 „przejęcie max 30% ładunku", §11A.4 „utrata 100% kursu"; `road_partial_theft`: 20–50% vs 30–70%. Ustalić kanon (np. ambush 30% + §11A.4 usunąć 100%) i spisać w jednym miejscu.
[KRYT] §11A.6 — próg opłacalności fałszywy: 1,80 zł/h ÷ (4,00−1,90 zł/bbl) = 857 bbl/h = 20 571 bbl/dobę, nie „1 440 bbl/dobę"; zwrot 600 000 ÷ (857×2,10×24) ≈ 14 dni, nie „~60 dni". Przeliczyć i poprawić oba progi.
[KRYT] §7.1 vs §11A.7 — `hub_valve_sabotage`: „max 2 000 bbl + wear +15" vs „kondycja −5…−15, rozlanie 2–5% bufora, przepustowość ×0,5 na 6–24h" — dwa różne zestawy skutków; wybrać jeden (kanon cen §7.2 nie obejmuje skutków).
[KRYT] §31.5 pkt 6 vs pkt 1/D2 — RARS: `quality_score ≥ 90` vs próg klasy lotniczej 92–100 i „tylko te partie do RARS". Zmienić §31.5 pkt 6 na `>= 92.00`.
[KRYT] §24.5 vs §7.2 — kierunek płatności ochrony: §7.2 „PLN_CASH, dopełnienie z PLN_BANK", §24.5 pkt 5 kanon „BANK → CASH". Wpisać ochronę jawnie do wyjątków CASH-first w §24.5 pkt 5.
[KRYT] §2 vs §14.3 — propagacja: §2 „KAŻDA zmiana credibility przenosi 50% Δ na B2B", §14.3 „2FA NIE wpływa na b2b_reputation". Dodać w §2 wyjątek: `twofa_bonus_granted` wyłączony z propagacji.
[KRYT] §7.3 vs §11A.2 — pozwolenie P2: rozpatrzenie „7 dni" vs „~12 h"; po odmowie „72h" vs „12h przerwy"; §11A.2 ma stany `opóźnione` nieobecne w §7.3. Kanon: §7.3; §11A.2 przepisać na 500k/7dni/72h.
[KRYT] §9.1 vs §11A.1 pkt 4 — status `choked` używany w kaskadzie i testach T3/T12, ale nie ma go w enumie §9.1 ani w widoku §18.1 pkt 3. Dodać `choked` do listy stanów odwiertu.
[KRYT] §24.5 pkt 4 vs §31.4 — INCOME_SET nie obejmuje przychodów rafinerii (`REF_SPOT_SALE`, `REF_RETAIL_SALE`, `REF_FUEL_PAYOUT`) — Faza 1 egzekucji ich nie zajmie. Dopisać kody §31.4 do INCOME_SET.
[KRYT] §13.2 vs §26.3 — sankcja kartelowa: −15 `company_credibility` (§26.3) vs −15 `b2b_reputation` (§13.2) — nie wiadomo, czy to dwa skutki, czy jeden źle nazwany. Rozstrzygnąć: oba, każdy w swojej skali.
[KRYT] §14.3 vs §14.5 — 2FA admina: „blokada IP po 5 błędnych próbach" vs „pełna blokada IP nie jest stosowana". Ustalić: blokada IP tylko dla panelu Filament, nie dla graczy.
[KRYT] §31.4 — tabela FTS rafinerii pomija kody EPC (`REF_EPC_CASH_STAGE`, `REF_EPC_DELAY_COMP`, `REF_EPC_DEFECT_REPAIR`, `REF_EPC_SITE_REPAIR`) mimo adnotacji pod tabelą; implementator weźmie tabelę jako pełną. Scalć kody do tabeli.
[KRYT] §7.3 vs §9.4/§11A.2 — zakresy pozwoleń się rozjeżdżają: P1 „wydobycie/eksploatacja" vs „wiercenia regionalne" (§18.1); P2 „rafineria" vs „budowa huba i rurociągu". Dodać mapę: P1 = koncesja+odwiert, P2 = hub/rurociąg/rafineria.
[KRYT] §13.2 vs §11A.8 — stany B2B: §13.2 `in_transit`, §11A.8/§18.4 `in_delivery` i `cargo_in_b2b_transit` — trzy nazwy tego samego stanu. Kanon: `in_transit`; usunąć `in_delivery`.
[IST] §27.1a vs §9.5 — budżet FTUE: „Wiercenie (płytka, 8–16h) 1 200 000" ale §9.5 shallow = 800 000 PLN i 6 h. Poprawić na 800 000/6h albo dodać mnożnik regionu (×1,5 = 1 200 000).
[IST] §27.1a — „Druga nitka: wiercenie 600 000" vs §9.5 shallow 800 000 — brak uzasadnienia (Frontier −25% daje 600 000, ale §19.4 dotyczy nowych regionów). Dopisać źródło: mnożnik frontier 0,75×800k=600k.
[IST] §23.4 vs §9.2 — kaskada: „ogniwo przechodzi w `paused_storage`/`choked` (kaskada §9.2)" — §9.2 to krzywa wyczerpania, kaskada jest w §11A.1 pkt 4. Poprawić odwołanie.
[IST] §17.4 vs §23.6 — eskalacja blowoutu „w każdym ticku" nie ma reguły agregacji przy długim Δt (§23.6 zabrania replayu N rzutów). Dodać: jeden zagregowany rzut eskalacji na segment.
[IST] §13.2 — `B2B_GM_AWARD` dla wygrywającego nie jest w INCOME_SET §24.5 pkt 4 — Faza 1 go nie zajmie. Dopisać albo jawnie wyłączyć.
[IST] §19.1 vs §31.7 — lista aktywów trwałych w podatku: §19.1 wymienia „odwierty, huby, rurociągi", bez rafinerii; §31.7 pkt 3 wlicza rafinerię do bazy. Dodać „rafinerie" w §19.1.
[IST] §31.8 — enum `refineries.status` zawiera `commissioning`, ale §31.2 etap 4 mówi tylko o przejściu w `operational`; brak reguły, kiedy stan `commissioning` jest ustawiany. Zdefiniować lub usunąć.
[IST] §31.8 — `refinery_units.unit_type` wymienia `reformer`, `coker`, których receptury §31.3 nie definiują (tylko warianty A i B). Usunąć z enuma v1 albo dodać receptury.
[IST] §31.8 — `refinery_insurance_policies.policy_status` (active/lapsed/claim_processing/cancelled) nie ma definicji przejść w §31.6. Dodać maszynę stanów polisy.
[IST] §31.10 — nagłówek „Testy R1–R12", wypisane R1–R6. Dopisać R7–R12 (np. bilans EPC, CAS budowy, malus, franszyza, idle_standby, snapshot).
[IST] §31.2 — „Klasa I: `product_tank_each` 6 000 bbl (~5 dób)": 6 000 ÷ (120×24×0,42 benzyna) = 6 000÷1 210 ≈ 4,96 dób — OK, ale dla mazutu 32%: 6 000÷922 ≈ 6,5 doby. Doprecyzować, którego produktu dotyczy „~5 dób".
[IST] §31.2 vs §31.2.3 — „wybór generalnego wykonawcy (kontrakt EPC — §31.2.3)" jest w §31.2, ale §31.2.3 mówi „przed opłaceniem etapu 1"; brak reguły, co z EPC przy zmianie klasy rafinerii w trakcie planowania. Dodać: zmiana klasy = nowy kontrakt.
[IST] §31.2.3 — „odchylenia ±5% względnie, integralność ±2 p.p., ryzyko wady ±2 p.p." — ale zakresy katalogu: „koszt co najmniej 0,60×, integralność co najmniej 70%, ryzyko najwyżej 50%". F: 0,65×0,95=0,6175 ≥ 0,60 OK; integralność 72−2=70 OK; ryzyko 45+2=47 ≤ 50 OK — spójne, ale §31.2.3 nie mówi, co z A (0,80×0,95=0,76) i D (1,30×1,05=1,365) — brak górnej granicy kosztu. Dodać cap 1,40×.
[IST] §29.6 — „transport kolejowy (§11A/D1)" — §11A nie ma podsekcji D1; D1 jest w §31.1. Poprawić odwołanie na §31.1 D1.
[IST] §25 vs §23.3 — „start i koniec wydarzeń w fazie 8 ticka (§23.2)", ale §23.3 faza 3 = Rynek (cena, wydarzenia) i faza 8 = decay. Przenieść wydarzenia do fazy 3 albo dopisać w §23.3.
[IST] §23.1 vs §23.2 — „`simulation_heartbeats` → `simulation_sweeps`" (§23.1), ale §23.2 mówi „pole `tick_no` → `sweep_no`" bez wzmianki o heartbeats; §29.3 „nieudany heartbeat" używa starej nazwy. Ujednolicić na `simulation_sweeps`.
[IST] §22.3 vs §3.1 — „Executive Headhunter 25 GBBL" — §3.1 mówi „Headhunter: płatna usługa" bez kwoty i bez GBBL. Dodać w §3.1: 25 GBBL, max 1/dobę (§22.3).
[IST] §14.1 vs §1 — „pożyczka rozruchowa 500 000 PLN od `SYS_TREASURY`" dla multikont — brak reguły spłaty, oprocentowania i terminu. Dodać: jak kredyt §17.1 (12%/rok, 30 dni) albo bezzwrotna z blokadą Kroplówki.
[IST] §18.3 vs §14.3 — role GM: §18.3 `admin > gm_support > gm_readonly`, §14.3 „obowiązkowe 2FA dla `admin` i `gm`" — `gm_readonly` nie jest wymienione. Dopisać: wszystkie role GM z 2FA.
[IST] §31.6 vs §3 — „Brak nadzoru HSE: wakat na stanowisku Inspektora BHP w Dziale Technicznym" — §3 nie definiuje roli Inspektor BHP (tylko operatorzy, inżynierowie). Dodać stanowisko w §3.1 i §3.2.
[IST] §31.5 pkt 4 vs §31.1 D2 — sezonowość: „benzyna Pb 95/98" — ale §31.1 D2 mówi „Progi: 70–84 Standard, 85–91 Euro 6, 92–100 Jet A-1", bez Pb 95/98 (to v2). Zmienić na „benzyna Euro 6".
[IST] §11A.4 vs §17.2 — stan `zajęty` (seized) dla cysterny: brak reguły, kto jest właścicielem ładunku po zajęciu i czy przychód trafia do egzekucji. Dodać: jak §21 „Ładunek w trasie należy do dłużnika do rozładunku".
[IST] §13.2 vs §26.3 — agregacja dobowa kartelu: „1 500 bbl/dobę/parę" — ale transze mają własne zegary 168h; brak reguły, czy limit dotyczy `published`, `accepted` czy `settled` w danej dobie UTC. Dodać: suma `settled` + `in_transit` w oknie 00:00–24:00 UTC.
[IST] §24.5 pkt 5 vs §31.2.3 — EPC: „25% z PLN_CASH, 75% z PLN_BANK" (tylko F) — ale §24.5 pkt 5 kanon „BANK → CASH" nie obejmuje EPC; brak reguły, czy niedobór na CASH blokuje oba ruchy (§31.2.3 mówi „tak"). Dopisać EPC do wyjątków w §24.5.
[DROB] §7.2 vs §11A.7 — nazewnictwo: `basic_escort` (§7.2) vs `escort_basic` (§11A.7). Ujednolicić na `basic_escort`.
[DROB] §2 vs §24.4 — nazwy wskaźników: §2 `b2b_reputation_scores` (plural), §24.4 migracja na `b2b_reputation_score` (singular), ale §2 i §14.3 nadal plural. Przepisać §2/§14.3 na singular.
[DROB] §31.2 — Klasa I `crude_tank_bbl` 6 000 „(~2 doby wsadu)": 6 000÷(120×24) = 2,08 doby — OK, ale Klasa II 20 000÷(400×24) = 2,08, Klasa III 50 000÷(1 000×24) = 2,08 — wszystkie ~2,08, nie „~2". Spójne, ale doprecyzować „2,1 doby".
[DROB] §17.3 vs §2 — „Realizacja planu naprawczego → +8 credibility" — §2 lista wzrostów ma „spłata kredytu +8", bez „wyjście z recovery_mode +8". Dodać w §2.
[DROB] §2 vs §14.3 — bonus 2FA +5 credibility nie ma go na liście §2 wzrostów (tylko spłaty i 7 dni czystej działalności). Dodać „włączenie 2FA +5 (jednorazowo)".
[DROB] §11A.4 vs §21 — stan `zatoka` (§11A.4) vs `waiting_bay` (§21) — dwie nazwy tego samego. Ujednolicić na `waiting_bay`.
[DROB] §9.4 vs §11A.2 — lejek odwiertu: §9.4 krok 5 „Zakup i montaż rurociągu dopływowego", ale nie wspomina o przypisaniu odwiertu do huba (§11A.2, opłata ×5/×8). Dodać krok 4a: przypisanie do huba.
[DROB] §31.4 vs §24.2 — `REF_BLACK_SALE`: „from_account `SYS_TREASURY`" — ale §24.2 mówi „gotówka czarnorynkowa trafia do PLN_CASH" bez konta źródłowego; §31.4 wprowadza `SYS_TREASURY` jako źródło, co nie jest w §24.2. Dodać w §24.2.
[DROB] §13.2 — „Kontrola kont powiązanych … (§13.2 dotychczas, §22.6)" — §22.6 nie wspomina o blokadzie transakcji między kontami powiązanymi. Przenieść regułę do §22.6 albo usunąć odwołanie.
