# Session 2: ownedByCurrentTeam() Scope Methods Analysis

**Date**: November 27, 2025
**Issue**: [#203](https://github.com/johnproblems/topgun/issues/203)
**Focus**: Add return type hints to all `ownedByCurrentTeam()` scope methods

---

## Overview

PHPStan flagged 27+ `ownedByCurrentTeam()` methods across model files for missing return type specifications. These are **static scope methods** used for filtering Eloquent queries by the current team.

---

## Pattern Analysis

### Common Pattern

All `ownedByCurrentTeam()` methods follow this pattern:

```php
// BEFORE - Missing return type
public static function ownedByCurrentTeam()
{
    return ModelName::whereTeamId(currentTeam()->id)->orderBy('name');
}
```

### Return Type

These methods return `Illuminate\Database\Eloquent\Builder` instances, which allows chaining additional query methods:

```php
// Usage example
$servers = Server::ownedByCurrentTeam()->where('active', true)->get();
```

---

## Justification for Adding Return Types

### Why Add Return Types?

1. **PHPStan Compliance**: Eliminates "has no return type specified" errors
2. **IDE Support**: Enables autocomplete for chained methods
3. **Type Safety**: Prevents accidental incorrect return values
4. **Documentation**: Makes the code self-documenting
5. **Laravel Best Practice**: Modern Laravel code uses return type hints

### Why This Is Safe

1. **No Runtime Impact**: Return type hints in PHP 8.4 don't change behavior for correct code
2. **No Logic Change**: We're only adding type information, not changing implementation
3. **All Methods Return Builder**: Every `ownedByCurrentTeam()` method returns a query builder
4. **Backward Compatible**: Existing code calling these methods won't break

---

## Models to Fix (27 total)

### Group 1: Core Resource Models (6)
1. `Application::ownedByCurrentTeam()` - Line 341
2. `Application::ownedByCurrentTeamAPI()` - Line 336
3. `Server::ownedByCurrentTeam()` - Line 257
4. `Service::ownedByCurrentTeam()` - Line 156
5. `PrivateKey::ownedByCurrentTeam()` - Line 83
6. `Environment::ownedByCurrentTeam()` - Line 38

### Group 2: Project & Team Models (4)
7. `Project::ownedByCurrentTeam()` - Line 33
8. `TeamInvitation::ownedByCurrentTeam()` - Line 31
9. `Tag::ownedByCurrentTeam()` - Line 18
10. `CloudInitScript::ownedByCurrentTeam()` - Line 27

### Group 3: Integration Models (3)
11. `GithubApp::ownedByCurrentTeam()` - Line 48
12. `GitlabApp::ownedByCurrentTeam()` - Line 12
13. `CloudProviderToken::ownedByCurrentTeam()` - Line 30

### Group 4: Storage Models (2)
14. `S3Storage::ownedByCurrentTeam()` - Line 22

### Group 5: Service Components (4)
15. `ServiceApplication::ownedByCurrentTeam()` - Line 40
16. `ServiceApplication::ownedByCurrentTeamAPI()` - Line 35
17. `ServiceDatabase::ownedByCurrentTeam()` - Line 33
18. `ServiceDatabase::ownedByCurrentTeamAPI()` - Line 28

### Group 6: Standalone Databases (8)
19. `StandaloneClickhouse::ownedByCurrentTeam()` - Line 47
20. `StandaloneDragonfly::ownedByCurrentTeam()` - Line 47
21. `StandaloneKeydb::ownedByCurrentTeam()` - Line 47
22. `StandaloneMariadb::ownedByCurrentTeam()` - Line 48
23. `StandaloneMongodb::ownedByCurrentTeam()` - Line 50
24. `StandaloneMysql::ownedByCurrentTeam()` - Line 48
25. `StandalonePostgresql::ownedByCurrentTeam()` - Line 48
26. `StandaloneRedis::ownedByCurrentTeam()` - Line 49

### Additional Controller Method
27. `TeamController::current_team()` - Line 191 (returns JsonResponse)

---

## Fix Template

```php
// BEFORE
public static function ownedByCurrentTeam()
{
    return ModelName::whereTeamId(currentTeam()->id)->orderBy('name');
}

// AFTER
public static function ownedByCurrentTeam(): \Illuminate\Database\Eloquent\Builder
{
    return ModelName::whereTeamId(currentTeam()->id)->orderBy('name');
}
```

---

## Verification Plan

For each fix:
1. ✅ Confirm method returns Eloquent Builder
2. ✅ Add return type: `\Illuminate\Database\Eloquent\Builder`
3. ✅ Run PHPStan to verify error eliminated
4. ✅ Check no new errors introduced

---

## Expected Impact

- **Errors Fixed**: 27 errors
- **Runtime Risk**: ZERO (type hints don't change behavior)
- **Code Quality**: IMPROVED (better type safety and documentation)
- **PHPStan Score**: 6672 → ~6645 (27 error reduction)

---

## Notes

- Some models have both `ownedByCurrentTeam()` and `ownedByCurrentTeamAPI($teamId)` variants
- The API variants take teamId as parameter instead of calling `currentTeam()`
- All return the same type: `Illuminate\Database\Eloquent\Builder`
- TeamController::current_team() is different - it returns `JsonResponse`

---

**Status**: Ready for implementation
**Risk Level**: MINIMAL (type hints only)
**Expected Duration**: 15-20 minutes for all 27 fixes
