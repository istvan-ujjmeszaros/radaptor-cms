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
					'Environment' => $environment['environment'] ?? null,
					'Application' => $environment['application_identifier'] ?? null,
					'Domain context' => $environment['domain_context'] ?? null,
					'Runtime' => $environment['runtime'] ?? null,
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
					'SMTP host' => $transport['host'] ?? null,
					'SMTP port' => $transport['port'] ?? null,
					'Using catcher' => $email['using_catcher'] ?? false,
					'Catcher host' => $catcher['host'] ?? null,
					'Catcher SMTP port' => $catcher['smtp_port'] ?? null,
					'Mailpit UI URL' => $catcher['mailpit_ui_url'] ?? null,
				]); ?>
			</div>
		</div>
	</section>

	<section class="col-12 col-xl-6">
		<div class="card card-hover h-100">
			<div class="card-body">
				<h2 class="h5 mb-3"><?= e($this->strings['runtime_diagnostics.card.database'] ?? 'Database') ?></h2>
				<?php $renderRows([
					'Driver' => $database['driver'] ?? null,
					'Host' => $database['host'] ?? null,
					'Port' => $database['port'] ?? null,
					'Database' => $database['database'] ?? null,
					'Username' => $database['username'] ?? null,
					'Password' => $database['password'] ?? null,
					'DSN' => $database['redacted_dsn'] ?? null,
				]); ?>
			</div>
		</div>
	</section>

	<section class="col-12 col-xl-6">
		<div class="card card-hover h-100">
			<div class="card-body">
				<h2 class="h5 mb-3"><?= e($this->strings['runtime_diagnostics.card.redis'] ?? 'Redis') ?></h2>
				<?php $renderRows([
					'Session' => $redis['session'] ?? null,
					'Cache' => $redis['cache'] ?? null,
					'Test' => $redis['test'] ?? null,
				]); ?>
			</div>
		</div>
	</section>

	<section class="col-12 col-xl-6">
		<div class="card card-hover h-100">
			<div class="card-body">
				<h2 class="h5 mb-3"><?= e($this->strings['runtime_diagnostics.card.mcp'] ?? 'MCP') ?></h2>
				<?php $renderRows([
					'Public URL' => $mcp['public_url'] ?? null,
					'Port' => $mcp['port'] ?? null,
					'Allowed origins' => $mcp['allowed_origins'] ?? null,
					'Enabled hint' => $mcp['enabled_hint'] ?? null,
				]); ?>
			</div>
		</div>
	</section>

	<section class="col-12 col-xl-6">
		<div class="card card-hover h-100">
			<div class="card-body">
				<h2 class="h5 mb-3"><?= e($this->strings['runtime_diagnostics.card.packages'] ?? 'Packages') ?></h2>
				<?php $renderRows([
					'Mode' => $packages['mode'] ?? null,
					'Local manifest' => $packages['local_manifest_present'] ?? null,
					'Local lock' => $packages['local_lock_present'] ?? null,
					'Workspace dev mode' => $packages['workspace_dev_mode_enabled'] ?? null,
					'Local overrides disabled' => $packages['local_overrides_disabled'] ?? null,
				]); ?>
			</div>
		</div>
	</section>

	<section class="col-12">
		<div class="card card-hover">
			<div class="card-body">
				<h2 class="h5 mb-3"><?= e($this->strings['runtime_diagnostics.card.packages'] ?? 'Packages') ?> - roots</h2>
				<?php if ($package_roots === []) { ?>
					<p class="mb-0 text-muted"><?= e($this->strings['runtime_diagnostics.none'] ?? 'None') ?></p>
				<?php } else { ?>
					<div class="table-responsive">
						<table class="table table-sm align-middle mb-0">
							<thead>
							<tr>
								<th>Package</th>
								<th>Source</th>
								<th>Version</th>
								<th>Active path</th>
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
