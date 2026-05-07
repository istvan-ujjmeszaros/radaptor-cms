<?php

declare(strict_types=1);

class WidgetMailpit extends AbstractWidget
{
	public const string ID = 'mailpit';

	private const array ROUTES = [
		'' => 'mailpit.inbox',
		'search' => [
			'component' => 'mailpit.inbox',
			'defaults' => [
				'mode' => 'search',
			],
		],
		'messages/{id}' => [
			'component' => 'mailpit.messageView',
			'defaults' => [
				'tab' => 'html',
			],
		],
		'messages/{id}/{tab}' => [
			'component' => 'mailpit.messageView',
			'where' => [
				'tab' => 'html|html-source|text|headers|raw|html-check|link-check',
			],
		],
	];

	public static function getName(): string
	{
		return t('widget.' . self::ID . '.name');
	}

	public static function getDescription(): string
	{
		return t('widget.' . self::ID . '.description');
	}

	public static function getListVisibility(): bool
	{
		return MailpitAccessPolicy::isAllowed();
	}

	public static function getDefaultPathForCreation(): array
	{
		return [
			'path' => '/admin/dev/mailpit/',
			'resource_name' => 'index.html',
			'layout' => 'admin_default',
		];
	}

	public static function isCatcher(): bool
	{
		return true;
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		$page_id = $tree_build_context instanceof iWebpageComposer
			? (int) $tree_build_context->getPageId()
			: (int) (WidgetConnection::getOwnerWebpageId($connection->getConnectionId()) ?? 0);

		if ($page_id <= 0) {
			return $this->createComponentTree('mailpit.unavailable', [
				'message' => t('mailpit.page_not_resolved'),
			], strings: self::buildStrings());
		}

		$route = CatcherRouteMap::match(CatcherRouteMap::subpathForPage($page_id), self::ROUTES);

		if ($route === null) {
			return $this->createComponentTree('mailpit.notFound', [
				'page_id' => $page_id,
				'connection_id' => $connection->getConnectionId(),
				'inbox_url' => MailpitCatcherUrls::page($page_id),
				'inbox_fragment_url' => MailpitCatcherUrls::fragment($page_id, $connection->getConnectionId()),
			], strings: self::buildStrings());
		}

		try {
			$props = match ($route['component']) {
				'mailpit.messageView' => MailpitReadModel::messageView($page_id, $connection->getConnectionId(), $route['params']),
				default => MailpitReadModel::inbox($page_id, $connection->getConnectionId(), $route['params']),
			};

			return $this->createComponentTree($route['component'], $props, strings: self::buildStrings());
		} catch (MailpitClientException $exception) {
			return $this->createComponentTree('mailpit.unavailable', [
				'message' => $exception->getMessage(),
				'status_code' => $exception->statusCode,
			], strings: self::buildStrings());
		} catch (Throwable $exception) {
			return $this->createComponentTree('mailpit.unavailable', [
				'message' => $exception->getMessage(),
			], strings: self::buildStrings());
		}
	}

	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return MailpitAccessPolicy::isAllowed();
	}

	public function getAccessDeniedMessage(): string
	{
		return t('mailpit.access_denied');
	}

	/**
	 * @return array<string, string>
	 */
	public static function buildStrings(): array
	{
		return [
			'mailpit.title' => t('mailpit.title'),
			'mailpit.subtitle' => t('mailpit.subtitle'),
			'mailpit.inbox' => t('mailpit.inbox'),
			'mailpit.search' => t('mailpit.search'),
			'mailpit.refresh' => t('mailpit.refresh'),
			'mailpit.back_to_inbox' => t('mailpit.back_to_inbox'),
			'mailpit.messages' => t('mailpit.messages'),
			'mailpit.unread' => t('mailpit.unread'),
			'mailpit.empty' => t('mailpit.empty'),
			'mailpit.unavailable' => t('mailpit.unavailable'),
			'mailpit.not_found' => t('mailpit.not_found'),
			'mailpit.col.subject' => t('mailpit.col.subject'),
			'mailpit.col.from' => t('mailpit.col.from'),
			'mailpit.col.to' => t('mailpit.col.to'),
			'mailpit.col.received' => t('mailpit.col.received'),
			'mailpit.col.size' => t('mailpit.col.size'),
			'mailpit.pagination.previous' => t('mailpit.pagination.previous'),
			'mailpit.pagination.next' => t('mailpit.pagination.next'),
			'mailpit.no_subject' => t('mailpit.no_subject'),
			'mailpit.delete' => t('mailpit.delete'),
			'mailpit.meta.from' => t('mailpit.meta.from'),
			'mailpit.meta.to' => t('mailpit.meta.to'),
			'mailpit.meta.cc' => t('mailpit.meta.cc'),
			'mailpit.meta.bcc' => t('mailpit.meta.bcc'),
			'mailpit.meta.date' => t('mailpit.meta.date'),
			'mailpit.meta.size' => t('mailpit.meta.size'),
			'mailpit.html_empty' => t('mailpit.html_empty'),
			'mailpit.html_preview_title' => t('mailpit.html_preview_title'),
			'mailpit.check.supported' => t('mailpit.check.supported'),
			'mailpit.check.partial' => t('mailpit.check.partial'),
			'mailpit.check.unsupported' => t('mailpit.check.unsupported'),
			'mailpit.check.tests' => t('mailpit.check.tests'),
			'mailpit.check.no_warnings' => t('mailpit.check.no_warnings'),
			'mailpit.warning_fallback' => t('mailpit.warning_fallback'),
			'mailpit.links.status' => t('mailpit.links.status'),
			'mailpit.links.none' => t('mailpit.links.none'),
			'mailpit.attachments' => t('mailpit.attachments'),
			'mailpit.inline_attachments' => t('mailpit.inline_attachments'),
			'mailpit.attachment_fallback' => t('mailpit.attachment_fallback'),
			'mailpit.tab_error' => t('mailpit.tab_error'),
		];
	}
}
