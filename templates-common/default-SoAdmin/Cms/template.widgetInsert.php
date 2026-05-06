<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$this->props['float'] = false;
$this->props['extra_style'] = 'clear:both;';

if (is_object($this->getWidgetConnection()) && $this->getWidgetConnection()->getExtraparam('float')) {
	switch ($this->getWidgetConnection()->getExtraparam('float')) {
		case 'left':
			$this->props['float'] = 'left';

			break;

		case 'right':
			$this->props['float'] = 'right';

			break;
	}

	if ($this->props['float']) {
		$this->props['extra_style'] = 'float: ' . $this->props['float'] . '; width:30px; height:auto;';
	}
}
?>
<?php $this->props['clipboard'] = WidgetConnection::getClipboard(); ?>
<div style="clear:both"></div>
<div class="widget-insert">
	<?php if (!is_null($this->props['clipboard']) && $this->props['clipboard'] !== false): ?>
		<a title="<?= e($this->strings['cms.widget.insert_from_clipboard']) ?>" href="<?= event_url('widgetConnection.add', [
			'pageid' => $this->getPageId(),
			'slot_name' => $this->props['slot_name'],
			'widget_name' => $this->props['clipboard'],
			'seq' => is_object($this->getWidgetConnection()) ? $this->getWidgetConnection()->seq() : null,
		]); ?>"><?= Icons::get(IconNames::WIDGET_INSERT); ?></a>
	<?php endif; ?>
	<?= $this->fetchContent('add_widget_from_list'); ?>
</div>
