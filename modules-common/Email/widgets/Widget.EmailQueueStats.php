<?php

declare(strict_types=1);

class WidgetEmailQueueStats extends AbstractWidget
{
	public const string ID = 'email_queue_stats';

	/**
	 * @return array<string, string>
	 */
	public static function buildStrings(): array
	{
		return [
			'admin.email_queue.title' => self::translate('admin.email_queue.title', 'Email queue'),
			'admin.email_queue.subtitle' => self::translate('admin.email_queue.subtitle', 'Transactional delivery health at a glance.'),
			'admin.email_queue.pending' => self::translate('admin.email_queue.pending', 'Pending'),
			'admin.email_queue.retry_wait' => self::translate('admin.email_queue.retry_wait', 'Retry wait'),
			'admin.email_queue.dead_letter' => self::translate('admin.email_queue.dead_letter', 'Dead letter'),
			'admin.email_queue.sent_last_24h' => self::translate('admin.email_queue.sent_last_24h', 'Sent in last 24h'),
			'admin.email_queue.worker' => self::translate('admin.email_queue.worker', 'Worker'),
			'admin.email_queue.open_outbox' => self::translate('admin.email_queue.open_outbox', 'Open email outbox'),
			'admin.email_queue.status.running' => self::translate('admin.email_queue.status.running', 'Running'),
			'admin.email_queue.status.pausing' => self::translate('admin.email_queue.status.pausing', 'Pausing'),
			'admin.email_queue.status.paused' => self::translate('admin.email_queue.status.paused', 'Paused'),
			'admin.email_queue.status.stale' => self::translate('admin.email_queue.status.stale', 'Stale'),
			'admin.email_queue.status.never_seen' => self::translate('admin.email_queue.status.never_seen', 'Never seen'),
			'admin.email_queue.status.unavailable' => self::translate('admin.email_queue.status.unavailable', 'Unavailable'),
			'admin.email_queue.last_seen' => self::translate('admin.email_queue.last_seen', 'Last seen'),
			'admin.email_queue.last_processed' => self::translate('admin.email_queue.last_processed', 'Last processed'),
			'admin.email_queue.instances' => self::translate('admin.email_queue.instances', 'Worker instances'),
			'admin.email_queue.instance_state' => self::translate('admin.email_queue.instance_state', 'State'),
			'admin.email_queue.current_job' => self::translate('admin.email_queue.current_job', 'Current job'),
			'admin.email_queue.empty' => self::translate('admin.email_queue.empty', 'No email jobs are waiting right now.'),
		];
	}

	public static function getName(): string
	{
		return self::translate('widget.' . self::ID . '.name', 'Email queue stats');
	}

	public static function getDescription(): string
	{
		return self::translate('widget.' . self::ID . '.description', 'Shows transactional email queue health on the admin dashboard.');
	}

	public static function getListVisibility(): bool
	{
		return Roles::hasRole(RoleList::ROLE_EMAILS_ADMIN);
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		return $this->createComponentTree('emailQueueStats', [
			'summary' => EmailQueueAdminReadModel::getSummary(),
			'outbox_url' => widget_url('EmailOutbox'),
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
