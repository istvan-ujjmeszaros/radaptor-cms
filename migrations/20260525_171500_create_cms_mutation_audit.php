<?php

declare(strict_types=1);

class Migration_20260525_171500_create_cms_mutation_audit
{
	public function run(): void
	{
		$pdo = Db::instance();

		if ($this->tableExists($pdo, 'cms_mutation_audit')) {
			return;
		}

		$pdo->exec(
			"CREATE TABLE `cms_mutation_audit` (
				`cms_mutation_audit_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`correlation_id` CHAR(36) NOT NULL,
				`parent_correlation_id` CHAR(36) NULL,
				`phase` VARCHAR(64) NOT NULL,
				`operation` VARCHAR(190) NOT NULL,
				`actor_type` VARCHAR(32) NOT NULL,
				`actor_user_id` BIGINT UNSIGNED NULL,
				`cli_command` VARCHAR(190) NULL,
				`args_hash` CHAR(64) NULL,
				`args_redacted_json` JSON NULL,
				`resource_id` BIGINT UNSIGNED NULL,
				`page_id` BIGINT UNSIGNED NULL,
				`widget_connection_id` BIGINT UNSIGNED NULL,
				`resource_path` VARCHAR(1024) NULL,
				`slot_name` VARCHAR(190) NULL,
				`widget_name` VARCHAR(190) NULL,
				`result_status` VARCHAR(64) NOT NULL DEFAULT 'success',
				`affected_count` INT NOT NULL DEFAULT 0,
				`error_code` VARCHAR(190) NULL,
				`error_class` VARCHAR(190) NULL,
				`error_message` VARCHAR(512) NULL,
				`before_json` JSON NULL,
				`after_json` JSON NULL,
				`summary_json` JSON NULL,
				`metadata_json` JSON NULL,
				`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`cms_mutation_audit_id`),
				KEY `idx_cms_mutation_audit_correlation` (`correlation_id`),
				KEY `idx_cms_mutation_audit_parent_correlation` (`parent_correlation_id`),
				KEY `idx_cms_mutation_audit_operation_created` (`operation`, `created_at`),
				KEY `idx_cms_mutation_audit_created` (`created_at`),
				KEY `idx_cms_mutation_audit_resource` (`resource_id`),
				KEY `idx_cms_mutation_audit_page` (`page_id`),
				KEY `idx_cms_mutation_audit_widget_connection` (`widget_connection_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);
	}

	public function getDescription(): string
	{
		return 'Create CMS mutation audit log table.';
	}

	private function tableExists(PDO $pdo, string $table): bool
	{
		$stmt = $pdo->prepare(
			"SELECT 1
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_TYPE = 'BASE TABLE'
			  AND TABLE_NAME = ?"
		);
		$stmt->execute([$table]);

		return (bool) $stmt->fetchColumn();
	}
}
