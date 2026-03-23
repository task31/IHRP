<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollConsultantEntry extends Model
{
    protected $fillable = [
        'user_id',
        'consultant_name',
        'year',
        'hours',
        'spread_per_hour',
        'commission_pct',
        'am_earnings',
        'revenue',
        'cost',
        'margin',
        'pct_of_total',
        'consultant_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year'            => 'integer',
            'hours'           => 'decimal:4',
            'spread_per_hour' => 'decimal:4',
            'commission_pct'  => 'decimal:8',
            'am_earnings'     => 'decimal:4',
            'revenue'         => 'decimal:4',
            'cost'            => 'decimal:4',
            'margin'          => 'decimal:4',
            'pct_of_total'    => 'decimal:4',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOwner($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Consultant, $this>
     */
    public function consultant(): BelongsTo
    {
        return $this->belongsTo(Consultant::class);
    }
}
