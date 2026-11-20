# OilEmpire — DOM03: przewaga w negocjacjach grupowych, wariant A

**Decyzja właściciela z 2026-11-21:** wariant A przyjęty do programowania. To doprecyzowanie [rejestru DOM](OILEMPIRE_DOM_DECYZJE_WLASCICIELA_2026_11_18.md), bez aktywacji balansu v1.

## Wejście i wynik

`CalculateGroupRaiseDemand(company_id, specialization_code, logical_at, balance_version)` czyta serwerowy snapshot aktywnych pracowników tej samej specjalizacji, ich `salary_satisfaction` (0–100), `skill_negotiation` obsadzonego dyrektora HR oraz obecność aktywnego `salary_negotiator`. Klient nie podaje przewagi ani średniej. Grupa liczy co najmniej 3 osoby; jedno otwarte żądanie na grupę i najwyżej jedno nowe w ruchomych 30 dniach. Członkostwo i parametry zapisuje się z `logical_at`; późniejsze zatrudnienie lub zwolnienie nie przelicza już żądania.

Dla `n` osób i sumy ich zadowolenia `S`:

```text
size_points = floor((n - 3) / 3)
dissatisfaction_points = floor(max(0, 60*n - S) / (10*n))
hr_points = skill_negotiation dyrektora HR; przy wakacie 0
negotiator_points = 2, gdy aktywny salary_negotiator; inaczej 0
advantage = clamp(-2, 8,
  size_points + dissatisfaction_points - hr_points - negotiator_points)
demand_pct = clamp(2, 12, 4 + advantage)
```

`n`, `S` i poziom HR są liczbami całkowitymi. Dzielenie wykonuje się dokładnie przed `floor`, bez użycia `float`. `salary_satisfaction` jest stanem z zatwierdzonego snapshotu P08, nie wynikiem ponownego odczytu po zmianie pensji. Wartości `size_points`, `dissatisfaction_points`, `hr_points`, `negotiator_points`, `advantage` i `demand_pct` są zapisane przy żądaniu wraz z `balance_version`.

**Przykład:** 9 pracowników, każdy z zadowoleniem 40, dyrektor HR ma skill 3, jest negocjator: `size=2`, `dissatisfaction=2`, `HR=3`, `negotiator=2`, zatem `advantage=-1`, `demand=3%`. Przy 3 pracownikach, zadowoleniu 50, wakacie dyrektora i braku negocjatora: `advantage=1`, `demand=5%`.

## Skutek i granica modułu

Wynik wyznacza **żądanie**, nie natychmiastową podwyżkę. Odpowiedź pracodawcy, ugoda, morale, ultimatum i cooldown korzystają z istniejącej akcji P08 oraz kanonu §4.2/§5. Płaca zmienia się raz po skutecznej decyzji; Payroll/Finance księguje według własnego kontraktu. Grupowe żądanie nie tworzy dziewięciu osobnych otwartych żądań ani dziewięciu rzutów RNG. Obliczenie jest deterministyczne; losowanie szansy ugody należy do oddzielnego kroku negocjacji.

Staff jest właścicielem zapisu snapshotu grupy i żądania. Odczyt `skill_negotiation` dyrektora i aktywności `salary_negotiator` idzie przez publiczny kontrakt Staff/Board. Przy równoczesnym stworzeniu żądania blokujemy trwały rekord grupy, sprawdzamy otwarte żądanie i ruchome okno `(logical_at−30 dni, logical_at]` w tej samej transakcji; trwały klucz intencji deduplikuje retry. Stały bucket kalendarzowy nie zastępuje ruchomego okna. Żadna druga firma nie czyta prywatnego snapshotu.

## Panel i testy

Filament publikuje wersjonowane: próg grupy 3, okno 30 dni, punkty liczebności co 3 osoby, próg zadowolenia 60, punkty co 10, bonus negocjatora 2, zakres przewagi −2..8 i podwyżki 2..12%. Podgląd pokazuje obliczenie na syntetycznych przykładach, różnicę kosztu płac i zakresy; zmiana nie działa wstecz. UI gracza PL/EN/DE pokazuje kwotę dla grupy i wpływ na łączny OPEX; lokalne SVG, bez systemowego okna.

[Kontrola wzoru](OILEMPIRE_DOM03_NEGOCJACJE_TEST.py) czyta kandydującą nakładkę YAML; sprawdza przykłady, progi i clamp bez silnika; wynik na kandydującej nakładce: 7/7 PASS. Testy implementacyjne: n=2/3/5/6/9, sat=59/60/61, wakat HR, skill=0/10, negocjator obecny/nieobecny, dolny/górny clamp, zmiana składu po snapshocie, dwa równoległe żądania, retry, granica 30 dni, jeden payroll effect po ugodzie, odmowa obcej firmy. Przed aktywacją wymagany przebieg 90 dni mierzący OPEX, morale, strajki i bankructwa.
