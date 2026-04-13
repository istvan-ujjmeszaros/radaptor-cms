<?php

declare(strict_types=1);

class WidgetEmailOutbox extends AbstractWidget
{
	public const string ID = 'email_outbox';

	/**
	 * @return array<string, string>
	 */
	public static function buildStrings(): array
	{
		return [
			'admin.email_outbox.title' => self::translate('admin.email_outbox.title', 'Email outbox'),
			'admin.email_outbox.description' => self::translate('admin.email_outbox.description', 'Read-only transactional email delivery history.'),
			'admin.email_outbox.filters.title' => self::translate('admin.email_outbox.filters.title', 'Filters'),
			'admin.email_outbox.filters.status' => self::translate('admin.email_outbox.filters.status', 'Status'),
			'admin.email_outbox.filters.search' => self::translate('admin.email_outbox.filters.search', 'Search'),
			'admin.email_outbox.filters.search_placeholder' => self::translate('admin.email_outbox.filters.search_placeholder', 'Subject, message UID, or recipient email'),
			'admin.email_outbox.filters.apply' => self::translate('admin.email_outbox.filters.apply', 'Apply filters'),
			'admin.email_outbox.filters.reset' => self::translate('admin.email_outbox.filters.reset', 'Reset'),
			'admin.email_outbox.summary.title' => self::translate('admin.email_outbox.summary.title', 'Queue summary'),
			'admin.email_outbox.list.title' => self::translate('admin.email_outbox.list.title', 'Recent outbox entries'),
			'admin.email_outbox.failures.title' => self::translate('admin.email_outbox.failures.title', 'Recent dead letters'),
			'admin.email_outbox.none' => self::translate('admin.email_outbox.none', 'No emails match the current filter.'),
			'admin.email_outbox.col.id' => self::translate('admin.email_outbox.col.id', 'ID'),
			'admin.email_outbox.col.subject' => self::translate('admin.email_outbox.col.subject', 'Subject'),
			'admin.email_outbox.col.status' => self::translate('admin.email_outbox.col.status', 'Status'),
			'admin.email_outbox.col.recipients' => self::translate('admin.email_outbox.col.recipients', 'Recipients'),
			'admin.email_outbox.col.created_at' => self::translate('admin.email_outbox.col.created_at', 'Created'),
			'admin.email_outbox.col.sent_at' => self::translate('admin.email_outbox.col.sent_at', 'Sent'),
			'admin.email_outbox.col.last_error' => self::translate('admin.email_outbox.col.last_error', 'Last error'),
			'admin.email_outbox.pagination.page' => self::translate('admin.email_outbox.pagination.page', 'Page'),
			'admin.email_outbox.status.all' => self::translate('admin.email_outbox.status.all', 'All statuses'),
			'admin.email_outbox.status.queued' => self::translate('admin.email_outbox.status.queued', 'Queued'),
			'admin.email_outbox.status.processing' => self::translate('admin.email_outbox.status.processing', 'Processing'),
			'admin.email_outbox.status.sent' => self::translate('admin.email_outbox.status.sent', 'Sent'),
			'admin.email_outbox.status.partial_failed' => self::translate('admin.email_outbox.status.partial_failed', 'Partial failed'),
			'admin.email_outbox.status.failed' => self::translate('admin.email_outbox.status.failed', 'Failed'),
			'admin.email_queue.pending' => self::translate('admin.email_queue.pending', 'Pending'),
			'admin.email_queue.retry_wait' => self::translate('admin.email_queue.retry_wait', 'Retry wait'),
			'admin.email_queue.dead_letter' => self::translate('admin.email_queue.dead_letter', 'Dead letter'),
			'admin.email_queue.sent_last_24h' => self::translate('admin.email_queue.sent_last_24h', 'Sent in last 24h'),
			'admin.email_queue.worker' => self::translate('admin.email_queue.worker', 'Worker'),
			'admin.email_queue.status.running' => self::translate('admin.email_queue.status.running', 'Running'),
			'admin.email_queue.status.stale' => self::translate('admin.email_queue.status.stale', 'Stale'),
			'admin.email_queue.status.never_seen' => self::translate('admin.email_queue.status.never_seen', 'Never seen'),
			'admin.email_queue.status.unavailable' => self::translate('admin.email_queue.status.unavailable', 'Unavailable'),
			'admin.email_queue.last_seen' => self::translate('admin.email_queue.last_seen', 'Last seen'),
			'admin.email_queue.last_processed' => self::translate('admin.email_queue.last_processed', 'Last processed'),
		];
	}

	public static function getName(): string
	{
		return self::translate('widget.' . self::ID . '.name', 'Email outbox');
	}

	public static function getDescription(): string
	{
		return self::translate('widget.' . self::ID . '.description', 'Shows the transactional email outbox and recent failures.');
	}

	public static function getListVisibility(): bool
	{
		return Roles::hasRole(RoleList::ROLE_EMAILS_ADMIN);
	}

	public static function getDefaultPathForCreation(): array
	{
		return [
			'path' => '/admin/email-outbox/',
			'resource_name' => 'index.html',
			'layout' => 'admin_default',
		];
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		$status_filter = trim((string) Request::_GET('status', ''));
		$search = trim((string) Request::_GET('search', ''));
		$page = max(1, (int) Request::_GET('page', 1));

		return $this->createComponentTree('emailOutbox', [
			'view' => EmailQueueAdminReadModel::getOutboxViewData($status_filter, $search, $page),
			'current_url' => Url::getCurrentUrl(),
		], strings: self::buildStrings());
	}

	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return Roles::hasRole(RoleList::ROLE_EMAILS_ADMIN);
	}

	private static function translate(string $key, string $fallback): string
	{
		$translated = t($key);

		return $translated === $key ? $fallback : $translated;
	}
}
