<?php assert(isset($this) && $this instanceof Template); ?>
<?php
library('__ADMIN_FORM_BUILDER');

$state = is_array($this->props['state'] ?? null) ? $this->props['state'] : [];
$definitions = is_array($state['definitions'] ?? null) ? $state['definitions'] : [];
$sourceFilter = (string)($state['source_filter'] ?? 'custom');
$urls = is_array($this->props['urls'] ?? null) ? $this->props['urls'] : [];
$editorFragmentUrl = (string)($urls['editor_fragment'] ?? '');
$jsonFlags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$stringsJson = json_encode($this->strings, $jsonFlags);
$buildEditorUrl = static function (string $definitionSlug) use ($sourceFilter): string {
	$params = [
		'form' => $definitionSlug,
	];

	if ($sourceFilter === 'system') {
		$params['source'] = 'system';
	}

	return '/admin/forms/?' . http_build_query($params, '', '&');
};
$tabUrl = static fn (string $source): string => '/admin/forms/?source=' . rawurlencode($source);
?>
<section
	class="form-list"
	data-controller="form-list"
	data-form-list-create-url-value="<?= e((string)($urls['create'] ?? '')) ?>"
	data-form-list-editor-fragment-url-value="<?= e($editorFragmentUrl) ?>"
	data-form-list-csrf-token-value="<?= e((string)($this->props['csrf_token'] ?? '')) ?>"
	data-form-list-strings-value="<?= e($stringsJson ?: '{}') ?>"
>
	<div class="content-card form-list__card">
		<div class="content-card-header align-items-start gap-3 flex-wrap">
			<div>
				<h1 class="h4 mb-1"><?= e($this->strings['form.list.title']) ?></h1>
				<p class="text-body-secondary mb-0"><?= e($this->strings['form.list.subtitle']) ?></p>
			</div>
			<span class="form-list__status badge text-bg-secondary bg-opacity-25" data-form-list-target="status"></span>
		</div>

		<div class="content-card-body">
			<form class="row g-3 align-items-end form-list__create" data-action="submit->form-list#create">
				<label class="col-12 col-lg-5 form-label mb-0">
					<span class="d-block mb-2"><?= e($this->strings['form.list.new_slug']) ?></span>
					<input
						type="text"
						class="form-control form-control-sm"
						name="definition_slug"
						value=""
						required
						maxlength="120"
						pattern="(?:capture[ _\-]*)?[A-Za-z0-9]+(?:[ _\-]+[A-Za-z0-9]+)*"
						placeholder="<?= e($this->strings['form.builder.placeholder.slug']) ?>"
						title="<?= e($this->strings['form.builder.help.slug']) ?>"
					>
				</label>
				<label class="col-12 col-lg-5 form-label mb-0">
					<span class="d-block mb-2"><?= e($this->strings['form.list.new_title']) ?></span>
					<input type="text" class="form-control form-control-sm" name="title" value="">
				</label>
				<div class="col-12 col-lg-auto">
					<button type="submit" class="btn btn-primary btn-sm">
						<i class="bi bi-plus-lg" aria-hidden="true"></i>
						<?= e($this->strings['form.list.create']) ?>
					</button>
				</div>
			</form>
		</div>

		<div class="content-card-tabs">
			<nav class="nav nav-tabs form-list__tabs" aria-label="<?= e($this->strings['form.list.title']) ?>">
				<a
					class="nav-link form-list__tab<?= $sourceFilter === 'custom' ? ' active is-active' : '' ?>"
					href="<?= e($tabUrl('custom')) ?>"
					<?= $sourceFilter === 'custom' ? 'aria-current="page"' : '' ?>
				>
					<?= e($this->strings['form.list.tab.custom']) ?> (<?= (int)($state['custom_count'] ?? 0) ?>)
				</a>
				<a
					class="nav-link form-list__tab<?= $sourceFilter === 'system' ? ' active is-active' : '' ?>"
					href="<?= e($tabUrl('system')) ?>"
					<?= $sourceFilter === 'system' ? 'aria-current="page"' : '' ?>
				>
					<?= e($this->strings['form.list.tab.system']) ?> (<?= (int)($state['system_count'] ?? 0) ?>)
				</a>
			</nav>
		</div>

		<div class="content-card-body pt-3">
			<?php if ($definitions === []): ?>
				<div class="form-list__empty">
					<?= e($this->strings[$sourceFilter === 'system' ? 'form.list.empty_system' : 'form.list.empty_custom']) ?>
				</div>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-hover align-middle mb-0 form-list__table">
						<thead>
						<tr>
							<th><?= e($this->strings['form.list.col.slug']) ?></th>
							<th><?= e($this->strings['form.list.col.source']) ?></th>
							<th><?= e($this->strings['form.list.col.status']) ?></th>
							<th><?= e($this->strings['form.list.col.version']) ?></th>
							<th><?= e($this->strings['form.list.col.usage']) ?></th>
							<th class="text-end"><?= e($this->strings['form.list.col.actions']) ?></th>
						</tr>
						</thead>
						<tbody>
						<?php foreach ($definitions as $definition): ?>
							<?php
							if (!is_array($definition)) {
								continue;
							}

							$slug = (string)($definition['definition_slug'] ?? '');
							$readOnly = (bool)($definition['read_only'] ?? false);
							$publishedVersion = $definition['published_version_number'] ?? null;
							$draftVersion = $definition['draft_version_number'] ?? null;
							$statusKey = $draftVersion !== null
								? 'form.list.status.draft'
								: ($publishedVersion !== null ? 'form.list.status.published' : 'form.list.status.unknown');
							$versionLabel = $publishedVersion !== null
								? t('form.list.version.published', ['version' => (string)(int)$publishedVersion])
								: ($draftVersion !== null ? t('form.list.version.draft', ['version' => (string)(int)$draftVersion]) : $this->strings['form.list.version.none']);
							$usageCount = (int)($definition['usage_count'] ?? 0);
							?>
							<tr data-form-list-slug="<?= e($slug) ?>">
								<td><code><?= e($slug) ?></code></td>
								<td><?= e((string)($definition['source'] ?? '')) ?></td>
								<td><?= e($this->strings[$statusKey]) ?></td>
								<td><?= e($versionLabel) ?></td>
								<td>
									<?php if ($usageCount > 0): ?>
										<?= $usageCount ?>
									<?php else: ?>
										<span class="text-muted"><?= e($this->strings['form.list.usage.none']) ?></span>
									<?php endif; ?>
								</td>
								<td class="text-end">
									<a
										class="btn btn-outline-primary btn-sm"
										href="<?= e($buildEditorUrl($slug)) ?>"
										data-action="click->form-list#openEditor"
										data-form-list-slug-param="<?= e($slug) ?>"
									>
										<i class="bi bi-<?= $readOnly ? 'eye' : 'pencil-square' ?>" aria-hidden="true"></i>
										<?= e($this->strings[$readOnly ? 'form.list.action.view' : 'form.list.action.edit']) ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div
			class="modal fade form-list__editor-modal"
			tabindex="-1"
			aria-hidden="true"
			data-form-list-target="modal"
		>
			<div class="modal-dialog modal-fullscreen">
				<div class="modal-content">
					<div class="modal-header">
						<h2 class="modal-title h5"><?= e($this->strings['form.list.editor_title']) ?></h2>
						<button
							type="button"
							class="btn-close"
							aria-label="<?= e($this->strings['form.list.close']) ?>"
							data-action="form-list#requestCloseEditor"
						></button>
					</div>
					<div class="modal-body p-0" data-form-list-target="editorHost">
						<div class="form-list__editor-loading">
							<?= e($this->strings['form.list.editor_loading']) ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
