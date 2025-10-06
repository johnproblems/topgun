Enhance a Coolify Enterprise Transformation task file with comprehensive specifications.

**Usage:** `/enhance-task <task_number>`

**Example:** `/enhance-task 29`

---

You will enhance the specified task file from a basic placeholder (40 lines) to a comprehensive specification (600-1200 lines).

## Step 1: Read Template Files

Read these template examples to understand the pattern:

**Backend Service Templates:**
- `/home/topgun/topgun/.claude/epics/topgun/2.md`
- `/home/topgun/topgun/.claude/epics/topgun/7.md`
- `/home/topgun/topgun/.claude/epics/topgun/14.md`

**Vue Component Templates:**
- `/home/topgun/topgun/.claude/epics/topgun/4.md`
- `/home/topgun/topgun/.claude/epics/topgun/5.md`
- `/home/topgun/topgun/.claude/epics/topgun/6.md`

**Background Job Templates:**
- `/home/topgun/topgun/.claude/epics/topgun/10.md`
- `/home/topgun/topgun/.claude/epics/topgun/18.md`

**Epic Context:**
- `/home/topgun/topgun/.claude/epics/topgun/epic.md`

## Step 2: Read Current Task File

Read the task file at: `/home/topgun/topgun/.claude/epics/topgun/$ARGUMENTS.md`

Understand:
- Task title and what it should accomplish
- Dependencies
- Whether it's backend, frontend, database, or testing

## Step 3: Enhance the Task

Create a comprehensive enhancement with these sections:

### Frontmatter (PRESERVE EXACTLY AS-IS)
Do NOT modify the YAML frontmatter between the `---` lines.

### Description (200-400 words)
- What the task accomplishes
- Why it's important
- How it integrates with other components
- Key features (4-6 bullets)

### Acceptance Criteria (12-15 items)
Use `- [ ]` checkboxes. Include:
- Functional requirements
- Performance requirements
- Security requirements
- Integration requirements

### Technical Details (LARGEST SECTION - 50-70% of content)

Include:
- **File paths:** Exact locations for all files
- **Full code examples:** 200-700 line implementations
  - Backend: Complete PHP classes with methods
  - Frontend: Complete Vue components with script/template/style
  - Database: Complete migrations with indexes
- **Integration code:** Controllers, routes, policies
- **Configuration:** Config files, environment variables

### Implementation Approach (8-10 steps)
Step-by-step plan with specific actions for each step.

### Test Strategy
Include ACTUAL test code examples:
- Unit tests (Pest for PHP, Vitest for Vue)
- Integration tests
- Browser tests (Dusk) if applicable

### Definition of Done (18-25 items)
Comprehensive checklist with `- [ ]` checkboxes.

### Related Tasks
List dependencies and integrations.

## Step 4: Write the Enhanced Task

Use the Write tool to replace the entire file:

```
file_path: /home/topgun/topgun/.claude/epics/topgun/$ARGUMENTS.md
content: [Your complete enhanced task]
```

## Step 5: Verify

After writing:
1. Use Bash to check line count: `wc -l /home/topgun/topgun/.claude/epics/topgun/$ARGUMENTS.md`
2. Verify it's 600-1200 lines
3. Confirm file was written successfully

## Quality Standards

- ✅ 600-1200 lines total
- ✅ Realistic, production-ready code examples
- ✅ Specific file paths
- ✅ All checkboxes use `- [ ]` NOT `- [x]`
- ✅ Follows Coolify Laravel/Vue.js patterns
- ✅ Includes comprehensive tests
- ✅ No placeholder text

## Technology Context

- **Laravel:** Version 12, Pest testing, Service/Interface pattern
- **Vue.js:** Version 3 Composition API, Inertia.js, Vitest
- **Database:** PostgreSQL 15+, proper indexes
- **Coolify Patterns:** Actions, Jobs, ExecuteRemoteCommand trait

Choose the appropriate template based on task type and follow its structure exactly.
