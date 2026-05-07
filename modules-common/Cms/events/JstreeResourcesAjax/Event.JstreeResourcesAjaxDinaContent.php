<?php

/**
 * JstreeResourcesAjaxDinaContent Event.
 *
 * Returns HTML content for the detail panel when a node is selected.
 *
 * Theme resolution: Uses referer GET parameter or HTTP referer header
 * to determine the theme from the page the request originated from.
 *
 * POST Parameters:
 * - id: Array of selected node IDs
 * - type: Node type (root, folder, webpage, file, _multiple_)
 * - jstree_id: Widget instance ID
 */
class EventJstreeResourcesAjaxDinaContent extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->inGroup(Usergroups::SYSTEMUSERGROUP_LOGGEDIN)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		$allowed_types = [
			'webpage',
			'folder',
			'root',
			'_multiple_',
			'null',
			'file',
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

		if (!in_array($type, $allowed_types)) {
			http_response_code(400);
			echo '<!-- ' . t('cms.tree.unknown_type', ['type' => $type]) . ' -->';

			return;
		}

		// Build template props
		$strings = JsTreeApiService::buildResourcesStrings();
		$templateProps = [
			'id' => $ids,
			'type' => $type,
			'jstree_id' => $jstree_id,
			'data' => [],
		];

		foreach ($ids as $id) {
			$id = $this->resolveResourceTreeNodeId($id);

			if (!$this->canRenderResourceDetails($id)) {
				ApiResponse::renderError('RESOURCE_ACCESS_DENIED', t('response_error.access_denied'), 403);

				return;
			}

			$resource = ResourceTypeFactory::Factory($id);

			if (!is_null($resource)) {
				$data = [
					'title' => $resource->getData('title'),
					'resource_name' => $resource->getData('resource_name'),
					'path' => $resource->getData('path'),
					'node_id' => $resource->getData('node_id'),
					'node_type' => $resource->getData('node_type'),
				];
			} else {
				$data = false;
			}

			$templateProps['data'][] = $data;

			if ($type == 'folder' || $type == 'root') {
				$templateProps['indexpage_node_id'] = ResourceTreeHandler::getIndexpageNodeId($id);
				$templateProps['indexpage_data'] = $templateProps['indexpage_node_id'] !== null
					&& $this->canRenderResourceDetails((int) $templateProps['indexpage_node_id'])
					? ResourceTreeHandler::getResourceTreeEntryDataById($templateProps['indexpage_node_id'])
					: null;
			}
		}

		if (Request::_GET('CKEditor', false) === false || Request::_GET('CKEditor') === 'undefined') {
			$templateProps['insertUrl'] = false;
		} else {
			$templateProps['insertUrl'] = Url::getUrl('url.redirect', [
				'direction' => 'in',
				'id' => $templateProps['data'][0]['node_id'],
			]);
		}

		$templateName = "jsTree.dina_content.resources.{$type}";
		JsTreeApiService::renderDinaComponent($templateName, $templateProps, $themeName, [
			'insert_button' => [
				JsTreeApiService::createDinaNode('dina_content._buttonInsert', [
					'insertUrl' => $templateProps['insertUrl'],
				], [], [], $strings),
			],
			'help' => [
				JsTreeApiService::createDinaNode('dina_content.resources._help', [], [], [], $strings),
			],
		], $strings);
	}

	private function resolveResourceTreeNodeId(mixed $node_id): int
	{
		if ($node_id === ResourceTreeHandler::JSTREE_SITE_ROOT_ID) {
			return CmsSiteContext::getCurrentRootId() ?? 0;
		}

		return (int) $node_id;
	}

	private function canRenderResourceDetails(int $resource_id): bool
	{
		return $resource_id > 0
			&& ResourceTreeHandler::getResourceTreeEntryDataById($resource_id) !== null
			&& ResourceAcl::canAccessResource($resource_id, ResourceAcl::_ACL_LIST);
	}
}
