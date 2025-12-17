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

    Формат: {са}сори → сА-сори (заглавная гласная с дефисом)

    Простой подход: EdgeTTS часто делает ударение на заглавные буквы,
    а дефис добавляет небольшую паузу для разделения.
    """

    # Паттерн для поиска {текст}остаток
    pattern = r'\{([^\}]+)\}(\S*)'

    def add_stress(match):
        stressed_part = match.group(1)  # "са"
        rest = match.group(2)           # "сори"

        # Находим последнюю гласную в ударной части и делаем её заглавной
        vowels = 'аеёиоуыэюя'
        vowels_upper = 'АЕЁИОУЫЭЮЯ'

        result = stressed_part
        for i in range(len(stressed_part) - 1, -1, -1):
            if stressed_part[i] in vowels:
                # Делаем гласную заглавной
                result = stressed_part[:i] + stressed_part[i].upper() + stressed_part[i+1:]
                break

        # Добавляем дефис для паузы если есть остаток слова
        if rest:
            return result + '-' + rest
        return result

    # Применяем замену
    processed = re.sub(pattern, add_stress, text)

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
