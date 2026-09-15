<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'actor_id',
        'actor_label',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'subject_label',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
