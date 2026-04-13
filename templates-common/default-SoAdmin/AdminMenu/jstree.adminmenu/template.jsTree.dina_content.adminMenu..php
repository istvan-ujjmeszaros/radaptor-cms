<?php assert(isset($this) && $this instanceof Template); ?>
<h3><?= e($this->strings['selection.invalid_entry']) ?>!</h3>
<br/>

<div class="buttons">

	<button class="button right_img button_delete" type="button" onclick="deleteRecursive('<?= '_' . ($this->props['id'][0] ?? $this->props['id']) ?>', <?= json_encode($this->strings['selection.invalid_entry']) ?>);"><?= Icons::get(IconNames::DELETE); ?>
		<?= e($this->strings['common.delete']) ?>
	</button>
	<br/>
	<br/>

</div>
