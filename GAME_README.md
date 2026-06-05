## Changelog

### 2026-06-05 - Aktualnosci spolki: render HTML z TinyMCE
- `src/AdminNewsApi.php` - dodano bezpieczne czyszczenie HTML aktualnosci i pole `content_html`, aby tresc z TinyMCE zachowala naglowki, linki i kolory tekstu.
- `assets/js/chat.js` - panel aktualnosci renderuje teraz HTML zwrocony przez API zamiast wyswietlac tresc jako zwykly tekst.
- `assets/js/chat.js` - poprawiono scope helpera renderowania HTML i tekst komunikatu ladowania, aby panel nie pokazywal `Bd adowania.` przy poprawnej odpowiedzi API.
- `assets/css/chat.css` - dodano style dla akapitow, list, naglowkow, cytatow i linkow w panelu aktualnosci spolki.

### 2026-06-04 — Dział prawny: domknięcie P1 i start P2
- `src/LegalService.php` — podpięto `required_legal_level` do walidacji wniosku i danych mapy; poziom działu prawnego liczony jest z aktywnego dyrektora roli `legal`.
- `public/legal.php`, `templates/views/legal/main.php`, `assets/js/legal.js`, `assets/css/legal.css` — dodano grupę regionów blokowanych poziomem prawnym i przeniesiono komunikaty flash do JS modułu.
- `admin/legal.php`, `templates/views/admin/legal/main.php`, `assets/js/admin_legal.js`, `admin/partials/footer.php`, `assets/css/admin.css` — admin może ustawiać wymagany poziom prawny regionu; potwierdzenia przeniesiono z inline JS.
- `src/WorldMap.php`, `assets/js/world_map.js`, `assets/css/map.css`, `lang/pl/map.php` — mapa rozróżnia status `legal_locked` i pokazuje osobny komunikat blokady prawnej.
- `src/Tick/LegalSection.php` — usunięto emoji z ikon powiadomień działu prawnego.
- `DZIAL_PRAWNY_P1_STATUS.md` — zaktualizowano status wdrożenia P1/P2 po audycie kodu.
- `tests/Integration/LegalServiceTest.php`, `tests/Integration/LegalMapPermitDataTest.php` — dodano testy blokady wymaganym poziomem działu prawnego.

### 2026-06-03 — Logowanie zapamiętywane na 30 dni
- `public/login.php` — podpięto istniejący mechanizm remember-me pod aktywny ekran `/login`, dodano checkbox i auto-logowanie z cookie.
- `login.php` — ujednolicono rootową kopię formularza logowania z aktywnym ekranem `/login`.
- `lang/pl/auth.php` — dodano tekst checkboxa logowania.
- `assets/css/auth.css` — dopasowano odstęp checkboxa na ekranie logowania.
