<?php

declare(strict_types=1);

class Migration_20260613_120000_form_editor_history_version_link
{
	public function run(): void
	{
		$pdo = Db::instance();

		// Undo/redo steps remember which version row held the state, so stepping
		// re-activates that row instead of replaying the descriptor through the
		// autosave path (which duplicates rows when descriptor normalization has
		// changed since the row was written). No FK: a deleted row simply falls
		// back to the descriptor replay.
		$column = $pdo->query(
			"SHOW COLUMNS FROM `form_definition_edit_history` LIKE 'version_id'"
		)->fetch();

		if ($column === false) {
			$pdo->exec(
				"ALTER TABLE `form_definition_edit_history`
					ADD COLUMN `version_id` INT UNSIGNED NULL AFTER `session_token`"
			);
		}
	}
}
