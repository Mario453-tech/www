# Audyt szczegółowy 102 — partia H: wymuszenie ticka i 2FA administratora

Data: 2026-10-30. Gałąź `feature/oilcorp-laravel-audit-baseline`. Pełny odczyt `admin/force_tick.php` (SHA `bcda59db6dbd4b78e89c47b63bb537ab1106a6ae`, 77 linii) i `admin/2fa.php` (`a1dfcb917cbedb21c55a76f4829116cba090a702`, 211 linii). Powiązania sprawdzone we wskazanych fragmentach `src/AdminAuth.php` (SHA `e1d888dcd78740d0bfae11d43b35ad17e597aa47`) i `src/Tick/TickCoordinator.php`; pełny audyt tych klas oraz testy runtime pozostają otwarte.

## H01 — `admin/force_tick.php`

Wejście: POST po `AdminAuth::requireLogin` (4–11), CSRF (14–19), cooldown 5 sekund zapisany w `$_SESSION['force_tick_last']` (21–28). Akcja (30–73) wywołuje `TickCoordinator::run('admin_force', null, true)`, który używa blokady `oilcorp_tick`; statusy busy/fail/partial/success trafiają do sesji i `AdminLog`. Przekierowanie po akcji wykorzystuje `HTTP_REFERER` (23, 75). Graf danych: `force_tick.php` → `TickCoordinator` → `TickEngine` → moduły → `well_config`, `market_state`, tabele graczy i logi ticka. Formularz wywołujący, widok komunikatu i test HTTP wymagają osobnego powiązania.

**P1 — globalny tick dostępny każdemu zalogowanemu administratorowi, bez osobnego uprawnienia operacyjnego.** W pliku jest tylko `requireLogin`, brak kontroli roli dla operacji zmieniającej gospodarkę wszystkich graczy (4–7, 30–32). To luka polityki uprawnień do rozstrzygnięcia: jeśli wszyscy administratorzy są celowo równi, nie jest błędem względem obecnego modelu; migracja musi jawnie zachować lub zmienić tę decyzję. Test: administrator o najniższych uprawnieniach i próba POST.

**P2 — cooldown sesyjny nie stanowi globalnego limitu wymuszania ticka.** `force_tick.php:21-28`; wiele sesji/adminów może przejść kontrolę, choć koordynator serializuje same wykonania. Test: dwa konta/sesje z poprawnym CSRF, powtarzanie ticka po zwolnieniu blokady.

**P2 — przekierowanie oparte na niezweryfikowanym `HTTP_REFERER`.** `force_tick.php:23,75` może wysłać administratora pod obcy adres po POST. Wymaga potwierdzenia przeglądarkowego; bezpieczny cel to lokalna ścieżka panelu.

**P2 — komunikat błędu zawiera tekst wyjątku.** `force_tick.php:42-61,71-73` zapisuje go w sesji i logu admina; może ujawniać szczegóły zapytań/schematu zalogowanym administratorom. Oczekiwany kontrakt migracyjny: publiczny identyfikator błędu, szczegóły w prywatnej telemetrii.

## H02 — `admin/2fa.php`

Wejście: oczekujący etap logowania w `AdminAuth::getPending` (18–23), tryb setup/verify (25–26), zaufane urządzenie (31–38). W setup sekret TOTP jest tworzony raz w sesji (40–47), weryfikowany i zapisywany w `admins.totp_secret, totp_enabled` przez `AdminAuth::enableTotpForPending` (107–117; `src/AdminAuth.php:187-208`). W trybie verify sekret jest pobierany z DB dla każdego POST (77–100). Błędny kod zwiększa licznik sesyjny i `admin_login_attempts` przez `logTotpFail` (`src/AdminAuth.php:523-549`). Widok zawiera formularz kodu i opcję zaufanego urządzenia (173–194), formularz anulowania (196–201), ładuje `admin.css`, `admin_auth.css`, `admin-2fa.css` (145–147). Powiązane zasoby CSS i przepływ przeglądarkowy czekają na przegląd.

**P2 — ochrona przed ponownym użyciem kodu TOTP jest sesyjna.** `admin/2fa.php:83-88` porównuje licznik tylko z `$_SESSION['admin_pending']['last_totp_counter']`; `AdminAuth::completeLogin` usuwa pending (`src/AdminAuth.php:167-175`). Kod z tego samego okna może być ponownie użyty w innej poprawnie uwierzytelnionej sesji, jeśli pozostałe warunki logowania są spełnione. To ograniczenie opisanej ochrony, nie dowód obejścia hasła. Test na dwóch odrębnych sesjach z tym samym administratorem i tym samym kodem; decyzja, czy wymagana jest globalna jednokrotność, należy do polityki 2FA.

**P1 do oceny polityki — cookie zaufanego urządzenia pozwala pominąć hasło i TOTP.** `admin/2fa.php:31-38` pomija formularz po `checkTrustedDevice`; `src/AdminAuth.php:347-352` ma także `tryTrustedDevice` bez wcześniejszego hasła. Token jest haszowany w DB, cookie ma HttpOnly, SameSite Lax i Secure zależne od HTTPS (`src/AdminAuth.php:612-647`). Dla migracji trzeba zachować świadomie ustaloną semantykę i przetestować odwołanie, kradzież/wygaśnięcie tokenu, reset hasła, zmianę 2FA i ograniczenia urządzenia. Sama obecność tej funkcji nie oznacza podatności.

## Testy i granice

Nie uruchomiono testów jednostkowych, integracyjnych, E2E ani wizualnych; nie wywoływano globalnego ticka i nie używano produkcyjnej bazy. W rejestrze istnieją testy blokady koordynatora i izolacji sesji admina, ale bez ich odczytu i uruchomienia nie przypisano im pokrycia powyższych scenariuszy. Oba pliki uzyskują w trackerze `SOURCE_READ_DEPENDENCIES_OPEN`, bez zmiany ich statusu w głównym rejestrze.