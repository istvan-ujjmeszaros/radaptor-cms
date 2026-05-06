<?php

/**
 * Base class for Layout Components.
 *
 * Layout Components are structural UI elements that are hardcoded into layouts
 * rather than being CMS-configurable like widgets.
 *
 * Instantiation is kept for registration side effects only. Rendering remains
 * explicit via `buildTree()`.
 *
 * ```php
 * // In a layout tree builder
 * $menu = new LayoutComponentMainMenu($composer);
 * $menu_tree = $menu->buildTree();
 * ```
 *
 * @see iLayoutComponent Interface definition
 * @see AbstractWidget For CMS-configurable content components
 *
 * @phpstan-type RenderTreeNode array{
 *     type: string,
 *     component: string,
 *     props: array<string, mixed>,
 *     contents: array<string, list<array<string, mixed>>>,
 *     strings?: array<string, mixed>,
 *     meta?: array<string, mixed>
 * }
 */
abstract class AbstractLayoutComponent implements iLayoutComponent
{
	/**
	 * Create the layout component.
	 *
	 * The constructor:
	 * 1. Stores the webpage composer and settings
	 * 2. Registers itself with the composer for edit mode support
	 *
	 * @param iTreeBuildContext $_webpage_composer Tree build context
	 * @param array<string, mixed> $_settings Optional configuration passed to the component
	 */
	public function __construct(
		public iTreeBuildContext $_webpage_composer,
		protected $_settings = []
	) {
		$this->_webpage_composer->registerRenderedLayoutComponent($this);
	}

	/**
	 * Get edit commands for admin edit mode.
	 *
	 * Override this method to provide edit links that appear when administrators
	 * are in edit mode. Common use: link to the page where the component's
	 * content is configured.
	 *
	 * @return list<WidgetEditCommand> Array of edit commands (empty by default)
	 */
	public function getEditableCommands(): array
	{
		return [];
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
		$stable_container_id = $this->getStableContainerId();

		if ($stable_container_id !== null) {
			$meta['stable_container_id'] ??= $stable_container_id;
		}

		return SduiNode::create(
			component: $component_name,
			props: $props,
			contents: $contents,
			type: SduiNode::TYPE_SUB,
			meta: $meta,
			strings: $strings,
		);
	}

	private function getStableContainerId(): ?string
	{
		if (!$this->_webpage_composer instanceof iWebpageComposer) {
			return null;
		}

		if (!$this->_webpage_composer->getLayoutType() instanceof iPartialNavigableLayout) {
			return null;
		}

		$component_id = defined(static::class . '::ID') ? (string)constant(static::class . '::ID') : '';

		return $component_id !== '' ? 'component-' . $component_id : null;
	}
}
