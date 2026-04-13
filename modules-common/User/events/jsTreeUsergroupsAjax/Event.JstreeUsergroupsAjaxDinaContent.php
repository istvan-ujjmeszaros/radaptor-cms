<?php

/**
 * JstreeUsergroupsAjaxDinaContent Event.
 *
 * Returns HTML content for the detail panel when a node is selected.
 *
 * Theme resolution: Uses referer GET parameter or HTTP referer header
 * to determine the theme from the page the request originated from.
 *
 * POST Parameters:
 * - id: Array of selected node IDs
 * - type: Node type (root, usergroup, systemusergroup, _multiple_)
 * - jstree_id: Widget instance ID
 */
class EventJstreeUsergroupsAjaxDinaContent extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->hasRole(RoleList::ROLE_USERGROUPS_ADMIN)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		$allowed_types = [
			'systemusergroup',
			'usergroup',
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
		$strings = JsTreeApiService::buildUsergroupsStrings();

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
					'title' => t('admin.menu.usergroups'),
					'node_id' => 0,
				];
			} else {
				$usergroup = Usergroups::getUsergroupValues((int) $id);

				if (is_array($usergroup)) {
					$data = [
						'title' => $usergroup['title'],
						'node_id' => $usergroup['node_id'],
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

		$templateName = "jsTree.dina_content.usergroups.{$type}";
		JsTreeApiService::renderDinaComponent($templateName, $templateProps, $themeName, [
			'help' => [
				JsTreeApiService::createDinaNode('dina_content.usergroups._help', [], [], [], $strings),
			],
		], $strings);
	}
}
