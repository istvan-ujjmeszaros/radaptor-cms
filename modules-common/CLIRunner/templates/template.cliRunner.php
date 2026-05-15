<?php assert(isset($this) && $this instanceof Template); ?>
<?php
/** @var array<string, list<array{slug: string, name: string, docs: string, params: list<array<string, mixed>>, risk_level: string, timeout: int}>> $commands */
$commands = $this->props['commands'] ?? [];
$execute_url = (string) ($this->props['execute_url'] ?? '');

$category_labels = [
	'build' => t('cli_runner.category.build'),
	'db' => t('cli_runner.category.db'),
	'emailqueue' => t('cli_runner.category.emailqueue'),
	'form' => t('cli_runner.category.form'),
	'i18n' => t('cli_runner.category.i18n'),
	'migrate' => t('cli_runner.category.migrate'),
	'role' => t('cli_runner.category.role'),
	'site' => t('cli_runner.category.site'),
	'user' => t('cli_runner.category.user'),
	'userconfig' => t('cli_runner.category.userconfig'),
	'usergroup' => t('cli_runner.category.usergroup'),
	'webpage' => t('cli_runner.category.webpage'),
	'widget' => t('cli_runner.category.widget'),
];

$risk_classes = [
	'safe' => 'bg-success',
	'build' => 'bg-warning text-dark',
	'mutation' => 'bg-danger',
];

registerI18n([
	'common.cancel',
	'common.close',
	'common.error',
	'common.loading',
	'cli_runner.action.run',
	'cli_runner.action.confirm',
	'cli_runner.confirm.title',
	'cli_runner.confirm.message',
	'cli_runner.output.empty',
	'cli_runner.output.running',
	'cli_runner.output.completed',
	'cli_runner.output.failed',
	'cli_runner.output.timeout',
	'cli_runner.select_command',
	'cli_runner.action.copy',
	'cli_runner.action.copied',
]);
?>

<style>
	.cli-runner-container {
		display: flex;
		gap: 1.5rem;
		align-items: flex-start;
	}

	.cli-runner-sidebar {
		min-width: 240px;
		max-width: 280px;
		flex-shrink: 0;
		height: 100%;
		overflow-y: auto;
		padding-right: 0.5rem;
	}

	.cli-runner-workspace {
		flex: 1;
		min-width: 0;
		height: 100%;
		overflow-y: auto;
	}

	.cli-cmd-btn {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		width: 100%;
		padding: 0.35rem 0.6rem;
		border: none;
		background: transparent;
		color: var(--bs-body-color);
		text-align: left;
		font-size: 0.85rem;
		border-radius: 4px;
		cursor: pointer;
		font-family: 'JetBrains Mono', 'Fira Code', monospace;
	}

	.cli-cmd-btn:hover {
		background: var(--bs-tertiary-bg);
	}

	.cli-cmd-btn.active {
		background: var(--bs-primary);
		color: #fff;
	}

	.cli-cmd-btn .badge {
		font-size: 0.6rem;
		padding: 0.15em 0.4em;
		margin-left: auto;
		flex-shrink: 0;
	}

	/* Custom combobox */
	.cli-combobox {
		position: relative;
	}
	.cli-combobox-input {
		background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 16 16'%3E%3Cpath fill='%236c757d' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
		background-repeat: no-repeat;
		background-position: right 0.5rem center;
		padding-right: 1.75rem !important;
	}
	.cli-combobox-dropdown {
		display: none;
		position: absolute;
		left: 0;
		right: 0;
		z-index: 1050;
		max-height: 200px;
		overflow-y: auto;
		background: var(--bs-body-bg, #1a1a2e);
		border: 1px solid var(--bs-border-color, #444);
		border-top: none;
		border-radius: 0 0 6px 6px;
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
	}
	.cli-combobox-dropdown.open {
		display: block;
	}
	.cli-combobox-option {
		padding: 0.35rem 0.75rem;
		font-size: 0.85rem;
		cursor: pointer;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	.cli-combobox-option:hover {
		background: var(--bs-primary, #0d6efd);
		color: #fff;
	}

	.cli-terminal-output {
		background: #1a1a2e;
		color: #d4d4d4;
		font-family: 'JetBrains Mono', 'Fira Code', 'Cascadia Code', monospace;
		font-size: 13px;
		line-height: 1.5;
		padding: 1rem;
		border-radius: 8px;
		max-height: 500px;
		min-height: 200px;
		overflow: auto;
		white-space: pre-wrap;
		word-break: break-all;
	}

	.cli-terminal-entry {
		margin-bottom: 1rem;
		padding-bottom: 1rem;
		border-bottom: 1px dashed rgba(255, 255, 255, 0.25);
	}

	.cli-terminal-entry:last-child {
		margin-bottom: 0;
		padding-bottom: 0;
		border-bottom: none;
	}

	.cli-terminal-prompt {
		color: #4ec94e;
		font-weight: bold;
	}

	.cli-terminal-meta {
		color: #888;
		font-size: 11px;
	}

	.cli-docs-panel {
		background: var(--bs-tertiary-bg);
		border-radius: 8px;
		padding: 0.75rem 1rem;
		font-size: 0.85rem;
		white-space: pre-wrap;
		font-family: 'JetBrains Mono', 'Fira Code', monospace;
		max-height: 200px;
		overflow-y: auto;
	}

	/* ANSI color classes */
	.ansi-bold { font-weight: bold; }
	.ansi-black { color: #555; }
	.ansi-red { color: #f44747; }
	.ansi-green { color: #4ec94e; }
	.ansi-yellow { color: #ffd700; }
	.ansi-blue { color: #569cd6; }
	.ansi-magenta { color: #c678dd; }
	.ansi-cyan { color: #4fc1ff; }
	.ansi-white { color: #d4d4d4; }
	.ansi-bg-black { background-color: #555; }
	.ansi-bg-red { background-color: #f44747; color: #fff; }
	.ansi-bg-green { background-color: #4ec94e; color: #000; }
	.ansi-bg-yellow { background-color: #ffd700; color: #000; }
	.ansi-bg-blue { background-color: #569cd6; }
	.ansi-bg-magenta { background-color: #c678dd; }
	.ansi-bg-cyan { background-color: #4fc1ff; color: #000; }
	.ansi-bg-white { background-color: #d4d4d4; color: #000; }

	.cli-confirm-dialog {
		width: min(500px, 92vw);
		border: none;
		border-radius: 16px;
		padding: 0;
		background: transparent;
		box-shadow: none;
		overflow: visible;
	}

	.cli-confirm-dialog::backdrop {
		background: rgba(15, 23, 42, 0.16);
		backdrop-filter: blur(8px);
		-webkit-backdrop-filter: blur(8px);
	}

	.cli-confirm-dialog .card {
		border-radius: 16px;
		overflow: hidden;
		box-shadow: 0 1.25rem 3rem rgba(0, 0, 0, 0.28);
	}
</style>

<div class="subheader">
	<h1><?= e(t('cli_runner.title')) ?></h1>
	<p><?= e(t('cli_runner.subtitle')) ?></p>
</div>

<div class="cli-runner-container"
	 data-controller="cli-runner"
	 data-cli-runner-execute-url-value="<?= e($execute_url) ?>"
	 data-cli-runner-commands-value="<?= e(json_encode($commands, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">

	<!-- Left: Command list -->
	<nav class="cli-runner-sidebar">
		<?php foreach ($commands as $category => $cmds): ?>
			<div class="mb-3">
				<small class="text-muted text-uppercase px-1 d-block mb-1">
					<?= e($category_labels[$category] ?? ucfirst($category)) ?>
				</small>
				<?php foreach ($cmds as $cmd): ?>
					<button class="cli-cmd-btn"
							type="button"
							data-action="cli-runner#selectCommand"
							data-cli-runner-slug-param="<?= e($cmd['slug']) ?>"
							data-bs-toggle="tooltip"
							data-bs-placement="right"
							data-bs-title="<?= e($cmd['name']) ?>"
							title="<?= e($cmd['name']) ?>">
						<span><?= e($cmd['slug']) ?></span>
						<span class="badge <?= $risk_classes[$cmd['risk_level']] ?? 'bg-secondary' ?>">
							<?= e($cmd['risk_level']) ?>
						</span>
					</button>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	</nav>

	<!-- Right: Workspace -->
	<div class="cli-runner-workspace">
		<!-- Command info + form -->
		<div data-cli-runner-target="commandPanel" hidden>
			<div class="card mb-3">
				<div class="card-body">
					<h3 class="card-title mb-1" data-cli-runner-target="commandName"></h3>
					<div class="cli-docs-panel mb-3" data-cli-runner-target="commandDocs"></div>

					<form data-cli-runner-target="paramForm" data-action="submit->cli-runner#runCommand">
						<!-- Dynamic param inputs rendered by JS -->
						<div data-cli-runner-target="paramFields"></div>

						<button type="submit"
								class="btn btn-primary btn-sm"
								data-cli-runner-target="runButton">
							<i class="bi bi-play-fill me-1"></i>
							<?= e(t('cli_runner.action.run')) ?>
						</button>
					</form>
				</div>
			</div>
		</div>

		<!-- Placeholder when no command selected -->
		<div data-cli-runner-target="placeholder" class="text-muted text-center py-5">
			<i class="bi bi-terminal" style="font-size: 3rem; opacity: 0.3;"></i>
			<p class="mt-2"><?= e(t('cli_runner.select_command')) ?></p>
		</div>

		<!-- Terminal output -->
		<div data-cli-runner-target="terminalCard" hidden>
			<div class="d-flex justify-content-between align-items-center mb-2">
				<strong><?= e(t('cli_runner.output.title')) ?></strong>
				<div class="d-flex gap-1">
					<button type="button"
							class="btn btn-outline-secondary btn-sm"
							data-action="cli-runner#copyOutput">
						<i class="bi bi-clipboard me-1"></i><?= e(t('cli_runner.action.copy')) ?>
					</button>
					<button type="button"
							class="btn btn-outline-secondary btn-sm"
							data-action="cli-runner#clearOutput">
						<i class="bi bi-trash me-1"></i><?= e(t('cli_runner.action.clear')) ?>
					</button>
				</div>
			</div>
			<div class="cli-terminal-output" data-cli-runner-target="terminal"></div>
		</div>
	</div>

	<!-- Confirm dialog for mutation commands -->
	<dialog data-cli-runner-target="confirmDialog" class="cli-confirm-dialog">
		<div class="card mb-0">
			<div class="card-header">
				<strong><?= e(t('cli_runner.confirm.title')) ?></strong>
			</div>
			<div class="card-body">
				<p data-cli-runner-target="confirmMessage"><?= e(t('cli_runner.confirm.message')) ?></p>
				<code data-cli-runner-target="confirmCommand" class="d-block mb-2"></code>
			</div>
			<div class="card-footer d-flex justify-content-end gap-2">
				<button type="button"
						class="btn btn-outline-secondary btn-sm"
						data-action="cli-runner#closeConfirm">
					<?= e(t('common.cancel')) ?>
				</button>
				<button type="button"
						class="btn btn-danger btn-sm"
						data-action="cli-runner#confirmRun">
					<?= e(t('cli_runner.action.confirm')) ?>
				</button>
			</div>
		</div>
	</dialog>
</div>
