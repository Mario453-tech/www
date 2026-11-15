[KRYT] §11A.2 ↔ §7.3 — To samo pozwolenie P2 ma dwa terminy i zakresy: ~12 h dla hubu/rury oraz 7 dni dla rafinerii; różne są też reguły odmowy — Zastąp zapis §11A.2 odesłaniem do P2 z §7.3: 500 000 PLN, 7 dni, ponowny wniosek po 72 h.

[KRYT] §31.8 ↔ §31.3 — Schemat `refineries.status` nie dopuszcza `idle_standby`, choć wymagają go flara, strajk i test R3 — Dodaj `idle_standby` do stanów i jawne przejścia z/do `operational`.

[KRYT] §31.2.3 ↔ §31.8 — Kontrakt EPC wymaga `refineries.epc_contract_id`, ale pokazana tabela go nie zawiera — Dodaj nullable `epc_contract_id BIGINT UNSIGNED` oraz klucz obcy do `refinery_epc_contracts`.

[KRYT] §31.9 ↔ §23.3 — Rafineria księguje OPEX w fazie 2.9, podczas gdy kanoniczna faza finansowa to 5; grozi to inną kolejnością zajęcia wpływów i naliczenia kosztów — W 2.9 zapisz naliczenia, a FTS wykonaj w fazie 5.

[KRYT] §13.2 ↔ §11A.8 — Termin dostawy ma jednocześnie „ETA + 24 h” i twarde escrow 168 h; przy pełnej trasie i kolejce zabraknie czasu na reklamacyjne 12 h oraz rozliczenie T+12 h — Ustal pierwszeństwo: o 168 h autorozstrzygnięcie przerywa wszystkie pozostałe zegary, a dopuszczalny ETA ogranicz tak, by te okna się mieściły.

[KRYT] §13.2 — „Dowody sprzeczne → freeze ≤72 h, potem GM” może trzymać escrow po bezwzględnym limicie 168 h — Usuń przedłużenie: o 168 h domyślny refund, późniejszą decyzję GM rozlicz poza escrow.

[KRYT] §13.2 — `delivered` ma automat `receipt + 12 h`, lecz reklamacja jest dozwolona przez pełne 12 h; na granicy czasu wynik zależy od kolejności zadań — Przy `now = receipt + 12 h` najpierw rozpatrz reklamacje złożone do terminu włącznie, potem wykonaj settlement.

[KRYT] §13.2 — Przy częściowej utracie ładunku opis nakazuje jednocześnie częściowe `settled` i refund, ale lista stanów nie przewiduje obu zakończeń transzy — Dodaj wynik `resolved_split` z polami `paid_bbl`, `refunded_bbl`, `lost_bbl` i niezmiennikiem ich sumy równej wolumenowi transzy.

[KRYT] §13.2 ↔ §24.5 — Kara anulowania należna drugiej stronie nie ma reguły, co zrobić przy niewystarczającym BANK i CASH; nie wolno jej po cichu pobrać z escrow kupującego, gdy karę płaci sprzedawca — Ustal źródło BANK→CASH płatnika, a niedobór jako zobowiązanie 72 h; escrow zwróć niezależnie.

[KRYT] §31.7 ↔ §19.1 — Wycena rafinerii zawiera już współczynnik 70%, a podatek obejmuje 70% wyceny aktywów trwałych; nie wiadomo, czy naliczyć go dwa razy — Ustal: majątek netto = `CAPEX × (1 − wear/100)`, podstawa podatku = 70% tej wartości; albo nazwij wynik §31.7 wartością podatkową i nie dyskontuj ponownie.

[KRYT] §31.2 ↔ §31.8 — Zakup działki, pozwolenie, EPC, cztery transze, `commissioning` i `building_paused_funds` nie tworzą pełnej maszyny stanów; brak też stanu po wstrzymanej naprawie placu — Dodaj tabelę przejść `planning → building(stage 1…4) → commissioning → operational`, z pauzami finansową/awaryjną i warunkami wznowienia.

[KRYT] §31.2.3 — Zmiana EPC między etapami jest dopuszczona w danych, lecz brak reguły rozliczenia starego kontraktu, pozostałego CAPEX, gwarancji i limitu zdarzeń — Pozwól zmienić tylko przed opłaceniem kolejnego etapu; zamroź zapłacone transze i ich ryzyka, nowe licz wyłącznie dla pozostałych etapów.

[KRYT] §17.2 ↔ §31.1/§31.7 — Faza 3 opisuje licytację wyłącznie odwiertów, a §31 nakazuje w niej licytować także rafinerię — Dodaj rafinerie do Fazy 3 z kolejnością względem odwiertów oraz regułą przeniesienia działki, polisy, zapasów i kontraktów.

[KRYT] §19.2 ↔ §30.4 ↔ §29.6 — `dormant` oznacza brak gotówki, ban, anonimizację albo wyłączenie modułu; tylko pierwszy przypadek podaje warunek powrotu, a aukcja po 90 dniach obejmuje je zbiorczo — Dodaj `dormant_reason` i osobne zasady naliczania długu, wznowienia oraz aukcji dla każdej przyczyny.

[KRYT] §11A.1 ↔ §9.1/§19.2 — Pełny hub powoduje rozlanie, podczas gdy reguła offline mówi, że ropa nie wycieka i odwiert bezpiecznie staje — Ustal pierwszeństwo: przy braku wolnego miejsca w hubie zatrzymaj dopływ i uruchom kaskadę do `paused_storage`, bez `hub_overflow_spill` od zwykłego nasycenia.

[IST] §27.1a ↔ §1/§11A — Start zawiera ropę w buforze huba, ale nie przyznaje huba, dostępu do niego, pojazdu ani kierowcy potrzebnych do pierwszego kursu — Dopisz do pakietu konkretny hub NPC z opłaconym dostępem oraz pojazd z kierowcą albo jawnie zapewnij bezpłatny kurs tutorialowy.

[IST] §27.1a ↔ §9.1 — Standardowy bufor płytkiego odwiertu ma 500 bbl, lecz szkoleniowy opisuje „bufor huba” 800 bbl bez wskazania huba i właściciela partii — Zapisz 800 bbl w określonym hubie na koncie ropy nowego gracza i uwzględnij je w `oil_movements` oraz wycenie startera.

[IST] §27.1a ↔ §9.5/§7.3 — Budżet tygodnia używa koncesji 400 tys., wiercenia 1,2 mln przez 8–16 h i OPEX 12 PLN/bbl, choć domyślne P1 to 150 tys., shallow 800 tys./6 h i 30 PLN/bbl — Oznacz pozycje budżetu jako osobne ceny ofert tutorialowych albo przelicz budżet według kanonicznych stawek.

[IST] §27.1a — Deklaracja „7 000 bbl przy 40 bbl/h w ~175 h” zakłada ciągłą pracę; podany wcześniej wyczerpujący się odwiert nie może ominąć logistycznych przestojów — 7 000 ÷ 40 = 175 h tylko bez postojów; zapisz „co najmniej 175 h pracy”, bez obietnicy 7,3 dnia kalendarzowego.

[IST] §31.5 — Wynik klasy III i zwrot CAPEX są niespójne z przyjętą marżą 96 PLN/bbl — 1 000 × (96 − 16) − 21 000 = 59 000 PLN/h, nie 59 400; 240 000 000 ÷ (59 000 × 24) ≈ 169,5 dnia, nie 168. Popraw tabelę albo podaj dokładną marżę używaną do obliczeń.

[IST] §11A.6 — Próg 1 440 bbl/dobę nie wynika z podanych kosztów rury i cystern; przy nim zwrot nie nastąpi po 60 dniach — 600 000 ÷ ((4,00 − 1,90) × 1 440) ≈ 198,4 dnia; ~60 dni wymaga 600 000 ÷ (2,10 × 60) ≈ 4 762 bbl/dobę. Rozdziel próg wyboru środka od okresu zwrotu przy pełnej przepustowości.

[IST] §11A.4/§11A.6 — Podana cena cysterny standard 4,00 PLN/bbl nie wynika z kursu i przyjętej w tej samej sekcji straty rury 0,8% — 1 250 ÷ 500 + 250 × 0,008 = 4,50 PLN/bbl; określ osobne prawdopodobieństwo straty drogowej użyte do 4,00 albo popraw porównanie.

[IST] §11A.6 — Cysterna opancerzona za 8,10 PLN/bbl jest tańsza niż sam koszt pełnego kursu — 3 500 ÷ 350 = 10,00 PLN/bbl przed stratą; popraw minimum na 10,00 i dolicz oczekiwaną stratę według jawnego wzoru.

[IST] §31.5 — Prosta destylacja ma deklarowany kosz produktów ≈270 PLN/bbl przy mnożniku 0,92, lecz nie wszystkie produkty mają jakość 70–84 — suma bez mnożników: (28×355 + 26×370 + 6×395 + 32×205 + 4×210) ÷ 100 = 293,30; 293,30 × 0,92 = 269,84. Podaj jakość mazutu/LPG i zastosuj mnożnik per produkt, nie do całego kosza.

[IST] §31.5 — Kryzysowy spread 25 PLN/bbl daje zgodne straty tylko przy pełnej pracy, ale tekst nie określa, czy jest to spread przed kosztami zmiennymi — 400×(25−19)−8 500 = −6 100; 1 000×(25−16)−21 000 = −12 000 PLN/h. Dopisz wprost „spread przed OPEX” do tabeli kryzysowej.

[IST] §31.3 ↔ §31.8 — Receptury bilansują się, lecz schemat nie mówi, jak reprezentować straty bez `product_type` dla strat ani jak uniknąć podwójnego liczenia `storage_transfer` — A: 28+26+6+32+4+4=100; B: 42+30+14+6+4+4=100. Dodaj typ `process_loss` i bilansuj tylko ruchy graniczne procesu.

[IST] §31.3 — `processed_bbl_since_regen BIGINT` gubi ułamkowe bbl używane w całym bilansie; granica regeneracji może przesuwać się zależnie od zaokrąglenia — Zmień na `DECIMAL(16,4)` i wyzwalaj zadanie przy `>= 200000.0000`.

[IST] §31.3/§31.9 — Pełny zbiornik produktu uruchamia flarę, ale brak reguły wyboru, ile każdej frakcji spalić, a ile zostawić; nie da się utrzymać bilansu ani policzyć mandatu — Przed wsadem rezerwuj miejsce dla całej receptury; jeśli go brak, określ jawny wolumen każdej frakcji skierowanej na flarę i limit dwóch godzin.

[IST] §31.4 ↔ §24.5 — OPEX rafinerii pobierany jest wyłącznie z BANK, choć firma może mieć pieniądze w CASH; nie wiadomo, czy to celowy wyjątek od dopełniania płatności — Ustal BANK→CASH dla `REF_FIXED_OPEX` i `REF_PROCESS_OPEX` albo zapisz, że gotówkę trzeba najpierw bezpłatnie wpłacić C2B.

[IST] §31.6 — Składka, suma ubezpieczenia i definicja szkody nie mają wzorów; tabela ma pola, lecz nie ma źródła ich wartości — Zdefiniuj w `game_balance` wzór `daily_premium` oraz `coverage_amount`, a wypłatę jako `min(85% × uznana_szkoda, coverage_amount, wolny_limit_dobowy, saldo_puli)`.

[IST] §31.5/§31.6 — Klasa produktu 92–100 jest jednocześnie „specyfikacją lotniczą”, lecz RARS przyjmuje od 90; §13.2 wymienia próg 92 bez rozróżnienia rynku — Zapisz osobne warunki: lotniczy `>=92`, RARS `>=90`, i nie nazywaj partii 90–91 lotniczą.

[IST] §14.3 ↔ §2.2 — Włączenie 2FA zmienia `company_credibility` o +5, a ogólna reguła propaguje każdą taką zmianę w 50% do B2B; §14.3 wyklucza ten wpływ — Dodaj wyraźny wyjątek propagacji dla `TWOFA_BONUS` i jego cofnięcia.

[IST] §5 ↔ §4.3 — §5 każe obniżyć morale zespołu po zwolnieniu o −10, podczas gdy §4.3 ustala karę −8/os. przez 14 dni — Zastąp −10 w §5 odwołaniem do −8/os., maksymalnie −24, przez 14 dni.

[IST] §5.1 ↔ §11A.6 — Strajk logistyki podnosi koszty cystern o 20%, ale wzór kanoniczny daje `1+0,25u` (od 0% do 25%) — Ustal jeden mnożnik: np. ×1,20 przy aktywnym strajku, niezależnie od udziału, i popraw §11A.6.

[IST] §11A.7 ↔ §7.2 — Kanoniczne `hub_security` „blokuje” sabotaż zaworów, lecz wiążący mnożnik ryzyka ×0,4 tylko go ogranicza — Zdefiniuj blokadę jako odrzucenie operacji `hub_valve_sabotage` przed pobraniem opłaty; ×0,4 stosuj wyłącznie do niezależnych awarii sprzętu.

[IST] §11A.7 ↔ §7.2 — `drone_patrol` dopuszcza hub jako cel, mimo że kanoniczna lista zakupów obejmuje wyłącznie korytarz trasy albo odcinek rurociągu — Usuń „hub” z celu drona; ochronę huba pozostaw `hub_security`.

[IST] §9.1 ↔ §11A.1 — Kaskada nazywa pełny odwiert `choked`, lecz nie ma go w stanach `wells` ani w widoku GM §18.1 — Dodaj `choked` do enumu i schematu albo użyj wszędzie istniejącego `paused_storage`.

[IST] §11A.8 ↔ §11A.4/§21 — Kurs podczas kredytu używa stanów `kredytowany` i `waiting_bay`, których nie ma na liście stanów kursu; holowanie po 48 h też nie ma stanu końcowego — Dodaj jawne przejścia `w_trasie → waiting_bay → kredytowany → dostarczony` oraz `waiting_bay → odholowany` z zachowaniem właściciela ładunku.

[IST] §19.1.1 ↔ §22.6.1 — Jeden snapshot ma zasilać podatek, tarczę i Tier, lecz zapisane pola obejmują majątek netto i Tier, nie podstawę podatku ani wyłączenia escrow/line-pack — Dodaj `tax_base_grosze`, `shield_base_grosze` i wersję wyceny zapasów do niezmiennego snapshotu.

[IST] §19.2 ↔ §13.2 — Urlop blokuje `accepted` i nieistniejący stan `in_delivery`, lecz B2B używa `in_transit`, `delivered`, `disputed` i stanów transz — Zablokuj urlop dla wszystkich nierozliczonych transz z escrow, od `accepted` do stanu końcowego.

[IST] §22.6 ↔ §22.6.1 — Tier V ma zakres `>1B`, a Tier IV kończy się na `1B`: dokładnie 1 mld należy do IV, lecz granice 10M/50M/200M są zapisane po obu stronach — Ustal przedziały półotwarte: I `<10M`, II `[10M,50M)`, III `[50M,200M)`, IV `[200M,1B]`, V `>1B`.

[IST] §22.6.1 ↔ §24.3 — „1 wymiana na 24h” została zaimplementowana jako jedna na datę UTC; dwie wymiany tuż przed i po północy mogą dzielić mniej niż 24 h — Nazwij limit „jedna na dobę UTC” w obu sekcjach albo wymagaj również `now >= last_conversion_at + 24h`.
