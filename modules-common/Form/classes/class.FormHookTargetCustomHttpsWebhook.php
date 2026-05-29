<?php

declare(strict_types=1);

final class FormHookTargetCustomHttpsWebhook implements iFormHookTarget
{
	public static function getHookTargetKey(): string
	{
		return FormHookTargetRegistry::KIND_CUSTOM_HTTPS_WEBHOOK;
	}

	public function definition(): FormHookTargetDefinition
	{
		return new FormHookTargetDefinition(
			kind: self::getHookTargetKey(),
			nameKey: 'form.hooks.target.custom_https_webhook.name',
			descriptionKey: 'form.hooks.target.custom_https_webhook.description',
			requiresSystemDeveloper: true,
			supportsUrl: true,
			supportsSecret: true,
		);
	}

	public function validateConfig(array $config, array $field_keys, bool $is_system_developer): void
	{
		if (!$is_system_developer) {
			throw FormHookConfigValidationException::developerRoleRequired('target_kind');
		}

		$url = trim((string)($config['url'] ?? ''));

		if ($url === '') {
			throw new FormHookConfigValidationException('FORM_HOOK_URL_REQUIRED', 'common.error_save', 422, ['url' => ['required']]);
		}

		if (class_exists(OutboundHttpJsonClient::class)) {
			try {
				OutboundHttpJsonClient::validateUrlForDelivery($url, false);
			} catch (OutboundDeliveryException $exception) {
				throw new FormHookConfigValidationException(
					'FORM_HOOK_URL_NOT_ALLOWED',
					'common.error_save',
					422,
					['url' => [$exception->getErrorCodeString()]],
					['message' => $exception->getMessage()],
				);
			}
		} else {
			$parts = parse_url($url);

			if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') {
				throw new FormHookConfigValidationException('FORM_HOOK_URL_NOT_ALLOWED', 'common.error_save', 422, ['url' => ['https_required']]);
			}
		}
	}

	public function invoke(FormHookInvocation $invocation): FormHookResult
	{
		$secret = FormHookSecretStore::decrypt($invocation->hook);
		$timestamp = (string)time();
		$body = FormCaptureCompiledDescriptorCache::encodeJson($invocation->payload);
		$headers = [
			'X-Radaptor-Hook-Event' => 'capture_form.submitted',
			'X-Radaptor-Hook-Delivery' => 'formhook_' . $invocation->deliveryId,
			'X-Radaptor-Hook-Timestamp' => $timestamp,
		];

		if ($secret !== null && trim($secret) !== '') {
			$headers['X-Radaptor-Hook-Signature-256'] = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
		}

		return (new FormHookOutboundDeliveryAdapter())->enqueue([
			'job_id' => 'formhook_' . $invocation->deliveryId,
			'url' => (string)$invocation->hook->url,
			'headers' => $headers,
			'body' => $invocation->payload,
			'metadata' => [
				'form_definition_slug' => $invocation->resolution->definitionSlug(),
				'submission_id' => $invocation->submissionId,
				'delivery_id' => $invocation->deliveryId,
			],
		]);
	}
}
