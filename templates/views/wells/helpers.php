<?php
// Escaped presentation helpers / Pomocniki prezentacji z escapowaniem.
function wwEscape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function wwNumber($value): string { return number_format((float)$value, 1, getLocale() === 'pl' ? ',' : '.', ' '); }
function wwMoney($value): string { return number_format((float)$value, 0, '.', ' ') . ' ' . t('common.pln'); }
function wwIcon(string $name): string
{
    static $cache = [];
    if (!isset($cache[$name])) {
        $file = __DIR__ . '/../../../assets/img/icons/wells/' . preg_replace('/[^a-z-]/', '', $name) . '.svg';
        $cache[$name] = is_file($file) ? preg_replace('/<svg\b/', '<svg class="ww-icon" aria-hidden="true" focusable="false"', file_get_contents($file), 1) : '';
    }
    return $cache[$name];
}
function wwEffects(array $effects): void
{
    echo '<dl class="ww-effects">';
    foreach (['prod' => 'prod', 'incident' => 'incident', 'wear' => 'wear', 'spiral' => 'spiral'] as $field => $key) {
        $value = round(($effects[$field] - 1) * 100, 1);
        echo '<div><dt>' . t('wg.eq_stat_' . $key) . '</dt><dd>' . ($value > 0 ? '+' : '') . wwNumber($value) . '%</dd></div>';
    }
    echo '</dl>';
}
function wwAction(string $label, string $endpoint, array $payload, string $summary, string $reason = ''): void
{
    echo '<button type="button" class="ww-button" data-well-action data-endpoint="' . wwEscape($endpoint) . '" data-payload="' . wwEscape(json_encode($payload)) . '" data-summary="' . wwEscape($summary) . '"' . ($reason !== '' ? ' disabled' : '') . '>' . $label . '</button>';
    if ($reason !== '') echo '<p class="ww-muted">' . $reason . '</p>';
}
