# OilEmpire — decyzje właściciela DOM03–DOM15 (2026-11-18)

**Aktualizacja 2026-11-21:** właściciel gry wybrał wariant A również dla DOM03, DOM06 i DOM15. Wszystkie 12 decyzji DOM03–DOM15 z tego rejestru mają wybór produktowy. Aktywacja wersjonowanego balansu, test ekonomii 90 dni i implementacja pozostają osobnymi bramkami. Ten dokument rozróżnia **decyzję produktową** od aktywacji wersji balansu. Aktywny `OILEMPIRE_GAME_BALANCE_V1.yaml` nie został zmieniony. Żadna nowa reguła nie jest automatycznie włączona w grze.

| ID | Decyzja produktowa | Zakres i status wdrożeniowy |
|---|---|---|
| DOM03 | Przyjęty wariant A: grupa ≥3 pracowników jednej specjalizacji, ruchome 30 dni; przewaga liczona z liczebności, zadowolenia, skill HR i negocjatora według [dokładnego kontraktu](OILEMPIRE_DOM03_NEGOCJACJE_WARIANT_A.md). | Staff P08-F04; wynik deterministyczny, bez wartości od klienta; wymaga kodu, testów i wersji parametrów. |
| DOM04 | Przyjęta: target 2–15%, propozycja systemu `min(15%,4% + 1 p.p. × max(0,60−salary_satisfaction))`; oferta 0–target. | Staff P08-F02; 72h i jednokrotne +48h z §4.2 pozostają bez zmian. |
| DOM05 | Przyjęta: −15 wiarygodności i −15 reputacji B2B to **wynik końcowy jednego sanction_event_id**; ogólny listener 50% nie nalicza ponownie B2B. | P06-F02/P21-F10; próg 3 flag/7 dni i 30 dni zawieszenia zwolnienia pozostają z kanonu. |
| DOM08 | Przyjęta: ukończona przed terminem naprawa ma pierwszeństwo przed eskalacją przy tym samym logical_at; jeśli warunki naprawy nie były spełnione, następuje eskalacja. | P13-F04; uprzednio rozstrzygnięty remis egzamin/koniec zatrudnienia w Staff A zachowany. |
| DOM09 | Przyjęta: opłata apelacyjna po przyjęciu bezzwrotna; techniczna odmowa przed przyjęciem zwraca opłatę; szansa apelacji `clamp(5%,90%,bazowa + min(10 p.p.,2 p.p. × skill_law))`, jeden rzut na case_id. Limit bonusu +10 p.p. pochodzi z §7.3; 5–90% dotyczy tylko apelacji, nie pierwotnego wniosku P1/P2. | Legal P10-F02; bazowa szansa i opłata pochodzą z właściwej wersji balansu, bez wartości domyślnej wymyślonej w Action. |
| DOM10 | Przyjęta: baza odnowienia = ostatnia zatwierdzona referencja regionu × udział eksploatacyjny; opłata 5% bazy z minimum katalogowym; grace 30 dni z istniejącym wydobyciem bez nowych odwiertów, potem pauza. | Legal P10-F03; brak referencji blokuje wycenę, nie tworzy zera. |
| DOM11 | Przyjęta: opłata odrzuconego pozwolenia to koszt; po skutecznej apelacji pierwotna opłata staje się CAPEX przez korektę księgi, opłata apelacyjna pozostaje kosztem. | Legal P10-F04; brak edycji historycznego zapisu i podwójnego CAPEX. |
| DOM12 | Przyjęta: 30 min załadunek/demurrage i 2h waiting_bay odbiorcy są oddzielnymi zegarami; jedna minuta i przyczyna nie są naliczane dwa razy; płatnik według źródła opóźnienia. | Logistics P12-F04; stawka 2000 PLN/h pozostaje z kanonu. |
| DOM13 | Przyjęta: 500/1500/4000 bbl na port/tier i trwały slot 5 min; replay nie zwiększa przepustowości. | Logistics P12-F04; idempotency `port:slot:flow`. |
| DOM14 | Przyjęta: przełączenie trwa dwa pełne sloty po 5 min od następnej granicy; zerowy przepływ do początku trzeciego slotu. | Logistics P12-F01; stan i due_at wersjonowane. |
| DOM06 | **Przyjęty wariant A:** cztery kursy bazowe 10/30/60 tys. PLN na poziomach I/II/III, kwalifikacja przez wersjonowane capability Staff, bez nowych mnożników efektu; [katalog](OILEMPIRE_DOM06_KATALOG_SZKOLEN_KANDYDAT.md). | Przerób Ropy zachowuje 20/50/100 tys. PLN oraz czasy 24/48/72h. Kalibracja kosztów i testy przed aktywacją. |
| DOM15 | **Przyjęty wariant A do programowania:** baza awarii odwiertów 0,2/0,4/0,7/1,2% odnosi się do 24h ekspozycji; slot 5 min służy deduplikacji. Istniejące godzinowe bazy drogi i rurociągu pozostają bez zmian; [kalibracja](OILEMPIRE_DOM15_KALIBRACJA_RYZYKA.md). | Test mechaniczny 6/6 PASS; test ekonomiczny 90 dni i obserwacja kosztów są bramką aktywacji. |

**DOM03:** [Wariant A](OILEMPIRE_DOM03_NEGOCJACJE_WARIANT_A.md) podaje pełny wzór przewagi, wejścia i testy. Wynik jest serwerowym snapshotem, nigdy wartością klienta.

## Publikacja i blokady

`approved_by_owner` oznacza zgodę na regułę do programowania, **nie** `active`. Zależna akcja czyta opublikowany, niezmienny snapshot polityki z `effective_at`, `balance_version` i hashem YAML; do czasu takiej publikacji zwraca `POLICY_NOT_APPROVED` tylko dla zależnej ścieżki. Przed aktywacją: aktualizacja kanonu, kart, kontraktów i kandydującego balansu w jednej zmianie, testy MySQL i API, symulator 0 FAIL, four-eyes oraz zapis hash. Główny administrator zatwierdza bez obowiązkowego komentarza zgodnie ze specyfikacją panelu.
