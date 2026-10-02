<?php
declare(strict_types=1);

/**
 * Bribery module translations (German).
 * Tlumaczenia modulu lapowek (niemiecki).
 */

return [
    'bribery.err_disabled' => 'Dieser Weg ist derzeit verschlossen.',
    'bribery.err_no_funds' => 'Nicht genügend Bargeld für das Schmiergeld (:cost € erforderlich).',
    'bribery.err_generic'  => 'Die Operation konnte nicht durchgeführt werden. Bitte versuche es erneut.',

    'bribery.msg_success'  => 'Die Angelegenheit wurde diskret geregelt. :cost € wurden abgebucht, der Ruf des Unternehmens hat leicht gelitten.',
    'bribery.msg_caught'   => 'Aufgeflogen! Das Schmiergeld (:cost €) ist verloren, der Ruf des Unternehmens hat schwer gelitten und der Fall ist länger blockiert.',

    'bribery.tx_label'     => 'Schmiergeld — :context',

    'bribery.note_success' => 'Schmiergeld (:context) — inoffiziell geregelt.',
    'bribery.note_caught'  => 'Beim Schmiergeldversuch ertappt (:context).',

    'bribery.notif.caught.title'   => 'Bestechungsversuch aufgeflogen',
    'bribery.notif.caught.message' => 'Der Bestechungsversuch in der Sache ":context" wurde aufgedeckt. Der Ruf des Unternehmens hat gelitten.',
];
