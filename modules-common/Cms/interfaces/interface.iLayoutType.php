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
 * @phpstan-type SlotTrees array<string, list<array<string, mixed>>>
 */
interface iLayoutType
{
	public function initialize(iTreeBuildContext $tree_build_context): void;

	/**
	 * Build the root layout node for the page.
	 *
	 * @param SlotTrees $slot_trees
	 * @param array<string, mixed> $build_context
	 * @return RenderTreeNode
	 */
	public function buildTree(iTreeBuildContext $tree_build_context, array $slot_trees, array $build_context = []): array;

	/**
	 * Get the available slots in this layout.
	 *
	 * @return list<string> Slot names (e.g., ['content', 'sidebar'])
	 */
	public static function getSlots(): array;

	/**
	 * Get the theme name this layout uses.
	 *
	 * Override in layout classes to explicitly declare which theme this layout uses.
	 * This ensures template resolution matches the layout's CSS/JS libraries.
	 *
	 * @return string|null Theme name (e.g., 'Tracker', 'RadaptorPortalAdmin') or null to use default resolution
	 */
	public static function getThemeName(): ?string;
}
