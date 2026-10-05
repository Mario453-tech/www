# Kompletny czat OilEmpire

## Układ

1. **Pokoje po lewej**
   - Polski
   - English
   - Germany
   - aktywny pokój ma złote obramowanie;
   - liczba nieprzeczytanych wiadomości jest przy pokoju.

2. **Prywatne wiadomości pod pokojami**
   - lista osób, z którymi gracz ma rozmowę;
   - zielona kropka oznacza online;
   - złoty licznik oznacza nieprzeczytane wiadomości;
   - kliknięcie otwiera rozmowę 1:1 w środkowej kolumnie.

3. **Środkowa rozmowa**
   - nagłówek pokazuje nazwę pokoju lub osobę;
   - wiadomości innych graczy są po lewej;
   - własne wiadomości są po prawej;
   - autor, avatar i czas są grupowane;
   - pole wiadomości zawsze opisuje aktualny pokój lub rozmowę prywatną.

4. **Aktywni gracze po prawej**
   - lista obecnych graczy i ich pokój;
   - kliknięcie gracza otwiera prywatną rozmowę;
   - offline jest widoczny, ale nie jako aktywny.

## Uprawnienia

- Zwykły gracz może wejść do trzech pokoi, pisać i otwierać rozmowy prywatne.
- Tylko administrator może tworzyć, zmieniać nazwę, usuwać i moderować pokoje.
- Przy zwykłym graczu nie pokazuj przycisku „Utwórz pokój”.
- Wiadomość administratora może mieć oznaczenie `[ADMIN]`, ale nie może zmieniać wyglądu całego wątku.

## Zachowanie

- Po wejściu domyślnie otwiera się pokój Polski.
- Zmiana pokoju zachowuje lokalny stan przeczytania.
- Po wysłaniu wiadomości pole jest czyszczone, a rozmowa przewija się na dół.
- Nieprzeczytane są zerowane dopiero po otwarciu danego pokoju lub rozmowy.
- Puste pokoje pokazują spokojny komunikat „Brak wiadomości. Rozpocznij rozmowę.”
- Długie wiadomości zawijają się; nie mogą powodować poziomego przewijania.

## Mobile

- Najpierw nagłówek i przełącznik „Pokoje / Prywatne”.
- Lista pokoi i osób otwiera się jako wysuwany panel.
- Rozmowa zajmuje całą szerokość.
- Pole wysyłania pozostaje przyklejone na dole.
- Minimalny cel dotykowy przycisków: 44×44 px.

## Przykładowe dane widoczne na makiecie

To są dane makiety. W produkcji liczby pokoi, online, wiadomości i liczniki muszą pochodzić z bazy oraz uprawnień użytkownika.

