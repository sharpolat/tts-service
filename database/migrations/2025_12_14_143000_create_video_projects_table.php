<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('tts_history_id')->constrained('tts_history')->onDelete('cascade');
            $table->text('segments')->nullable(); // JSON массив сегментов
            $table->string('video_file')->nullable();
            $table->enum('status', ['draft', 'processing', 'completed', 'failed'])->default('draft');
            $table->timestamps();
        });

        Schema::create('video_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_project_id')->constrained('video_projects')->onDelete('cascade');
            $table->text('text');
            $table->string('audio_segment')->nullable();
            $table->string('image_url')->nullable();
            $table->string('search_query')->nullable();
            $table->integer('order');
            $table->float('start_time')->default(0);
            $table->float('duration')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_segments');
        Schema::dropIfExists('video_projects');
    }
};
