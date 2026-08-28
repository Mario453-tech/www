<?php
declare(strict_types=1);

/**
 * Reads game log pages from the end without loading the whole file.
 * Czyta strony logu gry od konca bez ladowania calego pliku.
 */
final class GameLogReader
{
    private const CHUNK_SIZE = 65536;

    /**
     * @return array{lines:list<string>,has_more:bool,size:int}
     */
    public function readPage(string $path, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = max(1, min(1000, $limit));

        if (!is_readable($path)) {
            return ['lines' => [], 'has_more' => false, 'size' => 0];
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return ['lines' => [], 'has_more' => false, 'size' => 0];
        }

        try {
            fseek($handle, 0, SEEK_END);
            $position = ftell($handle);
            if ($position === false || $position === 0) {
                return ['lines' => [], 'has_more' => false, 'size' => 0];
            }

            $size = $position;
            $skip = ($page - 1) * $limit;
            $seen = 0;
            $lines = [];
            $carry = '';

            while ($position > 0 && count($lines) <= $limit) {
                $readSize = min(self::CHUNK_SIZE, $position);
                $position -= $readSize;
                fseek($handle, $position);
                $chunk = fread($handle, $readSize);
                if ($chunk === false) {
                    break;
                }

                $parts = explode("\n", $chunk . $carry);
                $carry = (string)array_shift($parts);

                for ($index = count($parts) - 1; $index >= 0; $index--) {
                    $line = rtrim($parts[$index], "\r");
                    if ($line === '') {
                        continue;
                    }
                    if ($seen++ < $skip) {
                        continue;
                    }
                    $lines[] = $line;
                    if (count($lines) > $limit) {
                        break;
                    }
                }
            }

            if ($position === 0 && count($lines) <= $limit) {
                $line = rtrim($carry, "\r");
                if ($line !== '' && $seen++ >= $skip) {
                    $lines[] = $line;
                }
            }

            return [
                'lines' => array_slice($lines, 0, $limit),
                'has_more' => count($lines) > $limit,
                'size' => $size,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Removes dated lines older than the cutoff using constant memory.
     * Usuwa datowane linie starsze od progu przy stalym zuzyciu pamieci.
     */
    public function pruneOlderThan(string $path, DateTimeImmutable $cutoff): int
    {
        if (!file_exists($path)) {
            return 0;
        }
        if (!is_readable($path) || !is_writable($path)) {
            throw new RuntimeException('Game log is not readable and writable');
        }

        $source = fopen($path, 'rb');
        if ($source === false) {
            throw new RuntimeException('Unable to open game log for retention');
        }
        if (!flock($source, LOCK_EX)) {
            if (is_resource($source)) {
                fclose($source);
            }
            throw new RuntimeException('Unable to lock game log for retention');
        }

        $tempPath = $path . '.prune-' . bin2hex(random_bytes(6)) . '.tmp';
        $target = fopen($tempPath, 'xb');
        if ($target === false) {
            flock($source, LOCK_UN);
            fclose($source);
            throw new RuntimeException('Unable to create temporary game log');
        }

        $removed = 0;
        try {
            while (($line = fgets($source)) !== false) {
                if ($this->isOlderThan($line, $cutoff)) {
                    $removed++;
                    continue;
                }
                if (fwrite($target, $line) === false) {
                    throw new RuntimeException('Unable to write pruned game log');
                }
            }
            fflush($target);
        } finally {
            fclose($target);
            flock($source, LOCK_UN);
            fclose($source);
        }

        if ($removed === 0) {
            unlink($tempPath);
            return 0;
        }

        if (!rename($tempPath, $path)) {
            unlink($tempPath);
            throw new RuntimeException('Unable to replace pruned game log');
        }

        return $removed;
    }

    private function isOlderThan(string $line, DateTimeImmutable $cutoff): bool
    {
        if (!preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $match)) {
            return false;
        }

        $lineDate = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $match[1]);
        return $lineDate !== false && $lineDate < $cutoff;
    }
}
