#!/bin/bash
# Delete old sync issues (#1-37) and keep new sync (#111-198)

set -euo pipefail

REPO=$(git remote get-url origin | sed 's|.*github.com[:/]||' | sed 's|\.git$||')

echo "📦 Repository: $REPO"
echo ""
echo "This will DELETE the old incomplete sync:"
echo "  - Old issues: #1-37 (incomplete, no labels)"
echo ""
echo "Keeping: #111-198 (new sync with proper labels)"
echo ""
read -p "Are you sure? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
  echo "Aborted."
  exit 0
fi

echo ""
echo "Deleting old sync issues #1-37..."
echo ""

for i in {1..37}; do
  gh issue delete "$i" --repo "$REPO" --yes 2>/dev/null && echo "✓ Deleted #$i" || echo "⚠ Failed #$i"
done

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✨ Cleanup Complete!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Active issues: #111-198 (with proper labels)"
echo ""
echo "Next steps:"
echo "  - View issues: gh issue list --repo $REPO"
echo "  - Check mapping: cat .claude/epics/topgun/github-mapping.md"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
