-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: mysql8
-- Generation Time: Maj 30, 2026 at 09:29 PM
-- Wersja serwera: 8.0.33-25
-- Wersja PHP: 8.2.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `01240275_oil`
--

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `admins`
--

CREATE TABLE `admins` (
  `id` int UNSIGNED NOT NULL,
  `username` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'bcrypt via password_hash()',
  `email` varchar(128) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `failed_attempts` tinyint UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Licznik nieudanych logowań',
  `lock_until` datetime DEFAULT NULL COMMENT 'Konto zablokowane do tego czasu',
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `admin_help_pages`
--

CREATE TABLE `admin_help_pages` (
  `id` int NOT NULL,
  `slug` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 0xF09F9384,
  `content` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` smallint NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_help_pages`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `admin_login_attempts`
--

CREATE TABLE `admin_login_attempts` (
  `id` int UNSIGNED NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'NULL gdy login nieznany',
  `attempted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_login_attempts`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int UNSIGNED NOT NULL,
  `action` varchar(64) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'np. cash_change, force_tick, well_status_change',
  `description` text COLLATE utf8mb4_general_ci COMMENT 'Opis czytelny dla człowieka',
  `target_player_id` int UNSIGNED DEFAULT NULL COMMENT 'ID gracza (jeśli dotyczy)',
  `target_type` enum('player','well','market','system') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'player',
  `target_id` int UNSIGNED DEFAULT NULL COMMENT 'ID encji (well_id itp.)',
  `admin_id` int UNSIGNED DEFAULT NULL COMMENT 'FK do admins.id',
  `admin_user` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'admin',
  `admin_ip` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'IPv4 lub IPv6',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_logs`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `admin_news`
--

CREATE TABLE `admin_news` (
  `id` int NOT NULL,
  `title` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT '0',
  `pinned_at` datetime DEFAULT NULL,
  `created_by` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Administrator',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_news`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `admin_password_resets`
--

CREATE TABLE `admin_password_resets` (
  `id` int NOT NULL,
  `email` varchar(128) COLLATE utf8mb4_general_ci NOT NULL,
  `token_hash` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_password_resets`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `bailiff_proceedings`
--

CREATE TABLE `bailiff_proceedings` (
  `id` int NOT NULL,
  `loan_id` int NOT NULL,
  `player_id` int NOT NULL,
  `stage` int DEFAULT '1' COMMENT '1=ostrzezenie 2=gotowka 3=ropa 4=odwierty',
  `cash_seized` decimal(12,2) DEFAULT '0.00',
  `oil_seized` decimal(12,2) DEFAULT '0.00',
  `wells_seized` int DEFAULT '0',
  `started_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `next_action_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Kiedy komornik podejmie kolejne działanie',
  `completed_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','suspended','suspended_recovery','completed','bankruptcy') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `negotiation_used` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Czy gracz wykorzystał szansę na negocjacje',
  `suspended_at` datetime DEFAULT NULL,
  `suspend_reason` varchar(60) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `dismissed_at` datetime DEFAULT NULL,
  `suspended_until` datetime DEFAULT NULL COMMENT 'Do kiedy komornik zawieszony (dla typu deferral)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `bankruptcy_events`
--

CREATE TABLE `bankruptcy_events` (
  `id` int NOT NULL,
  `player_id` int NOT NULL,
  `event_type` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `message` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `severity` enum('low','medium','high','critical') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'medium',
  `is_critical` tinyint(1) NOT NULL DEFAULT '0',
  `due_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `resolution_note` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

--
-- Dumping data for table `bankruptcy_events`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `bank_negotiations`
--

CREATE TABLE `bank_negotiations` (
  `id` int UNSIGNED NOT NULL,
  `player_id` int NOT NULL,
  `loan_id` int NOT NULL,
  `type` enum('deferral','restructure','extension','rate_reduction','recovery') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','rejected','completed','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `requested_deferral_days` int DEFAULT NULL,
  `requested_extension_months` int DEFAULT NULL,
  `requested_rate_reduction` decimal(5,2) DEFAULT NULL,
  `bank_decision` text COLLATE utf8mb4_unicode_ci,
  `approved_deferral_days` int DEFAULT NULL,
  `approved_extension_months` int DEFAULT NULL,
  `new_interest_rate` decimal(5,2) DEFAULT NULL,
  `additional_fee` decimal(12,2) NOT NULL DEFAULT '0.00',
  `requested_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `decision_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `decision_due_at` datetime DEFAULT NULL COMMENT 'Kiedy bank ma podjąć decyzję',
  `decision_hours` float DEFAULT '1' COMMENT 'Ile godzin bank potrzebuje na decyzję',
  `decision_delays` longtext COLLATE utf8mb4_unicode_ci COMMENT 'JSON — lista opóźnień z powodami',
  `approval_chance` tinyint UNSIGNED DEFAULT '75' COMMENT 'Szansa zatwierdzenia 0–100 (wewnętrzna)',
  `fee_breakdown` longtext COLLATE utf8mb4_unicode_ci COMMENT 'JSON — rozkład prowizji (tylko GM)',
  `rejection_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Wewnętrzny powód odrzucenia (tylko GM)',
  `resolved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Negocjacje warunków kredytu z bankiem';

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `bank_negotiation_events`
--

CREATE TABLE `bank_negotiation_events` (
  `id` int UNSIGNED NOT NULL,
  `negotiation_id` int NOT NULL,
  `event_key` varchar(60) COLLATE utf8mb4_general_ci NOT NULL,
  `event_type` enum('delay','speedup','fee_increase','fee_decrease','trust_penalty','approval_boost') COLLATE utf8mb4_general_ci NOT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `hours_added` float DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bank_negotiation_events`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `bank_settings`
--

CREATE TABLE `bank_settings` (
  `id` int NOT NULL,
  `setting_key` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `value` decimal(8,4) NOT NULL DEFAULT '1.0000',
  `description` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` varchar(64) COLLATE utf8mb4_general_ci DEFAULT 'system'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bank_settings`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `bank_trust_log`
--

CREATE TABLE `bank_trust_log` (
  `id` int UNSIGNED NOT NULL,
  `player_id` int NOT NULL,
  `event` varchar(80) COLLATE utf8mb4_general_ci NOT NULL,
  `delta` tinyint NOT NULL,
  `note` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `bank_trust_scores`
--

CREATE TABLE `bank_trust_scores` (
  `player_id` int NOT NULL,
  `score` tinyint UNSIGNED NOT NULL DEFAULT '50',
  `last_event` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_updated` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bank_trust_scores`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `black_market_offers`
--

CREATE TABLE `black_market_offers` (
  `id` int NOT NULL,
  `player_id` int NOT NULL,
  `bbl` int NOT NULL,
  `price_per_bbl` decimal(10,2) NOT NULL,
  `base_risk_pct` decimal(5,2) NOT NULL,
  `expires_at` datetime NOT NULL,
  `status` enum('active','accepted','expired') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `black_market_offers`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `black_market_transactions`
--

CREATE TABLE `black_market_transactions` (
  `id` int NOT NULL,
  `player_id` int NOT NULL,
  `offer_id` int NOT NULL,
  `bbl` int NOT NULL,
  `revenue` decimal(15,2) NOT NULL DEFAULT '0.00',
  `detected` tinyint(1) NOT NULL DEFAULT '0',
  `penalty` decimal(15,2) NOT NULL DEFAULT '0.00',
  `black_score_before` decimal(5,2) NOT NULL DEFAULT '0.00',
  `black_score_after` decimal(5,2) NOT NULL DEFAULT '0.00',
  `credit_score_change` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `black_market_transactions`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `boardroom_config`
--

CREATE TABLE `boardroom_config` (
  `key` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `value` text COLLATE utf8mb4_general_ci NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `boardroom_config`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `board_members`
--

CREATE TABLE `board_members` (
  `id` int NOT NULL,
  `player_id` int DEFAULT NULL COMMENT 'Gracz który zatrudnił — NULL = globalny/niezwiązany',
  `member_type` enum('director','staff') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'director',
  `role_id` int NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `gender` enum('M','F','N') COLLATE utf8mb4_general_ci DEFAULT 'M',
  `specialization_id` int DEFAULT NULL,
  `region_code` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `birth_date` date NOT NULL,
  `nationality` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `experience_years` int NOT NULL,
  `skill_organization` int NOT NULL,
  `skill_negotiation` int NOT NULL,
  `skill_analysis` int NOT NULL,
  `skill_stress` int NOT NULL,
  `skill_ethics` int NOT NULL,
  `trait_loyalty` int NOT NULL,
  `trait_corruption_risk` int NOT NULL,
  `trait_ambition` int NOT NULL,
  `salary` decimal(10,2) NOT NULL,
  `hired_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fired_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','suspended','fired') COLLATE utf8mb4_general_ci DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `board_members`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `board_roles`
--

CREATE TABLE `board_roles` (
  `id` int NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_general_ci DEFAULT '',
  `icon` varchar(10) COLLATE utf8mb4_general_ci DEFAULT '',
  `sort_order` int DEFAULT '0',
  `avatar_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT '',
  `is_required` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `board_roles`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `candidates`
--

CREATE TABLE `candidates` (
  `id` int NOT NULL,
  `player_id` int DEFAULT NULL COMMENT 'Gracz który zlecił rekrutację (przez recruitment_requests)',
  `director_status` enum('pending','approved','rejected') COLLATE utf8mb4_general_ci DEFAULT 'pending' COMMENT 'Status zatwierdzenia przez dyrektora (gracza)',
  `role_id` int NOT NULL,
  `request_id` int DEFAULT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `gender` enum('M','F','N') COLLATE utf8mb4_general_ci DEFAULT 'M',
  `birth_date` date NOT NULL,
  `nationality` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `region_code` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `specialization_id` int DEFAULT NULL,
  `experience_years` int NOT NULL,
  `skill_organization` int NOT NULL,
  `skill_negotiation` int NOT NULL,
  `skill_analysis` int NOT NULL,
  `skill_stress` int NOT NULL,
  `skill_ethics` int NOT NULL,
  `trait_loyalty` int NOT NULL,
  `trait_corruption_risk` int NOT NULL,
  `trait_ambition` int NOT NULL,
  `expected_salary` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `candidates`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `candidate_reviews`
--

CREATE TABLE `candidate_reviews` (
  `id` int NOT NULL,
  `candidate_id` int NOT NULL,
  `reviewer_member_id` int NOT NULL,
  `player_id` int NOT NULL,
  `score` tinyint NOT NULL,
  `recommendation` enum('hire','reject') COLLATE utf8mb4_general_ci NOT NULL,
  `comment` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ;

--
-- Dumping data for table `candidate_reviews`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `chat_bans`
--

CREATE TABLE `chat_bans` (
  `id` int NOT NULL,
  `player_id` int NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `banned_by` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `banned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL COMMENT 'NULL = permanent'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_bans`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `chat_blocked_words`
--

CREATE TABLE `chat_blocked_words` (
  `id` int UNSIGNED NOT NULL,
  `word` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `replacement` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '***',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_blocked_words`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `chat_conversation_reads`
--

CREATE TABLE `chat_conversation_reads` (
  `player_id` int NOT NULL,
  `partner_id` int NOT NULL,
  `last_read_message_id` int NOT NULL DEFAULT '0',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_conversation_reads`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int NOT NULL,
  `sender_id` int DEFAULT NULL COMMENT 'NULL = wiadomość admina',
  `receiver_id` int DEFAULT NULL COMMENT 'NULL = global',
  `channel` enum('global','private') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'global',
  `username` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `is_pinned` tinyint(1) NOT NULL DEFAULT '0',
  `pinned_at` datetime DEFAULT NULL,
  `attachment_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment_size` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_messages`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `chat_reports`
--

CREATE TABLE `chat_reports` (
  `id` int NOT NULL,
  `message_id` int NOT NULL,
  `reporter_id` int NOT NULL,
  `reason` enum('spam','obraza','inne') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'inne',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('open','resolved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_reports`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `director_notifications`
--

CREATE TABLE `director_notifications` (
  `id` int UNSIGNED NOT NULL,
  `player_id` int NOT NULL,
  `type` enum('bank','hr','technical','market','legal','urgent','info') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `priority` enum('low','medium','high','critical') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '?',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `requires_action` tinyint(1) NOT NULL DEFAULT '0',
  `action_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_label` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Powiadomienia dla dyrektora w boardroomie';

--
-- Dumping data for table `director_notifications`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `disaster_message_templates`
--

CREATE TABLE `disaster_message_templates` (
  `id` int UNSIGNED NOT NULL,
  `disaster_type` enum('blowout','pipeline_explosion','reservoir_contamination','surface_spill') COLLATE utf8mb4_unicode_ci NOT NULL,
  `hse_active` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0 = bez BHP (katastrofa), 1 = z BHP (interwencja)',
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Zmienne: {well} {pipe} {loss} {drop} {pct} {fine} {oil} {area} {time} {hours} {vol}',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0 = wyłączony (nie będzie losowany)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bank komunikatów katastrof przemysłowych — losowane przy zdarzeniu';

--
-- Dumping data for table `disaster_message_templates`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `drilling_projects`
--

CREATE TABLE `drilling_projects` (
  `id` int UNSIGNED NOT NULL,
  `player_id` int UNSIGNED NOT NULL,
  `project_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Nowy odwiert',
  `well_type` enum('onshore','offshore') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'onshore',
  `location_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pole naftowe',
  `depth_m` int UNSIGNED NOT NULL DEFAULT '2500',
  `stage` enum('planning','mobilization','drilling','casing','testing','done') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'planning',
  `stage_started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `stage_duration_h` smallint UNSIGNED NOT NULL DEFAULT '1',
  `total_cost` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `email_verifications`
--

CREATE TABLE `email_verifications` (
  `id` int NOT NULL,
  `player_id` int NOT NULL,
  `token_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `employee_certificates`
--

CREATE TABLE `employee_certificates` (
  `id` int NOT NULL,
  `member_id` int NOT NULL,
  `code` varchar(60) COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `skill_bonus` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `issued_at` date NOT NULL,
  `expires_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `employee_contracts`
--

CREATE TABLE `employee_contracts` (
  `id` int NOT NULL,
  `member_id` int NOT NULL,
  `contract_start` date NOT NULL,
  `contract_end` date NOT NULL,
  `salary` decimal(10,2) NOT NULL,
  `bonus` decimal(10,2) NOT NULL DEFAULT '0.00',
  `contract_type` enum('6m','1y','2y') COLLATE utf8mb4_general_ci NOT NULL DEFAULT '1y',
  `status` enum('active','expired','terminated') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_contracts`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `employment_history`
--

CREATE TABLE `employment_history` (
  `id` int NOT NULL,
  `member_id` int DEFAULT NULL,
  `action` enum('hired','fired','resigned','suspended') COLLATE utf8mb4_general_ci NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employment_history`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `failure_log`
--

CREATE TABLE `failure_log` (
  `id` int UNSIGNED NOT NULL,
  `player_id` int UNSIGNED NOT NULL,
  `well_id` int UNSIGNED DEFAULT NULL,
  `failure_type` enum('pump','pipeline','electrical','pressure_drop','blowout','pipeline_explosion','reservoir_contamination','surface_spill') COLLATE utf8mb4_general_ci NOT NULL,
  `repair_cost` decimal(14,2) NOT NULL DEFAULT '0.00',
  `environmental_fine` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Kara regulacyjna/środowiskowa',
  `production_lost_bbl` decimal(10,2) NOT NULL DEFAULT '0.00',
  `reservoir_loss_pct` tinyint UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Procent utraconego złoża',
  `description` text COLLATE utf8mb4_general_ci COMMENT 'Opis zdarzenia',
  `resolved` tinyint(1) NOT NULL DEFAULT '0',
  `occurred_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `failure_log`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `finance_logs`
--

CREATE TABLE `finance_logs` (
  `id` int NOT NULL,
  `player_id` int NOT NULL,
  `tick_at` datetime NOT NULL,
  `revenue` decimal(16,2) NOT NULL DEFAULT '0.00' COMMENT 'bbl × cena × (1-loss)',
  `gross_revenue` decimal(16,2) NOT NULL DEFAULT '0.00' COMMENT 'bbl × cena przed stratami',
  `opex` decimal(16,2) NOT NULL DEFAULT '0.00' COMMENT 'OPEX odwiertów',
  `salary_cost` decimal(16,2) NOT NULL DEFAULT '0.00' COMMENT 'Pensje zarząd + technicy',
  `transport_cost` decimal(16,2) NOT NULL DEFAULT '0.00' COMMENT 'Transport OPEX',
  `hub_usage_cost` decimal(16,2) NOT NULL DEFAULT '0.00',
  `incident_cost` decimal(16,2) NOT NULL DEFAULT '0.00' COMMENT 'Naprawy + kary',
  `tax` decimal(16,2) NOT NULL DEFAULT '0.00' COMMENT 'Podatek regionalny',
  `loss_bbl` decimal(14,4) NOT NULL DEFAULT '0.0000' COMMENT 'Baryłki utracone w transporcie',
  `pre_storage_loss_bbl` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `transport_loss_bbl` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `transport_event_loss_bbl` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `hub_loss_bbl` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `fallback_loss_bbl` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `hub_incident_loss_bbl` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `loss_value` decimal(16,2) NOT NULL DEFAULT '0.00' COMMENT 'Wartość strat PLN',
  `hub_loss_value` decimal(16,2) NOT NULL DEFAULT '0.00',
  `fallback_loss_value` decimal(16,2) NOT NULL DEFAULT '0.00',
  `hub_incident_loss_value` decimal(16,2) NOT NULL DEFAULT '0.00',
  `net_profit` decimal(16,2) NOT NULL DEFAULT '0.00' COMMENT 'revenue - wszystkie koszty',
  `cash_after` decimal(20,2) NOT NULL DEFAULT '0.00' COMMENT 'Stan kasy po ticku',
  `oil_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `produced_bbl` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `delivered_bbl` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `bbl_produced` decimal(14,4) NOT NULL DEFAULT '0.0000' COMMENT 'Baryłki wyprodukowane netto',
  `wells_active` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historia finansowa per gracz per tick';

--
-- Dumping data for table `finance_logs`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `game_help_pages`
--

CREATE TABLE `game_help_pages` (
  `id` int NOT NULL,
  `slug` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'identyfikator sekcji np. start, odwierty',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '?',
  `content` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` smallint NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `game_help_pages`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `geological_layers`
--

CREATE TABLE `geological_layers` (
  `id` tinyint UNSIGNED NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(60) COLLATE utf8mb4_general_ci NOT NULL,
  `depth_m_max` smallint NOT NULL,
  `reservoir_bbl` int NOT NULL,
  `richness_mult` decimal(4,2) NOT NULL,
  `risk_mult` decimal(5,2) NOT NULL,
  `wear_depth_factor` decimal(4,2) NOT NULL,
  `spiral_boost` decimal(4,2) NOT NULL,
  `switch_cost` bigint NOT NULL DEFAULT '0',
  `switch_hours` tinyint NOT NULL DEFAULT '0',
  `sort_order` tinyint NOT NULL DEFAULT '0',
  `description` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `geological_layers`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `headhunter_candidates`
--

CREATE TABLE `headhunter_candidates` (
  `id` int NOT NULL,
  `search_id` int NOT NULL,
  `player_id` int NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `specialization_id` int DEFAULT NULL,
  `skill_level` tinyint NOT NULL,
  `current_company` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `salary_expectation` decimal(15,2) NOT NULL,
  `signing_bonus` decimal(15,2) NOT NULL DEFAULT '0.00',
  `join_probability` tinyint NOT NULL,
  `trait_loyalty` tinyint NOT NULL DEFAULT '5',
  `status` enum('available','offered','accepted','rejected','expired') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'available',
  `expires_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `headhunter_candidates`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `headhunter_searches`
--

CREATE TABLE `headhunter_searches` (
  `id` int NOT NULL,
  `player_id` int NOT NULL,
  `specialization_id` int DEFAULT NULL,
  `spec_code` varchar(60) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `status` enum('searching','completed','failed') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'searching',
  `result_count` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `headhunter_searches`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `hr_events`
--

CREATE TABLE `hr_events` (
  `id` int NOT NULL,
  `player_id` int NOT NULL,
  `type` varchar(60) COLLATE utf8mb4_general_ci NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `member_id` int DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hr_events`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `hr_regions`
--

CREATE TABLE `hr_regions` (
  `code` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `skill_modifier` decimal(4,2) NOT NULL DEFAULT '1.00',
  `salary_modifier` decimal(4,2) NOT NULL DEFAULT '1.00',
  `availability` tinyint NOT NULL DEFAULT '60'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hr_regions`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `hr_specializations`
--

CREATE TABLE `hr_specializations` (
  `id` int NOT NULL,
  `code` varchar(60) COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `department` varchar(60) COLLATE utf8mb4_general_ci NOT NULL,
  `rarity` enum('common','uncommon','rare','very_rare') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'common',
  `base_salary_min` decimal(10,2) NOT NULL,
  `base_salary_max` decimal(10,2) NOT NULL,
  `min_age` tinyint NOT NULL DEFAULT '25',
  `max_age` tinyint NOT NULL DEFAULT '58',
  `description` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hr_specializations`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `hub_road_trips`
--

CREATE TABLE `hub_road_trips` (
  `id` bigint UNSIGNED NOT NULL,
  `player_id` int NOT NULL,
  `hub_id` bigint UNSIGNED NOT NULL COMMENT 'FK logistics_hubs.id',
  `volume_bbl` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `truck_type` enum('standard','heavy','armored') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `status` enum('in_transit','delayed','lost','delivered') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_transit',
  `departure_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `eta_at` datetime NOT NULL,
  `arrived_at` datetime DEFAULT NULL,
  `incident_type` enum('theft','raid','accident','sabotage','route_block') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Road transport trips: hub -> storage (time-based)';

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `industrial_disasters`
--

CREATE TABLE `industrial_disasters` (
  `id` int UNSIGNED NOT NULL,
  `player_id` int NOT NULL,
  `well_id` int DEFAULT NULL,
  `pipeline_id` int DEFAULT NULL,
  `disaster_type` enum('blowout','pipeline_explosion','reservoir_contamination','surface_spill') COLLATE utf8mb4_general_ci NOT NULL,
  `severity` enum('major','catastrophic') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'major',
  `repair_cost` decimal(14,2) NOT NULL DEFAULT '0.00',
  `env_fine` decimal(14,2) NOT NULL DEFAULT '0.00',
  `reservoir_lost` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'bbl złoża bezpowrotnie utracone',
  `oil_lost` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'bbl ropy utracone z magazynu',
  `hse_active` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Czy BHP było aktywne',
  `hse_skill` tinyint NOT NULL DEFAULT '0',
  `proc_level` tinyint UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Poziom procedur BHP w chwili katastrofy (0–5)',
  `proc_integrity` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Integralność procedur BHP w chwili katastrofy (0.00–100.00)',
  `description` text COLLATE utf8mb4_general_ci,
  `status` enum('active','being_repaired','resolved') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `occurred_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Dziennik katastrof przemysłowych';

--
-- Dumping data for table `industrial_disasters`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `loans`
--

CREATE TABLE `loans` (
  `id` int NOT NULL,
  `player_id` int NOT NULL,
  `application_id` int DEFAULT NULL,
  `principal_amount` decimal(20,2) NOT NULL,
  `remaining_amount` decimal(20,2) NOT NULL DEFAULT '0.00',
  `interest_rate` decimal(4,2) NOT NULL,
  `installment_amount` decimal(20,2) NOT NULL,
  `installment_frequency` int NOT NULL DEFAULT '12' COMMENT 'Co ile godzin',
  `next_installment_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','late','defaulted','paid_off') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `late_since` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_interest_calc_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `total_interest_paid` decimal(20,2) NOT NULL DEFAULT '0.00',
  `paid_off_at` timestamp NULL DEFAULT NULL,
  `installments_total` int DEFAULT '20',
  `installments_paid` int DEFAULT '0',
  `interest_model` varchar(20) COLLATE utf8mb4_general_ci DEFAULT 'annuity',
  `resolved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loans`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `loan_applications`
--

CREATE TABLE `loan_applications` (
  `id` int NOT NULL,
  `player_id` int NOT NULL,
  `requested_amount` decimal(20,2) NOT NULL,
  `status` enum('pending','approved','rejected','accepted','expired') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `risk_score` int DEFAULT NULL,
  `risk_breakdown` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `approved_amount` decimal(20,2) DEFAULT NULL,
  `interest_rate` decimal(5,2) DEFAULT NULL COMMENT 'APR w procentach',
  `rejection_reason` text COLLATE utf8mb4_general_ci,
  `market_trend_id` int DEFAULT NULL COMMENT 'ID trendu podczas decyzji',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `decision_at` timestamp NULL DEFAULT NULL COMMENT 'Kiedy bank podejmie decyzje',
  `decided_at` timestamp NULL DEFAULT NULL COMMENT 'Kiedy pojal decyzje',
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'Oferta wygasa po 48h'
) ;

--
-- Dumping data for table `loan_applications`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `loan_payments`
--

CREATE TABLE `loan_payments` (
  `id` int NOT NULL,
  `loan_id` int NOT NULL,
  `player_id` int NOT NULL,
  `amount` decimal(20,2) NOT NULL,
  `payment_type` enum('installment','interest','early_repayment','bailiff_seizure') COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_payments`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `logistics_hubs`
--

CREATE TABLE `logistics_hubs` (
  `id` bigint UNSIGNED NOT NULL,
  `player_id` bigint UNSIGNED NOT NULL,
  `tenant_player_id` bigint UNSIGNED NOT NULL DEFAULT '0',
  `region_id` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'FK to world_regions.id',
  `zone_key` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Sub-region zone within region_id',
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hub_type` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'small|medium|large',
  `acquisition_type` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT 'planned|building|active|overloaded|damaged|paused|disabled',
  `work_mode` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard' COMMENT 'eco|standard|max',
  `level` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `slot_limit` tinyint UNSIGNED NOT NULL DEFAULT '2',
  `condition_pct` decimal(5,2) NOT NULL DEFAULT '100.00',
  `initial_condition_pct` decimal(5,2) NOT NULL DEFAULT '100.00',
  `last_maintenance_at` datetime DEFAULT NULL,
  `wear_level` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `efficiency_pct` decimal(5,2) NOT NULL DEFAULT '100.00',
  `nominal_capacity_bph` decimal(12,2) NOT NULL DEFAULT '0.00',
  `real_capacity_bph` decimal(12,2) NOT NULL DEFAULT '0.00',
  `buffer_capacity_bbl` decimal(12,2) NOT NULL DEFAULT '0.00',
  `buffer_current_bbl` decimal(12,2) NOT NULL DEFAULT '0.00',
  `opex_per_tick` decimal(12,2) NOT NULL DEFAULT '0.00',
  `lease_fee_per_tick` decimal(12,2) NOT NULL DEFAULT '0.00',
  `build_cost` decimal(14,2) NOT NULL DEFAULT '0.00',
  `acquisition_price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `acquired_at` datetime DEFAULT NULL,
  `repair_cost_estimate` decimal(14,2) NOT NULL DEFAULT '0.00',
  `last_processed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `outbound_transport_type` enum('nieustawiony','rurociag','ciezarowki') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nieustawiony' COMMENT 'Typ transportu z hubu do magazynu (odcinek 2)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Player logistics hubs';

--
-- Dumping data for table `logistics_hubs`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `logistics_hub_assignments`
--

CREATE TABLE `logistics_hub_assignments` (
  `id` bigint UNSIGNED NOT NULL,
  `hub_id` bigint UNSIGNED NOT NULL,
  `well_id` bigint UNSIGNED NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT 'pending|active|relinking|detached|blocked',
  `access_fee_paid` decimal(12,2) NOT NULL DEFAULT '0.00',
  `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `detached_at` datetime DEFAULT NULL,
  `cooldown_until` datetime DEFAULT NULL,
  `relink_started_at` datetime DEFAULT NULL,
  `relink_finish_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Well-to-hub assignment records';

--
-- Dumping data for table `logistics_hub_assignments`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `logistics_hub_config`
--

CREATE TABLE `logistics_hub_config` (
  `id` bigint UNSIGNED NOT NULL,
  `config_group` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_scope` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'global',
  `config_value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Editable logistics hub config — admin/logistics.php';

--
-- Dumping data for table `logistics_hub_config`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `logistics_hub_events`
--

CREATE TABLE `logistics_hub_events` (
  `id` bigint UNSIGNED NOT NULL,
  `player_id` bigint UNSIGNED NOT NULL,
  `hub_id` bigint UNSIGNED DEFAULT NULL,
  `well_id` bigint UNSIGNED DEFAULT NULL,
  `event_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info' COMMENT 'info|warning|critical',
  `title` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta_json` json DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Logistics hub event log (player-visible + internal)';

--
-- Dumping data for table `logistics_hub_events`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `logistics_hub_tick_stats`
--

CREATE TABLE `logistics_hub_tick_stats` (
  `id` bigint UNSIGNED NOT NULL,
  `hub_id` bigint UNSIGNED NOT NULL,
  `tick_time` datetime NOT NULL,
  `input_volume_bbl` decimal(12,2) NOT NULL DEFAULT '0.00',
  `processed_volume_bbl` decimal(12,2) NOT NULL DEFAULT '0.00',
  `buffered_volume_bbl` decimal(12,2) NOT NULL DEFAULT '0.00',
  `lost_volume_bbl` decimal(12,2) NOT NULL DEFAULT '0.00',
  `load_pct` decimal(6,2) NOT NULL DEFAULT '0.00',
  `condition_before_pct` decimal(5,2) NOT NULL DEFAULT '100.00',
  `condition_after_pct` decimal(5,2) NOT NULL DEFAULT '100.00',
  `wear_added` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `overload_flag` tinyint(1) NOT NULL DEFAULT '0',
  `incident_flag` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Per-tick telemetry for logistics hubs';

--
-- Dumping data for table `logistics_hub_tick_stats`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `logistics_region_zones`
--

CREATE TABLE `logistics_region_zones` (
  `id` bigint UNSIGNED NOT NULL,
  `region_id` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'FK to world_regions.id',
  `zone_key` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone_type` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'inland' COMMENT 'inland|offshore|port|remote',
  `distance_penalty_pct` decimal(6,2) NOT NULL DEFAULT '0.00',
  `opex_penalty_pct` decimal(6,2) NOT NULL DEFAULT '0.00',
  `risk_penalty_pct` decimal(6,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Logistics zone dictionary per region';

--
-- Dumping data for table `logistics_region_zones`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `marine_deliveries`
--

CREATE TABLE `marine_deliveries` (
  `id` bigint NOT NULL,
  `player_id` int NOT NULL,
  `well_id` int NOT NULL,
  `port_id` int DEFAULT NULL,
  `hub_id` bigint UNSIGNED DEFAULT NULL COMMENT 'hub docelowy (FK logistics_hubs.id)',
  `volume_bbl` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `status` enum('departing','in_transit','waiting_for_port','processing','delivered','delayed','lost') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'departing',
  `departure_at` datetime NOT NULL,
  `eta_at` datetime NOT NULL,
  `arrived_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `delay_ticks` smallint NOT NULL DEFAULT '0',
  `incident_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `handling_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `marine_deliveries`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `market_demand_log`
--

CREATE TABLE `market_demand_log` (
  `id` int UNSIGNED NOT NULL,
  `demand_index` decimal(12,2) NOT NULL,
  `demand_base` decimal(12,2) NOT NULL,
  `season_factor` decimal(5,3) NOT NULL,
  `trend_factor` decimal(5,3) NOT NULL,
  `shock_factor` decimal(5,3) NOT NULL DEFAULT '1.000',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Historia demand_index per aktualizacja';

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `market_demand_shocks`
--

CREATE TABLE `market_demand_shocks` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `factor` decimal(5,3) NOT NULL COMMENT '>1 wzrost popytu, <1 spadek',
  `duration_h` int UNSIGNED NOT NULL DEFAULT '6',
  `activated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime GENERATED ALWAYS AS ((`activated_at` + interval `duration_h` hour)) STORED,
  `source` enum('trend','manual','auto') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'auto'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Jednorazowe szoki popytowe';

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `market_offers`
--

CREATE TABLE `market_offers` (
  `id` int NOT NULL,
  `player_id` int NOT NULL,
  `amount` int NOT NULL,
  `locked_amount` int DEFAULT '0',
  `limit_price` int NOT NULL,
  `status` enum('pending','completed','cancelled') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `auto_execute` tinyint(1) DEFAULT '1',
  `editable` tinyint(1) DEFAULT '1',
  `cancellation_fee` decimal(3,2) DEFAULT '0.10',
  `sold_amount` int DEFAULT '0',
  `sold_price` int DEFAULT '0',
  `created_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `market_offers`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `market_state`
--

CREATE TABLE `market_state` (
  `id` int NOT NULL,
  `base_price` int NOT NULL,
  `current_price` int NOT NULL,
  `volatility` int NOT NULL,
  `last_market_tick_at` datetime NOT NULL,
  `supply_index` decimal(12,2) NOT NULL DEFAULT '1000.00' COMMENT 'Łączna podaż ropy w systemie (bbl)',
  `demand_index` decimal(12,2) NOT NULL DEFAULT '1000.00' COMMENT 'Globalny popyt (startuje na 1000)',
  `world_production` decimal(12,2) NOT NULL DEFAULT '800.00' COMMENT 'Produkcja NPC-świata per tick (bbl)',
  `demand_base` decimal(12,2) NOT NULL DEFAULT '1000.00' COMMENT 'Bazowy popyt bez sezonowości i szoków',
  `season_factor` decimal(5,3) NOT NULL DEFAULT '1.000' COMMENT 'Mnożnik sezonowy',
  `last_supply_tick` datetime DEFAULT NULL COMMENT 'Kiedy ostatnio przeliczono supply',
  `last_demand_update` datetime DEFAULT NULL COMMENT 'Kiedy ostatnio zaktualizowano demand_index'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `market_state`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `market_supply_demand_log`
--

CREATE TABLE `market_supply_demand_log` (
  `id` int UNSIGNED NOT NULL,
  `supply` decimal(12,2) NOT NULL,
  `demand` decimal(12,2) NOT NULL,
  `ratio` decimal(8,4) NOT NULL COMMENT 'supply/demand',
  `price` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Historia supply/demand per tick';

--
-- Dumping data for table `market_supply_demand_log`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `market_trends`
--

CREATE TABLE `market_trends` (
  `id` int NOT NULL,
  `trend_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `category` enum('economic','political','environmental','technological','social','military') COLLATE utf8mb4_general_ci NOT NULL,
  `price_modifier` decimal(3,2) NOT NULL,
  `duration_hours` int NOT NULL,
  `message_template` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `active` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `activated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `market_trends`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `name_pool`
--

CREATE TABLE `name_pool` (
  `id` int NOT NULL,
  `type` enum('first_name','last_name') COLLATE utf8mb4_general_ci NOT NULL,
  `value` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `nationality` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `gender` enum('M','F','N') COLLATE utf8mb4_general_ci DEFAULT 'N'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `name_pool`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `nav_items`
--

CREATE TABLE `nav_items` (
  `id` int NOT NULL,
  `label` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'klucz do funkcji url() lub pełny URL zaczynający się od /',
  `icon` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sort_order` smallint NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `css_class` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'np. btn-danger dla Wyloguj',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `location` enum('header','footer','actions') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'header'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `nav_items`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `newsletter_log`
--

CREATE TABLE `newsletter_log` (
  `id` int NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body_html` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `sent_to` int NOT NULL DEFAULT '0',
  `sent_by` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sent_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('sent','failed','partial') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sent',
  `notes` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `newsletter_log`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `notification_history`
--

CREATE TABLE `notification_history` (
  `id` int UNSIGNED NOT NULL,
  `notification_id` int UNSIGNED NOT NULL,
  `player_id` int NOT NULL,
  `action` enum('created','read','dismissed','acted') COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log powiadomień dyrektora';

--
-- Dumping data for table `notification_history`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `offline_reports`
--

CREATE TABLE `offline_reports` (
  `id` int NOT NULL,
  `player_id` int NOT NULL,
  `offline_from` datetime NOT NULL,
  `offline_to` datetime NOT NULL,
  `offline_hours` decimal(6,2) NOT NULL DEFAULT '0.00',
  `was_frozen` tinyint(1) NOT NULL DEFAULT '0',
  `revenue_lost` decimal(14,2) NOT NULL DEFAULT '0.00',
  `opex_saved` decimal(14,2) NOT NULL DEFAULT '0.00',
  `summary_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `shown` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `token_hash` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `pipelines`
--

CREATE TABLE `pipelines` (
  `id` int UNSIGNED NOT NULL,
  `player_id` int UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Rurociąg główny',
  `capacity_bbl_h` int UNSIGNED NOT NULL DEFAULT '2000',
  `condition_pct` tinyint UNSIGNED NOT NULL DEFAULT '100',
  `status` enum('planned','building','active','damaged','leak','paused') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `last_inspected_at` datetime DEFAULT NULL COMMENT 'Ostatnia inspekcja przez Inżyniera Rurociągów',
  `damaged_at` datetime DEFAULT NULL,
  `transport_loss` decimal(4,2) NOT NULL DEFAULT '1.50',
  `built_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `build_started_at` datetime DEFAULT NULL,
  `build_finish_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pipelines`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `players`
--

CREATE TABLE `players` (
  `id` int NOT NULL,
  `username` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `cash` decimal(20,2) NOT NULL DEFAULT '50000.00',
  `status` enum('active','financial_risk','under_bailiff','bankrupt') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_tick_at` datetime NOT NULL,
  `bankruptcy_at` timestamp NULL DEFAULT NULL,
  `bankruptcy_status` enum('none','restructuring','liquidation','recovered') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'none',
  `safety_procedures_level` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `procedure_integrity` tinyint UNSIGNED NOT NULL DEFAULT '100',
  `procedures_last_decay_at` datetime DEFAULT NULL,
  `recovery_mode` tinyint(1) NOT NULL DEFAULT '0',
  `financial_state` enum('normal','warning','crisis') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'normal',
  `crisis_ticks` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `credit_score` int DEFAULT '50',
  `failed_attempts` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `lock_until` datetime DEFAULT NULL,
  `company_name` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `avatar_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_active_at` datetime DEFAULT NULL,
  `offline_mode` tinyint(1) NOT NULL DEFAULT '0',
  `offline_since` datetime DEFAULT NULL,
  `last_crisis_tick_at` datetime DEFAULT NULL,
  `black_market_score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `email_verified` tinyint(1) NOT NULL DEFAULT '0',
  `email_verified_at` datetime DEFAULT NULL,
  `newsletter_subscribed` tinyint(1) NOT NULL DEFAULT '1',
  `newsletter_token` varchar(32) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bailiff_count` int DEFAULT '0' COMMENT 'Liczba postępowań komorniczych (historia)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `players`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `player_finance_decisions`
--

CREATE TABLE `player_finance_decisions` (
  `id` int NOT NULL,
  `player_id` int NOT NULL,
  `decision_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_value` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `new_value` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'player',
  `effect_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `player_finance_decisions`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `player_finance_settings`
--

CREATE TABLE `player_finance_settings` (
  `player_id` int NOT NULL,
  `technical_budget` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `logistics_budget` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `hr_budget` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `safety_budget` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `reserve_policy` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `savings_plan_mode` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'off',
  `savings_plan_changed_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `player_finance_settings`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `player_meta`
--

CREATE TABLE `player_meta` (
  `id` int UNSIGNED NOT NULL,
  `player_id` int NOT NULL,
  `meta_key` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `meta_value` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Metadane gracza — klucz-wartość, np. liczniki tickowe';

--
-- Dumping data for table `player_meta`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `ports`
--

CREATE TABLE `ports` (
  `id` int NOT NULL,
  `region_id` int NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `port_type` enum('small','medium','large') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `throughput_per_tick` decimal(12,2) NOT NULL DEFAULT '500.00',
  `queue_limit` int NOT NULL DEFAULT '20',
  `handling_cost_per_bbl` decimal(8,4) NOT NULL DEFAULT '0.5000',
  `base_transit_hours` decimal(6,2) NOT NULL DEFAULT '3.00',
  `overload_risk_pct` decimal(5,2) NOT NULL DEFAULT '15.00',
  `failure_risk_per_tick` decimal(8,6) NOT NULL DEFAULT '0.001000',
  `status` enum('active','overloaded','damaged','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `port_queue`
--

CREATE TABLE `port_queue` (
  `id` bigint NOT NULL,
  `port_id` int NOT NULL,
  `delivery_id` bigint NOT NULL,
  `player_id` int NOT NULL,
  `volume_bbl` decimal(12,4) NOT NULL,
  `queued_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processing_started_at` datetime DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `status` enum('waiting','processing','done','abandoned') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'waiting'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `price_history`
--

CREATE TABLE `price_history` (
  `id` int NOT NULL,
  `price` int NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `price_history`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `recruitment_requests`
--

CREATE TABLE `recruitment_requests` (
  `id` int NOT NULL,
  `role_id` int NOT NULL,
  `region_code` varchar(10) COLLATE utf8mb4_general_ci DEFAULT 'PL',
  `player_id` int DEFAULT NULL,
  `initiated_by` enum('director','hr','technical') COLLATE utf8mb4_general_ci DEFAULT 'director',
  `recruitment_type` enum('local','international') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'local',
  `spec_code` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `requested_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ready_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `status` enum('pending','ready','completed','cancelled') COLLATE utf8mb4_general_ci DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recruitment_requests`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `regional_events`
--

CREATE TABLE `regional_events` (
  `id` int NOT NULL,
  `region_id` int NOT NULL,
  `player_id` int NOT NULL,
  `event_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `severity` tinyint NOT NULL DEFAULT '1',
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  `resolved` tinyint(1) DEFAULT '0',
  `message` text COLLATE utf8mb4_general_ci,
  `cooldown_until` datetime DEFAULT NULL COMMENT 'Cooldown — region nie może odpalić eventu przed tym czasem'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `regional_events`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `site_config`
--

CREATE TABLE `site_config` (
  `key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `site_config`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `staff_specializations`
--

CREATE TABLE `staff_specializations` (
  `code` varchar(40) COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Polska nazwa',
  `role` enum('operator','technician') COLLATE utf8mb4_general_ci NOT NULL,
  `rarity` enum('common','uncommon','rare') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'common',
  `description` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `prod_bonus` decimal(5,3) NOT NULL DEFAULT '0.000' COMMENT '+X% produkcji (tylko dla deep/ultra gdy only_deep_layers=1)',
  `only_deep_layers` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = efekt prod_bonus działa tylko na deep i ultra',
  `wear_reduction` decimal(5,3) NOT NULL DEFAULT '0.000' COMMENT 'Redukcja wear gain (0.20 = -20%)',
  `incident_reduction` decimal(5,3) NOT NULL DEFAULT '0.000' COMMENT 'Redukcja szansy awarii (0.15 = -15%)',
  `incident_return_reduction` decimal(5,3) NOT NULL DEFAULT '0.000' COMMENT 'Redukcja szansy powrotu awarii po naprawie',
  `spiral_reduction` decimal(5,3) NOT NULL DEFAULT '0.000' COMMENT 'Redukcja boost spirali katastrof',
  `catastrophe_reduction` decimal(5,3) NOT NULL DEFAULT '0.000' COMMENT 'Redukcja szansy katastrofy (blowout, spill)',
  `repair_speed` decimal(5,3) NOT NULL DEFAULT '0.000' COMMENT 'Przyspieszenie napraw (0.25 = -25% czasu)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Definicje specjalizacji (perków) pracowników';

--
-- Dumping data for table `staff_specializations`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `static_pages`
--

CREATE TABLE `static_pages` (
  `id` int NOT NULL,
  `slug` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '?',
  `content` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint NOT NULL DEFAULT '0',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `static_pages`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `storage`
--

CREATE TABLE `storage` (
  `player_id` int NOT NULL,
  `capacity` decimal(20,2) NOT NULL DEFAULT '1000.00',
  `used` decimal(20,2) NOT NULL DEFAULT '0.00',
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `storage`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `technical_notifications`
--

CREATE TABLE `technical_notifications` (
  `id` int UNSIGNED NOT NULL,
  `player_id` int UNSIGNED NOT NULL,
  `well_id` int UNSIGNED DEFAULT NULL,
  `type` enum('maintenance','failure','pipeline','pressure','production','drilling','task','hse_warning','hse_critical','disaster_blowout','disaster_pipeline_explosion','disaster_reservoir_contamination','disaster_surface_spill') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'task',
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `technical_notifications`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `technical_staff`
--

CREATE TABLE `technical_staff` (
  `id` int UNSIGNED NOT NULL,
  `player_id` int UNSIGNED NOT NULL,
  `manager_id` int UNSIGNED NOT NULL COMMENT 'board_members.id kierownika',
  `first_name` varchar(60) COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(60) COLLATE utf8mb4_general_ci NOT NULL,
  `spec_code` varchar(40) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'kod specjalizacji',
  `specialization` varchar(40) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Kod specjalizacji (perk) pracownika',
  `spec_name` varchar(80) COLLATE utf8mb4_general_ci NOT NULL,
  `experience_years` tinyint UNSIGNED NOT NULL DEFAULT '3',
  `skill_level` tinyint UNSIGNED NOT NULL DEFAULT '5' COMMENT '1-10',
  `salary` int UNSIGNED NOT NULL DEFAULT '8000',
  `status` enum('active','busy','on_leave','fired') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `hired_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fired_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `technical_staff`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `technical_tasks`
--

CREATE TABLE `technical_tasks` (
  `id` int UNSIGNED NOT NULL,
  `player_id` int UNSIGNED NOT NULL,
  `staff_id` int UNSIGNED NOT NULL COMMENT 'technical_staff.id',
  `task_type` enum('well_maintenance','well_repair','hub_maintenance','hub_repair','reservoir_analysis','production_optimization','install_module','pipeline_maintenance','pipeline_inspection','safety_audit','blowout_control','pipeline_repair','reservoir_rehabilitation','maintenance_service','implement_procedures','crisis_management','assign_operator','assign_technician') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'well_maintenance',
  `well_id` int UNSIGNED DEFAULT NULL COMMENT 'wells.id (nullable dla zadań ogólnych)',
  `hub_id` int DEFAULT NULL,
  `title` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `module_type` varchar(40) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `duration_hours` smallint UNSIGNED NOT NULL DEFAULT '6',
  `cost` decimal(14,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','in_progress','completed','failed','cancelled') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'in_progress',
  `result_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `notified` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

--
-- Dumping data for table `technical_tasks`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `technical_task_queue`
--

CREATE TABLE `technical_task_queue` (
  `id` int UNSIGNED NOT NULL,
  `player_id` int UNSIGNED NOT NULL,
  `staff_id` int UNSIGNED NOT NULL,
  `task_type` enum('well_maintenance','well_repair','hub_maintenance','hub_repair','reservoir_analysis','production_optimization','install_module','pipeline_maintenance','pipeline_inspection','safety_audit','blowout_control','pipeline_repair','reservoir_rehabilitation','maintenance_service','implement_procedures','crisis_management','assign_operator','assign_technician') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'well_maintenance',
  `well_id` int UNSIGNED DEFAULT NULL,
  `hub_id` int DEFAULT NULL,
  `module_type` varchar(40) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `priority` tinyint UNSIGNED NOT NULL DEFAULT '5',
  `queued_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `technical_task_queue`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `tick_stats`
--

CREATE TABLE `tick_stats` (
  `id` int NOT NULL,
  `ran_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cron',
  `duration_ms` int DEFAULT NULL,
  `oil_price` decimal(10,2) DEFAULT NULL,
  `trend_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trend_new` tinyint(1) NOT NULL DEFAULT '0',
  `bank_interest_processed` int DEFAULT NULL,
  `bank_installments_processed` int DEFAULT NULL,
  `bank_negotiations_resolved` int DEFAULT NULL,
  `bank_loan_decisions` int DEFAULT NULL,
  `hr_recruitments_processed` int DEFAULT NULL,
  `bankruptcy_processed` int DEFAULT NULL,
  `bankruptcy_recovered` int DEFAULT NULL,
  `players_processed` int DEFAULT NULL,
  `wells_active` int DEFAULT NULL,
  `total_production_bbl` decimal(14,4) DEFAULT NULL,
  `total_revenue_pln` decimal(16,2) DEFAULT NULL,
  `total_opex_pln` decimal(16,2) DEFAULT NULL,
  `disasters_triggered` int DEFAULT NULL,
  `incidents_triggered` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tick_stats`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `transport_config`
--

CREATE TABLE `transport_config` (
  `id` int NOT NULL,
  `transport_type` enum('rurociag','ciezarowki','tankowiec','nieustawiony') COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Typ transportu',
  `config_key` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `config_value` decimal(8,4) NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Edytowalne mnożniki transportu — admin/transport.php';

--
-- Dumping data for table `transport_config`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `wells`
--

CREATE TABLE `wells` (
  `id` int NOT NULL,
  `player_id` int NOT NULL,
  `level` int NOT NULL DEFAULT '1',
  `status` enum('active','paused_storage','paused_cash','paused_staff','no_operator','no_technician','broken','blowout','contaminated','seized','layer_switch','sold','equipment_swap') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `paused_staff_reason` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Brakujące specjalizacje przy paused_staff, np. "safety_officer,production_engineer"',
  `base_production_per_hour` decimal(10,2) NOT NULL DEFAULT '37.50',
  `upkeep_cost_per_hour` decimal(10,2) NOT NULL DEFAULT '1458.33',
  `technical_condition` int NOT NULL DEFAULT '100',
  `well_type` enum('onshore','offshore') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'onshore',
  `name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `location` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `upgrades` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `last_production_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL,
  `reservoir_remaining` decimal(14,2) NOT NULL DEFAULT '800000.00',
  `reservoir_max` decimal(14,2) NOT NULL DEFAULT '800000.00',
  `pressure` decimal(4,2) NOT NULL DEFAULT '1.00',
  `risk_level` tinyint UNSIGNED NOT NULL DEFAULT '10',
  `risk_score` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Skumulowane ryzyko 0–100. 0=bezpieczny, 100=krytyczny',
  `production_mode` enum('eco','normal','boost') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'normal' COMMENT 'Tryb produkcji: eco=-20% prod/-20% risk, normal=0, boost=+20% prod/+30% risk',
  `location_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pole naftowe',
  `depth_m` int UNSIGNED NOT NULL DEFAULT '2500',
  `production_boost_pct` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'bonus z Production Optimization',
  `location_id` int DEFAULT NULL,
  `region_id` int DEFAULT NULL,
  `zone_key` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `regional_tax_rate` decimal(5,4) DEFAULT '0.0300',
  `well_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `region_opex_mult` decimal(4,2) DEFAULT '1.00',
  `post_disaster_risk_boost` decimal(5,4) DEFAULT '0.0000' COMMENT 'Tymczasowy boost ryzyka po katastrofie (maleje z czasem)',
  `post_disaster_expires_at` datetime DEFAULT NULL,
  `operator_id` int UNSIGNED DEFAULT NULL COMMENT 'technical_staff.id aktywnego operatora',
  `technician_id` int UNSIGNED DEFAULT NULL COMMENT 'technical_staff.id aktywnego technika',
  `wear_level` decimal(5,2) NOT NULL DEFAULT '0.00',
  `equipment_tier` enum('black_market','standard','premium') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'standard',
  `equipment_upgrade_level` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `equipment_swap_until` datetime DEFAULT NULL,
  `equipment_swap_prev_status` varchar(32) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `post_incident_risk_boost` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `active_layer_id` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `layer_reservoir_used` bigint NOT NULL DEFAULT '0',
  `layer_switch_until` datetime DEFAULT NULL,
  `transport_type` enum('nieustawiony','rurociag','ciezarowki','tankowiec') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'nieustawiony' COMMENT 'Typ transportu ropy z odwiertu',
  `hub_outbound_transport_type` enum('nieustawiony','rurociag','ciezarowki','tankowiec') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'nieustawiony' COMMENT 'Typ transportu z hubu do magazynu (odcinek 2)',
  `transport_capacity_pct` decimal(5,2) NOT NULL DEFAULT '120.00' COMMENT 'Przepustowość transportu jako % produkcji (120=rurociąg, 70=ciężarówki, 110=tankowiec)',
  `transport_opex_pct` decimal(5,2) NOT NULL DEFAULT '7.50' COMMENT 'Dodatkowy OPEX transportu jako % wartości ropy (%)',
  `sold_at` datetime DEFAULT NULL
) ;

--
-- Dumping data for table `wells`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `wells_for_sale`
--

CREATE TABLE `wells_for_sale` (
  `id` int NOT NULL,
  `location_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `base_production` int NOT NULL,
  `base_cost` decimal(15,2) NOT NULL,
  `upkeep_cost` decimal(10,2) NOT NULL,
  `well_type` enum('onshore','offshore') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'onshore',
  `production_min` decimal(8,2) NOT NULL DEFAULT '10.00',
  `production_max` decimal(8,2) NOT NULL DEFAULT '60.00',
  `technical_condition` int NOT NULL DEFAULT '100',
  `description` text COLLATE utf8mb4_general_ci,
  `available` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `region_id` int DEFAULT NULL,
  `location_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wells_for_sale`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `well_config`
--

CREATE TABLE `well_config` (
  `key` varchar(60) COLLATE utf8mb4_general_ci NOT NULL,
  `value` decimal(15,2) NOT NULL,
  `label` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `category` varchar(60) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'general',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `well_config`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `well_events`
--

CREATE TABLE `well_events` (
  `id` int NOT NULL,
  `well_id` int NOT NULL,
  `player_id` int NOT NULL,
  `event_type` enum('maintenance','repair','pump_replaced','upgrade','failure','inspection') COLLATE utf8mb4_general_ci NOT NULL,
  `cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `description` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `technical_condition_before` int DEFAULT NULL,
  `technical_condition_after` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `well_events`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `well_incidents`
--

CREATE TABLE `well_incidents` (
  `id` int UNSIGNED NOT NULL,
  `well_id` int UNSIGNED NOT NULL,
  `player_id` int UNSIGNED NOT NULL,
  `level` enum('micro','minor','medium','major') COLLATE utf8mb4_general_ci NOT NULL,
  `cause_type` enum('operator','technician','hse','system') COLLATE utf8mb4_general_ci NOT NULL,
  `prod_drop` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `hours` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `deg_damage` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `cost` int UNSIGNED NOT NULL DEFAULT '0',
  `risk_add` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `auto_repair` tinyint(1) NOT NULL DEFAULT '1',
  `hse_active` tinyint(1) NOT NULL DEFAULT '0',
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `repaired_at` datetime DEFAULT NULL,
  `repaired_by` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `well_incidents`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `well_offshore_configs`
--

CREATE TABLE `well_offshore_configs` (
  `id` int NOT NULL,
  `player_id` int NOT NULL,
  `well_id` int NOT NULL,
  `tanker_type` enum('small','medium','large') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'small',
  `shipment_capacity_bbl` decimal(10,2) NOT NULL DEFAULT '30.00',
  `cost_per_shipment` decimal(10,2) NOT NULL DEFAULT '800.00',
  `incident_risk_mult` decimal(6,3) NOT NULL DEFAULT '1.000',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `well_offshore_configs`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `well_offshore_incident_logs`
--

CREATE TABLE `well_offshore_incident_logs` (
  `id` int NOT NULL,
  `well_id` int NOT NULL,
  `player_id` int NOT NULL,
  `incident_type` enum('storm','breakdown','delay','piracy','cataclysm','sabotage') COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipments_total` smallint UNSIGNED NOT NULL DEFAULT '0',
  `shipments_lost` smallint UNSIGNED NOT NULL DEFAULT '0',
  `vol_lost_bbl` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `well_pipelines`
--

CREATE TABLE `well_pipelines` (
  `id` int NOT NULL,
  `player_id` int NOT NULL,
  `well_id` int NOT NULL,
  `hub_id` bigint UNSIGNED DEFAULT NULL,
  `leg` enum('inbound','outbound') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'inbound',
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pipeline_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `status` enum('active','degraded','critical','damaged','disabled','building','leak','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `condition_pct` decimal(6,2) NOT NULL DEFAULT '100.00',
  `transport_loss` decimal(6,2) NOT NULL DEFAULT '0.00',
  `nominal_capacity_bph` decimal(12,2) NOT NULL DEFAULT '0.00',
  `real_capacity_bph` decimal(12,2) NOT NULL DEFAULT '0.00',
  `degradation_rate_per_hour` decimal(8,4) NOT NULL DEFAULT '0.0500',
  `incident_risk_mult` decimal(8,4) NOT NULL DEFAULT '1.0000',
  `opex_per_tick` decimal(12,2) NOT NULL DEFAULT '140.00',
  `opex_per_bbl` decimal(12,4) NOT NULL DEFAULT '0.2500',
  `build_cost` decimal(12,2) NOT NULL DEFAULT '18000.00',
  `build_started_at` datetime DEFAULT NULL,
  `build_finish_at` datetime DEFAULT NULL,
  `last_inspected_at` datetime DEFAULT NULL,
  `last_maintenance_at` datetime DEFAULT NULL,
  `damaged_at` datetime DEFAULT NULL,
  `leak_started_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `well_pipelines`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `well_pipeline_events`
--

CREATE TABLE `well_pipeline_events` (
  `id` int NOT NULL,
  `player_id` int NOT NULL,
  `well_id` int NOT NULL,
  `pipeline_id` int NOT NULL,
  `event_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `level` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `well_pipeline_events`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `well_pipeline_tick_stats`
--

CREATE TABLE `well_pipeline_tick_stats` (
  `id` int NOT NULL,
  `player_id` int NOT NULL,
  `well_id` int NOT NULL,
  `pipeline_id` int NOT NULL,
  `tick_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `delta_hours` decimal(8,4) NOT NULL DEFAULT '1.0000',
  `condition_before` decimal(6,2) NOT NULL DEFAULT '0.00',
  `condition_after` decimal(6,2) NOT NULL DEFAULT '0.00',
  `loss_pct_before` decimal(6,2) NOT NULL DEFAULT '0.00',
  `loss_pct_after` decimal(6,2) NOT NULL DEFAULT '0.00',
  `opex_tick_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status_after` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `well_pipeline_tick_stats`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `well_road_configs`
--

CREATE TABLE `well_road_configs` (
  `id` int NOT NULL,
  `player_id` int NOT NULL,
  `well_id` int NOT NULL,
  `truck_type` enum('standard','heavy','armored') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `trip_capacity_bbl` decimal(10,2) NOT NULL DEFAULT '25.00',
  `cost_per_trip` decimal(10,2) NOT NULL DEFAULT '500.00',
  `incident_risk_mult` decimal(6,3) NOT NULL DEFAULT '1.000',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `well_road_configs`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `well_road_incident_logs`
--

CREATE TABLE `well_road_incident_logs` (
  `id` int NOT NULL,
  `well_id` int NOT NULL,
  `player_id` int NOT NULL,
  `incident_type` enum('theft','raid','accident','sabotage','route_block') COLLATE utf8mb4_unicode_ci NOT NULL,
  `trips_total` smallint UNSIGNED NOT NULL DEFAULT '0',
  `trips_lost` smallint UNSIGNED NOT NULL DEFAULT '0',
  `vol_lost_bbl` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `well_road_incident_logs`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `well_road_trips`
--

CREATE TABLE `well_road_trips` (
  `id` bigint UNSIGNED NOT NULL,
  `player_id` int NOT NULL,
  `well_id` int NOT NULL,
  `hub_id` bigint UNSIGNED DEFAULT NULL COMMENT 'hub docelowy (FK logistics_hubs.id)',
  `volume_bbl` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `delivered_bbl` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `truck_type` enum('standard','heavy','armored') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `trips_count` smallint UNSIGNED NOT NULL DEFAULT '1',
  `trip_hours` tinyint UNSIGNED NOT NULL DEFAULT '2',
  `status` enum('in_transit','delayed','lost','delivered') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_transit',
  `departure_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `eta_at` datetime NOT NULL,
  `arrived_at` datetime DEFAULT NULL,
  `incident_type` enum('theft','raid','accident','sabotage','route_block') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `incident_risk_mult` decimal(6,3) NOT NULL DEFAULT '1.000',
  `political_risk_level` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Road transport trips: well -> hub (time-based)';

--
-- Dumping data for table `well_road_trips`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `well_staff_assignments`
--

CREATE TABLE `well_staff_assignments` (
  `id` int UNSIGNED NOT NULL,
  `well_id` int NOT NULL,
  `player_id` int NOT NULL,
  `staff_id` int UNSIGNED NOT NULL,
  `role` enum('operator','technician') COLLATE utf8mb4_unicode_ci NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `unassigned_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Przypisanie operatora i technika do konkretnego odwiertu';

--
-- Dumping data for table `well_staff_assignments`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `well_upgrades`
--

CREATE TABLE `well_upgrades` (
  `id` int NOT NULL,
  `well_id` int NOT NULL,
  `upgrade_type` enum('pump_electric','monitoring','water_injection') COLLATE utf8mb4_general_ci NOT NULL,
  `installed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cost_paid` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `well_upgrades`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `world_locations`
--

CREATE TABLE `world_locations` (
  `id` int NOT NULL,
  `region_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `country_code` varchar(5) COLLATE utf8mb4_general_ci NOT NULL,
  `latitude` decimal(10,6) NOT NULL,
  `longitude` decimal(10,6) NOT NULL,
  `oil_richness` decimal(5,2) DEFAULT '1.00',
  `well_type` enum('onshore','offshore') COLLATE utf8mb4_general_ci DEFAULT 'onshore',
  `description` text COLLATE utf8mb4_general_ci,
  `available` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `entry_cost_override` decimal(14,2) DEFAULT NULL COMMENT 'Nadpisuje entry_cost regionu; NULL = użyj regionu',
  `tax_rate_override` decimal(5,4) DEFAULT NULL COMMENT 'Nadpisuje tax_rate regionu; NULL = użyj regionu',
  `tier` enum('starter','medium','advanced') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'medium' COMMENT 'Tier odwiertu: starter/medium/advanced'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `world_locations`
--


-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `world_regions`
--

CREATE TABLE `world_regions` (
  `id` int NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `entry_cost` decimal(14,2) NOT NULL DEFAULT '5000000.00',
  `production_bonus` decimal(5,4) NOT NULL DEFAULT '0.0000',
  `tax_rate` decimal(5,4) NOT NULL DEFAULT '0.0500',
  `political_risk` tinyint NOT NULL DEFAULT '1',
  `color_hex` varchar(7) COLLATE utf8mb4_general_ci DEFAULT '#c8a84b',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `opex_mult` decimal(4,2) NOT NULL DEFAULT '1.00' COMMENT 'Mnożnik kosztów operacyjnych (logistyka, infrastruktura)',
  `stability_bonus` decimal(4,3) NOT NULL DEFAULT '0.000' COMMENT 'Mnożnik degradacji: <1.0 = wolniejsza (USA), >1.0 = szybsza'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `world_regions`
--


--
-- Indeksy dla zrzutów tabel
--

--
-- Indeksy dla tabeli `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`);

--
-- Indeksy dla tabeli `admin_help_pages`
--
ALTER TABLE `admin_help_pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indeksy dla tabeli `admin_login_attempts`
--
ALTER TABLE `admin_login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_time` (`attempted_at`);

--
-- Indeksy dla tabeli `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_target_player` (`target_player_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_admin_id` (`admin_id`);

--
-- Indeksy dla tabeli `admin_news`
--
ALTER TABLE `admin_news`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_news_active` (`active`,`is_pinned`),
  ADD KEY `idx_news_created` (`created_at`);

--
-- Indeksy dla tabeli `admin_password_resets`
--
ALTER TABLE `admin_password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token` (`token_hash`),
  ADD KEY `idx_email` (`email`);

--
-- Indeksy dla tabeli `bailiff_proceedings`
--
ALTER TABLE `bailiff_proceedings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_id` (`loan_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_next_action` (`next_action_at`),
  ADD KEY `idx_player` (`player_id`),
  ADD KEY `idx_bp_loan_status` (`loan_id`,`status`),
  ADD KEY `idx_bp_player_status` (`player_id`,`status`),
  ADD KEY `idx_bailiff_suspended_reason` (`status`,`suspend_reason`,`suspended_until`);

--
-- Indeksy dla tabeli `bankruptcy_events`
--
ALTER TABLE `bankruptcy_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_player_created` (`player_id`,`created_at`),
  ADD KEY `idx_player_critical_open` (`player_id`,`is_critical`,`resolved_at`,`due_at`);

--
-- Indeksy dla tabeli `bank_negotiations`
--
ALTER TABLE `bank_negotiations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_player` (`player_id`),
  ADD KEY `idx_loan` (`loan_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_decision_due` (`decision_due_at`),
  ADD KEY `idx_player_status` (`player_id`,`status`),
  ADD KEY `idx_bn_decision_due` (`decision_due_at`),
  ADD KEY `idx_bn_player_status` (`player_id`,`status`),
  ADD KEY `idx_bn_loan_status` (`loan_id`,`status`),
  ADD KEY `idx_bank_neg_due` (`status`,`decision_due_at`);

--
-- Indeksy dla tabeli `bank_negotiation_events`
--
ALTER TABLE `bank_negotiation_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_neg` (`negotiation_id`),
  ADD KEY `idx_neg_events_neg` (`negotiation_id`,`created_at`),
  ADD KEY `idx_neg_created` (`negotiation_id`,`created_at`),
  ADD KEY `idx_bne_neg_created` (`negotiation_id`,`created_at`);

--
-- Indeksy dla tabeli `bank_settings`
--
ALTER TABLE `bank_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indeksy dla tabeli `bank_trust_log`
--
ALTER TABLE `bank_trust_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_player` (`player_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_trust_log_player` (`player_id`,`created_at`),
  ADD KEY `idx_player_created` (`player_id`,`created_at`),
  ADD KEY `idx_btl_player_created` (`player_id`,`created_at`);

--
-- Indeksy dla tabeli `bank_trust_scores`
--
ALTER TABLE `bank_trust_scores`
  ADD PRIMARY KEY (`player_id`);

--
-- Indeksy dla tabeli `black_market_offers`
--
ALTER TABLE `black_market_offers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bm_player_status` (`player_id`,`status`),
  ADD KEY `idx_bm_expires` (`expires_at`);

--
-- Indeksy dla tabeli `black_market_transactions`
--
ALTER TABLE `black_market_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bmt_player` (`player_id`),
  ADD KEY `idx_bmt_created` (`created_at`);

--
-- Indeksy dla tabeli `boardroom_config`
--
ALTER TABLE `boardroom_config`
  ADD PRIMARY KEY (`key`);

--
-- Indeksy dla tabeli `board_members`
--
ALTER TABLE `board_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_role_status` (`role_id`,`status`),
  ADD KEY `idx_bm_player` (`player_id`);

--
-- Indeksy dla tabeli `board_roles`
--
ALTER TABLE `board_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indeksy dla tabeli `candidates`
--
ALTER TABLE `candidates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_role_expires` (`role_id`,`expires_at`),
  ADD KEY `idx_cand_player` (`player_id`);

--
-- Indeksy dla tabeli `candidate_reviews`
--
ALTER TABLE `candidate_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_review` (`candidate_id`,`reviewer_member_id`),
  ADD KEY `idx_candidate` (`candidate_id`),
  ADD KEY `idx_reviewer` (`reviewer_member_id`),
  ADD KEY `idx_player` (`player_id`);

--
-- Indeksy dla tabeli `chat_bans`
--
ALTER TABLE `chat_bans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `player_id` (`player_id`),
  ADD KEY `idx_ban_player` (`player_id`);

--
-- Indeksy dla tabeli `chat_blocked_words`
--
ALTER TABLE `chat_blocked_words`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_word` (`word`);

--
-- Indeksy dla tabeli `chat_conversation_reads`
--
ALTER TABLE `chat_conversation_reads`
  ADD PRIMARY KEY (`player_id`,`partner_id`),
  ADD KEY `idx_chat_conv_reads_partner` (`partner_id`);

--
-- Indeksy dla tabeli `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_chat_created` (`created_at`),
  ADD KEY `idx_channel_id` (`channel`,`id`),
  ADD KEY `idx_dm` (`sender_id`,`receiver_id`),
  ADD KEY `idx_chat_admin` (`is_admin`,`is_deleted`),
  ADD KEY `idx_chat_pinned` (`is_pinned`,`is_deleted`);

--
-- Indeksy dla tabeli `chat_reports`
--
ALTER TABLE `chat_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_message` (`message_id`);

--
-- Indeksy dla tabeli `director_notifications`
--
ALTER TABLE `director_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_player_unread` (`player_id`,`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indeksy dla tabeli `disaster_message_templates`
--
ALTER TABLE `disaster_message_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type_hse_active` (`disaster_type`,`hse_active`,`is_active`);

--
-- Indeksy dla tabeli `drilling_projects`
--
ALTER TABLE `drilling_projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_player` (`player_id`);

--
-- Indeksy dla tabeli `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ev_player` (`player_id`),
  ADD KEY `idx_ev_token` (`token_hash`);

--
-- Indeksy dla tabeli `employee_certificates`
--
ALTER TABLE `employee_certificates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_member` (`member_id`);

--
-- Indeksy dla tabeli `employee_contracts`
--
ALTER TABLE `employee_contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_member_status` (`member_id`,`status`),
  ADD KEY `idx_contract_end` (`contract_end`);

--
-- Indeksy dla tabeli `employment_history`
--
ALTER TABLE `employment_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_member_action` (`member_id`,`action`);

--
-- Indeksy dla tabeli `failure_log`
--
ALTER TABLE `failure_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_player` (`player_id`),
  ADD KEY `idx_well` (`well_id`),
  ADD KEY `idx_fl_player_occurred` (`player_id`,`occurred_at`);

--
-- Indeksy dla tabeli `finance_logs`
--
ALTER TABLE `finance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_finance_player_tick` (`player_id`,`tick_at`);

--
-- Indeksy dla tabeli `game_help_pages`
--
ALTER TABLE `game_help_pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indeksy dla tabeli `geological_layers`
--
ALTER TABLE `geological_layers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indeksy dla tabeli `headhunter_candidates`
--
ALTER TABLE `headhunter_candidates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_search` (`search_id`),
  ADD KEY `idx_player` (`player_id`);

--
-- Indeksy dla tabeli `headhunter_searches`
--
ALTER TABLE `headhunter_searches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_player_status` (`player_id`,`status`);

--
-- Indeksy dla tabeli `hr_events`
--
ALTER TABLE `hr_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_player_read` (`player_id`,`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indeksy dla tabeli `hr_regions`
--
ALTER TABLE `hr_regions`
  ADD PRIMARY KEY (`code`);

--
-- Indeksy dla tabeli `hr_specializations`
--
ALTER TABLE `hr_specializations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indeksy dla tabeli `hub_road_trips`
--
ALTER TABLE `hub_road_trips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hub_trips_player` (`player_id`),
  ADD KEY `idx_hub_trips_hub` (`hub_id`),
  ADD KEY `idx_hub_trips_status` (`status`),
  ADD KEY `idx_hub_trips_eta` (`eta_at`),
  ADD KEY `idx_hub_trips_active` (`status`,`eta_at`);

--
-- Indeksy dla tabeli `industrial_disasters`
--
ALTER TABLE `industrial_disasters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_player` (`player_id`),
  ADD KEY `idx_well` (`well_id`),
  ADD KEY `idx_type` (`disaster_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_id_player_status` (`player_id`,`status`);

--
-- Indeksy dla tabeli `loans`
--
ALTER TABLE `loans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_id` (`player_id`),
  ADD KEY `idx_loans_remaining` (`remaining_amount`);

--
-- Indeksy dla tabeli `loan_applications`
--
ALTER TABLE `loan_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_decision_at` (`decision_at`),
  ADD KEY `idx_player` (`player_id`);

--
-- Indeksy dla tabeli `loan_payments`
--
ALTER TABLE `loan_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_id` (`player_id`),
  ADD KEY `idx_loan` (`loan_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indeksy dla tabeli `logistics_hubs`
--
ALTER TABLE `logistics_hubs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hubs_player` (`player_id`),
  ADD KEY `idx_hubs_region` (`region_id`),
  ADD KEY `idx_hubs_zone` (`zone_key`),
  ADD KEY `idx_hubs_status` (`status`),
  ADD KEY `idx_hubs_player_region` (`player_id`,`region_id`),
  ADD KEY `idx_hubs_region_zone` (`region_id`,`zone_key`);

--
-- Indeksy dla tabeli `logistics_hub_assignments`
--
ALTER TABLE `logistics_hub_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_assign_hub` (`hub_id`),
  ADD KEY `idx_assign_well` (`well_id`),
  ADD KEY `idx_assign_status` (`status`),
  ADD KEY `idx_assign_well_active` (`well_id`,`status`),
  ADD KEY `idx_assign_relink_finish` (`status`,`relink_finish_at`);

--
-- Indeksy dla tabeli `logistics_hub_config`
--
ALTER TABLE `logistics_hub_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_hubconfig` (`config_group`,`config_key`,`config_scope`);

--
-- Indeksy dla tabeli `logistics_hub_events`
--
ALTER TABLE `logistics_hub_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hubevents_player` (`player_id`),
  ADD KEY `idx_hubevents_hub` (`hub_id`),
  ADD KEY `idx_hubevents_read` (`is_read`),
  ADD KEY `idx_hubevents_type` (`event_type`);

--
-- Indeksy dla tabeli `logistics_hub_tick_stats`
--
ALTER TABLE `logistics_hub_tick_stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tickstats_hub` (`hub_id`),
  ADD KEY `idx_tickstats_time` (`tick_time`),
  ADD KEY `idx_tickstats_hub_time` (`hub_id`,`tick_time`);

--
-- Indeksy dla tabeli `logistics_region_zones`
--
ALTER TABLE `logistics_region_zones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_region_zone` (`region_id`,`zone_key`),
  ADD KEY `idx_zone_region` (`region_id`);

--
-- Indeksy dla tabeli `marine_deliveries`
--
ALTER TABLE `marine_deliveries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_marine_player` (`player_id`),
  ADD KEY `idx_marine_well` (`well_id`),
  ADD KEY `idx_marine_port` (`port_id`),
  ADD KEY `idx_marine_status` (`status`),
  ADD KEY `idx_marine_eta` (`eta_at`),
  ADD KEY `idx_marine_hub` (`hub_id`);

--
-- Indeksy dla tabeli `market_demand_log`
--
ALTER TABLE `market_demand_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indeksy dla tabeli `market_demand_shocks`
--
ALTER TABLE `market_demand_shocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indeksy dla tabeli `market_offers`
--
ALTER TABLE `market_offers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_player_status` (`player_id`,`status`),
  ADD KEY `idx_locked_amount` (`locked_amount`),
  ADD KEY `idx_editable` (`editable`),
  ADD KEY `idx_auto_execute` (`auto_execute`);

--
-- Indeksy dla tabeli `market_state`
--
ALTER TABLE `market_state`
  ADD PRIMARY KEY (`id`);

--
-- Indeksy dla tabeli `market_supply_demand_log`
--
ALTER TABLE `market_supply_demand_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indeksy dla tabeli `market_trends`
--
ALTER TABLE `market_trends`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`active`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_activated` (`activated_at`),
  ADD KEY `idx_mt_activated` (`activated_at`);

--
-- Indeksy dla tabeli `name_pool`
--
ALTER TABLE `name_pool`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type_nationality` (`type`,`nationality`);

--
-- Indeksy dla tabeli `nav_items`
--
ALTER TABLE `nav_items`
  ADD PRIMARY KEY (`id`);

--
-- Indeksy dla tabeli `newsletter_log`
--
ALTER TABLE `newsletter_log`
  ADD PRIMARY KEY (`id`);

--
-- Indeksy dla tabeli `notification_history`
--
ALTER TABLE `notification_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_player` (`player_id`),
  ADD KEY `idx_notification` (`notification_id`);

--
-- Indeksy dla tabeli `offline_reports`
--
ALTER TABLE `offline_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_offline_reports_player` (`player_id`),
  ADD KEY `idx_offline_reports_shown` (`player_id`,`shown`);

--
-- Indeksy dla tabeli `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token` (`token_hash`),
  ADD KEY `idx_email` (`email`);

--
-- Indeksy dla tabeli `pipelines`
--
ALTER TABLE `pipelines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_player` (`player_id`);

--
-- Indeksy dla tabeli `players`
--
ALTER TABLE `players`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_financial_state` (`financial_state`),
  ADD KEY `idx_nl_token` (`newsletter_token`);

--
-- Indeksy dla tabeli `player_finance_decisions`
--
ALTER TABLE `player_finance_decisions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pfd_player_created` (`player_id`,`created_at`);

--
-- Indeksy dla tabeli `player_finance_settings`
--
ALTER TABLE `player_finance_settings`
  ADD PRIMARY KEY (`player_id`);

--
-- Indeksy dla tabeli `player_meta`
--
ALTER TABLE `player_meta`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_player_meta` (`player_id`,`meta_key`),
  ADD KEY `idx_player_meta_player` (`player_id`);

--
-- Indeksy dla tabeli `ports`
--
ALTER TABLE `ports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ports_region` (`region_id`),
  ADD KEY `idx_ports_status` (`status`);

--
-- Indeksy dla tabeli `port_queue`
--
ALTER TABLE `port_queue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_port_queue_delivery` (`delivery_id`),
  ADD KEY `idx_port_queue_port` (`port_id`),
  ADD KEY `idx_port_queue_player` (`player_id`),
  ADD KEY `idx_port_queue_status` (`status`);

--
-- Indeksy dla tabeli `price_history`
--
ALTER TABLE `price_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indeksy dla tabeli `recruitment_requests`
--
ALTER TABLE `recruitment_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `idx_status_ready` (`status`,`ready_at`);

--
-- Indeksy dla tabeli `regional_events`
--
ALTER TABLE `regional_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `region_id` (`region_id`),
  ADD KEY `idx_re_player` (`player_id`),
  ADD KEY `idx_re_active` (`player_id`,`resolved`,`expires_at`),
  ADD KEY `idx_re_cooldown` (`player_id`,`region_id`,`cooldown_until`);

--
-- Indeksy dla tabeli `site_config`
--
ALTER TABLE `site_config`
  ADD PRIMARY KEY (`key`);

--
-- Indeksy dla tabeli `staff_specializations`
--
ALTER TABLE `staff_specializations`
  ADD PRIMARY KEY (`code`);

--
-- Indeksy dla tabeli `static_pages`
--
ALTER TABLE `static_pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indeksy dla tabeli `storage`
--
ALTER TABLE `storage`
  ADD PRIMARY KEY (`player_id`);

--
-- Indeksy dla tabeli `technical_notifications`
--
ALTER TABLE `technical_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_player_unread` (`player_id`,`is_read`);

--
-- Indeksy dla tabeli `technical_staff`
--
ALTER TABLE `technical_staff`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_player` (`player_id`),
  ADD KEY `idx_manager` (`manager_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_ts_player_spec_status` (`player_id`,`spec_code`,`status`);

--
-- Indeksy dla tabeli `technical_tasks`
--
ALTER TABLE `technical_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_player` (`player_id`),
  ADD KEY `idx_staff` (`staff_id`),
  ADD KEY `idx_well` (`well_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_end` (`end_time`),
  ADD KEY `idx_tt_player_type_status_end` (`player_id`,`task_type`,`status`,`end_time`);

--
-- Indeksy dla tabeli `technical_task_queue`
--
ALTER TABLE `technical_task_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff` (`staff_id`),
  ADD KEY `idx_player` (`player_id`);

--
-- Indeksy dla tabeli `tick_stats`
--
ALTER TABLE `tick_stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ran_at` (`ran_at`);

--
-- Indeksy dla tabeli `transport_config`
--
ALTER TABLE `transport_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_type_key` (`transport_type`,`config_key`);

--
-- Indeksy dla tabeli `wells`
--
ALTER TABLE `wells`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_id` (`player_id`),
  ADD KEY `idx_risk_score` (`player_id`,`risk_score`),
  ADD KEY `idx_wells_wear` (`wear_level`),
  ADD KEY `idx_wells_layer` (`active_layer_id`),
  ADD KEY `idx_wells_player_status` (`player_id`,`status`,`id`);

--
-- Indeksy dla tabeli `wells_for_sale`
--
ALTER TABLE `wells_for_sale`
  ADD PRIMARY KEY (`id`);

--
-- Indeksy dla tabeli `well_config`
--
ALTER TABLE `well_config`
  ADD PRIMARY KEY (`key`);

--
-- Indeksy dla tabeli `well_events`
--
ALTER TABLE `well_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `well_id` (`well_id`);

--
-- Indeksy dla tabeli `well_incidents`
--
ALTER TABLE `well_incidents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_well` (`well_id`,`created_at`),
  ADD KEY `idx_player` (`player_id`,`created_at`),
  ADD KEY `idx_level` (`level`);

--
-- Indeksy dla tabeli `well_offshore_configs`
--
ALTER TABLE `well_offshore_configs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_offshore_cfg_well` (`well_id`),
  ADD KEY `idx_offshore_cfg_player` (`player_id`);

--
-- Indeksy dla tabeli `well_offshore_incident_logs`
--
ALTER TABLE `well_offshore_incident_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_offshore_inc_well` (`well_id`),
  ADD KEY `idx_offshore_inc_player` (`player_id`),
  ADD KEY `idx_offshore_inc_created` (`created_at`);

--
-- Indeksy dla tabeli `well_pipelines`
--
ALTER TABLE `well_pipelines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_well_pipeline_well_leg` (`well_id`,`leg`),
  ADD KEY `idx_well_pipeline_player` (`player_id`),
  ADD KEY `idx_well_pipeline_status` (`status`),
  ADD KEY `idx_pipeline_build_finish` (`status`,`build_finish_at`);

--
-- Indeksy dla tabeli `well_pipeline_events`
--
ALTER TABLE `well_pipeline_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pipeline_events_player` (`player_id`),
  ADD KEY `idx_pipeline_events_pipeline` (`pipeline_id`),
  ADD KEY `idx_pipeline_events_well` (`well_id`);

--
-- Indeksy dla tabeli `well_pipeline_tick_stats`
--
ALTER TABLE `well_pipeline_tick_stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pipeline_tick_player` (`player_id`),
  ADD KEY `idx_pipeline_tick_pipeline` (`pipeline_id`),
  ADD KEY `idx_pipeline_tick_well` (`well_id`);

--
-- Indeksy dla tabeli `well_road_configs`
--
ALTER TABLE `well_road_configs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_road_cfg_well` (`well_id`),
  ADD KEY `idx_road_cfg_player` (`player_id`);

--
-- Indeksy dla tabeli `well_road_incident_logs`
--
ALTER TABLE `well_road_incident_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_road_inc_well` (`well_id`),
  ADD KEY `idx_road_inc_player` (`player_id`),
  ADD KEY `idx_road_inc_created` (`created_at`);

--
-- Indeksy dla tabeli `well_road_trips`
--
ALTER TABLE `well_road_trips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_road_trips_player` (`player_id`),
  ADD KEY `idx_road_trips_well` (`well_id`),
  ADD KEY `idx_road_trips_hub` (`hub_id`),
  ADD KEY `idx_road_trips_status` (`status`),
  ADD KEY `idx_road_trips_eta` (`eta_at`),
  ADD KEY `idx_road_trips_active` (`status`,`eta_at`);

--
-- Indeksy dla tabeli `well_staff_assignments`
--
ALTER TABLE `well_staff_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_well_active` (`well_id`,`role`,`unassigned_at`),
  ADD KEY `idx_staff_id` (`staff_id`),
  ADD KEY `idx_player_id` (`player_id`);

--
-- Indeksy dla tabeli `well_upgrades`
--
ALTER TABLE `well_upgrades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `well_upgrade_unique` (`well_id`,`upgrade_type`);

--
-- Indeksy dla tabeli `world_locations`
--
ALTER TABLE `world_locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `region_id` (`region_id`);

--
-- Indeksy dla tabeli `world_regions`
--
ALTER TABLE `world_regions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `admin_help_pages`
--
ALTER TABLE `admin_help_pages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `admin_login_attempts`
--
ALTER TABLE `admin_login_attempts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=352;

--
-- AUTO_INCREMENT for table `admin_news`
--
ALTER TABLE `admin_news`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `admin_password_resets`
--
ALTER TABLE `admin_password_resets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bailiff_proceedings`
--
ALTER TABLE `bailiff_proceedings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `bankruptcy_events`
--
ALTER TABLE `bankruptcy_events`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bank_negotiations`
--
ALTER TABLE `bank_negotiations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `bank_negotiation_events`
--
ALTER TABLE `bank_negotiation_events`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=133;

--
-- AUTO_INCREMENT for table `bank_settings`
--
ALTER TABLE `bank_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `bank_trust_log`
--
ALTER TABLE `bank_trust_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `black_market_offers`
--
ALTER TABLE `black_market_offers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=205701;

--
-- AUTO_INCREMENT for table `black_market_transactions`
--
ALTER TABLE `black_market_transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `board_members`
--
ALTER TABLE `board_members`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `board_roles`
--
ALTER TABLE `board_roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `candidates`
--
ALTER TABLE `candidates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=334;

--
-- AUTO_INCREMENT for table `candidate_reviews`
--
ALTER TABLE `candidate_reviews`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_bans`
--
ALTER TABLE `chat_bans`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `chat_blocked_words`
--
ALTER TABLE `chat_blocked_words`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=149;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `chat_reports`
--
ALTER TABLE `chat_reports`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `director_notifications`
--
ALTER TABLE `director_notifications`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `disaster_message_templates`
--
ALTER TABLE `disaster_message_templates`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- AUTO_INCREMENT for table `drilling_projects`
--
ALTER TABLE `drilling_projects`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employee_certificates`
--
ALTER TABLE `employee_certificates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_contracts`
--
ALTER TABLE `employee_contracts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `employment_history`
--
ALTER TABLE `employment_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `failure_log`
--
ALTER TABLE `failure_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `finance_logs`
--
ALTER TABLE `finance_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=232540;

--
-- AUTO_INCREMENT for table `game_help_pages`
--
ALTER TABLE `game_help_pages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `geological_layers`
--
ALTER TABLE `geological_layers`
  MODIFY `id` tinyint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `headhunter_candidates`
--
ALTER TABLE `headhunter_candidates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `headhunter_searches`
--
ALTER TABLE `headhunter_searches`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `hr_events`
--
ALTER TABLE `hr_events`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=385;

--
-- AUTO_INCREMENT for table `hr_specializations`
--
ALTER TABLE `hr_specializations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `hub_road_trips`
--
ALTER TABLE `hub_road_trips`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `industrial_disasters`
--
ALTER TABLE `industrial_disasters`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `loans`
--
ALTER TABLE `loans`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `loan_applications`
--
ALTER TABLE `loan_applications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loan_payments`
--
ALTER TABLE `loan_payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=975;

--
-- AUTO_INCREMENT for table `logistics_hubs`
--
ALTER TABLE `logistics_hubs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=423;

--
-- AUTO_INCREMENT for table `logistics_hub_assignments`
--
ALTER TABLE `logistics_hub_assignments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `logistics_hub_config`
--
ALTER TABLE `logistics_hub_config`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT for table `logistics_hub_events`
--
ALTER TABLE `logistics_hub_events`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `logistics_hub_tick_stats`
--
ALTER TABLE `logistics_hub_tick_stats`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14505;

--
-- AUTO_INCREMENT for table `logistics_region_zones`
--
ALTER TABLE `logistics_region_zones`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `marine_deliveries`
--
ALTER TABLE `marine_deliveries`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2818;

--
-- AUTO_INCREMENT for table `market_demand_log`
--
ALTER TABLE `market_demand_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `market_demand_shocks`
--
ALTER TABLE `market_demand_shocks`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `market_offers`
--
ALTER TABLE `market_offers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `market_supply_demand_log`
--
ALTER TABLE `market_supply_demand_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18197;

--
-- AUTO_INCREMENT for table `market_trends`
--
ALTER TABLE `market_trends`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `name_pool`
--
ALTER TABLE `name_pool`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=638;

--
-- AUTO_INCREMENT for table `nav_items`
--
ALTER TABLE `nav_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `newsletter_log`
--
ALTER TABLE `newsletter_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notification_history`
--
ALTER TABLE `notification_history`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `offline_reports`
--
ALTER TABLE `offline_reports`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `pipelines`
--
ALTER TABLE `pipelines`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `players`
--
ALTER TABLE `players`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `player_finance_decisions`
--
ALTER TABLE `player_finance_decisions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `player_meta`
--
ALTER TABLE `player_meta`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13071;

--
-- AUTO_INCREMENT for table `ports`
--
ALTER TABLE `ports`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `port_queue`
--
ALTER TABLE `port_queue`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `price_history`
--
ALTER TABLE `price_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22292;

--
-- AUTO_INCREMENT for table `recruitment_requests`
--
ALTER TABLE `recruitment_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=402;

--
-- AUTO_INCREMENT for table `regional_events`
--
ALTER TABLE `regional_events`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `static_pages`
--
ALTER TABLE `static_pages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `technical_notifications`
--
ALTER TABLE `technical_notifications`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5088;

--
-- AUTO_INCREMENT for table `technical_staff`
--
ALTER TABLE `technical_staff`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `technical_tasks`
--
ALTER TABLE `technical_tasks`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `technical_task_queue`
--
ALTER TABLE `technical_task_queue`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=741;

--
-- AUTO_INCREMENT for table `tick_stats`
--
ALTER TABLE `tick_stats`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11842;

--
-- AUTO_INCREMENT for table `transport_config`
--
ALTER TABLE `transport_config`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `wells`
--
ALTER TABLE `wells`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wells_for_sale`
--
ALTER TABLE `wells_for_sale`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `well_events`
--
ALTER TABLE `well_events`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20176;

--
-- AUTO_INCREMENT for table `well_incidents`
--
ALTER TABLE `well_incidents`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10276;

--
-- AUTO_INCREMENT for table `well_offshore_configs`
--
ALTER TABLE `well_offshore_configs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `well_offshore_incident_logs`
--
ALTER TABLE `well_offshore_incident_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `well_pipelines`
--
ALTER TABLE `well_pipelines`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `well_pipeline_events`
--
ALTER TABLE `well_pipeline_events`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `well_pipeline_tick_stats`
--
ALTER TABLE `well_pipeline_tick_stats`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3125;

--
-- AUTO_INCREMENT for table `well_road_configs`
--
ALTER TABLE `well_road_configs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `well_road_incident_logs`
--
ALTER TABLE `well_road_incident_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `well_road_trips`
--
ALTER TABLE `well_road_trips`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=774;

--
-- AUTO_INCREMENT for table `well_staff_assignments`
--
ALTER TABLE `well_staff_assignments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `well_upgrades`
--
ALTER TABLE `well_upgrades`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `world_locations`
--
ALTER TABLE `world_locations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT for table `world_regions`
--
ALTER TABLE `world_regions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
