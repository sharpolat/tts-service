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
        'search_query',
        'order',
        'start_time',
        'duration',
    ];

    public function videoProject()
    {
        return $this->belongsTo(VideoProject::class);
    }
}
