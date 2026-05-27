<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class I18nTranslationServiceIntegrationTest extends TestCase
{
	private static bool $_runtime_bootstrapped = false;
	private bool $_transaction_started = false;

	protected function setUp(): void
	{
		self::bootstrapConsumerRuntime();

		$pdo = Db::instance();

		if (!$pdo->inTransaction()) {
			$pdo->beginTransaction();
			$this->_transaction_started = true;
		}
	}

	protected function tearDown(): void
	{
		if ($this->_transaction_started) {
			$pdo = Db::instance();

			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
		}

		$this->_transaction_started = false;
	}

	public function testImportUpdatesExistingSourceMessageFromIncomingSourceText(): void
	{
		$pdo = Db::instance();
		$domain = 'phpunit_i18n_source';
		$key = 'message_' . bin2hex(random_bytes(4));
		$context = '';
		$locale = 'en-US';
		$old_source_text = 'Old source text';
		$new_source_text = 'New source text';
		$translation_text = 'Translated source text';

		$pdo->prepare(
			'INSERT INTO i18n_messages (domain, `key`, context, source_text, source_hash)
			VALUES (?, ?, ?, ?, ?)'
		)->execute([$domain, $key, $context, $old_source_text, md5($old_source_text)]);

		$pdo->prepare(
			'INSERT INTO i18n_translations
				(domain, `key`, context, locale, text, human_reviewed, allow_source_match, source_hash_snapshot)
			VALUES (?, ?, ?, ?, ?, 0, 0, ?)'
		)->execute([$domain, $key, $context, $locale, $translation_text, md5($old_source_text)]);
		I18nTm::syncForSignature($domain, $key, $context, md5($old_source_text), $locale);

		$this->assertSame(1, $this->tmEntryCount($domain, $key, $context, $locale, md5($old_source_text)));
		$this->assertSame(0, $this->tmEntryCount($domain, $key, $context, $locale, md5($new_source_text)));

		$csv = $this->normalizedCsv([
			'domain' => $domain,
			'key' => $key,
			'context' => $context,
			'locale' => $locale,
			'source_text' => $new_source_text,
			'expected_text' => $translation_text,
			'human_reviewed' => '0',
			'allow_source_match' => '0',
			'text' => $translation_text,
		]);
		$dataset = new ImportExportDatasetI18nTranslations();

		$dry_run = $dataset->import($csv, [
			'format' => 'normalized',
			'mode' => CsvImportMode::Upsert->value,
			'expect_locale' => $locale,
			'dry_run' => '1',
		]);

		$this->assertSame(1, $dry_run['updated']);
		$this->assertSame(md5($old_source_text), $this->sourceHash($domain, $key, $context));
		$this->assertSame(1, $this->tmEntryCount($domain, $key, $context, $locale, md5($old_source_text)));
		$this->assertSame(0, $this->tmEntryCount($domain, $key, $context, $locale, md5($new_source_text)));

		$result = $dataset->import($csv, [
			'format' => 'normalized',
			'mode' => CsvImportMode::Upsert->value,
			'expect_locale' => $locale,
			'dry_run' => '0',
		]);

		$this->assertSame(1, $result['updated']);
		$this->assertSame(md5($new_source_text), $this->sourceHash($domain, $key, $context));

		$stmt = $pdo->prepare(
			'SELECT m.source_text, m.source_hash, t.text, t.source_hash_snapshot
			FROM i18n_messages m
			JOIN i18n_translations t ON t.domain = m.domain AND t.`key` = m.`key` AND t.context = m.context
			WHERE m.domain = ? AND m.`key` = ? AND m.context = ? AND t.locale = ?'
		);
		$stmt->execute([$domain, $key, $context, $locale]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		$this->assertIsArray($row);
		$this->assertSame($new_source_text, $row['source_text']);
		$this->assertSame(md5($new_source_text), $row['source_hash']);
		$this->assertSame($translation_text, $row['text']);
		$this->assertSame(md5($new_source_text), $row['source_hash_snapshot']);
		$this->assertSame(0, $this->tmEntryCount($domain, $key, $context, $locale, md5($old_source_text)));
		$this->assertSame(1, $this->tmEntryCount($domain, $key, $context, $locale, md5($new_source_text)));
	}

	public function testSourceMessageUpdateRollsBackWithCallerTransaction(): void
	{
		$pdo = Db::instance();
		$domain = 'phpunit_i18n_source';
		$key = 'rollback_' . bin2hex(random_bytes(4));
		$context = '';
		$locale = 'en-US';
		$old_source_text = 'Rollback old source';
		$new_source_text = 'Rollback new source';
		$translation_text = 'Rollback translation';

		$this->commitSetUpTransaction();

		try {
			$this->insertTranslationFixture($domain, $key, $context, $locale, $old_source_text, $translation_text);

			$pdo->beginTransaction();
			$result = I18nTranslationService::saveTranslation(
				$domain,
				$key,
				$context,
				$locale,
				$translation_text,
				false,
				false,
				$new_source_text,
				false
			);

			$this->assertSame('updated', $result['action']);
			$this->assertSame(md5($new_source_text), $this->sourceHash($domain, $key, $context));

			$pdo->rollBack();

			$this->assertSame(md5($old_source_text), $this->sourceHash($domain, $key, $context));
			$this->assertSame(md5($old_source_text), $this->translationSourceHash($domain, $key, $context, $locale));
		} finally {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}

			$this->deleteTranslationFixture($domain, $key, $context, $locale);
		}
	}

	public function testSourceMessageUpdatePrunesTmUsingExistingTranslationSnapshot(): void
	{
		$pdo = Db::instance();
		$domain = 'phpunit_i18n_source';
		$key = 'tm_snapshot_' . bin2hex(random_bytes(4));
		$context = '';
		$locale = 'hu-HU';
		$old_source_text = 'Snapshot old source';
		$new_source_text = 'Snapshot new source';
		$translation_text = 'Snapshot translation';

		$pdo->prepare(
			'INSERT INTO i18n_messages (domain, `key`, context, source_text, source_hash)
			VALUES (?, ?, ?, ?, ?)'
		)->execute([$domain, $key, $context, $new_source_text, md5($new_source_text)]);

		$pdo->prepare(
			'INSERT INTO i18n_translations
				(domain, `key`, context, locale, text, human_reviewed, allow_source_match, source_hash_snapshot)
			VALUES (?, ?, ?, ?, ?, 0, 0, ?)'
		)->execute([$domain, $key, $context, $locale, $translation_text, md5($old_source_text)]);

		I18nTm::record('en-US', $locale, $old_source_text, $translation_text, $domain, $key, $context, 'approved');

		$this->assertSame(1, $this->tmEntryCount($domain, $key, $context, $locale, md5($old_source_text)));
		$this->assertSame(0, $this->tmEntryCount($domain, $key, $context, $locale, md5($new_source_text)));

		$result = I18nTranslationService::saveTranslation(
			$domain,
			$key,
			$context,
			$locale,
			$translation_text,
			false,
			false,
			$new_source_text,
			false
		);

		$this->assertSame('updated', $result['action']);
		$this->assertSame(md5($new_source_text), $this->translationSourceHash($domain, $key, $context, $locale));
		$this->assertSame(0, $this->tmEntryCount($domain, $key, $context, $locale, md5($old_source_text)));
		$this->assertSame(1, $this->tmEntryCount($domain, $key, $context, $locale, md5($new_source_text)));
	}

	public function testWorkbenchDomainAndSearchFiltersDoNotReuseNamedPlaceholders(): void
	{
		$pdo = Db::instance();
		$domain = 'form_def';
		$key = 'phpunit_form_' . bin2hex(random_bytes(4)) . '.title';
		$context = '';
		$source_text = 'PHPUnit filtered form title';
		$translation_text = 'PHPUnit filtered form title';

		$pdo->prepare(
			'INSERT INTO i18n_messages (domain, `key`, context, source_text, source_hash)
			VALUES (?, ?, ?, ?, ?)'
		)->execute([$domain, $key, $context, $source_text, md5($source_text)]);

		$pdo->prepare(
			'INSERT INTO i18n_translations
				(domain, `key`, context, locale, text, human_reviewed, allow_source_match, source_hash_snapshot)
			VALUES (?, ?, ?, ?, ?, 1, 1, ?)'
		)->execute([$domain, $key, $context, 'en-US', $translation_text, md5($source_text)]);

		$result = I18nWorkbench::getTranslations('en-US', [
			'domain' => $domain,
			'search' => substr($key, 0, -6),
		], 0, 25);

		$this->assertGreaterThanOrEqual(1, $result['recordsFiltered']);
		$keys = array_column($result['data'], 'key');
		$this->assertContains($key, $keys);
	}

	/**
	 * @param array<string, string> $row
	 */
	private function normalizedCsv(array $row): string
	{
		$headers = [
			'domain',
			'key',
			'context',
			'locale',
			'source_text',
			'expected_text',
			'human_reviewed',
			'allow_source_match',
			'text',
		];
		$handle = fopen('php://temp', 'r+');

		if ($handle === false) {
			throw new RuntimeException('Unable to open temporary CSV buffer.');
		}

		fputcsv($handle, $headers, ',', '"', '');
		fputcsv($handle, array_map(static fn (string $header): string => $row[$header], $headers), ',', '"', '');
		rewind($handle);
		$csv = stream_get_contents($handle);
		fclose($handle);

		if ($csv === false) {
			throw new RuntimeException('Unable to read temporary CSV buffer.');
		}

		return $csv;
	}

	private function sourceHash(string $domain, string $key, string $context): string
	{
		$stmt = Db::instance()->prepare(
			'SELECT source_hash
			FROM i18n_messages
			WHERE domain = ? AND `key` = ? AND context = ?'
		);
		$stmt->execute([$domain, $key, $context]);

		return (string) $stmt->fetchColumn();
	}

	private function translationSourceHash(string $domain, string $key, string $context, string $locale): string
	{
		$stmt = Db::instance()->prepare(
			'SELECT source_hash_snapshot
			FROM i18n_translations
			WHERE domain = ? AND `key` = ? AND context = ? AND locale = ?'
		);
		$stmt->execute([$domain, $key, $context, $locale]);

		return (string) $stmt->fetchColumn();
	}

	private function tmEntryCount(string $domain, string $key, string $context, string $locale, string $source_hash): int
	{
		$stmt = Db::instance()->prepare(
			'SELECT COUNT(*)
			FROM i18n_tm_entries
			WHERE domain = ? AND source_key = ? AND context = ? AND target_locale = ? AND source_hash = ?'
		);
		$stmt->execute([$domain, $key, $context, $locale, $source_hash]);

		return (int) $stmt->fetchColumn();
	}

	private function commitSetUpTransaction(): void
	{
		if (!$this->_transaction_started) {
			return;
		}

		$pdo = Db::instance();

		if ($pdo->inTransaction()) {
			$pdo->commit();
		}

		$this->_transaction_started = false;
	}

	private function insertTranslationFixture(
		string $domain,
		string $key,
		string $context,
		string $locale,
		string $source_text,
		string $translation_text
	): void {
		$pdo = Db::instance();
		$source_hash = md5($source_text);

		$pdo->prepare(
			'INSERT INTO i18n_messages (domain, `key`, context, source_text, source_hash)
			VALUES (?, ?, ?, ?, ?)'
		)->execute([$domain, $key, $context, $source_text, $source_hash]);

		$pdo->prepare(
			'INSERT INTO i18n_translations
				(domain, `key`, context, locale, text, human_reviewed, allow_source_match, source_hash_snapshot)
			VALUES (?, ?, ?, ?, ?, 0, 0, ?)'
		)->execute([$domain, $key, $context, $locale, $translation_text, $source_hash]);
	}

	private function deleteTranslationFixture(string $domain, string $key, string $context, string $locale): void
	{
		$pdo = Db::instance();

		$pdo->prepare(
			'DELETE FROM i18n_translations
			WHERE domain = ? AND `key` = ? AND context = ? AND locale = ?'
		)->execute([$domain, $key, $context, $locale]);

		$pdo->prepare(
			'DELETE FROM i18n_messages
			WHERE domain = ? AND `key` = ? AND context = ?'
		)->execute([$domain, $key, $context]);
	}

	private static function bootstrapConsumerRuntime(): void
	{
		if (self::$_runtime_bootstrapped) {
			return;
		}

		$bootstrap = getenv('RADAPTOR_APP_TEST_BOOTSTRAP') ?: '/app/bootstrap/bootstrap.testing.php';

		if (!is_file($bootstrap)) {
			self::markTestSkipped('Set RADAPTOR_APP_TEST_BOOTSTRAP or run from the Radaptor app container to execute i18n integration tests.');
		}

		$bootstrap_path = realpath($bootstrap) ?: $bootstrap;
		$included_before = in_array($bootstrap_path, array_map(static fn (string $path): string => realpath($path) ?: $path, get_included_files()), true);

		require_once $bootstrap;

		if (!$included_before) {
			restore_error_handler();
			restore_exception_handler();
		}

		self::$_runtime_bootstrapped = true;
	}
}
