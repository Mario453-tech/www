<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';
require_once dirname(__DIR__, 2) . '/src/AdminLogs/GameLogReader.php';

final class GameLogReaderTest extends BaseTestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();
        $path = tempnam(sys_get_temp_dir(), 'game-log-');
        self::assertNotFalse($path);
        $this->logPath = $path;
    }

    protected function tearDown(): void
    {
        if (is_file($this->logPath)) {
            unlink($this->logPath);
        }
        parent::tearDown();
    }

    public function testReadsNewestLinesWithoutLoadingWholeFile(): void
    {
        $lines = [];
        for ($index = 1; $index <= 250; $index++) {
            $lines[] = 'line-' . $index;
        }
        file_put_contents($this->logPath, implode("\n", $lines) . "\n");

        $result = (new GameLogReader())->readPage($this->logPath, 1, 100);

        self::assertCount(100, $result['lines']);
        self::assertSame('line-250', $result['lines'][0]);
        self::assertSame('line-151', $result['lines'][99]);
        self::assertTrue($result['has_more']);
    }

    public function testReadsLastPartialPage(): void
    {
        $lines = [];
        for ($index = 1; $index <= 250; $index++) {
            $lines[] = 'line-' . $index;
        }
        file_put_contents($this->logPath, implode("\n", $lines));

        $result = (new GameLogReader())->readPage($this->logPath, 3, 100);

        self::assertCount(50, $result['lines']);
        self::assertSame('line-50', $result['lines'][0]);
        self::assertSame('line-1', $result['lines'][49]);
        self::assertFalse($result['has_more']);
    }

    public function testReadsAcrossInternalChunkBoundary(): void
    {
        $lines = [];
        for ($index = 1; $index <= 100; $index++) {
            $lines[] = 'line-' . $index . '-' . str_repeat('x', 1000);
        }
        file_put_contents($this->logPath, implode("\n", $lines) . "\n");

        $result = (new GameLogReader())->readPage($this->logPath, 2, 40);

        self::assertCount(40, $result['lines']);
        self::assertStringStartsWith('line-60-', $result['lines'][0]);
        self::assertStringStartsWith('line-21-', $result['lines'][39]);
        self::assertTrue($result['has_more']);
    }

    public function testPrunesOldDatedLinesWithoutRemovingUndatedLines(): void
    {
        file_put_contents($this->logPath, implode("\n", [
            '[2026-07-01 10:00:00] old',
            'undated diagnostic line',
            '[2026-08-20 10:00:00] recent',
        ]) . "\n");

        $reader = new GameLogReader();
        $removed = $reader->pruneOlderThan(
            $this->logPath,
            new DateTimeImmutable('2026-08-01 00:00:00')
        );

        self::assertSame(1, $removed);
        self::assertSame(
            "undated diagnostic line\n[2026-08-20 10:00:00] recent\n",
            file_get_contents($this->logPath)
        );
    }
}
