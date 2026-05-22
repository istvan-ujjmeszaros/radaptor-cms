<?php

declare(strict_types=1);

final class EventFormBuilderCreate extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormBuilderEventHelper::authorizeContentAdmin($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'form_builder.create',
			'group' => 'CMS Authoring',
			'name' => 'Create capture form draft',
			'summary' => 'Creates a DB-authored capture form definition and its initial draft version.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('definition_slug', 'body', 'string', true, 'New capture definition slug.'),
					BrowserEventDocumentationHelper::param('title', 'body', 'string', false, 'Initial form title.'),
					BrowserEventDocumentationHelper::param('csrf_token', 'body', 'string', true, 'Session-bound builder CSRF token.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
			],
			'authorization' => [
				'visibility' => 'role:content_admin',
			],
			'side_effects' => BrowserEventDocumentationHelper::lines('Creates form_definitions and form_definition_versions draft rows.'),
		];
	}

	public function run(): void
	{
		if (Request::getMethod() !== 'POST') {
			FormBuilderEventHelper::renderFailure('FORM_BUILDER_METHOD_NOT_ALLOWED', 'response_error.access_denied', 405);

			return;
		}

		$csrf_error = FormBuilderEventHelper::validateCsrfFromPost();

		if ($csrf_error !== null) {
			FormBuilderEventHelper::renderCsrfError($csrf_error);

			return;
		}

		$definition_slug = FormCaptureDescriptorSchemaValidator::normalizeDefinitionSlugInput(
			(string)Request::_POST('definition_slug', ''),
		);

		try {
			FormCaptureDescriptorSchemaValidator::validateDefinitionSlug($definition_slug);
		} catch (InvalidArgumentException) {
			FormBuilderEventHelper::renderFailure('FORM_BUILDER_CREATE_INVALID_SLUG', 'form.builder.error_slug_format', 422);

			return;
		}

		if (EntityFormDefinition::findBySlug($definition_slug) instanceof EntityFormDefinition) {
			FormBuilderEventHelper::renderFailure('FORM_BUILDER_CREATE_DUPLICATE_SLUG', 'form.builder.error_slug_duplicate', 422);

			return;
		}

		try {
			$result = CmsMutationAuditService::withContext(
				'form_builder.create',
				['definition_slug' => $definition_slug],
				static fn (): array => (new FormCaptureAuthoringService())->createDefinition(
					$definition_slug,
					(string)Request::_POST('title', ''),
				),
			);
			ApiResponse::renderSuccess($result);
		} catch (Throwable) {
			FormBuilderEventHelper::renderFailure('FORM_BUILDER_CREATE_FAILED', 'form.builder.error_create', 422);
		}
	}
}
