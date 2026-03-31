<?php

class EventTagsAjax extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return PolicyDecision::allow();
	}

	public function run(): void
	{
		try {
			$term = urldecode((string) Request::getRequired('term'));
			$tag_context = Request::getRequired('tag_context');
		} catch (RequestParamException $e) {
			ApiResponse::renderError($e->code_id, $e->getMessage(), 400);

			return;
		}

		$context = trim((string) $tag_context);

		if ($context === '') {
			ApiResponse::renderError('INVALID_CONTEXT', 'Context is required.', 400);

			return;
		}

		if (!PluginRegistry::hasTagContext($context)) {
			ApiResponse::renderError('UNKNOWN_CONTEXT', 'Unknown tag context.', 400);

			return;
		}

		$tags = EntityTag::getMatchingTagNames($context, $term);

		ApiResponse::renderSuccess(array_values($tags));
	}
}
