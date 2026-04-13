<?php

class EventJstreeRolesAjaxDeleteRecursive extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->hasRole(RoleList::ROLE_ROLES_ADMIN)
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

		$total_deleted = 0;
		$deleted_titles = [];
		$last_parent_id = null;
		$success = true;

		foreach ($ids as $id) {
			$id = (int) $id;
			$data = Roles::getRoleValues($id);

			if (!$data) {
				continue;
			}

			$last_parent_id = $data['parent_id'];
			$deletion_response_data = Roles::deleteRoleRecursive($id);

			if ($deletion_response_data['success']) {
				$total_deleted += $deletion_response_data['role'];
				$deleted_titles[] = $data['title'];
			} else {
				$success = false;
			}
		}

		if ($success && $total_deleted > 0) {
			$title_list = implode(', ', $deleted_titles);

			if (count($deleted_titles) === 1) {
				SystemMessages::addSystemMessage(t('user.role.deleted', ['title' => $title_list]));
			} else {
				SystemMessages::addSystemMessage(t('user.role.deleted_multiple', ['titles' => $title_list, 'count' => $total_deleted]));
			}
		} elseif (!$success) {
			SystemMessages::_error(t('user.role.delete_error'));
		}

		$parent_data = $last_parent_id !== null ? Roles::getRoleValues((int) $last_parent_id) : null;

		$json_data = [
			'deleted_ids' => $ids,
			'parent_data' => $parent_data,
			'parent_node' => (is_array($parent_data) ? 'role' : 'root') . ($last_parent_id ? '_' . $last_parent_id : ''),
		];

		if ($success) {
			header(JsTreeApiService::buildHxTriggerHeaderLine('roleTreeDeleted', [
				'nodeIds' => array_map('intval', $ids),
				'success' => true,
			]));
		} else {
			header(JsTreeApiService::buildHxTriggerHeaderLine('roleTreeError', ['message' => t('user.role.delete_failed')]));
		}

		JsTreeApiService::renderDeleteResponse($success, $json_data);
	}
}
