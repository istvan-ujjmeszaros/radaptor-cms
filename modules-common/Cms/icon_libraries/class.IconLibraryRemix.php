<?php

/**
 * Remix Icon - Open source icon library
 * https://remixicon.com/.
 */
class IconLibraryRemix extends AbstractIconLibrary
{
	/** @var array<string, string> Maps semantic names to "Category/icon-name" */
	protected static array $iconMap = [
		// Actions
		'add' => 'System/add-line',
		'edit' => 'Design/pencil-line',
		'delete' => 'System/delete-bin-line',
		'trash' => 'System/delete-bin-line',
		'view' => 'System/eye-line',
		'look' => 'System/external-link-line',
		'choose' => 'System/settings-3-line',

		// Navigation
		'dropdown' => 'Arrows/arrow-down-s-line',
		'widget_up' => 'Arrows/arrow-up-line',
		'widget_down' => 'Arrows/arrow-down-line',

		// File operations
		'upload' => 'System/upload-line',
		'download' => 'System/download-line',

		// Form
		'form_save' => 'Device/save-line',
		'form_cancel' => 'System/close-line',
		'form_help' => 'System/question-line',
		'form_error' => 'System/error-warning-line',

		// Content
		'widget_add' => 'Document/file-add-line',
		'widget_remove' => 'System/delete-bin-line',
		'widget_insert' => 'Document/file-add-line',
		'webpage_add' => 'Document/file-add-line',
		'content_add' => 'Document/file-add-line',
		'plus' => 'System/add-line',

		// Folders
		'folder' => 'Document/folder-line',
		'folder_add' => 'Document/folder-add-line',

		// Users
		'user' => 'User & Faces/user-line',
		'usergroup' => 'User & Faces/group-line',
		'usergroup_add' => 'User & Faces/user-add-line',
		'system_usergroup' => 'User & Faces/team-line',
		'people' => 'User & Faces/team-line',
		'roles' => 'System/shield-check-line',
		'role' => 'System/shield-check-line',
		'lock' => 'System/lock-line',
		'login' => 'System/login-box-line',

		// Status
		'status_ok' => 'System/checkbox-circle-line',
		'status_error' => 'System/close-circle-line',
		'alert' => 'System/error-warning-line',
		'warning' => 'System/alert-line',
		'info' => 'System/information-line',
		'accept' => 'System/check-line',
		'remove' => 'System/close-line',
		'bug' => 'Development/bug-line',
		'gear' => 'System/settings-3-line',
		'comment' => 'Communication/chat-1-line',

		// Data
		'datasheet' => 'Document/file-text-line',
		'chart' => 'Business/bar-chart-line',
		'checklist' => 'Document/todo-line',
		'versions' => 'System/history-line',
		'column_width' => 'Arrows/expand-horizontal-s-line',
		'align' => 'Media/equalizer-line',

		// Links
		'link' => 'Editor/link',
		'link_out' => 'System/external-link-line',
		'link_none' => 'Editor/link-unlink',

		// Misc
		'admin_wrench' => 'System/settings-5-line',
		'menubar' => 'System/menu-line',
		'help' => 'System/question-line',
		'date' => 'Business/calendar-line',
		'datetime' => 'Business/calendar-event-line',
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
		return "https://cdn.jsdelivr.net/npm/remixicon@latest/icons/{$name}.svg";
	}

	protected static function buildHtml(string $name, string $alt, string $size): string
	{
		$sizeVal = static::$sizes[$size] ?? 20;
		$title = htmlspecialchars($alt, ENT_QUOTES);

		return "<img class=\"ico remix-icon\" src=\"https://cdn.jsdelivr.net/npm/remixicon@latest/icons/{$name}.svg\" width=\"{$sizeVal}\" height=\"{$sizeVal}\" alt=\"{$title}\" title=\"{$title}\" />";
	}
}
