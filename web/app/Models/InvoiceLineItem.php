<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLineItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'invoice_id',
        'week_number',
        'description',
        'hours',
        'rate',
        'multiplier',
        'amount',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
            'rate' => 'decimal:4',
            'multiplier' => 'decimal:2',
            'amount' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
