<?php assert(isset($this) && $this instanceof Template); ?>
<h3><?= e($this->strings['user.role.all']) ?></h3>
<br/>

<div class="buttons">

	<button class="button right_img button_add_role" type="button" onclick="widgettype._.location('<?= Form::getSeoUrl(FormList::ROLE, null, null, ['ref_id' => ($this->props['data'][0]['node_id'] | 0)]); ?>');"><?= Icons::get(IconNames::ADD); ?>
		<?= e($this->strings['user.role.new']) ?>
	</button>
	<br/>
	<br/>

</div>

<hr>

<?= $this->fetchContent('help'); ?>
