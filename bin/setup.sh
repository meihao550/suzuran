#!/usr/bin/env bash
# Suzuran setup: brew install onnxruntime, build Rust logmel binary, fetch ReDimNet2 model.
#
# Usage:
#   bash bin/setup.sh
#
# macOS/Homebrew is assumed. On Linux, install libonnxruntime via your package manager and
# re-run only the model/cargo sections manually.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "==> Checking libonnxruntime"
if command -v brew >/dev/null 2>&1; then
    if ! brew list onnxruntime >/dev/null 2>&1; then
        brew install onnxruntime
    else
        echo "onnxruntime already installed via brew"
    fi
else
    echo "Homebrew not found; skipping. Install libonnxruntime manually so PHP FFI can load it."
fi

echo "==> Building Rust logmel binary"
if command -v cargo >/dev/null 2>&1; then
    (cd rust/logmel && cargo build --release)
    mkdir -p bin
    cp -f rust/logmel/target/release/logmel-rs bin/logmel-rs
    chmod +x bin/logmel-rs
    echo "bin/logmel-rs built"
else
    echo "cargo not found; install Rust (https://rustup.rs) then rerun to build bin/logmel-rs"
fi

echo "==> Ensuring models/redimnet2.onnx"
mkdir -p models
if [ ! -f models/redimnet2.onnx ]; then
    # FIXME: ReDimNet2 has no single canonical ONNX distribution yet. Options:
    #   1. Export from the PyTorch checkpoint at https://github.com/IDRnD/ReDimNet
    #      using torch.onnx.export
    #   2. Use a mirror if one becomes available and set REDIMNET2_URL below.
    if [ -n "${REDIMNET2_URL:-}" ]; then
        echo "Downloading from REDIMNET2_URL"
        curl -L -o models/redimnet2.onnx "$REDIMNET2_URL"
    else
        cat >&2 <<'EOF'
models/redimnet2.onnx not found. Export it once with the Python helper:

    python3 -m venv scripts/.venv
    source scripts/.venv/bin/activate
    pip install torch torchaudio onnx
    python scripts/export_redimnet.py
    deactivate

Or provide a direct URL:

    REDIMNET2_URL=https://example.com/redimnet2.onnx bash bin/setup.sh
EOF
    fi
else
    echo "models/redimnet2.onnx already present"
fi

echo "==> Done"
