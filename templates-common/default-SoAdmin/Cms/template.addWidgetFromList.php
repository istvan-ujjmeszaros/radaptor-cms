<?php assert(isset($this) && $this instanceof Template); ?>
<?php $this->registerLibrary('WIDGET_EDIT'); ?>
<?php $this->registerLibrary('QTIP'); ?>
<?php
$visibleWidgets = is_array($this->props['visibleWidgets'] ?? null) ? $this->props['visibleWidgets'] : [];
$widgetGroups = is_array($this->props['widgetGroups'] ?? null) ? $this->props['widgetGroups'] : [];
$renderGroups = $widgetGroups !== [] ? $widgetGroups : [[
	'id' => '',
	'label' => '',
	'items' => $visibleWidgets,
]];
?>

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
				<?php foreach ($renderGroups as $group): ?>
					<?php
					$group_items = is_array($group['items'] ?? null) ? $group['items'] : [];

					if ($group_items === []) {
						continue;
					}
					$group_label = trim((string)($group['label'] ?? ''));
					?>
					<?php if ($group_label !== ''): ?>
						<optgroup label="<?= e($group_label) ?>">
					<?php endif; ?>
					<?php foreach ($group_items as $widget): ?>
						<?php if (!is_array($widget) || empty($widget['type_name'])) {
							continue;
						} ?>
						<?php
						$disabled = (bool)($widget['disabled'] ?? false);
						$disabled_reason = (string)($widget['disabled_reason'] ?? '');
						?>
						<option value="<?= e((string)$widget['type_name']); ?>" class="tooltip-widget-selector" data-id="1" <?= $disabled ? 'disabled title="' . e($disabled_reason) . '"' : '' ?>><?= e((string)($widget['name'] ?? $widget['label'] ?? $widget['type_name'])); ?></option>
					<?php endforeach; ?>
					<?php if ($group_label !== ''): ?>
						</optgroup>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
		</div>
		<button class="submit_button" type="submit" value="save"><?= e($this->strings['cms.widget.insert.button']) ?></button>
	</form>
</div>
