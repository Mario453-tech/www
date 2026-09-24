<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/AdminPlayerListQuery.php';
require_once dirname(__DIR__, 2) . '/src/CSRF.php';

final class AdminPlayersDateButtonsTest extends TestCase
{
    private function render(array $input, bool $showRegistrationFilter): string
    {
        $listFilters = AdminPlayerListQuery::filters($input);
        $listFilters['registration_filter'] = $showRegistrationFilter ? '1' : '0';
        $viewData = [
            'players' => [],
            'filter' => $listFilters['filter'],
            'listFilters' => $listFilters,
            'showRegistrationFilter' => $showRegistrationFilter,
            'registrationFilterActive' => $listFilters['registered_from'] !== '' || $listFilters['registered_to'] !== '',
            'msg' => '',
            'error' => '',
        ];
        ob_start();
        require dirname(__DIR__, 2) . '/templates/views/admin/players/main.php';
        return (string) ob_get_clean();
    }

    public function testRegistrationButtonExpandsOnlyItsDateRange(): void
    {
        $initial = $this->render(['login_from' => '2026-09-22'], false);
        self::assertSame(1, preg_match('/<a href="([^"]+)"[^>]*aria-expanded="false"/', $initial, $button));
        parse_str((string) parse_url(html_entity_decode($button[1], ENT_QUOTES, 'UTF-8'), PHP_URL_QUERY), $params);
        self::assertSame('', $params['login_from']);
        self::assertSame('', $params['login_to']);
        self::assertSame('1', $params['registration_filter']);
        self::assertStringContainsString('registration_filter=1', $initial);
        self::assertSame(1, substr_count($initial, 'registration_filter=1'));
        self::assertStringNotContainsString('date_mode=login', $initial);
        self::assertStringContainsString('class="players-login-filters"', $initial);
        self::assertStringNotContainsString('class="players-registration-filters"', $initial);
        self::assertStringNotContainsString('type="date" name="registered_from"', $initial);

        $selected = $this->render([
            'filter' => 'active',
            'registered_from' => '2026-09-23',
            'login_to' => '2026-09-24',
        ], true);
        self::assertStringContainsString('class="players-registration-filters"', $selected);
        self::assertStringContainsString('type="date" name="registered_from" value="2026-09-23"', $selected);
        self::assertStringContainsString('type="hidden" name="login_to" value="2026-09-24"', $selected);
        self::assertStringContainsString('type="hidden" name="registered_from" value="2026-09-23"', $selected);
        self::assertStringContainsString('registration_filter=0', $selected);
    }
}
