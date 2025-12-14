<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Создать видео проект</title>
    <style>
        body {
            font-family: "MS Sans Serif", Arial, sans-serif;
            background: #008080;
            margin: 20px;
        }

        .container {
            max-width: 800px;
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

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 3px;
            font-size: 12px;
        }

        input[type="text"], textarea {
            width: 100%;
            padding: 3px;
            border: 2px inset #fff;
            background: white;
            font-family: "Courier New", monospace;
            font-size: 12px;
        }

        .segment {
            margin-bottom: 15px;
            padding: 10px;
            border: 2px groove #fff;
            background: #e0e0e0;
        }

        .segment-number {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 11px;
        }

        button {
            padding: 5px 20px;
            border: 2px outset #fff;
            background: #c0c0c0;
            font-size: 12px;
            cursor: pointer;
            font-family: "MS Sans Serif", Arial, sans-serif;
            margin-right: 5px;
        }

        button:active {
            border: 2px inset #fff;
        }

        a {
            color: #0000ff;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="title-bar">
            <span>Создать видео проект</span>
        </div>
        <div class="content">
            <h1>Новый видео проект</h1>

            <p style="margin-bottom: 10px; font-size: 11px;">
                <a href="{{ route('tts.index') }}">← Назад к TTS</a>
            </p>

            <form action="{{ route('video.store') }}" method="POST">
                @csrf
                <input type="hidden" name="tts_history_id" value="{{ $ttsHistory->id }}">

                <div class="form-group">
                    <label for="title">Название проекта:</label>
                    <input type="text" name="title" id="title" required value="Видео: {{ Str::limit($ttsHistory->text, 30) }}">
                </div>

                <p style="font-size: 11px; margin-bottom: 10px;">
                    <strong>Аудио:</strong> <a href="{{ asset($ttsHistory->audio_file) }}" target="_blank">Прослушать</a>
                </p>

                <div style="margin: 15px 0; padding: 10px; background: #fff; border: 2px inset #fff;">
                    <strong style="font-size: 12px;">Исходный текст:</strong>
                    <p style="font-size: 11px; margin-top: 5px; line-height: 1.5;">{{ $ttsHistory->text }}</p>
                </div>

                <h2 style="font-size: 14px; margin: 15px 0 10px;">
                    AI создал {{ count($aiSegments) }} сегментов с поисковыми запросами:
                </h2>

                @foreach($aiSegments as $index => $segment)
                    <div class="segment">
                        <div class="segment-number">
                            Сегмент {{ $index + 1 }}
                            @if(isset($segment['tone']))
                                <span style="color: #666; font-size: 10px;">[{{ $segment['tone'] }}]</span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label>Текст:</label>
                            <textarea name="segments[{{ $index }}][text]" rows="2" readonly>{{ $segment['text'] }}</textarea>
                        </div>

                        <div class="form-group">
                            <label for="search_{{ $index }}">Поисковый запрос (AI сгенерировал, можно редактировать):</label>
                            <input type="text"
                                   name="segments[{{ $index }}][search_query]"
                                   id="search_{{ $index }}"
                                   value="{{ $segment['search_query'] ?? '' }}"
                                   placeholder="Редактировать запрос">
                        </div>
                    </div>
                @endforeach

                <button type="submit">Создать проект</button>
                <a href="{{ route('tts.index') }}"><button type="button">Отмена</button></a>
            </form>
        </div>
    </div>
</body>
</html>
