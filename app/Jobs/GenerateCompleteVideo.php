<?php

namespace App\Jobs;

use App\Models\VideoProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Process;

class GenerateCompleteVideo implements ShouldQueue
{
    use Queueable;

    public $timeout = 900; // 15 минут

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
        $pythonBin = '/home/shapo/anime-stories/venv/bin/python3';

        try {
            // ШАГ 1: AI генерирует search_query для каждого сегмента
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

            // Обновляем search_query в сегментах
            foreach ($this->project->videoSegments as $segment) {
                if (isset($searchQueries[$segment->order])) {
                    $segment->update(['search_query' => $searchQueries[$segment->order]]);
                }
            }

            // ШАГ 2: Ищем картинки для каждого сегмента
            $imageScript = base_path('scripts/image_searcher.py');

            $segments = $this->project->videoSegments->map(function ($segment) {
                return [
                    'text' => $segment->text,
                    'search_query' => $segment->search_query,
                    'order' => $segment->order,
                ];
            })->toArray();

            $imageResult = Process::timeout(180)->run([
                $pythonBin,
                $imageScript,
                json_encode($segments),
                null,
                null
            ]);

            if ($imageResult->successful()) {
                $output = json_decode($imageResult->output(), true);

                if (isset($output['results'])) {
                    foreach ($output['results'] as $resultData) {
                        $segment = $this->project->videoSegments->where('order', $resultData['order'])->first();

                        if ($segment && isset($resultData['images'])) {
                            $options = array_slice($resultData['images'], 0, 3);

                            // Проверяем, была ли картинка выбрана вручную
                            $currentImageUrl = $segment->image_url;
                            $oldOptions = $segment->image_options ?? [];
                            $firstOldOption = $oldOptions[0]['url'] ?? null;

                            // Если текущий image_url:
                            // 1. Это base64 (кастомная загрузка) - не трогаем
                            // 2. Не совпадает с первой картинкой из старых options - пользователь выбрал вручную
                            $isCustomUpload = $currentImageUrl && str_starts_with($currentImageUrl, 'data:image');
                            $manuallySelected = $currentImageUrl && $currentImageUrl !== $firstOldOption;

                            $segment->update([
                                'image_options' => $options,
                                'image_url' => ($isCustomUpload || $manuallySelected) ? $currentImageUrl : ($options[0]['url'] ?? null)
                            ]);
                        }
                    }
                }
            }

            // ШАГ 3: Генерируем видео
            $this->generateVideo();

        } catch (\Exception $e) {
            \Log::error('Video generation failed: ' . $e->getMessage());
            $this->project->update(['status' => 'failed']);
        }
    }

    private function generateVideo()
    {
        $pythonBin = '/home/shapo/anime-stories/venv/bin/python3';
        $audioSplitterScript = base_path('scripts/smart_audio_splitter.py');
        $videoGeneratorScript = base_path('scripts/video_generator.py');

        $audioFile = public_path($this->project->ttsHistory->audio_file);
        $videoFile = public_path('videos/video_' . $this->project->id . '_' . time() . '.mp4');

        // Подготавливаем данные сегментов
        $segmentsData = $this->project->videoSegments->map(function ($segment) {
            return [
                'image_url' => $segment->image_url,
                'text' => $segment->text,
                'order' => $segment->order,
            ];
        })->toArray();

        // Разрезаем аудио
        $audioSegmentsDir = public_path('temp_audio_segments_' . $this->project->id);

        $audioSplitResult = Process::timeout(120)->run([
            $pythonBin,
            $audioSplitterScript,
            $audioFile,
            count($segmentsData),
            $audioSegmentsDir
        ]);

        if (!$audioSplitResult->successful()) {
            throw new \Exception('Audio split failed: ' . $audioSplitResult->errorOutput());
        }

        $audioSegments = json_decode($audioSplitResult->output(), true);

        // Добавляем информацию об аудио сегментах
        foreach ($segmentsData as $index => &$segment) {
            if (isset($audioSegments['segments'][$index])) {
                $segment['audio_file'] = $audioSegments['segments'][$index]['file'];
                $segment['duration'] = $audioSegments['segments'][$index]['duration'];
            }
        }

        // Настройки качества
        $quality = $this->project->quality ?? '720p';
        $qualityMap = [
            '480p' => ['width' => 854, 'height' => 480],
            '720p' => ['width' => 1280, 'height' => 720],
            '1080p' => ['width' => 1920, 'height' => 1080],
            '1440p' => ['width' => 2560, 'height' => 1440],
        ];

        $resolution = $qualityMap[$quality] ?? $qualityMap['720p'];

        $videoData = [
            'segments' => $segmentsData,
            'output_file' => $videoFile,
            'width' => $resolution['width'],
            'height' => $resolution['height']
        ];

        // Генерируем видео
        $result = Process::timeout(600)->run([
            $pythonBin,
            $videoGeneratorScript,
            json_encode($videoData)
        ]);

        if (!$result->successful()) {
            throw new \Exception('Video generation failed: ' . $result->errorOutput());
        }

        $output = json_decode($result->output(), true);

        if (isset($output['error'])) {
            throw new \Exception('Python error: ' . $output['error']);
        }

        // Обновляем проект
        $this->project->update([
            'video_file' => 'videos/' . basename($videoFile),
            'status' => 'completed'
        ]);
    }

    public function failed(\Throwable $exception)
    {
        $this->project->update(['status' => 'failed']);
    }
}
