<?php

/**
 * Bootstrap Icons
 * https://icons.getbootstrap.com/.
 */
class IconLibraryBootstrap extends AbstractIconLibrary
{
	protected static array $iconMap = [
		// Actions
		'add' => 'plus',
		'edit' => 'pencil',
		'delete' => 'trash',
		'trash' => 'trash',
		'view' => 'eye',
		'look' => 'box-arrow-up-right',
		'choose' => 'gear',

		// Navigation
		'dropdown' => 'chevron-down',
		'widget_up' => 'arrow-up',
		'widget_down' => 'arrow-down',

		// File operations
		'upload' => 'upload',
		'download' => 'download',

		// Form
		'form_save' => 'floppy',
		'form_cancel' => 'x-lg',
		'form_help' => 'question-circle',
		'form_error' => 'exclamation-circle',

		// Content
		'widget_add' => 'file-earmark-plus',
		'widget_remove' => 'trash',
		'widget_insert' => 'file-earmark-plus',
		'webpage_add' => 'file-earmark-plus',
		'content_add' => 'file-earmark-plus',
		'plus' => 'plus',

		// Folders
		'folder' => 'folder',
		'folder_add' => 'folder-plus',

		// Users
		'user' => 'person',
		'usergroup' => 'people',
		'usergroup_add' => 'person-plus',
		'system_usergroup' => 'people-fill',
		'people' => 'people',
		'roles' => 'shield-check',
		'role' => 'shield-check',
		'lock' => 'lock',
		'login' => 'box-arrow-in-right',

		// Status
		'status_ok' => 'check-circle',
		'status_error' => 'x-circle',
		'alert' => 'exclamation-circle',
		'warning' => 'exclamation-triangle',
		'info' => 'info-circle',
		'accept' => 'check',
		'remove' => 'x-lg',
		'bug' => 'bug',
		'gear' => 'gear',
		'comment' => 'chat',

		// Data
		'datasheet' => 'file-text',
		'chart' => 'bar-chart',
		'checklist' => 'list-check',
		'versions' => 'clock-history',
		'column_width' => 'arrows-expand',
		'content_formatting' => 'rulers',
		'align' => 'sliders',

		// Links
		'link' => 'link-45deg',
		'link_out' => 'box-arrow-up-right',
		'link_none' => 'link',

		// Misc
		'admin_wrench' => 'wrench',
		'home' => 'house',
		'logout' => 'box-arrow-right',
		'menubar' => 'list',
		'help' => 'question-circle',
		'date' => 'calendar',
		'datetime' => 'calendar-event',
	];

	/** @var array<string, int> */
	protected static array $sizes = [
		'small' => 16,
		'default' => 18,
		'medium' => 24,
		'large' => 32,
	];

	/** @var array<string, string> In-memory cache of loaded SVGs */
	private static array $_svgCache = [];

	protected static function buildPath(string $name, string $size): string
	{
		return "https://cdn.jsdelivr.net/npm/bootstrap-icons@latest/icons/{$name}.svg";
	}

	protected static function buildHtml(string $name, string $alt, string $size): string
	{
		$sizeVal = static::$sizes[$size] ?? 18;
		$title = htmlspecialchars($alt, ENT_QUOTES);

		// Try to load inline SVG from local node_modules
		$svg = self::_loadSvg($name);

		if ($svg !== null) {
			// Modify SVG attributes for size and accessibility
			$svg = preg_replace('/width="16"/', "width=\"{$sizeVal}\"", $svg);
			$svg = preg_replace('/height="16"/', "height=\"{$sizeVal}\"", $svg);
			$svg = preg_replace('/class="bi bi-[^"]*"/', 'class="ico bootstrap-icon"', $svg);
			$svg = preg_replace('/<svg/', '<svg role="img" aria-label="' . $title . '"', $svg, 1);

			return $svg;
		}

		// Fallback to img tag if local SVG not found
		return "<img class=\"ico bootstrap-icon\" src=\"https://cdn.jsdelivr.net/npm/bootstrap-icons@latest/icons/{$name}.svg\" width=\"{$sizeVal}\" height=\"{$sizeVal}\" alt=\"{$title}\" title=\"{$title}\" />";
	}

	private static function _loadSvg(string $name): ?string
	{
		if (isset(self::$_svgCache[$name])) {
			return self::$_svgCache[$name];
		}

		$svgPath = __DIR__ . '/../../../../../node_modules/bootstrap-icons/icons/' . $name . '.svg';

		if (file_exists($svgPath)) {
			self::$_svgCache[$name] = file_get_contents($svgPath);

			return self::$_svgCache[$name];
		}

		return null;
	}
}
