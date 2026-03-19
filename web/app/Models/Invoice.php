<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'invoice_date',
        'due_date',
        'consultant_id',
        'client_id',
        'timesheet_id',
        'bill_to_name',
        'bill_to_contact',
        'bill_to_address',
        'payment_terms',
        'po_number',
        'notes',
        'subtotal',
        'total_amount_due',
        'status',
        'sent_date',
        'paid_date',
        'pdf_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'sent_date' => 'date',
            'paid_date' => 'date',
            'subtotal' => 'decimal:4',
            'total_amount_due' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Consultant, $this>
     */
    public function consultant(): BelongsTo
    {
        return $this->belongsTo(Consultant::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Timesheet, $this>
     */
    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }

    /**
     * @return HasMany<InvoiceLineItem, $this>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class)->orderBy('sort_order');
    }
}
