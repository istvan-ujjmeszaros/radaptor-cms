<?php

class WidgetResourceAclSelector extends AbstractWidget
{
	public const string ID = 'resource_acl_selector';

	/**
	 * @return array<string, string>
	 */
	public static function buildStrings(): array
	{
		return [
			'common.delete' => t('common.delete'),
			'cms.resource_acl.title' => t('cms.resource_acl.title'),
			'cms.resource_acl.inherit_label' => t('cms.resource_acl.inherit_label'),
			'cms.resource_acl.inherit_help' => t('cms.resource_acl.inherit_help'),
			'cms.resource_acl.specific_help' => t('cms.resource_acl.specific_help'),
			'cms.resource_acl.inherited_title' => t('cms.resource_acl.inherited_title'),
			'cms.resource_acl.subject' => t('cms.resource_acl.subject'),
			'cms.resource_acl.permission.list' => t('cms.resource_acl.permission.list'),
			'cms.resource_acl.permission.view' => t('cms.resource_acl.permission.view'),
			'cms.resource_acl.permission.create' => t('cms.resource_acl.permission.create'),
			'cms.resource_acl.permission.edit' => t('cms.resource_acl.permission.edit'),
			'cms.resource_acl.permission.delete' => t('cms.resource_acl.permission.delete'),
			'cms.resource_acl.specific_title' => t('cms.resource_acl.specific_title'),
			'cms.resource_acl.new_assignment' => t('cms.resource_acl.new_assignment'),
			'cms.resource_acl.search_placeholder' => t('cms.resource_acl.search_placeholder'),
			'cms.resource_acl.assign' => t('cms.resource_acl.assign'),
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
			'path' => '/admin/resources/acl/',
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

		if (!isset($extra_params['resource'])) {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'message' => t('cms.resource_acl.error_missing_resource_id'),
			]);
		}

		$resourcedata = ResourceTreeHandler::getResourceTreeEntryDataById($extra_params['resource']);

		return $this->createComponentTree('resourceAclSelector', [
			'connection_id' => 'id' . $connection->getConnectionIdAsString(),
			'title' => $resourcedata['path'] . $resourcedata['resource_name'],
			'selectorId' => $extra_params['resource'],
			'is_inheriting_acl' => $resourcedata['is_inheriting_acl'],
		], strings: self::buildStrings());
	}
	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return Roles::hasRole(RoleList::ROLE_ACL_VIEWER) || Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER);
	}
}
