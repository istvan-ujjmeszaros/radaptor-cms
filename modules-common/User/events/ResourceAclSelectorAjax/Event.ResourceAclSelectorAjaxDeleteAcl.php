<?php

class EventResourceAclSelectorAjaxDeleteAcl extends AbstractEvent
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
			$acl_id = (int) Request::getRequired('acl_id');
		} catch (RequestParamException $e) {
			ApiResponse::renderError($e->code_id, $e->getMessage(), 400);

			return;
		}

		$acl_data = ResourceAcl::getAclValues($acl_id);

		if (is_null($acl_data)) {
			ApiResponse::renderError('ACL_NOT_FOUND', t('cms.resource_acl.acl_not_found', ['id' => $acl_id]), 404);

			return;
		}

		$success = ResourceAcl::deleteAcl($acl_id);

		if ($success) {
			SystemMessages::_ok(t('cms.resource_acl.deleted'));
			ApiResponse::renderSuccess();
		} else {
			SystemMessages::_error(t('cms.resource_acl.error_delete'));
			ApiResponse::renderErrorObj(
				new ApiError('OPERATION_FAILED', t('cms.resource_acl.error_delete')),
				400
			);
		}
	}
}
