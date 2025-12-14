<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tts_history', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('id');
            $table->integer('version')->default(1)->after('parent_id');

            $table->foreign('parent_id')->references('id')->on('tts_history')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('tts_history', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'version']);
        });
    }
};
