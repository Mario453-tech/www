# BRIEF DLA AI — Uniwersalny moduł ochrony z konfigurowalnymi opcjami i efektami

## Cel

Stworzyć uniwersalny moduł ochrony, który pozwala łatwo dodawać nowe opcje ochrony i nowe efekty bez przepisywania logiki w kodzie.

System ma działać dla różnych elementów gry:

- transport drogowy,
- odwiert,
- hub,
- rurociąg,
- magazyn,
- port,
- terminal.

Na start wdrażamy tylko transport drogowy, ale architektura ma być przygotowana pod kolejne moduły.

---

## 1. Główna zasada

Nie kodować opcji ochrony na sztywno w PHP.

Opcje ochrony mają być definiowane w bazie danych i edytowane z panelu admina.

Admin ma móc dodać nową ochronę bez zmiany kodu, np.:

- Eskorta podstawowa,
- Konwój uzbrojony,
- Patrol dronami,
- Ochrona odwiertu,
- Monitoring huba,
- Patrol rurociągu.

---

## 2. Struktura systemu

System ma składać się z kilku części:

- `ProtectionService` — główny silnik ochrony,
- `protection_options` — definicje opcji ochrony,
- `protection_effects` — efekty przypisane do opcji ochrony,
- `active_protections` — aktywne ochrony wykupione przez graczy,
- `protection_logs` — historia ochrony.

---

## 3. ProtectionService

Dodać serwis:

```php
src/ProtectionService.php
```

albo:

```php
src/Security/ProtectionService.php
```

Serwis ma odpowiadać za:

- pobranie dostępnych opcji ochrony,
- wyliczenie kosztu,
- aktywację ochrony,
- sprawdzenie aktywnej ochrony dla celu,
- zastosowanie efektów ochrony,
- zapis historii,
- wysłanie powiadomień.

Żaden moduł gry nie powinien samodzielnie liczyć efektów ochrony poza `ProtectionService`.

---

## 4. Tabela protection_options

Tabela przechowuje definicje opcji ochrony.

Przykładowe pola:

```sql
id
code
name
description
target_type
context
is_active
cost_type
cost_value
cost_currency
duration_minutes
requires_cash
requires_bank
min_company_credibility
min_legal_level
sort_order
created_at
updated_at
```

### Znaczenie pól

`code` — techniczny kod opcji, np. `basic_escort`, `armed_convoy`, `drone_patrol`.

`name` — nazwa widoczna dla gracza.

`description` — prosty opis dla gracza.

`target_type` — typ celu, do którego można użyć ochrony.

Przykłady:

```text
road_transport
well
hub
pipeline
warehouse
port
terminal
```

`context` — kontekst użycia ochrony.

Przykłady:

```text
road_transport_guard
well_security
hub_security
pipeline_patrol
warehouse_security
```

`cost_type` — sposób liczenia kosztu.

Przykłady:

```text
fixed
percent_reference
per_hour
per_bbl
```

`cost_value` — wartość kosztu.

`cost_currency` — źródło płatności.

Przykłady:

```text
cash
bank
both
```

`duration_minutes` — jak długo działa ochrona.

`requires_cash` — czy wymaga gotówki.

`requires_bank` — czy może być opłacona z konta.

`min_company_credibility` — minimalna wiarygodność firmy, jeśli ma być wymagana.

`min_legal_level` — minimalny poziom działu prawnego lub zgody, jeśli będzie kiedyś potrzebny.

---

## 5. Tabela protection_effects

Efekty ochrony mają być osobno, żeby można było łatwo dodawać nowe efekty.

Przykładowe pola:

```sql
id
protection_option_id
effect_key
effect_type
effect_value
created_at
updated_at
```

### Przykładowe effect_key

```text
theft_risk_mult
attack_risk_mult
sabotage_risk_mult
loss_mult
delay_risk_mult
incident_risk_mult
damage_mult
detection_risk_mult
black_market_score_delta
company_credibility_delta
equipment_theft_risk_mult
```

### Przykłady efektów

#### Eskorta podstawowa

```text
theft_risk_mult = 0.80
attack_risk_mult = 0.85
sabotage_risk_mult = 1.00
```

#### Konwój uzbrojony

```text
theft_risk_mult = 0.55
attack_risk_mult = 0.60
sabotage_risk_mult = 0.85
```

#### Patrol dronami

```text
sabotage_risk_mult = 0.70
detection_risk_mult = 0.80
delay_risk_mult = 0.95
```

#### Ochrona odwiertu

```text
sabotage_risk_mult = 0.65
attack_risk_mult = 0.75
equipment_theft_risk_mult = 0.60
```

---

## 6. Tabela active_protections

Tabela zapisuje aktywne ochrony wykupione przez graczy.

Przykładowe pola:

```sql
id
player_id
protection_option_id
target_type
target_id
context
paid_from
cost
starts_at
ends_at
status
meta_json
created_at
updated_at
```

Statusy:

```text
active
expired
cancelled
failed
```

`paid_from`:

```text
cash
bank
```

---

## 7. Tabela protection_logs

Tabela zapisuje historię ochrony.

Przykładowe pola:

```sql
id
player_id
protection_option_id
target_type
target_id
context
event_key
amount
message
meta_json
created_at
```

Przykłady `event_key`:

```text
protection_activated
protection_expired
protection_applied_to_incident
protection_failed
protection_cancelled
```

---

## 8. Metody ProtectionService

### getAvailableOptions

```php
getAvailableOptions(int $playerId, string $targetType, string $context): array
```

Zwraca listę ochron dostępnych dla gracza i danego celu.

Sprawdza:

- czy opcja jest aktywna,
- czy pasuje do `target_type`,
- czy pasuje do `context`,
- czy gracz spełnia wymagania,
- czy ma gotówkę lub środki na koncie,
- czy nie przekracza limitów.

### quote

```php
quote(int $playerId, string $optionCode, float $referenceValue, string $targetType, int $targetId): array
```

Zwraca koszt i efekty do UI.

### activate

```php
activate(
    int $playerId,
    string $optionCode,
    string $targetType,
    int $targetId,
    float $referenceValue,
    array $meta = []
): array
```

Aktywuje ochronę.

Robi:

- sprawdza opcję,
- wylicza koszt,
- pobiera środki,
- zapisuje `active_protections`,
- zapisuje `protection_logs`,
- wysyła powiadomienie.

### getActiveEffects

```php
getActiveEffects(int $playerId, string $targetType, int $targetId, string $context): array
```

Zwraca aktywne efekty ochrony dla danego celu.

### applyEffects

```php
applyEffects(array $baseRisks, array $effects): array
```

Nakłada efekty ochrony na bazowe ryzyka.

---

## 9. Panel admina

Dodać panel:

**Admin → Ochrona**

Zakładki:

### Opcje ochrony

Admin może:

- dodać nową opcję ochrony,
- edytować nazwę,
- edytować opis,
- ustawić koszt,
- ustawić czas działania,
- ustawić typ celu,
- ustawić kontekst,
- włączyć lub wyłączyć opcję,
- ustawić źródło płatności: gotówka, konto, oba.

### Efekty ochrony

Admin może dodać efekty do opcji.

Przykład:

```text
effect_key: theft_risk_mult
effect_value: 0.55
```

### Aktywne ochrony

Admin widzi:

- gracza,
- typ ochrony,
- cel,
- czas startu,
- czas końca,
- status.

### Historia ochrony

Admin widzi:

- kto wykupił ochronę,
- co wykupił,
- ile zapłacił,
- na co działało,
- czy ochrona zadziałała przy incydencie.

---

## 10. UI gracza

Przy celu, który może mieć ochronę, pokazać przycisk:

**Dodaj ochronę**

Po kliknięciu modal:

**Wybierz ochronę**

Lista opcji pokazuje:

- nazwę,
- opis,
- koszt,
- czas działania,
- źródło płatności,
- prosty opis efektu.

Przykład:

**Konwój uzbrojony**  
Zmniejsza ryzyko kradzieży i napadu podczas kursu.  
Koszt: 500 000 PLN gotówką  
Czas działania: 1 kurs / 60 minut

Przyciski:

- **Anuluj**
- **Wykup ochronę**

---

## 11. Prosty opis efektów dla gracza

Nie pokazywać graczowi mnożników typu `0.55`.

Pokazywać normalny tekst:

```text
Znacznie zmniejsza ryzyko kradzieży.
Zmniejsza ryzyko napadu.
Lekko zmniejsza ryzyko sabotażu.
Nie chroni przed awarią pojazdu ani pogodą.
```

---

## 12. Podpięcie P1 — transport drogowy

Na start wdrożyć tylko transport drogowy.

Ochrona ma wpływać na:

- kradzież,
- napad,
- sabotaż.

Nie wpływa na:

- pogodę,
- awarię pojazdu,
- zwykłe opóźnienie,
- korki,
- błędy techniczne.

Przy tworzeniu kursu drogowego system pobiera dostępne opcje ochrony dla:

```text
target_type = road_transport
context = road_transport_guard
```

Po wykupieniu ochrony zapisuje ją na danym kursie albo na aktywnym celu transportowym.

---

## 13. Podpięcia późniejsze

### Ochrona odwiertu

Może działać na:

- sabotaż,
- kradzież sprzętu,
- atak na ekipę,
- celowe uszkodzenie.

Nie działa na:

- naturalne zużycie,
- degradację,
- brak technika,
- awarie technologiczne.

### Ochrona huba

Może działać na:

- kradzież z bufora,
- sabotaż,
- atak na infrastrukturę,
- celowe zatrzymanie przepływu.

Nie działa na:

- przeciążenie,
- degradację,
- zły stan techniczny.

### Ochrona rurociągu

Może działać na:

- sabotaż,
- kradzież ropy,
- celowe uszkodzenie.

Nie działa na:

- naturalne zużycie,
- brak konserwacji,
- awarie techniczne.

---

## 14. Balans

Ochrona nie może być gwarancją bezpieczeństwa.

Ma tylko zmniejszać ryzyko.

Zasada:

- tania ochrona = mały efekt,
- średnia ochrona = dobry kompromis,
- droga ochrona = mocny efekt, ale opłacalna tylko przy dużych wartościach.

---

## 15. Czego nie wdrażać teraz

Nie wdrażać od razu:

- ochrony wszystkich aktywów,
- ochrony portów,
- ochrony terminali,
- prywatnej armii,
- kontraktów ochroniarskich na wiele dni,
- wpływu na czarny rynek,
- wpływu na wiarygodność firmy,
- śledztw po incydencie.

Na start:

**silnik + admin + transport drogowy.**

---

## 16. Najkrótsza wersja dla AI

Stworzyć konfigurowalny moduł ochrony. Opcje ochrony i ich efekty mają być definiowane w bazie i panelu admina, a nie wpisane na sztywno w kod. `ProtectionService` ma pobierać dostępne opcje, wyliczać koszt, aktywować ochronę, zapisywać historię i zwracać efekty dla konkretnego celu. Na start podpiąć tylko transport drogowy. System ma być gotowy do późniejszego użycia przy odwiertach, hubach i rurociągach.
