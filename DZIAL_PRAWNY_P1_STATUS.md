# Dział prawny P1 — status wdrożenia

> Porównanie kodu z briefem: `svn_repo/BRIEF DLA AI — Dział prawny P1: zezwolenia na wiercenie i mapa.pdf` (19 sekcji).
> Legenda: [x] zrobione · [~] częściowe / odstępstwo · [ ] do zrobienia · [odłożone] TODO świadomie odłożone (poza zakresem P1).

Data analizy: 2026-06-04

---

## 1. Architektura wdrożenia

| Warstwa | Plik |
|---|---|
| Logika / dane | `src/LegalService.php` (schema, seed, submit, migracja, gettery) |
| Tick | `src/Tick/LegalSection.php` podpięty w `cron/tick.php` (sekcja 7) |
| Bramka mapy | `src/WorldMap.php` (`regionPurchaseBlock`, `getMapData.permit_status`) |
| Widok gracza | `public/legal.php` + `templates/views/legal/main.php` |
| Panel admina | `admin/legal.php` + `templates/views/admin/legal/main.php` |
| Mapa frontend | `assets/js/world_map.js` (modale statusów zezwolenia i blokad) |
| Style | `assets/css/legal.css` |
| Tłumaczenia | `lang/pl/legal.php`, `lang/pl/admin/legal.php` |

---

## 2. Zakres P1 (sekcja 17 briefu) — 15 punktów

- [x] **1. Status zezwoleń na regiony** — enum `pending/delayed/no_decision/granted/refused/transitional`
- [x] **2. Panel działu prawnego z listą regionów** — aktywne / w toku / dostępne / cooldown / kapitał / poziom prawny
- [x] **3. Składanie wniosku** — `submitApplication()` + formularz POST
- [x] **4. Koszt wniosku** — `application_cost`, pokazany graczowi
- [x] **5. Czas rozpatrzenia w minutach** — `minutesToHuman()` -> „X min" / „X h", bez ticków
- [x] **6. Tick rozpatrujący wnioski** — `LegalSection::run()` raz/tick globalnie
- [x] **7. Opóźnienie decyzji** — `applyDelay` + `delay_count`, nowy termin
- [x] **8. Brak decyzji** — `applyNoDecision`, wniosek w zawieszeniu
- [x] **9. Odmowa** — `applyRefusal` + `refusal_cooldown_until`
- [x] **10. Zezwolenie aktywne** — `granted`, `ACTIVE_STATUSES`
- [x] **11. Blokada zakupu bez zezwolenia** — bramka w `buyWellAtLocation` + fail-closed
- [x] **12. Modale na mapie** — osobne warianty dla `none/pending/delayed/no_decision/refused/locked/legal_locked`
- [x] **13. Zezwolenia przejściowe (migracja)** — `migrateTransitionalPermits()` + przycisk admina
- [x] **14. Panel admina do konfiguracji** — regiony + wnioski + decyzje ręczne + wymagany poziom prawny
- [x] **15. Powiadomienia jak w dziale technicznym** — `director_notifications` type=`legal`

---

## 3. Zgodność z zasadami briefu

- [x] Priorytet losowania ticka `no_decision > refusal > delay > granted` (sekcja 10.3)
- [x] Nazewnictwo „ryzyko regionu" / `risk_level`, NIE „ryzyko polityczne" (sekcja 3, 10.2)
- [x] Gracz nie widzi procentów ryzyka — tylko poziom słownie, koszt, czas (sekcja 8)
- [x] Poziomy ryzyka regionu: low / medium / high / critical (sekcja 7.1)
- [x] Wymóg kapitałowy bramkuje regiony wysokiego ryzyka (sekcja 7.2, `required_capital`)
- [x] Wymóg poziomu działu prawnego bramkuje trudniejsze regiony (`required_legal_level`)
- [x] Fail-closed: przy błędzie bramki zakup zablokowany (zasada nadrzędna)
- [x] Auto-seed configu przy pierwszym uruchomieniu (gracze nie utkną)
- [x] Decyzje ręczne admina: grant / transitional / no_decision / refuse / reset (sekcja 16.2)

---

## 4. Status domknięcia i P2

### 4.1. Modale mapy
- [x] Mapa dostaje pełne `permit_status` per region.
- [x] `world_map.js` rozróżnia: brak zezwolenia, wniosek w trakcie, brak decyzji,
      odmowę z cooldownem, blokadę kapitałową oraz blokadę poziomem działu prawnego.

### 4.2. `required_legal_level`
- [x] Panel admina zapisuje `required_legal_level`.
- [x] `submitApplication()` sprawdza poziom przed pobraniem opłaty.
- [x] `/legal` pokazuje osobną grupę regionów wymagających wyższego poziomu działu.
- [x] Mapa pokazuje osobny status `legal_locked`.
- [x] Poziom działu prawnego jest liczony z aktywnego dyrektora roli `legal`
      na podstawie `skill_organization`, `skill_analysis`, `skill_ethics`.

---

## 5. TODO świadomie odłożone (sekcja 18 — poza zakresem P1)

Poprawnie NIE wdrożone, zgodnie z briefem:

- [ ] [odłożone] Zezwolenia infrastrukturalne (huby, rurociągi, transport morski) — 18.1
- [ ] [odłożone] Zezwolenia warunkowe (limit odwiertów, krótszy czas ważności) — 18.2
- [ ] [odłożone] Zezwolenia wygasłe (odnowienie) — 18.3
- [ ] [odłożone] Zezwolenia cofnięte (blokada regionu) — 18.4
- [ ] [odłożone] Kary i blokady prawne (po incydentach, sabotażu) — 18.5
- [ ] [odłożone] Wiarygodność firmy (score jak bank / czarny rynek) — 18.6
- [ ] [odłożone] Łapówki i nielegalne przyspieszanie decyzji — 18.7
- [ ] [odłożone] Sprawy sądowe, ugody, umowy — 18.8

---

## 6. Podsumowanie

Rdzeń P1 (zezwolenia, wnioski, tick, bramka zakupu, migracja, panel admina, powiadomienia)
jest wdrożony zgodnie z briefem. Dodatkowo rozpoczęto P2 przez realne podpięcie
`required_legal_level` do admina, strony działu prawnego, mapy i walidacji wniosku.
