<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    public $timestamps = false;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'created_at',
        'file_path',
        'file_size',
        'backup_type',
        'status',
        'notes',
    ];
}
