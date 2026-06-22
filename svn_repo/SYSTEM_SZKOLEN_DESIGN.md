# System Szkoleń — Projekt Modularny

**Status:** PROJEKT (do wdrożenia)  
**Data:** 2026-06-22  
**Dotyczy:** pracownicy działu technicznego + członkowie zarządu

---

## 1. Cel i założenia

System szkoleń umożliwia rozwijanie umiejętności pracowników poprzez opłacone kursy kończące się **egzaminem**. Egzamin można oblać — ukończenie szkolenia nie gwarantuje sukcesu.

### Główne zasady gry

| Zasada | Opis |
|---|---|
| Koszt kursu | Płatny z góry, bezzwrotny nawet przy oblaniu |
| Czas trwania | Tick-based (godziny gry), pracownik zajęty w trakcie |
| Egzamin | Automatyczny rzut kosem przy zakończeniu |
| Oblanie | Cooldown 12h przed kolejną próbą |
| Zaliczenie | +1 do wybranego skilla + certyfikat |
| Retry bonus | +10% za każde poprzednie oblanie (max +30%) |
| Pułap skilla | Nie można szkolić skilla już na poziomie max (10) |

---

## 2. Nowe sub-skille dla działu technicznego

Obecnie `technical_staff` ma jeden `skill_level` (1-10). System szkoleń wprowadza **cztery specjalizacje** przechowywane w osobnej tabeli.

### 2.1 Tabela `technical_staff_skills`

```sql
CREATE TABLE technical_staff_skills (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id      INT UNSIGNED NOT NULL,
    skill_code    VARCHAR(40)  NOT NULL,
    skill_level   TINYINT      NOT NULL DEFAULT 1,
    updated_at    DATETIME     NOT NULL DEFAULT NOW(),
    UNIQUE KEY uq_staff_skill (staff_id, skill_code),
    CONSTRAINT fk_tss_staff FOREIGN KEY (staff_id)
        REFERENCES technical_staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.2 Kody i opis specjalizacji

| Kod | Nazwa PL | Efekt gameplay |
|---|---|---|
| `skill_drilling` | Wiercenie | Skraca czas operacji wiertniczych, redukuje awarie głowic |
| `skill_maintenance` | Utrzymanie ruchu | Zmniejsza wear rate platform, szybsze naprawy |
| `skill_safety` | BHP i bezpieczeństwo | Redukuje ryzyko incydentów, niższe kary |
| `skill_analysis` | Analiza i raporty | Lepsze raporty geologiczne, wyższy odkrycie % |

Istniejący `skill_level` zostaje jako **ogólny poziom kompetencji** (używany przy kalkulacjach czasu/kosztu). Sub-skille go nie zastępują — są dodatkiem.

### 2.3 Istniejące skille zarządu (board_members)

Zarząd już ma 5 kolumn: `skill_organization`, `skill_negotiation`, `skill_analysis`, `skill_stress`, `skill_ethics`. Szkolenia obejmą wszystkie 5.

| Skill | Obecny efekt | Efekt po aktywacji systemu szkoleń |
|---|---|---|
| `skill_organization` | Tak (zarządzanie) | Bez zmian |
| `skill_negotiation` | Brak | Rabat na kontraktach / niższe opłaty prawne |
| `skill_analysis` | Tak (raporty) | Bez zmian |
| `skill_stress` | Brak | Odporność na kary finansowe / mniejszy wpływ incydentów |
| `skill_ethics` | Brak | Bonus wiarygodności firmy przy oblaniach moralnych |

---

## 3. Baza danych — pełny schemat

### 3.1 Tabela `training_programs`

Konfiguracja programów szkoleniowych (seed data, nie edytowalne przez gracza).

```sql
CREATE TABLE training_programs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(60)  NOT NULL UNIQUE,
    department      ENUM('technical','board') NOT NULL,
    target_skill    VARCHAR(40)  NOT NULL,
    name_pl         VARCHAR(120) NOT NULL,
    name_en         VARCHAR(120) NOT NULL,
    duration_hours  SMALLINT     NOT NULL DEFAULT 24,
    cost            INT UNSIGNED NOT NULL DEFAULT 0,
    base_pass_rate  TINYINT      NOT NULL DEFAULT 70,
    enabled         TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Przykładowe rekordy seed:**

```sql
INSERT INTO training_programs
    (code,                        department,  target_skill,       name_pl,                      name_en,                       duration_hours, cost,   base_pass_rate)
VALUES
    ('tech_drilling_basic',       'technical', 'skill_drilling',   'Kurs wiercenia poziom I',    'Drilling Course Level I',     48,  15000, 85),
    ('tech_drilling_advanced',    'technical', 'skill_drilling',   'Kurs wiercenia poziom II',   'Drilling Course Level II',    72,  30000, 65),
    ('tech_maintenance_basic',    'technical', 'skill_maintenance','Utrzymanie ruchu poziom I',  'Maintenance Course Level I',  36,  12000, 80),
    ('tech_safety_basic',         'technical', 'skill_safety',     'Szkolenie BHP poziom I',     'Safety Training Level I',     24,  8000,  90),
    ('tech_analysis_basic',       'technical', 'skill_analysis',   'Analiza geologiczna poz. I', 'Geological Analysis Level I', 60,  20000, 70),
    ('board_negotiation_basic',   'board',     'skill_negotiation','Negocjacje poziom I',        'Negotiation Course Level I',  48,  25000, 80),
    ('board_ethics_basic',        'board',     'skill_ethics',     'Etyka biznesu poziom I',     'Business Ethics Level I',     36,  18000, 85),
    ('board_stress_basic',        'board',     'skill_stress',     'Zarządzanie stresem poz. I', 'Stress Management Level I',   24,  15000, 88);
```

### 3.2 Tabela `staff_trainings`

Historia i aktywne szkolenia.

```sql
CREATE TABLE staff_trainings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id       INT UNSIGNED NOT NULL,
    staff_type      ENUM('technical','board') NOT NULL,
    staff_id        INT UNSIGNED NOT NULL,
    program_id      INT UNSIGNED NOT NULL,
    status          ENUM('in_progress','passed','failed','cancelled') NOT NULL DEFAULT 'in_progress',
    started_at      DATETIME     NOT NULL DEFAULT NOW(),
    finishes_at     DATETIME     NOT NULL,
    exam_score      TINYINT      NULL,
    exam_pass_min   TINYINT      NULL,
    retry_count     TINYINT      NOT NULL DEFAULT 0,
    cooldown_until  DATETIME     NULL,
    skill_before    TINYINT      NULL,
    skill_after     TINYINT      NULL,
    cost_paid       INT UNSIGNED NOT NULL DEFAULT 0,
    INDEX idx_st_player   (player_id),
    INDEX idx_st_staff    (staff_type, staff_id),
    INDEX idx_st_status   (status),
    INDEX idx_st_finishes (finishes_at),
    CONSTRAINT fk_st_program FOREIGN KEY (program_id)
        REFERENCES training_programs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.3 Rozszerzenie `employee_certificates`

Tabela istnieje ale jest pusta. Wykorzystamy ją do przechowywania certyfikatów po zdanych egzaminach.

```sql
-- Dodatkowe kolumny (ALTER TABLE)
ALTER TABLE employee_certificates
    ADD COLUMN staff_type  ENUM('technical','board') NULL AFTER member_id,
    ADD COLUMN training_id INT UNSIGNED NULL AFTER staff_type,
    ADD COLUMN score       TINYINT NULL AFTER training_id;
```

> **Uwaga:** Kolumna `member_id` będzie używana dla obu typów pracowników (technical_staff i board_members). `staff_type` rozróżnia.

---

## 4. Architektura PHP — wzorzec modularny

Wzorowany na `PrivacyFeatureRegistry`. Każdy program szkoleniowy to osobna klasa implementująca interfejs.

### 4.1 Struktura katalogów

```
src/
└── Training/
    ├── TrainingProgramInterface.php        # kontrakt dla programu
    ├── AbstractTrainingProgram.php         # wspólna logika (pass rate, score)
    ├── TrainingProgramRegistry.php         # rejestracja wszystkich programów
    ├── TrainingService.php                 # główna logika (start, tick, egzamin)
    ├── TrainingTickProcessor.php           # wywołanie z tick engine
    └── Programs/
        ├── Technical/
        │   ├── DrillingBasicProgram.php
        │   ├── DrillingAdvancedProgram.php
        │   ├── MaintenanceBasicProgram.php
        │   ├── SafetyBasicProgram.php
        │   └── AnalysisBasicProgram.php
        └── Board/
            ├── NegotiationBasicProgram.php
            ├── EthicsBasicProgram.php
            └── StressBasicProgram.php
```

### 4.2 `TrainingProgramInterface.php`

```php
<?php
declare(strict_types=1);

interface TrainingProgramInterface
{
    public function getCode(): string;
    public function getDepartment(): string;    // 'technical' | 'board'
    public function getTargetSkill(): string;   // np. 'skill_drilling'
    public function getNamePl(): string;
    public function getNameEn(): string;
    public function getDurationHours(): int;
    public function getCost(): int;
    public function getBasePassRate(): int;     // 0-100 (%)

    /**
     * Oblicza szanse zdania egzaminu dla konkretnego pracownika.
     * @param array<string,mixed> $staffData  rzad z bazy (skill_level, trait_ambition itd.)
     * @param int $retryCount  liczba poprzednich oblan tego kursu
     * @return int 0-100
     */
    public function computePassChance(array $staffData, int $retryCount): int;

    /**
     * Wywolywane po zdaniu egzaminu (w transakcji).
     * @param PDO   $db
     * @param int   $playerId
     * @param int   $staffId
     * @param array<string,mixed> $staffData
     * @param int   $trainingId
     */
    public function onPass(PDO $db, int $playerId, int $staffId, array $staffData, int $trainingId): void;

    /**
     * Wywolywane po oblaniu egzaminu (w transakcji).
     */
    public function onFail(PDO $db, int $playerId, int $staffId, array $staffData, int $trainingId): void;
}
```

### 4.3 `AbstractTrainingProgram.php`

```php
<?php
declare(strict_types=1);

abstract class AbstractTrainingProgram implements TrainingProgramInterface
{
    // Wspolczynnik ambicji: dla trait_ambition 1-10
    // +2% per punkt powyzej 5, -2% per punkt ponizej 5
    protected function ambitionModifier(array $staffData): int
    {
        $ambition = (int)($staffData['trait_ambition'] ?? 5);
        return ($ambition - 5) * 2;
    }

    // Retry bonus: +10% za kazde poprzednie oblanie, max +30%
    protected function retryModifier(int $retryCount): int
    {
        return min($retryCount * 10, 30);
    }

    public function computePassChance(array $staffData, int $retryCount): int
    {
        $base = $this->getBasePassRate();
        $chance = $base + $this->ambitionModifier($staffData) + $this->retryModifier($retryCount);
        return max(5, min(95, $chance));  // min 5% max 95% - nigdy nie ma pewnosci
    }

    // Domyslne onFail — nadpisywalne
    public function onFail(PDO $db, int $playerId, int $staffId, array $staffData, int $trainingId): void
    {
        // brak efektu ubocznego — nadpisz w klasie jesli potrzeba
    }
}
```

### 4.4 `TrainingProgramRegistry.php`

```php
<?php
declare(strict_types=1);

class TrainingProgramRegistry
{
    /** @var TrainingProgramInterface[] */
    private static array $programs = [];

    public static function build(): void
    {
        self::$programs = [];
        // Technical
        self::register(new DrillingBasicProgram());
        self::register(new DrillingAdvancedProgram());
        self::register(new MaintenanceBasicProgram());
        self::register(new SafetyBasicProgram());
        self::register(new AnalysisBasicProgram());
        // Board
        self::register(new NegotiationBasicProgram());
        self::register(new EthicsBasicProgram());
        self::register(new StressBasicProgram());
    }

    public static function register(TrainingProgramInterface $p): void
    {
        self::$programs[$p->getCode()] = $p;
    }

    public static function get(string $code): ?TrainingProgramInterface
    {
        return self::$programs[$code] ?? null;
    }

    /** @return TrainingProgramInterface[] */
    public static function all(): array
    {
        return self::$programs;
    }

    /** @return TrainingProgramInterface[] */
    public static function forDepartment(string $dept): array
    {
        return array_filter(self::$programs, fn($p) => $p->getDepartment() === $dept);
    }
}
```

### 4.5 Przykładowa klasa programu: `DrillingBasicProgram.php`

```php
<?php
declare(strict_types=1);

class DrillingBasicProgram extends AbstractTrainingProgram
{
    public function getCode(): string         { return 'tech_drilling_basic'; }
    public function getDepartment(): string   { return 'technical'; }
    public function getTargetSkill(): string  { return 'skill_drilling'; }
    public function getNamePl(): string       { return 'Kurs wiercenia poziom I'; }
    public function getNameEn(): string       { return 'Drilling Course Level I'; }
    public function getDurationHours(): int   { return 48; }
    public function getCost(): int            { return 15000; }
    public function getBasePassRate(): int    { return 85; }

    // Kurs wiercenia: im wyzszy ogolny skill_level tym latwie zdac (juz ma fundament)
    public function computePassChance(array $staffData, int $retryCount): int
    {
        $base  = $this->getBasePassRate();
        $bonus = (int)($staffData['skill_level'] ?? 1) * 2; // +2% per poziom
        $chance = $base + $bonus + $this->ambitionModifier($staffData) + $this->retryModifier($retryCount);
        return max(5, min(95, $chance));
    }

    public function onPass(PDO $db, int $playerId, int $staffId, array $staffData, int $trainingId): void
    {
        // Zwieksz skill_drilling o 1 (lub stworz rekord jesli nie istnieje)
        $db->prepare("
            INSERT INTO technical_staff_skills (staff_id, skill_code, skill_level, updated_at)
            VALUES (?, 'skill_drilling', 2, NOW())
            ON DUPLICATE KEY UPDATE
                skill_level = LEAST(skill_level + 1, 10),
                updated_at  = NOW()
        ")->execute([$staffId]);

        // Zapisz certyfikat
        $db->prepare("
            INSERT INTO employee_certificates
                (member_id, staff_type, training_id, code, name, issued_at)
            VALUES (?, 'technical', ?, 'tech_drilling_basic', 'Kurs wiercenia poz. I', NOW())
        ")->execute([$staffId, $trainingId]);
    }
}
```

---

## 5. Mechanika egzaminu — szczegóły

### 5.1 Wzór na szanse zdania

```
passChance = basePassRate
           + ambitionModifier       // (trait_ambition - 5) * 2%
           + retryModifier          // min(retryCount * 10, 30)%
           + classModifier          // opcjonalnie w podklasie
           
Klamry: min 5%, max 95%
```

### 5.2 Wynik egzaminu (score)

```
score    = rand(1, 100)
passMin  = 100 - passChance        // np. 35% szansy → próg = 65
result   = score >= passMin ? 'passed' : 'failed'
```

Wyświetlany w UI: **"Wynik egzaminu: 43/100 (wymagane minimum: 35/100)"**

### 5.3 Tabela przykładowych szans

| Kurs | Baza | skill_level=1 | skill_level=5 | skill_level=9 |
|---|---|---|---|---|
| BHP poziom I | 90% | 92% | 100%→95% | 95% |
| Wiercenie poziom I | 85% | 87% | 95% | 95% |
| Wiercenie poziom II | 65% | 67% | 75% | 83% |
| Analiza poz. I | 70% | 72% | 80% | 88% |

*(po dodaniu ambition i retry modyfikatora)*

---

## 6. `TrainingService.php` — główna logika

### 6.1 Metody publiczne

```php
class TrainingService
{
    // Sprawdza czy mozna zapisac na szkolenie + pobiera oplate + tworzy rekord
    public function startTraining(int $playerId, string $staffType, int $staffId, string $programCode): array
    // Zwraca ['success'=>bool, 'message'=>string, 'training_id'=>int|null]

    // Wywolywane przez tick: sprawdza finishes_at < NOW(), uruchamia egzamin
    public function processFinished(int $playerId): void

    // Anuluje szkolenie (tylko in_progress, bez zwrotu kosztu)
    public function cancelTraining(int $playerId, int $trainingId): array

    // Zwraca aktywne szkolenia gracza (in_progress)
    public function getActiveTrainings(int $playerId): array

    // Historia szkolen (paged)
    public function getHistory(int $playerId, int $page = 1, int $perPage = 20): array
}
```

### 6.2 Logika `startTraining()`

```
1. Pobierz program z registry (getCode)
2. Sprawdz czy program enabled w DB
3. Pobierz dane pracownika (staff_id, staff_type)
4. Sprawdz czy pracownik jest zatrudniony przez gracza
5. Sprawdz czy pracownik nie ma juz active training
6. Sprawdz cooldown_until (jesli ostatni egzamin oblanym)
7. Sprawdz skill < 10 (max osiagniety?)
8. Sprawdz saldo gracza >= koszt kursu
9. BEGIN TRANSACTION
   a. Pobierz oplata z bank_balance
   b. INSERT INTO staff_trainings
   c. COMMIT
10. Wyslij notyfikacje
```

### 6.3 Logika egzaminu w `processFinished()`

```
1. SELECT * FROM staff_trainings WHERE status='in_progress' AND finishes_at <= NOW()
2. Dla kazdego rekordu:
   a. Pobierz dane pracownika
   b. Pobierz program z registry
   c. Oblicz passChance = program->computePassChance(staffData, retry_count)
   d. passMin = 100 - passChance
   e. score = rand(1, 100)
   f. passed = (score >= passMin)
   g. BEGIN TRANSACTION
      - UPDATE staff_trainings SET status, exam_score, exam_pass_min, ...
      - if passed: program->onPass(...) + wyslij notif sukces
      - if failed: UPDATE cooldown_until = NOW() + 12h
                   UPDATE retry_count++
                   program->onFail(...)
                   wyslij notif porazki
      h. COMMIT
```

---

## 7. `TrainingTickProcessor.php`

```php
<?php
declare(strict_types=1);

class TrainingTickProcessor
{
    public function __construct(private PDO $db) {}

    public function run(): void
    {
        // Pobierz unikalne player_id z aktywnych szkolen
        $stmt = $this->db->prepare(
            "SELECT DISTINCT player_id FROM staff_trainings
              WHERE status = 'in_progress' AND finishes_at <= NOW()"
        );
        $stmt->execute();
        $playerIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $service = new TrainingService($this->db);
        foreach ($playerIds as $pid) {
            try {
                $service->processFinished((int)$pid);
            } catch (Throwable $e) {
                GameLog::error('TrainingTickProcessor', 'processFinished failed', $e, ['player_id' => $pid]);
            }
        }
    }
}
```

Rejestracja w tick engine (plik `src/TickEngine.php` lub odpowiednik):

```php
(new TrainingTickProcessor($db))->run();
```

---

## 8. Widoki (templates)

### 8.1 Zakładka szkoleń w dziale technicznym

Plik: `templates/views/technical/tabs/training.php`

**Sekcje:**
1. **Dostępne programy** — karty z nazwą, czasem, kosztem, przyciskiem "Zapisz" (przy aktywnym szkoleniu — wyłączony z tooltipem)
2. **Aktywne szkolenie** — pasek postępu z czasem do zakończenia
3. **Historia** — tabela z wynikami egzaminów (score/passMin, status, data)
4. **Sub-skille pracownika** — mini-tabela z 4 specjalizacjami i ich poziomem

### 8.2 Zakładka szkoleń zarządu

Plik: `templates/views/board/tabs/training.php` (lub sekcja w istniejącym widoku)

**Sekcje:**
1. Lista członków zarządu
2. Dla każdego: 5 skillow + dostępne kursy per skill

### 8.3 Powiadomienie po egzaminie

```
[Typ: szkolenia]
Pracownik Jan Kowalski zdał egzamin!
Kurs wiercenia poziom I — wynik: 73/100 (min: 15/100)
Skill: wiercenie 3 → 4
[Przejdź do działu technicznego]
```

```
[Typ: szkolenia]
Pracownik Jan Kowalski oblał egzamin.
Kurs wiercenia poziom II — wynik: 28/100 (min: 35/100)
Możliwość ponownego podejścia za: 12 godzin
[Przejdź do działu technicznego]
```

---

## 9. Tłumaczenia — klucze

```php
// lang/pl/training.php i lang/en/training.php
'training.page_title'              => 'Szkolenia pracowników',
'training.tab_available'           => 'Dostępne kursy',
'training.tab_active'              => 'W trakcie',
'training.tab_history'             => 'Historia',
'training.btn_enroll'              => 'Zapisz na kurs',
'training.btn_cancel'              => 'Anuluj szkolenie',
'training.status.in_progress'      => 'W trakcie',
'training.status.passed'           => 'Zaliczony',
'training.status.failed'           => 'Oblany',
'training.status.cancelled'        => 'Anulowany',
'training.exam_score'              => 'Wynik egzaminu: :score/100 (wymagane: :min/100)',
'training.retry_bonus'             => 'Bonus za poprzednie oblanie: +:pct%',
'training.cooldown_until'          => 'Możliwość ponownego podejścia: :date',
'training.skill.skill_drilling'    => 'Wiercenie',
'training.skill.skill_maintenance' => 'Utrzymanie ruchu',
'training.skill.skill_safety'      => 'BHP i bezpieczeństwo',
'training.skill.skill_analysis'    => 'Analiza i raporty',
'training.err.already_training'    => 'Ten pracownik już uczestniczy w szkoleniu.',
'training.err.on_cooldown'         => 'Ten pracownik jest w cooldownie po oblaniu egzaminu.',
'training.err.skill_maxed'         => 'Ten skill jest już na maksymalnym poziomie (10).',
'training.err.insufficient_funds'  => 'Niewystarczające środki na koncie bankowym.',
'training.notif.passed'            => ':name zdał egzamin! Wynik: :score/100. Skill :skill wzrósł do poziomu :level.',
'training.notif.failed'            => ':name oblał egzamin. Wynik: :score/100 (wymagane: :min/100). Cooldown: :hours godzin.',
```

---

## 10. Kolejność wdrożenia

### Faza 1 — Baza danych (migracje)

1. `CREATE TABLE training_programs`
2. `INSERT` seed data (programy)
3. `CREATE TABLE technical_staff_skills`
4. `CREATE TABLE staff_trainings`
5. `ALTER TABLE employee_certificates` (dodanie staff_type, training_id, score)

### Faza 2 — PHP backend

1. `TrainingProgramInterface.php`
2. `AbstractTrainingProgram.php`
3. Klasy programów (`Programs/Technical/`, `Programs/Board/`)
4. `TrainingProgramRegistry.php`
5. `TrainingService.php`
6. `TrainingTickProcessor.php`

### Faza 3 — Tłumaczenia

1. `lang/pl/training.php`
2. `lang/en/training.php`

### Faza 4 — Widoki

1. Zakładka szkoleń w dziale technicznym
2. Zakładka szkoleń zarządu
3. CSS (karta kursu, pasek postępu, tabela historii)

### Faza 5 — Integracja tick engine

1. Rejestracja `TrainingTickProcessor` w tick engine
2. Testy integracyjne

### Faza 6 — Efekty gameplay (opcjonalne, drugi sprint)

1. Podpięcie sub-skillow pod obliczenia (czas operacji, wear rate, etc.)
2. Aktywacja efektów `skill_negotiation`, `skill_ethics`, `skill_stress` dla zarządu

---

## 11. Pytania otwarte (do decyzji przed wdrożeniem)

| # | Pytanie | Opcje |
|---|---|---|
| 1 | Ile programów na start? | Min. 1 per skill, można dodawać więcej |
| 2 | Czy anulowanie szkolenia jest możliwe? | Tak (bez zwrotu) / Nie |
| 3 | Czy pracownik może mieć 2 szkolenia jednocześnie? | Zalecane: nie (1 na raz) |
| 4 | Skąd pobierana opłata? | `bank_balance` (jak permity) |
| 5 | Czy efekty sub-skillow są widoczne w UI? | Tooltip z opisem efektu |
| 6 | Trait ambition — czy istnieje w DB? | Sprawdzić przed wdrożeniem fazy 2 |
