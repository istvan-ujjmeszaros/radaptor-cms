<?php

declare(strict_types=1);

final class CmsMutationAuditService
{
	private const string TABLE = 'cms_mutation_audit';
	private const int MAX_JSON_BYTES = 65536;
	private const int LONG_SCALAR_MAX_BYTES = 160;
	private const string REDACTED = '[redacted]';

	/** @var list<array<string, mixed>> */
	private static array $_context_stack = [];
	private static ?bool $_table_exists = null;
	private static ?PDO $_audit_pdo = null;

	/**
	 * @param array<string, mixed> $args
	 * @param callable(): mixed $callback
	 * @param array<string, mixed> $metadata
	 */
	public static function withContext(string $operation, array $args, callable $callback, array $metadata = []): mixed
	{
		$context = self::buildContext($operation, $args, $metadata);
		self::$_context_stack[] = $context;
		self::insertAuditRow($context, 'context_started', [
			'result_status' => 'started',
			'metadata' => $metadata,
		]);

		try {
			$result = $callback();
			self::insertAuditRow($context, 'context_finished', [
				'result_status' => 'success',
			]);

			return $result;
		} catch (Throwable $exception) {
			self::insertAuditRow($context, 'context_finished', [
				'result_status' => 'failed',
				'error_class' => get_class($exception),
				'error_message' => $exception->getMessage(),
			]);

			throw $exception;
		} finally {
			array_pop(self::$_context_stack);
		}
	}

	/**
	 * @param array<string, mixed> $args
	 * @param callable(): mixed $callback
	 * @param array<string, mixed> $metadata
	 */
	public static function withContextIfMissing(string $operation, array $args, callable $callback, array $metadata = []): mixed
	{
		if (self::currentContext() !== null) {
			return $callback();
		}

		return self::withContext($operation, $args, $callback, ['actor_type' => 'internal'] + $metadata);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function recordLeaf(string $operation, array $data = []): void
	{
		$context = self::currentContext();

		if ($context !== null) {
			self::insertAuditRow($context, 'leaf', ['operation' => $operation] + $data);

			return;
		}

		self::withContext($operation, [], static function () use ($operation, $data): void {
			$context = self::currentContext();

			if ($context !== null) {
				self::insertAuditRow($context, 'leaf', ['operation' => $operation] + $data);
			}
		}, ['actor_type' => 'internal']);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function prune(int $retention_days, bool $dry_run = true): array
	{
		if ($retention_days < 1) {
			throw new InvalidArgumentException('Retention days must be at least 1.');
		}

		$cutoff = gmdate('Y-m-d H:i:s', time() - ($retention_days * 86400));

		if (!self::tableExists()) {
			return [
				'status' => 'skipped',
				'dry_run' => $dry_run,
				'retention_days' => $retention_days,
				'cutoff' => $cutoff,
				'matched_rows' => 0,
				'deleted_rows' => 0,
				'message' => 'cms_mutation_audit table does not exist.',
			];
		}

		$pdo = self::auditPdo();
		$stmt = $pdo->prepare('SELECT COUNT(*) FROM `cms_mutation_audit` WHERE `created_at` < ?');
		$stmt->execute([$cutoff]);
		$matched = (int) $stmt->fetchColumn();
		$deleted = 0;

		if (!$dry_run && $matched > 0) {
			$delete = $pdo->prepare('DELETE FROM `cms_mutation_audit` WHERE `created_at` < ?');
			$delete->execute([$cutoff]);
			$deleted = $delete->rowCount();
		}

		return [
			'status' => 'success',
			'dry_run' => $dry_run,
			'retention_days' => $retention_days,
			'cutoff' => $cutoff,
			'matched_rows' => $matched,
			'deleted_rows' => $deleted,
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function currentContext(): ?array
	{
		$key = array_key_last(self::$_context_stack);

		return $key === null ? null : self::$_context_stack[$key];
	}

	public static function resetForTests(): void
	{
		self::$_context_stack = [];
		self::$_table_exists = null;
		self::$_audit_pdo = null;
	}

	/**
	 * @param array<string, mixed> $args
	 * @param array<string, mixed> $metadata
	 * @return array<string, mixed>
	 */
	private static function buildContext(string $operation, array $args, array $metadata): array
	{
		$parent = self::currentContext();
		$actor_type = (string) ($metadata['actor_type'] ?? self::detectActorType());

		if (!in_array($actor_type, ['cli', 'web', 'internal'], true)) {
			$actor_type = 'internal';
		}

		return [
			'correlation_id' => self::uuidV4(),
			'parent_correlation_id' => is_array($parent) ? (string) $parent['correlation_id'] : null,
			'operation' => $operation,
			'actor_type' => $actor_type,
			'actor_user_id' => self::detectActorUserId(),
			'cli_command' => $actor_type === 'cli' ? self::detectCliCommand() : null,
			'args_hash' => self::hashPayload($args),
			'args_redacted_json' => self::encodePayload(self::redactPayload($args)),
		];
	}

	/**
	 * @param array<string, mixed> $context
	 * @param array<string, mixed> $data
	 */
	private static function insertAuditRow(array $context, string $phase, array $data): void
	{
		try {
			if (!self::tableExists()) {
				return;
			}

			$operation = (string) ($data['operation'] ?? $context['operation']);
			$stmt = self::auditPdo()->prepare(
				'INSERT INTO `cms_mutation_audit` (
					`correlation_id`, `parent_correlation_id`, `phase`, `operation`, `actor_type`,
					`actor_user_id`, `cli_command`, `args_hash`, `args_redacted_json`, `resource_id`,
					`page_id`, `widget_connection_id`, `resource_path`, `slot_name`, `widget_name`,
					`result_status`, `affected_count`, `error_code`, `error_class`, `error_message`, `before_json`, `after_json`,
					`summary_json`, `metadata_json`
				) VALUES (
					?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
				)'
			);

			$stmt->execute([
				(string) $context['correlation_id'],
				$context['parent_correlation_id'],
				$phase,
				$operation,
				(string) $context['actor_type'],
				$context['actor_user_id'],
				$context['cli_command'],
				$context['args_hash'],
				$context['args_redacted_json'],
				self::nullableInt($data['resource_id'] ?? null),
				self::nullableInt($data['page_id'] ?? null),
				self::nullableInt($data['widget_connection_id'] ?? null),
				self::nullableString($data['resource_path'] ?? null, 1024),
				self::nullableString($data['slot_name'] ?? null, 190),
				self::nullableString($data['widget_name'] ?? null, 190),
				(string) ($data['result_status'] ?? 'success'),
				self::affectedCount($data),
				self::nullableString($data['error_code'] ?? null, 190),
				self::nullableString($data['error_class'] ?? null, 190),
				self::nullableString($data['error_message'] ?? null, 512),
				array_key_exists('before', $data) ? self::encodePayload($data['before']) : null,
				array_key_exists('after', $data) ? self::encodePayload($data['after']) : null,
				array_key_exists('summary', $data) ? self::encodePayload($data['summary']) : null,
				array_key_exists('metadata', $data) ? self::encodePayload($data['metadata']) : null,
			]);
		} catch (Throwable $exception) {
			error_log('[cms-mutation-audit] failed to write audit row: ' . $exception->getMessage());
		}
	}

	private static function tableExists(): bool
	{
		if (self::$_table_exists === true) {
			return true;
		}

		try {
			$stmt = self::auditPdo()->query("SHOW TABLES LIKE 'cms_mutation_audit'");

			return self::$_table_exists = $stmt !== false && $stmt->rowCount() > 0;
		} catch (Throwable) {
			self::$_table_exists = false;

			return false;
		}
	}

	private static function auditPdo(): PDO
	{
		if (!self::$_audit_pdo instanceof PDO) {
			self::$_audit_pdo = Db::createIndependentPdoConnection();
		}

		return self::$_audit_pdo;
	}

	private static function detectActorType(): string
	{
		if (defined('RADAPTOR_CLI')) {
			return 'cli';
		}

		return PHP_SAPI === 'cli' ? 'internal' : 'web';
	}

	private static function detectActorUserId(): ?int
	{
		if (!class_exists(User::class) || defined('RADAPTOR_CLI')) {
			return null;
		}

		try {
			$user_id = User::getCurrentUserId();

			return $user_id > 0 ? $user_id : null;
		} catch (Throwable) {
			return null;
		}
	}

	private static function detectCliCommand(): ?string
	{
		global $argv;

		foreach ($argv ?? [] as $arg) {
			if (is_string($arg) && str_contains($arg, ':') && !str_starts_with($arg, '--')) {
				return mb_substr($arg, 0, 190);
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private static function hashPayload(array $payload): string
	{
		self::ksortRecursive($payload);

		return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
	}

	private static function encodePayload(mixed $payload): string
	{
		if (is_array($payload)) {
			self::ksortRecursive($payload);
		}

		$json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

		if (strlen($json) <= self::MAX_JSON_BYTES) {
			return $json;
		}

		return json_encode([
			'__truncated' => true,
			'__original_size' => strlen($json),
			'__sha256' => hash('sha256', $json),
		], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
	}

	private static function redactPayload(mixed $payload, string $key = ''): mixed
	{
		if (self::isSecretKey($key)) {
			return self::REDACTED;
		}

		if (is_array($payload)) {
			$redacted = [];

			foreach ($payload as $child_key => $child_value) {
				$redacted[$child_key] = self::redactPayload($child_value, (string) $child_key);
			}

			return $redacted;
		}

		if (is_scalar($payload) || $payload === null) {
			$value = (string) $payload;

			if (strlen($value) > self::LONG_SCALAR_MAX_BYTES) {
				return [
					'__redacted_long_scalar' => true,
					'__length' => strlen($value),
					'__sha256' => hash('sha256', $value),
				];
			}
		}

		return $payload;
	}

	private static function isSecretKey(string $key): bool
	{
		return preg_match('/(password|passwd|token|secret|api[_-]?key|authorization|cookie|session|dsn)/i', $key) === 1;
	}

	/**
	 * @param array<string|int, mixed> $value
	 */
	private static function ksortRecursive(array &$value): void
	{
		if (!array_is_list($value)) {
			ksort($value);
		}

		foreach ($value as &$child) {
			if (is_array($child)) {
				self::ksortRecursive($child);
			}
		}
	}

	private static function nullableInt(mixed $value): ?int
	{
		return is_numeric($value) ? (int) $value : null;
	}

	private static function nullableString(mixed $value, int $max_length): ?string
	{
		if ($value === null) {
			return null;
		}

		$string = (string) $value;

		return mb_substr($string, 0, $max_length);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function affectedCount(array $data): int
	{
		if (isset($data['affected_count']) && is_numeric($data['affected_count'])) {
			return max(0, (int) $data['affected_count']);
		}

		if (isset($data['summary']) && is_array($data['summary']) && isset($data['summary']['affected_count']) && is_numeric($data['summary']['affected_count'])) {
			return max(0, (int) $data['summary']['affected_count']);
		}

		return 0;
	}

	private static function uuidV4(): string
	{
		$bytes = random_bytes(16);
		$bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
		$bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
	}
}
