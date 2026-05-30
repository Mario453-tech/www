<?php

return [
    'host'    => getenv('DB_HOST')    ?: 'localhost',
    'dbname'  => getenv('DB_NAME')    ?: 'gra1',
    'user'    => getenv('DB_USER')    ?: 'root',
    'password'=> getenv('DB_PASS')    ?: '',
    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
];