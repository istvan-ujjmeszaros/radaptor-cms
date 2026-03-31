<?php

class EventUserLogout extends AbstractEvent
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
			$referer = Url::sanitizeRefererUrl((string) Request::_GET('referer', Request::DEFAULT_ERROR));
		} else {
			$referer = Url::getCurrentHost();
		}

		User::logout();

		if (User::getCurrentUserId() < 0) {
			SystemMessages::_notice(t('user.logout.success'));
		}

		Url::redirect($referer);
	}
}
