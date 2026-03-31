<?php

class WidgetUsergroupList extends AbstractWidget
{
	public const string ID = 'usergroup_list';

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
			'path' => '/admin/usergroups/',
			'resource_name' => 'index.html',
			'layout' => 'admin_default',
		];
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		return $this->createComponentTree('jsTree.usergroups', [
			'jstree_id' => 'jstree_' . $connection->connection_id,
			'jstree_type' => 'usergroups',
		], strings: JsTreeApiService::buildUsergroupsStrings());
	}
	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return Roles::hasRole(RoleList::ROLE_USERGROUPS_ADMIN);
	}
}
