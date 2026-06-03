<?php assert(isset($this) && $this instanceof Template); ?>
<?php $edit_mode_hx_swap = 'none show:none focus-scroll:false'; ?>
<?php $editmode_readonly_notice = trim((string)($this->props['editmode_readonly_notice'] ?? '')); ?>
<?php if ($editmode_readonly_notice !== ''): ?>
	<div class="editBar-readonly-notice" role="note">
		<?= Icons::get(IconNames::INFO, $editmode_readonly_notice) ?>
		<span><?= e($editmode_readonly_notice) ?></span>
	</div>
<?php endif; ?>
<table class="editBar" hx-boost="false">
		<tr>
			<?php foreach ($this->props['widget_edit_commands'] as $widgetEditCommand): ?>
				<?php if (!is_array($widgetEditCommand)) {
					continue;
				} ?>
				<td><?php //var_dump($widgetEditCommand);?></td>
				<?php $icon = isset($widgetEditCommand['icon']) ? IconNames::tryFrom((string)$widgetEditCommand['icon']) : null; ?>
				<?php
				$method = strtolower((string)($widgetEditCommand['method'] ?? 'get'));
				$payload = is_array($widgetEditCommand['payload'] ?? null) ? $widgetEditCommand['payload'] : [];
				$hx_vals = $payload !== [] ? json_encode($payload, JSON_THROW_ON_ERROR) : '';
				$properties_url = trim((string)($widgetEditCommand['properties_url'] ?? ''));
				?>
				<td>
					<?php if ($method === 'post'): ?>
						<button
							type="button"
							style="display:block;margin-bottom:3px;"
							title="<?= e((string)($widgetEditCommand['title'] ?? '')); ?>"
							hx-post="<?= e((string)($widgetEditCommand['url'] ?? '')); ?>"
							hx-swap="<?= e($edit_mode_hx_swap) ?>"
							<?= $hx_vals !== '' ? 'hx-vals="' . e($hx_vals) . '"' : '' ?>
							<?= !empty($widgetEditCommand['loader']) ? 'data-edit-mode-command' : '' ?>
						><?= Icons::get($icon); ?></button>
					<?php else: ?>
						<a style="display:block;margin-bottom:3px;" title="<?= e((string)($widgetEditCommand['title'] ?? '')); ?>" href="<?= e((string)($widgetEditCommand['url'] ?? '')); ?>" <?= $properties_url !== '' ? 'data-page-editor-properties-url="' . e($properties_url) . '"' : '' ?>><?= Icons::get($icon); ?></a>
					<?php endif; ?>
				</td>
			<?php endforeach; ?>
			<?php if ($this->getWidgetConnection()->getWidget()->defaultEditCommandsAreEnabled()): ?>
				<td>
					<a style="display:block;margin-bottom:3px;" title="<?= e($this->strings['cms.widget_connection_params.title']) ?>" href="<?= form_url(FormList::WIDGETCONNECTIONPARAMS, $this->getWidgetConnection()->connection_id); ?>" data-page-editor-properties-url="<?= e(Form::getEditorFragmentUrl(FormList::WIDGETCONNECTIONPARAMS, $this->getWidgetConnection()->connection_id)); ?>"><?= Icons::get(IconNames::ALIGN); ?></a>
				</td>
				<?php if ($this->getTheme()->getWidthPossibilities()): ?>
					<td>
						<a style="display:block;margin-bottom:3px;" title="<?= e($this->strings['form.widget_connection_settings.title']) ?>" href="<?= form_url(FormList::WIDGETCONNECTIONSETTINGS, $this->getWidgetConnection()->connection_id); ?>" data-page-editor-properties-url="<?= e(Form::getEditorFragmentUrl(FormList::WIDGETCONNECTIONSETTINGS, $this->getWidgetConnection()->connection_id)); ?>"><?= Icons::get(IconNames::COLUMN_WIDTH); ?></a>
					</td>
				<?php endif; ?>
			<?php if (!$this->getWidgetConnection()->isFirst()): ?>
				<td>
					<a style="display:block;margin-bottom:3px;" title="<?= e($this->strings['common.move_up']) ?>" href="<?= event_url('widgetConnection.swap', [
						'item_id' => $this->getWidgetConnection()->connection_id,
						'swap_id' => $this->getWidgetConnection()->previous()->connection_id,
					]); ?>" hx-get="<?= event_url('widgetConnection.swap', [
						'item_id' => $this->getWidgetConnection()->connection_id,
						'swap_id' => $this->getWidgetConnection()->previous()->connection_id,
					]); ?>" hx-swap="<?= e($edit_mode_hx_swap) ?>" data-edit-mode-command><?= Icons::get(IconNames::WIDGET_UP); ?></a>
				</td>
			<?php endif; ?>
			<?php if (!$this->getWidgetConnection()->isLast()): ?>
				<td>
					<a style="display:block;" title="<?= e($this->strings['common.move_down']) ?>" href="<?= event_url('widgetConnection.swap', [
						'item_id' => $this->getWidgetConnection()->connection_id,
						'swap_id' => $this->getWidgetConnection()->next()->connection_id,
					]); ?>" hx-get="<?= event_url('widgetConnection.swap', [
						'item_id' => $this->getWidgetConnection()->connection_id,
						'swap_id' => $this->getWidgetConnection()->next()->connection_id,
					]); ?>" hx-swap="<?= e($edit_mode_hx_swap) ?>" data-edit-mode-command><?= Icons::get(IconNames::WIDGET_DOWN); ?></a>
				</td>
			<?php endif; ?>
			<td>
				<a title="<?= e($this->strings['cms.widget_connection.remove_from_webpage']) ?>" href="<?= event_url('widgetConnection.remove', ['item_id' => $this->getWidgetConnection()->connection_id]); ?>" hx-get="<?= event_url('widgetConnection.remove', ['item_id' => $this->getWidgetConnection()->connection_id]); ?>" hx-swap="<?= e($edit_mode_hx_swap) ?>" hx-confirm="<?= e($this->strings['cms.widget_connection.remove_from_webpage']) ?>" data-edit-mode-command data-edit-mode-confirm data-edit-mode-confirm-title="<?= e($this->strings['common.delete']) ?>" data-edit-mode-confirm-message="<?= e($this->strings['cms.widget_connection.remove_from_webpage']) ?>" data-edit-mode-confirm-label="<?= e($this->strings['common.delete']) ?>" data-edit-mode-cancel-label="<?= e($this->strings['common.cancel']) ?>"><?= Icons::get(IconNames::WIDGET_REMOVE); ?></a>
			</td>
		<?php endif; ?>
	</tr>
</table>
