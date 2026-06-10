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

Po każdej zmianie pliku ZAWSZE rób commit i push bezpośrednio do `main`.
Nie używaj feature branchy — każda zmiana musi od razu trafić na `main`,
żeby GitHub Actions wydeployował ją na serwer.

Kolejność zawsze:
1. Zrób backup (patrz niżej)
2. Wprowadź zmiany
3. Zweryfikuj kodowanie
4. `git add` zmienionych plików + backupów
5. `git commit -m "..."` — opis **szczegółowy**: co konkretnie dodano lub jaki problem rozwiązano.
   - Pierwsza linia: krótkie podsumowanie (max ~72 znaki), np. `Wallet: naprawiono duplikat PLN PLN w dialogu potwierdzenia`
   - Kolejne linie (po pustej): szczegółowy opis — co było źródłem problemu, co zmieniono i dlaczego.
   - Przykład dobrego opisu:
     ```
     Admin transport: czyszczenie well_road_trips + modalne potwierdzenia

     - Dodano handler clear_road_trips (stuck/all) analogiczny do clear_marine_deliveries.
     - Zamieniono natywne confirm() na confirmSubmit() z modal.js — wymóg CLAUDE.md.
     - Każdy przycisk ma własny formularz z ukrytym clear_scope, bo form.submit() nie
       przenosi wartości name/value z przycisku po potwierdzeniu w modalu.
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
