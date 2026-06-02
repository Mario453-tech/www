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

## Kodowanie i komentarze — ZASADA OBOWIĄZKOWA

Przy KAŻDEJ zmianie istniejącego pliku ORAZ przy tworzeniu nowego pliku
obowiązuje poniższy standard. Always check this on every file change/creation.

1. **UTF-8 bez BOM** — zawsze. Nigdy nie zapisuj znacznika BOM (`EF BB BF`) na
   początku pliku. / Always UTF-8, never write a BOM.
2. **Bez krzaków (mojibake)** — żadnych uszkodzonych znaków: znak `�` (U+FFFD)
   ani podmienionych polskich liter (np. `Zarz�du`, `pracownik�w`, `Dzia prawny`
   zamiast `Dział prawny`). Jeśli natrafisz na krzaki w pliku, który ruszasz —
   napraw je na poprawne UTF-8. / No mojibake; fix any you touch.
3. **Komentarze dwujęzyczne** — komentarze ZOSTAWIAMY po polsku (z poprawnymi
   polskimi znakami) ORAZ dodajemy wersję angielską. Polskich znaków nie usuwamy
   i nie psujemy. / Keep Polish comments (correct diacritics) and add an English
   version next to them.
4. **Zawsze weryfikuj po zmianie** — po każdej edycji/utworzeniu pliku sprawdź
   kodowanie. Always verify after each change, e.g.:
   - BOM (musi być puste / must be empty):
     `head -c3 PLIK | od -An -tx1` — nie może zwrócić `ef bb bf`
   - Krzaki (musi być puste / must be empty):
     `grep -nP '\xEF\xBF\xBD' PLIK`
   - Poprawność UTF-8:
     `php -r 'echo mb_check_encoding(file_get_contents("PLIK"),"UTF-8")?"OK\n":"ZLE\n";'`
