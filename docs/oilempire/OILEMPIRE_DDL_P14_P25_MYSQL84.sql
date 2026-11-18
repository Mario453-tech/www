-- OilEmpire P14-P25: PROJEKT DDL, MySQL 8.4/InnoDB/utf8mb4. Nie uruchamiać na produkcji bez migracji expand/contract.
-- Preflight: P01-P13 posiada companies(id BIGINT UNSIGNED); zweryfikować nazwy i typy przed migracją.
-- Nie tworzyć wtórnych ledger, stock_reservations, oil_movements, obligations ani trips; korzystać z publicznych Actions ich właścicieli.
-- Kwoty: BIGINT grosze, ropa: DECIMAL(16,4), czas: UTC DATETIME(3), public_ulid: losowy ULID.
SET NAMES utf8mb4;

-- Market
CREATE TABLE market_offers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  storage_id BIGINT UNSIGNED NOT NULL,
  side VARCHAR(8) NOT NULL,
  quantity_bbl DECIMAL(16,4) NOT NULL,
  remaining_bbl DECIMAL(16,4) NOT NULL,
  limit_price_minor BIGINT UNSIGNED NOT NULL,
  expires_at DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_market_offers_ulid (public_ulid),
  KEY ix_market_offers_company_state (company_id, state, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE market_price_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  region_code VARCHAR(24) NOT NULL,
  price_minor BIGINT UNSIGNED NOT NULL,
  volume_60m_bbl DECIMAL(16,4) NOT NULL,
  event_multiplier_ppm INT UNSIGNED NOT NULL,
  slot_at DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_market_price_snapshots_ulid (public_ulid),
  UNIQUE KEY uq_market_price_snapshots_domain (region_code, slot_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE market_trades (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  buyer_company_id BIGINT UNSIGNED NULL,
  seller_company_id BIGINT UNSIGNED NULL,
  offer_id BIGINT UNSIGNED NULL,
  quantity_bbl DECIMAL(16,4) NOT NULL,
  unit_price_minor BIGINT UNSIGNED NOT NULL,
  total_minor BIGINT UNSIGNED NOT NULL,
  ledger_reference CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_market_trades_ulid (public_ulid),
  KEY ix_market_trades_company_state (company_id, state, id),
  UNIQUE KEY uq_market_trades_domain (ledger_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE black_market_trades (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  storage_id BIGINT UNSIGNED NOT NULL,
  quantity_bbl DECIMAL(16,4) NOT NULL,
  unit_price_minor BIGINT UNSIGNED NOT NULL,
  risk_seed VARBINARY(32) NOT NULL,
  risk_result TINYINT UNSIGNED NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_black_market_trades_ulid (public_ulid),
  KEY ix_black_market_trades_company_state (company_id, state, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE world_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  region_code VARCHAR(24) NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  multiplier_ppm INT UNSIGNED NOT NULL,
  starts_at DATETIME(3) NOT NULL,
  ends_at DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_world_events_ulid (public_ulid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Contracts
CREATE TABLE system_contract_offers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  region_code VARCHAR(24) NOT NULL,
  product_code VARCHAR(32) NOT NULL,
  quantity_bbl DECIMAL(16,4) NOT NULL,
  unit_price_minor BIGINT UNSIGNED NOT NULL,
  deposit_pct SMALLINT UNSIGNED NOT NULL,
  expires_at DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_system_contract_offers_ulid (public_ulid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE contracts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  buyer_company_id BIGINT UNSIGNED NULL,
  seller_company_id BIGINT UNSIGNED NULL,
  offer_id BIGINT UNSIGNED NULL,
  kind VARCHAR(16) NOT NULL,
  quantity_bbl DECIMAL(16,4) NOT NULL,
  unit_price_minor BIGINT UNSIGNED NOT NULL,
  due_at DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_contracts_ulid (public_ulid),
  KEY ix_contracts_company_state (company_id, state, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE contract_tranches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  contract_id BIGINT UNSIGNED NOT NULL,
  sequence_no INT UNSIGNED NOT NULL,
  quantity_bbl DECIMAL(16,4) NOT NULL,
  due_at DATETIME(3) NOT NULL,
  received_bbl DECIMAL(16,4) NOT NULL DEFAULT 0,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_contract_tranches_ulid (public_ulid),
  KEY ix_contract_tranches_company_state (company_id, state, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE b2b_offers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  seller_company_id BIGINT UNSIGNED NOT NULL,
  buyer_company_id BIGINT UNSIGNED NULL,
  source_storage_id BIGINT UNSIGNED NOT NULL,
  quantity_bbl DECIMAL(16,4) NOT NULL,
  unit_price_minor BIGINT UNSIGNED NOT NULL,
  expires_at DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_b2b_offers_ulid (public_ulid),
  KEY ix_b2b_offers_company_state (company_id, state, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE escrow_accounts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  contract_id BIGINT UNSIGNED NULL,
  b2b_offer_id BIGINT UNSIGNED NULL,
  held_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
  released_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
  refunded_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_escrow_accounts_ulid (public_ulid),
  KEY ix_escrow_accounts_company_state (company_id, state, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE escrow_movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  escrow_account_id BIGINT UNSIGNED NOT NULL,
  movement_key VARCHAR(128) NOT NULL,
  kind VARCHAR(20) NOT NULL,
  amount_minor BIGINT UNSIGNED NOT NULL,
  ledger_reference CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_escrow_movements_ulid (public_ulid),
  KEY ix_escrow_movements_company_state (company_id, state, id),
  UNIQUE KEY uq_escrow_movements_domain (movement_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE delivery_receipts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  contract_tranche_id BIGINT UNSIGNED NULL,
  b2b_offer_id BIGINT UNSIGNED NULL,
  trip_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  quantity_bbl DECIMAL(16,4) NOT NULL,
  received_at DATETIME(3) NOT NULL,
  proof_sha256 BINARY(32) NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_delivery_receipts_ulid (public_ulid),
  KEY ix_delivery_receipts_company_state (company_id, state, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE contract_disputes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  contract_id BIGINT UNSIGNED NOT NULL,
  receipt_id BIGINT UNSIGNED NULL,
  reason_code VARCHAR(40) NOT NULL,
  filed_at DATETIME(3) NOT NULL,
  resolved_at DATETIME(3) NULL,
  resolution_code VARCHAR(40) NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_contract_disputes_ulid (public_ulid),
  KEY ix_contract_disputes_company_state (company_id, state, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Finance
CREATE TABLE department_budgets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  department_code VARCHAR(32) NOT NULL,
  daily_limit_minor BIGINT UNSIGNED NOT NULL,
  effective_at DATETIME(3) NOT NULL,
  cooldown_until DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_department_budgets_ulid (public_ulid),
  KEY ix_department_budgets_company_state (company_id, state, id),
  UNIQUE KEY uq_department_budgets_domain (company_id, department_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE player_finance_decisions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  decision_key VARCHAR(128) NOT NULL,
  department_code VARCHAR(32) NOT NULL,
  old_limit_minor BIGINT UNSIGNED NOT NULL,
  new_limit_minor BIGINT UNSIGNED NOT NULL,
  morale_delta SMALLINT NOT NULL DEFAULT 0,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_player_finance_decisions_ulid (public_ulid),
  KEY ix_player_finance_decisions_company_state (company_id, state, id),
  UNIQUE KEY uq_player_finance_decisions_domain (decision_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE asset_valuations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  asset_type VARCHAR(32) NOT NULL,
  asset_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  gross_minor BIGINT NOT NULL,
  taxable_minor BIGINT NOT NULL,
  formula_version VARCHAR(24) NOT NULL,
  valued_at DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_asset_valuations_ulid (public_ulid),
  KEY ix_asset_valuations_company_state (company_id, state, id),
  UNIQUE KEY uq_asset_valuations_domain (company_id, asset_type, asset_public_ulid, valued_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE net_worth_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  snapshot_date DATE NOT NULL,
  cash_minor BIGINT NOT NULL,
  asset_minor BIGINT NOT NULL,
  liability_minor BIGINT NOT NULL,
  taxable_minor BIGINT NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_net_worth_snapshots_ulid (public_ulid),
  KEY ix_net_worth_snapshots_company_state (company_id, state, id),
  UNIQUE KEY uq_net_worth_snapshots_domain (company_id, snapshot_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE wealth_tax_assessments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  snapshot_id BIGINT UNSIGNED NOT NULL,
  tax_date DATE NOT NULL,
  tax_minor BIGINT UNSIGNED NOT NULL,
  ledger_reference CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wealth_tax_assessments_ulid (public_ulid),
  KEY ix_wealth_tax_assessments_company_state (company_id, state, id),
  UNIQUE KEY uq_wealth_tax_assessments_domain (snapshot_id, tax_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Banking
CREATE TABLE bank_trust_scores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  score SMALLINT UNSIGNED NOT NULL,
  score_version VARCHAR(24) NOT NULL,
  calculated_at DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bank_trust_scores_ulid (public_ulid),
  KEY ix_bank_trust_scores_company_state (company_id, state, id),
  UNIQUE KEY uq_bank_trust_scores_domain (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE credit_score_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  score_id BIGINT UNSIGNED NOT NULL,
  prior_score SMALLINT UNSIGNED NOT NULL,
  new_score SMALLINT UNSIGNED NOT NULL,
  reason_code VARCHAR(40) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_credit_score_history_ulid (public_ulid),
  KEY ix_credit_score_history_company_state (company_id, state, id),
  UNIQUE KEY uq_credit_score_history_domain (score_id, reason_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE loans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  principal_minor BIGINT UNSIGNED NOT NULL,
  outstanding_minor BIGINT UNSIGNED NOT NULL,
  apr_basis_points INT UNSIGNED NOT NULL,
  term_days SMALLINT UNSIGNED NOT NULL,
  accepted_at DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_loans_ulid (public_ulid),
  KEY ix_loans_company_state (company_id, state, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE loan_installments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  loan_id BIGINT UNSIGNED NOT NULL,
  sequence_no INT UNSIGNED NOT NULL,
  due_at DATETIME(3) NOT NULL,
  principal_minor BIGINT UNSIGNED NOT NULL,
  interest_minor BIGINT UNSIGNED NOT NULL,
  paid_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_loan_installments_ulid (public_ulid),
  KEY ix_loan_installments_company_state (company_id, state, id),
  UNIQUE KEY uq_loan_installments_domain (loan_id, sequence_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE bailiff_proceedings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  obligation_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  phase TINYINT UNSIGNED NOT NULL,
  started_at DATETIME(3) NOT NULL,
  next_due_at DATETIME(3) NULL,
  recovered_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bailiff_proceedings_ulid (public_ulid),
  KEY ix_bailiff_proceedings_company_state (company_id, state, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE auctions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  proceeding_id BIGINT UNSIGNED NOT NULL,
  asset_type VARCHAR(32) NOT NULL,
  asset_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  reserve_minor BIGINT UNSIGNED NOT NULL,
  winning_bid_minor BIGINT UNSIGNED NULL,
  closes_at DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_auctions_ulid (public_ulid),
  KEY ix_auctions_company_state (company_id, state, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Legal
CREATE TABLE sabotage_operations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  attacker_company_id BIGINT UNSIGNED NOT NULL,
  victim_company_id BIGINT UNSIGNED NOT NULL,
  target_type VARCHAR(24) NOT NULL,
  target_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  cost_minor BIGINT UNSIGNED NOT NULL,
  resolve_at DATETIME(3) NOT NULL,
  rng_seed VARBINARY(32) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sabotage_operations_ulid (public_ulid),
  KEY ix_sabotage_operations_company_state (company_id, state, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE victim_attack_windows (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  victim_company_id BIGINT UNSIGNED NOT NULL,
  window_started_at DATETIME(3) NOT NULL,
  successful_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_victim_attack_windows_ulid (public_ulid),
  KEY ix_victim_attack_windows_company_state (company_id, state, id),
  UNIQUE KEY uq_victim_attack_windows_domain (victim_company_id, window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE protection_policies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  target_type VARCHAR(24) NOT NULL,
  target_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tier_code VARCHAR(20) NOT NULL,
  premium_minor BIGINT UNSIGNED NOT NULL,
  starts_at DATETIME(3) NOT NULL,
  ends_at DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_protection_policies_ulid (public_ulid),
  KEY ix_protection_policies_company_state (company_id, state, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE protection_activations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  policy_id BIGINT UNSIGNED NOT NULL,
  attack_id BIGINT UNSIGNED NOT NULL,
  prevented TINYINT(1) NOT NULL,
  resolved_at DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_protection_activations_ulid (public_ulid),
  KEY ix_protection_activations_company_state (company_id, state, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE abuse_reports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  reporter_user_id BIGINT UNSIGNED NOT NULL,
  subject_company_id BIGINT UNSIGNED NOT NULL,
  reason_code VARCHAR(40) NOT NULL,
  evidence_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_abuse_reports_ulid (public_ulid),
  KEY ix_abuse_reports_company_state (company_id, state, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Refining
CREATE TABLE refinery_plots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  region_code VARCHAR(24) NOT NULL,
  plot_code VARCHAR(40) NOT NULL,
  acquired_minor BIGINT UNSIGNED NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_refinery_plots_ulid (public_ulid),
  KEY ix_refinery_plots_company_state (company_id, state, id),
  UNIQUE KEY uq_refinery_plots_domain (region_code, plot_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE epc_contracts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  plot_id BIGINT UNSIGNED NOT NULL,
  refinery_id BIGINT UNSIGNED NULL,
  price_minor BIGINT UNSIGNED NOT NULL,
  discount_ppm INT UNSIGNED NOT NULL,
  due_at DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_epc_contracts_ulid (public_ulid),
  KEY ix_epc_contracts_company_state (company_id, state, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE epc_stages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  epc_contract_id BIGINT UNSIGNED NOT NULL,
  sequence_no SMALLINT UNSIGNED NOT NULL,
  due_at DATETIME(3) NOT NULL,
  installment_minor BIGINT UNSIGNED NOT NULL,
  completed_at DATETIME(3) NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_epc_stages_ulid (public_ulid),
  KEY ix_epc_stages_company_state (company_id, state, id),
  UNIQUE KEY uq_epc_stages_domain (epc_contract_id, sequence_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE refinery_recipe_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  refinery_id BIGINT UNSIGNED NOT NULL,
  recipe_code VARCHAR(32) NOT NULL,
  recipe_version VARCHAR(24) NOT NULL,
  demand_snapshot_at DATETIME(3) NOT NULL,
  contract_snapshot_hash BINARY(32) NOT NULL,
  effective_at DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_refinery_recipe_versions_ulid (public_ulid),
  KEY ix_refinery_recipe_versions_company_state (company_id, state, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE refinery_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  refinery_id BIGINT UNSIGNED NOT NULL,
  recipe_id BIGINT UNSIGNED NOT NULL,
  slot_at DATETIME(3) NOT NULL,
  input_bbl DECIMAL(16,4) NOT NULL,
  output_bbl DECIMAL(16,4) NOT NULL,
  flare_bbl DECIMAL(16,4) NOT NULL DEFAULT 0,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_refinery_runs_ulid (public_ulid),
  KEY ix_refinery_runs_company_state (company_id, state, id),
  UNIQUE KEY uq_refinery_runs_domain (refinery_id, slot_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE product_movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  refinery_run_id BIGINT UNSIGNED NOT NULL,
  product_code VARCHAR(32) NOT NULL,
  quantity_bbl DECIMAL(16,4) NOT NULL,
  destination_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_product_movements_ulid (public_ulid),
  KEY ix_product_movements_company_state (company_id, state, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE insurance_policies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  asset_type VARCHAR(32) NOT NULL,
  asset_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  deductible_minor BIGINT UNSIGNED NOT NULL,
  daily_cap_minor BIGINT UNSIGNED NOT NULL,
  starts_at DATETIME(3) NOT NULL,
  ends_at DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_insurance_policies_ulid (public_ulid),
  KEY ix_insurance_policies_company_state (company_id, state, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE insurance_claims (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  policy_id BIGINT UNSIGNED NOT NULL,
  incident_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  gross_minor BIGINT UNSIGNED NOT NULL,
  paid_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
  filed_at DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_insurance_claims_ulid (public_ulid),
  KEY ix_insurance_claims_company_state (company_id, state, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Monetization
CREATE TABLE payment_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  platform VARCHAR(16) NOT NULL,
  provider VARCHAR(32) NOT NULL,
  product_code VARCHAR(32) NOT NULL,
  amount_minor BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL,
  provider_order_id VARCHAR(128) NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payment_orders_ulid (public_ulid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE payment_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  provider VARCHAR(32) NOT NULL,
  provider_event_id VARCHAR(128) NOT NULL,
  event_type VARCHAR(32) NOT NULL,
  payload_sha256 BINARY(32) NOT NULL,
  received_at DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payment_events_ulid (public_ulid),
  UNIQUE KEY uq_payment_events_domain (provider, provider_event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE payment_transactions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  event_id BIGINT UNSIGNED NOT NULL,
  provider_transaction_id VARCHAR(128) NOT NULL,
  amount_minor BIGINT UNSIGNED NOT NULL,
  settled_at DATETIME(3) NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payment_transactions_ulid (public_ulid),
  UNIQUE KEY uq_payment_transactions_domain (provider_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE gbbl_ledger (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  transaction_key VARCHAR(128) NOT NULL,
  delta_units BIGINT NOT NULL,
  reason_code VARCHAR(32) NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gbbl_ledger_ulid (public_ulid),
  UNIQUE KEY uq_gbbl_ledger_domain (transaction_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE premium_entitlements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  entitlement_code VARCHAR(32) NOT NULL,
  starts_at DATETIME(3) NOT NULL,
  ends_at DATETIME(3) NULL,
  source_order_id BIGINT UNSIGNED NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_premium_entitlements_ulid (public_ulid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE gbbl_conversions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  units BIGINT UNSIGNED NOT NULL,
  cash_minor BIGINT UNSIGNED NOT NULL,
  rate_version VARCHAR(24) NOT NULL,
  ledger_reference CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gbbl_conversions_ulid (public_ulid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE gbbl_accounts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  available_units BIGINT NOT NULL DEFAULT 0,
  recovery_debt_units BIGINT UNSIGNED NOT NULL DEFAULT 0,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gbbl_accounts_ulid (public_ulid),
  UNIQUE KEY uq_gbbl_accounts_domain (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Social
CREATE TABLE chat_threads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  kind VARCHAR(20) NOT NULL,
  subject_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_chat_threads_ulid (public_ulid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE chat_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  thread_id BIGINT UNSIGNED NOT NULL,
  sender_user_id BIGINT UNSIGNED NOT NULL,
  body_text TEXT NOT NULL,
  edited_at DATETIME(3) NULL,
  redacted_at DATETIME(3) NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_chat_messages_ulid (public_ulid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE cartels (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  name VARCHAR(80) NOT NULL,
  founder_company_id BIGINT UNSIGNED NOT NULL,
  invite_policy VARCHAR(20) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cartels_ulid (public_ulid),
  KEY ix_cartels_company_state (company_id, state, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE cartel_memberships (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  cartel_id BIGINT UNSIGNED NOT NULL,
  member_company_id BIGINT UNSIGNED NOT NULL,
  joined_at DATETIME(3) NOT NULL,
  left_at DATETIME(3) NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cartel_memberships_ulid (public_ulid),
  KEY ix_cartel_memberships_company_state (company_id, state, id),
  UNIQUE KEY uq_cartel_memberships_domain (cartel_id, member_company_id, joined_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE diplomacy_relations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  company_id BIGINT UNSIGNED NULL,
  other_company_id BIGINT UNSIGNED NOT NULL,
  relation_code VARCHAR(24) NOT NULL,
  effective_at DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_diplomacy_relations_ulid (public_ulid),
  KEY ix_diplomacy_relations_company_state (company_id, state, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE moderation_reports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  message_id BIGINT UNSIGNED NOT NULL,
  reporter_user_id BIGINT UNSIGNED NOT NULL,
  reason_code VARCHAR(40) NOT NULL,
  decision_code VARCHAR(40) NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_moderation_reports_ulid (public_ulid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE channel_grants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  channel_key VARCHAR(128) NOT NULL,
  granted_at DATETIME(3) NOT NULL,
  revoked_at DATETIME(3) NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_channel_grants_ulid (public_ulid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Onboarding
CREATE TABLE tutorial_progress (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  step_code VARCHAR(64) NOT NULL,
  completed_at DATETIME(3) NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tutorial_progress_ulid (public_ulid),
  UNIQUE KEY uq_tutorial_progress_domain (user_id, step_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE achievement_grants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  achievement_code VARCHAR(64) NOT NULL,
  granted_at DATETIME(3) NOT NULL,
  source_event_key VARCHAR(128) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_achievement_grants_ulid (public_ulid),
  UNIQUE KEY uq_achievement_grants_domain (user_id, achievement_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  template_key VARCHAR(128) NOT NULL,
  locale CHAR(2) NOT NULL,
  event_key VARCHAR(128) NOT NULL,
  payload_json JSON NOT NULL,
  created_at_event DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_notifications_ulid (public_ulid),
  UNIQUE KEY uq_notifications_domain (event_key, user_id, template_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE notification_deliveries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  notification_id BIGINT UNSIGNED NOT NULL,
  channel_code VARCHAR(16) NOT NULL,
  attempt_no SMALLINT UNSIGNED NOT NULL,
  next_attempt_at DATETIME(3) NOT NULL,
  delivered_at DATETIME(3) NULL,
  last_error_code VARCHAR(40) NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_notification_deliveries_ulid (public_ulid),
  UNIQUE KEY uq_notification_deliveries_domain (notification_id, channel_code, attempt_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE notification_preferences (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  channel_code VARCHAR(16) NOT NULL,
  category_code VARCHAR(32) NOT NULL,
  enabled TINYINT(1) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_notification_preferences_ulid (public_ulid),
  UNIQUE KEY uq_notification_preferences_domain (user_id, channel_code, category_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- CMS
CREATE TABLE static_pages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  slug VARCHAR(120) NOT NULL,
  locale CHAR(2) NOT NULL,
  published_version_id BIGINT UNSIGNED NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_static_pages_ulid (public_ulid),
  UNIQUE KEY uq_static_pages_domain (slug, locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE page_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  page_id BIGINT UNSIGNED NOT NULL,
  version_no INT UNSIGNED NOT NULL,
  safe_html MEDIUMTEXT NOT NULL,
  approved_by_user_id BIGINT UNSIGNED NULL,
  published_at DATETIME(3) NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_page_versions_ulid (public_ulid),
  UNIQUE KEY uq_page_versions_domain (page_id, version_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE privacy_policies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  policy_code VARCHAR(40) NOT NULL,
  locale CHAR(2) NOT NULL,
  version_no INT UNSIGNED NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  effective_at DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_privacy_policies_ulid (public_ulid),
  UNIQUE KEY uq_privacy_policies_domain (policy_code, locale, version_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE consent_records (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  policy_id BIGINT UNSIGNED NOT NULL,
  granted TINYINT(1) NOT NULL,
  recorded_at DATETIME(3) NOT NULL,
  evidence_hash BINARY(32) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_consent_records_ulid (public_ulid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE privacy_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  request_type VARCHAR(24) NOT NULL,
  requested_at DATETIME(3) NOT NULL,
  due_at DATETIME(3) NOT NULL,
  completed_at DATETIME(3) NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_privacy_requests_ulid (public_ulid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE newsletter_campaigns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  subject_pl VARCHAR(255) NOT NULL,
  subject_en VARCHAR(255) NOT NULL,
  subject_de VARCHAR(255) NOT NULL,
  safe_html MEDIUMTEXT NOT NULL,
  approved_by_user_id BIGINT UNSIGNED NULL,
  scheduled_at DATETIME(3) NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_newsletter_campaigns_ulid (public_ulid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE newsletter_deliveries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  attempt_no SMALLINT UNSIGNED NOT NULL,
  sent_at DATETIME(3) NULL,
  provider_message_id VARCHAR(128) NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_newsletter_deliveries_ulid (public_ulid),
  UNIQUE KEY uq_newsletter_deliveries_domain (campaign_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Client
CREATE TABLE client_version_support (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  platform VARCHAR(16) NOT NULL,
  min_contract_version VARCHAR(24) NOT NULL,
  max_contract_version VARCHAR(24) NOT NULL,
  min_app_version VARCHAR(24) NULL,
  effective_at DATETIME(3) NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_client_version_support_ulid (public_ulid),
  UNIQUE KEY uq_client_version_support_domain (platform, effective_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE ui_preferences (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  locale CHAR(2) NOT NULL,
  theme_code VARCHAR(32) NOT NULL,
  reduced_motion TINYINT(1) NOT NULL DEFAULT 0,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ui_preferences_ulid (public_ulid),
  UNIQUE KEY uq_ui_preferences_domain (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Operations
CREATE TABLE release_reports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  release_version VARCHAR(40) NOT NULL,
  contract_version VARCHAR(24) NOT NULL,
  balance_version VARCHAR(24) NOT NULL,
  yaml_sha256 BINARY(32) NOT NULL,
  status VARCHAR(20) NOT NULL,
  report_json JSON NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_release_reports_ulid (public_ulid),
  UNIQUE KEY uq_release_reports_domain (release_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE restore_reports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  release_report_id BIGINT UNSIGNED NULL,
  backup_id VARCHAR(128) NOT NULL,
  started_at DATETIME(3) NOT NULL,
  completed_at DATETIME(3) NULL,
  rpo_seconds INT UNSIGNED NULL,
  rto_seconds INT UNSIGNED NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_restore_reports_ulid (public_ulid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE reconciliation_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  scope_code VARCHAR(32) NOT NULL,
  slot_at DATETIME(3) NOT NULL,
  mismatch_count INT UNSIGNED NOT NULL DEFAULT 0,
  evidence_sha256 BINARY(32) NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reconciliation_runs_ulid (public_ulid),
  UNIQUE KEY uq_reconciliation_runs_domain (scope_code, slot_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE alert_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  code VARCHAR(64) NOT NULL,
  severity VARCHAR(12) NOT NULL,
  observed_at DATETIME(3) NOT NULL,
  resolved_at DATETIME(3) NULL,
  context_json JSON NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_alert_events_ulid (public_ulid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE runbook_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  runbook_code VARCHAR(40) NOT NULL,
  version_no INT UNSIGNED NOT NULL,
  sha256 BINARY(32) NOT NULL,
  approved_at DATETIME(3) NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  state_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  balance_version VARCHAR(24) NULL,
  logical_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_runbook_versions_ulid (public_ulid),
  UNIQUE KEY uq_runbook_versions_domain (runbook_code, version_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- FK po CREATE, aby cykle page/version i refinery/recipe nie blokowały tworzenia.
ALTER TABLE market_offers ADD CONSTRAINT fk_market_offers_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE market_trades ADD CONSTRAINT fk_market_trades_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE black_market_trades ADD CONSTRAINT fk_black_market_trades_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE contracts ADD CONSTRAINT fk_contracts_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE contract_tranches ADD CONSTRAINT fk_contract_tranches_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE contract_tranches ADD CONSTRAINT fk_contract_tranches_contract_id FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE b2b_offers ADD CONSTRAINT fk_b2b_offers_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE escrow_accounts ADD CONSTRAINT fk_escrow_accounts_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE escrow_movements ADD CONSTRAINT fk_escrow_movements_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE escrow_movements ADD CONSTRAINT fk_escrow_movements_escrow_account_id FOREIGN KEY (escrow_account_id) REFERENCES escrow_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE delivery_receipts ADD CONSTRAINT fk_delivery_receipts_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE contract_disputes ADD CONSTRAINT fk_contract_disputes_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE contract_disputes ADD CONSTRAINT fk_contract_disputes_contract_id FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE contract_disputes ADD CONSTRAINT fk_contract_disputes_receipt_id FOREIGN KEY (receipt_id) REFERENCES delivery_receipts(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE department_budgets ADD CONSTRAINT fk_department_budgets_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE player_finance_decisions ADD CONSTRAINT fk_player_finance_decisions_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE asset_valuations ADD CONSTRAINT fk_asset_valuations_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE net_worth_snapshots ADD CONSTRAINT fk_net_worth_snapshots_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE wealth_tax_assessments ADD CONSTRAINT fk_wealth_tax_assessments_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE wealth_tax_assessments ADD CONSTRAINT fk_wealth_tax_assessments_snapshot_id FOREIGN KEY (snapshot_id) REFERENCES net_worth_snapshots(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE bank_trust_scores ADD CONSTRAINT fk_bank_trust_scores_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE credit_score_history ADD CONSTRAINT fk_credit_score_history_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE credit_score_history ADD CONSTRAINT fk_credit_score_history_score_id FOREIGN KEY (score_id) REFERENCES bank_trust_scores(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE loans ADD CONSTRAINT fk_loans_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE loan_installments ADD CONSTRAINT fk_loan_installments_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE loan_installments ADD CONSTRAINT fk_loan_installments_loan_id FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE bailiff_proceedings ADD CONSTRAINT fk_bailiff_proceedings_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE auctions ADD CONSTRAINT fk_auctions_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE auctions ADD CONSTRAINT fk_auctions_proceeding_id FOREIGN KEY (proceeding_id) REFERENCES bailiff_proceedings(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE sabotage_operations ADD CONSTRAINT fk_sabotage_operations_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE victim_attack_windows ADD CONSTRAINT fk_victim_attack_windows_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE protection_policies ADD CONSTRAINT fk_protection_policies_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE protection_activations ADD CONSTRAINT fk_protection_activations_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE protection_activations ADD CONSTRAINT fk_protection_activations_policy_id FOREIGN KEY (policy_id) REFERENCES protection_policies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE protection_activations ADD CONSTRAINT fk_protection_activations_attack_id FOREIGN KEY (attack_id) REFERENCES sabotage_operations(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE abuse_reports ADD CONSTRAINT fk_abuse_reports_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE refinery_plots ADD CONSTRAINT fk_refinery_plots_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE epc_contracts ADD CONSTRAINT fk_epc_contracts_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE epc_contracts ADD CONSTRAINT fk_epc_contracts_plot_id FOREIGN KEY (plot_id) REFERENCES refinery_plots(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE epc_contracts ADD CONSTRAINT fk_epc_contracts_refinery_id FOREIGN KEY (refinery_id) REFERENCES refineries(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE epc_stages ADD CONSTRAINT fk_epc_stages_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE epc_stages ADD CONSTRAINT fk_epc_stages_epc_contract_id FOREIGN KEY (epc_contract_id) REFERENCES epc_contracts(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE refinery_recipe_versions ADD CONSTRAINT fk_refinery_recipe_versions_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE refinery_recipe_versions ADD CONSTRAINT fk_refinery_recipe_versions_refinery_id FOREIGN KEY (refinery_id) REFERENCES refineries(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE refinery_runs ADD CONSTRAINT fk_refinery_runs_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE refinery_runs ADD CONSTRAINT fk_refinery_runs_refinery_id FOREIGN KEY (refinery_id) REFERENCES refineries(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE refinery_runs ADD CONSTRAINT fk_refinery_runs_recipe_id FOREIGN KEY (recipe_id) REFERENCES refinery_recipe_versions(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE product_movements ADD CONSTRAINT fk_product_movements_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE product_movements ADD CONSTRAINT fk_product_movements_refinery_run_id FOREIGN KEY (refinery_run_id) REFERENCES refinery_runs(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE insurance_policies ADD CONSTRAINT fk_insurance_policies_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE insurance_claims ADD CONSTRAINT fk_insurance_claims_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE insurance_claims ADD CONSTRAINT fk_insurance_claims_policy_id FOREIGN KEY (policy_id) REFERENCES insurance_policies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE payment_events ADD CONSTRAINT fk_payment_events_order_id FOREIGN KEY (order_id) REFERENCES payment_orders(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE payment_transactions ADD CONSTRAINT fk_payment_transactions_order_id FOREIGN KEY (order_id) REFERENCES payment_orders(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE payment_transactions ADD CONSTRAINT fk_payment_transactions_event_id FOREIGN KEY (event_id) REFERENCES payment_events(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE premium_entitlements ADD CONSTRAINT fk_premium_entitlements_source_order_id FOREIGN KEY (source_order_id) REFERENCES payment_orders(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE chat_messages ADD CONSTRAINT fk_chat_messages_thread_id FOREIGN KEY (thread_id) REFERENCES chat_threads(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE cartels ADD CONSTRAINT fk_cartels_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE cartel_memberships ADD CONSTRAINT fk_cartel_memberships_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE cartel_memberships ADD CONSTRAINT fk_cartel_memberships_cartel_id FOREIGN KEY (cartel_id) REFERENCES cartels(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE diplomacy_relations ADD CONSTRAINT fk_diplomacy_relations_company_id FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE moderation_reports ADD CONSTRAINT fk_moderation_reports_message_id FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE notification_deliveries ADD CONSTRAINT fk_notification_deliveries_notification_id FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE static_pages ADD CONSTRAINT fk_static_pages_published_version_id FOREIGN KEY (published_version_id) REFERENCES page_versions(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE page_versions ADD CONSTRAINT fk_page_versions_page_id FOREIGN KEY (page_id) REFERENCES static_pages(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE consent_records ADD CONSTRAINT fk_consent_records_policy_id FOREIGN KEY (policy_id) REFERENCES privacy_policies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE newsletter_deliveries ADD CONSTRAINT fk_newsletter_deliveries_campaign_id FOREIGN KEY (campaign_id) REFERENCES newsletter_campaigns(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE restore_reports ADD CONSTRAINT fk_restore_reports_release_report_id FOREIGN KEY (release_report_id) REFERENCES release_reports(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
CREATE INDEX ix_market_offers_match ON market_offers (state, side, limit_price_minor, expires_at, id);
CREATE INDEX ix_contract_tranches_due ON contract_tranches (state, due_at, id);
CREATE INDEX ix_loan_installments_due ON loan_installments (state, due_at, id);
CREATE INDEX ix_notification_deliveries_due ON notification_deliveries (state, next_attempt_at, id);
CREATE INDEX ix_epc_stages_due ON epc_stages (state, due_at, id);
CREATE INDEX ix_escrow_accounts_expiry ON escrow_accounts (state, expires_at, id);
CREATE INDEX ix_payment_events_order ON payment_events (order_id, received_at);
CREATE INDEX ix_refinery_runs_slot ON refinery_runs (slot_at, refinery_id);

-- FK do tabel P01–P13. Preflight musi potwierdzić BIGINT UNSIGNED i nazwę tabeli; jeśli kontrakt bazowy używa innego klucza, migracja mapuje bez zmiany domeny.
ALTER TABLE market_offers ADD CONSTRAINT fk_market_offers_storage_id FOREIGN KEY (storage_id) REFERENCES storages(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE market_trades ADD CONSTRAINT fk_market_trades_buyer_company_id FOREIGN KEY (buyer_company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE market_trades ADD CONSTRAINT fk_market_trades_seller_company_id FOREIGN KEY (seller_company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE black_market_trades ADD CONSTRAINT fk_black_market_trades_storage_id FOREIGN KEY (storage_id) REFERENCES storages(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE contracts ADD CONSTRAINT fk_contracts_buyer_company_id FOREIGN KEY (buyer_company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE contracts ADD CONSTRAINT fk_contracts_seller_company_id FOREIGN KEY (seller_company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE b2b_offers ADD CONSTRAINT fk_b2b_offers_buyer_company_id FOREIGN KEY (buyer_company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE b2b_offers ADD CONSTRAINT fk_b2b_offers_seller_company_id FOREIGN KEY (seller_company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE b2b_offers ADD CONSTRAINT fk_b2b_offers_source_storage_id FOREIGN KEY (source_storage_id) REFERENCES storages(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE sabotage_operations ADD CONSTRAINT fk_sabotage_operations_attacker_company_id FOREIGN KEY (attacker_company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE sabotage_operations ADD CONSTRAINT fk_sabotage_operations_victim_company_id FOREIGN KEY (victim_company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE victim_attack_windows ADD CONSTRAINT fk_victim_attack_windows_victim_company_id FOREIGN KEY (victim_company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE abuse_reports ADD CONSTRAINT fk_abuse_reports_reporter_user_id FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE abuse_reports ADD CONSTRAINT fk_abuse_reports_reviewed_by_user_id FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE abuse_reports ADD CONSTRAINT fk_abuse_reports_subject_company_id FOREIGN KEY (subject_company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE payment_orders ADD CONSTRAINT fk_payment_orders_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE gbbl_ledger ADD CONSTRAINT fk_gbbl_ledger_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE premium_entitlements ADD CONSTRAINT fk_premium_entitlements_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE gbbl_conversions ADD CONSTRAINT fk_gbbl_conversions_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE gbbl_accounts ADD CONSTRAINT fk_gbbl_accounts_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE chat_threads ADD CONSTRAINT fk_chat_threads_created_by_user_id FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE chat_messages ADD CONSTRAINT fk_chat_messages_sender_user_id FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE cartels ADD CONSTRAINT fk_cartels_founder_company_id FOREIGN KEY (founder_company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE cartel_memberships ADD CONSTRAINT fk_cartel_memberships_member_company_id FOREIGN KEY (member_company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE diplomacy_relations ADD CONSTRAINT fk_diplomacy_relations_other_company_id FOREIGN KEY (other_company_id) REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE moderation_reports ADD CONSTRAINT fk_moderation_reports_reporter_user_id FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE channel_grants ADD CONSTRAINT fk_channel_grants_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE tutorial_progress ADD CONSTRAINT fk_tutorial_progress_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE achievement_grants ADD CONSTRAINT fk_achievement_grants_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE notifications ADD CONSTRAINT fk_notifications_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE notification_preferences ADD CONSTRAINT fk_notification_preferences_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE consent_records ADD CONSTRAINT fk_consent_records_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE privacy_requests ADD CONSTRAINT fk_privacy_requests_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE newsletter_deliveries ADD CONSTRAINT fk_newsletter_deliveries_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE ui_preferences ADD CONSTRAINT fk_ui_preferences_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT;
