<?php

declare(strict_types=1);

final class WidgetPlacementContext
{
	public function __construct(
		public readonly int $pageId,
		public readonly string $slotName,
		public readonly string $path,
		public readonly string $domainContext,
		public readonly int $domainRootId,
		public readonly string $surface,
		public readonly ?int $seq = null,
	) {
	}

	public static function fromPageId(int $page_id, string $slot_name, ?int $seq = null): self
	{
		$resource_data = ResourceTreeHandler::getResourceTreeEntryDataById($page_id);

		if (!is_array($resource_data) || ($resource_data['node_type'] ?? null) !== 'webpage') {
			throw new InvalidArgumentException('Widget placement target page is not a webpage.');
		}

		$path = ResourceTreeHandler::getPathFromId($page_id);
		$domain_context = ResourceTreeHandler::getDomainContextForResourceTreeEntryData($resource_data);
		$domain_root_id = ResourceTreeHandler::getDomainRoot($domain_context) ?? 0;

		return new self(
			$page_id,
			$slot_name,
			$path,
			$domain_context,
			$domain_root_id,
			WidgetAuthoringPolicy::surfaceForPath($path),
			$seq,
		);
	}
}
