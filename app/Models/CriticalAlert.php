<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CriticalAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'severity',
        'message',
        'contact_name',
        'contact_phone',
        'contact_relationship',
        'triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'triggered_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
