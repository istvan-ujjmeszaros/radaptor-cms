<?php

declare(strict_types=1);

final class HtmlFragmentTemplate extends Template
{
	protected function addDebugInfo(Template $template, string $content, string $widgetName = ''): string
	{
		return $content;
	}
}
