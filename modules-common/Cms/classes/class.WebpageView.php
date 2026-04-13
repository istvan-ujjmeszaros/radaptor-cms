<?php

class WebpageView extends AbstractWebpageViewComposer implements Stringable
{
	public const string _RENDERMODE_HTML = 'html';
	public const string _RENDERMODE_XHTML = 'xhtml';
	public const string OUTPUT_CHANNEL_HTML = 'html';
	public const string OUTPUT_CHANNEL_SDUI_JSON = 'sdui_json';

	//const _HEADER_403 = 'HTTP/1.1 403.8';  /* h2o crashes from 403.8 */
	public const string _HEADER_403 = 'HTTP/1.1 403';

	public function setRenderMode(string $render_mode): void
	{
		$this->_resourceData['render_mode'] = $render_mode;
	}

	public static function header(string $header): void
	{
		if (!headers_sent()) {
			header($header);
		}
	}

	public function getLangId(): string
	{
		return $this->_resourceData['lang_id'];
	}

	public function getType(): string
	{
		return $this->_resourceData['node_type'];
	}

	public function getOutputChannel(): string
	{
		$output_channel = trim((string) Request::_GET('output_channel', ''));

		if ($output_channel !== '') {
			return match ($output_channel) {
				self::OUTPUT_CHANNEL_SDUI_JSON => self::OUTPUT_CHANNEL_SDUI_JSON,
				default => self::OUTPUT_CHANNEL_HTML,
			};
		}

		$server = RequestContextHolder::current()->SERVER;
		$accept = strtolower(trim((string) ($server['HTTP_ACCEPT'] ?? $server['http_accept'] ?? '')));
		$accepts_html = str_contains($accept, 'text/html') || str_contains($accept, 'application/xhtml+xml');
		$accepts_json = str_contains($accept, 'application/json') || str_contains($accept, 'text/json');

		if ($accepts_json && !$accepts_html) {
			return self::OUTPUT_CHANNEL_SDUI_JSON;
		}

		return self::OUTPUT_CHANNEL_HTML;
	}

	public function isHtmlOutputChannel(): bool
	{
		return $this->getOutputChannel() === self::OUTPUT_CHANNEL_HTML;
	}

	public function view(): void
	{
		$this->_compose();

		echo $this->_content;
	}

	public function download(): void
	{
		$this->view();
	}

	public function __toString(): string
	{
		$this->_compose();

		return $this->_content;
	}

	public function renderKeywords(): void
	{
		if (trim((string) $this->_resourceData['keywords']) == '') {
			return;
		}

		echo "\t";

		echo match ($this->_resourceData['render_mode']) {
			'xhtml' => '<meta name="keywords" content="' . $this->_resourceData['keywords'] . '" />' . "\n",
			default => '<meta name="keywords" content="' . $this->_resourceData['keywords'] . '" >' . "\n",
		};
	}

	public function getDescription(): string
	{
		if (trim((string) $this->_resourceData['description']) == '') {
			return '';
		}

		return match ($this->_resourceData['render_mode']) {
			'xhtml' => "\t" . '<meta name="description" content="' . $this->_resourceData['description'] . '"/>' . "\n",
			default => "\t" . '<meta name="description" content="' . $this->_resourceData['description'] . '">' . "\n",
		};
	}

	/**
	 * Get all registered library information.
	 *
	 * @return array{constants: string[], css: string[], js: string[], modules: string[]}
	 */
	public function getRegisteredLibraries(): array
	{
		return [
			'constants' => array_keys($this->_debugCallers),
			'css' => array_keys($this->_registeredCss),
			'js' => array_keys($this->_registeredJs),
			'modules' => array_keys($this->_registeredModules),
		];
	}

	public function getLibraryDebugInfo(): string
	{
		if (!Config::DEV_APP_DEBUG_INFO->value() || !Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)) {
			return "";
		}

		static $already_listed = [];
		$output = "<!--\n";  // Start capturing output into a string

		foreach ($this->_debugCallers as $library => $call_list) {
			$output .= "{$library}\n";  // Append library name to output

			foreach ($call_list as $call) {
				$out = basename((string) $call['file']) . "::{$call['line']}";

				if (isset($already_listed[$out])) {
					continue;  // Skip if already listed
				}

				$already_listed[$out] = 1;  // Mark as listed
				$output .= "    {$out}\n";  // Append call info to output
			}
			$already_listed = [];  // Reset already listed for next library
		}
		$output .= "-->\n";  // End of output

		return $output;
	}

	public function getCss(): string
	{
		$output = "";  // Initialize the output string

		if ($this->isEditable()) {
			$this->registerLibrary('__COMMON_ADMIN');
		}

		$render_mode = $this->_resourceData['render_mode'] ?? '';

		foreach ($this->_registeredCss as $filename => $all_media) {
			$rel = mb_strpos((string) $filename, ".less") === false ? "stylesheet" : "stylesheet/less";
			$media = implode(', ', $all_media);
			$pos = mb_strpos($media, 'if ');
			$url = $this->_appendCacheBuster($filename);

			if ($pos === false || $pos > 0) {
				$out = "<link href=\"{$url}\" rel=\"{$rel}\" type=\"text/css\" media=\"{$media}\">\n";

				if ($render_mode == 'xhtml') {
					$out = str_replace('>', ' />', $out);
				}
			} else {
				$out = "<!--[$media]>\n";
				$out .= "<link rel=\"{$rel}\" type=\"text/css\" href=\"{$url}\">\n";
				$out .= '<![endif]-->' . "\n";
			}

			$output .= $out;  // Append each generated link tag to the output string
		}

		return $output;  // Return the complete output string
	}

	public function getJs(): string
	{
		$output = "";

		foreach ($this->_registeredJs as $filename => $top) {
			$url = $this->_appendCacheBuster($filename);
			$output .= "<script type=\"text/javascript\" src=\"$url\"></script>\n";
		}

		return $output;
	}

	public function getJsTop(): string
	{
		$output = "";

		// Inject Radaptor configuration for JavaScript (auto-loading, theme info)
		if ($this->_theme !== null) {
			$theme_slug = $this->_theme::getSlug();
			$theme_assets_base = $this->_getThemeAssetsBase($theme_slug);
			$stimulus_assets_base = $this->_getStimulusAssetsBase($theme_slug);
			$stimulus_controllers_dir = DEPLOY_ROOT . 'public/www' . $stimulus_assets_base . '/controllers';

			$radaptor_config = [
				'current_theme' => [
					'slug' => $theme_slug,
					'assets' => [
						'base' => $theme_assets_base,
					],
				],
				'stimulus' => [
					'controllers' => [
						'base' => $stimulus_assets_base . '/controllers',
						'version' => (string) max(array_map('filemtime', glob($stimulus_controllers_dir . '/*.js') ?: [0])),
						'loaded' => new \stdClass(),  // Empty object, populated by JS
					],
				],
			];
			$json = json_encode($radaptor_config, JSON_UNESCAPED_SLASHES);
			$output .= "<script>window.__RADAPTOR__ = {$json};</script>\n";
		}

		// Regular scripts marked for top
		foreach ($this->_registeredJs as $filename => $top) {
			if ($top) {
				$url = $this->_appendCacheBuster($filename);
				$output .= "<script type=\"text/javascript\" src=\"$url\"></script>\n";
			}
		}

		// Import map for ES module cache busting (must precede module scripts)
		if (!empty($this->_registeredModules) && Config::DEV_APP_DEBUG_INFO->value()) {
			$importMap = $this->_buildControllerImportMap();

			if (!empty($importMap)) {
				$output .= "<script type=\"importmap\">" . json_encode(['imports' => $importMap], JSON_UNESCAPED_SLASHES) . "</script>\n";
			}
		}

		// ES modules (always in head - they're deferred by default)
		foreach ($this->_registeredModules as $filename => $_unused) {
			$url = $this->_appendCacheBuster($filename);
			$output .= "<script type=\"module\" src=\"$url\"></script>\n";
		}

		return $output;
	}

	public function getJsBottom(): string
	{
		$output = "";

		// Regular scripts for bottom
		foreach ($this->_registeredJs as $filename => $top) {
			if (!$top) {
				$url = $this->_appendCacheBuster($filename);
				$output .= "<script type=\"text/javascript\" src=\"$url\"></script>\n";
			}
		}

		// Note: ES modules are always rendered in <head> via getJsTop()
		// since they're deferred by default and don't block parsing

		return $output;
	}

	/**
	 * Append cache-busting query parameter to asset URLs in dev mode.
	 *
	 * Uses file modification time to ensure browsers load fresh assets after changes.
	 * Only active when DEV_APP_DEBUG_INFO is enabled.
	 */
	private function _appendCacheBuster(string $url): string
	{
		if (!Config::DEV_APP_DEBUG_INFO->value()) {
			return $url;
		}

		// Only bust local assets (starting with /)
		if (!str_starts_with($url, '/')) {
			return $url;
		}

		// Get the file path from the URL (DEPLOY_ROOT/public/www is web root)
		$file_path = DEPLOY_ROOT . 'public/www' . $url;

		if (!file_exists($file_path)) {
			return $url;
		}

		$mtime = filemtime($file_path);
		$separator = str_contains($url, '?') ? '&' : '?';

		return $url . $separator . 'v=' . $mtime;
	}

	/**
	 * Build an import map for Stimulus controller files.
	 *
	 * Maps each controller JS file's absolute URL to a cache-busted version,
	 * ensuring that static ES module imports (e.g. `import X from "./base.js"`)
	 * also get fresh versions after deployment.
	 *
	 * @return array<string, string>
	 */
	private function _buildControllerImportMap(): array
	{
		$theme_slug = $this->_theme !== null ? $this->_theme::getSlug() : 'radaptor-portal-admin';
		$stimulus_assets_base = $this->_getStimulusAssetsBase($theme_slug);
		$controllersDir = DEPLOY_ROOT . 'public/www' . $stimulus_assets_base . '/controllers';

		if (!is_dir($controllersDir)) {
			return [];
		}

		$map = [];

		foreach (glob($controllersDir . '/*.js') as $file) {
			$basename = basename($file);
			$mtime = filemtime($file);
			$path = $stimulus_assets_base . "/controllers/{$basename}";
			$map[$path] = "{$path}?v={$mtime}";
		}

		// Also map top-level JS helper modules so Stimulus controllers can import
		// them with cache busting.
		$jsHelpersDir = DEPLOY_ROOT . 'public/www' . $stimulus_assets_base . '/js';

		if (is_dir($jsHelpersDir)) {
			foreach (glob($jsHelpersDir . '/*.js') as $file) {
				$basename = basename($file);
				$path = $stimulus_assets_base . "/js/{$basename}";
				$map[$path] = "{$path}?v=" . filemtime($file);
			}
		}

		return $map;
	}

	private function _getThemeAssetsBase(string $theme_slug): string
	{
		return CmsThemeAssetHelper::getThemeAssetsBase($theme_slug);
	}

	private function _getStimulusAssetsBase(string $theme_slug): string
	{
		return CmsThemeAssetHelper::getStimulusAssetsBase($theme_slug);
	}

	public function renderPreloadingImages(): void
	{
		$count = count($this->_registeredPreloadingImage);

		if ($count == 0) {
			return;
		}

		$i = 0;

		echo "<script type=\"text/javascript\">\n";
		echo "$.preloadImages(";

		foreach ($this->_registeredPreloadingImage as $src) {
			++$i;
			echo '"' . $src . '"';

			if ($i !== $count) {
				echo ", ";
			}
		}

		echo ");\n";
		echo "</script>\n";
	}

	public function fetchInnerHtml(): string
	{
		if (count($this->_registeredInnerHtml) > 0) {
			return implode("\n", $this->_registeredInnerHtml) . "\n";
		}

		return '';
	}

	public function fetchClosingHtml(): string
	{
		$i18n = $this->getI18nPayload();

		if (!empty($i18n)) {
			echo '<script>window.__i18n=' . json_encode($i18n, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ";</script>\n";
		}

		if (count($this->_registeredClosingHtml) > 0) {
			echo implode("\n", $this->_registeredClosingHtml) . "\n";
		}

		return '';
	}

	public function renderRobots(): void
	{
		$out = '';

		if ($this->_resourceData['robots_index']) {
			$out .= 'INDEX,';
		} else {
			$out .= 'NOINDEX,';
		}

		if ($this->_resourceData['robots_follow']) {
			$out .= 'FOLLOW';
		} else {
			$out .= 'NOFOLLOW';
		}

		echo $out;
	}

	public function getTitle(): string
	{
		return $this->_resourceData['title'];
	}

	public function addToTitle(string $addition): void
	{
		$this->_addBeforeTitle($addition);
	}

	private function _addBeforeTitle(string $addition): void
	{
		if ($this->_resourceData['title'] == '') {
			$this->_resourceData['title'] = $addition;
		} else {
			$this->_resourceData['title'] = $addition . ' - ' . $this->_resourceData['title'];
		}
	}
}
