<?php

/**
 * JSON adapter for jsTree 3.x format.
 *
 * Transforms raw tree data into the format expected by jsTree 3.x:
 *
 * {
 *     "id": "123",
 *     "text": "Node Name",
 *     "type": "folder",
 *     "children": true,
 *     "data": {"node_id": 123, ...}
 * }
 *
 * For checkbox trees, adds:
 * {
 *     "state": {"checked": true, "opened": false}
 * }
 *
 * Used by: RadaptorPortalAdmin theme
 */
class JsonAdapterJsTree3x
{
	/**
	 * Format resource tree data for jsTree 3.x.
	 *
	 * @param array $nodes Raw nodes from ResourceTreeHandler::getResourceTree()
	 * @param array|null $parent_data Parent node data for catcher detection
	 * @return array Formatted jsTree 3.x data
	 */
	public static function resourceTree(array $nodes, ?array $parent_data): array
	{
		$result = [];

		foreach ($nodes as $node) {
			$is_catcher = isset($parent_data['catcher_page'])
				&& $parent_data['catcher_page'] == $node['node_id'];

			$data = [
				'path' => $node['path'] ?? '',
				'resource_name' => $node['resource_name'],
				'node_type' => $node['node_type'],
				'is_catcher' => $is_catcher,
				'indexpage_node_id' => null,
			];

			if ($node['node_type'] === 'folder' || $node['node_type'] === 'root') {
				$data['indexpage_node_id'] = ResourceTreeHandler::getIndexpageNodeId($node['node_id']);
			}

			$result[] = self::buildNode(
				$node,
				$node['resource_name'],
				$node['node_type'],
				$data
			);
		}

		return $result;
	}

	/**
	 * Format admin menu tree data for jsTree 3.x.
	 *
	 * @param array $nodes Raw nodes from AdminMenu::getMenuTree()
	 * @param int $parent_node_id Parent node ID (0 for root level)
	 * @return array Formatted jsTree 3.x data
	 */
	public static function adminMenuTree(array $nodes, int $parent_node_id): array
	{
		$result = [];

		foreach ($nodes as $node) {
			// Determine link type
			if (!is_null($node['url'])) {
				$link_type = 'external';
			} elseif (is_null($node['page_id'])) {
				$link_type = 'none';
			} else {
				$link_type = 'internal';
			}

			$result[] = self::buildNode(
				$node,
				htmlspecialchars($node['node_name'], ENT_QUOTES | ENT_SUBSTITUTE),
				$node['node_type'],
				[
					'node_id' => $node['node_id'],
					'node_name' => $node['node_name'],
					'node_type' => $node['node_type'],
					'url' => $node['url'],
					'page_id' => $node['page_id'],
					'link_type' => $link_type,
				]
			);
		}

		// Wrap in virtual root when at root level
		if ($parent_node_id === 0) {
			return [self::buildVirtualRoot($result, t('admin.menu.admin_menu'))];
		}

		return $result;
	}

	/**
	 * Format main menu tree data for jsTree 3.x.
	 *
	 * @param array $nodes Raw nodes from MainMenu::getMenuTree()
	 * @param int $parent_node_id Parent node ID (0 for root level)
	 * @return array Formatted jsTree 3.x data
	 */
	public static function mainMenuTree(array $nodes, int $parent_node_id): array
	{
		$result = [];

		foreach ($nodes as $node) {
			// Determine link type
			if (!is_null($node['url'])) {
				$link_type = 'external';
			} elseif (is_null($node['page_id'])) {
				$link_type = 'none';
			} else {
				$link_type = 'internal';
			}

			$result[] = self::buildNode(
				$node,
				htmlspecialchars($node['node_name'], ENT_QUOTES | ENT_SUBSTITUTE),
				$node['node_type'],
				[
					'node_id' => $node['node_id'],
					'node_name' => $node['node_name'],
					'node_type' => $node['node_type'],
					'url' => $node['url'],
					'page_id' => $node['page_id'],
					'link_type' => $link_type,
				]
			);
		}

		// Wrap in virtual root when at root level
		if ($parent_node_id === 0) {
			return [self::buildVirtualRoot($result, t('cms.menu.root'))];
		}

		return $result;
	}

	/**
	 * Format roles tree data for jsTree 3.x (browser view).
	 *
	 * @param array $nodes Raw nodes from Roles::getRoleTree()
	 * @param int $parent_node_id Parent node ID (0 for root level)
	 * @return array Formatted jsTree 3.x data
	 */
	public static function rolesTree(array $nodes, int $parent_node_id): array
	{
		$result = [];

		foreach ($nodes as $node) {
			$title = $node['title'] ?? '';
			$role_name = $node['role'] ?? '';

			$text = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE)
				. ' <small class="text-muted">('
				. htmlspecialchars($role_name, ENT_QUOTES | ENT_SUBSTITUTE)
				. ')</small>';

			$result[] = self::buildNode(
				$node,
				$text,
				'role',
				[
					'node_id' => $node['node_id'],
					'title' => $title,
					'role' => $role_name,
				]
			);
		}

		// Wrap in virtual root when at root level
		if ($parent_node_id === 0) {
			return [self::buildVirtualRoot($result, t('admin.menu.roles'))];
		}

		return $result;
	}

	/**
	 * Format role selector data for jsTree 3.x (with checkboxes).
	 *
	 * @param array $nodes Raw nodes from Roles::getRoleTree()
	 * @param string $for_type Entity type ('user' or 'usergroup')
	 * @param int $for_id Entity ID to check assignments for
	 * @return array Formatted jsTree 3.x data with checkbox state
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
			$role_name = $node['role'] ?? '';

			$text = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE)
				. ' <small class="text-muted">('
				. htmlspecialchars($role_name, ENT_QUOTES | ENT_SUBSTITUTE)
				. ')</small>';

			$result[] = self::buildCheckboxNode(
				$node,
				$text,
				'role',
				$is_checked,
				[
					'node_id' => $node['node_id'],
					'is_explicit' => $is_checked,
					'lft' => $node['lft'],
					'rgt' => $node['rgt'],
				]
			);
		}

		return $result;
	}

	/**
	 * Format usergroups tree data for jsTree 3.x (browser view).
	 *
	 * @param array $nodes Raw nodes from Usergroups::getResourceTree()
	 * @param int $parent_node_id Parent node ID (0 for root level)
	 * @return array Formatted jsTree 3.x data
	 */
	public static function usergroupsTree(array $nodes, int $parent_node_id): array
	{
		$result = [];

		foreach ($nodes as $node) {
			$is_system = !empty($node['is_system_group']);
			$type = $is_system ? 'systemusergroup' : 'usergroup';

			$result[] = self::buildNode(
				$node,
				$node['title'] ?? '',
				$type,
				[
					'node_id' => $node['node_id'],
					'title' => $node['title'],
					'is_system_group' => $is_system,
				]
			);
		}

		// Wrap in virtual root when at root level
		if ($parent_node_id === 0) {
			return [self::buildVirtualRoot($result, t('admin.menu.usergroups'))];
		}

		return $result;
	}

	/**
	 * Format usergroup selector data for jsTree 3.x (with checkboxes).
	 *
	 * System groups are filtered out.
	 *
	 * @param array $nodes Raw nodes from Usergroups::getResourceTree()
	 * @param int $for_id User ID to check assignments for
	 * @return array Formatted jsTree 3.x data with checkbox state
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
				htmlspecialchars($node['title'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE),
				'usergroup',
				$is_checked,
				[
					'node_id' => $node['node_id'],
					'title' => $node['title'],
					'is_system_group' => false,
				]
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
	 * @param string $label Display label for the root node
	 * @return array Virtual root node with inline children
	 */
	private static function buildVirtualRoot(array $children, string $label): array
	{
		return [
			'id' => '0',
			'text' => $label,
			'type' => 'root',
			'state' => ['opened' => true],
			'children' => $children,
			'data' => ['node_id' => 0],
		];
	}

	/**
	 * Build a standard jsTree 3.x node structure.
	 */
	private static function buildNode(
		array $node,
		string $text,
		string $type,
		array $data
	): array {
		$has_children = ($node['rgt'] - $node['lft'] > 1);

		return [
			'id' => (string) $node['node_id'],
			'text' => $text,
			'type' => $type,
			'children' => $has_children,
			'data' => $data,
		];
	}

	/**
	 * Build a jsTree 3.x node with checkbox state.
	 */
	private static function buildCheckboxNode(
		array $node,
		string $text,
		string $type,
		bool $is_checked,
		array $data
	): array {
		$has_children = ($node['rgt'] - $node['lft'] > 1);

		return [
			'id' => (string) $node['node_id'],
			'text' => $text,
			'type' => $type,
			'children' => $has_children,
			'state' => [
				'checked' => $is_checked,
				'opened' => false,
			],
			'data' => $data,
		];
	}
}
