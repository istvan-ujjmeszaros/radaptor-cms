<?php

declare(strict_types=1);

final class FormBuilderEventHelper
{
	public const string CSRF_FORM_ID = 'form_builder';
	public const string CSRF_INLINE_INSERT_FORM_ID = 'form_inline_insert';
	public const string CSRF_INLINE_FIELD_PROPERTIES_FORM_ID = 'form_inline_field_properties';

	public static function authorizeContentAdmin(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->hasRole(RoleList::ROLE_CONTENT_ADMIN)
			? PolicyDecision::allow('role: content_admin')
			: PolicyDecision::deny('role required: content_admin');
	}

	public static function validateCsrfFromPost(): ?ApiError
	{
		return FormSubmitContext::validateCsrfTokenForForm(self::CSRF_FORM_ID, Request::_POST(FormSubmitContext::FIELD_CSRF_TOKEN, null));
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function descriptorFromPost(): array
	{
		$descriptor_json = (string)Request::_POST('descriptor_json', '');

		try {
			$descriptor = json_decode($descriptor_json, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			throw new InvalidArgumentException('Descriptor JSON is invalid.', 0, $exception);
		}

		if (!is_array($descriptor)) {
			throw new InvalidArgumentException('Descriptor JSON must decode to an object.');
		}

		return $descriptor;
	}

	public static function boolPost(string $key): bool
	{
		$value = strtolower(trim((string)Request::_POST($key, '')));

		return in_array($value, ['1', 'true', 'yes', 'on'], true);
	}

	public static function assertEditableCaptureTarget(string $definition_slug, int $host_page_id, int $widget_connection_id): void
	{
		if ($definition_slug === '' || $host_page_id <= 0 || $widget_connection_id <= 0) {
			throw new UnexpectedValueException('Form editor target is incomplete.');
		}

		if (!ResourceAcl::canAccessResource($host_page_id, ResourceAcl::_ACL_EDIT)) {
			throw new UnexpectedValueException('The current user cannot edit the host page.');
		}

		$connection_data = Widget::getConnectionData($widget_connection_id);

		if (!is_array($connection_data) || (int)($connection_data['page_id'] ?? 0) !== $host_page_id) {
			throw new UnexpectedValueException('Form editor widget target is invalid.');
		}

		if ((string)($connection_data['widget_name'] ?? '') !== 'CaptureForm') {
			throw new UnexpectedValueException('Only capture form widgets are editable inline.');
		}

		$attributes = AttributeHandler::getAttributes(
			new AttributeResourceIdentifier(ResourceNames::WIDGET_CONNECTION, (string)$widget_connection_id)
		);

		if ((string)($attributes['definition_slug'] ?? '') !== $definition_slug) {
			throw new UnexpectedValueException('Form editor definition target does not match the widget.');
		}

		$resolution = FormDefinitionResolver::resolveForRender($definition_slug, ['structure_editable' => true]);

		if (!$resolution instanceof FormDefinitionResolution || !$resolution->isStructureEditable()) {
			throw new UnexpectedValueException('Capture form definition is not editable here.');
		}
	}

	public static function renderCsrfError(ApiError $error): void
	{
		ApiResponse::renderErrorObj(
			new ApiError($error->code, t('form.builder.error_csrf')),
			403,
		);
	}

	public static function renderFailure(string $code, string $message_key, int $http_code = 400): void
	{
		ApiResponse::renderErrorObj(new ApiError($code, t($message_key)), $http_code);
	}
}
