<?php

class EventSitemapXml extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return PolicyDecision::allow();
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
