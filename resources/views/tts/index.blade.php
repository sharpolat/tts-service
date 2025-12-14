<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TTS Генератор</title>
    <style>
        body {
            font-family: "MS Sans Serif", Arial, sans-serif;
            background: #008080;
            margin: 20px;
        }

        .container {
            max-width: 600px;
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
            display: flex;
            justify-content: space-between;
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

        textarea, select {
            width: 100%;
            padding: 3px;
            border: 2px inset #fff;
            background: white;
            font-family: "Courier New", monospace;
            font-size: 12px;
        }

        textarea {
            min-height: 150px;
            resize: vertical;
        }

        button {
            padding: 5px 20px;
            border: 2px outset #fff;
            background: #c0c0c0;
            font-size: 12px;
            cursor: pointer;
            font-family: "MS Sans Serif", Arial, sans-serif;
        }

        button:active {
            border: 2px inset #fff;
        }

        .alert {
            padding: 8px;
            margin-bottom: 10px;
            border: 2px solid;
            font-size: 12px;
        }

        .alert-success {
            background: #ffffe0;
            border-color: #000;
        }

        .alert-error {
            background: #ff0000;
            color: white;
            border-color: #800000;
        }

        .audio-player {
            margin-top: 15px;
            padding: 10px;
            border: 2px inset #fff;
            background: #fff;
        }

        audio {
            width: 100%;
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
            <span>TTS Generator</span>
            <span>_□X</span>
        </div>
        <div class="content">
        <h1>TTS Генератор</h1>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-error">
                @foreach($errors->all() as $error)
                    {{ $error }}
                @endforeach
            </div>
        @endif

        <form action="{{ route('tts.generate') }}" method="POST">
            @csrf

            <div class="form-group">
                <label for="text">Введите текст для озвучки:</label>
                <textarea name="text" id="text" required placeholder="Введите текст на русском языке...">{{ old('text') }}</textarea>
            </div>

            <div class="form-group">
                <label for="speed">Скорость речи:</label>
                <select name="speed" id="speed">
                    <option value="0">Нормальная</option>
                    <option value="1" selected>Быстрее (+10%)</option>
                    <option value="2">Быстро (+20%)</option>
                    <option value="3">Очень быстро (+30%)</option>
                    <option value="4">Максимально быстро (+40%)</option>
                </select>
            </div>

            <button type="submit">Сгенерировать</button>
        </form>

        @if(session('audio_file'))
            <div class="audio-player">
                <strong>Результат:</strong>
                <audio controls>
                    <source src="{{ asset(session('audio_file')) }}" type="audio/mpeg">
                    Ваш браузер не поддерживает аудио.
                </audio>
                <p style="margin-top: 5px; font-size: 12px;">
                    <a href="{{ asset(session('audio_file')) }}" download>Скачать аудио</a>
                </p>
            </div>
        @endif
        </div>
    </div>
</body>
</html>
