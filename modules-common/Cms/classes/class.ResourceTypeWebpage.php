<?php

class ResourceTypeWebpage extends AbstractResourceType
{
	public const string DEFAULT_SLOT_NAME = 'content';

	protected WebpageView $_view;

	private static array $_attributes = [
		'title',
		'keywords',
		'description',
		'layout',
		'lang_id',
		'robots_index',
		'robots_follow',
		'breadcrumb_title',
	];

	public function __construct(int $resource_id, array $resource_data)
	{
		$this->_resourceId = $resource_id;
		$this->_resourceData = $resource_data + self::getExtradata($resource_id);
		$this->_view = new WebpageView($this->_resourceData);
	}

	public function getView(): WebpageView
	{
		return $this->_view;
	}

	public function view(): void
	{
		$this->_view->view();
	}

	public static function getExtradata(int $resource_id, array $extra_attributes = []): array
	{
		return AttributeHandler::getAttributeArray(new AttributeResourceIdentifier(ResourceNames::RESOURCE_DATA, (string) $resource_id), array_merge(self::$_attributes, $extra_attributes));
	}

	public static function getResourceData(int $resource_id, array $extra_attributes = []): array
	{
		return array_merge(ResourceTreeHandler::getResourceTreeEntryDataById($resource_id), ResourceTypeWebpage::getExtradata($resource_id, $extra_attributes));
	}

	/**
	 * Azt az oldalt lehet vele lekérni, amire egy adott űrlap először lett felpakolva.
	 */
	public static function getWebpageIdByFormType(string $form_type): int
	{
		$page_id = self::getWebpageIdByWidgetParam('form_id', $form_type);

		if (!is_null($page_id)) {
			return $page_id;
		}

		if (Config::DEV_WEBPAGE_AUTOGENERATION_ON_WIDGET_REQUEST->value()) {
			$page_id = self::ensureDefaultWebpageWithFormType($form_type);

			if ($page_id !== false) {
				return $page_id;
			}
		}

		return 0;
	}

	/**
	 * Azt az oldalt lehet vele lekérni, amire egy adott űrlap először lett felpakolva.
	 */
	public static function getWebpageIdByJstreeType(string $jstree_type): ?int
	{
		return self::getWebpageIdByWidgetParam('jstree_type', $jstree_type);
	}

	public static function generateWebpageForWidget(string $widget_name): false|int
	{
		$widget_classname = 'Widget' . ucwords($widget_name);

		if (!class_exists($widget_classname) || !is_subclass_of($widget_classname, 'AbstractWidget')) {
			SystemMessages::_error("Requested widget class '{$widget_classname}' does not exist or does not implement AbstractWidget.");

			return false;
		}

		try {
			$path_data = $widget_classname::getDefaultPathForCreation();
		} catch (Exception) {
			SystemMessages::_error("Requested widget doesn't have a class: {$widget_name}");

			return false;
		}

		if (!isset($path_data['path']) || !isset($path_data['resource_name']) || !isset($path_data['layout'])) {
			SystemMessages::_error("Bad getDefaultPathForCreation() value for widget: {$widget_name}");

			return false;
		}

		$resource_data = ResourceTreeHandler::getResourceTreeEntryData($path_data['path'], $path_data['resource_name']);

		if (is_null($resource_data)) {
			$page_id = ResourceTreeHandler::withProtectedResourceMutationBypass(
				static fn (): ?int => ResourceTreeHandler::createResourceTreeEntryFromPath($path_data['path'], $path_data['resource_name'], 'webpage', $path_data['layout'])
			);
		} else {
			$page_id = $resource_data['node_id'];
		}

		if (is_null($page_id)) {
			SystemMessages::_error("Error creating webpage for widget: {$widget_name}");

			return false;
		} elseif (is_null($resource_data)) {
			SystemMessages::_config("Webpage created for widget: {$widget_name}");
		}

		return $page_id;
	}

	public static function placeWidgetToWebpage(int $page_id, string $widget_name): false|int
	{
		return Widget::assignWidgetToWebpage($page_id, self::DEFAULT_SLOT_NAME, $widget_name);
	}

	public static function ensureDefaultWebpageWithFormType(string $form_type): false|int
	{
		if ($form_type === '') {
			return false;
		}

		$form_class_name = "FormType" . $form_type;

		if (!class_exists($form_class_name) || !is_subclass_of($form_class_name, 'AbstractForm')) {
			SystemMessages::_error("Requested form class '{$form_class_name}' does not exist or does not implement AbstractForm.");

			return false;
		}

		try {
			$path_data = $form_class_name::getDefaultPathForCreation();
		} catch (Exception) {
			SystemMessages::_error("Requested form type doesn't have a class: {$form_type}");

			return false;
		}

		if (!isset($path_data['path']) || !isset($path_data['resource_name']) || !isset($path_data['layout'])) {
			SystemMessages::_error("Bad getDefaultPathForCreation() value for form type: {$form_type}");

			return false;
		}

		$resource_data = ResourceTreeHandler::getResourceTreeEntryData($path_data['path'], $path_data['resource_name']);

		if (is_null($resource_data)) {
			$page_id = ResourceTreeHandler::withProtectedResourceMutationBypass(
				static fn (): ?int => ResourceTreeHandler::createResourceTreeEntryFromPath($path_data['path'], $path_data['resource_name'], 'webpage', $path_data['layout'])
			);
		} else {
			$page_id = (int) $resource_data['node_id'];
		}

		if (is_null($page_id)) {
			SystemMessages::_error("Error creating webpage for form type: {$form_type}");

			return false;
		} elseif (is_null($resource_data)) {
			SystemMessages::_config("Webpage created for form: {$form_type}");
		}

		if (self::defaultWebpageHasFormType($page_id, $form_type)) {
			return $page_id;
		}

		$connection_id = self::placeWidgetToWebpage($page_id, WidgetList::FORM);

		if ($connection_id === false) {
			SystemMessages::_error("Error placing form widget on default webpage: {$form_type}");

			return false;
		}

		AttributeHandler::addAttribute(new AttributeResourceIdentifier(ResourceNames::WIDGET_CONNECTION, (string) $connection_id), ['form_id' => $form_type]);
		SystemMessages::_config("Form type set: {$form_type}");

		return $page_id;
	}

	public static function ensureDefaultWebpageWithWidget(string $widget_name): false|int
	{
		if ($widget_name === '') {
			return false;
		}

		$page_id = self::generateWebpageForWidget($widget_name);

		if ($page_id === false) {
			return false;
		}

		$existing_connection_id = Widget::getWidgetConnectionId($page_id, self::DEFAULT_SLOT_NAME, $widget_name);

		if (is_numeric($existing_connection_id) && (int) $existing_connection_id > 0) {
			return $page_id;
		}

		$connection_id = self::placeWidgetToWebpage($page_id, $widget_name);

		if ($connection_id === false) {
			SystemMessages::_error("Error placing widget on default webpage: {$widget_name}");

			return false;
		}

		return $page_id;
	}

	private static function defaultWebpageHasFormType(int $page_id, string $form_type): bool
	{
		foreach (WidgetConnection::getWidgetsForSlot($page_id, self::DEFAULT_SLOT_NAME) as $connection) {
			if ($connection->getWidgetName() !== WidgetList::FORM) {
				continue;
			}

			$attributes = AttributeHandler::getAttributes(
				new AttributeResourceIdentifier(ResourceNames::WIDGET_CONNECTION, (string) $connection->getConnectionId())
			);

			if (($attributes['form_id'] ?? null) === $form_type) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Azt az oldalt lehet vele lekérni, amire egy adott tartalmi típus először lett felpakolva.
	 */
	public static function findWebpageIdWithWidget(string $widget_name): int
	{
		if ($widget_name == "") {
			return 0;
		}

		$cached = Cache::get(self::class, $widget_name);

		if (!is_null($cached)) {
			return $cached;
		}

		$query = "SELECT
						page_id
					FROM
						widget_connections
					WHERE widget_name = ?
					ORDER BY connection_id
					LIMIT 1;";

		$stmt = Db::instance()->prepare($query);
		$stmt->execute([$widget_name]);

		$rs = $stmt->fetch(PDO::FETCH_ASSOC);

		if (isset($rs['page_id'])) {
			return Cache::set(self::class, $widget_name, $rs['page_id']);
		} else {
			if (Config::DEV_WEBPAGE_AUTOGENERATION_ON_WIDGET_REQUEST->value()) {
				$page_id = self::ensureDefaultWebpageWithWidget($widget_name);

				if ($page_id !== false) {
					return Cache::set(self::class, $widget_name, $page_id);
				}
			} else {
				SystemMessages::_error(t('cms.webpage.create_for_widget', [
					'widget' => Widget::getWidgetName($widget_name),
				]));

				return Cache::set(self::class, $widget_name, false);
			}
		}

		return 0;
	}

	/**
	 * Retrieves the webpage ID based on a widget parameter.
	 *
	 * This method returns the ID of the webpage where a given form was first added.
	 * If the `$param_value` is an empty string, it returns the page ID where the
	 * parameter is not assigned to the widget.
	 *
	 * @param string $param_name The name of the parameter to search for.
	 * @param string $param_value The value of the parameter. If false, returns the page where the parameter is not assigned to the widget.
	 * @return ?int The ID of the webpage or false if not found.
	 */
	public static function getWebpageIdByWidgetParam(string $param_name, string $param_value): ?int
	{
		// TODO: figure out how this method works under the hood
		$cached = Cache::get(self::class, $param_name . $param_value);

		if (!is_null($cached)) {
			return $cached;
		}

		if ($param_value == '') {
			$query = "
                SELECT
                    wsc.page_id AS page_id,
                    (SELECT
                        COUNT(1)
                    FROM
                        attributes a
                    WHERE a.resource_id = wsc.connection_id
                        AND a.param_name = ?) AS paramcount
                FROM
                    widget_connections wsc
                WHERE wsc.widget_name = 'Form'
                HAVING paramcount = 0
                LIMIT 1;
            ";

			$stmt = Db::instance()->prepare($query);
			$stmt->execute([$param_name]);
		} else {
			$query = "
                SELECT
                    page_id
                FROM
                    widget_connections
                WHERE connection_id=
                    (SELECT
                        resource_id
                    FROM
                        attributes
                    WHERE resource_name='" . ResourceNames::WIDGET_CONNECTION . "' AND param_name = ?
                        AND param_value = ?
                    ORDER BY id DESC
                    LIMIT 1)
                LIMIT 1;
            ";

			$stmt = Db::instance()->prepare($query);
			$stmt->execute([
				$param_name,
				$param_value,
			]);
		}

		$rs = $stmt->fetch(PDO::FETCH_ASSOC);

		if (isset($rs['page_id'])) {
			return Cache::set(self::class, $param_name . $param_value, $rs['page_id']);
		} else {
			SystemMessages::_error(t('cms.webpage.create_for_params', [
				'param_name' => $param_name,
				'param_value' => $param_value,
			]));

			return Cache::set(self::class, $param_name . $param_value, null);
		}
	}
}
