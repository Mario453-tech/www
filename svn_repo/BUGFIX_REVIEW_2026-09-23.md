# Naprawy po przegladzie gry - 2026-09-23

## Zakres

- Rynek: sprzedaz natychmiastowa w serwisie, blokady zapasu i gracza, atomowa historia finansowa; anulowanie i edycja oferty sprawdzaja wlasciciela i status pod blokada.
- Bank i pozwolenia: ponowne wyliczenie naleznosci po blokadzie, jedna oplata i zmiana stanu w tej samej transakcji.
- Mapa: kolejnosc blokad gracz -> lokalizacja, ponowna kontrola dostepnosci, pozwolen i limitu odwiertow; platnosc przez PlayerPaymentService.
- Tick: inicjalizacja schematu przed transakcja, wspolny rollback okresu gracza, rzeczywiste obciazenie gotowki i audyt przez FinancialTransactionService. Niedokonczony okres zachowuje last_tick_at.
- Zadania: atomowy start i zakonczenie, zachowanie kolejki przy nieudanym starcie, propagacja krytycznych bledow.
- HR: podwyzka i ugoda od razu aktualizuja salary_satisfaction, bez dodatkowego cyklu morale.
- Bezpieczenstwo: limiter API w bazie, ogolne bledy PL/EN, sanitizacja pomocy, walidacja progow alertow, ograniczony i izolowany upload obrazow.
- UI: PRG i jednorazowy flash w dashboardzie/pomocy/alertach/newsach, zewnetrzne zasoby, usuwanie powiadomien dopiero po potwierdzeniu API.

## Dane i wdrozenie

Zmiany schematu sa addytywne: indeks wells(location_id, status), tabela
api_auth_rate_limits oraz klucze tlumaczen i parametry director_notifications.
Limiter posiada expires_at i indeks retencji; usuwa najwyzej 64 wygasle wpisy
na zadanie i nie usuwa rownolegle odnowionych licznikow.
Nie ma unikalnosci samego location_id. Sprzedane odwierty zachowuja historie.

Tick wymaga InnoDB dla zapisywanych tabel. Preflight blokuje rozliczenie przy
nietransakcyjnym silniku, zamiast udawac skuteczny rollback. Raport audytu
wskazuje takie tabele. Ewentualna konwersja produkcyjna wymaga osobnego backupu
i zatwierdzenia; nie jest wykonywana automatycznie. Schemat CI ma jawne InnoDB.

`php tools/transaction_race_audit.php` jest raportem read-only. Nie zwraca pieniedzy,
nie usuwa odwiertow i nie zmienia historycznych operacji. Niezgodnosci wymagaja
osobnej decyzji administratora i udokumentowanego procesu korekty.

Limit logowania: 5 prob na konto/IP i 30 na IP w ruchomych 15 minutach,
HTTP 429 z Retry-After. Upload: 20 MB lacznie, 1 MB na fragment, do 32
fragmentow, faktyczny MIME i dekodowanie obrazu, publikacja jako PNG.

## Weryfikacja

- Testy regresji finansow, taskow, HR, limitera, uploadu i interfejsu dodano przy odpowiadajacych modulach.
- MySQL: rownolegle procesy oraz wstrzykiwanie awarii magazynu/historii i ponowienie ticka.
- Pelne Unit + Integration + MySqlIntegration: 1108 testow, 14452 asercje, bez bledow i pominiec (PHP 8.5.0, wydzielona baza oil_review_20260923_test).
- PHPStan z limitem 1 GB: bez bledow. Lint 104 plikow PHP, encoding 1202 plikow, node --check, HR HTML standards oraz git diff --check: OK.
- Frontend uploadu: 20 testow Node. Przegladarka: 48 wariantow PL/EN (320, 360, 390, 768, 1024, 1440 px), potwierdzenia i niepoprawne odpowiedzi API: OK.
- Audyt dry-run na bazie testowej: wszystkie 9 kontroli bez rozbieznosci. Nie jest to audyt danych produkcyjnych.
- Niezalezne przeglady agentow: finanse, API, upload, panele, zadania i tick. Ostatni blocker propagacji bledu katastrofy zamknieto testem przez WellRiskHandler i ponownym przegladem.
- Commity kodu: 7f68ecf (finanse), 062b6c2 (bezpieczenstwo i UI), bee4934 (tick, zadania i HR).
- Pierwsza bramka CI wykryla brak jawnych dat w fixture'ach MySQL 8. Uzupelniono je bez zmiany schematu ani asercji; 34 testy / 388 asercji przechodza takze z STRICT_ALL_TABLES i zakazem zerowych dat. Produkcyjny deploy byl zablokowany.

## Wycofanie

Wycofac odpowiednie commity kodu przez git revert, bez cofania danych i bez
usuwania nowych kolumn/tabel. Zatrzymac cron na czas wymiany kodu ticka.
Nie odtwarzac produkcyjnej bazy z lokalnych fixture'ow ani nie uruchamiac
automatycznych rekompensat. Backupy kodu znajduja sie w backups/<obszar>/*.back.

## Stan

Integracja lokalna zakonczona. CI i wdrozenie musza zostac potwierdzone po pushu.
Nie wykonano napraw historycznych danych ani konwersji produkcyjnych silnikow.
Testy widokow uzywaja fixture'ow; nie zastepuja zalogowanej sesji na produkcji.
