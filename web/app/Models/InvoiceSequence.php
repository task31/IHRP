<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceSequence extends Model
{
    public $timestamps = false;

    protected $table = 'invoice_sequence';

    protected $fillable = [
        'prefix',
        'next_number',
        'fiscal_year_start',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'next_number' => 'integer',
        ];
    }
}
