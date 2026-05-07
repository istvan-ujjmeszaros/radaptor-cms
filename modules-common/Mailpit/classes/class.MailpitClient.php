<?php

declare(strict_types=1);

final class MailpitClient
{
	/** @var callable|null */
	private $transport;

	/**
	 * @param callable|null $transport Test seam. Signature: fn(string $method, string $url, ?array $jsonBody, array $headers): MailpitHttpResponse
	 */
	public function __construct(?string $base_url = null, ?callable $transport = null)
	{
		$this->baseUrl = self::normalizeBaseUrl($base_url ?? self::baseUrlFromConfig());
		$this->transport = $transport;
	}

	private string $baseUrl;

	public static function fromConfig(): self
	{
		return new self();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function listMessages(int $start = 0, int $limit = 50): array
	{
		return $this->getJson('/api/v1/messages', [
			'start' => max(0, $start),
			'limit' => max(1, $limit),
		]);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function searchMessages(string $query, int $start = 0, int $limit = 50, string $timezone = ''): array
	{
		$params = [
			'query' => $query,
			'start' => (string) max(0, $start),
			'limit' => (string) max(1, $limit),
		];

		if ($timezone !== '') {
			$params['tz'] = $timezone;
		}

		return $this->getJson('/api/v1/search', $params);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getMessage(string $id): array
	{
		return $this->getJson('/api/v1/message/' . rawurlencode($id));
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getHeaders(string $id): array
	{
		return $this->getJson('/api/v1/message/' . rawurlencode($id) . '/headers');
	}

	public function getRaw(string $id): string
	{
		return $this->getText('/api/v1/message/' . rawurlencode($id) . '/raw');
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getHtmlCheck(string $id): array
	{
		return $this->getJson('/api/v1/message/' . rawurlencode($id) . '/html-check');
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getLinkCheck(string $id, bool $follow = false): array
	{
		return $this->getJson('/api/v1/message/' . rawurlencode($id) . '/link-check', [
			'follow' => $follow ? 'true' : 'false',
		]);
	}

	public function getAttachment(string $id, string $part_id, bool $thumbnail = false): MailpitHttpResponse
	{
		$path = '/api/v1/message/' . rawurlencode($id) . '/part/' . rawurlencode($part_id);

		if ($thumbnail) {
			$path .= '/thumb';
		}

		return $this->request('GET', $path, headers: ['Accept: */*']);
	}

	/**
	 * @param list<string> $ids
	 */
	public function deleteMessages(array $ids = [], string $search = '', string $timezone = '', bool $delete_all = false): string
	{
		if ($search !== '') {
			$params = ['query' => $search];

			if ($timezone !== '') {
				$params['tz'] = $timezone;
			}

			return $this->request('DELETE', '/api/v1/search', $params)->body;
		}

		if ($ids === []) {
			if (!$delete_all) {
				throw new MailpitClientException('Mailpit delete target is required.');
			}

			return $this->request('DELETE', '/api/v1/messages')->body;
		}

		return $this->request('DELETE', '/api/v1/messages', jsonBody: ['IDs' => array_values($ids)])->body;
	}

	/**
	 * @param list<string> $ids
	 */
	public function setRead(array $ids, bool $read, string $search = '', string $timezone = ''): string
	{
		$params = [];

		if ($timezone !== '') {
			$params['tz'] = $timezone;
		}

		$body = [
			'Read' => $read,
		];

		if ($ids !== []) {
			$body['IDs'] = array_values($ids);
		}

		if ($search !== '') {
			$body['Search'] = $search;
		}

		return $this->request('PUT', '/api/v1/messages', $params, $body)->body;
	}

	/**
	 * @param array<string, mixed> $query
	 * @return array<string, mixed>
	 */
	private function getJson(string $path, array $query = []): array
	{
		return $this->request('GET', $path, $query, headers: ['Accept: application/json'])->json();
	}

	/**
	 * @param array<string, mixed> $query
	 */
	private function getText(string $path, array $query = []): string
	{
		return $this->request('GET', $path, $query, headers: ['Accept: text/plain'])->body;
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<string, mixed>|null $jsonBody
	 * @param list<string> $headers
	 */
	public function request(string $method, string $path, array $query = [], ?array $jsonBody = null, array $headers = []): MailpitHttpResponse
	{
		$url = $this->buildUrl($path, $query);
		$headers = $jsonBody === null
			? $headers
			: array_values(array_unique([...$headers, 'Content-Type: application/json', 'Accept: application/json']));

		if ($this->transport !== null) {
			$response = ($this->transport)($method, $url, $jsonBody, $headers);
		} else {
			$response = $this->curlRequest($method, $url, $jsonBody, $headers);
		}

		if ($response->statusCode >= 400) {
			$payload = $response->json();
			$message = (string) ($payload['error'] ?? $payload['message'] ?? $response->body);
			$message = trim($message) !== '' ? trim($message) : 'Mailpit request failed.';

			throw new MailpitClientException($message, $response->statusCode, $payload);
		}

		return $response;
	}

	/**
	 * @param array<string, mixed>|null $jsonBody
	 * @param list<string> $headers
	 */
	private function curlRequest(string $method, string $url, ?array $jsonBody, array $headers): MailpitHttpResponse
	{
		$handle = curl_init($url);

		if ($handle === false) {
			throw new MailpitClientException('Could not initialize cURL for Mailpit.');
		}

		$response_headers = [];
		$current_header_block = [];
		$options_set = curl_setopt_array($handle, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => strtoupper($method),
			CURLOPT_CONNECTTIMEOUT => 2,
			CURLOPT_TIMEOUT => 8,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_HEADERFUNCTION => static function (CurlHandle $handle, string $header_line) use (&$response_headers, &$current_header_block): int {
				$length = strlen($header_line);
				$line = trim($header_line);

				if ($line === '') {
					if ($current_header_block !== []) {
						$response_headers = $current_header_block;
						$current_header_block = [];
					}

					return $length;
				}

				if (str_starts_with($line, 'HTTP/')) {
					$current_header_block = [];

					return $length;
				}

				if (str_contains($line, ':')) {
					[$name, $value] = explode(':', $line, 2);
					$name = strtolower(trim($name));

					if ($name !== '') {
						$current_header_block[$name][] = trim($value);
					}
				}

				return $length;
			},
		]);

		if (!$options_set) {
			throw new MailpitClientException('Could not configure cURL for Mailpit.');
		}

		if ($jsonBody !== null) {
			$json = json_encode($jsonBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

			if (!is_string($json) || !curl_setopt($handle, CURLOPT_POSTFIELDS, $json)) {
				throw new MailpitClientException('Could not configure Mailpit request body.');
			}
		}

		$raw = curl_exec($handle);

		if ($raw === false) {
			$error = curl_error($handle);

			throw new MailpitClientException('Mailpit is unavailable: ' . $error);
		}

		$status_code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

		return new MailpitHttpResponse(
			$status_code,
			is_string($raw) ? $raw : '',
			$response_headers
		);
	}

	/**
	 * @param array<string, mixed> $query
	 */
	private function buildUrl(string $path, array $query = []): string
	{
		$path = '/' . ltrim($path, '/');
		$url = $this->baseUrl . $path;

		if ($query !== []) {
			$url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
		}

		return $url;
	}

	private static function baseUrlFromConfig(): string
	{
		$host = trim((string) self::configValue('EMAIL_CATCHER_HOST', 'mailpit'));
		$port = (int) self::configValue('EMAIL_CATCHER_HTTP_PORT', null);

		if ($port < 1) {
			$port = self::envInt('APP_MAILPIT_HTTP_PORT') ?? 8025;
		}

		return $host . ':' . $port;
	}

	private static function configValue(string $name, mixed $fallback): mixed
	{
		if (!enum_exists('Config') || !method_exists(Config::class, 'tryFrom')) {
			return $fallback;
		}

		$case = Config::tryFrom($name);

		if (!is_object($case) || !method_exists($case, 'value')) {
			return $fallback;
		}

		return $case->value();
	}

	private static function envInt(string $name): ?int
	{
		$value = getenv($name);

		if ($value === false || trim($value) === '') {
			return null;
		}

		$int_value = (int) $value;

		return $int_value > 0 ? $int_value : null;
	}

	private static function normalizeBaseUrl(string $base_url): string
	{
		$base_url = trim($base_url);

		if ($base_url === '') {
			$base_url = 'mailpit:8025';
		}

		if (!preg_match('#^https?://#i', $base_url)) {
			$base_url = 'http://' . $base_url;
		}

		return rtrim($base_url, '/');
	}
}
