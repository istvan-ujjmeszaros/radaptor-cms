<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$scope = (string)($this->props['scope'] ?? 'widget');
$transport = (string)($this->props['transport'] ?? 'standalone_form');
$items = is_array($this->props['items'] ?? null) ? $this->props['items'] : [];
$target = is_array($this->props['target'] ?? null) ? $this->props['target'] : [];
$insert_url = (string)($this->props['insert_url'] ?? '');
$item_payload_name = (string)($this->props['item_payload_name'] ?? ($scope === 'form' ? 'form_edit_insert' : 'widget_name'));
$button_label = $scope === 'form'
	? (string)($this->strings['form.insert.button'] ?? '')
	: (string)($this->strings['cms.widget.insert.button'] ?? '');
?>
<div class="editor-insert editor-insert--<?= e($scope) ?><?= $scope === 'widget' ? ' widget-insert' : '' ?>">
	<?php if ($scope === 'widget' && !empty($this->props['clipboard'])): ?>
		<a title="<?= e($this->strings['cms.widget.insert_from_clipboard'] ?? '') ?>" href="<?= e((string)($this->props['clipboard_url'] ?? '')) ?>">
			<?= Icons::get(IconNames::WIDGET_INSERT); ?>
		</a>
	<?php endif; ?>

	<div class="editor-insert-dropdown widgetSelector" data-editor-insert-dropdown>
		<button type="button" class="editor-insert-btn widget-add-icon" data-editor-insert-toggle>
			<?= Icons::get($scope === 'widget' ? IconNames::WIDGET_ADD : IconNames::PLUS); ?>
			<span class="editor-insert-text"><?= e($button_label) ?></span>
		</button>
		<ul class="editor-insert-menu">
			<?php foreach ($items as $item): ?>
				<?php if (!is_array($item) || trim((string)($item['type'] ?? '')) === '') {
					continue;
				} ?>
				<li>
					<?php if ($transport === EditorInsertSurfaceBuilder::TRANSPORT_INSIDE_FORM): ?>
						<?php
						$payload = array_replace($target, [
							'field_type' => (string)$item['type'],
							'csrf_token' => (string)($this->props['csrf_token'] ?? ''),
						]);
						$encoded_payload = base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
						$hx_values = json_encode([$item_payload_name => $encoded_payload], JSON_THROW_ON_ERROR);
						?>
						<button type="button"
								class="editor-insert-menu-item"
								name="<?= e($item_payload_name) ?>"
								value="<?= e($encoded_payload) ?>"
								hx-post="<?= e($insert_url) ?>"
								hx-vals="<?= e($hx_values) ?>"
								hx-swap="none">
							<?= e((string)($item['label'] ?? $item['type'])) ?>
						</button>
					<?php else: ?>
						<form method="post" data-controller="form-timezone" action="<?= e($insert_url) ?>" hx-post="<?= e($insert_url) ?>" hx-swap="none">
							<input type="hidden" name="<?= e($item_payload_name) ?>" value="<?= e((string)$item['type']) ?>">
							<button class="submit_button editor-insert-menu-item" type="submit" value="save">
								<?= e((string)($item['label'] ?? $item['type'])) ?>
							</button>
						</form>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>
