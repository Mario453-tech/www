# OilEmpire — decyzje właściciela DOM03–DOM15 (2026-11-18)

**Źródło decyzji:** właściciel gry przyjął rekomendację: zatwierdzić kierunek DOM03–05 i DOM08–14, dopracować DOM06, skalibrować DOM15. Ten dokument rozróżnia **decyzję produktową** od aktywacji wersji balansu. Aktywny `OILEMPIRE_GAME_BALANCE_V1.yaml` nie został zmieniony. Żadna nowa reguła nie jest automatycznie włączona w grze.

| ID | Decyzja produktowa | Zakres i status wdrożeniowy |
|---|---|---|
| DOM03 | Przyjęta: grupa ≥3 pracowników jednej specjalizacji; jedno żądanie na grupę/30 dni; podwyżka `clamp(2%,12%,4% + 1 p.p. × pełna przewaga siły)`. | Staff P08-F04; wymaga kodu, testów i wersji parametrów. |
| DOM04 | Przyjęta: target 2–15%, propozycja systemu `min(15%,4% + 1 p.p. × max(0,60−salary_satisfaction))`; oferta 0–target. | Staff P08-F02; 72h i jednokrotne +48h z §4.2 pozostają bez zmian. |
| DOM05 | Przyjęta: −15 wiarygodności i −15 reputacji B2B to **wynik końcowy jednego sanction_event_id**; ogólny listener 50% nie nalicza ponownie B2B. | P06-F02/P21-F10; próg 3 flag/7 dni i 30 dni zawieszenia zwolnienia pozostają z kanonu. |
| DOM08 | Przyjęta: ukończona przed terminem naprawa ma pierwszeństwo przed eskalacją przy tym samym logical_at; jeśli warunki naprawy nie były spełnione, następuje eskalacja. | P13-F04; uprzednio rozstrzygnięty remis egzamin/koniec zatrudnienia w Staff A zachowany. |
| DOM09 | Przyjęta: opłata apelacyjna po przyjęciu bezzwrotna; techniczna odmowa przed przyjęciem zwraca opłatę; szansa apelacji `clamp(5%,90%,bazowa + min(10 p.p.,2 p.p. × skill_law))`, jeden rzut na case_id. Limit bonusu +10 p.p. pochodzi z §7.3; 5–90% dotyczy tylko apelacji, nie pierwotnego wniosku P1/P2. | Legal P10-F02; bazowa szansa i opłata pochodzą z właściwej wersji balansu, bez wartości domyślnej wymyślonej w Action. |
| DOM10 | Przyjęta: baza odnowienia = ostatnia zatwierdzona referencja regionu × udział eksploatacyjny; opłata 5% bazy z minimum katalogowym; grace 30 dni z istniejącym wydobyciem bez nowych odwiertów, potem pauza. | Legal P10-F03; brak referencji blokuje wycenę, nie tworzy zera. |
| DOM11 | Przyjęta: opłata odrzuconego pozwolenia to koszt; po skutecznej apelacji pierwotna opłata staje się CAPEX przez korektę księgi, opłata apelacyjna pozostaje kosztem. | Legal P10-F04; brak edycji historycznego zapisu i podwójnego CAPEX. |
| DOM12 | Przyjęta: 30 min załadunek/demurrage i 2h waiting_bay odbiorcy są oddzielnymi zegarami; jedna minuta i przyczyna nie są naliczane dwa razy; płatnik według źródła opóźnienia. | Logistics P12-F04; stawka 2000 PLN/h pozostaje z kanonu. |
| DOM13 | Przyjęta: 500/1500/4000 bbl na port/tier i trwały slot 5 min; replay nie zwiększa przepustowości. | Logistics P12-F04; idempotency `port:slot:flow`. |
| DOM14 | Przyjęta: przełączenie trwa dwa pełne sloty po 5 min od następnej granicy; zerowy przepływ do początku trzeciego slotu. | Logistics P12-F01; stan i due_at wersjonowane. |
| DOM06 | **Kandydat do osobnego zatwierdzenia:** katalog, ceny kursów bazowych, kwalifikacja i efekty opisane w [DOM06](OILEMPIRE_DOM06_KATALOG_SZKOLEN_KANDYDAT.md). | Ceny/czasy Przerobu Ropy i efekty już ustalone nie są renegocjowane. |
| DOM15 | **Kalibracja trwa:** [raport DOM15](OILEMPIRE_DOM15_KALIBRACJA_RYZYKA.md) pokazuje, że wspólne 1%/slot byłoby błędem; baza odwiertów „per tick” nie ma ustalonej jednostki. | Nie aktywować nowej polityki hazardu przed testem 90 dni i decyzją o jednostce dla każdego risk_owner. |

**Doprecyzowanie DOM03:** „pełna przewaga siły” jest wejściem z wersjonowanego wyniku rundy negocjacyjnej Staff, nigdy wartością klienta. Wzór wyliczenia tego wyniku nie jest jeszcze podany w §5.2; zatwierdzono kierunek i limit podwyżki, a przed kodowaniem generatora trzeba opisać jego skalę i składniki. Bez opublikowanego generatora zależna gałąź pozostaje zamknięta.

## Publikacja i blokady

`approved_by_owner` oznacza zgodę na regułę do programowania, **nie** `active`. Zależna akcja czyta opublikowany, niezmienny snapshot polityki z `effective_at`, `balance_version` i hashem YAML; do czasu takiej publikacji zwraca `POLICY_NOT_APPROVED` tylko dla zależnej ścieżki. Przed aktywacją: aktualizacja kanonu, kart, kontraktów i kandydującego balansu w jednej zmianie, testy MySQL i API, symulator 0 FAIL, four-eyes oraz zapis hash. Główny administrator zatwierdza bez obowiązkowego komentarza zgodnie ze specyfikacją panelu.
