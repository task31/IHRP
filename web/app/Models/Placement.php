<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Placement extends Model
{
    protected $fillable = [
        'consultant_id',
        'client_id',
        'placed_by',
        'job_title',
        'start_date',
        'end_date',
        'pay_rate',
        'bill_rate',
        'po_number',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'pay_rate' => 'decimal:4',
            'bill_rate' => 'decimal:4',
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
     * Account manager who created the placement; null if the user was removed.
     *
     * @return BelongsTo<User, $this>
     */
    public function placedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by', 'id');
    }
}
