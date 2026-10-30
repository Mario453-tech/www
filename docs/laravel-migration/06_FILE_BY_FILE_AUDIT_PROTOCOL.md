# Protokół kontroli plik po pliku — OilCorp 2.0

## Zakres
Źródło: Git `main` @ `7521a97aab762c1ff4dd27b249b78778922cb5ed`. Rejestr `02_ALL_TRACKED_FILES.tsv` obejmuje 5133 śledzonych plików. Dotąd 98 plików ma status co najmniej kontroli statycznej (29 przeglądów krytycznych, 34 pozostałe kontrolery admina, 35 widoków admina). **5035 plików jeszcze nie sprawdzono indywidualnie**. Raporty uzupełniające: `07_EVIDENCE_ADDENDUM_B.md` oraz `08_MOBILE_AUTH_FILE_REVIEW_C.md` oraz `09_API_LIMITER_MARKET_FILE_REVIEW_D.md`. Statusy nie oznaczają pełnej weryfikacji zależności ani działania. Dla żadnej funkcji nie odnotowano nowego, wykonanego w tej serii testu runtime.

## Procedura każdej pozycji rejestru

1. Zapisz commit i SHA blobu, dokładną ścieżkę, typ artefaktu, rozmiar i kategorię.
2. Przeczytaj cały plik. Przy dużych plikach analizuj kolejne zakresy, zapisując postęp — nie oznaczaj jako ukończone na podstawie fragmentu.
3. Zidentyfikuj cel pliku, jego właściciela domenowego i rzeczywisty punkt wejścia; sprawdź, czy jest nadal wykorzystywany.
4. Wypisz importy, wywołania, wymagane szablony, skrypty i style, zależności od klas, funkcji i konfiguracji.
5. Wypisz tabele, kolumny, klucze, relacje, triggery, indeksy, efekty uboczne, transakcje, blokady oraz ścieżki zapisu.
6. Dla kontrolera lub widoku sprawdź każdy formularz, button, akcję JS, trasę, API, walidację, CSRF, uprawnienie i rzeczywisty wynik w testowej bazie.
7. Dla elementu graficznego zapisz źródłową ścieżkę, użycie, warianty językowe i ekranowe, format, rozmiar, możliwość uploadu; sprawdź zgodność wizualną przed i po migracji.
8. Dla modułu tick sprawdź wejście/wyjście, sekwencję, timeout, lock, możliwość ponownego wykonania, niezmienniki finansowe i zależności od tabel.
9. Dla testu automatycznego sprawdź, czy uruchamia prawdziwy serwis/bazę/endpoint czy mocki, czy wykrywa umyślnie wprowadzony błąd oraz czy jest uruchamiany w CI.
10. Wpisz dowód: ścieżkę, dokładne linie, SHA, nazwę testu, rzeczywisty wynik i datę uruchomienia. Nie oznaczaj **DZIAŁA** na podstawie istnienia kodu.
11. Dopiero po audycie określ decyzję migracyjną: **przenieść bez zmiany**, **opakować adapterem**, **przeprojektować**, **naprawić**, **wycofać z użycia**; każdą propozycję zmiany tabel poprzedź analizą danych i planem rollbacku.

## Statusy
- `NOT_REVIEWED` — obecność w repo jedynie zindeksowana.
- `ADMIN_CONTROLLER_STATIC_SCAN` — wykryto kontrolę logowania, post/CSRF, widok i odwołania do assetów na poziomie tekstu.
- `ADMIN_VIEW_STATIC_SCAN` — zliczono formularze/przyciski/zdarzenia, sprawdzono literalne ścieżki assetów.
- `CRITICAL_STATIC_REVIEW` — osobny raport plikowy z opisem zależności i zagrożeń.
- `FULL_SOURCE_REVIEW` — zakończony przegląd całości pliku, potwierdzono powiązania po obu stronach.
- `RUNTIME_TEST_PASS` — uruchomione testy na izolowanym środowisku z dowodami.
- `RUNTIME_TEST_FAIL` — potwierdzony błąd z możliwością odtworzenia.
- `MIGRATION_VERIFIED` — zgodność danych i funkcji starej oraz nowej wersji potwierdzona.

Statusów nie nadpisywać na zielono tylko dlatego, że GitHub Actions wykonał się z sukcesem.

## Kolejność audytu

**P0 (najpierw):** poufne i runtime artefakty Git, publiczne logi; konto gracza i administratora, tokeny; `full_wipe`; finanse, transakcje, CRON/tick, mechanizmy zmian schematu oraz backup/rollback.

**P1:** wszystkie kontrolery, serwisy oraz E2E panelu gracza i administratora; mapowanie akcji do DB i wpływu na tick; mobile API i kompatybilność logowania.

**P2:** pełna inwentaryzacja widoków PHP/Blade, komponentów, CSS, JS, SVG/PNG/JPG i plików dodawanych przez upload; porównania wizualne desktop/tablet/mobile.

**P3:** dokumentacja, tłumaczenia, narzędzia, testy, skrypty migracyjne, zależności; stare backupy i vendor klasyfikować przez pochodzenie, retencję, zgodność licencji i skan podatności, a nie przepisywać bibliotekę linia po linii.

## Bramki bezpieczeństwa

- Nie używać produkcyjnej DB w testach i nie uruchamiać w niej resetów, seedów, `migrate:fresh`, `TRUNCATE` ani destrukcyjnych scenariuszy.
- Stworzyć izolowany, ograniczony dostępem staging na odtworzonej **zanonimizowanej** kopii danych; dodatkowo zabezpieczyć oryginalny backup w niedostępnym publicznie magazynie.
- Produkcja nie powinna otrzymać kodu z gałęzi audytowej automatycznie; PR pozostaje wersją roboczą, bez merge.
- Przed cutover ustalić właściciela jednego procesu zapisującego i harmonogram zamrożenia ticków, finalną synchronizację i kryteria przywrócenia bez utraty nowych zapisów.
- Wersjonowanie istniejącego schematu jest odrębną decyzją — Laravel nie wymusza nazw `users` ani przebudowy `players`.

## Kryterium zamknięcia audytu
Dla **każdego autorskiego pliku wykonywalnego i widoku** wskazana decyzja migracyjna, zweryfikowana lista zależności, status testów i znane luki. Dla każdego formularza oraz akcji admina — test na kopii DB, pozytywny i negatywny przypadek oraz wynik. Dla uploadów i grafik — inwentaryzacja zasobów z repo i serwera. Dla bibliotek zewnętrznych — SBOM / skan wersji i licencji; dla kopii — kontrola retencji i braku publicznej ekspozycji. Bez tego nie deklarować zakończenia audytu.
