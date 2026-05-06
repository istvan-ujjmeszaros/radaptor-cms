<?php

declare(strict_types=1);

class CmsFragmentRenderer
{
	private const int MAX_TARGETS = 32;

	private WebpageView $view;
	private iLayoutType $layout;
	private WebpageTreeBuilder $treeBuilder;
	private HtmlTreeRenderer $renderer;

	public function __construct(private ResourceTypeWebpage $resource)
	{
		$this->view = $resource->getView();
		$this->layout = $this->view->getLayoutType();
		$this->treeBuilder = new WebpageTreeBuilder($this->view);
		$this->renderer = new HtmlTreeRenderer(
			theme: $this->view->getTheme(),
			lang_id: $this->view->getLangId(),
			page_id: $this->view->getPageId(),
			title: $this->view->getTitle(),
			description: $this->view->getRawDescription(),
			pagedata: $this->view->getAllPagedata(),
			is_editable: $this->view->isEditable(),
		);
	}

	public function renderDefaultPageFragment(): string
	{
		$this->assertPartialNavigableLayout();

		return $this->renderTargets($this->layout::getPageFragmentTargets());
	}

	/**
	 * @param list<string> $targets
	 */
	public function renderTargets(array $targets): string
	{
		$this->assertPartialNavigableLayout();
		$targets = $this->normalizeTargets($targets);

		if ($targets === []) {
			$targets = $this->normalizeTargets($this->layout::getPageFragmentTargets());
		}

		$oob_html = '';

		foreach ($targets as $target) {
			[$type, $name] = explode(':', $target, 2);
			$tree = match ($type) {
				'slot' => $this->buildSlotTargetTree($name),
				'widget' => $this->buildWidgetTargetTree((int)$name),
				'component' => $this->buildComponentTargetTree($name),
				default => throw new InvalidArgumentException("Unsupported fragment target type: {$type}"),
			};

			$tree['meta']['hx_swap_oob'] = true;
			$oob_html .= $this->renderer->render($tree);
		}

		$title = '<title>' . e($this->view->getTitle()) . '</title>';
		$assets = $this->renderAssetOobHtml();

		return $title . '<div hidden></div>' . $oob_html . $assets;
	}

	private function assertPartialNavigableLayout(): void
	{
		if (!$this->layout instanceof iPartialNavigableLayout) {
			throw new RuntimeException('The resolved webpage layout is not partial-navigable.');
		}
	}

	/**
	 * @param list<string> $targets
	 * @return list<string>
	 */
	private function normalizeTargets(array $targets): array
	{
		$normalized = [];

		foreach ($targets as $target) {
			$target = trim((string)$target);

			if ($target === '') {
				continue;
			}

			if (!preg_match('/^(component|slot|widget):([A-Za-z0-9_\\-]+)$/', $target, $matches)) {
				throw new InvalidArgumentException("Invalid fragment target: {$target}");
			}

			$type = $matches[1];
			$name = $matches[2];

			if ($type === 'widget' && !ctype_digit($name)) {
				throw new InvalidArgumentException("Invalid widget fragment target: {$target}");
			}

			$normalized[] = $type . ':' . $name;
		}

		$normalized = array_values(array_unique($normalized));

		if (count($normalized) > self::MAX_TARGETS) {
			throw new InvalidArgumentException('Too many fragment targets requested.');
		}

		return $normalized;
	}

	private function buildSlotTargetTree(string $slot_name): array
	{
		if (!in_array($slot_name, $this->layout::getSlots(), true)) {
			throw new RuntimeException("Fragment slot not found on resolved layout: {$slot_name}");
		}

		return $this->treeBuilder->buildSlotTargetTree($slot_name);
	}

	private function buildWidgetTargetTree(int $connection_id): array
	{
		if ($connection_id <= 0) {
			throw new InvalidArgumentException('Invalid widget connection id.');
		}

		$connection_data = Widget::getConnectionData($connection_id);

		if (!is_array($connection_data)) {
			throw new RuntimeException("Widget connection not found: {$connection_id}");
		}

		if ((int)($connection_data['page_id'] ?? 0) !== (int)$this->view->getPageId()) {
			throw new RuntimeException("Widget connection {$connection_id} does not belong to the resolved webpage.");
		}

		foreach (WidgetConnection::getWidgetsForSlot((int)$this->view->getPageId(), (string)$connection_data['slot_name']) as $connection) {
			if ($connection->getConnectionId() === $connection_id) {
				return $this->treeBuilder->buildWidgetTargetTree($connection);
			}
		}

		throw new RuntimeException("Widget connection not renderable: {$connection_id}");
	}

	private function buildComponentTargetTree(string $component_name): array
	{
		$component_map = $this->layout::getFragmentLayoutComponents();
		$component_class = $component_map[$component_name] ?? null;

		if ($component_class === null || !is_subclass_of($component_class, iLayoutComponent::class)) {
			throw new RuntimeException("Fragment layout component not found: {$component_name}");
		}

		$component = new $component_class($this->view);

		return $component->buildTree();
	}

	private function renderAssetOobHtml(): string
	{
		$html = '';

		foreach ($this->extractAssetTags($this->renderer->getCss() . $this->renderer->getJsTop() . $this->renderer->getJs()) as $tag) {
			$id = 'radaptor-asset-' . sha1($tag);
			$html .= '<template data-radaptor-fragment-assets>' . $this->withAssetAttributes($tag, $id) . '</template>';
		}

		return $html;
	}

	/**
	 * @return list<string>
	 */
	private function extractAssetTags(string $html): array
	{
		preg_match_all('/<link\\b[^>]*>/i', $html, $link_matches);
		preg_match_all('/<script\\b(?=[^>]*\\bsrc=)[^>]*>\\s*<\\/script>/i', $html, $script_matches);

		return array_values(array_unique([
			...($link_matches[0] ?? []),
			...($script_matches[0] ?? []),
		]));
	}

	private function withAssetAttributes(string $tag, string $id): string
	{
		if (preg_match('/\\sid=([\"\\\']).*?\\1/i', $tag)) {
			return preg_replace('/^(<(?:link|script)\\b)/i', '$1 data-radaptor-fragment-asset="1"', $tag, 1) ?? $tag;
		}

		return preg_replace('/^(<(?:link|script)\\b)/i', '$1 id="' . e($id) . '" data-radaptor-fragment-asset="1"', $tag, 1) ?? $tag;
	}
}
