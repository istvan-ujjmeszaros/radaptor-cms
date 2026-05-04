<?php

declare(strict_types=1);

final class JsTreeTemplateAdapter3x implements iJsTreeTemplateAdapter
{
	public function getTemplate(): string
	{
		return JsTreeApiService::TEMPLATE_JSTREE_3;
	}

	public function build(string $tree_type, array $raw_data, array $context): array
	{
		switch ($tree_type) {
			case JsTreeApiService::TYPE_ADMINMENU:
				return JsonAdapterJsTree3x::adminMenuTree(
					$raw_data,
					(int) $context['parent_node_id']
				);

			case JsTreeApiService::TYPE_MAINMENU:
				return JsonAdapterJsTree3x::mainMenuTree(
					$raw_data,
					(int) $context['parent_node_id']
				);

			case JsTreeApiService::TYPE_RESOURCES:
				return JsonAdapterJsTree3x::resourceTree(
					$raw_data,
					$context['parent_data'] ?? null
				);

			case JsTreeApiService::TYPE_ROLES:
				if (!empty($context['load_all'])) {
					return JsonAdapterJsTree3x::rolesTreeExpanded($raw_data);
				}

				return JsonAdapterJsTree3x::rolesTree(
					$raw_data,
					(int) $context['parent_node_id']
				);

			case JsTreeApiService::TYPE_USERGROUPS:
				if (!empty($context['load_all'])) {
					return JsonAdapterJsTree3x::usergroupsTreeExpanded($raw_data);
				}

				return JsonAdapterJsTree3x::usergroupsTree(
					$raw_data,
					(int) $context['parent_node_id']
				);

			case JsTreeApiService::TYPE_ROLE_SELECTOR:
				return JsonAdapterJsTree3x::roleSelector(
					$raw_data,
					(string) $context['for_type'],
					(int) $context['for_id']
				);

			case JsTreeApiService::TYPE_USERGROUP_SELECTOR:
				return JsonAdapterJsTree3x::usergroupSelector(
					$raw_data,
					(int) $context['for_id']
				);

			default:
				throw new InvalidArgumentException("Unsupported jsTree type: {$tree_type}");
		}
	}
}
