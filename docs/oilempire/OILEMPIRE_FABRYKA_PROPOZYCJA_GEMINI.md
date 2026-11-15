To jest ogromny, przełomowy krok w rozwoju OILEMPIRE. Wprowadzenie sektora petrochemicznego i rafinerii przenosi grę z poziomu „zwykłego wydobywcy surowca” do poziomu zintegrowanego globalnego koncernu energetycznego (Supermajor) – na wzór PKN Orlen, Shell, BP czy Saudi Aramco.

Zgodnie z Twoim poleceniem powołałem do tego zadania pełny zespół 7 wyspecjalizowanych agentów-architektów. Każdy z nich opracował swój filar tego gigantycznego systemu:

┌───────────────────────────────────────────────────────────────────────────────────────────────────────┐
│                     RADA PROJEKTOWA OILEMPIRE: MODUŁ RAFINERII I PETROCHEMII                         │
├───────────────────────────────┬───────────────────────────────────────────────────────────────────────┤
│ 1. INŻYNIER CHEMII PRZEMYSŁ.  │ Bilans masowy ropy, procesy krakingu, 4 produkty, etapy budowy       │
│ 2. ARCHITEKT SYSTEMOWY & DB   │ Baza danych, maszyna stanów, silnik Delta-Time przetwórstwa           │
│ 3. DYREKTOR LOGISTYKI FLOTY   │ Transport paliw czystych, cysterny kolejowe, huby lotniskowe          │
│ 4. GŁÓWNY EKONOMISTA & RYNEK  │ Wskaźnik Crack Spread, giełda hurtowa, kontrakty z liniami lotniczymi │
│ 5. DYREKTOR BHP, HSE & RYZYKA │ Zagrożenia wybuchowe (BLEVE), Seveso III, certyfikaty jakości         │
│ 6. LEAD FRONTEND & UX/UI      │ Schemat instalacji P&ID w Vue 3, dark luxury, sub-menu OPERACJE       │
│ 7. ARCHITEKT PANELU ADMINA    │ Filament v3, nadzór mocy przerobowych, podgląd w Widoku 360°          │
└───────────────────────────────┴───────────────────────────────────────────────────────────────────────┘
AGENT 1: Główny Inżynier Chemik i Procesowy

Rola: Technologia chemiczna, bilans rozfrakcjonowania baryłki, etapy budowy instalacji od fundamentów.

A. Bilans Masowy Baryłki (Co wychodzi z ropy surowej?)

Z ropy nie powstaje jedno paliwo. Zależnie od jakości surowca na wejściu, proces destylacji rozbija baryłkę na 4 główne produkty oraz 2 frakcje resztkowe:

100 bbl Ropy Surowej
⟶
{
42
 bbl
	
Benzyna silnikowa (Pb 95 / Pb 98)


28
 bbl
	
Olej Napędowy (Diesel ON / Euro 6)


14
 bbl
	
Nafta Lotnicza (Jet A-1 / Kerosene)


10
 bbl
	
Cię
z
˙
ki Olej Opałowy (Mazut do statk
o
ˊ
w)


4
 bbl
	
Asfalt drogowy i bituminy


2
 bbl
	
Gazy rafineryjne (LPG / straty kominowe)
100 bbl Ropy Surowej⟶
⎩
⎨
⎧
	​

42 bbl
28 bbl
14 bbl
10 bbl
4 bbl
2 bbl
	​

Benzyna silnikowa (Pb 95 / Pb 98)
Olej Napędowy (Diesel ON / Euro 6)
Nafta Lotnicza (Jet A-1 / Kerosene)
Cię
z
˙
ki Olej Opałowy (Mazut do statk
o
ˊ
w)
Asfalt drogowy i bituminy
Gazy rafineryjne (LPG / straty kominowe)
	​


Głębokość technologiczna: Gracz może inwestować w instalacje krakingu katalitycznego (FCC), aby „przesuwać” proporcje – np. zmniejszać produkcję taniego mazutu, a zwiększać udział drogocennego paliwa lotniczego Jet A-1!

B. 5 Etapów Budowy Rafinerii (Od działki do superkompleksu)

Inwestycja w rafinerię to proces długofalowy, podzielony na fazy kapitałowe:

Faza 0 – Przygotowanie Terenu i Koncesja Przetwórcza:
Koszt: 15 000 000 PLN | Czas budowy: 72 godziny.
Wymogi: Zgoda Ministerstwa Środowiska, wykup działki o statusie przemysłowym przy węźle rurociągowym lub kolejowym.
Faza 1 – Kolumna Destylacji Atmosferycznej (ADU - Atmospheric Distillation Unit):
Koszt: 35 000 000 PLN | Czas: 96 godzin.
Przerób bazowy: 500 bbl surowca / godzinę.
Umożliwia produkcję podstawowej Benzyny 95 oraz Mazutu.
Faza 2 – Kompleks Hydrorafinacji Oleju Napędowego (HDS Unit):
Koszt: 25 000 000 PLN | Czas: 48 godzin.
Usuwa siarkę, odblokowuje sprzedaż standardowego Diesla ON do sieci transportowych i stacji paliw.
Faza 3 – Kraking Katalityczny (FCC) & Reforming:
Koszt: 45 000 000 PLN | Czas: 120 godzin.
Zwiększa uzysk paliw lekkich kosztem mazutu; odblokowuje Benzynę 98 Super Plus.
Faza 4 – Certyfikowana Linia Nafty Lotniczej (Jet A-1 Merox & Dewaxing):
Koszt: 60 000 000 PLN | Czas: 144 godziny.
Wymaga sterylnego odwadniania i filtracji. Paliwo spełnia rygorystyczne normy lotnictwa cywilnego (IATA).
AGENT 2: Główny Architekt Systemowy & Backendowiec

Rola: Architektura modułu modules/Refinery, tabele SQL, ciągły silnik Delta-Time, obsługa blokad.

A. Struktura Modułowa (modules/Refinery)
modules/Refinery/
├── Database/
│   └── Migrations/
│       ├── create_refineries_table.php
│       ├── create_refinery_units_table.php
│       ├── create_refinery_tanks_table.php
│       └── create_refinery_batches_table.php
├── Models/
│   ├── Refinery.php
│   ├── RefineryUnit.php
│   └── RefineryTank.php
├── Services/
│   ├── RefinerySettlementService.php      # Silnik Delta-Time przetwórstwa
│   ├── CrackSpreadCalculator.php          # Kalkulator zyskowności przerobu
│   └── RefinerySafetyService.php          # Procedury Seveso i awarie
└── Http/Controllers/
    └── RefineryController.php
B. Silnik Przetwórstwa w Czasie Rzeczywistym (Delta-Time Processing)

Rafineria nie czeka na crona. Działa w oparciu o ten sam sprawdzony silnik Delta-Time co odwierty:
Δ
𝑡
=
now
(
)
−
last_processed_at
Δt=now()−last_processed_at
Zu
z
˙
yta ropa
=
min
⁡
(
ropa w buforze wej
s
ˊ
ciowym
,
Δ
𝑡
×
throughput_per_sec
)
Zu
z
˙
yta ropa=min(ropa w buforze wej
s
ˊ
ciowym,Δt×throughput_per_sec)

Zabezpieczenie Nasycenia Zbiorników (Storage Choke / Plateau): Każdy produkt (Benzyna, Diesel, Jet A-1) trafia do dedykowanego zbiornika. Jeśli zbiornik nafty lotniczej napełni się w 100%, a gracz nie odbiera paliwa:
Opcja A (Automatyczny zawór bezpieczeństwa): Rafineria dławi przerób o 50% i przepala frakcję na pochodni gazowej (Flaring – gracz ponosi karę środowiskową).
Opcja B (Zatrzymanie instalacji): Kolumna destylacyjna przechodzi w kosztowny tryb gorącej recyrkulacji (paused_product_full), pobierając 40% OPEX bez produkcji.
AGENT 3: Dyrektor Logistyki i Floty Paliw Gotowych

Rola: Nowy tier transportu masowego, specjalistyczne cysterny, dedykowane rurociągi produktowe.

Ropy surowej i czystego paliwa lotniczego nie wolno wozić tymi samymi cysternami (ryzyko zanieczyszczenia i katastrofy samolotu!).

┌────────────────────────────────────────────────────────────────────────────────────────────────────────┐
│                              ŁAŃCUCH LOGISTYCZNY PALIW GOTOWYCH                                        │
├──────────────────────────┬──────────────────────────┬──────────────────────────────────────────────────┤
│ 1. SKŁADY KOLEJOWE (PKP) │ Pociągi cysterny (Pesa / │ • Masowy transport benzyny i diesla (2 000 bbl) │
│                          │ Bombardier)              │ • Łączą rafinerię z regionalnymi bazami paliw    │
├──────────────────────────┼──────────────────────────┼──────────────────────────────────────────────────┤
│ 2. CYSTERNY BIAŁE (ADR)  │ Dedykowana flota paliw   │ • Wyłożone powłoką antykorozyjną ze stali nierdz.│
│                          │ czystych (Aluminium/Inox)│ • Dowożą paliwo bezpośrednio na stacje benzynowe │
├──────────────────────────┼──────────────────────────┼──────────────────────────────────────────────────┤
│ 3. RUROCIĄG NAFTY JET    │ Bezpośrednia magistrala  │ • Rurociąg łączący rafinerię z hubem lotniskowym │
│                          │ podziemna do Portu Lotn. │ • Ciągłe tłoczenie pod ciśnieniem 25 barów       │
└──────────────────────────┴──────────────────────────┴──────────────────────────────────────────────────┘
Terminal Paliw Lotniskowych (Airport Fuel Depot): Gracz buduje bazę przeładunkową tuż przy międzynarodowym porcie lotniczym. Odbiera tam Jet A-1 i tankuje samoloty pasażerskie w kontraktach długoterminowych.
AGENT 4: Główny Ekonomista & Szef Rynku Paliw

Rola: Wskaźnik Crack Spread, giełda hurtowa produktów, zróżnicowanie cen i odbiorcy B2B.

A. Wskaźnik Marży Rafineryjnej (Crack Spread Index)

W świecie finansów rafineria nie zarabia na samej cenie ropy, lecz na różnicy między kosztem zakupu ropy a ceną sprzedaży gotowych paliw:

Crack Spread (PLN/bbl)
=
(
0.42
×
𝑃
benzyna
+
0.28
×
𝑃
diesel
+
0.14
×
𝑃
jet
+
0.10
×
𝑃
mazut
)
−
𝑃
ropa
Crack Spread (PLN/bbl)=(0.42×P
benzyna
	​

+0.28×P
diesel
	​

+0.14×P
jet
	​

+0.10×P
mazut
	​

)−P
ropa
	​

Dynamika Rynkowa:
W lecie rośnie popyt na naftę lotniczą (sezon wakacyjny/czartery samolotów) 
→
→ marża na Jet A-1 skacze o +45%.
W zimie rośnie popyt na olej napędowy i opałowy 
→
→ diesel zyskuje kosztem benzyny.
Gracz musi stale balansować produkcją, aby wyciskać maksymalny zysk z każdej przetworzonej baryłki!
B. 4 Rynki Zbytu dla Rafinerii
Giełda Hurtowa Paliw (Wholesale Spot Market): Szybka sprzedaż partii benzyny i diesla po aktualnym kursie giełdowym hurtowniom paliw.
Kontrakty z Liniami Lotniczymi (Aviation Offtake Agreements):
Stałe umowy z przewoźnikami (np. Lufthansa Cargo, PLL LOT, Emirates Fuel Service).
Dostawa 20 000 baryłek Jet A-1 tygodniowo. Kaucja: 5 000 000 PLN. Gigantyczna kara za niedostarczenie paliwa na czas (paraliż lotniska).
Sieci Stacji Benzynowych (Retail Gas Networks):
Długoterminowe zaopatrzenie 500 stacji paliw w Benzynę 95/98 i Diesel ON.
Rezerwy Strategiczne Państwa (Government Energy Security Agency):
Rząd skupuje paliwo do podziemnych magazynów wojskowych. Płaci +20% powyżej ceny rynkowej, ale próbka laboratoryjna przechodzi rygorystyczny test jakości (odrzucenie partii obniża company_credibility o 15 pkt).
AGENT 5: Dyrektor Bezpieczeństwa, HSE & Ryzyka Katastrof

Rola: Inspekcje przemysłowe, dyrektywa Seveso III, katastrofy chemiczne i ubezpieczenia.

Rafineria przetwarza tysiące ton skrajnie łatwopalnych węglowodorów pod wysokim ciśnieniem i temperaturą 
400
∘
C
400
∘
C. Jest to Zakład Dużego Ryzyka (ZDR / Seveso III):

Katastrofa BLEVE (Boiling Liquid Expanding Vapor Explosion):
Najgroźniejsza awaria w grze: rozerwanie zbiornika ciśnieniowego benzyny.
Prawdopodobieństwo rośnie, gdy stan techniczny instalacji spadnie poniżej 60%, a procedury BHP są na poziomie niższym niż 4.
Skutek: Pożar 50% kompleksu, straty rzędu 40 000 000 PLN, zablokowanie zakładu przez prokuraturę i straż pożarną na 7 dni.
Laboratorium Kontroli Jakości (Quality Assurance Lab):
Każda partia paliwa lotniczego Jet A-1 musi uzyskać certyfikat czystości.
Gracz musi zatrudniać chemików analityków. Jeśli partia zanieczyszczona parafiną trafi do samolotu 
→
→ katastrofa, utrata 100% reputacji kontraktowej i wejście komornika na konto korporacji.
Polisa Ubezpieczeniowa Przerwy w Pracy (Business Interruption Insurance):
Płatna miesięcznie polisa, która w razie pożaru wypłaca 70% utraconych zysków z kontraktów rafinerii.
AGENT 6: Lead Frontend & UX/UI Designer

Rola: Interfejs gracza w Vue 3, dark luxury, schemat instalacji, integracja z menu.

A. Nowa Pozycja w Menu OPERACJE:

Na górnej belce nawigacyjnej, w menu OPERACJE, obok Mapy, Techniki i Logistyki, pojawia się czwarty, prestiżowy moduł:

[OPERACJE ▾] -> [🗺️ Mapa]  [🔧 Technika]  [🚚 Logistyka]  [🏭 RAFINERIA]
B. Pulpit Zarządzania Rafinerią (/operations/refinery):

Interfejs w klimacie Dark Luxury z interaktywnym, animowanym schematem technologicznym:

┌────────────────────────────────────────────────────────────────────────────────────────────────────────┐
│ CENTRUM PRZETWÓRSTWA NAFTOWEGO -> Rafineria "Texas Star Complex" #1         [Status: PRACA 100%]       │
├────────────────────────────────────────────────────────────────────────────────────────────────────────┤
│ WEJŚCIE: ROPA SUROWA             KOLUMNA DESTYLACYJNA (ADU/FCC)        WYJŚCIE: ZBIORNIKI PRODUKTÓW    │
│                                                                                                        │
│ ┌─ Bufor Wejściowy ─────────┐     ┌─ Instalacje Przerobu ──────────┐   ┌─ BENZYNA 95/98 (Pb) ────────┐ │
│ │ Ropa: 12 400 / 20 000 bbl │ ──► │ • Przerób: 650 bbl / godz.     │ ─►│ 18 200 bbl  [Zbywaj na giełdę]│
│ │ Ciśnienie: 42 bar (Opt.)  │     │ • Temp. pieca: 385 °C          │   └─────────────────────────────┘ │
│ └───────────────────────────┘     │ • Stan techniczny: 94% [Sprawny│   ┌─ OLEJ NAPĘDOWY (Diesel ON) ─┐ │
│                                   │ • Marża Crack Spread: +$18.40  │ ─►│ 14 100 bbl  [Kontrakt Stacje] │
│                                   └────────────────────────────────┘   └─────────────────────────────┘ │
│                                                                        ┌─ NAFTA LOTNICZA (Jet A-1) ──┐ │
│                                                                     ─► │  6 800 bbl  [Rurociąg Lotnisko│
│                                                                        └─────────────────────────────┘ │
│                                                                        ┌─ MAZUT & ASFALT ────────────┐ │
│                                                                     ─► │  4 100 bbl  [Port Bunkrowy] │ │
│                                                                        └─────────────────────────────┘ │
├────────────────────────────────────────────────────────────────────────────────────────────────────────┤
│ ZAKŁADKI: [1. Pulpit Przerobu] [2. Rozbudowa Kolumn] [3. Laboratorium Jakości] [4. Kontrakty Odbioru] │
└────────────────────────────────────────────────────────────────────────────────────────────────────────┘
Animacja płynącego strumienia: W Vue 3 / SVG rury rozdzielające ropę pulsują kolorami produktów:
Złoty: Benzyna,
Błękitny: Nafta lotnicza Jet A-1,
Morski/Niebieski: Diesel ON,
Czarny: Ciężki mazut i asfalt.
Wskaźnik Crack Spread na żywo: Duży, złoty zegar pokazujący aktualną marżę rafineryjną w PLN/bbl w czasie rzeczywistym.
AGENT 7: Architekt Panelu Administratora (Filament v3)

Rola: Nadzór nad gospodarką paliwową, moderacja GM, integracja z Widokiem 360° Gracza.

W panelu Filament v3 administrator otrzymuje pełny nadzór nad przetwórstwem w uniwersum OILEMPIRE:

Nowy Zasób: RefineryResource:
Lista wszystkich wybudowanych rafinerii w grze.
Poziomy instalacji, zużycie surowca, wskaźniki emisji spalin, historia incydentów.
Możliwość globalnego wymuszenia awarii (np. awaria zasilania w Teksasie) lub skażenia partii paliwa w celach eventowych.
Rozszerzenie Widoku 360° Gracza (PlayerResource):
Nowa zakładka: Kompleks Rafineryjny:
Podgląd stanu magazynów produktów gotowych gracza (ile ma benzyny, ile diesla, ile nafty lotniczej).
Wycena majątku trwałego w instalacjach chemicznych.
Podgląd aktywnych umów offtake z lotniskami i stacjami paliw.
Konfigurator Balansu Petrochemicznego:
Administrator może w locie regulować globalne zużycie paliwa lotniczego przez lotnictwo NPC oraz normy środowiskowe.
Podsumowanie i Decyzja Wdrożeniowa

Model rafinerii jest kompletnym, organicznym rozszerzeniem OILEMPIRE, które idealnie scala wszystkie istniejące systemy:

Wykorzystuje silnik Delta-Time i 5-minutowy Sweeper,
Tworzy gigantyczne ujście kapitału (Capital Sink) dla bogatych graczy w świecie Zero Wipe,
Łączy logistykę (kolej, rurociągi, cysterny ADR), rynek hurtowy i kontrakty lotnicze.