<?php

class WidgetJsTree extends AbstractWidget
{
	public const string ID = 'js_tree';
	public const bool VISIBILITY = true;

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
		return Roles::hasRole(RoleList::ROLE_SYSTEM_ADMINISTRATOR);
	}

	public static function getDefaultPathForCreation(): array
	{
		return [
			'path' => '/admin/',
			'resource_name' => 'jstree.html',
			'layout' => 'admin_default',
		];
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		if (Request::_GET('jstree_type')) {
			$jstree_type = Request::_GET('jstree_type');
		} elseif ($connection->getExtraparam('jstree_type')) {
			$jstree_type = $connection->getExtraparam('jstree_type');
		} else {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'message' => t('cms.tree.missing_type'),
			]);
		}

		return $this->createComponentTree('jsTree.' . $jstree_type, [
			'jstree_id' => 'jstree_' . $connection->getConnectionId(),
			'jstree_type' => $jstree_type,
		]);
	}

	public function getWidgetEditCommands(WidgetConnection $connection): WidgetEditCommand
	{
		$settings = new WidgetEditCommand();
		$settings->title = t('widget.' . self::ID . '.settings');
		$settings->url = Url::getUrl('resource.view', [
			'resource' => 'form.html',
			'form_id' => 'jsTreeSettings',
			'item_id' => $connection->connection_id,
		]);
		$settings->icon = IconNames::EDIT;

		return $settings;
	}
	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return true;
	}
}
