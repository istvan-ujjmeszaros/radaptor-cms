<?php

class WidgetResourceTree extends AbstractWidget
{
	public const string ID = 'resource_tree';

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
			'path' => '/admin/resources/',
			'resource_name' => 'index.html',
			'layout' => 'admin_default',
		];
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		// Use jsTree 3.x template for RadaptorPortalAdmin theme
		$themeName = Themes::getThemeNameForUser($tree_build_context->getLayoutTypeName());

		if ($themeName === 'RadaptorPortalAdmin') {
			$template_name = 'resourceTree.jstree3';
		} else {
			// Fallback to legacy jsTree template for other themes
			$template_name = 'jsTree.resources';
		}

		return $this->createComponentTree($template_name, [
			'jstree_id' => 'jstree_resources_' . $connection->connection_id,
		], strings: JsTreeApiService::buildResourcesStrings());
	}
	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return true;
	}
}
