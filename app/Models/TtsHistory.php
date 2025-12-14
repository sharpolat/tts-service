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
        'parent_id',
        'version',
    ];

    public function versions()
    {
        return $this->hasMany(TtsHistory::class, 'parent_id')->orderBy('version', 'desc');
    }

    public function parent()
    {
        return $this->belongsTo(TtsHistory::class, 'parent_id');
    }
}
