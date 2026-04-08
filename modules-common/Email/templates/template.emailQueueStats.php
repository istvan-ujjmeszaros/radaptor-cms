<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$summary = is_array($this->props['summary'] ?? null) ? $this->props['summary'] : [];
$worker = is_array($summary['worker'] ?? null) ? $summary['worker'] : [];
$outbox_url = (string) ($this->props['outbox_url'] ?? '');
$worker_status = (string) ($worker['status'] ?? 'unavailable');
$worker_status_label_key = 'admin.email_queue.status.' . $worker_status;
$worker_status_label = (string) ($this->strings[$worker_status_label_key] ?? ucfirst(str_replace('_', ' ', $worker_status)));
$is_queue_empty = ((int) ($summary['pending_count'] ?? 0) + (int) ($summary['retry_wait_count'] ?? 0) + (int) ($summary['dead_letter_count'] ?? 0)) === 0;
$metrics = [
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

<section class="card card-hover email-admin-dashboard">
	<div class="card-body">
		<div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-4">
			<div>
				<h2 class="h4 mb-1"><?= e($this->strings['admin.email_queue.title'] ?? 'Email queue') ?></h2>
				<p class="text-muted mb-0"><?= e($this->strings['admin.email_queue.subtitle'] ?? '') ?></p>
			</div>
			<?php if ($outbox_url !== '') { ?>
				<a class="btn btn-outline-secondary btn-sm" href="<?= e($outbox_url) ?>">
					<?= e($this->strings['admin.email_queue.open_outbox'] ?? 'Open email outbox') ?>
				</a>
			<?php } ?>
		</div>

		<div class="row g-3">
			<?php foreach ($metrics as $metric) { ?>
				<div class="col-12 col-sm-6 col-xxl-3">
					<div class="card h-100 email-admin-dashboard__metric">
						<div class="card-body">
							<div class="email-admin-dashboard__metric-label"><?= e($metric['label']) ?></div>
							<div class="email-admin-dashboard__metric-value"><?= e($metric['value']) ?></div>
						</div>
					</div>
				</div>
			<?php } ?>
		</div>

		<div class="card email-admin-dashboard__worker mt-3">
			<div class="card-body">
				<div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
					<div>
						<div class="email-admin-dashboard__metric-label"><?= e($this->strings['admin.email_queue.worker'] ?? 'Worker') ?></div>
						<span class="badge rounded-pill email-admin-dashboard__status" data-status="<?= e($worker_status) ?>">
							<?= e($worker_status_label) ?>
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
			</div>
		</div>

		<?php if ($is_queue_empty) { ?>
			<div class="alert alert-info mb-0 mt-3">
				<?= e($this->strings['admin.email_queue.empty'] ?? 'No email jobs are waiting right now.') ?>
			</div>
		<?php } ?>
	</div>
</section>
