#!/bin/bash
# Epic Sync Script - Syncs epic and tasks to GitHub Issues
# Usage: ./sync-epic.sh <epic-name>

set -euo pipefail

EPIC_NAME="$1"
EPIC_DIR=".claude/epics/${EPIC_NAME}"

if [ -z "$EPIC_NAME" ]; then
  echo "❌ Usage: ./sync-epic.sh <epic-name>"
  exit 1
fi

if [ ! -d "$EPIC_DIR" ]; then
  echo "❌ Epic directory not found: $EPIC_DIR"
  exit 1
fi

# Get repo info
REPO=$(git remote get-url origin | sed 's|.*github.com[:/]||' | sed 's|\.git$||')
echo "📦 Repository: $REPO"
echo "📂 Epic: $EPIC_NAME"
echo ""

# Step 0: Check if already synced
echo "Checking sync status..."
EPIC_GITHUB_URL=$(grep "^github:" "$EPIC_DIR/epic.md" | head -1 | sed 's/^github: //' | tr -d '[:space:]')

if [ -n "$EPIC_GITHUB_URL" ] && [[ ! "$EPIC_GITHUB_URL" =~ ^\[Will ]]; then
  EPIC_NUMBER=$(echo "$EPIC_GITHUB_URL" | grep -oP '/issues/\K[0-9]+')
  echo "✓ Epic already synced: #$EPIC_NUMBER"
  echo "  URL: $EPIC_GITHUB_URL"
  echo ""
else
  # Step 1: Create Epic Issue
  echo "Creating epic issue..."
  EPIC_TITLE=$(grep "^# Epic:" "$EPIC_DIR/epic.md" | head -1 | sed 's/^# Epic: //')

  # Strip frontmatter and prepare body
  awk 'BEGIN{fs=0} /^---$/{fs++; next} fs==2{print}' "$EPIC_DIR/epic.md" > /tmp/epic-body-raw.md

  # Remove "## Tasks Created" section and replace with Stats
  awk '
    /^## Tasks Created/ { in_tasks=1; next }
    /^## / && in_tasks && !/^## Tasks Created/ {
      in_tasks=0
      if (total_tasks) {
        print "## Stats"
        print ""
        print "Total tasks: " total_tasks
        print "Parallel tasks: " parallel_tasks " (can be worked on simultaneously)"
        print "Sequential tasks: " sequential_tasks " (have dependencies)"
        if (total_effort) print "Estimated total effort: " total_effort
        print ""
      }
    }
    /^Total tasks:/ && in_tasks { total_tasks = $3; next }
    /^Parallel tasks:/ && in_tasks { parallel_tasks = $3; next }
    /^Sequential tasks:/ && in_tasks { sequential_tasks = $3; next }
    /^Estimated total effort:/ && in_tasks {
      gsub(/^Estimated total effort: /, "")
      total_effort = $0
      next
    }
    !in_tasks { print }
  ' /tmp/epic-body-raw.md > /tmp/epic-body.md

  # Create epic
  EPIC_URL=$(gh issue create --repo "$REPO" --title "$EPIC_TITLE" --body-file /tmp/epic-body.md 2>&1 | grep "https://github.com" || true)

  if [ -z "$EPIC_URL" ]; then
    echo "❌ Failed to create epic issue"
    exit 1
  fi

  EPIC_NUMBER=$(echo "$EPIC_URL" | grep -oP '/issues/\K[0-9]+')

  echo "✅ Epic created: #$EPIC_NUMBER"
  echo ""

  # Update epic frontmatter immediately
  current_date=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
  sed -i "s|^github:.*|github: https://github.com/$REPO/issues/$EPIC_NUMBER|" "$EPIC_DIR/epic.md"
  sed -i "s|^updated:.*|updated: $current_date|" "$EPIC_DIR/epic.md"
fi

# Step 2: Create Task Issues (with resume capability)
echo "Creating task issues..."
TASK_FILES=$(find "$EPIC_DIR" -name "[0-9]*.md" ! -name "epic.md" | sort -V)
TASK_COUNT=$(echo "$TASK_FILES" | wc -l)

echo "Found $TASK_COUNT task files"
echo ""

# Count already synced tasks
SYNCED_COUNT=0
CREATED_COUNT=0

> /tmp/task-mapping.txt

for task_file in $TASK_FILES; do
  # Check if task already has GitHub URL
  TASK_GITHUB_URL=$(grep "^github:" "$task_file" | head -1 | sed 's/^github: //' | tr -d '[:space:]' || echo "")

  if [ -n "$TASK_GITHUB_URL" ] && [[ ! "$TASK_GITHUB_URL" =~ ^\[Will ]]; then
    # Already synced - extract issue number
    task_number=$(echo "$TASK_GITHUB_URL" | grep -oP '/issues/\K[0-9]+')
    echo "$task_file:$task_number" >> /tmp/task-mapping.txt
    SYNCED_COUNT=$((SYNCED_COUNT + 1))
    echo "⏭ Skipped (already synced): #$task_number"
  else
    # Not synced - create issue
    task_name=$(grep -E "^(name|title):" "$task_file" | head -1 | sed -E 's/^(name|title): //' | sed 's/^"//;s/"$//' || echo "Untitled Task")
    awk 'BEGIN{fs=0} /^---$/{fs++; next} fs==2{print}' "$task_file" > /tmp/task-body.md

    task_url=$(gh issue create --repo "$REPO" --title "$task_name" --body-file /tmp/task-body.md 2>&1 | grep "https://github.com" || echo "")

    if [ -z "$task_url" ]; then
      echo "❌ Failed to create task: $task_name"
      echo "   File: $task_file"
      continue
    fi

    task_number=$(echo "$task_url" | grep -oP '/issues/\K[0-9]+')

    echo "$task_file:$task_number" >> /tmp/task-mapping.txt
    CREATED_COUNT=$((CREATED_COUNT + 1))
    echo "✓ Created #$task_number: $task_name"

    # Update task frontmatter immediately
    current_date=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
    sed -i "s|^github:.*|github: https://github.com/$REPO/issues/$task_number|" "$task_file"
    sed -i "s|^updated:.*|updated: $current_date|" "$task_file"
  fi
done

echo ""
echo "✅ Task sync complete"
echo "   Already synced: $SYNCED_COUNT"
echo "   Newly created: $CREATED_COUNT"
echo ""

# Step 3: Add Labels
echo "Adding labels..."

# Create epic-specific label (ignore if exists)
EPIC_LABEL="epic:${EPIC_NAME}"
gh label create "$EPIC_LABEL" --repo "$REPO" --color "0e8a16" --description "Tasks for $EPIC_NAME" 2>/dev/null || true

# Create standard labels if needed (ignore if exist)
gh label create "task" --repo "$REPO" --color "d4c5f9" --description "Individual task" 2>/dev/null || true
gh label create "epic" --repo "$REPO" --color "3e4b9e" --description "Epic issue" 2>/dev/null || true
gh label create "enhancement" --repo "$REPO" --color "a2eeef" --description "New feature or request" 2>/dev/null || true

# Add labels to epic
gh issue edit "$EPIC_NUMBER" --repo "$REPO" --add-label "epic,enhancement" 2>/dev/null || true
echo "✓ Labeled epic #$EPIC_NUMBER"

# Add labels to tasks
while IFS=: read -r task_file task_number; do
  gh issue edit "$task_number" --repo "$REPO" --add-label "task,$EPIC_LABEL" 2>/dev/null || true
  echo "✓ Labeled task #$task_number"
done < /tmp/task-mapping.txt

echo ""
echo "✅ All labels applied"
echo ""

# Step 4: Create/Update GitHub Mapping File
echo "Creating GitHub mapping file..."
cat > "$EPIC_DIR/github-mapping.md" << EOF
# GitHub Issue Mapping

Epic: #${EPIC_NUMBER} - https://github.com/${REPO}/issues/${EPIC_NUMBER}

Tasks:
EOF

while IFS=: read -r task_file task_number; do
  task_name=$(grep -E "^(name|title):" "$task_file" | head -1 | sed -E 's/^(name|title): //' | sed 's/^"//;s/"$//' || echo "Untitled")
  echo "- #${task_number}: ${task_name} - https://github.com/${REPO}/issues/${task_number}" >> "$EPIC_DIR/github-mapping.md"
done < /tmp/task-mapping.txt

current_date=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
echo "" >> "$EPIC_DIR/github-mapping.md"
echo "Synced: $current_date" >> "$EPIC_DIR/github-mapping.md"

echo "✅ GitHub mapping created"
echo ""

# Summary
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✨ Sync Complete!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Epic: #$EPIC_NUMBER - $EPIC_TITLE"
echo "Total tasks: $TASK_COUNT"
echo "Already synced: $SYNCED_COUNT"
echo "Newly created: $CREATED_COUNT"
echo "View: https://github.com/$REPO/issues/$EPIC_NUMBER"
echo ""
echo "Next steps:"
echo "  - View epic: /pm:epic-show $EPIC_NAME"
echo "  - Start work: /pm:issue-start <task_number>"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
