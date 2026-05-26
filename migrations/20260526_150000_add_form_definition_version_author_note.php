<?php

declare(strict_types=1);

class Migration_20260526_150000_add_form_definition_version_author_note
{
	public function run(): void
	{
		if (!$this->columnExists('form_definition_versions', 'author_note')) {
			Db::instance()->exec(
				"ALTER TABLE `form_definition_versions`
					ADD COLUMN `author_note` TEXT NULL DEFAULT NULL AFTER `descriptor_hash`"
			);
		}
	}

	public function getDescription(): string
	{
		return 'Add optional author notes to capture form definition versions.';
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
}
