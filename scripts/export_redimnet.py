"""
One-shot ONNX export helper for ReDimNet2.

Usage:
    python3 -m venv scripts/.venv
    source scripts/.venv/bin/activate
    pip install torch torchaudio onnx
    python scripts/export_redimnet.py
    deactivate

The resulting file is written to models/redimnet2.onnx, which the PHP
pipeline (Suzuran\Embedding\ReDimNetEmbedder) then loads via FFI.

This script is a one-time bootstrap - Python is not needed at runtime.
"""

from __future__ import annotations

import argparse
import pathlib
import sys

import torch


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Export ReDimNet2 to ONNX")
    parser.add_argument("--variant", default="b2", help="ReDimNet variant (b0..b6)")
    parser.add_argument(
        "--train-type", default="ft_lm", choices=["ptn", "ft_lm"],
        help="Training regime published by IDRnD",
    )
    parser.add_argument("--dataset", default="vox2", help="Pretraining dataset")
    parser.add_argument("--n-mels", type=int, default=80, help="Log-mel feature dim")
    parser.add_argument(
        "--frames", type=int, default=200,
        help="Dummy time frames for tracing (any value works, axis is dynamic)",
    )
    parser.add_argument(
        "--out", default="models/redimnet2.onnx", help="Output ONNX path",
    )
    parser.add_argument("--opset", type=int, default=17, help="ONNX opset version")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    out_path = pathlib.Path(args.out)
    out_path.parent.mkdir(parents=True, exist_ok=True)

    model = torch.hub.load(
        "IDRnD/ReDimNet",
        "ReDimNet",
        model_name=args.variant,
        train_type=args.train_type,
        dataset=args.dataset,
    ).eval()

    dummy = torch.randn(1, args.n_mels, args.frames)
    torch.onnx.export(
        model,
        dummy,
        args.out,
        input_names=["mel"],
        output_names=["embedding"],
        dynamic_axes={"mel": {2: "time"}, "embedding": {0: "batch"}},
        opset_version=args.opset,
    )
    print(f"wrote {args.out}", file=sys.stderr)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
