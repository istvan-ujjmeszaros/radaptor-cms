<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

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
		$this->assertStringContainsString('form_definition_resolution', $context_source);
		$this->assertStringContainsString('formDefinitionVersionId: self::positiveIntOrNull', $context_source);
		$this->assertStringContainsString('FormDefinitionResolver::resolve($context->formId, $context->formDefinitionVersionId)', $event_source);
		$this->assertStringContainsString('resolve(string $form_id, ?int $form_definition_version_id = null)', $resolver_source);
		$this->assertStringContainsString('findPublishedResolution($form_id, $form_definition_version_id)', $resolver_source);
		$this->assertStringContainsString('findPublishedResolution(string $definition_slug, ?int $version_id = null)', $repository_source);
		$this->assertStringContainsString("v.version_id = ?", $repository_source);
		$this->assertStringContainsString("v.status = 'published'", $repository_source);
		$this->assertStringContainsString('FormSubmitContext::FIELD_FORM_DEFINITION_VERSION_ID', $validator_source);
	}

	public function testPhase4jBuilderAuthoringContractsAreCsrfGuardedAuditedAndDraftOnly(): void
	{
		$authoring_source = $this->source('modules-common/Form/classes/class.FormCaptureAuthoringService.php');
		$create_source = $this->source('modules-common/Form/events/Event.FormBuilderCreate.php');
		$save_source = $this->source('modules-common/Form/events/Event.FormBuilderSaveDraft.php');
		$publish_source = $this->source('modules-common/Form/events/Event.FormBuilderPublish.php');
		$preview_source = $this->source('modules-common/Form/events/Event.FormBuilderPreviewRender.php');

		foreach ([$create_source, $save_source, $publish_source, $preview_source] as $event_source) {
			$this->assertStringContainsString('FormBuilderEventHelper::authorizeContentAdmin', $event_source);
			$this->assertStringContainsString('FormBuilderEventHelper::validateCsrfFromPost', $event_source);
		}

		$this->assertStringContainsString("CmsMutationAuditService::withContext(\n\t\t\t\t'form_builder.create'", $create_source);
		$this->assertStringContainsString("CmsMutationAuditService::withContext(\n\t\t\t\t'form_builder.save_draft'", $save_source);
		$this->assertStringContainsString("CmsMutationAuditService::recordLeaf('form_builder.save_draft.conflict'", $save_source);
		$this->assertStringContainsString("CmsMutationAuditService::withContext(\n\t\t\t\t'form_builder.publish'", $publish_source);
		$this->assertStringNotContainsString('CmsMutationAuditService::withContext', $preview_source);
		$this->assertStringContainsString("'status' => self::STATUS_DRAFT", $authoring_source);
		$this->assertStringContainsString('Only the active draft version can be published.', $authoring_source);
		$this->assertStringContainsString('Shipped capture form definitions are read-only in the builder.', $authoring_source);
		$this->assertStringContainsString('status = ?', $authoring_source);
		$this->assertStringContainsString('self::STATUS_ABANDONED', $authoring_source);
	}

	public function testPhase4jBuilderWidgetAndPhpstanCoverageIncludesTouchedRuntimeFiles(): void
	{
		$widget_source = $this->source('modules-common/Form/widgets/Widget.CaptureFormBuilder.php');
		$template_source = $this->source('modules-common/Form/templates/template.captureFormBuilder.php');
		$phpstan_source = $this->source('phpstan.neon');

		$this->assertStringContainsString("library('__ADMIN_FORM_BUILDER')", $template_source);
		$this->assertStringContainsString('FormSubmitContext::issueCsrfTokenForForm(FormBuilderEventHelper::CSRF_FORM_ID)', $widget_source);
		$this->assertStringContainsString('form.builder.error_create', $widget_source);

		foreach ([
			'modules-common/Form/classes/class.FormSubmitContext.php',
			'modules-common/Form/classes/class.FormDefinitionResolver.php',
			'modules-common/Form/classes/class.FormDefinitionResolution.php',
			'modules-common/Form/classes/class.FormResponseEmitter.php',
			'modules-common/Form/events/Event.FormSubmit.php',
			'modules-common/Form/widgets/Widget.CaptureFormBuilder.php',
		] as $path) {
			$this->assertStringContainsString($path, $phpstan_source);
		}
	}

	private function source(string $relativePath): string
	{
		$path = dirname(__DIR__) . '/' . $relativePath;
		$this->assertFileExists($path);

		return (string) file_get_contents($path);
	}
}
