<?php

declare(strict_types=1);

class Migration_20260513_110000_site_snapshot_export_comments
{
	public function run(): void
	{
		$pdo = Db::instance();

		foreach ([
			'mcp_tokens',
			'mcp_audit',
			'i18n_build_state',
			'i18n_tm_entries',
			'runtime_worker_instances',
			'runtime_worker_pause_requests',
			'runtime_site_locks',
		] as $table) {
			$this->appendTableCommentToken($pdo, $table, '__noexport');
		}

		foreach ([
			'email_queue_transactional',
			'email_queue_bulk',
			'queued_jobs',
			'email_queue_archive',
			'email_queue_dead_letter',
			'queued_jobs_archive',
			'queued_jobs_dead_letter',
			'email_outbox',
			'email_outbox_recipients',
			'email_attachments',
			'email_outbox_attachments',
		] as $table) {
			$this->appendTableCommentToken($pdo, $table, '__noexport:disaster_recovery');
		}
	}

	public function getDescription(): string
	{
		return 'Add explicit site snapshot export comments to operational tables.';
	}

	private function appendTableCommentToken(PDO $pdo, string $table, string $token): void
	{
		$current_comment = $this->getTableComment($pdo, $table);

		if ($current_comment === null) {
			return;
		}

		$tokens = [];

		foreach (explode(',', $current_comment) as $existing_token) {
			$existing_token = trim($existing_token);

			if ($existing_token !== '') {
				$tokens[strtolower($existing_token)] = $existing_token;
			}
		}

		$normalized_token = strtolower(trim($token));

		if (isset($tokens[$normalized_token])) {
			return;
		}

		$tokens[$normalized_token] = $token;
		$comment = implode(', ', array_values($tokens));
		$pdo->exec('ALTER TABLE `' . str_replace('`', '``', $table) . '` COMMENT = ' . $pdo->quote($comment));
	}

	private function getTableComment(PDO $pdo, string $table): ?string
	{
		$stmt = $pdo->prepare(
			"SELECT TABLE_COMMENT
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_TYPE = 'BASE TABLE'
			  AND TABLE_NAME = ?"
		);
		$stmt->execute([$table]);
		$value = $stmt->fetchColumn();

		return is_string($value) ? $value : null;
	}
}
