<?php
declare(strict_types=1);

namespace App\Modules\Auth\Models;

use App\Config\Database;
use PDO;
use Throwable;

/**
 * Autentikasi mandiri terhadap tabel `users` di database dashboard_eksekutif.
 * Tidak ada lagi ketergantungan ke tabel admin/user SIMRS Khanza.
 */
class UserModel
{
    private ?PDO $pdo;

    /** Hash tiruan agar waktu respon sama saat username tidak ditemukan (anti user-enumeration). */
    private static ?string $dummyHash = null;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getAppConnection();
    }

    /**
     * @return array<string, mixed>|null data user (tanpa password_hash) bila kredensial valid & akun aktif
     */
    public function authenticate(string $username, string $password): ?array
    {
        if ($this->pdo === null || $username === '' || $password === '') {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, username, password_hash, nama, jabatan, departemen, role, status, allowed_modules
                 FROM users WHERE username = :u LIMIT 1'
            );
            $stmt->execute([':u' => $username]);
            $row = $stmt->fetch();
        } catch (Throwable $e) {
            return null;
        }

        if (self::$dummyHash === null) {
            self::$dummyHash = password_hash('permata-dummy', PASSWORD_BCRYPT);
        }

        $hash  = is_array($row) ? (string) $row['password_hash'] : self::$dummyHash;
        $valid = password_verify($password, $hash);

        if (!is_array($row) || !$valid || $row['status'] !== 'aktif') {
            return null;
        }

        // Upgrade hash otomatis bila cost/algoritma default PHP berubah
        if (password_needs_rehash($hash, PASSWORD_BCRYPT)) {
            $this->updateHash((int) $row['id'], password_hash($password, PASSWORD_BCRYPT));
        }

        try {
            $upd = $this->pdo->prepare('UPDATE users SET last_login = :t WHERE id = :id');
            $upd->execute([':t' => date('Y-m-d H:i:s'), ':id' => (int) $row['id']]);
        } catch (Throwable $e) {
            // last_login hanya informasi; jangan gagalkan login
        }

        unset($row['password_hash']);
        return $row;
    }

    private function updateHash(int $id, string $newHash): void
    {
        try {
            $stmt = $this->pdo->prepare('UPDATE users SET password_hash = :h, updated_at = :t WHERE id = :id');
            $stmt->execute([':h' => $newHash, ':t' => date('Y-m-d H:i:s'), ':id' => $id]);
        } catch (Throwable $e) {
            // abaikan; hash lama tetap valid
        }
    }

    /**
     * Ganti password mandiri oleh user yang sedang login.
     *
     * @return array{ok: bool, message: string}
     */
    public function changeOwnPassword(int $userId, string $oldPassword, string $newPassword): array
    {
        if ($this->pdo === null || $userId <= 0) {
            return ['ok' => false, 'message' => 'Koneksi database akun tidak tersedia.'];
        }

        if (strlen($newPassword) < 6) {
            return ['ok' => false, 'message' => 'Password baru minimal 6 karakter.'];
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, password_hash, status FROM users WHERE id = :id LIMIT 1'
            );
            $stmt->execute([':id' => $userId]);
            $row = $stmt->fetch();
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal memverifikasi akun di database.'];
        }

        if (!is_array($row) || ($row['status'] ?? '') !== 'aktif') {
            return ['ok' => false, 'message' => 'Akun tidak ditemukan atau sudah tidak aktif.'];
        }

        if (!password_verify($oldPassword, (string) $row['password_hash'])) {
            return ['ok' => false, 'message' => 'Password saat ini tidak sesuai.'];
        }

        if ($oldPassword === $newPassword) {
            return ['ok' => false, 'message' => 'Password baru tidak boleh sama dengan password saat ini.'];
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        try {
            $upd = $this->pdo->prepare(
                'UPDATE users SET password_hash = :h, updated_at = :t WHERE id = :id'
            );
            $upd->execute([
                ':h'  => $newHash,
                ':t'  => date('Y-m-d H:i:s'),
                ':id' => $userId,
            ]);
            return ['ok' => true, 'message' => 'Password akun Anda berhasil diperbarui.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal menyimpan password baru ke database.'];
        }
    }
}
