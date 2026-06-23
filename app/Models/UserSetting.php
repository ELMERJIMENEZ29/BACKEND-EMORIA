<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'preferences',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'critical_alerts_enabled',
    ];

    protected function casts(): array
    {
        return [
            'preferences' => 'array',
            'critical_alerts_enabled' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
