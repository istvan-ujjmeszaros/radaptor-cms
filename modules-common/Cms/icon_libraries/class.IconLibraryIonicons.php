<?php

/**
 * Ionicons - Ionic Framework's icon set
 * https://ionic.io/ionicons.
 */
class IconLibraryIonicons extends AbstractIconLibrary
{
	protected static array $iconMap = [
		// Actions
		'add' => 'add-outline',
		'edit' => 'pencil-outline',
		'delete' => 'trash-outline',
		'trash' => 'trash-outline',
		'view' => 'eye-outline',
		'look' => 'open-outline',
		'choose' => 'settings-outline',

		// Navigation
		'dropdown' => 'chevron-down-outline',
		'widget_up' => 'arrow-up-outline',
		'widget_down' => 'arrow-down-outline',

		// File operations
		'upload' => 'cloud-upload-outline',
		'download' => 'cloud-download-outline',

		// Form
		'form_save' => 'save-outline',
		'form_cancel' => 'close-outline',
		'form_help' => 'help-circle-outline',
		'form_error' => 'alert-circle-outline',

		// Content
		'widget_add' => 'duplicate-outline',
		'widget_remove' => 'trash-outline',
		'widget_insert' => 'duplicate-outline',
		'webpage_add' => 'duplicate-outline',
		'content_add' => 'duplicate-outline',
		'plus' => 'add-outline',

		// Folders
		'folder' => 'folder-outline',
		'folder_add' => 'folder-open-outline',

		// Users
		'user' => 'person-outline',
		'usergroup' => 'people-outline',
		'usergroup_add' => 'person-add-outline',
		'system_usergroup' => 'people-circle-outline',
		'people' => 'people-outline',
		'roles' => 'shield-checkmark-outline',
		'role' => 'shield-checkmark-outline',
		'lock' => 'lock-closed-outline',
		'login' => 'log-in-outline',

		// Status
		'status_ok' => 'checkmark-circle-outline',
		'status_error' => 'close-circle-outline',
		'alert' => 'alert-circle-outline',
		'warning' => 'warning-outline',
		'info' => 'information-circle-outline',
		'accept' => 'checkmark-outline',
		'remove' => 'close-outline',
		'bug' => 'bug-outline',
		'gear' => 'settings-outline',
		'comment' => 'chatbubble-outline',

		// Data
		'datasheet' => 'document-text-outline',
		'chart' => 'bar-chart-outline',
		'checklist' => 'checkbox-outline',
		'versions' => 'time-outline',
		'column_width' => 'resize-outline',
		'content_formatting' => 'text-outline',
		'align' => 'options-outline',

		// Links
		'link' => 'link-outline',
		'link_out' => 'open-outline',
		'link_none' => 'unlink-outline',

		// Misc
		'admin_wrench' => 'construct-outline',
		'home' => 'home-outline',
		'logout' => 'log-out-outline',
		'menubar' => 'menu-outline',
		'help' => 'help-circle-outline',
		'date' => 'calendar-outline',
		'datetime' => 'calendar-number-outline',
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
		return "https://cdn.jsdelivr.net/npm/ionicons@latest/dist/svg/{$name}.svg";
	}

	protected static function buildHtml(string $name, string $alt, string $size): string
	{
		$sizeVal = static::$sizes[$size] ?? 20;
		$title = htmlspecialchars($alt, ENT_QUOTES);

		return "<img class=\"ico ionicon\" src=\"https://cdn.jsdelivr.net/npm/ionicons@latest/dist/svg/{$name}.svg\" width=\"{$sizeVal}\" height=\"{$sizeVal}\" alt=\"{$title}\" title=\"{$title}\" />";
	}
}
