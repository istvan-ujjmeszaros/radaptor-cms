<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FormHookBackendIntegrationTest extends TestCase
{
	private static bool $_runtime_bootstrapped = false;
	private static ?int $_runtime_schema_token = null;
	private bool $_transaction_started = false;
	private string|false|null $_previous_app_secret = null;
	private string $_suffix = '';

	protected function setUp(): void
	{
		self::bootstrapConsumerRuntime();
		self::requireHookBackendFiles();
		self::installHookTables();

		$this->_previous_app_secret = getenv('APP_SECRET');
		putenv('APP_SECRET=form-hook-backend-test-secret');
		$this->_suffix = strtolower(bin2hex(random_bytes(3)));

		foreach ([
			CaptureForm::class,
			FormCaptureDefinitionRepository::class,
			FormDefinitionResolver::class,
			EntityFormDefinition::class,
			EntityFormDefinitionVersion::class,
			EntityFormSubmission::class,
			EntityFormHookTarget::class,
			EntityFormHookDelivery::class,
			FormHookConfigService::class,
			FormHookInvocationService::class,
			EventFormHookSave::class,
			EventFormHookTargets::class,
		] as $class_name) {
			if (!class_exists($class_name)) {
				self::markTestSkipped('The Radaptor consumer app runtime with capture form hook backend classes is required.');
			}
		}

		$pdo = Db::instance();

		if (!$pdo->inTransaction()) {
			$pdo->beginTransaction();
			$this->_transaction_started = true;
		}

		$this->setRequestContext();
	}

	protected function tearDown(): void
	{
		$this->setRequestContext();

		if ($this->_transaction_started) {
			$pdo = Db::instance();

			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
		}

		$this->_transaction_started = false;
		$this->restoreAppSecret();
	}

	public static function tearDownAfterClass(): void
	{
		if (self::$_runtime_schema_token !== null && class_exists(DbSchemaData::class, false)) {
			DbSchemaData::popRuntimeSchema(self::$_runtime_schema_token);
			self::$_runtime_schema_token = null;
		}
	}

	public function testDiscoveryIncludesBuiltInTargetsAndPluralAliases(): void
	{
		$definition_slug = $this->slug('discover');
		$this->upsertCapture($definition_slug);
		$this->impersonateAndRequireRole('form_phase1_content_admin', RoleList::ROLE_CONTENT_ADMIN);

		$response = $this->runHookEvent(new EventFormHookTargets(), get: ['definition_slug' => $definition_slug]);
		$alias_response = $this->runHookEvent(new EventFormHooksList(), get: ['definition_slug' => $definition_slug]);

		$this->assertSame(200, $response['http_code']);
		$this->assertTrue($response['body']['ok']);
		$this->assertSame(200, $alias_response['http_code']);
		$this->assertTrue($alias_response['body']['ok']);
		$this->assertSame(
			[
				FormHookTargetRegistry::KIND_CUSTOM_HTTPS_WEBHOOK,
				FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
			],
			array_column($response['body']['data']['targets'], 'kind'),
		);
		$targets = [];

		foreach ($response['body']['data']['targets'] as $target) {
			$targets[(string)$target['kind']] = $target;
		}
		$this->assertTrue($targets[FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL]['metadata_schema']['to']['required']);
		$this->assertFalse($targets[FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL]['supports_url']);
		$this->assertFalse($targets[FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL]['supports_secret']);
		$this->assertContains('email', array_column($response['body']['data']['fields'], 'key'));
		$fields = [];

		foreach ($response['body']['data']['fields'] as $field) {
			$fields[(string)$field['key']] = $field;
		}
		$this->assertSame('Name', $fields['name_key']['label'] ?? null);
		$this->assertSame('name', $fields['name_key']['name'] ?? null);
		$this->assertSame('Email', $fields['email']['label'] ?? null);
		$this->assertSame('text', $fields['email']['type'] ?? null);
	}

	public function testConfigValidationAndRoleBoundaries(): void
	{
		$definition_slug = $this->slug('roles');
		$this->upsertCapture($definition_slug);
		$service = new FormHookConfigService();

		$this->impersonateAndRequireRole('form_phase1_content_admin', RoleList::ROLE_CONTENT_ADMIN);
		$email = $service->saveForForm($definition_slug, [
			'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
			'preset_key' => 'default',
			'metadata_json' => '{"to":"ops@example.com","subject":"New capture submission","reply_to_field_key":"email"}',
			'excluded_field_keys_json' => '["message"]',
		]);

		$this->assertSame(FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL, $email['hook']['target_kind']);
		$this->assertSame(['message'], $email['hook']['excluded_field_keys']);

		$external_email = $service->saveForForm($definition_slug, [
			'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
			'metadata_json' => '{"to":"external@example.com","subject":"External capture submission"}',
		]);

		$this->assertSame('external@example.com', $external_email['hook']['metadata']['to']);

		$this->expectConfigExceptionCode('FORM_HOOK_DEVELOPER_ROLE_REQUIRED', function () use ($service, $definition_slug): void {
			$service->saveForForm($definition_slug, [
				'target_kind' => FormHookTargetRegistry::KIND_CUSTOM_HTTPS_WEBHOOK,
				'url' => 'https://hooks.example.test/capture',
			]);
		});
		$this->expectConfigExceptionCode('FORM_HOOK_DEVELOPER_ROLE_REQUIRED', function () use ($service, $definition_slug): void {
			$service->saveForForm($definition_slug, [
				'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
				'preset_key' => 'default',
				'metadata_json' => '{"transport":"smtp://example.test"}',
			]);
		});
		$this->expectConfigExceptionCode('FORM_HOOK_UNKNOWN_EXCLUDED_FIELD_KEY', function () use ($service, $definition_slug): void {
			$service->saveForForm($definition_slug, [
				'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
				'preset_key' => 'default',
				'excluded_field_keys_json' => '["missing_field"]',
			]);
		});
		$this->expectConfigExceptionCode('FORM_HOOK_EMAIL_TO_REQUIRED', function () use ($service, $definition_slug): void {
			$service->saveForForm($definition_slug, [
				'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
				'preset_key' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
			]);
		});
		$this->expectConfigExceptionCode('FORM_HOOK_EMAIL_TO_INVALID', function () use ($service, $definition_slug): void {
			$service->saveForForm($definition_slug, [
				'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
				'preset_key' => 'default',
			]);
		});

		$this->impersonateAndRequireRole('form_phase1_system_developer', RoleList::ROLE_SYSTEM_DEVELOPER);
		$webhook = $service->saveForForm($definition_slug, [
			'target_kind' => FormHookTargetRegistry::KIND_CUSTOM_HTTPS_WEBHOOK,
			'url' => 'https://hooks.example.com/capture',
			'enable_in_non_production' => true,
			'secret' => 'hook-secret-value',
			'excluded_field_keys_json' => '["email"]',
		]);

		$this->assertSame(FormHookTargetRegistry::KIND_CUSTOM_HTTPS_WEBHOOK, $webhook['hook']['target_kind']);
		$this->assertTrue($webhook['hook']['enable_in_non_production']);
		$this->assertTrue($webhook['hook']['has_secret']);
		$this->assertArrayNotHasKey('secret_mask', $webhook['hook']);

		$empty_metadata_webhook = $service->saveForForm($definition_slug, [
			'target_kind' => FormHookTargetRegistry::KIND_CUSTOM_HTTPS_WEBHOOK,
			'url' => 'https://hooks.example.com/empty-metadata',
			'secret' => 'hook-secret-value',
			'metadata_json' => '{}',
		]);

		$this->assertSame([], $empty_metadata_webhook['hook']['metadata']);

		$converted = $service->saveForForm($definition_slug, [
			'hook_id' => $webhook['hook']['hook_id'],
			'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
			'metadata_json' => '{"to":"converted@example.com"}',
		]);

		$this->assertSame(FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL, $converted['hook']['target_kind']);
		$this->assertSame('', $converted['hook']['url']);
		$this->assertSame('', $converted['hook']['preset_key']);
		$this->assertFalse($converted['hook']['has_secret']);

		$this->expectConfigExceptionCode('FORM_HOOK_EMAIL_TO_INVALID', function () use ($service, $definition_slug): void {
			$service->saveForForm($definition_slug, [
				'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
				'metadata_json' => '{"to":"not-an-email"}',
			]);
		});
		$this->expectConfigExceptionCode('FORM_HOOK_URL_NOT_ALLOWED', function () use ($service, $definition_slug): void {
			$service->saveForForm($definition_slug, [
				'target_kind' => FormHookTargetRegistry::KIND_CUSTOM_HTTPS_WEBHOOK,
				'url' => 'https://localhost/capture',
				'secret' => 'hook-secret-value',
			]);
		});
		$this->expectConfigExceptionCode('FORM_HOOK_UNKNOWN_FIELD', function () use ($service, $definition_slug): void {
			$service->saveForForm($definition_slug, [
				'target_kind' => FormHookTargetRegistry::KIND_CUSTOM_HTTPS_WEBHOOK,
				'target_url' => 'https://hooks.example.com/capture',
				'secret' => 'hook-secret-value',
			]);
		});
	}

	public function testMutationRejectsStaleHookIdsAndMalformedJson(): void
	{
		$source_slug = $this->slug('stale-source');
		$target_slug = $this->slug('stale-target');
		$this->upsertCapture($source_slug);
		$this->upsertCapture($target_slug);
		$this->impersonateAndRequireRole('form_phase1_content_admin', RoleList::ROLE_CONTENT_ADMIN);
		$service = new FormHookConfigService();

		$created = $service->saveForForm($source_slug, [
			'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
			'metadata_json' => '{"to":"source@example.com"}',
		]);
		$stale_hook_id = (int)$created['hook']['hook_id'];

		$this->expectConfigExceptionCode('FORM_HOOK_NOT_FOUND', function () use ($service, $target_slug, $stale_hook_id): void {
			$service->saveForForm($target_slug, [
				'hook_id' => $stale_hook_id,
				'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
				'metadata_json' => '{"to":"target@example.com"}',
			]);
		});
		$this->assertSame(0, DbHelper::count('form_hook_targets', ['definition_id' => $this->definitionId($target_slug)]));

		$metadata_response = $this->runHookEvent(new EventFormHookSave(), [
			'definition_slug' => $target_slug,
			'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
			'metadata_json' => '{"to":',
		]);
		$this->assertSame(422, $metadata_response['http_code']);
		$this->assertFalse($metadata_response['body']['ok']);
		$this->assertSame('FORM_HOOK_INVALID_JSON', $metadata_response['body']['error']['code']);

		$excluded_response = $this->runHookEvent(new EventFormHookSave(), [
			'definition_slug' => $target_slug,
			'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
			'metadata_json' => '{"to":"target@example.com"}',
			'excluded_field_keys_json' => '["email"',
		]);
		$this->assertSame(422, $excluded_response['http_code']);
		$this->assertFalse($excluded_response['body']['ok']);
		$this->assertSame('FORM_HOOK_INVALID_JSON', $excluded_response['body']['error']['code']);
		$this->assertSame(0, DbHelper::count('form_hook_targets', ['definition_id' => $this->definitionId($target_slug)]));
	}

	public function testNonProductionSuppressionExcludedFieldsAndSubmitIntegration(): void
	{
		$definition_slug = $this->slug('submit');
		$resolution = $this->upsertCapture($definition_slug);
		$this->impersonateAndRequireRole('form_phase1_system_developer', RoleList::ROLE_SYSTEM_DEVELOPER);

		(new FormHookConfigService())->saveForForm($definition_slug, [
			'target_kind' => FormHookTargetRegistry::KIND_CUSTOM_HTTPS_WEBHOOK,
			'url' => 'https://hooks.example.com/capture',
			'secret' => 'hook-secret-value',
			'enable_in_non_production' => false,
			'excluded_field_keys_json' => '["email"]',
		]);

		$invalid = $this->captureForm($resolution)->process([
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'name_key' => '',
			'email' => 'not-email',
			'topic' => 'general',
			'message' => 'short',
			'company_website' => '',
		]);
		$denied = $this->captureForm($resolution)->process([
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'name_key' => 'Ada Lovelace',
			'email' => 'ada@example.test',
			'topic' => 'general',
			'message' => 'This message is long enough.',
			'company_website' => 'https://bot.example.test',
		]);

		$this->assertTrue($invalid->isInvalid());
		$this->assertTrue($denied->isDenied());
		$this->assertSame(0, DbHelper::count('form_hook_deliveries', ['definition_id' => $resolution->definitionId()]));

		$valid = $this->captureForm($resolution)->process($this->validPayload());

		$this->assertTrue($valid->isSuccess());
		$this->assertSame(1, DbHelper::count('form_submissions', ['definition_id' => $resolution->definitionId()]));
		$this->assertSame(1, DbHelper::count('form_hook_deliveries', ['definition_id' => $resolution->definitionId()]));

		$delivery = DbHelper::selectOne('form_hook_deliveries', ['definition_id' => $resolution->definitionId()]);
		$this->assertIsArray($delivery);
		$this->assertSame(FormHookResult::STATUS_SUPPRESSED, (string)$delivery['status']);
		$this->assertSame('FORM_HOOK_SUPPRESSED_NON_PRODUCTION', (string)$delivery['error_code']);

		$payload = json_decode((string)$delivery['payload_json'], true, 512, JSON_THROW_ON_ERROR);
		$this->assertSame('capture_form.submitted', $payload['event']);
		$this->assertSame('Ada Lovelace', $payload['data']['name_key']);
		$this->assertArrayNotHasKey('email', $payload['data']);
		$this->assertArrayNotHasKey('_submission_id', $payload['data']);

		$logs = (new FormHookConfigService())->deliveriesForForm($definition_slug, 5);
		$this->assertSame(FormHookResult::STATUS_SUPPRESSED, $logs['deliveries'][0]['status']);

		DbHelper::prexecute('UPDATE form_hook_deliveries SET created_at = ? WHERE delivery_id = ?', [
			date('Y-m-d H:i:s', time() - 31 * 86400),
			(int)$delivery['delivery_id'],
		]);
		$prune = (new FormHookInvocationService())->pruneExpiredDeliveries(30, false, 100);
		$this->assertSame(1, $prune['matched_rows']);
		$this->assertSame(1, $prune['deleted_rows']);
		$this->assertSame(0, DbHelper::count('form_hook_deliveries', ['definition_id' => $resolution->definitionId()]));
	}

	public function testHookDeliveryPruneDryRunApplyLimitAndIndex(): void
	{
		$definition_slug = $this->slug('prune');
		$resolution = $this->upsertCapture($definition_slug);
		$this->insertHookDelivery($resolution, 'old-a', date('Y-m-d H:i:s', time() - 31 * 86400));
		$this->insertHookDelivery($resolution, 'old-b', date('Y-m-d H:i:s', time() - 32 * 86400));
		$this->insertHookDelivery($resolution, 'new', date('Y-m-d H:i:s'));
		$service = new FormHookInvocationService();

		$this->assertTrue($this->indexExists('form_hook_deliveries', 'idx_form_hook_deliveries_prune'));
		$dry_run = $service->pruneExpiredDeliveries(30, true, 1);
		$this->assertSame(2, $dry_run['matched_rows']);
		$this->assertSame(0, $dry_run['deleted_rows']);
		$this->assertSame(3, DbHelper::count('form_hook_deliveries', ['definition_id' => $resolution->definitionId()]));

		$first_apply = $service->pruneExpiredDeliveries(30, false, 1);
		$this->assertSame(2, $first_apply['matched_rows']);
		$this->assertSame(1, $first_apply['deleted_rows']);
		$this->assertSame(2, DbHelper::count('form_hook_deliveries', ['definition_id' => $resolution->definitionId()]));

		$second_apply = $service->pruneExpiredDeliveries(30, false, 10);
		$this->assertSame(1, $second_apply['matched_rows']);
		$this->assertSame(1, $second_apply['deleted_rows']);
		$this->assertSame(1, DbHelper::count('form_hook_deliveries', ['definition_id' => $resolution->definitionId()]));
	}

	public function testRawEmailHookQueuesEmailSnapshotAndExposesAuditReference(): void
	{
		if (!$this->tableExists('email_outbox') || !$this->tableExists('email_queue_transactional')) {
			self::markTestSkipped('Email queue tables are required for raw email hook enqueue coverage.');
		}

		$definition_slug = $this->slug('email-queue');
		$resolution = $this->upsertCapture($definition_slug);
		$this->impersonateAndRequireRole('form_phase1_system_developer', RoleList::ROLE_SYSTEM_DEVELOPER);
		$before_outbox = DbHelper::count('email_outbox', []);
		$before_queue = DbHelper::count('email_queue_transactional', []);

		(new FormHookConfigService())->saveForForm($definition_slug, [
			'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
			'metadata_json' => '{"to":"ops@example.com","subject":"Queued raw capture"}',
			'enable_in_non_production' => true,
			'excluded_field_keys_json' => '["message"]',
		]);

		$result = $this->captureForm($resolution)->process($this->validPayload());

		$this->assertTrue($result->isSuccess());
		$this->assertSame($before_outbox + 1, DbHelper::count('email_outbox', []));
		$this->assertSame($before_queue + 1, DbHelper::count('email_queue_transactional', []));

		$delivery_log = (new FormHookConfigService())->deliveriesForForm($definition_slug, 5);
		$delivery = $delivery_log['deliveries'][0];
		$this->assertSame(FormHookResult::STATUS_QUEUED, $delivery['status']);
		$this->assertIsArray($delivery['result']);
		$this->assertArrayHasKey('outbox_id', $delivery['result']);
		$this->assertArrayHasKey('queued_jobs', $delivery['result']);
		$this->assertNotSame('', $delivery['queued_reference']);
		$this->assertSame($delivery['queued_reference'], $delivery['message']);
		$this->assertIsArray($delivery['payload']);
		$this->assertIsArray($delivery['payload']['data']);
		$this->assertArrayNotHasKey('message', $delivery['payload']['data']);
	}

	public function testCustomHttpsHookQueuesOutboundDeliveryJobAndExposesAuditReference(): void
	{
		if (!$this->tableExists('outbound_delivery_queue')) {
			self::markTestSkipped('Outbound delivery queue table is required for HTTPS hook enqueue coverage.');
		}

		$definition_slug = $this->slug('https-queue');
		$resolution = $this->upsertCapture($definition_slug);
		$this->impersonateAndRequireRole('form_phase1_system_developer', RoleList::ROLE_SYSTEM_DEVELOPER);

		(new FormHookConfigService())->saveForForm($definition_slug, [
			'target_kind' => FormHookTargetRegistry::KIND_CUSTOM_HTTPS_WEBHOOK,
			'url' => 'https://hooks.example.com/capture',
			'secret' => 'hook-secret-value',
			'enable_in_non_production' => true,
			'excluded_field_keys_json' => '["email"]',
		]);

		$result = $this->captureForm($resolution)->process($this->validPayload());

		$this->assertTrue($result->isSuccess());

		$delivery_log = (new FormHookConfigService())->deliveriesForForm($definition_slug, 5);
		$delivery = $delivery_log['deliveries'][0];
		$this->assertSame(FormHookResult::STATUS_QUEUED, $delivery['status']);
		$this->assertSame('formhook_' . $delivery['delivery_id'], $delivery['queued_reference']);
		$this->assertSame($delivery['queued_reference'], $delivery['message']);
		$this->assertIsArray($delivery['payload']);
		$this->assertIsArray($delivery['payload']['data']);
		$this->assertArrayNotHasKey('email', $delivery['payload']['data']);

		$queue_row = DbHelper::selectOne('outbound_delivery_queue', ['job_id' => $delivery['queued_reference']]);
		$this->assertIsArray($queue_row);
		$this->assertSame('https://hooks.example.com/capture', (string)$queue_row['target_url']);
		$headers = json_decode((string)$queue_row['headers_json'], true, 512, JSON_THROW_ON_ERROR);
		$this->assertSame($delivery['queued_reference'], $headers['X-Radaptor-Hook-Delivery'] ?? null);
		$this->assertStringStartsWith('sha256=', (string)($headers['X-Radaptor-Hook-Signature-256'] ?? ''));
		$payload = json_decode((string)$queue_row['payload_json'], true, 512, JSON_THROW_ON_ERROR);
		$this->assertSame('capture_form.submitted', $payload['event']);
		$this->assertArrayNotHasKey('email', $payload['data']);
	}

	public function testCustomWebhookWithoutStoredSecretFailsWithoutEnqueue(): void
	{
		$definition_slug = $this->slug('missing-secret');
		$resolution = $this->upsertCapture($definition_slug);
		$this->impersonateAndRequireRole('form_phase1_system_developer', RoleList::ROLE_SYSTEM_DEVELOPER);
		$created = (new FormHookConfigService())->saveForForm($definition_slug, [
			'target_kind' => FormHookTargetRegistry::KIND_CUSTOM_HTTPS_WEBHOOK,
			'url' => 'https://hooks.example.com/capture',
			'secret' => 'hook-secret-value',
			'enable_in_non_production' => true,
		]);
		DbHelper::updateHelper('form_hook_targets', [
			'secret_ciphertext' => null,
			'secret_nonce' => null,
			'secret_tag' => null,
		], [
			'hook_id' => (int)$created['hook']['hook_id'],
		]);

		$valid = $this->captureForm($resolution)->process($this->validPayload());

		$this->assertTrue($valid->isSuccess());
		$delivery = DbHelper::selectOne('form_hook_deliveries', ['definition_id' => $resolution->definitionId()]);
		$this->assertIsArray($delivery);
		$this->assertSame(FormHookResult::STATUS_FAILED, (string)$delivery['status']);
		$this->assertSame('FORM_HOOK_SECRET_REQUIRED', (string)$delivery['error_code']);
	}

	public function testHookEventsRequireAuthorizedConfiguratorAndCsrfForMutation(): void
	{
		$definition_slug = $this->slug('events');
		$this->upsertCapture($definition_slug);
		$events = [
			new EventFormHookTargets(),
			new EventFormHookSave(),
			new EventFormHookDelete(),
			new EventFormHookDeliveries(),
			new EventFormHooksList(),
			new EventFormHooksSave(),
			new EventFormHooksDelete(),
			new EventFormHooksDeliveries(),
		];

		$this->impersonate(null);

		foreach ($events as $event) {
			$this->assertFalse($event->authorize(PolicyContext::fromEvent($event))->allow);
		}

		$this->impersonateAndRequireRole('form_phase1_content_admin', RoleList::ROLE_CONTENT_ADMIN);

		foreach ($events as $event) {
			$this->assertTrue($event->authorize(PolicyContext::fromEvent($event))->allow);
		}

		$response = $this->runHookEvent(new EventFormHookSave(), [
			'definition_slug' => $definition_slug,
			'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
			'metadata_json' => '{"to":"ops@example.com"}',
		], includeCsrfToken: false);

		$this->assertSame(403, $response['http_code']);
		$this->assertFalse($response['body']['ok']);
		$this->assertSame(0, DbHelper::count('form_hook_targets', ['definition_id' => $this->definitionId($definition_slug)]));
	}

	private function upsertCapture(string $definition_slug): FormDefinitionResolution
	{
		return (new FormCaptureDefinitionRepository())->upsertPublishedDefinition(
			$definition_slug,
			$this->descriptor(),
			$this->defaultSecurity(),
		);
	}

	private function captureForm(FormDefinitionResolution $resolution): CaptureForm
	{
		return new CaptureForm(
			$resolution->definitionSlug(),
			'hook_backend_' . md5($resolution->definitionSlug()),
			$this->treeContext(),
			'/capture-return',
			[
				'host_page_id' => 1,
				'widget_connection_id' => 2,
				'form_definition_resolution' => $resolution,
			],
		);
	}

	private function insertHookDelivery(FormDefinitionResolution $resolution, string $label, string $created_at): int
	{
		$submission_id = $this->submissionIdForResolution($resolution);
		$delivery = EntityFormHookDelivery::createFromArray([
			'hook_id' => null,
			'definition_id' => $resolution->definitionId(),
			'version_id' => $resolution->versionId(),
			'submission_id' => $submission_id,
			'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
			'target_label' => $label,
			'status' => FormHookResult::STATUS_SUPPRESSED,
			'environment' => Kernel::getEnvironment(),
			'payload_json' => '{}',
			'result_json' => '{}',
			'created_at' => $created_at,
		]);

		return (int)$delivery->delivery_id;
	}

	private function submissionIdForResolution(FormDefinitionResolution $resolution): int
	{
		$row = DbHelper::selectOne('form_submissions', ['definition_id' => $resolution->definitionId()]);

		if (!is_array($row)) {
			$result = $this->captureForm($resolution)->process($this->validPayload());
			$this->assertTrue($result->isSuccess());
			$row = DbHelper::selectOne('form_submissions', ['definition_id' => $resolution->definitionId()]);
		}

		$this->assertIsArray($row);

		return (int)$row['submission_id'];
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

	private function tableExists(string $table): bool
	{
		$stmt = Db::instance()->prepare(
			"SELECT 1
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_TYPE = 'BASE TABLE'
			  AND TABLE_NAME = ?"
		);
		$stmt->execute([$table]);

		return (bool)$stmt->fetchColumn();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function descriptor(): array
	{
		return [
			'kind' => 'capture',
			'title' => ['text' => 'Hook backend probe'],
			'description' => ['text' => 'Capture hook probe'],
			'fields' => [
				[
					'type' => 'text',
					'name' => 'name',
					'key' => 'name_key',
					'label' => ['text' => 'Name'],
					'normalizers' => ['trim', 'collapse-whitespace'],
					'validators' => [
						['type' => 'required'],
						['type' => 'max_length', 'max' => 120],
					],
				],
				[
					'type' => 'text',
					'name' => 'email',
					'label' => ['text' => 'Email'],
					'normalizers' => ['trim', 'lowercase'],
					'validators' => [
						['type' => 'required'],
						['type' => 'email'],
					],
				],
				[
					'type' => 'select',
					'name' => 'topic',
					'label' => ['text' => 'Topic'],
					'values' => [
						['inputtype' => 'option', 'value' => 'general', 'label' => ['text' => 'General']],
						['inputtype' => 'option', 'value' => 'support', 'label' => ['text' => 'Support']],
					],
					'validators' => [
						['type' => 'enum', 'values' => ['general', 'support']],
					],
				],
				[
					'type' => 'textarea',
					'name' => 'message',
					'label' => ['text' => 'Message'],
					'normalizers' => ['trim'],
					'validators' => [
						['type' => 'required'],
						['type' => 'min_length', 'min' => 10],
					],
				],
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function defaultSecurity(): array
	{
		return [
			'honeypot' => [
				'enabled' => true,
				'field_name' => 'company_website',
			],
			'rate_limit' => [
				'accepted' => 5,
				'window_seconds' => 600,
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function validPayload(): array
	{
		return [
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'name_key' => 'Ada Lovelace',
			'email' => 'ada@example.test',
			'topic' => 'general',
			'message' => 'This message is long enough.',
			'company_website' => '',
		];
	}

	private function treeContext(): iTreeBuildContext
	{
		return new class () implements iTreeBuildContext {
			public function getPageId(): ?int
			{
				return 1;
			}

			public function getPagedata($key)
			{
				return null;
			}

			public function registerRenderedLayoutComponent(iLayoutComponent $layoutComponent): void
			{
			}

			public function getLayoutTypeName(): ?string
			{
				return 'public_empty';
			}

			public function addToTitle(string $addition): void
			{
			}

			public function isEditable(): bool
			{
				return false;
			}

			public function getTheme(): ?AbstractThemeData
			{
				return null;
			}

			public function overrideLayoutType(string $layoutTypeName): void
			{
			}
		};
	}

	/**
	 * @param array<string, mixed> $post
	 * @param array<string, mixed> $get
	 * @return array{http_code: int|null, body: array<string, mixed>|null, output: string}
	 */
	private function runHookEvent(AbstractEvent $event, array $post = [], array $get = [], bool $includeCsrfToken = true): array
	{
		if ($includeCsrfToken && $post !== [] && !array_key_exists(FormSubmitContext::FIELD_CSRF_TOKEN, $post)) {
			$post[FormSubmitContext::FIELD_CSRF_TOKEN] = FormSubmitContext::issueCsrfTokenForForm(FormBuilderEventHelper::CSRF_FORM_ID);
		}

		$this->setRequestContext(
			get: $get,
			post: $post,
			server: [
				'HTTP_HOST' => 'localhost',
				'REQUEST_URI' => '/?context=form_hooks',
				'REQUEST_METHOD' => $post === [] ? 'GET' : 'POST',
				'HTTP_ACCEPT' => 'application/json',
			],
		);

		$ctx = RequestContextHolder::current();
		$ctx->apiResponseCaptureEnabled = true;

		ob_start();
		$event->run();
		$output = (string)ob_get_clean();

		return [
			'http_code' => $ctx->capturedApiResponseHttpCode,
			'body' => $ctx->capturedApiResponse,
			'output' => $output,
		];
	}

	/**
	 * @param callable(): void $callback
	 */
	private function expectConfigExceptionCode(string $api_code, callable $callback): void
	{
		try {
			$callback();
			$this->fail("Expected {$api_code}.");
		} catch (FormHookConfigValidationException $exception) {
			$error = $exception->toApiError();
			$this->assertSame($api_code, $error->code);
		}
	}

	private function definitionId(string $definition_slug): int
	{
		$definition = EntityFormDefinition::findBySlug($definition_slug);
		$this->assertInstanceOf(EntityFormDefinition::class, $definition);

		return (int)$definition->definition_id;
	}

	private function slug(string $name): string
	{
		return 'capture-hook-' . $name . '-' . $this->_suffix;
	}

	/**
	 * @param array<string, mixed> $get
	 * @param array<string, mixed> $post
	 * @param array<string, mixed> $server
	 */
	private function setRequestContext(array $get = [], array $post = [], array $server = []): void
	{
		$server += [
			'HTTP_HOST' => 'localhost',
			'REQUEST_URI' => '/capture-hook-backend',
			'REQUEST_METHOD' => $post === [] ? 'GET' : 'POST',
			'REMOTE_ADDR' => '203.0.113.44',
			'HTTP_USER_AGENT' => 'Radaptor form hook backend test',
		];

		$_GET = $get;
		$_POST = $post;
		$_SERVER = $server;
		RequestContextHolder::initializeRequest(get: $get, post: $post, server: $server);
	}

	private function impersonateAndRequireRole(string $username, string $role): void
	{
		$this->impersonate($username);

		if (!Roles::hasRole($role)) {
			self::markTestSkipped("User '{$username}' does not have required role '{$role}'.");
		}
	}

	private function impersonate(?string $username): void
	{
		$ctx = RequestContextHolder::current();

		if ($username === null) {
			$ctx->currentUser = null;
			$ctx->userSessionInitialized = true;
			Cache::flush(Roles::class);
			Cache::flush(User::class);

			return;
		}

		$user = EntityUser::findFirst(['username' => $username]);

		if ($user === null) {
			self::markTestSkipped("Missing test user: {$username}");
		}

		$ctx->currentUser = $user->data();
		$ctx->userSessionInitialized = true;
		Cache::flush(Roles::class);
		Cache::flush(User::class);
	}

	private function restoreAppSecret(): void
	{
		if ($this->_previous_app_secret === false || $this->_previous_app_secret === null) {
			putenv('APP_SECRET');
		} else {
			putenv('APP_SECRET=' . $this->_previous_app_secret);
		}

		$this->_previous_app_secret = null;
	}

	private static function installHookTables(): void
	{
		(new Migration_20260529_101000_capture_form_hooks())->run();
		(new Migration_20260530_143000_harden_capture_form_hooks())->run();

		if (self::$_runtime_schema_token === null) {
			self::$_runtime_schema_token = DbSchemaData::pushRuntimeSchema(
				DbSchemaDataBuilder::buildSchemaArray([Db::normalizeDsn()])
			);
		}
	}

	private static function requireHookBackendFiles(): void
	{
		$root = dirname(__DIR__, 2);

		foreach ([
			'migrations/20260529_101000_capture_form_hooks.php',
			'migrations/20260530_143000_harden_capture_form_hooks.php',
			'modules-common/Form/classes/class.CaptureForm.php',
			'modules-common/Form/interfaces/interface.iFormHookTarget.php',
			'modules-common/Form/entities/Entity.FormHookTarget.php',
			'modules-common/Form/entities/Entity.FormHookDelivery.php',
			'modules-common/Form/classes/class.FormHookTargetDefinition.php',
			'modules-common/Form/classes/class.FormHookConfigValidationException.php',
			'modules-common/Form/classes/class.FormHookResult.php',
			'modules-common/Form/classes/class.FormHookInvocation.php',
			'modules-common/Form/classes/class.FormHookSecretStore.php',
			'modules-common/Form/classes/class.FormHookOutboundDeliveryAdapter.php',
			'modules-common/Form/classes/class.FormHookTargetCustomHttpsWebhook.php',
			'modules-common/Form/classes/class.FormHookTargetRawEmail.php',
			'modules-common/Form/classes/class.FormHookTargetRegistry.php',
			'modules-common/Form/classes/class.FormHookConfigService.php',
			'modules-common/Form/classes/class.FormHookInvocationService.php',
			'modules-common/Form/classes/class.FormHookEventHelper.php',
			'modules-common/Form/events/Event.FormHookTargets.php',
			'modules-common/Form/events/Event.FormHookSave.php',
			'modules-common/Form/events/Event.FormHookDelete.php',
			'modules-common/Form/events/Event.FormHookDeliveries.php',
			'modules-common/Form/events/Event.FormHooksList.php',
			'modules-common/Form/events/Event.FormHooksSave.php',
			'modules-common/Form/events/Event.FormHooksDelete.php',
			'modules-common/Form/events/Event.FormHooksDeliveries.php',
		] as $relative_path) {
			$path = $root . '/' . $relative_path;

			if (!is_file($path)) {
				self::markTestSkipped("Missing hook backend file: {$relative_path}");
			}

			require_once $path;
		}
	}

	private static function bootstrapConsumerRuntime(): void
	{
		if (self::$_runtime_bootstrapped) {
			return;
		}

		$bootstrap = getenv('RADAPTOR_APP_TEST_BOOTSTRAP') ?: '/app/bootstrap/bootstrap.testing.php';

		if (!is_file($bootstrap)) {
			self::markTestSkipped('Set RADAPTOR_APP_TEST_BOOTSTRAP or run from the Radaptor app container to execute form hook backend integration tests.');
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
