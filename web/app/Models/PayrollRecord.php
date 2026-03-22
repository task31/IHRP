<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRecord extends Model
{
    protected $fillable = [
        'user_id',
        'check_date',
        'gross_pay',
        'net_pay',
        'federal_tax',
        'state_tax',
        'social_security',
        'medicare',
        'retirement_401k',
        'health_insurance',
        'other_deductions',
        'commission_subtotal',
        'salary_subtotal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'check_date' => 'date',
            'gross_pay' => 'decimal:4',
            'net_pay' => 'decimal:4',
            'federal_tax' => 'decimal:4',
            'state_tax' => 'decimal:4',
            'social_security' => 'decimal:4',
            'medicare' => 'decimal:4',
            'retirement_401k' => 'decimal:4',
            'health_insurance' => 'decimal:4',
            'other_deductions' => 'decimal:4',
            'commission_subtotal' => 'decimal:4',
            'salary_subtotal' => 'decimal:4',
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
}
