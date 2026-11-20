# OilEmpire — kontrakty wykonawcze akcji P14–P25

Ten plik uzupełnia karty o wymagania planu §3 dla każdej Action/Query. Normatywne powiązanie: ID → [karty](OILEMPIRE_PLAN_PROGRAMOWANIA.md#11-mapa-wykonawcza-p14p25) → [manifest](OILEMPIRE_KONTRAKTY_P14_P25.yaml) → [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql) → [OpenAPI](OILEMPIRE_OPENAPI_P14_P25_V1.yaml). Kolumny/typy/nullability/FK/UNIQUE są w DDL, bez powielania migracji P01–P13. Polityka DOM działa tylko w zakresie przypisanym do Action. Karty z wartościami DOM do zatwierdzenia nie są aktywne.

**Reguła wspólna migracji:** expand tworzy tylko tabele właściciela i indeksy; greenfield nie ma backfill. Import legacy wymaga osobnego mapowania, reconciliation i dry-run. Rollback aplikacji to draining + wyłączenie trasy/handlera + forward-fix bez DROP historii, FTS i outbox. Wydanie P25 zapisuje wersję kontraktu, schema i balance hash. **Reguła wspólna UI:** PL/EN/DE, lokalny SVG z katalogu modułu, dostępny modal (bez alert/confirm/prompt), stany loading/empty/error/conflict/offline, klawiatura i 320–1440 px. **Reguła wspólna zdarzeń:** outbox w transakcji z efektem; payload bez PII/sekretów, event_id do deduplikacji, ograniczone retry, DLQ+alert, manual replay z tym samym ID.


## P14

### P14-F01 — QuoteSale

- **Aktor/polityka:** market.read; cudzy obiekt → 404; właściciel zapisu Market. Źródło/warunek: P03/P04/P12.
- **Interface:** POST /api/v1/market/spot/quotes; company, source_storage, bbl, expected_state_version → cena/fee/ważność quote. Odpowiedź i kody FORBIDDEN, NOT_FOUND, RATE_LIMITED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - price_history [Market]: region_code VARCHAR(24) NOT NULL; price_minor BIGINT UNSIGNED NOT NULL; volume_60m_bbl DECIMAL(16,4) NOT NULL; event_multiplier_ppm INT UNSIGNED NOT NULL; slot_at DATETIME(3) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
  - stock_reservations [P01-P13 (public contract); kontrakt wcześniejszego pakietu]
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | bez zmiany | brak wolumenu/nieaktualna wersja → odmowa, bez ceny klienta; Clock UTC only_if_scheduled | odczyt aktualnego zapasu i ceny; bez rezerwacji oraz FTS | Brak zmian. |

- **Transakcja i czas:** none; read projection with ETag/as_of; read_only_no_side_effect; logical_at=server_as_of; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** Brak zdarzenia domenowego i outbox z odczytu.
- **UI i panel:** Rynek: oferta/quote; pl/en/de; SVG pod resources/svg/market/; loading, empty, error, offline; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik market.quote_sale.result etykiety result, reason_code, balance_version bez PII. granica limit/B2B, równoczesna rezerwacja; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P14-F02 — ExecuteSpot

- **Aktor/polityka:** market.trade; cudzy obiekt → 404; właściciel zapisu Market. Źródło/warunek: P03/P04/P12.
- **Interface:** POST /api/v1/market/spot/sales; quote_id, bbl, idempotency_key → trade_id/FTS/ref. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - market_trades [Market]: company_id BIGINT UNSIGNED NULL; buyer_company_id BIGINT UNSIGNED NULL; seller_company_id BIGINT UNSIGNED NULL; offer_id BIGINT UNSIGNED NULL; quantity_bbl DECIMAL(16,4) NOT NULL; unit_price_minor BIGINT UNSIGNED NOT NULL; total_minor BIGINT UNSIGNED NOT NULL; …
  - oil_movements [P01-P13 (public contract); kontrakt wcześniejszego pakietu]
  - ledger_accounts [P01-P13 (public contract); kontrakt wcześniejszego pakietu]
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | brak zapasu, wygasła wycena lub limit awaryjny → rollback całości; Clock UTC only_if_scheduled | available → sold, ruch bbl raz i wpływ PLN_BANK przez Ledger, zajęcie komornika w tej samej transakcji | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→11→20→70→100→130→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** market.execute_spot.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Contracts, Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Rynek: oferta/quote; pl/en/de; SVG pod resources/svg/market/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik market.execute_spot.result etykiety result, reason_code, balance_version bez PII. 2 sprzedaże tej samej baryłki, retry, egzekucja; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P14-F03 — PlaceLimitOrder

- **Aktor/polityka:** market.trade; cudzy obiekt → 404; właściciel zapisu Market. Źródło/warunek: P03/P04/P12.
- **Interface:** POST /api/v1/market/limit-orders; storage, bbl, limit_price_minor, expected_version → offer_id. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - market_offers [Market]: company_id BIGINT UNSIGNED NULL; storage_id BIGINT UNSIGNED NOT NULL; side VARCHAR(8) NOT NULL; quantity_bbl DECIMAL(16,4) NOT NULL; remaining_bbl DECIMAL(16,4) NOT NULL; limit_price_minor BIGINT UNSIGNED NOT NULL; expires_at DATETIME(3) NOT NULL; …
  - stock_reservations [P01-P13 (public contract); kontrakt wcześniejszego pakietu]
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | brak zapasu/limit niespełniony → brak oferty; Clock UTC only_if_scheduled | available → reserved_bbl, published; cena zmieniana tylko wersjonowaną akcją | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→11→20→70→100→130→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** market.place_limit_order.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Contracts, Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Rynek: oferta/quote; pl/en/de; SVG pod resources/svg/market/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik market.place_limit_order.result etykiety result, reason_code, balance_version bez PII. spot kontra limit, anulowanie i zwrot rezerwacji; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P14-F04 — MatchOrders

- **Aktor/polityka:** system_worker_lease_fencing; cudzy obiekt → 404; właściciel zapisu Market. Źródło/warunek: P03/P04/P12.
- **Interface:** internal:Market.MatchOrders; due_slot, price_snapshot → list trade_id. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - market_offers [Market]: company_id BIGINT UNSIGNED NULL; storage_id BIGINT UNSIGNED NOT NULL; side VARCHAR(8) NOT NULL; quantity_bbl DECIMAL(16,4) NOT NULL; remaining_bbl DECIMAL(16,4) NOT NULL; limit_price_minor BIGINT UNSIGNED NOT NULL; expires_at DATETIME(3) NOT NULL; …
  - market_trades [Market]: company_id BIGINT UNSIGNED NULL; buyer_company_id BIGINT UNSIGNED NULL; seller_company_id BIGINT UNSIGNED NULL; offer_id BIGINT UNSIGNED NULL; quantity_bbl DECIMAL(16,4) NOT NULL; unit_price_minor BIGINT UNSIGNED NOT NULL; total_minor BIGINT UNSIGNED NOT NULL; …
  - price_history [Market]: region_code VARCHAR(24) NOT NULL; price_minor BIGINT UNSIGNED NOT NULL; volume_60m_bbl DECIMAL(16,4) NOT NULL; event_multiplier_ppm INT UNSIGNED NOT NULL; slot_at DATETIME(3) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | po nieudanym workerze replay bez drugiej sprzedaży; Clock UTC stable_domain_slot | published → filled/partial tylko przez fazę globalnego matchingu; stabilny porządek ofert i effect_key | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→11→20→70→100→130→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** market.match_orders.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Contracts, Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Rynek: oferta/quote; pl/en/de; SVG pod resources/svg/market/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik market.match_orders.result etykiety result, reason_code, balance_version bez PII. 2 workery, kryzys cenowy, partial; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P14-F05 — TradeBlackMarket

- **Aktor/polityka:** market.trade; cudzy obiekt → 404; właściciel zapisu Market. Źródło/warunek: P03/P04/P12.
- **Interface:** POST /api/v1/market/black-market/trades; storage, bbl, quote_id → trade_id/cash_minor. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - black_market_trades [Market]: company_id BIGINT UNSIGNED NULL; storage_id BIGINT UNSIGNED NOT NULL; quantity_bbl DECIMAL(16,4) NOT NULL; unit_price_minor BIGINT UNSIGNED NOT NULL; risk_seed VARBINARY(32) NOT NULL; risk_result TINYINT UNSIGNED NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
  - company_reputation_state [P01-P13 (public contract); kontrakt wcześniejszego pakietu]
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | przekroczenie limitu, brak zapasu lub kontrola → jawny wynik; Clock UTC only_if_scheduled | available → sold; PLN_CASH, black_market_score +2; limit 5 transakcji i 1500 bbl/dobę UTC | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→11→20→70→100→130→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=persist_seed_and_outcome_once. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** market.trade_black_market.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Contracts, Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Rynek: oferta/quote; pl/en/de; SVG pod resources/svg/market/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik market.trade_black_market.result etykiety result, reason_code, balance_version bez PII. 2 równoległe transakcje na progu, score decay; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.


## P15

### P15-F01 — GenerateNpcOffers

- **Aktor/polityka:** system_worker_lease_fencing; cudzy obiekt → 404; właściciel zapisu Contracts. Źródło/warunek: P10/P12/P14.
- **Interface:** internal:Contracts.GenerateNpcOffers; region_id, due_slot, balance_version → oferta NPC. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - system_contract_offers [Contracts]: region_code VARCHAR(24) NOT NULL; product_code VARCHAR(32) NOT NULL; quantity_bbl DECIMAL(16,4) NOT NULL; unit_price_minor BIGINT UNSIGNED NOT NULL; deposit_pct SMALLINT UNSIGNED NOT NULL; expires_at DATETIME(3) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | brak miejsca w puli → no-op; Clock UTC stable_domain_slot | HMAC seed; pula 3+floor(gracze/25), max8/region, odstęp 24h; oferta ważna 24–72h | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→12→20→70→80→90→110→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** contracts.generate_npc_offers.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Logistics, Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Kontrakty: oferta, escrow i spór; pl/en/de; SVG pod resources/svg/contracts/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik contracts.generate_npc_offers.result etykiety result, reason_code, balance_version bez PII. retry i dwa sweepy nie dublują oferty; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P15-F02 — AcceptContract

- **Aktor/polityka:** contracts.manage; cudzy obiekt → 404; właściciel zapisu Contracts. Źródło/warunek: P10/P12/P14.
- **Interface:** POST /api/v1/contracts/offers/{id}/accept; offer_id, company_id, quote_version → contract/tranche plan. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - contracts [Contracts]: company_id BIGINT UNSIGNED NULL; buyer_company_id BIGINT UNSIGNED NULL; seller_company_id BIGINT UNSIGNED NULL; system_offer_id BIGINT UNSIGNED NULL; b2b_offer_id BIGINT UNSIGNED NULL; kind VARCHAR(16) NOT NULL; quantity_bbl DECIMAL(16,4) NOT NULL; …
  - contract_tranches [Contracts]: company_id BIGINT UNSIGNED NULL; contract_id BIGINT UNSIGNED NOT NULL; sequence_no INT UNSIGNED NOT NULL; quantity_bbl DECIMAL(16,4) NOT NULL; due_at DATETIME(3) NOT NULL; received_bbl DECIMAL(16,4) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
  - escrow_accounts [Contracts]: company_id BIGINT UNSIGNED NULL; contract_id BIGINT UNSIGNED NULL; b2b_offer_id BIGINT UNSIGNED NULL; held_minor BIGINT UNSIGNED NOT NULL DEFAULT 0; released_minor BIGINT UNSIGNED NOT NULL DEFAULT 0; refunded_minor BIGINT UNSIGNED NOT NULL DEFAULT 0; expires_at DATETIME(3) NOT NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | wygasła oferta, reputacja lub brak środków → bez kontraktu; Clock UTC only_if_scheduled | open → accepted; kaucja NPC 10% lub premium 5%; B2B osobny depozyt/zakres | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→12→20→70→80→90→110→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** contracts.accept_contract.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Logistics, Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Kontrakty: oferta, escrow i spór; pl/en/de; SVG pod resources/svg/contracts/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik contracts.accept_contract.result etykiety result, reason_code, balance_version bez PII. limit tier, kaucja raz, kontrakt po retry; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P15-F03 — FundEscrow

- **Aktor/polityka:** contracts.manage; cudzy obiekt → 404; właściciel zapisu Contracts. Źródło/warunek: P10/P12/P14.
- **Interface:** POST /api/v1/contracts/b2b/offers/{id}/accept; b2b_offer_id, buyer, expected_version → escrow_id. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - b2b_offers [Contracts]: company_id BIGINT UNSIGNED NULL; seller_company_id BIGINT UNSIGNED NOT NULL; buyer_company_id BIGINT UNSIGNED NULL; source_storage_id BIGINT UNSIGNED NOT NULL; quantity_bbl DECIMAL(16,4) NOT NULL; unit_price_minor BIGINT UNSIGNED NOT NULL; expires_at DATETIME(3) NOT NULL; …
  - escrow_accounts [Contracts]: company_id BIGINT UNSIGNED NULL; contract_id BIGINT UNSIGNED NULL; b2b_offer_id BIGINT UNSIGNED NULL; held_minor BIGINT UNSIGNED NOT NULL DEFAULT 0; released_minor BIGINT UNSIGNED NOT NULL DEFAULT 0; refunded_minor BIGINT UNSIGNED NOT NULL DEFAULT 0; expires_at DATETIME(3) NOT NULL; …
  - stock_reservations [P01-P13 (public contract); kontrakt wcześniejszego pakietu]
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | brak środków albo wolumenu → rollback obu stron; Clock UTC only_if_scheduled | published → accepted; rezerwacja 100% wartości i ropy sprzedawcy w jednej transakcji | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→12→20→70→80→90→110→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** contracts.fund_escrow.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Logistics, Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Kontrakty: oferta, escrow i spór; pl/en/de; SVG pod resources/svg/contracts/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik contracts.fund_escrow.result etykiety result, reason_code, balance_version bez PII. dwie akceptacje, cross-company access, saldo; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P15-F04 — SettleReceipt

- **Aktor/polityka:** system_worker_lease_fencing; cudzy obiekt → 404; właściciel zapisu Contracts. Źródło/warunek: P10/P12/P14.
- **Interface:** internal:Contracts.SettleReceipt; tranche_id, receipt_id, logical_at → settlement_id. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - delivery_receipts [Logistics P12, tylko odczyt]: fizyczny receipt pod trip; contract_receipt_links [Contracts]: logistics_receipt_id, escrow_account_id, effect_key, settled_bbl, linked_at.
  - contract_tranches [Contracts]: company_id BIGINT UNSIGNED NULL; contract_id BIGINT UNSIGNED NOT NULL; sequence_no INT UNSIGNED NOT NULL; quantity_bbl DECIMAL(16,4) NOT NULL; due_at DATETIME(3) NOT NULL; received_bbl DECIMAL(16,4) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
  - escrow_accounts [Contracts]: company_id BIGINT UNSIGNED NULL; contract_id BIGINT UNSIGNED NULL; b2b_offer_id BIGINT UNSIGNED NULL; held_minor BIGINT UNSIGNED NOT NULL DEFAULT 0; released_minor BIGINT UNSIGNED NOT NULL DEFAULT 0; refunded_minor BIGINT UNSIGNED NOT NULL DEFAULT 0; expires_at DATETIME(3) NOT NULL; …
  - escrow_movements [Contracts]: company_id BIGINT UNSIGNED NULL; escrow_account_id BIGINT UNSIGNED NOT NULL; movement_key VARCHAR(128) NOT NULL; kind VARCHAR(20) NOT NULL; amount_minor BIGINT UNSIGNED NOT NULL; ledger_reference CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | receipt po refund → rozstrzygnięcie według logic_at i stanu, bez podwójnej wypłaty; Clock UTC stable_domain_slot | in_transit/waiting_bay → delivered/settled; ruch bbl, prowizja 1,5%, wypłata na PLN_BANK, garnishment raz | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→12→20→70→80→90→110→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** contracts.settle_receipt.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Logistics, Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Kontrakty: oferta, escrow i spór; pl/en/de; SVG pod resources/svg/contracts/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik contracts.settle_receipt.result etykiety result, reason_code, balance_version bez PII. receipt kontra timeout/refund i 168h; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P15-F05 — ResolveDispute

- **Aktor/polityka:** system_worker_lease_fencing; cudzy obiekt → 404; właściciel zapisu Contracts. Źródło/warunek: P10/P12/P14.
- **Interface:** internal:Contracts.ResolveDispute; dispute_id, dowody, decision, expected_version → resolved outcome. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - contract_disputes [Contracts]: company_id BIGINT UNSIGNED NULL; contract_id BIGINT UNSIGNED NOT NULL; receipt_id BIGINT UNSIGNED NULL; reason_code VARCHAR(40) NOT NULL; filed_at DATETIME(3) NOT NULL; resolved_at DATETIME(3) NULL; resolution_code VARCHAR(40) NULL; …
  - escrow_accounts [Contracts]: company_id BIGINT UNSIGNED NULL; contract_id BIGINT UNSIGNED NULL; b2b_offer_id BIGINT UNSIGNED NULL; held_minor BIGINT UNSIGNED NOT NULL DEFAULT 0; released_minor BIGINT UNSIGNED NOT NULL DEFAULT 0; refunded_minor BIGINT UNSIGNED NOT NULL DEFAULT 0; expires_at DATETIME(3) NOT NULL; …
  - escrow_movements [Contracts]: company_id BIGINT UNSIGNED NULL; escrow_account_id BIGINT UNSIGNED NOT NULL; movement_key VARCHAR(128) NOT NULL; kind VARCHAR(20) NOT NULL; amount_minor BIGINT UNSIGNED NOT NULL; ledger_reference CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | brak uprawnienia/konflikt dowodów → jawne wstrzymanie do twardego limitu; Clock UTC stable_domain_slot | disputed → resolved_settled/split/refunded; max168h; późny GM award poza escrow | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→12→20→70→80→90→110→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** contracts.resolve_dispute.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Logistics, Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Kontrakty: oferta, escrow i spór; pl/en/de; SVG pod resources/svg/contracts/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik contracts.resolve_dispute.result etykiety result, reason_code, balance_version bez PII. freeze 72h, cap 168h, replays; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.


## P16

### P16-F01 — ChangeBudget

- **Aktor/polityka:** finance.manage; cudzy obiekt → 404; właściciel zapisu Finance. Źródło/warunek: P03/P06/P08/P11/P12/P15.
- **Interface:** PUT /api/v1/finance/budgets/{department}; department, low|standard|high, expected_version → effective_at. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - department_budgets [Finance]: company_id BIGINT UNSIGNED NULL; department_code VARCHAR(32) NOT NULL; daily_limit_minor BIGINT UNSIGNED NOT NULL; effective_at DATETIME(3) NOT NULL; cooldown_until DATETIME(3) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
  - player_finance_decisions [Finance]: company_id BIGINT UNSIGNED NULL; decision_key VARCHAR(128) NOT NULL; department_code VARCHAR(32) NOT NULL; old_limit_minor BIGINT UNSIGNED NOT NULL; new_limit_minor BIGINT UNSIGNED NOT NULL; morale_delta SMALLINT NOT NULL DEFAULT 0; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | brak dyrektora finansów poza jawnie dozwoloną ścieżką emergency → odmowa; Clock UTC only_if_scheduled | 24h cooldown; obniżka emituje morale −6 raz, koszt od kolejnego segmentu czasu | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→11→14/15→19→20→40–120→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** finance.change_budget.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Banking, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Finanse: budżet i podatek; pl/en/de; SVG pod resources/svg/finance/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik finance.change_budget.result etykiety result, reason_code, balance_version bez PII. zmiana na granicy 24h, strajk, retry; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P16-F02 — AccrueCosts

- **Aktor/polityka:** system_worker_lease_fencing; cudzy obiekt → 404; właściciel zapisu Finance. Źródło/warunek: P03/P06/P08/P11/P12/P15.
- **Interface:** internal:Finance.AccrueCosts; company, from,to, balance_version → obligations/FTS refs. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - department_budgets [Finance]: company_id BIGINT UNSIGNED NULL; department_code VARCHAR(32) NOT NULL; daily_limit_minor BIGINT UNSIGNED NOT NULL; effective_at DATETIME(3) NOT NULL; cooldown_until DATETIME(3) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
  - obligations [P01-P13 (public contract); kontrakt wcześniejszego pakietu]
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | pauza/vacation/dormant zgodnie z §19.2, bez ujemnego konta; Clock UTC stable_domain_slot | segmentowe OPEX/budżety/HR; subgroszowa reszta przenoszona; niedobór tworzy zobowiązanie 72h | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→11→14/15→19→20→40–120→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** finance.accrue_costs.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Banking, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Finanse: budżet i podatek; pl/en/de; SVG pod resources/svg/finance/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik finance.accrue_costs.result etykiety result, reason_code, balance_version bez PII. długi Δt, zmiana stawki, dwa workery; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P16-F03 — ValueAsset

- **Aktor/polityka:** finance.read; cudzy obiekt → 404; właściciel zapisu Finance. Źródło/warunek: P03/P06/P08/P11/P12/P15.
- **Interface:** GET /api/v1/finance/assets/{id}/valuation; asset_ref, as_of, valuation_purpose → breakdown_minor. Odpowiedź i kody FORBIDDEN, NOT_FOUND, RATE_LIMITED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - asset_valuations [Finance]: company_id BIGINT UNSIGNED NULL; asset_type VARCHAR(32) NOT NULL; asset_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL; gross_minor BIGINT NOT NULL; taxable_minor BIGINT NOT NULL; formula_version VARCHAR(24) NOT NULL; valued_at DATETIME(3) NOT NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | bez zmiany | brak wycenialnego aktywa → typed unavailable, bez zera z domysłu; Clock UTC only_if_scheduled | publiczne snapshoty właścicieli; CAPEX razy stan, depleted ×0,10; refinery według §31.7 bez drugiego ×0,70 | Brak zmian. |

- **Transakcja i czas:** none; read projection with ETag/as_of; read_only_no_side_effect; logical_at=server_as_of; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** Brak zdarzenia domenowego i outbox z odczytu.
- **UI i panel:** Finanse: budżet i podatek; pl/en/de; SVG pod resources/svg/finance/; loading, empty, error, offline; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik finance.value_asset.result etykiety result, reason_code, balance_version bez PII. przeniesienie aktywa, brak podwójnego pozwolenia; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P16-F04 — SnapshotTaxBase

- **Aktor/polityka:** system_worker_lease_fencing; cudzy obiekt → 404; właściciel zapisu Finance. Źródło/warunek: P03/P06/P08/P11/P12/P15.
- **Interface:** internal:Finance.SnapshotTaxBase; company, date_utc → immutable tax_base. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - net_worth_snapshots [Finance]: company_id BIGINT UNSIGNED NULL; cash_minor BIGINT NOT NULL; asset_minor BIGINT NOT NULL; liability_minor BIGINT NOT NULL; taxable_minor BIGINT NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | ponowny snapshot zwraca ten sam hash, korekta append-only; Clock UTC stable_domain_slot | 00:00 UTC; kwoty rzeczowe i wierzytelności raz; zawiera aktywa i zapasy według OWNERSHIP | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→11→14/15→19→20→40–120→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** finance.snapshot_tax_base.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Banking, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Finanse: budżet i podatek; pl/en/de; SVG pod resources/svg/finance/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik finance.snapshot_tax_base.result etykiety result, reason_code, balance_version bez PII. granica północy, ropa w trasie, escrow; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P16-F05 — AssessWealthTax

- **Aktor/polityka:** system_worker_lease_fencing; cudzy obiekt → 404; właściciel zapisu Finance. Źródło/warunek: P03/P06/P08/P11/P12/P15.
- **Interface:** internal:Finance.AssessWealthTax; snapshot_id, policy_version → assessment_minor. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - wealth_tax_assessments [Finance]: company_id BIGINT UNSIGNED NULL; snapshot_id BIGINT UNSIGNED NOT NULL; tax_minor BIGINT UNSIGNED NOT NULL; ledger_reference CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | niezatwierdzona polityka → POLICY_NOT_APPROVED; Clock UTC stable_domain_slot | marginalnie od nadwyżki, post Ledger raz; tarcza/tier nie używa dyskonta podatkowego | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→11→14/15→19→20→40–120→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** finance.assess_wealth_tax.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Banking, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Finanse: budżet i podatek; pl/en/de; SVG pod resources/svg/finance/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik finance.assess_wealth_tax.result etykiety result, reason_code, balance_version bez PII. 200M/400M/1,2B i retry; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.


## P17

### P17-F01 — EvaluateCreditScore

- **Aktor/polityka:** banking.read; cudzy obiekt → 404; właściciel zapisu Banking. Źródło/warunek: P15/P16.
- **Interface:** GET /api/v1/banking/credit-score; company, as_of → score/tier/breakdown. Odpowiedź i kody FORBIDDEN, NOT_FOUND, RATE_LIMITED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - bank_trust_scores [Banking]: company_id BIGINT UNSIGNED NULL; score SMALLINT UNSIGNED NOT NULL; score_version VARCHAR(24) NOT NULL; calculated_at DATETIME(3) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
  - credit_score_history [Banking]: company_id BIGINT UNSIGNED NULL; score_id BIGINT UNSIGNED NOT NULL; prior_score SMALLINT UNSIGNED NOT NULL; new_score SMALLINT UNSIGNED NOT NULL; reason_code VARCHAR(40) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | bez zmiany | brak historii =40, bez arbitralnego neutral=100; Clock UTC only_if_scheduled | credibility35%, history35%, solvency20%, tenure10%, bonus clean cap15; cap bankructwo/late/egzekucja | Brak zmian. |

- **Transakcja i czas:** none; read projection with ETag/as_of; read_only_no_side_effect; logical_at=server_as_of; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** Brak zdarzenia domenowego i outbox z odczytu.
- **UI i panel:** Bank: kredyt i egzekucja; pl/en/de; SVG pod resources/svg/banking/; loading, empty, error, offline; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik banking.evaluate_credit_score.result etykiety result, reason_code, balance_version bez PII. start48,5, bonus i aktywne late; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P17-F02 — ApplyLoan

- **Aktor/polityka:** banking.manage; cudzy obiekt → 404; właściciel zapisu Banking. Źródło/warunek: P15/P16.
- **Interface:** POST /api/v1/banking/loans; company, amount_minor, term_days, expected_version → loan/payment plan. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - loans [Banking]: company_id BIGINT UNSIGNED NULL; principal_minor BIGINT UNSIGNED NOT NULL; outstanding_minor BIGINT UNSIGNED NOT NULL; apr_basis_points INT UNSIGNED NOT NULL; term_days SMALLINT UNSIGNED NOT NULL; accepted_at DATETIME(3) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
  - loan_installments [Banking]: company_id BIGINT UNSIGNED NULL; loan_id BIGINT UNSIGNED NOT NULL; sequence_no INT UNSIGNED NOT NULL; due_at DATETIME(3) NOT NULL; principal_minor BIGINT UNSIGNED NOT NULL; interest_minor BIGINT UNSIGNED NOT NULL; paid_minor BIGINT UNSIGNED NOT NULL DEFAULT 0; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | brak zdolności, wakat dyrektora lub dług → odmowa; Clock UTC only_if_scheduled | eligible → approved/funded; tier cap i stawka z YAML, BoardAccess finance; Ledger raz | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→12→20→30→40–120→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** banking.apply_loan.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Bank: kredyt i egzekucja; pl/en/de; SVG pod resources/svg/banking/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik banking.apply_loan.result etykiety result, reason_code, balance_version bez PII. dwa wnioski pod limitem, retry funding; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P17-F03 — PayInstallment

- **Aktor/polityka:** banking.manage; cudzy obiekt → 404; właściciel zapisu Banking. Źródło/warunek: P15/P16.
- **Interface:** POST /api/v1/banking/loans/{id}/payments; loan_id, due_slot, amount_minor → paid/late. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - loan_installments [Banking]: company_id BIGINT UNSIGNED NULL; loan_id BIGINT UNSIGNED NOT NULL; sequence_no INT UNSIGNED NOT NULL; due_at DATETIME(3) NOT NULL; principal_minor BIGINT UNSIGNED NOT NULL; interest_minor BIGINT UNSIGNED NOT NULL; paid_minor BIGINT UNSIGNED NOT NULL DEFAULT 0; …
  - loans [Banking]: company_id BIGINT UNSIGNED NULL; principal_minor BIGINT UNSIGNED NOT NULL; outstanding_minor BIGINT UNSIGNED NOT NULL; apr_basis_points INT UNSIGNED NOT NULL; term_days SMALLINT UNSIGNED NOT NULL; accepted_at DATETIME(3) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | brak środków → late bez ujemnego salda; Clock UTC only_if_scheduled | PLN_BANK → wierzyciel raz; late nalicza +150% stopy bazowej i trust −5/tydzień | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→12→20→30→40–120→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** banking.pay_installment.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Bank: kredyt i egzekucja; pl/en/de; SVG pod resources/svg/banking/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik banking.pay_installment.result etykiety result, reason_code, balance_version bez PII. deadline kontra wpłata, FTS raz; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P17-F04 — StartEnforcement

- **Aktor/polityka:** system_worker_lease_fencing; cudzy obiekt → 404; właściciel zapisu Banking. Źródło/warunek: P15/P16.
- **Interface:** internal:Banking.StartEnforcement; obligation, logical_at → proceeding/phase. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - bailiff_proceedings [Banking]: company_id BIGINT UNSIGNED NULL; obligation_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL; phase TINYINT UNSIGNED NOT NULL; started_at DATETIME(3) NOT NULL; next_due_at DATETIME(3) NULL; recovered_minor BIGINT UNSIGNED NOT NULL DEFAULT 0; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
  - obligations [P01-P13 (public contract); kontrakt wcześniejszego pakietu]
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | escrow i zarezerwowana ropa wyłączone; brak długu → no-op; Clock UTC stable_domain_slot | late72h→Faza1; wpływy 50%; po7d bez planu Faza2, potem Faza3 zgodnie z kanonem | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→12→20→30→40–120→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** banking.start_enforcement.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Bank: kredyt i egzekucja; pl/en/de; SVG pod resources/svg/banking/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik banking.start_enforcement.result etykiety result, reason_code, balance_version bez PII. receipt kontra zajęcie, timeout i replay; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P17-F05 — AuctionAsset

- **Aktor/polityka:** banking.manage; cudzy obiekt → 404; właściciel zapisu Banking. Źródło/warunek: P15/P16.
- **Interface:** POST /api/v1/banking/auctions/{id}/bids; proceeding, asset_ref, bid → award/transfer. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - auctions [Banking]: company_id BIGINT UNSIGNED NULL; proceeding_id BIGINT UNSIGNED NOT NULL; asset_type VARCHAR(32) NOT NULL; asset_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL; reserve_minor BIGINT UNSIGNED NOT NULL; winning_bid_minor BIGINT UNSIGNED NULL; closes_at DATETIME(3) NOT NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | niewłaściwy właściciel lub brak prawa kupującego → odmowa; Clock UTC only_if_scheduled | seized → auctioned/transferred; wells/refinery wg kolejności §17.2; procesy i historia zachowane | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→12→20→30→40–120→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** banking.auction_asset.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Bank: kredyt i egzekucja; pl/en/de; SVG pod resources/svg/banking/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik banking.auction_asset.result etykiety result, reason_code, balance_version bez PII. aukcja kontra dostawa/strajk/EPC; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.


## P18

### P18-F01 — PlanSabotage

- **Aktor/polityka:** legal.pvp.manage; cudzy obiekt → 404; właściciel zapisu Legal. Źródło/warunek: P10/P13/P15/P17.
- **Interface:** POST /api/v1/legal/sabotage-operations; attacker, victim, target_ref, operation_code, expected_version → op_id/due_at. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - sabotage_operations [Legal]: company_id BIGINT UNSIGNED NULL; attacker_company_id BIGINT UNSIGNED NOT NULL; victim_company_id BIGINT UNSIGNED NOT NULL; target_type VARCHAR(24) NOT NULL; target_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL; cost_minor BIGINT UNSIGNED NOT NULL; resolve_at DATETIME(3) NOT NULL; …
  - victim_attack_windows [Legal]: company_id BIGINT UNSIGNED NULL; victim_company_id BIGINT UNSIGNED NOT NULL; window_started_at DATETIME(3) NOT NULL; successful_count SMALLINT UNSIGNED NOT NULL DEFAULT 0; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | własna firma, osłona celu, limit ofiary → odmowa/refund wg fazy; Clock UTC only_if_scheduled | planned opłaca koszt PLN_CASH raz; zapisuje seed i termin; sprawdza cooldown | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→12→15→20→40–80→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=persist_seed_and_outcome_once. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** legal.plan_sabotage.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Logistics, Notifications, Audit; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Prawo: sabotaż i ochrona; pl/en/de; SVG pod resources/svg/legal/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik legal.plan_sabotage.result etykiety result, reason_code, balance_version bez PII. dwa ataki na granicy5/7dni; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P18-F02 — ResolveSabotage

- **Aktor/polityka:** system_worker_lease_fencing; cudzy obiekt → 404; właściciel zapisu Legal. Źródło/warunek: P10/P13/P15/P17.
- **Interface:** internal:Legal.ResolveSabotage; op_id, due_slot → succeeded/failed/cancelled. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - sabotage_operations [Legal]: company_id BIGINT UNSIGNED NULL; attacker_company_id BIGINT UNSIGNED NOT NULL; victim_company_id BIGINT UNSIGNED NOT NULL; target_type VARCHAR(24) NOT NULL; target_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL; cost_minor BIGINT UNSIGNED NOT NULL; resolve_at DATETIME(3) NOT NULL; …
  - victim_attack_windows [Legal]: company_id BIGINT UNSIGNED NULL; victim_company_id BIGINT UNSIGNED NOT NULL; window_started_at DATETIME(3) NOT NULL; successful_count SMALLINT UNSIGNED NOT NULL DEFAULT 0; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | replay job/sweeper nie losuje ani nie obciąża ponownie; Clock UTC stable_domain_slot | ponownie sprawdza target/tarcza, trwały RNG; sukces stosuje limit szkody, wpadka −15 cred+100k | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→12→15→20→40–80→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=persist_seed_and_outcome_once. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** legal.resolve_sabotage.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Logistics, Notifications, Audit; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Prawo: sabotaż i ochrona; pl/en/de; SVG pod resources/svg/legal/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik legal.resolve_sabotage.result etykiety result, reason_code, balance_version bez PII. sprzedaż celu, urlop i ochrona tuż przed due; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P18-F03 — BuyProtection

- **Aktor/polityka:** legal.pvp.manage; cudzy obiekt → 404; właściciel zapisu Legal. Źródło/warunek: P10/P13/P15/P17.
- **Interface:** POST /api/v1/legal/protections; module, asset_ref, quoted_cost_minor → policy/effective_at. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - protection_policies [Legal]: company_id BIGINT UNSIGNED NULL; target_type VARCHAR(24) NOT NULL; target_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL; tier_code VARCHAR(20) NOT NULL; premium_minor BIGINT UNSIGNED NOT NULL; starts_at DATETIME(3) NOT NULL; ends_at DATETIME(3) NOT NULL; …
  - protection_activations [Legal]: company_id BIGINT UNSIGNED NULL; policy_id BIGINT UNSIGNED NOT NULL; attack_id BIGINT UNSIGNED NOT NULL; prevented TINYINT(1) NOT NULL; resolved_at DATETIME(3) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | niewystarczające środki albo brak dyrektora dla konwoju → odmowa; Clock UTC only_if_scheduled | basic/armed jeden kurs; drony/hub 7dni od następnego sweepa; Ledger i wersja polityki raz | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→12→15→20→40–80→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** legal.buy_protection.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Logistics, Notifications, Audit; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Prawo: sabotaż i ochrona; pl/en/de; SVG pod resources/svg/legal/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik legal.buy_protection.result etykiety result, reason_code, balance_version bez PII. ochrona kupiona po starcie ataku nie działa wstecz; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P18-F04 — InvestigateAbuse

- **Aktor/polityka:** GM_or_moderator_2FA_audited; cudzy obiekt → 404; właściciel zapisu Legal. Źródło/warunek: P10/P13/P15/P17.
- **Interface:** internal:Legal.InvestigateAbuse; report_id, evidence_refs, GM decision → audited outcome. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - abuse_reports [Legal]: company_id BIGINT UNSIGNED NULL; reporter_user_id BIGINT UNSIGNED NOT NULL; subject_company_id BIGINT UNSIGNED NOT NULL; reason_code VARCHAR(40) NOT NULL; evidence_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL; reviewed_by_user_id BIGINT UNSIGNED NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | bez audytu/bez capability → odmowa; Clock UTC stable_domain_slot | raport → reviewing/resolved; decyzja zapisuje audit i ewentualną sankcję przez ownera | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→12→15→20→40–80→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** legal.investigate_abuse.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Logistics, Notifications, Audit; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Prawo: sabotaż i ochrona; pl/en/de; SVG pod resources/svg/legal/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik legal.investigate_abuse.result etykiety result, reason_code, balance_version bez PII. cross-company data, spam report, retry GM; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.


## P19

### P19-F01 — StartEpcBuild

- **Aktor/polityka:** refining.manage; cudzy obiekt → 404; właściciel zapisu Refining. Źródło/warunek: P08/P10/P12/P13/P14/P15/P16/P17.
- **Interface:** POST /api/v1/refining/epc-contracts; plot_id, archetype, quote_version, expected_version → epc_id/stage plan. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - refinery_plots [Refining]: company_id BIGINT UNSIGNED NULL; region_code VARCHAR(24) NOT NULL; plot_code VARCHAR(40) NOT NULL; acquired_minor BIGINT UNSIGNED NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
  - epc_contracts [Refining]: company_id BIGINT UNSIGNED NULL; plot_id BIGINT UNSIGNED NOT NULL; refinery_id BIGINT UNSIGNED NULL; price_minor BIGINT UNSIGNED NOT NULL; discount_ppm INT UNSIGNED NOT NULL; due_at DATETIME(3) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
  - epc_stages [Refining]: company_id BIGINT UNSIGNED NULL; epc_contract_id BIGINT UNSIGNED NOT NULL; sequence_no SMALLINT UNSIGNED NOT NULL; due_at DATETIME(3) NOT NULL; installment_minor BIGINT UNSIGNED NOT NULL; completed_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | limit2 budów, brak środków/pozwolenia → rollback; Clock UTC only_if_scheduled | plot owned/permit valid → signed/building; rata przez Ledger raz, snapshot archetypu i due events | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→11/12→14/15/17→20/21→70→120→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** refining.start_epc_build.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Logistics, Market, Finance; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Rafineria: EPC, receptura i ubezpieczenie; pl/en/de; SVG pod resources/svg/refining/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik refining.start_epc_build.result etykiety result, reason_code, balance_version bez PII. równoległy start, gray F flag, takeover; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P19-F02 — AdvanceBuild

- **Aktor/polityka:** system_worker_lease_fencing; cudzy obiekt → 404; właściciel zapisu Refining. Źródło/warunek: P08/P10/P12/P13/P14/P15/P16/P17.
- **Interface:** internal:Refining.AdvanceBuild; epc_id, due_stage, state_version → stage/result. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - epc_contracts [Refining]: company_id BIGINT UNSIGNED NULL; plot_id BIGINT UNSIGNED NOT NULL; refinery_id BIGINT UNSIGNED NULL; price_minor BIGINT UNSIGNED NOT NULL; discount_ppm INT UNSIGNED NOT NULL; due_at DATETIME(3) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
  - epc_stages [Refining]: company_id BIGINT UNSIGNED NULL; epc_contract_id BIGINT UNSIGNED NOT NULL; sequence_no SMALLINT UNSIGNED NOT NULL; due_at DATETIME(3) NOT NULL; installment_minor BIGINT UNSIGNED NOT NULL; completed_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
  - refineries [Refining]: company_id BIGINT UNSIGNED NULL; plot_id BIGINT UNSIGNED NOT NULL; tier_code VARCHAR(20) NOT NULL; capacity_bbl_day DECIMAL(16,4) NOT NULL; commissioned_at DATETIME(3) NULL; selected_recipe_id BIGINT UNSIGNED NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | brak raty → paused_funds; worker retry no-op; Clock UTC stable_domain_slot | signed/building/paused_funds → next/completed; exact due_at, cap kosztu, brak reroll | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→11/12→14/15/17→20/21→70→120→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=persist_seed_and_outcome_once. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** refining.advance_build.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Logistics, Market, Finance; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Rafineria: EPC, receptura i ubezpieczenie; pl/en/de; SVG pod resources/svg/refining/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik refining.advance_build.result etykiety result, reason_code, balance_version bez PII. awaria job, aukcja w budowie, drugi worker; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P19-F03 — SelectRecipe

- **Aktor/polityka:** refining.manage; cudzy obiekt → 404; właściciel zapisu Refining. Źródło/warunek: P08/P10/P12/P13/P14/P15/P16/P17.
- **Interface:** PUT /api/v1/refining/refineries/{id}/recipe; refinery_id, recipe_code, effective_from, demand_snapshot, expected_version → selected recipe. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - refinery_recipe_versions [Refining]: company_id BIGINT UNSIGNED NULL; refinery_id BIGINT UNSIGNED NOT NULL; recipe_code VARCHAR(32) NOT NULL; recipe_version VARCHAR(24) NOT NULL; demand_snapshot_at DATETIME(3) NOT NULL; contract_snapshot_hash BINARY(32) NOT NULL; effective_at DATETIME(3) NOT NULL; …
  - refineries [Refining]: company_id BIGINT UNSIGNED NULL; plot_id BIGINT UNSIGNED NOT NULL; tier_code VARCHAR(20) NOT NULL; capacity_bbl_day DECIMAL(16,4) NOT NULL; commissioned_at DATETIME(3) NULL; selected_recipe_id BIGINT UNSIGNED NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | brak wsadu, nielegalna receptura/umowa → odmowa; Clock UTC only_if_scheduled | jawny wybór A/B/C na kolejny segment; podgląd popytu i kontraktów, ale nie automatyczna zmiana bez akcji | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→11/12→14/15/17→20/21→70→120→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** refining.select_recipe.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Logistics, Market, Finance; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Rafineria: EPC, receptura i ubezpieczenie; pl/en/de; SVG pod resources/svg/refining/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik refining.select_recipe.result etykiety result, reason_code, balance_version bez PII. zmiana w połowie segmentu, kontrakt jet/RARS; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P19-F04 — SettleRefinery

- **Aktor/polityka:** system_worker_lease_fencing; cudzy obiekt → 404; właściciel zapisu Refining. Źródło/warunek: P08/P10/P12/P13/P14/P15/P16/P17.
- **Interface:** internal:Refining.SettleRefinery; refinery_id, from,to,balance_version → oil/product movements, OPEX/FTS refs. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - refinery_runs [Refining]: company_id BIGINT UNSIGNED NULL; refinery_id BIGINT UNSIGNED NOT NULL; recipe_id BIGINT UNSIGNED NOT NULL; slot_at DATETIME(3) NOT NULL; input_bbl DECIMAL(16,4) NOT NULL; output_bbl DECIMAL(16,4) NOT NULL; flare_bbl DECIMAL(16,4) NOT NULL; …
  - product_movements [Refining, append-only]: refinery_id, refinery_run_id, sweep_no, movement_type, product_type, volume_bbl DECIMAL(16,4), quality_score DECIMAL(5,2), idempotency_key UNIQUE, typed tank refs; pełne typy w DDL.
  - refineries [Refining]: company_id BIGINT UNSIGNED NULL; plot_id BIGINT UNSIGNED NOT NULL; tier_code VARCHAR(20) NOT NULL; capacity_bbl_day DECIMAL(16,4) NOT NULL; commissioned_at DATETIME(3) NULL; selected_recipe_id BIGINT UNSIGNED NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | strike/awaria/zmiana właściciela segmentuje czas; Clock UTC stable_domain_slot | faza2.9 przerób i flara max2h, faza5 FTS; produkty tworzone tylko z realnego wsadu, pełny zbiornik → standby | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→11/12→14/15/17→20/21→70→120→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** refining.settle_refinery.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Logistics, Market, Finance; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Rafineria: EPC, receptura i ubezpieczenie; pl/en/de; SVG pod resources/svg/refining/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik refining.settle_refinery.result etykiety result, reason_code, balance_version bez PII. długi Δt, brak double OPEX, receptury i jakość; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P19-F05 — SubmitInsuranceClaim

- **Aktor/polityka:** refining.manage; cudzy obiekt → 404; właściciel zapisu Refining. Źródło/warunek: P08/P10/P12/P13/P14/P15/P16/P17.
- **Interface:** POST /api/v1/refining/claims; incident_id, policy_id, evidence_refs → claim/status. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - insurance_claims [Refining]: company_id BIGINT UNSIGNED NULL; policy_id BIGINT UNSIGNED NOT NULL; incident_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL; gross_minor BIGINT UNSIGNED NOT NULL; paid_minor BIGINT UNSIGNED NOT NULL DEFAULT 0; filed_at DATETIME(3) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
  - insurance_policies [Refining]: company_id BIGINT UNSIGNED NULL; asset_type VARCHAR(32) NOT NULL; asset_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL; deductible_minor BIGINT UNSIGNED NOT NULL; daily_cap_minor BIGINT UNSIGNED NOT NULL; starts_at DATETIME(3) NOT NULL; ends_at DATETIME(3) NOT NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | duplikat szkody albo brak polisy → odmowa; Clock UTC only_if_scheduled | reported→validated→queued/paid; deductible przed cap50M/dobę i saldem pool, malus raz | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→11/12→14/15/17→20/21→70→120→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** refining.submit_insurance_claim.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Logistics, Market, Finance; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Rafineria: EPC, receptura i ubezpieczenie; pl/en/de; SVG pod resources/svg/refining/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik refining.submit_insurance_claim.result etykiety result, reason_code, balance_version bez PII. wiele szkód tego dnia, wypłata po retry; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.


## P20

### P20-F01 — CreateOrder

- **Aktor/polityka:** monetization.purchase; cudzy obiekt → 404; właściciel zapisu Monetization. Źródło/warunek: P02/P03/P05/P06/P17.
- **Interface:** POST /api/v1/monetization/orders; product_code, channel, client_key → order_id/payment_intent. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - payment_orders [Monetization]: user_id BIGINT UNSIGNED NOT NULL; platform VARCHAR(16) NOT NULL; provider VARCHAR(32) NOT NULL; product_code VARCHAR(32) NOT NULL; catalog_version VARCHAR(24) NOT NULL; benefit_snapshot_hash BINARY(32) NOT NULL; promo_code_id BIGINT UNSIGNED NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | brak aktywnego produktu/nielegalny kanał → odmowa; Clock UTC only_if_scheduled | draft→pending; cena i benefit z zatwierdzonej wersji katalogu | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10 gdy firma→20 gdy PLN→121→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** monetization.create_order.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Ledger, Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Sklep: zamówienie i GBBL; pl/en/de; SVG pod resources/svg/monetization/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik monetization.create_order.result etykiety result, reason_code, balance_version bez PII. dwa kliknięcia jedna intencja; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P20-F02 — VerifyWebhook

- **Aktor/polityka:** signed_provider_raw_body; cudzy obiekt → 404; właściciel zapisu Monetization. Źródło/warunek: P02/P03/P05/P06/P17.
- **Interface:** POST /api/v1/payments/webhooks/{provider}; provider_event_id, signature, payload_hash → accepted/no-op. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - payment_events [Monetization]: order_id BIGINT UNSIGNED NULL; provider VARCHAR(32) NOT NULL; provider_event_id VARCHAR(128) NOT NULL; event_type VARCHAR(32) NOT NULL; payload_sha256 BINARY(32) NOT NULL; received_at DATETIME(3) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
  - payment_transactions [Monetization]: order_id BIGINT UNSIGNED NOT NULL; event_id BIGINT UNSIGNED NOT NULL; provider VARCHAR(32) NOT NULL; provider_transaction_id VARCHAR(128) NOT NULL; amount_minor BIGINT UNSIGNED NOT NULL; settled_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | błędny podpis/kwota/waluta → reject bez benefitów; Clock UTC only_if_scheduled | pending→paid/failed/refunded według adaptera; zapisz surowy id i zredagowany audit raz | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10 gdy firma→20 gdy PLN→121→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** monetization.verify_webhook.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Ledger, Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Sklep: zamówienie i GBBL; pl/en/de; SVG pod resources/svg/monetization/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik monetization.verify_webhook.result etykiety result, reason_code, balance_version bez PII. out-of-order i duplikat eventu; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P20-F03 — GrantGbbl

- **Aktor/polityka:** system_worker_lease_fencing; cudzy obiekt → 404; właściciel zapisu Monetization. Źródło/warunek: P02/P03/P05/P06/P17.
- **Interface:** internal:Monetization.GrantGbbl; paid_order_id, receipt_ref → grant_id/balance. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - gbbl_ledger [Monetization]: user_id BIGINT UNSIGNED NOT NULL; transaction_key VARCHAR(128) NOT NULL; fulfillment_key VARCHAR(128) NULL; delta_units BIGINT NOT NULL; reason_code VARCHAR(32) NOT NULL; order_id BIGINT UNSIGNED NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
  - premium_entitlements [Monetization]: user_id BIGINT UNSIGNED NOT NULL; entitlement_code VARCHAR(32) NOT NULL; starts_at DATETIME(3) NOT NULL; ends_at DATETIME(3) NULL; source_order_id BIGINT UNSIGNED NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | niepotwierdzona płatność → no-op/odmowa; Clock UTC stable_domain_slot | paid→granted; gbbl_ledger i benefit raz, outbox po commit | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10 gdy firma→20 gdy PLN→121→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** monetization.grant_gbbl.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Ledger, Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Sklep: zamówienie i GBBL; pl/en/de; SVG pod resources/svg/monetization/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik monetization.grant_gbbl.result etykiety result, reason_code, balance_version bez PII. webhook kontra retry/reversal; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P20-F04 — ConvertGbbl

- **Aktor/polityka:** monetization.purchase; cudzy obiekt → 404; właściciel zapisu Monetization. Źródło/warunek: P02/P03/P05/P06/P17.
- **Interface:** POST /api/v1/monetization/gbbl/conversions; company_id, amount, expected_version → conversion_id/cash_minor. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - gbbl_conversions [Monetization]: user_id BIGINT UNSIGNED NOT NULL; units BIGINT UNSIGNED NOT NULL; cash_minor BIGINT UNSIGNED NOT NULL; rate_version VARCHAR(24) NOT NULL; ledger_reference CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
  - gbbl_accounts [Monetization]: user_id BIGINT UNSIGNED NOT NULL; available_units BIGINT NOT NULL DEFAULT 0; recovery_debt_units BIGINT UNSIGNED NOT NULL DEFAULT 0; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | brak GBBL/nieaktywny balans → odmowa; Clock UTC only_if_scheduled | GBBL debit→PLN game FTS, drip30d/tier_base; blokada recovery/faza2 egzekucji | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10 gdy firma→20 gdy PLN→121→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** monetization.convert_gbbl.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Ledger, Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Sklep: zamówienie i GBBL; pl/en/de; SVG pod resources/svg/monetization/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik monetization.convert_gbbl.result etykiety result, reason_code, balance_version bez PII. równoległe konwersje, tier północ; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P20-F05 — ApplyChargeback

- **Aktor/polityka:** system_worker_lease_fencing; cudzy obiekt → 404; właściciel zapisu Monetization. Źródło/warunek: P02/P03/P05/P06/P17.
- **Interface:** internal:Monetization.ApplyChargeback; payment_event_id, order_id → chargeback/debt. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - payment_events [Monetization]: order_id BIGINT UNSIGNED NULL; provider VARCHAR(32) NOT NULL; provider_event_id VARCHAR(128) NOT NULL; event_type VARCHAR(32) NOT NULL; payload_sha256 BINARY(32) NOT NULL; received_at DATETIME(3) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
  - gbbl_accounts [Monetization]: user_id BIGINT UNSIGNED NOT NULL; available_units BIGINT NOT NULL DEFAULT 0; recovery_debt_units BIGINT UNSIGNED NOT NULL DEFAULT 0; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | duplikat eventu bez drugiego długu; Clock UTC stable_domain_slot | granted→charged_back; historia immutable, dług GBBL i blokada zakupów/kroplówki | Po commit wyłącznie nowy wpis kompensujący GBBL/FTS i dług recovery; bez kasowania historii. |

- **Transakcja i czas:** 10 gdy firma→20 gdy PLN→121→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** monetization.apply_chargeback.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Ledger, Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Sklep: zamówienie i GBBL; pl/en/de; SVG pod resources/svg/monetization/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik monetization.apply_chargeback.result etykiety result, reason_code, balance_version bez PII. zużyty benefit, refund po grant, retry; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.


## P21

### P21-F01 — SendMessage

- **Aktor/polityka:** active_thread_member; cudzy obiekt → 404; właściciel zapisu Social. Źródło/warunek: P02/P04/P06/P18.
- **Interface:** POST /api/v1/social/threads/{id}/messages; thread_id, text, client_key → message_id. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - chat_threads [Social]: kind VARCHAR(20) NOT NULL; subject_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL; created_by_user_id BIGINT UNSIGNED NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
  - chat_messages [Social]: thread_id BIGINT UNSIGNED NOT NULL; sender_user_id BIGINT UNSIGNED NOT NULL; body_text TEXT NOT NULL; edited_at DATETIME(3) NULL; redacted_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
- **Uzupełnienie danych:** chat_thread_members [Social] jest sprawdzane przy każdym wysłaniu wiadomości.
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | cudzy kanał/ban/rate limit → odmowa; Clock UTC only_if_scheduled | member→posted; sanitize i ograniczenie długości, outbox po commit | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→110 gdy kontrakt→122→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** social.send_message.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Contracts, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Czat, dyplomacja i kartel; pl/en/de; SVG pod resources/svg/social/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik social.send_message.result etykiety result, reason_code, balance_version bez PII. dwa urządzenia, replay, XSS; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P21-F02 — JoinCartel

- **Aktor/polityka:** social.manage; cudzy obiekt → 404; właściciel zapisu Social. Źródło/warunek: P02/P04/P06/P18.
- **Interface:** POST /api/v1/social/cartels/{id}/members; cartel_id, expected_version → membership. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - cartels [Social]: company_id BIGINT UNSIGNED NULL; name VARCHAR(80) NOT NULL; founder_company_id BIGINT UNSIGNED NOT NULL; invite_policy VARCHAR(20) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
  - cartel_memberships [Social]: company_id BIGINT UNSIGNED NULL; cartel_id BIGINT UNSIGNED NOT NULL; member_company_id BIGINT UNSIGNED NOT NULL; joined_at DATETIME(3) NOT NULL; left_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | brak prawa/pełny kartel → 409; Clock UTC only_if_scheduled | eligible→active; max5 członków i limit produkcji40%, relacja B2B od effective_at | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→110 gdy kontrakt→122→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** social.join_cartel.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Contracts, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Czat, dyplomacja i kartel; pl/en/de; SVG pod resources/svg/social/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik social.join_cartel.result etykiety result, reason_code, balance_version bez PII. równoległe zaproszenia i limit40%; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P21-F03 — LeaveCartel

- **Aktor/polityka:** social.manage; cudzy obiekt → 404; właściciel zapisu Social. Źródło/warunek: P02/P04/P06/P18.
- **Interface:** DELETE /api/v1/social/cartels/{id}/members/me; membership_id → ended_at. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - cartel_memberships [Social]: company_id BIGINT UNSIGNED NULL; cartel_id BIGINT UNSIGNED NOT NULL; member_company_id BIGINT UNSIGNED NOT NULL; joined_at DATETIME(3) NOT NULL; left_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | retry → ten sam wynik; Clock UTC only_if_scheduled | active→left; przyszłe prowizje wg snapshot, stare rozliczenia bez zmian | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→110 gdy kontrakt→122→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** social.leave_cartel.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Contracts, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Czat, dyplomacja i kartel; pl/en/de; SVG pod resources/svg/social/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik social.leave_cartel.result etykiety result, reason_code, balance_version bez PII. B2B w locie, fee collar; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P21-F04 — ModerateMessage

- **Aktor/polityka:** GM_or_moderator_2FA_audited; cudzy obiekt → 404; właściciel zapisu Social. Źródło/warunek: P02/P04/P06/P18.
- **Interface:** internal:Social.ModerateMessage; message_id, decision, reason_code → moderation_record. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - chat_messages [Social]: thread_id BIGINT UNSIGNED NOT NULL; sender_user_id BIGINT UNSIGNED NOT NULL; body_text TEXT NOT NULL; edited_at DATETIME(3) NULL; redacted_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
  - moderation_reports [Social]: message_id BIGINT UNSIGNED NOT NULL; reporter_user_id BIGINT UNSIGNED NOT NULL; reason_code VARCHAR(40) NOT NULL; decision_code VARCHAR(40) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | moderator bez scope →403, brak cichego UPDATE treści; Clock UTC stable_domain_slot | visible→hidden/redacted z trwałą historią, osobny audit | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→110 gdy kontrakt→122→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** social.moderate_message.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Contracts, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Czat, dyplomacja i kartel; pl/en/de; SVG pod resources/svg/social/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik social.moderate_message.result etykiety result, reason_code, balance_version bez PII. appeal, data retention, race; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P21-F05 — AuthorizeChannel

- **Aktor/polityka:** system_worker_lease_fencing; cudzy obiekt → 404; właściciel zapisu Social. Źródło/warunek: P02/P04/P06/P18.
- **Interface:** internal:Social.AuthorizeChannel; actor, channel, connection_epoch → grant/deny. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - channel_grants [Social]: user_id BIGINT UNSIGNED NOT NULL; channel_key VARCHAR(128) NOT NULL; granted_at DATETIME(3) NOT NULL; revoked_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | stara sesja/ban → disconnect; Clock UTC stable_domain_slot | scope po logowaniu i przy każdej rewokacji; WS bez payload prywatnych danych po zmianie ról | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→110 gdy kontrakt→122→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** social.authorize_channel.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Contracts, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Czat, dyplomacja i kartel; pl/en/de; SVG pod resources/svg/social/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik social.authorize_channel.result etykiety result, reason_code, balance_version bez PII. otwarty WebSocket po revokacji; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.


## P22

### P22-F01 — AdvanceTutorial

- **Aktor/polityka:** system_worker_lease_fencing; cudzy obiekt → 404; właściciel zapisu Onboarding. Źródło/warunek: P02/P04/P06/P11/P12/P14.
- **Interface:** internal:Onboarding.AdvanceTutorial; company_id, step_code, source_event → progress/reward. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - tutorial_progress [Onboarding]: user_id BIGINT UNSIGNED NOT NULL; step_code VARCHAR(64) NOT NULL; completed_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | pusty event/obca firma → odmowa; Clock UTC stable_domain_slot | pending→complete raz po domenowym dowodzie; etap szkoleniowego odwiertu i logistyki według §27.1a | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10 gdy firma→20/121 gdy nagroda→123→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** onboarding.advance_tutorial.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Samouczek i centrum powiadomień; pl/en/de; SVG pod resources/svg/onboarding/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik onboarding.advance_tutorial.result etykiety result, reason_code, balance_version bez PII. dwa wejścia i ponowny start firmy; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P22-F02 — GrantAchievement

- **Aktor/polityka:** system_worker_lease_fencing; cudzy obiekt → 404; właściciel zapisu Onboarding. Źródło/warunek: P02/P04/P06/P11/P12/P14.
- **Interface:** internal:Onboarding.GrantAchievement; company, achievement_code, evidence_id → grant. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - achievement_grants [Onboarding]: user_id BIGINT UNSIGNED NOT NULL; achievement_code VARCHAR(64) NOT NULL; granted_at DATETIME(3) NOT NULL; source_event_key VARCHAR(128) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | stary event replay → no-op; Clock UTC stable_domain_slot | not_granted→granted; reward przez Ledger/GBBL raz | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10 gdy firma→20/121 gdy nagroda→123→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** onboarding.grant_achievement.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Samouczek i centrum powiadomień; pl/en/de; SVG pod resources/svg/onboarding/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik onboarding.grant_achievement.result etykiety result, reason_code, balance_version bez PII. exactly once reward; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P22-F03 — DeliverNotification

- **Aktor/polityka:** system_worker_lease_fencing; cudzy obiekt → 404; właściciel zapisu Onboarding. Źródło/warunek: P02/P04/P06/P11/P12/P14.
- **Interface:** internal:Onboarding.DeliverNotification; event_id, recipient_id, channel → delivery result. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - notifications [Onboarding]: user_id BIGINT UNSIGNED NOT NULL; template_key VARCHAR(128) NOT NULL; locale CHAR(2) NOT NULL; event_key VARCHAR(128) NOT NULL; payload_json JSON NOT NULL; created_at_event DATETIME(3) NOT NULL; read_at DATETIME(3) NULL; …
  - notification_deliveries [Onboarding]: notification_id BIGINT UNSIGNED NOT NULL; channel_code VARCHAR(16) NOT NULL; attempt_no SMALLINT UNSIGNED NOT NULL; next_attempt_at DATETIME(3) NOT NULL; delivered_at DATETIME(3) NULL; last_error_code VARCHAR(40) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | wyłączenie push nie usuwa wpisu in-app; Clock UTC stable_domain_slot | outbox→pending→sent/failed/retry; centrum in-app trwałe, push/email adaptery | Po commit dostawca zewnętrzny: idempotentny provider key; unknown_delivery do uzgodnienia, bez ślepego ponowienia. |

- **Transakcja i czas:** 10 gdy firma→20/121 gdy nagroda→123→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** onboarding.deliver_notification.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Samouczek i centrum powiadomień; pl/en/de; SVG pod resources/svg/onboarding/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik onboarding.deliver_notification.result etykiety result, reason_code, balance_version bez PII. awaria FCM, duplicate job, offline; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P22-F04 — UpdatePreferences

- **Aktor/polityka:** self_user_id; cudzy obiekt → 404; właściciel zapisu Onboarding. Źródło/warunek: P02/P04/P06/P11/P12/P14.
- **Interface:** PUT /api/v1/notifications/preferences; user_id, categories/channels, expected_version → preferences. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - notification_preferences [Onboarding]: user_id BIGINT UNSIGNED NOT NULL; channel_code VARCHAR(16) NOT NULL; category_code VARCHAR(32) NOT NULL; enabled TINYINT(1) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | brak zgody newsletter → nie wysyłaj; Clock UTC only_if_scheduled | CAS; krytyczne systemowe nie zależą od opt-in marketingu | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10 gdy firma→20/121 gdy nagroda→123→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** onboarding.update_preferences.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Samouczek i centrum powiadomień; pl/en/de; SVG pod resources/svg/onboarding/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik onboarding.update_preferences.result etykiety result, reason_code, balance_version bez PII. zmiana zgody w trakcie kolejki; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.


## P23

### P23-F01 — PublishPage

- **Aktor/polityka:** GM_2FA_cms; cudzy obiekt → 404; właściciel zapisu CorePrivacy. Źródło/warunek: P02/P05/P06/P21/P22.
- **Interface:** internal:Core Privacy.PublishPage; draft_id, locale, expected_hash → page_version. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - static_pages [CorePrivacy]: slug VARCHAR(120) NOT NULL; locale CHAR(2) NOT NULL; published_version_id BIGINT UNSIGNED NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
  - page_versions [CorePrivacy]: page_id BIGINT UNSIGNED NOT NULL; version_no INT UNSIGNED NOT NULL; safe_html MEDIUMTEXT NOT NULL; approved_by_user_id BIGINT UNSIGNED NULL; published_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | zabroniony element/konflikt hash → odmowa; Clock UTC stable_domain_slot | draft→published immutable; sanitizacja HTML i bezpieczny preview | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** owner danych→124→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** core_privacy.publish_page.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy ClientDelivery, Audit; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** CMS, prywatność i newsletter; pl/en/de; SVG pod resources/svg/coreprivacy/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik coreprivacy.publish_page.result etykiety result, reason_code, balance_version bez PII. XSS, dwie publikacje; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P23-F02 — PublishPolicy

- **Aktor/polityka:** GM_2FA_cms; cudzy obiekt → 404; właściciel zapisu CorePrivacy. Źródło/warunek: P02/P05/P06/P21/P22.
- **Interface:** internal:Core Privacy.PublishPolicy; policy_version, effective_at → active. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - privacy_policies [CorePrivacy]: policy_code VARCHAR(40) NOT NULL; locale CHAR(2) NOT NULL; version_no INT UNSIGNED NOT NULL; body_html MEDIUMTEXT NOT NULL; effective_at DATETIME(3) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | brak tłumaczenia/akceptacji → odmowa; Clock UTC stable_domain_slot | draft→active; snapshot treści i hash, nowa zgoda przy wymaganej zmianie | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** owner danych→124→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** core_privacy.publish_policy.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy ClientDelivery, Audit; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** CMS, prywatność i newsletter; pl/en/de; SVG pod resources/svg/coreprivacy/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik coreprivacy.publish_policy.result etykiety result, reason_code, balance_version bez PII. wersja historyczna i odwołanie; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P23-F03 — RecordConsent

- **Aktor/polityka:** self_user_id_step_up_for_export_anonymization; cudzy obiekt → 404; właściciel zapisu CorePrivacy. Źródło/warunek: P02/P05/P06/P21/P22.
- **Interface:** POST /api/v1/privacy/consents; user_id, policy_version, purpose, action → consent_event. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - consent_records [CorePrivacy]: user_id BIGINT UNSIGNED NOT NULL; policy_id BIGINT UNSIGNED NOT NULL; purpose_code VARCHAR(64) NOT NULL; granted TINYINT(1) NOT NULL; recorded_at DATETIME(3) NOT NULL; evidence_hash BINARY(32) NOT NULL; source_key VARCHAR(128) NOT NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | obcy użytkownik →403/404; Clock UTC only_if_scheduled | grant/withdraw append-only; preferencje wynikają z ostatniego zdarzenia | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** owner danych→124→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** core_privacy.record_consent.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy ClientDelivery, Audit; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** CMS, prywatność i newsletter; pl/en/de; SVG pod resources/svg/coreprivacy/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik coreprivacy.record_consent.result etykiety result, reason_code, balance_version bez PII. równoległa zgoda i withdrawal; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P23-F04 — ExportPersonalData

- **Aktor/polityka:** self_user_id_step_up_for_export_anonymization; cudzy obiekt → 404; właściciel zapisu CorePrivacy. Źródło/warunek: P02/P05/P06/P21/P22.
- **Interface:** POST /api/v1/privacy/exports; verified_actor, request_id → private archive/ref. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - privacy_requests [CorePrivacy]: user_id BIGINT UNSIGNED NOT NULL; request_type VARCHAR(24) NOT NULL; requested_at DATETIME(3) NOT NULL; due_at DATETIME(3) NOT NULL; completed_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | brak identity step-up → odmowa; Clock UTC only_if_scheduled | requested→prepared→downloaded; chwilowy link, audyt pobrania | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** owner danych→124→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** core_privacy.export_personal_data.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy ClientDelivery, Audit; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** CMS, prywatność i newsletter; pl/en/de; SVG pod resources/svg/coreprivacy/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik coreprivacy.export_personal_data.result etykiety result, reason_code, balance_version bez PII. cudze dane, wygaśnięcie linku; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P23-F05 — AnonymizeUser

- **Aktor/polityka:** self_user_id_step_up_for_export_anonymization; cudzy obiekt → 404; właściciel zapisu CorePrivacy. Źródło/warunek: P02/P05/P06/P21/P22.
- **Interface:** POST /api/v1/privacy/anonymization-requests; approved_request, scope → anonymization_report. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - privacy_requests [CorePrivacy]: user_id BIGINT UNSIGNED NOT NULL; request_type VARCHAR(24) NOT NULL; requested_at DATETIME(3) NOT NULL; due_at DATETIME(3) NOT NULL; completed_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | aktywny obowiązek/hold → kontrolowany stan oczekiwania; Clock UTC only_if_scheduled | pending→completed; PII ukryte/usunięte wg retencji, FTS i historia gospodarcza zachowane | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** owner danych→124→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** core_privacy.anonymize_user.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy ClientDelivery, Audit; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** CMS, prywatność i newsletter; pl/en/de; SVG pod resources/svg/coreprivacy/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik coreprivacy.anonymize_user.result etykiety result, reason_code, balance_version bez PII. rekoncyliacja po anonimizacji; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P23-F06 — SendNewsletter

- **Aktor/polityka:** GM_2FA_cms; cudzy obiekt → 404; właściciel zapisu CorePrivacy. Źródło/warunek: P02/P05/P06/P21/P22.
- **Interface:** internal:Core Privacy.SendNewsletter; campaign_id, audience_snapshot → delivery batch. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - newsletter_campaigns [CorePrivacy]: subject_pl VARCHAR(255) NOT NULL; subject_en VARCHAR(255) NOT NULL; subject_de VARCHAR(255) NOT NULL; safe_html_pl MEDIUMTEXT NOT NULL; safe_html_en MEDIUMTEXT NOT NULL; safe_html_de MEDIUMTEXT NOT NULL; approved_by_user_id BIGINT UNSIGNED NULL; …
  - newsletter_deliveries [CorePrivacy]: campaign_id BIGINT UNSIGNED NOT NULL; user_id BIGINT UNSIGNED NOT NULL; attempt_no SMALLINT UNSIGNED NOT NULL; sent_at DATETIME(3) NULL; provider_message_id VARCHAR(128) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | withdrawn consent → skip; Clock UTC stable_domain_slot | draft→approved→sending→completed; TinyMCE sanitized, zgoda sprawdzona przy wysyłce | Po commit dostawca zewnętrzny: idempotentny provider key; unknown_delivery do uzgodnienia, bez ślepego ponowienia. |

- **Transakcja i czas:** owner danych→124→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** core_privacy.send_newsletter.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy ClientDelivery, Audit; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** CMS, prywatność i newsletter; pl/en/de; SVG pod resources/svg/coreprivacy/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik coreprivacy.send_newsletter.result etykiety result, reason_code, balance_version bez PII. duplikat job, unsubscribe i bounce; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.


## P24

### P24-F01 — SnapshotResource

- **Aktor/polityka:** self_user_authorized_projection; cudzy obiekt → 404; właściciel zapisu ClientDelivery. Źródło/warunek: P05–P23.
- **Interface:** GET /api/v1/game/snapshot; resource_type/id, as_of → server snapshot/version. Odpowiedź i kody FORBIDDEN, NOT_FOUND, RATE_LIMITED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - Bez własnej tabeli; odczyt przez kontrakty właścicieli.
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | bez zmiany | cudzy zasób →404; Clock UTC only_if_scheduled | GET rozlicza przez Settlement, maskuje prywatne pola, ETag/state_version | Brak zmian. |

- **Transakcja i czas:** none; read projection with ETag/as_of; read_only_no_side_effect; logical_at=server_as_of; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** Brak zdarzenia domenowego i outbox z odczytu.
- **UI i panel:** Pulpit Vue 3 / Flutter; pl/en/de; SVG pod resources/svg/clientdelivery/; loading, empty, error, offline; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik clientdelivery.snapshot_resource.result etykiety result, reason_code, balance_version bez PII. web/mobile ten sam wynik; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P24-F02 — ApiClientContract

- **Aktor/polityka:** public_contract_metadata; cudzy obiekt → 404; właściciel zapisu ClientDelivery. Źródło/warunek: P05–P23.
- **Interface:** GET /api/v1/client/contract; OpenAPI package and auth state → generated client binding. Odpowiedź i kody FORBIDDEN, NOT_FOUND, RATE_LIMITED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - client_version_support [ClientDelivery]: platform, min_contract_version, max_contract_version, min_app_version, effective_at (pełne typy/UNIQUE w DDL).
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | bez zmiany | expired token → refresh raz; conflict→sync UI; Clock UTC only_if_scheduled | Bearer 15min/refresh rotation, web cookie+CSRF; brak tokenu w localStorage; retry tylko przy idempotencji | Brak zmian. |

- **Transakcja i czas:** none; read projection with ETag/as_of; read_only_no_side_effect; logical_at=server_as_of; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** Brak zdarzenia domenowego i outbox z odczytu.
- **UI i panel:** Pulpit Vue 3 / Flutter; pl/en/de; SVG pod resources/svg/clientdelivery/; loading, empty, error, offline; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik clientdelivery.api_client_contract.result etykiety result, reason_code, balance_version bez PII. stara wersja klienta, logout/revocation; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P24-F03 — DeepLinkRouting

- **Aktor/polityka:** client_local; cudzy obiekt → 404; właściciel zapisu ClientDelivery. Źródło/warunek: P05–P23.
- **Interface:** lokalny kontrakt Vue/Flutter; app link, auth state, locale → safe screen. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - Bez własnej tabeli; odczyt przez kontrakty właścicieli.
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | bez zmiany | brak dostępu → safe 404, bez ujawniania istnienia; Clock UTC only_if_scheduled | allowlista domen i tras; route to active resource after auth | Brak zmian. |

- **Transakcja i czas:** none; read projection with ETag/as_of; read_only_no_side_effect; logical_at=server_as_of; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** Brak zdarzenia domenowego i outbox z odczytu.
- **UI i panel:** Pulpit Vue 3 / Flutter; pl/en/de; SVG pod resources/svg/clientdelivery/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik clientdelivery.deep_link_routing.result etykiety result, reason_code, balance_version bez PII. Android/iOS, offline, 320–1440 px; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.


## P25

### P25-F01 — BuildReleaseReport

- **Aktor/polityka:** system_worker_lease_fencing; cudzy obiekt → 404; właściciel zapisu Operations. Źródło/warunek: P01–P24.
- **Interface:** internal:Operations.BuildReleaseReport; commit, schema version, balance hash, test artifacts → signed report. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - release_reports [Operations]: release_version VARCHAR(40) NOT NULL; contract_version VARCHAR(24) NOT NULL; balance_version VARCHAR(24) NOT NULL; yaml_sha256 BINARY(32) NOT NULL; status VARCHAR(20) NOT NULL; report_json JSON NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | brak dowodu → blocked; Clock UTC stable_domain_slot | draft→verified; wszystkie bramki i artefakty, bez ręcznego oznaczania PASS | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 5 lease→odczyty publicznych kontraktów→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** operations.build_release_report.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Monitoring, Admin; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Filament: wydanie i monitoring; pl/en/de; SVG pod resources/svg/operations/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik operations.build_release_report.result etykiety result, reason_code, balance_version bez PII. fałszywy green CI kontra real deploy; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P25-F02 — RunReconciliation

- **Aktor/polityka:** system_worker_lease_fencing; cudzy obiekt → 404; właściciel zapisu Operations. Źródło/warunek: P01–P24.
- **Interface:** internal:Operations.RunReconciliation; world snapshot, interval → discrepancy report. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - reconciliation_runs [Operations]: scope_code VARCHAR(32) NOT NULL; slot_at DATETIME(3) NOT NULL; mismatch_count INT UNSIGNED NOT NULL DEFAULT 0; evidence_sha256 BINARY(32) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | wykryta różnica → alert, nie cicha naprawa; Clock UTC stable_domain_slot | read-only źródła FTS, bbl, escrow, GBBL, due/outbox; korekta oddzielną autoryzowaną akcją | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 5 lease→odczyty publicznych kontraktów→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** operations.run_reconciliation.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Monitoring, Admin; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Filament: wydanie i monitoring; pl/en/de; SVG pod resources/svg/operations/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik operations.run_reconciliation.result etykiety result, reason_code, balance_version bez PII. double FTS, orphan due, stock divergence; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P25-F03 — RestoreWorld

- **Aktor/polityka:** system_worker_lease_fencing; cudzy obiekt → 404; właściciel zapisu Operations. Źródło/warunek: P01–P24.
- **Interface:** internal:Operations.RestoreWorld; backup_id, target_env, expected_checksum → restore report. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - restore_reports [Operations]: release_report_id BIGINT UNSIGNED NULL; backup_id VARCHAR(128) NOT NULL; started_at DATETIME(3) NOT NULL; completed_at DATETIME(3) NULL; rpo_seconds INT UNSIGNED NULL; rto_seconds INT UNSIGNED NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | checksum mismatch → stop; Clock UTC stable_domain_slot | test w izolacji, RPO/RTO pomiar, worker fencing i replay idempotentny | Weryfikowany backup i runbook forward recovery; bez cofania części świata. |

- **Transakcja i czas:** 5 lease→odczyty publicznych kontraktów→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** operations.restore_world.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Monitoring, Admin; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Filament: wydanie i monitoring; pl/en/de; SVG pod resources/svg/operations/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik operations.restore_world.result etykiety result, reason_code, balance_version bez PII. przerwana kopia i odtworzenie; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P25-F04 — VerifyRelease

- **Aktor/polityka:** system_worker_lease_fencing; cudzy obiekt → 404; właściciel zapisu Operations. Źródło/warunek: P01–P24.
- **Interface:** internal:Operations.VerifyRelease; release_id, probes, rollback_ref → ready/blocked. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - release_reports [Operations]: release_version VARCHAR(40) NOT NULL; contract_version VARCHAR(24) NOT NULL; balance_version VARCHAR(24) NOT NULL; yaml_sha256 BINARY(32) NOT NULL; status VARCHAR(20) NOT NULL; report_json JSON NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
  - alert_events [Operations]: code VARCHAR(64) NOT NULL; severity VARCHAR(12) NOT NULL; observed_at DATETIME(3) NOT NULL; resolved_at DATETIME(3) NULL; context_json JSON NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | jakikolwiek wymagany FAIL → blocked; Clock UTC stable_domain_slot | health HTTP/queue/DB, migracje, klient, 90dni i inflacja<5% stałego koszyka | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 5 lease→odczyty publicznych kontraktów→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** operations.verify_release.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Monitoring, Admin; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Filament: wydanie i monitoring; pl/en/de; SVG pod resources/svg/operations/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik operations.verify_release.result etykiety result, reason_code, balance_version bez PII. pełny gate P25, rollback drill; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.


## P14

### P14-F06 — ListMarketOffers

- **Aktor/polityka:** market.read; cudzy obiekt → 404; właściciel zapisu Market. Źródło/warunek: P03/P04/P12.
- **Interface:** GET /api/v1/market/offers; region, grade, cursor, limit → paged offers/price. Odpowiedź i kody FORBIDDEN, NOT_FOUND, RATE_LIMITED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - market_offers [Market]: company_id BIGINT UNSIGNED NULL; storage_id BIGINT UNSIGNED NOT NULL; side VARCHAR(8) NOT NULL; quantity_bbl DECIMAL(16,4) NOT NULL; remaining_bbl DECIMAL(16,4) NOT NULL; limit_price_minor BIGINT UNSIGNED NOT NULL; expires_at DATETIME(3) NOT NULL; …
  - price_history [Market]: region_code VARCHAR(24) NOT NULL; price_minor BIGINT UNSIGNED NOT NULL; volume_60m_bbl DECIMAL(16,4) NOT NULL; event_multiplier_ppm INT UNSIGNED NOT NULL; slot_at DATETIME(3) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | bez zmiany | foreign offer private fields hidden; stale cursor→409; Clock UTC only_if_scheduled | read-only snapshot, available volume excludes reservations | Brak zmian. |

- **Transakcja i czas:** none; read projection with ETag/as_of; read_only_no_side_effect; logical_at=server_as_of; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** Brak zdarzenia domenowego i outbox z odczytu.
- **UI i panel:** Rynek: oferta/quote; pl/en/de; SVG pod resources/svg/market/; loading, empty, error, offline; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik market.list_market_offers.result etykiety result, reason_code, balance_version bez PII. pagination, own/foreign, concurrent match; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.


## P15

### P15-F06 — ListContracts

- **Aktor/polityka:** contracts.read; cudzy obiekt → 404; właściciel zapisu Contracts. Źródło/warunek: P03/P04/P12/P14.
- **Interface:** GET /api/v1/contracts; status, cursor, limit → own contracts and next due. Odpowiedź i kody FORBIDDEN, NOT_FOUND, RATE_LIMITED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - contracts [Contracts]: company_id BIGINT UNSIGNED NULL; buyer_company_id BIGINT UNSIGNED NULL; seller_company_id BIGINT UNSIGNED NULL; system_offer_id BIGINT UNSIGNED NULL; b2b_offer_id BIGINT UNSIGNED NULL; kind VARCHAR(16) NOT NULL; quantity_bbl DECIMAL(16,4) NOT NULL; …
  - contract_tranches [Contracts]: company_id BIGINT UNSIGNED NULL; contract_id BIGINT UNSIGNED NOT NULL; sequence_no INT UNSIGNED NOT NULL; quantity_bbl DECIMAL(16,4) NOT NULL; due_at DATETIME(3) NOT NULL; received_bbl DECIMAL(16,4) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
  - escrow_accounts [Contracts]: company_id BIGINT UNSIGNED NULL; contract_id BIGINT UNSIGNED NULL; b2b_offer_id BIGINT UNSIGNED NULL; held_minor BIGINT UNSIGNED NOT NULL DEFAULT 0; released_minor BIGINT UNSIGNED NOT NULL DEFAULT 0; refunded_minor BIGINT UNSIGNED NOT NULL DEFAULT 0; expires_at DATETIME(3) NOT NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | bez zmiany | foreign contract→404; escrow details masked; Clock UTC only_if_scheduled | read-only owner-filtered projection after Settlement | Brak zmian. |

- **Transakcja i czas:** none; read projection with ETag/as_of; read_only_no_side_effect; logical_at=server_as_of; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** Brak zdarzenia domenowego i outbox z odczytu.
- **UI i panel:** Kontrakty: oferta, escrow i spór; pl/en/de; SVG pod resources/svg/contracts/; loading, empty, error, offline; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik contracts.list_contracts.result etykiety result, reason_code, balance_version bez PII. buyer/seller visibility, pagination, due boundary; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.


## P16

### P16-F06 — GetFinanceSummary

- **Aktor/polityka:** finance.read; cudzy obiekt → 404; właściciel zapisu Finance. Źródło/warunek: P03/P04/P06/P07/P13.
- **Interface:** GET /api/v1/finance/summary; company → cash/assets/liabilities/tax snapshot. Odpowiedź i kody FORBIDDEN, NOT_FOUND, RATE_LIMITED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - net_worth_snapshots [Finance]: company_id BIGINT UNSIGNED NULL; cash_minor BIGINT NOT NULL; asset_minor BIGINT NOT NULL; liability_minor BIGINT NOT NULL; taxable_minor BIGINT NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
  - wealth_tax_assessments [Finance]: company_id BIGINT UNSIGNED NULL; snapshot_id BIGINT UNSIGNED NOT NULL; tax_minor BIGINT UNSIGNED NOT NULL; ledger_reference CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
  - asset_valuations [Finance]: company_id BIGINT UNSIGNED NULL; asset_type VARCHAR(32) NOT NULL; asset_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL; gross_minor BIGINT NOT NULL; taxable_minor BIGINT NOT NULL; formula_version VARCHAR(24) NOT NULL; valued_at DATETIME(3) NOT NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | bez zmiany | foreign company→404; Clock UTC only_if_scheduled | read-only as-of snapshot with current shield/Tier valuation | Brak zmian. |

- **Transakcja i czas:** none; read projection with ETag/as_of; read_only_no_side_effect; logical_at=server_as_of; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** Brak zdarzenia domenowego i outbox z odczytu.
- **UI i panel:** Finanse: budżet i podatek; pl/en/de; SVG pod resources/svg/finance/; loading, empty, error, offline; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik finance.get_finance_summary.result etykiety result, reason_code, balance_version bez PII. midnight, refinery discount once, transit oil once; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.


## P17

### P17-F06 — RestructureLoan

- **Aktor/polityka:** banking.manage; cudzy obiekt → 404; właściciel zapisu Banking. Źródło/warunek: P03/P04/P06/P16.
- **Interface:** POST /api/v1/banking/loans/{id}/restructuring; loan, proposed schedule, expected_version → decision. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - loans [Banking]: company_id BIGINT UNSIGNED NULL; principal_minor BIGINT UNSIGNED NOT NULL; outstanding_minor BIGINT UNSIGNED NOT NULL; apr_basis_points INT UNSIGNED NOT NULL; term_days SMALLINT UNSIGNED NOT NULL; accepted_at DATETIME(3) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
  - loan_installments [Banking]: company_id BIGINT UNSIGNED NULL; loan_id BIGINT UNSIGNED NOT NULL; sequence_no INT UNSIGNED NOT NULL; due_at DATETIME(3) NOT NULL; principal_minor BIGINT UNSIGNED NOT NULL; interest_minor BIGINT UNSIGNED NOT NULL; paid_minor BIGINT UNSIGNED NOT NULL DEFAULT 0; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | ineligible phase/insufficient trust→409; Clock UTC only_if_scheduled | eligible→restructured with new immutable installment schedule | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→12→20→30→40–120→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** banking.restructure_loan.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Bank: kredyt i egzekucja; pl/en/de; SVG pod resources/svg/banking/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik banking.restructure_loan.result etykiety result, reason_code, balance_version bez PII. two concurrent requests, sum principal/interest; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P17-F07 — DeclareBankruptcy

- **Aktor/polityka:** banking.manage; cudzy obiekt → 404; właściciel zapisu Banking. Źródło/warunek: P03/P04/P06/P16.
- **Interface:** POST /api/v1/banking/bankruptcy; company, expected_version → proceeding/state. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - bailiff_proceedings [Banking]: company_id BIGINT UNSIGNED NULL; obligation_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL; phase TINYINT UNSIGNED NOT NULL; started_at DATETIME(3) NOT NULL; next_due_at DATETIME(3) NULL; recovered_minor BIGINT UNSIGNED NOT NULL DEFAULT 0; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
  - loans [Banking]: company_id BIGINT UNSIGNED NULL; principal_minor BIGINT UNSIGNED NOT NULL; outstanding_minor BIGINT UNSIGNED NOT NULL; apr_basis_points INT UNSIGNED NOT NULL; term_days SMALLINT UNSIGNED NOT NULL; accepted_at DATETIME(3) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
  - auctions [Banking]: company_id BIGINT UNSIGNED NULL; proceeding_id BIGINT UNSIGNED NOT NULL; asset_type VARCHAR(32) NOT NULL; asset_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL; reserve_minor BIGINT UNSIGNED NOT NULL; winning_bid_minor BIGINT UNSIGNED NULL; closes_at DATETIME(3) NOT NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | escrow/cargo not seized; active dispute→review; Clock UTC only_if_scheduled | eligible→bankruptcy; Core company status via public Action | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→12→20→30→40–120→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** banking.declare_bankruptcy.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Bank: kredyt i egzekucja; pl/en/de; SVG pod resources/svg/banking/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik banking.declare_bankruptcy.result etykiety result, reason_code, balance_version bez PII. parallel transfer, asset snapshots, no history deletion; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P17-F08 — ListLoans

- **Aktor/polityka:** banking.read; cudzy obiekt → 404; właściciel zapisu Banking. Źródło/warunek: P03/P04/P06/P16.
- **Interface:** GET /api/v1/banking/loans; cursor, status → own loans/installments. Odpowiedź i kody FORBIDDEN, NOT_FOUND, RATE_LIMITED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - loans [Banking]: company_id BIGINT UNSIGNED NULL; principal_minor BIGINT UNSIGNED NOT NULL; outstanding_minor BIGINT UNSIGNED NOT NULL; apr_basis_points INT UNSIGNED NOT NULL; term_days SMALLINT UNSIGNED NOT NULL; accepted_at DATETIME(3) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
  - loan_installments [Banking]: company_id BIGINT UNSIGNED NULL; loan_id BIGINT UNSIGNED NOT NULL; sequence_no INT UNSIGNED NOT NULL; due_at DATETIME(3) NOT NULL; principal_minor BIGINT UNSIGNED NOT NULL; interest_minor BIGINT UNSIGNED NOT NULL; paid_minor BIGINT UNSIGNED NOT NULL DEFAULT 0; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | bez zmiany | foreign loan→404; Clock UTC only_if_scheduled | read-only owner-filtered projection | Brak zmian. |

- **Transakcja i czas:** none; read projection with ETag/as_of; read_only_no_side_effect; logical_at=server_as_of; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** Brak zdarzenia domenowego i outbox z odczytu.
- **UI i panel:** Bank: kredyt i egzekucja; pl/en/de; SVG pod resources/svg/banking/; loading, empty, error, offline; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik banking.list_loans.result etykiety result, reason_code, balance_version bez PII. paging and overdue as-of; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.


## P18

### P18-F05 — AttemptBribe

- **Aktor/polityka:** legal.pvp.manage; cudzy obiekt → 404; właściciel zapisu Legal. Źródło/warunek: P03/P06/P10/P13/P17.
- **Interface:** POST /api/v1/legal/bribe-attempts; target_case, amount_minor, expected_version → attempt/due. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - bribe_attempts [Legal]: company_id BIGINT UNSIGNED NOT NULL; target_case_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL; amount_minor BIGINT UNSIGNED NOT NULL; resolve_at DATETIME(3) NOT NULL; rng_seed VARBINARY(32) NOT NULL; result_code VARCHAR(32) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | shield/forbidden target→409; Clock UTC only_if_scheduled | requested→resolved once using persisted seed | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→12→15→20→40–80→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=persist_seed_and_outcome_once. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** legal.attempt_bribe.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Logistics, Notifications, Audit; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Prawo: sabotaż i ochrona; pl/en/de; SVG pod resources/svg/legal/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik legal.attempt_bribe.result etykiety result, reason_code, balance_version bez PII. once-only cost, bribery detection, concurrent case; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P18-F06 — GetProtectionStatus

- **Aktor/polityka:** legal.protection.read; cudzy obiekt → 404; właściciel zapisu Legal. Źródło/warunek: P03/P06/P10/P13/P17.
- **Interface:** GET /api/v1/legal/protections; asset, cursor → own protection/status. Odpowiedź i kody FORBIDDEN, NOT_FOUND, RATE_LIMITED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - protection_policies [Legal]: company_id BIGINT UNSIGNED NULL; target_type VARCHAR(24) NOT NULL; target_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL; tier_code VARCHAR(20) NOT NULL; premium_minor BIGINT UNSIGNED NOT NULL; starts_at DATETIME(3) NOT NULL; ends_at DATETIME(3) NOT NULL; …
  - protection_activations [Legal]: company_id BIGINT UNSIGNED NULL; policy_id BIGINT UNSIGNED NOT NULL; attack_id BIGINT UNSIGNED NOT NULL; prevented TINYINT(1) NOT NULL; resolved_at DATETIME(3) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | bez zmiany | foreign asset→404; Clock UTC only_if_scheduled | read-only projection | Brak zmian. |

- **Transakcja i czas:** none; read projection with ETag/as_of; read_only_no_side_effect; logical_at=server_as_of; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** Brak zdarzenia domenowego i outbox z odczytu.
- **UI i panel:** Prawo: sabotaż i ochrona; pl/en/de; SVG pod resources/svg/legal/; loading, empty, error, offline; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik legal.get_protection_status.result etykiety result, reason_code, balance_version bez PII. expiry boundary and secret attack masking; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.


## P19

### P19-F06 — ListRefineryProducts

- **Aktor/polityka:** refining.read; cudzy obiekt → 404; właściciel zapisu Refining. Źródło/warunek: P03/P08/P10/P12/P13/P14/P15/P16/P17.
- **Interface:** GET /api/v1/refining/products; refinery, cursor → product balances and demand recommendation. Odpowiedź i kody FORBIDDEN, NOT_FOUND, RATE_LIMITED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - refineries [Refining]: company_id BIGINT UNSIGNED NULL; plot_id BIGINT UNSIGNED NOT NULL; tier_code VARCHAR(20) NOT NULL; capacity_bbl_day DECIMAL(16,4) NOT NULL; commissioned_at DATETIME(3) NULL; selected_recipe_id BIGINT UNSIGNED NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
  - refinery_runs [Refining]: company_id BIGINT UNSIGNED NULL; refinery_id BIGINT UNSIGNED NOT NULL; recipe_id BIGINT UNSIGNED NOT NULL; slot_at DATETIME(3) NOT NULL; input_bbl DECIMAL(16,4) NOT NULL; output_bbl DECIMAL(16,4) NOT NULL; flare_bbl DECIMAL(16,4) NOT NULL; …
  - product_movements [Refining]: company_id BIGINT UNSIGNED NULL; refinery_run_id BIGINT UNSIGNED NOT NULL; product_code VARCHAR(32) NOT NULL; quantity_bbl DECIMAL(16,4) NOT NULL; destination_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | bez zmiany | foreign refinery→404; Clock UTC only_if_scheduled | read-only; recommendation server-side | Brak zmian. |

- **Transakcja i czas:** none; read projection with ETag/as_of; read_only_no_side_effect; logical_at=server_as_of; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** Brak zdarzenia domenowego i outbox z odczytu.
- **UI i panel:** Rafineria: EPC, receptura i ubezpieczenie; pl/en/de; SVG pod resources/svg/refining/; loading, empty, error, offline; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik refining.list_refinery_products.result etykiety result, reason_code, balance_version bez PII. recipe snapshot, product storage ownership; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.


## P20

### P20-F06 — VerifyPlatformReceipt

- **Aktor/polityka:** self_user_mobile_store_verification; cudzy obiekt → 404; właściciel zapisu Monetization. Źródło/warunek: P02/P03/P05/P06/P17.
- **Interface:** POST /api/v1/monetization/mobile/receipts; order_id, platform receipt token → verified/pending. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - payment_orders [Monetization]: user_id BIGINT UNSIGNED NOT NULL; platform VARCHAR(16) NOT NULL; provider VARCHAR(32) NOT NULL; product_code VARCHAR(32) NOT NULL; catalog_version VARCHAR(24) NOT NULL; benefit_snapshot_hash BINARY(32) NOT NULL; promo_code_id BIGINT UNSIGNED NULL; …
  - platform_purchase_receipts [Monetization]: user_id BIGINT UNSIGNED NOT NULL; order_id BIGINT UNSIGNED NOT NULL; platform VARCHAR(16) NOT NULL; provider_transaction_id VARCHAR(128) NOT NULL; receipt_hash BINARY(32) NOT NULL; receipt_ciphertext VARBINARY(8192) NOT NULL; verified_at DATETIME(3) NULL; …
  - payment_transactions [Monetization]: order_id BIGINT UNSIGNED NOT NULL; event_id BIGINT UNSIGNED NOT NULL; provider VARCHAR(32) NOT NULL; provider_transaction_id VARCHAR(128) NOT NULL; amount_minor BIGINT UNSIGNED NOT NULL; settled_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | wrong account/product/transaction replay→409; Clock UTC only_if_scheduled | pending→verified only after store server validation | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10 gdy firma→20 gdy PLN→121→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** monetization.verify_platform_receipt.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Ledger, Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Sklep: zamówienie i GBBL; pl/en/de; SVG pod resources/svg/monetization/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik monetization.verify_platform_receipt.result etykiety result, reason_code, balance_version bez PII. Play/IAP adapter replay, delayed store response; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P20-F07 — ApplyRefund

- **Aktor/polityka:** signed_provider_or_GM_2FA; cudzy obiekt → 404; właściciel zapisu Monetization. Źródło/warunek: P02/P03/P05/P06/P17.
- **Interface:** internal:Monetization.ApplyRefund; provider refund event, order, amount → refunded/recovery debt. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - payment_refunds [Monetization]: order_id BIGINT UNSIGNED NOT NULL; provider VARCHAR(32) NOT NULL; provider_refund_id VARCHAR(128) NOT NULL; amount_minor BIGINT UNSIGNED NOT NULL; received_at DATETIME(3) NOT NULL; recovery_units BIGINT UNSIGNED NOT NULL DEFAULT 0; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
  - payment_orders [Monetization]: user_id BIGINT UNSIGNED NOT NULL; platform VARCHAR(16) NOT NULL; provider VARCHAR(32) NOT NULL; product_code VARCHAR(32) NOT NULL; catalog_version VARCHAR(24) NOT NULL; benefit_snapshot_hash BINARY(32) NOT NULL; promo_code_id BIGINT UNSIGNED NULL; …
  - gbbl_accounts [Monetization]: user_id BIGINT UNSIGNED NOT NULL; available_units BIGINT NOT NULL DEFAULT 0; recovery_debt_units BIGINT UNSIGNED NOT NULL DEFAULT 0; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
  - gbbl_ledger [Monetization]: user_id BIGINT UNSIGNED NOT NULL; transaction_key VARCHAR(128) NOT NULL; fulfillment_key VARCHAR(128) NULL; delta_units BIGINT NOT NULL; reason_code VARCHAR(32) NOT NULL; order_id BIGINT UNSIGNED NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | unsigned/unmatched/excess refund→reject; Clock UTC stable_domain_slot | paid/granted→partial_or_full_refund, append compensation once | Po commit wyłącznie nowy wpis kompensujący GBBL/FTS i dług recovery; bez kasowania historii. |

- **Transakcja i czas:** 10 gdy firma→20 gdy PLN→121→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** monetization.apply_refund.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Ledger, Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Sklep: zamówienie i GBBL; pl/en/de; SVG pod resources/svg/monetization/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik monetization.apply_refund.result etykiety result, reason_code, balance_version bez PII. refund after conversion/grant, two refunds, chargeback race; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P20-F08 — RedeemPromoCode

- **Aktor/polityka:** monetization.purchase; cudzy obiekt → 404; właściciel zapisu Monetization. Źródło/warunek: P02/P03/P05/P06/P17.
- **Interface:** POST /api/v1/monetization/promo-redemptions; code, product, expected_version → discounted order quote. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - promo_codes [Monetization]: code_hmac BINARY(32) NOT NULL; product_code VARCHAR(32) NOT NULL; discount_basis_points SMALLINT UNSIGNED NOT NULL; quota_total INT UNSIGNED NOT NULL; quota_used INT UNSIGNED NOT NULL DEFAULT 0; starts_at DATETIME(3) NOT NULL; ends_at DATETIME(3) NOT NULL; …
  - payment_orders [Monetization]: user_id BIGINT UNSIGNED NOT NULL; platform VARCHAR(16) NOT NULL; provider VARCHAR(32) NOT NULL; product_code VARCHAR(32) NOT NULL; catalog_version VARCHAR(24) NOT NULL; benefit_snapshot_hash BINARY(32) NOT NULL; promo_code_id BIGINT UNSIGNED NULL; …
  - promo_redemptions [Monetization]: promo_code_id BIGINT UNSIGNED NOT NULL; user_id BIGINT UNSIGNED NOT NULL; order_id BIGINT UNSIGNED NULL; source_key VARCHAR(128) NOT NULL; reserved_at DATETIME(3) NOT NULL; consumed_at DATETIME(3) NULL; expires_at DATETIME(3) NOT NULL; …
- **Uzupełnienie danych:** promo_redemptions [Monetization] zapisuje source_key, user_id, promo_code_id, order_id i rezerwację quota; DDL ma FK/UNIQUE.
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | expired/owner mismatch/quota exhausted→409; Clock UTC only_if_scheduled | active→reserved→redeemed once with quota CAS | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10 gdy firma→20 gdy PLN→121→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** monetization.redeem_promo_code.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Ledger, Finance, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Sklep: zamówienie i GBBL; pl/en/de; SVG pod resources/svg/monetization/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik monetization.redeem_promo_code.result etykiety result, reason_code, balance_version bez PII. parallel final use, casefold, revoked code; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P20-F09 — GetOrder

- **Aktor/polityka:** self_user_order; cudzy obiekt → 404; właściciel zapisu Monetization. Źródło/warunek: P02/P03/P05/P06/P17.
- **Interface:** GET /api/v1/monetization/orders/{id}; order_id → own status/grant/refund. Odpowiedź i kody FORBIDDEN, NOT_FOUND, RATE_LIMITED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - payment_orders [Monetization]: user_id BIGINT UNSIGNED NOT NULL; platform VARCHAR(16) NOT NULL; provider VARCHAR(32) NOT NULL; product_code VARCHAR(32) NOT NULL; catalog_version VARCHAR(24) NOT NULL; benefit_snapshot_hash BINARY(32) NOT NULL; promo_code_id BIGINT UNSIGNED NULL; …
  - payment_transactions [Monetization]: order_id BIGINT UNSIGNED NOT NULL; event_id BIGINT UNSIGNED NOT NULL; provider VARCHAR(32) NOT NULL; provider_transaction_id VARCHAR(128) NOT NULL; amount_minor BIGINT UNSIGNED NOT NULL; settled_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
  - payment_refunds [Monetization]: order_id BIGINT UNSIGNED NOT NULL; provider VARCHAR(32) NOT NULL; provider_refund_id VARCHAR(128) NOT NULL; amount_minor BIGINT UNSIGNED NOT NULL; received_at DATETIME(3) NOT NULL; recovery_units BIGINT UNSIGNED NOT NULL DEFAULT 0; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | bez zmiany | foreign order→404; Clock UTC only_if_scheduled | read-only projection, no payment secrets | Brak zmian. |

- **Transakcja i czas:** none; read projection with ETag/as_of; read_only_no_side_effect; logical_at=server_as_of; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** Brak zdarzenia domenowego i outbox z odczytu.
- **UI i panel:** Sklep: zamówienie i GBBL; pl/en/de; SVG pod resources/svg/monetization/; loading, empty, error, offline; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik monetization.get_order.result etykiety result, reason_code, balance_version bez PII. web/mobile parity, stale event; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.


## P21

### P21-F06 — CreateCartel

- **Aktor/polityka:** social.manage; cudzy obiekt → 404; właściciel zapisu Social. Źródło/warunek: P02/P04/P06/P18.
- **Interface:** POST /api/v1/social/cartels; name, invite_policy → cartel_id. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - cartels [Social]: company_id BIGINT UNSIGNED NULL; name VARCHAR(80) NOT NULL; founder_company_id BIGINT UNSIGNED NOT NULL; invite_policy VARCHAR(20) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
  - cartel_memberships [Social]: company_id BIGINT UNSIGNED NULL; cartel_id BIGINT UNSIGNED NOT NULL; member_company_id BIGINT UNSIGNED NOT NULL; joined_at DATETIME(3) NOT NULL; left_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | name taken/limit→409; Clock UTC only_if_scheduled | new→active; founder membership once | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→110 gdy kontrakt→122→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** social.create_cartel.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Contracts, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Czat, dyplomacja i kartel; pl/en/de; SVG pod resources/svg/social/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik social.create_cartel.result etykiety result, reason_code, balance_version bez PII. two founders, membership uniqueness; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P21-F07 — CreateThread

- **Aktor/polityka:** authenticated_participant; cudzy obiekt → 404; właściciel zapisu Social. Źródło/warunek: P02/P04/P06/P18.
- **Interface:** POST /api/v1/social/threads; kind, participants, subject → thread_id. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - chat_threads [Social]: kind VARCHAR(20) NOT NULL; subject_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL; created_by_user_id BIGINT UNSIGNED NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
  - channel_grants [Social]: user_id BIGINT UNSIGNED NOT NULL; channel_key VARCHAR(128) NOT NULL; granted_at DATETIME(3) NOT NULL; revoked_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
- **Uzupełnienie danych:** chat_thread_members [Social] wiąże thread_id z user_id i umożliwia rewokację oraz autoryzację SendMessage.
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | private member missing→403; Clock UTC only_if_scheduled | new→open after participant authorization | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→110 gdy kontrakt→122→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** social.create_thread.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Contracts, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Czat, dyplomacja i kartel; pl/en/de; SVG pod resources/svg/social/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik social.create_thread.result etykiety result, reason_code, balance_version bez PII. duplicate direct thread, hidden member; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P21-F08 — SetDiplomacyRelation

- **Aktor/polityka:** social.manage; cudzy obiekt → 404; właściciel zapisu Social. Źródło/warunek: P02/P04/P06/P18.
- **Interface:** PUT /api/v1/social/relations/{id}; target_company, relation_code, expected_version → relation. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - diplomacy_relations [Social]: company_id BIGINT UNSIGNED NULL; other_company_id BIGINT UNSIGNED NOT NULL; relation_code VARCHAR(24) NOT NULL; effective_at DATETIME(3) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | foreign company/self→409; Clock UTC only_if_scheduled | previous→active versioned relation | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→110 gdy kontrakt→122→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** social.set_diplomacy_relation.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Contracts, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Czat, dyplomacja i kartel; pl/en/de; SVG pod resources/svg/social/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik social.set_diplomacy_relation.result etykiety result, reason_code, balance_version bez PII. concurrent updates, historical snapshot; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P21-F09 — ListThreads

- **Aktor/polityka:** active_thread_member; cudzy obiekt → 404; właściciel zapisu Social. Źródło/warunek: P02/P04/P06/P18.
- **Interface:** GET /api/v1/social/threads; cursor, limit → authorized thread list. Odpowiedź i kody FORBIDDEN, NOT_FOUND, RATE_LIMITED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - chat_threads [Social]: kind VARCHAR(20) NOT NULL; subject_public_ulid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL; created_by_user_id BIGINT UNSIGNED NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
  - chat_messages [Social]: thread_id BIGINT UNSIGNED NOT NULL; sender_user_id BIGINT UNSIGNED NOT NULL; body_text TEXT NOT NULL; edited_at DATETIME(3) NULL; redacted_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
  - channel_grants [Social]: user_id BIGINT UNSIGNED NOT NULL; channel_key VARCHAR(128) NOT NULL; granted_at DATETIME(3) NOT NULL; revoked_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
- **Uzupełnienie danych:** chat_thread_members [Social] filtruje listę do aktywnych uczestników.
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | bez zmiany | revoked member→404; Clock UTC only_if_scheduled | read-only, membership checked at query time | Brak zmian. |

- **Transakcja i czas:** none; read projection with ETag/as_of; read_only_no_side_effect; logical_at=server_as_of; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** Brak zdarzenia domenowego i outbox z odczytu.
- **UI i panel:** Czat, dyplomacja i kartel; pl/en/de; SVG pod resources/svg/social/; loading, empty, error, offline; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik social.list_threads.result etykiety result, reason_code, balance_version bez PII. revoke during open WS, no private preview; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P21-F10 — ApplyCartelSanction

- **DOM05:** finalne −15 credibility i −15 B2B bez wtórnej propagacji; właściciel zatwierdził, runtime wymaga opublikowanej wersji polityki.
- **Aktor/polityka:** system_worker_lease_fencing; cudzy obiekt → 404; właściciel zapisu Social. Źródło/warunek: P02/P04/P06/P18.
- **Interface:** internal:Social.ApplyCartelSanction; 3 flags/7d event_id → sanction state. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - cartels [Social]: company_id BIGINT UNSIGNED NULL; name VARCHAR(80) NOT NULL; founder_company_id BIGINT UNSIGNED NOT NULL; invite_policy VARCHAR(20) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
  - cartel_memberships [Social]: company_id BIGINT UNSIGNED NULL; cartel_id BIGINT UNSIGNED NOT NULL; member_company_id BIGINT UNSIGNED NOT NULL; joined_at DATETIME(3) NOT NULL; left_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
- **Uzupełnienie danych:** cartel_trade_flags [Social] przechowuje źródłowe trzy flagi, cartel_sanctions [Social] trwały skutek i 30 dni; obie tabele mają FK i UNIQUE.
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | fewer than 3 flags→no-op; DOM05 approved, POLICY_NOT_APPROVED only until published versioned policy; Clock UTC stable_domain_slot | threshold reached→30d fee exemption suspension; reputation deltas once | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10→110 gdy kontrakt→122→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** social.apply_cartel_sanction.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Contracts, Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Czat, dyplomacja i kartel; pl/en/de; SVG pod resources/svg/social/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik social.apply_cartel_sanction.result etykiety result, reason_code, balance_version bez PII. dedupe flags, 7d boundary, 30d expiry; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P21-F11 — ListCartels

- **Aktor/polityka:** public_safe_read; cudzy obiekt → 404; właściciel zapisu Social. Źródło/warunek: P02/P04/P06/P18.
- **Interface:** GET /api/v1/social/cartels; cursor, region → public cartel directory. Odpowiedź i kody FORBIDDEN, NOT_FOUND, RATE_LIMITED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - cartels [Social]: company_id BIGINT UNSIGNED NULL; name VARCHAR(80) NOT NULL; founder_company_id BIGINT UNSIGNED NOT NULL; invite_policy VARCHAR(20) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
  - cartel_memberships [Social]: company_id BIGINT UNSIGNED NULL; cartel_id BIGINT UNSIGNED NOT NULL; member_company_id BIGINT UNSIGNED NOT NULL; joined_at DATETIME(3) NOT NULL; left_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | bez zmiany | private membership hidden; Clock UTC only_if_scheduled | read-only public-safe projection | Brak zmian. |

- **Transakcja i czas:** none; read projection with ETag/as_of; read_only_no_side_effect; logical_at=server_as_of; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** Brak zdarzenia domenowego i outbox z odczytu.
- **UI i panel:** Czat, dyplomacja i kartel; pl/en/de; SVG pod resources/svg/social/; loading, empty, error, offline; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik social.list_cartels.result etykiety result, reason_code, balance_version bez PII. paging, directory privacy; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.


## P22

### P22-F05 — RegisterDeviceToken

- **Aktor/polityka:** self_user_id; cudzy obiekt → 404; właściciel zapisu Onboarding. Źródło/warunek: P02/P04/P06/P20/P21.
- **Interface:** POST /api/v1/notifications/device-tokens; platform, token, device_id → token registration. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - device_tokens [Onboarding]: user_id BIGINT UNSIGNED NOT NULL; platform VARCHAR(16) NOT NULL; device_hmac BINARY(32) NOT NULL; token_hmac BINARY(32) NOT NULL; token_ciphertext VARBINARY(4096) NOT NULL; revoked_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | token bound to another user→409; Clock UTC only_if_scheduled | absent/rotated→active; logout/revoke→revoked | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10 gdy firma→20/121 gdy nagroda→123→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** onboarding.register_device_token.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Samouczek i centrum powiadomień; pl/en/de; SVG pod resources/svg/onboarding/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik onboarding.register_device_token.result etykiety result, reason_code, balance_version bez PII. rotate/revoke, push privacy, duplicate; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P22-F06 — ListNotifications

- **Aktor/polityka:** self_user_id; cudzy obiekt → 404; właściciel zapisu Onboarding. Źródło/warunek: P02/P04/P06/P20/P21.
- **Interface:** GET /api/v1/notifications; cursor, limit → own notification center. Odpowiedź i kody FORBIDDEN, NOT_FOUND, RATE_LIMITED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - notifications [Onboarding]: user_id BIGINT UNSIGNED NOT NULL; template_key VARCHAR(128) NOT NULL; locale CHAR(2) NOT NULL; event_key VARCHAR(128) NOT NULL; payload_json JSON NOT NULL; created_at_event DATETIME(3) NOT NULL; read_at DATETIME(3) NULL; …
  - notification_preferences [Onboarding]: user_id BIGINT UNSIGNED NOT NULL; channel_code VARCHAR(16) NOT NULL; category_code VARCHAR(32) NOT NULL; enabled TINYINT(1) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | bez zmiany | foreign notification→404; Clock UTC only_if_scheduled | read-only projection | Brak zmian. |

- **Transakcja i czas:** none; read projection with ETag/as_of; read_only_no_side_effect; logical_at=server_as_of; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** Brak zdarzenia domenowego i outbox z odczytu.
- **UI i panel:** Samouczek i centrum powiadomień; pl/en/de; SVG pod resources/svg/onboarding/; loading, empty, error, offline; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik onboarding.list_notifications.result etykiety result, reason_code, balance_version bez PII. paging, locale fallback, preference; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P22-F07 — MarkNotificationRead

- **Aktor/polityka:** self_user_id; cudzy obiekt → 404; właściciel zapisu Onboarding. Źródło/warunek: P02/P04/P06/P20/P21.
- **Interface:** POST /api/v1/notifications/{id}/read; notification_id → read_at. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - notifications [Onboarding]: user_id BIGINT UNSIGNED NOT NULL; template_key VARCHAR(128) NOT NULL; locale CHAR(2) NOT NULL; event_key VARCHAR(128) NOT NULL; payload_json JSON NOT NULL; created_at_event DATETIME(3) NOT NULL; read_at DATETIME(3) NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | foreign notification→404; Clock UTC only_if_scheduled | unread→read once | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** 10 gdy firma→20/121 gdy nagroda→123→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** onboarding.mark_notification_read.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy Notifications; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** Samouczek i centrum powiadomień; pl/en/de; SVG pod resources/svg/onboarding/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik onboarding.mark_notification_read.result etykiety result, reason_code, balance_version bez PII. retry, concurrent read; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P22-F08 — GetTutorialProgress

- **Aktor/polityka:** self_user_id; cudzy obiekt → 404; właściciel zapisu Onboarding. Źródło/warunek: P02/P04/P06/P20/P21.
- **Interface:** GET /api/v1/onboarding/progress; company → steps/rewards. Odpowiedź i kody FORBIDDEN, NOT_FOUND, RATE_LIMITED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - tutorial_progress [Onboarding]: user_id BIGINT UNSIGNED NOT NULL; step_code VARCHAR(64) NOT NULL; completed_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
  - achievement_grants [Onboarding]: user_id BIGINT UNSIGNED NOT NULL; achievement_code VARCHAR(64) NOT NULL; granted_at DATETIME(3) NOT NULL; source_event_key VARCHAR(128) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | bez zmiany | foreign company→404; Clock UTC only_if_scheduled | read-only projection | Brak zmian. |

- **Transakcja i czas:** none; read projection with ETag/as_of; read_only_no_side_effect; logical_at=server_as_of; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** Brak zdarzenia domenowego i outbox z odczytu.
- **UI i panel:** Samouczek i centrum powiadomień; pl/en/de; SVG pod resources/svg/onboarding/; loading, empty, error, offline; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik onboarding.get_tutorial_progress.result etykiety result, reason_code, balance_version bez PII. FTUE state, reward once; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.


## P23

### P23-F07 — PublishNavigation

- **Aktor/polityka:** GM_2FA_cms; cudzy obiekt → 404; właściciel zapisu CorePrivacy. Źródło/warunek: P02/P05/P06/P21/P22.
- **Interface:** internal:CorePrivacy.PublishNavigation; draft menu version/hash → published navigation. Odpowiedź i kody FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED, POLICY_NOT_APPROVED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - navigation_items [CorePrivacy]: menu_key VARCHAR(40) NOT NULL; locale CHAR(2) NOT NULL; version_no INT UNSIGNED NOT NULL; position_no INT UNSIGNED NOT NULL; label VARCHAR(120) NOT NULL; route_name VARCHAR(128) NOT NULL; parent_id BIGINT UNSIGNED NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | zapisany stan i state_version | invalid target/locale/hash→409; Clock UTC stable_domain_slot | draft→published immutable version | Przed commit rollback całej Action; po commit korekta przez oddzielny wersjonowany efekt, nigdy edycja księgi. |

- **Transakcja i czas:** owner danych→124→140; Idempotency-Key or stable domain key + CAS; logical_at=server_assigned_and_persisted; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** core_privacy.publish_navigation.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; odbiorcy ClientDelivery, Audit; retry exponential_bounded, DLQ alert_and_manual_replay_same_event_id.
- **UI i panel:** CMS, prywatność i newsletter; pl/en/de; SVG pod resources/svg/coreprivacy/; idle, loading, success, validation_error, conflict, offline_retry; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik coreprivacy.publish_navigation.result etykiety result, reason_code, balance_version bez PII. broken link, PL/EN/DE, concurrent publish; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P23-F08 — GetPublishedPage

- **Aktor/polityka:** public_safe_read; cudzy obiekt → 404; właściciel zapisu CorePrivacy. Źródło/warunek: P02/P05/P06/P21/P22.
- **Interface:** GET /api/v1/content/pages/{slug}; slug, locale → published safe HTML/version. Odpowiedź i kody FORBIDDEN, NOT_FOUND, RATE_LIMITED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - static_pages [CorePrivacy]: slug VARCHAR(120) NOT NULL; locale CHAR(2) NOT NULL; published_version_id BIGINT UNSIGNED NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; logical_at DATETIME(3) NULL
  - page_versions [CorePrivacy]: page_id BIGINT UNSIGNED NOT NULL; version_no INT UNSIGNED NOT NULL; safe_html MEDIUMTEXT NOT NULL; approved_by_user_id BIGINT UNSIGNED NULL; published_at DATETIME(3) NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
  - navigation_items [CorePrivacy]: menu_key VARCHAR(40) NOT NULL; locale CHAR(2) NOT NULL; version_no INT UNSIGNED NOT NULL; position_no INT UNSIGNED NOT NULL; label VARCHAR(120) NOT NULL; route_name VARCHAR(128) NOT NULL; parent_id BIGINT UNSIGNED NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | bez zmiany | unpublished/foreign→404; Clock UTC only_if_scheduled | read-only, no draft leakage | Brak zmian. |

- **Transakcja i czas:** none; read projection with ETag/as_of; read_only_no_side_effect; logical_at=server_as_of; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** Brak zdarzenia domenowego i outbox z odczytu.
- **UI i panel:** CMS, prywatność i newsletter; pl/en/de; SVG pod resources/svg/coreprivacy/; loading, empty, error, offline; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik coreprivacy.get_published_page.result etykiety result, reason_code, balance_version bez PII. XSS, locale fallback, ETag; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.

### P23-F09 — GetPolicy

- **Aktor/polityka:** public_safe_read; cudzy obiekt → 404; właściciel zapisu CorePrivacy. Źródło/warunek: P02/P05/P06/P21/P22.
- **Interface:** GET /api/v1/privacy/policies/{id}; policy_id, locale → active policy/version. Odpowiedź i kody FORBIDDEN, NOT_FOUND, RATE_LIMITED w OpenAPI/kontrakcie Action; operacja używa expected_state_version/CAS gdy zmienia stan.
- **DDL i własność:**
  - privacy_policies [CorePrivacy]: policy_code VARCHAR(40) NOT NULL; locale CHAR(2) NOT NULL; version_no INT UNSIGNED NOT NULL; body_html MEDIUMTEXT NOT NULL; effective_at DATETIME(3) NOT NULL; state VARCHAR(32) NOT NULL DEFAULT 'draft'; balance_version VARCHAR(24) NULL; …
- **Przejście stanów:**

  | Stan przed | Warunek i zegar | Stan/efekt po | Kompensacja |
  |---|---|---|---|
  | bez zmiany | unknown version→404; Clock UTC only_if_scheduled | read-only immutable version | Brak zmian. |

- **Transakcja i czas:** none; read projection with ETag/as_of; read_only_no_side_effect; logical_at=server_as_of; RNG=none. Długie zadanie trzyma lease/fencing, terminowy handler używa stabilnego due_slot; deadlock ponawia całą Action z tym samym kluczem.
- **Outbox/awaria:** Brak zdarzenia domenowego i outbox z odczytu.
- **UI i panel:** CMS, prywatność i newsletter; pl/en/de; SVG pod resources/svg/coreprivacy/; loading, empty, error, offline; dostępny modal. Filament tylko dla jawnej roli, audyt każdej decyzji uprzywilejowanej. Odczyt w mobile nie dostaje pola ukrytego w web.
- **Telemetria i test:** licznik coreprivacy.get_policy.result etykiety result, reason_code, balance_version bez PII. effective_at, locale, consent version; test 401/403/404/409/422/429 według trasy, równoległość MySQL dla mutacji, retry po commit, brak duplikatu efektu i wycieku danych.
- **Migracja/wycofanie:** create/alter owner tables listed in DDL; preserve P01-P13 contracts; greenfield; legacy import requires explicit mapping and reconciliation; drain/disable route and forward-fix; no DROP of economic history.


### P20-F10 — VerifyStoreNotification

- **Aktor/polityka:** provider_native_signature_or_verified_pubsub_token; obcy zasób 404, signed provider odrzucany przed skutkiem.
- **Interface/DTO:** POST /api/v1/payments/store-notifications/{platform}; platform-native signed notification → verified renewal/refund/chargeback event; błędy SIGNATURE_INVALID, TRANSACTION_UNKNOWN, STATE_CONFLICT.
- **Dane/DDL:** payment_events [Monetization], platform_purchase_receipts [Monetization], payment_refunds [Monetization]; typy, FK, UNIQUE i indeksy w [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql).
- **Tabela przejścia:**

  | Przed | Guard | Po | Kompensacja |
  |---|---|---|---|
  | stan/version | invalid JWS/PubSub token or unknown transaction→reject before effect | received→verified→normalized event; ApplyRefund/ApplyChargeback reuse the same Action | rollback całości przed commit; po commit nowy efekt kompensujący |

- **LockPlan/idempotencja:** company10 gdy firma→Ledger20 gdy PLN→Monetization121→append140; provider+provider_event_id unique; UTC logical_at, stabilny due_slot, fencing przy jobie.
- **Zdarzenie:** monetization.verify_store_notification.v1; payload event_id, order_public_ulid, event_type, logical_at; subscribers Monetization, Finance, Notifications; bounded retry i DLQ.
- **UI/panel:** Brak UI gracza; status zamówienia w Sklepie, PL/EN/DE, lokalne SVG resources/svg/monetization/, loading/empty/error/conflict/offline i dostępny modal.
- **Test/telemetria:** Apple JWS and Google RTDN replay/out-of-order; monetization.verify_store_notification.result bez PII, MySQL dwie sesje dla mutacji, retry po commit.
- **Migracja/rollback:** payment_events/receipts/refunds już w DDL; greenfield; disable provider adapter, retain events.

### P14-F07 — AmendLimitOrder

- **Aktor/polityka:** market.trade; obcy zasób 404, signed provider odrzucany przed skutkiem.
- **Interface/DTO:** PUT /api/v1/market/limit-orders/{id}; offer_id, new_limit_price_minor, expected_state_version → offer/version; błędy FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED.
- **Dane/DDL:** market_offers [Market]; typy, FK, UNIQUE i indeksy w [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql).
- **Tabela przejścia:**

  | Przed | Guard | Po | Kompensacja |
  |---|---|---|---|
  | stan/version | filled/cancelled/foreign→409/404 | published→published with new price; reserved_bbl unchanged | rollback całości przed commit; po commit nowy efekt kompensujący |

- **LockPlan/idempotencja:** 10→11→20→70→100→130→140; Idempotency-Key + CAS; UTC logical_at, stabilny due_slot, fencing przy jobie.
- **Zdarzenie:** market.amend_limit_order.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; subscribers Contracts, Finance, Notifications; bounded retry i DLQ.
- **UI/panel:** Rynek: oferta, PL/EN/DE, lokalne SVG resources/svg/market/, loading/empty/error/conflict/offline i dostępny modal.
- **Test/telemetria:** match concurrent amendment, version CAS; market.amendlimitorder.result bez PII, MySQL dwie sesje dla mutacji, retry po commit.
- **Migracja/rollback:** owner tables in DDL; greenfield; drain/disable and forward-fix.

### P14-F08 — CancelLimitOrder

- **Aktor/polityka:** market.trade; obcy zasób 404, signed provider odrzucany przed skutkiem.
- **Interface/DTO:** POST /api/v1/market/limit-orders/{id}/cancel; offer_id, expected_state_version → cancelled/released_bbl; błędy FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED.
- **Dane/DDL:** market_offers [Market]; typy, FK, UNIQUE i indeksy w [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql).
- **Tabela przejścia:**

  | Przed | Guard | Po | Kompensacja |
  |---|---|---|---|
  | stan/version | filled/foreign→409/404 | published/partial→cancelled; Logistics releases remaining reservation once | rollback całości przed commit; po commit nowy efekt kompensujący |

- **LockPlan/idempotencja:** 10→11→20→70→100→130→140; Idempotency-Key + CAS; UTC logical_at, stabilny due_slot, fencing przy jobie.
- **Zdarzenie:** market.cancel_limit_order.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; subscribers Contracts, Finance, Notifications; bounded retry i DLQ.
- **UI/panel:** Rynek: oferta, PL/EN/DE, lokalne SVG resources/svg/market/, loading/empty/error/conflict/offline i dostępny modal.
- **Test/telemetria:** match concurrently, reservation exactly once; market.cancellimitorder.result bez PII, MySQL dwie sesje dla mutacji, retry po commit.
- **Migracja/rollback:** owner tables in DDL; greenfield; drain/disable and forward-fix.

### P15-F07 — CreateB2BOffer

- **Aktor/polityka:** contracts.manage; obcy zasób 404, signed provider odrzucany przed skutkiem.
- **Interface/DTO:** POST /api/v1/contracts/b2b/offers; source_storage_id, quantity_bbl, unit_price_minor, buyer_company_id, expires_at → offer_id; błędy FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED.
- **Dane/DDL:** b2b_offers [Contracts]; typy, FK, UNIQUE i indeksy w [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql).
- **Tabela przejścia:**

  | Przed | Guard | Po | Kompensacja |
  |---|---|---|---|
  | stan/version | insufficient stock/invalid collar→409 | available→published, Logistics reserves oil once | rollback całości przed commit; po commit nowy efekt kompensujący |

- **LockPlan/idempotencja:** 10→12→20→70→80→90→110→140; Idempotency-Key + CAS; UTC logical_at, stabilny due_slot, fencing przy jobie.
- **Zdarzenie:** contracts.create_b2boffer.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; subscribers Logistics, Finance, Notifications; bounded retry i DLQ.
- **UI/panel:** Kontrakty: oferta B2B, PL/EN/DE, lokalne SVG resources/svg/contracts/, loading/empty/error/conflict/offline i dostępny modal.
- **Test/telemetria:** spot concurrently, cartel collar and cap; contracts.createb2boffer.result bez PII, MySQL dwie sesje dla mutacji, retry po commit.
- **Migracja/rollback:** owner tables in DDL; greenfield; drain/disable and forward-fix.

### P15-F08 — CancelB2BOffer

- **Aktor/polityka:** contracts.manage; obcy zasób 404, signed provider odrzucany przed skutkiem.
- **Interface/DTO:** POST /api/v1/contracts/b2b/offers/{id}/cancel; offer_id, expected_state_version → cancelled/released_bbl; błędy FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED.
- **Dane/DDL:** b2b_offers [Contracts]; typy, FK, UNIQUE i indeksy w [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql).
- **Tabela przejścia:**

  | Przed | Guard | Po | Kompensacja |
  |---|---|---|---|
  | stan/version | accepted/expired→409 | published→cancelled, Logistics releases reservation once | rollback całości przed commit; po commit nowy efekt kompensujący |

- **LockPlan/idempotencja:** 10→12→20→70→80→90→110→140; Idempotency-Key + CAS; UTC logical_at, stabilny due_slot, fencing przy jobie.
- **Zdarzenie:** contracts.cancel_b2boffer.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; subscribers Logistics, Finance, Notifications; bounded retry i DLQ.
- **UI/panel:** Kontrakty: oferta B2B, PL/EN/DE, lokalne SVG resources/svg/contracts/, loading/empty/error/conflict/offline i dostępny modal.
- **Test/telemetria:** accept concurrently, double release; contracts.cancelb2boffer.result bez PII, MySQL dwie sesje dla mutacji, retry po commit.
- **Migracja/rollback:** owner tables in DDL; greenfield; drain/disable and forward-fix.

### P15-F09 — CancelContract

- **Aktor/polityka:** contracts.manage; obcy zasób 404, signed provider odrzucany przed skutkiem.
- **Interface/DTO:** POST /api/v1/contracts/{id}/cancel; contract_id, expected_state_version → cancelled/penalty/refund; błędy FORBIDDEN, NOT_FOUND, VERSION_CONFLICT, STATE_CONFLICT, VALIDATION_FAILED.
- **Dane/DDL:** contracts [Contracts], escrow_accounts [Contracts], contract_tranches [Contracts]; typy, FK, UNIQUE i indeksy w [DDL](OILEMPIRE_DDL_P14_P25_MYSQL84.sql).
- **Tabela przejścia:**

  | Przed | Guard | Po | Kompensacja |
  |---|---|---|---|
  | stan/version | receipt/settled/foreign→409/404 | accepted→cancelled; directional 10–40% penalty, escrow release/refund via same Action | rollback całości przed commit; po commit nowy efekt kompensujący |

- **LockPlan/idempotencja:** 10→12→20→70→80→90→110→140; Idempotency-Key + CAS; UTC logical_at, stabilny due_slot, fencing przy jobie.
- **Zdarzenie:** contracts.cancel_contract.v1; payload event_id, entity_public_ulid, logical_at, balance_version, contract_version; subscribers Logistics, Finance, Notifications; bounded retry i DLQ.
- **UI/panel:** Kontrakty: oferta B2B, PL/EN/DE, lokalne SVG resources/svg/contracts/, loading/empty/error/conflict/offline i dostępny modal.
- **Test/telemetria:** receipt vs cancel, penalty floor/cap, two workers; contracts.cancelcontract.result bez PII, MySQL dwie sesje dla mutacji, retry po commit.
- **Migracja/rollback:** owner tables in DDL; greenfield; drain/disable and forward-fix.

### P25-F05 — ReadHealth

- **Aktor/kontrakt:** publiczne GET /up ujawnia wyłącznie live; prywatne GET /internal/ready wymaga Operations auth. DTO: probe scope → healthy/degraded/down + timestamp/version.
- **Dane/przejście:** bez własnej tabeli, brak mutacji, Settlement/FTS/outbox/RNG i idempotencji mutacyjnej. Żaden probe nie czyta PII.
- **Zależności:** DB, queue, cache, private storage, due/outbox lag, schema/contract/balance version; każda kontrola z bounded timeout i kodem przyczyny.
- **UI/test:** Filament PL/EN/DE, lokalny SVG dla zdrowy/degraded/down; test rozłączania każdej zależności, brak sekretów w publicznym wyniku i poprawne alerty.
- **Migracja/rollback:** bez migracji; probe włączać wersjonowaną konfiguracją, stary kod pozostaje kompatybilny.
