<?php
/**
 * fix_lang_from_3856.php
 * Naprawa lang/pl.php od linii 3856 do konca.
 * - Poprawia mojibake / brakujace polskie znaki
 * - Usuwa zduplikowany blok (bank.*, black_market.*, auth.*, news.*) linie 4216-4276
 */
declare(strict_types=1);

$file = __DIR__ . '/lang/pl.php';
$content = file_get_contents($file);
if ($content === false) {
    echo "[BLAD] Nie mozna odczytac pliku.\n";
    exit(1);
}

// Kopia zapasowa
$backupDir = __DIR__ . '/backup';
if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
$bak = $backupDir . '/pl.php.bak_fix3856_' . date('Ymd_His');
copy($file, $bak);
echo "BAK: $bak\n";

// ── 1. Usun zduplikowany blok (linie ~4216-4276) ────────────────────────────
// Blok duplikatu zaczyna sie od "\n// Bank action handler\n'bank.action_err_csrf'"
// i konczy na "'news.time_days_ago' => ':count dni temu',"
// Usuwamy DRUGIE wystapienie calego bloku.

$dupStart = "\n// Bank action handler\n'bank.action_err_csrf'";
$dupEnd   = "'news.time_days_ago'";

$firstPos  = strpos($content, $dupStart);
$secondPos = strpos($content, $dupStart, $firstPos + 10);

if ($secondPos !== false) {
 // Znajdz koniec drugiego bloku (po ostatnim kluczu news.time_days_ago)
    $endPos = strpos($content, $dupEnd, $secondPos);
    if ($endPos !== false) {
 // Znajdz koniec tej linii
        $lineEnd = strpos($content, "\n", $endPos);
        if ($lineEnd === false) $lineEnd = strlen($content);
 // Usun od poczatku duplikatu do konca linii (wlacznie z newline)
        $content = substr($content, 0, $secondPos) . substr($content, $lineEnd + 1);
        echo "[OK] Usunieto zduplikowany blok (bank/black_market/auth/news).\n";
    }
} else {
    echo "[INFO] Duplikat nie znaleziony (moze juz usuniety).\n";
}

// ── 2. Poprawki wartosci (stary tekst => nowy tekst) ─────────────────────────
// Format: [$stary, $nowy] - podmieniamy tylko w wartosciach stringow

$fixes = [

 // === dm ===
    ["'Nie udao si usun zacznika.'",  "'Nie udało się usunąć załącznika.'"],

 // === admin.logistics subtitle / stats ===
    ["'Huby s infrastruktur systemow (player_id = 0). Gracz przypisuje odwierty  nie buduje hubw.'",
     "'Huby są infrastrukturą systemową (player_id = 0). Gracz przypisuje odwierty — nie buduje hubów.'"],
    ["=> 'Wszystkich hubw'",          "=> 'Wszystkich hubów'"],
    ["=> 'Pozostaych'",               "=> 'Pozostałych'"],

 // === verify ===
    ["'Weryfikacja ticku hubw'",       "'Weryfikacja ticku hubów'"],
    ["'Przypisane do hubw'",           "'Przypisane do hubów'"],
    ["=> 'aktywnych przypisa'",        "=> 'aktywnych przypisań'"],
    ["=> 'OPEX hubw'",                 "=> 'OPEX hubów'"],
    ["':count odwiertw bez huba  wyszy OPEX i straty przez fallback!'",
     "':count odwiertów bez huba — wyższy OPEX i straty przez fallback!'"],

 // === seed ===
    ["'Masowy seed hubw dla regionu'", "'Masowy seed hubów dla regionu'"],
    ["'Tworzy N hubw kadego typu (mae + rednie + due) dla wybranego regionu. Docelowo: 20  3 = 60 hubw/region.'",
     "'Tworzy N hubów każdego typu (małe + średnie + duże) dla wybranego regionu. Docelowo: 20 x 3 = 60 hubów/region.'"],
    ["'Ilo kadego typu'",              "'Ilość każdego typu'"],
    ["'Zainicjowa huby dla: :region?'","'Zainicjować huby dla: :region?'"],
    ["'Seed regionu #:id: utworzono :count hubw, bdy: :errors.'",
     "'Seed regionu #:id: utworzono :count hubów, błędy: :errors.'"],
    ["'Podaj prawidowe region_id.'",   "'Podaj prawidłowe region_id.'"],

 // === create ===
    ["'Utwrz pojedynczy hub'",         "'Utwórz pojedynczy hub'"],
    ["'np. Hub May Wrocaw A1'",        "'np. Hub Mały Wrocław A1'"],
    ["=> 'Utwrz'",                     "=> 'Utwórz'"],
 // create_err (dwa wystapienia tego samego klucza - oba naprawiamy)
    ["'Bd tworzenia huba: :error'",    "'Błąd tworzenia huba: :error'"],

 // === list ===
    ["'Lista hubw'",                   "'Lista hubów'"],
    ["'Brak hubw speniajcych kryteria. Uyj seeda aby utworzy huby.'",
     "'Brak hubów spełniających kryteria. Użyj seeda aby utworzyć huby.'"],

 // === hub fields ===
    ["=> 'Zuycie'",                    "=> 'Zużycie'"],
    ["'Ostatni tick  obcienie'",        "'Ostatni tick — obciążenie'"],
    ["'Ostatni tick  straty'",          "'Ostatni tick — straty'"],

 // === hub types ===
    ["=> 'May (lokalny)'",             "=> 'Mały (lokalny)'"],
    ["=> 'redni (regionalny)'",        "=> 'Średni (regionalny)'"],
    ["=> 'Duy (kontynentalny)'",       "=> 'Duży (kontynentalny)'"],

 // === hub statuses ===
    ["=> 'Wyczony'",                   "=> 'Wyłączony'"],
    ["=> 'Przeciony'",                 "=> 'Przeciążony'"],

 // === hub modes ===
    ["'Eco  mniejsze straty, wolniej'", "'Eco — mniejsze straty, wolniej'"],
    ["'Standard  normalny ruch'",       "'Standard — normalny ruch'"],
    ["'Turbo  szybko, ale zuywa hub'",  "'Turbo — szybko, ale zużywa hub'"],

 // === filter cond ===
    ["'Obniona (5069%)'",              "'Obniżona (50–69%)'"],
    ["'Za (3049%)'",                   "'Zła (30–49%)'"],

 // === pagination ===
    ["=> 'Nastpna '",                  "=> 'Następna »'"],

 // === detail ===
    ["'Szczegy: :name (ID#:id)'",      "'Szczegóły: :name (ID#:id)'"],
    ["'Brak przypisanych odwiertw.'",  "'Brak przypisanych odwiertów.'"],
    ["' Zamknij podgld'",              "'« Zamknij podgląd'"],

 // === buttons ===
    ["=> 'Podgld'",                    "=> 'Podgląd'"],
    ["=> 'Wznw'",                      "=> 'Wznów'"],
    ["'Zmie nazw'",                    "'Zmień nazwę'"],

 // === ok messages ===
    ["'Status huba #:id  :status.'",   "'Status huba #:id: :status.'"],
    ["'Tryb huba #:id  :mode.'",        "'Tryb huba #:id: :mode.'"],
    ["=> 'Bd operacji.'",              "=> 'Błąd operacji.'"],
    ["'Bd CSRF  odwie stron.'",        "'Błąd CSRF — odśwież stronę.'"],

 // === cfg ===
    ["' Konfiguracja systemu hubw'",   "'Konfiguracja systemu hubów'"],
    ["'Wartoci s zapisywane w logistics_hub_config i od razu aktywne w nastpnym ticku. Nie wymagaj zmian w kodzie.'",
     "'Wartości są zapisywane w logistics_hub_config i od razu aktywne w następnym ticku. Nie wymagają zmian w kodzie.'"],
    ["'Bd zapisu konfiguracji.'",      "'Błąd zapisu konfiguracji.'"],
    ["'Typy hubw'",                    "'Typy hubów'"],
    ["=> 'May'",                        "=> 'Mały'"],
    ["=> 'redni'",                     "=> 'Średni'"],
    ["=> 'Duy'",                        "=> 'Duży'"],
    ["=> 'Przepustowo'",               "=> 'Przepustowość'"],
    ["'Zuycie/tick'",                  "'Zużycie/tick'"],
    ["'Mnonik wear overload'",          "'Mnożnik wear overload'"],
    ["'Mnonik risk overload'",          "'Mnożnik risk overload'"],
    ["'Mnonik przepustowoci'",          "'Mnożnik przepustowości'"],
    ["'1.0 = bez zmiany'",              "'1.0 = bez zmiany'"],   // OK, zachowaj
    ["'Mnonik zuycia'",                "'Mnożnik zużycia'"],
    ["'Mnonik OPEX'",                   "'Mnożnik OPEX'"],
    ["'Mnonik ryzyka'",                 "'Mnożnik ryzyka'"],
    ["'Modyfikator wydajnoci'",         "'Modyfikator wydajności'"],
    ["'dodawany do efficiency_pct'",    "'dodawany do efficiency_pct'"],  // OK
    ["'Parametry stosowane gdy odwiert gracza nie ma przypisanego huba. Fallback ogranicza przepustowo i podnosi OPEX  motywacja do uywania systemw hubw.'",
     "'Parametry stosowane gdy odwiert gracza nie ma przypisanego huba. Fallback ogranicza przepustowość i podnosi OPEX — motywacja do używania systemów hubów.'"],
    ["'Maks. przepustowo fallback'",    "'Maks. przepustowość fallback'"],
    ["'Mnonik OPEX fallback'",          "'Mnożnik OPEX fallback'"],
    ["'Mnonik ryzyka fallback'",        "'Mnożnik ryzyka fallback'"],
    ["'Wydajno fallback'",             "'Wydajność fallback'"],
    ["'bazowa wydajno bez huba'",      "'bazowa wydajność bez huba'"],

 // === logistics_hub.* ===
    ["'Bd operacji. Odwie stron.'",    "'Błąd operacji. Odśwież stronę.'"],
    ["=> 'adowanie'",                  "=> 'Ładowanie'"],
    ["'Brak dostpnych hubw.'",         "'Brak dostępnych hubów.'"],
    ["'Brak innych dostpnych hubw do transferu.'",
     "'Brak innych dostępnych hubów do transferu.'"],
    ["':free/:total slotw'",           "':free/:total slotów'"],
    ["=> 'Przenie'",                   "=> 'Przenieś'"],
    ["=> 'Uywany'",                    "=> 'Używany'"],

 // === logistics.hub.acq ===
    ["'Zuycie'",                       "'Zużycie'"],

 // === marine.* ===
    ["'Ropa transportowana tankowcami  pojawi si w magazynie dopiero po rozadunku w porcie.'",
     "'Ropa transportowana tankowcami — pojawi się w magazynie dopiero po rozładunku w porcie.'"],
    ["=> 'Objto'",                     "=> 'Objętość'"],
    ["=> 'Rozadowywana'",              "=> 'Rozładowywana'"],
    ["=> 'Opniona'",                   "=> 'Opóźniona'"],
    ["'Opnienie: :n tick(i)'",         "'Opóźnienie: :n tick(i)'"],

 // === bank.action_err.* ===
    ["'Bd bezpieczestwa - odwie stron i sprbuj ponownie.'",
     "'Błąd bezpieczeństwa — odśwież stronę i spróbuj ponownie.'"],
    ["'Serwis bankowy jest niedostpny.'",
     "'Serwis bankowy jest niedostępny.'"],
    ["'Serwis bankowy jest niedostpny. Sprbuj ponownie za chwil.'",
     "'Serwis bankowy jest niedostępny. Spróbuj ponownie za chwilę.'"],
    ["'Bd podczas skadania wniosku. Sprbuj ponownie.'",
     "'Błąd podczas składania wniosku. Spróbuj ponownie.'"],
    ["'Bd podczas odrzucania oferty.'",
     "'Błąd podczas odrzucania oferty.'"],
    ["'Bd podczas akceptacji oferty. Sprbuj ponownie.'",
     "'Błąd podczas akceptacji oferty. Spróbuj ponownie.'"],
    ["'Bd podczas spaty kredytu.'",    "'Błąd podczas spłaty kredytu.'"],
    ["'System negocjacji jest niedostpny.'",
     "'System negocjacji jest niedostępny.'"],
    ["'Bd podczas skadania wniosku o odroczenie.'",
     "'Błąd podczas składania wniosku o odroczenie.'"],
    ["'Bd podczas skadania wniosku o restrukturyzacj.'",
     "'Błąd podczas składania wniosku o restrukturyzację.'"],
    ["'Bd podczas skadania planu naprawczego.'",
     "'Błąd podczas składania planu naprawczego.'"],
    ["'Bd podczas akceptacji warunkw negocjacji.'",
     "'Błąd podczas akceptacji warunków negocjacji.'"],

 // === black_market.api.* ===
    ["=> 'Bd CSRF'",                   "=> 'Błąd CSRF'"],
    ["'Zbyt wiele akcji! Poczekaj chwil.'",
     "'Zbyt wiele akcji! Poczekaj chwilę.'"],

 // === board_access ===
    ["'Aby odblokowa ten dzia, zatrudnij najpierw :label w Sali Zarzdu.'",
     "'Aby odblokować ten dział, zatrudnij najpierw :label w Sali Zarządu.'"],

 // === auth.email_verify ===
    ["'<p>Dzikujemy za rejestracj w OilCorp! Kliknij przycisk poniej, aby potwierdzi swj adres e-mail i aktywowa konto. Link jest wany przez <strong>24 godziny</strong>.</p>'",
     "'<p>Dziękujemy za rejestrację w OilCorp! Kliknij przycisk poniżej, aby potwierdzić swój adres e-mail i aktywować konto. Link jest ważny przez <strong>24 godziny</strong>.</p>'"],
    ["'Jeli nie zakadae konta w OilCorp, zignoruj t wiadomo. Konto zostanie automatycznie usunite.'",
     "'Jeśli nie zakładałeś konta w OilCorp, zignoruj tę wiadomość. Konto zostanie automatycznie usunięte.'"],

 // === auth.reset_email ===
    ["'Reset hasa - Oil Empire'",       "'Reset hasła — Oil Empire'"],
    ["'Otrzymalimy prob o reset hasa do Twojego konta. Link jest wany przez 1 godzin.'",
     "'Otrzymaliśmy prośbę o reset hasła do Twojego konta. Link jest ważny przez 1 godzinę.'"],
    ["=> 'Resetuj haso'",               "=> 'Resetuj hasło'"],
    ["'Jeli nie prosie o reset hasa, zignoruj t wiadomo.'",
     "'Jeśli nie prosiłeś o reset hasła, zignoruj tę wiadomość.'"],

 // === bailiff ===
    ["'[BANKRUCTWO] Firma utracia pynno. Wejd do restrukturyzacji i wybierz plan ratunkowy.'",
     "'[BANKRUCTWO] Firma utraciła płynność. Wejdź do restrukturyzacji i wybierz plan ratunkowy.'"],
    ["'Panie Dyrektorze, firma utracia pynno finansow. W cigu 24h podejmij decyzje ratunkowe.'",
     "'Panie Dyrektorze, firma utraciła płynność finansową. W ciągu 24h podejmij decyzje ratunkowe.'"],

 // === news ===
    ["'przed chwil'",                  "'przed chwilą'"],
    ["'1 dzie temu'",                  "'1 dzień temu'"],

 // === admin.logistics.hub_initial_condition ===
    ["'Stan pocztkowy'",               "'Stan początkowy'"],
    ["'Modele pozyskania hubw'",        "'Modele pozyskania hubów'"],

 // === hr_hiring ===
    ["'Kandydat nie istnieje lub oferta wygasa.'",
     "'Kandydat nie istnieje lub oferta wygasła.'"],
    ["'Stanowisko kierownika tego dziau jest ju obsadzone.'",
     "'Stanowisko kierownika tego działu jest już obsadzone.'"],
    ["'Bd podczas zatrudniania. Sprbuj ponownie.'",
     "'Błąd podczas zatrudniania. Spróbuj ponownie.'"],
    ["'Brak rodkw na zatrudnienie. Wymagane: {required}, dostpne: {available}.'",
     "'Brak środków na zatrudnienie. Wymagane: {required}, dostępne: {available}.'"],

 // === hr_headhunter ===
    ["'Headhunter jest ju w trakcie poszukiwa.'",
     "'Headhunter jest już w trakcie poszukiwań.'"],
    ["'Brak rodkw. Koszt: {cost}'",    "'Brak środków. Koszt: {cost}'"],
    ["'Headhunter rozpocz poszukiwania: {spec}. Koszt: {cost}. Wyniki za ~{mins} min.'",
     "'Headhunter rozpoczął poszukiwania: {spec}. Koszt: {cost}. Wyniki za ~{mins} min.'"],
    ["'Wystpi bd podczas uruchamiania poszukiwa.'",
     "'Wystąpił błąd podczas uruchamiania poszukiwań.'"],
    ["'Panie Dyrektorze, headhunter znalaz {count} kandydatw ({spec}). Prosz o decyzj.'",
     "'Panie Dyrektorze, headhunter znalazł {count} kandydatów ({spec}). Proszę o decyzję.'"],
    ["'Panie Dyrektorze, nie znaleziono odpowiedniego specjalisty ({spec}). Mona sprbowa ponownie.'",
     "'Panie Dyrektorze, nie znaleziono odpowiedniego specjalisty ({spec}). Można spróbować ponownie.'"],
    ["'Kandydat niedostpny lub oferta wygasa.'",
     "'Kandydat niedostępny lub oferta wygasła.'"],
    ["'Brak rodkw na bonus podpisowy. Wymagane: {cost}'",
     "'Brak środków na bonus podpisowy. Wymagane: {cost}'"],
    ["'{first} {last} odrzuci ofert.'", "'{first} {last} odrzucił ofertę.'"],
    ["'Wystpi bd podczas skadania oferty.'",
     "'Wystąpił błąd podczas składania oferty.'"],
    ["'Bd zatrudnienia: {error}'",     "'Błąd zatrudnienia: {error}'"],
    ["'{first} {last} zaakceptowa ofert! Bonus: {bonus}.'",
     "'{first} {last} zaakceptował ofertę! Bonus: {bonus}.'"],
    ["'Wystpi bd podczas zatrudniania.'",
     "'Wystąpił błąd podczas zatrudniania.'"],

 // === email_template ===
    ["'Jeli przycisk nie dziaa, wklej ten link w przegldarce:'",
     "'Jeśli przycisk nie działa, wklej ten link w przeglądarce:'"],

 // === game_shell ===
    ["'bbl - {pct}% pojemnoci'",        "'bbl — {pct}% pojemności'"],

 // === candidate.certificate ===
    ["'Certyfikat bezpieczestwa wierce'",
     "'Certyfikat bezpieczeństwa wierceń'"],
    ["'Certyfikat specjalisty sprztu'", "'Certyfikat specjalisty sprzętu'"],
    ["'Certyfikat zarzdzania BHP'",     "'Certyfikat zarządzania BHP'"],
    ["'Certyfikat zarzdzania projektami (PMP)'",
     "'Certyfikat zarządzania projektami (PMP)'"],

 // === trend_alert ===
    ["'Wplyw na Twoje przychody:'",     "'Wpływ na Twoje przychody:'"],
    ["=> 'Pozostalo'",                  "=> 'Pozostało'"],
    ["'przez najblizsze'",              "'przez najbliższe'"],

 // === urgent_offer ===
    ["=> 'Osiagnieto!'",               "=> 'Osiągnięto!'"],
    ["'Zarzadzaj ofertami'",            "'Zarządzaj ofertami'"],

 // === logistics.pipeline ===
    ["=> 'Rurocigi'",                   "=> 'Rurociągi'"],
    ["'Stan, wydajno i koszty rurocigw transportowych Twojej firmy.'",
     "'Stan, wydajność i koszty rurociągów transportowych Twojej firmy.'"],
    ["'Brak aktywnych rurocigw. Odwierty rurocigowe buduj rurocig automatycznie przy pierwszym ticku.'",
     "'Brak aktywnych rurociągów. Odwierty rurociągowe budują rurociąg automatycznie przy pierwszym ticku.'"],
    ["=> 'cznie'",                     "=> 'Łącznie'"],
    ["=> 'Czciowy'",                   "=> 'Częściowy'"],
    ["'Peny nadzr'",                   "'Pełny nadzór'"],
    ["':count rurocigw, :units jednostek pod nadzorem. Redukcja awarii: :failure_pct%, katastrof: :cat_pct%.'",
     "':count rurociągów, :units jednostek pod nadzorem. Redukcja awarii: :failure_pct%, katastrof: :cat_pct%.'"],
    ["'Zarzdzaj BHP'",                 "'Zarządzaj BHP'"],
    ["':engineers inynierw  :overdue zalegych przegldw  :critical krytycznych'",
     "':engineers inżynierów — :overdue zaległych przeglądów — :critical krytycznych'"],
    ["'Zarzdzaj technikami'",           "'Zarządzaj technikami'"],
    ["'Rurocig #:id'",                  "'Rurociąg #:id'"],
    ["=> 'Ciki'",                      "=> 'Ciężki'"],
    ["=> 'Przepyw'",                   "=> 'Przepływ'"],
    ["=> 'Przepustowo'",               "=> 'Przepustowość'"],
    ["'Przegld zalegy'",               "'Przegląd zaległy'"],
    ["'Nadzr BHP niekompletny'",        "'Nadzór BHP niekompletny'"],

 // Napraw komentarz naglowka z podwojnym 'k'
    ["// Naglowwek i opis",            "// Naglowek i opis"],
];

// ── Aplikuj podmiany ──────────────────────────────────────────────────────────
$changed = 0;
foreach ($fixes as [$old, $new]) {
    if ($old === $new) continue;
    $count  = substr_count($content, $old);
    if ($count > 0) {
        $content = str_replace($old, $new, $content);
        $changed += $count;
        echo sprintf("  [FIX] x%-2d  %s\n", $count, mb_substr($old, 0, 70));
    }
}

echo "\nZmienionych wystapien: $changed\n";

// ── Zapisz UTF-8 bez BOM ──────────────────────────────────────────────────────
file_put_contents($file, $content);
echo "[OK] Zapisano.\n";

// ── Lint PHP ─────────────────────────────────────────────────────────────────
$out = [];
$ret = 0;
exec('"C:\\xampp1\\bin\\php\\php8.2.29\\php.exe" -l ' . escapeshellarg($file) . ' 2>&1', $out, $ret);
echo "\nLint: " . ($ret === 0 ? 'OK' : 'BLAD') . "\n";
foreach ($out as $line) echo "  $line\n";
