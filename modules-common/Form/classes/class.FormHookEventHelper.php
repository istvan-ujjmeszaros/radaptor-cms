<?php

declare(strict_types=1);

final class FormHookEventHelper
{
	public static function authorizeConfigurator(PolicyContext $policyContext): PolicyDecision
	{
		return (
			$policyContext->principal->hasRole(RoleList::ROLE_CONTENT_ADMIN)
			|| $policyContext->principal->hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)
		)
			? PolicyDecision::allow('role: content_admin or system_developer')
			: PolicyDecision::deny('role required: content_admin or system_developer');
	}

	public static function validateCsrfFromPost(): ?ApiError
	{
		return FormBuilderEventHelper::validateCsrfFromPost();
	}

	public static function definitionSlugFromRequest(): string
	{
		$definition_slug = Request::_POST('definition_slug', null);

		if ($definition_slug === null || $definition_slug === '') {
			$definition_slug = Request::_GET('definition_slug', Request::_GET('form', ''));
		}

		return trim((string)$definition_slug);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function hookInputFromPost(): array
	{
		return Request::getPOST();
	}

	public static function renderCsrfError(ApiError $error): void
	{
		FormBuilderEventHelper::renderCsrfError($error);
	}

	public static function renderException(FormHookConfigValidationException $exception): void
	{
		ApiResponse::renderErrorObj($exception->toApiError(), $exception->httpStatus());
	}

	public static function renderFailure(string $code, int $http_code = 400): void
	{
		ApiResponse::renderErrorObj(new ApiError($code, t('common.error_save')), $http_code);
	}
}
