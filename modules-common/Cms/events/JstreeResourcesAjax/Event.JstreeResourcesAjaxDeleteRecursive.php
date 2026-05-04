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
			$id = (int) $id;
			$data = ResourceTreeHandler::getResourceTreeEntryDataById($id);

			if (!$data) {
				continue;
			}

			$last_parent_id = $data['parent_id'];

			try {
				$deletion_response_data = ResourceTreeHandler::deleteResourceEntriesRecursive($id);
			} catch (RuntimeException $exception) {
				$success = false;
				++$total_errors;
				$error_messages[] = $exception->getMessage();

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

		$total_deleted = $total_folders + $total_webpages + $total_files;

		if ($total_errors > 0) {
			SystemMessages::_error(
				$error_messages !== []
					? implode('<br>', array_map('e', $error_messages))
					: t('cms.resource.delete_partial_error', ['count' => $total_errors])
			);
		} elseif ($success && $total_deleted > 0) {
			$name_list = implode(', ', $deleted_names);
			$info_text = '';

			if ($total_deleted > 1) {
				$parts = [];

				if ($total_folders > 0) {
					$parts[] = t('cms.resource.folder_count', ['count' => $total_folders]);
				}

				if ($total_webpages > 0) {
					$parts[] = t('cms.resource.page_count', ['count' => $total_webpages]);
				}

				if (!empty($parts)) {
					$info_text = '<br><i>(' . implode(', ', $parts) . ')</i>';
				}
			}

			if (count($deleted_names) === 1) {
				SystemMessages::addSystemMessage(t('cms.resource.deleted') . "<br><i>{$name_list}</i>{$info_text}");
			} else {
				SystemMessages::addSystemMessage(t('cms.resource.deleted_multiple') . "<br><i>{$name_list}</i>{$info_text}");
			}
		} elseif (!$success) {
			SystemMessages::_error(t('cms.resource.delete_error'));
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

		JsTreeApiService::renderDeleteResponse($success, $json_data, ['messages' => $error_messages]);
	}
}
