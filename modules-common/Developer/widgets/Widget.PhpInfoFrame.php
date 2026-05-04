<?php

declare(strict_types=1);

class WidgetPhpInfoFrame extends AbstractWidget
{
	public const string ID = 'phpinfo_frame';

	/**
	 * @return array<string, string>
	 */
	public static function buildStrings(): array
	{
		return [
			'widget.phpinfo_frame.title' => t('widget.phpinfo_frame.title'),
			'widget.phpinfo_frame.open_raw' => t('widget.phpinfo_frame.open_raw'),
		];
	}

	public static function getName(): string
	{
		return t('widget.' . self::ID . '.name');
	}

	public static function getDescription(): string
	{
		return t('widget.' . self::ID . '.description');
	}

	public static function getListVisibility(): bool
	{
		return Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER);
	}

	public static function getDefaultPathForCreation(): array
	{
		return [
			'path' => '/admin/developer/',
			'resource_name' => 'phpinfo.html',
			'layout' => 'admin_default',
		];
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		return $this->createComponentTree('phpInfoFrame', [
			'src' => Url::getUrl('system.phpinfo'),
		], strings: self::buildStrings());
	}

	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER);
	}
}
