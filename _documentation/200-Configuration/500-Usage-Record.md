> **This document is partially outdated.** `UsageAnalyzerService` is `@deprecated`.

## Overview

The UsageRecord model tracks usage of AI models in the HAWKI system, capturing token consumption for both prompts and completions.

## Model Definition

**File:** `/app/Models/Records/UsageRecord.php`

```js
class UsageRecord extends Model {
    protected
    $fillable = [
        'user_id',
        'room_id',
        'prompt_tokens',
        'completion_tokens',
        'model',
        'type',
    ];

    public function

    user() {
        return $this->belongsTo(User::class);
    }

    public function

    room() {
        return $this->belongsTo(Room::class);
    }
}
```

## Database Schema

**Migration:** `/database/migrations/2025_02_06_103418_create_usage_records_table.php`

The `usage_records` table consists of:

- `id` - Primary key
- `user_id` - Foreign key to users table (nullable on user deletion)
- `room_id` - Foreign key to rooms table (nullable on room deletion)
- `prompt_tokens` - Unsigned big integer tracking token count in prompts
- `completion_tokens` - Unsigned big integer tracking token count in completions
- `type` - Enum with values 'private', 'group' or 'api'
- `model` - String identifier for the AI model used
- Timestamps (`created_at`, `updated_at`)

## Purpose

The UsageRecord system enables:

- Tracking AI token consumption on a per-user basis
- Distinguishing between private and group usage
- Model-specific usage tracking
- Potential for billing or quota implementation
