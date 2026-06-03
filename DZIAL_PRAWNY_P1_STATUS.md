# Dział prawny P1 — status wdrożenia

> Porównanie kodu z briefem: `svn_repo/BRIEF DLA AI — Dział prawny P1: zezwolenia na wiercenie i mapa.pdf` (19 sekcji).
> Legenda: ✅ zrobione · ⚠️ częściowe / odstępstwo · ⏳ do zrobienia · 🚫 TODO świadomie odłożone (poza zakresem P1).

Data analizy: 2026-06-03

---

## 1. Architektura wdrożenia

| Warstwa | Plik |
|---|---|
| Logika / dane | `src/LegalService.php` (schema, seed, submit, migracja, gettery) |
| Tick | `src/Tick/LegalSection.php` → podpięty w `cron/tick.php` (sekcja 7) |
| Bramka mapy | `src/WorldMap.php` (`regionPurchaseBlock`, `getMapData.has_permit`) |
| Widok gracza | `public/legal.php` + `templates/views/legal/main.php` |
| Panel admina | `admin/legal.php` + `templates/views/admin/legal/main.php` |
| Mapa frontend | `assets/js/world_map.js` (modal „brak zezwolenia") |
| Style | `assets/css/legal.css` |
| Tłumaczenia | `lang/pl/legal.php`, `lang/pl/admin/legal.php` |

---

## 2. Zakres P1 (sekcja 17 briefu) — 15 punktów

- [x] **1. Status zezwoleń na regiony** — enum `pending/delayed/no_decision/granted/refused/transitional` ✅
- [x] **2. Panel działu prawnego z listą regionów** — 4 grupy: aktywne / w toku / dostępne / zablokowane ✅
- [x] **3. Składanie wniosku** — `submitApplication()` + formularz POST ✅
- [x] **4. Koszt wniosku** — `application_cost`, pokazany graczowi ✅
- [x] **5. Czas rozpatrzenia w minutach** — `minutesToHuman()` → „X min" / „X h", bez ticków ✅
- [x] **6. Tick rozpatrujący wnioski** — `LegalSection::run()` raz/tick globalnie ✅
- [x] **7. Opóźnienie decyzji** — `applyDelay` + `delay_count`, nowy termin ✅
- [x] **8. Brak decyzji** — `applyNoDecision`, wniosek w zawieszeniu ✅
- [x] **9. Odmowa** — `applyRefusal` + `refusal_cooldown_until` ✅
- [x] **10. Zezwolenie aktywne** — `granted`, `ACTIVE_STATUSES` ✅
- [x] **11. Blokada zakupu bez zezwolenia** — bramka w `buyWellAtLocation` + fail-closed ✅
- [ ] **12. Modale na mapie** — ⚠️ uproszczone (patrz sekcja 4.1)
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

### 4.1. ⚠️ Modale mapy uproszczone (sekcja 6.2–6.5, 14)
- [ ] Brief chce RÓŻNYCH modali na mapie dla stanów: brak zezwolenia / wniosek w trakcie /
      brak decyzji / odrzucony.
- Obecnie: mapa dostaje tylko `has_permit` (bool) → pokazuje JEDEN panel
      „Brak zezwolenia → Przejdź do działu prawnego" dla wszystkich stanów nieaktywnych.
- Rozróżnienie statusów istnieje, ale tylko na stronie `/legal`, nie na mapie.
- **Fix:** przekazać w `getMapData` pełny status per region i rozgałęzić modal w `world_map.js`.

### 4.2. ⚠️ Modal „Region wysokiego ryzyka" nie pojawia się na mapie (sekcja 7.3 / 14.2)
- [ ] Wymóg kapitałowy (`required_capital`) jest egzekwowany dopiero przy złożeniu wniosku
      na `/legal` (zwraca `region_locked`), a nie jako uprzedzający modal na mapie.
- **Fix:** pokazać modal z warunkami (wymagany kapitał) zanim gracz spróbuje złożyć wniosek.

### 4.3. ⚠️ `confirm()` natywny zamiast wspólnego systemu modali (sekcja 14)
- [ ] Szablon gracza używa `onclick="return confirm(...)"` przy składaniu wniosku.
- Komunikaty błędów/sukcesu już idą przez `alertError` / `alertInfo` / `showGameToast`.
- **Fix:** zamienić `confirm()` na wspólny modal potwierdzenia.

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

Rdzeń P1 (zezwolenia, wnioski, tick, bramka zakupu, migracja, panel admina, powiadomienia)
jest wdrożony zgodnie z briefem. Główne realne odstępstwo: **uproszczone modale na mapie**
(jeden ogólny zamiast 4 per status). Reszta to drobiazgi i świadome placeholdery zgodne
z duchem dokumentu.
