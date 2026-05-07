<?php

declare(strict_types=1);

/**
 * Tiny route-map helper for catcher widgets.
 *
 * It intentionally keeps the contract as data:
 *   subpath + route array => component + params
 *
 * @phpstan-type CatcherRouteDefinition string|array{
 *     component: string,
 *     defaults?: array<string, scalar|null>,
 *     where?: array<string, string>
 * }
 * @phpstan-type CatcherRouteMatch array{
 *     component: string,
 *     route: string,
 *     params: array<string, scalar|null>
 * }
 */
final class CatcherRouteMap
{
	private function __construct()
	{
	}

	public static function subpathForPage(int $catcher_page_id): string
	{
		$mount_path = self::normalizeMountPath(self::pagePath($catcher_page_id));
		$request_path = self::normalizeRequestPath(
			(string) Request::_GET('folder', '/'),
			(string) Request::_GET('resource', 'index.html')
		);

		if ($request_path === rtrim($mount_path, '/')) {
			return '';
		}

		if (!str_starts_with($request_path, $mount_path)) {
			return '';
		}

		return trim(substr($request_path, strlen($mount_path)), '/');
	}

	/**
	 * @param array<string, mixed> $query
	 */
	public static function urlForPage(int $catcher_page_id, string $subpath = '', array $query = []): string
	{
		$mount_path = self::normalizeMountPath(self::pagePath($catcher_page_id));
		$subpath = self::normalizeSubpath($subpath);
		$url = $subpath === '' ? $mount_path : $mount_path . $subpath;

		if ($query !== []) {
			$url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
		}

		return $url;
	}

	/**
	 * @param array<string, CatcherRouteDefinition> $routes
	 * @return CatcherRouteMatch|null
	 */
	public static function match(string $subpath, array $routes): ?array
	{
		$subpath = self::normalizeSubpath($subpath);

		foreach ($routes as $pattern => $definition) {
			$route = self::normalizeDefinition((string) $pattern, $definition);
			$params = self::matchPattern($subpath, $route['route'], $route['where']);

			if ($params === null) {
				continue;
			}

			return [
				'component' => $route['component'],
				'route' => $route['route'],
				'params' => array_replace($route['defaults'], $params),
			];
		}

		return null;
	}

	private static function pagePath(int $page_id): string
	{
		$seo_url = Url::getSeoUrl($page_id, false, true);

		if (is_string($seo_url) && $seo_url !== '') {
			return $seo_url;
		}

		return ResourceTreeHandler::getPathFromId($page_id);
	}

	private static function normalizeMountPath(string $path): string
	{
		$path = (string) (parse_url($path, PHP_URL_PATH) ?: $path);
		$path = '/' . ltrim($path, '/');
		$path = preg_replace('#/+#', '/', $path) ?? $path;

		if (str_ends_with($path, '/index.html')) {
			$path = substr($path, 0, -strlen('index.html'));
		}

		return rtrim($path, '/') . '/';
	}

	private static function normalizeRequestPath(string $folder, string $resource): string
	{
		$folder = '/' . trim($folder, '/') . '/';
		$folder = $folder === '//' ? '/' : $folder;
		$resource = trim($resource, '/');

		$path = ($resource === '' || $resource === 'index.html')
			? $folder
			: $folder . $resource;

		return preg_replace('#/+#', '/', '/' . ltrim($path, '/')) ?? $path;
	}

	private static function normalizeSubpath(string $subpath): string
	{
		$subpath = trim($subpath);
		$subpath = preg_replace('#/+#', '/', $subpath) ?? $subpath;

		return trim($subpath, '/');
	}

	/**
	 * @param CatcherRouteDefinition $definition
	 * @return array{route: string, component: string, defaults: array<string, scalar|null>, where: array<string, string>}
	 */
	private static function normalizeDefinition(string $pattern, string|array $definition): array
	{
		if (is_string($definition)) {
			return [
				'route' => self::normalizeSubpath($pattern),
				'component' => $definition,
				'defaults' => [],
				'where' => [],
			];
		}

		$component = trim((string) ($definition['component'] ?? ''));

		if ($component === '') {
			throw new InvalidArgumentException("Catcher route '{$pattern}' has no component.");
		}

		return [
			'route' => self::normalizeSubpath($pattern),
			'component' => $component,
			'defaults' => is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [],
			'where' => is_array($definition['where'] ?? null) ? $definition['where'] : [],
		];
	}

	/**
	 * @param array<string, string> $where
	 * @return array<string, string>|null
	 */
	private static function matchPattern(string $subpath, string $pattern, array $where): ?array
	{
		$path_segments = $subpath === '' ? [] : explode('/', $subpath);
		$pattern_segments = $pattern === '' ? [] : explode('/', $pattern);

		if (count($path_segments) !== count($pattern_segments)) {
			return null;
		}

		$params = [];

		foreach ($pattern_segments as $index => $pattern_segment) {
			$path_segment = rawurldecode($path_segments[$index] ?? '');

			if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)}$/', $pattern_segment, $matches) === 1) {
				$name = $matches[1];

				if (isset($where[$name]) && preg_match('/^(?:' . $where[$name] . ')$/u', $path_segment) !== 1) {
					return null;
				}

				$params[$name] = $path_segment;

				continue;
			}

			if ($pattern_segment !== $path_segment) {
				return null;
			}
		}

		return $params;
	}
}
