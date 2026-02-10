#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DOCS_DIR="$ROOT_DIR/docs"
OUTPUT_FILE="$DOCS_DIR/FINAL_PRODUCT.md"

if [[ ! -d "$DOCS_DIR" ]]; then
  echo "docs directory not found: $DOCS_DIR" >&2
  exit 1
fi

{
  echo "# Final Product"
  echo
  echo "Generated from all files under \`docs/\` on $(date -u +"%Y-%m-%dT%H:%M:%SZ")."
  echo
  echo "## Files Included"
  rg --files "$DOCS_DIR" | rg -v '^.*/FINAL_PRODUCT\.md$' | sed "s#^$ROOT_DIR/##" | sort | sed 's/^/- `/' | sed 's/$/`/'
  echo

  while IFS= read -r file; do
    rel="${file#"$ROOT_DIR/"}"
    echo "## $rel"
    echo
    echo '```'
    cat "$file"
    echo
    echo '```'
    echo
  done < <(rg --files "$DOCS_DIR" | rg -v '^.*/FINAL_PRODUCT\.md$' | sort)
} > "$OUTPUT_FILE"

echo "Wrote $OUTPUT_FILE"
