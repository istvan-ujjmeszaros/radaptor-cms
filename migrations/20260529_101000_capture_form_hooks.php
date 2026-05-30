<?php

declare(strict_types=1);

class Migration_20260529_101000_capture_form_hooks
{
	public function run(): void
	{
		$pdo = Db::instance();

		$pdo->exec(
			"CREATE TABLE IF NOT EXISTS `form_hook_targets` (
				`hook_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`definition_id` INT UNSIGNED NOT NULL,
				`target_kind` VARCHAR(64) NOT NULL,
				`enabled` TINYINT(1) NOT NULL DEFAULT 1,
				`label` VARCHAR(190) NOT NULL,
				`url` VARCHAR(2048) NULL DEFAULT NULL,
				`preset_key` VARCHAR(128) NULL DEFAULT NULL,
				`metadata_json` LONGTEXT NOT NULL,
				`excluded_field_keys_json` LONGTEXT NOT NULL,
				`enable_in_non_production` TINYINT(1) NOT NULL DEFAULT 0,
				`secret_ciphertext` LONGTEXT NULL DEFAULT NULL,
				`secret_nonce` VARCHAR(64) NULL DEFAULT NULL,
				`secret_tag` VARCHAR(64) NULL DEFAULT NULL,
				`created_by_user_id` INT NULL DEFAULT NULL,
				`updated_by_user_id` INT NULL DEFAULT NULL,
				`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (`hook_id`),
				KEY `idx_form_hook_targets_definition` (`definition_id`, `enabled`),
				KEY `idx_form_hook_targets_kind` (`target_kind`),
				CONSTRAINT `fk_form_hook_targets_definition`
					FOREIGN KEY (`definition_id`) REFERENCES `form_definitions` (`definition_id`)
					ON DELETE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='__noexport:privacy'"
		);

		$pdo->exec(
			"CREATE TABLE IF NOT EXISTS `form_hook_deliveries` (
				`delivery_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`hook_id` BIGINT UNSIGNED NULL DEFAULT NULL,
				`definition_id` INT UNSIGNED NOT NULL,
				`version_id` INT UNSIGNED NOT NULL,
				`submission_id` BIGINT UNSIGNED NOT NULL,
				`target_kind` VARCHAR(64) NOT NULL,
				`target_label` VARCHAR(190) NOT NULL,
				`status` VARCHAR(32) NOT NULL DEFAULT 'pending',
				`environment` VARCHAR(32) NOT NULL,
				`payload_json` LONGTEXT NULL DEFAULT NULL,
				`result_json` LONGTEXT NULL DEFAULT NULL,
				`error_code` VARCHAR(128) NULL DEFAULT NULL,
				`error_message` TEXT NULL DEFAULT NULL,
				`queued_at` DATETIME NULL DEFAULT NULL,
				`completed_at` DATETIME NULL DEFAULT NULL,
				`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (`delivery_id`),
				KEY `idx_form_hook_deliveries_form_recent` (`definition_id`, `delivery_id`),
				KEY `idx_form_hook_deliveries_submission` (`submission_id`),
				KEY `idx_form_hook_deliveries_hook` (`hook_id`),
				KEY `idx_form_hook_deliveries_status` (`status`, `created_at`),
				KEY `idx_form_hook_deliveries_prune` (`created_at`, `delivery_id`),
				CONSTRAINT `fk_form_hook_deliveries_hook`
					FOREIGN KEY (`hook_id`) REFERENCES `form_hook_targets` (`hook_id`)
					ON DELETE SET NULL,
				CONSTRAINT `fk_form_hook_deliveries_definition`
					FOREIGN KEY (`definition_id`) REFERENCES `form_definitions` (`definition_id`)
					ON DELETE CASCADE,
				CONSTRAINT `fk_form_hook_deliveries_version`
					FOREIGN KEY (`version_id`) REFERENCES `form_definition_versions` (`version_id`)
					ON DELETE CASCADE,
				CONSTRAINT `fk_form_hook_deliveries_submission`
					FOREIGN KEY (`submission_id`) REFERENCES `form_submissions` (`submission_id`)
					ON DELETE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='__noexport:privacy'"
		);
	}

	public function getDescription(): string
	{
		return 'Create capture form hook target configuration and delivery log tables.';
	}
}
