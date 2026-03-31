<?php assert(isset($this) && $this instanceof Template); ?>
<div style="background-color:yellow;color:#000;padding:10px;">
	<strong><?= e((string) ($this->props['heading'] ?? ($this->strings['common.missing_required_url_params'] ?? ''))) ?>:</strong>
	<ul style="margin:5px 0 0 20px;">
		<?php foreach ($this->props['missing'] as $param => $description): ?>
			<li><code><?= htmlspecialchars($param) ?></code> – <?= htmlspecialchars($description) ?></li>
		<?php endforeach; ?>
	</ul>
</div>
