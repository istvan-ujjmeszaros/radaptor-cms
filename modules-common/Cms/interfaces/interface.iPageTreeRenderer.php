<?php

interface iPageTreeRenderer
{
	/**
	 * @param array<string, mixed> $node
	 * @param array<string, mixed> $render_context
	 */
	public function render(array $node, array $render_context = []): string;
}
