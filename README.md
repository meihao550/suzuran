# Suzuran

PHP で書かれた話者照合ライブラリ。2つのWAVファイルを渡すと同一話者か bool で返す。ReDimNet2 (ONNX Runtime) による埋め込み → コサイン類似度 → 閾値判定というシンプルなパイプライン。

## 目的

- PHP から音声AI機能を扱いやすくする学習用ライブラリ
- 話者照合（1対1のWAV比較）を最短経路で動かす
- 重い計算 (Log-mel) は PHP と Rust の両方で実装し、テストで実測して選べる状態にする

## フォルダー構成

```
src/               PHPソース (Suzuran クラス、パイプラインの各段)
├── WavConvertValue.php
├── Resampler.php
├── Feature/       Log-mel 抽出 (PHP版 / Rust版)
└── Embedding/     ReDimNet2 埋め込み (FFI + libonnxruntime)
rust/logmel/       Rust版 Log-mel の Cargo プロジェクト
bin/               setup.sh と Rust バイナリ (bin/logmel-rs)
models/            ReDimNet2.onnx (setup.sh で配置)
tests/             PHPUnit テストスイート + ベンチマーク
docs/              アーキテクチャと設計メモ
scale.wav          テスト用の16-bit mono 44.1 kHz WAV
```

## 起動方法

```bash
composer install
bash bin/setup.sh    # libonnxruntime (brew) + Rust logmel + ReDimNet2.onnx
```

`models/redimnet2.onnx` は自動配布URLがまだ無いので、`scripts/export_redimnet.py` で PyTorch チェックポイントから ONNX に変換します（1回きり）:

```bash
python3 -m venv scripts/.venv
source scripts/.venv/bin/activate
pip install torch torchaudio onnx
python scripts/export_redimnet.py         # → models/redimnet2.onnx
deactivate
```

`--variant b0..b6` などで別サイズのモデルを export できます。詳細は `python scripts/export_redimnet.py --help`。

## 使い方

```php
require 'vendor/autoload.php';

$suzuran = new Suzuran\Suzuran();
$isSame = $suzuran->isMatch('a.wav', 'b.wav', threshold: 0.7);
var_dump($isSame);
```

Log-mel 実装を差し替えたい場合はコンストラクタで DI する:

```php
use Suzuran\Suzuran;
use Suzuran\Feature\RustLogMel;

$suzuran = new Suzuran(logMel: new RustLogMel());
```

## テスト

```bash
composer test                             # PHPUnit ユニットテスト
php tests/Benchmark/LogMelBenchmark.php   # PhpLogMel vs RustLogMel の実測
vendor/bin/phpunit --testsuite integration # ONNX モデル込みの E2E (要 setup.sh 完走)
```

## 設計メモ

- パイプライン全体は [docs/architecture.md](docs/architecture.md) を参照
