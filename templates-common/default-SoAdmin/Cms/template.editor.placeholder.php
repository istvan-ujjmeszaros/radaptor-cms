<?php assert(isset($this) && $this instanceof Template); ?>
<input class="placeholder_<?= $this->props['widgetType']; ?>" readonly="readonly" style="border: 1px dotted red; padding: 10px 0; width: 95%; background-color: yellow; text-align: <?= $this->props['text_align']; ?>;" title="<?= $this->props['title']; ?>" type="text" value="<?= $this->props['value']; ?>"/>
