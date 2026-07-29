<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';

final class TickHrWiringTest extends BaseTestCase
{
    public function testPlayerGetEntrypointsDoNotProcessReadyRecruitments(): void
    {
        $root = dirname(__DIR__, 2);

        $this->assertStringNotContainsString(
            'processReadyRecruitments(',
            (string)file_get_contents($root . '/public/dashboard.php')
        );
        $this->assertStringNotContainsString(
            'processReadyRecruitments(',
            (string)file_get_contents($root . '/public/boardroom.php')
        );
        $this->assertStringNotContainsString(
            'syncReadyRecruitments()',
            (string)file_get_contents($root . '/public/technical.php')
        );
    }

    public function testTickUsesExplicitGlobalRecruitmentEntrypointAndModuleLimit(): void
    {
        $root = dirname(__DIR__, 2);
        $trait = (string)file_get_contents($root . '/src/HR/RecruitmentTrait.php');
        $bankModule = (string)file_get_contents($root . '/src/Tick/Modules/BankModule.php');
        $bankSection = (string)file_get_contents($root . '/src/Tick/BankSection.php');

        $this->assertStringContainsString('processReadyRecruitmentsAll', $trait);
        $this->assertStringContainsString("moduleLimit('bank', 200)", $bankModule);
        $this->assertStringContainsString('processReadyRecruitmentsAll($this->limit)', $bankSection);
        $this->assertStringContainsString('processReadyAll($this->limit)', $bankSection);
        $this->assertStringContainsString('LIMIT {$this->limit}', $bankSection);
    }
}
