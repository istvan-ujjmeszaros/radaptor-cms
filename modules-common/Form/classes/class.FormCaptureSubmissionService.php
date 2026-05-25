<?php

declare(strict_types=1);

final class FormCaptureSubmissionService
{
	private const array UNSAFE_APP_SECRET_VALUES = [
		'change-me-to-a-random-secret',
	];

	/**
	 * @param array{rate_limit?: array{accepted?: int, window_seconds?: int}} $security
	 */
	public function isRateLimited(FormDefinitionResolution $resolution, array $security): bool
	{
		$rate_limit = $security['rate_limit'] ?? [];
		$accepted = (int)($rate_limit['accepted'] ?? FormCaptureDescriptorSchemaValidator::DEFAULT_RATE_LIMIT_ACCEPTED);
		$window_seconds = (int)($rate_limit['window_seconds'] ?? FormCaptureDescriptorSchemaValidator::DEFAULT_RATE_LIMIT_WINDOW_SECONDS);
		$ip_hash = $this->clientIpHash();
		$since = date('Y-m-d H:i:s', time() - $window_seconds);
		$count = (int)DbHelper::selectOneColumnFromQuery(
			'SELECT COUNT(1)
			FROM form_submissions
			WHERE definition_id=?
			  AND ip_hash=?
			  AND created_at >= ?',
			[
				$resolution->definitionId(),
				$ip_hash,
				$since,
			],
		);

		return $count >= $accepted;
	}

	/**
	 * @param array<string, mixed> $payload
	 * @param array<string, mixed> $render_context
	 */
	public function store(FormDefinitionResolution $resolution, array $payload, array $render_context): int
	{
		$payload_json = json_encode(
			$payload,
			JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
		);
		$user_id = User::getCurrentUserId();
		$submission = EntityFormSubmission::createFromArray([
			'definition_id' => $resolution->definitionId(),
			'version_id' => $resolution->versionId(),
			'definition_slug' => $resolution->definitionSlug(),
			'payload_json' => $payload_json,
			'user_id' => $user_id > 0 ? $user_id : null,
			'locale' => Kernel::getLocale(),
			'ip_hash' => $this->clientIpHash(),
			'user_agent_hash' => $this->clientUserAgentHash(),
			'host_page_id' => $this->positiveIntOrNull($render_context['host_page_id'] ?? null),
			'widget_connection_id' => $this->positiveIntOrNull($render_context['widget_connection_id'] ?? null),
		]);

		return (int)$submission->submission_id;
	}

	private function clientIpHash(): string
	{
		$server = RequestContextHolder::current()->SERVER ?: $_SERVER;
		$ip = trim((string)($server['REMOTE_ADDR'] ?? $server['remote_addr'] ?? ''));

		return $this->clientFingerprintHash($ip !== '' ? $ip : 'unknown', 'capture_form.ip');
	}

	private function clientUserAgentHash(): ?string
	{
		$server = RequestContextHolder::current()->SERVER ?: $_SERVER;
		$user_agent = trim((string)($server['HTTP_USER_AGENT'] ?? $server['http_user_agent'] ?? ''));

		return $user_agent !== '' ? $this->clientFingerprintHash($user_agent, 'capture_form.user_agent') : null;
	}

	private function clientFingerprintHash(string $value, string $context): string
	{
		return hash_hmac('sha256', $context . "\n" . $value, $this->clientFingerprintHashKey());
	}

	private function clientFingerprintHashKey(): string
	{
		$secret = trim($this->configuredAppSecret() ?? '');

		if ($secret === '' || in_array($secret, self::UNSAFE_APP_SECRET_VALUES, true)) {
			throw FormCaptureRuntimeException::missingAppSecret();
		}

		return $secret;
	}

	private function configuredAppSecret(): ?string
	{
		$env_secret = getenv('APP_SECRET');

		if (is_string($env_secret) && trim($env_secret) !== '') {
			return $env_secret;
		}

		if (enum_exists('Config') && method_exists(Config::class, 'tryFrom')) {
			$config_case = Config::tryFrom('APP_SECRET');

			if ($config_case instanceof Config) {
				$config_secret = $config_case->value();

				if (is_scalar($config_secret) && trim((string)$config_secret) !== '') {
					return (string)$config_secret;
				}
			}
		}

		if (defined(ApplicationConfig::class . '::APP_SECRET')) {
			$app_secret = constant(ApplicationConfig::class . '::APP_SECRET');

			if (is_scalar($app_secret) && trim((string)$app_secret) !== '') {
				return (string)$app_secret;
			}
		}

		return null;
	}

	private function positiveIntOrNull(mixed $value): ?int
	{
		$value = (int)$value;

		return $value > 0 ? $value : null;
	}
}
