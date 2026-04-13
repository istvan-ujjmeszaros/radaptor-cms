<?php

class WidgetAdminMenu extends AbstractWidget
{
	public const string ID = 'admin_menu';

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
			'path' => '/admin/components/adminmenu/',
			'resource_name' => 'index.html',
			'layout' => 'admin_default',
		];
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		return $this->createComponentTree('jsTree.adminMenu', [
			'jstree_id' => 'jstree_adminmenu_' . $connection->connection_id,
		], strings: JsTreeApiService::buildAdminMenuStrings());
	}
	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return true;
	}
}
