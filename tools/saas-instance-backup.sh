#!/usr/bin/env bash
set -euo pipefail

fail() {
    printf 'Backup error: %s\n' "$1" >&2
    exit 1
}

dry_run=false
if [[ "${1:-}" == '--dry-run' ]]; then
    dry_run=true
    shift
fi
(( $# == 2 )) || fail 'expected project and output-file (optionally preceded by --dry-run)'
project=$1
output=$2
[[ "$project" =~ ^[a-z0-9][a-z0-9_-]*$ ]] || fail 'project must start with a lowercase letter or digit and contain only lowercase letters, digits, underscores, or hyphens'
[[ -n "$output" && "$output" != */ ]] || fail 'output must name a file'
[[ ! -e "$output" && ! -L "$output" ]] || fail 'output already exists'
[[ ! -e "$output.sha256" && ! -L "$output.sha256" ]] || fail 'checksum output already exists'

script_dir=$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)
repo_root=$(CDPATH= cd -- "$script_dir/.." && pwd -P)
parent=$(CDPATH= cd -- "$(dirname -- "$output")" 2>/dev/null && pwd -P) || fail 'output parent directory must exist'
[[ "$parent" != "$repo_root" && "$parent" != "$repo_root/"* ]] || fail 'backups must be stored outside the repository'

command='mysqldump --single-transaction -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"'
if "$dry_run"; then
    printf 'docker compose -p %q exec -T mysql sh -c '\''%s'\'' > %q\n' "$project" "$command" "$output"
    printf 'shasum -a 256 %q > %q\n' "$output" "$output.sha256"
    exit 0
fi

umask 077
# Resolve the parent once, and use absolute paths to avoid option-like filenames.
output="$parent/$(basename -- "$output")"
output_reserved=false
checksum_reserved=false
complete=false
file_inode() {
    local identity
    identity=$(LC_ALL=C ls -di -- "$1") || return 1
    read -r identity _ <<< "$identity"
    printf '%s' "$identity"
}
is_reserved_file() {
    [[ -f "$1" && ! -L "$1" && "$(file_inode "$1")" == "$2" ]]
}
cleanup() {
    if ! "$complete"; then
        if "$output_reserved" && is_reserved_file "$output" "$output_inode"; then rm -f -- "$output" || true; fi
        if "$checksum_reserved" && is_reserved_file "$output.sha256" "$checksum_inode"; then rm -f -- "$output.sha256" || true; fi
    fi
}
trap cleanup EXIT
trap 'exit 1' HUP INT TERM

# Noclobber reserves each new regular file exclusively (O_EXCL). Keep the
# descriptors open: never reopen or move onto a potentially changed destination.
set -C
if ! { exec 3> "$output"; } 2>/dev/null; then
    fail 'cannot reserve output'
fi
output_inode=$(file_inode "$output") || fail 'cannot identify output'
output_reserved=true
if ! { exec 4> "$output.sha256"; } 2>/dev/null; then
    fail 'cannot reserve checksum output'
fi
checksum_inode=$(file_inode "$output.sha256") || fail 'cannot identify checksum output'
checksum_reserved=true
set +C

# Credentials are expanded only inside the container, never by this shell.
# Partial files are visible during execution; failure removes our reservations.
digest=$(docker compose -p "$project" exec -T mysql sh -c "$command" | tee /dev/fd/3 | shasum -a 256) || fail 'database dump or checksum failed'
digest=${digest%% *}
# Match shasum's escaped filename format for backslashes and newlines.
checksum_name=${output##*/}
escaped=false
if [[ "$checksum_name" == *\\* || "$checksum_name" == *$'\n'* ]]; then
    escaped=true
    checksum_name=${checksum_name//\\/\\\\}
    checksum_name=${checksum_name//$'\n'/\\n}
fi
if "$escaped"; then printf '\\' >&4; fi
printf '%s  %s\n' "$digest" "$checksum_name" >&4 || fail 'cannot write checksum'
is_reserved_file "$output" "$output_inode" || fail 'output reservation was replaced'
is_reserved_file "$output.sha256" "$checksum_inode" || fail 'checksum reservation was replaced'
complete=true
exec 3>&- 4>&-
