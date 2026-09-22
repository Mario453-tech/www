<?php
declare(strict_types=1);

final class BoardroomUploadService
{
    public const MAX_CHUNK = 1048576;
    public const MAX_TOTAL = 20971520;
    public const MAX_CHUNKS = 32;
    public const MAX_DIMENSION = 8192;
    public const MAX_PIXELS = 16000000;

    public function __construct(private string $temporaryRoot, private string $destinationRoot)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, done: true}|array{ok: true, done: false, chunk: int}
     */
    public function receive(string $owner, array $input, string $data, bool $base64 = false): array
    {
        $name = $input['bg_name'] ?? null;
        $id = $input['upload_id'] ?? null;
        $index = $this->integer($input['chunk_index'] ?? null);
        $count = $this->integer($input['total_chunks'] ?? null);
        if ($owner === '' || !is_string($name) || !preg_match('/\A[a-zA-Z0-9_]{1,128}\z/D', $name)
            || !is_string($id) || !preg_match('/\A[a-zA-Z0-9_-]{1,80}\z/D', $id)
            || $count < 1 || $count > self::MAX_CHUNKS || $index < 0 || $index >= $count) {
            throw new DomainException('metadata');
        }
        $length = strlen($data);
        $limit = $base64 ? 4 * intdiv(self::MAX_CHUNK + 2, 3) : self::MAX_CHUNK;
        if ($length === 0 || $length > $limit) {
            throw new DomainException('size');
        }
        if ($base64 && (!preg_match('/\A[A-Za-z0-9+\/]*={0,2}\z/D', $data)
            || ($index !== $count - 1 && str_contains($data, '='))
            || intdiv($length * 3, 4) - substr_count($data, '=') > self::MAX_CHUNK)) {
            throw new DomainException('image');
        }
        $ownerDirectory = $this->temporaryRoot . '/' . hash('sha256', $owner);
        $this->removeExpired($ownerDirectory);
        $directory = $ownerDirectory . '/' . hash('sha256', $id . ':' . $name . ':' . (int)$base64);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Upload directory unavailable');
        }
        $lock = @fopen($directory . '/lock', 'c+b');
        if (!$lock || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) fclose($lock);
            throw new RuntimeException('Upload lock unavailable');
        }
        try {
            $metadataPath = $directory . '/metadata';
            $metadata = is_file($metadataPath) ? json_decode((string)file_get_contents($metadataPath), true, 16, JSON_THROW_ON_ERROR) : null;
            if ($metadata && ($metadata['count'] !== $count || $metadata['owner'] !== hash('sha256', $owner))) {
                throw new DomainException('metadata');
            }
            if (!$metadata) {
                if ($index !== 0) throw new DomainException('missing');
                $metadata = ['count' => $count, 'owner' => hash('sha256', $owner), 'created' => time(), 'done' => false];
                $this->write($metadataPath, json_encode($metadata, JSON_THROW_ON_ERROR));
            }
            if (time() - $metadata['created'] > 3600) throw new DomainException('expired');
            if ($metadata['done']) {
                if (!hash_equals($metadata['hashes'][$index], hash('sha256', $data))) throw new DomainException('retry');
                return ['ok' => true, 'done' => true];
            }
            $part = $directory . '/' . $index . '.part';
            if (is_file($part)) {
                if (!hash_equals(hash_file('sha256', $part), hash('sha256', $data))) throw new DomainException('retry');
            } else {
                $size = $length;
                for ($i = 0; $i < $count; $i++) {
                    $existing = $directory . '/' . $i . '.part';
                    if (is_file($existing)) $size += filesize($existing);
                }
                $totalLimit = $base64 ? 4 * intdiv(self::MAX_TOTAL + 2, 3) : self::MAX_TOTAL;
                if ($size > $totalLimit) throw new DomainException('size');
                $this->write($part, $data);
            }
            if ($index !== $count - 1) return ['ok' => true, 'done' => false, 'chunk' => $index];
            $assembled = $directory . '/assembled';
            $output = @fopen($assembled, 'wb');
            if (!$output) throw new RuntimeException('Assembly unavailable');
            try {
                $written = 0;
                $carry = '';
                for ($i = 0; $i < $count; $i++) {
                    $path = $directory . '/' . $i . '.part';
                    if (!is_file($path)) throw new DomainException('missing');
                    $source = @fopen($path, 'rb');
                    if (!$source) throw new RuntimeException('Chunk unavailable');
                    try {
                        while (!feof($source)) {
                            $bytes = fread($source, 65536);
                            if ($bytes === false) throw new RuntimeException('Chunk read failed');
                            if ($base64) {
                                $carry .= $bytes;
                                $aligned = intdiv(strlen($carry), 4) * 4;
                                $bytes = base64_decode(substr($carry, 0, $aligned), true);
                                $carry = substr($carry, $aligned);
                                if ($bytes === false) throw new DomainException('image');
                            }
                            $written += strlen($bytes);
                            if ($written > self::MAX_TOTAL) throw new DomainException('size');
                            if (fwrite($output, $bytes) !== strlen($bytes)) throw new RuntimeException('Assembly write failed');
                        }
                    } finally {
                        fclose($source);
                    }
                }
                if ($carry !== '') throw new DomainException('image');
            } finally {
                fclose($output);
            }
            try {
                $this->publish($assembled, $name);
            } finally {
                @unlink($assembled);
            }
            $metadata['done'] = true;
            $metadata['hashes'] = [];
            for ($i = 0; $i < $count; $i++) {
                $metadata['hashes'][] = hash_file('sha256', $directory . '/' . $i . '.part');
            }
            $this->write($metadataPath, json_encode($metadata, JSON_THROW_ON_ERROR));
            for ($i = 0; $i < $count; $i++) @unlink($directory . '/' . $i . '.part');
            return ['ok' => true, 'done' => true];
        } finally {
            if (isset($assembled)) @unlink($assembled);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function removeExpired(string $ownerDirectory): void
    {
        // Only sweep this authenticated owner's inactive uploads; usuwaj tylko stare uploady tego wlasciciela.
        foreach (glob($ownerDirectory . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            if (is_link($directory) || filemtime($directory) >= time() - 3600) continue;
            $lock = @fopen($directory . '/lock', 'c+b');
            if (!$lock) continue;
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                fclose($lock);
                continue;
            }
            foreach (glob($directory . '/*') ?: [] as $file) {
                if (basename($file) !== 'lock' && is_file($file)) @unlink($file);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
            @unlink($directory . '/lock');
            @rmdir($directory);
        }
    }

    private function integer(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/\A(?:0|[1-9][0-9]{0,2})\z/D', (string)$value)) {
            throw new DomainException('metadata');
        }
        return (int)$value;
    }

    private function write(string $path, string $data): void
    {
        $temporary = $path . '.new';
        if (@file_put_contents($temporary, $data) !== strlen($data) || !@rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Upload write failed');
        }
    }

    private function publish(string $source, string $name): void
    {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($source);
        $decoders = ['image/png' => 'imagecreatefrompng', 'image/jpeg' => 'imagecreatefromjpeg', 'image/webp' => 'imagecreatefromwebp'];
        $info = @getimagesize($source);
        if (!isset($decoders[$mime]) || !$info || ($info['mime'] ?? '') !== $mime) throw new DomainException('image');
        if ($info[0] < 1 || $info[1] < 1 || $info[0] > self::MAX_DIMENSION || $info[1] > self::MAX_DIMENSION
            || $info[0] * $info[1] > self::MAX_PIXELS) throw new DomainException('dimensions');
        if (!function_exists($decoders[$mime])) throw new RuntimeException('Image decoder unavailable');
        $image = @$decoders[$mime]($source);
        if (!$image) throw new DomainException('image');
        $temporary = null;
        try {
            imagesavealpha($image, true);
            if (!is_dir($this->destinationRoot)) throw new RuntimeException('Destination unavailable');
            // Stage a validated PNG on the destination filesystem; etapowy zapis PNG na docelowym systemie plikow.
            $temporary = $this->destinationRoot . '/.boardroom-' . bin2hex(random_bytes(16)) . '.png';
            $handle = @fopen($temporary, 'xb');
            if (!$handle) throw new RuntimeException('Publication unavailable');
            try {
                if (!@imagepng($image, $handle) || !fflush($handle)) throw new RuntimeException('PNG encoding failed');
            } finally {
                fclose($handle);
            }
            if (!@rename($temporary, $this->destinationRoot . '/boardroom_bg_' . $name . '.png')) {
                throw new RuntimeException('Atomic publication failed');
            }
        } finally {
            if ($temporary !== null && is_file($temporary)) @unlink($temporary);
            unset($image);
        }
    }
}
