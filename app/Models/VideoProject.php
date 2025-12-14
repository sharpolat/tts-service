<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoProject extends Model
{
    protected $fillable = [
        'title',
        'tts_history_id',
        'segments',
        'video_file',
        'status',
    ];

    protected $casts = [
        'segments' => 'array',
    ];

    public function ttsHistory()
    {
        return $this->belongsTo(TtsHistory::class);
    }

    public function videoSegments()
    {
        return $this->hasMany(VideoSegment::class)->orderBy('order');
    }
}
