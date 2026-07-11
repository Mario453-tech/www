<?php
declare(strict_types=1);

/**
 * Admin translations - force_tick.
 * Tlumaczenia admina - force_tick.
 */

return [
    'admin.force_tick.cooldown' => 'Poczekaj chwilę przed kolejnym wymuszonym tickiem (cooldown 5s).',
    'admin.force_tick.busy' => 'Tick jest juz uruchomiony przez cron lub inne wymuszenie. Poczekaj na zakonczenie biezacego przebiegu.',
    'admin.force_tick.lock_failed' => 'Nie udalo sie zalozyc blokady ticka. Przebieg zostal zatrzymany, aby nie uruchomic go rownolegle bez zabezpieczenia.',
    'admin.force_tick.err_failed' => 'Błąd wymuszonego ticku: :msg',
    'admin.force_tick.msg_ok' => 'Wymuszony tick OK — przetworzono :processed graczy, nowa cena rynku: :price$',
    'admin.force_tick.msg_partial' => 'Wymuszony tick wykonany częściowo — przetworzono :processed graczy, cena rynku: :price$. Ostrzeżenie modułu: :msg',
];
