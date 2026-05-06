<?php assert(isset($this) && $this instanceof Template); ?>
<?php $this->registerLibrary(LibrariesTracker::__WIDEWORKS_TRACK); ?>
<?php header("X-UA-Compatible: IE=Edge"); ?>
<!DOCTYPE html>
<!--[if IE 7]>
<html class="ie7 no-js" lang="<?= substr(Kernel::getLocale(), 0, 2) ?>">     <![endif]-->
<!--[if lte IE 8]>
<html class="ie8 no-js" lang="<?= substr(Kernel::getLocale(), 0, 2) ?>">     <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!-->
<html class="not-ie no-js" lang="<?= substr(Kernel::getLocale(), 0, 2) ?>">  <!--<![endif]-->
<head>
	<meta charset="utf-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

	<title><?= $this->getTitle() ?></title>
	<?php if ($this->getDescription() !== ''): ?>
	<meta name="description" content="<?= e($this->getDescription()) ?>">
	<?php endif; ?>

	<?= $this->getRenderer()->getLibraryDebugInfo(); ?>
	<meta name="author" content=""/>

	<link rel="shortcut" href="favicon.ico"/>
	<?= $this->getRenderer()->getCss(); ?>
	<?= $this->getRenderer()->getJsTop(); ?>

	<link href='https://fonts.googleapis.com/css?family=Oxygen:400,700|Kaushan+Script&subset=latin,latin-ext' rel='stylesheet' type='text/css'>
	<script type="text/javascript">

		var _gaq = _gaq || [];
		var pluginUrl = '//www.google-analytics.com/plugins/ga/inpage_linkid.js';
		_gaq.push(['_require', 'inpage_linkid', pluginUrl]);
		_gaq.push(['_setAccount', 'UA-35042809-1']);
		_gaq.push(['_trackPageview']);

		(function () {
			var ga = document.createElement('script');
			ga.type = 'text/javascript';
			ga.async = true;
			ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
			var s = document.getElementsByTagName('script')[0];
			s.parentNode.insertBefore(ga, s);
		})();

	</script>
</head>
<body>

<div class="wrap-header"></div><!--/ .wrap-header-->

<div class="wrap">

	<!-- ***************** - Header - ***************** -->

	<header id="header">

		<div class="container clearfix">

			<span id="logo"><img src="<?= Config::PATH_CDN->value(); ?>teamsolution.hu/images/logo-orange.png"><a href="/">TeamSolution</a></span>

			<?= $this->fetchContent('main_menu'); ?>
		</div>

	</header><!--/ #header -->

	<!-- ***************** -/ end Header - ***************** -->

</div><!--/ .wrap-->

<!-- ***************** - Slider - ***************** -->

<div class="slider-wrapper">

	<div class="TB_Wrapper">

		<div class="Slides">

			<div class="Slide" data-position="50,190" data-width="470" data-height="60">
				<img src="/images-slider/img01.jpg" alt="img1"/>
				<div class="SlideText-1">
					<h1><?= e($this->strings['layout.public_2row.hero.self_discovery']) ?></h1>
				</div>
			</div><!--/ .Slide-->

			<div class="Slide" data-position="400,15" data-width="560" data-height="60">
				<img src="/images-slider/img02.jpg" alt="img2"/>
				<div class="SlideText-1">
					<h1><?= e($this->strings['layout.public_2row.hero.equal_partner']) ?></h1>
				</div>
			</div><!--/ .Slide-->

			<div class="Slide" data-position="390,20" data-width="370" data-height="60">
				<img src="/images-slider/img03.jpg" alt="img3"/>
				<div class="SlideText-1">
					<h1><?= e($this->strings['layout.public_2row.hero.better_quality_of_life']) ?></h1>
				</div>
			</div><!--/ .Slide-->

			<div class="Slide" data-position="400,270" data-width="500" data-height="60">
				<img src="/images-slider/img04.jpg" alt="img4"/>
				<div class="SlideText-3">
					<h1><?= e($this->strings['layout.public_2row.hero.balanced_life']) ?></h1>
				</div>
			</div><!--/ .Slide-->

			<div class="Slide" data-position="290,290" data-width="460" data-height="60">
				<img src="/images-slider/img05.jpg" alt="img5"/>
				<div class="SlideText-1">
					<h1><?= e($this->strings['layout.public_2row.hero.conflict_management']) ?></h1>
				</div>
			</div><!--/ .Slide-->

			<div class="Slide" data-position="350,70" data-width="400" data-height="60">
				<img src="/images-slider/img06.jpg" alt="img6"/>
				<div class="SlideText-1">
					<h1><?= e($this->strings['layout.public_2row.hero.success_and_happiness']) ?></h1>
				</div>
			</div><!--/ .Slide-->
			<div class="Slide" data-position="20,200" data-width="340" data-height="60">
				<img src="/images-slider/img07.jpg" alt="img7"/>
				<div class="SlideText-3">
					<h1><?= e($this->strings['layout.public_2row.hero.solution']) ?></h1>
				</div>
			</div><!--/ .Slide-->
		</div><!--End of Slides -->

	</div><!--End of TB_Wrapper -->


</div><!--/ .slider-wrapper-->

<!-- *************** -/ end Slider - *************** -->

<div class="wrap">

	<?php //new LayoutComponentHeaderLine($this->getView());?>

	<!-- ***************** - Container - ***************** -->

	<section class="container clearfix">

		<?= $this->fetchContent('content'); ?>

	</section><!--/ .container -->

	<!-- ***************** - end Container - ***************** -->


	<!-- ***************** - Footer - ***************** -->

	<footer id="footer">

		<div class="adjective clearfix">
			<?php //new LayoutComponentFooter($this->getView());?>

		</div><!--/ .adjective-->

	</footer><!--/ #footer -->

	<!-- ***************** - end Footer - ***************** -->

</div><!--/ #wrap-->

<!--[if lt IE 9]>
    <script src="<?= Config::PATH_CDN->value(); ?>teamsolution.hu/js/selectivizr-and-extra-selectors.min.js"></script>
	<script src="https://ie7-js.googlecode.com/svn/version/2.1(beta4)/IE8.js"></script>
<![endif]-->

<?= $this->fetchContent('page_chrome'); ?>
<?= $this->getRenderer()->getJs(); ?>
<script src="<?= Config::PATH_CDN->value(); ?>teamsolution.hu/js/custom.js"></script>

<!--/body>
</html-->

<?php if (SystemMessages::countSystemMessages() > 0): ?>
	<script type="text/javascript">
		if (typeof renderSystemMessages === 'function') {
			renderSystemMessages();
		}
	</script>
<?php endif; ?>
<?= $this->getRenderer()->fetchClosingHtml(); ?>
