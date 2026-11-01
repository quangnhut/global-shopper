#!/usr/bin/env bash
# PostToolUse (Edit|Write): format edited PHP with Pint and enforce strict types.
# Exit 2 = show stderr to Claude so it fixes the file.
set -uo pipefail

file_path="$(python3 "$CLAUDE_PROJECT_DIR/.claude/hooks/_input.py" tool_input.file_path)"

[[ "$file_path" == *.php ]] || exit 0
[[ -f "$file_path" ]] || exit 0
[[ "$file_path" == *.blade.php ]] && exit 0

relative="${file_path#"$CLAUDE_PROJECT_DIR"/}"
case "$relative" in
    packages/*|tests/*|workbench/*) ;;
    *) exit 0 ;;
esac

if ! grep -q "declare(strict_types=1);" "$file_path"; then
    echo "$relative is missing 'declare(strict_types=1);' (required by CLAUDE.md). Add it after '<?php'." >&2
    exit 2
fi

# Pint runs fine on the host PHP; it only rewrites this one file.
"$CLAUDE_PROJECT_DIR/vendor/bin/pint" --quiet "$file_path" > /dev/null 2>&1 || true

exit 0
