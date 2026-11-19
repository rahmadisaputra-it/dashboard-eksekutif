<?php
declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;
use Throwable;

class Database
{
    private static ?Database $instance = null;
    private ?PDO $pdo = null;
    private bool $connected = false;
    private string $activeDbName = 'sik_fix';
    private string $statusMessage = 'Terhubung ke SIMRS Khanza';

    // Database mandiri aplikasi (users, pengaturan RS, log API, snapshot hasil push)
    public const APP_DB_NAME = 'dashboard_eksekutif';
    private ?PDO $appPdo = null;
    private bool $appTried = false;
    private string $dbUser = 'root';
    private string $dbPass = '';

    private function __construct()
    {
        $hosts = ['localhost', '127.0.0.1'];
        $user  = 'root';
        $pass  = '';
        $candidateDbs = ['sik_fix', 'sik'];

        $confPath = dirname(__DIR__, 3) . '/webapps/conf/conf.php';
        if (is_file($confPath) && is_readable($confPath)) {
            $raw = (string) file_get_contents($confPath);
            if (preg_match('/\$db_hostname\s*=\s*["\']([^"\']+)["\']/', $raw, $m)) {
                array_unshift($hosts, $m[1]);
                $hosts = array_values(array_unique($hosts));
            }
            if (preg_match('/\$db_username\s*=\s*["\']([^"\']*)["\']/', $raw, $m)) {
                $user = $m[1];
            }
            if (preg_match('/\$db_password\s*=\s*["\']([^"\']*)["\']/', $raw, $m)) {
                $pass = $m[1];
            }
            if (preg_match('/\$db_name\s*=\s*["\']([^"\']+)["\']/', $raw, $m)) {
                array_unshift($candidateDbs, $m[1]);
                $candidateDbs = array_values(array_unique($candidateDbs));
            }
        }

        $this->dbUser = $user;
        $this->dbPass = $pass;

        foreach ($hosts as $host) {
            $checkHost = ($host === 'localhost') ? '127.0.0.1' : $host;
            $fp = @fsockopen($checkHost, 3306, $errno, $errstr, 0.25);
            if ($fp === false) {
                continue;
            }
            @fclose($fp);

            foreach ($candidateDbs as $dbName) {
                try {
                    $dsn = "mysql:host={$host};dbname={$dbName};charset=utf8mb4";
                    $this->pdo = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                        PDO::ATTR_TIMEOUT            => 3,
                    ]);
                    $this->connected     = true;
                    $this->activeDbName  = $dbName;
                    $this->statusMessage = "Live DB ({$dbName})";
                    return;
                } catch (PDOException $e) {
                    $this->pdo       = null;
                    $this->connected = false;
                }
            }
        }

        $this->activeDbName  = self::APP_DB_NAME;
        $this->statusMessage = 'Standalone Mode (API Push)';
    }

    public static function getApiKey(): string
    {
        $envKey = getenv('DASHBOARD_API_KEY') ?: getenv('PERMATA_API_KEY');
        if (is_string($envKey) && trim($envKey) !== '') {
            return trim($envKey);
        }
        return 'DASHBOARD-EKSEKUTIF-2026-KEY';
    }

    public static function isValidApiKey(string $providedKey): bool
    {
        $providedKey = trim($providedKey);
        if ($providedKey === '') {
            return false;
        }
        $primaryKey = self::getApiKey();
        if (hash_equals($primaryKey, $providedKey)) {
            return true;
        }
        // Kompatibilitas mundur dengan key service lama di setting/database.xml
        if (hash_equals('RSUPM-HFIS-SYNC-2026-KEY', $providedKey)) {
            return true;
        }
        return false;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Koneksi ke database Khanza (sik_fix) — digunakan hanya oleh worker pengirim
     * atau fitur tarik profil instansi dari tabel setting Khanza.
     */
    public function getConnection(): ?PDO
    {
        return $this->pdo;
    }

    /**
     * Koneksi ke database mandiri aplikasi (dashboard_eksekutif).
     * Otomatis menyiapkan tabel snapshot bila belum ada.
     */
    public function getAppConnection(): ?PDO
    {
        if ($this->appPdo !== null || $this->appTried) {
            return $this->appPdo;
        }
        $this->appTried = true;

        $fp = @fsockopen('127.0.0.1', 3306, $errno, $errstr, 0.25);
        if ($fp === false) {
            return null;
        }
        @fclose($fp);

        try {
            $this->appPdo = new PDO(
                'mysql:host=127.0.0.1;dbname=' . self::APP_DB_NAME . ';charset=utf8mb4',
                $this->dbUser,
                $this->dbPass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_TIMEOUT            => 3,
                ]
            );
            Setup::ensureAppSchema($this->appPdo);
        } catch (PDOException $e) {
            // Jika database dashboard_eksekutif belum dibuat, jalankan Setup::run()
            try {
                Setup::run('127.0.0.1', $this->dbUser, $this->dbPass);
                $this->appPdo = new PDO(
                    'mysql:host=127.0.0.1;dbname=' . self::APP_DB_NAME . ';charset=utf8mb4',
                    $this->dbUser,
                    $this->dbPass,
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                        PDO::ATTR_TIMEOUT            => 3,
                    ]
                );
            } catch (Throwable $ex) {
                $this->appPdo = null;
            }
        }

        return $this->appPdo;
    }

    /**
     * Catat log penerimaan push API dari service Khanza ke tabel api_log
     */
    public static function logApiPush(
        string $jenisData,
        int $totalRecord,
        string $pengirim = 'khanza_service',
        string $ip = '127.0.0.1',
        string $status = 'SUKSES',
        ?string $keterangan = null
    ): void {
        $pdo = self::getInstance()->getAppConnection();
        if ($pdo === null) {
            return;
        }
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO `api_log`
                 (`waktu_sync`, `jenis_data`, `total_record`, `pengirim`, `ip_pengirim`, `status`, `keterangan`)
                 VALUES (:w, :j, :t, :p, :ip, :s, :k)'
            );
            $stmt->execute([
                ':w'  => date('Y-m-d H:i:s'),
                ':j'  => substr($jenisData, 0, 50),
                ':t'  => $totalRecord,
                ':p'  => substr($pengirim, 0, 100),
                ':ip' => substr($ip, 0, 45),
                ':s'  => substr($status, 0, 20),
                ':k'  => $keterangan,
            ]);
        } catch (Throwable $e) {
            // Abaikan kegagalan log agar tidak mengganggu respon API
        }
    }

    public function isConnected(): bool
    {
        return ($this->appPdo !== null) || ($this->connected && $this->pdo !== null);
    }

    public function getActiveDbName(): string
    {
        return self::APP_DB_NAME;
    }

    public function getStatusMessage(): string
    {
        return $this->statusMessage;
    }
}
