#!/usr/bin/env python3

from __future__ import annotations

import argparse
import hashlib
import os
import tarfile
import urllib.request
from pathlib import Path


def _download(url: str, dest: Path) -> None:
    dest.parent.mkdir(parents=True, exist_ok=True)
    with urllib.request.urlopen(url) as r:
        data = r.read()
    dest.write_bytes(data)


def _sha1(path: Path) -> str:
    h = hashlib.sha1()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--wordpress-version", required=True)
    ap.add_argument("--wordpress-sha1", default="")
    ap.add_argument("--wp-cli-version", required=True)
    ap.add_argument("--out-dir", required=True)
    args = ap.parse_args()

    out_dir = Path(args.out_dir)
    out_dir.mkdir(parents=True, exist_ok=True)

    wp_url = f"https://wordpress.org/wordpress-{args.wordpress_version}.tar.gz"
    wp_tgz = out_dir / "wordpress.tar.gz"
    _download(wp_url, wp_tgz)

    if args.wordpress_sha1:
        actual = _sha1(wp_tgz)
        expected = args.wordpress_sha1.strip().split()[0]
        if actual != expected:
            raise SystemExit(f"WordPress SHA1 mismatch: expected {expected}, got {actual}")

    wp_out = out_dir / "wordpress"
    wp_out.mkdir(parents=True, exist_ok=True)
    with tarfile.open(wp_tgz, "r:gz") as tf:
        members = tf.getmembers()
        # WordPress tarball has top-level 'wordpress/'
        for m in members:
            if not m.name.startswith("wordpress/"):
                continue
            m.name = m.name.removeprefix("wordpress/")
            if m.name == "":
                continue
            tf.extract(m, wp_out)

    wp_cli_url = f"https://github.com/wp-cli/wp-cli/releases/download/v{args.wp_cli_version}/wp-cli-{args.wp_cli_version}.phar"
    wp_cli_out = out_dir / "wp-cli"
    wp_cli_out.mkdir(parents=True, exist_ok=True)
    _download(wp_cli_url, wp_cli_out / "wp-cli.phar")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
