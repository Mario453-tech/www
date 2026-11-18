# OilEmpire — DOM06, katalog szkoleń technicznych v1 (kandydat)

**Status:** do zatwierdzenia po kalibracji HR. Nie zmienia aktywnego YAML. Kanon §6/§31.7 zachowuje +1 do dedykowanego sub-skilla za zdany egzamin, cap 10, bazową szansę 70%, +10 p.p. za powtórkę (max +30), cooldown 12h oraz blokadę 7 dni po 3 niepowodzeniach. Przerób Ropy I/II/III zachowuje 20/50/100 tys. PLN i 24/48/72h. [Decyzje właściciela](OILEMPIRE_DOM_DECYZJE_WLASCICIELA_2026_11_18.md).

## Katalog

`program_code` jest stabilny. Katalog jest wersjonowany, a enrolment zapisuje `program_version`, `price_minor`, `duration_h`, `skill_before`, `balance_version` i snapshot uprawnienia. Po zmianie cennika rozpoczęty kurs zachowuje własne warunki.

| program_code | Umiejętność techniczna | Dopuszczenie przez capability Staff | I: PLN/h | II: PLN/h | III: PLN/h | Efekt już określony w kanonie |
|---|---|---|---|---|---|---|
| drilling | skill_drilling | operacje odwiertów/wiercenia | 10 000/24 | 30 000/48 | 60 000/72 | czas wiercenia ×(1−0,05×skill), §9.4 |
| maintenance | skill_maintenance | utrzymanie ruchu/naprawy | 10 000/24 | 30 000/48 | 60 000/72 | zużycie odwiertu ×(1−0,04×skill), §9.3 |
| safety | skill_safety | bezpieczeństwo techniczne/BHP | 10 000/24 | 30 000/48 | 60 000/72 | ryzyko §17.4 ×(1−0,05×skill) |
| analysis | skill_analysis | geologia/analiza złoża | 10 000/24 | 30 000/48 | 60 000/72 | wydobycie ×(1+0,02×skill), §9.2 |
| refining | skill_refining | instalacja rafineryjna/proces chemiczny | **20 000/24** | **50 000/48** | **100 000/72** | przepustowość +1%/pkt i awaryjność −2%/pkt, §31.7 |

**Nowe liczby do decyzji:** 10/30/60 tys. PLN są kandydatem wyłącznie dla czterech bazowych kursów. Ich skutki liczbowe już istnieją w kanonie; nie dodajemy jednocześnie nieustalonych bonusów naprawy, strat surowca lub jakości, które podwoiłyby korzyść. Capability jest mapowane przez właściciela Staff na wersjonowane specjalizacje; nie wolno przepuścić dowolnego `role_code` z klienta. Nieobsługiwana specjalizacja → `PROGRAM_UNAVAILABLE`, nie darmowy kurs.

## Przejścia i czas

1. Poziom I wymaga właściwego capability i aktywnego zatrudnienia; II wymaga certyfikatu I, III wymaga II. Ten sam pracownik nie może mieć drugiego aktywnego enrolment. `UNIQUE(employee_id,program_code,level)` dla certyfikatów i trwały klucz `enrolment_id:exam_attempt` zapobiegają ponownemu grantowi.
2. Kurs trwa **24/48/72 godziny kalendarzowe**, co nie oznacza 24/48/72 godzin pracy. Przez okno kursu pracownik jest niedostępny do nowego przydziału krytycznego; rozliczenie płacy korzysta z faktycznego grafiku i umowy. Start wymaga pokazania skutku utraty obsady i zatwierdzenia tej akcji przez gracza.
3. Choroba wstrzymuje zegar kursu i blokuje egzamin; postęp oraz opłata zostają, nowy `due_at` ma wersję. Stary job jest no-op. Urlop nie jest ukrytą ścieżką przyspieszenia; nakładanie rozstrzyga Staff Availability.
4. Opłata jednorazowo przez FTS przy zapisie, bez zwrotu po prawidłowym rozpoczęciu; niepowodzenie techniczne przed skutecznym enrolment zwraca lub nie księguje. Zwolnienie przed końcem anuluje kurs bez certyfikatu. Strajk nie zatrzymuje zewnętrznego wykładowcy według §6.
5. Zaliczenie daje dokładnie +1 do sub-skilla, maks. 10, oraz certyfikat danego poziomu. Ponowienie exam joba nie daje kolejnego punktu. Nieudany egzamin przestrzega cooldown/bonus/lock z §6. Poziomy I–III są najwyżej trzema grantami z tej ścieżki; istniejący wyższy skill z rekrutacji lub historii nie jest resetowany.

## Panel i odbiór

Filament edytuje ceny, czasy, pass rate, wymagania capability, poziomy, udostępnienie i komunikaty PL/EN/DE w wersji kandydującej; opublikowanej wersji nie edytuje. Każda zmiana wartości ma diff, symulator i audit. UI gracza pokazuje cenę, czas kalendarzowy, wpływ na obsadę, szansę egzaminu i obecny/celowy skill; komunikat i ikona są lokalnym SVG, bez systemowego `alert`/`confirm`.

Testy: brak capability, poziom II bez I, cap 9→10 i 10→10, dwa równoległe zapisy, choroba na `due_at`, zwolnienie przed egzaminem, retry płatności/egzaminu, 12h/7dni granice, brak podwójnego morale `training_180d`. Przed zatwierdzeniem cen uruchomić 90 dni z kosztem kursów i niedoborem obsady; dopiero wtedy wpisać kandydat do pełnego game_balance.
