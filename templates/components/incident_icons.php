<?php
declare(strict_types=1);

// Render trusted project SVG icons with one cached file read per icon.
// Renderuj zaufane ikony SVG projektu z jednym odczytem pliku na ikone.
if (!function_exists('incidentIconSvg')) {
    function incidentIconSvg(string $name, string $class = ''): string
    {
        static $cache = [];
        $paths = [
            'rig' => 'incidents/rig.svg',
            'truck' => 'nav/logistics.svg',
            'gear' => 'wg/gear.svg',
            'person' => 'incidents/person.svg',
            'clock' => 'incidents/clock.svg',
            'chevron-down' => 'wg/chevron-down.svg',
            'chevron-up' => 'wg/chevron-up.svg',
        ];
        if (!isset($paths[$name])) {
            return '';
        }
        if (!array_key_exists($name, $cache)) {
            $path = dirname(__DIR__, 2) . '/assets/img/icons/' . $paths[$name];
            $cache[$name] = is_file($path) ? (string)file_get_contents($path) : '';
        }
        $className = htmlspecialchars(trim('incident-svg ' . $class), ENT_QUOTES, 'UTF-8');
        return (string)preg_replace('/<svg\b/', '<svg class="' . $className . '"', $cache[$name], 1);
    }
}
