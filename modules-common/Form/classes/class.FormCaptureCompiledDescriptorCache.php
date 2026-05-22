<?php

declare(strict_types=1);

final class FormCaptureCompiledDescriptorCache
{
	private const string CACHE_ROOT = 'var/cache/forms';

	/**
	 * @param array<string, mixed> $definition
	 * @param array<string, mixed> $version
	 * @param array<string, mixed> $descriptor
	 * @param array<string, mixed> $security
	 * @return array<string, mixed>
	 */
	public function write(array $definition, array $version, array $descriptor, array $security): array
	{
		$definition_slug = (string)($definition['definition_slug'] ?? '');
		$version_number = (int)($version['version_number'] ?? 0);
		$path = $this->path($definition_slug, $version_number);
		$directory = dirname($path);

		$this->ensureDirectory($directory);

		$entry = [
			'definition_slug' => $definition_slug,
			'definition_id' => (int)($definition['definition_id'] ?? 0),
			'version_id' => (int)($version['version_id'] ?? 0),
			'version_number' => $version_number,
			'source' => (string)($definition['source'] ?? ''),
			'descriptor_hash' => (string)($version['descriptor_hash'] ?? ''),
			'normalized_descriptor_hash' => self::hashData($descriptor),
			'security_hash' => self::hashData($security),
			'compiled_at' => gmdate(DATE_ATOM),
			'descriptor' => $descriptor,
			'security' => $security,
		];
		$contents = "<?php\n\nreturn " . var_export($entry, true) . ";\n";
		$temp_path = $path . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(6));

		try {
			if (@file_put_contents($temp_path, $contents, LOCK_EX) === false) {
				throw new RuntimeException("Unable to write temporary form descriptor cache file: {$temp_path}");
			}

			$this->applyFilesystemMode($temp_path, false);

			if (!@rename($temp_path, $path)) {
				throw new RuntimeException("Unable to move form descriptor cache file into place: {$path}");
			}

			$this->applyFilesystemMode($path, false);
		} finally {
			if (is_file($temp_path)) {
				@unlink($temp_path);
			}
		}

		return [
			'path' => $path,
			'entry' => $entry,
		];
	}

	/**
	 * @param array<string, mixed> $definition
	 * @param array<string, mixed> $version
	 * @return array<string, mixed>|null
	 */
	public function read(array $definition, array $version): ?array
	{
		$definition_slug = (string)($definition['definition_slug'] ?? '');
		$version_number = (int)($version['version_number'] ?? 0);
		$path = $this->path($definition_slug, $version_number);

		if (!is_file($path)) {
			return null;
		}

		try {
			$entry = include $path;
		} catch (Throwable $exception) {
			error_log('[form-capture-cache] failed to read compiled descriptor cache: ' . $exception->getMessage());

			return null;
		}

		if (!is_array($entry)) {
			return null;
		}

		if (
			(string)($entry['definition_slug'] ?? '') !== $definition_slug
			|| (int)($entry['definition_id'] ?? 0) !== (int)($definition['definition_id'] ?? 0)
			|| (int)($entry['version_id'] ?? 0) !== (int)($version['version_id'] ?? 0)
			|| (int)($entry['version_number'] ?? 0) !== $version_number
			|| (string)($entry['source'] ?? '') !== (string)($definition['source'] ?? '')
			|| (string)($entry['descriptor_hash'] ?? '') !== (string)($version['descriptor_hash'] ?? '')
		) {
			return null;
		}

		if (!is_array($entry['descriptor'] ?? null) || !is_array($entry['security'] ?? null)) {
			return null;
		}

		return $entry;
	}

	public function deleteStaleForSlug(string $definition_slug, int $keep_version_number): void
	{
		foreach (glob($this->path($definition_slug, 0, true)) ?: [] as $path) {
			if (!is_string($path)) {
				continue;
			}

			if (preg_match('/\.v([0-9]+)\.php$/D', $path, $matches) !== 1) {
				continue;
			}

			if ((int)$matches[1] === $keep_version_number) {
				continue;
			}

			@unlink($path);
		}
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function hashData(array $data): string
	{
		return hash('sha256', self::encodeJson($data));
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function encodeJson(array $data): string
	{
		return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	}

	private function path(string $definition_slug, int $version_number, bool $glob = false): string
	{
		$root = defined('DEPLOY_ROOT') ? rtrim((string)DEPLOY_ROOT, '/') . '/' : getcwd() . '/';
		$version = $glob ? '*' : (string)$version_number;

		return $root . self::CACHE_ROOT . '/' . $definition_slug . '.v' . $version . '.php';
	}

	private function ensureDirectory(string $directory): void
	{
		if (!is_dir($directory) && !@mkdir($directory, $this->directoryMode(), true) && !is_dir($directory)) {
			throw new RuntimeException("Unable to create form descriptor cache directory: {$directory}");
		}

		$this->applyFilesystemMode($directory, true);
	}

	private function applyFilesystemMode(string $path, bool $directory): void
	{
		if (class_exists('Config')) {
			$owner = Config::LINUX_FILE_OWNER->value();
			$group = Config::LINUX_FILE_GROUP->value();

			if (is_string($owner) && $owner !== '') {
				@chown($path, $owner);
			}

			if (is_string($group) && $group !== '') {
				@chgrp($path, $group);
			}
		}

		@chmod($path, $directory ? $this->directoryMode() : $this->fileMode());
	}

	private function directoryMode(): int
	{
		return class_exists('Config') ? (int)Config::LINUX_FILE_MODE_DIRECTORY->value() : 0o775;
	}

	private function fileMode(): int
	{
		return class_exists('Config') ? (int)Config::LINUX_FILE_MODE->value() : 0o664;
	}
}
