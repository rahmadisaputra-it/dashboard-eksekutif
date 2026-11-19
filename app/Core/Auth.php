<?php
declare(strict_types=1);

namespace App\Core;

use App\Config\Database;
use Throwable;

class Auth
{
    private const SESSION_KEY = 'dashboard_eksekutif_auth_user';
    private const CSRF_KEY    = 'dashboard_eksekutif_csrf';

    /**
     * Registri modul yang SUDAH punya rute di index.php.
     * Hanya modul di daftar ini yang boleh diberikan ke user. Tambahkan modul baru
     * di sini bersamaan dengan rutenya (jangan sebelum rutenya ada).
     *
     * @var array<string, array{label: string, route: string}>
     */
    public const MODULES = [
        'keuangan'    => ['label' => 'Keuangan',               'route' => 'index.php?module=keuangan&page=kendali-biaya'],
        'operasional' => ['label' => 'Operasional & Pelayanan', 'route' => 'index.php?module=operasional&page=jadwal-hfis'],
    ];

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_name('DASHBOARDEKSEKUTIF_SESSID');
            session_start();
        }
    }

    public static function check(): bool
    {
        self::start();
        return !empty($_SESSION[self::SESSION_KEY]) && is_array($_SESSION[self::SESSION_KEY]);
    }

    public static function user(): ?array
    {
        self::start();
        return isset($_SESSION[self::SESSION_KEY]) && is_array($_SESSION[self::SESSION_KEY])
            ? $_SESSION[self::SESSION_KEY]
            : null;
    }

    /**
     * Normalisasi daftar modul: hanya string yang dikenal registri, huruf kecil, unik.
     *
     * @param mixed $raw array, atau string JSON dari kolom users.allowed_modules
     * @return string[]
     */
    public static function normalizeModules(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return [];
        }
        $clean = [];
        foreach ($raw as $m) {
            if (is_string($m)) {
                $m = strtolower(trim($m));
                if (isset(self::MODULES[$m])) {
                    $clean[$m] = $m;
                }
            }
        }
        return array_values($clean);
    }

    /**
     * @param array<string, mixed> $userData baris dari tabel users (tanpa password_hash)
     */
    public static function login(array $userData): void
    {
        self::start();
        if (function_exists('session_regenerate_id') && !headers_sent()) {
            @session_regenerate_id(true);
        }
        unset($_SESSION[self::CSRF_KEY]);

        $_SESSION[self::SESSION_KEY] = self::buildSessionUser($userData) + [
            'login_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function buildSessionUser(array $row): array
    {
        $username = (string) ($row['username'] ?? '');
        return [
            'id'         => (int) ($row['id'] ?? 0),
            'username'   => $username,
            'id_user'    => $username, // kompatibilitas view lama (sidebar / cetak)
            'nama'       => (string) ($row['nama'] ?? $username),
            'jabatan'    => (string) ($row['jabatan'] ?? 'Petugas'),
            'departemen' => (string) ($row['departemen'] ?? '-'),
            'role'       => ($row['role'] ?? 'user') === 'admin' ? 'admin' : 'user',
            'modules'    => self::normalizeModules($row['allowed_modules'] ?? ($row['modules'] ?? [])),
        ];
    }

    public static function logout(): void
    {
        self::start();
        unset($_SESSION[self::SESSION_KEY], $_SESSION[self::CSRF_KEY]);
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_destroy();
        }
    }

    /**
     * Validasi ulang akun yang sedang login ke database (1 query PK per request).
     * Akun yang dinonaktifkan / dihapus langsung keluar; perubahan role & hak akses
     * oleh admin berlaku seketika tanpa menunggu user login ulang.
     * Fail-closed: bila database tidak terjangkau, sesi dianggap tidak valid.
     */
    public static function revalidate(): bool
    {
        $current = self::user();
        if ($current === null) {
            return false;
        }

        $pdo = Database::getInstance()->getAppConnection();
        if ($pdo === null) {
            self::logout();
            return false;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT id, username, nama, jabatan, departemen, role, status, allowed_modules
                 FROM users WHERE id = :id LIMIT 1'
            );
            $stmt->execute([':id' => (int) ($current['id'] ?? 0)]);
            $row = $stmt->fetch();
        } catch (Throwable $e) {
            self::logout();
            return false;
        }

        if (!is_array($row) || ($row['status'] ?? '') !== 'aktif') {
            self::logout();
            return false;
        }

        $_SESSION[self::SESSION_KEY] = self::buildSessionUser($row) + [
            'login_at' => $current['login_at'] ?? date('Y-m-d H:i:s'),
        ];
        return true;
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        return $user !== null && ($user['role'] ?? '') === 'admin';
    }

    /**
     * Cek apakah user yang sedang login berhak mengakses modul tertentu.
     * Modul yang tidak ada di registri MODULES selalu ditolak (default deny).
     */
    public static function canAccessModule(string $module): bool
    {
        if (!self::check()) {
            return false;
        }
        $module = strtolower($module);
        if (!isset(self::MODULES[$module])) {
            return false;
        }
        if (self::isAdmin()) {
            return true;
        }
        $modules = self::user()['modules'] ?? [];
        return is_array($modules) && in_array($module, $modules, true);
    }

    /**
     * Modul pertama yang boleh diakses user, atau '' bila tidak punya hak akses sama sekali.
     */
    public static function defaultModule(): string
    {
        foreach (array_keys(self::MODULES) as $module) {
            if (self::canAccessModule($module)) {
                return $module;
            }
        }
        return '';
    }

    /**
     * URL halaman awal sesuai hak akses user.
     */
    public static function defaultRoute(): string
    {
        $module = self::defaultModule();
        return $module !== '' ? self::MODULES[$module]['route'] : 'index.php?module=auth&page=login';
    }

    // ------------------------------------------------------------------
    // CSRF (semua form POST wajib menyertakan token ini)
    // ------------------------------------------------------------------

    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION[self::CSRF_KEY]) || !is_string($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::CSRF_KEY];
    }

    public static function verifyCsrf(mixed $token): bool
    {
        self::start();
        $expected = $_SESSION[self::CSRF_KEY] ?? '';
        return is_string($token) && is_string($expected) && $expected !== ''
            && hash_equals($expected, $token);
    }
}
