<?php

declare(strict_types=1);

final class FormHookTargetRawEmail implements iFormHookTarget
{
	private const string DEFAULT_RECIPIENT_PRESET_KEY = 'default';

	private const array SAFE_METADATA_KEYS = [
		'subject',
		'include_empty_fields',
		'reply_to_field_key',
		'to',
	];

	public static function getHookTargetKey(): string
	{
		return FormHookTargetRegistry::KIND_RAW_FORM_DATA_EMAIL;
	}

	public function definition(): FormHookTargetDefinition
	{
		return new FormHookTargetDefinition(
			kind: self::getHookTargetKey(),
			nameKey: 'form.hooks.target.raw_form_data_email.name',
			descriptionKey: 'form.hooks.target.raw_form_data_email.description',
			requiresSystemDeveloper: false,
			supportsPresetKey: true,
			metadataSchema: [
				'subject' => ['type' => 'string', 'required' => false],
				'to' => ['type' => 'email', 'required' => true],
			],
		);
	}

	public function validateConfig(array $config, array $field_keys, bool $is_system_developer): void
	{
		$metadata = is_array($config['metadata'] ?? null) ? $config['metadata'] : [];
		$recipient = trim((string)($metadata['to'] ?? ''));
		$preset_key = trim((string)($config['preset_key'] ?? ''));
		$subject = trim((string)($metadata['subject'] ?? ''));
		$reply_to_field_key = trim((string)($metadata['reply_to_field_key'] ?? ''));

		if (!$is_system_developer) {
			foreach (array_keys($metadata) as $key) {
				if (!in_array((string)$key, self::SAFE_METADATA_KEYS, true)) {
					throw FormHookConfigValidationException::developerRoleRequired('metadata.' . $key);
				}
			}
		}

		if ($recipient !== '' && !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
			throw new FormHookConfigValidationException('FORM_HOOK_EMAIL_TO_INVALID', 'common.error_save', 422, ['metadata.to' => ['email']]);
		}

		if ($recipient === '') {
			if ($preset_key !== self::DEFAULT_RECIPIENT_PRESET_KEY) {
				throw new FormHookConfigValidationException('FORM_HOOK_EMAIL_TO_REQUIRED', 'common.error_save', 422, ['metadata.to' => ['required']]);
			}

			$recipient = $this->defaultRecipient();

			if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
				throw new FormHookConfigValidationException('FORM_HOOK_EMAIL_TO_INVALID', 'common.error_save', 422, ['preset_key' => ['default_recipient_invalid']]);
			}
		}

		if (mb_strlen($subject) > 190) {
			throw new FormHookConfigValidationException('FORM_HOOK_EMAIL_SUBJECT_TOO_LONG', 'common.error_save', 422, ['metadata.subject' => ['max_length']]);
		}

		if ($reply_to_field_key !== '' && !in_array($reply_to_field_key, $field_keys, true)) {
			throw new FormHookConfigValidationException('FORM_HOOK_EMAIL_REPLY_TO_UNKNOWN_FIELD', 'common.error_save', 422, ['metadata.reply_to_field_key' => ['unknown_field_key']]);
		}
	}

	public function invoke(FormHookInvocation $invocation): FormHookResult
	{
		if (!class_exists(EmailOrchestrator::class)) {
			return FormHookResult::failed('FORM_HOOK_EMAIL_UNAVAILABLE', 'Email orchestrator is not available.');
		}

		$to = $this->resolveRecipient($invocation);
		$subject = trim((string)($invocation->metadata['subject'] ?? ''));

		if ($to === null) {
			return FormHookResult::failed('FORM_HOOK_EMAIL_TO_INVALID', 'Raw email hook recipient is invalid.');
		}

		if ($subject === '') {
			$subject = t('form.hooks.email.default_subject', ['form' => $invocation->resolution->definitionSlug()]);
		}

		try {
			$result = EmailOrchestrator::enqueueTransactionalSnapshotAsSystem(
				$subject,
				$this->htmlBody($invocation->payload),
				$this->plainBody($invocation->payload),
				[
					[
						'email' => $to,
						'context' => [
							'form_definition_slug' => $invocation->resolution->definitionSlug(),
							'submission_id' => $invocation->submissionId,
							'delivery_id' => $invocation->deliveryId,
						],
					],
				],
			);

			return FormHookResult::queued($result);
		} catch (Throwable $exception) {
			return FormHookResult::failed('FORM_HOOK_EMAIL_QUEUE_FAILED', $exception->getMessage());
		}
	}

	private function resolveRecipient(FormHookInvocation $invocation): ?string
	{
		$recipient = trim((string)($invocation->metadata['to'] ?? ''));

		if ($recipient === '' && trim((string)$invocation->hook->preset_key) === self::DEFAULT_RECIPIENT_PRESET_KEY) {
			$recipient = $this->defaultRecipient();
		}

		if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
			return null;
		}

		return $recipient;
	}

	private function defaultRecipient(): string
	{
		if (enum_exists('Config')) {
			$config_case = Config::tryFrom('EMAIL_TO_ADDRESS');

			if ($config_case instanceof Config) {
				return trim((string)$config_case->value());
			}
		}

		if (defined(ApplicationConfig::class . '::EMAIL_TO_ADDRESS')) {
			return trim((string)constant(ApplicationConfig::class . '::EMAIL_TO_ADDRESS'));
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function plainBody(array $payload): string
	{
		return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function htmlBody(array $payload): string
	{
		return '<pre>' . htmlspecialchars($this->plainBody($payload), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
	}
}
