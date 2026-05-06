<?php

declare(strict_types=1);

final class JsTreeApiService
{
	public const string TEMPLATE_JSTREE_3 = 'jstree_3';
	public const string TEMPLATE_JSTREE_1 = 'jstree_1';

	public const string TYPE_ADMINMENU = 'adminmenu';
	public const string TYPE_MAINMENU = 'mainmenu';
	public const string TYPE_RESOURCES = 'resources';
	public const string TYPE_ROLES = 'roles';
	public const string TYPE_USERGROUPS = 'usergroups';
	public const string TYPE_ROLE_SELECTOR = 'role_selector';
	public const string TYPE_USERGROUP_SELECTOR = 'usergroup_selector';

	/**
	 * @param mixed $ids
	 * @return list<mixed>
	 */
	public static function normalizeIds(mixed $ids): array
	{
		if (!is_array($ids)) {
			return [$ids];
		}

		return $ids;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public static function buildHxTriggerHeaderLine(string $event_name, array $payload): string
	{
		return 'HX-Trigger: ' . json_encode([$event_name => $payload]);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function buildResourcesStrings(): array
	{
		return [
			'cms.resource_browser.new_domain' => t('cms.resource_browser.new_domain'),
			'cms.resource_browser.select_tree_item' => t('cms.resource_browser.select_tree_item'),
			'cms.resource_browser.site_structure' => t('cms.resource_browser.site_structure'),
			'cms.resource_browser.insert' => t('cms.resource_browser.insert'),
			'cms.resource_browser.help_create' => t('cms.resource_browser.help_create'),
			'cms.resource_browser.help_move' => t('cms.resource_browser.help_move'),
			'selection.group' => t('selection.group'),
			'selection.selected_items' => t('selection.selected_items'),
			'selection.delete_selected' => t('selection.delete_selected'),
			'selection.delete_selected_confirm' => t('selection.delete_selected_confirm'),
			'common.preview' => t('common.preview'),
			'common.download' => t('common.download'),
			'common.delete' => t('common.delete'),
			'resource.properties' => t('resource.properties'),
			'resource.new_folder' => t('resource.new_folder'),
			'resource.new_webpage' => t('resource.new_webpage'),
			'resource.upload_file' => t('resource.upload_file'),
			'resource.security' => t('resource.security'),
			'cms.resource_browser.invalid_entry' => t('cms.resource_browser.invalid_entry'),
			'cms.resource_browser.create_index_page' => t('cms.resource_browser.create_index_page'),
			'cms.resource_browser.preview_index_page' => t('cms.resource_browser.preview_index_page'),
			'cms.resource_browser.delete_invalid_confirm' => t('cms.resource_browser.delete_invalid_confirm'),
			'cms.resource_browser.delete_file_confirm' => t('cms.resource_browser.delete_file_confirm'),
			'cms.resource_browser.delete_folder_confirm' => t('cms.resource_browser.delete_folder_confirm'),
			'cms.resource_browser.delete_webpage_confirm' => t('cms.resource_browser.delete_webpage_confirm'),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function buildRolesStrings(): array
	{
		return [
			'user.role.new' => t('user.role.new'),
			'cms.resource_browser.select_tree_item' => t('cms.resource_browser.select_tree_item'),
			'user.usergroup.roles' => t('user.usergroup.roles'),
			'admin.menu.roles' => t('admin.menu.roles'),
			'user.role.all' => t('user.role.all'),
			'user.role.help_create' => t('user.role.help_create'),
			'user.role.help_move' => t('user.role.help_move'),
			'selection.group' => t('selection.group'),
			'selection.multiple' => t('selection.multiple'),
			'selection.selected_items' => t('selection.selected_items'),
			'selection.invalid_entry' => t('selection.invalid_entry'),
			'selection.delete_selected' => t('selection.delete_selected'),
			'selection.delete_selected_confirm' => t('selection.delete_selected_confirm'),
			'selection.delete_invalid_entry_confirm' => t('selection.delete_invalid_entry_confirm'),
			'user.role.new_child' => t('user.role.new_child'),
			'common.edit' => t('common.edit'),
			'common.delete' => t('common.delete'),
			'user.role.delete_confirm' => t('user.role.delete_confirm'),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function buildUsergroupsStrings(): array
	{
		return [
			'user.usergroup.new' => t('user.usergroup.new'),
			'cms.resource_browser.select_tree_item' => t('cms.resource_browser.select_tree_item'),
			'user.usergroup.all' => t('user.usergroup.all'),
			'admin.menu.usergroups' => t('admin.menu.usergroups'),
			'user.usergroup.help_create' => t('user.usergroup.help_create'),
			'user.usergroup.help_move' => t('user.usergroup.help_move'),
			'selection.group' => t('selection.group'),
			'selection.selected_items' => t('selection.selected_items'),
			'selection.invalid_entry' => t('selection.invalid_entry'),
			'selection.delete_selected' => t('selection.delete_selected'),
			'selection.delete_selected_confirm' => t('selection.delete_selected_confirm'),
			'selection.delete_invalid_entry_confirm' => t('selection.delete_invalid_entry_confirm'),
			'user.usergroup.new_child' => t('user.usergroup.new_child'),
			'common.edit' => t('common.edit'),
			'common.delete' => t('common.delete'),
			'user.usergroup.roles' => t('user.usergroup.roles'),
			'user.usergroup.delete_confirm' => t('user.usergroup.delete_confirm'),
			'user.usergroup.system_managed_help' => t('user.usergroup.system_managed_help'),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function buildAdminMenuStrings(): array
	{
		return [
			'admin.menu.admin_menu' => t('admin.menu.admin_menu'),
			'cms.tree.select_menu_item' => t('cms.tree.select_menu_item'),
			'selection.group' => t('selection.group'),
			'selection.selected_items' => t('selection.selected_items'),
			'selection.invalid_entry' => t('selection.invalid_entry'),
			'selection.delete_selected' => t('selection.delete_selected'),
			'selection.delete_selected_confirm' => t('selection.delete_selected_confirm'),
			'selection.delete_invalid_entry_confirm' => t('selection.delete_invalid_entry_confirm'),
			'common.preview' => t('common.preview'),
			'common.edit' => t('common.edit'),
			'common.delete' => t('common.delete'),
			'cms.menu.new_item' => t('cms.menu.new_item'),
			'cms.menu.new_child' => t('cms.menu.new_child'),
			'cms.menu.selection_help' => t('cms.menu.selection_help'),
			'cms.menu.external_link' => t('cms.menu.external_link'),
			'cms.menu.no_link_configured' => t('cms.menu.no_link_configured'),
			'cms.menu.internal_link' => t('cms.menu.internal_link'),
			'cms.menu.delete_confirm' => t('cms.menu.delete_confirm'),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function buildMainMenuStrings(): array
	{
		return [
			'cms.menu.root' => t('cms.menu.root'),
			'cms.menu.new_child' => t('cms.menu.new_child'),
			'common.preview' => t('common.preview'),
			'common.edit' => t('common.edit'),
			'common.delete' => t('common.delete'),
			'cms.menu.external_link' => t('cms.menu.external_link'),
			'cms.menu.no_link_configured' => t('cms.menu.no_link_configured'),
			'cms.menu.internal_link' => t('cms.menu.internal_link'),
			'selection.group' => t('selection.group'),
			'selection.selected_items' => t('selection.selected_items'),
			'selection.invalid_entry' => t('selection.invalid_entry'),
			'selection.delete_selected' => t('selection.delete_selected'),
			'selection.delete_selected_confirm' => t('selection.delete_selected_confirm'),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function buildRoleSelectorStrings(): array
	{
		return [
			'user.role_selector.title' => t('user.role_selector.title'),
			'user.role_selector.available_roles' => t('user.role_selector.available_roles'),
			'user.role_selector.help' => t('user.role_selector.help'),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function buildUsergroupSelectorStrings(): array
	{
		return [
			'user.usergroup_selector.title' => t('user.usergroup_selector.title'),
			'user.usergroup_selector.available_usergroups' => t('user.usergroup_selector.available_usergroups'),
			'user.usergroup_selector.help' => t('user.usergroup_selector.help'),
		];
	}

	/**
	 * Resolves jsTree parent node id for both jsTree 1.x and 3.x conventions.
	 *
	 * @param mixed $node_id
	 * @param int $root_value
	 * @param null|callable():int $root_resolver
	 * @return int
	 */
	public static function resolveParentNodeId(mixed $node_id, int $root_value = 0, ?callable $root_resolver = null): int
	{
		if ($node_id === 'root' || $node_id === '#') {
			if ($root_resolver !== null) {
				return $root_resolver();
			}

			return $root_value;
		}

		return (int) $node_id;
	}

	/**
	 * @param array<string, mixed> $json_data
	 * @param array<string, mixed> $error_meta
	 */
	public static function buildMoveResponse(bool $success, array $json_data, array $error_meta = []): ApiResponse
	{
		if ($success) {
			return ApiResponse::success($json_data);
		}

		return ApiResponse::error(new ApiError('OPERATION_FAILED', 'Move failed'), 400, $error_meta);
	}

	/**
	 * @param array<string, mixed> $json_data
	 * @param array<string, mixed> $error_meta
	 */
	public static function renderMoveResponse(bool $success, array $json_data, array $error_meta = []): void
	{
		ApiResponse::renderResponse(self::buildMoveResponse($success, $json_data, $error_meta));
	}

	/**
	 * @param array<string, mixed> $json_data
	 * @param array<string, mixed> $error_meta
	 */
	public static function buildDeleteResponse(bool $success, array $json_data, array $error_meta = []): ApiResponse
	{
		if ($success) {
			return ApiResponse::success($json_data);
		}

		return ApiResponse::error(new ApiError('OPERATION_FAILED', 'Delete failed'), 400, $error_meta);
	}

	/**
	 * @param array<string, mixed> $json_data
	 * @param array<string, mixed> $error_meta
	 */
	public static function renderDeleteResponse(bool $success, array $json_data, array $error_meta = []): void
	{
		ApiResponse::renderResponse(self::buildDeleteResponse($success, $json_data, $error_meta));
	}

	/**
	 * Render a jsTree detail component with themed override support.
	 *
	 * @param array<string, mixed> $component_props
	 * @param array<string, list<array<string, mixed>>> $contents
	 * @param array<string, mixed> $strings
	 */
	public static function renderDinaComponent(string $component_name, array $component_props, string $theme_name, array $contents = [], array $strings = []): void
	{
		WebpageView::header('Content-type: ' . Template::MIME_HTML);

		$renderer = new HtmlTreeRenderer(theme: ThemeBase::factory($theme_name));
		echo $renderer->render(SduiNode::create(
			component: $component_name,
			props: $component_props,
			contents: $contents,
			strings: $strings,
		));
	}

	/**
	 * Render a jsTree detail template with themed override support.
	 *
	 * @param array<string, mixed> $template_props
	 * @param array<string, list<array<string, mixed>>> $contents
	 * @param array<string, mixed> $strings
	 */
	public static function renderDinaTemplate(string $template_name, array $template_props, string $theme_name, array $contents = [], array $strings = []): void
	{
		self::renderDinaComponent($template_name, $template_props, $theme_name, $contents, $strings);
	}

	/**
	 * @param array<string, mixed> $props
	 * @param array<string, list<array<string, mixed>>> $contents
	 * @param array<string, mixed> $meta
	 * @param array<string, mixed> $strings
	 * @return array<string, mixed>
	 */
	public static function createDinaNode(string $component_name, array $props = [], array $contents = [], array $meta = [], array $strings = []): array
	{
		return SduiNode::create(
			component: $component_name,
			props: $props,
			contents: $contents,
			meta: $meta,
			strings: $strings,
		);
	}

	/**
	 * @param list<string> $allowed_templates
	 * @param array<string, mixed> $context
	 * @param array<string, mixed> $meta
	 */
	public static function buildResponse(array $allowed_templates, string $tree_type, array $raw_data, array $context, ?string $shape_template, array $meta = []): ApiResponse
	{
		if ($shape_template === null || $shape_template === '') {
			return ApiResponse::error(
				new ApiError('TEMPLATE_REQUIRED', 'Missing required parameter: shape_template'),
				400
			);
		}

		if (!in_array($shape_template, $allowed_templates, true)) {
			return ApiResponse::error(
				new ApiError('TEMPLATE_NOT_ALLOWED', 'Invalid shape_template: ' . $shape_template),
				400
			);
		}

		$adapters = self::getAdapters();
		$adapter = $adapters[$shape_template] ?? null;
		assert($adapter instanceof iJsTreeTemplateAdapter);

		try {
			$data = $adapter->build($tree_type, $raw_data, $context);
		} catch (InvalidArgumentException $e) {
			return ApiResponse::error(
				new ApiError('TREE_TYPE_NOT_ALLOWED', $e->getMessage()),
				400
			);
		}

		return ApiResponse::success($data, $meta !== [] ? $meta : null);
	}

	/**
	 * @return array<string, iJsTreeTemplateAdapter>
	 */
	private static function getAdapters(): array
	{
		return [
			self::TEMPLATE_JSTREE_3 => new JsTreeTemplateAdapter3x(),
			self::TEMPLATE_JSTREE_1 => new JsTreeTemplateAdapter1x(),
		];
	}
}
