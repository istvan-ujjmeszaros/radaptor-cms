<?php

declare(strict_types=1);

class I18nTm
{
	public const string CANONICAL_SOURCE_LOCALE = 'en_US';
	private const int EXACT_LIMIT = 3;
	private const int FUZZY_LIMIT = 5;
	private const int FUZZY_CANDIDATE_LIMIT = 250;
	private const float FUZZY_THRESHOLD = 0.50;

	/**
	 * Record a confirmed translation in the TM.
	 */
	public static function record(
		string $sourceLoc,
		string $targetLoc,
		string $sourceText,
		string $targetText,
		string $domain,
		string $sourceKey,
		string $context,
		string $quality
	): void {
		$sourceHash = md5($sourceText);

		$existing = DbHelper::selectOne('i18n_tm_entries', [
			'source_locale' => $sourceLoc,
			'target_locale' => $targetLoc,
			'source_hash'   => $sourceHash,
			'domain'        => $domain,
			'source_key'    => $sourceKey,
			'context'       => $context,
		]);

		$now = date('Y-m-d H:i:s');

		if ($existing) {
			DbHelper::updateHelper('i18n_tm_entries', [
				'target_text'            => $targetText,
				'quality_score'          => $quality,
				'usage_count'            => $existing['usage_count'] + 1,
				'updated_at'             => $now,
			], (int) $existing['tm_id']);
		} else {
			$normalized = self::_normalize($sourceText);

			DbHelper::insertHelper('i18n_tm_entries', [
				'source_locale'          => $sourceLoc,
				'target_locale'          => $targetLoc,
				'source_text_normalized' => $normalized,
				'source_text_raw'        => $sourceText,
				'target_text'            => $targetText,
				'domain'                 => $domain,
				'source_key'             => $sourceKey,
				'context'                => $context,
				'source_hash'            => $sourceHash,
				'usage_count'            => 1,
				'quality_score'          => $quality,
				'created_at'             => $now,
				'updated_at'             => $now,
			]);
		}
	}

	/**
	 * Rebuild a narrow TM signature from the current translation rows.
	 *
	 * This is the canonical per-write synchronization path used by save/delete
	 * operations. It keeps TM consistent without requiring a full-table rebuild.
	 */
	public static function syncForSignature(
		string $domain,
		string $sourceKey,
		string $context,
		string $sourceHash,
		string $targetLocale
	): void {
		if ($sourceHash === '') {
			return;
		}

		$pdo = Db::instance();
		$delete = $pdo->prepare(
			"DELETE FROM i18n_tm_entries
			WHERE source_locale = :source_locale
				AND target_locale = :target_locale
				AND domain = :domain
				AND source_key = :source_key
				AND context = :context
				AND source_hash = :source_hash"
		);
		$delete->execute([
			':source_locale' => self::CANONICAL_SOURCE_LOCALE,
			':target_locale' => $targetLocale,
			':domain' => $domain,
			':source_key' => $sourceKey,
			':context' => $context,
			':source_hash' => $sourceHash,
		]);

		$rows = $pdo->prepare(
			"SELECT
				m.`key` AS source_key,
				m.source_text,
				t.text AS target_text,
				t.human_reviewed
			FROM i18n_messages m
			JOIN i18n_translations t
				ON t.domain = m.domain AND t.`key` = m.`key` AND t.context = m.context
			WHERE m.domain = :domain
				AND m.`key` = :source_key
				AND m.context = :context
				AND m.source_hash = :source_hash
				AND m.source_text <> ''
				AND t.locale = :target_locale
				AND TRIM(t.text) <> ''"
		);
		$rows->execute([
			':domain' => $domain,
			':source_key' => $sourceKey,
			':context' => $context,
			':source_hash' => $sourceHash,
			':target_locale' => $targetLocale,
		]);
		$matches = $rows->fetchAll(\PDO::FETCH_ASSOC);

		if (empty($matches)) {
			return;
		}

		$insert = $pdo->prepare(
			"INSERT INTO i18n_tm_entries (
				source_locale,
				target_locale,
				source_text_normalized,
				source_text_raw,
				target_text,
				domain,
				source_key,
				context,
				source_hash,
				usage_count,
				quality_score,
				created_at,
				updated_at
			) VALUES (
				:source_locale,
				:target_locale,
				:source_text_normalized,
				:source_text_raw,
				:target_text,
				:domain,
				:source_key,
				:context,
				:source_hash,
				:usage_count,
				:quality_score,
				:created_at,
				:updated_at
			)"
		);

		$now = date('Y-m-d H:i:s');

		foreach ($matches as $row) {
			$insert->execute([
				':source_locale' => self::CANONICAL_SOURCE_LOCALE,
				':target_locale' => $targetLocale,
				':source_text_normalized' => self::_normalize((string) $row['source_text']),
				':source_text_raw' => (string) $row['source_text'],
				':target_text' => (string) $row['target_text'],
				':domain' => $domain,
				':source_key' => (string) $row['source_key'],
				':context' => $context,
				':source_hash' => $sourceHash,
				':usage_count' => 1,
				':quality_score' => self::_mapHumanReviewedToQuality((bool) ((int) ($row['human_reviewed'] ?? 0))),
				':created_at' => $now,
				':updated_at' => $now,
			]);
		}
	}

	/**
	 * Rebuild the TM table from existing i18n translations.
	 *
	 * @return int Number of rebuilt TM rows
	 */
	public static function rebuildFromTranslations(?string $targetLocale = null): int
	{
		$pdo = Db::instance();

		if ($targetLocale === null || $targetLocale === '') {
			$pdo->exec("DELETE FROM i18n_tm_entries");
		} else {
			$stmt = $pdo->prepare("DELETE FROM i18n_tm_entries WHERE target_locale = ?");
			$stmt->execute([$targetLocale]);
		}

		$where = "WHERE m.source_text <> ''
			AND TRIM(t.text) <> ''";
		$params = [];

		if ($targetLocale !== null && $targetLocale !== '') {
			$where .= " AND t.locale = :target_locale";
			$params[':target_locale'] = $targetLocale;
		}

		$stmt = $pdo->prepare(
			"SELECT
				m.domain,
				m.`key`,
				m.context,
				m.source_text,
				m.source_hash,
				t.locale AS target_locale,
				t.text AS target_text,
				t.human_reviewed
			FROM i18n_messages m
			JOIN i18n_translations t
				ON t.domain = m.domain AND t.`key` = m.`key` AND t.context = m.context
			{$where}"
		);
		$stmt->execute($params);
		$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		if (empty($rows)) {
			return 0;
		}

		$insert = $pdo->prepare(
			"INSERT INTO i18n_tm_entries (
				source_locale,
				target_locale,
				source_text_normalized,
				source_text_raw,
				target_text,
				domain,
				source_key,
				context,
				source_hash,
				usage_count,
				quality_score,
				created_at,
				updated_at
			) VALUES (
				:source_locale,
				:target_locale,
				:source_text_normalized,
				:source_text_raw,
				:target_text,
				:domain,
				:source_key,
				:context,
				:source_hash,
				:usage_count,
				:quality_score,
				:created_at,
				:updated_at
			)"
		);

		$now = date('Y-m-d H:i:s');
		$count = 0;

		foreach ($rows as $row) {
			$insert->execute([
				':source_locale' => self::CANONICAL_SOURCE_LOCALE,
				':target_locale' => $row['target_locale'],
				':source_text_normalized' => self::_normalize($row['source_text']),
				':source_text_raw' => $row['source_text'],
				':target_text' => $row['target_text'],
				':domain' => $row['domain'],
				':source_key' => $row['key'],
				':context' => $row['context'],
				':source_hash' => $row['source_hash'],
				':usage_count' => 1,
				':quality_score' => self::_mapHumanReviewedToQuality((bool) ((int) ($row['human_reviewed'] ?? 0))),
				':created_at' => $now,
				':updated_at' => $now,
			]);
			$count++;
		}

		return $count;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function getExactSuggestions(string $sourceText, string $targetLocale, int $limit = self::EXACT_LIMIT): array
	{
		if ($sourceText === '' || $targetLocale === '') {
			return [];
		}

		$sourceHash = md5($sourceText);
		$rows = DbHelper::selectMany('i18n_tm_entries', [
			'source_locale' => self::CANONICAL_SOURCE_LOCALE,
			'target_locale' => $targetLocale,
			'source_hash' => $sourceHash,
		]);

		usort($rows, function (array $a, array $b): int {
			$qualityDiff = self::_qualityRank((string) $b['quality_score']) <=> self::_qualityRank((string) $a['quality_score']);

			if ($qualityDiff !== 0) {
				return $qualityDiff;
			}

			return ((int) $b['usage_count']) <=> ((int) $a['usage_count']);
		});

		$rows = self::_dedupeExactSuggestions($rows);

		return array_map(function (array $row): array {
			$row['match_type'] = 'exact';
			$row['similarity_score'] = 1.0;
			$row['similarity_percent'] = 100;
			$row['safety'] = 'safe';
			$row['placeholder_review'] = false;

			return $row;
		}, array_slice($rows, 0, $limit));
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function getFuzzySuggestions(
		string $sourceText,
		string $targetLocale,
		string $domain = '',
		string $context = '',
		int $limit = self::FUZZY_LIMIT
	): array {
		if ($sourceText === '' || $targetLocale === '') {
			return [];
		}

		$normalized = self::_normalize($sourceText);
		$sourceHash = md5($sourceText);

		if ($normalized === '') {
			return [];
		}

		$candidates = self::_fetchFuzzyCandidates($targetLocale, $sourceHash, $normalized);
		$currentPlaceholders = self::_extractPlaceholders($sourceText);
		$ranked = [];

		foreach ($candidates as $candidate) {
			$score = self::_scoreCandidate($normalized, $candidate, $domain, $context);

			if ($score < self::FUZZY_THRESHOLD) {
				continue;
			}

			$placeholders = self::_extractPlaceholders((string) $candidate['source_text_raw']);
			$isSafe = self::_samePlaceholderSet($currentPlaceholders, $placeholders);
			$candidate['match_type'] = 'similar';
			$candidate['similarity_score'] = $score;
			$candidate['similarity_percent'] = (int) round($score * 100);
			$candidate['safety'] = $isSafe ? 'safe' : 'review_placeholders';
			$candidate['placeholder_review'] = !$isSafe;
			$ranked[] = $candidate;
		}

		usort($ranked, function (array $a, array $b): int {
			$scoreDiff = ($b['similarity_score'] <=> $a['similarity_score']);

			if ($scoreDiff !== 0) {
				return $scoreDiff;
			}

			$qualityDiff = self::_qualityRank((string) $b['quality_score']) <=> self::_qualityRank((string) $a['quality_score']);

			if ($qualityDiff !== 0) {
				return $qualityDiff;
			}

			return ((int) $b['usage_count']) <=> ((int) $a['usage_count']);
		});

		return array_slice($ranked, 0, $limit);
	}

	/**
	 * Normalize source text for TM lookup: NFC + lowercase + collapse whitespace.
	 * Must match CLICommandI18nTmReindex::_normalize().
	 */
	public static function _normalize(string $text): string
	{
		if (class_exists('Normalizer')) {
			$text = \Normalizer::normalize($text, \Normalizer::NFC) ?: $text;
		}

		$text = mb_strtolower($text, 'UTF-8');
		$text = preg_replace('/\s+/', ' ', $text) ?? $text;

		return trim($text);
	}

	private static function _mapHumanReviewedToQuality(bool $humanReviewed): string
	{
		return $humanReviewed ? 'approved' : 'mt';
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function _fetchFuzzyCandidates(string $targetLocale, string $sourceHash, string $normalized): array
	{
		$length = max(1, mb_strlen($normalized, 'UTF-8'));
		$minLength = max(1, (int) floor($length * 0.5));
		$maxLength = max($length + 15, (int) ceil($length * 1.8));
		$pdo = Db::instance();
		$stmt = $pdo->prepare(
			"SELECT
				source_text_raw,
				source_text_normalized,
				target_text,
				domain,
				source_key,
				context,
				source_hash,
				usage_count,
				quality_score
			FROM i18n_tm_entries
			WHERE source_locale = :source_locale
				AND target_locale = :target_locale
				AND source_hash <> :source_hash
				AND source_text_normalized <> ''
				AND target_text <> ''
				AND CHAR_LENGTH(source_text_normalized) BETWEEN :min_length AND :max_length
			ORDER BY usage_count DESC, updated_at DESC
			LIMIT " . self::FUZZY_CANDIDATE_LIMIT
		);
		$stmt->bindValue(':source_locale', self::CANONICAL_SOURCE_LOCALE);
		$stmt->bindValue(':target_locale', $targetLocale);
		$stmt->bindValue(':source_hash', $sourceHash);
		$stmt->bindValue(':min_length', $minLength, \PDO::PARAM_INT);
		$stmt->bindValue(':max_length', $maxLength, \PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * @param array<string, mixed> $candidate
	 */
	private static function _scoreCandidate(string $normalizedSource, array $candidate, string $domain, string $context): float
	{
		$candidateSource = (string) ($candidate['source_text_normalized'] ?? '');

		if ($candidateSource === '') {
			return 0.0;
		}

		similar_text($normalizedSource, $candidateSource, $percent);
		$textSimilarity = max(0.0, min(1.0, $percent / 100));
		$tokenOverlap = self::_tokenOverlap($normalizedSource, $candidateSource);
		$domainBoost = ($domain !== '' && $domain === (string) ($candidate['domain'] ?? '')) ? 0.08 : 0.0;
		$contextBoost = ($context !== '' && $context === (string) ($candidate['context'] ?? '')) ? 0.05 : 0.0;
		$qualityBoost = match ((string) ($candidate['quality_score'] ?? 'manual')) {
			'approved' => 0.05,
			'manual' => 0.03,
			'imported' => 0.02,
			default => 0.01,
		};
		$usageBoost = min(0.05, log(((int) ($candidate['usage_count'] ?? 0)) + 1) / 20);
		$substringBoost = (str_contains($candidateSource, $normalizedSource) || str_contains($normalizedSource, $candidateSource))
			? 0.06
			: 0.0;

		$score = (0.60 * $textSimilarity)
			+ (0.20 * $tokenOverlap)
			+ $domainBoost
			+ $contextBoost
			+ $qualityBoost
			+ $usageBoost
			+ $substringBoost;

		return max(0.0, min(1.0, $score));
	}

	private static function _qualityRank(string $quality): int
	{
		return match ($quality) {
			'approved' => 4,
			'manual' => 3,
			'imported' => 2,
			'mt' => 1,
			default => 0,
		};
	}

	/**
	 * Collapse identical exact translations into one suggestion while preserving
	 * the strongest quality signal and total usage count across duplicates.
	 *
	 * @param list<array<string, mixed>> $rows
	 * @return list<array<string, mixed>>
	 */
	private static function _dedupeExactSuggestions(array $rows): array
	{
		$deduped = [];

		foreach ($rows as $row) {
			$targetText = (string) ($row['target_text'] ?? '');
			$signature = implode('|', [
				$targetText,
				(string) ($row['domain'] ?? ''),
				(string) ($row['source_key'] ?? ''),
				(string) ($row['context'] ?? ''),
			]);

			if (!isset($deduped[$signature])) {
				$deduped[$signature] = $row;

				continue;
			}

			$existing = $deduped[$signature];
			$deduped[$signature]['usage_count'] = ((int) ($existing['usage_count'] ?? 0)) + ((int) ($row['usage_count'] ?? 0));

			if (self::_qualityRank((string) ($row['quality_score'] ?? '')) > self::_qualityRank((string) ($existing['quality_score'] ?? ''))) {
				$deduped[$signature]['quality_score'] = $row['quality_score'];
			}
		}

		return array_values($deduped);
	}

	private static function _tokenOverlap(string $left, string $right): float
	{
		$leftTokens = self::_extractTokens($left);
		$rightTokens = self::_extractTokens($right);

		if (empty($leftTokens) || empty($rightTokens)) {
			return 0.0;
		}

		$intersection = array_intersect($leftTokens, $rightTokens);
		$union = array_unique(array_merge($leftTokens, $rightTokens));

		return count($intersection) / count($union);
	}

	/**
	 * @return list<string>
	 */
	private static function _extractTokens(string $text): array
	{
		$tokens = preg_split('/[^\p{L}\p{N}_]+/u', $text) ?: [];
		$tokens = array_filter(array_map('trim', $tokens), static fn (string $token): bool => $token !== '');

		return array_values(array_unique($tokens));
	}

	/**
	 * @return list<string>
	 */
	public static function extractPlaceholders(string $text): array
	{
		return self::_extractPlaceholders($text);
	}

	/**
	 * @return list<string>
	 */
	private static function _extractPlaceholders(string $text): array
	{
		if (!preg_match_all('/\{([A-Za-z0-9_]+)\}/', $text, $matches)) {
			return [];
		}

		$placeholders = array_values(array_unique($matches[1]));
		sort($placeholders);

		return $placeholders;
	}

	/**
	 * @param list<string> $left
	 * @param list<string> $right
	 */
	private static function _samePlaceholderSet(array $left, array $right): bool
	{
		sort($left);
		sort($right);

		return $left === $right;
	}
}
