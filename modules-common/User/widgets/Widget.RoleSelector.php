<?php

class WidgetRoleSelector extends AbstractWidget
{
	public const string ID = 'role_selector';

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
			'path' => '/admin/users/role-selector/',
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

		if (isset($extra_params['user']) && isset($extra_params['usergroup'])) {
			Kernel::abort(t('user.error_parameter_mismatch'));
		}

		$props = [
			'jstree_id' => 'jstree_' . $connection->connection_id,
			'jstree_type' => 'roleSelector',
		];
		$strings = JsTreeApiService::buildRoleSelectorStrings();

		if (isset($extra_params['user'])) {
			if (!Roles::hasRole(RoleList::ROLE_USERS_ROLE_ADMIN)) {
				return $this->buildStatusTree([
					'severity' => 'warning',
					'message' => $this->getAccessDeniedMessage(),
				]);
			}

			$props['selectorType'] = 'user';
			$props['selectorId'] = $extra_params['user'];
			$userdata = User::getUserFromId($extra_params['user']);
			$props['title'] = $userdata['username'];
		} elseif (isset($extra_params['usergroup'])) {
			if (!Roles::hasRole(RoleList::ROLE_USERGROUPS_ROLE_ADMIN)) {
				return $this->buildStatusTree([
					'severity' => 'warning',
					'message' => $this->getAccessDeniedMessage(),
				]);
			}

			$props['selectorType'] = 'usergroup';
			$props['selectorId'] = $extra_params['usergroup'];
			$usergroupdata = Usergroups::getUsergroupValues($extra_params['usergroup']);
			$props['title'] = $usergroupdata['title'];
		} else {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'message' => t('user.error_no_selector_specified'),
			]);
		}

		return $this->createComponentTree('jsTree.roleSelector', $props, [], [], $strings);
	}
	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return Roles::hasRole(RoleList::ROLE_USERS_ROLE_ADMIN)
			|| Roles::hasRole(RoleList::ROLE_USERGROUPS_ROLE_ADMIN);
	}
}
