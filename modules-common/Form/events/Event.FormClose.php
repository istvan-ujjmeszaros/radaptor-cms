<?php

class EventFormClose extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return PolicyDecision::allow();
	}

	public function run(): void
	{
		WebpageView::header('Content-Type: text/html; charset=UTF-8');

		$template = new Template('form.closer');
		$template->strings = [
			'form.close_page.title' => t('form.close_page.title'),
			'form.close_page.close_window' => t('form.close_page.close_window'),
		];

		$template->render();
	}
}
