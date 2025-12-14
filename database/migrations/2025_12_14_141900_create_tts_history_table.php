<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tts_history', function (Blueprint $table) {
            $table->id();
            $table->text('text');
            $table->string('speed', 10);
            $table->string('audio_file');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tts_history');
    }
};
