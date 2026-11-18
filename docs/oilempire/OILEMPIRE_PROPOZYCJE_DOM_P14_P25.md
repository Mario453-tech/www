# OilEmpire — propozycje DOM dla P14–P25

Status wszystkich poniższych wartości: **do zatwierdzenia przez właściciela produktu**. To kandydaci, nie aktywny balans ani zmiana kanonu. Zależna akcja zwraca `POLICY_NOT_APPROVED` (409), bez rezerwacji, FTS, księgowania i zdarzenia, dopóki wersjonowana polityka nie ma `approved_at`, `approved_by`, `effective_at` oraz powiązanego `balance_version`. DOM06 i DOM08 są częściowo ustalone; gate dotyczy tylko nierozstrzygniętej części. Po decyzji w jednym PR aktualizować kanon, karty P06–P25, kontrakty, DDL jeśli dotyczy, OpenAPI, kandydujący YAML, testy i macierz zależności. Aktywacja balansu wymaga four-eyes, hash YAML i 0 FAIL symulatora; nie wolno aktywować z tego pliku.

## DOM03 — Negocjacje zbiorowe

- **Propozycja:** Próg: ≥3 pracowników tej samej specjalizacji w firmie. Jedno żądanie na grupę/30 dni; wynik podwyżki = min(12%, max(2%, 4% + 1 p.p. za każdy pełny punkt przewagi siły negocjacyjnej)); brak kumulacji żądań z tego samego okresu.
- **Przykład:** 10 pracowników po 100 000 gr/dzień, przewaga 3 → 7%; koszt +70 000 gr/dzień; 30 dni = 2 100 000 gr.
- **Wpływ na balans:** Ogranicza skok OPEX i spam negocjacyjny; porównać efekty na morale oraz bankructwa.
- **Testy graniczne:** 0/2/3 pracowników, 30. dzień, siła ujemna i >8, retry i dwa równoległe żądania.
- **Stan:** `proposed_pending_owner_approval`; dla niezatwierdzonej części `POLICY_NOT_APPROVED`.

## DOM04 — Kwota żądanej podwyżki

- **Propozycja:** Wniosek pracownika/grupy podaje target w p.p. 2–15; propozycja systemu to 4 p.p. + 1 p.p. za każdy pełny punkt niezadowolenia ponad próg, cap 15. Oferta pracodawcy 0–target; decyzja i cooldown według HR-CONTRACT01.
- **Przykład:** Płaca 120 000 gr/dzień, żądanie 8% → 9 600 gr/dzień; oferta 5% → 6 000 gr/dzień.
- **Wpływ na balans:** Górny limit umożliwia wycenę ryzyka; brak podwójnego kosztu przy grupie.
- **Testy graniczne:** 0/2/15/16%, brak środków, retry, jednoczesna zmiana płacy.
- **Stan:** `proposed_pending_owner_approval`; dla niezatwierdzonej części `POLICY_NOT_APPROVED`.

## DOM05 — Kara kartelu

- **Propozycja:** Jedno udowodnione naruszenie: −15 wiarygodności i −15 reputacji B2B; propagację drugiego efektu z tego samego event_id zmniejszyć o 50%, zaokrąglić w dół tylko na końcu; brak duplikacji po retry.
- **Przykład:** B2B 80 i wiarygodność 70 → B2B 65, wiarygodność 55; wtórna propagacja 15×0,5 = 7,5 wg istniejącej reguły rounding.
- **Wpływ na balans:** Silna, ale ograniczona sankcja; weryfikować wolumen kontraktów i arbitraż.
- **Testy graniczne:** Dwa raporty o tym samym event_id, wynik 0 i 100, równoległa publikacja.
- **Stan:** `proposed_pending_owner_approval`; dla niezatwierdzonej części `POLICY_NOT_APPROVED`.

## DOM06 — Techniczne szkolenia — częściowe

- **Propozycja:** Zachować już ustalone poziomy I/II/III: 20k/50k/100k PLN i 24/48/72h. Do zatwierdzenia pozostają katalog umiejętności, wymagania stanowisk i skutki certyfikatu; bez nich akcja startu dla nieznanej specjalizacji odmawia.
- **Przykład:** Poziom II: 5 000 000 gr i 48h; dwa identyczne callbacki kończą jeden kurs.
- **Wpływ na balans:** Brak nowych mnożników wydajności przed kalibracją.
- **Testy graniczne:** Nieznana specjalizacja, poziom 0/IV, kończenie w tym samym czasie co urlop.
- **Stan:** `proposed_pending_owner_approval`; dla niezatwierdzonej części `POLICY_NOT_APPROVED`.

## DOM08 — Zdarzenie HR w tym samym terminie — częściowe

- **Propozycja:** Zachować znane rozstrzygnięcie: egzamin i koniec szkolenia w tym samym logical_at mają ustaloną kolejność w P07–P09. Propozycja dla naprawy i eskalacji: zakończenie naprawy poprzedza eskalację tylko gdy wszystkie warunki były spełnione na logical_at; inaczej eskalacja.
- **Przykład:** Naprawa i kontrola 12:00, części dostępne od 11:59 → status repaired, bez kary; części od 12:01 → kontrola karze.
- **Wpływ na balans:** Zmniejsza losowość przy tym samym terminie; może obniżyć kary.
- **Testy graniczne:** Dwa joby w odwrotnej kolejności, logical_at ±1 ms, retry i częściowe wykonanie.
- **Stan:** `proposed_pending_owner_approval`; dla niezatwierdzonej części `POLICY_NOT_APPROVED`.

## DOM09 — Apelacja: koszt i prawdopodobieństwo

- **Propozycja:** Opłata odwołania nie podlega zwrotowi po rozpatrzeniu; opłata zwracana przy błędzie technicznym przed przyjęciem. Szansa = clamp(kanoniczna baza + 2 p.p. × poziom kompetencji prawnej, 5%, 90%); losowanie raz na case_id.
- **Przykład:** Baza 30%, kompetencja 3 → 36%; opłata 100 000 gr → koszt niezależnie od wyniku.
- **Wpływ na balans:** Podnosi wartość HR Legal; koszt i cap wymagają kalibracji.
- **Testy graniczne:** Baza 0/100, kompetencja 0/max, dwa joby, anulowanie przed i po przyjęciu.
- **Stan:** `proposed_pending_owner_approval`; dla niezatwierdzonej części `POLICY_NOT_APPROVED`.

## DOM10 — Wycena regionalna i odnowienie koncesji

- **Propozycja:** Podstawa = ostatnia zatwierdzona wartość referencyjna regionu × udział eksploatacyjny; opłata odnowienia 5% podstawy, minimum z katalogu pozwolenia. 30 dni grace: istniejące wydobycie trwa, nowych odwiertów nie rozpoczyna się; po grace pauza.
- **Przykład:** Region 10 mln PLN, udział 100% → opłata 500 tys. PLN; 50% → 250 tys. PLN przed minimum.
- **Wpływ na balans:** Uznaje skalę aktywów; na regionach drogich może ograniczyć ekspansję.
- **Testy graniczne:** Udział 0/100%, brak referencji, dokładnie 30 dni, równoległe odnowienie.
- **Stan:** `proposed_pending_owner_approval`; dla niezatwierdzonej części `POLICY_NOT_APPROVED`.

## DOM11 — Koszt odmowy pozwolenia

- **Propozycja:** Odrzucone pozwolenie: opłata początkowa to koszt okresu, nie CAPEX. Po skutecznej apelacji tylko pierwotna opłata staje się CAPEX; opłata apelacyjna pozostaje kosztem. Księga zapisuje korektę, nigdy edycję starego zapisu.
- **Przykład:** Opłata 100 000 gr + apelacja 20 000 gr → CAPEX 100 000 gr, koszt 20 000 gr po sukcesie.
- **Wpływ na balans:** Wpływa na wartość netto i podatek; bez podwójnego naliczenia.
- **Testy graniczne:** Odrzucenie, skuteczna/nieskuteczna apelacja, refund i retry korekty.
- **Stan:** `proposed_pending_owner_approval`; dla niezatwierdzonej części `POLICY_NOT_APPROVED`.

## DOM12 — Czas oczekiwania w zatoce

- **Propozycja:** Dwa zegary: 30 min na podstawowy załadunek/demurrage przewoźnika; 2h na waiting_bay odbiorcy. Ta sama minuta nie generuje dwóch opłat z tej samej przyczyny. Płatnik przypisany do źródła opóźnienia.
- **Przykład:** Przyjazd 12:00, gotowość odbiorcy 14:15 → waiting_bay nalicza 15 min od 14:00; 30 min pracy załadunkowej nie mnoży tej opłaty.
- **Wpływ na balans:** Ogranicza nieprzewidywalny drenaż logistyki.
- **Testy graniczne:** 12:29/12:30/14:00/14:01, zmiana płatnika, retry.
- **Stan:** `proposed_pending_owner_approval`; dla niezatwierdzonej części `POLICY_NOT_APPROVED`.

## DOM13 — Przepustowość portu

- **Propozycja:** 500/1500/4000 bbl jest limitem na jeden logiczny slot 5 min na poziomie portu i tieru. Opóźniony worker przetwarza kolejno sloty, nie mnoży limitu przez liczbę uruchomień; idempotency port:slot:flow.
- **Przykład:** Tier 500, sloty 12:00 i 12:05 → max 1000 bbl łącznie; trzy retry pierwszego slotu nadal max 500.
- **Wpływ na balans:** Odcina exploit częstotliwości requestów; zwiększa kolejkę przy szczycie.
- **Testy graniczne:** Granica 5 min, 2 workerów, 0/500/501 bbl, restart po commit.
- **Stan:** `proposed_pending_owner_approval`; dla niezatwierdzonej części `POLICY_NOT_APPROVED`.

## DOM14 — Zmiana kierunku rurociągu

- **Propozycja:** Zmiana wymaga dwóch pełnych logicznych slotów 5 min liczonych od najbliższej granicy slotu; podczas switch zero przepływu; zlecenie i stan mają wersję.
- **Przykład:** Zlecenie 12:03 → sloty 12:05 i 12:10, nowy kierunek od 12:15; brak ładunku w przejściu.
- **Wpływ na balans:** Koszt alternatywny 10–12 min, zależny od chwili zlecenia.
- **Testy graniczne:** 12:00:00 i 12:04:59, dwa zlecenia odwrotne, awaria joba.
- **Stan:** `proposed_pending_owner_approval`; dla niezatwierdzonej części `POLICY_NOT_APPROVED`.

## DOM15 — Hazard logistyczny

- **Propozycja:** Ryzyko liczyć raz na kanoniczny logiczny slot 5 min, po ekspozycji. Prawdopodobieństwo p z kandydującego balansu; skumulowane P=1−(1−p)^n. RNG seed i wynik utrwalone na slot; retry bez nowego losowania.
- **Przykład:** Ilustracyjne p=1% i n=12 → P≈11,36% na godzinę; 1% nie jest propozycją aktywnej stawki.
- **Wpływ na balans:** Definicja czasu podnosi ryzyko długich przewozów; p wymaga symulacji.
- **Testy graniczne:** p=0/1, n=0/1, dwa workery, opóźniony slot, restart.
- **Stan:** `proposed_pending_owner_approval`; dla niezatwierdzonej części `POLICY_NOT_APPROVED`.

## Kontrakt decyzji i ślad audytowy

Właściciel **P05 Admin/Balance** przechowuje decyzję w istniejącym procesie `balance_versions` i historii zatwierdzeń: `dom_id`, `proposal_version`, `status` (`proposed`/`approved`/`rejected`), `approved_by`, `approved_at`, `effective_at`, `balance_version`, `yaml_sha256`, `simulator_run_id`. Jeśli obecny schemat P05 nie ma tych pól, P05 dodaje własną migrację; P14–P25 nie tworzą wtórnej tabeli polityk. Action czyta niezmienny opublikowany snapshot przed zmianą stanu i sprawdza jego wersję ponownie wewnątrz transakcji. Odrzucenie nie tworzy ukrytej wartości domyślnej. Zmiana zatwierdzonej reguły tworzy nową wersję, zachowuje historyczne snapshoty, przechodzi przez release P25.
