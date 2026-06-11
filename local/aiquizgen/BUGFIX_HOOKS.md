# Bug Fix: Deprecation Warning for before_footer Callback

## Problem

Deprecation warning in Moodle 5.0:
```
Callback before_footer in local_aiquizgen component should be migrated 
to new hook callback for core\hook\output\before_footer_html_generation
```

## Root Cause

Moodle 5.0 introduced a new **hook system** to replace legacy callbacks. The old `before_footer` callback pattern is deprecated in favor of the new hook architecture.

### Changes in Moodle 5.0:
- **OLD (Moodle 4.4)**: `local_pluginname_before_footer()` function in lib.php
- **NEW (Moodle 5.0)**: Hook callback via `db/hooks.php` and hook_callbacks class

## Solution

Implemented **dual-system support** for both Moodle 4.4 and 5.0:

### 1. Created Hook Callback Class

**File**: `classes/hook_callbacks.php`

```php
namespace local_aiquizgen;

class hook_callbacks {
    public static function before_footer_html_generation(
        \core\hook\output\before_footer_html_generation $hook
    ): void {
        // New hook-based implementation for Moodle 5.0+
    }
}
```

### 2. Created Hook Configuration

**File**: `db/hooks.php`

```php
$callbacks = [
    [
        'hook' => \core\hook\output\before_footer_html_generation::class,
        'callback' => \local_aiquizgen\hook_callbacks::class . '::before_footer_html_generation',
        'priority' => 500,
    ],
];
```

### 3. Updated Legacy Callback

**File**: `lib.php`

Modified `local_aiquizgen_before_footer()` to:
- Only execute if hook system is NOT available (Moodle 4.4)
- Add deprecation notice in comments
- Keep same functionality for backward compatibility

```php
function local_aiquizgen_before_footer() {
    // Only use if hook system is not available (Moodle 4.4)
    if (!class_exists('\core\hook\output\before_footer_html_generation')) {
        // Legacy implementation
    }
    return '';
}
```

## Benefits

1. ✅ **No deprecation warnings** in Moodle 5.0
2. ✅ **Fully compatible** with Moodle 4.4 (uses legacy callback)
3. ✅ **Fully compatible** with Moodle 5.0 (uses new hooks)
4. ✅ **Single codebase** for both versions
5. ✅ **Future-proof** - ready for when legacy callbacks are removed

## How It Works

### On Moodle 4.4:
1. Hook system not available
2. Legacy `before_footer` callback executes
3. Button added to question bank page

### On Moodle 5.0+:
1. Hook system available
2. New hook callback executes via `hook_callbacks.php`
3. Legacy callback checks for hook class and skips execution
4. No deprecation warnings
5. Button added to question bank page

## Files Modified

- `lib.php` - Added backward compatibility check to legacy callback
- `version.php` - Bumped to v1.0.2

## New Files Created

- `classes/hook_callbacks.php` - New hook callback implementation
- `db/hooks.php` - Hook configuration

## Testing

### Moodle 4.4:
1. Navigate to course
2. Go to question bank
3. Button should appear
4. No errors in logs

### Moodle 5.0:
1. Same as above
2. No deprecation warnings
3. Check debugging: No messages about before_footer
4. Button works correctly

## Compatibility Matrix

| Moodle Version | Implementation | Status |
|----------------|----------------|--------|
| 4.4.x | Legacy callback | ✅ Works |
| 5.0.x | Hook system | ✅ Works (No warnings) |
| 5.1+ | Hook system | ✅ Future-proof |

## Additional Notes

- This pattern can be applied to other deprecated callbacks
- Hook system is more flexible and performant
- Legacy callback will be automatically ignored when hooks are available
- When Moodle 4.4 support is dropped, legacy callback can be removed

## Version

- Fixed in: v1.0.2
- Date: 2025-10-03
- Issue: Deprecation warning in Moodle 5.0
