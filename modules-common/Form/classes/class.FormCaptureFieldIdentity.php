<?php

declare(strict_types=1);

final class FormCaptureFieldIdentity
{
	public const string DESCRIPTOR_KEY = 'editor_uid';
	private const string UID_PREFIX = 'f_';

	private function __construct()
	{
	}

	public static function isValidUid(mixed $uid): bool
	{
		return is_string($uid) && preg_match('/^f_[a-z0-9]{16}$/D', $uid) === 1;
	}

	public static function normalizeUid(mixed $uid): string
	{
		$uid = strtolower(trim((string)$uid));

		return self::isValidUid($uid) ? $uid : '';
	}

	/**
	 * @param array<string, true> $existing
	 */
	public static function generateUid(array $existing = []): string
	{
		do {
			$uid = self::UID_PREFIX . bin2hex(random_bytes(8));
		} while (isset($existing[$uid]));

		return $uid;
	}

	/**
	 * @param array<string, mixed> $field
	 */
	public static function legacyUidForField(array $field, int $index): string
	{
		$seed = implode('|', [
			(string)($field['type'] ?? ''),
			(string)($field['name'] ?? ''),
			(string)($field['key'] ?? $field['name'] ?? ''),
			(string)$index,
		]);

		return self::UID_PREFIX . substr(hash('sha256', $seed), 0, 16);
	}

	/**
	 * @param array<string, mixed> $descriptor
	 * @return array<string, mixed>
	 */
	public static function ensureDescriptorFieldUids(array $descriptor): array
	{
		$fields = is_array($descriptor['fields'] ?? null) ? $descriptor['fields'] : [];
		$seen = [];
		$normalized_fields = [];

		foreach ($fields as $index => $field) {
			if (!is_array($field)) {
				continue;
			}

			$uid = self::normalizeUid($field[self::DESCRIPTOR_KEY] ?? '');

			if ($uid === '' || isset($seen[$uid])) {
				$uid = self::uniqueLegacyUid($field, (int)$index, $seen);
			}

			$field[self::DESCRIPTOR_KEY] = $uid;
			$seen[$uid] = true;
			$normalized_fields[] = $field;
		}

		$descriptor['fields'] = $normalized_fields;

		return $descriptor;
	}

	public static function fieldTargetId(int $widget_connection_id, string $field_uid): string
	{
		$widget_connection_id = max(0, $widget_connection_id);
		$field_uid = self::normalizeUid($field_uid);

		if ($field_uid === '') {
			$field_uid = self::UID_PREFIX . str_repeat('0', 16);
		}

		return 'edit-widget-' . $widget_connection_id . '__field-' . $field_uid;
	}

	public static function formTargetId(int $widget_connection_id): string
	{
		return 'edit-widget-' . max(0, $widget_connection_id) . '__form';
	}

	public static function panelTargetId(int $widget_connection_id, string $field_uid): string
	{
		return self::fieldTargetId($widget_connection_id, $field_uid) . '__properties';
	}

	/**
	 * @param array<string, mixed> $field
	 * @param array<string, true> $seen
	 */
	private static function uniqueLegacyUid(array $field, int $index, array $seen): string
	{
		$uid = self::legacyUidForField($field, $index);
		$suffix = 1;

		while (isset($seen[$uid])) {
			$uid = self::UID_PREFIX . substr(hash('sha256', self::legacyUidForField($field, $index) . ':' . $suffix), 0, 16);
			++$suffix;
		}

		return $uid;
	}
}
