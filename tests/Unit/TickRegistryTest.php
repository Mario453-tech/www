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
        $this->assertContains('b2b_contracts', $keys);
        $this->assertInstanceOf(CredibilityModule::class, TickRegistry::find('credibility'));
        $this->assertInstanceOf(B2BContractsModule::class, TickRegistry::find('b2b_contracts'));
    }

    public function testDiscoverUsesExactCurrentOrder(): void
    {
        $modules = TickRegistry::discover();
        $this->assertSame(
            [
                [20, 'marine_purge'],
                [50, 'black_market'],
                [60, 'credibility'],
                [70, 'legal'],
                [80, 'training'],
                [90, 'contracts'],
                [100, 'b2b_contracts'],
            ],
            array_map(static fn(TickModule $module): array => [$module->order(), $module->key()], $modules)
        );
    }

    public function testDiscoverKeysReturnsRequestedModulesOnly(): void
    {
        $modules = TickRegistry::discoverKeys(['credibility', 'b2b_contracts', 'missing']);

        $this->assertCount(2, $modules);
        $this->assertSame(['credibility', 'b2b_contracts'], array_map(static fn(TickModule $module): string => $module->key(), $modules));
    }

    public function testDiscoverReturnsFreshInstances(): void
    {
        $first = TickRegistry::discover();
        $second = TickRegistry::discover();

        $this->assertNotSame($first[0], $second[0]);
        $this->assertSame(get_class($first[0]), get_class($second[0]));
    }

    public function testMalformedModuleFileFailsClosed(): void
    {
        $dir = $this->temporaryModuleDir();
        file_put_contents($dir . '/BrokenModule.php', "<?php\nfinal class DifferentClass {}\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must define class BrokenModule');
        TickRegistry::discover($dir);
    }

    public function testDuplicateOrderFailsClosed(): void
    {
        $dir = $this->temporaryModuleDir();
        $suffix = str_replace('.', '', uniqid('', true));
        $classA = 'FirstModule' . $suffix;
        $classB = 'SecondModule' . $suffix;
        $this->writeModule($dir, $classA, 'first_' . strtolower($suffix), 10);
        $this->writeModule($dir, $classB, 'second_' . strtolower($suffix), 10);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Duplicate tick module order: 10');
        TickRegistry::discover($dir);
    }

    public function testDuplicateKeyFailsClosed(): void
    {
        $dir = $this->temporaryModuleDir();
        $suffix = str_replace('.', '', uniqid('', true));
        $classA = 'KeyFirstModule' . $suffix;
        $classB = 'KeySecondModule' . $suffix;
        $key = 'duplicate_' . strtolower($suffix);
        $this->writeModule($dir, $classA, $key, 10);
        $this->writeModule($dir, $classB, $key, 20);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Duplicate tick module key: {$key}");
        TickRegistry::discover($dir);
    }

    public function testAdditionalClassInModuleFileFailsClosed(): void
    {
        $dir = $this->temporaryModuleDir();
        $suffix = str_replace('.', '', uniqid('', true));
        $className = 'MultiClassModule' . $suffix;
        $code = "<?php\nfinal class {$className} implements TickModule {"
            . "public function key(): string { return 'multi_" . strtolower($suffix) . "'; }"
            . "public function order(): int { return 10; }"
            . "public function failurePolicy(): TickFailurePolicy { return TickFailurePolicy::CONTINUE; }"
            . "public function run(TickContext \$ctx): void {}"
            . "public function stats(): array { return []; }"
            . "}\nfinal class Extra{$suffix} {}\n";
        file_put_contents($dir . DIRECTORY_SEPARATOR . $className . '.php', $code);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must define exactly one matching class');
        TickRegistry::discover($dir);
    }

    private function temporaryModuleDir(): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oil_tick_registry_' . str_replace('.', '', uniqid('', true));
        mkdir($dir, 0700, true);
        return $dir;
    }

    private function writeModule(string $dir, string $className, string $key, int $order): void
    {
        $code = "<?php\nfinal class {$className} implements TickModule {"
            . "public function key(): string { return '{$key}'; }"
            . "public function order(): int { return {$order}; }"
            . "public function failurePolicy(): TickFailurePolicy { return TickFailurePolicy::CONTINUE; }"
            . "public function run(TickContext \$ctx): void {}"
            . "public function stats(): array { return []; }"
            . "}\n";
        file_put_contents($dir . DIRECTORY_SEPARATOR . $className . '.php', $code);
    }
}
