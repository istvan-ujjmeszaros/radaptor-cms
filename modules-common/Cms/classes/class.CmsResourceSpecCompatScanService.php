<?php

declare(strict_types=1);

final class CmsResourceSpecCompatScanService
{
	/**
	 * @return array<string, mixed>
	 */
	public static function scan(string $path): array
	{
		$resolved = self::resolvePath($path);
		$files = is_dir($resolved) ? self::listSpecFiles($resolved) : [$resolved];
		$issues = [];
		$errors = [];

		foreach ($files as $file) {
			try {
				$spec = self::loadSpec($file);

				if ($spec === null) {
					continue;
				}

				foreach (CmsResourceTreeSpecService::flattenResources($spec) as $resource) {
					if (!is_array($resource) || ($resource['type'] ?? '') !== 'webpage') {
						continue;
					}

					if (is_array($resource['slots'] ?? null) && !array_key_exists('replace_slots', $resource)) {
						$issues[] = [
							'file' => $file,
							'path' => (string) ($resource['path'] ?? ''),
							'message' => 'Webpage spec declares slots without replace_slots. Omitted slots are preserved in CMS 0.1.24+.',
						];
					}
				}
			} catch (Throwable $exception) {
				$errors[] = [
					'file' => $file,
					'message' => $exception->getMessage(),
				];
			}
		}

		return [
			'status' => $errors === [] ? 'success' : 'error',
			'scanned_files' => count($files),
			'potential_legacy_specs' => count($issues),
			'issues' => $issues,
			'errors' => $errors,
		];
	}

	private static function resolvePath(string $path): string
	{
		$path = trim($path);

		if ($path === '') {
			throw new InvalidArgumentException('Path is required.');
		}

		$candidates = str_starts_with($path, '/')
			? [$path]
			: [DEPLOY_ROOT . $path, getcwd() . '/' . $path];

		foreach ($candidates as $candidate) {
			if ((is_file($candidate) || is_dir($candidate)) && is_readable($candidate)) {
				return $candidate;
			}
		}

		throw new RuntimeException("Path not found: {$path}");
	}

	/**
	 * @return list<string>
	 */
	private static function listSpecFiles(string $directory): array
	{
		$files = [];
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
		);

		foreach ($iterator as $file_info) {
			if (!$file_info instanceof SplFileInfo || !$file_info->isFile()) {
				continue;
			}

			$extension = strtolower($file_info->getExtension());

			if (in_array($extension, ['php', 'json'], true)) {
				$files[] = $file_info->getPathname();
			}
		}

		sort($files, SORT_STRING);

		return $files;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function loadSpec(string $file): ?array
	{
		if (str_ends_with(strtolower($file), '.json')) {
			$json = file_get_contents($file);

			if ($json === false) {
				throw new RuntimeException("Unable to read spec file: {$file}");
			}

			$decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

			if (!is_array($decoded)) {
				throw new InvalidArgumentException("JSON spec must decode to an object: {$file}");
			}

			return $decoded;
		}

		$source = file_get_contents($file);

		if (!is_string($source) || !self::looksLikePhpResourceSpec($source)) {
			return null;
		}

		ob_start();

		try {
			$spec = require $file;
		} finally {
			ob_end_clean();
		}

		if (!is_array($spec)) {
			return null;
		}

		return $spec;
	}

	private static function looksLikePhpResourceSpec(string $source): bool
	{
		return str_contains($source, 'return')
			&& str_contains($source, 'resources')
			&& str_contains($source, 'slots');
	}
}
