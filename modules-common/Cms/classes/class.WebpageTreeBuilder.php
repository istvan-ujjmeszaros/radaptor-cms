<?php

declare(strict_types=1);

/**
 * @phpstan-type RenderTreeNode array{
 *     type: string,
 *     component: string,
 *     props: array<string, mixed>,
 *     contents: array<string, list<array<string, mixed>>>,
 *     strings?: array<string, mixed>,
 *     meta?: array<string, mixed>
 * }
 * @phpstan-type SlotTrees array<string, list<array<string, mixed>>>
 */
class WebpageTreeBuilder
{
	/**
	 * @param AbstractWebpageViewComposer $view
	 */
	public function __construct(private AbstractWebpageViewComposer $view)
	{
	}

	/**
	 * @param array<string, mixed> $build_context
	 * @return RenderTreeNode
	 */
	public function build(array $build_context = []): array
	{
		$this->view->getLayoutType()->initialize($this->view);
		$this->view->getTheme()?->initialize();

		$connections_by_slot = WidgetConnection::getWidgetsForPageGroupedBySlot((int)$this->view->getPageId());
		$slot_trees = [];
		$slot_names = array_unique(array_merge(
			$this->view->getLayoutType()::getSlots(),
			array_keys($connections_by_slot)
		));

		foreach ($slot_names as $slot_name) {
			$slot_trees[$slot_name] = $this->buildSlotTrees($slot_name, $connections_by_slot[$slot_name] ?? [], $build_context);
		}

		$layout_type = $this->view->getLayoutType();

		foreach ($layout_type::getSlots() as $slot_name) {
			$slot_trees[$slot_name] ??= [];
		}

		$page_tree = $layout_type->buildTree($this->view, $slot_trees, $build_context);
		$page_chrome_trees = $this->buildPageChromeTrees();

		if ($page_chrome_trees !== []) {
			$page_tree['contents']['page_chrome'] = array_merge(
				$page_tree['contents']['page_chrome'] ?? [],
				$page_chrome_trees
			);
		}

		return $page_tree;
	}

	/**
	 * @param string $slot_name
	 * @param array<WidgetConnection> $connections
	 * @param array<string, mixed> $build_context
	 * @return list<array<string, mixed>>
	 */
	private function buildSlotTrees(string $slot_name, array $connections, array $build_context): array
	{
		$slot_trees = [];

		foreach ($connections as $connection) {
			if ($this->view->isEditable()) {
				$slot_trees[] = $this->view->buildWidgetInserterTree($slot_name, $connection);
			}

			$widget_tree = $connection->buildTree($this->view, $build_context);

			if ($this->view->isEditable()) {
				$widget_tree = Widget::buildEditTree($this->view, $connection, $widget_tree);
			}

			$wrapped_tree = SduiNode::create(
				component: 'layoutElementWidgetHandler',
				props: [
					'use_customizable_wrapper' => $connection->getWidget()?->isWrapperStylingEnabled() ?? true,
					'style' => $connection->getStyle(),
					'class' => Themes::getClass($this->view, $connection->connection_id),
					'settings' => WidgetSettings::getSettings($connection->connection_id),
					'extraparams' => $connection->getExtraparams(),
				],
				contents: [
					'content' => [$widget_tree],
				],
				type: SduiNode::TYPE_SUB,
				meta: [
					'widget_connection' => WidgetConnection::toTreeMetadata($connection),
				],
			);

			if (!empty($build_context['is_mock'])) {
				$wrapped_tree['meta']['render_flags'] = [
					'is_mock' => true,
				];
			}

			$slot_trees[] = $wrapped_tree;
		}

		if ($this->view->isEditable()) {
			$slot_trees[] = $this->view->buildWidgetInserterTree($slot_name);
		}

		return $slot_trees;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function buildPageChromeTrees(): array
	{
		$page_chrome_trees = [];
		$admin_dropdown_tree = $this->view->buildAdminDropdownTree();

		if ($admin_dropdown_tree !== null) {
			$page_chrome_trees[] = $admin_dropdown_tree;
		}

		return $page_chrome_trees;
	}
}
