#!/usr/bin/env bash
set -euo pipefail

fail() {
    printf 'Preflight error: %s\n' "$1" >&2
    exit 1
}

if (( $# != 4 )); then
    fail 'expected exactly four arguments: project-A host-port-A project-B host-port-B'
fi

valid_project() {
    [[ "$1" =~ ^[a-z0-9][a-z0-9_-]*$ ]]
}

valid_port() {
    local port=$1
    [[ "$port" =~ ^[0-9]+$ ]] || return 1
    # Compare by length after removing leading zeros to avoid shell integer overflow.
    port="${port#"${port%%[!0]*}"}"
    [[ -n "$port" ]] || return 1
    (( ${#port} < 5 )) || { (( ${#port} == 5 )) && (( 10#$port <= 65535 )); }
}

valid_project "$1" || fail 'project-A must start with a lowercase letter or digit and contain only lowercase letters, digits, underscores, or hyphens'
valid_project "$3" || fail 'project-B must start with a lowercase letter or digit and contain only lowercase letters, digits, underscores, or hyphens'
valid_port "$2" || fail 'host-port-A must be a decimal port in 1..65535'
valid_port "$4" || fail 'host-port-B must be a decimal port in 1..65535'
[[ "$1" != "$3" ]] || fail 'project names must differ'
# Normalize decimal values for comparison (e.g. 08001 and 8001).
port_a="${2#"${2%%[!0]*}"}"
port_b="${4#"${4%%[!0]*}"}"
[[ "$port_a" != "$port_b" ]] || fail 'host ports must differ'

printf 'Preflight passed: declared project names and host ports are distinct and valid.\n'
