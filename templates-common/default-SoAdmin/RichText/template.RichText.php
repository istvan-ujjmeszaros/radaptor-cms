<?php
assert(isset($this) && $this instanceof Template);
$anchor = $this->props['extraparams']['anchor'] ?? '';
$style = $this->props['style'] ?? '';
$class = $this->props['class'] ?? '';
$title = $this->props['title'] ?? '';
$content = $this->props['content'] ?? '';
?>
<article<?= $anchor ? " id=\"$anchor\"" : ''; ?><?= $style ? " style=\"$style\"" : ''; ?><?= $class ? " class=\"$class\"" : ''; ?>>
	<?= $title ? "<h1>$title</h1>" : ''; ?>
	<?= $content; ?>
</article>
