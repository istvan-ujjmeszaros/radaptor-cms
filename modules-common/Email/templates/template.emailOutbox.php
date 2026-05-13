<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$view = is_array($this->props['view'] ?? null) ? $this->props['view'] : [];
$summary = is_array($view['summary'] ?? null) ? $view['summary'] : [];
$worker = is_array($summary['worker'] ?? null) ? $summary['worker'] : [];
$workerInstances = is_array($worker['instances'] ?? null) ? $worker['instances'] : [];
$outboxRows = is_array($view['outbox_rows'] ?? null) ? $view['outbox_rows'] : [];
$recentFailures = is_array($view['recent_failures'] ?? null) ? $view['recent_failures'] : [];
$page = max(1, (int) ($view['page'] ?? 1));
$totalPages = max(1, (int) ($view['total_pages'] ?? 1));
$statusFilter = (string) ($view['status_filter'] ?? '');
$search = (string) ($view['search'] ?? '');

$buildPageUrl = static function (int $targetPage) use ($statusFilter, $search): string {
	return Url::modifyCurrentUrl([
		'page' => $targetPage,
		'status' => $statusFilter,
		'search' => $search,
	]);
};

$buildFilterUrl = static function (string $targetStatus) use ($search): string {
	return Url::modifyCurrentUrl([
		'page' => 1,
		'status' => $targetStatus,
		'search' => $search,
	]);
};

$workerStatus = (string) ($worker['status'] ?? 'unavailable');
$workerStatusLabel = (string) ($this->strings['admin.email_queue.status.' . $workerStatus] ?? ucfirst(str_replace('_', ' ', $workerStatus)));
$statusOptions = [
	'' => (string) ($this->strings['admin.email_outbox.status.all'] ?? 'All statuses'),
	'queued' => (string) ($this->strings['admin.email_outbox.status.queued'] ?? 'Queued'),
	'processing' => (string) ($this->strings['admin.email_outbox.status.processing'] ?? 'Processing'),
	'sent' => (string) ($this->strings['admin.email_outbox.status.sent'] ?? 'Sent'),
	'partial_failed' => (string) ($this->strings['admin.email_outbox.status.partial_failed'] ?? 'Partial failed'),
	'failed' => (string) ($this->strings['admin.email_outbox.status.failed'] ?? 'Failed'),
];
$summaryMetrics = [
	[
		'label' => (string) ($this->strings['admin.email_queue.pending'] ?? 'Pending'),
		'value' => (string) (int) ($summary['pending_count'] ?? 0),
	],
	[
		'label' => (string) ($this->strings['admin.email_queue.retry_wait'] ?? 'Retry wait'),
		'value' => (string) (int) ($summary['retry_wait_count'] ?? 0),
	],
	[
		'label' => (string) ($this->strings['admin.email_queue.dead_letter'] ?? 'Dead letter'),
		'value' => (string) (int) ($summary['dead_letter_count'] ?? 0),
	],
	[
		'label' => (string) ($this->strings['admin.email_queue.sent_last_24h'] ?? 'Sent in last 24h'),
		'value' => (string) (int) ($summary['sent_last_24h_count'] ?? 0),
	],
];
?>

<div class="email-outbox">
	<div class="subheader">
		<h1><?= e($this->strings['admin.email_outbox.title'] ?? 'Email outbox') ?></h1>
		<p><?= e($this->strings['admin.email_outbox.description'] ?? '') ?></p>
	</div>

	<section class="card card-hover email-admin-dashboard">
		<div class="card-body">
			<h2 class="h5 mb-3"><?= e($this->strings['admin.email_outbox.summary.title'] ?? 'Queue summary') ?></h2>

			<div class="row g-3">
				<?php foreach ($summaryMetrics as $metric) { ?>
					<div class="col-12 col-sm-6 col-xxl-3">
						<div class="card h-100 email-admin-dashboard__metric">
							<div class="card-body">
								<div class="email-admin-dashboard__metric-label"><?= e($metric['label']) ?></div>
								<div class="email-admin-dashboard__metric-value"><?= e($metric['value']) ?></div>
							</div>
						</div>
					</div>
				<?php } ?>

				<div class="col-12">
					<div class="card h-100 email-admin-dashboard__worker">
						<div class="card-body">
							<div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
								<div>
									<div class="email-admin-dashboard__metric-label"><?= e($this->strings['admin.email_queue.worker'] ?? 'Worker') ?></div>
									<span class="badge rounded-pill email-admin-dashboard__status" data-status="<?= e($workerStatus) ?>">
										<?= e($workerStatusLabel) ?>
									</span>
								</div>

								<div class="email-admin-dashboard__meta">
									<div>
										<div class="email-admin-dashboard__meta-label"><?= e($this->strings['admin.email_queue.last_seen'] ?? 'Last seen') ?></div>
										<div class="email-admin-dashboard__meta-value"><?= e((string) ($worker['last_seen_at'] ?? '—')) ?></div>
									</div>
									<div>
										<div class="email-admin-dashboard__meta-label"><?= e($this->strings['admin.email_queue.last_processed'] ?? 'Last processed') ?></div>
										<div class="email-admin-dashboard__meta-value"><?= e((string) ($worker['last_processed_at'] ?? '—')) ?></div>
									</div>
								</div>
							</div>

							<?php if ($workerInstances !== []) { ?>
								<div class="mt-3">
									<div class="email-admin-dashboard__metric-label mb-2"><?= e($this->strings['admin.email_queue.instances'] ?? 'Worker instances') ?></div>
									<div class="table-responsive">
										<table class="table table-sm align-middle mb-0">
											<thead>
												<tr>
													<th><?= e($this->strings['admin.email_queue.instance_state'] ?? 'State') ?></th>
													<th><?= e($this->strings['admin.email_queue.current_job'] ?? 'Current job') ?></th>
													<th><?= e($this->strings['admin.email_queue.last_seen'] ?? 'Last seen') ?></th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ($workerInstances as $workerInstance) { ?>
													<?php if (!is_array($workerInstance)) {
														continue;
													} ?>
													<tr>
														<td><?= e((string) ($workerInstance['effective_status'] ?? $workerInstance['state'] ?? 'unknown')) ?></td>
														<td><?= e((string) ($workerInstance['current_job_id'] ?? '—')) ?></td>
														<td><?= e((string) ($workerInstance['last_seen_at'] ?? '—')) ?></td>
													</tr>
												<?php } ?>
											</tbody>
										</table>
									</div>
								</div>
							<?php } ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section class="card card-hover">
		<div class="card-body">
			<h2 class="h5 mb-3"><?= e($this->strings['admin.email_outbox.filters.title'] ?? 'Filters') ?></h2>
			<form method="get" action="<?= e(Url::getCurrentUrl()) ?>" class="row g-3 align-items-end">
				<div class="col-12 col-lg-4">
				<label class="form-label" for="email-outbox-status"><?= e($this->strings['admin.email_outbox.filters.status'] ?? 'Status') ?></label>
				<select id="email-outbox-status" class="form-select" name="status">
					<?php foreach ($statusOptions as $statusValue => $statusLabel) { ?>
						<option value="<?= e($statusValue) ?>" <?= $statusValue === $statusFilter ? 'selected' : '' ?>>
							<?= e($statusLabel) ?>
						</option>
					<?php } ?>
				</select>
				</div>
				<div class="col-12 col-lg-5">
				<label class="form-label" for="email-outbox-search"><?= e($this->strings['admin.email_outbox.filters.search'] ?? 'Search') ?></label>
				<input id="email-outbox-search"
					   class="form-control"
					   type="text"
					   name="search"
					   value="<?= e($search) ?>"
					   placeholder="<?= e($this->strings['admin.email_outbox.filters.search_placeholder'] ?? '') ?>">
				</div>
				<div class="col-12 col-lg-3 d-flex gap-2">
				<button class="btn btn-primary" type="submit"><?= e($this->strings['admin.email_outbox.filters.apply'] ?? 'Apply filters') ?></button>
				<a class="btn btn-outline-secondary" href="<?= e($buildFilterUrl('')) ?>"><?= e($this->strings['admin.email_outbox.filters.reset'] ?? 'Reset') ?></a>
				</div>
			</form>
		</div>
	</section>

	<section class="card card-hover">
		<div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap">
			<h2 class="h5 mb-0"><?= e($this->strings['admin.email_outbox.list.title'] ?? 'Recent outbox entries') ?></h2>
			<div class="small text-muted">
				<?= e($this->strings['admin.email_outbox.pagination.page'] ?? 'Page') ?> <?= e((string) $page) ?> / <?= e((string) $totalPages) ?>
			</div>
		</div>

		<?php if ($outboxRows === []) { ?>
			<div class="card-body">
				<p class="mb-0"><?= e($this->strings['admin.email_outbox.none'] ?? 'No emails match the current filter.') ?></p>
			</div>
		<?php } else { ?>
			<div class="table-responsive">
				<table class="table table-hover align-middle mb-0 email-outbox__table">
					<thead>
					<tr>
						<th><?= e($this->strings['admin.email_outbox.col.id'] ?? 'ID') ?></th>
						<th><?= e($this->strings['admin.email_outbox.col.subject'] ?? 'Subject') ?></th>
						<th><?= e($this->strings['admin.email_outbox.col.status'] ?? 'Status') ?></th>
						<th><?= e($this->strings['admin.email_outbox.col.recipients'] ?? 'Recipients') ?></th>
						<th><?= e($this->strings['admin.email_outbox.col.created_at'] ?? 'Created') ?></th>
						<th><?= e($this->strings['admin.email_outbox.col.sent_at'] ?? 'Sent') ?></th>
						<th><?= e($this->strings['admin.email_outbox.col.last_error'] ?? 'Last error') ?></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ($outboxRows as $row) { ?>
						<tr>
							<td>#<?= e((string) ($row['outbox_id'] ?? '')) ?></td>
							<td>
								<div><strong><?= e((string) ($row['subject'] ?? '')) ?></strong></div>
								<div class="small text-muted email-outbox__message-meta"><?= e((string) ($row['message_uid'] ?? '')) ?></div>
							</td>
							<td>
								<?php $rowStatus = (string) ($row['status'] ?? 'queued'); ?>
								<span class="badge rounded-pill email-outbox__status" data-status="<?= e($rowStatus) ?>">
									<?= e($statusOptions[$rowStatus] ?? $rowStatus) ?>
								</span>
							</td>
								<td>
									<?= e((string) ((int) ($row['recipient_total'] ?? 0))) ?>
									<span class="small text-muted email-outbox__message-meta">
										<?= e(t('admin.email_outbox.recipients_delivery_counts', [
											'sent' => (string) ((int) ($row['recipient_sent'] ?? 0)),
											'failed' => (string) ((int) ($row['recipient_failed'] ?? 0)),
										])) ?>
									</span>
								</td>
							<td><?= e((string) ($row['created_at'] ?? '')) ?></td>
							<td><?= e((string) ($row['sent_at'] ?? '—')) ?></td>
							<td><?= e(trim((string) ($row['last_error_message'] ?? '')) ?: '—') ?></td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
		<?php } ?>

		<?php if ($totalPages > 1) { ?>
			<div class="card-footer d-flex justify-content-between">
					<a class="btn btn-outline-secondary btn-sm <?= $page <= 1 ? 'disabled' : '' ?>"
					   href="<?= e($page <= 1 ? '#' : $buildPageUrl($page - 1)) ?>">
						<?= e($this->strings['admin.email_outbox.pagination.previous'] ?? t('admin.email_outbox.pagination.previous')) ?>
					</a>
					<a class="btn btn-outline-secondary btn-sm <?= $page >= $totalPages ? 'disabled' : '' ?>"
					   href="<?= e($page >= $totalPages ? '#' : $buildPageUrl($page + 1)) ?>">
						<?= e($this->strings['admin.email_outbox.pagination.next'] ?? t('admin.email_outbox.pagination.next')) ?>
					</a>
			</div>
		<?php } ?>
	</section>

	<section class="card card-hover">
		<div class="card-header">
			<h2 class="h5 mb-0"><?= e($this->strings['admin.email_outbox.failures.title'] ?? 'Recent dead letters') ?></h2>
		</div>
		<?php if ($recentFailures === []) { ?>
			<div class="card-body">
				<p class="mb-0"><?= e($this->strings['admin.email_outbox.none'] ?? 'No emails match the current filter.') ?></p>
			</div>
		<?php } else { ?>
			<div class="table-responsive">
				<table class="table table-hover align-middle mb-0 email-outbox__table">
					<thead>
					<tr>
						<th><?= e($this->strings['admin.email_outbox.col.id'] ?? 'ID') ?></th>
						<th><?= e($this->strings['admin.email_outbox.col.job'] ?? t('admin.email_outbox.col.job')) ?></th>
						<th><?= e($this->strings['admin.email_outbox.col.status'] ?? 'Status') ?></th>
						<th><?= e($this->strings['admin.email_outbox.col.last_error'] ?? 'Last error') ?></th>
						<th><?= e($this->strings['admin.email_outbox.col.dead_lettered'] ?? t('admin.email_outbox.col.dead_lettered')) ?></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ($recentFailures as $failure) { ?>
						<tr>
							<td>#<?= e((string) ($failure['dead_letter_id'] ?? '')) ?></td>
							<td>
								<div><strong><?= e((string) ($failure['job_type'] ?? '')) ?></strong></div>
								<div class="small text-muted email-outbox__message-meta"><?= e((string) ($failure['source_table'] ?? '')) ?></div>
							</td>
							<td><span class="badge rounded-pill email-outbox__status" data-status="failed"><?= e($this->strings['admin.email_queue.dead_letter'] ?? t('admin.email_queue.dead_letter')) ?></span></td>
							<td>
								<div><strong><?= e((string) ($failure['error_code'] ?? '')) ?></strong></div>
								<div class="small text-muted email-outbox__message-meta"><?= e((string) ($failure['error_message'] ?? '')) ?></div>
							</td>
							<td><?= e((string) ($failure['dead_lettered_at'] ?? '')) ?></td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
		<?php } ?>
	</section>
</div>
