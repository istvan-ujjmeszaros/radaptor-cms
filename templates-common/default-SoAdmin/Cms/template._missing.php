<?php assert(isset($this) && $this instanceof Template); ?>
<div style="background-color:yellow">Missing template: <?= $this->getTemplateName(); ?></div>
