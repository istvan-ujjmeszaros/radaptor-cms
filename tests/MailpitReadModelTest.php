<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MailpitReadModelTest extends TestCase
{
	public function testMessageViewUsesResolvableMutationEventNames(): void
	{
		self::loadMailpitRuntime();

		$view = MailpitCatcherUrls::withResolvers(
			static function (int $page_id, string $subpath = '', array $query = []): string {
				$url = '/page-' . $page_id . '/' . ltrim($subpath, '/');

				return $query === [] ? $url : $url . '?' . http_build_query($query);
			},
			static fn (string $event, array $params = []): string => '/?context=mailpit&event=' . $event . ($params === [] ? '' : '&' . http_build_query($params)),
			static function (): array {
				$client = new MailpitClient('mailpit.test:8025', static fn (): MailpitHttpResponse => new MailpitHttpResponse(200, json_encode([
					'ID' => 'message-1',
					'Subject' => 'Subject',
					'From' => [],
					'To' => [],
					'Date' => '2026-05-07T10:00:00Z',
					'Size' => 42,
					'HTML' => '',
					'Text' => '',
				], JSON_THROW_ON_ERROR)));

				return MailpitReadModel::messageView(
					10,
					20,
					['id' => 'message-1'],
					$client,
					static fn (string $label_key): string => $label_key
				);
			}
		);

		$this->assertSame('/?context=mailpit&event=messagesDelete', $view['urls']['delete']);
		$this->assertSame('/?context=mailpit&event=messagesRead', $view['urls']['mark_read']);
	}

	private static function loadMailpitRuntime(): void
	{
		require_once dirname(__DIR__) . '/modules-common/Mailpit/classes/class.MailpitClientException.php';
		require_once dirname(__DIR__) . '/modules-common/Mailpit/classes/class.MailpitHttpResponse.php';
		require_once dirname(__DIR__) . '/modules-common/Mailpit/classes/class.MailpitClient.php';
		require_once dirname(__DIR__) . '/modules-common/Mailpit/classes/class.MailpitCatcherUrls.php';
		require_once dirname(__DIR__) . '/modules-common/Mailpit/classes/class.MailpitReadModel.php';
	}
}
