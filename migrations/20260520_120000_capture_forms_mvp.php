<?php

declare(strict_types=1);

class Migration_20260520_120000_capture_forms_mvp
{
	public function run(): void
	{
		$pdo = Db::instance();

		$pdo->exec(
			"CREATE TABLE IF NOT EXISTS `form_definitions` (
				`definition_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`definition_slug` VARCHAR(128) NOT NULL,
				`kind` VARCHAR(32) NOT NULL DEFAULT 'capture',
				`source` VARCHAR(32) NOT NULL DEFAULT 'db' COMMENT 'shipped vs db origin for later form:sync/admin builder reconciliation',
				`status` VARCHAR(32) NOT NULL DEFAULT 'draft',
				`owner_user_id` INT NULL DEFAULT NULL,
				`security_json` LONGTEXT NOT NULL,
				`published_version_id` INT UNSIGNED NULL DEFAULT NULL,
				`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (`definition_id`),
				UNIQUE KEY `uq_form_definitions_definition_slug` (`definition_slug`),
				KEY `idx_form_definitions_kind_status` (`kind`, `status`),
				KEY `idx_form_definitions_published_version_id` (`published_version_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);

		$pdo->exec(
			"CREATE TABLE IF NOT EXISTS `form_definition_versions` (
				`version_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`definition_id` INT UNSIGNED NOT NULL,
				`version_number` INT UNSIGNED NOT NULL,
				`status` VARCHAR(32) NOT NULL DEFAULT 'draft',
				`descriptor_json` LONGTEXT NOT NULL,
				`descriptor_hash` CHAR(64) NOT NULL COMMENT 'Descriptor integrity hash for runtime skew detection and future publish cache checks',
				`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`published_at` DATETIME NULL DEFAULT NULL,
				PRIMARY KEY (`version_id`),
				UNIQUE KEY `uq_form_definition_versions_number` (`definition_id`, `version_number`),
				UNIQUE KEY `uq_form_definition_versions_hash` (`definition_id`, `descriptor_hash`),
				KEY `idx_form_definition_versions_status` (`definition_id`, `status`),
				CONSTRAINT `fk_form_definition_versions_definition`
					FOREIGN KEY (`definition_id`) REFERENCES `form_definitions` (`definition_id`)
					ON DELETE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);

		$pdo->exec(
			"CREATE TABLE IF NOT EXISTS `form_submissions` (
				`submission_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`definition_id` INT UNSIGNED NOT NULL,
				`version_id` INT UNSIGNED NOT NULL,
				`definition_slug` VARCHAR(128) NOT NULL,
				`payload_json` LONGTEXT NOT NULL,
				`user_id` INT NULL DEFAULT NULL,
				`locale` VARCHAR(20) NULL DEFAULT NULL,
				`ip_hash` CHAR(64) NULL DEFAULT NULL,
				`user_agent_hash` CHAR(64) NULL DEFAULT NULL,
				`host_page_id` INT NULL DEFAULT NULL,
				`widget_connection_id` INT NULL DEFAULT NULL,
				`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`submission_id`),
				KEY `idx_form_submissions_definition_created` (`definition_id`, `created_at`),
				KEY `idx_form_submissions_version` (`version_id`),
				KEY `idx_form_submissions_rate_limit` (`definition_id`, `ip_hash`, `created_at`),
				CONSTRAINT `fk_form_submissions_definition`
					FOREIGN KEY (`definition_id`) REFERENCES `form_definitions` (`definition_id`)
					ON DELETE RESTRICT,
				CONSTRAINT `fk_form_submissions_version`
					FOREIGN KEY (`version_id`) REFERENCES `form_definition_versions` (`version_id`)
					ON DELETE RESTRICT
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='__noexport:privacy'"
		);
	}

	public function getDescription(): string
	{
		return 'Create capture form definition, version, and submission tables.';
	}
}
