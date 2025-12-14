#!/usr/bin/env python3
"""
TTS Worker для Laravel - принимает текст, возвращает аудио
"""

import sys
import asyncio
import edge_tts
import json

async def generate_tts(text, rate="+10%"):
    """Генерация TTS через EdgeTTS"""
    output_file = f"output_{hash(text)}.mp3"

    communicate = edge_tts.Communicate(text, voice="ru-RU-DmitryNeural", rate=rate)
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
