<?php assert(isset($this) && $this instanceof Template); ?>
<?php $adminmenu = $this->props['data'][0] ?? false; ?>

<h3><?= is_array($adminmenu) ? $adminmenu['node_name'] : e($this->strings['admin.menu.admin_menu']); ?></h3>
<br/>

<div class="buttons">

	<?php if (is_array($adminmenu)): ?>
		<button class="button right_img button_new_submenu" type="button" onclick="widgettype._.location('<?= Form::getSeoUrl(FormList::ADMINMENUMENUELEMENT, null, null, ['ref_id' => $adminmenu['node_id']]); ?>');"><?= Icons::get(IconNames::PLUS); ?>
			<?= e($this->strings['cms.menu.new_item']) ?>
		</button>
	<?php else: ?>
		<button class="button right_img button_new_submenu" type="button" onclick="widgettype._.location('<?= Form::getSeoUrl(FormList::ADMINMENUMENUELEMENT, null, null, ['ref_id' => 0]); ?>');"><?= Icons::get(IconNames::PLUS); ?>
			<?= e($this->strings['cms.menu.new_item']) ?>
		</button>
	<?php endif; ?>
	<br/>
	<br/>

</div>

<hr>
<br/>
