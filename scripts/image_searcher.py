#!/usr/bin/env python3
"""
Автоматический поиск картинок для видео сегментов
Использует Google Images (лучший поиск, бесплатно)
"""

import sys
import json
import requests
from urllib.parse import quote, urlencode
from bs4 import BeautifulSoup
import hashlib
import re
import time


def search_google_images(query, max_results=10):
    """
    Поиск через Google Images - лучший поиск, бесплатно
    """
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language': 'en-US,en;q=0.5',
        }

        params = {
            'q': query,
            'tbm': 'isch',  # image search
            'hl': 'en',
            'gl': 'us',
        }

        url = f"https://www.google.com/search?{urlencode(params)}"

        response = requests.get(url, headers=headers, timeout=15)
        response.raise_for_status()

        images = []

        # Ищем JSON данные в скриптах
        matches = re.findall(r'\["(https://[^"]+?)",(\d+),(\d+)\]', response.text)

        for match in matches[:max_results * 3]:  # Берём с запасом
            img_url = match[0]

            # Фильтруем Google служебные картинки
            if 'gstatic' in img_url or 'google' in img_url:
                continue

            # Убираем экранирование
            img_url = img_url.replace('\\u003d', '=').replace('\\u0026', '&')

            images.append({
                'url': img_url,
                'thumb': img_url,
                'author': 'Google Images',
                'source': 'google'
            })

            if len(images) >= max_results:
                break

        return images

    except Exception as e:
        print(f"Ошибка поиска Google: {str(e)}", file=sys.stderr)
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

    # Google Images - лучший поиск, самая большая база
    google_results = search_google_images(query, per_source)
    all_images.extend(google_results)

    return all_images


def search_for_segments(segments, pexels_key=None, pixabay_key=None):
    """
    Ищет картинки для каждого сегмента
    segments: [{'text': '...', 'search_query': '...', 'order': 0}, ...]
    """
    results = []
    used_urls = set()  # Отслеживаем использованные URL для уникальности

    for i, segment in enumerate(segments):
        search_query = segment.get('search_query', '')

        if not search_query:
            # Если нет поискового запроса, используем первые слова текста
            words = segment['text'].split()[:3]
            search_query = ' '.join(words)

        # Ищем картинки
        images = search_images(search_query, pexels_key, pixabay_key, per_source=10)

        # Выбираем первую уникальную картинку
        selected_image = None
        for img in images:
            if img['url'] not in used_urls:
                selected_image = img['url']
                used_urls.add(selected_image)
                break

        segment_result = {
            'order': segment.get('order', 0),
            'text': segment['text'],
            'search_query': search_query,
            'images': images[:3],  # Топ-3 для выбора
            'selected_image': selected_image
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
