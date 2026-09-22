# Rozszerzenie katalogu mapy

Dodano 140 lokalizacji: po 10 punktow kategorii `starter` i 10 kategorii `medium` w kazdym z siedmiu regionow mapy. Male lokalizacje maja nizszy koszt wejscia i zasobnosc 0.66-0.82; srednie koszt 3.2-4.5 mln PLN i zasobnosc 1.08-1.18.

Katalog jest uzupelniany jednorazowo przy pierwszym odczycie mapy po wdrozeniu. Wpis w `well_config` oznacza ukonczony przebieg. Seeder nie nadpisuje istniejacych lokalizacji, takze wylaczonych; nie zmienia kupionych odwiertow. Blad lub brak regionu wycofuje caly przebieg, a mapa nadal pokazuje dotychczasowe lokalizacje. Ponowienie nastapi przy nastepnym odczycie.

Testy: `WorldLocationCatalogSeederTest` obejmuje powtorne uruchomienie, zachowanie wylaczonego wpisu i rollback przy brakujacym regionie.
