<?php

declare(strict_types=1);

class Migration_20260613_080000_form_editor_session_history
{
	public function run(): void
	{
		$pdo = Db::instance();

		// Undo/redo history becomes editing-session-scoped: each editor open mints a
		// random session token, history rows and the cursor key off it, and a reload
		// starts a clean session. Pre-session rows carry no token and are dropped.
		$column = $pdo->query(
			"SHOW COLUMNS FROM `form_definition_edit_history` LIKE 'session_token'"
		)->fetch();

		if ($column === false) {
			$pdo->exec('DELETE FROM `form_definition_edit_history`');
			$pdo->exec(
				"ALTER TABLE `form_definition_edit_history`
					ADD COLUMN `session_token` VARCHAR(64) NOT NULL DEFAULT '' AFTER `definition_id`,
					DROP KEY `uq_form_definition_edit_history_seq`,
					ADD UNIQUE KEY `uq_form_definition_edit_history_seq` (`definition_id`, `session_token`, `seq`)"
			);
		}

		// One row per (editing session, definition): holds the undo cursor and the
		// timestamps used to garbage-collect stale sessions.
		$pdo->exec(
			"CREATE TABLE IF NOT EXISTS `form_editor_sessions` (
				`session_token` VARCHAR(64) NOT NULL,
				`definition_id` INT UNSIGNED NOT NULL,
				`edit_cursor` INT UNSIGNED NOT NULL DEFAULT 0,
				`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (`session_token`, `definition_id`),
				KEY `idx_form_editor_sessions_updated` (`updated_at`),
				CONSTRAINT `fk_form_editor_sessions_definition`
					FOREIGN KEY (`definition_id`) REFERENCES `form_definitions` (`definition_id`)
					ON DELETE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);

		// The per-definition cursor is superseded by the per-session cursor.
		$column = $pdo->query(
			"SHOW COLUMNS FROM `form_definitions` LIKE 'edit_history_seq'"
		)->fetch();

		if ($column !== false) {
			$pdo->exec('ALTER TABLE `form_definitions` DROP COLUMN `edit_history_seq`');
		}
	}
}
