#!/usr/bin/env bash
# SessionStart: stdout is added to Claude's context.
cd "$CLAUDE_PROJECT_DIR" || exit 0

running="$(docker compose ps --status running --services 2>/dev/null | tr '\n' ' ')"

echo "Shopper dev environment:"
if [[ -n "$running" ]]; then
    echo "- Docker services running: ${running}(admin: http://localhost:8000/cpanel, admin@laravelshopper.dev / password)"
else
    echo "- Docker is not running. Start it with: docker compose up -d"
fi
echo "- Run PHP tooling in the container: docker compose exec -T app vendor/bin/{pest,phpstan,testbench}"
echo "- Feature plans live in .claude/plans (start with .claude/plans/README.md)."
