<?php

declare(strict_types=1);

class Migration_20260530_143000_harden_capture_form_hooks
{
	public function run(): void
	{
		$pdo = Db::instance();

		if ($this->tableExists('form_hook_targets') && $this->columnExists('form_hook_targets', 'secret_mask')) {
			$pdo->exec('ALTER TABLE `form_hook_targets` DROP COLUMN `secret_mask`');
		}

		if ($this->tableExists('form_hook_deliveries') && !$this->indexExists('form_hook_deliveries', 'idx_form_hook_deliveries_prune')) {
			$pdo->exec('ALTER TABLE `form_hook_deliveries` ADD INDEX `idx_form_hook_deliveries_prune` (`created_at`, `delivery_id`)');
		}
	}

	public function getDescription(): string
	{
		return 'Harden capture form hook persistence and delivery pruning indexes.';
	}

	private function columnExists(string $table, string $column): bool
	{
		$stmt = Db::instance()->prepare(
			"SELECT 1
			FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_NAME = ?
			  AND COLUMN_NAME = ?"
		);
		$stmt->execute([$table, $column]);

		return (bool)$stmt->fetchColumn();
	}

	private function tableExists(string $table): bool
	{
		$stmt = Db::instance()->prepare(
			"SELECT 1
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_NAME = ?"
		);
		$stmt->execute([$table]);

		return (bool)$stmt->fetchColumn();
	}

	private function indexExists(string $table, string $index): bool
	{
		$stmt = Db::instance()->prepare(
			"SELECT 1
			FROM information_schema.STATISTICS
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_NAME = ?
			  AND INDEX_NAME = ?"
		);
		$stmt->execute([$table, $index]);

		return (bool)$stmt->fetchColumn();
	}
}
