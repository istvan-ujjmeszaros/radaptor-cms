<?php

class EventResourceAclSelectorAjaxSetInheritance extends AbstractEvent
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
			$resource_id = (int) Request::getRequired('for_id');
			$inheritance = (bool) Request::getRequired('inheritance');
		} catch (RequestParamException $e) {
			ApiResponse::renderError($e->code_id, $e->getMessage(), 400);

			return;
		}

		$resource_data = ResourceTreeHandler::getResourceTreeEntryDataById($resource_id);

		if (is_null($resource_data)) {
			ApiResponse::renderError('RESOURCE_NOT_FOUND', t('cms.resource_acl.resource_not_found', ['id' => $resource_id]), 400);

			return;
		}

		$success = ResourceTreeHandler::setInheritance($resource_id, $inheritance) > 0;

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
