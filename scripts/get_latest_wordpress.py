#!/usr/bin/env python3

from __future__ import annotations

import json
import urllib.request


def main() -> int:
    url = "https://api.wordpress.org/core/version-check/1.7/"
    with urllib.request.urlopen(url, timeout=30) as r:
        data = json.loads(r.read().decode("utf-8"))
    offers = data.get("offers") or []
    if not offers:
        raise SystemExit("No offers returned from WordPress API")
    version = offers[0].get("current")
    if not version:
        raise SystemExit("Missing 'current' version in offers[0]")
    print(version)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
