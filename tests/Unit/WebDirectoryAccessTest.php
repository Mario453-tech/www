<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WebDirectoryAccessTest extends TestCase
{
    public function testInternalApiRedirectsBrowserNavigationButNotAjax(): void
    {
        $rules = (string) file_get_contents(dirname(__DIR__, 2) . '/.htaccess');
        self::assertStringContainsString('RewriteCond %{REQUEST_METHOD} ^GET$', $rules);
        self::assertStringContainsString('RewriteCond %{HTTP:Sec-Fetch-Mode} ^navigate$ [OR]', $rules);
        self::assertStringContainsString('RewriteCond %{HTTP:Sec-Fetch-Dest} ^document$ [OR]', $rules);
        self::assertStringContainsString('RewriteCond %{HTTP_ACCEPT} text/html [NC]', $rules);
        self::assertStringContainsString('RewriteRule ^api/internal/ / [R=302,L,NE,QSD]', $rules);
    }

    public function testImplementationDirectoriesRedirectAndSourceHasDefenseInDepth(): void
    {
        $root = dirname(__DIR__, 2);
        $rules = (string)file_get_contents($root . '/.htaccess');
        $sourceRules = (string)file_get_contents($root . '/src/.htaccess');

        self::assertStringContainsString('(src|config|sql|tests|templates|sessions|tools|migrations|backup|backups|logs|svn_repo|vendor|mobile|serwer|lang|cron|', $rules);
        self::assertStringContainsString('RewriteRule ^ / [R=302,L,NE,QSD]', $rules);
        self::assertStringContainsString('Require all denied', $sourceRules);
        self::assertStringNotContainsString('AJAX endpoint allowlist in /src/', $rules);
    }

    public function testRuntimeClientsUseApiAdaptersInsteadOfSourceDirectory(): void
    {
        $root = dirname(__DIR__, 2);
        $paths = array_merge(
            glob($root . '/assets/js/*.js') ?: [],
            glob($root . '/templates/views/*/*.php') ?: [],
            glob($root . '/templates/views/*/partials/*.php') ?: [],
            glob($root . '/templates/components/*.php') ?: []
        );
        foreach ($paths as $path) {
            $source = (string)file_get_contents($path);
            self::assertDoesNotMatchRegularExpression('#["\'`](/?src/)[A-Za-z]+Api\.php#', $source, $path);
        }
        self::assertFileExists($root . '/api/internal/ChatApi.php');
        self::assertFileExists($root . '/api/internal/HRApi.php');
        self::assertFileExists($root . '/api/internal/PipelineApi.php');
    }

    public function testAdminDiagnosticsRedirectBeforeAdminDirectoryPassThrough(): void
    {
        $rules = (string)file_get_contents(dirname(__DIR__, 2) . '/.htaccess');
        $diagnosticRule = strpos($rules, 'RewriteRule ^admin/(AdminLog|db_connection_check|erorr|login_test|market_debug|redirect_diag|tick_test)\\.php$');
        $adminPassThrough = strpos($rules, 'RewriteCond %{REQUEST_URI} ^/admin/ [OR]');

        self::assertNotFalse($diagnosticRule);
        self::assertNotFalse($adminPassThrough);
        self::assertLessThan($adminPassThrough, $diagnosticRule);
    }

    public function testObsoleteDiagnosticsAreRemovedFromRepositoryAndProductionCleanup(): void
    {
        $root = dirname(__DIR__, 2);
        $obsolete = [
            'admin/db_connection_check.php', 'admin/erorr.php', 'admin/login_test.php',
            'admin/market_debug.php', 'admin/redirect_diag.php', 'admin/tick_test.php',
            'public/selfcheck.php', 'serwer/test_sockets.php', 'serwer/testmysql.php',
            'tools/check_dupes.php', 'tools/check_log_cfg.php', 'tools/db_connection_check.php',
            'tools/diag_bytes.php', 'tools/diag_incidents.php', 'tools/diag_upload.php',
            'tools/fix_emoji_test.php',
        ];
        $workflow = (string)file_get_contents($root . '/.github/workflows/deploy-ftp.yml');
        foreach ($obsolete as $path) {
            self::assertFileDoesNotExist($root . '/' . $path, $path);
            self::assertStringContainsString('rm -f ' . $path, $workflow, $path);
        }
        self::assertFileExists($root . '/api/v1/healthcheck.php');
        self::assertFileExists($root . '/tools/check_encoding.php');
    }
}
