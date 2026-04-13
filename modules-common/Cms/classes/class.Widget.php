<?php

/**
 * @phpstan-type RenderTreeNode array{
 *     type: string,
 *     component: string,
 *     props: array<string, mixed>,
 *     slots: array<string, list<array<string, mixed>>>,
 *     strings?: array<string, mixed>,
 *     meta?: array<string, mixed>
 * }
 * @phpstan-type WidgetMetadata array{
 *     type_name: string,
 *     name: string,
 *     description: string
 * }
 * @phpstan-type NormalizedEditCommand array{
 *     title: string,
 *     url: string,
 *     icon: string|null
 * }
 */
class Widget extends WidgetList
{
	public static function factory(string $widget_name): AbstractWidget
	{
		$widget_classname = 'Widget' . ucwords($widget_name);

		try {
			if (AutoloaderFromGeneratedMap::autoloaderClassExists($widget_classname)) {
				$widgetInstance = new $widget_classname();
			} else {
				$widgetInstance = new Widget_WidgetError($widget_classname);
			}
		} catch (Exception) {
			$widgetInstance = new Widget_WidgetError();
		}

		if (!$widgetInstance instanceof AbstractWidget) {
			Kernel::abort("Widget <i>$widget_name</i> must implement <b>AbstractWidget</b>!");
		}

		return $widgetInstance;
	}

	public static function checkWidgetExists(string $widget_name): bool
	{
		return in_array($widget_name, self::$_widgetNames);
	}

	public static function getWidgetDescription(string $widget_name): string
	{
		if (!self::checkWidgetExists($widget_name)) {
			return '';
		}

		$st = self::factory($widget_name);

		return $st->getDescription();
	}

	public static function getWidgetName(string $widget_name): string
	{
		if (!self::checkWidgetExists($widget_name)) {
			return '';
		}

		$st = self::factory($widget_name);

		return $st->getName();
	}

	public static function getWidgetConnectionId($page_id, $slot_name, $widget_name): ?int
	{
		$query = "SELECT connection_id FROM widget_connections WHERE page_id=? AND slot_name=? AND widget_name=? LIMIT 1";
		$stmt = Db::instance()->prepare($query);
		$stmt->execute([
			$page_id,
			$slot_name,
			$widget_name,
		]);

		$rs = $stmt->fetch(PDO::FETCH_ASSOC);

		return $rs['connection_id'] ?? null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function getConnectionData(int $connection_id): ?array
	{
		return DbHelper::selectOne('widget_connections', ['connection_id' => $connection_id]);
	}

	public static function assignWidgetToWebpage(int $page_id, string $slot_name, string $widget_name, ?int $seq = null, bool $multiple = true): false|int
	{
		$widgetClassname = 'Widget' . ucwords($widget_name);

		if (!class_exists($widgetClassname) || !is_subclass_of($widgetClassname, 'AbstractWidget')) {
			SystemMessages::_error("Requested widget class '{$widgetClassname}' does not exist or does not implement AbstractWidget.");

			return false;
		}

		try {
			$is_catcher = $widgetClassname::isCatcher();
		} catch (Exception) {
			SystemMessages::_error("Requested widget doesn't have a class: {$widget_name}");

			return false;
		}

		$additionalWidgets = $widgetClassname::getAdditionalWidgets();

		foreach ($additionalWidgets as $insertableWidget) {
			$additional_id = self::assignWidgetToWebpage($page_id, $slot_name, $insertableWidget, $seq, $multiple);
			$seq ??= Widget::getConnectionData($additional_id)['seq'];
		}

		$connection_id = self::getWidgetConnectionId($page_id, $slot_name, $widget_name);

		if (is_null($connection_id) || $multiple) {
			$savedata = [
				'page_id' => $page_id,
				'slot_name' => $slot_name,
				'widget_name' => $widget_name,
			];

			if (!is_null($seq)) {
				// Move all widgets after this one up by one
				$query = "UPDATE widget_connections SET seq=seq+1 WHERE page_id=? AND slot_name=? AND seq>=?";
				$stmt = Db::instance()->prepare($query);
				$stmt->execute([
					$page_id,
					$slot_name,
					$seq,
				]);

				Cache::flush();

				$savedata['seq'] = $seq;
			}

			$connection_id = DbHelper::insertHelper('widget_connections', $savedata);

			if ($is_catcher === true) {
				if (ResourceTreeHandler::checkParentHasCatcherPage($page_id)) {
					SystemMessages::_error("More than one catcher page... widget: {$widget_name}");

					return false;
				}

				ResourceTreeHandler::setAsCatcherPage($page_id);
			}

			return $connection_id;
		}

		return false;
	}

	public static function removeWidgetFromWebpage(int $connection_id): bool
	{
		$connection_data = self::getConnectionData($connection_id);

		if (!isset($connection_data['seq'])) {
			return false;
		}

		$seq = $connection_data['seq'];
		$page_id = (int) $connection_data['page_id'];
		$widget_name = $connection_data['widget_name'];
		$slot_name = (string) ($connection_data['slot_name'] ?? '');

		// Check if the widget being removed is a catcher widget
		$widgetClassname = 'Widget' . ucwords($widget_name);

		if (class_exists($widgetClassname) && is_subclass_of($widgetClassname, 'AbstractWidget')) {
			try {
				if ($widgetClassname::isCatcher()) {
					ResourceTreeHandler::clearCatcherPage($page_id);
				}
			} catch (Exception) {
				// Widget doesn't have isCatcher method, ignore
			}
		}

		AttributeHandler::deleteAttributes(new AttributeResourceIdentifier(ResourceNames::WIDGET_CONNECTION, (string) $connection_id));

		$update_seq_query = "
	            UPDATE widget_connections
	            SET
	                seq = seq - 1
	            WHERE page_id = ?
	              AND slot_name = ?
	              AND seq > ?";

		$update_stmt = Db::instance()->prepare($update_seq_query);
		$update_stmt->execute([$page_id, $slot_name, $seq]);

		Cache::flush();

		return DbHelper::deleteHelper('widget_connections', $connection_id);
	}

	/**
	 * Wrap an already-built widget subtree with edit-mode chrome.
	 *
	 * @param RenderTreeNode $widget_tree
	 * @return RenderTreeNode
	 */
	public static function buildEditTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $widget_tree): array
	{
		$templateName = match ($connection->getWidget()->editorPosition()) {
			'before' => 'widgetEditBefore',
			'after' => 'widgetEditAfter',
			default => 'widgetEdit',
		};

		$widget_edit_commands = self::normalizeEditCommands($connection->getWidget()->getEditableCommands($connection));

		$props = [
			'widget_edit_commands' => $widget_edit_commands,
		];

		if ($connection->getWidget()->isWrapperStylingEnabled()) {
			$props['style'] = '';
			$props['class'] = '';
			$props['settings'] = [];
		} else {
			$props['style'] = $connection->getStyle();
			$props['class'] = Themes::getClass($tree_build_context, $connection->connection_id);
			$props['settings'] = WidgetSettings::getSettings($connection->connection_id);
		}

		return SduiNode::create(
			component: $templateName,
			props: $props,
			slots: [
				'edit_bar' => [
					SduiNode::create('editBar.common', [
						'widget_edit_commands' => $widget_edit_commands,
					], strings: self::buildEditBarStrings()),
				],
				'widget_content' => [$widget_tree],
			],
			type: SduiNode::TYPE_SUB,
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function buildEditBarStrings(): array
	{
		return [
			'cms.widget_connection_params.title' => t('cms.widget_connection_params.title'),
			'form.widget_connection_settings.title' => t('form.widget_connection_settings.title'),
			'common.move_up' => t('common.move_up'),
			'common.move_down' => t('common.move_down'),
			'cms.widget_connection.remove_from_webpage' => t('cms.widget_connection.remove_from_webpage'),
		];
	}

	public static function getVisibleWidgetList(): array
	{
		$return = [];

		foreach (self::$_widgetNames as $widgetName) {
			$widget = Widget::factory($widgetName);

			if ($widget->getListVisibility()) {
				$return[] = $widget;
			}
		}

		return $return;
	}

	/**
	 * @return list<WidgetMetadata>
	 */
	public static function getVisibleWidgetMetadataList(): array
	{
		$return = [];

		foreach (self::getVisibleWidgetList() as $widget) {
			$return[] = [
				'type_name' => $widget->getTypeName(),
				'name' => $widget->getName(),
				'description' => $widget->getDescription(),
			];
		}

		return $return;
	}

	/**
	 * @param array<WidgetEditCommand> $commands
	 * @return list<NormalizedEditCommand>
	 */
	public static function normalizeEditCommands(array $commands): array
	{
		$return = [];

		foreach ($commands as $command) {
			$return[] = [
				'title' => $command->title,
				'url' => $command->url,
				'icon' => $command->icon?->value,
			];
		}

		return $return;
	}
}
