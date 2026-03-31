<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$error_string = trim((string)($this->props['error_string'] ?? ''));
$info_string = trim((string)($this->props['info_string'] ?? ''));
$target = (string)($this->props['target'] ?? '');
?>
<?php if ($error_string !== ''): ?>
	<img data-target="#<?= e($target) ?>" class="form-tooltip" src="<?= Icons::path(IconNames::FORM_ERROR); ?>" width="16" height="16" alt="" title="<?= htmlspecialchars($error_string, ENT_QUOTES | ENT_SUBSTITUTE); ?>">
<?php elseif ($info_string !== ''): ?>
	<img data-target="#<?= e($target) ?>" class="form-tooltip" src="<?= Icons::path(IconNames::FORM_HELP); ?>" width="16" height="16" alt="" title="<?= htmlspecialchars($info_string, ENT_QUOTES | ENT_SUBSTITUTE); ?>">
<?php else: ?>
	<?= Icons::placeholder(19, 19); ?>
<?php endif; ?>
