<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AiConv;
use App\Models\AiConvMsg;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AiConvDeletionCascadeTest extends TestCase
{
    use DatabaseTransactions;

    public function testDatabaseCascadeDeletesMessagesAndAttachmentsWithConversation(): void
    {
        $user = User::factory()->create();
        $conversation = AiConv::query()->create([
            'conv_name' => 'Cascade test',
            'slug' => Str::slug(Str::random(16)),
            'user_id' => $user->id,
            'system_prompt' => null,
        ]);

        $uuids = [];

        foreach ([1, 2] as $index) {
            $message = AiConvMsg::query()->create([
                'conv_id' => $conversation->id,
                'user_id' => $user->id,
                'message_role' => 'user',
                'message_id' => (string) $index,
                'iv' => 'iv',
                'tag' => 'tag',
                'content' => 'encrypted',
                'completion' => true,
            ]);
            $uuid = (string) Str::uuid();
            $message->attachments()->create([
                'ai_conv_msg_id' => $message->id,
                'uuid' => $uuid,
                'name' => "cascade-{$index}.txt",
                'category' => 'private',
                'type' => 'document',
                'mime' => 'text/plain',
                'user_id' => $user->id,
            ]);
            $uuids[] = $uuid;
        }

        DB::table('ai_convs')->where('id', $conversation->id)->delete();

        $this->assertDatabaseMissing('ai_convs', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('ai_conv_msgs', ['conv_id' => $conversation->id]);

        foreach ($uuids as $uuid) {
            $this->assertDatabaseMissing('attachments', ['uuid' => $uuid]);
        }
    }
}
