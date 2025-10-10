<?php

namespace App\Models;

use Orchid\Attachment\Models\Attachment as OrchidBaseAttachment;

class OrchidAttachment extends OrchidBaseAttachment
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'orchid_attachments';

    /**
     * Override the attachmentable relationship to use our custom pivot table.
     */
    public function attachmentable()
    {
        return $this->morphToMany(
            'Orchid\Attachment\Attachable',
            'attachmentable',
            'orchid_attachmentable',
            'attachment_id',
            'attachmentable_id'
        );
    }

    /**
     * Override delete to use the correct pivot table
     */
    public function delete()
    {
        try {
            // Delete from orchid_attachmentable pivot table first
            \DB::table('orchid_attachmentable')
                ->where('attachment_id', $this->id)
                ->delete();
        } catch (\Exception $e) {
            \Log::warning("Could not delete from orchid_attachmentable for attachment {$this->id}: {$e->getMessage()}");
        }

        // Delete the physical file
        try {
            $disk = $this->disk ?? config('platform.attachment.disk', 'public');
            $filePath = $this->path . $this->name . '.' . $this->extension;
            
            if (\Storage::disk($disk)->exists($filePath)) {
                \Storage::disk($disk)->delete($filePath);
            }
        } catch (\Exception $e) {
            \Log::warning("Could not delete physical file for attachment {$this->id}: {$e->getMessage()}");
        }

        // Delete the attachment record itself
        return \DB::table($this->table)->where('id', $this->id)->delete();
    }
}
