<?php

class EventJstreeMainMenuAjaxDinaContent extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->hasRole(RoleList::ROLE_CONTENT_ADMIN)
			|| $policyContext->principal->hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		$allowed_types = [
			'root',
			'submenu',
			'_multiple_',
		];

		try {
			$id = Request::postRequired('id');
			$type = Request::postRequired('type');
			$jstree_id = Request::postRequired('jstree_id');
		} catch (RequestParamException $e) {
			http_response_code(400);
			echo "<!-- {$e->getMessage()} -->";

			return;
		}

		if (!in_array($type, $allowed_types)) {
			http_response_code(400);
			echo '<!-- ' . t('cms.tree.unknown_type', ['type' => $type]) . ' -->';

			return;
		}

		$themeName = Themes::getThemeNameFromReferer();
		JsTreeApiService::renderDinaComponent("jsTree.dina_content.mainMenu.{$type}", [
			'id' => $id,
			'type' => $type,
			'jstree_id' => $jstree_id,
		], $themeName, [], JsTreeApiService::buildMainMenuStrings());
	}
}
