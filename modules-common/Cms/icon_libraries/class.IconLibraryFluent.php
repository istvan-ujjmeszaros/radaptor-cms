<?php

/**
 * Fluent UI Icons - Microsoft's icon system
 * https://github.com/microsoft/fluentui-system-icons.
 */
class IconLibraryFluent extends AbstractIconLibrary
{
	protected static array $iconMap = [
		// Actions
		'add' => 'add',
		'edit' => 'edit',
		'delete' => 'delete',
		'trash' => 'delete',
		'view' => 'eye',
		'look' => 'open',
		'choose' => 'settings',

		// Navigation
		'dropdown' => 'chevron_down',
		'widget_up' => 'arrow_up',
		'widget_down' => 'arrow_down',

		// File operations
		'upload' => 'arrow_upload',
		'download' => 'arrow_download',

		// Form
		'form_save' => 'save',
		'form_cancel' => 'dismiss',
		'form_help' => 'question_circle',
		'form_error' => 'warning',

		// Content
		'widget_add' => 'document_add',
		'widget_remove' => 'delete',
		'widget_insert' => 'add_circle',
		'webpage_add' => 'document_add',
		'content_add' => 'document_add',
		'plus' => 'add',

		// Folders
		'folder' => 'folder',
		'folder_add' => 'folder_add',

		// Users
		'user' => 'person',
		'usergroup' => 'people',
		'usergroup_add' => 'person_add',
		'system_usergroup' => 'people_team',
		'people' => 'people',
		'roles' => 'shield_checkmark',
		'role' => 'shield_checkmark',
		'lock' => 'lock_closed',
		'login' => 'arrow_enter',

		// Status
		'status_ok' => 'checkmark_circle',
		'status_error' => 'dismiss_circle',
		'alert' => 'error_circle',
		'warning' => 'warning',
		'info' => 'info',
		'accept' => 'checkmark',
		'remove' => 'dismiss',
		'bug' => 'bug',
		'gear' => 'settings',
		'comment' => 'comment',

		// Data
		'datasheet' => 'document_text',
		'chart' => 'data_bar_vertical',
		'checklist' => 'checkbox_checked',
		'versions' => 'history',
		'column_width' => 'arrow_autofit_width',
		'content_formatting' => 'text_font',
		'align' => 'options',

		// Links
		'link' => 'link',
		'link_out' => 'open',
		'link_none' => 'link_dismiss',

		// Misc
		'admin_wrench' => 'wrench',
		'home' => 'home',
		'logout' => 'arrow_exit',
		'menubar' => 'navigation',
		'help' => 'question_circle',
		'date' => 'calendar_ltr',
		'datetime' => 'calendar_clock',
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
		$sizeVal = static::$sizes[$size] ?? 20;

		return "https://cdn.jsdelivr.net/npm/@fluentui/svg-icons@latest/icons/{$name}_{$sizeVal}_regular.svg";
	}

	protected static function buildHtml(string $name, string $alt, string $size): string
	{
		$sizeVal = static::$sizes[$size] ?? 20;
		$title = htmlspecialchars($alt, ENT_QUOTES);

		return "<img class=\"ico fluent-icon\" src=\"https://cdn.jsdelivr.net/npm/@fluentui/svg-icons@latest/icons/{$name}_{$sizeVal}_regular.svg\" width=\"{$sizeVal}\" height=\"{$sizeVal}\" alt=\"{$title}\" title=\"{$title}\" />";
	}
}
