<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!class_exists('AutoloaderFromGeneratedMap', false)) {
	final class AutoloaderFromGeneratedMap
	{
		public static function autoloaderClassExists(string $class_name): bool
		{
			return false;
		}
	}
}

require_once dirname(__DIR__) . '/modules-common/Form/classes/class.FormSubmitContext.php';
require_once dirname(__DIR__) . '/modules-common/Form/classes/class.FormClassResolver.php';
require_once dirname(__DIR__) . '/modules-common/Form/classes/class.FormCaptureFieldIdentity.php';
require_once dirname(__DIR__) . '/modules-common/Form/classes/class.FormCaptureDescriptorSchemaValidator.php';
require_once dirname(__DIR__) . '/modules-common/Form/classes/class.I18nReferenceAuditService.php';

final class I18nReferenceAuditServiceTest extends TestCase
{
	public function testAuditRejectsUnseededShippedDescriptorKeys(): void
	{
		$temp_dir = sys_get_temp_dir() . '/radaptor-i18n-reference-audit-' . bin2hex(random_bytes(6));
		mkdir($temp_dir, 0o777, true);

		try {
			$this->writeSeedFile($temp_dir . '/en-US.csv', [
				['form', 'capture_demo.title', 'en-US', 'Contact demo'],
			]);
			$this->writeSeedFile($temp_dir . '/hu-HU.csv', [
				['form', 'capture_demo.title', 'hu-HU', 'Kapcsolat demo'],
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

	public function testAuditRejectsDbDescriptorKeysMissingFromMessageIndex(): void
	{
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

	/**
	 * @param list<array{0: string, 1: string, 2: string, 3: string}> $rows
	 */
	private function writeSeedFile(string $path, array $rows): void
	{
		$handle = fopen($path, 'w');
		$this->assertIsResource($handle);
		fputcsv($handle, ['domain', 'key', 'context', 'locale', 'source_text', 'expected_text', 'human_reviewed', 'allow_source_match', 'text'], ',', '"', '');

		foreach ($rows as [$domain, $key, $locale, $text]) {
			fputcsv($handle, [$domain, $key, '', $locale, $text, $text, '0', '0', $text], ',', '"', '');
		}

		fclose($handle);
	}
}
