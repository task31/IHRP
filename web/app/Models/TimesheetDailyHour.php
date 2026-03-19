<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimesheetDailyHour extends Model
{
    public $timestamps = false;

    protected $table = 'timesheet_daily_hours';

    protected $fillable = [
        'timesheet_id',
        'day_of_week',
        'week_number',
        'hours',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Timesheet, $this>
     */
    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }
}
