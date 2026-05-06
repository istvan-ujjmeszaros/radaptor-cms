<?php

/**
 * Font Awesome Free - The web's most popular icon set
 * https://fontawesome.com/.
 */
class IconLibraryFontAwesome extends AbstractIconLibrary
{
	protected static array $iconMap = [
		// Actions
		'add' => 'square-plus',
		'edit' => 'pen-to-square',
		'delete' => 'trash-can',
		'trash' => 'trash-can',
		'view' => 'eye',
		'look' => 'share-from-square',
		'choose' => 'gear',

		// Navigation
		'dropdown' => 'square-caret-down',
		'widget_up' => 'circle-up',
		'widget_down' => 'circle-down',

		// File operations
		'upload' => 'file-arrow-up',
		'download' => 'file-arrow-down',

		// Form
		'form_save' => 'floppy-disk',
		'form_cancel' => 'circle-xmark',
		'form_help' => 'circle-question',
		'form_error' => 'triangle-exclamation',

		// Content
		'widget_add' => 'square-plus',
		'widget_remove' => 'trash-can',
		'widget_insert' => 'square-plus',
		'webpage_add' => 'square-plus',
		'content_add' => 'square-plus',
		'plus' => 'square-plus',

		// Folders
		'folder' => 'folder',
		'folder_add' => 'folder-plus',

		// Users
		'user' => 'user',
		'usergroup' => 'users',
		'usergroup_add' => 'user-plus',
		'system_usergroup' => 'people-group',
		'people' => 'people-group',
		'roles' => 'shield',
		'role' => 'shield',
		'lock' => 'lock',
		'login' => 'right-to-bracket',

		// Status
		'status_ok' => 'circle-check',
		'status_error' => 'circle-xmark',
		'alert' => 'circle-exclamation',
		'warning' => 'triangle-exclamation',
		'info' => 'circle-info',
		'accept' => 'check',
		'remove' => 'xmark',
		'bug' => 'bug',
		'gear' => 'gear',
		'comment' => 'comment',

		// Data
		'datasheet' => 'file-lines',
		'chart' => 'chart-simple',
		'checklist' => 'list-check',
		'versions' => 'clock-rotate-left',
		'column_width' => 'hand-point-right',
		'content_formatting' => 'font',
		'align' => 'rectangle-list',

		// Links
		'link' => 'link',
		'link_out' => 'share-from-square',
		'link_none' => 'link-slash',

		// Misc
		'admin_wrench' => 'solid/gear',
		'home' => 'house',
		'logout' => 'right-from-bracket',
		'menubar' => 'bars',
		'help' => 'circle-question',
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
		// Support solid/ prefix for icons not in regular
		if (str_starts_with($name, 'solid/')) {
			$name = substr($name, 6);

			return "https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@latest/svgs/solid/{$name}.svg";
		}

		return "https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@latest/svgs/regular/{$name}.svg";
	}

	protected static function buildHtml(string $name, string $alt, string $size): string
	{
		$sizeVal = static::$sizes[$size] ?? 20;
		$title = htmlspecialchars($alt, ENT_QUOTES);

		// Support solid/ prefix for icons not in regular
		$style = 'regular';

		if (str_starts_with($name, 'solid/')) {
			$name = substr($name, 6);
			$style = 'solid';
		}

		return "<img class=\"ico fa-icon\" src=\"https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@latest/svgs/{$style}/{$name}.svg\" width=\"{$sizeVal}\" height=\"{$sizeVal}\" alt=\"{$title}\" title=\"{$title}\" />";
	}
}
