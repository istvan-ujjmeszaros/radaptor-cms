<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$locales = is_array($this->props['locales'] ?? null) ? $this->props['locales'] : [];
$action_url = (string) ($this->props['action_url'] ?? Url::getUrl('locale.set-enabled'));

$formatUsage = static function (array $usage): string {
	$parts = [];

	foreach ($usage as $table => $count) {
		if ((int) $count > 0) {
			$parts[] = $table . ': ' . (int) $count;
		}
	}

	return $parts !== [] ? implode(', ', $parts) : t('locale_admin.usage.none');
};
?>

<div class="subheader">
	<h1><?= e(t('locale_admin.title')) ?></h1>
	<p><?= e(t('locale_admin.subtitle')) ?></p>
</div>

<div class="row g-3">
	<section class="col-12 col-xl-4">
		<div class="card card-hover h-100">
			<div class="card-body">
				<h2 class="h5 mb-3"><?= e(t('locale_admin.add_title')) ?></h2>
				<form method="post" action="<?= e($action_url) ?>" class="d-flex gap-2 align-items-end">
					<div class="flex-grow-1">
						<label for="locale-admin-new-locale" class="form-label"><?= e(t('locale_admin.field.locale')) ?></label>
						<input id="locale-admin-new-locale" name="locale" class="form-control" placeholder="<?= e(t('locale_admin.placeholder.locale')) ?>">
					</div>
					<input type="hidden" name="enabled" value="1">
					<button type="submit" class="btn btn-primary"><?= e(t('locale_admin.action.add_enabled')) ?></button>
				</form>
			</div>
		</div>
	</section>

	<section class="col-12">
		<div class="card card-hover">
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-sm align-middle mb-0">
						<thead>
						<tr>
							<th><?= e(t('locale_admin.col.locale')) ?></th>
							<th><?= e(t('locale_admin.col.label')) ?></th>
							<th><?= e(t('locale_admin.col.native_label')) ?></th>
							<th><?= e(t('locale_admin.col.status')) ?></th>
							<th><?= e(t('locale_admin.col.home')) ?></th>
							<th><?= e(t('locale_admin.col.usage')) ?></th>
							<th class="text-end"><?= e(t('locale_admin.col.actions')) ?></th>
						</tr>
						</thead>
						<tbody>
						<?php foreach ($locales as $locale) { ?>
							<?php
							$code = (string) ($locale['locale'] ?? '');
							$is_enabled = (bool) ($locale['is_enabled'] ?? false);
							$is_default = (bool) ($locale['is_default'] ?? false);
							$usage = is_array($locale['usage'] ?? null) ? $locale['usage'] : [];
							$home_count = (int) ($locale['effective_home_count'] ?? 0);
							?>
							<tr>
								<td><code><?= e($code) ?></code></td>
								<td><?= e((string) ($locale['label'] ?? '')) ?></td>
								<td><?= e((string) ($locale['native_label'] ?? '')) ?></td>
								<td>
									<span class="badge <?= $is_enabled ? 'bg-success' : 'bg-secondary' ?>">
										<?= e(t($is_enabled ? 'locale_admin.status.enabled' : 'locale_admin.status.disabled')) ?>
									</span>
									<?php if ($is_default) { ?>
										<span class="badge bg-info text-dark"><?= e(t('locale_admin.status.default')) ?></span>
									<?php } ?>
								</td>
								<td><?= e(t('locale_admin.home_count', ['count' => $home_count])) ?></td>
								<td><small><?= e($formatUsage($usage)) ?></small></td>
								<td class="text-end">
									<form method="post" action="<?= e($action_url) ?>" class="d-inline">
										<input type="hidden" name="locale" value="<?= e($code) ?>">
										<input type="hidden" name="enabled" value="<?= $is_enabled ? '0' : '1' ?>">
										<button type="submit" class="btn btn-sm <?= $is_enabled ? 'btn-outline-secondary' : 'btn-outline-primary' ?>" <?= $is_default && $is_enabled ? 'disabled' : '' ?>>
											<?= e(t($is_enabled ? 'locale_admin.action.disable' : 'locale_admin.action.enable')) ?>
										</button>
									</form>
								</td>
							</tr>
						<?php } ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</section>
</div>
