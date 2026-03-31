<?php assert(isset($this) && $this instanceof Template); ?>
<?php $this->registerLibrary('CODEMIRROR'); ?>
<label for="<?= e((string)$this->props['id']) ?>"<?= (string)($this->props['label_style_attr'] ?? '') ?>><?= e((string)($this->props['label'] ?? '')) ?></label>
<?= $this->fetchSlot('helper') ?>
<textarea id="<?= e((string)$this->props['id']) ?>"<?= (string)($this->props['input_style_attr'] ?? '') ?> name="<?= e((string)$this->props['name']) ?>"><?= e((string)($this->props['value'] ?? '')) ?></textarea>
<script type="text/javascript">
	var editor = CodeMirror.fromTextArea(document.getElementById("<?= e((string)$this->props['id']) ?>"), {
		mode: "application/x-httpd-php",
		tabMode: "shift",
		showCursorWhenSelecting: true,
		enterMode: "keep",
		indentUnit: 4,
		indentWithTabs: true,
		matchBrackets: true,
		lineNumbers: true,
		lineWrapping: true
	});
</script>
