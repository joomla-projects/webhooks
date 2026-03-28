--
-- Table structure for table `#__webhooks`
--

CREATE TABLE IF NOT EXISTS `#__webhooks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `alias` varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '',
  `url` varchar(2048) NOT NULL DEFAULT '',
  `secret` varchar(255) NOT NULL DEFAULT '',
  `events` text NOT NULL,
  `conditions` text,
  `payload_mode` varchar(20) NOT NULL DEFAULT 'full',
  `batch_mode` varchar(20) NOT NULL DEFAULT 'individual',
  `retry_strategy` varchar(20) NOT NULL DEFAULT 'exponential',
  `retry_count` int NOT NULL DEFAULT 5,
  `retry_interval` int NOT NULL DEFAULT 60,
  `circuit_breaker_mode` varchar(20) NOT NULL DEFAULT 'disable',
  `circuit_breaker_threshold` int NOT NULL DEFAULT 50,
  `consecutive_failures` int NOT NULL DEFAULT 0,
  `disabled_at` datetime DEFAULT NULL,
  `circuit_breaker_half_open` tinyint NOT NULL DEFAULT 0,
  `verbose_logging` tinyint NOT NULL DEFAULT 0,
  `state` tinyint NOT NULL DEFAULT 1,
  `access` int unsigned NOT NULL DEFAULT 1,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  `checked_out` int unsigned DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  `ordering` int NOT NULL DEFAULT 0,
  `params` text NOT NULL,
  `verified` tinyint NOT NULL DEFAULT 0,
  `verify_token` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_state` (`state`),
  FULLTEXT KEY `idx_events` (`events`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `#__webhook_queue`
--

CREATE TABLE IF NOT EXISTS `#__webhook_queue` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `webhook_id` int unsigned NOT NULL,
  `event_name` varchar(255) NOT NULL DEFAULT '',
  `payload` mediumtext NOT NULL,
  `status` tinyint NOT NULL DEFAULT 0,
  `attempts` int NOT NULL DEFAULT 0,
  `next_attempt_at` datetime DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status_next_attempt` (`status`, `next_attempt_at`),
  KEY `idx_webhook_id` (`webhook_id`),
  KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `#__webhook_logs`
--

CREATE TABLE IF NOT EXISTS `#__webhook_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `webhook_id` int unsigned NOT NULL,
  `queue_id` bigint unsigned DEFAULT NULL,
  `event_name` varchar(255) NOT NULL DEFAULT '',
  `url` varchar(2048) NOT NULL DEFAULT '',
  `status_code` int DEFAULT NULL,
  `success` tinyint NOT NULL DEFAULT 0,
  `error_message` varchar(1024) DEFAULT NULL,
  `duration_ms` int DEFAULT NULL,
  `request_headers` text,
  `request_body` mediumtext,
  `response_headers` text,
  `response_body` mediumtext,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_webhook_id` (`webhook_id`),
  KEY `idx_created` (`created`),
  KEY `idx_success` (`success`),
  KEY `idx_event_name` (`event_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
