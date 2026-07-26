<?php
/**
 * Test integracyjny silnika szkolen (standalone, na zywej bazie).
 * Training engine integration test (standalone, against the live DB).
 *
 * Uruchomienie / Run:  php tests/test_training_engine.php
 * Wymaga skonfigurowanej bazy (config/database.php) z uruchomiona migracja
 * migrations/training_module.sql. Uzywa wysokich ID (9005xxxxx) i sprzata po sobie.
 *
 * Pokrywa: zapis na kurs, pobranie oplaty, izolacje gracza, egzamin pass/fail,
 * cooldown po oblaniu, limit maksymalnego poziomu, certyfikaty (technik + zarzad),
 * atomowosc oplaty oraz idempotencje egzaminu (ochrona przed podwojnym rozpatrzeniem).
 */
declare(strict_types=1);
chdir(dirname(__DIR__));
require_once 'src/init.php';
require_once 'src/Training/TrainingService.php';

$db = Database::getInstance()->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$P  = 900500001;   // player
$P2 = 900500002;   // other player
$TS = 900500010;   // technical staff
$BM = 900500020;   // board member

$pass = 0; $fail = 0;
function check(string $name, bool $cond, ?string $extra = '') {
    $extra = (string)$extra;
    global $pass, $fail;
    if ($cond) { $pass++; echo "  PASS  $name\n"; }
    else       { $fail++; echo "  FAIL  $name  $extra\n"; }
}

// ---- cleanup ----
function cleanup(PDO $db, array $players, array $staffIds, array $boardIds) {
    foreach ($players as $p) {
        $db->prepare("DELETE FROM staff_trainings WHERE player_id=?")->execute([$p]);
        $db->prepare("DELETE FROM players WHERE id=?")->execute([$p]);
    }
    foreach ($staffIds as $s) {
        $db->prepare("DELETE FROM technical_staff_skills WHERE staff_id=?")->execute([$s]);
        $db->prepare("DELETE FROM employee_certificates WHERE member_id=? AND staff_type='technical'")->execute([$s]);
        $db->prepare("DELETE FROM technical_staff WHERE id=?")->execute([$s]);
    }
    foreach ($boardIds as $b) {
        $db->prepare("DELETE FROM employee_certificates WHERE member_id=? AND staff_type='board'")->execute([$b]);
        $db->prepare("DELETE FROM board_members WHERE id=?")->execute([$b]);
    }
}
cleanup($db, [$P,$P2], [$TS], [$BM]);

// ---- seed ----
$db->prepare("INSERT INTO players (id,username,email,password_hash,cash,bank_balance,created_at,last_tick_at)
    VALUES (?,?,?,?,?,?,NOW(),NOW())")
   ->execute([$P, "tuser$P", "t$P@test.local", 'x', 0.00, 100000.00]);
$db->prepare("INSERT INTO players (id,username,email,password_hash,cash,bank_balance,created_at,last_tick_at)
    VALUES (?,?,?,?,?,?,NOW(),NOW())")
   ->execute([$P2, "tuser$P2", "t$P2@test.local", 'x', 0.00, 100000.00]);

$db->prepare("INSERT INTO technical_staff (id,player_id,manager_id,first_name,last_name,spec_code,spec_name,skill_level,status)
    VALUES (?,?,?,?,?,?,?,?, 'active')")
   ->execute([$TS, $P, 0, 'Jan', 'Technik', 'drilling', 'Wiertnik', 5]);

$db->prepare("INSERT INTO board_members
    (id,player_id,role_id,first_name,last_name,birth_date,nationality,experience_years,
     skill_organization,skill_negotiation,skill_analysis,skill_stress,skill_ethics,
     trait_loyalty,trait_corruption_risk,trait_ambition,salary,status)
    VALUES (?,?,?,?,?,?,?,?, ?,?,?,?,?, ?,?,?, ?, 'active')")
   ->execute([$BM, $P, 1, 'Anna', 'Prezes', '1980-01-01', 'PL', 10,
              5, 3, 5, 5, 5,   8, 2, 10,   20000.00]);

$svc = new TrainingService($db);

// program ids
$drillBasic = (int)$db->query("SELECT id FROM training_programs WHERE code='tech_drilling_basic'")->fetchColumn();
$negoBasic  = (int)$db->query("SELECT id FROM training_programs WHERE code='board_negotiation_basic'")->fetchColumn();
$drillCost  = (float)$db->query("SELECT cost FROM training_programs WHERE id=$drillBasic")->fetchColumn();

echo "\n== 1. Zapis technika na kurs ==\n";
$bankBefore = (float)$db->query("SELECT bank_balance FROM players WHERE id=$P")->fetchColumn();
$r = $svc->enroll($P, 'technical', $TS, $drillBasic);
check('enroll success', !empty($r['success']), json_encode($r));
$bankAfter = (float)$db->query("SELECT bank_balance FROM players WHERE id=$P")->fetchColumn();
check('oplata pobrana', abs(($bankBefore - $bankAfter) - $drillCost) < 0.01, "before=$bankBefore after=$bankAfter cost=$drillCost");
$trId = (int)($r['training_id'] ?? 0);
check('rekord in_progress', (string)$db->query("SELECT status FROM staff_trainings WHERE id=$trId")->fetchColumn() === 'in_progress');

echo "\n== 2. Podwojny zapis zablokowany ==\n";
$r = $svc->enroll($P, 'technical', $TS, $drillBasic);
check('drugi zapis odrzucony', empty($r['success']));

echo "\n== 3. Izolacja gracza: P2 nie zapisze pracownika P ==\n";
$r = $svc->enroll($P2, 'technical', $TS, $drillBasic);
check('obcy gracz odrzucony', empty($r['success']), json_encode($r));

echo "\n== 4. Egzamin: wymuszamy 100% bazy i koniec w przeszlosci -> PASS ==\n";
$db->exec("UPDATE training_programs SET base_pass_rate=100 WHERE id=$drillBasic");
$db->exec("UPDATE staff_trainings SET finishes_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id=$trId");
$n = $svc->processFinishedExams($P);
check('przetworzono 1 egzamin', $n === 1, "n=$n");
$row = $db->query("SELECT * FROM staff_trainings WHERE id=$trId")->fetch();
check('status passed', $row['status'] === 'passed', json_encode($row['status']));
check('skill_before=1 (sub-skill startuje od 1)', (int)$row['skill_before'] === 1, (string)$row['skill_before']);
check('skill_after=2', (int)$row['skill_after'] === 2, (string)$row['skill_after']);
$lvl = (int)$db->query("SELECT skill_level FROM technical_staff_skills WHERE staff_id=$TS AND skill_code='skill_drilling'")->fetchColumn();
check('sub-skill zapisany =2', $lvl === 2, "lvl=$lvl");
$cert = $db->query("SELECT * FROM training_certificates WHERE staff_type='technical' AND staff_id=$TS")->fetch(PDO::FETCH_ASSOC);
check('certyfikat technika wystawiony', $cert !== false);
check('certyfikat: skill_code=skill_drilling', ($cert['skill_code'] ?? '') === 'skill_drilling', json_encode($cert['skill_code'] ?? null));
check('certyfikat: level_after=2', (int)($cert['level_after'] ?? 0) === 2, (string)($cert['level_after'] ?? ''));

echo "\n== 5. Egzamin: wymuszamy 0% szans (min) -> FAIL + cooldown ==\n";
// base 1 -> chance clamps to MIN 5; to force fail reliably use base very low and check both branches statistically
$db->exec("UPDATE training_programs SET base_pass_rate=1 WHERE id=$drillBasic");
$r = $svc->enroll($P, 'technical', $TS, $drillBasic);
check('ponowny zapis OK (po passed)', !empty($r['success']), json_encode($r));
$trId2 = (int)($r['training_id'] ?? 0);
$db->exec("UPDATE staff_trainings SET finishes_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id=$trId2");
// run exam; with 5% pass chance, score>=95 passes. Loop not allowed; just assert it resolves and cooldown set when failed
$svc->processFinishedExams($P);
$row2 = $db->query("SELECT * FROM staff_trainings WHERE id=$trId2")->fetch();
check('egzamin rozstrzygniety', in_array($row2['status'], ['passed','failed'], true), $row2['status']);
if ($row2['status'] === 'failed') {
    check('cooldown ustawiony', $row2['cooldown_until'] !== null);
    check('blokada zapisu w cooldown', empty($svc->enroll($P, 'technical', $TS, $drillBasic)['success']));
} else {
    echo "  (egzamin zdany losowo - pomijam asercje cooldown)\n";
}

echo "\n== 6. Skill maxed: ustawiamy sub-skill=10, zapis odrzucony ==\n";
$db->exec("UPDATE training_programs SET base_pass_rate=85 WHERE id=$drillBasic");
$db->exec("UPDATE staff_trainings SET status='cancelled', cooldown_until=NULL, active_guard=NULL WHERE staff_id=$TS AND status='in_progress'");
$db->exec("UPDATE staff_trainings SET cooldown_until=NULL WHERE staff_id=$TS");
$db->prepare("INSERT INTO technical_staff_skills (staff_id, skill_code, skill_level, updated_at)
    VALUES (?, 'skill_drilling', 10, NOW())
    ON DUPLICATE KEY UPDATE skill_level=10")->execute([$TS]);
$r = $svc->enroll($P, 'technical', $TS, $drillBasic);
check('skill maxed odrzucony', empty($r['success']), json_encode($r));

echo "\n== 7. Zarzad: zapis prezesa na negocjacje + egzamin ==\n";
$negoCost = (float)$db->query("SELECT cost FROM training_programs WHERE id=$negoBasic")->fetchColumn();
$db->exec("UPDATE training_programs SET base_pass_rate=100 WHERE id=$negoBasic");
$r = $svc->enroll($P, 'board', $BM, $negoBasic);
check('enroll board success', !empty($r['success']), json_encode($r));
$trB = (int)($r['training_id'] ?? 0);
$db->exec("UPDATE staff_trainings SET finishes_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id=$trB");
$svc->processFinishedExams($P);
$negoAfter = (int)$db->query("SELECT skill_negotiation FROM board_members WHERE id=$BM")->fetchColumn();
check('skill_negotiation 3->4', $negoAfter === 4, "after=$negoAfter");
$bcert = (int)$db->query("SELECT COUNT(*) FROM training_certificates WHERE staff_type='board' AND staff_id=$BM")->fetchColumn();
check('certyfikat zarzadu wystawiony', $bcert === 1, "bcert=$bcert");

echo "\n== 8. Niewystarczajace srodki (atomowosc: brak wpisu) ==\n";
$db->exec("UPDATE training_programs SET base_pass_rate=85 WHERE id=$drillBasic");
$db->exec("UPDATE technical_staff_skills SET skill_level=1 WHERE staff_id=$TS AND skill_code='skill_drilling'");
// wyczysc caly stan szkolen tego pracownika dla izolacji asercji
$db->exec("UPDATE staff_trainings SET status='cancelled', cooldown_until=NULL, active_guard=NULL WHERE staff_id=$TS AND status='in_progress'");
$inProgBefore = (int)$db->query("SELECT COUNT(*) FROM staff_trainings WHERE staff_id=$TS AND status='in_progress'")->fetchColumn();
$db->exec("UPDATE players SET cash=0, bank_balance=0 WHERE id=$P");
$r = $svc->enroll($P, 'technical', $TS, $drillBasic);
check('brak srodkow odrzucony', empty($r['success']), json_encode($r));
$inProgAfter = (int)$db->query("SELECT COUNT(*) FROM staff_trainings WHERE staff_id=$TS AND status='in_progress'")->fetchColumn();
check('brak nowego wpisu przy nieudanej oplacie', $inProgAfter === $inProgBefore, "before=$inProgBefore after=$inProgAfter");

echo "\n== 9. Idempotencja egzaminu (race): drugie przetworzenie nie podwaja ==\n";
$db->exec("UPDATE training_programs SET base_pass_rate=100 WHERE id=$drillBasic");
$db->exec("UPDATE staff_trainings SET status='cancelled', active_guard=NULL WHERE staff_id=$TS AND status='in_progress'");
$db->prepare("INSERT INTO technical_staff_skills (staff_id, skill_code, skill_level, updated_at)
    VALUES (?, 'skill_drilling', 3, NOW()) ON DUPLICATE KEY UPDATE skill_level=3")->execute([$TS]);
$db->exec("UPDATE players SET bank_balance=100000 WHERE id=$P");
$r = $svc->enroll($P, 'technical', $TS, $drillBasic);
$trIdR = (int)($r['training_id'] ?? 0);
$db->exec("UPDATE staff_trainings SET finishes_at=DATE_SUB(NOW(),INTERVAL 1 HOUR) WHERE id=$trIdR");
$n1 = $svc->processFinishedExams($P);
$n2 = $svc->processFinishedExams($P); // drugie wywolanie - nie powinno nic zrobic
$rowR = $db->query("SELECT status FROM staff_trainings WHERE id=$trIdR")->fetch(PDO::FETCH_ASSOC);
$lvlR = (int)$db->query("SELECT skill_level FROM technical_staff_skills WHERE staff_id=$TS AND skill_code='skill_drilling'")->fetchColumn();
$certCnt = (int)$db->query("SELECT COUNT(*) FROM training_certificates WHERE training_id=$trIdR")->fetchColumn();
check('pierwsze przetworzenie n=1', $n1 === 1, "n1=$n1");
check('drugie przetworzenie n=0 (idempotentne)', $n2 === 0, "n2=$n2");
if (($rowR['status'] ?? '') === 'passed') {
    check('skill +1 tylko raz (3->4)', $lvlR === 4, "lvl=$lvlR");
    check('certyfikat tylko jeden', $certCnt === 1, "certs=$certCnt");
} else {
    check('oblany egzamin nie podnosi skillu', $lvlR === 3, "lvl=$lvlR");
    check('oblany egzamin nie wystawia certyfikatu', $certCnt === 0, "certs=$certCnt");
}

// restore seed base_pass_rate to avoid polluting future runs
$db->exec("UPDATE training_programs SET base_pass_rate=85 WHERE code='tech_drilling_basic'");
$db->exec("UPDATE training_programs SET base_pass_rate=80 WHERE code='board_negotiation_basic'");

echo "\n========================================\n";
echo "WYNIK: $pass passed, $fail failed\n";

cleanup($db, [$P,$P2], [$TS], [$BM]);
echo "cleanup done\n";
exit($fail > 0 ? 1 : 0);
