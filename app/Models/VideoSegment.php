<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoSegment extends Model
{
    protected $fillable = [
        'video_project_id',
        'text',
        'audio_segment',
        'image_url',
        'image_options',
        'search_query',
        'order',
        'start_time',
        'duration',
    ];

    protected $casts = [
        'image_options' => 'array',
    ];

    public function videoProject()
    {
        return $this->belongsTo(VideoProject::class);
    }
}
