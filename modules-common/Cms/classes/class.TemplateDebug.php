<?php

class TemplateDebug extends TemplateList
{
	protected static int $_levels = 0;
	protected int $_level;
	protected TemplateDebugType $_debugType = TemplateDebugType::DEBUG_HTML;

	protected function addDebugInfo(Template $template, string $content, string $widgetName = ''): string
	{
		if (!(Config::DEV_APP_DEBUG_INFO->value() && Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER))) {
			return $content;
		}

		// PHPStorms inspector was too picky about initializing these
		$begin_comment_begin = "";
		$begin_comment_end = "";
		$end_comment_begin = "";
		$end_comment_end = "";

		switch ($this->_debugType) {
			case TemplateDebugType::DEBUG_HTML:

				$begin_comment_begin = "<!--- ";
				$begin_comment_end = " -->";

				$end_comment_begin = "<!--- ";
				$end_comment_end = " -->";

				break;

			case TemplateDebugType::DEBUG_JAVASCRIPT:

				$begin_comment_begin = "// ";

				$end_comment_begin = "// ";

				break;

			case TemplateDebugType::DEBUG_JAVASCRIPT_HTML:

				$begin_comment_begin = "// ";

				$end_comment_begin = "<!--- ";
				$end_comment_end = " -->";

				break;

			case TemplateDebugType::DEBUG_HTML_JAVASCRIPT:

				$begin_comment_begin = "<!--- ";
				$begin_comment_end = " -->";

				$end_comment_begin = "// ";

				break;

			case TemplateDebugType::DEBUG_JSON:

				return $content;
		}

		$line_length = 150;

		$padding = str_repeat("    ", $this->_level);

		// Build debug info with template name, theme, and source
		$templateName = $template->getTemplateName();
		$templatePath = $template->_templatePath;

		// Determine theme source
		$themeName = null;
		$isThemed = false;
		$foundThemedKey = ThemedTemplateList::getKeyForPath($templatePath);

		if ($foundThemedKey !== null) {
			// Extract theme name from key like "widgetEdit.RadaptorPortalAdmin"
			$parts = explode('.', $foundThemedKey);

			if (count($parts) >= 2) {
				$themeName = end($parts);
				$isThemed = true;
			}
		}

		// If no themed key found, check if we have a renderer with theme info
		if ($themeName === null && $template->getRenderer() !== null) {
			$theme = $template->getRenderer()->getTheme();

			if ($theme !== null) {
				$themeName = $theme::getName();
				// Check if this theme has a version of this template
				$themedPath = ThemedTemplateList::getThemedTemplatePath($templateName, $themeName);
				$isThemed = ($themedPath !== null);
			}
		}

		// Build the debug text
		$debug_info_text = $templateName;

		if ($themeName !== null) {
			$debug_info_text .= ' @' . $themeName;

			if (!$isThemed) {
				$debug_info_text .= '(base)';
			}
		} else {
			$debug_info_text .= ' @base';
		}

		// Add widget name if provided
		if ($widgetName != "") {
			$debug_info_text .= ' [Widget:' . $widgetName . ']';
		}

		// Add short path for easier file location
		$shortPath = PackagePathHelper::shortenPath($templatePath);
		$debug_info_text .= ' {' . $shortPath . '}';

		$begin_padding = "";
		$end_padding = "";

		do {
			$begin_padding .= "^";

			$begin = $begin_comment_begin . $padding . $begin_padding . " BEGIN: " . $debug_info_text . " " . $begin_padding . $begin_comment_end;

			if (mb_strlen($begin) == $line_length - 1) {
				$begin = $begin_comment_begin . $padding . $begin_padding . " BEGIN: " . $debug_info_text . " " . $begin_padding . "^" . $begin_comment_end;
			}
		} while (mb_strlen($begin) < $line_length - 1);

		do {
			$end_padding .= "^";

			$end = $end_comment_begin . $padding . $end_padding . " EOF: " . $debug_info_text . " " . $end_padding . $end_comment_end;

			if (mb_strlen($end) == $line_length - 1) {
				$end = $end_comment_begin . $padding . $end_padding . " EOF: " . $debug_info_text . " " . $end_padding . "ˇ" . $end_comment_end;
			}
		} while (mb_strlen($end) < $line_length - 1);

		return "\n" . $begin . "\n" . $content . "\n" . $end . "\n";
	}

	public function setDebugType(TemplateDebugType $debugType): void
	{
		$this->_debugType = $debugType;
	}
}
