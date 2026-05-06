<?php assert(isset($this) && $this instanceof Template); ?>
<?php $this->registerLibrary('__ADMIN_SITE'); ?>
<?php $this->registerLibrary('STIMULUS_LOADER'); ?>
<?php
$lang = (string)($this->props['lang'] ?? 'en');
$site_name = (string)($this->props['site_name'] ?? Config::APP_SITE_NAME->value());
$document_title = (string)($this->props['document_title'] ?? $site_name);
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
	<meta charset="UTF-8">
	<title><?= e($document_title) ?></title>
	<meta name="robots" content="NOINDEX, NOFOLLOW">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?= $this->getRenderer()->getLibraryDebugInfo(); ?>
	<?= $this->getRenderer()->getCss(); ?>
	<?= $this->getRenderer()->getJsTop(); ?>
</head>
<body class="widget-preview">
	<div style="max-width: 1000px; margin: 0 auto; padding: 24px 16px;">
		<?= $this->fetchContent('content'); ?>
	</div>
	<?= $this->fetchContent('page_chrome'); ?>
	<?= $this->getRenderer()->getJs(); ?>
	<script type="text/javascript">
		if (typeof renderSystemMessages === 'function') {
			renderSystemMessages();
		}
	</script>
	<?= $this->getRenderer()->fetchClosingHtml(); ?>
</body>
</html>
