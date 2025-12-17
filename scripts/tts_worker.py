#!/usr/bin/env python3
"""
TTS Worker для Laravel - принимает текст, возвращает аудио
"""

import sys
import asyncio
import edge_tts
import json
import re

def process_stress_marks(text):
    """
    Обрабатывает пользовательские маркеры ударений в тексте

    Формат: {са}сори → <emphasis level="strong">са</emphasis>сори

    EdgeTTS использует SSML теги для управления произношением.
    Тег <emphasis> усиливает ударение на нужном слоге.
    """

    # Паттерн для поиска {текст}
    pattern = r'\{([^\}]+)\}'

    def add_emphasis(match):
        stressed_text = match.group(1)
        return f'<emphasis level="strong">{stressed_text}</emphasis>'

    # Применяем замену
    processed = re.sub(pattern, add_emphasis, text)

    # Оборачиваем весь текст в SSML если есть теги emphasis
    if '<emphasis' in processed:
        processed = f'<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="ru-RU">{processed}</speak>'

    return processed

async def generate_tts(text, rate="+10%"):
    """Генерация TTS через EdgeTTS"""

    # Обрабатываем маркеры ударений
    processed_text = process_stress_marks(text)

    output_file = f"output_{hash(text)}.mp3"

    communicate = edge_tts.Communicate(processed_text, voice="ru-RU-DmitryNeural", rate=rate)
    await communicate.save(output_file)

    return output_file

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No text provided"}))
        sys.exit(1)

    text = sys.argv[1]
    rate = sys.argv[2] if len(sys.argv) > 2 else "+10%"

    try:
        output_file = asyncio.run(generate_tts(text, rate))
        print(json.dumps({"success": True, "file": output_file}))
    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)
