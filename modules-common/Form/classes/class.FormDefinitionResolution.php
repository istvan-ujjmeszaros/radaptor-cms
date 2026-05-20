<?php

declare(strict_types=1);

final class FormDefinitionResolution
{
	public const string KIND_SYSTEM = 'system';
	public const string KIND_CAPTURE = 'capture';

	/**
	 * @param class-string<AbstractForm>|null $_class_name
	 * @param array<string, mixed> $_definition
	 * @param array<string, mixed> $_version
	 * @param array<string, mixed> $_descriptor
	 * @param array<string, mixed> $_security
	 */
	private function __construct(
		private readonly string $_kind,
		private readonly string $_form_id,
		private readonly ?string $_class_name = null,
		private readonly array $_definition = [],
		private readonly array $_version = [],
		private readonly array $_descriptor = [],
		private readonly array $_security = [],
	) {
	}

	/**
	 * @param class-string<AbstractForm> $class_name
	 */
	public static function system(string $form_id, string $class_name): self
	{
		return new self(self::KIND_SYSTEM, $form_id, $class_name);
	}

	/**
	 * @param array<string, mixed> $definition
	 * @param array<string, mixed> $version
	 * @param array<string, mixed> $descriptor
	 * @param array<string, mixed> $security
	 */
	public static function capture(string $definition_slug, array $definition, array $version, array $descriptor, array $security): self
	{
		return new self(self::KIND_CAPTURE, $definition_slug, CaptureForm::class, $definition, $version, $descriptor, $security);
	}

	public function kind(): string
	{
		return $this->_kind;
	}

	public function formId(): string
	{
		return $this->_form_id;
	}

	public function isSystem(): bool
	{
		return $this->_kind === self::KIND_SYSTEM;
	}

	public function isCapture(): bool
	{
		return $this->_kind === self::KIND_CAPTURE;
	}

	/**
	 * @return class-string<AbstractForm>
	 */
	public function className(): string
	{
		if ($this->_class_name === null) {
			Kernel::abort('Capture form resolution does not have a PHP form class.');
		}

		return $this->_class_name;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function definition(): array
	{
		return $this->_definition;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function version(): array
	{
		return $this->_version;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function descriptor(): array
	{
		return $this->_descriptor;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function security(): array
	{
		return $this->_security;
	}

	public function definitionId(): int
	{
		return (int)($this->_definition['definition_id'] ?? 0);
	}

	public function versionId(): int
	{
		return (int)($this->_version['version_id'] ?? 0);
	}

	public function definitionSlug(): string
	{
		return (string)($this->_definition['definition_slug'] ?? $this->_form_id);
	}
}
