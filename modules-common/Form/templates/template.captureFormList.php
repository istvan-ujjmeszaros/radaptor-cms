<?php assert(isset($this) && $this instanceof Template); ?>
<?php
library('__ADMIN_FORM_BUILDER');

$state = is_array($this->props['state'] ?? null) ? $this->props['state'] : [];
$definitions = is_array($state['definitions'] ?? null) ? $state['definitions'] : [];
$sourceFilter = (string)($state['source_filter'] ?? 'custom');
$showLifecycleColumns = $sourceFilter !== 'system';
$urls = is_array($this->props['urls'] ?? null) ? $this->props['urls'] : [];
$editorFragmentUrl = (string)($urls['editor_fragment'] ?? '');
$hooksListUrl = (string)($urls['hooks_list'] ?? Url::getUrl('form_hooks.list'));
$hooksSaveUrl = (string)($urls['hooks_save'] ?? Url::getUrl('form_hooks.save'));
$hooksDeleteUrl = (string)($urls['hooks_delete'] ?? Url::getUrl('form_hooks.delete'));
$hooksDeliveriesUrl = (string)($urls['hooks_deliveries'] ?? Url::getUrl('form_hooks.deliveries'));
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
	data-form-list-hooks-list-url-value="<?= e($hooksListUrl) ?>"
	data-form-list-hooks-save-url-value="<?= e($hooksSaveUrl) ?>"
	data-form-list-hooks-delete-url-value="<?= e($hooksDeleteUrl) ?>"
	data-form-list-hooks-deliveries-url-value="<?= e($hooksDeliveriesUrl) ?>"
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
							<?php if ($showLifecycleColumns): ?>
								<th><?= e($this->strings['form.list.col.source']) ?></th>
								<th><?= e($this->strings['form.list.col.status']) ?></th>
								<th><?= e($this->strings['form.list.col.version']) ?></th>
							<?php endif; ?>
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
								<?php if ($showLifecycleColumns): ?>
									<td><?= e((string)($definition['source'] ?? '')) ?></td>
									<td><?= e($this->strings[$statusKey]) ?></td>
									<td><?= e($versionLabel) ?></td>
								<?php endif; ?>
								<td>
									<?php if ($usageCount > 0): ?>
										<?= $usageCount ?>
									<?php else: ?>
										<span class="text-muted"><?= e($this->strings['form.list.usage.none']) ?></span>
									<?php endif; ?>
								</td>
								<td class="text-end">
									<div class="btn-group btn-group-sm" role="group" aria-label="<?= e($this->strings['form.list.col.actions']) ?>">
										<a
											class="btn btn-outline-primary"
											href="<?= e($buildEditorUrl($slug)) ?>"
											data-action="click->form-list#openEditor"
											data-form-list-slug-param="<?= e($slug) ?>"
										>
											<i class="bi bi-<?= $readOnly ? 'eye' : 'pencil-square' ?>" aria-hidden="true"></i>
											<?= e($this->strings[$readOnly ? 'form.list.action.view' : 'form.list.action.edit']) ?>
										</a>
										<button
											type="button"
											class="btn btn-outline-secondary"
											data-action="form-list#openHooks"
											data-form-list-slug-param="<?= e($slug) ?>"
										>
											<i class="bi bi-diagram-3" aria-hidden="true"></i>
											<?= e($this->strings['form.builder.panel.hooks']) ?>
										</button>
									</div>
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
						<div class="form-list__editor-loading" role="status" aria-live="polite">
							<span><?= e($this->strings['form.list.editor_loading']) ?></span>
							<span class="spinner-border text-primary mt-3" aria-hidden="true"></span>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div
			class="form-builder__usage-overlay form-builder__hooks-overlay"
			role="dialog"
			aria-modal="true"
			aria-labelledby="form-list-hooks-title"
			data-form-list-target="hooksModal"
			data-action="click->form-list#closeHooksOnBackdrop keydown.esc@window->form-list#closeHooks"
			hidden
		>
			<div class="modal-dialog modal-dialog-centered modal-xl">
				<div class="modal-content">
					<div class="modal-header">
						<h2 class="modal-title h5" id="form-list-hooks-title">
							<?= e($this->strings['form.builder.panel.hooks']) ?>
							<small class="text-body-secondary" data-form-list-target="hooksTitleSlug"></small>
						</h2>
						<button type="button" class="btn-close" data-action="form-list#closeHooks" aria-label="<?= e($this->strings['form.builder.action.close']) ?>"></button>
					</div>
					<div class="modal-body">
						<div class="form-builder__hooks-status" data-form-list-target="hooksStatus" role="status" aria-live="polite"></div>
						<div class="form-builder__hooks-grid">
							<aside class="form-builder__hooks-sidebar">
								<div class="form-builder__hooks-create-row">
									<label class="form-label mb-0">
										<span><?= e($this->strings['form.builder.hooks.preset']) ?></span>
										<select class="form-select form-select-sm" data-form-list-target="hooksPresetSelect"></select>
									</label>
									<button type="button" class="btn btn-outline-primary btn-sm" data-action="form-list#createHook">
										<i class="bi bi-plus-lg" aria-hidden="true"></i>
										<?= e($this->strings['form.builder.hooks.create']) ?>
									</button>
								</div>
								<div class="form-builder__hook-target-list" data-form-list-target="hooksList"></div>
							</aside>
							<section class="form-builder__hook-editor" data-form-list-target="hooksEditor" hidden>
								<form data-action="submit->form-list#saveHook">
									<div class="form-builder__hook-editor-grid">
											<label class="form-label form-builder__hook-full" data-form-list-target="hookTargetUrlGroup">
												<span><?= e($this->strings['form.builder.hooks.target_url']) ?></span>
												<input type="url" class="form-control form-control-sm" data-form-list-target="hookTargetUrlInput" placeholder="<?= e($this->strings['form.builder.hooks.target_placeholder']) ?>">
											</label>
											<label class="form-label form-builder__hook-full" data-form-list-target="hookSecretGroup">
												<span><?= e($this->strings['form.builder.hooks.secret']) ?></span>
												<input type="password" class="form-control form-control-sm" data-form-list-target="hookSecretInput" autocomplete="new-password" placeholder="<?= e($this->strings['form.builder.hooks.secret_placeholder']) ?>">
											</label>
										<label class="form-check form-builder__checkbox">
											<input type="checkbox" class="form-check-input" data-form-list-target="hookEnabledInput">
											<span><?= e($this->strings['form.builder.hooks.enabled']) ?></span>
										</label>
										<label class="form-check form-builder__checkbox">
											<input type="checkbox" class="form-check-input" data-form-list-target="hookNonProductionInput">
											<span><?= e($this->strings['form.builder.hooks.enabled_non_production']) ?></span>
										</label>
									</div>
									<div class="form-builder__hooks-section">
										<div class="form-builder__hooks-section-header">
											<h3><?= e($this->strings['form.builder.hooks.metadata']) ?></h3>
												<button type="button" class="btn btn-outline-secondary btn-sm" data-form-list-target="hookMetadataAddButton" data-action="form-list#addHookMetadataRow">
												<i class="bi bi-plus-lg" aria-hidden="true"></i>
												<?= e($this->strings['form.builder.hooks.add_metadata']) ?>
											</button>
										</div>
										<div class="form-builder__hook-metadata" data-form-list-target="hookMetadataRows"></div>
									</div>
									<div class="form-builder__hooks-section">
										<div class="form-builder__hooks-section-header">
											<h3><?= e($this->strings['form.builder.hooks.excluded_fields']) ?></h3>
										</div>
										<div class="form-builder__hook-checklist" data-form-list-target="hookExcludedFields"></div>
									</div>
									<div class="form-builder__hooks-section">
										<div class="form-builder__hooks-section-header">
											<h3><?= e($this->strings['form.builder.hooks.recent_logs']) ?></h3>
										</div>
										<div class="table-responsive">
											<table class="table table-sm align-middle mb-0 form-builder__hooks-log-table">
												<thead>
												<tr>
													<th><?= e($this->strings['form.builder.hooks.log_time']) ?></th>
													<th><?= e($this->strings['form.builder.hooks.log_status']) ?></th>
													<th><?= e($this->strings['form.builder.hooks.log_http_status']) ?></th>
													<th><?= e($this->strings['form.builder.hooks.log_message']) ?></th>
												</tr>
												</thead>
												<tbody data-form-list-target="hookLogsBody"></tbody>
											</table>
										</div>
									</div>
									<div class="form-builder__hook-actions">
										<button type="button" class="btn btn-outline-danger btn-sm" data-form-list-target="hookDeleteButton" data-action="form-list#deleteHook">
											<i class="bi bi-trash" aria-hidden="true"></i>
											<?= e($this->strings['form.builder.hooks.delete']) ?>
										</button>
										<span class="form-builder__toolbar-spacer"></span>
										<button type="button" class="btn btn-outline-secondary btn-sm" data-action="form-list#closeHooks">
											<?= e($this->strings['form.builder.action.close']) ?>
										</button>
										<button type="submit" class="btn btn-primary btn-sm" data-form-list-target="hookSaveButton">
											<i class="bi bi-save" aria-hidden="true"></i>
											<?= e($this->strings['form.builder.hooks.save']) ?>
										</button>
									</div>
								</form>
							</section>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
