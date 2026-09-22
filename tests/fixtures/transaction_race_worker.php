<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/src/i18n.php';
require_once dirname(__DIR__, 2) . '/src/MarketSaleService.php';
require_once dirname(__DIR__, 2) . '/src/WorldMap.php';
require_once dirname(__DIR__, 2) . '/src/LegalService.php';
require_once __DIR__ . '/TransactionRaceFixtures.php';

GameLog::setEnabled(false);
$payload = json_decode(base64_decode($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$db = Database::getInstance()->getConnection();
TransactionRaceFixtures::assertDedicatedDatabase($db);
$db->exec('SET SESSION innodb_lock_wait_timeout = 10');
new FinancialTransactionService($db);
$offers = new MarketOffer($db);
$legal = new LegalService($db);
$bank = new BankService();
$ensureLoan = new ReflectionMethod(BankService::class, 'ensureLoanColumnsExist');
$ensureLoan->invoke($bank);
new CompanyCredibilityService($db);
WorldMapSchema::ensure($db);
file_put_contents($argv[2], 'ready');
$deadline = microtime(true) + 20;
while (!is_file($argv[3])) {
    if (microtime(true) > $deadline) throw new RuntimeException('Worker barrier timeout.');
    usleep(5000);
}
file_put_contents($argv[2] . '.started', 'started');
$player = (int)$payload['player'];
$id = (int)($payload['id'] ?? 0);
try {
    $result = match ($payload['action']) {
        'sale' => (new MarketSaleService($db))->sellInstant($player, (int)$payload['amount']),
        'cancel' => $offers->cancelOffer($id, $player),
        'edit' => $offers->updateOffer($id, $player, (float)$payload['price']),
        'execute' => (static function () use ($db, $offers, $id, $payload): array {
            $stmt = $db->prepare('SELECT * FROM market_offers WHERE id = ?');
            $stmt->execute([$id]);
            $offer = $stmt->fetch(PDO::FETCH_ASSOC);
            (new ReflectionMethod(MarketOffer::class, 'executeOffer'))->invoke($offers, $offer, (int)$payload['price']);
            return ['success' => true];
        })(),
        'repay' => $bank->repay($id, $player, $payload['mode']),
        'legal' => $legal->submitApplication($player, $id),
        'hub' => $legal->submitHubApplication($player, $id),
        'map' => (new WorldMap($db))->buyWellAtLocation($player, $id),
    };
    echo json_encode(['result' => $result, 'transaction_open' => $db->inTransaction(),
        'session_active' => session_status() === PHP_SESSION_ACTIVE], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR, $e->__toString());
    exit(1);
}
