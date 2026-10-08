-- Add the missing request limiter table without changing messages or rooms.
-- Dodaj brakujaca tabele limitera bez zmiany wiadomosci ani pokoi.
CREATE TABLE IF NOT EXISTS chat_request_limits (
    bucket_key CHAR(64) NOT NULL PRIMARY KEY,
    started_at BIGINT NOT NULL,
    hits INT NOT NULL,
    INDEX idx_chat_request_limits_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
