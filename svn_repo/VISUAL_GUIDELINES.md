# WYTYCZNE WIZUALNE — OilCorp UI/CSS
> Dokument dla AI pracującego nad warstwą wizualną gry OilCorp (oilempire.pl).
> Ostatnia aktualizacja: 2026-07-06

---

## 1. TOŻSAMOŚĆ WIZUALNA

**Motyw:** Dark luxury · Gold accents · Industrial typography  
**Nazwa systemu w kodzie:** `OILCORP DESIGN SYSTEM`  
**Języki:** PL + EN (bilingual UI)

Gra to symulator zarządzania firmą naftową. Estetyka: ciemna, poważna, korporacyjna —
złote akcenty jako sygnał prestiżu, surowe kontrasy. Żadnych pastelowych kolorów,
żadnych zaokrąglonych "app-style" kart w stylu Material Design.

---

## 2. PALETA KOLORÓW

### Tokeny (źródło prawdy: `assets/css/variables.css`)

Wszystkie kolory zdefiniowane jako CSS custom properties w `:root`.
**NIGDY nie używaj hardkodowanych hex — zawsze `var(--nazwa)`.**

#### Złoto (akcent główny)
| Token | Wartość | Użycie |
|---|---|---|
| `--gold` | `#c8a84b` | przyciski primary, nagłówki, aktywne elementy |
| `--gold2` | `#e8cc7a` | highlight tekstu, hover, gradienty (jaśniejszy) |
| `--gold3` | `#a08030` | wyciszone akcenty, gradienty (ciemniejszy) |
| `--gold-dim` | `rgba(200,168,75,.12)` | tło hover, aktywne tło |
| `--gold-border` | `rgba(200,168,75,.25)` | obramowania kart |
| `--gold-glow` | `rgba(200,168,75,.3)` | box-shadow, poświata |

#### Tło
| Token | Wartość | Użycie |
|---|---|---|
| `--bg` | `#08080f` | tło strony (body) |
| `--bg2` | `#0f0f18` | karty (gradient start) |
| `--bg3` | `#161622` | karty (gradient end), formularze |
| `--bg4` | `#1e1e2e` | status items, btn-secondary |
| `--bg5` | `#252535` | hover na bg4 |

#### Tekst
| Token | Wartość | Użycie |
|---|---|---|
| `--text` | `#e8e8f0` | tekst podstawowy |
| `--text2` | `rgba(232,232,240,.6)` | tekst drugorzędny, labele |
| `--text3` | `rgba(232,232,240,.35)` | placeholder, muted, uppercase labels |

#### Kolory semantyczne
| Token | Wartość | Użycie |
|---|---|---|
| `--green` | `#4ec97a` | sukces, gotówka, btn-success |
| `--green-dim` | `rgba(78,201,122,.1)` | tło zielonego |
| `--red` | `#e05555` | błąd, niebezpieczeństwo, btn-danger |
| `--red-dim` | `rgba(224,85,85,.12)` | tło czerwonego |
| `--blue` | `#5b9cf6` | informacja, magazyn |
| `--orange` | `#f0a050` | ostrzeżenie, btn-warning |
| `--warn` | `#e6b43c` | ostrzeżenia (żółty odcień) |

#### Obramowania i kształt
| Token | Wartość | Użycie |
|---|---|---|
| `--border` | `rgba(200,168,75,.18)` | obramowania kart (złote) |
| `--border2` | `rgba(255,255,255,.06)` | delikatne białe obramowania |
| `--radius` | `8px` | standardowy border-radius |
| `--radius-lg` | `12px` | karty, większe elementy |
| `--shadow` | `0 8px 32px rgba(0,0,0,.55)` | cień kart |
| `--shadow-lg` | `0 20px 60px rgba(0,0,0,.7)` | cień modali, dużych elementów |

---

## 3. TYPOGRAFIA

**Krój:** `'Montserrat', 'Segoe UI', Arial, 'Noto Sans', sans-serif`  
**Rozmiar bazowy:** `14px`, `line-height: 1.6`  
**Ładowanie:** Google Fonts CDN w `style.css` (z `display=fallback`)

### Skala typograficzna
| Rola | Rozmiar | Waga | Inne |
|---|---|---|---|
| Logo / H1 | `22px` | `900` | `letter-spacing: 6px`, uppercase, gradient złota |
| Nagłówek karty H2 | `10px` | `700` | `letter-spacing: 3px`, uppercase, `color: var(--gold)` |
| Nagłówek sekcji H3 | `~16px` | `700` | normalny kolor |
| Przycisk `.btn` | `11px` | `700` | `letter-spacing: 1.5px`, uppercase |
| Label formularza | `9px` | `700` | `letter-spacing: 2px`, uppercase, `var(--text3)` |
| Tab nawigacji | `10px` | `700` | `letter-spacing: 1.6px`, uppercase |
| KPI label | `8px` | `700` | `letter-spacing: .12em`, uppercase |
| KPI wartość | `18px` | `800` | `letter-spacing: -.02em` |
| Tekst pomocniczy | `10-13px` | `400-500` | `var(--text2)` lub `var(--text3)` |

### Zasady typografii
- Uppercase + letter-spacing = tylko dla etykiet UI (labele, przyciski, nagłówki kart)
- Tekst długi (opisy, komunikaty) — normalny case, `14px`, `var(--text)`
- Liczby w kolumnach → `font-variant-numeric: tabular-nums`

---

## 4. STRUKTURA PLIKÓW WIZUALNYCH

### CSS — każdy moduł ma własny plik
```
assets/css/
├── variables.css       ← ŹRÓDŁO PRAWDY — tokeny kolorów i kształtów
├── style.css           ← GLOBALNY — reset, body, .card, .btn, .form-*, typo, layout
├── modal.css           ← GLOBALNY — system modali (confirmAction, alertInfo itp.)
├── admin.css           ← PANEL ADMINA — cały CSS dla /admin/
│
├── contracts.css       ← moduł kontraktów (gracz)
├── bank.css            ← moduł banku
├── market.css          ← moduł rynku
├── hr.css              ← moduł HR
├── legal.css           ← moduł prawny
├── logistics.css       ← moduł logistyki
├── dashboard.css       ← pulpit gracza
├── home.css            ← strona główna (po zalogowaniu)
├── boardroom.css       ← zarząd
├── training.css        ← szkolenia
├── black_market.css    ← czarny rynek
├── sabotage.css        ← sabotaż
├── finance.css         ← finanse
├── technical.css       ← serwis techniczny
├── map.css             ← mapa świata
├── auth.css            ← login/register
├── well-grid.css       ← siatka odwiertów
└── ...
```

### JS — analogicznie
```
assets/js/
├── modal.js            ← GLOBALNY — system modali, confirmAction, alertInfo, showGameToast
├── contracts.js        ← moduł kontraktów
├── bank.js             ← moduł banku
├── dashboard.js        ← pulpit
└── admin_*.js          ← pliki admina (admin_players.js, admin_wells.js itp.)
```

### Ikony SVG
```
assets/img/icons/
├── nav/                ← ikony akcji w pulpicie gracza
│   ├── bank.svg
│   ├── buy.svg
│   ├── dashboard.svg
│   ├── finance.svg
│   ├── help.svg
│   ├── legal.svg
│   ├── logistics.svg
│   ├── map.svg
│   ├── market.svg
│   ├── sabotage.svg
│   ├── team.svg
│   ├── technical.svg
│   └── default.svg
└── status/             ← ikony KPI w pasku statusu
    ├── cash.svg
    ├── bank.svg
    ├── storage.svg
    ├── oil_price.svg
    ├── company.svg
    └── wells.svg
```

### Obrazy
```
assets/img/
└── avatars/            ← awatary graczy (PNG/JPG, dynamiczne)
```

### Szablony HTML/PHP
```
templates/
├── header.php          ← globalny nagłówek (nav, KPI, burger mobile)
├── footer.php          ← globalny stopka
├── components/         ← komponenty wielokrotnego użytku
└── views/
    ├── contracts/main.php
    ├── bank/main.php
    ├── market/main.php
    ├── admin/
    │   ├── contracts/main.php
    │   ├── legal/main.php
    │   └── ...
    └── ...
```

---

## 5. KOMPONENTY — JAK WYGLĄDAJĄ I JAK ICH UŻYWAĆ

### Karta `.card`
```css
background: linear-gradient(145deg, var(--bg3), var(--bg2));
border: 1px solid var(--border);        /* złote obramowanie */
border-radius: var(--radius-lg);        /* 12px */
padding: 26px;
box-shadow: var(--shadow);
```
- Nagłówek karty: `.card h2` — uppercase, `var(--gold)`, 10px, z lewym paskiem złota (`::before`)
- Modyfikatory: `.card-warning` (lewa krawędź orange), `.card-danger` (red), `.card-success` (green)

### Przyciski `.btn`
| Klasa | Wygląd | Użycie |
|---|---|---|
| `.btn-primary` | złote tło, czarny tekst | główna akcja |
| `.btn-success` | przezroczyste, zielona obwódka | podpisz, zatwierdź |
| `.btn-danger` | przezroczyste, czerwona obwódka | anuluj, usuń |
| `.btn-warning` | przezroczyste, pomarańczowa obwódka | uwaga, zmiana |
| `.btn-secondary` | bg4, szara obwódka | nawigacja wstecz, drugorzędne |
| `.btn-info` | przezroczyste, niebieska obwódka | informacja |
| `.btn-sm` | mniejszy padding | inline, w tabelach |
| `.btn-full` | `width: 100%` | pełna szerokość |

### Formularze
- `.form-group` → `flex-direction: column; gap: 6px; margin-bottom: 16px`
- `label` / `.form-label` → 9px, 700, uppercase, `var(--text3)`
- `input`, `select`, `textarea` → dark background, złote focus border
- Focus: `border-color: var(--gold)` — jedyny efekt focus

### Tabs `.module-tab`
- Kontener: `.module-tabs` (flex, border-bottom)
- Aktywny: `.module-tab.active` → `var(--gold)`, złote tło, złota obwódka
- Nieaktywny: `var(--text3)`, przezroczysty

### Badges `.contracts-badge` (wzorzec z contracts.css)
Małe pigułki statusu — `var(--bg4)`, border, mały tekst uppercase.
Modyfikatory kolorystyczne przez `--low/medium/high/critical` lub `--status`.

### Pasek postępu
```css
/* Wzorzec z contracts.css */
.progress__bar {
    width: var(--bar-w);   /* ustawiane przez PHP: style="--bar-w: 45%" */
    background: linear-gradient(90deg, var(--gold3), var(--gold));
}
```
CSS custom property `--bar-w` jest jedynym dozwolonym miejscem na `style=""` dla wartości dynamicznych.

### KPI Status bar (`.status-kpi`)
Karty z ikoną po lewej + label + wartość. Używane globalnie w headerze gry.
Kolory per pozycja przez `nth-child` w `style.css` — nie modyfikuj bez potrzeby.

### Komunikaty flash
Dwa wzorce:
1. `<div class="msg-bar msg-error/msg-success">` — widoczne bez JS (dla `<noscript>`)
2. `<div id="XXX-flash" hidden data-error="..." data-success="...">` — odczytywane przez JS modułu

---

## 6. ZASADY BEZWZGLĘDNE — CZEGO NIE RUSZAĆ

### ❌ NIGDY nie rób
1. **Brak `<table>` w layoucie** — tylko CSS Grid i Flexbox. Tabele SQL (`CREATE TABLE`) to co innego.
2. **Brak inline `style=""`** — wyjątek: dynamiczne wartości PHP (`--bar-w:<?=?>%`, `width`, `color` z bazy).
3. **Brak bloków `<style>` w plikach PHP** — cały CSS idzie do `assets/css/[modul].css`.
4. **Brak logiki JS inline w PHP** — cały JS w `assets/js/[modul].js`. Jedyny wyjątek: blok `<script>` z konfiguracją PHP→JS (`window.MODAL_LANG = {...}`, itp.) — zero logiki.
5. **Brak `confirm()` / `alert()` / `prompt()`** — zawsze funkcje z `modal.js`: `confirmAction`, `alertInfo`, `alertError`, `alertWarning`, `showGameToast`.
6. **Brak emoji w kodzie/UI** — zamiast emoji używaj SVG (inline lub `<img>`).
7. **Brak hardkodowanych kolorów hex poza plikami CSS variables** — `var(--gold)`, nie `#c8a84b`.
8. **Nie modyfikuj `variables.css` ani `style.css`** bez bardzo dobrego powodu — to globalny fundament. Zmiany tam wpływają na wszystkie strony.
9. **Nie modyfikuj `modal.css` i `modal.js`** — system modali jest globalny i stabilny.
10. **Nie ruszaj nagłówka `templates/header.php` i stopki `templates/footer.php`** bez polecenia.

### ✅ CO WOLNO (i jak to robić)
1. **Nowy moduł wizualny** → nowy plik `assets/css/[modul].css` + `assets/js/[modul].js`
2. **Nowe komponenty** → klasy CSS w pliku modułu, nazewnictwo: `[modul]-[element]` (np. `contracts-card`, `contracts-grid`)
3. **Potwierdzenia akcji** → atrybut `data-confirm="..."` na `<form>`, obsługiwany przez globalny handler w `modal.js`
4. **Pasek postępu z PHP** → `style="--bar-w: <?= $pct ?>%"` na elemencie, CSS używa `var(--bar-w)`
5. **Nowe kolory semantyczne** → tylko przez nowy token w `variables.css`, potem `var(--nowy-token)` wszędzie

---

## 7. PANEL ADMINA — ODDZIELNY SYSTEM

Panel admina (`/admin/`) ma własny plik CSS: `assets/css/admin.css`.  
**Nie ładuje `style.css` gracza** — ma własny reset i komponenty.

### Struktura adminowa
```
admin/
├── init.php              ← bootstrap admina
├── partials/
│   ├── header.php        ← nawigacja admina (hardkodowana, sekcje nav_items)
│   └── footer.php
├── contracts.php         ← strona kontraktów admina
├── legal.php
├── bank.php
└── ...
```

### Nawigacja admina (`admin/partials/header.php`)
Sekcje nawigacji:
- `section_game`: players, wells, loans, gm_tools, hr, tasks, boardroom, training
- `section_market`: market, market_debug, balance, incidents, black_market
- `section_transport`: transport, pipelines, logistics_hubs, protection, sabotage
- **`section_legal`: legal, bribery, contracts** ← tutaj trafiają kontrakty
- `section_finance`: finance, financial-crisis, credibility
- `section_tools`: alerts, logs, chat, news, newsletter
- `section_content`: template editor, static pages

Klucze językowe dla nav: `lang/pl/admin/nav.php` i `lang/en/admin/nav.php`.

### Siatki danych admina (`admin.css`)
Wzorzec do list danych (zamiast `<table>`):
```css
.contracts-admin-grid { display: flex; flex-direction: column; overflow-x: auto; }
.contracts-admin-row  { display: grid; gap: 0 8px; padding: 8px 12px; }
/* Kolumny dostosowane do ilości danych */
.contracts-admin-row--options { grid-template-columns: 150px 1fr 150px 130px 90px 70px auto; }
```

---

## 8. IKONY — JAK UŻYWAĆ

### Ikony nawigacyjne (pulpit gracza)
Ładowane przez `GameShell::actionIconHtml(string $key)` — czyta SVG z dysku.

**Dozwolone klucze:** `market`, `bank`, `team`, `dashboard`, `map`, `buy`, `technical`, `finance`, `logistics`, `help`, `legal`, `sabotage`, `default`

Nowy moduł który nie ma własnej ikony → użyj najbliższej istniejącej (np. `finance` dla kontraktów).

Aby dodać nową ikonę:
1. Utwórz `assets/img/icons/nav/[nazwa].svg` (SVG inline, `fill="currentColor"`, viewBox="0 0 24 24")
2. Dodaj `'[nazwa]'` do tablicy `$allowed` w `GameShell::actionIconHtml()`
3. Dodaj `'url_key' => self::actionIconHtml('[nazwa]')` do `$iconMap`

### Ikony statusu (KPI bar)
Ładowane przez `GameShell::statusIconHtml(string $key)` — analogicznie z `assets/img/icons/status/`.

---

## 9. ROUTING I PLIKI PHP GRACZA

Każda nowa strona gracza wymaga trzech miejsc:
```
src/init.php           ← dodaj do ROUTES: 'nazwa' => '/nazwa'
.htaccess              ← dodaj RewriteRule: ^nazwa$ /public/nazwa.php [L,PT]
public/nazwa.php       ← controller gracza
```

Strona admina:
```
admin/partials/header.php   ← dodaj do $navSections
lang/pl/admin/nav.php       ← klucz admin.nav.nazwa
lang/en/admin/nav.php       ← klucz admin.nav.nazwa
admin/nazwa.php             ← controller admina
```

---

## 10. RESPONSIVE — BREAKPOINTY

| Breakpoint | Opis |
|---|---|
| `≤ 900px` | 2-kolumnowe gridy statusu, mniejsze paddingi nav |
| `≤ 640px` | 1-kolumnowe listy, `data-label` prefixes przez CSS `::before` |
| `≤ 600px` | mobile burger menu (slide-in z prawej), stopka kolumnowa |
| `≤ 480px` | 2-kolumnowy grid statusu, mniejszy KPI font |

### Wzorzec responsywnych list (zamiast tabeli)
```css
/* Desktop: grid wielokolumnowy */
.module-row { display: grid; grid-template-columns: 120px 1fr 90px auto; gap: 8px; }

/* Mobile: stack z etykietami */
@media (max-width: 640px) {
    .module-row { grid-template-columns: 1fr; }
    .module-row span[data-label]::before {
        content: attr(data-label) ": ";
        font-weight: 700;
        color: var(--text3);
    }
}
```

---

## 11. ANIMACJE I PRZEJŚCIA

- **Przejścia standardowe:** `transition: all .2s` lub konkretne właściwości (`.18s`–`.25s`)
- **Hover na kartach:** `border-color` i `box-shadow` — bez `transform`
- **Hover na przyciskach primary:** `transform: translateY(-1px)` + `box-shadow`
- **Puls statusu aktywnego:** `@keyframes statusPulse` (zielony ring) — klasa `.status-pulse`
- **`prefers-reduced-motion`:** jeśli dodajesz animacje non-trivial, owiń w `@media (prefers-reduced-motion: no-preference)`
- **Fade-in modułów:** klasa `.fade-in` na głównym wrapperze widoku

---

## 12. KLASY POMOCNICZE GLOBALNE (z `style.css`)

```css
.money, .green   /* var(--green) */
.danger, .red    /* var(--red) */
.warning         /* var(--orange) */
.storage, .blue  /* var(--blue) */
.gold            /* var(--gold) */
.muted           /* var(--text2) */
.muted2          /* var(--text3) */
.success         /* var(--green) */

.hidden          /* display: none */
.visually-hidden /* dostępne ukrycie dla screen readerów */
.mt-md           /* margin-top: 1rem */
.mb-md           /* margin-bottom: 1rem */
.table-scroll    /* overflow-x: auto na szerokich kontenerach */

/* Przyciski */
.btn-full        /* width: 100% */
.btn-sm          /* mniejszy padding */

/* Siatki akcji */
.action-grid            /* auto-fit, minmax(120px, 1fr) */
.action-grid--redesign  /* flex column, gap 10px */
.action-btn-row         /* flex, wrap, gap 8px */
```

---

## 13. SYSTEM JĘZYKOWY

Każdy string dla gracza widoczny w UI → **zawsze przez `t('modul.klucz')`**.

```
lang/
├── pl.php              ← ładuje glob pl/*.php
├── en.php              ← ładuje glob en/*.php
├── pl/
│   ├── contracts.php   ← 'contracts.page_title' => 'Kontrakty...'
│   ├── bank.php
│   ├── admin/
│   │   ├── nav.php     ← 'admin.nav.contracts' => 'Kontrakty...'
│   │   └── contracts.php
│   └── ...
└── en/
    └── (analogicznie)
```

Nigdy nie hardkoduj polskiego tekstu w szablonie PHP. Zawsze `t('klucz')`.

---

## SZYBKA ŚCIĄGAWKA

```
Nowy kolor?       → dodaj token do variables.css, używaj var()
Nowa strona?      → ROUTES w init.php + RewriteRule w .htaccess
Nowy CSS?         → nowy plik assets/css/modul.css, załaduj przez <link>
Nowy JS?          → nowy plik assets/js/modul.js, załaduj przez <script src>
Dialog "czy na pewno?"  → data-confirm="" na <form>, NIE confirm()
Pasek postępu?    → style="--bar-w: X%" + CSS var(--bar-w)
Ikona nawigacji?  → SVG w assets/img/icons/nav/, rejestracja w GameShell
Link w adminie?   → admin/partials/header.php + lang/pl/admin/nav.php
```
