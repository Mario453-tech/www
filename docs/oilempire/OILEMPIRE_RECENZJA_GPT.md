Ocena: §22 nie jest jeszcze specyfikacją gotową do implementacji. Opisuje produkty i początek płatności, ale nie definiuje skutków cofnięcia zakupu, życia uprawnień ani wpływu sprzedaży przewag na trwałą gospodarkę i PvP. Poniżej wskazuję luki dokumentu, nie potwierdzone podatności działającej gry. Pomijam problemy wymienione jako wcześniej zidentyfikowane.

Niespójności i brakujące powiązania

1. „Subskrypcje wygody” kontra zakup trwałej przewagi. §22 zapowiada Quality of Life, lecz oferuje dodatkowy slot koncesyjny, linię kredytową, pojemność magazynu, gwarantowanego lepszego kandydata oraz uniknięcie strat wizerunkowych po blowoucie. W świecie bez resetów (§19) skutki tych zakupów mogą się kumulować. Dokument musi rozstrzygnąć, czy dopuszcza pay-to-win; sama etykieta „wygoda” tego nie zmienia.

2. „Subskrypcja” VIP za 150 GBBL na 30 dni (§22.3) nie ma określonego modelu odnowienia. Nie wiadomo, czy jest to jednorazowy zakup dostępu, automatyczne pobranie GBBL, czy subskrypcja u operatora płatności. Te modele wymagają różnych reguł zgody, wygaśnięcia i rozliczeń.

3. §22.1 mówi o wpisie do `financial_transaction_service`, podczas gdy §20 definiuje FTS jako rejestr finansowy, a §22 wprowadza nowe jednostki: realne PLN, GBBL i pieniądz gry. Nie ma planu kont ani reguły, co dokładnie jest księgowane i w jakiej jednostce. To nowa luka modelu rozliczeń, odrębna od znanej niespójności walutowej mockupu.

4. §19.1 nazywa progresywną opłatą mechanizm opisany jedną stawką 0,05% dziennie od nadwyżki na rachunku bankowym. Nie podano progów progresji. Równocześnie gotówka (§18.1), escrow (§13, §21) i kupowana gotówka (§22.3) pozostają poza opisaną podstawą. Nie wiadomo więc, czy zamierzona jest opłata od majątku, czy wyłącznie od salda bankowego.

5. §22.1 przewiduje `PayUDriver`, ale opis powrotu z bramki i weryfikacji webhooków obejmuje wprost Stripe i P24. To nie jest pełny kontrakt sterownika: PayU ma nazwę w diagramie, lecz brak dla niego opisanej ścieżki rozliczenia.

6. Katalog §22 podaje ceny tylko części przyspieszeń i VIP. Slot koncesyjny, linia kredytowa i pojemność nie mają ceny, maksymalnej liczby zakupów, czasu trwania ani zasad utraty. „Zwiększenie pojemności bazy” nie wskazuje nawet, czy chodzi o magazyn ropy z §1 i §18.

Luki blokujące implementację

7. Brakuje maszyny stanów zamówienia i świadczenia. Podpisany webhook oraz unikalny `transaction_id` (§22.1) nie wystarczą, by ustalić, czy zdarzenie oznacza ostatecznie opłaconą właściwą kwotę za właściwy pakiet dla właściwego gracza. Trzeba zdefiniować powiązanie z niezmiennym `order_uuid`, kwotą, walutą, operatorem i identyfikatorem płatności; obsługę zdarzeń ponowionych i poza kolejnością, niedopłat, zwrotów oraz chargebacków. Osobno potrzebna jest gwarancja „najwyżej jedno przyznanie świadczenia na opłacone zamówienie” — nowa transakcja operatora dla tego samego zamówienia nie może oznaczać drugiego pakietu.

8. Brakuje cyklu życia uprawnień. Co dzieje się z pętlami cystern, pięcioma zadaniami TTS i piętnastoma ofertami po wygaśnięciu VIP? Czy istniejące operacje kończą się, są wstrzymywane, czy nadmiarowe oferty anulowane i zwracają zablokowaną ropę? Analogiczne reguły są potrzebne po zwrocie płatności, cofnięciu kodu i korekcie GM (§11–12, §22).

9. Brakuje kontraktu zużycia GBBL: atomowego sprawdzenia salda, obciążenia i przyznania usługi, klucza idempotencji żądania oraz zasad rekompensaty, gdy usługi nie uda się wykonać. Bez tego ponowienie żądania może wykonać benefit dwa razy albo pobrać GBBL bez rezultatu. Promokody wymagają ponadto atomowego egzekwowania limitu użyć, także przy równoczesnych realizacjach (§22.3–22.4).

10. Zwrot płatności nie ma zdefiniowanego skutku dla benefitów już zużytych. Nie da się „ododkryć” geologii, cofnąć zaliczonego kursu, odtworzyć ugaszonego pożaru ani odzyskać gotówki wydanej w B2B. Potrzebna jest polityka długu GBBL, ograniczeń konta i kompensacji, bez przepisywania niezmiennej historii FTS (§13, §20, §22).

11. Płatna certyfikacja „natychmiast kończy” szkolenie, które według §6 kończy się losowym egzaminem z możliwością oblania i 12-godzinnym cooldownem. Programista nie wie, czy zakup przyspiesza sam zegar, daje automatyczne zaliczenie, czy pozwala ominąć cooldown. To trzy istotnie różne produkty.

12. §22 nie określa granicy działania „Prywatnej Straży”: czy można jej użyć przed incydentem, w trakcie, czy po zaksięgowaniu szkody? „Bez strat wizerunkowych” koliduje z karą wiarygodności za katastrofę (§2). Trzeba wskazać dokładnie, które przyszłe skutki usuwa usługa, a których historycznych zapisów, strat surowca, kosztów i zobowiązań kontraktowych nie cofa.

Wektory nadużyć i balans

13. GBBL → gotówka gry → sabotaż. §7 wymaga gotówki do ataku, a §22.3 pozwala ją kupić pośrednio. Limit jednej wymiany dziennie nie ogranicza ataków sfinansowanych wcześniej zgromadzonym kapitałem. Płacący gracz otrzymuje więc kanał finansowania agresji PvP; należy oceniać go według szkód wyrządzanych innym, nie tylko wielkości dziennej emisji.

14. Limit wymiany nie jest limitem emisji. Nie ma kursu, maksymalnej kwoty pojedynczej wymiany, definicji „doby”, zasad użycia GBBL z kodów oraz pułapu globalnego. Skalowanie limitem „poziomu firmy” może dać największy dopływ pieniędzy najsilniejszym korporacjom (§19, §22.3).

15. Escrow może stać się schronieniem dla środków. §21 wyłącza je spod egzekucji komorniczej do rozliczenia lub anulowania umowy, a §13 nie definiuje warunków anulowania B2B i postępowania z nierzetelnymi ofertami. Możliwy scenariusz to blokowanie środków w pozornych kontraktach na czas egzekucji. Dokument nie dowodzi, że exploit zadziała, ale nie zawiera reguły, która by go wykluczała.

16. Zakupy infrastruktury zwiększają przewagę także poza bilansem pieniężnym. Dodatkowa koncesja podnosi potencjał wydobycia, pojemność zmniejsza przestoje, a linia kredytowa może umożliwić działanie mimo słabego scoringu lub egzekucji (§2, §18–19, §22). Nie określono, czy zakup daje wyłącznie wyższy limit, czy omija zwykłe pozwolenia i ocenę zdolności kredytowej.

17. Automatyzacja VIP nie jest neutralna ekonomicznie. Pętle skracają przestoje, kolejka napraw ogranicza straty po awariach, a więcej ofert pozwala lepiej wykorzystać wahania ceny. Pętla musi przy każdym kursie ponownie sprawdzać stan cysterny, ładunek, strajk i zapełniony bufor; kolejka musi rezerwować czas konkretnego technika. Inaczej premium może obchodzić fizyczne ograniczenia albo wykonywać kilka prac jedną osobą (§5, §11–12, §21–22).

18. Bonus +5 wiarygodności i status zaufanego kontrahenta za włączenie 2FA (§14) nie są określone jako jednorazowe ani odwracalne po wyłączeniu. Naprzemienne włączanie może farmić punkty, jeśli implementacja nalicza deltę za każdym razem. Nawet bez tego zabezpieczenie konta nie jest dowodem terminowej realizacji kontraktów B2B (§2, §13).

Ryzyka techniczne

Najwyższe ryzyko rozliczeniowe to wyścig między webhookiem, ponowieniem zakupu, wydaniem GBBL i chargebackiem. Potrzebne są odrębne identyfikatory zdarzenia operatora, płatności, zamówienia i przyznanego świadczenia, unikalności w odpowiednich zakresach oraz atomowy zapis skutku biznesowego. Sam unikalny identyfikator transakcji operatora tego nie zapewnia (§22.1).

W długowiecznym świecie osobne, niezmienne rejestry płatności, GBBL, FTS, escrow, ofert i zdarzeń transportowych będą rosły stale. Specyfikacja nie podaje retencji danych operacyjnych, partycjonowania historii ani sposobu odtwarzania sald i uprawnień z rejestru. Reverb powinien publikować zmiany stanu po zatwierdzeniu transakcji, nie stanowić źródła prawdy; odłączenie klienta nie może zmieniać wykonania kursu, oferty lub płatności (§1, §12–13, §20, §22). To uzupełnia, a nie powtarza, wcześniej zgłoszony brak projektu silnika symulacji.

TOP 10 poprawek, w kolejności priorytetu

1. Zdecydować i zapisać politykę fair play: usunąć lub jawnie ograniczyć kupowalną gotówkę, trwałe sloty, kredyt, gwarantowane wyniki i redukcję kar PvP.
2. Zdefiniować maszynę stanów płatności wraz z weryfikacją kwoty, waluty, zamówienia, operatora, zwrotów i chargebacków.
3. Zaprojektować osobne księgi PLN, GBBL i pieniędzy gry oraz atomowe, idempotentne przyznawanie i wydawanie świadczeń.
4. Opisać cykl życia każdego uprawnienia po wygaśnięciu VIP, zwrocie, korekcie GM i przy operacjach już rozpoczętych.
5. Ustalić kurs oraz kwotowe, czasowe i globalne limity emisji pieniędzy z GBBL; włączyć tę emisję do bilansu §19.
6. Zamknąć możliwość parkowania majątku przez jednoznaczne zasady opodatkowania, anulowania escrow i egzekucji.
7. Oddzielić zakup limitu koncesji lub kredytu od zwykłych warunków pozwolenia i oceny zdolności.
8. Określić dokładny wynik płatnej certyfikacji oraz zakres i moment działania usługi gaszenia blowoutu.
9. Zdefiniować maszyny stanów i rezerwacje zasobów dla pętli cystern, kolejek TTS i ofert limit, również przy końcu VIP.
10. Dodać testy scenariuszowe obejmujące równoczesne webhooki, refundację po wydaniu GBBL, koniec VIP w trakcie operacji, zakupioną gotówkę w PvP oraz escrow podczas egzekucji.

Bez decyzji z punktu 1 można zaimplementować poprawny technicznie sklep, który trwale wypaczy gospodarkę gry.


---

# CZĘŚĆ 2 — Recenzja sekcji §1–§21 (drugi przebieg GPT)

Poniżej oceniam wyłącznie §1–§21 dostarczonego tekstu. To luki i potencjalne exploity wynikające ze specyfikacji, nie potwierdzone błędy działającej gry. Pomijam problemy wskazane już po pierwszym przebiegu.

1. Niespójności wewnętrzne

- §3.2 kontra §4.2: `trait_loyalty` ma zakres 1–10, lecz pełna akceptacja podwyżki daje „lojalność +5 pkt”. Nie wiadomo, czy wynik jest obcinany do 10, czy chodzi o inny wskaźnik. To zmienia skuteczność ochrony przed strajkiem.
- §5.1: technicy podczas strajku „odmawiają naprawiania odwiertów”, ale jednocześnie „czas usuwania awarii wzrasta o 40%”. Jeśli nie pracują, naprawa nie powinna jedynie zwalniać. Podobnie strajk logistyki ma obniżać przepustowość o 30–80%, po czym pada kategoryczne „Ropa nie płynie do magazynu głównego!”. Trzeba określić, czy to redukcja częściowa, czy zatrzymanie konkretnych tras.
- §5.1 kontra §5.2: strajk HR powoduje „brak możliwości mediacji”, podczas gdy panel negocjacji jest drogą zakończenia strajku. Czy gracz może negocjować strajk samego HR, korzystać z innego mediatora, czy pozostaje tylko czekać?
- §11 i §21: przy strajku technicznym naprawa ma być niemożliwa lub spowolniona, natomiast macierz §21 dopuszcza „firmę zewnętrzną [...] za stawkę 2.5x” przy szkoleniu technika. Nie określono, czy to zastępstwo działa także podczas strajku i czy omija jego zasadniczy skutek.
- §19.1: „Progresywny Podatek Korporacyjny” ma jedną podaną stawkę — 0,05% dziennie. Brakuje stopni progresji; nie wiadomo też, czy opodatkowane jest całe saldo po przekroczeniu 50 mln USD, czy wyłącznie nadwyżka.
- §20 kontra §18.1: słownik definiuje FTS jako rejestr „z podwójnym zapisem (debit/credit)”, lecz GM może dokonać „korekty salda gotówki/banku”. Sama zmiana salda nie jest zapisem podwójnym. Specyfikacja musi wskazać konto przeciwstawne korekty oraz sposób uzgodnienia salda z księgą.
- §1.1 kontra §18.2: administrator dodaje pozycję menu, która ma się „natychmiast” pojawić wszystkim graczom, lecz nawigacja jest przekazywana jako `$page.props.navigation` przez `HandleInertiaRequests`. Samo odświeżenie cache na serwerze nie zmieni już otwartej strony Inertia; potrzebny jest sygnał i ponowne pobranie danych albo zmiana obietnicy UX.

2. Luki blokujące implementację i exploity według domen

- Reputacja (§2, §14, §21): diagram mówi `Cred --> B2BRep`, a §21 podaje konkretną regułę „rating B2B o 50% spadku wiarygodności” po wykryciu czarnego rynku. Nie wiadomo, czy ta sama reguła obowiązuje przy bankructwie, katastrofie i pozostałych zmianach `company_credibility`, ani czy odbudowa wiarygodności odbudowuje B2B. Ponadto opcjonalne 2FA daje „+5 pkt” i status zaufanego kontrahenta (§14): brak reguł wyłączenia, ponownego włączenia i odzyskania 2FA. Bez nich można wielokrotnie pobierać bonus lub zachować status po wyłączeniu zabezpieczenia.
- Rekrutacja i HR (§3–§4): limit „maksymalnie 2 procesy rekrutacyjne” nie określa zakresu — na firmę, rynek czy typ kandydata — ani momentu zwolnienia slotu po zakończeniu, anulowaniu lub nieodebraniu kandydata. Żądanie podwyżki powstaje, gdy zadowolenie spada „poniżej progu krytycznego”, ale próg, termin odpowiedzi i ograniczenie do jednego otwartego żądania na pracownika nie są podane. Kolejne przebiegi logiki mogą więc generować duplikaty sporów i kar morale. Wzór z §4.1 wymaga również `expected_salary > 0`; przy zerze nie da się go obliczyć.
- Strajki (§5, §21): warunek „średnie morale działu < 35 pkt” nie określa populacji (dyrektorzy, szkolący się, nieprzydzieleni, strajkujący) ani zachowania pustego działu. Nie opisano też kosztu, liczby i terminu rund negocjacji, dopuszczalnych ofert oraz tego, kiedy pobrać pieniądze i wznowić pracę. Zwolnienie lub przeniesienie najbardziej niezadowolonych osób może sztucznie usunąć przesłankę sporu, jeśli te granice pozostaną niejawne.
- Szkolenia i technika (§6, §11): kurs zajmuje pracownika na 24–72 h, a zadanie TTS ma przypisanego inżyniera. Brakuje jednej reguły wyłącznego przydziału między kursem, obsadą odwiertu i naprawą oraz przejścia po zwolnieniu lub rozpoczęciu strajku w trakcie zadania. „Unikalny certyfikat” nie określa, czy można zdobywać ten sam certyfikat wielokrotnie; nie ma maksymalnego poziomu sub-skilla, bazowej zdawalności ani limitu kumulowania bonusu +10% za powtórkę. Osobno §10–§11 nie określają kolejności łączenia budżetu BHP, poziomu procedur 1–5, ich `procedure_integrity` i skilla pracownika przy wyliczaniu ryzyka katastrofy.
- PvP (§7, §19.2): opłata i cooldown nie wyznaczają limitu szkody dla `player_budget_hit` ani delty `company_credibility` dla `player_dirty_report`. Brakuje limitu ataków otrzymywanych przez jedną firmę, reguł równoczesnych ataków wielu graczy i tego, co dzieje się z opłaconą operacją, gdy cel zmieni stan przed jej wykonaniem. W świecie bez resetów nękanie jednej ofiary może być bardziej opłacalne niż zwykła rywalizacja.
- Rynek spot i limit (§12, §19.3): spot dopuszcza „sprzedaż dowolnej ilości”, a przykład poślizgu dotyczy dużej partii „naraz”. Jeżeli poślizg liczy się od pojedynczego zlecenia, serię małych sprzedaży można wykorzystać do obejścia kosztu dużej sprzedaży. Limit nie określa kolejności wyścigu: automatyczna realizacja po osiągnięciu ceny kontra edycja/anulowanie i „natychmiastowy” zwrot ropy. Rezerwacja, sprzedaż i zwolnienie blokady muszą mieć jeden atomowy wynik.
- Kontrakty i escrow (§13, §21): kupujący blokuje 100% środków, ale specyfikacja nie blokuje ropy sprzedawcy ani nie definiuje dowodu dostawy, częściowych rat, odbioru i rozstrzygania sporu. Tę samą ropę można zatem obiecać B2B i wystawić na spot lub limit, o ile implementacja sama nie wymyśli rezerwacji. Nie wiadomo, kto i kiedy zwraca escrow po niedostarczeniu. Kaucja kontraktu systemowego także nie ma reguły przy częściowym wykonaniu: „przepadek kaucji” nie rozstrzyga, czy dotyczy całej umowy czy jednej transzy.
- Ekonomia i egzekucja (§13, §19.1, §21): podatek obejmuje środki „na rachunku bankowym”, a escrow jest poza zasięgiem komornika do rozliczenia lub anulowania. Bez terminu życia ofert, limitu depozytów, reguły anulowania i kontroli transakcji między powiązanymi kontami escrow może stać się schowkiem odraczającym zarówno podatek, jak i egzekucję. To warunkowy exploit wynikający z niezdefiniowanych zasad, nie dowód, że implementacja już go dopuszcza.
- Finanse i uprawnienia (§10, §12): dostęp do `/finance` wymaga dyrektora finansowego, ale spot wypłaca na bank, a inne procesy naliczają koszty niezależnie od obsady fotela. Specyfikacja nie rozróżnia uprawnień do oglądania danych, ustawiania budżetu, podpisywania kontraktu i operacji wykonywanych automatycznie. Bez tego utrata dyrektora może albo zablokować ratowanie firmy, albo uczynić kontrolę dostępu pozorną.
- Auth, CMS i prywatność (§14–§16): „zdalne wylogowanie” opiera rejestr urządzeń na IP i przeglądarce, które nie są identyfikatorami konkretnej sesji; trzeba powiązać akcję z identyfikatorem sesji/tokena, określić wygaśnięcie i ponowną weryfikację wrażliwej operacji. CMS zapisuje `content_html` i renderuje je w SPA bez określenia sanitizacji, dopuszczalnych osadzeń i linków — to ryzyko trwałego XSS po błędzie edytora lub przejęciu konta. §16 klasyfikuje token „Remember Me” jako funkcjonalny, lecz nie określa, czy odmowa tej kategorii usuwa istniejący token i wyłącza zapamiętywanie; wymaga to jawnego zachowania. Trzy wersje językowe strony CMS nie mają pól ani reguły fallbacku dla `title`, `slug` i `content_html`.
- Admin i edge cases (§18.1, §21): „Reset stanu” może wyczyścić dług, zakończyć strajk i odblokować odwiert, ale nie określa losu rat, postępowania komorniczego, licytacji, ultimatum, przydzielonych zadań i oczekujących akcji. Zmiana samych flag pozwoliłaby starym zadaniom odtworzyć skutek resetu. Przy `waiting_bay` opłata 500 USD/h po 2 h nie ma odpowiedzi na brak środków, rezygnację z kursu, limit kolejki i zablokowanie cysterny na zawsze.

3. Ryzyka techniczne wymagające konkretnych decyzji

- §12–§13, §20–§21: FTS, stan magazynu i escrow muszą być zatwierdzane w granicach tej samej operacji biznesowej, z jednoznacznym kluczem idempotencji. Inaczej ponowienie zadania rozliczającego dostawę może wypłacić środki dwa razy, nawet jeśli każdy pojedynczy zapis do księgi jest poprawny. Laravel udostępnia transakcje DB, ale specyfikacja nie przypisuje im granic domenowych. ([laravel.com](https://laravel.com/framework/docs/11.x/database?utm_source=openai))
- §6, §11–§13, §21: egzamin, zakończenie naprawy, wykonanie limitu i rata kontraktu mogą dojść do skutku równolegle z anulowaniem, zwolnieniem pracownika lub zmianą właściciela aktywa. Potrzebne są stany terminalne, blokady/warunkowe aktualizacje oraz zasada ponowienia. Dla harmonogramu uruchamianego na wielu instancjach trzeba też zdecydować o blokadzie nakładania się i pojedynczym wykonawcy; scheduler Laravel nie zapobiega nakładaniu domyślnie. ([laravel.com](https://laravel.com/framework/docs/11.x/scheduling?utm_source=openai))
- §1, §12, §18.2: notowania można rozsyłać publicznie, ale salda, alarmy, oferty i kontrakty wymagają kanałów autoryzowanych dla właściciela lub uczestników. Zdarzenia publikowane przed zatwierdzeniem transakcji mogą pokazać stan, którego baza ostatecznie nie przyjęła; Laravel opisuje ten problem oraz emisję po commicie. Nie należy też nadawać całego modelu gracza jako payloadu zdarzenia. ([laravel.com](https://laravel.com/framework/docs/11.x/broadcasting?utm_source=openai))
- §8 i §18: „auto-discovery zasobów” Filament nie definiuje polityki dostępu do odczytu i do każdej akcji GM. Role `admin` i `gm` oraz ich 2FA nie mówią, kto może zmieniać saldo, anulować dług czy wywoływać katastrofę. Potrzebna jest macierz uprawnień per akcja i niezależny audyt jej wykonania.
- §19–§20: przy zerowej liczbie wipe’ów logi wiarygodności, decyzji finansowych, transakcje FTS i historia cen będą rosły bez końca. Specyfikacja nie określa kluczy partycjonowania/archiwizacji, polityki odtwarzania projekcji i sposobu uzgodnienia księgi po awarii. To osobne wymaganie dla bazy, nie tylko kwestia pojemności dysku.

4. TOP 10 poprawek, w kolejności

1. Opisać atomowe rozliczenie sprzedaży i dostaw: rezerwacje ropy i pieniędzy, księgowanie FTS, stany terminalne oraz idempotencję (§12–§13, §20–§21).
2. Zdefiniować pełną maszynę stanów B2B i kontraktów systemowych: raty, częściową dostawę, dowód odbioru, spór, zwrot escrow i kaucji (§13).
3. Zamknąć obejście podatku i komornika przez escrow; określić podstawę i faktyczne progi podatku (§19.1, §21).
4. Ustalić model ceny spot i poślizgu dla łącznego wolumenu sprzedaży, w tym wielu małych zleceń (§12, §19.3).
5. Ujednolicić efekty strajku oraz określić, kto i za co może zakończyć strajk HR lub zamówić zastępczą naprawę (§5, §11, §21).
6. Wprowadzić wyłączny przydział pracownika i komplet przejść dla rekrutacji, żądań, szkoleń, zadań i zwolnień (§3–§6, §11).
7. Ograniczyć szkody PvP per ofiara i okres oraz opisać wynik operacji przy zmianie stanu celu (§7).
8. Zdefiniować wzory i granice reputacji, morale, skilli oraz łączenia modyfikatorów BHP; rozstrzygnąć bonus i wycofanie 2FA (§2, §4, §6, §10–§11, §14).
9. Nadać GM osobne uprawnienia per akcja i transakcyjne procedury resetu wraz z wygaszeniem zależnych procesów (§8, §18, §21).
10. Ustalić kontrakt publikowania danych po commicie, autoryzację kanałów Reverb, odświeżanie nawigacji Inertia oraz sanitizację CMS (§1, §12, §15, §18.2). ([laravel.com](https://laravel.com/framework/docs/11.x/broadcasting?utm_source=openai))
