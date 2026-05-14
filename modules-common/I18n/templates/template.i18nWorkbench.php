<?php assert(isset($this) && $this instanceof Template); ?>
<?php library('DATATABLES'); ?>
<?php registerI18n([
	'admin.i18n.title',
	'admin.i18n.locale',
	'admin.i18n.domain',
	'admin.i18n.search_placeholder',
	'admin.i18n.col.domain',
	'admin.i18n.col.key',
	'admin.i18n.col.source',
	'admin.i18n.col.translation',
	'admin.i18n.col.reviewed',
	'admin.i18n.col.allow_source_match',
	'admin.i18n.allow_source_match.help',
	'admin.i18n.coverage.title',
	'admin.i18n.coverage.translated',
	'admin.i18n.coverage.reviewed',
	'admin.i18n.coverage.missing',
	'admin.i18n.coverage.stale',
	'admin.i18n.tm.title',
	'admin.i18n.tm.exact_then_similar',
	'admin.i18n.tm.exact_matches',
	'admin.i18n.tm.similar_matches',
	'admin.i18n.tm.no_exact_matches',
	'admin.i18n.tm.no_similar_matches',
	'admin.i18n.tm.safe',
	'admin.i18n.tm.review_placeholders',
	'admin.i18n.tm.origin',
	'admin.i18n.tm.source_example',
	'admin.i18n.tm.similarity',
	'common.actions',
	'common.all',
	'common.search',
	'common.loading',
	'common.error',
	'common.error_save',
	'workbench.tm_no_results',
	'workbench.tm_uses',
	'workbench.tm_apply',
]); ?>
<?php
/** @var list<array{value: string, label: string}> $localeOptions */
$localeOptions = $this->props['locale_options'] ?? [];

/** @var list<array{value: string, label: string}> $domainOptions */
$domainOptions = $this->props['domain_options'] ?? [];
$selectedLocale = (string) ($this->props['selected_locale'] ?? 'en-US');
$selectedDomain = (string) ($this->props['selected_domain'] ?? '');
$selectedSearch = (string) ($this->props['selected_search'] ?? '');
$coverageSummary = is_array($this->props['coverage_summary'] ?? null) ? $this->props['coverage_summary'] : [];
$coverageLocales = is_array($coverageSummary['locales'] ?? null) ? $coverageSummary['locales'] : [];
$reviewedLabel = t('admin.i18n.col.reviewed');
$allowSourceMatchLabel = t('admin.i18n.col.allow_source_match');

if ($reviewedLabel === 'admin.i18n.col.reviewed') {
	$reviewedLabel = 'Reviewed';
}
?>
<style>
body.i18n-tm-open {
    overflow: hidden;
}

.i18n-tm-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.22);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    opacity: 0;
    pointer-events: none;
    transition: opacity 180ms ease;
    z-index: 1040;
}

.i18n-tm-backdrop.is-open {
    opacity: 1;
    pointer-events: auto;
}

.i18n-tm-panel {
    position: fixed;
    top: 1rem;
    right: 1rem;
    bottom: 1rem;
    width: min(26rem, calc(100vw - 2rem));
    transform: translateX(calc(100% + 1rem));
    transition: transform 220ms ease;
    z-index: 1050;
    display: flex;
    flex-direction: column;
    pointer-events: none;
}

.i18n-tm-panel.is-open {
    transform: translateX(0);
    pointer-events: auto;
}

.i18n-tm-panel__card {
    display: flex;
    flex-direction: column;
    height: 100%;
    border-radius: 1rem;
    overflow: hidden;
    box-shadow: 0 1.25rem 3rem rgba(15, 23, 42, 0.24);
}

.i18n-tm-panel__header {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    align-items: flex-start;
    padding: 1rem 1rem 0.75rem 1rem;
    border-bottom: 1px solid rgba(148, 163, 184, 0.25);
}

.i18n-tm-panel__title {
    margin: 0;
    font-size: 1.05rem;
}

.i18n-tm-panel__note {
    margin: 0.2rem 0 0 0;
    color: #94a3b8;
    font-size: 0.82rem;
    line-height: 1.35;
}

.i18n-tm-panel__context {
    margin-top: 0.8rem;
}

.i18n-tm-panel__key {
    margin: 0;
    color: #f8fafc;
    font-size: 0.98rem;
    font-weight: 700;
    line-height: 1.3;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    word-break: break-word;
}

.i18n-tm-panel__meta {
    margin-top: 0.25rem;
    color: #94a3b8;
    font-size: 0.8rem;
    font-weight: 600;
}

.i18n-tm-panel__body {
    flex: 1 1 auto;
    overflow-y: auto;
    padding: 0.95rem 1rem 1rem 1rem;
}

.i18n-tm-panel__source {
    margin-bottom: 0.9rem;
    padding: 0.8rem 0.9rem;
    border-radius: 0.8rem;
    border: 1px solid rgba(226, 232, 240, 0.14);
    background: rgba(15, 23, 42, 0.34);
}

.i18n-tm-panel__source-label {
    margin: 0;
    color: #94a3b8;
    font-size: 0.71rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.i18n-tm-panel__source-text {
    margin-top: 0.35rem;
    color: #f8fafc;
    line-height: 1.4;
    white-space: pre-wrap;
}

.i18n-tm-item {
    border: 1px solid rgba(148, 163, 184, 0.22);
    border-radius: 0.8rem;
    padding: 0.75rem 0.8rem;
    background: rgba(15, 23, 42, 0.62);
}

.i18n-tm-item + .i18n-tm-item {
    margin-top: 0.6rem;
}

.i18n-tm-item__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}

.i18n-tm-item__text {
    font-size: 0.92rem;
    line-height: 1.4;
    color: #f8fafc;
    flex: 1 1 auto;
}

.i18n-tm-item__meta {
    margin-top: 0.32rem;
    color: #94a3b8;
    font-size: 0.78rem;
}

.i18n-tm-item__origin {
    margin-top: 0.32rem;
    color: #cbd5e1;
    font-size: 0.78rem;
    line-height: 1.35;
}

.i18n-tm-item__origin-label {
    color: #94a3b8;
    font-weight: 700;
    margin-right: 0.25rem;
}

.i18n-tm-section + .i18n-tm-section {
    margin-top: 0.95rem;
}

.i18n-tm-section__title {
    margin: 0 0 0.5rem 0;
    color: #cbd5e1;
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.i18n-tm-item__source {
    margin-top: 0.4rem;
    color: #cbd5e1;
    font-size: 0.78rem;
    line-height: 1.4;
    border-left: 2px solid rgba(148, 163, 184, 0.35);
    padding-left: 0.55rem;
}

.i18n-tm-item__badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.5rem;
}

.i18n-tm-item__badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.2rem 0.5rem;
    font-size: 0.72rem;
    font-weight: 700;
}

.i18n-tm-item__badge--safe {
    background: rgba(22, 163, 74, 0.12);
    color: #166534;
}

.i18n-tm-item__badge--review {
    background: rgba(245, 158, 11, 0.14);
    color: #92400e;
}

.i18n-tm-item__badge--similarity {
    background: rgba(59, 130, 246, 0.12);
    color: #1d4ed8;
}

.i18n-tm-item__actions {
    flex: 0 0 auto;
}

.i18n-tm-item__actions .btn {
    white-space: nowrap;
}

.i18n-tm-panel__close {
    flex: 0 0 auto;
    line-height: 1;
    padding: 0.35rem 0.5rem;
}

.i18n-tm-empty {
    color: #94a3b8;
    font-size: 0.84rem;
    padding: 0.35rem 0;
}

.i18n-source-text {
    white-space: pre-wrap;
    line-height: 1.45;
}

.i18n-translation-cell {
    min-width: 20rem;
    position: relative;
    top: 0.67rem;
}

.i18n-workbench-filter--search {
    flex: 1 1 28rem;
    min-width: min(100%, 28rem);
}

.i18n-workbench-filter--search .form-control {
    width: 100%;
}

.i18n-translation-input-wrap {
    position: relative;
}

.i18n-translation-input {
    width: 100%;
    min-height: calc(1.5em + 0.5rem + 2px);
    overflow-y: hidden;
    resize: none;
    line-height: 1.4;
}

.i18n-reviewed-cell {
    text-align: center;
    vertical-align: middle;
    width: 5.5rem;
}

.i18n-source-match-cell {
    text-align: center;
    vertical-align: middle;
    width: 7rem;
}

.i18n-reviewed-cell .form-check,
.i18n-source-match-cell .form-check {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0;
    min-height: calc(1.5em + 0.5rem + 2px);
}

.i18n-inline-save-indicator {
    position: absolute;
    top: 0.32rem;
    right: 0.55rem;
    opacity: 0;
    pointer-events: none;
    transform: scale(0.92);
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.i18n-inline-save-indicator.is-visible {
    opacity: 1;
    transform: scale(1);
}

.i18n-row-state {
    min-height: 1rem;
}

.i18n-row-state--saving {
    color: #64748b;
}

.i18n-row-state--error {
    color: #b91c1c;
}

.i18n-coverage-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.i18n-coverage-summary__item {
    border: 1px solid rgba(148, 163, 184, 0.28);
    border-radius: 0.5rem;
    padding: 0.7rem 0.8rem;
}

.i18n-coverage-summary__locale {
    margin: 0;
    font-weight: 700;
}

.i18n-coverage-summary__meta {
    margin: 0.25rem 0 0 0;
    color: #64748b;
    font-size: 0.82rem;
    line-height: 1.35;
}
</style>
<div class="subheader">
    <h1><?= e(t('admin.i18n.title')) ?></h1>
</div>

<div
    data-controller="filter-state i18n-workbench"
    data-filter-state-debounce-ms-value="400"
    data-i18n-workbench-ajax-load-url-value="<?= ajax_url('i18n.ajaxLoad') ?>"
    data-i18n-workbench-ajax-save-url-value="<?= ajax_url('i18n.ajaxSave') ?>"
    data-i18n-workbench-ajax-tm-url-value="<?= ajax_url('i18n.ajaxTmSuggest') ?>"
    data-i18n-workbench-ajax-tm-fuzzy-url-value="<?= ajax_url('i18n.ajaxTmSuggestFuzzy') ?>"
>
    <div class="card shadow-sm">
        <div class="card-body">
            <!-- Filters -->
            <div class="d-flex gap-3 flex-wrap align-items-end mb-3">
                <div>
                    <label for="i18n-locale"><?= e(t('admin.i18n.locale')) ?></label><br>
                    <select id="i18n-locale" class="form-select form-select-sm"
                            data-filter-state-target="field"
                            data-filter-state-param="locale"
                            data-i18n-workbench-target="localeSelect">
                        <?php foreach ($localeOptions as $option) { ?>
                        <option value="<?= e($option['value']) ?>" <?= $option['value'] === $selectedLocale ? 'selected' : '' ?>>
                            <?= e($option['label']) ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
                <div>
                    <label for="i18n-domain"><?= e(t('admin.i18n.domain')) ?></label><br>
                    <select id="i18n-domain" class="form-select form-select-sm"
                            data-filter-state-target="field"
                            data-filter-state-param="domain"
                            data-i18n-workbench-target="domainSelect">
                        <option value=""><?= e(t('common.all')) ?></option>
                        <?php foreach ($domainOptions as $option) { ?>
                        <option value="<?= e($option['value']) ?>" <?= $option['value'] === $selectedDomain ? 'selected' : '' ?>><?= e($option['label']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="i18n-workbench-filter--search">
                    <label for="i18n-search"><?= e(t('common.search')) ?></label><br>
                    <input type="text" id="i18n-search" class="form-control form-control-sm"
                           value="<?= e($selectedSearch) ?>"
                           placeholder="<?= e(t('admin.i18n.search_placeholder')) ?>"
                           data-filter-state-target="field"
                           data-filter-state-param="search"
                           data-filter-state-sync="live"
                           data-i18n-workbench-target="searchInput">
                </div>
            </div>

            <?php if (!empty($coverageLocales)) { ?>
            <h2 class="h6 mb-2"><?= e(t('admin.i18n.coverage.title')) ?></h2>
            <div class="i18n-coverage-summary">
                <?php foreach ($coverageLocales as $coverageLocale) { ?>
                <div class="i18n-coverage-summary__item">
                    <p class="i18n-coverage-summary__locale"><?= e((string) ($coverageLocale['label'] ?? $coverageLocale['locale'] ?? '')) ?></p>
                    <p class="i18n-coverage-summary__meta">
                        <?= e(t('admin.i18n.coverage.translated')) ?>:
                        <?= e((string) ($coverageLocale['translated'] ?? 0)) ?>/<?= e((string) ($coverageLocale['total'] ?? 0)) ?>
                        (<?= e((string) ($coverageLocale['translated_percent'] ?? 0)) ?>%)<br>
                        <?= e(t('admin.i18n.coverage.reviewed')) ?>:
                        <?= e((string) ($coverageLocale['reviewed'] ?? 0)) ?><br>
                        <?= e(t('admin.i18n.coverage.missing')) ?>:
                        <?= e((string) ($coverageLocale['missing'] ?? 0)) ?>,
                        <?= e(t('admin.i18n.coverage.stale')) ?>:
                        <?= e((string) ($coverageLocale['stale'] ?? 0)) ?>
                    </p>
                </div>
                <?php } ?>
            </div>
            <?php } ?>

            <!-- Grid -->
            <div class="table-responsive">
                <table data-i18n-workbench-target="table" class="display" style="width:100%">
                    <thead>
                        <tr>
                            <th><?= e(t('admin.i18n.col.domain')) ?></th>
                            <th><?= e(t('admin.i18n.col.key')) ?></th>
                            <th><?= e(t('admin.i18n.col.source')) ?></th>
                            <th><?= e(t('admin.i18n.col.translation')) ?></th>
                            <th><?= e($reviewedLabel) ?></th>
                            <th><?= e($allowSourceMatchLabel) ?></th>
                            <th><?= e(t('common.actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="i18n-tm-backdrop"
         data-i18n-workbench-target="tmBackdrop"
         data-action="click->i18n-workbench#closeTmPanel"></div>

    <aside class="i18n-tm-panel"
           data-i18n-workbench-target="tmPanel"
           aria-hidden="true"
           role="dialog"
           aria-modal="true">
        <div class="card i18n-tm-panel__card">
            <div class="i18n-tm-panel__header">
                <div>
                    <h5 class="i18n-tm-panel__title"><?= e(t('admin.i18n.tm.title')) ?></h5>
                    <p class="i18n-tm-panel__note"><?= e(t('admin.i18n.tm.exact_then_similar')) ?></p>
                    <div class="i18n-tm-panel__context">
                        <p class="i18n-tm-panel__key" data-i18n-workbench-target="tmKey"></p>
                        <div class="i18n-tm-panel__meta" data-i18n-workbench-target="tmMeta"></div>
                    </div>
                </div>
                <button class="btn btn-sm btn-outline-secondary i18n-tm-panel__close"
                        title="<?= e(t('common.cancel')) ?>"
                        aria-label="<?= e(t('common.cancel')) ?>"
                        data-action="click->i18n-workbench#closeTmPanel">&times;</button>
            </div>
            <div class="i18n-tm-panel__body">
                <div class="i18n-tm-panel__source">
                    <p class="i18n-tm-panel__source-label"><?= e(t('admin.i18n.col.source')) ?></p>
                    <div class="i18n-tm-panel__source-text" data-i18n-workbench-target="tmSource"></div>
                </div>
                <section class="i18n-tm-section">
                    <h6 class="i18n-tm-section__title"><?= e(t('admin.i18n.tm.exact_matches')) ?></h6>
                    <div data-i18n-workbench-target="tmExact"></div>
                </section>
                <section class="i18n-tm-section">
                    <h6 class="i18n-tm-section__title"><?= e(t('admin.i18n.tm.similar_matches')) ?></h6>
                    <div data-i18n-workbench-target="tmFuzzy"></div>
                </section>
            </div>
        </div>
    </aside>
</div>
