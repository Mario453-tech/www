<?php
declare(strict_types=1);

final class AdminAlertThresholds
{
    public const RANGES = [
        'alert_loss_pct_warn' => [0.1, 50],
        'alert_loss_pct_critical' => [0.1, 50],
        'alert_price_high' => [1, 999],
        'alert_price_low' => [1, 999],
        'alert_roi_days_min' => [0.1, 30],
        'alert_roi_days_max' => [0.1, 30],
        'alert_condition_critical' => [1, 100],
        'alert_wear_critical' => [1, 100],
        'alert_storage_full_pct' => [1, 100],
    ];

    /**
     * @param array<string, mixed> $input
     * @return array<string, float>
     */
    public static function validate(array $input): array
    {
        $values = [];
        foreach (self::RANGES as $key => [$min, $max]) {
            $raw = $input[$key] ?? null;
            if (!is_scalar($raw) || is_bool($raw) || !is_numeric($raw)) {
                throw new InvalidArgumentException('Invalid threshold: ' . $key);
            }
            $value = (float)$raw;
            if (!is_finite($value) || $value < $min || $value > $max) {
                throw new InvalidArgumentException('Threshold out of range: ' . $key);
            }
            $values[$key] = $value;
        }
        foreach ([['loss_pct_warn', 'loss_pct_critical'], ['price_low', 'price_high'], ['roi_days_min', 'roi_days_max']] as [$low, $high]) {
            if ($values['alert_' . $low] >= $values['alert_' . $high]) {
                throw new InvalidArgumentException('Threshold order is invalid');
            }
        }
        return $values;
    }
}
