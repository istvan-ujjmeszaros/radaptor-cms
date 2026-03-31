<?php

/**
 * Material Symbols - Google's icon set
 * https://fonts.google.com/icons.
 */
class IconLibraryMaterial extends AbstractIconLibrary
{
	protected static array $iconMap = [
		// Actions
		'add' => 'add',
		'edit' => 'edit',
		'delete' => 'delete',
		'trash' => 'delete',
		'view' => 'visibility',
		'look' => 'open_in_new',
		'choose' => 'tune',

		// Navigation
		'dropdown' => 'expand_more',
		'widget_up' => 'arrow_upward',
		'widget_down' => 'arrow_downward',

		// File operations
		'upload' => 'upload',
		'download' => 'download',

		// Form
		'form_save' => 'save',
		'form_cancel' => 'close',
		'form_help' => 'help',
		'form_error' => 'error',

		// Content
		'widget_add' => 'note_add',
		'widget_remove' => 'delete',
		'widget_insert' => 'note_add',
		'webpage_add' => 'note_add',
		'content_add' => 'note_add',
		'plus' => 'add',

		// Folders
		'folder' => 'folder',
		'folder_add' => 'create_new_folder',

		// Users
		'user' => 'person',
		'usergroup' => 'group',
		'usergroup_add' => 'person_add',
		'system_usergroup' => 'groups',
		'people' => 'people',
		'roles' => 'verified_user',
		'role' => 'verified_user',
		'lock' => 'lock',
		'login' => 'login',

		// Status
		'status_ok' => 'check_circle',
		'status_error' => 'cancel',
		'alert' => 'error',
		'warning' => 'warning',
		'info' => 'info',
		'accept' => 'check',
		'remove' => 'close',
		'bug' => 'bug_report',
		'gear' => 'settings',
		'comment' => 'chat',

		// Data
		'datasheet' => 'description',
		'chart' => 'bar_chart',
		'checklist' => 'checklist',
		'versions' => 'history',
		'column_width' => 'swap_horiz',
		'align' => 'tune',

		// Links
		'link' => 'link',
		'link_out' => 'open_in_new',
		'link_none' => 'link_off',

		// Misc
		'admin_wrench' => 'build',
		'menubar' => 'menu',
		'help' => 'help',
		'date' => 'calendar_today',
		'datetime' => 'event',
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
		return "https://cdn.jsdelivr.net/npm/@material-design-icons/svg@latest/outlined/{$name}.svg";
	}

	protected static function buildHtml(string $name, string $alt, string $size): string
	{
		$sizeVal = static::$sizes[$size] ?? 20;
		$title = htmlspecialchars($alt, ENT_QUOTES);

		return "<img class=\"ico material-icon\" src=\"https://cdn.jsdelivr.net/npm/@material-design-icons/svg@latest/outlined/{$name}.svg\" width=\"{$sizeVal}\" height=\"{$sizeVal}\" alt=\"{$title}\" title=\"{$title}\" />";
	}
}
