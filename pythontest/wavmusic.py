import numpy as np
import matplotlib.pyplot as plt
import wave
import struct

# サンプリングレート
sr = 44100

# ドレミファソラシド（C4〜C5）
freqs = [
    261.63, 293.66, 329.63, 349.23,
    392.00, 440.00, 493.88, 523.25
]

note_duration = 0.6
silence = 0.05

t = np.linspace(0, note_duration, int(sr * note_duration), endpoint=False)

def adsr_env(length, sr):
    env = np.ones(length)
    a = int(sr * 0.01)
    r = int(sr * 0.03)
    env[:a] = np.linspace(0, 1, a)
    env[-r:] = np.linspace(1, 0, r)
    return env

scale = np.array([], dtype=np.float32)

for f in freqs:
    wave_data = np.sin(2 * np.pi * f * t) * adsr_env(len(t), sr)
    scale = np.concatenate([scale, wave_data, np.zeros(int(sr * silence))])

# 正規化して16bit整数へ
scale_int = np.int16(scale / np.max(np.abs(scale)) * 32767)

# WAV 保存
file_path = "scale.wav"
with wave.open(file_path, 'wb') as wf:
    wf.setnchannels(1)
    wf.setsampwidth(2)
    wf.setframerate(sr)
    wf.writeframes(struct.pack('<' + 'h'*len(scale_int), *scale_int))

print("WAV を保存しました:", file_path)

# 波形の一部を描画
plt.figure(figsize=(10, 3))
plt.plot(scale[:3000])
plt.title("波形のズーム（冒頭部分）")
plt.xlabel("Samples")
plt.ylabel("Amplitude")
plt.show()
