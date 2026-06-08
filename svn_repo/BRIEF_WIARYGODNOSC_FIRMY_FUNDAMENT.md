# BRIEF DLA AI — Wiarygodność firmy: fundament systemu

## Cel wdrożenia

Wdrażamy teraz podstawowy system **wiarygodności firmy**.

To ma być ogólny wskaźnik reputacji firmy wobec świata gry. Nie zastępuje istniejących systemów banku ani czarnego rynku.

Istniejące wskaźniki zostają bez zmian:

- `credit_score` — ocena kredytowa pod bank i kredyty,
- `bank_trust_scores` — ukryte zaufanie banku do negocjacji,
- `black_market_score` — podejrzaność / ślad po czarnym rynku,
- `company_credibility` — nowa ogólna wiarygodność firmy.

Ten system ma być fundamentem pod późniejszy dział prawny, trudniejsze regiony, kontrakty, partnerów i przetargi.

Na teraz wdrażamy tylko bazę: pole, logi, serwis, widok dla gracza, widok admina i kilka pierwszych zdarzeń.

---

## 1. Co wdrażamy teraz

### 1.1. Nowe pole w graczach

Dodać do tabeli graczy nowe pole:

```sql
company_credibility INT UNSIGNED NOT NULL DEFAULT 50
```

Skala:

**0–100**

Wartość startowa:

**50**

Znaczenie:

50 oznacza neutralną firmę. Gracz nie jest jeszcze bardzo wiarygodny, ale też nie jest traktowany jako ryzykowny.

Wynik nigdy nie może spaść poniżej 0 ani przekroczyć 100.

---

## 2. Poziomy wiarygodności

Dodać progi opisowe:

- 0–19 — krytyczna,
- 20–39 — niska,
- 40–59 — chwiejna,
- 60–79 — stabilna,
- 80–100 — wysoka.

### Opisy dla gracza

#### Krytyczna

Firma jest postrzegana jako bardzo ryzykowna. Część instytucji może ograniczać współpracę.

#### Niska

Firma ma słabą wiarygodność. Niektóre działania mogą być trudniejsze albo droższe.

#### Chwiejna

Firma działa, ale jej sytuacja nie jest jeszcze stabilna.

#### Stabilna

Firma jest postrzegana jako wiarygodna i przewidywalna.

#### Wysoka

Firma ma bardzo dobrą pozycję i może w przyszłości łatwiej uzyskiwać dostęp do trudniejszych regionów, umów i partnerów.

---

## 3. Tabela historii zmian

Dodać tabelę:

```sql
company_credibility_log
```

Minimalne pola:

```sql
id
player_id
event_key
delta
score_before
score_after
note
created_at
```

### Cel tabeli

Historia zmian jest obowiązkowa.

Bez historii ten system stanie się nieczytelny.

Admin musi widzieć:

- co zmieniło wynik,
- kiedy wynik się zmienił,
- o ile się zmienił,
- jaki był wynik przed zmianą,
- jaki jest wynik po zmianie.

---

## 4. Serwis

Dodać serwis:

```php
src/CompanyCredibilityService.php
```

Serwis ma odpowiadać za całą logikę wiarygodności firmy.

### Serwis ma robić:

1. pobrać aktualny wynik gracza,
2. zwrócić poziom opisowy,
3. zmienić wynik,
4. ograniczyć wynik do zakresu 0–100,
5. zapisać każdą zmianę w `company_credibility_log`.

### Ważna zasada

Żadna część gry nie powinna ręcznie zmieniać `company_credibility`.

Wszystkie zmiany muszą przechodzić przez `CompanyCredibilityService`.

---

## 5. Metody serwisu

Serwis powinien mieć proste metody.

Przykładowo:

```php
getScore(int $playerId): int
```

Zwraca aktualny wynik.

```php
getLevel(int $score): string
```

Zwraca poziom opisowy:

- krytyczna,
- niska,
- chwiejna,
- stabilna,
- wysoka.

```php
changeScore(int $playerId, int $delta, string $eventKey, ?string $note = null): void
```

Zmienia wynik i zapisuje log.

```php
logChange(int $playerId, string $eventKey, int $delta, int $before, int $after, ?string $note = null): void
```

Zapisuje historię zmiany.

Nazwy metod mogą być dopasowane do obecnego stylu kodu, ale zakres odpowiedzialności ma zostać taki sam.

---

## 6. Eventy do obsługi teraz

Na tym etapie podpinamy tylko kilka najważniejszych zdarzeń, żeby system zaczął żyć, ale nie rozregulował gry.

### 6.1. Zdarzenia negatywne

Podpiąć:

#### Wykrycie czarnego rynku

Event:

```text
black_market_detected
```

Delta:

```text
-12
```

#### Aktywacja komornika

Event:

```text
bailiff_activated
```

Delta:

```text
-20
```

#### Bankructwo

Event:

```text
bankruptcy_entered
```

Delta:

```text
-25
```

#### Złamany plan naprawczy

Event:

```text
recovery_plan_broken
```

Delta:

```text
-10
```

#### Duże opóźnienie w spłacie

Event:

```text
major_payment_delay
```

Delta:

```text
-6
```

### 6.2. Zdarzenia pozytywne

Podpiąć:

#### Terminowa spłata raty

Event:

```text
loan_installment_paid_on_time
```

Delta:

```text
+2
```

#### Pełna spłata kredytu

Event:

```text
loan_fully_repaid
```

Delta:

```text
+8
```

#### Spłata kredytu przed czasem

Event:

```text
loan_repaid_early
```

Delta:

```text
+6
```

#### Dłuższy okres bez naruszeń

Event:

```text
clean_operation_period
```

Delta:

```text
+3
```

Ten event można wdrożyć później, jeśli nie ma jeszcze prostego miejsca, gdzie da się go bezpiecznie podpiąć.

---

## 7. Widok dla gracza

Dodać na dashboardzie małą kartę:

**Wiarygodność firmy**

Pokazać:

- wynik, np. `62 / 100`,
- poziom opisowy, np. `stabilna`,
- krótki opis.

Przykład:

**Wiarygodność firmy: 62 / 100**  
**Status: stabilna**  
**Firma jest postrzegana jako wiarygodna i przewidywalna.**

### Ważne

Gracz nie ma widzieć pełnej matematyki.

Nie pokazywać mu całej listy algorytmów i przeliczników.

Na tym etapie wystarczy:

- wynik,
- opis,
- krótka informacja, że wynik zależy od stabilności finansowej, banku, naruszeń i działań ryzykownych.

---

## 8. Widok w panelu admina

Dodać w panelu admina podgląd wiarygodności firmy.

Admin ma widzieć:

- gracza,
- aktualny wynik,
- poziom opisowy,
- historię zmian z tabeli `company_credibility_log`.

Historia powinna pokazywać:

- data,
- `event_key`,
- delta,
- wynik przed,
- wynik po,
- notatka.

### Ręczna korekta admina

Admin może ręcznie zmienić wiarygodność firmy, ale tylko przez modal potwierdzenia.

Modal powinien wymagać:

- wartości zmiany,
- powodu / notatki.

Przykład modala:

**Ręczna korekta wiarygodności firmy**

**Podaj zmianę wyniku oraz powód korekty. Ta operacja zostanie zapisana w historii.**

Pola:

- zmiana wyniku,
- notatka.

Przyciski:

**Anuluj**  
**Zapisz korektę**

Event dla ręcznej korekty:

```text
admin_manual_adjustment
```

---

## 9. Powiadomienia

Na tym etapie powiadomienia dla gracza można zrobić ostrożnie.

Nie każda mała zmiana musi generować powiadomienie.

Powiadomienie dawać tylko przy większych zmianach, np. gdy delta ma wartość minimum 5 punktów w dół albo w górę.

Przykłady:

**Wiarygodność firmy spadła po wykryciu ryzykownych działań.**

**Wiarygodność firmy wzrosła po pełnej spłacie kredytu.**

**Wiarygodność firmy mocno spadła po aktywacji komornika.**

Powiadomienia mają działać tak jak w dziale technicznym, czyli przez istniejący system powiadomień, a nie przez zwykłe alerty.

---

## 10. Modale i komunikaty

Wszystkie komunikaty i potwierdzenia w panelu admina mają korzystać ze wspólnego systemu modali.

Nie używać:

- `alert()`,
- `confirm()`,
- `prompt()`.

Chyba że jako awaryjny fallback, jeśli projekt już tak robi w wyjątkowych miejscach.

---

## 11. Czego nie zmieniać

Nie nadpisywać `credit_score`.

Nie usuwać `bank_trust_scores`.

Nie modyfikować bezpośrednio `black_market_score`.

Nie mieszać tych systemów w jedno pole.

`company_credibility` ma być osobnym, nadrzędnym wskaźnikiem.

Może korzystać z wydarzeń z banku i czarnego rynku, ale nie zastępuje ich.

---

## 12. Testy

Dodać testy lub przynajmniej scenariusze testowe dla poniższych przypadków.

### 12.1. Zakres wyniku

Sprawdzić, że wynik nie spada poniżej 0.

Sprawdzić, że wynik nie rośnie powyżej 100.

### 12.2. Logowanie zmian

Po każdej zmianie musi powstać wpis w `company_credibility_log`.

### 12.3. Zmiana pozytywna

Przykład:

gracz spłaca kredyt, wynik rośnie, log zostaje zapisany.

### 12.4. Zmiana negatywna

Przykład:

wykrycie czarnego rynku, wynik spada, log zostaje zapisany.

### 12.5. Widok admina

Admin widzi aktualny wynik i historię zmian.

### 12.6. Ręczna korekta

Admin może zmienić wynik tylko z notatką, a zmiana zapisuje się w historii.

---

## 13. Co wdrażamy teraz

Wdrażamy teraz:

1. pole `company_credibility` w `players`,
2. tabelę `company_credibility_log`,
3. `CompanyCredibilityService`,
4. progi opisowe 0–100,
5. kartę na dashboardzie gracza,
6. podgląd w panelu admina,
7. ręczną korektę w panelu admina,
8. logowanie każdej zmiany,
9. pierwsze podpięcia do banku, komornika, bankructwa i czarnego rynku,
10. podstawowe testy.

---

## 14. Czego nie wdrażamy teraz

Nie wdrażać teraz:

- wpływu na zezwolenia regionalne,
- wpływu na dział prawny,
- wpływu na trudniejsze regiony,
- wpływu na kontrakty,
- wpływu na przetargi,
- łapówek,
- audytów,
- partnerów biznesowych,
- złożonych zależności z offshore,
- pełnej automatycznej odbudowy wyniku,
- pełnego systemu kar prawnych.

To wszystko zostaje jako TODO.

---

## 15. TODO — kolejne etapy

### 15.1. Podpięcie pod dział prawny

W przyszłości dział prawny może wymagać minimalnej wiarygodności firmy przy trudniejszych regionach.

Przykład:

region wysokiego ryzyka wymaga:

- kapitału,
- poziomu działu prawnego,
- wiarygodności firmy minimum 60 / 100.

### 15.2. Podpięcie pod bank

Bank może używać `company_credibility` jako dodatkowego modyfikatora.

Nie zastępuje to `credit_score`.

### 15.3. Kontrakty i partnerzy

Przyszłe kontrakty mogą wymagać określonej wiarygodności firmy.

### 15.4. Przetargi i offshore

Duże projekty, offshore i specjalne inwestycje mogą wymagać wysokiej wiarygodności firmy.

### 15.5. Łapówki i czarny rynek

W przyszłości łapówki mogą wpływać negatywnie na wiarygodność firmy, jeśli zostaną wykryte.

### 15.6. Odbudowa wiarygodności

W przyszłości można dodać powolną odbudowę wyniku przy stabilnej, legalnej działalności.

---

## 16. Najkrótsza wersja dla AI

Wdrażamy teraz fundament systemu `company_credibility`. To ogólna wiarygodność firmy w skali 0–100, startująca od 50. Nie zastępuje `credit_score`, `bank_trust_scores` ani `black_market_score`. Trzeba dodać pole w `players`, tabelę historii, serwis, kartę na dashboardzie, widok admina, ręczną korektę admina i pierwsze zdarzenia wpływające na wynik. Każda zmiana musi być logowana. Wpływ na dział prawny, regiony, kontrakty i inne moduły zostaje jako TODO na później.
