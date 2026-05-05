<?php

class EventJstreeResourcesAjaxDeleteRecursive extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->inGroup(Usergroups::SYSTEMUSERGROUP_LOGGEDIN)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		try {
			$ids = Request::getRequired('id');
		} catch (RequestParamException $e) {
			ApiResponse::renderError($e->code_id, $e->getMessage(), 400);

			return;
		}

		$ids = JsTreeApiService::normalizeIds($ids);

		$total_folders = 0;
		$total_webpages = 0;
		$total_files = 0;
		$total_errors = 0;
		$deleted_names = [];
		$error_messages = [];
		$last_parent_id = null;
		$success = true;

		foreach ($ids as $id) {
			$id = $this->resolveResourceTreeNodeId($id);
			$data = ResourceTreeHandler::getResourceTreeEntryDataById($id);

			if (!$data) {
				continue;
			}

			$last_parent_id = $data['parent_id'];

			$deletion_result = ResourceTreeHandler::deleteResourceEntriesRecursiveResult($id);
			$deletion_response_data = is_array($deletion_result->data)
				? $deletion_result->data
				: [
					'success' => false,
					'erroneous' => 1,
					'folder' => 0,
					'webpage' => 0,
					'file' => 0,
				];

			if (!$deletion_result->ok) {
				$success = false;
				++$total_errors;
				$error_messages[] = $deletion_result->error?->message ?? t('cms.resource.delete_failed');

				continue;
			}

			if ($deletion_response_data['success']) {
				$total_folders += $deletion_response_data['folder'] ?? 0;
				$total_webpages += $deletion_response_data['webpage'] ?? 0;
				$total_files += $deletion_response_data['file'] ?? 0;
				$total_errors += $deletion_response_data['erroneous'] ?? 0;
				$deleted_names[] = $data['resource_name'] ?? t('common.unknown');
			} else {
				$success = false;
			}
		}

		$parent_data = $last_parent_id !== null ? ResourceTreeHandler::getResourceTreeEntryDataById((int) $last_parent_id) : null;

		$json_data = [
			'deleted_ids' => $ids,
			'parent_data' => $parent_data,
			'parent_node' => (is_array($parent_data) ? $parent_data['node_type'] : 'root') . ($last_parent_id ? '_' . $last_parent_id : ''),
		];

		if ($success) {
			header(JsTreeApiService::buildHxTriggerHeaderLine('resourceTreeDeleted', [
				'nodeIds' => array_map('intval', $ids),
				'success' => true,
			]));
		} else {
			header(JsTreeApiService::buildHxTriggerHeaderLine('resourceTreeError', [
				'message' => $error_messages[0] ?? t('cms.resource.delete_failed'),
			]));
		}

		if ($success) {
			ApiResponse::renderSuccess($json_data, [
				'deleted' => [
					'folder' => $total_folders,
					'webpage' => $total_webpages,
					'file' => $total_files,
					'names' => $deleted_names,
				],
			]);

			return;
		}

		ApiResponse::renderErrorObj(
			new ApiError('RESOURCE_DELETE_FAILED', $error_messages[0] ?? t('cms.resource.delete_failed')),
			400,
			['messages' => $error_messages]
		);
	}

	private function resolveResourceTreeNodeId(mixed $node_id): int
	{
		if ($node_id === ResourceTreeHandler::JSTREE_SITE_ROOT_ID) {
			return CmsSiteContext::getCurrentRootId() ?? 0;
		}

		return (int) $node_id;
	}
}
