# Audyt plikowy C — mobilne API uwierzytelniania

Data: 2026-10-30. Źródło: `feature/oilcorp-laravel-audit-baseline`. Odczytano w całości cztery pliki: `api/v1/_bootstrap.php` (SHA `8c93e780334b4f9d179cc6b04ee33bae04417a44`), `api/v1/auth/login.php` (`eac860c96cbb0608c3992c9db61bd9b96bd9bfbe`), `api/v1/auth/logout.php` (`2db8ac747bb0b3e9bda191410fd2ce21452e7143`) i `src/ApiAuth.php` (`993cfe5a94ff90aea5fbfd226502724fbace38c0`). Przejrzano wskazane fragmenty `src/Auth.php` (`bf0a4ca60bab67e626136536890bf7f45cd198db`), bez awansowania całego pliku do `FULL_SOURCE_REVIEW`. Testów runtime nie uruchamiano.

## Macierz wywołań i danych

| Wejście | Funkcja | Tabele i kolumny | Wynik / kontrakt | Widok / JS / test |
| --- | --- | --- | --- | --- |
| `POST /api/v1/auth/login` | `ApiAuthRateLimiter::consume`, `ApiAuth::generateToken` | `players.id, username, password_hash, status, email_verified, last_login_at`; `api_tokens.player_id, token, device, created_at, expires_at`; tabela limitera do potwierdzenia | JSON: `token, player_id, username, expires_in_days`; błędy 400/401/403/405/429 | klient Flutter, konkretne wywołanie i test kontraktu: niezweryfikowane |
| `POST/DELETE /api/v1/auth/logout` | `apiRequireAuth`, `ApiAuth::revokeToken` | `api_tokens.token, last_used_at`; odczyt `players` | JSON `success, message`; 401/405 | klient/test: niezweryfikowane |
| Każde żądanie API v1 przez `_bootstrap.php` | `ApiAuth::ensureSchema`, `Market::ensureState` | DDL `api_tokens`, modyfikacja `market_state` do potwierdzenia w serwisie | JSON i CORS; `apiRequireAuth` | backend bez widoku; test bootstrapu: niezweryfikowany |

## Kartoteka czterech plików

### C01 — `api/v1/_bootstrap.php`

Linie 5–8 ustawiają CORS `*`; linie 10–16 kończą preflight. Linie 18–34 ładują błędy, autoload gdy dostępny, klasy gry i wyłączają GameLog. Linie 38–43 wywołują `ApiAuth::ensureSchema()` i `Market::ensureState()` dla każdego wejścia. Linie 49–105 definiują odpowiedzi i sprawdzenie bearer. Decyzja migracyjna: zachować kody i strukturę JSON oraz ścieżki URL; przenieść DDL i inicjalizację stanu rynku do wersjonowanego bootstrapu/migracji po sprawdzeniu obecnego schematu. `Market::ensureState` wymaga osobnego audytu całego pliku.

### C02 — `api/v1/auth/login.php`

Linie 16–29: metoda POST, parsowanie loginu/hasła i urządzenia. Linie 34–46: pobranie `players` po e-mailu albo nazwie. Linie 48–65: limit prób, `password_verify`, weryfikacja adresu i aktywnego statusu. Linie 68–77: zapis tokenu, czasu logowania i odpowiedź. Parametr `device` obcinany do 200 znaków po walidacji UTF-8. Decyzja migracyjna: test kontraktowy dla obu identyfikatorów, blokad, 429 oraz dokładnej odpowiedzi; nie zmieniać nazw pól bez wersjonowania mobilnego API.

### C03 — `api/v1/auth/logout.php`

Linie 13–25: POST/DELETE, wymagany poprawny bearer, usunięcie tylko aktualnego tokenu i odpowiedź. Decyzja migracyjna: zachować semantykę wylogowania pojedynczego urządzenia i oba dozwolone czasowniki; test na dwóch tokenach jednego gracza.

### C04 — `src/ApiAuth.php`

Linie 23–62: parsowanie bearer, odczyt aktywnego gracza, kontrola terminu tokenu i aktualizacja `last_used_at` (błąd jest ignorowany). Linie 69–103: generowanie 32 losowych bajtów jako 64 znaki hex, zapis tokenu, odwołanie pojedynczego/wszystkich tokenów. Linie 109–144: tworzenie tabeli `api_tokens` w czasie żądania. Linie 150–160: odczyt surowego bearer bez odwołania do DB. Decyzja migracyjna: zachować stare tokeny w okresie przejściowym i zaprojektować migrację do reprezentacji hash bez natychmiastowego wylogowania użytkowników; test odwołania, wygaśnięcia i zgodności klienta.

## Ustalenia i ryzyka

**P1 — zmiana/reset hasła nie odwołuje aktywnych tokenów mobilnych ani wszystkich tokenów „zapamiętaj mnie”.** `src/Auth.php:609-628` aktualizuje wyłącznie `players.password_hash`; `src/Auth.php:860-888` aktualizuje hash i stan `password_resets`. `src/ApiAuth.php:98-103` udostępnia `revokeAllForPlayer`, ale te dwie ścieżki jej nie wywołują. `src/Auth.php:763-794` przyjmuje ważny `player_remember_tokens.token_hash` bez sprawdzania czasu ostatniej zmiany hasła. Wniosek z tych ścieżek: znany wcześniej token pozostaje użyteczny po zmianie hasła do czasu własnego wygaśnięcia lub osobnego usunięcia. Należy jeszcze sprawdzić wszystkie zewnętrzne wywołania i politykę sesji, zanim określi się pełny zasięg. Test: dwa aktywne urządzenia mobilne i cookie „zapamiętaj mnie”, zmiana i reset hasła, oczekiwane unieważnienie według uzgodnionej polityki.

**P1 — token mobilny przechowywany w bazie w postaci surowej.** `src/ApiAuth.php:45,50,69-80,87-91`. Odczyt `api_tokens.token` ujawnia działające poświadczenia do czasu wygaśnięcia (standardowo 90 dni, `expires_at=NULL` nigdy). Test migracyjny: kompatybilność istniejących tokenów, rotacja i odwołanie, brak surowego tokenu w nowo tworzonych rekordach.

**P1 — DDL i zapis stanu rynku podczas obsługi żądania API.** `api/v1/_bootstrap.php:38-43`, `src/ApiAuth.php:109-144`. `ensureSchema` ustawia flagę `$done` przed próbą DDL (linie 111–118); wyjątek jest logowany, więc w tym samym procesie nie ma ponowienia. Skutek zależy od środowiska PHP i schematu. `Market::ensureState` pozostaje do szczegółowego sprawdzenia.

**P2 — obserwowalność nieudanego odświeżenia tokenu.** `src/ApiAuth.php:53-59` ignoruje wyjątek `last_used_at`; autoryzacja może nadal przejść. To może zaburzyć audyt użycia, lecz nie dowodzi obejścia kontroli dostępu.

## Testy i stan

Wykonano: pełny odczyt czterech wymienionych plików, porównanie wywołań i kolumn z fragmentami `Auth.php`. Nie wykonano: testów jednostkowych/integracyjnych/E2E/wizualnych, uruchomienia PHP, sprawdzenia produkcyjnego schematu, klienta Flutter i kodu `Market` oraz limitera. Wyniki są statyczne. Status czterech plików: `CRITICAL_STATIC_REVIEW`; status `FULL_SOURCE_REVIEW` wymaga domknięcia wszystkich zależności po obu stronach i testów kontraktowych.