<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TtsHistory extends Model
{
    protected $table = 'tts_history';

    protected $fillable = [
        'text',
        'speed',
        'audio_file',
    ];
}
