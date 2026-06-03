<?php

class WidgetEditCommand extends Struct
{
	/**
	 * @param array<string, scalar|null> $payload
	 */
	public function __construct(
		public string $title = '',
		public string $url = '',
		public ?IconNames $icon = null,
		public string $method = 'get',
		public array $payload = [],
		public bool $loader = false,
		public string $properties_url = '',
	) {
	}
}
