<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';
require_once dirname(__DIR__, 2) . '/src/TechnicalTeamService.php';

final class TechnicalTaskCatalogTest extends BaseTestCase
{
    public function testEveryTechnicalTaskHasPositiveCostRange(): void
    {
        $catalog = (new ReflectionClass(TechnicalTeamService::class))->getConstant('TASKS');

        foreach ($catalog as $taskCode => $task) {
            self::assertGreaterThan(0, $task['cost_min'], $taskCode . ' must have a positive minimum cost');
            self::assertGreaterThanOrEqual($task['cost_min'], $task['cost_max'], $taskCode . ' maximum cost must not be below minimum cost');
        }
    }
}
