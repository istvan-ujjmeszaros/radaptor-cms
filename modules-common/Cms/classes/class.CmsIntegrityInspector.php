<?php

declare(strict_types=1);

final class CmsIntegrityInspector
{
	/**
	 * @return array<string, mixed>
	 */
	public static function inspectSummary(): array
	{
		$layout = self::inspectLayouts();
		$form = self::inspectForms();
		$widget = self::inspectWidgets();
		$checks = [
			self::summaryRow('layouts', $layout['summary']),
			self::summaryRow('forms', $form['summary']),
			self::summaryRow('widgets', $widget['summary']),
		];

		return [
			'status' => self::aggregateStatus($checks),
			'checks' => $checks,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function inspectLayouts(?string $layout_id = null): array
	{
		$layout_id = self::normalizeOptionalFilter($layout_id);
		$layout_templates = self::collectLayoutTemplates();
		$layouts = [];

		foreach ($layout_templates as $layout => $templates) {
			if ($layout_id !== null && $layout !== $layout_id) {
				continue;
			}

			$layouts[] = self::inspectLayout($layout, $templates);
		}

		return [
			'status' => self::aggregateStatus($layouts),
			'layout' => $layout_id,
			'summary' => self::countStatuses($layouts),
			'layouts' => $layouts,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function inspectForms(?string $form_id = null): array
	{
		$form_id = self::normalizeOptionalFilter($form_id);
		$form_ids = AutoloaderFromGeneratedMap::getFilteredList('FormType');
		sort($form_ids);
		$forms = [];

		foreach ($form_ids as $form) {
			if ($form_id !== null && $form !== $form_id) {
				continue;
			}

			$forms[] = self::inspectForm($form);
		}

		return [
			'status' => self::aggregateStatus($forms),
			'form' => $form_id,
			'summary' => self::countStatuses($forms),
			'forms' => $forms,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function inspectWidgets(?string $widget_id = null): array
	{
		$widget_id = self::normalizeOptionalFilter($widget_id);
		$widget_ids = Widget::getRegisteredWidgetNames();
		sort($widget_ids);
		$widgets = [];

		foreach ($widget_ids as $widget) {
			if ($widget_id !== null && $widget !== $widget_id) {
				continue;
			}

			$widgets[] = self::inspectWidget($widget);
		}

		return [
			'status' => self::aggregateStatus($widgets),
			'widget' => $widget_id,
			'summary' => self::countStatuses($widgets),
			'widgets' => $widgets,
		];
	}

	/**
	 * @param list<array<string, mixed>> $templates
	 * @return array<string, mixed>
	 */
	private static function inspectLayout(string $layout, array $templates): array
	{
		$template_statuses = [];

		foreach ($templates as $template) {
			$contract = LayoutTemplateContractInspector::inspectFile((string) $template['path']);
			$template_statuses[] = [
				'template_name' => (string) $template['template_name'],
				'theme' => $template['theme'],
				'path' => (string) $template['path'],
				'status' => $contract['status'],
				'contract' => $contract,
			];
		}

		$usage = CmsUsageInspector::inspectLayoutUsage($layout);
		$messages = [];
		$layout_registered = Layout::checkLayoutExists($layout);
		$class = $layout_registered ? Layout::getLayoutClassName($layout) : null;
		$status = self::aggregateStatus($template_statuses);

		if (!$layout_registered) {
			$messages[] = 'Layout template is registered, but no matching layout type is registered.';
			$status = self::maxStatus($status, 'warning');
		}

		if ($templates === []) {
			$messages[] = 'No registered template file was found for this layout.';
			$status = 'error';
		}

		return [
			'layout' => $layout,
			'status' => $status,
			'registered_layout_type' => $layout_registered,
			'class' => $class,
			'template_count' => count($template_statuses),
			'usage_count' => (int) ($usage['count'] ?? 0),
			'messages' => $messages,
			'templates' => $template_statuses,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function inspectForm(string $form): array
	{
		$class = 'FormType' . $form;
		$messages = [];
		$status = 'ok';
		$default_path = self::inspectDefaultPath($class);
		$placements = self::findFormPlacements($form);

		if (!class_exists($class) || !is_subclass_of($class, AbstractForm::class)) {
			$messages[] = "Form class is not loadable: {$class}.";
			$status = 'error';
		}

		if (($default_path['valid'] ?? false) !== true) {
			$messages[] = 'Form default path is missing or incomplete.';
			$status = self::maxStatus($status, 'warning');
		}

		$default_page = self::resolveDefaultPage($default_path);

		if ($default_page === null) {
			$messages[] = 'Default form page is not present in the resource tree.';
			$status = self::maxStatus($status, 'warning');
		}

		if ($placements === []) {
			$messages[] = 'No Form widget placement is registered for this form.';
			$status = self::maxStatus($status, 'warning');
		}

		return [
			'form' => $form,
			'class' => $class,
			'status' => $status,
			'default_path' => $default_path,
			'default_page' => $default_page,
			'url' => $placements[0]['path'] ?? $default_page['path'] ?? null,
			'placement_count' => count($placements),
			'placements' => $placements,
			'messages' => $messages,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function inspectWidget(string $widget): array
	{
		$class = 'Widget' . ucwords($widget);
		$messages = [];
		$status = 'ok';
		$visible = false;
		$default_path = self::inspectDefaultPath($class);
		$placements = CmsAuthoringQueryHelper::getWidgetPlacements($widget);

		if (!class_exists($class) || !is_subclass_of($class, AbstractWidget::class)) {
			$messages[] = "Widget class is not loadable: {$class}.";
			$status = 'error';
		} else {
			$visible = (bool) $class::getListVisibility();
		}

		if (($default_path['valid'] ?? false) !== true && $visible) {
			$messages[] = 'Visible widget default path is missing or incomplete.';
			$status = self::maxStatus($status, 'warning');
		}

		$default_page = self::resolveDefaultPage($default_path);

		if ($default_page === null && $visible) {
			$messages[] = 'Visible widget default page is not present in the resource tree.';
			$status = self::maxStatus($status, 'warning');
		}

		if ($placements === [] && $visible) {
			$messages[] = 'Visible widget has no registered page placement.';
			$status = self::maxStatus($status, 'warning');
		}

		return [
			'widget' => $widget,
			'class' => $class,
			'status' => $status,
			'visible' => $visible,
			'default_path' => $default_path,
			'default_page' => $default_page,
			'url' => $placements[0]['path'] ?? $default_page['path'] ?? null,
			'placement_count' => count($placements),
			'placements' => $placements,
			'messages' => $messages,
		];
	}

	/**
	 * @return array<string, list<array<string, mixed>>>
	 */
	private static function collectLayoutTemplates(): array
	{
		$layouts = [];

		if (method_exists(TemplateList::class, 'getTemplates')) {
			foreach (TemplateList::getTemplates() as $template_name => $path) {
				if (!self::isLayoutTemplateName($template_name)) {
					continue;
				}

				$layout = substr($template_name, strlen('layout_'));
				$layouts[$layout][] = [
					'template_name' => $template_name,
					'theme' => null,
					'path' => $path,
				];
			}
		}

		if (method_exists(ThemedTemplateList::class, 'getThemedTemplates')) {
			foreach (ThemedTemplateList::getThemedTemplates() as $key => $path) {
				[$template_name, $theme] = array_pad(explode('.', $key, 2), 2, null);

				if (!is_string($theme) || !self::isLayoutTemplateName($template_name)) {
					continue;
				}

				$layout = substr($template_name, strlen('layout_'));
				$layouts[$layout][] = [
					'template_name' => $template_name,
					'theme' => $theme,
					'path' => $path,
				];
			}
		}

		foreach (Layout::getRegisteredLayoutTypes() as $layout) {
			$layout_instance = Layout::factory($layout);

			if ($layout_instance->getListVisibility()) {
				$layouts[$layout] ??= [];
			}
		}

		ksort($layouts);

		return $layouts;
	}

	private static function isLayoutTemplateName(string $template_name): bool
	{
		return str_starts_with($template_name, 'layout_') && !str_starts_with($template_name, 'layoutElement');
	}

	/**
	 * @param class-string|non-empty-string $class
	 * @return array<string, mixed>
	 */
	private static function inspectDefaultPath(string $class): array
	{
		if (!class_exists($class) || !method_exists($class, 'getDefaultPathForCreation')) {
			return ['valid' => false, 'path' => null, 'resource_name' => null, 'layout' => null, 'resolved_path' => null];
		}

		try {
			$path_data = $class::getDefaultPathForCreation();
		} catch (Throwable $exception) {
			return [
				'valid' => false,
				'path' => null,
				'resource_name' => null,
				'layout' => null,
				'resolved_path' => null,
				'error' => $exception->getMessage(),
			];
		}

		$path = isset($path_data['path']) ? (string) $path_data['path'] : '';
		$resource_name = isset($path_data['resource_name']) ? (string) $path_data['resource_name'] : '';
		$layout = isset($path_data['layout']) ? (string) $path_data['layout'] : '';
		$valid = $path !== '' && $resource_name !== '' && $layout !== '';

		return [
			'valid' => $valid,
			'path' => $path !== '' ? $path : null,
			'resource_name' => $resource_name !== '' ? $resource_name : null,
			'layout' => $layout !== '' ? $layout : null,
			'resolved_path' => $valid ? CmsPathHelper::normalizePath(rtrim($path, '/') . '/' . $resource_name) : null,
		];
	}

	/**
	 * @param array<string, mixed> $default_path
	 * @return array<string, mixed>|null
	 */
	private static function resolveDefaultPage(array $default_path): ?array
	{
		if (($default_path['valid'] ?? false) !== true) {
			return null;
		}

		$page = ResourceTreeHandler::getResourceTreeEntryData(
			(string) $default_path['path'],
			(string) $default_path['resource_name']
		);

		if (!is_array($page) || ($page['node_type'] ?? '') !== 'webpage') {
			return null;
		}

		$page_id = (int) $page['node_id'];

		return [
			'page_id' => $page_id,
			'path' => Url::getSeoUrl($page_id, false) ?? ResourceTreeHandler::getPathFromId($page_id),
		];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function findFormPlacements(string $form): array
	{
		$stmt = DbHelper::prexecute(
			"SELECT wc.page_id, wc.slot_name, wc.seq, wc.connection_id
			 FROM widget_connections wc
			 INNER JOIN attributes a ON a.resource_id = wc.connection_id
			 WHERE wc.widget_name = ?
			   AND a.resource_name = ?
			   AND a.param_name = 'form_id'
			   AND a.param_value = ?
			 ORDER BY wc.page_id, wc.slot_name, wc.seq",
			[
				WidgetList::FORM,
				ResourceNames::WIDGET_CONNECTION,
				$form,
			]
		);
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$placements = [];

		foreach ($rows as $row) {
			$page_id = (int) $row['page_id'];
			$placements[] = [
				'page_id' => $page_id,
				'path' => Url::getSeoUrl($page_id, false) ?? ResourceTreeHandler::getPathFromId($page_id),
				'slot' => (string) ($row['slot_name'] ?? ''),
				'seq' => (int) ($row['seq'] ?? 0),
				'connection_id' => (int) $row['connection_id'],
			];
		}

		return $placements;
	}

	/**
	 * @param array<string, mixed> $summary
	 * @return array<string, mixed>
	 */
	private static function summaryRow(string $name, array $summary): array
	{
		return [
			'name' => $name,
			'status' => self::statusFromCounts($summary),
			'ok' => (int) ($summary['ok'] ?? 0),
			'warning' => (int) ($summary['warning'] ?? 0),
			'error' => (int) ($summary['error'] ?? 0),
			'total' => (int) ($summary['total'] ?? 0),
		];
	}

	/**
	 * @param list<array<string, mixed>> $items
	 * @return array{ok: int, warning: int, error: int, total: int}
	 */
	private static function countStatuses(array $items): array
	{
		$counts = ['ok' => 0, 'warning' => 0, 'error' => 0, 'total' => count($items)];

		foreach ($items as $item) {
			$status = (string) ($item['status'] ?? 'error');
			$status = in_array($status, ['ok', 'warning', 'error'], true) ? $status : 'error';
			++$counts[$status];
		}

		return $counts;
	}

	/**
	 * @param list<array<string, mixed>> $items
	 */
	private static function aggregateStatus(array $items): string
	{
		$status = 'ok';

		foreach ($items as $item) {
			$status = self::maxStatus($status, (string) ($item['status'] ?? 'error'));
		}

		return $status;
	}

	/**
	 * @param array<string, mixed> $counts
	 */
	private static function statusFromCounts(array $counts): string
	{
		if ((int) ($counts['error'] ?? 0) > 0) {
			return 'error';
		}

		if ((int) ($counts['warning'] ?? 0) > 0) {
			return 'warning';
		}

		return 'ok';
	}

	private static function maxStatus(string $current, string $candidate): string
	{
		$rank = ['ok' => 0, 'warning' => 1, 'error' => 2];
		$current_rank = $rank[$current] ?? 2;
		$candidate_rank = $rank[$candidate] ?? 2;

		return $candidate_rank > $current_rank ? $candidate : $current;
	}

	private static function normalizeOptionalFilter(?string $filter): ?string
	{
		$filter = is_string($filter) ? trim($filter) : '';

		return $filter !== '' ? $filter : null;
	}
}
