<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';
require_once dirname(__DIR__, 2) . '/src/Tick/TickRegistry.php';

final class TickAdapterMetadataTest extends BaseTestCase
{
    /** @dataProvider adapterProvider */
    public function testAdapterMetadata(string $key, int $order, TickFailurePolicy $policy): void
    {
        $module = TickRegistry::find($key);

        $this->assertInstanceOf(TickModule::class, $module);
        $this->assertSame($order, $module->order());
        $this->assertSame($policy, $module->failurePolicy());
    }

    /** @return iterable<string,array{string,int,TickFailurePolicy}> */
    public function adapterProvider(): iterable
    {
        yield 'market' => ['market', 10, TickFailurePolicy::STOP];
        yield 'marine purge' => ['marine_purge', 20, TickFailurePolicy::CONTINUE];
        yield 'bank' => ['bank', 30, TickFailurePolicy::STOP];
        yield 'players' => ['players', 40, TickFailurePolicy::STOP];
        yield 'black market' => ['black_market', 50, TickFailurePolicy::CONTINUE];
        yield 'legal' => ['legal', 70, TickFailurePolicy::CONTINUE];
        yield 'training' => ['training', 80, TickFailurePolicy::CONTINUE];
    }
}
