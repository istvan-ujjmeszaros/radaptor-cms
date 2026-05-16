<?php

declare(strict_types=1);

/**
 * @phpstan-type RenderTreeNode array{
 *     type?: string,
 *     component?: string,
 *     props?: array<string, mixed>,
 *     contents?: array<string, list<array<string, mixed>>>,
 *     strings?: array<string, mixed>,
 *     meta?: array<string, mixed>
 * }
 * @phpstan-type RenderContext array{
 *     is_mock?: bool,
 *     theme_name?: string,
 *     widget_connection?: WidgetConnection|null
 * }
 * @phpstan-type WidgetConnectionTreeMetadata array{
 *     connection_id: int,
 *     widget_name: string,
 *     slot_name: string,
 *     seq: int|null,
 *     is_first: bool,
 *     is_last: bool,
 *     previous_connection_id: int|null,
 *     next_connection_id: int|null,
 *     extraparams: array<string, mixed>
 * }
 */
class HtmlTreeRenderer implements iPageTreeRenderer, iHtmlTemplateRuntime
{
	private const string CONTEXT_WIDGET_CONNECTION = 'widget_connection';
	private const string CONTEXT_DEBUG_OWNER_WIDGET_CONNECTION_ID = '__radaptor_debug_owner_widget_connection_id';

	// ── Asset state ──────────────────────────────────────────────────────────

	/** @var array<string, list<string>> */
	private array $_registeredCss = [];

	/** @var array<string, bool> key→is_top */
	private array $_registeredJs = [];

	/** @var array<string, true> */
	private array $_registeredModules = [];

	/** @var list<string> */
	private array $_registeredInnerHtml = [];

	/** @var list<string> */
	private array $_registeredClosingHtml = [];

	/** @var array<string, true> */
	private array $_registeredI18nKeys = [];

	/** @var list<string> */
	private array $_alreadyRegistered = [];

	/** @var array<string, list<array<mixed>>> */
	private array $_debugCallers = [];

	private ?HtmlRenderDebugCollector $_debugCollector = null;

	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * @param array<string, mixed> $pagedata
	 * @param class-string<Template> $template_class
	 */
	public function __construct(
		private ?AbstractThemeData $theme = null,
		private string $lang_id = '',
		private ?int $page_id = null,
		private string $title = '',
		private string $description = '',
		private array $pagedata = [],
		private bool $is_editable = false,
		private string $template_class = Template::class,
	) {
	}

	// ── iHtmlTemplateRuntime: page-metadata getters ───────────────────────────

	public function getTheme(): ?AbstractThemeData
	{
		return $this->theme;
	}

	public function getLangId(): string
	{
		return $this->lang_id;
	}

	public function getPageId(): ?int
	{
		return $this->page_id;
	}

	public function getTitle(): string
	{
		return $this->title;
	}

	public function getDescription(): string
	{
		return $this->description;
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function getPagedata(string $key)
	{
		return $this->pagedata[$key] ?? null;
	}

	public function isEditable(): bool
	{
		return $this->is_editable;
	}

	public function recordTemplateDebug(string $templateName, string $templatePath, float $durationMs): void
	{
		if (!$this->isRadaptorDebugEnabled()) {
			return;
		}

		$this->getDebugCollector()->recordTemplateDebug($templateName, $templatePath, $durationMs);
	}

	// ── iHtmlAssetRegistry: registration ─────────────────────────────────────

	/**
	 * Register library dependencies (CSS, JS, ES modules).
	 *
	 * Multiple files can be specified, comma-separated.
	 * Supports type prefixes: js:https://..., css:https://..., module:https://...
	 * Use ^ prefix for head loading (JS only): ^/path/to/script.js
	 * Use * prefix to comment out: *path/to/script.js
	 * Conditional CSS: valami.css#if IE7
	 */
	public function registerLibrary(string $resource_name, bool $force_top = false): void
	{
		$exploded_files = explode(',', $resource_name);

		foreach ($exploded_files as $file) {
			$file = trim($file, " \n\r\t");

			$type_prefix = null;

			if (preg_match('/^(js|css|module):(.+)$/i', $file, $matches)) {
				$type_prefix = strtolower($matches[1]);
				$file = $matches[2];
			}

			$file_exploded = explode('?', $file);
			$file_trimmed = $file_exploded[0];

			if (mb_strpos($file_trimmed, '*') === 0) {
				continue;
			}

			if (Config::DEV_APP_DEBUG_INFO->value() && Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)) {
				$backtrace = debug_backtrace();
				$index = 0;

				if (isset($backtrace[$index]['file']) && isset($backtrace[$index]['line'])) {
					while (basename($backtrace[$index]['file']) == 'class.HtmlTreeRenderer.php') {
						++$index;
					}

					$this->_debugCallers[$file][] = $backtrace[$index];
				}
			}

			if (in_array($file, $this->_alreadyRegistered)) {
				continue;
			}

			if ($file !== '') {
				$this->_alreadyRegistered[] = $file;
			}

			$path_parts = pathinfo($file_trimmed);

			if (!isset($path_parts['extension'])) {
				if ($path_parts['filename'] == '') {
					$path_parts['extension'] = 'undefined';
				} else {
					$path_parts['extension'] = 'const';
				}
			}

			if ($type_prefix !== null) {
				$extension = $type_prefix;
			} else {
				$exploded_extension = explode(':', $path_parts['extension']);
				$extension = $exploded_extension[0];
				$exploded_extension = explode('#', $extension);
				$extension = $exploded_extension[0];
			}

			$file_path_for_prefix_check = ltrim($file, '^');

			if (mb_substr($file_path_for_prefix_check, 0, 1) != '/' && mb_strpos($file_path_for_prefix_check, ":") === false) {
				$full_path = Config::PATH_CDN->value() . $file;
			} else {
				$full_path = $file;
			}

			switch ($extension) {
				case 'undefined':
					break;

				case 'css':
				case 'less':
					$this->registerCss($full_path);

					break;

				case 'js':
					$this->registerJs($full_path, $force_top);

					break;

				case 'module':
					$this->registerModule($full_path);

					break;

				case 'const':
					$libraryClass = $this->theme?->getLibrariesClassName();

					if (!AutoloaderFromGeneratedMap::autoloaderClassExists((string)$libraryClass)) {
						$libraryClass = "LibrariesCommon";
					}

					if (defined($libraryClass . '::' . $path_parts['filename'])) {
						$this->registerLibrary(constant($libraryClass . '::' . $path_parts['filename']), $force_top);
					} elseif ($libraryClass !== LibrariesCommon::class && defined(LibrariesCommon::class . '::' . $path_parts['filename'])) {
						$this->registerLibrary(constant(LibrariesCommon::class . '::' . $path_parts['filename']), $force_top);
					} else {
						SystemMessages::_warning(t('cms.library.unknown') . ': ' . $path_parts['filename']);

						$_missing = new Template('_missing_library', $this);
						$_missing->props['library_name'] = $path_parts['filename'];
						$_missing->props['folder'] = Request::_GET('folder');
						$_missing->props['resource'] = Request::_GET('resource');
						$_missing->strings = self::buildMissingLibraryStrings();
						$_missing->render();
					}

					break;

				default:
					Kernel::abort("registerLibrary unknown file extension for file: <b>{$file}</b> <i>({$full_path})</i>");
			}
		}
	}

	public function registerCss(string $resource_name): void
	{
		$exploded_resource_name = explode(',', $resource_name);

		foreach ($exploded_resource_name as $name) {
			$media = 'all';
			$exploded_name = explode('#', $name);

			if (isset($exploded_name[1])) {
				$name = $exploded_name[0];
				$media = $exploded_name[1];
			}

			if (!isset($this->_registeredCss[$name]) || (!in_array($media, $this->_registeredCss[$name]))) {
				$this->_registeredCss[$name][] = $media;
			}
		}
	}

	public function registerJs(string $resource_name, bool $top = false): void
	{
		$exploded_resource_name = explode(',', $resource_name);

		foreach ($exploded_resource_name as $name) {
			$clean_name = str_replace("^", "", $name);
			$top = $top || ($clean_name !== $name);

			if (array_key_exists($clean_name, $this->_registeredJs) && !$top) {
				continue;
			}

			$this->_registeredJs[$clean_name] = $top;
		}
	}

	public function registerModule(string $resource_name): void
	{
		$exploded_resource_name = explode(',', $resource_name);

		foreach ($exploded_resource_name as $name) {
			$clean_name = str_replace("^", "", $name);

			if (array_key_exists($clean_name, $this->_registeredModules)) {
				continue;
			}

			$this->_registeredModules[$clean_name] = true;
		}
	}

	public function registerInnerHtml(string $inner_html): void
	{
		if (!in_array($inner_html, $this->_registeredInnerHtml)) {
			$this->_registeredInnerHtml[] = $inner_html;
		}
	}

	public function registerClosingHtml(string $closing_html): void
	{
		if (!in_array($closing_html, $this->_registeredClosingHtml)) {
			$this->_registeredClosingHtml[] = $closing_html;
		}
	}

	/**
	 * @param string|array<string> $keys
	 */
	public function registerI18n(string|array $keys): void
	{
		if (is_string($keys)) {
			$this->_registeredI18nKeys[$keys] = true;
		} else {
			foreach ($keys as $key) {
				$this->_registeredI18nKeys[$key] = true;
			}
		}
	}

	// ── iHtmlAssetRegistry: emission ─────────────────────────────────────────

	public function getCss(): string
	{
		$output = "";

		if ($this->is_editable) {
			$this->registerLibrary('__COMMON_ADMIN');
		}

		$render_mode = $this->pagedata['render_mode'] ?? '';

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

			$output .= $out;
		}

		return $output;
	}

	public function getJs(): string
	{
		$output = "";

		foreach ($this->_registeredJs as $filename => $top) {
			if ($top) {
				continue;
			}

			$url = $this->_appendCacheBuster($filename);
			$output .= "<script type=\"text/javascript\" src=\"$url\"></script>\n";
		}

		return $output;
	}

	public function getJsTop(): string
	{
		$output = "";

		if ($this->theme !== null) {
			$theme_slug = $this->theme::getSlug();
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
						'version' => $this->_getStimulusControllersVersion($stimulus_controllers_dir),
						'loaded' => new \stdClass(),
					],
				],
			];
			$json = json_encode($radaptor_config, JSON_UNESCAPED_SLASHES);
			$output .= "<script>window.__RADAPTOR__ = {$json};</script>\n";
		}

		foreach ($this->_registeredJs as $filename => $top) {
			if ($top) {
				$url = $this->_appendCacheBuster($filename);
				$output .= "<script type=\"text/javascript\" src=\"$url\"></script>\n";
			}
		}

		if (!empty($this->_registeredModules) && Config::DEV_APP_DEBUG_INFO->value()) {
			$importMap = $this->_buildControllerImportMap();

			if (!empty($importMap)) {
				$output .= "<script type=\"importmap\">" . json_encode(['imports' => $importMap], JSON_UNESCAPED_SLASHES) . "</script>\n";
			}
		}

		foreach ($this->_registeredModules as $filename => $_unused) {
			$url = $this->_appendCacheBuster($filename);
			$output .= "<script type=\"module\" src=\"$url\"></script>\n";
		}

		return $output;
	}

	private function _getStimulusControllersVersion(string $stimulus_controllers_dir): string
	{
		$controller_files = glob($stimulus_controllers_dir . '/*.js');

		if ($controller_files === false || $controller_files === []) {
			return '0';
		}

		$timestamps = array_map(static fn (string $path): int => (int) (filemtime($path) ?: 0), $controller_files);

		return (string) max($timestamps ?: [0]);
	}

	public function getJsBottom(): string
	{
		$output = "";

		foreach ($this->_registeredJs as $filename => $top) {
			if (!$top) {
				$url = $this->_appendCacheBuster($filename);
				$output .= "<script type=\"text/javascript\" src=\"$url\"></script>\n";
			}
		}

		return $output;
	}

	public function getLibraryDebugInfo(): string
	{
		if (!Config::DEV_APP_DEBUG_INFO->value() || !Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)) {
			return "";
		}

		static $already_listed = [];
		$output = "<!--\n";

		foreach ($this->_debugCallers as $library => $call_list) {
			$output .= "{$library}\n";

			foreach ($call_list as $call) {
				$out = basename((string) $call['file']) . "::{$call['line']}";

				if (isset($already_listed[$out])) {
					continue;
				}

				$already_listed[$out] = 1;
				$output .= "    {$out}\n";
			}
			$already_listed = [];
		}
		$output .= "-->\n";

		return $output;
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
		$i18n = $this->_getI18nPayload();

		$output = '';

		if (!empty($i18n)) {
			$output .= '<script>window.__i18n=' . json_encode($i18n, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ";</script>\n";
		}

		if (count($this->_registeredClosingHtml) > 0) {
			$output .= implode("\n", $this->_registeredClosingHtml) . "\n";
		}

		return $output;
	}

	// ── iPageTreeRenderer: render ─────────────────────────────────────────────

	/**
	 * @param RenderTreeNode $node
	 * @param array<string, mixed> $render_context
	 */
	public function render(array $node, array $render_context = []): string
	{
		$node = SduiNode::normalize($node);
		$props = $node['props'];
		$meta = $node['meta'] ?? [];
		$node_render_context = $render_context;
		$debug_enabled = $this->isRadaptorDebugEnabled();
		$debug_node_id = null;

		if (isset($meta['render_flags']) && is_array($meta['render_flags'])) {
			$node_render_context = array_replace($node_render_context, $meta['render_flags']);
		}

		if (array_key_exists('widget_connection', $meta)) {
			$node_render_context[self::CONTEXT_WIDGET_CONNECTION] = $this->hydrateWidgetConnection($meta['widget_connection']);
		}

		if ($debug_enabled) {
			$owner_widget_connection_id = $this->resolveDebugOwnerWidgetConnectionId($meta, $render_context);
			$node_render_context[self::CONTEXT_DEBUG_OWNER_WIDGET_CONNECTION_ID] = $owner_widget_connection_id;
			$debug_node_id = $this->getDebugCollector()->pushFrame($node, $owner_widget_connection_id);
		}

		$result = '';

		try {
			$result = $this->renderNode($node, $props, $meta, $node_render_context, $debug_node_id);

			return $result;
		} finally {
			if ($debug_enabled && $debug_node_id !== null) {
				$this->getDebugCollector()->popFrame($debug_node_id);
			}
		}
	}

	/**
	 * @param RenderTreeNode $node
	 * @param array<string, mixed> $props
	 * @param array<string, mixed> $meta
	 * @param array<string, mixed> $node_render_context
	 */
	private function renderNode(array $node, array $props, array $meta, array $node_render_context, ?string $debug_node_id): string
	{
		$content_html = [];

		foreach ($node['contents'] as $content_name => $items) {
			$content_html[$content_name] = '';

			foreach ($items as $item) {
				$content_html[$content_name] .= $this->render($item, $node_render_context);
			}
		}

		if ($node['component'] === '_contentContainer') {
			return $this->wrapStableContainer($content_html['content'] ?? '', $meta, $debug_node_id);
		}

		$template_name = HtmlComponentTemplateResolver::resolveTemplateName($node);

		$template_class = $this->template_class;
		$template = new $template_class(
			$template_name,
			$this,
			$node_render_context[self::CONTEXT_WIDGET_CONNECTION] ?? null,
		);
		$template->strings = $node['strings'];
		$template->props = $props;
		$template->setContents($content_html);
		$template->setRenderContext($node_render_context);

		$html = $template->fetch();

		if (($content_html['page_chrome'] ?? '') !== '' && !$template->hasFetchedContent('page_chrome')) {
			$html = $this->appendPageChrome($html, $content_html['page_chrome']);
		}

		return $this->wrapStableContainer($html, $meta, $debug_node_id);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getBootstrap(): array
	{
		$collectorBootstrap = $this->_debugCollector?->toBootstrap() ?? [
			'roots' => [],
			'nodes' => [],
		];

		return [
			'version' => 1,
			'sessionId' => $this->debugSessionId(),
			'requestId' => $this->debugRequestId(),
			'renderer' => 'html',
			'features' => $this->debugFeatures(),
			'roots' => $collectorBootstrap['roots'],
			'nodes' => $collectorBootstrap['nodes'],
			'endpoints' => new stdClass(),
		];
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	private function appendPageChrome(string $html, string $page_chrome): string
	{
		$body_close_position = strripos($html, '</body>');

		if ($body_close_position === false) {
			return $html . $page_chrome;
		}

		return substr($html, 0, $body_close_position)
			. $page_chrome
			. substr($html, $body_close_position);
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	private function wrapStableContainer(string $html, array $meta, ?string $debug_node_id = null): string
	{
		$container_id = trim((string)($meta['stable_container_id'] ?? ''));

		if ($container_id === '') {
			if ($this->isRadaptorDebugEnabled() && $debug_node_id !== null) {
				return $this->stampDebugRootElements($html, $meta, $debug_node_id);
			}

			return $html;
		}

		$attributes = [
			'id' => $container_id,
		];

		if (!empty($meta['hx_swap_oob'])) {
			$attributes['hx-swap-oob'] = (string)($meta['hx_swap_oob'] === true ? 'outerHTML' : $meta['hx_swap_oob']);
		}

		if ($this->isRadaptorDebugEnabled() && $debug_node_id !== null) {
			$attributes += $this->getDebugCollector()->stableContainerAttributes($debug_node_id, $container_id, $meta);
		}

		$attribute_html = '';

		foreach ($attributes as $name => $value) {
			$attribute_html .= ' ' . e((string)$name) . '="' . e((string)$value) . '"';
		}

		return '<div' . $attribute_html . '>' . $html . '</div>';
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	private function stampDebugRootElements(string $html, array $meta, string $debug_node_id): string
	{
		$attributes = $this->getDebugCollector()->rootElementAttributes($debug_node_id, $meta);
		$result = HtmlDebugDomStamper::stampRootElements($html, $attributes);

		if ($result['stampedElementCount'] > 0) {
			$this->getDebugCollector()->markRootElementsStamped($debug_node_id, $result['stampedElementCount']);
		}

		return $result['html'];
	}

	/**
	 * @return array<string, string>
	 */
	private function _getI18nPayload(): array
	{
		$result = [];

		foreach (array_keys($this->_registeredI18nKeys) as $key) {
			$result[$key] = I18nRuntime::t($key);
		}

		return $result;
	}

	private function _appendCacheBuster(string $url): string
	{
		if (!Config::DEV_APP_DEBUG_INFO->value()) {
			return $url;
		}

		if (!str_starts_with($url, '/')) {
			return $url;
		}

		$file_path = DEPLOY_ROOT . 'public/www' . $url;

		if (!file_exists($file_path)) {
			return $url;
		}

		$mtime = filemtime($file_path);
		$separator = str_contains($url, '?') ? '&' : '?';

		return $url . $separator . 'v=' . $mtime;
	}

	/**
	 * @return array<string, string>
	 */
	private function _buildControllerImportMap(): array
	{
		$theme_slug = $this->theme !== null ? $this->theme::getSlug() : 'radaptor-portal-admin';
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

	private function isRadaptorDebugEnabled(): bool
	{
		return class_exists(DebugSession::class) && DebugSession::isEnabled();
	}

	private function getDebugCollector(): HtmlRenderDebugCollector
	{
		$this->_debugCollector ??= new HtmlRenderDebugCollector();

		return $this->_debugCollector;
	}

	/**
	 * @param array<string, mixed> $meta
	 * @param array<string, mixed> $render_context
	 */
	private function resolveDebugOwnerWidgetConnectionId(array $meta, array $render_context): ?string
	{
		$connection_id = $this->readWidgetConnectionId($meta['widget_connection'] ?? null);

		if ($connection_id !== null) {
			return 'wc:' . $connection_id;
		}

		$owner = $render_context[self::CONTEXT_DEBUG_OWNER_WIDGET_CONNECTION_ID] ?? null;

		return is_string($owner) && $owner !== '' ? $owner : null;
	}

	private function readWidgetConnectionId(mixed $widget_connection): ?int
	{
		if (is_array($widget_connection) && isset($widget_connection['connection_id']) && is_numeric($widget_connection['connection_id'])) {
			return (int)$widget_connection['connection_id'];
		}

		if ($widget_connection instanceof WidgetConnection) {
			return $widget_connection->getConnectionId();
		}

		return null;
	}

	private function debugSessionId(): string
	{
		return class_exists(DebugSession::class) ? DebugSession::sessionId() : '';
	}

	private function debugRequestId(): string
	{
		return class_exists(DebugSession::class) ? DebugSession::requestId() : '';
	}

	/**
	 * @return list<string>
	 */
	private function debugFeatures(): array
	{
		if (!class_exists(DebugSession::class)) {
			return ['tree', 'dommap', 'timings'];
		}

		$features = DebugSession::features();
		$features = array_values(array_map('strval', is_array($features) ? $features : []));

		return $features !== [] ? $features : ['tree', 'dommap', 'timings'];
	}

	/**
	 * @return array<string, string>
	 */
	private static function buildMissingLibraryStrings(): array
	{
		return [
			'cms.library.unknown' => t('cms.library.unknown'),
			'cms.library.folder' => t('cms.library.folder'),
			'cms.library.resource' => t('cms.library.resource'),
			'common.not_set' => t('common.not_set'),
		];
	}

	/**
	 * @param WidgetConnection|WidgetConnectionTreeMetadata|null $widget_connection
	 */
	private function hydrateWidgetConnection(mixed $widget_connection): ?WidgetConnection
	{
		if ($widget_connection instanceof WidgetConnection || $widget_connection === null) {
			return $widget_connection;
		}

		return WidgetConnection::fromTreeMetadata($widget_connection);
	}
}
