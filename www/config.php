<?php
declare(strict_types=1);

/**
 * Konfigurasi koneksi database.
 * Nilai diambil dari environment (lihat docker-compose.yml),
 * dengan fallback agar kode tetap berjalan saat diuji tanpa env.
 */

function env_str(string $key, string $default): string
{
    $v = getenv($key);
    return $v === false || $v === '' ? $default : $v;
}

final class DB
{
    public const HOST = 'mariadb';
    public const PORT = 3306;
    public const NAME = 'semogasukses_db';
    public const USER = 'semogasukses_user';
    public const PASS = 'UserSecretPass123!';

    private static ?PDO $pdo = null;

    public static function conn(): PDO
    {
        if (self::$pdo === null) {
            $host = env_str('DB_HOST', self::HOST);
            $port = (int) env_str('DB_PORT', (string) self::PORT);
            $name = env_str('DB_NAME', self::NAME);
            $user = env_str('DB_USER', self::USER);
            $pass = env_str('DB_PASS', self::PASS);

            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    /** Kembalikan koneksi, atau null bila DB belum siap (mis. container baru start). */
    public static function tryConn(): ?PDO
    {
        try {
            return self::conn();
        } catch (Throwable $e) {
            return null;
        }
    }
}

/** Escape output HTML agar aman dari XSS. */
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/** Kirim respons JSON lalu hentikan eksekusi. */
function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function apply_security_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline';");
}