<?php

/**
 * @phpstan-type RenderTreeNode array{
 *     type: string,
 *     component: string,
 *     props: array<string, mixed>,
 *     contents: array<string, list<array<string, mixed>>>,
 *     strings?: array<string, mixed>,
 *     meta?: array<string, mixed>
 * }
 * @phpstan-type DefaultPathDefinition array{
 *     path?: string,
 *     resource_name?: string,
 *     layout?: string
 * }
 */
abstract class AbstractWidget implements iWidget, iListable
{
	public static function editorPosition(): string
	{
		return 'inside';
	}

	/**
	 * Retrieves an array of editable commands for a given widget connection.
	 *
	 * @param WidgetConnection $connection The widget connection for which to retrieve the commands.
	 * @return list<WidgetEditCommand> An array of WidgetEditCommand objects.
	 */
	public function getEditableCommands(WidgetConnection $connection): array
	{
		return [];
	}

	public function getAccessDeniedMessage(): string
	{
		return "You do not have permission to view the content...";
	}

	/**
	 * Authorization gate — must be implemented by every concrete widget class.
	 *
	 * Return true to allow rendering, false to return getAccessDeniedMessage().
	 * PHPStan will report a static error for any concrete subclass missing this method.
	 */
	abstract public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool;

	/**
	 * Template method: enforces canAccess() before tree building.
	 *
	 * This method is final — no widget can override it and bypass the access check.
	 *
	 * @param array<string, mixed> $build_context
	 * @return RenderTreeNode
	 */
	final public function buildTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		if (!$this->canAccess($tree_build_context, $connection)) {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'message' => $this->getAccessDeniedMessage(),
			]);
		}

		return $this->buildAuthorizedTree($tree_build_context, $connection, $build_context);
	}

	/**
	 * Widgets override this method to provide their subtree.
	 *
	 * @param array<string, mixed> $build_context
	 * @return RenderTreeNode
	 */
	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		$className = static::class;

		return $this->buildStatusTree([
			'severity' => 'error',
			'message' => 'Widget ' . $className . ' must implement the tree-building contract.',
		]);
	}

	public function getTypeName(): string
	{
		$className = static::class;

		if (mb_strpos($className, 'Widget') === 0) {
			return mb_substr($className, 6);
		}

		return '';
	}

	/**
	 * @return DefaultPathDefinition
	 */
	public static function getDefaultPathForCreation(): array
	{
		return [];
	}

	public static function isCatcher(): bool
	{
		return false;
	}

	/**
	 * Get additional widgets that can be inserted when this widget is selected.
	 *
	 * @return list<string>
	 */
	public static function getAdditionalWidgets(): array
	{
		return [];
	}

	public static function defaultEditCommandsAreEnabled(): bool
	{
		return true;
	}

	public static function isWrapperStylingEnabled(): bool
	{
		return true;
	}

	/**
	 * Build a preview-safe subtree for a single widget type.
	 *
	 * The helper is used by widget preview flows and never mocks the whole page by itself.
	 *
	 * @param string $widgetName The widget type name (e.g., 'CompanyList')
	 * @param array<string, mixed> $build_context
	 * @return RenderTreeNode
	 */
	public static function buildMockedTree(string $widgetName, iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		$widget = Widget::factory($widgetName);

		if (!$widget instanceof iMockable) {
			return $widget->buildStatusTree([
				'severity' => 'error',
				'message' => 'Widget ' . $widgetName . ' does not implement iMockable.',
			]);
		}

		$build_context['is_mock'] = true;

		return self::withRenderContext(
			$widget->buildMockTree($tree_build_context, $connection, $build_context),
			['is_mock' => true]
		);
	}

	/**
	 * @param array<string, mixed> $props
	 * @param array<string, list<array<string, mixed>>> $contents
	 * @param array<string, mixed> $strings
	 * @param array<string, mixed> $meta
	 * @return RenderTreeNode
	 */
	protected function createComponentTree(string $component_name, array $props = [], array $strings = [], array $contents = [], array $meta = []): array
	{
		return SduiNode::create(
			component: $component_name,
			props: $props,
			contents: $contents,
			type: SduiNode::TYPE_WIDGET,
			meta: $meta,
			strings: $strings,
		);
	}

	/**
	 * @param array<string, mixed> $props
	 * @return RenderTreeNode
	 */
	protected function buildStatusTree(array $props): array
	{
		return $this->createComponentTree('statusMessage', $props);
	}

	/**
	 * @param RenderTreeNode $tree
	 * @param array<string, mixed> $render_context
	 * @return RenderTreeNode
	 */
	protected static function withRenderContext(array $tree, array $render_context): array
	{
		$tree = SduiNode::normalize($tree);
		$tree['meta']['render_flags'] = array_replace(
			$tree['meta']['render_flags'] ?? [],
			$render_context
		);

		return $tree;
	}

	/**
	 * @param RenderTreeNode $tree
	 * @return RenderTreeNode
	 */
	protected static function withWidgetConnection(array $tree, ?WidgetConnection $connection): array
	{
		$tree = SduiNode::normalize($tree);
		$tree['meta']['widget_connection'] = WidgetConnection::toTreeMetadata($connection);

		return $tree;
	}
}
