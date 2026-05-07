<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$message = is_array($this->props['message'] ?? null) ? $this->props['message'] : [];
$tabs = is_array($this->props['tabs'] ?? null) ? $this->props['tabs'] : [];
$content = is_array($this->props['tab_content'] ?? null) ? $this->props['tab_content'] : [];
$urls = is_array($this->props['urls'] ?? null) ? $this->props['urls'] : [];
$id = (string) ($this->props['id'] ?? $message['ID'] ?? '');
$subject = trim((string) ($message['Subject'] ?? '')) ?: ($this->strings['mailpit.no_subject'] ?? t('mailpit.no_subject'));
$attachments = is_array($message['Attachments'] ?? null) ? $message['Attachments'] : [];
$inline = is_array($message['Inline'] ?? null) ? $message['Inline'] : [];
$attachmentFallback = $this->strings['mailpit.attachment_fallback'] ?? t('mailpit.attachment_fallback');

$hxLinkAttrs = static function (string $href, string $fragmentHref): string {
	if ($fragmentHref === '') {
		return 'href="' . e($href) . '"';
	}

	return 'href="' . e($href) . '" hx-get="' . e($fragmentHref) . '" hx-swap="none" hx-push-url="' . e($href) . '"';
};

$renderAddressRow = static function (string $label, string $value): void {
	if (trim($value) === '') {
		return;
	}
	?>
	<div class="mailpit-message__meta-row">
		<div class="mailpit-message__meta-label"><?= e($label) ?></div>
		<div class="mailpit-message__meta-value"><?= e($value) ?></div>
	</div>
	<?php
};

$renderAttachmentList = static function (array $items, string $title) use ($id, $attachmentFallback): void {
	if ($items === []) {
		return;
	}
	?>
	<div class="mailpit-message__attachments">
		<h3 class="h6"><?= e($title) ?></h3>
		<div class="list-group list-group-flush">
			<?php foreach ($items as $attachment) { ?>
				<?php
				$attachment = is_array($attachment) ? $attachment : [];
				$filename = (string) ($attachment['FileName'] ?? $attachment['ContentID'] ?? $attachment['PartID'] ?? $attachmentFallback);
				$partId = (string) ($attachment['PartID'] ?? '');
				$url = $partId !== '' ? MailpitCatcherUrls::event('attachment', [
					'id' => $id,
					'part' => $partId,
					'filename' => $filename,
					'download' => 1,
				]) : '#';
				?>
				<a class="list-group-item list-group-item-action mailpit-message__attachment" href="<?= e($url) ?>">
					<i class="bi bi-paperclip" aria-hidden="true"></i>
					<span><?= e($filename) ?></span>
					<small><?= e((string) ($attachment['ContentType'] ?? '')) ?></small>
				</a>
			<?php } ?>
		</div>
	</div>
	<?php
};
?>

<div class="mailpit-catcher mailpit-message">
	<div class="subheader mailpit-catcher__header">
		<div>
			<a class="btn btn-link btn-sm px-0 mailpit-message__back"
			   <?= $hxLinkAttrs((string) ($urls['inbox'] ?? '#'), (string) ($urls['inbox_fragment'] ?? '')) ?>>
				<i class="bi bi-arrow-left" aria-hidden="true"></i>
				<span><?= e($this->strings['mailpit.back_to_inbox'] ?? t('mailpit.back_to_inbox')) ?></span>
			</a>
			<h1><?= e($subject) ?></h1>
			<p><?= e((string) ($message['MessageID'] ?? $id)) ?></p>
		</div>
		<div class="mailpit-catcher__header-actions">
			<form method="post" action="<?= e((string) ($urls['delete'] ?? '#')) ?>"
				  hx-post="<?= e((string) ($urls['delete'] ?? '#')) ?>"
				  hx-swap="none">
				<input type="hidden" name="ids[]" value="<?= e($id) ?>">
				<input type="hidden" name="redirect" value="<?= e((string) ($urls['inbox'] ?? '/')) ?>">
				<button class="btn btn-outline-danger btn-sm" type="submit">
					<i class="bi bi-trash" aria-hidden="true"></i>
					<span><?= e($this->strings['mailpit.delete'] ?? t('mailpit.delete')) ?></span>
				</button>
			</form>
		</div>
	</div>

	<section class="card card-hover mailpit-message__summary">
		<div class="card-body">
			<?php $renderAddressRow($this->strings['mailpit.meta.from'] ?? t('mailpit.meta.from'), (string) ($message['FromFormatted'] ?? '')); ?>
			<?php $renderAddressRow($this->strings['mailpit.meta.to'] ?? t('mailpit.meta.to'), (string) ($message['ToFormatted'] ?? '')); ?>
			<?php $renderAddressRow($this->strings['mailpit.meta.cc'] ?? t('mailpit.meta.cc'), (string) ($message['CcFormatted'] ?? '')); ?>
			<?php $renderAddressRow($this->strings['mailpit.meta.bcc'] ?? t('mailpit.meta.bcc'), (string) ($message['BccFormatted'] ?? '')); ?>
			<?php $renderAddressRow($this->strings['mailpit.meta.date'] ?? t('mailpit.meta.date'), (string) ($message['DateFormatted'] ?? '')); ?>
			<?php $renderAddressRow($this->strings['mailpit.meta.size'] ?? t('mailpit.meta.size'), (string) ($message['SizeFormatted'] ?? '')); ?>
		</div>
	</section>

	<section class="card card-hover mailpit-message__viewer">
		<div class="mailpit-message__tabs">
			<ul class="nav nav-tabs" role="tablist">
				<?php foreach ($tabs as $tab) { ?>
					<?php $tab = is_array($tab) ? $tab : []; ?>
					<li class="nav-item" role="presentation">
						<a class="nav-link <?= !empty($tab['active']) ? 'active' : '' ?>"
						   <?= !empty($tab['active']) ? 'aria-current="page"' : '' ?>
						   <?= $hxLinkAttrs((string) ($tab['url'] ?? '#'), (string) ($tab['fragment_url'] ?? '')) ?>>
							<?= e((string) ($tab['label'] ?? '')) ?>
						</a>
					</li>
				<?php } ?>
			</ul>
		</div>

		<div class="card-body mailpit-message__tab-body">
			<?php if (($content['kind'] ?? '') === 'html') { ?>
				<?php $html = (string) ($content['html'] ?? ''); ?>
				<?php if (trim($html) === '') { ?>
					<p class="text-muted mb-0"><?= e($this->strings['mailpit.html_empty'] ?? t('mailpit.html_empty')) ?></p>
				<?php } else { ?>
					<iframe class="mailpit-message__html-frame"
							title="<?= e($this->strings['mailpit.html_preview_title'] ?? t('mailpit.html_preview_title')) ?>"
							sandbox="allow-popups allow-popups-to-escape-sandbox"
							srcdoc="<?= e($html) ?>"></iframe>
				<?php } ?>
			<?php } elseif (($content['kind'] ?? '') === 'source') { ?>
				<pre class="mailpit-message__code"><code><?= e((string) ($content['source'] ?? '')) ?></code></pre>
			<?php } elseif (($content['kind'] ?? '') === 'text') { ?>
				<pre class="mailpit-message__code"><code><?= e((string) ($content['text'] ?? '')) ?></code></pre>
			<?php } elseif (($content['kind'] ?? '') === 'headers') { ?>
				<?php $headers = is_array($content['headers'] ?? null) ? $content['headers'] : []; ?>
				<div class="table-responsive">
					<table class="table table-sm align-middle mailpit-message__headers">
						<tbody>
						<?php foreach ($headers as $name => $values) { ?>
							<tr>
								<th><?= e((string) $name) ?></th>
								<td>
									<?php foreach ((array) $values as $value) { ?>
										<div><?= e((string) $value) ?></div>
									<?php } ?>
								</td>
							</tr>
						<?php } ?>
						</tbody>
					</table>
				</div>
			<?php } elseif (($content['kind'] ?? '') === 'raw') { ?>
				<pre class="mailpit-message__code"><code><?= e((string) ($content['raw'] ?? '')) ?></code></pre>
			<?php } elseif (($content['kind'] ?? '') === 'html-check') { ?>
				<?php
				$result = is_array($content['result'] ?? null) ? $content['result'] : [];
				$total = is_array($result['Total'] ?? null) ? $result['Total'] : [];
				$warnings = is_array($result['Warnings'] ?? null) ? $result['Warnings'] : [];
				?>
				<div class="mailpit-check-summary">
					<div><strong><?= e((string) round((float) ($total['Supported'] ?? 0), 1)) ?>%</strong><span><?= e($this->strings['mailpit.check.supported'] ?? t('mailpit.check.supported')) ?></span></div>
					<div><strong><?= e((string) round((float) ($total['Partial'] ?? 0), 1)) ?>%</strong><span><?= e($this->strings['mailpit.check.partial'] ?? t('mailpit.check.partial')) ?></span></div>
					<div><strong><?= e((string) round((float) ($total['Unsupported'] ?? 0), 1)) ?>%</strong><span><?= e($this->strings['mailpit.check.unsupported'] ?? t('mailpit.check.unsupported')) ?></span></div>
					<div><strong><?= e((string) ($total['Tests'] ?? 0)) ?></strong><span><?= e($this->strings['mailpit.check.tests'] ?? t('mailpit.check.tests')) ?></span></div>
				</div>
				<?php if ($warnings === []) { ?>
					<p class="text-muted mb-0"><?= e($this->strings['mailpit.check.no_warnings'] ?? t('mailpit.check.no_warnings')) ?></p>
				<?php } else { ?>
					<div class="mailpit-check-list">
						<?php foreach ($warnings as $warning) { ?>
							<?php $warning = is_array($warning) ? $warning : []; ?>
							<article class="mailpit-check-warning">
								<div>
									<h3 class="h6"><?= e((string) ($warning['Title'] ?? ($this->strings['mailpit.warning_fallback'] ?? t('mailpit.warning_fallback')))) ?></h3>
									<p><?= e((string) ($warning['Description'] ?? '')) ?></p>
								</div>
								<span class="badge text-bg-warning"><?= e((string) ($warning['Category'] ?? 'html')) ?></span>
							</article>
						<?php } ?>
					</div>
				<?php } ?>
			<?php } elseif (($content['kind'] ?? '') === 'link-check') { ?>
				<?php
				$result = is_array($content['result'] ?? null) ? $content['result'] : [];
				$links = is_array($result['Links'] ?? null) ? $result['Links'] : [];
				?>
				<div class="table-responsive">
					<table class="table table-sm align-middle mailpit-message__links">
						<thead>
						<tr>
							<th>URL</th>
							<th><?= e($this->strings['mailpit.links.status'] ?? t('mailpit.links.status')) ?></th>
						</tr>
						</thead>
						<tbody>
						<?php foreach ($links as $link) { ?>
							<?php
							$link = is_array($link) ? $link : [];
							$statusCode = (int) ($link['StatusCode'] ?? 0);
							$statusClass = $statusCode >= 200 && $statusCode < 400 ? 'text-bg-success' : 'text-bg-danger';
							?>
							<tr>
								<td><code><?= e((string) ($link['URL'] ?? '')) ?></code></td>
								<td><span class="badge <?= e($statusClass) ?>"><?= e((string) ($link['Status'] ?? $statusCode)) ?></span></td>
							</tr>
						<?php } ?>
						</tbody>
					</table>
				</div>
				<?php if ($links === []) { ?>
					<p class="text-muted mb-0"><?= e($this->strings['mailpit.links.none'] ?? t('mailpit.links.none')) ?></p>
				<?php } ?>
			<?php } elseif (($content['kind'] ?? '') === 'error') { ?>
				<div class="alert alert-warning mb-0">
					<?= e((string) ($content['message'] ?? ($this->strings['mailpit.tab_error'] ?? t('mailpit.tab_error')))) ?>
				</div>
			<?php } ?>
		</div>
	</section>

	<?php $renderAttachmentList($attachments, $this->strings['mailpit.attachments'] ?? t('mailpit.attachments')); ?>
	<?php $renderAttachmentList($inline, $this->strings['mailpit.inline_attachments'] ?? t('mailpit.inline_attachments')); ?>
</div>
