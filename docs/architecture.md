# Suzuran アーキテクチャ

話者照合パイプラインを PHP で実装する。2つのWAVファイルを受け取って「同一話者か」を bool で返す `Suzuran::isMatch()` が最終出力。

## パイプライン

```
WAV path
   │
   ▼
WavConvertValue      RIFFチャンクを走査して int[] + sampleRate を返す (16-bit mono PCM のみ)
   │  int[], sampleRate
   ▼
Resampler            任意レート → 16 kHz、int16 を float[-1,1] に正規化
   │  float[]
   ▼
LogMelInterface      Log-mel スペクトログラム (n_mels=80, win=400, hop=160, n_fft=512)
   │                 実装は PhpLogMel / RustLogMel の2種類
   │  float[80][T]
   ▼
ReDimNetEmbedder     FFI で libonnxruntime をロードし ReDimNet2.onnx を推論
   │  float[192]
   ▼
CosineScorer         2つの埋め込みのコサイン類似度
   │  float [-1, 1]
   ▼
Verdict              score >= threshold か
   │  bool
   ▼
isMatch()
```

## 各クラスの責任

| クラス | 責任 |
| --- | --- |
| `WavConvertValue` | RIFF/WAVEヘッダを解析し、`data` チャンクから int16 サンプル配列を取り出す |
| `Resampler` | 線形補間で 16 kHz に落とし、int16 → float[-1,1] に正規化 |
| `Feature\LogMelInterface` | Log-mel 抽出の契約 |
| `Feature\PhpLogMel` | pure PHP 実装 (Hann窓, radix-2 FFT, 80-mel filterbank) |
| `Feature\RustLogMel` | `bin/logmel-rs` を `proc_open` で呼び、stdin にf32 waveform, stdout でmel行列を受け取る |
| `Embedding\EmbedderInterface` | 192次元 float埋め込みを返す契約 |
| `Embedding\ReDimNetEmbedder` | `FFI::cdef` で libonnxruntime をロード、ReDimNet2.onnx で推論 |
| `CosineScorer` | 既存。2ベクトルのコサイン類似度 |
| `Verdict` | 既存。score + threshold で bool |
| `Suzuran` | DIで各段を組み立て、`isMatch(wav1, wav2, threshold): bool` を提供 |

## Log-mel を PHP と Rust の2種類で持つ理由

「設計」メモに「テストで計算時間を計算していく」とあるとおり、実装のコストパフォーマンスを実測で選べるようにする。同じ入力に対して両方が近い出力を返すことを `tests/RustLogMelTest.php` で担保し、実行時間は `tests/Benchmark/LogMelBenchmark.php` で計測する。

## 前提と拡張ポイント

- **入力フォーマット**: 16-bit mono PCM のみ。ステレオや 24-bit は未対応（要件に応じて `WavConvertValue` を拡張）
- **リサンプル品質**: 線形補間。話者照合の精度をより上げたい場合は windowed sinc に置き換える
- **モデル入力仕様**: ReDimNet2 が期待する mel フォーマット (n_mels=80, hop=10ms) を採用。差異があれば `PhpLogMel` の定数と `rust/logmel/src/main.rs` を対応させる
- **enroll/verify**: 現状の要件は 1:1 の isMatch のみ。ユーザー登録・照合のフローは別PRでストレージ層 (PDO / pgvector) と併せて追加
