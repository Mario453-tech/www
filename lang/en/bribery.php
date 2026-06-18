<?php
declare(strict_types=1);

/**
 * Bribery module translations.
 * Polski - tlumaczenia modulu lapowek.
 */

return [
    'bribery.err_disabled' => 'This route is currently closed.',
    'bribery.err_no_funds' => 'Not enough cash for the bribe (:cost USD required).',
    'bribery.err_generic'  => 'The operation could not be completed. Try again.',

    'bribery.msg_success'  => 'Handled quietly. :cost USD charged, company reputation suffered slightly.',
    'bribery.msg_caught'   => 'Failure. The bribe (:cost USD) is lost, company reputation suffered heavily, and the case has been blocked for longer.',

    'bribery.tx_label'     => 'Bribe - :context',

    'bribery.note_success' => 'Bribe (:context) - matter handled unofficially.',
    'bribery.note_caught'  => 'Caught during a bribe attempt (:context).',

    'bribery.notif.caught.title'   => 'Bribery attempt exposed',
    'bribery.notif.caught.message' => 'The bribery attempt regarding ":context" has been exposed. Company reputation has suffered.',
];
