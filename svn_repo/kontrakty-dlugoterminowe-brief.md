# Długoterminowe kontrakty na dostawy ropy — modułowy brief wdrożeniowy

## Cel

Celem jest stworzenie modułu długoterminowych kontraktów na dostawy ropy.

Moduł ma być zrobiony w stylu systemu sabotażu, czyli jako osobny, konfigurowalny silnik gry. Nie powinien być dopisany jako zwykła funkcja rynku ani jako rozszerzenie `market_offers`.

Kontrakty mają działać według zasady:

```text
gracz podpisuje kontrakt
tick sprawdza termin dostawy
moduł pobiera ropę z magazynu
moduł księguje przychód
moduł nalicza karę za braki
moduł zapisuje historię i logi
```

Na start MVP ma obsługiwać tylko kontrakty magazynowe, czyli kontrakty rozliczane na podstawie ropy dostępnej w `storage`.

Docelowo ten sam silnik ma pozwolić dodać:

```text
kontrakty regionalne
kontrakty pod konkretny hub
kontrakty pod port
kontrakty offshore
kontrakty między graczami
aukcje kontraktów
kontrakty zależne od reputacji firmy
kontrakty zależne od działu prawnego
```

---

## Najważniejsza decyzja architektoniczna

Kontrakty nie powinny być podpięte pod `MarketOffer`.

`MarketOffer` to oferta limitowana. Gracz od razu zdejmuje ropę z magazynu i czeka, aż cena osiągnie określony poziom.

Kontrakt długoterminowy to zobowiązanie. Gracz podpisuje umowę, a ropa jest pobierana dopiero przy każdej zaplanowanej dostawie.

Dlatego kontrakty mają być osobnym modułem.

---

## Zgodność z modularizacją tick engine

Po uwzględnieniu briefu dotyczącego modularizacji tick engine część kontraktów odpowiedzialna za działanie w ticku nie powinna docelowo być zwykłym `ContractSection` dopisywanym ręcznie do `cron/tick.php`.

Docelowy plik tickowy:

```text
src/Tick/Modules/ContractsModule.php
```

Moduł powinien implementować:

```php
TickModule
```

i mieć kolejność:

```php
order() = 35
```

Dlaczego `35`?

Zakładana kolejność modułów ticka:

```text
10 market
20 bank
25 marine_purge
30 players
35 contracts
40 black_market
50 credibility
60 legal
```

Kontrakty muszą działać po `players`, ponieważ najpierw produkcja, transport i logistyka powinny dopisać ropę do magazynu. Dopiero później kontrakty mogą sprawdzić, czy gracz ma wystarczającą ilość ropy na zaplanowaną dostawę.

Kontrakty powinny działać przed `black_market`, ponieważ czarny rynek może później generować oferty na podstawie aktualnej sytuacji gracza i rynku.

---

## Dwa poziomy modułowości

Moduł kontraktów powinien być podzielony na dwa poziomy.

### 1. Moduł gry

Odpowiada za logikę kontraktów, dane, warunki, historię i panel gracza.

```text
ContractSchema
ContractService
contract_options
contract_terms
player_contracts
contract_deliveries
contract_logs
public/contracts.php
admin/contracts.php
```

### 2. Moduł ticka

Odpowiada tylko za cykliczne rozliczanie dostaw w ticku.

```text
ContractsModule implements TickModule
```

Najkrócej:

```text
Kontrakty robimy jak sabotaż, ale część tickową projektujemy od razu pod nowy system TickModule.
```

---

## Wzorzec jak w sabotażu

Moduł sabotażu ma układ:

```text
src/Sabotage/SabotageSchema.php
src/SabotageService.php
public/sabotage.php
admin/sabotage.php
templates/views/sabotage/main.php
templates/views/admin/sabotage/main.php
```

I tabele:

```text
sabotage_options
sabotage_effects
sabotage_attempts
sabotage_logs
```

Kontrakty powinny mieć analogiczny układ:

```text
src/Contracts/ContractSchema.php
src/ContractService.php
src/Tick/Modules/ContractsModule.php
public/contracts.php
admin/contracts.php
templates/views/contracts/main.php
templates/views/admin/contracts/main.php
```

I tabele:

```text
contract_options
contract_terms
player_contracts
contract_deliveries
contract_logs
```

Mapa odpowiedników:

```text
SabotageSchema      → ContractSchema
SabotageService     → ContractService
sabotage_options    → contract_options
sabotage_effects    → contract_terms
sabotage_attempts   → contract_deliveries
sabotage_logs       → contract_logs
public/sabotage.php → public/contracts.php
admin/sabotage.php  → admin/contracts.php
```

---

## Dlaczego `contract_terms`, a nie same kolumny

Chodzi o modułowość.

Gdyby wszystkie warunki kontraktu były twardymi kolumnami, każda nowa mechanika wymagałaby kolejnej migracji tabeli.

Zamiast tego robimy:

```text
contract_options = główna definicja kontraktu
contract_terms   = elastyczne warunki kontraktu
```

Przykładowe warunki:

```text
total_bbl = 30000
delivery_bbl = 10000
delivery_interval_minutes = 1440
duration_minutes = 4320
bonus_pct = 12
penalty_pct = 8
min_credibility = 35
```

Później można dodać bez przebudowy tabeli:

```text
requires_region_id = 3
requires_hub_level = 2
requires_port_id = 5
requires_pipeline = 1
requires_offshore = 1
credibility_delta_on_success = 2
credibility_delta_on_miss = -4
bonus_on_full_completion_pct = 3
```

To daje podobny mechanizm jak w sabotażu, gdzie opcja ma listę efektów.

---

## Targety i konteksty

Tak jak sabotaż ma `target_type` i `context`, kontrakty też powinny mieć ten sam mechanizm.

Na MVP używamy tylko magazynu.

Docelowe targety:

```php
public const TARGET_STORAGE = 'storage';
public const TARGET_REGION = 'region';
public const TARGET_HUB = 'hub';
public const TARGET_PORT = 'port';
public const TARGET_PLAYER_COMPANY = 'player_company';
```

Docelowe konteksty:

```php
public const CONTEXT_STORAGE_DELIVERY = 'storage_oil_delivery';
public const CONTEXT_REGION_DELIVERY = 'region_oil_delivery';
public const CONTEXT_HUB_DELIVERY = 'hub_oil_delivery';
public const CONTEXT_PORT_DELIVERY = 'port_oil_delivery';
public const CONTEXT_P2P_DELIVERY = 'player_oil_delivery';
```

Na pierwsze wdrożenie używać tylko:

```php
ContractService::TARGET_STORAGE
ContractService::CONTEXT_STORAGE_DELIVERY
```

Dzięki temu MVP jest proste, ale cały moduł od początku jest gotowy pod rozbudowę.

---

## Nowe pliki

Dodać:

```text
src/Contracts/ContractSchema.php
src/ContractService.php
src/Tick/Modules/ContractsModule.php
public/contracts.php
admin/contracts.php
templates/views/contracts/main.php
templates/views/admin/contracts/main.php
assets/css/contracts.css
assets/js/contracts.js
lang/pl/contracts.php
lang/en/contracts.php
lang/pl/admin/contracts.php
lang/en/admin/contracts.php
```

Opcjonalnie później:

```text
tests/Integration/ContractServiceTest.php
tests/MySqlIntegration/MySqlContractServiceTest.php
tests/Integration/ContractsModuleTest.php
```

---

## Zmiany w istniejących plikach

### `src/init.php`

Dodać route:

```php
'contracts' => '/contracts',
```

Jeżeli serwis nie jest ładowany automatycznie przez autoloader, dodać także wymagane `require_once`.

### `.htaccess`

Dodać routing `/contracts`, jeśli projekt wymaga osobnej reguły.

Przykład:

```apache
RewriteRule ^contracts$ public/contracts.php [L]
```

Dostosować do obecnego stylu `.htaccess`.

### `cron/tick.php`

Docelowo nie dopisywać kontraktów ręcznie do `cron/tick.php`.

Po wdrożeniu modularizacji tick engine kontrakty mają być wykrywane przez:

```php
TickRegistry::discover()
```

Jeżeli kontrakty powstaną przed pełną migracją tick engine, można użyć tymczasowego mostka, ale powinien on być oznaczony jako rozwiązanie przejściowe.

Docelowy wariant:

```text
dodanie pliku src/Tick/Modules/ContractsModule.php
bez edycji cron/tick.php
```

### `src/FinancialTransactionService.php`

Dodać typy transakcji:

```php
public const TYPE_CONTRACT_SALE = 'contract_sale';
public const TYPE_CONTRACT_PENALTY = 'contract_penalty';
public const TYPE_CONTRACT_BONUS = 'contract_bonus';
```

Dodać je do `ALLOWED_TYPES`.

### `src/WalletConfig.php`

Dodać routing pul:

```php
'contract_sale' => self::POOL_BANK,
'contract_bonus' => self::POOL_BANK,
'contract_penalty' => self::POOL_BANK,
```

Przychód z kontraktu ma trafiać na konto bankowe.

Kary kontraktowe mają być pobierane przez `debitCombined()`, żeby system mógł pobrać środki z konta i gotówki.

### `lang/pl/bank.php`

Dodać etykiety i opisy:

```php
'bank.account.type.contract_sale' => 'Przychód z kontraktu',
'bank.account.type.contract_penalty' => 'Kara kontraktowa',
'bank.account.type.contract_bonus' => 'Bonus kontraktowy',

'bank.tx_contract_sale' => 'Dostawa ropy w ramach kontraktu #:id',
'bank.tx_contract_penalty' => 'Kara za niedostarczenie ropy w kontrakcie #:id',
'bank.tx_contract_bonus' => 'Bonus za terminową realizację kontraktu #:id',
```

---

# Struktura modułu

## `src/Contracts/ContractSchema.php`

Szkielet klasy:

```php
<?php
declare(strict_types=1);

class ContractSchema
{
    /** @var WeakMap<PDO, bool>|null */
    private static ?WeakMap $ensured = null;

    public static function ensure(PDO $db): void
    {
        self::$ensured ??= new WeakMap();

        if (isset(self::$ensured[$db])) {
            return;
        }

        try {
            if ($db->inTransaction()) {
                return;
            }
        } catch (Throwable) {
        }

        try {
            $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                self::createSqlite($db);
            } else {
                self::createMysql($db);
                self::migrateMysql($db);
            }

            self::seedDefaults($db, $driver);
            self::$ensured[$db] = true;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('ContractSchema', 'ensure FAILED', $e);
            }
        }
    }

    private static function createMysql(PDO $db): void
    {
        // tabele MySQL
    }

    private static function createSqlite(PDO $db): void
    {
        // tabele SQLite do testów
    }

    private static function migrateMysql(PDO $db): void
    {
        // przyszłe indeksy i bezpieczne migracje
    }

    private static function seedDefaults(PDO $db, string $driver): void
    {
        // domyślne kontrakty MVP
    }
}
```

Zasady:

```text
Schema nie wykonuje DDL w otwartej transakcji.
Schema działa raz na połączenie.
Schema obsługuje MySQL i SQLite.
```

---

## `src/ContractService.php`

Szkielet:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/Contracts/ContractSchema.php';

class ContractService
{
    public const CFG_MODULE_ENABLED = 'contracts_module_enabled';

    public const TARGET_STORAGE = 'storage';
    public const TARGET_REGION = 'region';
    public const TARGET_HUB = 'hub';
    public const TARGET_PORT = 'port';
    public const TARGET_PLAYER_COMPANY = 'player_company';

    public const CONTEXT_STORAGE_DELIVERY = 'storage_oil_delivery';
    public const CONTEXT_REGION_DELIVERY = 'region_oil_delivery';
    public const CONTEXT_HUB_DELIVERY = 'hub_oil_delivery';
    public const CONTEXT_PORT_DELIVERY = 'port_oil_delivery';
    public const CONTEXT_P2P_DELIVERY = 'player_oil_delivery';

    private const CFG_LABEL_MODULE_ENABLED = 'Contracts module enabled';
    private const CFG_CATEGORY = 'contracts';

    /** @var array<string, list<array<string,mixed>>> */
    private array $optionsCache = [];

    private ?bool $moduleEnabledCache = null;

    public function __construct(private ?PDO $db = null)
    {
        $this->db ??= Database::getInstance()->getConnection();
        ContractSchema::ensure($this->db);
        $this->ensureConfig();
    }

    public function isModuleEnabled(): bool
    {
        // odczyt contracts_module_enabled z well_config
    }

    public function setModuleEnabled(bool $enabled): void
    {
        // zapis do well_config
    }

    public function getAvailableOptions(
        int $playerId,
        string $targetType,
        string $context,
        float $referenceValue = 0.0
    ): array {
        // pobierz aktywne opcje i dołącz terms
    }

    public function acceptContract(
        int $playerId,
        int $optionId,
        string $targetType,
        ?int $targetId,
        string $context
    ): array {
        // podpisanie kontraktu
    }

    public function cancelContract(int $playerId, int $contractId): array
    {
        // anulowanie kontraktu gracza
    }

    public function listActiveContracts(int $playerId): array
    {
        // aktywne kontrakty gracza
    }

    public function listDeliveries(int $playerId, int $limit = 50): array
    {
        // historia dostaw
    }

    public function listLogs(int $playerId, int $limit = 50): array
    {
        // logi kontraktów
    }

    public function processDueContracts(DateTime $now, float $marketPrice): array
    {
        // tick kontraktów
    }

    private function processOneDueContract(array $contract, DateTime $now, float $marketPrice): array
    {
        // jedna rata dostawy
    }

    private function getTermsForMany(array $optionIds): array
    {
        // batch odczyt terms
    }

    private function calculatePrice(array $contract, array $terms, float $marketPrice): float
    {
        // fixed / market_multiplier / market_plus_bonus
    }

    private function logEvent(
        int $playerId,
        ?int $contractId,
        string $targetType,
        ?int $targetId,
        string $context,
        string $eventKey,
        string $message,
        array $meta = []
    ): void {
        // wpis do contract_logs
    }

    private function ensureConfig(): void
    {
        // contracts_module_enabled w well_config
    }
}
```

---

## `src/Tick/Modules/ContractsModule.php`

```php
<?php
declare(strict_types=1);

class ContractsModule implements TickModule
{
    private int $processed = 0;
    private int $completed = 0;
    private int $failed = 0;
    private float $revenue = 0.0;
    private float $penalties = 0.0;

    public function key(): string
    {
        return 'contracts';
    }

    public function order(): int
    {
        return 35;
    }

    public function run(TickContext $ctx): void
    {
        $service = new ContractService($ctx->db);

        $result = $service->processDueContracts(
            $ctx->now,
            (float)$ctx->newPrice
        );

        $this->processed = (int)($result['processed'] ?? 0);
        $this->completed = (int)($result['completed'] ?? 0);
        $this->failed = (int)($result['failed'] ?? 0);
        $this->revenue = (float)($result['revenue'] ?? 0.0);
        $this->penalties = (float)($result['penalties'] ?? 0.0);

        $ctx->mergeStats($this->key(), $this->stats());
    }

    public function stats(): array
    {
        return [
            'contracts_processed' => $this->processed,
            'contracts_completed' => $this->completed,
            'contracts_failed' => $this->failed,
            'contracts_revenue' => $this->revenue,
            'contracts_penalties' => $this->penalties,
        ];
    }
}
```

---

# Tabele bazy danych

## `contract_options`

To odpowiednik `sabotage_options`.

```sql
CREATE TABLE IF NOT EXISTS contract_options (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,

    code VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    description VARCHAR(512) NOT NULL DEFAULT '',

    buyer_name VARCHAR(128) NOT NULL DEFAULT 'Odbiorca kontraktowy',

    target_type VARCHAR(32) NOT NULL,
    context VARCHAR(64) NOT NULL,

    is_active TINYINT(1) NOT NULL DEFAULT 1,

    price_mode ENUM('fixed','market_multiplier','market_plus_bonus') NOT NULL DEFAULT 'market_plus_bonus',
    fixed_price DECIMAL(12,2) NULL,
    price_multiplier DECIMAL(8,4) NOT NULL DEFAULT 1.0000,

    severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',

    min_credibility INT NOT NULL DEFAULT 0,
    requires_legal_level INT NOT NULL DEFAULT 0,

    max_active_per_player INT NOT NULL DEFAULT 3,

    expires_at DATETIME NULL,
    sort_order INT NOT NULL DEFAULT 0,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_contract_code (code),
    KEY idx_contract_target (target_type, context, is_active),
    KEY idx_contract_expires (is_active, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## `contract_terms`

To odpowiednik `sabotage_effects`.

```sql
CREATE TABLE IF NOT EXISTS contract_terms (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,

    contract_option_id INT NOT NULL,

    term_key VARCHAR(64) NOT NULL,
    term_type ENUM('number','percent','minutes','text','bool') NOT NULL DEFAULT 'number',

    term_value DECIMAL(14,4) NULL,
    term_text VARCHAR(255) NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_contract_term (contract_option_id, term_key),
    KEY idx_contract_term_option (contract_option_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Przykładowe `term_key`:

```text
total_bbl
delivery_bbl
delivery_interval_minutes
duration_minutes
bonus_pct
penalty_pct
min_storage_buffer_bbl
credibility_delta_on_success
credibility_delta_on_miss
bonus_on_full_completion_pct
requires_region_id
requires_hub_id
requires_port_id
requires_pipeline
requires_offshore
```

---

## `player_contracts`

To aktywne i historyczne kontrakty graczy.

```sql
CREATE TABLE IF NOT EXISTS player_contracts (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,

    player_id INT NOT NULL,
    contract_option_id INT NOT NULL,

    target_type VARCHAR(32) NOT NULL,
    target_id INT NULL,
    context VARCHAR(64) NOT NULL,

    buyer_name VARCHAR(128) NOT NULL,
    contract_name VARCHAR(128) NOT NULL,

    status ENUM('active','completed','failed','cancelled','expired') NOT NULL DEFAULT 'active',

    total_bbl DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    delivered_bbl DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    missed_bbl DECIMAL(14,2) NOT NULL DEFAULT 0.00,

    next_delivery_at DATETIME NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,

    completed_at DATETIME NULL,
    cancelled_at DATETIME NULL,

    terms_json TEXT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_player_contracts_player (player_id, status),
    KEY idx_player_contracts_due (status, next_delivery_at),
    KEY idx_player_contracts_context (target_type, context),
    KEY idx_player_contracts_end (status, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Statusy:

```text
active
completed
failed
cancelled
expired
```

`terms_json` jest ważne.

Przy podpisaniu kontraktu należy zapisać snapshot warunków z `contract_terms`. Dzięki temu późniejsza edycja szablonu w panelu admina nie zmieni aktywnej umowy gracza.

---

## `contract_deliveries`

To historia rat dostawy.

```sql
CREATE TABLE IF NOT EXISTS contract_deliveries (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,

    player_contract_id INT NOT NULL,
    player_id INT NOT NULL,

    due_at DATETIME NOT NULL,

    required_bbl DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    delivered_bbl DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    missed_bbl DECIMAL(14,2) NOT NULL DEFAULT 0.00,

    price_per_bbl DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    revenue DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    penalty DECIMAL(14,2) NOT NULL DEFAULT 0.00,

    status ENUM('delivered','partial','missed','cancelled') NOT NULL DEFAULT 'delivered',

    meta_json TEXT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_contract_deliveries_contract (player_contract_id),
    KEY idx_contract_deliveries_player (player_id, created_at),
    KEY idx_contract_deliveries_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Statusy:

```text
delivered
partial
missed
cancelled
```

---

## `contract_logs`

To czytelny log modułu.

```sql
CREATE TABLE IF NOT EXISTS contract_logs (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,

    player_contract_id INT NULL,
    player_id INT NOT NULL,

    target_type VARCHAR(32) NOT NULL,
    target_id INT NULL,
    context VARCHAR(64) NOT NULL,

    event_key VARCHAR(64) NOT NULL,
    message VARCHAR(512) NOT NULL DEFAULT '',
    meta_json TEXT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_contract_logs_contract (player_contract_id),
    KEY idx_contract_logs_player (player_id, created_at),
    KEY idx_contract_logs_event (event_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

# Domyślne kontrakty MVP

W `ContractSchema::seedDefaults()` dodać trzy kontrakty.

## Kontrakt mały

```text
code: small_local_refinery
name: Lokalna rafineria
buyer_name: BalticFuel Local
target_type: storage
context: storage_oil_delivery
price_mode: market_plus_bonus
bonus_pct: 5
penalty_pct: 5
total_bbl: 5000
delivery_bbl: 1250
delivery_interval_minutes: 360
duration_minutes: 1440
min_credibility: 0
requires_legal_level: 0
severity: low
```

## Kontrakt średni

```text
code: medium_fuel_network
name: Sieć paliwowa
buyer_name: NorthPetrol Network
target_type: storage
context: storage_oil_delivery
price_mode: market_plus_bonus
bonus_pct: 10
penalty_pct: 8
total_bbl: 30000
delivery_bbl: 5000
delivery_interval_minutes: 720
duration_minutes: 4320
min_credibility: 35
requires_legal_level: 0
severity: medium
```

## Kontrakt duży

```text
code: large_industrial_buyer
name: Koncern przemysłowy
buyer_name: Baltic Heavy Industry
target_type: storage
context: storage_oil_delivery
price_mode: market_plus_bonus
bonus_pct: 18
penalty_pct: 12
total_bbl: 100000
delivery_bbl: 14285
delivery_interval_minutes: 1440
duration_minutes: 10080
min_credibility: 60
requires_legal_level: 3
severity: high
```

---

# Logika podpisania kontraktu

Metoda:

```php
acceptContract(int $playerId, int $optionId, string $targetType, ?int $targetId, string $context): array
```

Kroki:

1. Sprawdź, czy moduł jest włączony.
2. Sprawdź, czy `optionId` istnieje.
3. Sprawdź, czy opcja jest aktywna.
4. Sprawdź `target_type` i `context`.
5. Pobierz warunki z `contract_terms`.
6. Sprawdź, czy oferta nie wygasła.
7. Sprawdź wymagania:
   - wiarygodność firmy,
   - poziom działu prawnego,
   - limit aktywnych kontraktów gracza.
8. Zrób snapshot warunków do `terms_json`.
9. Utwórz wpis w `player_contracts`.
10. Ustaw:
   - `starts_at = NOW()`
   - `next_delivery_at = NOW() + delivery_interval_minutes`
   - `ends_at = NOW() + duration_minutes`
11. Zapisz log `contract_signed`.
12. Zwróć sukces.

Na tym etapie nie pobierać ropy i nie wypłacać pieniędzy.

---

# Logika ticka kontraktów

Metoda:

```php
processDueContracts(DateTime $now, float $marketPrice): array
```

Pobiera kontrakty:

```sql
SELECT *
FROM player_contracts
WHERE status = 'active'
  AND next_delivery_at <= :now
ORDER BY next_delivery_at ASC
LIMIT 200
```

Każdy kontrakt przetwarzać osobno.

Błąd jednego kontraktu nie może zatrzymać pozostałych.

---

## Logika jednej dostawy

Metoda:

```php
processOneDueContract(array $contract, DateTime $now, float $marketPrice): array
```

Kroki:

1. Otwórz transakcję.
2. Pobierz kontrakt `FOR UPDATE`.
3. Pobierz magazyn gracza `FOR UPDATE`.
4. Odczytaj `terms_json`.
5. Oblicz:
   ```php
   $remainingBbl = $contract['total_bbl'] - $contract['delivered_bbl'];
   $requiredBbl = min($terms['delivery_bbl'], $remainingBbl);
   $deliveredBbl = min($requiredBbl, $storageUsed);
   $missedBbl = $requiredBbl - $deliveredBbl;
   ```
6. Jeśli `deliveredBbl > 0`, odejmij ropę z magazynu:
   ```sql
   UPDATE storage
   SET used = used - :deliveredBbl
   WHERE player_id = :playerId
   ```
7. Oblicz cenę:
   - `fixed`: `fixed_price`
   - `market_multiplier`: `marketPrice * price_multiplier`
   - `market_plus_bonus`: `marketPrice * (1 + bonus_pct / 100)`
8. Oblicz przychód:
   ```php
   $revenue = $deliveredBbl * $pricePerBbl;
   ```
9. Jeśli `revenue > 0`, zaksięguj przez `FinancialTransactionService::credit()`.
10. Oblicz karę:
    ```php
    $penalty = $missedBbl * $pricePerBbl * ($penaltyPct / 100);
    ```
11. Jeśli `penalty > 0`, pobierz przez `FinancialTransactionService::debitCombined()`.
12. Dodaj wpis do `contract_deliveries`.
13. Zaktualizuj `player_contracts`:
    - `delivered_bbl += deliveredBbl`
    - `missed_bbl += missedBbl`
    - `next_delivery_at += delivery_interval_minutes`
    - `updated_at = NOW()`
14. Jeśli `delivered_bbl >= total_bbl`, ustaw `completed`.
15. Jeśli `NOW() >= ends_at` i kontrakt nie został wykonany, ustaw `failed`.
16. Dodaj wpis do `contract_logs`.
17. Commit.

---

# Księgowanie

## Przychód

```php
$fts->credit(
    $playerId,
    $revenue,
    FinancialTransactionService::TYPE_CONTRACT_SALE,
    tPlain('bank.tx_contract_sale', ['id' => $contractId]),
    'contract',
    $contractId
);
```

## Kara

```php
$fts->debitCombined(
    $playerId,
    $penalty,
    FinancialTransactionService::TYPE_CONTRACT_PENALTY,
    tPlain('bank.tx_contract_penalty', ['id' => $contractId]),
    'contract',
    $contractId
);
```

## Bonus za pełne wykonanie

Etap późniejszy:

```php
$fts->credit(
    $playerId,
    $bonus,
    FinancialTransactionService::TYPE_CONTRACT_BONUS,
    tPlain('bank.tx_contract_bonus', ['id' => $contractId]),
    'contract',
    $contractId
);
```

---

# `public/contracts.php`

Endpoint gracza ma być cienki, tak jak `public/sabotage.php`.

Szkielet:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/init.php';

Auth::requireLogin();

$playerId = Auth::getUserId();
$db = Database::getInstance()->getConnection();
$contracts = new ContractService($db);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!RateLimiter::check('action')) {
        $error = t('common.ratelimit');
    } elseif (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = t('common.csrf_error');
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'accept_contract') {
            $optionId = (int)($_POST['option_id'] ?? 0);

            $result = $contracts->acceptContract(
                $playerId,
                $optionId,
                ContractService::TARGET_STORAGE,
                null,
                ContractService::CONTEXT_STORAGE_DELIVERY
            );

            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        } elseif ($action === 'cancel_contract') {
            $contractId = (int)($_POST['contract_id'] ?? 0);

            $result = $contracts->cancelContract($playerId, $contractId);

            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }
}

$moduleEnabled = $contracts->isModuleEnabled();

$options = $contracts->getAvailableOptions(
    $playerId,
    ContractService::TARGET_STORAGE,
    ContractService::CONTEXT_STORAGE_DELIVERY
);

$activeContracts = $contracts->listActiveContracts($playerId);
$deliveries = $contracts->listDeliveries($playerId, 20);
$logs = $contracts->listLogs($playerId, 20);

$viewData = array_merge(GameShell::data($playerId), [
    'error' => $error,
    'success' => $success,
    'moduleEnabled' => $moduleEnabled,
    'options' => $options,
    'activeContracts' => $activeContracts,
    'deliveries' => $deliveries,
    'logs' => $logs,
]);

$pageTitle = t('contracts.page_title');
$gameShellTitle = t('contracts.page_title');
$gameShellView = __DIR__ . '/../templates/views/contracts/main.php';
$extraCss = ['/assets/css/contracts.css'];
$extraJs = ['/assets/js/contracts.js'];

require_once __DIR__ . '/../templates/header.php';
extract($viewData, EXTR_SKIP);
require __DIR__ . '/../templates/components/game_shell.php';
require_once __DIR__ . '/../templates/footer.php';
```

---

# Widok gracza

`templates/views/contracts/main.php`

Sekcje:

```text
1. Dostępne kontrakty
2. Aktywne kontrakty
3. Historia dostaw
4. Logi kontraktów
```

## Dostępne kontrakty

Kolumny:

```text
Odbiorca
Kontrakt
Wolumen całkowity
Rata dostawy
Co ile dostawa
Czas trwania
Cena
Kara
Wymagania
Akcja
```

## Aktywne kontrakty

Kolumny:

```text
Kontrakt
Dostarczono / całość
Następna dostawa
Wymagana rata
Braki
Status
Akcja
```

Przydatny komunikat:

```text
Do kolejnej dostawy brakuje Ci 2 400 bbl. Jeśli nie uzupełnisz magazynu, zapłacisz karę około 18 200 PLN.
```

## Historia dostaw

Kolumny:

```text
Data
Kontrakt
Wymagane
Dostarczono
Brakło
Cena za bbl
Przychód
Kara
Status
```

---

# `admin/contracts.php`

Panel admina zrobić analogicznie do `admin/sabotage.php`.

Zakładki:

```text
options
terms
active
deliveries
logs
help
```

Czyli:

```text
Opcje kontraktów
Warunki kontraktów
Aktywne kontrakty graczy
Dostawy
Logi
Pomoc
```

Funkcje:

```text
włącz / wyłącz moduł
dodaj opcję kontraktu
edytuj opcję kontraktu
włącz / wyłącz opcję
dodaj warunek
edytuj warunek
usuń warunek
podejrzyj aktywne kontrakty graczy
podejrzyj dostawy
podejrzyj logi
```

Nie usuwać fizycznie opcji kontraktu. Używać `is_active = 0`.

---

# Znane klucze `contract_terms`

W panelu admina dać podpowiedzi:

```php
$knownTermKeys = [
    'total_bbl',
    'delivery_bbl',
    'delivery_interval_minutes',
    'duration_minutes',
    'bonus_pct',
    'penalty_pct',
    'min_storage_buffer_bbl',
    'credibility_delta_on_success',
    'credibility_delta_on_miss',
    'bonus_on_full_completion_pct',
    'requires_region_id',
    'requires_hub_id',
    'requires_port_id',
    'requires_pipeline',
    'requires_offshore',
    'requires_legal_permit',
];
```

---

# Lang

## `lang/pl/contracts.php`

```php
<?php

return [
    'page_title' => 'Kontrakty',
    'available_title' => 'Dostępne kontrakty',
    'active_title' => 'Aktywne kontrakty',
    'deliveries_title' => 'Historia dostaw',
    'logs_title' => 'Logi kontraktów',

    'btn_accept' => 'Podpisz kontrakt',
    'btn_cancel' => 'Anuluj kontrakt',

    'status_active' => 'Aktywny',
    'status_completed' => 'Zrealizowany',
    'status_failed' => 'Niewykonany',
    'status_cancelled' => 'Anulowany',
    'status_expired' => 'Wygasły',

    'delivery_delivered' => 'Dostarczono',
    'delivery_partial' => 'Częściowo',
    'delivery_missed' => 'Brak dostawy',
    'delivery_cancelled' => 'Anulowano',

    'msg_signed' => 'Kontrakt został podpisany.',
    'msg_cancelled' => 'Kontrakt został anulowany.',
    'msg_delivery_done' => 'Dostawa kontraktowa została rozliczona.',

    'err_module_disabled' => 'Moduł kontraktów jest obecnie wyłączony.',
    'err_not_found' => 'Nie znaleziono kontraktu.',
    'err_option_unavailable' => 'Ta oferta kontraktu jest niedostępna.',
    'err_requirements' => 'Nie spełniasz wymagań tego kontraktu.',
    'err_limit' => 'Masz już maksymalną liczbę aktywnych kontraktów.',
    'err_cancel_status' => 'Tego kontraktu nie można już anulować.',
    'err_process_failed' => 'Nie udało się przetworzyć kontraktu.',
];
```

## `lang/pl/admin/contracts.php`

```php
<?php

return [
    'title' => 'Kontrakty',
    'tab_options' => 'Opcje',
    'tab_terms' => 'Warunki',
    'tab_active' => 'Aktywne kontrakty',
    'tab_deliveries' => 'Dostawy',
    'tab_logs' => 'Logi',
    'tab_help' => 'Pomoc',

    'module_enabled' => 'Moduł kontraktów włączony',
    'msg_module_enabled' => 'Moduł kontraktów został włączony.',
    'msg_module_disabled' => 'Moduł kontraktów został wyłączony.',

    'msg_option_saved' => 'Opcja kontraktu została zapisana.',
    'msg_term_saved' => 'Warunek kontraktu został zapisany.',
    'msg_term_deleted' => 'Warunek kontraktu został usunięty.',

    'err_option_required' => 'Kod, nazwa, target i kontekst są wymagane.',
    'err_option_save' => 'Nie udało się zapisać opcji kontraktu.',
    'err_term_required' => 'Opcja i klucz warunku są wymagane.',
    'err_term_save' => 'Nie udało się zapisać warunku kontraktu.',
    'err_term_delete' => 'Nie udało się usunąć warunku kontraktu.',
];
```

---

# Testy

Dodać testy integracyjne.

Minimalny zestaw:

```text
1. ContractSchema tworzy tabele i seeduje domyślne kontrakty.
2. Moduł można włączyć i wyłączyć przez ContractService.
3. getAvailableOptions zwraca tylko aktywne opcje dla target_type + context.
4. acceptContract tworzy player_contracts i snapshot terms_json.
5. Gracz nie może podpisać kontraktu, jeśli moduł jest wyłączony.
6. Gracz nie może podpisać kontraktu, jeśli nie spełnia wymagań.
7. Gracz nie może przekroczyć limitu aktywnych kontraktów.
8. Tick realizuje pełną dostawę.
9. Tick realizuje częściową dostawę i nalicza karę.
10. Tick przy braku ropy nalicza karę i nie daje przychodu.
11. contract_sale trafia do bank_balance.
12. contract_penalty nie robi ujemnego salda.
13. Kontrakt przechodzi na completed.
14. Kontrakt przechodzi na failed po końcu czasu.
15. Błąd jednego kontraktu nie zatrzymuje innych kontraktów.
16. Dwa równoległe ticki nie mogą pobrać tej samej ropy dwa razy.
17. ContractsModule ma key contracts i order 35.
18. ContractsModule zapisuje statystyki przez TickContext::mergeStats().
```

Dodać osobno test MySQL na `FOR UPDATE` i race condition.

---

# Kolejność wdrożenia

## Etap 1 — szkielet modułu gry

```text
ContractSchema
ContractService
contract_options
contract_terms
player_contracts
contract_deliveries
contract_logs
seedDefaults
```

Bez UI i bez ticka.

## Etap 2 — panel admina

```text
admin/contracts.php
templates/views/admin/contracts/main.php
włączanie modułu
edycja opcji
edycja terms
podgląd logów
```

## Etap 3 — endpoint i widok gracza

```text
public/contracts.php
templates/views/contracts/main.php
assets/css/contracts.css
assets/js/contracts.js
```

## Etap 4 — finanse

```text
FinancialTransactionService typy contract_*
WalletConfig routing pul
bank lang
historia transakcji
```

## Etap 5 — moduł ticka

Jeżeli modularizacja tick engine jest już wdrożona:

```text
src/Tick/Modules/ContractsModule.php
key() = contracts
order() = 35
run(TickContext $ctx)
stats()
```

Jeżeli modularizacja tick engine nie jest jeszcze wdrożona, przygotować moduł tak, aby późniejsze przepięcie wymagało minimalnej zmiany.

## Etap 6 — testy regresyjne

Najpierw testy magazynowe, potem MySQL concurrency.

## Etap 7 — rozbudowa

Dopiero po stabilnym MVP:

```text
regiony
porty
huby
kontrakty PvP
aukcje
reputacja kontraktowa
bonusy za pełne wykonanie
```

---

# Zasady bezpieczeństwa i stabilności

1. Nie mieszać kontraktów z `market_offers`.
2. Nie pobierać całej ropy przy podpisaniu kontraktu.
3. Nie robić bezpośrednich zmian `cash` ani `bank_balance`.
4. Pieniądze księgować tylko przez `FinancialTransactionService`.
5. Ropę pobierać w transakcji.
6. Magazyn blokować przez `FOR UPDATE`.
7. Jeden błąd kontraktu nie może zatrzymać całego ticka.
8. Snapshot warunków zapisywać do `terms_json`.
9. Admin nie usuwa opcji fizycznie, tylko wyłącza.
10. MVP obsługuje tylko magazyn.
11. Regiony, porty i huby dopiero później.
12. Testy MySQL są obowiązkowe dla race condition.
13. Nie odpalać DDL w otwartej transakcji.
14. Logować każde rozliczenie w `contract_logs`.
15. Każda dostawa ma mieć wpis w `contract_deliveries`.
16. Część tickowa ma być gotowa pod `TickModule`.
17. Docelowo dodanie kontraktów do ticka nie powinno wymagać edycji `cron/tick.php`.

---

# Najkrótsze polecenie dla Codexa

```text
Stwórz modułowy system długoterminowych kontraktów na dostawy ropy, wzorowany architektonicznie na module sabotażu i zgodny z briefem modularizacji tick engine.

Nie przerabiaj market_offers.

Dodaj:
- ContractSchema
- ContractService
- ContractsModule implements TickModule
- contract_options
- contract_terms
- player_contracts
- contract_deliveries
- contract_logs
- public/contracts.php
- admin/contracts.php
- widoki i lang

MVP ma obsługiwać tylko kontrakty magazynowe:
TARGET_STORAGE + CONTEXT_STORAGE_DELIVERY.

Opcje kontraktów mają być w bazie.
Warunki kontraktów mają być w contract_terms.
Aktywne kontrakty graczy mają być w player_contracts.
Wykonane dostawy mają być w contract_deliveries.
Logi mają być w contract_logs.

Część tickowa ma być plikiem:
src/Tick/Modules/ContractsModule.php

ContractsModule:
- key() = contracts
- order() = 35
- run(TickContext $ctx)
- stats()

Moduł ma działać po PlayersModule i przed BlackMarketModule.

Przychody i kary księguj przez FinancialTransactionService.
Ropę pobieraj z magazynu w transakcji z FOR UPDATE.

Dodaj testy integracyjne oraz test MySQL na race condition.
```
