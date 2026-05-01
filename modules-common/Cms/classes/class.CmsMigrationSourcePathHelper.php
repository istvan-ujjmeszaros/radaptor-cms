<?php

declare(strict_types=1);

final class CmsMigrationSourcePathHelper
{
	/**
	 * @return list<string>
	 */
	public static function getConfiguredRoots(): array
	{
		$raw = trim((string) getenv('APP_MIGRATION_SOURCE_ROOTS'));

		if ($raw === '') {
			return [];
		}

		$parts = preg_split('/[,:]/', $raw) ?: [];
		$roots = [];

		foreach ($parts as $part) {
			$root = realpath(trim($part));

			if ($root !== false && is_dir($root)) {
				$roots[] = rtrim($root, DIRECTORY_SEPARATOR);
			}
		}

		return array_values(array_unique($roots));
	}

	public static function resolveReadableFile(string $sourcePath): string
	{
		$source_path = trim($sourcePath);

		if ($source_path === '') {
			throw new InvalidArgumentException('source_path is required.');
		}

		$real_path = realpath($source_path);

		if ($real_path === false || !is_file($real_path) || !is_readable($real_path)) {
			throw new RuntimeException("Migration source file is not readable: {$source_path}");
		}

		foreach (self::getConfiguredRoots() as $root) {
			if ($real_path === $root || str_starts_with($real_path, $root . DIRECTORY_SEPARATOR)) {
				return $real_path;
			}
		}

		throw new RuntimeException('Migration source file is outside APP_MIGRATION_SOURCE_ROOTS.');
	}
}
