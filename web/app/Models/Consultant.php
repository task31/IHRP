<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consultant extends Model
{
    protected $fillable = [
        'full_name',
        'pay_rate',
        'bill_rate',
        'gross_margin_per_hour',
        'state',
        'industry_type',
        'client_id',
        'project_start_date',
        'project_end_date',
        'w9_on_file',
        'w9_file_path',
        'contract_on_file',
        'contract_file_path',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pay_rate' => 'decimal:4',
            'bill_rate' => 'decimal:4',
            'gross_margin_per_hour' => 'decimal:4',
            'w9_on_file' => 'boolean',
            'contract_on_file' => 'boolean',
            'active' => 'boolean',
            'project_start_date' => 'date',
            'project_end_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
