# OilEmpire - kompletny czat graczy

## Cel

Zastąpić pojedynczy panel „Czat graczy” pełnym modułem komunikacji z trzema stałymi pokojami językowymi oraz wiadomościami prywatnymi.

Zakres obejmuje interfejs, dane, autoryzację, moderację, odczyt nieprzeczytanych wiadomości, wersje PL/EN i testy. Nie zmienia mechaniki gry, finansów ani uprawnień poza komunikacją.

Makieta referencyjna:

- `chat-kompletny.svg` - widok desktopowy.
- `chat-wiadomosci-poprawione.svg` - zasady układania wiadomości.
- `chat-pokoje-admin.svg` - rola administratora przy pokojach.

## Wynik dla gracza

Gracz widzi jeden moduł czatu z trzema kolumnami na desktopie:

1. po lewej stałe pokoje oraz prywatne rozmowy;
2. na środku aktualnie otwartą rozmowę;
3. po prawej aktywnych graczy.

Może:

- wejść do pokoju Polski, English albo Germany;
- pisać i czytać wiadomości w pokojach;
- otworzyć prywatną rozmowę z innym graczem;
- pisać w rozmowie prywatnej;
- zobaczyć liczbę nieprzeczytanych wiadomości;
- zobaczyć aktywnych graczy oraz ich aktualny pokój językowy.

Nie może tworzyć, usuwać ani zmieniać nazw pokoi.

Administrator dodatkowo może:

- tworzyć pokój;
- zmieniać nazwę, język, opis i status pokoju;
- zamknąć albo archiwizować pokój;
- usuwać lub ukrywać wiadomość zgodnie z polityką moderacji;
- wyciszyć lub zablokować możliwość pisania konkretnemu graczowi;
- sprawdzić historię działań moderacyjnych.

## Stałe pokoje startowe

| Slug | Widoczna nazwa PL | Widoczna nazwa EN | Przeznaczenie |
|---|---|---|---|
| `polski` | Polski | Polish | rozmowy po polsku |
| `english` | English | English | rozmowy po angielsku |
| `germany` | Germany | Germany | rozmowy po niemiecku |

Te trzy pokoje powinny być utworzone przez seed lub migrację danych. Nie twórz ich warunkowo przy każdym wejściu na stronę.

Pokój ma pozostać widoczny także wtedy, gdy nie ma wiadomości ani uczestników online. W takim przypadku środek pokazuje pusty stan:

> Brak wiadomości. Rozpocznij rozmowę.

Jeżeli pokój jest zamknięty przez administratora, gracze mogą czytać historię, ale nie mogą wysłać nowej wiadomości. Pole wpisywania zastępuje komunikat:

> Pokój jest obecnie tylko do odczytu.

## Makieta i rozkład desktopowy

Szerokość od 1024 px:

```
┌──────────────────────┬─────────────────────────────────────┬───────────────────────┐
│ POKOJE               │ # Polski                             │ AKTYWNI GRACZE        │
│ Polski               │ opis pokoju · liczba uczestników     │ Marta Kowalska        │
│ English              │─────────────────────────────────────│ Jan Nowak             │
│ Germany              │ wiadomości innych po lewej           │ Emily Smith           │
│──────────────────────│ własne wiadomości po prawej          │ Hans Müller           │
│ PRYWATNE             │                                     │───────────────────────│
│ Marta Kowalska (2)   │                                     │ Instrukcja            │
│ Jan Nowak            │─────────────────────────────────────│                       │
│ Piotr Wiśniewski     │ [ wpisz wiadomość              ][→] │                       │
│──────────────────────│                                     │                       │
│ ADMIN: tylko admin   │                                     │                       │
└──────────────────────┴─────────────────────────────────────┴───────────────────────┘
```

Wymiary desktopowe:

- całość: maksimum 1280 px szerokości, bez bocznego paska gry;
- lewa kolumna: 250-280 px;
- prawa kolumna: 230-270 px;
- środkowa kolumna: reszta szerokości, minimum 460 px;
- odstęp między kolumnami: 0, rozdzielony cienką linią;
- wewnętrzny padding: 20-24 px;
- wysokość modułu: zależna od okna, docelowo minimum 540 px i maksimum około 700 px;
- lista wiadomości jest jedynym pionowo przewijanym obszarem w środku;
- lewa i prawa lista mogą przewijać się niezależnie, gdy danych jest więcej niż mieści się w panelu;
- pole pisania jest zawsze przy dolnej krawędzi środkowej kolumny.

Styl:

- tło strony: `#0b0c10`;
- panel: `#15161f`;
- tło pola i list: `#101117`;
- linie: `#34343e`;
- złoto: `#c8a84b`;
- tekst główny: `#eeedf0`;
- tekst pomocniczy: `#92939d`;
- sukces / online: `#4dcb8e`;
- ostrzeżenie: `#e3aa54`;
- błąd: `#ef6976`.

Nie stosować gradientów, neonów, przeszklonych kart, ciężkich cieni ani wielkich nagłówków.

## Wiadomości

### Wiadomość innych graczy

- po lewej stronie;
- avatar 32 px wyświetlany raz dla kolejnego bloku wiadomości tego samego autora;
- obok avatara: nazwa autora i czas;
- bąbel o maksymalnej szerokości około 70% środkowej kolumny;
- tło bąbla: grafitowe `#24252f`, cienka obwódka;
- kolejne wiadomości tej samej osoby w krótkim odstępie grupują się pod pierwszym avatarem.

### Własna wiadomość

- po prawej stronie;
- avatar jest po prawej i razem z wiadomością pozostaje w środkowej kolumnie;
- nie może nachodzić na prawy panel aktywnych graczy;
- nazwa i czas wyrównane do prawej krawędzi bloku;
- bąbel ma subtelne złote tło `#2c281d` i obramowanie `#806b34`;
- maksymalna szerokość taka sama jak dla wiadomości innych graczy.

### Wiadomość administratora

- format identyczny z wiadomością zwykłego gracza;
- nazwa może mieć prefiks `[ADMIN]`;
- złoty kolor dotyczy wyłącznie nazwy lub małej etykiety;
- nie wyróżniaj całej szerokości wiadomości i nie używaj dużych banerów.

### Data i czas

- separator daty np. `DZISIAJ`, `WCZORAJ`, `4 PAŹDZIERNIKA`;
- czas przy grupie wiadomości w lokalnym formacie użytkownika;
- serwer zapisuje czas w UTC;
- klient formatuje go według strefy czasu lub ustalonej strefy gry.

### Treść

- maksymalna długość: 1 000 znaków;
- wielolinijkowa wiadomość zawija się i nie rozpycha layoutu;
- URL można zamienić na bezpieczny link, otwierany z `rel="noopener noreferrer"`;
- w pierwszej wersji nie obsługiwać HTML, Markdown, obrazów, GIF-ów ani osadzania stron;
- wszystkie treści użytkownika kodować przy renderowaniu;
- emoji można dopuścić wyłącznie jako zwykły tekst, nie jako zestaw dodatkowych komponentów.

## Pokoje

Każdy wpis pokoju zawiera:

- kropkę statusu;
- nazwę;
- opis pomocniczy: liczba nowych wiadomości albo uczestników;
- licznik nieprzeczytanych, tylko gdy jest większy od zera;
- aktywny pokój: złote obramowanie i przygaszone złote tło;
- nieaktywny pokój: neutralne tło bez dużych kart.

Kropka przy pokoju nie oznacza „online”. Jest to znacznik pokoju. Do statusu obecności używaj zielonej kropki przy użytkowniku.

Nazwa pokoju nie może być jedynym nośnikiem języka. Model danych przechowuje kod języka, np. `pl`, `en`, `de`.

### Tworzenie pokoju przez administratora

Przycisk „Utwórz pokój” jest widoczny tylko użytkownikowi z uprawnieniem `chat.rooms.manage`.

Formularz administratora:

- nazwa PL;
- nazwa EN;
- slug;
- kod języka lub typ pokoju;
- krótki opis PL/EN;
- kolejność na liście;
- status: aktywny / tylko do odczytu / archiwalny.

Slug waliduj po stronie serwera: małe litery, cyfry i myślniki, unikalny globalnie.

Usunięcie pokoju nie powinno być fizyczne, jeżeli zawiera wiadomości. Użyj archiwizacji: pokój znika z domyślnej listy, historia zostaje zachowana dla moderatorów oraz zgodnie z polityką retencji.

## Prywatne wiadomości

Sekcja „Prywatne” jest pod pokojami.

Każdy wpis zawiera:

- avatar lub inicjał;
- zieloną / szarą kropkę obecności;
- nazwę gracza;
- podgląd ostatniej wiadomości albo datę;
- licznik nieprzeczytanych.

Kliknięcie osoby:

1. zmienia środkowy nagłówek z `# Polski` na nazwę gracza;
2. pokazuje status online / offline;
3. ładuje rozmowę 1:1;
4. zmienia placeholder na `Napisz prywatną wiadomość do …`;
5. odczytuje licznik tej rozmowy.

Rozmowa prywatna jest zawsze dostępna tylko dla dwóch uczestników oraz uprawnionych moderatorów zgodnie z jawną polityką. Nie udostępniaj listy prywatnych rozmów jednej osoby innym graczom.

Zablokowany gracz nie może otworzyć ani wysłać wiadomości prywatnej, jeśli taka jest decyzja produktu. Komunikat nie powinien ujawniać, kto wykonał blokadę.

## Aktywni gracze

Prawa kolumna pokazuje tylko osoby aktywne w ostatnich pięciu minutach, o ile system nie ma dokładniejszego mechanizmu presence.

Przy użytkowniku:

- zielona kropka: aktywny;
- szara kropka: ostatnio offline, opcjonalna w ograniczonej liczbie;
- nazwa;
- aktualny pokój, jeśli użytkownik zgodził się na publiczną obecność albo to jest ogólnodostępny pokój;
- strzałka / przycisk „Napisz” otwierający prywatną rozmowę.

Nie pokazuj w tej kolumnie prywatnych danych gracza, pełnej historii online ani aktywności na ekranach gry.

## Model danych

Nazwy tabel dostosuj do istniejącego projektu. Nie wprowadzaj duplikatów, jeżeli projekt już ma odpowiednik.

### `chat_rooms`

| Kolumna | Typ / zasada |
|---|---|
| id | ULID lub identyfikator projektu |
| slug | unikalny, indeks |
| type | enum: language, custom, system |
| locale_code | nullable, np. pl/en/de |
| name_pl | tekst |
| name_en | tekst |
| description_pl | nullable |
| description_en | nullable |
| sort_order | liczba całkowita |
| status | active, read_only, archived |
| created_by | FK do użytkownika, nullable dla seedów |
| created_at / updated_at | UTC |
| archived_at | nullable |

### `chat_room_messages`

| Kolumna | Typ / zasada |
|---|---|
| id | ULID lub identyfikator projektu |
| room_id | FK, indeks |
| sender_id | FK, indeks |
| body | tekst, maksimum 1 000 znaków po walidacji |
| status | active, deleted_by_author, hidden_by_moderator |
| created_at | UTC, indeks wraz z room_id |
| edited_at | nullable |
| deleted_at | nullable |
| moderation_note | nullable, prywatna dla moderatorów |

Indeks odczytu historii: `(room_id, created_at, id)`.

### `chat_direct_threads`

| Kolumna | Typ / zasada |
|---|---|
| id | ULID lub identyfikator projektu |
| participant_low_id | FK |
| participant_high_id | FK |
| last_message_at | UTC, indeks |
| created_at / updated_at | UTC |

Para uczestników musi być kanoniczna: mniejszy identyfikator w `participant_low_id`, większy w `participant_high_id`. Dodaj unikalność pary.

### `chat_direct_messages`

| Kolumna | Typ / zasada |
|---|---|
| id | ULID lub identyfikator projektu |
| thread_id | FK, indeks |
| sender_id | FK |
| body | tekst |
| status | active, deleted_by_author, hidden_by_moderator |
| created_at | UTC, indeks wraz z thread_id |
| edited_at / deleted_at | nullable |

Indeks odczytu historii: `(thread_id, created_at, id)`.

### `chat_read_states`

Jeden wpis dla gracza i źródła rozmowy.

| Kolumna | Typ / zasada |
|---|---|
| player_id | FK |
| conversation_type | room lub direct |
| conversation_id | id pokoju lub wątku |
| last_read_message_id | nullable |
| last_read_at | UTC |
| updated_at | UTC |

Dodaj unikalność `(player_id, conversation_type, conversation_id)`.

Nie licz nieprzeczytanych przez zapamiętywanie osobnego licznika dla każdego wpisu. Wyprowadzaj go z ostatnio przeczytanej wiadomości albo aktualizuj materializowany licznik tylko wtedy, gdy skala tego wymaga.

### `chat_moderation_actions`

| Kolumna | Typ / zasada |
|---|---|
| id | ULID |
| actor_id | administrator / moderator |
| action | room_created, room_archived, message_hidden, player_muted, player_unmuted |
| target_type | room, room_message, direct_message, player |
| target_id | identyfikator celu |
| reason | nullable |
| created_at | UTC |

To jest audyt działań administracyjnych. Nie pozwalaj administratorowi cicho usuwać historii moderacji.

## Uprawnienia

Minimalne polityki po stronie serwera:

| Działanie | Gracz | Administrator |
|---|---:|---:|
| Lista aktywnych pokoi | tak | tak |
| Odczyt aktywnego pokoju | tak | tak |
| Wysłanie do aktywnego pokoju | tak, gdy active | tak |
| Odczyt prywatnej rozmowy | tylko uczestnik | według jawnej polityki |
| Wysłanie prywatnej wiadomości | tak | tak |
| Tworzenie pokoju | nie | tak |
| Edycja / archiwizacja pokoju | nie | tak |
| Moderacja wiadomości | nie | tak |
| Wyciszenie gracza | nie | tak |
| Odczyt audytu moderacji | nie | tak |

Ukrycie przycisku nie jest autoryzacją. Każdy endpoint, formularz i akcja serwera musi sprawdzać rolę oraz własność rozmowy.

## Endpointy i akcje

Dostosuj nazwy do istniejącego stylu aplikacji. Poniższa lista opisuje kontrakt.

- `GET /chat` - renderuje ekran i minimum danych początkowych.
- `GET /chat/rooms` - lista pokoi, statusy i liczniki dla zalogowanego użytkownika.
- `GET /chat/rooms/{slug}/messages?before={cursor}` - stronicowana historia pokoju.
- `POST /chat/rooms/{slug}/messages` - wysyła wiadomość do pokoju.
- `GET /chat/direct` - lista prywatnych wątków użytkownika.
- `POST /chat/direct/{player}/open` - otwiera lub odnajduje kanoniczny wątek 1:1.
- `GET /chat/direct/{thread}/messages?before={cursor}` - historia prywatna z autoryzacją uczestnika.
- `POST /chat/direct/{thread}/messages` - wysyła wiadomość prywatną.
- `POST /chat/read-state` - aktualizuje ostatnio przeczytaną wiadomość.
- `GET /chat/presence` - lista online, tylko do odczytu.
- `POST /admin/chat/rooms` - tworzy pokój.
- `PATCH /admin/chat/rooms/{room}` - edytuje pokój.
- `POST /admin/chat/rooms/{room}/archive` - archiwizuje pokój.
- `POST /admin/chat/messages/{message}/hide` - ukrywa wiadomość z uzasadnieniem.
- `POST /admin/chat/players/{player}/mute` - wycisza gracza.

Wszystkie mutacje: sesja, CSRF, walidacja, polityka dostępu, limit częstotliwości, audit tam gdzie potrzebny. Kontroler tylko przyjmuje żądanie i uruchamia akcję / serwis.

## Walidacja i bezpieczeństwo

- wymagaj zalogowanej sesji dla każdego endpointu czatu;
- dla prywatnego wątku sprawdzaj, czy użytkownik jest jego uczestnikiem;
- używaj przygotowanych zapytań / ORM, bez składania SQL z tekstu użytkownika;
- koduj każdą treść wiadomości w HTML;
- nie renderuj surowego HTML, SVG, JavaScriptu ani stylów z wiadomości;
- limit wiadomości: 1 000 znaków;
- limit częstotliwości startowy: 5 wiadomości na 10 sekund dla użytkownika i osobny limit dla adresu IP;
- po przekroczeniu limitu zwróć neutralny komunikat z czasem ponownej próby;
- blokuj puste wiadomości po trimowaniu;
- ogranicz liczbę nowych prywatnych wątków na minutę;
- loguj błędy techniczne po angielsku bez pełnej treści wiadomości;
- nie umieszczaj treści prywatnej wiadomości w URL, logu błędu ani analityce;
- nie zwracaj w API danych graczy spoza uprawnionego widoku;
- dodaj ochronę przed masowym odpytywaniem presence;
- zdefiniuj retencję rozmów prywatnych i politykę dostępu moderatorów przed produkcją.

## Odświeżanie i realtime

W pierwszym wdrożeniu wystarczy kontrolowany polling, bez zewnętrznej usługi realtime:

- gdy aktywna karta przeglądarki ma otwarty czat: co 8-12 sekund pobierz nowe wiadomości po ostatnim identyfikatorze;
- gdy karta jest ukryta: zwolnij do 30-60 sekund albo wstrzymaj;
- odświeżaj listę presence nie częściej niż co 30 sekund;
- po wysłaniu lokalnie dodaj wiadomość dopiero po sukcesie serwera;
- pokazuj stan „wysyłanie”, a przy błędzie umożliw ponowienie;
- nie przesuwaj widoku na dół, jeżeli użytkownik czyta starszą historię; pokaż mały przycisk „Nowe wiadomości”.

Dopiero gdy skala lub doświadczenie tego wymagają, wprowadź WebSockety jako osobny etap. Nie mieszaj realtime z pierwszym wdrożeniem interfejsu.

## Responsywność

### Od 1024 px

Trzy kolumny jak na makiecie.

### 768-1023 px

- lewa kolumna 220 px;
- środek reszta;
- prawa lista aktywnych chowa się do przycisku „Aktywni (8)” nad rozmową;
- kliknięcie otwiera mały panel lub modal.

### Poniżej 768 px

- ekran rozmowy zajmuje całą szerokość;
- nagłówek zawiera przyciski: „Pokoje”, „Prywatne”, „Aktywni”;
- lewa i prawa lista otwierają się jako panel modalny / drawer;
- brak poziomego przewijania całej strony;
- pole wpisywania przyklejone przy dolnej krawędzi rozmowy;
- przycisk wysłania i przyciski otwierające panele mają minimum 44 x 44 px;
- dłuższa nazwa gracza jest skracana wizualnie, ale pełna nazwa pozostaje dostępna przez title lub tekst pomocniczy.

### Najmniejsze szerokości

Sprawdź 320, 360 i 390 px. Na tych szerokościach nie zmniejszaj tekstu wiadomości poniżej 14 px.

## Dostępność

- używaj prawdziwych przycisków i linków, nie klikanych divów;
- lewa lista pokoi: `nav` z opisem;
- lista wiadomości: `section` z nagłówkiem rozmowy;
- każda nowa wiadomość nie powinna samoczynnie przejmować fokusu;
- dla nowych wiadomości użyj spokojnego komunikatu `aria-live="polite"`, bez odczytywania całej historii przy każdym odświeżeniu;
- wyraźny focus dla pokoju, osoby, pola i przycisku wysłania;
- kolor statusu zawsze uzupełniony tekstem;
- avatar dekoracyjny ma pusty alt, a nazwa autora jest obok jako tekst;
- przycisk prywatnej rozmowy ma np. `aria-label="Napisz prywatną wiadomość do Marty Kowalskiej"`;
- modal na telefonie obsługuje Escape, focus trap i zwraca fokus do wyzwalacza.

## Tłumaczenia

Każdy nowy tekst musi istnieć w PL i EN. Przykładowe klucze:

```
chat.title
chat.subtitle
chat.rooms
chat.private
chat.active_players
chat.online_count
chat.room.polish
chat.room.english
chat.room.germany
chat.placeholder.room
chat.placeholder.direct
chat.send
chat.empty_room
chat.read_only
chat.new_messages
chat.admin_only_room_management
chat.create_room
chat.archive_room
chat.mute_player
chat.message_rate_limited
```

Nazwy pomieszczeń mogą pozostać własne: `Polski`, `English`, `Germany`. Opisy i wszystkie komunikaty wymagają tłumaczeń.

## Testy wymagane

### Logika i dane

- seed tworzy dokładnie trzy pokoje startowe i jest idempotentny;
- zwykły gracz nie tworzy pokoju;
- administrator tworzy i archiwizuje pokój;
- aktywny pokój przyjmuje wiadomość;
- pokój read-only odrzuca wysłanie wiadomości;
- prywatną historię widzą wyłącznie uczestnicy;
- dwóch graczy nie tworzy duplikatów wątku 1:1;
- licznik nieprzeczytanych rośnie po nowej wiadomości i znika po odczycie;
- ukryta wiadomość nie jest widoczna dla gracza;
- działanie moderatora trafia do audytu;
- limit wiadomości blokuje nadmiar bez utraty wcześniejszych danych.

### Widok

- PL i EN;
- pokoj Polski, English oraz Germany;
- pusty pokój;
- pusta lista prywatnych rozmów;
- wiadomość własna i obca;
- długi tekst, wielolinijkowy tekst, duża liczba wiadomości;
- brak poziomego przewijania dla 320, 360, 390, 768, 1024 i 1440 px;
- klawiatura, focus i Escape dla paneli mobilnych;
- brak błędów konsoli;
- brak HTML w wiadomości po renderowaniu.

## Kolejność wdrożenia

1. Sprawdź obecny moduł czatu, schemat danych, routing, rolę administratora i istniejące tłumaczenia.
2. Dodaj brakujące migracje oraz seed trzech językowych pokoi.
3. Dodaj model / repozytoria, polityki dostępu i akcje aplikacyjne.
4. Dodaj endpointy oraz testy autoryzacji.
5. Zbuduj listę pokoi i historię jednego pokoju.
6. Dodaj wysyłanie wiadomości oraz read states.
7. Dodaj prywatne wątki i wiadomości prywatne.
8. Dodaj panel aktywnych graczy oraz kontrolowany polling.
9. Wdróż desktopowy układ z makiety.
10. Wdróż wersję mobilną.
11. Dodaj moderację i audyt.
12. Uruchom testy, lint, kontrolę kodowania, build zasobów i test przeglądarkowy.

## Kryteria odbioru

- Gracz może przełączać Polski, English i Germany.
- Gracz widzi i używa prywatnych wiadomości.
- Tylko administrator tworzy oraz zarządza pokojami.
- Wiadomości nie wychodzą poza środkową kolumnę.
- Własna wiadomość nie nachodzi na panel aktywnych graczy.
- Liczniki nieprzeczytanych są poprawne.
- Czat działa od 320 px i bez JavaScriptu pokazuje co najmniej historię oraz formularz standardowego wysyłania.
- Nie ma luk w autoryzacji prywatnych wątków.
- Wszystkie komunikaty mają PL/EN.
- Makieta służy do układu. Produkcyjne dane, liczniki i obecność pochodzą z rzeczywistych źródeł.

## Pliki przekazywane z tym briefem

- `chat-kompletny.svg` - kompletna makieta.
- `chat-wiadomosci-poprawione.svg` - poprawne grupowanie wiadomości.
- `chat-pokoje-admin.svg` - zakres administratora.
- `ROZPISKA-CZATU.md` - krótsza wersja koncepcji.
- `SPECYFIKACJA-WDROZENIA-CZATU.md` - ten pełny dokument.

