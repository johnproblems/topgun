# Session 3: Cascade Investigation & Resolution Plan

**Date**: November 27, 2025
**Issue**: [#203](https://github.com/johnproblems/topgun/issues/203)
**Phase**: PHASE 1 - LOW-HANGING FRUIT & CRITICAL STABILITY
**Focus**: Resolve 203 cascading errors revealed by Session 2 type safety improvements

---

## Executive Summary

Session 2 enhanced type safety by adding return type hints to 29 scope methods. This enabled PHPStan to perform deeper analysis, revealing **203 previously hidden bugs**. Session 3 will systematically resolve these cascading errors using an investigative, risk-minimizing approach.

**Goal**: Reduce errors from 6,697 to below 6,500 (197+ error reduction)

---

## Understanding the Cascade

### What Happened

```
Session 2: Added Return Types → PHPStan Can Now Analyze Calling Code → Found Hidden Bugs
```

**Before Session 2**:
```php
// PHPStan couldn't analyze this because it didn't know what ownedByCurrentTeam() returns
$projects = Project::ownedByCurrentTeam(['name'])->get();
// ✅ No PHPStan error (method return type unknown)
```

**After Session 2**:
```php
/**
 * @return \Illuminate\Database\Eloquent\Builder<Project>
 */
public static function ownedByCurrentTeam(): \Illuminate\Database\Eloquent\Builder  // No parameters!

// Now PHPStan can analyze:
$projects = Project::ownedByCurrentTeam(['name'])->get();
// ❌ PHPStan error: "Method invoked with 1 parameter, 0 expected"
```

**This is progress!** We're making invisible bugs visible.

---

## Cascade Error Categories

### Preliminary Analysis (from initial PHPStan output)

**Total Cascade Errors**: 203

**Category A: Method Signature Mismatches** (~50 errors)
- Methods called with wrong number/type of parameters
- Quick fix: Add missing parameters to method signatures
- **Risk**: LOW (parameter additions are backward compatible)
- **Effort**: 2-3 hours

**Category B: Missing Type Annotations** (~80 errors)
- PHPDoc `@param` / `@return` annotations needed
- Generic type specifications required
- **Risk**: LOW (annotations only, no logic changes)
- **Effort**: 4-6 hours

**Category C: Relationship Return Types** (~40 errors)
- Methods like `applications()`, `services()`, `team()` need return types
- Affects morphMany, belongsTo, hasMany relationships
- **Risk**: LOW-MEDIUM (type hints may reveal more issues)
- **Effort**: 3-4 hours

**Category D: Complex Type Issues** (~33 errors)
- Array value type specifications
- Generic type mismatches
- Deeply nested type problems
- **Risk**: MEDIUM (may require refactoring)
- **Effort**: 3-6 hours

---

## Investigation Methodology

### Phase 1: Comprehensive Error Catalog (1 hour)

**Objective**: Create complete inventory of all 203 errors

**Process**:
1. Run PHPStan with detailed output
2. Extract and categorize each unique error pattern
3. Identify dependencies between errors
4. Create priority matrix

**Tools**:
```bash
# Generate detailed error report
docker exec coolify sh -c "cd /var/www/html && \
  ./vendor/bin/phpstan analyze --memory-limit=2G --error-format=json" \
  > phpstan-cascade-errors.json

# Analyze error patterns
jq '.files | to_entries[] | .value.messages[] | {
  file: .file,
  line: .line,
  message: .message,
  identifier: .identifier
}' phpstan-cascade-errors.json | sort | uniq -c
```

**Deliverable**: `session-3-error-catalog.md` with:
- Complete list of all 203 errors
- Categorization by type and complexity
- Dependency graph showing fix order
- Risk assessment for each category

### Phase 2: Dependency Mapping (30 minutes)

**Objective**: Understand which fixes depend on other fixes

**Example Dependency Chain**:
```
Fix: Project::ownedByCurrentTeam() signature
  ↓ Enables
Fix: Livewire components calling Project::ownedByCurrentTeam()
  ↓ Enables
Fix: Related methods in same Livewire components
```

**Process**:
1. Group errors by file
2. Identify shared method calls
3. Map fix prerequisites
4. Determine optimal fix order

**Deliverable**: Dependency graph (text format)

### Phase 3: Risk Assessment (30 minutes)

**Objective**: Classify each fix by risk level

**Risk Criteria**:
- **Runtime Impact**: Does fix change behavior?
- **API Contract**: Does fix change public interfaces?
- **Test Coverage**: Are affected areas tested?
- **Complexity**: How many lines of code affected?

**Risk Levels**:
- 🟢 **LOW**: Type annotations only, no logic changes
- 🟡 **MEDIUM**: Parameter additions, may affect calling code
- 🔴 **HIGH**: Refactoring required, breaking changes possible

**Deliverable**: Risk matrix for all fixes

---

## Execution Strategy

### Principle: Minimize Cascade Amplification

**Golden Rule**: Each fix should reduce errors, not create more

**Approach**:
1. **Fix in Dependency Order**: Resolve prerequisites first
2. **Batch Similar Fixes**: Apply same pattern to multiple files
3. **Verify Incrementally**: Run PHPStan after each batch
4. **Document Learnings**: Record unexpected cascades

### Batch Execution Plan

#### Batch 1: Method Signature Completions (2-3 hours)

**Target**: ~50 errors

**Example**:
```php
// Error: Project::ownedByCurrentTeam() invoked with 1 parameter, 0 expected

// Fix: Add parameter to match calling convention
/**
 * @param array<int, string> $select
 * @return \Illuminate\Database\Eloquent\Builder<Project>
 */
public static function ownedByCurrentTeam(array $select = ['*']): \Illuminate\Database\Eloquent\Builder
{
    $selectArray = collect($select)->concat(['id']);
    return Project::whereTeamId(currentTeam()->id)
        ->select($selectArray->all())
        ->orderByRaw('LOWER(name)');
}
```

**Verification**:
```bash
# After each fix
docker exec coolify phpstan analyze app/Models/Project.php
# Should show: ✅ Errors reduced
```

**Expected Reduction**: ~50 errors

---

#### Batch 2: PHPDoc Annotations (4-6 hours)

**Target**: ~80 errors

**Pattern 1: Array Parameter Types**
```php
// Error: Parameter $select has no value type specified

// Fix: Add @param annotation
/**
 * @param array<int, string> $select
 */
public static function ownedAndOnlySShKeys(array $select = ['*'])
```

**Pattern 2: Return Type Documentation**
```php
// Error: Method applications() has no return type specified

// Fix: Add return type
/**
 * @return \Illuminate\Database\Eloquent\Relations\MorphToMany<Application>
 */
public function applications()
{
    return $this->morphedByMany(Application::class, 'taggable');
}
```

**Batch Strategy**:
1. Fix all methods in one model file
2. Verify that file with PHPStan
3. Move to next model
4. Track cumulative error reduction

**Expected Reduction**: ~80 errors

---

#### Batch 3: Relationship Return Types (3-4 hours)

**Target**: ~40 errors

**Common Patterns**:

**BelongsTo**:
```php
/**
 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Team, $this>
 */
public function team()
{
    return $this->belongsTo(Team::class);
}
```

**HasMany**:
```php
/**
 * @return \Illuminate\Database\Eloquent\Relations\HasMany<Application>
 */
public function applications()
{
    return $this->hasMany(Application::class);
}
```

**MorphMany**:
```php
/**
 * @return \Illuminate\Database\Eloquent\Relations\MorphToMany<Tag>
 */
public function tags()
{
    return $this->morphToMany(Tag::class, 'taggable');
}
```

**Strategy**:
- Group by relationship type
- Apply pattern consistently
- Verify incrementally

**Expected Reduction**: ~40 errors

---

#### Batch 4: Complex Type Issues (3-6 hours)

**Target**: ~33 errors

**These require case-by-case analysis**:

**Example Issues**:
1. Generic type mismatches (`Builder<Application>` vs `Builder<static>`)
2. Collection type specifications
3. Conditional return types
4. Complex array structures

**Approach**:
1. Analyze each error individually
2. Research Laravel/PHPStan best practices
3. Apply most conservative fix
4. Document rationale for complex decisions

**Expected Reduction**: ~33 errors

---

## Cascade Prevention Strategies

### 1. Incremental Verification

**After Every Batch**:
```bash
# Run PHPStan
docker exec coolify sh -c "cd /var/www/html && \
  ./vendor/bin/phpstan analyze --memory-limit=2G" | tee phpstan-batch-N.txt

# Check error delta
prev_errors=$(grep "Found.*errors" phpstan-batch-$((N-1)).txt | grep -oP '\d+')
curr_errors=$(grep "Found.*errors" phpstan-batch-N.txt | grep -oP '\d+')
delta=$((curr_errors - prev_errors))

if [ $delta -gt 0 ]; then
  echo "⚠️  WARNING: Errors increased by $delta"
  echo "Review changes before proceeding"
fi
```

### 2. Pattern Validation

**Before Mass-Applying a Pattern**:
1. Test on 1 file
2. Verify PHPStan result
3. Check for new cascades
4. Only then apply to remaining files

### 3. Rollback Checkpoints

**Git Strategy**:
```bash
# Create checkpoint before each batch
git add -A
git commit -m "session-3: checkpoint before batch N"

# If cascade amplifies, easy rollback
git reset --hard HEAD^
```

### 4. Documentation of Surprises

**When Unexpected Cascades Occur**:
- Document the pattern
- Analyze why it happened
- Adjust strategy
- Update this plan

---

## Success Metrics

### Primary Goal
**Reduce errors from 6,697 to below 6,500** (197+ error reduction)

### Batch-Level Goals

| Batch | Target Errors | Expected Reduction | Risk Level |
|-------|--------------|-------------------|------------|
| 1 | 50 | -50 | 🟢 LOW |
| 2 | 80 | -80 | 🟢 LOW |
| 3 | 40 | -40 | 🟡 MEDIUM |
| 4 | 33 | -33 | 🟡 MEDIUM |
| **Total** | **203** | **-203** | - |

### Quality Metrics

- ✅ **Zero Runtime Regressions**: All tests pass
- ✅ **Zero Breaking Changes**: Existing code continues to work
- ✅ **Comprehensive Documentation**: Every fix justified
- ✅ **Pattern Establishment**: Reusable patterns for future work

---

## Verification & Testing

### Automated Checks

**After Each Batch**:
```bash
# 1. PHP Syntax Check
find app -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"

# 2. PHPStan Analysis
docker exec coolify phpstan analyze --memory-limit=2G

# 3. Linting
docker exec coolify ./vendor/bin/pint --test

# 4. Unit Tests (if applicable)
docker exec coolify ./vendor/bin/pest tests/Unit
```

### Manual Review Checklist

- [ ] Error count decreased (not increased)
- [ ] No new error categories introduced
- [ ] Changes follow established patterns
- [ ] All fixes documented in commit message
- [ ] Risk assessment updated

---

## Contingency Plans

### If Errors Increase Instead of Decrease

**Assessment**:
1. Analyze which new errors appeared
2. Determine if they're "good" cascades (finding bugs) or "bad" cascades (mistakes)
3. Decide: fix forward or rollback

**Decision Matrix**:
- New errors reveal real bugs → Continue, document findings
- New errors are pattern mistakes → Rollback, revise approach
- New errors are framework limitations → Add to PHPStan baseline

### If Batch Takes Longer Than Estimated

**Options**:
1. **Split Batch**: Break into smaller sub-batches
2. **Skip Complex Cases**: Move difficult errors to separate batch
3. **Pause for Research**: Some errors may need Laravel/PHPStan research

### If Unexpected Breaking Changes Occur

**Immediate Actions**:
1. Rollback to last checkpoint
2. Analyze what broke and why
3. Design safer approach
4. Test in isolation before reapplying

---

## Timeline Estimate

### Conservative Estimate

| Phase | Duration | Cumulative |
|-------|----------|------------|
| Investigation | 2 hours | 2 hours |
| Batch 1 | 3 hours | 5 hours |
| Batch 2 | 6 hours | 11 hours |
| Batch 3 | 4 hours | 15 hours |
| Batch 4 | 6 hours | 21 hours |
| Testing & Documentation | 2 hours | 23 hours |
| **Total** | **23 hours** | - |

### Optimistic Estimate

| Phase | Duration | Cumulative |
|-------|----------|------------|
| Investigation | 1 hour | 1 hour |
| Batch 1 | 2 hours | 3 hours |
| Batch 2 | 4 hours | 7 hours |
| Batch 3 | 3 hours | 10 hours |
| Batch 4 | 3 hours | 13 hours |
| Testing & Documentation | 1 hour | 14 hours |
| **Total** | **14 hours** | - |

**Realistic Range**: 14-23 hours over multiple sessions

---

## Deliverables

### During Session 3

1. **Error Catalog** (`session-3-error-catalog.md`)
2. **Dependency Graph** (text format)
3. **Batch Completion Reports** (after each batch)
4. **Final Summary** (`session-3-completion-summary.md`)

### Git Commits

**Structure**:
```
session-3: batch-1 - method signature completions (50 errors fixed)
session-3: batch-2 - phpdoc annotations (80 errors fixed)
session-3: batch-3 - relationship return types (40 errors fixed)
session-3: batch-4 - complex type issues (33 errors fixed)
session-3: final - verification and documentation
```

---

## Post-Session 3 Outlook

### If Successful (197+ errors reduced)

**Next Steps**:
- Session 4: Address remaining high-priority errors
- Session 5: Establish PHPStan in CI/CD
- Long-term: Maintain error count below 6,500

### If Partial Success (100-196 errors reduced)

**Options**:
- Session 3B: Continue with remaining errors
- Reassess approach for difficult categories
- Consider PHPStan baseline for edge cases

### If Minimal Progress (<100 errors reduced)

**Analysis Needed**:
- Review methodology
- Identify blocking issues
- May need architectural changes
- Consult Laravel/PHPStan community

---

## References

- **Session 2 Summary**: [session-2-completion-summary.md](./session-2-completion-summary.md)
- **Session 2 Justifications**: [session-2-fix-justification.md](./session-2-fix-justification.md)
- **PHPStan Documentation**: https://phpstan.org/
- **Laravel Type Hints**: https://laravel.com/docs/11.x/eloquent-relationships
- **PHP Generics (PHPDoc)**: https://phpstan.org/blog/generics-in-php-using-phpdocs

---

**Status**: 📋 Ready for Execution
**Risk Level**: 🟡 MEDIUM (managed through incremental approach)
**Expected Outcome**: 197+ error reduction, comprehensive type safety

---

**Generated**: November 27, 2025
**Author**: AI Assistant (Claude Sonnet 4.5)
