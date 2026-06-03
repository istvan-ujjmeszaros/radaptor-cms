<?php

declare(strict_types=1);

final class EventFormEditorFragment extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->inGroup(Usergroups::SYSTEMUSERGROUP_LOGGEDIN)
			? PolicyDecision::allow('group: logged-in')
			: PolicyDecision::deny('group required: logged-in');
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'form.editor_fragment',
			'group' => 'CMS Authoring',
			'name' => 'Render form editor fragment',
			'summary' => 'Renders a single form fragment for page-editor properties panels without composing a full webpage.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('form_id', 'query', 'string', true, 'Form type id to render.'),
					BrowserEventDocumentationHelper::param('item_id', 'query', 'int', false, 'Optional edited item id.'),
					BrowserEventDocumentationHelper::param('host_page_id', 'query', 'int', false, 'Page that hosts the form widget.'),
					BrowserEventDocumentationHelper::param('form_widget_connection_id', 'query', 'int', false, 'Widget connection id of the form widget.'),
				],
			],
			'response' => [
				'kind' => 'html-fragment',
				'content_type' => 'text/html; charset=UTF-8',
			],
			'authorization' => [
				'visibility' => 'logged-in users + form policy',
			],
			'side_effects' => [],
		];
	}

	public function run(): void
	{
		WebpageView::header('Content-Type: text/html; charset=UTF-8');

		if (Request::getMethod() !== 'GET') {
			$this->renderFailure('response_error.access_denied', 405);

			return;
		}

		try {
			echo $this->renderFormFragment();
		} catch (Throwable) {
			$this->renderFailure('form.list.editor_load_failed', 422);
		}
	}

	private function renderFormFragment(): string
	{
		$form_id = trim((string)Request::_GET('form_id', ''));
		$form_class_name = FormClassResolver::resolveClassName($form_id);

		if ($form_id === '' || $form_class_name === null) {
			http_response_code(404);

			return $this->failureHtml('cms.form.no_id');
		}

		$missing = Request::getMissingParams($form_class_name::getRequiredUrlParams());

		if ($missing !== []) {
			http_response_code(400);

			return $this->failureHtml('common.missing_required_url_params');
		}

		$tree_context = $this->buildTreeContext((int)Request::_GET('host_page_id', 0));
		$form_widget_connection_id = (int)Request::_GET('form_widget_connection_id', 0);
		$form_instance_seed = $form_widget_connection_id > 0 ? (string)$form_widget_connection_id : 'editor_fragment';
		$return_target = Request::_GET('referer', false)
			? Url::sanitizeRefererUrl((string)Request::_GET('referer'))
			: Url::getCurrentUrlForReferer();

		$form = Form::factory(
			$form_id,
			md5($form_id . '_' . $form_instance_seed),
			$tree_context,
			null,
			[
				'host_page_id' => $tree_context->getPageId(),
				'widget_connection_id' => $form_widget_connection_id > 0 ? $form_widget_connection_id : null,
				'return_target' => $return_target,
			]
		);

		if (!$form->hasRole()) {
			http_response_code(403);

			return $this->failureHtml('response_error.access_denied');
		}

		$renderer = new HtmlTreeRenderer(
			theme: $tree_context->getTheme(),
			lang_id: Kernel::getLocale(),
			page_id: $tree_context->getPageId(),
			is_editable: false,
			template_class: HtmlFragmentTemplate::class,
		);
		$html = $renderer->render($form->buildTree());
		$assets = HtmlFragmentAssetRenderer::renderTemplatesFromRenderer($renderer);

		return '<div data-radaptor-form-editor-fragment="1">' . $html . '</div>' . $assets;
	}

	private function buildTreeContext(int $host_page_id): iTreeBuildContext
	{
		if ($host_page_id > 0) {
			$page_data = ResourceTypeWebpage::getResourceData($host_page_id);

			if (is_array($page_data) && ($page_data['node_type'] ?? null) === 'webpage') {
				return new WebpageView($page_data, false);
			}
		}

		return new WebpageView([], false);
	}

	private function renderFailure(string $message_key, int $http_code): void
	{
		http_response_code($http_code);
		echo $this->failureHtml($message_key);
	}

	private function failureHtml(string $message_key): string
	{
		return '<div class="alert alert-danger m-3" role="alert">' . e(t($message_key)) . '</div>';
	}
}
