<?php assert(isset($this) && $this instanceof Template); ?>
<?php $this->setDebugType(TemplateDebugType::DEBUG_HTML); ?>
<?php
$this->registerLibrary('__ADMIN_ROLE_SELECTOR');

$jstreeId = $this->props['jstree_id'];
$baseUrl = Url::getAjaxUrl("jstree.{$this->props['jstree_type']}Ajax", [
	"for_type" => $this->props['selectorType'],
	"for_id" => $this->props['selectorId'],
]);
$ajaxUrl = $baseUrl . 'Load';
$addUrl = $baseUrl . 'AddRole';
$removeUrl = $baseUrl . 'RemoveRole';
?>
<div class="box">
	<h2><?= e($this->strings['user.role_selector.title']) ?></h2>
	<h3><i>[<?= $this->props['title']; ?>]</i></h3>
	<div class="dina_content" id="dina_content<?= e($jstreeId); ?>" style="width:230px;float:left;margin:0 0 30px 10px;overflow:auto;">
		<i></i>
	</div>
	<div style="float:left;overflow:auto;width:530px;height:90%;margin-left:10px;">
		<div id="<?= e($jstreeId); ?>" style="margin:15px 0;"
			 data-controller="jstree-role-selector"
			 data-jstree-role-selector-ajax-url-value="<?= e($ajaxUrl); ?>"
			 data-jstree-role-selector-add-url-value="<?= e($addUrl); ?>"
			 data-jstree-role-selector-remove-url-value="<?= e($removeUrl); ?>">
		</div>
	</div>
	<br/>
	<br/>
	<div style="clear:both;"></div>
</div>
