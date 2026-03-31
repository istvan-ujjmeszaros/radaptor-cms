<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$field_refs = is_array($this->props['field_refs'] ?? null) ? $this->props['field_refs'] : [];

$template = new Template('sdui.form', $this->getRenderer(), $this->getWidgetConnection());
$template->props = array_replace($this->props, [
	'html_attributes' => [
		'data-controller' => 'form-timezone menu-link-type',
		'data-menu-link-type-input-name-value' => (string)($field_refs['type']['name'] ?? ''),
		'data-menu-link-type-url-row-id-value' => (string)($field_refs['url']['row_id'] ?? ''),
		'data-menu-link-type-page-row-id-value' => (string)($field_refs['page_id']['row_id'] ?? ''),
		'data-menu-link-type-internal-value' => 'belso',
	],
]);
$template->setSlots([
	'hidden_fields' => $this->fetchSlot('hidden_fields'),
	'rows' => $this->fetchSlot('rows'),
]);
?>
<?= $template->fetch() ?>
