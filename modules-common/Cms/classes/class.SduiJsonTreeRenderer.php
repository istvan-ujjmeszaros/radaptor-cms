<?php

declare(strict_types=1);

class SduiJsonTreeRenderer implements iPageTreeRenderer
{
	private SduiJsonSerializer $_serializer;

	public function __construct()
	{
		$this->_serializer = new SduiJsonSerializer();
	}

	/**
	 * @param array<string, mixed> $node
	 * @param array<string, mixed> $render_context
	 */
	public function render(array $node, array $render_context = []): string
	{
		return $this->_serializer->serializeDocument($node, (string)($render_context['lang_id'] ?? ''));
	}
}
