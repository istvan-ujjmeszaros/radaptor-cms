<?php

/**
 * Boxicons - High quality web friendly icons
 * https://boxicons.com/.
 */
class IconLibraryBoxicons extends AbstractIconLibrary
{
	protected static array $iconMap = [
		// Actions
		'add' => 'bx-plus',
		'edit' => 'bx-pencil',
		'delete' => 'bx-trash',
		'trash' => 'bx-trash',
		'view' => 'bx-show',
		'look' => 'bx-link-external',
		'choose' => 'bx-cog',

		// Navigation
		'dropdown' => 'bx-chevron-down',
		'widget_up' => 'bx-up-arrow-alt',
		'widget_down' => 'bx-down-arrow-alt',

		// File operations
		'upload' => 'bx-upload',
		'download' => 'bx-download',

		// Form
		'form_save' => 'bx-save',
		'form_cancel' => 'bx-x',
		'form_help' => 'bx-help-circle',
		'form_error' => 'bx-error-circle',

		// Content
		'widget_add' => 'bx-layer-plus',
		'widget_remove' => 'bx-trash',
		'widget_insert' => 'bx-plus-circle',
		'webpage_add' => 'bx-layer-plus',
		'content_add' => 'bx-layer-plus',
		'plus' => 'bx-plus',

		// Folders
		'folder' => 'bx-folder',
		'folder_add' => 'bx-folder-plus',

		// Users
		'user' => 'bx-user',
		'usergroup' => 'bx-group',
		'usergroup_add' => 'bx-user-plus',
		'system_usergroup' => 'bx-group',
		'people' => 'bx-group',
		'roles' => 'bx-shield-quarter',
		'role' => 'bx-shield-quarter',
		'lock' => 'bx-lock',
		'login' => 'bx-log-in',

		// Status
		'status_ok' => 'bx-check-circle',
		'status_error' => 'bx-x-circle',
		'alert' => 'bx-error-circle',
		'warning' => 'bx-error',
		'info' => 'bx-info-circle',
		'accept' => 'bx-check',
		'remove' => 'bx-x',
		'bug' => 'bx-bug',
		'gear' => 'bx-cog',
		'comment' => 'bx-comment',

		// Data
		'datasheet' => 'bx-file',
		'chart' => 'bx-bar-chart',
		'checklist' => 'bx-list-check',
		'versions' => 'bx-history',
		'column_width' => 'bx-move-horizontal',
		'content_formatting' => 'bx-font',
		'align' => 'bx-slider',

		// Links
		'link' => 'bx-link',
		'link_out' => 'bx-link-external',
		'link_none' => 'bx-unlink',

		// Misc
		'admin_wrench' => 'bx-wrench',
		'home' => 'bx-home',
		'logout' => 'bx-log-out',
		'menubar' => 'bx-menu',
		'help' => 'bx-help-circle',
		'date' => 'bx-calendar',
		'datetime' => 'bx-calendar-event',
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
		return "https://cdn.jsdelivr.net/npm/boxicons@latest/svg/regular/{$name}.svg";
	}

	protected static function buildHtml(string $name, string $alt, string $size): string
	{
		$sizeVal = static::$sizes[$size] ?? 20;
		$title = htmlspecialchars($alt, ENT_QUOTES);

		return "<img class=\"ico boxicon\" src=\"https://cdn.jsdelivr.net/npm/boxicons@latest/svg/regular/{$name}.svg\" width=\"{$sizeVal}\" height=\"{$sizeVal}\" alt=\"{$title}\" title=\"{$title}\" />";
	}
}
