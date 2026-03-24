<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailInboxMessage extends Model
{
    protected $fillable = [
        'graph_message_id',
        'internet_message_id',
        'mailbox_upn',
        'from_name',
        'from_email',
        'subject',
        'received_at',
        'has_attachments',
        'status',
        'body_preview',
        'body_plain',
        'body_html',
        'body_content_type',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'has_attachments' => 'boolean',
        ];
    }

    /**
     * @return HasMany<EmailInboxAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(EmailInboxAttachment::class);
    }
}
