# OilEmpire — DOM15, kalibracja jednostki hazardu

**Status 2026-11-21:** właściciel przyjął wariant A do programowania: istniejące stawki awarii odwiertów odnoszą się do 24 godzin ekspozycji. Aktywny YAML v1 pozostaje bez zmian; test ekonomii 90 dni i bramki wydania poprzedzają aktywację. Źródło: §9.5, §11A.3–4, §17.4 i §23.6 oraz aktywny `OILEMPIRE_GAME_BALANCE_V1.yaml`. Właściciel gry zatwierdził sprawdzenie przed decyzją; nie zatwierdził arbitralnego `p=1%/5 min`.

## Wykryta pułapka jednostek

Cztery bazowe ryzyka awarii odwiertu mają etykietę **„per tick”** bez długości ticka. Jeśli 5-minutowy sweeper stanie się bezpośrednio jednostką ryzyka, jedna doba zawiera 288 prób. Poniżej neutralny mnożnik 1, niezależne ekspozycje, bez ograniczenia po pierwszej awarii; liczby służą **porównaniu scenariuszy**, nie prognozie gotowej gry.

| Warstwa | Wartość w YAML | P≥1/dobę przy 5 min | P≥1/dobę przy 1 h | P≥1/dobę przy 24 h | Oczekiwane próby/90 dni przy 24 h* |
|---|---:|---:|---:|---:|---:|
| shallow | 0.2% | 43.82% | 4.69% | 0.20% | 0.18 |
| mid | 0.4% | 68.47% | 9.17% | 0.40% | 0.36 |
| deep | 0.7% | 86.78% | 15.51% | 0.70% | 0.63 |
| offshore | 1.2% | 96.91% | 25.15% | 1.20% | 1.08 |

\* Liczba 90×p jest górną miarą ekspozycji niezatrzymanej awarią; faktyczny przebieg musi uwzględnić naprawy, kondycję, BHP, koszty i produkcję.

**Wniosek:** bez jawnej jednostki nie wolno uruchamiać spontanicznej awarii odwiertu. 5-minutowe traktowanie wartości z §9.5 tworzy niemal pewną awarię nawet dla płytkiego odwiertu w ciągu dnia. Wybrany wariant do programowania to **24 godziny ekspozycji dla baz §9.5**, przy zachowaniu tych samych wartości procentowych. Decyzja produktowa nie aktywuje tej interpretacji w wersji v1.

## Kalendarz ekspozycji dla różnych właścicieli ryzyka

| risk_owner | Obecna baza | Jednostka kandydująca | Wzór w logicznym segmencie |
|---|---|---|---|
| Extraction/well spontaneous failure | 0,2/0,4/0,7/1,2% „per tick” | **24h, przyjęte do programowania** | `1−(1−p_day)^(exposure_h/24 × m)` |
| Logistics/road spontaneous incident | 0,8% na godzinę kursu, §11A.4 | **1h — już jawna** | `1−(1−0,008)^(trip_exposure_h × m)` |
| Logistics/pipeline leak | 0,0008 na godzinę × warunek kondycji, §11A.3 | **1h — już jawna** | zachować §11A.3; bez drugiego rzutu P13 |
| Logistics/pipeline explosion | 0,0006 na godzinę × mnożniki, §11A.3 | **1h — już jawna** | zachować §11A.3; osobny kind, jeden owner |
| Logistics/marine | 0,5% oczekiwanej straty jako składnik ceny | **brak p zdarzenia** | nie wyprowadzać prawdopodobieństwa bez rozkładu dotkliwości |
| Technical/blowout escalation | `next_due_at` zdarzenia | termin zapisany w stanie | to zdarzenie terminowe, nie drugi spontaniczny rzut przy odczycie |

Dla drogi przy neutralnych warunkach: 5 min = **0.066912%**, 12 min = **0.160514%** (stary liniowy szacunek 0,160000%), 18 min = **0.240675%** (stary 0,240000%), 60 min = **0,8%**. To zmiana poniżej 0,001 punktu procentowego na typowym kursie. **Jedna godzina kursu nie może dostać 12 pełnych prób po 0,8%**, bo podniosłoby to ryzyko.

## Kontrakt implementacji i testów

Ekspozycję dzielić według rzeczywistego przecięcia przedziału kursu/pracy z trwałymi slotami 5 min; slot częściowy ma ułamek czasu. Ryzyko agregować przez sumę hazardu w kanonicznych przedziałach, a wynik losować **raz dla zamkniętego zakresu ekspozycji** pod trwałym kluczem `risk_owner:target:from:to:balance_version`. Slot jest jednostką deduplikacji/terminu, nie nową stawką. Dwa worker/job/HTTP nie zwiększają liczby rzutów. Przy podziale i scaleniu przedziału prawdopodobieństwo musi być identyczne. Zapisujemy `exposure_h`, risk owner, wersję parametrów, modyfikatory, seed key id, wynik i logical_at; zwykłe logi nie dostają seedu.

Testy graniczne: 0/1/5/12/60 min, wejście 12:03 i wyjście 12:15, pełny dzień przy 5-min sweeperze, uśpiony gracz rozliczony po 48h, retry i dwa workery, p=0/1, cap 95%, BHP/skill/kondycja, brak dwóch rzutów Logistics/P13. Test 90 dni mierzy awarie na 100 aktywnych odwiertów według warstwy, czas przestoju, koszt napraw, utracone bbl, inflację i bankructwa. [Kontrola matematyczna DOM15](OILEMPIRE_DOM15_HAZARD_CALIBRATION.py) czyta obecny YAML i nakładkę kandydującą: 6/6 testów PASS (wartości bazowe, normalizacja doby, kurs 12/18 min, podział slotów, wykrycie błędnej jednostki 5 min). Nie modeluje kosztu napraw, przestojów ani rozkładu szkód. **Aktywacja DOM15 wymaga przebiegu ekonomicznego 90 dni, przeglądu wyników i wersjonowanej publikacji balansu.** Sam obecny symulator 73 testów nie bada tej dystrybucji.
