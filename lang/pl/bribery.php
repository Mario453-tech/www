<?php
declare(strict_types=1);

/**
 * Modul lapowek — uniwersalne komunikaty (wspolne dla wszystkich modulow).
 * Bribery module — universal messages (shared across all modules).
 */

return [
    // Bledy / Errors
    'bribery.err_disabled' => 'Ta droga jest teraz zamknięta.',
    'bribery.err_no_funds' => 'Brakuje gotówki na łapówkę (potrzeba :cost PLN).',
    'bribery.err_generic'  => 'Nie udało się przeprowadzić operacji. Spróbuj ponownie.',

    // Wynik / Outcome (widoczny dla gracza po akcji)
    'bribery.msg_success'  => 'Sprawa załatwiona po cichu. Pobrano :cost PLN, reputacja firmy lekko ucierpiała.',
    'bribery.msg_caught'   => 'Wpadka! Łapówka (:cost PLN) przepadła, reputacja firmy mocno ucierpiała, a sprawa została zablokowana na dłużej.',

    // Opis transakcji w historii / Transaction description in history
    'bribery.tx_label'     => 'Łapówka — :context',

    // Notatki do historii reputacji / Reputation history notes
    'bribery.note_success' => 'Łapówka (:context) — sprawa załatwiona nieoficjalnie.',
    'bribery.note_caught'  => 'Wpadka na łapówce (:context).',

    // Powiadomienie dyrektora o wpadce / Director "caught" notification
    'bribery.notif.caught.title'   => 'Wpadka na łapówce',
    'bribery.notif.caught.message' => 'Próba przekupstwa w sprawie ":context" wyszła na jaw. Reputacja firmy ucierpiała.',
];
