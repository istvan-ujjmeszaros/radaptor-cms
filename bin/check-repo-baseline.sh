#!/usr/bin/env bash
set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$REPO_ROOT"

fail() {
	echo -e "${RED}$*${NC}" >&2
	exit 1
}

info() {
	echo -e "${YELLOW}$*${NC}"
}

success() {
	echo -e "${GREEN}$*${NC}"
}

require_file() {
	local path="$1"

	if [ ! -f "$path" ]; then
		fail "Missing required baseline file: $path"
	fi
}

require_executable() {
	local path="$1"

	require_file "$path"

	if [ ! -x "$path" ]; then
		fail "Required baseline file is not executable: $path"
	fi
}

require_tracked_executable() {
	local path="$1"
	local mode

	require_executable "$path"

	mode="$(git ls-files -s -- "$path" | awk 'NR == 1 {print $1}')"
	if [ "$mode" != "100755" ]; then
		fail "Required baseline file is not tracked executable: $path"
	fi
}

find_consumer_app_root() {
	local dir="$REPO_ROOT"

	while [ "$dir" != "/" ]; do
		if [ -f "$dir/docker-compose-dev.yml" ] && [ -x "$dir/php-cs-fixer.sh" ]; then
			echo "$dir"
			return 0
		fi

		dir="$(dirname "$dir")"
	done

	return 1
}

# Baseline-managed package-dev runtime discovery. The workspace baseline
# template is the source of truth; keep this helper and the php-generic
# pre-commit hook copy in sync. The container path is coupled to
# docker-compose.packages-dev.yml.
find_packages_dev_runtime() {
	local dir="$REPO_ROOT"

	while [ "$dir" != "/" ]; do
		if [ "$(basename "$dir")" = "packages-dev" ] && [ -d "$dir" ]; then
			local workspace_root
			workspace_root="$(dirname "$dir")"
			local repo_relative_to_packages_dev="${REPO_ROOT#"$dir/"}"
			local container_repo_root="/workspace/packages-dev/$repo_relative_to_packages_dev"
			local docker_service="${RADAPTOR_DOCKER_PHP_SERVICE:-php}"
			local preferred_container=""
			local fallback_container=""
			local container_name=""
			local project_working_dir=""
			local config_files=""

			if ! command -v docker >/dev/null 2>&1; then
				return 1
			fi

			while IFS=$'\t' read -r container_name project_working_dir config_files; do
				if [ -z "$container_name" ]; then
					continue
				fi

				if [[ "$config_files" != *"$workspace_root/docker-compose.packages-dev.yml"* ]]; then
					continue
				fi

				if [ -z "$fallback_container" ]; then
					fallback_container="$container_name"
				fi

				if [ "$project_working_dir" = "$workspace_root/radaptor-app-skeleton" ]; then
					preferred_container="$container_name"
					break
				fi
			done < <(
				docker ps \
					--filter "label=com.docker.compose.service=$docker_service" \
					--format '{{.Names}}\t{{.Label "com.docker.compose.project.working_dir"}}\t{{.Label "com.docker.compose.project.config_files"}}'
			)

			if [ -z "$preferred_container" ] && [ -z "$fallback_container" ]; then
				return 1
			fi

			printf '%s|%s\n' "${preferred_container:-$fallback_container}" "$container_repo_root"
			return 0
		fi

		dir="$(dirname "$dir")"
	done

	return 1
}

run_php_cs_fixer_check_locally() {
	if [ -x "$REPO_ROOT/php-cs-fixer.sh" ]; then
		"$REPO_ROOT/php-cs-fixer.sh" --config=.php-cs-fixer.php --dry-run --diff
		return $?
	fi

	if [ -x "$REPO_ROOT/tools/php-cs-fixer" ]; then
		"$REPO_ROOT/tools/php-cs-fixer" fix --dry-run --diff --config=.php-cs-fixer.php
		return $?
	fi

	if [ -x "$REPO_ROOT/vendor/bin/php-cs-fixer" ]; then
		"$REPO_ROOT/vendor/bin/php-cs-fixer" fix --dry-run --diff --config=.php-cs-fixer.php
		return $?
	fi

	if command -v php-cs-fixer >/dev/null 2>&1; then
		php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.php
		return $?
	fi

	return 1
}

run_php_cs_fixer_check_in_consumer_app() {
	local app_root="$1"
	local docker_service="${RADAPTOR_DOCKER_PHP_SERVICE:-php}"
	local repo_relative_path="${REPO_ROOT#"$app_root"}"
	local container_repo_root="/app${repo_relative_path}"

	if ! command -v docker >/dev/null 2>&1; then
		return 1
	fi

	if ! docker compose -f "$app_root/docker-compose-dev.yml" ps "$docker_service" 2>/dev/null | grep -q "Up"; then
		return 1
	fi

	docker compose -f "$app_root/docker-compose-dev.yml" exec -T "$docker_service" bash -lc \
		"cd '$container_repo_root' && php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.php"
}

run_php_cs_fixer_check_in_packages_dev_runtime() {
	local runtime="$1"
	local container="${runtime%%|*}"
	local container_repo_root="${runtime#*|}"

	docker exec "$container" bash -lc \
		"cd '$container_repo_root' && php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.php"
}

run_php_cs_fixer_check() {
	local app_root
	local runtime_spec

	info "Running baseline formatting check..."

	if run_php_cs_fixer_check_locally; then
		return 0
	fi

	if app_root="$(find_consumer_app_root)"; then
		info "Using consumer app container at $app_root for formatting check."

		if run_php_cs_fixer_check_in_consumer_app "$app_root"; then
			return 0
		fi
	fi

	if runtime_spec="$(find_packages_dev_runtime)"; then
		info "Using packages-dev workspace container ${runtime_spec%%|*} for formatting check."

		if run_php_cs_fixer_check_in_packages_dev_runtime "$runtime_spec"; then
			return 0
		fi
	fi

	fail "Unable to run php-cs-fixer locally or through a running consumer app or packages-dev container."
}

PROFILE_FILE=".repo-baseline-profile"

require_file "$PROFILE_FILE"
if [ ! -d ".git" ] && [ ! -f ".git" ]; then
	fail "Repo baseline check requires a Git worktree at $REPO_ROOT"
fi

require_tracked_executable ".githooks/install.sh"
require_tracked_executable ".githooks/pre-commit"
require_tracked_executable "bin/check-repo-baseline.sh"
require_file ".github/workflows/repo-checks.yml"

if [ -f ".github/workflows/php-cs-fixer.yml" ]; then
	fail "Legacy workflow still present: .github/workflows/php-cs-fixer.yml"
fi

PROFILE="$(tr -d '[:space:]' < "$PROFILE_FILE")"

case "$PROFILE" in
	generic|php-generic|php-consumer-app)
		;;
	*)
		fail "Unsupported baseline profile in $PROFILE_FILE: $PROFILE"
		;;
esac

if [ "$(git config --get core.hooksPath || true)" != ".githooks" ]; then
	fail "core.hooksPath is not configured to .githooks. Run ./.githooks/install.sh before checking the baseline."
fi

if [ "$PROFILE" = "generic" ]; then
	success "Repo baseline check passed for profile: $PROFILE"
	exit 0
fi

require_file ".php-cs-fixer.php"
run_php_cs_fixer_check

if [ "$PROFILE" = "php-consumer-app" ]; then
	require_tracked_executable "bin/check-local-override-state.sh"
	./bin/check-local-override-state.sh
fi

success "Repo baseline check passed for profile: $PROFILE"
