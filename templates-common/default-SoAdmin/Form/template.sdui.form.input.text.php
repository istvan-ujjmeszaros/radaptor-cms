<?php assert(isset($this) && $this instanceof Template); ?>
<?php $readonly = (bool)($this->props['readonly'] ?? false); ?>
<label for="<?= e((string)$this->props['id']) ?>"<?= (string)($this->props['label_style_attr'] ?? '') ?>><?= e((string)($this->props['label'] ?? '')) ?></label>
<?= $this->fetchSlot('helper') ?>
<input id="<?= e((string)$this->props['id']) ?>"<?= (string)($this->props['input_style_attr'] ?? '') ?> type="text" name="<?= e((string)$this->props['name']) ?>" value="<?= e((string)($this->props['value'] ?? '')) ?>"<?= $readonly ? ' readonly="readonly" tabindex="-1"' : '' ?>>
<?php if ((string)($this->props['autocomplete_url'] ?? '') !== ''): ?>
	<script>
		<?php if ((string)($this->props['connected_autocomplete_fieldname'] ?? '') !== ''): ?>
		$("#<?= e((string)($this->props['connected_autocomplete_row_id'] ?? '')) ?>").hide();
		<?php endif; ?>

		$("#<?= e((string)$this->props['id']) ?>")
			.bind("keydown", function (event) {
				if (event.keyCode === $.ui.keyCode.TAB &&
					$(this).data("ui-autocomplete").menu.active) {
					event.preventDefault();
				}
			})
			<?php if ((string)($this->props['connected_autocomplete_fieldname'] ?? '') !== ''): ?>
			.bind("keyup", function (event) {
				if (event.keyCode !== $.ui.keyCode.ENTER)
					$("#<?= e((string)($this->props['connected_autocomplete_input_id'] ?? '')) ?>").val('_' + this.value + '_')
			})
			<?php endif; ?>
			.autocomplete({
				source: function (request, response) {
					$.getJSON("<?= e((string)($this->props['autocomplete_url'] ?? '')) ?>", {
						term: extractLast(request.term)
					}, response);
				},
				search: function () {
					var term = extractLast(this.value);
				},
				focus: function () {
					return false;
				},
				select: function (event, ui) {
					$("#<?= e((string)$this->props['id']) ?>").val(stripTags(ui.item.label));
					<?php if ((string)($this->props['connected_autocomplete_fieldname'] ?? '') !== ''): ?>
					$("#<?= e((string)($this->props['connected_autocomplete_input_id'] ?? '')) ?>").val(ui.item.value);
					<?php endif; ?>
					return false;
				}
			})
			.after('<span class="select-downarrow"><?= Icons::get(IconNames::DROPDOWN); ?></span>')
			.data("ui-autocomplete")._renderItem = function (ul, item) {
			item.label = item.label.replace(new RegExp("(?![^&;]+;)(?!<[^<>]*)(" + $.ui.autocomplete.escapeRegex($.trim(this.term)) + ")(?![^<>]*>)(?![^&;]+;)", "gi"), "<strong>$1</strong>");
			return $("<li></li>")
				.data("item.autocomplete", item)
				.append("<a>" + item.label + "</a>")
				.appendTo(ul);
		};
	</script>
<?php endif; ?>
