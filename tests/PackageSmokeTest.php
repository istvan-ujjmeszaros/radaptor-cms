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

	public function testPageEditorIframeDoesNotBuildAdminDropdownChrome(): void
	{
		$root = dirname(__DIR__);
		$view_source = (string) file_get_contents($root . '/modules-common/Cms/classes/class.AbstractWebpageViewBase.php');

		$this->assertMatchesRegularExpression(
			'/public function buildAdminDropdownTree\(\): \?array\s+\{\s+if \(CmsConfig::isPageEditorIframeRequest\(\)\) \{\s+return null;\s+\}/',
			$view_source
		);
	}

	public function testMenuAdministrationWidgetsAreAdminSurfaceOnly(): void
	{
		$root = dirname(__DIR__);
		$main_menu_source = (string) file_get_contents($root . '/modules-common/MainMenu/widgets/Widget.MainMenu.php');
		$admin_menu_source = (string) file_get_contents($root . '/modules-common/AdminMenu/widgets/Widget.AdminMenu.php');

		$this->assertStringContainsString("'surfaces' => ['admin']", $main_menu_source);
		$this->assertStringContainsString("'surfaces' => ['admin']", $admin_menu_source);
		$this->assertStringNotContainsString("'surfaces' => ['public']", $main_menu_source);
	}

	public function testEditBarFormCommandsExposeEditorFragmentUrls(): void
	{
		$root = dirname(__DIR__);
		$form_source = (string) file_get_contents($root . '/modules-common/Form/classes/class.Form.php');
		$event_source = (string) file_get_contents($root . '/modules-common/Form/events/Event.FormEditorFragment.php');
		$widget_source = (string) file_get_contents($root . '/modules-common/Cms/classes/class.Widget.php');
		$edit_bar_source = (string) file_get_contents($root . '/templates-common/default-SoAdmin/Cms/template.editBar.common.php');
		$fragment_assets_source = (string) file_get_contents($root . '/modules-common/Cms/classes/class.HtmlFragmentAssetRenderer.php');
		$fragment_template_source = (string) file_get_contents($root . '/modules-common/Cms/classes/class.HtmlFragmentTemplate.php');
		$cms_fragment_source = (string) file_get_contents($root . '/modules-common/Cms/classes/class.CmsFragmentRenderer.php');

		$this->assertStringContainsString("Url::getUrl('form.editor_fragment'", $form_source);
		$this->assertStringContainsString('getEditorFragmentUrlFromSeoUrl', $form_source);
		$this->assertStringContainsString("'event_name' => 'form.editor_fragment'", $event_source);
		$this->assertStringContainsString('HtmlFragmentAssetRenderer::renderTemplatesFromRenderer($renderer)', $event_source);
		$this->assertStringContainsString('template_class: HtmlFragmentTemplate::class', $event_source);
		$this->assertStringContainsString('Form::getEditorFragmentUrlFromSeoUrl($command->url)', $widget_source);
		$this->assertStringContainsString("'properties_url' => \$properties_url", $widget_source);
		$this->assertStringContainsString('data-page-editor-properties-url', $edit_bar_source);
		$this->assertStringContainsString('template data-radaptor-fragment-assets', $fragment_assets_source);
		$this->assertStringContainsString('data-radaptor-fragment-asset', $fragment_assets_source);
		$this->assertStringContainsString('return $content;', $fragment_template_source);
		$this->assertStringContainsString('HtmlFragmentAssetRenderer::renderTemplatesFromRenderer($this->renderer)', $cms_fragment_source);
	}

	private function relativePath(string $path, string $root): string
	{
		return ltrim(str_replace($root, '', $path), '/');
	}
}
