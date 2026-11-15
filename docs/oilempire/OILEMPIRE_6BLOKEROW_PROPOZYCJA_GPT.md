### ZAD.1 — Baza podatku majątkowego

Wycena zapasów po 80% ceny referencyjnej utrudnia przenoszenie majątku z pieniędzy do ropy, ale uwzględnia koszty sprzedaży i wahania rynku. Jedna chwila pomiaru oraz przypisanie każdej partii jednemu właścicielowi zapobiegają podwójnemu liczeniu jej w magazynie, buforze i transporcie.

```markdown
### 19.1a. Zapasy i wierzytelności w podstawie podatku

Na każdy termin naliczenia podatku utrwala się stan własności i położenia aktywów. Podstawa = PLN_CASH + PLN_BANK + własne środki w PLN_ESCROW + 70% wartości aktywów trwałych + wartość zapasów + wartość należności B2B. Każda partia surowca lub produktu ma jednego właściciela i jest liczona dokładnie raz, także podczas transportu, rezerwacji sprzedażowej i postoju w buforze. Nie odejmuje się zobowiązań od podstawy podatku majątkowego; majątek netto na potrzeby Tieru pozostaje odrębną wielkością.

Wartość partii = ilość bbl × 80% ceny referencyjnej właściwego surowca lub produktu. Cena referencyjna to mediana cen wykonanych transakcji spot z ostatnich 24h UTC; transakcje własne, między kontami powiązanymi i anulowane nie wchodzą do próby. Gdy brak wiarygodnych transakcji, stosuje się ostatnią prawidłową medianę, a przed jej powstaniem cenę bazową z game_balance (ropa: 250 PLN/bbl); wycena nie może spaść poniżej 60% tej ceny bazowej. Produkty mają odrębne ceny bazowe w game_balance. Wynik zaokrągla się do grosza po zsumowaniu wartości partii danego rodzaju.

Należność B2B liczy się od chwili nieodwracalnego wydania towaru lub wykonania usługi do zapłaty: w wartości wymagalnej kwoty, najwyżej do kwoty faktycznie zabezpieczonej w PLN_ESCROW kupującego; niezabezpieczoną część liczy się według 50% wartości nominalnej. Środki zablokowane w escrow pozostają w podstawie kupującego do rozliczenia, a odpowiadającej im zabezpieczonej należności nie dolicza się sprzedającemu. Po rozliczeniu escrow przechodzi do pieniędzy sprzedającego i przestaje być podstawą kupującego. Towar po wydaniu należy wyłącznie do podstawy nowego właściciela.

Wydobycie samo nie jest sprzedażą ani drugim zdarzeniem podatkowym: ropę wycenia się jako istniejący zapas w każdym kolejnym dobowym stanie, a po sprzedaży w tej samej podstawie zastępują ją pieniądze lub należność. Ta sama wycena zapasów obowiązuje przy progach tarczy startowej i przy obliczaniu majątku netto Tieru; wyłączenie wartości pakietu startowego stosuje się zgodnie z §22.6, bez wyłączania później wydobytej ropy.
```

### ZAD.2 — Wzór morale

Pensja ma wagę 40 punktów, aby niska płaca mogła wywołać spór, lecz nie przesądzała o nim mimo dobrych warunków pracy. Ograniczenie zmiany do 3 punktów na dobę dla zwykłych warunków zapobiega skokom co pięć minut; decyzje kadrowe zachowują swoje natychmiastowe skutki z §4.2.

```markdown
### 4.1a. Liczbowe morale pracownika i działu

`morale` jest liczbą z zakresu 0–100. Docelowe morale pracownika wylicza jedna funkcja z utrwalonych stanów:
`target = clamp(0, 100, 50 + 0,4 × (salary_satisfaction − 100) − overload − raise_delay − rejected_raise − layoffs + hr_budget + bonuses + training + safety)`.
Składniki są punktami morale, poza `salary_satisfaction`, które zachowuje definicję z §4.1. Wszystkie okresy poniżej oznaczają ruchome okna czasu UTC.

| Składnik | Reguła |
|---|---|
| `overload` | 0 bez przeciążenia; 5 za pracę w przeciążonym obiekcie; 10 przy trybie turbo tego obiektu; nie sumować obu |
| `raise_delay` | 0 przez pierwsze 30 dni bez podwyżki; następnie +1 za każde pełne 7 dni, maks. 8 |
| `rejected_raise` | 12 przez 14 dni od odmowy własnego żądania; 8 przez 14 dni od przegranej kontrofer­ty; stosować tylko silniejszy skutek |
| `layoffs` | 4 za każde zwolnienie innej osoby w dziale w ostatnich 7 dniach, maks. 12 |
| `hr_budget` | `low`: −6; `standard`: 0; `high`: +4, według faktycznie opłaconego budżetu |
| `bonuses` | +6 przez 7 dni od wypłaconej pracownikowi premii `hr_bonus`; kolejne premie nie kumulują punktów |
| `training` | +4 przez 14 dni od ukończenia szkolenia lub certyfikacji; bez kumulacji |
| `safety` | +4 przy bezpiecznych warunkach i braku incydentu przez 7 dni; 0 w zwykłych warunkach; −8 przez 7 dni od incydentu w miejscu pracy; −15 przez 7 dni od ciężkiego incydentu; stosować tylko jeden stan |

Co 5 minut sweeper stosuje `delta_hours = min(czas_od_ostatniego_przeliczenia, 24h)` i przesuwa morale o najwyżej `3 × delta_hours / 24` punktu w stronę `target`; nie nalicza zmiany ponownie za ten sam odcinek czasu. Zmiany natychmiastowe z §4.2 (+20, +8, −20) oraz pozostałych reguł kadrowych stosuje się osobno, raz na zdarzenie, po czym ogranicza wynik do 0–100. Nie zastępuje się nimi `target`.

Morale działu to średnia arytmetyczna aktualnego morale aktywnych pracowników przypisanych do działu; pusty dział nie ma morale ani sporu. Próg sporu z §5 sprawdza się na aktualnym morale pracownika `<35`, a warunki strajku działu na średniej działu, jeśli §5 wymaga wyniku działowego. Parametry punktowe, okresy i tempo należą do wersjonowanego game_balance.
```

### ZAD.3 — Cennik i zakup ochrony

Cena za kurs skaluje się z ładunkiem, więc osłona ma sens także dla cysterny około 500 bbl, a ubezpieczenie drogiego transportu nie kosztuje tyle samo co pustego. Ochrona stała jest krótkoterminowa, aby gracz płacił za faktycznie chroniony okres, nie za trwałą odporność na PvP.

```markdown
### 7.2. Zakup i rozliczanie ochrony

Ochronę kupuje się w panelu `/sabotage/protection` dla wskazanego kursu albo aktywa. Cena jest pobierana z PLN_CASH lub PLN_BANK wybranego przez gracza przed aktywacją; brak środków oznacza brak zakupu, bez ujemnego salda. Wartość kursu to liczba przewożonych bbl × cena referencyjna z §19.1a w chwili zakupu. Wszystkie ceny i okresy są wersjonowane w game_balance.

| Moduł | Cena | Okres i aktywacja |
|---|---|---|
| `basic_escort` | większa z: 2 000 PLN albo 2% wartości ładunku | Jeden wskazany kurs; aktywna od potwierdzenia, tylko przed rozpoczęciem kursu |
| `armed_convoy` | większa z: 6 000 PLN albo 5% wartości ładunku | Jeden wskazany kurs; aktywna od potwierdzenia, tylko przed rozpoczęciem kursu; wymaga dyrektora prawnego poziomu ≥3 zarówno przy zakupie, jak i rozpoczęciu kursu |
| `drone_patrol` | 12 000 PLN za 24h na wskazany rurociąg albo trasę | Aktywacja po 30 minutach; wykrywanie dotyczy wyłącznie operacji rozstrzyganych w czasie aktywności |
| `hub_security` | 20 000 PLN za 24h na wskazany hub | Aktywacja po 30 minutach; ochrona dotyczy wyłącznie operacji rozstrzyganych w czasie aktywności |

Eskorta i konwój są wzajemnie wykluczające się dla kursu; redukcje ryzyka nie sumują się. Anulowanie kursu przed startem zwraca 100% opłaty, po starcie nie ma zwrotu; po zakończeniu kursu ochrona wygasa. Dla ochrony dobowej czas biegnie od aktywacji, bez automatycznego odnawiania i bez zwrotu za niewykorzystany okres. Zakup w czasie już rozpoczętego sabotażu nie działa wstecz.

Opłata ochrony trafia wpisem FTS `PLN_CASH/PLN_BANK → SYS_TREASURY`, z `reason_code=protection_basic_escort`, `protection_armed_convoy`, `protection_drone_patrol` albo `protection_hub_security`; zapis obejmuje `idempotency_key`, `sweep_no` i `balance_version`. Budżet Bezpieczeństwa BHP (§10) nie opłaca modułów i nie wzmacnia ich procentowych efektów; jego własne skutki dotyczą bezpieczeństwa pracy, nie rozstrzygnięć PvP. Przy utracie wymaganego dyrektora przed startem kursu konwój nie rusza jako chroniony: gracz może przywrócić warunek lub anulować ochronę z pełnym zwrotem.
```

### ZAD.4 — Pozwolenia i łapówki

Czasy 12 i 24 godzin sprawiają, że pozwolenia są planowaną przeszkodą, a nie losową blokadą na wiele dni. Łapówka może jedynie przyspieszyć rozpoznanie wniosku: nie usuwa mandatu ani zajęcia komorniczego, więc nie pozwala kupić obejścia §17.

```markdown
### 7.3. Pozwolenia P1/P2 i nielegalne przyspieszenie

Wniosek P1 kosztuje 25 000 PLN i jest rozstrzygany po 12h; P2 kosztuje 75 000 PLN i jest rozstrzygany po 24h. Opłata jest bezzwrotna, pobierana przy zgłoszeniu (`PLN_CASH/PLN_BANK → SYS_TREASURY`, `reason_code=permit_p1_application` albo `permit_p2_application`). Dozwolony jest jeden otwarty wniosek danego typu dla tego samego aktywa. Odmowa lub zgoda są ustalane w terminie z jednego losowania: `p_odmowy = clamp(5%, 45%, baza + max(0, 70 − company_credibility) × 0,3 p.p. + black_market_score × 0,1 p.p.)`, gdzie baza wynosi 8% dla P1 i 15% dla P2, a `black_market_score` jest ograniczony do 0–100 na potrzeby wzoru. Wynik i wersja game_balance zostają zapisane; ponowne wykonanie sweepera nie losuje ponownie.

Od odmowy można odwołać się raz w ciągu 72h za 10 000 PLN (P1) albo 30 000 PLN (P2). Odwołanie trwa odpowiednio 12h lub 24h; ponownie stosuje się wzór odmowy z obniżeniem wyniku o 10 p.p., lecz nie poniżej 5%. Opłata trafia do SYS_TREASURY z `reason_code=permit_p1_appeal` albo `permit_p2_appeal`. Po bezskutecznym odwołaniu nowy wniosek dla tego aktywa jest możliwy po 24h.

Jedyna dopuszczona łapówka to próba przyspieszenia już opłaconego, nierozstrzygniętego wniosku P1/P2: koszt 40 000/100 000 PLN, najwyżej jedna próba na wniosek i jedna na firmę w 72h. Próba przenosi termin rozstrzygnięcia na najbliższy przebieg sweepera po upływie 1h od zapłaty; nie zmienia prawdopodobieństwa odmowy ani prawomocnych warunków pozwolenia. Nie można przekupić umorzenia mandatu, ostrzeżenia o kontroli, uchylenia zajęcia, odblokowania odwiertu ani wyniku odwołania.

Ryzyko wpadki: `clamp(10%, 60%, 25% − 1 p.p. × skill_ethics dyrektora prawnego + 0,2 p.p. × black_market_score)`. Brak dyrektora oznacza `skill_ethics=0`. Losowanie wpadki jest odrębne od losowania pozwolenia. Wpadka: `company_credibility −18`, mandat 150 000 PLN, postępowanie administracyjne i blokada nowych wniosków P1/P2 na 72h; przyspieszenie przepada, a bieżący wniosek wraca do pierwotnego terminu. Mandat płatny do 72h, dalej §17; nie wolno sprowadzać salda gracza poniżej zera.

Opłata za próbę: `PLN_CASH → SYS_TREASURY`, `reason_code=bribe_attempt`; mandat: `PLN_CASH/PLN_BANK → SYS_TREASURY`, `reason_code=bribe_penalty`. Wpisy FTS mają `idempotency_key`, `sweep_no`, `balance_version`. Losowania używają `HMAC(seed, encja:due_slot)`, a opłaty, terminy, prawdopodobieństwa i blokady należą do wersjonowanego game_balance.
```

### ZAD.5 — Starter kit i wykonalny samouczek

Wybór pada na gotowy odwiert szkoleniowy: skrócenie 48-godzinnego badania i wielogodzinnej budowy do 30 minut przeczyłoby zwykłemu lejkowi §9.4. Sam zakup podstawowego łańcucha za przykładowe 2 150 000 PLN zostawiłby z 4 mln PLN 1 850 000 PLN, ale nie rozwiązałby ograniczenia czasu; gotowy łańcuch zachowuje całą gotówkę i bank na OPEX oraz dalszą budowę.

```markdown
### 27.1a. Startowy odwiert szkoleniowy

Do pakietu startowego, bez potrącenia z 2 000 000 PLN w PLN_CASH i 2 000 000 PLN w PLN_BANK, dodaje się: jedną nieprzenoszalną koncesję szkoleniową płytkiej warstwy, ukończone badanie, czynny odwiert `base_rate=20 bbl/h`, podłączony rurociąg polowy do małego bufora oraz jedną cysternę 500 bbl. Pojemność magazynu 20 000 bbl nadal oznacza pustą pojemność, nie zapas ropy. Wyjątek dotyczy wyłącznie utworzenia gotowego zestawu przy założeniu firmy: nie skraca późniejszych badań, wierceń ani montażu z §9.4.

Zasobność szkoleniowa wynosi 3 360 bbl, czyli 20 bbl/h przez 7 dni przy nieprzerwanej pracy; nie odnawia się. Odwiert pozostaje fizycznie podłączony i po wyczerpaniu przechodzi zwykłą procedurę zakończenia eksploatacji. Ropa i przychody ze sprzedaży są zwykłymi aktywami gracza; sam zestaw jest oznaczony jako wartość pakietu startowego dla wyłączenia przewidzianego w §22.6. Nie można sprzedać ani przenieść koncesji, odwiertu, rury i cysterny szkoleniowej przed wyczerpaniem złoża.

Tutorial prowadzi: uruchomienie gotowego odwiertu → sprawdzenie podłączonej rury i bufora → załadunek cysterny → kurs → sprzedaż spot → zatrudnienie technika i odczyt KPI. W pierwszych 30 minutach przy pełnej wydajności powstaje 10 bbl; kurs szkoleniowy może przewozić niepełny ładunek i kosztuje standardowe 4 PLN/bbl. Nagrody krokowe pozostają łącznie około 100 000 PLN i wymagają faktycznego wykonania działań.

Bilans wejścia: 4 000 000 PLN pozostaje na rachunkach, ponieważ koncesja, badanie, odwiert, rura i cysterna są świadczeniem rzeczowym, nie wydatkiem z tych rachunków. Po odłożeniu 300 000 PLN na pierwsze dni OPEX i kursy zostaje 3 700 000 PLN na zwykłą ekspansję. Przy przykładowym koszcie nowego łańcucha: koncesja 300 000 + badanie 100 000 + wiercenie 1 100 000 + rura 350 000 + cysterna 300 000 = 2 150 000 PLN, po zakupie i odłożeniu rezerwy pozostaje 1 550 000 PLN. Te przykładowe ceny nie zastępują tabel §9.5 i §11A.3: przed wdrożeniem należy sprawdzić, czy wybrany najtańszy rzeczywisty łańcuch mieści się w 3 700 000 PLN.
```

### ZAD.6 — Dzienny Tier Kroplówki

Snapshot o 00:00 UTC daje jeden przewidywalny limit na dobę i usuwa możliwość podwyższenia Tieru własną wymianą tuż przed kolejną wymianą. Odjęcie samych konwersji z 30 dni byłoby arbitralne; osobny składnik pochodzenia środków pozwala wyłączyć również ropę lub aktywa kupione za pieniądze z Kroplówki.

```markdown
### 22.6a. Snapshot Tieru i wyłączenie emisji Kroplówki

O 00:00 UTC utrwala się jeden snapshot Tieru na dzień UTC: `net_worth_ex_drip = max(0, majątek_netto_z_wyłączeniem_pakietu_startowego − drip_attributed_value)`. Obliczenie majątku obejmuje zapasy i należności według §19.1a. Tier, przypisany limit GBBL i `balance_version` snapshotu obowiązują do następnego 00:00 UTC; spadek majątku w ciągu dnia zmienia Tier dopiero w następnym snapshocie. Blokady wymiany z §24.3 działają natychmiast, niezależnie od snapshotu.

`drip_attributed_value` to część bieżącego majątku netto przypisana do PLN wyemitowanych przez Kroplówkę, nie suma historycznych wypłat. Każda konwersja zwiększa przypisany składnik o wypłaconą kwotę; przy zakupie zapasu lub aktywa przypisanie przechodzi na nabyty składnik proporcjonalnie do udziału środków z Kroplówki w zapłacie. Sprzedaż przenosi przypisanie na uzyskane pieniądze; zużycie, utrata wartości i koszty zmniejszają je proporcjonalnie. Nie może ono przekroczyć bieżącej dodatniej wartości netto firmy ani spaść poniżej zera. Przeniesienie między PLN_CASH, PLN_BANK i PLN_ESCROW nie zmienia przypisania. Zapisuje się ruchy przypisania z `conversion_id`, aby snapshot dało się odtworzyć; nie stosuje się okna 30 dni.

Wymiana jest najwyżej jedna na dzień UTC i musi być obsłużona w transakcji z blokadą wiersza `(player_id, date_utc)`, unikatowym kluczem tej pary i `idempotency_key` żądania. Równoległe próby: pierwsza zatwierdzona zużywa dzienny limit, ponowienie tego samego klucza zwraca jej wynik, a pozostałe próby są odrzucane bez pobrania GBBL. Weryfikacja salda GBBL, snapshotu, limitu i blokad, obie księgi powiązane `conversion_id` oraz zapis przypisania są atomowe. Snapshot liczony przed pierwszą wymianą danego dnia nie uwzględnia jej emisji; kolejny także wyłącza wartość nadal przypisaną Kroplówce.
```
