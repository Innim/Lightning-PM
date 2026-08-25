#!/usr/bin/env bash
# PreToolUse reminder: keep the three external-API docs in sync.
#
# Fires (non-blocking) when a tool is about to add or modify a file under
# lpm-core/modules/api/. Injects a reminder into the model context so the
# project rule (AGENTS.md -> Architecture Notes, External HTTP API) isn't
# silently skipped mid-task. Never blocks the tool call.
set -euo pipefail

command -v jq >/dev/null 2>&1 || exit 0

input=$(cat)
# Malformed input must not fail the hook: a non-zero exit here would surface
# as a hook error on an ordinary Edit/Write.
file=$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty' 2>/dev/null || true)

[ -n "$file" ] || exit 0

# Matches both absolute and repo-relative paths.
printf '%s' "$file" | grep -qE '(^|/)lpm-core/modules/api/' || exit 0

cat <<'JSON'
{"hookSpecificOutput":{"hookEventName":"PreToolUse","additionalContext":"Проектное правило (AGENTS.md -> Architecture Notes, External HTTP API): ты правишь lpm-core/modules/api/. Если добавляешь, меняешь или удаляешь эндпоинт — тем же изменением обнови все три дока: docs/api/README.md (полный справочник), llms.txt (краткий список эндпоинтов для агентов) и ai/skills/lightning-pm-issue/references/api.md (справочник скила). Если контракт эндпоинтов не менялся — скажи это явно в отчёте."}}
JSON
