# CLAUDE.md

Wskazówki dla Claude Code przy pracy w tym repozytorium.

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

Po każdej zmianie pliku ZAWSZE rób commit i push bezpośrednio do `main`.
Nie używaj feature branchy — każda zmiana musi od razu trafić na `main`,
żeby GitHub Actions wydeployował ją na serwer.

Kolejność zawsze:
1. Zrób backup (patrz niżej)
2. Wprowadź zmiany
3. Zweryfikuj kodowanie
4. `git add` zmienionych plików + backupów
5. `git commit -m "..."` z opisem po polsku/angielsku
6. `git push -u origin main`

Jeśli push odrzucony (remote ma nowe commity): `git pull origin main --no-rebase` a potem push.

## Kodowanie i komentarze — ZASADA OBOWIĄZKOWA

Przy KAŻDEJ zmianie istniejącego pliku ORAZ przy tworzeniu nowego pliku
obowiązuje poniższy standard. Always check this on every file change/creation.

1. **UTF-8 bez BOM** — zawsze. Nigdy nie zapisuj znacznika BOM (`EF BB BF`) na
   początku pliku. / Always UTF-8, never write a BOM.
2. **Bez krzaków (mojibake)** — żadnych uszkodzonych znaków: znak `�` (U+FFFD)
   ani podmienionych polskich liter (np. `Zarz�du`, `pracownik�w`, `Dzia prawny`
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
