<?php

declare(strict_types=1);

final class FormCaptureDescriptorSpecLoader
{
	private const array SUPPORTED_SOURCES = ['db', 'shipped'];

	/**
	 * @param array<string, mixed> $descriptor
	 * @param array<string, mixed>|string|null $security
	 * @return array<string, mixed>
	 */
	public static function previewPublish(
		string $definition_slug,
		array $descriptor,
		array|string|null $security,
		string $source,
		string $origin = 'inline',
	): array {
		$spec = self::normalizeSpec([
			'definition_slug' => $definition_slug,
			'descriptor' => $descriptor,
			'security' => $security,
			'source' => $source,
			'origin' => $origin,
		]);

		self::validateSpecs([$spec]);

		return [
			'status' => 'success',
			'dry_run' => true,
			'source' => $source,
			'definitions' => [self::previewSpec($spec)],
			'summary' => [
				'validated' => 1,
				'would_publish' => 1,
				'published' => 0,
			],
		];
	}

	/**
	 * @param array<string, mixed> $descriptor
	 * @param array<string, mixed>|string|null $security
	 * @return array<string, mixed>
	 */
	public static function applyPublish(
		string $definition_slug,
		array $descriptor,
		array|string|null $security,
		string $source,
		string $origin = 'inline',
	): array {
		$spec = self::normalizeSpec([
			'definition_slug' => $definition_slug,
			'descriptor' => $descriptor,
			'security' => $security,
			'source' => $source,
			'origin' => $origin,
		]);

		self::validateSpecs([$spec]);
		$result = self::applySpec($spec);

		return [
			'status' => 'success',
			'dry_run' => false,
			'source' => $source,
			'definitions' => [$result],
			'summary' => [
				'validated' => 1,
				'would_publish' => 0,
				'published' => 1,
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function previewSync(string $path): array
	{
		$specs = self::loadShippedSpecs($path);
		self::validateSpecs($specs);

		return [
			'status' => 'success',
			'dry_run' => true,
			'source' => 'shipped',
			'path' => $path,
			'definitions' => array_map(static fn (array $spec): array => self::previewSpec($spec), $specs),
			'summary' => [
				'validated' => count($specs),
				'would_publish' => count($specs),
				'published' => 0,
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function applySync(string $path): array
	{
		$specs = self::loadShippedSpecs($path);
		self::validateSpecs($specs);

		$definitions = [];

		foreach ($specs as $spec) {
			$definitions[] = self::applySpec($spec);
		}

		return [
			'status' => 'success',
			'dry_run' => false,
			'source' => 'shipped',
			'path' => $path,
			'definitions' => $definitions,
			'summary' => [
				'validated' => count($specs),
				'would_publish' => 0,
				'published' => count($definitions),
			],
		];
	}

	/**
	 * @return list<array{definition_slug: string, descriptor: array<string, mixed>, security: array<string, mixed>|string|null, source: string, origin: string}>
	 */
	public static function loadShippedSpecs(string $path): array
	{
		$files = self::discoverSpecFiles($path);
		$specs = [];

		foreach ($files as $file) {
			$spec = self::loadSpecFile($file);
			$source = (string)($spec['source'] ?? 'shipped');

			if ($source !== 'shipped') {
				throw new InvalidArgumentException("Form sync only accepts shipped form specs; {$file} declares source '{$source}'.");
			}

			$spec['source'] = 'shipped';
			$spec['origin'] = $file;
			$specs[] = self::normalizeSpec($spec);
		}

		if ($specs === []) {
			throw new InvalidArgumentException("No *.form.php or *.form.json files found at {$path}.");
		}

		return $specs;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function decodeJsonObject(string $json, string $label): array
	{
		try {
			$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			throw new InvalidArgumentException("{$label} must be valid JSON.", 0, $exception);
		}

		if (!is_array($data)) {
			throw new InvalidArgumentException("{$label} must decode to an object.");
		}

		return $data;
	}

	public static function normalizeSource(string $source): string
	{
		$source = trim($source);

		if (!in_array($source, self::SUPPORTED_SOURCES, true)) {
			throw new InvalidArgumentException('Form definition source must be db or shipped.');
		}

		return $source;
	}

	/**
	 * @param array<string, mixed>|string|null $security
	 * @return array<string, mixed>|string|null
	 */
	public static function normalizeSecurityPayload(array|string|null $security): array|string|null
	{
		if (is_string($security) && trim($security) !== '') {
			return self::decodeJsonObject($security, 'security_json');
		}

		return $security;
	}

	/**
	 * @param list<array{definition_slug: string, descriptor: array<string, mixed>, security: array<string, mixed>|string|null, source: string, origin: string}> $specs
	 */
	private static function validateSpecs(array $specs): void
	{
		$seen = [];

		foreach ($specs as $spec) {
			$definition_slug = $spec['definition_slug'];

			if (isset($seen[$definition_slug])) {
				throw new InvalidArgumentException("Duplicate form definition_slug '{$definition_slug}' in sync batch.");
			}

			$seen[$definition_slug] = true;
			$field_keys = FormCaptureDescriptorSchemaValidator::validateDescriptor($spec['descriptor']);
			FormCaptureDescriptorSchemaValidator::validateForDefinition($definition_slug, $spec['descriptor'], $spec['security']);
			FormCaptureDescriptorSchemaValidator::normalizeSecurity($spec['security'], $field_keys);
			self::assertExistingSourceMatches($definition_slug, $spec['source']);
		}
	}

	/**
	 * @param array<string, mixed> $raw
	 * @return array{definition_slug: string, descriptor: array<string, mixed>, security: array<string, mixed>|string|null, source: string, origin: string}
	 */
	private static function normalizeSpec(array $raw): array
	{
		$definition_slug = trim((string)($raw['definition_slug'] ?? $raw['slug'] ?? ''));

		if ($definition_slug === '') {
			throw new InvalidArgumentException('Form spec requires definition_slug.');
		}

		$descriptor = $raw['descriptor'] ?? null;

		if (!is_array($descriptor)) {
			$descriptor = $raw;
			unset($descriptor['definition_slug'], $descriptor['slug'], $descriptor['security'], $descriptor['security_json'], $descriptor['source'], $descriptor['origin']);
		}

		if (!is_array($descriptor)) {
			throw new InvalidArgumentException("Form spec {$definition_slug} requires a descriptor object.");
		}

		$security = $raw['security'] ?? $raw['security_json'] ?? null;

		return [
			'definition_slug' => $definition_slug,
			'descriptor' => $descriptor,
			'security' => self::normalizeSecurityPayload($security),
			'source' => self::normalizeSource((string)($raw['source'] ?? 'db')),
			'origin' => (string)($raw['origin'] ?? 'inline'),
		];
	}

	private static function assertExistingSourceMatches(string $definition_slug, string $source): void
	{
		$definition = EntityFormDefinition::findBySlug($definition_slug);

		if ($definition === null) {
			return;
		}

		$existing_source = (string)($definition->source ?? '');

		if ($existing_source !== '' && $existing_source !== $source) {
			throw new InvalidArgumentException("Form definition {$definition_slug} already exists with source '{$existing_source}', not '{$source}'.");
		}
	}

	/**
	 * @param array{definition_slug: string, descriptor: array<string, mixed>, security: array<string, mixed>|string|null, source: string, origin: string} $spec
	 * @return array<string, mixed>
	 */
	private static function previewSpec(array $spec): array
	{
		$preview = (new FormCaptureDefinitionRepository())->previewPublishDefinition(
			$spec['definition_slug'],
			$spec['descriptor'],
			$spec['security'],
			$spec['source'],
		);

		return $preview + [
			'definition_slug' => $spec['definition_slug'],
			'origin' => $spec['origin'],
			'source' => $spec['source'],
			'status' => 'validated',
		];
	}

	/**
	 * @param array{definition_slug: string, descriptor: array<string, mixed>, security: array<string, mixed>|string|null, source: string, origin: string} $spec
	 * @return array<string, mixed>
	 */
	private static function applySpec(array $spec): array
	{
		$resolution = (new FormCaptureDefinitionRepository())->upsertPublishedDefinition(
			$spec['definition_slug'],
			$spec['descriptor'],
			$spec['security'],
			$spec['source'],
		);

		return [
			'definition_slug' => $resolution->definitionSlug(),
			'definition_id' => $resolution->definitionId(),
			'version_id' => $resolution->versionId(),
			'version_number' => (int)($resolution->version()['version_number'] ?? 0),
			'origin' => $spec['origin'],
			'source' => $spec['source'],
			'status' => 'published',
		];
	}

	/**
	 * @return list<string>
	 */
	private static function discoverSpecFiles(string $path): array
	{
		$real_path = realpath($path);

		if ($real_path === false) {
			throw new InvalidArgumentException("Form spec path does not exist: {$path}");
		}

		if (is_file($real_path)) {
			if (!self::isSpecFile($real_path)) {
				throw new InvalidArgumentException("Form spec file must end with .form.php or .form.json: {$path}");
			}

			return [$real_path];
		}

		if (!is_dir($real_path)) {
			throw new InvalidArgumentException("Form spec path is not a file or directory: {$path}");
		}

		$files = [];
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real_path, FilesystemIterator::SKIP_DOTS));

		foreach ($iterator as $file) {
			if (!$file instanceof SplFileInfo || !$file->isFile()) {
				continue;
			}

			$filename = $file->getPathname();

			if (self::isSpecFile($filename)) {
				$files[] = $filename;
			}
		}

		sort($files);

		return $files;
	}

	private static function isSpecFile(string $path): bool
	{
		return str_ends_with($path, '.form.php') || str_ends_with($path, '.form.json');
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function loadSpecFile(string $file): array
	{
		if (!is_readable($file)) {
			throw new InvalidArgumentException("Form spec file is not readable: {$file}");
		}

		if (str_ends_with($file, '.form.json')) {
			return self::decodeJsonObject((string)file_get_contents($file), $file);
		}

		$spec = (static function (string $path): mixed {
			return require $path;
		})($file);

		if (!is_array($spec)) {
			throw new InvalidArgumentException("Form spec PHP file must return an array: {$file}");
		}

		return $spec;
	}
}
