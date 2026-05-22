#!/usr/bin/env bash
# PostToolUse hook: when an Edit/Write touches a PHP or Vue/TS file, run the
# matching formatter on just that file. Quietly no-ops if the tools aren't
# installed yet (e.g. before `composer install` has run on the host).

set -euo pipefail

# Claude Code passes the edited path in $CLAUDE_TOOL_FILE_PATH. Fall back to
# checking common locations so the hook also works in non-Claude shells.
file="${CLAUDE_TOOL_FILE_PATH:-}"
[ -z "$file" ] && exit 0
[ -f "$file" ] || exit 0

case "$file" in
  *.php)
    if [ -x "backend/vendor/bin/pint" ]; then
      (cd backend && ./vendor/bin/pint --quiet "$file" >/dev/null 2>&1) || true
    fi
    ;;
  *.vue|*.ts|*.tsx|*.js|*.jsx)
    # Stay no-op until the repo has prettier configured; placeholder for now.
    :
    ;;
esac

exit 0
