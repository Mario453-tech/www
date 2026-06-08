# Dział prawny — status wdrożenia (P1 + P2a)

> Legenda: ✅ zrobione · ⚠️ częściowe / odstępstwo · ⏳ do zrobienia · 🚫 TODO świadomie odłożone.

---

## Etapy

| Etap | Zakres | Data | Status |
|------|--------|------|--------|
| **P1** | Zezwolenia na wiercenie per region | 2026-06-04 | ✅ ukończony |
| **P2a** | Zezwolenia na huby logistyczne per region | 2026-06-08 | ✅ ukończony |
| P2b | Zezwolenia na rurociągi / transport morski | — | 🚫 poza zakresem |
| P3+ | Kary, cofnięcia, łapówki, sprawy sądowe | — | 🚫 poza zakresem |

---

## Architektura

| Warstwa | Pliki |
|---------|-------|
| **Logika / dane** | `src/LegalService.php` (fasada) + `src/Legal/HubPermitTrait.php` (P2a) |
| **Tick** | `src/Tick/LegalSection.php` → `cron/tick.php` (przetwarza wiercenia + huby) |
| **Bramka wiercenie** | `src/WorldMap.php` (`regionPurchaseBlock`, `getMapPermitData`) |
| **Bramka hub** | `src/HubAcquisitionService.php` (`buyNew`, `buyUsed`, `rent`) |
| **API hubów** | `src/HubApi.php` (error code `no_hub_permit` → komunikat gracza) |
| **Widok gracza** | `public/legal.php` + `templates/views/legal/main.php` |
| **Panel admina** | `admin/legal.php` + `templates/views/admin/legal/main.php` |
| **Mapa frontend** | `assets/js/world_map.js` (`buildPermitHtml`, `permitBadge`) |
| **Style** | `assets/css/legal.css`, `assets/css/map.css` |
| **Tłumaczenia** | `lang/pl/legal.php`, `lang/pl/admin/legal.php`, `lang/pl/map.php` |
| **Powiadomienia** | `director_notifications` type=`legal` |
| **Testy** | `tests/Integration/LegalMapPermitDataTest.php`, `LegalNotificationsTest.php` |

---

## P1 — Zezwolenia na wiercenie

### Zakres (15 punktów)

- [x] Status enum `pending/delayed/no_decision/granted/refused/transitional`
- [x] Panel gracza — 4 grupy: aktywne / w toku / dostępne / zablokowane kapitałowo
- [x] Składanie wniosku — `submitApplication()` + formularz POST
- [x] Koszt wniosku `application_cost`, pokazany graczowi
- [x] Czas rozpatrzenia `minutesToHuman()` — bez ticków, w minutach/godzinach
- [x] Tick `LegalSection::run()` — raz/tick globalnie
- [x] Opóźnienie decyzji — `applyDelay` + `delay_count`, nowy termin
- [x] Brak decyzji — `applyNoDecision`, wniosek w zawieszeniu
- [x] Odmowa — `applyRefusal` + `refusal_cooldown_until`
- [x] Zezwolenie aktywne — `granted`, `ACTIVE_STATUSES`
- [x] Bramka zakupu fail-closed — `WorldMap::regionPurchaseBlock()`
- [x] Modale mapy — 6 wariantów per status (active/pending/delayed/no_decision/refused/locked)
- [x] Zezwolenia przejściowe (migracja) — `migrateTransitionalPermits()` + przycisk admina
- [x] Panel admina — konfiguracja regionów + wnioski + decyzje ręczne
- [x] Powiadomienia dyrektora — `director_notifications` type=`legal`

### Zasady ogólne P1

- Priorytet ticka: `no_decision > refusal > delay > granted`
- Gracz nie widzi procentów ryzyka — tylko poziom słownie, koszt, czas
- Poziomy ryzyka: `low / medium / high / critical`
- Wymóg kapitałowy (`required_capital`) bramkuje regiony wysokiego ryzyka
- Fail-closed: błąd DB = zakup zablokowany
- Auto-seed configu przy pierwszym uruchomieniu
- Bramka **zawsze aktywna** dla każdego regionu z `enabled=1` — brak opt-in

---

## P2a — Zezwolenia na huby logistyczne

### Zakres

- [x] Tabela `hub_permit_applications` (schema identyczna z `drilling_permit_applications`, bez `transitional`)
- [x] Kolumny w `legal_region_config`: `hub_permit_enabled` (default=0), `hub_permit_cost`, `hub_review_minutes`
- [x] Tick `LegalSection::runHubPermits()` — ta sama logika losowania wyników co P1
- [x] Bramka fail-closed w `buyNew / buyUsed / rent` (helper `hasHubPermitOrNotRequired`)
- [x] API `HubApi.php` — error code `no_hub_permit` → czytelny komunikat gracza
- [x] Widok gracza — sekcja „Zezwolenia na huby" (4 grupy: aktywne/w toku/dostępne/cooldown)
- [x] Widok gracza — sekcja widoczna tylko gdy ≥1 region ma `hub_permit_enabled=1`
- [x] Panel admina — 3 nowe kolumny w tabeli regionów (hub wymagany / koszt / czas)
- [x] Panel admina — zakładka „Wnioski na huby" z akcjami: grant / no_decision / refuse / reset→pending
- [x] `seedHubPermitDefaults()` — ustawia koszty/czasy per poziom ryzyka przy akcji „Seeduj regiony"
- [x] Powiadomienia dyrektora — emoji per wynik (📝 submit, ✅ granted, ❌ refused, ⏳ delayed, ⚠️ no_decision)

### Różnice względem P1 (projektowe)

| Cecha | Wiercenie (P1) | Hub (P2a) |
|-------|---------------|-----------|
| Domyślny stan | Bramka zawsze aktywna dla `enabled=1` | Opt-in per region (`hub_permit_enabled=0` default) |
| Status `transitional` | Tak (migracja starych graczy) | Nie (gracze startują od zera) |
| Poziomy kosztów (low→critical) | 100k / 250k / 500k / 1M PLN | 200k / 500k / 1M / 2M PLN |
| Czas rozpatrzenia (low→critical) | 30 / 60 / 90 / 120 min | 60 / 120 / 180 / 240 min |
| Gdzie sprawdzana bramka | `WorldMap` (zakup odwiertu) | `HubAcquisitionService` (kupno/wynajem hubu) |

### Rollout

Po wdrożeniu `hub_permit_enabled=0` dla wszystkich regionów → **żaden zakup hubu nie jest blokowany** dopóki admin nie włączy wymagania per region w panelu admina. Istniejące huby nie są dotknięte (bramka tylko dla nowych zakupów).

Kolejność uruchomienia:
1. Wdrożyć kod (schema tworzy się automatycznie przy pierwszym żądaniu przez `ensureSchema`)
2. Admin: „Seeduj regiony" → uzupełnia domyślne koszty/czasy hubów per poziom ryzyka
3. Admin: włącza `hub_permit_enabled=1` per region kiedy chce wymagać zezwoleń
4. Gracze składają wnioski → tick rozpatruje → po `granted` mogą kupić hub

---

## Świadome placeholdery / TODO

- [ ] ⚠️ `required_legal_level` — kolumna w schemacie, ale nigdzie nie używana (system poziomu działu nie istnieje)
- [ ] 🚫 Zezwolenia infrastrukturalne (rurociągi, transport morski) — P2b
- [ ] 🚫 Zezwolenia warunkowe (limit, krótszy czas ważności) — P2+
- [ ] 🚫 Zezwolenia wygasłe (odnowienie) — P2+
- [ ] 🚫 Zezwolenia cofnięte (blokada regionu) — P3
- [ ] 🚫 Kary i blokady prawne (po incydentach) — P3
- [ ] 🚫 Wiarygodność firmy (score) — P3
- [ ] 🚫 Łapówki i nielegalne przyspieszanie decyzji — P3
- [ ] 🚫 Sprawy sądowe, ugody, umowy — P3
