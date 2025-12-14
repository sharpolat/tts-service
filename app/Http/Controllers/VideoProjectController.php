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

        // Используем AI для умного разбиения текста
        $pythonScript = base_path('scripts/ai_video_analyzer.py');
        $pythonBin = '/home/shapo/anime-stories/venv/bin/python3';

        $result = Process::timeout(300)->run([
            $pythonBin,
            $pythonScript,
            $ttsHistory->text,
            'qwen2.5:14b'
        ]);

        $aiSegments = [];
        if ($result->successful()) {
            $output = json_decode($result->output(), true);
            if (isset($output['segments'])) {
                $aiSegments = $output['segments'];
            }
        }

        // Fallback если AI не сработал
        if (empty($aiSegments)) {
            $sentences = preg_split('/(?<=[.!?])\s+/', $ttsHistory->text, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($sentences as $index => $sentence) {
                $aiSegments[] = [
                    'text' => trim($sentence),
                    'search_query' => '',
                    'tone' => 'neutral',
                    'order' => $index
                ];
            }
        }

        return view('video.create', compact('ttsHistory', 'aiSegments'));
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

        // Сначала умно разрезаем аудио
        $audioSplitterScript = base_path('scripts/smart_audio_splitter.py');
        $audioSegmentsDir = public_path('temp_audio_segments_' . $project->id);

        $audioSplitResult = Process::timeout(120)->run([
            $pythonBin,
            $audioSplitterScript,
            $audioFile,
            count($segmentsData),
            $audioSegmentsDir
        ]);

        if (!$audioSplitResult->successful()) {
            $project->update(['status' => 'failed']);
            return back()->withErrors(['error' => 'Ошибка разрезания аудио: ' . $audioSplitResult->errorOutput()]);
        }

        $audioSegments = json_decode($audioSplitResult->output(), true);

        // Добавляем информацию об аудио сегментах к данным
        foreach ($segmentsData as $index => &$segment) {
            if (isset($audioSegments['segments'][$index])) {
                $segment['audio_file'] = $audioSegments['segments'][$index]['file'];
                $segment['duration'] = $audioSegments['segments'][$index]['duration'];
            }
        }

        // Вызов Python скрипта для генерации видео
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

    public function autoSearchImages($id)
    {
        $project = VideoProject::with('videoSegments')->findOrFail($id);

        // Подготавливаем сегменты для поиска
        $segments = $project->videoSegments->map(function ($segment) {
            return [
                'text' => $segment->text,
                'search_query' => $segment->search_query,
                'order' => $segment->order,
            ];
        })->toArray();

        // Вызываем Python скрипт для поиска картинок
        $pythonScript = base_path('scripts/image_searcher.py');
        $pythonBin = '/home/shapo/anime-stories/venv/bin/python3';

        // API ключи из .env
        $pexelsKey = env('PEXELS_API_KEY', null);
        $pixabayKey = env('PIXABAY_API_KEY', null);

        $result = Process::timeout(120)->run([
            $pythonBin,
            $pythonScript,
            json_encode($segments),
            $pexelsKey,
            $pixabayKey
        ]);

        if (!$result->successful()) {
            return back()->withErrors(['error' => 'Ошибка поиска картинок: ' . $result->errorOutput()]);
        }

        $output = json_decode($result->output(), true);

        if (isset($output['error'])) {
            return back()->withErrors(['error' => 'Ошибка: ' . $output['error']]);
        }

        // Автоматически выбираем первую картинку для каждого сегмента
        foreach ($output['results'] as $result) {
            $segment = $project->videoSegments->where('order', $result['order'])->first();

            if ($segment && $result['selected_image']) {
                $segment->update(['image_url' => $result['selected_image']]);
            }
        }

        return back()->with('success', 'Картинки автоматически найдены и добавлены!');
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
