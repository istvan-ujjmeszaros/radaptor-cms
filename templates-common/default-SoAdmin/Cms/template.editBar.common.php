<?php assert(isset($this) && $this instanceof Template); ?>
<table class="editBar" hx-boost="false">
		<tr>
			<?php foreach ($this->props['widget_edit_commands'] as $widgetEditCommand): ?>
				<?php if (!is_array($widgetEditCommand)) {
					continue;
				} ?>
				<td><?php //var_dump($widgetEditCommand);?></td>
				<?php $icon = isset($widgetEditCommand['icon']) ? IconNames::tryFrom((string)$widgetEditCommand['icon']) : null; ?>
				<td>
					<a style="display:block;margin-bottom:3px;" title="<?= (string)($widgetEditCommand['title'] ?? ''); ?>" href="<?= (string)($widgetEditCommand['url'] ?? ''); ?>"><?= Icons::get($icon); ?></a>
				</td>
			<?php endforeach; ?>
		<?php if ($this->getWidgetConnection()->getWidget()->defaultEditCommandsAreEnabled()): ?>
			<td>
				<a style="display:block;margin-bottom:3px;" title="<?= e($this->strings['cms.widget_connection_params.title']) ?>" href="<?= form_url(FormList::WIDGETCONNECTIONPARAMS, $this->getWidgetConnection()->connection_id); ?>"><?= Icons::get(IconNames::ALIGN); ?></a>
			</td>
			<?php if ($this->getTheme()->getWidthPossibilities()): ?>
				<td>
					<a style="display:block;margin-bottom:3px;" title="<?= e($this->strings['form.widget_connection_settings.title']) ?>" href="<?= form_url(FormList::WIDGETCONNECTIONSETTINGS, $this->getWidgetConnection()->connection_id); ?>"><?= Icons::get(IconNames::COLUMN_WIDTH); ?></a>
				</td>
			<?php endif; ?>
			<?php if (!$this->getWidgetConnection()->isFirst()): ?>
				<td>
					<a style="display:block;margin-bottom:3px;" title="<?= e($this->strings['common.move_up']) ?>" href="<?= event_url('widgetConnection.swap', [
						'item_id' => $this->getWidgetConnection()->connection_id,
						'swap_id' => $this->getWidgetConnection()->previous()->connection_id,
					]); ?>"><?= Icons::get(IconNames::WIDGET_UP); ?></a>
				</td>
			<?php endif; ?>
			<?php if (!$this->getWidgetConnection()->isLast()): ?>
				<td>
					<a style="display:block;" title="<?= e($this->strings['common.move_down']) ?>" href="<?= event_url('widgetConnection.swap', [
						'item_id' => $this->getWidgetConnection()->connection_id,
						'swap_id' => $this->getWidgetConnection()->next()->connection_id,
					]); ?>"><?= Icons::get(IconNames::WIDGET_DOWN); ?></a>
				</td>
			<?php endif; ?>
			<td>
				<a title="<?= e($this->strings['cms.widget_connection.remove_from_webpage']) ?>" href="<?= event_url('widgetConnection.remove', ['item_id' => $this->getWidgetConnection()->connection_id]); ?>"><?= Icons::get(IconNames::WIDGET_REMOVE); ?></a>
			</td>
		<?php endif; ?>
	</tr>
</table>
