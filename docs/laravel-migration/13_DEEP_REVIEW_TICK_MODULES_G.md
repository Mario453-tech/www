# Audyt szczegółowy 102 — partia G: panel modułów ticka

Data: 2026-10-30. Gałąź `feature/oilcorp-laravel-audit-baseline`. Pełny odczyt dwóch wcześniej skanowanych plików: `admin/tick_modules.php` (blob `51c56507453a8ef5a475feef4e439877bd81db4c`, 170 linii) i `templates/views/admin/tick_modules/main.php` (`742048b5a74ec322e361112fdf7f7b8fe05b5710`, 279 linii). Sprawdzono powiązania w `src/TickModuleAdminService.php` (`2ae89400bf1e7c5564b1e4604fa732b768275b7c`), `src/Tick/TickModuleConfigRepository.php` (`f82174e158f70b064428d63c1573de086b6a1bce`), `src/Tick/TickModuleScheduler.php` (`d4e1fabbfffd05e147214e7308a9f386171237e3`) oraz `src/Tick/TickContext.php`. Nie przeprowadzono testów runtime.

## Akcje, autoryzacja i pełna droga ustawienia

`admin/tick_modules.php:7-11` ładuje panel, wymaga zalogowanego administratora i tworzy serwis. POST (30–113) wymaga CSRF (34–37) i obsługuje cztery akcje. Widok pokazuje dla każdego modułu formularze zapisu, ręcznego uruchomienia i przywrócenia ustawień (126–164), a osobno czyszczenie historii (186–193). Formularze ręcznego uruchomienia, przywrócenia i czyszczenia mają `data-confirm`; globalny handler jest w `assets/js/modal.js`, lecz jego wczytanie i przebieg w przeglądarce pozostają do potwierdzenia.

| Akcja | Kontroler → serwis → zapis | Efekt w silniku | Test |
| --- | --- | --- | --- |
| `save_settings` | 40–46 → `TickModuleAdminService::updateSettings` → `TickModuleConfigRepository::update` → `tick_module_config.enabled, interval_ticks, max_items_per_run, updated_at` | `TickModuleScheduler::decision` czyta ustawienie (19–45), ustawia `run` i `limit`; `TickEngine` przekazuje limit do `TickContext` | `tests/Unit/TickModuleAdminServiceTest.php` sprawdza zapis/restore, ale w tej sesji nieuruchomiony |
| `restore_recommended` | 47–50 → serwis → repozytorium → te same kolumny | Przywraca wartości z `TickModuleCatalog` | istniejący test jednostkowy, nieuruchomiony |
| `run_module` | 51–83 → `TickCoordinator::runModule` → lock i `TickEngine::runOne` → tabele domenowe oraz `tick_module_config/tick_module_run_logs` | Wymuszone wykonanie jednego modułu; ma kontrolę zajętości i statusu | test HTTP i wpływu na gospodarkę: brak dowodu w tej partii |
| `cleanup_history` | 84–98 → serwis/repozytorium/statystyki → `tick_module_run_logs`, historia ticków | Usuwa historię starszą niż 2 dni | test jednostkowy istnieje, nieuruchomiony |

GET (116–155) synchronizuje listę modułów, pobiera konfigurację, paginuje logi i statystyki oraz przekazuje dane do widoku. `TickModuleAdminService::modules` wywołuje `TickRegistry::discover` i `syncModules`, co może zapisywać brakujące rekordy konfiguracyjne nawet podczas otwierania panelu. To należy uwzględnić przy migracji i uprawnieniach DB.

## Zachowanie potwierdzone kodem

- `src/TickModuleAdminService.php:55-68` wymusza `enabled=true` dla krytycznych modułów. Widok (132–145) pokazuje je jako zawsze aktywne. `TickModuleScheduler.php:26-31` uruchamia moduł krytyczny niezależnie od `enabled` i interwału. Jest to spójne na trzech warstwach; istniejący test `TickModuleAdminServiceTest::testCriticalModuleCannotBeDisabledFromAdminService` sprawdza część zachowania, lecz nie uruchamiano go tutaj.
- Dla modułów opcjonalnych `TickModuleScheduler.php:33-45` uwzględnia przełącznik, `interval_ticks` i `last_run_tick`. `TickEngine.php:112-114` przekazuje `max_items_per_run` jako limit do `TickContext`. Czy każdy moduł faktycznie wywołuje `moduleLimit`, wymaga kontroli każdego modułu.
- `admin/tick_modules.php:102-110` umieszcza tekst wyjątku w komunikacie panelu. Komunikat widzi zalogowany administrator, ale może ujawniać szczegóły schematu/ścieżek; przed migracją należy zastąpić go identyfikatorem zdarzenia i prywatnym logiem. **P2** ryzyko ekspozycji szczegółów błędu w panelu, bez potwierdzonego wycieku do niezalogowanych.

## Ryzyka migracyjne i testy do wykonania

**P1 — zapis przy odczycie panelu.** `admin/tick_modules.php:116` → `TickModuleAdminService.php:19-23` → `TickModuleConfigRepository.php:21-40` wykonuje `INSERT IGNORE` brakujących wierszy. Przeniesienie ekranu jako czystego GET w Laravel bez etapu synchronizacji może skutkować brakiem konfiguracji i błędem scheduler (`TickModuleScheduler.php:21-24`). Test: brak jednego wiersza, GET panelu, następnie tick; po migracji zapewnić jawny etap inicjalizacji schematu i konfiguracji.

**P2 — limit w formularzu nie jest jeszcze dowodem ograniczenia pracy modułu.** `templates/views/admin/tick_modules/main.php:143` → `TickModuleConfigRepository.php:63-71` → `TickModuleScheduler.php:26,30,44` → `TickContext::setModuleLimit`. Trzeba sprawdzić każdy moduł i test z limitem 1/2 dla liczby przetworzonych rekordów; samo zapisanie wartości nie potwierdza skutku. W szczególności w `MarketModule.php` nie ma odczytu `moduleLimit`, więc pole limitu dla tego modułu nie wpływa na obliczanie ceny. To może być świadomy brak zastosowania dla modułu jednorazowego; panel powinien to wyjaśniać.

Nie wykazano tu działania przeglądarkowego, rzeczywistego wykonania modułów, zachowania na anonimowej MySQL ani porównania wyglądu. Pełny audyt JS/CSS, tabel statystyk, wszystkich modułów i testów pozostaje otwarty. Status obu plików w rejestrze pozostaje skanem statycznym, a w trackerze otrzymują `SOURCE_READ_DEPENDENCIES_OPEN`.