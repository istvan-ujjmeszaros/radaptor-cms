<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

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

	public function testHttpErrorThrowsMailpitClientException(): void
	{
		$client = new MailpitClient('mailpit.test:8025', static fn (): MailpitHttpResponse => new MailpitHttpResponse(404, '{"error":"not found"}'));

		$this->expectException(MailpitClientException::class);
		$this->expectExceptionMessage('not found');

		$client->getMessage('missing');
	}
}
