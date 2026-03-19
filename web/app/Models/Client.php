<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'name',
        'billing_contact_name',
        'billing_address',
        'email',
        'smtp_email',
        'payment_terms',
        'total_budget',
        'budget_alert_warning_sent',
        'budget_alert_critical_sent',
        'po_number',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_budget' => 'decimal:4',
            'budget_alert_warning_sent' => 'boolean',
            'budget_alert_critical_sent' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Consultant, $this>
     */
    public function consultants(): HasMany
    {
        return $this->hasMany(Consultant::class);
    }
}
