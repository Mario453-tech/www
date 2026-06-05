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

    'legal.section_active'         => 'Aktywne zezwolenia',
    'legal.section_in_progress'    => 'Wnioski w toku',
    'legal.section_available'      => 'Dostępne regiony',
    'legal.section_locked'         => 'Regiony zablokowane (cooldown)',
    'legal.section_capital_locked' => 'Regiony wysokiego ryzyka (wymagany kapitał)',
    'legal.section_credibility_locked' => 'Regiony wysokiego ryzyka (wymagana wiarygodność)',
    'legal.section_level_locked'   => 'Regiony wymagające wyższego poziomu działu prawnego',

    'legal.risk_label'        => 'Poziom ryzyka',
    'legal.cost_label'        => 'Opłata za wniosek',
    'legal.review_time_label' => 'Szacowany czas rozpatrzenia',
    'legal.decided_at'        => 'Decyzja wydana',
    'legal.decision_due'      => 'Decyzja spodziewana do',
    'legal.delay_count'       => 'Opóźnienie nr :n',

    // Brief §15.1 / §8.1: co zezwolenie odblokowuje (widoczne dla gracza).
    // Brief §15.1 / §8.1: what the permit unlocks (shown to the player).
    'legal.unlocks_note'      => 'Zezwolenie odblokowuje zakup i obsługę odwiertów w tym regionie.',
    // Brief §12.2: opis zezwolenia przejściowego dla obecnych graczy.
    // Brief §12.2: transitional permit description for existing players.
    'legal.transitional_note' => 'Ten region został tymczasowo odblokowany, ponieważ Twoja firma prowadziła tu działalność przed wprowadzeniem działu prawnego. Nowe inwestycje mogą wymagać normalnego zezwolenia.',
    // Brief §7.3 / §8.1: blokada kapitałowa regionu wysokiego ryzyka.
    // Brief §7.3 / §8.1: capital lock of a high-risk region.
    'legal.badge_high_risk'        => 'Region wysokiego ryzyka',
    'legal.required_capital_label' => 'Wymagany kapitał',
    'legal.capital_missing'        => 'Brakuje jeszcze :amount PLN',
    'legal.badge_level_locked'     => 'Wymagany dział prawny',
    'legal.badge_credibility_locked' => 'Wymagana wiarygodność',
    'legal.required_legal_level_label' => 'Wymagany poziom działu prawnego',
    'legal.current_legal_level_label'  => 'Twój poziom działu prawnego',
    'legal.required_credibility_label' => 'Wymagana wiarygodność firmy',
    'legal.current_credibility_label'  => 'Twoja wiarygodność firmy',

    'legal.btn_submit'      => 'Złóż wniosek',
    'legal.confirm_submit'  => 'Złożyć wniosek o zezwolenie na wiercenie w regionie ":region"? Opłata: :cost PLN zostanie pobrana natychmiast.',
    'legal.modal_region_label' => 'Region',
    'legal.modal_cost_note'    => 'Opłata zostanie pobrana natychmiast.',
    'legal.no_regions'      => 'Brak dostępnych regionów do zarządzania zezwoleniami. Skontaktuj się z administratorem.',

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
    'legal.err.credibility_locked' => 'Wiarygodność firmy jest zbyt niska, aby ubiegać się o zezwolenie w tym regionie. Wymagane minimum: :min / 100. Aktualnie: :current / 100.',
    'legal.err.legal_level_locked' => 'Ten region wymaga działu prawnego na poziomie :level. Aktualny poziom Twojego działu prawnego: :current.',
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

    // Brief §13: powiadomienie o złożeniu wniosku / Application submitted notification.
    'legal.notif.submitted.title'     => 'Wniosek o zezwolenie złożony',
    'legal.notif.submitted.message'   => 'Złożono wniosek o zezwolenie na wiercenie w regionie :region. Decyzja przewidywana za około :time.',

    // Brief §13 / §12.2: powiadomienie o zezwoleniu przejściowym / Transitional permit notification.
    'legal.notif.transitional.title'   => 'Zezwolenie przejściowe nadane',
    'legal.notif.transitional.message' => 'Nadano zezwolenie przejściowe dla regionu :region. Region został tymczasowo odblokowany, ponieważ Twoja firma prowadziła tu działalność przed wprowadzeniem działu prawnego. Nowe inwestycje mogą wymagać normalnego zezwolenia.',

];
