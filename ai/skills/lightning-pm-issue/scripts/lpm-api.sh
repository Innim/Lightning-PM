#!/usr/bin/env bash
set -euo pipefail

usage() {
    cat <<'EOF'
Usage:
  lpm-api.sh ROOT_URL METHOD URL_OR_PATH [JSON_BODY] [-- CURL_ARGS...]

Environment:
  LIGHTNING_PM_API_KEY   Required API key for Lightning PM.

Examples:
  lpm-api.sh 'https://pm.example.com/project/demo/issue/891' GET '/api/v1/issues/resolve?url=https://pm.example.com/project/demo/issue/891'
  lpm-api.sh 'https://pm.example.com' POST /api/v1/issues/891/branches '{"name":"891.inner-store-payment-method","repositoryId":12,"parentBranch":"develop"}'
  lpm-api.sh 'https://pm.example.com/project/demo/issue/891' GET 'https://pm.example.com/lpm-files/protected/file.png' -- --output /tmp/file.png
EOF
}

if [[ $# -lt 3 ]]; then
    usage >&2
    exit 1
fi

if [[ -z "${LIGHTNING_PM_API_KEY:-}" ]]; then
    echo "LIGHTNING_PM_API_KEY is not set." >&2
    exit 1
fi

root_url="$1"
shift
method="$1"
shift
target="$1"
shift

if [[ ! "$root_url" =~ ^https?:// ]]; then
    echo "ROOT_URL must start with http:// or https://." >&2
    exit 1
fi

body=""
if [[ $# -gt 0 && "${1:-}" != "--" ]]; then
    body="$1"
    shift
fi

extra_args=()
if [[ $# -gt 0 ]]; then
    if [[ "$1" != "--" ]]; then
        echo "Unexpected argument: $1" >&2
        usage >&2
        exit 1
    fi
    shift
    extra_args=("$@")
fi

base_url="$(printf '%s' "$root_url" | sed -E 's#^(https?://[^/]+).*$#\1#')"

if [[ "$target" =~ ^https?:// ]]; then
    url="$target"
else
    if [[ "$target" == /* ]]; then
        url="${base_url}${target}"
    else
        url="${base_url}/${target}"
    fi
fi

curl_args=(
    --fail-with-body
    --silent
    --show-error
    -X "$method"
    -H "X-LPM-API-Key: ${LIGHTNING_PM_API_KEY}"
    "$url"
)

if [[ -n "$body" ]]; then
    curl_args+=(
        -H "Content-Type: application/json"
        --data "$body"
    )
fi

if [[ ${#extra_args[@]} -gt 0 ]]; then
    curl "${curl_args[@]}" "${extra_args[@]}"
else
    curl "${curl_args[@]}"
fi
