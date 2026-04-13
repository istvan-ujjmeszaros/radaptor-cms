<?php assert(isset($this) && $this instanceof Template); ?>
<?php $this->registerLibrary('WIDGET_EDIT'); ?>
<?php $this->registerLibrary('QTIP'); ?>

<a href="" class="widget-add-icon"><?= Icons::get(IconNames::WIDGET_ADD, $this->strings['cms.widget.insert.icon_title']); ?></a>
<div class="widgetSelector">
	<form method="post" data-controller="form-timezone" action="<?= event_url('widgetConnection.add', [
		'pageid' => $this->getPageId(),
		'slot_name' => $this->props['slot_name'],
		'seq' => is_object($this->getWidgetConnection()) ? $this->getWidgetConnection()->seq() : null,
	]); ?>">
		<div class="combobox-holder">
			<select name="widget_name">
				<option value=""><?= e($this->strings['cms.widget.insert.placeholder']) ?></option>
				<?php foreach ($this->props['visibleWidgets'] as $widget): ?>
					<?php if (!is_array($widget) || empty($widget['type_name'])) {
						continue;
					} ?>
					<option value="<?= (string)$widget['type_name']; ?>" class="tooltip-widget-selector" data-id="1"><?= (string)($widget['name'] ?? $widget['type_name']); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<button class="submit_button" type="submit" value="save"><?= e($this->strings['cms.widget.insert.button']) ?></button>
	</form>
</div>
