# CLAUDE.md - OilEmpire.pl

Ten plik jest skrotem dla Claude Code. Pelne zasady sa w `AGENTS.md`.

W razie konfliktu zawsze obowiazuje `AGENTS.md`.

## Najwazniejsze zasady

- Przed praca przeczytaj `AGENTS.md`.
- Po zmianie architektury lub zasad pracy aktualizuj `AGENTS.md`.
- Po zmianie funkcji gry aktualizuj `svn_repo/GAME_README.md`.
- Nie przebudowuj projektu bez wyraznej potrzeby.
- Nie cofaj cudzych zmian.
- Nie ruszaj niepowiazanych plikow.

## Sciezki projektu

- Strony gracza: `public/`
- Panel admina: `admin/`
- Logika biznesowa: `src/`
- Widoki: `templates/views/`
- Komponenty: `templates/components/`
- JavaScript: `assets/js/`
- CSS: `assets/css/`
- Tlumaczenia: `lang/`
- Cron/tick: `cron/`, `src/Tick/`
- Dokumentacja: `svn_repo/`
- Backupy: `backups/`

Nie dodawaj nowych publicznych plikow PHP w root projektu.

## Kodowanie

- Wszystkie pliki zapisuj jako UTF-8 bez BOM.
- Teksty UI moga miec poprawne polskie znaki.
- Komentarze w kodzie PHP/JS/CSS pisz po angielsku i bez polskich znakow.
- Nie dodawaj emoji. Zamiast emoji uzywaj SVG albo klas ikon.
- Jezeli edytujesz fragment z mojibake, popraw go w tym fragmencie.

## PHP i architektura

- Logika biznesowa idzie do `src/`.
- Kontrolery stron ida do `public/` albo `admin/`.
- HTML idzie do `templates/views/`.
- SQL nie moze byc w widokach.
- Uzywaj PDO prepared statements.
- Kazdy POST musi miec CSRF.
- Chronione strony gracza wymagaja `Auth::requireLogin()`.
- Chronione strony admina wymagaja `AdminAuth::requireLogin()`.
- Operacje finansowe musza isc przez serwisy finansowe projektu, nie przez reczne `UPDATE players SET cash = ...`.

## Formularze i flash

Kazdy klasyczny formularz strony w `public/` i `admin/` musi dzialac przez PRG:

1. POST wykonuje akcje.
2. Wynik zapisuje do `$_SESSION['..._flash']`.
3. POST konczy sie `header('Location: ...')` i `exit`.
4. GET odczytuje flash i natychmiast robi `unset`.

Nie renderuj widoku jako finalnej odpowiedzi po POST, chyba ze to endpoint JSON/API.

Elementy JS typu `*-flash` musza usunac sie z DOM po pokazaniu komunikatu.

## JavaScript i CSS

- Nie dodawaj logiki JS inline w PHP.
- Inline moze byc tylko konfiguracja danych, np. `window.MODULE_CONFIG`.
- Nie uzywaj natywnych `confirm()`, `prompt()`, `alert()`.
- Uzywaj `confirmAction()`, `promptInput()`, `alertInfo()`, `alertError()`, `alertWarning()`, `showGameToast()`.
- Nie dodawaj `<style>` w PHP.
- Nie dodawaj inline `style=""`, poza uzasadnionymi dynamicznymi CSS variables.
- Nowe moduly dostaja osobne pliki JS/CSS.

## Tlumaczenia

- Teksty widoczne dla gracza/admina ida do `lang/`.
- Nie hardkoduj polskich komunikatow w PHP/JS.
- Logi wewnetrzne `GameLog` i `AdminLog` zostaja po angielsku i nie ida do lang.
- Jezeli uzytkownik zakaze edycji lang, przygotuj gotowe klucze i teksty do wklejenia.

## Backupy

- Backup rob tylko wtedy, gdy zmieniasz plik krytyczny albo uzytkownik tego oczekuje.
- Backup zapisuj w `backups/<obszar>/`.
- Rozszerzenie backupu: `.back`.
- Nie tworz backupow obok plikow zrodlowych.
- Nie commituj backupow, chyba ze uzytkownik wyraznie tego chce.

## Tick engine

Tick jest obszarem wysokiego ryzyka.

Przed zmiana ticka:

- przeczytaj `cron/tick.php`,
- przeczytaj odpowiednie pliki `src/Tick/`,
- zrob backup `.back` do `backups/tick/`,
- nie zmieniaj kolejnosci sekcji bez wyraznego powodu,
- sprawdz lock `oilcorp_tick`,
- uruchom targeted testy i code review.

Moduly krytyczne nie moga byc pomijane przez scheduling. `market`, `bank`, `players` sa krytyczne.

## Narzedzia i testy

Uzywaj jawnej sciezki PHP, jezeli `php` nie dziala z PATH:

```powershell
& 'C:\xampp1\bin\php\php8.5.0\php.exe' -l path\file.php
& 'C:\xampp1\bin\php\php8.5.0\php.exe' tools\check_encoding.php
git diff --check
```

Dla JS:

```powershell
node --check assets\js\file.js
```

Jesli PowerShell psuje quoting, nie powtarzaj tego samego polecenia. Uzyj here-string, malego skryptu tymczasowego albo `apply_patch`.

## Code review przed koncem

Sprawdz:

- czy zmiana odpowiada na najnowsza prosbe,
- czy nie ma mojibake w edytowanych fragmentach,
- czy komentarze kodu sa po angielsku i bez polskich znakow,
- czy teksty UI sa w lang,
- czy formularze stron maja PRG,
- czy endpointy JSON nie renderuja HTML,
- czy SQL jest prepared,
- czy JS/CSS nie jest inline,
- czy flash jest jednorazowy,
- czy `tools/check_encoding.php` przechodzi,
- czy `git diff --check` przechodzi.

## Komunikacja

- Odpowiadaj po polsku.
- Pisz krotko i konkretnie.
- Nie ukrywaj nieuruchomionych testow.
- Nie twierdz, ze cos jest wdrozone, jezeli nie zostalo sprawdzone.
