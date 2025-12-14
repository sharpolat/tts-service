#!/usr/bin/env python3
"""
Автоматический поиск картинок для видео сегментов
Использует полностью бесплатные источники без API ключей:
1. DuckDuckGo Image Search (через библиотеку duckduckgo-search)
"""

import sys
import json
import requests
from urllib.parse import quote, unquote
import hashlib
import re

try:
    from duckduckgo_search import DDGS
    DDGS_AVAILABLE = True
except ImportError:
    DDGS_AVAILABLE = False
    print("Warning: duckduckgo-search не установлена. Установите: pip install duckduckgo-search", file=sys.stderr)


def search_duckduckgo(query, max_results=10):
    """
    Поиск через DuckDuckGo Images - полностью бесплатно, без лимитов
    """
    if not DDGS_AVAILABLE:
        return []

    try:
        with DDGS() as ddgs:
            results = list(ddgs.images(
                keywords=query,
                max_results=max_results,
                safesearch='off'
            ))

            images = []
            for item in results:
                images.append({
                    'url': item.get('image', ''),
                    'thumb': item.get('thumbnail', ''),
                    'author': item.get('source', 'DuckDuckGo'),
                    'source': 'duckduckgo'
                })

            return images

    except Exception as e:
        print(f"Ошибка поиска DuckDuckGo: {str(e)}", file=sys.stderr)
        return []


def get_picsum_images(query, count=3):
    """
    Генерирует стабильные случайные картинки на основе запроса
    Lorem Picsum - полностью бесплатно, без лимитов
    """
    images = []

    # Генерируем seed на основе запроса для стабильности
    seed = int(hashlib.md5(query.encode()).hexdigest(), 16) % 1000

    for i in range(count):
        image_id = (seed + i * 17) % 1000  # Разные ID для разных картинок

        images.append({
            'url': f'https://picsum.photos/seed/{image_id}/1920/1080',
            'thumb': f'https://picsum.photos/seed/{image_id}/400/300',
            'author': 'Lorem Picsum',
            'source': 'picsum'
        })

    return images


def search_pexels(query, api_key, per_page=3):
    """
    Поиск через Pexels API
    Получить ключ: https://www.pexels.com/api/
    """
    if not api_key:
        return []

    try:
        url = f"https://api.pexels.com/v1/search?query={quote(query)}&per_page={per_page}"

        headers = {
            'Authorization': api_key
        }

        response = requests.get(url, headers=headers, timeout=10)
        response.raise_for_status()

        data = response.json()

        images = []
        if 'photos' in data:
            for photo in data['photos'][:per_page]:
                images.append({
                    'url': photo['src']['large'],
                    'thumb': photo['src']['medium'],
                    'author': photo['photographer'],
                    'source': 'pexels'
                })

        return images

    except Exception as e:
        print(f"Ошибка поиска Pexels: {str(e)}", file=sys.stderr)
        return []


def search_pixabay(query, api_key, per_page=3):
    """
    Поиск через Pixabay API
    Получить ключ: https://pixabay.com/api/docs/
    """
    if not api_key:
        return []

    try:
        url = f"https://pixabay.com/api/?key={api_key}&q={quote(query)}&per_page={per_page}&image_type=photo"

        response = requests.get(url, timeout=10)
        response.raise_for_status()

        data = response.json()

        images = []
        if 'hits' in data:
            for hit in data['hits'][:per_page]:
                images.append({
                    'url': hit['largeImageURL'],
                    'thumb': hit['previewURL'],
                    'author': hit.get('user', 'Unknown'),
                    'source': 'pixabay'
                })

        return images

    except Exception as e:
        print(f"Ошибка поиска Pixabay: {str(e)}", file=sys.stderr)
        return []


def search_images(query, pexels_key=None, pixabay_key=None, per_source=10):
    """
    Поиск только релевантных картинок (без случайных фото)
    """
    all_images = []

    # DuckDuckGo - полностью бесплатно, без лимитов
    ddg_results = search_duckduckgo(query, per_source)
    all_images.extend(ddg_results)

    return all_images


def search_for_segments(segments, pexels_key=None, pixabay_key=None):
    """
    Ищет картинки для каждого сегмента
    segments: [{'text': '...', 'search_query': '...', 'order': 0}, ...]
    """
    results = []

    for segment in segments:
        search_query = segment.get('search_query', '')

        if not search_query:
            # Если нет поискового запроса, используем первые слова текста
            words = segment['text'].split()[:3]
            search_query = ' '.join(words)

        images = search_images(search_query, pexels_key, pixabay_key, per_source=5)

        segment_result = {
            'order': segment.get('order', 0),
            'text': segment['text'],
            'search_query': search_query,
            'images': images,
            'selected_image': images[0]['url'] if images else None
        }

        results.append(segment_result)

    return results


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "Usage: image_searcher.py <segments_json> [pexels_key] [pixabay_key]"}))
        sys.exit(1)

    segments_json = sys.argv[1]
    pexels_key = sys.argv[2] if len(sys.argv) > 2 else None
    pixabay_key = sys.argv[3] if len(sys.argv) > 3 else None

    try:
        segments = json.loads(segments_json)

        results = search_for_segments(segments, pexels_key, pixabay_key)

        print(json.dumps({
            "success": True,
            "results": results
        }))

    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)
