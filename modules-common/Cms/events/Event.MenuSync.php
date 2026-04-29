<?php

declare(strict_types=1);

class EventMenuSync extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)
			? PolicyDecision::allow()
			: PolicyDecision::deny('system developer role required');
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'menu.sync',
			'group' => 'CMS Authoring',
			'name' => 'Sync menu entries',
			'summary' => 'Reconciles flat root-level main or admin menu entries.',
			'description' => 'Creates or updates root-level menu entries by title and optionally prunes root-level entries not present in the payload.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('type', 'body', 'string', true, 'Menu type: main or admin.'),
					BrowserEventDocumentationHelper::param('items', 'body', 'json-array', true, 'Ordered root-level menu items.'),
					BrowserEventDocumentationHelper::param('prune', 'body', 'bool', false, 'Delete root-level entries not present in the payload.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns created, updated, and pruned menu entries.',
			],
			'authorization' => [
				'visibility' => 'role',
				'description' => 'Requires system developer role.',
			],
			'mcp' => [
				'enabled' => true,
				'tool_name' => 'radaptor.menu.sync',
				'risk' => 'write',
			],
			'notes' => [],
			'side_effects' => BrowserEventDocumentationHelper::lines('Creates, updates, moves, and optionally deletes root-level menu rows.'),
		];
	}

	public function run(): void
	{
		$type = trim((string) Request::_POST('type', ''));
		$items = Request::_POST('items', null);
		$prune = filter_var(Request::_POST('prune', false), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;

		if ($type === '') {
			ApiResponse::renderError('MISSING_TYPE', 'type is required.', 400);

			return;
		}

		if ($items === null) {
			ApiResponse::renderError('MISSING_ITEMS', 'items is required.', 400);

			return;
		}

		if (!is_array($items)) {
			ApiResponse::renderError('INVALID_ITEMS', 'items must be an array.', 400);

			return;
		}

		try {
			$type = CmsMenuService::normalizeType($type);
			$existing_by_title = self::rootItemsByTitle($type);
			$seen_titles = [];
			$created = [];
			$updated = [];

			foreach (array_values($items) as $position => $item) {
				if (!is_array($item)) {
					throw new InvalidArgumentException('Each menu item must be an object.');
				}

				$title = trim((string) ($item['title'] ?? ''));
				$page_path = trim((string) ($item['page_path'] ?? ''));
				$url = trim((string) ($item['url'] ?? ''));

				if ($title === '') {
					throw new InvalidArgumentException('Menu item title is required.');
				}

				if ($page_path === '' && $url === '') {
					throw new InvalidArgumentException("Menu item {$title} must define page_path or url.");
				}

				$seen_titles[$title] = true;

				if (isset($existing_by_title[$title])) {
					$id = (int) $existing_by_title[$title]['node_id'];
					$updated[] = CmsMenuService::update($type, $id, [
						'title' => $title,
						'page_path' => $page_path,
						'url' => $url,
					]);
					$updated[count($updated) - 1] = self::moveRootItemToPosition($type, $id, $position);
				} else {
					$created_item = CmsMenuService::create(
						$type,
						$title,
						0,
						$page_path !== '' ? $page_path : null,
						$url !== '' ? $url : null,
						$position
					);
					$created[] = $created_item;
					$existing_by_title[$title] = $created_item;
				}
			}

			$pruned = [];

			if ($prune) {
				foreach ($existing_by_title as $title => $existing_item) {
					if (isset($seen_titles[$title])) {
						continue;
					}

					$node_id = (int) $existing_item['node_id'];

					if (CmsMenuService::delete($type, $node_id, false)) {
						$pruned[] = $node_id;
					}
				}
			}

			ApiResponse::renderSuccess([
				'type' => $type,
				'created' => $created,
				'updated' => $updated,
				'pruned' => $pruned,
				'items' => CmsMenuService::list($type),
			]);
		} catch (Throwable $exception) {
			ApiResponse::renderError('MENU_SYNC_FAILED', $exception->getMessage(), 400);
		}
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function rootItemsByTitle(string $type): array
	{
		$items = [];

		foreach (CmsMenuService::list($type) as $item) {
			if ((int) ($item['parent_id'] ?? 0) !== 0) {
				continue;
			}

			$title = (string) ($item['node_name'] ?? '');

			if ($title !== '' && !isset($items[$title])) {
				$items[$title] = $item;
			}
		}

		return $items;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function moveRootItemToPosition(string $type, int $id, int $position): array
	{
		$current_ids = self::rootNodeIds($type);
		$current_index = array_search($id, $current_ids, true);

		if ($current_index === $position) {
			return CmsMenuService::get($type, $id);
		}

		return CmsMenuService::move($type, $id, 0, $position);
	}

	/**
	 * @return list<int>
	 */
	private static function rootNodeIds(string $type): array
	{
		$ids = [];

		foreach (CmsMenuService::list($type) as $item) {
			if ((int) ($item['parent_id'] ?? 0) === 0) {
				$ids[] = (int) $item['node_id'];
			}
		}

		return $ids;
	}
}
