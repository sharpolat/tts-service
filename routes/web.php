<?php

use App\Http\Controllers\TtsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TtsController::class, 'index'])->name('tts.index')->middleware('auth.basic');
Route::post('/generate', [TtsController::class, 'generate'])->name('tts.generate')->middleware('auth.basic');
Route::put('/update/{id}', [TtsController::class, 'update'])->name('tts.update')->middleware('auth.basic');
Route::delete('/delete/{id}', [TtsController::class, 'delete'])->name('tts.delete')->middleware('auth.basic');
