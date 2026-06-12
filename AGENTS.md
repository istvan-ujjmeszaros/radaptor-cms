# Radaptor CMS Package - Agent Rules

## Package Scope

- This is the standalone `radaptor/core/cms` package repository.
- CMS changes belong here, not in consumer app `packages/registry/...` copies.
- CMS-owned behavior, CMS services, CMS resource specs, CMS site snapshots, and CMS-specific CLI commands belong in this package. Put only generic infrastructure in `radaptor/core/framework`.

## Supported Runtime

- Run checks from a Radaptor consumer app container, not with host PHP or host Composer.
- Before implementation, commits, hooks, CLI work, browser smoke, Playwright, or package-dev verification, start every Docker Compose stack that is relevant to the repos/worktrees you will touch.
- If a clean proof/tmp consumer app worktree, PR-sync clone, portal app, or other app repo is part of the task, bring up that worktree's own compose project before relying on its hooks or tests. Docker Compose labels include the worktree path, so a running `php` container from another app checkout does not satisfy that worktree's hooks.
- Do not bypass Git hooks only because the expected Docker Compose project is not running. Start the relevant stack first; if a hook still cannot run, state the reason and the equivalent checks that were run before committing.
- If the Docker daemon / Docker Desktop is not running, do not work around it: ask the user to start Docker Desktop, wait until the daemon is up, and only then continue.
- In the `_RADAPTOR` workspace, use:
  `./bin/docker-compose-packages-dev.sh radaptor-app-skeleton exec -T php bash -lc 'cd /workspace/packages-dev/core/cms && /app/php-cs-fixer.sh --config=.php-cs-fixer.php'`
- For PHPStan, use the documented workspace command from the root `AGENTS.md`.

## GitHub PR Review Workflow

- Do not commit without explicit maintainer/user approval.
- After opening or updating a GitHub PR, request the primary review gate by posting `@codex review` on the PR before merging, publishing, releasing, or treating the PR as approved dependency input. Treat the gate as complete only after GitHub-hosted Codex posts findings or an explicit no-findings result for the current HEAD.
- Claude-driven sessions should run Claude's internal review agents (for example `/code-review`) on the branch before requesting `@codex review`, so obvious findings are fixed before the primary gate runs.
- Review results must be visible on the PR, using inline comments for line-tied findings when possible and a top-level PR comment otherwise. A no-findings result must also be posted for the reviewed HEAD.
- Use a local Codex CLI review worker only as a fallback when the GitHub `@codex review` path is quota-limited, rate-limited, unavailable, or fails to produce a usable result. Document the fallback reason, keep the worker review-only (no edits, commits, pushes, or merges), and re-try GitHub review on later passes because its reset window is opaque.
- If the maintainer asks for Claude review, use `claudee` from the CLI for one PR at a time. If `claudee` is unavailable or fails, report that and fall back through GitHub `@codex review` first; use a local Codex review worker only if the GitHub path is unavailable.
- When addressing review feedback, use a thread-aware read of GitHub review threads; flat comment lists are not enough because they lose resolved/outdated state.
- After implementing, validating, committing, and pushing a fix, always mark every review thread resolved that the pushed commit actually addresses.
- If the fresh review pass posts any actionable finding, fix it, validate, push, re-read thread-aware state, resolve only addressed threads, and request another fresh `@codex review` pass. Repeat until the current HEAD has an explicit no-findings result.
- Never resolve a thread just to clear the list. If a thread remains unresolved intentionally, say why and include the next concrete fix.
- Before requesting a fresh review pass, merging, or publishing, re-check unresolved review threads and report the count. Before merging or publishing, also verify that the latest review result was posted for the current HEAD.
- Merge and publish only after the relevant PR has a completed Codex review result for the current HEAD, no unresolved review threads, required checks are green or explicitly accepted, any dependent lockfile/runtime update plan is clear, and the maintainer explicitly approves the merge/publish step.
- After publishing this package, update every dependent consumer lockfile/runtime that should consume the new immutable version, then commit those dependency updates separately.
