<?php

declare(strict_types=1);

final class RichTextWidgetContentLocaleStrategy extends WidgetContentLocaleStrategy
{
	/**
	 * @param array<string, scalar|null> $attributes
	 */
	public function assertConnectionAttributesCompatible(int $connection_id, array $attributes): void
	{
		if (!array_key_exists('content_id', $attributes)) {
			return;
		}

		$content_id = (int) ($attributes['content_id'] ?? 0);

		if ($content_id <= 0) {
			return;
		}

		if (!RichTextLocaleService::contentMatchesConnectionLocale($content_id, $connection_id)) {
			throw new RuntimeException(t('cms.richtext.locale_mismatch'));
		}
	}
}
