<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!enum_exists('Config', false)) {
	enum Config: string
	{
		case EMAIL_CATCHER_HOST = 'EMAIL_CATCHER_HOST';

		public function value(): mixed
		{
			return match ($this) {
				self::EMAIL_CATCHER_HOST => getenv('MAILPIT_TEST_CATCHER_HOST') ?: 'localhost',
			};
		}
	}
}

require_once dirname(__DIR__) . '/modules-common/Mailpit/classes/class.MailpitClientException.php';
require_once dirname(__DIR__) . '/modules-common/Mailpit/classes/class.MailpitHttpResponse.php';
require_once dirname(__DIR__) . '/modules-common/Mailpit/classes/class.MailpitClient.php';

final class MailpitClientTest extends TestCase
{
	public function testListMessagesBuildsExpectedApiUrl(): void
	{
		$seen = [];
		$client = new MailpitClient('http://mailpit.test:8025', static function (string $method, string $url, ?array $body, array $headers) use (&$seen): MailpitHttpResponse {
			$seen = compact('method', 'url', 'body', 'headers');

			return new MailpitHttpResponse(200, '{"messages":[],"total":0}');
		});

		$this->assertSame(['messages' => [], 'total' => 0], $client->listMessages(25, 10));
		$this->assertSame('GET', $seen['method']);
		$this->assertSame('http://mailpit.test:8025/api/v1/messages?start=25&limit=10', $seen['url']);
		$this->assertNull($seen['body']);
		$this->assertContains('Accept: application/json', $seen['headers']);
	}

	public function testDeleteMessagesSendsIdsBody(): void
	{
		$seen = [];
		$client = new MailpitClient('mailpit.test:8025', static function (string $method, string $url, ?array $body, array $headers) use (&$seen): MailpitHttpResponse {
			$seen = compact('method', 'url', 'body', 'headers');

			return new MailpitHttpResponse(200, 'OK');
		});

		$client->deleteMessages(['a', 'b']);

		$this->assertSame('DELETE', $seen['method']);
		$this->assertSame('http://mailpit.test:8025/api/v1/messages', $seen['url']);
		$this->assertSame(['IDs' => ['a', 'b']], $seen['body']);
		$this->assertContains('Content-Type: application/json', $seen['headers']);
	}

	public function testDeleteMessagesRequiresTargetByDefault(): void
	{
		$client = new MailpitClient('mailpit.test:8025', static fn (): MailpitHttpResponse => new MailpitHttpResponse(200, 'OK'));

		$this->expectException(MailpitClientException::class);
		$this->expectExceptionMessage('Mailpit delete target is required.');

		$client->deleteMessages();
	}

	public function testDeleteMessagesAllowsExplicitDeleteAll(): void
	{
		$seen = [];
		$client = new MailpitClient('mailpit.test:8025', static function (string $method, string $url, ?array $body, array $headers) use (&$seen): MailpitHttpResponse {
			$seen = compact('method', 'url', 'body', 'headers');

			return new MailpitHttpResponse(200, 'OK');
		});

		$client->deleteMessages(delete_all: true);

		$this->assertSame('DELETE', $seen['method']);
		$this->assertSame('http://mailpit.test:8025/api/v1/messages', $seen['url']);
		$this->assertNull($seen['body']);
	}

	public function testFromConfigUsesAppMailpitHttpPortWhenConfigCaseIsMissing(): void
	{
		$previous_port = getenv('APP_MAILPIT_HTTP_PORT');
		putenv('APP_MAILPIT_HTTP_PORT=8123');

		try {
			$seen = [];
			$client = new MailpitClient(null, static function (string $method, string $url, ?array $body, array $headers) use (&$seen): MailpitHttpResponse {
				$seen = compact('method', 'url', 'body', 'headers');

				return new MailpitHttpResponse(200, '{"messages":[],"total":0}');
			});

			$client->listMessages();

			$this->assertSame('http://localhost:8123/api/v1/messages?start=0&limit=50', $seen['url']);
		} finally {
			$this->restoreEnv('APP_MAILPIT_HTTP_PORT', $previous_port);
		}
	}

	public function testFromConfigUsesInternalPortForMailpitServiceHost(): void
	{
		$previous_host = getenv('MAILPIT_TEST_CATCHER_HOST');
		$previous_port = getenv('APP_MAILPIT_HTTP_PORT');
		putenv('MAILPIT_TEST_CATCHER_HOST=mailpit');
		putenv('APP_MAILPIT_HTTP_PORT=8123');

		try {
			$seen = [];
			$client = new MailpitClient(null, static function (string $method, string $url, ?array $body, array $headers) use (&$seen): MailpitHttpResponse {
				$seen = compact('method', 'url', 'body', 'headers');

				return new MailpitHttpResponse(200, '{"messages":[],"total":0}');
			});

			$client->listMessages();

			$this->assertSame('http://mailpit:8025/api/v1/messages?start=0&limit=50', $seen['url']);
		} finally {
			$this->restoreEnv('MAILPIT_TEST_CATCHER_HOST', $previous_host);
			$this->restoreEnv('APP_MAILPIT_HTTP_PORT', $previous_port);
		}
	}

	public function testSetReadRequiresTargetByDefault(): void
	{
		$client = new MailpitClient('mailpit.test:8025', static fn (): MailpitHttpResponse => new MailpitHttpResponse(200, 'OK'));

		$this->expectException(MailpitClientException::class);
		$this->expectExceptionMessage('Mailpit read target is required.');

		$client->setRead([], true);
	}

	public function testSetReadAllowsExplicitAll(): void
	{
		$seen = [];
		$client = new MailpitClient('mailpit.test:8025', static function (string $method, string $url, ?array $body, array $headers) use (&$seen): MailpitHttpResponse {
			$seen = compact('method', 'url', 'body', 'headers');

			return new MailpitHttpResponse(200, 'OK');
		});

		$client->setRead([], true, all: true);

		$this->assertSame('PUT', $seen['method']);
		$this->assertSame('http://mailpit.test:8025/api/v1/messages', $seen['url']);
		$this->assertSame(['Read' => true], $seen['body']);
	}

	public function testHttpErrorThrowsMailpitClientException(): void
	{
		$client = new MailpitClient('mailpit.test:8025', static fn (): MailpitHttpResponse => new MailpitHttpResponse(404, '{"error":"not found"}'));

		$this->expectException(MailpitClientException::class);
		$this->expectExceptionMessage('not found');

		$client->getMessage('missing');
	}

	private function restoreEnv(string $name, string|false $value): void
	{
		if ($value === false) {
			putenv($name);

			return;
		}

		putenv($name . '=' . $value);
	}
}
