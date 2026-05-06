<?php

declare(strict_types=1);

class SduiNode
{
	public const string TYPE_WIDGET = 'widget';
	public const string TYPE_SUB = 'sub';

	/**
	 * @param array<string, mixed> $props
	 * @param array<string, list<array<string, mixed>>> $contents
	 * @param array<string, mixed> $meta
	 * @param array<string, mixed> $strings
	 * @return array{type: string, component: string, props: array<string, mixed>, contents: array<string, list<array<string, mixed>>>, strings: array<string, mixed>, meta: array<string, mixed>}
	 */
	public static function create(
		string $component,
		array $props = [],
		array $contents = [],
		string $type = self::TYPE_SUB,
		array $meta = [],
		array $strings = [],
	): array {
		return [
			'type'      => $type,
			'component' => $component,
			'props'     => $props,
			'contents'  => $contents,
			'strings'   => $strings,
			'meta'      => $meta,
		];
	}

	/**
	 * @param array<string, mixed> $node
	 * @return array{type: string, component: string, props: array<string, mixed>, contents: array<string, list<array<string, mixed>>>, strings: array<string, mixed>, meta?: array<string, mixed>}
	 */
	public static function normalize(array $node): array
	{
		$props = is_array($node['props'] ?? null) ? $node['props'] : [];
		$meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
		$strings = is_array($node['strings'] ?? null) ? $node['strings'] : [];

		$normalized = [
			'type'      => (string)($node['type'] ?? self::TYPE_SUB),
			'component' => (string)($node['component'] ?? '_missing'),
			'props'     => $props,
			'contents'  => is_array($node['contents'] ?? null) ? $node['contents'] : [],
			'strings'   => $strings,
		];

		if ($meta !== []) {
			$normalized['meta'] = $meta;
		}

		return $normalized;
	}
}
