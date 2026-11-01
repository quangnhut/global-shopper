#!/usr/bin/env bash
# PreToolUse (Edit|Write): block edits to generated, vendored or secret files.
# Exit 2 = block the tool call and show stderr to Claude.
set -euo pipefail

file_path="$(python3 "$CLAUDE_PROJECT_DIR/.claude/hooks/_input.py" tool_input.file_path)"
relative="${file_path#"$CLAUDE_PROJECT_DIR"/}"

case "$relative" in
    vendor/*|node_modules/*|*/vendor/*)
        echo "Blocked: '$relative' is installed by Composer/npm. Change the package source or composer.json instead." >&2
        exit 2 ;;
    packages/admin/dist/*|packages/*/dist/*)
        echo "Blocked: '$relative' is a build artifact. Edit the sources in resources/ and run 'npm run build'." >&2
        exit 2 ;;
    composer.lock|package-lock.json)
        echo "Blocked: lock files are only changed by 'composer'/'npm' commands." >&2
        exit 2 ;;
    uploads/*)
        echo "Blocked: 'uploads/' holds files admins uploaded. Never edit them by hand." >&2
        exit 2 ;;
esac

exit 0
