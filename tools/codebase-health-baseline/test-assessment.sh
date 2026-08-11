#!/usr/bin/env bash
set -euo pipefail

script_dir=$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
assessment="$script_dir/assess.sh"
repository_root=$(git -C "$script_dir/../.." rev-parse --show-toplevel)
status_before=$(git -C "$repository_root" status --porcelain)

assert_failure_without_passing_claim() {
    local output
    if output=$("$@" 2>&1); then
        printf 'Expected command to fail: %s\n' "$*" >&2
        exit 1
    fi

    if [[ "$output" == *'BASELINE_CLAIM=passing'* ]]; then
        printf 'Rejected assessment must not claim a passing baseline.\n' >&2
        exit 1
    fi
}

valid_output=$($assessment "$repository_root")
status_after=$(git -C "$repository_root" status --porcelain)
[[ "$status_before" == "$status_after" ]]
[[ "$valid_output" == *"REPOSITORY_ROOT=$repository_root"* ]]
[[ "$valid_output" == *'COMMIT='* ]]
[[ "$valid_output" == *'BASELINE_CLAIM=unavailable'* ]]
[[ "$valid_output" == *'LEDGER='* ]]
[[ "$valid_output" == *'REPORT='* ]]

ledger="$repository_root/docs/runbooks/codebase-health-baseline/ledger.json"
report="$repository_root/docs/runbooks/codebase-health-baseline/report.md"
backlog="$repository_root/docs/runbooks/codebase-health-baseline/backlog.md"
python3 - "$ledger" "$report" "$backlog" <<'PY'
import json
import sys
from pathlib import Path

ledger = json.loads(Path(sys.argv[1]).read_text())
assert ledger['baseline_claim'] == 'unavailable'
assert ledger['checks']
assert all({'procedure', 'inputs', 'result', 'availability', 'reproducibility',
            'evidence_class', 'source_refs', 'confidence_impact'} <= set(check)
           for check in ledger['checks'])
assert all(check['evidence_class'] in {'verified fact', 'hypothesis', 'follow-up validation'}
           for check in ledger['checks'])
report_text = Path(sys.argv[2]).read_text()
backlog_text = Path(sys.argv[3]).read_text()
signals = {signal['id']: signal for signal in ledger['signals']}
assert signals['runtime-matrix-drift']['evidence_class'] == 'verified fact'
runtime_drift = signals['runtime-matrix-drift']
authoritative_sources = {reference.split(':', 1)[0] for reference in runtime_drift['source_refs']}
assert {'README.md', 'Dockerfile', '.github/workflows/testing.yaml'} <= authoritative_sources
assert 'not identical' in runtime_drift['interpretation']
assert any(check['id'] == 'migration-forward-replay' for check in ledger['checks'])
assert all(check['availability'] == 'unavailable'
           for check in ledger['checks'] if check['id'] != 'phpstan-suppressions')
assert 'No passing baseline claim' in report_text
assert 'does not change application source' in report_text
assert 'P1' in backlog_text and 'P2' in backlog_text
assert 'verified fact' in backlog_text and 'hypothesis' in backlog_text
assert 'No hypothesis receives a remediation priority' in backlog_text
PY

assert_failure_without_passing_claim "$assessment" ../
assert_failure_without_passing_claim "$assessment" "$repository_root" --expected-root /tmp/not-the-repository

printf 'Assessment contract tests passed.\n'
