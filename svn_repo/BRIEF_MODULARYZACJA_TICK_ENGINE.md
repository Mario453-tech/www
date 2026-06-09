# BRIEF DLA AI — MODULARYZACJA SILNIKA TICK

## Cel

Silnik tick (`cron/tick.php`) jest fasadą, która ręcznie wołuje każdą sekcję gry.

Każde nowe moduł wymaga dziś edycji `cron/tick.php` w 4–5 miejscach: `require_once`, instancja z własną sygnaturą, wywołanie `run()`, odczyt statystyk przez publiczne property, własny `try/catch`.

Celem tego zadania jest wprowadzenie wspólnego kontraktu modułowego, dzięki któremu dodanie nowego modułu tickowego będzie wymagało tylko jednego pliku w katalogu `src/Tick/Modules/` — bez edycji `cron/tick.php`.

---

## Zasady nadrzędne

Silnik tick odpala się co 5 minut. To najważniejszy plik w grze.

Każda zmiana w ticku to zmiana wysokiego ryzyka.

Wdrożenie musi być przyrostowe i bezpieczne:

- najpierw fundament bez żadnych zmian działającego kodu,
- potem migracja sekcji jedna na raz,
- backup `cron/tick.php` przed każdą zmianą.

Nie wolno przepisywać wszystkich sekcji naraz.

Nie wolno zmieniać kolejności operacji w ticku bez wyraźnego powodu.

---

## Nowe pliki

### `src/Tick/TickModule.php` — interfejs

Każdy moduł tickowy musi implementować ten interfejs.

```php
interface TickModule {
    public function key(): string;
    public function order(): int;
    public function run(TickContext $ctx): void;
    public function stats(): array;
}
```

- `key()` zwraca krótki identyfikator modułu, np. `'market'`, `'legal'`, `'credibility'`.
- `order()` decyduje o kolejności uruchomienia. Wartości rosną co 10 (10, 20, 30...) żeby zostawić miejsce na wstawianie między istniejącymi.
- `run()` wykonuje całą logikę sekcji, korzystając z kontekstu.
- `stats()` zwraca tablicę klucz-wartość do zapisu w `tick_stats`.

---

### `src/Tick/TickContext.php` — kontener współdzielonych danych

`TickContext` niesie dane dostępne dla wszystkich modułów: połączenie z bazą, czas, cenę ropy, mnożniki balansu, źródło wywołania.

Moduły mogą zapisywać do kontekstu wyniki (np. `MarketModule` ustawia nową cenę ropy, `PlayersModule` odczytuje ją później).

```
$ctx->db              — połączenie PDO
$ctx->now             — DateTime bieżącego ticku
$ctx->newPrice        — cena ropy (ustawiana przez MarketModule)
$ctx->balanceMults    — globalne mnożniki balansu z well_config
$ctx->source          — 'cron' | 'cron_http'

$ctx->setNewPrice(float $p)
$ctx->mergeStats(string $moduleKey, array $data)
$ctx->collectStats(): array
$ctx->loadBalanceMults(): void
$ctx->runCleanup(): void   — incident_retention, notif cleanup
$ctx->summary(): string    — jeden wiersz do echo na końcu ticku
```

---

### `src/Tick/TickRegistry.php` — auto-odkrywanie modułów

`TickRegistry::discover()` skanuje katalog `src/Tick/Modules/`, ładuje wszystkie klasy implementujące `TickModule`, sortuje po `order()` i zwraca posortowaną tablicę.

Nie wymaga rejestrowania modułów ręcznie.

Nowy moduł = nowy plik w katalogu. Nic więcej.

---

### `src/Tick/Modules/` — katalog modułów

Nowe moduły tickowe trafiają tutaj.

Każdy plik implementuje `TickModule`.

Podczas migracji stare sekcje są przenoszone do tego katalogu i dostosowywane do kontraktu.

---

## Kolejność modułów

| order | Klucz | Odpowiednik (stary) |
|-------|-------|---------------------|
| 10 | `market` | `MarketSection` |
| 20 | `bank` | `BankSection` |
| 25 | `marine_purge` | `MarineDeliverySection::purgeStale()` |
| 30 | `players` | `PlayersSection` |
| 40 | `black_market` | inline w tick.php linie 104–154 |
| 50 | `credibility` | `CredibilitySection` |
| 60 | `legal` | `LegalSection` |

Nowe moduły wstawiaj między istniejące wartości (np. `order: 55`).

Nie zmieniaj kolejności istniejących modułów bez analizy zależności.

---

## Zależności między modułami

`PlayersModule` wymaga ceny ropy ustawionej przez `MarketModule`.

`BlackMarketModule` wymaga ceny ropy ustawionej przez `MarketModule`.

Zależności są przekazywane przez `TickContext`, nie przez bezpośrednie wywołania.

Jeżeli moduł A musi uruchomić się przed modułem B, wystarczy że `A.order() < B.order()`.

---

## Jak wygląda tick po pełnej migracji

```php
$ctx = TickContext::boot($db, $now, $source);
$ctx->loadBalanceMults();

foreach (TickRegistry::discover() as $module) {
    try {
        $module->run($ctx);
    } catch (Throwable $e) {
        GameLog::error('tick', $module->key() . ' FAILED', $e);
    }
}

(new TickStatsRepository())->save($ctx->collectStats());
$ctx->runCleanup();
echo $ctx->summary();
```

Około 40 linii zamiast obecnych 280.

---

## Plan wdrożenia — fazy

### Faza 1: fundament (bez zmian w działającym ticku)

1. Utwórz `src/Tick/TickModule.php` (interfejs).
2. Utwórz `src/Tick/TickContext.php` (kontener).
3. Utwórz `src/Tick/TickRegistry.php` (auto-discover).
4. Utwórz katalog `src/Tick/Modules/`.
5. Przenieś i zaadaptuj `CredibilitySection` jako `CredibilityModule` — wzór do sprawdzenia.
6. `cron/tick.php` nadal woła starą `CredibilitySection`. Nowy plik tylko istnieje.

**Tick działa identycznie. Zero ryzyka w tej fazie.**

### Faza 2: podpięcie pierwszego modułu (jedno wywołanie)

1. Backup `cron/tick.php`.
2. W `cron/tick.php` zamień tylko sekcję credibility (4 linie) na wywołanie `CredibilityModule` przez rejestr.
3. Przetestuj tick ręcznie.
4. Jeżeli OK — przejdź do kolejnych sekcji.

### Faza 3: migracja pozostałych sekcji

Migruj po jednej sekcji na tick, po każdej test.

Kolejność od najmniejszego ryzyka:

1. `CredibilitySection` — 56 linii, brak zależności wyjściowych
2. `LegalSection` — samodzielna, zwraca tylko liczniki
3. `MarineDeliverySection::purgeStale()` — static, łatwa do owinięcia
4. `BlackMarketModule` — wyodrębnić z inline kodu tick.php
5. `MarketSection` — ustawia `newPrice` w ctx
6. `BankSection` — pobiera flagi `$bankNegAvailable`, `$bankruptcyAvailable`
7. `PlayersSection` — największa, najważniejsza, migruj na końcu

### Faza 4: odchudzenie cron/tick.php

Po migracji wszystkich sekcji: przenieś logikę `loadBalanceMults()`, `runCleanup()` i `summary()` do `TickContext`. Skróć `cron/tick.php` do ~40 linii.

---

## Czego nie robić

Nie migrować wszystkich sekcji naraz.

Nie zmieniać logiki biznesowej podczas migracji — tylko przenieść do nowej struktury.

Nie usuwać starych plików sekcji zanim nowe moduły nie są przetestowane.

Nie łączyć tego zadania z innymi zmianami w ticku.

---

## Scaffolder modułów gry (opcjonalne, osobne zadanie)

Niezależnie od tick engine, można dodać skrypt `bin/scaffold-module.php`, który generuje szkielet pełnego modułu gry wg schematu AGENTS.md §4:

```
src/NazwaService.php
src/NazwaApi.php
public/nazwa.php
templates/views/nazwa/main.php
assets/js/nazwa.js
assets/css/nazwa.css
admin/nazwa.php
lang/pl/nazwa.php
```

To osobne zadanie, niezwiązane z tick engine. Nie wdrażać w tym samym kroku.

---

## Pliki tego modułu

| Plik | Status |
|------|--------|
| `src/Tick/TickModule.php` | do utworzenia |
| `src/Tick/TickContext.php` | do utworzenia |
| `src/Tick/TickRegistry.php` | do utworzenia |
| `src/Tick/Modules/CredibilityModule.php` | do utworzenia (wzór) |
| `cron/tick.php` | modyfikacja (Faza 2+) — backup obowiązkowy |
| `src/Tick/CredibilitySection.php` | zostaje bez zmian do końca Fazy 3 |

---

## Testy po każdej fazie

Po Fazie 1: uruchom `cron/tick.php` — wynik identyczny jak przed. Żadne nowe pliki nie są jeszcze wołane.

Po Fazie 2: sprawdź logi GameLog dla klucza `credibility` — musi pojawić się log z nowego modułu, nie ze starej sekcji.

Po każdej fazie 3 (migracja sekcji): porównaj wyniki `tick_stats` z poprzednim tickiem — wartości muszą być zbliżone.

Po Fazie 4: tick powinien działać w czasie < 10s dla 100 graczy × 10 odwiertów (cel z AGENTS.md §10).
