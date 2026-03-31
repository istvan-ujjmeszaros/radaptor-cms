<?php assert(isset($this) && $this instanceof Template); ?>
<?php if ($this->props['isFirstCall']): ?>
	<div id="fb-root"></div>
	<script>
		(function (d, s, id) {
			var js, fjs = d.getElementsByTagName(s)[0];
			if (d.getElementById(id)) return;
			js = d.createElement(s);
			js.id = id;
			js.src = "//connect.facebook.net/hu_HU/all.js#xfbml=1";
			fjs.parentNode.insertBefore(js, fjs);
		}(document, 'script', 'facebook-jssdk'));

		window.___gcfg = {lang: 'hu'};
		(function () {
			var po = document.createElement('script');
			po.type = 'text/javascript';
			po.async = true;
			po.src = 'https://apis.google.com/js/plusone.js';
			var s = document.getElementsByTagName('script')[0];
			s.parentNode.insertBefore(po, s);
		})();
	</script>
<?php endif; ?>

<div class="rt-container">
	<div class="rt-grid-12 ">
		<div class="social">
			<span class="social-fb"><div class="fb-like" data-send="true" data-layout="button_count" data-show-faces="true" data-colorscheme="light"></div></span>
			<span class="social-g"><div class="g-plusone" data-size="medium" data-annotation="inline" data-width="120"></div></span>
		</div>
	</div>
	<!--div class="clear"></div-->
</div>
