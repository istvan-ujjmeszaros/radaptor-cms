<?php

class WidgetUserList extends AbstractWidget
{
	public const string ID = 'user_list';

	/**
	 * @return array<string, string>
	 */
	public static function buildStrings(): array
	{
		return [
			'user.action.edit' => t('user.action.edit'),
			'user.action.datasheet' => t('user.action.datasheet'),
			'user.action.roles' => t('user.action.roles'),
			'user.action.usergroups' => t('user.action.usergroups'),
			'user.list.title' => t('user.list.title'),
			'user.list.new' => t('user.list.new'),
			'common.id' => t('common.id'),
			'user.col.username' => t('user.col.username'),
			'user.col.actions' => t('user.col.actions'),
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
			'path' => '/admin/users/',
			'resource_name' => 'index.html',
			'layout' => 'admin_default',
		];
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		return $this->createComponentTree('userList', [], strings: self::buildStrings());
	}
	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return Roles::hasRole(RoleList::ROLE_USERS_ADMIN);
	}
}
