<?php

/**
 * JstreeRolesAjaxDinaContent Event.
 *
 * Returns HTML content for the detail panel when a node is selected.
 *
 * Theme resolution: Uses referer GET parameter or HTTP referer header
 * to determine the theme from the page the request originated from.
 *
 * POST Parameters:
 * - id: Array of selected node IDs
 * - type: Node type (root, role, _multiple_)
 * - jstree_id: Widget instance ID
 */
class EventJstreeRolesAjaxDinaContent extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return ($policyContext->principal->hasRole(RoleList::ROLE_ROLES_ADMIN) || $policyContext->principal->hasRole(RoleList::ROLE_ROLES_VIEWER))
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		$allowed_types = [
			'role',
			'root',
			'_multiple_',
			'null',
		];

		try {
			$ids = JsTreeApiService::normalizeIds(Request::postRequired('id'));
			$type = Request::postOptional('type', 'null');
			$jstree_id = Request::postRequired('jstree_id');
		} catch (RequestParamException $e) {
			http_response_code(400);
			echo "<!-- {$e->getMessage()} -->";

			return;
		}

		// Get theme from referer for AJAX requests
		$themeName = Themes::getThemeNameFromReferer();
		$strings = JsTreeApiService::buildRolesStrings();

		if (!in_array($type, $allowed_types)) {
			http_response_code(400);
			echo '<!-- ' . t('cms.tree.unknown_type', ['type' => $type]) . ' -->';

			return;
		}

		// Build template props
		$templateProps = [
			'id' => $ids,
			'type' => $type,
			'jstree_id' => $jstree_id,
			'data' => [],
			'selected_items' => [],
		];

		foreach ($ids as $id) {
			// Handle root node (id=0) specially
			if ($id === '0' || $id === 0) {
				$data = [
					'title' => t('admin.menu.roles'),
					'node_id' => 0,
				];
			} else {
				$role = Roles::getRoleValues((int) $id);

				if (!is_null($role)) {
					$data = [
						'title' => $role['title'],
						'node_id' => $role['node_id'],
					];
				} else {
					$data = false;
				}
			}

			$templateProps['data'][] = $data;

			if (is_array($data)) {
				$templateProps['selected_items'][] = [
					'title' => $data['title'],
				];
			}
		}

		$templateName = "jsTree.dina_content.roles.{$type}";
		JsTreeApiService::renderDinaComponent($templateName, $templateProps, $themeName, [
			'help' => [
				JsTreeApiService::createDinaNode('dina_content.roles._help', [], [], [], $strings),
			],
		], $strings);
	}
}
