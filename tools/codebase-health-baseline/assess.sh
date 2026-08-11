#!/usr/bin/env bash
set -euo pipefail

usage() {
    printf 'Usage: %s ABSOLUTE_REPOSITORY_ROOT [--expected-root ABSOLUTE_REPOSITORY_ROOT]\n' "$0" >&2
}

fail() {
    printf 'Assessment unavailable: %s\n' "$1" >&2
    exit 1
}

if [[ $# -lt 1 || $# -gt 3 ]]; then
    usage
    exit 2
fi

target_path=$1
expected_root=''
if [[ $# -eq 3 && $2 == '--expected-root' ]]; then
    expected_root=$3
elif [[ $# -ne 1 ]]; then
    usage
    exit 2
fi

[[ $target_path = /* ]] || fail 'repository root must be an absolute path'
[[ -d $target_path ]] || fail 'repository root does not exist'

resolved_root=$(git -C "$target_path" rev-parse --show-toplevel 2>/dev/null) \
    || fail 'path is not inside a Git repository'
resolved_root=$(CDPATH= cd -- "$resolved_root" && pwd -P)
requested_root=$(CDPATH= cd -- "$target_path" && pwd -P)
[[ $resolved_root == "$requested_root" ]] \
    || fail 'path must identify the repository root, not a subdirectory'

if [[ -n $expected_root ]]; then
    [[ $expected_root = /* ]] || fail 'expected root must be an absolute path'
    expected_root=$(CDPATH= cd -- "$expected_root" 2>/dev/null && pwd -P) \
        || fail 'expected root does not exist'
    [[ $resolved_root == "$expected_root" ]] \
        || fail 'repository root does not match expected root'
fi

commit=$(git -C "$resolved_root" rev-parse HEAD 2>/dev/null) \
    || fail 'unable to resolve repository commit'

ledger="$resolved_root/docs/runbooks/codebase-health-baseline/ledger.json"
report="$resolved_root/docs/runbooks/codebase-health-baseline/report.md"
[[ -f $ledger && -f $report ]] || fail 'baseline outputs are missing'
python3 - "$ledger" <<'PY' || fail 'ledger is not a valid evidence contract'
import json
import sys
from pathlib import Path

data = json.loads(Path(sys.argv[1]).read_text())
required = {'procedure', 'inputs', 'result', 'availability', 'reproducibility',
            'evidence_class', 'source_refs', 'confidence_impact'}
classes = {'verified fact', 'hypothesis', 'follow-up validation'}
if data.get('baseline_claim') != 'unavailable' or not data.get('checks'):
    raise SystemExit(1)
if any(not required <= set(check) or check['evidence_class'] not in classes
       for check in data['checks']):
    raise SystemExit(1)
PY

printf 'REPOSITORY_ROOT=%s\n' "$resolved_root"
printf 'COMMIT=%s\n' "$commit"
printf 'BASELINE_CLAIM=unavailable\n'
printf 'LEDGER=%s\n' "$ledger"
printf 'REPORT=%s\n' "$report"
printf 'REASON=checks unavailable unless their Docker/dependency prerequisites are present\n'
