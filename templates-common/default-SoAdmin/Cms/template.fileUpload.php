<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$this->registerLibrary('STIMULUS_LOADER');
$this->registerLibrary('DROPZONE');

$upload_url = ajax_url('dropzone.PartitionedUploadHandler', [
	'ref_id' => $this->props['ref_id'],
]);
$destination_path = (string) ($this->props['destination_path'] ?? '');
?>
<div class="subheader"><h1><?= e($this->strings['widget.file_upload.name']) ?></h1><br class="cleaner"></div>
<h2><?= e($this->strings['cms.file_upload.destination_path']) ?>: <i><?= e($destination_path) ?></i></h2>

<div
	data-controller="dropzone-upload"
	data-dropzone-upload-upload-url-value="<?= $upload_url ?>"
	data-dropzone-upload-default-message-value="<?= e($this->strings['common.upload']) ?>"
	data-dropzone-upload-uploaded-message-value="<?= e($this->strings['cms.file.uploaded']) ?>"
	data-dropzone-upload-upload-error-message-value="<?= e($this->strings['cms.file.upload_error']) ?>"
>
	<form
		action="<?= $upload_url ?>"
		class="dropzone"
		data-dropzone-upload-target="form"
	>
		<div class="dz-message">
			<strong><?= e($this->strings['common.upload']) ?></strong>
		</div>
	</form>

	<p data-dropzone-upload-target="status"></p>
</div>
