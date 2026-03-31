<?php assert(isset($this) && $this instanceof Template); ?>
<?php $this->registerLibrary(LibrariesSoAdmin::__ADMIN_SITE); ?>
<?php
$lang = (string)($this->props['lang'] ?? substr(Kernel::getLocale(), 0, 2));
$site_name = (string)($this->props['site_name'] ?? Config::APP_SITE_NAME->value());
$administration_label = (string)($this->props['administration_label'] ?? '');
$document_title = (string)($this->props['document_title'] ?? ($administration_label !== '' ? $administration_label . ' - ' . $site_name : $site_name));
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

	<script src="//cdnjs.cloudflare.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>

	<?= $this->getRenderer()->getJsTop(); ?>
	<?= $this->getRenderer()->getJs(); ?>
	<!--link rel="stylesheet" href="debug.css" type="text/css"-->

</head>
<body>
<?= $this->getRenderer()->fetchInnerHtml(); ?>
<div id="container">
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
	renderSystemMessages();
</script>
<?= $this->fetchSlot('page_chrome'); ?>
<?= $this->getRenderer()->fetchClosingHtml(); ?>
