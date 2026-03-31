<?php

class AdminMenu
{
	public const string _MENU_ROOT = '/admin/';

	public static function getUrl(int $id, bool $full = true): string
	{
		$menu_data = self::getMenuValues($id);

		// külső oldal
		if (!is_null($menu_data['url'])) {
			return $menu_data['url'];
		}

		// nincs se külső oldal, se belső oldal link információ
		if (is_null($menu_data['page_id'])) {
			return '#';
		}

		// belső oldal azonosító alapján előállított url
		return Url::getSeoUrl($menu_data['page_id'], $full) ?? '';
	}

	public static function addMenu(array $savedata, int $parent_id = 0): bool
	{
		$savedata['node_type'] = 'submenu';

		try {
			$id = NestedSet::addNode('adminmenu_tree', $parent_id, $savedata);
		} catch (Exception) {
			return false;
		}

		return $id > 0;
	}

	public static function updateMenu(array $savedata, int $id): bool
	{
		$savedata['node_id'] = $id;

		return DbHelper::updateHelper('adminmenu_tree', $savedata) > 0;
	}

	public static function getMenuValues(int $id): array
	{
		return DbHelper::selectOne('adminmenu_tree', ['node_id' => $id]);
	}

	public static function getMenuName(int $id): string
	{
		$return = DbHelper::selectOne('adminmenu_tree', ['node_id' => $id]);

		return $return['node_name'] ?? '';
	}

	public static function factory(int $id): ?array
	{
		$adminMenu = NestedSet::getNodeInfo('adminmenu_tree', $id);

		if (is_array($adminMenu)) {
			$adminMenu['parent'] = NestedSet::getNodeInfo('adminmenu_tree', $adminMenu['parent_id']);
		}

		return $adminMenu;
	}

	public static function getMenuTree($parent_id): array
	{
		return NestedSet::getChildren('adminmenu_tree', (int)$parent_id, [
			'node_name',
			'node_type',
			'url',
			'page_id',
		]);
	}

	public static function deleteRecursive(int $id): bool
	{
		return NestedSet::deleteNodeRecursive('adminmenu_tree', $id) > 0;
	}

	public static function moveToPosition(int $id, int $parent_id, int $position): bool
	{
		return NestedSet::moveToPosition('adminmenu_tree', $id, $parent_id, $position);
	}

	public static function getMenuData(iTreeBuildContext $tree_build_context): array
	{
		$menuData = DbHelper::fetchAll("SELECT * FROM adminmenu_tree ORDER BY lft;");

		$current_page_path = $tree_build_context->getPagedata('path');

		$found = false;

		foreach ($menuData as &$menu) {
			//$menuData[$key] = $adminmenu;

			$menu['href'] = AdminMenu::getUrl($menu['node_id']);

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
