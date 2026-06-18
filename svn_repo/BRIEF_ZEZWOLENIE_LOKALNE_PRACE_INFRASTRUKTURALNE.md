# BRIEF DLA AI — Zezwolenie lokalne na prace infrastrukturalne

## Cel

Wprowadzamy drugi poziom zezwoleń w dziale prawnym.

Nie zastępuje on zezwolenia regionalnego. Jest kolejnym etapem, potrzebnym do uruchamiania infrastruktury w regionie.

Najprostsza nazwa widoczna dla gracza:

**Zezwolenie na prace lokalne**

Pełna nazwa techniczna / opisowa:

**Zezwolenie lokalne na prace infrastrukturalne**

---

## 1. Główna zasada

System zezwoleń ma działać w dwóch poziomach.

### Poziom 1 — zezwolenie regionalne na wiercenie

To zezwolenie pozwala wejść do regionu i kupować odwierty.

Odblokowuje:

- możliwość kupna odwiertów w regionie,
- możliwość rozpoczęcia działalności wydobywczej,
- dostęp do odwiertów na mapie danego regionu.

### Poziom 2 — zezwolenie lokalne na prace infrastrukturalne

To zezwolenie pozwala uruchamiać i podłączać infrastrukturę w regionie.

Odblokowuje:

- wynajem huba systemowego,
- zakup używanego huba,
- zakup nowego huba,
- uruchomienie huba,
- przypisanie odwiertu do huba,
- instalację rurociągu lokalnego,
- instalację głównego rurociągu,
- podłączenie odwiertu do infrastruktury logistycznej.

---

## 2. Kolejność działania

Kolejność ma być zawsze taka:

1. Gracz uzyskuje zezwolenie regionalne na wiercenie.
2. Dopiero wtedy może kupić odwiert w regionie.
3. Następnie, żeby uruchomić logistykę, musi uzyskać zezwolenie lokalne na prace infrastrukturalne.
4. Dopiero po uzyskaniu zezwolenia lokalnego może korzystać z hubów i rurociągów.

Schemat:

**zezwolenie regionalne → zakup odwiertu → zezwolenie lokalne → hub / rurociąg → produkcja**

---

## 3. Co blokuje brak zezwolenia lokalnego

Jeśli gracz ma zezwolenie regionalne, ale nie ma zezwolenia lokalnego, może mieć prawo do kupna odwiertu, ale nie może jeszcze uruchomić pełnej infrastruktury.

Bez zezwolenia lokalnego gracz nie może:

- wynająć huba systemowego,
- kupić używanego huba,
- kupić nowego huba,
- aktywować huba,
- przypisać odwiertu do huba,
- zainstalować rurociągu,
- uruchomić pełnej logistyki odwiertu.

---

## 4. Dlaczego to jest spójne

Zezwolenie regionalne oznacza:

**Firma ma zgodę na wejście do regionu i wiercenie.**

Zezwolenie lokalne oznacza:

**Firma ma zgodę na wykonywanie prac infrastrukturalnych w terenie.**

Nie należy mieszać tych dwóch rzeczy.

---

## 5. Huby a zezwolenie lokalne

### Hub systemowy pod wynajem

Hub istnieje już w świecie gry, ale gracz chce z niego korzystać.

Nie wymaga to pozwolenia na budowę, ale wymaga zezwolenia lokalnego na prace i korzystanie z infrastruktury.

Czyli:

**wynajem huba systemowego wymaga zezwolenia lokalnego, ale nie wymaga pozwolenia na budowę.**

### Hub używany

Gracz kupuje istniejącą infrastrukturę w gorszym stanie technicznym.

To nadal wymaga zgody lokalnej, ponieważ firma przejmuje i uruchamia infrastrukturę w regionie.

### Hub nowy

Gracz kupuje albo uruchamia nowy hub.

To wymaga zezwolenia lokalnego.

W przyszłości duże huby mogą dodatkowo wymagać osobnego procesu budowy, ale nie trzeba tego wdrażać w tym etapie.

### Duży hub

Duży hub powinien być traktowany jako poważna inwestycja.

Docelowo może wymagać:

- dużego kapitału,
- czasu budowy,
- zezwolenia lokalnego,
- ewentualnie dodatkowego pozwolenia infrastrukturalnego w przyszłości.

Na ten moment wystarczy, że duże huby również wymagają zezwolenia lokalnego.

---

## 6. Rurociągi a zezwolenie lokalne

Rurociąg zawsze oznacza ingerencję w teren.

Dlatego instalacja rurociągu powinna wymagać zezwolenia lokalnego.

Dotyczy to:

- rurociągu lokalnego z odwiertu do huba,
- głównego rurociągu z huba do magazynu,
- przyszłych większych połączeń logistycznych.

Bez zezwolenia lokalnego gracz nie może zainstalować rurociągu.

---

## 7. Modal dla gracza

Jeśli gracz próbuje wynająć hub, kupić hub albo zainstalować rurociąg bez zezwolenia lokalnego, system pokazuje modal.

### Tytuł

**Brak zezwolenia na prace lokalne**

### Treść

**Masz zezwolenie na wiercenie w tym regionie, ale nie masz jeszcze zgody na prace lokalne. Uzyskaj zezwolenie lokalne, aby wynająć lub kupić hub, podłączyć odwiert i zainstalować rurociąg.**

### Przyciski

- **Anuluj**
- **Złóż wniosek**
- **Przejdź do działu prawnego**

---

## 8. Widok w dziale prawnym

Dział prawny powinien pokazywać dwa typy zezwoleń:

### Zezwolenie regionalne na wiercenie

Opis:

**Pozwala kupować odwierty i rozpocząć działalność wydobywczą w regionie.**

### Zezwolenie na prace lokalne

Opis:

**Pozwala uruchamiać infrastrukturę w regionie: huby, rurociągi i podłączenia logistyczne.**

---

## 9. Panel admina

Panel admina powinien umożliwić konfigurację zezwoleń lokalnych.

Admin powinien móc ustawić:

- region,
- koszt wniosku,
- czas rozpatrzenia,
- ryzyko opóźnienia decyzji,
- ryzyko braku decyzji,
- ryzyko odmowy,
- cooldown po odmowie,
- czy zezwolenie lokalne jest wymagane w regionie.

Gracz nie widzi procentów ryzyka.

Gracz widzi tylko:

- koszt,
- przewidywany czas,
- status wniosku,
- informację, co zezwolenie odblokowuje.

---

## 10. Powiadomienia

Powiadomienia mają działać jak w dziale technicznym.

Przykłady:

- **Złożono wniosek o zezwolenie na prace lokalne w regionie Polska.**
- **Uzyskano zezwolenie na prace lokalne w regionie Polska.**
- **Decyzja w sprawie zezwolenia lokalnego została opóźniona.**
- **Urząd nie wydał decyzji w sprawie zezwolenia lokalnego.**
- **Wniosek o zezwolenie lokalne został odrzucony.**

---

## 11. Czego nie robić teraz

Nie wdrażać jeszcze:

- osobnych pozwoleń na każdy typ huba,
- osobnych pozwoleń na każdy rurociąg,
- portów,
- terminali,
- rozbudowanego sądu,
- ugód,
- łapówek,
- kar prawnych za brak zezwolenia,
- zezwoleń offshore jako osobnego pełnego systemu.

Na ten etap wystarczy:

**zezwolenie regionalne + zezwolenie lokalne**

---

## 12. Najkrótsza wersja dla AI

Dodać drugi poziom zezwoleń: **Zezwolenie na prace lokalne**. Zezwolenie regionalne pozwala kupić odwiert w regionie. Zezwolenie lokalne pozwala korzystać z infrastruktury: wynająć hub systemowy, kupić używany lub nowy hub, aktywować hub, przypisać odwiert do huba i instalować rurociągi. Bez zezwolenia lokalnego gracz nie może uruchomić hubów ani rurociągów. Nie tworzyć osobnych pozwoleń na każdy hub i rurociąg — wszystko ma być spięte jednym, czytelnym zezwoleniem lokalnym.
