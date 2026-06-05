# Wiarygodność firmy (fundament) — status wdrożenia

> Porównanie kodu z briefem: `BRIEF_WIARYGODNOSC_FIRMY_FUNDAMENT.md` (16 sekcji).
> Legenda: ✅ zrobione · ⚠️ częściowe / odstępstwo · ⏳ do zrobienia · 🚫 TODO świadomie odłożone (poza zakresem fundamentu).

Data analizy: 2026-06-05

---

## 1. Architektura wdrożenia

| Warstwa | Plik |
|---|---|
| Logika / dane | `src/CompanyCredibilityService.php` (schema, getScore, getLevel, changeScore, logChange, applyEvent, getHistory) |
| Migracja | `migrations/etap12_company_credibility.sql` (pole + tabela log) |
| Widok gracza | `templates/components/company_credibility.php` + `assets/css/credibility.css` |
| Dashboard | `public/index.php` + `templates/views/index/main.php` |
| Panel admina | `admin/credibility.php` + `templates/views/admin/credibility/main.php` |
| JS admina | `assets/js/admin_credibility.js` (modal ręcznej korekty) |
| Style admina | `assets/css/admin.css` (odznaki, grid listy, modal) |
| Nawigacja | `admin/partials/header.php` (sekcja Finanse) |
| Tłumaczenia | `lang/pl/credibility.php`, `lang/pl/admin/credibility.php`, `lang/pl/admin/nav.php`, loader `lang/pl.php` |
| Powiadomienia | `director_notifications` typ `credibility` (guarded) |
| Testy | `tests/Integration/CompanyCredibilityServiceTest.php` (21 testów) |

---

## 2. Zakres „Co wdrażamy teraz" (sekcja 13 briefu) — 10 punktów

- [x] **1. Pole `company_credibility` w `players`** — `INT UNSIGNED NOT NULL DEFAULT 50` ✅
- [x] **2. Tabela `company_credibility_log`** — id, player_id, event_key, delta, score_before, score_after, note, created_at ✅
- [x] **3. `CompanyCredibilityService`** — pełna logika, jedyny punkt zmiany wyniku ✅
- [x] **4. Progi opisowe 0–100** — 5 poziomów (critical/low/shaky/stable/high) ✅
- [x] **5. Karta na dashboardzie gracza** — wynik, poziom, opis, pasek; bez matematyki ✅
- [x] **6. Podgląd w panelu admina** — lista graczy + historia zmian ✅
- [x] **7. Ręczna korekta w panelu admina** — modal, wymaga delty i notatki ✅
- [x] **8. Logowanie każdej zmiany** — wpis w `company_credibility_log` przy każdej zmianie ✅
- [x] **9. Pierwsze podpięcia (bank, komornik, bankructwo, czarny rynek)** — 8 zdarzeń ✅
- [x] **10. Podstawowe testy** — 21 testów integracyjnych (zakres, log, poziomy, próg, guard) ✅

---

## 3. Zgodność z zasadami briefu

- [x] Skala 0–100, start 50; wynik twardo przycinany (sekcja 1.1) — `clamp()` ✅
- [x] Osobny wskaźnik — NIE rusza `credit_score`, `bank_trust_scores`, `black_market_score` (sekcja 11) ✅
- [x] Wszystkie zmiany przechodzą przez serwis (sekcja 4) — żaden inny kod nie pisze do pola bezpośrednio ✅
- [x] Każda zmiana logowana (sekcja 3) — log zapisywany nawet przy delcie efektywnej 0 (sufit/podłoga) ✅
- [x] Gracz nie widzi matematyki (sekcja 7) — tylko wynik, poziom, opis, krótki hint ✅
- [x] Powiadomienia przez istniejący system (`director_notifications`), nie alerty (sekcja 9) ✅
- [x] Powiadomienie tylko przy `|delta| >= 5` (sekcja 9) ✅
- [x] Brak `alert()` / `confirm()` / `prompt()` natywnych w panelu admina (sekcja 10) ✅

---

## 4. Metody serwisu (sekcja 5)

- [x] `getScore(int $playerId): int` ✅
- [x] `getLevel(int $score): string` → critical / low / shaky / stable / high ✅
- [x] `changeScore(int, int, string, ?string): int` — brief sugerował `void`; zwracamy `int` (wynik po zmianie) jako rozszerzenie zgodne z sekcją „nazwy/zakres dopasowane do stylu kodu" ⚠️ (świadome, korzystne)
- [x] `logChange(int, string, int, int, int, ?string): void` ✅
- [x] `applyEvent(int, string, ?string): void` — wygodne podpięcie ze stałej mapy delt (dodatkowa metoda) ✅
- [x] `getHistory(int, int): array` — dla panelu admina (dodatkowa metoda) ✅

---

## 5. Eventy (sekcja 6)

### 5.1. Zdarzenia negatywne

- [x] `black_market_detected` (−12) — `BlackMarketService::executeTransaction()` po `commit()` ✅
- [x] `bailiff_activated` (−20) — `BailiffService::startNewProceedings()` ✅
- [x] `bankruptcy_entered` (−25) — `BailiffService::declareBankruptcy()` ✅
- [x] `recovery_plan_broken` (−10) — `BankNegotiation/ProcessorTrait::checkRecoveryPlanViolations()` ✅
- [x] `major_payment_delay` (−6) — `LoanRepository::processInstallment()` przy przejściu w `late` (raz na transition) ✅

### 5.2. Zdarzenia pozytywne

- [x] `loan_installment_paid_on_time` (+2) — `LoanRepository::processInstallment()` (rata częściowa) ✅
- [x] `loan_fully_repaid` (+8) — `LoanRepository::processInstallment()` (pełna spłata ratą) ✅
- [x] `loan_repaid_early` (+6) — `Bank/RepaymentTrait::repay()` po `commit()` (ręczna spłata) ✅
- [ ] `clean_operation_period` (+3) — 🚫 świadomie odłożony zgodnie z briefem (sekcja 6.2: „można wdrożyć później, jeśli nie ma prostego miejsca")

---

## 6. Widok gracza (sekcja 7)

- [x] Karta „Wiarygodność firmy" na dashboardzie ✅
- [x] Wynik `X / 100`, poziom opisowy, krótki opis ✅
- [x] Hint: „wynik zależy od stabilności finansowej, banku, naruszeń i działań ryzykownych" ✅
- [x] Brak ujawniania algorytmów / przeliczników ✅

---

## 7. Widok admina (sekcja 8)

- [x] Lista graczy: nazwa, aktualny wynik, poziom opisowy ✅
- [x] Historia zmian z `company_credibility_log`: data, event_key, delta, przed, po, notatka ✅
- [x] Ręczna korekta przez modal — pola: zmiana wyniku + notatka; przyciski Anuluj / Zapisz korektę ✅
- [x] Walidacja: notatka wymagana, delta ≠ 0 (inaczej błąd) ✅
- [x] Event `admin_manual_adjustment`, zapis w historii ✅
- [x] Widok zbudowany na CSS Grid — zero tabel HTML (zasada projektu) ✅

### 7.1. ⚠️ Modal — odstępstwo od współdzielonego `modal.js`
- Modal ręcznej korekty to **dedykowany modal HTML** (`assets/js/admin_credibility.js`),
  a nie `confirmAction()`/`promptInput()` z `modal.js`.
- Powód: `promptInput()` obsługuje jedno pole, a korekta wymaga DWÓCH (zmiana + notatka).
- Zgodność z twardą zasadą sekcji 10 zachowana: **nie używamy** `alert()`/`confirm()`/`prompt()` natywnych.
- **Możliwy fix later:** rozszerzyć `modal.js` o modal wielopolowy i podpiąć pod niego korektę.

---

## 8. Powiadomienia (sekcja 9)

- [x] Próg `|delta| >= 5` — małe zmiany (+2, +3) nie powiadamiają ✅
- [x] Komunikaty per zdarzenie (czarny rynek, komornik, bankructwo, pełna spłata, …) ✅
- [x] Komunikat ogólny (fallback) wg kierunku (wzrost/spadek) ✅
- [x] Duży spadek (`<= -15`: komornik, bankructwo) → priorytet `high` ✅
- [x] Przez `director_notifications` (typ `credibility`), nie przez alerty ✅
- [x] W pełni guarded — brak tabeli powiadomień nie przerywa zmiany wyniku ✅

---

## 9. Testy (sekcja 12)

- [x] **12.1 Zakres wyniku** — `testScoreNeverExceedsMax`, `testScoreNeverDropsBelowZero` ✅
- [x] **12.2 Logowanie zmian** — `testEveryChangeWritesLog`, `testLogStoresEffectiveDeltaWhenClamped` ✅
- [x] **12.3 Zmiana pozytywna** — `testPositiveChangeRaisesScore` (loan_fully_repaid +8) ✅
- [x] **12.4 Zmiana negatywna** — `testNegativeChangeLowersScore` (black_market_detected −12) ✅
- [x] **12.5 Widok admina** — logika danych (`getScore`/`getLevel`/`getHistory`) pokryta testami; sam render HTML do sprawdzenia ręcznego ⚠️
- [x] **12.6 Ręczna korekta** — pokryta na poziomie serwisu (`changeScore` z notatką → log); walidacja „delta ≠ 0 / notatka wymagana" w kontrolerze admina do sprawdzenia ręcznego ⚠️
- [x] Dodatkowo: progi poziomów (data provider), próg powiadomienia, guard braku tabeli, nieznane zdarzenie ✅

Wynik: **21 testów / 21 OK**. Pełny pakiet Unit+Integration: **160/160 OK**.
(`MySqlIntegration` wymaga serwera MySQL — niedostępny w tym środowisku, niezwiązane z modułem.)

---

## 10. 🚫 TODO świadomie odłożone (sekcje 14–15 — poza zakresem fundamentu)

Poprawnie NIE wdrożone, zgodnie z briefem:

- [ ] 🚫 Wpływ na zezwolenia regionalne / dział prawny (15.1)
- [ ] 🚫 Wpływ na trudniejsze regiony, próg np. 60/100 (15.1)
- [ ] 🚫 Bank jako modyfikator z `company_credibility` (15.2)
- [ ] 🚫 Kontrakty i partnerzy (15.3)
- [ ] 🚫 Przetargi i offshore (15.4)
- [ ] 🚫 Łapówki wpływające na wynik (15.5)
- [ ] 🚫 Automatyczna odbudowa wyniku (15.6)
- [ ] 🚫 Audyty, pełny system kar prawnych (sekcja 14)
- [ ] 🚫 Event `clean_operation_period` (+3) — odłożony (6.2)

---

## 11. Co jeszcze warto dopiąć (drobne, opcjonalne)

1. **Test kontrolera admina** — automatyczny test walidacji ręcznej korekty (delta ≠ 0, notatka wymagana) zamiast tylko ręcznego sprawdzenia (12.6).
2. **Modal wielopolowy w `modal.js`** — by ujednolicić z resztą gry (sekcja 10 / pkt 7.1).
3. **Migracja produkcyjna** — uruchomić `migrations/etap12_company_credibility.sql` raz przez phpMyAdmin (lub poczekać na auto-`ensureSchema()` przy pierwszym wejściu na dashboard/panel admina poza transakcją).

---

## 12. Podsumowanie

Fundament `company_credibility` **wdrożony w całości** zgodnie z briefem: pole, tabela historii,
serwis (jedyny punkt zmiany), 5 poziomów, karta gracza, panel admina z historią i ręczną korektą,
8 podpiętych zdarzeń, powiadomienia przy `|delta| >= 5`, 21 testów. Świadome odstępstwa: dedykowany
modal admina zamiast `modal.js` (powód: dwa pola), zwrot `int` z `changeScore`, oraz odłożony
`clean_operation_period` — wszystkie zgodne z duchem dokumentu. Reszta to TODO sekcji 14–15.
