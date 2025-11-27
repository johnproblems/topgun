#!/bin/bash
# Delete duplicate GitHub issues by issue number ranges
# Keeps the first sync (issues #1-37) and deletes duplicates

set -euo pipefail

REPO=$(git remote get-url origin | sed 's|.*github.com[:/]||' | sed 's|\.git$||')

echo "📦 Repository: $REPO"
echo ""
echo "This will DELETE (not close) the following issues:"
echo "  - Epic duplicates: #38, #75"
echo "  - Task duplicates: #39-74, #76-110"
echo ""
echo "Keeping: #1 (epic) and #2-37 (tasks)"
echo ""
read -p "Are you sure? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
  echo "Aborted."
  exit 0
fi

echo ""
echo "Deleting duplicate issues..."
echo ""

# Delete duplicate epics
for epic_num in 38 75; do
  echo "Deleting epic #$epic_num..."
  gh issue delete "$epic_num" --repo "$REPO" --yes 2>/dev/null && echo "✓ Deleted #$epic_num" || echo "⚠ Failed to delete #$epic_num"
done

echo ""

# Delete second set of duplicate tasks (#39-74)
echo "Deleting tasks #39-74..."
for i in {39..74}; do
  gh issue delete "$i" --repo "$REPO" --yes 2>/dev/null && echo "✓ Deleted #$i" || echo "⚠ Failed #$i"
done

echo ""

# Delete third set of duplicate tasks (#76-110)
echo "Deleting tasks #76-110..."
for i in {76..110}; do
  gh issue delete "$i" --repo "$REPO" --yes 2>/dev/null && echo "✓ Deleted #$i" || echo "⚠ Failed #$i"
done

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✨ Cleanup Complete!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Remaining issues: #1 (epic) and #2-37 (tasks)"
echo ""
echo "Next steps:"
echo "  1. Run sync again to add labels and update frontmatter:"
echo "     bash .claude/scripts/pm/sync-epic.sh topgun"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
