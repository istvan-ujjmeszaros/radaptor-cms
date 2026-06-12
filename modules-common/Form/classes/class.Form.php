<?php

class Form
{
	/**
	 * @param array<string, mixed> $render_context
	 */
	public static function factory(string $form_type, string $form_id, iTreeBuildContext $tree_build_context, ?string $referer = null, array $render_context = []): AbstractForm
	{
		$resolution = $render_context['form_definition_resolution'] ?? null;

		if (!$resolution instanceof FormDefinitionResolution) {
			$resolution = FormDefinitionResolver::requireResolution($form_type);
		}

		$class = $resolution->className();
		$render_context['form_definition_resolution'] = $resolution;
		$form = new $class($form_type, $form_id, $tree_build_context, $referer, $render_context);

		if ($form instanceof AbstractForm) {
			return $form;
		}

		Kernel::abort($class . ' must implement iForm!');
	}

	public static function getVisibleFormTypes(): array
	{
		$return = [];

		$formTypes = AutoloaderFromGeneratedMap::getFilteredList('FormType');

		foreach ($formTypes as $formType) {
			$formClassName = "FormType" . $formType;

			if (!class_exists($formClassName) || !is_subclass_of($formClassName, 'AbstractForm')) {
				SystemMessages::_error("Requested form class '{$formClassName}' does not exist or does not implement AbstractForm.");

				return [];
			}

			if (!$formClassName::getListVisibility()) {
				continue;
			}

			$return[] = [
				'inputtype' => 'option',
				'value' => $formType,
				'label' => $formClassName::getName(),
			];
		}

		return $return;
	}

	public static function getSeoUrl($form_id, $item_id = null, $referer = null, $extra_params = []): string
	{
		$referer ??= Request::_GET('referer', Url::getCurrentUrlForReferer());
		$referer = Url::sanitizeRefererUrl((string) $referer);

		$page_id = ResourceTypeWebpage::getWebpageIdByFormType($form_id);

		if (!$page_id) {
			$page_id = ResourceTypeWebpage::getWebpageIdByFormType('');

			if (!$page_id) {
				SystemMessages::_warning(t('cms.form.no_form_page_warning'));

				return '';
			}

			$extra_params['form_id'] = $form_id;
		}

		$url = Url::getSeoUrl($page_id);

		if ($url === null) {
			SystemMessages::_warning(t('cms.form.no_form_page_warning'));

			return '';
		}

		$extra_params_text = '';

		foreach ($extra_params as $key => $value) {
			$extra_params_text .= '&' . $key . '=' . urlencode((string) $value);
		}

		if (is_null($item_id)) {
			return $url . '?' . ltrim($extra_params_text, '&') . ($extra_params_text ? '&' : '') . 'referer=' . urlencode((string) $referer);
		} else {
			return $url . '?item_id=' . $item_id . $extra_params_text . '&referer=' . urlencode((string) $referer);
		}
	}

	/**
	 * @param array<string, mixed> $extra_params
	 */
	public static function getEditorFragmentUrl(string $form_id, $item_id = null, ?string $referer = null, array $extra_params = []): string
	{
		$params = $extra_params;
		unset($params['context'], $params['event'], $params['folder'], $params['resource']);

		$params['form_id'] = $form_id;

		if (!is_null($item_id)) {
			$params['item_id'] = $item_id;
		}

		$referer_value = $referer ?? ($params['referer'] ?? Request::_GET('referer', Url::getCurrentUrlForReferer()));
		$params['referer'] = Url::sanitizeRefererUrl((string)$referer_value);

		return Url::getUrl('form.editor_fragment', $params);
	}

	public static function getEditorFragmentUrlFromSeoUrl(string $url): string
	{
		$url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

		if ($url === '') {
			return '';
		}

		$parsed_url = parse_url($url);

		if ($parsed_url === false) {
			return '';
		}

		$query_params = [];

		if (isset($parsed_url['query'])) {
			parse_str((string)$parsed_url['query'], $query_params);
		}

		$form_page_context = self::resolveEditorFragmentFormPageContext($parsed_url, $query_params);
		$form_id = trim((string)($query_params['form_id'] ?? $form_page_context['form_id'] ?? ''));

		if ($form_id === '') {
			return '';
		}

		$params = $query_params;

		if ((int)($form_page_context['page_id'] ?? 0) > 0) {
			$params['host_page_id'] = (int)$form_page_context['page_id'];
		}

		if ((int)($form_page_context['form_widget_connection_id'] ?? 0) > 0) {
			$params['form_widget_connection_id'] = (int)$form_page_context['form_widget_connection_id'];
		}

		return self::getEditorFragmentUrl(
			$form_id,
			$params['item_id'] ?? null,
			isset($params['referer']) ? (string)$params['referer'] : null,
			$params
		);
	}

	/**
	 * @param array<string, mixed> $parsed_url
	 * @param array<string, mixed> $query_params
	 * @return array{page_id?: int, form_id?: string, form_widget_connection_id?: int}
	 */
	private static function resolveEditorFragmentFormPageContext(array $parsed_url, array $query_params): array
	{
		$page_data = self::resolveEditorFragmentFormPageData($parsed_url, $query_params);

		if (!is_array($page_data)) {
			return [];
		}

		$page_id = (int)($page_data['node_id'] ?? 0);

		if ($page_id <= 0) {
			return [];
		}

		$connection = self::findFormWidgetConnectionOnPage($page_id);

		if (!$connection instanceof WidgetConnection) {
			return ['page_id' => $page_id];
		}

		return [
			'page_id' => $page_id,
			'form_id' => trim((string)$connection->getExtraparam('form_id')),
			'form_widget_connection_id' => $connection->getConnectionId(),
		];
	}

	/**
	 * @param array<string, mixed> $parsed_url
	 * @param array<string, mixed> $query_params
	 * @return array<string, mixed>|null
	 */
	private static function resolveEditorFragmentFormPageData(array $parsed_url, array $query_params): ?array
	{
		if (isset($query_params['folder'], $query_params['resource'])) {
			return ResourceTreeHandler::getResourceTreeEntryData(
				(string)$query_params['folder'],
				(string)$query_params['resource']
			);
		}

		[$folder, $resource_name] = self::folderAndResourceFromFormUrlPath((string)($parsed_url['path'] ?? '/'));

		return ResourceTreeHandler::getResourceTreeEntryData($folder, $resource_name);
	}

	/**
	 * @return array{0: string, 1: string}
	 */
	private static function folderAndResourceFromFormUrlPath(string $path): array
	{
		$path = rawurldecode($path);
		$path = '/' . ltrim($path, '/');

		if ($path === '/' || str_ends_with($path, '/')) {
			return [$path, 'index.html'];
		}

		$last_slash = strrpos($path, '/');

		if ($last_slash === false) {
			return ['/', $path];
		}

		$folder = substr($path, 0, $last_slash + 1);
		$resource_name = substr($path, $last_slash + 1);

		return [$folder !== '' ? $folder : '/', $resource_name !== '' ? $resource_name : 'index.html'];
	}

	private static function findFormWidgetConnectionOnPage(int $page_id): ?WidgetConnection
	{
		foreach (WidgetConnection::getWidgetsForSlot($page_id, ResourceTypeWebpage::DEFAULT_SLOT_NAME) as $connection) {
			if ($connection->getWidgetName() === WidgetList::FORM) {
				return $connection;
			}
		}

		foreach (WidgetConnection::getWidgetsForPageGroupedBySlot($page_id) as $connections) {
			foreach ($connections as $connection) {
				if ($connection->getWidgetName() === WidgetList::FORM) {
					return $connection;
				}
			}
		}

		return null;
	}
}
