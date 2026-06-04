<?php
declare(strict_types=1);

/**
 * Dział prawny P1 — zezwolenia na wiercenie (widok gracza, mapa).
 * Legal department P1 — drilling permits (player view, map).
 */

return [

    // --- Strona gracza (etap 5) ---
    'legal.page_title'   => 'Dział prawny',
    'legal.page_intro'   => 'Tu możesz złożyć wniosek o zezwolenie na wiercenie w wybranym regionie. Zezwolenie jest wymagane przed zakupem odwiertu. Decyzja zapada automatycznie po upływie czasu rozpatrzenia.',

    'legal.section_active'      => 'Aktywne zezwolenia',
    'legal.section_in_progress' => 'Wnioski w toku',
    'legal.section_available'   => 'Dostępne regiony',
    'legal.section_locked'         => 'Regiony zablokowane (cooldown)',
    'legal.section_capital_locked' => 'Regiony wysokiego ryzyka — wymagany kapitał',

    'legal.risk_label'        => 'Poziom ryzyka',
    'legal.cost_label'        => 'Opłata za wniosek',
    'legal.review_time_label' => 'Szacowany czas rozpatrzenia',
    'legal.decided_at'        => 'Decyzja wydana',
    'legal.decision_due'      => 'Decyzja spodziewana do',
    'legal.delay_count'       => 'Opóźnienie nr :n',

    'legal.btn_submit'      => 'Złóż wniosek',
    'legal.confirm_submit'  => 'Złożyć wniosek o zezwolenie na wiercenie w regionie ":region"? Opłata: :cost PLN zostanie pobrana natychmiast.',
    'legal.no_regions'      => 'Brak dostępnych regionów do zarządzania zezwoleniami. Skontaktuj się z administratorem.',

    'legal.badge_high_risk'         => 'Wysokie ryzyko',
    'legal.required_capital_label'  => 'Wymagany kapitał firmy',
    'legal.capital_missing'         => 'Brakuje: :amount PLN',
    'legal.unlocks_note'            => 'Zezwolenie odblokowuje dostęp do odwiertów w tym regionie.',
    'legal.transitional_note'       => 'Twoja firma otrzymała tymczasowe zezwolenie przejściowe — region jest tymczasowo odblokowany. Złóż wniosek o pełne zezwolenie, aby zachować dostęp.',

    // --- Bramka zakupu na mapie (etap 2) ---
    'legal.err_no_drilling_permit' => 'Nie możesz kupić odwiertu w tym regionie, ponieważ Twoja firma nie ma aktywnego zezwolenia na wiercenie. Złóż wniosek w dziale prawnym, aby odblokować region :region.',

    // --- Poziomy ryzyka regionu (prosty opis, bez procentów) ---
    'legal.risk.low'      => 'Region o niskim ryzyku',
    'legal.risk.medium'   => 'Region o umiarkowanym ryzyku',
    'legal.risk.high'     => 'Region o wysokim ryzyku',
    'legal.risk.critical' => 'Region o krytycznym ryzyku',

    // --- Składanie wniosku (etap 3) ---
    'legal.msg.application_submitted' => 'Wniosek o zezwolenie został złożony. Decyzja przewidywana za około :time.',
    'legal.err.unknown_region'     => 'Ten region nie jest obsługiwany przez dział prawny.',
    'legal.err.region_disabled'    => 'Składanie wniosków dla tego regionu jest obecnie wyłączone.',
    'legal.err.already_active'     => 'Masz już aktywne zezwolenie na wiercenie w tym regionie.',
    'legal.err.in_progress'        => 'Wniosek o zezwolenie na ten region jest już rozpatrywany.',
    'legal.err.cooldown'           => 'Możesz złożyć ponowny wniosek za około :time.',
    'legal.err.region_locked'      => 'Twoja firma nie spełnia jeszcze warunków, aby ubiegać się o zezwolenie w tym regionie. Rozwiń firmę i zwiększ kapitał, aby odblokować trudniejsze regiony.',
    'legal.err.insufficient_funds' => 'Brak środków na opłatę za wniosek. Potrzebujesz :cost PLN.',
    'legal.err.unknown_player'     => 'Nie znaleziono firmy gracza.',
    'legal.err.generic'            => 'Nie udało się złożyć wniosku. Spróbuj ponownie.',

    // --- Statusy sprawy (widziane przez gracza) ---
    'legal.status.none'         => 'Brak zezwolenia',
    'legal.status.pending'      => 'Wniosek w trakcie',
    'legal.status.delayed'      => 'Opóźnienie decyzji',
    'legal.status.no_decision'  => 'Brak decyzji',
    'legal.status.granted'      => 'Zezwolenie aktywne',
    'legal.status.refused'      => 'Wniosek odrzucony',
    'legal.status.transitional' => 'Zezwolenie przejściowe',

    // --- Powiadomienia dyrektora (§13) ---
    'legal.notif.submitted.title'     => 'Wniosek o zezwolenie złożony',
    'legal.notif.submitted.message'   => 'Wniosek o zezwolenie na wiercenie w regionie :region został złożony. Decyzja spodziewana za :time.',
    'legal.notif.transitional.title'  => 'Zezwolenie przejściowe nadane',
    'legal.notif.transitional.message' => 'Twoja firma otrzymała tymczasowe zezwolenie na wiercenie w regionie :region. Region jest tymczasowo odblokowany — złóż wniosek o pełne zezwolenie, aby zachować dostęp.',

    // --- Powiadomienia dyrektora (tick — etap 4) ---
    'legal.notif.granted.title'       => 'Zezwolenie na wiercenie przyznane',
    'legal.notif.granted.message'     => 'Twój wniosek o zezwolenie na wiercenie w regionie :region został rozpatrzony pozytywnie.',
    'legal.notif.refused.title'       => 'Wniosek o zezwolenie odrzucony',
    'legal.notif.refused.message'     => 'Urząd odmówił wydania zezwolenia na wiercenie w regionie :region. Sprawdź, kiedy możesz złożyć ponowny wniosek.',
    'legal.notif.delayed.title'       => 'Decyzja o zezwoleniu opóźniona',
    'legal.notif.delayed.message'     => 'Rozpatrzenie wniosku o zezwolenie na wiercenie w regionie :region zostało opóźnione. Wyznaczono nowy termin decyzji.',
    'legal.notif.no_decision.title'   => 'Brak decyzji w sprawie zezwolenia',
    'legal.notif.no_decision.message' => 'Urząd nie wydał decyzji w sprawie zezwolenia na wiercenie w regionie :region. Wniosek pozostaje bez rozpatrzenia.',
    'legal.notif.default.title'       => 'Aktualizacja wniosku o zezwolenie',
    'legal.notif.default.message'     => 'Status Twojego wniosku o zezwolenie na wiercenie w regionie :region został zaktualizowany.',

];
