<?php

declare(strict_types=1);

class Migration_20260514_121000_ensure_i18n_allow_source_match
{
	public function run(): void
	{
		$pdo = Db::instance();

		if (!$this->tableExists($pdo, 'i18n_translations')) {
			return;
		}

		if ($this->columnExists($pdo, 'i18n_translations', 'allow_source_match')) {
			return;
		}

		$after_column = $this->columnExists($pdo, 'i18n_translations', 'human_reviewed')
			? 'human_reviewed'
			: 'text';

		$pdo->exec(
			"ALTER TABLE `i18n_translations`
			ADD COLUMN `allow_source_match` TINYINT(1) NOT NULL DEFAULT 0 AFTER `{$after_column}`"
		);
	}

	public function getDescription(): string
	{
		return 'Ensure i18n translations can store intentional source-text matches.';
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

	private function columnExists(PDO $pdo, string $table, string $column): bool
	{
		$stmt = $pdo->prepare(
			"SELECT 1
			FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_NAME = ?
			  AND COLUMN_NAME = ?"
		);
		$stmt->execute([$table, $column]);

		return (bool) $stmt->fetchColumn();
	}
}
