#!/usr/bin/env python3
"""
Автоматический поиск картинок для видео сегментов
Использует Bing Image Search (полностью бесплатный через scraping)
"""

import sys
import json
import requests
from urllib.parse import quote, urlencode
from bs4 import BeautifulSoup
import hashlib
import re
import time


def search_bing_images(query, max_results=10):
    """
    Поиск через Bing Images - полностью бесплатно через scraping
    """
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language': 'en-US,en;q=0.5',
        }

        params = {
            'q': query,
            'first': 1,
            'count': max_results,
            'qft': '+filterui:photo-photo',  # только фото
        }

        url = f"https://www.bing.com/images/search?{urlencode(params)}"

        response = requests.get(url, headers=headers, timeout=15)
        response.raise_for_status()

        soup = BeautifulSoup(response.text, 'html.parser')

        images = []

        # Ищем JSON с данными картинок
        for a_tag in soup.find_all('a', class_='iusc'):
            if len(images) >= max_results:
                break

            m_attr = a_tag.get('m')
            if m_attr:
                try:
                    data = json.loads(m_attr)
                    images.append({
                        'url': data.get('murl', ''),
                        'thumb': data.get('turl', ''),
                        'author': 'Bing Images',
                        'source': 'bing'
                    })
                except:
                    continue

        return images

    except Exception as e:
        print(f"Ошибка поиска Bing: {str(e)}", file=sys.stderr)
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

    # Bing Images - полностью бесплатно, без лимитов
    bing_results = search_bing_images(query, per_source)
    all_images.extend(bing_results)

    return all_images


def search_for_segments(segments, pexels_key=None, pixabay_key=None):
    """
    Ищет картинки для каждого сегмента
    segments: [{'text': '...', 'search_query': '...', 'order': 0}, ...]
    """
    results = []
    used_urls = set()  # Отслеживаем использованные URL для разнообразия

    for i, segment in enumerate(segments):
        search_query = segment.get('search_query', '')

        if not search_query:
            # Если нет поискового запроса, используем первые слова текста
            words = segment['text'].split()[:3]
            search_query = ' '.join(words)

        # Ищем больше картинок чтобы было из чего выбрать
        images = search_images(search_query, pexels_key, pixabay_key, per_source=15)

        # Фильтруем уже использованные картинки
        unique_images = []
        for img in images:
            if img['url'] not in used_urls:
                unique_images.append(img)
                if len(unique_images) >= 5:  # Оставляем топ-5 уникальных
                    break

        # Выбираем картинку с индексом, зависящим от позиции сегмента
        # Это даст разные картинки для разных сегментов
        selected_image = None
        if unique_images:
            # Берём разные позиции: 0, 1, 2, 0, 1, 2...
            index = i % min(3, len(unique_images))
            selected_image = unique_images[index]['url']
            used_urls.add(selected_image)

        segment_result = {
            'order': segment.get('order', 0),
            'text': segment['text'],
            'search_query': search_query,
            'images': unique_images,
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
