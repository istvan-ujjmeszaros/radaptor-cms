<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FormRefactorPhase4CaptureIntegrationTest extends TestCase
{
	private static bool $_runtime_bootstrapped = false;
	private bool $_transaction_started = false;
	private string|false|null $_previous_app_secret = null;

	protected function setUp(): void
	{
		self::bootstrapConsumerRuntime();
		$this->_previous_app_secret = getenv('APP_SECRET');
		putenv('APP_SECRET=form-refactor-phase-4-capture-test-secret');

		foreach ([
			AbstractForm::class,
			CaptureForm::class,
			FormCaptureDefinitionRepository::class,
			FormDefinitionResolver::class,
			FormCaptureDescriptorSchemaValidator::class,
			EntityFormDefinition::class,
			EntityFormDefinitionVersion::class,
			EntityFormSubmission::class,
		] as $class_name) {
			if (!class_exists($class_name)) {
				self::markTestSkipped('The Radaptor consumer app runtime with capture-form MVP classes is required.');
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

	public function testSystemFormsStillResolveThroughClassResolver(): void
	{
		$resolution = FormDefinitionResolver::resolve('UserLogin');

		$this->assertInstanceOf(FormDefinitionResolution::class, $resolution);
		$this->assertTrue($resolution->isSystem());
		$this->assertSame(FormTypeUserLogin::class, $resolution->className());
	}

	public function testPublishedCaptureDefinitionResolvesAndBuildsTreeWithHoneypot(): void
	{
		$resolution = $this->upsertCapture('capture-phase4-render');
		$form = $this->captureForm($resolution);
		$tree = $form->buildTree();

		$this->assertTrue($resolution->isCapture());
		$this->assertSame('capture-phase4-render', $tree['props']['form_descriptor_id']);
		$this->assertSame('Contact probe', $tree['props']['title']);
		$this->assertSame('form.honeypot', $tree['slots']['hidden_fields'][1]['component'] ?? null);
		$this->assertSame('company_website', $tree['slots']['hidden_fields'][1]['props']['name'] ?? null);
	}

	public function testCaptureSchemaRejectsSystemCollisionAndUnsupportedFeatures(): void
	{
		$this->expectException(InvalidArgumentException::class);
		FormCaptureDescriptorSchemaValidator::validateForDefinition('UserLogin', $this->descriptor());
	}

	public function testCaptureSchemaRejectsDefinitionSlugLongerThanDatabaseLimit(): void
	{
		$max_length_slug = FormCaptureDescriptorSchemaValidator::CAPTURE_PREFIX . str_repeat('a', FormCaptureDescriptorSchemaValidator::MAX_DEFINITION_SLUG_LENGTH - strlen(FormCaptureDescriptorSchemaValidator::CAPTURE_PREFIX));
		FormCaptureDescriptorSchemaValidator::validateForDefinition($max_length_slug, $this->descriptor());

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('128 characters or shorter');

		FormCaptureDescriptorSchemaValidator::validateForDefinition($max_length_slug . 'a', $this->descriptor());
	}

	public function testCaptureSchemaRejectsAdminCodeFeatures(): void
	{
		$descriptor = $this->descriptor();
		$descriptor['fields'][0]['autocomplete_url'] = '/admin-only/provider';

		$this->expectException(InvalidArgumentException::class);
		FormCaptureDescriptorSchemaValidator::validateForDefinition('capture-phase4-invalid-feature', $descriptor);
	}

	public function testValidSubmitStoresPayloadByStableFieldKeys(): void
	{
		$resolution = $this->upsertCapture('capture-phase4-submit');
		$form = $this->captureForm($resolution);
		$result = $form->process([
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'name_key' => '  Ada Lovelace  ',
			'email' => 'ADA@EXAMPLE.TEST ',
			'topic' => 'support',
			'message' => '  Please contact me.  ',
			'company_website' => '',
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame(1, DbHelper::count('form_submissions', ['definition_id' => $resolution->definitionId()]));

		$row = DbHelper::selectOne('form_submissions', ['definition_id' => $resolution->definitionId()]);
		$this->assertIsArray($row);
		$payload = json_decode((string)$row['payload_json'], true, 512, JSON_THROW_ON_ERROR);
		$this->assertSame([
			'name_key' => 'Ada Lovelace',
			'email' => 'ada@example.test',
			'topic' => 'support',
			'message' => 'Please contact me.',
		], $payload);
		$this->assertSame($this->expectedClientFingerprintHash('203.0.113.44', 'capture_form.ip'), $row['ip_hash']);
		$this->assertNotSame(hash('sha256', '203.0.113.44'), $row['ip_hash']);
		$this->assertSame($this->expectedClientFingerprintHash('Radaptor capture test', 'capture_form.user_agent'), $row['user_agent_hash']);
		$this->assertNotSame(hash('sha256', 'Radaptor capture test'), $row['user_agent_hash']);
	}

	public function testMissingAppSecretFailsAsControlledCaptureRuntimeError(): void
	{
		$resolution = $this->upsertCapture('capture-phase4-missing-secret');
		putenv('APP_SECRET');

		try {
			$this->captureForm($resolution)->process($this->validPayload());
			$this->fail('Capture submission without APP_SECRET should fail as a controlled runtime exception.');
		} catch (FormCaptureRuntimeException $exception) {
			$this->assertSame('FORM_CAPTURE_SECRET_MISSING', $exception->apiCode());
			$this->assertSame('form.capture.error_unavailable', $exception->messageKey());
			$this->assertSame(500, $exception->httpStatus());
			$this->assertSame(0, DbHelper::count('form_submissions', ['definition_id' => $resolution->definitionId()]));
		} finally {
			putenv('APP_SECRET=form-refactor-phase-4-capture-test-secret');
		}
	}

	public function testInvalidHoneypotAndRateLimitRejectWithoutStorage(): void
	{
		$resolution = $this->upsertCapture('capture-phase4-security', [
			'rate_limit' => [
				'accepted' => 1,
				'window_seconds' => 600,
			],
		]);

		$invalid = $this->captureForm($resolution)->process([
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'name_key' => '',
			'email' => 'not-email',
			'topic' => 'general',
			'message' => 'short',
			'company_website' => '',
		]);
		$this->assertTrue($invalid->isInvalid());
		$this->assertSame(0, DbHelper::count('form_submissions', ['definition_id' => $resolution->definitionId()]));

		$honeypot = $this->captureForm($resolution)->process([
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'name_key' => 'Ada Lovelace',
			'email' => 'ada@example.test',
			'topic' => 'general',
			'message' => 'This message is long enough.',
			'company_website' => 'https://bot.example.test',
		]);
		$this->assertTrue($honeypot->isDenied());
		$this->assertSame(0, DbHelper::count('form_submissions', ['definition_id' => $resolution->definitionId()]));

		$first = $this->captureForm($resolution)->process($this->validPayload());
		$this->assertTrue($first->isSuccess());
		$this->assertSame(1, DbHelper::count('form_submissions', ['definition_id' => $resolution->definitionId()]));

		$limited = $this->captureForm($resolution)->process($this->validPayload());
		$this->assertTrue($limited->isDenied());
		$this->assertSame(1, DbHelper::count('form_submissions', ['definition_id' => $resolution->definitionId()]));
	}

	public function testDraftDisabledMissingAndInvalidCaptureDefinitionsAreControlledRejections(): void
	{
		$security_json = json_encode(
			FormCaptureDescriptorSchemaValidator::normalizeSecurity(null, ['name_key', 'email', 'topic', 'message']),
			JSON_THROW_ON_ERROR,
		);
		EntityFormDefinition::createFromArray([
			'definition_slug' => 'capture-phase4-draft',
			'kind' => 'capture',
			'source' => 'db',
			'status' => 'draft',
			'security_json' => $security_json,
		]);
		$this->assertNull(FormDefinitionResolver::resolve('capture-phase4-draft'));

		$disabled = (new FormCaptureDefinitionRepository())->upsertPublishedDefinition('capture-phase4-disabled', $this->descriptor());
		EntityFormDefinition::updateById($disabled->definitionId(), ['status' => 'disabled']);
		$this->assertNull(FormDefinitionResolver::resolve('capture-phase4-disabled'));

		$this->assertNull(FormDefinitionResolver::resolve('capture-phase4-missing'));

		$invalid_definition = EntityFormDefinition::createFromArray([
			'definition_slug' => 'capture-phase4-invalid-runtime',
			'kind' => 'capture',
			'source' => 'db',
			'status' => 'published',
			'security_json' => $security_json,
		]);
		$invalid_version = EntityFormDefinitionVersion::createFromArray([
			'definition_id' => (int)$invalid_definition->definition_id,
			'version_number' => 1,
			'status' => 'published',
			'descriptor_json' => '{"kind":"capture","fields":[{"type":"hidden","name":"secret"}]}',
			'descriptor_hash' => hash('sha256', 'invalid-runtime'),
			'published_at' => date('Y-m-d H:i:s'),
		]);
		EntityFormDefinition::updateById((int)$invalid_definition->definition_id, [
			'published_version_id' => (int)$invalid_version->version_id,
		]);

		try {
			FormDefinitionResolver::resolve('capture-phase4-invalid-runtime');
			$this->fail('Invalid runtime capture descriptor should be reported as a controlled runtime exception.');
		} catch (FormCaptureRuntimeException $exception) {
			$this->assertSame('FORM_CAPTURE_DESCRIPTOR_INVALID', $exception->apiCode());
			$this->assertSame(500, $exception->httpStatus());
		}
	}

	private function upsertCapture(string $definition_slug, array $security = []): FormDefinitionResolution
	{
		return (new FormCaptureDefinitionRepository())->upsertPublishedDefinition(
			$definition_slug,
			$this->descriptor(),
			array_replace_recursive([
				'honeypot' => [
					'enabled' => true,
					'field_name' => 'company_website',
				],
				'rate_limit' => [
					'accepted' => 5,
					'window_seconds' => 600,
				],
			], $security),
		);
	}

	private function captureForm(FormDefinitionResolution $resolution): CaptureForm
	{
		return new CaptureForm(
			$resolution->definitionSlug(),
			'phase4_capture_' . md5($resolution->definitionSlug()),
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
			'title' => ['text' => 'Contact probe'],
			'description' => ['text' => 'Capture probe'],
			'fields' => [
				[
					'type' => 'text',
					'name' => 'name',
					'key' => 'name_key',
					'label' => ['text' => 'Name'],
					'normalizers' => ['trim', 'collapse-whitespace'],
					'validators' => [
						['type' => 'required', 'message' => ['text' => 'Name required']],
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
						['type' => 'email', 'message' => ['text' => 'Email invalid']],
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

	private function expectedClientFingerprintHash(string $value, string $context): string
	{
		return hash_hmac('sha256', $context . "\n" . $value, 'form-refactor-phase-4-capture-test-secret');
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

	private function setRequestContext(): void
	{
		$get = [];
		$post = [];
		$server = [
			'HTTP_HOST' => 'localhost',
			'REQUEST_URI' => '/capture-phase-4',
			'REQUEST_METHOD' => 'GET',
			'REMOTE_ADDR' => '203.0.113.44',
			'HTTP_USER_AGENT' => 'Radaptor capture test',
		];

		$_GET = $get;
		$_POST = $post;
		$_SERVER = $server;
		RequestContextHolder::initializeRequest(get: $get, post: $post, server: $server);
	}

	private static function bootstrapConsumerRuntime(): void
	{
		if (self::$_runtime_bootstrapped) {
			return;
		}

		$bootstrap = getenv('RADAPTOR_APP_TEST_BOOTSTRAP') ?: '/app/bootstrap/bootstrap.testing.php';

		if (!is_file($bootstrap)) {
			self::markTestSkipped('Set RADAPTOR_APP_TEST_BOOTSTRAP or run from the Radaptor app container to execute form refactor integration tests.');
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
