# Session 3: Quick Reference Guide

**Quick access to key information for executing the PHPStan error resolution plan**

---

## At a Glance

- **Total Errors**: 6,349 in 539 files
- **Expected Reduction**: 87% (down to ~849)
- **Total Time**: 17-24 hours
- **Phases**: 4 phases (Foundation → Type Safety → Safety/Fixes → Verification)

---

## Priority Files to Fix

### Top 10 Models (Fix First)

1. `app/Models/Server.php` - 52 property errors
2. `app/Models/Application.php` - 45 property errors
3. `app/Models/User.php` - 284 references
4. `app/Models/EnvironmentVariable.php` - 16 property errors
5. `app/Models/LocalFileVolume.php` - 15 property errors
6. `app/Models/StandaloneRedis.php` - 10 property errors
7. `app/Models/ApplicationPreview.php` - 9 property errors
8. `app/Models/Service.php` - 110 references
9. `app/Models/Team.php` - 106 references
10. `app/Models/Organization.php` - 80 references

---

## Phase Checklist

### Phase 1: Foundation (4-6h) - 🟢 LOW RISK
- [ ] Add @property annotations to 55 models
- [ ] Add Collection<Type> generic specs
- [ ] Add @param array<type> annotations
- [ ] Simplify logic conditions
- [ ] Expected: Fix 2,044 errors
- [ ] Run tests after completion

### Phase 2: Type Safety (5-7h) - 🟡 MEDIUM RISK
- [ ] Add return types to Actions/Services (2,000+ methods)
- [ ] Add parameter type hints (385 locations)
- [ ] Fix undefined variables (92 locations)
- [ ] Expected: Fix 2,586 errors
- [ ] Run comprehensive tests

### Phase 3: Safety & Fixes (6-8h) - 🔴 HIGH RISK
- [ ] Add null checks (395 locations)
- [ ] Use null-safe operators (?->)
- [ ] Fix type mismatches (475 locations)
- [ ] Expected: Fix 870 errors
- [ ] CRITICAL: Test all code paths

### Phase 4: Verification (2-3h)
- [ ] Run PHPStan analysis
- [ ] Execute full test suite
- [ ] Check performance
- [ ] Update documentation
- [ ] Create completion summary

---

## Error Categories

| Category | Count | Risk | Priority |
|----------|-------|------|----------|
| Missing return types | 2,109 | 🟡 MED | HIGH |
| Property annotations | 1,119 | 🟢 LOW | HIGH |
| Type mismatches | 475 | 🔴 HIGH | MED |
| Iterable types | 414 | 🟢 LOW | MED |
| Collection generics | 441 | 🟢 LOW | MED |
| Null safety | 395 | 🔴 HIGH | HIGH |
| Parameter types | 385 | 🟡 MED | MED |
| Property types | 381 | 🟢 LOW | LOW |
| Undefined variables | 92 | 🟡 MED | HIGH |
| Logic issues | 70 | 🟢 LOW | LOW |

---

## Common Fixes

### Model Property Annotations

```php
/**
 * @property-read int $id
 * @property string $name
 * @property \App\Models\Team $team
 * @property \Illuminate\Support\Collection<int, \App\Models\Server> $servers
 */
class MyModel extends Model
{
    // ...
}
```

### Return Type Declarations

```php
// Before
public function handle()
{
    return $result;
}

// After
public function handle(): bool
{
    return $result;
}
```

### Parameter Type Hints

```php
// Before
public function process($data)
{
    // ...
}

// After
public function process(array $data): void
{
    // ...
}
```

### Null Safety

```php
// Before
$user = auth()->user();
$team = $user->currentTeam();  // Can crash if user is null

// After - Option 1: Null-safe operator
$team = auth()->user()?->currentTeam();

// After - Option 2: Early exit
$user = auth()->user();
if (!$user) {
    abort(403);
}
$team = $user->currentTeam();
```

### Collection Generics

```php
// Before
/** @var Collection */
private $items;

// After
/** @var Collection<int, \App\Models\Item> */
private Collection $items;
```

### Array Type Specs

```php
// Before
/** @param array $options */
public function configure(array $options)

// After
/** @param array<string, mixed> $options */
public function configure(array $options): void
```

---

## Testing Commands

```bash
# Run PHPStan
./vendor/bin/phpstan analyze --memory-limit=2G

# Run tests
./vendor/bin/pest

# Run specific test suite
./vendor/bin/pest tests/Feature
./vendor/bin/pest tests/Unit

# Check code style
./vendor/bin/pint --test
```

---

## Git Commands

```bash
# Create checkpoint after each phase
git add -A
git commit -m "session-3-phase-1: Foundation complete"
git push origin copilot/fix-phpstan-errors-analysis

# If need to rollback
git reset --hard HEAD~1  # Undo last commit
git revert HEAD  # Create revert commit
```

---

## When Things Go Wrong

### Tests Failing
1. Check what test is failing
2. Verify if test needs updating
3. If fix is wrong, rollback change
4. Re-analyze and apply different fix

### PHPStan Error Increases
1. This is expected initially
2. New type info reveals hidden issues
3. Continue with plan
4. Errors should decrease by end

### Performance Issues
1. Profile slow operations
2. Check for N+1 queries
3. Verify no unnecessary loops
4. Rollback if critical

---

## Resources

- **Full Analysis**: `docs/session-3-investigation-summary.md`
- **PHPStan Report**: `phpstan-report.json`
- **Error Summary**: `phpstan-summary.txt`
- **Session 1**: `docs/session-1-completion-summary.md`
- **Session 2**: `docs/session-2-completion-summary.md`

---

**Status**: Ready for Execution  
**Last Updated**: December 3, 2025
