<?php

declare(strict_types=1);

class SduiJsonSerializer
{
	/**
	 * @param array<string, mixed> $tree
	 */
	public function serializeDocument(array $tree, string $locale): string
	{
		return json_encode([
			'version' => 1,
			'locale' => $locale,
			'tree' => $this->serializeNode($tree),
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
	}

	/**
	 * @param array<string, mixed> $node
	 * @return array<string, mixed>
	 */
	private function serializeNode(array $node): array
	{
		$normalized = SduiNode::normalize($node);

		if ($normalized['component'] === '_rawHtml') {
			throw new LogicException('Raw HTML nodes cannot be serialized into the SDUI JSON transport.');
		}

		$contents = [];

		foreach ($normalized['contents'] as $slot_name => $items) {
			$contents[$slot_name] = array_map(
				fn (array $item): array => $this->serializeNode($item),
				$items
			);
		}

		return [
			'type'      => $normalized['type'],
			'component' => $normalized['component'],
			'props'     => (object)$this->normalizeValue($normalized['props']),
			'strings'   => (object)$this->normalizeValue($normalized['strings']),
			'contents'     => (object)$contents,
		];
	}

	private function normalizeValue(mixed $value): mixed
	{
		if (is_array($value)) {
			$normalized = [];

			foreach ($value as $key => $item) {
				$normalized[$key] = $this->normalizeValue($item);
			}

			return $normalized;
		}

		if (is_scalar($value) || $value === null) {
			return $value;
		}

		if ($value instanceof BackedEnum) {
			return $value->value;
		}

		if ($value instanceof DateTimeInterface) {
			return $value->format(DATE_ATOM);
		}

		if ($value instanceof JsonSerializable) {
			return $this->normalizeValue($value->jsonSerialize());
		}

		if ($value instanceof Stringable) {
			return (string)$value;
		}

		if (is_object($value)) {
			return $this->normalizeValue(get_object_vars($value));
		}

		return null;
	}
}
