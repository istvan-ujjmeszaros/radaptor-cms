<?php

/**
 * Carbon Icons - IBM's open source icon library
 * https://carbondesignsystem.com/guidelines/icons/library/.
 */
class IconLibraryCarbon extends AbstractIconLibrary
{
	protected static array $iconMap = [
		// Actions
		'add' => 'add',
		'edit' => 'edit',
		'delete' => 'trash-can',
		'trash' => 'trash-can',
		'view' => 'view',
		'look' => 'launch',
		'choose' => 'settings--edit',

		// Navigation
		'dropdown' => 'chevron--down',
		'widget_up' => 'arrow--up',
		'widget_down' => 'arrow--down',

		// File operations
		'upload' => 'upload',
		'download' => 'download',

		// Form
		'form_save' => 'save',
		'form_cancel' => 'close',
		'form_help' => 'help',
		'form_error' => 'warning--alt',

		// Content
		'widget_add' => 'document--add',
		'widget_remove' => 'trash-can',
		'widget_insert' => 'add--alt',
		'webpage_add' => 'document--add',
		'content_add' => 'document--add',
		'plus' => 'add',

		// Folders
		'folder' => 'folder',
		'folder_add' => 'folder--add',

		// Users
		'user' => 'user',
		'usergroup' => 'group',
		'usergroup_add' => 'user--follow',
		'system_usergroup' => 'group',
		'people' => 'group',
		'roles' => 'group--security',
		'role' => 'group--security',
		'lock' => 'locked',
		'login' => 'login',

		// Status
		'status_ok' => 'checkmark--filled',
		'status_error' => 'close--filled',
		'alert' => 'warning--alt--filled',
		'warning' => 'warning',
		'info' => 'information',
		'accept' => 'checkmark',
		'remove' => 'close',
		'bug' => 'debug',
		'gear' => 'settings',
		'comment' => 'chat',

		// Data
		'datasheet' => 'document',
		'chart' => 'chart--bar',
		'checklist' => 'task',
		'versions' => 'time',
		'column_width' => 'fit-to-width',
		'content_formatting' => 'text-font',
		'align' => 'settings--adjust',

		// Links
		'link' => 'link',
		'link_out' => 'launch',
		'link_none' => 'unlink',

		// Misc
		'admin_wrench' => 'tool-kit',
		'home' => 'home',
		'logout' => 'logout',
		'menubar' => 'menu',
		'help' => 'help',
		'date' => 'calendar',
		'datetime' => 'event--schedule',
	];

	/** @var array<string, int> */
	protected static array $sizes = [
		'small' => 16,
		'default' => 20,
		'medium' => 24,
		'large' => 32,
	];

	/** @var array<string, string> In-memory cache of loaded SVGs */
	private static array $_svgCache = [];

	protected static function buildPath(string $name, string $size): string
	{
		// Carbon only has full icon set in 32 folder
		return "https://cdn.jsdelivr.net/npm/@carbon/icons@latest/svg/32/{$name}.svg";
	}

	protected static function buildHtml(string $name, string $alt, string $size): string
	{
		$sizeVal = static::$sizes[$size] ?? 20;
		$title = htmlspecialchars($alt, ENT_QUOTES);

		// Try to load inline SVG from local node_modules
		$svg = self::_loadSvg($name);

		if ($svg !== null) {
			// Add width, height, fill="currentColor" and accessibility attributes
			$svg = preg_replace(
				'/<svg\s+xmlns/',
				'<svg class="ico carbon-icon" role="img" aria-label="' . $title . '" width="' . $sizeVal . '" height="' . $sizeVal . '" fill="currentColor" xmlns',
				$svg,
				1
			);

			return $svg;
		}

		// Fallback to img tag if local SVG not found
		return "<img class=\"ico carbon-icon\" src=\"https://cdn.jsdelivr.net/npm/@carbon/icons@latest/svg/32/{$name}.svg\" width=\"{$sizeVal}\" height=\"{$sizeVal}\" alt=\"{$title}\" title=\"{$title}\" />";
	}

	private static function _loadSvg(string $name): ?string
	{
		if (isset(self::$_svgCache[$name])) {
			return self::$_svgCache[$name];
		}

		$svgPath = __DIR__ . '/../../../../../node_modules/@carbon/icons/svg/32/' . $name . '.svg';

		if (file_exists($svgPath)) {
			self::$_svgCache[$name] = file_get_contents($svgPath);

			return self::$_svgCache[$name];
		}

		return null;
	}
}
