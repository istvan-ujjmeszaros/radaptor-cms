<?php

class CmsPathHelper
{
	public static function normalizePath(string $path): string
	{
		$path = trim($path);

		if ($path === '') {
			return '/';
		}

		$path = '/' . ltrim($path, '/');
		$path = preg_replace('#/+#', '/', $path) ?? $path;

		if ($path !== '/' && str_ends_with($path, '/index.html')) {
			$path = substr($path, 0, -10) . '/';
		}

		return $path;
	}

	/**
	 * @return array{normalized_path: string, folder: string, resource_name: string}
	 */
	public static function splitWebpagePath(string $path): array
	{
		$normalized_path = self::normalizePath($path);

		if ($normalized_path === '/') {
			return [
				'normalized_path' => '/',
				'folder' => '/',
				'resource_name' => 'index.html',
			];
		}

		if (str_ends_with($normalized_path, '/')) {
			return [
				'normalized_path' => $normalized_path,
				'folder' => $normalized_path,
				'resource_name' => 'index.html',
			];
		}

		$parts = explode('/', trim($normalized_path, '/'));
		$resource_name = (string) array_pop($parts);
		$folder = '/' . implode('/', $parts);

		if ($folder !== '/') {
			$folder .= '/';
		}

		return [
			'normalized_path' => $normalized_path,
			'folder' => $folder,
			'resource_name' => $resource_name,
		];
	}

	/**
	 * @return array{normalized_path: string, parent_path: string, resource_name: string}
	 */
	public static function splitFolderPath(string $path): array
	{
		$normalized_path = self::normalizePath($path);

		if ($normalized_path === '/') {
			return [
				'normalized_path' => '/',
				'parent_path' => '/',
				'resource_name' => '',
			];
		}

		$trimmed = trim($normalized_path, '/');
		$parts = explode('/', $trimmed);
		$resource_name = (string) array_pop($parts);
		$parent_path = '/' . implode('/', $parts);

		if ($parent_path !== '/') {
			$parent_path .= '/';
		}

		return [
			'normalized_path' => '/' . $trimmed . '/',
			'parent_path' => $parent_path,
			'resource_name' => $resource_name,
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function resolveWebpage(string $path): ?array
	{
		$parts = self::splitWebpagePath($path);

		return ResourceTreeHandler::getResourceTreeEntryData(
			$parts['folder'],
			$parts['resource_name'],
			Config::APP_DOMAIN_CONTEXT->value()
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function resolveFolder(string $path): ?array
	{
		$parts = self::splitFolderPath($path);

		if ($parts['normalized_path'] === '/') {
			$root_id = ResourceTreeHandler::getDomainRoot(Config::APP_DOMAIN_CONTEXT->value());

			return is_int($root_id) ? ResourceTreeHandler::getResourceTreeEntryDataById($root_id) : null;
		}

		return ResourceTreeHandler::getResourceTreeEntryData(
			$parts['parent_path'],
			$parts['resource_name'],
			Config::APP_DOMAIN_CONTEXT->value()
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function resolveResource(string $path): ?array
	{
		$normalized_path = self::normalizePath($path);

		if ($normalized_path === '/') {
			return self::resolveFolder('/');
		}

		if (str_ends_with($normalized_path, '/')) {
			return self::resolveFolder($normalized_path) ?? self::resolveWebpage($normalized_path);
		}

		return self::resolveWebpage($normalized_path) ?? self::resolveFolder($normalized_path);
	}
}
