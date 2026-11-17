# OilCorp 2.0 — zasady bezpiecznej migracji do Laravel

Status: audyt wstępny na podstawie kodu repozytorium `Mario453-tech/www`, bez dostępu do produkcyjnej bazy, bez uruchomienia aplikacji i bez zmian produkcyjnych.

## Cel i nienaruszalne warunki

1. Zachować konta graczy, ich identyfikatory `players.id`, istniejące hashe haseł, e-mail, status weryfikacji i powiązania przez `player_id`.
2. Zachować stan gospodarki, salda, transakcje, odwierty, magazyny, transport, personel, historię i konfigurację ticków; nie zerować postępu.
3. Zachować istniejące widoki, CSS, JavaScript, ikony, tła, grafikę, menu i układ mobilny; zmiana frameworka nie jest automatycznym redesignem.
4. Zachować funkcjonalność panelu administratora oraz 2FA, blokady, uprawnienia, audyt i operacje GM. Wszystkie operacje destrukcyjne migrować jako ostatnie.
5. Zabezpieczyć aplikację mobilną oraz dotychczasowe API; zdefiniować kompatybilność adresów i tokenów.
6. Żadnego zapisu do produkcyjnej bazy podczas audytu, implementacji ani prób migracji; wdrożenie dopiero po odrębnej weryfikacji.

## Co potwierdzono w kodzie

- `src/Auth.php` używa `players`, `password_verify()` i BCrypt przy rejestracji; kolumna hasła to `password_hash`. To przemawia za zachowaniem hashy, ale trzeba przetestować wszystkie historyczne konta.
- `src/AdminAuth.php` ma osobne uwierzytelnianie administratora, ograniczanie prób logowania i TOTP/2FA. Nie łączyć mechanicznie tabel `admins` i `players`.
- `admin/partials/header.php` definiuje 36 pozycji menu administracyjnego. `admin/` zawiera również dodatkowe akcje i strony poza menu.
- `templates/views/admin/` ma 44 katalogi widoków, w tym widoki dla banku, HR, ticków, edytora wyglądu i graczy.
- `assets/css/` ma 82 pozycje, `assets/js/` ma 77. Istnieją grafiki `assets/images/oilcorp_bg.png`, `oilempire_bg.png`, `boardroom_bg*.png`, a także zasoby `assets/images/home/` i katalogi ikon.
- `admin/template_editor.php` zarządza konfiguracją wyglądu, nawigacją i wgrywaniem grafik; zasoby tworzone przez użytkowników na serwerze mogą nie występować w repozytorium.
- `src/Tick/TickCoordinator.php`, `src/Tick/` i `cron/tick.php` obsługują silnik cykliczny. Przenoszenie ticków bez porównania wyników stwarza ryzyko ekonomiczne.
- `tests/ci-schema.sql` istnieje, lecz nie można uznać go za pewną kopię aktualnego schematu produkcji; dokumentacja `MODULE_CHECKLIST.md` wspomina wcześniejszą rozbieżność API i schematu.
- `tests/admin_dashboard_browser.cjs` używa `fixture.test` i stubów odpowiedzi API: weryfikuje część zachowania UI, nie jest pełnym testem live.
- `tests/MySqlIntegration/MySqlTransactionRacesTest.php` zawiera testy współbieżności i wycofywania transakcji; należy je zachować i rozszerzyć.
- `admin/gm_tools.php` zawiera `full_wipe` oraz inne operacje kasujące; to obszar krytyczny wymagający dodatkowych ograniczeń i oddzielnych testów.

## Docelowy wzorzec

Modularny monolit Laravel; moduły domenowe: Auth, Admin, Finance/Bank, Market, Wells/Production, Logistics, HR, Legal, Contracts, Tick, Messaging/Content, API/Mobile. Nazwy modułów są robocze, granice zależności muszą wynikać z audytu wywołań, a nie tylko katalogów.

Nie wymieniać nazw tabel, kluczy ani kolumn dla samej zgodności z konwencjami Eloquent. Dla modeli można wskazać własną tabelę, klucz główny, relacje i sposób obsługi znaczników czasu. Nie uruchamiać `migrate:fresh`, `migrate:refresh`, resetujących seedów ani `DROP` / `TRUNCATE` na danych graczy.

Zacząć od niezmiennego snapshotu schematu z kontrolowanego eksportu produkcji (`SHOW CREATE TABLE`, indeksy, FK, triggery, silniki, charsety), zanonimizowanej kopii do testów i manifestu wszystkich tabel oraz wolumenów. Obecny SQL w repozytorium jest materiałem porównawczym, nie źródłem prawdy o produkcji.

Nowe tabele tylko po udokumentowaniu potrzeby (np. `migrations`, techniczny scheduler, deduplikacja zadań). Dla istniejących tabel najpierw warstwa zgodności, potem małe migracje addytywne z testem odwrócenia. Trzeba uważać na istniejące mechanizmy `ensureSchema()` wykonujące DDL w czasie żądań.

## Strategia wizualna

1. Zarchiwizować wersję wszystkich `assets/css`, `assets/js`, `assets/icons`, `assets/images`, `templates/`, konfiguracji wyglądu z bazy i wgrywanych plików na produkcji.
2. Przenieść istniejący HTML do layoutów i częściowych widoków Blade z zachowaniem klas CSS, identyfikatorów DOM i ścieżek assetów; nie przepisywać jednocześnie JS.
3. Zachować osobne layouty gracza i administratora; uwzględnić edytor menu i tła sali zarządu oraz CSS modułowe.
4. Uruchomić wizualne testy porównawcze stary vs nowy: 1440 px, tablet, 390 px i 320 px; sprawdzić interakcje klawiaturą oraz obrazy.
5. Sprawdzać ładowanie każdego istniejącego assetu, zmienne konfiguracji zapisane w DB i pliki uploadowane poza repozytorium.

## Etapy i bramki akceptacji

**E0 — inwentaryzacja:** pełna lista tabel, FK, danych, endpointów, kontrolerów i podstron admina. Dla każdej funkcji: kontroler → serwis → tabele → widok → JS → test. Statusy oddzielone: `kod połączony`, `test automatyczny`, `sprawdzone w przeglądarce`, `brak potwierdzenia`, `błąd`.

**E1 — kopia i kompatybilność:** odtwarzalny backup i izolowana baza testowa. Dla zanonimizowanych użytkowników: poprawne logowanie istniejącym hashem, statusy kont, 2FA admina, relacje i dane po migracji. Porównanie liczby rekordów i sum kontrolnych dla tabel krytycznych.

**E2 — szkielet Laravel:** dopiero po E0/E1 dodać framework i adaptery do istniejącej bazy. Na początek samo logowanie oraz układ wizualny bez przełączania silnika ticków. Osobne środowisko i gałąź, żadnych zmian w głównej aplikacji.

**E3 — moduły:** przenosić po jednym module i porównywać wynik operacji ze starą aplikacją, w tym rollback, idempotencja, wyścigi, uprawnienia, edge cases, kontrakty mobilne.

**E4 — panel admina i grafika:** każdy przycisk i formularz w macierzy admina sprawdzić na realnej testowej bazie; porównać ekrany i zasoby.

**E5 — cutover:** zamrożenie zapisów i ticków w starej wersji; finalny snapshot, ostatnia synchronizacja, kontrola integralności, przełączenie tylko jednego silnika zapisującego, smoke test i monitoring. Cofnięcie po przyjęciu nowych zapisów wymaga ich odtworzenia lub uzgodnienia — sam stary backup nie wystarcza.

## Otwarte pytania techniczne do rozstrzygnięcia w audycie

- Aktualny produkcyjny `SHOW CREATE TABLE` dla każdej tabeli, FK, indeksy i triggers, a także wersja serwera i PHP.
- Czy w bazie są historyczne hashe inne niż obecny BCrypt oraz aktywne tokeny/sesje wymagające przeniesienia.
- W jakim katalogu na serwerze są realne pliki uploadowane w panelu i jakie ścieżki przechowuje DB.
- Które strony, akcje admina i endpointy są używane, a które są pozostałościami lub przekierowaniami.
- Jakie są wyniki aktualnych CI i rzeczywistych testów E2E przeciwko bazie testowej.

## Zakaz interpretacyjny

Obecność kontrolera, formularza, testu czy odwołania do serwisu NIE oznacza, że funkcja działa produkcyjnie. Dokument stanowi pierwszy etap audytu statycznego, nie certyfikat działania.