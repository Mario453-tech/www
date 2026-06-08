<?php

/**
 * Diagnostyka transportu morskiego: odwierty tankowcowe vs porty w regionach.
 * Marine transport diagnostic: tanker wells vs ports per region.
 *
 * Uruchom na serwerze (CLI) / Run on the server (CLI):
 *   php cron/diag_marine_ports.php
 *
 * Pokazuje / Shows:
 *  - kazdy odwiert morski (offshore + tankowiec): region i czy region ma port
 *    each offshore+tanker well: its region and whether the region has a port
 *  - liste regionow, ktore maja odwierty morskie ale NIE maja portu (wymagaja zasiewu)
 *    regions that have marine wells but NO port (need seeding)
 *
 * Tylko odczyt — niczego nie zmienia. / Read-only — changes nothing.
 */

require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/PortService.php';

$db   = Database::getInstance()->getConnection();
$port = new PortService($db);

echo "=== DIAGNOSTYKA TRANSPORTU MORSKIEGO / MARINE TRANSPORT DIAGNOSTIC ===\n\n";

// 1) Porty wg regionu i statusu / Ports per region and status
echo "PORTY W BAZIE / PORTS IN DB:\n";
$ports = $db->query(
    "SELECT p.region_id, COALESCE(r.name, CONCAT('region#', p.region_id)) AS region_name,
            p.id, p.name, p.status
       FROM ports p
       LEFT JOIN world_regions r ON r.id = p.region_id
      ORDER BY p.region_id, p.id"
)->fetchAll(PDO::FETCH_ASSOC);
if (!$ports) {
    echo "  (brak jakichkolwiek portow / no ports at all)\n";
} else {
    foreach ($ports as $p) {
        printf("  region %-3d %-22s port#%-4d %-26s [%s]\n",
            (int)$p['region_id'], $p['region_name'], (int)$p['id'], $p['name'], $p['status']);
    }
}
echo "\n";

// 2) Odwierty morskie (offshore + tankowiec), aktywne (nie sprzedane)
//    Marine wells (offshore + tanker), active (not sold)
echo "ODWIERTY MORSKIE / MARINE WELLS (offshore + tankowiec):\n";
$wells = $db->query(
    "SELECT w.id, w.player_id, w.location_name, w.status, w.region_id,
            COALESCE(r.name, CONCAT('region#', w.region_id)) AS region_name
       FROM wells w
       LEFT JOIN world_regions r ON r.id = w.region_id
      WHERE w.well_type = 'offshore'
        AND w.transport_type = 'tankowiec'
        AND w.status <> 'sold'
      ORDER BY w.player_id, w.region_id, w.id"
)->fetchAll(PDO::FETCH_ASSOC);

if (!$wells) {
    echo "  (brak aktywnych odwiertow morskich / no active marine wells)\n";
}

$regionsNeedingPort = []; // region_id => region_name
$ok = 0; $paused = 0;
foreach ($wells as $w) {
    $regionId = (int)$w['region_id'];
    $hasPort  = $port->hasActivePortForRegion($regionId);
    if ($hasPort) {
        $ok++;
    } else {
        $paused++;
        $regionsNeedingPort[$regionId] = $w['region_name'];
    }
    printf("  well#%-5d gracz#%-4d region %-3d %-22s %-22s => %s\n",
        (int)$w['id'], (int)$w['player_id'], $regionId, $w['region_name'],
        $w['location_name'],
        $hasPort ? 'PORT OK (produkuje)' : '>>> BRAK PORTU (WSTRZYMANY) <<<');
}
echo "\n";

// 3) Podsumowanie / Summary
echo "PODSUMOWANIE / SUMMARY:\n";
printf("  odwiertow morskich razem / total marine wells: %d\n", count($wells));
printf("  produkuje (region ma port) / producing (region has port): %d\n", $ok);
printf("  wstrzymanych (brak portu) / paused (no port): %d\n", $paused);
echo "\n";

if ($regionsNeedingPort) {
    echo "REGIONY WYMAGAJACE ZASIEWU PORTU / REGIONS NEEDING A PORT:\n";
    foreach ($regionsNeedingPort as $rid => $rname) {
        printf("  - region %d (%s)\n", $rid, $rname);
    }
    echo "\n  Zasiej porty w panelu: admin/transport.php -> \"Zasiej domyslne porty\"\n";
    echo "  Seed ports in panel:    admin/transport.php -> \"Seed default ports\"\n";
} else {
    echo "OK: kazdy odwiert morski ma port w swoim regionie.\n";
    echo "OK: every marine well has a port in its region.\n";
}
