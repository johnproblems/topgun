#!/bin/bash
# Delete duplicate GitHub issues created by sync-epic.sh
# This script detects duplicates by checking issue titles and deletes them

set -euo pipefail

EPIC_NAME="${1:-}"

if [ -z "$EPIC_NAME" ]; then
  echo "❌ Usage: ./delete-duplicates.sh <epic-name>"
  echo "   Example: ./delete-duplicates.sh topgun/2"
  exit 1
fi

EPIC_DIR=".claude/epics/${EPIC_NAME}"

if [ ! -d "$EPIC_DIR" ]; then
  echo "❌ Epic directory not found: $EPIC_DIR"
  exit 1
fi

# Get repo info
REPO=$(git remote get-url origin | sed 's|.*github.com[:/]||' | sed 's|\.git$||')
echo "📦 Repository: $REPO"
echo "📂 Epic: $EPIC_NAME"
echo ""

# Get the correct epic number from frontmatter
EPIC_GITHUB_URL=$(grep "^github:" "$EPIC_DIR/epic.md" | head -1 | sed 's/^github: //' | tr -d '[:space:]')
CORRECT_EPIC_NUMBER=$(echo "$EPIC_GITHUB_URL" | grep -oP '/issues/\K[0-9]+')

echo "✓ Correct epic issue: #$CORRECT_EPIC_NUMBER"
echo ""

# Get correct task numbers from task files
declare -A CORRECT_TASKS
TASK_FILES=$(find "$EPIC_DIR" -name "[0-9]*.md" ! -name "epic.md" | sort -V)

for task_file in $TASK_FILES; do
  task_github_url=$(grep "^github:" "$task_file" | head -1 | sed 's/^github: //' | tr -d '[:space:]')
  if [ -n "$task_github_url" ] && [[ ! "$task_github_url" =~ ^\[Will ]]; then
    task_number=$(echo "$task_github_url" | grep -oP '/issues/\K[0-9]+')
    task_name=$(grep -E "^(name|title):" "$task_file" | head -1 | sed -E 's/^(name|title): //' | sed 's/^"//;s/"$//')
    CORRECT_TASKS["$task_name"]=$task_number
  fi
done

echo "✓ Found ${#CORRECT_TASKS[@]} correct tasks"
echo ""

# Fetch all issues with epic label
EPIC_LABEL="epic:${EPIC_NAME}"
echo "Fetching all issues with label '$EPIC_LABEL'..."

ALL_ISSUES=$(gh issue list --repo "$REPO" --label "$EPIC_LABEL" --state all --limit 1000 --json number,title,state | jq -r '.[] | "\(.number)|\(.title)|\(.state)"')

if [ -z "$ALL_ISSUES" ]; then
  echo "✓ No issues found with label '$EPIC_LABEL'"
  exit 0
fi

echo ""
echo "Analyzing issues for duplicates..."
echo ""

# Find and delete duplicate epics
EPIC_TITLE=$(grep "^# Epic:" "$EPIC_DIR/epic.md" | head -1 | sed 's/^# Epic: //')
DUPLICATE_EPICS=()

while IFS='|' read -r issue_num issue_title issue_state; do
  # Check if it's an epic issue (has "epic" label)
  HAS_EPIC_LABEL=$(gh issue view "$issue_num" --repo "$REPO" --json labels | jq -r '.labels[] | select(.name=="epic") | .name')

  if [ -n "$HAS_EPIC_LABEL" ] && [ "$issue_title" == "$EPIC_TITLE" ] && [ "$issue_num" != "$CORRECT_EPIC_NUMBER" ]; then
    DUPLICATE_EPICS+=("$issue_num")
  fi
done <<< "$ALL_ISSUES"

# Delete duplicate epics
if [ ${#DUPLICATE_EPICS[@]} -gt 0 ]; then
  echo "🗑️  Found ${#DUPLICATE_EPICS[@]} duplicate epic issue(s)"
  for dup_num in "${DUPLICATE_EPICS[@]}"; do
    echo "   Deleting duplicate epic #$dup_num..."
    gh api -X DELETE "repos/$REPO/issues/$dup_num" 2>/dev/null && echo "   ✓ Deleted #$dup_num" || echo "   ⚠ Failed to delete #$dup_num (may need admin permissions)"
  done
  echo ""
else
  echo "✓ No duplicate epic issues found"
  echo ""
fi

# Find and delete duplicate tasks
DUPLICATE_TASKS=()
declare -A DUPLICATE_MAP

while IFS='|' read -r issue_num issue_title issue_state; do
  # Check if it's a task issue (has "task" label but not "epic" label)
  HAS_TASK_LABEL=$(gh issue view "$issue_num" --repo "$REPO" --json labels | jq -r '.labels[] | select(.name=="task") | .name')
  HAS_EPIC_LABEL=$(gh issue view "$issue_num" --repo "$REPO" --json labels | jq -r '.labels[] | select(.name=="epic") | .name')

  if [ -n "$HAS_TASK_LABEL" ] && [ -z "$HAS_EPIC_LABEL" ]; then
    # Check if this task title exists in our correct tasks
    if [ -n "${CORRECT_TASKS[$issue_title]:-}" ]; then
      correct_num="${CORRECT_TASKS[$issue_title]}"
      if [ "$issue_num" != "$correct_num" ]; then
        DUPLICATE_TASKS+=("$issue_num")
        DUPLICATE_MAP["$issue_num"]="$issue_title (correct: #$correct_num)"
      fi
    fi
  fi
done <<< "$ALL_ISSUES"

# Delete duplicate tasks
if [ ${#DUPLICATE_TASKS[@]} -gt 0 ]; then
  echo "🗑️  Found ${#DUPLICATE_TASKS[@]} duplicate task issue(s)"
  for dup_num in "${DUPLICATE_TASKS[@]}"; do
    echo "   Deleting #$dup_num: ${DUPLICATE_MAP[$dup_num]}"
    gh api -X DELETE "repos/$REPO/issues/$dup_num" 2>/dev/null && echo "   ✓ Deleted #$dup_num" || echo "   ⚠ Failed to delete #$dup_num (may need admin permissions)"
  done
  echo ""
else
  echo "✓ No duplicate task issues found"
  echo ""
fi

# Summary
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✨ Cleanup Complete!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Correct epic: #$CORRECT_EPIC_NUMBER"
echo "Correct tasks: ${#CORRECT_TASKS[@]}"
echo "Deleted duplicate epics: ${#DUPLICATE_EPICS[@]}"
echo "Deleted duplicate tasks: ${#DUPLICATE_TASKS[@]}"
echo ""
echo "Note: If deletion failed, you may need repository admin"
echo "permissions. Use GitHub's web interface to delete manually."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
