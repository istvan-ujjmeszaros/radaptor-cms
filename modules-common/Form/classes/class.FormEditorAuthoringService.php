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

		$page_url = $this->hostPageUrl($host['page_id']);
		$preview_url = $this->withFormEditorIframeParams($page_url);
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
					'csrf_token' => FormSubmitContext::issueCsrfTokenForForm(FormBuilderEventHelper::CSRF_INLINE_INSERT_FORM_ID),
					'urls' => [
						'undo' => Url::getUrl('form_editor.undo'),
						'redo' => Url::getUrl('form_editor.redo'),
						'update_form' => Url::getUrl('form_editor.update_form'),
						'publish' => Url::getUrl('form_builder.publish'),
					],
				],
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
			'form.builder.action.publish' => t('form.builder.action.publish'),
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

	private function withFormEditorIframeParams(string $url): string
	{
		$separator = str_contains($url, '?') ? '&' : '?';

		return $url . $separator . http_build_query([
			CmsConfig::EDITOR_IFRAME_PARAM => CmsConfig::EDITOR_IFRAME_VALUE,
			CmsConfig::EDITOR_SCOPE_PARAM => CmsConfig::EDITOR_SCOPE_FORM,
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
