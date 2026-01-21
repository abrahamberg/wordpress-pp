#!/usr/bin/env python3

from __future__ import annotations

import argparse
import re
import subprocess


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--image", required=True)
    args = ap.parse_args()

    proc = subprocess.run(
        ["docker", "buildx", "imagetools", "inspect", args.image],
        check=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        text=True,
    )
    m = re.search(r"^Digest:\s+(sha256:[0-9a-f]{64})\s*$", proc.stdout, flags=re.MULTILINE)
    if not m:
        raise SystemExit("Unable to parse base image digest from buildx output")
    print(m.group(1))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
