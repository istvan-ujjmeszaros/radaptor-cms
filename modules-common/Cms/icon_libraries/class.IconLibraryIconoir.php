<?php

/**
 * Iconoir - A high-quality selection of free icons
 * https://iconoir.com/.
 */
class IconLibraryIconoir extends AbstractIconLibrary
{
	protected static array $iconMap = [
		// Actions
		'add' => 'plus',
		'edit' => 'edit-pencil',
		'delete' => 'trash',
		'trash' => 'trash',
		'view' => 'eye-empty',
		'look' => 'open-new-window',
		'choose' => 'settings',

		// Navigation
		'dropdown' => 'nav-arrow-down',
		'widget_up' => 'arrow-up',
		'widget_down' => 'arrow-down',

		// File operations
		'upload' => 'upload',
		'download' => 'download',

		// Form
		'form_save' => 'floppy-disk',
		'form_cancel' => 'xmark',
		'form_help' => 'help-circle',
		'form_error' => 'warning-circle',

		// Content
		'widget_add' => 'page-plus-in',
		'widget_remove' => 'trash',
		'widget_insert' => 'page-plus-in',
		'webpage_add' => 'page-plus-in',
		'content_add' => 'page-plus-in',
		'plus' => 'plus',

		// Folders
		'folder' => 'folder',
		'folder_add' => 'folder-plus',

		// Users
		'user' => 'user',
		'usergroup' => 'group',
		'usergroup_add' => 'add-user',
		'system_usergroup' => 'community',
		'people' => 'community',
		'roles' => 'shield-check',
		'role' => 'shield-check',
		'lock' => 'lock',
		'login' => 'log-in',

		// Status
		'status_ok' => 'check-circle',
		'status_error' => 'xmark-circle',
		'alert' => 'warning-circle',
		'warning' => 'warning-triangle',
		'info' => 'info-circle',
		'accept' => 'check',
		'remove' => 'xmark',
		'bug' => 'bug',
		'gear' => 'settings',
		'comment' => 'chat-bubble',

		// Data
		'datasheet' => 'page',
		'chart' => 'stats-up-square',
		'checklist' => 'task-list',
		'versions' => 'clock',
		'column_width' => 'horizontal-split',
		'content_formatting' => 'text',
		'align' => 'ruler-arrows',

		// Links
		'link' => 'link',
		'link_out' => 'open-new-window',
		'link_none' => 'link-xmark',

		// Misc
		'admin_wrench' => 'tools',
		'home' => 'home',
		'logout' => 'log-out',
		'menubar' => 'menu',
		'help' => 'help-circle',
		'date' => 'calendar',
		'datetime' => 'calendar-plus',
	];

	/** @var array<string, int> */
	protected static array $sizes = [
		'small' => 16,
		'default' => 20,
		'medium' => 24,
		'large' => 32,
	];

	protected static function buildPath(string $name, string $size): string
	{
		return "https://cdn.jsdelivr.net/npm/iconoir@latest/icons/regular/{$name}.svg";
	}

	protected static function buildHtml(string $name, string $alt, string $size): string
	{
		$sizeVal = static::$sizes[$size] ?? 20;
		$title = htmlspecialchars($alt, ENT_QUOTES);

		return "<img class=\"ico iconoir\" src=\"https://cdn.jsdelivr.net/npm/iconoir@latest/icons/regular/{$name}.svg\" width=\"{$sizeVal}\" height=\"{$sizeVal}\" alt=\"{$title}\" title=\"{$title}\" />";
	}
}
