# OilEmpire — propozycje DOM dla P14–P25

**Aktualizacja decyzji właściciela 2026-11-18:** DOM03–05 i DOM08–14 przyjęte jako reguły do programowania; DOM06 ma [katalog kandydujący](OILEMPIRE_DOM06_KATALOG_SZKOLEN_KANDYDAT.md), DOM15 ma [kalibrację kandydującą](OILEMPIRE_DOM15_KALIBRACJA_RYZYKA.md). [Rejestr decyzji](OILEMPIRE_DOM_DECYZJE_WLASCICIELA_2026_11_18.md) jest nadrzędny dla statusu. Żadna nowa reguła nie jest aktywnym balansem. Zależna akcja zwraca `POLICY_NOT_APPROVED` (409), bez rezerwacji, FTS, księgowania i zdarzenia, dopóki wersjonowana polityka nie ma `approved_at`, `approved_by`, `effective_at` oraz powiązanego `balance_version`. DOM06 i DOM08 są częściowo ustalone; gate dotyczy tylko nierozstrzygniętej części. Po decyzji w jednym PR aktualizować kanon, karty P06–P25, kontrakty, DDL jeśli dotyczy, OpenAPI, kandydujący YAML, testy i macierz zależności. Aktywacja balansu wymaga four-eyes, hash YAML i 0 FAIL symulatora; nie wolno aktywować z tego pliku.

## DOM03 — Negocjacje zbiorowe

- **Propozycja:** Próg: ≥3 pracowników tej samej specjalizacji w firmie. Jedno żądanie na grupę/30 dni; wynik podwyżki = min(12%, max(2%, 4% + 1 p.p. za każdy pełny punkt przewagi siły negocjacyjnej)); brak kumulacji żądań z tego samego okresu.
- **Przykład:** 10 pracowników po 100 000 gr/dzień, przewaga 3 → 7%; koszt +70 000 gr/dzień; 30 dni = 2 100 000 gr.
- **Wpływ na balans:** Ogranicza skok OPEX i spam negocjacyjny; porównać efekty na morale oraz bankructwa.
- **Testy graniczne:** 0/2/3 pracowników, 30. dzień, siła ujemna i >8, retry i dwa równoległe żądania.
- **Stan:** `owner_approved_pending_implementation_and_activation`; reguła zatwierdzona przez właściciela, lecz wymaga wersjonowanego snapshotu i bramek wydania.

## DOM04 — Kwota żądanej podwyżki

- **Propozycja:** Wniosek pracownika/grupy podaje target w p.p. 2–15; propozycja systemu to 4% + 1 p.p. × max(0,60−salary_satisfaction), cap 15. Oferta pracodawcy 0–target; decyzja i cooldown według HR-CONTRACT01.
- **Przykład:** Płaca 120 000 gr/dzień, żądanie 8% → 9 600 gr/dzień; oferta 5% → 6 000 gr/dzień.
- **Wpływ na balans:** Górny limit umożliwia wycenę ryzyka; brak podwójnego kosztu przy grupie.
- **Testy graniczne:** 0/2/15/16%, brak środków, retry, jednoczesna zmiana płacy.
- **Stan:** `owner_approved_pending_implementation_and_activation`; reguła zatwierdzona przez właściciela, lecz wymaga wersjonowanego snapshotu i bramek wydania.

## DOM05 — Propagacja sankcji kartelowej

- **Reguła już ustalona w kanonie §21/§26.3:** po 3 oflagowanych transakcjach w 7 dni obie strony otrzymują −15 company_credibility i −15 b2b_reputation_score oraz 30 dni zawieszenia zwolnienia z prowizji. Propozycja nie zmienia progu, kar ani czasu.
- **Propozycja tylko dla otwartej części:** oba bezpośrednie spadki są wynikiem końcowym pojedynczego sanction_event_id; ogólny listener propagacji reputacji nie nalicza od tego samego event_id kolejnego spadku B2B. Dwa różne zdarzenia nadal mogą mieć odrębne skutki według kanonu.
- **Przykład:** 2 flagi → brak sankcji. Trzecia flaga w 7 dni: credibility 70→55, B2B 80→65, zwolnienie z prowizji zawieszone na 30 dni; retry tego samego event_id nie zmienia wyniku.
- **Wpływ na balans:** brak podwójnej kary z jednego wyzwalacza; sprawdzić częstotliwość progu i obroty B2B, bez zmiany aktywnego YAML.
- **Testy graniczne:** 2/3/4 flagi, 7 dni ±1 ms, dwie strony, dwa równoległe handlery, retry event_id, 30 dni ±1 ms.
- **Stan:** `owner_approved_pending_implementation_and_activation`; reguła zatwierdzona przez właściciela, lecz wymaga wersjonowanego snapshotu i bramek wydania.

## DOM06 — Techniczne szkolenia — katalog kandydujący

- **Propozycja:** Zachować już ustalone poziomy I/II/III: 20k/50k/100k PLN i 24/48/72h. [Katalog kandydujący](OILEMPIRE_DOM06_KATALOG_SZKOLEN_KANDYDAT.md) podaje cztery kursy bazowe i zachowuje ustalone efekty; ceny 10/30/60 tys. PLN oraz mapa capability nadal wymagają zatwierdzenia i kalibracji. Nieznana specjalizacja odmawia.
- **Przykład:** Poziom II: 5 000 000 gr i 48h; dwa identyczne callbacki kończą jeden kurs.
- **Wpływ na balans:** Brak nowych mnożników wydajności przed kalibracją.
- **Testy graniczne:** Nieznana specjalizacja, poziom 0/IV, kończenie w tym samym czasie co urlop.
- **Stan:** `proposed_pending_owner_approval`; dla niezatwierdzonej części `POLICY_NOT_APPROVED`.

## DOM08 — Zdarzenie HR w tym samym terminie — częściowe

- **Propozycja:** Zachować znane rozstrzygnięcie: egzamin i koniec szkolenia w tym samym logical_at mają ustaloną kolejność w P07–P09. Propozycja dla naprawy i eskalacji: zakończenie naprawy poprzedza eskalację tylko gdy wszystkie warunki były spełnione na logical_at; inaczej eskalacja.
- **Przykład:** Naprawa i kontrola 12:00, części dostępne od 11:59 → status repaired, bez kary; części od 12:01 → kontrola karze.
- **Wpływ na balans:** Zmniejsza losowość przy tym samym terminie; może obniżyć kary.
- **Testy graniczne:** Dwa joby w odwrotnej kolejności, logical_at ±1 ms, retry i częściowe wykonanie.
- **Stan:** `owner_approved_pending_implementation_and_activation`; reguła zatwierdzona przez właściciela, lecz wymaga wersjonowanego snapshotu i bramek wydania.

## DOM09 — Apelacja: koszt i prawdopodobieństwo

- **Propozycja:** Opłata odwołania nie podlega zwrotowi po rozpatrzeniu; opłata zwracana przy błędzie technicznym przed przyjęciem. Szansa apelacji = clamp(kanoniczna baza + min(10 p.p., 2 p.p. × skill_law), 5%, 90%); losowanie raz na case_id. Limit bonusu +10 p.p. pochodzi z §7.3; limit 5–90% nie zmienia pierwotnego wniosku P1/P2.
- **Przykład:** Baza 30%, kompetencja 3 → 36%; opłata 100 000 gr → koszt niezależnie od wyniku.
- **Wpływ na balans:** Podnosi wartość HR Legal; koszt i cap wymagają kalibracji.
- **Testy graniczne:** Baza 0/100, kompetencja 0/max, dwa joby, anulowanie przed i po przyjęciu.
- **Stan:** `owner_approved_pending_implementation_and_activation`; reguła zatwierdzona przez właściciela, lecz wymaga wersjonowanego snapshotu i bramek wydania.

## DOM10 — Wycena regionalna i odnowienie koncesji

- **Propozycja:** Podstawa = ostatnia zatwierdzona wartość referencyjna regionu × udział eksploatacyjny; opłata odnowienia 5% podstawy, minimum z katalogu pozwolenia. 30 dni grace: istniejące wydobycie trwa, nowych odwiertów nie rozpoczyna się; po grace pauza.
- **Przykład:** Region 10 mln PLN, udział 100% → opłata 500 tys. PLN; 50% → 250 tys. PLN przed minimum.
- **Wpływ na balans:** Uznaje skalę aktywów; na regionach drogich może ograniczyć ekspansję.
- **Testy graniczne:** Udział 0/100%, brak referencji, dokładnie 30 dni, równoległe odnowienie.
- **Stan:** `owner_approved_pending_implementation_and_activation`; reguła zatwierdzona przez właściciela, lecz wymaga wersjonowanego snapshotu i bramek wydania.

## DOM11 — Koszt odmowy pozwolenia

- **Propozycja:** Odrzucone pozwolenie: opłata początkowa to koszt okresu, nie CAPEX. Po skutecznej apelacji tylko pierwotna opłata staje się CAPEX; opłata apelacyjna pozostaje kosztem. Księga zapisuje korektę, nigdy edycję starego zapisu.
- **Przykład:** Opłata 100 000 gr + apelacja 20 000 gr → CAPEX 100 000 gr, koszt 20 000 gr po sukcesie.
- **Wpływ na balans:** Wpływa na wartość netto i podatek; bez podwójnego naliczenia.
- **Testy graniczne:** Odrzucenie, skuteczna/nieskuteczna apelacja, refund i retry korekty.
- **Stan:** `owner_approved_pending_implementation_and_activation`; reguła zatwierdzona przez właściciela, lecz wymaga wersjonowanego snapshotu i bramek wydania.

## DOM12 — Czas oczekiwania w zatoce

- **Propozycja:** Dwa zegary: 30 min na podstawowy załadunek/demurrage przewoźnika; 2h na waiting_bay odbiorcy. Ta sama minuta nie generuje dwóch opłat z tej samej przyczyny. Płatnik przypisany do źródła opóźnienia.
- **Przykład:** Przyjazd 12:00, gotowość odbiorcy 14:15 → waiting_bay nalicza 15 min od 14:00; 30 min pracy załadunkowej nie mnoży tej opłaty.
- **Wpływ na balans:** Ogranicza nieprzewidywalny drenaż logistyki.
- **Testy graniczne:** 12:29/12:30/14:00/14:01, zmiana płatnika, retry.
- **Stan:** `owner_approved_pending_implementation_and_activation`; reguła zatwierdzona przez właściciela, lecz wymaga wersjonowanego snapshotu i bramek wydania.

## DOM13 — Przepustowość portu

- **Propozycja:** 500/1500/4000 bbl jest limitem na jeden logiczny slot 5 min na poziomie portu i tieru. Opóźniony worker przetwarza kolejno sloty, nie mnoży limitu przez liczbę uruchomień; idempotency port:slot:flow.
- **Przykład:** Tier 500, sloty 12:00 i 12:05 → max 1000 bbl łącznie; trzy retry pierwszego slotu nadal max 500.
- **Wpływ na balans:** Odcina exploit częstotliwości requestów; zwiększa kolejkę przy szczycie.
- **Testy graniczne:** Granica 5 min, 2 workerów, 0/500/501 bbl, restart po commit.
- **Stan:** `owner_approved_pending_implementation_and_activation`; reguła zatwierdzona przez właściciela, lecz wymaga wersjonowanego snapshotu i bramek wydania.

## DOM14 — Zmiana kierunku rurociągu

- **Propozycja:** Zmiana wymaga dwóch pełnych logicznych slotów 5 min liczonych od najbliższej granicy slotu; podczas switch zero przepływu; zlecenie i stan mają wersję.
- **Przykład:** Zlecenie 12:03 → sloty 12:05 i 12:10, nowy kierunek od 12:15; brak ładunku w przejściu.
- **Wpływ na balans:** Koszt alternatywny 10–12 min, zależny od chwili zlecenia.
- **Testy graniczne:** 12:00:00 i 12:04:59, dwa zlecenia odwrotne, awaria joba.
- **Stan:** `owner_approved_pending_implementation_and_activation`; reguła zatwierdzona przez właściciela, lecz wymaga wersjonowanego snapshotu i bramek wydania.

## DOM15 — Hazard i jednostka ekspozycji

- **Propozycja:** [Kalibracja](OILEMPIRE_DOM15_KALIBRACJA_RYZYKA.md) wykazuje, że 5 min jest slotem deduplikacji, a nie wspólną jednostką stawki. Road ma bazę 0,8%/h; odwiert ma nieustalone 0,2–1,2%/tick. Kandydat to baza /24h dla odwiertu, do testu 90 dni. Wynik rzutu utrwala się dla zamkniętej ekspozycji; retry nie losuje ponownie.
- **Przykład:** Dla kursu drogowego 0,8%/h: 12 min ekspozycji → P≈0,160514%; 5-min slot → P≈0,066912%. Dla odwiertu 1,2%/tick użyte co 5 min dałoby 96,91% szansy co najmniej jednej awarii/dobę — wariant odrzucony do czasu kalibracji.
- **Wpływ na balans:** Jednostka czasu radykalnie zmienia awaryjność odwiertów; na typowym kursie drogowym formuła hazardowa praktycznie zachowuje istniejącą wycenę. Pełna kalibracja wymaga 90 dni silnika.
- **Testy graniczne:** p=0/1, n=0/1, dwa workery, opóźniony slot, restart.
- **Stan:** `proposed_pending_owner_approval`; dla niezatwierdzonej części `POLICY_NOT_APPROVED`.

## Kontrakt decyzji i ślad audytowy

Właściciel **P05 Admin/Balance** przechowuje decyzję w istniejącym procesie `balance_versions` i historii zatwierdzeń: `dom_id`, `proposal_version`, `status` (`proposed`/`approved`/`rejected`), `approved_by`, `approved_at`, `effective_at`, `balance_version`, `yaml_sha256`, `simulator_run_id`. Jeśli obecny schemat P05 nie ma tych pól, P05 dodaje własną migrację; P14–P25 nie tworzą wtórnej tabeli polityk. Action czyta niezmienny opublikowany snapshot przed zmianą stanu i sprawdza jego wersję ponownie wewnątrz transakcji. Odrzucenie nie tworzy ukrytej wartości domyślnej. Zmiana zatwierdzonej reguły tworzy nową wersję, zachowuje historyczne snapshoty, przechodzi przez release P25.
