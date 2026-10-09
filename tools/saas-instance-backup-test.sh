#!/usr/bin/env bash
set -euo pipefail

script_dir=$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)
backup="$script_dir/saas-instance-backup.sh"
fixture_dir=$(mktemp -d /tmp/saas-backup-contract.XXXXXXXX)
trap 'rm -rf -- "$fixture_dir"' EXIT
output_file="$fixture_dir/dry-run.sql"
positive=0
negative=0

# A dry run must never reach Docker, even if it is installed.
docker() { printf 'Unexpected Docker invocation.\n' >&2; return 99; }
export -f docker

assert_failure() {
    local output
    if output=$(bash "$backup" "$@" 2>&1); then
        printf 'Expected rejection for invalid backup request.\n' >&2
        exit 1
    fi
    [[ "$output" == *'Backup error:'* ]] || { printf 'Missing clear error.\n' >&2; exit 1; }
    ((negative += 1))
}

assert_success() {
    local project=$1 output expected command
    command='mysqldump --single-transaction -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"'
    printf -v expected 'docker compose -p %q exec -T mysql sh -c '\''%s'\'' > %q\nshasum -a 256 %q > %q' "$project" "$command" "$output_file" "$output_file" "$output_file.sha256"
    output=$(MARIADB_ROOT_PASSWORD='backup-contract-secret-sentinel' bash "$backup" --dry-run "$project" "$output_file") || { printf 'Expected dry-run success.\n' >&2; exit 1; }
    [[ "$output" == "$expected" ]] || { printf 'Unexpected dry-run command.\n' >&2; exit 1; }
    [[ "$output" != *'backup-contract-secret-sentinel'* ]] || { printf 'Secret leaked.\n' >&2; exit 1; }
    [[ ! -e "$output_file" && ! -e "$output_file.sha256" ]] || { printf 'Dry run created output.\n' >&2; exit 1; }
    ((positive += 1))
}

assert_failure --dry-run Pilot "$output_file"
assert_failure --dry-run '' "$output_file"
assert_failure --dry-run -pilot "$output_file"
assert_failure --dry-run pilot.one "$output_file"
assert_failure --dry-run pilot "$backup"
assert_failure --dry-run pilot "$script_dir/backup-contract.sql"
assert_failure --dry-run pilot "$output_file/missing.sql"
assert_failure
assert_failure --dry-run
assert_failure pilot
assert_failure --dry-run pilot
assert_failure pilot "$output_file" extra
assert_failure --dry-run pilot "$output_file" extra
assert_failure pilot /dev/null
assert_success pilot-one
assert_success 1_pilot
output_file="$fixture_dir/saas backup 'contract.sql"
assert_success a

# Only shell fixtures run below: no Docker daemon or container is involved.
docker() {
    case "$fixture_mode" in
        directory) mkdir -- "$fixture_output" 2>/dev/null || : ;;
        symlink) ln -s -- "$fixture_dir/race-target" "$fixture_output" 2>/dev/null || : ;;
        checksum-directory) mkdir -- "$fixture_output.sha256" 2>/dev/null || : ;;
        checksum-symlink) ln -s -- "$fixture_dir/race-target" "$fixture_output.sha256" 2>/dev/null || : ;;
        dump-failure) printf 'partial dump\n'; return 1 ;;
    esac
    printf 'fixture dump\n'
}
shasum() {
    if [[ "$fixture_mode" == checksum-failure ]]; then
        printf 'partial checksum\n'
        return 1
    fi
    command shasum "$@"
}
# Inject a collision after validation but before reservation.
basename() {
    if [[ "$fixture_mode" == reservation-collision ]]; then
        ln -s -- "$fixture_dir/race-target" "$fixture_output.sha256"
    fi
    command basename "$@"
}
export -f docker shasum basename
export fixture_dir fixture_mode fixture_output
mkdir -- "$fixture_dir/race-target"

for fixture_mode in directory symlink checksum-directory checksum-symlink success; do
    fixture_output="$fixture_dir/$fixture_mode.sql"
    result=0
    diagnostics=$(bash "$backup" pilot "$fixture_output" 2>&1) || result=$?
    if [[ -d "$fixture_output" || -L "$fixture_output" || -d "$fixture_output.sha256" || -L "$fixture_output.sha256" ]]; then
        printf 'Race regression: dump/checksum destination became a directory or symlink (%s).\n' "$fixture_mode" >&2
        exit 1
    fi
    [[ "$result" == 0 && "$(<"$fixture_output")" == 'fixture dump' ]] || { printf 'Expected complete fixture dump: %s\n' "$diagnostics" >&2; exit 1; }
    (cd -- "$fixture_dir" && command shasum -a 256 -c "${fixture_output##*/}.sha256") >/dev/null || { printf 'Invalid checksum.\n' >&2; exit 1; }
    [[ ! -s "$fixture_dir/race-target/$(basename -- "$fixture_output")" ]] || exit 1
    ((positive += 1))
done

for fixture_mode in dump-failure checksum-failure reservation-collision; do
    fixture_output="$fixture_dir/$fixture_mode.sql"
    assert_failure pilot "$fixture_output"
    [[ ! -e "$fixture_output" && ! -L "$fixture_output" ]] || { printf 'Partial dump remains.\n' >&2; exit 1; }
    if [[ "$fixture_mode" == reservation-collision ]]; then
        [[ -L "$fixture_output.sha256" ]] || { printf 'Foreign checksum reservation removed.\n' >&2; exit 1; }
    else
        [[ ! -e "$fixture_output.sha256" && ! -L "$fixture_output.sha256" ]] || { printf 'Partial checksum remains.\n' >&2; exit 1; }
    fi
done

# Replace already-reserved paths from an executable fake Docker first on PATH.
unset -f docker
mkdir -- "$fixture_dir/bin"
printf '%s\n' '#!/usr/bin/env bash' 'set -eu' \
    'case "$fixture_mode" in' \
    'stream-swap)' \
    '    mv -- "$fixture_output" "$fixture_output.reserved"' \
    '    mv -- "$fixture_output.sha256" "$fixture_output.saved-checksum"' \
    '    printf "foreign content\n" > "$fixture_output" ;;' \
    'replace-checksum-directory)' \
    '    mv -- "$fixture_output.sha256" "$fixture_output.saved-checksum"' \
    '    mkdir -- "$fixture_output.sha256" ;;' \
    'replace-checksum-symlink)' \
    '    mv -- "$fixture_output.sha256" "$fixture_output.saved-checksum"' \
    '    ln -s -- "$fixture_dir/foreign-checksum" "$fixture_output.sha256" ;;' \
    'replace-dump-directory)' \
    '    mv -- "$fixture_output" "$fixture_output.reserved"' \
    '    mkdir -- "$fixture_output" ;;' \
    'esac' \
    'printf "fixture dump\n"' > "$fixture_dir/bin/docker"
chmod +x "$fixture_dir/bin/docker"
export PATH="$fixture_dir/bin:$PATH"
printf 'foreign checksum\n' > "$fixture_dir/foreign-checksum"
regressions=0
for fixture_mode in stream-swap replace-checksum-directory replace-checksum-symlink replace-dump-directory; do
    fixture_output="$fixture_dir/$fixture_mode.sql"
    result=0
    bash "$backup" pilot "$fixture_output" >/dev/null 2>&1 || result=$?
    valid=true
    [[ "$result" != 0 ]] || valid=false
    case "$fixture_mode" in
        stream-swap)
            expected_hash=$(printf 'fixture dump\n' | command shasum -a 256)
            expected_hash=${expected_hash%% *}
            [[ "$(<"$fixture_output.saved-checksum")" == "$expected_hash  ${fixture_output##*/}" ]] || valid=false
            [[ -f "$fixture_output" && "$(<"$fixture_output")" == 'foreign content' ]] || valid=false
            ;;
        replace-checksum-directory)
            [[ -d "$fixture_output.sha256" && ! -e "$fixture_output" ]] || valid=false ;;
        replace-checksum-symlink)
            [[ -L "$fixture_output.sha256" && ! -e "$fixture_output" ]] || valid=false
            [[ "$(<"$fixture_dir/foreign-checksum")" == 'foreign checksum' ]] || valid=false ;;
        replace-dump-directory)
            [[ -d "$fixture_output" && ! -e "$fixture_output.sha256" ]] || valid=false ;;
    esac
    if ! "$valid"; then
        printf 'Replacement regression: %s (exit %d).\n' "$fixture_mode" "$result" >&2
        ((regressions += 1))
    else
        ((negative += 1))
    fi
done
[[ "$regressions" == 0 ]] || { printf '%d replacement regressions; existing cases: %d positive, %d negative.\n' "$regressions" "$positive" "$negative" >&2; exit 1; }

printf 'Backup contract tests passed: %d positive, %d negative.\n' "$positive" "$negative"
