<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyCallReport extends Model
{
    protected $fillable = [
        'user_id',
        'report_date',
        'calls_made',
        'contacts_reached',
        'submittals',
        'interviews_scheduled',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'calls_made' => 'integer',
            'contacts_reached' => 'integer',
            'submittals' => 'integer',
            'interviews_scheduled' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
