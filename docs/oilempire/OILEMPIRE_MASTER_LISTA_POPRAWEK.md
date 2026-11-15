# OilEmpire — SCALONA LISTA POPRAWEK (wersja master)

Zkonsolidowany wynik 4 niezależnych przeglądów specyfikacji `OILEMPIRE_ARCHITEKTURA_SYSTEMOWA.md`:
1. Analiza Hermes (3 iteracje, wersje v1→v3 dokumentu),
2. GPT (gpt-6-sol) — przebieg 1: sekcja §22 (monetyzacja),
3. GPT (gpt-6-sol) — przebieg 2: sekcje §1–§21,
4. DeepSeek v4 Pro (reasoning: high) — przegląd ślepy całości §1–§22.

Duplikaty usunięte; każdą poprawkę potwierdziły co najmniej dwa przeglądy, chyba że oznaczono `[1 źródło]`.
UWAGA: odrzucono wyliczenie DeepSeek, że podatek 0,05%/dzień to „$5 dziennie" — poprawnie: 0,05% od $10M nadwyżki = **$5 000/dzień** (~18%/rok). Problemem podatku zostaje tylko brak progresji i niejasna podstawa (poz. 16).

Legenda priorytetów:
- **P0 — KRYTYCZNE:** blokuje implementację albo psuje żywą gospodarkę/PvP od dnia startu.
- **P1 — WYSOKIE:** exploity, balans, luki domenowe wymagające reguł przed kodowaniem.
- **P2 — ŚREDNIE:** techniczne, bezpieczeństwo, operacje.
- **P3 — PRODUKT/DOKUMENT:** treść, UX, kompletność specyfikacji.

---

## A. P0 — KRYTYCZNE (bez tego nie zaczynać kodowania)

> ✅ **Status 09.11.2026 — ETAP 1 WDROŻONY do specyfikacji:** P0-1–P0-8 zamknięte. Nowe sekcje: §9 (Extraction), §17 (Banking/Incidents), §23 (Tick Engine: 8 faz, dt-based OPEX, `simulation_heartbeats`, RNG per tick, `SELECT...FOR UPDATE`, after_commit), §24 (waluta PLN + kurs centralny 1 USD = 4,00 PLN + plan kont FTS), §22.5 (maszyna stanów płatności + księga GBBL), §22.6 (polityka fair-play/P2W + Tiery I–V + wygasanie VIP). Rozszerzone: §12 (blokada wolumenu), §13 (maszyna stanów B2B, rezerwacja ropy, timeouty, spory, kaucja 10%, konta powiązane), §19.2 (tarcza 10M PLN netto BEZ pakietu startowego + dwukierunkowa), §21 (2 000 PLN/h, uszczelnienie escrow), §20 (słownik: GBBL, Tier, kurs centralny, tick; OPEX dt-based). Konwersja walutowa: pakiet startowy 2M+2M PLN, podatek 200M PLN. Poprawki konsultacyjne: GBBL niezbywalne (anty-RMT), Kroplówka 1 GBBL = 1 000 PLN z limitami Tier i blokadą w recovery/Fazie 2, Certyfikacja skraca tylko czas (egzamin losowy), Straż Prywatna tylko na aktywny incydent, headhunter 1/dobę, PayU z tym samym kontraktem webhooka.

- [x] **P0-1. Specyfikacja Tick Engine** (GPT×2, DS, Hermes — największa luka projektu)
  - Interwał ticka i zegar referencyjny; kolejność faz (np. wydobycie → transport → rynek → kontrakty → finanse/OPEX → HR → incydenty → decay).
  - Rozstrzygnąć sprzeczność: „OPEX pobierany co sekundę" (§20) vs model tickowy → naliczanie dt-based od ostatniego ticka, idempotentne przy restarcie.
  - Granice transakcji (1 transakcja per gracz per tick? locki rekordów?), determinizm (seed), batchowanie (rząd wielkości: 1000 graczy × 10 odwiertów = 10k aktualizacji/tick), scheduler z `withoutOverlapping`, zachowanie przy wielu instancjach.
  - Restart/odtwarzanie: co się dzieje po awarii workera w połowie ticka.

- [x] **P0-2. Jedna waluta + plan kont trzech pieniądzy** (GPT×2, DS, Hermes)
  - Mockup §1.1 i KPI są w **PLN**, cała reszta ($500k, $/bbl, $50M, $500/h) w **USD** — rozstrzygnąć natychmiast.
  - Zdefiniować plan kont i przeliczniki dla: PLN realne (płatności), GBBL (premium), pieniądz gry — co dokładnie księguje FTS i w jakiej jednostce (§22.1 księguje do FTS bez jednostki).
  - Podać kurs GBBL→gotówka gry („Kroplówka Kapitałowa" §22.3 nie ma kursu).

- [x] **P0-3. Uzupełnić §9 (Extraction) i §17 (Banking/Incidents)** — sekcje nie istnieją (wszyscy)
  - §9: odwierty, warstwy geologiczne, zasobność złóż, degradacja/wear, tiery, upgrade'y, wydobycie per tick, obsada, koszty.
  - §17: **kompletny moduł bankructwa i komornika** — progi uruchomienia egzekucji, mechanika zajęcia z giełdy (procent od sprzedaży? blokada?), etapy, licytacja majątku, `recovery_mode`, restrukturyzacja. Dziś tylko wzmianki rozrzucone po §2/§18.1/§21.

- [x] **P0-4. Kontrakty i escrow B2B — pełna maszyna stanów** (GPT×2, DS)
  - **Rezerwacja ropy sprzedawcy** przy wystawieniu/akceptacji oferty — dziś tę samą ropę można obiecać w B2B i sprzedać na spocie/limicie.
  - Timeout dostawy + procedura sporu + moment zwrotu escrow (griefing: sprzedający przyjmuje i nie dostarcza → środki kupującego zamrożone na zawsze).
  - Dowód dostawy, częściowe raty, odbiór; „przepadek kaucji" — cała umowa czy jedna transza?
  - Formuła kaucji i kary umownej (brak liczb).
  - Cykl życia oferty B2B + limit depozytów + kontrola transakcji między powiązanymi kontami (patrz P1-9: schron przed komornikiem).

- [x] **P0-5. Tarcza startowa wygasa w momencie rejestracji** [DS — wyliczenie zweryfikowane]
  - Pakiet startowy: $500k + $500k + 20 000 bbl × ~$62 = **~$2,24M netto** > próg tarczy **$2,000,000** (§19.2 vs §1). Każdy nowy gracz traci immunitet w pierwszej sekundzie.
  - Fix: próg liczony bez pakietu startowego albo podniesiony do $5–10M.
  - Dodatkowo (Hermes): tarcza **dwukierunkowa** — gracz ze tarczą nie może też atakować (alt-konta).

- [x] **P0-6. Maszyna stanów płatności i księga GBBL** (GPT §22)
  - Powiązanie `order_uuid` ↔ płatność operatora ↔ świadczenie; walidacja kwoty/waluty/pakietu; zdarzenia ponowione i poza kolejnością; niedopłaty; refundy i chargebacki; gwarancja „najwyżej jedno świadczenie na opłacone zamówienie" (sam unikalny `transaction_id` nie wystarcza).
  - Kontrakt zużycia GBBL: atomowe sprawdź→pobierz→przyznaj + klucz idempotencji + kompensata przy niewykonanej usłudze; atomowy limit użyć promokodów.
  - Polityka zwrotów po zużytych benefitach („dług GBBL", ograniczenia konta) bez przepisywania niezmiennego FTS.
  - `PayUDriver` istnieje na diagramie bez ścieżki rozliczenia/webhooka.

- [x] **P0-7. Decyzja fair-play / P2W** (GPT §22, DS) — rozstrzygnąć i zapisać wprost
  - „Subskrypcje wygody" zawierają trwałe przewagi: slot koncesyjny, linia kredytowa (czy omija scoring i egzekucję?), pojemność magazynu, gwarantowany kandydat headhuntera, anulowanie strat wizerunkowych blowoutu, natychmiastowy certyfikat. W zero-wipe kumulują się bezpowrotnie.
  - Pętla P2W: majątek → „poziom firmy" → wyższy limit Kroplówki → więcej majątku. „Poziom firmy" jest w ogóle niezdefiniowany.
  - Model odnowienia VIP (jednorazowy zakup 150 GBBL? auto-pobranie? subskrypcja operatora?) — różne wymogi prawne.
  - Uzupełnić ceny/limity/czas trwania/zasady utraty dla produktów bez liczb.

- [x] **P0-8. Atomowe rozliczenie każdej operacji gospodarczej** (GPT×2)
  - FTS + stan magazynu + escrow w **jednej transakcji DB** z kluczem idempotencji — inaczej ponowienie joba wypłaci dostawę dwa razy mimo poprawnych pojedynczych zapisów.
  - Wyścigi: realizacja zlecenia limit vs edycja/anulowanie; dostawa kontraktu vs sprzedaż spot tej samej ropy; egzamin/naprawa vs zwolnienie pracownika. Stany terminalne + warunkowe aktualizacje.

---

## B. P1 — WYSOKIE (exploity, balans, reguły domenowe)

> ✅ **Status 09.11.2026 — ETAP 2 WDROŻONY do specyfikacji:** P1-1 – P1-11 zamknięte (P1-10 domknięty w Etapie 1, §22.6). Zmiany: §7 logiczna macierz ochrony (nowy atak `hub_valve_sabotage`, kontra `legal_defense` na donosy/kary, drony na operacje fizyczne) + tabela parametrów sabotaży + limit 5 udanych ataków na ofiarę/7 dni + reguły zmiany stanu celu; §14.1 deduplikacja/linkowanie OAuth, limit pakietu startowego per odcisk tożsamości, detekcja multikont; §14.3 bonus 2FA jednorazowy i odwracalny; §2 zasada propagacji Cred→B2B (50% Δ) + liczby black_market (decay −1/24h, ryzyko kontroli, gotówka podlega egzekucji); §10 rozdział uprawnień + tryb awaryjny przy wakacie dyrektora; §12/§19.3 slippage od skumulowanego wolumenu w oknie 60 min; §4 formuła z clampami, próg żądania <60, deadline 72h+48h, formuła ugody; §5 populacja średniej morale, spójne skutki strajków (logistyka proporcjonalna, techniczny = TTS stop + zewnętrzna ×2.5/×1.4, pat strajku HR rozwiązany mediatorem/dyr. prawnym); §6 wyłączny przydział pracownika, pass rate 70% (+10 p.p. max +30), cap skilli 10, kursy I–III; §19.1 progresywny podatek od CAŁEGO kapitału (bank+cash+escrow+70% aktywów, stawki 0/0,03/0,05/0,08%/dzień); §19.2 mediator min. 25 000 PLN lub `mediator_debt`, urlop z twardymi warunkami + immunitet PvP, podłoga offline (`dormant`, bankructwo dopiero po 14 dniach); §21 pełne reguły `waiting_bay`; §19.4 Frontier Basins + wygasanie koncesji + aukcje opuszczonych aktywów.

- [x] **P1-1. PvP — limity i liczby** (GPT×2, DS, Hermes)
  - Brak: cen sabotaży, cooldownów (na gracza? na cel?), formuły ryzyka wpadki, delty wiarygodności za `dirty_report`, limitu szkody `player_budget_hit`.
  - Limit ataków otrzymywanych przez jedną ofiarę w czasie + reguły równoczesnych ataków wielu agresorów; los opłaconej operacji, gdy cel zmienił stan przed wykonaniem (wszedł pod tarczę, sprzedał cysternę).
  - W zero-wipe nękanie jednej ofiary może być bardziej opłacalne niż rywalizacja — potrzebny mechanizm tłumiący.
  - **Poprawić macierz ochrony §7** (Hermes): `drone_patrol` „wykrywa" kary administracyjne, a `hub_security` „neutralizuje" donos — kontry nielogiczne; donos powinien kontrować dział prawny/PR, drony i ochrona huba — sabotaże fizyczne.

- [x] **P1-2. Multikonta i OAuth** (GPT, DS)
  - Google OAuth jednym kliknięciem = konto z $1M + 20k bbl; brak deduplikacji (ten sam Google ID / e-mail istniejący z rejestracji hasłem — merge czy odmowa?), limitu kont, mechaniki wykrywania (fingerprint, korelacja IP, analiza transferów B2B).
  - Farma pakietów startowych z transferem przez B2B escrow = realny wyciek ekonomii.

- [x] **P1-3. Escrow/urlop/gotówka jako schrony majątku** (GPT×2, DS)
  - Escrow poza zasięgiem komornika (§21) + poza podstawą podatku (§19.1) + brak zasad anulowania B2B = legalny raj do parkowania środków (także między własnymi kontami).
  - Czarny rynek płaci „czystą gotówką" — czy komornik zajmuje portfel `cash`, czy tylko `bank_balance`? Jeśli nie — wektor unikania egzekucji.
  - Tryb urlopowy: zamraża **trwającą awarię** (aktywacja w momencie wypadku = pauza skutków na 30 dni); brak reguł dla aktywnych kontraktów, escrow w transporcie, zleceń limit, strajków i bycia celem PvP na urlopie.
  - Fail-safe mediator: 10% z $0 = $0 → darmowa ochrona przez celowe zerowanie kasy; potrzebna stawka minimalna lub kara.
  - Offline drain: OPEX/pensje lecą, gracz offline 2 tygodnie wraca bankrutem — rozstrzygnąć, czy offline bez urlopu może doprowadzić do bankructwa (twardy floor?).
  - `waiting_bay` $500/h (§21): brak reguł przy braku środków, rezygnacji z kursu, limicie kolejki — cysterna zablokowana na zawsze?

- [x] **P1-4. Rynek — slippage i wyścigi** (GPT, DS, Hermes)
  - Slippage liczony od pojedynczego zlecenia → trywialne obejście przez serializację małych sprzedaży; model ceny od **łącznego wolumenu w oknie czasu**.
  - Doprecyzować „spadek 1.5% na 10 000 bbl" (1.5% czego? narastająco?) i „powrót 1% na 10 min" (1% czego?).
  - „Sprzedaż dowolnej ilości" na spocie vs głębokość księgi NPC — spójny model płynności.

- [x] **P1-5. Strajki i HR — spójność i formuły** (GPT, DS, Hermes)
  - Sprzeczność §5.1: technicy „odmawiają napraw" vs „czas naprawy +40%"; logistyka „30–80%" vs „ropa NIE płynie" — redukcja częściowa czy zatrzymanie? Co determinuje pozycję w zakresie 30–80%?
  - **Pat strajku HR**: strajk HR blokuje mediację, która jest drogą zakończenia strajku — kto negocjuje strajk HR? (Hermes: potrzebny circuit breaker całej kaskady morale→spór→strajk→paraliż HR→lawinowy spadek morale.)
  - Czy firma zewnętrzna 2.5x (§21) omija skutek strajku technicznego?
  - „Średnie morale działu < 35" — populacja (dyrektorzy? szkolący się? `unassigned`? już strajkujący?), ważenie, pusty dział; zwalnianie najbardziej niezadowolonych może sztucznie gasić przesłankę sporu.
  - Brak: progu krytycznego żądania podwyżki, terminu odpowiedzi (deadline odroczenia), zasady „1 otwarte żądanie na pracownika" (duplikaty sporów), kosztu/liczby/terminu rund negocjacji, momentu pobrania pieniędzy i wznowienia pracy, schematu tabeli `employee_dialogue_templates`.
  - `trait_loyalty` 1–10 vs „lojalność +5" (§4.2) — clamp do 10?
  - Rekrutacja: czasy „2–5 minut" [DS] — w grze 24/7 to absurd albo błąd jednostek (godziny?); zakres limitu „2 procesy" (na firmę/rynek/typ) i moment zwolnienia slotu; limity zatrudnienia i obsada wymagana na odwiert/hub/rurociąg.
  - `expected_salary > 0` we wzorze §4.1; brak dolnego clamp `max(0, …)` (Hermes).

- [x] **P1-6. Szkolenia i przydział pracownika** (GPT, DS)
  - Reguła **wyłącznego przydziału**: kurs (24–72h) vs obsada odwiertu vs zadanie TTS; przejścia przy zwolnieniu/strajku w trakcie zadania.
  - Brak: kosztów kursów, bazowego pass rate, unikalności certyfikatu (powtarzalny?), max poziomu sub-skilla, limitu kumulacji bonusu +10%.
  - Kolejność łączenia mnożników ryzyka katastrofy: budżet BHP × poziom procedur 1–5 × `procedure_integrity` × `skill_safety` × mnożnik strajku (×2 blowout z §5.1) — nieokreślona (Hermes+GPT).

- [x] **P1-7. Reputacja — brakujące reguły przeliczania** (GPT, DS)
  - `Cred → B2BRep` i `Cred → ContractRep`: zdefiniowana tylko kara z czarnego rynku („50% spadku") — czy ta reguła dotyczy bankructwa, katastrofy itd.? Czy odbudowa Cred odbudowuje B2B?
  - Redundancja `contract_reputation` vs `b2b_reputation_scores` [DS] — który wskaźnik rządzi dostępem do jakich kontraktów?
  - `credit_score` 0–115: progi (ile kredytu przy jakim score) albo reskala; `bank_trust_scores` (l.mn.) — wskaźnik czy wektor? mechanika „negocjacji umorzeń" nieopisana.
  - Formuła decay `black_market_score`; mechanika „kontroli skarbowych, nalotów, blokady rachunków" (prawdopodobieństwo, czas, zakres).
  - 2FA +5 wiarygodności (§14): **jednorazowe, odwracalne przy wyłączeniu** — inaczej farmienie przez włącz/wyłącz (GPT×2, DS); „status zaufanego kontrahenta B2B" niezdefiniowany i nieuzasadniony (zabezpieczenie konta ≠ rzetelność dostaw).

- [x] **P1-8. Uprawnienia i rozdział operacji** (GPT)
  - `/finance` wymaga dyrektora finansowego, ale sprzedaż spot i koszty stałe działają bez niego — rozróżnić: podgląd / ustawianie budżetu / podpisywanie kontraktów / operacje automatyczne. Utrata dyrektora nie może blokować ratowania firmy ani czynić kontroli dostępu pozorną.

- [x] **P1-9. Podatek majątkowy** (GPT×2, DS — z korektą rachunku)
  - „Progresywny" z jedną stawką 0,05%/dzień — dodać prawdziwe progi progresji.
  - Podstawa: samo saldo bankowe czy cały majątek (gotówka w portfelu, escrow, złoża/sprzęt)? Dziś cash i escrow są poza podatkiem = obejście.

- [x] **P1-10. Automatyzacja VIP z walidacją stanów** (GPT)
  - Pętla cystern musi przy KAŻDYM kursie sprawdzać stan cysterny/ładunek/strike/zapełnienie bufora; kolejka TTS musi rezerwować konkretnego technika — inaczej premium obchodzi fizyczne limity i wykonuje wiele prac jedną osobą.
  - Cykl życia uprawnień po wygaśnięciu VIP / zwrocie płatności / korekcie GM: co z pętlami w toku, 5 zadaniami w kolejce, 15 ofertami limit (nadmiarowe anulować i zwrócić zablokowaną ropę?).

- [x] **P1-11. Zero-wipe: catch-up i lifecycle świata** (Hermes)
  - Brak mechanizmu dla graczy dołączających po latach (świat opanowany przez kartele, tanie złoża wyczerpane): wygasanie/odnawianie koncesji, nowe regiony frontowe odblokowywane czasowo, bonusy catch-up.
  - Cele długoterminowe/osiągnięcia/leaderboardy jako zamiennik progresji sezonowej („historia odznaczeń" wspomniana raz, bez designu).

---

## C. P2 — ŚREDNIE (techniczne, bezpieczeństwo, operacje)

> ✅ **Status 09.11.2026 — ETAP 3 WDROŻONY:** P2-1 (sanityzacja CMS §15.4–15.5, CSP, RODO §16.4–16.5: eksport/anonimizacja, retencja, Matomo), P2-2 (hasła NIST min. 12 + HIBP §14.2, throttle per konto bez blokady NAT, trusted_devices per token + step-up §14.5), P2-3 (§28: kanały Reverb z autoryzacją, payload minimalny, after_commit, fallback polling, kontrakt propsów Inertia, nawigacja przez `navigation.updated` §18.2), P2-4 (§18.3 macierz GM 3 role + four-eyes + `gm_action_log`; §18.4 namespace auto-discovery, `is_active` = tylko nawigacja z blokadą przy procesach krytycznych, transakcyjny Reset stanu), P2-5 (§29.4 partycjonowanie roczne + archiwum zimne + retencje), P2-6 (§29.1 expand-migrate-contract, rolling deploy, snapshot+binlog, staging), P2-7 (§29.3 metryki ticka + telemetria growa), P2-8 (§29.5 wersjonowanie `game_balance` per tick, stany w toku wg wersji startowej), P2-9 (§23.4 — wdrożony w Etapie 1), P2-10 (Chart.js §12.3, uploady re-encoding + signed URL §14.6, mockup spójny §1.1), P2-11 (§24.4 konwencje: UTC, BIGINT grosze, enumy VARCHAR, JSON vs norm.), P2-12 (§23.7: 12 scenariuszy acceptance blokujących release).

- [x] **P2-1. Sanitizacja CMS i RODO-detal** (GPT×2, DS)
  - `content_html` renderowany w SPA bez specyfikacji sanitizacji = **trwałe XSS** (błąd edytora lub przejęte konto admina); wymagany purifier na wejściu i wyjściu + CSP.
  - i18n CMS: brak pól/reguł fallbacku dla `title`/`slug`/`content_html` w PL/EN/DE.
  - Odmowa cookies „funkcjonalnych" musi usuwać istniejący token Remember-Me; `policy_version` — gdzie przechowywany; narzędzie analityczne (GA/Matomo) — osobna integracja RODO.
  - Brak: prawa do usunięcia/eksportu danych, retencji logów (`cookie_consents_log`, `company_credibility_log`, FTS).

- [x] **P2-2. Auth — sesje, urządzenia, hasła** (GPT, DS, Hermes)
  - `trusted_devices` po IP+przeglądarce to nie identyfikator sesji — zdalne wylogowanie wymaga powiązania z ID sesji/tokena + wygaśnięcie + step-up przy wrażliwych operacjach.
  - Hasło min 6 znaków — podnieść (DS: NIST — długość ≥12, bez klas znaków, + check listy wycieków); przy zero-wipe przejęcie konta = utrata dorobku życia.
  - Blokada IP po 10 próbach logowania i po 5 próbach 2FA = wektor DoS na graczy za wspólnym NAT — throttle na konto, nie na IP.

- [x] **P2-3. Reverb / Inertia — kontrakt real-time** (GPT, DS)
  - Autoryzacja kanałów (prywatne per właściciel/uczestnik; publiczne tylko notowania), payload bez całych modeli, emisja **po commicie** transakcji (`after_commit`), fallback gdy WS niedostępny.
  - Inertia nie jest real-time: jak dane ticka (produkcja, OPEX, awarie, odliczanie) trafiają do Vue między nawigacjami — WS czy polling? Kontrakt shared props (`$page.props`).
  - §18.2 „zmiana natychmiast u wszystkich graczy" jest fałszywa dla otwartych stron Inertii — potrzebny broadcast + listener/refetch albo zmiana obietnicy UX.

- [x] **P2-4. Filament / GM — uprawnienia i operacje** (GPT×2, DS)
  - Macierz uprawnień **per akcja GM** (kto koryguje saldo, umarza dług, wywołuje katastrofę) + niezależny audyt wykonań; auto-discovery wymaga konwencji namespace'ów modułów; „100% parytetu z panelem oryginalnym" — „oryginalny panel" nigdzie nieopisany.
  - GM „Reset stanu": reset samych flag odtworzy skutek przez stare joby — procedura transakcyjna z wygaszeniem procesów zależnych (raty, licytacje, ultimatum, przypisane zadania, oczekujące akcje).
  - Korekty GM w FTS: konto przeciwstawne (double-entry!) + typ `admin_adjustment`.

- [x] **P2-5. Dane w zero-wipe — retencja i wzrost** (GPT×2, DS)
  - Niezmienne rejestry (FTS, credibility_log, finance_decisions, consents, historia cen, escrow, transport) rosną bez końca — setki GB/rok: partycjonowanie, archiwizacja, polityka retencji, procedura odtwarzania salda/projekcji i uzgadniania księgi po awarii.

- [x] **P2-6. Deploy, backup, środowiska** (Hermes)
  - Tick w trakcie migracji (pauza/okno serwisowe), rollback gdy gracze mid-transakcja, staging z seedowanym światem, polityka snapshotów, zero-downtime.

- [x] **P2-7. Obserwowalność i telemetria balansu** (Hermes)
  - Metryki ticka (czas, opóźnienia kolejki, WS connections), alerty, telemetria growa (lejki: strajki, FTUE, pierwsza sesja) — balans po starcie bez tego będzie ślepy.

- [x] **P2-8. Wersjonowanie konfiguracji balansu** (Hermes)
  - Wszystkie liczby (progi, ceny, cooldowny, mnożniki) w config/DB z wersją i audytem zmian; korekta mid-season nie może psuć stanów w toku (strajk rozpoczęty pod starymi progami).

- [x] **P2-9. Architektura RNG** (Hermes)
  - Jeden serwis losowania z seedem per tick (determinizm/replay), polityka anty-reroll (anulowanie rekrutacji/szkolenia w celu ponownego losowania), weryfikowalność rzutów dla gracza („dlaczego egzamin oblany?").

- [x] **P2-10. Frontend — decyzje** (Hermes, GPT, DS)
  - Chart.js **lub** TradingView (§12 wymienia oba); kontrakt propsów Inertia; proces rekrutacji 2–5 min musi przeżywać zamknięcie karty (stan po stronie serwera); uploady: re-encoding (stripping EXIF/payloadów), storage prywatny + signed URL; mockup: spójne dane demo (0/3 aktywnych odwiertów przy produkcji 333 bbl/h; „Admin" ze 100M vs starter 500k — oznaczyć jako przykład).

- [x] **P2-11. Konwencje danych** (Hermes, DS)
  - Decyzja: kolumny JSON (`technical_staff_skills`, `bank_trust_scores`) vs tabele znormalizowane; wzorzec czasu (tick/timestampy UTC, display w strefie gracza); nazewnictwo (l.poj./mn., snake_case, enumy); schemat FTS (pola: strony, kwota, waluta, typ, powód audytowy).

- [x] **P2-12. Testy scenariuszowe — wymagane przypadki** (GPT×2, Hermes)
  - Równoległe webhooki; refund po wydaniu GBBL; koniec VIP w trakcie operacji; zakupiona gotówka w PvP; escrow podczas egzekucji; podwójna sprzedaż tej samej ropy (B2B+spot); ponowienie joba rozliczającego dostawę; headless symulator ticka (deterministyczny seed) + golden-master formuł; scenariusz „1000 ticków bez graczy = stabilne ceny".

---

## D. P3 — PRODUKT / DOKUMENT

> ✅ **Status 09.11.2026 — ETAP 3 WDROŻONY:** P3-1 (§25 silnik `world_events`: harmonogram, 24h okno reakcji, max 2 globalne, modyfikatory), P3-2 (§26 kanały czatu + moderacja + kartele 2–5 firm z paktem nieagresji i limitem 40% produkcji), P3-3 (§27.1 FTUE: tutorial 5 kroków, tryb nauki 48h), P3-4 (§27.2 centrum powiadomień: 4 kategorie, `critical` zawsze e-mail, deep-linki, deduplikacja), P3-5 (§27.3 osiągnięcia bez przewag liczbowych, leaderboardy, Legacy), P3-6 (§30 ToS, zakaz RMT/handlu kontami, granice griefingu, bany + odwołania, konta porzucone), P3-7 (tabele liczb: §7.1 sabotaże, §9.5 wydobycie, §10 formuły liquidity/radar, §17.1 scoring, §2 decay/kontrole, §22.6 Tiery, §24.3 Kroplówka — reszta jako config `game_balance` do kalibracji §23.7).
> ⚠️ **P3-8 częściowo:** i18n dialogów PL/EN/DE ✅, drzewo modułów + Cms/Privacy ✅, mapa moduł↔trasy↔Filament↔tabele (§8.2) ✅, urwany box §19.1 ✅, mockup §1.1 ✅, konwencja ID wymagań (§24.4) ✅, macierz §21 rozszerzona do 15 par (cel ~30 — dopisywać przy implementacji). **ZAMKNIĘTE 14.11.2026:** plik przemianowany na `OILEMPIRE_ARCHITEKTURA_SYSTEMOWA.md`; tytuł dokumentu `OilEmpire:`; odwołania zaktualizowane w 3 raportach i skillu `oilcorp-laravel-audit`; 0 wystąpień `OilCorp` w dokumencie. → P3-8 ZAMKNIĘTY (39/39).

- [x] **P3-1. Silnik wydarzeń światowych** — Boom/Pandemia/Embargo istnieją jako „trendy", banner z odliczaniem w §1.1; brak specyfikacji: harmonogram vs losowanie vs GM, czas trwania, zasięg, stackowanie, zapowiedzi (okno reakcji graczy).
- [x] **P3-2. Czat i dyplomacja** — Reverb „czat" bez specyfikacji: kanały (globalny/regionalny/DM/firmowy), moderacja (filtry, muty, zgłoszenia); rozważyć sojusze/bloki handlowe — dziś jedyna interakcja poza B2B to sabotaż (gra czysto antagonistyczna wyniszcza serwer).
- [x] **P3-3. Onboarding/FTUE** — 6 wskaźników reputacji, 3 rynki pracy, eskalacje strajkowe = wysoki próg wejścia; zaprojektować pierwsze 30 minut, tutorial, podpowiedzi kontekstowe.
- [x] **P3-4. Centrum powiadomień** — kategorie + preferencje per kanał (in-app/e-mail/push); dziś tylko e-mail w §19.2; spójne z polityką offline.
- [x] **P3-5. Meta-progresja** — osiągnięcia, leaderboardy (per oś: majątek/wydobycie/reputacja), nagrody/prestiż przenoszony (zero-wipe → to jedyna progresja długoterminowa).
- [x] **P3-6. ToS i polityki** — bany i odwołania, handel kontami, granica griefingu PvP, RMT; jawna decyzja o monetyzacji zapisana w dokumencie (teraz §22 istnieje — spójność z fair-play P0-7).
- [x] **P3-7. Tabela brakujących liczb** (do wypełnienia przy §9/§17 i balansie) — ceny i cooldowny sabotaży, kary wiarygodności sabotaży, formuła kaucji/kar kontraktowych, koszty kursów i bazowe pass rate'y, progi credit_score, wagi negocjacji płacowych, deadline'y, determinanta 30–80% strajku logistyki, stawki OPEX, parametr decay black_market, stawka/pojemność mediowania, kurs Kroplówki, „poziom firmy".
- [x] **P3-8. Kosmetyka i struktura dokumentu**
  - i18n: dialogi „dwujęzyczne" (§5.2) vs PL/EN/DE (reszta) — ujednolicić.
  - Drzewo modułów §8: dodać `PrivacyCompliance` i `CMS` (albo adnotacja, że w Core).
  - Nazwa pliku `OILCORP_…` → `OILEMPIRE_…`; tytuł dokumentu spójny z rebrandem.
  - Urwany tekst w boxie faucets/sinks §19.1; literówki („Blue Barrel" → standard to *oil barrel*, bbl).
  - Wprowadzić **ID wymagań** (np. `HR-RAISE-02`) + mierzalne kryteria akceptacji per funkcja.
  - Załącznik: mapa moduł ↔ URL ↔ Filament Resource ↔ tabele ↔ kanały Reverb.
  - Rozbudować §21 do pełnej macierzy (~30 par modułów), nie 6 przykładów.
  - Dodać do słownika: „panel oryginalny", „poziom firmy", „Radar 5 filarów" (wejścia/normalizacja/wagi), „wskaźnik pokrycia kosztów w godzinach" (formuła), zachowanie „rezerwy awaryjnej".

---

## TOP 10 — kolejność wykonywania (synteza wszystkich przeglądów)

| # | Poprawka | Pozycje |
|---|----------|---------|
| 1 | Tick engine + OPEX dt-based + transakcje/idempotencja | P0-1, P0-8 |
| 2 | Jedna waluta + plan kont PLN/GBBL/gra + kursy | P0-2 |
| 3 | §9 Extraction i §17 Banking/Incidents (w tym cały moduł bankructwa/komornika) | P0-3 |
| 4 | Escrow/B2B: rezerwacja ropy, timeouty, spory, kaucje + zamknięcie schronów majątku (escrow/cash/urlop) | P0-4, P1-3 |
| 5 | Decyzja P2W + maszyna stanów płatności + księga GBBL | P0-6, P0-7 |
| 6 | Tarcza startowa (próg + dwukierunkowość) + anty-multikonto/OAuth | P0-5, P1-2 |
| 7 | PvP: liczby, limity per ofiara, logiczna macierz ochrony | P1-1 |
| 8 | Spójność strajków/HR + wyłączny przydział pracownika + formuły negocjacji | P1-5, P1-6 |
| 9 | Rynek: slippage od łącznego wolumenu + atomowość limitów | P1-4, P0-8 |
| 10 | Bezpieczeństwo techniczne: sanitizacja CMS, sesje/2FA, Reverb po commicie, uprawnienia GM | P2-1, P2-2, P2-3, P2-4 |

**Zasada nadrzędna:** punkty 1–5 to decyzje architektoniczno-ekonomiczne, których nie da się odwrócić po starcie żywego świata zero-wipe — resztę można dopisywać równolegle z implementacją silnika.
