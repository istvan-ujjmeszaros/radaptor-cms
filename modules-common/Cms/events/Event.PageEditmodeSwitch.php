<?php

class EventPageEditmodeSwitch extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->inGroup(Usergroups::SYSTEMUSERGROUP_LOGGEDIN)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		if (Request::_GET('referer', Request::DEFAULT_ERROR)) {
			$referer = Request::_GET('referer', Request::DEFAULT_ERROR);
		} else {
			$referer = Url::getCurrentHost();
		}

		$value = Request::_GET('set');

		if (in_array($value, [
			'0',
			'1',
		])) {
			$result = User::setConfig(CmsConfig::EDITMODE, $value);

			if (is_null($result)) {
				SystemMessages::_error(t('cms.edit_mode.save_error'));
			} elseif ($value) {
				SystemMessages::_notice(t('cms.edit_mode.enabled'));
			} else {
				SystemMessages::_notice(t('cms.edit_mode.disabled'));
			}
		}

		Url::redirect($referer);
	}
}
