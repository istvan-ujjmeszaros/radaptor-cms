<?php

class WidgetWidgetPreview extends AbstractWidget
{
	public const string ID = 'widget_preview';

	/**
	 * @return array<string, string>
	 */
	private static function buildInfoStrings(): array
	{
		return [
			'cms.widget_preview.info.title' => t('cms.widget_preview.info.title'),
			'cms.widget_preview.info.widget' => t('cms.widget_preview.info.widget'),
			'cms.widget_preview.info.template' => t('cms.widget_preview.info.template'),
			'cms.widget_preview.info.current_theme' => t('cms.widget_preview.info.current_theme'),
			'cms.widget_preview.info.implemented' => t('cms.widget_preview.info.implemented'),
			'cms.widget_preview.info.fallback' => t('cms.widget_preview.info.fallback'),
		];
	}

	/**
	 * @return array<string, string>
	 */
	private static function buildListStrings(): array
	{
		return [
			'cms.widget_preview.title' => t('cms.widget_preview.title'),
			'cms.widget_preview.none' => t('cms.widget_preview.none'),
			'cms.widget_preview.widget' => t('cms.widget_preview.widget'),
			'cms.widget_preview.description' => t('cms.widget_preview.description'),
			'cms.widget_preview.implemented' => t('cms.widget_preview.implemented'),
			'cms.widget_preview.not_implemented' => t('cms.widget_preview.not_implemented'),
		];
	}

	public static function getName(): string
	{
		return t('widget.' . self::ID . '.name');
	}

	public static function getDescription(): string
	{
		return t('widget.' . self::ID . '.description');
	}

	public static function getListVisibility(): bool
	{
		return Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER);
	}

	public static function getDefaultPathForCreation(): array
	{
		return [
			'path' => '/admin/',
			'resource_name' => 'widget-preview.html',
			'layout' => 'widget_previewer',
		];
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		$widgetName = Request::_GET('widget');

		// No widget specified - show list (use whatever layout admin configured)
		if (!$widgetName) {
			return $this->_buildAvailableWidgetsTree($tree_build_context, $connection);
		}

		// Widget specified - override to widget_previewer layout for proper preview
		$tree_build_context->overrideLayoutType('widget_previewer');

		if (!Widget::checkWidgetExists($widgetName)) {
			return $this->buildStatusTree([
				'severity' => 'error',
				'message' => t('cms.widget_preview.not_registered', ['widget' => $widgetName]),
			]);
		}

		$widget = Widget::factory($widgetName);

		if (!$widget instanceof iMockable) {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'message' => trim(
					t('cms.widget_preview.not_mockable_prefix')
					. ' ' . $widgetName . ' '
					. t('cms.widget_preview.not_mockable_suffix')
				),
			]);
		}

		$mockTree = AbstractWidget::buildMockedTree($widgetName, $tree_build_context, $connection, [
			'is_mock' => true,
		]);
		$templateName = $this->resolvePreviewSupportTemplateName($widgetName, $mockTree);
		$availableThemes = $this->resolvePreviewSupportThemes($templateName);
		$currentTheme = Themes::getThemeNameForUser($tree_build_context->getLayoutTypeName());

		$json_preview_params = [
			'widget' => $widgetName,
			'theme' => $currentTheme,
			'output_channel' => WebpageView::OUTPUT_CHANNEL_SDUI_JSON,
		];

		return $this->createComponentTree('widgetPreviewInfo', [
			'widgetName' => $widgetName,
			'templateName' => $templateName,
			'currentTheme' => $currentTheme,
			'templateScopeNote' => t('cms.widget_preview.template_scope_note'),
			'themesWithWidgetTemplate' => $availableThemes,
			'allThemes' => $this->getPreviewThemeNames(),
			'jsonPreviewUrl' => '?' . http_build_query($json_preview_params),
			'serverPreviewTitle' => t('cms.widget_preview.server_html_preview'),
			'jsonPreviewTitle' => t('cms.widget_preview.subtree_json'),
			'jsonPreviewDescription' => t('cms.widget_preview.subtree_json_description'),
			'openJsonLabel' => t('cms.widget_preview.open_json'),
		], self::buildInfoStrings(), [
			'preview' => [$mockTree],
		]);
	}

	private function _buildAvailableWidgetsTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection): array
	{
		$widgets = [];

		foreach (Widget::getVisibleWidgetList() as $widget) {
			// Get AbstractWidget methods before instanceof narrows the type
			$name = $widget->getTypeName();
			$description = $widget->getDescription();

			if (!$widget instanceof iMockable) {
				continue;
			}

			$templateTree = $widget->buildMockTree($tree_build_context, $connection, ['is_mock' => true]);
			$templateName = $this->resolvePreviewSupportTemplateName($name, $templateTree);
			$availableThemes = $this->resolvePreviewSupportThemes($templateName);

			$widgets[] = [
				'name' => $name,
				'description' => $description,
				'template_name' => $templateName,
				'themes' => $availableThemes,
			];
		}

		return $this->createComponentTree('widgetPreviewList', [
			'widgets' => $widgets,
			'allThemes' => $this->getPreviewThemeNames(),
			'templateScopeNote' => 'Theme support is calculated only from the resolved preview HTML template of each widget root node.',
		], strings: self::buildListStrings());
	}

	/**
	 * The generic Form widget preview should report support only from the main form widget template,
	 * not from nested input component templates rendered inside the preview.
	 *
	 * @param array<string, mixed> $tree
	 */
	private function resolvePreviewSupportTemplateName(string $widget_name, array $tree): string
	{
		if ($widget_name === WidgetList::FORM || (string)($tree['component'] ?? '') === 'form') {
			return 'sdui.form';
		}

		return HtmlComponentTemplateResolver::resolveTemplateName($tree);
	}

	/**
	 * Widget preview theme support should show only exact theme implementations for the resolved
	 * root template, and should hide the framework base SoAdmin fallback from this UI.
	 *
	 * @return string[]
	 */
	private function resolvePreviewSupportThemes(string $template_name): array
	{
		$themes = [];

		foreach ($this->getPreviewThemeNames() as $theme_name) {
			if (ThemedTemplateList::getThemedTemplatePath($template_name, $theme_name) !== null) {
				$themes[] = $theme_name;
			}
		}

		return $themes;
	}

	/**
	 * @return string[]
	 */
	private function getPreviewThemeNames(): array
	{
		return array_values(array_filter(
			Themes::getAllThemeNames(),
			static fn (string $theme_name): bool => $theme_name !== 'SoAdmin'
		));
	}

	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return true;
	}
}
