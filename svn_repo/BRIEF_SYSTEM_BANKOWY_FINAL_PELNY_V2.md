
# BRIEF DLA AI — SYSTEM KONT BANKOWYCH I PRZELEWÓW (WERSJA FINALNA + API FINANSOWE)

## Cel wdrożenia

Wdrażamy pierwszy etap systemu bankowego.

Firma otrzymuje własny numer rachunku bankowego oraz możliwość wykonywania przelewów między graczami.

Nie tworzymy nowego systemu pieniędzy.

Nie tworzymy osobnej tabeli kont bankowych.

Obecne saldo firmy pozostaje głównym źródłem prawdy.

Celem tego etapu jest:

- numer rachunku bankowego,
- przelewy między graczami,
- historia operacji,
- panel bankowy,
- powiadomienia,
- panel administracyjny,
- fundament pod przyszłe API finansowe.

---

## Założenia architektoniczne

Najważniejsza zasada:

- nie tworzyć tabeli player_bank_accounts,
- nie tworzyć drugiego salda,
- nie migrować pieniędzy,
- nie duplikować środków.

Aktualne pole pieniędzy używane przez grę pozostaje bez zmian.

To pole oznacza saldo konta bankowego firmy.

---

## Nowe pole w tabeli graczy

bank_account_number VARCHAR(32) UNIQUE NULL

Numer rachunku:

- unikalny,
- generowany automatycznie,
- niezmienny,
- widoczny dla gracza.

Przykład:

OC-000001-2026
OC-000002-2026
OC-000003-2026

---

## Migracja istniejących graczy

Dla każdego istniejącego gracza:

- wygenerować numer rachunku,
- zapisać numer rachunku.

Nie zmieniać salda.

Nie przenosić pieniędzy.

---

## Historia operacji

Tabela:

bank_transactions

Pola:

id
from_player_id
to_player_id
amount
transaction_type
description
reference_type
reference_id
created_at

Typy operacji:

player_transfer
loan
loan_payment
market_sale
tax
well_purchase
hub_purchase
pipeline_purchase
legal_fee
admin_adjustment

Każda operacja finansowa musi być logowana.

Bez wyjątków.

---

## Panel gracza

Pokazać:

- numer rachunku,
- aktualne saldo,
- historię operacji,
- przycisk wykonania przelewu.

---

## Formularz przelewu

Gracz podaje:

- numer rachunku odbiorcy,
- kwotę,
- opis.

System sprawdza:

- czy konto istnieje,
- czy nie jest własnym kontem,
- czy kwota jest większa od zera,
- czy gracz posiada środki.

Po zatwierdzeniu:

- pobierz środki,
- dodaj środki odbiorcy,
- zapisz historię,
- wyślij powiadomienia.

Operacja musi być wykonywana transakcyjnie.

---

## Powiadomienia

Nadawca:

Przelew został wysłany.

Odbiorca:

Otrzymano przelew od firmy XYZ.

Pokazać:

- kwotę,
- datę,
- opis.

---

## Integracja z istniejącym systemem

Na tym etapie nie przebudowywać ekonomii gry.

System ma korzystać z obecnego salda.

Tam gdzie możliwe zapisywać operacje:

- sprzedaż ropy,
- kredyty,
- raty,
- podatki,
- odwierty,
- huby,
- rurociągi,
- dział prawny.

---

## Panel administratora

Administrator widzi:

- numer rachunku,
- właściciela,
- saldo,
- historię operacji.

Administrator może:

- dodać środki,
- odjąć środki.

Każda korekta:

- modal,
- obowiązkowa notatka,
- wpis do historii.

Typ:

admin_adjustment

---

## NOWY FUNDAMENT — API FINANSOWE

To jest bardzo ważne.

Od tego momentu nie należy wykonywać:

money +=
money -=

bezpośrednio w nowych modułach.

Należy przygotować wspólny serwis:

BankService

lub

FinancialTransactionService

który będzie jedynym miejscem odpowiedzialnym za ruch środków.

---

## Zadania serwisu

Serwis ma obsługiwać:

- pobranie środków,
- dodanie środków,
- przelew między graczami,
- logowanie operacji,
- walidację salda,
- tworzenie historii.

Przykładowe metody:

credit()

debit()

transfer()

logTransaction()

---

## Dlaczego to ważne

Przyszłe systemy:

- dział prawny,
- kary,
- podatki,
- kontrakty,
- pożyczki,
- aukcje,
- przelewy,
- dywidendy,
- komornik,
- gotówka,

nie powinny modyfikować pieniędzy bezpośrednio.

Wszystko powinno przechodzić przez jeden serwis.

---

## Przyszłe integracje

W przyszłości przez BankService mają przechodzić:

- zakup odwiertu,
- zakup huba,
- zakup rurociągu,
- zakup licencji,
- kary prawne,
- podatki,
- dywidendy,
- odsetki,
- kredyty,
- czarny rynek,
- gotówka.

---

## Audyt przed wdrożeniem

AI ma:

1. znaleźć pole pieniędzy używane przez grę,
2. znaleźć wszystkie miejsca odpowiedzialne za operacje finansowe,
3. przygotować raport,
4. dopiero rozpocząć wdrożenie.

---

## Testy

Sprawdzić:

- generowanie numerów rachunków,
- unikalność numerów,
- przelewy,
- blokadę przelewu na własne konto,
- blokadę przelewu przy braku środków,
- historię operacji,
- powiadomienia,
- poprawność BankService.

---

## Czego nie wdrażamy teraz

Nie wdrażać:

- gotówki,
- kart płatniczych,
- blokad komorniczych,
- lokat,
- kont walutowych,
- pożyczek między graczami,
- opłat bankowych.

---

## TODO — Etap 2

Po stabilizacji:

- gotówka,
- wpłaty do banku,
- wypłaty z banku,
- płatności gotówką,
- zajęcia komornicze,
- limity przelewów,
- kontrole finansowe,
- pożyczki między graczami.

---

## Najkrótsza wersja dla AI

Nie tworzyć nowej tabeli kont bankowych. Obecne saldo pozostaje źródłem prawdy. Dodać numer rachunku do gracza, historię operacji, przelewy między graczami oraz BankService jako centralny punkt wszystkich operacji finansowych wykonywanych w przyszłości.
