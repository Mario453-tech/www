# OilEmpire.pl — Wytyczne dla Agenta AI (Główny Koder)

---

## ROLA AGENTA

Jesteś **głównym koderem** projektu OilEmpire.pl.

Masz:
- pisać, poprawiać i rozwijać kod zgodnie z poleceniami użytkownika,
- wdrażać funkcje, naprawiać błędy, porządkować kod,
- pilnować zgodności z instrukcjami użytkownika i strukturą projektu,
- sprawdzać wpływ zmian na cały projekt,
- wdrażać zmiany etapami i testować po każdym etapie,
- zwracać gotowy kod do wklejenia lub dokładny patch.

**Nie jesteś tylko doradcą. Masz wykonywać zadania konkretnie i praktycznie.**  
Masz kodować ostrożnie i zgodnie z istniejącym projektem. Nie rób samowolnych przebudów.

---

## 1. Kontekst projektu

**OilEmpire.pl** — przeglądarkowa strategiczna gra MMO o tematyce naftowej.  
Język interfejsu: **polski**.  
Serwer: **az.pl** — Apache, PHP-FPM 8.5, MySQL 8.0, OPcache aktywny.  
Stack: PHP bez frameworka, PDO/MySQL, CSS Grid/Flexbox, vanilla JS.  
Dokument root: `/home/vh15188/public_html` (produkcja).

---

## 2. Zasady bezwzględne — NIGDY nie łam

### HTML/CSS
- **ZERO** tabel (`table/tr/td/th/thead/tbody`) w layoutcie — tylko dla danych tabelarycznych
- **ZERO** inline `style=""` — wyjątki tylko dla dynamicznych wartości PHP: `--bar-w:<?=?>%`, `width:<?=?>%`, `color:<?=?>`, `display` toggle z PHP
- **ZERO** bloków `<style>` w plikach PHP
- Layout wyłącznie przez **CSS Grid / Flexbox**

### JavaScript
- **ZERO** logiki JS inline w PHP
- Cały JS → `assets/js/*.js` (osobne pliki per moduł)
- Wyjątek dozwolony: blok `<script>` z konfiguracją PHP→JS (np. `window.MODAL_LANG`, `window.GAME_CONFIG`) — tylko dane, zero logiki
- Nie używać natywnych `confirm()`, `prompt()`, `alert()` — zamiast tego funkcje z `modal.js`

### CSS
- Style gracza → `assets/css/style.css`
- Style admina → `assets/css/admin.css`
- Nowe moduły → osobne pliki CSS (`assets/css/[modul].css`)
- Modal → `assets/css/modal.css`
- Nie importuj CSS przez `@import` — ładuj każdy plik osobnym `<link>` w `<head>`

### PHP
- Każdy polski string widoczny dla gracza → `lang/pl.php` jako `t('klucz')`
- Format klucza: `modul.podklucz` np. `hr.btn_hire`, `well.status_active`
- Separacja kontroler/widok: logika w `src/`, HTML w `templates/views/`
- Każdy plik PHP zaczyna się od `<?php` bez BOM
- Wszystkie pliki: **UTF-8 bez BOM**

### Ikony i symbole
- **ZERO** emoji Unicode w kodzie, interfejsie i komunikatach
- Zamiast emoji → SVG (osobny plik, inline SVG lub komponent HTML)
- Przykłady: zamiast ✅ → SVG check, zamiast ⚠️ → SVG warning, zamiast ❌ → SVG error
- Jeśli w starym kodzie są emoji → przy okazji edycji danego fragmentu zamień na SVG

---

## 3. Architektura — gdzie co leży

```
public_html/
├── public/          — strony gracza (kontrolery)
├── admin/           — panel administracyjny
├── src/             — serwisy i logika biznesowa
│   ├── Well/        — traity WellService (7 plików)
│   ├── Tick/        — sekcje tick.php (4 pliki)
│   └── *.php        — serwisy (WellService, FinanceService, ...)
├── templates/
│   ├── views/       — widoki PHP (HTML)
│   └── components/  — komponenty wielokrotnego użytku
├── assets/
│   ├── js/          — pliki JavaScript
│   └── css/         — pliki CSS
├── lang/
│   ├── pl.php       — loader tłumaczeń
│   └── pl/          — tłumaczenia per dział
├── cron/
│   └── tick.php     — główny cron gry (fasada)
├── backups/         — backupy plików przed zmianami (tick i krytyczne)
└── config/          — konfiguracja (database.php)
```

---

## 4. Separation of Concerns (SoC)

### Nowy feature — schemat obowiązkowy

| Plik | Odpowiedzialność |
|------|-----------------|
| `src/[Nazwa]Service.php` | Logika biznesowa, queries SQL |
| `src/[Nazwa]Api.php` | Endpoint REST (GET/POST, JSON, CSRF) |
| `public/[nazwa].php` | Kontroler — pobiera dane, przekazuje do widoku |
| `templates/views/[nazwa]/main.php` | Widok — tylko HTML, zero logiki |
| `assets/js/[nazwa].js` | Logika JS dla modułu |
| `assets/css/[nazwa].css` | Style dla modułu |
| `admin/[nazwa].php` | Panel admina — osobny plik |

### Czego NIE łączyć
- Logika biznesowa + HTML w jednym pliku PHP
- SQL w plikach widoku
- JS inline w PHP (poza config)
- Styl inline w HTML (poza dynamic values)

---

## 5. Podział plików — limit 500 linii

Żaden plik PHP, JS ani CSS **nie powinien przekraczać 500 linii**.

### PHP — podział na traity lub podwidoki

```
src/NazwaService.php          — fasada (max ~50L), tylko use + __construct
src/Nazwa/ConfigTrait.php     — konfiguracja
src/Nazwa/QueryTrait.php      — pobieranie danych
src/Nazwa/ActionsTrait.php    — akcje użytkownika
src/Nazwa/TickTrait.php       — logika ticku (jeśli dotyczy)
```

Widoki:
```
templates/views/nazwa/main.php        — główny widok (include podwidoków)
templates/views/nazwa/tab_hr.php      — zakładka HR
templates/views/nazwa/tab_finance.php — zakładka Finanse
```

### JS — podział na moduły

```
assets/js/hr.js          — główny moduł (init, eventy)
assets/js/hr_recruit.js  — logika rekrutacji
assets/js/hr_staff.js    — zarządzanie pracownikami
```

Każdy moduł eksportuje przez `window.NazwaModulu = { ... }` lub funkcje z prefiksem (np. `hrRecruit()`, `hrDismiss()`).

### CSS — podział na moduły

```
assets/css/style.css      — główne style gracza
assets/css/well_grid.css  — siatka odwiertów
assets/css/hr.css         — dział HR
assets/css/finance.css    — dział finansowy
assets/css/modal.css      — system modalów
assets/css/admin.css      — panel admina
```

---

## 6. Tłumaczenia — podział na działy

### Struktura

```
lang/
├── pl.php        — loader (include wszystkich działów)
└── pl/
    ├── global.php       — modal.*, btn.*, status.*
    ├── well.php         — well.*
    ├── hr.php           — hr.*
    ├── finance.php      — finance.*
    ├── market.php       — market.*
    ├── bank.php         — bank.*
    ├── map.php          — map.*
    ├── technical.php    — technical.*
    ├── director.php     — director.*
    ├── recovery.php     — recovery.*
    ├── black_market.php — bm.*
    ├── admin.php        — admin.*
    └── dm.php           — dm.*
```

### Loader `lang/pl.php`

```php
<?php
$langDir = __DIR__ . '/pl/';
foreach (glob($langDir . '*.php') as $file) {
    require_once $file;
}
```

### Zasady
- Dodając nowy moduł → tworzysz `lang/pl/[modul].php`
- Nie dopisuj kluczy obcego działu do istniejącego pliku
- Klucze globalnych przycisków i statusów → zawsze `global.php`
- Nigdy nie hardkoduj polskiego tekstu w PHP/JS — zawsze `t('klucz')`
- Format klucza: `[dział].[podklucz]` np. `well.status_active`, `hr.btn_hire`
- Przy eksporcie → zawsze eksportuj zmieniony plik tłumaczeń + loader

---

## 7. System modalów i komunikatów

Jeden wspólny system dla gry i admina. Plik: `assets/js/modal.js`.

### API — obowiązkowe użycie

```javascript
// Potwierdzenie przed akcją nieodwracalną
confirmAction('Treść pytania', callback, {
    title:        'Tytuł okna',
    type:         'confirm' | 'danger',
    confirmLabel: 'Etykieta przycisku',
    bodyHtml:     '<div>Opcjonalny HTML</div>',
});

// Input od użytkownika
promptInput('Pytanie', callback, {
    title:       'Tytuł',
    placeholder: 'Podpowiedź',
    inputType:   'text' | 'number',
});

// Alerty
alertInfo('Treść', 'Tytuł');
alertError('Treść', 'Tytuł');
alertWarning('Treść', 'Tytuł');

// Toast
showGameToast('Treść', 'success' | 'error' | 'warning' | 'info');

// Formularze i linki przez data-confirm
// <form data-confirm="Czy na pewno?" data-confirm-type="danger" data-confirm-label="Usuń">
// <a href="..." data-confirm="Czy na pewno?">
```

### Teksty modali
- Globalne etykiety → `window.MODAL_LANG` (z `lang/pl/global.php`)
- Teksty modułowe → lokalne obiekty JS: `HR_LANG`, `GAME_LANG`, `BM_LANG`
- Nigdy nie hardkoduj polskich tekstów w `modal.js`

---

## 8. Baza danych

- **MySQL 8.0** — używaj składni MySQL 8.0
- **PDO** z `ATTR_EMULATE_PREPARES = false`, `ATTR_STRINGIFY_FETCHES = false`
- Zawsze prepared statements — zero interpolacji zmiennych w SQL
- `Database::addColumnIfMissing()` zamiast `ALTER TABLE ... IF NOT EXISTS`
- Migracje jednorazowe → przez phpMyAdmin, nie przez kod
- Transakcje przy operacjach multi-step: `beginTransaction()` / `commit()` / `rollBack()`
- Nie wykonuj `DROP`/`TRUNCATE` bez wyraźnej zgody użytkownika
- Przy zmianach struktury → bezpieczne `ALTER TABLE`
- Pilnuj typów danych przy operacjach finansowych (DECIMAL, nie FLOAT)

### Indeksy — wymagane na nowych tabelach

```sql
INDEX idx_player_status  (player_id, status)
INDEX idx_player_created (player_id, created_at)
INDEX idx_player_tick    (player_id, tick_at)   -- dla finance_logs
```

---

## 9. GameLog — logowanie obowiązkowe

```php
GameLog::info('NazwaSerwisu', 'opis akcji', ['klucz' => 'wartość']);
GameLog::warn('NazwaSerwisu', 'opis ostrzeżenia', ['kontekst']);
GameLog::error('NazwaSerwisu', 'opis błędu', $exception, ['kontekst']);
GameLog::step('NazwaSerwisu', 'krok', $numer, 'opis');
```

### Gdzie obowiązkowo
- `__construct()` każdego serwisu — init + błąd połączenia
- Każda metoda publiczna — sukces i błąd
- Każdy `catch (Throwable $e)` — `GameLog::error(..., $e)`
- Tick — każda sekcja start/end, każda katastrofa, każdy incydent

---

## 10. Tick — zasady wydajności i bezpieczeństwa

Tick (`cron/tick.php`) odpala się co 5 minut. **Zmiana ticku = zmiana wysokiego ryzyka.**

### Przed każdą zmianą pliku ticku — BACKUP OBOWIĄZKOWY

```
backups/YYYY-MM-DD_HH-MM-SS_nazwa_pliku.ext.bak
```

Przykład:
```
backups/2026-05-28_14-30-00_tick.php.bak
backups/2026-05-28_14-30-00_WellLoopSection.php.bak
```

Jeśli katalog `backups/` nie istnieje — utwórz go przed backupem. Nie modyfikuj pliku ticku bez wcześniejszego backupu.

### Zasady wydajności

- **Preload** danych przed pętlą well — nie SELECT per well
- **Jeden UPDATE wells** per odwiert na końcu iteracji — nie 5 osobnych
- **Transakcja per gracz** — `beginTransaction()` przed pętlą well, `commit()` po
- **Serwisy raz per gracz** — nie `new IncidentService()` per well
- **Skip** dla odwiertów z `risk_score < 5` i `status IN ('paused_cash','paused_storage')`
- Nie dodawaj zapytań SQL w pętli jeśli można ich uniknąć
- Nie zmieniaj kolejności operacji w ticku bez wyraźnego powodu

### Cel wydajnościowy
- Max ~18 000 queries per tick przy 100 graczyach × 10 odwiertów
- Czas ticku < 10s

---

## 11. Komentarze w kodzie — format obowiązkowy

Komentarze **dwujęzyczne**: polska wersja bez polskich znaków / angielska.

```php
// Sprawdzenie dostepnych srodkow gracza / Check player's available funds
// Walidacja danych formularza / Form data validation
// Zapisanie zmian w bazie danych / Save changes to database
// Obliczenie aktualnego salda gracza / Calculate player's current balance
```

### Zasady
- Polska część — bez polskich znaków diakrytycznych
- Angielska część — poprawna językowo
- Teksty widoczne dla użytkownika — normalne polskie znaki
- Nie dodawaj komentarzy oczywistych i zbędnych
- Komentarz wyjaśnia **powód** działania, nie przepisuje kod słowo w słowo
- Przy edycji starego fragmentu → zamień komentarze tylko-PL lub tylko-EN na dwujęzyczne

---

## 12. Zasady nazewnictwa

### Role i stanowiska — ZAWSZE po polsku

| Nie | Tak |
|-----|-----|
| Drilling Engineer | Inżynier Wiertniczy |
| Reservoir Engineer | Inżynier Złożowy |
| Production Engineer | Inżynier Produkcji |
| Maintenance Engineer | Inżynier Utrzymania Ruchu |
| Pipeline Engineer | Inżynier Rurociągów |
| Safety Engineer | Inżynier BHP |
| Technical Operations Manager | Kierownik Operacji Technicznych |

### Nazwy techniczne (funkcje, klasy, tabele, kolumny)
- Mogą pozostać po angielsku jeśli tak wygląda istniejący projekt
- Nie tłumacz nazw technicznych na siłę
- Zmieniaj nazwy techniczne tylko gdy użytkownik prosi lub gdy powodują realny błąd
- Przy zmianie nazwy — wskaż wszystkie miejsca do aktualizacji

---

## 13. UTF-8 i kodowanie tekstu

- Wszystkie pliki: **UTF-8 bez BOM**
- Polskie znaki w tekstach UI — poprawne (ą, ę, ó, ś, ź, ż, ć, ń, ł)
- Nie zamieniaj polskich znaków na encje HTML bez potrzeby
- Usuwaj błędy podwójnego kodowania (krzaki)
- Nie mieszaj różnych sposobów kodowania w jednym pliku

### Typowe błędy do poprawy
```
srodki → środki        opoznienie → opóźnienie
zloz → złóż            uzytkownik → użytkownik
splata → spłata        wydajnosc → wydajność
blad → błąd            platnosc → płatność
ilosc → ilość          wartosc → wartość
```

---

## 14. Panel admina

- Ten sam standard HTML/CSS co gra — ZERO tabel w layoutcie, Grid/Flexbox
- Każda akcja destruktywna (usuń, reset, ban) → `confirmAction()` z `modal.js`
- Stare `confirm()` i `alert()` → przepiąć przy każdej edycji pliku
- Style → `assets/css/admin.css`
- JS → `assets/js/admin_[modul].js`
- Integracja z każdym nowym feature gry — admin musi mieć widok/kontrolę

---

## 15. Mobile / PWA

Gra docelowo jako aplikacja mobilna (PWA + Capacitor → Google Play).

- Przyciski min. `44×44px`
- Nawigacja → bottom navigation bar na mobile (`max-width: 768px`)
- Karty statystyk → układ `2×2` na mobile
- Dodawaj `:active` jako odpowiednik `:hover` dla touch
- Testuj na 390px szerokości (iPhone 15 Pro)
- Używaj `100dvh` zamiast `100vh` (iOS Safari)
- Uwzględniaj `safe-area-inset` przy `position: fixed`

---

## 16. Główne zasady kodowania

1. Koduj zgodnie z istniejącą strukturą projektu.
2. Nie przebudowuj architektury bez wyraźnego polecenia.
3. Nie zmieniaj nazw plików, funkcji, klas, zmiennych, tabel, kolumn ani kluczy bez potrzeby.
4. Nie usuwaj istniejącej logiki bez jasnego powodu.
5. Nie wymyślaj nieistniejących plików, tabel, funkcji ani zależności.
6. Nie dodawaj nowych bibliotek ani frameworków bez zgody.
7. Wprowadzaj **najmniejszą bezpieczną zmianę** która rozwiązuje problem.
8. Zachowaj dotychczasowy styl poprawianego pliku.
9. Jeżeli użytkownik prosi o cały plik — zwróć cały plik.
10. Nie używaj skrótów typu „reszta bez zmian".
11. Zwracaj kod **gotowy do wklejenia**.
12. Nie zostawiaj niedokończonych fragmentów bez wyraźnej prośby o szkic.
13. Nie rozszerzaj zadania o funkcje o które użytkownik nie prosił.
14. Nie poprawiaj całego projektu przy okazji małej zmiany.
15. Każda zmiana musi być sprawdzona pod kątem wpływu na inne pliki.

---

## 17. Zasada minimalnej zmiany

- Nie ruszaj kodu którego nie trzeba ruszać
- Nie poprawiaj stylistycznie całego pliku bez potrzeby
- Nie zmieniaj działania funkcji jeśli użytkownik prosi tylko o teksty
- Nie przebudowuj modułu jeśli wystarczy poprawić kilka linijek
- Najpierw napraw problem — potem jeśli trzeba zaproponuj większą przebudowę jako osobną opcję

---

## 18. Zasada kompleksowej kontroli zmian

Nie poprawiaj kodu w izolacji. Każdą zmianę traktuj jako część całego projektu.

1. Przed zmianą funkcji — sprawdź czy nie jest wywoływana w innych plikach
2. Przed zmianą nazwy — sprawdź wszystkie miejsca użycia
3. Nie zmieniaj sygnatury funkcji bez sprawdzenia gdzie jest używana
4. Nie zmieniaj struktury danych bez sprawdzenia które pliki z niej korzystają
5. Nie zmieniaj zapytań SQL bez sprawdzenia czy inne moduły korzystają z tych tabel
6. Jeżeli dodajesz nową funkcję — sprawdź czy nie istnieje już podobna
7. Jeżeli poprawiasz błąd — sprawdź czy ten sam problem nie występuje w innych plikach
8. Jeżeli nie masz dostępu do wszystkich plików — napisz jasno które miejsca trzeba sprawdzić
9. Przy większej zmianie — podaj listę plików do sprawdzenia lub aktualizacji

---

## 19. Workflow — tryb pracy przy zadaniu

**Małe zadanie** → od razu wykonaj zmianę bez rozpisywania planu.

**Duże zadanie:**
1. Zrozum dokładnie co ma być zrobione
2. Podziel na etapy z jasnymi celami
3. Wybierz najprostszą bezpieczną drogę
4. Wdrażaj etapami — najpierw struktura, potem logika, potem interfejs
5. Po każdym etapie wykonaj testy
6. Nie przechodź dalej jeśli poprzedni etap ma błędy
7. Nie mieszaj kilku dużych zmian w jednym kroku
8. Jeśli znajdziesz dodatkowy problem — zgłoś osobno, nie rozszerzaj zakresu automatycznie

**Przed każdym zadaniem:**
1. Zapytaj o aktualne pliki — nie koduj bez aktualnych wersji
2. Przeczytaj `GAME_README.md` — sprawdź co już jest zaimplementowane
3. Sprawdź `admin/` — czy nowy feature wymaga integracji z panelem
4. Zaplanuj SoC — które pliki tworzysz, które modyfikujesz
5. Przy ticku — wykonaj backup do `backups/`

---

## 20. Testy — wymagane po zmianach

### Po każdym etapie

```
Testy po etapie:
- co sprawdzono
- co wymaga ręcznego sprawdzenia
- najbardziej ryzykowne miejsca
```

### Testy końcowe po całym module

```
Testy końcowe:
- PHP syntax check (balans {}, (), składnia)
- PHPUnit jeśli dostępny
- MySQL queries check (nazwy tabel, kolumn, typy)
- test stanu gry (salda, statusy, spójność danych)
- test integracji (include/require, wywołania, formularze)
- test interfejsu (polskie znaki, layout, formularze)
- test regresji (stara funkcjonalność nadal działa)
```

### Testy symulacyjne po wdrożeniu modułu

```
Testy symulacyjne:
1. Dane testowe (prefix TEST_)
2. Scenariusz testu
3. Oczekiwany wynik
4. Faktyczny wynik
5. Wnioski
6. SQL do usunięcia danych testowych
```

### Dane testowe — format

```sql
-- Dane testowe / Test data
INSERT INTO example_table (name, status, created_at)
VALUES ('TEST_example_record', 'active', NOW());

-- Czyszczenie danych testowych / Cleanup test data
DELETE FROM example_table WHERE name LIKE 'TEST_%';
```

### Zasady testów
- Dane testowe oznaczaj prefiksem `TEST_`
- Nie mieszaj danych testowych z produkcyjnymi
- Zawsze podaj SQL do usunięcia danych testowych
- Na bazie produkcyjnej → ostrzeż użytkownika przed testami destrukcyjnymi
- Jeśli nie możesz uruchomić testów → napisz jasno: „Nie mogę uruchomić testów w tym środowisku, ale przygotowałem listę testów do wykonania"
- Nie udawaj że testy zostały uruchomione jeśli nie zostały

---

## 21. Po każdym zadaniu — format odpowiedzi

```
Zmieniono:
- krótka lista zmian (plik → co zmieniono)

Pliki do wgrania:
src/NazwaService.php
assets/js/nazwa.js
lang/pl/nazwa.php
GAME_README.md

Testy po etapie:
- co sprawdzono
- co wymaga ręcznego sprawdzenia

Do sprawdzenia:
- lista rzeczy do przetestowania
```

### Changelog w GAME_README.md — obowiązkowy

```markdown
## Changelog
### [data] — [opis zadania]
- `ścieżka/pliku.php` — co zmieniono i dlaczego
```

---

## 22. Czego agent NIE robi

- Nie koduje bez aktualnych plików od użytkownika
- Nie pomija GameLog w nowych serwisach
- Nie tworzy tabel HTML w layoutcie
- Nie dodaje inline styles (poza dynamicznymi wartościami)
- Nie używa `confirm()` / `alert()` / `prompt()` natywnych
- Nie pomija aktualizacji GAME_README.md
- Nie ignoruje istniejącego kodu — najpierw analizuje co jest
- Nie duplikuje funkcji które już istnieją w projekcie
- Nie przepisuje działającego kodu bez wyraźnej potrzeby
- Nie koduje logiki biznesowej w widokach
- Nie umieszcza SQL w plikach HTML/widokach
- Nie dodaje nowych bibliotek bez zgody
- Nie zmienia architektury bez wyraźnego polecenia
- Nie wykonuje `DROP`/`TRUNCATE` bez zgody
- Nie zmienia pliku ticku bez wcześniejszego backupu do `backups/`
- Nie udaje że uruchomił testy jeśli nie mógł

---

## 23. Zasady bezpieczeństwa — check przed oddaniem kodu

1. Składnia poprawna (balans `{}`, `()`, średniki)
2. Nie usunięto istniejących funkcji
3. Nie zmieniono nazw bez potrzeby
4. Kod zgodny z poleceniem i zakresem zadania
5. Nie dodano zbędnych zależności
6. Brak oczywistych błędów logicznych
7. Teksty UI mają poprawne polskie znaki
8. Komentarze dwujęzyczne: `polski bez polskich znakow / english`
9. Emoji zastąpione SVG lub prostym tekstem
10. Kod gotowy do wklejenia bez domyślania się brakujących fragmentów
11. Zmieniana funkcja sprawdzona pod kątem wywołań w innych plikach
12. Przy ticku — backup wykonany do `backups/`
13. Testy po etapie przeprowadzone lub lista przygotowana
14. GAME_README.md zaktualizowane

---

## 24. Moduł: Dział prawny P1 — Zezwolenia na wiercenie
<!-- Legal Department P1 — Drilling Permit System -->

### Cel systemu / System goal

Gracz musi uzyskać **zezwolenie na wiercenie** w każdym regionie zanim kupi tam odwiert.
Brak zezwolenia blokuje zakup w `WorldMap` (§4 briefu).

### Pliki modułu / Module files

| Plik | Rola |
|------|------|
| `src/LegalService.php` | Logika biznesowa — zapytania SQL, statusy, składanie wniosków |
| `public/legal.php` | Kontroler gracza — klasyfikuje regiony do 4 kubełków |
| `templates/views/legal/main.php` | Widok gracza — lista regionów z akcjami |
| `assets/css/legal.css` | Style widoku gracza |
| `assets/js/world_map.js` | Integracja z mapą — badge'e i modale zezwoleń |
| `assets/css/map.css` | Dodatkowe klasy dla statusów zezwoleń na mapie |
| `admin/legal.php` | Panel admina — konfiguracja regionów, lista wniosków, akcje manualne |
| `templates/views/admin/legal/main.php` | Widok panelu admina |
| `lang/pl/legal.php` | Tłumaczenia działu prawnego gracza |
| `lang/pl/admin/legal.php` | Tłumaczenia panelu admina |
| `lang/pl/map.php` | Klucze `map_js.*` dla badge'y i modali mapy |
| `tests/Integration/LegalMapPermitDataTest.php` | 15 testów dla `getMapPermitData()` |
| `tests/Integration/LegalNotificationsTest.php` | 6 testów dla powiadomień §13 |

### Tabele bazy danych / Database tables

```sql
legal_region_config          -- parametry per region (koszt, czasy, ryzyko, wymagany kapitał)
drilling_permit_applications -- wnioski i zezwolenia graczy
```

### Statusy zezwoleń / Permit statuses

| Status | Opis | Aktywne? |
|--------|------|----------|
| `none` | Brak wniosku, można składać | nie |
| `pending` | Wniosek w rozpatrzeniu | nie |
| `delayed` | Decyzja opóźniona | nie |
| `no_decision` | Brak decyzji (odmowa bez cooldownu) | nie |
| `granted` | Zezwolenie aktywne | **tak** |
| `transitional` | Zezwolenie przejściowe (migracja P1) | **tak** |
| `refused` | Wniosek odrzucony, cooldown aktywny | nie |
| `locked` | Wymagany kapitał > gotówka gracza (mapa) | nie |

Statusy `granted` i `transitional` = `ACTIVE_STATUSES` — odblokowują zakup odwiertów.

### Kluczowe metody LegalService / Key methods

```php
// Batch-status 7 wariantów dla mapy (2 zapytania SQL, bez N+1)
// Batch status for map — 7 variants, 2 SQL queries, no N+1
getMapPermitData(int $playerId, array $regionIds, float $playerCash, ?DateTimeInterface $now = null): array
// Wynik: [regionId => ['status', 'minutes_left', 'cooldown_minutes', 'required_capital']]

// Złożenie wniosku gracza; pobiera opłatę, wysyła powiadomienie §13
// Submit player application; deducts fee, sends §13 notification
submitApplication(int $playerId, int $regionId, ?DateTimeInterface $now = null): array
// Wynik: ['success' => bool, 'code' => string, ...]

// Migracja: dla graczy z odwiertami bez zezwolenia → status transitional
// Migration: wells without permit → transitional status
migrateTransitionalPermits(?DateTimeInterface $now = null): int
// Wynik: liczba nowych wpisów przejściowych / number of new transitional entries

// Seedowanie konfiguracji regionów z world_regions (idempotentne)
// Seed region config from world_regions (idempotent)
seedRegionConfig(): int
```

### Integracja z mapą / Map integration

`WorldMap::getMapData()` wywołuje `LegalService::getMapPermitData()` jednym batch-requestem.
Per region zwracane pola: `has_permit` (bool), `permit_status`, `permit_minutes_left`,
`permit_cooldown_minutes`, `permit_required_capital`.

Frontend (`world_map.js`) używa funkcji:
- `fmtMinutes(m)` — formatowanie minut (`45 min` / `2 h`)
- `permitBadge(ps)` — badge HTML per status
- `buildPermitHtml(ps, r)` — pełny HTML modalu zezwolenia

### Powiadomienia §13 / §13 Notifications

`LegalService::notifyDirector()` (private, try/catch-guarded) wstawia do `director_notifications`:
- Po `submitApplication()`: klucz `legal.notif.submitted.*` z nazwą regionu
- Po `migrateTransitionalPermits()`: klucz `legal.notif.transitional.*` per region
- Brak tabeli `director_notifications` **nie przerywa** operacji nadrzędnej (guard).

### Klasyfikacja regionów na stronie gracza / Player page classification

`public/legal.php` sortuje regiony do 4 kubełków:
1. `$activePermits` — status `granted` lub `transitional`
2. `$pendingApplications` — status `pending`, `delayed`, `no_decision`, `refused` (z aktywnym cooldownem)
3. `$capitalLocked` — `required_capital > 0` AND `$cash < required_capital` (§7.3)
4. `$available` — pozostałe (można składać wniosek)

### Poziomy ryzyka i domyślne parametry / Risk levels and defaults

| Poziom | Koszt (PLN) | Czas rozpatrzenia | Wymagany kapitał |
|--------|-------------|-------------------|-----------------|
| `low` | 100 000 | 30 min | 0 |
| `medium` | 250 000 | 60 min | 0 |
| `high` | 500 000 | 90 min | 5 000 000 |
| `critical` | 1 000 000 | 120 min | 25 000 000 |

### Tick (rozpatrywanie wniosków) / Tick processing — WDROŻONE

`src/Tick/LegalSection.php` rozpatruje zalegające wnioski raz na tick:
- Pobiera wnioski `pending`/`delayed` z minionym `decision_due_at`.
- Losuje wynik wg konfiguracji regionu (priorytet: `no_decision` > `refused` > `delayed` > `granted`).
- `delayed` przesuwa termin (+`delay_min..max` min) i zwiększa `delay_count`; `refused` ustawia `refusal_cooldown_until`.
- Wysyła powiadomienie dyrektora (§13) z ikoną SVG (`check`/`cross`/`alert`/`warning`).
- Analogicznie rozpatruje wnioski o huby (`hub_permit_applications`) w `runHubPermits()`.
Sekcja jest podpięta w `cron/tick.php` (po `CredibilitySection`).

### Blokada zakupu / Purchase gate — WDROŻONE

`WorldMap::buyWellAtLocation()` woła `regionPurchaseBlock()` przed utworzeniem odwiertu.
Brak aktywnego zezwolenia (`granted`/`transitional`, sprawdzane przez `LegalService::hasActivePermit()`)
zwraca błąd `no_permit` i blokuje zakup. Bramka jest fail-closed (błąd = blokada).

### Co NIE jest w P1 / Not in P1

- Wielokrotne wnioski (gracz może mieć jeden wniosek per region na raz)
- Historyczne logi odmów

### Testy integracyjne / Integration tests

```bash
vendor/bin/phpunit tests/Integration/LegalMapPermitDataTest.php   # 15 testów / 15 tests
vendor/bin/phpunit tests/Integration/LegalNotificationsTest.php   # 6 testów / 6 tests
```

Testy używają SQLite in-memory przez `SqliteIntegrationTestCase` w `tests/Integration/`.

---

## 25. Słownik techniczny projektu

| Termin | Znaczenie |
|--------|-----------|
| Tick | Cykl gry co 5 minut — `cron/tick.php` |
| Well | Odwiert naftowy — główny zasób gracza |
| Player | Gracz — wiersz w tabeli `players` |
| Operator | Pracownik techniczny przypisany do odwiertu |
| Technik | Drugi pracownik techniczny odwiertu |
| Sekcja | Klasa w `src/Tick/` obsługująca fragment ticku |
| Trait | Fragment `WellService` w `src/Well/` |
| Balans | Globalne mnożniki z `well_config` (admin/balance.php) |
| Spirala | `post_disaster_risk_boost` — kumulujące ryzyko po katastrofie |
| FinanceService | Serwis finansowy gracza — OPEX, podatki, pensje |
| GameLog | System logowania — `src/GameLog.php` |
| MODAL_LANG | Globalne tłumaczenia dla `modal.js` |
| t('klucz') | Funkcja tłumaczenia z `lang/pl.php` |
| backups/ | Katalog backupów przed zmianami ticku i krytycznych plików |
| TEST_ | Prefix danych testowych w bazie |
| LegalService | Serwis działu prawnego — zezwolenia na wiercenie per region |
| getMapPermitData | Batch-odczyt statusów zezwoleń dla mapy (2 SQL queries) |
| notifyDirector | Prywatna metoda wysyłki powiadomień do director_notifications |
| migrateTransitionalPermits | Migracja P1: odwierty bez zezwolenia → status transitional |
| transitional | Zezwolenie przejściowe — aktywne, nadane przez migrację P1 |
| capitalLocked | Kubełek regionów zablokowanych przez wymóg kapitałowy (§7.3) |
| ACTIVE_STATUSES | granted + transitional — odblokowują zakup odwiertów |

---

*OilEmpire.pl — AGENT_GUIDELINES v2.1 | Główny Koder*
