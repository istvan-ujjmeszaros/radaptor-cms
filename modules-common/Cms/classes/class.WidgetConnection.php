<?php

/**
 * Represents a connection between a Widget and a Slot on a Page.
 *
 * This is the join record that connects a Widget class to a named slot
 * (insertion point) in a page's layout.
 *
 * Terminology:
 * - Layout: Page structure definition (e.g., LayoutTypePublic2row)
 * - Slot: Named insertion point in a layout (e.g., 'content', 'sidebar')
 * - WidgetConnection: This class - the join record
 * - Widget: The component class that renders content (e.g., WidgetCompanyList)
 *
 * @phpstan-type RenderTreeNode array{
 *     type: string,
 *     component: string,
 *     props: array<string, mixed>,
 *     contents: array<string, list<array<string, mixed>>>,
 *     strings?: array<string, mixed>,
 *     meta?: array<string, mixed>
 * }
 * @phpstan-type WidgetConnectionInit array{
 *     connection_id?: int|string,
 *     first?: bool,
 *     last?: bool,
 *     widget_name?: string,
 *     slot_name?: string,
 *     seq?: int|null,
 *     previous?: WidgetConnection|null,
 *     next?: WidgetConnection|null,
 *     extraparams?: array<string, mixed>
 * }
 * @phpstan-type WidgetConnectionTreeMetadata array{
 *     connection_id: int,
 *     widget_name: string,
 *     slot_name: string,
 *     seq: int|null,
 *     is_first: bool,
 *     is_last: bool,
 *     previous_connection_id: int|null,
 *     next_connection_id: int|null,
 *     extraparams: array<string, mixed>
 * }
 * @phpstan-type WidgetConnectionTreeMetadataInput array{
 *     connection_id: int,
 *     widget_name: string,
 *     slot_name?: string,
 *     seq?: int|null,
 *     is_first?: bool,
 *     is_last?: bool,
 *     previous_connection_id?: int|null,
 *     next_connection_id?: int|null,
 *     extraparams?: array<string, mixed>
 * }
 */
class WidgetConnection
{
	public mixed $connection_id;
	protected int $_connection_id;
	protected bool $_is_first = false;
	protected bool $_is_last = false;
	protected string $_widget_name = "";
	protected string $_slot_name = "";
	protected ?int $_seq = null;

	protected ?WidgetConnection $_previous = null;
	protected ?WidgetConnection $_next = null;
	protected array $_extraparams = [];

	protected ?AbstractWidget $_widget = null;

	public function getWidgetName(): string
	{
		return $this->_widget_name;
	}

	public function getSlotName(): string
	{
		return $this->_slot_name;
	}

	/**
	 * @param WidgetConnectionInit $arguments
	 */
	public function __construct(array $arguments)
	{
		if (isset($arguments['connection_id'])) {
			$this->_connection_id = (int) $arguments['connection_id'];
			$this->connection_id = $arguments['connection_id'];
		} else {
			$this->_connection_id = -1 * random_int(1, PHP_INT_MAX);
			$this->connection_id = $this->_connection_id;
		}

		if (isset($arguments['first'])) {
			$this->_is_first = $arguments['first'];
		}

		if (isset($arguments['last'])) {
			$this->_is_last = $arguments['last'];
		}

		if (isset($arguments['widget_name'])) {
			$this->_widget_name = $arguments['widget_name'];
		}

		if (isset($arguments['slot_name'])) {
			$this->_slot_name = $arguments['slot_name'];
		}

		if (isset($arguments['seq'])) {
			$this->_seq = $arguments['seq'];
		}

		if (isset($arguments['previous'])) {
			$this->_previous = $arguments['previous'];
		}

		if (isset($arguments['next'])) {
			$this->_next = $arguments['next'];
		}

		if (isset($arguments['extraparams'])) {
			$this->_extraparams = $arguments['extraparams'];
		}

		if ($this->_widget_name !== '') {
			$this->_widget = Widget::factory($this->_widget_name);
		}
	}

	public function getWidget(): ?AbstractWidget
	{
		return $this->_widget;
	}

	/**
	 * Get all widget connections for a slot on a page.
	 *
	 * @param int $page_id The page ID
	 * @param string $slot_name The slot name (e.g., 'content', 'sidebar')
	 * @return array<WidgetConnection>
	 */
	public static function getWidgetsForSlot(int $page_id, string $slot_name): array
	{
		$connection_list = DbHelper::selectMany('widget_connections', [
			'page_id' => $page_id,
			'slot_name' => $slot_name,
		], false, 'seq');

		return self::createConnectionsFromRows($connection_list);
	}

	/**
	 * Get all widget connections for a page grouped by slot name.
	 *
	 * @param int $page_id The page ID
	 * @return array<string, array<WidgetConnection>>
	 */
	public static function getWidgetsForPageGroupedBySlot(int $page_id): array
	{
		$connection_list = DbHelper::selectMany('widget_connections', [
			'page_id' => $page_id,
		], false, 'slot_name, seq');

		$rowsBySlot = [];

		foreach ($connection_list as $row) {
			$slotName = (string)($row['slot_name'] ?? '');
			$rowsBySlot[$slotName][] = $row;
		}

		$connectionsBySlot = [];

		foreach ($rowsBySlot as $slotName => $rows) {
			$connectionsBySlot[$slotName] = self::createConnectionsFromRows($rows);
		}

		return $connectionsBySlot;
	}

	/**
	 * @param array<int, array<string, mixed>> $connection_list
	 * @return array<WidgetConnection>
	 */
	private static function createConnectionsFromRows(array $connection_list): array
	{
		$connectionCount = count($connection_list);

		if ($connectionCount === 0) {
			return [];
		}

		$connections = [];
		$i = 0;

		foreach ($connection_list as $key => $arguments) {
			++$i;

			$arguments['extraparams'] = AttributeHandler::getAttributes(
				new AttributeResourceIdentifier(ResourceNames::WIDGET_CONNECTION, $arguments['connection_id'])
			);

			$arguments['first'] = ($i === 1);
			$arguments['last'] = ($i === $connectionCount);

			$connection_list[$key] = $arguments;
			$connections[$key] = new WidgetConnection($arguments);
		}

		// Link previous/next
		$keys = array_keys($connections);

		foreach ($keys as $idx => $key) {
			if ($idx > 0) {
				$connections[$key]->_previous = $connections[$keys[$idx - 1]];
			}

			if ($idx < count($keys) - 1) {
				$connections[$key]->_next = $connections[$keys[$idx + 1]];
			}
		}

		return $connections;
	}

	/**
	 * Get the page ID that owns this widget connection.
	 */
	public static function getOwnerWebpageId(int $connection_id): ?int
	{
		$query = "SELECT page_id FROM widget_connections WHERE connection_id=? LIMIT 1";
		$stmt = Db::instance()->prepare($query);
		$stmt->execute([$connection_id]);
		$rs = $stmt->fetch(PDO::FETCH_ASSOC);

		return $rs['page_id'] ?? null;
	}

	/**
	 * Get the title of the page that owns this widget connection.
	 */
	public static function getOwnerWebpageTitle(int $connection_id): string
	{
		$owner_page_id = WidgetConnection::getOwnerWebpageId($connection_id);

		if (is_null($owner_page_id)) {
			return '';
		}

		$attributes = AttributeHandler::getAttributes(
			new AttributeResourceIdentifier(ResourceNames::RESOURCE_DATA, (string) $owner_page_id)
		);

		return $attributes['title'] ?? '';
	}

	public function getConnectionId(): int
	{
		return $this->_connection_id;
	}

	public function isFirst(): bool
	{
		return $this->_is_first;
	}

	public function isLast(): bool
	{
		return $this->_is_last;
	}

	public function previous(): ?WidgetConnection
	{
		return $this->_previous;
	}

	public function next(): ?WidgetConnection
	{
		return $this->_next;
	}

	public function seq(): ?int
	{
		return $this->_seq;
	}

	public function getExtraparam(string $name): mixed
	{
		return $this->_extraparams[$name] ?? null;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getExtraparams(): array
	{
		return $this->_extraparams;
	}

	/**
	 * @return WidgetConnectionTreeMetadata|null
	 */
	public static function toTreeMetadata(?WidgetConnection $connection): ?array
	{
		if ($connection === null) {
			return null;
		}

		return [
			'connection_id' => $connection->getConnectionId(),
			'widget_name' => $connection->getWidgetName(),
			'slot_name' => $connection->getSlotName(),
			'seq' => $connection->seq(),
			'is_first' => $connection->isFirst(),
			'is_last' => $connection->isLast(),
			'previous_connection_id' => $connection->previous()?->getConnectionId(),
			'next_connection_id' => $connection->next()?->getConnectionId(),
			'extraparams' => $connection->getExtraparams(),
		];
	}

	/**
	 * @param WidgetConnectionTreeMetadataInput $metadata
	 */
	public static function fromTreeMetadata(array $metadata): WidgetConnection
	{
		$arguments = [
			'connection_id' => $metadata['connection_id'],
			'widget_name' => (string)$metadata['widget_name'],
			'slot_name' => (string)($metadata['slot_name'] ?? ''),
			'first' => (bool)($metadata['is_first'] ?? false),
			'last' => (bool)($metadata['is_last'] ?? false),
			'extraparams' => is_array($metadata['extraparams'] ?? null) ? $metadata['extraparams'] : [],
		];

		if (array_key_exists('seq', $metadata) && $metadata['seq'] !== null) {
			$arguments['seq'] = (int)$metadata['seq'];
		}

		if (isset($metadata['previous_connection_id'])) {
			$arguments['previous'] = new self([
				'connection_id' => (int)$metadata['previous_connection_id'],
			]);
		}

		if (isset($metadata['next_connection_id'])) {
			$arguments['next'] = new self([
				'connection_id' => (int)$metadata['next_connection_id'],
			]);
		}

		return new self($arguments);
	}

	/**
	 * @param array<string, mixed> $build_context
	 * @return RenderTreeNode
	 */
	public function buildTree(iTreeBuildContext $tree_build_context, array $build_context = []): array
	{
		if (!empty($build_context['is_mock'])) {
			return AbstractWidget::buildMockedTree($this->getWidgetName(), $tree_build_context, $this, $build_context);
		}

		return Widget::factory($this->getWidgetName())->buildTree($tree_build_context, $this, $build_context);
	}

	public static function copyToClipboard(int $connection_id): void
	{
		Request::saveSessionData(['clipboard'], $connection_id);
		SystemMessages::_notice(t('cms.widget_connection.copied_to_clipboard'));
	}

	public static function getClipboard(): mixed
	{
		return Request::getSessionData(['clipboard']) ?? false;
	}

	public static function clearClipboard(): void
	{
		Request::unsetSessionData(['clipboard']);
	}

	public function getConnectionIdAsString(): string
	{
		return 'connection_id_' . $this->connection_id;
	}

	public function getStyle(): string
	{
		$return = '';

		if ($this->getExtraparam('margin-top')) {
			$return .= 'margin-top: ' . $this->getExtraparam('margin-top') . ';';
		}

		if ($this->getExtraparam('margin-left')) {
			$return .= 'margin-left: ' . $this->getExtraparam('margin-left') . ';';
		}

		if ($this->getExtraparam('margin-bottom')) {
			$return .= 'margin-bottom: ' . $this->getExtraparam('margin-bottom') . ';';
		}

		if ($this->getExtraparam('margin-right')) {
			$return .= 'margin-right: ' . $this->getExtraparam('margin-right') . ';';
		}

		if ($this->getExtraparam('width')) {
			$return .= 'width: ' . $this->getExtraparam('width') . ';';
		}

		if ($this->getExtraparam('height')) {
			$return .= 'height: ' . $this->getExtraparam('height') . ';';
		}

		if ($this->getExtraparam('float')) {
			switch ($this->getExtraparam('float')) {
				case 'left':
					$return .= 'float: left;';

					break;

				case 'left-clear':
					$return .= 'float: left;clear:both;';

					break;

				case 'right':
					$return .= 'float: right;';

					break;

				case 'right-clear':
					$return .= 'float: right;clear:both;';

					break;

				case 'clear':
					$return .= 'clear:both;';

					break;
			}
		}

		return $return;
	}
}
