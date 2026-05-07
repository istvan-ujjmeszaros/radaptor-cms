<?php assert(isset($this) && $this instanceof Template); ?>
<?php $this->registerLibrary('JQUERY_UI'); ?>
<?php $this->registerLibrary('QTIP'); ?>
<div id="edit-widget-<?= $this->getWidgetConnection()->connection_id; ?>" class="widget-area<?php if ($this->props['class'] != ''): ?> <?= $this->props['class']; ?><?php endif; ?>"<?php if ($this->props['style'] != ''): ?> style="<?= $this->props['style']; ?>"<?php endif; ?>>
	<div id="edit-widget-toolbar-<?= $this->getWidgetConnection()->connection_id; ?>" class="widget-edit">
		<?= $this->fetchContent('edit_bar'); ?>
	</div>
	<?= $this->fetchContent('widget_content'); ?>
	<div style="clear:both;"></div>
</div>
