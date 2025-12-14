<?php

use App\Http\Controllers\TtsController;
use App\Http\Controllers\VideoProjectController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TtsController::class, 'index'])->name('tts.index')->middleware('auth.basic');
Route::post('/generate', [TtsController::class, 'generate'])->name('tts.generate')->middleware('auth.basic');
Route::put('/update/{id}', [TtsController::class, 'update'])->name('tts.update')->middleware('auth.basic');
Route::delete('/delete/{id}', [TtsController::class, 'delete'])->name('tts.delete')->middleware('auth.basic');

// Video Projects
Route::prefix('video')->middleware('auth.basic')->group(function () {
    Route::get('/', [VideoProjectController::class, 'index'])->name('video.index');
    Route::get('/create/{ttsHistoryId}', [VideoProjectController::class, 'create'])->name('video.create');
    Route::post('/store', [VideoProjectController::class, 'store'])->name('video.store');
    Route::get('/{id}/edit', [VideoProjectController::class, 'edit'])->name('video.edit');
    Route::put('/{id}', [VideoProjectController::class, 'update'])->name('video.update');
    Route::post('/{id}/generate', [VideoProjectController::class, 'generate'])->name('video.generate');
    Route::delete('/{id}', [VideoProjectController::class, 'delete'])->name('video.delete');
});
