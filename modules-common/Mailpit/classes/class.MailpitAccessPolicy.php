<?php

declare(strict_types=1);

final class MailpitAccessPolicy
{
	private function __construct()
	{
	}

	public static function isAllowed(): bool
	{
		return Kernel::getEnvironment() !== 'production'
			&& (
				Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)
				|| Roles::hasRole(RoleList::ROLE_EMAILS_ADMIN)
			);
	}

	public static function authorize(): PolicyDecision
	{
		return self::isAllowed()
			? PolicyDecision::allow('mailpit catcher access granted')
			: PolicyDecision::deny('mailpit catcher requires non-production and email/developer role');
	}
}
