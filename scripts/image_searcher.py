#!/usr/bin/env python3
"""
Автоматический поиск картинок для видео сегментов
Использует полностью бесплатные источники без API ключей:
1. Wikimedia Commons (Wikipedia фото)
2. Lorem Picsum (случайные фото)
"""

import sys
import json
import requests
from urllib.parse import quote
import hashlib


def search_wikimedia(query, per_page=5):
    """
    Поиск через Wikimedia Commons (Wikipedia) - полностью бесплатно
    """
    try:
        url = "https://commons.wikimedia.org/w/api.php"

        params = {
            'action': 'query',
            'format': 'json',
            'generator': 'search',
            'gsrsearch': query,
            'gsrnamespace': '6',  # File namespace
            'gsrlimit': per_page,
            'prop': 'imageinfo',
            'iiprop': 'url|size',
            'iiurlwidth': '800'
        }

        response = requests.get(url, params=params, timeout=10)
        response.raise_for_status()

        data = response.json()

        images = []
        if 'query' in data and 'pages' in data['query']:
            for page_id, page in data['query']['pages'].items():
                if 'imageinfo' in page and page['imageinfo']:
                    img_info = page['imageinfo'][0]
                    images.append({
                        'url': img_info.get('url', img_info.get('thumburl', '')),
                        'thumb': img_info.get('thumburl', img_info.get('url', '')),
                        'author': 'Wikimedia Commons',
                        'source': 'wikimedia'
                    })

        return images

    except Exception as e:
        print(f"Ошибка поиска Wikimedia: {str(e)}", file=sys.stderr)
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


def search_images(query, pexels_key=None, pixabay_key=None, per_source=5):
    """
    Комбинированный поиск - приоритет бесплатным источникам
    """
    all_images = []

    # 1. Wikimedia Commons (Wikipedia) - полностью бесплатно, без лимитов
    wikimedia_results = search_wikimedia(query, per_source)
    all_images.extend(wikimedia_results)

    # 2. Lorem Picsum - если Wikimedia не нашла достаточно
    if len(all_images) < per_source:
        picsum_results = get_picsum_images(query, per_source - len(all_images))
        all_images.extend(picsum_results)

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
