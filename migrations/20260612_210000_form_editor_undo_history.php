<?php

declare(strict_types=1);

class Migration_20260612_210000_form_editor_undo_history
{
	public function run(): void
	{
		$pdo = Db::instance();

		// Ordered edit-history ring for server-backed undo/redo in the unified form
		// editor. Distinct from form_definition_versions, whose unique descriptor hash
		// dedupes states and therefore cannot record the edit ORDER (A -> B -> A).
		$pdo->exec(
			"CREATE TABLE IF NOT EXISTS `form_definition_edit_history` (
				`history_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`definition_id` INT UNSIGNED NOT NULL,
				`seq` INT UNSIGNED NOT NULL,
				`descriptor_json` LONGTEXT NOT NULL,
				`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`history_id`),
				UNIQUE KEY `uq_form_definition_edit_history_seq` (`definition_id`, `seq`),
				CONSTRAINT `fk_form_definition_edit_history_definition`
					FOREIGN KEY (`definition_id`) REFERENCES `form_definitions` (`definition_id`)
					ON DELETE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);

		$column = $pdo->query(
			"SHOW COLUMNS FROM `form_definitions` LIKE 'edit_history_seq'"
		)->fetch();

		if ($column === false) {
			// Cursor into the edit history: rows with a higher seq are the redo tail.
			$pdo->exec(
				"ALTER TABLE `form_definitions`
					ADD COLUMN `edit_history_seq` INT UNSIGNED NOT NULL DEFAULT 0"
			);
		}
	}
}
