<?php

/**
 * Lucide Icons - thinner strokes than Tabler (stroke-width: 2, but visually lighter)
 * https://lucide.dev/.
 */
class IconLibraryLucide extends AbstractIconLibrary
{
	protected static array $iconMap = [
		// Actions
		'add' => 'plus',
		'edit' => 'pencil',
		'delete' => 'trash-2',
		'trash' => 'trash-2',
		'view' => 'eye',
		'look' => 'external-link',
		'choose' => 'settings-2',

		// Navigation
		'dropdown' => 'chevron-down',
		'widget_up' => 'arrow-up',
		'widget_down' => 'arrow-down',

		// File operations
		'upload' => 'upload',
		'download' => 'download',

		// Form
		'form_save' => 'save',
		'form_cancel' => 'x',
		'form_help' => 'help-circle',
		'form_error' => 'alert-circle',

		// Content
		'widget_add' => 'file-plus',
		'widget_remove' => 'trash-2',
		'widget_insert' => 'file-plus',
		'webpage_add' => 'file-plus',
		'content_add' => 'file-plus',
		'plus' => 'plus',

		// Folders
		'folder' => 'folder',
		'folder_add' => 'folder-plus',

		// Users
		'user' => 'user',
		'usergroup' => 'users',
		'usergroup_add' => 'user-plus',
		'system_usergroup' => 'users',
		'people' => 'users',
		'roles' => 'shield-check',
		'role' => 'shield-check',
		'lock' => 'lock',
		'login' => 'log-in',

		// Status
		'status_ok' => 'check-circle',
		'status_error' => 'x-circle',
		'alert' => 'alert-circle',
		'warning' => 'alert-triangle',
		'info' => 'info',
		'accept' => 'check',
		'remove' => 'x',
		'bug' => 'bug',
		'gear' => 'settings',
		'comment' => 'message-circle',

		// Data
		'datasheet' => 'file-text',
		'chart' => 'bar-chart-2',
		'checklist' => 'list-checks',
		'versions' => 'history',
		'column_width' => 'move-horizontal',
		'align' => 'sliders-horizontal',

		// Links
		'link' => 'link',
		'link_out' => 'external-link',
		'link_none' => 'unlink',

		// Misc
		'admin_wrench' => 'wrench',
		'menubar' => 'menu',
		'help' => 'help-circle',
		'date' => 'calendar',
		'datetime' => 'calendar-clock',
	];

	/** @var array<string, int> */
	protected static array $sizes = [
		'small' => 16,
		'default' => 18,
		'medium' => 24,
		'large' => 32,
	];

	protected static function buildPath(string $name, string $size): string
	{
		return "https://cdn.jsdelivr.net/npm/lucide-static@latest/icons/{$name}.svg";
	}

	protected static function buildHtml(string $name, string $alt, string $size): string
	{
		$sizeVal = static::$sizes[$size] ?? 18;
		$title = htmlspecialchars($alt, ENT_QUOTES);

		return "<img class=\"ico lucide-icon\" src=\"https://cdn.jsdelivr.net/npm/lucide-static@latest/icons/{$name}.svg\" width=\"{$sizeVal}\" height=\"{$sizeVal}\" alt=\"{$title}\" title=\"{$title}\" />";
	}
}
