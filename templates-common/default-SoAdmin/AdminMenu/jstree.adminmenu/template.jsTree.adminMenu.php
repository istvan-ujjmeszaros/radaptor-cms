<?php assert(isset($this) && $this instanceof Template); ?>
<?php $this->setDebugType(TemplateDebugType::DEBUG_HTML); ?>
<?php
$this->registerLibrary('__ADMIN_ADMINMENU');

$jstreeId = $this->props['jstree_id'];
$ajaxUrl = Url::getAjaxUrl("jstree.adminMenuAjax");
?>
<div class="box">
	<div class="dina_content" id="dina_content<?= e($jstreeId); ?>" style="width:230px;float:left;margin:0 0 30px 10px;overflow:auto;">
		<i></i>
	</div>
	<div style="float:left;overflow:auto;width:530px;height:450px;margin-left:10px;">
		<h3><i><?= e($this->strings['admin.menu.admin_menu']) ?></i></h3>
		<br/>

		<div id="<?= e($jstreeId); ?>" style="margin:15px 0;"
			 data-controller="jstree-adminmenu"
			 data-jstree-adminmenu-jstree-id-value="<?= e($jstreeId); ?>"
			 data-jstree-adminmenu-ajax-url-value="<?= e($ajaxUrl); ?>Load"
			 data-jstree-adminmenu-move-url-value="<?= e($ajaxUrl); ?>Move"
			 data-jstree-adminmenu-detail-url-value="<?= e($ajaxUrl); ?>DinaContent"
			 data-jstree-adminmenu-delete-url-value="<?= e($ajaxUrl); ?>DeleteRecursive">
		</div>
	</div>
	<br/>
	<br/>
	<div style="clear:both;"></div>
</div>
