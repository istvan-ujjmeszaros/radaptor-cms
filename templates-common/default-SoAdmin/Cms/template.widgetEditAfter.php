<?php assert(isset($this) && $this instanceof Template); ?>

<?= $this->fetchContent('widget_content'); ?>

<div id="widget-<?= $this->getWidgetConnection()->connection_id; ?>" class="widget-area editable-special-background<?php if ($this->props['class'] != ''): ?> <?= $this->props['class']; ?><?php endif; ?>"<?php if ($this->props['style'] != ''): ?> style="<?= $this->props['style']; ?>"<?php endif; ?>>
	<div id="edit-<?= $this->getWidgetConnection()->connection_id; ?>" class="widget-edit">
		<?= $this->fetchContent('edit_bar'); ?>
	</div>
	<div style="clear:both;"></div>
</div>
