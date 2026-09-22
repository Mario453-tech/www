<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/BoardroomUploadService.php';

final class BoardroomUploadServiceTest extends TestCase
{
    private string $root;
    private BoardroomUploadService $service;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/boardroom-test-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/public', 0700, true);
        $this->service = new BoardroomUploadService($this->root . '/private', $this->root . '/public');
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    }

    private function input(array $overrides = []): array
    {
        return array_replace(['bg_name' => 'hr_M', 'upload_id' => 'upload123', 'chunk_index' => '0', 'total_chunks' => '1', 'bg_file_mime' => 'image/png'], $overrides);
    }

    private function image(string $format = 'png'): string
    {
        $image = imagecreatetruecolor(3, 2);
        ob_start();
        $format === 'jpeg' ? imagejpeg($image) : imagepng($image);
        return ob_get_clean();
    }

    private function rejected(string $reason, callable $call): void
    {
        try {
            $call();
            self::fail('Upload should be rejected');
        } catch (DomainException $e) {
            self::assertSame($reason, $e->getMessage());
        }
    }

    public function testJpegIsDecodedAndPublishedAsActualPng(): void
    {
        $result = $this->service->receive('admin1:session1', $this->input(), $this->image('jpeg'));
        self::assertTrue($result['done']);
        $path = $this->root . '/public/boardroom_bg_hr_M.png';
        self::assertSame('image/png', (new finfo(FILEINFO_MIME_TYPE))->file($path));
        self::assertSame([3, 2], array_slice(getimagesize($path), 0, 2));
        self::assertNotFalse(imagecreatefrompng($path));
    }

    public function testFakeMimeAndInvalidDecodedImageAreRejected(): void
    {
        $this->rejected('image', fn() => $this->service->receive('a', $this->input(), '<?php echo "payload";'));
        $png = $this->image();
        $headerOnly = substr($png, 0, 33);
        $this->rejected('image', fn() => $this->service->receive('b', $this->input(), $headerOnly));
        self::assertSame([], glob($this->root . '/public/*'));
    }

    public function testMetadataValidation(): void
    {
        foreach ([
            ['total_chunks' => 33], ['total_chunks' => 0], ['total_chunks' => '2abc'],
            ['chunk_index' => -1], ['chunk_index' => 1], ['chunk_index' => []],
            ['upload_id' => '../bad'], ['upload_id' => ''], ['bg_name' => '../bad'],
        ] as $override) {
            $this->rejected('metadata', fn() => $this->service->receive('a', $this->input($override), 'x'));
        }
        self::assertDirectoryDoesNotExist($this->root . '/private');
    }

    public function testChunkLimitsForRawAndBase64(): void
    {
        $this->rejected('size', fn() => $this->service->receive('a', $this->input(), str_repeat('x', BoardroomUploadService::MAX_CHUNK + 1)));
        $this->rejected('size', fn() => $this->service->receive('b', $this->input(), base64_encode(str_repeat('x', BoardroomUploadService::MAX_CHUNK + 4)), true));
        $this->rejected('image', fn() => $this->service->receive('c', $this->input(), base64_encode(str_repeat('x', BoardroomUploadService::MAX_CHUNK + 1)), true));
    }

    public function testRawTotalOverflowIsRejectedBeforeWritingChunk(): void
    {
        $chunk = str_repeat('a', BoardroomUploadService::MAX_CHUNK);
        for ($i = 0; $i < 20; $i++) {
            $this->service->receive('a', $this->input(['chunk_index' => $i, 'total_chunks' => 21]), $chunk);
        }
        $this->rejected('size', fn() => $this->service->receive('a', $this->input(['chunk_index' => 20, 'total_chunks' => 21]), 'a'));
        self::assertCount(20, glob($this->root . '/private/*/*/*.part'));
    }

    public function testBase64DecodedTotalOverflow(): void
    {
        $chunk = base64_encode(str_repeat('a', 1048575));
        for ($i = 0; $i < 20; $i++) {
            $this->service->receive('a', $this->input(['chunk_index' => $i, 'total_chunks' => 21]), $chunk, true);
        }
        $this->rejected('size', fn() => $this->service->receive('a', $this->input(['chunk_index' => 20, 'total_chunks' => 21]), base64_encode(str_repeat('a', 21)), true));
    }

    public function testOtherOwnerCannotCompleteOrReplaceChunks(): void
    {
        $png = $this->image();
        $first = substr($png, 0, 20);
        $last = substr($png, 20);
        $this->service->receive('admin1:session1', $this->input(['total_chunks' => 2]), $first);
        foreach (['admin2:session1', 'admin1:session2'] as $owner) {
            $this->rejected('missing', fn() => $this->service->receive($owner, $this->input(['chunk_index' => 1, 'total_chunks' => 2]), $last));
        }
        self::assertTrue($this->service->receive('admin1:session1', $this->input(['chunk_index' => 1, 'total_chunks' => 2]), $last)['done']);
    }

    public function testRetriesAreIdempotentAndConflictingRetriesRejected(): void
    {
        $png = $this->image();
        $first = substr($png, 0, 20);
        $input = $this->input(['total_chunks' => 2]);
        self::assertFalse($this->service->receive('a', $input, $first)['done']);
        self::assertFalse($this->service->receive('a', $input, $first)['done']);
        $this->rejected('retry', fn() => $this->service->receive('a', $input, 'different'));
        $this->rejected('metadata', fn() => $this->service->receive('a', $this->input(['total_chunks' => 3]), $first));
        $lastInput = $this->input(['chunk_index' => 1, 'total_chunks' => 2]);
        $last = substr($png, 20);
        self::assertTrue($this->service->receive('a', $lastInput, $last)['done']);
        $path = $this->root . '/public/boardroom_bg_hr_M.png';
        $hash = hash_file('sha256', $path);
        self::assertTrue($this->service->receive('a', $lastInput, $last)['done']);
        self::assertSame($hash, hash_file('sha256', $path));
    }

    public function testBase64UnalignedChunksAreAssembledAndValidated(): void
    {
        $data = base64_encode($this->image());
        self::assertFalse($this->service->receive('a', $this->input(['total_chunks' => 2]), substr($data, 0, 17), true)['done']);
        self::assertTrue($this->service->receive('a', $this->input(['chunk_index' => 1, 'total_chunks' => 2]), substr($data, 17), true)['done']);
        self::assertNotFalse(imagecreatefrompng($this->root . '/public/boardroom_bg_hr_M.png'));
    }

    public function testFailedValidationPreservesExistingPublication(): void
    {
        $path = $this->root . '/public/boardroom_bg_hr_M.png';
        file_put_contents($path, $this->image());
        $before = hash_file('sha256', $path);
        $this->rejected('image', fn() => $this->service->receive('a', $this->input(), 'fake image'));
        self::assertSame($before, hash_file('sha256', $path));
        self::assertSame([], glob($this->root . '/public/.boardroom-*'));
    }

    public function testAtomicRenameFailureCanBeRetried(): void
    {
        $path = $this->root . '/public/boardroom_bg_hr_M.png';
        mkdir($path);
        file_put_contents($path . '/keep', 'unchanged');
        try {
            $this->service->receive('a', $this->input(), $this->image());
            self::fail('Expected atomic publication failure');
        } catch (RuntimeException $e) {
            self::assertSame('Atomic publication failed', $e->getMessage());
        }
        self::assertSame('unchanged', file_get_contents($path . '/keep'));
        self::assertSame([], glob($this->root . '/public/.boardroom-*'));
        unlink($path . '/keep');
        rmdir($path);
        self::assertTrue($this->service->receive('a', $this->input(), $this->image())['done']);
    }

    public function testDimensionsRejectedBeforeDecoding(): void
    {
        $png = $this->image();
        $ihdr = pack('NN', 8193, 2) . substr($png, 24, 5);
        $png = substr($png, 0, 16) . $ihdr . pack('N', crc32('IHDR' . $ihdr)) . substr($png, 33);
        $this->rejected('dimensions', fn() => $this->service->receive('a', $this->input(), $png));
        $ihdr = pack('NN', 5000, 5000) . substr($png, 24, 5);
        $png = substr($png, 0, 16) . $ihdr . pack('N', crc32('IHDR' . $ihdr)) . substr($png, 33);
        $this->rejected('dimensions', fn() => $this->service->receive('b', $this->input(), $png));
    }

    public function testLocalizedErrorsExistInBothLanguages(): void
    {
        foreach (['pl', 'en'] as $locale) {
            $lang = require dirname(__DIR__, 2) . '/lang/' . $locale . '/admin/template_editor.php';
            foreach (['auth', 'csrf', 'metadata', 'size', 'image', 'dimensions', 'missing', 'retry', 'expired', 'server'] as $key) {
                self::assertNotEmpty($lang['admin.template_editor.upload_' . $key]);
            }
        }
    }

    public function testMissingMiddleChunkCanBeRetriedWithoutLosingEarlierChunks(): void
    {
        $png = $this->image();
        $this->service->receive('a', $this->input(['total_chunks' => 3]), substr($png, 0, 10));
        $last = $this->input(['chunk_index' => 2, 'total_chunks' => 3]);
        $this->rejected('missing', fn() => $this->service->receive('a', $last, substr($png, 20)));
        self::assertSame([], glob($this->root . '/private/*/*/assembled'));
        $this->service->receive('a', $this->input(['chunk_index' => 1, 'total_chunks' => 3]), substr($png, 10, 10));
        self::assertTrue($this->service->receive('a', $last, substr($png, 20))['done']);
        self::assertSame([], glob($this->root . '/private/*/*/*.part'));
    }

    public function testWebpIsPublishedAsPng(): void
    {
        $image = imagecreatetruecolor(3, 2);
        ob_start();
        imagewebp($image);
        $data = ob_get_clean();
        self::assertTrue($this->service->receive('a', $this->input(), $data)['done']);
        self::assertSame('image/png', (new finfo(FILEINFO_MIME_TYPE))->file($this->root . '/public/boardroom_bg_hr_M.png'));
    }

    public function testMalformedBase64IsRejected(): void
    {
        foreach (['a===', '****', 'YW Jj', 'abc'] as $i => $data) {
            $this->rejected('image', fn() => $this->service->receive('owner' . $i, $this->input(), $data, true));
        }
        self::assertSame([], glob($this->root . '/public/*'));
    }

    public function testThirtyTwoChunksAreAccepted(): void
    {
        $png = $this->image();
        for ($i = 0; $i < 32; $i++) {
            $result = $this->service->receive('a', $this->input(['chunk_index' => $i, 'total_chunks' => 32]), $i === 31 ? substr($png, 31) : $png[$i]);
            self::assertSame($i === 31, $result['done']);
        }
    }

    public function testBothRoutesAreGuardedBeforeAnyDatabaseBootstrap(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2) . '/admin/template_editor.php');
        $upload = substr($controller, 0, strpos($controller, '$_codexGuardStart'));
        self::assertStringContainsString("=== 'upload_bg_chunk'", $upload);
        self::assertStringContainsString("['ajax_upload']", $upload);
        self::assertStringContainsString('AdminAuth::isLoggedIn()', $upload);
        self::assertStringContainsString('CSRF::validateToken', $upload);
        self::assertStringContainsString('BoardroomUploadService::MAX_CHUNK + 1', $upload);
        self::assertStringNotContainsString("'err' => \$e->getMessage()", $upload);
        self::assertStringNotContainsString('file_put_contents', $upload);
    }
}
