<?php

declare(strict_types=1);

final class LayoutTemplateContractInspector
{
	private const array REQUIRED_ITEMS = [
		'getCss',
		'getJsTop',
		'getJs',
		'page_chrome',
		'fetchClosingHtml',
	];

	/**
	 * @return list<string>
	 */
	public static function getRequiredItems(): array
	{
		return self::REQUIRED_ITEMS;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function inspectFile(string $path): array
	{
		if (!is_file($path) || !is_readable($path)) {
			return [
				'path' => $path,
				'exists' => false,
				'status' => 'error',
				'present' => [],
				'missing' => self::REQUIRED_ITEMS,
				'violations' => ['Template file is missing or unreadable.'],
				'skips' => [],
				'invalid_skips' => [],
			];
		}

		$source = file_get_contents($path);

		if (!is_string($source)) {
			return [
				'path' => $path,
				'exists' => true,
				'status' => 'error',
				'present' => [],
				'missing' => self::REQUIRED_ITEMS,
				'violations' => ['Template file could not be read.'],
				'skips' => [],
				'invalid_skips' => [],
			];
		}

		$skip_data = self::parseHeaderSkips($source);
		$method_lines = self::detectMethodLines($source);
		$present = [];
		$missing = [];
		$violations = $skip_data['invalid'];
		$head_close_line = self::findLineForNeedle($source, '</head>');

		foreach (self::REQUIRED_ITEMS as $item) {
			$present[$item] = isset($method_lines[$item]);

			if (isset($skip_data['skips'][$item])) {
				continue;
			}

			if (!$present[$item]) {
				$missing[] = $item;
			}
		}

		if ($head_close_line !== null) {
			if (!isset($skip_data['skips']['getJsTop']) && isset($method_lines['getJsTop'])) {
				foreach ($method_lines['getJsTop'] as $line) {
					if ($line > $head_close_line) {
						$violations[] = 'getJsTop must be rendered before </head>.';

						break;
					}
				}
			}

			if (!isset($skip_data['skips']['getJs']) && isset($method_lines['getJs'])) {
				foreach ($method_lines['getJs'] as $line) {
					if ($line < $head_close_line) {
						$violations[] = 'getJs must not be rendered before </head>.';

						break;
					}
				}
			}
		} elseif (!isset($skip_data['skips']['getJsTop']) || !isset($skip_data['skips']['getJs'])) {
			$violations[] = 'Layout is missing </head> close tag, cannot enforce script placement.';
		}

		$skips = [];

		foreach ($skip_data['skips'] as $item => $reason) {
			$skips[] = [
				'item' => $item,
				'reason' => $reason,
			];
		}

		return [
			'path' => $path,
			'exists' => true,
			'status' => $missing === [] && $violations === [] ? 'ok' : 'error',
			'present' => $present,
			'missing' => $missing,
			'violations' => $violations,
			'skips' => $skips,
			'invalid_skips' => $skip_data['invalid'],
		];
	}

	/**
	 * @return array{skips: array<string, string>, invalid: list<string>}
	 */
	private static function parseHeaderSkips(string $source): array
	{
		$tokens = token_get_all($source);
		$doc_comment = null;

		foreach ($tokens as $token) {
			if (!is_array($token)) {
				break;
			}

			if (in_array($token[0], [T_OPEN_TAG, T_WHITESPACE], true)) {
				continue;
			}

			if ($token[0] === T_DOC_COMMENT) {
				$doc_comment = $token[1];
			}

			break;
		}

		if ($doc_comment === null) {
			return ['skips' => [], 'invalid' => []];
		}

		$skips = [];
		$invalid = [];

		if (preg_match_all('/@radaptor-layout-skip\s+([^\s]+)([^\n\r]*)/', $doc_comment, $matches, PREG_SET_ORDER) !== false) {
			foreach ($matches as $match) {
				$item = trim((string) $match[1]);
				$tail = (string) ($match[2] ?? '');
				$reason = '';

				if (preg_match('/\breason=(?:"([^"]+)"|\'([^\']+)\')/', $tail, $reason_match) === 1) {
					$reason = trim((string) ($reason_match[1] !== '' ? $reason_match[1] : $reason_match[2]));
				}

				if (!in_array($item, self::REQUIRED_ITEMS, true)) {
					$invalid[] = "Unknown layout contract skip item: {$item}.";

					continue;
				}

				if ($reason === '') {
					$invalid[] = "Layout contract skip for {$item} must include a non-empty reason.";

					continue;
				}

				$skips[$item] = $reason;
			}
		}

		return ['skips' => $skips, 'invalid' => $invalid];
	}

	/**
	 * @return array<string, list<int>>
	 */
	private static function detectMethodLines(string $source): array
	{
		$lines = [];
		$tokens = token_get_all($source);
		$operator_tokens = [T_OBJECT_OPERATOR];

		if (defined('T_NULLSAFE_OBJECT_OPERATOR')) {
			$operator_tokens[] = T_NULLSAFE_OBJECT_OPERATOR;
		}

		foreach ($tokens as $index => $token) {
			if (!is_array($token) || !in_array($token[0], $operator_tokens, true)) {
				continue;
			}

			$name_token = self::nextNonWhitespaceToken($tokens, $index + 1);

			if (!is_array($name_token) || $name_token[0] !== T_STRING) {
				continue;
			}

			$method = $name_token[1];

			if (in_array($method, ['getCss', 'getJsTop', 'getJs', 'fetchClosingHtml'], true)) {
				$lines[$method][] = (int) $name_token[2];

				continue;
			}

			if (in_array($method, ['fetchContent', 'fetchSlot'], true) && self::methodCallContainsStringArgument($tokens, $index + 1, 'page_chrome')) {
				$lines['page_chrome'][] = (int) $name_token[2];
			}
		}

		$render_system_messages_lines = self::detectInlineFunctionLines($tokens, 'renderSystemMessages');

		if ($render_system_messages_lines !== []) {
			$lines['renderSystemMessages'] = $render_system_messages_lines;
		}

		return $lines;
	}

	/**
	 * @param list<array<int, mixed>|string> $tokens
	 * @return list<int>
	 */
	private static function detectInlineFunctionLines(array $tokens, string $function): array
	{
		$lines = [];
		$pattern = '/\b' . preg_quote($function, '/') . '\s*\(/';

		foreach ($tokens as $index => $token) {
			if (is_array($token) && $token[0] === T_STRING && $token[1] === $function) {
				$next = self::nextNonWhitespaceToken($tokens, $index + 1);

				if ($next === '(') {
					$lines[] = (int) $token[2];
				}

				continue;
			}

			if (!is_array($token) || $token[0] !== T_INLINE_HTML) {
				continue;
			}

			$html = (string) $token[1];
			$masked = self::maskCommentsAndStringLiterals($html);

			if (preg_match_all($pattern, $masked, $matches, PREG_OFFSET_CAPTURE) === false) {
				continue;
			}

			foreach ($matches[0] as $match) {
				$lines[] = (int) $token[2] + substr_count(substr($html, 0, (int) $match[1]), "\n");
			}
		}

		return $lines;
	}

	private static function maskCommentsAndStringLiterals(string $html): string
	{
		return preg_replace_callback(
			[
				'/<!--.*?-->/s',
				'/\/\*.*?\*\//s',
				'/"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|`(?:\\\\.|[^`\\\\])*`/s',
			],
			static fn (array $match): string => preg_replace('/[^\r\n]/', ' ', $match[0]) ?? $match[0],
			$html
		) ?? $html;
	}

	/**
	 * @param list<array<int, mixed>|string> $tokens
	 */
	private static function nextNonWhitespaceToken(array $tokens, int $start): array|string|null
	{
		for ($i = $start; $i < count($tokens); ++$i) {
			$token = $tokens[$i];

			if (is_array($token) && $token[0] === T_WHITESPACE) {
				continue;
			}

			return $token;
		}

		return null;
	}

	/**
	 * @param list<array<int, mixed>|string> $tokens
	 */
	private static function methodCallContainsStringArgument(array $tokens, int $start, string $expected): bool
	{
		$inside_call = false;
		$depth = 0;

		for ($i = $start; $i < count($tokens); ++$i) {
			$token = $tokens[$i];
			$text = is_array($token) ? (string) $token[1] : $token;

			if ($text === '(') {
				$inside_call = true;
				++$depth;

				continue;
			}

			if (!$inside_call) {
				continue;
			}

			if ($text === ')') {
				--$depth;

				if ($depth <= 0) {
					return false;
				}

				continue;
			}

			if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
				$value = trim($text, "'\"");

				if ($value === $expected) {
					return true;
				}
			}
		}

		return false;
	}

	private static function findLineForNeedle(string $source, string $needle): ?int
	{
		$offset = stripos($source, $needle);

		if ($offset === false) {
			return null;
		}

		return self::lineForOffset($source, $offset);
	}

	private static function lineForOffset(string $source, int $offset): int
	{
		return substr_count(substr($source, 0, $offset), "\n") + 1;
	}
}
