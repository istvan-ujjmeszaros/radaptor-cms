<?php

assert(isset($this) && $this instanceof Template);

if (!$this->props['use_customizable_wrapper']) {
	echo $this->fetchContent('content');
} else {
	$attributes = [];

	// Collect attributes dynamically
	if (!empty($this->props['extraparams']['anchor'])) {
		$attributes['id'] = $this->props['extraparams']['anchor'];
	}

	if ($this->props['style'] !== '') {
		$attributes['style'] = $this->props['style'];
	}

	if ($this->props['class'] !== '') {
		$attributes['class'] = $this->props['class'];
	}

	// Create the attribute string for the HTML tag
	$attributeString = '';

	foreach ($attributes as $key => $value) {
		$attributeString .= " {$key}=\"{$value}\"";
	}

	// Echo the div with dynamic attributes
	echo "<div$attributeString>";
	echo $this->fetchContent('content');
	echo "</div>";

	// Clear both if it is the last item
	if (!empty($this->props['settings']['is_last'])) {
		echo '<div style="clear:both;"></div>';
	}
}
