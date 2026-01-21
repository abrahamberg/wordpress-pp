All examples in this guide use the public image. If you’ve mirrored the repository for your own use (for example, to your Docker Hub namespace), update your commands to reference the mirrored image instead of the public one.

For example:

Public image: dhi.io/<repository>:<tag>
Mirrored image: <your-namespace>/dhi-<repository>:<tag>
For the examples, you must first use docker login dhi.io to authenticate to the registry to pull the images.

What’s included in this PHP image
This Docker Hardened PHP image includes PHP runtime and essential tools in a single, security-hardened package:

php: PHP command-line interpreter and runtime environment (non-FPM variants only)
php-fpm: FastCGI Process Manager for serving PHP applications (FPM variant only)
phpdbg: a lightweight, powerful, easy to use debugging platform for PHP (dev variant only)
Essential PHP extensions built-in/statically compiled
Full phpize environment (phpize, php-config, compiler, PHP source code) for building extensions (dev variant only)
Start a PHP image
Run the following command and replace <tag> with the image variant you want to run.

docker run dhi.io/php:<tag> --version
Common PHP use cases
Running a PHP script
Execute PHP scripts directly from the command line.

docker run --rm -v $(pwd):/app -w /app dhi.io/php:<tag> php script.php
Serving a PHP application with PHP-FPM
Use the FPM variant to serve PHP applications through a FastCGI gateway like Nginx.

FROM dhi.io/php:<tag>-fpm
COPY . /var/www/html
EXPOSE 9000
Customizing PHP configuration with $PHP_INI_DIR
Configure PHP settings by adding custom ini files to the PHP configuration directory.

FROM dhi.io/php:<tag>
COPY custom-php.ini $PHP_INI_DIR/conf.d/
Building custom PHP extensions (dev variant)
Use the dev variant to compile and install additional PHP extensions.

Multi-stage build:

FROM dhi.io/php:<tag>-dev AS builder
WORKDIR /tmp
Example of building Redis extension manually:

RUN pecl install redis

FROM dhi.io/php:<tag>-fpm
COPY --from=builder $PHP_PREFIX/lib/php/extensions $PHP_PREFIX/lib/php/extensions
Add extension configuration:

RUN echo "extension=redis.so" > $PHP_INI_DIR/conf.d/redis.ini
Non-hardened images vs. Docker Hardened Images
Key differences
Feature	Docker Official PHP	Docker Hardened PHP
Security	Standard base with common utilities	Minimal, hardened base with security patches
Shell access	Full shell (bash/sh) available	No shell in runtime variants
Package manager	apt/apk available	No package manager in runtime variants
User	Runs as root by default	Runs as nonroot user
Attack surface	Larger due to additional utilities	Minimal, only essential components
Debugging	Traditional shell debugging	Use Docker Debug or Image Mount for troubleshooting
Apache support	mod_php available via apache2 variant	No mod_php support, FPM/FastCGI only
Extension building tools	docker-php-ext-* helper scripts for easy extension installation	No helper scripts, but PHP source in $PHP_SRC_DIR and full phpize environment in -dev variantdocker pull bitnami/wordpress:sha256-b697155ef45b6ceb80e2bfdcc758e4ccf846dd4a523caefcc87934139ce6fc88configure/make process
Why no shell or package manager?