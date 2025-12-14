<?php

use App\Http\Controllers\TtsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TtsController::class, 'index'])->name('tts.index');
Route::post('/generate', [TtsController::class, 'generate'])->name('tts.generate');
