<?php

abstract class AbstractWebpageViewComposer extends AbstractWebpageViewBase
{
	abstract public function getOutputChannel(): string;
	abstract public function getLangId(): string;
	abstract public function getTitle(): string;

	/** @noinspection PhpUndefinedFunctionInspection */
	protected function _compose(): void
	{
		if (!empty($this->_content)) {
			return;
		}

		$tree_builder = new WebpageTreeBuilder($this);
		$page_tree = $tree_builder->build();

		if ($this->getOutputChannel() === WebpageView::OUTPUT_CHANNEL_SDUI_JSON) {
			WebpageView::header('Content-Type: application/json; charset=UTF-8');
			$renderer = new SduiJsonTreeRenderer();
			$this->_content = $renderer->render($page_tree, ['lang_id' => $this->getLangId()]);

			return;
		}

		self::beginRadaptorDebugSessionIfAvailable();
		WebpageView::header('Content-Type: text/html; charset=UTF-8');
		$renderer = new HtmlTreeRenderer(
			theme: $this->getTheme(),
			lang_id: $this->getLangId(),
			page_id: $this->getPageId(),
			title: $this->getTitle(),
			description: $this->getRawDescription(),
			pagedata: $this->getAllPagedata(),
			is_editable: $this->isEditable(),
		);
		$this->_content = $renderer->render($page_tree);

		/*  törli az üres tag-eket, ami problémákat okoz! */
		if (Config::DEV_ENABLE_TIDY_OUTPUT->value() && extension_loaded('tidy')) {
			$tidy = new tidy();
			$tidy_config = [
				'char-encoding' => 'utf8',
				'indent' => true,
				'indent-spaces' => 2,
				'wrap' => 256,
				'hide-comments' => false,
				// Beware that enabling this will remove the debug annotations!
			];
			$this->_content = $tidy->repairString($this->_content, $tidy_config, 'utf8');
		}

		if (!Config::DEV_APP_DEBUG_INFO->value() && Config::DEV_APP_MINIFY_HTML->value()) {
			$this->_content = preg_replace('/<!--[^\[|(<!)|>]([\s\S])*?-->/m', '', $this->_content); // removing all comments
			$this->_content = preg_replace('/^\s+/m', '', $this->_content);                          // removing whitespace from the beginning of lines
		}

		if (self::isRadaptorDebugEnabled()) {
			$this->_content = self::appendRadaptorDebugBootstrap($this->_content, $renderer->getBootstrap());
			WebpageView::header('Radaptor-Debug: 1');
			WebpageView::header('Radaptor-Debug-Request: ' . self::radaptorDebugRequestId());
			WebpageView::header('Radaptor-Debug-Renderer: html');
			WebpageView::header('Radaptor-Debug-Features: ' . implode(',', self::radaptorDebugFeatures()));
		}

		if (!self::isRadaptorDebugEnabled()
			&& RequestContextHolder::isPersistentCacheWriteEnabled()
			&& ResourceTreeHandler::canAccessResource($this->getPageId(), ResourceAcl::_ACL_VIEW)
		) {
			global $persistentCache;
			assert($persistentCache instanceof iPersistentCache);
			$server = RequestContextHolder::current()->SERVER;
			$requestUri = (string)($server['REQUEST_URI'] ?? $_SERVER['REQUEST_URI'] ?? '/');
			$cacheKey = 'user:' . User::getCurrentUserId() . ':REQUEST_URI:' . $requestUri;
			$persistentCache->setEx($cacheKey, 60, brotli_compress($this->_content, 11));
		}
	}

	private static function beginRadaptorDebugSessionIfAvailable(): void
	{
		if (class_exists(DebugSession::class)) {
			DebugSession::beginIfRequested();
		}
	}

	private static function isRadaptorDebugEnabled(): bool
	{
		return class_exists(DebugSession::class) && DebugSession::isEnabled();
	}

	/**
	 * @param array<string, mixed> $bootstrap
	 */
	private static function appendRadaptorDebugBootstrap(string $html, array $bootstrap): string
	{
		$json = json_encode($bootstrap, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		if (!is_string($json)) {
			return $html;
		}

		$script = '<script id="__RADAPTOR_DEBUG__" type="application/json">' . $json . '</script>';
		$body_close_position = strripos($html, '</body>');

		if ($body_close_position === false) {
			return $html . $script;
		}

		return substr($html, 0, $body_close_position)
			. $script
			. substr($html, $body_close_position);
	}

	private static function radaptorDebugRequestId(): string
	{
		return class_exists(DebugSession::class) ? DebugSession::requestId() : '';
	}

	/**
	 * @return list<string>
	 */
	private static function radaptorDebugFeatures(): array
	{
		if (!class_exists(DebugSession::class)) {
			return ['tree', 'dommap', 'timings'];
		}

		$features = DebugSession::features();
		$features = array_values(array_map('strval', is_array($features) ? $features : []));

		return $features !== [] ? $features : ['tree', 'dommap', 'timings'];
	}
}
