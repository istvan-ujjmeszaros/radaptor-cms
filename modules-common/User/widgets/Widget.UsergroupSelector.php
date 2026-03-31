<?php

class WidgetUsergroupSelector extends AbstractWidget
{
	public const string ID = 'usergroup_selector';

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
			'path' => '/admin/users/usergroup-selector/',
			'resource_name' => 'index.html',
			'layout' => 'admin_default',
		];
	}

	public static function isCatcher(): bool
	{
		return true;
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		$extra_params = Url::getExtraParams($tree_build_context);
		$extra_params = $extra_params['paired'];

		if (!isset($extra_params['user'])) {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'message' => t('user.error_missing_user_id'),
			]);
		}

		$userdata = User::getUserFromId($extra_params['user']);

		return $this->createComponentTree('jsTree.usergroupSelector', [
			'jstree_id' => 'jstree_' . $connection->connection_id,
			'jstree_type' => 'usergroupSelector',
			'title' => $userdata['username'],
			'selectorId' => $extra_params['user'],
		], strings: JsTreeApiService::buildUsergroupSelectorStrings());
	}
	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return Roles::hasRole(RoleList::ROLE_USERS_ADMIN);
	}
}
