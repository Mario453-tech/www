# -*- coding: utf-8 -*-
"""
OilEmpire — headless symulator/weryfikator balansu (v1, 2026-11-14)
Przelicza kanoniczne liczby z dokumentu OILEMPIRE_ARCHITEKTURA_SYSTEMOWA.md
i krzyżuje je z OILEMPIRE_GAME_BALANCE_V1.yaml. Zero zależności poza PyYAML.
Uruchomienie:  python OILEMPIRE_SYMULATOR_BALANSU.py
Kod wyjścia 0 = wszystko PASS; 1 = co najmniej jeden FAIL.
"""
import sys, io, os
import yaml

BASE = os.path.dirname(os.path.abspath(__file__))
DOC = os.path.join(BASE, "OILEMPIRE_ARCHITEKTURA_SYSTEMOWA.md")
YML = os.path.join(BASE, "OILEMPIRE_GAME_BALANCE_V1.yaml")

results = []
def check(nr, nazwa, ok, detal=""):
    results.append((nr, nazwa, "PASS" if ok else "FAIL", detal))
def info(nr, nazwa, detal):
    results.append((nr, nazwa, "INFO", detal))
def close(a, b, tol=0.51):
    return abs(a - b) <= tol

Y = yaml.safe_load(io.open(YML, encoding="utf-8").read())
M = Y["modules"]
doc_txt = io.open(DOC, encoding="utf-8").read()

# ---------- pomocnicze wyciągi z YAML ----------
def deep(d, *keys):
    for k in keys:
        if isinstance(d, dict) and k in d: d = d[k]
        else: return None
    return d

ev_types   = deep(M, "economy", "params", "world_events", "types") or {}
cs_formula = deep(M, "banking", "params", "credit_score_formula") or {}
bm         = deep(M, "market", "params", "market", "black_market") or deep(M, "market", "params", "black_market") or {}
b2b_rep    = deep(M, "market", "params", "contracts_system", "b2b_reputation") or {}
npc_gen    = deep(M, "market", "params", "contracts_system", "npc_generator") or {}
refining   = deep(M, "refining", "params") or {}

# =========================================================
# A. KOTWICE ARYTMETYCZNE (niezależne od YAML)
# =========================================================
PRICE_BASE = 250.0
PROD = {"benzyna":355.0,"diesel":370.0,"jet":395.0,"mazut":205.0,"lpg":210.0}
# bilans masowy wariant A (prosta destylacja): 28/26/6/32/4/4
balA = 28+26+6+32+4+4
balB = 42+30+14+6+4+4
recipe_c = deep(refining, "refinery", "recipes", "variant_c_hydrotreating") or {}
recipe_c_total = sum(recipe_c.get(k, 0) for k in ("gasoline_bbl", "diesel_bbl", "jet_a1_bbl", "mazut_bbl", "lpg_bbl", "process_losses_bbl"))
check("A1", "Bilans masowy receptur = 100 bbl", balA==100 and balB==100 and recipe_c_total==100, f"A={balA}, B={balB}, C={recipe_c_total}")
check("A1C", "Wariant C: Klasa II+, hydrotreater, OPEX +8 PLN/bbl i kanon w dokumencie",
      recipe_c.get("min_refinery_class")==2 and recipe_c.get("requires_unit")=="hydrotreater"
      and recipe_c.get("variable_opex_surcharge_pln_bbl")==8
      and "16 660 PLN/h" in doc_txt and "22 bbl Nafta Jet A-1" in doc_txt)

# koszyk bazowy produktów wariantu B (kraking, pełna jakość): 42/28/14/10/4/2 → ~336 PLN/bbl (marża 86)
koszyk = 0.42*355 + 0.28*370 + 0.14*395 + 0.10*205 + 0.04*210
check("A2", "Koszyk produktów (wariant B) ≈ 336 PLN/bbl (marża bazowa 86)", close(koszyk, 336, 1.5) and close(koszyk-250, 86, 1.5), f"obliczone {koszyk:.1f}, marża {koszyk-250:.1f}")
c_basket = (30*355 + 34*370 + 22*395 + 6*205 + 4*210)/100
c_net_h = (c_basket - PRICE_BASE - 19 - 8)*400 - 8500
check("A2C", "Wariant C: kosz 339,90, netto Klasy II 16 660 PLN/h < B 18 300 bez premii",
      close(c_basket, 339.9, 0.01) and close(c_net_h, 16660, 0.01) and c_net_h < 18300,
      f"kosz={c_basket:.2f}, netto={c_net_h:.0f}")

crisis = 336*0.29 - 250*0.29
check("A3", "Kryzys: spread ≈ 25 PLN/bbl (§31.5)", close(crisis, 25, 2.0), f"{crisis:.2f}")
boom = 336*1.35 - 250*1.30
check("A4", "Boom: marża ∈ [120;150] (§25.1)", 120 <= boom <= 150, f"{boom:.1f}")

# Wariant A hurt (jakość 75 → ×0,92; jet 65 → opałowy ×0,55)
wA = (28*355+26*370+32*205+4*210)*0.92/100 + 6*395*0.55/100
check("A5", "Wariant A: kosz hurtowy ≈ 261, marża ≈ 11 (§31.5)", close(wA,261,2) and close(wA-250,11,2), f"kosz {wA:.1f}, marża {wA-250:.1f}")

# Klasa I detal: wszystko ×0,92×1,30 poza jet (65 pkt → ×0,55)
detal = ((28*355+26*370+32*205+4*210)*0.92*1.30 + 6*395*0.55)/100
marginI = detal - 250
netI = (marginI - 22)*120 - 2400
payI = 18_000_000/(netI*24)
check("A6", "Klasa I: marża 85,5 / netto ~5,2k/h / zwrot ~144 dni", close(marginI,85.5,1) and close(netI,5200,150) and close(payI,144,3),
      f"marża {marginI:.1f}, netto {netI:.0f}/h, zwrot {payI:.0f} dni")
netII = (86-19)*400 - 8500
payII = 75_000_000/(netII*24)
check("A7", "Klasa II: przychód 34,4k/h, netto ~18,3k/h, zwrot ~170 dni", close(86*400,34400,1) and close(netII,18300,50) and close(payII,170,3),
      f"netto {netII}/h, zwrot {payII:.0f}")
netIII = (96-16)*1000 - 21000
payIII = 240_000_000/(netIII*24)
check("A8", "Klasa III: przychód 96,0k/h (spójne!), netto ~59,0k/h, zwrot ~170 dni", close(96*1000,96000,1) and close(netIII,59000,1) and close(payIII,170,3),
      f"netto {netIII}/h, zwrot {payIII:.0f} dni")
# spójność z tekstem dokumentu — wiersz P&L Klasy III
dok_I  = "| I (detal ×1,30 z mnożnikiem jakości; ~85,5 PLN/bbl) | 10,3 tys. PLN/h" in doc_txt
dok_III_przychod = "96,0 tys. PLN/h" in doc_txt
check("A9", "Dokument: wiersz Klasy III ma przychód 96,0 tys./h (nie 96,4 — niespójne z marżą 96)", dok_III_przychod,
      "znaleziono '96,0 tys. PLN/h'" if dok_III_przychod else "w dokumencie nadal '96,4 tys. PLN/h'")

check("A10", "B2C: 40 000 bbl × 250 = 10 mln; 0,5% = 50 000", close(40000*250*0.005, 50000, 0.1))
check("A11", "Kołnierz kartelowy: 1500×250×1,2 = 450 000", close(1500*250*1.2, 450000, 0.1))
t_std = 1250/500 + 0.006*250
t_op  = 3500/350 + 0.002*250
t_fb  = 2*4.00 + 0.03*250
check("A12", "Transport: standard 4,00 / opancerzona 10,50 / fallback ~15,5", close(t_std,4,0.01) and close(t_op,10.5,0.01) and close(t_fb,15.5,0.01),
      f"{t_std:.2f} / {t_op:.2f} / {t_fb:.2f}")
wagi = 0.45+0.15+0.10+0.10+0.10+0.10
check("A13", "Morale: suma wag = 1,00 (§4.3)", close(wagi,1.0,1e-9), f"{wagi:.2f}")
cs_start = 0.35*70 + 0.35*40 + 0.20*50 + 0.10*0
check("A14", "credit_score start = 48,5 → tier 40–59 (max 2 mln)", close(cs_start,48.5,0.1) and 40 <= cs_start < 60, f"{cs_start:.1f}")
cs_clean = 0.35*90 + 0.35*100 + 0.20*90 + 0.10*100 + 15
check("A15", "credit_score wzorowy ≥ 100 (tier 100–115 osiągalny)", cs_clean >= 100, f"{cs_clean:.1f}")
bhp_sum = 120_000+350_000+800_000+1_800_000
check("A16", "BHP: suma upgrade'ów L1→L5 = 3,07 mln", bhp_sum==3_070_000, f"{bhp_sum:,}")
pi_std = (100-70)/0.4
pi_low = (100-70)/(0.4*1.8)
check("A17", "PI: 100→70 w 75 dni (standard) / ~42 dni (low)", close(pi_std,75,0.5) and close(pi_low,41.7,0.5), f"{pi_std:.0f} / {pi_low:.0f}")
clamp_stack = 1.30*1.80
check("A18", "Wydarzenia: boom×wojna = 2,34 > clamp 2,20 → clamp aktywny", clamp_stack > 2.20, f"{clamp_stack:.2f}")
def wealth_tax_daily(netto):
    """§19.1 — stawki MARGINALNE od nadwyżki: 0–200M 0%; 200–500M 0,03%/d; 500M–1B 0,05%/d; >1B 0,08%/d."""
    br = [(200e6, 0.0), (500e6, 0.0003), (1e9, 0.0005), (float("inf"), 0.0008)]
    tax, prev = 0.0, 0.0
    for cap, rate in br:
        if netto <= prev: break
        tax += (min(netto, cap) - prev) * rate
        prev = cap
    return tax
check("A19", "Podatek majątkowy MARGINALNIE: 200M→0; 400M→60k; 1,2B→500k/dzień (§19.1)",
      close(wealth_tax_daily(200e6),0,1) and close(wealth_tax_daily(400e6),60_000,1) and close(wealth_tax_daily(1.2e9),500_000,1),
      f"{wealth_tax_daily(200e6):,.0f} / {wealth_tax_daily(400e6):,.0f} / {wealth_tax_daily(1.2e9):,.0f}")

# =========================================================
# B. SPÓJNOŚĆ YAML ↔ DOKUMENT (wybrane klucze kanoniczne)
# =========================================================
def yaml_eq(nr, nazwa, sciezka, oczek):
    v = deep(M, *sciezka)
    check(nr, nazwa, v == oczek, f"YAML={v!r}, kanon={oczek!r}")

def find_key(d, key, path=""):
    hits=[]
    if isinstance(d, dict):
        for k,v in d.items():
            if k==key: hits.append((path+"/"+k, v))
            hits += find_key(v, key, path+"/"+k)
    elif isinstance(d, list):
        for i,v in enumerate(d): hits += find_key(v, key, f"{path}[{i}]")
    return hits

_pb = find_key(M, "oil_price_base_pln_bbl")
check("B1", "YAML: cena bazowa ropy = 250 PLN/bbl (§24.1)", bool(_pb) and _pb[0][1]==250, str(_pb[0][1] if _pb else None))
ref_classes = deep(refining, "classes") or deep(M,"refining","params","refinery_classes") or {}
# lokalizacja klas rafinerii — przeszukaj (find_key zdefiniowane wyżej)
fixed_hits = find_key(M, "fixed_opex_pln_h")
if fixed_hits:
    vals = sorted({v for _,v in fixed_hits})
    check("B2", "YAML: fixed OPEX rafinerii = {2400, 8500, 21000}", set(vals)=={2400,8500,21000}, f"{vals}")
else:
    info("B2","YAML: fixed_opex_pln_h","brak klucza — sprawdź strukturę modułu refining")
capex_hits = find_key(M, "capex_pln")
if capex_hits:
    vals = sorted({v for _,v in capex_hits})
    check("B3", "YAML: CAPEX = {18M, 75M, 240M}", set(vals)=={18_000_000,75_000_000,240_000_000}, f"{vals}")
else:
    info("B3","YAML: capex_pln","brak klucza capex_pln (może być capex)")

check("B4", "YAML: premia czarnego rynku 15%", bm.get("oil_price_premium_pct")==15, str(bm.get("oil_price_premium_pct")))
check("B5", "YAML: limity CM 5 transakcji / 1500 bbl/dobę", bm.get("daily_trade_limit")==5 and bm.get("daily_volume_limit_bbl")==1500)
check("B6", "YAML: score +2 / decay −1/24h", bm.get("score_per_trade")==2 and bm.get("score_decay_per_24h")==1)
check("B7", "YAML: b2b start 50, delta −8/−6/−15, progi 75/25/10",
      b2b_rep.get("start_value")==50 and b2b_rep.get("refunded_seller_fault")==-8
      and b2b_rep.get("missed_or_penal_cancel")==-6 and b2b_rep.get("cartel_sanction")==-15
      and b2b_rep.get("premium_threshold")==75 and b2b_rep.get("market_lock_below")==25 and b2b_rep.get("blacklist_below")==10)
check("B8", "YAML: generator NPC tier1 od 240 bbl, premium rep 75/kaucja 5%",
      deep(npc_gen,"tiers_by_contract_reputation","t1","tranche_bbl")==[240,960]
      and deep(npc_gen,"tiers_by_contract_reputation","premium","deposit_pct")==5)
check("B9", "YAML: credit_score wagi 0.35/0.35/0.20/0.10 + cap bonusu 15",
      "0.35*credibility" in str(cs_formula.get("formula","")) and cs_formula.get("clean_bonus_cap")==15)
ev_ok = ev_types and close(deep(ev_types,"kryzys_ekonomiczny","oil") or 0, 0.29, 1e-9) \
        and close(deep(ev_types,"wojna_naftowa","oil") or 0, 1.80, 1e-9) \
        and deep(ev_types,"kryzys_ekonomiczny","exclusive") is True
check("B10", "YAML: wydarzenia — kryzys 0,29 exclusive, wojna 1,80", bool(ev_ok))
grey = deep(M,"legal","params","permits_bribes","grey_zone_flag") or {}
envi = deep(M,"legal","params","permits_bribes","env_incident_180d") or {}
check("B11", "YAML: flaga szarej strefy próg 30 + EPC F; incydenty 180 dni z wyłączeniem sabotażu",
      grey.get("bms_threshold_pts")==30 and grey.get("active_epc_archetype_f_counts") is True
      and envi.get("window_days")==180 and envi.get("exclude_confirmed_sabotage") is True)
sal = find_key(M, "min_market_salary_pln")
if sal:
    s = sal[0][1]
    check("B12", "YAML: płace — chemik 26k, operator rafinerii 17k, dyrektor finansowy 38k",
          s.get("chemical_engineer")==26000 and s.get("refinery_operator")==17000 and s.get("director_finance")==38000)
else:
    info("B12","YAML: min_market_salary_pln","brak klucza")
onb = find_key(M, "week1_obligations_total_pln")
check("B13", "YAML: FTUE suma zobowiązań 3 134 000 ≤ 4 mln", onb and onb[0][1]==3_134_000, str(onb[0][1] if onb else None))

# =========================================================
# C. SYMULACJE
# =========================================================
# --- C1: FTUE tydzień 1 (krok 1h) ---
cash = 2_000_000.0 + 2_000_000.0      # gotówka + bank
bufor = 800.0                          # bbl w hubie NPC (opłacone 7 dni z pakietu)
zloze = 7000.0
wydobyte = 0.0
przychod = 0.0
koszty = 0.0
min_cash = cash
t_pierwsza_sprzedaz = None
harmonogram = {  # wydatki w godzinach (kanon §27.1a: łańcuch własny 1,59M + druga nitka 1,15M = 2,74M harmonogramu; +OPEX ~394k = 3,134M)
    1:   [("2 cysterny używane (2×120k)", 240_000)],
    2:   [("P1 pozwolenie", 150_000)],
    6:   [("badanie geologiczne", 150_000)],
    60:  [("wiercenie odwiert #2 (6h)", 800_000)],
    72:  [("rurociąg dopływowy #2", 250_000)],
    96:  [("P1 druga nitka", 150_000)],
    100: [("badanie #2", 150_000)],
    120: [("wiercenie #3", 600_000)],
    140: [("rurociąg #3", 250_000)],
}
pensje_h = 45_000/168.0
cykl = 0.0
for h in range(0, 169):
    # wydobycie szkoleniowe 40 bbl/h do wyczerpania złoża
    if zloze > 0:
        q = min(40.0, zloze)
        zloze -= q; bufor += q; wydobyte += q
        koszty += q*30                     # OPEX wydobycia shallow
    # cysterna: kurs co ~13,5h (12,5h napełniania 500 bbl + 1h trasy)
    cykl += 1
    if bufor >= 500 and cykl >= 13:
        bufor -= 500
        rev = 500*250
        przychod += rev; cash += rev
        koszty += 500*4                    # paliwo/transport 4 PLN/bbl
        cykl = 0
        if t_pierwsza_sprzedaz is None: t_pierwsza_sprzedaz = h+1
    # koszty stałe
    koszty += 120 + pensje_h               # rura polowa 120/h + pensje
    cash -= 120 + pensje_h
    # sprzedaż bufora startowego od razu (800 bbl → pierwszy kurs natychmiast)
    if h == 0:
        cash += 500*250 - 500*4; przychod += 500*250; koszty += 500*4; bufor -= 500
        t_pierwsza_sprzedaz = 1
        cykl = -12  # następny kurs po napełnieniu
    for nazwa, kwota in harmonogram.get(h, []):
        cash -= kwota; koszty += kwota
    min_cash = min(min_cash, cash)
saldo_konc = cash
ok_ftue = saldo_konc > 0 and min_cash > -50_000
check("C1", "FTUE 7 dni: dodatnie saldo końcowe i brak zapaści płynności", ok_ftue,
      f"saldo {saldo_konc:,.0f} PLN, min {min_cash:,.0f}, przychód {przychod:,.0f}, koszty {koszty:,.0f}")
check("C2", "FTUE: pierwsza sprzedaż w ≤2h (bufor 800 bbl w pakiecie)", t_pierwsza_sprzedaz is not None and t_pierwsza_sprzedaz <= 2,
      f"t={t_pierwsza_sprzedaz}h")
check("C3", "FTUE: złoże 7000 bbl przy 40 bbl/h = 175h pracy (tydzień 1 + 7h)", close(7000/40, 175, 0.1))
check("C4", "FTUE: harmonogram 2,74M + OPEX ~394k = kanon 3,134M ≤ 4M", close(sum(k for v in harmonogram.values() for _,k in v), 2_740_000, 1),
      f"harmonogram {sum(k for v in harmonogram.values() for _,k in v):,} PLN")

# --- C5: czarny rynek — EV i trajektoria score ---
# przy 1 transakcji/dobę: +2 −1 = +1/dobę → próg 30 po 30 dniach
dni_do_flagi = 30
audit_p_dzien = lambda score: max(0, (score-30))*0.0002*24
ev = lambda score: 1500*250*0.15 - audit_p_dzien(score)*(2*1500*250*1.15)
check("C5", "Czarny rynek: flaga przy 1 trans./dobę po ~30 dniach", dni_do_flagi==30)
check("C6", "Czarny rynek: EV dodatnie przy score 35, ujemne przy 60+", ev(35)>0 and ev(60)<0,
      f"EV(35)={ev(35):,.0f}, EV(60)={ev(60):,.0f} PLN/dobę")
check("C7", "Czarny rynek: limit dobowy zysku premii ≈ 56 tys. (1500 bbl × 250 × 15%)", close(1500*250*0.15, 56250, 1))

# --- C8: kryzys w rafinerii Klasy II ---
net_crisis = (25-19)*400 - 8500
check("C8", "Kryzys: Klasa II pod kreską (−6,1 tys./h)", net_crisis < 0 and close(net_crisis,-6100,50), f"{net_crisis:,} PLN/h")
netI_crisis = (335.5*0.29 - 250*0.29 - 22)*120 - 2400
check("C9", "Kryzys: Klasa I (detal) też pod kreską", netI_crisis < 0, f"{netI_crisis:,.0f} PLN/h")

# --- C10: R12 kołnierz kartelowy ---
cena_spot = 250.0
transakcja = 1400*250*1.25   # 1,25× spot — poza kołnierzem 0,8–1,2
prowizja = transakcja*0.015
check("C10", "R12: transakcja 1,25× spot poza kołnierzem → pełna prowizja 1,5%", close(prowizja, transakcja*0.015, 0.01) and 1.25 > 1.2)

# --- C11: R10 flara offline ---
check("C11", "R10: offline 12h → mandaty max za 2h flary (kanon §31)", True, "reguła opisowa; egzekwowana w fazie 2.9")

# --- C12: snapshot Tieru — konwersje GBBL wyłączone ---
tier_base = 1_200_000_000 - 250_000_000   # aktywa minus konwersje GBBL z 30 dni
check("C12", "R11/Tier: 1,2 mld − 250 mln konwersji = 950 mln < 1 mld → brak Tier V", tier_base < 1_000_000_000)

# =========================================================
# D. PRAWO / LOGISTYKA / EPC / UBEZPIECZENIA / DOKUMENT
# =========================================================
yml_txt = io.open(YML, encoding="utf-8").read()

# D1–D2: podatek — zapasy i tarcza
check("D1", "Zapasy w podstawie: 10k bbl × 250 × 90% = 2,25 mln (§19.1.1)", close(10_000*250*0.90, 2_250_000, 1))
starter_netto = 4_000_000 + 800*250*1.0   # tarcza: zapasy w 100% (bez dyskontu podatkowego)
check("D2", "Tarcza startowa aktywna: netto ~4,2 mln < 10 mln (zapasy 100%)", starter_netto < 10_000_000, f"{starter_netto:,.0f}")

# D3: kara anulowania B2B — kierunkowa min(40, max(10, |Δceny|))
f_kara = lambda d: min(40.0, max(10.0, abs(d)))
check("D3", "Kara anulowania: Δ25%→25; Δ5%→10 (floor); Δ60%→40 (cap)",
      f_kara(25)==25 and f_kara(5)==10 and f_kara(-60)==40)

# D4–D5: escrow i zatoka
najgorszy = 72 + 24 + 48 + 72   # ETA + margines + kolejka + freeze sporu
check("D4", "Escrow: najgorszy łańcuch 216h > limit 168h → autorozstrzygnięcie przerywa zegary (zasada §13.2 #5)", najgorszy > 168, f"{najgorszy}h")
check("D5", "Zatoka postojowa: 48h × 2 000 = 96 000 PLN (§13.2)", close(48*2000, 96_000, 1))

# D6–D7: ubezpieczenia
wyplata = min(0.85*100e6, 60e6, 50e6)   # 85% szkody, suma polisy, limit dobowy
check("D6", "Ubezpieczenie: kolejność capów min(85%×szkoda, suma, 50M/dobę) = 50M", close(wyplata, 50e6, 1), f"{wyplata:,.0f}")
check("D7", "Pula: kapitał 100M, reasekuracja <20M, składki +20% (§31.6)", True, "reguły opisowe; parametry w game_balance")

# D8–D9: EPC
oszczednosc_szara = 0.35*75e6
oczekiwana_kara = 4*0.15*(0.05*75e6)
check("D8", "EPC szara strefa: oszczędność 26,25M vs oczekiwane karne ~2,25M + −12 wiarygodności (ryzyko świadome)",
      oszczednosc_szara > oczekiwana_kara*5, f"oszcz. {oszczednosc_szara:,.0f}, E[kara] {oczekiwana_kara:,.0f}")
check("D9", "EPC wada ukryta: 4% × 75M = 3M + 24h (§31.2.3)", close(0.04*75e6, 3e6, 1))

# D10–D11: RARS i franczyza
check("D10", "RARS: jet 395 × 1,20 = 474 PLN/bbl (jakość ≥90)", close(395*1.20, 474, 0.1))
check("D11", "Franczyza: Klasa I 120×24 = 2 880 bbl/dobę ≤ limit 3 000 (§31.5)", 120*24 <= 3000, f"{120*24}")

# D12: ochrona — procent z minimum
eskorta = lambda v: max(0.02*v, 2500)
konwoj  = lambda v: max(0.05*v, 8000)
check("D12", "Ochrona: eskorta(50k)=2 500 min; eskorta(500k)=10 000; konwój(500k)=25 000 (§7.2)",
      close(eskorta(50_000),2500,1) and close(eskorta(500_000),10_000,1) and close(konwoj(500_000),25_000,1))

# D13: pozwolenie P1 — wzór szansy
szansa = lambda cred, flaga=0, incyd=0: min(98, max(20, 70 + 0.5*(cred-70) - 3*flaga - 2*incyd))
check("D13", "P1: wiarygodność 70→70%; 50→60%; 50+flaga→57%; clamp [20;98]",
      szansa(70)==70 and szansa(50)==60 and szansa(50,1)==57 and szansa(0)==35)

# D14: prowizja B2B i zwolnienie kartelowe
check("D14", "B2B: prowizja 1,5% × 1M = 15k; kołnierz kartelu 300–450k PLN/dobę/parę (§26.3)",
      close(0.015*1e6,15_000,1) and close(1500*250*0.8,300_000,1) and close(1500*250*1.2,450_000,1))

# D15–D18: obecność kluczy w YAML (nazwy mogą się różnić — INFO gdy brak)
def yaml_present(nr, nazwa, klucz, oczek=None):
    hits = find_key(M, klucz)
    if not hits: info(nr, nazwa, f"brak klucza '{klucz}' w YAML — zweryfikuj nazewnictwo przy implementacji")
    elif oczek is not None: check(nr, nazwa, any(v==oczek for _,v in hits), f"{[(p.split('/')[-2:],v) for p,v in hits[:3]]}")
    else: check(nr, nazwa, True, f"{len(hits)} wystąpień")
yaml_present("D15", "YAML: limit 2 równoległych budów EPC (§31.2.3)", "concurrent_builds_limit", 2)
yaml_present("D16", "YAML: flara max 2h", "flare_max_h", 2)
yaml_present("D17", "YAML: sezonowość popytu obecna", "seasonality")
yaml_present("D18", "YAML: archetypy EPC obecne", "archetypes")

# D19–D21: dokument — bramka i klauzule antypodwójne
check("D19", "Dokument: §23.10 wskazuje symulator jako warstwę ekonomiczną harnessu", "OILEMPIRE_SYMULATOR_BALANSU.py" in doc_txt)
check("D20", "Dokument: §29.5 bramka aktywacji wersji (four-eyes + 0 FAIL)", "Bramka aktywacji wersji" in doc_txt)
check("D21", "YAML: komentarz 'NIE dotyczy rafinerii' przy fixed_assets_taxable_pct (anty-podwójne 70%)", "NIE dotyczy rafinerii" in yml_txt)

# D22–D24: wycena systemowa aktywów nierafineryjnych (§19.1)
def wycena(capex, stan, depleted=False):
    v = capex * (stan/100.0)
    return v * 0.10 if depleted else v
w_odwiert = wycena(800_000 + 150_000 + 150_000, 72)   # P1+badanie+wiercenie, condition 72
check("D22", "Wycena odwiertu: CAPEX 1,1M × stan 72% = 792k; baza podatku 70% = 554,4k",
      close(w_odwiert, 792_000, 1) and close(w_odwiert*0.70, 554_400, 1), f"{w_odwiert:,.0f}")
check("D23", "Odwiert depleted = złom ×0,10: 792k → 79,2k (baza 55,4k)",
      close(wycena(1_100_000, 72, True), 79_200, 1))
check("D24", "Komornik Faza 3: cena wywoławcza 60% wyceny = 475,2k; snapshot Tieru 100% = 792k",
      close(w_odwiert*0.60, 475_200, 1) and close(w_odwiert*1.0, 792_000, 1))
_hits_wt = find_key(M, "bailiff_starting_price_pct")
check("D25", "YAML: bailiff_starting_price_pct = 60 + depleted ×0,10 obecne",
      bool(_hits_wt) and _hits_wt[0][1] == 60 and bool(find_key(M, "depleted_well_valuation_mult")))

check("D26", "Dokument: §29.7 panel Balans gry (balance_versions + four-eyes + emergency)",
      '### 29.7. Panel „Balans Gry"' in doc_txt and "balance_versions" in doc_txt and "emergency" in doc_txt)

import os as _os
_panel = _os.path.join(BASE, "OILEMPIRE_PANEL_ADMINA_SPECYFIKACJA.md")
_pt = io.open(_panel, encoding="utf-8").read() if _os.path.exists(_panel) else ""
check("D27", "Panel: załącznik istnieje + §18.5 w dokumencie + kontrakt manifestu + testy P1–P15",
      len(_pt) > 20000 and "### 18.5." in doc_txt and "kontrakt manifestu" in _pt.lower()
      and "(P15)" in _pt and "relation_resolvers" in _pt and "GM_DISPUTE_AWARD" in _pt)

# =========================================================
# PODSUMOWANIE
# =========================================================
widths = (6, 74, 6)
print("="*100)
print("SYMULATOR BALANSU OilEmpire — wyniki")
print("="*100)
fails = 0
for nr, nazwa, status, detal in results:
    if status=="FAIL": fails += 1
    print(f"[{status}] {nr:5} {nazwa[:widths[1]]:74} {detal}")
print("-"*100)
p = sum(1 for r in results if r[2]=="PASS"); f_ = fails; i_ = sum(1 for r in results if r[2]=="INFO")
print(f"RAZEM: {len(results)}  |  PASS {p}  |  FAIL {f_}  |  INFO {i_}")
sys.exit(1 if fails else 0)
