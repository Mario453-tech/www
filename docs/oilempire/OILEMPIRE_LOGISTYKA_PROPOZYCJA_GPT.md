CZĘŚĆ 1 — SEKCJA DO SPECYFIKACJI OILEMPIRE

## Logistyka ropy [LOG-01–LOG-16]

### 1. Zasada przepływu i jednostki [LOG-01]

Ropa jest zasobem fizycznym. Wydobycie zwiększa wyłącznie bufor odwiertu; nie zwiększa magazynu głównego. Do sprzedaży spot i rezerwacji B2B kwalifikuje się dopiero ropa przyjęta do magazynu głównego, z wyjątkiem jawnie zarezerwowanych dostaw B2B ze wskazanego huba.

Dozwolone łańcuchy:

| Źródło | Odcinek 1 | Przeładunek | Odcinek 2 | Cel |
|---|---|---|---|---|
| Odwiert lądowy | Rurociąg albo cysterna | Hub | Rurociąg albo cysterna | Magazyn główny |
| Odwiert morski | Tankowiec przez port | Hub | Rurociąg albo cysterna | Magazyn główny |

Hub jest obowiązkowy. Port jest punktem obsługi rejsu, a nie zamiennikiem huba. Nie istnieje automatyczny transfer „odwiert → magazyn” ani „port → magazyn”.

Wolumen zapisuje się jako całkowitą liczbę jednostek `oil_units`, gdzie 10 000 jednostek = 1 bbl. Pieniądz zapisuje się wyłącznie jako `BIGINT amount_grosze`. Formuły procentowe i stawki mogą być przechowywane jako skalowane liczby całkowite; zaokrąglenie następuje raz na operację, z przenoszeniem reszty, nie osobno dla każdej jednostki ropy. Każde przesunięcie tworzy niezmienny zapis w `oil_movements`: `source_type/id`, `destination_type/id`, `quantity_units`, `reason_code`, `tick_no`, `idempotency_key`. Obowiązuje bilans: stan początkowy + wydobycie − sprzedaż − udokumentowane straty = stan końcowy we wszystkich buforach, kolejkach, ładunkach i magazynach.

### 2. Huby [LOG-02]

`logistics_hubs` mają `owner_type` (`npc|player`), `owner_player_id` nullable, `tenant_player_id` nullable, `region_id`, `status`, `work_mode`, `level`, `condition_bp`, `buffer_capacity_units`, `throughput_units_per_hour` i `slot_limit`. Ropa w hubie należy do dostarczającego gracza, nie automatycznie do właściciela obiektu. Wspólny hub NPC prowadzi osobne salda partii per gracz; wynajęcie może rezerwować część slotów i przepustowości. Własny hub jest aktywem firmy; najem daje czasowe prawo używania i obsady, lecz nie prawo sprzedaży ani trwałej rozbudowy.

| Rodzaj korzystania | Nabycie | Koszty i uprawnienia |
|---|---|---|
| Dostęp do huba NPC | Opłata za przypisanie odwiertu | Opłata za obsługę bbl i ewentualna rezerwacja slotu; naprawy finansuje operator NPC |
| Najem huba | Kaucja i umowa z terminem | Czynsz za czas i obsługa; najemca ustawia tryb i obsadę w granicach umowy; właściciel odpowiada za konstrukcję |
| Hub własny | Budowa lub zakup istniejącego | CAPEX, OPEX, obsada, naprawy; właściciel ustawia tryb, rozbudowuje i może wynajmować |

Rozróżnienie `new|used|rental` opisuje ofertę nabycia, nie zastępuje własności. Hub używany ma jawnie pokazaną kondycję i wycenę; „rental” jest umową, nie typem technicznym huba. Zakup od NPC lub gracza (`hub_acquisitions`) blokuje ofertę i hub, sprawdza pozwolenie oraz istniejący najem, księguje FTS i przenosi własność w jednej transakcji. Aktywny najem i ropa osób trzecich pozostają chronione warunkami umowy; nie wolno ich wyzerować przy przejęciu. Sprzedaż huba z ładunkiem wymaga przejęcia obowiązków operatora albo wcześniejszego opróżnienia.

Propozycja poziomu 1 do `game_balance`; wartości są startowe do testów symulacyjnych, nie odziedziczone z legacy:

| Wielkość huba | Przepustowość | Bufor | Sloty | Budowa | Bazowy OPEX |
|---|---:|---:|---:|---:|---:|
| Mały | 120 bbl/h | 500 bbl | 2 | 120 000 PLN | 120 PLN/h |
| Średni | 400 bbl/h | 2 000 bbl | 6 | 450 000 PLN | 320 PLN/h |
| Duży | 1 200 bbl/h | 8 000 bbl | 16 | 1 400 000 PLN | 850 PLN/h |

Poziomy 2 i 3 zwiększają przepustowość i bufor odpowiednio ×1,5 i ×2 względem poziomu 1; koszt ulepszenia wynosi odpowiednio 60% i 100% kosztu budowy poziomu 1, czas 4 h i 8 h. Rozbudowa wymaga pozwolenia na prace lokalne, wolnego miejsca na mapie i nie może kasować zawartości bufora.

| Tryb | Przepustowość | OPEX | Zużycie | Ryzyko awarii |
|---|---:|---:|---:|---:|
| `normal` | ×1,00 | ×1,00 | ×1,00 | ×1,00 |
| `turbo` | ×1,35 | ×1,60 | ×1,80 | ×1,50 |

Efektywna przepustowość w ticku = `nominalna_bbl/h × Δt_h × mnożnik_trybu × mnożnik_kondycji × mnożnik_obsady × mnożnik_budżetu × mnożnik_strajku`. Wynik ograniczają wolny bufor, zdolność rozładunku i rezerwacje innych graczy. Wydajność i obsada nigdy nie wytwarzają ropy.

Najpierw przyjmuje się fizycznie przybyłe partie do wolnego bufora, potem przetwarza partie w kolejności FIFO z gwarantowanym udziałem przepustowości wynikającym z rezerwacji slotów. Nadwyżka pozostaje w cysternie, tankowcu lub buforze źródła jako `waiting_bay`; nie ginie automatycznie. `overflow_lost` występuje wyłącznie przy udokumentowanym incydencie, np. pęknięciu zbiornika, i tworzy `oil_movement` ze wskazaniem straty. Pełny magazyn zatrzymuje wysyłkę z huba; ropa pozostaje w buforze huba (`paused_storage`), a przy pełnym hubie w źródle lub pojeździe.

Obsada: `hub_operator` jest wyłącznie pracownikiem technicznym; technicy utrzymania są przypisani do konkretnego huba. Suma alokacji pracownika na wszystkie cele ≤100%; brak obsady obniża przepustowość do 70% i podnosi bazowe ryzyko ×1,5. Pełna obsada przywraca 100%; premie za skill działają tylko na przypisany obiekt. Przy hubie NPC obsadę bazową zapewnia NPC, a gracz nie przypisuje tam swoich ludzi bez odrębnej umowy. Utrzymanie i naprawy są zadaniami TTS: konserwacja hamuje dalsze zużycie, naprawa przywraca kondycję; nie są tym samym przyciskiem.

### 3. Rurociągi [LOG-03]

Każdy rurociąg ma dokładnie jeden skierowany odcinek: `inbound` (konkretny odwiert lądowy → konkretny hub) albo `outbound` (konkretny hub → konkretny magazyn). Model `pipeline_segments` używa rzeczywistych `origin_type/id` i `destination_type/id`, bez `well_id=0`. Jeden aktywny odcinek danej trasy na właściciela w MVP; osobne wiersze i obsada dla wejścia i wyjścia. Rurociąg nie działa bez aktywnego, zgodnego geograficznie huba i pozwolenia Poziomu 2.

Budowa: wniosek o pozwolenie → opłata w FTS → `permitted` → rezerwacja trasy i kosztu → `building` z `started_at`, `finish_at`, `balance_version` → `active` po upływie czasu w fazie 2. Odcinki mogą mieć długość i przeszkody terenowe; koszt = stały koszt przyłącza + `długość_km × stawka_typu × mnożnik_regionu`. Zmiana huba nie przenosi automatycznie starego rurociągu. Podczas budowy można wybrać drogę jako trasę zastępczą, jeżeli istnieje wolna cysterna.

| Typ | Przepustowość | Przyłącze | Budowa/km | Czas bazowy + /km | OPEX | Strata bazowa |
|---|---:|---:|---:|---:|---:|---:|
| `light` | 60 bbl/h | 11 000 PLN | 3 000 PLN | 4 h + 15 min | 95 PLN/h + 0,25 PLN/bbl | 0,5% |
| `standard` | 180 bbl/h | 18 000 PLN | 5 000 PLN | 8 h + 20 min | 140 PLN/h + 0,20 PLN/bbl | 0,3% |
| `heavy` | 500 bbl/h | 28 000 PLN | 9 000 PLN | 16 h + 25 min | 190 PLN/h + 0,15 PLN/bbl | 0,2% |

Stawki nie sumują się z ogólnym „kosztem rurociągu” drugi raz. Przesył jest fizyczny: przyjęty wolumen pozostaje w `pipeline_inflight` przez czas `max(1 tick, ceil(długość_km / 60 km/h))`; po dojściu czeka na miejsce odbioru. Wyciek obniża tylko wolumen aktualnie tłoczony lub znajdujący się w danym odcinku, nie stan magazynu. Dla wolumenu `q`: `loss_units = round_half_even(q × loss_bp / 10 000)`; `out_units = q − loss_units`. Bazowe straty, skutki wycieku i rozlanie awaryjne są odrębnymi przyczynami, stosowanymi raz. `condition_bp` i reszta zużycia mają precyzję pozwalającą naliczyć zużycie przy ticku 60 s. Inspekcja TTS wykrywa ukryty wyciek i ogranicza ryzyko; naprawa blokuje odcinek na zadany czas. `pipeline_engineer` obniża zużycie i ryzyko wyłącznie przypisanego odcinka; specjalista logistyki może zmniejszać jego straty. Przy `damaged` lub `leak` gracz jawnie wybiera bezpieczną trasę zastępczą; system nie uruchamia nieistniejących ciężarówek.

### 4. Cysterny i trasy drogowe [LOG-04]

Flota jest skończonym zasobem: `vehicles` określa właściciela albo umowę najmu, pojemność, lokalizację, stan, dostępność i obsadę kierowcy. Zakup zwiększa aktywa; najem rezerwuje konkretny pojazd na czas umowy oraz nalicza czynsz. Pojazd nie może równocześnie obsługiwać dwóch kursów. Po dostawie odbywa kurs powrotny albo pozostaje w miejscu docelowym; czas powrotu wpływa na dostępność.

| Cysterna | Pojemność | Zakup | Najem | Prędkość | Koszt stały kursu | Zmienny |
|---|---:|---:|---:|---:|---:|---:|
| Standard | 25 bbl | 35 000 PLN | 25 PLN/h | 50 km/h | 90 PLN | 2 PLN/km |
| Ciężka | 50 bbl | 65 000 PLN | 45 PLN/h | 40 km/h | 140 PLN | 3 PLN/km |
| Opancerzona | 20 bbl | 100 000 PLN | 70 PLN/h | 45 km/h | 220 PLN | 4 PLN/km |

`czas_kursu = załadunek + ceil(dystans/prędkość) + rozładunek`; przyjąć po 5 min na załadunek i rozładunek, minimum jeden tick na fizyczny przejazd. Koszt kursu = opłata stała + paliwo/dystans + kierowca za czas + opłaty drogi + ochrona + oczekiwanie; powrót, jeśli konieczny, jest uwzględniony w ofercie, nie „darmowy”. W tabeli stawka zmienna obejmuje paliwo i opłaty drogi; pensja kierowcy i ochrona są osobnymi pozycjami. Opłata za kurs obciąża również kurs utracony.

Gracz wybiera dopuszczalną trasę i priorytet dla obu odcinków: `manual`, `cheapest`, `fastest`, `safest`. Silnik wyznacza trasę po grafie dróg z ograniczeniami regionów i statusów. Zmiana trasy w locie nie teleportuje ładunku: dotyczy przyszłych kursów lub tworzy jawne przekierowanie z nowym ETA i kosztem. Kurs ręczny jest dostępny każdemu graczowi. Automatyczna pętla VIP (§22.6) tylko planuje następne kursy na tych samych zasadach pojemności, kosztu i ryzyka; nie zwiększa fizycznej floty. Po końcu VIP już rozpoczęty kurs się kończy, ale nowy kurs automatyczny nie powstaje. Kursy oczekujące na zatokę są `waiting_bay` z opłatą postojową według §21; opłata nie jest naliczana równolegle przez dwa moduły.

### 5. Morze i porty [LOG-05]

Dla odwiertu morskiego pierwszy odcinek prowadzi tankowcem do portu obsługującego wybrany hub. Port ma przepustowość rozładunku na godzinę, limit miejsc i kolejkę FIFO z priorytetem opłaconej rezerwacji; `closed` wymaga jawnej naprawy lub decyzji operatora, a nie samoczynnego wznowienia po ustąpieniu przeciążenia. Ropa pozostaje przypisana do partii i właściciela podczas rejsu, postoju w porcie i przeładunku.

| Jednostka | Ładowność | Najem za rejs | Czas bazowy rejsu | Koszt obsługi portowej | Minimalny załadunek |
|---|---:|---:|---:|---:|---:|
| Mała | 1 000 bbl | 12 000 PLN | 3 h + dystans/25 km/h | 1 PLN/bbl | 500 bbl |
| Średnia | 5 000 bbl | 35 000 PLN | 4 h + dystans/30 km/h | 0,8 PLN/bbl | 2 500 bbl |
| Duża | 20 000 bbl | 100 000 PLN | 6 h + dystans/32 km/h | 0,6 PLN/bbl | 10 000 bbl |

Tankowiec można kupić albo wynająć; zakup wymaga osobnego CAPEX w `game_balance`, a podane stawki najmu nie są ceną zakupu. Przy nieosiągniętym minimum ropa czeka w ograniczonym buforze morskim odwiertu. Gracz może zlecić niepełny rejs ręcznie, płacąc pełną stawkę. Opóźnienie i częściowa albo pełna utrata dotyczą wskazanej partii, nie globalnego salda. Morze jest właściwą drogą dla offshore i dużych partii; na połączeniach lądowych porównanie opłacalności uwzględnia dwa przeładunki, kolejkę portową i czas, więc nie jest domyślnym skrótem względem drogi czy rurociągu.

### 6. Budżet działu, strajk i ekonomia [LOG-06]

Wszystkie liczby powyżej są propozycją wersji startowej `game_balance`, zapisywanej wraz z `balance_version`. Koszt OPEX = koszt czasu aktywnych hubów, rurociągów, flot i portowych rezerwacji + koszt wolumenu/kursów + płace i czynsze, przy czym każda pozycja ma jednego właściciela naliczenia. OPEX czasu wynosi `stawka_grosze/h × Δt_h`, z przeniesieniem reszty grosza. CAPEX, pozwolenia, kary, naprawy i ochrona są osobnymi `reason_code`; koszt utraconej ropy jest utraconym aktywem, nie drugi raz wydatkiem pieniężnym.

| Budżet logistyki §10 | Przepustowość obsługowa | Koszt bazowego utrzymania | Ryzyko awarii |
|---|---:|---:|---:|
| `low` | ×0,85 | ×0,80 | ×1,25 |
| `standard` | ×1,00 | ×1,00 | ×1,00 |
| `high` | ×1,10 | ×1,25 | ×0,85 |

Budżet nie obniża należnych pensji, czynszu ani opłat za już wykonane kursy. Brak środków wstrzymuje nowy odpłatny dispatch i tworzy zaległość zgodnie z ogólną polityką długu, nie usuwa ładunków w drodze.

Dla udziału strajkujących `u ∈ [0,1]` spadek przepustowości logistyki wynosi `30% + 50% × u`; mnożnik przepustowości wynosi `1 − (0,30 + 0,50u)`. Przy `u=0,60` wynosi 0,40. Reguła obowiązuje podczas aktywnego strajku; dla `u=0` bez strajku wynosi 1, nie 0,70. Gdy `u > 0,60`, główna trasa firmy nie przyjmuje nowych wysyłek (`main_route_stopped`); istniejące kursy kończą się lub oczekują według fizycznego statusu celu. „Trasa główna” to jawnie wskazana `route_id`; pozostałe trasy podlegają zwykłemu mnożnikowi. Koszty stałe trwają podczas strajku.

### 7. Faza 2 ticka i maszyny stanów [LOG-07–LOG-11]

Faza 2 (§23.2) działa na migawce czynników po fazie 1. W ramach transakcji gracza:

1. Zakończ budowy, przepięcia, inspekcje i naprawy, których termin upłynął przed początkiem bieżącego ticka. Nowy stan może obsłużyć tylko pozostały rzeczywisty czas `Δt`; faza 7 nie działa wstecz.
2. Zablokuj w stałej kolejności aktywne trasy, bufory, pojazdy i odbiorców. Ustal przepustowości, obsadę, budżet, strajk i ochronę z wersji balansu właściwej dla procesu.
3. Rozlicz dojrzałe przyjazdy drogowe i morskie; zastosuj raz incydent przypisany do kursu; umieść ocalały ładunek w zatoce albo buforze portu/huba. Kolejka portowa i `waiting_bay` są obsługiwane FIFO z ograniczeniem przepustowości.
4. Przetwórz wejście huba i jego stary bufor, zaksięguj rzeczywiste straty, następnie uruchom przepływ outbound. Nie zwiększaj magazynu przy samym wyjeździe z huba.
5. Rozlicz dojrzałe przyjazdy outbound i przyjmij tyle, ile mieści magazyn; reszta pozostaje w pojeździe, buforze lub `paused_storage`.
6. W granicach wolnego zasobu uruchom nowe transporty inbound i outbound. Flota, miejsce w buforach i ropa są rezerwowane atomowo. Wylicz nowe ETA; kurs wysłany w tym ticku nie „dociera natychmiast”.
7. Zapisz `oil_movements`, wyniki odcinków, stany i zobowiązania kosztowe z unikalnymi kluczami `tick_no|phase|entity_id|operation`. Faza 5 księguje finansowe skutki na `PLN_CASH`/`PLN_BANK` i odpowiednich kontach systemowych FTS dokładnie raz. Emituj powiadomienia dopiero `after_commit`.

Kolejność „przyjazdy → hub → wyjazdy” pozwala ropie przybyłej na początku ticka opuścić hub jeszcze w tym ticku, ale każdy nowy transport zachowuje fizyczny czas przejazdu. Nie uruchamia się osobnego naliczania ekonomii w `HubTickService` i ponownie w fazie 5.

| Byt | Dozwolone przejścia |
|---|---|
| Przypisanie odwiertu | `unassigned → relinking → active → detaching → unassigned`; przepięcie rezerwuje nowy slot, blokuje wysyłkę z odwiertu i zachowuje OPEX; porzucone ładunki jadą do starego celu albo są jawnie przekierowane |
| Hub | `planned → building → active ↔ maintenance`; `active → damaged → maintenance → active`; `active → paused → active`; `disabled` wymaga jawnej reaktywacji |
| Rurociąg | `planned → permitted → building → active ↔ degraded ↔ critical`; `active|degraded|critical → leak|damaged → repairing → active`; `active → suspended → active`; `disabled` nie wznawia się przełącznikiem `suspended` |
| Kurs drogowy | `queued → loading → in_transit → waiting_bay → unloading → delivered`; `in_transit → delayed → in_transit`; `in_transit → lost`; `unloading → paused_storage → unloading` |
| Rejs morski | `queued → departing → in_transit → waiting_for_port → processing → delivered_to_hub`; możliwe `delayed`, `partially_lost`, `lost` z zachowaniem historii i wolumenu |
| Partia magazynowa | `at_well → reserved_inbound → at_hub → reserved_outbound → at_storage → reserved_sale|reserved_b2b → consumed`; strata kończy część partii w `lost` z przyczyną |

Wszystkie terminalne przejścia są jednokrotne; status „dostarczono” nie może poprzedzać zapisu odbiorcy. Restart workera po części fazy wznawia niezakończone operacje po kluczu idempotencji. Samo `simulation_heartbeats.status=done` nie zastępuje idempotencji per gracz i per partia.

### 8. B2B, incydenty i ochrona [LOG-12–LOG-14]

Dostawa B2B jest realnym transportem, nie samym timerem. Po zawarciu kontraktu blokuje się konkretną ilość ropy w magazynie lub hubie, finansuje escrow na `PLN_ESCROW` i tworzy zlecenie wysyłki. Timer kontraktu wyznacza termin, ale `delivered` następuje dopiero po fizycznym przyjęciu przez odbiorcę i atomowym przeniesieniu własności partii. Brak trasy, utrata lub kolejka mogą spowodować spóźnienie i uruchomić reguły sporu §13.2. Nie wolno równocześnie sprzedać tej samej partii na spot, limicie i B2B.

Rzuty incydentów używają podkluczy RNG §23.4 per `tick_no`, `entity_id` i `incident_type`. Incydent fazy 7 zmienia ryzyko i stan na następny tick; incydent związany z rozliczeniem konkretnego kursu w fazie 2 jest jego jednokrotnym wynikiem, utrwalonym przy partii. `road_ambush` może zatrzymać i utracić cały pojedynczy kurs; `road_partial_theft` zabiera określoną część pojedynczego kursu. Nie należy stosować legacy zasady „każda kradzież = cały kurs” do nowego typu częściowej kradzieży. `hub_valve_sabotage` może ograniczyć przepustowość albo wywołać wyciek ze wskazanej partii w hubie. Inspekcja i `drone_patrol` zmniejszają wyłącznie przypisane prawdopodobieństwa, nie usuwają ryzyka wypadku czy złej pogody; aktywna ochrona ma właściciela celu i okres ważności. Ograniczenia sabotażu §7, w tym limit 5 skutecznych/7 dni na ofiarę, sprawdza się atomowo przed zapisaniem skutku. Nie istnieje „darmowa” awaria: straty, naprawa i przestój mają jednoznaczny koszt albo skutek.

### 9. Dane, widoki i kryteria odbioru [LOG-15–LOG-16]

Moduł Laravel 11 `Logistics` obejmuje serwisy domenowe, maszyny stanów, migracje i job fazy 2; nie wykonuje DDL przy konstruowaniu serwisu ani podczas ticka. Indeksy obejmują co najmniej `(player_id,status,eta_at)` dla kursów, `(hub_id,status,queued_at)` dla zatok, `(player_id,origin_type,origin_id,status)` dla partii, unikalność aktywnego przypisania odwiertu oraz unikalny `idempotency_key` ruchu ropy. Statusy są `VARCHAR` z backed enum PHP 8.3; czas `DATETIME(3)` UTC.

Gracz widzi dwie osobne nogi trasy, realne ETA, bufory i przyczyny blokady, koszt przed potwierdzeniem, incydenty oraz bilans dobowy bbl i kosztów. Panel GM pokazuje te same partie i może wymusić incydent lub korektę z audytem i powodem; nie usuwa aktywnych kursów. Powiadomienia są deduplikowane i odnoszą się do konkretnego odcinka lub ładunku.

Release blokują testy: bilans ropy przez oba odcinki; pełny magazyn i pełny hub; dwa równoległe dispatch’e jednej partii; zakup rurociągu równolegle z przepięciem; koniec VIP podczas kursu; strajk przy `u=0,60` i `u>0,60`; sabotaż częściowy i całkowity; wznowienie po awarii workera; aktualizacja balansu podczas rejsu; B2B kontra spot i limit; brak podwójnego OPEX i podwójnej opłaty portowej. Headless symulator musi sprawdzić także przepustowość i rentowność transportu przy cenie startowej 250 PLN/bbl.

CZĘŚĆ 2 — AUDYT DOSTARCZONEGO LEGACY

Zakres ustaleń: poniżej „potwierdzone” oznacza widoczne bezpośrednio w załączonym kodzie lub SQL. Wpisy `GAME_README.md` są dowodem historii napraw według dokumentacji, nie dowodem, że niewidoczna bieżąca wersja całego repozytorium nadal ma dany błąd.

### A. Co przenieść jako zasadę, bez kopiowania implementacji

1. Obowiązkowe dwa odcinki przez hub i fizyczny moment dotarcia ropy — `svn_repo/LOGISTICS_HUBS_README_UJEDNOLICONE_FINAL.md`, rozdziały 2, 9–10; `BRIEF_TESTERZY_LOGISTYKA.md`. To właściwa reguła gospodarcza. Nie przenosić `HubTickService::applyFallback()`, jeśli pozwala ominąć hub.
2. Dopuszczalność huba według geografii, slotów i stanu oraz koszt i przestój podczas przepięcia — `LOGISTICS_HUBS_README_UJEDNOLICONE_FINAL.md`, rozdziały 4–5; `HubAssignmentService::transferWell()` opisany w `OSTATECZNA_WERSJA_HUB_RUROCIAG.md`. Zachować zachowanie gracza, ale zamienić prosty test `same region_id` na graf połączeń dopuszczający hub graniczny.
3. Czas budowy i niezależność wyboru transportu od przyznania rurociągu — `OSTATECZNA_WERSJA_HUB_RUROCIAG.md`, `WellPipelineService::completeBuildingPipelines()` oraz decyzja admina w `LOGISTICS_HUBS_README_UJEDNOLICONE_FINAL.md`. Adminowy grant aktywa nie powinien sam zmieniać aktywnej trasy.
4. Odrębne koszty i ryzyko każdego kursu, brak przychodu z niedostarczonej ropy — `src/RoadTransportService.php`, `processCompletedTrips()`; projekt rejsów w `migrations/etap5_marine_ports.sql`. Zachować pełną utratę dla napadu, lecz nie dla nowego `road_partial_theft`.
5. Wspólny limit alokacji pracownika do 100%, obsada per konkretny hub/rurociąg oraz `hub_operator` jako specjalizacja techniczna — wpisy `GAME_README.md` z 18–19 lipca i `migrations/2026-07-18_hub_operator_technical_migration.sql`. Nie kopiować destrukcyjnej metody migracji pracowników.
6. Zachowanie historii utraconych transportów zamiast `DELETE` i bez zerowania buforów — `GAME_README.md`, 9 lipca. W zero-wipe jest to obowiązek, nie tylko funkcja panelu.
7. Finalny filtr własności i stanu w mutacji, a nie tylko w poprzedzającym ją `SELECT` — `GAME_README.md`, audyt izolacji z 16 lipca i naprawy obsady z 19 lipca. To zasada wszystkich komend domenowych.

### B. Co dopracować

1. Własność hubów jest sprzeczna między dokumentami. `LOGISTICS_HUBS_README_UJEDNOLICONE_FINAL.md` i początek `OSTATECZNA_WERSJA_HUB_RUROCIAG.md` wykluczają prywatne huby; sekcja 7 tego drugiego pliku żąda ich wdrożenia; `migrations/etap9_hub_ownership.sql` dodaje `tenant_player_id` i prywatnego właściciela, a `GAME_README.md` z 22 lipca opisuje sterowanie trybem przez właściciela lub najemcę. Dla OilEmpire przyjąłem jawne trzy modele: NPC, własność, najem. Trzeba usunąć stare twierdzenie „hub zawsze systemowy” z nowej specyfikacji.
2. `migrations/etap10_transport_leg2.sql` lokuje wybór outbound w `wells.hub_outbound_transport_type`, a `etap11_hub_outbound_pipeline.sql` przenosi go do `logistics_hubs.outbound_transport_type`. Przy wspólnym hubie NPC ustawienie na całym hubie daje jednemu graczowi kontrolę nad trasą innych. Nowy wybór powinien należeć do trasy/rezerwacji per gracz i cel, z fizycznie współdzieloną przepustowością huba.
3. `README_rurociag.md` mówi o automatycznym przejściu na drogę po wyłączeniu rurociągu; `GAME_README.md` z 3 lipca odnotowuje naprawę sytuacji, w której uszkodzenie inbound zatrzymywało produkcję zamiast „cicho” uruchamiać ciężarówki. Zasada docelowa: tylko jawna, dostępna trasa zastępcza, bez darmowej floty.
4. `GAME_README.md` z 6–7 czerwca dokumentuje brakujące dane morskie w widoku, mikrorejsy co tick, dopisywanie ropy do huba przed dotarciem i brak pełnego drugiego odcinka. Trzeba zbudować jeden model partii/ładunku; nie naprawiać tych problemów przez kolejne niezależne liczniki w `wells`, `marine_deliveries`, `port_queue` i pamięci ticka.
5. Zezwolenie z `migrations/etap13_hub_permit_schema.sql` jest per `(player_id,region_id)` i ma domyślnie `hub_permit_enabled=0`; nie modeluje jednoznacznie wymaganego w nowej specyfikacji pozwolenia Poziomu 2 na konkretną budowę rurociągu. Trzeba określić zakres, ważność i przypadek odmowy pozwolenia przed naliczeniem CAPEX.

### C. Bugi, luki i przyczyny źródłowe

| Źródło | Ustalenie | Skutek i zabezpieczenie w OilEmpire |
|---|---|---|
| `src/HubTickService.php::processTick()` | Potwierdzone: `processed = min(inputBbl, tickCapacityBbl)` nie uwzględnia wolnej pojemności magazynu ani gotowości outbound; zwraca wolumen „processed” zanim ostatni odcinek faktycznie dostarczy ropę. | Nie kredytować magazynu z `processed_bbl`; to tylko wynik pracy huba. Prowadzić partie w buforze i oddzielny odbiór outbound. Historia napraw z 7 czerwca i 15 lipca wskazuje, że pomylenie tych wielkości powodowało utratę lub podwójne ujęcie ropy. |
| `src/HubTickService.php::processTick()` | Potwierdzone: gdy hub ma status `paused`, `building` itd., `buildEmptyResult($hub,$inputBbl,...)` otrzymuje wejście, lecz pokazany kod nie dowodzi, gdzie ono zostaje zachowane. `applyFallback()` zwraca wolumen „effective” bez huba. | Odrzucić lub zatrzymać dostawę w źródle/zatoce; zakazać ścieżki bez huba. Bez kodu `buildEmptyResult()` nie można twierdzić, że samo wejście jest już gubione. |
| `src/HubTickService.php::processTick()` | Potwierdzone: zużycie zależy od `time()` i `strtotime(last_maintenance_at)`, nie od czasu/seed ticka; kondycja końcowa jest zaokrąglana do dwóch miejsc. | Replay i nadrabianie ticków mogą dać różne wyniki; przy 60 s małe zużycie może znikać w zaokrągleniu. Przekazywać `tick_started_at` i akumulator zużycia o większej precyzji. |
| `src/HubEconomyService.php::getBuildSummary()` i `getOpex()` | Potwierdzone niespójne nazwy jednostek: `opex_per_tick` jest traktowane jako PLN/h (`opex_per_day = ×24`), choć tick OilEmpire trwa minutę. `getViabilityRatio()` normalizuje statystyki jednym przekazanym `deltaHours` mimo potencjalnie różnych interwałów statystyk. | Stawki wyłącznie per godzinę i rzeczywiste `Δt` per rekord; nie kopiować mnożnika „na tick”. Wskaźnik rentowności liczyć z faktycznych dostaw, a nie przychodu hipotetycznego `processed × oilPrice`. |
| `migrations/etap11_hub_outbound_pipeline.sql` | Potwierdzone: `MAX(w.hub_outbound_transport_type)` rozstrzyga konflikt decyzji kilku odwiertów arbitralnie według porządku tekstu; `DELETE` duplikatów usuwa aktywa/historię; przy identycznym `condition_pct` pozostawia remis, po czym unikalny indeks może nie wejść. `well_id=0` i `hub_id=0` ukrywają brak relacji. | Migracja wymaga jawnej tabeli konfliktów, rozliczenia/refundu i deterministycznego wyboru; nie usuwać aktywów. Osobny model odcinka bez sentinelów. |
| `migrations/etap13_hub_permit_schema.sql` i `GAME_README.md` 9 czerwca | Potwierdzona niespójność dostarczonych materiałów: migracja deklaruje `ADD COLUMN IF NOT EXISTS`, a historia mówi, że tę składnię usunięto jako niekompatybilną z używanym MySQL. | Nie traktować tej migracji jako sprawdzonej produkcyjnie. W nowej grze migracje Laravel uruchamiane przy deployu, nie DDL wykonywane w serwisie. |
| `src/TransportConfigService.php::ensureTransportSchema()` i `src/RoadTransportService.php::ensureSchema()` | Potwierdzone: serwisy wykonują `ALTER TABLE`/`CREATE TABLE` podczas działania aplikacji; pierwszy ustawia `schemaEnsured=true` przed powodzeniem operacji, a część błędów tylko loguje. | Możliwy częściowo zmigrowany schemat i różne wyniki między procesami. Migracje należy wydzielić z requestów i ticków; nie maskować brakującej kolumny wartościami domyślnymi. |
| `src/RoadTransportService.php::dispatchTrips()` | Potwierdzone: zapisuje jedną zbiorczą pozycję `well_road_trips` z `trips_count`, lecz nie wskazuje identyfikatorów pojazdów, docelowego huba ani blokady wolumenu; samo wywołanie nie pokazuje potrącenia `cost`. | Z przedstawionego fragmentu nie da się potwierdzić podwójnego dispatchu ani darmowego kursu, bo wywołujący kod może to robić. Nowy serwis musi mieć transakcyjną rezerwację ropy, floty i kosztu oraz jednoznaczny cel. |
| `src/RoadTransportService.php::processCompletedTrips()` | Potwierdzone ryzyko wyścigu: `SELECT` dojrzałych kursów nie ma `FOR UPDATE`, a `UPDATE ... WHERE id=? AND player_id=?` nie warunkuje poprzedniego statusu ani nie sprawdza `rowCount()`. | Dwa workery mogą przeliczyć ten sam kurs, zwłaszcza przy wywołaniu poza zewnętrzną blokadą. Zapewnić claim CAS (`status`, wersja), lock i idempotentny zapis odbiorcy. Sama deklaracja transakcji w komentarzu nie dowodzi ochrony wszystkich ścieżek. |
| `src/RoadTransportService.php::applyTripIncidents()` | Potwierdzone: używa `mt_rand()`, a `processTick()` ma osobny stateless model; to nie spełnia deterministycznego replay §23.4. Po ustawieniu wagi sabotażu na zero przeskalowanie szansy następuje tylko przy niepustym `$protMults`, więc samo wyłączenie sabotażu zmienia rozkład pozostałych typów w sposób zależny od ochrony. | Jeden algorytm, seed per kurs/typ; sumę prawdopodobieństw typów liczyć jawnie. `computeTripLoss()` traktuje `route_block` jak utratę całego ładunku, choć blokada powinna zasadniczo oznaczać opóźnienie; to decyzja balansowa do zmiany. |
| `src/RoadTransportService.php::getActiveTripsForPlayer()` | Potwierdzone: pokazuje tylko `status='in_transit'`, podczas gdy `processCompletedTrips()` obsługuje także `delayed` i `crediting`. | Aktywny opóźniony kurs może zniknąć z tej listy. Widok ma prezentować wszystkie nieterminalne stany z powodem i ETA. |
| `src/WellRoadTripSection.php::process()` | Potwierdzone: dla kursów z hubem dodaje `hubDelivered` do `$currentStorage` „optymistycznie”, a następnie polega na późniejszej korekcie finalizacji huba; kursy bez huba mogą zasilić magazyn. Skaluje `delivered_bbl` SQL-em po kursach `crediting` przez współczynnik `round(...,8)`. | To kruche sprzężenie i ryzyko różnic zaokrągleń oraz obejścia obowiązkowego huba. Nowy zapis przenosi partię bezpośrednio do właściwego bufora, bez chwilowego kredytu magazynu i masowego skalowania historii kursów. |
| `src/PipelineSection.php::process()` | Potwierdzone: wybuch oblicza `oilInTransit` z `$currentStorage` jako 5% pozostałego magazynu, nawet gdy rurociąg jest inbound; pozycję straty przypisuje także hubowi. | Wybuch rurociągu nie powinien usuwać niezwiązanego zapasu w magazynie. Strata musi pochodzić z `pipeline_inflight` danego `pipeline_id` i mieć audytowany ruch. |
| `src/PipelineSection.php::rollPipelineIncident()` | Potwierdzone: losowanie używa `mt_rand()`; przy spadku kondycji do zera status jest korygowany od razu, ale pozostałe przejścia odkłada się na następny tick. | Przy 60 s zachować deterministyczne RNG i określić, czy wzrost strat/ryzyka obowiązuje od bieżącej czy następnej fazy. Nie pozostawiać tego przypadkowej kolejności wywołań. |
| `migrations/2026-07-18_hub_operator_technical_migration.sql` | Potwierdzone: skrypt trwale usuwa `employee_assignments`, kontrakty i historię zatrudnienia starych operatorów; „techniczny mirror” zachowuje, ustawiając `manager_id=0`. | W zero-wipe migrować relacje i zachować zdarzenia archiwalne; usunięcie historii nie jest akceptowalną metodą ujednolicenia stanowiska. |
| `GAME_README.md`, 3 lipca, 9 lipca i 15 lipca | Historia odnotowuje m.in. naprawy podwójnego spillu, potrójnej opłaty offshore, darmowych awarii, wskrzeszania rurociągów, kasowania rejsów, błędnego capowania magazynem i precyzji kondycji. | Nie oznaczam ich jako nadal występujących w niewidocznym kodzie. Każda przyczyna dostaje test regresyjny i pojedyncze miejsce naliczenia/zmiany stanu w OilEmpire. |

### D. Decyzje przed rozpoczęciem kodowania — kolejność

1. Zatwierdzić model własności i najmu hubów oraz prawa do ropy w hubach NPC/wspólnych. Bez tego nie da się poprawnie zaprojektować uprawnień, obsady ani outbound.
2. Zatwierdzić jeden model partii i niezmienny bilans ropy, w tym miejsce oczekiwania przy pełnym hubie, porcie i magazynie. To warunek usunięcia klas błędów z czerwca i lipca.
3. Zdecydować, czy B2B może wysyłać bezpośrednio z huba; jeśli tak, określić punkt przeniesienia własności i rezerwacje konkurujące ze spotem.
4. Zatwierdzić graf tras, pojemność rzeczywistej floty, zasady powrotu oraz brak automatycznego fallbacku bez pojazdu.
5. Zdefiniować pozwolenie Poziomu 2, pełny cykl życia każdego odcinka i politykę przekierowania partii podczas przepięcia.
6. Ustalić pojedynczego właściciela każdej opłaty i straty w fazach 2/5 oraz komplet kluczy idempotencji; dopiero potem stroić liczby `game_balance`.
7. Zatwierdzić semantykę strajku: aktywny strajk uruchamia formułę, a ściśle `u>60%` zatrzymuje wyłącznie wskazaną trasę główną. Test granicy `u=60%` jest obowiązkowy.
8. Przed UI wykonać headless testy bilansu partii, wyścigów, restartu ticka i wszystkich historycznych regresji z `GAME_README.md`.
