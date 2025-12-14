<?php

namespace App\Http\Controllers;

use App\Models\TtsHistory;
use App\Models\VideoProject;
use App\Models\VideoSegment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class VideoProjectController extends Controller
{
    public function index()
    {
        $projects = VideoProject::with('ttsHistory')->orderBy('created_at', 'desc')->get();
        return view('video.index', compact('projects'));
    }

    public function create($ttsHistoryId)
    {
        $ttsHistory = TtsHistory::findOrFail($ttsHistoryId);

        // Разбиваем текст на сегменты (по предложениям)
        $sentences = preg_split('/(?<=[.!?])\s+/', $ttsHistory->text, -1, PREG_SPLIT_NO_EMPTY);

        return view('video.create', compact('ttsHistory', 'sentences'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'tts_history_id' => 'required|exists:tts_history,id',
            'segments' => 'required|array',
            'segments.*.text' => 'required|string',
            'segments.*.search_query' => 'nullable|string',
        ]);

        $project = VideoProject::create([
            'title' => $request->title,
            'tts_history_id' => $request->tts_history_id,
            'status' => 'draft',
        ]);

        foreach ($request->segments as $index => $segment) {
            VideoSegment::create([
                'video_project_id' => $project->id,
                'text' => $segment['text'],
                'search_query' => $segment['search_query'] ?? null,
                'order' => $index,
            ]);
        }

        return redirect()->route('video.edit', $project->id)->with('success', 'Проект создан!');
    }

    public function edit($id)
    {
        $project = VideoProject::with(['videoSegments', 'ttsHistory'])->findOrFail($id);
        return view('video.edit', compact('project'));
    }

    public function update(Request $request, $id)
    {
        $project = VideoProject::findOrFail($id);

        $request->validate([
            'segments' => 'required|array',
            'segments.*.id' => 'required|exists:video_segments,id',
            'segments.*.image_url' => 'nullable|url',
            'segments.*.search_query' => 'nullable|string',
        ]);

        foreach ($request->segments as $segmentData) {
            $segment = VideoSegment::find($segmentData['id']);
            $segment->update([
                'image_url' => $segmentData['image_url'] ?? null,
                'search_query' => $segmentData['search_query'] ?? null,
            ]);
        }

        return back()->with('success', 'Сегменты обновлены!');
    }

    public function generate($id)
    {
        $project = VideoProject::with(['videoSegments', 'ttsHistory'])->findOrFail($id);

        // Проверяем что у всех сегментов есть картинки
        $missingImages = $project->videoSegments->filter(function ($segment) {
            return empty($segment->image_url);
        });

        if ($missingImages->count() > 0) {
            return back()->withErrors(['error' => 'Не все сегменты имеют картинки!']);
        }

        $project->update(['status' => 'processing']);

        // Подготавливаем данные сегментов
        $segmentsData = $project->videoSegments->map(function ($segment) {
            return [
                'image_url' => $segment->image_url,
                'text' => $segment->text,
                'order' => $segment->order,
            ];
        })->toArray();

        // Путь к Python скрипту
        $pythonScript = base_path('scripts/video_generator.py');
        $pythonBin = '/home/shapo/anime-stories/venv/bin/python3';
        $audioFile = public_path($project->ttsHistory->audio_file);
        $videoFile = public_path('videos/video_' . $project->id . '_' . time() . '.mp4');

        // Создаем директорию для видео если не существует
        if (!file_exists(public_path('videos'))) {
            mkdir(public_path('videos'), 0777, true);
        }

        // Вызов Python скрипта
        $result = Process::timeout(600)->run([
            $pythonBin,
            $pythonScript,
            json_encode($segmentsData),
            $audioFile,
            $videoFile
        ]);

        if (!$result->successful()) {
            $project->update(['status' => 'failed']);
            return back()->withErrors(['error' => 'Ошибка генерации видео: ' . $result->errorOutput()]);
        }

        $output = json_decode($result->output(), true);

        if (isset($output['error'])) {
            $project->update(['status' => 'failed']);
            return back()->withErrors(['error' => 'Python ошибка: ' . $output['error']]);
        }

        // Обновляем проект
        $project->update([
            'video_file' => 'videos/' . basename($videoFile),
            'status' => 'completed'
        ]);

        return back()->with('success', 'Видео успешно сгенерировано!');
    }

    public function delete($id)
    {
        $project = VideoProject::findOrFail($id);

        // Удалить файлы
        if ($project->video_file && file_exists(public_path($project->video_file))) {
            unlink(public_path($project->video_file));
        }

        $project->delete();

        return redirect()->route('video.index')->with('success', 'Проект удален');
    }
}
