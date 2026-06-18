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
