<?php assert(isset($this) && $this instanceof Template); ?>
<?php
library('__ADMIN_FORM_BUILDER');

$state = is_array($this->props['state'] ?? null) ? $this->props['state'] : [];
$selected = is_array($state['selected'] ?? null) ? $state['selected'] : [];
$definition = is_array($selected['definition'] ?? null) ? $selected['definition'] : null;
$palette = is_array($state['palette'] ?? null) ? $state['palette'] : [];
$dropTargets = is_array($state['drop_targets'] ?? null) ? $state['drop_targets'] : [];
$descriptor = is_array($selected['descriptor'] ?? null) ? $selected['descriptor'] : [];
$usage = is_array($selected['usage'] ?? null) ? $selected['usage'] : [];
$urls = is_array($this->props['urls'] ?? null) ? $this->props['urls'] : [];
$selectedSlug = is_array($definition) ? (string)($definition['definition_slug'] ?? '') : '';
$readOnly = (bool)($selected['read_only'] ?? false);
$activeDraft = is_array($selected['active_draft'] ?? null) ? $selected['active_draft'] : null;
$publishedVersion = is_array($selected['published_version'] ?? null) ? $selected['published_version'] : null;

$jsonFlags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$stateJson = json_encode([
	'definition_slug' => $selectedSlug,
	'descriptor' => $descriptor,
	'base_server_hash' => (string)($selected['base_server_hash'] ?? ''),
	'read_only' => $readOnly,
	'active_draft' => $activeDraft,
	'published_version' => $publishedVersion,
	'usage' => $usage,
	'initial_panel' => (string)($this->props['initial_panel'] ?? 'properties'),
	'initial_preview' => is_array($this->props['initial_preview'] ?? null) ? $this->props['initial_preview'] : [],
	'initial_preview_html' => (string)($this->props['initial_preview_html'] ?? ''),
	'palette' => $palette,
	'drop_targets' => $dropTargets,
], $jsonFlags);
$stringsJson = json_encode($this->strings, $jsonFlags);
?>
<section
	class="form-builder"
	data-controller="form-builder"
	data-form-builder-state-value="<?= e($stateJson ?: '{}') ?>"
	data-form-builder-strings-value="<?= e($stringsJson ?: '{}') ?>"
	data-form-builder-create-url-value="<?= e((string)($urls['create'] ?? '')) ?>"
	data-form-builder-preview-url-value="<?= e((string)($urls['preview_render'] ?? '')) ?>"
	data-form-builder-save-url-value="<?= e((string)($urls['save_draft'] ?? '')) ?>"
	data-form-builder-publish-url-value="<?= e((string)($urls['publish'] ?? '')) ?>"
	data-form-builder-csrf-token-value="<?= e((string)($this->props['csrf_token'] ?? '')) ?>"
>
	<header class="form-builder__header card shadow-sm">
		<div class="form-builder__title-row">
			<h1 class="form-builder__title h5 mb-0"><?= e($this->strings['form.builder.title']) ?></h1>
			<span class="form-builder__status badge text-bg-light" data-form-builder-target="status"><?= e($this->strings[$readOnly ? 'form.builder.status.read_only' : 'form.builder.status.clean']) ?></span>
		</div>
	</header>

	<div class="form-builder__toolbar card shadow-sm">
		<button type="button" class="btn btn-outline-secondary btn-sm" data-action="form-builder#undo" data-form-builder-target="undoButton">
			<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
			<?= e($this->strings['form.builder.action.undo']) ?>
		</button>
		<button type="button" class="btn btn-outline-secondary btn-sm" data-action="form-builder#redo" data-form-builder-target="redoButton">
			<i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
			<?= e($this->strings['form.builder.action.redo']) ?>
		</button>
		<button type="button" class="btn btn-outline-secondary btn-sm" data-action="form-builder#moveSelectedUp" data-form-builder-target="moveUpButton">
			<i class="bi bi-arrow-up" aria-hidden="true"></i>
			<?= e($this->strings['form.builder.action.move_up']) ?>
		</button>
		<button type="button" class="btn btn-outline-secondary btn-sm" data-action="form-builder#moveSelectedDown" data-form-builder-target="moveDownButton">
			<i class="bi bi-arrow-down" aria-hidden="true"></i>
			<?= e($this->strings['form.builder.action.move_down']) ?>
		</button>
		<button type="button" class="btn btn-outline-danger btn-sm" data-action="form-builder#deleteSelected" data-form-builder-target="deleteButton">
			<i class="bi bi-trash" aria-hidden="true"></i>
			<?= e($this->strings['form.builder.action.delete']) ?>
		</button>
		<span class="form-builder__toolbar-spacer"></span>
		<button type="button" class="btn btn-outline-primary btn-sm" data-action="form-builder#saveDraft" data-form-builder-target="saveButton">
			<i class="bi bi-save" aria-hidden="true"></i>
			<?= e($this->strings['form.builder.action.save_draft']) ?>
		</button>
		<button type="button" class="btn btn-primary btn-sm" data-action="form-builder#publish" data-form-builder-target="publishButton">
			<i class="bi bi-upload" aria-hidden="true"></i>
			<?= e($this->strings['form.builder.action.publish']) ?>
		</button>
	</div>

	<div class="form-builder__conflict alert alert-warning" data-form-builder-target="conflictActions" hidden>
		<span><?= e($this->strings['form.builder.warning.reload_or_overwrite']) ?></span>
		<button type="button" class="btn btn-outline-secondary btn-sm" data-action="form-builder#reloadServer">
			<?= e($this->strings['form.builder.action.reload_server']) ?>
		</button>
		<button type="button" class="btn btn-warning btn-sm" data-action="form-builder#overwriteLocal">
			<?= e($this->strings['form.builder.action.overwrite_local']) ?>
		</button>
	</div>

	<div class="form-builder__grid">
		<aside class="form-builder__palette card shadow-sm" aria-label="<?= e($this->strings['form.builder.palette']) ?>">
			<h2 class="h6"><?= e($this->strings['form.builder.palette']) ?></h2>
			<div class="form-builder__palette-items">
				<?php foreach ($palette as $item): ?>
					<?php
					if (!is_array($item)) {
						continue;
					}
					?>
						<button
							type="button"
							draggable="<?= $readOnly ? 'false' : 'true' ?>"
							class="btn btn-outline-secondary form-builder__palette-item"
							data-form-builder-palette-type-param="<?= e((string)($item['type'] ?? '')) ?>"
							data-action="pointerdown->form-builder#preparePaletteDrag dragstart->form-builder#dragPaletteItem dragend->form-builder#endPaletteDrag click->form-builder#addPaletteItem"
							<?= $readOnly ? 'disabled' : '' ?>
					>
						<i class="bi bi-<?= e((string)($item['icon'] ?? 'type')) ?>" aria-hidden="true"></i>
						<span><?= e((string)($item['label'] ?? '')) ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</aside>

		<main class="form-builder__preview-panel card shadow-sm">
			<h2 class="h6"><?= e($this->strings['form.builder.preview']) ?></h2>
			<div class="form-builder__preview-wrap">
				<iframe
					class="form-builder__preview"
					title="<?= e($this->strings['form.builder.preview']) ?>"
					data-form-builder-target="previewFrame"
				></iframe>
				<div
					class="form-builder__preview-drop-overlay"
					data-form-builder-target="previewOverlay"
					data-action="dragenter->form-builder#previewOverlayDrag dragover->form-builder#previewOverlayDrag dragleave->form-builder#previewOverlayLeave drop->form-builder#previewOverlayDrop"
					hidden
				></div>
			</div>
		</main>

		<aside class="form-builder__properties card shadow-sm">
			<h2 class="h6"><?= e($this->strings['form.builder.properties']) ?></h2>
			<div class="btn-group form-builder__panel-tabs" role="tablist">
				<button type="button" class="btn btn-outline-secondary btn-sm" data-form-builder-target="propertiesTabButton" data-action="form-builder#showPropertiesPanel">
					<?= e($this->strings['form.builder.panel.properties']) ?>
				</button>
				<button type="button" class="btn btn-outline-secondary btn-sm" data-form-builder-target="usageTabButton" data-action="form-builder#showUsagePanel">
					<?= e($this->strings['form.builder.panel.usage']) ?> (<?= count($usage) ?>)
				</button>
			</div>
			<div data-form-builder-target="propertiesPane" class="form-builder__properties-pane">
				<div data-form-builder-target="emptyProperties" class="form-builder__empty-properties">
					<?= e($this->strings['form.builder.no_selection']) ?>
				</div>
				<div data-form-builder-target="propertiesPanel">
					<label class="form-label w-100">
						<span><?= e($this->strings['form.builder.label.title']) ?></span>
						<input type="text" class="form-control form-control-sm" data-form-builder-target="formTitleInput" data-action="input->form-builder#updateFormText">
					</label>
					<label class="form-label w-100">
						<span><?= e($this->strings['form.builder.label.description']) ?></span>
						<textarea rows="2" class="form-control form-control-sm" data-form-builder-target="formDescriptionInput" data-action="input->form-builder#updateFormText"></textarea>
					</label>
					<label class="form-label w-100">
						<span><?= e($this->strings['form.builder.label.submit_label']) ?></span>
						<input type="text" class="form-control form-control-sm" data-form-builder-target="submitLabelInput" data-action="input->form-builder#updateFormText">
					</label>
					<div data-form-builder-target="fieldProperties" hidden>
						<hr>
						<label class="form-label w-100">
							<span><?= e($this->strings['form.builder.label.field_label']) ?></span>
							<input type="text" class="form-control form-control-sm" data-form-builder-target="fieldLabelInput" data-action="input->form-builder#updateSelectedField">
						</label>
						<label class="form-label w-100">
							<span><?= e($this->strings['form.builder.label.field_name']) ?></span>
							<input type="text" class="form-control form-control-sm" data-form-builder-target="fieldNameInput" data-action="input->form-builder#updateSelectedField">
						</label>
						<label class="form-label w-100">
							<span><?= e($this->strings['form.builder.label.field_key']) ?></span>
							<input type="text" class="form-control form-control-sm" data-form-builder-target="fieldKeyInput" data-action="change->form-builder#confirmAndUpdateFieldKey">
						</label>
						<label class="form-check form-builder__checkbox">
							<input type="checkbox" class="form-check-input" data-form-builder-target="fieldRequiredInput" data-action="change->form-builder#updateSelectedField">
							<span><?= e($this->strings['form.builder.label.required']) ?></span>
						</label>
						<label class="form-label w-100" data-form-builder-target="fieldOptionsGroup">
							<span><?= e($this->strings['form.builder.label.options']) ?></span>
							<textarea rows="5" class="form-control form-control-sm" data-form-builder-target="fieldOptionsInput" data-action="input->form-builder#updateSelectedField"></textarea>
						</label>
					</div>
				</div>
			</div>
			<div data-form-builder-target="usagePane" class="form-builder__usage-pane" hidden>
				<?php if ($usage === []): ?>
					<div class="form-builder__empty-properties"><?= e($this->strings['form.builder.usage.empty']) ?></div>
				<?php else: ?>
					<ul class="form-builder__usage-list">
						<?php foreach ($usage as $placement): ?>
							<?php
							if (!is_array($placement)) {
								continue;
							}
							$path = (string)($placement['path'] ?? '');
							?>
							<li>
								<a href="<?= e($path) ?>" target="_blank" rel="noopener"><?= e($path) ?></a>
								<span><?= e($this->strings['form.builder.usage.slot']) ?>: <?= e((string)($placement['slot'] ?? '')) ?></span>
								<span><?= e($this->strings['form.builder.usage.connection']) ?>: <?= e((string)($placement['connection_id'] ?? '')) ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</aside>
	</div>
</section>
