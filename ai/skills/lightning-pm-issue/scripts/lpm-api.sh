#!/usr/bin/env bash
set -euo pipefail

# This script is meant to be allow-listed by command prefix, so it may be invoked
# with attacker-chosen arguments (issue text, comments and web pages are untrusted
# input for the agent that calls it). Everything below therefore treats every
# argument as hostile and fails closed.
#
# Security invariants — do not weaken without re-reading all of them:
#   * the request never leaves the origin of ROOT_URL (scheme + host + port),
#     compared component-wise, not by string prefix;
#   * X-LPM-API-Key is sent only to that origin, and never appears in argv;
#   * redirects are NOT followed (no -L / --location): a 3xx Location pointing at
#     another host would otherwise replay the API key there. If -L is ever needed,
#     the key must be dropped on cross-origin hops (curl does that only with
#     --location + explicit --proxy-header/--no-... handling), so keep it off;
#   * curl options are never taken from the caller. In particular -K/--config
#     would let any file supply arbitrary options and defeats argument-level
#     filtering entirely.

usage() {
    cat <<'EOF'
Usage:
  lpm-api.sh ROOT_URL METHOD URL_OR_PATH [JSON_BODY] [--save-to PATH]

Arguments:
  ROOT_URL       Lightning PM base or issue URL. Defines the only origin this
                 script will talk to. https:// is required (http:// is allowed
                 for localhost / 127.0.0.1 / ::1 only).
  METHOD         One of GET, POST, PUT, PATCH, DELETE.
  URL_OR_PATH    Path ('/api/v1/...') or absolute URL. An absolute URL is
                 accepted only when its scheme, host and port match ROOT_URL.
  JSON_BODY      Optional request body, sent as application/json.
  --save-to PATH Write the response body to PATH instead of stdout (for
                 attachment downloads). PATH must be inside the current
                 directory or a temp directory, must not be hidden or point
                 through a symlink, and its directory must already exist.

Environment:
  LIGHTNING_PM_API_KEY   Required API key for Lightning PM.

Examples:
  lpm-api.sh 'https://pm.example.com/project/demo/issue/891' GET '/api/v1/issues/resolve?url=https://pm.example.com/project/demo/issue/891'
  lpm-api.sh 'https://pm.example.com' POST /api/v1/issues/891/branches '{"name":"891.inner-store-payment-method","repositoryId":12,"parentBranch":"develop"}'
  lpm-api.sh 'https://pm.example.com/project/demo/issue/891' GET 'https://pm.example.com/lpm-files/protected/file.png' --save-to /tmp/file.png
EOF
}

die() {
    echo "$1" >&2
    exit 1
}

die_usage() {
    echo "$1" >&2
    usage >&2
    exit 1
}

lower() {
    printf '%s' "$1" | tr '[:upper:]' '[:lower:]'
}

# Splits an absolute http(s) URL into normalized origin components.
# Sets: p_scheme, p_host, p_port (always explicit), p_authority (as written,
# lowercased, without a default port). Fails on anything it cannot parse
# unambiguously, including embedded userinfo.
parse_origin() {
    local url="$1" what="$2" rest authority hostport host port

    case "$url" in
        http://*)  p_scheme=http;  rest=${url#http://} ;;
        https://*) p_scheme=https; rest=${url#https://} ;;
        *) die "$what must start with http:// or https://." ;;
    esac

    # Authority ends at the first '/', '?' or '#'.
    authority=${rest%%/*}
    authority=${authority%%\?*}
    authority=${authority%%#*}
    [ -n "$authority" ] || die "$what has no host."

    # userinfo ('user@host') is how 'https://good.example.com@evil.tld' hides the
    # real host, so it is rejected outright rather than parsed away.
    case "$authority" in
        *@*) die "$what must not contain userinfo ('@' before the host)." ;;
    esac

    hostport=$(lower "$authority")

    case "$hostport" in
        \[*)
            # IPv6 literal: [::1] or [::1]:8080
            host=${hostport%%\]*}
            host="${host}]"
            port=${hostport#"$host"}
            case "$host" in
                *[!0-9a-f:.\[\]]*) die "$what has an invalid IPv6 host." ;;
            esac
            ;;
        *:*)
            host=${hostport%%:*}
            port=:${hostport#*:}
            case "$port" in
                *:*:*) die "$what has an invalid host:port." ;;
            esac
            ;;
        *)
            host=$hostport
            port=""
            ;;
    esac

    # Trailing dot is the same DNS name; normalize both sides identically.
    while [ "${host%.}" != "$host" ]; do host=${host%.}; done

    case "$host" in
        "") die "$what has no host." ;;
        \[*\]) : ;;
        *[!a-z0-9.-]*) die "$what has an invalid host." ;;
    esac

    if [ -n "$port" ]; then
        port=${port#:}
        case "$port" in
            "" | *[!0-9]*) die "$what has an invalid port." ;;
        esac
        [ "$port" -ge 1 ] && [ "$port" -le 65535 ] || die "$what has an invalid port."
        p_port=$port
        p_authority="${host}:${port}"
    else
        if [ "$p_scheme" = https ]; then p_port=443; else p_port=80; fi
        p_authority=$host
    fi

    p_host=$host
}

is_local_host() {
    case "$1" in
        localhost | 127.0.0.1 | '[::1]') return 0 ;;
        *) return 1 ;;
    esac
}

# Response files are the one thing a caller may write to disk, so the path is
# constrained to places where clobbering a file cannot alter what runs next.
resolve_save_path() {
    local path="$1" dir base parent rel root roots candidate matched

    case "$path" in
        "") die "--save-to requires a non-empty path." ;;
        -*) die "--save-to path must not start with '-'." ;;
        */) die "--save-to path must be a file, not a directory." ;;
    esac

    dir=$(dirname "$path")
    base=$(basename "$path")

    case "$base" in
        . | .. | .*) die "--save-to file name must not be hidden or a directory reference." ;;
    esac

    [ -d "$dir" ] || die "--save-to directory does not exist: $dir"
    parent=$(cd "$dir" >/dev/null 2>&1 && pwd -P) || die "--save-to directory is not accessible: $dir"

    roots=""
    for candidate in "$PWD" "${TMPDIR:-}" /tmp /var/folders; do
        [ -n "$candidate" ] || continue
        [ -d "$candidate" ] || continue
        root=$(cd "$candidate" >/dev/null 2>&1 && pwd -P) || continue
        roots="${roots}${root}
"
    done

    matched=""
    while IFS= read -r root; do
        [ -n "$root" ] || continue
        if [ "$parent" = "$root" ] || [ "${parent#"$root"/}" != "$parent" ]; then
            matched=$root
            break
        fi
    done <<EOF
$roots
EOF

    [ -n "$matched" ] || die "--save-to path must be under the current directory or a temp directory: $path"

    # No hidden directories below the allowed root ('.git/hooks', '.claude', ...).
    # Only the part below the root is checked: the root itself may legitimately
    # live inside a dot-directory.
    rel=${parent#"$matched"}
    case "/${rel}/" in
        */.*) die "--save-to path must not contain hidden directories: $path" ;;
    esac

    if [ -L "$path" ]; then
        die "--save-to path is a symlink, refusing to write through it: $path"
    fi
    if [ -e "$path" ] && [ ! -f "$path" ]; then
        die "--save-to path exists and is not a regular file: $path"
    fi

    printf '%s/%s' "$parent" "$base"
}

if [ $# -lt 3 ]; then
    usage >&2
    exit 1
fi

if [ -z "${LIGHTNING_PM_API_KEY:-}" ]; then
    die "LIGHTNING_PM_API_KEY is not set."
fi

# The key is handed to curl through a config file on stdin, whose syntax is
# line-based; a newline in the key would let it inject further curl options.
case "$LIGHTNING_PM_API_KEY" in
    *$'\n'* | *$'\r'*) die "LIGHTNING_PM_API_KEY must not contain newlines." ;;
esac

root_url=$1
method=$2
target=$3
shift 3

body=""
body_set=0
save_to=""

while [ $# -gt 0 ]; do
    case "$1" in
        --save-to)
            [ $# -ge 2 ] || die_usage "--save-to requires a path."
            save_to=$2
            shift 2
            ;;
        --save-to=*)
            save_to=${1#--save-to=}
            shift
            ;;
        --)
            die_usage "Passing raw curl arguments is no longer supported. Use --save-to PATH to download a response body."
            ;;
        -*)
            die_usage "Unknown option: $1"
            ;;
        *)
            [ "$body_set" -eq 0 ] || die_usage "Unexpected argument: $1"
            body=$1
            body_set=1
            shift
            ;;
    esac
done

case "$method" in
    GET | POST | PUT | PATCH | DELETE) : ;;
    *) die_usage "METHOD must be one of GET, POST, PUT, PATCH, DELETE." ;;
esac

parse_origin "$root_url" "ROOT_URL"
root_scheme=$p_scheme
root_host=$p_host
root_port=$p_port
root_authority=$p_authority

if [ "$root_scheme" != https ] && ! is_local_host "$root_host"; then
    die "ROOT_URL must use https:// (http:// is allowed for localhost only)."
fi

base_url="${root_scheme}://${root_authority}"

case "$target" in
    http://* | https://*)
        parse_origin "$target" "URL_OR_PATH"
        if [ "$p_scheme" != "$root_scheme" ] || [ "$p_host" != "$root_host" ] || [ "$p_port" != "$root_port" ]; then
            die "URL_OR_PATH points at ${p_scheme}://${p_authority}, which is not the ROOT_URL origin ${root_scheme}://${root_authority}. Refusing to send the API key."
        fi
        url=$target
        ;;
    /*)
        url="${base_url}${target}"
        ;;
    *)
        url="${base_url}/${target}"
        ;;
esac

if [ -n "$save_to" ]; then
    save_to=$(resolve_save_path "$save_to")
fi

# Deliberately no -L: see the security notes at the top of this file.
curl_args=(
    --fail-with-body
    --silent
    --show-error
    -X "$method"
    -K -
    "$url"
)

if [ "$body_set" -eq 1 ] && [ -n "$body" ]; then
    curl_args+=(
        -H "Content-Type: application/json"
        --data "$body"
    )
fi

if [ -n "$save_to" ]; then
    curl_args+=(--output "$save_to")
fi

# curl config syntax: double-quoted values understand backslash escapes, so the
# key is escaped rather than interpolated raw. Passing it here instead of as -H
# keeps it out of argv, where any local process could read it via `ps`.
escaped_key=${LIGHTNING_PM_API_KEY//\\/\\\\}
escaped_key=${escaped_key//\"/\\\"}

printf 'header = "X-LPM-API-Key: %s"\n' "$escaped_key" | curl "${curl_args[@]}"
