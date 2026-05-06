<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$widgetName = $this->props['widgetName'];
$templateName = $this->props['templateName'];
$currentTheme = $this->props['currentTheme'];
$templateScopeNote = (string)($this->props['templateScopeNote'] ?? '');
$themesWithWidgetTemplate = $this->props['themesWithWidgetTemplate'];
$allThemes = $this->props['allThemes'];
$themesWithoutWidgetTemplate = array_diff($allThemes, $themesWithWidgetTemplate);
$jsonPreviewUrl = (string)($this->props['jsonPreviewUrl'] ?? '');
$serverPreviewTitle = (string)($this->props['serverPreviewTitle'] ?? 'Server HTML preview');
$jsonPreviewTitle = (string)($this->props['jsonPreviewTitle'] ?? 'Widget subtree JSON');
$jsonPreviewDescription = (string)($this->props['jsonPreviewDescription'] ?? '');
$openJsonLabel = (string)($this->props['openJsonLabel'] ?? 'Open JSON');
?>
<div class="widget-preview-info" style="margin-bottom: 20px; padding: 15px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px;">
	<h4 style="margin-top: 0;"><?= e($this->strings['cms.widget_preview.info.title']) ?></h4>
	<table style="margin-bottom: 15px;">
		<tr>
			<th style="width: 150px; text-align: left; padding: 4px 8px 4px 0;"><?= e($this->strings['cms.widget_preview.info.widget']) ?>:</th>
			<td><code><?= htmlspecialchars($widgetName, ENT_QUOTES | ENT_SUBSTITUTE); ?></code></td>
		</tr>
		<tr>
			<th style="text-align: left; padding: 4px 8px 4px 0;"><?= e($this->strings['cms.widget_preview.info.template']) ?>:</th>
			<td><code><?= htmlspecialchars($templateName, ENT_QUOTES | ENT_SUBSTITUTE); ?></code></td>
		</tr>
		<tr>
			<th style="text-align: left; padding: 4px 8px 4px 0;"><?= e($this->strings['cms.widget_preview.info.current_theme']) ?>:</th>
			<td><strong><?= htmlspecialchars($currentTheme, ENT_QUOTES | ENT_SUBSTITUTE); ?></strong></td>
		</tr>
	</table>
	<?php if (count($themesWithWidgetTemplate) > 0): ?>
		<div style="margin-bottom: 8px;">
			<span style="color: #666; font-size: 12px;"><?= e($this->strings['cms.widget_preview.info.implemented']) ?>:</span>
			<?php foreach ($themesWithWidgetTemplate as $theme): ?>
				<?php if ($theme === $currentTheme): ?>
					<strong style="margin-left: 8px;"><?= htmlspecialchars($theme, ENT_QUOTES | ENT_SUBSTITUTE); ?></strong>
				<?php else: ?>
					<a style="margin-left: 8px;" href="?<?= http_build_query(['widget' => $widgetName, 'theme' => $theme]); ?>"><?= htmlspecialchars($theme, ENT_QUOTES | ENT_SUBSTITUTE); ?></a>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php if (count($themesWithoutWidgetTemplate) > 0): ?>
		<div style="margin-bottom: 8px;">
			<span style="color: #666; font-size: 12px;"><?= e($this->strings['cms.widget_preview.info.fallback']) ?>:</span>
			<?php foreach ($themesWithoutWidgetTemplate as $theme): ?>
				<?php if ($theme === $currentTheme): ?>
					<strong style="color: #6c757d; margin-left: 8px;"><?= htmlspecialchars($theme, ENT_QUOTES | ENT_SUBSTITUTE); ?></strong>
				<?php else: ?>
					<a style="color: #6c757d; margin-left: 8px;" href="?<?= http_build_query(['widget' => $widgetName, 'theme' => $theme]); ?>"><?= htmlspecialchars($theme, ENT_QUOTES | ENT_SUBSTITUTE); ?></a>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php if ($templateScopeNote !== ''): ?>
		<div style="color: #666; font-size: 12px;"><?= e($templateScopeNote) ?></div>
	<?php endif; ?>
</div>
<div style="border: 1px solid #ddd; border-radius: 4px; background: #fff; margin-bottom: 24px;">
	<div style="padding: 12px 16px; border-bottom: 1px solid #ddd; font-weight: bold;"><?= e($serverPreviewTitle) ?></div>
	<div style="padding: 16px;">
		<?= $this->fetchContent('preview'); ?>
	</div>
</div>
<div style="border: 1px solid #ddd; border-radius: 4px; background: #fff;" data-controller="sdui-json-preview" data-sdui-json-preview-json-url-value="<?= e($jsonPreviewUrl) ?>" data-sdui-json-preview-preview-component-value="widgetPreviewInfo" data-sdui-json-preview-preview-slot-value="preview">
	<div style="padding: 12px 16px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; gap: 12px;">
		<div>
			<div style="font-weight: bold;"><?= e($jsonPreviewTitle) ?></div>
			<?php if ($jsonPreviewDescription !== ''): ?>
				<div style="color: #666; font-size: 12px; margin-top: 4px;"><?= e($jsonPreviewDescription) ?></div>
			<?php endif; ?>
		</div>
		<?php if ($jsonPreviewUrl !== ''): ?>
			<a href="<?= e($jsonPreviewUrl) ?>" target="_blank" rel="noreferrer"><?= e($openJsonLabel) ?></a>
		<?php endif; ?>
	</div>
	<div style="padding: 16px;">
		<div style="margin-bottom: 12px; padding: 10px 12px; border: 1px solid #ddd; background: #f8f9fa;" data-sdui-json-preview-target="status">Loading JSON preview…</div>
		<div style="color: #666; font-size: 12px; margin-bottom: 12px;"><?= e('Showing the widget subtree extracted from the full SDUI JSON document.') ?></div>
		<pre style="overflow: auto; margin: 0; padding: 12px; border-radius: 4px; background: #111827; color: #f9fafb; font-size: 12px; min-height: 480px;" data-sdui-json-preview-target="source"></pre>
	</div>
</div>
