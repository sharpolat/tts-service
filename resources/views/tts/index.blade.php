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

        .history {
            margin-top: 20px;
            border: 2px inset #fff;
            background: #fff;
            padding: 10px;
        }

        .history-item {
            padding: 8px;
            margin-bottom: 8px;
            border: 1px solid #ccc;
            background: #f0f0f0;
        }

        .history-item:hover {
            background: #e0e0e0;
        }

        .history-text {
            font-size: 11px;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .history-actions {
            font-size: 11px;
            display: flex;
            gap: 10px;
        }

        .btn-small {
            padding: 2px 8px;
            font-size: 11px;
        }

        .version-item {
            padding: 5px 8px;
            margin: 5px 0 5px 20px;
            border: 1px solid #999;
            background: #e8e8e8;
            font-size: 10px;
        }

        .version-toggle {
            cursor: pointer;
            color: #0000ff;
            text-decoration: underline;
            font-size: 10px;
            margin-left: 10px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #c0c0c0;
            border: 2px outset #fff;
            padding: 3px;
            max-width: 500px;
            width: 90%;
        }

        .modal-body {
            padding: 10px;
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

        <p style="margin-bottom: 15px; font-size: 11px;">
            <a href="{{ route('video.index') }}">📹 Мои видео проекты</a>
        </p>

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

        @if($history->count() > 0)
            <div class="history">
                <strong>История (последние 10):</strong>
                @foreach($history as $item)
                    <div class="history-item">
                        <div class="history-text" title="{{ $item->text }}">
                            v{{ $item->version }} - {{ Str::limit($item->text, 80) }}
                            @if($item->versions->count() > 0)
                                <span class="version-toggle" onclick="toggleVersions({{ $item->id }})">
                                    [{{ $item->versions->count() }} версий]
                                </span>
                            @endif
                        </div>
                        <div class="history-actions">
                            <span>{{ $item->created_at->format('d.m.Y H:i') }}</span>
                            <a href="{{ asset($item->audio_file) }}" target="_blank">Прослушать</a>
                            <a href="{{ asset($item->audio_file) }}" download>Скачать</a>
                            <a href="{{ route('video.create', $item->id) }}">Создать видео</a>
                            <a href="#" onclick="openEditModal({{ $item->id }}, '{{ addslashes($item->text) }}', '{{ substr($item->speed, 1, -1) }}'); return false;">Редактировать</a>
                            <form action="{{ route('tts.delete', $item->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Удалить запись?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-small">Удалить</button>
                            </form>
                        </div>

                        @if($item->versions->count() > 0)
                            <div id="versions-{{ $item->id }}" style="display:none;">
                                @foreach($item->versions as $version)
                                    <div class="version-item">
                                        <div class="history-text" title="{{ $version->text }}">
                                            v{{ $version->version }} - {{ Str::limit($version->text, 70) }}
                                        </div>
                                        <div class="history-actions">
                                            <span>{{ $version->created_at->format('d.m.Y H:i') }}</span>
                                            <a href="{{ asset($version->audio_file) }}" target="_blank">Прослушать</a>
                                            <a href="{{ asset($version->audio_file) }}" download>Скачать</a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <!-- Пагинация -->
            <div style="margin-top: 20px; padding: 10px; text-align: center;">
                {{ $history->links() }}
            </div>
        @endif
        </div>
    </div>

    <!-- Modal для редактирования -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="title-bar">
                <span>Редактирование</span>
                <span style="cursor:pointer;" onclick="closeEditModal()">X</span>
            </div>
            <div class="modal-body">
                <form id="editForm" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="form-group">
                        <label for="edit_text">Текст:</label>
                        <textarea name="text" id="edit_text" required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="edit_speed">Скорость:</label>
                        <select name="speed" id="edit_speed">
                            <option value="0">Нормальная</option>
                            <option value="1">Быстрее (+10%)</option>
                            <option value="2">Быстро (+20%)</option>
                            <option value="3">Очень быстро (+30%)</option>
                            <option value="4">Максимально быстро (+40%)</option>
                        </select>
                    </div>

                    <button type="submit">Сохранить</button>
                    <button type="button" onclick="closeEditModal()">Отмена</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openEditModal(id, text, speed) {
            document.getElementById('edit_text').value = text;
            const speedValue = speed === '0' ? '0' : String(parseInt(speed) / 10);
            document.getElementById('edit_speed').value = speedValue;
            document.getElementById('editForm').action = '/update/' + id;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function toggleVersions(id) {
            const versionsDiv = document.getElementById('versions-' + id);
            if (versionsDiv.style.display === 'none') {
                versionsDiv.style.display = 'block';
            } else {
                versionsDiv.style.display = 'none';
            }
        }

        // Закрытие по клику вне модального окна
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>
