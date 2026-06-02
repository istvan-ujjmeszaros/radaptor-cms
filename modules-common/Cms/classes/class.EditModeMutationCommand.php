<?php

declare(strict_types=1);

final class EditModeMutationCommand
{
	public const string OPERATION_REPLACE = 'replace';
	public const string TARGET_SLOT = 'slot';
	public const string TARGET_WIDGET = 'widget';
	public const string TARGET_FORM = 'form';
	public const string TARGET_FORM_FIELD = 'form_field';

	/**
	 * @param array<string, mixed> $context
	 */
	private function __construct(
		private readonly string $operation,
		private readonly string $targetType,
		private readonly string $targetId,
		private readonly array $context = [],
	) {
	}

	public static function replaceSlot(string $slot_name): self
	{
		return new self(self::OPERATION_REPLACE, self::TARGET_SLOT, $slot_name);
	}

	public static function replaceWidget(int $connection_id): self
	{
		return new self(self::OPERATION_REPLACE, self::TARGET_WIDGET, (string)$connection_id, [
			'widget_connection_id' => $connection_id,
		]);
	}

	public static function replaceForm(int $widget_connection_id): self
	{
		return new self(
			self::OPERATION_REPLACE,
			self::TARGET_FORM,
			FormCaptureFieldIdentity::formTargetId($widget_connection_id),
			[
				'widget_connection_id' => $widget_connection_id,
			],
		);
	}

	public static function replaceFormField(int $widget_connection_id, string $field_uid): self
	{
		return new self(
			self::OPERATION_REPLACE,
			self::TARGET_FORM_FIELD,
			FormCaptureFieldIdentity::fieldTargetId($widget_connection_id, $field_uid),
			[
				'widget_connection_id' => $widget_connection_id,
				'field_uid' => FormCaptureFieldIdentity::normalizeUid($field_uid),
				'panel_target_id' => FormCaptureFieldIdentity::panelTargetId($widget_connection_id, $field_uid),
			],
		);
	}

	public function operation(): string
	{
		return $this->operation;
	}

	public function targetType(): string
	{
		return $this->targetType;
	}

	public function targetId(): string
	{
		return $this->targetId;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function context(): array
	{
		return $this->context;
	}

	/**
	 * @return array{operation: string, target_type: string, target_id: string, context: array<string, mixed>}
	 */
	public function toArray(): array
	{
		return [
			'operation' => $this->operation,
			'target_type' => $this->targetType,
			'target_id' => $this->targetId,
			'context' => $this->context,
		];
	}
}
