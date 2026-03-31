<?php

class Themes extends ThemeList
{
	public static function checkThemeDataExists(string $theme_type_name): bool
	{
		return in_array($theme_type_name, self::$_themeDataNames);
	}

	/**
	 * @return string[]
	 */
	public static function getAllThemeNames(): array
	{
		return array_filter(self::$_themeDataNames, fn ($name) => !str_starts_with($name, '_'));
	}

	/**
	 * Get theme name for layout with user-specific logic applied.
	 * This method can be extended for A/B testing, user preferences, etc.
	 *
	 * Resolution order:
	 * 1. URL parameter ?theme=ThemeName (if valid)
	 * 2. Layout class declares theme via getThemeName() (no override allowed)
	 * 3. User config override (themeoverride:baseTheme)
	 * 4. Layout→theme mapping from database
	 * 5. Config default (APP_DEFAULT_THEME_NAME)
	 */
	public static function getThemeNameForUser(?string $layout_name): string
	{
		// 1. Check URL parameter override first (dev/testing only — not in production)
		if (Kernel::getEnvironment() !== 'production') {
			$url_theme = Request::_GET('theme', null);

			if ($url_theme !== null && self::checkThemeDataExists($url_theme)) {
				return $url_theme;
			}

			// For AJAX/htmx requests: inherit ?theme= from the Referer page URL.
			// Must only apply to AJAX/htmx — regular navigations carry the previous
			// page's URL as HTTP_REFERER, which would leak the theme across pages.
			$isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
			$isHtmx = strtolower((string) ($_SERVER['HTTP_HX_REQUEST'] ?? '')) === 'true';
			$referer = $_SERVER['HTTP_REFERER'] ?? '';

			if ($referer !== '' && ($isAjax || $isHtmx)) {
				$referer_query = parse_url($referer, PHP_URL_QUERY) ?? '';
				parse_str($referer_query, $referer_params);
				$referer_theme = $referer_params['theme'] ?? null;

				if ($referer_theme !== null && self::checkThemeDataExists($referer_theme)) {
					return $referer_theme;
				}
			}
		}

		// 2. Check if layout class explicitly declares its theme
		// When a layout declares its theme, user overrides don't apply - the layout
		// knows which theme's CSS/JS libraries it uses and templates must match
		if ($layout_name !== null && Layout::checkLayoutExists($layout_name)) {
			$layout_class = Layout::getLayoutClassName($layout_name);
			$declared_theme = $layout_class::getThemeName();

			if ($declared_theme !== null && self::checkThemeDataExists($declared_theme)) {
				return $declared_theme;
			}
		}

		// 3. Get base theme from layout mapping in database
		$base_theme = self::getThemeNameForLayout($layout_name);

		// Use config default if no theme is configured for this layout
		if ($base_theme === '') {
			$base_theme = Config::APP_DEFAULT_THEME_NAME->value();
		}

		if ($base_theme === '') {
			return $base_theme;
		}

		// 4. Check user config override
		$user_id = User::getCurrentUserId();

		if ($user_id < 0) {
			return $base_theme;
		}

		$override_key = 'themeoverride:' . $base_theme;
		$override_theme = UserConfig::getConfig($override_key);

		if ($override_theme !== null && self::checkThemeDataExists($override_theme)) {
			return $override_theme;
		}

		return $base_theme;
	}

	public static function getThemeListForSelect(): array
	{
		$return = [];

		foreach (self::$_themeDataNames as $value) {
			$theme = Themes::factory($value);
			$return[] = [
				'inputtype' => 'option',
				'value' => $value,
				'label' => $theme->getName(),
			];
		}

		return $return;
	}

	public static function initExtraWebpageFormInputs(FormTypeWebpagePage $form): void
	{
		$item_id = $form->getItemId();

		if ($item_id === null) {
			return;
		}

		$resource = ResourceTypeFactory::Factory($item_id);

		if (!is_object($resource)) {
			return;
		}

		$layout_name = $resource->getData('layout');
		$theme_name = Themes::getThemeNameForLayout($layout_name);

		Themes::factory($theme_name)->extraWebpageFormInputs($form);
	}

	public static function extraAttributes(FormTypeWebpagePage $form): array
	{
		$item_id = $form->getItemId();

		if ($item_id === null) {
			return [];
		}

		$resource = ResourceTypeFactory::Factory($item_id);

		if (!is_object($resource)) {
			return [];
		}

		$layout_name = $resource->getData('layout');
		$theme_name = Themes::getThemeNameForLayout($layout_name);

		return Themes::factory($theme_name)->extraAttributes($form);
	}

	/**
	 * Resolve theme name from referer URL.
	 *
	 * Parses the referer URL, finds the corresponding page, gets its layout,
	 * and resolves that layout to a theme name. Useful for AJAX requests
	 * that need to render themed templates without a WebpageComposer.
	 *
	 * Checks for explicit 'referer' GET parameter first (allows browser debugging),
	 * then falls back to HTTP_REFERER header.
	 *
	 * Aborts if theme cannot be resolved - the referer should always have a layout.
	 *
	 * @return string Theme name
	 */
	public static function getThemeNameFromReferer(): string
	{
		// First check for explicit referer GET parameter (allows browser debugging)
		$referer = Request::_GET('referer', null);

		// Fall back to HTTP header
		if ($referer === null) {
			$referer = $_SERVER['HTTP_REFERER'] ?? null;
		}

		if ($referer === null) {
			Kernel::abort('getThemeNameFromReferer: No referer parameter or HTTP_REFERER header');
		}

		// Parse the URL to get the path
		$parsed = parse_url($referer);
		$path = $parsed['path'] ?? '/';

		// Split path into folder and resource name
		// e.g., /admin/resources/index.html → folder=/admin/resources/, resource=index.html
		// e.g., /admin/resources/ → folder=/admin/resources/, resource=index.html
		$basename = basename($path);

		if (str_contains($basename, '.')) {
			// Path ends with a file (e.g., index.html)
			$folder = dirname($path);
			$resource_name = $basename;
		} else {
			// Path ends with a directory
			$folder = $path;
			$resource_name = 'index.html';
		}

		// Normalize folder: ensure it starts and ends with /
		$folder = '/' . trim($folder, '/');

		if ($folder !== '/') {
			$folder .= '/';
		}

		// Use domain context from config
		$domain_context = Config::APP_DOMAIN_CONTEXT->value();

		// Get page data from ResourceTreeHandler
		$page_data = ResourceTreeHandler::getResourceTreeEntryData($folder, $resource_name, $domain_context);

		if ($page_data === null) {
			Kernel::abort("getThemeNameFromReferer: Page not found for referer path: {$folder}{$resource_name}");
		}

		$node_id = (int) $page_data['node_id'];

		// Create ResourceTypeWebpage to get the layout
		$referer_page = new ResourceTypeWebpage($node_id, $page_data);
		$layout_name = $referer_page->getData('layout');

		if ($layout_name === null || $layout_name === '') {
			Kernel::abort("getThemeNameFromReferer: No layout for page node_id={$node_id}");
		}

		// Resolve layout to theme (with user overrides)
		return self::getThemeNameForUser($layout_name);
	}
}
