<?php

class WidgetGroupBeginning extends AbstractWidget
{
	public const string ID = 'group_beginning';

	public static function editorPosition(): string
	{
		return 'after';
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
			'path' => '/admin/components/widget-group/',
			'resource_name' => 'widget-group-beginning.html',
			'layout' => 'admin_default',
		];
	}

	public static function getAdditionalWidgets(): array
	{
		return ['GroupEnd'];
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
		$class = Themes::getClass($tree_build_context, $connection->connection_id);

		if ($tree_build_context->isEditable()) {
			$class .= ' block-editable';
		}

		return $this->createComponentTree(TemplateList::WIDGETGROUPBEGINNING, [
			'class' => $class,
		]);
	}

	public function getEditableCommands(WidgetConnection $connection): array
	{
		$return = [];

		$width = new WidgetEditCommand();
		$width->title = t('common.width');
		$width->icon = IconNames::COLUMN_WIDTH;
		$width->url = Form::getSeoUrl(FormList::WIDGETCONNECTIONSETTINGS, $connection->connection_id);

		if (Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN)) {
			$return[] = $width;
		}

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

		$remove_block = new WidgetEditCommand();
		$remove_block->title = t('cms.widget_group.remove_block');
		$remove_block->icon = IconNames::WIDGET_REMOVE;
		$remove_block->url = Url::getUrl('widgetGroup.removeFromWebpage', ['item_id' => $connection->connection_id]);

		if (Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN)) {
			$return[] = $remove_block;
		}

		return $return;
	}
	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return true;
	}
}
