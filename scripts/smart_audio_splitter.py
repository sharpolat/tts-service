#!/usr/bin/env python3
"""
Умное разрезание аудио по паузам и смысловым блокам
"""

import sys
import json
from pydub import AudioSegment
from pydub.silence import detect_silence, split_on_silence


def split_audio_smart(audio_file, num_segments=None, min_silence_len=500, silence_thresh=-40):
    """
    Умное разрезание аудио:
    1. Сначала определяет паузы в речи
    2. Группирует их в N сегментов примерно равной длины
    """

    audio = AudioSegment.from_file(audio_file)
    total_duration = len(audio)

    # Находим все паузы
    silences = detect_silence(
        audio,
        min_silence_len=min_silence_len,
        silence_thresh=silence_thresh
    )

    if not silences or not num_segments:
        # Если нет пауз, делим равномерно
        return split_equally(audio, num_segments or 5)

    # Группируем сегменты по паузам
    segments = []
    segment_boundaries = [0]  # Начало первого сегмента

    # Определяем границы сегментов по паузам
    if num_segments and len(silences) >= num_segments - 1:
        # Выбираем равномерно распределенные паузы
        step = len(silences) // (num_segments - 1)
        selected_silences = [silences[i * step] for i in range(num_segments - 1)]

        for silence_start, silence_end in selected_silences:
            # Граница сегмента - середина паузы
            boundary = (silence_start + silence_end) // 2
            segment_boundaries.append(boundary)

    else:
        # Используем все паузы
        for silence_start, silence_end in silences:
            boundary = (silence_start + silence_end) // 2
            segment_boundaries.append(boundary)

    segment_boundaries.append(total_duration)

    # Создаем сегменты
    for i in range(len(segment_boundaries) - 1):
        start = segment_boundaries[i]
        end = segment_boundaries[i + 1]

        segment_audio = audio[start:end]

        segments.append({
            'audio': segment_audio,
            'start_time': start / 1000.0,
            'end_time': end / 1000.0,
            'duration': (end - start) / 1000.0,
            'order': i
        })

    return segments


def split_equally(audio, num_segments):
    """
    Простое равномерное разделение
    """
    total_duration = len(audio)
    segment_duration = total_duration / num_segments

    segments = []
    for i in range(num_segments):
        start = int(i * segment_duration)
        end = int((i + 1) * segment_duration) if i < num_segments - 1 else total_duration

        segment_audio = audio[start:end]

        segments.append({
            'audio': segment_audio,
            'start_time': start / 1000.0,
            'end_time': end / 1000.0,
            'duration': (end - start) / 1000.0,
            'order': i
        })

    return segments


def export_segments(segments, output_dir):
    """
    Экспортирует сегменты в отдельные файлы
    """
    import os
    os.makedirs(output_dir, exist_ok=True)

    segment_files = []

    for segment in segments:
        filename = f"segment_{segment['order']:03d}.mp3"
        filepath = os.path.join(output_dir, filename)

        segment['audio'].export(filepath, format='mp3')

        segment_files.append({
            'file': filepath,
            'start_time': segment['start_time'],
            'end_time': segment['end_time'],
            'duration': segment['duration'],
            'order': segment['order']
        })

    return segment_files


if __name__ == "__main__":
    if len(sys.argv) < 3:
        print(json.dumps({"error": "Usage: smart_audio_splitter.py <audio_file> <num_segments> [output_dir]"}))
        sys.exit(1)

    audio_file = sys.argv[1]
    num_segments = int(sys.argv[2])
    output_dir = sys.argv[3] if len(sys.argv) > 3 else "audio_segments"

    try:
        segments = split_audio_smart(audio_file, num_segments)
        segment_files = export_segments(segments, output_dir)

        print(json.dumps({
            "success": True,
            "segments": segment_files
        }))

    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)
