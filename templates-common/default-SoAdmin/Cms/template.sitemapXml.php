<?php assert(isset($this) && $this instanceof Template); ?>
<?= '<?xml version="1.0" encoding="UTF-8"?>' ?>

<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
		xmlns:image="http://www.sitemaps.org/schemas/sitemap-image/1.1"
		xmlns:video="http://www.sitemaps.org/schemas/sitemap-video/1.1">
	<?php foreach ($this->props['urls'] as $url): ?>
		<url>
			<loc><?= $this->props['host'] . $url; ?></loc>
		</url>
	<?php endforeach; ?>
</urlset>
