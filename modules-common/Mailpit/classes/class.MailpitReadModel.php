<?php

declare(strict_types=1);

final class MailpitReadModel
{
	public const array TABS = [
		'html' => 'mailpit.tab.html',
		'html-source' => 'mailpit.tab.html_source',
		'text' => 'mailpit.tab.text',
		'headers' => 'mailpit.tab.headers',
		'raw' => 'mailpit.tab.raw',
		'html-check' => 'mailpit.tab.html_check',
		'link-check' => 'mailpit.tab.link_check',
	];

	private function __construct()
	{
	}

	/**
	 * @param array<string, scalar|null> $route_params
	 * @return array<string, mixed>
	 */
	public static function inbox(int $page_id, int $connection_id, array $route_params, ?MailpitClient $client = null): array
	{
		$client ??= MailpitClient::fromConfig();
		$query = trim((string) Request::_GET('q', ''));
		$start = max(0, (int) Request::_GET('start', 0));
		$limit = min(100, max(10, (int) Request::_GET('limit', 25)));
		$mode = $query !== '' ? 'search' : (string) ($route_params['mode'] ?? 'inbox');
		$summary = $query !== ''
			? $client->searchMessages($query, $start, $limit, date_default_timezone_get())
			: $client->listMessages($start, $limit);
		$messages = is_array($summary['messages'] ?? null) ? $summary['messages'] : [];
		$messages = array_map([self::class, 'normalizeMessageSummary'], $messages);

		foreach ($messages as &$message) {
			$id = (string) ($message['ID'] ?? '');
			$subpath = 'messages/' . rawurlencode($id);
			$message['Url'] = $id !== '' ? MailpitCatcherUrls::page($page_id, $subpath) : '';
			$message['FragmentUrl'] = $id !== '' ? MailpitCatcherUrls::fragment($page_id, $connection_id, $subpath) : '';
		}
		unset($message);

		$total = (int) ($summary['messages_count'] ?? $summary['total'] ?? count($messages));
		$next_start = $start + $limit;
		$previous_start = max(0, $start - $limit);

		return [
			'page_id' => $page_id,
			'connection_id' => $connection_id,
			'mode' => $mode,
			'query' => $query,
			'start' => $start,
			'limit' => $limit,
			'messages' => $messages,
			'summary' => [
				'total' => (int) ($summary['total'] ?? $total),
				'unread' => (int) ($summary['unread'] ?? 0),
				'messages_count' => $total,
				'messages_unread' => (int) ($summary['messages_unread'] ?? 0),
				'tags' => is_array($summary['tags'] ?? null) ? $summary['tags'] : [],
			],
			'urls' => [
				'inbox' => MailpitCatcherUrls::page($page_id),
				'inbox_fragment' => MailpitCatcherUrls::fragment($page_id, $connection_id),
				'search' => MailpitCatcherUrls::page($page_id, 'search'),
				'search_fragment' => MailpitCatcherUrls::fragment($page_id, $connection_id, 'search'),
				'refresh' => MailpitCatcherUrls::page($page_id, $query !== '' ? 'search' : '', self::pageQuery($query, $start, $limit)),
				'refresh_fragment' => MailpitCatcherUrls::fragment($page_id, $connection_id, $query !== '' ? 'search' : '', self::pageQuery($query, $start, $limit)),
				'previous' => $start > 0 ? MailpitCatcherUrls::page($page_id, $query !== '' ? 'search' : '', self::pageQuery($query, $previous_start, $limit)) : '',
				'previous_fragment' => $start > 0 ? MailpitCatcherUrls::fragment($page_id, $connection_id, $query !== '' ? 'search' : '', self::pageQuery($query, $previous_start, $limit)) : '',
				'next' => $next_start < $total ? MailpitCatcherUrls::page($page_id, $query !== '' ? 'search' : '', self::pageQuery($query, $next_start, $limit)) : '',
				'next_fragment' => $next_start < $total ? MailpitCatcherUrls::fragment($page_id, $connection_id, $query !== '' ? 'search' : '', self::pageQuery($query, $next_start, $limit)) : '',
			],
		];
	}

	/**
	 * @param array<string, scalar|null> $route_params
	 * @return array<string, mixed>
	 */
	public static function messageView(int $page_id, int $connection_id, array $route_params, ?MailpitClient $client = null): array
	{
		$client ??= MailpitClient::fromConfig();
		$id = trim((string) ($route_params['id'] ?? ''));
		$tab = trim((string) ($route_params['tab'] ?? 'html'));

		if (!isset(self::TABS[$tab])) {
			$tab = 'html';
		}

		$message = $client->getMessage($id);
		$message = self::normalizeMessage($message);

		return [
			'page_id' => $page_id,
			'connection_id' => $connection_id,
			'id' => $id,
			'tab' => $tab,
			'tabs' => self::tabs($page_id, $connection_id, $id, $tab),
			'message' => $message,
			'tab_content' => self::tabContent($client, $message, $tab, $page_id),
			'urls' => [
				'inbox' => MailpitCatcherUrls::page($page_id),
				'inbox_fragment' => MailpitCatcherUrls::fragment($page_id, $connection_id),
				'delete' => MailpitCatcherUrls::event('messagesDelete'),
				'mark_read' => MailpitCatcherUrls::event('messagesRead'),
			],
		];
	}

	/**
	 * @param array<string, mixed> $message
	 * @return array<string, mixed>
	 */
	private static function tabContent(MailpitClient $client, array $message, string $tab, int $page_id): array
	{
		$id = (string) ($message['ID'] ?? '');

		try {
			return match ($tab) {
				'html' => [
					'kind' => 'html',
					'html' => self::htmlWithInlineAttachmentUrls((string) ($message['HTML'] ?? ''), $message),
				],
				'html-source' => [
					'kind' => 'source',
					'source' => (string) ($message['HTML'] ?? ''),
				],
				'text' => [
					'kind' => 'text',
					'text' => (string) ($message['Text'] ?? ''),
				],
				'headers' => [
					'kind' => 'headers',
					'headers' => $client->getHeaders($id),
				],
				'raw' => [
					'kind' => 'raw',
					'raw' => $client->getRaw($id),
				],
				'html-check' => [
					'kind' => 'html-check',
					'result' => $client->getHtmlCheck($id),
				],
				'link-check' => [
					'kind' => 'link-check',
					'result' => $client->getLinkCheck($id, filter_var(Request::_GET('follow', 'false'), FILTER_VALIDATE_BOOLEAN)),
				],
				default => [
					'kind' => 'text',
					'text' => '',
				],
			};
		} catch (MailpitClientException $exception) {
			return [
				'kind' => 'error',
				'message' => $exception->getMessage(),
				'status_code' => $exception->statusCode,
			];
		}
	}

	/**
	 * @return list<array{key: string, label: string, url: string, fragment_url: string, active: bool}>
	 */
	private static function tabs(int $page_id, int $connection_id, string $id, string $active_tab): array
	{
		$tabs = [];

		foreach (self::TABS as $key => $label_key) {
			$subpath = $key === 'html' ? 'messages/' . rawurlencode($id) : 'messages/' . rawurlencode($id) . '/' . $key;
			$tabs[] = [
				'key' => $key,
				'label' => t($label_key),
				'url' => MailpitCatcherUrls::page($page_id, $subpath),
				'fragment_url' => MailpitCatcherUrls::fragment($page_id, $connection_id, $subpath),
				'active' => $key === $active_tab,
			];
		}

		return $tabs;
	}

	/**
	 * @param array<string, mixed> $message
	 * @return array<string, mixed>
	 */
	private static function normalizeMessage(array $message): array
	{
		$message['FromFormatted'] = self::formatAddress($message['From'] ?? null);
		$message['ToFormatted'] = self::formatAddressList($message['To'] ?? []);
		$message['CcFormatted'] = self::formatAddressList($message['Cc'] ?? []);
		$message['BccFormatted'] = self::formatAddressList($message['Bcc'] ?? []);
		$message['ReplyToFormatted'] = self::formatAddressList($message['ReplyTo'] ?? []);
		$message['DateFormatted'] = self::formatDate((string) ($message['Date'] ?? ''));
		$message['SizeFormatted'] = self::formatBytes((int) ($message['Size'] ?? 0));
		$message['Attachments'] = is_array($message['Attachments'] ?? null) ? $message['Attachments'] : [];
		$message['Inline'] = is_array($message['Inline'] ?? null) ? $message['Inline'] : [];
		$message['Tags'] = is_array($message['Tags'] ?? null) ? $message['Tags'] : [];

		return $message;
	}

	/**
	 * @param array<string, mixed> $message
	 * @return array<string, mixed>
	 */
	private static function normalizeMessageSummary(array $message): array
	{
		$message['FromFormatted'] = self::formatAddress($message['From'] ?? null);
		$message['ToFormatted'] = self::formatAddressList($message['To'] ?? []);
		$message['CreatedFormatted'] = self::formatDate((string) ($message['Created'] ?? ''));
		$message['SizeFormatted'] = self::formatBytes((int) ($message['Size'] ?? 0));
		$message['Tags'] = is_array($message['Tags'] ?? null) ? $message['Tags'] : [];
		$message['Url'] = '';
		$message['FragmentUrl'] = '';

		return $message;
	}

	/**
	 * @param array<string, mixed> $message
	 */
	private static function htmlWithInlineAttachmentUrls(string $html, array $message): string
	{
		$id = (string) ($message['ID'] ?? '');
		$inline = is_array($message['Inline'] ?? null) ? $message['Inline'] : [];

		foreach ($inline as $attachment) {
			if (!is_array($attachment)) {
				continue;
			}

			$content_id = trim((string) ($attachment['ContentID'] ?? ''), '<>');
			$part_id = trim((string) ($attachment['PartID'] ?? ''));

			if ($content_id === '' || $part_id === '') {
				continue;
			}

			$url = MailpitCatcherUrls::event('attachment', [
				'id' => $id,
				'part' => $part_id,
			]);

			$html = str_replace('cid:' . $content_id, $url, $html);
			$html = str_replace('cid:' . rawurlencode($content_id), $url, $html);
		}

		return $html;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function pageQuery(string $query, int $start, int $limit): array
	{
		$params = [
			'start' => $start,
			'limit' => $limit,
		];

		if ($query !== '') {
			$params['q'] = $query;
		}

		return $params;
	}

	private static function formatAddress(mixed $address): string
	{
		if (!is_array($address)) {
			return '';
		}

		$email = (string) ($address['Address'] ?? $address['Email'] ?? '');
		$name = trim((string) ($address['Name'] ?? ''));

		if ($name !== '' && $email !== '') {
			return $name . ' <' . $email . '>';
		}

		return $email !== '' ? $email : $name;
	}

	private static function formatAddressList(mixed $addresses): string
	{
		if (!is_array($addresses)) {
			return '';
		}

		if (isset($addresses['Address']) || isset($addresses['Email'])) {
			return self::formatAddress($addresses);
		}

		$formatted = [];

		foreach ($addresses as $address) {
			$value = self::formatAddress($address);

			if ($value !== '') {
				$formatted[] = $value;
			}
		}

		return implode(', ', $formatted);
	}

	private static function formatDate(string $date): string
	{
		$date = trim($date);

		if ($date === '') {
			return '';
		}

		$date = preg_replace('/(\.\d{6})\d+(Z|[+\-]\d\d:\d\d)$/', '$1$2', $date) ?? $date;

		try {
			return (new DateTimeImmutable($date))
				->setTimezone(new DateTimeZone(date_default_timezone_get()))
				->format('Y-m-d H:i:s');
		} catch (Throwable) {
			return $date;
		}
	}

	private static function formatBytes(int $bytes): string
	{
		if ($bytes < 1024) {
			return $bytes . ' B';
		}

		if ($bytes < 1024 * 1024) {
			return number_format($bytes / 1024, 1) . ' KB';
		}

		return number_format($bytes / 1024 / 1024, 1) . ' MB';
	}
}
