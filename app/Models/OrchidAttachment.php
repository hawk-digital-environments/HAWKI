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
}
