# Session 3 Revised Plan: Architecture-First Approach

**Date**: November 27, 2025
**Previous Plan**: `phpstan-path-day-2:docs/session-3-cascade-investigation.md`
**Revision Reason**: CodeRabbit critique identified architectural issues that must be fixed before type annotation work
**Status**: Ready for execution

---

## Executive Summary

### What Changed

Original Session 3 plan assumed type annotations alone would guide developers to correct fixes. CodeRabbit's feedback revealed this assumption is incorrect - we need **architectural refactoring first**.

### New Approach

**Phase 0: Architectural Foundation** (6 hours, NEW)
- Fix 5 broken notification components
- Document 3 safe patterns
- Establish guidelines for team

**Phase 1-4: Type Annotation Work** (14-23 hours, original Session 3)
- Add @return types to scope methods
- Add @param types
- Fix method signatures
- Resolve cascading errors

### Why This Works Better

- ✅ Prevents production crashes (TypeError in notifications)
- ✅ Establishes correct patterns for team to follow
- ✅ Reduces architectural cascades in Session 3
- ✅ Creates sustainable type-safe codebase
- ⏱️ Adds 6 hours but with much higher quality

---

## Phase 0: Architectural Foundation (NEW)

### Objective

Fix the 5 notification components identified by CodeRabbit that will crash with TypeError in production.

**Affected Components**:
1. `app/Notifications/Discord.php`
2. `app/Notifications/Pushover.php`
3. `app/Notifications/Slack.php`
4. `app/Notifications/Telegram.php`
5. `app/Notifications/Webhook.php`

**Current Problem**:
```php
private Team $team;  // Non-nullable
$this->team = auth()->user()?->currentTeam();  // Can assign null
if (! $this->team) { /* Never reached - TypeError throws first */ }
```

**Expected Outcome**:
All 5 components use one of three safe patterns:
1. **Nullable Property**: `private ?Team $team = null;`
2. **Guaranteed Injection**: `__construct(Team $team)` with type hint
3. **Early Exit**: Check and return before using `$team`

---

### Phase 0 Execution Plan

#### Step 1: Analyze Current Implementation (30 minutes)

```bash
# Check which pattern each notification component uses
grep -A 10 "private Team" app/Notifications/*.php

# Identify which pattern is safest for each component
# Pattern 1: Nullable property (easiest)
# Pattern 2: Guaranteed injection (cleanest)
# Pattern 3: Early exit (requires refactoring)
```

#### Step 2: Fix Discord.php (1 hour)

**Analysis**: Determine constructor and usage pattern

**Option A: Make Nullable (Recommended)**
```php
// BEFORE
private Team $team;

public function __construct(/* ... */)
{
    $this->team = auth()->user()?->currentTeam();
    if (! $this->team) {
        // Unreachable due to TypeError
    }
}

// AFTER
private ?Team $team = null;

public function __construct(/* ... */)
{
    $this->team = auth()->user()?->currentTeam();
    if (! $this->team) {
        // Now reachable - safe
        $this->team = null;  // Or handle error
        return;
    }
}
```

**Option B: Guaranteed Injection (If applicable)**
```php
// AFTER - if team should always exist
public function __construct(Team $team, /* ... */)
{
    $this->team = $team;  // Guaranteed non-null
    // No null check needed
}
```

**Step**:
1. Analyze how Discord is instantiated
2. Choose pattern (Nullable is safer for notification components)
3. Make change
4. Update any code that accesses `$this->team`
5. Run tests: `php artisan test --filter Discord`

#### Step 3: Fix Pushover.php (1 hour)

Same process as Discord.

#### Step 4: Fix Slack.php (1 hour)

Same process as Discord.

#### Step 5: Fix Telegram.php (1 hour)

Same process as Discord.

#### Step 6: Fix Webhook.php (1 hour)

Same process as Discord.

#### Step 7: Create Pattern Documentation (1 hour)

**File**: `docs/patterns/safe-null-handling.md`

```markdown
# Safe Null Handling Patterns in Topgun

## Problem Statement

When a method can return null (e.g., `auth()->user()?->currentTeam()`),
we must not assign it to a non-nullable typed property without verification.

## Pattern 1: Nullable Property (Recommended for Services/Notifications)

Use when: Component MAY operate without the resource

```php
class Discord {
    private ?Team $team = null;  // Explicitly nullable

    public function send()
    {
        if (! $this->team) {
            return;  // Graceful degradation
        }
        // Safe to use $this->team
    }
}
```

**Pros**:
- Clear intent (property CAN be null)
- Safe at assignment time
- Easy to understand

**Cons**:
- Every usage needs null check

## Pattern 2: Guaranteed Injection (Recommended for Controllers/Components)

Use when: Component ALWAYS needs the resource

```php
class MyController {
    private Team $team;

    public function __construct(Team $team)
    {
        $this->team = $team;  // Guaranteed non-null
    }
}

// Called as:
new MyController(currentTeam());  // Only called if team exists
```

**Pros**:
- Compiler/IDE can verify
- No null checks needed
- Clear contract

**Cons**:
- Requires caller to verify
- Can't use in lazy contexts

## Pattern 3: Early Exit (Recommended for Middleware)

Use when: Component should fail fast if resource missing

```php
public function process(Request $request)
{
    $team = currentTeam();
    if (! $team) {
        abort(403, 'No team assigned');  // Exit before using
    }
    // Safe to use $team - now guaranteed non-null
}
```

**Pros**:
- Simple and clear
- No additional properties
- Safe scope

**Cons**:
- Only works for methods, not properties

## Choosing Your Pattern

### Decision Tree

```
Does component ALWAYS need the resource?
├─ YES → Use Pattern 2 (Guaranteed Injection)
└─ NO → Does component use resource in constructor?
    ├─ YES → Use Pattern 1 (Nullable Property)
    └─ NO → Use Pattern 3 (Early Exit in methods)
```

### Component Types

**Notification Components** (Discord, Slack, etc.)
- ✅ Pattern 1 (Nullable Property)
- Reason: Don't always have team, graceful degradation OK

**Controllers** (TeamController, ApplicationController, etc.)
- ✅ Pattern 2 or 3
- Pattern 2 if always needed
- Pattern 3 if checking existence

**Middleware**
- ✅ Pattern 3 (Early Exit)
- Reason: Fail fast on missing resource

**Livewire Components**
- ✅ Pattern 2 or 1
- Depends on component's role

## Anti-Patterns (DO NOT DO THIS)

```php
// ❌ WRONG: Non-nullable property + null assignment
private Team $team;
public function __construct() {
    $this->team = auth()->user()?->currentTeam();  // TypeError!
    if (! $this->team) { /* unreachable */ }
}

// ❌ WRONG: Using team without checks
$team = auth()->user()?->currentTeam();  // Can be null
echo $team->name;  // TypeError if null

// ❌ WRONG: Assuming middleware guarantees
public function test(Request $request) {
    $team = currentTeam();  // Can return null
    echo $team->id;  // No check - risky
}
```

## Testing Null Paths

When writing tests, verify both null and non-null cases:

```php
// Test with no team
Auth::logout();
$notification = new Discord();
$notification->send();  // Should not crash

// Test with team
Auth::loginAs($user);
$notification = new Discord();
$notification->send();  // Should work normally
```

## Further Reading

- Laravel: Nullsafe operator: https://www.php.net/manual/en/language.oop5.basic.php#language.oop5.basic.nullsafe
- PHPStan: Type System: https://phpstan.org/
- Coolify CLAUDE.md: See "Type Safety" section
```

#### Step 8: Run Full Test Suite (30 minutes)

```bash
# Unit tests (outside Docker)
./vendor/bin/pest tests/Unit

# Feature tests (inside Docker)
docker exec coolify php artisan test
```

**Verification**:
- ✅ No TypeError exceptions
- ✅ All notification tests pass
- ✅ No regressions in unrelated areas

#### Step 9: Git Checkpoint (15 minutes)

```bash
git add -A
git commit -m "phase-0: Fix architectural issues in notification components

- Change non-nullable Team properties to nullable
- Or inject Team as guaranteed parameter
- Prevents TypeError exceptions at runtime
- Establishes safe null handling patterns

Affected files:
- app/Notifications/Discord.php
- app/Notifications/Pushover.php
- app/Notifications/Slack.php
- app/Notifications/Telegram.php
- app/Notifications/Webhook.php

New documentation:
- docs/patterns/safe-null-handling.md"
```

---

## Phase 1-4: Type Annotation Work (Original Session 3)

### Overview

Once Phase 0 is complete, proceed with original Session 3 plan without modification.

### Reference

See: `phpstan-path-day-2:docs/session-3-cascade-investigation.md` for full details.

### Quick Summary

**Phase 1: Method Signature Completions** (2-3 hours)
- Add missing parameters to methods
- Expected reduction: ~50 errors

**Phase 2: PHPDoc Annotations** (4-6 hours)
- Add @param array<int, string> annotations
- Add @return Builder<Model> annotations
- Expected reduction: ~80 errors

**Phase 3: Relationship Return Types** (3-4 hours)
- Add return types to relationship methods
- Expected reduction: ~40 errors

**Phase 4: Complex Type Issues** (3-6 hours)
- Case-by-case analysis of remaining issues
- Expected reduction: ~33 errors

### Key Differences from Original Session 3

**Better Position**:
- Phase 0 has established safe patterns
- Developers have clear guidance (documentation)
- Fewer architectural cascades expected
- Session 3 batches will run smoother

**Same Methodology**:
- Incremental verification after each batch
- Git checkpoints after each batch
- Comprehensive testing
- Error count tracking

---

## Phase 5: Final Verification (2-3 hours)

### Automated Tests

```bash
# 1. PHP Syntax
find app -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"

# 2. PHPStan Analysis
docker exec coolify phpstan analyze --memory-limit=2G

# 3. Code Formatting
docker exec coolify ./vendor/bin/pint --test

# 4. Unit Tests
docker exec coolify ./vendor/bin/pest tests/Unit

# 5. Feature Tests
docker exec coolify ./vendor/bin/pest tests/Feature
```

### Success Criteria

- [ ] Error count: 6,697 → <6,500 (197+ reduction)
- [ ] No TypeError exceptions in notification system
- [ ] All tests passing
- [ ] No regressions in unrelated code
- [ ] Documentation complete and accurate

---

## Timeline Revised

### Original Session 3 Only
| Phase | Duration | Total |
|-------|----------|-------|
| Investigation | 2h | 2h |
| Batch 1 | 3h | 5h |
| Batch 2 | 6h | 11h |
| Batch 3 | 4h | 15h |
| Batch 4 | 6h | 21h |
| Testing | 2h | 23h |
| **TOTAL** | **23 hours** | |

### New Hybrid Approach
| Phase | Duration | Total |
|-------|----------|-------|
| **Phase 0: Architecture** | **6h** | **6h** |
| Investigation | 1h | 7h |
| Batch 1 | 3h | 10h |
| Batch 2 | 6h | 16h |
| Batch 3 | 4h | 20h |
| Batch 4 | 6h | 26h |
| Testing | 2h | 28h |
| **TOTAL** | **28 hours** | |

**Trade-off**: +5 hours, but significantly higher quality and risk reduction

---

## Risk Assessment Revised

### Original Session 3
| Risk | Level | Details |
|------|-------|---------|
| Notification Crashes | 🔴 HIGH | TypeError possible in production |
| Type Safety Guidance | 🟡 MEDIUM | Developers might make wrong choices |
| Architectural Issues | 🟡 MEDIUM | May appear later in testing |

### New Hybrid Approach
| Risk | Level | Details |
|------|-------|---------|
| Notification Crashes | 🟢 LOW | Fixed in Phase 0 |
| Type Safety Guidance | 🟢 LOW | Clear patterns documented |
| Architectural Issues | 🟢 LOW | Resolved before Phase 1 |

**Overall Risk**: 🔴 HIGH → 🟢 LOW

---

## Deliverables

### Phase 0
- [ ] 5 notification components fixed
- [ ] `docs/patterns/safe-null-handling.md` created
- [ ] All tests passing
- [ ] Git commit with Phase 0 work

### Phase 1-4
- [ ] Error catalog (all 203 errors identified)
- [ ] Dependency graph
- [ ] Batch 1 completion report
- [ ] Batch 2 completion report
- [ ] Batch 3 completion report
- [ ] Batch 4 completion report
- [ ] Session 3 completion summary
- [ ] 4 git commits (one per batch)

### Phase 5
- [ ] Final verification report
- [ ] Performance analysis
- [ ] Documentation updates
- [ ] Style guide updates

---

## Git Commit Strategy

```
phase-0: Fix architectural issues in notification components
├─ Change non-nullable properties to nullable
├─ Add pattern documentation
└─ Verify no TypeErrors

session-3: Investigation and error catalog
├─ Comprehensive PHPStan analysis
├─ Dependency mapping
└─ Risk assessment

session-3: batch-1 - method signature completions
├─ Add missing parameters to methods
├─ Fix 50 errors
└─ Verify incremental results

session-3: batch-2 - phpdoc annotations
├─ Add @param and @return annotations
├─ Fix 80 errors
└─ Verify incremental results

session-3: batch-3 - relationship return types
├─ Add return types to relationships
├─ Fix 40 errors
└─ Verify incremental results

session-3: batch-4 - complex type issues
├─ Case-by-case analysis and fixes
├─ Fix 33 errors
└─ Final verification

session-3: completion and documentation
├─ Full test suite verification
├─ Performance analysis
└─ Pattern establishment
```

---

## Decision Checklist

**Before starting Phase 0**:
- [ ] Team approves revised approach (Phase 0 + Session 3)
- [ ] 28 hours allocated (vs original 23 hours)
- [ ] CodeRabbit's feedback reviewed and understood
- [ ] Notification components tested in current state (to confirm issue)

**Before starting Phase 1**:
- [ ] Phase 0 complete and verified
- [ ] All tests passing
- [ ] Pattern documentation reviewed by team
- [ ] Phase 1 investigation complete (error catalog)

---

## Why This Revised Plan is Better

### Addresses CodeRabbit's Concerns
✅ Fixes the 5 broken notification components immediately
✅ Establishes safe patterns for developers to follow
✅ Prevents TypeError exceptions in production

### Maintains Session 3's Strengths
✅ Systematic approach to 203 cascading errors
✅ Comprehensive type annotation work
✅ Clear error reduction metric
✅ Incremental, testable approach

### Creates Sustainable Solution
✅ Type-safe codebase (from architecture + annotations)
✅ Clear patterns for future development
✅ Lower risk (architectural issues fixed first)
✅ Better team knowledge (documented patterns)

---

## Conclusion

The original Session 3 plan was sound, but incomplete. CodeRabbit's critique identified a category of fixes that require architectural work, not just type annotations.

This revised plan combines the best of both approaches:
1. **Phase 0**: Fix the architectural issues that caused the critique
2. **Phase 1-4**: Execute the comprehensive type annotation work
3. **Phase 5**: Verify everything works and document patterns

**Result**: A type-safe, production-ready codebase with clear patterns for future development.

---

**Status**: 📋 Ready for Execution
**Timeline**: 28 hours (vs 23 hours originally)
**Quality**: Significantly improved
**Risk Level**: 🟢 LOW (was 🔴 HIGH)
**Recommendation**: ✅ Proceed with Hybrid Approach

---

**Generated**: November 27, 2025
**Based On**: Session 3 Plan + CodeRabbit Feedback + Differential Analysis
**Author**: Claude Code
