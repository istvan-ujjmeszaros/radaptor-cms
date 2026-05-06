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
interface iWidget
{
	/**
	 * Build the widget subtree for the current render pass.
	 *
	 * @param array<string, mixed> $build_context
	 * @return RenderTreeNode
	 */
	public function buildTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array;

	public static function editorPosition(): string;

	/**
	 * Describe the default resource path used when creating a new widget-backed page.
	 *
	 * @return DefaultPathDefinition
	 */
	public static function getDefaultPathForCreation(): array;

	public static function isCatcher(): bool;

	/**
	 * Get additional widgets that can be inserted when this widget is selected.
	 *
	 * @return list<string>
	 */
	public static function getAdditionalWidgets(): array;

	public static function defaultEditCommandsAreEnabled(): bool;

	public static function isWrapperStylingEnabled(): bool;

	/**
	 * Authorization gate for widget rendering.
	 *
	 * Return true to allow rendering, false to return getAccessDeniedMessage().
	 * Called by AbstractWidget::buildTree() before buildAuthorizedTree().
	 */
	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool;
}
