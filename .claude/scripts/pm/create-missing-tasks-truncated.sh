#!/bin/bash
# Create the 3 missing tasks with truncated descriptions

set -euo pipefail

REPO="johnproblems/topgun"
EPIC_DIR=".claude/epics/topgun"

echo "Creating missing task issues (with truncated descriptions)..."
echo ""

for num in 38 46 70; do
  task_file="$EPIC_DIR/$num.md"
  task_name=$(grep "^name:" "$task_file" | head -1 | sed 's/^name: //')

  echo "Creating task $num: $task_name"

  # Extract and truncate body (first 300 lines + note)
  {
    awk 'BEGIN{fs=0} /^---$/{fs++; next} fs==2{print}' "$task_file" | head -300
    echo ""
    echo "---"
    echo ""
    echo "**Note:** Full task details available in repository at `.claude/epics/topgun/$num.md`"
  } > "/tmp/task-body-$num.md"

  # Create issue
  task_url=$(gh issue create --repo "$REPO" --title "$task_name" --body-file "/tmp/task-body-$num.md" 2>&1 | grep "https://github.com" || echo "")

  if [ -n "$task_url" ]; then
    task_number=$(echo "$task_url" | grep -oP '/issues/\K[0-9]+')
    echo "  ✓ Created #$task_number"

    # Update frontmatter
    current_date=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
    sed -i "s|^github:.*|github: https://github.com/$REPO/issues/$task_number|" "$task_file"
    sed -i "s|^updated:.*|updated: $current_date|" "$task_file"

    # Add labels
    gh issue edit "$task_number" --repo "$REPO" --add-label "task,epic:topgun" 2>/dev/null && echo "  ✓ Labeled #$task_number"
  else
    echo "  ❌ Failed to create issue"
    cat "/tmp/task-body-$num.md" | wc -c | xargs echo "  Body size (chars):"
  fi

  echo ""
done

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ Done! Missing tasks created."
echo ""
echo "Next steps:"
echo "  1. Delete old incomplete sync: bash .claude/scripts/pm/delete-old-sync.sh"
echo "  2. Update github-mapping.md if needed"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
