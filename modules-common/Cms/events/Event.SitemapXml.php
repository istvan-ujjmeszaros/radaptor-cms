<?php

class EventSitemapXml extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return PolicyDecision::allow();
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'sitemap.xml',
			'group' => 'Runtime',
			'name' => 'Render XML sitemap',
			'summary' => 'Streams the sitemap XML generated from known webpage resources.',
			'description' => 'Collects webpage URLs from the resource tree and renders them through the sitemap XML template.',
			'request' => [
				'method' => 'GET',
				'params' => [],
			],
			'response' => [
				'kind' => 'xml',
				'content_type' => 'application/xml',
				'description' => 'Returns XML sitemap content rendered through the sitemap template.',
			],
			'authorization' => [
				'visibility' => 'public',
				'description' => 'The sitemap endpoint is public.',
			],
			'notes' => BrowserEventDocumentationHelper::lines(
				'This route is intended for crawlers and sitemap consumers, not for interactive admin workflows.'
			),
			'side_effects' => [],
		];
	}

	public function run(): void
	{
		$webpages = ResourceTreeHandler::getResourceListForSelect('webpage', false);

		$urls = [];

		foreach ($webpages as $webpage) {
			if (!isset($webpage['value']) || $webpage['value'] == '') {
				continue;
			}

			$urls[] = $webpage['label'];
		}

		$template = new Template('sitemapXml');
		$template->setMime(Template::MIME_XML);

		$template->props['host'] = Url::getCurrentHost(false);
		$template->props['urls'] = $urls;

		$template->render();
	}
}
