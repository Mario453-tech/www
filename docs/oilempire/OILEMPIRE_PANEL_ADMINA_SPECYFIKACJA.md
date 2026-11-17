# OilEmpire — Panel Administracyjny (Filament v5): Pełna Specyfikacja

> Część wspólnej specyfikacji OilEmpire. [Indeks, mapa pakietów i zasady wspólnej zmiany](README.md). Zakres tego rozdziału: Interfejs GM i integracja panelu z modułami; uprawnienia i akcje podlegają wspólnej domenie oraz bezpieczeństwu. Sprzeczność między rozdziałami wymaga jawnego rozstrzygnięcia i aktualizacji wszystkich dotkniętych zapisów.


**Status:** KANONICZNY załącznik `OILEMPIRE_ARCHITEKTURA_SYSTEMOWA.md` (§18.5 wskazuje ten plik). Panel implementowany 1:1 z tą specyfikacją.
**Zakres:** warstwa wizualna i szablony, pulpit, nawigacja, katalog WSZYSTKICH zasobów (kolumny, filtry, akcje wiersza, formularze edycji z wariantami), katalog akcji GM, macierz uprawnień, testy akceptacyjne panelu.
**Zasada nadrzędna:** panel NIGDY nie zmienia reguł gry ad-hoc — każda mutacja to udokumentowana akcja GM (§18.3) lub wersja balansu (§29.7). RNG jest nietykalne (fair-play §22.6): GM koryguje skutki, nie przerzuca kości.

---

## 0. Zasady ogólne

1. **Filament v5** + auto-discovery modułów (§18.4): każdy moduł gry rejestruje własne zasoby panelem manifestu (§29.6) — rdzeń panelu nie zna parametrów modułów.
2. **Role (§18.3):** dokładnie jedno nieusuwalne `owner_admin` (administrator główny), dodatkowi `admin`, `gm_support` (etykieta UI: Moderator) i `gm_readonly`. `owner_admin` tworzy, blokuje i nadaje role innym kontom panelu; nie można utworzyć drugiego właściciela ani zdegradować ostatniego. Wszystkie role: obowiązkowe 2FA (§14.3), throttle logowania; panel ma miękki limit 20 prób/h na IP + alert do `owner_admin`.
3. **Języki panelu: PL/EN/DE** od początku. Administrator wybiera własny język interfejsu niezależnie od locale gracza. Wszystkie etykiety, formularze, walidacje, statusy, dialogi, powiadomienia, e-maile i eksporty przeznaczone dla człowieka mają tłumaczenia PL/EN/DE; treści gracza wyświetlane są w jego locale z oznaczonym fallbackiem do PL. Klucze techniczne i nazwy kont pozostają po angielsku. Kwoty w księgach i ekranach audytowych są opisane jako „PLN" (kanon wewnętrzny), bez przeliczania wartości.
4. **Każda akcja mutująca → `gm_action_log`:** kto, kiedy, co, na kim, wartość przed/po, `reason`. `owner_admin` nigdy nie musi wpisywać wolnego komentarza ani powodu zatwierdzenia; `reason` może być NULL. Pozostali administratorzy podają powód dla: wersji balansu (§29.5), anonimizacji RODO, przywracania backupu, korekt FTS, banów >30 dni. `gm_support`: powód wymagany przy KAŻDEJ mutacji. Wymagane dane merytoryczne działania (np. podstawa bana lub kwota korekty) pozostają polami operacji, nie komentarzem.
5. **Parity nawigacji:** grupy 1:1 ze starym panelem (§18.1, zrzuty G1–G7) ROZSZERZONE o nowe moduły (Rafineria/EPC, Balans, Wydarzenia, Bezpieczeństwo). Parity = struktura i funkcje; warstwa wizualna = nowa (Dark Luxury), bez kopiowania starego CSS.
6. **Wzorce zapisu:** panel pisze wyłącznie przez serwisy domenowe (te same co gra) — zero bezpośredniego SQL; kwoty BIGINT grosze; daty `DATETIME(3)` UTC.
7. **Read-only z uzasadnieniem:** księgi append-only (FTS, `oil_movements`, `gm_action_log`, `balance_versions`) są w panelu wyłącznie do odczytu i eksportu — korekty tylko udokumentowanymi kodami GM (sekcja 5).
8. **Zero teleportów:** żadna akcja GM nie przenosi zasobów poza fizyką gry; rekompensaty tworzą udokumentowane zapisy (np. `GM_COMPENSATE` z przeciwkontem `SYS_TREASURY`).
9. **Potwierdzenia:** akcje nieodwracalne = modal z wpisywanym potwierdzeniem (nazwa zasobu) + podwójny klik; akcje masowe pokazują dokładną listę dotkniętych encji przed zatwierdzeniem.
10. **Wydajność:** listy paginowane (25/50/100), filtry po indeksach, wykresy z agregatów (`price_history`, telemetry), żadnego `SELECT *` po `financial_transaction_service` bez partycji (§29.4).

---

## 1. Warstwa wizualna — motyw `oilempire-panel` i szablony

### 1.1. Motyw (tokeny współdzielone z frontendem gracza)

Wspólne tokeny designu (Tailwind config) dla gry i panelu — spójna marka, ciemny motyw WYŁĄCZNY (bez przełącznika light; decyzja: redukcja powierzchni testów i spójność z „Dark Luxury" §1). CSS panelu ma własny punkt wejścia w module Admin, a CSS zasobów modułowych pozostaje przy modułach; wspólny katalog zawiera tylko tokeny i komponenty współdzielone (§8.0a dokumentu głównego).

| Token | Wartość | Zastosowanie w panelu |
|---|---|---|
| `bg.base` | `#08080f` | tło strony |
| `bg.surface` | `#10101a` | karty, tabele, modale |
| `bg.raised` | `#161624` | nagłówki tabel, dropdowny |
| `border.subtle` | `#1f1f2e` | obramowania, separatory |
| `gold.primary` | `#c8a84b` | primary Filament (przyciski, aktywna nawigacja, linki) |
| `gold.light` | `#d8b95c` | hover primary, nagłówki sekcji |
| `accent.emerald` | `#34d399` | sukces, stany `operational/active/approved` |
| `accent.amber` | `#fbbf24` | ostrzeżenie, stany `paused/idle_standby/pending_review` |
| `accent.red` | `#e05252` | błąd, stany `late/emergency/banned/rejected`, danger zone |
| `accent.slate` | `#64748b` | stany `dormant/superseded/draft`, dane nieaktywne |
| `text.primary` | `#e8e6e3` | tekst główny |
| `text.muted` | `#9ca3af` | etykiety, pomoc kontekstowa |

Mapowanie na zmienne Filament v5: `--primary-*` → paleta złota, `--danger-*` → `accent.red`, `--success-*` → emerald, `--warning-*` → amber, `--gray-*` → neutralna ciemna (bg/base→surface→raised). Kontrast tekstu ≥ 4.5:1 (WCAG AA) — złoto na `#10101a` spełnia; `text.muted` tylko dla treści drugorzędnych.

### 1.2. Typografia

* **Syne** — logo panelu, nagłówki stron/grup nawigacji (700).
* **Montserrat** — cały UI (400/500/600).
* **JetBrains Mono** — WSZYSTKIE liczby w tabelach, ID, klucze parametrów (`game_balance`), hashe, kody FTS, współrzędne snapshotów; tabular-nums.

### 1.3. Kanon badge'y statusów (jedna rodzina = jeden kolor, cały panel)

| Rodzina | Kolory (`accent.*`) | Przykłady statusów |
|---|---|---|
| Zdrowe | emerald | `operational`, `active`, `approved`, `committed`, `settled`, `delivered`, `granted` |
| Wstrzymane/oczekujące | amber | `paused`, `idle_standby`, `pending_review`, `building_paused_funds`, `waiting_bay`, `in_transit`, `late` (pierwsze 24h) |
| Krytyczne | red | `emergency_shutdown`, `late` (>24h), `banned`, `rejected`, `disputed`, `failed`, `bankruptcy`, `seized` |
| Uśpione/historyczne | slate | `dormant` (z tooltipem `dormant_reason`), `superseded`, `rolled_back`, `depleted`, `expired`, `cancelled` |

Zasada: ten sam status ma TEN SAM kolor w każdym zasobie (spójność cross-modułowa); badge z `dormant_reason` i `state_version` w tooltipie.

### 1.4. Formatery (helpery wyświetlania — obowiązkowe we wszystkich zasobach)

* `pln(grosze)` → „1 234 567,89 PLN" (spacja tysięczna, przecinek dziesiętny, mono, prawostronnie); wartości ujemne na czerwono z „−".
* `bbl(x)` → „18 400,0000 bbl" (4 miejsca — precyzja DECIMAL(16,4) §11A).
* `pct(x)` → „12,5%" (1 miejsce).
* `dt_utc(x)` → „2026-11-14 21:37:05 UTC" + tooltip z czasem lokalnym przeglądarki; wszystkie czasy w panelu domyślnie UTC (kanon gry), przełącznik „pokaż lokalny" per sesja.
* `delta(old,new)` → „1 800 → 2 400 (+33,3%)" — używane w diffach i logach.
* `idem(key)` → skrócony hash + kopiowanie całości kliknięciem.

### 1.5. Szablony tabel (Resource → Table)

* Kolumny liczbowe: mono, prawostronne; sortowanie serwerowe; domyślny sort per zasób (np. `created_at desc` dla ksiąg, `id asc` dla katalogów).
* Kolumna ID zawsze pierwsza (klik = View); kolumna akcji ostatnia, przypięta.
* **Akcje wiersza — kanon 3+1:** `View` (ikona oka; zawsze), `Edit` (ołówek; zawsze gdy zasób edytowalny — patrz sekcja 4, przy read-only zastąpione `Details`), `Actions ▾` (dropdown akcji kontekstowych GM, filtrowany rolą), + ew. `Logs` (historia zmian encji z `gm_action_log`/logów domenowych).
* Filtry: boczne (Filament `Filter`/`SelectFilter`/`TernaryFilter`), zapisywane w query stringu (linkowalność — GM wysyła GM-owi gotowy widok).
* Bulk actions: tylko jawne (zaznaczenie checkboxem), z podglądem listy dotkniętych (§0.9); domyślnie wyłączone dla ksiąg.
* Eksport: CSV/JSON aktualnego widoku (z filterami) — każda tabela; eksport pełny = zadanie kolejkowe z powiadomieniem (nie blokuje requestu).

### 1.6. Szablony formularzy (Resource → Form)

* Sekcje = fieldsety z nagłówkiem Syne; pola grupowane domenowo (nie alfabetycznie).
* **Komponent `ReasonInput`:** textarea min. 10 znaków, obowiązkowy dla ról wskazanych w §0.4; dla `owner_admin` pole jest opcjonalne i nie blokuje zatwierdzenia. Treść, jeśli podana, trafia do `gm_action_log.reason` i `balance_versions.reason`.
* **Komponent `DangerZone`:** czerwona ramka, checkbox „rozumiem skutki" + wpisanie nazwy zasobu (np. „RAFINERIA-1042") przed odblokowaniem przycisku.
* **Komponent `MoneyInput`:** edycja w PLN z przeliczeniem na grosze, walidacja zakresu z `game_balance` (min/max), podgląd „przed → po".
* **Komponent `EnumSelect`:** select z kanonicznym słownikiem statusów (VARCHAR + BackedEnum §24.4) — tylko dozwolone przejścia stanów (maszyny stanów z dokumentu; niedozwolone opcje wyszarzone z tooltipem „dlaczego").
* **Komponent `SchemaForm` (balans §29.7):** formularz generowany z JSON Schema modułu — typ, min/max, enum, jednostka w etykiecie, walidacja natychmiast.
* **Komponent `DiffViewer`:** dwie kolumny old/new per klucz + Δ% + podświetlenie wyjścia poza zakres; używany w `balance_versions` i w podglądzie każdej akcji GM „przed/po".
* Zapis formularza = jedna transakcja domenowa; porzucony formularz z ostrzeżeniem (beforeunload).

### 1.6a. Komunikaty i dialogi panelu

Panel korzysta ze wspólnego systemu komunikatów §27.2a specyfikacji głównej i zasad zasobów §8.0a: toast, błąd pola, alert, powiadomienie oraz modal Filament mają lokalną ikonę SVG właściwą dla zdarzenia, etykietę i tekst. Komunikaty sukcesu, ostrzeżenia, błędu, informacji oraz potwierdzenia nie mogą korzystać z natywnych `alert()`, `confirm()` ani `prompt()`. Dotyczy to również akcji GM, błędów walidacji, odmowy 403, błędów 500, wyników symulatora i potwierdzeń w `DangerZone`. Modal wymaga dostępnego tytułu, opisu skutków, poprawnego fokusu i działania od 320 CSS px; teksty panelu są w języku wybranego administratora (§0.3). Ikona i kolor nie zastępują tekstu. Zapis i kontrola uprawnień pozostają po stronie serwera.

### 1.7. Wykresy i wizualizacje

Chart.js (kanon §12 — brak zewnętrznych licencji): liniowe (ceny, telemetria), słupkowe (per region/moduł), sparkline w tabelach (trend 7 dni przy cenie spot). Kolory serii: złoto → emerald → amber → slate; tło `bg.surface`, siatka `border.subtle`.

### 1.8. Strony niestandardowe (poza CRUD)

* **360° Gracza** (§18.1) — układ zakładek jak w starym panelu, rozszerzony o zakładki nowych modułów (sekcja 4, G3).
* **Mapa świata/regionów** — SVG regionów z warstwami: aktywa graczy (kropki), rurociągi (linie z przepustowością), trasy cystern (animowane kropki z Reverb), huby/porty (kwadraty z obłożeniem), rafinerie (ikony z klasą), wydarzenia regionalne (banery). Klik = deep-link do zasobu. Read-only (interakcje GM z mapy: tylko podgląd + szybkie filtry).
* **Kalendarz wydarzeń** (§25) — oś czasu: zapowiedziane (`announced_at`), aktywne, historia; pasmo globalne i regionalne.

---

## 2. Pulpit główny (Dashboard) — widgety i źródła danych

Układ siatki 3 kolumn; odświeżanie: Reverb (live) albo polling 30s. Kolejność domyślna = kolejność poniżej (drag&drop per admin, zapis w `user_preferences`).

| # | Widget | Treść | Źródło | Alarm |
|---|---|---|---|---|
| 1 | **Zdrowie serwera** | czas ostatniego sweepa vs budżet 60s (§23.10), fazy z czasem, kolejki (rozmiar/opóźnienie względem `due_at`, ponowienia, dead-letter), Reverb połączenia, `deadlock_log` 24h | `simulation_sweeps`, telemetry §29 | sweep >48s = amber, >60s = red |
| 2 | **Gospodarka** | Σ `PLN_BANK`, Σ `PLN_CASH`, Σ escrow; inflacja 30d vs bramka <5%/mies.; wykres spot 7/30 dni | FTS agregaty, `price_history` | inflacja >5% = red |
| 3 | **Integralność FTS** | wynik testu sumy zerowej kont (ostatni przebieg dzienny §29.2), rozjazdy | rekoncyliacja | ≠0 = red + link do raportu |
| 4 | **Gracze** | online teraz, rejestracje 7d, aktywni/dobę, `dormant` (z rozbiciem `dormant_reason`), urlopy | sesje, `companies` | dormant >5% populacji = amber |
| 5 | **Alert: balans emergency** | baner „aktywna wersja EMERGENCY od X" + kto + powód (do zastąpienia zwykłą wersją) | `balance_versions` | zawsze widoczny gdy aktywny |
| 6 | **Wydarzenia światowe** | aktywne (z mnożnikami i `ends_at`), kolejka FIFO, zapowiedziane | `world_events` | kryzys aktywny = amber |
| 7 | **Pula ubezpieczeń** | saldo `SYS_INSURANCE_POOL` vs kapitał 100M, wypłaty 24h/50M per gracz, próg reasekuracji 20M | §31.6 | <20M = red (pożyczka Skarbu +20% składek) |
| 8 | **Spory i wnioski** | otwarte `disputed` B2B (z wiekiem vs SLA), odwołania od banów, reklamacje | §13.2, §30 | spór >48h bez ruchu = amber |
| 9 | **Wersje balansu** | `pending_review` (wiek), ostatnie `approved/activated`, wynik symulatora | §29.5/§29.7 | pending >72h = amber |
| 10 | **Bezpieczeństwo** | próby logowań (throttle), wykryte multi-konta (§14), flagi anty-cheat 24h (§22.5), naloty/sabotaże | telemetria | spike ×3 = red |
| 11 | **Rafinerie i budowy** | aktywne per klasa, budowy (etap/transza/wykonawca), `building_paused_funds` (wiek) | §31 | paused >7 dni = amber |
| 12 | **Szybkie akcje GM** | przyciski: wymuś sweep (§23), wstrzymaj generator NPC (§13.1a), tryb konserwacji (banner graczy), odśwież snapshot majątkowy (z powodem) | akcje z sekcji 5 | każda → `gm_action_log` |

---

## 3. Nawigacja — mapa grup i zasobów (parity + nowe moduły)

Grupy zgodne ze starym panelem (§18.1 G1–G7), rozszerzone. Każdy zasób: `Resource` Filament z modułu gry (auto-discovery §18.4).

| Grupa (kolejność w sidebarze) | Zasoby |
|---|---|
| **1. PULPIT** | Dashboard (widgety §2), Mapa świata, Kalendarz wydarzeń |
| **2. RYNEK** | Rynek ropy (monitor spot), Historia cen, Zlecenia limit, **Balans gry** (§29.7), Incydenty, Czarny rynek (oferty/transakcje/wykrycia), Generator NPC (§13.1a) |
| **3. GRA** | Gracze (360° §18.1), Firmy, Odwierty, Koncesje/regiony, Pożyczki, Narzędzia GM, Kadry (HR), Zadania techniczne (TTS), Zarząd (Boardroom), Szkolenia i kursy |
| **4. TRANSPORT** | Huby, Rurociągi (odcinki), Cysterny i flota, Kursy (trips), Porty i tankowce, Rejsy morskie, Księga ruchów ropy (`oil_movements`, read-only) |
| **5. KONTRAKTY** | Kontrakty systemowe (NPC), Oferty B2B, Transze i escrow, **Spory B2B** (rozstrzyganie), Kartele, Kaucje i przepady |
| **6. FINANSE** | Księga FTS (read-only), Konta (graczy i SYS_*), Zobowiązania (`obligations`), Egzekucje komornicze, Podatki (snapshoty + `asset_valuations`), Ubezpieczenia (pula/polisy/szkody), GBBL i Kroplówka, Tiery (snapshoty §22.6.1) |
| **7. TREŚCI** | Szablon strony, Nawigacja i nagłówek (§18.2), Pomoc admina, Instrukcja gracza, Szablony dialogów (§5.2), Powiadomienia (§27.2), Komunikaty GM, Newsletter (TinyMCE), Osiągnięcia (§27.3), CMS strony publicznej |
| **8. ŚWIAT** | Wydarzenia światowe (§25), Regiony i Frontier Basins (§28), Działki rafineryjne i aukcje (§31.2.1), RARS (rezerwy strategiczne) |
| **9. RAFINERIA / EPC** | Rafinerie, Budowy i transze, Wykonawcy EPC (katalog 24 firm), Produkty i jakość (partie), Księga ruchów produktów (read-only) |
| **10. BEZPIECZEŃSTWO** | Sabotaże i śledztwa (§7), Ochrona (eskorty/konwoje/drony), Sesje i urządzenia (2FA), Bany i odwołania (§30), Flagi anty-cheat (§22.5), Czat — moderacja (§26) |
| **11. ADMINISTRACJA** | Role i uprawnienia GM, Dziennik akcji GM (read-only), Wersje balansu (§29.7), Moduły i manifesty (§29.6), Backupy i eksporty (§29), Raporty symulatora, RODO i anonimizacje (§16), Ustawienia panelu |

Zasady nawigacji: grupa widoczna tylko przy choć jednym zasobie dostępnym dla roli; kolejność grup stała (jak wyżej); zasoby premium/modułów wyłączonych (`enabled=false`) — widoczne, wyszarzone, z badge „moduł wyłączony" (zero-wipe: dane istnieją, UI read-only).

---


---

## 4. Modularność panelu — kontrakt manifestu (rozszerzenie §29.6)

Panel jest SKŁADANY z manifestów modułów — rdzeń panelu nie zawiera wiedzy o żadnym module gry. Nowy moduł (także premium) dokłada swoją część panelu przez drop-in, bez zmian w rdzeniu.

Obowiązuje [standard modułu i wdrażania](OILEMPIRE_STANDARD_MODULU_WDROZENIE.md). Manifest ma ścieżkę `Modules/<Name>/manifest.yaml`; panel pokazuje stany runtime, zależności i blokery. Wyłączenie rozpoczyna `draining`, a istniejące procesy są nadal rozliczane. Kod modułu jest dostarczany przez wydanie, nie upload ZIP w panelu.

### 4.1. Co moduł wnosi do panelu (sekcja `panel` manifestu)

```yaml
# Modules/<Key>/manifest.yaml → sekcja panel:
panel:
  navigation:                 # własna grupa LUB dopięcie do istniejącej
    - group: "9. RAFINERIA / EPC"   # nowa grupa tworzona automatycznie
      order: 90                    # rdzeń posiada G1–G11 (order 10–110); moduły > 1000 doklejają się na koniec, albo:
      # insert_after: "5. KONTRAKTY"
      icon: "heroicon-o-building-library"
  resources:                  # zasoby Filament (klasy z modułu)
    - RefineryResource        # tabela/formularz wg szablonów §1.5/§1.6
    - RefineryBuildResource
    - EpcContractorResource
  pages: [ RefiningMapPage ]  # strony niestandardowe (§1.8)
  widgets:                    # widgety pulpitu z deklarowanym slotem
    - { class: RefineriesWidget, slot: "ops", order: 11 }
  player360_tabs:             # WŁASNE zakładki widoku 360° gracza — rdzeń ich NIE hardkoduje
    - { key: refining, label: "Rafinerie", order: 60, component: RefineriesTab, provider: RefiningPanelDataProvider }
  relation_resolvers:         # kanoniczne deep-linki między modułami (§4.3)
    - { entity: refinery, label: "Rafineria #{id}", route: refining.refineries.view }
    - { entity: plot, label: "Działka #{id}", route: refining.plots.view }
  gm_actions:                 # rejestrowane akcje GM — pojawiają się w „Narzędzia GM" i w Actions ▾ zasobów
    - { code: GM_REFINERY_FORCE_STANDBY, target: refinery, roles: [admin], reason: required,
        form: { mode: { type: enum, values: [idle_standby], note: "tylko przejścia legalne z maszyny §31.2" } } }
  balance_schema: refining    # klucz w modules.<key>.params → SchemaForm w „Balans gry" (§29.7)
  reverb_channels: [ "panel.refining.{region}" ]   # live widgety
  permissions: [ "refinery.view", "refinery.gm" ]  # domyślnie mapowane: admin=all, gm_support=view+oznaczone, gm_readonly=view
```

### 4.2. Rdzeń panelu (jedyny kod „centralny")

Shell: motyw i tokeny (§1.1–1.4), autoryzacja + 2FA, budowniczy nawigacji (składa grupy z manifestów, waliduje konflikty przy starcie), siatka pulpitu (sloty: `health`, `econ`, `ops`, `security` — widgety modułów wpinają się w sloty), shell 360° gracza (rdzeń渲染uje zakładki bazowe: Przegląd, Finanse, Reputacja, Bezpieczeństwo; zakładki modułowe z manifestów wg `order`), przeglądarka `gm_action_log`, edytor ról. **Dodanie modułu = wrzucenie katalogu modułu + odświeżenie cache manifestów — zero edycji rdzenia.**

### 4.3. Łączenie modułów (cross-linki i relacje)

* **Kanon deep-linku:** każda komórka tabeli i każde pole formularza będące kluczem obcym renderuje się jako link wygenerowany z `relation_resolvers` modułu-właściciela encji (np. kolumna `epc_contract_id` w rafinerii → link do zasobu modułu Refining; `hub_id` w kursie → link modułu Logistics). Brak resolvera = tekst + ostrzeżenie w teście P12 (pokrycie resolverów mierzone automatycznie).
* **Relacje w formularzach:** moduł-właściciel rejestruje swoje RelationManagers w zasobach innych modułów przez manifest (`relations: { host: players, tab: refining, manager: RefineriesRelationManager }`) — host nie zna gości.
* **Zakładki 360°:** agregacja przez `player360_tabs` — widok gracza rośnie razem z modułami (stary panel miał sztywny układ; nowy składa się z części).
* **Akcje GM na encjach obcych:** moduł rejestruje `gm_actions` z `target` = typ encji innego modułu (np. Banking rejestruje akcję na `refinery` przy egzekucji Fazy 3) — akcja pojawia się w Actions ▾ zasobu docelowego, uprawnienia i reason wg deklaracji.
* **Kolizje:** zduplikowany slug zasobu lub kod akcji GM = błąd walidacji manifestu przy starcie panelu (fail-fast, test P14); dwa modułe w tej samej grupie nawigacji — łączone wg `order`.

### 4.4. Cykl życia modułu w panelu (zero-wipe)

| Stan modułu | Zachowanie panelu |
|---|---|
| runtime active | dostępne operacje według polityk; enabled jest zatwierdzonym zamiarem |
| runtime draining | enabled=false blokuje nowe zobowiązania; kontynuacja, recovery i obsługa rozpoczętych procesów pozostają dostępne zgodnie z politykami |
| runtime disabled | historia read-only; brak nowych procesów, jawne akcje recovery wymagają odrębnego uprawnienia |
| runtime faulted | komunikat incydentu, zakres blokady i audytowane recovery; brak automatycznej reaktywacji |
| premium=true | badge PREMIUM; role GM nadal podlegają politykom i zakresowi zasobu |
| nowy moduł | installed/ready do czasu zaliczenia walidacji, migracji i zatwierdzenia aktywacji |
| usunięcie kodu | niedozwolone przy procesach, zależnościach i historii wymagającej jego kontraktów; plan migracji i zachowanie odczytu przed usunięciem |


---

## 5. Katalog zasobów — kolumny, filtry, akcje wiersza, formularze

Zapis zwarty: **K** = kolumny tabeli, **F** = filtry, **A** = akcje wiersza (View/Edit/Actions▾ z kodami GM z sekcji 6), **E** = formularz edycji (pola, warianty, opcje), **R** = role. Kwoty zawsze `MoneyInput`/`pln()`, statusy `EnumSelect` + kanon badge (§1.3), daty UTC (§1.4). „read-only" = brak Edit, akcje tylko zadeklarowane.

### Grupa 2. RYNEK

**Monitor spot** (strona): live cena + wolumen okna 60-min, chłonność NPC per region (progi 70/100% §19.3), mnożniki wydarzeń, wykres 7/30 dni; GM: brak (zmiany wyłącznie przez Wydarzenia/Balans). **R:** wszyscy.
**Historia cen** `price_history`: **K** ts, region, towar, cena, wolumen, mnożnik wydarzenia · **F** zakres, region, towar · **A** View, eksport · read-only. **R:** wszyscy.
**Zlecenia limit** `market_offers`: **K** id, firma (link), towar, wolumen, `reserved_bbl`, `limit_price`, status (`open/triggered/cancelled`), wiek · **F** status, firma, towar, cena min/max · **A** View, `GM_OFFER_CANCEL` (zwraca ropę do magazynu; reason) · read-only. **R:** view wszyscy; cancel admin+support.
**Balans gry** (§29.7): drzewo modułów → wszystkie liście `modules.*.params` (`SchemaForm`, także listy/receptury/mapy; raport pokrycia YAML↔schemat); lista kandydatur: **K** id, moduł, status, proponujący, wiek, wynik symulatora, `yaml_hash` · **F** status, moduł, autor · **A** View diff (`DiffViewer`), Zatwierdź (admin, inny niż autor), Odrzuć (reason), Aktywuj, Rollback (= nowa kandydatura), Emergency (admin, typed-confirm) · **E** edycja parametru wyłącznie przez kandydaturę (nigdy wprost). **R:** propose admin+support; approve/emergency admin.
**Incydenty** `incidents`: **K** id, typ, klasa, encja (link resolverem), firma, start/koniec, koszt, status · **F** klasa, typ, firma, zakres, seed · **A** View (timeline + seed HMAC + wynik rzutu — replayowalne), `GM_INCIDENT_RESOLVE` (koszt przejmuje firma/pula/Skarb — wariant w formie; reason), oznacz false-positive (telemetria) · **E** tylko `admin_note`. **R:** view wszyscy; resolve admin+support.
**Czarny rynek**: (a) oferty skupu: **K** wolumen, cena/premia, `risk_base`, TTL — read-only; (b) transakcje: **K** firma, wolumen, premia 15%, Δ score, wykryta?, kara 200% · **F** firma, wykryte, zakres; (c) wykrycia `black_market_detected`: szczegół + kara + Δ wiarygodności −12; (d) ranking BMS: score, decay, flaga szarej strefy ≥30 (highlight), EPC-F aktywny · **A** `GM_BMS_ADJUST` (±, reason), zamrożenie ofert firmy (dochodzenie). **R:** view admin+support; adjust admin.
**Generator NPC** (§13.1a): **K** region, cel puli `3+floor(gracze/25)` max 8, aktywne oferty, TTL · **A** pauza/wznów (region/global), wymuś uzupełnienie, `GM_OFFER_CANCEL`; parametry wyłącznie przez Balans. **R:** admin.

### Grupa 3. GRA

**Gracze** `players` (360° §18.1): **K** id, firma, e-mail (maska `m***@***`), Tier, wiarygodność, B2B, `credit_score`, BMS+flaga, status (`active/vacation/dormant/banned` + `dormant_reason`), netto (snapshot), online, 2FA · **F** status, Tier, region, `dormant_reason`, flaga szarej strefy, 2FA brak · **A** View 360° (zakładki rdzenia + modułów), Edit, Actions▾: `GM_CRED_ADJUST`, przelicz `credit_score` (z wzoru §17.1 — bez ręcznego nadpisywania), `GM_SHIELD_GRANT/REVOKE`, `GM_MEDIATION_FORCE`, dormant→recovery, `GM_BAN/GM_UNBAN`, `GM_GBBL_ADJUST`, reset 2FA (po weryfikacji tożsamości — procedura §14.3), link resetu hasła (jednorazowy, 60 min), `GM_ANONYMIZE` (admin, DangerZone + typed-confirm, nieodwracalne §16) · **E** (edytowalne pole-ka): e-mail (z maską i re-maską po zapisie), locale PL/EN/DE (EnumSelect), urlop on/off (twarde warunki §19.2 — EnumSelect blokuje przy aktywnym incydencie/dostawie z tooltipem), status — wyłącznie legalne przejścia · **R:** view wszyscy; edit+akcje support (cap patrz §6); anonimizacja/emergency admin.
**Firmy** `companies`: **K** nazwa, właściciel (link), wiarygodność, `contract_reputation`, B2B, pracownicy, aktywa (snapshot), Tier, status · **F** status, region, Tier · **A** View (360° firmy = agregacja zakładek modułowych), `GM_COMPANY_RENAME` (nadużycia nazw/impersonacja; reason + powiadomienie gracza), blokada konta multi (§14 — reason, dowody). **R:** admin+support.
**Odwierty** `wells`: **K** id, firma, region, tier (`standard/premium/black_market`), `base_rate`, condition, wear, bufor/zasobność (DECIMAL), status (enum §9.1: drilling/awaiting_connection/active/choked/paused_storage/depleted/abandoned), rurociąg (link) · **F** status, tier, firma, region · **A** View (wykres produkcji + Δt rozliczenia), `GM_WELL_REPAIR` (wariant: naprawa na koszt firmy / darmowa z reason), ustaw status (EnumSelect — legalne przejścia), `GM_WELL_DEPLETE` (admin, DangerZone) · **R:** view wszyscy; repair support; reszta admin.
**Koncesje i regiony**: **K** firma, region, typ P1/P2, wydano/wygasa (180 dni), opłata odnowieniowa (5% wyceny regionu), status · **F** region, typ, wygasające <30 dni · **A** View, `GM_PERMIT_EXTEND` (reason), `GM_PERMIT_REVOKE` (podstawa + reason) · pula regionów + harmonogram Frontier Basins (§28) read-only. **R:** admin.
**Pożyczki** `loans`: **K** firma, kapitał, oprocentowanie, pozostało, rata, due, status (`current/late/restructured`) · **F** status, firma, po terminie · **A** View (harmonogram + historia spłat → `credit_score`), `GM_LOAN_RESTRUCTURE` (**E**: warianty — wydłużenie okresu / obniżka stopy / karencja; pole `new_terms_json` z walidacją widełek §17.1; reason), `GM_LOAN_FORGIVE` (częściowe/całkowite; przeciwkonto `SYS_TREASURY`; reason), wymuś Fazę 1 (gdy kanoniczne warunki spełnione — przycisk aktywny tylko wtedy) · **R:** admin; support: restructure z cap.
**Narzędzia GM**: wyszukiwarka zarejestrowanych `gm_actions` (kod/cel/moduł/role) + formularz wykonania ze schematu akcji + feed ostatnich akcji; każda akcja → `gm_action_log`. **R:** wg deklaracji akcji.
**Kadry (HR)**: (a) pracownicy: **K** firma, stanowisko, przypisanie (obiekt link), sub-skille 1–10, pensja vs `expected`/`min_market`, `salary_satisfaction`, morale, status (`assigned/unassigned/training/on_strike`) · **F** firma, stanowisko, status, morale <35; (b) rynek kandydatów: `expected_salary` (mnożnik 1,0–1,6), osobowość (`loyal/standard/ambitious`) · **A** View, mediator przy sporze (`GM_MEDIATION_FORCE`), podgląd wzoru morale (§4.3 z rozbiciem składników i lag-1) · read-only (pensje/zatrudnienie = domena gracza; GM nie steruje kadrami wprost — jedyne: `GM_STRIKE_BREAK` admin, DangerZone, reason; spójne z §5) · **R:** view wszyscy; akcje admin.
**Zadania TTS**: **K** firma, zadanie (klucz z katalogu §11.3), `seconds_remaining`, inżynier (link), materiały, status · **F** firma, typ, aktywne · **A** View, `GM_TASK_CANCEL` (zwrot materiałów), `GM_TASK_FINISH` (reason — nadużycia audytowane w logu) · **R:** support.
**Zarząd (Boardroom)**: **K** firma, rola (5 dyrektorów), pracownik, skill kluczowy, lojalność, wakaty · read-only + linki. **R:** wszyscy.
**Szkolenia**: katalog kursów (z Balansu), sesje w toku (postęp, pass 70% +10 p.p./powtórka cap +30), wyniki egzaminów (seed HMAC — podgląd replayu rzutu); read-only (RNG nietykalne); `GM_CERT_REVOKE` (admin, sfałszowany certyfikat — reason). **R:** view support.

### Grupa 4. TRANSPORT

**Huby**: **K** id, typ (mały/średni/duży/port), właściciel (NPC/gracz link), pojemność, bufor, wykorzystanie %, tryb (normal/turbo), koszt miejsca/dobę (wzór §11A.2: utrzymanie×24÷miejsca), kolejka `waiting_bay` · **F** typ, region, obłożenie >80% · **A** View (bufory w czasie, line-pack), `GM_HUB_RELIEVE` (wymuś odholowanie z kolejki per reguła 48h §21 — skrót z reason), podgląd opłat · **R:** view wszyscy; relieve support.
**Rurociągi + odcinki**: **K** typ (polowy/standardowy/magistralny/podmorski; planowane: podgrzewany, nielegalne wcięcie — badge „v2"), długość, `wear_level`, condition, przepustowość, line-pack, źródło (nowy/używany/black_market +30–40% ceny, ×0,9 przepust, ×1,5 zużycie) · **F** typ, wear >60, black_market · **A** View (profil zużycia + przeglądy obowiązkowe), `GM_SEGMENT_REPAIR` (koszt firmy/Skarb wariant), zamknij odcinek (tylko jako skutek incydentu — link do incydentu) · **R:** view wszyscy; repair support.
**Cysterny i flota**: **K** pojazd (standard 500 bbl/opancerzona 350 bbl), condition, `road_loss_pct`, przypisanie, ochrona (eskorta/konwój aktywne §7.2) · **A** View, `GM_VEHICLE_REPAIR`. **R:** support.
**Kursy (trips)**: **K** id, trasa (origin→dest linki), ładunek bbl (DECIMAL), właściciel ładunku, ETA, stan (`załadowany→w_trasie→waiting_bay→kredytowany→dostarczony` + `odholowany` §11A.8), opłata · **F** stan, firma, wiek > ETA · **A** View (mapa + timeline), `GM_TRIP_FORCE_DELIVER` (tylko przy zatkaniu systemu; idempotentne `sweep_no`; fizyczna dostępność wymagana — brak teleportu; reason) · **R:** view wszyscy; force admin.
**Porty i tankowce / Rejsy morskie**: **K** port, kolejka, czas rozładunku; rejs: statek, ładunek, ETA, stan · analogicznie do kursów; `GM_VOYAGE_*` jak trips. **R:** support.
**Księga `oil_movements`**: read-only — **K** id, typ ruchu, from/to (resolver), bbl DECIMAL(16,4), właściciel na moment zapisu, `idempotency_key`, sweep · **F** firma, typ, zakres, klucz · eksport; korekty wyłącznie kodami GM z przeciwkontem. **R:** wszyscy.

### Grupa 5. KONTRAKTY

**Kontrakty systemowe NPC** (§13.1a): **K** id, NPC-kupiec (katalog), tier (T1 240–960 bbl/T2/T3/premium), tryb cenowy (`fixed` 1,02–1,10 snapshot / `market_multiplier` 0,94–1,06 / `market_plus_bonus` +4–15), transze (postęp), kaucja 10% (5% premium), podtyp (`aviation` ≥92 ×1,20 / `rars` ≥90 +20%), reputacja wymagana · **F** tier, tryb, status, region · **A** View (transze + fizyka dostaw), `GM_CONTRACT_SETTLE` (wymuś rozliczenie dostępnej transzy — idempotentne), `GM_CONTRACT_CANCEL` (reason; kaucja/kary per kanon §13.1) · **R:** admin.
**Oferty B2B** (§13.2): **K** id, strony (linki), towar, wolumen, cena, stan (pełna maszyna §13.2), escrow bbl+PLN, wiek od `accepted` vs limit 168h (pasek postępu!) · **F** stan, wiek >120h (ryzyko autorozstrzygnięcia), strony · **A** View (timeline stanów + `state_version`), podgląd `resolved_split` (`paid+refunded+lost=wolumen`) · read-only (maszyna stanów samobieżna; interwencja tylko przez Spory). **R:** wszyscy.
**Transze i escrow**: **K** transza nr, stan, `locked_bbl`, `locked_pln`, ETA, reklamacja (T/F), freeze do · **F** freeze aktywne, >160h · **A** View; brak edit (zasada #5 §13.2: zegary kanoniczne). **R:** wszyscy.
**Spory B2B** (kolejka rozstrzygająca): **K** id, oferta, strony, wiek freeze (≤72h), SLA · **A** **Rozstrzygnij** — formularz werdyktu: dowody (timeline, `oil_movements`, trips, jakość partii, `black_market_origin`), opcje: całość→kupujący / całość→sprzedający / split (suwaki bbl + PLN, suma = stan escrow — walidacja) / 50-50; wykonanie = `B2B_GM_AWARD` (wyłącznie przeniesienie ISTNIEJĄCYCH środków z escrow — nie tworzy pieniędzy; reason wymagany od obu ról) · po limicie 168h werdykt wykonywany poza escrow (zasada #5). **R:** support rozstrzyga, admin przegląda/uchyla.
**Kartele** (§26.3): **K** nazwa, członkowie (2–5), pakt nieagresji, limit 40% produkcji, zwolnienie z prowizji (1 500 bbl/dobę/parę, kołnierz 0,8–1,2× spot), flagi 7d (3 = sankcja), sankcje historia · **A** View (telemetria obrotu wewnątrz), `GM_CARTEL_SUSPEND` (reason). **R:** admin.
**Kaucje i przepady**: rejestr `CONTRACT_DEPOSIT*` — read-only + linki. **R:** wszyscy.

### Grupa 6. FINANSE

**Księga FTS**: read-only — **K** id, ts, `reason_code` (rejestr z manifestów — tooltip „moduł/kod"), `from_account`→`to_account`, kwota, `idempotency_key` (hash + kopiuj), encja (resolver) · **F** reason_code, firma/konto, zakres, kwota min/max · test sumy zerowej (link do raportu §29.2) · korekty WYŁĄCZNIE kodami GM z parą zapisów (nigdy edycja wpisu). **R:** wszyscy.
**Konta**: (a) graczy: `PLN_CASH/PLN_BANK/PLN_ESCROW/GBBL` — saldo, ruch 24h; (b) systemowe `SYS_*` (TREASURY, MARKET_MAKER, INSURANCE_POOL, TAX, CAPEX_*...) — saldo, dzienny obrót · **A** View (historia FTS per konto), `GM_ACCOUNT_TRANSFER` (tylko para kont + reason + przeciwkonto jawne; zero kreacji pieniądza poza udokumentowanym `GM_COMPENSATE`) · **R:** admin.
**Zobowiązania** `obligations`: **K** źródło (budżet/kara/transza), kwota, termin 72h, status (`current/overdue/windykacja`) · **A** View, `GM_OBLIGATION_EXTEND` (reason), przekaż do egzekucji (kanonicznie automatyczne). **R:** support.
**Egzekucje komornicze** (§17.2): **K** firma, faza 1/2/3, dług, odzyskano, harmonogram · **A** View (fazy + zajęte wpływy 50%), `GM_BAILIFF_PAUSE` (reason), zatwierdź plan spłaty (wstrzymuje Fazę 2), `GM_AUCTION_RUN` (Faza 3: odwierty → rafinerie; wywoławcza 60% wyceny §19.1/§31.7; kupujący NPC-bank/gracz) · **R:** admin.
**Podatki**: **K** snapshot dzienny 00:00 UTC: `net_worth`, `tax_base` (zapasy 90% VWAP, aktywa 70% wyceny systemowej §19.1, wierzytelności 90%), `tier_base` (−`drip_30d` z księgi `gbbl_conversions`), podatek (marginalny 0/0,03/0,05/0,08%) · `asset_valuations` per aktywum (CAPEX×stan/100; depleted ×0,10; rafinerie §31.7) · **A** View rozbicia, `GM_SNAPSHOT_RECOMPUTE` (idempotentne, reason — naprawa awarii, nie korekta wyniku!) · read-only po materializacji. **R:** admin.
**Ubezpieczenia** (§31.6): pula: saldo vs 100M kapitału, wypłaty 24h (cap 50M/gracz), próg reasekuracji 20M (pożyczka Skarbu + składki +20%); polisy: stan (`active/claim_processing/lapsed/cancelled`), suma = wycena §31.7, składka dobowa, franszyza 15%, malus +50%; szkody: **A** Zatwierdź — forma pokazuje składowe `min(85%×szkoda, suma, wolny limit 50M, saldo puli)` i wyliczoną wypłatę (nie do nadpisania — tylko akceptacja), Odrzuć (reason → ścieżka odwoławcza gracza), `GM_INSURANCE_FRAUD_FLAG` (śledztwo) · **R:** admin; support: podgląd + flag.
**GBBL i Kroplówka** (§22.5/§24.3): zakupy (bramka, statusy, chargebacki), księga `gbbl_conversions` (źródło `drip_30d` — read-only), kurs Kroplówki (z Balansu) · **A** `GM_DRIP_BLOCK` (egzekucja/chargeback — kanon §21), refund chargeback (flow §22.5: dług GBBL + blokada zakupów) · **R:** admin.
**Tiery** (§22.6.1): snapshot: tier, `tier_base`, progi (półotwarte — 1 mld dokładnie = Tier IV), VIP cliff tylko dla nowych procesów · read-only + link do snapshotów. **R:** wszyscy.

### Grupa 7. TREŚCI

**Szablon strony / Nawigacja i nagłówek** (§18.2): edycja logo, nazwy, kolorów brandu (tokeny §1.1 — tylko z palety), struktury menu gracza; live-preview; publikacja = wersjonowany snapshot (rollback treści jak balans, ale bez symulatora — four-eyes wystarczy). **R:** support proponuje, admin publikuje.
**Pomoc admina / Instrukcja gracza**: Markdown + podgląd; instrukcja gracza trójjęzyczna (PL/EN/DE, fallback PL). **R:** support.
**Szablony dialogów** (§5.2): `employee_dialogue_templates` — edytor wariantów: klucz, kategorie, 3 wersje językowe obok siebie, fallback PL oznaczony; import/eksport CSV; walidacja kompletności tłumaczeń (braki = raport). **R:** support.
**Powiadomienia** (§27.2): szablony per kategoria (4), krytyczne = zawsze e-mail; deep-linki; test-wysyłka do siebie; deduplikacja parametry. Historia wysyłek i błędów jest trwała. **R:** support.
**Komunikaty GM do graczy** (odpowiednik legacy `gm_tools.php`): tytuł, treść, grupa odbiorców (jeden gracz / wszyscy / wybrany segment), kanał in-app oraz opcjonalny e-mail zgodny z kategorią i preferencjami §27.2. Przed wysyłką podgląd liczby odbiorców i treści; wysyłka w kolejce z idempotencją, raportem dostarczenia i wpisem `gm_action_log`. Wysłanej treści ani historii nie usuwa się po fakcie. **R:** admin; moderator może przygotować szkic i wysłać do jednego gracza w zakresie swoich uprawnień.
**Newsletter z TinyMCE** (odpowiednik legacy `newsletter.php`): edytor treści PL/EN/DE z zatwierdzoną konfiguracją TinyMCE, temat, podgląd e-maila, test-wysyłka do własnego adresu administratora, liczba uprawnionych odbiorców, planowanie i uruchomienie kampanii, statusy `draft/queued/sending/sent/partial/failed/cancelled`, historia kampanii i per-odbiorca. Produkcyjna wysyłka trafia tylko do zweryfikowanych adresów z aktywną zgodą na newsletter; każda wiadomość zawiera działający link rezygnacji. HTML z edytora przechodzi serwerową allowlistę i sanitizację; skrypty, niezatwierdzone osadzenia i style inline są odrzucane. TinyMCE jest zasobem aplikacji budowanym przez Vite, bez klucza ani skryptu z CDN w widoku. Wysyłka odbywa się partiami w kolejce, z deduplikacją i limitami; zapis kampanii oraz audyt są nieusuwalne z panelu. **R:** moderator przygotowuje szkic i test; admin publikuje kampanię; owner_admin nie musi podawać komentarza.
**Osiągnięcia** (§27.3): katalog + warunki; `GM_ACHIEVEMENT_GRANT/REVOKE` (reason; bez przewag liczbowych — kanon) · **R:** support.
**CMS strony publicznej**: strony/blokady HTML z sanityzacją (§16 — purifier, podgląd diff surowy vs oczyszczony). **R:** support proponuje, admin publikuje.

### Grupa 8. ŚWIAT

**Wydarzenia światowe** (§25/§25.1): kalendarz + lista; **E** (event GM): typ (EnumSelect 6), scope (global/region), magnitude, czas trwania h (widełki z tabeli §25.1), zapowiedź (0h dozwolone — przywilej GM §25), walidacja clamp [0,25; 2,20] z podglądem iloczynu z AKTYWNYMI wydarzeniami, wyłączność kryzysu egzekwowana w formie · **A** `GM_EVENT_TRIGGER`, `GM_EVENT_END` (wcześniejsze), `GM_EVENT_EXTEND` · wszystkie → log + wpis w historię notowań. **R:** admin.
**Regiony i Frontier Basins** (§28): regiony (nasycenie, chłonność NPC, aktywa), harmonogram otwarć frontier (mnożnik 0,75× §19.4); parametry przez Balans. **R:** view wszyscy.
**Działki rafineryjne + aukcje** (§31.2.1): 2–3/region (anty-monopol), aukcja angielska 24h, wadium 10%, licytujący · **A** View, `GM_AUCTION_CANCEL` (reason + zwrot wadiów), `GM_AUCTION_SETTLE` (gdy system nie rozliczył) · **R:** admin.
**RARS**: rezerwy strategiczne — wolumen, skup +20% (jakość ≥90), dostawy, limity; read-only + raporty. **R:** view.

### Grupa 9. RAFINERIA / EPC

**Rafinerie**: **K** id, firma, klasa I/II/III, status (pełny enum §31.2: planning/building 1–4/commissioning/operational/idle_standby/building_paused_funds/emergency_shutdown/abandoned — kanon badge), przepustowość (120/400/1000 bbl/h), wykorzystanie %, zbiorniki crude (6k/20k/50k) i produkty (6k/16k/40k per produkt) DECIMAL, wariant (A destylacja/B kraking/C hydrooczyszczanie — klasa I tylko A §31.2–§31.3), jednostki (`distillation_column/catalytic_cracker/hydrotreater`; reformer/coker badge „v2"), polisa (link), `epc_contract_id` (link), działka (link), wear, PI firmy (link §11.2) · **F** klasa, status, firma, region, wariant · **A** View (schemat instalacji: jednostki→strumienie→zbiorniki, czyta stany; P&L na żywo z marżami §31.5), `GM_REFINERY_FORCE_STANDBY`/wznowienie (wyłącznie legalne przejścia — EnumSelect; wznowienie po strajku z deterministycznym rzutem szok-termiczny, seed zapisany), link polisy/szkody · **R:** view wszyscy; akcje admin.
**Budowy i transze**: **K** rafineria, etap 1–4 (10/45/35/10% czasu; 5/45/40/10% CAPEX), wykonawca (link), transza (kwota, status, `building_paused_funds` wiek), `stage_ends_at`, incydent budowy (max 1: kara 4% CAPEX + wydłużenie 25%) · **A** View, `GM_BUILD_UNPAUSE` (gdy środki są, a system zablokował — reason), `GM_BUILD_EXTEND` (reason) · **R:** admin.
**Wykonawcy EPC** (§31.2.3): katalog 24 firm (6 archetypów ×4): **K** firma, archetyp A–F, `capex_mult` [0,60–1,40], `time_mult`, `start_integrity` ≥70%, `latent_defect_risk` ≤50%, `warranty_days`, `min_credibility`, publiczna rzetelność 0–100 · parametry UKRYTE tylko przez Balans (kontrakt = snapshot + checksum — edycja w panelu NIE zmienia podpisanych kontraktów) · **A** View (historia kontraktów, katastrofy 1/budowa), podgląd rzetelności ukrytej (audyt) · **R:** view admin; edycja = Balans (four-eyes + symulator).
**Produkty i jakość**: partie: **K** produkt (benzyna/diesel/jet/mazut/LPG), wolumen, `quality_score` 0–100 (progi 70/85/92; <70 substandard −40%/czarny rynek), przeznaczenie (spot/NPC/B2B/RARS/aviation/franczyza ×1,30 limit 3 000 bbl/dobę), flaga `black_market_origin` · read-only (jakość deterministyczna — podgląd składowych: technologia, katalizator, `skill_refining`). **R:** wszyscy.
**Księga `product_movements`**: read-only jak `oil_movements` + flara per frakcja (LPG→gazy→mazut §31.3). **R:** wszyscy.

### Grupa 10. BEZPIECZEŃSTWO

**Sabotaże i śledztwa** (§7): **K** operacja, typ (road_ambush do 30% / hub_valve / …), cel (resolver), koszt (15–60k), ryzyko roll (seed), wynik, śledztwo (agresor zidentyfikowany?), skutki (kanon §11A.7 — wyłącznie ładunek w tranzycie, NIGDY magazyn) · **A** View (seed + replay rzutu), `GM_SABOTAGE_CANCEL` (zwrot per kanon), priorytet śledztwa · **R:** admin; support view.
**Ochrona**: aktywne usługi (eskorta 2% min 2 500 / konwój 5% min 8 000 + dyr. prawny ≥3 / drony 35k/7dni max 10 / hub 60k/7dni), wygasania, modyfikatory BHP −10%/+15% · read-only (zakup nie działa wstecz — kanon §7.2). **R:** view.
**Sesje i urządzenia**: aktywne sesje, `trusted_devices`, status 2FA, throttle (5/60s, 10/15min), miękki limit IP 50/h (pełna blokada IP niestosowana — NAT §14) · **A** `GM_SESSION_LOGOUT` (wszystkie/wybrane), `GM_DEVICE_REVOKE`, reset 2FA · **R:** support.
**Bany i odwołania** (§30): **K** gracz, typ, długość, powód, paragraf ToS, odwołanie (kolejka) · **A** View, `GM_UNBAN`, skrócenie, rozpatrz odwołanie (szablon + własna treść) · **R:** support do 30 dni; admin bez limitu.
**Flagi anty-cheat** (§22.5–22.6): typ flagi, score, dowody (linki telemetry), status · **A** `GM_FLAG_DISMISS` (reason), eskalacja do bana (z dowodami) · **R:** admin.
**Czat — moderacja** (§26): zgłoszenia, kanały (global/regional/DM/firmowy/kartel), muty · **A** `GM_CHAT_MUTE` (długość warianty: 1h/24h/7dni/trwały), usuń wiadomość (zostaje w logu), `GM_CHANNEL_CLOSE` · **R:** support.

### Grupa 11. ADMINISTRACJA

**Hasła i odzyskiwanie dostępu**: administrator może sam zmienić hasło po ponownym uwierzytelnieniu; ekran „Nie pamiętam hasła” wysyła jednorazowy link ważny 60 minut bez ujawniania, czy adres istnieje. Po użyciu token wygasa, pozostałe sesje oraz zaufane urządzenia są unieważniane, a właściciel konta dostaje powiadomienie. W widoku gracza uprawniony administrator może jedynie zainicjować wysłanie graczowi linku resetu (§14.3); panel nigdy nie pokazuje hasła ani tokenu i nie ustawia hasła za gracza. Reset 2FA to odrębna, audytowana procedura weryfikacji tożsamości. **R:** self-service dla każdej roli; inicjowanie linku gracza: admin i moderator z uprawnieniem `player.password_reset.request`.
**Role i uprawnienia GM**: konta panelu (zaproszenie, 2FA, blokada, rola) i macierz capability × rola (z manifestów §4.1). Tylko `owner_admin` nadaje/odbiera role `admin` i `gm_support`; nie można nadać `owner_admin`. Zmiana = wersjonowany snapshot i `gm_action_log`. **R:** owner_admin; administratorzy przeglądają.
**Dziennik akcji GM**: read-only append-only — **K** ts, aktor, rola, kod, cel (resolver), przed→po (`delta`), reason, IP/sesja · **F** aktor, kod, cel, zakres · eksport CSV; retencja trwała. **R:** wszyscy (support bez eksportu pełnego — zakres własnych akcji + publiczne).
**Wersje balansu**: pełny flow §29.7 (drzewo modułów, kandydatury, diff, zatwierdzenie z wyjątkiem jedynego właściciela, symulator, emergency, rollback). **R:** wg §29.7.
**Moduły i manifesty** (§29.6): **K** key, etykieta, `enabled`, `premium`, `params_version`, liczba zasobów/widgetów/zakładek, wynik walidacji schematu · **A** enable/disable (four-eyes + symulator; zero-wipe passthrough §4.4), toggle premium (four-eyes), re-register (cache), podgląd manifestu YAML · **R:** admin.
**Backupy i eksporty** (§29): harmonogram, ostatni backup + weryfikacja sum, eksporty zadań kolejkowych · **A** uruchom teraz, weryfikuj, **PRZYWRÓĆ** (2× admin: inicjator + potwierdzający, DangerZone + typed-confirm + reason; snapshot stanu przed) · **R:** admin.
**Raporty symulatora**: per `balance_version` — PASS/FAIL lista, hash YAML, czas; ponowne uruchomienie na kandydaturze (bramka §29.5). **R:** view wszyscy; run admin+support.
**RODO** (§16): kolejka anonimizacji (`GM_ANONYMIZE` — firma → `dormant` → licytacja §19.4, dane osobowe anonimizowane, `RESTRICT` blokuje kaskady), rejestr retencji (3 lata zgody, 12 mies. logi IP, 30 dni czat, FTS trwale — obowiązek podatkowy), eksport danych gracza (wniosek → paczka JSON/CSV) · **R:** admin.
**Ustawienia panelu**: kolejność widgetów, UTC/lokalny, paginacja, limity throttle, baner konserwacji (`GM_MAINTENANCE` — komunikat dla graczy + tryb tylko-panel) · **R:** admin.

---

## 6. Katalog akcji GM (kody rejestrowane w manifestach)

Każda akcja: kod (prefix `GM_` poza FTS-owymi), cel, role, reason (W = wymagany dla ról innych niż owner_admin; S = wymagany dla `gm_support`; owner_admin ma wyjątek §0.4), cap (ograniczenie nadużyć), skutek księgowy (para FTS z jawnym przeciwkontem albo brak — „niepieniężna").

| Kod | Cel | Role | Reason | Cap / warunki | Księgowanie |
|---|---|---|---|---|---|
| `GM_CRED_ADJUST` | firma | admin ±20; support ±10 | W/S | 1×/firmę/24h (anti-spam) | niepieniężna |
| `GM_GBBL_ADJUST` | gracz | admin | W | księga GBBL (§22.5) | niepieniężna |
| `GM_COMPENSATE` | firma | admin (support do 100k) | W | para FTS | `SYS_TREASURY → PLN_BANK` |
| `GM_SHIELD_GRANT/REVOKE` | firma | admin | W/S | warunki §19.2 | niepieniężna |
| `GM_MEDIATION_FORCE` | spór prac. | admin+support | S | §19.2 | koszt mediatora per kanon |
| `GM_BAN` / `GM_UNBAN` | gracz | support ≤30 dni; admin bez | W | ToS §30 | niepieniężna |
| `GM_ANONYMIZE` | gracz | admin (2×) | W | DangerZone; §16/§19.4 | niepieniężna |
| `GM_LOAN_RESTRUCTURE` | pożyczka | admin; support z cap | W/S | widełki §17.1 | niepieniężna (nowe warunki) |
| `GM_LOAN_FORGIVE` | pożyczka | admin | W | — | `SYS_TREASURY → loans` |
| `GM_OFFER_CANCEL` | oferta spot/NPC | admin+support | S | zwrot rezerwacji | niepieniężna |
| `GM_TRIP_FORCE_DELIVER` / `GM_VOYAGE_*` | kurs/rejs | admin | W | idempotentne; fizyczna dostępność | per kanon dostawy |
| `GM_INCIDENT_RESOLVE` | incydent | admin+support | W/S | wariant kosztu (firma/pula/Skarb) | para FTS wg wariantu |
| `GM_WELL_REPAIR` / `GM_SEGMENT_REPAIR` / `GM_VEHICLE_REPAIR` / `GM_HUB_RELIEVE` | aktywo | support | S | koszt firmy lub Skarb (wariant) | para FTS wg wariantu |
| `GM_WELL_DEPLETE` | odwiert | admin | W | DangerZone | niepieniężna |
| `GM_BMS_ADJUST` | firma | admin | W | ±; decay §2.3 dalej biegnie | niepieniężna |
| `GM_DISPUTE_AWARD` (= `B2B_GM_AWARD`) | spór | support (admin review) | W | wyłącznie środki z escrow; split suma=stan | przeniesienie escrow |
| `GM_CONTRACT_SETTLE/CANCEL` | kontrakt NPC | admin | W | idempotentne; kary per kanon | para FTS |
| `GM_CARTEL_SUSPEND` | kartel | admin | W | §26.3 | niepieniężna |
| `GM_EVENT_TRIGGER/END/EXTEND` | wydarzenie | admin | W | clamp + wyłączność kryzysu (§25.1) | niepieniężna |
| `GM_AUCTION_CANCEL/SETTLE/RUN` | aukcja/egzekucja | admin | W | wadia zwracane; 60% wyceny | para FTS |
| `GM_SNAPSHOT_RECOMPUTE` | snapshot | admin | W | idempotentne; tylko awaria | niepieniężna |
| `GM_SWEEP_FORCE` | system | admin | W | budżet 60s; CAS | niepieniężna |
| `GM_GENERATOR_PAUSE` | region/global | admin | S | §13.1a | niepieniężna |
| `GM_MAINTENANCE` | system | admin | W | banner + tryb panel-only | niepieniężna |
| `GM_SESSION_LOGOUT` / `GM_DEVICE_REVOKE` / `GM_CHAT_MUTE` / `GM_FLAG_DISMISS` | sesja/czat/flaga | support | S | długości: mute 1h/24h/7d/trwały | niepieniężna |
| `GM_ACHIEVEMENT_GRANT/REVOKE` | gracz | support | S | bez przewag liczbowych | niepieniężna |
| `GM_REFINERY_FORCE_STANDBY` | rafineria | admin | W | tylko legalne przejścia §31.2 | niepieniężna |
| `GM_BUILD_UNPAUSE/EXTEND` | budowa | admin | W | — | niepieniężna |
| `GM_TASK_CANCEL/FINISH` | TTS | support | S | zwrot materiałów przy cancel | niepieniężna |
| `GM_ACCOUNT_TRANSFER` | konta | admin | W | para jawna; zero kreacji | para FTS |
| `GM_OBLIGATION_EXTEND` | zobowiązanie | support | S | max +72h | niepieniężna |
| `GM_BAILIFF_PAUSE` | egzekucja | admin | W | — | niepieniężna |
| `GM_DRIP_BLOCK` | GBBL | admin | W | kanon §21/§24.3 | niepieniężna |
| `GM_INSURANCE_FRAUD_FLAG` | szkoda | admin+support | S | śledztwo | niepieniężna |
| `GM_PERMIT_EXTEND/REVOKE` | koncesja | admin | W | §7.3 | niepieniężna |
| `GM_CERT_REVOKE` | certyfikat | admin | W | — | niepieniężna |
| `GM_COMPANY_RENAME` | firma | admin+support | W | powiadomienie gracza | niepieniężna |
| `GM_STRIKE_BREAK` | strajk | admin | W | DangerZone; §5 | niepieniężna |
| `GM_BALANCE_PROPOSE/APPROVE/REJECT/ACTIVATE/ROLLBACK/EMERGENCY` | balans | §29.7 | W | four-eyes + symulator | niepieniężna |
| `GM_PLAYER_PASSWORD_RESET_LINK` | gracz | admin; moderator z capability | S | jednorazowy link 60 min; bez ujawnienia tokenu | niepieniężna |
| `GM_BROADCAST_SEND` | gracze | admin; moderator tylko jeden gracz | S | podgląd odbiorców, kolejka, idempotencja | niepieniężna |
| `GM_NEWSLETTER_PUBLISH` | kampania | admin | S | zgoda i weryfikacja e-maila; test i podgląd | niepieniężna |

Zasady wspólne: wykonanie = jedna transakcja domenowa + `gm_action_log` + (dla pieniężnych) para FTS z `reason_code=GM_*` i `idempotency_key` (§29.4a); akcje niedostępne = ukryte w UI ORAZ 403 na serwerze (test P13); cap-y egzekwowane serwerowo, nie w formularzu.

---

## 7. Macierz uprawnień (klasy działań × role)

| Klasa działań | `admin` | `gm_support` | `gm_readonly` |
|---|---|---|---|
| Podgląd wszystkich zasobów + eksport | ✅ | ✅ | ✅ (eksport: tylko własne raporty) |
| Akcje operacyjne (naprawy, mute, logout, task, obligation extend, dispute resolve) | ✅ | ✅ (z cap i reason) | ❌ |
| Korekty reputacji/score (±cap) | ✅ ±20 | ✅ ±10 | ❌ |
| Pieniądze (`GM_COMPENSATE`, forgive, transfer, auction settle) | ✅ | tylko compensate ≤100k | ❌ |
| Bany | bez limitu | ≤30 dni | ❌ |
| Wydarzenia GM, generator, sweep, maintenance | ✅ | ❌ | ❌ |
| Balans: proponowanie | ✅ | ✅ | ❌ |
| Balans: zatwierdzanie / emergency / rollback | ✅ (four-eyes: ≠ autor) | ❌ | ❌ |
| Moduły enable/disable, premium | ✅ (four-eyes) | ❌ | ❌ |
| Anonimizacja RODO, przywracanie backupu | ✅ (2× admin) | ❌ | ❌ |
| Treści/CMS/publikacja brandu | publikuje | proponuje | ❌ |
| Reset hasła gracza / komunikaty GM | ✅ | tylko z capability i w limicie jednego gracza | ❌ |
| Newsletter: szkic/test / publikacja | ✅ / ✅ | ✅ / ❌ | ❌ |

---

## 8. Testy akceptacyjne panelu (P1–P22)

* **(P1) Drop-in modułu:** rejestracja testowego modułu „Dummy" (manifest §4.1) → bez zmian rdzenia pojawiają się: grupa nawigacji, zasób z tabelą, zakładka 360°, widget pulpitu, akcja GM w Actions ▾.
* **(P2) Zero-wipe disable:** `enabled=false` → zasoby read-only z badge, dane nienaruszone, akcje GM i widgety ukryte.
* **(P3) Re-enable:** dane i historia zachowane; stan po upływie czasu wynika z legalnych rozliczeń/dormant, nie z odtworzenia starego salda. W draining trwające procesy nadal się kończą.
* **(P4) Four-eyes serwerowe:** gdy istnieje drugi aktywny administrator z 2FA, autor kandydatury otrzymuje 403 przy próbie jej zatwierdzenia (także owner_admin).
* **(P5) Bramka symulatora:** kandydatura z wartością poza kotwicą → auto `rejected` + raport w `simulator_report_json`.
* **(P6) Emergency:** aktywacja pomija symulator, NIE pomija four-eyes; przy jednym owner_admin zwraca 403; baner na dashboardzie do następnej zwykłej wersji.
* **(P7) Reason:** każda mutacja `gm_support` bez reason (min 10 znaków) → 422; bez reason nie powstaje wpis.
* **(P8) Ślad:** każda akcja GM → `gm_action_log` z przed→po; brak akcji bez wpisu (property test po wszystkich kodach §6).
* **(P9) DangerZone:** anonimizacja i restore backupu wymagają typed-confirm; restore wymaga drugiego admina.
* **(P10) Kanon badge:** snapshot-test mapy status→kolor (§1.3) — nowy status bez przypisania = czerwony test.
* **(P11) Formattery:** property test `pln(grosze)` — brak float drift na losowych BIGINT (porównanie z BCMath).
* **(P12) Pokrycie resolverów:** automatyczny przegląd wszystkich kolumn FK w zasobach → każda ma deep-link albo jawne wyłączenie.
* **(P13) `gm_readonly` zero mutacji:** przegląd tras panelu — brak POST/PUT/DELETE dostępnych dla roli (test HTTP 403).
* **(P14) Walidacja manifestów:** zduplikowany slug zasobu/kod GM/schemat poza własnym namespace → błąd startu panelu z czytelnym komunikatem.
* **(P15) Wydajność pulpitu:** dashboard < 2s przy 1 000 graczy — wyłącznie agregaty i tabele snapshotów, explain bez full-scan na FTS.
* **(P16) Komunikaty aplikacji:** sukces/ostrzeżenie/błąd/informacja/potwierdzenie, także 403/422/500 i DangerZone, pokazują właściwe lokalne SVG, tekst PL/EN/DE zgodny z locale administratora i dostępny dialog lub toast; brak `window.alert/confirm/prompt`; klawiatura, czytnik ekranu i szerokość 320 CSS px działają.
* **(P17) Zasoby i kodowanie:** każda ikona panelu jest lokalnym SVG bez bitmap/skryptów; CSS panelu i modułów jest rozdzielony według §8.0a; nowe komentarze kodu mają pary PL bez diakrytyków + EN, a pliki są UTF-8 bez BOM.
* **(P18) Pełne pokrycie balansu:** każdy liść `modules.*.params` z YAML ma edytor i schemat; brak schematu blokuje publikację; zmiana listy/receptury przechodzi przez diff, walidację, symulator liczony z kandydujących wartości i nową wersję, bez wpływu na podpisane kontrakty; poprawna zmiana ceny nie pada na sztywnej kotwicy v1.
* **(P19) Jeden właściciel i konta panelu:** tylko owner_admin nadaje role; drugi owner i usunięcie właściciela są odrzucone. Sam właściciel aktywuje zwykły balans z 2FA, typed-confirm i 0 FAIL, bez wymaganego powodu i komentarza; tryb single_owner jest audytowany. Po dodaniu drugiego admina własne zatwierdzenie właściciela daje 403; emergency przy jednym adminie daje 403.
* **(P20) Reset hasła:** link administratora i gracza wygasa po 60 minutach, jest jednorazowy, odpowiedź nie ujawnia istnienia adresu; po użyciu sesje i zaufane urządzenia są unieważniane. Akcja GM nie odsłania tokenu ani nie ustawia hasła gracza; reset 2FA ma osobny audyt.
* **(P21) Komunikaty GM:** wysyłka do jednej osoby, wszystkich i segmentu ma podgląd odbiorców, idempotencję, kolejkę, raport dostarczenia oraz nieusuwalną historię; serwer egzekwuje rolę, kategorię i preferencje.
* **(P22) Newsletter TinyMCE:** szkic PL/EN/DE, podgląd każdej wersji i test do administratora, sanitizacja HTML, wysyłka partiami tylko do zweryfikowanych subskrybentów, rezygnacja i historia per odbiorca; ponowienie zadania nie dubluje e-maila, moderator nie może sam opublikować kampanii.

---

## 9. Dziennik zmian specyfikacji panelu

* v3 (2026-11-15): pełna lokalizacja PL/EN/DE także dla panelu, powiadomień i newslettera.
* v2 (2026-11-15): parytet legacy — reset hasła, komunikaty GM i newsletter TinyMCE; akcje i testy P20–P22.

* v1 (2026-11-14): pierwsza pełna wersja — warstwa wizualna i szablony (§1), pulpit (§2), nawigacja parity+moduły (§3), kontrakt manifestu panelowego i łączenie modułów (§4), katalog ~45 zasobów z kolumnami/filtrami/akcjami/formularzami (§5), 40+ kodów akcji GM z capami (§6), macierz uprawnień (§7), testy P1–P15 (§8).
