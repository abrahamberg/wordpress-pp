#!/usr/bin/env python3

from __future__ import annotations

import argparse
import urllib.request


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--version", required=True)
    args = ap.parse_args()

    url = f"https://wordpress.org/wordpress-{args.version}.tar.gz.sha1"
    with urllib.request.urlopen(url, timeout=30) as r:
        sha1 = r.read().decode("utf-8").strip().split()[0]
    print(sha1)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
