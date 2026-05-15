<?php

class EventTagsTagDescription extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return PolicyDecision::allow();
	}

	public function run(): void
	{
		try {
			$tag_name = urldecode((string) Request::getRequired('item_id'));
			$tag_context = trim((string) Request::getRequired('tag_context'));
		} catch (RequestParamException $e) {
			http_response_code(400);
			echo '<i>' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE) . '</i>';

			return;
		}

		self::renderTagDescription($tag_context, $tag_name);
	}

	public static function renderTagDescription(string $tag_context, string $tag_name): void
	{
		if ($tag_context === '' || !PackageTagContextRegistry::has($tag_context)) {
			http_response_code(400);
			echo '<i>' . htmlspecialchars(t('tags.validation.unknown_context'), ENT_QUOTES | ENT_SUBSTITUTE) . '</i>';

			return;
		}

		$tag_id = EntityTag::getTagId($tag_context, $tag_name);

		if ($tag_id === null) {
			echo '<i>' . htmlspecialchars(t('common.no_description'), ENT_QUOTES | ENT_SUBSTITUTE) . '</i>';

			return;
		}

		$tag_data = EntityTag::getTagValues($tag_id);
		$description = trim(strip_tags((string) ($tag_data['description'] ?? '')));

		if ($description === '') {
			echo '<i>' . htmlspecialchars(t('common.no_description'), ENT_QUOTES | ENT_SUBSTITUTE) . '</i>';
		} else {
			echo (string) $tag_data['description'];
		}
	}
}
