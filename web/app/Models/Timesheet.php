<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Timesheet extends Model
{
    protected $fillable = [
        'consultant_id',
        'client_id',
        'pay_period_start',
        'pay_period_end',
        'pay_rate_snapshot',
        'bill_rate_snapshot',
        'state_snapshot',
        'industry_type_snapshot',
        'ot_rule_applied',
        'week1_regular_hours',
        'week1_ot_hours',
        'week1_dt_hours',
        'week1_regular_pay',
        'week1_ot_pay',
        'week1_dt_pay',
        'week1_regular_billable',
        'week1_ot_billable',
        'week1_dt_billable',
        'week2_regular_hours',
        'week2_ot_hours',
        'week2_dt_hours',
        'week2_regular_pay',
        'week2_ot_pay',
        'week2_dt_pay',
        'week2_regular_billable',
        'week2_ot_billable',
        'week2_dt_billable',
        'total_regular_hours',
        'total_ot_hours',
        'total_dt_hours',
        'total_consultant_cost',
        'total_client_billable',
        'gross_revenue',
        'gross_margin_dollars',
        'gross_margin_percent',
        'invoice_id',
        'invoice_status',
        'source_file_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pay_period_start' => 'date',
            'pay_period_end' => 'date',
            'pay_rate_snapshot' => 'decimal:4',
            'bill_rate_snapshot' => 'decimal:4',
            'week1_regular_hours' => 'decimal:4',
            'week1_ot_hours' => 'decimal:4',
            'week1_dt_hours' => 'decimal:4',
            'week1_regular_pay' => 'decimal:4',
            'week1_ot_pay' => 'decimal:4',
            'week1_dt_pay' => 'decimal:4',
            'week1_regular_billable' => 'decimal:4',
            'week1_ot_billable' => 'decimal:4',
            'week1_dt_billable' => 'decimal:4',
            'week2_regular_hours' => 'decimal:4',
            'week2_ot_hours' => 'decimal:4',
            'week2_dt_hours' => 'decimal:4',
            'week2_regular_pay' => 'decimal:4',
            'week2_ot_pay' => 'decimal:4',
            'week2_dt_pay' => 'decimal:4',
            'week2_regular_billable' => 'decimal:4',
            'week2_ot_billable' => 'decimal:4',
            'week2_dt_billable' => 'decimal:4',
            'total_regular_hours' => 'decimal:4',
            'total_ot_hours' => 'decimal:4',
            'total_dt_hours' => 'decimal:4',
            'total_consultant_cost' => 'decimal:4',
            'total_client_billable' => 'decimal:4',
            'gross_revenue' => 'decimal:4',
            'gross_margin_dollars' => 'decimal:4',
            'gross_margin_percent' => 'decimal:4',
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
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return HasMany<TimesheetDailyHour, $this>
     */
    public function dailyHours(): HasMany
    {
        return $this->hasMany(TimesheetDailyHour::class);
    }
}
