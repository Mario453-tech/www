# Dokumentacja OilEmpire

Ten katalog zawiera specyfikację nowego silnika gry oraz materiały, z których powstała. Pliki można edytować bezpośrednio w GitHubie, a zmiany są wersjonowane przez Git.

## Dokumenty obowiązujące

- [Architektura i zasady gry](OILEMPIRE_ARCHITEKTURA_SYSTEMOWA.md) — główna specyfikacja.
- [Panel administracyjny](OILEMPIRE_PANEL_ADMINA_SPECYFIKACJA.md) — kanoniczny załącznik dotyczący Filamenta.
- [API gry i aplikacji mobilnej](OILEMPIRE_API_SPECYFIKACJA_TECHNICZNA.md) — projekt kontraktu HTTP dla wspólnego silnika.
- [Balans gry](OILEMPIRE_GAME_BALANCE_V1.yaml) — parametry ekonomii, czytane przez symulator.
- [Symulator balansu](OILEMPIRE_SYMULATOR_BALANSU.py) — automatyczna kontrola parametrów.
- [Lista poprawek](OILEMPIRE_MASTER_LISTA_POPRAWEK.md) i [audyt końcowy](OILEMPIRE_AUDYT_FINAL_SYNTEZA.md) — historia decyzji i ich uzasadnienie.

Pozostałe pliki `OILEMPIRE_*` to propozycje, recenzje, syntezy i wcześniejsze wersje analiz. Zachowujemy je jako źródła historyczne; w razie rozbieżności pierwszeństwo mają dokumenty obowiązujące wymienione wyżej.

## Wprowadzanie zmian

W GitHubie otwórz plik i wybierz **Edit this file**. Zmieniaj główną specyfikację oraz odpowiedni załącznik lub YAML razem, jeśli decyzja wpływa na kilka miejsc. Przed przyjęciem zmian balansu uruchom symulator na tym samym YAML:

```powershell
python OILEMPIRE_SYMULATOR_BALANSU.py
```

Dokumentacja opisuje docelowy silnik Laravel. Samo zapisanie wymagania w tym katalogu nie oznacza, że funkcja jest już wdrożona w kodzie gry.
