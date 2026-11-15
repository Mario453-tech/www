# OilEmpire — ślepa recenzja DeepSeek v4 Pro (reasoning: high, provider: alibaba-token-plan)

Data: 2026-11-09. Przegląd CAŁOŚCI §1–§22, wykonany bez dostępu do ustaleń GPT (porównanie ślepe).

---

1. NIESPÓJNOŚCI WEWNĘTRZNE1.1. Brakujące sekcje dokumentu
- §9 nie istnieje (przeskok z §8 do §10)
- §17 nie istnieje (przeskok z §16 do §18)
- Nie wiadomo, czy to celowe (placeholder), czy błąd edycyjny.

1.2. Waluta: dolar vs PLN — chaos
- §1 dashboard: "100 172 456 PLN" i "0,00 PLN" na saldzie bankowym- §1 pakiet startowy: "$500,000 w gotówce + $500,000 na koncie bankowym"
- §12 Rynek: cena ropy w "\$/bbl", wypłata trafia "na konto bankowe"
- §19 podatek: "powyżej \$50,000,000 na rachunku bankowym" (dolar)
- §21 opłata postojowa: "\$500/h" (dolar)
- §22 sklep: ceny w PLN za GBBL
- Problem: Czy gospodarka gry operuje w PLN, USD, czy są dwa równoległe obiegi? Nigdzie nie zdefiniowano przewalutowania ani kursu walutowego. Implementacja FTS bez rozstrzygnięcia tego jest niemożliwa.

1.3. Dashboard mockup — liczby niezgrane ze światem
- §1 dashboard pokazuje "0 / 3 Aktywne odwierty" — ale jednocześnie "Produkcja: 333 bbl/h (+44.1%)". Nie da się mieć produkcji przy zerze aktywnych odwiertów.
- Dashboard pokazuje "100 172 456 PLN" gotówki — postać demo "Admin" ma 100 mln, ale nowy gracz dostaje $500k. Te liczby nie są oznaczone jako "przykład po X godzinach gry".

1.4. Dwa systemy reputacji handlowej — dublowanie
- §2: contract_reputation (0-100) — "Kary i kaucje w kontraktach systemowych"
- §2: b2b_reputation_scores (0-100%) — "Zaufanie graczy na giełdzie kontraktów"
- §13: b2b_reputation_scores 0-100% ponownie przy giełdzie B2B
- Problem: Jaka jest relacja między contract_reputation a b2b_reputation_scores? Czy kontrakty systemowe i B2B współdzielą reputację? Dwa osobne wskaźniki 0-100 dla "reputacji kontraktowej" to redundancja — programista nie wie, który użyć przy sprawdzaniu dostępu do kontraktu.

1.5. Credit score — niespotykana skala
- §2: credit_score: 0-115 pkt. Dlaczego 115? Żadna sekcja nie definiuje, co oznaczają konkretne progi (np. 50 = dostajesz kredyt do X PLN, 100 = do Y PLN). Wartość 115 wygląda na przypadkową.

1.6. Tarcza startowa vs pakiet startowy — natychmiastowe wygaśnięcie
- §19.2: Tarcza startowa trwa "7 dni lub do osiągnięcia majątku netto \$2,000,000"
- §1: Pakiet startowy: "$500,000 + $500,000 + 20,000 bbl × cena rynkowa"
- Przy cenie ropy \~$62/bbl, sam magazyn wart jest $1,240,000. Łącznie z gotówką: $2,240,000. Tarcza startowa wygasa w momencie rejestracji, zanim gracz cokolwiek zrobi. To czyni cały mechanizm martwym.

1.7. Podatek majątkowy — zbyt niski, by cokolwiek zmienić
- §19.1: 0.05% dziennie od nadwyżki powyżej $50M. Przy $60M na koncie to $5 dziennie kary. To nie motywuje do reinwestowania — to błąd zaokrąglenia. Albo stawka jest źle zapisana (miało być 0.5%? 5%?), albo mechanizm nie spełnia deklarowanej roli sinks.

1.8. Czas rekrutacji — minuty zamiast godzin
- §3.1: czasy rekrutacji "2-4 minuty", "3-5 minut". W grze trwałej 24/7 to absurdalnie krótko. Tick symulacyjny może nawet nie zdążyć przetworzyć jednej rekrutacji, zanim gracz zatrudni 100 pracowników w ciągu godziny. Albo to błąd jednostek (godziny?), albo rekrutacja jest natychmiastowa de facto.



2. LUKI BLOKUJĄCE IMPLEMENTACJĘ### 2.1. Finanse / FTS- §20 definiuje FTS jako "atomowy rejestr z podwójnym zapisem (debit/credit)". Brak schematu tabeli financial_transaction_service, brak specyfikacji pól (od kogo, do kogo, kwota, waluta, typ operacji, powód audytowy). Programista nie wie, czy to jedna tabela z debit_account/credit_account, czy dwie.
- §10: "Wskaźnik pokrycia kosztów w godzinach" — brak formuły. Jak obliczane są "koszty stałe na godzinę"? Z sumy OPEX? Z prognozy?
- §10: "Radar oceniający 5 filarów" — co jest wejściem do radaru? Każdy filar potrzebuje normalizacji 0-100 i wagi. Ani słowa o tym.
- §10: "Ustawienie docelowej rezerwy awaryjnej" — co system robi z tą wartością? Blokuje środki? Wysyła alerty? Nie wiadomo.

2.2. Reputacja i scoring- §2: black_market_score — "powoli opada z czasem (decay skalowany czasem)". Brak formuły decay: ile punktów na godzinę/dzień? Czy jest liniowy? Czy zależy od poziomu score?
- §2: bank_trust_scores (liczba mnoga!) — czy to pojedynczy wskaźnik czy wektor? W diagramie: "Skuteczność negocjacji umorzeń" — ale ani słowa dalej o mechanice umorzeń.
- §2: Relacja Cred -→ B2BRep i Cred -→ ContractRep — w diagramie strzałki, w tekście zero formuł. O ile spadek credibility obniża reputację B2B? Nie wiadomo.

2.3. HR, morale, strajki — brakujące formuły- §4.2: "Probabilistyczny silnik negocjacji" — zero wzoru na szansę ugody. Jakie są wagi dla: dyrektor HR, specjalista salary_negotiator, lojalność, ambicja? Bez tego nie da się zakodować kontroferty.
- §4.2: "Odroczenie decyzji — deadline" — jaki deadline? Nie podano czasu.
- §5: "Średnie morale działu < 35 pkt" — jak liczona jest średnia? Ważona liczbą pracowników? Którzy pracownicy wchodzą do średniej (tylko aktywni? również w sporze?)?
- §5.1: "Przepustowość hubów spada o 30-80%" — co determinuje, czy spadek wyniesie 30% czy 80%? Czas trwania strajku? Liczba strajkujących?
- §5.2: "Baza 80+ dialogów" i "Wynik rundy zależy od wysokości zaoferowanej kwoty, talentu dyrektora HR i zaufania komitetu". Brak schematu tabeli employee_dialogue_templates (pola, typy, jak są wybierane szablony, jak mapowane na wyniki).
- §3: Ile maksymalnie pracowników może być zatrudnionych? Limit? Na co? Ilu potrzeba na jeden odwiert/hub/rurociąg?

2.4. PvP / Sabotaż- §7: "Wymaga opłaty w gotówce" — brak ceny każdego typu sabotażu. Bez tego ekonomiści nie zbilansują kosztu vs korzyści z kradzieży X bbl ropy.
- §7: "Czas odnowienia (cooldown)" — nie podany. Godzina? Dzień? Na gracza? Na cel?
- §7: "Ryzyko wpadki" — brak formuły. Czy zależy od black_market_score agresora? Od ochrony ofiary? Od czegoś jeszcze?
- §7: "Potężna kara do company_credibility" — jaka konkretnie wartość? §2 mówi -12 za wykrycie czarnego rynku, ale to dotyczy sprzedaży, nie sabotażu. Sabotaż nie ma zdefiniowanej kary.

2.5. Kontrakty / Escrow
- §13.1: "Kaucja (security_deposit)" — brak formuły. Procent wartości kontraktu? Stała kwota? Zależna od contract_reputation?
- §13.1: "Kara umowna" za niewywiązanie się — jaka? Procent kaucji? Kwota? Formuła?
- §13.2: Escrow B2B — co się dzieje, gdy sprzedający nie dostarczy ropy? Po jakim czasie escrow wygasa i środki wracają do kupującego? Brak timeoutu i procedury sporu.
- §13.2: "Oceny i reputacja handlowa B2B" — brak mechanizmu oceniania. Czy kupujący ocenia sprzedającego po transakcji? Skala? Automatycznie czy ręcznie?

2.6. Bank / Komornik
- §18.1: "Postępowania komornicze: kwoty zajęte z giełdy ropy" — ale nigdzie wcześniej nie opisano, jak komornik zajmuje środki. Jaki jest próg zadłużenia uruchamiający komornika? Jak działa egzekucja z giełdy (procent od każdej sprzedaży? blokada całości?).
- Brak całej sekcji o bankructwie — tylko wzmianki w §2 (kara -25 do credibility), §18.1 (tryb naprawczy), §21 (licytacja odwiertu). Brakuje pełnego opisu mechaniki bankructwa: progi, etapy, restrukturyzacja, licytacja majątku.

2.7. Tick / symulacja 24/7
- Największa luka w dokumencie: nie ma ani słowa o architekturze ticka. Jak działa symulacja świata 24/7? Cron co minutę? Laravel job na Redis? Scheduler? Jaka jest częstotliwość przeliczania: produkcji, zużycia, OPEX, morale, decay czarnorynkowego?
- §20: "OPEX pobierane płynnie co sekundę" — pobieranie co sekundę z bazy na setkach graczy to absurd skalowalny. Musi być tick interval i formuły na ułamki ticka.
- Bez specyfikacji ticka nie da się zaprojektować tabel, indeksów, kolejek ani testów obciążeniowych.

2.8. Auth / 2FA- §14.1: Google OAuth — "nowy gracz otrzymuje natychmiast konto z pakietem startowym". Brak logiki deduplikacji: co, gdy ten sam Google ID już ma konto? Co, gdy email już istnieje z rejestracji hasłem?
- §14.3: 2FA dla gracza — "bonus +5 pkt credibility". Można włączyć 2FA, dostać +5, wyłączyć, włączyć ponownie? Brak ograniczenia — to exploit na reputację.

2.9. CMS / RODO
- §15: static_pages.content_html — brak polityki sanitizacji. WYSIWYG + renderowanie w SPA = XSS, jeśli HTML nie jest czyszczony przed zapisem/odczytem.
- §16: "Wyświetlany nowym użytkownikom lub po podbiciu wersji polityki" — brak mechanizmu wersjonowania w cookie_consents_log. Gdzie trzymane jest policy_version?

2.10. Monetyzacja i GBBL- §22.3: "Max 1 wymiana na dobę skalowana poziomem firmy" — co to jest "poziom firmy"? Ani słowa wcześniej. I jak skaluje limit (np. poziom 1 = 1 wymiana, poziom 10 = 3 wymiany)?
- §22.3: "Pakiety Zastrzyku Gotówki" — wymiana GBBL na PLN. Jaki jest kurs wymiany? Ile PLN za 1 GBBL? Bez tego nie da się wycenić ani zaimplementować.

2.11. Szkolenia- §6: Brak kosztów kursów. "Opłata za kurs (płatna z góry)" — ale jaka? Każdy kurs ma własną cenę? Jak ustalaną?
- §6: "Rzut kostką vs Pass Rate" — co jest bazowym pass rate? Formuła?
- §6: "Bonus powtórkowy: +10% pass rate" — czy kumuluje się przy kolejnych oblaniach?

2.12. Admin / GM- §18.1: "Korekta środków finansowych: zmiana salda przez FTS" — czy FTS obsługuje ręczne wpisy administracyjne? Jeśli tak, jak są oznaczane (osobny typ transakcji admin_adjustment)?
- §18.2: "Zmiana nazwy lub dodanie podstrony natychmiast odzwierciedla się na ekranach wszystkich graczy" — czy to przez WebSocket push? Inertia nie ma wbudowanego pusha do wszystkich klientów. Potrzebny broadcasting + listener na froncie. Nieopisane.



3. WEKTORY EKSPLOITACJI I PROBLEMY BALANSU### 3.1. Ekonomia — faucets vs sinks
- §19.1: Podatek 0.05% dziennie to farsa. Opisany jako "progresywny", ale nie ma progów — jest jeden próg $50M. Brak realnego sink dla dużych korporacji.
- OPEX jako sink: nie ma stawek. Bez nich nie da się ocenić, czy gospodarka jest zbilansowana, czy gracze będą tonąć w gotówce po miesiącu.
- Wyczerpywanie złóż: "po 70% zasobu spadek 1.5% na każde 10 000 bbl" — to sink rozłożony na miesiące/ lata realnej gry. Nie ratuje krótkoterminowego balansu.

3.2. Tarcza startowa — exploit z natychmiastowym wygaśnięciem
- Jak wykazano w 1.6: tarcza znika przy starcie. Nowy gracz jest goły na PvP od pierwszej sekundy. To nie jest "ochrona nowych graczy".

3.3. Czarny rynek — brak realnego ryzyka- §2: "Gdy jest wysoki, zwiększa ryzyko kontroli skarbowych, nalotów i blokady rachunków" — ale zero mechaniki: jakie jest prawdopodobieństwo kontroli? Co to znaczy "blokada rachunków" (na ile? całości czy części?)?. Gracz może kalkulować, czy wyższa cena na czarnym rynku przebija ryzyko — ale bez liczb nie da się.
- §12.4: "Płatne w czystej gotówce" — to omija mechanizm banku. Jeśli gracz trzyma wszystko w gotówce, komornik nie ma czego zająć? To wektor na unikanie egzekucji.

3.4. Escrow — griefing i zamrażanie kapitału- §13.2: Gracz może wystawić ofertę skupu, zamrozić środki w escrow, a sprzedający nigdy nie dostarczy. Brak timeoutu na escrow — środki kupującego zamrożone na zawsze? To wektor griefingu: gracz A składa ofertę graczowi B, B akceptuje, A nie dostarcza — kapitał B zablokowany.
- §21: "Środki w depozycie Escrow są prawnie wydzielone — komornik nie może ich zająć" — escrow jako legalny raj podatkowy do chowania pieniędzy przed komornikiem. Gracz z długami wystawia ogromną ofertę kupna swojemu drugiemu kontu — pieniądze bezpieczne.

3.5. Offline / urlop — tarcza do nadużyć
- §19.2: "Tryb urlopowy: produkcja, pensje i koszty stałe zamrożone". Gracz może aktywować urlop na 30 dni w momencie, gdy jego odwiert ma wypadek — zamrozić awarię, unikając jej skutków.
- §19.2: "Fail-Safe Circuit Breaker: automatycznie zatrudnia Mediatora Rządowego, płacąc 10% rezerwy gotówkowej". Jeśli gracz celowo trzyma gotówkę na poziomie $0, rezerwa = $0, 10% z $0 = $0. Ochrona za darmo. Brak minimalnej opłaty.

3.6. Multikonta- §14.1: Google OAuth — jedno kliknięcie = nowe konto z pakietem $1M + 20k bbl. Zero bariery. Multikonta do farmienia pakietów startowych i przekazywania (jak? może przez B2B escrow?) są trywialne.
- §18: "Wykrywanie multikont i nadużyć" w Centrum Nadzoru Sabotaży — wymienione, ale zero mechaniki wykrywania (IP? fingerprint? shared cookies? analiza wzorców transferów?).

3.7. GBBL/ monetyzacja vs fair play- §22.3: "Błyskawiczny Certyfikat: natychmiastowe ukończenie szkolenia" — w grze PvP to P2W. Gracz płacący omija 72h blokady szkoleniowej.
- §22.3: "Prywatna Straż Przemysłowa: ugaszenie blowoutu bez strat wizerunkowych" — P2W: omija całą mechanikę katastrof i kar reputacyjnych.
- §22.3: "Kroplówka Kapitałowa" z limitem "skalowanym poziomem firmy" — jeśli poziom zależy od majątku, to P2W: płacący gracz ma większy majątek → wyższy poziom → większy limit wymiany → jeszcze więcej majątku. Pętla.



4. RYZYKA TECHNICZNE

4.1. Tick i symulacja 24/7- Brak architektury ticka to największe ryzyko techniczne całego projektu. Bez definicji interwału, kolejności operacji (produkcja → transport → zużycie → OPEX → sprawdzenie magazynu → paused_storage), mechanizmu blokad na rekordach — system będzie miał race conditions przy każdej operacji symulacyjnej.
- Przy 1000 graczy × 10 odwiertów × tick co minutę = 10 000 aktualizacji na tick. Bez odpowiedniego batchowania i kolejek to utopi Laravela.

4.2. OPEX "co sekundę"
- §20: "pobierane płynnie co sekundę". Implementacja: albo tick co sekundę (nierealne), albo naliczanie różnicy od ostatniego ticka (dt). Każde podejście wymaga precyzyjnego timestampowania i idempotencji, żeby przy restarcie serwera nie pobrać podwójnie. Zero mechanizmu w dokumencie.

4.3. WebSocket / Reverb
- §1: "odliczanie do ticka, live feed giełdowy" — jak wygląda autoryzacja kanałów Reverb? Które dane lecą przez WebSocket, a które przez Inertia reload? Brak specyfikacji eventów, payloadów, fallbacku gdy WebSocket niedostępny.
- §18.2: "Zmiana nazwy natychmiast odzwierciedla się u wszystkich graczy" — wymaga broadcast event + nasłuch w każdym otwartym SPA. Nieopisane.

4.4. Inertia.js — ograniczenia w grze real-time
- Inertia nie jest frameworkiem real-time. Każda wizyta strony to pełny request HTTP + JSON payload + render Vue. Tick gry nie może czekać na odświeżenie strony. Jak dane ticka (produkcja, OPEX, stany awarii) trafiają do komponentów Vue między nawigacjami? WebSocket? Polling? Nic nie powiedziano.

4.5. Transakcje i idempotencja
- §22.2: Idempotencja webhooków przez transaction_id — ok.
- §20: FTS "atomowy" — ale czy używa DB::transaction() Laravela? Co z operacjami rozproszonymi (produkcja na tick + naliczenie OPEX + sprawdzenie magazynu + ewentualne paused_storage)? Jedna transakcja per tick per gracz? Globalny lock?
- Brak strategii retry dla nieudanych webhooków.

4.6. Wzrost bazy w zero-wipe
- §20: company_credibility_log — "każda zmiana audytowana". Przy setkach graczy i ticku co minutę, setki wpisów dziennie.
- player_finance_decisions — każda zmiana polityki.
- cookie_consents_log — każda zmiana zgód.
- financial_transaction_service — każda transakcja spot, kontrakt, kara, podatek.
- Bez polityki retencji i archiwizacji, baza urośnie do setek GB w ciągu roku. Zero słowa o tym.

4.7. Bezpieczeństwo
- §15: CMS z content_html — brak wzmianki o HTML purifier / sanitizacji przy zapisie i renderowaniu. Wektor XSS przez panel admina.
- §16: Analityczne cookies wymienione, ale bez konkretnego narzędzia. Google Analytics? Matomo? Każde wymaga osobnej integracji RODO.
- §14.2: Hasło min 6 znaków — skandalicznie niskie minimum. Bruteforce 6-znakowego hasła to minuty. Nawet z 2FA, to najsłabsze ogniwo.

4.8. Filament v3- §18: "Wszystkie moduły automatycznie rejestrują swoje zasoby w Filament". W module modularnym monolicie, Filament musi skanować modules/*/src/Filament/ każdego modułu. Auto-discovery działa na sztywnych namespace'ach. Brak opisu konwencji namespace'ów dla modułów.
- "100% parytetu z panelem oryginalnym" — co to jest oryginalny panel? Poprzednia wersja OilEmpire? Nigdzie nieopisana.



5. TOP 10 NAJWAŻNIEJSZYCH POPRAWEK

#: 1
Problem: Architektura ticka 24/7: interwał, kolejność operacji, blokady, batch
  processing, idempotencja
Sekcja: brak (największa luka)
Priorytet: KRYTYCZNY
────────────────────────────────────────
#: 2
Problem: Rozstrzygnięcie waluty: PLN vs USD vs dual-currency; przewalutowanie,
  kurs
Sekcja: §1, §10, §12, §19, §22
Priorytet: KRYTYCZNY
────────────────────────────────────────
#: 3
Problem: Tarcza startowa: $2M próg do zmiany na min. $5M-10M lub liczone BEZ
  pakietu startowego
Sekcja: §19.2 vs §1
Priorytet: WYSOKI
────────────────────────────────────────
#: 4
Problem: Formuły negocjacji płacowych i strajkowych: szansa ugody, deadline
  odroczenia, wpływ skilli, spadek morale
Sekcja: §4, §5
Priorytet: WYSOKI
────────────────────────────────────────
#: 5
Problem: Mechanika escrow B2B: timeout dostawy, procedura sporu, zwrot
  środków, ochrona przed griefingiem
Sekcja: §13.2, §21
Priorytet: WYSOKI
────────────────────────────────────────
#: 6
Problem: Kompletny moduł bankructwa i komornika: progi, etapy, egzekucja z
  giełdy, licytacja, restrukturyzacja
Sekcja: §2, §18.1, §21 (rozproszone)
Priorytet: WYSOKI
────────────────────────────────────────
#: 7
Problem: Ceny i koszty: sabotaże (§7), szkolenia (§6), kursy (§6),
  kontrakty/kaucje (§13), poziomy BHP (§11), budżety działowe (§10)
Sekcja: wiele sekcji
Priorytet: WYSOKI
────────────────────────────────────────
#: 8
Problem: Polityka haseł: minimum 12 znaków, brak wymogu "wielka litera + cyfra
  + znak specjalny" (NIST 2024: tylko długość ma znaczenie)
Sekcja: §14.2
Priorytet: ŚREDNI
────────────────────────────────────────
#: 9
Problem: Sanityzacja CMS: HTML purifier na wejściu i wyjściu + CSP header
Sekcja: §15
Priorytet: ŚREDNI
────────────────────────────────────────
#: 10
Problem: Anti-multikonto: fingerprint, IP correlation, analiza transferów B2B,
  limit kont na Google OAuth
Sekcja: §14.1, §18
Priorytet: ŚREDNI



6. MOCNE STRONY DOKUMENTU

- Macierz interakcji §21 to świetny pomysł — konkretne kolizje między modułami z regułami. Warto ją rozbudować dla pozostałych par modułów.
- Widok 360° gracza w Filament (§18.1) — dobrze rozpisane zakładki, każdy aspekt korporacji widoczny. To wzorcowy opis panelu admina.
- System BHP i degradacji procedur (§11) — mechanizm zużywania się procedur wymuszający cykliczny koszt to dobry, realistyczny sink.
- Polityka offline (§19.2) — pomysł z magazynem zatrzymującym produkcję zamiast wybuchu jest dobry. Fail-safe mediator to też kreatywny bezpiecznik (wymaga tylko naprawy stawki minimalnej).
- Driver pattern dla bramek płatności (§22.1) — elastyczna architektura, łatwo dodać kolejnego operatora.
- Monitorowanie ARPU i konwersji w panelu admina (§22.4) — rzadkość w specyfikacjach gier, a kluczowe dla utrzymania serwisu.
- Spójny system sub-skilli technicznych (§6.1) — drilling, maintenance, safety, analysis — dobrze pokrywają domenę operacyjną i dają sensowną głębię rozwojowi kadry.
- Menu dynamiczne z Filament do Vue (§18.2) — ambicja edycji nawigacji bez kodu to dobra inwestycja w elastyczność, jeśli tylko broadcasting jest poprawnie opisane (obecnie nie jest).
