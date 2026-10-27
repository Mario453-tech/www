<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';

final class SabotagePageContractTest extends BaseTestCase
{
    private const ROOT = __DIR__ . '/../..';

    public function testControllerKeepsAuthorizationAndOneTimePostGuard(): void
    {
        $source = file_get_contents(self::ROOT . '/public/sabotage.php');
        foreach (['Auth::requireLogin()', 'BoardAccess::require(', 'CSRF::validateToken(', 'RateLimiter::check(', 'sabotage_operation_token', 'hash_equals(', 'executePlayerSabotage(', "header('Location: '"] as $piece) {
            self::assertStringContainsString($piece, $source);
        }
        self::assertStringContainsString("is_string(\$_POST['csrf_token'] ?? null)", $source);
    }

    public function testControllerServesAuthorizedLiveSuggestionsAndServerHistoryPages(): void
    {
        $source = file_get_contents(self::ROOT . '/public/sabotage.php');
        foreach (['searchPlayerTargets(', 'application/json', 'countRecentPlayerAttempts(', 'getRecentPlayerAttempts(', 'estimatePlayerCost(', 'hpage', 'tpage'] as $piece) {
            self::assertStringContainsString($piece, $source);
        }
    }

    public function testTickRunsRetentionForExpiredPlayerAttempts(): void
    {
        $source = file_get_contents(self::ROOT . '/cron/tick.php');
        self::assertStringContainsString('pruneExpiredPlayerAttempts()', $source);
        self::assertStringContainsString('GameLog::error(', $source);
    }

    public function testSabotageUserFacingTranslationsExistInEveryLocale(): void
    {
        $pl = require self::ROOT . '/lang/pl/sabotage.php';
        foreach (['en', 'de'] as $locale) {
            $other = require self::ROOT . '/lang/' . $locale . '/sabotage.php';
            $missing = array_filter(array_keys(array_diff_key($pl, $other)), static fn(string $key): bool => str_starts_with($key, 'sabotage.'));
            self::assertSame([], array_values($missing), "Missing {$locale} sabotage translations");
        }
    }

    public function testViewProvidesOneRealFormSelectionAndAccessibleConfirmation(): void
    {
        $source = file_get_contents(self::ROOT . '/templates/views/sabotage/main.php');
        foreach (['aria-pressed=', 'role="listbox"', 'aria-live="polite"', 'target_player_id', 'option_id', 'data-sabotage-action', 'data-sabotage-target', 'sabotage-confirm-dialog', 'sabotage-action-form', '$historyPages'] as $piece) {
            self::assertStringContainsString($piece, $source);
        }
        self::assertStringNotContainsString('sabotage-target-card', $source);
    }
}
