<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailInboxAttachment extends Model
{
    protected $fillable = [
        'email_inbox_message_id',
        'graph_attachment_id',
        'filename',
        'content_type',
        'size_bytes',
        'storage_path',
    ];

    /**
     * @return BelongsTo<EmailInboxMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(EmailInboxMessage::class, 'email_inbox_message_id');
    }
}
