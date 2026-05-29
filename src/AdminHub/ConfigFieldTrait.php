<?php

/**
 * AdminHubConfigFieldTrait - renderowanie pol konfiguracyjnych w sekcji admina | AdminHubConfigFieldTrait - renders config input fields in the admin config section
 *
 * Uzywane przez: admin/logistics_hubs.php | Used by: admin/logistics_hubs.php
 */
trait AdminHubConfigFieldTrait
{
 /**
 * Renderuje pojedyncze pole konfiguracyjne z formularzem zapisu.
 * Renders a single config field with a save form.
 *
 * @param string $group Klucz grupy konfiguracji / Config group key
 * @param string $key Klucz pola konfiguracji / Config field key
 * @param string $label Etykieta wyswietlana / Display label
 * @param string $currentVal Aktualna wartosc / Current value
 * @param string $csrf Token CSRF / CSRF token
 * @param string $unit Optional unit (bph, PLN, x...) / Opcjonalna jednostka (bph, PLN, x...)
 * @param string $step Krok inputa numerycznego / Numeric input step
 * @param string $note Opcjonalny opis pola / Optional field note
 */
    public function renderCfgField(
        string $group,
        string $key,
        string $label,
        string $currentVal,
        string $csrf,
        string $unit  = '',
        string $step  = '1',
        string $note  = ''
    ): void {
        $val   = htmlspecialchars($currentVal);
        $gEsc  = htmlspecialchars($group);
        $kEsc  = htmlspecialchars($key);
        $lEsc  = htmlspecialchars($label);
        $csrfE = htmlspecialchars($csrf);

        echo '<div class="cfg-field">';
        echo '<div class="cfg-label">' . $lEsc;
        if ($unit !== '') {
            echo ' <small class="c-muted">(' . htmlspecialchars($unit) . ')</small>';
        }
        echo '</div>';
        if ($note !== '') {
            echo '<div class="cfg-note">' . htmlspecialchars($note) . '</div>';
        }
        echo '<form method="POST" class="cfg-form">';
        echo '<input type="hidden" name="action"       value="save_config">';
        echo '<input type="hidden" name="csrf_token"   value="' . $csrfE . '">';
        echo '<input type="hidden" name="config_group" value="' . $gEsc . '">';
        echo '<input type="hidden" name="config_key"   value="' . $kEsc . '">';
        echo '<input type="number" name="config_value" value="' . $val  . '" '
           . 'step="' . htmlspecialchars($step) . '" class="admin-input cfg-input">';
        echo '<button type="submit" class="btn btn-xs btn-secondary">' . t('admin.logistics.cfg_save') . '</button>';
        echo '</form>';
        echo '</div>';
    }
}
