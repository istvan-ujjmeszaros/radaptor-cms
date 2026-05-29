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
		$this->assertContains('email', $response['body']['data']['available_field_keys']);
	}

	public function testConfigValidationAndRoleBoundaries(): void
	{
		$definition_slug = $this->slug('roles');
		$this->upsertCapture($definition_slug);
		$service = new FormHookConfigService();

		$this->impersonateAndRequireRole('form_phase1_content_admin', RoleList::ROLE_CONTENT_ADMIN);
		$email = $service->saveForForm($definition_slug, [
			'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
			'label' => 'Internal notification',
			'preset_key' => 'default',
			'metadata' => [
				'subject' => 'New capture submission',
				'reply_to_field_key' => 'email',
			],
			'excluded_field_keys' => ['message'],
		]);

		$this->assertSame(FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL, $email['hook']['target_kind']);
		$this->assertSame(['message'], $email['hook']['excluded_field_keys']);

		$external_email = $service->saveForForm($definition_slug, [
			'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
			'label' => 'External notification',
			'metadata' => [
				'to' => 'external@example.com',
				'subject' => 'External capture submission',
			],
		]);

		$this->assertSame('external@example.com', $external_email['hook']['metadata']['to']);

		$this->expectConfigExceptionCode('FORM_HOOK_DEVELOPER_ROLE_REQUIRED', function () use ($service, $definition_slug): void {
			$service->saveForForm($definition_slug, [
				'target_kind' => FormHookTargetRegistry::KIND_CUSTOM_HTTPS_WEBHOOK,
				'label' => 'External webhook',
				'url' => 'https://hooks.example.test/capture',
			]);
		});
		$this->expectConfigExceptionCode('FORM_HOOK_DEVELOPER_ROLE_REQUIRED', function () use ($service, $definition_slug): void {
			$service->saveForForm($definition_slug, [
				'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
				'label' => 'Unsafe metadata',
				'preset_key' => 'default',
				'metadata' => ['transport' => 'smtp://example.test'],
			]);
		});
		$this->expectConfigExceptionCode('FORM_HOOK_UNKNOWN_EXCLUDED_FIELD_KEY', function () use ($service, $definition_slug): void {
			$service->saveForForm($definition_slug, [
				'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
				'label' => 'Unknown field',
				'preset_key' => 'default',
				'excluded_field_keys' => ['missing_field'],
			]);
		});

		$this->impersonateAndRequireRole('form_phase1_system_developer', RoleList::ROLE_SYSTEM_DEVELOPER);
		$webhook = $service->saveForForm($definition_slug, [
			'target_kind' => FormHookTargetRegistry::KIND_CUSTOM_HTTPS_WEBHOOK,
			'label' => 'External webhook',
			'url' => 'https://hooks.example.com/capture',
			'enable_in_non_production' => true,
			'secret' => 'hook-secret-value',
			'excluded_field_keys' => ['email'],
		]);

		$this->assertSame(FormHookTargetRegistry::KIND_CUSTOM_HTTPS_WEBHOOK, $webhook['hook']['target_kind']);
		$this->assertTrue($webhook['hook']['enable_in_non_production']);
		$this->assertTrue($webhook['hook']['has_secret']);
		$this->assertStringEndsWith('alue', $webhook['hook']['secret_mask']);

		$this->expectConfigExceptionCode('FORM_HOOK_EMAIL_TO_INVALID', function () use ($service, $definition_slug): void {
			$service->saveForForm($definition_slug, [
				'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
				'label' => 'Invalid recipient',
				'metadata' => ['to' => 'not-an-email'],
			]);
		});
		$this->expectConfigExceptionCode('FORM_HOOK_URL_NOT_ALLOWED', function () use ($service, $definition_slug): void {
			$service->saveForForm($definition_slug, [
				'target_kind' => FormHookTargetRegistry::KIND_CUSTOM_HTTPS_WEBHOOK,
				'label' => 'Local webhook',
				'url' => 'https://localhost/capture',
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
			'label' => 'Source notification',
			'metadata' => ['to' => 'source@example.com'],
		]);
		$stale_hook_id = (int)$created['hook']['hook_id'];

		$this->expectConfigExceptionCode('FORM_HOOK_NOT_FOUND', function () use ($service, $target_slug, $stale_hook_id): void {
			$service->saveForForm($target_slug, [
				'hook_id' => $stale_hook_id,
				'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
				'label' => 'Wrong form notification',
				'metadata' => ['to' => 'target@example.com'],
			]);
		});
		$this->assertSame(0, DbHelper::count('form_hook_targets', ['definition_id' => $this->definitionId($target_slug)]));

		$metadata_response = $this->runHookEvent(new EventFormHookSave(), [
			'definition_slug' => $target_slug,
			'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
			'label' => 'Malformed metadata',
			'metadata_json' => '{"to":',
		]);
		$this->assertSame(422, $metadata_response['http_code']);
		$this->assertFalse($metadata_response['body']['ok']);
		$this->assertSame('FORM_HOOK_INVALID_JSON', $metadata_response['body']['error']['code']);

		$excluded_response = $this->runHookEvent(new EventFormHookSave(), [
			'definition_slug' => $target_slug,
			'target_kind' => FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL,
			'label' => 'Malformed excluded fields',
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
			'label' => 'Suppressed webhook',
			'url' => 'https://hooks.example.com/capture',
			'secret' => 'hook-secret-value',
			'enable_in_non_production' => false,
			'excluded_field_keys' => ['email'],
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
			'label' => 'Missing CSRF',
			'metadata' => ['to' => 'ops@example.com'],
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
