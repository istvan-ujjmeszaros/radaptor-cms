<?php

class WidgetGroup
{
	public static function getNextEndConnectionId($beginning_connection_id): int
	{
		$beginning_data = Widget::getConnectionData($beginning_connection_id);

		$query = "SELECT connection_id FROM widget_connections WHERE page_id = ? AND seq>? AND widget_name = 'GroupEnd' ORDER BY seq LIMIT 1;";

		return DbHelper::selectOneColumnFromQuery(
			$query,
			[$beginning_data['page_id'], $beginning_data['seq']]
		) ?? 0;
	}
}
