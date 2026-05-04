<?php

abstract class AbstractWebpageViewBase implements iView, iWebpageComposer
{
	protected array $_debugCallers = [];
	protected ?int $_id = null;
	protected array $_resourceData = [];

	protected ?WebpageData $_webpageData = null;
	protected string $_content = '';
	protected bool $_editMode = false;

	/** @var iLayoutComponent[] */
	protected array $_renderedLayoutComponents = [];
	protected array $_registeredInnerHtml = [];
	protected array $_registeredClosingHtml = [];

	/** @var array<string, true> */
	protected array $_registeredI18nKeys = [];
	protected array $_registeredPreloadingImage = [];
	protected array $_registeredJs = [];
	protected array $_registeredModules = [];
	protected array $_registeredCss = [];

	protected ?AbstractThemeData $_theme = null;

	protected iLayoutType $layoutType;
	protected ?string $_layoutTypeOverride = null;

	protected array $_alreadyRegistered = [];
	protected int $_widgetInsertCounter = 0;

	/** @var list<array{type_name:string, name:string, description:string}>|null */
	protected ?array $_visibleWidgets = null;

	/**
	 * @param array<string, mixed> $resourcedata
	 */
	public function __construct(array $resourcedata = [], ?bool $editmode = null)
	{
		if (empty($resourcedata)) {
			$theme_name = Themes::getThemeNameForUser(null);
			$this->_theme = ThemeBase::factory($theme_name);

			return;
		}

		if (isset($resourcedata['node_id'])) {
			$this->_id = $resourcedata['node_id'];
		} else {
			$this->_id = null;
		}

		$this->_resourceData = $resourcedata;

		$this->_webpageData = new WebpageData($this->_id);

		$this->_resourceData['render_mode'] ??= null;

		$this->layoutType = Layout::factory($this->_webpageData->layout_name);

		$theme_name = Themes::getThemeNameForUser($this->getLayoutTypeName());

		$this->_theme = ThemeBase::factory($theme_name);

		if (is_null($editmode) && Request::_CONFIG(CmsConfig::EDITMODE, CmsConfig::EDITMODE_DEFAULTVALUE) == 1 && ResourceAcl::canAccessResource($this->getPageId(), ResourceAcl::_ACL_EDIT)) {
			$this->_editMode = true;
		}
	}

	public function getLayoutTypeName(): ?string
	{
		return $this->_layoutTypeOverride ?? $this->_webpageData?->layout_name;
	}

	public function getLayoutType(): iLayoutType
	{
		return $this->layoutType;
	}

	/**
	 * Override the layout type for rendering.
	 *
	 * Can be called from a widget's fetch() method to change the layout
	 * that will be used for the final page rendering. This is useful for
	 * widget preview where we want a minimal layout regardless of the
	 * page's configured layout.
	 *
	 * Note: The new layout's elements should be a subset of the original
	 * layout's elements, since element content is already populated.
	 * Theme is NOT changed - it was already resolved via URL param or user config.
	 */
	public function overrideLayoutType(string $layoutTypeName): void
	{
		if (!Layout::checkLayoutExists($layoutTypeName)) {
			return;
		}

		$this->_layoutTypeOverride = $layoutTypeName;
		$this->layoutType = Layout::factory($layoutTypeName);
		$this->layoutType->initialize($this);
	}

	public function getTheme(): ?AbstractThemeData
	{
		return $this->_theme;
	}

	public function getPageId(): ?int
	{
		return $this->_id;
	}

	public function isEditable(): bool
	{
		return $this->_editMode;
	}

	public function setEditMode($editable): void
	{
		$this->_editMode = (bool)$editable;
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function getPagedata($key)
	{
		return $this->_resourceData[$key] ?? null;
	}

	/**
	 * Returns the raw description text (no HTML wrapping).
	 * Used by HtmlTreeRenderer to pass plain text; layout templates own the <meta> tag.
	 */
	public function getRawDescription(): string
	{
		return trim((string) ($this->_resourceData['description'] ?? ''));
	}

	/**
	 * Returns the full pagedata array for passing to HtmlTreeRenderer constructor.
	 *
	 * @return array<string, mixed>
	 */
	public function getAllPagedata(): array
	{
		return $this->_resourceData;
	}

	/**
	 * Register library dependencies (CSS, JS, ES modules).
	 *
	 * Multiple files can be specified, comma-separated.
	 * Supports type prefixes for explicit type specification:
	 *   - js:https://cdn.example.com/script.js - Regular JavaScript
	 *   - css:https://cdn.example.com/style.css - Stylesheet
	 *   - module:https://cdn.example.com/module.js - ES module (type="module")
	 *
	 * Use ^ prefix for head loading (JS only): ^/path/to/script.js
	 * Use * prefix to comment out: *path/to/script.js
	 * Conditional CSS: valami.css#if IE7
	 *
	 * Note: ES modules are always rendered in <head> since they're deferred by default.
	 */
	public function registerLibrary(string $resource_name, bool $force_top = false): void
	{
		$exploded_files = explode(',', $resource_name);

		foreach ($exploded_files as $file) {
			// megtisztítjuk a nevet a sortörésektől, tabulátoroktól és szóközöktől
			$file = trim($file, " \n\r\t");

			// Handle type prefix for URLs: "js:https://...", "css:https://...", "module:https://..."
			$type_prefix = null;

			if (preg_match('/^(js|css|module):(.+)$/i', $file, $matches)) {
				$type_prefix = strtolower($matches[1]);
				$file = $matches[2];
			}

			$file_exploded = explode('?', $file);
			$file_trimmed = $file_exploded[0];

			// skipping commented lines (they start with an asterix)
			if (mb_strpos($file_trimmed, '*') === 0) {
				continue;
			}

			if (Config::DEV_APP_DEBUG_INFO->value() && Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)) {
				$backtrace = debug_backtrace();
				//var_dump(debug_backtrace());
				$index = 0;

				if (isset($backtrace[$index]['file']) && isset($backtrace[$index]['line'])) {
					while (basename($backtrace[$index]['file']) == 'class.WebpageViewBase.php') {
						++$index;
					}
					/*                echo "index: $index ";
					  echo "file: " . basename($backtrace[$index]['file']);
					  echo " -- line: " . $backtrace[$index]['line'];
					  echo "<br>\n"; */
					$this->_debugCallers[$file][] = $backtrace[$index];
				}
			}

			// a körkörös hivatkozások elkerülése miatt kell
			// pl JQUERY meghívja a COMMON-t, COMMON meghívja a JQUERY-t, így
			// ide-oda hívnák egymást a végtelenségig
			if (in_array($file, $this->_alreadyRegistered)) {
				continue;
			}

			if ($file !== '') {
				$this->_alreadyRegistered[] = $file;
			}

			$path_parts = pathinfo($file_trimmed);

			// ha vessző van az utolsó fájlnév végén, akkor nincs definiálva az 'extension'
			if (!isset($path_parts['extension'])) {
				if ($path_parts['filename'] == '') {
					$path_parts['extension'] = 'undefined';
				} else {
					$path_parts['extension'] = 'const';
				}
			}

			// Use type prefix if provided, otherwise detect from extension
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
					// ha véletlenül ottmaradt egy pontosvessző a végén...
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
					$libraryClass = $this->getTheme()?->getLibrariesClassName();

					if (!AutoloaderFromGeneratedMap::autoloaderClassExists((string)$libraryClass)) {
						$libraryClass = "LibrariesCommon";
					}

					if (defined($libraryClass . '::' . $path_parts['filename'])) {
						$this->registerLibrary(constant($libraryClass . '::' . $path_parts['filename']), $force_top);
					} else {
						SystemMessages::_warning(t('cms.library.unknown') . ': ' . $path_parts['filename']);

						$_missing = new Template('_missing_library', null);
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

	/**
	 * Betöltendő CSS fájlokat regisztrál.
	 */
	public function registerCss(string $resource_name): void
	{
		$exploded_resource_name = explode(',', $resource_name);

		foreach ($exploded_resource_name as $name) {
			$media = 'all';

			$exploded_name = explode('#', $name);

			// ha megadunk médiát, akkor az a kettőskereszt után lesz
			if (isset($exploded_name[1])) {
				$name = $exploded_name[0];
				$media = $exploded_name[1];
			}

			if (!isset($this->_registeredCss[$name]) || (!in_array($media, $this->_registeredCss[$name]))) {
				$this->_registeredCss[$name][] = $media;
			}
		}
	}

	/**
	 * Betöltendő javascript fájlokat regisztrál.
	 */
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

	/**
	 * Register ES module scripts (loaded with type="module").
	 * Use "module:" prefix in library constants or call directly.
	 * Modules are always rendered in <head> since they're deferred by default.
	 */
	public function registerModule(string $resource_name): void
	{
		$exploded_resource_name = explode(',', $resource_name);

		foreach ($exploded_resource_name as $name) {
			// Strip ^ prefix if present (not needed for modules, they're always deferred)
			$clean_name = str_replace("^", "", $name);

			if (array_key_exists($clean_name, $this->_registeredModules)) {
				continue;
			}

			$this->_registeredModules[$clean_name] = true;
		}
	}

	/**
	 * Required to list the menu entries in the edit menu on the bottom right.
	 */
	public function registerRenderedLayoutComponent(iLayoutComponent $layoutComponent): void
	{
		if (!in_array($layoutComponent, $this->_renderedLayoutComponents)) {
			$this->_renderedLayoutComponents[] = $layoutComponent;
		}
	}

	/**
	 * Returns an array of rendered layout components.
	 *
	 * @return iLayoutComponent[] An array of iLayoutComponent elements.
	 */
	public function getRenderedLayoutComponents(): array
	{
		return $this->_renderedLayoutComponents;
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

	/**
	 * @return array<string, string>
	 */
	public function getI18nPayload(): array
	{
		$result = [];

		foreach (array_keys($this->_registeredI18nKeys) as $key) {
			$result[$key] = I18nRuntime::t($key);
		}

		return $result;
	}

	/**
	 * @return array<string, string>
	 */
	private static function buildWidgetInserterStrings(): array
	{
		return [
			'cms.widget.insert.icon_title' => t('cms.widget.insert.icon_title'),
			'cms.widget.insert.placeholder' => t('cms.widget.insert.placeholder'),
			'cms.widget.insert.button' => t('cms.widget.insert.button'),
			'cms.widget.insert_from_clipboard' => t('cms.widget.insert_from_clipboard'),
		];
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
	 * @return array{
	 *     type: string,
	 *     component: string,
	 *     props: array{
	 *         slot_name: string,
	 *         counter: int,
	 *         visibleWidgets: list<array{type_name:string, name:string, description:string}>,
	 *     },
	 *     slots: array{
	 *         add_widget_from_list: list<array{
	 *             type: string,
	 *             component: string,
	 *             props: array{
	 *                 slot_name: string,
	 *                 counter: int,
	 *                 visibleWidgets: list<array{type_name:string, name:string, description:string}>
	 *             },
	 *             slots: array<string, list<array<string, mixed>>>,
	 *             meta?: array<string, mixed>
	 *         }>
	 *     },
	 *     meta?: array<string, mixed>
	 * }
	 */
	public function buildWidgetInserterTree(string $slot_name, ?WidgetConnection $connection = null): array
	{
		$this->_visibleWidgets ??= Widget::getVisibleWidgetMetadataList();
		++$this->_widgetInsertCounter;

		$strings = self::buildWidgetInserterStrings();
		$props = [
			'slot_name' => $slot_name,
			'counter' => $this->_widgetInsertCounter,
			'visibleWidgets' => $this->_visibleWidgets,
		];

		return SduiNode::create(
			component: 'widgetInsert',
			props: $props,
			slots: [
				'add_widget_from_list' => [
					SduiNode::create('addWidgetFromList', $props, strings: $strings),
				],
			],
			type: SduiNode::TYPE_SUB,
			meta: [
				'widget_connection' => WidgetConnection::toTreeMetadata($connection),
			],
			strings: $strings,
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function buildAdminDropdownTree(): ?array
	{
		$page_id = $this->getPageId();

		if ($page_id === null || !User::getCurrentUser()) {
			return null;
		}

		if (!ResourceAcl::canAccessResource($page_id, ResourceAcl::_ACL_EDIT)) {
			return null;
		}

		$edit_mode = Request::_CONFIG(CmsConfig::EDITMODE, CmsConfig::EDITMODE_DEFAULTVALUE) == 1;
		$layout_commands = [];

		foreach ($this->getRenderedLayoutComponents() as $layout_component) {
			$layout_commands = array_merge(
				$layout_commands,
				Widget::normalizeEditCommands($layout_component->getEditableCommands())
			);
		}

		return SduiNode::create(
			component: 'adminDropdown',
			props: [
				'page_edit_url' => Form::getSeoUrl(FormList::WEBPAGEPAGE, $page_id),
				'page_edit_label' => t('cms.admin_dropdown.seo'),
				'edit_mode_url' => Url::modifyCurrentUrl([
					'context' => 'Page',
					'event' => 'EditmodeSwitch',
					'set' => $edit_mode ? '0' : '1',
					'referer' => Url::getCurrentUrlForReferer(),
				]),
				'edit_mode_label' => t('cms.admin_dropdown.edit_mode'),
				'edit_mode_state_label' => t($edit_mode ? 'cms.admin_dropdown.edit_mode_off' : 'cms.admin_dropdown.edit_mode_on'),
				'edit_mode_action_label' => t($edit_mode ? 'cms.admin_dropdown.turn_edit_mode_off' : 'cms.admin_dropdown.turn_edit_mode_on'),
				'layout_commands' => $layout_commands,
				'home_url' => '/admin/',
				'home_label' => t('admin.menu.home'),
				'logout_url' => Url::modifyCurrentUrl([
					'context' => 'user',
					'event' => 'logout',
					'referer' => Url::getCurrentUrlForReferer(),
				]),
				'logout_label' => t('common.logout'),
			],
			type: SduiNode::TYPE_SUB,
		);
	}
}
