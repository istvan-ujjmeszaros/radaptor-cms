<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!function_exists('t')) {
	function t(string $key): string
	{
		return $key;
	}
}

if (!class_exists('CatcherRouteMap', false)) {
	final class CatcherRouteMap
	{
		/**
		 * @param array<string, mixed> $query
		 */
		public static function urlForPage(int $page_id, string $subpath = '', array $query = []): string
		{
			$url = '/page-' . $page_id . '/' . ltrim($subpath, '/');

			return $query === [] ? $url : $url . '?' . http_build_query($query);
		}
	}
}

if (!class_exists('Url', false)) {
	final class Url
	{
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
	}
}

require_once dirname(__DIR__) . '/modules-common/Mailpit/classes/class.MailpitClientException.php';
require_once dirname(__DIR__) . '/modules-common/Mailpit/classes/class.MailpitHttpResponse.php';
require_once dirname(__DIR__) . '/modules-common/Mailpit/classes/class.MailpitClient.php';
require_once dirname(__DIR__) . '/modules-common/Mailpit/classes/class.MailpitCatcherUrls.php';
require_once dirname(__DIR__) . '/modules-common/Mailpit/classes/class.MailpitReadModel.php';

final class MailpitReadModelTest extends TestCase
{
	public function testMessageViewUsesResolvableMutationEventNames(): void
	{
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

		$view = MailpitReadModel::messageView(10, 20, ['id' => 'message-1'], $client);

		$this->assertSame('/?context=mailpit&event=messagesDelete', $view['urls']['delete']);
		$this->assertSame('/?context=mailpit&event=messagesRead', $view['urls']['mark_read']);
	}
}
