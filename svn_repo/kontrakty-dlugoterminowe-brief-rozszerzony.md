# Długoterminowe kontrakty na dostawy ropy — rozszerzony brief rozwoju modułu

## Cel dokumentu

Ten brief opisuje dalszy rozwój modułowego systemu kontraktów długoterminowych.

Zakładamy, że podstawowy moduł kontraktów jest albo będzie wdrożony jako osobny silnik gry, wzorowany architektonicznie na module sabotażu:

```text
ContractSchema
ContractService
ContractsModule implements TickModule
contract_options
contract_terms
player_contracts
contract_deliveries
contract_logs
public/contracts.php
admin/contracts.php
```

Część tickowa ma być zgodna z założeniami modularizacji tick engine:

```text
src/Tick/Modules/ContractsModule.php
key() = contracts
order() = 35
```

Kontrakty powinny działać po `players`, ponieważ najpierw produkcja i logistyka muszą dopisać ropę do magazynu. Dopiero potem moduł kontraktów rozlicza dostawy.

---

## Zakres rozszerzeń

Zostają i mają zostać dopracowane następujące rozszerzenia:

```text
1. Reputacja kontraktowa
2. Bonus za pełną realizację kontraktu
3. Kaucja / zabezpieczenie kontraktu
4. Kontrakty regionalne
8. Renegocjacja kontraktu
9. Zerwanie kontraktu
10. Kontrakty dynamiczne zależne od rynku
11. Kontrakty specjalne / wydarzeniowe
12. Klauzule kontraktowe
13. Kontrakty między graczami
14. Aukcje kontraktów
15. Ubezpieczenie kontraktu
```

Nie rozwijamy teraz jako osobnych priorytetów:

```text
5. Kontrakty pod port / offshore
6. Kontrakty pod hub
7. Okna czasowe dostaw
```

To nie znaczy, że te funkcje nie powstaną nigdy. Po prostu nie są częścią obecnego briefu.

---

# 1. Reputacja kontraktowa

## Cel

Dodać osobny wskaźnik reputacji kontraktowej. Ma on pokazywać, jak dobrze firma realizuje zobowiązania wobec odbiorców ropy.

Nie mieszać tego bezpośrednio z ogólną wiarygodnością firmy. Firma może być dobra finansowo i prawnie, ale słaba operacyjnie, jeśli często zawala dostawy.

## Nowy parametr

Dodać parametr:

```text
contract_reputation
```

Zakres:

```text
0–100
```

Interpretacja:

```text
0–20   bardzo zła reputacja kontraktowa
21–40  słaba reputacja
41–60  przeciętna reputacja
61–80  dobra reputacja
81–100 świetna reputacja
```

## Przechowywanie danych

Rekomendowana tabela:

```sql
CREATE TABLE IF NOT EXISTS contract_reputation (
    player_id INT NOT NULL PRIMARY KEY,
    score INT NOT NULL DEFAULT 50,
    total_contracts INT NOT NULL DEFAULT 0,
    completed_contracts INT NOT NULL DEFAULT 0,
    failed_contracts INT NOT NULL DEFAULT 0,
    cancelled_contracts INT NOT NULL DEFAULT 0,
    missed_deliveries INT NOT NULL DEFAULT 0,
    perfect_contracts INT NOT NULL DEFAULT 0,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Osobna tabela jest lepsza niż dokładanie wielu kolumn do `players`, bo pozwala trzymać statystyki kontraktowe w jednym miejscu.

## Historia reputacji

Dodać tabelę:

```sql
CREATE TABLE IF NOT EXISTS contract_reputation_log (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    contract_id INT NULL,
    delta INT NOT NULL,
    score_after INT NOT NULL,
    reason VARCHAR(80) NOT NULL,
    message VARCHAR(512) NOT NULL DEFAULT '',
    meta_json TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_contract_rep_player (player_id, created_at),
    KEY idx_contract_rep_contract (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Nowy serwis

Dodać:

```text
src/ContractReputationService.php
```

Szkielet:

```php
class ContractReputationService
{
    public function getScore(int $playerId): int;

    public function ensureRow(int $playerId): void;

    public function changeScore(
        int $playerId,
        int $delta,
        string $reason,
        ?int $contractId = null,
        array $meta = []
    ): void;

    public function onDeliverySuccess(int $playerId, int $contractId, array $delivery): void;

    public function onDeliveryMiss(int $playerId, int $contractId, array $delivery): void;

    public function onContractCompleted(int $playerId, int $contractId, bool $perfect): void;

    public function onContractFailed(int $playerId, int $contractId): void;

    public function onContractCancelled(int $playerId, int $contractId): void;
}
```

## Wpływ reputacji na grę

Reputacja kontraktowa wpływa na:

```text
dostępność lepszych kontraktów
wysokość wymaganej kaucji
wysokość kar
koszt ubezpieczenia kontraktu
możliwość renegocjacji
dostęp do kontraktów specjalnych
dostęp do aukcji kontraktów
wiarygodność w kontraktach między graczami
```

## Nowe `contract_terms`

```text
min_contract_reputation
reputation_gain_on_delivery
reputation_gain_on_full_completion
reputation_gain_on_perfect_contract
reputation_loss_on_partial_delivery
reputation_loss_on_missed_delivery
reputation_loss_on_contract_failed
reputation_loss_on_cancel
reputation_required_for_renegotiation
```

## Przykładowe wartości

Mały kontrakt:

```text
min_contract_reputation = 0
reputation_gain_on_delivery = 1
reputation_loss_on_missed_delivery = -1
reputation_loss_on_contract_failed = -3
```

Średni kontrakt:

```text
min_contract_reputation = 35
reputation_gain_on_delivery = 1
reputation_gain_on_perfect_contract = 3
reputation_loss_on_missed_delivery = -2
reputation_loss_on_contract_failed = -6
```

Duży kontrakt:

```text
min_contract_reputation = 60
reputation_gain_on_delivery = 2
reputation_gain_on_perfect_contract = 6
reputation_loss_on_missed_delivery = -4
reputation_loss_on_contract_failed = -12
```

## UI gracza

W `/contracts` dodać panel:

```text
Reputacja kontraktowa: 72/100
Status: dobra reputacja
Ukończone kontrakty: 14
Nieudane kontrakty: 2
Idealnie wykonane: 6
```

Komunikaty:

```text
Twoja reputacja kontraktowa jest zbyt niska dla tego kontraktu.
Ten kontrakt wymaga reputacji kontraktowej min. 60.
Terminowa realizacja zwiększy Twoją reputację.
Braki w dostawie obniżą Twoją reputację.
```

---

# 2. Bonus za pełną realizację kontraktu

## Cel

Dodać premię za wykonanie kontraktu w 100%.

Gracz powinien mieć powód, żeby realizować kontrakt dokładnie, a nie tylko unikać największych kar.

## Rodzaje bonusów

### Bonus procentowy od wartości kontraktu

```text
bonus_on_full_completion_pct = 3
```

Przykład:

```text
Wartość kontraktu: 2 000 000 PLN
Bonus 3%: 60 000 PLN
```

### Bonus tylko za kontrakt bez braków

```text
bonus_requires_no_miss = 1
```

Bonus przysługuje tylko wtedy, gdy:

```text
missed_bbl = 0
wszystkie dostawy mają status delivered
kontrakt kończy się jako completed
```

### Bonus reputacyjny

```text
reputation_gain_on_perfect_contract = 5
```

## Nowe `contract_terms`

```text
bonus_on_full_completion_pct
bonus_requires_no_miss
bonus_min_delivered_pct
bonus_contract_reputation_gain
```

## Logika

Przy zakończeniu kontraktu:

```php
$isCompleted = $deliveredBbl >= $totalBbl;
$isPerfect = $isCompleted && $missedBbl <= 0;
```

Jeżeli kontrakt jest wykonany:

```php
if ($isCompleted && $bonusPct > 0) {
    if (!$bonusRequiresNoMiss || $isPerfect) {
        // nalicz bonus
    }
}
```

## Księgowanie bonusu

Dodać typ w `FinancialTransactionService`:

```php
public const TYPE_CONTRACT_BONUS = 'contract_bonus';
```

Księgować przez:

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

## Logi

Dodać event:

```text
contract_bonus_paid
```

Komunikat:

```text
Otrzymano bonus 60 000 PLN za pełną realizację kontraktu z BalticFuel.
```

## UI

W aktywnym kontrakcie pokazać:

```text
Bonus za pełne wykonanie: 3%
Warunek: brak niedostarczonych baryłek
Szacowany bonus: 60 000 PLN
```

W historii:

```text
Bonus wypłacony: 60 000 PLN
```

---

# 3. Kaucja / zabezpieczenie kontraktu

## Cel

Dodać zabezpieczenie finansowe przy podpisaniu większych kontraktów.

Gracz nie powinien móc brać dużych kontraktów bez żadnego ryzyka.

## Mechanika

Przy podpisaniu kontraktu gracz wpłaca kaucję.

Kaucja może:

```text
wrócić po pełnej realizacji
wrócić częściowo przy częściowej realizacji
przepaść przy porażce
przepaść przy zerwaniu kontraktu
```

## Nowe `contract_terms`

```text
security_deposit_pct
security_deposit_fixed
deposit_refund_on_complete
deposit_partial_refund_enabled
deposit_forfeit_on_fail
deposit_forfeit_on_cancel
deposit_reputation_discount_enabled
```

## Obliczanie kaucji

Opcja procentowa:

```php
$estimatedContractValue = $totalBbl * $estimatedPricePerBbl;
$deposit = $estimatedContractValue * ($securityDepositPct / 100);
```

Opcja stała:

```php
$deposit = security_deposit_fixed;
```

Jeżeli ustawione są oba parametry, użyć większej wartości:

```php
$deposit = max($depositFromPct, $depositFixed);
```

## Wpływ reputacji

Przy dobrej reputacji można obniżać kaucję:

```text
contract_reputation >= 80 → kaucja -30%
contract_reputation >= 60 → kaucja -15%
contract_reputation < 30  → kaucja +25%
```

## Nowe pola w `player_contracts`

```sql
ALTER TABLE player_contracts
ADD COLUMN security_deposit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
ADD COLUMN security_deposit_status ENUM('none','paid','refunded','partial_refund','forfeited') NOT NULL DEFAULT 'none',
ADD COLUMN security_deposit_refunded DECIMAL(14,2) NOT NULL DEFAULT 0.00;
```

## Księgowanie pobrania

Dodać typ:

```php
public const TYPE_CONTRACT_DEPOSIT = 'contract_deposit';
```

Księgowanie:

```php
$fts->debitCombined(
    $playerId,
    $deposit,
    FinancialTransactionService::TYPE_CONTRACT_DEPOSIT,
    tPlain('bank.tx_contract_deposit', ['id' => $contractId]),
    'contract',
    $contractId
);
```

## Księgowanie zwrotu

Dodać typ:

```php
public const TYPE_CONTRACT_DEPOSIT_REFUND = 'contract_deposit_refund';
```

Księgowanie:

```php
$fts->credit(
    $playerId,
    $refund,
    FinancialTransactionService::TYPE_CONTRACT_DEPOSIT_REFUND,
    tPlain('bank.tx_contract_deposit_refund', ['id' => $contractId]),
    'contract',
    $contractId
);
```

## UI

Przy podpisaniu:

```text
Wymagana kaucja: 120 000 PLN
Zwracana po pełnej realizacji kontraktu.
Przepadnie przy zerwaniu lub niewykonaniu kontraktu.
```

Przy aktywnym kontrakcie:

```text
Kaucja: 120 000 PLN
Status: wpłacona
Możliwy zwrot: 120 000 PLN
```

---


# 4. Kontrakty regionalne

## Cel

Dodać kontrakty wymagające działania w konkretnym regionie.

Kontrakt regionalny nie musi jeszcze wymagać konkretnego portu, huba ani trasy. Na tym etapie wystarczy, że kontrakt jest powiązany z regionem i może wymagać licencji.

## Mechanika

Kontrakt ma target:

```php
TARGET_REGION
```

i context:

```php
CONTEXT_REGION_DELIVERY
```

Kontrakt może wymagać:

```text
posiadania licencji w regionie
aktywnego odwiertu w regionie
minimalnej produkcji z regionu
minimalnej reputacji kontraktowej
braku aktywnych kar prawnych w regionie
```

## Nowe `contract_terms`

```text
requires_region_id
requires_region_license
requires_active_well_in_region
min_region_production_bph
regional_bonus_pct
regional_penalty_pct
```

## Walidacja podpisania

Przy `acceptContract()`:

```text
sprawdź, czy gracz ma wymaganą licencję
sprawdź, czy gracz ma aktywny odwiert w regionie
sprawdź, czy spełnia minimalną reputację kontraktową
sprawdź, czy nie ma blokady kontraktowej
```

Nie trzeba jeszcze śledzić, czy ropa faktycznie pochodzi z konkretnego regionu, jeżeli obecny magazyn nie rozróżnia pochodzenia ropy.

## Etap późniejszy

Jeżeli w przyszłości magazyn będzie obsługiwał pochodzenie ropy, można dodać:

```text
storage_batches
oil_origin_region_id
```

Dopiero wtedy kontrakt regionalny będzie mógł wymagać dostarczenia ropy z konkretnego regionu.

## UI

Dostępne kontrakty:

```text
Region: Afryka
Wymaga licencji: tak
Wymaga aktywnego odwiertu w regionie: tak
Premia regionalna: +6%
```

Komunikaty:

```text
Nie masz licencji w wymaganym regionie.
Nie posiadasz aktywnego odwiertu w tym regionie.
Ten kontrakt wymaga minimalnej produkcji regionalnej.
```

---

# 8. Renegocjacja kontraktu

## Cel

Dać graczowi możliwość ratowania kontraktu, jeżeli widzi, że nie da rady wykonać go w terminie.

Renegocjacja nie może być darmowa. Ma być decyzją strategiczną.

## Dostępne akcje

Gracz może poprosić o:

```text
wydłużenie czasu kontraktu
przesunięcie następnej dostawy
zmniejszenie najbliższej raty
obniżenie kary
zamianę części kary na utratę reputacji
```

## Nowe `contract_terms`

```text
allow_renegotiation
max_renegotiations
renegotiation_fee_pct
renegotiation_fee_fixed
renegotiation_reputation_loss
renegotiation_extend_minutes
renegotiation_reduce_next_delivery_pct
renegotiation_penalty_reduction_pct
```

## Nowe pola w `player_contracts`

```sql
ALTER TABLE player_contracts
ADD COLUMN renegotiations_used INT NOT NULL DEFAULT 0,
ADD COLUMN last_renegotiated_at DATETIME NULL;
```

## Nowa tabela

```sql
CREATE TABLE IF NOT EXISTS contract_renegotiations (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    player_contract_id INT NOT NULL,
    player_id INT NOT NULL,

    request_type VARCHAR(64) NOT NULL,
    fee DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    reputation_loss INT NOT NULL DEFAULT 0,

    old_next_delivery_at DATETIME NULL,
    new_next_delivery_at DATETIME NULL,

    old_ends_at DATETIME NULL,
    new_ends_at DATETIME NULL,

    old_delivery_bbl DECIMAL(14,2) NULL,
    new_delivery_bbl DECIMAL(14,2) NULL,

    status ENUM('accepted','rejected') NOT NULL DEFAULT 'accepted',

    meta_json TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_contract_renegotiations_contract (player_contract_id),
    KEY idx_contract_renegotiations_player (player_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Logika

Przy renegocjacji:

```text
1. Sprawdź, czy kontrakt jest aktywny.
2. Sprawdź, czy allow_renegotiation = 1.
3. Sprawdź, czy gracz nie przekroczył max_renegotiations.
4. Oblicz opłatę.
5. Pobierz opłatę przez FinancialTransactionService.
6. Obniż reputację, jeśli wymagane.
7. Zmień warunki aktywnego kontraktu.
8. Zapisz wpis do contract_renegotiations.
9. Zapisz event contract_renegotiated.
```

## UI

Przycisk:

```text
Renegocjuj kontrakt
```

Modal:

```text
Wydłuż termin o 12 godzin
Koszt: 80 000 PLN
Spadek reputacji: -2
Pozostałe renegocjacje: 1
```

---

# 9. Zerwanie kontraktu

## Cel

Dodać możliwość zerwania kontraktu przez gracza.

Zerwanie powinno być możliwe, ale bolesne.

## Nowe `contract_terms`

```text
allow_cancel
cancel_penalty_pct
cancel_penalty_fixed
cancel_reputation_loss
cancel_forfeit_deposit
cancel_blocks_new_contracts_minutes
```

## Nowe pola

W `player_contracts`:

```sql
ALTER TABLE player_contracts
ADD COLUMN cancel_penalty DECIMAL(14,2) NOT NULL DEFAULT 0.00,
ADD COLUMN cancelled_reason VARCHAR(255) NULL;
```

W reputacji lub osobnej tabeli można dodać blokadę:

```sql
ALTER TABLE contract_reputation
ADD COLUMN contract_blocked_until DATETIME NULL;
```

## Logika

Przy anulowaniu:

```text
1. Sprawdź, czy kontrakt jest aktywny.
2. Sprawdź, czy allow_cancel = 1.
3. Oblicz karę.
4. Pobierz karę przez FinancialTransactionService.
5. Jeśli cancel_forfeit_deposit = 1, oznacz kaucję jako przepadłą.
6. Obniż reputację kontraktową.
7. Ustaw status cancelled.
8. Ustaw cancelled_at = NOW().
9. Ustaw cancelled_reason.
10. Zapisz event contract_cancelled.
```

## UI

Komunikat przed anulowaniem:

```text
Zerwanie kontraktu spowoduje:
kara: 240 000 PLN
utrata reputacji: -10
utrata kaucji: 120 000 PLN
blokada nowych kontraktów: 24h
```

Potwierdzenie:

```text
Wpisz ZRYWAM, aby potwierdzić.
```

---

# 10. Kontrakty dynamiczne zależne od rynku

## Cel

Kontrakty powinny reagować na sytuację rynkową, a nie być zawsze takie same.

Moduł może generować oferty zależnie od ceny ropy, trendów i podaży.

## Nowa tabela

```sql
CREATE TABLE IF NOT EXISTS contract_spawn_rules (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,

    code VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(128) NOT NULL,

    is_active TINYINT(1) NOT NULL DEFAULT 1,

    min_market_price DECIMAL(12,2) NULL,
    max_market_price DECIMAL(12,2) NULL,

    min_supply_ratio DECIMAL(8,4) NULL,
    max_supply_ratio DECIMAL(8,4) NULL,

    required_trend_code VARCHAR(64) NULL,

    contract_option_code VARCHAR(64) NOT NULL,

    spawn_chance_pct DECIMAL(6,3) NOT NULL DEFAULT 10.000,
    max_active_offers INT NOT NULL DEFAULT 3,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_contract_spawn_active (is_active),
    KEY idx_contract_spawn_price (min_market_price, max_market_price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Nowy serwis

```text
ContractOfferGeneratorService
```

Metody:

```php
generateDynamicOffers(float $marketPrice, array $marketSnapshot): int;
cleanupExpiredOffers(): int;
```

## Przykłady reguł

Gdy cena niska:

```text
max_market_price = 60
contract_option_code = fixed_price_safe_buyer
spawn_chance_pct = 25
```

Gdy cena wysoka:

```text
min_market_price = 120
contract_option_code = emergency_high_volume_contract
spawn_chance_pct = 15
```

Gdy rynek ma niedobór:

```text
max_supply_ratio = 0.85
contract_option_code = shortage_premium_contract
spawn_chance_pct = 30
```

## UI admina

Dodać zakładkę:

```text
spawn_rules
```

Funkcje:

```text
dodaj regułę generowania
edytuj regułę
włącz / wyłącz regułę
testuj regułę na obecnej cenie rynku
```

---

# 11. Kontrakty specjalne / wydarzeniowe

## Cel

Dodać rzadkie kontrakty, które pojawiają się przy wydarzeniach lub jako specjalne oferty dla najlepszych firm.

## Typy kontraktów

```text
kontrakt rządowy
kontrakt wojskowy
kontrakt kryzysowy
kontrakt humanitarny
kontrakt premium z rafinerią
kontrakt awaryjny w czasie niedoboru
```

## Nowe `contract_terms`

```text
special_contract
special_contract_type
requires_no_recent_spills
requires_no_recent_black_market
requires_contract_reputation
requires_company_credibility
special_bonus_pct
special_failure_publicity_penalty
```

## Warunki dostępu

Przykład kontraktu rządowego:

```text
contract_reputation >= 75
company_credibility >= 70
brak ostatnich wycieków
brak wykrytych czarnorynkowych sprzedaży
dział prawny poziom 4
```

## Ryzyko

Kontrakty specjalne powinny mieć:

```text
wysoką premię
wysokie wymagania
wysoką karę za porażkę
duży wpływ na reputację
widoczny log w historii firmy
```

## UI

Oznaczenie:

```text
KONTRAKT SPECJALNY
Oferta ograniczona czasowo
Wysokie wymagania
Wysoka kara za porażkę
```

---


# 12. Klauzule kontraktowe

## Cel

Dodać klauzule, czyli dodatkowe warunki, które zmieniają sposób rozliczania kontraktu.

Klauzule sprawiają, że kontrakty nie są tylko schematem “dostarcz X ropy”.

## Nowa tabela

```sql
CREATE TABLE IF NOT EXISTS contract_clauses (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,

    code VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(128) NOT NULL,
    description VARCHAR(512) NOT NULL DEFAULT '',

    is_active TINYINT(1) NOT NULL DEFAULT 1,

    effect_key VARCHAR(64) NOT NULL,
    effect_type ENUM('mult','delta','set') NOT NULL DEFAULT 'delta',
    effect_value DECIMAL(14,4) NOT NULL DEFAULT 0.0000,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Tabela połączeniowa:

```sql
CREATE TABLE IF NOT EXISTS contract_option_clauses (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    contract_option_id INT NOT NULL,
    clause_id INT NOT NULL,

    UNIQUE KEY uq_contract_option_clause (contract_option_id, clause_id),
    KEY idx_contract_option_clause_option (contract_option_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Przykładowe klauzule

### Klauzula terminowości

```text
Każda brakująca dostawa obniża cenę kolejnych dostaw o 3%.
```

```text
effect_key = next_delivery_price_cut_pct
effect_value = 3
```

### Klauzula środowiskowa

```text
Jeśli w czasie kontraktu wystąpi wyciek, kara kontraktowa rośnie o 5%.
```

```text
effect_key = environmental_penalty_pct
effect_value = 5
```

### Klauzula siły wyższej

```text
Katastrofa naturalna zmniejsza karę za niedostarczenie o 50%.
```

```text
effect_key = force_majeure_penalty_reduction_pct
effect_value = 50
```

### Klauzula jakości

```text
Kontrakt bez awarii i braków daje dodatkowy bonus 2%.
```

```text
effect_key = quality_bonus_pct
effect_value = 2
```

## UI

Przy kontrakcie pokazać:

```text
Klauzule:
- Terminowość: spóźnienia obniżają cenę kolejnych dostaw.
- Środowiskowa: wyciek zwiększy karę.
- Jakościowa: pełna realizacja bez awarii daje dodatkowy bonus.
```

---

# 13. Kontrakty między graczami

## Cel

Dodać kontrakty P2P, gdzie jeden gracz może zlecić dostawę ropy innemu graczowi.

To ma być etap późniejszy, po stabilnych kontraktach NPC.

## Mechanika

Gracz kupujący tworzy ofertę:

```text
Kupię 20 000 bbl w 48h po 85 PLN/bbl.
```

Gracz sprzedający podpisuje kontrakt i musi dostarczyć ropę.

## Nowa tabela

```sql
CREATE TABLE IF NOT EXISTS player_contract_offers (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,

    buyer_player_id INT NOT NULL,
    seller_player_id INT NULL,

    status ENUM('open','accepted','completed','failed','cancelled','expired') NOT NULL DEFAULT 'open',

    total_bbl DECIMAL(14,2) NOT NULL,
    price_per_bbl DECIMAL(12,2) NOT NULL,
    delivery_deadline DATETIME NOT NULL,

    escrow_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    escrow_status ENUM('none','locked','released','refunded') NOT NULL DEFAULT 'none',

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    accepted_at DATETIME NULL,
    completed_at DATETIME NULL,

    KEY idx_p2p_contract_status (status),
    KEY idx_p2p_contract_buyer (buyer_player_id),
    KEY idx_p2p_contract_seller (seller_player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Escrow

Kupujący musi mieć środki.

Przy wystawieniu oferty:

```text
system blokuje pełną kwotę kontraktu
```

Po wykonaniu:

```text
sprzedający dostaje środki
kupujący dostaje rozliczoną ropę albo zapis dostawy według modelu gry
```

Przy porażce:

```text
kupujący odzyskuje środki
sprzedający płaci karę albo traci reputację
```

## Uwaga projektowa

Kontrakty P2P są bardziej ryzykowne niż NPC, bo wymagają escrow i zabezpieczenia przed nadużyciami.

Wdrożyć dopiero po stabilnym module NPC.

---

# 14. Aukcje kontraktów

## Cel

Dodać rywalizację o najlepsze kontrakty.

Najlepsze kontrakty nie powinny być dostępne dla każdego od razu. Gracze mogą walczyć o nie poprzez aukcję.

## Typy aukcji

### Aukcja marży

Wygrywa gracz, który zaakceptuje najniższą premię ponad cenę rynku.

Przykład:

```text
Kontrakt bazowy: market + 15%
Gracz A oferuje market + 12%
Gracz B oferuje market + 9%
Wygrywa gracz B
```

### Aukcja reputacyjna

Wygrywa firma z najlepszą reputacją kontraktową, jeśli spełnia wymagania.

### Aukcja mieszana

Punktacja:

```text
score = reputacja * 0.6 + atrakcyjność oferty * 0.4
```

## Nowe tabele

```sql
CREATE TABLE IF NOT EXISTS contract_auctions (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,

    contract_option_id INT NOT NULL,
    status ENUM('open','closed','cancelled') NOT NULL DEFAULT 'open',

    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,

    auction_type ENUM('margin','reputation','mixed') NOT NULL DEFAULT 'margin',

    winning_bid_id INT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_contract_auction_status (status, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

```sql
CREATE TABLE IF NOT EXISTS contract_auction_bids (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,

    auction_id INT NOT NULL,
    player_id INT NOT NULL,

    offered_bonus_pct DECIMAL(8,4) NULL,
    bid_score DECIMAL(12,4) NULL,

    status ENUM('active','winning','lost','cancelled') NOT NULL DEFAULT 'active',

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_contract_bid_auction (auction_id),
    KEY idx_contract_bid_player (player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Logika

```text
1. Admin albo generator tworzy aukcję.
2. Gracze składają oferty.
3. Po czasie aukcji tick albo moduł aukcji wybiera zwycięzcę.
4. Zwycięzca dostaje player_contracts.
5. Pozostali dostają status lost.
```

## UI

Zakładka:

```text
Aukcje kontraktów
```

Pokazać:

```text
kontrakt
czas do końca
typ aukcji
obecnie najlepsza oferta
moja oferta
wymagana reputacja
```

---

# 15. Ubezpieczenie kontraktu

## Cel

Dać graczowi możliwość ograniczenia ryzyka finansowego.

Ubezpieczenie pokrywa część kary za niedostarczenie ropy, ale samo kosztuje.

## Mechanika

Przy podpisaniu kontraktu gracz może wykupić ubezpieczenie.

Przykład:

```text
Koszt ubezpieczenia: 3% szacowanej wartości kontraktu
Pokrycie kar: 50%
```

## Nowe `contract_terms`

```text
insurance_available
insurance_cost_pct
insurance_cost_fixed
insurance_penalty_coverage_pct
insurance_requires_min_reputation
insurance_excludes_cancel
insurance_excludes_fraud
```

## Nowe pola w `player_contracts`

```sql
ALTER TABLE player_contracts
ADD COLUMN insurance_enabled TINYINT(1) NOT NULL DEFAULT 0,
ADD COLUMN insurance_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
ADD COLUMN insurance_coverage_pct DECIMAL(8,4) NOT NULL DEFAULT 0.0000;
```

## Księgowanie kosztu

Dodać typ:

```php
public const TYPE_CONTRACT_INSURANCE = 'contract_insurance';
```

Księgowanie:

```php
$fts->debitCombined(
    $playerId,
    $insuranceCost,
    FinancialTransactionService::TYPE_CONTRACT_INSURANCE,
    tPlain('bank.tx_contract_insurance', ['id' => $contractId]),
    'contract',
    $contractId
);
```

## Rozliczanie kary z ubezpieczeniem

Jeśli kara wynosi 100 000 PLN, a pokrycie to 50%:

```text
ubezpieczenie pokrywa 50 000 PLN
gracz płaci 50 000 PLN
```

W `contract_deliveries` można dodać:

```sql
ALTER TABLE contract_deliveries
ADD COLUMN penalty_covered_by_insurance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
ADD COLUMN penalty_paid_by_player DECIMAL(14,2) NOT NULL DEFAULT 0.00;
```

## Wyłączenia

Ubezpieczenie nie powinno działać, jeśli:

```text
gracz zerwał kontrakt ręcznie
gracz użył czarnego rynku do obejścia rozliczeń
kontrakt ma klauzulę wyłączającą ubezpieczenie
gracz ma zbyt niską reputację
```

## UI

Przy podpisaniu:

```text
Ubezpieczenie kontraktu:
Koszt: 60 000 PLN
Pokrycie kar: 50%
Nie obejmuje zerwania kontraktu z winy gracza.
```

W historii dostaw:

```text
Kara: 100 000 PLN
Pokryte przez ubezpieczenie: 50 000 PLN
Zapłacono: 50 000 PLN
```

---

# Priorytet wdrażania

## Faza 1 — mechaniki bazowe

```text
1. Reputacja kontraktowa
2. Bonus za pełną realizację
3. Kaucja / zabezpieczenie kontraktu
9. Zerwanie kontraktu
8. Renegocjacja kontraktu
15. Ubezpieczenie kontraktu
```

Te funkcje wzmacniają podstawowy moduł i nie wymagają jeszcze PvP ani dynamicznego generatora ofert.

## Faza 2 — kontrakty zależne od świata gry

```text
4. Kontrakty regionalne
10. Kontrakty dynamiczne zależne od rynku
11. Kontrakty specjalne / wydarzeniowe
12. Klauzule kontraktowe
```

Te funkcje sprawiają, że kontrakty są bardziej różnorodne i zależne od sytuacji w grze.

## Faza 3 — multiplayer i rywalizacja

```text
13. Kontrakty między graczami
14. Aukcje kontraktów
```

Te funkcje wdrażać dopiero po stabilnym module NPC, ponieważ wymagają escrow, zabezpieczeń i dodatkowych testów antynadużyciowych.

---

# Najkrótsze polecenie dla Codexa

```text
Rozszerz moduł długoterminowych kontraktów na dostawy ropy.

Zostają i mają być szczegółowo dopracowane funkcje:
1. Reputacja kontraktowa
2. Bonus za pełną realizację
3. Kaucja / zabezpieczenie kontraktu
4. Kontrakty regionalne
8. Renegocjacja kontraktu
9. Zerwanie kontraktu
10. Kontrakty dynamiczne zależne od rynku
11. Kontrakty specjalne / wydarzeniowe
12. Klauzule kontraktowe
13. Kontrakty między graczami
14. Aukcje kontraktów
15. Ubezpieczenie kontraktu

Nie wdrażaj teraz jako osobnych priorytetów:
5. Kontraktów pod port / offshore
6. Kontraktów pod hub
7. Okien czasowych dostaw

Zachowaj architekturę modułową:
ContractSchema
ContractService
ContractsModule implements TickModule
contract_options
contract_terms
player_contracts
contract_deliveries
contract_logs

Część tickowa ma pozostać jako ContractsModule:
key() = contracts
order() = 35

Wszystkie nowe warunki dodawaj przez contract_terms, jeżeli to możliwe.
Pieniądze księguj tylko przez FinancialTransactionService.
Dla PvP i aukcji zaprojektuj escrow oraz testy antynadużyciowe.
```
