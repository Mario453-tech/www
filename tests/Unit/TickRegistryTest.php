<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';
require_once dirname(__DIR__, 2) . '/src/Tick/TickRegistry.php';

final class TickRegistryTest extends BaseTestCase
{
    protected function tearDown(): void
    {
        TickRegistry::clearCache();
        parent::tearDown();
    }

    public function testDiscoverFindsCredibilityModule(): void
    {
        $modules = TickRegistry::discover();

        $keys = array_map(static fn(TickModule $module): string => $module->key(), $modules);

        $this->assertContains('credibility', $keys);
        $this->assertInstanceOf(CredibilityModule::class, TickRegistry::find('credibility'));
    }

    public function testDiscoverSortsModulesDeterministically(): void
    {
        $modules = TickRegistry::discover();

        $sortKeys = array_map(
            static fn(TickModule $module): array => [$module->order(), $module->key(), get_class($module)],
            $modules
        );
        $expected = $sortKeys;
        sort($expected);

        $this->assertSame($expected, $sortKeys);
    }

    public function testDiscoverKeysReturnsRequestedModulesOnly(): void
    {
        $modules = TickRegistry::discoverKeys(['credibility', 'missing']);

        $this->assertCount(1, $modules);
        $this->assertSame('credibility', $modules[0]->key());
    }
}
