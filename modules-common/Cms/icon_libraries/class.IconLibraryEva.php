<?php

/**
 * Eva Icons - Beautifully crafted Open Source UI icons
 * https://akveo.github.io/eva-icons/.
 */
class IconLibraryEva extends AbstractIconLibrary
{
	protected static array $iconMap = [
		// Actions
		'add' => 'plus-outline',
		'edit' => 'edit-outline',
		'delete' => 'trash-2-outline',
		'trash' => 'trash-2-outline',
		'view' => 'eye-outline',
		'look' => 'external-link-outline',
		'choose' => 'settings-2-outline',

		// Navigation
		'dropdown' => 'chevron-down-outline',
		'widget_up' => 'arrow-up-outline',
		'widget_down' => 'arrow-down-outline',

		// File operations
		'upload' => 'upload-outline',
		'download' => 'download-outline',

		// Form
		'form_save' => 'save-outline',
		'form_cancel' => 'close-outline',
		'form_help' => 'question-mark-circle-outline',
		'form_error' => 'alert-circle-outline',

		// Content
		'widget_add' => 'file-add-outline',
		'widget_remove' => 'trash-2-outline',
		'widget_insert' => 'file-add-outline',
		'webpage_add' => 'file-add-outline',
		'content_add' => 'file-add-outline',
		'plus' => 'plus-outline',

		// Folders
		'folder' => 'folder-outline',
		'folder_add' => 'folder-add-outline',

		// Users
		'user' => 'person-outline',
		'usergroup' => 'people-outline',
		'usergroup_add' => 'person-add-outline',
		'system_usergroup' => 'people-outline',
		'people' => 'people-outline',
		'roles' => 'shield-outline',
		'role' => 'shield-outline',
		'lock' => 'lock-outline',
		'login' => 'log-in-outline',

		// Status
		'status_ok' => 'checkmark-circle-outline',
		'status_error' => 'close-circle-outline',
		'alert' => 'alert-circle-outline',
		'warning' => 'alert-triangle-outline',
		'info' => 'info-outline',
		'accept' => 'checkmark-outline',
		'remove' => 'close-outline',
		'bug' => 'bug-outline',
		'gear' => 'settings-outline',
		'comment' => 'message-circle-outline',

		// Data
		'datasheet' => 'file-text-outline',
		'chart' => 'bar-chart-outline',
		'checklist' => 'checkmark-square-outline',
		'versions' => 'clock-outline',
		'column_width' => 'expand-outline',
		'align' => 'options-outline',

		// Links
		'link' => 'link-outline',
		'link_out' => 'external-link-outline',
		'link_none' => 'link-2-outline',

		// Misc
		'admin_wrench' => 'settings-2-outline',
		'menubar' => 'menu-outline',
		'help' => 'question-mark-circle-outline',
		'date' => 'calendar-outline',
		'datetime' => 'calendar-outline',
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
		return "https://cdn.jsdelivr.net/npm/eva-icons@latest/outline/svg/{$name}.svg";
	}

	protected static function buildHtml(string $name, string $alt, string $size): string
	{
		$sizeVal = static::$sizes[$size] ?? 20;
		$title = htmlspecialchars($alt, ENT_QUOTES);

		return "<img class=\"ico eva-icon\" src=\"https://cdn.jsdelivr.net/npm/eva-icons@latest/outline/svg/{$name}.svg\" width=\"{$sizeVal}\" height=\"{$sizeVal}\" alt=\"{$title}\" title=\"{$title}\" />";
	}
}
