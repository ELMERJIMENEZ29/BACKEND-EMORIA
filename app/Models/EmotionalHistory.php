<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmotionalHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'companion',
        'recognized_emotion',
        'depression_score',
        'anxiety_score',
        'stress_score',
        'depression_severity',
        'anxiety_severity',
        'stress_severity',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
