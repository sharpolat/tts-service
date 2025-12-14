<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class TtsController extends Controller
{
    public function index()
    {
        return view('tts.index');
    }

    public function generate(Request $request)
    {
        $request->validate([
            'text' => 'required|string|max:5000',
            'speed' => 'nullable|in:0,1,2,3,4'
        ]);

        $text = $request->input('text');
        $speedMap = [
            '0' => '+0%',
            '1' => '+10%',
            '2' => '+20%',
            '3' => '+30%',
            '4' => '+40%'
        ];
        $speed = $speedMap[$request->input('speed', '1')];

        // Путь к Python скрипту
        $pythonScript = base_path('scripts/tts_worker.py');
        $pythonBin = '/usr/bin/python3';
        $outputDir = public_path('audio');

        // Вызов Python скрипта с рабочей директорией
        $result = Process::path($outputDir)->run([
            $pythonBin,
            $pythonScript,
            $text,
            $speed
        ]);

        if (!$result->successful()) {
            return back()->withErrors(['error' => 'Ошибка генерации аудио: ' . $result->errorOutput()]);
        }

        $output = json_decode($result->output(), true);

        if (isset($output['error'])) {
            return back()->withErrors(['error' => 'Python ошибка: ' . $output['error']]);
        }

        // Файл уже в public/audio
        $filename = basename($output['file']);

        return redirect()->route('tts.index')->with([
            'success' => 'Аудио успешно сгенерировано!',
            'audio_file' => 'audio/' . $filename
        ]);
    }
}
