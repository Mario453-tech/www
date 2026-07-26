# Wspólny system pracowników, role działowe, obsada logistyki, morale, podwyżki i strajki

## Cel dokumentu

Rozbuduj istniejący system HR tak, aby pracownicy wszystkich działów byli realną częścią mechaniki gry.

System ma obsługiwać:

```text
pracowników zwykłych i technicznych,
role i specjalizacje działowe,
przypisywanie pracowników do obiektów,
obsadę hubów i innych elementów logistyki,
morale,
oczekiwania płacowe,
żądania podwyżek,
negocjacje,
ryzyko odejścia,
konflikty pracownicze,
strajki działowe,
wpływ pracowników na mechaniki gry,
prosty panel gracza,
prosty panel admina,
cykliczne przeliczanie przez istniejący modularny tick.
```

Nie twórz kolejnego, oderwanego systemu pracowników.

Wykorzystaj istniejące elementy:

```text
board_members
technical_staff
board_roles
hr_specializations
staff_specializations
employee_contracts
employment_history
hr_events
HRService
TechnicalTeamService
TrainingService
TickEngine
TickModuleScheduler
TickModuleConfigRepository
```

Nowa warstwa ma połączyć te elementy wspólnym API, bez jednorazowego przepisywania całej gry.

---

# 1. Obecny stan projektu

W projekcie istnieją obecnie dwa główne źródła pracowników.

## 1.1 Zarząd i pracownicy działowi

Tabela:

```text
board_members
```

Rozróżnienie:

```text
member_type = director
member_type = staff
```

Pracownicy mają między innymi:

```text
dział przez board_roles,
specjalizację przez hr_specializations,
skille,
lojalność,
ambicję,
ryzyko korupcji,
pensję,
kontrakt.
```

## 1.2 Personel techniczny

Tabela:

```text
technical_staff
```

Posiada własne:

```text
spec_code,
spec_name,
skill_level,
salary,
status,
manager_id,
zadania techniczne,
kolejkę zadań,
przypisania do odwiertów.
```

## 1.3 Obecny problem

System zatrudniania poprawnie rozpoznaje specjalistów technicznych i zapisuje ich do `technical_staff`.

Dla specjalistów innych działów nie ma jeszcze kompletnej, uniwersalnej ścieżki zatrudnienia.

Nie wolno budować obsady logistyki bez naprawienia tego przepływu.

Docelowo kandydat z działu:

```text
logistics
finance
legal
hr
```

musi móc zostać zatrudniony jako zwykły pracownik działowy, nawet jeśli dyrektor tego działu jest już zatrudniony.

Zajęte stanowisko dyrektora nie może blokować zatrudnienia pracowników podlegających dyrektorowi.

---

# 2. Najważniejsze zasady architektury

## 2.1 Nie twórz trzeciego źródła pracowników

Nie dodawaj osobnej tabeli typu:

```text
logistics_staff
```

Pracownicy logistyki mają być zwykłymi pracownikami działowymi.

Źródła pracownika pozostają:

```text
board_member
technical_staff
```

Nowe serwisy mają obsługiwać oba źródła przez wspólny identyfikator.

## 2.2 Wprowadź wspólną referencję pracownika

Dodaj obiekt wartości:

```text
src/Employee/EmployeeRef.php
```

Przykładowa struktura:

```php
final class EmployeeRef
{
    public function __construct(
        public readonly string $sourceType,
        public readonly int $sourceId,
        public readonly int $playerId
    ) {}
}
```

Dozwolone źródła:

```text
board_member
technical_staff
```

## 2.3 Dodaj wspólne repozytorium pracowników

Plik:

```text
src/Employee/EmployeeRepository.php
```

Repozytorium ma ujednolicić odczyt pracownika z obu tabel.

Przykładowe metody:

```php
public function find(EmployeeRef $ref): ?array;

public function listForPlayer(
    int $playerId,
    ?string $departmentCode = null,
    bool $activeOnly = true
): array;

public function resolveDepartment(EmployeeRef $ref): ?string;

public function resolveSalary(EmployeeRef $ref): float;

public function resolveSkills(EmployeeRef $ref): array;

public function resolveTraits(EmployeeRef $ref): array;

public function isActive(EmployeeRef $ref): bool;
```

Zwracany wspólny model powinien zawierać:

```text
source_type
source_id
player_id
first_name
last_name
department_code
role_code
specialization_code
specialization_name
salary
status
skills
traits
hired_at
```

Dla `technical_staff` repozytorium zwraca trwałe cechy zapisane w tabeli (`trait_loyalty`, `trait_corruption_risk`, `trait_ambition`). Stare rekordy są uzupełniane deterministycznie przez bootstrap schematu, więc widok HR i mechaniki morale nie pokazują już wszystkim technikom neutralnych wartości `5/10`.

Nie duplikuj logiki odczytu pracownika w wielu serwisach.

---

# 3. Naprawa i ujednolicenie zatrudniania

## 3.1 Rozdziel kandydatów na dyrektorów i pracowników

Zasada:

```text
brak specialization_id
→ kandydat na dyrektora

specialization_id ustawione
→ kandydat na zwykłego pracownika działu
```

Wyjątek techniczny:

```text
specjalizacja techniczna
→ zapis do technical_staff
```

Pozostałe specjalizacje:

```text
hr
finance
legal
logistics
```

mają być zapisywane jako:

```text
board_members.member_type = staff
```

## 3.2 Zajęta rola blokuje tylko dyrektora

Obecne sprawdzenie zajętego stanowiska należy stosować tylko, gdy kandydat jest dyrektorem.

Dla pracownika działowego nie sprawdzaj:

```text
czy dział ma już dyrektora
```

Dyrektor działu może zarządzać wieloma pracownikami.

## 3.3 Ustal rolę działową pracownika

Dla pracownika z `hr_specializations.department` pobierz właściwe `board_roles.id`.

Przykład:

```text
department = logistics
→ board_roles.code = logistics
```

Zapis:

```text
board_members.role_id = rola działu
board_members.member_type = staff
board_members.specialization_id = wybrana specjalizacja
```

## 3.4 Kontrakt i pierwsza pensja

Każdy zwykły pracownik działowy powinien dostać:

```text
employee_contracts,
employment_history,
status active,
datę zatrudnienia,
pensję z oferty.
```

Wszystkie operacje finansowe wykonuj przez:

```text
FinancialTransactionService
```

Nie wykonuj bezpośrednich zmian:

```sql
UPDATE players SET cash = ...
```

## 3.5 Popraw HeadhunterService

Przy zatrudnianiu technicznego specjalisty nie twórz dwóch niezależnych pracowników reprezentujących tę samą osobę.

Docelowa zasada:

```text
specjalista techniczny
→ technical_staff

specjalista innego działu
→ board_members member_type=staff
```

Przeanalizuj istniejące rekordy utworzone podwójnie przez headhuntera.

Dodaj bezpieczną migrację, która:

```text
wykrywa prawdopodobne duplikaty,
nie usuwa danych automatycznie bez jednoznacznego dopasowania,
zapisuje raport do logu admina,
dla jednoznacznych duplikatów zachowuje techniczny rekord jako źródło operacyjne,
oznacza lustrzany rekord board_members jako nieaktywny albo powiązany.
```

Nie kasuj danych historycznych.

---

# 4. Jasne znaczenie ról i specjalizacji

## 4.1 Model pojęciowy

```text
Dział
= gdzie pracownik pracuje.

Rola / specjalizacja zawodowa
= czym pracownik się zajmuje.

Skille
= jak dobrze wykonuje zadania.

Cechy
= jak zachowuje się w firmie.

Przypisanie
= jaki obiekt lub zakres obecnie obsługuje.

Morale
= jak skutecznie pracuje i czy grozi konfliktem.
```

## 4.2 Źródła specjalizacji

Zachowaj istniejące tabele, ale nadaj im jednoznaczne znaczenie.

### `hr_specializations`

To jest katalog zawodów i specjalizacji używany przy rekrutacji:

```text
nazwa,
dział,
rzadkość,
widełki pensji.
```

To właśnie ta tabela ma być głównym katalogiem ról działowych.

### `staff_specializations`

Pozostaw jako techniczne perki lub dodatkowe cechy personelu technicznego:

```text
bonus produkcji,
redukcja zużycia,
redukcja incydentów,
szybkość napraw,
redukcja katastrof.
```

Nie używaj jej jako głównego katalogu wszystkich zawodów.

### `TechnicalTeamService::SPECS`

Na razie pozostaw jako katalog kompatybilności z zadaniami technicznymi.

Nie usuwaj go w tym samym wdrożeniu.

Docelowo może stać się konfiguracją zapasową dla danych z bazy, ale nie łącz tej migracji z systemem morale i logistyki.

---

# 5. Efekty ról i specjalizacji

Dodaj tabelę:

```text
employee_role_effects
```

Przykładowy schemat:

```sql
CREATE TABLE IF NOT EXISTS employee_role_effects (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,

    specialization_code VARCHAR(80) NOT NULL,
    effect_key VARCHAR(100) NOT NULL,
    effect_type ENUM('percent','flat','multiplier','bool') NOT NULL DEFAULT 'percent',
    effect_value DECIMAL(12,4) NOT NULL DEFAULT 0.0000,

    target_scope ENUM(
        'department',
        'hub',
        'pipeline',
        'warehouse',
        'road_transport',
        'port',
        'b2b',
        'well',
        'global'
    ) NOT NULL DEFAULT 'department',

    skill_weights_json JSON NULL,
    description_pl VARCHAR(255) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,

    UNIQUE KEY uq_employee_role_effect (
        specialization_code,
        effect_key,
        target_scope
    ),
    KEY idx_employee_role_effect_scope (target_scope, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Dodaj serwis:

```text
src/Employee/EmployeeRoleEffectService.php
```

Metody:

```php
public function getEffectsForSpecialization(string $code): array;

public function calculateEffects(
    EmployeeRef $employee,
    string $targetScope,
    array $context = []
): array;

public function saveEffect(array $data): int;

public function deleteEffect(int $effectId): void;
```

## 5.1 Skille wzmacniają efekt

`skill_weights_json` określa, które skille wpływają na daną rolę.

Przykład operatora huba:

```json
{
  "organization": 0.40,
  "analysis": 0.35,
  "stress": 0.25
}
```

Przykład negocjatora płacowego:

```json
{
  "negotiation": 0.60,
  "analysis": 0.20,
  "ethics": 0.20
}
```

Nie zapisuj wzorów osobno w widokach.

Całe liczenie ma być w serwisie.

---

# 6. Role działowe do dodania

## 6.1 Logistyka

Dodaj do `hr_specializations`:

```text
hub_operator
Operator huba

transport_dispatcher
Dyspozytor transportu

warehouse_coordinator
Koordynator magazynu

pipeline_logistics_specialist
Specjalista logistyki rurociągów

b2b_delivery_coordinator
Koordynator dostaw B2B

terminal_operator
Operator terminala

oil_flow_analyst
Analityk przepływu ropy
```

## 6.2 Kadry

```text
recruiter
Rekruter

salary_negotiator
Negocjator płacowy

employee_mediator
Mediator pracowniczy

training_specialist
Specjalista ds. szkoleń

retention_specialist
Specjalista utrzymania pracowników
```

## 6.3 Finanse

```text
financial_analyst
Analityk finansowy

cost_controller
Kontroler kosztów

credit_specialist
Specjalista kredytowy

internal_auditor
Audytor wewnętrzny

financial_risk_specialist
Specjalista ryzyka finansowego
```

## 6.4 Dział prawny

```text
licensing_specialist
Specjalista licencji

legal_counsel
Radca prawny

settlement_negotiator
Negocjator ugód

compliance_specialist
Specjalista zgodności

legal_risk_analyst
Analityk ryzyka prawnego
```

## 6.5 Technika

Nie usuwaj istniejących specjalizacji.

Uzupełnij tylko brakujące role, jeśli są potrzebne:

```text
automation_engineer
Automatyk

critical_failure_specialist
Specjalista awarii krytycznych

technical_inspector
Inspektor techniczny
```

---

# 7. Wspólny stan pracownika

Nie dodawaj osobno pól morale do `board_members` i `technical_staff`.

Dodaj wspólną tabelę:

```text
employee_state
```

```sql
CREATE TABLE IF NOT EXISTS employee_state (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,

    player_id INT NOT NULL,
    source_type ENUM('board_member','technical_staff') NOT NULL,
    source_id INT NOT NULL,
    department_code VARCHAR(40) NOT NULL,

    morale DECIMAL(5,2) NOT NULL DEFAULT 65.00,
    salary_satisfaction DECIMAL(5,2) NOT NULL DEFAULT 70.00,
    expected_salary DECIMAL(14,2) NOT NULL DEFAULT 0.00,

    leave_risk DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    strike_support DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    workload DECIMAL(5,2) NOT NULL DEFAULT 0.00,

    relation_status ENUM(
        'normal',
        'unhappy',
        'raise_requested',
        'dispute',
        'strike_threat',
        'on_strike',
        'leaving',
        'inactive'
    ) NOT NULL DEFAULT 'normal',

    last_raise_at DATETIME NULL,
    last_raise_request_at DATETIME NULL,
    last_morale_tick_at DATETIME NULL,

    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,

    UNIQUE KEY uq_employee_state_source (source_type, source_id),
    KEY idx_employee_state_player_department (
        player_id,
        department_code,
        relation_status
    ),
    KEY idx_employee_state_risk (
        player_id,
        leave_risk,
        strike_support
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Dodaj serwis:

```text
src/Employee/EmployeeStateService.php
```

Metody:

```php
public function ensureState(EmployeeRef $employee): array;

public function getState(EmployeeRef $employee): array;

public function updateMorale(
    EmployeeRef $employee,
    float $delta,
    string $reason,
    array $meta = []
): array;

public function updateSalarySatisfaction(EmployeeRef $employee): array;

public function calculateExpectedSalary(EmployeeRef $employee): float;

public function calculateLeaveRisk(EmployeeRef $employee): float;

public function calculateStrikeSupport(EmployeeRef $employee): float;

public function listAtRiskEmployees(
    int $playerId,
    int $limit,
    int $offset
): array;
```

Każda zmiana morale ma mieć log.

---

# 8. Uniwersalne przypisywanie pracowników

Dodaj tabelę:

```text
employee_assignments
```

```sql
CREATE TABLE IF NOT EXISTS employee_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,

    player_id INT NOT NULL,
    source_type ENUM('board_member','technical_staff') NOT NULL,
    source_id INT NOT NULL,

    department_code VARCHAR(40) NOT NULL,
    role_code VARCHAR(80) NOT NULL,

    target_type ENUM(
        'department',
        'hub',
        'pipeline',
        'warehouse',
        'road_transport',
        'port',
        'b2b',
        'well'
    ) NOT NULL,

    target_id INT NULL,
    allocation_pct DECIMAL(5,2) NOT NULL DEFAULT 100.00,

    status ENUM('active','released','suspended') NOT NULL DEFAULT 'active',
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    released_at DATETIME NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,

    KEY idx_employee_assignment_employee (
        source_type,
        source_id,
        status
    ),
    KEY idx_employee_assignment_target (
        player_id,
        target_type,
        target_id,
        status
    ),
    KEY idx_employee_assignment_department (
        player_id,
        department_code,
        status
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Dodaj serwis:

```text
src/Employee/EmployeeAssignmentService.php
```

Metody:

```php
public function assign(
    EmployeeRef $employee,
    string $roleCode,
    string $targetType,
    ?int $targetId,
    float $allocationPct = 100.0
): array;

public function release(int $assignmentId, int $playerId): array;

public function getActiveAssignments(EmployeeRef $employee): array;

public function getTargetAssignments(
    int $playerId,
    string $targetType,
    ?int $targetId
): array;

public function calculateAllocatedPct(EmployeeRef $employee): float;
```

## 8.1 Zasady przypisywania

Na pierwszym etapie:

```text
pracownik może mieć jedno pełne przypisanie operacyjne,
allocation_pct domyślnie 100%,
łączna aktywna alokacja nie może przekraczać 100%,
pracownik na szkoleniu lub zwolniony nie może zostać przypisany,
pracownik zajęty zadaniem technicznym nie może zostać równolegle przypisany na 100%.
```

Walidacja musi być w serwisie i transakcji.

---

# 9. Obsada logistyki

Dodaj serwis:

```text
src/LogisticsStaffingService.php
```

Serwis ma łączyć:

```text
pracowników,
role,
skille,
morale,
przypisania,
wymaganą obsadę obiektu,
efekty kierownika logistyki.
```

## 9.1 Pierwszy zakres

Na pierwszym etapie wdroż obsadę dla:

```text
hubów,
transportu drogowego jako zakres działowy,
magazynów,
rurociągów,
dostaw B2B.
```

Najważniejszym pierwszym obiektem są huby.

## 9.2 Wymagana obsada hubów

Ustawienia mają być konfigurowalne w panelu admina.

Domyślne wartości:

```text
Mały hub:
1 operator huba

Średni hub:
2 operatorów huba

Duży hub:
3 operatorów huba

Hub strategiczny / terminal:
4 operatorów, gdy taki typ zostanie dodany
```

Opcjonalne role wspierające:

```text
dyspozytor transportu,
analityk przepływu ropy.
```

## 9.3 Brak obsady nie blokuje huba

Hub bez pełnej obsady nadal działa, ale dostaje kary.

Domyślne progi:

| Pokrycie obsady | Przepustowość | Ryzyko incydentu | Czas reakcji |
|---:|---:|---:|---:|
| 100% lub więcej | bez kary | bez kary | bez kary |
| 67–99% | -10% | +5% | +5% |
| 34–66% | -25% | +15% | +20% |
| 1–33% | -40% | +25% | +35% |
| 0% | -50% | +35% | +50% |

Wszystkie wartości muszą być konfigurowalne.

## 9.4 Efekt skilli

Dla operatora huba użyj wag:

```text
Organizacja: 40%
Analiza: 35%
Odporność na stres: 25%
```

Dla dyspozytora transportu:

```text
Organizacja: 45%
Analiza: 35%
Negocjacje: 20%
```

Dla koordynatora magazynu:

```text
Organizacja: 50%
Analiza: 40%
Odporność na stres: 10%
```

Dla koordynatora B2B:

```text
Organizacja: 35%
Analiza: 30%
Negocjacje: 25%
Odporność na stres: 10%
```

## 9.5 Morale wpływa na efekt

Przykładowy mnożnik morale:

```text
morale 0–20   → 0.70
morale 21–40  → 0.85
morale 41–60  → 1.00
morale 61–80  → 1.05
morale 81–100 → 1.10
```

## 9.6 Kierownik logistyki

Dyrektor logistyki z `board_members` ma dawać bonus całemu działowi.

Uwzględnij:

```text
skill_organization,
skill_analysis,
skill_negotiation,
morale kierownika.
```

Przykładowe efekty kierownika:

```text
zwiększenie efektywności obsady,
zmniejszenie kosztów transportu,
zmniejszenie ryzyka opóźnień,
zmniejszenie kary za niepełną obsadę.
```

Dodaj metodę:

```php
public function getLogisticsManagerBonus(int $playerId): array;
```

Nie licz bonusu bezpośrednio w widoku.

## 9.7 Wynik obliczeń obsady

Metoda:

```php
public function calculateTargetStaffing(
    int $playerId,
    string $targetType,
    ?int $targetId
): array;
```

Powinna zwracać:

```text
required_count
assigned_count
coverage_pct
average_skill
average_morale
throughput_mult
incident_risk_mult
delay_risk_mult
response_time_mult
cost_mult
missing_roles
assigned_employees
warnings
```

---

# 10. Podpięcie obsady do mechanik gry

Obsada nie może być tylko informacją w interfejsie.

## 10.1 Huby

Podłącz efekt do:

```text
przepustowości huba,
ryzyka incydentu,
degradacji przy przeciążeniu,
czasu reakcji na incydent,
czasu wznowienia pracy.
```

Wykorzystaj jeden wspólny wynik z `LogisticsStaffingService`.

Nie licz obsady osobno w:

```text
HubService,
HubEconomyService,
HubIncidentService,
widoku.
```

## 10.2 Transport drogowy

Dyspozytor transportu może wpływać na:

```text
koszt kursu,
ryzyko opóźnienia,
ryzyko pustego przebiegu,
czas realizacji.
```

Na pierwszym etapie efekt może być działowy, bez przypisywania do pojedynczego pojazdu.

## 10.3 Magazyn

Koordynator magazynu może wpływać na:

```text
ryzyko przepełnienia,
straty magazynowe,
błędy przy przyjęciu dostawy,
czas przyjęcia ropy.
```

## 10.4 Rurociągi

Specjalista logistyki rurociągów nie zastępuje inżyniera technicznego.

Podział:

```text
inżynier techniczny
→ naprawa, konserwacja i stan techniczny

specjalista logistyki rurociągów
→ planowanie przepływu, przeciążenia, wykorzystanie przepustowości
```

## 10.5 B2B

Koordynator dostaw B2B może wpływać na:

```text
ostrzeżenia o niedoborze ropy,
ryzyko operacyjnego opóźnienia,
planowanie dostaw częściowych,
rekomendowany zapas magazynowy.
```

Nie zmniejszaj bezpośrednio kary kontraktowej bez jawnej reguły w konfiguracji.

---

# 11. Morale pracowników

Dodaj serwis:

```text
src/Employee/EmployeeRelationsService.php
```

Morale ma być liczone na podstawie zdarzeń i stanu firmy.

## 11.1 Co obniża morale

```text
pensja poniżej oczekiwanej,
długi czas bez podwyżki,
wysokie przeciążenie,
praca przy przeciążonym hubie,
odrzucone żądanie podwyżki,
odłożona decyzja o podwyżce,
zwolnienia w dziale,
aktywny konflikt,
strajk,
częste awarie i kryzysy,
zaległości płacowe, jeśli zostaną dodane.
```

## 11.2 Co podnosi morale

```text
pełna podwyżka,
częściowo zaakceptowana podwyżka,
premia,
awans,
ukończone szkolenie,
dobre wyniki działu,
pełna obsada,
niski poziom przeciążenia,
udane mediacje HR.
```

## 11.3 Cechy pracownika

Uwzględnij:

```text
lojalność
→ wolniejszy spadek morale i mniejsze ryzyko odejścia

ambicja
→ szybszy wzrost oczekiwanej pensji i większa potrzeba awansu

odporność na stres
→ mniejszy spadek morale podczas kryzysów

ryzyko korupcji
→ nie wpływa bezpośrednio na podwyżkę, ale może zostać wykorzystane później przez sabotaż
```

---

# 12. Oczekiwana pensja i zadowolenie płacowe

Dodaj wspólne liczenie:

```text
expected_salary
salary_satisfaction
```

Przykładowa baza oczekiwanej pensji:

```text
widełki hr_specializations,
poziom skilli,
staż,
rzadkość specjalizacji,
ambicja,
liczba ukończonych szkoleń,
aktualny zakres odpowiedzialności.
```

Przykładowe zadowolenie:

```text
salary_satisfaction =
current_salary / expected_salary × 100
```

Ogranicz wynik do:

```text
0–120
```

Interpretacja w UI:

```text
0–39   bardzo niezadowolony
40–59  niezadowolony
60–79  akceptuje pensję
80–99  zadowolony
100+   bardzo zadowolony
```

---

# 13. Żądania podwyżek

Dodaj tabelę:

```text
employee_raise_requests
```

```sql
CREATE TABLE IF NOT EXISTS employee_raise_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,

    player_id INT NOT NULL,
    source_type ENUM('board_member','technical_staff') NOT NULL,
    source_id INT NOT NULL,

    current_salary DECIMAL(14,2) NOT NULL,
    requested_salary DECIMAL(14,2) NOT NULL,
    negotiated_salary DECIMAL(14,2) NULL,

    reason_code VARCHAR(60) NOT NULL,
    status ENUM(
        'pending',
        'accepted',
        'negotiated',
        'rejected',
        'postponed',
        'expired',
        'cancelled'
    ) NOT NULL DEFAULT 'pending',

    decision_deadline_at DATETIME NULL,
    resolved_at DATETIME NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,

    KEY idx_raise_player_status (player_id, status, created_at),
    KEY idx_raise_employee (source_type, source_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 13.1 Warunki utworzenia żądania

Przykładowe warunki:

```text
morale poniżej progu,
zadowolenie z pensji poniżej progu,
minął wymagany czas od ostatniej podwyżki,
brak aktywnego żądania,
pracownik jest aktywny,
ambicja zwiększa prawdopodobieństwo.
```

Nie twórz żądania losowo bez przyczyny.

## 13.2 Decyzje gracza

```text
Przyznaj pełną podwyżkę
Zaproponuj mniejszą podwyżkę
Odmów
Odłóż decyzję
Zwolnij pracownika
```

## 13.3 Domyślne efekty

### Pełna podwyżka

```text
pensja = żądana pensja,
morale +20,
lojalność efektywna +5,
ryzyko odejścia spada.
```

### Mniejsza podwyżka

```text
uruchom negocjacje,
przy sukcesie morale +8,
przy porażce żądanie pozostaje aktywne albo przechodzi w spór.
```

### Odmowa

```text
morale -20,
ryzyko odejścia rośnie,
poparcie dla strajku rośnie.
```

### Odłożenie

```text
morale -5,
nowy termin decyzji,
po terminie automatyczne pogorszenie konfliktu.
```

Wartości mają być konfigurowalne.

---

# 14. Negocjacje podwyżki

Skuteczność negocjacji ma uwzględniać:

```text
negocjacje pracownika,
ambicję pracownika,
lojalność pracownika,
morale pracownika,
skille dyrektora HR,
obecność negocjatora płacowego,
morale działu HR.
```

Dodaj metodę:

```php
public function negotiateRaise(
    int $playerId,
    int $requestId,
    float $offeredSalary
): array;
```

Wynik ma być determinowany kontrolowanym mechanizmem losowym, zapisanym w logu.

Zwróć:

```text
success
accepted_salary
chance_pct
employee_reaction
morale_delta
```

---

# 15. Konflikty i strajki

## 15.1 Strajk ma narastać etapami

Status konfliktu:

```text
normal
unhappy
raise_requested
dispute
strike_threat
on_strike
resolved
```

Strajk nie może pojawić się bez wcześniejszych sygnałów.

## 15.2 Tabela strajków

```sql
CREATE TABLE IF NOT EXISTS employee_strikes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,

    player_id INT NOT NULL,
    department_code VARCHAR(40) NOT NULL,

    status ENUM(
        'threat',
        'active',
        'negotiation',
        'resolved',
        'failed'
    ) NOT NULL DEFAULT 'threat',

    reason_code VARCHAR(80) NOT NULL,
    participant_count INT NOT NULL DEFAULT 0,
    support_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,

    productivity_penalty_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    cost_penalty_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    incident_risk_bonus_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,

    started_at DATETIME NULL,
    ends_at DATETIME NULL,
    resolved_at DATETIME NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,

    KEY idx_employee_strike_player (
        player_id,
        department_code,
        status
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Dodaj tabelę uczestników:

```text
employee_strike_members
```

Pola:

```text
strike_id
source_type
source_id
support_pct
is_active
joined_at
left_at
```

## 15.3 Warunki groźby strajku

Przykład:

```text
średnie morale działu poniżej 35,
kilku pracowników ma aktywny spór,
wysokie poparcie dla strajku,
odrzucono kilka podwyżek,
dział jest przeciążony.
```

## 15.4 Strajk logistyki

Domyślne efekty:

```text
przepustowość hubów -30%,
koszt transportu drogowego +20%,
ryzyko opóźnień +15%,
czas reakcji na awarie logistyczne +25%,
obsada strajkujących pracowników nie jest liczona jako aktywna.
```

## 15.5 Inne działy

### Technika

```text
czas napraw +40%,
koszt usług awaryjnych +15%,
mniejsza dostępność pracowników do zadań.
```

### Finanse

```text
opóźnione raporty,
gorsza skuteczność kontroli kosztów,
brak części bonusów finansowych.
```

### Kadry

```text
wolniejsze rekrutacje,
mniejsza skuteczność negocjacji,
szybszy wzrost niezadowolenia w innych działach.
```

### Prawny

```text
wolniejsze sprawy,
mniejsza skuteczność części działań prawnych,
większe ryzyko przekroczenia terminów.
```

Efekty muszą być konfigurowalne.

---

# 16. Zdarzenia i logi pracownicze

Dodaj tabelę:

```text
employee_events
```

```sql
CREATE TABLE IF NOT EXISTS employee_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,

    player_id INT NOT NULL,
    source_type ENUM('board_member','technical_staff') NULL,
    source_id INT NULL,
    department_code VARCHAR(40) NULL,

    event_key VARCHAR(80) NOT NULL,
    title VARCHAR(180) NOT NULL,
    message VARCHAR(600) NOT NULL,
    meta_json JSON NULL,

    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_employee_events_player (
        player_id,
        is_read,
        created_at
    ),
    KEY idx_employee_events_employee (
        source_type,
        source_id,
        created_at
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Przykładowe eventy:

```text
employee_morale_dropped
employee_overworked
raise_requested
raise_accepted
raise_negotiated
raise_rejected
raise_postponed
employee_dispute_started
strike_threat_started
strike_started
strike_resolved
employee_left_company
employee_assignment_changed
employee_training_completed
```

Nie zapisuj danych ważnych tylko w ogólnym `hr_events`.

Możesz nadal tworzyć skrócone powiadomienie HR dla gracza, ale szczegółowa historia ma być w `employee_events`.

---

# 17. Integracja z istniejącym tickiem

Tick jest już modularny.

Nie przebudowuj ponownie `tick.php`.

Dodaj:

```text
src/Tick/Modules/EmployeesModule.php
```

Klucz:

```text
employees
```

Rekomendowana kolejność:

```text
35
```

Czyli:

```text
Bank
Employees
Players i produkcja
```

Dzięki temu aktualny stan strajków i morale jest dostępny przed przeliczeniem produkcji i części gospodarki.

Rekomendowana konfiguracja:

```text
enabled = 1
interval_ticks = 2
max_items_per_run = 200
```

## 17.1 Zadania modułu

```text
zapewnienie employee_state dla nowych pracowników,
aktualizacja oczekiwanej pensji,
aktualizacja zadowolenia płacowego,
przeliczenie przeciążenia,
zmiany morale,
utworzenie żądań podwyżek,
eskalacja nierozwiązanych żądań,
przeliczenie ryzyka odejścia,
przeliczenie poparcia dla strajku,
tworzenie groźby strajku,
uruchamianie strajków,
kończenie czasowych efektów,
przetwarzanie maksymalnie max_items_per_run pracowników.
```

Moduł ma zwracać statystyki:

```text
employees_processed
states_created
morale_changes
raise_requests_created
disputes_created
strike_threats_created
strikes_started
employees_left
errors
```

## 17.2 Użyj limitu modułu

Pobierz limit przez istniejący `TickContext`.

Nie pobieraj wszystkich pracowników naraz.

Zapytania mają używać:

```sql
LIMIT :maxItemsPerRun
```

Dodaj stabilny kursor albo sortowanie po:

```text
last_morale_tick_at
id
```

aby kolejni pracownicy byli przetwarzani w następnych uruchomieniach.

## 17.3 Katalog modułów

Dopisz moduł do:

```text
TickModuleCatalog
```

Polska nazwa:

```text
Pracownicy i morale
```

Opis:

```text
Aktualizuje morale, oczekiwania płacowe, żądania podwyżek, ryzyko odejścia i konflikty pracownicze.
```

## 17.4 Popraw synchronizację rekomendowanego interwału

W `TickModuleConfigRepository::syncModules()` nowy moduł nie powinien zawsze dostawać:

```text
interval_ticks = 1
```

Przy tworzeniu wpisu użyj:

```php
TickModuleCatalog::recommendedInterval($module->key())
```

oraz:

```php
TickModuleCatalog::recommendedLimit($module->key())
```

---

# 18. Bootstrap schematu

Dodaj jeden bootstrap:

```text
src/EmployeeSystemBootstrap.php
```

Funkcja:

```php
ensureEmployeeSystemSchema(): void
```

Ma tworzyć:

```text
employee_state
employee_assignments
employee_role_effects
employee_raise_requests
employee_strikes
employee_strike_members
employee_events
employee_system_config
```

Nie rozrzucaj `ALTER TABLE` po:

```text
EmployeeStateService
EmployeeRelationsService
LogisticsStaffingService
public/hr.php
public/logistics.php
```

Bootstrap ma być idempotentny.

Podłącz go w `src/init.php` zgodnie z obecnym wzorcem nowych modułów.

---

# 19. Konfiguracja systemu

Dodaj tabelę:

```text
employee_system_config
```

Przykładowe klucze:

```text
module_enabled
morale_default
morale_raise_full_bonus
morale_raise_partial_bonus
morale_raise_reject_penalty
morale_raise_postpone_penalty
raise_request_morale_threshold
raise_request_salary_satisfaction_threshold
raise_request_cooldown_days
raise_decision_deadline_hours
strike_department_morale_threshold
strike_min_disputes
strike_support_threshold
employee_leave_risk_threshold
hub_staffing_small_required
hub_staffing_medium_required
hub_staffing_large_required
hub_understaffing_penalty_level_1
hub_understaffing_penalty_level_2
hub_understaffing_penalty_level_3
hub_understaffing_penalty_empty
```

Dodaj:

```text
src/Employee/EmployeeConfigService.php
```

Wszystkie serwisy mają czytać wartości przez ten serwis.

---

# 20. Refaktoryzacja strony logistyki

Obecna strona logistyki jest duża.

Przed dodaniem obsady wydziel warstwę kontrolera.

Docelowe pliki:

```text
src/LogisticsPageController.php
src/LogisticsPage/ActionsTrait.php
src/LogisticsPage/DataTrait.php
src/LogisticsPage/StaffingTrait.php
src/LogisticsPage/ViewDataTrait.php
```

`public/logistics.php` powinien odpowiadać głównie za:

```text
init,
autoryzację,
utworzenie kontrolera,
obsługę POST,
zbudowanie viewData,
wyrenderowanie widoku.
```

Podziel widok:

```text
templates/views/logistics/main.php
templates/views/logistics/tabs/overview.php
templates/views/logistics/tabs/hubs.php
templates/views/logistics/tabs/pipelines.php
templates/views/logistics/tabs/transport.php
templates/views/logistics/tabs/staffing.php
templates/views/logistics/tabs/alerts.php
templates/views/logistics/modals/assign_employee.php
templates/views/logistics/modals/employee_details.php
```

Nie usuwaj obecnych funkcji strony podczas refaktoryzacji.

Najpierw zapewnij test zgodności danych widoku.

---

# 21. Panel gracza — HR

Rozbuduj istniejącą stronę HR.

Zakładki:

```text
Pracownicy
Rekrutacja
Podwyżki
Morale
Konflikty
Szkolenia
Historia
```

## 21.1 Karta pracownika

Pokazuj:

```text
imię i nazwisko,
dział,
rola i specjalizacja,
pensja,
oczekiwana pensja,
morale,
zadowolenie z pensji,
ryzyko odejścia,
status relacji,
aktualne przypisanie,
obciążenie,
najważniejsze skille.
```

Akcje:

```text
Daj podwyżkę
Negocjuj
Przenieś
Przypisz do obiektu
Wyślij na szkolenie
Zwolnij
```

Nie pokazuj surowych kluczy technicznych.

## 21.2 Podwyżki

Lista:

```text
Pracownik
Dział
Obecna pensja
Żądana pensja
Powód
Termin decyzji
Ryzyko odmowy
Akcje
```

## 21.3 Morale

Pokaż podsumowanie działów:

```text
średnie morale,
liczba niezadowolonych,
aktywne żądania,
ryzyko strajku,
przeciążenie.
```

---

# 22. Panel gracza — logistyka

Dodaj zakładkę:

```text
Obsada
```

## 22.1 Lista obiektów

Kolumny:

```text
Obiekt
Typ
Wymagana obsada
Obecna obsada
Efektywność
Morale zespołu
Ryzyko błędu
Wpływ na działanie
Akcje
```

## 22.2 Karta huba

Dodaj sekcję:

```text
Obsada huba
```

Pokaż:

```text
wymagana liczba operatorów,
przypisani pracownicy,
brakujące role,
pokrycie obsady,
średnie skille,
średnie morale,
mnożnik przepustowości,
mnożnik ryzyka incydentu,
ostrzeżenia.
```

Przyciski:

```text
Przypisz pracownika
Zmień obsadę
Oddeleguj
Pokaż pracownika
```

## 22.3 Modal przypisania

Filtruj kandydatów:

```text
tylko pracownicy gracza,
tylko dział logistyki,
tylko aktywni,
bez pełnego przypisania,
preferowana odpowiednia specjalizacja.
```

Pokaż informację:

```text
jak zmieni się efektywność po przypisaniu,
jak zmieni się przepustowość,
jak zmieni się ryzyko incydentu.
```

---

# 23. Panel admina

Rozbuduj panel:

```text
HR / Pracownicy
```

Podzakładki:

```text
Pulpit
Pracownicy
Role i specjalizacje
Efekty ról
Przypisania
Morale
Podwyżki
Strajki
Ustawienia
Logi
```

Panel ma być prosty i opisany po polsku.

## 23.1 Pulpit

Karty:

```text
Aktywni pracownicy
Niezadowoleni pracownicy
Aktywne żądania podwyżek
Pracownicy zagrożeni odejściem
Groźby strajku
Aktywne strajki
Nieobsadzone huby
```

## 23.2 Role i specjalizacje

Pola:

```text
Kod
Nazwa
Dział
Rzadkość
Minimalna pensja
Maksymalna pensja
Można przypisać do
Aktywna
```

## 23.3 Efekty ról

Pola po polsku:

```text
Specjalizacja
Działa na
Efekt
Wartość
Skille wpływające na efekt
Opis dla gracza
Włączony
```

Przykład:

```text
Operator huba
Działa na: Hub
Efekt: Zwiększa przepustowość
Wartość bazowa: 5%
Skille: Organizacja 40%, Analiza 35%, Odporność 25%
```

## 23.4 Przypisania

Tabela:

```text
Pracownik
Dział
Specjalizacja
Przypisany do
Alokacja
Morale
Status
Akcje
```

## 23.5 Ustawienia

Nie pokazuj tylko kluczy typu:

```text
strike_support_threshold
```

Pokaż:

```text
Minimalne poparcie potrzebne do rozpoczęcia strajku
```

Każde ustawienie ma mieć:

```text
polską nazwę,
krótki opis,
jednostkę,
bezpieczny zakres,
wartość zalecaną.
```

## 23.6 Logi

Filtry:

```text
Wszystkie
Morale
Podwyżki
Przypisania
Strajki
Odejścia
Błędy
```

Dodaj paginację.

---

# 24. Powiadomienia

Wykorzystaj obecny system powiadomień dyrektora i HR.

Przykłady:

```text
Pracownik żąda podwyżki.
Morale działu logistyki spadło poniżej 40.
Hub nie ma wymaganej obsady.
Dział logistyki grozi strajkiem.
Rozpoczął się strajk logistyki.
Pracownik zamierza odejść z firmy.
```

Każde powiadomienie powinno prowadzić do właściwej zakładki.

---

# 25. Zabezpieczenia i transakcje

Każda operacja zmieniająca:

```text
pensję,
stan pracownika,
przypisanie,
żądanie podwyżki,
status strajku,
środki gracza
```

musi działać w transakcji.

Przy przypisaniu pracownika:

```text
SELECT pracownika FOR UPDATE,
SELECT aktywne przypisania FOR UPDATE,
sprawdzenie alokacji,
INSERT przypisania,
log zdarzenia,
COMMIT.
```

Przy podwyżce:

```text
SELECT żądania FOR UPDATE,
SELECT pracownika FOR UPDATE,
zmiana pensji,
aktualizacja employee_state,
zamknięcie żądania,
log zdarzenia,
COMMIT.
```

Nie wolno:

```text
przypisać zwolnionego pracownika,
przekroczyć 100% alokacji,
rozstrzygnąć tego samego żądania dwa razy,
uruchomić dwóch aktywnych strajków tego samego działu,
naliczyć tej samej zmiany morale dwa razy w jednym cyklu.
```

---

# 26. Migracja istniejących danych

Dodaj komendę lub metodę administracyjną:

```text
backfillEmployeeState()
```

Ma utworzyć stan dla:

```text
aktywnych board_members,
aktywnych technical_staff.
```

Wartości początkowe:

```text
morale = 65,
salary_satisfaction wyliczone z pensji,
expected_salary wyliczone z widełek,
leave_risk = 0,
strike_support = 0,
relation_status = normal.
```

Dodaj raport:

```text
liczba utworzonych stanów,
liczba pominiętych rekordów,
liczba błędów,
podejrzane duplikaty.
```

Nie uruchamiaj destrukcyjnego czyszczenia bez podglądu admina.

---

# 27. Testy

## 27.1 Rekrutacja

```text
1. Kandydat bez specjalizacji zostaje dyrektorem.
2. Nie można zatrudnić drugiego dyrektora tego samego działu.
3. Kandydat logistyczny zostaje zwykłym pracownikiem board_members.
4. Dyrektor logistyki nie blokuje zatrudnienia pracownika logistyki.
5. Kandydat techniczny trafia do technical_staff.
6. Headhunter nie tworzy podwójnego pracownika.
7. Każdy zatrudniony pracownik dostaje employee_state.
```

## 27.2 Przypisania

```text
1. Operator huba może zostać przypisany do huba.
2. Pracownik innego gracza nie może zostać przypisany.
3. Zwolniony pracownik nie może zostać przypisany.
4. Alokacja nie może przekroczyć 100%.
5. Oddelegowanie zamyka aktywne przypisanie.
6. Zwolnienie pracownika automatycznie kończy przypisania.
```

## 27.3 Logistyka

```text
1. Pełna obsada nie daje kary.
2. Niepełna obsada obniża przepustowość.
3. Brak obsady zwiększa ryzyko incydentu.
4. Skille operatora zwiększają efekt.
5. Niskie morale osłabia efekt.
6. Kierownik logistyki daje bonus działowy.
7. Strajkujący pracownik nie jest liczony do aktywnej obsady.
```

## 27.4 Podwyżki

```text
1. Pracownik spełniający warunki tworzy żądanie.
2. Nie powstaje drugie aktywne żądanie.
3. Pełna podwyżka zmienia pensję i morale.
4. Negocjacja zapisuje wynik i użyte skille.
5. Odmowa obniża morale.
6. Odłożenie ustawia nowy termin.
7. Tego samego żądania nie można rozstrzygnąć dwa razy.
```

## 27.5 Strajki

```text
1. Niskie morale samo nie uruchamia natychmiast strajku.
2. Powstaje etap groźby strajku.
3. Nierozwiązany konflikt może przejść w strajk.
4. Dział może mieć tylko jeden aktywny strajk.
5. Strajk logistyki wpływa na huby i transport.
6. Rozwiązanie strajku usuwa aktywne kary.
7. Historia strajku pozostaje w bazie.
```

## 27.6 Tick

```text
1. EmployeesModule działa według interval_ticks.
2. EmployeesModule respektuje max_items_per_run.
3. Błąd jednego pracownika nie zatrzymuje całego modułu.
4. Błąd modułu nie zatrzymuje pozostałych modułów ticka.
5. Statystyki modułu są zapisane w tick_module_run_logs.
6. Nowy moduł dostaje rekomendowany interwał z TickModuleCatalog.
```

---

# 28. Kolejność wdrożenia

## Etap 1 — fundament wspólnego pracownika

**Status: [x] Wdrożony i zweryfikowany 2026-07-15.**

```text
[x] EmployeeRef
[x] EmployeeRepository
[x] EmployeeSystemBootstrap
[x] employee_state
[x] employee_source_links
[x] backfill employee_state (dry-run + jawny tryb apply)
[x] testy Unit, Integration i MySqlIntegration
```

### Doprecyzowania etapu 1 po audycie kodu

1. `technical_staff` jest źródłem kanonicznym dla starych techników utworzonych podwójnie przez wcześniejsze flow HR i `HeadhunterService`.
2. Rozpoznanie starego lustrzanego rekordu wymaga jednocześnie:
   - `technical_staff.manager_id = board_members.id`,
   - tego samego `player_id`, imienia i nazwiska,
   - zgodności `hr_specializations.code` z `technical_staff.spec_code`.
   Historyczne lustro może mieć `board_members.member_type = 'director'` albo `staff`; sam typ nie rozstrzyga tożsamości.
3. Po rozpoznaniu para identyfikatorów jest zapisywana w `employee_source_links`. Dalsze działanie systemu używa wyłącznie tego trwałego powiązania ID, a nie ponownej heurystyki.
4. Backfill domyślnie działa jako dry-run. W tym trybie nie tworzy stanów ani powiązań źródeł.
5. Tryb `apply` tworzy brakujące powiązania i dokładnie jeden `employee_state` dla pracownika kanonicznego. Nie usuwa ani nie scala rekordów źródłowych.
6. Widełki oczekiwanej pensji technika są pobierane przez `technical_staff.spec_code -> hr_specializations.code`. Brak widełek oznacza zachowanie bieżącej pensji jako neutralnej wartości początkowej.
7. `department_code` w `employee_state` ma co najmniej 50 znaków, zgodnie z `board_roles.code`.
8. Pusty `EmployeesModule` nie powstaje w etapie 1. Moduł jest dodawany dopiero w etapie 9 razem z realnym przeliczaniem morale i limitowaniem rekordów.
9. Kontrolowany backfill uruchamiaj przez `tools/backfill_employee_state.php`. Domyślnie wykonuje dry-run; zapis wymaga flagi `--apply`, a `--player=ID` ogranicza zakres do jednego gracza.
10. `EmployeeSystemBootstrap` pozostaje podpięty do `src/init.php`, zgodnie z obecnym standardem projektu. Przyszłe utwardzenie infrastruktury może przenieść wszystkie bootstrapy do jednego wersjonowanego runnera migracji, aby ograniczyć zapytania DDL i ryzyko metadata locków; nie należy robić tej przebudowy wyłącznie dla modułu pracowników.

## Etap 2 — naprawa rekrutacji

**Status: [x] Wdrożony i zweryfikowany 2026-07-15.**

```text
poprawienie HRHiringTrait,
pracownicy wszystkich działów,
zajęta rola tylko dla dyrektora,
poprawienie HeadhunterService,
testy rekrutacji.
```

### Doprecyzowania etapu 2 po wdrożeniu

1. `HRHiringTrait` rozdziela teraz trzy ścieżki:
   - brak specjalizacji -> `board_members.member_type = 'director'`,
   - specjalizacja nietechniczna -> `board_members.member_type = 'staff'`,
   - specjalizacja techniczna -> wyłącznie `technical_staff`.
2. Zajęta rola jest sprawdzana tylko dla ścieżki dyrektorskiej. Istniejący dyrektor działu nie blokuje zatrudnienia zwykłego pracownika tego działu.
3. `HeadhunterService` nie hardkoduje już roli `technical` dla wszystkich specjalizacji. Dla pracowników działowych używa mapowania `hr_specializations.department -> board_roles.code`.
4. Techniczny headhunter zapisuje tylko rekord `technical_staff` powiązany z aktywnym dyrektorem technicznym. Nie tworzy już lustrzanego `board_members`.
5. Headhunter dla działów nietechnicznych zapisuje tylko `board_members.member_type = 'staff'` i zakłada standardowy `employee_contracts`.
6. Dodano regresję MySQL obejmującą pięć scenariuszy: pracownik działowy przy zajętym dyrektorze, blokada duplikatu dyrektora, technik z HR, technik z headhuntera, pracownik działowy z headhuntera.

## Etap 3 — role i efekty

**Status: [x] Wdrożony i zweryfikowany 2026-07-15; model hub_operator ujednolicono 2026-07-18.**

```text
employee_role_effects,
EmployeeRoleEffectService,
role logistyczne,
panel admina ról i efektów.
```

## Etap 4 — przypisania

**Status: [x] Wdrożony i utwardzony 2026-07-19.**

```text
employee_assignments,
EmployeeAssignmentService,
integracja ze zwalnianiem i szkoleniami.
```

## Etap 5 — obsada hubów

**Status: [x] Wdrożony i zweryfikowany 2026-07-19.**

```text
LogisticsStaffingService,
wymagana obsada,
efekt skilli i morale,
kierownik logistyki,
podpięcie pod huby i incydenty,
zakładka Obsada.
```

## Etap 5A — obsada rurociągów

**Status: [x] Wdrożony, poddany code review i zweryfikowany 2026-07-19.**

- [x] dwie role na każdy odcinek: pipeline_engineer i pipeline_logistics_specialist
- [x] osobna obsada rekordów inbound i outbound
- [x] wspólny limit alokacji hub + rurociąg
- [x] lokalne efekty degradacji, ryzyka i strat w ticku
- [x] UI logistyki: pokrycie, modal, przypisanie, aktualizacja i zwolnienie
- [x] CSRF, PRG, jednorazowy flash i confirmAction
- [x] izolacja player_id, blokady MySQL i testy wyścigów

## Etap 6 — morale i oczekiwania płacowe

**Status: [~] Częściowo wdrożony. EmployeeStateService i wpływ morale na efekty działają; brakuje relacji cyklicznych, zdarzeń morale oraz pełnego UI gracza i admina.**

```text
EmployeeStateService,
EmployeeRelationsService,
eventy morale,
panel gracza i admina.
```

## Etap 7 — podwyżki

**Status: [x] Wdrożony i zweryfikowany 2026-07-26.**

- `employee_raise_requests` przechowuje migrowalne snapshoty pensji, przyczynę, liczbę odroczeń, termin i wynik.
- Tick tworzy jedno aktywne żądanie tylko dla aktywnego pracownika i automatycznie eskaluje przeterminowane żądanie do sporu.
- Gracz może przyznać pełną podwyżkę, złożyć mniejszą ofertę, odmówić albo odłożyć decyzję.
- Akcje używają CSRF, `player_id`, transakcji, blokad MySQL oraz tokenów idempotencji; wynik losowania i formuła nie są ujawniane przez API.
- Efekty morale, lojalności, ryzyka odejścia, poparcia strajku, terminów i negocjatora płacowego są typowane i edytowalne w panelu admina HR.
- Powiadomienia oraz UI gracza i admina mają wersje PL/EN.
- Weryfikacja: targeted SQLite/MySQL, Unit+Integration 600/600, MySqlIntegration 233/233, PHPStan 0 błędów, lint, encoding i `git diff --check`.
## Etap 8 — strajki

**Status: [ ] Nie wdrożony. Istnieją statusy relacji używane jako blokady, ale nie ma procesu konfliktu, tabel strajków ani efektów strajku.**

```text
employee_strikes,
employee_strike_members,
groźby strajku,
strajki działowe,
efekty logistyki i innych działów.
```

## Etap 9 — tick

**Status: [ ] Nie wdrożony. Nie istnieje EmployeesModule; cykliczne morale, limity i statystyki pracowników pozostają do wykonania.**

```text
EmployeesModule,
TickModuleCatalog,
limity rekordów,
statystyki,
testy integracyjne.
```

## Etap 10 — refaktoryzacja UI logistyki

**Status: [~] Częściowo wdrożony. Widok i JavaScript logistyki są podzielone, ale public/logistics.php nadal wymaga wydzielenia kontrolera oraz traitów danych i akcji.**

```text
LogisticsPageController,
traity danych i akcji,
podział templates/views/logistics/main.php,
usunięcie zależności od main.bak.php po weryfikacji.
```

---

# 29. Kryteria akceptacji

Wdrożenie jest kompletne, gdy:

```text
1. Można zatrudnić zwykłego pracownika logistyki mimo istniejącego dyrektora logistyki.
2. Pracownik ma wspólny stan morale niezależnie od tabeli źródłowej.
3. Operator huba może zostać przypisany do konkretnego huba.
4. Hub pokazuje wymaganą i obecną obsadę.
5. Brak obsady realnie wpływa na przepustowość i ryzyko incydentu.
6. Skille i morale realnie zmieniają efekt pracownika.
7. Kierownik logistyki wpływa na cały dział.
8. Pracownik może zażądać podwyżki.
9. Gracz może przyjąć, negocjować, odrzucić albo odłożyć żądanie.
10. Nierozwiązane konflikty mogą doprowadzić do strajku.
11. Strajk logistyki realnie wpływa na logistykę.
12. Wszystkie ustawienia są dostępne w prostym panelu admina po polsku.
13. System działa przez istniejący modularny tick.
14. max_items_per_run jest faktycznie respektowane.
15. Nie powstaje trzecia, niezależna tabela pracowników logistycznych.
16. Wszystkie operacje finansowe są zapisane przez FinancialTransactionService.
17. Wszystkie ważne akcje mają log.
18. Istnieją testy transakcyjne i testy współbieżności.
```

---

# 30. Najkrótsze polecenie dla AI

```text
Przebuduj obecny system HR w sposób przyrostowy, bez tworzenia nowego, trzeciego systemu pracowników.

Wykorzystaj istniejące:
board_members
technical_staff
hr_specializations
staff_specializations
employee_contracts
HRService
TechnicalTeamService
modularny TickEngine

Najpierw napraw zatrudnianie:
- kandydat bez specjalizacji jest dyrektorem,
- kandydat ze specjalizacją jest zwykłym pracownikiem działu,
- specjalista techniczny trafia do technical_staff,
- specjalista logistyki, finansów, HR lub prawny trafia do board_members jako member_type=staff,
- zajęty fotel dyrektora nie blokuje zatrudniania pracowników,
- HeadhunterService nie może tworzyć podwójnych rekordów tej samej osoby.

Dodaj wspólną warstwę:
EmployeeRef
EmployeeRepository
EmployeeStateService
EmployeeAssignmentService
EmployeeRoleEffectService
EmployeeRelationsService
LogisticsStaffingService

Dodaj tabele:
employee_state
employee_assignments
employee_role_effects
employee_raise_requests
employee_strikes
employee_strike_members
employee_events
employee_system_config

Dodaj role logistyki:
Operator huba
Dyspozytor transportu
Koordynator magazynu
Specjalista logistyki rurociągów
Koordynator dostaw B2B
Operator terminala
Analityk przepływu ropy

Dodaj przypisywanie pracowników do:
hubów
rurociągów
magazynów
transportu
B2B
portów

Najpierw wdroż pełną obsadę hubów.
Brak obsady nie blokuje huba, ale zmniejsza przepustowość i zwiększa ryzyko incydentu.
Skille i morale mają wzmacniać albo osłabiać efekt pracownika.
Dyrektor logistyki ma dawać bonus całemu działowi.

Dodaj morale, oczekiwaną pensję, zadowolenie z pensji, ryzyko odejścia i poparcie dla strajku.

Dodaj żądania podwyżek:
pełna podwyżka
mniejsza podwyżka i negocjacja
odmowa
odłożenie decyzji
zwolnienie

Dodaj narastający konflikt:
niezadowolenie
żądanie podwyżki
spór
groźba strajku
strajk

Strajk logistyki ma realnie wpływać na:
przepustowość hubów
koszt transportu
ryzyko opóźnień
czas reakcji na awarie

Dodaj EmployeesModule do istniejącego modularnego ticka:
key = employees
order = 35
recommended interval = 2
recommended max items = 200

Nie przebudowuj ponownie tick.php.
Napraw TickModuleConfigRepository::syncModules(), aby używał recommendedInterval i recommendedLimit.

Rozbuduj panel admina HR o:
Pulpit
Pracownicy
Role i specjalizacje
Efekty ról
Przypisania
Morale
Podwyżki
Strajki
Ustawienia
Logi

Wszystkie nazwy w panelu mają być po polsku i prosto opisane.

Przed dodaniem rozbudowanego UI obsady wydziel public/logistics.php i templates/views/logistics/main.php do kontrolera, traitów i osobnych zakładek.

Wdrażaj etapami.
Po każdym etapie uruchom testy PHP, testy bazy i testy współbieżności.
Nie zmieniaj kilku niezależnych mechanik w jednym commitcie.
```
## 31. Status wdrożenia - Etap 3

**Status: [x] Wdrożony i zweryfikowany 2026-07-15.**

1. `EmployeeSystemBootstrap` tworzy tabelę `employee_role_effects` idempotentnie dla MySQL i SQLite.
2. Bootstrap seeduje brakujące specjalizacje logistyczne z wyjątkiem hub_operator: transport_dispatcher, warehouse_coordinator, pipeline_logistics_specialist, b2b_delivery_coordinator, terminal_operator, oil_flow_analyst. hub_operator jest stanowiskiem rekrutacyjnym działu technicznego, zarządzanym w hr_specializations; migracja produkcyjna znajduje się w sql/manual/2026-07-18_hub_operator_technical_migration.sql.
3. Bootstrap seeduje bazowe efekty logistyczne dla scope: `hub`, `road_transport`, `warehouse`, `pipeline`, `b2b`, `port`, `department`.
4. Teksty seedów widoczne dla gracza nie są hardkodowane w PHP. Nazwy i opisy są pobierane z `lang/pl/hr.php`.
5. Dodano `EmployeeRoleEffectService` z metodami `getEffectsForSpecialization()`, `calculateEffects()`, `saveEffect()`, `deleteEffect()` oraz `getLogisticsManagerBonus()`.
6. `calculateEffects()` wykorzystuje wspólne `EmployeeRepository` i `EmployeeStateService`, liczy wpływ `skill_weights_json` oraz stosuje mnożnik morale zgodny z briefem.
7. Pracownik techniczny bez osobnej specjalizacji perkowej używa fallbacku `role_code`, więc efekty mogą działać już na `technical_staff.spec_code`.
8. Dodano regresje SQLite i MySQL dla seedów, liczenia morale/skilli, fallbacku `role_code`, CRUD efektu i bonusu kierownika logistyki.

## 32. Aktualny stan wdrożenia i następny etap — 2026-07-20

### 32.1 Wdrożone w ostatnich etapach

1. Fundament wspólnego pracownika, rekrutacja działowa, employee_state, role i efekty są wdrożone.
2. hub_operator jest realnym stanowiskiem technicznym z hr_specializations, a nie perkiem z staff_specializations ani pracownikiem zarządu.
3. Obsada hubów korzysta z realnych technical_staff, lokalnych przypisań, skilli, morale i bonusu kierownika logistyki.
4. Panel admina logistyki ma diagnostykę obsady hubów, konfigurację wymagań i historię przypisań.
5. Każdy rekord well_pipelines inbound lub outbound ma niezależną obsadę pipeline_engineer i pipeline_logistics_specialist.
6. Efekty obsady rurociągu są lokalne: inżynier skaluje degradację i ryzyko, a specjalista logistyki zmniejsza straty tylko przypisanego odcinka.
7. Logistyka udostępnia zarządzanie obsadą rurociągów przez modal, CSRF, PRG, jednorazowy flash i potwierdzenie zwolnienia.
8. Warstwa przypisań ponownie sprawdza player_id, cel, typ i status w finalnym zapisie oraz ma testy blokad i wyścigów MySQL.

### 32.2 Co pozostało do wdrożenia

1. **Etap 6 — relacje i morale:** dodać EmployeeRelationsService, cykliczne zdarzenia morale, obciążenie pracą, ryzyko odejścia oraz widoki gracza i admina.
2. **Etap 7 — podwyżki: [x] wdrożony 2026-07-26.** Pełny proces, UI gracza, konfiguracja admina, powiadomienia i testy są zamknięte.
3. **Etap 8 — konflikty i strajki:** dodać employee_strikes, członków strajku, eskalację konfliktu oraz realne efekty działowe.
4. **Etap 9 — modularny tick pracowników:** dodać EmployeesModule, rekomendowany interwał, max_items_per_run, statystyki i odporność na błąd pojedynczego pracownika.
5. **Etap 10 — kontroler logistyki:** wydzielić logikę z dużego public/logistics.php do kontrolera oraz traitów danych i akcji.
6. **Czytelność admin HR:** nazwać sekcje tak, aby laik rozróżniał perki techniczne staff_specializations od stanowisk rekrutacyjnych hr_specializations.
7. **Weryfikacja produkcji:** uruchomić i sprawdzić migrację hub_operator, jeżeli nie została jeszcze wykonana na serwerze produkcyjnym.
8. **Kontrola wizualna:** wykonać udokumentowany test desktop/mobile modali obsady hubów i rurociągów, w tym kart nieoperacyjnych.

### 32.3 Rekomendowany kolejny etap

Kontynuować od **Etapu 6**. Najpierw wdrożyć sam EmployeeRelationsService i deterministyczne przeliczanie morale bez podwyżek i strajków. Dopiero po zielonych testach tego fundamentu przejść do Etapu 7, a następnie do eskalacji konfliktów w Etapie 8.

### 32.4 Ostatnia pełna weryfikacja

- targeted: 4/4,
- Unit+Integration: 571/571,
- MySqlIntegration: 227/227,
- encoding: 1956 plików,
- git diff --check: bez błędów.

---

## 33. Aktualny stan wdrożenia po Etapach 6-8, obsadzie logistyki i poprawkach - 2026-07-22

**Status: [~] Częściowo wdrożone. Kanoniczne morale, modularny tick, konflikty, backend negocjacji oraz podstawowy panel negocjacji gracza są wdrożone i sprawdzone pełnymi suite. Panel administracyjny HR i pełne efekty strajków w konsumentach działowych pozostają do wykonania.**

### 33.1 Wdrożone funkcjonalności i moduły

**Aktualizacja statusu 2026-07-22 po podpięciu negocjacji gracza:**

- System morale działa przez `employee_state`, `EmployeeSystemConfigService`, `MoraleServiceV2` i `EmployeeMoraleSection`; legacy `MoraleBootstrap` nie jest uruchamiany globalnie z `src/init.php`.
- Tabele nowego systemu, migrator legacy, idempotencja cyklu oraz zakres oczekiwanej pensji `40% salary_min - 90% salary_max` są wdrożone.
- `EmployeeStrikeService`, `EmployeeDialogueTemplateService`, `EmployeeNegotiationService` i `StrikeEffectService` obsługują eskalację, ponad 80 dwujęzycznych tekstów, token idempotencji oferty, utrwalony wynik losowania i atomową ugodę przez `FinancialTransactionService`.
- `public/hr.php` pokazuje konflikty działowe, poparcie, morale i uczestników. Gracz może rozpocząć negocjacje i składać oferty podwyżki oraz premii w rundach ograniczonych konfiguracją admina.
- Odpowiedź rundy pokazuje wybrany tekst dialogowy PL/EN. Formuła i wynik losowania pozostają danymi wewnętrznymi i nie są ujawniane przez API.
- Usunięto publiczną legacy akcję natychmiastowego zakończenia strajku za stałą kwotę. Premie techniczne używają `EmployeeBonusService`, `FinancialTransactionService` i kanonicznego morale z filtrem `player_id`.
- Nie wdrożono jeszcze kompletu zakładek admin HR, panelu edycji dialogów ani pełnego podpięcia `StrikeEffectService` do logistyki, techniki, HR i działu prawnego.

1. **Morale i relacje pracownicze (Etap 6):**
   - `EmployeesModule` i `EmployeeMoraleSection` przeliczają morale, oczekiwaną pensję, zadowolenie płacowe, workload, leave risk i strike support w modularnym ticku.
   - Cykl jest wznawialny i idempotentny, a błąd pojedynczego pracownika nie zatrzymuje całej partii.

2. **Podwyżki i żądania płacowe (Etap 7):**
   - Wdrożono tworzenie, akceptację, mniejszą ofertę, odmowę, odroczenie, wygaśnięcie oraz powiadomienia.
   - Decyzje są transakcyjne, izolowane przez `player_id` i idempotentne; negocjacje zapisują formułę oraz pojedynczy wynik losowania.
   - UI gracza oraz typowane ustawienia w panelu admina HR są podłączone. Etap 7 jest zamknięty.
3. **Konflikty i strajki działowe (Etap 8):**
   - Konflikty są grupowane według gracza i działu; jeden dział nie może mieć dwóch otwartych strajków.
   - Groźba zachowuje liczbę kwalifikujących sporów po zmianie relacji na `strike_threat`, dzięki czemu może poprawnie eskalować po wymaganych cyklach.
   - Negocjacje mają rundy, deadline, cooldown, kontrofertę i bezpieczne ponowne otwarcie po statusie `failed` lub `expired`.

4. **Obsada hubów i rurociągów:**
   - Huby i konkretne odcinki rurociągów inbound/outbound korzystają z lokalnych przypisań pracowników, alokacji, skilli i morale.
   - Odczyty oraz finalne zapisy zachowują izolację `player_id`; wynajmowane huby uwzględniają `tenant_player_id`.

### 33.2 Weryfikacja automatyczna i statyczna

- Targeted `EmployeeNegotiationServiceTest`: 10/10 testów, 340 asercji.
- Targeted MySQL morale, premii i eskalacji: 5/5 testów, 32 asercje.
- `Unit + Integration`: 588/588 testów, 6842 asercje.
- `MySqlIntegration`: 232/232 testy, 2564 asercje.
- Targeted PHPStan dla zmienionych serwisów i kontrolerów: bez błędów.
- Lint PHP/JS, `tools/check_encoding.php` i `git diff --check`: wymagane przed commitem.