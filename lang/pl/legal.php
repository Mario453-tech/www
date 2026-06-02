<?php
declare(strict_types=1);

/**
 * Dział prawny P1 — zezwolenia na wiercenie (widok gracza, mapa).
 * Legal department P1 — drilling permits (player view, map).
 */

return [

    // --- Bramka zakupu na mapie (etap 2) ---
    'legal.err_no_drilling_permit' => 'Nie możesz kupić odwiertu w tym regionie, ponieważ Twoja firma nie ma aktywnego zezwolenia na wiercenie. Złóż wniosek w dziale prawnym, aby odblokować region :region.',

    // --- Poziomy ryzyka regionu (prosty opis, bez procentów) ---
    'legal.risk.low'      => 'Region o niskim ryzyku',
    'legal.risk.medium'   => 'Region o umiarkowanym ryzyku',
    'legal.risk.high'     => 'Region o wysokim ryzyku',
    'legal.risk.critical' => 'Region o krytycznym ryzyku',

    // --- Statusy sprawy (widziane przez gracza) ---
    'legal.status.none'         => 'Brak zezwolenia',
    'legal.status.pending'      => 'Wniosek w trakcie',
    'legal.status.delayed'      => 'Opóźnienie decyzji',
    'legal.status.no_decision'  => 'Brak decyzji',
    'legal.status.granted'      => 'Zezwolenie aktywne',
    'legal.status.refused'      => 'Wniosek odrzucony',
    'legal.status.transitional' => 'Zezwolenie przejściowe',

];
