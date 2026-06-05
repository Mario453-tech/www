# Dział prawny P1 — status wdrożenia

> Porównanie kodu z briefem: `svn_repo/BRIEF DLA AI — Dział prawny P1: zezwolenia na wiercenie i mapa.pdf` (19 sekcji).
> Legenda: [x] zrobione · [~] częściowe / odstępstwo · [ ] do zrobienia · [odłożone] TODO świadomie odłożone (poza zakresem P1).

Data analizy: 2026-06-05

---

## 1. Architektura wdrożenia

| Warstwa | Plik |
|---|---|
| Logika / dane | `src/LegalService.php` (schema, seed, submit, migracja, gettery) |
| Tick prawa | `src/Tick/LegalSection.php` podpięty w `cron/tick.php` (sekcja 8) |
| Tick wiarygodności | `src/Tick/CredibilitySection.php` podpięty w `cron/tick.php` (sekcja 7) |
| Bramka mapy | `src/WorldMap.php` (`regionPurchaseBlock`, `getMapData.permit_status`) |
| Widok gracza | `public/legal.php` + `templates/views/legal/main.php` |
| Panel admina | `admin/legal.php` + `templates/views/admin/legal/main.php` |
| Mapa frontend | `assets/js/world_map.js` (modale statusów zezwolenia i blokad) |
| Wiarygodność firmy | `src/CompanyCredibilityService.php`, `admin/credibility.php`, `templates/components/company_credibility.php` |
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
- [x] **12. Modale na mapie** — osobne warianty dla `none/pending/delayed/no_decision/refused/locked/legal_locked/credibility_locked`
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
- [x] Wymóg wiarygodności firmy bramkuje regiony `high/critical` (`company_credibility >= 40`)
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

### 4.3. `18.6` Wiarygodność firmy — fundament i realny wpływ na dział prawny
- [x] Fundament wyniku `players.company_credibility` w skali 0-100.
- [x] Historia zmian w `company_credibility_log`.
- [x] Panel admina `admin/credibility.php` z historią i ręczną korektą.
- [x] Karta wiarygodności w profilu oraz w dziale prawnym.
- [x] Hooki negatywne: czarny rynek, komornik, bankructwo, złamany plan naprawczy, duże opóźnienie płatności.
- [x] Hooki pozytywne: rata na czas, pełna spłata kredytu, wcześniejsza spłata.
- [x] Tick `CredibilitySection` przyznaje `clean_operation_period` `+3` raz na 7 dni bez negatywnych zdarzeń.
- [x] `LegalService::submitApplication()` blokuje wnioski w regionach `high/critical`, jeśli wiarygodność firmy jest poniżej `40/100`.
- [x] `/legal` pokazuje osobną grupę regionów zablokowanych wiarygodnością.
- [x] Mapa pokazuje osobny status `credibility_locked`.
- [x] Testy dopisane: `CompanyCredibilityServiceTest`, `LegalServiceTest`, `LegalMapPermitDataTest`.

---

## 5. Co zostało po aktualizacji 2026-06-05

### 5.1. Najbliższe TODO techniczne

- [ ] Uruchomić pełne testy PHP po przywróceniu `php` w PATH: `CompanyCredibilityServiceTest`, `LegalServiceTest`, `LegalMapPermitDataTest`, `LegalSectionTest`, `WorldMapPermitGateTest`.
- [ ] Ręcznie sprawdzić `/legal`: gracz z wiarygodnością `< 40` widzi osobną grupę blokady dla regionów `high/critical`.
- [ ] Ręcznie sprawdzić mapę: region `high/critical` przy wiarygodności `< 40` pokazuje `credibility_locked`, a przy `>= 40` przechodzi do kolejnych bramek.
- [ ] Ręcznie sprawdzić tick: `clean_operation_period` dopisuje historię i podnosi wynik tylko raz na 7 dni.
- [ ] Rozważyć przeniesienie progu `40` i okresu `7 dni` do konfiguracji admina/balansu, jeśli balans będzie wymagał częstych zmian.

### 5.2. TODO dla `18.6` Wiarygodność firmy

- [x] Fundament wyniku, historia, panel admina, karta gracza, hooki i wpływ na dział prawny.
- [ ] Dodać wpływ wiarygodności na inne działy dopiero po decyzji projektowej: bank, kontrakty, partnerzy, aukcje, koszty ubezpieczeń.
- [x] Zdarzenia transportowe są księgowane finansowo: `theft` i `accident` w transporcie drogowym, `leak` i `pressure_drop` w modelu rurociągowym oraz utracone dostawy morskie (`piracy` / `catastrophe`) trafiają do `finance_logs.transport_event_loss_bbl`; `storm` w aktualnym modelu dostaw morskich pozostaje opóźnieniem bez utraty ropy.
- [x] Panel admina incydentów ma osobny toolbar do ręcznej ingerencji w dostawy morskie: `piracy`, `catastrophe`, `storm`, `breakdown`.
- [ ] Dodać więcej źródeł negatywnych zdarzeń, gdy będą gotowe moduły: poważne incydenty środowiskowe, cofnięcie zezwolenia, kara prawna, przegrana sprawa sądowa.
- [ ] Dodać więcej źródeł pozytywnych zdarzeń, jeśli gameplay tego potrzebuje: długi okres bez awarii, audyt prawny zakończony pozytywnie, ugoda bez eskalacji.
- [ ] Doprecyzować politykę dla graczy bez historii: obecnie pierwszy tick bez negatywnych zdarzeń może przyznać `+3`; to jest proste i zgodne z aktualnym briefem, ale może wymagać balansu.

### 5.3. TODO świadomie odłożone z sekcji 18

- [ ] [odłożone] Zezwolenia infrastrukturalne (huby, rurociągi, transport morski) — 18.1
- [ ] [odłożone] Zezwolenia warunkowe (limit odwiertów, krótszy czas ważności) — 18.2
- [ ] [odłożone] Zezwolenia wygasłe (odnowienie) — 18.3
- [ ] [odłożone] Zezwolenia cofnięte (blokada regionu) — 18.4
- [ ] [odłożone] Kary i blokady prawne (po incydentach, sabotażu) — 18.5
- [x] [wdrożone] Wiarygodność firmy — fundament + wpływ na wnioski w regionach wysokiego ryzyka — 18.6
- [ ] [odłożone] Łapówki i nielegalne przyspieszanie decyzji — 18.7
- [ ] [odłożone] Sprawy sądowe, ugody, umowy — 18.8

---

## 6. Rekomendowana kolejność dalszego wdrażania

1. **18.5 Kary i blokady prawne po incydentach** — naturalnie łączy dział prawny z istniejącymi incydentami, katastrofami i wiarygodnością firmy.
2. **18.3 Zezwolenia wygasłe / odnowienie** — prosta mechanika retencji zezwoleń, dobra do ticka i panelu `/legal`.
3. **18.4 Zezwolenia cofnięte** — mocniejsza konsekwencja prawna, najlepiej po 18.5.
4. **18.2 Zezwolenia warunkowe** — ciekawy balans, ale wymaga więcej UI i jasnych limitów.
5. **18.1 Zezwolenia infrastrukturalne** — większy zakres, bo dotyka hubów, rurociągów i transportu.
6. **18.7 Łapówki / nielegalne przyspieszanie** — warto wdrażać dopiero po stabilnych karach, bo musi mieć realne ryzyko.
7. **18.8 Sprawy sądowe, ugody, umowy** — największy moduł, raczej osobny etap.

---

## 7. Podsumowanie

Rdzeń P1 (zezwolenia, wnioski, tick, bramka zakupu, migracja, panel admina, powiadomienia)
jest wdrożony zgodnie z briefem. Dodatkowo rozpoczęto P2 przez realne podpięcie
`required_legal_level` oraz `company_credibility` do admina, strony działu prawnego,
mapy i walidacji wniosku. Punkt `18.6` jest wdrożony jako fundament plus pierwszy
realny wpływ na gameplay: blokada wniosków w regionach wysokiego ryzyka przy
wiarygodności firmy poniżej `40/100`.
