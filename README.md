# wordpress-pp

Production-focused WordPress filesystem layout and env configuration, built on Docker Hardened Images (DHI) PHP-FPM.

This repository publishes images to Docker Hub:

- `abrahamberg/wordpress-pp:latest` (rolling, Debian-based)
- `abrahamberg/wordpress-pp:debian` (rolling)
- `abrahamberg/wordpress-pp:alpine` (rolling)

## Tagging

Tags follow these rules:

- `latest` and `debian` always point to the latest WordPress release (Debian-based).
- `alpine` always points to the latest WordPress release (Alpine-based).
- WordPress-version tags match upstream WordPress:
  - `:<wpVersion>` (Debian-based)
  - `:<wpVersion>-debian`
  - `:<wpVersion>-alpine`
- If WordPress is unchanged but the hardened base changes, the same WordPress-version tags above are re-published and will move to the rebuilt image.
- For immutable pinning across base rebuilds, use:
  - `:<wpVersion>-<distro>-base-<baseDigest12>`

All tags are published as multi-arch manifests for `linux/amd64` and `linux/arm64`.

## Layout compatibility

- Primary paths:
  - `APP_ROOT_DIR=/opt/wordpress-pp`
  - `APP_VOLUME_DIR=/data`
  - WordPress core: `/opt/wordpress-pp/wordpress`
  - Persistent data volume: `/data/wordpress`

- Compatibility symlinks (for charts that expect these mount points):
  - `/opt/bitnami` -> `/opt/wordpress-pp`
  - `/bitnami` -> `/data`
- Persisted by default: `wp-config.php` and `wp-content`

> Note: this image is non-root. Host-mounted volumes must be writable by the container UID.

## Environment variables (subset)

This image supports the commonly used Bitnami WordPress env vars for database and config generation:

- `WORDPRESS_DATABASE_HOST`
- `WORDPRESS_DATABASE_PORT_NUMBER`
- `WORDPRESS_DATABASE_NAME`
- `WORDPRESS_DATABASE_USER`
- `WORDPRESS_DATABASE_PASSWORD`
- `WORDPRESS_TABLE_PREFIX`
- `WORDPRESS_EXTRA_WP_CONFIG_CONTENT`
- `WORDPRESS_*_KEY` / `WORDPRESS_*_SALT` (optional; generated if omitted)

For secrets, `*_FILE` variants are supported for the above variables.

## Runtime

This is an **FPM-only** image (no Apache/mod_php). Use it behind Nginx/Ingress/FastCGI.

Default command:

- `php-fpm -F`

## GitHub Actions

The workflow in `.github/workflows/build.yml`:

- runs daily (and manually)
- resolves the latest WordPress version
- builds Debian and Alpine variants
- pushes multi-arch images

Secrets required:

- `DOCKERHUB_USERNAME`
- `DOCKERHUB_TOKEN`

If `dhi.io` requires authentication in CI, the workflow will also use the same `DOCKERHUB_USERNAME` / `DOCKERHUB_TOKEN` to login to `dhi.io`.

Optional override (only if you need different creds for `dhi.io`):

- `DHI_USERNAME`
- `DHI_PASSWORD`

Base image tags:

- The workflow uses `BASE_IMAGE_DEBIAN` and `BASE_IMAGE_ALPINE`. If your DHI registry uses different tag naming, update those env values in `.github/workflows/build.yml`.
