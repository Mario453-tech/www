<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DeploymentBaselineTest extends TestCase
{
    public function testUploadUsesLastSuccessfulDeploymentAndFallsBackToFullMirror(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/deploy-ftp.yml');
        self::assertStringContainsString('status=success&per_page=100', $source);
        self::assertStringContainsString('select(.id < ${GITHUB_RUN_ID})', $source);
        self::assertStringContainsString('BEFORE_SHA="$LAST_DEPLOY_SHA"', $source);
        self::assertMatchesRegularExpression('/else\s+echo "Brak potwierdzonego udanego wdrozenia[^\n]+\n\s+MODE=full/', $source);
        self::assertStringContainsString('git diff --name-only --diff-filter=ACMRT "$BEFORE_SHA" "$AFTER_SHA"', $source);
    }
}
