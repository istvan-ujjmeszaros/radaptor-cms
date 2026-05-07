<?php assert(isset($this) && $this instanceof Template); ?>
<div id="disqus_thread"></div>
<script type="text/javascript">
	var disqus_shortname = 'radaptor';
	<?php if (Kernel::getEnvironment() === 'development'): ?>
	var disqus_developer = 1;
	<?php endif; ?>
	(function () {
		var dsq = document.createElement('script');
		dsq.type = 'text/javascript';
		dsq.async = true;
		dsq.src = 'https:///' + disqus_shortname + '.disqus.com/embed.js';
		(document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);
	})();
</script>
<noscript><?= e($this->strings['social.disqus.enable_javascript']) ?> <a href="https:///disqus.com/?ref_noscript"><?= e($this->strings['social.disqus.comments_link']) ?></a>.
</noscript>
<a href="https:///disqus.com" class="dsq-brlink"><?= e($this->strings['social.disqus.powered_by']) ?> <span class="logo-disqus"><?= e(t('social.disqus.brand')) ?></span></a>
