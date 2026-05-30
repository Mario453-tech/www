<?php
/**
 * TEST: System komunikatw dyrektora
 * Generuje przykadowe komunikaty dla testowania
 */

require_once __DIR__ . '/../src/init.php';

// Wymaga zalogowania lub ustaw ID gracza
$playerId = 1; // Zmie na ID testowego gracza

echo "=== TEST SYSTEMU KOMUNIKATW ===\n\n";

try {
    $notificationService = new DirectorNotificationService();
    
    echo "1. Tworzenie przykadowych komunikatw...\n";
    
 // 1. Komunikat bankowy - zbliajcy si termin
    $id1 = $notificationService->create($playerId, 'bank_payment_due', [
        'amount' => '15,000.00',
        'date' => date('Y-m-d', strtotime('+3 days'))
    ], 72);
    echo "    Utworzono: Zbliajcy si termin spaty (ID: {$id1})\n";
    
 // 2. Komunikat HR - nowi kandydaci
    $id2 = $notificationService->create($playerId, 'hr_new_candidates', [
        'count' => 3,
        'role' => 'Dzia Techniczny'
    ], 48);
    echo "    Utworzono: Nowi kandydaci (ID: {$id2})\n";
    
 // 3. Komunikat techniczny - awaria
    $id3 = $notificationService->create($playerId, 'technical_well_failure', [
        'well_id' => 2
    ]);
    echo "    Utworzono: Awaria odwiertu (ID: {$id3})\n";
    
 // 4. Komunikat rynkowy - wzrost cen
    $id4 = $notificationService->create($playerId, 'market_price_surge', [
        'percent' => 45,
        'price' => 145
    ], 24);
    echo "    Utworzono: Wzrost cen ropy (ID: {$id4})\n";
    
 // 5. Komunikat pilny - magazyn peny
    $id5 = $notificationService->create($playerId, 'storage_full', [
        'percent' => 95
    ]);
    echo "    Utworzono: Magazyn peny (ID: {$id5})\n";
    
 // 6. Komunikat prawny - komornik
    $id6 = $notificationService->create($playerId, 'legal_bailiff_started', []);
    echo "    Utworzono: Postpowanie komornicze (ID: {$id6})\n";
    
 // 7. Komunikat krytyczny - groba bankructwa
    $id7 = $notificationService->create($playerId, 'urgent_bankruptcy_risk', [
        'balance' => '-25,000',
        'debt' => '150,000'
    ]);
    echo "    Utworzono: Groba bankructwa (ID: {$id7})\n";
    
 // 8. Komunikat info - nowa funkcja
    $id8 = $notificationService->create($playerId, 'info_new_feature', [
        'feature_name' => 'Negocjacje Bankowe',
        'description' => 'Moesz teraz negocjowa warunki kredytu z bankiem.'
    ], 168);
    echo "    Utworzono: Nowa funkcja (ID: {$id8})\n";
    
    echo "\n2. Sprawdzanie nieprzeczytanych...\n";
    $unread = $notificationService->getUnread($playerId);
    echo "    Nieprzeczytanych komunikatw: " . count($unread) . "\n";
    
    echo "\n3. Lista komunikatw:\n";
    foreach ($unread as $notif) {
        $priority = strtoupper($notif['priority']);
        echo "   [{$priority}] {$notif['icon']} {$notif['title']}\n";
        echo "       {$notif['message']}\n";
    }
    
    echo "\n4. Test oznaczania jako przeczytane...\n";
    $result = $notificationService->markAsRead($id8, $playerId);
    echo "   " . ($result ? '' : '') . " Oznaczono komunikat #{$id8}\n";
    
    $unreadAfter = $notificationService->countUnread($playerId);
    echo "    Pozostao nieprzeczytanych: {$unreadAfter}\n";
    
    echo "\n=== TEST ZAKOCZONY POMYLNIE ===\n";
    echo "\nOtwrz dashboard gry aby zobaczy komunikaty!\n";
    echo "URL: http://localhost/public/index.php\n";
    
} catch (Exception $e) {
    echo " BD: " . $e->getMessage() . "\n";
    echo "Sprawd czy tabele zostay utworzone:\n";
    echo "mysql -u vh15188_oil -p vh15188_oil < sql/director_notifications_system.sql\n";
}
