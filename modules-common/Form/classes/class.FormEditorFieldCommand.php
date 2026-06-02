<?php

declare(strict_types=1);

final class FormEditorFieldCommand
{
	public const string ACTION_PANEL = 'panel';
	public const string ACTION_HTMX = 'htmx';
	public const string VARIANT_DEFAULT = 'default';
	public const string VARIANT_DANGER = 'danger';

	/**
	 * @param array<string, scalar|null> $payload
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $title,
		public readonly ?IconNames $icon,
		public readonly string $action,
		public readonly string $url = '',
		public readonly string $method = 'post',
		public readonly array $payload = [],
		public readonly string $variant = self::VARIANT_DEFAULT,
		public readonly bool $disabled = false,
		public readonly string $panelId = '',
		public readonly string $confirmTitle = '',
		public readonly string $confirmMessage = '',
		public readonly string $confirmLabel = '',
		public readonly string $cancelLabel = '',
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->id,
			'title' => $this->title,
			'icon' => $this->icon?->value,
			'action' => $this->action,
			'url' => $this->url,
			'method' => $this->method,
			'payload' => $this->payload,
			'variant' => $this->variant,
			'disabled' => $this->disabled,
			'panel_id' => $this->panelId,
			'confirm_title' => $this->confirmTitle,
			'confirm_message' => $this->confirmMessage,
			'confirm_label' => $this->confirmLabel,
			'cancel_label' => $this->cancelLabel,
		];
	}
}
