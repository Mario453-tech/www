<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HRApiEndpointTest extends TestCase
{
    public function testUnauthenticatedRequestReturnsJson401(): void
    {
        $root = dirname(__DIR__, 2);
        $script = <<<'PHP'
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_GET['action'] = 'get_panel_data';
register_shutdown_function(static function (): void {
    fwrite(STDERR, 'STATUS=' . http_response_code());
});
require getcwd() . '/src/HRApi.php';
PHP;
        $process = proc_open(
            [PHP_BINARY, '-r', $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root
        );
        self::assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, $stderr);
        self::assertStringContainsString('STATUS=401', $stderr);
        $payload = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($payload['success']);
        self::assertIsString($payload['error']);
        self::assertNotSame('', $payload['error']);
    }
}
