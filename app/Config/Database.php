<?php
declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;
use Throwable;

class Database
{
    public const DB_HOST = '127.0.0.1';
    public const DB_PORT = '3306';
    public const DB_NAME = 'dashboard_eksekutif';
    public const DB_USER = 'root';
    public const DB_PASS = '';
    public const API_KEY = 'DASHBOARD-EKSEKUTIF-2026-KEY';

    private static ?Database $instance = null;
    private ?PDO $appPdo = null;
    private bool $appTried = false;

    private function __construct()
    {
    }

    public static function getApiKey(): string
    {
        return self::API_KEY;
    }

    public static function isValidApiKey(string $providedKey): bool
    {
        $providedKey = trim($providedKey);
        if ($providedKey === '') {
            return false;
        }
        return hash_equals(self::API_KEY, $providedKey);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): ?PDO
    {
        return null;
    }

    public function getAppConnection(): ?PDO
    {
        if ($this->appPdo !== null || $this->appTried) {
            return $this->appPdo;
        }
        $this->appTried = true;

        $dsn = 'mysql:host=' . self::DB_HOST . ';port=' . self::DB_PORT . ';dbname=' . self::DB_NAME . ';charset=utf8mb4';
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 3,
        ];

        try {
            $this->appPdo = new PDO($dsn, self::DB_USER, self::DB_PASS, $opts);
            Setup::ensureAppSchema($this->appPdo);
        } catch (PDOException $e) {
            try {
                $setup = Setup::run(self::DB_HOST, self::DB_USER, self::DB_PASS, self::DB_NAME, self::DB_PORT);
                if (!empty($setup['success'])) {
                    $this->appPdo = new PDO($dsn, self::DB_USER, self::DB_PASS, $opts);
                }
            } catch (Throwable $ex) {
                $this->appPdo = null;
            }
        }

        return $this->appPdo;
    }

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
        }
    }

    public function isConnected(): bool
    {
        return $this->getAppConnection() !== null;
    }

    public function getActiveDbName(): string
    {
        return self::DB_NAME;
    }

    public function getStatusMessage(): string
    {
        return $this->isConnected() ? 'Terhubung (' . self::DB_NAME . ')' : 'Terputus';
    }
}
