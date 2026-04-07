<?php

/**
 * Thin seed façade over CmsResourceSpecService.
 */
class CmsSeedHelper
{
	public function __construct(private readonly SeedContext $context)
	{
	}

	/**
	 * @param array<string, mixed> $spec
	 */
	public function upsertFolder(array $spec): int
	{
		return CmsResourceSpecService::upsertFolder($spec);
	}

	/**
	 * @param array<string, mixed> $spec
	 */
	public function upsertWebpage(array $spec): int
	{
		return CmsResourceSpecService::upsertWebpage($spec);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function loadJson(string $relative_path): array
	{
		$path = rtrim($this->context->basePath, '/') . '/' . ltrim($relative_path, '/');

		if (!is_file($path)) {
			throw new RuntimeException("Seed JSON file not found: {$path}");
		}

		$json = file_get_contents($path);

		if (!is_string($json)) {
			throw new RuntimeException("Unable to read seed JSON file: {$path}");
		}

		try {
			$decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			throw new RuntimeException("Invalid JSON in {$path}: {$exception->getMessage()}", 0, $exception);
		}

		if (!is_array($decoded)) {
			throw new RuntimeException("Seed JSON must decode to an array: {$path}");
		}

		return $decoded;
	}
}
