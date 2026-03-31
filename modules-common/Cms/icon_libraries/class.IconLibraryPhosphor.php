<?php

/**
 * Phosphor Icons - A flexible icon family for interfaces
 * https://phosphoricons.com/.
 */
class IconLibraryPhosphor extends AbstractIconLibrary
{
	protected static array $iconMap = [
		// Actions
		'add' => 'plus',
		'edit' => 'pencil-simple',
		'delete' => 'trash',
		'trash' => 'trash',
		'view' => 'eye',
		'look' => 'arrow-square-out',
		'choose' => 'gear',

		// Navigation
		'dropdown' => 'caret-down',
		'widget_up' => 'arrow-up',
		'widget_down' => 'arrow-down',

		// File operations
		'upload' => 'upload-simple',
		'download' => 'download-simple',

		// Form
		'form_save' => 'floppy-disk',
		'form_cancel' => 'x',
		'form_help' => 'question',
		'form_error' => 'warning-circle',

		// Content
		'widget_add' => 'file-plus',
		'widget_remove' => 'trash',
		'widget_insert' => 'plus-circle',
		'webpage_add' => 'file-plus',
		'content_add' => 'file-plus',
		'plus' => 'plus',

		// Folders
		'folder' => 'folder',
		'folder_add' => 'folder-plus',

		// Users
		'user' => 'user',
		'usergroup' => 'users-three',
		'usergroup_add' => 'user-plus',
		'system_usergroup' => 'users',
		'people' => 'users',
		'roles' => 'shield-check',
		'role' => 'shield-check',
		'lock' => 'lock',
		'login' => 'sign-in',

		// Status
		'status_ok' => 'check-circle',
		'status_error' => 'x-circle',
		'alert' => 'warning-circle',
		'warning' => 'warning',
		'info' => 'info',
		'accept' => 'check',
		'remove' => 'x',
		'bug' => 'bug',
		'gear' => 'gear',
		'comment' => 'chat-circle',

		// Data
		'datasheet' => 'file-text',
		'chart' => 'chart-bar',
		'checklist' => 'list-checks',
		'versions' => 'clock-counter-clockwise',
		'column_width' => 'arrows-out-line-horizontal',
		'align' => 'sliders-horizontal',

		// Links
		'link' => 'link',
		'link_out' => 'arrow-square-out',
		'link_none' => 'link-break',

		// Misc
		'admin_wrench' => 'wrench',
		'menubar' => 'list',
		'help' => 'question',
		'date' => 'calendar-blank',
		'datetime' => 'calendar',
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
		return "https://cdn.jsdelivr.net/npm/@phosphor-icons/core@latest/assets/regular/{$name}.svg";
	}

	protected static function buildHtml(string $name, string $alt, string $size): string
	{
		$sizeVal = static::$sizes[$size] ?? 20;
		$title = htmlspecialchars($alt, ENT_QUOTES);

		return "<img class=\"ico phosphor-icon\" src=\"https://cdn.jsdelivr.net/npm/@phosphor-icons/core@latest/assets/regular/{$name}.svg\" width=\"{$sizeVal}\" height=\"{$sizeVal}\" alt=\"{$title}\" title=\"{$title}\" />";
	}
}
