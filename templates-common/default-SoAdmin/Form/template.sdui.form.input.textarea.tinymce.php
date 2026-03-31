<?php assert(isset($this) && $this instanceof Template); ?>
<?php $this->registerLibrary('TINYMCE'); ?>
<label for="<?= e((string)$this->props['id']) ?>"<?= (string)($this->props['label_style_attr'] ?? '') ?>><?= e((string)($this->props['label'] ?? '')) ?></label>
<?= $this->fetchSlot('helper') ?>
<textarea id="<?= e((string)$this->props['id']) ?>"<?= (string)($this->props['input_style_attr'] ?? '') ?> name="<?= e((string)$this->props['name']) ?>"><?= e((string)($this->props['value'] ?? '')) ?></textarea>
<script type="text/javascript">
	document.domain = /(\w+)(.\w+)?$/.exec(location.hostname)[0];

	$(function () {
		$('#<?= e((string)$this->props['id']) ?>').tinymce({
			theme: "advanced",
			mode: "exact",
			language: "hu",
			width: "<?= !empty($this->props['width']) ? e((string)$this->props['width']) : '100%' ?>",
			height: "300px",
			imagemanager_contextmenu: true
		});
	});
</script>
