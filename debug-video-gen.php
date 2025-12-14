<?php

// Debug script to test video generation step-by-step

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$projectId = 11;
$project = \App\Models\VideoProject::with(['videoSegments', 'ttsHistory'])->find($projectId);

if (!$project) {
    die("Project not found\n");
}

echo "Project: {$project->title}\n";
echo "Segments: " . $project->videoSegments->count() . "\n";
echo "Audio file: {$project->ttsHistory->audio_file}\n\n";

// Check all segments have images
$missingImages = $project->videoSegments->filter(fn($s) => empty($s->image_url));
if ($missingImages->count() > 0) {
    die("Missing images in segments\n");
}
echo "✓ All segments have images\n\n";

// Paths
$pythonBin = '/home/shapo/anime-stories/venv/bin/python3';
$audioFile = public_path($project->ttsHistory->audio_file);
$videoFile = public_path('videos/video_' . $project->id . '_' . time() . '.mp4');
$audioSplitterScript = base_path('scripts/smart_audio_splitter.py');

echo "Audio file: $audioFile\n";
echo "Audio exists: " . (file_exists($audioFile) ? 'YES' : 'NO') . "\n";
echo "Splitter script: $audioSplitterScript\n";
echo "Splitter exists: " . (file_exists($audioSplitterScript) ? 'YES' : 'NO') . "\n\n";

// Check videos dir
$videosDir = public_path('videos');
echo "Videos dir: $videosDir\n";
echo "Videos dir exists: " . (file_exists($videosDir) ? 'YES' : 'NO') . "\n";
echo "Videos dir writable: " . (is_writable($videosDir) ? 'YES' : 'NO') . "\n\n";

// Prepare segments data
$segmentsData = $project->videoSegments->map(function ($segment) {
    return [
        'image_url' => $segment->image_url,
        'text' => $segment->text,
        'order' => $segment->order,
    ];
})->toArray();

echo "Segments count: " . count($segmentsData) . "\n\n";

// Test audio splitter
echo "=== Testing Audio Splitter ===\n";
$audioSegmentsDir = public_path('temp_audio_segments_' . $project->id);

$command = [
    $pythonBin,
    $audioSplitterScript,
    $audioFile,
    count($segmentsData),
    $audioSegmentsDir
];

echo "Command: " . implode(' ', array_map('escapeshellarg', $command)) . "\n\n";

$result = \Illuminate\Support\Facades\Process::timeout(120)->run($command);

echo "Exit code: " . $result->exitCode() . "\n";
echo "Output:\n" . $result->output() . "\n";
echo "Errors:\n" . $result->errorOutput() . "\n";

if (!$result->successful()) {
    die("\n❌ Audio splitter failed\n");
}

echo "\n✓ Audio splitter succeeded\n\n";

// Parse audio segments result
$audioResult = json_decode($result->output(), true);
if (!isset($audioResult['segments'])) {
    die("❌ No segments in audio result\n");
}

// Test video generator
echo "=== Testing Video Generator ===\n";
$videoGeneratorScript = base_path('scripts/video_generator.py');
echo "Generator script: $videoGeneratorScript\n";
echo "Generator exists: " . (file_exists($videoGeneratorScript) ? 'YES' : 'NO') . "\n\n";

// Prepare video generation data
$videoData = [
    'segments' => [],
    'output_file' => $videoFile,
];

foreach ($audioResult['segments'] as $index => $audioSegment) {
    $segment = $project->videoSegments->where('order', $index)->first();
    if (!$segment) {
        die("❌ Missing segment at order $index\n");
    }

    $videoData['segments'][] = [
        'image_url' => $segment->image_url,
        'audio_file' => $audioSegment['file'],
        'duration' => $audioSegment['duration'],
        'order' => $audioSegment['order'],
    ];
}

$jsonInput = json_encode($videoData);
$command = [
    $pythonBin,
    $videoGeneratorScript,
    $jsonInput
];

echo "Command: " . implode(' ', array_map('escapeshellarg', $command)) . "\n\n";
echo "Input JSON:\n" . json_encode($videoData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";

$result = \Illuminate\Support\Facades\Process::timeout(300)->run($command);

echo "Exit code: " . $result->exitCode() . "\n";
echo "Output:\n" . $result->output() . "\n";
echo "Errors:\n" . $result->errorOutput() . "\n";

if (!$result->successful()) {
    die("\n❌ Video generator failed\n");
}

echo "\n✓ Video generator succeeded\n";
echo "Video file: $videoFile\n";
