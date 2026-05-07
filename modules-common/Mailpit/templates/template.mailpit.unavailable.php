<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$message = trim((string) ($this->props['message'] ?? ''));
$statusCode = (int) ($this->props['status_code'] ?? 0);
?>

<div class="mailpit-catcher">
	<div class="alert alert-danger d-flex align-items-start gap-3">
		<i class="bi bi-plug" aria-hidden="true"></i>
		<div>
			<h1 class="h5"><?= e($this->strings['mailpit.unavailable'] ?? t('mailpit.unavailable')) ?></h1>
			<?php if ($message !== '') { ?>
				<p class="mb-0"><?= e($message) ?></p>
			<?php } ?>
			<?php if ($statusCode > 0) { ?>
				<p class="small text-muted mb-0">HTTP <?= e((string) $statusCode) ?></p>
			<?php } ?>
		</div>
	</div>
</div>
