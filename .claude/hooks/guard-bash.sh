#!/usr/bin/env bash
# PreToolUse (Bash): the host PHP (8.3) cannot load vendor/ (it needs >= 8.4.1),
# so PHP tooling except Pint must run inside the Docker "app" container.
set -euo pipefail

command="$(python3 "$CLAUDE_PROJECT_DIR/.claude/hooks/_input.py" tool_input.command)"

# Only match where a shell command starts (line start, or after ; & | ( ), so text that merely
# mentions these tools (heredocs, strings, docs being edited) is not blocked.
command_start="(^|[;&|(]|"$'\n'")[[:space:]]*"

if [[ "$command" != *"docker compose"* ]] \
    && [[ "$command" =~ ${command_start}(\./)?(vendor/bin/(pest|phpunit|paratest|phpstan|rector|testbench)|composer[[:space:]]+test|php[[:space:]]+artisan) ]]; then
    cat >&2 <<'MSG'
Blocked: run PHP tooling inside the container, the host PHP is too old for vendor/:
  docker compose exec -T app vendor/bin/pest ...
  docker compose exec -T app vendor/bin/phpstan analyse ...
  docker compose exec -T app vendor/bin/testbench ...   (this replaces `php artisan`)
MSG
    exit 2
fi

exit 0
