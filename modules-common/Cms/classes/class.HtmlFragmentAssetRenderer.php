<?php

declare(strict_types=1);

final class HtmlFragmentAssetRenderer
{
	public static function renderTemplatesFromRenderer(HtmlTreeRenderer $renderer): string
	{
		$html = '';
		$asset_html = $renderer->getCss() . $renderer->getJsTop() . $renderer->getJs();

		foreach (self::extractAssetTags($asset_html) as $tag) {
			$id = 'radaptor-asset-' . sha1($tag);
			$html .= '<template data-radaptor-fragment-assets>' . self::withAssetAttributes($tag, $id) . '</template>';
		}

		return $html;
	}

	/**
	 * @return list<string>
	 */
	private static function extractAssetTags(string $html): array
	{
		preg_match_all('/<link\\b[^>]*>/i', $html, $link_matches);
		preg_match_all('/<script\\b(?=[^>]*\\bsrc=)[^>]*>\\s*<\\/script>/i', $html, $script_matches);

		return array_values(array_unique([
			...($link_matches[0] ?? []),
			...($script_matches[0] ?? []),
		]));
	}

	private static function withAssetAttributes(string $tag, string $id): string
	{
		if (preg_match('/\\sid=([\"\\\']).*?\\1/i', $tag)) {
			return preg_replace('/^(<(?:link|script)\\b)/i', '$1 data-radaptor-fragment-asset="1"', $tag, 1) ?? $tag;
		}

		return preg_replace('/^(<(?:link|script)\\b)/i', '$1 id="' . e($id) . '" data-radaptor-fragment-asset="1"', $tag, 1) ?? $tag;
	}
}
