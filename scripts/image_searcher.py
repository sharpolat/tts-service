#!/usr/bin/env python3
"""
Автоматический поиск картинок для видео сегментов
Использует несколько источников:
1. Unsplash API (бесплатно)
2. Pexels API (бесплатно с ключом)
3. Pixabay API (бесплатно с ключом)
"""

import sys
import json
import requests
from urllib.parse import quote


def search_unsplash(query, per_page=3):
    """
    Поиск через Unsplash (без API ключа, через публичный endpoint)
    """
    try:
        # Используем публичный поиск Unsplash
        url = f"https://unsplash.com/napi/search/photos?query={quote(query)}&per_page={per_page}"

        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        }

        response = requests.get(url, headers=headers, timeout=10)
        response.raise_for_status()

        data = response.json()

        images = []
        if 'results' in data:
            for item in data['results'][:per_page]:
                images.append({
                    'url': item['urls']['regular'],
                    'thumb': item['urls']['small'],
                    'author': item['user']['name'],
                    'source': 'unsplash',
                    'download_url': item['links']['download']
                })

        return images

    except Exception as e:
        print(f"Ошибка поиска Unsplash: {str(e)}", file=sys.stderr)
        return []


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


def search_images(query, pexels_key=None, pixabay_key=None, per_source=3):
    """
    Комбинированный поиск по всем источникам
    """
    all_images = []

    # Поиск в Unsplash (всегда доступен)
    unsplash_results = search_unsplash(query, per_source)
    all_images.extend(unsplash_results)

    # Поиск в Pexels если есть ключ
    if pexels_key:
        pexels_results = search_pexels(query, pexels_key, per_source)
        all_images.extend(pexels_results)

    # Поиск в Pixabay если есть ключ
    if pixabay_key:
        pixabay_results = search_pixabay(query, pixabay_key, per_source)
        all_images.extend(pixabay_results)

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
