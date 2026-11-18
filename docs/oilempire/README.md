# OilEmpire — jedna specyfikacja techniczna gry

[HR-PAY-01](OILEMPIRE_KADRY_UMOWY_PLACE_ODPRAWY.md) ustala kandydatów płac i odpraw, a Kadry §19 wybierają uproszczony wariant A także dla DOM01/DOM02/DOM16. Pozostają kalibracja, końcowe migracje/OpenAPI i testy przed aktywacją; aktywny YAML v1 pozostaje bez zmian.


> **Kadry — wariant A do implementacji:** [mechanika i parametry §19](OILEMPIRE_KADRY_ZATRUDNIENIE_ROZSZERZENIE.md) oraz [HR-CONTRACT01](OILEMPIRE_KADRY_KONTRAKT_IMPLEMENTACYJNY.md) ustalają kierunek P07–P09/P16. DOM01/DOM02/DOM16 mają decyzje A, ale wymagają propagacji do kontraktów maszynowych, kalibracji i testów przed aktywacją. Starsze karty opisują bazowy model historyczny; przy wdrażaniu A stosuje się wskazaną deltę. Aktywny game_balance v1 nie został zmieniony.


Ten dokument jest głównym wejściem do całej specyfikacji. Pliki poniżej są rozdziałami jednego projektu, a nie niezależnymi wariantami silnika. Czytamy je razem zgodnie z zakresem zadania. Dokumentacja opisuje wymagania do implementacji; wdrożenie potwierdzają kod i testy.

**Aktualne decyzje:** OPEN01–OPEN12 są zatwierdzone; [rejestr i zakres realizacji w planie §10](OILEMPIRE_PLAN_PROGRAMOWANIA.md). Wzmianki o otwartych OPENxx w poprzednich przebiegach audytu są historyczne. Karty funkcji i testy implementacji nadal są wymagane.

## 1. Mapa specyfikacji i właściciele reguł

| Dokument | Jest źródłem prawdy dla | Powiązanie |
|---|---|---|
| [Architektura i domena](OILEMPIRE_ARCHITEKTURA_SYSTEMOWA.md) | mechanika gry, stany ekonomiczne, kolejność faz, świat i balans opisowy | podstawa wszystkich pakietów |
| [Kontrakt implementacyjny Kadr](OILEMPIRE_KADRY_KONTRAKT_IMPLEMENTACYJNY.md) | akcje, uprawnienia i plan migracji wariantu A | delta P07–P09/P16; ceny i mechanika w specyfikacji Kadr |
| [DDL Staff A](OILEMPIRE_KADRY_DDL_MYSQL84.sql) | 46 tabel, fizyczne kolumny, FK, indeksy i guardy MySQL 8.4 | wymaga fundamentu P01/P06; nie jest migracją danych produkcyjnych |
| [OpenAPI Staff A](OILEMPIRE_KADRY_OPENAPI_V1.yaml) | 36 operacji HTTP, request/response, błędy i bezpieczne DTO | transport akcji HR-CONTRACT01; polityki DOM03/04 nadal bramkowane |
| [Parametry balansu](OILEMPIRE_GAME_BALANCE_V1.yaml) | wersjonowane wartości parametrów wykonawczych | zgodność z domeną sprawdza symulator; konflikt liczby jest błędem do wyjaśnienia |
| [API](OILEMPIRE_API_SPECYFIKACJA_TECHNICZNA.md) | protokół HTTP, sesje mobile, formaty danych i limity API | transport dla tych samych akcji domenowych |
| [Panel](OILEMPIRE_PANEL_ADMINA_SPECYFIKACJA.md) | ekrany GM, zasoby, nawigacja i UX akcji administracyjnych | akcje podlegają domenie, politykom i §29.7 architektury |
| [Bezpieczeństwo i niezawodność](OILEMPIRE_BEZPIECZENSTWO_SILNIKA.md) | gwarancje transakcji, autoryzacja, outbox, awarie i scenariusze S01–S18 | wymagania przekrojowe wszystkich modułów |
| [Standard modułu](OILEMPIRE_STANDARD_MODULU_WDROZENIE.md) | struktura kodu, manifest, kontrakty, runtime i proces wydania | konkretyzuje modularność §8/§29.6 |
| [Plan programowania](OILEMPIRE_PLAN_PROGRAMOWANIA.md) | kolejność P01–P25, zakres pakietów i karty implementacji | realizuje wymagania powyższych rozdziałów, nie zmienia mechaniki |
| [Audyt gotowości](OILEMPIRE_AUDYT_GOTOWOSCI_PROGRAMOWANIA.md) | wykryte rozbieżności, blokery OPEN01–OPEN09 i dowody kontroli | lista ograniczeń gotowości, nie alternatywne wymagania |

Nie ma zasady „nowszy plik automatycznie wygrywa”. Dokument właściwy dla obszaru określa wymaganie, ale sprzeczność między rozdziałami musi zostać jawnie rozstrzygnięta i poprawiona we wszystkich dotkniętych miejscach. Nie wolno wybierać wygodniejszego zapisu podczas programowania.

## 2. Jak czytać i programować

Dalszy rozdział wykonawczy: [31 kart P06–P13](OILEMPIRE_KARTY_IMPLEMENTACJI_P06_P13.md), [kontrakty i rejestr gotowości](OILEMPIRE_KONTRAKTY_P06_P13.yaml). Razem z fundamentem to 47 kart. Ujawnione luki ekonomiczne dotyczą wskazanych akcji, nie są po cichu wypełniane nowymi wartościami; rejestr DOM01–DOM16 jest na końcu kart i w audycie.

Szczegółowy rozdział wykonawczy: [karty implementacyjne P01–P05](OILEMPIRE_KARTY_IMPLEMENTACJI_P01_P05.md). Zawiera 16 kart danych, kontraktów i transakcji. Rozwija standard i plan; źródłem reguł gry pozostaje architektura.

1. Rozpocznij od mapy powyżej i otwartych problemów audytu.
2. Odczytaj właściwe sekcje domeny, standard modułu i kontrakt bezpieczeństwa.
3. Wybierz pakiet z planu i sprawdź poprzedniki oraz blokery.
4. Dołącz API dla endpointów i panel dla funkcji GM. Web i mobile korzystają z jednej logiki aplikacyjnej.
5. Wypełnij kartę funkcji z §3 planu: dane, kontrakt, przejścia, księgowanie, czas, zdarzenia, UI i testy.
6. Po implementacji przypisz wyniki kontroli do pakietu, kontraktu i konkretnego commita.

Czytanie samego planu nie wystarcza do implementacji modułu. Dokumenty nie są kopiowane do każdej karty: karta zawiera odnośniki i doprecyzowania wymagane dla konkretnej operacji.

## 3. Mapa pakiet → wymagania → odbiór

Skróty: A = architektura, API = specyfikacja API, Panel = specyfikacja panelu, B = bezpieczeństwo, M = standard modułu. Dokumenty są podlinkowane w §1. Każdy pakiet stosuje B §10 (właściwe S01–S18) oraz M §10–§11; poniżej dodatkowy zakres odbioru.

| Pakiet | Sekcje domeny A | Pozostałe obowiązkowe rozdziały | Dowód odbioru / bloker |
|---|---|---|---|
| P01 Platform | §1, §8, §29.6 | M §2–§8; B §1 | walidacja manifestu i zależności |
| P02 Identity | §14, §16 | API §3/§10; B §6 | revokacja, TOTP, Google linking; OPEN05 |
| P03 Ledger | §23.5a/§23.8, §24, §29.4a | API §5; B §3–§4 | transakcje MySQL, idempotencja; OPEN01 |
| P04 Simulation | §23, §25 | B §3–§5/§9 | delta-time, CAS, outbox; OPEN01 |
| P05 Admin/Balance | §18, §29.5–§29.7 | Panel §4–§8; M §5–§8 | approval, single_owner, brak obejścia CLI |
| P06 Company | §1–§2, §19, §22.6 | API §4; B §4 | starter i reputacja raz; OPEN02 |
| P07 Staff | §3, §10 | Panel §5: Gra; API §6 | przydziały ≤100%, polityki dyrektorów |
| P08 Morale/Strikes | §4–§5 | API §5; B §2–§4 | jedno żądanie, terminy i eskalacja |
| P09 Training | §6 | M §9; API §5 | egzamin raz, kurs kończy się w draining |
| P10 Legal | §7.3, §9, §11A | API §6; B §2 | decyzje i odwołania P1/P2 |
| P11 Extraction | §9, §19.1 | API §4–§5; B §4 | plateau, depletion, CAPEX; OPEN03 |
| P12 Logistics | §11A, §13, §21 | API §5; B §3–§4 | bilans bbl i receipt; OPEN04 |
| P13 Incidents | §11, §17.4, §21 | B §2–§5 | eskalacja i naprawy przy strajku |
| P14 Market | §12, §19.3, §25 | API §5; B §3 | globalna kolejność i quote; OPEN01 |
| P15 Contracts | §13, §21 | API §5; B §3–§5 | escrow, transze, receipt/refund; OPEN01/OPEN04 |
| P16 Finance | §10, §19.1, §24 | B §4; Panel §5: Finanse | snapshot i marginalny podatek; OPEN02/OPEN03 |
| P17 Banking | §17.1–§17.3, §21 | B §3–§4 | aukcje, długi, legalne wyłączenie escrow |
| P18 PvP/Protection | §7, §21, §30 | B §2/§7 | limity ofiary, tarcza z timestampu |
| P19 Refining | §31, §21 | API §6; B §3–§4 | faza 2.9/faza 5, flara i ubezpieczenia |
| P20 Monetization | §22, §24.3 | B §8; API §6 | podpis/kwota/refund; OPEN07 |
| P21 Social | §26, §28, §30 | B §6–§7; API §8 | prywatne kanały, moderacja, revokacja |
| P22 Onboarding | §27–§28 | API §8/§10; B §5 | nagrody raz i deduplikacja wysyłki |
| P23 Cms/Privacy | §15–§16, §30 | Panel §5: Treści; B §7 | sanityzacja, zgody, eksport/anonimizacja |
| P24 Web/Mobile | §1, §28 | API całość; Panel właściwe ekrany | E2E, responsywność, stare klienty; OPEN07 |
| P25 Operations | §29 | B §9–§11; M §7–§8 | restore i raport; OPEN08/OPEN09 |

OPEN06 (szczegółowe karty/DDL/OpenAPI) dotyczy każdego pakietu. Mapa jest indeksem wymagań, nie deklaracją zaliczenia testów.

## 4. Jedna zmiana — wszystkie dotknięte rozdziały

| Typ zmiany | Aktualizujemy razem |
|---|---|
| Reguła gry, cena, czas, próg | domenę i właściwy węzeł YAML; karty pakietu, testy i symulator, jeśli obejmuje regułę |
| Operacja lub stan domenowy | domenę, kontrakt akcji, API/panel jeżeli występują, zdarzenia i testy |
| Sesja, uprawnienie, idempotencja | bezpieczeństwo i API; właściwy opis domeny/panelu oraz test odmowy |
| Manifest i runtime modułu | standard modułu, §8/§29.6 architektury, integrację panelu i walidator |
| Sekwencja wydania | standard modułu, plan i runbook Operations |
| Rozstrzygnięcie OPENxx | dokument właściciela wymagania, wszystkie dotknięte rozdziały, test regresji i status w audycie |

Nie duplikujemy całych wzorów i tabel liczbowych do planu/API. Kontrakty transportowe wskazują domenę, jednostki i wersję. Każdy nowy załącznik musi mieć właściciela zakresu, link w tym indeksie oraz powiązanie z pakietami i testami.

## 5. Weryfikacja całości

[Maszynowy kontrakt fundamentu](OILEMPIRE_KONTRAKTY_FUNDAMENTU.yaml) zawiera wycinek OpenAPI i powiązania sekwencji 2FA oraz kluczy tabel. [Testy weryfikatora](OILEMPIRE_TESTY_WERYFIKATORA.py) uruchamiaj wyłącznie w osobnej kopii dokumentacji, bez równoległej edycji: celowo zmieniają pliki i przywracają je w finally. Testy sprawdzają wykrywanie wybranych regresji, nie uruchamiają SQL ani serwera gry.

W katalogu dokumentacji uruchom:
```powershell
python OILEMPIRE_WERYFIKATOR_DOKUMENTACJI.py
python OILEMPIRE_SYMULATOR_BALANSU.py
```

Oba narzędzia wymagają PyYAML. Weryfikator sprawdza wybrane własności strukturalne, a symulator swoje asercje ekonomiczne. Ręczny audyt stanów i testy implementacyjne MySQL/API/mobile są nadal wymagane. Status decyzji i historia problemów są widoczne w audycie; zielony wynik skryptów nie zastępuje kart i testów implementacyjnych.

## 6. Materiały historyczne

[Lista poprawek](OILEMPIRE_MASTER_LISTA_POPRAWEK.md), [audyt końcowy poprzedniego etapu](OILEMPIRE_AUDYT_FINAL_SYNTEZA.md), recenzje i propozycje modeli dokumentują genezę decyzji. Starszy OILEMPIRE_LARAVEL_ARCHITEKTURA_SYSTEMOWA.md jest materiałem historycznym. Te pliki nie stanowią alternatywnej implementacji i nie nadpisują rozdziałów z §1.

Pliki można edytować w GitHubie. Zmiana wymaga zachowania powiązań z §4 i wskazania, które testy faktycznie wykonano.


## Opcjonalne rozszerzenie przyszłe — Agencja pracy

[Specyfikacja Agencji pracy](OILEMPIRE_AGENCJA_PRACY_SPECYFIKACJA.md): planowanie załogi, utrzymanie obsady i delegowana obsługa kadr. Właściciel WorkforceAgency, zależność do Staff/Finance; powiązania P07–P09/P16/P24/P25. Nie zmienia obecnej listy P01–P25 ani14korzeni balansu. Taryfy, kontrakty i testy AG-CFG/CONTRACT/ECON/RELEASE wymagane przed przyszłą aktywacją.

## Karty wykonawcze P14–P25

- [P14–P18: gospodarka, kontrakty, finanse, bankowość i PvP](OILEMPIRE_KARTY_IMPLEMENTACJI_P14_P18.md)
- [P19–P20: rafineria/EPC/ubezpieczenia i płatności](OILEMPIRE_KARTY_IMPLEMENTACJI_P19_P20.md)
- [P21–P24: komunikacja, powiadomienia, CMS/prywatność i klient](OILEMPIRE_KARTY_IMPLEMENTACJI_P21_P24.md)
- [P25: wydanie i eksploatacja](OILEMPIRE_KARTY_IMPLEMENTACJI_P25.md)
- [Manifest 56 kart i właścicieli tabel](OILEMPIRE_KONTRAKTY_P14_P25.yaml), [projekt DDL MySQL 8.4](OILEMPIRE_DDL_P14_P25_MYSQL84.sql), [projekt OpenAPI 3.1](OILEMPIRE_OPENAPI_P14_P25_V1.yaml)
- [Propozycje DOM do zatwierdzenia](OILEMPIRE_PROPOZYCJE_DOM_P14_P25.md)

To dokumentacja do programowania. Nie stanowi działającego silnika ani zatwierdzonej wersji balansu. DDL wymaga sprawdzenia preflight na rzeczywistym schemacie P01–P13, a pełne testy MySQL, PHP, API, UI i 90 dni wymagają implementacji.
