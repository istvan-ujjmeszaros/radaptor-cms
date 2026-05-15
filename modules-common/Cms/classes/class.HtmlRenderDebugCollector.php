<?php

declare(strict_types=1);

/**
 * Request-local debug state owned by one HtmlTreeRenderer instance.
 */
class HtmlRenderDebugCollector
{
	private const int MAX_STRING_LENGTH = 240;
	private const int MAX_ARRAY_DEPTH = 2;
	private const int MAX_ARRAY_ITEMS = 50;

	/** @var list<string> */
	private const array SENSITIVE_KEYS = [
		'password',
		'token',
		'secret',
		'key',
		'api_key',
		'session',
		'csrf',
	];

	private int $_nextNodeIndex = 0;

	/** @var list<string> */
	private array $_roots = [];

	/** @var list<string> */
	private array $_frameStack = [];

	/** @var array<string, array<string, mixed>> */
	private array $_nodes = [];

	/** @var array<string, float> */
	private array $_frameStartedAt = [];

	/** @var array<string, float> */
	private array $_childTotalMs = [];

	/** @var list<array<string, mixed>> */
	private array $_messages = [];

	/**
	 * @param array<string, mixed> $node
	 */
	public function pushFrame(array $node, ?string $ownerWidgetConnectionId): string
	{
		$nodeId = 'n' . $this->_nextNodeIndex++;
		$parentId = $this->currentNodeId();
		$meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
		$component = (string)($node['component'] ?? '');
		$props = is_array($node['props'] ?? null) ? $node['props'] : [];
		$stableContainerId = $this->normalizeStableContainerId($meta['stable_container_id'] ?? null);
		$source = $this->resolveSource($meta);

		if ($parentId === null) {
			$this->_roots[] = $nodeId;
		} elseif (isset($this->_nodes[$parentId])) {
			$this->_nodes[$parentId]['children'][] = $nodeId;
		}

		$this->_nodes[$nodeId] = [
			'id' => $nodeId,
			'parentId' => $parentId,
			'children' => [],
			'type' => $this->resolveNodeType($node),
			'component' => $component,
			'templateName' => $component,
			'widgetName' => $this->readWidgetConnectionValue($meta, 'widget_name'),
			'ownerWidgetConnectionId' => $ownerWidgetConnectionId,
			'slotName' => $this->resolveSlotName($node, $meta, $stableContainerId),
			'seq' => $this->normalizeNullableInt($this->readWidgetConnectionValue($meta, 'seq')),
			'domMode' => $stableContainerId !== null ? 'stable-container' : 'none',
			'stableContainerId' => $stableContainerId,
			'propsPreview' => $this->previewValue($props),
			'source' => $source,
			'timings' => [
				'totalMs' => 0.0,
				'templateMs' => 0.0,
				'selfMs' => 0.0,
			],
			'messages' => [],
		];

		$this->_frameStack[] = $nodeId;
		$this->_frameStartedAt[$nodeId] = microtime(true);
		$this->_childTotalMs[$nodeId] = 0.0;

		return $nodeId;
	}

	public function popFrame(string $nodeId, string $renderedHtml): void
	{
		$startedAt = $this->_frameStartedAt[$nodeId] ?? microtime(true);
		$totalMs = $this->roundMs((microtime(true) - $startedAt) * 1000);
		$templateMs = (float)($this->_nodes[$nodeId]['timings']['templateMs'] ?? 0.0);
		$childTotalMs = $this->_childTotalMs[$nodeId] ?? 0.0;
		$selfMs = $this->roundMs(max(0.0, $totalMs - $childTotalMs - $templateMs));

		if (isset($this->_nodes[$nodeId])) {
			$this->_nodes[$nodeId]['timings']['totalMs'] = $totalMs;
			$this->_nodes[$nodeId]['timings']['selfMs'] = $selfMs;
		}

		$parentId = $this->_nodes[$nodeId]['parentId'] ?? null;

		if (is_string($parentId)) {
			$this->_childTotalMs[$parentId] = ($this->_childTotalMs[$parentId] ?? 0.0) + $totalMs;
		}

		$this->popStackFrame($nodeId);
		unset($this->_frameStartedAt[$nodeId], $this->_childTotalMs[$nodeId]);
	}

	/**
	 * @param array<string, mixed> $meta
	 * @return array<string, string>
	 */
	public function stableContainerAttributes(string $nodeId, string $stableContainerId, array $meta): array
	{
		if (!isset($this->_nodes[$nodeId])) {
			return [];
		}

		$this->_nodes[$nodeId]['domMode'] = 'stable-container';
		$this->_nodes[$nodeId]['stableContainerId'] = $stableContainerId;

		$attributes = [
			'data-radaptor-node' => $nodeId,
			'data-radaptor-type' => (string)$this->_nodes[$nodeId]['type'],
		];
		$ownerWidgetConnectionId = $this->_nodes[$nodeId]['ownerWidgetConnectionId'] ?? null;
		$widgetName = $this->readWidgetConnectionValue($meta, 'widget_name');
		$slotName = $this->_nodes[$nodeId]['slotName'] ?? null;

		if (is_string($ownerWidgetConnectionId) && $ownerWidgetConnectionId !== '') {
			$attributes['data-radaptor-owner'] = $ownerWidgetConnectionId;
		}

		if (is_string($widgetName) && $widgetName !== '') {
			$attributes['data-radaptor-widget'] = $widgetName;
		}

		if (is_string($slotName) && $slotName !== '') {
			$attributes['data-radaptor-slot'] = $slotName;
		}

		return $attributes;
	}

	public function recordTemplateDebug(string $templateName, string $templatePath, float $durationMs): void
	{
		$nodeId = $this->currentNodeId();

		if ($nodeId === null || !isset($this->_nodes[$nodeId])) {
			return;
		}

		$this->_nodes[$nodeId]['templateName'] = $templateName;
		$this->_nodes[$nodeId]['source']['templatePath'] = $templatePath;
		$this->_nodes[$nodeId]['timings']['templateMs'] = $this->roundMs(
			(float)$this->_nodes[$nodeId]['timings']['templateMs'] + $durationMs
		);
	}

	public function recordMessage(DebugMessage $message): void
	{
		$normalized = $this->normalizeMessage($message);
		$nodeId = is_string($normalized['nodeId'] ?? null) ? $normalized['nodeId'] : $this->currentNodeId();
		$normalized['nodeId'] = $nodeId;
		$this->_messages[] = $normalized;

		if ($nodeId !== null && isset($this->_nodes[$nodeId])) {
			$this->_nodes[$nodeId]['messages'][] = $normalized;
		}
	}

	/**
	 * @return array{roots: list<string>, nodes: array<string, array<string, mixed>>, messages: list<array<string, mixed>>}
	 */
	public function toBootstrap(): array
	{
		return [
			'roots' => $this->_roots,
			'nodes' => $this->_nodes,
			'messages' => $this->_messages,
		];
	}

	public function currentNodeId(): ?string
	{
		if ($this->_frameStack === []) {
			return null;
		}

		return $this->_frameStack[array_key_last($this->_frameStack)];
	}

	/**
	 * @param array<string, mixed> $node
	 */
	private function resolveNodeType(array $node): string
	{
		$component = (string)($node['component'] ?? '');
		$type = (string)($node['type'] ?? SduiNode::TYPE_SUB);

		if ($component === '_contentContainer') {
			return 'slot';
		}

		if ($component === 'widgetInsert' || $component === 'addWidgetFromList') {
			return 'edit-inserter';
		}

		if ($type === SduiNode::TYPE_WIDGET) {
			return 'widget';
		}

		if (str_starts_with($component, 'layout_')) {
			return 'layout';
		}

		return 'sub';
	}

	/**
	 * @param array<string, mixed> $node
	 * @param array<string, mixed> $meta
	 */
	private function resolveSlotName(array $node, array $meta, ?string $stableContainerId): ?string
	{
		$slotName = $this->readWidgetConnectionValue($meta, 'slot_name');

		if (is_string($slotName) && $slotName !== '') {
			return $slotName;
		}

		if (($node['component'] ?? null) === '_contentContainer' && is_string($stableContainerId) && str_starts_with($stableContainerId, 'slot-')) {
			return substr($stableContainerId, 5);
		}

		$props = is_array($node['props'] ?? null) ? $node['props'] : [];

		if (is_string($props['slot_name'] ?? null) && $props['slot_name'] !== '') {
			return $props['slot_name'];
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	private function readWidgetConnectionValue(array $meta, string $key): mixed
	{
		$widgetConnection = $meta['widget_connection'] ?? null;

		if (is_array($widgetConnection)) {
			return $widgetConnection[$key] ?? null;
		}

		if (!$widgetConnection instanceof WidgetConnection) {
			return null;
		}

		return match ($key) {
			'connection_id' => $widgetConnection->getConnectionId(),
			'widget_name' => $widgetConnection->getWidgetName(),
			'slot_name' => $widgetConnection->getSlotName(),
			'seq' => $widgetConnection->seq(),
			default => null,
		};
	}

	private function normalizeStableContainerId(mixed $stableContainerId): ?string
	{
		$stableContainerId = trim((string)$stableContainerId);

		return $stableContainerId !== '' ? $stableContainerId : null;
	}

	private function normalizeNullableInt(mixed $value): ?int
	{
		if ($value === null || $value === '') {
			return null;
		}

		return is_numeric($value) ? (int)$value : null;
	}

	/**
	 * @param array<string, mixed> $meta
	 * @return array{templatePath: null, class: string|null, file: string|null, line: int|null}
	 */
	private function resolveSource(array $meta): array
	{
		$className = null;
		$file = null;
		$line = null;
		$widgetName = $this->readWidgetConnectionValue($meta, 'widget_name');

		if (is_string($widgetName) && $widgetName !== '') {
			$className = 'Widget' . (string)preg_replace('/[^A-Za-z0-9_]/', '', $widgetName);

			if (class_exists($className, false)) {
				$reflection = new ReflectionClass($className);
				$file = $reflection->getFileName() ?: null;
				$line = $reflection->getStartLine();
			}
		}

		return [
			'templatePath' => null,
			'class' => $className,
			'file' => $file,
			'line' => $line,
		];
	}

	/**
	 * @return mixed
	 */
	private function previewValue(mixed $value, int $depth = 0, string|int|null $key = null): mixed
	{
		if ($key !== null && $this->isSensitiveKey($key)) {
			return '***';
		}

		if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
			return $value;
		}

		if (is_string($value)) {
			return $this->truncateString($value);
		}

		if (is_object($value)) {
			return [
				'__class' => $value::class,
			];
		}

		if (is_resource($value)) {
			return [
				'__resource' => get_resource_type($value),
			];
		}

		if (!is_array($value)) {
			return get_debug_type($value);
		}

		if ($depth >= self::MAX_ARRAY_DEPTH) {
			return [
				'__type' => 'array',
				'__count' => count($value),
			];
		}

		$output = [];
		$count = 0;

		foreach ($value as $itemKey => $itemValue) {
			if ($count >= self::MAX_ARRAY_ITEMS) {
				$output['__truncated'] = count($value) - self::MAX_ARRAY_ITEMS;

				break;
			}

			$output[$itemKey] = $this->previewValue($itemValue, $depth + 1, $itemKey);
			++$count;
		}

		return $output;
	}

	private function isSensitiveKey(string|int $key): bool
	{
		if (is_int($key)) {
			return false;
		}

		$normalized = strtolower(str_replace(['-', ' '], '_', $key));

		if (in_array($normalized, self::SENSITIVE_KEYS, true)) {
			return true;
		}

		foreach (['password', 'token', 'secret', 'session', 'csrf'] as $needle) {
			if (str_contains($normalized, $needle)) {
				return true;
			}
		}

		return false;
	}

	private function truncateString(string $value): string
	{
		if (mb_strlen($value) <= self::MAX_STRING_LENGTH) {
			return $value;
		}

		return mb_substr($value, 0, self::MAX_STRING_LENGTH - 3) . '...';
	}

	private function roundMs(float $value): float
	{
		return round($value, 3);
	}

	private function popStackFrame(string $nodeId): void
	{
		$current = array_pop($this->_frameStack);

		if ($current === $nodeId) {
			return;
		}

		$this->_frameStack = array_values(array_filter(
			$this->_frameStack,
			static fn (string $stackNodeId): bool => $stackNodeId !== $nodeId
		));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function normalizeMessage(DebugMessage $message): array
	{
		if (method_exists($message, 'toArray')) {
			$payload = $message->toArray();

			if (is_array($payload)) {
				return $payload;
			}
		}

		if ($message instanceof JsonSerializable) {
			$payload = $message->jsonSerialize();

			if (is_array($payload)) {
				return $payload;
			}
		}

		$payload = [];

		foreach (['code', 'level', 'kind', 'context', 'time', 'nodeId', 'requestId'] as $property) {
			foreach ([$property, 'get' . ucfirst($property)] as $method) {
				if (method_exists($message, $method)) {
					$payload[$property] = $message->{$method}();

					continue 2;
				}
			}

			$publicProperties = get_object_vars($message);

			if (array_key_exists($property, $publicProperties)) {
				$payload[$property] = $publicProperties[$property];
			}
		}

		return $payload;
	}
}
