<?php

declare(strict_types=1);

final class WidgetPlacementService
{
	/**
	 * @return list<array{
	 *     id: string,
	 *     label: string,
	 *     label_key: string,
	 *     items: list<array<string, mixed>>
	 * }>
	 */
	public function getGroupedPalette(WidgetPlacementContext $context, bool $include_disabled = true): array
	{
		$surface_groups = Widget::getPaletteWidgetNamesBySurfaceAndGroup($context->surface);
		$group_label_keys = WidgetAuthoringPolicy::groupLabelKeys();
		$used_once_per_domain = $this->getUsedOncePerDomainWidgetNameSet($context);
		$groups = [];

		foreach (WidgetAuthoringPolicy::groupOrder() as $group_id) {
			$widget_names = $surface_groups[$group_id] ?? [];
			$items = [];

			foreach ($widget_names as $widget_name) {
				$decision = $this->canPlace($widget_name, $context, $used_once_per_domain);

				if (!$decision->isAllowed() && !$include_disabled) {
					continue;
				}

				if (!$decision->isAllowed() && $decision->code() !== 'WIDGET_PLACEMENT_ONCE_PER_DOMAIN') {
					continue;
				}

				$items[] = $this->buildPaletteItem($widget_name, $group_id, $decision);
			}

			if ($items === []) {
				continue;
			}

			$label_key = $group_label_keys[$group_id] ?? 'widget.group.content';
			$groups[] = [
				'id' => $group_id,
				'label' => t($label_key),
				'label_key' => $label_key,
				'items' => $items,
			];
		}

		return $groups;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function getFlatPaletteItems(WidgetPlacementContext $context, bool $include_disabled = false): array
	{
		$items = [];

		foreach ($this->getGroupedPalette($context, $include_disabled) as $group) {
			foreach ($group['items'] as $item) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * @param array<string, true>|null $used_once_per_domain
	 */
	public function canPlace(string $widget_name, WidgetPlacementContext $context, ?array $used_once_per_domain = null): WidgetPlacementDecision
	{
		if (!Widget::checkWidgetExists($widget_name)) {
			return WidgetPlacementDecision::deny('WIDGET_PLACEMENT_UNKNOWN', 'cms.widget_connection.not_allowed');
		}

		if (!ResourceAcl::canAccessResource($context->pageId, ResourceAcl::_ACL_EDIT)) {
			return WidgetPlacementDecision::deny('WIDGET_PLACEMENT_FORBIDDEN', 'cms.widget_connection.not_allowed');
		}

		$policy = Widget::getWidgetAuthoringPolicy($widget_name);

		if (!WidgetAuthoringPolicy::isManual($policy)) {
			return WidgetPlacementDecision::deny('WIDGET_PLACEMENT_SYSTEM_ONLY', 'cms.widget_connection.not_allowed');
		}

		if (!WidgetAuthoringPolicy::supportsSurface($policy, $context->surface)) {
			return WidgetPlacementDecision::deny('WIDGET_PLACEMENT_WRONG_SURFACE', 'cms.widget_connection.wrong_surface');
		}

		try {
			if (!Widget::factory($widget_name)->getListVisibility()) {
				return WidgetPlacementDecision::deny('WIDGET_PLACEMENT_HIDDEN', 'cms.widget_connection.not_allowed');
			}
		} catch (Throwable) {
			return WidgetPlacementDecision::deny('WIDGET_PLACEMENT_HIDDEN', 'cms.widget_connection.not_allowed');
		}

		if (WidgetAuthoringPolicy::isOncePerDomain($policy)) {
			$used_once_per_domain ??= $this->getUsedOncePerDomainWidgetNameSet($context);

			if (isset($used_once_per_domain[$widget_name])) {
				return WidgetPlacementDecision::deny('WIDGET_PLACEMENT_ONCE_PER_DOMAIN', 'cms.widget_connection.once_per_domain_used');
			}
		}

		return WidgetPlacementDecision::allow();
	}

	/**
	 * @return array<string, true>
	 */
	public function getUsedOncePerDomainWidgetNameSet(WidgetPlacementContext $context): array
	{
		$widget_names = Widget::getOncePerDomainWidgetNames();

		if ($widget_names === [] || $context->domainRootId <= 0) {
			return [];
		}

		$placeholders = implode(', ', array_fill(0, count($widget_names), '?'));
		$stmt = Db::instance()->prepare(
			"SELECT DISTINCT wc.widget_name
			   FROM widget_connections wc
			   JOIN resource_tree page ON page.node_id = wc.page_id
			   JOIN resource_tree root ON root.node_id = ?
			  WHERE page.lft >= root.lft
			    AND page.rgt <= root.rgt
			    AND wc.widget_name IN ({$placeholders})"
		);
		$stmt->execute([
			$context->domainRootId,
			...$widget_names,
		]);

		$used = [];

		foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $widget_name) {
			$used[(string)$widget_name] = true;
		}

		return $used;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildPaletteItem(string $widget_name, string $group_id, WidgetPlacementDecision $decision): array
	{
		$metadata = Widget::getWidgetMetadata($widget_name);
		$message_key = $decision->messageKey();

		return $metadata + [
			'type' => $widget_name,
			'type_name' => $widget_name,
			'label' => (string)($metadata['name'] ?? $widget_name),
			'group' => $group_id,
			'disabled' => !$decision->isAllowed(),
			'disabled_reason_key' => $message_key,
			'disabled_reason' => $message_key !== '' ? t($message_key) : '',
		];
	}
}
