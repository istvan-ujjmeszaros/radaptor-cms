<?php

class EventResourceAclSelectorAjaxSetOperation extends AbstractEvent
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
			$is_allowed = (int) Request::getRequired('checked');
			$operation = Request::getRequired('operation', [
				'allow_view',
				'allow_edit',
				'allow_delete',
				'allow_list',
				'allow_create',
			]);
		} catch (RequestParamException $e) {
			ApiResponse::renderError($e->code_id, $e->getMessage(), 400);

			return;
		}

		$acl_data = ResourceAcl::getAclValues($acl_id);

		if (is_null($acl_data)) {
			ApiResponse::renderError('ACL_NOT_FOUND', t('cms.resource_acl.acl_not_found', ['id' => $acl_id]), 404);

			return;
		}

		$savedata = [$operation => $is_allowed, ];

		$success = ResourceAcl::updateAcl($acl_id, $savedata) > 0;

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
