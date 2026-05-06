<?php

/**
 * Heroicons - by the makers of Tailwind CSS
 * https://heroicons.com/.
 */
class IconLibraryHeroicons extends AbstractIconLibrary
{
	protected static array $iconMap = [
		// Actions
		'add' => 'plus',
		'edit' => 'pencil',
		'delete' => 'trash',
		'trash' => 'trash',
		'view' => 'eye',
		'look' => 'arrow-top-right-on-square',
		'choose' => 'cog-6-tooth',

		// Navigation
		'dropdown' => 'chevron-down',
		'widget_up' => 'arrow-up',
		'widget_down' => 'arrow-down',

		// File operations
		'upload' => 'arrow-up-tray',
		'download' => 'arrow-down-tray',

		// Form
		'form_save' => 'document-check',
		'form_cancel' => 'x-mark',
		'form_help' => 'question-mark-circle',
		'form_error' => 'exclamation-circle',

		// Content
		'widget_add' => 'document-plus',
		'widget_remove' => 'trash',
		'widget_insert' => 'document-plus',
		'webpage_add' => 'document-plus',
		'content_add' => 'document-plus',
		'plus' => 'plus',

		// Folders
		'folder' => 'folder',
		'folder_add' => 'folder-plus',

		// Users
		'user' => 'user',
		'usergroup' => 'user-group',
		'usergroup_add' => 'user-plus',
		'system_usergroup' => 'user-group',
		'people' => 'users',
		'roles' => 'shield-check',
		'role' => 'shield-check',
		'lock' => 'lock-closed',
		'login' => 'arrow-right-on-rectangle',

		// Status
		'status_ok' => 'check-circle',
		'status_error' => 'x-circle',
		'alert' => 'exclamation-circle',
		'warning' => 'exclamation-triangle',
		'info' => 'information-circle',
		'accept' => 'check',
		'remove' => 'x-mark',
		'bug' => 'bug-ant',
		'gear' => 'cog-6-tooth',
		'comment' => 'chat-bubble-left',

		// Data
		'datasheet' => 'document-text',
		'chart' => 'chart-bar',
		'checklist' => 'clipboard-document-check',
		'versions' => 'clock',
		'column_width' => 'arrows-right-left',
		'content_formatting' => 'bars-3-bottom-left',
		'align' => 'adjustments-horizontal',

		// Links
		'link' => 'link',
		'link_out' => 'arrow-top-right-on-square',
		'link_none' => 'link',

		// Misc
		'admin_wrench' => 'wrench',
		'home' => 'home',
		'logout' => 'arrow-left-on-rectangle',
		'menubar' => 'bars-3',
		'help' => 'question-mark-circle',
		'date' => 'calendar',
		'datetime' => 'calendar-days',
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
		return "https://cdn.jsdelivr.net/npm/heroicons@latest/24/outline/{$name}.svg";
	}

	protected static function buildHtml(string $name, string $alt, string $size): string
	{
		$sizeVal = static::$sizes[$size] ?? 20;
		$title = htmlspecialchars($alt, ENT_QUOTES);

		return "<img class=\"ico heroicon\" src=\"https://cdn.jsdelivr.net/npm/heroicons@latest/24/outline/{$name}.svg\" width=\"{$sizeVal}\" height=\"{$sizeVal}\" alt=\"{$title}\" title=\"{$title}\" />";
	}
}
