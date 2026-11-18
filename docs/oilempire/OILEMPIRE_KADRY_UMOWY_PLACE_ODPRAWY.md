# OilEmpire — umowy, wynagrodzenia i odprawy: polityka HR-PAY-01

[Indeks](README.md) · [Zakres kadr](OILEMPIRE_KADRY_ZATRUDNIENIE_ROZSZERZENIE.md) · [Karty P07–P09](OILEMPIRE_KARTY_IMPLEMENTACJI_P06_P13.md)
Data opracowania: 2026-11-17. Właściciel Staff; rozliczenia Finance/Ledger.

Status: konkretna specyfikacja kandydującej polityki gry. Algorytmy i przykłady do programowania; nowe współczynniki wymagają kalibracji i zwykłej bramki balance_version. To nie aktywny balans, nie kompletny kalkulator polskich wynagrodzeń i nie implementacja Laravel.
Rozwija HR-CFG02/04. Kadry §19.3 rozstrzygają wariant A dla kar morale DOM02; grafiki, urlopy/choroby opisują dalsze sekcje Kadr. [HR-CONTRACT01](OILEMPIRE_KADRY_KONTRAKT_IMPLEMENTACYJNY.md) określa docelowe tabele i akcje, a finalne migracje/OpenAPI i testy pozostają bramką aktywacji.

## 1. Co jest prawem, a co modelem gry

Źródła sprawdzone podczas przygotowania:
- [ZUS: finansowanie składek](https://psz.zus.pl/pl/web/guest/pracujacy/system-ubezpieczen-spolecznych-w-polsce/finansowanie-skladek-na-ubezpieczenia-spoleczne): udział pracodawcy emerytalny9,76%, rentowy6,5%; składka wypadkowa po stronie pracodawcy.
- [Biznes.gov.pl: składki](https://biznes.gov.pl/pl/portal/00274): przykład wypadkowej1,67%, fundusze i zwolnienia. Strona źródłowa dostępna w wyniku wyszukiwania; bez pełnego odczytu (403).
- [Ministerstwo: ustanie stosunku pracy](https://www.gov.pl/web/rodzina/ustanie-stosunku-pracy): okres wypowiedzenia zależy od rodzaju umowy i stażu; reguły końca okresu nie są prostą liczbą sekund.
- [Ustawa, tekst jednolity2025, art.1/8/10](https://eli.gov.pl/api/acts/DU/2025/570/text/T/D20250570L.pdf): szczególny zakres odpraw ekonomicznych, warunki zastosowania i limit. Nie jest to odprawa za każde zwolnienie.

GRA świadomie stosuje krótsze progi stażu i okresy wypowiedzenia niż rzeczywiste przepisy. Odprawa umowna jest dostępna również w małej firmie. Są to reguły świata gry, nie polskie obowiązki ustawowe.
Stawki rynkowe architektury były już skalowane PL2025×1,8; nie obniżamy ich ani nie uznajemy za rzeczywiste wynagrodzenia netto. Kandydat interpretuje je jako brutto pełnego etatu. Nowe obciążenia zwiększą OPEX — wymagany ponowny test ekonomii.
Nie obiecujemy identycznych przepisów w każdym kraju. Pierwsza polityka to wspólny, jawny model gry; regionalne adaptery prawne pozostają poza tym wydaniem.

## 2. Macierz form i warunków

| Forma | Podstawa wynagrodzenia | Czas i koniec | Świadczenie przy zakończeniu |
|---|---|---|---|
| employment | miesięczne brutto faktycznego etatu | próbna7dni, określona30/90dni, bezterminowa | odprawa wg §5 |
| mandate | brutto za zatwierdzone sekundy pracy, stawka/godzinę | określona lub bezterminowa, wypowiedzenie7dni | brak automatycznej odprawy, zapłata wykonanej pracy |
| business_service | netto za godzinę, odebraną usługę lub miesięczny ryczałt | termin/zadanie; wypowiedzenie14dni dla godzin/ryczałtu | jawna klauzula anulowania z quote |
| management | miesięczny ryczałt brutto + należna premia z zatwierdzonej reguły | określona lub bezterminowa, wypowiedzenie30dni | 1 ryczałt przy zakończeniu przez firmę |

Wartości tej tabeli są kandydatem gry. Umowa usługowa za zadanie nie dostaje automatycznie14dni ani darmowego anulowania: przed podpisaniem musi mieć kryterium odbioru, kwotę oraz formułę rozliczenia części/anulowania. Brak klauzuli blokuje ten wariant, nie pozostałe umowy.
Katalog stanowisk określa dopuszczalne formy. Brak wpisu oznacza niedostępny wariant. Stały operator na zmianie nie dostaje automatycznie B2B jako obejścia kosztów.
Próba nie odnawia się dla tej samej osoby i firmy po ponownym zatrudnieniu; bez automatycznej zamiany na umowę stałą. Kontynuacja wymaga zaakceptowanej umowy.
Etat: numerator/denominator =1/4,1/2,3/4,1/1. salary_fte_minor jest stawką odniesienia; actual_monthly_minor=round_half_up(salary_fte_minor×fraction) raz przy podpisaniu. Rozliczenie używa już actual, bez drugiego mnożenia.
Porównanie oczekiwań/minimum/pensji w morale na zgodnej podstawie. Brutto zlecenia i netto faktury nie są tym samym.
Aneks jest niezmienną wersją z datą skutku, poprzednią wersją i akceptacją. Zakaz daty skutku wcześniejszej niż rozliczony stan; błędy historii naprawiane osobnym dokumentem korekty.

## 3. Składniki kosztu i naliczanie

Kandydat profilu STANDARD_GAME:
| Składnik pracodawcy | Punkty bazowe | % |
|---|---:|---:|
| emerytalny | 976 | 9,76 |
| rentowy | 650 | 6,50 |
| wypadkowy | 167 | 1,67 |
| fundusze pracy/solidarnościowy łącznie | 245 | 2,45 |
| gwarancyjny | 10 | 0,10 |

Suma2048bps=20,48%. Jest to uproszczony standard kalkulacyjny; nie uwzględnia wyjątków, limitu rocznej podstawy, PPK ani indywidualnego profilu podatkowego. Nie przedstawiać jako stawki obowiązującej każdego rzeczywistego pracodawcę.
Employment/mandate/management używają w tym kandydacie tego samego profilu kosztu, bez ulg. UI pokazuje brutto i koszt firmy; NIE pokazuje nieobliczonego netto pracownika.
Business_service: koszt netto + nieodliczalny podatek; płatność netto+cały podatek. Każda faktura ma tax_bps i recoverable_tax_bps w zakresie0..tax_bps. Nie zakładać automatycznie VAT23% lub pełnego odliczenia. Oba parametry muszą pochodzić z zatwierdzonej polityki.
Nie pobieramy ponownie prowizji rekrutacyjnej jako opłaty za podpis, jeżeli kampania już ją obejmowała.

Miesiąc to [pierwszy dzień00:00UTC, pierwszy dzień następnego00:00UTC). Pensja miesięczna narasta ciągle za czas trwania stosunku, również poza zmianą. Godzinowa tylko za zatwierdzone przedziały pracy. Ryczałt miesięczny jak pensja; zadanie po odbiorze, nie proporcjonalnie do uptime.
W pełnym miesiącu bez zmian i nieobecności obniżających płacę suma=actual_monthly_minor niezależnie od28/29/30/31dni.
Dla segmentu: amount_exact=monthly_minor×eligible_duration_ms/month_duration_ms. Segment na granicy miesiąca, aneksu, polityki lub odpłatności nieobecności.
Normy grafiku nie są dzielnikiem pensji miesięcznej. Niewykonanie grafiku z winy firmy nie zmienia jej automatycznie na stawkę godzinową.
Urlop firmy odejmuje od eligible_duration według istniejącego kontraktu zamrożenia; urlop pracownika wymaga własnej polityki, nie pomniejszenia z automatu.
Wszystkie dokładne kwoty są racjonalne/DECIMAL z kontrolowaną precyzją; nie float. Zachować resztę dla employee+contract+period+component.
Księgowanie przyrostu: floor(cumulative_exact_minor)−already_posted_minor. Na zamknięciu miesiąca/umowy round_half_up(cumulative_exact_minor)−already_posted_minor. Nigdy round_half_up osobno każdego GET.
Każdy składnik obciążeń ma osobny akumulator: gross_exact×bps/10000. Kwota całkowita=sum(round każdego składnika na zamknięciu). Nie mnożyć zaokrąglonego brutto ponownie przy każdym odczycie.
Należności naliczane ciągle, domyślna płatność w ramach istniejącej fazy5; brak środków tworzy zaległość. Nie dopisujemy drugiej miesięcznej wypłaty tej samej należności. Prognoza nie księguje.
Premie, noc/nadgodziny, choroba i urlop: osobne komponenty z podstawą i polityką. Do zamknięcia HR-CFG03 nie aktywować nieokreślonych dodatków ani ekwiwalentu; nie ustawiać zera jako cichego domyślnego prawa.

## 4. Staż i wypowiedzenie w grze

Staż: suma rozłącznych przedziałów zatrudnienia tej osoby u tej samej firmy; przerwy nie liczą się, ponowne zatrudnienie nie zeruje stażu. Doba=86400000ms; nie zaokrąglać w górę. Zwykłe nieobecności i płatne odsunięcie liczą się do stażu, pauzy company_time_pauses nie. Ten sam zegar aktywnego czasu firmy przesuwa koniec próby, terminu i wypowiedzenia; nie tworzymy nowego mechanizmu pauzy. Żadna korekta stażu nie działa bez śladu audytowego.
Wypowiedzenie zamraża próg stażu na moment złożenia decyzji. Odprawa używa stażu na effective employment end. Ta różnica jest jawna i testowana.
| Staż w dniu decyzji (employment poza próbą) | Wypowiedzenie |
|---|---:|
| <30dni | 7dni |
| >=30 i <90dni | 14dni |
| >=90dni | 30dni |

Okresy to dokładne doby aktywnego czasu firmy od effective decision time, bez reguł polskiego końca tygodnia/miesiąca.
W próbie:1dzień wypowiedzenia. Umowa terminowa kończy się najpóźniej w zapisanym ends_at; wcześniejsze wypowiedzenie nie przedłuża jej.
Pracownik może pracować do końca albo zostać odsunięty w work_release_at. Odsunięcie nie zmniejsza należnej pensji. Brak podwójnej pozycji „pensja wypowiedzenia” za okres już naliczany zwykłą pensją.
Natychmiastowe rozwiązanie przez gracza jest porozumieniem wymagającym akceptacji kandydata/procesu, nie etykietą „dyscyplinarka” wybieraną dla uniknięcia kosztów. Tryb naruszenia wymaga odrębnej zweryfikowanej reguły domenowej, której ten etap nie aktywuje.

## 5. Odprawa i końcowe rozliczenie

Kandydat odprawy employment, gdy inicjatorem wcześniejszego zakończenia jest firma:
| Staż na koniec zatrudnienia | Mnożnik podstawy |
|---|---:|
| <30dni, poza próbą | 0,5 |
| >=30 i <90dni | 1 |
| >=90dni | 2 |

Próba:0; naturalne wygaśnięcie:0; samodzielne odejście pracownika:0; porozumienie:jawnie zaakceptowana rekompensata.
Jeśli wypowiedzenie firmy przypada dokładnie na naturalny koniec umowy, stosujemy expiry bez odprawy. Jeżeli firma skraca termin choćby o część doby, obowiązuje właściwa odprawa; nie przeliczać jej proporcjonalnie do skrócenia.
Podstawa: większa z (actual_monthly_minor na koniec) oraz średniej ważonej czasem actual_monthly_minor w ostatnich90dniach zatrudnienia; krótszy staż używa dostępnych okresów, przerwy pomijane. Chroni przed obniżką pensji/etatu tuż przed zwolnieniem.
Zmiennych premii/nadgodzin nie włącza się do podstawy odprawy w tym uproszczeniu; nadal płatne jako osobne należności.
Odprawa=round_half_up(base_minor×multiplier). Bez drugiego przeliczenia przez etat.
W kandydacie odprawa i uzgodniona rekompensata mają własny profil kosztowy zero dodatkowych narzutów; jest to jawne uproszczenie gry, nie kwalifikacja podatkowa wszystkich świadczeń.
Management:1×podstawa ryczałtu przy wcześniejszym zakończeniu przez firmę; nie łączyć z odprawą employment. Mandate bez automatycznej odprawy. B2B według podpisanej klauzuli, nie tabeli etatowej.
Wymagalność końcowych nieopłaconych należności: logical employment_ended_at; po opóźnieniu workera rejestrujemy rzeczywisty logical due time.
Każda pozycja ma UNIQUE(employment_period_id, benefit_type, entitlement_slot). Korekta odrębnym zdarzeniem, nie usunięciem oryginału.
Quote jest ważny5min i tylko dla aktualnych wersji umowy/polityki/ewidencji. Potwierdzenie rozlicza do teraz, przelicza koszty i odrzuca stale quote; nie pobiera starej kwoty mimo zmiany podstawy.
Odprawa w quote dla przyszłego końca to prognoza. Potwierdzenie ustala regułę, nie zamraża przyszłych należnych pensji. Każdy późniejszy aneks pokazuje zaktualizowaną prognozę; finalizacja wylicza dokładny stan na końcu.
Niepłynność firmy nie usuwa świadczenia. Finance tworzy dług i płatności częściowe, bez ujemnego konta i bez ponownego kosztu przy spłacie.
Kary morale wariantu A określa Kadry §19.3: −10 albo −8 z limitem −24/14 dni, bez sumowania. Populacja sporu jest snapshotem z logical_at decyzji, bez fikcyjnych 24h po zakończeniu.

## 6. Przykłady kontrolne (PLN)

| Przypadek | Brutto/netto usługi | Narzuty firmy | Łącznie |
|---|---:|---:|---:|
| Operator pełny etat, miesiąc | 15000,00 | 3072,00 | 18072,00 |
| Ten sam poziom płacy, pół etatu | 7500,00 | 1536,00 | 9036,00 |
| 15dni z30, miesięczna15000 | 7500,00 | 1536,00 | 9036,00 |
| Zlecenie80h×100 | 8000,00 | 1638,40 | 9638,40 |
| Aneks15000→18000 dokładnie w połowie30dni | 16500,00 | 3379,20 | 19879,20 |

B2B przykład warunkowy: faktura netto10000, tax23%, odzyskiwalny23% → płatność12300, koszt10000, należność podatkowa do rozliczenia2300. Bez odliczenia koszt12300. To fixture kontraktu, nie przyjęta stawka dla wszystkich firm.
Odprawa przy podstawie15000: staż29dni→7500;30dni→15000;89dni→15000;90dni→30000. Wypłaty za wypowiedzenie i wcześniejsze długi są osobne.
Obniżka actual z15000 do7500 na ostatnie30dni przy poprzednich60dniach15000: podstawa=max(7500,(60×15000+30×7500)/90)=12500; przy mnożniku2 odprawa25000.
Pół etatu przez cały okres: podstawa7500; mnożnik2 daje15000, bez kolejnego×0,5.
Ponowne zatrudnienie30dni + przerwa20dni +30dni → staż60dni, nie80 i nie30. Historyczna wypłata nie jest powtarzana; nowy okres ma własny entitlement. Kolejne rzeczywiste zwolnienie w nowym okresie może rodzić nową odprawę z zachowanym stażem — świadomy koszt ponownego zatrudniania, nie limit resetowany przez usunięcie osoby.

## 7. Zapis, panel i wykonanie

Umowa utrwala wage_basis, amount_minor, fte_fraction, contribution_profile_version, termination_policy_version, effective_from/to, accepted_at i state_version.
Zmiana globalnych parametrów nie przelicza historycznych kosztów; polityka nowych umów stosowana według effective_from. Obowiązkowa zmiana dla istniejących umów wymaga jawnego planu przejścia, przyszłej daty i komunikatu.
Przed implementacją: jedna projekcja warunków pensji z employment_contract_versions; employee_salary_terms nie jest drugim niezależnym źródłem.
Panel GM: kandydat profilu narzutów, macierz wypowiedzeń/odpraw, podstawa i daty, preview kosztów, walidacja jednostek/bps, zakresów i kompletności. Główny administrator bez obowiązkowego komentarza zgodnie z wcześniejszym ustaleniem; audit pozostaje.
UI gracza: pensja/brutto lub faktura/netto oznaczone, pełny koszt, dostępność, koszt odejścia z rozbiciem, szacunek vs należność. SVG i PL/EN/DE.
Autoryzacja i idempotencja we wspólnej akcji Staff. Finance przyjmuje source_component_id i chroni przed duplikatem. Job/HTTP/sweeper korzystają z jednej finalizacji i jednego logical timestamp.
[HR-CONTRACT01](OILEMPIRE_KADRY_KONTRAKT_IMPLEMENTACYJNY.md) określa docelowe tabele/unikaty/lock order i deltę API; finalne migracje i pełny OpenAPI pozostają do wykonania.

## 8. Odbiór i następne bramki

Przypadki obowiązkowe:28/29/30/31dni; granice miesiąca i aneksu; wiele odczytów vs jeden; każdy narzut osobno; pół etatu i zadowolenie; koniec próby; progi29/30/89/90dni; przerwy; anulowanie/expiry; odprawa z trzech triggerów; płatność częściowa; nieobecność; urlop firmy; quote po zmianie wersji; odmowa cudzej firmy.
Lokalna kontrola przykładów nie zastępuje testów Laravel/MySQL, 90dni ekonomii ani prawdziwego kalkulatora ustawowego.
HR-CFG02: naliczanie podstawowe opisane; dodatkowe stawki i absencje zależą od HR-CFG03.
HR-CFG04: kandydat macierzy kosztowej opisany; DOM02 ma decyzję wariantu A w Kadry §19.3, a pozostały remis domenowy DOM08 i kalibracja wymagają odbioru.
Następny krok: finalne migracje/OpenAPI oraz testy grafiku, absencji, płac i cennika rekrutacji §19.2. Nie aktywować nowego narzutu20,48% w YAML v1 bez badania marży i startu firmy.


Kontrola tej rundy:19 asercji arytmetycznych PASS (narzuty, miesiące28–31dni, aneks, progi odpraw, pół etatu i faktura). Agent przejrzał założenia algorytmu; uwzględniono staż, podstawę, pauzę firmy i granice umowy. Nie uruchamiano Laravel/MySQL ani 90dni nowego balansu. Wycofanie dokumentacyjne bez wpływu na dane gry.
