<?php assert(isset($this) && $this instanceof Template); ?>
<?php
/** @var list<AbstractImportExportDataset> $datasets */
$datasets = $this->props['datasets'] ?? [];

/** @var AbstractImportExportDataset|null $selectedDataset */
$selectedDataset = $this->props['selected_dataset'] ?? null;
$selectedDatasetKey = (string) ($this->props['selected_dataset_key'] ?? '');
$currentUrl = (string) ($this->props['current_url'] ?? Url::getCurrentUrl());
$selectedDatasetUrl = $selectedDatasetKey !== ''
	? Url::modifyCurrentUrl(['dataset' => $selectedDatasetKey])
	: $currentUrl;

registerI18n([
	'common.cancel',
	'common.close',
	'common.error',
	'common.loading',
	'import_export.action.preview_import',
	'import_export.action.run_import',
	'import_export.error.file_required',
	'import_export.result.detected_locale',
	'import_export.result.detected_locales',
	'import_export.result.format',
	'import_export.result.mode',
	'import_export.result.processed',
	'import_export.result.inserted',
	'import_export.result.updated',
	'import_export.result.imported',
	'import_export.result.skipped',
	'import_export.result.deleted',
	'import_export.result.dry_run_summary',
	'import_export.result.success_summary',
	'import_export.result.error_summary',
]);

$renderField = static function (string $name, array $field, string $value = ''): void {
	$type = $field['type'] ?? 'text';
	$label = $field['label'] ?? $name;
	$help = $field['help'] ?? '';
	$required = !empty($field['required']);
	$options = $field['options'] ?? [];
	$accept = $field['accept'] ?? '';
	$default = (string) ($field['default'] ?? '');
	$value = $value !== '' ? $value : $default;
	$id = 'import-export-' . preg_replace('/[^a-z0-9_-]+/i', '-', $name);
	$extraAttrs = $name === 'csv_file'
		? ' data-import-export-target="fileInput"'
		: '';
	?>
	<div class="mb-3">
		<label for="<?= e($id) ?>"><strong><?= e($label) ?></strong></label><br>
		<?php if ($type === 'select') { ?>
			<select id="<?= e($id) ?>" name="<?= e($name) ?>" class="form-select form-select-sm" <?= $required ? 'required' : '' ?>>
				<?php foreach ($options as $optionValue => $optionLabel) { ?>
					<option value="<?= e($optionValue) ?>" <?= $optionValue === $value ? 'selected' : '' ?>>
						<?= e($optionLabel) ?>
					</option>
				<?php } ?>
			</select>
		<?php } elseif ($type === 'file') { ?>
			<input id="<?= e($id) ?>" type="file" name="<?= e($name) ?>" class="form-control form-control-sm"
				   <?= $accept !== '' ? 'accept="' . e($accept) . '"' : '' ?>
				   <?= $extraAttrs ?>
				   <?= $required ? 'required' : '' ?>>
		<?php } else { ?>
			<input id="<?= e($id) ?>" type="text" name="<?= e($name) ?>" value="<?= e($value) ?>"
				   class="form-control form-control-sm" <?= $required ? 'required' : '' ?>>
		<?php } ?>
		<?php if ($help !== '') { ?>
			<div class="small text-muted"><?= e($help) ?></div>
		<?php } ?>
	</div>
	<?php
};
?>

<style>
	.import-export-dialog {
		width: min(760px, 92vw);
		max-height: 85vh;
		border: none;
		border-radius: 16px;
		padding: 0;
		background: transparent;
		box-shadow: none;
		overflow: visible;
	}

	.import-export-dialog::backdrop {
		background: rgba(15, 23, 42, 0.16);
		backdrop-filter: blur(8px);
		-webkit-backdrop-filter: blur(8px);
	}

	.import-export-dialog .card {
		border-radius: 16px;
		overflow: hidden;
		box-shadow: 0 1.25rem 3rem rgba(0, 0, 0, 0.28);
	}
</style>

<div class="subheader">
	<h1><?= e(t('import_export.workbench.title')) ?></h1>
	<p><?= e(t('import_export.workbench.description')) ?></p>
</div>

<?php if (empty($datasets)) { ?>
	<p><?= e(t('import_export.workbench.no_datasets')) ?></p>
	<?php return; ?>
<?php } ?>

<div class="d-flex gap-4 flex-wrap align-items-start">
	<div style="min-width: 260px; max-width: 320px;">
		<form method="get" action="<?= e($currentUrl) ?>">
			<div class="mb-3">
				<label for="import-export-dataset"><strong><?= e(t('import_export.field.dataset.label')) ?></strong></label><br>
				<select id="import-export-dataset" name="dataset" class="form-select">
					<?php foreach ($datasets as $dataset) { ?>
						<option value="<?= e($dataset->getKey()) ?>" <?= $dataset->getKey() === $selectedDatasetKey ? 'selected' : '' ?>>
							<?= e($dataset->getName()) ?>
						</option>
					<?php } ?>
				</select>
			</div>
			<button type="submit" class="btn btn-outline-secondary btn-sm"><?= e(t('common.open')) ?></button>
		</form>
	</div>

	<div style="flex: 1 1 560px;">
		<?php if ($selectedDataset !== null) { ?>
			<h2><?= e($selectedDataset->getName()) ?></h2>
			<p><?= e($selectedDataset->getDescription()) ?></p>

			<?php if ($selectedDataset->supportsExport()) { ?>
				<div class="card mb-4">
					<div class="card-body">
						<h3><?= e($selectedDataset->getExportTitle()) ?></h3>
						<form method="get" action="/">
							<input type="hidden" name="context" value="importExport">
							<input type="hidden" name="event" value="download">
							<input type="hidden" name="dataset" value="<?= e($selectedDataset->getKey()) ?>">
							<?php foreach ($selectedDataset->getExportFieldDefinitions() as $fieldName => $field) {
								$renderField($fieldName, $field, Request::_GET($fieldName, (string) ($field['default'] ?? '')));
							} ?>
							<button type="submit" class="btn btn-primary btn-sm"><?= e($selectedDataset->getExportActionLabel()) ?></button>
						</form>
					</div>
				</div>
			<?php } ?>

			<?php if ($selectedDataset->supportsImport()) { ?>
				<div class="card" data-controller="import-export">
					<div class="card-body">
						<h3><?= e($selectedDataset->getImportTitle()) ?></h3>
						<form method="post"
							  enctype="multipart/form-data"
							  action="<?= event_url('importExport.import') ?>"
							  data-import-export-target="importForm"
							  data-action="submit->import-export#submit">
							<input type="hidden" name="dataset" value="<?= e($selectedDataset->getKey()) ?>">
							<input type="hidden" name="referer" value="<?= e($selectedDatasetUrl) ?>">
							<?php foreach ($selectedDataset->getImportFieldDefinitions() as $fieldName => $field) {
								$renderField($fieldName, $field, '');
							} ?>
							<div class="d-flex gap-2 flex-wrap">
								<button type="submit"
										name="dry_run"
										value="1"
										class="btn btn-outline-secondary btn-sm"
										data-import-export-target="submitButton">
									<?= e(t('import_export.action.preview_import')) ?>
								</button>
								<button type="submit"
										name="dry_run"
										value="0"
										class="btn btn-primary btn-sm"
										data-import-export-target="submitButton">
									<?= e(t('import_export.action.run_import')) ?>
								</button>
							</div>
						</form>
					</div>

					<dialog data-import-export-target="dialog" class="import-export-dialog">
						<div class="card mb-0">
							<div class="card-header">
								<strong data-import-export-target="dialogTitle"><?= e(t('common.loading')) ?></strong>
							</div>
							<div class="card-body">
								<div data-import-export-target="dialogSummary"></div>
								<div data-import-export-target="dialogErrors" class="mt-3"></div>
							</div>
							<div class="card-footer d-flex justify-content-end gap-2">
								<button type="button" class="btn btn-outline-secondary btn-sm"
										data-import-export-target="cancelButton"
										data-action="import-export#closeDialog">
									<?= e(t('common.cancel')) ?>
								</button>
								<button type="button"
										class="btn btn-primary btn-sm"
										data-import-export-target="runButton"
										data-action="import-export#runImportFromDialog"
										hidden>
									<?= e(t('import_export.action.run_import')) ?>
								</button>
							</div>
						</div>
					</dialog>
				</div>
			<?php } ?>
		<?php } ?>
	</div>
</div>
