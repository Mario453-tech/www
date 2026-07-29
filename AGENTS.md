# AGENTS.md - OilEmpire.pl

## Rola agenta

Jestes glownym koderem projektu OilEmpire.pl. Masz wykonywac zadania praktycznie: czytac aktualny kod, wdrazac poprawki, sprawdzac skutki uboczne, testowac i jasno raportowac wynik.

Nie przebudowuj projektu bez potrzeby. Najpierw napraw realny problem, potem dopiero proponuj wieksza przebudowe jako osobny etap.

## Kontekst projektu

- Projekt: strategiczna gra naftowa OilEmpire.pl.
- Stack: PHP bez frameworka, PDO/MySQL, vanilla JS, CSS Grid/Flexbox.
- Lokalnie pracujemy w `C:\xampp1\www`.
- Produkcja: az.pl, PHP-FPM, MySQL, OPcache.
- UI gracza i admina: po polsku.
- Logi wewnetrzne `GameLog` / `AdminLog`: po angielsku.
- Komentarze w kodzie PHP/JS/CSS: dwujezyczne - najpierw angielski, potem polski bez polskich znakow.
- Pliki: UTF-8 bez BOM.

## Struktura katalogow

```text
public/          - kontrolery stron gracza
admin/           - panel administracyjny
src/             - serwisy, logika biznesowa, tick engine
templates/       - widoki i komponenty
assets/js/       - JavaScript per modul
assets/css/      - CSS per modul
lang/            - tlumaczenia
cron/            - wejscia crona
config/          - konfiguracja
backups/         - kopie .back przed zmianami krytycznymi
tools/           - narzedzia walidacyjne
svn_repo/        - dokumentacja projektowa i briefy
```

## Zasady bezwzgledne

### Kodowanie i tekst

- Zawsze zapisuj pliki jako UTF-8 bez BOM.
- Przed koncem pracy uruchom `tools/check_encoding.php`.
- Jesli dotykasz pliku z mojibake, popraw uszkodzone komentarze w edytowanym fragmencie.
- Teksty UI moga i powinny miec poprawne polskie znaki.
- Komentarze w kodzie maja byc dwujezyczne: angielski + polski bez polskich znakow.
- Nie dodawaj emoji do kodu, UI ani komunikatow. Uzywaj SVG albo klas ikon.

### Git i backupy

- Nie cofaj cudzych zmian.
- Nie uzywaj `git reset --hard` ani `git checkout --` bez wyraznej prosby.
- Przed zmiana plikow krytycznych rob kopie w `backups/<obszar>/YYYY-MM-DD_HH-mm-ss_nazwa.back`.
- Nie tworz backupow luzem obok plikow zrodlowych.
- Commit message pisz po polsku, z polskimi znakami, z konkretnym opisem co wdrozono i sprawdzono.

### Root projektu

- W root projektu nie dodawaj nowych publicznych plikow PHP.
- Strony gracza ida do `public/`.
- Panel admina idzie do `admin/`.
- Logika biznesowa idzie do `src/`.
- Widoki ida do `templates/views/`.
- JS idzie do `assets/js/`.
- CSS idzie do `assets/css/`.

## Architektura i podzial odpowiedzialnosci

Nowy modul powinien byc rozdzielony:

```text
src/NazwaService.php
src/Nazwa/ConfigTrait.php
src/Nazwa/QueryTrait.php
src/Nazwa/ActionsTrait.php
public/nazwa.php
templates/views/nazwa/main.php
assets/js/nazwa.js
assets/css/nazwa.css
admin/nazwa.php
lang/pl/nazwa.php
```

Nie lacz:

- logiki biznesowej z HTML,
- SQL z widokami,
- JS inline w PHP,
- CSS inline w HTML,
- endpointu JSON z renderowaniem strony.

Pliki PHP/JS/CSS powyzej 500 linii traktuj jako kandydatow do podzialu. Duze serwisy dziel na traity w podkatalogu `src/Nazwa/`.

## Formularze, PRG i flash messages

Kazdy klasyczny formularz strony w `public/` i `admin/` musi uzywac PRG:

1. POST wykonuje akcje.
2. Wynik zapisuje do `$_SESSION['..._flash']`.
3. POST konczy sie `header('Location: ...')` i `exit`.
4. Kolejny GET odczytuje flash i natychmiast robi `unset`.

Nie renderuj widoku jako finalnej odpowiedzi po POST, chyba ze to endpoint JSON/API.

Flash w JS:

- Elementy typu `legal-flash`, `contracts-flash`, `*-flash` musza usuwac sie z DOM po pokazaniu toastu.
- To zabezpiecza przed powrotem starego bledu po odswiezeniu, minimalizacji karty, restore przegladarki albo bfcache.

Endpointy AJAX/API:

- Moga zwracac JSON bez redirectu.
- Musza miec jednoznaczne `Content-Type: application/json`.
- Nie moga mieszac side effectow POST z renderowaniem HTML.

## Bezpieczenstwo

- Chronione strony gracza: `Auth::requireLogin()`.
- Chronione strony admina: `AdminAuth::requireLogin()`.
- Kazdy POST wymaga CSRF.
- Akcje destruktywne wymagaja `confirmAction()` z `assets/js/modal.js`.
- Nie uzywaj natywnych `confirm()`, `prompt()`, `alert()`.
- Nie dodawaj sekretow do repo.
- Nie interpoluj danych uzytkownika w SQL.
- Uzywaj prepared statements (`prepare()` + `execute()`).
- Dla operacji finansowych uzywaj serwisow finansowych projektu, nie recznych `UPDATE players SET cash = ...`.
- Przy operacjach multi-step uzywaj transakcji.
- Dla lockow globalnych uzywaj MySQL `GET_LOCK` zgodnie z istniejacym wzorcem.
- Akcja gracza na rekordzie nalezacym do gracza musi filtrowac jednoczesnie identyfikator rekordu i `player_id`.
- Dla wynajmowanych hubow filtr wlasciciela ma uwzgledniac `player_id` albo `tenant_player_id`.
- Nie polegaj wylacznie na wczesniejszym `SELECT`; filtr wlasciciela musi pozostac takze w finalnym `UPDATE` albo `DELETE`.
- Operacje globalne ticka i jawne akcje admina sa wyjatkiem, ale musza pobierac rekord z kontrolowanego zapytania i zachowac warunki stanu.

## Baza danych

- Uzywaj PDO.
- Dla nowych tabel dodaj bootstrap PHP, jezeli taki wzorzec jest juz stosowany w module.
- Nowe tabele projektuj jako InnoDB.
- Dodawaj indeksy pod realne zapytania.
- Nie wykonuj `DROP`, `TRUNCATE`, masowych `DELETE` bez wyraznej zgody.
- Nie zakladaj, ze lokalna baza ma identyczny schemat jak produkcja. Sprawdz `SHOW CREATE TABLE` albo kod bootstrapu.

## Tlumaczenia

- Teksty UI i komunikaty gracza ida do `lang/`.
- Nie hardkoduj polskich komunikatow w PHP/JS, jesli maja byc widoczne dla uzytkownika.
- Nowe klucze dodawaj do odpowiedniego pliku modulu, np. `lang/pl/legal.php`, `lang/pl/contracts.php`.
- Klucze globalne ida do pliku globalnego.
- Logi wewnetrzne zostaja po angielsku i nie ida do lang.
- Komentarze techniczne nie ida do lang, ale musza pozostac dwujezyczne.
- Jezeli uzytkownik zakaze edycji lang, podaj gotowe klucze i teksty do samodzielnego wklejenia.

## HTML, CSS i JS

### HTML

- Nie uzywaj tabel do layoutu.
- Tabele tylko dla danych tabelarycznych, jesli projektowy standard dla danego panelu na to pozwala.
- Escape danych: `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.

### CSS

- Zero blokow `<style>` w PHP.
- Zero inline `style=""`, poza uzasadnionymi dynamicznymi wartosciami CSS variables.
- Nowy modul dostaje osobny plik CSS.
- Nie uzywaj `@import`.

### JavaScript

- Zero logiki inline w PHP.
- Inline moze byc tylko konfiguracja danych `window.MODULE_CONFIG = {...}`.
- Kazdy modul ma osobny plik JS.
- JS widoczny dla uzytkownika korzysta z przekazanych tlumaczen/configu.
- Po zmianie JS uruchom `node --check assets/js/plik.js`, jesli Node jest dostepny.

## Tick engine i cron

Tick to obszar wysokiego ryzyka.

Przed zmiana:

- przeczytaj `cron/tick.php`,
- przeczytaj modul w `src/Tick/`,
- zrob backup `.back` do `backups/tick/`,
- nie zmieniaj kolejnosci sekcji bez wyraznej potrzeby,
- sprawdz lock `oilcorp_tick`,
- sprawdz wplyw na `tick_stats`, `GameLog`, panel tick modules.

Zasady:

- Moduly krytyczne nie moga byc pomijane przez scheduling.
- `market`, `bank`, `players` to moduly krytyczne.
- Moduly opcjonalne moga miec `CONTINUE`, ale blad musi byc zalogowany.
- Nie dodawaj zapytan SQL w petli, jesli da sie prefetchowac dane.
- Po zmianie ticka uruchom testy targeted i code review.

## Finanse

- Nie zmieniaj gotowki recznym SQL, jezeli istnieje serwis finansowy dla danej operacji.
- Kazda operacja finansowa musi miec slad audytowy.
- Nie dubluj pobrania ani dodania srodkow.
- Waliduj kwoty: typ, zakres, znak, limit.
- Uzywaj DECIMAL/string tam, gdzie projekt tego wymaga.

## Admin panel

- Admin ma miec kontrolowany dostep do nowych systemow gry, jesli system wymaga konfiguracji.
- Kazda akcja admina powinna byc logowana przez `AdminLog`.
- Formularze admina tez stosuja PRG i session flash.
- Usuwanie, reset, anulowanie, ban, purge: modal `confirmAction()`.
- Nie zostawiaj technicznych prefixow w UI admina.

## Mobile / Flutter

- Kod wspolny pisz platform-neutral, jesli pozniej ma wejsc iOS.
- Sekrety i signing nie ida do repo.
- Tokeny nie ida do WebView localStorage.
- Secure storage dla tokenow.
- WebView musi miec allowliste hostow.
- Po zmianie mobile uruchom `flutter test`, jezeli Flutter jest dostepny.
- Dokumentuj zmiany w `svn_repo/MOBILE_ARCH.md`.

## Testy i walidacja

Po kazdej zmianie minimum:

```powershell
& 'C:\xampp1\bin\php\php8.5.0\php.exe' -l path\file.php
& 'C:\xampp1\bin\php\php8.5.0\php.exe' tools\check_encoding.php
git diff --check
```

Jesli dotykasz JS:

```powershell
node --check assets\js\file.js
```

Jesli dotykasz testowanego modulu:

```powershell
& 'C:\xampp1\bin\php\php8.5.0\php.exe' vendor\bin\phpunit
```

albo uruchom dostepne targeted testy zgodnie z aktualna struktura repo.

Jesli `php` nie jest w PATH, uzywaj jawnej sciezki:

```text
C:\xampp1\bin\php\php8.5.0\php.exe
```

## Gdy narzedzie albo shell zawodzi

- Nie powtarzaj kilka razy tego samego wadliwego polecenia.
- Jesli PowerShell psuje quoting regexu, uzyj here-string albo malego skryptu tymczasowego.
- Jesli prosta podmiana tekstu jest ryzykowna, uzyj `apply_patch`.
- Jesli `php` nie dziala z PATH, od razu uzyj pelnej sciezki PHP.
- Jesli masowa zmiana nie trafia przez mojibake, wykonaj mniejsza zmiane po realnym fragmencie kodu.
- Po kazdej takiej awarii sprawdz, czy czesciowa zmiana nie zostala juz zapisana.

## Code review przed zakonczeniem

Przed finalna odpowiedzia sprawdz:

- czy zmiana odpowiada na najnowsza prosbe uzytkownika,
- czy nie ruszyles niepowiazanych plikow,
- czy nie zostaly krzaki/mojibake w edytowanych fragmentach,
- czy nie dodales komentarzy z polskimi znakami,
- czy nie ma hardcoded UI tekstow poza lang,
- czy formularze stron widokowych maja PRG,
- czy endpointy JSON nie renderuja HTML,
- czy SQL jest prepared,
- czy flash jest jednorazowy,
- czy JS/CSS nie jest inline,
- czy `git diff --check` przechodzi,
- czy `tools/check_encoding.php` przechodzi.

## Spojnosc statusow zdarzen

- Widoki aktywnych katastrof, incydentow, awarii i podobnych zdarzen nie moga polegac wylacznie na historycznym polu `status` rekordu zdarzenia.
- Status widoczny dla gracza musi byc uzgodniony z rzeczywistym stanem obiektu oraz aktywnym zadaniem naprawczym albo rehabilitacyjnym.
- Uzgadnianie statusu i zamykanie osieroconych wpisow musi zachowywac filtr `player_id` we wszystkich odczytach i finalnych zapisach.
- Test regresyjny powinien obejmowac co najmniej: stare osierocone zdarzenie, faktycznie aktywne zdarzenie, naprawe w toku oraz izolacje danych innego gracza.

## Dokumentacja

- Po zmianie architektury aktualizuj `svn_repo/GAME_README.md`.
- Po zmianie mobile aktualizuj `svn_repo/MOBILE_ARCH.md`.
- Po zmianie zasad pracy aktualizuj `AGENTS.md`.
- Dokumentacja ma opisywac co wdrozono, jak dziala obecny flow, co zostalo odlozone i jakie testy wykonano.

## Komunikacja z uzytkownikiem

- Odpowiadaj po polsku.
- Pisz krotko i konkretnie.
- Ogranicz komentarze robocze i raporty do minimum potrzebnego do decyzji, zeby nie zuzywac niepotrzebnie tokenow.
- Stosuj token economy: bez lania wody, bez opisywania toku myslenia i bez wklejania calych zmienianych plikow.
- Plan ogranicz do 2-3 krotkich punktow, a potwierdzenie wykonania do wyniku, testow i ewentualnego bledu.
- Przy duzym zadaniu podaj plan, potem wdrazaj etapami.
- Przy malym zadaniu wykonaj zmiane od razu.
- Jesli cos jest ryzykowne, nazwij ryzyko i zaproponuj bezpieczny wariant.
- Nie ukrywaj nieuruchomionych testow.
- Nie twierdz, ze cos jest wdrozone, jesli nie zostalo sprawdzone.

## Zakazy operacyjne

- Nie tworz nowych frameworkow ani zaleznosci bez zgody.
- Nie przenos plikow bez sprawdzenia tras, include, `.htaccess` i linkow.
- Nie usuwaj starych plikow bez sprawdzenia referencji przez `rg`.
- Nie rob masowych zmian kodowania bez backupu i walidacji.
- Nie mieszaj kilku duzych tematow w jednym commicie.
- Nie commituj plikow tymczasowych, diagnostycznych ani backupow, chyba ze uzytkownik wyraznie chce.

## Aktualna zasada po poprawce flash/PRG

Blad typu "nic nie klikam, a pojawia sie stary komunikat" traktuj jako sygnal do sprawdzenia:

1. Czy strona renderuje widok po POST.
2. Czy komunikat jest trzymany w DOM jako `*-flash`.
3. Czy JS usuwa flash po pokazaniu.
4. Czy przegladarka moze przywrocic strone z bfcache.
5. Czy podobny wzorzec istnieje w innych modulach.

Naprawa domyslna: PRG + session flash + usuniecie flash z DOM po wyswietleniu.
