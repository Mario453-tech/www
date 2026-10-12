<?php
declare(strict_types=1);

/** Safe, normalized navigation artwork shared by the player and the editor. */
final class PlayerNavIcon
{
    public const DIRECTORY = __DIR__ . '/../assets/img/icons/game-nav';

    /** @return array<string, string> */
    public static function builtIns(): array
    {
        $result = [];
        foreach (glob(self::DIRECTORY . '/*.svg') ?: [] as $file) {
            $name = basename($file, '.svg');
            $result[$name] = $name;
        }
        ksort($result);
        return $result;
    }

    public static function fallback(string $key): string
    {
        return [
            'home' => 'home', 'help' => 'pomoc', 'chat' => 'czat',
            'operations' => 'mapa', 'business' => 'finanse', 'company' => 'dyrektor',
            'map' => 'mapa', 'technical' => 'technika', 'logistics' => 'logistyka',
            'market' => 'rynek', 'bank' => 'bank', 'finance' => 'finanse',
            'contracts' => 'kontrakty', 'dashboard' => 'dyrektor',
            'boardroom' => 'dyrektor', 'hr' => 'kadry', 'legal' => 'dzial-prawny',
            'sabotage' => 'dashboard',
        ][$key] ?? 'dashboard';
    }

    public static function valid(string $name): bool
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/D', $name)) return false;
        $extension = str_starts_with($name, 'custom-') ? '.png' : '.svg';
        if ($extension === '.svg' && !isset(self::builtIns()[$name])) return false;
        return is_file(self::DIRECTORY . '/' . $name . $extension);
    }

    public static function resolve(string $name, string $urlKey): string
    {
        if (self::valid($name)) return $name;
        $fallback = self::fallback(trim($urlKey, '/'));
        return self::valid($fallback) ? $fallback : 'dashboard';
    }

    public static function url(string $name): string
    {
        if (!self::valid($name)) $name = 'dashboard';
        return '/assets/img/icons/game-nav/' . $name . (str_starts_with($name, 'custom-') ? '.png' : '.svg');
    }

    /** @return array<string, string> */
    public static function choices(): array
    {
        $choices = self::builtIns();
        foreach (glob(self::DIRECTORY . '/custom-*.png') ?: [] as $file) {
            $name = basename($file, '.png');
            if (self::valid($name)) $choices[$name] = $name;
        }
        return $choices;
    }

    /** Accept only raster images, then re-encode to a 40x40 PNG (never serve untrusted SVG). */
    public static function storeImage(string $source, string $mime, string $directory = self::DIRECTORY): string
    {
        if (!extension_loaded('gd') || !is_file($source) || filesize($source) > 1024 * 1024) {
            throw new InvalidArgumentException('image');
        }
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw new InvalidArgumentException('image');
        }
        $info = @getimagesize($source);
        if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] > 2048 || $info[1] > 2048 || $info['mime'] !== $mime) {
            throw new InvalidArgumentException('image');
        }
        $bytes = file_get_contents($source);
        $input = $bytes === false ? false : @imagecreatefromstring($bytes);
        if (!$input) throw new InvalidArgumentException('image');
        try {
            $canvas = imagecreatetruecolor(40, 40);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $clear = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefill($canvas, 0, 0, $clear);
            $scale = min(40 / $info[0], 40 / $info[1]);
            $width = max(1, (int)round($info[0] * $scale));
            $height = max(1, (int)round($info[1] * $scale));
            imagecopyresampled($canvas, $input, (int)floor((40 - $width) / 2), (int)floor((40 - $height) / 2), 0, 0, $width, $height, $info[0], $info[1]);
            if (!is_dir($directory) || !is_writable($directory)) throw new RuntimeException('storage');
            $name = 'custom-' . bin2hex(random_bytes(12));
            if (!imagepng($canvas, $directory . '/' . $name . '.png')) throw new RuntimeException('storage');
            return $name;
        } finally {
            if (isset($canvas)) imagedestroy($canvas);
            imagedestroy($input);
        }
    }
}
