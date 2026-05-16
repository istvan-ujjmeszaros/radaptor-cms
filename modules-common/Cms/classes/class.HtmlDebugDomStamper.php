<?php

declare(strict_types=1);

/**
 * Adds Radaptor debug ownership attributes to HTML fragments.
 */
class HtmlDebugDomStamper
{
	private const string WRAPPER_ID = '__radaptor_debug_fragment__';

	/**
	 * @param array<string, string> $attributes
	 * @return array{html: string, stampedElementCount: int}
	 */
	public static function stampRootElements(string $html, array $attributes): array
	{
		if ($attributes === [] || trim($html) === '' || self::looksLikeFullDocument($html)) {
			return [
				'html' => $html,
				'stampedElementCount' => 0,
			];
		}

		if (!class_exists(Dom\HTMLDocument::class)) {
			return [
				'html' => $html,
				'stampedElementCount' => 0,
			];
		}

		try {
			$document = @Dom\HTMLDocument::createFromString(
				'<!doctype html><html><body><div id="' . self::WRAPPER_ID . '">' . $html . '</div></body></html>',
				LIBXML_NOERROR | LIBXML_COMPACT
			);
		} catch (Throwable) {
			return [
				'html' => $html,
				'stampedElementCount' => 0,
			];
		}

		$wrapper = $document->getElementById(self::WRAPPER_ID);

		if (!$wrapper instanceof Dom\HTMLElement) {
			return [
				'html' => $html,
				'stampedElementCount' => 0,
			];
		}

		$stampedElementCount = 0;

		foreach ($wrapper->children as $child) {
			if (!$child instanceof Dom\HTMLElement) {
				continue;
			}

			if ($child->hasAttribute('data-radaptor-node')) {
				continue;
			}

			foreach ($attributes as $name => $value) {
				$child->setAttribute($name, $value);
			}

			++$stampedElementCount;
		}

		if ($stampedElementCount === 0) {
			return [
				'html' => $html,
				'stampedElementCount' => 0,
			];
		}

		$output = '';

		foreach ($wrapper->childNodes as $child) {
			$output .= $document->saveHtml($child);
		}

		return [
			'html' => $output,
			'stampedElementCount' => $stampedElementCount,
		];
	}

	private static function looksLikeFullDocument(string $html): bool
	{
		$prefix = strtolower(ltrim($html));

		return str_starts_with($prefix, '<!doctype')
			|| str_starts_with($prefix, '<html')
			|| str_contains(substr($prefix, 0, 512), '<body');
	}
}
