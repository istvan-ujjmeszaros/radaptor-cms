<?php

class MainMenu
{
	public const string _MENU_ROOT = '/main/';

	public static function getUrl(int $id, bool $full = true): string
	{
		$menu_data = self::getMenuValues($id);

		// External URL, no need to modify
		if (!is_null($menu_data['url'])) {
			return $menu_data['url'];
		}

		// Neither an internal page nor an external URL
		if (is_null($menu_data['page_id'])) {
			return '#';
		}

		// Generate and return SEO-friendly URL for internal pages
		return Url::getSeoUrl($menu_data['page_id'], $full) ?? '';
	}

	public static function addMenu(array $savedata, int $parent_id = 0): bool
	{
		$savedata['node_type'] = 'submenu';

		return NestedSet::addNode('mainmenu_tree', $parent_id, $savedata) > 0;
	}

	public static function updateMenu(array $savedata, int $id): bool
	{
		$savedata['node_id'] = $id;

		return DbHelper::updateHelper('mainmenu_tree', $savedata) > 0;
	}

	public static function getMenuValues(int $id): array
	{
		return DbHelper::selectOne('mainmenu_tree', ['node_id' => $id]);
	}

	public static function getMenuName(int $id): string
	{
		$return = DbHelper::selectOne('mainmenu_tree', ['node_id' => $id]);

		return $return['node_name'] ?? '';
	}

	public static function factory(int $id): ?array
	{
		$mainMenu = NestedSet::getNodeInfo('mainmenu_tree', $id);

		if ($mainMenu) {
			$mainMenu['parent'] = NestedSet::getNodeInfo('mainmenu_tree', $mainMenu['parent_id']);
		}

		return $mainMenu;
	}

	public static function getMenuTree(int $parent_id): array
	{
		return NestedSet::getChildren('mainmenu_tree', $parent_id, [
			'node_name',
			'node_type',
			'url',
			'page_id',
		]);
	}

	/**
	 * Build JS tree data for main menu.
	 *
	 * @param int $parent_node_id The parent node ID.
	 * @param string $id_prefix The prefix for the node ID.
	 * @return array<int, array{attr: array<string, string>, state: string|null, data: array<string, string>}>
	 */
	public static function buildJsTreeData(int $parent_node_id, string $id_prefix): array
	{
		$data = self::getMenuTree($parent_node_id);

		$return = [];

		foreach ($data as $d) {
			if (!is_null($d['url'])) {
				$subtype = IconNames::LINK_OUT;
			} elseif (is_null($d['page_id'])) {
				$subtype = IconNames::LINK_NONE;
			} else {
				$subtype = IconNames::LINK;
			}

			$meta = [];

			foreach ($d as $key => $value) {
				$meta['data-' . $key] = $value;
			}

			$return[] = [
				'attr' => [
					'id' => $id_prefix . '_' . $d['node_id'],
					'rel' => $d['node_type'],
				] + $meta,
				'state' => ($d['rgt'] - $d['lft'] > 1) ? 'closed' : null,
				'data' => [
					'title' => '<span class="debug">' . $d['node_id'] . ':<i style="color:#999">(' . $d['lft'] . '-' . $d['rgt'] . ')</i> </span><span class="title">' . $d['node_name'] . '</span>',
					'icon' => $d['node_type'] === 'root' ? Icons::path(IconNames::MENUBAR) : Icons::path($subtype),
				],
			];
		}

		return $return;
	}

	public static function deleteRecursive(int $id): bool
	{
		return NestedSet::deleteNodeRecursive('mainmenu_tree', $id) > 0;
	}

	public static function moveToPosition(int $id, int $parent_id, int $position): bool
	{
		return NestedSet::moveToPosition('mainmenu_tree', $id, $parent_id, $position);
	}

	public static function getMenuData(iTreeBuildContext $tree_build_context): array
	{
		$menuData = DbHelper::fetchAll("SELECT * FROM mainmenu_tree ORDER BY lft;");

		$current_page_path = $tree_build_context->getPagedata('path');

		$found = false;

		foreach ($menuData as &$menu) {
			//$menuData[$key] = $mainmenu;

			$menu['href'] = MainMenu::getUrl($menu['node_id']);

			$menu['parseurl'] = parse_url($menu['href']);

			if (isset($menu['parseurl']['path']) && Url::comparePathLevels($current_page_path, $menu['parseurl']['path'], false, self::_MENU_ROOT)) {
				$found = true;
				$menu['is_active'] = true;
			} else {
				$menu['is_active'] = false;
			}
		}

		// ha belsőbb mappára mutat a menü, akkor az előző módszer nem
		// találja meg az egyezést, ezért ilyenkor megkeressük a legfelső
		// mappára való egyezés(eke)t (figyeljünk rá, hogy csak egy ilyen legyen)
		if (!$found) {
			foreach ($menuData as &$menu) {
				if (isset($menu['parseurl']['path']) && Url::comparePathLevels($current_page_path, $menu['parseurl']['path'], true, self::_MENU_ROOT)) {
					$menu['is_active'] = true;
				} else {
					$menu['is_active'] = false;
				}
			}
		}

		return $menuData;
	}
}
