<?php

/**
 * JSON adapter for jsTree 1.x format.
 *
 * Transforms raw tree data into the format expected by jsTree 1.x:
 *
 * {
 *     "attr": {"id": "prefix_123", "rel": "folder", "data-node_id": 123, ...},
 *     "state": "closed" | null,
 *     "data": {"title": "<span>...</span>", "icon": "path/to/icon.svg"}
 * }
 *
 * Used by: SoAdmin, Tracker themes
 */
class JsonAdapterJsTree1x
{
	/**
	 * Format resource tree data for jsTree 1.x.
	 *
	 * @param array $nodes Raw nodes from ResourceTreeHandler::getResourceTree()
	 * @param array|null $parent_data Parent node data for catcher detection
	 * @param string $id_prefix Prefix for node IDs
	 * @return array Formatted jsTree 1.x data
	 */
	public static function resourceTree(array $nodes, ?array $parent_data, string $id_prefix): array
	{
		$result = [];

		foreach ($nodes as $node) {
			$icon_name = $node['node_type'];

			if (isset($parent_data['catcher_page']) && $parent_data['catcher_page'] == $node['node_id']) {
				$icon_name .= '-catcher';
			}

			$item = self::buildNode(
				$node,
				$id_prefix,
				$node['node_type'],
				Icons::resolve($icon_name),
				self::defaultTitle($node['resource_name'] ?? '', $node)
			);

			// Add indexpage_node_id for folders
			if ($node['node_type'] === 'folder' || $node['node_type'] === 'root') {
				$item['attr']['data-indexpage_node_id'] = ResourceTreeHandler::getIndexpageNodeId($node['node_id']);
			}

			$result[] = $item;
		}

		return $result;
	}

	/**
	 * Format admin menu tree data for jsTree 1.x.
	 *
	 * @param array $nodes Raw nodes from AdminMenu::getMenuTree()
	 * @param string $id_prefix Prefix for node IDs
	 * @param int $parent_node_id Parent node ID (0 for root level)
	 * @return array Formatted jsTree 1.x data
	 */
	public static function adminMenuTree(array $nodes, string $id_prefix, int $parent_node_id): array
	{
		$result = [];

		foreach ($nodes as $node) {
			// Determine link type for display
			if (!is_null($node['url'])) {
				$link_type = 'external';
			} elseif (is_null($node['page_id'])) {
				$link_type = 'none';
			} else {
				$link_type = 'internal';
			}

			$item = self::buildNode(
				$node,
				$id_prefix,
				$node['node_type'],
				Icons::resolve('adminmenu'),
				self::defaultTitle($node['node_name'] ?? '', $node)
			);

			$item['attr']['data-link_type'] = $link_type;

			$result[] = $item;
		}

		// Wrap in virtual root when at root level
		if ($parent_node_id === 0) {
			return [self::buildVirtualRoot($result, $id_prefix, t('admin.menu.admin_menu'), Icons::resolve('adminmenu'))];
		}

		return $result;
	}

	/**
	 * Format main menu tree data for jsTree 1.x.
	 *
	 * @param array $nodes Raw nodes from MainMenu::getMenuTree()
	 * @param string $id_prefix Prefix for node IDs
	 * @param int $parent_node_id Parent node ID (0 for root level)
	 * @return array Formatted jsTree 1.x data
	 */
	public static function mainMenuTree(array $nodes, string $id_prefix, int $parent_node_id): array
	{
		$result = [];

		foreach ($nodes as $node) {
			// Determine link type icon
			if (!is_null($node['url'])) {
				$subtype = IconNames::LINK_OUT;
			} elseif (is_null($node['page_id'])) {
				$subtype = IconNames::LINK_NONE;
			} else {
				$subtype = IconNames::LINK;
			}

			$icon = $node['node_type'] === 'root'
				? Icons::path(IconNames::MENUBAR)
				: Icons::path($subtype);

			$item = self::buildNode(
				$node,
				$id_prefix,
				$node['node_type'],
				$icon,
				self::defaultTitle($node['node_name'] ?? '', $node)
			);

			$result[] = $item;
		}

		// Wrap in virtual root when at root level
		if ($parent_node_id === 0) {
			return [self::buildVirtualRoot($result, $id_prefix, t('cms.menu.root'), Icons::path(IconNames::MENUBAR))];
		}

		return $result;
	}

	/**
	 * Format roles tree data for jsTree 1.x (browser view).
	 *
	 * @param array $nodes Raw nodes from Roles::getRoleTree()
	 * @param string $id_prefix Prefix for node IDs
	 * @param int $parent_node_id Parent node ID (0 for root level)
	 * @return array Formatted jsTree 1.x data
	 */
	public static function rolesTree(array $nodes, string $id_prefix, int $parent_node_id): array
	{
		$result = [];

		foreach ($nodes as $node) {
			$title = $node['title'] ?? '';
			$role_name = $node['role'] ?? '';

			$title_html = self::debugPrefix($node)
				. '<span class="title">'
				. htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE)
				. ' (' . htmlspecialchars($role_name, ENT_QUOTES | ENT_SUBSTITUTE) . ')'
				. '</span>';

			$item = self::buildNode(
				$node,
				$id_prefix,
				'role',
				Icons::path(IconNames::ROLE),
				$title_html
			);

			$item['attr']['data-role_name'] = $role_name;

			$result[] = $item;
		}

		// Wrap in virtual root when at root level
		if ($parent_node_id === 0) {
			return [self::buildVirtualRoot($result, $id_prefix, t('admin.menu.roles'), Icons::path(IconNames::ROLE))];
		}

		return $result;
	}

	/**
	 * Format role selector data for jsTree 1.x (with checkboxes).
	 *
	 * @param array $nodes Raw nodes from Roles::getRoleTree()
	 * @param string $for_type Entity type ('user' or 'usergroup')
	 * @param int $for_id Entity ID to check assignments for
	 * @return array Formatted jsTree 1.x data with checkbox classes
	 */
	public static function roleSelector(array $nodes, string $for_type, int $for_id): array
	{
		$result = [];

		foreach ($nodes as $node) {
			$is_checked = match ($for_type) {
				'user' => Roles::checkUserIsAssigned($node['node_id'], $for_id),
				'usergroup' => Roles::checkUsergroupIsAssigned($node['node_id'], $for_id),
				default => false,
			};

			$title = $node['title'] ?? '';

			$result[] = self::buildCheckboxNode(
				$node,
				'role',
				$is_checked,
				Icons::path(IconNames::ROLE),
				self::defaultTitle($title, $node)
			);
		}

		return $result;
	}

	/**
	 * Format usergroups tree data for jsTree 1.x (browser view).
	 *
	 * @param array $nodes Raw nodes from Usergroups::getResourceTree()
	 * @param string $id_prefix Prefix for node IDs
	 * @param int $parent_node_id Parent node ID (0 for root level)
	 * @return array Formatted jsTree 1.x data
	 */
	public static function usergroupsTree(array $nodes, string $id_prefix, int $parent_node_id): array
	{
		$result = [];

		foreach ($nodes as $node) {
			$is_system = !empty($node['is_system_group']);
			$type = $is_system ? 'systemusergroup' : 'usergroup';
			$icon = Icons::path($is_system ? IconNames::SYSTEM_USERGROUP : IconNames::USERGROUP);

			$result[] = self::buildNode(
				$node,
				$id_prefix,
				$type,
				$icon,
				self::defaultTitle($node['title'] ?? '', $node)
			);
		}

		// Wrap in virtual root when at root level
		if ($parent_node_id === 0) {
			return [self::buildVirtualRoot($result, $id_prefix, t('admin.menu.usergroups'), Icons::path(IconNames::USERGROUP))];
		}

		return $result;
	}

	/**
	 * Format usergroup selector data for jsTree 1.x (with checkboxes).
	 *
	 * System groups are filtered out.
	 *
	 * @param array $nodes Raw nodes from Usergroups::getResourceTree()
	 * @param int $for_id User ID to check assignments for
	 * @return array Formatted jsTree 1.x data with checkbox classes
	 */
	public static function usergroupSelector(array $nodes, int $for_id): array
	{
		$result = [];

		foreach ($nodes as $node) {
			// Skip system groups in selector
			if (!empty($node['is_system_group'])) {
				continue;
			}

			$is_checked = Usergroups::checkUserIsAssigned($node['node_id'], $for_id);

			$result[] = self::buildCheckboxNode(
				$node,
				'usergroup',
				$is_checked,
				Icons::path(IconNames::USERGROUP),
				self::defaultTitle($node['title'] ?? '', $node)
			);
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Private helper methods
	// -------------------------------------------------------------------------

	/**
	 * Build a virtual root node that wraps children (for browser trees).
	 *
	 * @param array $children Child nodes to nest under the root
	 * @param string $id_prefix Prefix for node IDs
	 * @param string $label Display label for the root node
	 * @param string $icon Icon path for the root node
	 * @return array Virtual root node with children
	 */
	private static function buildVirtualRoot(array $children, string $id_prefix, string $label, string $icon): array
	{
		return [
			'attr' => [
				'id' => $id_prefix . '_0',
				'rel' => 'root',
				'data-node_id' => 0,
			],
			'state' => 'open',
			'data' => [
				'title' => '<span class="title">' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE) . '</span>',
				'icon' => $icon,
			],
			'children' => $children,
		];
	}

	/**
	 * Build a standard jsTree 1.x node structure.
	 */
	private static function buildNode(
		array $node,
		string $id_prefix,
		string $type,
		string $icon,
		string $title_html
	): array {
		$has_children = ($node['rgt'] - $node['lft'] > 1);

		$attr = [
			'id' => $id_prefix . '_' . $node['node_id'],
			'rel' => $type,
		];

		// Add all node data as data-* attributes
		foreach ($node as $key => $value) {
			$attr['data-' . $key] = $value;
		}

		return [
			'attr' => $attr,
			'state' => $has_children ? 'closed' : null,
			'data' => [
				'title' => $title_html,
				'icon' => $icon,
			],
		];
	}

	/**
	 * Build a jsTree 1.x node with checkbox support.
	 *
	 * Note: Checkbox nodes use raw node_id (no prefix) for check/uncheck operations.
	 */
	private static function buildCheckboxNode(
		array $node,
		string $type,
		bool $is_checked,
		string $icon,
		string $title_html
	): array {
		$has_children = ($node['rgt'] - $node['lft'] > 1);

		$attr = [
			'id' => $node['node_id'],
			'class' => $is_checked ? 'jstree-checked' : 'jstree-unchecked',
			'rel' => $type,
		];

		// Add all node data as data-* attributes
		foreach ($node as $key => $value) {
			$attr['data-' . $key] = $value;
		}

		return [
			'attr' => $attr,
			'state' => $has_children ? 'closed' : null,
			'data' => [
				'title' => $title_html,
				'icon' => $icon,
			],
		];
	}

	/**
	 * Build default title HTML with debug info prefix.
	 */
	private static function defaultTitle(string $name, array $node): string
	{
		return self::debugPrefix($node)
			. '<span class="title">'
			. htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE)
			. '</span>';
	}

	/**
	 * Build debug info prefix showing node_id and lft-rgt range.
	 */
	private static function debugPrefix(array $node): string
	{
		return '<span class="debug">'
			. $node['node_id']
			. ':<i style="color:#999">(' . $node['lft'] . '-' . $node['rgt'] . ')</i> '
			. '</span>';
	}
}
