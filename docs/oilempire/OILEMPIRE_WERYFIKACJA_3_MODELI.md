# OilEmpire — WERYFIKACJA KOŃCOWA: 3 modele (GPT / DeepSeek / Qwen) + synteza

Data: 09.11.2026. Przegląd ślepy kompletnej specyfikacji §1–§30 (po Etapach 1–3 poprawek).
Modele: **GPT (gpt-6-sol)**, **DeepSeek v4 Pro (reasoning: high)**, **Qwen3.8-Max (reasoning: high)** — każdy bez dostępu do ustaleń pozostałych.
Pełne recenzje: Część I (GPT), Część II (DeepSeek), Część III (Qwen) poniżej.

---

# CZĘŚĆ 0 — SYNTEZA (Hermes)

## Werdykty

| Model | Werdykt |
|---|---|
| **GPT** | Dokument NIE jest gotowy do implementacji całej gospodarki. Szkielet modułów, UI, modele, część HR/Extraction — można. Nie kodować jeszcze: księgi FTS (błąd księgowania GBBL→PLN), egzekucji, B2B (sprzeczne reguły), ticka produkcyjnego (nieodtwarzalna granica ticka). |
| **DeepSeek** | Dokument w dużym stopniu kompletny; **można zacząć implementację** po rozstrzygnięciu 6 blokerów (A1–A6). Zabezpieczenia (tarcza 10M, 5/7dni, Kroplówka per Tier, escrow 7 dni, podatek, dormant) **działają zgodnie z intencją** — luki są optymalizacyjne, nie wywracają gospodarki. Najpoważniejszy problem: brak mechaniki ZAKUPU ochrony przeciwsabotażowej. |
| **Qwen** | Nie gotowy jako całość; gotowe obszary: FTS/plan kont, płatności+GBBL, szkielet ticka, auth, CMS/Privacy, macierz GM, retencja. **Nie kodować:** Logistyki (sekcja NIE ISTNIEJE), Market fazy 3 (brak modelu ceny), HR dispute chain (brak modelu morale), Banking egzekucja+podatek (brak wyceny aktywów). |

**Konsensu:** fundament techniczny (tick, FTS, płatności, auth, GM, operacje) jest implementowalny dziś. Kodowanie domenowe blokują 4 dziury: **Logistyka, model ceny, model morale, wycena aktywów** + niedomknięte stany escrow/dormant i sprzeczności mediator/tarcza.

## Już naprawione (8 mikropoprawek wdrożonych od razu po recenzjach)

1. §17.4 — **odwrócony mnożnik `procedure_integrity`** (zużyte procedury ZMNIEJSZAŁY ryzyko — błąd krytyczny) → `(2 − integrity/100)`.
2. §23.1 — `runOnOneServer()` → poprawne Laravel `onOneServer()`.
3. §19.3 — regeneracja ceny: liniowo 10% **pierwotnego** spadku/10 min (poprzednio matematycznie nie domykała się do 100 min).
4. §24.1 — usunięta błędna konwersja „$2M → 10M PLN" (przy kursie 4,00 to 8M); próg tarczy jest wartością autonomiczną.
5. §19.2 — wycena pakietu startowego: 20 000 bbl to (prawdopodobnie) **pojemność, nie ropa** — oznaczone jako decyzja produktowa, usunięte „~8,96M".
6. §23.7 — test (7) nie odwołuje się już do nieistniejących „sabotaży za GBBL".
7. §19.1 — depletion ujednolicony z addytywnym wzorem §9.2 (−1,5 p.p./10k bbl).
8. (przy 4–5) doprecyzowane okno kroczące limitu sabotaży.

## FINALNA LISTA PRIORYTETÓW (po scaleniu trzech recenzji)

### (a) BLOKERY KODOWANIA — przed pierwszą linią kodu domenowego

| # | Problem | Sekcje | Źródło |
|---|---|---|---|
| a1 | **Napisać sekcję LOGISTYKA** (huby: parametry/bufory/przepustowość/turbo; rurociągi: koszt/czas/tiery; cysterny: pojemność/prędkość/koszt kursu/zakup; routing; wycieki; staffing) — moduł i faza 2 ticka istnieją bez specyfikacji | nowa §, §8, §23.2 | Qwen A1, GPT |
| a2 | **Funkcja wyceny aktywów i majątku netto** — JEDEN wzór sterujący tarczą, Tierami, podatkiem, aukcją 60%, odnowieniem 5%, bankructwem; wycena pakietu startowego (pojemność vs ropa); snapshot vs stała | §17.2, §19.1, §19.2, §19.4, §22.6 | wszyscy |
| a3 | **Model ceny spot**: postać funkcyjna, kotwica mean-reversion, modyfikatory wydarzeń, **slippage buy-side + spread MM** (inaczej arbitraż na deterministycznej regeneracji drenuje SYS_MARKET_MAKER), decyzja czy gracz w ogóle KUPUJE na spocie | §12.3, §19.3 | Qwen A4/3.3, GPT, DS C7 |
| a4 | **B2B domknięcie**: struktura oferty (kierunek, transze, dostawa timer vs fizyczna, prowizja bazowa — §26.3 obiecuje zwolnienie z niezdefiniowanej prowizji!), stan `blocked_pending_review`, **twardy max escrow lock 7 dni od `accepted` obejmujący `disputed`**, `delivery_receipt` w trakcie sporu → `settled` zamiast auto-`refunded` | §13.2, §21, §26.3 | wszyscy |
| a5 | **Kredyty/egzekucja**: jawna formuła `credit_score` (wagi), typ amortyzacji rat, kolejność spłaty wielu kredytów, progi przejść Faza 1→2→3, **kolejność dormant → late → komornik → bankructwo** (dziś dwie ścieżki bez rozstrzygnięcia), definicja płynności (=0 vs <48h OPEX) | §17, §19.2 | wszyscy |
| a6 | **Model morale**: skala, wartość startowa, źródła delt, relacja do `salary_satisfaction` — cały łańcuch dispute→strike wisi na niezdefiniowanej zmiennej | §4, §5 | Qwen A3 |
| a7 | **Transfer PLN_CASH ↔ PLN_BANK** (wpłata/wypłata: istnieje? koszt? czas?) + fallback raty kredytu na gotówkę — bez tego gracz z pełnym cashem i pustym bankiem dostaje `late` bez drogi naprawy | §24.2, §17.1 | Qwen A5 |
| a8 | **Zakup ochrony (§7)**: model `protections` — koszt, czas trwania (24/72/168h?), limit na zasób, UI w `/legal`; rozdzielić P(sukces operacji) od P(wpadki/detekcji) — kolumna „ryzyko wpadki − ochrona" myśli dwa mechanizmy | §7 | DS A1 (nazwał to największą pojedynczą dziurą), GPT |
| a9 | **Pozwolenia P1/P2 + łapówki**: koszt/czas/odmowa/odwołanie, bazowe sloty koncesyjne (premium +2 — wobec jakiej bazy?), mechanika łapówek (co przekupujesz, ryzyko wpadki) albo jawne scope-out z v1 | §8.2, §9.4, §2 | Qwen A6 |
| a10 | **Starter asset kit pod FTUE**: pakiet nie zawiera odwiertu/cysterny/koncesji, a tutorial obiecuje „pierwszy odwiert→cysterna→sprzedaż" w 30 min — fizycznie niewykonalne; zdefiniować prezbudowane aktywa lub przeprojektować FTUE | §1, §9.4, §27.1 | Qwen A7 |
| a11 | **Aukcje i przetargi**: typ, czas, postąpienie, zachowanie NPC-banku, rozliczenie, brak licytantów — dotyczy Fazy 3 komornika, aukcji dormant i 30% slotów Frontier; generator kontraktów systemowych (kto/tworzy/wybiera) | §17.2, §19.4, §13.1 | Qwen A10/A11 |
| a12 | **Tick — doprecyzowanie**: polityka catch-up (cap Δt np. 3600s + kolaps rzutów dyskretnych po długiej awarii: replay vs skumulowany rzut), transakcje dla faz GLOBALNYCH (3 i 8 — matching przy globalnej cenie nie zmieści się w schemacie per-player), globalny porządek blokad wszystkich typów zasobów, idempotencja także na enqueue, `secret_id` przy rotacji SERVER_SECRET | §23.3–23.5, §29 | Qwen A11/4.1–4.4, GPT, DS D2 |
| a13 | **Księgowość wielowalutowa**: jedna pozycja FTS nie może debetować GBBL i kredytować PLN — oddzielne zbilansowane księgi per waluta + niezmienny `conversion_id`; dekretacje dla zakupu/wydania/konwersji/refundu/korekty GM; **webhooki per driver** (P24: SHA-384 + obowiązkowe `transaction/verify`; PayU: `OpenPayU-Signature` z algorytmem z nagłówka — wspólny kontrakt „HMAC-SHA256 dla wszystkich" jest błędny); świadczenie dopiero po potwierdzonym statusie | §22.1, §22.5, §24 | GPT (z dokumentacją operatorów) |
| a14 | **Partycjonowanie MySQL vs unikalność**: kolumna partycjonowania musi być w każdym kluczu unikalnym — `idempotency_key` FTS wymaga osobnej niepartycjonowanej tabeli kluczy; indeksy pod zapytania scoringu przez wiele partycji | §29.4 | GPT, DS D3 |
| a15 | **Mapa modułów**: Contracts/Finance/Monetization/Simulation brak w drzewie §8 (są w §8.2) — struktura repo niegenerowalna bez zgadywania; nazwy legacy (`bank_trust_scores` itd.) używane w tekście normatywnym mimo migracji §24.4 — ujednolicić PRZED modelami | §8, §8.2, §24.4 | Qwen A14/1.3, DS 1.3, GPT |

### (b) DECYZJE PRODUKTOWE

1. **Pakiet startowy:** 20 000 bbl = pojemność czy ropa? (zmienia wycenę tarczy/Tierów) — wszyscy.
2. **Mediator:** trzy różne ceny tej samej usługi (50k §5.1 / 10% min 25k §19.2 / debt 25k) + BRAK GÓRNEGO CAPU (10% ze 100M = 10M) + rozjazd ręczny vs automat — ujednolicić — Qwen 1.2, GPT.
3. **Limit 5/7dni jako tarcza immunitetowa:** „przyjazny" gracz wykonuje 5 tanich kradzieży i ofiara ma tygodniowy immunitet na wszystko (~75k PLN za immunitet). Fix: próg szkody / sublimit per agresor / wyłączenie kont sprzymierzonych — Qwen 3.2, GPT (okno kroczące + liczenie prób, nie tylko sukcesów; atomowe zdjęcie tarczy PRZED autoryzacją).
4. **Cykl dormant→tarcza powracającego:** urlop 30d → powrót 7d → dormant → powrót 7d tarczy... ~80–90% uptime z immunitetem. Fix: ochrona powracającego raz na 180 dni + ograniczenie handlu w jej trakcie — Qwen 3.4.
5. **Self-rescue w dormant:** czy gracz może sprzedać ropę z magazynu, by się obudzić? (rekomendacja Qwen: tak, 50% wpływów na zaległości) — inaczej wymuszony pay-to-unlock Kroplówką.
6. **Kara za anulowanie B2B 5%** umożliwia arbitraż (spadek ceny >5% → opłaca się zerwać i kupić taniej na spocie): podnieść do 10–15% lub uzależnić od kierunku ceny — DS B2.
7. **Kartel jako pralnia GBBL:** Kroplówka → ropa spot → B2B bez prowizji w kartelu → wypłata (omija limit Tieru drugiego gracza). Fix: limit wolumenu wewnątrz-kartelowego/dobę lub prowizja powyżej progu — DS 3.8/B3.
8. **Kartele 40%:** okno pomiaru, skutek przekroczenia organicznego (nie tylko blokada rekrutacji), wiele skoordynowanych karteli — Qwen 3.7, GPT.
9. **Podatek:** ropa w magazynie i w transporcie poza podstawą (parking ~6M PLN w 25k bbl); aktywa brutto bez odliczenia zobowiązań = podwójne karanie lewaru; aktywa w budowie? — Qwen 3.6, GPT b1.
10. **GBBL-mitygacja w egzekucji:** płacący gracz w Fazy 2–3 gasi blowouty i naprawia bez cashu — recovery_mode jest dla niego łagodniejsze. Zapisać jawnie jako AKCEPTOWANĄ resztę P2W albo ograniczyć — Qwen 3.8/B5.
11. **Kroplówka w Fazie 1:** blokada dopiero od Fazy 2, a Faza 1 zajmuje 50% wpływów — rozstrzygnąć czy konwersja podlega zajęciu i czy limit GBBL zużywa się w całości; Tier snapshot na początek doby (race przy zmianie tieru) — GPT, DS 3.3.
12. **Ban a zegar aukcji dormant (90 dni):** biegnie czy pauzuje — DS B4.
13. **ToS ≠ zgody cookies:** osobna wersja, osobny rekord akceptacji, blokada przy braku akceptacji — GPT.
14. **2FA +5:** zaimplementować jako osobny modyfikator wyświetlanej wiarygodności (nie delta bazowej — „nie niżej niż przed bonusem" jest niewykonalne po późniejszych zmianach) — GPT.
15. **Black market +2/transakcję bez względu na wolumen:** batching trywializuje ryzyko (1 transakcja/dobę = score ~2–4, nigdy nie przekroczy 30). Fix: skalowanie wolumenem — Qwen 3.9.
16. **„Wrogie kontrole skarbowe ze strony graczy"** (§19.2) — mechanika nie istnieje: dopisać albo usunąć zapis — Qwen 1.8.
17. **Wydarzenia GM „natychmiast"** vs obietnica „24h zapowiedzi" w §21 — spójna reguła wyjątków — Qwen 1.9.
18. **Step-up „przelew >100k":** zdefiniować jako każdą inicjowaną przez gracza operację FTS >100k (poza automatycznymi OPEX/pensje/raty); konta Google bez hasła — ścieżka TOTP/ponowny OAuth — DS A5, GPT.
19. **Pożyczka rozruchowa 500k dla kont bez pakietu:** warunki (oprocentowanie, okres, egzekucja) + czy takie konto ma magazyn i wiarygodność 70 — Qwen 1.11/B9.
20. **Wakaty dyrektorów innych niż CFO** (Tech przy TTS, Legal przy sabotażach/convoy, HR) — tryby awaryjne albo blokada — Qwen B10.
21. **Limit spot 20 000 PLN/h w trybie awaryjnym** ≈ 80 bbl/h — zbyt dotkliwy dla firmy 300+ bbl/h? — Qwen B7.
22. **Zakres językowy v1** (80+ dialogów × 3 języki) — czy EN/DE jadą w Etapie 2 przy fallbacku PL — Qwen B11.

### (c) KALIBRACJA `game_balance` (reguły najpierw, liczby potem — symulator headless)

1. **`initial_reserves_bbl` per stratum — TABELA NIE ISTNIEJE**, a bez niej depletion i cała ekonomia są niekalibrowalne (Qwen C1).
2. Marże: base_rate × cena vs OPEX + transport + naprawy — czy pojedynczy shallow jest rentowny; tempo dojścia do 10M/200M (GPT c1, Qwen C1).
3. base_risk przy condition<20% + tabela skutków incydentów per klasa (Qwen C3).
4. Czarny rynek: premia cenowa %, limity wolumenu, licznik „niezadeklarowanych przychodów" (Qwen C4).
5. `min_market_salary` + widełki `expected_salary` per stanowisko, koszty rekrutacji/headhuntera (Qwen C5).
6. Pojemność okna slippage, głębokość księgi NPC, spread MM (DS C7, Qwen C2).
7. Podatek vs lewar i emisja Kroplówki: scenariusz „1B w aktywach na kredyt"; czy 400 GBBL/dzień (Tier V) nie rozsadza sinków (DS C6, Qwen C7).
8. Sabotaże: czy 5 udanych w 7 dni niszczy firmę Tier I — jeśli tak, ochrona startowa musi realnie działać (DS C4).
9. `procedure_integrity`: wartość startowa, tempo degradacji, koszt audytu (Qwen C9).
10. Wagi credit_score (Qwen C10), wycena regionu pod 5% odnowienia (Qwen C8).
11. Mockup §1.1: magazyn 2 400 bbl u „dojrzałej" firmy vs starter 20 000; przychód 1 346 PLN/h przy 333 bbl/h × 248 PLN/bbl ≈ 82 600 PLN/h — poprawić liczby demo (wzorzec dla frontendu) (Qwen 1.3/1.4, C11).

### (d) W TRAKCIE IMPLEMENTACJI (z testami jako warunkiem odbioru)

1. **Headless symulator jako PIERWSZY deliverable** z profilem 1000 graczy (brama release od Etapu 1, nie „później"); scenariusze §23.7 rozszerzyć o: dwóch schedulerów naraz, catch-up 12h, arbitraż MM, freeze fazy 5.
2. Testy równoległe: dwóch graczy + magazyn + escrow + egzekucja; awaria po każdym commicie fazy; powtórzenie joba po zmianie `balance_version`.
3. `balance_version` w heartbeats od pierwszej migracji; telemetria faucets/sinks instrumentowana RÓWNO z mechanikami.
4. Runbook: freeze fazy 5 (ile świat działa bez OPEX/rat/podatków; `late` od terminu raty czy od przebiegu fazy?), miesięczny test odtworzenia backupu, rekoncyliacja płatności z operatorami po DR (RPO 5 min vs obietnica zero-wipe — rollback bazy nie cofnie chargebacka).
5. HIBP: zachowanie przy niedostępności (fail-open z flagą + re-weryfikacja, lista lokalna jako fallback).
6. Fingerprint: wybór biblioteki (FingerprintJS vs custom, client-side vs TLS) — bez tego moduł anty-multikonto nie ruszy.
7. Chart.js: brak natywnych świec — plugin financial-chart lub lightweight-charts (jeśli świece potrzebne).
8. Konta systemowe (SYS_*) jako gorące wiersze: batchowanie UPDATE'ów, monitoring.
9. Migracja nazw legacy PRZED utworzeniem modeli; konwencja enumów i lista wszystkich maszyn stanów z przejściami.
10. Content pipeline równolegle: 80+ dialogów ×3, ~60 osiągnięć, tutorial, strony CMS/ToS.
11. ID wymagań {DOMENA}-{NR} nadawać od razu przy przepisywaniu punktów a/b/c do trackera.

## Rozbieżności między modelami (rozstrzygnąć samemu)

- **Gotowość dokumentu:** DS „można startować po 6 blokerach" vs GPT/Qwen „nie kodować gospodarki/B2B/ticka produkcyjnego". Prawda: szkielet tak, domena po (a).
- **Starter 20k bbl:** DS/Qwen liczą jak przyznaną ropę (~8,96M), GPT czyta jako pojemność (4M) — stąd rozjazd ocen tarczy. To decyzja produktowa (b1).
- **Tarcza 10M wg DS** „trzyma ~42 dni przy 1 odwiercie shallow, więc 7-dniowa wygasa pierwsza" — po rozstrzygnięciu (b1) przeliczyć.

---

# CZĘŚĆ I — PEŁNA RECENZJA: GPT (gpt-6-sol)

Werdykt: dokument nie jest jeszcze gotowy do implementacji całej gospodarki. Da się rozpocząć szkielet modułów, interfejs, modele i część mechanik HR/Extraction. Nie należy jeszcze kodować księgi FTS, egzekucji, B2B ani produkcyjnego ticka według obecnego opisu: w tych obszarach programista musiałby sam ustalić reguły, które zmieniają wynik gry. Najpoważniejsze nowe błędy to błędny model księgowania GBBL→PLN, sprzeczne reguły escrow i niemożliwa do odtworzenia granica ticka.

1. Niespójności wprowadzone przez poprawki

• §17.4: `procedure_integrity/100` jest mnożnikiem ryzyka. Spadek integralności z 100 do 0 zmniejsza ryzyko aż do zera. Zużyta procedura BHP staje się doskonałą ochroną. Trzeba odwrócić kierunek wpływu i ustalić dolną/górną granicę ryzyka.

• §24.2–24.3 i §22.5: jedna pozycja FTS ma jedną kwotę w groszach, a Kroplówka ma jednocześnie obciążyć konto GBBL i uznać konto PLN. To nie jest poprawny podwójny zapis w jednej jednostce. Potrzebne są oddzielne, zbilansowane księgi per waluta, powiązane niezmiennym identyfikatorem konwersji; emisja PLN musi mieć własną parę kont PLN. Nie wolno wpisywać „równowartości” GBBL jako groszy.

• §19.2, §20, §22.6: Tier i próg tarczy liczy się od „majątku netto z wyłączeniem pakietu startowego”, ale nie określono, czy odejmuje się początkowe salda nominalne, wartość otrzymanych aktywów, czy historyczną wartość pakietu po jego wydaniu. Wartość pakietu „~8,96M” nie wynika z samego §1: 4M PLN jest gotówką, a 20 000 bbl to pojemność magazynu, nie przyznana ropa. Liczba 8,96M odpowiada wycenie 20 000 bbl po 62 USD × 4 PLN, czyli wycenia pustą pojemność jak pełny magazyn. Bez definicji wyceny tarcza i Tiery będą działać inaczej, niż sugeruje dokument. 

• §13.2 i §21: limit 7 dni dotyczy oferty `published`, kiedy środki i ropa nie są jeszcze w escrow. Nie ogranicza czasu `accepted`, `in_delivery` ani `disputed`. Zdanie „escrow nie może być schronem majątkowym” jest więc nieprawdziwe.

• §13.2: „kupujący wycofuje ofertę tylko w `published`”, ale „po `accepted` anulowanie = kara 5%”. Brakuje aktora, przejścia stanów, źródła kary i reguły zwolnienia obu blokad. Ropa „wraca do sprzedawcy” po `refunded` nawet wtedy, gdy mogła już fizycznie opuścić jego magazyn.

• §23.2: faza 8 uruchamia terminy, zdarzenia i rozstrzyganie sabotaży, lecz §23.2 mówi też, że fazy 6–8 zmieniają wyłącznie wejścia następnego ticka. Sabotaż może natychmiast odebrać ropę lub pieniądze. §25 uruchamia wydarzenia w fazie 8, a §21 mówi o kursie obowiązującym „od chwili zmiany”; należy jawnie określić, że pierwszy dotknięty matching następuje w kolejnym ticku.

• §23.3 i §29.1: wznowienie fazy `in_progress` z `last_calc_at` nie wystarcza do dokładnego replayu. Po częściowym zatwierdzeniu transakcji zmienią się stan wejściowy, zbiór bytów i czas `now`; nie można jednocześnie obiecać replayu identycznego ticka i naliczenia całego czasu przestoju w tym samym ticku.

• §19.3: „10% wartości spadku co 10 minut” nie daje pełnej regeneracji po ~100 minutach, jeżeli jest procentem pozostałego spadku. Po dziesięciu takich krokach pozostaje około 35% początkowego spadku. Potrzebna jest funkcja powrotu, nie opis słowny. 

• §24.3, §22.3: Kroplówka ma dawać `PLN_CASH`, podczas gdy §24.2 opisuje ją także przy `PLN_BANK`. W egzekucji Fazy 1 zajmowane jest 50% „każdego wpływu” na oba konta; §22.3 blokuje Kroplówkę dopiero od Fazy 2. Trzeba rozstrzygnąć, czy połowa konwersji w Fazie 1 idzie wierzycielowi i czy limit GBBL zużywa się w całości.

• §5.1 i §19.2: mediator strajku HR kosztuje 50 000 PLN, a mediator automatyczny 10% rezerwy, minimum 25 000 PLN. Może to być celowo odrębna usługa, ale dokument nazywa obie „Zewnętrznym Mediatorem Rządowym” i odsyła do tej samej mechaniki bez rozróżnienia skutków.

• §7.1: kolumna „bazowe ryzyko wpadki” odejmuje ochronę, podczas gdy `drone_patrol` ma dodać 15 p.p. ryzyka wpadki. Ochrona nie może zarazem zmniejszać szansy wykrycia agresora i chronić ofiary; trzeba rozdzielić prawdopodobieństwo powodzenia, wykrycia oraz wielkość szkody.

• §14.3 i §2.2: „każda zmiana” wiarygodności propaguje 50% do B2B, ale bonus za 2FA ma nie propagować. Po wyłączeniu 2FA „odjęcie 5, nie niżej niż stan sprzed bonusu” jest niewykonalne po późniejszych zmianach wiarygodności bez osobnego składnika bonusowego. To powinien być odrębny modyfikator wyświetlanej wiarygodności, nie zwykła delta bazowej reputacji.

• §22.1 i §22.5: wspólny kontrakt „wszystkie webhooki HMAC-SHA256” jest błędny dla nazwanych operatorów. P24 opisuje podpis SHA-384 i obowiązkowe `transaction/verify`; PayU Europe dokumentuje `OpenPayU-Signature` z algorytmem wskazanym w nagłówku, w przykładzie MD5. Weryfikacja musi być specyficzna dla drivera, a świadczenie wydane dopiero po potwierdzonym statusie płatności. ([developers.przelewy24.pl](https://developers.przelewy24.pl/?utm_source=openai))

• §30.1 i §16: regulamin ToS jest utożsamiony z wersją polityki cookies i jej banerem. Akceptacja regulaminu nie jest zgodą na analitykę; potrzebuje osobnej wersji, rekordu akceptacji i blokady dalszego korzystania, jeżeli wymagane warunki nie są przyjęte.

• §24.4 pozostawia wiele nazw legacy w §2, §8, §13, §17 i §18. To nie tylko kosmetyka: trzeba wskazać jedną nazwę fizycznej kolumny i migrację, zanim powstaną modele oraz zapytania.

2. Luki blokujące kodowanie

• §23, §9, §17: brakuje jednej specyfikacji czasu symulacji: czy losowanie „na tick” zachodzi raz na rzeczywisty tick czy za każdą zaległą minutę, jak naliczyć produkcję i ryzyko po 15 minutach przestoju, kiedy zaokrągla się ułamki bbl/groszy i jaki stan zamyka tick. Bez tego wynik zależy od awarii serwera.

• §24, §17, §19: brak definicji majątku netto i wyceny aktywów. Nie określono wartości odwiertu z wyczerpującym się złożem, ropy w buforze i transporcie, rezerwacji, długu, premii infrastrukturalnej ani daty wyceny. Ten jeden wynik steruje tarczą, Tierem, podatkiem, kredytem, sabotażem, bankructwem i aukcją.

• §13 i §21: brak kompletnej tabeli przejść B2B dla stron, transz, częściowego rozładunku, pełnego magazynu, timeoutu, sporu GM, zwrotu, utraty ładunku oraz bankructwa. Każde przejście musi wskazać właściciela ropy, właściciela pieniędzy, fizyczną lokalizację, rezerwacje i wpisy księgi.

• §17.1–17.3: nie ma algorytmu rat, naliczania odsetek i zaległości, kolejności spłaty przy wielu kredytach, zasad wejścia z Fazy 1 do 3, progu zakończenia egzekucji ani definicji płynności w bankructwie. Plan naprawczy pozostaje arbitralną decyzją „systemu lub GM”.

• §9–§11 i §19.4: nie podano zasobności i generowania złóż, liczby działek/koncesji, kosztów badań, bazowego zużycia per tier, relacji `condition` do `wear_level`, kosztów napraw i upgrade’ów, kosztów utrzymania infrastruktury ani przepustowości transportu. Sama tabela `base_rate` nie tworzy grywalnego modelu wydobycia.

• §19.3, §12, §25: brakuje równania ceny bazowej, pojemności okna, kolejności zleceń przy zmianie ceny, maksymalnych/minimalnych cen i sposobu naliczania slippage na kolejnych baryłkach. „Skumulowany wolumen wszystkich zleceń” wymaga też decyzji, czy jedna firma może obniżyć cenę skupu wszystkim pozostałym.

• §7: nie ma tabeli prawdopodobieństw sukcesu, działania ochrony na wynik ani zasad rozliczenia opłaty, łupu i szkody przy niepowodzeniu. Limit 5/7 dni nie wskazuje, czy okno jest kroczące, czy liczy sukcesy w chwili zgłoszenia czy wykonania.

3. Czy zabezpieczenia faktycznie działają?

• Tarcza 10M (§19.2): nie da się jej sprawdzić bez definicji pakietu i wyceny. Co więcej, gracz może rozdzielić majątek między konta i korzystać z ponownie przyznanej tarczy powracającego (§19.4). „Pierwsza próba” agresji wymaga atomowego zdjęcia tarczy PRZED autoryzacją operacji, inaczej równoległe żądania pozwolą zaatakować pod ochroną.

• Limit 5/7 dni (§7.1): ogranicza tylko udane operacje jednej ofiary, nie liczbę prób. Wielu agresorów może zasypać ją nieudanymi operacjami, a ataki zgłoszone przed piątym sukcesem wymagają zablokowania wspólnego licznika przy wykonaniu. Kara za „uporczywe nękanie” dopiero po trzech cyklach nie chroni przed szkodą w pierwszych dwóch. Dodatkowo limit na firmę można obchodzić zmianą konta-ofiary, jeśli aktywa da się przenieść.

• Kroplówka per Tier (§22.6, §24.3): Tier wyznaczany przez majątek, który sama Kroplówka powiększa, tworzy próg eskalacji. Zdefiniować snapshot Tieru na początek doby, liczenie limitu przy równoległych żądaniach oraz wyłączenie emisji GBBL→PLN z podstawy progresji Tieru, jeśli intencją jest limit P2W. Zakaz „transferu gotówki z Kroplówki” nie działa bez śledzenia pochodzenia środków: PLN są zamienne, można za nie kupić ropę, aktywa lub sfinansować B2B. Nie obiecywać technicznej blokady transferu konkretnych PLN, jeśli system nie prowadzi osobnych sald/ograniczeń.

• Escrow (§13, §21): rzeczywiste aktywne depozyty nie mają limitu 7 dni. Konto kupującego może zablokować środki przez serię sporów; środki są opodatkowane, ale wyłączone z egzekucji. Trzeba wprowadzić maksymalny czas sporu, automatyczne rozstrzygnięcie i regułę zajęcia środków po ich zwrocie, zamiast nazywać ten wektor zamkniętym.

• Podatek (§19.1): obejmuje cash, bank i escrow, ale nie wycenioną ropę w magazynie, buforze i transporcie ani wierzytelności B2B. Przesunięcie kapitału w ropę jest prostym obejściem. Wpływ do `SYS_TAX` jest sinkiem tylko wtedy, gdy system nie wydaje go z powrotem; dokument równocześnie używa skarbu jako źródła emisji.

• Dormant 14 dni (§19.2, §30): próg „cash+bank = 0” można omijać utrzymywaniem minimalnego dodatniego salda albo celowo osiągać, by zatrzymać produkcję i nowe incydenty. Status po banie daje ochronę, lecz nie mówi, czy zegar 90-dniowej aukcji biegnie podczas bana lub odwołania. Urlop zamraża także terminy i cooldowny, więc wymaga zamrożenia odrębnych zegarów procesów, nie tylko produkcji.

• Kartel 40% (§26): blokada rekrutacji dopiero „powyżej” progu nie rozwiązuje przekroczenia po wzroście produkcji; 2–5 firm może utrzymywać kilka skoordynowanych karteli. Trzeba zdefiniować mianownik (chwilowy czy średnia), moment kontroli, wejście/wyjście członka i sankcję po przekroczeniu. Bez prowizji B2B wewnątrz kartelu obniża koszt przesuwania wartości między powiązanymi kontami.

4. Pozostałe ryzyka techniczne

• §23.1: w Laravel 11 metoda schedulera nazywa się `onOneServer()`, nie `runOnOneServer()`. Wymaga wspólnego centralnego cache dla instancji; sam scheduler nie jest blokadą transakcyjną świata. ([laravel.com](https://laravel.com/framework/docs/11.x/scheduling?utm_source=openai))

• §23.5: „jedna transakcja na gracza na fazę” koliduje z operacją B2B obejmującą dwóch graczy, escrow i magazyny oraz z globalnym matchingiem. Sam porządek `player_id` nie eliminuje deadlocków między żądaniami HTTP, GM i jobami. Potrzebny jest globalny porządek blokad wszystkich typów zasobów, ograniczone retry i testy konkurencyjne.

• §29.4: roczne partycjonowanie MySQL po `created_at` wymaga, by kolumna partycjonowania należała do każdego klucza unikalnego, w tym głównego. To może zepsuć globalną unikalność `idempotency_key` FTS; potrzebna jest osobna niepartycjonowana tabela kluczy albo inna strategia archiwizacji. ([dev.mysql.com](https://dev.mysql.com/doc/refman/8.0/en/partitioning-limitations-partitioning-keys-unique-keys.html?utm_source=openai))

• §23.3–23.4: sam seed ticka nie gwarantuje replayu, gdy zmieni się kolejność iteracji, wersja balansu, liczba losowań albo stan po częściowym commicie. Zapis losowania powinien wiązać wersję algorytmu, identyfikator zdarzenia i niezmienny kontekst; „anty-reroll tego samego slotu” wymaga identyfikatora slotu, którego specyfikacja nie definiuje.

• §29.1–29.2: snapshot i binlog z RPO ≤5 min nie są zgodne z bezwarunkową obietnicą „zero wipe”, jeśli utrata pięciu minut opłaconych transakcji jest dopuszczalna. Trzeba określić rekoncyliację płatności z operatorem i ponowne odtworzenie świadczeń po DR; rollback bazy nie może cofać realnego chargebacku ani wykonanej płatności.

• §14.5: „hash tokenu sesji” w `trusted_devices` nie określa mechanizmu unieważniania rzeczywistej sesji Laravel. Dla kont Google bez hasła step-up musi mieć ścieżkę TOTP lub ponownego OAuth; dla kont bez włączonego 2FA dokument nie podaje, jak zatwierdzić wrażliwą operację. Standardowe wylogowywanie innych urządzeń w Laravel wymaga określonego middleware i uwierzytelnienia hasłem. ([laravel.com](https://laravel.com/framework/docs/11.x/authentication.?utm_source=openai))

5. Priorytety przed startem implementacji

(a) Blokery kodowania — zamknąć przed implementacją domeny:
1. §24, §22.5: rozdzielić księgę PLN i GBBL, spisać dekretacje dla zakupu, wydania, konwersji, refundu i korekty GM.
2. §19.2, §17, §22.6: zatwierdzić jedną funkcję majątku netto i wyceny aktywów; poprawić wycenę pakietu startowego.
3. §23, §29.5: określić niezmienną granicę ticka, zaległy czas, replay, wersję algorytmów i zasady commitów po awarii.
4. §13, §21: spisać pełną tabelę stanów transz B2B wraz z własnością surowca, timeoutami i rozstrzyganiem sporów.
5. §17: spisać algorytm kredytu, zaległości, egzekucji, bankructwa i wychodzenia z nich.
6. §22.1: zastąpić wspólną procedurę HMAC-SHA256 kontraktami weryfikacji specyficznymi dla Stripe/P24/PayU. ([developers.przelewy24.pl](https://developers.przelewy24.pl/?utm_source=openai))

(b) Decyzje produktowe:
1. §19.1: czy ropa i należności podlegają podatkowi; czy podatek ma ograniczać płynność, czy całkowity majątek.
2. §19.2, §30: czy ban i usunięcie konta uruchamiają zegar aukcji; jak chronić prawomocnie odwołującego się gracza.
3. §22.3, §22.6: uczciwie nazwać Kroplówkę oraz płatne gaszenie blowoutu przewagami ekonomicznymi, albo usunąć je z deklaracji „brak P2W”.
4. §26: zdecydować, czy 40% ma być twardym limitem członkostwa, czy tylko sygnałem dla GM.
5. §30, §16: oddzielić akceptację ToS od zgód cookies.

(c) Kalibracja `game_balance` — dopiero po określeniu reguł:
1. §9, §10, §17: zasobność złóż, pełny OPEX, koszty transportu i napraw versus marża oraz tempo dojścia do 10M/200M.
2. §17.4: poprawiony wzór BHP, ryzyko w przeliczeniu na godzinę i koszt katastrofy.
3. §19.3: pojemność rynku, funkcja slippage i regeneracja ceny.
4. §7, §22.6: szkoda na ofiarę w siedmiu dniach, koszt obrony, skala konwersji GBBL w każdym Tierze.
5. §19.4: liczba slotów Frontier Basins i realna dostępność koncesji dla nowych firm po roku/dwóch latach.

(d) W trakcie implementacji — z testami jako warunkiem odbioru:
1. §23.5, §13, §12: testy równoległych transakcji między dwoma graczami, magazynem, escrow i egzekucją; nie tylko lista scenariuszy z §23.7.
2. §23.3, §29.1: test awarii po każdym commicie fazy oraz powtórzenia joba po zmianie wersji balansu.
3. §29.4: sprawdzić migracje i unikalność kluczy na docelowej wersji MySQL przed uruchomieniem partycjonowania.
4. §22.5, §29.2: testy rzeczywistych sandboxów operatorów i odzyskania świadczeń po odtworzeniu backupu.
5. §14, §28: test autoryzacji kanałów, step-up, unieważnienia sesji i izolacji danych między firmami.

Gotowe do rozpoczęcia, po ustaleniu zwykłych schematów danych, są układ SPA (§1, §18.2, §28), podstawowy model szkoleń (§6) oraz szkielet wydobycia (§9). Nie należy jednak traktować wpisów „wartości w `game_balance`” jako zastępstwa za brakującą regułę działania mechaniki.


---

# CZĘŚĆ II — PEŁNA RECENZJA: DeepSeek v4 Pro (reasoning high)

# RECENZJA KOŃCOWA ARCHITEKTURY OILEMPIRE — WERYFIKACJA PRZED IMPLEMENTACJĄ

---

## 1. NOWE NIESPÓJNOŚCI WPROWADZONE PRZEZ POPRAWKI

### 1.1 Krzywa wyczerpania złoża: dwie różne matematyki

**§9.2** (formuła produkcji): *„spada liniowo o 1,5 punktu procentowego bazowej wydajności za każde kolejne 10 000 bbl”* — spadek addytywny, w jednostkach p.p.

**§19.1** (opis ekonomiczny): *„wydobycie spada o 1.5% na każde kolejne 10 000 bbl”* — znak procentu sugeruje spadek multiplikatywny.

Przy odwiercie shallow (40 bbl/h), po 100 000 bbl powyżej 70%:
- Wg §9.2 (addytywnie): factor = 1.0 − 10 × 0.015 = 0.85 → 34 bbl/h
- Wg §19.1 (multiplikatywnie): factor = 0.985^10 ≈ 0.86 → 34.4 bbl/h

Różnica jest niewielka w tym przykładzie, ale narasta przy głębszych złożach. Deweloper, który przeczyta tylko §19.1, zaimplementuje inaczej niż ten, który czyta §9.2. Decyzja: addytywna (§9.2) jest kanoniczna — §19.1 do poprawy.

### 1.2 Test akceptacyjny odwołuje się do nieistniejącej mechaniki

**§23.7 test (7):** *„finansowane GBBL sabotaże vs limit 5/7 dni na ofiarę”*

W całej sekcji §7 (Sabotaże) wszystkie koszty operacji są w PLN (tabela 7.1: 15 000–60 000 PLN). Nie istnieje żadna ścieżka „kup sabotaż za GBBL" — ani w §7, ani w §22.3 (katalog usług premium), ani w §24.3 (Kroplówka). Test odwołuje się do fantomu.

**Akcja:** usunąć test (7) LUB dodać mechanikę sabotowania za GBBL (np. „Prywatny kontraktor 50 GBBL") — ale to otwiera wektor P2W, którego §22.6 wyraźnie unika.

### 1.3 Nazwy pól: liczba mnoga vs pojedyncza — niespójność między sekcjami

**§24.4** deklaruje migrację: `bank_trust_scores` → `bank_trust_score`, `b2b_reputation_scores` → `b2b_reputation_score` (oba jako pojedyncze wskaźniki 0–100).

Mimo to:
- **§2.2** używa `b2b_reputation_scores` (mnoga)
- **§13.2** używa `b2b_reputation_scores` (mnoga)
- **§18.1** używa `bank_trust_scores` (mnoga)

Jeśli migracja jest faktem, tekst dokumentu powinien używać nazw docelowych. Deweloper czytający §13.2 utworzy kolumnę w liczbie mnogiej.

### 1.4 Limity sabotaży: globalne okno vs definicja „7 dni”

**§7.1:** *„max 5 udanych sabotaży łącznie na jedną firmę w oknie 7 dni”*

Czy okno jest kroczące (ostatnie 168h od teraz), czy kalendarzowe (poniedziałek–niedziela UTC)? Tick engine w §23 używa UTC i delta-time — okno kalendarzowe byłoby niespójne. Dokument nie precyzuje.

---

## 2. LUKI BLOKUJĄCE IMPLEMENTACJĘ

### 2.1 Sposób nabycia ochrony przeciwsabotażowej — ZERO informacji

**§7** opisuje 5 typów ochrony (`basic_escort`, `armed_convoy`, `drone_patrol`, `hub_security`, `legal_defense`), ich działanie i redukcję ryzyka. NIGDZIE nie ma:
- Kosztu nabycia (PLN? GBBL? stała opłata?)
- Czasu trwania ochrony (godziny? dni? do odwołania?)
- Limitu aktywnych ochron (jedna na zasób? kumulatywne?)
- Interfejsu zakupu (która podstrona?)

Bez tego programista nie napisze ani kontrolera zakupu, ani modelu `protections`, ani logiki wygasania. To jest twardy bloker.

### 2.2 Formuła credit_score — obietnica bez ciała

**§17.1:** *„credit_score = f(company_credibility, historia spłat, zadłużenie/aktywa) — formuła i wagi w game_balance”*

To nie jest specyfikacja. Programista nie wie:
- Jak mierzyć „historię spłat" (liczba spłaconych kredytów? średnia opóźnień? czas od ostatniego `late`?)
- Jak mierzyć „zadłużenie/aktywa" (total debt / total assets? current liabilities / liquid assets? wartość rynkowa czy księgowa?)
- Jakie są wagi (np. credibility 40%, historia 35%, D/A 25% — czy coś innego?)
- Czy scoring rośnie liniowo, logarytmicznie, progami?

Zapis „w game_balance" to nie rozwiązanie — ktoś musi tę formułę wymyślić i zapisać przed implementacją.

### 2.3 „Majątek netto z wyłączeniem pakietu startowego" — algorytm

Używane w trzech miejscach:
- **§19.2** Tarcza Startowa (próg 10M PLN)
- **§22.6** Tiery firmy (I–V)
- **§17.3** Bankructwo („majątek netto < 0" — niejawnie ta sama definicja)

Problem: jak obliczyć „wyłączenie pakietu startowego" po tygodniach/miesiącach gry?

Pakiet startowy to: 2M PLN cash + 2M PLN bank + 20 000 bbl ropy. Po miesiącu oryginalna gotówka została wydana, oryginalna ropa sprzedana. Czy:

(a) Odejmujemy stałą wartość 8,96M PLN (pakiet wyceniony po cenie z chwili startu)?
(b) Śledzimy pochodzenie każdej baryłki i każdej złotówki (niercalne)?
(c) Po prostu odejmujemy `PLN_CASH + PLN_BANK + zapasy_ropy_po_cenie_rynkowej` od sumy i porównujemy z zerem? (to by znaczyło, że każdy nowy gracz ma „net worth ex-starter = 0" i tarcza trzyma do 10M — czyli zgodnie z intencją, ale nie wynika to z tekstu)

**Rekomendowany algorytm (c)**: `net_worth = PLN_CASH + PLN_BANK + inventory_bbl × spot_price + 0.7 × fixed_asset_value − total_liabilities`. `net_worth_ex_starter = net_worth − starter_package_initial_value` (gdzie starter_package_initial_value to stała wyliczona przy rejestracji). Tarcza opada gdy `net_worth_ex_starter ≥ 10M PLN`. Trzeba to zapisać wprost.

### 2.4 Podstawa podatku majątkowego: „własne środki w PLN_ESCROW"

**§19.1:** podstawa = `PLN_BANK + PLN_CASH + własne środki w PLN_ESCROW + 70% wyceny aktywów trwałych`

W escrow mogą być środki w dwóch sytuacjach:
- Jesteś **kupującym** — Twoje pieniądze są zamrożone do dostawy (to są „własne środki")
- Jesteś **sprzedawcą** — pieniądze kupującego czekają na rozliczenie (to NIE są Twoje środki)

Specyfikacja nie rozróżnia. Bez tego podatek albo opodatkuje cudze pieniądze (błąd), albo pominie własne zamrożone (niedomiar).

### 2.5 Step-up auth: co to jest „przelew"?

**§14.5:** *„przelew powyżej 100 000 PLN"* wymaga ponownego uwierzytelnienia.

W grze nie ma przelewów między graczami (GBBL niezbywalne, gotówka brak transferów P2P). Co więc jest „przelewem"?
- Każda transakcja FTS obciążająca konto gracza powyżej 100k? (zakup odwiertu za 800k → step-up)
- Tylko operacje na koncie bankowym?
- Zakup GBBL?

Bez definicji system nie wie, kiedy żądać hasła.

### 2.6 B2B: kolizja timeoutu z rzeczywistą dostawą

**§13.2:** po timeout → `disputed` → 24h bez reakcji sprzedawcy → `refunded` (automatycznie).

A jeśli w tych 24h sprzedawca faktycznie DOSTARCZY ropę (protokół rozładunku w hubie kupującego)? System ma automatycznie zwrócić pieniądze mimo fizycznej dostawy? Specyfikacja nie opisuje interakcji `delivery_receipt` ze stanem `disputed`.

---

## 3. WEKTORY EKSPLOATACJI I PROBLEMY BALANSU

### 3.1 Sprawdzenie zabezpieczeń — Tarcza 10M bez pakietu

**Działa?** Tak, ale z zastrzeżeniem. Nowy gracz z 8,96M PLN pakietu ma net-worth-ex-starter = 0 (przy algorytmie z 2.3c). Tarcza trzyma do 10M, czyli do czasu gdy gracz realnie zarobi ~10M. Przy jednym płytkim odwiercie (40 bbl/h × 248 PLN/bbl = ~10k PLN/h) to ~1000h (~42 dni) — tarcza czasowa (7 dni) wygasa pierwsza. **Ochrona działa zgodnie z intencją.**

### 3.2 Sprawdzenie — limity sabotaży 5/7 dni

**Działa?** Tak, ale z luką koordynacyjną. 5 sabotaży/tydzień/firmę jest twardym limitem. Ale:

**Luka 1 — karuzela agresorów:** Gracz A i Gracz B (współpracujący) mogą atakować Cel X naprzemiennie przez wiele tygodni. §30.3 flaguje to po ≥3 cyklach — ale tylko jako „możliwa sankcja moderacyjna", nie automatyczna blokada. W praktyce przez 3 tygodnie (15+ sabotaży) nikt nie zareaguje.

**Luka 2 — pomiar okna:** jeśli okno jest kalendarzowe, atak w niedzielę 23:50 i poniedziałek 00:10 daje 10 sabotaży w ciągu 20 minut (po 5 w każdym oknie). Jeśli kroczące — OK. (patrz 1.4).

### 3.3 Sprawdzenie — Kroplówka per Tier

**Działa?** Limity dzienne wg tieru — poprawne. Blokada w `recovery_mode` i od Fazy 2 egzekucji — poprawna.

**Luka:** Zmiana tieru w dół obcina limit „od następnego dnia" (§22.6). Gracz Tier III (100 GBBL/dzień) na kilka minut przed 00:00 UTC wymienia 100 GBBL, po czym spada do Tier II. O 00:00 UTC dostaje nowy limit 50 GBBL — efektywnie 150 GBBL w ciągu kilku minut. Technicznie: dwie różne doby. Ale jeśli tick spóźni się z aktualizacją tieru, gracz może zdążyć wymienić drugi raz po starej stawce. **Ryzyko:** race condition między wymianą a przeliczeniem tieru w fazie 5 ticka.

### 3.4 Sprawdzenie — escrow nie jest schronem (max 7 dni, podatek)

**Działa częściowo.** Escrow wygasa po 7 dniach — OK. Transakcje między kontami powiązanymi blokowane — OK. Podatek obejmuje „własne środki w PLN_ESCROW" — ale patrz 2.4 (niejasność).

**Luka:** Oferta B2B `published` trwa max 7 dni. Gracz może co 6 dni zakładać nową ofertę na ogromną kwotę (np. 1B PLN) między swoimi dwoma kontami na różnych odciskach. Oferta nie zostanie zaakceptowana (drugie konto jej nie akceptuje) — wygasa po 7 dniach. Gracz zakłada kolejną. Pieniądze NIE są w escrow (oferta tylko `published`), więc nie ma podatku, nie ma blokady. **To nie działa jako schron** — OK, to NIE jest exploit.

Gdy oferta jest `accepted`, pieniądze są w escrow i są opodatkowane — OK.

### 3.5 Sprawdzenie — podatek od całego kapitału

**Działa?** Tak — gotówka, bank, escrow, 70% aktywów trwałych. Nie ma gdzie schować pieniędzy.

**Luka — reinwestycja w aktywa:** podatek od aktywów trwałych to 0.7 × wycena, podczas gdy gotówka jest opodatkowana w 100%. Gracz może kupować odwierty/huby tylko po to, żeby zmniejszyć podstawę opodatkowania (70% vs 100%). Przy stawkach 0,03–0,08%/dzień to marginalne, ale teoretycznie obecne.

**Luka — aktywa w budowie:** nowy odwiert w trakcie wiercenia (koszt już poniesiony, aktywo jeszcze nieproduktywne). Czy 70% wyceny dotyczy odwiertu w budowie? Czy kosztu wiercenia? Niejasne.

### 3.6 Sprawdzenie — dormant 14 dni przed bankructwem

**Działa.** §19.2: `PLN_CASH + PLN_BANK = 0` → `dormant`. Bankructwo dopiero po 14 dniach. Ale:

**Luka:** gracz może mieć 1 PLN na koncie — nie spełnia warunku `= 0`, więc nie wchodzi w `dormant`. Długi narastają, OPEX nalicza się na minus (jako dług), ale bankructwo wymaga OBA warunków: net worth < 0 ORAZ brak płynności 48h. Jeśli gracz ma 1 PLN, ma „płynność"? System musi zdefiniować minimalną płynność pokrywającą OPEX na 48h, nie dosłowne `= 0`.

### 3.7 Nowy exploit — arbitraż na anulowaniu B2B

**§13.2:** po `accepted`, anulowanie = kara 5% na rzecz drugiej strony.

Jeśli cena ropy na spocie spadnie o >5% po zaakceptowaniu kontraktu B2B, kupującemu opłaca się anulować kontrakt (kara 5%), odzyskać 95% wartości z escrow i kupić ropę na spocie po niższej cenie. Sprzedawca zostaje z zablokowaną ropą i 5% rekompensatą, która może nie pokryć różnicy cen.

**Rekomendacja:** kara za anulowanie powinna być wyższa (10–15%) lub asymetryczna (kupujący płaci więcej).

### 3.8 Kartel jako pralnia RMT

**§26.3:** kontrakty B2B wewnątrz kartelu bez prowizji escrow. **§22.6:** GBBL i gotówka z Kroplówki nie podlegają transferom.

Luka: Gracz A kupuje GBBL → wymienia na PLN (Kroplówka) → kupuje ropę na spocie → sprzedaje koledze z kartelu przez B2B (zero prowizji) po cenie transferowej → kolega sprzedaje na spocie → wypłaca. Efektywnie GBBL→gotówka trafia do drugiego gracza z pominięciem limitu jego własnego Tieru.

**Wykrywalność:** telemetria §29.3 + anty-powiązane konta §13.2 — ale kartel to JAWNA struktura, więc wzorce są trudniejsze do odróżnienia od legalnego handlu.

---

## 4. RYZYKA TECHNICZNE

### 4.1 Serializacja na kontach systemowych w ticku

**§23.5:** batch 100 graczy, każdy w osobnej transakcji, ale wszystkie aktualizują `SYS_TREASURY`, `SYS_TAX`, `SYS_MARKET_MAKER`. Przy 1000 graczy = 10 batchy × 1 transakcja na gracza = 1000 osobnych UPDATE'ów na tych samych wierszach systemowych. `SELECT ... FOR UPDATE` na kontach graczy nie koliduje, ale SYS konta będą wąskim gardłem. Przy 1000 graczy i 60-sekundowym ticku to prawdopodobnie niegroźne, ale warto monitorować.

### 4.2 Δt-kompensacja po awarii — brak górnego capu

**§23.3:** restart workera → Δt = now − last_calc_at. Jeśli serwer padł na 6 godzin, przy restarcie gracz dostaje 6h OPEX, wear, depletion naraz. To jest poprawne matematycznie, ale może wywołać szok (bankructwo, blowout z opóźnioną eskalacją). Spec nie definiuje maksymalnego Δt — deweloper powinien dodać `MAX_DELTA_SECONDS` (np. 3600s) i rozbijać duże Δt na partie.

### 4.3 FTS: immutable, partycjonowany, ale query do scoringu kredytowego

FTS będzie partycjonowany rocznie (§29.4). Zapytanie o „historię spłat" gracza z 3-letnim stażem przejdzie przez 3 partycje. Przy indeksie na `(debit_account, created_at)` i `(credit_account, created_at)` będzie OK, ale trzeba to zaplanować przy projektowaniu schematu.

### 4.4 Chart.js do wykresów finansowych — ograniczenia

§12.3 i §28.6 wybierają Chart.js. Chart.js nie ma natywnego wsparcia dla wykresów kandelowych (świecowych), które są standardem przy notowaniach giełdowych. Jeśli gra potrzebuje świec, trzeba będzie dodać plugin lub zmienić bibliotekę.

### 4.5 Device fingerprint — nieopisany mechanizm

§14.1 i §13.2 odwołują się do odcisku urządzenia (fingerprint) jako podstawy detekcji multikont. Nie ma informacji: klient-side (JS fingerprinting) czy server-side (TLS fingerprint)? Jakie atrybuty? To nie bloker całej implementacji, ale moduł anty-multikonto nie ruszy bez tej decyzji.

---

## 5. FINAŁOWA LISTA PRIORYTETÓW

### (a) BLOKERY KODOWANIA — nie da się zacząć bez rozstrzygnięcia

| Prio | Sekcja | Problem | Konkretna akcja |
|------|--------|---------|-----------------|
| **A1** | §7 | Ochrona przeciwsabotażowa: brak kosztu, czasu trwania, limitu, UI | Zaprojektować model `protections`: koszt PLN, duration 24h/72h/168h, max 1 na zasób. Dodać do `/legal` jako zakładkę. |
| **A2** | §17.1 | credit_score: brak formuły | Podać jawną formułę, np. `credit_score = clamp(0,115, 0.4×credibility + 0.3×repayment_rate×100 + 0.3×(1 − debt/assets_norm)×100 + late_penalty)` gdzie `repayment_rate = spłacone_raty / (spłacone + opóźnione)`. |
| **A3** | §19.2, §22.6 | "Majątek netto z wyłączeniem pakietu startowego" — algorytm | Przyjąć algorytm z 2.3(c): `net_worth_ex_starter = net_worth − STARTER_VALUE` gdzie `STARTER_VALUE` stała wyliczana przy rejestracji z ceny spot. Zapisać w §19.2 jawnym wzorem. |
| **A4** | §19.1 | "własne środki w PLN_ESCROW" — definicja | Rozstrzygnąć: tylko środki gdzie gracz jest KUPUJĄCYM (jego pieniądze zamrożone). Sprzedawca nie ma „środków" w escrow do czasu dostawy. |
| **A5** | §14.5 | "przelew powyżej 100 000 PLN" — definicja | Zdefiniować jako każdą transakcję FTS debetującą konto gracza (poza OPEX, pensjami i ratami — te są automatyczne). Lub prościej: operacje inicjowane przez gracza w UI >100k (zakup odwiertu, kontrakt, zlecenie limit). |
| **A6** | §13.2 | Kolizja timeoutu B2B z dostawą | Dodać regułę: jeśli `delivery_receipt` zostanie zarejestrowany w trakcie `disputed`, stan przechodzi w `delivered` → `settled` zamiast `refunded`. Timeout refund NIE wykonuje się, jeśli istnieje protokół rozładunku. |

### (b) DECYZJE PRODUKTOWE — trzeba świadomie podjąć

| Prio | Sekcja | Problem | Opcje |
|------|--------|---------|-------|
| **B1** | §23.7 test (7) | Test odwołuje się do nieistniejących „sabotaży za GBBL" | (a) Usunąć test — sabotowanie zostaje PLN-only. (b) Dodać usługę premium „Prywatny kontraktor: 50 GBBL za sabotaż bez cooldownu agresora" — ale to P2W. |
| **B2** | §13.2 | Anulowanie B2B po accepted: 5% kara umożliwia arbitraż cenowy | Podnieść karę do 10–15% wartości kontraktu LUB uzależnić od kierunku zmiany ceny spot. |
| **B3** | §26.3 / §22.6 | Kartel jako kanał pośredniego transferu wartości z GBBL | Dodać limit wolumenu B2B wewnątrz kartelu na dobę LUB wymagać prowizji escrow także wewnątrz kartelu po przekroczeniu progu. |
| **B4** | §30.4 + §19.4 | Czy ban wstrzymuje zegar aukcji dormant (90 dni)? | Rozstrzygnąć: ban NIE wstrzymuje zegara (180 dni to i tak długo) LUB ban pauzuje zegar do odwieszenia konta. |
| **B5** | §19.2 | Czy `PLN_CASH + PLN_BANK = 0` dosłownie, czy < minimalny OPEX? | Zmienić warunek dormant na: `PLN_CASH + PLN_BANK < 48h_OPEX_plus_pensje` zamiast dosłownego zera. |

### (c) KALIBRACJA LICZB W game_balance — potrzebne przed/na starcie

| Prio | Sekcja | Co skalibrować | Uwagi |
|------|--------|---------------|-------|
| **C1** | §9.5 | base_rate, koszt wiercenia, OPEX, wear rate — 4×4 wartości | Wartości startowe podane; wymagają symulacji 1000+ ticków (headless) dla sprawdzenia, czy pojedynczy odwiert shallow jest rentowny. |
| **C2** | §17.1 | Progi credit_score, oprocentowanie, okresy | Wartości podane — OK jako start. Ale trzeba zdefiniować `credit_score` → formułę (zob. A2). |
| **C3** | §19.1 | Progi i stawki podatku majątkowego | Podane (200M/500M/1B, 0,03/0,05/0,08%/dzień). Do symulacji: czy przy tych stawkach top 1% graczy oddaje rozsądną część majątku. |
| **C4** | §7.1 | Koszty sabotaży, szkody, ryzyko wpadki | Wartości podane. Do symulacji: czy 5 sabotaży w 7 dni może zniszczyć firmę Tier I (majątek <10M) — jeśli tak, to ochrona startowa musi działać. |
| **C5** | §6 | Koszty kursów (10k–100k PLN), pass_rate (70%), bonus powtórkowy (+10 p.p., max +30 p.p.) | Wartości domyślne podane — OK. |
| **C6** | §22.6 | Limity Kroplówki per Tier (20/50/100/200/400 GBBL/dzień) | Do symulacji: czy 400 GBBL/dzień dla Tier V (400k PLN/dzień) nie rozsadza sinków podatkowych. |
| **C7** | §19.3 | Pojemność okna slippage, tempo regeneracji (10%/10min) | Wartości niepodane jawnie (tylko „w game_balance"). Trzeba ustalić konkretne liczby. |

### (d) DO ZROBIENIA W TRAKCIE IMPLEMENTACJI — nie blokują startu

| Prio | Sekcja | Co | Uwagi |
|------|--------|-----|-------|
| **D1** | §23.5 | Optymalizacja kont systemowych: batchowanie UPDATE SYS kont co N graczy | Nie blokuje — najpierw działa, potem profilujemy. |
| **D2** | §23.3 | MAX_DELTA_SECONDS przy restarcie workera | Dodać cap np. 3600s; rozbicie na partie w pętli. |
| **D3** | §29.4 | Strategia partycjonowania FTS + indeksy dla credit_score query | Projektować schemat z wyprzedzeniem, ale sam kod scoringu może używać agregacji. |
| **D4** | §14.1 | Wybór biblioteki fingerprintingowej (np. FingerprintJS vs custom) | Decyzja implementacyjna. |
| **D5** | §12.3 / §28.6 | Chart.js — weryfikacja, czy obsłuży wszystkie typy wykresów (świece?) | Jeśli nie — dodać plugin financial-chart lub迁移 do lightweight-charts. |
| **D6** | §29.2 | Procedura rekoncyliacji FTS (odtwarzanie sald z double-entry) | Napisać jako komendę Artisan, testować co miesiąc. |
| **D7** | §24.4 | Enumy: VARCHAR + backed enum PHP 8.3 — konwencja nazw przejść stanów | Ustalić listę wszystkich maszyn stanów i ich przejść przed kodowaniem modeli. |

---

## PODSUMOWANIE

Dokument po trzech rundach poprawek jest **w dużym stopniu kompletny** — architektura modułowa, silnik ticka, FTS, maszyny stanów i zabezpieczenia ekonomiczne są spójne i przemyślane.

**Można zacząć implementację** pod warunkiem rozstrzygnięcia **sześciu blokerów kodowania (A1–A6)** przed pierwszym commitem — bez nich deweloper utknie lub zgadnie źle. Pięć decyzji produktowych (B1–B5) też warto podjąć przed startem, ale nie wstrzymują one pisania szkieletu.

Główne zabezpieczenia (tarcza 10M, limity 5/7 dni, Kroplówka per Tier, escrow 7 dni, podatek od całości kapitału, dormant 14 dni) **działają zgodnie z intencją**, choć każde ma drobne luki opisane w sekcji 3. Żadna z tych luk nie jest krytyczna — to luki optymalizacyjne, nie wywracające gospodarki.

Najpoważniejszy pojedynczy problem: **brak mechaniki nabycia ochrony przeciwsabotażowej (A1)** — to dziura w architekturze, nie tylko niedookreślenie.


---

# CZĘŚĆ III — PEŁNA RECENZJA: Qwen3.8-Max (reasoning high)

Weryfikacja wersji: plik live `C:/Users/Marioo/Documents/OILEMPIRE_ARCHITEKTURA_SYSTEMOWA.md` zawiera §1–§30 i jest zgodny z wklejonym dokumentem — przegląd robiony na aktualnej wersji. Wszystkie wyliczenia liczbowe przeliczone samodzielnie.

═══════════════════════════════════════
CZĘŚĆ 1: NOWE NIESPÓJNOŚCI WPROWADZONE PRZEZ POPRAWKI
═══════════════════════════════════════

KRYTYCZNE (liczby, które do siebie nie pasują):

1.1. §24.1 vs §19.2 — próg tarczy łamie kurs centralny. Tabela przeliczeń mówi „próg tarczy $2M → 10M PLN", ale przy sztywnym kursie 1 USD = 4,00 PLN daje to 8M PLN, nie 10M. Albo próg historyczny był inny, albo konwersja jest błędna. Programista kodujący `shield_threshold` dostaje dwie różne wartości z dwóch sekcji.

1.2. §5.1 vs §19.2 — trzy różne ceny tego samego mediatora. Strajk HR: „Zewnętrzny Mediator Rządowy (50 000 PLN, mechanika §19.2)". §19.2: „10% rezerwy gotówkowej, minimum 25 000 PLN" + dług `mediator_debt` 25 000 PLN. Jedna usługa, trzy widełki (50k / 10% min 25k / dług 25k). Dodatkowo §19.2 nie ma GÓRNEGO limitu — przy rezerwie 100M PLN mediator kosztowałby 10M PLN, co jest absurdalne i karze oszczędnych graczy offline. Do tego rozjazd semantyczny: §5.1 opisuje mediatora jako zakup RĘCZNY (pat mediacyjny), §19.2 jako AUTOMAT po 72h nieobecności — czy to ten sam trigger i ten sam efekt (zamrożenie 7 dni, skutki −50%)?

1.3. §1.1 (mockup) vs §1/§14.1 — pojemność magazynu 2 400/2 400 bbl u „dojrzałej korporacji", podczas gdy pakiet STARTOWY daje 20 000 bbl (i premium max +25% = 25 000). Dojrzała firma ma 8× mniejszy magazyn niż starter. Nawet z zastrzeżeniem „wartości poglądowe" pasek pojemności jest sprzecznością strukturalną, nie kosmetyczną.

1.4. §1.1 (mockup) — „Produkcja: 333 bbl/h" przy „Przychód: 1 346 PLN/h". Przy 62 USD/bbl × 4,00 = 248 PLN/bbl daje to ~82 600 PLN/h; nawet po kryzysie −70% z banera (~74 PLN/bbl) ~24 800 PLN/h. 1 346 PLN/h implikuje cenę ~4 PLN/bbl (1 USD/bbl). Mockup jest wewnętrznie niespójny arytmetycznie — a to wzorzec wizualny, na którym frontend będzie się opierał.

1.5. §17.3 vs §19.2 — dwa niezależne spusty bankructwa bez rozstrzygniętej relacji. §17.3: majątek netto < 0 + 48h bez płynności. §19.2: „bankructwo może zostać ogłoszone najwcześniej po 14 dniach dormant". Czy dormant 14 dni uruchamia bankructwo nawet przy netto > 0? Czy `late` 72h (§17.1) → komornik Fazy 1–3 biegnie wobec firmy `dormant` (produkcja stoi, zobowiązania narastają jako dług) i licytuje jej odwierty PRZED upływem 14 dni? Kolejność: dormant → late → komornik → bankructwo vs dormant → 14 dni → bankructwo — nie jest zdefiniowana, a to dwie różne ścieżki kodu.

ŚREDNIE (obietnice bez pokrycia / wiszące odwołania):

1.6. §26.3 — „kontrakty B2B wewnątrz kartelu bez prowizji escrow". Prowizja escrow nie jest zdefiniowana NIGDZIE w dokumencie (ani stawka bazowa, ani konto FTS). Obietnica zwolnienia z opłaty, która nie istnieje.

1.7. §24.3 — „Kroplówka nie jest źródłem finansowania sabotaży ponad limit tieru". Nie istnieje żaden „limit tieru" dla sabotaży — sabotaże mają sztywne ceny PLN (§7.1) i jedyne limity to cooldowny + 5/7dni na ofiarę. Wiszące odwołanie do mechaniki, której nie ma.

1.8. §19.2 — tarcza chroni przed „wrogimi kontrolami skarbowymi ze strony innych graczy". Nie istnieje mechanika, w której gracz wyzwala kontrolę skarbową u innego gracza (kontrolę触发uje `black_market_score` §2; `player_dirty_report` bije w credibility, nie w kontrolę). Albo brakująca mechanika, albo martwy zapis.

1.9. §25.3 — wewnętrzna sprzeczność w jednym akapicie: „każde wydarzenie ma announced_at ≥ 24h przed startem" i zdanie później „Wydarzenia GM mogą startować natychmiast". §21 (wiersz o kursie centralnym) obiecuje twardo „24h zapowiedzią (§25)" — jeśli GM może natychmiast, obietnica z §21 jest łamana przez własny exception.

1.10. §8 (drzewo modułów) vs §8.2 (tabela) — tabela routingu wymienia moduły Contracts, Finance, Monetization, Simulation, których NIE MA w drzewie `modules/` w §8 (Contracts ma adnotację „Market/B2B", ale Finance z `player_finance_decisions`/`department_budgets`, Monetization z `payment_orders`/`gbbl_ledger` i Simulation z `simulation_heartbeats` nie mają przypisania). Szkielet repozytorium nie da się wygenerować z dokumentu bez zgadywania.

1.11. §14.1 vs §17.1 — kolejne konto z tego samego odciska dostaje „pożyczkę rozruchową 500 000 PLN od SYS_TREASURY", ale scoring 0–39 = „brak zdolności". Warunki tej pożyczki (oprocentowanie, okres, co przy niespłaceniu — komornik od 500k?) są niezdefiniowane; to osobny produkt kredytowy poza §17.1.

NISKA KOSMETYKA (poprawić przy okazji):

1.12. §2/§13/§18.1 używają legacy nazw `bank_trust_scores` / `b2b_reputation_scores` w tekście normatywnym, mimo że §24.4 nakazuje migrację na formy pojedyncze. Dokument powinien konsekwentnie używać nazw kanonicznych (choćby z przypisem o legacy).
1.13. §26 (`regional_{region}`, `company_{id}`, `dm_{pair}`) vs §28 (`chat.regional.*`, `chat.company.*`, `chat.dm.*`) — dwie konwencje nazewnicze kanałów; ujednolicić, bo autoryzacja Reverb koduje się z nazw.
1.14. §9.1 — bufory 500/2 000/5 000 bbl „zależne od tieru" bez jawnego przypisania który tier który bufor (kolejność black_market/standard/premium jest domyślna, ale powinna być wpisana wprost).
1.15. §22.3 „Zwiększenie pojemności bazy" (GBBL) i §22.6 „pojemność bazy max +25% dożywotnio" — prawdopodobnie ta sama mechanika, ale bez jawnego cross-referencji; ryzyko, że powstaną dwa osobne limity.

═══════════════════════════════════════
CZĘŚĆ 2: LUKI BLOKUJĄCE IMPLEMENTACJĘ
═══════════════════════════════════════

To jest najpoważniejsza część werdyktu. Dokument po trzech rundach ma domknięte finanse, tick engine, płatności i auth — ale MA CAŁĄ DOMENĘ BEZ SPECYFIKACJI:

2.1. LOGISTYKA NIE ISTNIEJE JAKO SEKCJA. Moduł Logistics to 1 z 11 modułów (§8), faza 2 ticka (§23.2), trasa `/logistics`, tabele `hubs`, `pipelines`, `trucks`, `transport_runs` (§8.2) — i ani jednej sekcji definiującej: koszt budowy i parametry hubów (pojemność bufora, przepustowość, `waiting_bay` max 5 jest tylko w §21), koszt/czas/przepustowość/tier rurociągów (wzmianki: pozwolenie Poziomu 2, `pipeline_engineer`, inspekcje), flotę cystern (pojemność, prędkość, koszt kursu PLN/km czy PLN/bbl, zakup, „czas reakcji +25%" przy strajku — wobec jakiej bazy?), routing (kto wybiera trasę odwiert→hub→magazyn, czy kursy są ręczne domyślnie — VIP „pętla automatyczna" sugeruje że tak, ale to nigdzie nie jest powiedziane wprost), „wycieki i opóźnienia przy budżecie low" (§10) — bez stawek, „tryb turbo" hubów (§4.1, §9.3 mnożnik 1.5) — jak się go włącza, ile kosztuje, co poza wear. Programista fazy 2 ticka nie zakoduje NICZEGO bez zgadywania. To jest blocker numer 1 całego projektu — potrzebna pełna sekcja „Logistyka" na wzór §9.

2.2. WYCENA AKTYWÓW (asset valuation) — używana w trzech krytycznych miejscach: Faza 3 komornika „60% wyceny systemowej" (§17.2), podatek majątkowy „70% wyceny aktywów trwałych" (§19.1), odnowienie koncesji „5% wyceny regionu" (§19.4). Formuła wyceny odwiertu (zależna od remaining_bbl, condition, tier, stratum?), huba, rurociągu i „regionu" nie istnieje. Bez tego nie da się zakodować ani podatku, ani egzekucji, ani koncesji.

2.3. MODEL MORALE. §4.1 definiuje wzór na `salary_satisfaction` (0–120), ale morale — którego średnia <35 uruchamia spór (§5), które dostaje +20/−20/−10/−1 pkt z §4.2/§5.1 — nie ma definicji: skala (0–100?), wartość startowa, tempo zmian, relacja do `salary_satisfaction` (czy morale = f(satisfaction) czy osobny byt z własną dynamiką?). Cały łańcuch dispute→threat→strike wisi na niezdefiniowanej zmiennej.

2.4. SILNIK CENY. §12.3 wymienia czynniki (popyt/podaż, indeksy, trendy, szoki), §19.3 definiuje tylko slippage i regenerację. Brak: postaci funkcyjnej aktualizacji ceny w fazie 3 (cena bazowa? mean-reversion do jakiej kotwicy? elastyczność popytu NPC?), składowania `price_history` wobec kursu USD→PLN, oraz CZY GRACZ W OGÓLE MOŻE KUPOWAĆ ROPĘ NA SPOCIE (§12.1 opisuje wyłącznie sprzedaż; §19.3 mówi o „głębokości księgi popytu" — ale strona podażowa dla gracza nie jest zdefiniowana). To blokuje fazę 3 ticka i test „1000 ticków = stabilna cena" (§23.7).

2.5. TRANSFER PLN_CASH ↔ PLN_BANK NIE ISTNIEJE. Gra ma dwa portfele (§24.2), raty schodzą wyłącznie z `PLN_BANK` (§17.1), sabotaże płatne „w gotówce" (§7), Kroplówka kredytuje `PLN_CASH` (§24.3), step-up auth dotyczy „przelewów powyżej 100 000 PLN" (§14.5 — jakich przelewów, skoro przelewów między graczami nie ma?). Nie ma ani słowa o mechanice wpłaty/wypłaty z banku: czy istnieje, czy darmowa, czy natychmiastowa, czy limitowana. Bez tego: gracz z pełnym cashem i pustym bankiem dostaje `late` na kredycie — i nie ma legalnej drogi naprawy. Pytanie pochodne: czy rata przy pustym `PLN_BANK` fallbackuje na `PLN_CASH`?

2.6. POZWOLENIA I ŁAPÓWKI — funkcja bez specyfikacji. §8.2 obiecuje tabele `permits` i panel „Łapówki", §18.2 zasób Filament, §2 karę „wpadka przy łapówce −18", §9.4 koncesję Poziomu 1 jako krok 1 lejka. Nie zdefiniowano: kosztu i czasu wydania pozwolenia P1/P2, limitu slotów koncesyjnych bazowych (premium „+2 dożywotnio" — wobec jakiej bazy?), mechaniki odmowy/odwołania, oraz CZYM SĄ ŁAPÓWKI (co przekupujesz: szybsze pozwolenie? niższa kara? umorzenie kontroli skarbowej? kto jest odbiorcą — NPC? jaki model ryzyka „wpadki"?). Cały podmoduł do napisania od zera.

2.7. PAKIET STARTOWY A FTUE — sprzeczność operacyjna. §27.1 obiecuje tutorial 30 minut: „pierwszy odwiert → cysterna → sprzedaż". Tymczasem lejek §9.4 to: koncesja + badanie geologiczne 48h + wiercenie 6–48h. Starter (§1) to gotówka + magazyn 20 000 bbl — BEZ odwiertu, BEZ cysterny, BEZ koncesji. Nowy gracz fizycznie nie przejdzie FTUE w 30 minut, chyba że starter zawiera prezbudowane aktywa (odwiert `active`? cysterna? koncesja?) — czego dokument nie mówi. Zdefiniować starter asset kit albo przeprojektować FTUE.

2.8. „MAJĄTEK NETTO Z WYŁĄCZENIEM PAKIETU STARTOWEGO" — sposób liczenia. §19.2 podaje „~8,96M PLN" (= 4M gotówki + 20 000 bbl × 248 PLN/bbl przy cenie 62 USD) — czyli wartość pakietu zależy od CENY ROPY W DNIU REJESTRACJI. Czy wyłączenie to stała globalna z `game_balance`, czy snapshot per gracz z dnia założenia firmy? Co po sprzedaniu startowej ropy i zmieszaniu gotówki? Bez tej definicji nie da się zakodować ani wygasania tarczy, ani Tierów I–V (§22.6) — a Tier bramkuje Kroplówkę, czyli przychód.

2.9. B2B — fizyka dostawy i model oferty. §13.2: „czas dostawy wynika z trasy (24–72h, config)" + `delivery_receipt` w hubie/magazynie kupującego + §21 `waiting_bay` przy przepełnieniu. Nie wiadomo: czy dostawa to TIMER (teleport po X godzinach), czy realny kurs cysterny/rurociągiem przez silnik logistyki (którego i tak brak — 2.1); czy oferty B2B są tylko sell-side, czy też buy-side; czy oferta ma definicję transz (ratach) w strukturze danych („dostawy ratalne" §13.2, kara 5% „transzy" §13.1); jaką prowizję/płatność pobiera system (patrz 1.6). Maszyna stanów jest dobra, payload stanów — nie.

2.10. AUKCJE — trzy rodzaje (Faza 3 §17.2, aktywa dormant §19.4, 30% slotów Frontier §19.4) i zero mechaniki: typ (angielska? holenderska?), czas trwania, minimalne postąpienie, zachowanie NPC-banku (kupuje zawsze po cenie wywoławczej?), rozliczenie (escrow aukcyjny?), co gdy nikt nie licytuje. Plus: losowanie „pierwszeństwa koncesji" dla firm <90 dni (70% slotów) — format zgłoszeń, okno, rozstrzygnięcie.

2.11. KONTRAKTY SYSTEMOWE (§13.1) — generator: kto i jak często tworzy oferty rafinerii NPC, czym jest „przetarg" (blokada 7 dni po 3 niewykonanych transzach sugeruje istnienie przetargów), jak gracz się zgłasza, jak NPC wybiera kontrahenta (cena? `contract_reputation`?), struktura transz (wolumen/częstotliwość).

2.12. HARMONOGRAM SPŁATY KREDYTU (§17.1) — „raty co 24h", ale: annuitet, rata stała kapitałowa, czy odsetki + bullet na koniec? Od „max okres 30–120 dni" i „12%/rok" nie da się wyliczyć raty bez typu amortyzacji.

2.13. CATCH-UP PO AWARII — §23.3 definiuje Δt dla naliczeń ciągłych, ale NIE definiuje polityki dla zdarzeń DYSKRETNYCH per-tick (rzuty incydentów §17.4, egzaminy §6, rozstrzygnięcia sabotaży §7.1, losowania wydarzeń §25): po 12h przerwy = 720 pominiętych ticków — replay wszystkich rzutów (godziny liczenia, lawina incydentów) czy jeden skumulowany rzut (zniekształcone prawdopodobieństwa)? Potrzebny twardy cap Δt i reguła kolapsu. §29.1 pokrywa tylko planowane okna ≤15 min.

2.14. POZYCJE POMNIEJSZE, ale realne przy kodowaniu: (a) „niezadeklarowane przychody gotówkowe" (§2) — jaki licznik/ledger je gromadzi; (b) premia cenowa i limity wolumenu czarnego rynku (tylko „ceny wyższe niż giełda"); (c) wakaty dyrektorów innych niż Finanse (§10 definiuje tryb awaryjny tylko dla CFO — co z wakatem Tech przy kolejce TTS, Legal przy sabotażach/`armed_convoy`?); (d) rekrutacja dyrektorów w ogóle (pula kandydatów, kontrakty, odwołanie); (e) czy bonus powtórkowy +30pp z §6 resetuje się po 7-dniowej blokadzie kursu; (f) `blocked_pending_review` dla transakcji kont powiązanych (§13.2) nie ma stanu w maszynie §13.2; (g) techniczny zapis blokady 50% `PLN_BANK` po kontroli skarbowej (24–72h) — pole, zwolnienie, interakcja z fazą 1 komornika.

═══════════════════════════════════════
CZĘŚĆ 3: EKSPLOATACJE — CZY ZABEZPIECZENIA DZIAŁAJĄ
═══════════════════════════════════════

Ocena wprowadzonych mechanizmów: tarcza, limity sabotaży, Kroplówka per Tier, podatek od całego kapitału, escrow-7dni, dormant-14dni, kartele-40% — kierunek dobry, większość dziur z poprzednich rund zamknięta (GBBL niezbywalne = brak RMT-furtki; escrow w bazie podatkowej = brak parkowania; Kroplówka blokowana od Fazy 2 = brak ucieczki przed długiem; anty-double-spend `FOR UPDATE`; walidacja fizyczna automatyzacji VIP). Zostały jednak konkretne obejścia:

3.1. ESCROW: 7 dni dotyczy tylko stanu `published`. Wygaśnięcie „max 7 dni" (§13.2, §21) nie obejmuje stanów `accepted`/`in_delivery`/`disputed`. Dostawa 24–72h + spór bez twardego terminu rozstrzygnięcia („rozstrzyga automat PO TIMEOUTACH lub GM" — timeout sporu nie istnieje, jeśli sprzedawca odpowiada) = środki i ropa zablokowane bez górnego limitu czasu. Komornik escrow nie rusza (§21), więc dłużnik w roli kupującego może cyklicznie parkować gotówkę w escrow i przeczekiwać Fazy 1–2 (podatek majątkowy częściowo to równoważy, ale egzekucja jest bezradna). FIX: globalny `max_escrow_lock` 7 dni od `accepted` obejmujący `disputed` (auto-`refunded`), a po rozstrzygnięciu środki kupującego-dłużnika przechwytywane przez egzekucję.

3.2. LIMIT SABOTAŻY 5/7dni: grywalny jako TARCZA IMMUNITETOWA. Limit jest GLOBALNY na ofiarę i liczy UDANE operacje — więc „przyjazny" gracz (nie kartel — pakt §26.3 blokuje tylko wewnątrz kartelu) wykonuje 5 tanich `road_partial_theft` (15k PLN/szt., cooldown pary 24h → 5 dni) na minimalnym ładunku i ofiara ma tygodniowy immunitet na WSZYSTKIE realne ataki. Koszt immunitetu ~75k PLN + 20% małego ładunku — trywialnie tani dla dużej firmy. FIX (wybrać): minimum progowe szkody, by operacja liczyła się do limitu; sublimit per para agresor→ofiara (np. max 2 z 5 od jednego agresora); albo operacje od kont sprzymierzonych (kartel, wspólne B2B w ostatnich X dniach) nie liczą się do limitu ofiary.

3.3. ARBITRAŻ NA DETERMINISTYCZNEJ REGENERACJI CENY. §19.3: spadek 3–12%, powrót dokładnie 10% spadku / 10 min, pełna regeneracja ~100 min — w 100% przewidywalny. Jeśli istnieje strona zakupowa spotu (niezdefiniowana — 2.4) BEZ slippage'u zakupowego, to: zrzut dużej partii (własnej lub cudzej) zbija cenę o 12% → skup po dnie → sprzedaż po równowadze za ~100 min = darmowy zysk drenujący `SYS_MARKET_MAKER`. Nawet bez manipulacji: każdy gracz kupuje po każdym cudzym zrzucie — systematyczny arbitraż na MM. FIX: symetryczny slippage zakupowy, spread MM (buy < sell), i/lub niedeterministyczna regeneracja (RNG ticka + głębokość księgi NPC).

3.4. CYKLOWANIE DORMANT → OCHRONA POWRACAJĄCEGO. §19.4 pkt 4: powrót z dormant daje 7-dniową ochronę na zasadach Tarczy (dwukierunkową — ale Tarcza zabrania tylko bycia AGRESOREM i czarnego rynku; handel B2B i sprzedaż spot dozwolone). Łańcuch: urlop 30 dni (immunitet) → powrót → 7 dni → wyzerowanie cashu (aktywa zachowane) → dormant → powrót z 7-dniową tarczą → powtórz. Dostajemy ~80–90% uptime'u z immunitetem PvP przy zachowaniu pełnego majątku i możliwości handlu w oknach aktywności. FIX: ochrona powracającego max raz na N dni (np. 180) + w okresie ochrony zakaz pełnego handlu (jak przy tarczy newbie: passywna sprzedaż spot OK, ale bez nowych kontraktów).

3.5. DORMANT BEZ SELF-RESCUE (odwrotność exploitu — pułapka na gracza). Firma z pełnym magazynem ropy i zerem gotówki przechodzi w `dormant` (produkcja i operacje stają). Czy gracz może w dormant sprzedać ropę spot, żeby się obudzić? Dokument mówi tylko „powrót i spłata zaległości przywracają operacje" — jeśli sprzedaż jest zablokowana, uczciwy gracz offline wraca do firmy, której nie da się uratować bez dopłaty realnych pieniędzy (Kroplówka w dormant nie jest zablokowana — zablokowana jest dopiero od Fazy 2 i w recovery_mode). To nie exploit, to wymuszony pay-to-unlock — decyzja produktowa wymagająca odpowiedzi (rekomendacja: zezwolić na sprzedaż spot z magazynu w dormant, z 50% wpływów na spłatę zaległości).

3.6. PODATEK MAJĄTKOWY — dwie nieszczelności kalibracyjne (nie krytyczne): (a) baza to aktywa BRUTTO bez odliczenia zobowiązań — gracz z kredytem 100M zainwestowanym w odwierty płaci podatek od 70% całości; lewar jest karany podwójnie (odsetki + podatek od nieswojego kapitału) — do przeliczenia w symulatorze, bo może zdusić agresywny rozwój; (b) ropa w magazynie i w `locked_in_escrow_bbl` (strona sprzedającego) ma stawkę 0% — magazyn 25k bbl to ~6M PLN nieopodatkowanego parkowania; limit pojemności to ogranicza, ale przy progach 200M+ jest pomijalne. Zanotować, nie naprawiać.

3.7. KARTELE 40%: pomiar niezdefiniowany (okno? produkcja z 24h? z 7 dni?) i obejście przez dwa formalnie niezależne kartele sterowane przez tę samą grupę (rejestry publiczne, ale powiązanie wykrywa tylko telemetria/GM §26.4). Dodatkowo: co gdy kartel przekroczy 40% organicznie (wzrost produkcji członków)? „Blokada rekrutacji" działa tylko na wejściu — brak mechanizmu wyjścia (przymusowe wydalenie? zamrożenie benefitów?). Domknąć zapisem + zdefiniować okno pomiaru.

3.8. GBBL W EGZEKUCJI — udokumentowana, ale warta re-decyzji reszta P2W: zakup GBBL dozwolony w każdej fazie (§21), Prywatna Straż i firma zewnętrzna działają „mimo braku gotówki" (płatność GBBL lub zobowiązanie, §21/§17.4). Gracz w Fazy 2–3 komornika, który nie może użyć Kroplówki, nadal może za GBBL gasić blowouty i naprawiać ×2,5 bez cashu — czyli pieniądz realny częściowo neutralizuje skutki bankructwa. Granica §22.6 („bezkarność PvP nie do kupienia") formalnie nie jest złamana (Straż nie cofa historii), ale ekonomicznie recovery_mode jest dla płacącego gracza istotnie łagodniejszy. Świadoma decyzja produktowa — zapisać ją jawnie w §22.6, żeby nie „wypłynęła" jako luka po starcie.

3.9. CZARNY RYNEK — +2 `black_market_score` per TRANSAKCJA bez względu na wolumen: opłaca się jedna megatransakcja (2 pkt za cały przerzut) zamiast wielu małych. Przy decay −1/24h przemytnik robiący 1 transakcję/dobę utrzymuje score ~2–4 i nigdy nie przekracza progu 30 — mechanika ryzyka jest trywializowana przez batching. FIX: score skalowany wolumenem/wartością (np. +1 za każde X bbl, min +2).

3.10. STARTER-FARMING: pakiet raz na odcisk (fingerprint + płatność + IP) — solidne, ale odcisk urządzenia to client-side metric; po jego skompromitowaniu zostaje korelacja IP i sieć płatnicza, a konto „bez pakietu" nadal dostaje 500k pożyczki (1.11) — wartość farmienia spada, ale nie do zera, dopóki pożyczka nie ma twardych warunków i dopóki „zero pakietu" nie obejmuje też magazynu/kredytu startowego (niezdefiniowane — czy takie konto ma 20 000 bbl magazynu i wiarygodność 70?).

═══════════════════════════════════════
CZĘŚĆ 4: POZOSTAŁE RYZYKA TECHNICZNE
═══════════════════════════════════════

4.1. Catch-up po awarii (powtórzenie z 2.13, bo to ryzyko produkcyjne, nie tylko luka w specyfikacji): brak capu Δt i polityki kolapsu rzutów dyskretnych = po dłuższej awarii albo godzinnego replayu 720 ticków, albo cichej zmiany rozkładów prawdopodobieństwa. To musi być rozstrzygnięte PRZED kodowaniem fazy 7.

4.2. Granice transakcji dla stanów GLOBALNYCH. §23.5 definiuje „jedna transakcja na gracza na fazę" + kolejność wg `player_id` — ale faza 3 (cena, matching order booka, `SYS_MARKET_MAKER`) i faza 8 (world events) operują na stanie współdzielonym przez wszystkich graczy. Matching zleceń limit w transakcjach per-gracz przy globalnej cenie = race conditions albo deadlocki na gorącym wierszu stanu rynku. Potrzebny jawny projekt: globalna transakcja fazy 3 z własnym porządkiem blokad (offer_id), sekwencyjna, poza schematem per-player.

4.3. `runOnOneServer()` + `withoutOverlapping()` opierają się na cache store (Redis). Awaria/partycja cache przy dwóch instancjach scheduler = podwójne dispatchowanie jobów. UNIQUE(`tick_no`,`phase`) chroni przed podwójnym WYKONANIEM fazy, ale nie przed zalaniem kolejki duplikatami; klucze idempotencji (§23.5) muszą być egzekwowane także na poziomie enqueue. Do przetestowania scenariuszem z §23.7 pkt 6 rozszerzonym o „dwóch schedulerów naraz".

4.4. Rotacja `SERVER_SECRET` łamie odtwarzalność RNG (§23.4: replay ticka po rotacji klucza niemożliwy) — wersjonować klucz (`secret_id` w `simulation_heartbeats`) albo zaakceptować i udokumentować granicę replayu.

4.5. Budżet ticka przy 1000 graczy jest CELEM, nie faktem: 8 faz × batch 100 graczy + fazy globalne, `SELECT ... FOR UPDATE` na partycjonowanych tabelach, emisja Reverb `after_commit` (faza 3 generuje zdarzenia cenowe do wszystkich subskrybentów co 60s). Ryzyko realne do momentu pierwszego load-testu headless symulatorem — rekomendacja: symulator z profilem 1000 graczy jako brama release od ETAPU 1, nie „Etap 3".

4.6. HIBP k-anonymity jako zależność zewnętrzna w krytycznej ścieżce (rejestracja/zmiana hasła): brak zdefiniowanego zachowania przy niedostępności API (fail-open = hasła z wycieków przechodzą; fail-closed = blokada rejestracji). Zdecydować i zapisać (rekomendacja: fail-open z flagą + re-weryfikacja przy następnym logowaniu, lista lokalna jako fallback).

4.7. Rekoncyliacja FTS (§29.2) zamraża fazę 5 przy rozbieżności — freeze fazy 5 zatrzymuje OPEX/raty/podatki DLA WSZYSTKICH; potrzebny runbook: ile świat może działać z zamrożoną fazą 5, zanim Δt zacznie kumulować problemy (§23.3 kompensuje, ale „kompensuje" dla zaległych rat kredytowych oznacza lawinę `late` po odmrożeniu — czy status `late` liczy się od terminu raty, czy od faktycznego przebiegu fazy?).

4.8. Podwójna natura `PLN_ESCROW` „per transakcja" (§24.2) vs podatek „własne środki w escrow" (§19.1): agregacja sald po wszystkich aktywnych transakcjach gracza na każdy tick podatkowy — przy dużej liczbie ofert to zapytanie musi mieć indeks i plan; drobiazg, ale łatwo go przeoczyć i zabić budżet fazy 5.

═══════════════════════════════════════
CZĘŚĆ 5: FINAŁOWA LISTA PRIORYTETÓW
═══════════════════════════════════════

Werdykt ogólny: dokument NIE JEST gotowy do startu implementacji jako całość — ale jest gotowy w obszarach: FTS/plan kont (§24.2), maszyna stanów płatności i księga GBBL (§22.5), szkielet tick engine z heartbeats i deterministycznym RNG (§23.1–23.5), auth/sesje/hasła (§14), CMS+Privacy (§15–16), macierz GM (§18.3), retencja (§29.4). Te moduły można kodować dziś. Nie można kodować: Logistics (brak sekcji), Market faza 3 (brak modelu ceny), HR dispute chain (brak modelu morale), Banking egzekucja+podatek (brak wyceny aktywów).

(A) BLOKERY KODOWANIA — przed pierwszą linią kodu domenowego:
 A1. §nowa: napisać pełną sekcję LOGISTYKA (huby, rurociągi, cysterny, routing, tryb turbo, wycieki, koszty, staffing) — odpowiednik §9 dla modułu Logistics.
 A2. §17.2/§19.1/§19.4: formuła wyceny aktywów (odwiert/hub/rurociąg/region) jako serwis domenowy + wpis do game_balance.
 A3. §4/§5: model morale — skala, start, delta-źródła, relacja do salary_satisfaction, próg 35.
 A4. §12.3/§19.3: postać funkcyjna ceny spot (kotwica, mean-reversion, modyfikatory world_events, slippage buy-side i spread MM) — razem z decyzją czy gracz kupuje na spocie.
 A5. §24.2/§14.5/§17.1: mechanika transferu CASH↔BANK + fallback raty kredytu.
 A6. §8.2/§18: specyfikacja pozwoleń P1/P2 (koszt, czas, bazowe sloty koncesyjne, odnowienia) i mechaniki łapówek albo jawne przesunięcie łapówek na Etap 2 z usunięciem z zakresu v1 (wtedy §2 „wpadka przy łapówce −18" oznaczyć jako future).
 A7. §27.1/§9.4: zawartość pakietu startowego pod FTUE (starter well/cysterna/koncesja?) — bez tego tutorial 30 min jest niewykonalny.
 A8. §19.2/§22.6: definicja „netto bez pakietu" (stała z game_balance vs snapshot z rejestracji) — spójna dla tarczy i Tierów; przy okazji naprawić 1.1 (8M vs 10M).
 A9. §13.2: struktura oferty B2B (kierunek, transze, dostawa timer vs fizyczna, prowizja bazowa) + stan `blocked_pending_review`.
 A10. §13.1/§17.2/§19.4: mechanika aukcji i przetargów (typ, czas, postąpienie, NPC, rozliczenie).
 A11. §23.3: polityka catch-up (cap Δt, kolaps rzutów dyskretnych) + granice transakcji dla faz globalnych (4.2).
 A12. §17.1: typ amortyzacji rat.
 A13. §19.2 vs §17: rozstrzygnąć kolejność dormant→late→komornik→bankructwo (1.5) i czy egzekucja biegnie wobec dormant.
 A14. §8 vs §8.2: domknąć mapę modułów (Monetization, Simulation, Finance) — wpłynie na strukturę katalogów i providery Filament.

(B) DECYZJE PRODUKTOWE (nie blokują szkieletu, blokują feature'y):
 B1. §19.2/§19.4: jednolita cena mediatora + cap (3 warianty w dokumencie — 1.2) oraz limit częstotliwości ochrony powracającego (anty-cykling 3.4).
 B2. §19.2: czy w dormant dozwolona jest sprzedaż spot self-rescue (3.5) — rekomendacja: tak, 50% na zaległości.
 B3. §13.2/§21: twardy max escrow lock 7 dni od `accepted` obejmujący `disputed` (3.1).
 B4. §7.1: model liczenia limitu 5/7dni — próg szkody / sublimit per agresor (3.2).
 B5. §22.6: jawne zapisanie, że GBBL-mitygacja katastrof w egzekucji jest AKCEPTOWANĄ resztą P2W (3.8) albo jej ograniczenie w recovery_mode.
 B6. §26.4: okno pomiaru 40% kartelu + konsekwencja przekroczenia organicznego.
 B7. §10: czy limit 20 000 PLN/h sprzedaży awaryjnej (wakat CFO) nie jest zbyt dotkliwy dla firmy 300+ bbl/h (mockup §1.1) — to ~80 bbl/h.
 B8. §10: „podatki regionalne" w KPI — zdefiniować mechanikę albo usunąć z listy.
 B9. §14.1: warunki pożyczki rozruchowej 500k i pełny starter konta „bez pakietu" (magazyn? wiarygodność?).
 B10. §3/§10: zasady wakatu dla pozostałych dyrektorów (Tech/Legal/HR/Logistyka) — czy tylko Finanse mają tryb awaryjny.
 B11. Zakres językowy v1: 80+ dialogów × 3 języki (§5.2) + CMS i18n — czy DE/EN nie jadą jako Etap 2 (fallback PL już jest).

(C) KALIBRACJA W game_balance (przed testami symulatorem, wartości istnieją jako pola):
 C1. §9.5: `initial_reserves_bbl` per stratum (+ rozkład „suchego odwiertu" §9.4) — TABELA NIE ISTNIEJE, a bez niej depletion (0.10 floor po ~600k bbl ponad 70%) i ekonomia całej gry są niekalibrowalne.
 C2. §19.3: pojemność okna slippage, głębokość księgi NPC, spread MM.
 C3. §17.4: tabela base_risk przy condition<20% + skutki per klasa incydentu (dokument odsyła do game_balance — wpisać wartości startowe).
 C4. §12.4: premia czarnego rynku (%), limit wolumenu/ transakcji, skalowanie +2 score wolumenem (3.9), licznik „niezadeklarowanych przychodów".
 C5. §3/§4: `min_market_salary` i widełki `expected_salary` per stanowisko, koszty rekrutacji/headhuntera PLN.
 C6. §6: koszty kursów 10k–100k (tabela per poziom I–III), pass_rate per kurs, reset bonusu powtórkowego.
 C7. §19.1: progi/stawki podatku vs emisja Kroplówki i lewar kredytowy — przeliczyć scenariusz „1B w aktywach na kredyt" (4.6b/3.6a).
 C8. §19.4: wycena regionu (baza 5% odnowienia), tempo Frontier 90 dni vs renew 180 dni.
 C9. §11: `procedure_integrity` start + tempo degradacji + koszt audytu.
 C10. §17.1: wagi formuły credit_score (dokument sam odsyła do game_balance — bez tego faza 5 stoi).
 C11. §1.1: poprawić mockup (magazyn 20 000+, spójny przychód) — wzorzec dla frontendu.

(D) DO ZROBIENIA W TRAKCIE IMPLEMENTACJI (nie blokuje startu):
 D1. §23.7: headless symulator jako PIERWSZY deliverable Etapu 1 + golden-mastery formuł (§4.1, §9.2, §17.4) i 12 scenariuszy acceptance — rozszerzyć o: dwóch schedulerów naraz (4.3), catch-up 12h (4.1), arbitraż MM (3.3).
 D2. §29.5: wersjonowanie game_balance od pierwszej migracji (balance_version w heartbeats).
 D3. §29.3: telemetria faucets/sinks i skuteczności sabotaży instrumentowana równo z mechanikami, nie po fakcie.
 D4. Treści: 80+ dialogów, ~60 osiągnięć, tutorial 5 kroków, strony CMS/ToS — pipeline contentowy równolegle z backendem.
 D5. §29.1: staging z seedowanym światem + miesięczny test odtworzenia backupu (RPO 5 min) — zanim ruszą płatności.
 D6. §14/§15: HIBP fallback (4.6), CSP, re-encode uploadów — standardowe hardening przy kodowaniu auth.
 D7. Runbook freeze fazy 5 (4.7) + kolejność `late` po odmrożeniu.
 D8. Numeracja wymagań {DOMENA}-{NR} (§24.4) — zacząć nadawać ID przy przepisywaniu powyższych punktów A/B/C do trackera.

PODSUMOWANIE JEDNYM ZDANIEM: szkielet techniczny (tick, FTS, płatności, auth, GM) jest implementowalny dziś i jest dobrze uszczelniony; start kodowania domenowego blokują cztery dziury: brak sekcji Logistyka, brak modelu ceny/morale/wyceny aktywów, niespójna cena mediatora i progu tarczy (8M vs 10M), oraz niezamknięte stany escrow/dormant — pozostałe zabezpieczenia działają, z wyjątkiem trzech obejść: friendly-sabotage immunitet, arbitraż na deterministycznej regeneracji ceny i cykl dormant-tarcza.

---

# ADDENDUM (09.11.2026, po recenzjach) — DECYZJA UŻYTKOWNIKA: JEDNA WALUTA

**Rozstrzygnięcie:** w grze istnieje JEDNA abstrakcyjna waluta. Nazwa jest wyłącznie kosmetyczna i zależy od locale interfejsu: **PL → „PLN", EN → „$", DE → „€"** — kwota identyczna w każdym języku. **Nie ma kursu wymiany, przewalutowań ani drugiej waluty.**

Wdrożone w specyfikacji (§1, §1.1 mockup, §20, §21, §22.2, §24, §25):
- usunięto kurs centralny 1 USD = 4,00 PLN i wydarzenie światowe `zmiana_kursu`;
- cena bazowa ropy: **250 PLN/bbl** (`price_base` w `game_balance`) — notowania bezpośrednio w walucie gry;
- mockup §1.1 skorygowany: 250,00 PLN/bbl, magazyn 18 400/20 000 bbl (spójny ze starterem), przychód 83 250 PLN/h = 333 bbl/h × 250 (naprawia Qwen 1.3/1.4);
- §24.1–24.3: konto techniczne `PLN_*` jako identyfikator niezależny od locale; Kroplówka jako przelicznik wewnętrzny 1 GBBL = 1 000 jednostek (osobne księgi per waluta z `conversion_id` — naprawia zarzut GPT o mieszaniu jednostek w jednym wpisie FTS);
- §22.2: jedyna realna konwersja pozostaje po stronie operatora płatności (Stripe dla EN/DE) — zewnętrzna wobec gospodarki gry.

**Zamknięte tym decyzją pozycje z list priorytetowych:** Qwen 1.1 (8M vs 10M — progi są autonomiczne w PLN), GPT a13-część walutowa (plan kont uproszczony: jedna księga gry + księga GBBL + księga płatności realnych), DS 1.2-część (jednostki), część (c) kalibracji — ceny progowe liczone w jednej walucie.
**Pozostaje aktualne:** osobne księgi GBBL vs waluta gry (GPT a13), pełna lista (a)/(b)/(c)/(d) poza pozycjami walutowymi.
