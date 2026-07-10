# Kontrakty B2B firma–firma — kompletny brief wdrożeniowy

## Cel modułu

Moduł B2B ma rozszerzyć istniejące kontrakty długoterminowe o handel firma–firma między graczami.

Nie ma to być osobna, chaotyczna strona. Moduł B2B ma być widoczny w obecnym ekranie:

```text
Kontrakty długoterminowe
```

Gracz powinien mieć w jednym miejscu:

```text
Systemowe
Rynek B2B
Moje B2B
Historia
Logi
```

Technicznie moduł B2B ma być osobny i modułowy, żeby można go było później łatwo rozbudować o częściowe dostawy, podkontrakty, aukcje, reputację B2B i dodatkowe klauzule.

---

# 1. Główna idea B2B

Gracz kupujący wystawia zlecenie kupna ropy.

Przykład:

```text
Kupię 20 000 bbl ropy po 85 PLN za bbl.
```

System od razu blokuje pełną wartość zamówienia jako zabezpieczenie.

Inny gracz może przyjąć zlecenie, jeśli ma wystarczająco ropy w magazynie.

Po przyjęciu:

```text
ropa schodzi z magazynu sprzedającego,
pieniądze trafiają do sprzedającego,
zlecenie zostaje oznaczone jako zrealizowane.
```

To ma być prosty, czytelny mechanizm:

```text
kupujący wystawia zlecenie
system blokuje pieniądze
sprzedający przyjmuje zlecenie
system pobiera ropę z magazynu sprzedającego
system wypłaca pieniądze sprzedającemu
```

---

# 2. Architektura modułowa

Moduł B2B ma mieć własny serwis, własne tabele i własny moduł ticka.

## Pliki

```text
src/B2BContracts/B2BContractSchema.php
src/B2BContractService.php
src/Tick/Modules/B2BContractsModule.php

public/contracts.php
admin/contracts.php

templates/views/contracts/main.php
templates/views/admin/contracts/main.php

assets/css/contracts.css
assets/js/contracts.js

lang/pl/contracts.php
lang/pl/admin/contracts.php
```

## Ważna decyzja

Nie tworzyć osobnej pozycji menu gracza typu:

```text
B2B
```

B2B ma być częścią istniejącego ekranu kontraktów.

Logika ma być osobno:

```text
B2BContractSchema
B2BContractService
B2BContractsModule
```

ale UI gracza ma być podpięte pod:

```text
public/contracts.php
templates/views/contracts/main.php
```

---

# 3. Moduł tickowy

Moduł tickowy B2B ma być zgodny z modularizacją tick engine.

Plik:

```text
src/Tick/Modules/B2BContractsModule.php
```

Parametry:

```php
key() = b2b_contracts
order() = 100
```

Kolejność:

```text
35 contracts
36 b2b_contracts
40 black_market
```

B2B działa po zwykłych kontraktach i przed czarnym rynkiem.

W MVP tick B2B robi głównie:

```text
wygaszanie ofert po czasie,
zwrot 100% zabezpieczenia przy wygaśnięciu,
logowanie zdarzeń,
statystyki ticka.
```

## Szkielet

```php
<?php
declare(strict_types=1);

class B2BContractsModule implements TickModule
{
    private int $expired = 0;
    private float $refunded = 0.0;

    public function key(): string
    {
        return 'b2b_contracts';
    }

    public function order(): int
    {
        return 36;
    }

    public function run(TickContext $ctx): void
    {
        $result = (new B2BContractService($ctx->db))
            ->expireOpenOffers($ctx->now);

        $this->expired = (int)($result['expired'] ?? 0);
        $this->refunded = (float)($result['refunded'] ?? 0.0);

        $ctx->mergeStats($this->key(), $this->stats());
    }

    public function stats(): array
    {
        return [
            'b2b_contracts_expired' => $this->expired,
            'b2b_contracts_refunded' => $this->refunded,
        ];
    }
}
```

---

# 4. Mechanika działania

## 4.1 Kupujący wystawia zlecenie

Kupujący podaje:

```text
ilość ropy w bbl,
cenę za 1 bbl,
czas ważności oferty,
minimalną reputację sprzedającego, opcjonalnie.
```

System sprawdza:

```text
czy moduł B2B jest włączony,
czy ilość bbl mieści się w limitach,
czy cena mieści się w limicie względem ceny rynku,
czy kupujący ma środki,
czy kupujący nie przekroczył limitu aktywnych ofert.
```

System wylicza:

```text
wartość zamówienia = ilość bbl × cena za bbl
zabezpieczenie = pełna wartość zamówienia
kara za anulowanie = 10% wartości zamówienia
zwrot po anulowaniu = 90% wartości zamówienia
```

Przykład:

```text
20 000 bbl × 85 PLN = 1 700 000 PLN

Zabezpieczenie: 1 700 000 PLN
Kara za anulowanie: 170 000 PLN
Zwrot po anulowaniu: 1 530 000 PLN
```

Status oferty:

```text
Aktywne
```

Technicznie:

```text
open
```

---

## 4.2 Sprzedający przyjmuje zlecenie

Sprzedający klika:

```text
Przyjmij i dostarcz
```

System sprawdza:

```text
czy zlecenie jest aktywne,
czy zlecenie nie wygasło,
czy sprzedający nie jest kupującym,
czy sprzedający ma wystarczającą ilość ropy,
czy sprzedający spełnia wymaganie reputacji.
```

Jeśli wszystko się zgadza:

```text
ropa schodzi z magazynu sprzedającego,
zabezpieczenie trafia do sprzedającego,
zlecenie zmienia status na Zrealizowane,
system zapisuje logi.
```

W MVP dostawa jest pełna.

Jeśli sprzedający nie ma całej ilości ropy, system nie pozwala przyjąć zlecenia.

Komunikat:

```text
Nie masz wystarczającej ilości ropy w magazynie.
```

---

## 4.3 Kupujący anuluje zlecenie

Kupujący może anulować tylko zlecenie aktywne.

Domyślna kara:

```text
10% wartości zamówienia
```

Przykład:

```text
Wartość zamówienia: 1 700 000 PLN
Kara za anulowanie: 170 000 PLN
Zwrot dla kupującego: 1 530 000 PLN
```

Status:

```text
Anulowane
```

Technicznie:

```text
cancelled
```

W logach zapisać:

```text
offer_cancelled
cancel_penalty_charged
escrow_refunded
```

---

## 4.4 Zlecenie wygasa

Jeśli nikt nie przyjmie zlecenia do końca czasu ważności, tick ustawia status:

```text
Wygasłe
```

Technicznie:

```text
expired
```

Przy wygaśnięciu:

```text
kupujący odzyskuje 100% zabezpieczenia,
nie ma kary,
system zapisuje log.
```

---

# 5. Statusy po polsku

Nie pokazywać graczowi technicznych statusów typu `open`, `completed`, `expired`.

## Status zlecenia

| Technicznie | Po polsku |
|---|---|
| `open` | Aktywne |
| `completed` | Zrealizowane |
| `cancelled` | Anulowane |
| `expired` | Wygasłe |
| `failed` | Nieudane |
| `flagged` | Do sprawdzenia |

## Status zabezpieczenia środków

Nie używać słowa `escrow` jako głównej nazwy w UI gracza.

| Technicznie | Po polsku |
|---|---|
| `locked` | Środki zablokowane |
| `released` | Środki wypłacone sprzedającemu |
| `refunded` | Środki zwrócone |
| `partial_refund` | Częściowy zwrot po karze |
| `forfeited` | Środki zatrzymane |

W panelu admina można użyć nazwy:

```text
Zabezpieczenie / escrow
```

ale tylko jako opis techniczny.

---

# 6. Podpięcie pod stronę gracza

## Zakładki gracza

Na stronie kontraktów dodać zakładki:

```text
[ Systemowe ] [ Rynek B2B ] [ Moje B2B ] [ Historia ] [ Logi ]
```

---

## 6.1 Zakładka: Systemowe

Tu zostają obecne kontrakty NPC:

```text
Dostępne kontrakty
Aktywne kontrakty
```

Dodać paginację.

Parametry URL:

```text
?tab=systemowe&oferty_strona=1&aktywne_strona=1
```

---

## 6.2 Zakładka: Rynek B2B

Tu są zlecenia kupna ropy wystawione przez innych graczy.

Nagłówek:

```text
Rynek B2B — zlecenia kupna ropy
```

Kolumny albo pola na karcie:

```text
Firma kupująca
Ilość ropy
Cena za bbl
Wartość zamówienia
Zabezpieczenie
Czas do wygaśnięcia
Wymagana reputacja
Akcja
```

Przykład karty:

```text
Firma kupująca: PetroMax
Kupi: 20 000 bbl
Cena: 85 PLN / bbl
Wartość: 1 700 000 PLN
Zabezpieczenie: środki zablokowane
Wygasa za: 12 godzin
Wymagana reputacja: 40

[Przyjmij i dostarcz]
```

Paginacja:

```text
?tab=b2b_rynek&b2b_rynek_strona=1
```

---

## 6.3 Zakładka: Moje B2B

Dwie sekcje:

```text
Moje zlecenia kupna
Moje sprzedaże B2B
```

### Moje zlecenia kupna

Kolumny:

```text
Ilość ropy
Cena za bbl
Wartość
Zabezpieczenie
Status
Kara za anulowanie
Zwrot po anulowaniu
Wygasa
Akcja
```

Przycisk:

```text
Anuluj zlecenie
```

Przy anulowaniu pokazać mocny komunikat:

```text
Anulowanie zlecenia pobierze karę 10% wartości zamówienia.

Wartość zamówienia: 1 700 000 PLN
Kara za anulowanie: 170 000 PLN
Zwrot dla Ciebie: 1 530 000 PLN
```

Potwierdzenie:

```text
Wpisz ANULUJ, aby potwierdzić.
```

### Moje sprzedaże B2B

Kolumny:

```text
Kupujący
Ilość ropy
Cena za bbl
Przychód
Status
Data realizacji
```

Paginacja:

```text
?tab=moje_b2b&moje_kupno_strona=1&moje_sprzedaze_strona=1
```

---

## 6.4 Zakładka: Historia

Historia powinna mieć przełącznik:

```text
[ Dostawy systemowe ] [ B2B ]
```

Kolumny dla dostaw systemowych:

```text
Termin
Dostarczono
Braki
Cena / bbl
Przychód
Kara
Status
```

Kolumny dla B2B:

```text
Data
Typ
Kontrahent
Ilość ropy
Cena / bbl
Wartość
Status
```

Paginacja:

```text
?tab=historia&historia_systemowa_strona=1&historia_b2b_strona=1
```

---

## 6.5 Zakładka: Logi

Logi powinny mieć filtry:

```text
Wszystkie
Systemowe
B2B
```

Kolumny:

```text
Data
Typ zdarzenia
Kontrakt / zlecenie
Opis
```

Paginacja:

```text
?tab=logi&logi_strona=1
```

---

# 7. Paginacja

Każda sekcja musi mieć własny parametr strony.

Nie używać jednego parametru `page`, bo jedna paginacja będzie przestawiać inne sekcje.

## Parametry

```php
$contractOffersPage = max(1, (int)($_GET['oferty_strona'] ?? 1));
$activeContractsPage = max(1, (int)($_GET['aktywne_strona'] ?? 1));

$b2bMarketPage = max(1, (int)($_GET['b2b_rynek_strona'] ?? 1));
$b2bMyBuyPage = max(1, (int)($_GET['moje_kupno_strona'] ?? 1));
$b2bMySalesPage = max(1, (int)($_GET['moje_sprzedaze_strona'] ?? 1));

$historySystemPage = max(1, (int)($_GET['historia_systemowa_strona'] ?? 1));
$historyB2BPage = max(1, (int)($_GET['historia_b2b_strona'] ?? 1));

$logsPage = max(1, (int)($_GET['logi_strona'] ?? 1));
```

## Limity

```php
$cardsPerPage = 9;
$tablePerPage = 20;
$logsPerPage = 30;
```

Każda metoda serwisu powinna zwracać:

```text
items
total
page
pages
limit
offset
```

---

# 8. Panel admina — docelowy wygląd

Panel admina ma wyglądać jak przygotowany prototyp:

```text
prototyp-panel-admin-kontrakty-b2b.html
```

Styl:

```text
ciemny panel,
złote akcenty,
czytelne karty,
polskie nazwy,
krótkie opisy,
brak surowych kluczy technicznych w głównym widoku.
```

Administrator nie powinien widzieć na pierwszym planie nazw typu:

```text
cancel_penalty_pct
deposit_forfeit_on_cancel
reputation_loss_on_missed_delivery
delivery_interval_minutes
```

Takie klucze mogą istnieć w bazie i w trybie zaawansowanym, ale panel główny ma mówić ludzkim językiem.

---

# 9. Główne zakładki panelu admina

```text
Pulpit
Kontrakty
Warunki
Aktywne
Dostawy
Reputacja
B2B
Ustawienia
Logi
```

---

## 9.1 Pulpit

Krótki ekran z najważniejszymi informacjami.

Karty:

```text
Aktywne kontrakty
Dostawy dzisiaj
Suma kar dzisiaj
B2B do sprawdzenia
```

Przykład:

```text
Aktywne kontrakty: 18
Dostawy dzisiaj: 74
Suma kar dzisiaj: 240 000 PLN
B2B do sprawdzenia: 2
```

Dodatkowe metryki:

```text
Przychód z kontraktów dzisiaj
Liczba zerwanych kontraktów
Liczba nieudanych dostaw
Wartość aktywnego B2B
Środki zablokowane w B2B
```

---

## 9.2 Kontrakty

Ta zakładka zastępuje surową listę opcji kontraktów.

Pokazywać:

```text
Nazwa
Odbiorca
Wolumen
Dostawa
Bonus
Kara
Kaucja
Wymagana reputacja
Status
Akcje
```

Przykład:

| Nazwa | Odbiorca | Wolumen | Dostawa | Bonus | Kara | Kaucja | Status | Akcje |
|---|---|---:|---|---:|---:|---:|---|---|
| Lokalna rafineria | BalticFuel Local | 5 000 bbl | 1 250 bbl co 6 godz. | +5% | 5% | 0 PLN | Włączony | Edytuj / Warunki / Wyłącz |
| Sieć paliwowa | NorthPetrol Network | 30 000 bbl | 5 000 bbl co 12 godz. | +10% | 8% | 231 000 PLN | Włączony | Edytuj / Warunki / Wyłącz |

Akcje:

```text
Edytuj
Warunki
Wyłącz
Duplikuj
Podgląd gracza
```

Paginacja:

```text
kontrakty_strona
```

Limit:

```text
20 kontraktów na stronę
```

---

## 9.3 Warunki

To najważniejsza zmiana.

Obecnie panel pokazuje długą listę technicznych kluczy. Nowy widok ma grupować warunki.

Kategorie:

```text
Podstawowe
Cena i premie
Dostawy
Kary i anulowanie
Kaucja
Reputacja
Ubezpieczenie
Zaawansowane
```

Lewy panel pokazuje kategorie i liczbę ustawień w każdej z nich:

```text
Podstawowe        4
Cena i premie     4
Dostawy           3
Kary i anulowanie 6
Kaucja            6
Reputacja         7
Ubezpieczenie     4
Zaawansowane      12
```

## Widok warunku jako karta

Każdy warunek pokazywać jako kartę.

Przykład:

```text
Całkowita ilość ropy
Ile ropy gracz musi dostarczyć przez cały czas trwania kontraktu.
Wartość: 5 000 bbl
[Edytuj]
```

Przykład:

```text
Czas trwania kontraktu
Po tym czasie kontrakt zostanie rozliczony jako wykonany albo nieudany.
Wartość: 1 dzień
[Edytuj]
```

Przykład:

```text
Wymagana reputacja
Minimalna reputacja kontraktowa potrzebna do podpisania tego kontraktu.
Wartość: 0
[Edytuj]
```

---

# 10. Formularz warunku

Obecny formularz:

```text
Klucz warunku
Typ
Wartość
Tekst
```

ma zostać tylko jako tryb zaawansowany.

Domyślnie administrator ma używać trybu prostego.

## Tryb prosty

Pola:

```text
Co chcesz ustawić?
Wartość
Jednostka
[Zapisz warunek]
```

Przykład:

```text
Co chcesz ustawić?
Kara za zerwanie kontraktu

Wartość:
10

Jednostka:
%
```

Opis pod spodem:

```text
Jeśli gracz zerwie kontrakt, zapłaci 10% wartości kontraktu.
```

Panel może dodatkowo pokazać techniczny klucz jako informację pomocniczą:

```text
Klucz techniczny: cancel_penalty_pct
```

ale nie jako główną nazwę.

## Tryb zaawansowany

Przycisk:

```text
Pokaż tryb zaawansowany
```

Po kliknięciu pokazuje pola:

```text
Klucz techniczny
Typ
Wartość
Tekst
```

Tryb zaawansowany jest dla admina technicznego albo programisty.

---

# 11. Mapowanie warunków na polskie nazwy

## Podstawowe

| Klucz techniczny | Nazwa w panelu | Opis |
|---|---|---|
| `total_bbl` | Całkowita ilość ropy | Ile ropy trzeba dostarczyć w całym kontrakcie. |
| `duration_minutes` | Czas trwania kontraktu | Po tym czasie kontrakt zostanie rozliczony. |
| `min_contract_reputation` | Wymagana reputacja kontraktowa | Minimalna reputacja potrzebna do podpisania kontraktu. |
| `max_active_per_player` | Limit aktywnych kontraktów | Ile takich kontraktów gracz może mieć jednocześnie. |

## Cena i premie

| Klucz techniczny | Nazwa w panelu | Opis |
|---|---|---|
| `bonus_pct` | Bonus do ceny | Dodatkowy procent doliczany do ceny rynkowej. |
| `bonus_on_full_completion_pct` | Bonus za pełną realizację | Premia za wykonanie całego kontraktu. |
| `bonus_requires_no_miss` | Bonus tylko bez braków | Bonus wypłaci się tylko wtedy, gdy gracz nie miał braków. |
| `price_multiplier` | Mnożnik ceny | Mnożnik używany przy wyliczaniu ceny. |

## Dostawy

| Klucz techniczny | Nazwa w panelu | Opis |
|---|---|---|
| `delivery_bbl` | Ilość ropy na jedną dostawę | Ile ropy system pobiera przy jednej dostawie. |
| `delivery_interval_minutes` | Co ile odbywa się dostawa | Odstęp między kolejnymi dostawami. |
| `min_storage_buffer_bbl` | Minimalny zapas w magazynie | Zapas, który gracz powinien mieć przed dostawą. |

## Kary i anulowanie

| Klucz techniczny | Nazwa w panelu | Opis |
|---|---|---|
| `penalty_pct` | Kara za brakującą ropę | Procent wartości brakującej dostawy. |
| `allow_cancel` | Gracz może zerwać kontrakt | Czy gracz może sam zakończyć kontrakt przed czasem. |
| `cancel_penalty_pct` | Kara za zerwanie kontraktu | Procent wartości kontraktu pobierany przy zerwaniu. |
| `cancel_penalty_fixed` | Stała kara za zerwanie | Stała kwota pobierana przy zerwaniu. |
| `cancel_reputation_loss` | Utrata reputacji za zerwanie | Ile punktów reputacji gracz traci przy zerwaniu. |
| `cancel_blocks_new_contracts_minutes` | Blokada nowych kontraktów | Czas blokady po zerwaniu kontraktu. |

## Kaucja

| Klucz techniczny | Nazwa w panelu | Opis |
|---|---|---|
| `security_deposit_pct` | Kaucja procentowa | Procent wartości kontraktu pobierany jako kaucja. |
| `security_deposit_fixed` | Kaucja stała | Stała kwota kaucji. |
| `deposit_refund_on_complete` | Zwrot kaucji po wykonaniu | Czy kaucja wróci po wykonaniu kontraktu. |
| `deposit_forfeit_on_cancel` | Kaucja przepada po zerwaniu | Czy gracz traci kaucję, gdy zerwie kontrakt. |
| `deposit_forfeit_on_fail` | Kaucja przepada po porażce | Czy gracz traci kaucję przy niewykonaniu kontraktu. |
| `deposit_partial_refund_enabled` | Częściowy zwrot kaucji | Czy możliwy jest częściowy zwrot kaucji. |

## Reputacja

| Klucz techniczny | Nazwa w panelu | Opis |
|---|---|---|
| `reputation_gain_on_delivery` | Reputacja za dostawę | Punkty reputacji za udaną dostawę. |
| `reputation_gain_on_full_completion` | Reputacja za ukończenie | Punkty reputacji za wykonanie kontraktu. |
| `reputation_gain_on_perfect_contract` | Reputacja za idealne wykonanie | Punkty za kontrakt bez braków. |
| `reputation_loss_on_cancel` | Utrata reputacji za zerwanie | Punkty odejmowane za zerwanie. |
| `reputation_loss_on_contract_failed` | Utrata reputacji za porażkę | Punkty odejmowane za niewykonanie kontraktu. |
| `reputation_loss_on_missed_delivery` | Utrata reputacji za brak dostawy | Punkty odejmowane za pominiętą dostawę. |
| `reputation_loss_on_partial_delivery` | Utrata reputacji za częściową dostawę | Punkty odejmowane za niepełną dostawę. |

## Ubezpieczenie

| Klucz techniczny | Nazwa w panelu | Opis |
|---|---|---|
| `insurance_available` | Ubezpieczenie dostępne | Czy gracz może wykupić ubezpieczenie kontraktu. |
| `insurance_cost_pct` | Koszt ubezpieczenia | Procent wartości kontraktu pobierany za ubezpieczenie. |
| `insurance_penalty_coverage_pct` | Pokrycie kary przez ubezpieczenie | Jaki procent kary pokryje ubezpieczenie. |
| `insurance_requires_min_reputation` | Wymagana reputacja do ubezpieczenia | Minimalna reputacja potrzebna do wykupienia ubezpieczenia. |

---

# 12. Zakładka B2B w panelu admina

Zakładka B2B ma mieć podzakładki:

```text
Pulpit B2B
Oferty B2B
Do sprawdzenia
Gracze B2B
Ustawienia B2B
Logi B2B
```

---

## 12.1 Pulpit B2B

Karty statystyk:

```text
Aktywne zlecenia
Zrealizowane dzisiaj
Anulowane dzisiaj
Wygasłe dzisiaj
Środki zablokowane
Obrót dzisiaj
Kary za anulowanie
Oferty do sprawdzenia
```

Przykład:

```text
Aktywne zlecenia: 12
Środki zablokowane: 3 400 000 PLN
Kary za anulowanie: 170 000 PLN
Obrót dzisiaj: 7 900 000 PLN
```

---

## 12.2 Oferty B2B

Kolumny:

```text
ID
Kupujący
Sprzedający
Status
Ilość ropy
Cena za bbl
Wartość
Zabezpieczenie
Kara anulowania
Zwrot po anulowaniu
Wygasa
Akcje
```

Akcje:

```text
Podejrzyj
Flaguj
Zdejmij flagę
Anuluj i zwróć środki
Anuluj z karą
Oznacz jako nieudane
Pokaż logi
```

Każda akcja admina musi iść przez serwis, nie przez ręczny SQL w widoku.

---

## 12.3 Do sprawdzenia

Lista ofert podejrzanych.

Powody po polsku:

```text
Wysoka wartość
Cena blisko limitu
Częste transakcje między tymi samymi graczami
Częste anulowania
Nietypowy wolumen
```

Admin może:

```text
zostawić flagę
zdjąć flagę
anulować ofertę
zablokować B2B graczowi
```

---

## 12.4 Gracze B2B

Tabela:

```text
Gracz
Oferty kupna
Sprzedaże
Anulowania
Suma kar
Obrót B2B
Flagi
Blokada B2B do
```

Akcje:

```text
Zablokuj B2B
Odblokuj B2B
Pokaż historię B2B
```

---

## 12.5 Ustawienia B2B

Pola po polsku:

```text
Moduł B2B włączony
Minimalna cena względem rynku
Maksymalna cena względem rynku
Minimalna ilość ropy w zleceniu
Maksymalna ilość ropy w zleceniu
Limit aktywnych zleceń na gracza
Domyślny czas ważności zlecenia
Minimalny czas ważności
Maksymalny czas ważności
Kara za anulowanie aktywnego zlecenia
Próg kontroli admina
Oznaczaj ceny blisko limitu
```

---

# 13. Tabele B2B

## `b2b_contract_offers`

```sql
CREATE TABLE IF NOT EXISTS b2b_contract_offers (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,

    buyer_player_id INT NOT NULL,
    seller_player_id INT NULL,

    status ENUM('open','completed','cancelled','expired','failed','flagged') NOT NULL DEFAULT 'open',

    total_bbl DECIMAL(14,2) NOT NULL,
    delivered_bbl DECIMAL(14,2) NOT NULL DEFAULT 0.00,

    price_per_bbl DECIMAL(12,2) NOT NULL,
    total_value DECIMAL(14,2) NOT NULL,

    escrow_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    escrow_status ENUM('none','locked','released','refunded','partial_refund','forfeited') NOT NULL DEFAULT 'none',

    cancel_penalty_pct DECIMAL(8,4) NOT NULL DEFAULT 10.0000,
    cancel_penalty_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    refunded_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,

    min_seller_reputation INT NOT NULL DEFAULT 0,

    expires_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    cancelled_at DATETIME NULL,

    cancel_reason VARCHAR(255) NULL,

    is_flagged TINYINT(1) NOT NULL DEFAULT 0,
    flag_reason VARCHAR(255) NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,

    KEY idx_b2b_offers_status (status, expires_at),
    KEY idx_b2b_offers_buyer (buyer_player_id, status),
    KEY idx_b2b_offers_seller (seller_player_id, status),
    KEY idx_b2b_offers_price (price_per_bbl),
    KEY idx_b2b_offers_flagged (is_flagged, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## `b2b_contract_terms`

Tabela pod łatwe rozszerzenia.

```sql
CREATE TABLE IF NOT EXISTS b2b_contract_terms (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,

    offer_id INT NOT NULL,

    term_key VARCHAR(80) NOT NULL,
    term_type ENUM('number','percent','minutes','text','bool') NOT NULL DEFAULT 'number',

    term_value DECIMAL(14,4) NULL,
    term_text VARCHAR(255) NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_b2b_contract_term (offer_id, term_key),
    KEY idx_b2b_contract_terms_offer (offer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Przykłady przyszłych warunków:

```text
partial_delivery_allowed
seller_reputation_required
linked_npc_contract_id
delivery_deadline_minutes
buyer_cancel_penalty_pct
seller_cancel_penalty_pct
```

## `b2b_contract_logs`

```sql
CREATE TABLE IF NOT EXISTS b2b_contract_logs (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,

    offer_id INT NOT NULL,
    player_id INT NULL,

    event_key VARCHAR(64) NOT NULL,
    message VARCHAR(512) NOT NULL DEFAULT '',
    meta_json TEXT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_b2b_logs_offer (offer_id, created_at),
    KEY idx_b2b_logs_player (player_id, created_at),
    KEY idx_b2b_logs_event (event_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## `b2b_contract_config`

```sql
CREATE TABLE IF NOT EXISTS b2b_contract_config (
    config_key VARCHAR(80) NOT NULL PRIMARY KEY,
    config_value VARCHAR(255) NOT NULL,
    label VARCHAR(160) NOT NULL DEFAULT '',
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

# 14. Konfiguracja B2B — polskie nazwy

| Klucz techniczny | Nazwa w panelu | Opis |
|---|---|---|
| `module_enabled` | Moduł B2B włączony | Gdy wyłączone, gracze nie mogą wystawiać ani przyjmować zleceń B2B. |
| `min_price_market_pct` | Minimalna cena względem rynku | Najniższa dozwolona cena zlecenia jako procent ceny ropy. |
| `max_price_market_pct` | Maksymalna cena względem rynku | Najwyższa dozwolona cena zlecenia jako procent ceny ropy. |
| `min_bbl_per_offer` | Minimalna ilość ropy w zleceniu | Najmniejsze zlecenie, jakie gracz może wystawić. |
| `max_bbl_per_offer` | Maksymalna ilość ropy w zleceniu | Największe zlecenie, jakie gracz może wystawić. |
| `max_open_offers_per_player` | Limit aktywnych zleceń na gracza | Ile aktywnych zleceń kupna może mieć jeden gracz. |
| `default_expiry_minutes` | Domyślny czas ważności zlecenia | Po tym czasie zlecenie wygaśnie, jeśli nikt go nie przyjmie. |
| `min_expiry_minutes` | Minimalny czas ważności | Najkrótszy czas, jaki gracz może ustawić. |
| `max_expiry_minutes` | Maksymalny czas ważności | Najdłuższy czas, jaki gracz może ustawić. |
| `buyer_cancel_penalty_pct` | Kara za anulowanie aktywnego zlecenia | Procent wartości zamówienia potrącany przy anulowaniu. |
| `admin_review_threshold_value` | Próg kontroli admina | Oferty powyżej tej wartości zostaną oznaczone do sprawdzenia. |
| `flag_price_near_limit` | Oznaczaj ceny przy limicie | System oznaczy oferty z ceną blisko minimum albo maksimum. |

Domyślne wartości:

```text
module_enabled = 1
min_price_market_pct = 70
max_price_market_pct = 130
min_bbl_per_offer = 100
max_bbl_per_offer = 50000
max_open_offers_per_player = 5
default_expiry_minutes = 1440
min_expiry_minutes = 60
max_expiry_minutes = 10080
buyer_cancel_penalty_pct = 10
admin_review_threshold_value = 5000000
flag_price_near_limit = 1
```

---

# 15. Serwis B2B

Plik:

```text
src/B2BContractService.php
```

Metody:

```php
class B2BContractService
{
    public const CFG_MODULE_ENABLED = 'b2b_contracts_module_enabled';

    public function __construct(private ?PDO $db = null);

    public function isModuleEnabled(): bool;

    public function setModuleEnabled(bool $enabled): void;

    public function getConfig(): array;

    public function saveConfig(array $data): void;

    public function listOpenOffers(int $viewerPlayerId, int $limit = 50, int $offset = 0): array;

    public function countOpenOffers(int $viewerPlayerId): int;

    public function listMyBuyOffers(int $buyerPlayerId, int $limit, int $offset): array;

    public function countMyBuyOffers(int $buyerPlayerId): int;

    public function listMySales(int $sellerPlayerId, int $limit, int $offset): array;

    public function countMySales(int $sellerPlayerId): int;

    public function createBuyOffer(
        int $buyerPlayerId,
        float $bbl,
        float $pricePerBbl,
        int $expiresMinutes,
        int $minSellerReputation = 0
    ): array;

    public function cancelBuyOffer(int $buyerPlayerId, int $offerId, string $reason = ''): array;

    public function acceptAndDeliver(int $sellerPlayerId, int $offerId): array;

    public function expireOpenOffers(DateTime $now): array;

    public function adminCancelOffer(int $adminId, int $offerId, string $reason): array;

    public function adminFlagOffer(int $adminId, int $offerId, string $reason): array;

    public function adminUnflagOffer(int $adminId, int $offerId): array;

    private function validatePriceAgainstMarket(float $pricePerBbl): bool;

    private function calculateCancelPenalty(float $totalValue): float;

    private function lockEscrow(int $buyerPlayerId, float $amount, int $offerId): void;

    private function refundEscrow(int $buyerPlayerId, float $amount, int $offerId): void;

    private function chargeCancelPenalty(int $buyerPlayerId, float $amount, int $offerId): void;

    private function releaseEscrowToSeller(int $sellerPlayerId, float $amount, int $offerId): void;

    private function logEvent(int $offerId, ?int $playerId, string $eventKey, string $message, array $meta = []): void;
}
```

---

# 16. Typy finansowe

Dodać do `FinancialTransactionService`:

```php
public const TYPE_B2B_ESCROW_LOCK = 'b2b_escrow_lock';
public const TYPE_B2B_ESCROW_REFUND = 'b2b_escrow_refund';
public const TYPE_B2B_CANCEL_PENALTY = 'b2b_cancel_penalty';
public const TYPE_B2B_TRADE_REVENUE = 'b2b_trade_revenue';
```

Routing pul w `WalletConfig`:

```php
'b2b_escrow_lock' => self::POOL_BANK,
'b2b_escrow_refund' => self::POOL_BANK,
'b2b_cancel_penalty' => self::POOL_BANK,
'b2b_trade_revenue' => self::POOL_BANK,
```

Nie robić ręcznego:

```php
UPDATE players SET cash = ...
UPDATE players SET bank_balance = ...
```

Wszystko przez `FinancialTransactionService`.

---

# 17. Zabezpieczenia

## Cena względem rynku

Cena B2B nie może być dowolna.

Domyślnie:

```text
minimalna cena = 70% ceny rynku
maksymalna cena = 130% ceny rynku
```

Przykład:

```text
Cena rynku: 100 PLN
Minimalna cena B2B: 70 PLN
Maksymalna cena B2B: 130 PLN
```

## Zakaz własnych ofert

Sprzedający nie może przyjąć własnego zlecenia:

```text
buyer_player_id != seller_player_id
```

## Limit aktywnych zleceń

Domyślnie:

```text
5 aktywnych zleceń na gracza
```

## Limit wolumenu

Domyślnie:

```text
minimum: 100 bbl
maksimum: 50 000 bbl
```

## Zabezpieczenie środków

Pełna wartość zamówienia musi być blokowana przy wystawieniu.

## Blokady transakcyjne

Przy akceptacji oferty:

```text
oferta pobierana FOR UPDATE
magazyn sprzedającego pobierany FOR UPDATE
```

Dwa równoległe kliknięcia nie mogą rozliczyć tej samej oferty dwa razy.

---

# 18. Logi B2B

Każda ważna akcja musi mieć log.

Eventy:

```text
offer_created
escrow_locked
offer_cancelled
cancel_penalty_charged
escrow_refunded
offer_expired
offer_completed
delivery_completed
escrow_released
offer_flagged
admin_cancelled
admin_refunded
```

Log powinien zawierać:

```text
offer_id
player_id
event_key
message
meta_json
created_at
```

---

# 19. Testy

Minimalny zestaw testów:

```text
1. Schema tworzy tabele.
2. Moduł można włączyć i wyłączyć.
3. Kupujący wystawia ofertę, a środki zostają zablokowane.
4. Nie można wystawić oferty bez środków.
5. Nie można wystawić oferty z ceną poza limitem rynku.
6. Nie można przekroczyć limitu aktywnych ofert.
7. Sprzedający nie może przyjąć własnej oferty.
8. Sprzedający bez wystarczającej ilości ropy nie może przyjąć oferty.
9. Przyjęcie oferty pobiera ropę tylko raz.
10. Przyjęcie oferty wypłaca środki sprzedającemu tylko raz.
11. Anulowanie aktywnego zlecenia pobiera 10% kary i zwraca 90% zabezpieczenia.
12. Wygasłe zlecenie zwraca 100% zabezpieczenia.
13. Tick wygasza oferty po expires_at.
14. Admin może anulować ofertę i zapisać log.
15. Admin może flagować i zdejmować flagę.
16. Dwa równoległe requesty nie mogą rozliczyć tej samej oferty dwa razy.
17. Paginacja działa osobno dla rynku B2B, moich zleceń, historii i logów.
18. Panel admina nie pokazuje technicznych kluczy w trybie prostym.
```

Najważniejszy test MySQL:

```text
Dwa równoległe requesty próbują przyjąć tę samą ofertę.

Tylko jeden może wygrać.
Ropa schodzi tylko raz.
Zabezpieczenie wypłaca się tylko raz.
Drugi request dostaje komunikat, że oferta nie jest już dostępna.
```

---

# 20. Kolejność wdrożenia

## Etap 1 — baza i serwis

```text
B2BContractSchema
B2BContractService
b2b_contract_offers
b2b_contract_terms
b2b_contract_logs
b2b_contract_config
```

## Etap 2 — finanse i zabezpieczenie środków

```text
b2b_escrow_lock
b2b_escrow_refund
b2b_cancel_penalty
b2b_trade_revenue
```

## Etap 3 — UI gracza w kontraktach

```text
zakładki:
Systemowe
Rynek B2B
Moje B2B
Historia
Logi
```

## Etap 4 — panel admina

```text
nowy wygląd jak prototyp HTML,
polskie nazwy,
karty statystyk,
grupowanie warunków,
tryb prosty i zaawansowany,
zakładka B2B.
```

## Etap 5 — tick

```text
B2BContractsModule
wygaszanie ofert
zwrot zabezpieczenia
statystyki ticka
```

## Etap 6 — testy i zabezpieczenia

```text
testy serwisowe,
testy finansów,
testy MySQL concurrency,
testy paginacji,
testy akcji admina.
```

---

# 21. Najważniejsze zasady dla Codexa

```text
1. B2B ma być podpięte pod istniejący ekran kontraktów gracza.
2. Nie tworzyć osobnej pozycji w menu gracza.
3. Logika B2B ma być w osobnym serwisie.
4. Panel admina ma mieć polskie, proste nazwy.
5. Surowe klucze techniczne pokazywać tylko w trybie zaawansowanym.
6. Dodać paginację do każdej długiej listy.
7. Kara za anulowanie ma być opisana jako: Kara za anulowanie aktywnego zlecenia.
8. Domyślna kara za anulowanie: 10% wartości zamówienia.
9. Każda operacja finansowa przez FinancialTransactionService.
10. Każde rozliczenie i każda akcja admina musi mieć log.
11. Panel admina ma wyglądać jak prototyp: prototyp-panel-admin-kontrakty-b2b.html.
12. Warunki kontraktów mają być pogrupowane i opisane po polsku.
13. B2B ma mieć osobną sekcję w panelu admina: Pulpit B2B, Oferty B2B, Do sprawdzenia, Gracze B2B, Ustawienia B2B, Logi B2B.
```

---

# 22. Najkrótsze polecenie do wdrożenia

```text
Rozbuduj istniejący moduł kontraktów o prosty moduł B2B firma–firma.

B2B ma być widoczne w obecnym ekranie kontraktów jako zakładki:
Systemowe, Rynek B2B, Moje B2B, Historia, Logi.

Logika B2B ma być osobnym serwisem:
B2BContractSchema
B2BContractService
B2BContractsModule implements TickModule

Dodaj:
b2b_contract_offers
b2b_contract_terms
b2b_contract_logs
b2b_contract_config

Mechanika:
kupujący wystawia zlecenie kupna ropy,
system blokuje pełną wartość zamówienia,
sprzedający przyjmuje i dostarcza ropę,
system wypłaca środki sprzedającemu,
kupujący może anulować aktywne zlecenie,
anulowanie pobiera 10% wartości zamówienia,
wygasłe zlecenie zwraca 100% środków.

Panel admina ma być przebudowany na prosty i czytelny:
polskie nazwy pól,
opisy przy polach,
grupowanie warunków,
tryb prosty i tryb zaawansowany,
zakładka B2B z pulpitem, ofertami, flagami, graczami, ustawieniami i logami.

Dodać paginację do:
dostępnych kontraktów,
aktywnych kontraktów,
rynku B2B,
moich zleceń B2B,
moich sprzedaży B2B,
historii,
logów.

Kara za anulowanie aktywnego zlecenia B2B ma wynosić domyślnie 10% wartości zamówienia.
```
# Status wdrozenia 2026-07-08

Etap P1 zostal wdrozony w istniejacym module `/contracts`.

Wdrozone:

- osobny schemat i konfiguracja B2B: `b2b_contract_offers`, `b2b_contract_terms`, `b2b_contract_logs`, `b2b_contract_config`,
- `B2BContractService`: create, cancel, accept/deliver, expire, admin flag/unflag/cancel, listy i logi,
- escrow przez `FinancialTransactionService`: blokada 100%, zwrot 90% przy anulowaniu kupujacego, zwrot 100% przy wygasnieciu/admin cancel, wyplata sprzedawcy,
- zakladki gracza w `/contracts`: Systemowe, Rynek B2B, Moje B2B, Historia, Logi,
- zakladka admina w `admin/contracts.php`: pulpit, ustawienia, oferty, akcje admina, logi,
- tick module `B2BContractsModule` z `key=b2b_contracts`, `order=100`,
- testy integracyjne, MySQL i rejestru ticka.

Zakres swiadomie odlozony:

- czesciowe dostawy,
- aukcje,
- podkontrakty,
- osobna reputacja B2B,
- rozbudowane klauzule i negocjacje.
