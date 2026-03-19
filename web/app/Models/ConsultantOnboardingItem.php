<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultantOnboardingItem extends Model
{
    protected $fillable = [
        'consultant_id',
        'item_key',
        'completed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Consultant, $this>
     */
    public function consultant(): BelongsTo
    {
        return $this->belongsTo(Consultant::class);
    }
}
