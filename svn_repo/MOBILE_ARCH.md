# OilEmpire — Aplikacja mobilna: architektura i know-how

> Dokument dla kolejnego agenta AI kontynuującego rozwój projektu.
> Zaktualizowany: 2026-06-27.

---

## 1. Gdzie co jest

```
www/
├── api/v1/              ← REST API dla aplikacji mobilnej
│   ├── _bootstrap.php   ← wspólny bootstrap wszystkich endpointów
│   ├── auth/            ← login (POST), logout (POST)
│   ├── player/          ← GET dane gracza
│   ├── market/          ← GET cena ropy + event rynkowy + oferty
│   └── wells/           ← GET/POST studnie gracza
├── src/                 ← silnik gry PHP (tick, modele, bootstrap)
├── mobile/              ← aplikacja Flutter
│   ├── lib/
│   │   ├── app/         ← AppShell, modules.dart (jedyne miejsce rejestracji)
│   │   ├── config/      ← AppConfig (baseUrl z env)
│   │   ├── i18n/        ← LocaleProvider, AppLocalizations, core strings
│   │   ├── models/      ← Player, Market, Well (czyste modele)
│   │   ├── modules/     ← każdy moduł w osobnym katalogu
│   │   │   ├── app_module.dart   ← kontrakt (abstract class)
│   │   │   ├── module_registry.dart ← scala tłumaczenia
│   │   │   ├── dashboard/        ← Dashboard (moduł #1)
│   │   │   └── game/             ← WebView z grą (moduł #2)
│   │   ├── providers/   ← AuthProvider
│   │   ├── screens/     ← LoginScreen, WellsScreen, WebViewScreen
│   │   ├── services/    ← ApiService
│   │   └── theme/       ← AppColors, AppTheme
│   └── test/            ← testy widgetów i modeli
└── .github/workflows/
    ├── deploy-ftp.yml   ← deploy PHP → produkcja az.pl (FTP)
    └── flutter-build.yml ← build APK → GitHub Releases
```

---

## 2. Architektura — wielki obraz

```
┌─────────────────────────────────────────────┐
│  Serwer az.pl (oilempire.pl)                │
│                                             │
│  cron co ~5 min → Tick.php                 │
│  ┌─────────────────────────────────────┐   │
│  │  market_state, market_trends,        │   │
│  │  wells, players, loans, storage…    │   │
│  └─────────────────────────────────────┘   │
│          ↑                  ↑               │
│     PHP Web (browser)  REST API v1          │
│     dashboard.php      api/v1/market        │
│     hr.php             api/v1/player        │
│                        api/v1/wells         │
└─────────────────────────────────────────────┘
                          │
                   HTTPS + Bearer token
                          │
              ┌─────────────────────┐
              │  Aplikacja Flutter   │
              │  (Android, przyszłość: iOS) │
              │                     │
              │  ApiService.dart    │
              │  AuthProvider       │
              │  DashboardScreen    │
              │  GameModule (WebView)│
              └─────────────────────┘
```

**Zasada: serwer jest jedynym źródłem prawdy.** Tick liczy się po stronie PHP (cron) — telefon TYLKO odczytuje wyniki. Zero logiki gry w Darcie.

---

## 3. Kluczowe gotcha — koniecznie przeczytaj

### 3.1 `vendor/` NIE jest deployowany

FTP deploy (`.github/workflows/deploy-ftp.yml`) celowo wyklucza `vendor/`. Zatem **nigdy** nie pisz:

```php
require_once $_API_ROOT . '/vendor/autoload.php'; // FATAL 500 na produkcji!
```

Każdy plik PHP musi ładować zależności przez explicit `require_once` ścieżką bezwzględną. W `api/v1/_bootstrap.php`:

```php
$_API_ROOT = dirname(__DIR__, 2);
require_once $_API_ROOT . '/src/GameLog.php';
require_once $_API_ROOT . '/src/Database.php';
require_once $_API_ROOT . '/src/ApiAuth.php';
```

> **To był root cause błędu "Invalid JSON response" / HTTP 500 przy logowaniu.** Diagnoza: `api/v1/healthcheck.php` (jeśli jeszcze istnieje na serwerze).

### 3.2 Tabela `loans`, nie `bank_loans`

Aktywne pożyczki gracza są w tabeli `loans`. Nigdzie nie ma tabeli `bank_loans`. Sprawdź zapytania SQL w `api/v1/player/index.php`.

### 3.3 `dirname(__DIR__)` vs `dirname(__DIR__, 2)`

Endpointy leżą w `api/v1/market/index.php`. Stamtąd:
- `dirname(__DIR__)` → `api/v1/` ← poprawne dla `/_bootstrap.php`
- `dirname(__DIR__, 2)` → `api/` ← ZŁE (zwróci błąd "brak pliku")

### 3.4 SharedPreferences mock w testach Flutter

Każdy test korzystający z `LocaleProvider` (który używa SharedPreferences) MUSI mieć:

```dart
setUp(() => SharedPreferences.setMockInitialValues({}));
```

Bez tego `await SharedPreferences.getInstance()` nigdy się nie resolve'uje → test się zawiesza w CI na 5+ minut.

### 3.5 Odliczanie eventu rynkowego — clock drift

Telefon może mieć inny zegar niż serwer. Dlatego:
- Serwer liczy `remaining_seconds` w SQL w chwili requestu (`TIMESTAMPDIFF(SECOND, NOW(), ...)`)
- Telefon zapamiętuje `_fetchedAt = DateTime.now()` i każdą sekundę odejmuje elapsed time
- Model: `mobile/lib/models/market.dart` → `MarketTrend.remainingSeconds([DateTime? now])`

### 3.6 OPcache na produkcji

Po deployu FTP skrypt automatycznie czyści OPcache przez `/public/opcache-reset.php?token=...`. Jeśli zmiany PHP nie są widoczne na produkcji — OPcache mógł się nie wyczyścić.

---

## 4. API v1 — kontrakty danych

### Autentykacja

```
POST /api/v1/auth/login.php
Body: { "username": "...", "password": "..." }
→ { "token": "Bearer_token_string", "player": { ...player data... } }

POST /api/v1/auth/logout.php
Header: Authorization: Bearer <token>
→ { "success": true }
```

Token przechowywany w SharedPreferences (`auth_token`). `AuthProvider` zarządza stanem logowania.

### GET /api/v1/player

```json
{
  "id": 1,
  "username": "gracz",
  "cash": 962529.92,
  "bank_balance": 2842343.70,
  "financial_state": "normal",
  "crisis_ticks": 0,
  "credit_score": 750,
  "offline_mode": false,
  "company_name": "Nazwa Firmy",
  "company_age_days": 42,
  "oil_price": 150.00,
  "storage": {
    "used": 1300,
    "capacity": 1300,
    "fill_percent": 100.0
  },
  "active_wells": 5,
  "active_loans": 2
}
```

### GET /api/v1/market

```json
{
  "price": {
    "current": 150.00,
    "base": 100.00,
    "last_updated_at": "2026-01-01 12:00:00"
  },
  "tick": {
    "last_at": "2026-01-01 12:00:00",
    "next_at_estimated": "2026-01-01 12:05:00",
    "interval_seconds": 300
  },
  "trend": {
    "name": "Zagrożenie militarne",
    "category": "military",
    "price_pct": 70,
    "message": "Zagrożenie militarne zwiększa zapotrzebowanie, ceny ropy +70%!",
    "remaining_seconds": 3540,
    "activated_at": "2026-01-01 11:00:00"
  },
  "my_offers": []
}
```

Gdy brak aktywnego eventu: `"trend": null`.

### GET /api/v1/wells

```json
{
  "wells": [
    {
      "id": 1,
      "name": "Szybik Alpha",
      "depth": 1000,
      "output_bbl_per_tick": 50.0,
      "status": "active",
      "efficiency": 95.0
    }
  ]
}
```

---

## 5. Moduł system Flutter

### Dodanie nowego modułu (np. Rynek, HR, Logistyka)

**Krok 1:** Stwórz plik `mobile/lib/modules/rynek/rynek_module.dart`:

```dart
import 'package:flutter/material.dart';
import '../app_module.dart';
import 'i18n/rynek_pl.dart';
import 'i18n/rynek_en.dart';
import 'rynek_screen.dart';

class RynekModule extends AppModule {
  @override String get id => 'rynek';
  @override String get titleKey => 'nav.rynek';
  @override IconData get navIcon => Icons.show_chart;
  @override int get order => 2;

  @override
  Map<String, Map<String, String>> get translations => {
    'pl': rynekPl,
    'en': rynekEn,
  };

  @override
  Widget buildScreen(BuildContext context) => const RynekScreen();
}
```

**Krok 2:** Zarejestruj w `mobile/lib/app/modules.dart` (jedyne miejsce rejestracji):

```dart
List<AppModule> buildAppModules() => [
  DashboardModule(),
  GameModule(),
  RynekModule(),  // ← dodaj tutaj
];
```

Reszta (nawigacja, tłumaczenia, ikona) podepnie się automatycznie przez `ModuleRegistry`.

### Kontrakt AppModule

```dart
abstract class AppModule {
  String get id;              // 'rynek'
  String get titleKey;        // 'nav.rynek' — klucz i18n w nav
  IconData get navIcon;
  IconData get navIconSelected => navIcon;  // opcjonalnie inny
  int get order;              // kolejność w dolnym nav
  bool get showInNav => true;
  Map<String, Map<String, String>> get translations => const {};
  Widget buildScreen(BuildContext context);
}
```

---

## 6. System tłumaczeń (i18n)

### Wzorzec

Każdy moduł ma własne pliki tłumaczeń:
```
mobile/lib/modules/dashboard/i18n/
    dashboard_pl.dart    ← Polski (fallback)
    dashboard_en.dart    ← Angielski
```

Format kluczy: `moduł.element` (np. `dashboard.cash`, `dashboard.event.active`).

### Użycie w widgetach

```dart
// W buildContext (po zaimportowaniu locale_provider.dart):
Text(context.t('dashboard.cash'))
Text(context.t('dashboard.greeting', {'name': player.companyName}))
```

### Interpolacja parametrów

Zarówno `{param}` jak i `:param` działają:
```dart
const Map<String, String> dashboardPl = {
  'dashboard.greeting': 'Witaj, {name}!',
  'dashboard.storage_filled': '{pct}% pełny',
};
```

### Fallback EN → PL

`ModuleRegistry.buildTranslations()` buduje mapę PL jako bazę, nakłada EN. Brakujące klucze EN cicho fallbackują na PL.

### Globalny przełącznik języka

Pill `PL / EN` w górnym pasku (`AppShell`). Stan przechowywany w SharedPreferences (`locale`). `LocaleProvider.setLocale('en')` → przebudowuje całe drzewo widgetów.

---

## 7. Paleta kolorów (1:1 z grą webową)

`mobile/lib/theme/app_colors.dart` — kolory bezpośrednio z `assets/css/variables.css`:

| Zmienna CSS | Dart | Wartość |
|-------------|------|---------|
| `--gold` | `AppColors.gold` | `0xFFC8A84B` |
| `--gold2` | `AppColors.goldBright` | `0xFFE8CC7A` |
| `--bg` | `AppColors.bg` | `0xFF08080F` |
| `--bg2` | `AppColors.bg2` | `0xFF0F0F18` |
| `--bg3` | `AppColors.bg3` | `0xFF161622` |
| `--green` | `AppColors.green` | `0xFF4EC97A` |
| `--red` | `AppColors.red` | `0xFFE05555` |
| `--orange` | `AppColors.orange` | `0xFFE8953A` |
| `--text` | `AppColors.text` | `0xFFE8E0D0` |
| `--text2` | `AppColors.text2` | `0xFF9A8F7E` |

Gradient tła kart: `LinearGradient([AppColors.bg3, AppColors.bg2])`.

---

## 8. Dashboard — architektura odświeżania

`DashboardScreen` to `StatefulWidget` z `WidgetsBindingObserver`:

```dart
// Timery w initState():
_refreshTimer = Timer.periodic(Duration(seconds: 60), (_) => _refreshAll());
_tickTimer = Timer.periodic(Duration(seconds: 1), (_) {
  if (mounted && (_market?.trend?.isActive ?? false)) setState(() {});
});

// Odświeżanie przy powrocie do aplikacji:
void didChangeAppLifecycleState(AppLifecycleState state) {
  if (state == AppLifecycleState.resumed) _refreshAll();
}
```

- `_refreshTimer`: co 60s pełny reload API (player + market)
- `_tickTimer`: co 1s tylko `setState()` gdy aktywny event (przebudowuje licznik)
- `WidgetsBindingObserver`: odświeżenie po powrocie z tła

**Pełny reload zawiera:**
1. `auth.refreshPlayer()` — GET /api/v1/player
2. `_loadMarketWith(token)` — GET /api/v1/market

---

## 9. Widget eventu rynkowego

`mobile/lib/modules/dashboard/widgets/market_event_card.dart`

Komponent `MarketEventCard(trend: trend)`:
- Baner z gradientem + złota ramka (jak web)
- Dwa pilulki: "AKTYWNE ZDARZENIE RYNKOWE" + "+70% CENY ROPY"
- Licznik: `_Countdown(seconds: trend.remainingSeconds())`
- Format: `>= 1h → HH:MM`, `< 1h → MM:SS`

Baner wyświetla się tylko gdy `trend != null && trend.isActive`.

---

## 10. CI/CD

### Flutter APK (`.github/workflows/flutter-build.yml`)

Trigger: push do `main` ze zmianami w `mobile/**`.

Pipeline:
1. `flutter pub get`
2. `flutter analyze --no-fatal-infos`
3. `flutter test`
4. `flutter build apk --release`
5. Publikacja jako GitHub Release → plik `OilEmpire.apk` (nie ZIP!)

APK dostępny pod: `https://github.com/Mario453-tech/www/releases/latest`

### PHP deploy (`.github/workflows/deploy-ftp.yml`)

Trigger: push do `main` lub `master`.

Pipeline:
1. PHPUnit (Unit + Integration z SQLite) — blokuje deploy
2. MySQL tests — informacyjne, nie blokują
3. FTP deploy przez `lftp` (implicit FTPS 990):
   - Deployuje tylko zmienione pliki (`git diff BEFORE..AFTER`)
   - Wyklucza: `vendor/`, `tests/`, `.git/`, `*.sql`, `*.log`, itd.
   - Zawsze dodaje `assets/version.txt` (timestamp dla cache-bustingu)
4. OPcache reset

Sekrety wymagane: `FTP_USER`, `FTP_PASSWORD`, `FTP_REMOTE_DIR`, `ENV_FILE`, `SITE_URL`, `OPCACHE_RESET_TOKEN`.

---

## 11. Auto-migracje DB (wzorzec Bootstrap)

Każdy moduł PHP z nowymi tabelami MUSI używać wzorca Bootstrap (nie phpMyAdmin):

**Krok 1:** Stwórz `src/NowyModulBootstrap.php`:

```php
<?php
function ensureNowyModulSchema(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo = Database::getInstance()->getConnection();
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') return; // pomiń w testach
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS nowa_tabela (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ...
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        GameLog::warn('ensureNowyModulSchema: ' . $e->getMessage());
    }
}
```

**Krok 2:** Wepnij w `src/init.php`:

```php
require_once __DIR__ . '/NowyModulBootstrap.php';
ensureNowyModulSchema();
```

Wzorce do kopiowania: `src/BankruptcyBootstrap.php`, `src/ChatBootstrap.php`.

---

## 12. Istniejące endpointy — mapa

| Endpoint | Metoda | Auth | Opis |
|----------|--------|------|------|
| `/api/v1/auth/login.php` | POST | nie | login, zwraca token |
| `/api/v1/auth/logout.php` | POST | tak | unieważnia token |
| `/api/v1/player` | GET | tak | dane gracza (KPI) |
| `/api/v1/market` | GET | tak | cena ropy, event, oferty |
| `/api/v1/wells` | GET | tak | lista studni gracza |

---

## 13. Co do zrobienia (zaplanowane, nierozpoczęte)

- [ ] **Wyczyść diagnostykę**: usunąć `api/v1/healthcheck.php` z serwera po potwierdzeniu stabilności
- [ ] **Ekran Rynku** (pełny moduł): wykresy ceny, aktywne oferty, formularz sprzedaży
- [ ] **Ekran Studni** (zastąpić `wells_screen.dart` modułem): lista studni w nowym układzie z KPI-style cards
- [ ] **"Następny tick za X"** na dashboardzie: użyć pola `tick.next_at_estimated` z `/api/v1/market`
- [ ] **iOS build** w CI (wymaga certyfikatu Apple Developer)
- [ ] **Tryb offline**: gdy brak sieci, pokazać ostatnie dane z SharedPreferences/sqlite

---

## 14. Zależności Flutter

```yaml
dependencies:
  http: ^1.2.0            # HTTP client do API
  shared_preferences: ^2.3.0  # token, locale
  provider: ^6.1.0        # state management (AuthProvider, LocaleProvider)
  intl: ^0.19.0           # NumberFormat (formatowanie kwot)
  webview_flutter: ^4.7.0 # WebView dla modułu "Gra"
```

Flutter: `3.27.x` (stable). Dart SDK: `>=3.3.0 <4.0.0`.

---

## 15. Jak testować lokalnie

```bash
cd mobile
flutter pub get
flutter analyze
flutter test
flutter build apk --release
```

Zmiana URL API (domyślnie `https://oilempire.pl`):
```dart
// mobile/lib/config/app_config.dart
static const String baseUrl = 'https://oilempire.pl';
```

W testach widgetów: zainicjuj Provider i LocaleProvider przez helper `_wrap(widget)` (patrz `test/market_event_card_test.dart`).

---

## 16. Skróty git / branching

- `main` — produkcja (PHP + Android APK release)
- `push-to-main` — branch roboczy dla zmian Flutter/PHP, PR → main
- Każdy push do `main` triggeruje oba workflow (deploy-ftp + flutter-build)
