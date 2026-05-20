# Radaptor CMS Package - Agent Rules

## Package Scope

- This is the standalone `radaptor/core/cms` package repository.
- CMS changes belong here, not in consumer app `packages/registry/...` copies.
- CMS-owned behavior, CMS services, CMS resource specs, CMS site snapshots, and CMS-specific CLI commands belong in this package. Put only generic infrastructure in `radaptor/core/framework`.

## Supported Runtime

- Run checks from a Radaptor consumer app container, not with host PHP or host Composer.
- In the `_RADAPTOR` workspace, use:
  `./bin/docker-compose-packages-dev.sh radaptor-app-skeleton exec -T php bash -lc 'cd /workspace/packages-dev/core/cms && /app/php-cs-fixer.sh --config=.php-cs-fixer.php'`
- For PHPStan, use the documented workspace command from the root `AGENTS.md`.

## GitHub PR Review Workflow

- Do not commit without explicit maintainer/user approval.
- After opening or updating a GitHub PR, request Codex review with a PR comment containing exactly `@codex review`. Do not use GitHub's normal reviewer API for `codex`; an `eyes` reaction means the bot accepted the request, not that review is complete.
- After requesting Codex review, wait for a completed Codex review result on the PR's current HEAD before merging, publishing, releasing, or treating the PR as approved dependency input. A completed result means a Codex review submission, inline findings, or an explicit Codex completion/no-findings comment. `eyes`, queued/running status, elapsed time, and "zero unresolved threads" by themselves are not a review result.
- If Codex does not produce a completed review result, stop and report that the review is still pending. Do not bypass this gate unless the maintainer explicitly says to proceed without Codex review for that specific PR/commit.
- When addressing review feedback, use a thread-aware read of GitHub review threads; flat comment lists are not enough because they lose resolved/outdated state.
- After implementing, validating, committing, and pushing a fix, always mark every review thread resolved that the pushed commit actually addresses.
- Never resolve a thread just to clear the list. If a thread remains unresolved intentionally, say why and include the next concrete fix.
- Before requesting a fresh `@codex review`, merging, or publishing, re-check unresolved review threads and report the count. Before merging or publishing, also verify that the latest `@codex review` request for the current HEAD has a completed Codex review result.
- Merge and publish only after the relevant PR has a completed Codex review result for the current HEAD, no unresolved review threads, required checks are green or explicitly accepted, and any dependent lockfile/runtime update plan is clear.
- After publishing this package, update every dependent consumer lockfile/runtime that should consume the new immutable version, then commit those dependency updates separately.
