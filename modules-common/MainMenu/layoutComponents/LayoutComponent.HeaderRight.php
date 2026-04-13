<?php

class LayoutComponentHeaderRight extends AbstractLayoutComponent
{
	public const string ID = 'header_right';

	public function buildTree(): array
	{
		return $this->createComponentTree('headerRight', [
			'currentUrl' => Url::getCurrentUrl(),
		], strings: self::buildStrings());
	}

	/**
	 * @return array<string, string>
	 */
	public static function buildStrings(): array
	{
		return [
			'admin.menu.home' => t('admin.menu.home'),
		];
	}

	public static function getLayoutComponentName(): string
	{
		return t('layout.' . self::ID . '.name');
	}

	public static function getLayoutComponentDescription(): string
	{
		return t('layout.' . self::ID . '.description');
	}
}
