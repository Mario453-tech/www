# CLAUDE.md

Wskazówki dla Claude Code przy pracy w tym repozytorium.

> **PRZECZYTAJ NAJPIERW `AGENTS.md`** — to pełne wytyczne agenta (rola, architektura,
> SoC, modale, baza, tick, testy). `GAME_README.md` = stan wdrożonych funkcji + changelog.
> Ten plik (`CLAUDE.md`) zawiera tylko wyciąg najważniejszych zasad, które zawsze muszą
> być w kontekście. W razie konfliktu obowiązuje `AGENTS.md`.

## Zasady bezwzględne — NIGDY nie łam (wyciąg z AGENTS.md §2, §22)

### HTML / CSS
- **ZERO tabel HTML** (`table/tr/td/th/thead/tbody`) w layoutcie — layout tylko
  CSS Grid / Flexbox. (Tabele w **bazie danych** SQL `CREATE TABLE` to co innego — są OK.)
- **ZERO inline `style=""`** — wyjątek: dynamiczne wartości PHP (`--bar-w:<?=?>%`, `width`, `color`).
- **ZERO bloków `<style>`** w plikach PHP. Style → `assets/css/[modul].css`, ładowane osobnym `<link>`.
- **ZERO emoji Unicode** w kodzie/UI/komunikatach — zamiast emoji SVG (check/warning/error).

### JavaScript
- **ZERO logiki JS inline w PHP** — cały JS w `assets/js/[modul].js`. Wyjątek: blok
  `<script>` tylko z konfiguracją PHP→JS (`window.MODAL_LANG`, `*_LANG`), zero logiki.
- **Nigdy** natywnych `confirm()` / `alert()` / `prompt()` — zawsze funkcje z `modal.js`
  (`confirmAction`, `promptInput`, `alertInfo/Error/Warning`, `showGameToast`).

### PHP / architektura (SoC)
- Każdy polski string dla gracza → `lang/pl/[modul].php` jako `t('modul.klucz')`. Nigdy nie hardkoduj.
- Separacja: logika w `src/`, HTML w `templates/views/`, SQL nigdy w widoku.
- Schemat feature: `src/[Nazwa]Service.php` + `[Nazwa]Api.php` + `public/[nazwa].php`
  + `templates/views/[nazwa]/main.php` + `assets/js/[nazwa].js` + `assets/css/[nazwa].css` + `admin/[nazwa].php`.
- Limit **~500 linii** na plik PHP/JS/CSS — dziel na traity / podwidoki / moduły.
- **GameLog obowiązkowy** w serwisach: `__construct`, każda metoda publiczna, każdy `catch`.
- **CSRF — TYLKO te metody** (klasa `src/CSRF.php`, NIE wymyślaj innych nazw):
  - `CSRF::field()` — ukryte pole `<input>` w formularzu HTML (najczęstsze).
  - `CSRF::generateToken()` — pobranie surowego tokenu (np. do `window.*_CSRF` dla AJAX).
  - `CSRF::validateToken($token)` — walidacja tokenu po stronie serwera. Zwraca `bool`.
  - NIE istnieje `CSRF::validate()` ani `CSRF::check()` — walidacja to ZAWSZE
    `validateToken()`. / CSRF validation is ALWAYS `CSRF::validateToken($token)`,
    never `validate()` / `check()`.

### Baza danych
- MySQL 8.0, PDO prepared statements (zero interpolacji), DECIMAL (nie FLOAT) dla finansów.
- `Database::addColumnIfMissing()` zamiast ręcznych ALTER. Migracje idempotentne.
- Nigdy `DROP`/`TRUNCATE` bez wyraźnej zgody użytkownika.

### Zasada minimalnej zmiany
- Najmniejsza bezpieczna zmiana rozwiązująca problem. Nie przebudowuj architektury bez polecenia.
- Nie ruszaj kodu, którego nie trzeba. Przed zmianą funkcji/nazwy/SQL — sprawdź użycia w innych plikach.
- Po zmianie modułu — dopisz changelog w `GAME_README.md`.

## Kopie zapasowe (backup) — ZASADA OBOWIĄZKOWA

Przed każdą zmianą pliku, którą warto cofnąć, ZAWSZE rób kopię zapasową.
Nigdy inaczej niż wg poniższego schematu:

- **Lokalizacja:** zawsze katalog `backups/` (nigdzie indziej).
- **Nazwa:** `<data>_<godzina>_<nazwa-oryginalnego-pliku>.bak`
  - format daty/godziny: `YYYY-MM-DD_HH-MM-SS`
  - zachowaj pełną oryginalną nazwę pliku z rozszerzeniem, na końcu dodaj `.bak`
- **Przykłady (zgodne z istniejącymi w repo):**
  - `backups/2026-05-29_04-43-09_transport.php.bak`
  - `backups/2026-05-28_22-52-51_htaccess.bak`

Czyli zawsze: nazwa pliku + data, rozszerzenie `.bak`, kopia w katalogu `backups/`.
Nigdy nie nadpisuj pliku bez wcześniejszego zrobienia takiej kopii.

## Git — ZASADA OBOWIĄZKOWA

### Git-first: zawsze na main

**JEDYNA gałąź to `main`.** Nigdy nie używaj feature branchy ani innych gałęzi —
każda zmiana musi natychmiast trafić na `main`, żeby GitHub Actions wydeployował ją
na serwer przez FTP. Git jest źródłem prawdy; FTP to tylko transport.

Kolejność zawsze:
1. Zrób backup (patrz niżej)
2. Wprowadź zmiany
3. Zweryfikuj kodowanie
4. `git add` zmienionych plików + backupów
5. `git commit` — opis **po polsku**, **szczegółowy**, z **datą wdrożenia**:
   - Pierwsza linia: `YYYY-MM-DD — Moduł: krótkie podsumowanie (max ~72 znaki)`
   - Kolejne linie (po pustej): co było źródłem problemu, co zmieniono i dlaczego.
   - Przykład:
     ```
     2026-07-08 — Dział techniczny: opłata zadania przez bank+gotówka

     Błąd: debit() sprawdzał tylko players.cash; gracz z środkami na koncie
     bankowym dostawał błąd insufficient_funds mimo wystarczającego salda.
     Zmiana: debit() -> debitCombined(), które pobiera najpierw z banku,
     potem z gotówki — spójnie z kwotą pokazywaną w UI.
     ```
   - Nigdy nie pisz tylko `fix`, `update`, `changes` bez kontekstu.
6. `git push -u origin main`

Jeśli push odrzucony (remote ma nowe commity): `git pull origin main --no-rebase` a potem push.

## Styl pisania kodu — ZASADA OBOWIĄZKOWA

### Zakres zmian
- Najmniejsza zmiana rozwiązująca problem. Nie dodawaj funkcji, refaktoryzacji ani abstrakcji
  ponad to, czego wymaga zadanie.
- Trzy podobne linie są lepsze niż przedwczesna abstrakcja.
- Żadnych niedokończonych implementacji — każda zmiana musi działać w całości.

### Obsługa błędów
- Nie dodawaj obsługi błędów dla scenariuszy, które nie mogą wystąpić.
- Walidacja tylko na granicach systemu: input użytkownika, zewnętrzne API.
- Ufaj gwarancjom wewnętrznego kodu i frameworka — nie owijaj w bawełnę.

### Komentarze
- **Domyślnie zero komentarzy.**
- Dodaj tylko gdy DLACZEGO jest nieoczywiste: ukryte ograniczenie, subtelny niezmiennik,
  obejście konkretnego buga. Jeśli usunięcie komentarza nie zmyli przyszłego czytającego —
  nie pisz go.
- Nie opisuj CO kod robi — dobrze nazwane identyfikatory to robią same.
- Nie referencjonuj zadania, ticketu ani callera w komentarzach (np. "dodane dla flow X",
  "wywoływane przez Y") — to należy do opisu commita, nie kodu.

### Bezpieczeństwo
- Nigdy nie wprowadzaj SQL injection, XSS, command injection ani innych OWASP Top 10.
- PDO prepared statements zawsze — zero interpolacji zmiennych w SQL.
- Jeśli zauważę niebezpieczny kod (nawet istniejący) — naprawiam od razu.

## Kodowanie i komentarze — ZASADA OBOWIĄZKOWA

Przy KAŻDEJ zmianie istniejącego pliku ORAZ przy tworzeniu nowego pliku
obowiązuje poniższy standard. Always check this on every file change/creation.

1. **UTF-8 bez BOM** — zawsze. Nigdy nie zapisuj znacznika BOM (`EF BB BF`) na
   początku pliku. / Always UTF-8, never write a BOM.
2. **Bez krzaków (mojibake)** — żadnych uszkodzonych znaków: znak zastępczy
   `U+FFFD` ani podmienionych polskich liter (np. `Zarz[?]du`, `pracownik[?]w`, `Dzia prawny`
   zamiast `Dział prawny`). Jeśli natrafisz na krzaki w pliku, który ruszasz —
   napraw je na poprawne UTF-8. / No mojibake; fix any you touch.
3. **Komentarze dwujęzyczne, BEZ polskich znaków diakrytycznych** — komentarze
   pisz po polsku ORAZ po angielsku, ale w polskiej części NIE używaj liter
   `ąćęłńóśźżĄĆĘŁŃÓŚŹŻ`. Zamiast nich pisz odpowiedniki bez ogonków
   (np. `splata` zamiast `spłata`, `gotowka` zamiast `gotówka`, `srodki`
   zamiast `środki`, `wlasciwy` zamiast `właściwy`). Dotyczy WYŁĄCZNIE
   komentarzy w kodzie — stringi językowe (`lang/pl/*.php`), wiadomości
   commitów oraz dokumentacja Markdown mogą zawierać poprawne polskie znaki.
   / Bilingual comments, but the Polish part MUST NOT contain diacritics
   (`ąćęłńóśźżĄĆĘŁŃÓŚŹŻ`). Use ASCII-only Polish in comments (e.g. `splata`
   instead of `spłata`). This applies to CODE COMMENTS ONLY — language
   strings, commit messages, and Markdown docs may use proper Polish.
4. **Zawsze weryfikuj po zmianie** — po każdej edycji/utworzeniu pliku sprawdź
   kodowanie. Always verify after each change, e.g.:
   - BOM (musi być puste / must be empty):
     `head -c3 PLIK | od -An -tx1` — nie może zwrócić `ef bb bf`
   - Krzaki (musi być puste / must be empty):
     `grep -nP '\xEF\xBF\xBD' PLIK`
   - Poprawność UTF-8:
     `php -r 'echo mb_check_encoding(file_get_contents("PLIK"),"UTF-8")?"OK\n":"ZLE\n";'`

## Zasady bezpiecznego kodowania — ZASADA OBOWIĄZKOWA

Wnioski z dwóch rund code-review (czerwiec 2026). Stosuj przy każdej zmianie PHP/JS.

### 1. Izolacja gracza w SQL — każdy UPDATE/DELETE/SELECT musi mieć player_id

Każde zapytanie dotyczące zasobów gracza MUSI filtrować po `player_id`.
Brak tego warunku pozwala graczowi zmodyfikować dane innego gracza.

```php
// ŹLE — gracz może podać cudze staff_id
UPDATE technical_staff SET status = 'fired' WHERE id = ?

// DOBRZE
UPDATE technical_staff SET status = 'fired' WHERE id = ? AND player_id = ?
```

Dotyczy też SELECT-ów sprawdzających własność (np. busy-check pracownika).
Przy kopiowaniu zapytania zawsze sprawdź czy `player_id` jest obecne.

### 2. Atomowe sprawdzanie salda — nigdy SELECT+UPDATE osobno

Sprawdzanie salda i jego zmiana muszą być atomowe (jedna operacja SQL).
Wzorzec SELECT cash + osobny UPDATE pozwala na race condition — cash może
zejść poniżej zera przy równoczesnych requestach.

```php
// ŹLE — TOCTOU: inny request może zmienić cash między SELECT a UPDATE
$cash = $db->query("SELECT cash FROM players WHERE id = ?")->fetchColumn();
if ($cash < $cost) return error;
$db->query("UPDATE players SET cash = cash - ? WHERE id = ?");

// DOBRZE — atomowe, wewnątrz transakcji
$stmt = $db->prepare("UPDATE players SET cash = cash - ? WHERE id = ? AND cash >= ?");
$stmt->execute([$cost, $playerId, $cost]);
if ($stmt->rowCount() === 0) { $db->rollBack(); return error; }
```

### 3. Zagnieżdżone transakcje w PDO/MySQL — niedozwolone

PDO z MySQL (InnoDB) nie obsługuje prawdziwych zagnieżdżonych transakcji.
Wywołanie `beginTransaction()` wewnątrz już otwartej transakcji powoduje
niejawny commit lub wyjątek — co niszczy spójność danych.

Zasada: metody które same otwierają transakcję (np. `startTask()`)
MUSZĄ być wywoływane PO `commit()` transakcji zewnętrznej, nigdy w środku.

```php
// ŹLE — startTask() otwiera własną transakcję wewnątrz tej zewnętrznej
$db->beginTransaction();
// ... operacje ...
$this->startTask($staffId, $taskData); // nested beginTransaction() — BŁĄD
$db->commit();

// DOBRZE
$db->beginTransaction();
// ... operacje ...
$db->commit();
$this->startTask($staffId, $taskData); // po commit — bezpieczne
```

### 4. Escaping w szablonach PHP — każde <?= musi być bezpieczne

Każda zmienna wypisywana w HTML musi być escapowana. Dotyczy to szczególnie:
- atrybutów HTML (w tym `id=`, `value=`, `data-*`)
- atrybutów JS inline (`onclick=`, `onsubmit=`) — tu `ENT_QUOTES` jest kluczowe
- danych z bazy danych, które mogą zawierać cudzysłowy lub `<script>`

```php
// ŹLE — XSS możliwy przez apostrof w tłumaczeniu lub danych z DB
onsubmit="return confirm('<?= t('msg') ?>')"
id="item-<?= $row['id'] ?>"

// DOBRZE
onsubmit="return confirm('<?= htmlspecialchars(t('msg'), ENT_QUOTES, 'UTF-8') ?>')"
id="item-<?= (int)$row['id'] ?>"
```

Dla ID-ków z bazy (zawsze liczby całkowite) używaj `(int)` zamiast `htmlspecialchars`.
Dla JSON w atrybutach onclick używaj `json_encode($var, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)` — BEZ dodatkowego `htmlspecialchars()`, które zniszczy cudzysłowy JSON.

### 5. Transakcje — wzorzec $ownTx

Przy metodach które mogą być wywołane zarówno samodzielnie jak i wewnątrz
innej transakcji, używaj wzorca `$ownTx`:

```php
$ownTx = !$this->db->inTransaction();
if ($ownTx) $this->db->beginTransaction();
try {
    // ... operacje ...
    if ($ownTx) $this->db->commit();
} catch (\Throwable $e) {
    if ($ownTx && $this->db->inTransaction()) $this->db->rollBack();
    throw $e;
}
```

### 6. Powiadomienia i operacje poboczne — zabezpiecz try/catch

Metody poboczne takie jak `notify()`, `log()`, wysyłanie eventów — NIE mogą
rzucać wyjątków do wywołującego. Zawsze owijaj w try/catch z logowaniem:

```php
try {
    $this->notify('task_complete', $wellId, t('...'));
} catch (\Throwable $e) {
    GameLog::error('notifications', 'notify() failed', $e);
    // nie przerywaj głównego przepływu
}
```

### 7. Zawsze prepare() — nigdy interpolacja zmiennych w SQL

Każde zapytanie SQL zawierające jakąkolwiek zmienną MUSI używać `prepare()` z parametrami `?`.
Interpolacja `"... WHERE id = {$id}"` jest niedopuszczalna nawet jeśli zmienna jest rzutowana
na `(int)` — bo wzorzec jest kopiowany bez rzutowania, bo `->query()` nie obsługuje parametrów,
i bo mieszanie stylów utrudnia audyt bezpieczeństwa. Jedynym wyjątkiem są zapytania
bez żadnych zmiennych zewnętrznych.

```php
// ZLE — interpolacja zmiennej w SQL (znaleziono w ActionsTrait.php)
$this->db->query("SELECT boost FROM wells WHERE id = {$wellId}")->fetchColumn();

// DOBRZE
$stmt = $this->db->prepare("SELECT boost FROM wells WHERE id = ?");
$stmt->execute([$wellId]);
$val = $stmt->fetchColumn();
```

### 8. CSRF — zawsze validateToken(), nigdy nieistniejące metody

Klasa `CSRF` ma dokładnie trzy metody publiczne: `generateToken()`, `validateToken(string $token)`,
`field()`. Wywołanie nieistniejącego `CSRF::validate()` nie jest błędem kompilacji — PHP rzuci
`Error: Call to undefined method` dopiero przy pierwszym POST, co oznacza że brama CSRF nie działa
i każdy form jest podatny. Przed każdym użyciem klasy CSRF sprawdź nazwę metody dosłownie.

```php
// ZLE — metoda nie istnieje, brama CSRF przepuszcza wszystko do błędu 500
if (!CSRF::validate($_POST['_token'] ?? '')) { return; }

// DOBRZE
if (!CSRF::validateToken($_POST['_token'] ?? '')) { return; }
```

### 9. Dialogi potwierdzenia — data-confirm, nie onclick confirm()

Inline `onclick="return confirm('...')"` jest zawodne: cudzysłowy z `json_encode` psują atrybut
HTML, polskie znaki i apostrofy łatwo wyrwą się z kontekstu tworząc XSS lub błąd parsera.
Jedyny dopuszczalny wzorzec to atrybut `data-confirm` escapowany przez
`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`, przechwytywany przez globalny handler w `modal.js`.
Nie dodawaj nowych `onclick confirm` nigdzie w kodzie.

```php
// ZLE — apostrofy i cudzysłowy psują atrybut HTML
<button onclick="return confirm(<?= json_encode(t('msg'), JSON_UNESCAPED_UNICODE) ?>)">

// DOBRZE — escapowany atrybut, jeden globalny handler w modal.js
<form data-confirm="<?= htmlspecialchars(t('confirm.msg'), ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit">...</button>
</form>
```

### 10. Mutacje gotówki — zawsze przez Player::updateCash() z typem

Każde zmniejszenie lub zwiększenie `players.cash` musi być wykonane przez
`Player::updateCash(float $amount, string $type, ?string $desc)` z niepustym `$type`
pasującym do stałych `FinancialTransactionService::TYPE_*`. Bezpośredni
`UPDATE players SET cash = cash - ?` bez wywołania `updateCash()` tworzy lukę w logu
finansowym — brakujące transakcje są nieodkrywalne retroaktywnie. Nowe typy operacji
wymagają dodania stałej `TYPE_` do `FinancialTransactionService`.

```php
// ZLE — surowy SQL, brak wpisu w financial_transactions
$db->prepare("UPDATE players SET cash = cash - ? WHERE id = ? AND cash >= ?")
   ->execute([$cost, $playerId, $cost]);

// DOBRZE — atomowe + audit trail
$player = new Player($db, $playerId);
$ok = $player->updateCash(-$cost, FinancialTransactionService::TYPE_TASK_FEE, "Task $taskId");
if (!$ok) throw new \RuntimeException('insufficient_cash');
```

### 11. Tick/pętle — continue po błędzie DB, nie kontynuuj akumulacji

W pętli tick, jeśli próba zapisu statusu do DB się nie uda (wyjątek w UPDATE), a kod
kontynuuje akumulację wartości do sumy zbiorczej (np. `$totalDelivered +=`), kolejny tick
ponownie przetworzy ten sam element i ponownie doliczy jego wartość — podwójny kredyt.
Po każdym nieudanym UPDATE w pętli natychmiast wywołaj `continue`, żeby pominąć akumulację.

```php
// ZLE — brak continue; $total rośnie mimo błędu DB → podwójny kredyt w następnym tiku
try {
    $db->execute("UPDATE trips SET status='crediting' WHERE id=?", [$id]);
} catch (\Throwable $e) {
    GameLog::error('tick', 'update failed', $e);
    // brak continue!
}
$totalDelivered += $delivered;

// DOBRZE
try {
    $db->execute("UPDATE trips SET status='crediting' WHERE id=?", [$id]);
} catch (\Throwable $e) {
    GameLog::error('tick', 'update failed', $e);
    continue; // element pozostaje in_transit, zostanie bezpiecznie przetworzony ponownie
}
$totalDelivered += $delivered;
```

### 12. Tick/gotówka — każde odjęcie z playerCash MUSI trafić do totalCosts

Realny zapis salda gracza w ticku to `FinancialStateSection::saveCashAndTick`:
`cash = GREATEST(0, cash - totalCosts)`. Zmienna `playerCash`/`loopCtx->playerCash`
w pamięci służy tylko do sprawdzania wypłacalności i detekcji kryzysu — **nigdy nie
jest zapisywana do bazy**. Jeśli koszt odejmiesz od `playerCash`, ale zapomnisz dodać go
do `totalCosts`, koszt nie schodzi z realnego salda — gracz dostaje go za darmo.
Ten błąd zdarzył się dla kosztów incydentów i katastrof (runda 5 C1).

```php
// ZLE — koszt katastrofy nie schodzi z DB (brak w totalCosts)
$loopCtx->finIncident += $cost;
$loopCtx->playerCash   = max(0.0, $loopCtx->playerCash - $cost);

// DOBRZE — totalCosts = realny zapis DB; finIncident = raport; playerCash = wyplacalnosc
$loopCtx->finIncident += $cost;
$loopCtx->totalCosts  += $cost;   // ← bez tego katastrofa/incydent jest darmowy
$loopCtx->playerCash   = max(0.0, $loopCtx->playerCash - $cost);
```

### 13. Tick/deltaHours — mechaniki per-tick skaluj czasem, nie stałym +1

Tick może obejmować różny czas (`deltaHours`): normalny co 5 min, ale po przerwie crona
jeden „catch-up" tick nadrabia wiele godzin. Każda mechanika liczona per-tick (koszty,
pensje, odsetki, prawdopodobieństwa incydentów, liczniki odporności/presji, decay) MUSI
skalować się przez `deltaHours`, a nie zakładać stałego kroku. Inaczej catch-up tick
under/over-nalicza albo daje „darmowe" okno. Prawdopodobieństwa dodatkowo clampuj do
`min(1.0, ...)`. Błędy tej klasy: licznik odporności +1/uruchomienie (runda 5 M8),
incydent morski liczony po ETA (M5), clamp zdarzeń regionalnych, decay czarnego rynku.

```php
// ZLE — stałe +1 niezależnie od czasu; catch-up tick daje darmową odporność
$db->prepare("UPDATE wells SET ticks_since_incident = ticks_since_incident + 1 WHERE ...");

// DOBRZE — skala czasem (1 tick = 5 min = deltaHours/12)
$ticksElapsed = max(1, (int) round($deltaHours * 12.0));
$db->prepare("UPDATE wells SET ticks_since_incident = ticks_since_incident + ? WHERE ...")
   ->execute([$ticksElapsed, ...]);
```

### 14. Znaczniki czasu w DB — jeden zegar dla zapisu i odczytu (MySQL NOW())

Jeśli kolumnę czasu zapisujesz z PHP (`date()`/`time()`), a porównujesz z `NOW()`
(zegar sesji MySQL), różnica stref czasowych PHP vs MySQL przesuwa całe okno (rekord
wygasa za wcześnie albo wisi za długo). Zapis i odczyt MUSZĄ używać tego samego zegara —
domyślnie MySQL `NOW()` / `DATE_ADD(NOW(), INTERVAL ...)` po obu stronach. Błędy tej
klasy: wygasanie ofert czarnego rynku (runda 5 M2), purge dostaw morskich, fallback
odporności incydentów.

```php
// ZLE — zapis z PHP time(), odczyt z MySQL NOW() => skew stref
$expires = date('Y-m-d H:i:s', time() + $ttlMin * 60);
$db->prepare("INSERT INTO offers (..., expires_at) VALUES (..., ?)")->execute([$expires]);
// ... gdzie indziej: WHERE expires_at <= NOW()

// DOBRZE — obie strony na zegarze MySQL
$db->prepare("INSERT INTO offers (..., expires_at) VALUES (..., DATE_ADD(NOW(), INTERVAL ? MINUTE))")
   ->execute([$ttlMin]);
```

## Flutter (mobile/) — ZASADA OBOWIĄZKOWA

### Zawsze buduj aplikację po zmianach

Po każdej zmianie kodu Flutter (pliki w `mobile/lib/`) OBOWIĄZKOWO:

1. `flutter analyze --no-pub` — zero błędów przed commitem
2. Zbuduj APK:
   ```bash
   cd /home/user/www/mobile
   /opt/flutter-sdk/bin/flutter build apk --release --no-pub
   ```
   Plik wynikowy: `mobile/build/app/outputs/flutter-apk/app-release.apk`
3. Poinformuj uzytkownika o sciezce do APK do instalacji na telefonie.

**UWAGA srodowisko zdalne:** W srodowisku claude.ai/code (remote cloud) nie ma
Android SDK — `flutter build apk` nie zadzala (brak ANDROID_HOME). W takim
przypadku poinformuj uzytkownika, ze APK musi byc zbudowany lokalnie lub przez CI/CD
z danego brancha. Zawsze wykonaj przynajmniej `flutter analyze --no-pub`.

### Zawsze podawaj pelny link do aplikacji / brancha

Po kazdym pushu podaj uzytkownikowi pelny link do brancha na GitHub:
`https://github.com/mario453-tech/www/tree/<nazwa-brancha>`

Przyklad dla brancha roboczego:
`https://github.com/mario453-tech/www/tree/claude/restart-basch-process-omr7r7`

### Flutter SDK

Zainstalowany w `/opt/flutter-sdk`. Uzywaj pelnej sciezki:
- `analyze`: `/opt/flutter-sdk/bin/flutter analyze --no-pub`
- `build apk`: `/opt/flutter-sdk/bin/flutter build apk --release --no-pub`
- `pub get`: `/opt/flutter-sdk/bin/flutter pub get`

Jesli flutter nie dzala: `git config --global --add safe.directory /opt/flutter-sdk`
