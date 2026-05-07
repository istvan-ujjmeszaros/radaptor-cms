<?php assert(isset($this) && $this instanceof Template); ?>
<div style="background-color:yellow"><?= e(t('cms.template.missing')) ?> <?= e($this->getTemplateName()); ?></div>
