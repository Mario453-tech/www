# Wspólny system komunikatów i modali — gra + panel admina

## Cel

Ujednolicić wszystkie:

- modale potwierdzające,
- krótkie toasty,
- alerty błędów i ostrzeżeń,
- komunikaty po akcji,
- blokujące potwierdzenia przed akcją,

tak, aby:

- **gra** i **panel admina** korzystały z jednego systemu,
- nowe widoki nie wymagały każdorazowego dopinania własnego `confirm()` lub `alert()`,
- teksty były gotowe pod **wiele języków**,
- wygląd był spójny z modalu bankowego i ogólnym UI gry.

---

## Wzorzec wizualny

Wzorcem dla całego systemu jest modal bankowy:

- ikona u góry,
- prosty tytuł,
- krótki opis,
- wyróżniona wartość lub skutek,
- dwa przyciski:
  - `Anuluj`
  - główna akcja (`Potwierdź`, `Zwolnij`, `Spłać`, itd.).

To jest standard dla:

- gry gracza,
- działów operacyjnych,
- panelu admina.

---

## Architektura docelowa

### 1. Jeden wspólny JS

- `C:\xampp1\htdocs\assets\js\modal.js`

Odpowiada za:

- `confirmAction(...)`
- `promptInput(...)`
- `alertInfo(...)`
- `alertError(...)`
- `alertWarning(...)`
- `showGameToast(...)`
- automatyczne `data-confirm` dla linków i formularzy

### 2. Jeden wspólny CSS

- `C:\xampp1\htdocs\assets\css\modal.css`

Odpowiada za:

- modal potwierdzający,
- modal wejściowy,
- warstwę overlay,
- toast stack,
- warianty `success / error / warning / info / danger`.

### 3. Jeden wspólny zestaw tłumaczeń

Źródłem prawdy dla etykiet globalnych są klucze:

- `modal.confirm`
- `modal.cancel`
- `modal.ok`
- `modal.title_error`
- `modal.title_info`
- `modal.title_warn`
- `modal.title_success`
- `modal.close`

Renderowane globalnie przez:

- `C:\xampp1\htdocs\templates\header.php`
- `C:\xampp1\htdocs\admin\partials\footer.php`

do:

- `window.MODAL_LANG`

Dzięki temu modal jest gotowy pod kolejne języki bez przepisywania mechaniki.

---

## Typy komunikatów

### Confirm modal
Do akcji nieodwracalnych lub kosztownych:

- spłata kredytu,
- sprzedaż odwiertu,
- zwolnienie pracownika,
- odrzucenie kandydata,
- reset ustawień,
- usunięcie rekordu,
- wymuszenie akcji w adminie.

### Prompt modal
Do akcji, które wymagają krótkiego inputu:

- powód zwolnienia,
- edycja limitu ceny,
- prosty komentarz lub decyzja tekstowa.

### Toast
Do informacji po wykonaniu akcji:

- zapisano,
- zatrudniono,
- odrzucono,
- przypisano do huba,
- oznaczono jako przeczytane.

### Alert modal
Do błędów lub ważnych stanów blokujących:

- brak danych,
- błąd połączenia,
- akcja niedozwolona,
- błąd walidacji.

---

## Zasada dla nowych widoków

### Formularze

Preferowany standard:

```html
<form
  data-confirm="Czy na pewno?"
  data-confirm-type="danger"
  data-confirm-title="Usuń rekord"
  data-confirm-label="Usuń">
```

### Linki

Preferowany standard:

```html
<a
  href="/..."
  data-confirm="Czy na pewno?"
  data-confirm-type="warning"
  data-confirm-title="Potwierdzenie"
  data-confirm-label="Kontynuuj">
```

### JS

Jeśli akcja jest dynamiczna:

- `confirmAction(...)`
- `promptInput(...)`
- `showGameToast(...)`
- `alertInfo(...)`
- `alertError(...)`
- `alertWarning(...)`

Nie używamy:

- `confirm()`
- `prompt()`
- `alert()`

chyba że to tymczasowy fallback w bardzo starym, jeszcze nieprzepiętym miejscu.

---

## Postęp wdrożenia

### Fundament

- [x] `assets/js/modal.js` jako wspólny silnik
- [x] `assets/css/modal.css` jako wspólny styl
- [x] globalne ładowanie w grze
- [x] globalne ładowanie w adminie
- [x] `window.MODAL_LANG`
- [x] tłumaczenia globalnych etykiet modala
- [x] polskie znaki naprawione w rdzeniu modala

### Gra — już przepięte

- [x] Bank — potwierdzenia formularzy przez `data-confirm`
- [x] Bank — modal spłaty jako wzorzec wizualny
- [x] Dashboard dyrektora — zatrudnienie / zwolnienie
- [x] Zarząd / Boardroom — toasty i confirmy
- [x] Personel odwiertów — przypisanie / odpięcie
- [x] Powiadomienia dyrektora — oznaczanie jako przeczytane
- [x] Sprzedaż ropy / wspólne potwierdzenie w `game.js`
- [x] Rynek ofert — edycja ceny i anulowanie oferty
- [x] Rynek ofert — edycja ceny przez `promptInput(...)`
- [x] Rynek ofert — anulowanie oferty przez `confirmAction(...)`

### Gra — już przepięte (cd.)

- [x] Finance (gracz) — `$msg`/`$err` jako `showGameToast()` + fallback banner z `id`
- [x] Finance (gracz) — potwierdzenie zmiany trybu na `aggressive` lub wyłączenia planu

### Gra — częściowo przepięte

- [x] HR — toasty delegowane do `showGameToast(...)`
- [x] HR — potwierdzenia przez wspólny modal
- [x] Recruitment — potwierdzenia i błędy przez wspólny system
- [x] DM — korzysta z globalnego toastu, ma lokalny fallback
- [x] Black Market — korzysta z `confirmAction(...)` i globalnego toastu
- [x] Well Grid — błędy przez `alertError(...)`, sukces przez globalny toast
- [x] World Map — błędy połączenia przez `alertError(...)`

### Admin — już przepięte

- [x] Admin Finance — reset mnożników przez wspólny modal
- [x] Admin Finance — reset polityki gracza przez wspólny modal
- [x] Admin Finance — zapis mnożników (`data-confirm`, type=warning)
- [x] Admin Finance — zapis konfiguracji alertów (`data-confirm`, type=info)
- [x] Admin Finance — `$msg`/`$err` jako `showGameToast()` przy powrocie po zapisie
- [x] Admin Tick Test — wymuszenie ticka przez `data-confirm`
- [x] Admin Tick Log — usuwanie wpisu przez `data-confirm`
- [x] Admin Chat — delete expired przez `data-confirm`
- [x] Admin Chat — ban gracza przez `data-confirm`
- [x] Admin Chat — unban przez `data-confirm`
- [x] Admin Chat — delete message przez `data-confirm`
- [x] Admin Chat — delete all messages gracza przez `data-confirm`
- [x] Admin Chat — usuwanie blokowanych słów przez `data-confirm`

### Admin / gra — jeszcze do przepięcia

- [ ] Admin Index — wymuszenie ticka
- [ ] Admin HR — cleanup
- [ ] Admin News — usuwanie wpisów
- [ ] Admin Boardroom — usuwanie ról / rekordów
- [ ] Admin Map Locations — usuwanie lokalizacji i alert Three.js
- [ ] Admin Help Editor — usuwanie stron
- [ ] Admin Pages Editor — usuwanie stron
- [ ] Admin Template Editor — usuwanie nav/footer/akcji/tła
- [ ] Admin Black Market — wymuszenie generowania ofert
- [ ] pozostałe stare `confirm()` w panelu admina
- [ ] pozostałe stare `alert()` w panelu admina
- [ ] czyszczenie starych lokalnych fallbacków toastów, gdzie nie są już potrzebne
- [ ] ujednolicenie starych niestandardowych modalów tam, gdzie warto je oprzeć o wspólny layout

---

## Miejsca jeszcze do przejrzenia

Najważniejsze pozostałe obszary:

- admin chat — custom modal czyszczenia jest OK, ale warto go później podpiąć pod ten sam język komponentów
- admin template editor
- admin help editor
- admin map locations
- admin pages editor
- admin boardroom
- admin news
- starsze inline skrypty z `alert()`

---

## Zasady tłumaczeń

### Mechanika

JS ma odpowiadać za mechanikę, nie za trzymanie tekstów domenowych.

### Globalne etykiety

Idą z:

- `window.MODAL_LANG`

### Teksty modułowe

Idą z:

- `t(...)` w PHP,
- albo lokalnych obiektów typu:
  - `HR_LANG`
  - `BR_LANG`
  - `GAME_LANG`
  - `BM_LANG`

### Kodowanie

Wszystkie pliki:

- PHP
- JS
- CSS
- tłumaczenia

muszą być zapisane jako:

- **UTF-8 bez BOM**

---

## Co robimy dalej

Najbliższa kolejność:

1. dokończyć przepięcie pozostałych `confirm()` w adminie,
2. wycinać stare `alert()` tam, gdzie są jeszcze w starych inline scriptach,
3. czyścić zbędne lokalne fallbacki toastów,
4. dopiero na końcu robić kosmetyczne ujednolicanie starszych custom modalów.

---

## Krótki stan na dziś

System już działa jako **jeden wspólny fundament** dla gry i admina.  
Nie jesteśmy już na etapie „pomysłu”, tylko na etapie:

- przepinania starych wyjątków,
- sprzątania resztek,
- i pilnowania, żeby nowe rzeczy od razu wchodziły w standard.
