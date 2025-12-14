<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TTS Генератор</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        h1 {
            color: #333;
            margin-bottom: 30px;
            font-size: 32px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
        }

        textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            min-height: 200px;
            resize: vertical;
            font-family: inherit;
        }

        textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            background: white;
        }

        select:focus {
            outline: none;
            border-color: #667eea;
        }

        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        button:hover {
            transform: translateY(-2px);
        }

        button:active {
            transform: translateY(0);
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .audio-player {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        audio {
            width: 100%;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎙️ TTS Генератор</h1>

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

            <button type="submit">🎤 Сгенерировать аудио</button>
        </form>

        @if(session('audio_file'))
            <div class="audio-player">
                <h3>✅ Результат:</h3>
                <audio controls>
                    <source src="{{ asset(session('audio_file')) }}" type="audio/mpeg">
                    Ваш браузер не поддерживает аудио.
                </audio>
                <p style="margin-top: 10px;">
                    <a href="{{ asset(session('audio_file')) }}" download style="color: #667eea; text-decoration: none;">
                        📥 Скачать аудио
                    </a>
                </p>
            </div>
        @endif
    </div>
</body>
</html>
