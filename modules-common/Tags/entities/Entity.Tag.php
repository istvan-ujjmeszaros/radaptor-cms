<?php

/**
 * @phpstan-type ShapeEntityTag array{
 *     id?: int,
 *     context?: string|null,
 *     slug?: string|null,
 *     name?: string|null,
 *     __description?: string|null,
 *     description?: string|null,
 * }
 *
 * @property ?int $id (auto_increment)
 * @property ?string $context
 * @property ?string $slug
 * @property ?string $name
 * @property ?string $__description
 * @property ?string $description
 *
 * @extends SQLEntity<ShapeEntityTag>
 */
class EntityTag extends SQLEntity
{
	public const string TABLE_NAME = 'tags';
	private const string I18N_DOMAIN = 'tag';

	// ============================================================================
	// Custom methods (safe from regeneration)
	// ============================================================================

	public static function addTag(array $savedata): ?int
	{
		$savedata['name'] = HtmlProcessor::cleanText((string) ($savedata['name'] ?? ''));
		$savedata['slug'] = self::resolveUniqueSlug(
			context: (string) ($savedata['context'] ?? ''),
			name: (string) $savedata['name'],
			preferredSlug: array_key_exists('slug', $savedata) ? (string) $savedata['slug'] : null
		);

		$entity = static::createFromArray($savedata);
		$id = $entity->pkey();

		self::syncI18nMessageForId((int) $id);

		return $id;
	}

	public static function getTagList(?string $context = null): array
	{
		if (is_null($context)) {
			return DbHelper::selectMany('tags');
		} else {
			return DbHelper::selectMany('tags', ['context' => $context]);
		}
	}

	public static function getMatchingTagNames(string $context, string $match): array
	{
		$match = trim($match);

		return DbHelper::selectManyColumn(
			table: 'tags',
			col: 'name',
			where: [
				'context' => $context,
				' name LIKE' => "%{$match}%",
			]
		);
	}

	public static function getTagValues(int $id): ?array
	{
		return static::findById($id)?->dto();
	}

	public static function getTagName(int $id): string
	{
		return static::pluckFirst('name', ['id' => $id]) ?? '';
	}

	public static function getTagDisplayName(int $id): string
	{
		$tag = static::getTagValues($id);

		if (!is_array($tag)) {
			return '';
		}

		return static::getDisplayNameFromValues($tag);
	}

	public static function getTagId(string $context, string $name, bool $enable_create = false): ?int
	{
		$name = HtmlProcessor::cleanText($name);
		$tag_id = DbHelper::selectOneColumn('tags', [
			'context' => $context,
			'name' => $name,
		], '', 'id');

		if (!is_null($tag_id)) {
			return $tag_id;
		}

		if ($enable_create) {
			$tag_id = static::addTag([
				'context' => $context,
				'name' => $name,
			]);
		}

		return $tag_id;
	}

	public static function updateTag(array $savedata, int $id): int
	{
		$current = static::findById($id);

		if ($current === null) {
			return 0;
		}

		$savedata['name'] = HtmlProcessor::cleanText((string) ($savedata['name'] ?? ''));

		if (array_key_exists('context', $savedata)) {
			$currentContext = $current->context;

			if ($currentContext !== null && $savedata['context'] !== $currentContext) {
				throw new EntitySaveException(
					message: 'Cannot modify immutable field context',
					entityClass: static::class,
					data: ['id' => $id, 'savedata' => $savedata]
				);
			}

			unset($savedata['context']);
		}

		$currentSlug = trim((string) ($current->slug ?? ''));

		if (array_key_exists('slug', $savedata)) {
			$requestedSlug = self::normalizeSlug((string) $savedata['slug']);

			if ($currentSlug !== '' && $requestedSlug !== $currentSlug) {
				throw new EntitySaveException(
					message: 'Cannot modify immutable field slug',
					entityClass: static::class,
					data: ['id' => $id, 'savedata' => $savedata]
				);
			}

			unset($savedata['slug']);
		}

		if ($currentSlug === '') {
			$savedata['slug'] = self::resolveUniqueSlug(
				context: (string) ($current->context ?? ''),
				name: (string) $savedata['name'],
				excludeTagId: $id
			);
		}

		$updated = DbHelper::updateHelper('tags', $savedata, $id);

		if ($updated > 0) {
			self::syncI18nMessageForId($id);
		}

		return $updated;
	}

	public static function extractTagsFromString(string $tags_string): array
	{
		$tags_string = HtmlProcessor::cleanText($tags_string);
		$tags = explode(',', str_replace(", ", ",", $tags_string));

		if ($tags[count($tags) - 1] == '') {
			unset($tags[count($tags) - 1]);
		}

		return $tags;
	}

	public static function getConnectedTagNames(string $context, int $connected_id): array
	{
		$query = "SELECT t.name FROM tag_connections tc LEFT JOIN tags t ON tc.tag_id=t.id WHERE tc.context=? AND tc.connected_id=?";

		return DbHelper::selectManyFromQuery(
			$query,
			[$context, $connected_id]
		);
	}

	public static function getConnectedTags(string $context, int $connected_id): array
	{
		$query = "SELECT t.id, t.name FROM tag_connections tc LEFT JOIN tags t ON tc.tag_id=t.id WHERE tc.context=? AND tc.connected_id=?";

		return DbHelper::selectManyFromQuery(
			$query,
			[$context, $connected_id]
		);
	}

	public static function getConnectedTagNamesString(string $context, int $connected_id): string
	{
		return implode(', ', static::getConnectedTagNames($context, $connected_id));
	}

	public static function connectTagByName(string $context, int $connected_id, string $tag_name): ?int
	{
		$tag_id = static::getTagId($context, $tag_name, true);

		$savedata = [
			'context'      => $context,
			'connected_id' => $connected_id,
			'tag_id'       => $tag_id,
		];

		return EntityTagConnection::createFromArray($savedata)->rowid;
	}

	public static function disconnectTagByName(string $context, int $connected_id, string $tag_name): int
	{
		$tag_id = static::getTagId($context, $tag_name);

		if (is_null($tag_id)) {
			return 0;
		}

		$query = "DELETE FROM tag_connections WHERE context=? AND connected_id=? AND tag_id=?";
		$stmt = Db::instance()->prepare($query);
		$stmt->execute([
			$context,
			$connected_id,
			$tag_id,
		]);

		Cache::flush();

		return $stmt->rowCount();
	}

	public static function updateAllTags(string $context, int $connected_id, array $tags): bool
	{
		$updated = false;

		$already_connected = static::getConnectedTagNames($context, $connected_id);

		foreach ($already_connected as $tag) {
			if (!in_array($tag, $tags)) {
				if (static::disconnectTagByName($context, $connected_id, $tag)) {
					$updated = true;
				}
			}
		}

		$processed_tags = [];

		foreach ($tags as $tag) {
			if (in_array($tag, $processed_tags)) {
				continue;
			}

			$processed_tags[] = HtmlProcessor::cleanText($tag);

			if (!in_array($tag, $already_connected)) {
				if (static::connectTagByName($context, $connected_id, $tag)) {
					$updated = true;
				}
			}
		}

		return $updated;
	}

	public static function cleanupAllTags(): void
	{
		$query = "SELECT * FROM tags";

		$stmt = Db::instance()->prepare($query);

		$stmt->execute();

		$rs = $stmt->fetchAll(PDO::FETCH_ASSOC);

		foreach ($rs as $tag) {
			$cleanup_savedata = [
				'name' => HtmlProcessor::cleanText($tag['name']),
				'__description' => HtmlProcessor::cleanText($tag['__description']),
			];

			if ($tag['name'] !== $cleanup_savedata['name']) {
				var_dump([
					$tag,
					$cleanup_savedata,
				]);
			}
		}
	}

	public static function getDisplayNameFromValues(array $tag): string
	{
		$fallback = (string) ($tag['name'] ?? '');
		$context = trim((string) ($tag['context'] ?? ''));
		$slug = trim((string) ($tag['slug'] ?? ''));

		if ($context === '' || $slug === '') {
			return $fallback;
		}

		$translationKey = self::getI18nTranslationKey($context, $slug);
		$translated = t($translationKey);

		return $translated === $translationKey ? $fallback : $translated;
	}

	/**
	 * @return array{processed: int, slug_backfilled: int, messages_created: int, messages_updated: int, unchanged: int}
	 */
	public static function syncAllTagI18nMessages(bool $dryRun = false): array
	{
		$rows = DbHelper::selectMany('tags');
		$summary = [
			'processed' => 0,
			'slug_backfilled' => 0,
			'messages_created' => 0,
			'messages_updated' => 0,
			'unchanged' => 0,
		];

		foreach ($rows as $row) {
			$summary['processed']++;
			$context = trim((string) ($row['context'] ?? ''));
			$name = HtmlProcessor::cleanText((string) ($row['name'] ?? ''));
			$currentSlug = trim((string) ($row['slug'] ?? ''));
			$resolvedSlug = self::resolveUniqueSlug(
				context: $context,
				name: $name,
				preferredSlug: $currentSlug !== '' ? $currentSlug : null,
				excludeTagId: (int) ($row['id'] ?? 0)
			);

			if ($currentSlug !== $resolvedSlug) {
				$summary['slug_backfilled']++;

				if (!$dryRun) {
					DbHelper::updateHelper('tags', ['slug' => $resolvedSlug], (int) $row['id']);
				}
			}

			$messageStatus = self::syncI18nMessageForValues([
				'id' => (int) ($row['id'] ?? 0),
				'context' => $context,
				'slug' => $resolvedSlug,
				'name' => $name,
			], $dryRun);

			if ($messageStatus === 'created') {
				$summary['messages_created']++;
			} elseif ($messageStatus === 'updated') {
				$summary['messages_updated']++;
			} else {
				$summary['unchanged']++;
			}
		}

		return $summary;
	}

	public static function syncI18nMessageForId(int $id): void
	{
		$tag = static::getTagValues($id);

		if (!is_array($tag)) {
			return;
		}

		if (trim((string) ($tag['slug'] ?? '')) === '') {
			$slug = self::resolveUniqueSlug(
				context: (string) ($tag['context'] ?? ''),
				name: (string) ($tag['name'] ?? ''),
				excludeTagId: $id
			);
			DbHelper::updateHelper('tags', ['slug' => $slug], $id);
			$tag['slug'] = $slug;
		}

		self::syncI18nMessageForValues($tag, false);
	}

	private static function syncI18nMessageForValues(array $tag, bool $dryRun): string
	{
		$context = trim((string) ($tag['context'] ?? ''));
		$slug = trim((string) ($tag['slug'] ?? ''));
		$name = HtmlProcessor::cleanText((string) ($tag['name'] ?? ''));

		if ($context === '' || $slug === '' || $name === '') {
			return 'unchanged';
		}

		$key = self::buildI18nMessageKey($context, $slug);
		$sourceHash = md5($name);

		$existing = EntityI18n_messag::findById([
			'domain' => self::I18N_DOMAIN,
			'key' => $key,
			'context' => '',
		]);

		if ($existing === null) {
			if (!$dryRun) {
				EntityI18n_messag::createFromArray([
					'domain' => self::I18N_DOMAIN,
					'key' => $key,
					'context' => '',
					'source_text' => $name,
					'source_hash' => $sourceHash,
				]);
			}

			return 'created';
		}

		$currentSourceText = (string) ($existing->source_text ?? '');
		$currentSourceHash = (string) ($existing->source_hash ?? '');

		if ($currentSourceText === $name && $currentSourceHash === $sourceHash) {
			return 'unchanged';
		}

		if (!$dryRun) {
			$existing->patch([
				'domain' => self::I18N_DOMAIN,
				'key' => $key,
				'context' => '',
				'source_text' => $name,
				'source_hash' => $sourceHash,
			])->save();
		}

		return 'updated';
	}

	private static function getI18nTranslationKey(string $context, string $slug): string
	{
		return self::I18N_DOMAIN . '.' . self::buildI18nMessageKey($context, $slug);
	}

	private static function buildI18nMessageKey(string $context, string $slug): string
	{
		return $context . '_' . $slug . '.label';
	}

	private static function resolveUniqueSlug(string $context, string $name, ?string $preferredSlug = null, ?int $excludeTagId = null): string
	{
		$baseSlug = self::normalizeSlug($preferredSlug ?? $name);

		if ($baseSlug === '') {
			$baseSlug = $excludeTagId !== null && $excludeTagId > 0
				? 'tag-' . $excludeTagId
				: 'tag';
		}

		$candidate = $baseSlug;
		$suffix = 2;

		while (self::slugExists($context, $candidate, $excludeTagId)) {
			$candidate = $baseSlug . '-' . $suffix;
			$suffix++;
		}

		return $candidate;
	}

	private static function slugExists(string $context, string $slug, ?int $excludeTagId = null): bool
	{
		$query = "SELECT id FROM tags WHERE context = ? AND slug = ?";
		$params = [$context, $slug];

		if ($excludeTagId !== null && $excludeTagId > 0) {
			$query .= " AND id <> ?";
			$params[] = $excludeTagId;
		}

		$query .= " LIMIT 1";

		return DbHelper::selectOneColumnFromQuery($query, $params) !== false;
	}

	private static function normalizeSlug(string $value): string
	{
		$value = trim(mb_strtolower($value));

		$transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

		if (is_string($transliterated) && $transliterated !== '') {
			$value = $transliterated;
		}

		$value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
		$value = preg_replace('/-+/', '-', $value) ?? '';

		return trim($value, '-');
	}

	// ============================================================================
	// Autogenerated method override for IDE autocomplete and type safety
	// ============================================================================

	/**
	 * {@inheritDoc}
	 * @param ShapeEntityTag $data
	 */
	public function __construct(array $data)
	{
		parent::__construct($data);
	}

	/**
	 * {@inheritDoc}
	 * @param ShapeEntityTag $data
	 */
	public function patch(array $data): static
	{
		return parent::patch($data);
	}

	/**
	 * {@inheritDoc}
	 * @param ShapeEntityTag $data
	 */
	public static function saveFromArray(array $data): static
	{
		return parent::saveFromArray($data);
	}

	/**
	 * {@inheritDoc}
	 * @param ShapeEntityTag $data
	 */
	public static function createFromArray(array $data): static
	{
		return parent::createFromArray($data);
	}

	/**
	 * {@inheritDoc}
	 * @param int|string|array<string, mixed> $id
	 * @param ShapeEntityTag $data
	 */
	public static function updateById(int|string|array $id, array $data): static
	{
		return parent::updateById($id, $data);
	}

	/**
	 * {@inheritDoc}
	 * @param ShapeEntityTag $data
	 */
	public static function instantiateFromArray(array $data): static
	{
		return parent::instantiateFromArray($data);
	}
}
