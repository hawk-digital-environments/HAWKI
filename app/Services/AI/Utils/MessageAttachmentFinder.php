<?php
declare(strict_types=1);


namespace App\Services\AI\Utils;


use App\Models\Attachment;

readonly final class MessageAttachmentFinder
{
    /**
     * Helper for the AI converter classes to find all attachments referenced in the given messages.
     * @param array{content:array{attachments: []}[] $messages The raw messages as arrays.
     * @return array<string, Attachment>  Returns an array of Attachment models indexed by their UUIDs.
     */
    public function findAttachmentsOfMessages(array $messages): array
    {
        $uuids = collect($messages)
            ->pluck('content.attachments')
            ->filter()
            ->flatten()
            ->unique()
            ->all();
        
        if (empty($uuids)) {
            return [];
        }
        
        return Attachment::whereIn('uuid', $uuids)
            ->get()
            ->keyBy('uuid')
            ->all(); // returns [uuid => AttachmentModel]
    }
}
