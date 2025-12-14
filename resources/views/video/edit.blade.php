<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать видео проект</title>
    <style>
        body {
            font-family: "MS Sans Serif", Arial, sans-serif;
            background: #008080;
            margin: 20px;
        }

        .container {
            max-width: 1200px;
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

        .segment {
            margin-bottom: 20px;
            padding: 10px;
            border: 2px groove #fff;
            background: #e0e0e0;
        }

        .segment-header {
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 12px;
        }

        .segment-text {
            background: #fff;
            padding: 5px;
            margin-bottom: 10px;
            border: 2px inset #fff;
            font-size: 11px;
        }

        .form-group {
            margin-bottom: 10px;
        }

        label {
            display: block;
            margin-bottom: 3px;
            font-size: 11px;
        }

        input[type="text"], input[type="url"] {
            width: 100%;
            padding: 3px;
            border: 2px inset #fff;
            background: white;
            font-family: "Courier New", monospace;
            font-size: 11px;
        }

        button {
            padding: 5px 15px;
            border: 2px outset #fff;
            background: #c0c0c0;
            font-size: 11px;
            cursor: pointer;
            font-family: "MS Sans Serif", Arial, sans-serif;
            margin-right: 5px;
        }

        button:active {
            border: 2px inset #fff;
        }

        .btn-search {
            background: #008000;
            color: white;
        }

        .btn-generate {
            background: #ff0000;
            color: white;
            font-weight: bold;
        }

        .image-preview {
            max-width: 200px;
            border: 2px solid #000;
            margin-top: 5px;
        }

        a {
            color: #0000ff;
            text-decoration: underline;
            font-size: 11px;
        }

        .alert {
            padding: 8px;
            margin-bottom: 10px;
            border: 2px solid;
            font-size: 11px;
        }

        .alert-success {
            background: #ffffe0;
            border-color: #000;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="title-bar">
            <span>Редактировать проект: {{ $project->title }}</span>
        </div>
        <div class="content">
            <p style="margin-bottom: 10px;">
                <a href="{{ route('tts.index') }}">← Назад к TTS</a> |
                <a href="{{ route('video.index') }}">Все проекты</a>
            </p>

            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            <h1>Подбор картинок для сегментов</h1>

            <p style="font-size: 11px; margin-bottom: 10px;">
                <strong>Аудио:</strong> <a href="{{ asset($project->ttsHistory->audio_file) }}" target="_blank">Прослушать</a>
            </p>

            <div style="margin: 15px 0; padding: 10px; background: #fff; border: 2px inset #fff;">
                <strong style="font-size: 12px;">Исходный текст:</strong>
                <p style="font-size: 11px; margin-top: 5px; line-height: 1.5;">{{ $project->ttsHistory->text }}</p>
            </div>

            <form action="{{ route('video.update', $project->id) }}" method="POST">
                @csrf
                @method('PUT')

                @foreach($project->videoSegments as $segment)
                    <div class="segment">
                        <div class="segment-header">Сегмент {{ $segment->order + 1 }}</div>

                        <div class="segment-text">
                            {{ $segment->text }}
                        </div>

                        <input type="hidden" name="segments[{{ $loop->index }}][id]" value="{{ $segment->id }}">

                        <div class="form-group">
                            <label>Поисковый запрос:</label>
                            <input type="text"
                                   name="segments[{{ $loop->index }}][search_query]"
                                   value="{{ $segment->search_query }}"
                                   placeholder="итачи учиха аниме">
                        </div>

                        <div class="form-group">
                            <label>URL картинки:</label>
                            <input type="url"
                                   name="segments[{{ $loop->index }}][image_url]"
                                   id="image_{{ $segment->id }}"
                                   value="{{ $segment->image_url }}"
                                   placeholder="https://example.com/image.jpg"
                                   onchange="updatePreview({{ $segment->id }})">
                        </div>

                        @if($segment->image_url)
                            <img src="{{ $segment->image_url }}"
                                 class="image-preview"
                                 id="preview_{{ $segment->id }}"
                                 alt="Preview">
                        @else
                            <img src="" class="image-preview" id="preview_{{ $segment->id }}" style="display:none;">
                        @endif

                        <p style="margin-top: 10px; font-size: 10px; color: #666;">
                            Вставьте URL картинки вручную или найдите в интернете
                        </p>
                    </div>
                @endforeach

                <button type="submit">Сохранить изменения</button>
            </form>

            <hr style="margin: 20px 0; border: 1px inset #fff;">

            <form action="{{ route('video.autoSearchImages', $project->id) }}" method="POST" style="margin: 15px 0;">
                @csrf
                <button type="submit" class="btn-search" onclick="return confirm('Автоматически найти картинки для всех сегментов?');">
                    🔍 АВТОПОИСК КАРТИНОК (Unsplash)
                </button>
                <p style="font-size: 10px; margin-top: 5px; color: #666;">
                    Автоматически найдет и подставит картинки для всех сегментов на основе поисковых запросов
                </p>
            </form>

            <hr style="margin: 20px 0; border: 1px inset #fff;">

            <form action="{{ route('video.generate', $project->id) }}" method="POST">
                @csrf
                <button type="submit" class="btn-generate" onclick="return confirm('Создать видео из всех сегментов?');">
                    ГЕНЕРИРОВАТЬ ВИДЕО
                </button>
            </form>

            @if($project->video_file)
                <div style="margin-top: 15px; padding: 10px; background: #fff; border: 2px inset #fff;">
                    <strong>Готовое видео:</strong>
                    <a href="{{ asset($project->video_file) }}" target="_blank">Просмотреть</a> |
                    <a href="{{ asset($project->video_file) }}" download>Скачать</a>
                </div>
            @endif
        </div>
    </div>

    <script>
        function updatePreview(segmentId) {
            const input = document.getElementById('image_' + segmentId);
            const preview = document.getElementById('preview_' + segmentId);

            if (input.value) {
                preview.src = input.value;
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        }
    </script>
</body>
</html>
