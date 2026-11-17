# OilEmpire — standard modułu i specyfikacja wdrażania

> Część wspólnej specyfikacji OilEmpire. [Indeks, mapa pakietów i zasady wspólnej zmiany](README.md). Zakres tego rozdziału: Struktura modułu, manifest, zależności i cykl wdrożenia. Reguły gry pozostają w architekturze. Sprzeczność między rozdziałami wymaga jawnego rozstrzygnięcia i aktualizacji wszystkich dotkniętych zapisów.


**Status:** obowiązujący projekt do implementacji. Rozwija §8 i §29.6–29.7 architektury, §4 panelu oraz kontrakt bezpieczeństwa silnika. Nie oznacza, że opisane klasy lub polecenia już istnieją. Schemat manifestu i cykl życia są tu opisane szczegółowo; sprzeczne przykłady w innych rozdziałach należy poprawić wraz z tym standardem, bez automatycznego nadawania pierwszeństwa jednemu plikowi.

Zatwierdzone rozstrzygnięcia OPEN01–OPEN12 są zebrane w [planie programowania §10](OILEMPIRE_PLAN_PROGRAMOWANIA.md). Nowy DDL i kontrakty stosują je przed przykładami historycznymi.

## 1. Wersje i źródła prawdy

| Wersja | Źródło | Kiedy zmieniana |
|---|---|---|
| Wydanie gry | tag Git oraz pełny commit SHA niezmiennego artefaktu | przy wydaniu kodu; np. 1.4.0 |
| Publiczny kontrakt | kontrakt API v1 / nazwany kontrakt modułu z SemVer | przy zmianie publicznego interfejsu; major przy zmianie niekompatybilnej |
| Parametry | globalna balance_version i params_version węzła modułu | przez kandydaturę i zatwierdzenie §29.7 |
| Schemat danych | standardowa historia migracji Laravel | nowa migracja, bez edycji migracji już wdrożonej |

Klasy PHP nie mają osobnych numerów wersji. Publiczny kontrakt oznacza interface/DTO albo schemat komunikatu udostępniony innym modułom; prywatna implementacja może się zmienić bez zmiany kontraktu. Zmiana formuły ekonomicznej wymaga wydania kodu i zgodności z historycznymi procesami, nawet gdy struktura API pozostaje taka sama.

Przy uruchomieniu release zapisuje: release_id, commit_sha, artifact_hash, manifest_hash, listę migracji, zestaw wersji kontraktów oraz aktywną balance_version. Historia aktywacji jest oddzielna od artefaktu kodu; zmiana parametrów nie tworzy nowego wydania PHP.

## 2. Kanoniczna struktura

Wybieramy katalog Modules/ (wielka litera), namespace Modules\\, mapowanie Composer PSR-4 do Modules/. Dotychczasowe przykłady modules/<Nazwa>/module.json traktujemy jako wcześniejszy szkic; kanoniczny manifest to Modules/<Name>/manifest.yaml. Jeden centralny ModuleRegistry ładuje manifesty dostarczone z wydaniem; nie wymaga dopisywania warunków dla każdego modułu.

Przykład:
- Modules/Staff/manifest.yaml
- Contracts/ — publiczne interfaces, DTO i zdarzenia z wersją schematu
- Application/Actions/ i Application/Queries/ — komendy i odczyty
- Domain/ — reguły, wartości i dozwolone przejścia
- Infrastructure/ — repozytoria, adaptery i implementacje kontraktów
- Http/Controllers/, Http/Requests/, Http/Resources/
- Models/, Policies/, Jobs/, Listeners/
- Providers/StaffServiceProvider.php
- Routes/web.php, Routes/api.php, Routes/console.php
- Database/Migrations/, Database/Factories/, Database/Seeders/
- Filament/Resources/, Filament/Pages/, Filament/Widgets/
- Resources/views/, Resources/css/, Resources/js/, Resources/icons/
- Lang/pl/, Lang/en/, Lang/de/
- Schemas/balance.schema.json, Schemas/events/, OpenApi/
- Tests/Unit/, Tests/Feature/, Tests/MySql/, Tests/Contract/
- docs/module.md — publiczne operacje, stany, obsługa awarii i rollback

Nie tworzymy pustych warstw dla modułu, który ich nie potrzebuje. Tylko Contracts/ stanowi publiczną powierzchnię kodową. Wspólne mechanizmy techniczne nie mogą przejmować reguł biznesowych modułów.

## 3. Manifest i walidacja

Manifest zawiera dane, nie wykonywalny kod. YAML jest parsowany bez własnych tagów obiektowych; klasy muszą należeć do dostarczonego artefaktu i spełniać wymagany interface.

Przykład struktury (wartości kontraktów ilustracyjne):
```yaml
manifest_schema_version: 1
key: staff
namespace: Modules\Staff
provider: Modules\Staff\Providers\StaffServiceProvider
label_key: staff.module.name
requires:
  all:
    - { module: core, contract: core.access, version: "^1.0" }
    - { module: core, contract: core.ledger, version: "^1.0" }
provides:
  - { contract: staff.employees, version: "1.0.0" }
  - { contract: staff.training, version: "1.0.0" }
balance_schema: Schemas/balance.schema.json
routes:
  web: Routes/web.php
  api: Routes/api.php
settlement:
  handlers: [TrainingDueEventsHandler]
permissions: [training.view, training.enroll, training.gm]
reason_codes: [TRAINING_FEE]
events:
  - { name: training.course.completed, schema_version: 1 }
panel:
  resources: [TrainingCourseResource]
```

Kontrakty core.access i core.ledger są zewnętrznymi zależnościami Staff. staff.employees i staff.training są kontraktami dostarczanymi przez Staff; brak zależności do samego siebie. Training jest obszarem wewnątrz Modules/Staff, bez własnego manifestu, korzenia balansu i stanu runtime. Pola panel mają pełny kontrakt w specyfikacji panelu §4.

Enabled, premium i params_version pochodzą z zatwierdzonego snapshotu balansu, a nie z edytowanego manifestu produkcyjnego. Manifest rejestruje możliwości kodu; runtime przechowuje stan wykonania. Brak węzła balansu dla modułu ekonomicznego blokuje aktywację.

Walidator sprawdza: schemat manifestu, unikalność key, klas, nazw tras, uprawnień, reason_codes i kontraktów; istnienie wskazanych plików; zgodność wersji; kompletność tłumaczeń; kompletność schematu parametrów; brak cykli zależności. requires.all oznacza wszystkie zależności. Alternatywy zapisuje się jawnie w requires.any jako grupy, nie tekstem „extraction|market”. Nieznany wymagany klucz jest błędem, nigdy cichym pominięciem.

## 4. Kontrakty i zależności

- Moduł wywołuje publiczny kontrakt innego modułu przez kontener. Nie używa jego prywatnego Eloquent, tabeli, repozytorium ani pliku widoku do zmiany stanu.
- Synchroniczny kontrakt jest używany do operacji wymagającej atomowego wyniku, np. księgowania opłaty. Zdarzenia outbox uruchamiają skutki po commicie, np. powiadomienie.
- Rejestr buduje skierowany graf zależności; cykl blokuje wydanie. Rozwiązaniem jest jawny kontrakt lub zmiana odpowiedzialności, nie pominięcie sprawdzenia.
- Każdy kontrakt opisuje argumenty, wynik, błędy, jednostki, autoryzację, transakcję i idempotencję. Schemat zdarzenia zawiera event_id, aggregate_id/version i schema_version.
- Dodawanie opcjonalnego pola może być kompatybilne; zmiana znaczenia lub usunięcie pola wymaga nowego major. Stary konsument ma działać przez okres migracji.
- Testy architektury wykrywają importy poza Contracts/, niedozwolone zapisy tabel obcego modułu i cykle. Sama analiza importów nie dowodzi braku zależności SQL: zapytania i migracje podlegają przeglądowi.

## 5. Cykl życia

Stan runtime przechowuje module_runtime_states: module_key (UNIQUE), state, state_version, desired_enabled, activation_balance_version, last_transition_at, actor_id, request_id. Do zmian służy jedna akcja z CAS i audytem.

| Stan | Zachowanie |
|---|---|
| installed | kod wykryty, kontrakty i migracje jeszcze nie potwierdzone; brak akcji gracza |
| ready | walidacja, migracje i testy zaliczone; oczekuje zatwierdzenia aktywacji |
| active | nowe i istniejące operacje działają według polityk |
| draining | nowe zobowiązania zablokowane; istniejące procesy i ich rozliczenia nadal działają |
| disabled | brak nowych procesów i obsługi zwykłych mutacji; dane zachowane, bez zaległych procesów wymagających handlera |
| faulted | stan incydentu blokuje wskazane operacje; przechowywany poprzedni stan i przyczyna, brak automatycznej reaktywacji |

Przejścia: installed → ready → active → draining → disabled; disabled → ready → active. Faulted jest kontrolowaną ścieżką incydentu ze stanu wykonawczego; wznowienie wymaga sprawdzenia integralności i audytowanej decyzji. Nie oznacza automatycznej pauzy ekonomii ani zamrożenia zegara.

Żądanie enabled=false oznacza zamiar wyłączenia i przejście do draining, a nie natychmiastowe pominięcie faz. Disabled jest dopuszczalne dopiero, gdy moduł potwierdzi brak aktywnych zobowiązań, zaległych due-events, nieprzekazanych zdarzeń i rezerwacji wymagających jego obsługi. OPEX i aktywa pasywne mają jawnego właściciela naliczania przed zmianą stanu. Nie da się wyłączyć modułu finansowego lub innej wymaganej infrastruktury, dopóki aktywny zależny moduł jej potrzebuje.

Wyłączenie premium dla nowych operacji nie cofa uprawnień procesów rozpoczętych zgodnie z §22.6. Wygaśnięcie VIP i wyłączenie całego modułu to różne decyzje.

## 6. ActivationGuard i ModuleAvailability

ActivationGuard sprawdza w jednej kontrolowanej operacji: wersję runtime, zatwierdzony snapshot, wymagane kontrakty, stan migracji, wyniki walidatora, aktywność zależności i raporty wymaganych testów powiązane z hashem artefaktu. Aktywacja balansu odbywa się na granicy sweepa zgodnie z §29.7.

ModuleAvailability rozróżnia read, start_new_process, continue_existing i recovery. Sprawdzenie jest wywoływane we wspólnej akcji, nie tylko w nawigacji/HTTP. Status draining blokuje start, lecz dopuszcza kontynuację. Trasy i klasy pozostają zarejestrowane, aby obsłużyć stare procesy, odczyt i recovery.

API zwraca 503 MODULE_UNAVAILABLE albo MODULE_DRAINING dla niedostępnej operacji; odpowiedź zawiera request_id i komunikat PL/EN/DE. Odczyt potwierdzonej historii może pozostać dostępny. Panel pokazuje stan, zależności, liczbę procesów do domknięcia i blokery. Ukrycie przycisku nie stanowi zabezpieczenia.

## 7. Migracje, uruchomienie i wycofanie

1. Zbuduj niezmienny artefakt z przypiętymi zależnościami i sprawdź manifesty.
2. Uruchom testy i przygotuj backup/rollback dla zmiany schematu.
3. Wdróż migracje expand kompatybilne ze starym kodem. Migracje są standardowymi migracjami Laravel z globalnie unikalnymi nazwami.
4. Wdróż kod web/workerów, odśwież wygenerowany rejestr/cache i potwierdź zgodność wszystkich instancji.
5. Przeprowadź health check, dopiero potem aktywuj funkcję i zatwierdzony balans.
6. Usunięcia starych kolumn/kontraktów wykonaj w późniejszym wydaniu po zakończeniu migracji danych i starych procesów.

Rollback kodu jest możliwy tylko, jeśli stary artefakt rozumie obecny schemat i procesy. Wyłączenie przyjmowania nowych operacji nie usuwa zobowiązań. Historyczny balans przywraca się jako nową kandydaturę, a błędne księgowanie koryguje kompensacją.

Wdrożenie dla grupy testowej dopuszczalne dla prezentacji i funkcji izolowanych. Globalnego rynku, reguł księgowania ani wspólnego balansu nie wolno dzielić na różne wartości dla losowych graczy. Test gospodarki odbywa się w oddzielnym świecie testowym z syntetycznymi danymi.

## 8. Narzędzia do zaprogramowania

Poniższe komendy są projektowane, nie istnieją automatycznie w Laravelu:
- modules:make Staff — generator katalogu, manifestu i minimalnych testów; nie aktywuje i nie wykonuje migracji.
- modules:validate — manifesty, graf zależności, kontrakty, schematy i kolizje; exit 0 tylko przy braku błędów.
- modules:status — rzeczywisty stan, wersje i blokery; odczyt.
- modules:plan-disable staff — lista procesów, zależności i zasobów do domknięcia; odczyt.
- modules:reconcile staff — raport niezgodności bez samodzielnego naprawiania ksiąg.

Aktywacja, draining i wznowienie są akcjami aplikacyjnymi wywoływanymi przez panel i autoryzowane CLI, z tym samym approval, step-up i audytem. CLI nie stanowi obejścia procedury §29.7. Wgrywanie ZIP i instalowanie kodu przez panel nie jest częścią systemu.

CI wykonuje właściwe testy jednostkowe, procesowe, uprawnień, MySQL i kontraktów, analizę statyczną/formatowanie, kontrolę zależności i build zmienionych zasobów. Zmiana balansu wymaga symulatora 0 FAIL. Raport gotowości zapisuje hash artefaktu i wynik każdej wymaganej kontroli; brak kontroli to nie wynik PASS.

## 9. Przykład: szkolenie pracownika

EnrollEmployeeInCourse:
- wejście: aktor, employee_id, course_type, idempotency_key i oczekiwana wersja stanu;
- warunki: Staff active, własny pracownik, właściwe kwalifikacje, brak kolidującego kursu i dostępne środki;
- wykonanie: rozliczenie, blokady w porządku §23, opłata przez kontrakt core.ledger, rekord szkolenia z due_at i balance_version, outbox — jedna transakcja;
- wynik: identyfikator i stan kursu, cena serwera, termin i wersja stanu.

CompleteCourse:
- HTTP odczytu, job i sweeper uruchamiają tę samą procedurę, według utrwalonego due_at i zasad egzaminu;
- jedna zmiana kwalifikacji/wyniku pod kluczem due_slot, jeden wynik finansowy i zdarzenie completed;
- strajk, zwolnienie pracownika i brak obecności są obsługiwane zgodnie z główną specyfikacją; dokument nie ustala nowych liczb.

Draining Staff (obszar szkoleń):
- nowe zapisy odrzucone; rozpoczęte kursy, egzaminy i rozliczenia kończą się normalnie;
- status pokazuje remaining_courses, pending_due_events, pending_outbox i zależności;
- disabled Staff dopiero po zakończeniu obsługi wszystkich obszarów Staff, także płac i strajków; historia i certyfikaty pozostają dostępne przez publiczny kontrakt;
- powrót przez ready ponownie weryfikuje zgodność migracji, kontraktów i parametrów.

## 10. Backlog programistyczny i odbiór

| Kolejność | Zadanie | Kryterium ukończenia |
|---|---|---|
| M01 | JSON Schema manifestu + parser | błędne i nieznane klucze odrzucone; brak wykonania treści manifestu |
| M02 | ModuleRegistry + cache związany z artefaktem | deterministyczny rejestr, błędy kolizji, bez skanowania katalogów przy każdym żądaniu |
| M03 | Graf i wersje kontraktów | cykl, brak modułu i niezgodny major blokują aktywację |
| M04 | Wspólny kontekst aktora i ModuleAvailability | jednakowa odmowa web/API/GM/job dla niedozwolonej operacji |
| M05 | Runtime states + audyt/CAS | konkurencyjne przejścia nie gubią decyzji, enabled=false rozpoczyna draining |
| M06 | ActivationGuard + istniejący proces balansu | approval i testy dotyczą dokładnego snapshotu/artefaktu; brak obejścia CLI |
| M07 | Generator i walidator | nowy Staff przechodzi walidację, ma PL/EN/DE i podstawowe testy |
| M08 | Integracja API/panel/settlement/outbox | brak ręcznej edycji rdzenia dla zasobu zgodnego z kontraktami |
| M09 | Pilotaż Training | zapis, zakończenie kursu i draining całego Staff działają na MySQL z testami awarii |
| M10 | Proces wydania | raport gotowości, expand/contract, zgodność workerów i przetestowany rollback |

Dodatkowe scenariusze: zależny moduł blokuje wyłączenie dostawcy; job uruchomiony podczas draining kończy istniejący proces; disabled nie kasuje danych; zmiana balansu nie zmienia wersji rozpoczętego kursu; niedostępna klasa/kontrakt blokuje wydanie przed aktywacją. Obowiązują też S01–S18 z kontraktu bezpieczeństwa.

## 11. Definicja gotowego modułu

Moduł ma właściciela danych, publiczne kontrakty, jawne przejścia stanów i wyłączenie bez utraty postępu; komplet API/panel/tłumaczeń dla jego zakresu; przechodzące właściwe testy i udokumentowaną obsługę incydentu. Bramka wydania potwierdza to na dokładnym artefakcie. Dokumentacja nie zastępuje testów implementacji.
