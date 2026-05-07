<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$inboxUrl = (string) ($this->props['inbox_url'] ?? '#');
$inboxFragmentUrl = (string) ($this->props['inbox_fragment_url'] ?? '');
$attrs = $inboxFragmentUrl !== ''
	? 'href="' . e($inboxUrl) . '" hx-get="' . e($inboxFragmentUrl) . '" hx-swap="none" hx-push-url="' . e($inboxUrl) . '"'
	: 'href="' . e($inboxUrl) . '"';
?>

<div class="mailpit-catcher">
	<div class="alert alert-warning d-flex align-items-start gap-3">
		<i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
		<div>
			<h1 class="h5"><?= e($this->strings['mailpit.not_found'] ?? t('mailpit.not_found')) ?></h1>
			<a class="btn btn-outline-secondary btn-sm mt-2" <?= $attrs ?>>
				<i class="bi bi-arrow-left" aria-hidden="true"></i>
				<span><?= e($this->strings['mailpit.back_to_inbox'] ?? t('mailpit.back_to_inbox')) ?></span>
			</a>
		</div>
	</div>
</div>
