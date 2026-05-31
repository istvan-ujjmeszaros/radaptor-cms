<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CmsSiteSnapshotServiceTest extends TestCase
{
	private static bool $_runtime_bootstrapped = false;

	private bool $_transaction_started = false;

	protected function setUp(): void
	{
		self::bootstrapConsumerRuntime();

		if (!class_exists('Db') || !class_exists('CmsSiteSnapshotService')) {
			self::markTestSkipped('The Radaptor consumer app runtime is required for CMS site snapshot integration tests.');
		}

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

	public function testExportIncludesMigrationAndSeedTablesByDefault(): void
	{
		$snapshot = CmsSiteSnapshotService::exportSnapshot(true);

		$this->assertArrayHasKey('migrations', $snapshot['tables']);
		$this->assertArrayHasKey('seeds', $snapshot['tables']);
		$this->assertArrayHasKey('environment', $snapshot);
		$this->assertArrayHasKey('excluded_tables', $snapshot);
		$this->assertArrayNotHasKey('excluded_operational_tables', $snapshot);
	}

	public function testDryRunReportsEnvironmentMismatchWithoutBlockingValidation(): void
	{
		$snapshot = $this->snapshotWithDifferentEnvironment();

		$result = CmsSiteSnapshotService::importSnapshot($snapshot, true, true);

		$this->assertSame('success', $result['status']);
		$this->assertFalse($result['applied']);
		$this->assertSame('mismatch', $result['environment_check']['status']);
	}

	public function testApplyBlocksEnvironmentMismatchBeforeMutation(): void
	{
		$snapshot = $this->snapshotWithDifferentEnvironment();

		$result = CmsSiteSnapshotService::importSnapshot($snapshot, false, true);

		$this->assertSame('error', $result['status']);
		$this->assertFalse($result['applied']);
		$this->assertSame('mismatch', $result['environment_check']['status']);
		$this->assertContains(
			'Snapshot environment does not match the current environment. Re-run with --allow-environment-mismatch only if this restore target is intentional.',
			$result['errors']
		);
	}

	public function testAllowEnvironmentMismatchLeavesOtherApplyGuardsInCharge(): void
	{
		$snapshot = $this->snapshotWithDifferentEnvironment();

		$result = CmsSiteSnapshotService::importSnapshot($snapshot, false, false, true);

		$this->assertSame('error', $result['status']);
		$this->assertFalse($result['applied']);
		$this->assertSame('mismatch', $result['environment_check']['status']);
		$this->assertTrue($result['environment_check']['allowed']);
		$this->assertContains('Refusing destructive import without --replace.', $result['errors']);
		$this->assertNotContains(
			'Snapshot environment does not match the current environment. Re-run with --allow-environment-mismatch only if this restore target is intentional.',
			$result['errors']
		);
	}

	public function testSiteMigrationWorkerScopesComeFromRuntimeRegistryWhenAvailable(): void
	{
		$method = new ReflectionMethod(CmsSiteSnapshotService::class, 'migrationSensitiveWorkerScopes');
		$scopes = $method->invoke(null);

		$this->assertContains(
			[
				'worker_type' => 'email_queue',
				'queue_name' => 'transactional_email',
			],
			$scopes
		);

		if (class_exists('OutboundDeliveryWorker')) {
			$this->assertContains(
				[
					'worker_type' => 'outbound_delivery',
					'queue_name' => 'http_webhook',
				],
				$scopes
			);
		}
	}

	public function testWorkerPauseAggregationFailsClosedWhenAnyScopeDoesNotConfirm(): void
	{
		if (!class_exists('RuntimeWorkerRegistry') || !class_exists('RuntimeWorkerPauseControl')) {
			self::markTestSkipped('Runtime worker registry support is required for multi-scope pause aggregation tests.');
		}

		$worker_instance_id = RuntimeWorkerRegistry::register('outbound_delivery', 'http_webhook', ['test' => self::class]);

		if ($worker_instance_id === null) {
			self::markTestSkipped('Runtime worker registry tables are not available.');
		}

		$method = new ReflectionMethod(CmsSiteSnapshotService::class, 'requestAndWaitForWorkerPauses');
		$result = $method->invoke(null, 'site_migration_export', 'test_snapshot', [
			'pause_timeout_seconds' => 0,
			'allow_stale_workers' => false,
		]);

		$this->assertTrue($result['requested']);
		$this->assertFalse($result['confirmed']);
		$this->assertArrayHasKey('outbound_delivery/http_webhook', $result['confirmations']);
		$this->assertFalse($result['confirmations']['outbound_delivery/http_webhook']['confirmed']);
		$this->assertNotEmpty($result['confirmations']['outbound_delivery/http_webhook']['pending_workers']);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function snapshotWithDifferentEnvironment(): array
	{
		$snapshot = CmsSiteSnapshotService::exportSnapshot(true);
		$snapshot['environment']['database']['dbname'] = '__different_database__';

		return $snapshot;
	}

	private static function bootstrapConsumerRuntime(): void
	{
		if (self::$_runtime_bootstrapped) {
			return;
		}

		$bootstrap = getenv('RADAPTOR_APP_TEST_BOOTSTRAP') ?: '/app/bootstrap/bootstrap.testing.php';

		if (!is_file($bootstrap)) {
			self::markTestSkipped('Set RADAPTOR_APP_TEST_BOOTSTRAP or run from the Radaptor app container to execute CMS site snapshot integration tests.');
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
