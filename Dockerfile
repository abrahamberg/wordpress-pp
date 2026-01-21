# syntax=docker/dockerfile:1.7

ARG BASE_IMAGE

FROM python:3.12-alpine AS fetch

ARG WORDPRESS_VERSION
ARG WORDPRESS_SHA1
ARG WP_CLI_VERSION=2.10.0

WORKDIR /work

COPY scripts/fetch_assets.py /work/fetch_assets.py

RUN python /work/fetch_assets.py \
      --wordpress-version "$WORDPRESS_VERSION" \
      --wordpress-sha1 "$WORDPRESS_SHA1" \
      --wp-cli-version "$WP_CLI_VERSION" \
      --out-dir /out


FROM ${BASE_IMAGE} AS runtime

ARG WORDPRESS_VERSION
ARG BASE_IMAGE

ENV APP_ROOT_DIR=/opt/wordpress-pp \
      APP_VOLUME_DIR=/data \
      WORDPRESS_BASE_DIR=/opt/wordpress-pp/wordpress \
      WORDPRESS_VOLUME_DIR=/data/wordpress \
      WORDPRESS_CONF_FILE=/opt/wordpress-pp/wordpress/wp-config.php \
      APP_VERSION=${WORDPRESS_VERSION}

WORKDIR /opt/wordpress-pp

COPY --from=fetch /out/wordpress /opt/wordpress-pp/wordpress
COPY --from=fetch /out/wp-cli/wp-cli.phar /opt/wordpress-pp/wp-cli/bin/wp
COPY docker/opt/bitnami/scripts/wordpress/entrypoint.php /opt/wordpress-pp/scripts/wordpress/entrypoint.php

# Create compatibility symlinks and persistence symlinks without relying on a shell.
RUN ["php", "-r", "\
@mkdir('/opt/wordpress-pp/scripts/wordpress', 0775, true);\
@mkdir('/opt/wordpress-pp/wp-cli/bin', 0775, true);\
@chmod('/opt/wordpress-pp/wp-cli/bin/wp', 0755);\
@mkdir('/data/wordpress', 0775, true);\
if (!file_exists('/opt/bitnami')) { @symlink('/opt/wordpress-pp', '/opt/bitnami'); }\
if (!file_exists('/bitnami')) { @symlink('/data', '/bitnami'); }\
$root='/opt/wordpress-pp/wordpress';\
$vol='/data/wordpress';\
if (is_dir($root.'/wp-content') && !is_link($root.'/wp-content')) { rename($root.'/wp-content', $root.'/wp-content.dist'); }\
if (!file_exists($vol.'/wp-content')) { @mkdir($vol.'/wp-content', 0775, true); }\
if (!file_exists($root.'/wp-content')) { @symlink($vol.'/wp-content', $root.'/wp-content'); }\
if (is_file($root.'/wp-config.php') && !is_link($root.'/wp-config.php')) { @unlink($root.'/wp-config.php'); }\
if (!file_exists($root.'/wp-config.php')) { @symlink($vol.'/wp-config.php', $root.'/wp-config.php'); }\
"]

# OCI labels (minimal, public-repo best practice)
LABEL org.opencontainers.image.title="wordpress-pp" \
      org.opencontainers.image.description="Chart-compatible WordPress layout on Docker Hardened PHP (FPM-only)" \
      org.opencontainers.image.version="${WORDPRESS_VERSION}" \
      org.opencontainers.image.base.name="${BASE_IMAGE}"

EXPOSE 9000

ENTRYPOINT ["php", "/opt/wordpress-pp/scripts/wordpress/entrypoint.php"]
CMD ["php-fpm", "-F"]
