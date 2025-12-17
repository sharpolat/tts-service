<?php

namespace App\Http\Controllers;

use App\Models\TtsHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class TtsController extends Controller
{
    public function index()
    {
        $history = TtsHistory::whereNull('parent_id')
            ->with('versions')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        return view('tts.index', compact('history'));
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
        $pythonBin = '/home/shapo/anime-stories/venv/bin/python3';
        $outputDir = public_path('audio');

        // Вызов Python скрипта с рабочей директорией и переменными окружения
        $result = Process::path($outputDir)
            ->env([
                'PATH' => '/usr/local/bin:/usr/bin:/bin',
                'PYTHONUNBUFFERED' => '1'
            ])
            ->run([
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

        // Сохраняем в историю
        TtsHistory::create([
            'text' => $text,
            'speed' => $speed,
            'audio_file' => 'audio/' . $filename
        ]);

        return redirect()->route('tts.index')->with([
            'success' => 'Аудио успешно сгенерировано!',
            'audio_file' => 'audio/' . $filename
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'text' => 'required|string|max:5000',
            'speed' => 'nullable|in:0,1,2,3,4'
        ]);

        $originalItem = TtsHistory::findOrFail($id);

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
        $pythonBin = '/home/shapo/anime-stories/venv/bin/python3';
        $outputDir = public_path('audio');

        // Вызов Python скрипта с рабочей директорией и переменными окружения
        $result = Process::path($outputDir)
            ->env([
                'PATH' => '/usr/local/bin:/usr/bin:/bin',
                'PYTHONUNBUFFERED' => '1'
            ])
            ->run([
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

        $filename = basename($output['file']);

        // Определяем parent_id и новую версию
        $parentId = $originalItem->parent_id ?: $originalItem->id;
        $maxVersion = TtsHistory::where('parent_id', $parentId)
            ->orWhere('id', $parentId)
            ->max('version');
        $newVersion = $maxVersion + 1;

        // Создаем новую версию
        TtsHistory::create([
            'text' => $text,
            'speed' => $speed,
            'audio_file' => 'audio/' . $filename,
            'parent_id' => $parentId,
            'version' => $newVersion
        ]);

        return redirect()->route('tts.index')->with([
            'success' => 'Создана новая версия (v' . $newVersion . ')!',
            'audio_file' => 'audio/' . $filename
        ]);
    }

    public function delete($id)
    {
        $item = TtsHistory::findOrFail($id);

        // Удаляем файл
        $filePath = public_path($item->audio_file);
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $item->delete();

        return redirect()->route('tts.index')->with('success', 'Запись удалена');
    }
}
