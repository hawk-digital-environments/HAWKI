---
sidebar_position: 9
---

## Overview
The UsageRecord model tracks usage of AI models in the HAWKI2 system, capturing token consumption for both prompts and completions.
  
  ## Model Definition
  **File:** `/app/Models/Records/UsageRecord.php`
  
```js
class UsageRecord extends Model
{
    protected $fillable = [
        'user_id',
        'room_id',
        'prompt_tokens',
        'completion_tokens',
        'model',
        'type',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function room()
    {
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
  
 ## Usage Recording Process
 
 ### When Records Are Created
 UsageRecords are created in the following scenarios:
 
 1. **Group Chat Interactions**
    In `StreamController::handleGroupChatRequest()`, records are created after a group AI response is generated:

    ```js
    $this->usageAnalyzer->submitUsageRecord($usage, 'group', $formattedPayload['model'], $room->id);
    ```
  
  2. **Private Chat Interactions**
     In `StreamController::createRequest()`, records are created for private AI conversations:
     ```js
     $this->usageAnalyzer->submitUsageRecord($usage, 'private', $formattedPayload['model']);
     ```
  
  3. **Streaming Responses**
  During streaming responses in `StreamController::createStream()`, usage records are submitted when usage data is available:
        ```js
        if($usage){
            $this->usageAnalyzer->submitUsageRecord($usage, 'private', $formattedPayload['model']);
        }
        ```
  
### Record Creation Logic
The `UsageAnalyzerService` handles the actual record creation through its `submitUsageRecord`
method:
  
```js
public function submitUsageRecord($usage, $type, $model, $roomId = null) {
    $userId = Auth::user()->id;

    UsageRecord::create([
        'user_id' => $userId,
        'room_id' => $roomId,
        'prompt_tokens' => $usage['prompt_tokens'],
        'completion_tokens' => $usage['completion_tokens'],
        'model' => $model,
        'type' => $type,
    ]);
}
```
  
## Data Maintenance
The `UsageAnalyzerService` includes a `summarizeAndCleanup` method that:

1. Summarizes usage records from the previous month, grouped by user, room, type, and model
2. Deletes the old records after summarization

This helps manage database size while preserving usage analytics data.
  
## Purpose
The UsageRecord system enables:
  - Tracking AI token consumption on a per-user basis
  - Distinguishing between private and group usage
  - Model-specific usage tracking
  - Potential for billing or quota implementation