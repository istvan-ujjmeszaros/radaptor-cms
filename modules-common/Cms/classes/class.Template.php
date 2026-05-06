<?php

declare(strict_types=1);

class Template extends TemplateDebug
{
	public string $style;

	public string $content;

	public bool $useCustomizableWrapper = true;
	public string $class = '';
	private array $_dynamic_properties = [];

	public const string MIME_HTML = 'text/html; charset=utf-8';
	public const string MIME_JSON = 'application/json; charset=utf-8';
	public const string MIME_XML = 'text/xml; charset=utf-8';

	/** @var array<string, string|null> */
	private static array $_dynamicTemplatePathCache = [];

	protected string $_templatePath = '';
	protected ?string $_content = null;
	protected string $_mime = self::MIME_HTML;

	/** @var array<string, string> */
	protected array $_contentHtml = [];

	/** @var array<string, mixed> */
	protected array $_render_context = [];

	public AbstractForm $form;

	/** @var array<string, mixed> Can be used to store properties for the views */
	public array $props = [];

	/** @var array<string, string> Resolved human-facing strings for the active template */
	public array $strings = [];

	public function __construct(
		protected string                $_template_name,
		protected ?iHtmlTemplateRuntime $_renderer = null,
		protected ?WidgetConnection     $_widget_connection = null,
	) {
		$this->_templatePath = $this->getTemplatePath($_template_name);

		if (empty($this->_templatePath)) {
			SystemMessages::_error("Unregistered template: <i>$_template_name</i>");
			$_template_name = '_missing';
			$this->_templatePath = self::getTemplatePath($_template_name);
		}

		$this->_level = self::$_levels++ - 1;

		if ($this->_level < 0) {
			$this->_level *= -1;
			$this->_level += 1;
		}
	}

	public function setMime(string $mime): void
	{
		$this->_mime = $mime;
	}

	public function __clone(): void
	{
		$this->_content = null;
	}

	public static function checkTemplateIsRegistered(string $templateName): bool
	{
		return static::lookupHasTemplate($templateName);
	}

	/**
	 * @return class-string<iTemplateRenderer>
	 */
	protected static function lookupTemplateRenderer(string $templateName): string
	{
		if (TemplateList::hasTemplate($templateName)) {
			return TemplateList::getRendererForTemplate($templateName);
		}

		$dynamic_path = self::findDynamicTemplatePath($templateName);

		if ($dynamic_path === null) {
			return TemplateList::getRendererForTemplate($templateName);
		}

		return match (true) {
			str_ends_with($dynamic_path, '.blade.php') => TemplateRendererBlade::class,
			str_ends_with($dynamic_path, '.twig') => TemplateRendererTwig::class,
			default => TemplateRendererPhp::class,
		};
	}

	protected static function lookupHasTemplate(string $templateName): bool
	{
		return TemplateList::hasTemplate($templateName) || self::findDynamicTemplatePath($templateName) !== null;
	}

	public function getRenderer(): ?iHtmlTemplateRuntime
	{
		return $this->_renderer;
	}

	public function getWidgetConnection(): ?WidgetConnection
	{
		return $this->_widget_connection;
	}

	public function getTemplatePath(string $templateName): string
	{
		$theme_name = $this->_renderer?->getTheme()?->getThemeName();

		if ($theme_name !== null) {
			$themedPath = ThemedTemplateList::getThemedTemplatePath($templateName, $theme_name);

			if ($themedPath !== null) {
				return $themedPath;
			}
		}

		// Direct template lookups without a renderer keep the base registry path.
		$default_theme_name = $theme_name !== null ? Config::APP_DEFAULT_THEME_NAME->value() : '';

		if ($default_theme_name !== '' && $default_theme_name !== $theme_name) {
			$defaultThemedPath = ThemedTemplateList::getThemedTemplatePath($templateName, $default_theme_name);

			if ($defaultThemedPath !== null) {
				return $defaultThemedPath;
			}
		}

		// Fall back to base template (from __templates__.php)
		if (TemplateList::hasTemplate($templateName)) {
			return TemplateList::getPathForTemplate($templateName);
		}

		return self::findDynamicTemplatePath($templateName) ?? '';
	}

	private static function findDynamicTemplatePath(string $templateName): ?string
	{
		if (array_key_exists($templateName, self::$_dynamicTemplatePathCache)) {
			return self::$_dynamicTemplatePathCache[$templateName];
		}

		$candidates = [
			'template.' . $templateName . '.blade.php',
			'template.' . $templateName . '.twig',
			'template.' . $templateName . '.php',
		];

		foreach (PackagePathHelper::getScannableRoots() as $root) {
			if (!is_dir($root)) {
				continue;
			}

			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
			);

			foreach ($iterator as $file) {
				if (!$file instanceof SplFileInfo || !$file->isFile()) {
					continue;
				}

				if (PackagePathHelper::shouldSkipPath($file->getPathname())) {
					continue;
				}

				$basename = $file->getBasename();

				if (!in_array($basename, $candidates, true)) {
					continue;
				}

				$stored_path = PackagePathHelper::toStoragePath($file->getPathname());
				self::$_dynamicTemplatePathCache[$templateName] = $stored_path;

				return $stored_path;
			}
		}

		self::$_dynamicTemplatePathCache[$templateName] = null;

		return null;
	}

	// ── Page-metadata delegation to renderer ──────────────────────────────────

	public function getPageId(): ?int
	{
		return $this->_renderer?->getPageId();
	}

	public function getTitle(): string
	{
		return $this->_renderer?->getTitle() ?? '';
	}

	public function getDescription(): string
	{
		return $this->_renderer?->getDescription() ?? '';
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function getPagedata(string $key)
	{
		return $this->_renderer?->getPagedata($key);
	}

	public function getTheme(): ?AbstractThemeData
	{
		return $this->_renderer?->getTheme();
	}

	public function isEditable(): bool
	{
		return $this->_renderer?->isEditable() ?? false;
	}

	// ── Asset proxy methods (delegate to renderer) ────────────────────────────

	public function registerLibrary(string $name, bool $force_top = false): void
	{
		$this->_renderer?->registerLibrary($name, $force_top);
	}

	public function registerCss(string $name): void
	{
		$this->_renderer?->registerCss($name);
	}

	public function registerJs(string $name, bool $top = false): void
	{
		$this->_renderer?->registerJs($name, $top);
	}

	public function registerModule(string $name): void
	{
		$this->_renderer?->registerModule($name);
	}

	public function registerInnerHtml(string $inner_html): void
	{
		$this->_renderer?->registerInnerHtml($inner_html);
	}

	public function registerClosingHtml(string $closing_html): void
	{
		$this->_renderer?->registerClosingHtml($closing_html);
	}

	/**
	 * @param string|array<string> $keys
	 */
	public function registerI18n(string|array $keys): void
	{
		$this->_renderer?->registerI18n($keys);
	}

	// ─────────────────────────────────────────────────────────────────────────

	public function compose(): void
	{
		if (!is_null($this->_content)) {
			return;
		}

		// Get the renderer for this template
		$rendererClass = static::lookupTemplateRenderer($this->_template_name);

		// Render the template using the appropriate renderer
		$renderedContent = $rendererClass::render(
			PackagePathHelper::resolveStoragePath($this->_templatePath),
			$this->props,
			$this
		);

		$this->_content = $this->addDebugInfo($this, $renderedContent, $this->_widget_connection?->getWidgetName() ?? '');
	}

	public function fetch(): string
	{
		$this->compose();

		return $this->_content;
	}

	public function getTemplateName(): string
	{
		return $this->_template_name;
	}

	/**
	 * @param array<string, string> $content_html
	 */
	public function setContents(array $content_html): void
	{
		$this->_contentHtml = $content_html;
	}

	/**
	 * Return the already-rendered HTML for a named template content region.
	 */
	public function fetchContent(string $name): string
	{
		return $this->_contentHtml[$name] ?? '';
	}

	/**
	 * @param array<string, mixed> $render_context
	 */
	public function setRenderContext(array $render_context): void
	{
		$this->_render_context = $render_context;
	}

	public function isMock(): bool
	{
		return (bool)($this->_render_context['is_mock'] ?? false);
	}

	public function render(): void
	{
		$content = $this->fetch();

		if ($this->_mime != '') {
			WebpageView::header('Content-type: ' . $this->_mime);
		}

		echo $content;
	}

	public function __isset(string $name): bool
	{
		return isset($this->props[$name]);
	}

	public function __set(string $name, mixed $value): never
	{
		Kernel::abort("Setting non-defined properties directly on the Template object is not allowed. Use the props array instead, like: \$this->props['$name'] = \$value;");
	}

	public function __get(string $name): void
	{
		if (array_key_exists($name, $this->props)) {
			Kernel::abort("The custom property '$name' was defined, but it is not allowed to access it with a getter. Use the props array to access the property, like: \$value = \$this->props['$name'];");
		}

		Kernel::ob_end_clean_all();
		echo "<span style=\"background-color:#FFd0d0;\">";
		echo "Template, missing variable: <i>{$name}</i><br>\n";
		$debug_data = debug_backtrace();
		echo '<i>' . basename($debug_data[0]['file']) . "</i> (line {$debug_data[0]['line']})" . "<br>\n";

		if ($debug_data[3]['class'] == 'Template') {
			echo '<i>' . basename($debug_data[3]['file']) . "</i> (line {$debug_data[3]['line']})" . "<br>\n";
		} else {
			echo '<i>' . basename($debug_data[4]['file']) . "</i> (line {$debug_data[4]['line']})" . "<br>\n";
		}

		echo '<br><br><br>';

		foreach ($debug_data as $debug) {
			echo '<i>' . basename($debug['file']) . "</i> (line {$debug['line']})" . "<br>\n";
		}

		Kernel::abort("Getting non-defined properties is not allowed. The key '$name' does not exist in props. Use the props array to define it. Example: \$value = \$this->props['$name'];");
	}

	public function __unset(string $name): void
	{
		if ('_' != mb_substr($name, 0, 1) && isset($this->_dynamic_properties[$name])) {
			unset($this->_dynamic_properties[$name]);
		}
	}
}
