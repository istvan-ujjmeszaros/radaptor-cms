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
 * @phpstan-type SlotTrees array<string, list<array<string, mixed>>>
 */
abstract class AbstractLayoutType implements iLayoutType, iListable
{
	public function initialize(iTreeBuildContext $tree_build_context): void
	{
	}

	/**
	 * Get the theme name this layout uses.
	 *
	 * Override in layout classes to explicitly declare which theme this layout uses.
	 * Returns null by default, which means theme resolution will use DB mapping or config default.
	 */
	public static function getThemeName(): ?string
	{
		return null;
	}

	/**
	 * @param array<string, mixed> $props
	 * @param array<string, mixed> $strings
	 * @param SlotTrees $slots
	 * @param array<string, mixed> $meta
	 * @return RenderTreeNode
	 */
	protected function createLayoutTree(string $component_name, array $props = [], array $strings = [], array $slots = [], array $meta = []): array
	{
		return SduiNode::create(
			component: $component_name,
			props: $props,
			slots: $slots,
			type: SduiNode::TYPE_SUB,
			meta: $meta,
			strings: $strings,
		);
	}
}
