<?php

declare(strict_types=1);

final class PageEditorAuthoringService
{
	public function buildEditorTree(int $page_id): array
	{
		$resource_data = $page_id > 0 ? ResourceTreeHandler::getResourceTreeEntryDataById($page_id) : null;

		if (!is_array($resource_data) || ($resource_data['node_type'] ?? null) !== 'webpage') {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'message' => t('cms.page_editor.page_not_found'),
			]);
		}

		if (!ResourceAcl::canAccessResource($page_id, ResourceAcl::_ACL_EDIT)) {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'message' => t('cms.page_editor.access_denied'),
			]);
		}

		$context = WidgetPlacementContext::fromPageId($page_id, ResourceTypeWebpage::DEFAULT_SLOT_NAME);
		$page_url = $this->buildPageUrl($page_id, $resource_data);
		$preview_url = $this->withPageEditorIframeParam($page_url);
		$strings = self::buildStrings();

		return SduiNode::create(
			component: 'pageEditor',
			props: [
				'page_id' => $page_id,
				'page_path' => ResourceTreeHandler::getPathFromId($page_id),
				'page_url' => $page_url,
				'preview_url' => $preview_url,
				'iframe_marker_param' => CmsConfig::PAGE_EDITOR_IFRAME_PARAM,
				'iframe_marker_value' => CmsConfig::PAGE_EDITOR_IFRAME_VALUE,
				'palette_groups' => (new WidgetPlacementService())->getGroupedPalette($context, true),
				'strings' => $strings,
			],
			type: SduiNode::TYPE_WIDGET,
			strings: $strings,
		);
	}

	public function renderEditorFragment(int $page_id): string
	{
		$renderer = new HtmlTreeRenderer(theme: $this->currentAdminTheme(), lang_id: Kernel::getLocale(), is_editable: false);

		return $renderer->render($this->buildEditorTree($page_id));
	}

	/**
	 * @return array<string, string>
	 */
	public static function buildStrings(): array
	{
		return [
			'cms.page_editor.title' => t('cms.page_editor.title'),
			'cms.page_editor.editor_title' => t('cms.page_editor.editor_title'),
			'cms.page_editor.editor_loading' => t('cms.page_editor.editor_loading'),
			'cms.page_editor.editor_load_failed' => t('cms.page_editor.editor_load_failed'),
			'cms.page_editor.page_loading' => t('cms.page_editor.page_loading'),
			'cms.page_editor.close' => t('cms.page_editor.close'),
			'cms.page_editor.palette' => t('cms.page_editor.palette'),
			'cms.page_editor.preview' => t('cms.page_editor.preview'),
			'cms.page_editor.properties' => t('cms.page_editor.properties'),
			'cms.page_editor.properties_loading' => t('cms.page_editor.properties_loading'),
			'cms.page_editor.properties_saving' => t('cms.page_editor.properties_saving'),
			'cms.page_editor.no_selection' => t('cms.page_editor.no_selection'),
			'cms.page_editor.panel.form_input' => t('cms.page_editor.panel.form_input'),
			'cms.page_editor.panel.widget' => t('cms.page_editor.panel.widget'),
			'cms.page_editor.open_page' => t('cms.page_editor.open_page'),
			'cms.page_editor.drop_hint' => t('cms.page_editor.drop_hint'),
			'cms.page_editor.page_not_found' => t('cms.page_editor.page_not_found'),
			'cms.page_editor.access_denied' => t('cms.page_editor.access_denied'),
			'cms.page_editor.status.ready' => t('cms.page_editor.status.ready'),
			'cms.page_editor.status.saving' => t('cms.page_editor.status.saving'),
			'cms.page_editor.status.drop_ready' => t('cms.page_editor.status.drop_ready'),
			'cms.page_editor.status.saved' => t('cms.page_editor.status.saved'),
			'cms.page_editor.status.error' => t('cms.page_editor.status.error'),
			'cms.widget_connection.once_per_domain_used' => t('cms.widget_connection.once_per_domain_used'),
			'widget.group.content' => t('widget.group.content'),
			'widget.group.forms' => t('widget.group.forms'),
			'widget.group.navigation' => t('widget.group.navigation'),
			'widget.group.admin' => t('widget.group.admin'),
			'widget.group.developer' => t('widget.group.developer'),
		];
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

	/**
	 * @param array<string, mixed> $resource_data
	 */
	private function buildPageUrl(int $page_id, array $resource_data): string
	{
		$seo_url = Url::getSeoUrl($page_id, false);

		// The editor iframe must stay same-origin so the page editor JS can reach
		// contentDocument; pages on another domain context get a host-qualified
		// (protocol-relative) SEO URL, so those use the resource.view form instead.
		if ($seo_url !== null && !str_starts_with($seo_url, '//')) {
			return $seo_url;
		}

		return Url::getUrl('resource.view', [
			'folder' => $resource_data['path'],
			'resource' => $resource_data['resource_name'],
			'domain_context' => ResourceTreeHandler::getDomainContextForResourceTreeEntryData($resource_data),
		]);
	}

	private function withPageEditorIframeParam(string $url): string
	{
		$fragment = '';
		$fragment_position = strpos($url, '#');

		if ($fragment_position !== false) {
			$fragment = substr($url, $fragment_position);
			$url = substr($url, 0, $fragment_position);
		}

		$separator = str_contains($url, '?') ? '&' : '?';

		return $url . $separator . http_build_query([
			CmsConfig::PAGE_EDITOR_IFRAME_PARAM => CmsConfig::PAGE_EDITOR_IFRAME_VALUE,
		]) . $fragment;
	}
}
