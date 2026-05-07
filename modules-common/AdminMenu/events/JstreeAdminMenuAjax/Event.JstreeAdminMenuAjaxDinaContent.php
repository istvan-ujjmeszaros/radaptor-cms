<?php

/**
 * JstreeAdminMenuAjaxDinaContent Event.
 *
 * Returns HTML content for the detail panel when a node is selected.
 *
 * Theme resolution: Uses referer GET parameter or HTTP referer header
 * to determine the theme from the page the request originated from.
 *
 * POST Parameters:
 * - id: Array of selected node IDs
 * - type: Node type (root, submenu, _multiple_)
 * - jstree_id: Widget instance ID
 */
class EventJstreeAdminMenuAjaxDinaContent extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		$allowed_types = [
			'root',
			'submenu',
			'title',
			'_multiple_',
		];

		try {
			$ids = JsTreeApiService::normalizeIds(Request::postRequired('id'));
			$type = Request::postRequired('type');
			$jstree_id = Request::postRequired('jstree_id');
		} catch (RequestParamException $e) {
			http_response_code(400);
			echo "<!-- {$e->getMessage()} -->";

			return;
		}

		// Get theme from referer for AJAX requests
		$themeName = Themes::getThemeNameFromReferer();
		$strings = JsTreeApiService::buildAdminMenuStrings();

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
			$adminmenu = AdminMenu::factory((int) $id);

			if (is_array($adminmenu)) {
				$data = [
					'node_id' => $adminmenu['node_id'],
					'node_name' => $adminmenu['node_name'],
					'url' => AdminMenu::getUrl($adminmenu['node_id']),
					'internal_url' => AdminMenu::getUrl($adminmenu['node_id'], false),
					'page_id' => $adminmenu['page_id'],
					'has_link' => !is_null($adminmenu['page_id']) || !is_null($adminmenu['url']),
					'is_external' => !is_null($adminmenu['url']),
				];
			} else {
				$data = false;
			}

			$templateProps['data'][] = $data;

			if (is_array($data)) {
				$templateProps['selected_items'][] = [
					'title' => $data['node_name'],
					'url' => $data['internal_url'],
				];
			}
		}

		$templateName = "jsTree.dina_content.adminMenu.{$type}";
		JsTreeApiService::renderDinaComponent($templateName, $templateProps, $themeName, [], $strings);
	}
}
