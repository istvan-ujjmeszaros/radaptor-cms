<?php

declare(strict_types=1);

final class EventFormBuilderSaveDraft extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormBuilderEventHelper::authorizeContentAdmin($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'form_builder.save_draft',
			'group' => 'CMS Authoring',
			'name' => 'Save capture form draft',
			'summary' => 'Validates and saves one active DB-authored capture form draft version.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('definition_slug', 'body', 'string', true, 'Capture definition slug.'),
					BrowserEventDocumentationHelper::param('descriptor_json', 'body', 'json-object', true, 'Capture descriptor JSON.'),
					BrowserEventDocumentationHelper::param('base_server_hash', 'body', 'string', false, 'Hash of the server state the editor loaded.'),
					BrowserEventDocumentationHelper::param('overwrite', 'body', 'bool', false, 'Explicit conflict override flag.'),
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
			'side_effects' => BrowserEventDocumentationHelper::lines('Abandons previous active draft rows and saves one active draft row.'),
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

		$definition_slug = trim((string)Request::_POST('definition_slug', ''));

		try {
			$result = CmsMutationAuditService::withContext(
				'form_builder.save_draft',
				['definition_slug' => $definition_slug],
				static function () use ($definition_slug): array {
					$result = (new FormCaptureAuthoringService())->saveDraft(
						$definition_slug,
						FormBuilderEventHelper::descriptorFromPost(),
						(string)Request::_POST('base_server_hash', ''),
						FormBuilderEventHelper::boolPost('overwrite'),
					);

					if (($result['status'] ?? '') === 'conflict') {
						CmsMutationAuditService::recordLeaf('form_builder.save_draft.conflict', [
							'result_status' => 'conflict',
						]);
					}

					return $result;
				},
			);
			$http_code = ($result['status'] ?? '') === 'conflict' ? 409 : 200;
			ApiResponse::renderSuccess($result, null, null, $http_code);
		} catch (Throwable) {
			FormBuilderEventHelper::renderFailure('FORM_BUILDER_SAVE_DRAFT_FAILED', 'form.builder.error_save', 422);
		}
	}
}
