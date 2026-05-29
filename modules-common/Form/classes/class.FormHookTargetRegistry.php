<?php

declare(strict_types=1);

final class FormHookTargetRegistry
{
	public const string KIND_CUSTOM_HTTPS_WEBHOOK = 'custom_https_webhook';
	public const string KIND_RAW_FORM_DATA_EMAIL = 'raw_form_data_email';

	/**
	 * @return array<string, iFormHookTarget>
	 */
	public static function all(): array
	{
		$targets = [
			new FormHookTargetCustomHttpsWebhook(),
			new FormHookTargetRawEmail(),
		];
		$indexed = [];

		foreach ($targets as $target) {
			$kind = self::normalizeKind($target->definition()->kind);

			if ($kind === '') {
				continue;
			}

			$indexed[$kind] = $target;
		}

		ksort($indexed);

		return $indexed;
	}

	public static function get(string $kind): ?iFormHookTarget
	{
		$kind = self::normalizeKind($kind);
		$targets = self::all();

		return $targets[$kind] ?? null;
	}

	/**
	 * @return array<string, class-string<iFormHookTarget>>
	 */
	public static function targetClasses(): array
	{
		return [
			self::KIND_CUSTOM_HTTPS_WEBHOOK => FormHookTargetCustomHttpsWebhook::class,
			self::KIND_RAW_FORM_DATA_EMAIL => FormHookTargetRawEmail::class,
		];
	}

	public static function create(string $kind): ?iFormHookTarget
	{
		return self::get($kind);
	}

	public static function targetRequiresDeveloper(string $kind): bool
	{
		return self::get($kind)?->definition()->requiresSystemDeveloper ?? false;
	}

	public static function normalizeKind(string $kind): string
	{
		$kind = strtolower(trim($kind));
		$kind = str_replace('-', '_', $kind);

		return match ($kind) {
			'custom', 'custom_http', 'https_webhook', 'webhook', 'custom_https' => self::KIND_CUSTOM_HTTPS_WEBHOOK,
			'email', 'raw_email', 'raw_form_email' => self::KIND_RAW_FORM_DATA_EMAIL,
			default => preg_replace('/[^a-z0-9_]+/', '_', $kind) ?? '',
		};
	}

	/**
	 * @return array{targets: list<array<string, mixed>>, presets: list<array<string, mixed>>}
	 */
	public static function discoveryPayload(): array
	{
		$targets = [];
		$presets = [];

		foreach (self::all() as $target) {
			$definition = $target->definition();
			$target_data = $definition->toArray();
			$targets[] = $target_data;
			$presets[] = [
				'id' => $definition->kind,
				'label' => $target_data['name'],
				'target_kind' => $definition->kind,
				'target_url' => '',
				'metadata' => [],
				'excluded_fields' => [],
			];
		}

		return [
			'targets' => $targets,
			'presets' => $presets,
		];
	}
}
