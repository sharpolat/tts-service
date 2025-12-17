#!/usr/bin/env python3
"""
TTS Worker для Laravel - принимает текст, возвращает аудио
"""

import sys
import asyncio
import edge_tts
import json
import re
from pydub import AudioSegment
import os
import tempfile

async def generate_tts_segment(text, rate="+10%", pitch="+0Hz", volume="+0%"):
    """Генерация одного сегмента TTS"""
    temp_file = tempfile.NamedTemporaryFile(delete=False, suffix='.mp3').name

    # Формируем строку с параметрами prosody
    prosody_parts = []
    if rate != "+0%":
        prosody_parts.append(f"rate={rate}")
    if pitch != "+0Hz":
        prosody_parts.append(f"pitch={pitch}")
    if volume != "+0%":
        prosody_parts.append(f"volume={volume}")

    communicate = edge_tts.Communicate(text, voice="ru-RU-DmitryNeural")

    # Применяем параметры через SubMaker если нужно
    if prosody_parts:
        communicate = edge_tts.Communicate(
            text,
            voice="ru-RU-DmitryNeural",
            rate=rate,
            volume=volume,
            pitch=pitch
        )

    await communicate.save(temp_file)
    return temp_file

def parse_stress_text(text):
    """
    Разбивает текст на части с учётом ударений

    Формат: {са}сори → [("са", True), ("сори", False)]
    True = ударная часть, False = обычная часть
    """
    parts = []
    pattern = r'\{([^\}]+)\}'

    last_end = 0
    for match in re.finditer(pattern, text):
        # Текст до скобок
        if match.start() > last_end:
            before = text[last_end:match.start()]
            if before.strip():
                parts.append((before, False))

        # Ударная часть в скобках
        parts.append((match.group(1), True))
        last_end = match.end()

    # Остаток текста после последних скобок
    if last_end < len(text):
        rest = text[last_end:]
        if rest.strip():
            parts.append((rest, False))

    # Если нет скобок, весь текст обычный
    if not parts:
        parts = [(text, False)]

    return parts

async def generate_tts(text, rate="+10%"):
    """Генерация TTS через EdgeTTS с поддержкой ударений"""

    # Проверяем наличие маркеров ударений
    if '{' not in text:
        # Нет ударений - обычная генерация
        output_file = f"output_{hash(text)}.mp3"
        communicate = edge_tts.Communicate(text, voice="ru-RU-DmitryNeural", rate=rate)
        await communicate.save(output_file)
        return output_file

    # Есть ударения - разбиваем и склеиваем
    parts = parse_stress_text(text)
    temp_files = []

    try:
        for part_text, is_stressed in parts:
            if is_stressed:
                # Ударный слог - выше pitch и громче
                temp_file = await generate_tts_segment(
                    part_text,
                    rate=rate,
                    pitch="+50Hz",  # Выше тон
                    volume="+20%"   # Громче
                )
            else:
                # Обычный текст
                temp_file = await generate_tts_segment(part_text, rate=rate)

            temp_files.append(temp_file)

        # Склеиваем все части
        combined = AudioSegment.empty()
        for temp_file in temp_files:
            audio = AudioSegment.from_mp3(temp_file)
            combined += audio

        # Сохраняем результат
        output_file = f"output_{hash(text)}.mp3"
        combined.export(output_file, format="mp3")

        # Удаляем временные файлы
        for temp_file in temp_files:
            os.unlink(temp_file)

        return output_file

    except Exception as e:
        # Очищаем временные файлы при ошибке
        for temp_file in temp_files:
            if os.path.exists(temp_file):
                os.unlink(temp_file)
        raise e

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
