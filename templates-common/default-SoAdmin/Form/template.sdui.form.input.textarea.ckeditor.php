<?php assert(isset($this) && $this instanceof Template); ?>
<?php $this->registerLibrary('JQUERY_UI'); ?>
<?php
if (Config::DEV_APP_DEBUG_INFO->value() && Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)) {
	$this->registerLibrary('CKEDITOR_SOURCE');
} else {
	$this->registerLibrary('CKEDITOR');
}
?>
<label for="<?= e((string)$this->props['id']) ?>"<?= (string)($this->props['label_style_attr'] ?? '') ?>><?= e((string)($this->props['label'] ?? '')) ?></label>
<?= $this->fetchSlot('helper') ?>
<textarea id="<?= e((string)$this->props['id']) ?>"<?= (string)($this->props['input_style_attr'] ?? '') ?> name="<?= e((string)$this->props['name']) ?>"><?= e((string)($this->props['value'] ?? '')) ?></textarea>
<script type="text/javascript">
	$('#<?= e((string)$this->props['id']) ?>').ckeditor(function (editor) {
		},
		{
			"toolbar": "<?= e((string)($this->props['toolbar'] ?? 'toolbar_Minimal')) ?>",
			"skin": "light"
		});
</script>
