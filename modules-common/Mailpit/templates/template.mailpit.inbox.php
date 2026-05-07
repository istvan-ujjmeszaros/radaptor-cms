<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$pageId = (int) ($this->props['page_id'] ?? 0);
$connectionId = (int) ($this->props['connection_id'] ?? 0);
$messages = is_array($this->props['messages'] ?? null) ? $this->props['messages'] : [];
$summary = is_array($this->props['summary'] ?? null) ? $this->props['summary'] : [];
$urls = is_array($this->props['urls'] ?? null) ? $this->props['urls'] : [];
$query = (string) ($this->props['query'] ?? '');
$start = (int) ($this->props['start'] ?? 0);
$limit = (int) ($this->props['limit'] ?? 25);
$total = (int) ($summary['messages_count'] ?? 0);
$rangeStart = $total > 0 ? $start + 1 : 0;
$rangeEnd = $total > 0 ? min($start + $limit, max($total, count($messages))) : 0;

$hxLinkAttrs = static function (string $href, string $fragmentHref): string {
	if ($fragmentHref === '') {
		return 'href="' . e($href) . '"';
	}

	return 'href="' . e($href) . '" hx-get="' . e($fragmentHref) . '" hx-swap="none" hx-push-url="' . e($href) . '"';
};
?>

<div class="mailpit-catcher">
	<div class="subheader mailpit-catcher__header">
		<div>
			<h1><?= e($this->strings['mailpit.title'] ?? t('mailpit.title')) ?></h1>
			<p><?= e($this->strings['mailpit.subtitle'] ?? t('mailpit.subtitle')) ?></p>
		</div>
		<div class="mailpit-catcher__header-actions">
			<a class="btn btn-outline-secondary btn-sm"
			   <?= $hxLinkAttrs((string) ($urls['refresh'] ?? '#'), (string) ($urls['refresh_fragment'] ?? '')) ?>>
				<i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
				<span><?= e($this->strings['mailpit.refresh'] ?? t('mailpit.refresh')) ?></span>
			</a>
		</div>
	</div>

	<section class="mailpit-catcher__toolbar">
		<div class="mailpit-catcher__metric">
			<span class="mailpit-catcher__metric-value"><?= e((string) ($summary['total'] ?? 0)) ?></span>
			<span class="mailpit-catcher__metric-label"><?= e($this->strings['mailpit.messages'] ?? t('mailpit.messages')) ?></span>
		</div>
		<div class="mailpit-catcher__metric">
			<span class="mailpit-catcher__metric-value"><?= e((string) ($summary['unread'] ?? 0)) ?></span>
			<span class="mailpit-catcher__metric-label"><?= e($this->strings['mailpit.unread'] ?? t('mailpit.unread')) ?></span>
		</div>

		<form class="mailpit-catcher__search" method="get" action="<?= e((string) ($urls['search'] ?? '')) ?>">
			<input type="hidden" name="limit" value="<?= e((string) $limit) ?>">
			<label class="visually-hidden" for="mailpit-search"><?= e($this->strings['mailpit.search'] ?? t('mailpit.search')) ?></label>
			<div class="input-group input-group-sm">
				<span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
				<input id="mailpit-search"
					   class="form-control"
					   type="search"
					   name="q"
					   value="<?= e($query) ?>"
					   placeholder="<?= e($this->strings['mailpit.search'] ?? t('mailpit.search')) ?>">
				<button class="btn btn-primary" type="submit">
					<i class="bi bi-search" aria-hidden="true"></i>
					<span><?= e($this->strings['mailpit.search'] ?? t('mailpit.search')) ?></span>
				</button>
			</div>
		</form>
	</section>

	<section class="card card-hover mailpit-catcher__list">
		<?php if ($messages === []) { ?>
			<div class="card-body">
				<p class="mb-0 text-muted"><?= e($this->strings['mailpit.empty'] ?? t('mailpit.empty')) ?></p>
			</div>
		<?php } else { ?>
			<div class="table-responsive">
				<table class="table table-hover align-middle mb-0 mailpit-catcher__table">
					<thead>
					<tr>
						<th scope="col"><?= e($this->strings['mailpit.col.subject'] ?? t('mailpit.col.subject')) ?></th>
						<th scope="col"><?= e($this->strings['mailpit.col.from'] ?? t('mailpit.col.from')) ?></th>
						<th scope="col"><?= e($this->strings['mailpit.col.to'] ?? t('mailpit.col.to')) ?></th>
						<th scope="col"><?= e($this->strings['mailpit.col.received'] ?? t('mailpit.col.received')) ?></th>
						<th scope="col"><?= e($this->strings['mailpit.col.size'] ?? t('mailpit.col.size')) ?></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ($messages as $message) { ?>
						<?php
						$message = is_array($message) ? $message : [];
						$subject = trim((string) ($message['Subject'] ?? '')) ?: ($this->strings['mailpit.no_subject'] ?? t('mailpit.no_subject'));
						$read = (bool) ($message['Read'] ?? false);
						$tags = is_array($message['Tags'] ?? null) ? $message['Tags'] : [];
						$attachmentCount = (int) ($message['Attachments'] ?? 0);
						?>
						<tr class="<?= $read ? '' : 'mailpit-catcher__row-unread' ?>">
							<td class="mailpit-catcher__subject">
								<a class="mailpit-catcher__message-link"
								   <?= $hxLinkAttrs((string) ($message['Url'] ?? '#'), (string) ($message['FragmentUrl'] ?? '')) ?>>
									<?php if (!$read) { ?>
										<span class="mailpit-catcher__unread-dot" aria-hidden="true"></span>
									<?php } ?>
									<span><?= e($subject) ?></span>
								</a>
								<div class="mailpit-catcher__snippet"><?= e((string) ($message['Snippet'] ?? '')) ?></div>
								<?php if ($tags !== [] || $attachmentCount > 0) { ?>
									<div class="mailpit-catcher__badges">
										<?php foreach ($tags as $tag) { ?>
											<span class="badge text-bg-secondary"><?= e((string) $tag) ?></span>
										<?php } ?>
										<?php if ($attachmentCount > 0) { ?>
											<span class="badge text-bg-info">
												<i class="bi bi-paperclip" aria-hidden="true"></i>
												<?= e((string) $attachmentCount) ?>
											</span>
										<?php } ?>
									</div>
								<?php } ?>
							</td>
							<td><?= e((string) ($message['FromFormatted'] ?? '')) ?></td>
							<td><?= e((string) ($message['ToFormatted'] ?? '')) ?></td>
							<td><span class="text-nowrap"><?= e((string) ($message['CreatedFormatted'] ?? '')) ?></span></td>
							<td><span class="text-nowrap"><?= e((string) ($message['SizeFormatted'] ?? '')) ?></span></td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
		<?php } ?>

		<div class="card-footer mailpit-catcher__pager">
			<div class="small text-muted">
				<?= e((string) $rangeStart) ?>-<?= e((string) $rangeEnd) ?> / <?= e((string) $total) ?>
			</div>
			<div class="btn-group btn-group-sm">
				<a class="btn btn-outline-secondary <?= ($urls['previous'] ?? '') === '' ? 'disabled' : '' ?>"
				   <?= $hxLinkAttrs((string) (($urls['previous'] ?? '') ?: '#'), (string) ($urls['previous_fragment'] ?? '')) ?>>
					<i class="bi bi-chevron-left" aria-hidden="true"></i>
					<span><?= e($this->strings['mailpit.pagination.previous'] ?? t('mailpit.pagination.previous')) ?></span>
				</a>
				<a class="btn btn-outline-secondary <?= ($urls['next'] ?? '') === '' ? 'disabled' : '' ?>"
				   <?= $hxLinkAttrs((string) (($urls['next'] ?? '') ?: '#'), (string) ($urls['next_fragment'] ?? '')) ?>>
					<span><?= e($this->strings['mailpit.pagination.next'] ?? t('mailpit.pagination.next')) ?></span>
					<i class="bi bi-chevron-right" aria-hidden="true"></i>
				</a>
			</div>
		</div>
	</section>
</div>
