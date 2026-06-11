# Bug Fix: Type Error in question_category_options()

## Problem

Type error when loading generate form:
```
Exception - qbank_managecategories\helper::question_category_options(): 
Argument #1 ($contexts) must be of type array, 
core_question\local\bank\question_edit_contexts given
```

## Root Cause

The `question_category_options()` function expects an **array of context objects**, but we were passing a `question_edit_contexts` object directly.

### Code Flow:
1. `generate.php` creates: `$contexts = new \core_question\local\bank\question_edit_contexts($context)`
2. Passes object to form: `$customdata = ['contexts' => $contexts]`
3. Form tries to use it: `question_category_options($contexts)` ❌ **Type mismatch!**

### Expected vs Actual:

```php
// Expected (array of contexts):
$contexts = [\context_course::instance(1), \context_system::instance()];

// Actual (object):
$contexts = new \core_question\local\bank\question_edit_contexts($context);
```

## Solution

Extract the array of contexts from the `question_edit_contexts` object using its `all()` method:

### Before (Broken):
```php
if (isset($customdata['contexts'])) {
    $contexts = $customdata['contexts']; // This is an object, not array!
    $categories = \qbank_managecategories\helper::question_category_options($contexts);
    // ❌ TypeError: Argument must be array
}
```

### After (Fixed):
```php
if (isset($customdata['contexts'])) {
    $contextsobj = $customdata['contexts'];
    
    // Extract array from object
    if (is_object($contextsobj) && method_exists($contextsobj, 'all')) {
        $contexts = $contextsobj->all(); // Returns array of context objects
    } else {
        $contexts = $contextsobj; // Already an array
    }
    
    $categories = \qbank_managecategories\helper::question_category_options($contexts);
    // ✅ Works! $contexts is now an array
}
```

## Benefits

1. ✅ **Type safe** - Always passes correct type (array) to helper
2. ✅ **Flexible** - Works if passed object or array
3. ✅ **Defensive** - Checks object type and method existence
4. ✅ **Future-proof** - Handles both coding patterns

## How question_edit_contexts Works

The `question_edit_contexts` class is a wrapper around context arrays:

```php
class question_edit_contexts {
    protected $allcontexts; // Array of context objects
    
    public function __construct(\context $thiscontext) {
        $this->allcontexts = [$thiscontext];
    }
    
    // Returns the array of contexts
    public function all() {
        return $this->allcontexts;
    }
}
```

We need to call `all()` to get the actual array.

## Testing

### Test Case 1: Standard Flow
```php
$context = context_course::instance(1);
$contextsobj = new \core_question\local\bank\question_edit_contexts($context);
$contexts = $contextsobj->all(); // Returns array

// Result: Array ( [0] => context_course Object )
```

### Test Case 2: Form Processing
1. Navigate to course
2. Click "AI Quiz Generator"
3. Form loads ✅ (no error)
4. Category dropdown populated ✅
5. Can generate questions ✅

## Files Modified

- `classes/form/generate_form.php` - Added context extraction logic
- `version.php` - Bumped to v1.0.3

## Version

- Fixed in: v1.0.3
- Date: 2025-10-03
- Error: TypeError on form load

## Related Documentation

- See also: `BUGFIX_COMPATIBILITY.md` for namespace compatibility fix
- See also: `BUGFIX_HOOKS.md` for hook system migration
