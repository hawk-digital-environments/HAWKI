<?php

use App\Models\Message;
use Illuminate\Database\Migrations\Migration;
use Symfony\Component\Console\Output\ConsoleOutput;

return new class extends Migration {
    public function up(): void
    {
        // Iterate all messages in chunks of 1000
        // Extract the message_id, if it does not end in .000 we assume it is a threaded message
        // If a message is threaded, replace the numbers after the dot with 0, this is the message_id of the parent.
        // Find the message with the generated message_id, extract the "id" and set it as the thread_id of the current message.
        // If the thread_id is already set, skip it.
        // If the message_id ends with .000, we assume it is not a threaded message and set the thread_id to null.

        $chunkSize = 1000;
        $messages = Message::query();

        $io = new ConsoleOutput();

        $messages->chunk($chunkSize, function ($chunk) use ($io) {
            $io->writeln(sprintf(
                'Generating thread_id chunk of %d messages',
                $chunk->count()
            ));

            foreach ($chunk as $message) {
                if ($message->thread_id !== null) {
                    // Skip if thread_id is already set
                    continue;
                }

                $parts = explode('.', $message->message_id);
                if (count($parts) !== 2) {
                    $io->writeln(sprintf(
                        'Invalid message ID format: %s. Expected format is "threadId.threadMessageId". Skipping message.',
                        $message->message_id
                    ));
                    continue;
                }

                $threadId = (int)$parts[0];
                $threadMessageId = (int)$parts[1];

                if ($threadMessageId === 0) {
                    // Not a threaded message
                    $message->thread_id = null;
                } else {
                    // Find the parent message by its ID
                    $parentMessage = Message::where('message_id', $threadId . '.000')->first();
                    if ($parentMessage) {
                        $message->thread_id = $parentMessage->id;
                    } else {
                        $io->writeln(sprintf(
                            'Parent message not found for thread ID: %d in message ID: %s. Skipping message.',
                            $threadId,
                            $message->message_id
                        ));
                        continue;
                    }
                }

                $message->save();
            }
        });
    }

    public function down(): void
    {
        Message::query()->update(['thread_id' => null]);
    }
};
