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
- In the `_RADAPTOR` workspace, use:
  `./bin/docker-compose-packages-dev.sh radaptor-app-skeleton exec -T php bash -lc 'cd /workspace/packages-dev/core/cms && /app/php-cs-fixer.sh --config=.php-cs-fixer.php'`
- For PHPStan, use the documented workspace command from the root `AGENTS.md`.

## GitHub PR Review Workflow

- Do not commit without explicit maintainer/user approval.
- After opening or updating a GitHub PR, start a local Codex CLI review agent for the exact PR URL and current HEAD before merging, publishing, releasing, or treating the PR as approved dependency input. The review task is review-only: no edits, commits, pushes, or merges.
- The review agent must post its result on the PR, using inline comments for line-tied findings when possible and a top-level PR comment otherwise. A no-findings result must also be posted for the reviewed HEAD.
- If the maintainer asks for Claude review, use `claudee` from the CLI for one PR at a time. If `claudee` is unavailable or fails, report that and fall back to a local Codex review worker.
- A bare `@codex review` PR comment is not the primary gate. Use it only when explicitly requested as a fallback/extra signal; it does not replace the local Codex CLI review agent result.
- When addressing review feedback, use a thread-aware read of GitHub review threads; flat comment lists are not enough because they lose resolved/outdated state.
- After implementing, validating, committing, and pushing a fix, always mark every review thread resolved that the pushed commit actually addresses.
- Never resolve a thread just to clear the list. If a thread remains unresolved intentionally, say why and include the next concrete fix.
- Before requesting a fresh local Codex CLI review agent, merging, or publishing, re-check unresolved review threads and report the count. Before merging or publishing, also verify that the latest local Codex CLI review agent result was posted for the current HEAD.
- Merge and publish only after the relevant PR has a completed local Codex CLI review agent result for the current HEAD, no unresolved review threads, required checks are green or explicitly accepted, any dependent lockfile/runtime update plan is clear, and the maintainer explicitly approves the merge/publish step.
- After publishing this package, update every dependent consumer lockfile/runtime that should consume the new immutable version, then commit those dependency updates separately.
