# Brief techniczny: modularny moduł RODO / cookies dla OilCorp

## Cel

Zaprojektować i wdrożyć w istniejącym projekcie OilCorp moduł RODO / cookies, który:

- wyświetla użytkownikowi baner cookies,
- pozwala zarządzać zgodami,
- zapisuje historię decyzji,
- pozwala adminowi zarządzać definicjami cookies i treścią polityk,
- jest modularny i gotowy do dalszej rozbudowy.

Moduł ma być zgodny z obecną architekturą gry:

- PHP bez frameworka,
- PDO + MySQL / MariaDB,
- istniejący panel admina,
- istniejący system tłumaczeń,
- istniejący układ `public/`, `src/`, `templates/`, `assets/`, `admin/`.

To nie ma być ciężki system enterprise. Ma być prosty, czytelny, bezpieczny i gotowy do rozwijania etapami.

---

## Kontekst projektu

Projekt to przeglądarkowa gra ekonomiczna OilCorp / OilEmpire oparta o:

- PHP 8.x
- MySQL / MariaDB
- panel admina już istnieje
- frontend gracza już istnieje
- system tłumaczeń `t()` już istnieje
- w projekcie już występują mechanizmy oparte o cookies, np.:
  - logowanie i sesja,
  - remember me,
  - trusted device dla 2FA,
  - preferencje językowe.

Moduł musi być dopasowany do gry, a nie do ogólnego portalu SaaS.

---

## Główne wymaganie architektoniczne

Moduł ma być **modularny**, ale bez budowania osobnego mini-frameworka.

To oznacza:

- ma istnieć wspólny rdzeń prywatności / cookies,
- rdzeń ma pozwalać dokładać kolejne feature modules,
- etap 1 wdraża tylko to, co potrzebne teraz,
- późniejsze funkcje mają być możliwe do dodania bez przebudowy całości.

Nie chodzi o zrobienie "wielkiej platformy compliance", tylko o **rozsądny moduł, który od początku ma dobry podział i możliwość rozbudowy**.

---

## Główna koncepcja

Stworzyć moduł bazowy, przykładowo:

`PrivacyModule`

lub:

`PrivacyComplianceModule`

W jego ramach mają istnieć:

- wspólny rejestr funkcji,
- wspólny kontrakt dla feature modules,
- wspólna warstwa ustawień,
- wspólny log audytowy,
- wspólny mechanizm wersjonowania polityk,
- wspólny mechanizm odczytu stanu zgody,
- wspólny mechanizm sprawdzania, czy baner ma się wyświetlić,
- feature flags / aktywacja i dezaktywacja modułów.

---

## Zakres etapu 1

Na pierwszym etapie wdrożyć:

1. rdzeń modułu prywatności,
2. baner cookies na froncie,
3. modal ustawień cookies,
4. zapis zgód,
5. panel admina do zarządzania:
   - definicjami cookies,
   - zgodami,
   - wersją banera,
   - wersją polityki cookies,
   - podstawowymi ustawieniami modułu.

Minimalne feature modules etapu 1:

- `CookiesFeature`
- `ConsentsFeature`
- `PolicyFeature`
- `BannerSettingsFeature`

Nie trzeba od razu wdrażać:

- żądań prywatności,
- retencji danych,
- incydentów,
- rejestru procesorów,
- automatycznych workflow RODO.

---

## Co moduł ma robić na froncie

### 1. Baner cookies

Baner ma pojawić się:

- przy pierwszej wizycie,
- gdy użytkownik nie ma zapisanej zgody,
- gdy zmieniła się wersja banera,
- gdy zmieniła się wersja polityki cookies i system wymaga ponownej zgody.

Baner ma zawierać minimum:

- krótki opis,
- przycisk `Akceptuję wszystkie`,
- przycisk `Tylko niezbędne`,
- przycisk `Ustawienia`,
- link do polityki cookies / polityki prywatności.

### 2. Modal ustawień cookies

Użytkownik ma móc:

- zobaczyć kategorie cookies,
- odczytać opis kategorii,
- zaakceptować wybrane kategorie,
- zapisać decyzję,
- później wrócić do ustawień i zmienić decyzję.

### 3. Link do zarządzania prywatnością

W stopce lub w innym trwałym miejscu ma być link:

- `Ustawienia prywatności`
albo
- `Ustawienia cookies`

Po kliknięciu użytkownik ma móc ponownie otworzyć modal i zmienić zgodę.

---

## Kategorie cookies dla OilCorp

Minimalny podział kategorii:

### 1. Niezbędne

Przykłady:

- sesja logowania,
- remember me,
- trusted device dla 2FA,
- tokeny bezpieczeństwa,
- utrzymanie stanu gry wymagającego sesji.

Te cookies nie mogą być wyłączone przez użytkownika, jeśli są niezbędne do działania gry i bezpieczeństwa konta.

### 2. Preferencje

Przykłady:

- język interfejsu,
- ustawienia wyglądu,
- ustawienia UX.

### 3. Analityczne

Ta kategoria ma być gotowa pod późniejsze wdrożenie.

Jeśli w projekcie nie ma jeszcze narzędzi analitycznych, można ją zostawić aktywną architektonicznie, ale bez aktywnych wpisów.

### 4. Marketingowe

Tak samo jak analityczne:

- architektura ma przewidywać,
- ale nie trzeba wdrażać realnych rekordów, jeśli nie ma takiej potrzeby.

---

## Rdzeń modułu ma zawierać

### 1. Feature Registry

Jedno centralne miejsce, gdzie rejestrowane są funkcje modułu prywatności.

Każda funkcja powinna deklarować:

- key,
- label,
- icon,
- permission key,
- pozycję w menu,
- czy jest aktywna,
- własne zakładki / sekcje,
- własne ustawienia,
- własne widgety dashboardowe, jeśli są potrzebne.

### 2. Wspólny kontrakt dla feature modules

Każdy feature module ma wdrażać wspólny interfejs.

Przykładowy zakres:

- identyfikator funkcji,
- etykieta,
- ikona,
- permission key,
- rejestracja w menu,
- rejestracja zakładki,
- rejestracja ustawień,
- opcjonalnie: dashboard widget.

### 3. Wspólna warstwa ustawień

Jedna wspólna warstwa ustawień dla całego modułu.

Przykłady kluczy:

- `privacy.banner.enabled`
- `privacy.banner.version`
- `privacy.banner.force_reconsent`
- `privacy.cookies.policy_version`
- `privacy.cookies.reconsent_after_policy_change`
- `privacy.cookies.show_decline_button`
- `privacy.audit.enabled`

### 4. Wspólny log audytowy

Wspólny log zmian i działań admina w module.

Log powinien zapisywać:

- `admin_id`
- `action`
- `entity_type`
- `entity_id`
- `old_data_json`
- `new_data_json`
- `ip_address`
- `user_agent`
- `created_at`

### 5. Wspólny silnik odczytu zgody

Moduł ma mieć jedno miejsce odpowiedzialne za:

- odczyt ostatniej zgody,
- ustalenie, czy użytkownik / gość ma aktywną zgodę,
- ustalenie, czy trzeba pokazać baner,
- interpretację wersji zgody i wersji polityki.

### 6. Feature Flags

Każdy feature ma mieć możliwość:

- włączenia,
- wyłączenia,
- ukrycia z menu bez usuwania kodu.

---

## Minimalna struktura katalogów

Dostosowana do obecnej architektury projektu:

```text
/src/Privacy/
    PrivacyFeatureInterface.php
    AbstractPrivacyFeature.php
    PrivacyFeatureRegistry.php
    PrivacySettingsService.php
    PrivacyAuditLogger.php
    PrivacyConsentService.php
    PrivacyBannerService.php
    PrivacyPolicyService.php

/src/Privacy/Features/
    /Cookies/
        CookiesFeature.php
        CookieRepository.php
        CookieService.php
    /Consents/
        ConsentsFeature.php
        ConsentRepository.php
        ConsentService.php
    /Policy/
        PolicyFeature.php
        PolicyRepository.php
        PolicyService.php
    /Banner/
        BannerSettingsFeature.php
        BannerSettingsService.php

/public/
    privacy-settings.php
    cookies-policy.php
    privacy-policy.php

/admin/
    privacy.php

/templates/views/privacy/
    banner.php
    settings_modal.php
    policy_page.php

/templates/views/admin/privacy/
    main.php
    tab_cookies.php
    tab_consents.php
    tab_policies.php
    tab_settings.php

/assets/js/
    privacy_banner.js

/assets/css/
    privacy.css

/lang/pl/
    privacy.php

/lang/en/
    privacy.php
```

---

## Wymagania dla CookiesFeature

Feature ma umożliwiać zarządzanie definicjami cookies i podobnych technologii.

### Pola rekordu

- `name`
- `category`
- `provider`
- `purpose`
- `duration`
- `type`
- `is_required`
- `is_active`
- `cookie_key`
- `notes`
- `created_at`
- `updated_at`

### Minimalne funkcje

- lista cookies,
- filtrowanie po kategorii,
- filtrowanie po statusie,
- dodawanie,
- edycja,
- aktywacja / dezaktywacja,
- oznaczenie, czy cookie jest niezbędne,
- eksport listy,
- log zmian.

---

## Wymagania dla ConsentsFeature

Feature ma umożliwiać zarządzanie zgodami użytkowników i gości.

### Pola rekordu

- `player_id` nullable
- `anonymous_token`
- `consent_version`
- `banner_version`
- `accepted_categories_json`
- `rejected_categories_json`
- `source`
- `ip_address`
- `user_agent`
- `created_at`
- `updated_at`
- `withdrawn_at` nullable

### Minimalne funkcje

- lista zgód,
- filtrowanie po dacie,
- filtrowanie po wersji,
- filtrowanie po źródle,
- filtrowanie po kategorii,
- podgląd szczegółów zgody,
- historia zmian zgody,
- eksport CSV / JSON,
- log działań administracyjnych.

---

## Wymagania dla PolicyFeature

Feature ma obsługiwać treść i wersjonowanie polityk.

### Typy polityk

Minimum:

- `cookies`
- `privacy`

### Pola rekordu

- `policy_type`
- `version`
- `title`
- `content`
- `is_active`
- `published_at`
- `created_at`
- `updated_at`

### Minimalne funkcje

- lista wersji,
- aktywacja wybranej wersji,
- podgląd aktualnej wersji,
- edycja nowej wersji,
- historia zmian,
- powiązanie z mechanizmem ponownego pytania o zgodę.

---

## Wymagania dla BannerSettingsFeature

Feature ma umożliwiać zarządzanie treścią i zachowaniem banera.

### Minimalne ustawienia

- czy baner jest aktywny,
- aktualna wersja banera,
- nagłówek,
- opis,
- etykiety przycisków,
- czy przycisk "Tylko niezbędne" jest widoczny,
- czy zmiana wersji wymusza ponowną zgodę,
- link do polityki cookies,
- link do polityki prywatności.

---

## Tabele bazy danych

Zaprojektować SQL zgodnie z obecną konwencją projektu.

### Tabele rdzenia

#### `privacy_settings`

- `id`
- `setting_key`
- `setting_value`
- `value_type`
- `updated_at`

#### `privacy_audit_logs`

- `id`
- `admin_id`
- `action`
- `entity_type`
- `entity_id`
- `old_data_json`
- `new_data_json`
- `ip_address`
- `user_agent`
- `created_at`

#### `privacy_features`

- `id`
- `feature_key`
- `is_enabled`
- `sort_order`
- `created_at`
- `updated_at`

### Tabele etapu 1

#### `cookie_definitions`

- `id`
- `name`
- `category`
- `provider`
- `purpose`
- `duration`
- `type`
- `is_required`
- `is_active`
- `cookie_key`
- `notes`
- `created_at`
- `updated_at`

#### `cookie_consents`

- `id`
- `player_id` nullable
- `anonymous_token`
- `consent_version`
- `banner_version`
- `accepted_categories_json`
- `rejected_categories_json`
- `source`
- `ip_address`
- `user_agent`
- `created_at`
- `updated_at`
- `withdrawn_at` nullable

#### `privacy_policy_versions`

- `id`
- `policy_type`
- `version`
- `title`
- `content`
- `is_active`
- `published_at`
- `created_at`
- `updated_at`

---

## Routing i integracja z panelem admina

Moduł ma zostać wpięty do istniejącego panelu admina.

### Wymagania

- własna sekcja menu, np. `Prywatność i cookies`
- jedna strona admina, np. `admin/privacy.php`
- zakładki:
  - Cookies
  - Zgody
  - Polityki
  - Ustawienia
- integracja ze stylem istniejącego panelu
- bez rozwalania obecnej nawigacji admina

---

## Integracja z frontendem gracza

### Baner

Baner ma być renderowany globalnie, najlepiej przez wspólny layout, tak aby działał:

- na stronie logowania,
- na stronie głównej,
- w widokach gry,
- na stronach statycznych.

### Warunki wyświetlenia

Baner wyświetla się, gdy:

- brak aktywnej zgody,
- wersja zgody nie zgadza się z aktualną konfiguracją,
- wersja polityki wymaga odświeżenia zgody,
- użytkownik cofnął zgodę.

---

## UI / UX

Interfejs ma być prosty i praktyczny.

### Front

- estetyczny baner,
- czytelny modal ustawień,
- brak przeładowanej treści,
- szybki wybór podstawowy,
- możliwość szczegółowego wyboru.

### Admin

- listy z filtrami,
- czytelne formularze,
- przełączniki statusu,
- paginacja,
- komunikaty sukcesu i błędów,
- potwierdzenie usuwania,
- szybkie akcje w wierszu.

---

## Wymagania bezpieczeństwa

- walidacja danych wejściowych,
- escaping danych wyjściowych,
- CSRF dla formularzy,
- kontrola dostępu do panelu admina,
- sprawdzanie uprawnień dla każdej akcji,
- brak możliwości użycia wyłączonego feature module,
- logowanie akcji administracyjnych,
- bezpieczny zapis zgód i zmian wersji.

---

## Wymagania jakościowe

- kod zgodny ze stylem projektu,
- prosty podział warstw,
- brak ogromnych kontrolerów,
- brak logiki biznesowej w widokach,
- brak hardkodowania modułu w wielu miejscach,
- rozsądna modularność bez nadmiarowej abstrakcji,
- przygotowanie pod późniejszą rozbudowę.

---

## Oczekiwany rezultat po zakończeniu etapu 1

Po wdrożeniu chcę mieć:

1. działający moduł prywatności / cookies,
2. baner cookies na froncie,
3. modal ustawień cookies,
4. zapis zgód użytkownika / gościa,
5. panel admina do zarządzania cookies,
6. panel admina do podglądu zgód,
7. wersjonowanie polityki cookies,
8. wersjonowanie treści banera,
9. wspólny log audytowy dla modułu,
10. strukturę gotową pod dalszą rozbudowę.

---

## Plan dalszej rozbudowy

Architektura ma być gotowa pod późniejsze wdrożenie:

- `PrivacyRequestsFeature`
- `RetentionFeature`
- `SecurityIncidentsFeature`
- `ProcessorsFeature`
- `PolicyVersioningFeature` w szerszym zakresie
- `ConsentExportFeature`
- `GeoRulesFeature` jeśli kiedyś potrzebne będą reguły per region / kraj

---

## Czego nie robić

- nie budować ogromnego systemu enterprise na start,
- nie przepisywać całego panelu admina,
- nie robić osobnego mini-frameworka,
- nie mieszać logiki biznesowej z HTML,
- nie robić jednego wielkiego kontrolera,
- nie hardkodować wszystkiego w jednym pliku,
- nie wdrażać naraz wszystkich przyszłych modułów.

---

## Forma dostarczenia

Przy wdrożeniu przygotować:

1. plan wdrożenia krok po kroku,
2. strukturę plików,
3. SQL tabel,
4. klasy rdzenia,
5. integrację z adminem,
6. integrację z layoutem frontowym,
7. działający baner,
8. działający zapis zgód,
9. działający panel admina dla cookies i zgód,
10. krótki opis, jak później dodać nowy feature module.

---

## Dodatkowa uwaga

Najpierw pokazać:

1. plan wdrożenia,
2. listę plików,
3. SQL,

a dopiero potem przejść do implementacji.
