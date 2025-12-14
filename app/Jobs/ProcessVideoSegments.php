<?php

namespace App\Jobs;

use App\Models\VideoProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Process;

class ProcessVideoSegments implements ShouldQueue
{
    use Queueable;

    public $timeout = 600; // 10 минут

    /**
     * Create a new job instance.
     */
    public function __construct(public VideoProject $project)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. Генерируем search_query через AI
        $pythonBin = '/home/shapo/anime-stories/venv/bin/python3';
        $aiScript = base_path('scripts/ai_video_analyzer.py');

        $aiResult = Process::timeout(300)->run([
            $pythonBin,
            $aiScript,
            $this->project->ttsHistory->text,
            'qwen2.5:14b'
        ]);

        $searchQueries = [];
        if ($aiResult->successful()) {
            $aiOutput = json_decode($aiResult->output(), true);
            if (isset($aiOutput['segments'])) {
                foreach ($aiOutput['segments'] as $seg) {
                    $searchQueries[$seg['order']] = $seg['search_query'];
                }
            }
        }

        // 2. Обновляем search_query в сегментах
        foreach ($this->project->videoSegments as $segment) {
            if (isset($searchQueries[$segment->order])) {
                $segment->update(['search_query' => $searchQueries[$segment->order]]);
            }
        }

        // 3. Ищем картинки
        $imageScript = base_path('scripts/image_searcher.py');

        $segments = $this->project->videoSegments->map(function ($segment) {
            return [
                'text' => $segment->text,
                'search_query' => $segment->search_query,
                'order' => $segment->order,
            ];
        })->toArray();

        $result = Process::timeout(180)->run([
            $pythonBin,
            $imageScript,
            json_encode($segments),
            null,
            null
        ]);

        if ($result->successful()) {
            $output = json_decode($result->output(), true);

            if (isset($output['results'])) {
                foreach ($output['results'] as $resultData) {
                    $segment = $this->project->videoSegments->where('order', $resultData['order'])->first();

                    if ($segment && isset($resultData['images'])) {
                        // Сохраняем топ-3 картинки как варианты
                        $options = array_slice($resultData['images'], 0, 3);

                        $segment->update([
                            'image_options' => $options,  // Laravel автоматически преобразует в JSON
                            'image_url' => $options[0]['url'] ?? null  // Первая выбрана по умолчанию
                        ]);
                    }
                }
            }
        }

        // Обновляем статус проекта
        $this->project->update(['status' => 'draft']);
    }
}
