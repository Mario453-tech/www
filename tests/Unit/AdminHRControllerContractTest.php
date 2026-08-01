<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminHRControllerContractTest extends TestCase
{
    public function testTestStrikeIsAtomicAndUnchangedUpdatesAreAccepted(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string)file_get_contents($root . '/admin/hr.php');

        self::assertMatchesRegularExpression(
            '/beginTransaction\\(\\).*forceActiveForTesting.*feature_negotiations.*commit\\(\\)/s',
            $controller
        );
        self::assertStringContainsString('err_spec_not_found', $controller);
        self::assertStringContainsString('err_hr_spec_not_found', $controller);
        self::assertStringNotContainsString('if ($stmt->rowCount() < 1)', $controller);
    }

    public function testCandidateAndStrikeRoundViewsHaveIndependentPagination(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string)file_get_contents($root . '/admin/hr.php');
        $pagination = (string)file_get_contents($root . '/templates/views/admin/hr/_pagination.php');

        self::assertStringContainsString("'candidate_page'", $controller);
        self::assertStringContainsString("'round_page'", $controller);
        self::assertStringContainsString("'candidate_page'", $pagination);
        self::assertStringContainsString("'round_page'", $pagination);
    }
}
