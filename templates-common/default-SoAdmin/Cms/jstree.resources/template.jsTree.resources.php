<?php assert(isset($this) && $this instanceof Template); ?>
<?php $this->setDebugType(TemplateDebugType::DEBUG_HTML); ?>
<?php
$this->registerLibrary('__ADMIN_RESOURCES');

$jstreeId = $this->props['jstree_id'];
$ajaxUrl = Url::getAjaxUrl("jstree.resourcesAjax", ["CKEditor" => Request::_GET("CKEditor")]);
?>
<?php if (Roles::hasRole(RoleList::ROLE_DOMAINS_ADMIN)): ?>
	<a href="<?= form_url(FormList::WEBPAGEDOMAIN); ?>" class="controller-menu"><?= e($this->strings['cms.resource_browser.new_domain']) ?></a>
<?php endif; ?>
<div class="box">
	<div class="dina_content" id="dina_content<?= e($jstreeId); ?>" style="width:230px;float:left;margin:0 0 30px 10px;overflow:auto;">
		<i></i>
	</div>
	<div style="float:left;overflow:auto;width:530px;height:450px;margin-left:10px;">
		<h3><i><?= e($this->strings['cms.resource_browser.site_structure']) ?></i></h3>
		<br/>

		<div id="<?= e($jstreeId); ?>" style="margin:15px 0;"
			 data-controller="jstree-resources"
			 data-jstree-resources-jstree-id-value="<?= e($jstreeId); ?>"
			 data-jstree-resources-ajax-url-value="<?= e($ajaxUrl); ?>Load"
			 data-jstree-resources-move-url-value="<?= e($ajaxUrl); ?>Move"
			 data-jstree-resources-detail-url-value="<?= e($ajaxUrl); ?>DinaContent"
			 data-jstree-resources-delete-url-value="<?= e($ajaxUrl); ?>DeleteRecursive">
		</div>
	</div>
	<br/>
	<br/>
	<div style="clear:both;"></div>
</div>
