# OilEmpire — kontrakt bezpieczeństwa i niezawodności silnika

**Status:** obowiązująca specyfikacja do implementacji, nie potwierdzenie wdrożenia. Obejmuje Laravel, Inertia, API mobilne, Filament, Artisan, kolejki i sweeper. Rozszerza specyfikację główną §14, §23, §24 i §29 oraz specyfikację API; nie zmienia ustalonego balansu. Każdy moduł musi spełnić poniższe gwarancje przed aktywacją.

## 1. Odpowiedzialności i granice

| Właściciel | Odpowiedzialność |
|---|---|
| Identity/Access | sesje, TOTP, step-up, role i jawny kontekst aktora |
| Moduł domenowy | własność zasobu, dozwolone przejścia, walidacja reguł i parametrów |
| Settlement | aktualny stan według czasu serwera, due-events, CAS, globalny porządek blokad |
| Finance / rejestry zasobów | atomowe księgowanie, rezerwacje, bilans i rekoncyliacja |
| Integrations/Outbox | trwałe przekazanie zdarzeń, ponowienia, deduplikacja konsumentów |
| Operations | backup/restore, monitoring, klucze, wydania i obsługa incydentów |

Kontroler, klient mobilny i widok nie nadają uprawnień ani nie wyliczają wiążącego kosztu. Filament wywołuje te same akcje domenowe co gra. CLI/job ma jawny typ aktora systemowego i zakres zadania; nie dziedziczy domyślnie roli administratora. Kod innych modułów nie zapisuje prywatnych tabel domeny.

## 2. Autoryzacja, stan i dane wejściowe

- Każda akcja ma politykę: kto, na jakim zasobie, w jakim stanie i w jakim czasie może ją wykonać. Zapytanie i końcowa mutacja uwzględniają właściciela/uprawnioną stronę. Dla B2B jawnie rozróżniamy sprzedawcę, kupującego i system.
- Politykę wykonuje wspólna akcja aplikacyjna; middleware jest kontrolą wstępną. Job ponownie sprawdza aktualny stan domenowy i uprawnienie operacji. Odebranie roli GM blokuje oczekujące polecenie GM, chyba że jawny proces domenowy został już nieodwracalnie zatwierdzony.
- Każdy proces ma tabelę przejść: stan źródłowy, komenda, aktor, warunki, stan wynikowy, skutki finansowe/zasobowe, zdarzenie i klucz deduplikacji. Moduł nie może być wdrożony z samą listą statusów.
- Ponowiona komenda końcowa jest kontrolowanym no-opem albo konfliktem; nigdy powtórną nagrodą, dostawą czy zwrotem. Termin, cooldown, tarcza i VIP są sprawdzane według timestampów (§23.6).
- Walidacja obejmuje typ, zakres, precyzję, długość, enum, rozmiar listy i limit payloadu. Pola zapisywane są z allowlisty; klient nie może ustawić właściciela, roli, salda, ceny systemowej, statusu wypłaty ani wyniku RNG.
- Pieniądze: BIGINT grosze; wolumen: DECIMAL zgodnie z domeną. W API liczby całkowite przekraczające bezpieczny zakres JavaScript wymagają jawnego kodowania dziesiętnego jako string w OpenAPI; brak cichego zaokrąglenia. Czasy klienta nie ustalają terminu ani kolejności księgowania.

## 3. Kanoniczny przebieg operacji gospodarczej

1. Zweryfikuj sesję, limit żądań, format i zakres aktora; zbuduj znormalizowaną komendę i request_id.
2. W transakcji domenowej zarejestruj/sprawdź klucz idempotencji przed blokadą kont (§23.5a). Identyczny klucz/payload odtwarza wynik; zmieniony payload daje konflikt.
3. Zablokuj znany zestaw zasobów według globalnego porządku §23.5a; rozlicz wymagany czas przez wspólny SettlementService. Ponownie sprawdź własność, legalny stan, state_version, terminy, saldo i pojemności.
4. Oblicz skutki z właściwej balance_version. Zapisz rezerwację, FTS, ruch zasobu, przejście stanu, audit, wynik idempotencji i zdarzenia outbox w jednej transakcji.
5. Sprawdź niezmienniki i wykonaj commit. Dopiero po nim wolno wysłać Reverb, push, e-mail lub zlecenie integracji. Awaria dostarczenia nie cofa zatwierdzonej gospodarki.
6. Zwróć zaktualizowany stan. Timeout po commicie klient rozstrzyga ponowieniem tego samego klucza albo odczytem operacji.

Zabronione są wywołania sieciowe do operatora płatności/push w otwartej transakcji finansowej. Deadlock retry obejmuje całą transakcję, według istniejącego limitu §23.5a, z ponowną walidacją. Blokada cache lub UNIQUE job są optymalizacją; poprawność zapewniają transakcje, ograniczenia UNIQUE, CAS i stan domenowy.

## 4. Niezmienniki i reakcja na naruszenie

| Gwarancja | Weryfikacja |
|---|---|
| Bilans pieniędzy | suma zapisów księgowania = 0; każde źródło/ujście ma konto przeciwstawne |
| Brak niedozwolonego debetu | konta gracza nie schodzą poniżej zera poza jawnie dozwolonym długiem GBBL po chargebacku |
| Bilans ropy/produktów | wydanie = przyjęcie + udokumentowana strata/zużycie; brak podwójnej własności ładunku |
| Rezerwacje | dostępne = fizyczne saldo minus aktywne rezerwacje, nigdy ujemne |
| Czas i zdarzenia | last_calc_at nie cofa się; ten sam due_slot daje jeden skutek |
| Pojemność | przyjęcie nie przekracza pojemności; nadmiar obsługiwany wyłącznie regułą domenową |
| Wycena | wspólny wersjonowany snapshot dla podatku, tarczy i Tieru; bez podwójnego dyskonta rafinerii |

Naruszenie w transakcji powoduje rollback i alarm z request_id. Wykrycie rozbieżności już zapisanej przez rekoncyliację wstrzymuje dotknięte mutacje dla zasobu/firmy lub odpowiedniej fazy globalnej; zakres musi być najmniejszy, który zachowuje integralność. Odczyt pokazuje ostatni potwierdzony stan i komunikat. Naprawa wymaga audytowanej operacji kompensacyjnej; zakaz edycji historycznej księgi i automatycznego „wyrównania salda” bez wyjaśnienia.

## 5. Outbox i niezawodne dostarczenie

- Tabela outbox zawiera event_id (UNIQUE), typ i wersję schematu, aggregate_id, aggregate_version, minimalny payload, occurred_at, available_at, attempts, status, lease_until i delivered_at. Rekord powstaje w transakcji skutku domenowego.
- Dispatcher przejmuje rekord na wygasającą dzierżawę, publikuje po commicie i oznacza dostarczenie. Awaria między publikacją a oznaczeniem może dać duplikat: dostarczenie jest co najmniej raz, konsumenci muszą być idempotentni.
- Konsument zapisuje UNIQUE (consumer, event_id); własny skutek bazodanowy i potwierdzenie przetworzenia zapisuje atomowo. Dla usług zewnętrznych przekazuje idempotency key, jeśli dostawca go wspiera; nie obiecuje dokładnie jednej wiadomości przy niejednoznacznym timeout dostawcy.
- Retry: wykładniczy backoff z jitterem, limit prób i czasu ustalony per konsument; po wyczerpaniu dead-letter z alarmem. Panel pokazuje zaległość, najstarsze zdarzenie, błąd bez sekretów i audytowany retry. Ręczne ponowienie zachowuje event_id.
- Poza kolejnością: konsument porównuje aggregate_version lub pobiera stan bieżący; nie nadpisuje nowego stanu starą deltą. Schemat zdarzenia jest wersjonowany.
- Samo after_commit nie zastępuje outbox: proces może umrzeć po commicie przed dispatch. Zdarzenia terminowe dodatkowo odzyskuje sweeper (§23.7).

## 6. Konta, personel i sekrety

- Obowiązują TOTP, czasy tokenów, rotacja refresh i limity ze specyfikacji API §3/§10. Token odświeżania i kody odzyskiwania są hashowane; sekret TOTP szyfrowany kluczem poza bazą.
- Rotacja refresh: klient wykonuje jedno odświeżenie naraz. Retry po utracie odpowiedzi używa tego samego klucza idempotencji. Odtworzenie odpowiedzi wymaga krótkotrwałego, szyfrowanego wyniku powiązanego z sesją/żądaniem; nie wolno przechowywać jawnych tokenów w logu lub zwykłym cache. Ponowne użycie zużytego refresh poza tym samym żądaniem unieważnia rodzinę sesji.
- Reset hasła/wylogowanie wszystkich urządzeń unieważnia sesje web/mobile, refresh i dostęp do prywatnych kanałów. Otwarty WebSocket musi utracić autoryzację po revokacji, a nie dopiero przy ponownym połączeniu.
- Zmiana e-maila, łączenie Google, wyłączenie 2FA i odzyskanie konta mają osobne stany, step-up i powiadomienie; link nie nadaje uprawnień ponad wskazaną operację. Odzyskiwanie 2FA nie może polegać na znajomości nicku/e-maila.
- GM działa na imiennym koncie z minimalnymi uprawnieniami. Audit zapisuje aktora, akcję, zasób, wynik, czas i request_id; powód dla owner_admin pozostaje opcjonalny zgodnie z §29.7. Brak powodu nie usuwa audytu.
- Klucze APP_KEY, RNG, HMAC i integracji mają identyfikator wersji i plan rotacji/odtworzenia; sekretów nie ma w repozytorium. Zmiana klucza nie może uniemożliwić odszyfrowania istniejących danych ani odtworzenia wcześniej zapisanych slotów RNG.

## 7. HTTP, WebSocket, treść i upload

- HTTPS/WSS, jawna lista CORS i origin przeglądarkowych WebSocket; origin nie zastępuje uwierzytelniania aplikacji mobilnej. Cookies Secure/HttpOnly/SameSite oraz CSRF dla sesyjnych mutacji web. CORS nie jest mechanizmem autoryzacji API.
- Początkowe limity: JSON do 256 KiB poza dedykowanym uploadem, ramka przychodząca WS do 16 KiB, 5 równoległych połączeń WS na sesję i 10 na konto. Przekroczenie jest odrzucane przed kosztownym przetwarzaniem. Strojenie na podstawie pomiarów; limity API pozostają w jego specyfikacji.
- Prywatny kanał sprawdza konto i uczestnictwo; wiadomość czatu przechodzi walidację/limity serwera, nie jest bezpośrednio rozsyłana z payloadu klienta. Nie logujemy treści rozmów ani tokenów.
- Tekst użytkownika jest kodowany przy wyświetleniu. Rich text CMS/newslettera jest sanityzowany po stronie serwera; SVG ikon pochodzi z wersjonowanych zasobów projektu, nie z dowolnego uploadu użytkownika.
- Awatar zachowuje limit 2 MB i re-encoding §14.6, limit wymiarów/rozpakowanych pikseli i usuwanie EXIF. Prywatne pliki wymagają polityki dostępu. Serwer nie pobiera dowolnych URL z treści użytkownika bez odrębnego zabezpieczenia SSRF.
- Produkcja: APP_DEBUG=false; brak publicznego dostępu do .env, vendor, backupów, logów, kolejek diagnostycznych i narzędzi developerskich. Webroot wskazuje public. Diagnostyka ma osobne uprawnienie.

## 8. Integracje i płatności

Webhook weryfikuje podpis na surowej treści zgodnie z dostawcą, ogranicza rozmiar i deduplikuje event_id. Kwota, waluta i produkt muszą odpowiadać zamówieniu serwera. Zdarzenie nieznane lub poza kolejnością nie przyznaje premium bez legalnego przejścia. Chargeback/refund wykonuje ustaloną kompensację, nie usuwa zakupu z historii. Dane kart nie trafiają do gry. Każdy adapter ma timeout, retry i obsługę niejednoznacznego wyniku; retry płatności nie może utworzyć drugiego zamówienia.

## 9. Eksploatacja, awarie i wydania

- Backup i restore spełniają §29.2 (RPO ≤5 min); test odtworzenia co miesiąc obejmuje klucze, księgi, outbox i zaległe terminy. Backup jest szyfrowany, odseparowany od serwera aplikacji i ma ograniczone uprawnienia. RTO musi być zmierzone i zatwierdzone przed startem produkcji.
- Awaria workera: trwałe due-events i outbox czekają; sweeper odzyskuje terminy. Awaria DB: mutacje kończą się kontrolowaną niedostępnością; brak lokalnego „sukcesu” na telefonie. Awaria Redis/limitera: mutacje i logowanie są odrzucane, jeśli nie można bezpiecznie wymusić limitu; nie wyłącza się kontroli.
- Metryki: p95 API/SettlementService, konflikty i deadlocki, najstarszy outbox, lag due-events, błąd rekoncyliacji, nieudane backupy, błędy logowania i revokacji. Alerty nie zawierają sekretów. Procedura incydentu określa osobę reagującą i audytowane wznowienie.
- Wydania: expand–migrate–contract, zgodność starego/nowego workera i schematów zdarzeń, restart długowiecznych workerów. Wycofanie kodu nie odwraca automatycznie zatwierdzonych transakcji; naprawa danych to jawna kompensacja.
- Środowiska testowe używają syntetycznych danych. Zapis §29.1 o kopii produkcyjnej jest dopuszczalny wyłącznie po skutecznej anonimizacji i usunięciu sekretów, tokenów, treści prywatnych oraz rzeczywistych kanałów wysyłki; preferowany jest seed syntetyczny.

## 10. Obowiązkowe scenariusze odbioru

| ID | Scenariusz i wymagany wynik |
|---|---|
| S01 | Cudzy odwiert, kurs, kontrakt, operacja i kanał WS: odmowa, bez wycieku danych i skutku |
| S02 | 20 równoległych żądań tej samej sprzedaży z jednym kluczem: jeden skutek FTS i jeden ruch ropy |
| S03 | Różne klucze konkurują o cały ten sam zapas: sukces tylko dla dostępnego wolumenu, brak ujemnych rezerwacji |
| S04 | Ten sam klucz z innym payloadem: konflikt, bez kolejnego skutku |
| S05 | Awaria przed commitem: rollback całości; po commicie przed odpowiedzią: retry odtwarza wynik |
| S06 | HTTP + job + sweeper na tym samym due_slot: jedno rozliczenie, deterministyczny RNG |
| S07 | Restart po commicie bez dispatch: outbox odzyskany; awaria po publikacji: konsument deduplikuje |
| S08 | Anulowany/zakończony proces i akcja poza kolejnością: odrzucone przejście, brak nagrody/refundu drugi raz |
| S09 | Deadlock retry: aktualna walidacja, brak podwójnego FTS; test na MySQL |
| S10 | Reset/logout/revokacja: stary access/refresh i prywatny WS tracą dostęp; kody odzyskiwania działają raz |
| S11 | Utracona odpowiedź refresh: ten sam request odtworzony; inne ponowne użycie starego refresh unieważnia rodzinę |
| S12 | Podpisany webhook ponowiony/poza kolejnością i fałszywa kwota: brak podwójnego przyznania premium |
| S13 | Złośliwy rich text, upload, za duży JSON/WS, obcy origin: kontrolowane odrzucenie, bez wykonania kodu |
| S14 | Rekoncyliacja wykrywa zasianą rozbieżność: alarm i właściwa blokada, bez cichej zmiany księgi |
| S15 | Restore backupu plus zaległe zdarzenia: spójny bilans, brak duplikatów i pomiar RPO/RTO |
| S16 | Web/mobile/GM wykonują równoważną akcję: wspólne reguły, właściwe uprawnienia, zgodny wynik ekonomiczny |
| S17 | Limiter/Redis lub DB niedostępny: brak obchodzenia zabezpieczeń i fałszywego sukcesu |
| S18 | Wyjątek i log diagnostyczny: brak hasła, tokenu, sekretu RNG, pełnej treści czatu i stack trace w API |

## 11. Kolejność implementacji i bramka aktywacji

1. Identity/Access, kontekst aktora, kontrakt błędów i audyt.
2. Idempotencja, transakcje, stany i niezmienniki na pierwszej ścieżce gospodarczej.
3. Settlement oraz testy równoległości HTTP/job/sweeper na MySQL.
4. Outbox, deduplikacja konsumentów i obsługa dead-letter.
5. Integracje, bezpieczne treści, limity WS/HTTP, rekoncyliacja i procedura incydentu.
6. Test restore i scenariusze awarii przed uruchomieniem produkcji.

Każdy moduł rejestruje akcje, polityki, przejścia, księgowania, zdarzenia, limity oraz odpowiednie testy S01–S18. Aktywacja wymaga przejścia właściwych testów, przeglądu uprawnień i QA, zielonego symulatora dla zmiany balansu i planu rollback/kompensacji. Samo istnienie dokumentu albo zielony symulator ekonomiczny nie dowodzą bezpieczeństwa wdrożenia.

## 12. Źródła i odniesienia

- [Laravel — zdarzenia po commicie](https://laravel.com/framework/docs/events) i [transakcje](https://laravel.com/framework/docs/database).
- [OWASP — bezpieczeństwo REST](https://cheatsheetseries.owasp.org/cheatsheets/REST_Security_Cheat_Sheet.html) i [WebSocket](https://cheatsheetseries.owasp.org/cheatsheets/WebSocket_Security_Cheat_Sheet.html).
- Lokalne kontrakty: OILEMPIRE_ARCHITEKTURA_SYSTEMOWA.md §14/§23/§24/§29 i OILEMPIRE_API_SPECYFIKACJA_TECHNICZNA.md.
