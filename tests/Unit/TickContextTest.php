<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';
require_once dirname(__DIR__, 2) . '/src/Tick/TickContext.php';

final class TickContextTest extends BaseTestCase
{
    public function testBalanceMultipliersAreClampedAndLoadedOnlyOnce(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE well_config (`key` TEXT PRIMARY KEY, `value` TEXT NOT NULL)');
        $stmt = $db->prepare('INSERT INTO well_config (`key`, `value`) VALUES (?, ?)');
        $stmt->execute(['global_incident_multiplier', '0.01']);
        $stmt->execute(['global_production_mult', '25']);
        $stmt->execute(['global_tax_multiplier', '1.5']);

        $ctx = new TickContext($db, new DateTimeImmutable('2026-07-10 12:00:00'), 'test');
        $ctx->loadBalanceMults();

        $this->assertSame(0.1, $ctx->balanceMults['incident']);
        $this->assertSame(10.0, $ctx->balanceMults['production']);
        $this->assertSame(1.5, $ctx->balanceMults['tax']);
        $this->assertSame(1.0, $ctx->balanceMults['opex']);

        $db->prepare('UPDATE well_config SET `value` = ? WHERE `key` = ?')
            ->execute(['2.0', 'global_tax_multiplier']);
        $ctx->loadBalanceMults();

        $this->assertSame(1.5, $ctx->balanceMults['tax']);
    }
}
