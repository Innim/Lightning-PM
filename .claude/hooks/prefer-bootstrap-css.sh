#!/usr/bin/env bash
# PreToolUse reminder: prefer Bootstrap 5.1 utilities/components over custom CSS.
#
# Fires (non-blocking) when a tool is about to add or modify custom CSS under
# lpm-themes/*/css/. Injects a reminder into the model context so the project
# rule (AGENTS.md -> Frontend Conventions) isn't silently skipped mid-task.
# Vendored/third-party CSS is ignored. Never blocks the tool call.
set -euo pipefail

input=$(cat)
file=$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty')
cmd=$(printf '%s' "$input" | jq -r '.tool_input.command // empty')

if [ -n "$file" ]; then
  # Edit/Write: inspect the target file path.
  target="$file"
else
  # Bash: only care about commands that WRITE (redirect, tee, sed -i, cp/mv).
  printf '%s' "$cmd" | grep -qE '(>>?|tee|sed -i|cp |mv )' || exit 0
  target="$cmd"
fi

# Only custom CSS under the theme css dirs.
printf '%s' "$target" | grep -qE 'lpm-themes/[^/]+/css/[^ ]*\.css' || exit 0
# Skip vendored / third-party CSS.
printf '%s' "$target" | grep -qiE 'bootstrap\.min\.css|font-awesome|highlightjs|tribute\.css|vanilla-calendar\.css|vue-multiselect' && exit 0

cat <<'JSON'
{"hookSpecificOutput":{"hookEventName":"PreToolUse","additionalContext":"Проектное правило (AGENTS.md -> Frontend Conventions): предпочитай Bootstrap 5.1 утилиты и компоненты кастомному CSS. Ты редактируешь custom CSS — сначала убедись, что нет подходящей Bootstrap-утилиты (opacity-*, text-muted, gap-*, d-flex, position-* и т.п.). Добавляй правило в CSS только если утилиты действительно нет, и тогда кратко обоснуй в ответе, почему Bootstrap не подошёл."}}
JSON
