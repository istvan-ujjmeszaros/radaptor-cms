<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$summary = is_array($this->props['summary'] ?? null) ? $this->props['summary'] : [];
$environment = is_array($summary['environment'] ?? null) ? $summary['environment'] : [];
$email = is_array($summary['email'] ?? null) ? $summary['email'] : [];
$database = is_array($summary['database'] ?? null) ? $summary['database'] : [];
$redis = is_array($summary['redis'] ?? null) ? $summary['redis'] : [];
$mcp = is_array($summary['mcp'] ?? null) ? $summary['mcp'] : [];
$packages = is_array($summary['packages'] ?? null) ? $summary['packages'] : [];
$warnings = is_array($summary['warnings'] ?? null) ? $summary['warnings'] : [];
$strings = $this->strings;

$formatValue = static function (mixed $value) use ($strings): string {
	if (is_bool($value)) {
		return $value
			? (string) ($strings['runtime_diagnostics.yes'] ?? 'yes')
			: (string) ($strings['runtime_diagnostics.no'] ?? 'no');
	}

	if ($value === null || $value === '') {
		return '-';
	}

	if (is_array($value)) {
		return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
	}

	return (string) $value;
};

$renderRows = static function (array $rows) use ($formatValue): void {
	?>
	<dl class="row g-2 mb-0">
		<?php foreach ($rows as $label => $value) { ?>
			<dt class="col-12 col-md-5 text-muted fw-normal"><?= e((string) $label) ?></dt>
			<dd class="col-12 col-md-7 mb-2"><code><?= e($formatValue($value)) ?></code></dd>
		<?php } ?>
	</dl>
	<?php
};

$transport = is_array($email['transport'] ?? null) ? $email['transport'] : [];
$catcher = is_array($email['catcher'] ?? null) ? $email['catcher'] : [];
$package_roots = is_array($packages['package_roots'] ?? null) ? $packages['package_roots'] : [];
?>

<div class="subheader">
	<h1><?= e($this->strings['runtime_diagnostics.title'] ?? 'Runtime diagnostics') ?></h1>
	<p><?= e($this->strings['runtime_diagnostics.subtitle'] ?? '') ?></p>
</div>

<div class="row g-3 runtime-diagnostics">
	<section class="col-12 col-xl-6">
		<div class="card card-hover h-100">
			<div class="card-body">
					<h2 class="h5 mb-3"><?= e($this->strings['runtime_diagnostics.card.environment'] ?? 'Environment') ?></h2>
					<?php $renderRows([
						($this->strings['runtime_diagnostics.field.environment'] ?? t('runtime_diagnostics.field.environment')) => $environment['environment'] ?? null,
						($this->strings['runtime_diagnostics.field.application'] ?? t('runtime_diagnostics.field.application')) => $environment['application_identifier'] ?? null,
						($this->strings['runtime_diagnostics.field.domain_context'] ?? t('runtime_diagnostics.field.domain_context')) => $environment['domain_context'] ?? null,
						($this->strings['runtime_diagnostics.field.runtime'] ?? t('runtime_diagnostics.field.runtime')) => $environment['runtime'] ?? null,
					]); ?>
			</div>
		</div>
	</section>

	<section class="col-12 col-xl-6">
		<div class="card card-hover h-100">
			<div class="card-body">
				<h2 class="h5 mb-3"><?= e($this->strings['runtime_diagnostics.card.email'] ?? 'Email safety') ?></h2>
				<?php $renderRows([
					$this->strings['runtime_diagnostics.safe_to_test'] ?? 'Safe to test' => $email['safe_to_test'] ?? false,
					($this->strings['runtime_diagnostics.field.smtp_host'] ?? t('runtime_diagnostics.field.smtp_host')) => $transport['host'] ?? null,
					($this->strings['runtime_diagnostics.field.smtp_port'] ?? t('runtime_diagnostics.field.smtp_port')) => $transport['port'] ?? null,
					($this->strings['runtime_diagnostics.field.using_catcher'] ?? t('runtime_diagnostics.field.using_catcher')) => $email['using_catcher'] ?? false,
					($this->strings['runtime_diagnostics.field.catcher_host'] ?? t('runtime_diagnostics.field.catcher_host')) => $catcher['host'] ?? null,
					($this->strings['runtime_diagnostics.field.catcher_smtp_port'] ?? t('runtime_diagnostics.field.catcher_smtp_port')) => $catcher['smtp_port'] ?? null,
					($this->strings['runtime_diagnostics.field.mailpit_ui_url'] ?? t('runtime_diagnostics.field.mailpit_ui_url')) => $catcher['mailpit_ui_url'] ?? null,
				]); ?>
			</div>
		</div>
	</section>

	<section class="col-12 col-xl-6">
		<div class="card card-hover h-100">
			<div class="card-body">
				<h2 class="h5 mb-3"><?= e($this->strings['runtime_diagnostics.card.database'] ?? 'Database') ?></h2>
				<?php $renderRows([
					($this->strings['runtime_diagnostics.field.driver'] ?? t('runtime_diagnostics.field.driver')) => $database['driver'] ?? null,
					($this->strings['runtime_diagnostics.field.host'] ?? t('runtime_diagnostics.field.host')) => $database['host'] ?? null,
					($this->strings['runtime_diagnostics.field.port'] ?? t('runtime_diagnostics.field.port')) => $database['port'] ?? null,
					($this->strings['runtime_diagnostics.field.database'] ?? t('runtime_diagnostics.field.database')) => $database['database'] ?? null,
					($this->strings['runtime_diagnostics.field.username'] ?? t('runtime_diagnostics.field.username')) => $database['username'] ?? null,
					($this->strings['runtime_diagnostics.field.password'] ?? t('runtime_diagnostics.field.password')) => $database['password'] ?? null,
					($this->strings['runtime_diagnostics.field.dsn'] ?? t('runtime_diagnostics.field.dsn')) => $database['redacted_dsn'] ?? null,
				]); ?>
			</div>
		</div>
	</section>

	<section class="col-12 col-xl-6">
		<div class="card card-hover h-100">
			<div class="card-body">
				<h2 class="h5 mb-3"><?= e($this->strings['runtime_diagnostics.card.redis'] ?? 'Redis') ?></h2>
				<?php $renderRows([
					($this->strings['runtime_diagnostics.field.session'] ?? t('runtime_diagnostics.field.session')) => $redis['session'] ?? null,
					($this->strings['runtime_diagnostics.field.cache'] ?? t('runtime_diagnostics.field.cache')) => $redis['cache'] ?? null,
					($this->strings['runtime_diagnostics.field.test'] ?? t('runtime_diagnostics.field.test')) => $redis['test'] ?? null,
				]); ?>
			</div>
		</div>
	</section>

	<section class="col-12 col-xl-6">
		<div class="card card-hover h-100">
			<div class="card-body">
				<h2 class="h5 mb-3"><?= e($this->strings['runtime_diagnostics.card.mcp'] ?? 'MCP') ?></h2>
				<?php $renderRows([
					($this->strings['runtime_diagnostics.field.public_url'] ?? t('runtime_diagnostics.field.public_url')) => $mcp['public_url'] ?? null,
					($this->strings['runtime_diagnostics.field.port'] ?? t('runtime_diagnostics.field.port')) => $mcp['port'] ?? null,
					($this->strings['runtime_diagnostics.field.allowed_origins'] ?? t('runtime_diagnostics.field.allowed_origins')) => $mcp['allowed_origins'] ?? null,
					($this->strings['runtime_diagnostics.field.enabled_hint'] ?? t('runtime_diagnostics.field.enabled_hint')) => $mcp['enabled_hint'] ?? null,
				]); ?>
			</div>
		</div>
	</section>

	<section class="col-12 col-xl-6">
		<div class="card card-hover h-100">
			<div class="card-body">
				<h2 class="h5 mb-3"><?= e($this->strings['runtime_diagnostics.card.packages'] ?? 'Packages') ?></h2>
				<?php $renderRows([
					($this->strings['runtime_diagnostics.field.mode'] ?? t('runtime_diagnostics.field.mode')) => $packages['mode'] ?? null,
					($this->strings['runtime_diagnostics.field.local_manifest'] ?? t('runtime_diagnostics.field.local_manifest')) => $packages['local_manifest_present'] ?? null,
					($this->strings['runtime_diagnostics.field.local_lock'] ?? t('runtime_diagnostics.field.local_lock')) => $packages['local_lock_present'] ?? null,
					($this->strings['runtime_diagnostics.field.workspace_dev_mode'] ?? t('runtime_diagnostics.field.workspace_dev_mode')) => $packages['workspace_dev_mode_enabled'] ?? null,
					($this->strings['runtime_diagnostics.field.local_overrides_disabled'] ?? t('runtime_diagnostics.field.local_overrides_disabled')) => $packages['local_overrides_disabled'] ?? null,
				]); ?>
			</div>
		</div>
	</section>

	<section class="col-12">
		<div class="card card-hover">
			<div class="card-body">
				<h2 class="h5 mb-3"><?= e($this->strings['runtime_diagnostics.card.package_roots'] ?? t('runtime_diagnostics.card.package_roots')) ?></h2>
				<?php if ($package_roots === []) { ?>
					<p class="mb-0 text-muted"><?= e($this->strings['runtime_diagnostics.none'] ?? 'None') ?></p>
				<?php } else { ?>
					<div class="table-responsive">
						<table class="table table-sm align-middle mb-0">
							<thead>
							<tr>
								<th><?= e($this->strings['runtime_diagnostics.col.package'] ?? t('runtime_diagnostics.col.package')) ?></th>
								<th><?= e($this->strings['runtime_diagnostics.col.source'] ?? t('runtime_diagnostics.col.source')) ?></th>
								<th><?= e($this->strings['runtime_diagnostics.col.version'] ?? t('runtime_diagnostics.col.version')) ?></th>
								<th><?= e($this->strings['runtime_diagnostics.col.active_path'] ?? t('runtime_diagnostics.col.active_path')) ?></th>
							</tr>
							</thead>
							<tbody>
							<?php foreach ($package_roots as $package) { ?>
								<?php $package = is_array($package) ? $package : []; ?>
								<tr>
									<td><code><?= e((string) ($package['package_key'] ?? $package['package'] ?? '')) ?></code></td>
									<td><?= e((string) ($package['source_type'] ?? '')) ?></td>
									<td><?= e((string) ($package['version'] ?? '')) ?></td>
									<td><code><?= e((string) ($package['active_path'] ?? '')) ?></code></td>
								</tr>
							<?php } ?>
							</tbody>
						</table>
					</div>
				<?php } ?>
			</div>
		</div>
	</section>

	<section class="col-12">
		<div class="card card-hover">
			<div class="card-body">
				<h2 class="h5 mb-3"><?= e($this->strings['runtime_diagnostics.card.warnings'] ?? 'Warnings') ?></h2>
				<?php if ($warnings === []) { ?>
					<p class="mb-0 text-muted"><?= e($this->strings['runtime_diagnostics.none'] ?? 'None') ?></p>
				<?php } else { ?>
					<ul class="mb-0">
						<?php foreach ($warnings as $warning) { ?>
							<li><?= e((string) $warning) ?></li>
						<?php } ?>
					</ul>
				<?php } ?>
			</div>
		</div>
	</section>
</div>
