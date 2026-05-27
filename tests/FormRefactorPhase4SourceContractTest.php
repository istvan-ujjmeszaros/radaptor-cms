<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!class_exists('FormSubmitContext', false)) {
	final class FormSubmitContext
	{
		public const string FIELD_FORM_ID = '_form_id';
		public const string FIELD_FORM_INSTANCE_ID = '_form_instance_id';
		public const string FIELD_ITEM_ID = '_item_id';
		public const string FIELD_RETURN_TARGET = '_return_target';
		public const string FIELD_HOST_PAGE_ID = '_host_page_id';
		public const string FIELD_WIDGET_CONNECTION_ID = '_widget_connection_id';
		public const string FIELD_BUILD_ID = '_build_id';
		public const string FIELD_CONTEXT_PARAMS = '_context_params';
		public const string FIELD_FORM_DEFINITION_VERSION_ID = '_form_definition_version_id';
		public const string FIELD_FORM_RENDER_STATE_ID = '_form_render_state_id';
		public const string FIELD_CSRF_TOKEN = '_token';
	}
}

if (!class_exists('FormClassResolver', false)) {
	final class FormClassResolver
	{
		public static function resolveClassName(string $form_id): ?string
		{
			return null;
		}
	}
}

final class FormRefactorPhase4SourceContractTest extends TestCase
{
	private const array EXPECTED_VALIDATOR_TYPES = [
		'required',
		'email',
		'url',
		'min_length',
		'max_length',
		'number_min',
		'number_max',
		'regex',
		'enum',
		'date',
		'file_type',
		'file_size',
	];

	public function testAbstractFormKeepsImperativePathAndAddsDescriptorAdapterPath(): void
	{
		$source = $this->source('modules-common/Form/classes/class.AbstractForm.php');

		$this->assertStringContainsString('public function getDescriptor(): ?array', $source);
		$this->assertStringContainsString('$descriptor = $this->getDescriptor();', $source);
		$this->assertStringContainsString('FormDescriptorAdapter::buildInputs($this, $descriptor);', $source);
		$this->assertStringContainsString('$this->makeInputs();', $source);
	}

	public function testPhpAndJsValidatorRegistriesExposeTheSameCanonicalTypes(): void
	{
		require_once dirname(__DIR__) . '/modules-common/Form/classes/class.FormDescriptorValidatorRegistry.php';

		$js_source = $this->source('modules-common/Form/js/form-validator-registry.js');
		$this->assertSame(1, preg_match('/SUPPORTED_FORM_VALIDATOR_TYPES = \[(.*?)\];/s', $js_source, $matches));
		preg_match_all('/"([a-z_]+)"/', $matches[1], $validator_matches);

		$this->assertSame(self::EXPECTED_VALIDATOR_TYPES, FormDescriptorValidatorRegistry::getSupportedTypes());
		$this->assertSame(self::EXPECTED_VALIDATOR_TYPES, $validator_matches[1]);
	}

	public function testCoreDescriptorValidatorSemanticsAreServerAuthoritative(): void
	{
		require_once dirname(__DIR__) . '/modules-common/Form/classes/class.FormDescriptorValidatorRegistry.php';

		$this->assertFalse(FormDescriptorValidatorRegistry::isValid('required', ''));
		$this->assertTrue(FormDescriptorValidatorRegistry::isValid('required', 'value'));
		$this->assertFalse(FormDescriptorValidatorRegistry::isValid('required', [
			'error' => UPLOAD_ERR_NO_FILE,
			'name' => '',
			'type' => '',
			'tmp_name' => '',
			'size' => 0,
		]));
		$this->assertFalse(FormDescriptorValidatorRegistry::isValid('required', [
			'error' => [
				UPLOAD_ERR_NO_FILE,
				UPLOAD_ERR_NO_FILE,
			],
			'name' => [
				'',
				'',
			],
			'type' => [
				'',
				'',
			],
			'tmp_name' => [
				'',
				'',
			],
			'size' => [
				0,
				0,
			],
		]));
		$this->assertFalse(FormDescriptorValidatorRegistry::isValid('required', [
			'error' => UPLOAD_ERR_PARTIAL,
			'name' => 'document.pdf',
			'type' => 'application/pdf',
			'tmp_name' => '/tmp/php-upload',
			'size' => 512,
		]));
		$this->assertTrue(FormDescriptorValidatorRegistry::isValid('required', [
			'error' => UPLOAD_ERR_OK,
			'name' => 'document.pdf',
			'type' => 'application/pdf',
			'tmp_name' => '/tmp/php-upload',
			'size' => 1024,
		]));
		$this->assertTrue(FormDescriptorValidatorRegistry::isValid('required', [
			'error' => [
				UPLOAD_ERR_OK,
				UPLOAD_ERR_NO_FILE,
			],
			'name' => [
				'document.pdf',
				'',
			],
			'type' => [
				'application/pdf',
				'',
			],
			'tmp_name' => [
				'/tmp/php-upload-one',
				'',
			],
			'size' => [
				1024,
				0,
			],
		]));
		$this->assertTrue(FormDescriptorValidatorRegistry::isValid('email', 'person@example.test'));
		$this->assertFalse(FormDescriptorValidatorRegistry::isValid('email', 'not-email'));
		$this->assertTrue(FormDescriptorValidatorRegistry::isValid('url', 'https://example.test/path'));
		$this->assertFalse(FormDescriptorValidatorRegistry::isValid('url', 'example.test/path'));
		$this->assertTrue(FormDescriptorValidatorRegistry::isValid('min_length', 'abc', ['min' => 3]));
		$this->assertFalse(FormDescriptorValidatorRegistry::isValid('max_length', 'abcd', ['max' => 3]));
		$this->assertTrue(FormDescriptorValidatorRegistry::isValid('number_min', '5', ['min' => 5]));
		$this->assertFalse(FormDescriptorValidatorRegistry::isValid('number_max', '6', ['max' => 5]));
		$this->assertTrue(FormDescriptorValidatorRegistry::isValid('regex', 'abc-123', ['pattern' => '/^[a-z]+-[0-9]+$/']));
		$this->assertFalse(FormDescriptorValidatorRegistry::isValid('enum', 'archived', ['values' => ['draft', 'published']]));
		$this->assertTrue(FormDescriptorValidatorRegistry::isValid('enum', 'draft', ['values' => ['draft' => 'Draft']]));
		$this->assertTrue(FormDescriptorValidatorRegistry::isValid('enum', '1', ['values' => ['No', 'Yes']]));
		$this->assertTrue(FormDescriptorValidatorRegistry::isValid('enum', 'published', ['values' => [['value' => 'published', 'label' => 'Published']]]));
		$this->assertTrue(FormDescriptorValidatorRegistry::isValid('enum', ['alpha' => '1', 'beta' => '1'], ['values' => ['alpha', 'beta', 'gamma']]));
		$this->assertFalse(FormDescriptorValidatorRegistry::isValid('enum', ['alpha' => '1', 'omega' => '1'], ['values' => ['alpha', 'beta', 'gamma']]));
		$this->assertTrue(FormDescriptorValidatorRegistry::isValid('date', '2026-05-19'));
		$this->assertFalse(FormDescriptorValidatorRegistry::isValid('date', '2026-02-31'));
		$this->assertTrue(FormDescriptorValidatorRegistry::isValid('file_type', [
			'error' => UPLOAD_ERR_OK,
			'name' => 'document.pdf',
			'type' => 'application/pdf',
		], ['extensions' => ['pdf']]));
		$this->assertTrue(FormDescriptorValidatorRegistry::isValid('file_type', [
			'path' => '/tmp/radaptor-rebuilt-upload',
			'original_name' => 'chunked-document.pdf',
			'mime' => 'application/pdf',
			'size' => 1024,
		], ['extensions' => ['pdf']]));
		$this->assertTrue(FormDescriptorValidatorRegistry::isValid('file_type', [
			'error' => [
				UPLOAD_ERR_OK,
				UPLOAD_ERR_OK,
			],
			'name' => [
				'document.pdf',
				'notes.txt',
			],
			'type' => [
				'application/pdf',
				'text/plain',
			],
			'tmp_name' => [
				'/tmp/php-upload-one',
				'/tmp/php-upload-two',
			],
			'size' => [
				1024,
				512,
			],
		], ['extensions' => ['pdf', 'txt']]));
		$this->assertFalse(FormDescriptorValidatorRegistry::isValid('file_type', [
			'error' => UPLOAD_ERR_PARTIAL,
			'name' => 'document.pdf',
			'type' => 'application/pdf',
		], ['extensions' => ['pdf']]));
		$this->assertFalse(FormDescriptorValidatorRegistry::isValid('file_type', [
			'error' => [
				UPLOAD_ERR_OK,
				UPLOAD_ERR_PARTIAL,
			],
			'name' => [
				'document.pdf',
				'notes.txt',
			],
			'type' => [
				'application/pdf',
				'text/plain',
			],
			'tmp_name' => [
				'/tmp/php-upload-one',
				'/tmp/php-upload-two',
			],
			'size' => [
				1024,
				512,
			],
		], ['extensions' => ['pdf', 'txt']]));
		$this->assertTrue(FormDescriptorValidatorRegistry::isValid('file_size', [
			'error' => UPLOAD_ERR_OK,
			'size' => 1024,
		], ['max_bytes' => 2048]));
		$this->assertTrue(FormDescriptorValidatorRegistry::isValid('file_size', [
			'path' => '/tmp/radaptor-rebuilt-upload',
			'original_name' => 'chunked-document.pdf',
			'size' => 1024,
		], ['max_bytes' => 2048]));
		$this->assertFalse(FormDescriptorValidatorRegistry::isValid('file_size', [
			'path' => '/tmp/radaptor-rebuilt-upload',
			'original_name' => 'chunked-document.pdf',
			'size' => 4096,
		], ['max_bytes' => 2048]));
		$this->assertTrue(FormDescriptorValidatorRegistry::isValid('file_size', [
			'error' => [
				UPLOAD_ERR_OK,
				UPLOAD_ERR_OK,
			],
			'name' => [
				'document.pdf',
				'notes.txt',
			],
			'tmp_name' => [
				'/tmp/php-upload-one',
				'/tmp/php-upload-two',
			],
			'size' => [
				1024,
				512,
			],
		], ['max_bytes' => 2048]));
		$this->assertFalse(FormDescriptorValidatorRegistry::isValid('file_size', [
			'error' => [
				UPLOAD_ERR_OK,
				UPLOAD_ERR_OK,
			],
			'name' => [
				'document.pdf',
				'notes.txt',
			],
			'tmp_name' => [
				'/tmp/php-upload-one',
				'/tmp/php-upload-two',
			],
			'size' => [
				1024,
				4096,
			],
		], ['max_bytes' => 2048]));
		$this->assertFalse(FormDescriptorValidatorRegistry::isValid('file_size', [
			'error' => UPLOAD_ERR_INI_SIZE,
			'size' => 0,
		], ['max_bytes' => 2048]));
	}

	public function testPhase4fPublisherExposesDryRunAndApplyContracts(): void
	{
		$source = $this->source('modules-common/Form/classes/class.FormCaptureDescriptorSpecLoader.php');

		$this->assertStringContainsString('final class FormCaptureDescriptorSpecLoader', $source);
		$this->assertStringContainsString('public static function previewPublish(', $source);
		$this->assertStringContainsString('public static function applyPublish(', $source);
		$this->assertStringContainsString('public static function previewSync(', $source);
		$this->assertStringContainsString('public static function applySync(', $source);
		$this->assertStringContainsString('FormCaptureDescriptorSchemaValidator::validateForDefinition', $source);
		$this->assertStringContainsString('FormCaptureDefinitionRepository', $source);
		$this->assertStringContainsString("'dry_run' => true", $source);
		$this->assertStringContainsString("'dry_run' => false", $source);
	}

	public function testPhase4fRuntimeCacheContractGuardsPublishedDescriptorIntegrity(): void
	{
		$cache_source = $this->source('modules-common/Form/classes/class.FormCaptureCompiledDescriptorCache.php');
		$repository_source = $this->source('modules-common/Form/classes/class.FormCaptureDefinitionRepository.php');

		$this->assertStringContainsString('final class FormCaptureCompiledDescriptorCache', $cache_source);
		$this->assertStringContainsString('public function write(', $cache_source);
		$this->assertStringContainsString('public function read(', $cache_source);
		$this->assertStringContainsString('public function deleteStaleForSlug(', $cache_source);
		$this->assertStringContainsString('descriptor_hash', $cache_source);
		$this->assertStringContainsString('normalized_descriptor_hash', $cache_source);
		$this->assertStringContainsString('Config::LINUX_FILE_OWNER', $cache_source);
		$this->assertStringContainsString('Config::LINUX_FILE_GROUP', $cache_source);
		$this->assertStringContainsString('Config::LINUX_FILE_MODE_DIRECTORY', $cache_source);
		$this->assertStringContainsString('FormCaptureCompiledDescriptorCache', $repository_source);
		$this->assertStringContainsString('hash_equals', $repository_source);
		$this->assertStringContainsString('descriptor_hash', $repository_source);
	}

	public function testPhase4fCaptureWidgetKeepsUnavailableDefinitionsAsRenderableFallback(): void
	{
		$source = $this->source('modules-common/Form/widgets/Widget.CaptureForm.php');

		$this->assertStringContainsString('FormDefinitionResolver::resolve($definition_slug)', $source);
		$this->assertStringContainsString('catch (FormCaptureRuntimeException)', $source);
		$this->assertStringContainsString("t('form.capture.error_unavailable')", $source);
		$this->assertStringContainsString('$resolution === null || !$resolution->isCapture()', $source);
		$this->assertStringNotContainsString('Kernel::abort', $source);
	}

	public function testPhase4jVersionedSubmitUsesExactCaptureVersionWhenProvided(): void
	{
		$context_source = $this->source('modules-common/Form/classes/class.FormSubmitContext.php');
		$event_source = $this->source('modules-common/Form/events/Event.FormSubmit.php');
		$resolver_source = $this->source('modules-common/Form/classes/class.FormDefinitionResolver.php');
		$repository_source = $this->source('modules-common/Form/classes/class.FormCaptureDefinitionRepository.php');
		$validator_source = $this->source('modules-common/Form/classes/class.FormCaptureDescriptorSchemaValidator.php');

		$this->assertStringContainsString('FIELD_FORM_DEFINITION_VERSION_ID', $context_source);
		$this->assertStringContainsString('FIELD_FORM_RENDER_STATE_ID', $context_source);
		$this->assertStringContainsString('SESSION_KEY_RENDER_STATES', $context_source);
		$this->assertStringContainsString('form_definition_resolution', $context_source);
		$this->assertStringContainsString('formDefinitionVersionId: self::positiveIntOrNull', $context_source);
		$this->assertStringContainsString('validateRenderState(array $post)', $context_source);
		$this->assertStringContainsString('hasRenderStateForSubmittedForm()', $context_source);
		$this->assertStringContainsString('$render_state_error = $context->validateRenderState($post);', $event_source);
		$this->assertStringContainsString('FormDefinitionResolver::resolve($context->formId, $context->formDefinitionVersionId)', $event_source);
		$this->assertLessThan(
			strpos($event_source, 'FormDefinitionResolver::resolve($context->formId, $context->formDefinitionVersionId)'),
			strpos($event_source, '$render_state_error = $context->validateRenderState($post);')
		);
		$this->assertStringContainsString('resolve(string $form_id, ?int $form_definition_version_id = null)', $resolver_source);
		$this->assertStringContainsString('findPublishedResolution($form_id, $form_definition_version_id)', $resolver_source);
		$this->assertStringContainsString('findPublishedResolution(string $definition_slug, ?int $version_id = null)', $repository_source);
		$this->assertStringContainsString("v.version_id = ?", $repository_source);
		$this->assertStringContainsString("v.status = 'published'", $repository_source);
		$this->assertStringContainsString('FormSubmitContext::FIELD_FORM_DEFINITION_VERSION_ID', $validator_source);
		$this->assertStringContainsString('FormSubmitContext::FIELD_FORM_RENDER_STATE_ID', $validator_source);
	}

	public function testPhase4jBuilderAuthoringContractsAreCsrfGuardedAuditedAndDraftOnly(): void
	{
		$authoring_source = $this->source('modules-common/Form/classes/class.FormCaptureAuthoringService.php');
		$create_source = $this->source('modules-common/Form/events/Event.FormBuilderCreate.php');
		$save_source = $this->source('modules-common/Form/events/Event.FormBuilderSaveDraft.php');
		$publish_source = $this->source('modules-common/Form/events/Event.FormBuilderPublish.php');
		$preview_source = $this->source('modules-common/Form/events/Event.FormBuilderPreviewRender.php');
		$fragment_source = $this->source('modules-common/Form/events/Event.FormBuilderEditorFragment.php');
		$load_draft_source = $this->source('modules-common/Form/events/Event.FormBuilderLoadDraftVersion.php');
		$note_source = $this->source('modules-common/Form/events/Event.FormBuilderUpdateDraftNote.php');

		foreach ([$create_source, $save_source, $publish_source, $preview_source, $fragment_source, $load_draft_source, $note_source] as $event_source) {
			$this->assertStringContainsString('FormBuilderEventHelper::authorizeContentAdmin', $event_source);
		}

		foreach ([$create_source, $save_source, $publish_source, $preview_source, $load_draft_source, $note_source] as $event_source) {
			$this->assertStringContainsString('FormBuilderEventHelper::validateCsrfFromPost', $event_source);
		}

		$this->assertStringContainsString("CmsMutationAuditService::withContext(\n\t\t\t\t'form_builder.create'", $create_source);
		$this->assertStringContainsString("CmsMutationAuditService::withContext(\n\t\t\t\t'form_builder.save_draft'", $save_source);
		$this->assertStringContainsString("CmsMutationAuditService::recordLeaf('form_builder.save_draft.conflict'", $save_source);
		$this->assertStringContainsString("CmsMutationAuditService::withContext(\n\t\t\t\t'form_builder.publish'", $publish_source);
		$this->assertStringContainsString("CmsMutationAuditService::withContext(\n\t\t\t\t'form_builder.update_draft_note'", $note_source);
		$this->assertStringNotContainsString('CmsMutationAuditService::withContext', $preview_source);
		$this->assertStringNotContainsString('CmsMutationAuditService::withContext', $load_draft_source);
		$this->assertStringNotContainsString('FormBuilderEventHelper::validateCsrfFromPost', $fragment_source);
		$this->assertStringNotContainsString('CmsMutationAuditService::withContext', $fragment_source);
		$this->assertStringContainsString("'status' => self::STATUS_DRAFT", $authoring_source);
		$this->assertStringContainsString('Only the active draft version can be published.', $authoring_source);
		$this->assertStringContainsString('Shipped capture form definitions are read-only in the builder.', $authoring_source);
		$this->assertStringContainsString('status = ?', $authoring_source);
		$this->assertStringContainsString('self::STATUS_ABANDONED', $authoring_source);
	}

	public function testPhase4jBuilderWidgetAndPhpstanCoverageIncludesTouchedRuntimeFiles(): void
	{
		$authoring_source = $this->source('modules-common/Form/classes/class.FormCaptureAuthoringService.php');
		$widget_source = $this->source('modules-common/Form/widgets/Widget.CaptureFormBuilder.php');
		$list_widget_source = $this->source('modules-common/Form/widgets/Widget.CaptureFormList.php');
		$template_source = $this->source('modules-common/Form/templates/template.captureFormBuilder.php');
		$list_template_source = $this->source('modules-common/Form/templates/template.captureFormList.php');
		$phpstan_source = $this->source('phpstan.neon');

		$this->assertStringContainsString("library('__ADMIN_FORM_BUILDER')", $template_source);
		$this->assertStringContainsString("library('__ADMIN_FORM_BUILDER')", $list_template_source);
		$this->assertStringContainsString('FormSubmitContext::issueCsrfTokenForForm(FormBuilderEventHelper::CSRF_FORM_ID)', $authoring_source);
		$this->assertStringContainsString('FormSubmitContext::issueCsrfTokenForForm(FormBuilderEventHelper::CSRF_FORM_ID)', $list_widget_source);
		$this->assertStringContainsString('form.builder.error_create', $widget_source);
		$this->assertStringContainsString("Url::getUrl('form_builder.editor_fragment')", $list_widget_source);
		$this->assertStringContainsString('data-form-list-editor-fragment-url-value', $list_template_source);
		$this->assertStringContainsString('$showLifecycleColumns = $sourceFilter !== \'system\';', $list_template_source);
		$this->assertStringContainsString('<?php if (!$readOnly): ?>', $template_source);
		$this->assertStringNotContainsString('return_to', $list_template_source);
		$this->assertStringContainsString("'form' => \$definitionSlug", $list_template_source);
		$this->assertStringNotContainsString("'edit' => \$definitionSlug", $list_template_source);
		$this->assertStringNotContainsString('?edit=', $list_template_source);
		$this->assertStringContainsString('nav nav-tabs form-list__tabs', $list_template_source);
		$this->assertStringContainsString('nav-link form-list__tab', $list_template_source);
		$this->assertStringContainsString('aria-current="page"', $list_template_source);
		$this->assertStringContainsString("'/admin/forms/'", $list_widget_source);

		foreach ([
			'modules-common/Form/classes/class.FormSubmitContext.php',
			'modules-common/Form/classes/class.FormDefinitionResolver.php',
			'modules-common/Form/classes/class.FormDefinitionResolution.php',
			'modules-common/Form/classes/class.FormResponseEmitter.php',
			'modules-common/Form/events/Event.FormSubmit.php',
			'modules-common/Form/events/Event.FormBuilderEditorFragment.php',
			'modules-common/Form/events/Event.FormBuilderLoadDraftVersion.php',
			'modules-common/Form/events/Event.FormBuilderUpdateDraftNote.php',
			'modules-common/Form/classes/class.I18nReferenceAuditService.php',
			'modules-common/Form/widgets/Widget.CaptureFormBuilder.php',
			'modules-common/Form/widgets/Widget.CaptureFormList.php',
		] as $path) {
			$this->assertStringContainsString($path, $phpstan_source);
		}
	}

	public function testI18nReferenceAuditRejectsUnseededShippedDescriptorKeys(): void
	{
		require_once dirname(__DIR__) . '/modules-common/Form/classes/class.FormCaptureDescriptorSchemaValidator.php';
		require_once dirname(__DIR__) . '/modules-common/Form/classes/class.I18nReferenceAuditService.php';

		$temp_dir = sys_get_temp_dir() . '/radaptor-i18n-reference-audit-' . bin2hex(random_bytes(6));
		mkdir($temp_dir, 0o777, true);

		try {
			$this->writeSeedFile($temp_dir . '/en-US.csv', [
				['form', 'capture_demo.title', 'en-US', 'Contact demo'],
			]);
			$this->writeSeedFile($temp_dir . '/hu-HU.csv', [
				['form', 'capture_demo.title', 'hu-HU', 'Kapcsolat demó'],
			]);

			$result = I18nReferenceAuditService::audit([
				'locales' => ['en-US', 'hu-HU'],
				'seed_targets' => [[
					'input_dir' => $temp_dir,
				]],
				'descriptor_rows' => [[
					'definition_slug' => 'capture-contact-demo',
					'source' => 'shipped',
					'version_id' => 10,
					'version_number' => 1,
					'descriptor' => [
						'kind' => 'capture',
						'title' => ['key' => 'form.capture_demo.title'],
						'description' => ['key' => 'form.capture_demo.description'],
						'fields' => [
							[
								'type' => 'text',
								'name' => 'name',
								'key' => 'name',
								'label' => ['text' => 'Name'],
							],
						],
					],
				]],
			]);
		} finally {
			@unlink($temp_dir . '/en-US.csv');
			@unlink($temp_dir . '/hu-HU.csv');
			@rmdir($temp_dir);
		}

		$this->assertSame('error', $result['status']);
		$this->assertSame(2, $result['references_scanned']);
		$this->assertSame(2, $result['missing_references']);
		$this->assertSame('i18n_reference_missing_seed', $result['issues'][0]['code'] ?? null);
		$this->assertSame('form.capture_demo.description', $result['issues'][0]['key'] ?? null);
	}

	public function testCaptureDescriptorValidatorAndAuditHandleCustomFormI18nKeys(): void
	{
		require_once dirname(__DIR__) . '/modules-common/Form/classes/class.FormDescriptorValidatorRegistry.php';
		require_once dirname(__DIR__) . '/modules-common/Form/classes/class.FormCaptureDescriptorSchemaValidator.php';
		require_once dirname(__DIR__) . '/modules-common/Form/classes/class.I18nReferenceAuditService.php';

		$descriptor = [
			'kind' => 'capture',
			'i18n_mode' => FormCaptureDescriptorSchemaValidator::I18N_MODE_KEYED,
			'title' => [
				'key' => 'form_def.capture_contact_demo.title',
				'text' => 'Contact demo',
			],
			'fields' => [
				[
					'type' => 'text',
					'name' => 'name',
					'key' => 'name',
					'label' => [
						'key' => 'form_def.capture_contact_demo.fields.name.label',
						'text' => 'Name',
					],
				],
			],
		];

		FormCaptureDescriptorSchemaValidator::validateForDefinition('capture-contact-demo', $descriptor, null);

		$invalid = $descriptor;
		$invalid['title'] = ['text' => 'Missing key'];

		try {
			FormCaptureDescriptorSchemaValidator::validateForDefinition('capture-contact-demo', $invalid, null);
			$this->fail('Keyed capture form descriptors must reject text definitions without i18n keys.');
		} catch (InvalidArgumentException $exception) {
			$this->assertStringContainsString('must use an i18n key', $exception->getMessage());
		}

		$result = I18nReferenceAuditService::audit([
			'locales' => ['en-US'],
			'seed_targets' => [],
			'message_keys' => ['form_def.capture_contact_demo.title'],
			'descriptor_rows' => [[
				'definition_slug' => 'capture-contact-demo',
				'source' => 'db',
				'version_id' => 11,
				'version_number' => 2,
				'descriptor' => $descriptor,
			]],
		]);

		$this->assertSame('error', $result['status']);
		$this->assertSame(2, $result['references_scanned']);
		$this->assertSame(1, $result['missing_references']);
		$this->assertSame('i18n_reference_missing_db_message', $result['issues'][0]['code'] ?? null);
		$this->assertSame('form_def.capture_contact_demo.fields.name.label', $result['issues'][0]['key'] ?? null);
	}

	private function source(string $relativePath): string
	{
		$path = dirname(__DIR__) . '/' . $relativePath;
		$this->assertFileExists($path);

		return (string) file_get_contents($path);
	}

	/**
	 * @param list<array{0: string, 1: string, 2: string, 3: string}> $rows
	 */
	private function writeSeedFile(string $path, array $rows): void
	{
		$handle = fopen($path, 'w');
		$this->assertIsResource($handle);
		fputcsv($handle, ['domain', 'key', 'context', 'locale', 'source_text', 'expected_text', 'human_reviewed', 'allow_source_match', 'text']);

		foreach ($rows as [$domain, $key, $locale, $text]) {
			fputcsv($handle, [$domain, $key, '', $locale, $text, $text, '0', '0', $text]);
		}

		fclose($handle);
	}
}
