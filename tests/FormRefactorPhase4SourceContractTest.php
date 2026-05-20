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

	private function source(string $relativePath): string
	{
		$path = dirname(__DIR__) . '/' . $relativePath;
		$this->assertFileExists($path);

		return (string) file_get_contents($path);
	}
}
