<?php assert(isset($this) && $this instanceof Template); ?>
<?php $this->registerLibrary('__ADMIN_SITE'); ?>
<?php
$lang = (string)($this->props['lang'] ?? substr(Kernel::getLocale(), 0, 2));
$administration_string = (string)($this->strings['admin.menu.section.administration'] ?? '');
$site_name = (string)($this->props['site_name'] ?? Config::APP_SITE_NAME->value());
$document_title = (string)($this->props['document_title'] ?? trim($administration_string . ' - ' . $site_name, ' -'));
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
	<meta charset="utf-8">
	<title><?= e($document_title) ?></title>
	<meta name="robots" content="NOINDEX, NOFOLLOW">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<link rel="shortcut icon" href="/favicon.ico">
	<link rel="icon" href="/favicon.ico" type="image/x-icon">
	<meta name="viewport" content="width=device-width">
	<?= $this->getRenderer()->getLibraryDebugInfo(); ?>
	<?= $this->getRenderer()->getCss(); ?>

	<?= $this->getRenderer()->getJsTop(); ?>
	<?= $this->getRenderer()->getJs(); ?>
	<!--link rel="stylesheet" href="debug.css" type="text/css"-->

</head>
<body>
<?= $this->getRenderer()->fetchInnerHtml(); ?>
<div id="container">
	<!-- TOPMENU -->
	<div id="topmenu-container">
		<div id="topmenu">
		</div>
	</div>
	<!-- /TOPMENU -->
	<!-- HEADER -->
		<div id="header">
		<div id="logo"><a href="/"><?= e($site_name) ?></a></div>
		<div class="cleaner"></div>
	</div>
	<!-- /HEADER -->
	<!-- CONTENT -->
	<div class="content">
		<div class="content-full">
			<?= $this->fetchSlot('content'); ?>
		</div>
	</div>
	<!-- /CONTENT -->
	<br class="cleaner">
</div>
<script type="text/javascript">
	if (typeof renderSystemMessages === 'function') {
		renderSystemMessages();
	}
</script>
<?= $this->fetchSlot('page_chrome'); ?>
<?= $this->getRenderer()->fetchClosingHtml(); ?>
