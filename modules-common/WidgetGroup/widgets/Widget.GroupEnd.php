<?php

class WidgetGroupEnd extends AbstractWidget
{
	public const string ID = 'group_end';

	public static function editorPosition(): string
	{
		return 'before';
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
		// no manual insert - inserted automatically by WidgetGroupBeginning
		return false;
	}

	public static function getDefaultPathForCreation(): array
	{
		return [
			'path' => '/admin/components/widget-group/',
			'resource_name' => 'widget-group-end.html',
			'layout' => 'admin_default',
		];
	}

	public static function isWrapperStylingEnabled(): bool
	{
		return false;
	}

	public static function defaultEditCommandsAreEnabled(): bool
	{
		return false;
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		return $this->createComponentTree(TemplateList::WIDGETGROUPEND);
	}

	public function getEditableCommands(WidgetConnection $connection): array
	{
		$return = [];

		if (
			!$connection->isFirst()
			&& (!$connection->previous()->getWidget() instanceof WidgetGroupBeginning)
			&& (!$connection->previous()->getWidget() instanceof WidgetGroupEnd)
		) {
			$move_up = new WidgetEditCommand();
			$move_up->title = t('common.move_up');
			$move_up->icon = IconNames::WIDGET_UP;
			$move_up->url = Url::getUrl('widgetConnection.swap', [
				'item_id' => $connection->connection_id,
				'swap_id' => $connection->previous()->connection_id,
			]);

			if (Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN)) {
				$return[] = $move_up;
			}
		}

		if (
			!$connection->isLast()
			&& (!$connection->next()->getWidget() instanceof WidgetGroupBeginning)
			&& (!$connection->next()->getWidget() instanceof WidgetGroupEnd)
		) {
			$move_down = new WidgetEditCommand();
			$move_down->title = t('common.move_down');
			$move_down->icon = IconNames::WIDGET_DOWN;
			$move_down->url = Url::getUrl('widgetConnection.swap', [
				'item_id' => $connection->connection_id,
				'swap_id' => $connection->next()->connection_id,
			]);

			if (Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN)) {
				$return[] = $move_down;
			}
		}

		return $return;
	}
	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return true;
	}
}
