<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$widgets = $this->props['widgets'];
$allThemes = $this->props['allThemes'] ?? [];
$templateScopeNote = (string)($this->props['templateScopeNote'] ?? '');
?>
<div class="widget-preview-list">
	<h3><?= e($this->strings['cms.widget_preview.title']) ?></h3>
	<?php if ($templateScopeNote !== ''): ?>
		<p style="color: #666;"><?= e($templateScopeNote) ?></p>
	<?php endif; ?>
	<?php if (empty($widgets)): ?>
		<p><em><?= e($this->strings['cms.widget_preview.none']) ?></em></p>
	<?php else: ?>
		<table style="width: 100%; border-collapse: collapse;">
			<thead>
				<tr style="background: #f5f5f5;">
					<th style="text-align: left; padding: 8px; border-bottom: 2px solid #ddd;"><?= e($this->strings['cms.widget_preview.widget']) ?></th>
					<th style="text-align: left; padding: 8px; border-bottom: 2px solid #ddd;"><?= e($this->strings['cms.widget_preview.description']) ?></th>
					<th style="text-align: left; padding: 8px; border-bottom: 2px solid #ddd;"><?= e($this->strings['cms.widget_preview.implemented']) ?></th>
					<th style="text-align: left; padding: 8px; border-bottom: 2px solid #ddd;"><?= e($this->strings['cms.widget_preview.not_implemented']) ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($widgets as $widget): ?>
					<?php $unavailableThemes = array_diff($allThemes, $widget['themes']); ?>
					<tr>
						<td style="padding: 8px; border-bottom: 1px solid #eee;"><a href="?widget=<?= urlencode((string)$widget['name']) ?>"><code><?= htmlspecialchars($widget['name'], ENT_QUOTES | ENT_SUBSTITUTE); ?></code></a></td>
						<td style="padding: 8px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($widget['description'], ENT_QUOTES | ENT_SUBSTITUTE); ?></td>
						<td style="padding: 8px; border-bottom: 1px solid #eee;">
							<?php if (empty($widget['themes'])): ?>
								<span style="color: #999;">-</span>
							<?php else: ?>
								<?php $themeLinks = array_map(function ($theme) use ($widget) {
									$url = '?widget=' . urlencode($widget['name']) . '&theme=' . urlencode($theme);

									return '<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE) . '">'
										. htmlspecialchars($theme, ENT_QUOTES | ENT_SUBSTITUTE) . '</a>';
								}, $widget['themes']); ?>
								<?= implode(', ', $themeLinks); ?>
							<?php endif; ?>
							<div style="margin-top: 4px; color: #666; font-size: 12px;"><code><?= htmlspecialchars((string)($widget['template_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE); ?></code></div>
						</td>
						<td style="padding: 8px; border-bottom: 1px solid #eee;">
							<?php if (empty($unavailableThemes)): ?>
								<span style="color: #999;">-</span>
							<?php else: ?>
								<?php $fallbackLinks = array_map(function ($theme) use ($widget) {
									$url = '?widget=' . urlencode($widget['name']) . '&theme=' . urlencode($theme);

									return '<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE) . '">'
										. htmlspecialchars($theme, ENT_QUOTES | ENT_SUBSTITUTE) . '</a>';
								}, $unavailableThemes); ?>
								<?= implode(', ', $fallbackLinks); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
