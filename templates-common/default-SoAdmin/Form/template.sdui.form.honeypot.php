<?php assert(isset($this) && $this instanceof Template); ?>
<?php
// Core/common honeypot component for capture MVP. Public themes that do not inherit
// common Form templates need their own isolated copy when themed capture forms land.
$id = (string)($this->props['id'] ?? '');
$name = (string)($this->props['name'] ?? '');
$label = (string)($this->props['label'] ?? '');
?>
<div class="sdui-form-honeypot" aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
	<label for="<?= e($id) ?>"><?= e($label) ?></label>
	<input id="<?= e($id) ?>" type="text" name="<?= e($name) ?>" value="" tabindex="-1" autocomplete="off">
</div>
