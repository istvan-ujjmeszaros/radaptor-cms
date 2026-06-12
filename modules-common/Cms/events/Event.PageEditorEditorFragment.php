<?php

declare(strict_types=1);

final class EventPageEditorEditorFragment extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->hasRole(RoleList::ROLE_CONTENT_ADMIN)
			? PolicyDecision::allow('role: content_admin')
			: PolicyDecision::deny('role required: content_admin');
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'page_editor.editor_fragment',
			'group' => 'CMS Authoring',
			'name' => 'Render webpage editor fragment',
			'summary' => 'Renders the iframe-based webpage editor chrome for insertion into the resource browser modal.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('page_id', 'query', 'int', true, 'Resource tree webpage id.'),
				],
			],
			'response' => [
				'kind' => 'html',
				'content_type' => 'text/html; charset=UTF-8',
			],
			'authorization' => [
				'visibility' => 'role:content_admin',
			],
			'side_effects' => [],
		];
	}

	public function run(): void
	{
		if (Request::getMethod() !== 'GET') {
			WebpageView::header('Content-Type: text/html; charset=UTF-8');
			http_response_code(405);
			echo '<div class="alert alert-danger m-3" role="alert">' . e(t('response_error.access_denied')) . '</div>';

			return;
		}

		try {
			WebpageView::header('Content-Type: text/html; charset=UTF-8');
			echo (new PageEditorAuthoringService())->renderEditorFragment((int)Request::_GET('page_id', 0));
		} catch (Throwable) {
			WebpageView::header('Content-Type: text/html; charset=UTF-8');
			http_response_code(422);
			echo '<div class="alert alert-danger m-3" role="alert">' . e(t('cms.page_editor.editor_load_failed')) . '</div>';
		}
	}
}
