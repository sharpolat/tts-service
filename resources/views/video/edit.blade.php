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

        .alert-info {
            background: #add8e6;
            border-color: #000080;
            color: #000080;
            font-weight: bold;
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
                <a href="{{ route('tts.index') }}">← TTS Главная</a> |
                <a href="{{ route('video.index') }}">Все видео проекты</a>
            </p>

            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('info'))
                <div class="alert alert-info">
                    {{ session('info') }}
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
                            <strong style="font-size: 10px; color: #666;">Озвучивается текст:</strong><br>
                            {{ $segment->text }}
                        </div>

                        <input type="hidden" name="segments[{{ $loop->index }}][id]" value="{{ $segment->id }}">

                        @if($segment->search_query)
                            <p style="font-size: 10px; color: #666; margin: 10px 0;">
                                <strong>AI запрос:</strong> {{ $segment->search_query }}
                            </p>
                        @endif

                        @if($segment->image_options && is_array($segment->image_options) && count($segment->image_options) > 0)
                            <div class="form-group">
                                <label>Выберите картинку (отметьте галочкой):</label>
                                <div style="display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap;">
                                    @foreach($segment->image_options as $index => $option)
                                        <label style="cursor: pointer; border: 2px solid #999; padding: 5px; background: #fff;">
                                            <input type="radio"
                                                   name="segments[{{ $loop->parent->index }}][image_url]"
                                                   value="{{ $option['url'] }}"
                                                   {{ $segment->image_url === $option['url'] ? 'checked' : '' }}>
                                            <img src="{{ $option['thumb'] ?? $option['url'] }}"
                                                 style="display: block; max-width: 150px; max-height: 150px; margin-top: 5px;"
                                                 alt="Вариант {{ $index + 1 }}">
                                        </label>
                                    @endforeach

                                    <!-- Своя картинка -->
                                    <label style="cursor: pointer; border: 2px dashed #999; padding: 5px; background: #f0f0f0; min-width: 160px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                                        <input type="radio"
                                               name="segments[{{ $loop->parent->index }}][image_url]"
                                               value=""
                                               class="custom-image-radio"
                                               data-segment="{{ $loop->parent->index }}">
                                        <input type="file"
                                               accept="image/*"
                                               class="custom-image-upload"
                                               data-segment="{{ $loop->parent->index }}"
                                               style="display: none;">
                                        <div style="text-align: center; font-size: 10px; color: #666;">
                                            📤 Загрузить<br>свою картинку
                                        </div>
                                        <img class="custom-image-preview" data-segment="{{ $loop->parent->index }}" style="display: none; max-width: 150px; max-height: 150px; margin-top: 5px;" alt="Своя картинка">
                                    </label>
                                </div>
                            </div>
                        @else
                            <p style="margin-top: 10px; font-size: 10px; color: #666;">
                                Картинки появятся после генерации видео
                            </p>
                        @endif
                    </div>
                @endforeach

                <button type="submit">Сохранить изменения</button>
            </form>

            <hr style="margin: 20px 0; border: 1px inset #fff;">

            <form action="{{ route('video.generate', $project->id) }}" method="POST">
                @csrf
                <div style="margin-bottom: 10px;">
                    <label style="font-size: 11px; display: block; margin-bottom: 5px;">
                        <strong>Качество видео:</strong>
                    </label>
                    <select name="quality" style="padding: 5px; font-size: 11px; border: 2px inset #fff; background: #fff;">
                        <option value="480p" {{ ($project->quality ?? '720p') === '480p' ? 'selected' : '' }}>480p (SD)</option>
                        <option value="720p" {{ ($project->quality ?? '720p') === '720p' ? 'selected' : '' }}>720p (HD)</option>
                        <option value="1080p" {{ ($project->quality ?? '720p') === '1080p' ? 'selected' : '' }}>1080p (Full HD)</option>
                        <option value="1440p" {{ ($project->quality ?? '720p') === '1440p' ? 'selected' : '' }}>1440p (2K)</option>
                    </select>
                </div>
                <button type="submit" class="btn-generate" onclick="return confirm('Создать видео из всех сегментов?');">
                    ГЕНЕРИРОВАТЬ ВИДЕО
                </button>
            </form>

            @if($project->video_file)
                <div style="margin-top: 15px; padding: 10px; background: #fff; border: 2px inset #fff;">
                    <strong>Готовое видео:</strong><br>
                    <video controls style="max-width: 100%; margin: 10px 0; border: 2px solid #000;">
                        <source src="{{ asset($project->video_file) }}" type="video/mp4">
                        Ваш браузер не поддерживает видео.
                    </video>
                    <br>
                    <a href="{{ asset($project->video_file) }}" download style="padding: 5px 10px; background: #c0c0c0; border: 2px outset #fff; text-decoration: none; display: inline-block;">💾 Скачать видео</a>
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

        // Обработчик загрузки своих картинок
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.custom-image-upload').forEach(input => {
                const segmentIndex = input.dataset.segment;
                const label = input.closest('label');
                const preview = label.querySelector('.custom-image-preview');
                const radio = label.querySelector('.custom-image-radio');

                // Клик по label открывает file input
                label.addEventListener('click', (e) => {
                    if (e.target !== radio && e.target !== input) {
                        e.preventDefault();
                        input.click();
                    }
                });

                // При выборе файла
                input.addEventListener('change', (e) => {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = (event) => {
                            // Показываем превью
                            preview.src = event.target.result;
                            preview.style.display = 'block';
                            label.querySelector('div').style.display = 'none';

                            // Выбираем radio и устанавливаем значение в base64
                            radio.checked = true;
                            radio.value = event.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            });
        });
    </script>
</body>
</html>
