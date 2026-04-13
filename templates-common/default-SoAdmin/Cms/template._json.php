<?php

assert(isset($this) && $this instanceof Template);

if (!function_exists('multidimensionalArrayMap')) {
	function multidimensionalArrayMap(callable $fn, array $arr): array
	{
		$newArr = [];

		foreach ($arr as $key => $value) {
			$newArr[$key] = (is_array($value) ? multidimensionalArrayMap($fn, $value) : $fn($value));
		}

		return $newArr;
	}
}

if (!function_exists('filter_nulls')) {
	function filter_nulls($value)
	{
		return is_null($value) ? '' : $value;
	}
}

$response = $this->props['api_response'] ?? null;

if (!$response instanceof ApiResponse) {
	$response = ApiResponse::error(
		new ApiError('API_RESPONSE_REQUIRED', 'TemplateJson expects ApiResponse in props[api_response].'),
		500
	);
}

$this->setMime(Template::MIME_JSON);
$this->setDebugType(TemplateDebugType::DEBUG_JSON);

http_response_code($response->getHttpCode());

echo json_encode(multidimensionalArrayMap('filter_nulls', $response->toArray()));
