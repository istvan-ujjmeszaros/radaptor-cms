<?php

/**
 * Interface for Layout Components.
 *
 * Layout Components are structural UI elements (menus, headers, footers) that are
 * hardcoded directly into layout templates. Unlike widgets, they are not configurable
 * through the CMS admin interface.
 *
 * Key differences from widgets:
 * - Instantiated directly in layout templates via `new`
 * - No database configuration - purely code-driven
 * - Used for structural, non-content elements
 *
 * @see AbstractLayoutComponent Base implementation
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
interface iLayoutComponent
{
	/**
	 * Get the human-readable name of the component.
	 *
	 * Used for display in edit mode dropdowns and admin interfaces.
	 *
	 * @return string Component name (e.g., "Main Menu", "Admin Sidebar")
	 */
	public static function getLayoutComponentName(): string;

	/**
	 * Get a description of what the component does.
	 *
	 * @return string Brief description of the component's purpose
	 */
	public static function getLayoutComponentDescription(): string;

	/**
	 * Build the component tree.
	 *
	 * @return RenderTreeNode
	 */
	public function buildTree(): array;

	/**
	 * Get edit commands for admin edit mode.
	 *
	 * When administrators are in edit mode, these commands appear in a dropdown
	 * menu allowing quick access to related configuration pages.
	 *
	 * @return list<WidgetEditCommand> Array of edit commands (may be empty)
	 */
	public function getEditableCommands(): array;
}
