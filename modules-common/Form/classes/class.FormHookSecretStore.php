<?php

declare(strict_types=1);

final class FormHookSecretStore
{
	private const string CIPHER = 'aes-256-gcm';

	/**
	 * @return array{secret_ciphertext: string|null, secret_nonce: string|null, secret_tag: string|null, secret_mask: string|null}
	 */
	public static function encryptNullable(?string $secret): array
	{
		$secret = trim((string)$secret);

		if ($secret === '') {
			return [
				'secret_ciphertext' => null,
				'secret_nonce' => null,
				'secret_tag' => null,
				'secret_mask' => null,
			];
		}

		if (!function_exists('openssl_encrypt')) {
			throw new RuntimeException('OpenSSL is required to store capture form hook secrets.');
		}

		$nonce = random_bytes(12);
		$tag = '';
		$ciphertext = openssl_encrypt(
			$secret,
			self::CIPHER,
			self::encryptionKey(),
			OPENSSL_RAW_DATA,
			$nonce,
			$tag,
		);

		if (!is_string($ciphertext) || $tag === '') {
			throw new RuntimeException('Unable to encrypt capture form hook secret.');
		}

		return [
			'secret_ciphertext' => base64_encode($ciphertext),
			'secret_nonce' => base64_encode($nonce),
			'secret_tag' => base64_encode($tag),
			'secret_mask' => self::mask($secret),
		];
	}

	public static function decrypt(EntityFormHookTarget $hook): ?string
	{
		$ciphertext = trim((string)($hook->secret_ciphertext ?? ''));
		$nonce = trim((string)($hook->secret_nonce ?? ''));
		$tag = trim((string)($hook->secret_tag ?? ''));

		if ($ciphertext === '' || $nonce === '' || $tag === '') {
			return null;
		}

		if (!function_exists('openssl_decrypt')) {
			throw new RuntimeException('OpenSSL is required to read capture form hook secrets.');
		}

		$plain = openssl_decrypt(
			base64_decode($ciphertext, true) ?: '',
			self::CIPHER,
			self::encryptionKey(),
			OPENSSL_RAW_DATA,
			base64_decode($nonce, true) ?: '',
			base64_decode($tag, true) ?: '',
		);

		if (!is_string($plain)) {
			throw new RuntimeException('Unable to decrypt capture form hook secret.');
		}

		return $plain;
	}

	public static function mask(string $secret): string
	{
		$secret = trim($secret);
		$length = strlen($secret);

		if ($length <= 4) {
			return '****';
		}

		return str_repeat('*', min(16, max(8, $length - 4))) . substr($secret, -4);
	}

	private static function encryptionKey(): string
	{
		$secret = self::configuredAppSecret();

		if ($secret === null || trim($secret) === '') {
			throw FormCaptureRuntimeException::missingAppSecret();
		}

		return hash('sha256', 'form-hook-secret' . "\n" . $secret, true);
	}

	private static function configuredAppSecret(): ?string
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
}
