<?php
declare(strict_types=1);

/**
 * Protection module translations (German).
 * Tlumaczenia modulu ochrony (niemiecki).
 */

return [
    // Bledy / Errors
    'protection.err_not_found'      => 'Diese Schutzoption existiert nicht.',
    'protection.err_wrong_context'  => 'Diese Schutzoption passt nicht zum gewählten Ziel.',
    'protection.err_disabled'       => 'Dieser Schutz ist derzeit nicht verfügbar.',
    'protection.err_req_credibility'=> 'Erforderliche Bonität: mindestens :min/100.',
    'protection.err_req_legal'      => 'Erforderliche Stufe der Rechtsabteilung: mindestens :min/10.',
    'protection.err_already_active' => 'Dieses Ziel verfügt bereits über einen aktiven Schutz (bis :ends).',
    'protection.err_already_active_generic' => 'Dieses Ziel verfügt bereits über einen aktiven Schutz.',
    'protection.err_no_funds'       => 'Nicht genügend Bargeld für den Schutz (:cost € erforderlich).',
    'protection.err_generic'        => 'Schutz konnte nicht aktiviert werden. Bitte versuche es erneut.',

    // Wynik / Outcome
    'protection.msg_activated'      => 'Schutz ":name" aktiviert. :cost € wurden abgebucht.',

    // Opis transakcji w historii / Transaction description in history
    'protection.tx_label'           => 'Schutz — :name',

    // Wpisy historii ochrony / Protection history entries
    'protection.log_activated'      => 'Schutz ":name" erworben.',

    // Powiadomienie dyrektora / Director notification
    'protection.notif.activated.title'   => 'Schutz aktiviert',
    'protection.notif.activated.message' => 'Schutz ":name" ist aktiv bis :ends. :target',

    // Opisy sily efektu dla gracza (bez mnoznikow) / Effect strength text (no multipliers)
    'protection.effect.strong'      => 'Verringert das Risiko von :what erheblich.',
    'protection.effect.medium'      => 'Verringert das Risiko von :what.',
    'protection.effect.light'       => 'Verringert das Risiko von :what leicht.',
    'protection.effect.disclaimer'  => 'Verringert das Risiko, schließt es aber nicht vollständig aus.',

    // Nazwy ryzyk do opisow / Risk names for descriptions
    'protection.risk.theft_risk_mult'    => 'Diebstahl',
    'protection.risk.raid_risk_mult'     => 'Überfällen',
    'protection.risk.sabotage_risk_mult' => 'Sabotage',
    'protection.risk.equipment_damage_risk_mult'  => 'Ausrüstungsschäden',
    'protection.risk.local_leak_risk_mult'        => 'Leckagen',
    'protection.risk.critical_overload_risk_mult' => 'kritischer Überlastung',
    'protection.risk.transfer_failure_risk_mult'  => 'Umschlagausfällen',
    'protection.risk.loading_error_risk_mult'     => 'Ladefehlern',
    'protection.risk.storage_jam_risk_mult'       => 'Lagerengpässen',
    'protection.risk.pipeline_incident_risk_mult' => 'Pipeline-Havarien',

    // Walidacja celu (endpoint) / Target validation (endpoint)
    'protection.err_target_invalid'  => 'Dieses Ziel existiert nicht oder gehört nicht zu deinem Unternehmen.',
    'protection.err_target_not_road' => 'Transportschutz gilt nur für Bohrungen mit Straßentransport per LKW.',
    'protection.target_well'         => 'Bohrung #:id',
    'protection.target_hub'          => 'Hub #:id',
    'protection.target_pipeline'     => 'Pipeline #:id',
    'protection.pipeline_target_leg'   => 'Pipeline #:id (:leg)',
    'protection.pipeline_leg_inbound'  => 'Bohrung → Hub',
    'protection.pipeline_leg_outbound' => 'Hub → Lager',

    // Sekcje w logistyce / Logistics sections
    'protection.section_title_road'     => 'Schutz für Straßentransporte',
    'protection.section_desc_road'      => 'Schutz verringert das Risiko von Diebstahl, Überfällen und Sabotage bei Transporten',
    'protection.section_title_hub'      => 'Schutz für Hubs',
    'protection.section_desc_hub'       => 'Schutz verringert das Risiko von Ausrüstungsschäden, Leckagen und Hub-Überlastung',
    'protection.section_title_pipeline' => 'Schutz für Pipelines',
    'protection.section_desc_pipeline'  => 'Schutz verringert das Risiko von Pipeline-Havarien',
    'protection.col_well'         => 'Bohrung',
    'protection.col_hub'          => 'Hub',
    'protection.col_pipeline'     => 'Pipeline',
    'protection.col_protection'   => 'Schutz',
    'protection.col_until'        => 'Aktiv bis',
    'protection.col_action'       => 'Aktion',
    'protection.status_none'      => 'kein Schutz',
    'protection.btn_add'          => 'Schutz hinzufügen',
    'protection.btn_renew'        => 'Verlängern',
    'protection.btn_buy'          => 'Schutz kaufen',
    'protection.btn_cancel'       => 'Abbrechen',
    'protection.modal_title'      => 'Schutz auswählen',
    'protection.label_cost'       => 'Kosten:',
    'protection.label_duration'   => 'Dauer:',
    'protection.label_payment'    => 'Zahlung: Bar',
    'protection.duration_minutes' => ':min Minuten',
    'protection.locked_credibility' => 'Erfordert Bonität :min/100.',
    'protection.locked_legal'       => 'Erfordert Rechtsabteilung Stufe :min/10.',
    'protection.not_affordable'     => 'Nicht genügend Bargeld.',
    'protection.confirm_question'   => 'Schutz ":name" für :cost € kaufen?',
    'protection.confirm_renew'      => 'Schutz ":name" für :cost € verlängern? Der aktuelle Schutz wird ohne Erstattung ersetzt.',
];
