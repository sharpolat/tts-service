#!/usr/bin/env python3
"""
AI анализатор текста для видео проектов
Использует локальную AI модель для:
1. Разбиения текста на смысловые блоки
2. Генерации поисковых запросов для картинок
3. Определения эмоционального тона каждого сегмента
"""

import sys
import json
import requests
import re


def analyze_with_ollama(text, model="qwen2.5:14b"):
    """
    Анализирует текст через Ollama API
    Возвращает структурированные сегменты с поисковыми запросами
    """

    prompt = f"""Проанализируй следующий текст и разбей его на смысловые сегменты для создания видео.

Для каждого сегмента создай:
1. Текст сегмента (1-2 предложения)
2. Поисковый запрос для картинки на АНГЛИЙСКОМ языке
3. Эмоциональный тон

ВАЖНЫЕ ПРАВИЛА для search_query:
- Запрос должен ТОЧНО описывать ВИЗУАЛЬНОЕ содержание сегмента
- Используй конкретные объекты, персонажей, места, действия
- НЕ используй абстрактные понятия (hatred, goal, purpose)
- Описывай то, что можно УВИДЕТЬ на фото
- Делай запросы РАЗНООБРАЗНЫМИ для каждого сегмента
- Каждый запрос должен быть УНИКАЛЬНЫМ

ПРИМЕРЫ:
❌ ПЛОХО: "itachi sasuke hatred" (hatred - абстрактное понятие)
✅ ХОРОШО: "itachi uchiha standing alone dark background"

❌ ПЛОХО: "naruto goal"
✅ ХОРОШО: "naruto training waterfall landscape"

❌ ПЛОХО: "tokyo night"
✅ ХОРОШО: "tokyo shibuya crossing night neon lights"

Верни ответ СТРОГО в JSON формате:
[
  {{
    "text": "текст сегмента",
    "search_query": "detailed visual english description",
    "tone": "neutral/happy/sad/dramatic/action",
    "order": 0
  }}
]

Текст для анализа:
{text}

Верни только JSON, без дополнительного текста."""

    try:
        response = requests.post(
            'http://localhost:11434/api/generate',
            json={
                'model': model,
                'prompt': prompt,
                'stream': False,
                'options': {
                    'temperature': 0.3,
                    'num_predict': 2000
                }
            },
            timeout=120
        )

        response.raise_for_status()
        result = response.json()

        # Извлекаем JSON из ответа
        response_text = result.get('response', '')

        # Ищем JSON в ответе
        json_match = re.search(r'\[.*\]', response_text, re.DOTALL)
        if json_match:
            segments = json.loads(json_match.group(0))
            return segments
        else:
            # Если не нашли JSON, пытаемся парсить весь ответ
            segments = json.loads(response_text)
            return segments

    except Exception as e:
        print(f"Ошибка при анализе с Ollama: {str(e)}", file=sys.stderr)
        # Fallback - простое разбиение по предложениям
        return fallback_split(text)


def fallback_split(text):
    """
    Простое разбиение на предложения если AI не работает
    """
    sentences = re.split(r'(?<=[.!?])\s+', text)
    segments = []

    for i, sentence in enumerate(sentences):
        if sentence.strip():
            # Простой поисковый запрос - первые 3 слова
            words = sentence.split()[:3]
            search_query = ' '.join(words)

            segments.append({
                'text': sentence.strip(),
                'search_query': search_query,
                'tone': 'neutral',
                'order': i
            })

    return segments


def search_images_unsplash(query, count=1):
    """
    Поиск картинок через Unsplash API (бесплатный)
    """
    # TODO: добавить API ключ Unsplash
    # Пока возвращаем заглушку
    return []


def search_images_pexels(query, count=1):
    """
    Поиск картинок через Pexels API (бесплатный)
    """
    # TODO: добавить API ключ Pexels
    # Пока возвращаем заглушку
    return []


def search_images_google(query, count=1):
    """
    Поиск картинок через Google Custom Search
    Требует API ключ
    """
    # TODO: реализовать поиск через Google
    return []


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "Usage: ai_video_analyzer.py <text> [model]"}))
        sys.exit(1)

    text = sys.argv[1]
    model = sys.argv[2] if len(sys.argv) > 2 else "llama3.2:latest"

    try:
        segments = analyze_with_ollama(text, model)

        # Добавляем автоматический поиск картинок (опционально)
        # for segment in segments:
        #     images = search_images_pexels(segment['search_query'])
        #     if images:
        #         segment['image_url'] = images[0]

        print(json.dumps({"success": True, "segments": segments}))

    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)
