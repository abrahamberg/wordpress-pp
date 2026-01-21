#!/usr/bin/env python3

from __future__ import annotations

import argparse


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--image", required=True)
    ap.add_argument("--wp-version", required=True)
    ap.add_argument("--distro", choices=["debian", "alpine"], required=True)
    ap.add_argument("--base-digest-short", required=True)
    ap.add_argument("--rolling", default="")
    args = ap.parse_args()

    tags: list[str] = []
    if args.rolling:
        for t in args.rolling.split(","):
            t = t.strip()
            if t:
                tags.append(f"{args.image}:{t}")

    tags.append(f"{args.image}:{args.wp_version}-{args.distro}")
    if args.distro == "debian":
        tags.append(f"{args.image}:{args.wp_version}")
    tags.append(f"{args.image}:{args.wp_version}-{args.distro}-base-{args.base_digest_short}")

    for t in tags:
        print(t)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
