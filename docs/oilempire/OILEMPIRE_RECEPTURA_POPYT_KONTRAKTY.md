# OilEmpire — wybór receptury z popytu i kontraktów

Źródło: architektura §31.3, P19-F03/F04, kanoniczne receptury A/B/C i aktywny game_balance. Ten dokument określa algorytm rekomendacji, nie zmienia wydajności receptur ani cen. Wybór ostateczny należy do gracza.

## Wejście serwera

W jednej wersji snapshotu serwer pobiera: klasę i sprawność rafinerii, dostępny wsad i miejsce w zbiornikach, wersję balansu, aktualną prognozę popytu/cen regionu, aktywne kontrakty produktowe z ilością pozostałą i terminem, obecny zapas produktu o jakości zgodnej z kontraktem oraz ograniczenia BHP/techniczne. Klient podaje tylko recipe_code i expected_state_version. Snapshot popytu/kontraktów, logical_at i hash tworzy serwer pod LockPlan; nie ufa demand_snapshot_at z telefonu.

## Rekomendacja deterministyczna

Dla każdej technicznie dostępnej A/B/C oblicz projekcję jednego następnego slotu przerobu z kanonicznych udziałów §31.3. Ilość do kontraktu produktu j = max(0, suma niewypełnionych dostaw we wszystkich aktywnych kontraktach − już dostępny zapas o wymaganej jakości). Projekcja kontraktu = min(ilość do kontraktu, wynik receptury j). Receptury porównuje się leksykograficznie:

1. Najpierw minimalizuj sumę bbl niewypełnionych aktywnych kontraktów w horyzoncie, z wcześniejszym due_at przed późniejszym. Nie obiecuj produktu, którego jakość nie spełnia umowy.
2. Przy remisie maksymalizuj przewidywaną marżę w groszach: przychód kontraktowy + możliwa sprzedaż po serwerowej cenie popytu − zmienny OPEX − koszt technologiczny receptury − znane opłaty za przepełnienie/flarę. Popyt ogranicza ilość wycenianą po cenie spot; nadwyżka nie dostaje fikcyjnego przychodu.
3. Przy dalszym remisie zachowaj bieżącą recepturę, potem deterministyczny porządek A/B/C. Nie losuj rekomendacji.

Nie wprowadzamy nowego horyzontu ani mnożnika: aktywne kontrakty są rozpatrywane w kolejności due_at, a ceny i popyt pochodzą z tego samego serwerowego snapshotu. Reguły legalności A/B/C i wydajność pozostają z kanonu.

## Decyzja gracza i historia

UI pokazuje A/B/C: przewidywane produkty, pokrycie kontraktów, marżę, brak miejsca, flarę i powód rekomendacji. Gracz może wybrać inną legalną recepturę; potwierdzenie używa lokalnej ikony SVG i dostępnego modala. SelectRecipe zapisuje refinery_recipe_versions: wybraną recepturę, demand_snapshot_json, contract_snapshot_json, demand_snapshot_at, contract_snapshot_hash, recommendation_code, selection_reason_code, balance_version i effective_at. Receptura działa od kolejnego pełnego slotu, bez przepisywania już rozliczonych refinery_runs. SettleRefinery nie przelicza starego snapshotu.

## Przykład i testy

100 bbl wsadu: A daje 6 bbl Jet, B 14 bbl, C 22 bbl Jet według §31.3. Przy 20 bbl kwalifikowanego Jet do dostarczenia w aktywnych kontraktach i zerowym zapasie C jako jedyna pokrywa całe zobowiązanie, jeśli sprzęt pozwala; dodatkowe 8 PLN/bbl C jest uwzględnione w drugim kryterium. Bez kontraktu Jet porównać marżę A/B/C na aktualnym popycie i wolnym magazynie. Testy: kontrakt w aktywnych kontraktach ±1 ms, równa marża, niekwalifikowana jakość, C bez hydrotreatera, zmiana popytu między podglądem a zatwierdzeniem (409 i nowy snapshot), dwa równoległe wybory, pełny magazyn, 2h limit flary i bilans masowy 0.0001 bbl.
