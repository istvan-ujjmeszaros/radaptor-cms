<?php

class WidgetEditCommand extends Struct
{
	public function __construct(
		public string $title = '',
		public string $url = '',
		public ?IconNames $icon = null
	) {
	}
}
