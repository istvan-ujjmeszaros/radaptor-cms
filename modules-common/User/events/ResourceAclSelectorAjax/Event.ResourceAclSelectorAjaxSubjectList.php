<?php

class EventResourceAclSelectorAjaxSubjectList extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return ($policyContext->principal->hasRole(RoleList::ROLE_ACL_VIEWER) || $policyContext->principal->hasRole(RoleList::ROLE_SYSTEM_DEVELOPER))
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		try {
			$resource_id = (int) Request::getRequired('for_id');
			$type = Request::getRequired('type', [
				'inherited',
				'specific',
			]);
		} catch (RequestParamException $e) {
			ApiResponse::renderError($e->code_id, $e->getMessage(), 400);

			return;
		}

		$subjectList = [];
		$readonly = false;

		switch ($type) {
			case 'inherited':

				$subjectList = ResourceAcl::getSubjectsListOfAncestorAclObjects($resource_id);
				$readonly = true;

				break;

			case 'specific':

				$subjectList = ResourceAcl::getSubjectsListOfAclObject($resource_id);

				break;

			default:

				ApiResponse::renderError('UNKNOWN_TYPE', t('cms.resource_acl.unknown_type', ['type' => $type]), 400);

				return;
		}

		$output['aaData'] = [];

		$name = '';

		foreach ($subjectList as $subject) {
			switch ($subject['subject_type']) {
				case 'usergroup':

					$subject_data = Usergroups::getUsergroupValues($subject['subject_id']);
					$name = Icons::get(IconNames::USERGROUP) . $subject_data['title'];

					break;

				case 'user':

					$subject_data = User::getUserFromId($subject['subject_id']);
					$name = Icons::get(IconNames::USER) . $subject_data['username'];

					break;

				default:

					ApiResponse::renderError('UNKNOWN_SUBJECT_TYPE', t('cms.resource_acl.unknown_subject_type', ['type' => $subject['subject_type']]), 400);

					return;
			}

			// oszlopok: név, megtekintés, szerkesztés, törlés
			$output['aaData'][] = [
				$name,
				$this->_getCheckbox($subject, 'allow_list', $readonly),
				$this->_getCheckbox($subject, 'allow_view', $readonly),
				$this->_getCheckbox($subject, 'allow_create', $readonly),
				$this->_getCheckbox($subject, 'allow_edit', $readonly),
				$this->_getCheckbox($subject, 'allow_delete', $readonly),
				$subject['acl_id'],
			];
		}

		ApiResponse::renderSuccess($output);
	}

	private function _getCheckbox(array $subject, string $operation, bool $readonly): string
	{
		if ($readonly) {
			$readonly_text = ' disabled="disabled"';
		} else {
			$readonly_text = '';
		}

		$checked = $subject[$operation];

		$id = "acl_{$subject['acl_id']}_{$operation}";

		$meta_data = [
			'acl_id' => $subject['acl_id'],
			'operation' => $operation,
		];

		$meta_data_text = Helpers::getHtmlDataAttribute($meta_data);

		if ($checked) {
			return '<input id="' . $id . '" type="checkbox" checked="checked"' . $readonly_text . $meta_data_text . '>';
		} else {
			return '<input id="' . $id . '" type="checkbox"' . $readonly_text . $meta_data_text . '>';
		}
	}
}
