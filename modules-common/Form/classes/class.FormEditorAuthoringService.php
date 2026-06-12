<?php

declare(strict_types=1);

/**
 * Builds the unified editor component for capture form definitions. Mirrors
 * PageEditorAuthoringService: same component shape, scope=form — the preview iframe
 * renders the form's host page with form-field inserters only, the palette lists
 * field types, and mutations flow through the form_editor.* events.
 */
final class FormEditorAuthoringService
{
	public function buildEditorTree(string $definition_slug): array
	{
		$definition_slug = trim($definition_slug);
		$capture = new FormCaptureAuthoringService();

		try {
			$state = $capture->loadDefinition($definition_slug);
		} catch (InvalidArgumentException) {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'message' => t('form.editor.definition_not_found'),
			]);
		}

		if ((bool)($state['read_only'] ?? false)) {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'message' => t('form.editmode_readonly.system_defined'),
			]);
		}

		$host = $this->resolveEditableHost($capture, $definition_slug);

		if ($host === null) {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'message' => t('form.editor.no_host_page'),
			]);
		}

		// Undo/redo scope: one token per editor open. It rides the iframe URL, the
		// editor controller propagates it onto every mutation request, and a reload
		// starts a clean session. Stale sessions are swept opportunistically here.
		$capture->purgeStaleEditorSessions();
		$session_token = bin2hex(random_bytes(16));

		$page_url = $this->hostPageUrl($host['page_id']);
		$preview_url = $this->withFormEditorIframeParams(
			Url::getUrl('form_editor.canvas', ['definition_slug' => $definition_slug]),
			$session_token,
			$definition_slug,
		);
		$strings = self::buildStrings();

		return SduiNode::create(
			component: 'pageEditor',
			props: [
				'scope' => CmsConfig::EDITOR_SCOPE_FORM,
				'page_id' => $host['page_id'],
				'page_path' => (string)($state['definition']['definition_slug'] ?? $definition_slug),
				'page_url' => $page_url,
				'preview_url' => $preview_url,
				'iframe_marker_param' => CmsConfig::EDITOR_IFRAME_PARAM,
				'iframe_marker_value' => CmsConfig::EDITOR_IFRAME_VALUE,
				'palette_groups' => $this->paletteGroups(),
				'form_context' => [
					'definition_slug' => $definition_slug,
					'host_page_id' => $host['page_id'],
					'widget_connection_id' => $host['connection_id'],
					'session_token' => $session_token,
					'session_param' => CmsConfig::EDITOR_SESSION_PARAM,
					'csrf_token' => FormSubmitContext::issueCsrfTokenForForm(FormBuilderEventHelper::CSRF_INLINE_INSERT_FORM_ID),
					// form_builder.publish and form_builder.update_draft_note validate the
					// builder-level CSRF form, not the inline one.
					'publish_csrf_token' => FormSubmitContext::issueCsrfTokenForForm(FormBuilderEventHelper::CSRF_FORM_ID),
					'urls' => [
						'undo' => Url::getUrl('form_editor.undo'),
						'redo' => Url::getUrl('form_editor.redo'),
						'update_form' => Url::getUrl('form_editor.update_form'),
						'publish' => Url::getUrl('form_builder.publish'),
						'restore_version' => Url::getUrl('form_editor.restore_version'),
						'update_draft_note' => Url::getUrl('form_builder.update_draft_note'),
						'state' => Url::getUrl('form_editor.state'),
					],
					'published_version_id' => (int)($state['definition']['published_version_id'] ?? 0),
				],
				'editor_state' => $capture->editorStateForDefinition($definition_slug, $session_token),
				'strings' => $strings,
			],
			type: SduiNode::TYPE_WIDGET,
			strings: $strings,
		);
	}

	public function renderEditorFragment(string $definition_slug): string
	{
		$renderer = new HtmlTreeRenderer(theme: $this->currentAdminTheme(), lang_id: Kernel::getLocale(), is_editable: false);

		return $renderer->render($this->buildEditorTree($definition_slug));
	}

	/**
	 * The editing canvas: a minimal standalone document containing ONLY the target
	 * form's widget, rendered through the host page's real widget pipeline so the
	 * markup, fragment ids, and edit chrome match what the mutation responder swaps.
	 */
	public function renderEditorCanvas(string $definition_slug): string
	{
		$definition_slug = trim($definition_slug);
		$capture = new FormCaptureAuthoringService();
		$capture->loadDefinition($definition_slug);
		$host = $this->resolveEditableHost($capture, $definition_slug);

		if ($host === null) {
			throw new InvalidArgumentException('Capture form definition has no editable host page.');
		}

		$resource = ResourceTypeFactory::Factory($host['page_id']);

		if (!$resource instanceof ResourceTypeWebpage) {
			throw new RuntimeException('Form editor host page is not a webpage.');
		}

		$view = $resource->getView();
		$connection_data = Widget::getConnectionData($host['connection_id']);

		if (!is_array($connection_data)) {
			throw new RuntimeException('Form editor host widget connection not found.');
		}

		$widget_tree = null;

		foreach (WidgetConnection::getWidgetsForSlot($host['page_id'], (string)$connection_data['slot_name']) as $connection) {
			if ($connection->getConnectionId() === $host['connection_id']) {
				$widget_tree = (new WebpageTreeBuilder($view))->buildWidgetTargetTree($connection);

				break;
			}
		}

		if ($widget_tree === null) {
			throw new RuntimeException('Form editor host widget is not renderable.');
		}

		$renderer = new HtmlTreeRenderer(
			theme: $view->getTheme(),
			lang_id: $view->getLangId(),
			page_id: $view->getPageId(),
			title: $view->getTitle(),
			description: $view->getRawDescription(),
			pagedata: $view->getAllPagedata(),
			is_editable: $view->isEditable(),
		);
		$html = $renderer->render($widget_tree);

		return '<!doctype html>'
			. '<html lang="' . e((string)$view->getLangId()) . '">'
			. '<head>'
			. '<meta charset="utf-8">'
			. '<meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<meta name="robots" content="noindex">'
			. '<title>' . e(t('form.editor.title')) . '</title>'
			. $renderer->getCss()
			. $renderer->getJsTop()
			. '<style>body{margin:0;padding:16px}</style>'
			. '</head>'
			. '<body class="radaptor-editor-canvas">'
			. $html
			. $renderer->getJs()
			. '</body>'
			. '</html>';
	}

	/**
	 * @return array<string, string>
	 */
	public static function buildStrings(): array
	{
		return PageEditorAuthoringService::buildStrings() + [
			'form.editor.title' => t('form.editor.title'),
			'form.editor.palette' => t('form.editor.palette'),
			'form.editor.no_host_page' => t('form.editor.no_host_page'),
			'form.editor.action_undo' => t('form.editor.action_undo'),
			'form.editor.action_redo' => t('form.editor.action_redo'),
			'form.editor.versions' => t('form.editor.versions'),
			'form.editor.no_versions' => t('form.editor.no_versions'),
			'form.editor.action_restore' => t('form.editor.action_restore'),
			'form.editor.version_published' => t('form.editor.version_published'),
			'form.editor.version_draft' => t('form.editor.version_draft'),
			'form.editor.note_placeholder' => t('form.editor.note_placeholder'),
			'form.editor.action_save_note' => t('form.editor.action_save_note'),
			'form.editor.used_on_pages' => t('form.editor.used_on_pages'),
			'form.builder.action.publish' => t('form.builder.action.publish'),
			'form.builder.properties' => t('form.builder.properties'),
			'form.builder.label.title' => t('form.builder.label.title'),
			'form.builder.label.description' => t('form.builder.label.description'),
			'form.builder.label.submit_label' => t('form.builder.label.submit_label'),
		];
	}

	/**
	 * The form editor edits the definition where it is actually placed: the first
	 * usage page the current user may edit. Unplaced definitions have no host yet.
	 *
	 * @return array{page_id: int, connection_id: int}|null
	 */
	private function resolveEditableHost(FormCaptureAuthoringService $capture, string $definition_slug): ?array
	{
		foreach ($capture->usageForDefinition($definition_slug) as $usage) {
			$page_id = (int)($usage['page_id'] ?? 0);
			$connection_id = (int)($usage['connection_id'] ?? 0);

			if ($page_id > 0 && $connection_id > 0 && ResourceAcl::canAccessResource($page_id, ResourceAcl::_ACL_EDIT)) {
				return [
					'page_id' => $page_id,
					'connection_id' => $connection_id,
				];
			}
		}

		return null;
	}

	/**
	 * @return list<array{id: string, label: string, items: list<array<string, mixed>>}>
	 */
	private function paletteGroups(): array
	{
		$items = [];

		foreach ((new FormCaptureEditorPaletteProvider())->getPaletteItems() as $item) {
			$data = $item->toArray();
			$items[] = [
				'type' => (string)($data['type'] ?? ''),
				'label' => (string)($data['label'] ?? ($data['type'] ?? '')),
				'disabled' => false,
			];
		}

		return [[
			'id' => 'fields',
			'label' => t('form.editor.palette'),
			'items' => $items,
		]];
	}

	private function hostPageUrl(int $page_id): string
	{
		$seo_url = Url::getSeoUrl($page_id, false);

		// Same-origin requirement as the page editor preview: cross-context pages use
		// the resource.view form (see PageEditorAuthoringService::buildPageUrl).
		if ($seo_url !== null && !str_starts_with($seo_url, '//')) {
			return $seo_url;
		}

		$resource_data = ResourceTreeHandler::getResourceTreeEntryDataById($page_id) ?? [];

		return Url::getUrl('resource.view', [
			'folder' => (string)($resource_data['path'] ?? ''),
			'resource' => (string)($resource_data['resource_name'] ?? ''),
			'domain_context' => ResourceTreeHandler::getDomainContextForResourceTreeEntryData($resource_data),
		]);
	}

	private function withFormEditorIframeParams(string $url, string $session_token, string $definition_slug): string
	{
		$separator = str_contains($url, '?') ? '&' : '?';

		return $url . $separator . http_build_query([
			CmsConfig::EDITOR_IFRAME_PARAM => CmsConfig::EDITOR_IFRAME_VALUE,
			CmsConfig::EDITOR_SCOPE_PARAM => CmsConfig::EDITOR_SCOPE_FORM,
			CmsConfig::EDITOR_SESSION_PARAM => $session_token,
			CmsConfig::EDITOR_TARGET_PARAM => $definition_slug,
		]);
	}

	/**
	 * @param array<string, mixed> $props
	 * @return array<string, mixed>
	 */
	private function buildStatusTree(array $props): array
	{
		return SduiNode::create('statusMessage', $props, type: SduiNode::TYPE_WIDGET);
	}

	private function currentAdminTheme(): ?AbstractThemeData
	{
		$theme_name = Themes::getThemeNameForUser('admin_default');

		return $theme_name !== '' ? ThemeBase::factory($theme_name) : null;
	}
}
