# OilEmpire — audyt gotowości do programowania

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
