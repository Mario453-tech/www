# OilCorp Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ Dokumentacja Techniczna Gry

> Strategiczna gra naftowa. ZarzÄ‚â€žĂ˘â‚¬Â¦dzasz firmÄ‚â€žĂ˘â‚¬Â¦ wydobywczÄ‚â€žĂ˘â‚¬Â¦: kupujesz lokalizacje, wiertujesz odwierty, zatrudniasz ludzi, handlujesz ropÄ‚â€žĂ˘â‚¬Â¦.

---

## Spis treĂ„Ä…Ă˘â‚¬Ĺźci

1. [Architektura](#1-architektura)
2. [Routing i .htaccess](#2-routing-i-htaccess)
3. [Autentykacja i sesje](#3-autentykacja-i-sesje)
4. [Odwierty (Wells)](#4-odwierty-wells)
4b. [System sprzÄ‚â€žĂ˘â€žËtu (Equipment Tiers)](#4b-system-sprzÄ‚â€žĂ˘â€žËtu-equipment-tiers)
4c. [System transportu odwiertĂ„â€šÄąâ€šw](#4c-system-transportu-odwiertĂ„â€šÄąâ€šw)
4d. [SprzedaĂ„Ä…Ă„Ëť odwiertĂ„â€šÄąâ€šw](#4d-sprzedaĂ„Ä…Ă„Ëť-odwiertĂ„â€šÄąâ€šw)
5. [Warstwy geologiczne](#5-warstwy-geologiczne)
6. [System pracownikĂ„â€šÄąâ€šw (HR)](#6-system-pracownikĂ„â€šÄąâ€šw-hr)
7. [System degradacji](#7-system-degradacji)
8. [System awarii i incydentĂ„â€šÄąâ€šw](#8-system-awarii-i-incydentĂ„â€šÄąâ€šw)
9. [Wear & Tear (ZuĂ„Ä…Ă„Ëťycie)](#9-wear--tear-zuĂ„Ä…Ă„Ëťycie)
10. [Spirala katastrof](#10-spirala-katastrof)
11. [System rynku (Market)](#11-system-rynku-market)
12. [System bankowy](#12-system-bankowy)
13. [System bankructwa](#13-system-bankructwa)
14. [Komornik (Bailiff)](#14-komornik-bailiff)
15. [Mapa Ă„Ä…Ă˘â‚¬Ĺźwiata i lokalizacje](#15-mapa-Ă„Ä…Ă˘â‚¬Ĺźwiata-i-lokalizacje)
16. [System zadaĂ„Ä…Ă˘â‚¬Ĺľ technicznych](#16-system-zadaĂ„Ä…Ă˘â‚¬Ĺľ-technicznych)
17. [Cron Tick Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ serce gry](#17-cron-tick--serce-gry)
18. [Panel admina](#18-panel-admina)
18b. [Panel admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ sekcje transportu i balansu](#18b-panel-admina--sekcje-transportu-i-balansu)
18c. [Panel admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ HR (`admin/hr.php`)](#18c-panel-admina--hr-adminhrphp)
18d. [Panel admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ moderacja czatu](#18d-panel-admina--moderacja-czatu-adminchatphp)
19. [Profil gracza](#19-profil-gracza)
20. [Sala ZarzÄ‚â€žĂ˘â‚¬Â¦du (Boardroom)](#20-sala-zarzÄ‚â€žĂ˘â‚¬Â¦du-boardroom)
21. [BezpieczeĂ„Ä…Ă˘â‚¬Ĺľstwo](#21-bezpieczeĂ„Ä…Ă˘â‚¬Ĺľstwo)
22. [System czatu graczy](#22-system-czatu-graczy)
23. [DziaĂ„Ä…Ă˘â‚¬Ĺˇ Finansowy](#23-dziaĂ„Ä…Ă˘â‚¬Ĺˇ-finansowy)
24. [Czarny Rynek Ropy](#24-czarny-rynek-ropy)
25. [Separacja logiki od widoku](#25-separacja-logiki-od-widoku--faza-1)
26. [Panel admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ odwierty (admin/wells.php)](#26-panel-admina--odwierty-adminwellsphp--i18n--zakĂ„Ä…Ă˘â‚¬Ĺˇadki-konfiguracji)
27. [System AktualnoĂ„Ä…Ă˘â‚¬Ĺźci (Admin News)](#27-system-aktualnoĂ„Ä…Ă˘â‚¬Ĺźci-admin-news)
- [Specjalizacje pracownikĂ„â€šÄąâ€šw (perki)](#specjalizacje-pracownikĂ„â€šÄąâ€šw-perki)
- [Changelog](#changelog)
- [Otwarte TODO](#otwarte-todo)
- [JakoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ kodu Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ PHPStan](#jakoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡-kodu--phpstan)

---

## 1. Architektura

```
htdocs/
Ä‚ËĂ˘â‚¬ĹĄÄąâ€şÄ‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬Ä‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬ public/         Ä‚ËĂ˘â‚¬Â Ă‚Â strony dostÄ‚â€žĂ˘â€žËpne przez przeglÄ‚â€žĂ˘â‚¬Â¦darkÄ‚â€žĂ˘â€žË (login, market, bank, sellÄ‚ËĂ˘â€šÂ¬Ă‚Â¦)
Ä‚ËĂ˘â‚¬ĹĄÄąâ€şÄ‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬Ä‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬ src/            Ä‚ËĂ˘â‚¬Â Ă‚Â serwisy PHP (logika biznesowa)
Ä‚ËĂ˘â‚¬ĹĄÄąâ€şÄ‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬Ä‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬ cron/           Ä‚ËĂ˘â‚¬Â Ă‚Â cron/tick.php (uruchamiany co ~5 min)
Ä‚ËĂ˘â‚¬ĹĄÄąâ€şÄ‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬Ä‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬ assets/         Ä‚ËĂ˘â‚¬Â Ă‚Â CSS, JS, obrazy
Ä‚ËĂ˘â‚¬ĹĄÄąâ€şÄ‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬Ä‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬ templates/      Ä‚ËĂ˘â‚¬Â Ă‚Â header.php (topbar nawigacji)
Ä‚ËĂ˘â‚¬ĹĄÄąâ€şÄ‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬Ä‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬ config/         Ä‚ËĂ˘â‚¬Â Ă‚Â database.php (dane poĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â‚¬Â¦czenia)
Ä‚ËĂ˘â‚¬ĹĄÄąâ€şÄ‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬Ä‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬ admin/          Ä‚ËĂ˘â‚¬Â Ă‚Â panel admina
Ä‚ËĂ˘â‚¬ĹĄÄąâ€şÄ‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬Ä‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬ profile.php     Ä‚ËĂ˘â‚¬Â Ă‚Â strona profilu gracza
Ä‚ËĂ˘â‚¬ĹĄÄąâ€şÄ‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬Ä‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬ hr.php          Ä‚ËĂ˘â‚¬Â Ă‚Â panel HR (zarzÄ‚â€žĂ˘â‚¬Â¦dzanie pracownikami)
Ä‚ËĂ˘â‚¬ĹĄÄąâ€şÄ‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬Ä‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬ dashboard.php   Ä‚ËĂ˘â‚¬Â Ă‚Â gĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šwna strona gry
Ä‚ËĂ˘â‚¬ĹĄÄąâ€şÄ‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬Ä‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬ boardroom.php   Ä‚ËĂ˘â‚¬Â Ă‚Â sala zarzÄ‚â€žĂ˘â‚¬Â¦du
Ä‚ËĂ˘â‚¬ĹĄÄąâ€şÄ‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬Ä‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬ technical.php   Ä‚ËĂ˘â‚¬Â Ă‚Â panel techniczny (incydenty, odwierty)
Ä‚ËĂ˘â‚¬ĹĄĂ˘â‚¬ĹĄÄ‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬Ä‚ËĂ˘â‚¬ĹĄĂ˘â€šÂ¬ htaccess        Ä‚ËĂ˘â‚¬Â Ă‚Â plik do wgrania na serwer jako .htaccess
```

### Kluczowe serwisy (`src/`)
| Serwis | Opis |
|---|---|
| `WellService` | Produkcja, degradacja, wear, spirala |
| `IncidentService` | Generowanie i obsĂ„Ä…Ă˘â‚¬Ĺˇuga awarii |
| `MarketTick` | Obliczanie ceny ropy co tick |
| `MarketTrend` | ZarzÄ‚â€žĂ˘â‚¬Â¦dzanie trendami rynkowymi |
| `HRService` | Rekrutacja, zatrudnianie, kontrakty |
| `GeologicalLayerService` | Warstwy geologiczne, zmiana warstwy |
| `BankService` / `LoanRepository` | Kredyty, raty, komornik |
| `BankruptcyService` | Bankructwo, restrukturyzacja |
| `RegionalEventService` | Zdarzenia regionalne |
| `TechnicalTeamService` | ZespĂ„â€šÄąâ€šĂ„Ä…Ă˘â‚¬Ĺˇ techniczny, BHP, zadania, powiadomienia |
| `RiskScoreEngine` | Ocena ryzyka kredytowego |
| `DirectorNotificationService` | Powiadomienia dla dyrektora (`director_notifications`) |
| `WellStaffService` | Przypisanie personelu do odwiertĂ„â€šÄąâ€šw, transport |

### Autoloading
`src/init.php` rejestruje `spl_autoload_register` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ klasy Ă„Ä…Ă˘â‚¬Ĺˇadowane automatycznie po nazwie pliku z `src/`.

---

## 2. Routing i .htaccess

### Clean URLs
Wszystkie strony majÄ‚â€žĂ˘â‚¬Â¦ "czyste" URLe bez `.php`:
```
/dashboard   Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ dashboard.php
/hr          Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ hr.php
/profile     Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ profile.php
/technical   Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ technical.php
/market      Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ public/market.php
/bank        Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ public/bank.php
/sell        Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ public/sell.php
/map         Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ public/map.php
/dm          Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ dm.php
...
```

### WAĂ„Ä…Ă‚Â»NE Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ plik `htaccess`
Plik w repozytorium nazywa siÄ‚â€žĂ˘â€žË `htaccess` (bez kropki, bo Windows nie pozwala tworzyÄ‚â€žĂ˘â‚¬Ë‡ plikĂ„â€šÄąâ€šw zaczynajÄ‚â€žĂ˘â‚¬Â¦cych siÄ‚â€žĂ˘â€žË od `.`).

**Na serwerze produkcyjnym musi byÄ‚â€žĂ˘â‚¬Ë‡ wgrany jako `.htaccess`** w katalogu gĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šwnym `public_html/`.

### Mapa routingu (`src/init.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ staĂ„Ä…Ă˘â‚¬Ĺˇa `ROUTES`)
```php
const ROUTES = [
    'home'            => '/',
    'dashboard'       => '/dashboard',
    'profile'         => '/profile',
    'hr'              => '/hr',
    'technical'       => '/technical',
    'market'          => '/market',
    'bank'            => '/bank',
    'sell'            => '/sell',
    'map'             => '/map',
    'boardroom'       => '/boardroom',
    'loans'           => '/loans',
    ...
];
```
UĂ„Ä…Ă„Ëťycie: `url('profile')` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ `/profile`

### Zmiany (03.04.2026)
- Dodano reguĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â€žË `RewriteRule ^profile$ /profile.php [L,PT]` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ wczeĂ„Ä…Ă˘â‚¬Ĺźniej `/profile` zwracaĂ„Ä…Ă˘â‚¬Ĺˇo 404

### Zmiany (06Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›07.04.2026)
- Dodano reguĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â€žË `RewriteRule ^dm$ /dm.php [L,PT]` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ strona wiadomoĂ„Ä…Ă˘â‚¬Ĺźci prywatnych
- `src/.htaccess` usuniÄ‚â€žĂ˘â€žËty Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ caĂ„Ä…Ă˘â‚¬Ĺˇa ochrona katalogu `src/` przeniesiona do gĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šwnego `.htaccess`
- Whitelist `/src/` rozszerzona o: `ChatApi.php`, `DmApi.php`, `TechNotifApi.php`

### Zmiany (22.04.2026)
- **Technical team** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ status odwiertu w dropdown "Zlec zadanie" teraz wyĂ„Ä…Ă˘â‚¬Ĺźwietlany z polskimi etykietami (zamiast surowego `paused_storage`, `paused_cash` etc.)
- **Mapa** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ klikniÄ‚â€žĂ˘â€žËcie na wĂ„Ä…Ă˘â‚¬Ĺˇasny odwiert na mapie teraz wyĂ„Ä…Ă˘â‚¬Ĺźwietla szczegĂ„â€šÄąâ€šĂ„Ä…Ă˘â‚¬Ĺˇy: status, produkcja, stan techniczny, poziom + link do panelu technicznego
- **Cache-Control** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ dodanie `Cache-Control: no-store` do `Security::setHeaders()` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ naprawia problem z nieodĂ„Ä…Ă˘â‚¬ĹźwieĂ„Ä…Ă„ËťajÄ‚â€žĂ˘â‚¬Â¦cÄ‚â€žĂ˘â‚¬Â¦ siÄ‚â€žĂ˘â€žË gotĂ„â€šÄąâ€šwkÄ‚â€žĂ˘â‚¬Â¦ po zmianie przez admina (przeglÄ‚â€žĂ˘â‚¬Â¦darka nie cachuje stron)
- **technical.php** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ naprawa bĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â€žËdu `$db = null` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ inicjalizacja `$db` przeniesiona przed pierwsze uĂ„Ä…Ă„Ëťycie (wczeĂ„Ä…Ă˘â‚¬Ĺźniej byĂ„Ä…Ă˘â‚¬Ĺˇa dopiero przy pobieraniu pipelines)

---

## 3. Autentykacja i sesje

- `Auth::requireLogin()` na kaĂ„Ä…Ă„Ëťdej chronionej stronie
- Bcrypt dla haseĂ„Ä…Ă˘â‚¬Ĺˇ
- Reset hasĂ„Ä…Ă˘â‚¬Ĺˇa przez e-mail (token jednorazowy)
- CSRF token (`window.WG_CSRF` / `CSRF::validateToken()`) na kaĂ„Ä…Ă„Ëťdym endpoincie AJAX

### Bugfix (06.04.2026)
- **`public/register.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ Duplicate entry username** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ INSERT nie podawaĂ„Ä…Ă˘â‚¬Ĺˇ kolumny `username` (ma `DEFAULT ''` + `UNIQUE`); drugi gracz dostawaĂ„Ä…Ă˘â‚¬Ĺˇ bĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â‚¬Â¦d; naprawiono przez auto-generowanie username z czÄ‚â€žĂ˘â€žËĂ„Ä…Ă˘â‚¬Ĺźci emaila przed `@` (suffix liczby jeĂ„Ä…Ă˘â‚¬Ĺźli zajÄ‚â€žĂ˘â€žËte)

---

## 4. Odwierty (Wells)

### Statusy odwiertu (`wells.status`)
| Status | Opis |
|---|---|
| `active` | Produkuje normalnie |
| `contaminated` | SkaĂ„Ä…Ă„Ëťony, produkuje z karÄ‚â€žĂ˘â‚¬Â¦ |
| `no_operator` | Brak operatora |
| `no_technician` | Brak technika |
| `paused_staff` | Brak minimum kadrowego |
| `paused_cash` | Brak gotĂ„â€šÄąâ€šwki na OPEX |
| `paused_storage` | Magazyn peĂ„Ä…Ă˘â‚¬Ĺˇny |
| `broken` | Zerowy stan techniczny Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ odwiert zatrzymany, nie nalicza OPEX, wymaga naprawy |
| `seized` | ZajÄ‚â€žĂ˘â€žËty przez komornika |
| `blowout` | Zniszczony (katastrofa) |
| `layer_switch` | W trakcie wiercenia do nowej warstwy |
| `sold` | Sprzedany Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ niewidoczny na liĂ„Ä…Ă˘â‚¬Ĺźcie gracza, lokalizacja ponownie wolna na mapie |

### FormuĂ„Ä…Ă˘â‚¬Ĺˇa produkcji (`WellService::getEffectiveProduction`)
```
produkcja = base_production
  Ă„â€šĂ˘â‚¬â€ť region_richness
  Ă„â€šĂ˘â‚¬â€ť equipment_mult
  Ă„â€šĂ˘â‚¬â€ť operator_skill_mult     (skill 1Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘70%, 5Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘100%, 10Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘130%)
  Ă„â€šĂ˘â‚¬â€ť layer_richness_mult     (shallowĂ„â€šĂ˘â‚¬â€ť0.70 Ä‚ËĂ˘â€šÂ¬Ă‚Â¦ ultraĂ„â€šĂ˘â‚¬â€ť2.80)
  Ă„â€šĂ˘â‚¬â€ť technical_condition/100
  Ă„â€šĂ˘â‚¬â€ť incident_prod_drop      (jeĂ„Ä…Ă˘â‚¬Ĺźli aktywny incydent)
  Ă„â€šĂ˘â‚¬â€ť regional_event_mult
```

---

## 4b. System sprzÄ‚â€žĂ˘â€žËtu (Equipment Tiers)

KaĂ„Ä…Ă„Ëťdy odwiert ma tier sprzÄ‚â€žĂ˘â€žËtu i poziom upgrade:
- `equipment_tier`: `black_market` / `standard` / `premium`
- `equipment_upgrade_level`: 0Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›3 (kaĂ„Ä…Ă„Ëťdy poziom: +5% prod, Ä‚ËĂ‚ÂĂ˘â‚¬â„˘10% awarii, Ä‚ËĂ‚ÂĂ˘â‚¬â„˘10% wear)

### MnoĂ„Ä…Ă„Ëťniki tierĂ„â€šÄąâ€šw

| Tier | Produkcja | Awarie | Wear | Spirala |
|------|-----------|--------|------|---------|
| Ă„â€ÄąĹźĂ˘â‚¬ĹĄĂ‚Â´ Czarny rynek | Ä‚ËĂ‚ÂĂ˘â‚¬â„˘10% | +70% | +60% | +20% |
| Ă„â€ÄąĹźÄąĹźĂ‹â€ˇ Standard | 0% | +40% | +20% | 0% |
| Ă„â€ÄąĹźÄąĹźĂ‹Â Premium | +10% | Ä‚ËĂ‚ÂĂ˘â‚¬â„˘30% | Ä‚ËĂ‚ÂĂ˘â‚¬â„˘40% | Ä‚ËĂ‚ÂĂ˘â‚¬â„˘15% |

### Koszty
| Operacja | Koszt |
|----------|-------|
| Czarny rynek | 500 000 PLN |
| Standard | 2 000 000 PLN |
| Premium | 8 000 000 PLN |
| Upgrade lvl 1 | 30 000 000 PLN |
| Upgrade lvl 2 | 60 000 000 PLN |
| Upgrade lvl 3 | 100 000 000 PLN |

### Implementacja
- `WellService::getEquipmentMultipliers(tier, upgradeLevel)` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ zwraca mnoĂ„Ä…Ă„Ëťniki
- `WellService::getEffectiveProduction()` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ uĂ„Ä…Ă„Ëťywa `prod` mult
- `WellService::processWear()` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ uĂ„Ä…Ă„Ëťywa `wear` mult
- `IncidentService::processTick()` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ uĂ„Ä…Ă„Ëťywa `incident` mult
- `WellService::addSpiralBoost()` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ uĂ„Ä…Ă„Ëťywa `spiral` mult
- `cron/tick.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ pobiera `eqMults` i stosuje do produkcji, degradacji, wear i spirali
- UI: panel Ä‚ËÄąË‡Ă˘â€žËĂ„ĹąĂ‚Â¸ÄąÄ… SprzÄ‚â€žĂ˘â€žËt w `well_grid.php` (zmiana tieru, upgrade)

## 4c. System transportu odwiertĂ„â€šÄąâ€šw

KaĂ„Ä…Ă„Ëťdy odwiert ma przypisany typ transportu ropy do magazynu. Transport wpĂ„Ä…Ă˘â‚¬Ĺˇywa na przepustowoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡, OPEX i ryzyko awarii.

### Kolumny w tabeli `wells`
| Kolumna | Typ | DomyĂ„Ä…Ă˘â‚¬Ĺźlnie | Opis |
|---------|-----|-----------|------|
| `transport_type` | enum('rurociag','ciezarowki','tankowiec') | 'rurociag' | Typ transportu |
| `transport_capacity_pct` | decimal(5,2) | 120.00 | PrzepustowoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ jako % produkcji |
| `transport_opex_pct` | decimal(5,2) | 7.50 | OPEX transportu jako % wartoĂ„Ä…Ă˘â‚¬Ĺźci ropy |

### Parametry typĂ„â€šÄąâ€šw

| Typ | Ikona | PrzepustowoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ | OPEX | Awarie |
|-----|-------|--------------|------|--------|
| RurociÄ‚â€žĂ˘â‚¬Â¦g | Ă„â€ÄąĹźĂ˘â‚¬ĹĄĂ‚Âµ | 120% produkcji | 7.5% wartoĂ„Ä…Ă˘â‚¬Ĺźci ropy | Ä‚ËĂ‚ÂĂ˘â‚¬â„˘20% |
| CiÄ‚â€žĂ˘â€žËĂ„Ä…Ă„ËťarĂ„â€šÄąâ€šwki | Ă„â€ÄąĹźÄąĹźĂ‹â€ˇ | 70% produkcji | 20.0% wartoĂ„Ä…Ă˘â‚¬Ĺźci ropy | +30% |
| Tankowiec | Ă„â€ÄąĹźÄąĹźĂ‹Â | 110% produkcji | 12.0% wartoĂ„Ä…Ă˘â‚¬Ĺźci ropy | 0% |

### FormuĂ„Ä…Ă˘â‚¬Ĺˇa w tick.php
```
sprzedaĂ„Ä…Ă„Ëť = MIN(produkcja_tick, produkcja_tick Ă„â€šĂ˘â‚¬â€ť transport_capacity_pct / 100)
opex_transport = sprzedaĂ„Ä…Ă„Ëť Ă„â€šĂ˘â‚¬â€ť oil_price Ă„â€šĂ˘â‚¬â€ť transport_opex_pct / 100
```

### Implementacja
- **Migracja SQL**: `transport_migration.sql` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ dodaje 3 kolumny do `wells`
- **`cron/tick.php`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ pobiera transport per odwiert, ogranicza produkcjÄ‚â€žĂ˘â€žË przez capacity, pobiera OPEX, przekazuje `transport_incident_mult` do IncidentService
- **`IncidentService::processTick()`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ mnoĂ„Ä…Ă„Ëťy szansÄ‚â€žĂ˘â€žË incydentu przez `transport_incident_mult` (rurociÄ‚â€žĂ˘â‚¬Â¦g Ă„â€šĂ˘â‚¬â€ť0.8, ciÄ‚â€žĂ˘â€žËĂ„Ä…Ă„ËťarĂ„â€šÄąâ€šwki Ă„â€šĂ˘â‚¬â€ť1.3, tankowiec Ă„â€šĂ˘â‚¬â€ť1.0)
- **`WellStaffApi.php`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ endpoint `set_transport`: zmiana typu per odwiert
- **`well_grid.php`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ panel Ă„â€ÄąĹźÄąË‡Ă˘â‚¬Ĺź Transport z aktualnym typem i przeĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â‚¬Â¦czaniem
- **`well_grid.js`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `wgToggleTransport()`, `wgSetTransport()` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ fetch do `/src/WellStaffApi.php`
- **`style.css`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ klasy `.wg-transport-wrap`, `.wg-transport-toggle`, `.wg-transport-body`

### Zmiana transportu
Gracz zmienia transport klikajÄ‚â€žĂ˘â‚¬Â¦c **Ă„â€ÄąĹźÄąË‡Ă˘â‚¬Ĺź Transport Ä‚ËĂ˘â‚¬â€śĂ„Ëť** w karcie odwiertu. Zmiana natychmiastowa (bez kosztu).

---

## 4d. SprzedaĂ„Ä…Ă„Ëť odwiertĂ„â€šÄąâ€šw

Gracz moĂ„Ä…Ă„Ëťe sprzedaÄ‚â€žĂ˘â‚¬Ë‡ dowolny odwiert (z wyjÄ‚â€žĂ˘â‚¬Â¦tkiem `seized`, `blowout`, `sold`) i otrzymaÄ‚â€žĂ˘â‚¬Ë‡ jednorazowÄ‚â€žĂ˘â‚¬Â¦ wypĂ„Ä…Ă˘â‚¬ĹˇatÄ‚â€žĂ˘â€žË.

### Wycena (`WellService::calculateSellValue`)

Podstawa: `profit_per_hour Ă„â€šĂ˘â‚¬â€ť 24h Ă„â€šĂ˘â‚¬â€ť 1.2` (szacowany zwrot za dobÄ‚â€žĂ˘â€žË z premiÄ‚â€žĂ˘â‚¬Â¦ 20%).

Modyfikatory:
| Czynnik | Zakres | Efekt |
|---------|--------|-------|
| Stan techniczny (`condition`) | 0Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›100% | Ä‚ËĂ‚ÂĂ˘â‚¬â„˘30% do +10% |
| ZuĂ„Ä…Ă„Ëťycie (`wear_level`) | 0Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›100 | 0% do Ä‚ËĂ‚ÂĂ˘â‚¬â„˘20% |
| Risk score | 0Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›100 | 0% do Ä‚ËĂ‚ÂĂ˘â‚¬â„˘25% |
| Tier sprzÄ‚â€žĂ˘â€žËtu | black_market/standard/premium | Ä‚ËĂ‚ÂĂ˘â‚¬â„˘10% / 0% / +15% |
| GĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â€žËbokoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ (`depth_m`) | 0Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›8000 m | 0% do +20% |
| Spirala katastrof | `post_incident_risk_boost` > 0 | do Ä‚ËĂ‚ÂĂ˘â‚¬â„˘15% |

### Cooldown
Nie moĂ„Ä…Ă„Ëťna sprzedaÄ‚â€žĂ˘â‚¬Ë‡ odwiertu kupionego mniej niĂ„Ä…Ă„Ëť **2 godziny temu** (`created_at`).

### Wykonanie sprzedaĂ„Ä…Ă„Ëťy (`WellService::sellWell`)
1. `UPDATE wells SET status='sold', sold_at=NOW()`
2. `UPDATE players SET cash = cash + sell_value`
3. Wpis do `bankruptcy_events` (typ `well_sold`)
4. Wpis do `admin_logs`

### Zachowanie po sprzedaĂ„Ä…Ă„Ëťy
- `wells.status` = `sold`, `sold_at` = NOW()
- Odwiert **znika z listy gracza** (zapytania filtrujÄ‚â€žĂ˘â‚¬Â¦ `status != 'sold'`)
- Lokalizacja (`location_id`) staje siÄ‚â€žĂ˘â€žË **ponownie wolna** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `WorldMap::isLocationAvailable()` zwraca `true`
- `WorldMap::getAvailableLocations()` i `WorldMap::getOccupiedLocations()` nie uwzglÄ‚â€žĂ˘â€žËdniajÄ‚â€žĂ˘â‚¬Â¦ `sold`
- Karta odwiertu usuwa siÄ‚â€žĂ˘â€žË z DOM animacjÄ‚â€žĂ˘â‚¬Â¦ opacity+scale (bez reload strony); toast `wgShowSoldToast` pokazuje zarobionÄ‚â€žĂ˘â‚¬Â¦ kwotÄ‚â€žĂ˘â€žË

### Ponowny zakup tej samej lokalizacji
- Gracz (lub inny) moĂ„Ä…Ă„Ëťe kupiÄ‚â€žĂ˘â‚¬Ë‡ lokalizacjÄ‚â€žĂ˘â€žË ponownie Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ powstaje **nowy rekord** `wells`
- Nowy odwiert **dziedziczy stan zĂ„Ä…Ă˘â‚¬ĹˇoĂ„Ä…Ă„Ëťa** (`reservoir_extracted_bbl`) z poprzedniego rekordu tej lokalizacji (jeĂ„Ä…Ă˘â‚¬Ĺźli `locations.reservoir_bbl` jest skoĂ„Ä…Ă˘â‚¬Ĺľczone)
- JeĂ„Ä…Ă˘â‚¬Ĺźli poprzedni odwiert wyeksploatowaĂ„Ä…Ă˘â‚¬Ĺˇ zĂ„Ä…Ă˘â‚¬ĹˇoĂ„Ä…Ă„Ëťe (`reservoir_pct < 5%`) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ zakup jest nadal moĂ„Ä…Ă„Ëťliwy, ale wycena sprzedaĂ„Ä…Ă„Ëťy bÄ‚â€žĂ˘â€žËdzie bardzo niska; `world_map.js` powinien ostrzegaÄ‚â€žĂ˘â‚¬Ë‡ gracza (TODO)

### Implementacja
- **`src/WellSellApi.php`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ endpoint REST:
  - `GET ?well_id=N` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ wycena (bez zmiany stanu); zwraca `{ sell_value, breakdown, reservoir_pct }`
  - `POST {well_id, csrf_token}` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ wykonanie sprzedaĂ„Ä…Ă„Ëťy (walidacja CSRF); zwraca `{ success, sell_value }`
- **`src/WellService.php`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `calculateSellValue()`, `sellWell()`
- **`templates/components/well_grid.php`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ przycisk Ă„â€ÄąĹźĂ˘â‚¬â„˘Ă‚Â° Sprzedaj odwiert na gĂ„â€šÄąâ€šrze sekcji szczegĂ„â€šÄąâ€šĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šw karty (warunek: status nie w `seized`, `blowout`, `sold`)
- **`assets/js/well_grid.js`**:
  - `wgSellPreview(wellId)` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ fetch GET Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ buduje `bodyHtml` z breakdown + pasek zĂ„Ä…Ă˘â‚¬ĹˇoĂ„Ä…Ă„Ëťa (`wg-sell-reservoir`) + `wg-sell-note`; wywoĂ„Ä…Ă˘â‚¬Ĺˇuje `confirmAction()` z `modal.js`
  - `wgConfirmSell(wellId)` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ fetch POST Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ animuje usuniÄ‚â€žĂ˘â€žËcie karty (`#wg-card-{id}`), czyĂ„Ä…Ă˘â‚¬Ĺźci pusty region, wywoĂ„Ä…Ă˘â‚¬Ĺˇuje `wgShowSoldToast()`
  - `wgShowSoldToast(earned)` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ toast dolny z kwotÄ‚â€žĂ˘â‚¬Â¦, auto-znika po 3.5s
- **`assets/css/style.css`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `.wg-sell-wrap`, `.wg-btn-sell`, `.wg-sell-breakdown`, `.wg-sell-row`, `.wg-sell-minus`, `.wg-sell-plus`, `.wg-sell-total`, `.wg-sell-price`, `.wg-sell-reservoir`, `.wg-sell-res-bar`, `.wg-sell-res-val`, `.wg-sold-toast`, `.wg-sold-toast--show`

### UI Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ flow gracza
1. Kliknij **Ä‚ËĂ˘â‚¬â€śĂ„Ëť szczegĂ„â€šÄąâ€šĂ„Ä…Ă˘â‚¬Ĺˇy** na karcie odwiertu
2. Kliknij **Ă„â€ÄąĹźĂ˘â‚¬â„˘Ă‚Â° Sprzedaj odwiert**
3. Modal (`confirmAction` z `modal.js`) pokazuje breakdown wyceny, cenÄ‚â€žĂ˘â€žË koĂ„Ä…Ă˘â‚¬ĹľcowÄ‚â€žĂ˘â‚¬Â¦ i (jeĂ„Ä…Ă˘â‚¬Ĺźli < 100%) pasek zasobĂ„â€šÄąâ€šw zĂ„Ä…Ă˘â‚¬ĹˇoĂ„Ä…Ă„Ëťa
4. Kliknij **PotwierdĂ„Ä…ÄąĹş sprzedaĂ„Ä…Ă„Ëť** Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ Ă„Ä…Ă˘â‚¬Ĺźrodki trafiajÄ‚â€žĂ˘â‚¬Â¦ na konto natychmiast
5. Karta odwiertu znika animacjÄ‚â€žĂ˘â‚¬Â¦ (opacity 0 + scale 0.95 Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ `card.remove()`); jeĂ„Ä…Ă˘â‚¬Ĺźli region pusty Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ region teĂ„Ä…Ă„Ëť znika
6. Toast w dolnej czÄ‚â€žĂ˘â€žËĂ„Ä…Ă˘â‚¬Ĺźci ekranu: `Ä‚ËÄąâ€şĂ˘â‚¬Â¦ Odwiert sprzedany Ä‚â€šĂ‚Â· +{kwota} PLN`

### TODO
- `world_map.js`: przed zakupem lokalizacji sprawdziÄ‚â€žĂ˘â‚¬Ë‡ `reservoir_pct` poprzedniego odwiertu i pokazaÄ‚â€žĂ˘â‚¬Ë‡ ostrzeĂ„Ä…Ă„Ëťenie jeĂ„Ä…Ă˘â‚¬Ĺźli zĂ„Ä…Ă˘â‚¬ĹˇoĂ„Ä…Ă„Ëťe wyeksploatowane (< 10%)

---

## 5. Warstwy geologiczne

KaĂ„Ä…Ă„Ëťdy odwiert wierci w konkretnej warstwie geologicznej. Warstwa wpĂ„Ä…Ă˘â‚¬Ĺˇywa na produkcjÄ‚â€žĂ˘â€žË, ryzyko, zuĂ„Ä…Ă„Ëťycie i spiralÄ‚â€žĂ˘â€žË.

| Warstwa | Maks. gĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â€žËbokoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ | Zasoby | Richness | Ryzyko | ZuĂ„Ä…Ă„Ëťycie | Spirala | Koszt | PrzestĂ„â€šÄąâ€šj |
|---|---|---|---|---|---|---|---|---|
| PĂ„Ä…Ă˘â‚¬Ĺˇytka | 300 m | 100k bbl | Ă„â€šĂ˘â‚¬â€ť0.70 | Ă„â€šĂ˘â‚¬â€ť5.0 | Ă„â€šĂ˘â‚¬â€ť1.0 | Ă„â€šĂ˘â‚¬â€ť1.0 | bezpĂ„Ä…Ă˘â‚¬Ĺˇatna | 0h |
| Ă„Ä…ÄąË‡rodkowa | 3 000 m | 400k bbl | Ă„â€šĂ˘â‚¬â€ť1.30 | Ă„â€šĂ˘â‚¬â€ť7.5 | Ă„â€šĂ˘â‚¬â€ť1.3 | Ă„â€šĂ˘â‚¬â€ť1.2 | 25 mln PLN | 2h |
| GĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â€žËboka | 5 000 m | 1 mln bbl | Ă„â€šĂ˘â‚¬â€ť2.00 | Ă„â€šĂ˘â‚¬â€ť11.0 | Ă„â€šĂ˘â‚¬â€ť1.6 | Ă„â€šĂ˘â‚¬â€ť1.4 | 120 mln PLN | 4h |
| Ultra-gĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â€žËboka | 8 000 m | 2.5 mln bbl | Ă„â€šĂ˘â‚¬â€ť2.80 | Ă„â€šĂ˘â‚¬â€ť16.0 | Ă„â€šĂ˘â‚¬â€ť2.0 | Ă„â€šĂ˘â‚¬â€ť1.7 | 400 mln PLN | 6h |

### Zasady
- **PĂ„Ä…Ă˘â‚¬Ĺˇytka jest domyĂ„Ä…Ă˘â‚¬Ĺźlna** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ kaĂ„Ä…Ă„Ëťdy odwiert startuje na shallow (id=1)
- **Black market + deep/ultra** Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ +50% ryzyka awarii
- **Zmiana warstwy** Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ odwiert pauzowany na czas wiercenia (`layer_switch_until`)
- **Zasoby per warstwa** Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ Ă„Ä…Ă˘â‚¬Ĺźledzone w `layer_reservoir_used`

### Implementacja
- `GeologicalLayerService::getAllLayers()` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ z `try/catch` (fallback gdy brak tabeli)
- `GeologicalLayerService::getActiveLayer(wellId)` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ z `try/catch` (fallback gdy brak kolumny)
- `GeologicalLayerService::processSwitchCompletion()` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ wywoĂ„Ä…Ă˘â‚¬Ĺˇywane w kaĂ„Ä…Ă„Ëťdym ticku
- UI: panel w `well_grid.php`, endpoint AJAX `layer_well.php`
- JS: `wgToggleLayer()`, `wgSwitchLayer()`

---

### Aktualizacja (03.05.2026) - HR jako panel kadrowy, rekrutacja zarzadu w dashboardzie
- HR nie prowadzi juz rekrutacji kadry dyrektorskiej.
- Z `hr.php` usunieto widoczny flow: **Rekrutacja**, **Kandydaci** i stary formularz nowej rekrutacji.
- `dashboard.php` przejal:
  - start rekrutacji dyrektorow,
  - liste kandydatow zarzadu,
  - aktywne procesy rekrutacji dyrektorskiej,
  - decyzje zatrudnij / odrzuc.
- HRApi.php blokuje stary flow initiated_by='hr' komunikatem hr.recruitment_moved_to_dashboard.
- `src/HR/HiringTrait.php` i `src/HR/DataTrait.php` zostaly doszczelnione:
  - kandydaci i requesty sa scope'owane do player_id,
  - finalizacja zatrudnienia sprzata tylko wlasciwego kandydata / `request_id`,
  - nie ma juz ryzyka czyszczenia obcych kandydatow po samej roli.
- Mobilny i waski layout poprawiono:
  - pasek zakladek HR ma poziomy scroll i nie ucina juz `Headhunter`,
  - karta pracownika nie wychodzi poza ekran na telefonie,
  - dashboard dyrektora ma pelnoszerokie akcje i lepsze zawijanie formularza.

### Aktualizacja (02.05.2026) - rozdzielenie HR i zarzadu

- Dodano osobna zakladke **Zarzad** w HR (widok read-only).
- Zakladka **Pracownicy** pokazuje tylko personel operacyjny (bez czlonkow zarzadu).
- W `board_members` wprowadzono `member_type` (`director` / `staff`) i na tym oparto filtrowanie:
  - dostep do dzialow (`BoardAccess`) liczy wylacznie `director`,
  - dane HR rozdzielaja `staff` vs `director`.
- Rekrutacja HR wymaga `spec_code` (`HRApi.php` - walidacja).
- Skrocono czas rekrutacji do minut: `local` 120-240 s, `international` 180-300 s.
- Usunieto auto-refresh aktywnych rekrutacji; karta nowej rekrutacji dopina sie dynamicznie bez przeladowania.
- Poprawiono lokalny timer odliczania (bez bledu przesuniecia czasu o ~2h).
- Dodano akcje `fire_technical_staff` dla `technical_staff`.
- Uzupelniono i18n w `lang/pl.php` (`hr.tab_directors`, `hr.field_specialization`, `hr.recruitment_started`, `hr.err_missing_specialization`, rozszerzone `hr.spec.*`).


### Panel HR (`hr.php` + `assets/js/hr.js`)
Zakladki:
- **Pracownicy** - lista aktywnego personelu operacyjnego
- **Zarzad** - read-only podglad obsadzonej kadry dyrektorskiej
- **Kontrakty** - zarzadzanie umowami
- **Historia** - log zdarzen kadrowych
- **Rynek pracy** - regiony i modyfikatory rynku pracy
- **Headhunter** - zlecenia na technicznych ekspertow
### Panel dyrektora (`dashboard.php`)
Od 03.05.2026 dashboard jest jedynym miejscem dla rekrutacji zarzadu:
- **Rekrutacja kadry dyrektorskiej** - formularz wyboru stanowiska i regionu
- **CV oczekujace na Twoja decyzje** - kandydaci zarzadu
- **Aktywne rekrutacje** - procesy initiated_by='director'
- **Zarzad** - aktywnie zatrudnieni dyrektorzy
- **Ostatnie zdarzenia** - historia ostatnich decyzji
### Zatrudnianie kandydata (`hireCandidate`)
Po klikniÄ‚â€žĂ˘â€žËciu "Zatrudnij":
1. AJAX Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ `HRApi.php` action=`hire_candidate`
2. Toast z potwierdzeniem
3. Karta kandydata znika z animacjÄ‚â€žĂ˘â‚¬Â¦ (fade + scale)
4. Licznik na zakĂ„Ä…Ă˘â‚¬Ĺˇadce "Kandydaci" maleje o 1
5. **Strona NIE jest przeĂ„Ä…Ă˘â‚¬Ĺˇadowywana** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ gracz zostaje na zakĂ„Ä…Ă˘â‚¬Ĺˇadce Kandydaci

**Zmiana (03.04.2026):** WczeĂ„Ä…Ă˘â‚¬Ĺźniej `hireCandidate` wywoĂ„Ä…Ă˘â‚¬ĹˇywaĂ„Ä…Ă˘â‚¬Ĺˇo `location.reload()` co przenosiĂ„Ä…Ă˘â‚¬Ĺˇo gracza z powrotem na zakĂ„Ä…Ă˘â‚¬ĹˇadkÄ‚â€žĂ˘â€žË Rekrutacja. Teraz karta jest usuwana z DOM bez reload.

### Pracownicy przypisani do odwiertĂ„â€šÄąâ€šw
- `operator_id` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ wpĂ„Ä…Ă˘â‚¬Ĺˇywa na produkcjÄ‚â€žĂ˘â€žË (skill_level)
- `technician_id` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ wpĂ„Ä…Ă˘â‚¬Ĺˇywa na degradacjÄ‚â€žĂ˘â€žË (skill_level)
- Brak operatora Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ status `no_operator`, brak produkcji
- Brak technika Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ status `no_technician`, +50% degradacji

### RĂ„â€šÄąâ€šwnolegĂ„Ä…Ă˘â‚¬Ĺˇa rekrutacja (02.05.2026)

Gracz moĂ„Ä…Ă„Ëťe prowadziÄ‚â€žĂ˘â‚¬Ë‡ **maksymalnie 2 rekrutacje jednoczeĂ„Ä…Ă˘â‚¬Ĺźnie**, w rĂ„â€šÄąâ€šĂ„Ä…Ă„Ëťnych dziaĂ„Ä…Ă˘â‚¬Ĺˇach.

#### Ograniczenia (walidacja po stronie serwera Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `src/HRApi.php`)
- Limit 2 aktywnych procesĂ„â€šÄąâ€šw (status `pending` lub `ready`) Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ bĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â‚¬Â¦d `hr.err_max_recruitments`
- Nie moĂ„Ä…Ă„Ëťna rekrutowaÄ‚â€žĂ˘â‚¬Ë‡ dwukrotnie na tÄ‚â€žĂ˘â€žË samÄ‚â€žĂ˘â‚¬Â¦ rolÄ‚â€žĂ˘â€žË Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ bĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â‚¬Â¦d `hr.err_role_already_recruiting`

#### Formularz nowej rekrutacji (`templates/views/hr/main.php`)
Formularz `.new-recruit-card` wyĂ„Ä…Ă˘â‚¬Ĺźwietlany pod listÄ‚â€žĂ˘â‚¬Â¦ aktywnych procesĂ„â€šÄąâ€šw w zakĂ„Ä…Ă˘â‚¬Ĺˇadce Rekrutacja:
- **WskaĂ„Ä…ÄąĹşnik slotĂ„â€šÄąâ€šw** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ badge kolorowy (zielony 0/2, zĂ„Ä…Ă˘â‚¬Ĺˇoty 1/2, czerwony 2/2): `"Aktywne rekrutacje: X / 2"`
- **Dropdown rĂ„â€šÄąâ€šl** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ filtruje automatycznie role juĂ„Ä…Ă„Ëť obsadzone przez pracownika i bÄ‚â€žĂ˘â€žËdÄ‚â€žĂ˘â‚¬Â¦ce w rekrutacji
- **Dropdown specjalizacji** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ filtrowany przez `nrFilterSpecializations()` do dziaĂ„Ä…Ă˘â‚¬Ĺˇu wybranej roli
- **Siatka regionĂ„â€šÄąâ€šw** (`.nr-region-grid`) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ klikalnie z pre-selekcjÄ‚â€žĂ˘â‚¬Â¦ pierwszego regionu
- Gdy limit 2/2 osiÄ‚â€žĂ˘â‚¬Â¦gniÄ‚â€žĂ˘â€žËty Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ formularz zastÄ‚â€žĂ˘â€žËpowany alertem `.hr-alert--warn`
- Gdy wszystkie role obsadzone Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ alert `.hr-alert--info`

#### JS (`assets/js/hr.js`)
| Funkcja | Opis |
|---------|------|
| `startNewRecruitment()` | WysyĂ„Ä…Ă˘â‚¬Ĺˇa `action=start_recruitment`; po sukcesie dodaje kartÄ‚â€žĂ˘â€žË i aktualizuje UI |
| `addRecruitmentCard(rec)` | Tworzy kartÄ‚â€žĂ˘â€žË z timerem (obsĂ„Ä…Ă˘â‚¬Ĺˇuguje HH:MM:SS dla rekrutacji >60 min); usuwa rolÄ‚â€žĂ˘â€žË z dropdownu; wywoĂ„Ä…Ă˘â‚¬Ĺˇuje `_nrUpdateSlotsUI()` |
| `_nrUpdateSlotsUI()` | Aktualizuje badge slotĂ„â€šÄąâ€šw + chowa formularz gdy 2/2; uĂ„Ä…Ă„Ëťywa `hrl()` dla i18n |
| `nrFilterSpecializations()` | Chowa opcje spec niezgodne z dziaĂ„Ä…Ă˘â‚¬Ĺˇem wybranej roli |
| `nrSelectRegion(el)` | Zaznacza kartÄ‚â€žĂ˘â€žË regionu i ustawia `#nr-region` hidden input |
| `switchTab(name)` *(fix)* | Teraz poprawnie re-aktywuje przycisk zakĂ„Ä…Ă˘â‚¬Ĺˇadki (wczeĂ„Ä…Ă˘â‚¬Ĺźniej tylko panel treĂ„Ä…Ă˘â‚¬Ĺźci) |

#### CSS (`assets/css/hr.css`)
| Klasa | Opis |
|-------|------|
| `.nrc-slots-badge` | Badge wskaĂ„Ä…ÄąĹşnika slotĂ„â€šÄąâ€šw |
| `.nrc-slots-free` | Zielony (0 aktywnych) |
| `.nrc-slots-partial` | ZĂ„Ä…Ă˘â‚¬Ĺˇoty (1 aktywna) |
| `.nrc-slots-full` | Czerwony (2 aktywne Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ limit) |
| `.hr-alert` | Ramka alertu wewnÄ‚â€žĂ˘â‚¬Â¦trz formularza |
| `.hr-alert--warn` | Wersja zĂ„Ä…Ă˘â‚¬Ĺˇota (ostrzeĂ„Ä…Ă„Ëťenie o limicie) |
| `.hr-alert--info` | Wersja niebieska (brak dostÄ‚â€žĂ˘â€žËpnych rĂ„â€šÄąâ€šl) |

#### i18n (`lang/pl.php`)
| Klucz | WartoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ |
|-------|---------|
| `hr.err_max_recruitments` | Maksymalnie 2 rekrutacje mogÄ‚â€žĂ˘â‚¬Â¦ trwaÄ‚â€žĂ˘â‚¬Ë‡ jednoczeĂ„Ä…Ă˘â‚¬Ĺźnie. |
| `hr.err_role_already_recruiting` | Rekrutacja na to stanowisko jest juĂ„Ä…Ă„Ëť w toku. |
| `hr.slots_indicator` | Aktywne rekrutacje: %d / 2 |
| `hr.max_slots_reached` | OsiÄ‚â€žĂ˘â‚¬Â¦gniÄ‚â€žĂ˘â€žËto limit 2 rĂ„â€šÄąâ€šwnolegĂ„Ä…Ă˘â‚¬Ĺˇych rekrutacjiÄ‚ËĂ˘â€šÂ¬Ă‚Â¦ |
| `hr.nr_role_label` | DziaĂ„Ä…Ă˘â‚¬Ĺˇ / stanowisko |
| `hr.nr_region_label` | Region rekrutacji |
| `hr.nr_spec_label` | Specjalizacja |
| `hr.nr_no_roles_available` | Wszystkie stanowiska sÄ‚â€žĂ˘â‚¬Â¦ juĂ„Ä…Ă„Ëť obsadzone lub w trakcie rekrutacji. |

### Bugfix (05.04.2026)
- **`hr.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `$db` undefined** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `$db` uĂ„Ä…Ă„Ëťywane w auto-cleanup rekrutacji przed inicjalizacjÄ‚â€žĂ˘â‚¬Â¦; dodano `$db = Database::getInstance()->getConnection()` na poczÄ‚â€žĂ˘â‚¬Â¦tku pliku

### Bugfix (02.05.2026)
- **`src/HRApi.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `$db` undefined** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ zmienna `$db` uĂ„Ä…Ă„Ëťywana w walidacji `start_recruitment` (max 2 rekrutacje) ale nigdy zainicjalizowana; kaĂ„Ä…Ă„Ëťde wywoĂ„Ä…Ă˘â‚¬Ĺˇanie koĂ„Ä…Ă˘â‚¬ĹľczyĂ„Ä…Ă˘â‚¬Ĺˇo siÄ‚â€žĂ˘â€žË fatal error; naprawione przez dodanie `$db = Database::getInstance()->getConnection()` po inicjalizacji serwisĂ„â€šÄąâ€šw

---

## 7. System degradacji

### FormuĂ„Ä…Ă˘â‚¬Ĺˇa (`WellService::processDegradation`)
```
degradacja/tick = base_deg
  Ă„â€šĂ˘â‚¬â€ť political_risk_mult
  Ă„â€šĂ˘â‚¬â€ť wear_mult
  Ă„â€šĂ˘â‚¬â€ť brak_technika (+50% jeĂ„Ä…Ă˘â‚¬Ĺźli brak)
  Ă„â€šĂ˘â‚¬â€ť monitoring_mult
  Ă„â€šĂ˘â‚¬â€ť hse_mult
  Ă„â€šĂ˘â‚¬â€ť spirala_mult
```

### Stan techniczny (`technical_condition`)
- 100% = idealny
- <40% = wysokie ryzyko awarii
- 0% = odwiert zatrzymany

---

## 8. System awarii i incydentĂ„â€šÄąâ€šw

### Poziomy
| Poziom | Produkcja | Naprawa | CzÄ‚â€žĂ˘â€žËstotliwoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ |
|---|---|---|---|
| `micro` | -5-10% | Automatyczna | Bardzo czÄ‚â€žĂ˘â€žËsto |
| `minor` | -20% | Automatyczna | CzÄ‚â€žĂ˘â€žËsto |
| `medium` | -50% | RÄ‚â€žĂ˘â€žËczna | Umiarkowanie |
| `major` | Stop | RÄ‚â€žĂ˘â€žËczna | Rzadko |

### FormuĂ„Ä…Ă˘â‚¬Ĺˇa szansy incydentu (`IncidentService::processTick`)
```
chance = max(
  BASE_CHANCE_PER_HOUR[level] Ă„â€šĂ˘â‚¬â€ť deltaHours Ă„â€šĂ˘â‚¬â€ť eq_mult Ă„â€šĂ˘â‚¬â€ť layer_risk_mult Ă„â€šĂ˘â‚¬â€ť wear_mult Ă„â€šĂ˘â‚¬â€ť spiral_mult,
  FLOOR_CHANCE_PER_TICK       (tylko micro)
)
```

### Skalibrowane staĂ„Ä…Ă˘â‚¬Ĺˇe (02.04.2026)
```php
// Przy shallow(Ă„â€šĂ˘â‚¬â€ť5.0) + standard(Ă„â€šĂ˘â‚¬â€ť1.4) + tick 5min(0.0833h):
BASE_CHANCE_PER_HOUR = [
    'micro'  => 0.072,   // ~4.2%/tick Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ ~1 micro co 2h
    'minor'  => 0.029,   // ~1.7%/tick Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ ~1 minor co 5h
    'medium' => 0.0096,  // ~0.56%/tick Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ ~1 medium co 15h
    'major'  => 0.002,   // ~0.12%/tick Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ ~1 major co 70h
];
FLOOR_CHANCE_PER_TICK = 0.025; // min 2.5%/tick dla micro
```

**Zmiana (02.04.2026):** Przeliczono bazy z uwzglÄ‚â€žĂ˘â€žËdnieniem 5-minutowego ticku. Dodano `FLOOR_CHANCE_PER_TICK` jako staĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â‚¬Â¦ minimalnÄ‚â€žĂ˘â‚¬Â¦ (nie skalowanÄ‚â€žĂ˘â‚¬Â¦ przez `deltaHours`).

### Implementacja
- `IncidentService::processTick` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ gĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šwna logika
- `IncidentService::saveIncident` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ zapis do `well_incidents`
- `IncidentService::applyEffects` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ aktualizuje `technical_condition`, `post_incident_risk_boost`
- `technical.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ wyĂ„Ä…Ă˘â‚¬Ĺźwietla listÄ‚â€žĂ˘â€žË incydentĂ„â€šÄąâ€šw

---

## 9. Wear & Tear (ZuĂ„Ä…Ă„Ëťycie)

ZuĂ„Ä…Ă„Ëťycie odwiertu (`wear_level`) roĂ„Ä…Ă˘â‚¬Ĺźnie z produkcjÄ‚â€žĂ˘â‚¬Â¦. Wysokie zuĂ„Ä…Ă„Ëťycie zwiÄ‚â€žĂ˘â€žËksza ryzyko awarii i degradacjÄ‚â€žĂ˘â€žË.

### FormuĂ„Ä…Ă˘â‚¬Ĺˇa (`WellService::processWear`)
```
wear_gain = base_production_per_hour
  Ă„â€šĂ˘â‚¬â€ť richness_mult
  Ă„â€šĂ˘â‚¬â€ť wear_depth_factor   (z warstwy geologicznej)
  Ă„â€šĂ˘â‚¬â€ť (1 + spiral_wear_mult)
```

---

## 10. Spirala katastrof

Po kaĂ„Ä…Ă„Ëťdym incydencie (poza micro) odwiert wchodzi w "spiralÄ‚â€žĂ˘â€žË katastrof" Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ zwiÄ‚â€žĂ˘â€žËksza ryzyko kolejnych incydentĂ„â€šÄąâ€šw.

### Implementacja
- `WellService::addSpiralBoost` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ dodaje `post_incident_risk_boost` (mnoĂ„Ä…Ă„Ëťony przez `spiral_boost` warstwy)
- `WellService::processSpiralDecay` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ redukuje boost z czasem

### WyĂ„Ä…Ă˘â‚¬Ĺźwietlanie w UI
Spirala widoczna w szczegĂ„â€šÄąâ€šĂ„Ä…Ă˘â‚¬Ĺˇach odwiertu (`well_grid.php`) jako metryka **Ă„â€ÄąĹźÄąĹˇĂ˘â€šÂ¬ Spirala**:
- Pojawia siÄ‚â€žĂ˘â€žË gdy `post_incident_risk_boost > 0.5%`
- Ă„Ä…Ă‚Â»Ă„â€šÄąâ€šĂ„Ä…Ă˘â‚¬Ĺˇta: boost < 30%
- Czerwona: boost Ä‚ËĂ˘â‚¬Â°Ă„â€ž 30%
- Nie pojawia siÄ‚â€žĂ˘â€žË przy pauzowanych odwiertach (brak incydentĂ„â€šÄąâ€šw = brak spirali)

### Uwaga
Odwierty z `no_operator`, `paused_staff` etc. nie generujÄ‚â€žĂ˘â‚¬Â¦ incydentĂ„â€šÄąâ€šw w ticku Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ spirala nie roĂ„Ä…Ă˘â‚¬Ĺźnie gdy odwiert nie produkuje.

---

## 11. System rynku (Market)

### FormuĂ„Ä…Ă˘â‚¬Ĺˇa ceny (`MarketTick::updatePrices`)
```
ratio        = (player_supply + world_production) / effective_demand
sd_pressure  = (1 - ratio) Ă„â€šĂ˘â‚¬â€ť SENSITIVITY(0.40) Ă„â€šĂ˘â‚¬â€ť current_price
trend_target = clamp(base_price Ă„â€šĂ˘â‚¬â€ť price_modifier, MIN_PRICE, MAX_PRICE)
gravity      = (trend_target - current_price) Ă„â€šĂ˘â‚¬â€ť GRAVITY_RATE(0.03)
trend_shock  = (price_modifier - 1.0) Ă„â€šĂ˘â‚¬â€ť current_price Ă„â€šĂ˘â‚¬â€ť 0.05   [gdy |modifier-1| Ä‚ËĂ˘â‚¬Â°Ă„â€ž 0.25]
new_price    = clamp(current + sd_pressure + gravity + trend_shock + noise, 30, 300)
```

### Kluczowe staĂ„Ä…Ă˘â‚¬Ĺˇe
| StaĂ„Ä…Ă˘â‚¬Ĺˇa | WartoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ | Opis |
|---|---|---|
| `MIN_PRICE` | 30 | Minimalna cena ropy |
| `MAX_PRICE` | 300 | Maksymalna cena ropy |
| `SENSITIVITY` | 0.40 | SiĂ„Ä…Ă˘â‚¬Ĺˇa nacisku supply/demand |
| `GRAVITY_RATE` | 0.03 | SzybkoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ powrotu do celu trendu |

### Trendy rynkowe (`MarketTrend`)
Aktywny trend wpĂ„Ä…Ă˘â‚¬Ĺˇywa na cenÄ‚â€žĂ˘â€žË dwutorowo:
1. **Popyt**: `effective_demand = demand_index Ă„â€šĂ˘â‚¬â€ť price_modifier`
2. **Gravity target**: `trend_target = base_price Ă„â€šĂ˘â‚¬â€ť price_modifier`
3. **Szok cenowy**: przy silnym trendzie (`|modifier-1| Ä‚ËĂ˘â‚¬Â°Ă„â€ž 0.25`) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ dodatkowy impuls co tick

PrzykĂ„Ä…Ă˘â‚¬Ĺˇad Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ Pandemia (`price_modifier=0.60`):
- `trend_target = 100 Ă„â€šĂ˘â‚¬â€ť 0.60 = 60` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ gravity ciÄ‚â€žĂ˘â‚¬Â¦gnie cenÄ‚â€žĂ˘â€žË do $60
- `effective_demand` spada o 40% Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ oversupply Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ dodatkowa presja w dĂ„â€šÄąâ€šĂ„Ä…Ă˘â‚¬Ĺˇ
- `trend_shock` = ujemny (~-3$/tick przy cenie $100)

**Zmiana (02.04.2026):** WczeĂ„Ä…Ă˘â‚¬Ĺźniej `gravity` ciÄ‚â€žĂ˘â‚¬Â¦gnÄ‚â€žĂ˘â€žËĂ„Ä…Ă˘â‚¬Ĺˇo do `base_price` ignorujÄ‚â€žĂ˘â‚¬Â¦c trend. Teraz `gravity` ciÄ‚â€žĂ˘â‚¬Â¦gnie do `base_price Ă„â€šĂ˘â‚¬â€ť price_modifier`. Dodano `trendShock` dla natychmiastowej reakcji.

### Synchronizacja
`$newPrice` obliczony przez `MarketTick` na poczÄ‚â€žĂ˘â‚¬Â¦tku ticku jest uĂ„Ä…Ă„Ëťywany bezpoĂ„Ä…Ă˘â‚¬Ĺźrednio przy obliczeniu podatku regionalnego Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ bez dodatkowego `SELECT`.

---

## 12. System bankowy

### Kredyty (`BankService`, `LoanRepository`)
- Wymagania: konto >4 dni, brak bankructwa, odpowiednia ocena ryzyka
- Raty pobierane automatycznie w ticku
- Przekroczenie terminu Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ status `late` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ komornik

### Ocena ryzyka kredytowego (`RiskScoreEngine`)
Skala 0Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›115 pkt: odwierty, produkcja, magazyn, gotĂ„â€šÄąâ€šwka, zachowanie, rynek, historia, credit score.

### Negocjacje (`BankNegotiationService`)
Restrukturyzacja, umorzenie odsetek, wydĂ„Ä…Ă˘â‚¬ĹˇuĂ„Ä…Ă„Ëťenie okresu Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ decyzja probabilistyczna.

---

## 13. System bankructwa

| Status | Opis |
|---|---|
| `none` | Normalny |
| `restructuring` | Plan naprawczy aktywny |
| `liquidation` | Odwierty zajÄ‚â€žĂ˘â€žËte |
| `recovered` | WyszedĂ„Ä…Ă˘â‚¬Ĺˇ z bankructwa |

---

## 14. Komornik (Bailiff)

Gdy gracz nie spĂ„Ä…Ă˘â‚¬Ĺˇaca rat: kredyt `late` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ postÄ‚â€žĂ˘â€žËpowanie egzekucyjne Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ odwierty `seized` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ po spĂ„Ä…Ă˘â‚¬Ĺˇacie zwolnione.

---

## 15. Mapa Ă„Ä…Ă˘â‚¬Ĺźwiata i lokalizacje

Regiony z parametrami: `oil_richness`, `political_risk`, `regional_tax_rate`, `region_opex_mult`, `region_production_bonus`, `region_stability_bonus`.

Zdarzenia regionalne (`RegionalEventService`) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ kataklizmy, kryzysy polityczne, odkrycia zĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šĂ„Ä…Ă„Ëť.

---

## 16. System zadaĂ„Ä…Ă˘â‚¬Ĺľ technicznych

`TechnicalTeamService` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ naprawy awarii, konserwacja prewencyjna, wymiana sprzÄ‚â€žĂ˘â€žËtu. Czas trwania zaleĂ„Ä…Ă„Ëťy od poziomu uszkodzenia i dostÄ‚â€žĂ˘â€žËpnego personelu.

---

## 17. Cron Tick Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ serce gry

`cron/tick.php` uruchamiany co **~5 minut**.

### KolejnoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ operacji

#### 1. Gospodarka i rynek
- Aktualizacja popytu (`EconomyService`, co 30 min)
- Deaktywacja wygasĂ„Ä…Ă˘â‚¬Ĺˇych / aktywacja nowych trendĂ„â€šÄąâ€šw (`MarketTrend`)
- Obliczenie `$newPrice` (`MarketTick::updatePrices($activeTrend)`)

#### 2. System bankowy
- Odsetki, raty, komornik, decyzje kredytowe, negocjacje, plany naprawcze, `Headhunter`, bankruci

#### 2b. Odczyt globalnych mnoĂ„Ä…Ă„ËťnikĂ„â€šÄąâ€šw balansu
```php
// Przed pÄ‚â€žĂ˘â€žËtlÄ‚â€žĂ˘â‚¬Â¦ graczy Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ jednorazowo per tick
$gBalanceMults = ['incident'=>1.0, 'disaster'=>1.0, 'wear'=>1.0,
                  'degradation'=>1.0, 'loss'=>1.0, 'opex'=>1.0, 'production'=>1.0];
// Odczyt z well_config (klucze global_*_multiplier / global_*_mult)
// Fallback: 1.0 gdy tabela/klucz nie istnieje
```
Ustawiane przez `admin/balance.php`. Gdy wszystkie = 1.0 Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ tick zachowuje siÄ‚â€žĂ˘â€žË identycznie.

#### 3. KaĂ„Ä…Ă„Ëťdy gracz (pomijani bankruci)

```
Przed pÄ‚â€žĂ˘â€žËtlÄ‚â€žĂ˘â‚¬Â¦ odwiertĂ„â€šÄąâ€šw:
  - TechnicalTeamService: HSE bonus, staffCheck, processProcedureDecay
  - RegionalEventService: resolveExpired, processTick, getActiveEvents
  - PotrÄ‚â€žĂ˘â‚¬Â¦cenie pensji (zarzÄ‚â€žĂ˘â‚¬Â¦d + technicy, przeliczone na deltaHours)

Dla kaĂ„Ä…Ă„Ëťdego odwiertu:
  a) ZakoĂ„Ä…Ă˘â‚¬Ĺľczenie wiercenia warstwy (processSwitchCompletion)
  b) Sprawdzenie minimum kadrowego Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ paused_staff / wznowienie
  c) Weryfikacja operatora i technika (czy nie zwolnieni)
  d) Degradacja stanu technicznego
  e) Aktualizacja risk_score
  f) Incydenty (IncidentService::processTick)
     - BASE_CHANCE_PER_HOUR Ă„â€šĂ˘â‚¬â€ť deltaHours Ă„â€šĂ˘â‚¬â€ť eq_mult Ă„â€šĂ˘â‚¬â€ť layer_risk_mult Ă„â€šĂ˘â‚¬â€ť wear_mult Ă„â€šĂ˘â‚¬â€ť spiral_mult
     - Ă„â€šĂ˘â‚¬â€ť transport_incident_mult Ă„â€šĂ˘â‚¬â€ť gBalanceMults['incident']
     - floor: min 2.5%/tick dla micro
  g) Wear & Tear (WellService::processWear)
     - Ă„â€šĂ˘â‚¬â€ť transportWearMult Ă„â€šĂ˘â‚¬â€ť gBalanceMults['wear']
  h) Decay spirali katastrof
  i) Disaster roll (blowout, pipeline explosion, surface spill)
     - Ă„â€šĂ˘â‚¬â€ť transportDisasterMult Ă„â€šĂ˘â‚¬â€ť gBalanceMults['disaster']
  j) OPEX Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ jeĂ„Ä…Ă˘â‚¬Ĺźli brak kasy: paused_cash
  k) Efektywna produkcja (WellService::getEffectiveProduction)
     - Ă„â€šĂ˘â‚¬â€ť gBalanceMults['production']
  l) Transport loss (rurociÄ‚â€žĂ˘â‚¬Â¦g infrastrukturalny Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `pipelines`)
     - lostOil Ă„â€šĂ˘â‚¬â€ť gBalanceMults['loss']
  l2) Transport per odwiert:
     - `sprzedaĂ„Ä…Ă„Ëť = MIN(produkcja, produkcja Ă„â€šĂ˘â‚¬â€ť transport_capacity_pct%)`
     - OPEX transportu: `sprzedaĂ„Ä…Ă„Ëť Ă„â€šĂ˘â‚¬â€ť oil_price Ă„â€šĂ˘â‚¬â€ť transport_opex_pct% Ă„â€šĂ˘â‚¬â€ť gBalanceMults['opex']`
     - `transport_incident_mult` przekazywany do IncidentService
  l3) Zdarzenia transportowe (theft/accident/storm/leak/pressure_drop):
     - WywoĂ„Ä…Ă˘â‚¬Ĺˇywane **przed** zapisem do magazynu i liczeniem finansĂ„â€šÄąâ€šw
     - ModyfikujÄ‚â€žĂ˘â‚¬Â¦ `$actual` przez referencjÄ‚â€žĂ˘â€žË Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ faktycznie redukujÄ‚â€žĂ˘â‚¬Â¦ przychĂ„â€šÄąâ€šd i produkcjÄ‚â€žĂ˘â€žË
     - RĂ„â€šÄąâ€šĂ„Ä…Ă„Ëťnica `$actualBefore - $actual` trafia do `finance_logs.loss_bbl` i `loss_value`
     - `finance_logs.gross_revenue` = produkcja przed zdarzeniem Ă„â€šĂ˘â‚¬â€ť cena
  m) Zapis do magazynu Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ paused_storage jeĂ„Ä…Ă˘â‚¬Ĺźli peĂ„Ä…Ă˘â‚¬Ĺˇny
  n) Podatek regionalny: actual Ă„â€šĂ˘â‚¬â€ť $newPrice Ă„â€šĂ˘â‚¬â€ť tax_rate
```

### CzÄ‚â€žĂ˘â€žËstotliwoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ efektywna
- Tick co 5 min = 12 tickĂ„â€šÄąâ€šw/godzinÄ‚â€žĂ˘â€žË
- `delta_hours` = czas od `last_tick_at` gracza (odporny na opĂ„â€šÄąâ€šĂ„Ä…ÄąĹşnienia)
- Max `delta_hours` = 24h (zabezpieczenie)

### Bugfixy tick (22.04.2026)
- **`PipelineSection`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `spec_code` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ `specialization` w sprawdzeniu inĂ„Ä…Ă„Ëťyniera rurociÄ‚â€žĂ˘â‚¬Â¦gĂ„â€šÄąâ€šw
- **`WellLoopSection`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ dodano `'broken'` do skip listy statusĂ„â€šÄąâ€šw odwiertĂ„â€šÄąâ€šw
- **`WellLoopSection`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ zdarzenia transportowe wywoĂ„Ä…Ă˘â‚¬Ĺˇywane przed zapisem finansĂ„â€šÄąâ€šw (theft/accident majÄ‚â€žĂ˘â‚¬Â¦ teraz realny efekt)
- **`WellLoopSection`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `finGross/finLossBbl/finLossValue` teraz poprawnie wypeĂ„Ä…Ă˘â‚¬Ĺˇniane w `finance_logs`
- **`BankSection`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `WHERE status != 'bankrupt'` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ `status = 'bankrupt'` w selekcji graczy do BankruptcyService
- **`LoanDecisionService`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `?? null`/`?? 0` przy dostÄ‚â€žĂ˘â€žËpie do `$breakdown['market']`
- **`CostsTrait`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `'turbo'` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ `'boost'` w match trybu produkcji (Ă„â€šĂ˘â‚¬â€ť1.40 dziaĂ„Ä…Ă˘â‚¬Ĺˇa poprawnie)

---

## 18. Panel admina

DostÄ‚â€žĂ˘â€žËpny pod `/admin/`. Wymaga osobnego logowania (`AdminAuth`).

### IstniejÄ‚â€žĂ˘â‚¬Â¦ce strony
| Plik | URL | Opis |
|---|---|---|
| `index.php` | `/admin` | Dashboard Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ statystyki, quick links |
| `players.php` | `/admin/players` | ZarzÄ‚â€žĂ˘â‚¬Â¦dzanie graczami |
| `market.php` | `/admin/market` | RÄ‚â€žĂ˘â€žËczna zmiana ceny, trendy rynkowe |
| `loans.php` | `/admin/loans` | Panel bankowy i kredyty |
| `wells.php` | `/admin/wells` | ZarzÄ‚â€žĂ˘â‚¬Â¦dzanie odwiertami |
| `gm_tools.php` | `/admin/gm_tools` | GM Tools (reset, testy) |
| `logs.php` | `/admin/logs` | Logi gry (`game_debug.log`) |
| `chat.php` | `/admin/chat` | Moderacja czatu Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ historia, usuwanie, ban/mute, zgĂ„Ä…Ă˘â‚¬Ĺˇoszenia |
| `finance.php` | `/admin/finance` | DziaĂ„Ä…Ă˘â‚¬Ĺˇ finansowy Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ globalne statystyki, per-gracz, mnoĂ„Ä…Ă„Ëťniki |
| `financial-crisis.php` | `/admin/financial-crisis` | Kryzys finansowy Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ lista firm w warning/crisis, config, akcje |

### Konfiguracja gry (`well_config`)
Tabela klucz-wartoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ do przechowywania parametrĂ„â€šÄąâ€šw gry modyfikowanych bez deploy:
- Globalne mnoĂ„Ä…Ă„Ëťniki balansu (patrz Ä‚â€šĂ‚Â§18b)
- Dowolne klucze konfiguracji uĂ„Ä…Ă„Ëťywane przez serwisy

### Inicjalizacja
`admin/init.php` Ă„Ä…Ă˘â‚¬Ĺˇaduje `src/init.php` + klasy admina: `AdminAuth`, `AdminLog`, `BankSettings`.

### BezpieczeĂ„Ä…Ă˘â‚¬Ĺľstwo
- Osobna sesja, osobne hasĂ„Ä…Ă˘â‚¬Ĺˇo admina
- CSRF protection (`CSRF::field()` / `CSRF::validateToken()`)
- KaĂ„Ä…Ă„Ëťda akcja logowana przez `AdminLog::log($action, $detail)`

### Bugfixy (06.04.2026)
- **`src/AdminLog.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ TypeError** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `GameLog::error()` wywoĂ„Ä…Ă˘â‚¬Ĺˇywane z array zamiast `?Throwable` jako 3. argument; naprawiono
- **`admin/chat.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ ENUM truncation** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ username admina jako `target_type`; naprawiono: `'player'` + `$pid` dla akcji na graczu, pominiÄ‚â€žĂ˘â€žËcie dla akcji globalnych

---

## 18b. Panel admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ sekcje transportu i balansu

Dodane w sesji 05.04.2026. Nawigacja admina podzielona na dwie grupy linkĂ„â€šÄąâ€šw (separator `|`).

### Nowe strony
| Plik | URL | Opis | Priorytet |
|---|---|---|---|
| `transport.php` | `/admin/transport` | Konfiguracja mnoĂ„Ä…Ă„ËťnikĂ„â€šÄąâ€šw per typ transportu | MUST |
| `transport_loss.php` | `/admin/transport_loss` | Monitoring strat transportowych (global, per gracz, per odwiert) | MUST |
| `market_debug.php` | `/admin/market_debug` | Debug rynku: cena, supply/demand, historia tickĂ„â€šÄąâ€šw | MUST |
| `pipelines.php` | `/admin/pipelines` | Panel rurociÄ‚â€žĂ˘â‚¬Â¦gĂ„â€šÄąâ€šw: stan, naprawa, wymuszanie awarii | SHOULD |
| `alerts.php` | `/admin/alerts` | Alerty systemowe z progami (cena, loss, warunki, gracze) | SHOULD |
| `balance.php` | `/admin/balance` | Quick Balance Panel Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ globalne mnoĂ„Ä…Ă„Ëťniki bez deploy | SHOULD |

### admin/transport.php
- Edytowalne mnoĂ„Ä…Ă„Ëťniki per typ (rurociÄ‚â€žĂ˘â‚¬Â¦g / ciÄ‚â€žĂ˘â€žËĂ„Ä…Ă„ËťarĂ„â€šÄąâ€šwki / tankowiec): `incident`, `disaster`, `wear`, `spiral`, `capacity_pct`, `opex_pct`
- Persystencja w tabeli `transport_config` (plik migracji: `sql/transport_config.sql`)
- Masowy UPDATE odwiertĂ„â€šÄąâ€šw danego typu (capacity/OPEX)
- Graceful fallback gdy tabela nie istnieje Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ wyĂ„Ä…Ă˘â‚¬Ĺźwietla SQL do utworzenia

### admin/transport_loss.php
- Globalne pipeline loss (avg, max, krytyczne >15%)
- Loss per typ transportu z szacowanym kosztem OPEX/h
- Loss per warstwa geologiczna Ă„â€šĂ˘â‚¬â€ť typ
- Loss per gracz z transport mix
- Top 20 najgorszych odwiertĂ„â€šÄąâ€šw

### admin/market_debug.php
- Stan rynku: cena, base price, volatility, world_production, demand_index
- Globalna produkcja odwiertĂ„â€šÄąâ€šw per typ transportu
- Statystyki magazynĂ„â€šÄąâ€šw, szacowany pipeline loss
- Historia ceny z `price_history` (tabela, kolumny: `price`, `created_at`)
- Historia supply/demand z `market_supply_demand_log`
- Ekonomia per gracz (produkcja, magazyn, transport mix, status)
- Szacunkowy bilans supply/demand

### Historia ceny
`MarketTick::savePriceHistory` zapisuje kaĂ„Ä…Ă„Ëťdy tick do tabeli `price_history` (kolumny: `price INT`, `created_at DATETIME`).
`MarketTick::getPriceHistory(hours)` zwraca historiÄ‚â€žĂ˘â€žË z ostatnich N godzin (domyĂ„Ä…Ă˘â‚¬Ĺźlnie 24h).

### Historia supply/demand
`MarketTick::saveSupplyDemandLog` zapisuje do `market_supply_demand_log` (kolumny: `supply`, `demand`, `ratio`, `price`, `created_at`). Rekordy starsze niĂ„Ä…Ă„Ëť `LOG_KEEP_DAYS=7` dni sÄ‚â€žĂ˘â‚¬Â¦ automatycznie usuwane.

### admin/pipelines.php
- Lista wszystkich `pipelines` z condition%, loss%, status, historia awarii
- Akcje: napraw, ustaw loss%, wymuĂ„Ä…Ă˘â‚¬Ĺź awariÄ‚â€žĂ˘â€žË
- Mass repair wszystkich krytycznych (condition < 30%)

> Ä‚ËÄąË‡Ă‚Â Ă„ĹąĂ‚Â¸ÄąÄ… **Schemat `players`:** kolumna loginu to `username` (nie `login`). We wszystkich zapytaniach SQL w panelach admina naleĂ„Ä…Ă„Ëťy uĂ„Ä…Ă„ËťywaÄ‚â€žĂ˘â‚¬Ë‡ `p.username` / `pl.username`. Alias `AS login` moĂ„Ä…Ă„Ëťe byÄ‚â€žĂ˘â‚¬Ë‡ stosowany dla kompatybilnoĂ„Ä…Ă˘â‚¬Ĺźci z kodem PHP.

### admin/alerts.php
Automatyczne alerty z progami:
| Typ alertu | PrĂ„â€šÄąâ€šg krytyczny | PrĂ„â€šÄąâ€šg ostrzegawczy |
|---|---|---|
| Cena ropy | > $140 lub < $40 | > $120 lub < $60 |
| Pipeline loss | > 15% | > 8% |
| Stan rurociÄ‚â€žĂ˘â‚¬Â¦gĂ„â€šÄąâ€šw | condition < 30% | condition < 50% |
| Odwierty | tech_condition < 20% | tech_condition < 40% |
| Blowout | > 0 aktywnych | Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ |
| Magazyny | > 95% pojemnoĂ„Ä…Ă˘â‚¬Ĺźci | > 85% |
| Gracze ujemna kasa | > 0 | Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ |
| Cron zatrzymany | > 30 min bez ticku | > 15 min |

### admin/balance.php Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ Quick Balance Panel
7 globalnych mnoĂ„Ä…Ă„ËťnikĂ„â€šÄąâ€šw zapisywanych w `well_config`, odczytywanych przez `cron/tick.php`:

| Klucz w `well_config` | SkrĂ„â€šÄąâ€št w ticku | Zastosowanie |
|---|---|---|
| `global_incident_multiplier` | `incident` | `IncidentService::processTick` |
| `global_disaster_multiplier` | `disaster` | `WellService::processDisasterRoll` |
| `global_wear_multiplier` | `wear` | `WellService::processWear` (Ă„â€šĂ˘â‚¬â€ť2 wywoĂ„Ä…Ă˘â‚¬Ĺˇania) |
| `global_degradation_mult` | `degradation` | `WellService::processDegradation` |
| `global_loss_multiplier` | `loss` | transport loss ($lostOil) |
| `global_opex_multiplier` | `opex` | transport OPEX ($transportOpex) |
| `global_production_mult` | `production` | `WellService::getEffectiveProduction` |

DomyĂ„Ä…Ă˘â‚¬Ĺźlnie wszystkie = `1.0` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ tick zachowuje siÄ‚â€žĂ˘â€žË identycznie jak przed wdroĂ„Ä…Ă„Ëťeniem.
Zmiana widoczna od **nastÄ‚â€žĂ˘â€žËpnego ticku** crona.

**Emergency Hotfix** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ jednym klikniÄ‚â€žĂ˘â€žËciem ustawia grupÄ‚â€žĂ˘â€žË mnoĂ„Ä…Ă„ËťnikĂ„â€šÄąâ€šw na podany wspĂ„â€šÄąâ€šĂ„Ä…Ă˘â‚¬Ĺˇczynnik:
- Ă„â€ÄąĹźĂ˘â‚¬ĹĄĂ‚Â´ Incydenty + Katastrofy
- Ă„â€ÄąĹźĂ˘â‚¬â„˘Ă‚Â§ Loss + OPEX
- Ä‚ËÄąË‡Ă‚Â Ă„ĹąĂ‚Â¸ÄąÄ… Wszystkie ryzyka
- Ä‚ËĂ‚Â¬Ă˘â‚¬Â Ă„ĹąĂ‚Â¸ÄąÄ… Produkcja (buff)

---

## 18c. Panel admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ HR (`admin/hr.php`)

Dodano w sesji 12Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›13.04.2026. WielozakĂ„Ä…Ă˘â‚¬Ĺˇadkowy panel zarzÄ‚â€žĂ˘â‚¬Â¦dzania danymi HR z widokiem i edycjÄ‚â€žĂ˘â‚¬Â¦ kandydatĂ„â€šÄąâ€šw, historii zatrudnienia, statystyk HR graczy oraz sĂ„Ä…Ă˘â‚¬ĹˇownikĂ„â€šÄąâ€šw specjalizacji.

### Pliki

| Plik | Rola |
|------|------|
| `admin/hr.php` | Kontroler Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ routing zakĂ„Ä…Ă˘â‚¬Ĺˇadek, obsĂ„Ä…Ă˘â‚¬Ĺˇuga POST, pobieranie danych |
| `templates/views/admin/hr/main.php` | Widok Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ HTML zakĂ„Ä…Ă˘â‚¬Ĺˇadek, formularze, siatki danych |
| `assets/css/admin.css` | Style HR panelu (`.spec-card`, `.spec-group`, `.add-spec-form`, `.btn-danger` itd.) |
| `lang/pl.php` | Klucze i18n dla caĂ„Ä…Ă˘â‚¬Ĺˇego panelu HR (`admin.hr.*`) |

### ZakĂ„Ä…Ă˘â‚¬Ĺˇadki

| ZakĂ„Ä…Ă˘â‚¬Ĺˇadka | URL parametr | Opis |
|----------|-------------|------|
| Kandydaci | `?tab=candidates` | Lista aktywnych kandydatĂ„â€šÄąâ€šw z filtrem |
| Historia | `?tab=history` | Historia zatrudnienia (per gracz, paginacja) |
| Statystyki HR | `?tab=stats` | Statystyki HR per gracz |
| Specjalizacje | `?tab=specializations` | Edycja `staff_specializations` i `hr_specializations` |

### ZakĂ„Ä…Ă˘â‚¬Ĺˇadka Specjalizacje Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `staff_specializations`

Specjalizacje techniczne pogrupowane po polu `role` w osobnych sekcjach:

- **Ä‚ËĂ˘â‚¬ĹźÄąÄ… Operatorzy** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `role = operator`
- **Ă„â€ÄąĹźĂ˘â‚¬ĹĄĂ‚Â§ Technicy** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `role = technician`
- **Ă„â€ÄąĹźĂ˘â‚¬Ĺ›Ă‚Â PozostaĂ„Ä…Ă˘â‚¬Ĺˇe** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ pozostaĂ„Ä…Ă˘â‚¬Ĺˇe wartoĂ„Ä…Ă˘â‚¬Ĺźci `role`

KaĂ„Ä…Ă„Ëťda specjalizacja wyĂ„Ä…Ă˘â‚¬Ĺźwietlana jako `<details>` (`.spec-card`). Po rozwiniÄ‚â€žĂ˘â€žËciu Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ formularz edycji z polami:

| Pole | Opis |
|------|------|
| `spec_name` | Polska nazwa (edytowalna, zapisywana w `staff_specializations.name`) |
| `prod_bonus` | Bonus produkcji (0.0Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›1.0) |
| `wear_reduction` | Redukcja zuĂ„Ä…Ă„Ëťycia (0.0Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›1.0) |
| `incident_reduction` | Redukcja incydentĂ„â€šÄąâ€šw (0.0Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›1.0) |
| `spiral_reduction` | Redukcja spirali (0.0Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›1.0) |
| `repair_speed` | SzybkoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ naprawy (0.0Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›1.0) |
| `incident_return_reduction` | Redukcja powrotu awarii (0.0Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›1.0) |
| `catastrophe_reduction` | Redukcja katastrof (0.0Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›1.0) |

**Dodawanie nowej specjalizacji technicznej** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ formularz z polami: `code` (snake_case), `name` (PL), `role`, `rarity`. POST handler: `add_spec`.

**Usuwanie** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ przycisk **UsuĂ„Ä…Ă˘â‚¬Ĺľ** z modalem potwierdzenia (`confirmAction`, `type:'danger'`). POST handler: `delete_spec`.

### ZakĂ„Ä…Ă˘â‚¬Ĺˇadka Specjalizacje Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `hr_specializations`

Specjalizacje kandydatĂ„â€šÄąâ€šw HR pogrupowane po polu `department` w osobnych sekcjach z nagĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šwkiem `Ă„â€ÄąĹźĂ˘â‚¬Ĺ›Ă˘â‚¬Ĺˇ {dziaĂ„Ä…Ă˘â‚¬Ĺˇ}`.

KaĂ„Ä…Ă„Ëťdy wiersz edytowalny inline: `name`, `department`, `rarity` (select). Przyciski: **Zapisz** i **UsuĂ„Ä…Ă˘â‚¬Ĺľ**.

**Dodawanie** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ formularz z polami: `code`, `name` (PL), `department`, `rarity`. POST handler: `add_hr_spec`.

**Usuwanie** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ modal potwierdzenia. POST handler: `delete_hr_spec`.

### POST handlery (`admin/hr.php`)

| `$_POST` key | Akcja |
|---|---|
| `add_spec` | INSERT do `staff_specializations` (walidacja: code/name wymagane, unique) |
| `delete_spec` | DELETE FROM `staff_specializations` WHERE code |
| `save_spec` | UPDATE `staff_specializations` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ nazwa + wszystkie pola perkĂ„â€šÄąâ€šw |
| `add_hr_spec` | INSERT do `hr_specializations` |
| `delete_hr_spec` | DELETE FROM `hr_specializations` WHERE id |
| `save_hr_spec` | UPDATE `hr_specializations` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ name, department, rarity |

KaĂ„Ä…Ă„Ëťdy handler: CSRF (`CSRF::validateToken()`), `AdminLog::log()`, try/catch z komunikatem bĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â€žËdu.

### Konwencja pola `code`

- Snake_case, tylko `[a-z0-9_]`
- Musi byÄ‚â€žĂ˘â‚¬Ë‡ unikalny w obrÄ‚â€žĂ˘â€žËbie tabeli
- PrzykĂ„Ä…Ă˘â‚¬Ĺˇady: `drilling_specialist`, `safety_bph`, `hr_legal_analyst`
- UĂ„Ä…Ă„Ëťywany jako klucz w `technical_staff.specialization` (Ă„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â‚¬Â¦cznik miÄ‚â€žĂ˘â€žËdzy tabelami)

### CSS (nowe klasy w `assets/css/admin.css`)

| Klasa | Zastosowanie |
|-------|-------------|
| `.spec-card` | Karta `<details>` specjalizacji technicznej |
| `.spec-card-summary` | NagĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šwek karty (flex: nazwa, kod, badge rzadkoĂ„Ä…Ă˘â‚¬Ĺźci) |
| `.spec-form` | Formularz edycji wewnÄ‚â€žĂ˘â‚¬Â¦trz karty |
| `.spec-fields` | Grid pĂ„â€šÄąâ€šl formularza (auto-fill, min 200px) |
| `.spec-field--full` | Pole na peĂ„Ä…Ă˘â‚¬ĹˇnÄ‚â€žĂ˘â‚¬Â¦ szerokoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ gridu (nazwa PL) |
| `.spec-group` | Kontener sekcji (np. Operatorzy) |
| `.spec-group-title` | NagĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šwek sekcji Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ uppercase, border-bottom |
| `.spec-group-inline` | Kontener sekcji dla hr_specializations |
| `.spec-group-subtitle` | NagĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šwek podsekecji dziaĂ„Ä…Ă˘â‚¬Ĺˇu |
| `.spec-delete-form` | Formularz usuwania (flex, justify-content: flex-end) |
| `.add-spec-form` | Formularz dodawania nowej specjalizacji |
| `.add-spec-fields` | Flex-wrap pola formularza dodawania |
| `.spec-field--action` | Kontener przycisku submit (padding-top wyrĂ„â€šÄąâ€šwnuje z inputami) |
| `.btn-danger` | Czerwony przycisk usuwania |

### i18n Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ nowe klucze (`lang/pl.php`)

```
admin.hr.section_staff_specs       admin.hr.section_hr_specs
admin.hr.staff_specs_desc          admin.hr.hr_specs_desc
admin.hr.btn_add_spec              admin.hr.add_spec_hint
admin.hr.field_code                admin.hr.field_name_pl
admin.hr.field_role                admin.hr.field_prod_bonus
admin.hr.field_wear_reduction      admin.hr.field_incident_reduction
admin.hr.field_spiral_reduction    admin.hr.field_repair_speed
admin.hr.field_incident_return     admin.hr.field_catastrophe_reduction
admin.hr.col_spec_name             admin.hr.col_department
admin.hr.col_rarity                admin.hr.confirm_delete_spec
admin.hr.msg_spec_saved            admin.hr.msg_spec_deleted
admin.hr.msg_spec_added            admin.hr.msg_hrspec_added
admin.hr.msg_hrspec_deleted        admin.hr.err_spec_empty
admin.hr.err_spec_duplicate        common.delete
```

### Modal systemu potwierdzeĂ„Ä…Ă˘â‚¬Ĺľ

Przyciski **UsuĂ„Ä…Ă˘â‚¬Ĺľ** uĂ„Ä…Ă„ËťywajÄ‚â€žĂ˘â‚¬Â¦ `confirmAction()` z `modal.js` (globalny system zastÄ‚â€žĂ˘â€žËpujÄ‚â€žĂ˘â‚¬Â¦cy `confirm()`):

```js
confirmAction('Czy na pewno usunÄ‚â€žĂ˘â‚¬Â¦Ä‚â€žĂ˘â‚¬Ë‡...?', function() {
    document.getElementById('del-spec-{code}').submit();
}, { type: 'danger', confirmLabel: 'UsuĂ„Ä…Ă˘â‚¬Ĺľ' });
```

- `modal.js` Ă„Ä…Ă˘â‚¬Ĺˇadowany przez `admin/partials/footer.php` (dodano `require_once footer.php` do `admin/hr.php`)
- Formularz delete ma unikalny `id` (`del-spec-{code}` / `del-hs-{id}`) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ niezaleĂ„Ä…Ă„Ëťny od `display:contents`

---

## 18d. Panel admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ moderacja czatu (`admin/chat.php`)

Dodane w sesji 06.04.2026.

### Funkcje
- PodglÄ‚â€žĂ˘â‚¬Â¦d historii czatu z filtrem (gracz, data, paginacja)
- Soft delete wiadomoĂ„Ä…Ă˘â‚¬Ĺźci (`is_deleted=1`) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ usuniÄ‚â€žĂ˘â€žËte wiadomoĂ„Ä…Ă˘â‚¬Ĺźci niewidoczne dla graczy
- Ban/mute gracza na czas (1h/3h/12h/1d/3d/7d/14d/30d/90d) lub permanentnie
- Odblokowanie gracza
- WysyĂ„Ä…Ă˘â‚¬Ĺˇanie komunikatu jako `[ADMIN]` (`player_id=NULL`)
- Czyszczenie caĂ„Ä…Ă˘â‚¬Ĺˇego czatu globalnego (soft delete)
- Top nadawcĂ„â€šÄąâ€šw, statystyki
- **Otwarte zgĂ„Ä…Ă˘â‚¬Ĺˇoszenia** (`chat_reports`) z akcjami: UsuĂ„Ä…Ă˘â‚¬Ĺľ wiadomoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ / Oznacz jako rozwiÄ‚â€žĂ˘â‚¬Â¦zane

---

## 19. Profil gracza

### Strona `/profile` (`profile.php`)
- Zmiana hasĂ„Ä…Ă˘â‚¬Ĺˇa, nazwy firmy, upload avatara
- Statystyki: liczba odwiertĂ„â€šÄąâ€šw, aktywne odwierty, historia sprzedaĂ„Ä…Ă„Ëťy

### Licznik aktywnych odwiertĂ„â€šÄąâ€šw
Liczy odwierty ze wszystkimi statusami operacyjnymi Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ wykluczone sÄ‚â€žĂ˘â‚¬Â¦ tylko `seized` i `blowout`:
```sql
SELECT COUNT(*) FROM wells
WHERE player_id = ? AND status NOT IN ('seized', 'blowout')
```

**Zmiana (03.04.2026):** WczeĂ„Ä…Ă˘â‚¬Ĺźniej liczono tylko `status='active'` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ odwierty z `no_operator`, `paused_cash` etc. byĂ„Ä…Ă˘â‚¬Ĺˇy ignorowane, co dawaĂ„Ä…Ă˘â‚¬Ĺˇo wynik 0 przy aktywnych odwiertach.

---

## 20. Sala ZarzÄ‚â€žĂ˘â‚¬Â¦du (Boardroom)

`boardroom.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ sala zarzÄ‚â€žĂ˘â‚¬Â¦du z dynamicznym tĂ„Ä…Ă˘â‚¬Ĺˇem zmieniajÄ‚â€žĂ˘â‚¬Â¦cym siÄ‚â€žĂ˘â€žË w zaleĂ„Ä…Ă„ËťnoĂ„Ä…Ă˘â‚¬Ĺźci od zatrudnionych pracownikĂ„â€šÄąâ€šw.

### System dynamicznych teĂ„Ä…Ă˘â‚¬Ĺˇ
TĂ„Ä…Ă˘â‚¬Ĺˇo sali dobierane jest na podstawie obsadzonych rĂ„â€šÄąâ€šl **i pĂ„Ä…Ă˘â‚¬Ĺˇci** pracownikĂ„â€šÄąâ€šw.

**Format nazwy pliku:**
```
boardroom_bg_[rola1]_[pĂ„Ä…Ă˘â‚¬ĹˇeÄ‚â€žĂ˘â‚¬Ë‡1]_[rola2]_[pĂ„Ä…Ă˘â‚¬ĹˇeÄ‚â€žĂ˘â‚¬Ë‡2].png
```

PrzykĂ„Ä…Ă˘â‚¬Ĺˇady:
```
brak pracownikĂ„â€šÄąâ€šw          Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ boardroom_bg.png
Dyrektor + Kobieta HR     Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ boardroom_bg_hr_F.png
Dyrektor + MÄ‚â€žĂ˘â€žËĂ„Ä…Ă„Ëťczyzna HR   Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ boardroom_bg_hr_M.png
Dyrektor + HR(K) + Tech(M)Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ boardroom_bg_hr_F_tech_M.png
```

### KolejnoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ rĂ„â€šÄąâ€šl w nazwie (zawsze ta sama)
1. `hr`
2. `tech`
3. `finance`
4. `legal`
5. `logistics`

**Format pĂ„Ä…Ă˘â‚¬Ĺˇci:** `M` = mÄ‚â€žĂ˘â€žËĂ„Ä…Ă„Ëťczyzna, `F` = kobieta

### Sloty przy stole (kÄ‚â€žĂ˘â‚¬Â¦ty od gĂ„â€šÄąâ€šry)
| Slot | KÄ‚â€žĂ˘â‚¬Â¦t | Rola |
|---|---|---|
| #0 | 348Ä‚â€šĂ‚Â° | Dyrektor (zawsze) |
| #1 | 9Ä‚â€šĂ‚Â° | HR |
| #2 | 40Ä‚â€šĂ‚Â° | Technical |
| #3 | 58Ä‚â€šĂ‚Â° | Finance |
| #4 | 92Ä‚â€šĂ‚Â° | Legal |
| #5 | 140Ä‚â€šĂ‚Â° | Logistics |

### Priorytety generowania obrazĂ„â€šÄąâ€šw
| Priorytet | Plik | Opis |
|---|---|---|
| Konieczny | `boardroom_bg.png` | Sam dyrektor (start gry) |
| Konieczny | `boardroom_bg_hr_M/F.png` | Dyrektor + HR |
| WaĂ„Ä…Ă„Ëťny | `boardroom_bg_hr_*_tech_*.png` | + Technical |
| WaĂ„Ä…Ă„Ëťny | `boardroom_bg_hr_*_tech_*_finance_*.png` | + Finance |
| Opcjonalny | + `legal`, + `logistics` | PeĂ„Ä…Ă˘â‚¬Ĺˇny zarzÄ‚â€žĂ˘â‚¬Â¦d |

### Fallback
JeĂ„Ä…Ă˘â‚¬Ĺźli plik graficzny nie istnieje Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ system Ă„Ä…Ă˘â‚¬Ĺˇaduje `boardroom_bg.png` (domyĂ„Ä…Ă˘â‚¬Ĺźlny).

### Specyfikacja graficzna
- RozdzielczoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡: **1920Ă„â€šĂ˘â‚¬â€ť1080px** lub wyĂ„Ä…Ă„Ëťsza
- Format: **PNG**
- Ta sama sala i oĂ„Ä…Ă˘â‚¬Ĺźwietlenie na wszystkich obrazach Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ zmieniajÄ‚â€žĂ˘â‚¬Â¦ siÄ‚â€žĂ˘â€žË tylko osoby na krzesĂ„Ä…Ă˘â‚¬Ĺˇach
- Puste krzesĂ„Ä…Ă˘â‚¬Ĺˇa powinny byÄ‚â€žĂ˘â‚¬Ë‡ widoczne

### Testowanie
1. Wgraj obrazy przez panel admina Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ Edytor szablonu Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ zakĂ„Ä…Ă˘â‚¬Ĺˇadka **Boardroom** Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ sekcja **TĂ„Ä…Ă˘â‚¬Ĺˇa sceny zarzÄ‚â€žĂ˘â‚¬Â¦du**
2. OtwĂ„â€šÄąâ€šrz `boardroom.php`
3. Konsola przeglÄ‚â€žĂ˘â‚¬Â¦darki (F12) pokazuje: `Background loaded: boardroom_bg_hr_F.png`

> PeĂ„Ä…Ă˘â‚¬Ĺˇna dokumentacja dla grafika: `BOARDROOM_BACKGROUNDS_GUIDE.md`
> `IMAGES_GUIDE.md` jest przestarzaĂ„Ä…Ă˘â‚¬Ĺˇy Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ zastÄ‚â€žĂ˘â‚¬Â¦piony przez powyĂ„Ä…Ă„Ëťszy przewodnik.

### Panel admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ manager teĂ„Ä…Ă˘â‚¬Ĺˇ sceny
ZakĂ„Ä…Ă˘â‚¬Ĺˇadka **Edytor szablonu Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ Boardroom Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ TĂ„Ä…Ă˘â‚¬Ĺˇa sceny zarzÄ‚â€žĂ˘â‚¬Â¦du** (`admin/template_editor.php`):

- **Siatka istniejÄ‚â€žĂ˘â‚¬Â¦cych plikĂ„â€šÄąâ€šw** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ miniaturki wgranych teĂ„Ä…Ă˘â‚¬Ĺˇ z moĂ„Ä…Ă„ËťliwoĂ„Ä…Ă˘â‚¬ĹźciÄ‚â€žĂ˘â‚¬Â¦ usuniÄ‚â€žĂ˘â€žËcia (przycisk Ä‚ËÄąâ€şĂ˘â‚¬Ë, handler `delete_boardroom_bg`)
- **Upload nowego tĂ„Ä…Ă˘â‚¬Ĺˇa** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ checkboxy rĂ„â€šÄąâ€šl + select pĂ„Ä…Ă˘â‚¬Ĺˇci (M/F/Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ); nazwa pliku generowana automatycznie na podstawie wybranych rĂ„â€šÄąâ€šl w kolejnoĂ„Ä…Ă˘â‚¬Ĺźci sceny
- Plik zapisywany bezpoĂ„Ä…Ă˘â‚¬Ĺźrednio do `assets/images/boardroom_bg_{combo}.png`
- System JS (`boardroom-dynamic.js`) wybiera najbardziej pasujÄ‚â€žĂ˘â‚¬Â¦cy istniejÄ‚â€žĂ˘â‚¬Â¦cy plik Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ fallback do `boardroom_bg.png`

#### Mechanizm uploadu Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ chunked AJAX (az.pl)

Hosting az.pl ma dwa ograniczenia blokujÄ‚â€žĂ˘â‚¬Â¦ce standardowy upload:
1. **Brak katalogu tymczasowego PHP** (`UPLOAD_ERR_NO_TMP_DIR`) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `$_FILES` nie dziaĂ„Ä…Ă˘â‚¬Ĺˇa
2. **WAF (ModSecurity) blokuje body Ä‚ËĂ˘â‚¬Â°Ă„â€ž 16 KB** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `$_POST` i `php://input` sÄ‚â€žĂ˘â‚¬Â¦ puste dla Ă„Ä…Ă„ËťÄ‚â€žĂ˘â‚¬Â¦daĂ„Ä…Ă˘â‚¬Ĺľ z body > 16 KB

RozwiÄ‚â€žĂ˘â‚¬Â¦zanie: upload jako surowe bajty binarne w chunked AJAX:

- JS czyta plik przez `file.arrayBuffer()` (brak konwersji base64)
- Dane dzielone na chunki po **8 KB** (`application/octet-stream`)
- Metadane (`bg_name`, `bg_file_mime`, `chunk_index`, `total_chunks`, `csrf_token`, `upload_id`) wysyĂ„Ä…Ă˘â‚¬Ĺˇane jako **parametry GET** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ omijajÄ‚â€žĂ˘â‚¬Â¦ WAF caĂ„Ä…Ă˘â‚¬Ĺˇkowicie
- PHP wykrywa Ă„Ä…Ă„ËťÄ‚â€žĂ˘â‚¬Â¦danie przez `$_GET['ajax_upload'] === '1'` + nagĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šwek `X-Requested-With: XMLHttpRequest`
- Chunki zapisywane tymczasowo do `assets/images/boardroom/` jako `.ub_{uploadId}_{bgName}_{idx}`
- Po ostatnim chunku Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ Ă„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â‚¬Â¦czenie pliku i zapis do `assets/images/boardroom_bg_{name}.png`
- `upload_id` generowany po stronie JS (`Math.random().toString(36)`) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ unikalny identyfikator sesji uploadu

**Pliki kontrolera/widoku:**
- `admin/template_editor.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ blok AJAX na samym poczÄ‚â€žĂ˘â‚¬Â¦tku pliku (przed `$_codexGuardStart`); handlery `save_boardroom_bg`, `delete_boardroom_bg`; `$brBgMatrix` z macierzÄ‚â€žĂ˘â‚¬Â¦ kombinacji
- `templates/views/admin/template_editor/main.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ sekcja UI z podglÄ‚â€žĂ˘â‚¬Â¦dem generowanej nazwy pliku (live JS), chunked upload bez `<form enctype="multipart/form-data">`
- `assets/css/admin.css` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ klasy `.br-bg-*`

---

## 21. BezpieczeĂ„Ä…Ă˘â‚¬Ĺľstwo

- Sesje PHP + `Auth::requireLogin()` na chronionych stronach
- Bcrypt dla haseĂ„Ä…Ă˘â‚¬Ĺˇ
- CSRF token na kaĂ„Ä…Ă„Ëťdym Ă„Ä…Ă„ËťÄ‚â€žĂ˘â‚¬Â¦daniu POST/AJAX
- PDO prepared statements wszÄ‚â€žĂ˘â€žËdzie
- `src/`, `config/`, `cron/` zablokowane w `.htaccess`

---

## 22. System czatu graczy

System wiadomoĂ„Ä…Ă˘â‚¬Ĺźci zintegrowany z dashboardem gry. Polling PHP, bez zewnÄ‚â€žĂ˘â€žËtrznych zaleĂ„Ä…Ă„ËťnoĂ„Ä…Ă˘â‚¬Ĺźci (LiteSpeed shared hosting).

---

### Baza danych

| Tabela | Opis |
|--------|------|
| `chat_messages` | WiadomoĂ„Ä…Ă˘â‚¬Ĺźci: `id`, `sender_id` (NULL=admin), `receiver_id` (NULL=global), `channel` (global/private), `username`, `message`, `is_deleted`, `is_admin`, `is_pinned`, `pinned_at`, `created_at` |
| `chat_bans` | Bany i muty: `player_id`, `reason`, `banned_by`, `banned_at`, `expires_at` (NULL=permanent) |
| `chat_reports` | ZgĂ„Ä…Ă˘â‚¬Ĺˇoszenia: `message_id`, `reporter_id`, `reason` (spam/obraza/inne), `status` (open/resolved), `created_at` |

**Migracja:** `chat_migration.sql` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ ALTER `chat_messages` + CREATE `chat_reports` + CREATE `chat_bans`

> Kolumny `is_admin TINYINT(1)`, `is_pinned TINYINT(1)`, `pinned_at DATETIME NULL` dodane do `chat_messages` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ umoĂ„Ä…Ă„ËťliwiajÄ‚â€žĂ˘â‚¬Â¦ pin/unpin z panelu admina.

---

### Architektura (v2 Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ 06.04.2026)

| Plik | Rola |
|------|------|
| `src/ChatApi.php` | GĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šwny endpoint: GET pobiera wiad., POST wysyĂ„Ä…Ă˘â‚¬Ĺˇa/zgĂ„Ä…Ă˘â‚¬Ĺˇasza; obsĂ„Ä…Ă˘â‚¬Ĺˇuguje global i DM |
| `src/DmApi.php` | Alias dla DM Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ deleguje do `ChatApi.php` |
| `dm.php` | Strona `/dm?with=player_id` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ widok wiadomoĂ„Ä…Ă˘â‚¬Ĺźci prywatnych; config PHPÄ‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘JS inline (`DM_API`, `MY_ID`, `WITH_ID`), logika w `assets/js/dm.js` |
| `assets/js/chat.js` | Polling co 4s, renderowanie, emoji, przyciski Ä‚ËÄąË‡Ă˘â‚¬Â ZgĂ„Ä…Ă˘â‚¬ĹˇoĂ„Ä…Ă˘â‚¬Ĺź i Ä‚ËÄąâ€şĂ˘â‚¬Â° DM |
| `assets/js/dm.js` | Logika DM: polling, renderowanie wiadomoĂ„Ä…Ă˘â‚¬Ĺźci, emoji, wysyĂ„Ä…Ă˘â‚¬Ĺˇka (15 KwiecieĂ„Ä…Ă˘â‚¬Ĺľ 2026 Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ wyodrÄ‚â€žĂ˘â€žËbnione z `dm.php`) |
| `assets/css/chat.css` | Style czatu Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ wiadomoĂ„Ä…Ă˘â‚¬Ĺźci, hover opcje, przyciski |
| `assets/css/style.css` | Style layoutu DM (`.dm-layout`, `.dm-sidebar` itp. Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ przeniesione z inline `<style>` w `dm.php`) |
| `templates/components/chat.php` | Komponent HTML osadzony w `public/index.php` |
| `templates/views/admin/chat/main.php` | Widok panelu admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ CSS Grid zamiast `<table>` (15 KwiecieĂ„Ä…Ă˘â‚¬Ĺľ 2026) |

---

### Funkcje globalne (czat na dashboardzie)

- Polling co 4 sekundy (`GET /src/ChatApi.php?since=id`)
- Historia: ostatnie 50 wiadomoĂ„Ä…Ă˘â‚¬Ĺźci przy pierwszym Ă„Ä…Ă˘â‚¬Ĺˇadowaniu
- Nadawca: `company_name` gracza (fallback: `username`)
- Limit 300 znakĂ„â€šÄąâ€šw, walidacja PHP + `maxlength` HTML
- **Rate limit:** 1 wiadomoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ / 2 sekundy
- **Flood protection:** >5 wiadomoĂ„Ä…Ă˘â‚¬Ĺźci w 30s Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ auto-mute 5 minut (insert do `chat_bans`)
- **Emoji:** `parseEmojis()` w `chat.js` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ 20+ skrĂ„â€šÄąâ€štĂ„â€šÄąâ€šw po `escHtml()` (XSS-safe): `:)` `:(` `:D` `<3` `:fire:` `:oil:` `:money:` `:up:` `:down:` itp.
- **Soft delete:** `is_deleted=1` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ wiadomoĂ„Ä…Ă˘â‚¬Ĺźci usuniÄ‚â€žĂ˘â€žËte przez admina niewidoczne dla graczy
- **ZgĂ„Ä…Ă˘â‚¬Ĺˇoszenia:** przycisk Ä‚ËÄąË‡Ă˘â‚¬Â (pojawia siÄ‚â€žĂ˘â€žË po hover) Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ modal z powodem Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ `ChatApi` action=report Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ `chat_reports`
- **Link DM:** przycisk Ä‚ËÄąâ€şĂ˘â‚¬Â° przy wiadomoĂ„Ä…Ă˘â‚¬Ĺźci Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ `/dm?with=sender_id`
- WiadomoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ `[ADMIN]` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `sender_id=NULL`, wysyĂ„Ä…Ă˘â‚¬Ĺˇana z `admin/chat.php`

---

### WiadomoĂ„Ä…Ă˘â‚¬Ĺźci prywatne (DM)

Strona `/dm?with=player_id`:
- **Sidebar** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ lista aktywnych rozmĂ„â€šÄąâ€šw z ostatniÄ‚â€žĂ˘â‚¬Â¦ wiadomoĂ„Ä…Ă˘â‚¬ĹźciÄ‚â€žĂ˘â‚¬Â¦
- **Nowa rozmowa** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ dropdown z listÄ‚â€žĂ˘â‚¬Â¦ graczy (max 50)
- **Okno DM** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ polling co 4s, Enter wysyĂ„Ä…Ă˘â‚¬Ĺˇa, emoji
- Backend: `ChatApi.php` z `channel='private'`, query:
  ```sql
  WHERE (sender_id=A AND receiver_id=B) OR (sender_id=B AND receiver_id=A)
  ```
- `GET ?action=conversations` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ lista rozmĂ„â€šÄąâ€šw z `partner_id`, `partner_name`, `last_message`
- `GET ?action=players` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ lista graczy do nowej rozmowy

**Architektura JS (po refaktorze 15.04.2026):**
- `dm.php` zawiera tylko config inline: `var DM_API`, `var MY_ID`, `var WITH_ID`, `var lastId`, `var interval`
- CaĂ„Ä…Ă˘â‚¬Ĺˇa logika (polling, render, emoji, wysyĂ„Ä…Ă˘â‚¬Ĺˇka) Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ `assets/js/dm.js`
- Style layoutu (`.dm-layout`, `.dm-sidebar`, `.dm-msg-area` itp.) Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ `assets/css/style.css`
- Klasy CSS zamiast inline styles: `.dm-msg-area`, `.dm-input-row`, `.dm-overflow`

---

### Panel admina (`admin/chat.php`)

- Historia czatu z filtrem (gracz, data, paginacja)
- **Soft delete** zamiast hard DELETE (`is_deleted=1`)
- Ban/mute na czas (1h/3h/12h/1d/3d/7d/14d/30d/90d/permanentny)
- Odblokowanie gracza (`chat_bans` DELETE)
- WysyĂ„Ä…Ă˘â‚¬Ĺˇanie komunikatu jako `[ADMIN]`
- Czyszczenie czatu globalnego (soft delete)
- **Usuwanie przeterminowanych wiadomoĂ„Ä…Ă˘â‚¬Ĺźci** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ akcja `delete_expired` kasuje (soft) wiadomoĂ„Ä…Ă˘â‚¬Ĺźci starsze niĂ„Ä…Ă„Ëť 30 minut; klucz i18n `admin.chat.delete_expired`
- **Otwarte zgĂ„Ä…Ă˘â‚¬Ĺˇoszenia** (`chat_reports` WHERE status='open') Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ widok z akcjami:
  - UsuĂ„Ä…Ă˘â‚¬Ĺľ wiadomoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ (soft delete)
  - Oznacz jako rozwiÄ‚â€žĂ˘â‚¬Â¦zane (`status='resolved'`)
- **Przypinanie wiadomoĂ„Ä…Ă˘â‚¬Ĺźci admina** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ przycisk Ă„â€ÄąĹźĂ˘â‚¬Ĺ›ÄąĹˇ PrzypiĂ„Ä…Ă˘â‚¬Ĺľ / Ă„â€ÄąĹźĂ˘â‚¬Ĺ›ÄąĹˇ Odepnij w historii czatu dla wiadomoĂ„Ä…Ă˘â‚¬Ĺźci z `is_admin=1`; akcje `pin_msg` / `unpin_msg`; ustawia `is_pinned=1` + `pinned_at=NOW()`

**Widok (po refaktorze 15.04.2026):**
- `templates/views/admin/chat/main.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ lista wiadomoĂ„Ä…Ă˘â‚¬Ĺźci w CSS Grid (`.chat-msg-list`, `.chat-msg-header`, `.chat-msg-row`); poprzednio `<table>` z 15 tagami
- Lista banĂ„â€šÄąâ€šw Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ CSS Grid z `.data-list` / `.list-row`
- Style w `assets/css/admin.css`: `.chat-msg-list`, `.chat-msg-header`, `.chat-msg-row`, `.chat-msg-actions`

**Aktualizacja (02.05.2026):**
- Kolumny `is_admin`, `is_pinned` dodane do SELECT w `admin/chat.php` (byĂ„Ä…Ă˘â‚¬Ĺˇy pominiÄ‚â€žĂ˘â€žËte)
- Przyciski pin/unpin wyĂ„Ä…Ă˘â‚¬Ĺźwietlane tylko dla `is_admin=1`; badge `admin` i badge Ă„â€ÄąĹźĂ˘â‚¬Ĺ›ÄąĹˇ w kolumnie autora
- Dodano brakujÄ‚â€žĂ˘â‚¬Â¦ce klucze i18n: `admin.chat.delete_expired`, `admin.chat.delete_expired_confirm`, `admin.chat.msg_expired_deleted`, `admin.chat.pin_btn`, `admin.chat.pin_title`, `admin.chat.unpin_btn`, `admin.chat.unpin_title`

---

### Pinned messages Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ architektura (02.05.2026)

PrzypiÄ‚â€žĂ˘â€žËte wiadomoĂ„Ä…Ă˘â‚¬Ĺźci admina wyĂ„Ä…Ă˘â‚¬Ĺźwietlane sÄ‚â€žĂ˘â‚¬Â¦ **wyĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â‚¬Â¦cznie** w pasku nad czatem (`#chatPinnedBar`), **nigdy** w strumieniu wiadomoĂ„Ä…Ă˘â‚¬Ĺźci.

| Endpoint | Kiedy | Co zwraca |
|----------|-------|-----------|
| `GET /src/ChatApi.php` (pierwsze Ă„Ä…Ă˘â‚¬Ĺˇadowanie) | Inicjalizacja czatu | `messages` (bez przypiÄ‚â€žĂ˘â€žËtych) + `pinned` (przypiÄ‚â€žĂ˘â€žËte) + `my_id` |
| `GET /src/ChatApi.php?since=N` | Polling co 4s | Nowe wiadomoĂ„Ä…Ă˘â‚¬Ĺźci z `id > N` i `is_pinned = 0` |
| `GET /src/ChatApi.php?pinned_only=1` | Polling co 15s | Tylko przypiÄ‚â€žĂ˘â€žËte wiadomoĂ„Ä…Ă˘â‚¬Ĺźci admina |

- Zapytania strumieniowe majÄ‚â€žĂ˘â‚¬Â¦ `AND is_pinned = 0` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ przypiÄ‚â€žĂ˘â€žËtych nigdy nie ma w streamie
- `renderPinned()` w `chat.js` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ po wyrenderowaniu paska **usuwa** z DOM ewentualne duplikaty w `#chatMessages` (`box.querySelector('[data-id="N"]')?.remove()`)

### Routing i bezpieczeĂ„Ä…Ă˘â‚¬Ĺľstwo

- `src/ChatApi.php` i `src/DmApi.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ odblokowane w gĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šwnym `.htaccess` (brak `src/.htaccess`)
- Route `/dm` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ `dm.php` w `.htaccess`
- `ob_start()` + `ob_clean()` przed `header('Content-Type: application/json')` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ chroni przed HTML z exception handlera
- XSS: `escHtml()` w JS przed renderowaniem, dopiero potem `parseEmojis()`
- CSRF: sesja PHP + `credentials: same-origin`

---

### Bugfixy (06Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›07.04.2026)

- **`src/AdminLog.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ TypeError** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `GameLog::error()` wywoĂ„Ä…Ă˘â‚¬Ĺˇywane z array zamiast `?Throwable`
- **`admin/chat.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ ENUM truncation** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ username admina jako `target_type`; naprawiono na `'player'`/pominiÄ‚â€žĂ˘â€žËcie
- **`src/ChatApi.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ 403 Forbidden** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `src/.htaccess` blokowaĂ„Ä…Ă˘â‚¬Ĺˇ endpoint; usuniÄ‚â€žĂ˘â€žËty; whitelist w gĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šwnym `.htaccess`
- **`src/ChatApi.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ FK violation** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ FOREIGN KEY do `players` blokowaĂ„Ä…Ă˘â‚¬Ĺˇ wiad. admina (`player_id=NULL`); FK usuniÄ‚â€žĂ˘â€žËty
- **`src/ChatApi.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ Unknown column player_id** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ stary kod po migracji SQL; wgranie nowej wersji
- **`dm.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `CSRF::getToken()` undefined** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ metoda nie istnieje w klasie CSRF; nieuĂ„Ä…Ă„Ëťywana zmienna usuniÄ‚â€žĂ˘â€žËta

---

### Refaktor (15.04.2026)

- **`dm.php`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ logika JS (113L) wyodrÄ‚â€žĂ˘â€žËbniona do `assets/js/dm.js`; style layoutu (`<style>` blok) przeniesione do `assets/css/style.css`; w pliku zostaje tylko config PHPÄ‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘JS (5 linii)
- **`templates/views/admin/chat/main.php`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `<table>` z historiÄ‚â€žĂ˘â‚¬Â¦ wiadomoĂ„Ä…Ă˘â‚¬Ĺźci (TABLEĂ„â€šĂ˘â‚¬â€ť15) zastÄ‚â€žĂ˘â‚¬Â¦piony CSS Grid (`.chat-msg-list`); responsive breakpoint przy 900px

---

### Roadmap

#### Faza 1 Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ ZREALIZOWANA (06.04.2026)
- [x] KanaĂ„Ä…Ă˘â‚¬Ĺˇy global/private (`channel` w `chat_messages`)
- [x] WiadomoĂ„Ä…Ă˘â‚¬Ĺźci prywatne (DM) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `dm.php`, `DmApi.php`
- [x] System zgĂ„Ä…Ă˘â‚¬ĹˇoszeĂ„Ä…Ă˘â‚¬Ĺľ Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `chat_reports`, przycisk Ä‚ËÄąË‡Ă˘â‚¬Â, panel admina
- [x] Soft delete (`is_deleted`)
- [x] Flood protection (auto-mute)
- [x] Emoji (`parseEmojis`)
- [x] Nazwa firmy zamiast loginu
- [x] Otwarte zgĂ„Ä…Ă˘â‚¬Ĺˇoszenia w panelu admina

#### Faza 2 Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ ZaĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â‚¬Â¦czniki (do zrobienia)
- [ ] Upload obrazĂ„â€šÄąâ€šw (jpg/png/webp, max 5 MB)
- [ ] Walidacja MIME (`finfo_file()`), hash SHA256 jako nazwa
- [ ] Zapis do `/uploads/chat/YYYY-MM/`
- [ ] Miniatura w wiadomoĂ„Ä…Ă˘â‚¬Ĺźci, lightbox modal
- [ ] `attachment_url` w `chat_messages`

#### Faza 3 Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ Moderacja rozszerzona (do zrobienia)
- [ ] Filtr sĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šw kluczowych (`chat_blocked_words`)
- [ ] Statystyki zgĂ„Ä…Ă˘â‚¬Ĺˇaszanych graczy w adminie
- [ ] SSE (Server-Sent Events) zamiast pollingu dla >50 graczy jednoczeĂ„Ä…Ă˘â‚¬Ĺźnie
- [ ] Indeks `(channel, id)` na `chat_messages` przy skalowaniu


---

## 23. DziaĂ„Ä…Ă˘â‚¬Ĺˇ Finansowy

Centralny system finansowy OilCorp. Agreguje przychody i koszty z wszystkich systemĂ„â€šÄąâ€šw gry per tick, zapisuje historiÄ‚â€žĂ˘â€žË i udostÄ‚â€žĂ˘â€žËpnia analizÄ‚â€žĂ˘â€žË.

### Architektura

| Plik | Rola |
|------|------|
| `src/FinanceService.php` | Serwis: zapis tickĂ„â€šÄąâ€šw, agregaty, alerty, admin stats |
| `public/finance.php` | Strona `/finance` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ panel gracza |
| `admin/finance.php` | Panel admina `/admin/finance` |
| `assets/js/finance.js` | Wykres Chart.js (linia: przychĂ„â€šÄąâ€šd/koszty/zysk/straty) |
| `assets/css/finance.css` | Style panelu finansowego |
| `finance_migration.sql` | CREATE TABLE `finance_logs` |

### Baza danych Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `finance_logs`

Zapisywana co tick (5 min) per gracz:

| Kolumna | Opis |
|---------|------|
| `revenue` | bbl Ă„â€šĂ˘â‚¬â€ť cena Ă„â€šĂ˘â‚¬â€ť (1Ä‚ËĂ‚ÂĂ˘â‚¬â„˘loss) |
| `gross_revenue` | bbl Ă„â€šĂ˘â‚¬â€ť cena przed stratami |
| `opex` | OPEX odwiertĂ„â€šÄąâ€šw |
| `salary_cost` | Pensje zarzÄ‚â€žĂ˘â‚¬Â¦d + technicy |
| `transport_cost` | Transport OPEX |
| `incident_cost` | Naprawy, kary |
| `tax` | Podatek regionalny |
| `loss_bbl` / `loss_value` | Straty transportu (bbl + PLN) |
| `net_profit` | revenue Ä‚ËĂ‚ÂĂ˘â‚¬â„˘ wszystkie koszty |
| `cash_after` | Stan kasy po ticku |
| `oil_price` | Cena ropy w tym ticku |
| `bbl_produced` | BaryĂ„Ä…Ă˘â‚¬Ĺˇki netto |
| `wells_active` | Liczba aktywnych odwiertĂ„â€šÄąâ€šw |

### Logika finansowa (tick)

```
gross_revenue = bbl Ă„â€šĂ˘â‚¬â€ť cena_ropy
revenue       = bbl_netto Ă„â€šĂ˘â‚¬â€ť cena  (po odjÄ‚â€žĂ˘â€žËciu lostOil)
loss_value    = lostOil Ă„â€šĂ˘â‚¬â€ť cena
net_profit    = revenue Ä‚ËĂ‚ÂĂ˘â‚¬â„˘ opex Ä‚ËĂ‚ÂĂ˘â‚¬â„˘ salary Ä‚ËĂ‚ÂĂ˘â‚¬â„˘ transport_opex Ä‚ËĂ‚ÂĂ˘â‚¬â„˘ incident_cost Ä‚ËĂ‚ÂĂ˘â‚¬â„˘ tax
```

### Panel gracza (`/finance`)

- **4 karty top bar:** Saldo, Zysk/tick, Zysk/godzina (Ă„â€šĂ˘â‚¬â€ť12), Straty transport %
- **Wykres liniowy 24h/7dni:** przychĂ„â€šÄąâ€šd, koszty, zysk netto, straty
- **Breakdown struktury finansowej:** przychody / koszty (7 kategorii) / straty netto
- **Tabela per odwiert:** produkcja/h, OPEX/h, transport%, podatek%, szacowany zysk/h
- **Alerty automatyczne:** strata netto, loss >15%, brak produkcji, podatki >20%

### Panel admina (`/admin/finance`)

- Globalne statystyki (przychĂ„â€šÄąâ€šd, wynik netto, straty, produkcja, gracze aktywni)
- Tabela per gracz: saldo, przychĂ„â€šÄąâ€šd, wynik netto, straty, avg/tick
- **Config panel:** 3 globalne mnoĂ„Ä…Ă„Ëťniki w `well_config` (tax, cost, loss) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ efekt od nastÄ‚â€žĂ˘â€žËpnego ticku

### Integracja z tick.php

Zmienne agregujÄ‚â€žĂ˘â‚¬Â¦ce per pÄ‚â€žĂ˘â€žËtla gracza:
`$finRevenue`, `$finGross`, `$finOpex`, `$finSalary`, `$finTransport`, `$finIncident`, `$finTax`, `$finLossBbl`, `$finLossValue`, `$finBbl`, `$finWellsActive`

`FinanceService::saveTick()` wywoĂ„Ä…Ă˘â‚¬Ĺˇywana przed `UPDATE players SET cash`.


## Specjalizacje pracownikĂ„â€šÄąâ€šw (perki)

KaĂ„Ä…Ă„Ëťdy pracownik techniczny moĂ„Ä…Ă„Ëťe mieÄ‚â€žĂ˘â‚¬Ë‡ maksymalnie 1 specjalizacjÄ‚â€žĂ˘â€žË (perk), losowanÄ‚â€žĂ˘â‚¬Â¦ przy zatrudnieniu.

> **ZarzÄ‚â€žĂ˘â‚¬Â¦dzanie sĂ„Ä…Ă˘â‚¬Ĺˇownikami** specjalizacji dostÄ‚â€žĂ˘â€žËpne w panelu admina: `admin/hr.php?tab=specializations` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ zob. Ä‚â€šĂ‚Â§18d.

### Operator (`staff_specializations` WHERE role = 'operator')
| Kod | Polska nazwa | Efekt | Rarity |
|-----|-------------|-------|--------|
| `drilling_specialist` | Specjalista Wiercenia | +7.5% produkcji (tylko deep/ultra), Ä‚ËĂ‚ÂĂ˘â‚¬â„˘10% wear | rare |
| `pressure_control` | Kontrola CiĂ„Ä…Ă˘â‚¬Ĺźnienia | Ä‚ËĂ‚ÂĂ˘â‚¬â„˘15% szansy awarii, Ä‚ËĂ‚ÂĂ˘â‚¬â„˘10% boost spirali | uncommon |

### Technik (`staff_specializations` WHERE role = 'technician')
| Kod | Polska nazwa | Efekt | Rarity |
|-----|-------------|-------|--------|
| `electronics_specialist` | Specjalista Elektroniki | Ä‚ËĂ‚ÂĂ˘â‚¬â„˘25% czasu naprawy, Ä‚ËĂ‚ÂĂ˘â‚¬â„˘10% powrotu awarii | uncommon |
| `mechanical_specialist` | Specjalista Mechaniczny | Ä‚ËĂ‚ÂĂ˘â‚¬â„˘20% wear, Ä‚ËĂ‚ÂĂ˘â‚¬â„˘15% awarii sprzÄ‚â€žĂ˘â€žËtu | common |
| `safety_specialist` | Specjalista BHP | Ä‚ËĂ‚ÂĂ˘â‚¬â„˘12.5% katastrof, Ä‚ËĂ‚ÂĂ˘â‚¬â„˘10% boost spirali | rare |

### Specjalizacje kandydatĂ„â€šÄąâ€šw HR (`hr_specializations`)
Losowane przy generowaniu kandydata. Pogrupowane po polu `department` (np. `finance`, `legal`, `hr`, `logistics`).
ZarzÄ‚â€žĂ˘â‚¬Â¦dzanie w `admin/hr.php?tab=specializations` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ dodawanie, edycja nazwy/dziaĂ„Ä…Ă˘â‚¬Ĺˇu/rzadkoĂ„Ä…Ă˘â‚¬Ĺźci, usuwanie.

### Szansa losowania specjalizacji technicznej
- Bazowa: 5% + 1% za kaĂ„Ä…Ă„Ëťdy skill ponad 5 (skill 10 = 10%)
- Wagi: common=60%, uncommon=30%, rare=10%

### Konwencja `code`
- Snake_case, tylko `[a-z0-9_]`, unikalny w tabeli
- PowiÄ‚â€žĂ˘â‚¬Â¦zanie: `technical_staff.specialization = staff_specializations.code`
- Nowe kody tworzone wyĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â‚¬Â¦cznie w panelu admina (Ä‚â€šĂ‚Â§18d)

### Implementacja
- `staff_specializations` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ tabela definicji perkĂ„â€šÄąâ€šw (edytowalna przez admina)
- `hr_specializations` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ tabela definicji specjalizacji kandydatĂ„â€šÄąâ€šw (edytowalna przez admina)
- `technical_staff.specialization` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ przypisany perk pracownika
- `HRService::rollStaffSpecialization()` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ losowanie przy zatrudnieniu
- `cron/tick.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ zastosowanie efektĂ„â€šÄąâ€šw (produkcja, wear, incydenty, spirala)
- `IncidentService::processTick()` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ redukcja szansy incydentĂ„â€šÄąâ€šw
- UI: badge Ä‚ËĂ‚Â­Ă‚Â w karcie pracownika (`technical.php`, `well_staff` modal)

---

## 27. System AktualnoĂ„Ä…Ă˘â‚¬Ĺźci (Admin News)

Panel aktualnoĂ„Ä…Ă˘â‚¬Ĺźci zintegrowany z dashboardem gry. Admin tworzy newsy widoczne dla wszystkich graczy w bocznym panelu.

### Architektura

| Plik | Rola |
|------|------|
| `admin/news.php` | Kontroler admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ CRUD newsĂ„â€šÄąâ€šw (add/edit/delete/pin/unpin), `$viewData`, obsĂ„Ä…Ă˘â‚¬Ĺˇuga POST |
| `templates/views/admin/news/main.php` | Widok panelu admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ layout kart zamiast tabeli |
| `src/AdminNewsApi.php` | Publiczny endpoint AJAX (GET) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ pobiera newsy dla graczy |
| `assets/js/chat.js` | ModuĂ„Ä…Ă˘â‚¬Ĺˇ `NEWS PANEL` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ polling co 60s, renderowanie |
| `assets/css/admin.css` | Style kart newsĂ„â€šÄąâ€šw (`.news-card`, `.news-admin-layout` itp.) |
| `lang/pl.php` | Klucze `admin.news.*` + `admin.nav.news` |

### Baza danych Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ tabela `admin_news`

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | INT AUTO_INCREMENT | PK |
| `title` | VARCHAR(120) | TytuĂ„Ä…Ă˘â‚¬Ĺˇ aktualnoĂ„Ä…Ă˘â‚¬Ĺźci |
| `content` | TEXT | TreĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ |
| `is_pinned` | TINYINT(1) | 1 = przypiÄ‚â€žĂ˘â€žËta (pokazywana na gĂ„â€šÄąâ€šrze) |
| `pinned_at` | DATETIME NULL | Czas przypiÄ‚â€žĂ˘â€žËcia |
| `active` | TINYINT(1) | 1 = widoczna dla graczy |
| `created_by` | VARCHAR(60) | Login admina |
| `created_at` | DATETIME | Data dodania |
| `updated_at` | DATETIME | Data edycji |

### Funkcje panelu admina (`admin/news.php`)

| Akcja POST | Opis |
|------------|------|
| `add` | INSERT do `admin_news` (title, content, created_by) |
| `edit` | UPDATE title + content WHERE id |
| `delete` | `SET active = 0` (soft delete) |
| `pin` | `SET is_pinned = 1, pinned_at = NOW()` (maks. 3 przypiÄ‚â€žĂ˘â€žËte Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ walidacja) |
| `unpin` | `SET is_pinned = 0, pinned_at = NULL` |

KaĂ„Ä…Ă„Ëťda akcja zabezpieczona przez `CSRF::validateToken()`.

### API dla graczy (`src/AdminNewsApi.php`)

Endpoint: `GET /src/AdminNewsApi.php`

OdpowiedĂ„Ä…ÄąĹş JSON:
```json
{
  "news": [
    {
      "id": 5,
      "title": "Nowa wersja 1.4",
      "content": "Dodano obsĂ„Ä…Ă˘â‚¬ĹˇugÄ‚â€žĂ˘â€žË warstw gĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â€žËbokich...",
      "is_pinned": 1,
      "created_by": "admin",
      "created_at": "2026-05-02 12:00:00",
      "date_fmt": "02.05.2026"
    }
  ]
}
```

Zapytanie SQL: `WHERE active = 1 ORDER BY is_pinned DESC, created_at DESC LIMIT 20`

### Renderowanie po stronie gracza (`assets/js/chat.js`)

ModuĂ„Ä…Ă˘â‚¬Ĺˇ IIFE `NEWS PANEL`:
- `loadNews()` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `fetch('/src/AdminNewsApi.php')` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ `renderNews(data.news)`
- `renderNews(items)` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ buduje HTML w `#newsList`; przypiÄ‚â€žĂ˘â€žËte newsy otrzymujÄ‚â€žĂ˘â‚¬Â¦ klasÄ‚â€žĂ˘â€žË `.news-item--pinned` i badge Ă„â€ÄąĹźĂ˘â‚¬Ĺ›ÄąĹˇ
- `initNews()` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ inicjuje przy `DOMContentLoaded`; polling co **60 sekund**
- `escHtml()` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ lokalna kopia (moduĂ„Ä…Ă˘â‚¬Ĺˇ jest osobnym IIFE)

### Widok admina (`templates/views/admin/news/main.php`)

Dwukolumnowy layout (`.news-admin-layout`):
- **Lewa kolumna** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ formularz dodaj/edytuj (`.news-form-panel`): pola `title` + `content`, dynamiczny nagĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šwek Ä‚ËÄąâ€şÄąÄ…Ă„ĹąĂ‚Â¸ÄąÄ… Edytuj / Ä‚ËÄąÄľĂ˘â‚¬Ë Dodaj, przycisk Anuluj przy edycji
- **Prawa kolumna** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ lista kart (`.news-card-list`): kaĂ„Ä…Ă„Ëťda karta zawiera ID, badge (Ă„â€ÄąĹźĂ˘â‚¬Ĺ›ÄąĹˇ PrzypiÄ‚â€žĂ˘â€žËta / Ä‚ËÄąâ€şĂ˘â‚¬Ĺ› Aktywna), datÄ‚â€žĂ˘â€žË + godzinÄ‚â€žĂ˘â€žË, tytuĂ„Ä…Ă˘â‚¬Ĺˇ, podglÄ‚â€žĂ˘â‚¬Â¦d treĂ„Ä…Ă˘â‚¬Ĺźci (120 znakĂ„â€šÄąâ€šw), autora, przyciski Ä‚ËÄąâ€şÄąÄ…Ă„ĹąĂ‚Â¸ÄąÄ… Edytuj / Ă„â€ÄąĹźĂ˘â‚¬Ĺ›ÄąĹˇ PrzypiĂ„Ä…Ă˘â‚¬Ĺľ-Odepnij / Ă„â€ÄąĹźĂ˘â‚¬â€ťĂ˘â‚¬Â UsuĂ„Ä…Ă˘â‚¬Ĺľ

### .htaccess Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ whitelist

`src/AdminNewsApi.php` dodane do **dwĂ„â€šÄąâ€šch** miejsc w `.htaccess`:
1. Blok wyjÄ‚â€žĂ˘â‚¬Â¦tkĂ„â€šÄąâ€šw (`RewriteCond %{REQUEST_URI} ^/src/AdminNewsApi\.php$`)
2. Negatywny lookahead reguĂ„Ä…Ă˘â‚¬Ĺˇy blokujÄ‚â€žĂ˘â‚¬Â¦cej (`!^/src/(Ä‚ËĂ˘â€šÂ¬Ă‚Â¦|AdminNewsApi\.php)$`)

Bez obu wpisĂ„â€šÄąâ€šw endpoint zwracaĂ„Ä…Ă˘â‚¬Ĺˇ 403 Forbidden.

### i18n (`lang/pl.php`)

| Klucz | WartoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ |
|-------|---------|
| `admin.news.heading` | AktualnoĂ„Ä…Ă˘â‚¬Ĺźci |
| `admin.news.add_heading` | Dodaj aktualnoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ |
| `admin.news.edit_heading` | Edytuj aktualnoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ |
| `admin.news.title_label` | TytuĂ„Ä…Ă˘â‚¬Ĺˇ |
| `admin.news.content_label` | TreĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ |
| `admin.news.submit_add` | Dodaj |
| `admin.news.submit_edit` | Zapisz zmiany |
| `admin.news.btn_cancel` | Anuluj |
| `admin.news.btn_edit` | Edytuj |
| `admin.news.btn_pin` | PrzypiĂ„Ä…Ă˘â‚¬Ĺľ |
| `admin.news.btn_unpin` | Odepnij |
| `admin.news.btn_delete` | UsuĂ„Ä…Ă˘â‚¬Ĺľ |
| `admin.news.delete_confirm` | Na pewno usunÄ‚â€žĂ˘â‚¬Â¦Ä‚â€žĂ˘â‚¬Ë‡ tÄ‚â€žĂ˘â€žË aktualnoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡? |
| `admin.news.status_pinned` | PrzypiÄ‚â€žĂ˘â€žËta |
| `admin.news.status_active` | Aktywna |
| `admin.news.list_empty` | Brak aktualnoĂ„Ä…Ă˘â‚¬Ĺźci. |
| `admin.nav.news` | AktualnoĂ„Ä…Ă˘â‚¬Ĺźci |

---

- [x] **Rekrutacja zarzadu przeniesiona z HR do dashboardu** (03.05.2026) - `hr.php` stal sie panelem czysto kadrowym; zniknely zakladki `Rekrutacja`, `Kandydaci` i `Specjalizacje`; `dashboard.php` przejal start rekrutacji dyrektorow, liste kandydatow zarzadu i aktywne procesy; `HRApi.php` blokuje stary flow `initiated_by='hr'`; `HiringTrait` / `DataTrait` zawezaja dane do `player_id`.
- [x] **Mobilne poprawki HR i dashboardu** (03.05.2026) - `assets/css/hr.css`: poziomy scroll zakladek, brak ucietego `Headhunter`, poprawione karty pracownikow i akcje na telefonie; `assets/css/dashboard.css`: pelnoszerokie akcje na mobile, lepsze zawijanie formularza rekrutacji i sekcji dyrektorskich.
- [x] **RĂ„â€šÄąâ€šwnolegĂ„Ä…Ă˘â‚¬Ĺˇa rekrutacja HR Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ maks. 2 dziaĂ„Ä…Ă˘â‚¬Ĺˇy jednoczeĂ„Ä…Ă˘â‚¬Ĺźnie** (02.05.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `src/HRApi.php`: walidacja max 2 aktywne rekrutacje + brak duplikatu roli (status `pending`/`ready`); formularz `.new-recruit-card` w zakĂ„Ä…Ă˘â‚¬Ĺˇadce Rekrutacja z dropdownem rĂ„â€šÄąâ€šl (filtruje zajÄ‚â€žĂ˘â€žËte/rekrutowane), dropdownem specjalizacji (filtrowany per dziaĂ„Ä…Ă˘â‚¬Ĺˇ), siatkÄ‚â€žĂ˘â‚¬Â¦ regionĂ„â€šÄąâ€šw i wskaĂ„Ä…ÄąĹşnikiem slotĂ„â€šÄąâ€šw; `_nrUpdateSlotsUI()` aktualizuje badge i chowa formularz po osiÄ‚â€žĂ˘â‚¬Â¦gniÄ‚â€žĂ˘â€žËciu limitu; 8 nowych kluczy `hr.*` w `lang/pl.php`; nowe klasy `.nrc-slots-badge`, `.hr-alert--warn/info` w `hr.css`
- [x] **System AktualnoĂ„Ä…Ă˘â‚¬Ĺźci (Ä‚â€šĂ‚Â§27)** (02.05.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `admin/news.php` + `templates/views/admin/news/main.php` (CRUD z layoutem kart); `src/AdminNewsApi.php` (GET endpoint dla graczy); `assets/js/chat.js` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ moduĂ„Ä…Ă˘â‚¬Ĺˇ NEWS PANEL (polling co 60s, `#newsList`); tabela `admin_news`; link Ă„â€ÄąĹźĂ˘â‚¬Ĺ›Ă‚Â° AktualnoĂ„Ä…Ă˘â‚¬Ĺźci w nawigacji admina; `.htaccess` whitelist dla `AdminNewsApi.php`; i18n `admin.news.*` + `admin.nav.news`
- [x] **Pinowanie wiadomoĂ„Ä…Ă˘â‚¬Ĺźci admina w czacie** (02.05.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `admin/chat.php`: akcje `pin_msg`/`unpin_msg`; przyciski Ă„â€ÄąĹźĂ˘â‚¬Ĺ›ÄąĹˇ w historii czatu (tylko dla `is_admin=1`); `src/ChatApi.php`: endpoint `?pinned_only=1` (polling co 15s z JS); filtry `AND is_pinned=0` w strumieniu wiadomoĂ„Ä…Ă˘â‚¬Ĺźci Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ przypiÄ‚â€žĂ˘â€žËtych nigdy nie ma w scrollu czatu; `chat.js`: `renderPinned()` usuwa duplikaty z DOM; kolumny `is_admin`, `is_pinned`, `pinned_at` w `chat_messages`
- [x] **Bugfix czat Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ brakujÄ‚â€žĂ˘â‚¬Â¦ce klucze i18n** (02.05.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `admin.chat.delete_expired`, `admin.chat.delete_expired_confirm`, `admin.chat.msg_expired_deleted`, `admin.chat.pin_btn`, `admin.chat.pin_title`, `admin.chat.unpin_btn`, `admin.chat.unpin_title` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ surowe klucze wyĂ„Ä…Ă˘â‚¬ĹźwietlaĂ„Ä…Ă˘â‚¬Ĺˇy siÄ‚â€žĂ˘â€žË w UI zamiast polskich etykiet
- [x] **Bugfix `src/HRApi.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `$db` undefined przy walidacji rekrutacji** (02.05.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ fatal error przy kaĂ„Ä…Ă„Ëťdym `action=start_recruitment`; dodano `$db = Database::getInstance()->getConnection()` po inicjalizacji serwisĂ„â€šÄąâ€šw
- [x] **Bugfix `hr.js` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `switchTab()` nie reaktywowaĂ„Ä…Ă˘â‚¬Ĺˇ przycisku zakĂ„Ä…Ă˘â‚¬Ĺˇadki** (02.05.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ po klikniÄ‚â€žĂ˘â€žËciu wszystkie przyciski zakĂ„Ä…Ă˘â‚¬Ĺˇadek byĂ„Ä…Ă˘â‚¬Ĺˇy wizualnie nieaktywne; naprawione przez `querySelector(".hr-tab[onclick=...]")?.classList.add('active')`
- [x] **Bugfix `hr.css` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `.btn-hr-primary:hover` niewidoczny** (02.05.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ hover uĂ„Ä…Ă„ËťywaĂ„Ä…Ă˘â‚¬Ĺˇ `--gold2` = `rgba(200,168,75,0.15)` (prawie przezroczysty); zamieniono na solidne `#d4b455`
- [x] **HR: rozdzielenie Pracownicy vs Zarzad + stabilizacja rekrutacji** (02.05.2026) - `member_type` (`director`/`staff`) w `board_members`; nowa zakladka `Zarzad` w HR; filtrowanie dostepow przez `member_type='director'`; HR rekrutuje ze `spec_code`; czasy rekrutacji skrocone do minut; brak auto-refresh listy rekrutacji; dynamiczne dodawanie karty bez reload; poprawiony countdown; dodane `fire_technical_staff`; dopisane klucze `hr.*` i `hr.spec.*` do `lang/pl.php`.


- [x] **Warstwy geologiczne** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ zaimplementowane (`GeologicalLayerService`, UI w `well_grid.php`)
- [x] **System incydentĂ„â€šÄąâ€šw** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ naprawione szanse (skalibrowane per 5-min tick), floor per-tick
- [x] **MarketTick** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ gravity do celu trendu, trendShock, synchronizacja `$newPrice`
- [x] **Profil `/profile`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ 404 naprawione (reguĂ„Ä…Ă˘â‚¬Ĺˇa w `.htaccess`), licznik aktywnych odwiertĂ„â€šÄąâ€šw naprawiony
- [x] **HR zatrudnianie** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ brak reload po `hireCandidate`, karta usuwana z DOM
- [x] Specjalizacje pracownikĂ„â€šÄąâ€šw (perki) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ drilling_specialist, pressure_control, electronics_specialist, mechanical_specialist, safety_specialist
- [x] **Panel admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ HR** (`admin/hr.php`) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ wielozakĂ„Ä…Ă˘â‚¬Ĺˇadkowy panel: kandydaci, historia, statystyki HR, specjalizacje
- [x] **Panel admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ ZarzÄ‚â€žĂ˘â‚¬Â¦dzanie `staff_specializations`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ dodawanie, edycja (nazwa PL + perki), usuwanie z potwierdzeniem modal; grupowanie po `role`
- [x] **Panel admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ ZarzÄ‚â€žĂ˘â‚¬Â¦dzanie `hr_specializations`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ dodawanie, edycja inline (nazwa/dziaĂ„Ä…Ă˘â‚¬Ĺˇ/rzadkoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡), usuwanie; grupowanie po `department`
- [x] **i18n HR admin** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ ~30 nowych kluczy `admin.hr.*` + `common.delete` w `lang/pl.php`
- [x] **CSS spec-card** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `.spec-card`, `.spec-group`, `.spec-group-title`, `.spec-delete-form`, `.btn-danger`, `.spec-field--full` w `assets/css/admin.css`
- [x] **Modal confirm dla akcji delete** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `confirmAction()` z `modal.js` zamiast natywnego `confirm()`; `admin/hr.php` doĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â‚¬Â¦cza `admin/partials/footer.php` (Ă„Ä…Ă˘â‚¬Ĺˇaduje `modal.js`)
- [x] **Bugfix label a11y** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ usuniÄ‚â€žĂ˘â€žËte puste `<label>&nbsp;</label>`; `spec-field--action` wyrĂ„â€šÄąâ€šwnany przez `padding-top` w CSS
- [x] **Bugfix confirmAction undefined** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ dodano `require_once footer.php` do `admin/hr.php`
- [x] **System transportu per odwiert** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ rurociÄ‚â€žĂ˘â‚¬Â¦g/ciÄ‚â€žĂ˘â€žËĂ„Ä…Ă„ËťarĂ„â€šÄąâ€šwki/tankowiec
- [x] **Panel admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ Transport Config** (`admin/transport.php`) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ mnoĂ„Ä…Ă„Ëťniki per typ, capacity, OPEX
- [x] **Panel admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ Loss Monitoring** (`admin/transport_loss.php`) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ straty global/per gracz/per odwiert
- [x] **Panel admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ Market Debug** (`admin/market_debug.php`) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ supply/demand, historia ceny, ekonomia graczy
- [x] **Panel admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ Pipelines** (`admin/pipelines.php`) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ stan, naprawa, wymuszanie awarii
- [x] **Panel admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ Alerty** (`admin/alerts.php`) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ automatyczne progi krytyczne i ostrzegawcze
- [x] **Panel admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ Quick Balance** (`admin/balance.php`) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ globalne mnoĂ„Ä…Ă„Ëťniki bez deploy kodu
- [x] **Globalne mnoĂ„Ä…Ă„Ëťniki balansu** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ odczyt z `well_config` w `cron/tick.php`, 7 mnoĂ„Ä…Ă„ËťnikĂ„â€šÄąâ€šw Ă„â€šĂ˘â‚¬â€ť 6 miejsc w pÄ‚â€žĂ˘â€žËtli
- [x] **`sql/transport_config.sql`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ migracja tabeli `transport_config` z domyĂ„Ä…Ă˘â‚¬Ĺźlnymi wartoĂ„Ä…Ă˘â‚¬Ĺźciami
- [x] **Bugfix `players.username`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ tabela `players` uĂ„Ä…Ă„Ëťywa kolumny `username`, nie `login`; poprawione w `admin/pipelines.php`, `admin/transport_loss.php`, `admin/market_debug.php` (JOIN `pl.username AS player_login`)
- [x] **Bugfix `storage` FK** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ klucz obcy wskazywaĂ„Ä…Ă˘â‚¬Ĺˇ na `players_old` zamiast `players`; migracja `sql/fix_storage_fk_players.sql`
- [x] **Bugfix `admin/alerts.php` PDO** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ stara wersja uĂ„Ä…Ă„ËťywaĂ„Ä…Ă˘â‚¬Ĺˇa `$db->query(..., [array])` (bĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â‚¬Â¦d TypeError); zastÄ‚â€žĂ˘â‚¬Â¦pione przez `prepare()`+`execute()`
- [x] **Strona pomocy `/help`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `public/help.php` z dynamicznÄ‚â€žĂ˘â‚¬Â¦ treĂ„Ä…Ă˘â‚¬ĹźciÄ‚â€žĂ˘â‚¬Â¦ z DB, `assets/css/help.css`, routing `.htaccess` + `ROUTES`, link w nawigacji gracza
- [x] **Panel admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ Edytor instrukcji** (`admin/help_editor.php`) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ WYSIWYG TinyMCE 6, tabela `game_help_pages`, zarzÄ‚â€žĂ˘â‚¬Â¦dzanie sekcjami (dodaj/edytuj/usuĂ„Ä…Ă˘â‚¬Ĺľ/kolejnoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡/widocznoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡); style w `assets/css/help_editor.css`, JS w `assets/js/help_editor.js`
- [x] **Panel admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ Edytor szablonu** (`admin/template_editor.php`) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ edycja nawigacji gracza (tabela `nav_items`), nazwy serwisu, tagline, tekstu stopki, pliku JS; tabela `site_config`; `templates/header.php` i `templates/footer.php` pobierajÄ‚â€žĂ˘â‚¬Â¦ dane z DB z fallbackiem
- [x] **TĂ„Ä…Ă˘â‚¬Ĺˇumaczenia admina** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ wszystkie angielskie fragmenty (`Force Tick`, `Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ Logi`, `supply`, `pipeline loss`, `wear_level`, `ROI`) przetĂ„Ä…Ă˘â‚¬Ĺˇumaczone na polski w `balance.php`, `alerts.php`, `force_tick.php`, `index.php`
- [x] **Edytowalne progi alertĂ„â€šÄąâ€šw** (`admin/alerts.php`) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ progi zapisywane w `well_config` zamiast hardcode; formularz z 9 progami (straty rurociÄ‚â€žĂ˘â‚¬Â¦gĂ„â€šÄąâ€šw, cena ropy, ROI, stan techniczny, zuĂ„Ä…Ă„Ëťycie, magazyn); style `input-inline` + `alert-banner` w `admin.css`
- [x] **Bugfix TinyMCE** (`assets/js/help_editor.js`) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `setup` callback z `editor.on('init')` nasĂ„Ä…Ă˘â‚¬Ĺˇuchuje `submit` na `#editForm` i wywoĂ„Ä…Ă˘â‚¬Ĺˇuje `tinymce.triggerSave()` przed wysĂ„Ä…Ă˘â‚¬Ĺˇaniem; bez tego TinyMCE nie synchronizowaĂ„Ä…Ă˘â‚¬Ĺˇ treĂ„Ä…Ă˘â‚¬Ĺźci z `textarea` i pole `content` byĂ„Ä…Ă˘â‚¬Ĺˇo puste
- [x] **Edytowalne linki stopki** (`admin/template_editor.php` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ zakĂ„Ä…Ă˘â‚¬Ĺˇadka Ä‚ËĂ˘â€šÂ¬ÄąÄľLinki stopki") Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ tabela `nav_items` rozszerzona o kolumnÄ‚â€žĂ˘â€žË `location ENUM('header','footer','actions')`; `templates/footer.php` renderuje dynamiczne linki z bazy; style `.footer-nav` / `.footer-link` w `assets/css/style.css`
- [x] **Edytowalne przyciski AKCJE** (`admin/template_editor.php` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ zakĂ„Ä…Ă˘â‚¬Ĺˇadka Ä‚ËĂ˘â€šÂ¬ÄąÄľPrzyciski AKCJE") Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ przyciski na dashboardzie gracza (Rynek ropy, Kup odwiert, Ulepsz, ZarzÄ‚â€žĂ˘â‚¬Â¦d/HR, Bank) pobierane z `nav_items WHERE location='actions'`; edycja etykiety, ikony, URL key, klasy CSS, kolejnoĂ„Ä…Ă˘â‚¬Ĺźci, widocznoĂ„Ä…Ă˘â‚¬Ĺźci; fallback hardkodowany jeĂ„Ä…Ă˘â‚¬Ĺźli baza pusta; `public/index.php` zastÄ‚â€žĂ˘â‚¬Â¦piony dynamicznym zapytaniem
- [x] **Edytor stron statycznych** (`admin/pages_editor.php`) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ WYSIWYG TinyMCE 6, tabela `static_pages` (slug, title, icon, content, active, sort_order); tworzenie/edycja/usuwanie podstron (Regulamin, Polityka, Kontakt itp.); routing `.htaccess` aktualizowany **automatycznie** po kaĂ„Ä…Ă„Ëťdym zapisie/usuniÄ‚â€žĂ˘â€žËciu Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ blok `# BEGIN static_pages Ä‚ËĂ˘â€šÂ¬Ă‚Â¦ # END static_pages`; `public/page.php` wyĂ„Ä…Ă˘â‚¬Ĺźwietla stronÄ‚â€žĂ˘â€žË po slugu z 404 fallbackiem; style w `assets/css/static_page.css`; link w menu admina sekcja Ä‚ËĂ˘â€šÂ¬ÄąÄľTreĂ„Ä…Ă˘â‚¬Ĺźci"
- [x] **Bugfix `templates/header.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ nav_items bez filtra lokalizacji** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ zapytanie pobieraĂ„Ä…Ă˘â‚¬Ĺˇo wszystkie aktywne wpisy z `nav_items` (header + footer + actions), przez co przyciski AKCJE i linki stopki trafiaĂ„Ä…Ă˘â‚¬Ĺˇy do navbara gracza i powodowaĂ„Ä…Ă˘â‚¬Ĺˇy wyĂ„Ä…Ă˘â‚¬Ĺźwietlanie panelu admina na stronie logowania; naprawione przez dodanie `WHERE location='header'`
- [x] **Bugfix TinyMCE Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ biaĂ„Ä…Ă˘â‚¬Ĺˇe litery na stronie publicznej** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `content_style` w `assets/js/help_editor.js` i `assets/js/pages_editor.js` uĂ„Ä…Ă„ËťywaĂ„Ä…Ă˘â‚¬Ĺˇ `color: #e8e8f0` jako domyĂ„Ä…Ă˘â‚¬Ĺźlny kolor body, przez co TinyMCE wstawiaĂ„Ä…Ă˘â‚¬Ĺˇ inline `color: rgb(232,232,240)` do kaĂ„Ä…Ă„Ëťdego akapitu; zmieniono na `color: #c8c8d4` (zbliĂ„Ä…Ă„Ëťony do `--text2` na stronie gracza) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ domyĂ„Ä…Ă˘â‚¬Ĺźlny tekst nie ma inline koloru, celowo wybrane kolory (Ă„Ä…Ă„ËťĂ„â€šÄąâ€šĂ„Ä…Ă˘â‚¬Ĺˇty, czerwony itp.) dziaĂ„Ä…Ă˘â‚¬ĹˇajÄ‚â€žĂ˘â‚¬Â¦ poprawnie
- [x] **DziaĂ„Ä…Ă˘â‚¬Ĺˇ Finansowy** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `FinanceService`, `/finance`, `/admin/finance`, `finance_logs`, wykres Chart.js, breakdown, per-well analiza
- [x] **SprzedaĂ„Ä…Ă„Ëť odwiertĂ„â€šÄąâ€šw** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `WellSellApi.php`, `WellService::calculateSellValue/sellWell`, UI w `well_grid.php` + `well_grid.js`, wycena z breakdownem w modalu, CSRF, cooldown 2h
- [x] **PodziaĂ„Ä…Ă˘â‚¬Ĺˇ `templates/views/technical/main.php` na taby** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ plik 1405 linii rozbity na 10 osobnych plikĂ„â€šÄąâ€šw w `templates/views/technical/tabs/` (`team`, `candidates`, `tasks`, `wells`, `well_staff`, `prod`, `infra`, `safety`, `incidents`, `report`); `main.php` skrĂ„â€šÄąâ€šcony do 81 linii Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ zawiera tylko topbar, nawigacjÄ‚â€žĂ˘â€žË i jeden dynamiczny `include` z walidacjÄ‚â€žĂ˘â‚¬Â¦ Ă„Ä…Ă˘â‚¬ĹźcieĂ„Ä…Ă„Ëťki (`preg_replace('/[^a-z_]/', '', $activeTab)`)
- [x] **Internacjonalizacja `BankNegotiationService.php`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ wszystkie polskie stringi zastÄ‚â€žĂ˘â‚¬Â¦pione wywoĂ„Ä…Ă˘â‚¬Ĺˇaniami `t()`; dodano ~80 kluczy `bank_neg.*` do `lang/pl.php`; objÄ‚â€žĂ˘â€žËte metody: `formatHours`, `formatHoursNom`, `calculateDecisionTime`, `buildBankOpeningMessage`, `buildCfoOpeningMessage`, `buildApprovalMessage`, `buildRejectionMessage`, `triggerRandomEvent`, `requestRecoveryPlan`, `resolveNegotiation`; logi wewnÄ‚â€žĂ˘â€žËtrzne (`GameLog`/`AdminLog`) pozostawione w angielskim
- [x] **PodziaĂ„Ä…Ă˘â‚¬Ĺˇ `BankruptcyService.php` na traity** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ plik 606 linii rozbity na 3 traity w `src/Bankruptcy/`: `StateTrait.php` (getState, ensureRecoveryMode, getRecoveryOptions, tryRecover, loadState, getEvents, countOpenCriticalEvents), `OptionsTrait.php` (applyOption, applySellAsset, applyBankTakeover, applyEmergencyLoan, applyCostCuts, applyRescueInvestor, applyNewStart), `EventsTrait.php` (tickBankruptcyFlow, spawnCriticalEventIfNeeded, applyLiquidationResetIfNeeded, logEvent, addNotification); gĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šwny plik skrĂ„â€šÄąâ€šcony do 31 linii
- [x] **PodziaĂ„Ä…Ă˘â‚¬Ĺˇ `BankNegotiationService.php` na traity** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ plik 1530 linii rozbity na 5 traitĂ„â€šÄąâ€šw w `src/BankNegotiation/`: `ContextTrait.php` (buildContext, trust score, calculateDecisionTime, calculateDeferralFee), `MessagesTrait.php` (buildBankOpeningMessage, buildCfoOpeningMessage, buildApprovalMessage, buildRejectionMessage), `RandomEventsTrait.php` (triggerRandomEvent), `RequestsTrait.php` (requestDeferral, requestRestructure, requestRecoveryPlan), `ProcessorTrait.php` (processPendingNegotiations, applyNegotiation, canNegotiate, checkRecovery*, gettery); gĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šwny `BankNegotiationService.php` skrĂ„â€šÄąâ€šcony do 79 linii Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ tylko staĂ„Ä…Ă˘â‚¬Ĺˇe, konstruktor i `use` traitĂ„â€šÄąâ€šw; `init.php`, `bank.php`, `tick.php` bez zmian
- [x] **System kryzysu finansowego** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `financial_state` (normal/warning/crisis) + `crisis_ticks` w `players`; tick.php detekuje warning/crisis i triggeruje bankructwo po N tickach (modyfikowane przez credit_score); crisis overlay + warning banner w dashboardzie gracza; blokada UI (brak budowy/upgrade w trybie crisis); `/admin/financial-crisis` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ lista firm + config + akcje; CSS w style.css i admin.css
- [x] **Czarny Rynek Ropy (Ä‚â€šĂ‚Â§24)** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ zakĂ„Ä…Ă˘â‚¬Ĺˇadka w `/market`, oferty co 3 ticki, black_score, kary proporcjonalne do kasy, credit recovery, profil gracza, panel admina z konfiguracjÄ‚â€žĂ˘â‚¬Â¦
- [x] **Refaktor separacji logiki od widoku** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `change_password.php`, `loans.php`, `boardroom.php`, `admin/boardroom.php`, `admin/black_market.php` przeniesione do wzorca `$viewData` + `require templates/views/Ä‚ËĂ˘â€šÂ¬Ă‚Â¦/main.php`; wszystkie polskie stringi zastÄ‚â€žĂ˘â‚¬Â¦pione wywoĂ„Ä…Ă˘â‚¬Ĺˇaniami `t()`; nowe pliki widokĂ„â€šÄąâ€šw: `templates/views/admin/change_password/main.php`, `templates/views/admin/loans/main.php`
- [x] **Manager teĂ„Ä…Ă˘â‚¬Ĺˇ sceny Boardroom** (`admin/template_editor.php` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ zakĂ„Ä…Ă˘â‚¬Ĺˇadka Boardroom Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ TĂ„Ä…Ă˘â‚¬Ĺˇa sceny zarzÄ‚â€žĂ˘â‚¬Â¦du) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ upload PNG z automatycznym nazewnictwem `boardroom_bg_{role}_{gender}.png`, podglÄ‚â€žĂ˘â‚¬Â¦d live nazwy pliku przez JS, siatka istniejÄ‚â€žĂ˘â‚¬Â¦cych teĂ„Ä…Ă˘â‚¬Ĺˇ z miniaturkami i usuwaniem; handlery `save_boardroom_bg` / `delete_boardroom_bg`; `$brBgMatrix` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ macierz kombinacji rĂ„â€šÄąâ€šl Ă„â€šĂ˘â‚¬â€ť pĂ„Ä…Ă˘â‚¬ĹˇeÄ‚â€žĂ˘â‚¬Ë‡ ze statusem istnienia pliku
- [x] **Bugfix `PipelineSection` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ inĂ„Ä…Ă„Ëťynier rurociÄ‚â€žĂ˘â‚¬Â¦gĂ„â€šÄąâ€šw nie byĂ„Ä…Ă˘â‚¬Ĺˇ rozpoznawany** (22.04.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ zapytanie SQL uĂ„Ä…Ă„ËťywaĂ„Ä…Ă˘â‚¬Ĺˇo `spec_code` zamiast `specialization`; rurociÄ‚â€žĂ˘â‚¬Â¦gi zawsze degradowaĂ„Ä…Ă˘â‚¬Ĺˇy 2Ă„â€šĂ˘â‚¬â€ť za szybko; naprawione w `src/Tick/PipelineSection.php:102`
- [x] **Bugfix `WellLoopSection` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ odwierty `broken` byĂ„Ä…Ă˘â‚¬Ĺˇy przetwarzane przez tick** (22.04.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `broken` brakowaĂ„Ä…Ă˘â‚¬Ĺˇo na liĂ„Ä…Ă˘â‚¬Ĺźcie pomijanych statusĂ„â€šÄąâ€šw; odwierty z zerowym stanem technicznym naliczaĂ„Ä…Ă˘â‚¬Ĺˇy OPEX; naprawione w `src/Tick/WellLoopSection.php`
- [x] **Bugfix `WellLoopSection` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ zdarzenia transportowe bez efektu** (22.04.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `processTransportEvent(&$actual)` wywoĂ„Ä…Ă˘â‚¬Ĺˇywane po `currentStorage += $actual` i `finBbl += $actual`; kradzieĂ„Ä…Ă„Ëťe i wypadki byĂ„Ä…Ă˘â‚¬Ĺˇy logowane, ale olej i tak trafiaĂ„Ä…Ă˘â‚¬Ĺˇ do magazynu; naprawione Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ transport events wywoĂ„Ä…Ă˘â‚¬Ĺˇywane **przed** zapisem finansĂ„â€šÄąâ€šw
- [x] **Bugfix `WellLoopSection` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `finGross/finLossBbl/finLossValue` zawsze = 0** (22.04.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ pola zadeklarowane ale nigdy przypisywane; `finance_logs.gross_revenue`, `loss_bbl`, `loss_value` byĂ„Ä…Ă˘â‚¬Ĺˇy zerowe dla kaĂ„Ä…Ă„Ëťdego ticka; naprawione Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `$actualBeforeEvent` zapisywany przed eventem transportowym, rĂ„â€šÄąâ€šĂ„Ä…Ă„Ëťnica idzie do `finLoss*`
- [x] **Bugfix `BankSection` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ BankruptcyService dla wszystkich graczy** (22.04.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ warunek `WHERE status != 'bankrupt' OR recovery_mode=1` wybieraĂ„Ä…Ă˘â‚¬Ĺˇ wszystkich aktywnych graczy zamiast tylko bankrutĂ„â€šÄąâ€šw; naprawione na `status = 'bankrupt' OR recovery_mode=1`
- [x] **Bugfix `LoanDecisionService` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ brak `?? null/0` przy dostÄ‚â€žĂ˘â€žËpie do `$breakdown['market']`** (22.04.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ przy braku aktywnego trendu rynkowego linia 54 i 77 rzucaĂ„Ä…Ă˘â‚¬Ĺˇy `Undefined array key`; naprawione przez dodanie `?? null` i `?? 0`
- [x] **Bugfix `CostsTrait` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ tryb `boost` nie dawaĂ„Ä…Ă˘â‚¬Ĺˇ efektu** (22.04.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ match uĂ„Ä…Ă„ËťywaĂ„Ä…Ă˘â‚¬Ĺˇ `'turbo'` zamiast `'boost'`; UI i DB zapisujÄ‚â€žĂ˘â‚¬Â¦ `'boost'`; odwierty w trybie boost produkowaĂ„Ä…Ă˘â‚¬Ĺˇy jak normalne (Ă„â€šĂ˘â‚¬â€ť1.00 zamiast Ă„â€šĂ˘â‚¬â€ť1.40); naprawione w `src/Well/CostsTrait.php:152`
- [x] **Poprawki danych produkcyjnych** (22.04.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ skrypt `sql/fixes_2026_04_22.sql`: well #19 statusÄ‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘`sold`, well #13 statusÄ‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘`broken`, `crisis_ticks_base` 6Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘48, `bm_max_bbl` 200000Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘2000, cena ropy reset do $70
- [x] **Bugfix `public/bank.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ komunikat "SpĂ„Ä…Ă˘â‚¬ĹˇaÄ‚â€žĂ˘â‚¬Ë‡ zobowiÄ‚â€žĂ˘â‚¬Â¦zania" wyĂ„Ä…Ă˘â‚¬Ĺźwietlany nowym graczom** (26.04.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ komunikat `bank.blocked_repay_hint` byĂ„Ä…Ă˘â‚¬Ĺˇ widoczny dla kaĂ„Ä…Ă„Ëťdego zablokowanego gracza niezaleĂ„Ä…Ă„Ëťnie od powodu blokady; naprawione przez flagÄ‚â€žĂ˘â€žË `$blockHasActiveLoan` ustawianÄ‚â€žĂ˘â‚¬Â¦ tylko gdy status kredytu to `active` lub `late`; widok `templates/views/bank/main.php` warunkuje wyĂ„Ä…Ă˘â‚¬Ĺźwietlanie na `<?php if ($blockHasActiveLoan): ?>`
- [x] **Manager teĂ„Ä…Ă˘â‚¬Ĺˇ Boardroom Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ chunked AJAX upload na az.pl** (26Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›27.04.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ standardowy upload `$_FILES` niemoĂ„Ä…Ă„Ëťliwy (brak tmp_dir); WAF ModSecurity blokuje body Ä‚ËĂ˘â‚¬Â°Ă„â€ž 16 KB; rozwiÄ‚â€žĂ˘â‚¬Â¦zanie: surowe bajty binarne (`application/octet-stream`) w 8 KB chunkach, metadane w GET params; CSRF token w URL; tymczasowe chunki w `assets/images/boardroom/`; PHP Ă„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â‚¬Â¦czy i zapisuje finalny PNG
- [x] **Bugfix `admin/template_editor.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ regex ucinaĂ„Ä…Ă˘â‚¬Ĺˇ M/F z nazwy pliku tĂ„Ä…Ă˘â‚¬Ĺˇa** (27.04.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `preg_replace('/[^a-z0-9_]/', '')` wyrzucaĂ„Ä…Ă˘â‚¬Ĺˇ wielkie litery; `boardroom_bg_hr_M.png` zapisywaĂ„Ä…Ă˘â‚¬Ĺˇo siÄ‚â€žĂ˘â€žË jako `boardroom_bg_hr_.png`; naprawione przez zmianÄ‚â€žĂ˘â€žË na `[^a-zA-Z0-9_]` w handlerach `upload_bg_chunk` i `delete_boardroom_bg`
- [x] **Bugfix htaccess Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ whitelist dla ChatApi i DmApi** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `ChatApi.php` i `DmApi.php` nie byĂ„Ä…Ă˘â‚¬Ĺˇy na whitelist reguĂ„Ä…Ă˘â‚¬Ĺˇy blokujÄ‚â€žĂ˘â‚¬Â¦cej `/src/`; Apache zwracaĂ„Ä…Ă˘â‚¬Ĺˇ 403; czat nie wyĂ„Ä…Ă˘â‚¬ĹźwietlaĂ„Ä…Ă˘â‚¬Ĺˇ wiadomoĂ„Ä…Ă˘â‚¬Ĺźci ani nie zapisywaĂ„Ä…Ă˘â‚¬Ĺˇ ich do DB; naprawione przez dodanie peĂ„Ä…Ă˘â‚¬Ĺˇnej whitelist AJAX: `ChatApi.php`, `DmApi.php`, `TechNotifApi.php`, `WellSellApi.php`, `RecruitmentAPI.php`, `HRApi.php`, `WellStaffApi.php`, `BlackMarketApi.php`
- [x] **Integracja stron dziaĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šw ze wspĂ„â€šÄąâ€šlnym layoutem gry** (28.04.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `dashboard.php`, `boardroom.php`, `hr.php` i `technical.php` przestaĂ„Ä…Ă˘â‚¬Ĺˇy renderowaÄ‚â€žĂ˘â‚¬Ë‡ wĂ„Ä…Ă˘â‚¬Ĺˇasne peĂ„Ä…Ă˘â‚¬Ĺˇne dokumenty HTML/topbary; strony uĂ„Ä…Ă„ËťywajÄ‚â€žĂ˘â‚¬Â¦ teraz `templates/header.php`, wspĂ„â€šÄąâ€šlnego `status_grid`, centralnego shellu `templates/components/game_shell.php`, wĂ„Ä…Ă˘â‚¬ĹˇaĂ„Ä…Ă˘â‚¬Ĺźciwego widoku moduĂ„Ä…Ă˘â‚¬Ĺˇu oraz wspĂ„â€šÄąâ€šlnego `action_grid` na dole. Dodano `src/GameShell.php`, ktĂ„â€šÄąâ€šry zbiera metryki gracza i akcje (`nav_items WHERE location='actions'` z fallbackiem). UsuniÄ‚â€žĂ˘â€žËto lokalne topbary z widokĂ„â€šÄąâ€šw moduĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šw i ograniczono CSS, Ă„Ä…Ă„Ëťeby nie nadpisywaĂ„Ä…Ă˘â‚¬Ĺˇ globalnego `body`.
- [x] **Muzyka tĂ„Ä…Ă˘â‚¬Ĺˇa na mapie Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ automatyczna lista utworĂ„â€šÄąâ€šw** (29.04.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `assets/js/map_audio.js` pobiera listÄ‚â€žĂ˘â€žË plikĂ„â€šÄąâ€šw MP3/OGG/WAV/M4A z endpointu `assets/audio/list.php` (PHP skanuje katalog przez `DirectoryIterator`); lista tasowana losowo przy kaĂ„Ä…Ă„Ëťdej sesji; brak koniecznoĂ„Ä…Ă˘â‚¬Ĺźci edycji JS po dodaniu nowego pliku Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ wystarczy wrzuciÄ‚â€žĂ˘â‚¬Ë‡ plik do `/assets/audio/`; fade-in/out 1500 ms, widget gĂ„Ä…Ă˘â‚¬ĹˇoĂ„Ä…Ă˘â‚¬ĹźnoĂ„Ä…Ă˘â‚¬Ĺźci zapamiÄ‚â€žĂ˘â€žËtywany w `localStorage`.
- [x] **Wizualny picker wspĂ„â€šÄąâ€šĂ„Ä…Ă˘â‚¬ĹˇrzÄ‚â€žĂ˘â€žËdnych globusa w panelu admina** (29.04.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `templates/views/admin/map_locations/main.php`: przycisk Ä‚ËĂ˘â€šÂ¬ÄąÄľĂ„â€ÄąĹźÄąĹˇÄąÂ¤ Ustaw na globie" przy polach Lat/Lng modala edycji lokalizacji; peĂ„Ä…Ă˘â‚¬Ĺˇnoekranowy modal z Three.js SphereGeometry (ta sama tekstura co mapa gracza); klik na globie Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ czerwony marker + wyĂ„Ä…Ă˘â‚¬Ĺźwietlenie wspĂ„â€šÄąâ€šĂ„Ä…Ă˘â‚¬ĹˇrzÄ‚â€žĂ˘â€žËdnych; Ä‚ËĂ˘â€šÂ¬ÄąÄľZatwierdĂ„Ä…ÄąĹş" wypeĂ„Ä…Ă˘â‚¬Ĺˇnia pola formularza; `latLngToVec3` / `vec3ToLatLng` matematycznie spĂ„â€šÄąâ€šjne z `world_map.js`; Three.js + OrbitControls doĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â‚¬Â¦czone lokalnie przez CDN (nie sÄ‚â€žĂ˘â‚¬Â¦ w globalnym headerze admina).
- [x] **Obfuskacja JS przed deplojem na az.pl** (29.04.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `build/` folder: `package.json` (zaleĂ„Ä…Ă„ËťnoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ `javascript-obfuscator ^4.1.1`), `obfuscate.js` (przetwarza wszystkie pliki z `assets/js/` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ `dist/js/`; opcje: `stringArrayEncoding: base64`, `controlFlowFlattening`, `splitStrings`, `identifierNamesGenerator: hexadecimal`), `buduj.bat` (podwĂ„â€šÄąâ€šjne klikniÄ‚â€žĂ˘â€žËcie = instalacja + build); Ă„Ä…ÄąĹşrĂ„â€šÄąâ€šdĂ„Ä…Ă˘â‚¬Ĺˇowe JS pozostajÄ‚â€žĂ˘â‚¬Â¦ czytelne lokalnie, na serwer wgrywana jest wyĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â‚¬Â¦cznie zawartoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ `dist/js/`.
- [x] **ResponsywnoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ mobilna gry** (29.04.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ audyt i poprawki we wszystkich plikach CSS gry (pominiÄ‚â€žĂ˘â€žËto panel admina); zmiany:
  - `assets/css/hr.css` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `@media (max-width: 900px)`: `.candidates-grid`, `.employees-grid`, `.recruit-form-grid` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ 1 kolumna; tabele kontraktĂ„â€šÄąâ€šw/specs/historii Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ `overflow-x: auto` + `min-width`; `@media (max-width: 600px)`: `.hr-tabs` mniejszy padding, `.region-info` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ 1 kolumna, `.cand-skills` / `.emp-skills-grid` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ 3 kolumny zamiast 5
  - `assets/css/black_market.css` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ dodano caĂ„Ä…Ă˘â‚¬Ĺˇy blok `@media` (wczeĂ„Ä…Ă˘â‚¬Ĺźniej zero media queries); listy 10/9/7/6-kolumnowe Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ scroll poziomy (`overflow-x: auto`); karty towarĂ„â€šÄąâ€šw Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ 1 kolumna na Ä‚ËĂ˘â‚¬Â°Ă‚Â¤600px
  - `assets/css/style.css` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ rozszerzono `@media (max-width: 900px)`: globus mapy `560px Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ 380px`, filtry `.map-top-filters__group` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ `flex-wrap: wrap`, widget audio mniejszy
  - `assets/css/recruitment.css` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `.panel-stats` 3 kolumny Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ 2 kol. (Ä‚ËĂ˘â‚¬Â°Ă‚Â¤768px) Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ 1 kol. (Ä‚ËĂ˘â‚¬Â°Ă‚Â¤480px)
  - `assets/css/modal.css` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `@media (max-width: 600px)`: przyciski modala `padding: 8px Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ 11px` (wiÄ‚â€žĂ˘â€žËkszy touch target), `flex-direction: column`, peĂ„Ä…Ă˘â‚¬Ĺˇna szerokoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡
- [x] **Poprawki panelu HR** (29.04.2026):
  - Nowa rekrutacja z wyborem regionu bezpoĂ„Ä…Ă˘â‚¬Ĺźrednio z `hr.php` (zakĂ„Ä…Ă˘â‚¬Ĺˇadka Rekrutacja) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ gracz nie musi wchodziÄ‚â€žĂ˘â‚¬Ë‡ do boardroom; wybĂ„â€šÄąâ€šr roli z dropdownu + siatka kart regionĂ„â€šÄąâ€šw; `hr.js`: `nrSelectRegion()` + `startNewRecruitment()`; backend `HRApi: start_recruitment`; `hr.php`: `$rolesForRecruitment` (filtrowanie zajÄ‚â€žĂ˘â€žËtych/rekrutujÄ‚â€žĂ˘â‚¬Â¦cych rĂ„â€šÄąâ€šl); CSS: `.new-recruit-card`, `.nr-region-card`, `.nr-region-grid` w `hr.css`
  - Staz pracy pracownika w boardroom Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `boardroom.php`: dodano `DATEDIFF(CURDATE(), bm.hired_at) AS days_employed` do zapytania SQL; `boardroom-dynamic.js` wyĂ„Ä…Ă˘â‚¬Ĺźwietla realnÄ‚â€žĂ˘â‚¬Â¦ liczbÄ‚â€žĂ˘â€žË dni zamiast `undefined`
  - Timer aktywnej rekrutacji Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ usuniÄ‚â€žĂ˘â€žËto PHP-generowane `~X min` (arc-status); pozostaĂ„Ä…Ă˘â‚¬Ĺˇ tylko JS countdown (arc-timer); `hr.lang: status_pending` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ `'W trakcie'`
- [x] **Panel admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ dane ostatniego logowania gracza** (29.04.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ w gĂ„â€šÄąâ€šrnej siatce statystyk gracza (`admin/player/main.php`) zamieniono Ä‚ËĂ˘â€šÂ¬ÄąÄľOstatni tick" na Ä‚ËĂ˘â€šÂ¬ÄąÄľOstatnie logowanie" (`last_login_at`); dodano teĂ„Ä…Ă„Ëť wiersz w szczegĂ„â€šÄąâ€šĂ„Ä…Ă˘â‚¬Ĺˇach gracza (zakĂ„Ä…Ă˘â‚¬Ĺˇadka Info); nowe klucze i18n: `admin.player.stat_last_login`, `admin.player.info_last_login` w `lang/pl.php`
- [x] **Optymalizator logistyki odwiertĂ„â€šÄąâ€šw** (29.04.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ przycisk Ä‚ËĂ˘â€šÂ¬ÄąÄľOptymalizuj logistykÄ‚â€žĂ˘â€žË" w zakĂ„Ä…Ă˘â‚¬Ĺˇadce Odwierty panelu technicznego; modal z 3 trybami optymalizacji (Balans, Max produkcja, Min koszt) i podglÄ‚â€žĂ˘â‚¬Â¦dem stanu obecnego; backend:
  - `src/LogisticsService.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ algorytm per odwiert: offshoreÄ‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘tankowiec, onshoreÄ‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘rurociag/ciezarowki; scoring `transported Ä‚ËĂ‚ÂĂ˘â‚¬â„˘ costĂ„â€šĂ˘â‚¬â€ť0.001` (tryb balans), `transported` (max_prod), `-cost` (min_cost); batch UPDATE w transakcji; cooldown 5 min (sesja); zwraca statystyki przed/po (straty, koszt, efektywnoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡)
  - `src/LogisticsApi.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ AJAX endpoint; akcje: `optimize`, `summary`, `cooldown`; dodano do whitelist w `htaccess`
  - `assets/js/logistics.js` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `openLogisticsModal()`, `loadLogisticsSummary()`, `runLogisticsOptimize()`, `startCooldownTimer()`; renderuje porĂ„â€šÄąâ€šwnanie przed/po i listÄ‚â€žĂ˘â€žË zmian per odwiert
  - CSS Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `.logistics-optimizer-bar`, `.logistics-modal-*`, `.logistics-modes`, `.logistics-compare`, `.logistics-changes-list` w `assets/css/style.css`; responsive Ä‚ËĂ˘â‚¬Â°Ă‚Â¤640px
  - W szczegĂ„â€šÄąâ€šĂ„Ä…Ă˘â‚¬Ĺˇach kaĂ„Ä…Ă„Ëťdego odwiertu (zakĂ„Ä…Ă˘â‚¬Ĺˇadka Odwierty) dodano wiersz Ä‚ËĂ˘â€šÂ¬ÄąÄľTransport" z aktualnym typem i % przepustowoĂ„Ä…Ă˘â‚¬Ĺźci
  - Nowe klucze i18n: `technical.logistics_*` (~30 kluczy), `technical.transport_*`, `technical.stat_transport` w `lang/pl.php`
- [x] **Bugfix `WellStaffService` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ nieprawidĂ„Ä…Ă˘â‚¬Ĺˇowe `spec_code` technikĂ„â€šÄąâ€šw** (01.05.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `assign()` i `getAvailableStaff()` uĂ„Ä…Ă„ËťywaĂ„Ä…Ă˘â‚¬Ĺˇy `['well_technician', 'maintenance_engineer']` dla roli technika; `well_technician` nie istnieje w DB; technikĂ„â€šÄąâ€šw nie moĂ„Ä…Ă„Ëťna byĂ„Ä…Ă˘â‚¬Ĺˇo przypisaÄ‚â€žĂ˘â‚¬Ë‡; naprawione na `['maintenance_engineer', 'pipeline_engineer', 'safety_engineer', 'safety_officer']`
- [x] **Bugfix `WellSellTrait` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ pracownicy "zajÄ‚â€žĂ˘â€žËci" po sprzedaĂ„Ä…Ă„Ëťy odwiertu** (01.05.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `sellWell()` ustawiaĂ„Ä…Ă˘â‚¬Ĺˇo status odwiertu na `sold` i zerowaĂ„Ä…Ă˘â‚¬Ĺˇ `operator_id`/`technician_id` w `wells`, ale nie aktualizowaĂ„Ä…Ă˘â‚¬Ĺˇo `well_staff_assignments`; rekordy z `unassigned_at IS NULL` pozostawaĂ„Ä…Ă˘â‚¬Ĺˇy Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ pracownicy wyglÄ‚â€žĂ˘â‚¬Â¦dali na zajÄ‚â€žĂ˘â€žËtych i nie moĂ„Ä…Ă„Ëťna ich byĂ„Ä…Ă˘â‚¬Ĺˇo przypisaÄ‚â€žĂ˘â‚¬Ë‡ do nowego odwiertu; naprawione przez dodanie `UPDATE well_staff_assignments SET unassigned_at=NOW() WHERE well_id=? AND player_id=? AND unassigned_at IS NULL` w transakcji sprzedaĂ„Ä…Ă„Ëťy
- [x] **Bugfix `DisastersTrait` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ pracownicy "zajÄ‚â€žĂ˘â€žËci" po blowout** (01.05.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ analogiczny problem jak w `sellWell`; `triggerBlowout()` nie czyĂ„Ä…Ă˘â‚¬ĹźciĂ„Ä…Ă˘â‚¬Ĺˇo `well_staff_assignments`; naprawione w `src/Well/DisastersTrait.php`
- [x] **SQL jednorazowy `fix_stale_well_staff_2026_05_01.sql`** Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ naprawia historyczne stale rekordy `well_staff_assignments` dla odwiertĂ„â€šÄąâ€šw `sold`/`blowout` istniejÄ‚â€žĂ˘â‚¬Â¦cych przed poprawkÄ‚â€žĂ˘â‚¬Â¦; plik: `sql/fix_stale_well_staff_2026_05_01.sql`
- [x] **Bugfix `WellGridData` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ klucze i18n nieistniejÄ‚â€žĂ˘â‚¬Â¦ce** (01.05.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `WellGridData::prepare()` wywoĂ„Ä…Ă˘â‚¬ĹˇywaĂ„Ä…Ă˘â‚¬Ĺˇo `t('wg.status_active')`, `t('wg.spec_safety_officer')` itp., ktĂ„â€šÄąâ€šre nie istniaĂ„Ä…Ă˘â‚¬Ĺˇy w `lang/pl.php`; dashboard wyĂ„Ä…Ă˘â‚¬ĹźwietlaĂ„Ä…Ă˘â‚¬Ĺˇ surowe klucze zamiast polskich nazw; naprawione przez zamianÄ‚â€žĂ˘â€žË na istniejÄ‚â€žĂ˘â‚¬Â¦ce klucze: statusy Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ `technical.ws_*` / `well.status.*` / `map_js.well_status_broken`, specjalizacje Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ `hr.spec.*`; dodano jedyny brakujÄ‚â€žĂ˘â‚¬Â¦cy klucz `wg.no_location` => `'Bez regionu'`
- [x] **Bugfix `boardroom.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ brak tabeli `boardroom_config` i kolumny `sort_order`** (02.05.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `board_roles` nie miaĂ„Ä…Ă˘â‚¬Ĺˇa kolumny `sort_order`; tabela `boardroom_config` nie istniaĂ„Ä…Ă˘â‚¬Ĺˇa; strona boardroom crashowaĂ„Ä…Ă˘â‚¬Ĺˇa z PDOException; SQL fix: `sql/fix_boardroom_2026_05_02.sql`
- [x] **Bugfix `boardroom-dynamic.js` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ rekrutacja zarzÄ‚â€žĂ˘â‚¬Â¦du zwracaĂ„Ä…Ă˘â‚¬Ĺˇa "Brak specjalizacji"** (02.05.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `submitRecruitment()` wysyĂ„Ä…Ă˘â‚¬ĹˇaĂ„Ä…Ă˘â‚¬Ĺˇo `initiated_by: 'hr'` gdy HR byĂ„Ä…Ă˘â‚¬Ĺˇ zatrudniony, ale nie wysyĂ„Ä…Ă˘â‚¬ĹˇaĂ„Ä…Ă˘â‚¬Ĺˇo `spec_code`; `HRApi.php` rzucaĂ„Ä…Ă˘â‚¬Ĺˇ wyjÄ‚â€žĂ˘â‚¬Â¦tek dla `initiator=hr` bez `spec_code`; naprawione przez zmianÄ‚â€žĂ˘â€žË na `initiated_by: 'director'` (role zarzÄ‚â€žĂ˘â‚¬Â¦du nie wymagajÄ‚â€žĂ˘â‚¬Â¦ specjalizacji)
- [x] **Rozbudowa paneli pracownikĂ„â€šÄąâ€šw w boardroom** (02.05.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ dodano `showLogisticsPanel()` z linkiem do `/logistics` i `showLegalPanel()` dla odpowiednich rĂ„â€šÄąâ€šl; wczeĂ„Ä…Ă˘â‚¬Ĺźniej oba dziaĂ„Ä…Ă˘â‚¬Ĺˇy uĂ„Ä…Ă„ËťywaĂ„Ä…Ă˘â‚¬Ĺˇy generycznego `showEmployeeModal` bez linku do dziaĂ„Ä…Ă˘â‚¬Ĺˇu; nowe klucze i18n: `br_js.logistics_panel_*`, `br_js.legal_panel_*` w `lang/pl.php` i `$brLang` w `boardroom.php`
- [x] **Bugfix `logistics.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ "Transport dziaĂ„Ä…Ă˘â‚¬Ĺˇa optymalnie" przy braku aktywnych odwiertĂ„â€šÄąâ€šw** (02.05.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ gdy brak odwiertĂ„â€šÄąâ€šw lub `$totalOutput == 0`, Ă„Ä…Ă„Ëťaden prĂ„â€šÄąâ€šg alertu nie byĂ„Ä…Ă˘â‚¬Ĺˇ przekraczany Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ wyĂ„Ä…Ă˘â‚¬ĹźwietlaĂ„Ä…Ă˘â‚¬Ĺˇo siÄ‚â€žĂ˘â€žË bĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â€žËdnie "Transport dziaĂ„Ä…Ă˘â‚¬Ĺˇa optymalnie."; naprawione przez sprawdzenie `empty($wells) || $totalOutput == 0` przed logikÄ‚â€žĂ˘â‚¬Â¦ alertĂ„â€šÄąâ€šw Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ wtedy pokazywany jest czerwony alert `logistics.no_wells`
- [x] **Bugfix `admin/player_clean.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ kolumna `event_type` nie istnieje w `bank_trust_log`** (02.05.2026) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `fetchTrustLog()` wykonywaĂ„Ä…Ă˘â‚¬Ĺˇo `SELECT id, event_type AS event, ...` ale tabela ma kolumnÄ‚â€žĂ˘â€žË `event`, nie `event_type`; rzucaĂ„Ä…Ă˘â‚¬Ĺˇo `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'event_type' in 'field list'`; naprawione przez usuniÄ‚â€žĂ˘â€žËcie aliasu: `SELECT id, event, delta, note, created_at FROM bank_trust_log`

---

## Otwarte TODO

- [ ] Cleanup starego kodu po migracjach i naprawach i18n:
  `dashboard.php`, `boardroom.php`, `assets/js/boardroom-dynamic.js`, `assets/js/recruitment.js`
  - wyczyszczenie starych komentarzy i pozostalosci po zlym kodowaniu,
  - przeglad twardych fallbackow tekstowych,
  - audyt martwych lub nieuzywanych kluczy w `lang/pl.php`

- [ ] Multiplayer Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ sabotaĂ„Ä…Ă„Ëť, przejÄ‚â€žĂ˘â€žËcia

---

## 24. Czarny Rynek Ropy

Nielegalna sprzedaĂ„Ä…Ă„Ëť ropy po zawyĂ„Ä…Ă„Ëťonych cenach Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ zakĂ„Ä…Ă˘â‚¬Ĺˇadka "Ă„â€ÄąĹźÄąÄ…Ă‚Â´ Czarny Rynek" w `/market`.

### Pliki

- `src/BlackMarketService.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ logika ofert, transakcji, kar, decay, statystyk
- `src/BlackMarketApi.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ endpoint AJAX (whitelist w htaccess)
- `assets/js/black_market.js` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ polling ofert, sprzedaĂ„Ä…Ă„Ëť, historia, toasty
- `assets/css/black_market.css` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ style zakĂ„Ä…Ă˘â‚¬Ĺˇadek, score bar, risk colors
- `admin/black_market.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ logika: POST handlers, zapytania DB, `$viewData`
- `templates/views/admin/black_market/main.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ widok HTML panelu admina
- `sql/black_market_migration.sql` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ migracja

### Tabele DB

- `black_market_offers` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ (player_id, bbl, price_per_bbl, base_risk_pct, expires_at, status)
- `black_market_transactions` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ (player_id, offer_id, bbl, revenue, detected, penalty, black_score_before/after, credit_score_change)
- `players.black_market_score` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ DECIMAL(5,2), 0Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›100

### Mechanika

**Generowanie ofert:**
- Co N tickĂ„â€šÄąâ€šw (domyĂ„Ä…Ă˘â‚¬Ĺźlnie 3, klucz `bm_offer_interval_ticks`) system generuje 1Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›3 losowych ofert per gracz
- IloĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡: 200Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›2000 bbl (skalowane do 80% magazynu)
- Cena: 130Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›200% oficjalnej ceny ropy
- Ryzyko bazowe: 15Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›40%
- Czas waĂ„Ä…Ă„ËťnoĂ„Ä…Ă˘â‚¬Ĺźci: 6Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›18 tickĂ„â€šÄąâ€šw
- Gracz musi mieÄ‚â€žĂ˘â‚¬Ë‡ Ä‚ËĂ˘â‚¬Â°Ă„â€ž 50 bbl w magazynie i nie byÄ‚â€žĂ˘â‚¬Ë‡ w kryzysie

**Transakcja:**
1. Gracz klika Ä‚ËĂ˘â€šÂ¬ÄąÄľSprzedajÄ‚ËĂ˘â€šÂ¬ÄąÄ„ na ofercie Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ modal potwierdzenia
2. Ropa pobierana z magazynu, gotĂ„â€šÄąâ€šwka na konto
3. **Black score** roĂ„Ä…Ă˘â‚¬Ĺźnie: +3 do +8 za transakcjÄ‚â€žĂ˘â€žË
4. Rzut na wykrycie: `szansa = base_risk + (black_score Ă„â€šĂ˘â‚¬â€ť 0.5)%` (max 95%)

**Gdy wykryty:**
- Kara = % aktualnej kasy gracza (nigdy na minus):
  - score < 30 Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ ~5Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›10% kasy
  - score 30Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›60 Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ ~10Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›20% kasy
  - score > 60 Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ ~20Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›35% kasy
- Credit score: Ä‚ËĂ‚ÂĂ˘â‚¬â„˘3 do Ä‚ËĂ‚ÂĂ˘â‚¬â„˘10 pkt
- Wpis do admin_logs

**Black Score:**
- Zakres 0Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›100, domyĂ„Ä…Ă˘â‚¬Ĺźlny decay: Ä‚ËĂ‚ÂĂ˘â‚¬â„˘0.5/tick
- Widoczny w profilu gracza (progress bar, kolorowy)
- Edytowalny przez admina

**Credit Score Recovery:**
- Legalna sprzedaĂ„Ä…Ă„Ëť na oficjalnym rynku Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ +0.1 pkt/transakcjÄ‚â€žĂ˘â€žË (klucz `credit_score_legal_recovery_rate`)

### Konfiguracja (well_config)

| Klucz | DomyĂ„Ä…Ă˘â‚¬Ĺźlnie | Opis |
|-------|-----------|------|
| `bm_offer_interval_ticks` | 3 | Co ile tickĂ„â€šÄąâ€šw nowe oferty |
| `bm_score_decay_per_tick` | 0.5 | Spadek score/tick |
| `bm_min_bbl` / `bm_max_bbl` | 200 / 2000 | Zakres bbl w ofercie |
| `bm_price_mult_min` / `bm_price_mult_max` | 1.3 / 2.0 | MnoĂ„Ä…Ă„Ëťnik ceny |
| `bm_base_risk_min` / `bm_base_risk_max` | 15 / 40 | Ryzyko bazowe (%) |
| `bm_score_gain_min` / `bm_score_gain_max` | 3 / 8 | Przyrost score za tx |
| `bm_penalty_low/mid/high_pct` | 7.5 / 15 / 27.5 | % kary wg score |
| `bm_offer_ttl_ticks_min/max` | 6 / 18 | Czas Ă„Ä…Ă„Ëťycia oferty (ticki) |
| `credit_score_legal_recovery_rate` | 0.1 | Recovery per legalnÄ‚â€žĂ˘â‚¬Â¦ tx |

### UI Gracza

- ZakĂ„Ä…Ă˘â‚¬Ĺˇadka w `/market?tab=black_market`
- Score bar (progress, kolor: zielony < 30, zĂ„Ä…Ă˘â‚¬Ĺˇoty < 60, czerwony Ä‚ËĂ˘â‚¬Â°Ă„â€ž 60)
- Tabela ofert z ryzykiem (kolor: zielony/Ă„Ä…Ă„ËťĂ„â€šÄąâ€šĂ„Ä…Ă˘â‚¬Ĺˇty/czerwony)
- Historia transakcji (status: udana/wykryta, kara, zmiana score)
- Warning banner gdy score > 50
- Sekcja w profilu gracza (score, tx count, przychĂ„â€šÄąâ€šd, kary)

### Panel admina (`admin/black_market.php` + `templates/views/admin/black_market/main.php`)

- Logika oddzielona od widoku (wzorzec `$viewData` + `extract()`, jak `admin/hr.php`)
- Statystyki globalne (tx, przychĂ„â€šÄąâ€šd, kary, wykrycia, unikalni gracze)
- Lista graczy z edytowalnym black_score Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ CSS grid (`bm-list-head/row`), bez `<table>`
- Historia transakcji z filtrowaniem per gracz Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ CSS grid (`bm-tx-head/row`), bez `<table>`
- Konfiguracja 16 kluczy `bm_*` + `credit_score_legal_recovery_rate` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ CSS grid (`config-grid`)
- Zero inline styles Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ wszystkie klasy w `assets/css/black_market.css`

### Integracja z tick.php

- Sekcja Ä‚â€šĂ‚Â§6 w `cron/tick.php`
- Expire przeterminowanych ofert
- Decay black_score wszystkich graczy
- Generowanie ofert co N tickĂ„â€šÄąâ€šw dla aktywnych graczy

### i18n

~80 kluczy `black_market.*` w `lang/pl.php` (zakĂ„Ä…Ă˘â‚¬Ĺˇadki, oferty, historia, profil, admin, komunikaty).

---

## JakoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ kodu Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ PHPStan

PHPStan level 5 na `src/` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ **0 bĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â€žËdĂ„â€šÄąâ€šw** (sesja type-hinting, kwiecieĂ„Ä…Ă˘â‚¬Ĺľ 2026).

Dodano precyzyjne adnotacje PHPDoc generic dla wszystkich tablic iteracyjnych:
- `array<string, mixed>` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ asocjacyjne tablice (wiersze DB, konteksty)
- `list<array<string, mixed>>` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ listy wierszy DB (wyniki fetchAll)
- `array<int, array<string, mixed>>` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ cache indeksowany po int ID
- `list<string>` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ proste listy stringĂ„â€šÄąâ€šw

Pliki objÄ‚â€žĂ˘â€žËte: wszystkie klasy w `src/` (serwisy, traity, helpers) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ Ă„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â‚¬Â¦cznie ~35 plikĂ„â€šÄąâ€šw i ~28 traitĂ„â€šÄąâ€šw.

---

## 25. Separacja logiki od widoku Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ Faza 1

Realizacja wzorca MVC-lite opisanego w `BRIEF_VIEW_SEPARATION.md`. KaĂ„Ä…Ă„Ëťdy plik PHP dzielony na:
- **Kontroler** (`public/X.php` / `admin/X.php`) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ tylko PHP: query, walidacja, obliczenia, `$viewData`
- **Widok** (`templates/views/X/main.php`) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ tylko HTML z `<?= $zmienna ?>`

### Zrealizowane w sesji 10 KwiecieĂ„Ä…Ă˘â‚¬Ĺľ 2026

#### Infrastruktura

| Plik | Opis |
|------|------|
| `src/i18n.php` | Funkcja `t('modul.klucz', [':param' => val])` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ tĂ„Ä…Ă˘â‚¬Ĺˇumaczenia inline przy separacji |
| `src/Cache.php` | Prosty cache plikowy z TTL; metody: `get`, `set`, `delete`, `flush`; pliki w `cache/` |
| `src/BankHelpers.php` | Funkcje pomocnicze banku (`loanStatusBadge`, `negStatusBadge`, `negTypeLabel`, `negEventIcon`) wyodrÄ‚â€žĂ˘â€žËbnione z `public/bank.php` |
| `lang/pl.php` | Plik tĂ„Ä…Ă˘â‚¬ĹˇumaczeĂ„Ä…Ă˘â‚¬Ĺľ PL Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ klucze `bank.*`, `index.*`, `common.*` (format `modul.klucz`) |
| `cache/.htaccess` | `Deny from all` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ zabezpieczenie katalogu cache przed dostÄ‚â€žĂ˘â€žËpem HTTP |
| `src/init.php` | Dodano `require_once` dla `Cache.php` i `i18n.php` |
| `tests/test_separation.php` | Test weryfikujÄ‚â€žĂ˘â‚¬Â¦cy separacjÄ‚â€žĂ˘â€žË (brak DB/logiki w widokach) |
| `tests/test_cache.php` | Test funkcjonalnoĂ„Ä…Ă˘â‚¬Ĺźci `Cache.php` |
| `tests/test_html_standards.php` | Test standardĂ„â€šÄąâ€šw HTML (brak tabel layoutowych, brak statycznych inline styles) |

#### Rozdzielone pliki

| Kontroler | Widok | Uwagi |
|-----------|-------|-------|
| `public/bank.php` | `templates/views/bank/main.php` | 1073L Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ kontroler 476L + widok 305L; i18n inline |
| `public/index.php` | `templates/views/index/main.php` | 350L Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ kontroler 210L + widok; i18n inline |

#### Poprawki standardĂ„â€šÄąâ€šw CSS Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `templates/components/well_grid.php`

- UsuniÄ‚â€žĂ˘â€žËto statyczny `style="color:var(--red);margin-top:6px"` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ klasa CSS `.wg-diag-note--danger`
- Dodano `.wg-diag-note--danger` i `.wg-hidden` do `assets/css/style.css`
- PozostaĂ„Ä…Ă˘â‚¬Ĺˇe `style=` w `well_grid.php` sÄ‚â€žĂ˘â‚¬Â¦ dozwolone (dynamiczne PHP: `color:<?= $color ?>`, `width:<?= $cond ?>%`, `display:none` dla JS accordion)

### Bugfixy

| Problem | Przyczyna | Fix |
|---------|-----------|-----|
| `lang/pl.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `strict_types declaration must be the very first statement` | `Set-Content` PowerShell zapisywaĂ„Ä…Ă˘â‚¬Ĺˇ plik z UTF-8 BOM przed `<?php` | Zapis przez `[System.IO.File]::WriteAllText` z `UTF8Encoding($false)` (bez BOM) |
| `cron/tick.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `Unknown column 'last_crisis_tick_at'` | Kolumna uĂ„Ä…Ă„Ëťywana w SELECT ale nie dodana migracjÄ‚â€žĂ˘â‚¬Â¦ | `ALTER TABLE players ADD COLUMN IF NOT EXISTS last_crisis_tick_at DATETIME NULL` w bootstrap `ensureBankruptcyRecoverySchema()` w `src/init.php`; SELECT zabezpieczony przez `COALESCE` |
| `templates/views/index/main.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ `Failed opening required status_bar.php` | Komponent nazywa siÄ‚â€žĂ˘â€žË `status_grid.php`, nie `status_bar.php` | Poprawiono Ă„Ä…Ă˘â‚¬ĹźcieĂ„Ä…Ă„ËťkÄ‚â€žĂ˘â€žË require w widoku |

### i18n Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ nowe klucze (`lang/pl.php`)

```
bank.*          Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ ~80 kluczy: tytuĂ„Ä…Ă˘â‚¬Ĺˇy, sekcje, kredyty, negocjacje, blokady, wnioski
index.*         Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ ~40 kluczy: dashboard, komornik, magazyn, akcje, alerty
common.pln / common.apr / common.days / common.months / common.dash
```

### Wzorzec kontrolera (standard)

```php
require_once __DIR__ . '/../src/init.php';
Auth::requireLogin();
// ... logika, serwisy, POST handling ...
$viewData = compact('zmienna1', 'zmienna2', ...);
$pageTitle = t('modul.title');
require_once __DIR__ . '/../templates/header.php';
require __DIR__ . '/../templates/views/modul/main.php';
require_once __DIR__ . '/../templates/footer.php';
```

### Wzorzec widoku (standard)

```php
<?php extract($viewData, EXTR_SKIP); ?>
<!-- tylko HTML + <?= $zmienna ?> + t('klucz') -->
```

### ReguĂ„Ä…Ă˘â‚¬Ĺˇy kodowania (obowiÄ‚â€žĂ˘â‚¬Â¦zkowe)

- **ZERO** `<table>` dla layoutu Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ tylko dla naprawdÄ‚â€žĂ˘â€žË tabelarycznych danych
- **ZERO** `style=""` statycznych Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ wyjÄ‚â€žĂ˘â‚¬Â¦tek: dynamiczne wartoĂ„Ä…Ă˘â‚¬Ĺźci PHP (`width:<?= $pct ?>%`)
- **ZERO** zapytaĂ„Ä…Ă˘â‚¬Ĺľ DB i logiki biznesowej w widokach
- CSS gracza Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ `assets/css/style.css`; CSS admina Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ `assets/css/admin.css`

### Priorytety Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ pozostaĂ„Ä…Ă˘â‚¬Ĺˇe pliki do separacji

| Priorytet | Plik | Status |
|-----------|------|--------|
| Ä‚ËÄąâ€şĂ˘â‚¬Â¦ | `public/bank.php` | Zrealizowane |
| Ä‚ËÄąâ€şĂ˘â‚¬Â¦ | `public/index.php` | Zrealizowane |
| Ă„â€ÄąĹźĂ˘â‚¬ĹĄĂ‚Â´ | `admin/chat.php` (485L) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ TABLEĂ„â€šĂ˘â‚¬â€ť30, inlineĂ„â€šĂ˘â‚¬â€ť42 | Do zrobienia |
| Ă„â€ÄąĹźĂ˘â‚¬ĹĄĂ‚Â´ | `admin/map_locations.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ inlineĂ„â€šĂ˘â‚¬â€ť37 | Do zrobienia |
| Ă„â€ÄąĹźĂ˘â‚¬ĹĄĂ‚Â´ | `admin/player_clean.php` (487L) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ inlineĂ„â€šĂ˘â‚¬â€ť27 | Do zrobienia |
| Ă„â€ÄąĹźÄąĹźĂ‹â€ˇ | `templates/components/well_grid.php` | CSS czÄ‚â€žĂ˘â€žËĂ„Ä…Ă˘â‚¬Ĺźciowo naprawiony |
| Ă„â€ÄąĹźÄąĹźĂ‹â€ˇ | `templates/components/my_offers_table.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ 15Ă„â€šĂ˘â‚¬â€ť `<table>` | Do zrobienia |
| Ă„â€ÄąĹźÄąĹźĂ‹â€ˇ | `templates/components/offers_table.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ 11Ă„â€šĂ˘â‚¬â€ť `<table>`, 10Ă„â€šĂ˘â‚¬â€ť inline | Do zrobienia |

---

### Faza 2 Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ integracja stron dziaĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šw ze wspĂ„â€šÄąâ€šlnym shellem gry (28.04.2026)

Cel: strony kodowane pĂ„â€šÄąâ€šĂ„Ä…ÄąĹşniej (`dashboard`, `boardroom`, `hr`, `technical`) majÄ‚â€žĂ˘â‚¬Â¦ wyglÄ‚â€žĂ˘â‚¬Â¦daÄ‚â€žĂ˘â‚¬Ë‡ jak czÄ‚â€žĂ˘â€žËĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ gĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šwnej gry, a nie osobne aplikacje. Zachowany jest globalny header/nav, pasek statusu gracza i dolny blok AKCJE; wĂ„Ä…Ă˘â‚¬ĹˇaĂ„Ä…Ă˘â‚¬Ĺźciwa zawartoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ dziaĂ„Ä…Ă˘â‚¬Ĺˇu renderuje siÄ‚â€žĂ˘â€žË pomiÄ‚â€žĂ˘â€žËdzy tymi sekcjami.

#### Nowa infrastruktura

| Plik | Rola |
|------|------|
| `src/GameShell.php` | Centralny helper dla widokĂ„â€šÄąâ€šw gracza: buduje `statusItems` (gotĂ„â€šÄąâ€šwka, magazyn, cena ropy, status) i `actions` z `nav_items location='actions'` lub fallbacku |
| `templates/components/game_shell.php` | WspĂ„â€šÄąâ€šlny wrapper: `status_grid` Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ nagĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šwek moduĂ„Ä…Ă˘â‚¬Ĺˇu Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ widok moduĂ„Ä…Ă˘â‚¬Ĺˇu Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ `action_grid` |
| `templates/header.php` | ObsĂ„Ä…Ă˘â‚¬Ĺˇuga opcjonalnego `$extraHead` dla stron wymagajÄ‚â€žĂ˘â‚¬Â¦cych dodatkowych meta tagĂ„â€šÄąâ€šw, np. CSRF w `technical.php` |

#### Zintegrowane strony

| Strona | Zmiana |
|--------|--------|
| `dashboard.php` | PrzejĂ„Ä…Ă˘â‚¬Ĺźcie na `templates/header.php` / `templates/footer.php`; widok `templates/views/dashboard/main.php` nie renderuje juĂ„Ä…Ă„Ëť wĂ„Ä…Ă˘â‚¬Ĺˇasnego topbara ani peĂ„Ä…Ă˘â‚¬Ĺˇnego dokumentu HTML |
| `boardroom.php` | Sala zarzÄ‚â€žĂ˘â‚¬Â¦du dziaĂ„Ä…Ă˘â‚¬Ĺˇa wewnÄ‚â€žĂ˘â‚¬Â¦trz shellu gry; widok sceny zachowany, ale bez wĂ„Ä…Ă˘â‚¬Ĺˇasnego `<html>`, `<body>`, headera i footera |
| `hr.php` | Panel HR dziaĂ„Ä…Ă˘â‚¬Ĺˇa pod globalnym headerem i statusem gry; usuniÄ‚â€žĂ˘â€žËto lokalny topbar z `templates/views/hr/main.php` |
| `technical.php` | Panel techniczny dziaĂ„Ä…Ă˘â‚¬Ĺˇa pod globalnym headerem; lokalny topbar usuniÄ‚â€žĂ˘â€žËty, CSRF meta przekazywane przez `$extraHead` |

#### CSS

- `assets/css/style.css` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ dodano klasy `.game-shell`, `.game-shell-heading`, `.game-shell-module`
- `assets/css/dashboard.css` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ globalne reset/body ograniczone do `.db-container`, Ă„Ä…Ă„Ëťeby nie psuÄ‚â€žĂ˘â‚¬Ë‡ layoutu gry
- `assets/css/hr.css` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ koĂ„Ä…Ă˘â‚¬Ĺľcowy override `body, body.hr-body` zawÄ‚â€žĂ˘â€žËĂ„Ä…Ă„Ëťony do `body.hr-body`
- `assets/css/boardroom-scene.css` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ dodano `.br-shell-summary` dla krĂ„â€šÄąâ€štkiego statusu sali zarzÄ‚â€žĂ˘â‚¬Â¦du wewnÄ‚â€žĂ˘â‚¬Â¦trz shellu

#### Weryfikacja

`php -l` bez bĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â€žËdĂ„â€šÄąâ€šw dla:
- `dashboard.php`, `boardroom.php`, `hr.php`, `technical.php`
- `src/GameShell.php`
- `templates/header.php`
- `templates/components/game_shell.php`
- widokĂ„â€šÄąâ€šw `templates/views/{dashboard,boardroom,hr,technical}/main.php`

`git diff --check` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ brak bĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â€žËdĂ„â€šÄąâ€šw whitespace; tylko standardowe ostrzeĂ„Ä…Ă„Ëťenia Git o LF/CRLF na Windows.

---

## 26. Panel admina Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ odwierty (admin/wells.php) Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ i18n + zakĂ„Ä…Ă˘â‚¬Ĺˇadki konfiguracji

Sesja 21Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬Ĺ›22 KwiecieĂ„Ä…Ă˘â‚¬Ĺľ 2026. Rozbudowa `admin/wells.php` o polskie tĂ„Ä…Ă˘â‚¬Ĺˇumaczenia, czytelne etykiety parametrĂ„â€šÄąâ€šw i logiczny podziaĂ„Ä…Ă˘â‚¬Ĺˇ konfiguracji na zakĂ„Ä…Ă˘â‚¬Ĺˇadki.

### ZakĂ„Ä…Ă˘â‚¬Ĺˇadki panelu (`admin/wells.php`)

| ID zakĂ„Ä…Ă˘â‚¬Ĺˇadki | Etykieta | ZawartoĂ„Ä…Ă˘â‚¬ĹźÄ‚â€žĂ˘â‚¬Ë‡ |
|-------------|----------|-----------|
| `config` | Ä‚ËÄąË‡Ă˘â€žË Parametry | GĂ„Ä…Ă˘â‚¬ĹˇĂ„â€šÄąâ€šwne parametry gry: drilling, opex, production, maintenance, repair, upgrade, market, incident, crisis, balance |
| `sell` | Ă„â€ÄąĹźĂ˘â‚¬â„˘Ă‚Â° Wycena i sprzedaĂ„Ä…Ă„Ëť | Parametry wyceny odwiertu (`sell`) + ustawienia systemowe (`system`) z separatorem |
| `wells` | Ă„â€ÄąĹźĂ˘â‚¬ĹźĂ‹Â Odwierty | Lista odwiertĂ„â€šÄąâ€šw z edycjÄ‚â€žĂ˘â‚¬Â¦ inline (pressure, reservoir) |
| `events` | Ă„â€ÄąĹźĂ˘â‚¬Ĺ›Ă˘â‚¬Ä… Zdarzenia | Dziennik zdarzeĂ„Ä…Ă˘â‚¬Ĺľ odwiertĂ„â€šÄąâ€šw (ostatnie 50) |
| `help` | Ä‚ËÄąÄ„Ă˘â‚¬Ĺ› Pomoc | Instrukcja systemu odwiertĂ„â€šÄąâ€šw (10 sekcji) |

> Parametry czarnego rynku (`bm_*`) sÄ‚â€žĂ˘â‚¬Â¦ zarzÄ‚â€žĂ˘â‚¬Â¦dzane wyĂ„Ä…Ă˘â‚¬ĹˇÄ‚â€žĂ˘â‚¬Â¦cznie w `admin/black_market.php` Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ nie duplikowane w panelu wells.

### PodziaĂ„Ä…Ă˘â‚¬Ĺˇ kategorii `well_config` na grupy

```php
$catMain   = ['drilling','opex','production','maintenance','repair','upgrade','market','incident','crisis','balance'];
$catSell   = ['sell'];        // Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ zakĂ„Ä…Ă˘â‚¬Ĺˇadka "Wycena i sprzedaĂ„Ä…Ă„Ëť"
$catSystem = ['system'];      // Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ zakĂ„Ä…Ă˘â‚¬Ĺˇadka "Wycena i sprzedaĂ„Ä…Ă„Ëť" (separator na dole)
// kategoria 'general' (bm_*) Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ admin/black_market.php (nie wyĂ„Ä…Ă˘â‚¬Ĺźwietlana w wells)
```

Nieznane przyszĂ„Ä…Ă˘â‚¬Ĺˇe kategorie fallbackujÄ‚â€žĂ˘â‚¬Â¦ do `$groupMain`.

### Czytelne etykiety parametrĂ„â€šÄąâ€šw (`admin.wells.key.*`)

Widok uĂ„Ä…Ă„Ëťywa `t('admin.wells.key.' . $r['key'])` z fallbackiem do `$r['label']` z DB:

```php
$lKey = 'admin.wells.key.' . $r['key'];
echo t($lKey) !== $lKey ? t($lKey) : htmlspecialchars($r['label']);
```

Dodano ~50 kluczy `admin.wells.key.*` w `lang/pl.php`, m.in.:

| Klucz DB | Polska etykieta |
|----------|----------------|
| `sell_base_days_mult` | Wycena bazowa Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ dni zysku Ă„â€šĂ˘â‚¬â€ť ten mnoĂ„Ä…Ă„Ëťnik |
| `sell_eq_black_market` | MnoĂ„Ä…Ă„Ëťnik wyceny: sprzÄ‚â€žĂ˘â€žËt z czarnego rynku |
| `sell_risk_divisor` | Dzielnik ryzyka w wycenie (niĂ„Ä…Ă„Ëťszy = wiÄ‚â€žĂ˘â€žËksza kara za ryzyko) |
| `sell_wear_divisor` | Dzielnik zuĂ„Ä…Ă„Ëťycia w wycenie (niĂ„Ä…Ă„Ëťszy = wiÄ‚â€žĂ˘â€žËksza kara za zuĂ„Ä…Ă„Ëťycie) |
| `last_system_tick_at` | Czas ostatniego ticka systemu |
| `incident_retention_days` | Czas przechowywania historii incydentĂ„â€šÄąâ€šw (dni) |
| `bm_offer_interval_ticks` | Czarny rynek: co ile tickĂ„â€šÄąâ€šw pojawia siÄ‚â€žĂ˘â€žË nowa oferta |
| `bm_penalty_high_pct` | Czarny rynek: kara za wysoki poziom ryzyka (%) |
| `bm_score_decay_per_tick` | Czarny rynek: utrata punktĂ„â€šÄąâ€šw reputacji co tick |
| `credit_score_legal_recovery_rate` | Credit score: tempo odbudowy po wyjĂ„Ä…Ă˘â‚¬Ĺźciu z kryzysu (pkt/tick) |

### TĂ„Ä…Ă˘â‚¬Ĺˇumaczenia kategorii (`admin.wells.cat.*`)

| Klucz | Polska nazwa |
|-------|-------------|
| `admin.wells.cat.drilling` | Ă„â€ÄąĹźĂ˘â‚¬ĹĄĂ‚Â© Budowa i zakup odwiertĂ„â€šÄąâ€šw |
| `admin.wells.cat.opex` | Ă„â€ÄąĹźĂ˘â‚¬â„˘Ă‚Â¸ Koszty operacyjne (OPEX) |
| `admin.wells.cat.production` | Ă„â€ÄąĹźĂ˘â‚¬ĹźĂ‹Â Produkcja |
| `admin.wells.cat.maintenance` | Ă„â€ÄąĹźĂ˘â‚¬ĹĄĂ‚Â§ Konserwacja |
| `admin.wells.cat.repair` | Ă„â€ÄąĹźĂ˘â‚¬ĹĄĂ‚Â¨ Naprawa i wymiana |
| `admin.wells.cat.upgrade` | Ä‚ËĂ‚Â¬Ă˘â‚¬Â  Modernizacje |
| `admin.wells.cat.market` | Ă„â€ÄąĹźĂ˘â‚¬Ĺ›ÄąÂ  Rynek |
| `admin.wells.cat.sell` | Ă„â€ÄąĹźĂ˘â‚¬â„˘Ă‚Â° Wycena sprzedaĂ„Ä…Ă„Ëťy odwiertu |
| `admin.wells.cat.crisis` | Ă„â€ÄąĹźÄąË‡Ă‚Â¨ Kryzys finansowy i bankructwo |
| `admin.wells.cat.balance` | Ä‚ËÄąË‡Ă˘â‚¬â€ś Balans rozgrywki |
| `admin.wells.cat.general` | Ă„â€ÄąĹźĂ˘â‚¬ĹĄĂ‚Â© Ustawienia ogĂ„â€šÄąâ€šlne |
| `admin.wells.cat.system` | Ă„â€ÄąĹźĂ˘â‚¬â€śĂ„â€ž Parametry systemowe |
| `admin.wells.cat.incident` | Ä‚ËÄąË‡Ă‚Â Ă„ĹąĂ‚Â¸ÄąÄ… Incydenty i awarie |

### Separatory grup (`admin.wells.group_*`)

W zakĂ„Ä…Ă˘â‚¬Ĺˇadce Ä‚ËĂ˘â€šÂ¬ÄąÄľWycena i sprzedaĂ„Ä…Ă„Ëť" miÄ‚â€žĂ˘â€žËdzy `sell` a `system` wyĂ„Ä…Ă˘â‚¬Ĺźwietla siÄ‚â€žĂ˘â€žË separator:

```html
<div class="config-group-separator config-group-separator--system">
    <span><?= t('admin.wells.group_system') ?></span>
</div>
```

Klasy CSS: `.config-group-separator` (linia + label), `.config-group-separator--system` (szary wariant).

### ZakĂ„Ä…Ă˘â‚¬Ĺˇadka Pomoc Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ 10 sekcji (`admin.wells.help.*`)

| Sekcja | Temat |
|--------|-------|
| s1 | Jak dziaĂ„Ä…Ă˘â‚¬Ĺˇa odwiert? (produkcja, OPEX, wear, wyczerpanie) |
| s2 | Statusy odwiertu (active, broken, paused_cash, paused_storage, staff, blowout) |
| s3 | Produkcja Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ jak jest liczona? (formuĂ„Ä…Ă˘â‚¬Ĺˇa, sprzÄ‚â€žĂ˘â€žËt, ciĂ„Ä…Ă˘â‚¬Ĺźnienie, warstwa, operator) |
| s4 | Efektywne ciĂ„Ä…Ă˘â‚¬Ĺźnienie i wyczerpanie zĂ„Ä…Ă˘â‚¬ĹˇoĂ„Ä…Ă„Ëťa (formuĂ„Ä…Ă˘â‚¬Ĺˇa, zachowanie przy wyczerpaniu) |
| s5 | ZuĂ„Ä…Ă„Ëťycie i stan techniczny (technik, warstwa, wear, spirala) |
| s6 | Warstwy geologiczne (shallow/mid/deep/ultra Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ produkcja, ryzyko, koszty) |
| s7 | Koszty operacyjne OPEX (aktywny/paused_storage/paused_cash) |
| s8 | Kryzys finansowy (warning Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ crisis Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ bankructwo, grace period, credit score) |
| s9 | SprzedaĂ„Ä…Ă„Ëť odwiertu (wycena, modyfikatory, cooldown, flow gracza) |
| s10 | Parametry konfiguracyjne Ä‚ËĂ˘â€šÂ¬Ă˘â‚¬ĹĄ co regulowaÄ‚â€žĂ˘â‚¬Ë‡? (kluczowe staĂ„Ä…Ă˘â‚¬Ĺˇe systemu) |

Sekcja s11 (Czarny rynek sprzÄ‚â€žĂ˘â€žËtu) przeniesiona do zakĂ„Ä…Ă˘â‚¬Ĺˇadki `admin/black_market.php`.

### Pliki zmodyfikowane

| Plik | Zmiany |
|------|--------|
| `lang/pl.php` | ~50 nowych kluczy `admin.wells.key.*`, `admin.wells.cat.*`, `admin.wells.group_*`, `admin.wells.tab_*`, `admin.wells.help.*` |
| `templates/views/admin/wells/main.php` | PodziaĂ„Ä…Ă˘â‚¬Ĺˇ na 5 zakĂ„Ä…Ă˘â‚¬Ĺˇadek; `wellsRenderSection()` jako nazwana funkcja PHP; grupowanie kategorii; separatory; zakĂ„Ä…Ă˘â‚¬Ĺˇadka Help z 10 sekcjami |
| `assets/js/admin_wells.js` | Tablica zakĂ„Ä…Ă˘â‚¬Ĺˇadek: `['config','sell','wells','events','help']` |
| `assets/css/admin.css` | `.config-group-separator`, `.config-group-separator--system`, `.config-save-bar`, `.bm-info`, `.help-section--dark` |

---

### Aktualizacja (03.05.2026) - technical i panel admina gracza

#### Technical
- Drobne etykiety pomocnicze w panelu technicznym zostaly uporzadkowane i przepiete pod spojniejszy system:
  - `templates/views/technical/tabs/candidates.php`
  - `templates/views/technical/tabs/tasks.php`
  - `templates/views/technical/tabs/team.php`
  - `templates/views/technical/tabs/wells.php`
  - `templates/views/technical/tabs/well_staff.php`
- Ograniczono twarde skróty i stare znaczniki w UI (`OK`, `X`, `V`, `TASK`, `STOP`, `CASH`, `MAG`, `AWR`, `SKAZ`) i przepieto je na klucze `technical.short_*` oraz bezpieczne encje HTML tam, gdzie to mialo sens.
- `well_staff.php` zostal wyczyszczony z najbardziej widocznych resztek starego kodowania w badge'ach statusu i przyciskach akcji.

#### Admin - profil gracza
- `admin/player_clean.php` zostal uporzadkowany pod katem logow i komunikatow:
  - `AdminLog::log(...)` dla akcji na gotowce, statusach, ticku, trust score, `credit_score` i bankructwie korzysta teraz z kluczy `admin.player.log_*`
  - komunikat ustawiania `credit_score` korzysta z `admin.player.msg_credit_score_set`
  - bledy ustawiania `credit_score` korzystaja z `admin.player.err_credit_score_set`
  - mapowanie statusow gracza i odwiertow opiera sie na tlumaczeniach `player.status.*` i `well.status.*`
- `templates/views/admin/player/main.php` dostal czesc porzadkow i18n:
  - naglowek strony korzysta z `admin.player.title`
  - sekcja `Credit Score` jest podpieta pod `admin.player.credit_score_*`
  - dodano brakujace klucze pomocnicze dla paginacji i formularza trust/credit score

#### i18n
- `lang/pl.php` rozszerzono o:
  - `technical.skill_short`
  - `technical.manager_badge`
  - `technical.rec_ok_short`
  - `technical.short_*`
  - `admin.player.msg_cash_set`
  - `admin.player.msg_credit_score_set`
  - `admin.player.err_credit_score_set`
  - `admin.player.log_*`
  - `admin.player.credit_score_*`
  - `admin.player.pagination_wells`
  - `admin.player.trust_current`
  - `admin.player.trust_adjust_label`
  - `admin.player.trust_reason_placeholder`
  - `admin.player.trust_save_delta`

#### Weryfikacja
- `php -l` bez bledow dla:
  - `admin/player_clean.php`
  - `templates/views/admin/player/main.php`
  - `templates/views/technical/tabs/candidates.php`
  - `templates/views/technical/tabs/tasks.php`
  - `templates/views/technical/tabs/team.php`
  - `templates/views/technical/tabs/wells.php`
  - `templates/views/technical/tabs/well_staff.php`
  - `lang/pl.php`

*Ostatnia aktualizacja: 03 Maj 2026 (technical: przepiecie etykiet pomocniczych i cleanup `well_staff.php`; admin: profil gracza, logi `AdminLog`, komunikaty `credit_score`, nowe klucze `admin.player.*` i `technical.short_*`) | poprzednio: 03 Maj 2026 (HR jako panel czysto kadrowy; rekrutacja zarzadu przeniesiona do `dashboard.php`; kandydaci i requesty zawezone do `player_id` w `HiringTrait` / `DataTrait`; mobilne poprawki `hr.css` i `dashboard.css`, scroll zakladek HR, naprawa ucietego `Headhunter`)*
