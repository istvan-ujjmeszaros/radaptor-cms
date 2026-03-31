<?php

declare(strict_types=1);

final class I18nTranslationService
{
	/**
	 * Save or update a translation row through the canonical write path.
	 *
	 * @return array{
	 *   action: 'inserted'|'updated'|'skipped',
	 *   natural_key: string,
	 *   reason?: string
	 * }
	 */
	public static function saveTranslation(
		string $domain,
		string $key,
		string $context,
		string $locale,
		string $text,
		?bool $humanReviewed,
		bool $dryRun = false,
		?string $sourceTextOverride = null
	): array {
		$domain = trim($domain);
		$key = trim($key);
		$context = trim($context);
		$locale = trim($locale);
		$naturalKey = self::_buildNaturalKey($domain, $key, $context, $locale);

		if ($domain === '' || $key === '' || $locale === '') {
			throw new InvalidArgumentException('domain, key and locale are required');
		}

		if (trim($text) === '') {
			throw new InvalidArgumentException('text is required');
		}

		$message = self::_loadOrCreateMessage($domain, $key, $context, $sourceTextOverride, $dryRun);
		$existing = EntityI18n_translation::findById([
			'domain' => $domain,
			'key' => $key,
			'context' => $context,
			'locale' => $locale,
		]);

		$sourceHash = $message['source_hash'];
		$existingText = $existing !== null ? (string) $existing->text : '';
		$existingHumanReviewed = $existing !== null && self::_normalizeHumanReviewed($existing->human_reviewed ?? 0);
		$existingSourceHash = $existing !== null ? (string) $existing->source_hash_snapshot : '';
		$targetHumanReviewed = self::_resolveImportedHumanReviewed($existing, $humanReviewed);

		if ($existing !== null) {
			$unchanged = $existingText === $text
				&& $existingHumanReviewed === $targetHumanReviewed
				&& $existingSourceHash === $sourceHash;

			if ($unchanged) {
				return [
					'action' => 'skipped',
					'natural_key' => $naturalKey,
					'reason' => 'unchanged',
				];
			}
		}

		if ($dryRun) {
			return [
				'action' => $existing === null ? 'inserted' : 'updated',
				'natural_key' => $naturalKey,
			];
		}

		$pdo = Db::instance();
		$startedTransaction = !$pdo->inTransaction();

		if ($startedTransaction) {
			$pdo->beginTransaction();
		}

		try {
			if ($existing === null) {
				EntityI18n_translation::createFromArray([
					'domain' => $domain,
					'key' => $key,
					'context' => $context,
					'locale' => $locale,
					'text' => $text,
					'human_reviewed' => $targetHumanReviewed ? 1 : 0,
					'source_hash_snapshot' => $sourceHash,
				]);
			} else {
				$existing->patch([
					'domain' => $domain,
					'key' => $key,
					'context' => $context,
					'locale' => $locale,
					'text' => $text,
					'human_reviewed' => $targetHumanReviewed ? 1 : 0,
					'source_hash_snapshot' => $sourceHash,
				])->save();
			}

			self::_syncTmForMessageSignature($domain, $key, $context, $sourceHash, $locale);

			if ($startedTransaction) {
				$pdo->commit();
			}
		} catch (Throwable $e) {
			if ($startedTransaction && $pdo->inTransaction()) {
				$pdo->rollBack();
			}

			throw $e;
		}

		return [
			'action' => $existing === null ? 'inserted' : 'updated',
			'natural_key' => $naturalKey,
		];
	}

	/**
	 * Delete a translation through the canonical write path.
	 *
	 * @return array{
	 *   action: 'deleted'|'skipped',
	 *   natural_key: string,
	 *   reason?: string
	 * }
	 */
	public static function deleteTranslation(
		string $domain,
		string $key,
		string $context,
		string $locale,
		bool $dryRun = false
	): array {
		$domain = trim($domain);
		$key = trim($key);
		$context = trim($context);
		$locale = trim($locale);
		$naturalKey = self::_buildNaturalKey($domain, $key, $context, $locale);

		$existing = EntityI18n_translation::findById([
			'domain' => $domain,
			'key' => $key,
			'context' => $context,
			'locale' => $locale,
		]);

		if ($existing === null) {
			return [
				'action' => 'skipped',
				'natural_key' => $naturalKey,
				'reason' => 'already_absent',
			];
		}

		if ($dryRun) {
			return [
				'action' => 'deleted',
				'natural_key' => $naturalKey,
				'reason' => 'absent_from_import',
			];
		}

		$sourceHash = (string) ($existing->source_hash_snapshot ?? '');
		$pdo = Db::instance();
		$startedTransaction = !$pdo->inTransaction();

		if ($startedTransaction) {
			$pdo->beginTransaction();
		}

		try {
			EntityI18n_translation::delete([
				'domain' => $domain,
				'key' => $key,
				'context' => $context,
				'locale' => $locale,
			]);

			if ($sourceHash !== '') {
				self::_syncTmForMessageSignature($domain, $key, $context, $sourceHash, $locale);
			}

			if ($startedTransaction) {
				$pdo->commit();
			}
		} catch (Throwable $e) {
			if ($startedTransaction && $pdo->inTransaction()) {
				$pdo->rollBack();
			}

			throw $e;
		}

		return [
			'action' => 'deleted',
			'natural_key' => $naturalKey,
			'reason' => 'absent_from_import',
		];
	}

	/**
	 * @param array<string, string> $row
	 * @return array{
	 *   action: 'inserted'|'updated'|'skipped',
	 *   natural_key: string,
	 *   reason?: string
	 * }
	 */
	public static function applyImportRow(array $row, CsvImportMode $mode, bool $dryRun = false): array
	{
		$domain  = trim((string) ($row['domain'] ?? ''));
		$key     = trim((string) ($row['key'] ?? ''));
		$context = trim((string) ($row['context'] ?? ''));
		$locale  = trim((string) ($row['locale'] ?? ''));
		$text    = (string) ($row['text'] ?? '');
		$expectedText = (string) ($row['expected_text'] ?? '');
		$requestedHumanReviewed = self::_normalizeImportedHumanReviewed($row['human_reviewed'] ?? '');
		$naturalKey = self::_buildNaturalKey($domain, $key, $context, $locale);

		$existing = EntityI18n_translation::findById([
			'domain' => $domain,
			'key' => $key,
			'context' => $context,
			'locale' => $locale,
		]);

		if ($mode === CsvImportMode::InsertNew && $existing !== null) {
			return [
				'action' => 'skipped',
				'natural_key' => $naturalKey,
				'reason' => 'already_exists',
			];
		}

		if (trim($text) === '') {
			return [
				'action' => 'skipped',
				'natural_key' => $naturalKey,
				'reason' => 'empty_text',
			];
		}

		if (
			$existing !== null
			&& $expectedText !== ''
			&& (string) ($existing->text ?? '') !== $expectedText
		) {
			return [
				'action' => 'skipped',
				'natural_key' => $naturalKey,
				'reason' => 'expected_mismatch',
				'expected_text' => $expectedText,
				'actual_text' => (string) ($existing->text ?? ''),
			];
		}

		return self::saveTranslation(
			$domain,
			$key,
			$context,
			$locale,
			$text,
			$requestedHumanReviewed,
			$dryRun,
			(string) ($row['source_text'] ?? '')
		);
	}

	/**
	 * @return array{domain: string, key: string, context: string, source_text: string, source_hash: string}
	 */
	private static function _loadOrCreateMessage(
		string $domain,
		string $key,
		string $context,
		?string $sourceTextOverride,
		bool $dryRun
	): array {
		$message = EntityI18n_messag::findById([
			'domain' => $domain,
			'key' => $key,
			'context' => $context,
		]);

		if ($message !== null) {
			return [
				'domain' => (string) $message->domain,
				'key' => (string) $message->key,
				'context' => (string) $message->context,
				'source_text' => (string) ($message->source_text ?? ''),
				'source_hash' => (string) ($message->source_hash ?? ''),
			];
		}

		$sourceText = trim((string) ($sourceTextOverride ?? ''));

		if ($sourceText === '') {
			throw new RuntimeException(
				"Source message not found for domain='{$domain}', key='{$key}', context='{$context}'"
			);
		}

		$sourceHash = md5($sourceText);

		if (!$dryRun) {
			EntityI18n_messag::createFromArray([
				'domain' => $domain,
				'key' => $key,
				'context' => $context,
				'source_text' => $sourceText,
				'source_hash' => $sourceHash,
			]);
		}

		return [
			'domain' => $domain,
			'key' => $key,
			'context' => $context,
			'source_text' => $sourceText,
			'source_hash' => $sourceHash,
		];
	}

	private static function _syncTmForMessageSignature(
		string $domain,
		string $key,
		string $context,
		string $sourceHash,
		string $targetLocale
	): void {
		if ($sourceHash === '') {
			return;
		}

		I18nTm::syncForSignature($domain, $key, $context, $sourceHash, $targetLocale);
	}

	private static function _buildNaturalKey(string $domain, string $key, string $context, string $locale): string
	{
		return "domain={$domain}|key={$key}|context={$context}|locale={$locale}";
	}

	private static function _normalizeHumanReviewed(mixed $value): bool
	{
		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value)) {
			return $value === 1;
		}

		$value = mb_strtolower(trim((string) $value));

		return in_array($value, ['1', 'true', 'yes', 'on'], true);
	}

	private static function _normalizeImportedHumanReviewed(mixed $value): ?bool
	{
		$value = trim((string) $value);

		if ($value === '') {
			return null;
		}

		return self::_normalizeHumanReviewed($value);
	}

	private static function _resolveImportedHumanReviewed(?EntityI18n_translation $existing, ?bool $requestedHumanReviewed): bool
	{
		$existingHumanReviewed = $existing !== null && self::_normalizeHumanReviewed($existing->human_reviewed ?? 0);

		if ($requestedHumanReviewed === null) {
			return $existingHumanReviewed;
		}

		// Seed/CSV sync is allowed to promote reviewed state, but it must not silently
		// downgrade a manually reviewed translation back to unreviewed.
		if ($existingHumanReviewed && $requestedHumanReviewed === false) {
			return true;
		}

		return $requestedHumanReviewed;
	}
}
