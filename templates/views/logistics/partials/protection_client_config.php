<?php
$logisticsClientConfig['protection'] = [
    'api' => '/public/protection.php',
    'csrf_token' => CSRF::generateToken(),
    'lang' => [
        'confirm_question' => tPlain('protection.confirm_question'),
        'confirm_renew' => tPlain('protection.confirm_renew'),
        'err' => tPlain('protection.err_generic'),
        'err_target_invalid' => tPlain('protection.err_target_invalid'),
    ],
];
