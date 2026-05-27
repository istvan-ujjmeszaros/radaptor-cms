<?php

declare(strict_types=1);

class Migration_20260527_121000_align_cms_mutation_audit_schema
{
	public function run(): void
	{
		$pdo = Db::instance();

		if (!$this->tableExists($pdo, 'cms_mutation_audit')) {
			return;
		}

		$pdo->exec(
			"UPDATE `cms_mutation_audit`
			SET `actor_type` = 'internal'
			WHERE `actor_type` IS NULL"
		);

		$pdo->exec(
			"ALTER TABLE `cms_mutation_audit`
				MODIFY COLUMN `cms_mutation_audit_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				MODIFY COLUMN `phase` VARCHAR(64) NOT NULL,
				MODIFY COLUMN `actor_type` VARCHAR(32) NOT NULL,
				MODIFY COLUMN `actor_user_id` BIGINT UNSIGNED NULL,
				MODIFY COLUMN `resource_id` BIGINT UNSIGNED NULL,
				MODIFY COLUMN `page_id` BIGINT UNSIGNED NULL,
				MODIFY COLUMN `widget_connection_id` BIGINT UNSIGNED NULL,
				MODIFY COLUMN `result_status` VARCHAR(64) NOT NULL DEFAULT 'success',
				MODIFY COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"
		);
	}

	public function getDescription(): string
	{
		return 'Align CMS mutation audit schema for existing installs.';
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

		return (bool)$stmt->fetchColumn();
	}
}
