<?php
declare(strict_types=1);

final class Env
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $fileKey = $key . '_FILE';
        $filePath = getenv($fileKey);
        if (is_string($filePath) && $filePath !== '' && is_readable($filePath)) {
            $value = trim((string) file_get_contents($filePath));
            if ($value !== '') {
                putenv($key . '=' . $value);
                return $value;
            }
        }
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        return $value;
    }

    public static function isYes(string $key, bool $default = false): bool
    {
        $value = strtolower((string) self::get($key, $default ? 'yes' : 'no'));
        return in_array($value, ['1', 'true', 'yes', 'y', 'on'], true);
    }
}

final class Fs
{
    public static function ensureDir(string $path, int $mode = 0775): void
    {
        if (is_dir($path)) {
            return;
        }
        if (!@mkdir($path, $mode, true) && !is_dir($path)) {
            throw new RuntimeException("Unable to create directory: {$path}");
        }
    }

    public static function copyDir(string $src, string $dst): void
    {
        self::ensureDir($dst);
        $items = scandir($src);
        if ($items === false) {
            throw new RuntimeException("Unable to read directory: {$src}");
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $s = $src . DIRECTORY_SEPARATOR . $item;
            $d = $dst . DIRECTORY_SEPARATOR . $item;
            if (is_link($s)) {
                continue;
            }
            if (is_dir($s)) {
                self::copyDir($s, $d);
                continue;
            }
            if (!is_file($d)) {
                if (!@copy($s, $d)) {
                    throw new RuntimeException("Unable to copy file {$s} -> {$d}");
                }
            }
        }
    }
}

final class WordPress
{
    public function __construct(
        private string $rootDir,
        private string $volumeDir,
        private string $configPath,
    ) {}

    public function bootstrap(): void
    {
        Fs::ensureDir($this->volumeDir);

        $this->ensureWpContentPersisted();
        $this->ensureWpConfigPersisted();
        $this->renderWpConfigFromEnvIfEmpty();
    }

    private function ensureWpContentPersisted(): void
    {
        $persisted = $this->volumeDir . '/wp-content';
        if (!is_dir($persisted) || (count((array) @scandir($persisted)) <= 2)) {
            $dist = $this->rootDir . '/wp-content.dist';
            if (is_dir($dist)) {
                Fs::copyDir($dist, $persisted);
            } elseif (is_dir($this->rootDir . '/wp-content')) {
                Fs::copyDir($this->rootDir . '/wp-content', $persisted);
            } else {
                Fs::ensureDir($persisted);
            }
        }
    }

    private function ensureWpConfigPersisted(): void
    {
        if (is_file($this->configPath)) {
            return;
        }
        $sample = $this->rootDir . '/wp-config-sample.php';
        if (is_file($sample)) {
            $content = (string) file_get_contents($sample);
            if ($content === '') {
                throw new RuntimeException("Empty wp-config-sample.php at {$sample}");
            }
            if (!@file_put_contents($this->configPath, $content)) {
                throw new RuntimeException("Unable to write wp-config.php at {$this->configPath}");
            }
            return;
        }
        throw new RuntimeException("Missing {$sample}");
    }

    private function renderWpConfigFromEnvIfEmpty(): void
    {
        $marker = $this->volumeDir . '/.wp_config_rendered';
        if (is_file($marker)) {
            return;
        }

        $dbHost = Env::get('WORDPRESS_DATABASE_HOST', 'mariadb');
        $dbPort = Env::get('WORDPRESS_DATABASE_PORT_NUMBER', '3306');
        $dbName = Env::get('WORDPRESS_DATABASE_NAME', 'bitnami_wordpress');
        $dbUser = Env::get('WORDPRESS_DATABASE_USER', 'bn_wordpress');
        $dbPass = Env::get('WORDPRESS_DATABASE_PASSWORD', '');
        $tablePrefix = Env::get('WORDPRESS_TABLE_PREFIX', 'wp_');

        $cfg = (string) file_get_contents($this->configPath);
        if ($cfg === '') {
            throw new RuntimeException("Unable to read {$this->configPath}");
        }

        $cfg = preg_replace("/define\(\s*'DB_NAME'\s*,\s*'[^']*'\s*\);/", "define('DB_NAME', '" . addslashes($dbName) . "');", $cfg) ?? $cfg;
        $cfg = preg_replace("/define\(\s*'DB_USER'\s*,\s*'[^']*'\s*\);/", "define('DB_USER', '" . addslashes($dbUser) . "');", $cfg) ?? $cfg;
        $cfg = preg_replace("/define\(\s*'DB_PASSWORD'\s*,\s*'[^']*'\s*\);/", "define('DB_PASSWORD', '" . addslashes($dbPass) . "');", $cfg) ?? $cfg;

        $host = $dbHost;
        if ($dbPort !== null && $dbPort !== '' && $dbPort !== '3306' && strpos($dbHost ?? '', ':') === false) {
            $host = $dbHost . ':' . $dbPort;
        }
        $cfg = preg_replace("/define\(\s*'DB_HOST'\s*,\s*'[^']*'\s*\);/", "define('DB_HOST', '" . addslashes($host) . "');", $cfg) ?? $cfg;

        $cfg = preg_replace("/\$table_prefix\s*=\s*'[^']*';/", "\$table_prefix = '" . addslashes($tablePrefix) . "';", $cfg) ?? $cfg;

        $cfg = $this->applySalts($cfg);

        $extra = Env::get('WORDPRESS_EXTRA_WP_CONFIG_CONTENT', '');
        if (is_string($extra) && $extra !== '') {
            $cfg = preg_replace("#(/\* That's all, stop editing! Happy publishing\. \*/)#", trim($extra) . "\n\n$1", $cfg) ?? ($cfg . "\n" . $extra);
        }

        if (!@file_put_contents($this->configPath, $cfg)) {
            throw new RuntimeException("Unable to write {$this->configPath}");
        }

        @file_put_contents($marker, "ok\n");
    }

    private function applySalts(string $cfg): string
    {
        $map = [
            'AUTH_KEY' => Env::get('WORDPRESS_AUTH_KEY', ''),
            'SECURE_AUTH_KEY' => Env::get('WORDPRESS_SECURE_AUTH_KEY', ''),
            'LOGGED_IN_KEY' => Env::get('WORDPRESS_LOGGED_IN_KEY', ''),
            'NONCE_KEY' => Env::get('WORDPRESS_NONCE_KEY', ''),
            'AUTH_SALT' => Env::get('WORDPRESS_AUTH_SALT', ''),
            'SECURE_AUTH_SALT' => Env::get('WORDPRESS_SECURE_AUTH_SALT', ''),
            'LOGGED_IN_SALT' => Env::get('WORDPRESS_LOGGED_IN_SALT', ''),
            'NONCE_SALT' => Env::get('WORDPRESS_NONCE_SALT', ''),
        ];
        foreach ($map as $key => $value) {
            if (!is_string($value) || $value === '') {
                $value = base64_encode(random_bytes(48));
            }
            $cfg = preg_replace(
                "/define\(\s*'" . preg_quote($key, '/') . "'\s*,\s*'[^']*'\s*\);/",
                "define('{$key}', '" . addslashes($value) . "');",
                $cfg
            ) ?? $cfg;
        }
        return $cfg;
    }
}

function log_stderr(string $msg): void
{
    fwrite(STDERR, $msg . "\n");
}

try {
    $root = Env::get('WORDPRESS_BASE_DIR', '/opt/bitnami/wordpress');
    $vol = Env::get('WORDPRESS_VOLUME_DIR', '/bitnami/wordpress');
    $conf = Env::get('WORDPRESS_CONF_FILE', '/opt/bitnami/wordpress/wp-config.php');

    (new WordPress($root ?? '/opt/bitnami/wordpress', $vol ?? '/bitnami/wordpress', $conf ?? '/opt/bitnami/wordpress/wp-config.php'))
        ->bootstrap();

    $cmd = $argv;
    array_shift($cmd);
    if (count($cmd) === 0) {
        $cmd = ['php-fpm', '-F'];
    }

    $printable = implode(' ', array_map(static fn($s) => (string) $s, $cmd));
    log_stderr("[wordpress-pp] starting: {$printable}");

    if (function_exists('pcntl_exec')) {
        $bin = array_shift($cmd);
        pcntl_exec((string) $bin, array_map('strval', $cmd));
        throw new RuntimeException('pcntl_exec failed');
    }

    $proc = proc_open(
        array_map('strval', $cmd),
        [0 => STDIN, 1 => STDOUT, 2 => STDERR],
        $pipes,
        null,
        null,
        ['bypass_shell' => true]
    );
    if (!is_resource($proc)) {
        throw new RuntimeException('Unable to start process');
    }
    $code = proc_close($proc);
    exit(is_int($code) ? $code : 1);
} catch (Throwable $e) {
    log_stderr('[wordpress-pp] init failed: ' . $e->getMessage());
    exit(1);
}
