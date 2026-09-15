#!/usr/bin/env bash
# Build checks from the spec. `demo/verify.sh` runs all; `demo/verify.sh versions` runs one.
set -euo pipefail
. "$(dirname "$0")/lib/common.sh"
. "$DEMO_DIR/lib/checks.sh"

ALL_CHECKS=(versions)

checks=("$@")
[ ${#checks[@]} -gt 0 ] || checks=("${ALL_CHECKS[@]}")

for check in "${checks[@]}"; do
    step "Check: $check"
    "check_$check"
done

step "All checks passed: ${checks[*]}"
