<?php

class IconLibraryTabler extends AbstractIconLibrary
{
	protected static array $iconMap = [
		// Actions
		'add' => 'plus',
		'edit' => 'pencil',
		'delete' => 'trash',
		'trash' => 'trash',
		'view' => 'eye',
		'look' => 'external-link',
		'choose' => 'settings',

		// Navigation
		'dropdown' => 'chevron-down',
		'widget_up' => 'arrow-up',
		'widget_down' => 'arrow-down',

		// File operations
		'upload' => 'upload',
		'download' => 'download',

		// Form
		'form_save' => 'device-floppy',
		'form_cancel' => 'x',
		'form_help' => 'help',
		'form_error' => 'alert-circle',

		// Content
		'widget_add' => 'file-plus',
		'widget_remove' => 'trash',
		'widget_insert' => 'file-plus',
		'webpage_add' => 'file-plus',
		'content_add' => 'file-plus',
		'plus' => 'plus',

		// Folders
		'folder' => 'folder',
		'folder_add' => 'folder-plus',

		// Users
		'user' => 'user',
		'usergroup' => 'users-group',
		'usergroup_add' => 'user-plus',
		'system_usergroup' => 'users-group',
		'people' => 'users',
		'roles' => 'shield-check',
		'role' => 'shield-check',
		'lock' => 'lock',
		'login' => 'login',

		// Status
		'status_ok' => 'circle-check',
		'status_error' => 'circle-x',
		'alert' => 'alert-circle',
		'warning' => 'alert-triangle',
		'info' => 'info-circle',
		'accept' => 'check',
		'remove' => 'x',
		'bug' => 'bug',
		'gear' => 'settings',
		'comment' => 'message',

		// Data
		'datasheet' => 'file-text',
		'chart' => 'chart-bar',
		'checklist' => 'list-check',
		'versions' => 'history',
		'column_width' => 'arrows-horizontal',
		'align' => 'adjustments',

		// Links
		'link' => 'link',
		'link_out' => 'external-link',
		'link_none' => 'link-off',

		// Misc
		'admin_wrench' => 'tools',
		'menubar' => 'menu-2',
		'help' => 'help',
		'date' => 'calendar',
		'datetime' => 'calendar-time',
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
		return "https://cdn.jsdelivr.net/npm/@tabler/icons@latest/icons/outline/{$name}.svg";
	}

	protected static function buildHtml(string $name, string $alt, string $size): string
	{
		$sizeVal = static::$sizes[$size] ?? 20;
		$title = htmlspecialchars($alt, ENT_QUOTES);

		// Try to load inline SVG from local node_modules
		$svg = self::_loadSvg($name);

		if ($svg !== null) {
			// Modify SVG attributes for size and accessibility
			$svg = preg_replace('/width="24"/', "width=\"{$sizeVal}\"", $svg);
			$svg = preg_replace('/height="24"/', "height=\"{$sizeVal}\"", $svg);
			$svg = preg_replace('/<svg/', '<svg class="ico tabler-icon" role="img" aria-label="' . $title . '"', $svg, 1);

			return $svg;
		}

		// Fallback to img tag if local SVG not found
		return "<img class=\"ico tabler-icon\" src=\"https://cdn.jsdelivr.net/npm/@tabler/icons@latest/icons/outline/{$name}.svg\" width=\"{$sizeVal}\" height=\"{$sizeVal}\" alt=\"{$title}\" title=\"{$title}\" />";
	}

	private static function _loadSvg(string $name): ?string
	{
		if (isset(self::$_svgCache[$name])) {
			return self::$_svgCache[$name];
		}

		$svgPath = __DIR__ . '/../../../../../node_modules/@tabler/icons/icons/outline/' . $name . '.svg';

		if (file_exists($svgPath)) {
			self::$_svgCache[$name] = file_get_contents($svgPath);

			return self::$_svgCache[$name];
		}

		return null;
	}
}
