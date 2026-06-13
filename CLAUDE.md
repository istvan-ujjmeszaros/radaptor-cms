# Radaptor CMS Package Notes

The canonical repo workflow rules live in [`AGENTS.md`](./AGENTS.md). Treat `AGENTS.md` as source
of truth.

## Package Scope

- This is the standalone `radaptor/core/cms` package repository.
- CMS changes belong here, not in consumer app `packages/registry/...` copies.
- CMS-owned behavior, services, resource specs, site snapshots, and CMS-specific CLI commands
  belong in this package. Put only generic infrastructure in `radaptor/core/framework`.

## Supported Runtime

- Run checks from a Radaptor consumer app container, not with host PHP or host Composer.
- Before any implementation, hooks, CLI, test, or browser work, start every Docker Compose stack
  relevant to the repos/worktrees you will touch. If the Docker daemon / Docker Desktop is not
  running, ask the user to start it and wait until it is up before moving forward.
- Worktrees are separate compose projects (labels include the worktree path); a running `php`
  container from another app checkout does not satisfy this worktree's hooks. Do not bypass Git
  hooks because a stack is down — start it first.
- PHP-CS-Fixer from the `_RADAPTOR` workspace:
  ```
  ../../../bin/docker-compose-packages-dev.sh radaptor-app-skeleton exec -T php bash -lc \
    'cd /workspace/packages-dev/core/cms && /app/php-cs-fixer.sh --config=.php-cs-fixer.php'
  ```
- PHPStan: use the documented workspace command from the root `AGENTS.md` (runs the
  `NonHtmlResponseHeaderDetectionRule` autoload alongside this repo's `phpstan.neon`).

## CMS Content Safety

- Migrations must never create, repair, overwrite, move, or delete app-authored CMS content
  (`resource_tree`, widget placements, ACLs, uploads, menus). Use seeds or `resource-spec:*` sync.
- Migrations must strictly never delete rows from `resource_tree`.

## Runtime Response Detection Rule

- When adding or touching PHP files that can inspect response-family headers, add them to
  `phpstan.neon`'s `paths` entry so the detection rule actually checks them.
- New code must use `Request::wantsNonHtmlResponse()`; do not hand-read `HTTP_ACCEPT`,
  `HTTP_X_REQUESTED_WITH`, or `HTTP_HX_REQUEST`, and do not add `ajax=1`-style query fallbacks.

## Commit & PR

- Do not commit without explicit maintainer approval.
- Run Claude's internal review agents (e.g. `/code-review`) on the branch before the primary gate.
- The primary review gate is a local Codex CLI review worker: run `codex --dangerously-bypass-approvals-and-sandbox exec review --base
  origin/main` on the PR branch and post findings or an explicit no-findings result on the PR for
  the current HEAD. The worker streams no output until it finishes and can take 20–40+ minutes, so run it with a long timeout and never kill it early; the `--dangerously-bypass-approvals-and-sandbox` flag is required or it blocks on an approval prompt and produces nothing. A GitHub `@codex review` comment is an optional extra signal when quota
  allows; `claudee` only on maintainer request.
- Thread-aware review reads; resolve threads that pushed commits address; never resolve to clear
  the list. Re-check unresolved count before requesting another review, merging, or publishing.
- Merge/publish only with a completed review result for the current HEAD, zero unresolved threads,
  green checks, and explicit maintainer approval.
- After publishing this package, update every dependent consumer lockfile/runtime in separate commits.
