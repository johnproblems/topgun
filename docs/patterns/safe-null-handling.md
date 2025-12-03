# Safe Null Handling Patterns in Topgun

## Problem Statement

When a method can return null (e.g., `auth()->user()?->currentTeam()`), we must not assign it to a non-nullable typed property without verification.

**The Critical Issue**: Assigning `null` to a non-nullable typed property throws a `TypeError` immediately at assignment time, **before** any guard checks can execute.

## Pattern 1: Nullable Property (Recommended for Services/Notifications)

**Use when**: Component MAY operate without the resource

```php
class Discord {
    private ?Team $team = null;  // Explicitly nullable

    public function mount()
    {
        $user = auth()->user();
        $this->team = $user?->currentTeam();  // Safe assignment
        
        if (! $this->team) {
            return handleError(new \Exception('Team not found.'), $this);  // Now reachable
        }
        
        // Safe to use $this->team - guaranteed non-null from here
    }
}
```

**Pros:**
- Clear intent (property CAN be null)
- Safe at assignment time
- Easy to understand
- Allows graceful degradation

**Cons:**
- Every usage needs null check
- Slightly more verbose

## Pattern 2: Guaranteed Injection (Recommended for Controllers/Components)

**Use when**: Component ALWAYS needs the resource

```php
class MyController {
    private Team $team;

    public function __construct(Team $team)
    {
        $this->team = $team;  // Guaranteed non-null
    }
    
    public function process()
    {
        // No null checks needed - $this->team is guaranteed non-null
        echo $this->team->name;
    }
}

// Called as:
$team = currentTeam();
if (! $team) {
    abort(403, 'No team assigned');
}
new MyController($team);  // Only called if team exists
```

**Pros:**
- Compiler/IDE can verify
- No null checks needed after construction
- Clear contract
- Type-safe

**Cons:**
- Requires caller to verify
- Can't use in lazy contexts
- Less flexible

## Pattern 3: Early Exit (Recommended for Middleware)

**Use when**: Component should fail fast if resource missing

```php
public function process(Request $request)
{
    $team = currentTeam();
    if (! $team) {
        abort(403, 'No team assigned');  // Exit before using
    }
    
    // Safe to use $team - now guaranteed non-null in this scope
    echo $team->name;
}
```

**Pros:**
- Simple and clear
- No additional properties
- Safe scope
- Fail-fast behavior

**Cons:**
- Only works for methods, not properties
- Can't share across methods easily

## Choosing Your Pattern

### Decision Tree

```
Does component ALWAYS need the resource?
├─ YES → Use Pattern 2 (Guaranteed Injection)
└─ NO → Does component use resource in constructor/mount?
    ├─ YES → Use Pattern 1 (Nullable Property)
    └─ NO → Use Pattern 3 (Early Exit in methods)
```

### Component Types

#### Notification Components (Discord, Slack, etc.)
✅ **Pattern 1 (Nullable Property)**
- **Reason**: Don't always have team, graceful degradation OK
- **Example**: `app/Livewire/Notifications/Discord.php`

#### Controllers (TeamController, ApplicationController, etc.)
✅ **Pattern 2 or 3**
- **Pattern 2** if always needed
- **Pattern 3** if checking existence
- **Reason**: Clear failure modes, type safety

#### Middleware
✅ **Pattern 3 (Early Exit)**
- **Reason**: Fail fast on missing resource
- **Example**: Check auth, abort if missing

#### Livewire Components
✅ **Pattern 2 or 1**
- Depends on component's role
- Use Pattern 1 if team might not exist
- Use Pattern 2 if team is always required

## Anti-Patterns (DO NOT DO THIS)

### ❌ WRONG: Non-nullable property + null assignment

```php
private Team $team;  // Says "must be Team"

public function __construct() {
    $this->team = auth()->user()?->currentTeam();  // TypeError!
    
    if (! $this->team) {  // UNREACHABLE - TypeError already thrown
        throw new Exception('Team not found');
    }
}
```

**Why it's wrong**: The TypeError is thrown at the assignment line, before the guard check can execute.

### ❌ WRONG: Using team without checks

```php
$team = auth()->user()?->currentTeam();  // Can be null
echo $team->name;  // TypeError if null - no safety
```

**Why it's wrong**: No null check before dereferencing.

### ❌ WRONG: Assuming middleware guarantees

```php
public function test(Request $request) {
    $team = currentTeam();  // Can return null
    echo $team->id;  // No check - risky
}
```

**Why it's wrong**: Middleware might not always guarantee the resource exists.

## Testing Null Paths

When writing tests, verify both null and non-null cases:

```php
// Test with no team
Auth::logout();
$notification = new Discord();
$notification->mount();  // Should handle gracefully

// Test with team
Auth::loginAs($user);
$notification = new Discord();
$notification->mount();  // Should work normally
```

## Real-World Examples from This Codebase

### Fixed: Discord Notification Component

**Before (Broken)**:
```php
public Team $team;  // Non-nullable

public function mount()
{
    $user = auth()->user();
    $this->team = $user?->currentTeam();  // TypeError if null!
    
    if (! $this->team) {  // UNREACHABLE
        return handleError(new \Exception('Team not found.'), $this);
    }
}
```

**After (Fixed with Pattern 1)**:
```php
public ?Team $team = null;  // Nullable

public function mount()
{
    $user = auth()->user();
    $this->team = $user?->currentTeam();  // Safe assignment
    
    if (! $this->team) {  // NOW REACHABLE
        return handleError(new \Exception('Team not found.'), $this);
    }
}
```

**Impact**: Prevents TypeError exceptions, allows graceful error handling.

## Further Reading

- **PHP Nullsafe operator**: https://www.php.net/manual/en/language.oop5.basic.php#language.oop5.basic.nullsafe
- **PHPStan Type System**: https://phpstan.org/writing-php-code/phpdocs-basics
- **Coolify CLAUDE.md**: See "Type Safety" section
- **Laravel Type Hints**: https://laravel.com/docs/11.x/container#method-invocation-and-injection

## Summary

| Pattern | Use Case | Property Type | Assignment Safety | Guard Check Works |
|---------|----------|---------------|-------------------|-------------------|
| 1. Nullable Property | May not have resource | `?Team` | ✅ Safe | ✅ Works |
| 2. Guaranteed Injection | Always has resource | `Team` | ✅ Safe | N/A (not needed) |
| 3. Early Exit | Check before use | N/A (local var) | ✅ Safe | ✅ Works |
| ❌ Anti-pattern | N/A | `Team` + null assign | ❌ TypeError | ❌ Unreachable |

## Phase 0 Fixes Applied

The following 6 notification components were fixed using Pattern 1:

1. ✅ `app/Livewire/Notifications/Discord.php`
2. ✅ `app/Livewire/Notifications/Pushover.php`
3. ✅ `app/Livewire/Notifications/Slack.php`
4. ✅ `app/Livewire/Notifications/Telegram.php`
5. ✅ `app/Livewire/Notifications/Webhook.php`
6. ✅ `app/Livewire/Notifications/Email.php`

All components now use `public ?Team $team = null` instead of `public Team $team`, preventing TypeError exceptions while maintaining type safety.
