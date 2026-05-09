<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LocaleSwitchServiceTestRequest
{
	public static string $method = 'GET';

	public static function getMethod(): string
	{
		return self::$method;
	}
}

if (!class_exists('Request', false)) {
	class_alias(LocaleSwitchServiceTestRequest::class, 'Request');
}

final class LocaleSwitchServiceTestRequestContextHolder
{
	/** @var object{SERVER: array<string, mixed>, COOKIE: array<string, mixed>} */
	public static object $context;

	public static function current(): object
	{
		return self::$context;
	}
}

if (!class_exists('RequestContextHolder', false)) {
	class_alias(LocaleSwitchServiceTestRequestContextHolder::class, 'RequestContextHolder');
}

final class LocaleSwitchServiceTestUrl
{
	public static string $currentHost = 'https://example.test';

	/**
	 * @param array<string, mixed> $customparams
	 */
	public static function getUrl(string $eventName = '', array $customparams = [], string $ampersand = '&', string $base_href = '/'): string
	{
		[$context, $event] = explode('.', $eventName, 2);

		return $base_href . '?' . http_build_query($customparams + [
			'context' => $context,
			'event' => $event,
		], '', $ampersand);
	}

	public static function sanitizeRefererUrl(string $url): string
	{
		$url = trim($url);

		return preg_match('/[\r\n]/', $url) ? '' : $url;
	}

	public static function getCurrentHost(bool $includeRequestUri = true): string
	{
		return self::$currentHost;
	}
}

if (!class_exists('Url', false)) {
	class_alias(LocaleSwitchServiceTestUrl::class, 'Url');
}

require_once dirname(__DIR__) . '/modules-common/Cms/classes/class.LocaleSwitchService.php';

final class LocaleSwitchServiceTest extends TestCase
{
	protected function setUp(): void
	{
		LocaleSwitchServiceTestRequest::$method = 'GET';
		LocaleSwitchServiceTestUrl::$currentHost = 'https://example.test:8443';
		LocaleSwitchServiceTestRequestContextHolder::$context = (object) [
			'SERVER' => [
				'HTTPS' => 'on',
				'HTTP_HOST' => 'example.test:8443',
			],
			'COOKIE' => [],
		];

		if (property_exists(Request::class, 'method')) {
			Request::$method = 'GET';
		}

		if (property_exists(Url::class, 'currentHost')) {
			Url::$currentHost = 'https://example.test:8443';
		}

		if (property_exists(RequestContextHolder::class, 'context')) {
			RequestContextHolder::$context = LocaleSwitchServiceTestRequestContextHolder::$context;
		}
	}

	public function testSanitizeSameSiteReturnUrlAllowsRelativeAndSameOriginUrls(): void
	{
		$this->assertSame('/admin/?tab=content', LocaleSwitchService::sanitizeSameSiteReturnUrl('/admin/?tab=content'));
		$this->assertSame(
			'https://example.test:8443/content/page.html',
			LocaleSwitchService::sanitizeSameSiteReturnUrl('https://example.test:8443/content/page.html')
		);
	}

	public function testSanitizeSameSiteReturnUrlRejectsCrossOriginAndUnsafeUrls(): void
	{
		$this->assertSame('https://example.test:8443', LocaleSwitchService::sanitizeSameSiteReturnUrl('https://other.test/page.html'));
		$this->assertSame('https://example.test:8443', LocaleSwitchService::sanitizeSameSiteReturnUrl('//other.test/page.html'));
		$this->assertSame('https://example.test:8443', LocaleSwitchService::sanitizeSameSiteReturnUrl('javascript:alert(1)'));
	}

	public function testIsSameOriginPostRequestAcceptsSameOriginOriginHeader(): void
	{
		LocaleSwitchServiceTestRequest::$method = 'POST';
		Request::$method = 'POST';
		LocaleSwitchServiceTestRequestContextHolder::$context->SERVER['HTTP_ORIGIN'] = 'https://example.test:8443';

		$this->assertTrue(LocaleSwitchService::isSameOriginPostRequest());
	}

	public function testIsSameOriginPostRequestRejectsUnsafeMethodsAndOrigins(): void
	{
		LocaleSwitchServiceTestRequest::$method = 'GET';
		Request::$method = 'GET';
		LocaleSwitchServiceTestRequestContextHolder::$context->SERVER['HTTP_ORIGIN'] = 'https://example.test:8443';
		$this->assertFalse(LocaleSwitchService::isSameOriginPostRequest());

		LocaleSwitchServiceTestRequest::$method = 'POST';
		Request::$method = 'POST';
		LocaleSwitchServiceTestRequestContextHolder::$context->SERVER['HTTP_ORIGIN'] = 'null';
		$this->assertFalse(LocaleSwitchService::isSameOriginPostRequest());

		LocaleSwitchServiceTestRequestContextHolder::$context->SERVER['HTTP_ORIGIN'] = 'https://other.test';
		$this->assertFalse(LocaleSwitchService::isSameOriginPostRequest());
	}

	public function testIsSameOriginPostRequestFallsBackToRefererWhenOriginIsAbsent(): void
	{
		LocaleSwitchServiceTestRequest::$method = 'POST';
		Request::$method = 'POST';
		LocaleSwitchServiceTestRequestContextHolder::$context->SERVER['HTTP_REFERER'] = 'https://example.test:8443/admin/';

		$this->assertTrue(LocaleSwitchService::isSameOriginPostRequest());

		LocaleSwitchServiceTestRequestContextHolder::$context->SERVER['HTTP_REFERER'] = 'https://other.test/admin/';
		$this->assertFalse(LocaleSwitchService::isSameOriginPostRequest());
	}
}
