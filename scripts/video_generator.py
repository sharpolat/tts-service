#!/usr/bin/env python3
"""
Генератор видео из картинок и аудио сегментов
Использует: pydub для работы с аудио, moviepy для создания видео
"""

import sys
import json
import os
import base64
from pydub import AudioSegment
from pydub.silence import split_on_silence

# MoviePy 2.x imports
try:
    from moviepy import ImageClip, AudioFileClip, concatenate_videoclips
except ImportError:
    # Fallback для старой версии
    from moviepy.editor import ImageClip, AudioFileClip, concatenate_videoclips

import requests
from pathlib import Path


def split_audio_by_sentences(audio_file, num_segments):
    """
    Разрезает аудио на N равных частей (по количеству сегментов)
    """
    audio = AudioSegment.from_file(audio_file)
    total_duration = len(audio)
    segment_duration = total_duration / num_segments

    segments = []
    for i in range(num_segments):
        start = int(i * segment_duration)
        end = int((i + 1) * segment_duration) if i < num_segments - 1 else total_duration
        segment = audio[start:end]
        segments.append({
            'audio': segment,
            'start_time': start / 1000.0,
            'duration': (end - start) / 1000.0
        })

    return segments


def download_image(url, output_path):
    """
    Скачивает картинку по URL
    """
    try:
        response = requests.get(url, timeout=10)
        response.raise_for_status()

        with open(output_path, 'wb') as f:
            f.write(response.content)

        return True
    except Exception as e:
        print(f"Ошибка загрузки картинки {url}: {str(e)}", file=sys.stderr)
        return False


def create_video(segments_data, audio_file, output_file):
    """
    Создает видео из сегментов
    segments_data: [{'image_url': '...', 'text': '...', 'order': 0}, ...]
    """

    # Создаем временную директорию для скачанных картинок и аудио сегментов
    temp_dir = Path('temp_video')
    temp_dir.mkdir(exist_ok=True)

    # Разрезаем аудио на части
    audio_segments = split_audio_by_sentences(audio_file, len(segments_data))

    # Скачиваем картинки и создаем видео клипы
    video_clips = []

    for i, (segment_info, audio_seg) in enumerate(zip(segments_data, audio_segments)):
        # Скачиваем картинку
        image_path = temp_dir / f"image_{i}.jpg"

        if not download_image(segment_info['image_url'], image_path):
            # Если не удалось скачать, используем черный экран
            print(f"Используем черный экран для сегмента {i}")
            # TODO: создать черное изображение
            continue

        # Сохраняем аудио сегмент
        audio_segment_path = temp_dir / f"audio_{i}.mp3"
        audio_seg['audio'].export(audio_segment_path, format='mp3')

        # Создаем видео клип
        duration = audio_seg['duration']

        image_clip = ImageClip(str(image_path)).set_duration(duration)
        audio_clip = AudioFileClip(str(audio_segment_path))

        video_clip = image_clip.set_audio(audio_clip)
        video_clips.append(video_clip)

    # Объединяем все клипы
    if video_clips:
        final_video = concatenate_videoclips(video_clips, method="compose")

        # Экспортируем финальное видео
        final_video.write_videofile(
            output_file,
            fps=24,
            codec='libx264',
            audio_codec='aac',
            temp_audiofile='temp-audio.m4a',
            remove_temp=True
        )

        # Закрываем клипы
        for clip in video_clips:
            clip.close()
        final_video.close()

        return True

    return False


def create_video_from_segments(data):
    """
    Создает видео из готовых аудио сегментов и картинок
    data: {
        "segments": [
            {
                "image_url": "...",
                "audio_file": "/path/to/segment.mp3",
                "duration": 5.5,
                "order": 0
            }
        ],
        "output_file": "/path/to/output.mp4"
    }
    """
    segments = data['segments']
    output_file = data['output_file']

    # Создаем временную директорию для скачанных картинок
    temp_dir = Path('/tmp/video_temp')
    temp_dir.mkdir(exist_ok=True)

    video_clips = []

    for segment in segments:
        # Скачиваем или сохраняем картинку
        image_path = temp_dir / f"image_{segment['order']}.jpg"
        image_url = segment['image_url']

        # Проверяем если это base64
        if image_url.startswith('data:image'):
            try:
                # Извлекаем base64 данные
                header, encoded = image_url.split(',', 1)
                image_data = base64.b64decode(encoded)

                with open(image_path, 'wb') as f:
                    f.write(image_data)
            except Exception as e:
                print(f"Ошибка сохранения base64 картинки {segment['order']}: {e}", file=sys.stderr)
                continue
        else:
            # Обычный URL - скачиваем
            if not download_image(image_url, image_path):
                print(f"Пропускаем сегмент {segment['order']} - не удалось скачать картинку")
                continue

        # Создаем видео клип из картинки и аудио
        try:
            # MoviePy 2.x API: используем with_duration вместо set_duration
            image_clip = ImageClip(str(image_path)).with_duration(segment['duration'])
            audio_clip = AudioFileClip(segment['audio_file'])

            # Resize to target resolution if provided
            target_width = data.get('width', 1280)
            target_height = data.get('height', 720)
            image_clip = image_clip.resized(width=target_width, height=target_height)

            video_clip = image_clip.with_audio(audio_clip)
            video_clips.append(video_clip)
        except Exception as e:
            print(f"Ошибка создания клипа {segment['order']}: {e}", file=sys.stderr)
            continue

    if not video_clips:
        return False

    # Объединяем все клипы
    final_video = concatenate_videoclips(video_clips, method="compose")

    # Экспортируем финальное видео
    final_video.write_videofile(
        output_file,
        fps=24,
        codec='libx264',
        audio_codec='aac',
        temp_audiofile='/tmp/temp-audio.m4a',
        remove_temp=True
    )

    # Закрываем клипы
    for clip in video_clips:
        clip.close()
    final_video.close()

    return True


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "Usage: video_generator.py <json_data>"}))
        sys.exit(1)

    json_data = sys.argv[1]

    try:
        data = json.loads(json_data)

        success = create_video_from_segments(data)

        if success:
            print(json.dumps({"success": True, "file": data['output_file']}))
        else:
            print(json.dumps({"error": "Failed to create video"}))
            sys.exit(1)

    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)
