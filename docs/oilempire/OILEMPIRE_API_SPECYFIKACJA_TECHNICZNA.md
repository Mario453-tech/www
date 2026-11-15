# OilEmpire — specyfikacja techniczna API v1

**Status:** projekt kontraktu do implementacji. Założenia domenowe i liczby pozostają w `OILEMPIRE_ARCHITEKTURA_SYSTEMOWA.md` oraz `OILEMPIRE_GAME_BALANCE_V1.yaml`. Ten dokument opisuje wejście HTTP dla aplikacji mobilnej i klientów webowych; nie oznacza, że endpointy już istnieją.

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
| Pieniądze | liczba całkowita w najmniejszej jednostce jako `amount_minor` lub pole `*_minor`; jedna waluta gry, bez `float` i bez przewalutowania (§24) |
| Ropa i produkty | wartości DECIMAL przesyłane jako ciągi dziesiętne, np. `"123.4567"` bbl; bez utraty precyzji przez JSON number |
| Język | `Accept-Language: pl|en|de`; serwer zwraca `Content-Language`; kody błędów pozostają stabilne niezależnie od języka |
| Wersja | zmiany kompatybilne w `/api/v1`; usunięcie/zmiana znaczenia pola wymaga `/api/v2` i okresu równoległej obsługi |

Odpowiedź sukcesu: `data` (obiekt lub lista), opcjonalne `meta` (paginacja, czas synchronizacji, wersja balansu). Odpowiedzi list mogą mieć `links.next`. Klient ignoruje nieznane pola, ale nie domyśla się znaczenia nieznanego kodu statusu.

## 3. Uwierzytelnianie i sesje

- **Mobile:** krótkotrwały token dostępu `Bearer` plus rotowany token odświeżania przypisany do urządzenia. W bazie przechowywany jest wyłącznie hash tokenu odświeżania; dostęp i rotacja są audytowane. Wyciek/ponowne użycie starego tokenu unieważnia daną rodzinę sesji. Implementacja może użyć Laravel Sanctum dla tokenu dostępu, ale kontrakt rotacji, urządzeń i revokacji musi być jawnie dodany — samo Sanctum nie definiuje całego procesu.
- **Web:** sesja cookie z ochroną CSRF. Przeglądarka nie zapisuje tokenu Bearer w `localStorage`. Ten sam użytkownik może mieć sesję webową i mobilną, ale każda sesja ma własny identyfikator i możliwość zdalnego wylogowania (§14).
- Token mobilny trafia do bezpiecznego magazynu systemowego. Sekretu nie umieszcza się w URL, logach, analityce, payloadzie Reverb ani push. Po stronie serwera obowiązują polityki per zasób i per akcja; `company_id` z żądania nie daje dostępu do cudzej firmy.
- Logowanie, reset hasła i 2FA muszą respektować §14. Reset hasła lub wylogowanie wszystkich urządzeń unieważnia również sesje API. Pełne endpointy uwierzytelniania są wdrażane po ustaleniu mechanizmu 2FA i czasów życia tokenów; nie wolno wystawić prowizorycznego logowania bez tych kontroli.

| Metoda i ścieżka | Cel |
|---|---|
| `POST /api/v1/auth/login` | rozpoczęcie sesji lub wyzwania 2FA; identyczny komunikat dla błędnych danych logowania |
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

- Każda mutacja gospodarcza używa `Idempotency-Key` (UUID/ULID generowany przez klienta dla jednej intencji). Unikalność jest ograniczona do aktora, operacji i klucza; serwer zapisuje hash kanonicznego żądania, wynik oraz status. Ten sam klucz i identyczny payload zwracają poprzedni wynik; ten sam klucz z innym payloadem zwraca `409 IDEMPOTENCY_KEY_REUSED`.
- Klucz jest rejestrowany w trwałej tabeli przed blokowaniem kont (§23.5a). Rozliczenie, rezerwacja, FTS, zmiana stanu i wynik idempotencji są atomowe. Odpowiedź utracona przez sieć nie może spowodować ponownego pobrania pieniędzy przy retry.
- Mutacja może wymagać `If-Match` z `state_version`. Nieaktualna wersja daje `409 STATE_CHANGED` ze wskazaniem odświeżenia, bez częściowego wykonania.
- Serwer wylicza cenę, koszt, limit, terminy i skutki z aktywnej `balance_version`; wartości przesłane przez klienta są co najwyżej oczekiwanym quote. Przy zmianie quote serwer zwraca konflikt i nową ofertę do świadomego potwierdzenia.
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
- `code` jest stabilny i lokalizowany przez klienta; `message` pochodzi z języka żądania i nadaje się do wyświetlenia. `details.fields` może zawierać błędy pól. UI dobiera własną ikonę SVG i komponent zgodnie z §27.2a; API nie przekazuje HTML ani zewnętrznego URL ikony.
- Rate limiting jest osobny dla logowania/2FA/resetu, odczytów, mutacji gospodarczych, czatu i wyszukiwania. Limity chronią przed bruteforce i kosztownym rozliczaniem na żądanie; `429` zawiera `Retry-After`. Limiter nie zastępuje idempotencji.
- Każda odpowiedź ma `X-Request-Id`; logi przechowują kod operacji, identyfikator aktora i czas, ale nie hasła, tokeny, pełny payload ani treść prywatnych wiadomości. Metryki rozdzielają web/mobile, typ akcji, czas `SettlementService`, konflikty i błędy.

## 8. Realtime i push

- Mobilny klient może subskrybować prywatne kanały Reverb po autoryzacji sesji API. Publiczne kanały (`market.prices`, `world.events`) nie mogą zawierać danych prywatnych. Zdarzenie niesie ID, typ, wersję i minimalną deltę; po przerwie klient pobiera snapshot/API. Autoryzacja kanału jest sprawdzana po stronie serwera (§28).
- Centrum powiadomień jest trwałym źródłem prawdy. Push jest sygnałem o nowym zdarzeniu, może dotrzeć późno lub wielokrotnie i nie wykonuje akcji gry. Deep link prowadzi do zasobu po ponownej autoryzacji. Token urządzenia jest przypisany do sesji, rotowany/usuwany przy wylogowaniu; payload zablokowanego ekranu nie zawiera danych wrażliwych (§27.2).

## 9. Kontrakt, testy i kolejność wdrożenia

1. Opublikować wersjonowany opis OpenAPI 3.1 dla pierwszych endpointów oraz wspólny schemat błędu, czasu, pieniędzy, objętości, paginacji i idempotencji. Zmiana kontraktu wymaga sprawdzenia kompatybilności z poprzednią wersją aplikacji mobilnej.
2. Zbudować warstwę sesji/2FA i `GET /me`, następnie `GET /game/snapshot`, a potem pierwszą pionową ścieżkę: odczyt odwiertu → jedna autoryzowana akcja → świeży snapshot. Rozszerzać API moduł po module bez kopiowania logiki Inertia.
3. Testy: odmowa dostępu do cudzego zasobu; 401/403/404/409/422/429; ten sam klucz dwa razy i z innym payloadem; retry po timeout; konflikt `If-Match`; HTTP/job/sweeper dla jednego `due_at`; zgodność wyników web/mobile; precision pieniędzy i bbl; PL/EN/DE; token po logout/reset; reconnect Reverb/push. Integracyjne testy transakcji i blokad uruchamiać na MySQL.
4. Przed wydaniem aplikacji mobilnej sprawdzić obsługę starych wersji klienta, czas życia tokenów, kompatybilność endpointów, polityki sklepów i plan wycofania wadliwej wersji API. Wyłączenie `/api/v1` wymaga wcześniejszego okresu migracji klientów.

**Otwarte decyzje techniczne:** framework aplikacji mobilnej, konkretne czasy życia tokenów i metoda 2FA, dostawca push, limity rate limit per grupa oraz pierwszy zestaw pól OpenAPI. Te decyzje nie zmieniają zasad ekonomii ani wymogu wspólnego `SettlementService`.
