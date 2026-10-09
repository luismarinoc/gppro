#!/usr/bin/env bash
set -euo pipefail

script_dir=$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
preflight="$script_dir/saas-instance-preflight.sh"
positive=0
negative=0

assert_success() {
    local output
    output=$(bash "$preflight" "$@") || { printf 'Expected success for declared pair.\n' >&2; exit 1; }
    [[ "$output" == *'Preflight passed'* ]] || { printf 'Missing success confirmation.\n' >&2; exit 1; }
    ((positive += 1))
}

assert_failure() {
    local output
    if output=$(bash "$preflight" "$@" 2>&1); then
        printf 'Expected rejection for invalid declared pair.\n' >&2
        exit 1
    fi
    [[ "$output" == *'Preflight error:'* ]] || { printf 'Missing clear error.\n' >&2; exit 1; }
    ((negative += 1))
}

assert_success pilot-one 8001 pilot_two 8002
assert_success a 1 b 65535
assert_failure pilot-one 8001 pilot-one 8002
assert_failure pilot-one 8001 pilot-two 8001
assert_failure pilot-one 08001 pilot-two 8001
assert_failure pilot-one 8001 pilot-two
assert_failure pilot-one 8001 pilot-two 8002 extra
assert_failure '' 8001 pilot-two 8002
assert_failure Pilot-One 8001 pilot-two 8002
assert_failure '-pilot' 8001 pilot-two 8002
assert_failure 'pilot.two' 8001 pilot-two 8002
assert_failure pilot-one 0 pilot-two 8002
assert_failure pilot-one 65536 pilot-two 8002
assert_failure pilot-one 999999999999999999999999 pilot-two 8002
assert_failure pilot-one 8x pilot-two 8002
assert_failure pilot-one 08001 pilot-two -1

printf 'Preflight contract tests passed: %d positive, %d negative.\n' "$positive" "$negative"
