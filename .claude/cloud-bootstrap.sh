#!/usr/bin/env bash
# The therapy cloud environment's setup script field holds this file. It runs the repo's own
# .claude/cloud-setup.sh, so setup changes ship through git and the field never needs editing.
set -euo pipefail

script="$(find /home /root /workspace -maxdepth 4 -path '*/.claude/cloud-setup.sh' -not -path '*/node_modules/*' 2>/dev/null | head -1 || true)"
if [ -z "$script" ]; then
  echo "Could not find .claude/cloud-setup.sh in a cloned repository." >&2
  exit 1
fi
cd "$(dirname "$script")/.."
exec bash .claude/cloud-setup.sh
