<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$this->registerLibrary('QTIP');
$this->registerLibrary('JQUERY_COLOR');
?>
<div class="subheader">
	<h1><?= e($this->strings['user.description.title']) ?> #<?= $this->props['userData']['user_id'], ', ', $this->props['userData']['username']; ?></h1>
	<br class="cleaner">
</div>
<?php if (Roles::hasRole(RoleList::ROLE_USERS_ADMIN)): ?>
	<p>
		<a href="<?= form_url(FormList::USER, $this->props['userData']['user_id']); ?>" class="controller-menu"><?= e($this->strings['user.description.action.edit']) ?></a><span></span>
	</p>
<?php endif; ?>
