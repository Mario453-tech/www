# OilEmpire — specyfikacja techniczna API v1

> **Kadry — wariant A do implementacji:** [mechanika i parametry §19](OILEMPIRE_KADRY_ZATRUDNIENIE_ROZSZERZENIE.md) oraz [HR-CONTRACT01](OILEMPIRE_KADRY_KONTRAKT_IMPLEMENTACYJNY.md) ustalają kierunek P07–P09/P16. DOM01/DOM02/DOM16 mają decyzje A, ale wymagają propagacji do kontraktów maszynowych, kalibracji i testów przed aktywacją. Starsze karty opisują bazowy model historyczny; przy wdrażaniu A stosuje się wskazaną deltę. Aktywny game_balance v1 nie został zmieniony.


> Część wspólnej specyfikacji OilEmpire. [Indeks, mapa pakietów i zasady wspólnej zmiany](README.md). Zakres tego rozdziału: Transport HTTP, sesje mobilne i kontrakty API dla wspólnych akcji domenowych. Sprzeczność między rozdziałami wymaga jawnego rozstrzygnięcia i aktualizacji wszystkich dotkniętych zapisów.


**Status:** projekt kontraktu do implementacji. Założenia domenowe i liczby pozostają w `OILEMPIRE_ARCHITEKTURA_SYSTEMOWA.md` oraz `OILEMPIRE_GAME_BALANCE_V1.yaml`. Ten dokument opisuje wejście HTTP dla aplikacji mobilnej i klientów webowych; nie oznacza, że endpointy już istnieją.

Obowiązuje również [kontrakt bezpieczeństwa silnika](OILEMPIRE_BEZPIECZENSTWO_SILNIKA.md), w szczególności wspólna autoryzacja, transakcje, outbox, rotacja/revokacja sesji i testy S01–S18.

## 1. Cel i granice

- Publiczne API gracza ma prefiks `/api/v1`. Jedno konto i jeden trwały świat są wspólne dla aplikacji Android/iOS i przeglądarki. Oba kanały wywołują te same akcje domenowe Laravel; API nie implementuje osobnej ekonomii.
- Web Vue/Inertia może nadal korzystać z tras Inertia. Formularz webowy i endpoint API wywołują tę samą klasę `Action`/serwis aplikacyjny i tę samą autoryzację. Nie ma dwóch ścieżek księgowania.
- Filament GM pozostaje panelem webowym. Operacje GM, webhooki płatności i wewnętrzny sweeper nie należą do publicznego API gracza i mają osobne uwierzytelnianie, polityki i limity.
- Endpointy są przypisane do modułów domenowych. Kontroler parsuje żądanie, uruchamia walidację i akcję, a `Resource` formatuje wynik; nie wykonuje SQL ani obliczeń balansu.

## 2. Protokół i format

| Pole | Kontrakt |
|---|---|
| Transport | HTTPS; odrzucenie niezabezpieczonego transportu poza lokalnym środowiskiem developerskim |
| Format | JSON UTF-8, `Accept: application/json`; daty ISO 8601 UTC z milisekundami, np. `2026-11-15T12:30:00.000Z` |
| Identyfikatory publiczne | losowe ULID/UUID, bez ujawniania sekwencyjnych kluczy bazy; identyfikator nie zastępuje polityki dostępu |
| Pieniądze | kwota w najmniejszej jednostce jako dziesiętny string `amount_minor`/`*_minor`, np. `"1200000"`; stały typ także dla małych kwot, w bazie BIGINT, bez float i przewalutowania (§24) |
| Ropa i produkty | wartości DECIMAL przesyłane jako ciągi dziesiętne, np. `"123.4567"` bbl; bez utraty precyzji przez JSON number |
| Język | `Accept-Language: pl|en|de`; serwer zwraca `Content-Language`; kody błędów pozostają stabilne niezależnie od języka |
| Wersja | zmiany kompatybilne w `/api/v1`; usunięcie/zmiana znaczenia pola wymaga `/api/v2` i okresu równoległej obsługi |

Odpowiedź sukcesu: `data` (obiekt lub lista), opcjonalne `meta` (paginacja, czas synchronizacji, wersja balansu). Odpowiedzi list mogą mieć `links.next`. Klient ignoruje nieznane pola, ale nie domyśla się znaczenia nieznanego kodu statusu.

## 3. Uwierzytelnianie i sesje

- **Mobile:** token dostępu `Bearer` ważny **15 minut** plus rotowany token odświeżania przypisany do urządzenia. Token odświeżania wygasa po **30 dniach bez użycia** (okno przesuwne) i bezwzględnie po **90 dniach od zalogowania**; po tym czasie wymagane jest ponowne logowanie. Trwały rejestr tokenów przechowuje wyłącznie hash; wyjątkiem jest szyfrowana odpowiedź odzyskiwania opisana poniżej; dostęp i rotacja są audytowane. Ponowne użycie starego tokenu unieważnia daną rodzinę sesji. Laravel Sanctum jest wyborem dla tokenu dostępu; rotacja refresh, limit absolutny, rodzina sesji i revokacja wymagają osobnej implementacji — samo Sanctum nie definiuje tego procesu.
- **Web:** sesja cookie z ochroną CSRF. Przeglądarka nie zapisuje tokenu Bearer w `localStorage`. Ten sam użytkownik może mieć sesję webową i mobilną, ale każda sesja ma własny identyfikator i możliwość zdalnego wylogowania (§14).
- Token mobilny trafia do bezpiecznego magazynu systemowego. Sekretu nie umieszcza się w URL, logach, analityce, payloadzie Reverb ani push. Po stronie serwera obowiązują polityki per zasób i per akcja; `company_id` z żądania nie daje dostępu do cudzej firmy.
- **2FA:** istniejąca decyzja §14 jest wiążąca: TOTP z aplikacji uwierzytelniającej, opcjonalny dla gracza i obowiązkowy dla każdej roli GM. Jednorazowe kody odzyskiwania są przechowywane jako hashe i zużywane atomowo. Wyzwanie po poprawnym haśle jest ważne **5 minut**, dopuszcza **5 prób** i nie wydaje tokenu dostępu przed zakończeniem. Endpoint mobilny i webowy używają tej samej polityki; nie traktujemy biometrii telefonu ani samego push jako drugiego składnika. Operacje wrażliwe wymagają ponownego potwierdzenia w ostatnich **15 minutach**, zgodnie z §14.5.
- Logowanie, reset hasła i 2FA muszą respektować §14. Reset hasła lub wylogowanie wszystkich urządzeń unieważnia również sesje API. Nie wolno wystawić prowizorycznego logowania bez tych kontroli.

**Revokacja access tokenu:** personal_access_tokens ma relację api_session_id do api_sessions i auth_epoch_at_issue. Każde żądanie Bearer sprawdza expires_at, revoked_at, idle/absolute deadline i auth_epoch; logout/reset blokuje niewygasły access token od razu i odłącza prywatne WS.

**OPEN05:** refresh wymaga Idempotency-Key; klient wykonuje jedno odświeżenie naraz. Rotacja i szyfrowany wynik odpowiedzi są zapisane atomowo w bazie w tej samej transakcji; wynik jest dostępny przez 2 minuty, związany z sesją, zużytym tokenem i request_hash. Po sprawdzeniu ważności sesji wyłącznie identyczne session_id/request_key/used_token_hash/request_hash w oknie 2 min jest replay. Każde inne użycie zużytego tokenu unieważnia rodzinę i daje 401 REFRESH_REUSE, również inny payload pod tym samym kluczem. Dopiero dla niewykorzystanego tokenu konflikt istniejącego klucza daje 409 IDEMPOTENCY_KEY_REUSED bez rotacji. Po upływie okna wymagane może być ponowne logowanie. Klucz szyfrowania wersjonowany, poza bazą; tokeny nigdy nie trafiają do zwykłego cache/logów. Karta P02 określa kolumny i purge.

| Metoda i ścieżka | Cel |
|---|---|
| `POST /api/v1/auth/login` | 200: sesja; 202: challenge_id, challenge_secret, expires_at, next_step=totp; sekret losowy, w bazie tylko hash; identyczny komunikat dla błędnych danych logowania |
| `POST /api/v1/auth/2fa/verify` | zakończenie wyzwania, wydanie pary tokenów po poprawnej weryfikacji |
| `POST /api/v1/auth/refresh` | atomowa rotacja tokenu odświeżania |
| `POST /api/v1/auth/logout` | unieważnienie bieżącej sesji |
| `GET /api/v1/me/sessions` / `DELETE /api/v1/me/sessions/{id}` | lista urządzeń i zdalne wylogowanie własnej sesji |
| `GET /api/v1/me` | bieżący profil i dostępne role/funkcje, bez sekretów |

## 4. Odczyt i rozliczanie czasu

- Każdy odczyt stanu firmy wymagający aktualnej ekonomii wywołuje wspólną procedurę `SettlementService` z §23 przed zbudowaniem odpowiedzi. API, Inertia, opóźniony job i sweeper używają tych samych reguł, blokad, `balance_version` oraz znaczników `due_at`. Nie uruchamia się rozliczenia od samych żądań list publicznych, które nie wymagają stanu gracza.
- `GET /api/v1/game/snapshot` zwraca atomowo spójny obraz firmy do pulpitu: salda w jednostkach najmniejszych, magazyny, cenę referencyjną, licznik pilnych alertów, `server_time`, `synced_at`, `rate_version`, `valid_until`, `balance_version` i wersję stanu firmy. Nie zwraca pełnych modeli Eloquent.
- Wskaźniki interpolowane na telefonie są wyłącznie prezentacją (§23.9). Po upływie `valid_until`, powrocie z tła lub zmianie `rate_version` klient pobiera nowy snapshot. Decyzję o możliwości akcji podejmuje serwer na aktualnym stanie.
- Odczyt po mutacji zwraca zaktualizowany stan dotkniętych zasobów oraz `state_version`. Klient nie łączy dwóch niespójnych snapshotów jako jednego salda.

## 5. Mutacje i idempotencja

- Każda mutacja gospodarcza używa `Idempotency-Key` (UUID/ULID generowany przez klienta dla jednej intencji). Unikalność HTTP obejmuje actor_id, company_id, operation i client_key; serwer zapisuje hash kanonicznego żądania, wynik oraz status. Ten sam klucz i identyczny payload zwracają poprzedni wynik; ten sam klucz z innym payloadem zwraca `409 IDEMPOTENCY_KEY_REUSED`.
- Zdarzenie domenowe ma klucz event_type + entity_id + durable_slot_id bez aktora/wyzwalacza, więc HTTP, job i sweeper trafiają na ten sam wynik. Długie próby używają osobnej dzierżawy; krótkie operacje nie przejmują starego pending (§29.4a).
- Klucz jest rejestrowany w trwałej tabeli przed blokowaniem kont (§23.5a). Rozliczenie, rezerwacja, FTS, zmiana stanu i wynik idempotencji są atomowe. Odpowiedź utracona przez sieć nie może spowodować ponownego pobrania pieniędzy przy retry.
- Mutacja może wymagać `If-Match` z `state_version`. Nieaktualna wersja daje `409 STATE_CHANGED` ze wskazaniem odświeżenia, bez częściowego wykonania.
- Dla nowej operacji serwer wylicza cenę, koszt, limit i terminy z aktywnej `balance_version`; kontynuacja procesu używa utrwalonej wersji startowej zgodnie z §29.5; wartości przesłane przez klienta są co najwyżej oczekiwanym quote. Przy zmianie quote serwer zwraca konflikt i nową ofertę do świadomego potwierdzenia.
- Zadania odroczone zapisują rekord domenowy i `due_at` w transakcji, a job wysyłają po commicie. Klient dostaje `202 Accepted` z ID operacji i terminem, gdy wynik końcowy nastąpi później. `GET /api/v1/operations/{id}` zwraca status tylko właścicielowi.

## 6. Moduły i zasoby API

Poniższe ścieżki są kontraktem przestrzeni nazw i odpowiedzialności. Konkretne pola żądań i odpowiedzi, legalne przejścia stanów oraz limity są definiowane przed implementacją każdego modułu w jego OpenAPI i testach kontraktowych; nie wolno ich zgadywać z formularza webowego.

| Moduł | Odczyt | Mutacja przez akcję domenową |
|---|---|---|
| Core/Company | `/game/snapshot`, `/company`, `/company/history` | `/company/actions/{action}` tylko dla jawnie dopuszczonych akcji |
| Map/Legal | `/map/regions`, `/legal/permits`, `/legal/concessions` | wnioski o pozwolenie/koncesję, odwołania |
| Wells/Technical | `/wells`, `/wells/{id}`, `/technical/incidents` | wiercenie, tryb odwiertu, naprawa |
| Logistics | `/logistics/hubs`, `/logistics/pipelines`, `/logistics/trips`, `/logistics/storages` | budowa, przypisanie, wysłanie kursu |
| Market/Contracts | `/market/quote`, `/market/offers`, `/contracts`, `/contracts/{id}` | sprzedaż spot, oferta B2B, akceptacja/anulowanie kontraktu |
| Finance/Banking | `/finance/summary`, `/finance/ledger`, `/banking/loans` | transfer, wniosek kredytowy, spłata |
| HR/Board | `/hr/employees`, `/board/positions`, `/hr/strikes` | rekrutacja, przydział, negocjacja |
| Refinery/EPC | `/refineries`, `/refineries/{id}`, `/epc/contracts` | budowa, wybór wykonawcy, wybór receptury, uruchomienie |
| Notifications/Chat | `/notifications`, `/notification-preferences`, `/chat/threads` | oznaczenie odczytu, preferencje, wiadomość |

Odczyty kolekcji używają paginacji kursorowej, stabilnego sortowania oraz limitu maksymalnego; filtrowanie i sortowanie są z allowlisty. Szczegóły prywatnego zasobu nie mogą ujawniać, czy istnieje cudzy rekord: odpowiedź `404` po sprawdzeniu polityki. Płatności premium są odseparowane od tej tabeli; webhook dostawcy jest serwer–serwer i podpisany (§22/§29).

Szczegółowa delta zasobów HR wariantu A (akcje, DTO, kody domenowe i uprawnienia) znajduje się w [HR-CONTRACT01 §6–§7](OILEMPIRE_KADRY_KONTRAKT_IMPLEMENTACYJNY.md), a kompletny opis 36 operacji w [OpenAPI Staff A](OILEMPIRE_KADRY_OPENAPI_V1.yaml). Nowe trasy /hr rozwijają niniejszą przestrzeń nazw; konkretny plik OpenAPI 3.1 pozostaje do przygotowania przed kodowaniem klientów.

## 7. Błędy i limity

```json
{
  "error": {
    "code": "STATE_CHANGED",
    "message": "Stan gry zmienił się. Odśwież dane i spróbuj ponownie.",
    "details": {},
    "request_id": "01J..."
  }
}
```

- `400` niepoprawny format; `401` brak/wygaśnięcie sesji; `403` brak uprawnienia; `404` brak dostępnego zasobu; `409` konflikt stanu, quote lub idempotencji; `422` walidacja pól; `429` limit żądań; `503` chwilowa niedostępność. Błąd `500` nie ujawnia stack trace ani danych wrażliwych.
- `code` jest stabilnym identyfikatorem niezależnym od języka; klient lokalizuje jego prezentację; `message` pochodzi z języka żądania i nadaje się do wyświetlenia. `details.fields` może zawierać błędy pól. UI dobiera własną ikonę SVG i komponent zgodnie z §27.2a; API nie przekazuje HTML ani zewnętrznego URL ikony.
- Rate limiting jest osobny dla logowania/2FA/resetu, odczytów, mutacji gospodarczych, czatu i wyszukiwania. Limity chronią przed bruteforce i kosztownym rozliczaniem na żądanie; `429` zawiera `Retry-After`. Limiter nie zastępuje idempotencji. Konkretne wartości startowe i klucze są w §10.
- Każda odpowiedź ma `X-Request-Id`; logi przechowują kod operacji, identyfikator aktora i czas, ale nie hasła, tokeny, pełny payload ani treść prywatnych wiadomości. Metryki rozdzielają web/mobile, typ akcji, czas `SettlementService`, konflikty i błędy.

## 8. Realtime i push

- Mobilny klient może subskrybować prywatne kanały Reverb po autoryzacji sesji API. Publiczne kanały (`market.prices`, `world.events`) nie mogą zawierać danych prywatnych. Zdarzenie niesie ID, typ, wersję i minimalną deltę; po przerwie klient pobiera snapshot/API. Autoryzacja kanału jest sprawdzana po stronie serwera (§28).
- Centrum powiadomień jest trwałym źródłem prawdy. Push jest sygnałem o nowym zdarzeniu, może dotrzeć późno lub wielokrotnie i nie wykonuje akcji gry. Deep link prowadzi do zasobu po ponownej autoryzacji. Token urządzenia jest przypisany do sesji, rotowany/usuwany przy wylogowaniu; payload zablokowanego ekranu nie zawiera danych wrażliwych (§27.2).

## 9. Kontrakt, testy i kolejność wdrożenia

1. Opublikować wersjonowany opis OpenAPI 3.1 dla pierwszych endpointów oraz wspólny schemat błędu, czasu, pieniędzy, objętości, paginacji i idempotencji. Zmiana kontraktu wymaga sprawdzenia kompatybilności z poprzednią wersją aplikacji mobilnej.
2. Zbudować warstwę sesji/2FA i `GET /me`, następnie `GET /game/snapshot`, a potem pierwszą pionową ścieżkę: odczyt odwiertu → jedna autoryzowana akcja → świeży snapshot. Rozszerzać API moduł po module bez kopiowania logiki Inertia.
3. Testy: odmowa dostępu do cudzego zasobu; 401/403/404/409/422/429; ten sam klucz dwa razy i z innym payloadem; retry po timeout; konflikt `If-Match`; HTTP/job/sweeper dla jednego `due_at`; zgodność wyników web/mobile; precision pieniędzy i bbl; PL/EN/DE; token po logout/reset; reconnect Reverb/push. Integracyjne testy transakcji i blokad uruchamiać na MySQL.
4. Przed wydaniem aplikacji mobilnej sprawdzić obsługę starych wersji klienta, czas życia tokenów, kompatybilność endpointów, polityki sklepów i plan wycofania wadliwej wersji API. Wyłączenie `/api/v1` wymaga wcześniejszego okresu migracji klientów.

## 10. Ustalone decyzje techniczne i wartości startowe

| Obszar | Decyzja | Uzasadnienie / granica |
|---|---|---|
| Aplikacja mobilna | **Flutter**, jeden kod klienta dla Androida i iOS; serwer Laravel i API v1 pozostają jedynym źródłem stanu. | Mobilny UI, bez osadzania całej gry w WebView. Publikacja iOS wymaga środowiska macOS/Xcode. |
| Uwierzytelnianie mobilne | Laravel Sanctum dla 15-minutowych tokenów dostępu; własny, hashowany i rotowany refresh: 30 dni bezczynności, maksimum 90 dni od logowania. | Jawne unieważnienie per urządzenie; odświeżenie jest atomowe. Czasy są polityką aplikacji, nie domyślnymi ustawieniami Sanctum. |
| Drugi składnik | TOTP + kody odzyskiwania. Gracz: opcjonalnie. GM: obowiązkowo, przez panel webowy. Wyzwanie 5 minut/5 prób; step-up 15 minut. | Zachowuje §14; biometria systemowa może odblokować lokalny magazyn tokenów, ale nie zastępuje TOTP na serwerze. |
| Push | **Firebase Cloud Messaging (FCM)** jako transport dla Flutter/Android; dla iOS FCM przekazuje wiadomości przez **APNs**. Własny adapter `PushTransport`, kolejka i trwałe centrum powiadomień w Laravel. | Dostawca jest wymienny. Włączenie zewnętrznej usługi, konta Firebase/APNs i przekazanie tokenów urządzeń wymaga osobnej akceptacji właściciela przed integracją. |
| Zawartość push | Wersja domyślna: ogólny tytuł „OilEmpire” i kod/deep link zdarzenia, bez sald, nazw firm, wiadomości czatu i innych danych prywatnych. | System operacyjny może pokazać powiadomienie na ekranie blokady; wewnątrz aplikacji komunikaty używają komponentów i SVG z §27.2a, bez systemowych dialogów. |

### 10.1. Limity API v1

Wartości poniżej są **wartościami startowymi**, konfigurowanymi poza `game_balance` i obserwowanymi w telemetrii. Okna są kroczące lub o równoważnym zachowaniu; przekroczenie zwraca `429` i `Retry-After`. Limity per konto nie są zastępowane samym IP, aby wspólna sieć/NAT nie blokowała wszystkich graczy.

| Grupa | Limit i klucz |
|---|---|
| Logowanie | istniejące §14.5: **5 błędnych prób/konto → 60 s**, **10 → 15 min**; miękki limit **50 prób/IP/godz.** z ostrzeżeniem, bez pełnej blokady IP graczy |
| Wyzwanie TOTP | **5 prób/wyzwanie/5 min**; po wyczerpaniu nowe logowanie, dodatkowo limit logowania per konto |
| Reset hasła | istniejące §14.4: **3 prośby/godz. na konto i 3/IP/godz.**, neutralna odpowiedź bez enumeracji |
| Refresh tokenu | **6 żądań/min/sesję**; równoległe odświeżenia jednej sesji są serializowane, a ponowienie z tym samym kluczem idempotencji zwraca ten sam wynik |
| Zwykły odczyt | **120 żądań/min/gracza**; kolekcje mają niezależny limit rozmiaru strony |
| Snapshot z rozliczeniem | **30 żądań/min/gracza**, niezależnie od zwykłych odczytów; bezpieczne odświeżenie po mutacji jest częścią jej odpowiedzi |
| Mutacje ekonomiczne | **20 żądań/min/gracza**; osobno limity domenowe i idempotencja, bez ponownego wykonania po `429` |
| Czat | **30 wiadomości/min/gracza**; dodatkowe limity antyspamowe per wątek |
| Publiczne wyszukiwanie/rynek | **60 żądań/min/IP** jako limit ochronny, z osobnym limitem per konto po zalogowaniu; nie obejmuje prywatnego snapshotu |

Limity mają wspólny, atomowy magazyn dla wszystkich instancji aplikacji (docelowo Redis). Klucze są namespaced według środowiska, grupy, konta/sesji lub znormalizowanego IP; dane osobowe nie trafiają do klucza jawnie. Zmianę limitów poprzedzają pomiary p95, liczby `429` i obciążenia `SettlementService`; alerty bezpieczeństwa nie znoszą autoryzacji ani idempotencji.

### 10.2. Otwarte przed implementacją

Do ustalenia pozostają: konta i zgoda na integrację FCM/APNs, konfiguracja środowiska macOS dla wydania iOS, dokładne schematy pierwszych endpointów w OpenAPI oraz pomiar limitów po uruchomieniu testowego obciążenia. Wartości z §10.1 można dostroić na podstawie pomiarów bez zmiany ekonomii gry.

### 10.3. Źródła techniczne

- [Flutter — platformy docelowe](https://docs.flutter.dev/install) i [wydanie iOS](https://docs.flutter.dev/deployment/ios).
- [Laravel Sanctum — tokeny i wygasanie](https://laravel.com/framework/docs/sanctum) oraz [Laravel Routing — limitowanie tras](https://laravel.com/framework/docs/routing).
- [Firebase Cloud Messaging dla Fluttera](https://firebase.google.com/docs/cloud-messaging/flutter/get-started) i [architektura FCM/APNs](https://firebase.google.com/docs/cloud-messaging/fcm-architecture).
- [NIST SP 800-63B — uwierzytelnianie i ograniczenia OTP](https://pages.nist.gov/800-63-4/sp800-63b.html).

### 10.4. Zakupy mobile — zatwierdzony kanał (OPEN07)

Android: Google Play Billing; iOS: Apple In-App Purchase; web: operator płatności przez adapter. Wszystkie zakupy wpadają do jednego procesu zamówienia i księgi GBBL. Potwierdzenie telefonu nie nadaje waluty: serwer weryfikuje transakcję z operatorem, produkt i przypisanie do konta. UNIQUE(provider, provider_transaction_id) wyklucza ponowne przyznanie na innym urządzeniu/koncie. Refund i chargeback mają idempotentne skutki zgodne z §22.5; katalog produktów mapuje SKU kanałów na jeden produkt gry. Konfiguracja kont sklepowych jest zadaniem wydania; ceny, podatki operatora i prowizje nie zmieniają salda przyznanego produktu po stronie gry.

## 11. Powiązanie domeny P06–P13

[31 kart P06–P13](OILEMPIRE_KARTY_IMPLEMENTACJI_P06_P13.md) rozwija wejścia i wyniki dla firmy, Staff/Training, Legal, Extraction, Logistics i Incidents. [Wycinek OpenAPI](OILEMPIRE_KONTRAKTY_P06_P13.yaml) formalizuje8wejść i przypisuje operacje do kart oraz warunków gotowości. Prefiks wszystkich ścieżek to /api/v1; Logistics pozostaje w /api/v1/logistics. Wewnętrzne kontrakty nie są automatycznie publicznymi endpointami. Polityki DOM01–DOM16 wymagają decyzji przed aktywacją wskazanych procesów; odpowiedź kontrolowana nie zastępuje obowiązku ich domknięcia przed wydaniem.
