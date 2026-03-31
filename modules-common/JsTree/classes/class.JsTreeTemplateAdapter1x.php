<?php

declare(strict_types=1);

final class JsTreeTemplateAdapter1x implements iJsTreeTemplateAdapter
{
	public function getTemplate(): string
	{
		return JsTreeApiService::TEMPLATE_JSTREE_1;
	}

	public function build(string $tree_type, array $raw_data, array $context): array
	{
		$id_prefix = (string) ($context['id_prefix'] ?? '');

		switch ($tree_type) {
			case JsTreeApiService::TYPE_ADMINMENU:
				return JsonAdapterJsTree1x::adminMenuTree(
					$raw_data,
					$id_prefix,
					(int) $context['parent_node_id']
				);

			case JsTreeApiService::TYPE_MAINMENU:
				return JsonAdapterJsTree1x::mainMenuTree(
					$raw_data,
					$id_prefix,
					(int) $context['parent_node_id']
				);

			case JsTreeApiService::TYPE_RESOURCES:
				return JsonAdapterJsTree1x::resourceTree(
					$raw_data,
					$context['parent_data'] ?? null,
					$id_prefix
				);

			case JsTreeApiService::TYPE_ROLES:
				return JsonAdapterJsTree1x::rolesTree(
					$raw_data,
					$id_prefix,
					(int) $context['parent_node_id']
				);

			case JsTreeApiService::TYPE_USERGROUPS:
				return JsonAdapterJsTree1x::usergroupsTree(
					$raw_data,
					$id_prefix,
					(int) $context['parent_node_id']
				);

			case JsTreeApiService::TYPE_ROLE_SELECTOR:
				return JsonAdapterJsTree1x::roleSelector(
					$raw_data,
					(string) $context['for_type'],
					(int) $context['for_id']
				);

			case JsTreeApiService::TYPE_USERGROUP_SELECTOR:
				return JsonAdapterJsTree1x::usergroupSelector(
					$raw_data,
					(int) $context['for_id']
				);

			default:
				throw new InvalidArgumentException("Unsupported jsTree type: {$tree_type}");
		}
	}
}
