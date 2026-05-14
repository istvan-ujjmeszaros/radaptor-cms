<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!function_exists('t')) {
	function t(string $key): string
	{
		return $key;
	}
}

require_once dirname(__DIR__) . '/../framework/classes/class.LocaleService.php';
require_once dirname(__DIR__) . '/../framework/classes/class.LocaleRegistry.php';
require_once dirname(__DIR__) . '/../framework/classes/class.I18nCsvSchema.php';
require_once dirname(__DIR__) . '/../framework/classes/class.CsvHelper.php';
require_once dirname(__DIR__) . '/../framework/classes/class.iCsvMap.php';
require_once dirname(__DIR__) . '/../framework/classes/interface.iImportExportDataset.php';
require_once dirname(__DIR__) . '/../framework/classes/class.AbstractImportExportDataset.php';
require_once dirname(__DIR__) . '/../framework/classes/entities/class.iEntity.php';
require_once dirname(__DIR__) . '/../framework/classes/entities/class.SQLEntity.php';
require_once dirname(__DIR__) . '/modules-common/I18n/entities/Entity.I18n_translation.php';
require_once dirname(__DIR__) . '/modules-common/I18n/classes/class.I18nTranslationService.php';
require_once dirname(__DIR__) . '/modules-common/I18n/classes/class.I18nTranslationsWideCsv.php';
require_once dirname(__DIR__) . '/modules-common/I18n/classes/ImportExportDataset.I18nTranslations.php';

final class I18nTranslationServiceSourceMatchTest extends TestCase
{
	public function testAllowSourceMatchIsRejectedWhenIncomingTextDiffersFromSourceText(): void
	{
		$method = new ReflectionMethod(I18nTranslationService::class, '_resolveImportedAllowSourceMatch');

		$this->assertTrue($method->invoke(null, null, true, 'hu-HU', 'Locales', 'Locales'));
		$this->assertFalse($method->invoke(null, null, true, 'hu-HU', 'Locales', 'Locale-ok'));
		$this->assertFalse($method->invoke(null, null, true, 'en-US', 'Locales', 'Locales'));
	}

	public function testAllowSourceMatchPreservesExistingFlagWhenImportOmitsValue(): void
	{
		$method = new ReflectionMethod(I18nTranslationService::class, '_resolveImportedAllowSourceMatch');
		$existingAllowed = new EntityI18n_translation(['allow_source_match' => 1]);
		$existingDenied = new EntityI18n_translation(['allow_source_match' => 0]);

		$this->assertTrue($method->invoke(null, $existingAllowed, null, 'hu-HU', 'Locales', 'Locales'));
		$this->assertFalse($method->invoke(null, $existingAllowed, null, 'hu-HU', 'Locales', 'Locale-ok'));
		$this->assertFalse($method->invoke(null, $existingDenied, null, 'hu-HU', 'Locales', 'Locales'));
	}

	public function testImportWarningDetectsLegacyNormalizedHeader(): void
	{
		$this->assertSame(
			['import_export.warning.allow_source_match_missing'],
			$this->detectImportWarnings(
				"domain,key,context,locale,source_text,expected_text,human_reviewed,text\n"
				. "admin,menu.locales,,hu-HU,Locales,Locales,0,Locales\n",
				'normalized'
			)
		);
	}

	public function testImportWarningAcceptsNormalizedHeaderWithAllowSourceMatch(): void
	{
		$this->assertSame(
			[],
			$this->detectImportWarnings(
				"domain,key,context,locale,source_text,expected_text,human_reviewed,allow_source_match,text\n"
				. "admin,menu.locales,,hu-HU,Locales,Locales,0,1,Locales\n",
				'normalized'
			)
		);
	}

	public function testImportWarningDetectsLegacyWideHeader(): void
	{
		$this->assertSame(
			['import_export.warning.allow_source_match_missing'],
			$this->detectImportWarnings(
				"domain,key,context,source_text,text:hu-HU\n"
				. "admin,menu.locales,,Locales,Locales\n",
				'wide'
			)
		);
	}

	public function testImportWarningAcceptsWideHeaderWithAllowSourceMatchColumns(): void
	{
		$this->assertSame(
			[],
			$this->detectImportWarnings(
				"domain,key,context,source_text,text:hu-HU,allow_source_match:hu-HU\n"
				. "admin,menu.locales,,Locales,Locales,1\n",
				'wide'
			)
		);
	}

	/**
	 * @return list<string>
	 */
	private function detectImportWarnings(string $csvContent, string $format): array
	{
		$dataset = new ImportExportDatasetI18nTranslations();
		$method = new ReflectionMethod($dataset, '_detectImportWarnings');

		return $method->invoke($dataset, $csvContent, $format);
	}
}
