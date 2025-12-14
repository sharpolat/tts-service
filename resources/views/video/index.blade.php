<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Видео проекты</title>
    <style>
        body {
            font-family: "MS Sans Serif", Arial, sans-serif;
            background: #008080;
            margin: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #c0c0c0;
            border: 2px outset #fff;
            padding: 3px;
        }

        .title-bar {
            background: linear-gradient(to right, #000080, #1084d0);
            color: white;
            padding: 3px 5px;
            font-weight: bold;
            font-size: 12px;
        }

        .content {
            background: #c0c0c0;
            padding: 10px;
        }

        h1 {
            font-size: 16px;
            margin-bottom: 15px;
        }

        .project-item {
            padding: 10px;
            margin-bottom: 10px;
            border: 2px groove #fff;
            background: #e0e0e0;
        }

        .project-title {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 12px;
        }

        .project-info {
            font-size: 11px;
            margin-bottom: 8px;
            color: #666;
        }

        .project-actions {
            font-size: 11px;
        }

        button {
            padding: 3px 10px;
            border: 2px outset #fff;
            background: #c0c0c0;
            font-size: 11px;
            cursor: pointer;
            font-family: "MS Sans Serif", Arial, sans-serif;
        }

        button:active {
            border: 2px inset #fff;
        }

        a {
            color: #0000ff;
            text-decoration: underline;
        }

        .status {
            padding: 2px 8px;
            border: 1px solid;
            font-size: 10px;
            display: inline-block;
        }

        .status-draft { background: #ffffe0; border-color: #000; }
        .status-processing { background: #add8e6; border-color: #00008b; }
        .status-completed { background: #90ee90; border-color: #006400; }
        .status-failed { background: #ffb6c1; border-color: #8b0000; }
    </style>
</head>
<body>
    <div class="container">
        <div class="title-bar">
            <span>Видео проекты</span>
        </div>
        <div class="content">
            <p style="margin-bottom: 15px;">
                <a href="{{ route('tts.index') }}">← Назад к TTS</a>
            </p>

            <h1>Все видео проекты</h1>

            @if($projects->count() > 0)
                @foreach($projects as $project)
                    <div class="project-item">
                        <div class="project-title">
                            {{ $project->title }}
                            <span class="status status-{{ $project->status }}">{{ $project->status }}</span>
                        </div>

                        <div class="project-info">
                            Создан: {{ $project->created_at->format('d.m.Y H:i') }} |
                            Сегментов: {{ $project->videoSegments->count() }}
                        </div>

                        <div class="project-actions">
                            <a href="{{ route('video.edit', $project->id) }}">Редактировать</a> |

                            @if($project->video_file)
                                <a href="{{ asset($project->video_file) }}" target="_blank">Просмотреть</a> |
                                <a href="{{ asset($project->video_file) }}" download>Скачать</a> |
                            @endif

                            <form action="{{ route('video.delete', $project->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Удалить проект?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit">Удалить</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            @else
                <p style="font-size: 11px;">Нет проектов. Создайте видео из TTS истории.</p>
            @endif
        </div>
    </div>
</body>
</html>
