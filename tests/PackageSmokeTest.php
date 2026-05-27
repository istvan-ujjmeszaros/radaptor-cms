<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PackageSmokeTest extends TestCase
{
	public function testRegistryPackageMetadataIsValid(): void
	{
		$root = dirname(__DIR__);
		$metadata_path = $root . '/.registry-package.json';
		$this->assertFileExists($metadata_path);

		$decoded = json_decode((string) file_get_contents($metadata_path), true);
		$this->assertIsArray($decoded);
		$this->assertSame('radaptor/core/cms', $decoded['package'] ?? null);
		$this->assertSame('core', $decoded['type'] ?? null);
		$this->assertSame('cms', $decoded['id'] ?? null);
		$this->assertIsString($decoded['dependencies']['radaptor/core/framework'] ?? null);
		$this->assertMatchesRegularExpression('/^\^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $decoded['dependencies']['radaptor/core/framework']);
		$this->assertSame('^8.5', $decoded['composer']['require']['php'] ?? null);
	}

	public function testCmsCoreEntrypointsExist(): void
	{
		$root = dirname(__DIR__);

		$this->assertFileExists($root . '/class.LibrariesCommon.php');
		$this->assertFileExists($root . '/modules-common/Cms/classes/class.CmsResourceSpecService.php');
		$this->assertFileExists($root . '/modules-common/Cms/classes/class.CmsSeedHelper.php');
		$this->assertFileExists($root . '/modules-common/Mailpit/classes/class.MailpitClient.php');
		$this->assertFileExists($root . '/modules-common/Mailpit/widgets/Widget.Mailpit.php');
		$this->assertFileExists($root . '/modules-common/Mailpit/templates/template.mailpit.inbox.php');
		$this->assertFileExists($root . '/modules-common/Mailpit/i18n/seeds/en-US.csv');
		$this->assertDirectoryExists($root . '/templates-common');
	}

	public function testI18nSeedFilenamesUseCanonicalLocalesOnly(): void
	{
		$root = dirname(__DIR__);
		$seed_dirs = [];

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
		);

		foreach ($iterator as $file) {
			if (!$file instanceof SplFileInfo || $file->getExtension() !== 'csv') {
				continue;
			}

			$seed_dir = $file->getPath();

			if (!str_ends_with($seed_dir, '/i18n/seeds')) {
				continue;
			}

			$seed_dirs[$seed_dir][] = $file;
		}

		$legacy_files = [];
		$duplicate_locales = [];

		foreach ($seed_dirs as $seed_dir => $files) {
			$files_by_canonical_locale = [];

			foreach ($files as $file) {
				$locale = $file->getBasename('.csv');
				$canonical_locale = str_replace('_', '-', $locale);

				if (str_contains($locale, '_')) {
					$legacy_files[] = $this->relativePath($file->getPathname(), $root);
				}

				$files_by_canonical_locale[$canonical_locale][] = $this->relativePath($file->getPathname(), $root);
			}

			foreach ($files_by_canonical_locale as $canonical_locale => $locale_files) {
				if (count($locale_files) > 1) {
					$duplicate_locales[] = $this->relativePath($seed_dir, $root) . ': ' . $canonical_locale . ' => ' . implode(', ', $locale_files);
				}
			}
		}

		$failures = [];

		if ($legacy_files !== []) {
			$failures[] = "Legacy underscore locale seed filenames:\n" . implode("\n", $legacy_files);
		}

		if ($duplicate_locales !== []) {
			$failures[] = "Canonical/legacy duplicate locale seed filenames:\n" . implode("\n", $duplicate_locales);
		}

		$this->assertSame([], $failures, implode("\n\n", $failures));
	}

	public function testCaptureFormWidgetTranslationsLiveInRootSeedScope(): void
	{
		$root = dirname(__DIR__);
		$root_seed = (string) file_get_contents($root . '/i18n/seeds/en-US.csv');
		$form_seed = (string) file_get_contents($root . '/modules-common/Form/i18n/seeds/en-US.csv');

		$this->assertStringContainsString('widget,capture_form.name', $root_seed);
		$this->assertStringContainsString('widget,capture_form_builder.name', $root_seed);
		$this->assertStringContainsString('widget,capture_form_list.name', $root_seed);
		$this->assertStringNotContainsString('widget,capture_form.name', $form_seed);
		$this->assertStringNotContainsString('widget,capture_form_builder.name', $form_seed);
		$this->assertStringNotContainsString('widget,capture_form_list.name', $form_seed);
	}

	public function testActiveLocaleFallbackSeedsCoverFormBuilderKeys(): void
	{
		$root = dirname(__DIR__);
		$root_de_seed = (string) file_get_contents($root . '/i18n/seeds/de-DE.csv');
		$form_de_seed = (string) file_get_contents($root . '/modules-common/Form/i18n/seeds/de-DE.csv');

		$this->assertStringContainsString('admin,menu.forms,,de-DE', $root_de_seed);
		$this->assertStringContainsString('widget,capture_form_builder.name,,de-DE', $root_de_seed);
		$this->assertStringContainsString('form,builder.title,,de-DE', $form_de_seed);
		$this->assertStringContainsString('form,list.title,,de-DE', $form_de_seed);
	}

	private function relativePath(string $path, string $root): string
	{
		return ltrim(str_replace($root, '', $path), '/');
	}
}
