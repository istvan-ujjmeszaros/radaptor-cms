<?php

class IconLibraryCdnPng extends AbstractIconLibrary
{
	protected static array $iconMap = [
		'dropdown' => 'downarrow3',
		'upload' => 'up',
		'download' => 'down',
		'widget_up' => 'section-up',
		'widget_down' => 'section-down',
		'widget_add' => 'section-add',
		'widget_remove' => 'section-remove',
		'widget_insert' => 'section-insert-here',
		'webpage_add' => 'webpage-plus',
		'content_add' => 'edit-add',
		'folder_add' => 'folder-plus',
		'usergroup_add' => 'usergroup-add',
		'system_usergroup' => 'systemusergroup',
		'status_ok' => 'block-ok',
		'status_error' => 'block-error',
		'warning' => 'exclamation',
		'remove' => 'cross',
		'bug' => 'bug-red',
		'gear' => 'gear',
		'datasheet' => 'sheet',
		'align' => 'align4',
		'column_width' => 'column-width',
		'content_formatting' => 'align4',
		'people' => 'people1',
		'admin_wrench' => 'admin_menu_wrench3',
		'home' => 'home',
		'logout' => 'logout',
		'form_help' => 'form_help4',
		'form_error' => 'form_error',
		'help' => 'help1',
		'link_out' => 'link-out',
		'link_none' => 'link-none',
	];

	/** @var array<string, string> */
	protected static array $sizes = [
		'small' => '16x16',
		'default' => '16x16',
		'medium' => '24x24',
		'large' => '32x32',
	];

	protected static function buildPath(string $name, string $size): string
	{
		$sizeVal = static::$sizes[$size] ?? '16x16';
		$sizePath = ($sizeVal === '16x16') ? '' : $sizeVal . '/';

		return Config::PATH_ICONS->value() . $sizePath . $name . Config::PATH_ICONS_EXTENSION->value();
	}

	protected static function buildHtml(string $name, string $alt, string $size): string
	{
		$path = static::buildPath($name, $size);
		$title = htmlspecialchars($alt, ENT_QUOTES);

		return "<img class=\"ico\" src=\"{$path}\" style=\"border:0;font-size:1px;\" alt=\"{$title}\" title=\"{$title}\" />";
	}
}
