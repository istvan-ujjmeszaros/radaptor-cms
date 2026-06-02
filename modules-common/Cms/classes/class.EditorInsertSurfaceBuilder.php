<?php

declare(strict_types=1);

final class EditorInsertSurfaceBuilder
{
	public const string SCOPE_WIDGET = 'widget';
	public const string SCOPE_FORM = 'form';
	public const string VARIANT_WIDGET = 'widget';
	public const string VARIANT_FORM = 'form';
	public const string TRANSPORT_STANDALONE_FORM = 'standalone_form';
	public const string TRANSPORT_INSIDE_FORM = 'inside_form';

	/**
	 * @param list<EditorInsertItem> $items
	 * @param array<string, mixed> $target
	 * @param array<string, string> $strings
	 * @param array<string, mixed> $extra_props
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	public function build(
		string $scope,
		string $variant,
		string $transport,
		array $items,
		array $target,
		string $insert_url,
		int|string $counter,
		array $strings,
		array $extra_props = [],
		array $meta = [],
	): array {
		$props = array_replace($extra_props, [
			'scope' => $scope,
			'variant' => $variant,
			'transport' => $transport,
			'items' => array_map(static fn (EditorInsertItem $item): array => $item->toArray(), $items),
			'target' => $target,
			'insert_url' => $insert_url,
			'counter' => $counter,
		]);

		return SduiNode::create(
			component: 'editorInsert',
			props: $props,
			type: SduiNode::TYPE_SUB,
			meta: $meta,
			strings: $strings,
		);
	}
}
