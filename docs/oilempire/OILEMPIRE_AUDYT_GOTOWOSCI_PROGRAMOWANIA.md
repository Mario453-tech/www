# OilEmpire — audyt gotowości do programowania


**BIEŻĄCY STATUS:** właściciel zatwierdził wszystkie propozycje OPEN01–OPEN12. Decyzje projektowe są rozstrzygnięte i wdrożone do dokumentacji źródłowej; rejestr: [plan §10](OILEMPIRE_PLAN_PROGRAMOWANIA.md). Poniższe określenia „otwarte” i liczniki dotyczą historycznych przebiegów audytu. Nie oznacza to gotowego kodu ani kompletu kart DDL/OpenAPI.

> Część wspólnej specyfikacji OilEmpire. [Indeks, mapa pakietów i zasady wspólnej zmiany](README.md). Zakres tego rozdziału: Dowody weryfikacji i rejestr otwartych problemów; nie zastępuje wymagań rozdziałów źródłowych. Sprzeczność między rozdziałami wymaga jawnego rozstrzygnięcia i aktualizacji wszystkich dotkniętych zapisów.


Data: 2026-11-17. Zakres: pełny odczyt kanonicznej architektury, panelu, API, bezpieczeństwa, standardu modułu, YAML i symulatora; przekrojowe sprawdzenie zależności i nowego planu P01–P25. Historyczne recenzje nie zostały ponownie w całości audytowane. Audyt jest statyczny plus wykonanie testów Python; nie obejmuje testów nieistniejącej implementacji Laravel.

## 1. Wynik

Można przygotowywać i programować fundament P01–P05 po wypełnieniu kart funkcji. Nie uznajemy wszystkich modułów za gotowe do bezpośredniego kodowania: pola OpenAPI, DDL i nierozstrzygnięte przypadki poniżej muszą zostać zamknięte przed odpowiednim pakietem. Architektura ma spójny kierunek, ale nie ma dowodu braku wszystkich błędów.

## 2. Ustalenia poprawione w tej rundzie

| ID | Waga | Dowód i poprawka |
|---|---|---|
| DOC01 | P1 | YAML meta.source_document wskazywał nieistniejący OILCORP_LARAVEL_ARCHITEKTURA_SYSTEMOWA.md. Poprawiono na kanoniczny OILEMPIRE_ARCHITEKTURA_SYSTEMOWA.md. generated_at/source_lines opisują historyczne źródło ekstrakcji, nie bieżącą liczbę linii. |
| DOC02 | P1 | Standard wybrał Modules/, lecz architektura nadal instruowała modules/ i src/Filament. Ujednolicono wskazane ścieżki do standardu; wielkość liter ma znaczenie na Linux. |
| DOC03 | P1 | Przykładowe identity/hr/finance nie odpowiadały rejestrowi właścicieli i YAML. Zmieniono kontrakty na core/staff; Training jawnie jest podmodułem Staff, bez zależności do samego siebie. |
| DOC04 | P1 | VWAP w §19.1.1 wskazywał fazę 4, a rynek globalny §23.3 jest w fazie 3. Doprecyzowano publikację ceny w fazie 3 przed konsumentami. |
| DOC05 | P0 | §29.4a ustawiał expires_at=NOW()+24h, ale wymagał 30 dni dla HTTP i trwałości dla domeny; cleaner mógł skasować dowód deduplikacji. Doprecyzowano NULL dla pending/domeny oraz jawne 30 dni od committed_at dla HTTP i 24h dla failed. |
| DOC06 | P2 | Nagłówek YAML wciąż mówił o nierozstrzygniętej eskorcie 0.5, mimo ustalonej wartości 0.6. Poprawiono komentarz; brak zmiany wartości ekonomicznych. |
| DOC07 | P1 | W rozmowie zaproponowano koszt odtworzenia i wartość złoża, choć §19.1 już ustala CAPEX ewidencjonowany × stan i depleted ×0.10. Propozycja rozmowna nie obowiązuje; plan odsyła do istniejącego kanonu, bez renegocjacji liczb. |

## 3. Otwarte problemy i konkretne warunki zamknięcia

| ID | Waga / blokowany pakiet | Co trzeba rozstrzygnąć przed implementacją |
|---|---|---|
| OPEN01 | P1 / P03–P04, P14–P15 | Granica transakcji operacji spot/B2B dotykającej globalnego rynku: lock rank 130 jest opisany jako tylko sweeper, a HTTP ma wykonywać sprzedaż. Karta transakcji musi określić czy HTTP jedynie tworzy polecenie/rezerwację, czy rozlicza przez ograniczony kontrakt; nie wolno wprowadzić drugiej ścieżki globalnego matchingu. |
| OPEN02 | P1 / P16 | Snapshot 00:00 UTC i zakaz śróddziennych przeliczeń kolidują potencjalnie z natychmiastowym sprawdzaniem tarczy/Tieru po nabyciu/transferze aktywa. Określić osobno cenę referencyjną i moment aktualizacji ilości/własności, z testem tuż przed/po północy. |
| OPEN03 | P1 / P11, P16 | P1 obejmuje region i odwiert, a CAPEX odwiertu zawiera opłatę pozwolenia. Przy wielu odwiertach trzeba wskazać jedną alokację kosztu, aby ten sam wpis FTS nie powiększał kilku wycen. Brak zgadywania proporcji w kodzie. |
| OPEN04 | P1 / P12, P15 | Nazwy transport_runs/trips, b2b_escrow_lock/escrow_transactions i companies/players trzeba uzgodnić w jednym słowniku DDL oraz publicznych DTO przed migracjami. |
| OPEN05 | P1 / P02 | Powtórzenie refresh po utracie odpowiedzi wymaga szyfrowanego, krótkiego przechowania wyniku. Przed kodem ustalić TTL, klucz szyfrowania i atomowy zapis razem z rotacją; dokument bezpieczeństwa określa wymaganie, ale nie komplet kolumn. |
| OPEN06 | P1 / każdy pakiet | Publiczne akcje w planie są nazwami docelowymi. Nadal wymagają kart §3: komplet pól, kodów błędów, indeksów, przejść i przykładowych request/response OpenAPI. |
| OPEN07 | P1 / P20, P24 | Premium w aplikacji mobilnej: dobrać zgodny ze sklepem kanał zakupu, serwerową weryfikację transakcji i proces refund. Nie zakładać, że webowa bramka wystarczy w aplikacji sklepowej. |
| OPEN08 | P1 / P25 | Próg inflacji <5%/miesiąc nie ma jeszcze operacyjnej definicji: podaż netto na aktywnego gracza, ceny koszyka czy inny wskaźnik? Zatwierdzić miernik, populację, seed, horyzont i raport przed testem 90 dni. |
| OPEN09 | P2 / P25 | RPO ustalone ≤5 min; RTO ma zostać zmierzone i zatwierdzone. Backup bez testu restore nie zamyka tego punktu. |

P0 blokuje objęty mechanizm; P1 blokuje pakiet do czasu decyzji, P2 wymaga zaplanowanego odbioru. Poprawka DOC05 została zapisana w specyfikacji, lecz jej implementacja nadal wymaga testu czyszczenia po 24h/30 dniach i trwałości domenowych kluczy.

## 4. Wykonane kontrole

- Symulator ekonomii: **73 PASS, 0 FAIL, 0 INFO** na kopii kanonicznego YAML i dokumentów w katalogu roboczym.
- Nowy OILEMPIRE_WERYFIKATOR_DOKUMENTACJI.py: **9 PASS, 0 FAIL** po korekcie metadanych i pobraniu brakujących lokalnie dokumentów referencyjnych.
- Kontrole obejmują unikalność kluczy YAML, komplet 14 korzeni, istnienie źródła, odnośniki kanonicznych dokumentów, 25 unikalnych pakietów, pokrycie korzeni w planie, porządek poprzedników, 18 scenariuszy bezpieczeństwa i UTF-8 bez BOM.
- Pierwszy przebieg ujawnił błędne source_document. Brak dwóch odnośników README był brakiem plików w kopii roboczej, nie błędem GitHuba; pobrano je i ponowiono test.
- 11 wystąpień null w YAML nie oznacza automatycznie 11 nieustalonych parametrów: 10 to przekierowanie cen/czasów ochrony do Legal, jedno to otwarty górny próg. Schema musi jawnie rozpoznawać te przypadki.
- Kontrola poprzedników implementacyjnych nie dowodzi jeszcze acykliczności runtime: manifesty i interfaces nie są wdrożone.
- Nie uruchomiono testów PHP, MySQL, E2E, mobile, restore ani długiej symulacji; przedmiotem zmiany jest dokumentacja i jej weryfikator, nie działający silnik.

## 5. Następny krok

Wypełnić szczegółowe karty P01–P05, zaczynając od rejestru kontraktów i modelu transakcji Ledger/Settlement. Równolegle zamknąć OPEN01, bo wpływa na projekt podstawowej transakcji. Kolejne pakiety doprecyzowywać przed kodowaniem, z zachowaniem pełnego zakresu planu. Po każdej decyzji ponowić właściwe testy dokumentacji; po implementacji zastąpić statyczne założenia dowodami z MySQL i testów kontraktowych.

## 6. Powiązanie w jedną specyfikację

Wspólny README określa właścicieli reguł i mapę P01–P25 → domena/API/panel/bezpieczeństwo → testy/blokery. Każdy kanoniczny rozdział odsyła do indeksu. Zmiana wymaga aktualizacji wszystkich dotkniętych kontraktów; nowszy załącznik nie nadpisuje automatycznie innych reguł. Po integracji wykonano 12 kontroli dokumentacji: 12 PASS, 0 FAIL; symulator ekonomii: 73 PASS, 0 FAIL. Pierwszy test D10 ujawnił błąd wzorca w nowej kontroli indeksu; poprawiono wzorzec i ponowiono przebieg. Otwarte OPEN01–OPEN09 pozostają bez zmian.

## 7. Drugi przegląd dokumentacji implementacji — 2026-11-17

Zakres: pobrano komplet 11 aktualnych plików kanonicznych wraz z SHA. Ponownie przeanalizowano plan, standard modułu, kontrakt bezpieczeństwa i API; porównanie architektury i panelu było ukierunkowane na transakcje, wersjonowanie, approval i runtime. Nie jest to ponowny audyt każdej liczby i każdego wiersza całej domeny ani historycznych recenzji.

| ID | Waga | Sprzeczność i korekta |
|---|---|---|
| REV01 | P1 | Manifest nadal deklarował key training mimo właściciela Staff; usunięto konieczność „zwinięcia przykładu” przez programistę. Przykład ma key staff, provider Staff i dostarczane kontrakty staff.employees/staff.training, bez samozależności. |
| REV02 | P1 | Panel §4.4 natychmiast blokował wszystkie akcje po enabled=false. Opisano runtime active/draining/disabled/faulted oraz kontynuację i recovery. |
| REV03 | P1 | Test panelu P3 wymagał identycznego stanu po re-enable, choć czas i procesy biegną. Test wymaga zachowania danych i historii oraz legalnego rozliczenia czasu. |
| REV04 | P1 | API wymagało aktywnej balance_version dla wszystkich skutków, również trwających procesów. Nowa akcja bierze aktywną wersję, kontynuacja utrwaloną startową. |
| REV05 | P0 | Warunek SQL przejęcia klucza dopuszczał każdy stary created_at, bez status=pending, więc po zmianie stanu mógł nadpisać committed. Ograniczono warunek do failed albo starego pending; committed nigdy nie podlega przejęciu. To wymaga implementacyjnego testu wyścigu, nie tylko kontroli tekstu. |
| REV06 | P1 | API i bezpieczeństwo dopuszczały różny typ JSON pieniędzy. Ustalono jeden typ dziesiętnego stringa w jednostkach najmniejszych dla wszystkich kwot; baza i arytmetyka nadal BIGINT. |
| REV07 | P1 | Graf statusów balansu mógł sugerować rejected → active. Wypisano jawne przejścia; rejected jest końcowy, rollback tworzy kandydaturę. |
| REV08 | P1 | Tabela reason W sugerowała obowiązek również dla owner_admin. Doprecyzowano wyjątek §0.4; owner nie musi wpisywać wolnego powodu. |
| REV09 | P1 | Zakres klucza FTS pomijał company_id i operation mimo zakresu API per aktor/operacja. Dodano firmę i operację do opisu wejścia hasha. Ostateczna karta musi również uwzględnić aktora, zgodnie z kontraktem API. |

Weryfikacja po zmianach: 18 kontroli dokumentacji PASS, 0 FAIL; ekonomia 73 PASS, 0 FAIL. D13 parsuje przykład manifestu zamiast polegać tylko na opisie. Kontrole D14–D18 sprawdzają wskazane własności regresyjne; nie zastępują rzeczywistego wykonania SQL/HTTP ani testu współbieżności. Pierwszy przebieg nowej D13 ujawnił błąd ekstrakcji bloku YAML w weryfikatorze; poprawiono ekstrakcję przed publikacją.

OPEN01–OPEN09 pozostają otwarte. Dodatkowe ograniczenia:
- OPEN10 (P1/P03–P04): opis idempotencji łączy pending w jednej transakcji z przejmowaniem starych pending i stanem failed. Trzeba przed kodem ustalić, czy istnieje osobna trwała próba operacji/lease; nie wolno uznać, że niezatwierdzony pending innej transakcji jest widoczny. Dodać test 1062, timeout, rollback i CAS.
- OPEN11 (P1/P05): append-only balance_versions jest opisane równolegle z aktualizowalnym statusem. Karta DDL ma rozdzielić niezmienny snapshot parametrów od historii przejść/aktualnego stanu aktywacji; brak edycji parametrów już zatwierdzonych.
- OPEN12 (P1/P03): scope actor_id dla HTTP musi być zapisany w finalnym kluczu idempotencji; dla zdarzenia systemowego używa się stabilnej tożsamości domenowej, niezależnej od tego, czy wyzwolił je HTTP, job czy sweeper.

Werdykt: nie wszystko jest jeszcze zgodne ani gotowe do bezpośredniej implementacji. Poprawiono konkretne błędy dokumentacji; 12 otwartych problemów nadal wymaga kart i decyzji przed wskazanymi pakietami. Wynik 18/73 PASS nie zamyka tych problemów.

## 8. Zamknięcie decyzji OPEN01–OPEN12

Właściciel zaakceptował wszystkie 12 propozycji. Status każdego: **decyzja zatwierdzona / dokumentacja zaktualizowana / implementacja do wykonania**. OPEN06 zostaje zrealizowane przez obowiązkową procedurę kart funkcji, nie przez fikcyjne oznaczenie wszystkich kart jako gotowych.

| Zakres | Aktualizacja |
|---|---|
| OPEN01/02/03/08/09/10/11/12 | Architektura: spot i lock rank 130, bieżący Tier, alokacja CAPEX, wskaźniki inflacji, RTO, transakcje/lease, model balansu i scope kluczy |
| OPEN04/06 | Plan: słownik encji i obowiązkowe karty przed programowaniem |
| OPEN05/07/12 | API: refresh 2 minuty, kanały sklepowe i klucze HTTP/domeny |
| OPEN02/09/10 | Bezpieczeństwo: dwa widoki wyceny, RTO i rozdzielenie transakcji od dzierżawy |

Zmiana OPEN02 jawnie zastępuje poprzedni zapis o obniżeniu Tieru dopiero następnego dnia. Podatek pozostaje dobowy, limity dobowe i już wykonana wymiana nie są cofane. Wartości ekonomiczne YAML nie były zmieniane. Do kart wykonawczych pozostają m.in. konkretne kolumny/indeksy, wagi koszyka, profile agentów, konfiguracja kluczy i produktów sklepów; to nie są nierozstrzygnięte warianty opisanych decyzji.

Kontrola po wprowadzeniu zatwierdzonych decyzji: dokumentacja 23 PASS/0 FAIL; symulator 73 PASS/0 FAIL. Zmieniono wyłącznie komentarze opisowe YAML, bez zmiany wartości parametrów. Testy kodu Laravel, współbieżności MySQL, płatności i restore pozostają do wykonania po implementacji.

## 9. Karty wykonawcze P01–P05

Dodano [16 kart fundamentu](OILEMPIRE_KARTY_IMPLEMENTACJI_P01_P05.md): P01 3, P02 4, P03 3, P04 3, P05 3. Karty określają dane, indeksy, kontrakty, transakcje, odmowy i testy. Uzgodniono jedną transakcję settlement+mutacja oraz możliwość nowej wersji balansu z identycznym hashem treści podczas rollbacku. Blokada activation_gate obejmuje również zmiany ról, aby liczba administratorów nie ścigała się z approval.

Wyniki kontroli: dokumentacja 28 PASS/0 FAIL, symulator 73 PASS/0 FAIL. Kontrole są strukturalne i regresyjne; nie wykonano jeszcze kodu SQL/HTTP kart. Karty P06–P25 oraz pełne wykonywalne migracje/OpenAPI pozostają pracą następnych etapów. Migracje wymagają porównania ze szkieletem i docelowym MySQL; brak twierdzenia o wdrożeniu fundamentu.

## 10. Przegląd zespołowy kart P01–P05 — 2026-11-17

**Werdykt: zgodność częściowa; fundament nie jest jeszcze gotowy do literalnej implementacji bez korekt poniżej.** Dwa niezależne przeglądy agentów zakończone: API/panel/testy i transakcje/bezpieczeństwo. Agent architektury nie ukończył porównania; nie przypisujemy mu niezależnego potwierdzenia. Dodatkowe porównanie architektury wykonał agent główny. Przeglądy nie edytowały źródeł wymagań; ta runda zapisuje ustalenia, nie udaje ich naprawienia.

Snapshot źródeł: karty `357bd84dcca496dbb61009631b653236f6b89d02`; architektura `94e34f16d5da6a14dc8e0e0e2072846318bc5ad8`; API `554780d6a175b2c081b79df1bfa9276c4fdd7b74`; panel `7d3ab01b0b75f1100c08f14f714db165c66bf9c6`; standard `53d13b582bcc49e00bcd95e9ac2309e075a7cdfd`; bezpieczeństwo `ee090a5723624fe0a0f9e7903cf06df2d6211392`; weryfikator `e55409e673d1b8c2678f7130bcf407b8881e3577`.

P1 = blokujący brak kontraktu/sprzeczność przed kodowaniem danego zakresu; P2 = konkretny błąd lub niejednoznaczność do poprawy. To uwagi do projektu, nie wykryte podatności istniejącego silnika.

| ID | Waga | Miejsce | Dowód / problem | Wymagana korekta i odbiór |
|---|---|---|---|---|
| TEAM01 | P1 | Karty P02-F01 l.74 / P02-F02 l.84–87 | Login 202 nie wydaje challenge_secret wymaganego przez verify. | Dodać secret do odpowiedzi i hash do wyzwania; test całej ścieżki login→verify. |
| TEAM02 | P1 | Karty P02-F01/F03/F04 l.72–108 | Brak relacji tokenów Sanctum z api_sessions oraz sprawdzenia revokacji/auth_epoch w każdym Bearer request. | Zdefiniować relację personal_access_tokens.api_session_id i kontrolę sesji/epoch; test natychmiastowej odmowy po logout. |
| TEAM03 | P2 | Karty P02-F03 l.100–102 / API §3 | Zużyty token z tym samym kluczem i innym payloadem może dawać 409 albo revokację/401. | Ustalić kolejność: identyczny replay dozwolony; inne użycie zużytego refresh revokuje rodzinę. Test macierzy przypadków. |
| TEAM04 | P1 | Karty P04-F02 l.169–173 | CAS wymaga bieżącego rekordu operacji, ale zdefiniowano tylko próby. | Dodać operations z aktualnym lease/fencing/state_version i transakcyjny acquire/reclaim; test dwóch przejęć i starego workera. |
| TEAM05 | P1 | Karty P05-F02/F03 l.206–220 | Brak globalnego rekordu aktywnej wersji; UNIQUE(version_id) nie gwarantuje jednego active ani nośnika activation_gate. | Dodać singleton balance_current z active_version_id/state_version i zdefiniować blokadę bramki. |
| TEAM06 | P1 | Karty P03/P04 vs architektura §23.5a | Nowe rekordy settlement, due-events, rezerwacji i lease nie mają miejsca w kanonicznym porządku blokad. | Rozszerzyć tabelę rank/podkolejności; test HTTP/job/sweeper i capture/release na MySQL. |
| TEAM07 | P2 | Karty P03-F02 l.133 | UNIQUE(operation_id,account_id,reservation_kind) używa nieopisanej kolumny reservation_kind. | Dodać kolumnę i słownik albo świadomie zmienić UNIQUE; test DDL. |
| TEAM08 | P1 | Karty P03-F01/F02 l.127–139 | PostTransfer nie precyzuje obciążania salda dostępnego po rezerwacjach. Saldo100/rezerwacja80/transfer30 łamie available≥0 bez debetu. | Wymagać amount≤available, capture wskazuje rezerwację i zużywa ją raz; wspólna blokada i test konkurencji. |
| TEAM09 | P2 | Karty P05-F03 l.220 | Sformułowanie outbox po commicie może sugerować opóźniony INSERT. | Zapisać jednoznacznie INSERT outbox z aktywacją w transakcji; dopiero publikacja po commicie. |
| TEAM10 | P1 | Architektura §23.7 l.1597 vs OPEN01 §23.3 | Tabela zastrzega slippage wyłącznie dla sweepera, lecz zaakceptowany spot HTTP wymaga wspólnego stanu slippage/płynności. | Oddzielić publikowanie ceny/matching od atomowej aktualizacji okna sprzedaży podczas spot. |
| TEAM11 | P1 | Architektura §19.2 l.1311 vs §19.1/§1/OPEN02 | Stary wzór tarczy nadal dyskontuje aktywa ×0.70 i nazywa pojemność magazynu nierozstrzygniętą. | Ujednolicić do 100% wyceny aktywów dla progów i już ustalonego pustego magazynu; nie zmieniać zatwierdzonych kwot. |
| TEAM12 | P2 | Weryfikator D22, D24–D28 | D22 łączy company_wealth_state z niezwiązanym client_key; pozostałe testy głównie liczą nagłówki i szukają tekstu. | Nazwać kontrole strukturalnymi, poprawić D22 i dodać maszynowe schematy sekwencji API/DDL. Nie traktować PASS jako dowodu semantyki. |

### Pokrycie potwierdzone

Czasy 15 min/30 dni/90 dni, challenge 5 min/5 prób, replay refresh 2 min są zgodne między kartami i API. Właściciel bez wymaganego wolnego powodu, single_owner i emergency z dwoma administratorami są zgodne z panelem. Wspólny Settlement i transakcja rozliczenie+komenda mają właściwy kierunek. Jawnie odłożonych migracji wykonywalnych i pełnego OpenAPI pozostałych modułów nie liczymy jako nowych błędów.

### Kontrole i granice dowodu

Ponowiono istniejące narzędzia w kopii roboczej: dokumentacja 28 PASS/0 FAIL, symulator 73 PASS/0 FAIL. Wynik strukturalny nie wykrywa powyższych sprzeczności kontraktowych; nie może być bramką samodzielnie potwierdzającą gotowość do kodowania. Nie wykonano testów implementacyjnych SQL/HTTP/MySQL ani pełnego przeglądu każdego paragrafu i każdej liczby. TEAM01–TEAM12 mają status **otwarte po przeglądzie zespołowym**. Zatwierdzone decyzje produktowe OPEN01–OPEN12 pozostają zatwierdzone; nowe uwagi dotyczą ich niepełnego lub sprzecznego odwzorowania.

### Kolejność korekt

Najpierw TEAM01/02/03 (sesje), TEAM04/05/06 (rekordy kontrolne i blokady), TEAM07/08 (rezerwacje), następnie TEAM09/10/11 (spójność źródeł) i TEAM12 (zakres testów). Po poprawkach wymagany ponowny przegląd tych kontraktów, a po kodowaniu testy procesowe i konkurencyjne na MySQL.
