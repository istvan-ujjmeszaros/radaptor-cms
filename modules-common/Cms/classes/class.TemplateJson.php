<?php

class TemplateJson extends Template
{
	public function __construct(ApiResponse $response)
	{
		$this->props['api_response'] = $response;
		parent::__construct('_json');
	}
}
