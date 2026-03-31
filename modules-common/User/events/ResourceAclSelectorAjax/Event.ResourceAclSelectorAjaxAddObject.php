<?php

class EventResourceAclSelectorAjaxAddObject extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		try {
			$object_type = Request::getRequired('object_type', [
				'user',
				'usergroup',
				'unknown',
			]);
			$object_name = Request::getRequired('object_name');
			$resource_id = (int) Request::getRequired('for_id');
		} catch (RequestParamException $e) {
			ApiResponse::renderError($e->code_id, $e->getMessage(), 400);

			return;
		}

		$user = User::getUserByName($object_name);
		$usergroup = Usergroups::getUsergroupByName($object_name);

		$resource_data = ResourceTreeHandler::getResourceTreeEntryDataById($resource_id);

		if (is_null($resource_data)) {
			ApiResponse::renderError('RESOURCE_NOT_FOUND', t('cms.resource_acl.resource_not_found', ['id' => $resource_id]), 400);

			return;
		}

		if ($object_type == 'unknown') {
			if (is_array($user) && is_array($usergroup)) {
				SystemMessages::_error(t('cms.resource_acl.error_ambiguous_subject', ['name' => $object_name]));
				ApiResponse::renderErrorObj(new ApiError('AMBIGUOUS_OBJECT', t('cms.resource_acl.error_ambiguous_subject_short')));

				exit;
			}

			if (is_array($user)) {
				$object_type = 'user';
			} elseif (is_array($usergroup)) {
				$object_type = 'usergroup';
			}
		} elseif (is_null($user) && is_null($usergroup)) {
			$object_type = 'unknown';
		}

		$success = false;

		switch ($object_type) {
			case 'user':

				$object_id = $user['user_id'];
				$success = ResourceAcl::assignToUser($object_id, $resource_id);

				break;

			case 'usergroup':

				$object_id = $usergroup['node_id'];
				$success = ResourceAcl::assignToUsergroup($object_id, $resource_id);

				break;

			case 'unknown':

				SystemMessages::_error(t('cms.resource_acl.error_subject_not_found', ['name' => $object_name]));

				ApiResponse::renderErrorObj(new ApiError('OBJECT_NOT_FOUND', t('cms.resource_acl.error_subject_not_found_short')));

				exit;
		}

		if ($success) {
			SystemMessages::_ok(t('common.saved'));
			ApiResponse::renderSuccess();
		} else {
			SystemMessages::_error(t('cms.resource_acl.error_save'));
			ApiResponse::renderErrorObj(
				new ApiError('OPERATION_FAILED', t('cms.resource_acl.error_save')),
				400
			);
		}
	}
}
