<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;

final class FormRefactorPhase4IntegrationTest extends TestCase
{
	private static bool $_runtime_bootstrapped = false;
	private bool $_transaction_started = false;

	protected function setUp(): void
	{
		self::bootstrapConsumerRuntime();

		if (
			!class_exists('AbstractForm')
			|| !class_exists('FormInputText')
			|| !class_exists('FormDescriptorAdapter')
			|| !class_exists('FormDescriptorValidatorRegistry')
			|| !class_exists('RequestContextHolder')
		) {
			self::markTestSkipped('The Radaptor consumer app runtime is required for form refactor integration tests.');
		}

		if (class_exists('Db')) {
			$pdo = Db::instance();

			if (!$pdo->inTransaction()) {
				$pdo->beginTransaction();
				$this->_transaction_started = true;
			}
		}

		$this->ensureProbeFormClass();
		$this->setRequestContext();
		Request::saveSessionData([FormSubmitContext::SESSION_KEY_CSRF_TOKENS], []);
		FormSubmissionStateStore::clear();
		FormTypePhase4DescriptorProbe::resetProbe();
	}

	protected function tearDown(): void
	{
		if (class_exists('RequestContextHolder', autoload: false)) {
			FormSubmissionStateStore::clear();
			Request::saveSessionData([FormSubmitContext::SESSION_KEY_CSRF_TOKENS], []);
			$this->setRequestContext();
		}

		if ($this->_transaction_started && class_exists('Db', autoload: false)) {
			$pdo = Db::instance();

			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
		}

		$this->_transaction_started = false;
	}

	#[WithoutErrorHandler]
	public function testDescriptorBuildsFormInputGraphAndSduiTreeWithoutCallingMakeInputs(): void
	{
		$form = $this->probeForm();
		$tree = $form->buildTree();

		$this->assertFalse(FormTypePhase4DescriptorProbe::$makeInputsCalled);
		$this->assertInstanceOf(FormInputText::class, $form->getInput('title'));
		$this->assertSame('title_key', $form->getInput('title')?->getKey());
		$this->assertSame('Descriptor default', $form->getInput('title')?->getValue());
		$this->assertInstanceOf(FormInputSelect::class, $form->getInput('status'));
		$this->assertInstanceOf(FormInputCheckboxgroup::class, $form->getInput('choices'));
		$this->assertInstanceOf(FormInputHidden::class, $form->getInput('tracking_token'));

		$this->assertSame('form', $tree['component']);
		$this->assertSame('/?context=form&event=submit', $tree['props']['action']);
		$this->assertSame('title_key', $tree['props']['field_refs']['title']['key']);
		$this->assertSame('title_key', $tree['props']['field_refs']['title']['name']);
		$this->assertSame('status', $tree['props']['field_refs']['status']['key']);
		$this->assertSame('Phase4DescriptorProbe', $tree['props']['submit_context'][FormSubmitContext::FIELD_FORM_ID]);
		$this->assertArrayNotHasKey(FormSubmitContext::FIELD_CSRF_TOKEN, $tree['props']['submit_context']);

		$this->assertCount(2, $tree['slots']['hidden_fields'] ?? []);
		$this->assertSame('tracking_key', $tree['slots']['hidden_fields'][0]['props']['name'] ?? null);
		$this->assertSame(FormSubmitContext::FIELD_CSRF_TOKEN, $tree['slots']['hidden_fields'][1]['props']['name'] ?? null);
		$this->assertFalse($tree['slots']['hidden_fields'][0]['props']['save']);
		$this->assertFalse($tree['slots']['hidden_fields'][1]['props']['save']);

		$title_props = $tree['slots']['rows'][0]['slots']['content'][0]['props'] ?? [];
		$this->assertSame('Title', $title_props['label'] ?? null);
		$this->assertSame('title_key', $title_props['name'] ?? null);
		$this->assertSame('title_key', $title_props['data_field_key'] ?? null);
		$this->assertSame('Short descriptor label.', $title_props['info_string'] ?? null);
		$this->assertSame('required', $title_props['validators'][0]['type'] ?? null);
		$this->assertSame('max_length', $title_props['validators'][1]['type'] ?? null);
		$this->assertSame(['max' => 12], $title_props['validators'][1]['props'] ?? null);
	}

	public function testDescriptorValidatorsReturnErrorsByStableFieldKey(): void
	{
		$form = $this->probeForm();

		$result = $form->process([
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'title_key' => '',
			'status' => 'archived',
		]);

		$this->assertTrue($result->isInvalid());
		$this->assertArrayHasKey('title_key', $result->errors());
		$this->assertArrayHasKey('status', $result->errors());
		$this->assertSame(['Title is required.'], $result->errors()['title_key']);
		$this->assertSame(['Unsupported status.'], $result->errors()['status']);
		$this->assertFalse(FormTypePhase4DescriptorProbe::$committed);
	}

	public function testDescriptorFormProcessesStablePayloadKeysThroughExistingSystemPipeline(): void
	{
		$form = $this->probeForm();

		$result = $form->process([
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'title_key' => 'Phase Four',
			'status' => 'draft',
			'choices' => [
				'alpha' => '1',
			],
			'tracking_key' => 'changed-by-client',
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertTrue(FormTypePhase4DescriptorProbe::$committed);
		$this->assertSame('Phase Four', FormTypePhase4DescriptorProbe::$lastSavedata['title']);
		$this->assertSame('draft', FormTypePhase4DescriptorProbe::$lastSavedata['status']);
		$this->assertSame(['alpha' => '1'], FormTypePhase4DescriptorProbe::$lastSavedata['choices']);
		$this->assertArrayNotHasKey('tracking_token', FormTypePhase4DescriptorProbe::$lastSavedata);
	}

	public function testSetInitValuesWinsOverDescriptorInlineValueForUpdateMode(): void
	{
		$form = $this->probeForm(itemId: 42);

		$this->assertSame(AbstractForm::_MODE_UPDATE, $form->getMode());
		$this->assertSame('Loaded title from storage', $form->getInput('title')?->getValue());
	}

	private function probeForm(?int $itemId = null): AbstractForm
	{
		return new FormTypePhase4DescriptorProbe(
			'Phase4DescriptorProbe',
			'phase4_descriptor_probe',
			$this->treeContext(),
			'/phase-4-return',
			[
				'item_id' => $itemId,
			],
		);
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
				return 'admin_default';
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
	 * @param array<string, mixed> $get
	 * @param array<string, mixed> $post
	 * @param array<string, mixed> $server
	 */
	private function setRequestContext(array $get = [], array $post = [], array $server = []): void
	{
		$server += [
			'HTTP_HOST' => 'localhost',
			'REQUEST_URI' => '/phase-4-form-refactor',
			'REQUEST_METHOD' => $post === [] ? 'GET' : 'POST',
		];

		$_GET = $get;
		$_POST = $post;
		$_SERVER = $server;
		RequestContextHolder::initializeRequest(get: $get, post: $post, server: $server);
	}

	private function ensureProbeFormClass(): void
	{
		if (class_exists('FormTypePhase4DescriptorProbe', autoload: false)) {
			return;
		}

		eval(<<<'PHP'
			final class FormTypePhase4DescriptorProbe extends AbstractForm
			{
				public static bool $makeInputsCalled = false;
				public static bool $committed = false;
				public static array $lastSavedata = [];

				public static function resetProbe(): void
				{
					self::$makeInputsCalled = false;
					self::$committed = false;
					self::$lastSavedata = [];
				}

				public static function getName(): string
				{
					return 'Phase 4 descriptor probe';
				}

				public static function getDescription(): string
				{
					return 'Phase 4 descriptor probe form';
				}

				public static function getListVisibility(): bool
				{
					return false;
				}

				public static function getDefaultPathForCreation(): array
				{
					return [];
				}

				public function hasRole(): bool
				{
					return true;
				}

				public function commit(): void
				{
					self::$committed = true;
					self::$lastSavedata = $this->savedata;
				}

				public function setMetadata(): void
				{
					$this->_meta->title = 'Phase 4 Descriptor Probe';
					$this->_meta->enableAutoReferer = false;
				}

				public function setInitValues(): void
				{
					if ($this->getItemId() !== null) {
						$this->initvalues['title'] = 'Loaded title from storage';
					}
				}

				public function getDescriptor(): ?array
				{
					return [
						'fields' => [
							[
								'type' => 'text',
								'name' => 'title',
								'key' => 'title_key',
								'label' => ['text' => 'Title'],
								'help' => ['text' => 'Short descriptor label.'],
								'value' => 'Descriptor default',
								'validators' => [
									[
										'type' => 'required',
										'message' => ['text' => 'Title is required.'],
									],
									[
										'type' => 'max_length',
										'max' => 12,
										'message' => ['text' => 'Title is too long.'],
									],
								],
							],
							[
								'type' => 'select',
								'name' => 'status',
								'label' => ['text' => 'Status'],
								'required' => false,
								'values' => [
									['inputtype' => 'option', 'value' => 'draft', 'label' => ['text' => 'Draft']],
									['inputtype' => 'option', 'value' => 'published', 'label' => ['text' => 'Published']],
								],
								'validators' => [
									[
										'type' => 'enum',
										'values' => ['draft', 'published'],
										'message' => ['text' => 'Unsupported status.'],
									],
								],
							],
							[
								'type' => 'checkboxgroup',
								'name' => 'choices',
								'label' => ['text' => 'Choices'],
								'values' => [
									'alpha' => ['text' => 'Alpha'],
									'beta' => ['text' => 'Beta'],
								],
							],
							[
								'type' => 'hidden',
								'name' => 'tracking_token',
								'key' => 'tracking_key',
								'value' => 'descriptor-token',
								'save' => false,
							],
						],
					];
				}

				public function makeInputs(): void
				{
					self::$makeInputsCalled = true;
					new FormInputText('imperative_fallback', $this);
				}
			}
			PHP);
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

		require_once $bootstrap;
		restore_error_handler();
		restore_exception_handler();

		self::$_runtime_bootstrapped = true;
	}
}
