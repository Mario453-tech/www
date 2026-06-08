# Dział prawny P1 — status wdrożenia

> Porównanie kodu z briefem: `svn_repo/BRIEF DLA AI — Dział prawny P1: zezwolenia na wiercenie i mapa.pdf` (19 sekcji).
> Legenda: ✅ zrobione · ⚠️ częściowe / odstępstwo · ⏳ do zrobienia · 🚫 TODO świadomie odłożone (poza zakresem P1).

Data analizy: 2026-06-04

---

## 1. Architektura wdrożenia

| Warstwa | Plik |
|---|---|
| Logika / dane | `src/LegalService.php` (schema, seed, submit, migracja, gettery, `getMapPermitData`, `notifyDirector`) |
| Tick | `src/Tick/LegalSection.php` → podpięty w `cron/tick.php` (sekcja 7) |
| Bramka mapy | `src/WorldMap.php` (`regionPurchaseBlock`, `getMapData` — batch `getMapPermitData`) |
| Widok gracza | `public/legal.php` + `templates/views/legal/main.php` |
| Panel admina | `admin/legal.php` + `templates/views/admin/legal/main.php` |
| Mapa frontend | `assets/js/world_map.js` (`fmtMinutes`, `permitBadge`, `buildPermitHtml` — 6 wariantów modalnych) |
| Style | `assets/css/legal.css`, `assets/css/map.css` (klasy per status) |
| Tłumaczenia | `lang/pl/legal.php`, `lang/pl/admin/legal.php`, `lang/pl/map.php` (`map_js.permit_*`) |
| Powiadomienia | `director_notifications` — `notifyDirector()` (try/catch guard) |
| Testy | `tests/Integration/LegalMapPermitDataTest.php` (15 testów), `tests/Integration/LegalNotificationsTest.php` (6 testów) |

---

## 2. Zakres P1 (sekcja 17 briefu) — 15 punktów

- [x] **1. Status zezwoleń na regiony** — enum `pending/delayed/no_decision/granted/refused/transitional` ✅
- [x] **2. Panel działu prawnego z listą regionów** — 4 grupy: aktywne / w toku / dostępne / zablokowane kapitałowo ✅
- [x] **3. Składanie wniosku** — `submitApplication()` + formularz POST ✅
- [x] **4. Koszt wniosku** — `application_cost`, pokazany graczowi ✅
- [x] **5. Czas rozpatrzenia w minutach** — `minutesToHuman()` → „X min" / „X h", bez ticków ✅
- [x] **6. Tick rozpatrujący wnioski** — `LegalSection::run()` raz/tick globalnie ✅
- [x] **7. Opóźnienie decyzji** — `applyDelay` + `delay_count`, nowy termin ✅
- [x] **8. Brak decyzji** — `applyNoDecision`, wniosek w zawieszeniu ✅
- [x] **9. Odmowa** — `applyRefusal` + `refusal_cooldown_until` ✅
- [x] **10. Zezwolenie aktywne** — `granted`, `ACTIVE_STATUSES` ✅
- [x] **11. Blokada zakupu bez zezwolenia** — bramka w `buyWellAtLocation` + fail-closed ✅
- [x] **12. Modale na mapie** — 6 wariantów per status: active / pending / delayed / no_decision / refused / locked ✅
- [x] **13. Zezwolenia przejściowe (migracja)** — `migrateTransitionalPermits()` + przycisk admina ✅
- [x] **14. Panel admina do konfiguracji** — regiony + wnioski + decyzje ręczne ✅
- [x] **15. Powiadomienia jak w dziale technicznym** — `director_notifications` type=`legal` ✅

---

## 3. Zgodność z zasadami briefu

- [x] Priorytet losowania ticka `no_decision > refusal > delay > granted` (sekcja 10.3) ✅
- [x] Nazewnictwo „ryzyko regionu" / `risk_level`, NIE „ryzyko polityczne" (sekcja 3, 10.2) ✅
- [x] Gracz nie widzi procentów ryzyka — tylko poziom słownie, koszt, czas (sekcja 8) ✅
- [x] Poziomy ryzyka regionu: low / medium / high / critical (sekcja 7.1) ✅
- [x] Wymóg kapitałowy bramkuje regiony wysokiego ryzyka (sekcja 7.2, `required_capital`) ✅
- [x] Fail-closed: przy błędzie bramki zakup zablokowany (zasada nadrzędna) ✅
- [x] Auto-seed configu przy pierwszym uruchomieniu (gracze nie utkną) ✅
- [x] Decyzje ręczne admina: grant / transitional / no_decision / refuse / reset (sekcja 16.2) ✅

---

## 4. Do zrobienia / odstępstwa względem briefu

### 4.1. ✅ Modale mapy — WDROŻONE (04.06.2026)
- [x] `LegalService::getMapPermitData()` zwraca pełny status per region (2 SQL queries, brak N+1).
- [x] `WorldMap::getMapData()` korzysta z batch-requestu — przekazuje `permit_status`,
      `permit_minutes_left`, `permit_cooldown_minutes`, `permit_required_capital` per region.
- [x] `buildPermitHtml(ps, r)` w `world_map.js` rozgałęzia 6 wariantów modalnych:
      `active` / `pending` / `delayed` / `no_decision` / `refused` / `locked`.
- [x] `permitBadge(ps)` renderuje kolorowy badge per status w liście lokacji.
- [x] Klucze i18n: `map_js.permit_*` w `lang/pl/map.php`.
- [x] CSS: `.loc-badge--permit-*`, `.sr-permit--active`, `.loc-permit-required--*` w `map.css`.

### 4.2. ✅ Modal „Region zablokowany kapitałowo" na mapie — WDROŻONE (04.06.2026)
- [x] `getMapPermitData()` zwraca status `locked` gdy `required_capital > playerCash`.
- [x] `buildPermitHtml()` pokazuje dedykowany modal z wymaganym kapitałem i brakującą kwotą.
- [x] Widoczne na mapie zanim gracz spróbuje złożyć wniosek (§7.3).

### 4.3. ✅ `confirm()` natywny → wspólny modal (sekcja 14) — WDROŻONE
- [x] Formularz składania wniosku używa teraz `data-confirm` / `data-confirm-label`
      obsługiwanych przez `modal.js` — spójne z resztą gry.

### 4.4. ⏳ `required_legal_level` — placeholder
- [ ] Kolumna istnieje w schemacie i seedzie, ale nie jest używana: panel admina jej nie ustawia
      (`save_region_config` pomija pole), `submitApplication()` jej nie sprawdza.
- Zgodne z briefem („wymagany poziom działu prawnego, JEŚLI istnieje / zostanie wdrożony") —
      system poziomu działu jeszcze nie istnieje. Świadomy placeholder, do podpięcia przy P2.

---

## 5. 🚫 TODO świadomie odłożone (sekcja 18 — poza zakresem P1)

Poprawnie NIE wdrożone, zgodnie z briefem:

- [ ] 🚫 Zezwolenia infrastrukturalne (huby, rurociągi, transport morski) — 18.1
- [ ] 🚫 Zezwolenia warunkowe (limit odwiertów, krótszy czas ważności) — 18.2
- [ ] 🚫 Zezwolenia wygasłe (odnowienie) — 18.3
- [ ] 🚫 Zezwolenia cofnięte (blokada regionu) — 18.4
- [ ] 🚫 Kary i blokady prawne (po incydentach, sabotażu) — 18.5
- [ ] 🚫 Wiarygodność firmy (score jak bank / czarny rynek) — 18.6
- [ ] 🚫 Łapówki i nielegalne przyspieszanie decyzji — 18.7
- [ ] 🚫 Sprawy sądowe, ugody, umowy — 18.8

---

## 6. Podsumowanie

P1 **ukończony w całości** zgodnie z briefem. Wszystkie 15 punktów zakresu wdrożone,
w tym modale mapy (6 wariantów per status) i blokada kapitałowa widoczna na mapie.
Jedyny świadomy placeholder: `required_legal_level` (system poziomu działu nie istnieje w P1).
